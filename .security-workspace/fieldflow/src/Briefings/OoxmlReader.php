<?php
namespace RoutesPro\Briefings;

if (!defined('ABSPATH')) exit;

/**
 * Minimal OOXML reader for weekly briefing XLSX files.
 *
 * It deliberately avoids PHP XML extensions because some WordPress hosts only
 * expose libxml without SimpleXML, DOM or XMLReader. XLSX uses predictable XML,
 * so a small, defensive token reader is enough for cells, shared strings and
 * workbook relationships.
 */
class OoxmlReader {
    public static function read(string $path): array {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('O ficheiro Excel não está disponível para leitura.');
        }

        [$get, $close] = self::archiveReader($path);
        try {
            $sharedStrings = self::sharedStrings($get);
            $workbook = $get('xl/workbook.xml');
            $relations = $get('xl/_rels/workbook.xml.rels');
            if ($workbook === false || $relations === false) {
                throw new \RuntimeException('Estrutura XLSX incompleta: workbook.');
            }

            $targets = [];
            if (preg_match_all('/<Relationship\b([^>]*)\/?\s*>/i', $relations, $matches)) {
                foreach ($matches[1] as $rawAttrs) {
                    $attrs = self::attributes($rawAttrs);
                    $id = (string)($attrs['Id'] ?? '');
                    $target = (string)($attrs['Target'] ?? '');
                    if ($id !== '' && $target !== '') $targets[$id] = $target;
                }
            }

            $sheets = [];
            if (preg_match_all('/<sheet\b([^>]*)\/?\s*>/i', $workbook, $matches)) {
                foreach ($matches[1] as $rawAttrs) {
                    $attrs = self::attributes($rawAttrs);
                    $name = self::decode((string)($attrs['name'] ?? ''));
                    $relId = (string)($attrs['r:id'] ?? '');
                    if ($name === '' || $relId === '' || empty($targets[$relId])) continue;
                    $target = self::normaliseTarget((string)$targets[$relId]);
                    $raw = $get($target);
                    if ($raw === false) continue;
                    $sheets[$name] = self::parseSheet($raw, $sharedStrings);
                }
            }
            return $sheets;
        } finally {
            $close();
        }
    }

    private static function archiveReader(string $path): array {
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) throw new \RuntimeException('Não foi possível abrir o ficheiro XLSX.');
            return [
                static function (string $name) use ($zip) { return $zip->getFromName($name); },
                static function () use ($zip): void { $zip->close(); },
            ];
        }

        if (class_exists('PharData')) {
            try {
                $phar = new \PharData($path);
                return [
                    static function (string $name) use ($phar) {
                        return isset($phar[$name]) ? $phar[$name]->getContent() : false;
                    },
                    static function (): void {},
                ];
            } catch (\Throwable $e) {
                throw new \RuntimeException('Não foi possível abrir o ficheiro XLSX: ' . $e->getMessage());
            }
        }

        $pcl = defined('ABSPATH') ? ABSPATH . 'wp-admin/includes/class-pclzip.php' : '';
        if ($pcl && is_file($pcl)) require_once $pcl;
        if (!class_exists('PclZip')) throw new \RuntimeException('O servidor não tem suporte ZIP para importar ficheiros XLSX.');
        $archive = new \PclZip($path);
        return [
            static function (string $name) use ($archive) {
                $list = $archive->extract(PCLZIP_OPT_BY_NAME, $name, PCLZIP_OPT_EXTRACT_AS_STRING);
                return is_array($list) && isset($list[0]['content']) ? $list[0]['content'] : false;
            },
            static function (): void {},
        ];
    }

    private static function sharedStrings(callable $get): array {
        $raw = $get('xl/sharedStrings.xml');
        if ($raw === false || $raw === '') return [];
        $out = [];
        if (!preg_match_all('/<si\b[^>]*>(.*?)<\/si>/is', $raw, $items)) return $out;
        foreach ($items[1] as $item) {
            // Phonetic runs (<rPh>) are metadata, not visible cell content.
            $item = preg_replace('/<rPh\b[^>]*>.*?<\/rPh>/is', '', $item);
            $parts = [];
            if (preg_match_all('/<t\b[^>]*>(.*?)<\/t>/is', $item, $texts)) {
                foreach ($texts[1] as $text) $parts[] = self::decode($text);
            }
            $out[] = self::cleanValue(implode('', $parts));
        }
        return $out;
    }

    private static function parseSheet(string $raw, array $sharedStrings): array {
        $rows = [];
        if (!preg_match_all('/<row\b([^>]*)>(.*?)<\/row>/is', $raw, $rowMatches, PREG_SET_ORDER)) return $rows;
        $fallbackRow = 0;
        foreach ($rowMatches as $rowMatch) {
            $rowAttrs = self::attributes($rowMatch[1]);
            $rowNumber = isset($rowAttrs['r']) ? (int)$rowAttrs['r'] : ++$fallbackRow;
            $fallbackRow = max($fallbackRow, $rowNumber);
            $cells = [];
            if (preg_match_all('/<c\b([^>]*?)(?:\/\s*>|>(.*?)<\/c>)/is', $rowMatch[2], $cellMatches, PREG_SET_ORDER)) {
                foreach ($cellMatches as $cellMatch) {
                    $attrs = self::attributes($cellMatch[1]);
                    $ref = strtoupper((string)($attrs['r'] ?? ''));
                    if (!preg_match('/^([A-Z]+)\d+$/', $ref, $m)) continue;
                    $column = $m[1];
                    $type = (string)($attrs['t'] ?? '');
                    $body = isset($cellMatch[2]) ? (string)$cellMatch[2] : '';
                    $value = '';
                    if ($type === 'e') {
                        continue;
                    } elseif ($type === 'inlineStr') {
                        $parts = [];
                        if (preg_match_all('/<t\b[^>]*>(.*?)<\/t>/is', $body, $texts)) {
                            foreach ($texts[1] as $text) $parts[] = self::decode($text);
                        }
                        $value = implode('', $parts);
                    } elseif (preg_match('/<v\b[^>]*>(.*?)<\/v>/is', $body, $valueMatch)) {
                        $value = self::decode($valueMatch[1]);
                        if ($type === 's' && $value !== '') {
                            $value = $sharedStrings[(int)$value] ?? $value;
                        } elseif ($type === 'b') {
                            $value = $value === '1' ? '1' : '0';
                        }
                    } elseif ($type === 'str' && preg_match('/<f\b[^>]*>(.*?)<\/f>/is', $body, $formulaMatch)) {
                        $value = self::decode($formulaMatch[1]);
                    }
                    $value = self::cleanValue((string)$value);
                    if ($value !== '') $cells[$column] = $value;
                }
            }
            if ($cells) $rows[$rowNumber] = $cells;
        }
        self::propagateMergedCells($raw, $rows);
        return $rows;
    }

    private static function propagateMergedCells(string $raw, array &$rows): void {
        if (!$rows || !preg_match_all('/<mergeCell\b[^>]*\bref=(?:\"([^\"]+)\"|\'([^\']+)\')[^>]*\/?\s*>/i', $raw, $matches, PREG_SET_ORDER)) return;
        $filled = 0;
        foreach (array_slice($matches, 0, 1000) as $match) {
            $ref = strtoupper((string)($match[1] !== '' ? $match[1] : ($match[2] ?? '')));
            if (!preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $ref, $m)) continue;
            $startCol = self::columnToIndex($m[1]);
            $startRow = (int)$m[2];
            $endCol = self::columnToIndex($m[3]);
            $endRow = (int)$m[4];
            if ($startCol < 1 || $endCol < $startCol || $startRow < 1 || $endRow < $startRow) continue;
            $source = (string)($rows[$startRow][$m[1]] ?? '');
            if ($source === '') continue;
            for ($rowNo=$startRow; $rowNo<=$endRow; $rowNo++) {
                for ($col=$startCol; $col<=$endCol; $col++) {
                    if (++$filled > 5000) return;
                    $letter = self::indexToColumn($col);
                    if (!isset($rows[$rowNo])) $rows[$rowNo] = [];
                    if (!isset($rows[$rowNo][$letter]) || trim((string)$rows[$rowNo][$letter]) === '') $rows[$rowNo][$letter] = $source;
                }
                ksort($rows[$rowNo], SORT_NATURAL);
            }
        }
        ksort($rows, SORT_NUMERIC);
    }

    private static function columnToIndex(string $letters): int {
        $letters = strtoupper($letters);
        $value = 0;
        for ($i=0, $len=strlen($letters); $i<$len; $i++) {
            $ord = ord($letters[$i]);
            if ($ord < 65 || $ord > 90) continue;
            $value = ($value * 26) + ($ord - 64);
        }
        return $value;
    }

    private static function indexToColumn(int $index): string {
        $out = '';
        while ($index > 0) {
            $index--;
            $out = chr(65 + ($index % 26)) . $out;
            $index = intdiv($index, 26);
        }
        return $out;
    }

    private static function attributes(string $raw): array {
        $out = [];
        if (preg_match_all('/([A-Za-z_:][A-Za-z0-9_.:-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/s', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $out[$match[1]] = self::decode($match[2] !== '' ? $match[2] : ($match[3] ?? ''));
            }
        }
        return $out;
    }

    private static function normaliseTarget(string $target): string {
        $target = str_replace('\\', '/', trim($target));
        if (strpos($target, '/') === 0) return ltrim($target, '/');
        $parts = explode('/', 'xl/' . $target);
        $normal = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') { array_pop($normal); continue; }
            $normal[] = $part;
        }
        return implode('/', $normal);
    }

    private static function decode(string $value): string {
        return html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function cleanValue(string $value): string {
        $value = str_replace(["\xC2\xA0", "\u{00A0}"], ' ', $value);
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value);
        $value = preg_replace('/[ \t]+/u', ' ', $value);
        $value = preg_replace('/\h*\R\h*/u', "\n", $value);
        return trim((string)$value);
    }
}
