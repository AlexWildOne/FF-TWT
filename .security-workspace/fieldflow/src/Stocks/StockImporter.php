<?php
namespace RoutesPro\Stocks;

use RoutesPro\Briefings\OoxmlReader;
use RoutesPro\Import\WorkbookIntelligence;
use RoutesPro\Forms\ProductCardex;
use RoutesPro\Mapping\MappingEngine;

if (!defined('ABSPATH')) exit;

class StockImporter {
    private const ALIASES = [
        'store_ref' => [
            'centro','cod centro','codigo centro','código centro','numero centro','número centro','id centro','center code',
            'cod loja','codigo loja','código loja','numero loja','número loja','id loja','store id','store code','store no','store number',
            'external ref','external_ref','loja id','cod pdv','codigo pdv','código pdv','pdv code','estabelecimento id','site id','store'
        ],
        'store_name' => [
            'descr centro','descr. centro','descricao centro','descrição centro','designacao centro','designação centro',
            'nome loja','designacao loja','designação loja','loja','store name','store_name','nome estabelecimento','estabelecimento',
            'ponto de venda','ponto venda','nome pdv','pdv nome','location name'
        ],
        'locality' => ['localidade','cidade','city','locality','concelho','municipio','município','municipality','town','zona'],
        'article_ref' => [
            'artigo','cod artigo','codigo artigo','código artigo','article','article code','sku','sku code','item','item code',
            'material','material code','cod material','codigo material','código material','product code','ref produto','referencia produto','referência produto'
        ],
        'ean' => ['ean','ean 13','ean13','gtin','gtin 13','gtin13','barcode','bar code','codigo barras','código barras','codigo de barras','código de barras'],
        'product_name' => [
            'descricao artigo','descrição artigo','descr artigo','descr. artigo','desc artigo','produto','nome produto','descricao produto','descrição produto',
            'item description','product name','product description','material description','descricao material','descrição material','sku description','desc produto'
        ],
        'unit' => ['um','u m','unidade','unit','uom','udm','un medida','unidade medida','unidade de medida'],
        'conversion_factor' => ['fc','fator conversao','fator conversão','factor','conversion factor','conversion','factor conversao','pack factor','units case','un cx','un/cx'],
        'stock_total' => [
            'stock total','total stock','stock','stock atual','stock disponível','stock disponivel','stock loja','stock fisico','stock físico',
            'quantidade stock','qty stock','qty','quantity','on hand','on hand qty','stock on hand','soh','qoh','inventory','inventory qty',
            'available stock','closing stock','existencias','existências','existencia','existência','estoque','saldo stock','saldo'
        ],
    ];

    public static function import(string $path, array $options): array {
        global $wpdb;
        StockSchema::ensureOrThrow();
        if (!is_file($path) || !is_readable($path)) throw new \RuntimeException('O ficheiro de stock não está disponível para leitura.');
        $clientId = absint($options['client_id'] ?? 0);
        $projectId = absint($options['project_id'] ?? 0);
        $insignia = sanitize_text_field((string)($options['insignia'] ?? ''));
        $snapshotDate = self::normaliseDate((string)($options['snapshot_date'] ?? ''));
        $publish = !empty($options['publish']);
        if (!$clientId || !$projectId) throw new \RuntimeException('Seleciona o cliente e o projeto.');
        if ($insignia === '') throw new \RuntimeException('Indica a insígnia a que o ficheiro pertence.');
        if (!$snapshotDate) throw new \RuntimeException('Indica uma data de referência válida para o stock.');

        $projectClientId = (int)$wpdb->get_var($wpdb->prepare("SELECT client_id FROM {$wpdb->prefix}routespro_projects WHERE id=%d", $projectId));
        if (!$projectClientId || $projectClientId !== $clientId) throw new \RuntimeException('O projeto selecionado não pertence ao cliente indicado.');
        $sourceFilename = sanitize_file_name((string)($options['source_filename'] ?? basename($path)));
        $parsed = self::parse($path, $sourceFilename, $insignia);
        $rows = $parsed['rows'];
        if (!$rows) throw new \RuntimeException('O ficheiro não contém linhas de stock válidas.');
        $hash = hash_file('sha256', $path) . '|' . $clientId . '|' . $projectId . '|' . StockService::normaliseText($insignia) . '|' . $snapshotDate;
        $hash = hash('sha256', $hash);
        $existing = (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM ' . StockService::batchTable() . ' WHERE import_hash=%s LIMIT 1', $hash));
        if ($existing) throw new \RuntimeException('Este ficheiro já foi importado para o mesmo cliente, projeto, insígnia e data.');

        $locationIndex = StockService::locationIndex($clientId, $projectId, $insignia);
        $mappingIndex = StockService::mappingIndex($clientId, $projectId, $insignia);
        $productCatalog = self::productCatalog($clientId, $projectId, $insignia);
        $stores = [];
        foreach ($rows as $row) {
            $key = StockService::storeKey((string)$row['store_external_ref'], (string)$row['store_name']);
            if (!isset($stores[$key])) {
                $stores[$key] = StockService::resolveLocation(
                    (string)$row['store_external_ref'],
                    (string)$row['store_name'],
                    $locationIndex,
                    $mappingIndex,
                    (string)$row['locality'],
                    $insignia
                );
            }
        }

        $wpdb->query('START TRANSACTION');
        try {
            $now = current_time('mysql');
            $mappingPayload = (array)$parsed['mapping'];
            $mappingPayload['_import_intelligence'] = (array)($parsed['diagnostics'] ?? []);
            $mappingPayload['_mapping_confidence'] = (array)($parsed['mapping_confidence'] ?? []);
            $mappingPayload['_header_depth'] = (int)($parsed['header_depth'] ?? 1);
            $mappingPayload['_header_end_row'] = (int)($parsed['header_end_row'] ?? $parsed['header_row']);
            $ok = $wpdb->insert(StockService::batchTable(), [
                'client_id'=>$clientId,
                'project_id'=>$projectId,
                'insignia'=>$insignia,
                'snapshot_date'=>$snapshotDate,
                'status'=>'draft',
                'source_filename'=>$sourceFilename,
                'sheet_name'=>(string)$parsed['sheet_name'],
                'header_row'=>(int)$parsed['header_row'],
                'import_hash'=>$hash,
                'mapping_json'=>wp_json_encode($mappingPayload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'created_by'=>get_current_user_id(),
                'published_at'=>null,
                'created_at'=>$now,
                'updated_at'=>$now,
            ]);
            if (!$ok) throw new \RuntimeException('Não foi possível criar o lote de stock: ' . $wpdb->last_error);
            $batchId = (int)$wpdb->insert_id;
            $insertRows = [];
            $productCache = [];
            $productStats = ['catalog_items'=>count($productCatalog),'mapped_rows'=>0,'unmatched_rows'=>0,'exact_ean'=>0,'exact_reference'=>0,'exact_name'=>0,'high_confidence'=>0];
            foreach ($rows as $row) {
                $storeKey = StockService::storeKey((string)$row['store_external_ref'], (string)$row['store_name']);
                $match = $stores[$storeKey] ?? ['location_id'=>0,'confidence'=>0,'method'=>'unmatched'];
                $productKey = StockService::normaliseText((string)($row['ean'] ?: ($row['article_ref'] ?: $row['product_name'])));
                if (!isset($productCache[$productKey])) $productCache[$productKey] = self::resolveProduct($row, $productCatalog);
                $productMatch = $productCache[$productKey];
                if (!empty($productMatch['cardex_item_id'])) {
                    $productStats['mapped_rows']++;
                    $statKey = (string)($productMatch['method'] ?? 'high_confidence');
                    if (isset($productStats[$statKey])) $productStats[$statKey]++; else $productStats['high_confidence']++;
                } else $productStats['unmatched_rows']++;
                $insertRows[] = [
                    $batchId,$clientId,$projectId,(int)$match['location_id'],$storeKey,(string)$row['store_external_ref'],(string)$row['store_name'],(string)$row['locality'],(string)$row['article_ref'],(string)$row['ean'],(string)$row['product_name'],(string)$row['unit'],(float)$row['conversion_factor'],(float)$row['stock_total'],(int)$match['confidence'],(string)$match['method'],(int)$productMatch['cardex_item_id'],(int)$productMatch['confidence'],(string)$productMatch['method'],$now
                ];
            }
            self::bulkInsert($insertRows);
            $productStats['coverage_percent'] = count($rows) ? round(($productStats['mapped_rows'] / count($rows)) * 100, 2) : 0;
            $mappingPayload['_product_mapping'] = $productStats;
            $mappingPayload['_snapshot_policy'] = [
                'mode'=>StockService::SNAPSHOT_MODE,
                'active_batch_id'=>0,
                'replaced_batch_ids'=>[],
                'replaced_count'=>0,
                'activated_at'=>null,
                'source'=>'draft_import',
            ];
            $mappingUpdated = $wpdb->update(StockService::batchTable(), ['mapping_json'=>wp_json_encode($mappingPayload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)], ['id'=>$batchId]);
            if ($mappingUpdated === false) throw new \RuntimeException('Não foi possível guardar a auditoria do snapshot de stock: ' . $wpdb->last_error);
            StockService::refreshBatchStats($batchId);
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        $batch = StockService::getBatch($batchId);
        $publishError = '';
        $replacedIds = [];
        if ($publish) {
            try {
                $published = StockService::publishSnapshot($batchId, get_current_user_id(), 'import_publish_now');
                $batch = (array)($published['batch'] ?? $batch);
                $replacedIds = (array)($published['replaced_batch_ids'] ?? []);
            } catch (\Throwable $publishException) {
                // The import itself is already safe and committed as Draft. Keep
                // the current live snapshot untouched and surface the publish
                // failure instead of losing the imported audit trail.
                $publishError = $publishException->getMessage();
                do_action('fieldflow_stock_imported', $batchId, [
                    'batch_id'=>$batchId,
                    'client_id'=>$clientId,
                    'project_id'=>$projectId,
                    'insignia'=>$insignia,
                    'snapshot_date'=>$snapshotDate,
                    'status'=>'draft',
                    'rows'=>count($rows),
                    'user_id'=>get_current_user_id(),
                    'source'=>'import_publish_failed',
                    'snapshot_mode'=>StockService::SNAPSHOT_MODE,
                    'publish_error'=>$publishError,
                    'import_intelligence'=>(array)($parsed['diagnostics'] ?? []),
                ]);
            }
        } else {
            do_action('fieldflow_stock_imported', $batchId, [
                'batch_id'=>$batchId,
                'client_id'=>$clientId,
                'project_id'=>$projectId,
                'insignia'=>$insignia,
                'snapshot_date'=>$snapshotDate,
                'status'=>'draft',
                'rows'=>count($rows),
                'user_id'=>get_current_user_id(),
                'source'=>'draft_import',
                'snapshot_mode'=>StockService::SNAPSHOT_MODE,
                'import_intelligence'=>(array)($parsed['diagnostics'] ?? []),
            ]);
        }
        return ['batch_id'=>$batchId,'parsed'=>$parsed,'batch'=>$batch,'publish_error'=>$publishError,'replaced_batch_ids'=>$replacedIds];
    }

    public static function parse(string $path, string $sourceFilename = '', string $insignia = ''): array {
        $ext = strtolower(pathinfo($sourceFilename !== '' ? $sourceFilename : $path, PATHINFO_EXTENSION));
        if ($ext === 'csv' || $ext === 'txt') return self::parseCsv($path);
        if ($ext !== 'xlsx') throw new \RuntimeException('Formato não suportado. Usa XLSX ou CSV.');
        $readPath = $path;
        $temporaryXlsx = '';
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') {
            $tempBase = tempnam(sys_get_temp_dir(), 'ff-stock-');
            if ($tempBase === false) throw new \RuntimeException('Não foi possível preparar o ficheiro Excel para leitura.');
            $temporaryXlsx = $tempBase . '.xlsx';
            @unlink($tempBase);
            if (!@copy($path, $temporaryXlsx)) throw new \RuntimeException('Não foi possível preparar o ficheiro Excel para leitura.');
            $readPath = $temporaryXlsx;
        }
        try {
            $sheets = OoxmlReader::read($readPath);
        } finally {
            if ($temporaryXlsx !== '' && is_file($temporaryXlsx)) @unlink($temporaryXlsx);
        }
        if (!$sheets) throw new \RuntimeException('O Excel não contém folhas legíveis.');

        $candidates = [];
        $rejectedSheets = [];
        foreach ($sheets as $sheetName => $rows) {
            $candidate = self::detectHeaderInSheet((string)$sheetName, $rows);
            if (!$candidate) continue;
            try {
                $parsed = self::buildParsed((string)$sheetName, $rows, (int)$candidate['header_row'], (int)$candidate['header_end_row'], (int)$candidate['header_depth'], (array)$candidate['mapping'], (array)$candidate['confidence']);
            } catch (\Throwable $e) {
                $rejectedSheets[] = [
                    'sheet'=>(string)$sheetName,
                    'stage'=>'row_materialization',
                    'message'=>sanitize_text_field((string)$e->getMessage()),
                ];
                continue;
            }
            if (empty($parsed['rows'])) {
                $rejectedSheets[] = [
                    'sheet'=>(string)$sheetName,
                    'stage'=>'empty_after_mapping',
                    'message'=>'Cabeçalho reconhecido, mas nenhuma linha de stock válida foi materializada.',
                ];
                continue;
            }
            $affinity = self::sheetAffinity((string)$sheetName, $insignia);
            if ($affinity <= -100) continue;
            $rowWeight = min(180, (int)round(log10(max(1, (int)$parsed['row_count']) + 1) * 60));
            $quality = (int)$candidate['score'] + $rowWeight + $affinity;
            $parsed['_quality'] = $quality;
            $candidates[] = $parsed;
        }
        if (!$candidates) {
            $detail = $rejectedSheets ? ' Detalhe: ' . (string)($rejectedSheets[0]['sheet'] ?? '') . ' / ' . (string)($rejectedSheets[0]['message'] ?? '') : '';
            throw new \RuntimeException('Não foi possível reconhecer uma tabela de stock. Procurei loja, produto e quantidade em todas as folhas e até três linhas de cabeçalho.' . $detail);
        }
        usort($candidates, static function(array $a, array $b): int {
            $cmp = ((int)$b['_quality']) <=> ((int)$a['_quality']);
            if ($cmp) return $cmp;
            return ((int)$b['row_count']) <=> ((int)$a['row_count']);
        });
        $best = $candidates[0];
        $sheetDiagnostics = [];
        foreach ($candidates as $candidate) {
            $sheetDiagnostics[] = [
                'sheet'=>(string)$candidate['sheet_name'],
                'selected'=>(string)$candidate['sheet_name'] === (string)$best['sheet_name'],
                'header_row'=>(int)$candidate['header_row'],
                'header_end_row'=>(int)$candidate['header_end_row'],
                'header_depth'=>(int)$candidate['header_depth'],
                'mapping'=>(array)$candidate['mapping'],
                'mapping_confidence'=>(array)$candidate['mapping_confidence'],
                'accepted_rows'=>(int)$candidate['row_count'],
                'quality'=>(int)$candidate['_quality'],
            ];
        }
        unset($best['_quality']);
        $best['diagnostics']['candidate_sheets'] = $sheetDiagnostics;
        $best['diagnostics']['selected_sheet'] = (string)$best['sheet_name'];
        $best['diagnostics']['workbook_sheet_count'] = count($sheets);
        $best['diagnostics']['engine'] = 'fieldflow_workbook_intelligence_v1.1';
        $best['diagnostics']['rejected_sheets'] = $rejectedSheets;
        return $best;
    }

    private static function parseCsv(string $path): array {
        $handle = fopen($path, 'rb');
        if (!$handle) throw new \RuntimeException('Não foi possível abrir o CSV.');
        $rows = [];
        $delimiter = self::detectDelimiter($path);
        $rowNo = 0;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNo++;
            $assoc = [];
            foreach ($data as $idx => $value) $assoc[WorkbookIntelligence::columnLetter($idx + 1)] = WorkbookIntelligence::clean((string)$value);
            $rows[$rowNo] = $assoc;
        }
        fclose($handle);
        $candidate = self::detectHeaderInSheet('CSV', $rows);
        if (!$candidate) throw new \RuntimeException('Não foi possível reconhecer as colunas do CSV.');
        $parsed = self::buildParsed('CSV', $rows, (int)$candidate['header_row'], (int)$candidate['header_end_row'], (int)$candidate['header_depth'], (array)$candidate['mapping'], (array)$candidate['confidence']);
        $parsed['diagnostics']['delimiter'] = $delimiter === "\t" ? 'TAB' : $delimiter;
        $parsed['diagnostics']['engine'] = 'fieldflow_workbook_intelligence_v1';
        return $parsed;
    }

    private static function detectHeaderInSheet(string $sheetName, array $rows): ?array {
        $candidate = WorkbookIntelligence::detectHeader($rows, self::ALIASES, [
            ['store_ref','store_name'],
            ['product_name','article_ref','ean'],
            ['stock_total'],
        ], 70, 3);
        if (!$candidate) return null;

        // The Continente source uses STORE for the numeric code and STORE_NAME for the designation.
        // Force this known pair if both headers are present in the composite header.
        $headerColumns = [];
        foreach ((array)$candidate['headers'] as $column => $header) $headerColumns[WorkbookIntelligence::normalise((string)$header)] = (string)$column;
        foreach ([
            ['store','store name'],
            ['loja','nome loja'],
            ['pdv','nome pdv'],
            ['estabelecimento','nome estabelecimento'],
        ] as [$refHeader,$nameHeader]) {
            if (!isset($headerColumns[$refHeader], $headerColumns[$nameHeader])) continue;
            $candidate['mapping']['store_ref'] = $headerColumns[$refHeader];
            $candidate['confidence']['store_ref'] = 100;
            $candidate['mapping']['store_name'] = $headerColumns[$nameHeader];
            $candidate['confidence']['store_name'] = 100;
            break;
        }
        // 9DK: the source column PACK describes a packaging label and is not
        // proof of a numeric stock conversion multiplier. An old fuzzy match
        // claimed 91% confidence for PACK versus 'pack factor'.
        $factorColumn = (string)($candidate['mapping']['conversion_factor'] ?? '');
        if ($factorColumn !== '') {
            $factorHeader = WorkbookIntelligence::normalise((string)($candidate['headers'][$factorColumn] ?? ''));
            if (in_array($factorHeader, ['pack', 'packs', 'embalagem'], true)) {
                unset($candidate['mapping']['conversion_factor'], $candidate['confidence']['conversion_factor']);
            }
        }
        $candidate['sheet_name'] = $sheetName;
        $candidate['rows'] = $rows;
        return $candidate;
    }

    private static function buildParsed(string $sheetName, array $rows, int $headerRow, int $headerEndRow, int $headerDepth, array $mapping, array $mappingConfidence): array {
        $items = [];
        $dedupe = [];
        $stats = [
            'source_data_rows'=>0,
            'accepted_source_rows'=>0,
            'skipped_blank'=>0,
            'skipped_total'=>0,
            'skipped_missing_identity'=>0,
            'skipped_invalid_stock'=>0,
            'duplicates_collapsed'=>0,
            'raw_stock_total'=>0.0,
            'imported_stock_total'=>0.0,
            'invalid_stock_samples'=>[],
        ];
        foreach ($rows as $rowNo => $cells) {
            if ((int)$rowNo <= $headerEndRow) continue;
            $stats['source_data_rows']++;
            $storeRef = WorkbookIntelligence::identifier(self::cell($cells, $mapping['store_ref'] ?? ''));
            $storeName = self::cell($cells, $mapping['store_name'] ?? '');
            $productName = self::cell($cells, $mapping['product_name'] ?? '');
            $articleRef = WorkbookIntelligence::identifier(self::cell($cells, $mapping['article_ref'] ?? ''));
            $ean = preg_replace('/\D+/', '', WorkbookIntelligence::identifier(self::cell($cells, $mapping['ean'] ?? '')));
            $stockRaw = self::cell($cells, $mapping['stock_total'] ?? '');

            $rowHasAnyValue = false;
            foreach ((array)$cells as $value) if (trim((string)$value) !== '') { $rowHasAnyValue = true; break; }
            if (!$rowHasAnyValue) { $stats['skipped_blank']++; continue; }
            if (($storeRef === '' && $storeName === '') || ($productName === '' && $articleRef === '' && $ean === '')) {
                $stats['skipped_missing_identity']++;
                continue;
            }
            if (WorkbookIntelligence::isTotalLabel($storeName) || WorkbookIntelligence::isTotalLabel($productName)) {
                $stats['skipped_total']++;
                continue;
            }
            $stock = WorkbookIntelligence::number($stockRaw);
            if ($stock === null) {
                $stats['skipped_invalid_stock']++;
                if (count($stats['invalid_stock_samples']) < 8) $stats['invalid_stock_samples'][] = ['row'=>(int)$rowNo,'value'=>$stockRaw];
                continue;
            }
            $stats['accepted_source_rows']++;
            $stats['raw_stock_total'] += $stock;
            $row = [
                'store_external_ref'=>sanitize_text_field($storeRef),
                'store_name'=>sanitize_text_field($storeName),
                'locality'=>sanitize_text_field(self::cell($cells, $mapping['locality'] ?? '')),
                'article_ref'=>sanitize_text_field($articleRef),
                'ean'=>sanitize_text_field($ean),
                'product_name'=>sanitize_text_field($productName),
                'unit'=>sanitize_text_field(self::cell($cells, $mapping['unit'] ?? '') ?: 'UN'),
                'conversion_factor'=>WorkbookIntelligence::number(self::cell($cells, $mapping['conversion_factor'] ?? '')) ?? 1.0,
                'stock_total'=>$stock,
            ];
            $key = StockService::storeKey($row['store_external_ref'], $row['store_name']) . '|' . StockService::normaliseText($row['ean'] ?: ($row['article_ref'] ?: $row['product_name']));
            if (isset($dedupe[$key])) {
                $items[$dedupe[$key]]['stock_total'] += $stock;
                $stats['duplicates_collapsed']++;
            } else {
                $dedupe[$key] = count($items);
                $items[] = $row;
            }
            if (count($items) > 250000) throw new \RuntimeException('O ficheiro excede o limite de 250.000 linhas de stock.');
        }
        foreach ($items as $item) $stats['imported_stock_total'] += (float)$item['stock_total'];
        $stats['raw_stock_total'] = round((float)$stats['raw_stock_total'], 3);
        $stats['imported_stock_total'] = round((float)$stats['imported_stock_total'], 3);
        $stats['unique_rows'] = count($items);
        $stats['mapping_fields'] = count($mapping);
        $stats['mapping_average_confidence'] = $mappingConfidence ? (int)round(array_sum($mappingConfidence)/count($mappingConfidence)) : 0;
        return [
            'sheet_name'=>$sheetName,
            'header_row'=>$headerRow,
            'header_end_row'=>$headerEndRow,
            'header_depth'=>$headerDepth,
            'mapping'=>$mapping,
            'mapping_confidence'=>$mappingConfidence,
            'rows'=>$items,
            'row_count'=>count($items),
            'diagnostics'=>$stats,
        ];
    }

    private static function sheetAffinity(string $sheetName, string $insignia): int {
        if ($insignia === '') return 0;
        $wanted = StockService::brandFamily($insignia);
        if ($wanted === '') return 0;
        $candidate = StockService::brandFamily($sheetName);
        if ($candidate === '') return 0;
        return $candidate === $wanted ? 55 : -120;
    }

    private static function cell(array $cells, string $column): string {
        return WorkbookIntelligence::rowValue($cells, $column);
    }

    private static function detectDelimiter(string $path): string {
        $sample = file_get_contents($path, false, null, 0, 16384);
        if ($sample === false) return ',';
        $lines = preg_split('/\R/', $sample) ?: [];
        $lines = array_slice(array_values(array_filter($lines, static fn($line) => trim((string)$line) !== '')), 0, 8);
        $delimiters = [';', ',', "\t", '|'];
        $best = ','; $bestScore = -1;
        foreach ($delimiters as $delimiter) {
            $counts = array_map(static fn($line) => substr_count((string)$line, $delimiter), $lines);
            if (!$counts) continue;
            $positive = array_values(array_filter($counts, static fn($count) => $count > 0));
            if (!$positive) continue;
            $average = array_sum($positive) / count($positive);
            $variance = 0.0;
            foreach ($positive as $count) $variance += abs($count - $average);
            $score = ($average * 10) + (count($positive) * 4) - $variance;
            if ($score > $bestScore) { $bestScore = $score; $best = $delimiter; }
        }
        return $best;
    }

    private static function productCatalog(int $clientId, int $projectId, string $insignia): array {
        global $wpdb;
        ProductCardex::install();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*,c.name AS cardex_name,c.insignia,c.client_id AS cardex_client_id,c.project_id AS cardex_project_id
             FROM " . ProductCardex::table_items() . " i
             INNER JOIN " . ProductCardex::table_cardex() . " c ON c.id=i.cardex_id
             WHERE i.is_active=1 AND c.status='active'
               AND (c.client_id=0 OR c.client_id=%d)
               AND (c.project_id=0 OR c.project_id=%d)",
            $clientId, $projectId
        ), ARRAY_A) ?: [];
        $wanted = StockService::brandFamily($insignia);
        $ranked = [];
        $bestRank = 0;
        foreach ($rows as $row) {
            $family = StockService::brandFamily(trim((string)($row['insignia'] ?? '') . ' ' . (string)($row['cardex_name'] ?? '')));
            if ($wanted !== '' && $family !== '' && $family !== $wanted) continue;
            $rank = ((int)($row['cardex_project_id'] ?? 0) === $projectId ? 4 : 0)
                + ((int)($row['cardex_client_id'] ?? 0) === $clientId ? 2 : 0)
                + ($family === $wanted && $wanted !== '' ? 1 : 0);
            if ($rank > $bestRank) $bestRank = $rank;
            $ranked[] = ['rank'=>$rank,'row'=>$row];
        }
        $out = [];
        foreach ($ranked as $entry) if ((int)$entry['rank'] === $bestRank) $out[] = $entry['row'];
        return $out;
    }

    private static function resolveProduct(array $row, array $catalog): array {
        if (!$catalog) return ['cardex_item_id'=>0,'confidence'=>0,'method'=>'unmatched'];
        $candidates = MappingEngine::productCandidates([
            'source_label'=>(string)($row['product_name'] ?? ''),
            'external_ref'=>(string)($row['article_ref'] ?? ''),
            'meta_json'=>['ean'=>(string)($row['ean'] ?? '')],
        ], $catalog, 3);
        $top = $candidates[0] ?? [];
        $second = (int)($candidates[1]['confidence'] ?? 0);
        $confidence = (int)($top['confidence'] ?? 0);
        if ($confidence < 96 || ($confidence < 99 && ($confidence - $second) < 7)) return ['cardex_item_id'=>0,'confidence'=>$confidence,'method'=>'review_required'];
        $signals = array_map('strtolower', (array)($top['signals'] ?? []));
        $method = 'high_confidence';
        foreach ($signals as $signal) {
            if (strpos($signal, 'ean exato') !== false) { $method = 'exact_ean'; break; }
            if (strpos($signal, 'referência exata') !== false) { $method = 'exact_reference'; break; }
            if (strpos($signal, 'produto exato') !== false) $method = 'exact_name';
        }
        return ['cardex_item_id'=>absint($top['target_id'] ?? 0),'confidence'=>$confidence,'method'=>$method];
    }

    private static function normaliseDate(string $value): string {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
        $ts = strtotime($value);
        return $ts ? wp_date('Y-m-d', $ts, wp_timezone()) : '';
    }

    private static function bulkInsert(array $rows): void {
        global $wpdb;
        $table = StockService::itemTable();
        $columns = '(batch_id,client_id,project_id,location_id,source_store_key,store_external_ref,store_name,locality,article_ref,ean,product_name,unit,conversion_factor,stock_total,match_confidence,match_method,cardex_item_id,product_match_confidence,product_match_method,created_at)';
        foreach (array_chunk($rows, 300) as $chunk) {
            $placeholders = [];
            $args = [];
            foreach ($chunk as $row) {
                $placeholders[] = '(%d,%d,%d,%d,%s,%s,%s,%s,%s,%s,%s,%s,%f,%f,%d,%s,%d,%d,%s,%s)';
                $args = array_merge($args, $row);
            }
            $sql = "INSERT INTO {$table} {$columns} VALUES " . implode(',', $placeholders);
            $result = $wpdb->query($wpdb->prepare($sql, ...$args));
            if ($result === false) throw new \RuntimeException('Falha ao guardar linhas de stock: ' . $wpdb->last_error);
        }
    }
}
