<?php
namespace RoutesPro\Forms;

if (!defined('ABSPATH')) exit;

/**
 * Semantic evidence/conformity question.
 *
 * Editors persist a single evidence_check question. At runtime it is expanded
 * into primitives already supported by web and mobile clients:
 * - radio: Sim / Não / Não aplicável
 * - image_upload shown when the answer is Sim
 * - textarea shown when the answer is Não
 *
 * This keeps the mobile contract backwards compatible while preserving a
 * richer and exportable evidence trail.
 */
class EvidenceFlow {
    public const TYPE = 'evidence_check';
    public const VERSION = 2;
    private const PHOTO_SUFFIX = '__evidence_photo';
    private const REASON_SUFFIX = '__evidence_reason';

    public static function expand_schema(array $schema): array {
        $questions = isset($schema['questions']) && is_array($schema['questions']) ? $schema['questions'] : [];
        if (!$questions) return $schema;

        $expanded = [];
        $replacementMap = [];
        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $type = sanitize_key((string)($question['type'] ?? 'text'));
            $parentKey = sanitize_key((string)($question['key'] ?? ''));
            if ($type !== self::TYPE || $parentKey === '') {
                $expanded[] = $question;
                continue;
            }

            $label = sanitize_text_field((string)($question['label'] ?? $parentKey));
            $photoKey = self::child_key($parentKey, self::PHOTO_SUFFIX);
            $reasonKey = self::child_key($parentKey, self::REASON_SUFFIX);

            $parent = $question;
            $parent['key'] = $parentKey;
            $parent['type'] = 'radio';
            $parent['options'] = ['Sim', 'Não', 'Não aplicável'];
            $parent['selection_mode'] = 'single';
            $parent['multiple'] = false;
            $parent['evidence_flow_version'] = self::VERSION;
            $parent['evidence_flow_role'] = 'answer';
            $parent['evidence_flow_parent'] = $parentKey;
            $parent['evidence_flow_photo_key'] = $photoKey;
            $parent['evidence_flow_reason_key'] = $reasonKey;
            if (trim((string)($parent['help_text'] ?? '')) === '') {
                $parent['help_text'] = 'Se responderes Sim, podes adicionar fotografias. Se responderes Não, podes indicar o motivo da não conformidade.';
            }
            $parent['evidence_require_photo_on_yes'] = !empty($question['evidence_require_photo_on_yes']);
            $parent['evidence_require_reason_on_no'] = !empty($question['evidence_require_reason_on_no']);
            $parent['conditions'] = self::parent_conditions($question, $photoKey, $reasonKey);
            $expanded[] = $parent;

            $photo = self::companion_base($question, $photoKey, 'Fotografia de evidência, ' . $label, 'photo', $parentKey);
            $photo['type'] = 'image_upload';
            $photo['required'] = false;
            $photo['multiple'] = true;
            $photo['help_text'] = !empty($parent['evidence_require_photo_on_yes'])
                ? 'Adiciona pelo menos uma fotografia que comprove a conformidade.'
                : 'Opcional. Adiciona uma ou várias fotografias que comprovem a conformidade.';
            $expanded[] = $photo;

            $reason = self::companion_base($question, $reasonKey, 'Motivo da não conformidade, ' . $label, 'reason', $parentKey);
            $reason['type'] = 'textarea';
            // A obrigatoriedade é condicional e validada por EvidenceFlow::validate_answers().
            $reason['required'] = false;
            $reason['help_text'] = !empty($parent['evidence_require_reason_on_no'])
                ? 'Explica de forma objetiva o motivo da não conformidade.'
                : 'Opcional. Se for útil, explica o motivo da não conformidade.';
            $expanded[] = $reason;

            $replacementMap[$parentKey] = [$parentKey, $photoKey, $reasonKey];
        }

        if (!$replacementMap) return $schema;
        $schema['questions'] = $expanded;
        $schema['evidence_flow'] = [
            'version' => self::VERSION,
            'type' => self::TYPE,
            'semantics' => 'yes_optional_photo_no_optional_reason',
        ];
        $schema = self::expand_layout($schema, $replacementMap);
        return $schema;
    }

    /**
     * Validates conditional evidence requirements after the regular answer
     * extraction has happened. Returns WP_Error on the first invalid branch.
     */
    public static function validate_answers(array $schema, array $answers) {
        foreach ((array)($schema['questions'] ?? []) as $question) {
            if (!is_array($question) || sanitize_key((string)($question['evidence_flow_role'] ?? '')) !== 'answer') continue;
            $parentKey = sanitize_key((string)($question['key'] ?? ''));
            if ($parentKey === '') continue;

            $answer = self::answer_value($answers[$parentKey] ?? null);
            $token = self::token($answer);

            if ($token === 'sim' && !empty($question['evidence_require_photo_on_yes'])) {
                $photoKey = sanitize_key((string)($question['evidence_flow_photo_key'] ?? self::child_key($parentKey, self::PHOTO_SUFFIX)));
                $photo = self::answer_value($answers[$photoKey] ?? null);
                if (!self::has_value($photo)) {
                    return new \WP_Error(
                        'fieldflow_evidence_photo_required',
                        'Adiciona a fotografia de evidência pedida nesta pergunta.',
                        ['status' => 422, 'question_key' => $photoKey, 'parent_question_key' => $parentKey]
                    );
                }
            }

            if ($token === 'nao' && !empty($question['evidence_require_reason_on_no'])) {
                $reasonKey = sanitize_key((string)($question['evidence_flow_reason_key'] ?? self::child_key($parentKey, self::REASON_SUFFIX)));
                $reason = self::answer_value($answers[$reasonKey] ?? null);
                if (!self::has_value($reason)) {
                    return new \WP_Error(
                        'fieldflow_evidence_reason_required',
                        'Indica o motivo da não conformidade.',
                        ['status' => 422, 'question_key' => $reasonKey, 'parent_question_key' => $parentKey]
                    );
                }
            }
        }
        return null;
    }

    public static function is_photo_key(string $key): bool {
        return self::ends_with(sanitize_key($key), self::PHOTO_SUFFIX);
    }

    public static function is_reason_key(string $key): bool {
        return self::ends_with(sanitize_key($key), self::REASON_SUFFIX);
    }


    public static function photo_key_for_parent(string $parentKey): string {
        return self::child_key($parentKey, self::PHOTO_SUFFIX);
    }

    public static function reason_key_for_parent(string $parentKey): string {
        return self::child_key($parentKey, self::REASON_SUFFIX);
    }

    private static function companion_base(array $source, string $key, string $label, string $role, string $parentKey): array {
        $out = [
            'key' => $key,
            'label' => sanitize_text_field($label),
            'required' => false,
            'show_previous_on_start' => false,
            'evidence_flow_version' => self::VERSION,
            'evidence_flow_role' => $role,
            'evidence_flow_parent' => $parentKey,
        ];
        foreach ([
            'source','context_question_id','context_type','form_id','source_type','briefing_id','briefing_version_no',
            'briefing_section_id','briefing_item_key','briefing_title','valid_from','valid_to','briefing_block_key'
        ] as $metaKey) {
            if (array_key_exists($metaKey, $source)) $out[$metaKey] = $source[$metaKey];
        }
        return $out;
    }

    private static function parent_conditions(array $question, string $photoKey, string $reasonKey): array {
        $conditions = isset($question['conditions']) && is_array($question['conditions']) ? $question['conditions'] : [];
        $rules = [];
        if (!empty($conditions['enabled'])) {
            $sourceRules = isset($conditions['rules']) && is_array($conditions['rules']) ? $conditions['rules'] : [];
            if (!$sourceRules && !empty($conditions['go_to'])) {
                $sourceRules = [[
                    'value' => $conditions['value'] ?? '',
                    'go_to' => $conditions['go_to'] ?? '',
                ]];
            }
            foreach ($sourceRules as $rule) if (is_array($rule)) $rules[] = $rule;
        }
        $rules[] = ['id' => 'ff_evidence_sim', 'value' => 'Sim', 'go_to' => $photoKey];
        $rules[] = ['id' => 'ff_evidence_nao', 'value' => 'Não', 'go_to' => $reasonKey];
        return ['enabled' => true, 'rules' => $rules];
    }

    private static function expand_layout(array $schema, array $replacementMap): array {
        if (empty($schema['layout']) || !is_array($schema['layout'])) return $schema;
        $layout = $schema['layout'];

        if (!empty($layout['steps']) && is_array($layout['steps'])) {
            foreach ($layout['steps'] as &$step) {
                if (!is_array($step) || empty($step['fields']) || !is_array($step['fields'])) continue;
                $fields = [];
                foreach ($step['fields'] as $field) {
                    $field = sanitize_key((string)$field);
                    if ($field === '') continue;
                    if (isset($replacementMap[$field])) {
                        foreach ($replacementMap[$field] as $replacementKey) $fields[] = $replacementKey;
                    } else {
                        $fields[] = $field;
                    }
                }
                $step['fields'] = array_values(array_unique($fields));
            }
            unset($step);
        }

        if (!empty($layout['field_layout']) && is_array($layout['field_layout'])) {
            foreach ($replacementMap as $parentKey => $replacementKeys) {
                $parentLayout = isset($layout['field_layout'][$parentKey]) && is_array($layout['field_layout'][$parentKey])
                    ? $layout['field_layout'][$parentKey]
                    : ['width' => 100];
                foreach ($replacementKeys as $replacementKey) {
                    if (!isset($layout['field_layout'][$replacementKey])) $layout['field_layout'][$replacementKey] = $parentLayout;
                }
            }
        }

        $schema['layout'] = $layout;
        return $schema;
    }

    private static function child_key(string $parentKey, string $suffix): string {
        $parentKey = sanitize_key($parentKey);
        $maxLength = 180;
        if (strlen($parentKey . $suffix) <= $maxLength) return sanitize_key($parentKey . $suffix);
        $hash = substr(hash('sha256', $parentKey), 0, 10);
        $room = max(20, $maxLength - strlen($suffix) - strlen($hash) - 1);
        return sanitize_key(substr($parentKey, 0, $room) . '_' . $hash . $suffix);
    }

    private static function answer_value($answer) {
        if (is_array($answer) && array_key_exists('value', $answer)) return $answer['value'];
        return $answer;
    }

    private static function has_value($value): bool {
        if (is_array($value)) {
            foreach ($value as $item) if (self::has_value($item)) return true;
            return false;
        }
        if ($value === null || is_bool($value)) return $value === true;
        return trim((string)$value) !== '';
    }

    private static function token($value): string {
        if (is_array($value)) $value = reset($value);
        $value = trim((string)$value);
        if (function_exists('remove_accents')) $value = remove_accents($value);
        return strtolower($value);
    }

    private static function ends_with(string $haystack, string $needle): bool {
        if ($needle === '') return true;
        if (strlen($needle) > strlen($haystack)) return false;
        return substr($haystack, -strlen($needle)) === $needle;
    }
}
