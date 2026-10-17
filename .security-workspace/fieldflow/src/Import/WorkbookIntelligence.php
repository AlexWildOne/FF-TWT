<?php
namespace RoutesPro\Import;

if (!defined('ABSPATH')) exit;

/**
 * Shared spreadsheet intelligence used by FieldFlow importers.
 *
 * The goal is deterministic resilience, not probabilistic guessing: recognise
 * multi-row headers, tolerate small wording changes and expose confidence so
 * callers can keep ambiguous files in review instead of silently misreading them.
 */
final class WorkbookIntelligence {
    public static function normalise(string $value): string {
        $value = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($value) : strip_tags($value);
        if (function_exists('remove_accents')) $value = remove_accents($value);
        else $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower($value);
        $value = str_replace(["\xC2\xA0", "\u{00A0}"], ' ', $value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
        return trim(preg_replace('/\s+/u', ' ', (string)$value));
    }

    public static function compact(string $value): string {
        return str_replace(' ', '', self::normalise($value));
    }

    public static function detectHeader(array $rows, array $aliases, array $requiredGroups = [], int $maxRows = 60, int $maxDepth = 3): ?array {
        if (!$rows || !$aliases) return null;
        $rowNumbers = array_keys($rows);
        sort($rowNumbers, SORT_NUMERIC);
        $rowNumbers = array_slice($rowNumbers, 0, max(1, $maxRows));
        $best = null;

        foreach ($rowNumbers as $position => $startRow) {
            for ($depth = 1; $depth <= max(1, $maxDepth); $depth++) {
                $slice = array_slice($rowNumbers, $position, $depth);
                if (count($slice) !== $depth) break;
                $headers = self::compositeHeaders($rows, $slice);
                if (!$headers) continue;
                $assignment = self::assignFields($headers, $aliases);
                $mapping = $assignment['mapping'];
                $confidence = $assignment['confidence'];
                if (!$mapping) continue;

                $groupsSatisfied = 0;
                $allRequired = true;
                foreach ($requiredGroups as $fields) {
                    $matched = false;
                    foreach ((array)$fields as $field) {
                        if (isset($mapping[$field]) && (int)($confidence[$field] ?? 0) >= 62) { $matched = true; break; }
                    }
                    if ($matched) $groupsSatisfied++;
                    else $allRequired = false;
                }
                if ($requiredGroups && !$allRequired) continue;

                $values = array_values($confidence);
                $average = $values ? (int)round(array_sum($values) / count($values)) : 0;
                $exactish = count(array_filter($values, static fn($v) => (int)$v >= 90));
                $score = ($average * 2) + (count($mapping) * 12) + ($groupsSatisfied * 24) + ($exactish * 7) - (($depth - 1) * 3);
                $candidate = [
                    'header_row' => (int)$startRow,
                    'header_end_row' => (int)end($slice),
                    'header_depth' => $depth,
                    'mapping' => $mapping,
                    'confidence' => $confidence,
                    'headers' => $headers,
                    'average_confidence' => $average,
                    'score' => $score,
                ];
                if ($best === null || $candidate['score'] > $best['score']) $best = $candidate;
            }
        }
        return $best;
    }

    public static function compositeHeaders(array $rows, array $rowNumbers): array {
        $columns = [];
        foreach ($rowNumbers as $rowNo) {
            foreach ((array)($rows[$rowNo] ?? []) as $column => $value) {
                $normal = self::normalise((string)$value);
                if ($normal === '') continue;
                $columns[(string)$column][] = $normal;
            }
        }
        foreach ($columns as $column => $parts) {
            $unique = [];
            foreach ($parts as $part) if (!in_array($part, $unique, true)) $unique[] = $part;
            $columns[$column] = trim(implode(' ', $unique));
        }
        return $columns;
    }

    public static function headerMatch(string $header, array $aliases): int {
        $header = self::normalise($header);
        if ($header === '') return 0;
        $best = 0;
        foreach ($aliases as $aliasRaw) {
            $alias = self::normalise((string)$aliasRaw);
            if ($alias === '') continue;
            if ($header === $alias) return 100;

            $aliasLen = strlen(str_replace(' ', '', $alias));
            if ($aliasLen >= 4 && (strpos($header, $alias) !== false || strpos($alias, $header) !== false)) {
                $best = max($best, 91);
            }

            $a = array_values(array_unique(array_filter(explode(' ', $header))));
            $b = array_values(array_unique(array_filter(explode(' ', $alias))));
            $intersection = count(array_intersect($a, $b));
            $union = count(array_unique(array_merge($a, $b)));
            $tokenScore = $union ? (int)round(($intersection / $union) * 100) : 0;
            similar_text($header, $alias, $similarity);
            $combined = (int)round(($tokenScore * 0.62) + ($similarity * 0.38));
            if ($intersection >= 1 && $combined >= 58) $best = max($best, min(89, $combined));
        }
        return $best;
    }

    private static function assignFields(array $headers, array $aliases): array {
        $pairs = [];
        foreach ($aliases as $field => $fieldAliases) {
            foreach ($headers as $column => $header) {
                $score = self::headerMatch((string)$header, (array)$fieldAliases);
                if ($score >= 58) $pairs[] = ['field'=>(string)$field, 'column'=>(string)$column, 'score'=>$score];
            }
        }
        usort($pairs, static function(array $a, array $b): int {
            $cmp = ((int)$b['score']) <=> ((int)$a['score']);
            if ($cmp) return $cmp;
            $cmp = strlen((string)$b['field']) <=> strlen((string)$a['field']);
            return $cmp ?: strnatcasecmp((string)$a['column'], (string)$b['column']);
        });
        $mapping = [];
        $confidence = [];
        $usedColumns = [];
        foreach ($pairs as $pair) {
            $field = (string)$pair['field'];
            $column = (string)$pair['column'];
            if (isset($mapping[$field]) || isset($usedColumns[$column])) continue;
            $mapping[$field] = $column;
            $confidence[$field] = (int)$pair['score'];
            $usedColumns[$column] = true;
        }
        return ['mapping'=>$mapping, 'confidence'=>$confidence];
    }

    public static function rowValue(array $cells, string $column): string {
        if ($column === '') return '';
        return self::clean((string)($cells[$column] ?? ''));
    }

    public static function clean(string $value): string {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        $value = str_replace(["\xC2\xA0", "\u{00A0}", "\u{2007}", "\u{202F}"], ' ', $value);
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value);
        $value = preg_replace('/\h+/u', ' ', $value);
        $value = preg_replace('/\h*\R\h*/u', "\n", $value);
        return trim((string)$value);
    }

    public static function identifier(string $value): string {
        $value = self::clean($value);
        $value = trim($value, "'\"");
        if ($value === '') return '';
        $numeric = str_replace([' ', ','], ['', '.'], $value);
        if (preg_match('/^[+-]?\d+(?:\.0+)?$/', $numeric)) {
            return preg_replace('/\.0+$/', '', $numeric);
        }
        if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)[eE][+-]?\d+$/', $numeric) && is_numeric($numeric)) {
            return sprintf('%.0f', (float)$numeric);
        }
        return $value;
    }

    public static function number(string $value): ?float {
        $value = self::clean($value);
        if ($value === '') return null;
        $negative = false;
        if (preg_match('/^\((.*)\)$/', $value, $m)) { $negative = true; $value = $m[1]; }
        $value = str_replace(["\xC2\xA0", "\u{00A0}", ' '], '', $value);
        $value = str_replace(["\u{2212}"], '-', $value);
        if (in_array(strtolower($value), ['n/a','na','nd','n.d.','null'], true)) return null;
        if (in_array($value, ['-','--'], true)) return 0.0;

        if (preg_match('/^[+-]?(?:\d+(?:[.,]\d*)?|[.,]\d+)[eE][+-]?\d+$/', $value)) {
            $scientific = str_replace(',', '.', $value);
            return is_numeric($scientific) ? (float)$scientific : null;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');
        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) $value = str_replace(['.', ','], ['', '.'], $value);
            else $value = str_replace(',', '', $value);
        } elseif ($lastComma !== false) {
            $parts = explode(',', $value);
            $tail = end($parts);
            if (count($parts) > 2 || (strlen((string)$tail) === 3 && strlen((string)$parts[0]) >= 1)) $value = str_replace(',', '', $value);
            else $value = str_replace(',', '.', $value);
        } elseif ($lastDot !== false) {
            $parts = explode('.', $value);
            $tail = end($parts);
            if (count($parts) > 2 || (strlen((string)$tail) === 3 && strlen((string)$parts[0]) >= 1)) $value = str_replace('.', '', $value);
        }
        $value = preg_replace('/[^0-9eE+\.\-]/', '', $value);
        if ($value === '' || !is_numeric($value)) return null;
        $number = (float)$value;
        return $negative ? -abs($number) : $number;
    }

    public static function isTotalLabel(string $value): bool {
        $value = self::normalise($value);
        if ($value === '') return false;
        return in_array($value, ['total','total geral','grand total','subtotal','row labels','rotulos de linha','rótulos de linha'], true)
            || strpos($value, 'total geral') === 0
            || strpos($value, 'grand total') === 0;
    }

    public static function columnProfile(array $rows, int $afterRow, string $column, int $limit = 250): array {
        $values = [];
        foreach ($rows as $rowNo => $cells) {
            if ((int)$rowNo <= $afterRow) continue;
            $value = self::rowValue((array)$cells, $column);
            if ($value === '') continue;
            $values[] = $value;
            if (count($values) >= $limit) break;
        }
        $count = count($values);
        if (!$count) return ['count'=>0,'numeric_ratio'=>0,'digit_ratio'=>0,'ean_ratio'=>0,'unique_ratio'=>0,'average_length'=>0];
        $numeric = 0; $digits = 0; $ean = 0; $length = 0; $unique = [];
        foreach ($values as $value) {
            $length += strlen($value);
            if (self::number($value) !== null) $numeric++;
            $identifier = preg_replace('/\D+/', '', self::identifier($value));
            if ($identifier !== '') {
                $digits++;
                $len = strlen($identifier);
                if ($len >= 8 && $len <= 14) $ean++;
            }
            $unique[self::normalise($value)] = true;
        }
        return [
            'count'=>$count,
            'numeric_ratio'=>(int)round(($numeric/$count)*100),
            'digit_ratio'=>(int)round(($digits/$count)*100),
            'ean_ratio'=>(int)round(($ean/$count)*100),
            'unique_ratio'=>(int)round((count($unique)/$count)*100),
            'average_length'=>(int)round($length/$count),
        ];
    }

    public static function columnIndex(string $letters): int {
        $letters = strtoupper($letters);
        $value = 0;
        for ($i=0, $len=strlen($letters); $i<$len; $i++) {
            $ord = ord($letters[$i]);
            if ($ord < 65 || $ord > 90) continue;
            $value = ($value * 26) + ($ord - 64);
        }
        return $value;
    }

    public static function columnLetter(int $index): string {
        $out = '';
        while ($index > 0) {
            $index--;
            $out = chr(65 + ($index % 26)) . $out;
            $index = intdiv($index, 26);
        }
        return $out;
    }
}
