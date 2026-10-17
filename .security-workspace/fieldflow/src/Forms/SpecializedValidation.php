<?php
namespace RoutesPro\Forms;

use RoutesPro\Briefings\ShareSpaceMatrix;

if (!defined('ABSPATH')) exit;

/**
 * Canonical server mirror for specialised form-field validation.
 *
 * Generic active/required/persistence semantics stay in SubmissionValidation.
 * This class owns only question types whose validity depends on structured
 * media or matrix semantics, and is shared by web and mobile submissions.
 */
final class SpecializedValidation {
    public const VERSION = 1;
    public const CONTRACT = 'fieldflow-typed-specialized-validation-core-v1';

    public static function validate_schema(array $schema, array $answers, array $visibility = []) {
        foreach ((array)($schema['questions'] ?? []) as $question) {
            if (!is_array($question)) continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($key === '' || !SubmissionValidation::is_question_active($key, $visibility)) continue;
            $error = self::validate_question($question, self::answer_value($answers[$key] ?? null), $answers);
            if (is_wp_error($error)) return $error;
        }
        return null;
    }

    public static function validate_question(array $question, $value, array $answers = []) {
        $key = sanitize_key((string)($question['key'] ?? ''));
        $type = sanitize_key((string)($question['type'] ?? 'text')) ?: 'text';
        $required = !empty($question['required']);

        if (sanitize_key((string)($question['evidence_flow_role'] ?? '')) === 'answer') {
            $answer = self::token($value);
            if (($answer === 'sim' || $answer === 'yes') && !empty($question['evidence_require_photo_on_yes'])) {
                $photoKey = sanitize_key((string)($question['evidence_flow_photo_key'] ?? EvidenceFlow::photo_key_for_parent($key)));
                if (!self::has_media_value(self::answer_value($answers[$photoKey] ?? null))) {
                    return self::error('fieldflow_evidence_photo_required', 'Adiciona a fotografia de evidência pedida nesta pergunta.', $photoKey, $key);
                }
            }
            if (($answer === 'nao' || $answer === 'não' || $answer === 'no') && !empty($question['evidence_require_reason_on_no'])) {
                $reasonKey = sanitize_key((string)($question['evidence_flow_reason_key'] ?? EvidenceFlow::reason_key_for_parent($key)));
                if (self::is_empty(self::answer_value($answers[$reasonKey] ?? null))) {
                    return self::error('fieldflow_evidence_reason_required', 'Indica o motivo da não conformidade.', $reasonKey, $key);
                }
            }
        }

        if (in_array($type, ['image_upload', 'file_upload'], true) && $required && !self::has_media_value($value)) {
            return self::error('fieldflow_specialized_media_required', 'Adiciona o ficheiro obrigatório antes de concluir.', $key);
        }

        if ($type === 'categorized_image_upload') {
            $state = self::categorized_state($required, $value);
            if (!$state['valid']) {
                $message = $state['issue'] === 'categorized_selection_required'
                    ? 'Seleciona pelo menos uma categoria e adiciona a evidência pedida.'
                    : 'Adiciona pelo menos uma fotografia em cada categoria selecionada.';
                return self::error('fieldflow_specialized_' . $state['issue'], $message, $key, '', ['missing_categories' => $state['missing']]);
            }
        }

        if ($type === 'share_space_matrix') {
            $state = self::share_space_state($required, $value, (array)($question['question_config'] ?? ($question['share_space_rows'] ?? [])));
            if (!$state['valid']) {
                $message = $state['issue'] === 'share_space_configuration_missing'
                    ? 'Esta pergunta comparativa não contém referências válidas.'
                    : 'Completa todos os comparativos obrigatórios. Quando não existirem frentes, introduz 0 para registar esse valor explicitamente.';
                return self::error('fieldflow_specialized_' . $state['issue'], $message, $key, '', ['missing_metric_keys' => $state['missing']]);
            }
        }

        if ($type === 'product_matrix' && $required && !self::product_matrix_has_meaningful_row($value)) {
            return self::error('fieldflow_specialized_product_matrix_required', 'Preenche pelo menos uma referência na matriz de produtos.', $key);
        }

        return null;
    }

    public static function has_media_value($value): bool {
        if ($value === null || $value === '') return false;
        if (is_string($value)) return trim($value) !== '';
        if (is_int($value) || is_float($value)) return (float)$value > 0;
        if (is_bool($value)) return $value;
        if (!is_array($value)) return false;
        if (!$value) return false;

        foreach (['url','media_ref','mediaRef','path','src'] as $key) {
            if (isset($value[$key]) && is_scalar($value[$key]) && trim((string)$value[$key]) !== '') return true;
        }
        if (isset($value['size']) && is_numeric($value['size']) && (float)$value['size'] > 0) return true;
        foreach (['images','files'] as $key) {
            if (isset($value[$key]) && is_array($value[$key]) && self::has_media_value($value[$key])) return true;
        }
        $isList = array_keys($value) === range(0, count($value) - 1);
        if ($isList) foreach ($value as $item) if (self::has_media_value($item)) return true;
        return false;
    }

    public static function categorized_state(bool $required, $value): array {
        $rows = is_array($value) ? $value : [];
        $selected = [];
        $missing = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $category = sanitize_key((string)($row['category_key'] ?? $row['key'] ?? ''));
            if ($category === '') continue;
            $selected[$category] = true;
            if (!self::has_media_value($row['images'] ?? $row['files'] ?? null)) $missing[$category] = true;
        }
        if ($required && !$selected) return ['valid'=>false,'issue'=>'categorized_selection_required','missing'=>[]];
        if ($missing) return ['valid'=>false,'issue'=>'categorized_media_required','missing'=>array_keys($missing)];
        return ['valid'=>true,'issue'=>'','missing'=>[]];
    }

    public static function share_space_state(bool $required, $value, array $config): array {
        if (!$required) return ['valid'=>true,'issue'=>'','missing'=>[]];
        $cleanConfig = class_exists(ShareSpaceMatrix::class) ? ShareSpaceMatrix::sanitizeConfig($config) : $config;
        $expected = [];
        foreach ((array)($cleanConfig['rows'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $key = sanitize_key((string)($row['metric_key'] ?? ''));
            if ($key !== '') $expected[$key] = true;
        }
        if (!$expected) return ['valid'=>false,'issue'=>'share_space_configuration_missing','missing'=>[]];
        $byKey = [];
        foreach ((array)$value as $row) {
            if (!is_array($row)) continue;
            $key = sanitize_key((string)($row['metric_key'] ?? ''));
            if ($key !== '') $byKey[$key] = $row;
        }
        $missing = [];
        foreach (array_keys($expected) as $key) {
            $row = $byKey[$key] ?? null;
            if (!$row || (!self::non_negative_numeric_input($row['pr_fronts'] ?? null) && !self::non_negative_numeric_input($row['competitor_fronts'] ?? null))) {
                $missing[] = $key;
            }
        }
        if ($missing) return ['valid'=>false,'issue'=>'share_space_incomplete','missing'=>$missing];
        return ['valid'=>true,'issue'=>'','missing'=>[]];
    }

    public static function product_matrix_has_meaningful_row($value): bool {
        foreach ((array)$value as $row) {
            if (!is_array($row)) continue;
            foreach (['category','ref','reference','name','product'] as $key) {
                if (isset($row[$key]) && is_scalar($row[$key]) && trim((string)$row[$key]) !== '') return true;
            }
            foreach (['qty','quantity','before','after','order_qty','boxes'] as $key) {
                if (array_key_exists($key, $row) && self::non_negative_numeric_input($row[$key])) return true;
            }
        }
        return false;
    }

    private static function answer_value($answer) {
        return is_array($answer) && array_key_exists('value', $answer) ? $answer['value'] : $answer;
    }

    private static function token($value): string {
        if ($value === true) return '1';
        if ($value === false || $value === null) return $value === false ? '0' : '';
        return strtolower(trim((string)$value));
    }

    private static function is_empty($value): bool {
        if ($value === null || $value === '') return true;
        if (is_array($value)) {
            if (!$value) return true;
            foreach ($value as $part) if (!self::is_empty($part)) return false;
            return true;
        }
        return false;
    }

    private static function non_negative_numeric_input($value): bool {
        if ($value === null || $value === '') return false;
        if (is_string($value)) $value = str_replace(',', '.', trim($value));
        return is_numeric($value) && is_finite((float)$value) && (float)$value >= 0;
    }

    private static function error(string $code, string $message, string $questionKey, string $parentKey = '', array $extra = []) {
        return new \WP_Error($code, $message, array_merge([
            'status' => 422,
            'question_key' => sanitize_key($questionKey),
            'parent_question_key' => sanitize_key($parentKey),
            'contract' => self::CONTRACT,
        ], $extra));
    }
}
