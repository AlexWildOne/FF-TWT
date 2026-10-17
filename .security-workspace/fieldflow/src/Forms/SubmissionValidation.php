<?php
namespace RoutesPro\Forms;

if (!defined('ABSPATH')) exit;

/**
 * Canonical server mirror for the typed submission validation contract.
 * Keeps generic active/required/persistence semantics identical between web,
 * mobile and the TypeScript frontend. Specialised evidence/media/cardex
 * validation remains in its dedicated services.
 */
final class SubmissionValidation {
    public const VERSION = 1;
    public const CONTRACT = 'fieldflow-typed-submission-validation-core-v1';

    public static function is_question_active(string $key, array $visibility = []): bool {
        $key = sanitize_key($key);
        if ($key === '') return false;
        return !array_key_exists($key, $visibility) || !empty($visibility[$key]);
    }

    public static function required_missing(array $question, $value, array $visibility = []): bool {
        $key = sanitize_key((string)($question['key'] ?? ''));
        if ($key === '' || empty($question['required']) || !self::is_question_active($key, $visibility)) return false;
        $type = sanitize_key((string)($question['type'] ?? 'text')) ?: 'text';
        if ($type === 'checkbox') return !self::checkbox_checked($value);
        return self::is_empty($value, $type);
    }

    public static function should_persist($value, string $type = 'text', bool $provided = true): bool {
        $type = sanitize_key($type) ?: 'text';
        if ($type === 'checkbox') return $provided;
        return !self::is_empty($value, $type);
    }

    public static function filter_active_values(array $values, array $visibility = []): array {
        $out = [];
        foreach ($values as $key => $value) {
            $clean = sanitize_key((string)$key);
            if ($clean !== '' && self::is_question_active($clean, $visibility)) $out[$clean] = $value;
        }
        return $out;
    }

    public static function validate(array $questions, array $values, array $visibility = []): array {
        $active = [];
        $hidden = [];
        $required = [];
        $missing = [];
        $activeValues = [];
        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($key === '') continue;
            $type = sanitize_key((string)($question['type'] ?? 'text')) ?: 'text';
            if (!self::is_question_active($key, $visibility)) {
                $hidden[] = $key;
                continue;
            }
            $active[] = $key;
            if (!empty($question['required'])) $required[] = $key;
            $provided = array_key_exists($key, $values);
            $value = $provided ? $values[$key] : null;
            if (self::required_missing($question, $value, $visibility)) $missing[] = $key;
            if (self::should_persist($value, $type, $provided)) $activeValues[$key] = $value;
        }
        return [
            'contract' => self::CONTRACT,
            'valid' => !$missing,
            'active_keys' => array_values($active),
            'hidden_keys' => array_values($hidden),
            'required_keys' => array_values($required),
            'missing_required_keys' => array_values($missing),
            'active_values' => $activeValues,
        ];
    }

    private static function checkbox_checked($value): bool {
        if ($value === true || $value === 1) return true;
        $token = strtolower(trim((string)($value ?? '')));
        return $token !== '' && !in_array($token, ['0','false','off','no','nao','não'], true);
    }

    private static function is_empty($value, string $type): bool {
        if ($value === null || $value === '') return true;
        if (is_array($value)) {
            if (!$value) return true;
            foreach ($value as $part) if (!self::is_empty($part, $type)) return false;
            return true;
        }
        if (is_bool($value)) return $type === 'checkbox' ? !$value : false;
        if (is_float($value) && !is_finite($value)) return true;
        return false;
    }
}
