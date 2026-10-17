<?php
namespace RoutesPro\Briefings;

if (!defined('ABSPATH')) exit;

/**
 * 9DX | fieldflow-briefing-steps-continuity-9dx-v1
 *
 * Non-destructive presentation settings for a single weekly briefing.
 * A step is a view grouping, never a question, an answer or a store scope.
 * Existing question keys and answer rows are left untouched.
 */
final class BriefingSteps {
    private const OPTION_PREFIX = 'fieldflow_briefing_steps_9dx_';
    private const MAX_STEPS = 30;

    public static function all(int $briefingId): array {
        if ($briefingId <= 0) return [];
        return self::normalize(get_option(self::OPTION_PREFIX . $briefingId, []));
    }

    private static function normalize($saved): array {
        if (!is_array($saved)) return [];
        $items = [];
        foreach (array_slice($saved, 0, self::MAX_STEPS) as $row) {
            if (!is_array($row)) continue;
            $key = sanitize_key((string)($row['key'] ?? ''));
            $title = sanitize_text_field((string)($row['title'] ?? ''));
            if ($key === '' || $title === '' || isset($items[$key])) continue;
            $items[$key] = [
                'key' => $key,
                'title' => $title,
                'description' => sanitize_text_field((string)($row['description'] ?? '')),
                'order' => max(1, min(9999, (int)($row['order'] ?? 100))),
            ];
        }
        uasort($items, static function($a, $b) {
            $compare = $a['order'] <=> $b['order'];
            return $compare !== 0 ? $compare : strcmp($a['key'], $b['key']);
        });
        return array_values($items);
    }

    public static function find(int $briefingId, string $key): array {
        $key = sanitize_key($key);
        if ($key === '') return [];
        foreach (self::all($briefingId) as $row) {
            if ($row['key'] === $key) return $row;
        }
        return [];
    }

    /** Save/add a step without changing any question, assignment, response or report. */
    public static function save(int $briefingId, string $key, string $title, string $description, int $order): array {
        if ($briefingId <= 0) return ['error' => 'Briefing inválido.'];
        $title = sanitize_text_field(trim($title));
        $description = sanitize_text_field(trim($description));
        $title = function_exists('mb_substr') ? mb_substr($title, 0, 100) : substr($title, 0, 100);
        $description = function_exists('mb_substr') ? mb_substr($description, 0, 240) : substr($description, 0, 240);
        if ($title === '') return ['error' => 'Indica o título do step.'];
        $order = max(1, min(9999, $order));
        $key = sanitize_key($key);
        $create = $key === '';
        $option = self::OPTION_PREFIX . $briefingId;
        $failure = ['error' => 'Não foi possível guardar o step. Atualiza a página e tenta novamente.'];
        global $wpdb;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            // Read the authoritative bytes, not an option/object-cache snapshot.
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s LIMIT 1", $option
            ), ARRAY_A);
            if ($wpdb->last_error !== '') return $failure;
            $raw = $row === null ? null : (string)$row['option_value'];
            $steps = self::normalize($raw === null ? [] : maybe_unserialize($raw));
            if (!$create) {
                $found = false;
                foreach ($steps as &$step) {
                    if ($step['key'] !== $key) continue;
                    $step['title'] = $title;
                    $step['description'] = $description;
                    $step['order'] = $order;
                    $found = true;
                    break;
                }
                unset($step);
                if (!$found) return ['error' => 'O step deixou de existir. Atualiza a página.'];
            } else {
                if (count($steps) >= self::MAX_STEPS) return ['error' => 'Limite de 30 steps por briefing.'];
                // Stable identity across retries; never derived from a title.
                while ($key === '' || in_array($key, array_column($steps, 'key'), true)) {
                    $key = 'bs_' . str_replace('-', '', wp_generate_uuid4());
                }
                $steps[] = compact('key', 'title', 'description', 'order');
            }
            $value = maybe_serialize(array_values($steps));
            if ($raw !== $value) {
                $result = $raw === null
                    ? $wpdb->query($wpdb->prepare(
                        "INSERT IGNORE INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no')", $option, $value
                    ))
                    : $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s", $value, $option, $raw
                    ));
                if ($result === false || $wpdb->last_error !== '') return $failure;
                if ((int)$result !== 1) continue;
            }
            wp_cache_delete($option, 'options');
            wp_cache_delete('alloptions', 'options');
            wp_cache_delete('notoptions', 'options');
            return ['key' => $key, 'error' => ''];
        }
        return $failure;
    }
}
