<?php
namespace RoutesPro\Shopping;

use RoutesPro\Briefings\OoxmlReader;
use RoutesPro\Import\WorkbookIntelligence;

if (!defined('ABSPATH')) exit;

/**
 * Resilient Shopping workbook importer.
 *
 * Scans every worksheet, tolerates merged and multi-row headers, detects the
 * article column wherever it is placed and ranks the sheet that really holds
 * the price matrix instead of assuming that the first sheet is the data sheet.
 */
final class ShoppingImporter {
    private const ARTICLE_ALIASES = ['artigos','artigo','articles','article','produto','produtos','descricao produto','designacao','item','sku description'];
    private const TYPE_ALIASES = ['tipo','type','categoria','segmento','tipo artigo','familia'];
    private const REGULAR_ALIASES = ['pvp regular','pvpr regular','preco regular','regular price','regular'];
    private const PROMO_ALIASES = ['pvp promo','pvpr promo','preco promo','preco promocional','promo price','promo'];

    public static function parse(string $path): array {
        $sheets = OoxmlReader::read($path);
        if (!$sheets) throw new \RuntimeException('O ficheiro Excel nao contem folhas legiveis.');

        $candidates = [];
        foreach ($sheets as $sheetName => $rows) {
            if (!is_array($rows) || !$rows) continue;
            $candidate = self::detectMatrix((string)$sheetName, $rows);
            if ($candidate) $candidates[] = $candidate;
        }
        if (!$candidates) throw new \RuntimeException('Nao foi encontrada uma matriz Shopping com artigos e pares de preco Regular / Promo.');
        usort($candidates, static function(array $a, array $b): int {
            $cmp = ((int)$b['score']) <=> ((int)$a['score']);
            return $cmp ?: strnatcasecmp((string)$a['sheet_name'], (string)$b['sheet_name']);
        });
        $selected = $candidates[0];
        $sheetName = (string)$selected['sheet_name'];
        $rows = (array)$selected['rows'];
        $articleHeaderRow = (int)$selected['header_row'];
        $headerEndRow = (int)$selected['header_end_row'];
        $articleColumn = (string)$selected['article_column'];
        $typeColumn = (string)($selected['type_column'] ?? '');
        $pairs = (array)$selected['pairs'];

        $products = [];
        $seenKeys = [];
        $rowNumbers = array_keys($rows);
        sort($rowNumbers, SORT_NUMERIC);
        foreach ($rowNumbers as $rowNo) {
            if ((int)$rowNo <= $headerEndRow) continue;
            $row = (array)($rows[$rowNo] ?? []);
            $name = WorkbookIntelligence::rowValue($row, $articleColumn);
            if ($name === '' || WorkbookIntelligence::isTotalLabel($name)) continue;
            $type = $typeColumn !== '' ? WorkbookIntelligence::rowValue($row, $typeColumn) : '';
            $normalName = WorkbookIntelligence::normalise($name);
            if ($normalName === '' || in_array($normalName, ['pvp regular','pvp promo','regular','promo'], true)) continue;

            $type = self::normaliseProductType($type);
            $baseKey = 'shopping_' . substr(md5(WorkbookIntelligence::normalise($type . '|' . $name)), 0, 16);
            $key = $baseKey;
            $suffix = 2;
            while (isset($seenKeys[$key])) $key = $baseKey . '_' . $suffix++;
            $seenKeys[$key] = true;
            $products[] = [
                'key' => $key,
                'type' => $type,
                'name' => $name,
                'source_row' => (int)$rowNo,
            ];
        }
        if (!$products) throw new \RuntimeException('Nao foram encontrados artigos para criar o formulario Shopping.');

        $stores = [];
        foreach ($pairs as $pair) {
            $prices = [];
            foreach ($products as $product) {
                $row = (array)($rows[(int)$product['source_row']] ?? []);
                $prices[(string)$product['key']] = [
                    'regular' => self::normalisePrice($row[(string)$pair['regular_column']] ?? null),
                    'promo' => self::normalisePrice($row[(string)$pair['promo_column']] ?? null),
                ];
            }
            $stores[] = [
                'name' => (string)$pair['name'],
                'regular_column' => (string)$pair['regular_column'],
                'promo_column' => (string)$pair['promo_column'],
                'prices' => $prices,
            ];
        }

        [$week, $year] = self::weekAndYear($sheetName, $rows);
        $priceNote = self::priceNote($rows, $articleHeaderRow);
        $candidateSummary = [];
        foreach ($candidates as $candidate) {
            $candidateSummary[] = [
                'sheet'=>(string)$candidate['sheet_name'],
                'header_row'=>(int)$candidate['header_row'],
                'header_depth'=>(int)$candidate['header_depth'],
                'stores'=>count((array)$candidate['pairs']),
                'score'=>(int)$candidate['score'],
                'selected'=>(string)$candidate['sheet_name'] === $sheetName,
            ];
        }

        return [
            'sheet_name' => $sheetName,
            'week_no' => $week,
            'year_no' => $year,
            'title' => trim('Shopping' . ($week ? ' - Semana ' . $week : '') . ($year ? '/' . $year : '')),
            'price_note' => $priceNote,
            'products' => $products,
            'stores' => $stores,
            'product_count' => count($products),
            'store_count' => count($stores),
            'import_intelligence' => [
                'engine'=>'fieldflow_workbook_intelligence_v1',
                'workbook_sheets'=>count($sheets),
                'selected_sheet'=>$sheetName,
                'header_row'=>$articleHeaderRow,
                'header_end_row'=>$headerEndRow,
                'header_depth'=>(int)$selected['header_depth'],
                'article_column'=>$articleColumn,
                'type_column'=>$typeColumn,
                'candidate_sheets'=>$candidateSummary,
            ],
        ];
    }

    private static function detectMatrix(string $sheetName, array $rows): ?array {
        $rowNumbers = array_keys($rows);
        sort($rowNumbers, SORT_NUMERIC);
        $rowNumbers = array_slice($rowNumbers, 0, 90);
        $best = null;
        foreach ($rowNumbers as $position => $startRow) {
            for ($depth = 1; $depth <= 3; $depth++) {
                $slice = array_slice($rowNumbers, $position, $depth);
                if (count($slice) !== $depth) break;
                $headers = WorkbookIntelligence::compositeHeaders($rows, $slice);
                if (!$headers) continue;
                [$articleColumn, $articleScore] = self::bestColumn($headers, self::ARTICLE_ALIASES);
                if ($articleColumn === '' || $articleScore < 68) continue;
                [$typeColumn, $typeScore] = self::bestColumn($headers, self::TYPE_ALIASES, [$articleColumn]);
                $pairs = self::detectStorePairs($rows, $headers, (int)$startRow, (int)end($slice));
                if (!$pairs) continue;
                $dataRows = self::sampleDataRows($rows, (int)end($slice), $articleColumn, $pairs);
                if ($dataRows === 0) continue;
                $score = ($articleScore * 2) + (count($pairs) * 75) + min(240, $dataRows * 8) + max(0, $typeScore - 50) - (($depth - 1) * 3);
                $candidate = [
                    'sheet_name'=>$sheetName,
                    'rows'=>$rows,
                    'header_row'=>(int)$startRow,
                    'header_end_row'=>(int)end($slice),
                    'header_depth'=>$depth,
                    'article_column'=>$articleColumn,
                    'type_column'=>$typeScore >= 60 ? $typeColumn : '',
                    'pairs'=>$pairs,
                    'score'=>$score,
                ];
                if ($best === null || $candidate['score'] > $best['score']) $best = $candidate;
            }
        }
        return $best;
    }

    private static function detectStorePairs(array $rows, array $headers, int $headerStart, int $headerEnd): array {
        $columns = array_keys($headers);
        usort($columns, static fn($a, $b) => WorkbookIntelligence::columnIndex((string)$a) <=> WorkbookIntelligence::columnIndex((string)$b));
        $pairs = [];
        $usedPromo = [];
        foreach ($columns as $column) {
            $regularScore = WorkbookIntelligence::headerMatch((string)($headers[$column] ?? ''), self::REGULAR_ALIASES);
            if ($regularScore < 68) continue;
            $index = WorkbookIntelligence::columnIndex((string)$column);
            $promoColumn = '';
            $promoScore = 0;
            foreach ([$index + 1, $index + 2] as $candidateIndex) {
                $candidateColumn = WorkbookIntelligence::columnLetter($candidateIndex);
                if (isset($usedPromo[$candidateColumn])) continue;
                $score = WorkbookIntelligence::headerMatch((string)($headers[$candidateColumn] ?? ''), self::PROMO_ALIASES);
                if ($score > $promoScore) { $promoScore = $score; $promoColumn = $candidateColumn; }
            }
            if ($promoColumn === '' || $promoScore < 68) continue;
            $storeName = self::deriveStoreName($rows, (string)$column, $promoColumn, $headerStart, $headerEnd, $headers);
            if ($storeName === '') $storeName = 'Loja ' . (count($pairs) + 1);
            $pairs[] = [
                'name'=>$storeName,
                'regular_column'=>(string)$column,
                'promo_column'=>$promoColumn,
                'confidence'=>(int)round(($regularScore + $promoScore) / 2),
            ];
            $usedPromo[$promoColumn] = true;
        }
        return $pairs;
    }

    private static function deriveStoreName(array $rows, string $regularColumn, string $promoColumn, int $headerStart, int $headerEnd, array $headers): string {
        $candidates = [];
        $from = max(1, $headerStart - 5);
        for ($rowNo = $from; $rowNo <= $headerEnd; $rowNo++) {
            foreach ([$regularColumn, $promoColumn] as $column) {
                $value = WorkbookIntelligence::clean((string)($rows[$rowNo][$column] ?? ''));
                if ($value === '') continue;
                $normal = WorkbookIntelligence::normalise($value);
                if ($normal === '' || self::isPriceHeader($normal) || WorkbookIntelligence::headerMatch($normal, self::ARTICLE_ALIASES) >= 75) continue;
                $candidates[] = $value;
            }
        }
        if ($candidates) {
            $counts = array_count_values($candidates);
            arsort($counts, SORT_NUMERIC);
            return WorkbookIntelligence::clean((string)array_key_first($counts));
        }
        $header = WorkbookIntelligence::clean((string)($headers[$regularColumn] ?? ''));
        $header = preg_replace('/\b(?:pvp|pvpr|preco|price|regular|promo|promocional)\b/i', ' ', $header);
        $header = trim(preg_replace('/\s+/', ' ', (string)$header));
        return $header;
    }

    private static function isPriceHeader(string $normal): bool {
        return WorkbookIntelligence::headerMatch($normal, self::REGULAR_ALIASES) >= 68
            || WorkbookIntelligence::headerMatch($normal, self::PROMO_ALIASES) >= 68;
    }

    private static function sampleDataRows(array $rows, int $headerEnd, string $articleColumn, array $pairs): int {
        $count = 0; $scanned = 0;
        foreach ($rows as $rowNo => $row) {
            if ((int)$rowNo <= $headerEnd) continue;
            if (++$scanned > 80) break;
            $article = WorkbookIntelligence::rowValue((array)$row, $articleColumn);
            if ($article === '' || WorkbookIntelligence::isTotalLabel($article)) continue;
            $hasPrice = false;
            foreach ($pairs as $pair) {
                $regular = self::normalisePrice($row[(string)$pair['regular_column']] ?? null);
                $promo = self::normalisePrice($row[(string)$pair['promo_column']] ?? null);
                if ($regular !== null || $promo !== null) { $hasPrice = true; break; }
            }
            if ($hasPrice) $count++;
        }
        return $count;
    }

    private static function bestColumn(array $headers, array $aliases, array $excluded = []): array {
        $bestColumn = ''; $bestScore = 0;
        foreach ($headers as $column => $header) {
            if (in_array((string)$column, $excluded, true)) continue;
            $score = WorkbookIntelligence::headerMatch((string)$header, $aliases);
            if ($score > $bestScore) { $bestScore = $score; $bestColumn = (string)$column; }
        }
        return [$bestColumn, $bestScore];
    }

    private static function normaliseProductType(string $type): string {
        $normal = WorkbookIntelligence::normalise($type);
        if ($normal === 'prp') return 'PRP';
        if (strpos($normal, 'concorr') !== false) return 'Concorrência';
        return $type !== '' ? $type : 'Outros';
    }

    private static function normalisePrice($value): ?string {
        if ($value === null) return null;
        $raw = WorkbookIntelligence::clean((string)$value);
        if ($raw === '' || in_array(strtolower($raw), ['-','--','?','n/a','na','nd'], true)) return null;
        $number = WorkbookIntelligence::number($raw);
        if ($number === null || $number < 0 || $number > 999999) return null;
        return number_format($number, 2, '.', '');
    }

    private static function weekAndYear(string $sheetName, array $rows): array {
        $week = 0; $year = 0;
        $texts = [$sheetName];
        $scanned = 0;
        foreach ($rows as $row) {
            foreach ((array)$row as $value) {
                $value = WorkbookIntelligence::clean((string)$value);
                if ($value !== '') $texts[] = $value;
                if (++$scanned >= 160) break 2;
            }
        }
        foreach ($texts as $text) {
            if (!$week && preg_match('/(?:semana|week|w)\s*0*(\d{1,2})/i', (string)$text, $match)) $week = max(1, min(53, (int)$match[1]));
            if (!$year && preg_match('/\b(20\d{2})\b/', (string)$text, $match)) $year = (int)$match[1];
            if ($week && $year) break;
        }
        return [$week, $year];
    }

    private static function priceNote(array $rows, int $headerRow): string {
        for ($rowNo = max(1, $headerRow - 5); $rowNo <= $headerRow; $rowNo++) {
            foreach ((array)($rows[$rowNo] ?? []) as $value) {
                $value = WorkbookIntelligence::clean((string)$value);
                $normal = WorkbookIntelligence::normalise($value);
                if ($value !== '' && (strpos($normal, 'iva') !== false || strpos($normal, 'iec') !== false || strpos($normal, 'pvpr') !== false)) return $value;
            }
        }
        return 'PVPR com IEC, excluindo IVA';
    }
}
