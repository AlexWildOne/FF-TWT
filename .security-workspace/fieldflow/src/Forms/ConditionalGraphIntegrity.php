<?php
namespace RoutesPro\Forms;

if (!defined('ABSPATH')) exit;

/**
 * Non-blocking integrity audit for the form conditional graph.
 *
 * The conditional engine keeps its existing OR visibility semantics. This
 * service only explains graph ownership and highlights suspicious cross-domain
 * gates so editors can review them before they reach the field.
 */
final class ConditionalGraphIntegrity {
    public const VERSION = 1;
    public const CONTRACT = 'fieldflow-condition-graph-integrity-v1';

    public static function analyse(array $schema): array {
        $questions = isset($schema['questions']) && is_array($schema['questions']) ? $schema['questions'] : [];
        $nodes = [];
        $edges = [];
        $controlled = [];

        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($key === '') continue;
            $nodes[$key] = [
                'key' => $key,
                'label' => sanitize_text_field((string)($question['label'] ?? $key)),
                'type' => sanitize_key((string)($question['type'] ?? 'text')),
                'domain' => self::domain($question),
            ];
        }

        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $sourceKey = sanitize_key((string)($question['key'] ?? ''));
            if ($sourceKey === '' || !isset($nodes[$sourceKey])) continue;
            $conditions = isset($question['conditions']) && is_array($question['conditions']) ? $question['conditions'] : [];
            if (empty($conditions['enabled'])) continue;
            $rules = isset($conditions['rules']) && is_array($conditions['rules']) ? $conditions['rules'] : [];
            if (!$rules && !empty($conditions['go_to'])) {
                $rules = [[
                    'value' => $conditions['value'] ?? '',
                    'go_to' => $conditions['go_to'] ?? '',
                ]];
            }
            foreach ($rules as $rule) {
                if (!is_array($rule)) continue;
                $targetKey = sanitize_key((string)($rule['go_to'] ?? ''));
                $value = sanitize_text_field((string)($rule['value'] ?? ''));
                if ($targetKey === '' || $value === '' || $targetKey === $sourceKey || !isset($nodes[$targetKey])) continue;
                $edge = [
                    'source_key' => $sourceKey,
                    'source_label' => $nodes[$sourceKey]['label'],
                    'source_domain' => $nodes[$sourceKey]['domain'],
                    'value' => $value,
                    'target_key' => $targetKey,
                    'target_label' => $nodes[$targetKey]['label'],
                    'target_domain' => $nodes[$targetKey]['domain'],
                ];
                $edges[] = $edge;
                if (!isset($controlled[$targetKey])) $controlled[$targetKey] = [];
                $controlled[$targetKey][] = [
                    'source_key' => $sourceKey,
                    'source_label' => $nodes[$sourceKey]['label'],
                    'value' => $value,
                ];
            }
        }

        $warnings = [];
        foreach ($edges as $edge) {
            if (!self::cross_domain_gate($edge['source_domain'], $edge['target_domain'])) continue;
            $warnings[] = [
                'code' => 'cross_domain_gate',
                'severity' => 'warning',
                'source_key' => $edge['source_key'],
                'source_label' => $edge['source_label'],
                'source_domain' => $edge['source_domain'],
                'value' => $edge['value'],
                'target_key' => $edge['target_key'],
                'target_label' => $edge['target_label'],
                'target_domain' => $edge['target_domain'],
                'message' => sprintf(
                    'A pergunta "%s" controla um ramo funcional diferente, "%s". Confirma que esta exclusividade é intencional.',
                    $edge['source_label'],
                    $edge['target_label']
                ),
            ];
        }

        return [
            'version' => self::VERSION,
            'contract' => self::CONTRACT,
            'semantics' => 'non_blocking_graph_audit',
            'combination' => 'or',
            'question_count' => count($nodes),
            'rule_count' => count($edges),
            'controlled_question_count' => count($controlled),
            'warning_count' => count($warnings),
            'has_warnings' => !empty($warnings),
            'controlled_by' => $controlled,
            'warnings' => $warnings,
        ];
    }

    private static function cross_domain_gate(string $source, string $target): bool {
        if ($source === '' || $target === '' || $source === $target) return false;
        if (($source === 'conquest' && $target === 'order') || ($source === 'order' && $target === 'conquest')) return true;
        return false;
    }

    private static function domain(array $question): string {
        $type = sanitize_key((string)($question['type'] ?? ''));
        $text = self::token((string)($question['key'] ?? '') . ' ' . (string)($question['label'] ?? ''));

        if ($type === 'order_suggestion' || self::contains_any($text, ['encomenda','order','pedido','sugestao de encomenda','sugestaoencomenda','sugestaodeencomenda'])) return 'order';
        if (self::contains_any($text, ['conquista','implementacao','implementacoes','expositor','arca','display','topo','orelha','planograma'])) return 'conquest';
        if (in_array($type, ['categorized_image_upload','evidence_check','image_upload'], true) || self::contains_any($text, ['evidencia','foto','fotografia','imagem','prova'])) return 'evidence';
        if ($type === 'briefing_block' || self::contains_any($text, ['briefing'])) return 'briefing';
        return 'general';
    }

    private static function contains_any(string $text, array $needles): bool {
        foreach ($needles as $needle) if ($needle !== '' && strpos($text, $needle) !== false) return true;
        return false;
    }

    private static function token(string $value): string {
        $value = strtolower(trim($value));
        return strtr($value, [
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c',
        ]);
    }
}
