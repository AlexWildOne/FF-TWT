<?php
namespace RoutesPro\Implementations;

if (!defined('ABSPATH')) exit;

class PernodMaterialImporter {
    public static function parse(string $path): array {
        $document = DocxReader::read($path);
        $notes = [];
        $sections = [];
        $seenTable = false;
        $tableNo = 0;

        foreach ((array)($document['blocks'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'paragraph') {
                $text = trim((string)($block['text'] ?? ''));
                if (!$seenTable && $text !== '') $notes[] = $text;
                continue;
            }
            if (($block['type'] ?? '') !== 'table') continue;
            $seenTable = true;
            $tableNo++;
            $rows = self::normaliseRows((array)($block['rows'] ?? []));
            if (!$rows) continue;

            if (self::isCentralisedTable($rows)) {
                $sections[] = self::parseCentralised($rows, $tableNo);
            } elseif (self::isRtdTable($rows)) {
                $sections[] = self::parseRtd($rows, $tableNo);
            } else {
                $generic = self::parseGeneric($rows, $tableNo);
                if (!empty($generic['items'])) $sections[] = $generic;
            }
        }

        $sections = array_values(array_filter($sections, static fn($section) => !empty($section['items'])));
        if (!$sections) {
            throw new \RuntimeException('O Word foi aberto, mas não foram encontradas tabelas de implementações reconhecíveis.');
        }

        $summary = [
            'sections' => count($sections),
            'items' => 0,
            'implemented' => 0,
            'blocked' => 0,
            'pending' => 0,
            'source_tables' => $tableNo,
        ];
        foreach ($sections as $section) {
            foreach ((array)($section['items'] ?? []) as $item) {
                $summary['items']++;
                $key = (string)($item['status_key'] ?? 'pending');
                if ($key === 'implemented') $summary['implemented']++;
                elseif (in_array($key, ['stock_blocked', 'order_pending'], true)) $summary['blocked']++;
                else $summary['pending']++;
            }
        }

        return [
            'title' => 'Implementações de Materiais',
            'notes' => implode("\n\n", array_values(array_unique($notes))),
            'sections' => $sections,
            'summary' => $summary,
        ];
    }

    private static function isCentralisedTable(array $rows): bool {
        $header = self::rowSignature($rows[0] ?? []);
        return strpos($header, 'sales rep') !== false
            && strpos($header, 'codigo') !== false
            && strpos($header, 'loja') !== false
            && (strpos($header, 'espaco') !== false || strpos($header, 'materiais') !== false);
    }

    private static function isRtdTable(array $rows): bool {
        $first = self::rowSignature($rows[0] ?? []);
        $sample = self::rowSignature(array_merge(...array_slice($rows, 0, min(4, count($rows)))));
        return strpos($first, 'rtd') !== false
            || (strpos($sample, 'designacao') !== false && strpos($sample, 'status implementacoes') !== false)
            || (strpos($sample, 'previsao') !== false && strpos($sample, 'materiais') !== false);
    }

    private static function parseCentralised(array $rows, int $tableNo): array {
        $headerIndex = 0;
        foreach ($rows as $index => $row) {
            if (self::isCentralisedTable([$row])) { $headerIndex = $index; break; }
        }
        $header = $rows[$headerIndex] ?? [];
        $items = [];
        $sort = 10;
        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $row = array_pad($row, 8, '');
            $code = self::cleanCode($row[1] ?? '');
            $name = trim((string)($row[2] ?? ''));
            if ($code === '' && $name === '') continue;
            $statusLabel = trim((string)($row[4] ?? '')) ?: 'Por atualizar';
            $observations = trim((string)($row[7] ?? ''));
            $items[] = [
                'external_store_code' => $code,
                'external_store_name' => $name ?: ('Loja ' . $code),
                'sales_rep' => trim((string)($row[0] ?? '')),
                'implementation_type' => trim((string)($row[3] ?? '')) ?: 'Espaço centralizado',
                'planned_date' => null,
                'planned_date_text' => '',
                'status_key' => self::statusKey($statusLabel . ' ' . $observations),
                'status_label' => $statusLabel,
                'observations' => $observations,
                'materials_proposed' => self::number($row[5] ?? 0),
                'materials_missing' => self::number($row[6] ?? 0),
                'materials_implemented' => 0,
                'details' => ['source_table' => $tableNo, 'source_headers' => $header, 'source_row' => $row],
                'sort_order' => $sort,
            ];
            $sort += 10;
        }

        return [
            'key' => 'centralizados_pingo_doce',
            'title' => 'Espaços centralizados Pingo Doce',
            'summary' => 'Lista de espaços e materiais centralizados a implementar por loja.',
            'metadata' => ['source_table' => $tableNo, 'headers' => $header],
            'sort_order' => 10,
            'items' => $items,
        ];
    }

    private static function parseRtd(array $rows, int $tableNo): array {
        $title = 'RTDs';
        foreach (array_slice($rows, 0, 3) as $row) {
            foreach ($row as $cell) {
                if (stripos(self::ascii((string)$cell), 'rtd') !== false) { $title = trim((string)$cell); break 2; }
            }
        }
        $materialType = '';
        $targetStores = 0;
        if (!empty($rows[1])) {
            foreach ($rows[1] as $cell) {
                $cellText = trim((string)$cell);
                if ($materialType === '' && preg_match('/\bexp\b|expositor|ilha|display/i', self::ascii($cellText))) $materialType = $cellText;
                if (!$targetStores && preg_match('/^\d+$/', $cellText)) $targetStores = (int)$cellText;
            }
        }
        if ($materialType === '') $materialType = 'Implementação RTD';
        $proposedPerStore = 0;
        if (preg_match('/^\s*(\d+)/', $materialType, $match)) $proposedPerStore = (int)$match[1];

        $items = [];
        $sort = 10;
        foreach ($rows as $row) {
            $row = array_pad($row, 6, '');
            $code = self::cleanCode($row[1] ?? '');
            if ($code === '' || !preg_match('/^\d{2,10}$/', $code)) continue;
            $name = trim((string)($row[2] ?? ''));
            if ($name === '' || self::normalise($name) === 'designacao') continue;
            $plannedText = trim((string)($row[3] ?? ''));
            $sourceState = trim((string)($row[4] ?? ''));
            $statusLabel = $sourceState !== '' ? $sourceState : ($plannedText !== '' ? 'Planeado' : 'Por atualizar');
            $items[] = [
                'external_store_code' => $code,
                'external_store_name' => $name,
                'sales_rep' => trim((string)($row[0] ?? '')),
                'implementation_type' => $materialType,
                'planned_date' => null,
                'planned_date_text' => $plannedText,
                'status_key' => self::statusKey($statusLabel),
                'status_label' => $statusLabel,
                'observations' => $sourceState,
                'materials_proposed' => $proposedPerStore,
                'materials_missing' => 0,
                'materials_implemented' => self::number($row[5] ?? 0),
                'details' => ['source_table' => $tableNo, 'source_row' => $row, 'target_stores' => $targetStores],
                'sort_order' => $sort,
            ];
            $sort += 10;
        }

        return [
            'key' => sanitize_key($title) ?: 'rtds',
            'title' => $title,
            'summary' => trim($materialType . ($targetStores ? ' · ' . $targetStores . ' lojas previstas' : '')),
            'metadata' => ['source_table' => $tableNo, 'material_type' => $materialType, 'target_stores' => $targetStores],
            'sort_order' => 20,
            'items' => $items,
        ];
    }

    private static function parseGeneric(array $rows, int $tableNo): array {
        $items = [];
        $sort = 10;
        foreach ($rows as $row) {
            $code = '';
            $name = '';
            foreach ($row as $index => $cell) {
                $cell = trim((string)$cell);
                if ($code === '' && preg_match('/^\d{2,10}$/', self::cleanCode($cell))) {
                    $code = self::cleanCode($cell);
                    $name = trim((string)($row[$index + 1] ?? ''));
                    break;
                }
            }
            if ($code === '' || $name === '') continue;
            $items[] = [
                'external_store_code' => $code,
                'external_store_name' => $name,
                'sales_rep' => '',
                'implementation_type' => 'Implementação de material',
                'planned_date' => null,
                'planned_date_text' => '',
                'status_key' => 'pending',
                'status_label' => 'Por atualizar',
                'observations' => implode(' · ', array_values(array_filter($row, static fn($v) => trim((string)$v) !== ''))),
                'materials_proposed' => 0,
                'materials_missing' => 0,
                'materials_implemented' => 0,
                'details' => ['source_table' => $tableNo, 'source_row' => $row],
                'sort_order' => $sort,
            ];
            $sort += 10;
        }
        return [
            'key' => 'tabela_' . $tableNo,
            'title' => 'Implementações, tabela ' . $tableNo,
            'summary' => 'Tabela Word importada automaticamente.',
            'metadata' => ['source_table' => $tableNo],
            'sort_order' => 40 + $tableNo,
            'items' => $items,
        ];
    }

    public static function statusKey(string $value): string {
        $value = self::normalise($value);
        if ($value === '') return 'pending';
        if (strpos($value, 'implementado') !== false || strpos($value, 'concluido') !== false) return 'implemented';
        if (strpos($value, 'sem stock') !== false || strpos($value, 'stock apenas') !== false || strpos($value, 'stock no frio') !== false) return 'stock_blocked';
        if (strpos($value, 'nao recebeu') !== false || strpos($value, 'nao chegou') !== false || strpos($value, 'aguarda pedido') !== false) return 'order_pending';
        if (strpos($value, 'encomenda feita') !== false || strpos($value, 'agendar') !== false) return 'scheduled';
        if (strpos($value, 'sem feedback') !== false) return 'no_feedback';
        if (strpos($value, 'enviado') !== false) return 'sent';
        if (strpos($value, 'aguarda') !== false) return 'awaiting';
        if (strpos($value, 'previsao') !== false || strpos($value, 'planeado') !== false || strpos($value, 'por atualizar') !== false) return 'pending';
        return 'other';
    }

    private static function normaliseRows(array $rows): array {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $clean = array_map(static function ($value) {
                $value = str_replace(["\xC2\xA0", "\u{00A0}"], ' ', (string)$value);
                $value = preg_replace('/\s+/u', ' ', $value);
                return trim((string)$value);
            }, $row);
            while ($clean && end($clean) === '') array_pop($clean);
            if ($clean && implode('', $clean) !== '') $out[] = $clean;
        }
        return $out;
    }

    private static function rowSignature(array $row): string {
        return self::normalise(implode(' ', array_map('strval', $row)));
    }

    private static function cleanCode($value): string {
        $value = trim((string)$value);
        if (preg_match('/\d+/', $value, $match)) return ltrim($match[0], '0') ?: '0';
        return '';
    }

    private static function number($value): int {
        if (is_numeric($value)) return max(0, (int)$value);
        if (preg_match('/-?\d+/', (string)$value, $match)) return max(0, (int)$match[0]);
        return 0;
    }

    private static function normalise(string $value): string {
        $value = self::ascii($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim((string)preg_replace('/\s+/', ' ', (string)$value));
    }

    private static function ascii(string $value): string {
        if (function_exists('remove_accents')) return (string)remove_accents($value);
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return $converted !== false ? $converted : $value;
    }
}
