<?php
namespace RoutesPro\Forms;

if (!defined('ABSPATH')) exit;

/**
 * Canonical conditional-flow contract shared by the web renderer and mobile API.
 * Conditions only control visibility. They never jump over unrelated questions.
 */
class ConditionalFlow {
    public const VERSION = 3;
    public const CONTRACT = 'fieldflow-typed-form-state-conditional-core-v1';

    public static function normalise_schema(array $schema): array {
        $questions = isset($schema['questions']) && is_array($schema['questions']) ? $schema['questions'] : [];
        if (!$questions) return $schema;

        $keys = [];
        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($key !== '') $keys[$key] = true;
        }

        $graph = [];
        $normalised = [];
        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $sourceKey = sanitize_key((string)($question['key'] ?? ''));
            if ($sourceKey === '') {
                $normalised[] = $question;
                continue;
            }

            $conditions = isset($question['conditions']) && is_array($question['conditions']) ? $question['conditions'] : [];
            $rawRules = isset($conditions['rules']) && is_array($conditions['rules']) ? $conditions['rules'] : [];
            if (!$rawRules && !empty($conditions['go_to'])) {
                $rawRules = [[
                    'value' => $conditions['value'] ?? '',
                    'go_to' => $conditions['go_to'] ?? '',
                ]];
            }

            $rules = [];
            $seen = [];
            if (!empty($conditions['enabled'])) {
                foreach ($rawRules as $rule) {
                    if (!is_array($rule)) continue;
                    $value = sanitize_text_field((string)($rule['value'] ?? ''));
                    $targetKey = sanitize_key((string)($rule['go_to'] ?? ''));
                    if ($value === '' || $targetKey === '' || $targetKey === $sourceKey || !isset($keys[$targetKey])) continue;
                    $signature = self::token($value) . '|' . $targetKey;
                    if (isset($seen[$signature])) continue;

                    // Reject the edge that would close a cycle. Cyclic show rules can
                    // leave every member hidden and make the wizard look blocked.
                    if (self::path_exists($graph, $targetKey, $sourceKey)) continue;
                    $graph[$sourceKey][] = $targetKey;
                    $seen[$signature] = true;
                    $rules[] = [
                        'id' => sanitize_key((string)($rule['id'] ?? '')),
                        'value' => $value,
                        'go_to' => $targetKey,
                    ];
                }
            }

            $question['conditions'] = [
                'enabled' => !empty($rules),
                'rules' => $rules,
            ];
            $normalised[] = $question;
        }

        $schema['questions'] = $normalised;
        return $schema;
    }

    public static function payload(array $schema, array $values = []): array {
        $schema = self::normalise_schema($schema);
        $rules = [];
        $keys = [];
        $targetKeys = [];

        foreach ((array)($schema['questions'] ?? []) as $question) {
            if (!is_array($question)) continue;
            $sourceKey = sanitize_key((string)($question['key'] ?? ''));
            if ($sourceKey === '') continue;
            $keys[] = $sourceKey;
            foreach ((array)($question['conditions']['rules'] ?? []) as $rule) {
                if (!is_array($rule)) continue;
                $targetKey = sanitize_key((string)($rule['go_to'] ?? ''));
                $expected = sanitize_text_field((string)($rule['value'] ?? ''));
                if ($targetKey === '' || $expected === '') continue;
                $rules[] = [
                    'source_key' => $sourceKey,
                    'expected_value' => $expected,
                    'target_key' => $targetKey,
                    'operator' => 'equals',
                ];
                $targetKeys[$targetKey] = true;
            }
        }

        $visibility = self::evaluate($keys, $rules, $values);
        return [
            'version' => self::VERSION,
            'contract' => self::CONTRACT,
            'semantics' => 'show_target_when_visible_source_value_matches',
            'combination' => 'or',
            'rules' => $rules,
            'target_keys' => array_values(array_keys($targetKeys)),
            'root_keys' => array_values(array_filter($keys, static fn($key) => !isset($targetKeys[$key]))),
            'initial_visibility' => $visibility,
        ];
    }

    /**
     * Snapshot the resolved conditional state at submission time so downstream
     * automations never have to guess whether a dependent question was active.
     */
    public static function submission_meta(array $schema, array $visibility): array {
        $hidden = [];
        $orderKeys = [];
        $activeOrderKeys = [];
        foreach ((array)($schema['questions'] ?? []) as $question) {
            if (!is_array($question)) continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($key === '') continue;
            $type = sanitize_key((string)($question['type'] ?? 'text'));
            $visible = !array_key_exists($key, $visibility) || !empty($visibility[$key]);
            if (!$visible) $hidden[] = $key;
            if ($type === 'order_suggestion') {
                $orderKeys[] = $key;
                if ($visible) $activeOrderKeys[] = $key;
            }
        }
        return [
            'version' => self::VERSION,
            'contract' => self::CONTRACT,
            'hidden_question_keys' => array_values(array_unique($hidden)),
            'order_suggestion_keys' => array_values(array_unique($orderKeys)),
            'active_order_suggestion_keys' => array_values(array_unique($activeOrderKeys)),
        ];
    }

    public static function evaluate(array $keys, array $rules, array $values): array {
        $targets = [];
        foreach ($rules as $rule) {
            $target = sanitize_key((string)($rule['target_key'] ?? ''));
            if ($target !== '') $targets[$target] = true;
        }

        $visibility = [];
        foreach ($keys as $key) {
            $key = sanitize_key((string)$key);
            if ($key !== '') $visibility[$key] = !isset($targets[$key]);
        }

        // Monotonic fixed point. A question may become visible, but never flips
        // repeatedly during the same evaluation pass.
        $limit = max(1, count($visibility) + count($rules) + 1);
        for ($pass = 0; $pass < $limit; $pass++) {
            $changed = false;
            foreach ($rules as $rule) {
                $source = sanitize_key((string)($rule['source_key'] ?? ''));
                $target = sanitize_key((string)($rule['target_key'] ?? ''));
                if ($source === '' || $target === '' || empty($visibility[$source]) || !array_key_exists($target, $visibility)) continue;
                $actual = $values[$source] ?? null;
                if (!self::matches($actual, $rule['expected_value'] ?? '') || !empty($visibility[$target])) continue;
                $visibility[$target] = true;
                $changed = true;
            }
            if (!$changed) break;
        }
        return $visibility;
    }

    private static function path_exists(array $graph, string $from, string $to): bool {
        if ($from === $to) return true;
        $stack = [$from];
        $visited = [];
        while ($stack) {
            $node = array_pop($stack);
            if (isset($visited[$node])) continue;
            $visited[$node] = true;
            foreach ((array)($graph[$node] ?? []) as $next) {
                if ($next === $to) return true;
                if (!isset($visited[$next])) $stack[] = $next;
            }
        }
        return false;
    }

    private static function matches($actual, $expected): bool {
        $wanted = self::token($expected);
        if (is_array($actual)) {
            foreach ($actual as $value) if (self::token($value) === $wanted) return true;
            return false;
        }
        return self::token($actual) === $wanted;
    }

    private static function token($value): string {
        if (is_bool($value)) return $value ? '1' : '0';
        if ($value === null) return '';
        return strtolower(trim((string)$value));
    }
}
