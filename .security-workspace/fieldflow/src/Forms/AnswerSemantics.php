<?php
namespace RoutesPro\Forms;

if (!defined('ABSPATH')) exit;

/**
 * Canonical answer normalization and domain validation shared by Web and Mobile.
 * Historical storage remains owned by SubmissionApplicationService.
 */
final class AnswerSemantics {
    public const VERSION = 1;
    public const CONTRACT = 'fieldflow-canonical-answer-semantics-v1';

    public static function normalize(array $question, $value, bool $provided = true): array {
        $type = sanitize_key((string)($question['type'] ?? 'text')) ?: 'text';
        if (!$provided) return self::ok(null, $type, 'not_provided');

        if (in_array($type, ['number', 'currency', 'percent'], true)) {
            return self::normalize_number_question($question, $value, $type);
        }

        if ($type === 'checkbox') {
            return self::ok(self::checkbox_value($value) ? 1 : 0, $type);
        }

        if (in_array($type, ['select', 'radio', 'visual_choice', 'multi_select', 'checkbox_group'], true)) {
            return self::normalize_choice_question($question, $value, $type);
        }

        if (in_array($type, ['product_matrix', 'order_suggestion', 'share_space_matrix', 'categorized_image_upload'], true)) {
            if ($value === null || $value === '') return self::ok([], $type);
            if (!is_array($value)) return self::invalid($value, $type, 'structured_value_invalid', 'Structured answer must be an array.');
            return self::ok($value, $type);
        }

        return self::ok($value, $type);
    }

    public static function descriptor(): array {
        return [
            'contract' => self::CONTRACT,
            'version' => self::VERSION,
            'numeric_types' => ['number', 'currency', 'percent'],
            'choice_types' => ['select', 'radio', 'visual_choice', 'multi_select', 'checkbox_group'],
            'structured_types' => ['product_matrix', 'order_suggestion', 'share_space_matrix', 'categorized_image_upload'],
        ];
    }

    private static function normalize_number_question(array $question, $value, string $type): array {
        if ($value === null || (is_string($value) && trim($value) === '')) return self::ok('', $type);
        $number = self::parse_number($value);
        if ($number === null) return self::invalid($value, $type, 'number_invalid', 'Valor numerico invalido.');

        $min = self::parse_optional_bound($question['min'] ?? null);
        $max = self::parse_optional_bound($question['max'] ?? null);
        if ($min !== null && $number < $min) {
            return self::invalid($value, $type, 'number_below_min', 'Valor abaixo do minimo permitido.', ['min' => $min, 'actual' => $number]);
        }
        if ($max !== null && $number > $max) {
            return self::invalid($value, $type, 'number_above_max', 'Valor acima do maximo permitido.', ['max' => $max, 'actual' => $number]);
        }
        return self::ok($number, $type);
    }

    private static function normalize_choice_question(array $question, $value, string $type): array {
        $multiple = in_array($type, ['multi_select', 'checkbox_group'], true)
            || sanitize_key((string)($question['selection_mode'] ?? '')) === 'multiple'
            || !empty($question['multiple']);
        $allowed = self::choice_values($question, $type);

        if ($multiple) {
            $values = is_array($value) ? $value : (($value === null || $value === '') ? [] : [$value]);
            $clean = [];
            foreach ($values as $item) {
                if (!is_scalar($item)) return self::invalid($value, $type, 'choice_shape_invalid', 'Resposta de escolha invalida.');
                $token = sanitize_text_field((string)$item);
                if ($token === '' || in_array($token, $clean, true)) continue;
                if ($allowed && !in_array($token, $allowed, true)) {
                    return self::invalid($value, $type, 'choice_not_allowed', 'Opcao selecionada ja nao existe no formulario.', ['invalid_value' => $token]);
                }
                $clean[] = $token;
            }
            return self::ok(array_values($clean), $type);
        }

        if (is_array($value)) {
            $items = array_values($value);
            if (count($items) > 1) return self::invalid($value, $type, 'choice_shape_invalid', 'A pergunta permite apenas uma opcao.');
            $value = $items ? $items[0] : '';
        }
        if ($value === null || $value === '') return self::ok('', $type);
        if (!is_scalar($value)) return self::invalid($value, $type, 'choice_shape_invalid', 'Resposta de escolha invalida.');
        $token = sanitize_text_field((string)$value);
        if ($token !== '' && $allowed && !in_array($token, $allowed, true)) {
            return self::invalid($value, $type, 'choice_not_allowed', 'Opcao selecionada ja nao existe no formulario.', ['invalid_value' => $token]);
        }
        return self::ok($token, $type);
    }

    private static function choice_values(array $question, string $type): array {
        $source = $type === 'visual_choice' && is_array($question['visual_options'] ?? null)
            ? $question['visual_options']
            : (is_array($question['options'] ?? null) ? $question['options'] : []);
        $values = [];
        foreach ($source as $option) {
            if (is_scalar($option)) $candidate = (string)$option;
            elseif (is_array($option)) $candidate = (string)($option['value'] ?? $option['label'] ?? '');
            else $candidate = '';
            $candidate = sanitize_text_field($candidate);
            if ($candidate !== '' && !in_array($candidate, $values, true)) $values[] = $candidate;
        }
        return $values;
    }

    private static function parse_optional_bound($value): ?float {
        if ($value === null || (is_string($value) && trim($value) === '')) return null;
        return self::parse_number($value);
    }

    private static function parse_number($value): ?float {
        if (is_int($value) || is_float($value)) {
            $number = (float)$value;
            return is_finite($number) ? $number : null;
        }
        if (!is_string($value) && !is_numeric($value)) return null;
        $raw = trim((string)$value);
        if ($raw === '') return null;
        $raw = str_replace(["\xC2\xA0", ' '], '', $raw);
        $comma = strrpos($raw, ',');
        $dot = strrpos($raw, '.');
        if ($comma !== false && $dot !== false) {
            if ($comma > $dot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($comma !== false) {
            $raw = str_replace(',', '.', $raw);
        }
        if (!preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $raw)) return null;
        $number = (float)$raw;
        return is_finite($number) ? $number : null;
    }

    private static function checkbox_value($value): bool {
        if ($value === true || $value === 1 || $value === 1.0) return true;
        if ($value === false || $value === 0 || $value === 0.0 || $value === null) return false;
        $token = strtolower(trim((string)$value));
        if ($token === '') return false;
        return !in_array($token, ['0', 'false', 'off', 'no', 'nao', 'não'], true);
    }

    private static function ok($value, string $type, string $reason = ''): array {
        return [
            'contract' => self::CONTRACT,
            'valid' => true,
            'type' => $type,
            'value' => $value,
            'issue' => '',
            'message' => '',
            'meta' => $reason !== '' ? ['reason' => $reason] : [],
        ];
    }

    private static function invalid($value, string $type, string $issue, string $message, array $meta = []): array {
        return [
            'contract' => self::CONTRACT,
            'valid' => false,
            'type' => $type,
            'value' => $value,
            'issue' => $issue,
            'message' => $message,
            'meta' => $meta,
        ];
    }
}
