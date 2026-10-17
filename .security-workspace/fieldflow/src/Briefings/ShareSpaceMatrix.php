<?php
namespace RoutesPro\Briefings;

if (!defined('ABSPATH')) exit;

class ShareSpaceMatrix {
    public static function splitMetric(string $metric): array {
        $metric = trim(wp_strip_all_tags($metric));
        $parts = preg_split('/\s+vs\.?\s+/iu', $metric, 2);
        $pr = trim((string)($parts[0] ?? $metric));
        $competitor = trim((string)($parts[1] ?? 'Concorrência'));
        if ($pr === '') $pr = 'Produto Pernod Ricard';
        if ($competitor === '') $competitor = 'Concorrência';
        return [$pr, $competitor];
    }

    public static function rowFromItem(array $item, int $index = 0): array {
        $metric = sanitize_text_field((string)self::firstValue($item, ['metric','comparison','title','label','name']));
        $leftExplicit = sanitize_text_field((string)self::firstValue($item, ['pr_label','reference_a','left_label','product','product_name','reference','brand_a']));
        $rightExplicit = sanitize_text_field((string)self::firstValue($item, ['competitor_label','reference_b','right_label','competitor','competitor_name','comparison_reference','brand_b']));
        [$metricLeft, $metricRight] = self::splitMetric($metric);
        $pr = $leftExplicit !== '' ? $leftExplicit : $metricLeft;
        $competitor = $rightExplicit !== '' ? $rightExplicit : $metricRight;
        if ($metric === '' && ($pr !== '' || $competitor !== '')) $metric = trim($pr . ' vs ' . $competitor);
        if ($pr === '') $pr = 'Referência A';
        if ($competitor === '') $competitor = 'Referência B';
        $target = self::percentValue(self::firstValue($item, ['target','objective','goal','target_share']));
        $referenceShare = self::percentValue(self::firstValue($item, ['share','reference_share','pr_share','left_share']));
        $referenceCompetitor = self::percentValue(self::firstValue($item, ['competitor_share','reference_competitor_share','right_share']));
        $keySource = sanitize_text_field((string)($item['metric_key'] ?? ''));
        if ($keySource === '') $keySource = $metric !== '' ? $metric : ($pr . '-' . $competitor . '-' . $index);
        $metricKey = sanitize_key(substr($keySource, 0, 150));
        if ($metricKey === '') $metricKey = 'comparison_' . ($index + 1);
        return [
            'metric_key' => $metricKey,
            'metric' => $metric !== '' ? $metric : ($pr . ' vs ' . $competitor),
            'pr_label' => $pr,
            'competitor_label' => $competitor,
            'target' => $target,
            'reference_share' => $referenceShare,
            'reference_competitor_share' => $referenceCompetitor,
            'reference_week' => absint($item['reference_week'] ?? 0),
        ];
    }

    public static function configFromItems(array $items, array $meta = []): array {
        $rows = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) continue;
            $row = self::rowFromItem($item, (int)$index);
            if ($row['metric'] === '') continue;
            $rows[] = $row;
        }
        return self::sanitizeConfig([
            'version' => 1,
            'calculation' => 'fronts_ratio',
            'rows' => $rows,
            'reference_week' => absint($meta['reference_week'] ?? $meta['week'] ?? 0),
            'source' => sanitize_key((string)($meta['source'] ?? 'briefing_comparison')) ?: 'briefing_comparison',
        ]);
    }

    public static function configFromSection(array $content, string $sectionType = '', array $meta = []): array {
        $stored = self::sanitizeConfig($content['question_config'] ?? []);
        if (!empty($stored['rows'])) return $stored;

        $sectionType = sanitize_key($sectionType);
        $candidates = [];
        foreach (['items','comparisons','comparison_rows','rows','references'] as $key) {
            if (!empty($content[$key]) && is_array($content[$key])) {
                foreach ($content[$key] as $item) if (is_array($item)) $candidates[] = $item;
            }
        }
        $explicit = [];
        foreach ($candidates as $item) {
            $metric = trim((string)self::firstValue($item, ['metric','comparison','title','label','name']));
            $left = trim((string)self::firstValue($item, ['pr_label','reference_a','left_label','product','product_name','reference','brand_a']));
            $right = trim((string)self::firstValue($item, ['competitor_label','reference_b','right_label','competitor','competitor_name','comparison_reference','brand_b']));
            $hasPair = ($left !== '' && $right !== '') || preg_match('/\s+vs\.?\s+/iu', $metric);
            if ($sectionType === 'share_space' || $hasPair) $explicit[] = $item;
        }
        if (!$explicit) return self::sanitizeConfig([]);
        return self::configFromItems($explicit, [
            'reference_week' => absint($meta['reference_week'] ?? $meta['week'] ?? $content['week'] ?? 0),
            'source' => sanitize_key((string)($meta['source'] ?? ('briefing_' . ($sectionType ?: 'comparison')))) ?: 'briefing_comparison',
        ]);
    }

    public static function sanitizeConfig($config): array {
        if (is_string($config)) $config = json_decode(wp_unslash($config), true);
        if (!is_array($config)) $config = [];
        $rows = [];
        $usedKeys = [];
        foreach ((array)($config['rows'] ?? []) as $index => $item) {
            if (!is_array($item)) continue;
            $row = self::rowFromItem($item, (int)$index);
            if ($row['metric'] === '') continue;
            $baseKey = $row['metric_key'];
            $suffix = 2;
            while (isset($usedKeys[$row['metric_key']])) $row['metric_key'] = sanitize_key($baseKey . '_' . $suffix++);
            $usedKeys[$row['metric_key']] = true;
            $rows[] = $row;
            if (count($rows) >= 40) break;
        }
        return [
            'version' => 1,
            'calculation' => 'fronts_ratio',
            'rows' => $rows,
            'reference_week' => absint($config['reference_week'] ?? 0),
            'source' => sanitize_key((string)($config['source'] ?? 'briefing_comparison')) ?: 'briefing_comparison',
        ];
    }

    public static function calculateAnswers($raw, array $config): array {
        if (!is_array($raw)) return [];
        $config = self::sanitizeConfig($config);
        $postedByKey = [];
        foreach ($raw as $row) {
            if (!is_array($row)) continue;
            $key = sanitize_key((string)($row['metric_key'] ?? ''));
            if ($key !== '') $postedByKey[$key] = $row;
        }
        $answers = [];
        foreach ($config['rows'] as $definition) {
            $key = sanitize_key((string)$definition['metric_key']);
            $posted = $postedByKey[$key] ?? [];
            $pr = self::nonNegativeNumber($posted['pr_fronts'] ?? null);
            $competitor = self::nonNegativeNumber($posted['competitor_fronts'] ?? null);
            $hasInput = $pr !== null || $competitor !== null;
            if (!$hasInput) continue;
            $pr = $pr ?? 0.0;
            $competitor = $competitor ?? 0.0;
            $total = $pr + $competitor;
            $share = $total > 0 ? round(($pr / $total) * 100, 2) : 0.0;
            $competitorShare = $total > 0 ? round(($competitor / $total) * 100, 2) : 0.0;
            $target = (float)$definition['target'];
            $gap = round($share - $target, 2);
            $answers[] = array_merge($definition, [
                'pr_fronts' => self::compactNumber($pr),
                'competitor_fronts' => self::compactNumber($competitor),
                'total_fronts' => self::compactNumber($total),
                'pr_share' => $share,
                'competitor_share' => $competitorShare,
                'gap' => $gap,
                'status' => $total <= 0 ? 'no_fronts' : ($gap < 0 ? 'below' : 'on_or_above'),
            ]);
        }
        return $answers;
    }

    public static function isComplete(array $answers, array $config): bool {
        $expected = count((array)(self::sanitizeConfig($config)['rows'] ?? []));
        return $expected > 0 && count($answers) >= $expected;
    }

    private static function firstValue(array $item, array $keys) {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $item)) continue;
            $value = $item[$key];
            if (is_scalar($value) && trim((string)$value) !== '') return $value;
        }
        return '';
    }

    private static function percentValue($value): float {
        if (!is_numeric($value)) return 0.0;
        $number = (float)$value;
        if (abs($number) <= 1.000001) $number *= 100;
        return round($number, 2);
    }

    private static function nonNegativeNumber($value): ?float {
        if ($value === null || $value === '') return null;
        if (is_string($value)) $value = str_replace(',', '.', trim($value));
        if (!is_numeric($value)) return null;
        return round(max(0, (float)$value), 2);
    }

    private static function compactNumber(float $number) {
        return abs($number - round($number)) < 0.00001 ? (int)round($number) : round($number, 2);
    }
}
