<?php
namespace RoutesPro\Briefings;

use RoutesPro\Import\WorkbookIntelligence;

if (!defined('ABSPATH')) exit;

class PernodWeeklyImporter {
    private const RETAILERS = [
        'sonae' => 'Sonae',
        'pingo_doce' => 'Pingo Doce',
        'auchan' => 'Auchan',
        'intermarche' => 'Intermarché',
        'eci' => 'El Corte Inglés',
    ];

    public static function parse(string $path): array {
        $sheets = OoxmlReader::read($path);
        if (!$sheets) throw new \RuntimeException('O Excel não contém folhas legíveis.');

        $week = self::detectWeek($sheets);
        $year = self::detectYear($sheets);
        $sections = [];
        $storeItems = [];

        $narrativeSheets = [];
        foreach ($sheets as $sheetName => $rows) {
            if (strpos(self::normaliseText((string)$sheetName), 'briefing') !== false) $narrativeSheets[$sheetName] = $rows;
        }
        ksort($narrativeSheets, SORT_NATURAL | SORT_FLAG_CASE);
        foreach (array_slice($narrativeSheets, 0, 3, true) as $rows) self::parseNarrativeSheet($rows, $sections);
        self::parsePromotions($sheets, $sections);
        self::parseVaps(self::sheetRows($sheets, ['VAPS Q4'], ['vaps', 'vap']), $sections);
        self::parseCentralSpaces(self::sheetRows($sheets, ['ESPAÇOS CENTRALIZADOS'], ['espacos centralizados', 'espaços centralizados']), $year, $storeItems);
        self::parseStoreAssortment(self::sheetRows($sheets, ['CARDEX (MC1L)(MC Caramelo0,7L)'], ['mc1l', 'caramelo0 7l', 'caramelo 0 7l']), $storeItems);
        self::parseShareSpace(self::sheetRows($sheets, ['Status of Share Space'], ['share space']), $week, $sections);
        self::parseCardexSheets($sheets, $sections);

        $dateBounds = self::dateBounds($sections, $storeItems, $year, $week);
        $retailers = [];
        foreach ($sections as $section) {
            $key = (string)($section['retailer_key'] ?? 'all');
            if ($key !== 'all') $retailers[$key] = self::retailerLabel($key);
        }
        foreach ($storeItems as $item) {
            $key = (string)($item['retailer_key'] ?? 'all');
            if ($key !== 'all') $retailers[$key] = self::retailerLabel($key);
        }

        return [
            'template' => 'pernod_weekly_2026',
            'title' => 'Briefing Semanal W' . $week,
            'week_no' => $week,
            'year_no' => $year,
            'valid_from' => $dateBounds['from'],
            'valid_to' => $dateBounds['to'],
            'sections' => array_values($sections),
            'store_items' => array_values($storeItems),
            'retailers' => $retailers,
            'summary' => [
                'sections' => count($sections),
                'store_items' => count($storeItems),
                'retailers' => count($retailers),
                'promotions' => self::countSections($sections, 'promotions'),
                'cardex_products' => self::countSections($sections, 'cardex'),
                'spaces' => count(array_filter($storeItems, static fn($i) => ($i['item_type'] ?? '') === 'space')),
                'assortment_links' => count(array_filter($storeItems, static fn($i) => ($i['item_type'] ?? '') === 'assortment')),
                'share_space_kpis' => self::countSections($sections, 'share_space'),
                'source_sheets' => self::sourceManifest($sheets, $sections, $storeItems, $week),
                'import_intelligence' => [
                    'engine' => 'fieldflow_workbook_intelligence_v1',
                    'workbook_sheets' => count($sheets),
                    'recognised_sections' => count($sections),
                    'store_level_records' => count($storeItems),
                    'merged_headers_supported' => true,
                    'multirow_headers_supported' => true,
                ],
            ],
        ];
    }

    public static function retailerLabel(string $key): string {
        return self::RETAILERS[$key] ?? ucwords(str_replace('_', ' ', $key));
    }


    private static function sheetRows(array $sheets, array $exactNames, array $contains): array {
        foreach ($exactNames as $name) {
            if (!empty($sheets[$name]) && is_array($sheets[$name])) return $sheets[$name];
        }
        foreach ($sheets as $sheetName => $rows) {
            $normal = self::normaliseText((string)$sheetName);
            foreach ($contains as $needle) {
                $needle = self::normaliseText((string)$needle);
                if ($needle !== '' && strpos($normal, $needle) !== false) return is_array($rows) ? $rows : [];
            }
        }
        return [];
    }

    private static function detectWeek(array $sheets): int {
        foreach (['Briefing_1', 'Briefing_2'] as $sheetName) {
            foreach (($sheets[$sheetName] ?? []) as $row) {
                foreach ($row as $value) {
                    if (preg_match('/\bW\s*(\d{1,2})\b/i', (string)$value, $m)) return max(1, min(53, (int)$m[1]));
                }
            }
        }
        foreach ($sheets as $name => $rows) {
            if (preg_match('/\bW\s*(\d{1,2})\b/i', (string)$name, $m)) return max(1, min(53, (int)$m[1]));
            foreach ($rows as $row) {
                foreach ($row as $value) {
                    if (preg_match('/\bW\s*(\d{1,2})\b/i', (string)$value, $m)) return max(1, min(53, (int)$m[1]));
                }
            }
        }
        return (int)date('W');
    }

    private static function detectYear(array $sheets): int {
        $counts = [];
        foreach ($sheets as $rows) {
            foreach ($rows as $row) {
                foreach ($row as $value) {
                    $text = trim((string)$value);
                    if (!preg_match('/^(20\d{2})$/', $text, $m)) continue;
                    $year = (int)$m[1];
                    if ($year >= 2020 && $year <= 2100) $counts[$year] = ($counts[$year] ?? 0) + 1;
                }
            }
        }
        if ($counts) {
            arsort($counts, SORT_NUMERIC);
            return (int)array_key_first($counts);
        }
        return (int)date('Y');
    }

    private static function parseNarrativeSheet(array $rows, array &$sections): void {
        $current = '';
        $seenNarrative = []; // 9DK: repeated OOXML merged-cell values are not separate instructions.
        foreach ($rows as $row) {
            $values = [];
            foreach ((array)$row as $column => $value) {
                $value = trim((string)$value);
                if ($value !== '') $values[(string)$column] = $value;
            }
            if (!$values) continue;

            // 9DK: merged retailer headings may occupy 10+ columns although they
            // are one logical value. Examine distinct visible values, not cells.
            $uniqueVisible = [];
            foreach ($values as $value) {
                $normal = self::normaliseText($value);
                if ($normal !== '') $uniqueVisible[$normal] = $value;
            }
            $marker = '';
            if (count($uniqueVisible) === 1) {
                $value = (string)reset($uniqueVisible);
                if (self::isRetailerMarker($value)) $marker = self::retailerKey($value);
            }
            if ($marker !== '') {
                $current = $marker;
                continue;
            }
            if (!$current) continue;

            $label = trim((string)($row['B'] ?? ''));
            $content = trim((string)($row['C'] ?? ''));
            if ($label === '' || $content === '') {
                $textValues = array_values(array_filter($values, static function($value): bool {
                    $n = WorkbookIntelligence::normalise((string)$value);
                    return $n !== '' && !preg_match('/^\d+(?:[.,]\d+)?$/', $n);
                }));
                if (count($textValues) >= 2) {
                    usort($textValues, static fn($a, $b) => strlen((string)$a) <=> strlen((string)$b));
                    if ($label === '') $label = (string)$textValues[0];
                    if ($content === '') $content = (string)$textValues[count($textValues)-1];
                }
            }
            if ($label === '' || $content === '' || $label === $content) continue;
            if (self::isRetailerMarker($label)) { $current = self::retailerKey($label); continue; }
            $type = self::narrativeType($label);
            // A merged row can repeat its text in columns C..P. Never count
            // identical retailer/type/title/content twice in the same sheet.
            $fingerprint = hash('sha256', implode('|', [
                $current, $type, self::normaliseText($label), self::normaliseText($content)
            ]));
            if (isset($seenNarrative[$fingerprint])) continue;
            $seenNarrative[$fingerprint] = true;
            $items = self::splitTextItems($content);
            $sections[] = [
                'retailer_key' => $current,
                'section_type' => $type,
                'title' => self::cleanSectionTitle($label),
                'content' => ['text' => $content, 'items' => $items],
                'sort_order' => self::sectionOrder($type),
            ];
        }
    }

    private static function parsePromotions(array $sheets, array &$sections): void {
        $map = [
            'SONAE_JULHO_FY27_W29' => 'sonae',
            'PD_JULHO_FY27_W29' => 'pingo_doce',
            'AUCHAN_JULHO_FY27_W29' => 'auchan',
            'ITMI_JULHO_FY27_W29' => 'intermarche',
            'ECI_JULHO_FY27_W29' => 'eci',
        ];
        $aliases = [
            'product' => ['produto capacidade','produto','produtos','descricao produto','descrição produto','artigo descricao','artigo descrição','product'],
            'start' => ['inicio','início','data inicio','data início','inicio promocao','início promoção','start','from'],
            'end' => ['fim','data fim','fim promocao','fim promoção','end','to'],
            'regular' => ['pvpr','preco regular','preço regular','pvp','regular price','preco base','preço base'],
            'discount' => ['atividade promocional','desconto','discount','mecanica','mecânica','promo activity'],
            'promo' => ['pvpr promo','pvp promo','preco promo','preço promo','preco promocional','preço promocional','promo price','promotional price'],
        ];
        foreach ($sheets as $sheetName => $rows) {
            $retailer = $map[$sheetName] ?? self::retailerFromSheetName($sheetName);
            if (!$retailer || stripos($sheetName, 'CARDEX') !== false) continue;
            $header = WorkbookIntelligence::detectHeader($rows, $aliases, [['product']], 70, 3);
            if (!$header) continue;
            $mapping = (array)$header['mapping'];
            if (empty($mapping['start']) && empty($mapping['end']) && empty($mapping['regular']) && empty($mapping['discount']) && empty($mapping['promo'])) continue;
            $items = [];
            foreach ($rows as $rowNo => $row) {
                if ((int)$rowNo <= (int)$header['header_end_row']) continue;
                $product = WorkbookIntelligence::rowValue($row, (string)($mapping['product'] ?? ''));
                if ($product === '' || WorkbookIntelligence::isTotalLabel($product)) continue;
                $from = self::excelDate(WorkbookIntelligence::rowValue($row, (string)($mapping['start'] ?? '')));
                $to = self::excelDate(WorkbookIntelligence::rowValue($row, (string)($mapping['end'] ?? '')));
                $regular = self::number(WorkbookIntelligence::rowValue($row, (string)($mapping['regular'] ?? '')));
                $discount = self::number(WorkbookIntelligence::rowValue($row, (string)($mapping['discount'] ?? '')));
                $promo = self::number(WorkbookIntelligence::rowValue($row, (string)($mapping['promo'] ?? '')));
                if ($from === '' && $to === '' && $regular == 0.0 && $discount == 0.0 && $promo == 0.0) continue;
                $items[] = [
                    'product' => $product,
                    'valid_from' => $from,
                    'valid_to' => $to,
                    'regular_price' => $regular,
                    'discount' => $discount,
                    'promo_price' => $promo,
                    'source_sheet' => (string)$sheetName,
                    'source_row' => (int)$rowNo,
                ];
            }
            if ($items) {
                $sections[] = [
                    'retailer_key' => $retailer,
                    'section_type' => 'promotions',
                    'title' => 'Promoções da semana',
                    'content' => ['items' => $items, 'header_confidence'=>(int)($header['average_confidence'] ?? 0)],
                    'sort_order' => 30,
                ];
            }
        }
    }

    private static function parseVaps(array $rows, array &$sections): void {
        $byRetailer = ['sonae' => [], 'pingo_doce' => [], 'intermarche' => [], 'auchan' => [], 'eci' => []];
        $indexes = [];

        $addOrMerge = static function (string $retailer, array $item) use (&$byRetailer, &$indexes): void {
            $name = trim((string)($item['name'] ?? ''));
            if ($name === '') return;
            $key = preg_replace('/[^a-z0-9]+/', '', strtolower(function_exists('remove_accents') ? remove_accents($name) : $name));
            if (!isset($indexes[$retailer][$key])) {
                $indexes[$retailer][$key] = count($byRetailer[$retailer]);
                $byRetailer[$retailer][] = ['name' => $name];
            }
            $idx = $indexes[$retailer][$key];
            foreach ($item as $field => $value) {
                if ($field === 'name' || $value === '' || $value === null) continue;
                $byRetailer[$retailer][$idx][$field] = $value;
            }
        };

        // Prefer a semantic matrix when the workbook exposes explicit grouped
        // headers. This prevents legacy fixed columns from being interpreted as
        // data after a template redesign.
        if (self::parseVapsIntelligent($rows, $addOrMerge)) {
            foreach ($byRetailer as $retailer => $items) {
                if (!$items) continue;
                $sections[] = [
                    'retailer_key' => $retailer,
                    'section_type' => 'vaps',
                    'title' => 'Materiais VAP',
                    'content' => ['items' => array_values($items), 'parser'=>'intelligent_header'],
                    'sort_order' => 40,
                ];
            }
            return;
        }

        // Bloco superior, referências comerciais por insígnia.
        foreach ($rows as $rowNo => $row) {
            if ($rowNo < 4 || $rowNo > 12) continue;
            $name = trim((string)($row['D'] ?? ''));
            if ($name === '' || strtoupper($name) === 'TOTAL' || preg_match('/^[+-]?\d+(?:[.,]\d+)?$/', $name)) continue;
            $status = trim((string)($row['J'] ?? ''));
            foreach (['sonae' => 'E', 'auchan' => 'F', 'eci' => 'G'] as $retailer => $col) {
                $reference = trim((string)($row[$col] ?? ''));
                if ($reference === '') continue;
                $addOrMerge($retailer, ['name' => $name, 'reference' => $reference, 'status' => $status]);
            }
        }

        // Bloco inferior, alocação planeada e execução. O ficheiro tem duas
        // colunas ITMI, por isso ambas são preservadas em vez de serem somadas.
        $allocationMap = [
            'sonae' => [['W', 'X', 'Y', 'AA', '']],
            'pingo_doce' => [['AB', 'AC', 'AD', 'AE', '']],
            'intermarche' => [['AF', 'AG', 'AH', 'AI', ''], ['AV', 'AW', 'AX', 'AY', 'secondary_']],
            'auchan' => [['AJ', 'AK', 'AL', 'AM', '']],
            'eci' => [['AN', 'AO', 'AP', 'AQ', '']],
        ];
        foreach ($rows as $rowNo => $row) {
            if ($rowNo < 27) continue;
            $name = trim((string)($row['C'] ?? ($row['V'] ?? '')));
            if ($name === '' || strtoupper($name) === 'TOTAL') continue;
            $sku = trim((string)($row['D'] ?? ($row['U'] ?? '')));
            foreach ($allocationMap as $retailer => $sets) {
                $payload = ['name' => $name];
                if ($sku !== '') $payload['sku'] = $sku;
                $hasData = false;
                foreach ($sets as [$allocationCol, $actualCol, $pendingCol, $deltaCol, $prefix]) {
                    $values = [
                        $prefix . 'allocation' => self::number($row[$allocationCol] ?? 0),
                        $prefix . 'actual' => self::number($row[$actualCol] ?? 0),
                        $prefix . 'pending' => self::number($row[$pendingCol] ?? 0),
                        $prefix . 'delta' => self::number($row[$deltaCol] ?? 0),
                    ];
                    foreach ($values as $field => $value) {
                        if ($value != 0.0) $hasData = true;
                        $payload[$field] = $value;
                    }
                }
                if ($hasData) $addOrMerge($retailer, $payload);
            }
        }

        foreach ($byRetailer as $retailer => $items) {
            if (!$items) continue;
            $sections[] = [
                'retailer_key' => $retailer,
                'section_type' => 'vaps',
                'title' => 'Materiais VAP',
                'content' => ['items' => array_values($items)],
                'sort_order' => 40,
            ];
        }
    }

    private static function parseCentralSpaces(array $rows, int $year, array &$storeItems): void {
        if (self::parseCentralSpacesIntelligent($rows, $year, $storeItems)) return;
        foreach ($rows as $rowNo => $row) {
            if ($rowNo < 3) continue;
            $sonaeStore = trim((string)($row['G'] ?? ''));
            if ($sonaeStore !== '') {
                $storeItems[] = [
                    'retailer_key' => 'sonae',
                    'external_store_name' => $sonaeStore,
                    'item_type' => 'space',
                    'title' => trim((string)($row['B'] ?? 'Espaço centralizado')),
                    'details' => [
                        'space' => trim((string)($row['K'] ?? '')),
                        'store_type' => trim((string)($row['H'] ?? '')),
                        'sales_rep' => trim((string)($row['I'] ?? '')),
                    ],
                    'valid_from' => self::excelDate($row['C'] ?? ''),
                    'valid_to' => self::excelDate($row['D'] ?? ''),
                    'sort_order' => 10,
                ];
            }
            $auchanStore = trim((string)($row['O'] ?? ''));
            if ($auchanStore !== '') {
                [$from, $to] = self::parsePortuguesePeriod((string)($row['N'] ?? ''), $year);
                $storeItems[] = [
                    'retailer_key' => 'auchan',
                    'external_store_name' => $auchanStore,
                    'item_type' => 'space',
                    'title' => trim((string)($row['M'] ?? 'Espaço centralizado')),
                    'details' => [
                        'period' => trim((string)($row['N'] ?? '')),
                        'sales_rep' => trim((string)($row['P'] ?? '')),
                    ],
                    'valid_from' => $from,
                    'valid_to' => $to,
                    'sort_order' => 10,
                ];
            }
        }
    }

    private static function parseStoreAssortment(array $rows, array &$storeItems): void {
        $productA = trim((string)($rows[3]['C'] ?? ''));
        $codeA = trim((string)($rows[3]['B'] ?? ''));
        $productB = trim((string)($rows[3]['E'] ?? ''));
        $codeB = trim((string)($rows[3]['D'] ?? ''));
        $genericHeaders = ['loja','nome loja','store','store name','codigo','code','sku','produto','product','artigo'];
        $productANormal = WorkbookIntelligence::normalise($productA);
        $productBNormal = WorkbookIntelligence::normalise($productB);
        $legacyLayout = ($productA !== '' && !in_array($productANormal, $genericHeaders, true))
            || ($productB !== '' && !in_array($productBNormal, $genericHeaders, true));
        if ($legacyLayout) {
            foreach ($rows as $rowNo => $row) {
                if ($rowNo < 4) continue;
                foreach ([['C', $productA, $codeA], ['E', $productB, $codeB]] as $def) {
                    [$col, $product, $code] = $def;
                    if ($product === '') continue;
                    $store = trim((string)($row[$col] ?? ''));
                    if ($store === '' || preg_match('/^-+$/', $store)) continue;
                    $storeItems[] = [
                        'retailer_key' => 'auchan',
                        'external_store_name' => $store,
                        'item_type' => 'assortment',
                        'title' => $product ?: 'Sortido específico',
                        'details' => ['product_code' => $code, 'product' => $product],
                        'valid_from' => '',
                        'valid_to' => '',
                        'sort_order' => 20,
                    ];
                }
            }
        }
        self::parseStoreAssortmentIntelligent($rows, $storeItems);
    }

    private static function parseShareSpace(array $rows, int $week, array &$sections): void {
        if (!$rows) return;
        $rowNumbers = array_keys($rows);
        sort($rowNumbers, SORT_NUMERIC);
        $blocks = [];

        foreach ($rowNumbers as $position => $rowNo) {
            $weekCol = '';
            $weekNo = 0;
            foreach ((array)$rows[$rowNo] as $column => $value) {
                $candidate = trim((string)$value);
                if (preg_match('/^(?:W\s*)?(\d{1,2})$/i', $candidate, $m)) {
                    $possible = (int)$m[1];
                    if ($possible >= 1 && $possible <= 53) { $weekCol = (string)$column; $weekNo = $possible; break; }
                }
            }
            if (!$weekNo) continue;

            $headerPos = null; $metricCol = '';
            for ($look = $position + 1; $look < min(count($rowNumbers), $position + 6); $look++) {
                $probeNo = $rowNumbers[$look];
                foreach ((array)$rows[$probeNo] as $column => $value) {
                    if (WorkbookIntelligence::normalise((string)$value) === 'kpi') { $headerPos = $look; $metricCol = (string)$column; break 2; }
                }
                foreach ((array)$rows[$probeNo] as $value) {
                    if (preg_match('/^(?:W\s*)?\d{1,2}$/i', trim((string)$value))) break 2;
                }
            }
            if ($headerPos === null || $metricCol === '') continue;

            $headerRowNo = $rowNumbers[$headerPos];
            $headerRow = (array)$rows[$headerRowNo];
            $shareAliases = [
                'share'=>['share','share space','quota','pr share','actual','atual'],
                'target'=>['target','objetivo','goal'],
                'competitor'=>['competitor','concorrente','competitor share','share concorrente'],
                'gap'=>['gap','desvio','diferenca','diferença'],
                'delta'=>['delta','variacao','variação','evolucao','evolução','vs semana anterior'],
            ];
            $metricIndex = WorkbookIntelligence::columnIndex($metricCol);
            $columns = ['share'=>'','target'=>'','competitor'=>'','gap'=>'','delta'=>''];
            foreach ($shareAliases as $field => $aliases) {
                $bestScore = 0; $bestColumn = '';
                foreach ($headerRow as $column => $value) {
                    if ((string)$column === $metricCol) continue;
                    $score = WorkbookIntelligence::headerMatch((string)$value, $aliases);
                    if ($score > $bestScore) { $bestScore = $score; $bestColumn = (string)$column; }
                }
                if ($bestScore >= 58) $columns[$field] = $bestColumn;
            }
            $fallback = [
                'share'=>WorkbookIntelligence::columnLetter($metricIndex+1),
                'target'=>WorkbookIntelligence::columnLetter($metricIndex+2),
                'competitor'=>WorkbookIntelligence::columnLetter($metricIndex+3),
                'gap'=>WorkbookIntelligence::columnLetter($metricIndex+4),
                'delta'=>WorkbookIntelligence::columnLetter($metricIndex+5),
            ];
            foreach ($columns as $field => $column) if ($column === '') $columns[$field] = $fallback[$field];

            $items = [];
            for ($i = $headerPos + 1; $i < count($rowNumbers) && count($items) < 30; $i++) {
                $metricRowNo = $rowNumbers[$i];
                $metric = WorkbookIntelligence::rowValue((array)$rows[$metricRowNo], $metricCol);
                if ($metric === '') continue;
                if (WorkbookIntelligence::normalise($metric) === 'kpi' || preg_match('/^(?:W\s*)?\d{1,2}$/i', $metric)) break;
                $share = self::number(WorkbookIntelligence::rowValue((array)$rows[$metricRowNo], $columns['share']));
                $target = self::number(WorkbookIntelligence::rowValue((array)$rows[$metricRowNo], $columns['target']));
                $competitor = self::number(WorkbookIntelligence::rowValue((array)$rows[$metricRowNo], $columns['competitor']));
                $gapRaw = WorkbookIntelligence::rowValue((array)$rows[$metricRowNo], $columns['gap']);
                $gap = $gapRaw === '' ? ($share - $target) : self::number($gapRaw);
                $delta = self::number(WorkbookIntelligence::rowValue((array)$rows[$metricRowNo], $columns['delta']));
                [$prLabel, $competitorLabel] = ShareSpaceMatrix::splitMetric($metric);
                $items[] = [
                    'metric' => $metric,
                    'metric_key' => sanitize_key(substr($metric, 0, 150)),
                    'pr_label' => $prLabel,
                    'competitor_label' => $competitorLabel,
                    'share' => $share,
                    'target' => $target,
                    'competitor_share' => $competitor,
                    'gap' => $gap,
                    'delta' => $delta,
                    'status' => $gap < 0 ? 'below' : 'on_or_above',
                    'calculation' => 'fronts_ratio',
                    'reference_week' => $weekNo,
                    'source_row' => (int)$metricRowNo,
                ];
            }
            if ($items) $blocks[] = ['week'=>$weekNo,'row'=>$rowNo,'items'=>$items];
        }
        if (!$blocks) return;

        $wanted = $week > 1 ? $week - 1 : 53;
        $selected = null;
        for ($i = count($blocks) - 1; $i >= 0; $i--) {
            if ((int)$blocks[$i]['week'] === $wanted) { $selected = $blocks[$i]; break; }
        }
        if (!$selected) $selected = $blocks[count($blocks)-1];
        $items = (array)($selected['items'] ?? []);
        if (!$items) return;
        $priority = array_values(array_filter($items, static fn($item) => (float)($item['gap'] ?? 0) < 0));
        usort($priority, static fn($a, $b) => ((float)$a['gap']) <=> ((float)$b['gap']));
        $sections[] = [
            'retailer_key'=>'all',
            'section_type'=>'share_space',
            'title'=>'Status of Share Space, referência W' . (int)$selected['week'],
            'content'=>[
                'week'=>(int)$selected['week'],
                'source_row'=>(int)$selected['row'],
                'items'=>$items,
                'question_config'=>ShareSpaceMatrix::configFromItems($items, ['reference_week'=>(int)$selected['week']]),
                'priority_items'=>$priority,
                'below_target_count'=>count($priority),
            ],
            'sort_order'=>50,
        ];
    }

    private static function parseCardexSheets(array $sheets, array &$sections): void {
        $map = [
            'sonae' => [['CARDEX SONAE'], ['cardex sonae']],
            'pingo_doce' => [['CARDEX PD'], ['cardex pd', 'cardex pingo']],
            'auchan' => [['Cardex Auchan'], ['cardex auchan']],
            'intermarche' => [['CARDEX ITM'], ['cardex itm', 'cardex itmi', 'cardex intermarche']],
            'eci' => [['CARDEX ECI'], ['cardex eci', 'cardex el corte']],
        ];
        $aliases = [
            'product'=>['produtos','produto','descrição','descricao','descricao produto','descrição produto','product'],
            'code'=>['código','codigo','artigo','referencia','referência','sku','product code'],
            'purchase_ref'=>['ref compra eci','referencia compra','referência compra','purchase ref','buying ref'],
            'sale_ref'=>['ref venda eci','referencia venda','referência venda','sales ref','selling ref'],
            'ean'=>['ean','ean13','ean 13','gtin','gtin13','codigo barras','código barras','barcode'],
            'type'=>['tipo','type'],
            'brand'=>['marca','brand'],
            'capacity'=>['cap cl','cap (cl)','capacidade','capacity','volume'],
            'units'=>['un cx','un/cx','unidades caixa','units case','units box'],
            'observations'=>['observações','observacoes','obs','notes','notas'],
        ];
        foreach ($map as $retailer => $names) {
            $rows = self::sheetRows($sheets, $names[0], $names[1]);
            if (!$rows) continue;
            $header = WorkbookIntelligence::detectHeader($rows, $aliases, [['product']], 70, 3);
            if (!$header) continue;
            $mapping = (array)$header['mapping'];
            $items = [];
            $category = '';
            foreach ($rows as $rowNo => $row) {
                if ((int)$rowNo <= (int)$header['header_end_row']) continue;
                $product = WorkbookIntelligence::rowValue($row, (string)($mapping['product'] ?? ''));
                $codeRaw = WorkbookIntelligence::rowValue($row, (string)($mapping['code'] ?? ''));
                $code = WorkbookIntelligence::identifier($codeRaw);
                $purchaseRef = WorkbookIntelligence::identifier(WorkbookIntelligence::rowValue($row, (string)($mapping['purchase_ref'] ?? '')));
                $saleRef = WorkbookIntelligence::identifier(WorkbookIntelligence::rowValue($row, (string)($mapping['sale_ref'] ?? '')));
                $ean = preg_replace('/\D+/', '', WorkbookIntelligence::identifier(WorkbookIntelligence::rowValue($row, (string)($mapping['ean'] ?? ''))));
                if ($code === '' && $purchaseRef !== '') $code = $purchaseRef;

                // Category/section rows are merged across the Cardex columns. OoxmlReader
                // expands merged values into every cell, so detect that pattern before
                // treating the row as a product. This keeps headers, notes and category
                // separators out of the product catalogue.
                $mappedValues = [];
                foreach (['code','purchase_ref','sale_ref','ean','type','brand','product','capacity','units','observations'] as $field) {
                    $column = (string)($mapping[$field] ?? '');
                    if ($column === '') continue;
                    $value = trim(WorkbookIntelligence::rowValue($row, $column));
                    if ($value !== '') $mappedValues[] = WorkbookIntelligence::normalise($value);
                }
                $uniqueValues = array_values(array_unique(array_filter($mappedValues, static fn($v) => $v !== '')));
                if (count($uniqueValues) === 1) {
                    $candidateCategory = trim((string)($codeRaw !== '' ? $codeRaw : $product));
                    $normalCategory = WorkbookIntelligence::normalise($candidateCategory);
                    if ($candidateCategory !== '' && !in_array($normalCategory, ['observacoes','observacao','datas de entrega','data de entrega'], true) && strpos($normalCategory, 'artigos ') !== 0) $category = sanitize_text_field($candidateCategory);
                    continue;
                }

                if ($product === '' || WorkbookIntelligence::isTotalLabel($product)) continue;
                $capacityRaw = WorkbookIntelligence::rowValue($row, (string)($mapping['capacity'] ?? ''));
                $unitsRaw = WorkbookIntelligence::rowValue($row, (string)($mapping['units'] ?? ''));
                $capacityNumber = WorkbookIntelligence::number($capacityRaw);
                $unitsNumber = WorkbookIntelligence::number($unitsRaw);
                $hasProductIdentity = ($code !== '' || $purchaseRef !== '' || $ean !== '');
                if (!$hasProductIdentity || ($capacityNumber === null && $unitsNumber === null)) continue;

                $rawType = WorkbookIntelligence::rowValue($row, (string)($mapping['type'] ?? ''));
                $items[] = [
                    'code'=>$code,
                    'purchase_ref'=>$purchaseRef,
                    'sale_ref'=>$saleRef,
                    'ean'=>$ean,
                    'product'=>$product,
                    'category'=>$category,
                    'type'=>$rawType,
                    'brand'=>WorkbookIntelligence::rowValue($row, (string)($mapping['brand'] ?? '')),
                    'capacity_cl'=>$capacityNumber === null ? 0 : self::capacity($capacityRaw),
                    'units_box'=>$unitsNumber === null ? 0 : $unitsNumber,
                    'observations'=>WorkbookIntelligence::rowValue($row, (string)($mapping['observations'] ?? '')),
                    'source_row'=>(int)$rowNo,
                ];
            }
            if ($items) {
                $anomalies = [];
                foreach ($items as $item) {
                    $type = strtolower(remove_accents(trim((string)($item['type'] ?? ''))));
                    $type = trim(preg_replace('/[^a-z0-9]+/', '_', $type), '_');
                    $known = true;
                    if ($retailer === 'pingo_doce' && $type !== '' && !preg_match('/^p[1-5]$/', $type)) $known = false;
                    elseif ($retailer === 'sonae' && $type !== '' && !in_array($type, ['pbl'], true)) $known = false;
                    elseif ($retailer === 'auchan' && $type !== '' && !in_array($type, ['pbl','pbs'], true)) $known = false;
                    elseif ($retailer === 'intermarche' && $type !== '' && !in_array($type, ['std','fluxo_t','fluxo_tenso'], true)) $known = false;
                    if (!$known) $anomalies[] = [
                        'code'=>(string)($item['code'] ?? ''),
                        'product'=>(string)($item['product'] ?? ''),
                        'type'=>(string)($item['type'] ?? ''),
                        'source_row'=>(int)($item['source_row'] ?? 0),
                        'reason'=>'unknown_assortment_type',
                    ];
                }
                $sections[] = [
                    'retailer_key'=>$retailer,
                    'section_type'=>'cardex',
                    'title'=>'Cardex de referência',
                    'content'=>['items'=>$items,'header_confidence'=>(int)($header['average_confidence'] ?? 0),'anomalies'=>$anomalies],
                    'sort_order'=>60,
                ];
            }
        }
    }

    private static function parseVapsIntelligent(array $rows, callable $addOrMerge): bool {
        if (!$rows) return false;
        $rowNumbers = array_keys($rows);
        sort($rowNumbers, SORT_NUMERIC);
        $rowNumbers = array_slice($rowNumbers, 0, 55);
        $nameAliases = ['produto','material','vap','descricao','designacao','nome material','material description'];
        $skuAliases = ['sku','codigo','artigo','material code','product code'];
        $statusAliases = ['status','estado','situacao'];
        $metricAliases = [
            'reference'=>['referencia','reference','ref comercial','ref'],
            'allocation'=>['alocacao','allocation','planeado','planned','quantidade planeada'],
            'actual'=>['real','actual','executado','implementado','implemented'],
            'pending'=>['pendente','pending','por executar','falta'],
            'delta'=>['delta','desvio','gap','diferenca'],
        ];
        $best = null;
        foreach ($rowNumbers as $position => $startRow) {
            for ($depth=1; $depth<=3; $depth++) {
                $slice=array_slice($rowNumbers,$position,$depth);
                if(count($slice)!==$depth) break;
                $headers=WorkbookIntelligence::compositeHeaders($rows,$slice);
                if(!$headers) continue;
                [$nameCol,$nameScore]=self::bestHeaderColumn($headers,$nameAliases);
                if($nameCol===''||$nameScore<68) continue;
                [$skuCol,$skuScore]=self::bestHeaderColumn($headers,$skuAliases,[$nameCol]);
                [$statusCol,$statusScore]=self::bestHeaderColumn($headers,$statusAliases,[$nameCol,$skuCol]);
                $matrix=[];$coverage=0;
                foreach($headers as $column=>$header){
                    $retailer=self::retailerKey((string)$header);
                    if($retailer==='') continue;
                    foreach($metricAliases as $metric=>$aliases){
                        $score=WorkbookIntelligence::headerMatch((string)$header,$aliases);
                        if($score>=64 && (!isset($matrix[$retailer][$metric]) || $score>$matrix[$retailer][$metric]['score'])){
                            $matrix[$retailer][$metric]=['column'=>(string)$column,'score'=>$score];
                        }
                    }
                }
                foreach($matrix as $set) $coverage+=count($set);
                if($coverage<2) continue;
                $score=($nameScore*2)+($coverage*45)+max(0,$skuScore-50)+max(0,$statusScore-50)-(($depth-1)*3);
                $candidate=['header_end'=>(int)end($slice),'headers'=>$headers,'name_col'=>$nameCol,'sku_col'=>$skuScore>=60?$skuCol:'','status_col'=>$statusScore>=60?$statusCol:'','matrix'=>$matrix,'score'=>$score];
                if($best===null||$candidate['score']>$best['score'])$best=$candidate;
            }
        }
        if(!$best) return false;
        $parsedAny=false;
        foreach($rows as $rowNo=>$row){
            if((int)$rowNo<=(int)$best['header_end']) continue;
            $name=WorkbookIntelligence::rowValue((array)$row,(string)$best['name_col']);
            if($name===''||WorkbookIntelligence::isTotalLabel($name)) continue;
            $sku=$best['sku_col']!==''?WorkbookIntelligence::identifier(WorkbookIntelligence::rowValue((array)$row,(string)$best['sku_col'])):'';
            $status=$best['status_col']!==''?WorkbookIntelligence::rowValue((array)$row,(string)$best['status_col']):'';
            foreach((array)$best['matrix'] as $retailer=>$metrics){
                $payload=['name'=>$name]; if($sku!=='')$payload['sku']=$sku; if($status!=='')$payload['status']=$status;
                $has=false;
                foreach($metrics as $metric=>$def){
                    $raw=WorkbookIntelligence::rowValue((array)$row,(string)$def['column']);
                    if($metric==='reference'){
                        $value=WorkbookIntelligence::identifier($raw); if($value!==''){$payload['reference']=$value;$has=true;}
                    }else{
                        $number=WorkbookIntelligence::number($raw); if($number!==null){$payload[$metric]=(float)$number;if((float)$number!=0.0)$has=true;}
                    }
                }
                if($has){$addOrMerge((string)$retailer,$payload);$parsedAny=true;}
            }
        }
        return $parsedAny;
    }

    private static function parseCentralSpacesIntelligent(array $rows, int $year, array &$storeItems): bool {
        if(!$rows) return false;
        $rowNumbers=array_keys($rows);sort($rowNumbers,SORT_NUMERIC);$rowNumbers=array_slice($rowNumbers,0,35);
        $storeAliases=['loja','nome loja','store','store name','pdv','estabelecimento'];
        $fields=[
            'title'=>['material','acao','ativacao','titulo','descricao','description'],
            'space'=>['espaco','space','local exposicao','localizacao'],
            'store_type'=>['tipo loja','store type','formato'],
            'sales_rep'=>['sales rep','comercial','responsavel','rep'],
            'from'=>['inicio','data inicio','valid from','from'],
            'to'=>['fim','data fim','valid to','to'],
            'period'=>['periodo','period','datas'],
        ];
        $best=null;
        foreach($rowNumbers as $position=>$startRow){
            for($depth=1;$depth<=3;$depth++){
                $slice=array_slice($rowNumbers,$position,$depth);if(count($slice)!==$depth)break;
                $hasRetailerMarker=false;
                foreach($slice as $sliceRow){foreach((array)($rows[$sliceRow]??[]) as $sliceValue){if(self::isRetailerMarker((string)$sliceValue)){$hasRetailerMarker=true;break 2;}}}
                if(!$hasRetailerMarker)continue;
                $headers=WorkbookIntelligence::compositeHeaders($rows,$slice);if(!$headers)continue;
                $blocks=[];
                foreach($headers as $column=>$header){
                    $storeScore=WorkbookIntelligence::headerMatch((string)$header,$storeAliases);
                    $headerNormal=WorkbookIntelligence::normalise((string)$header);
                    if($storeScore<64 || strpos($headerNormal,'tipo loja')!==false || strpos($headerNormal,'store type')!==false)continue;
                    $retailer=self::retailerKey((string)$header);if($retailer==='')continue;
                    $storeIndex=WorkbookIntelligence::columnIndex((string)$column);$mapped=[];
                    foreach($fields as $field=>$aliases){
                        $bestCol='';$bestScore=0;$bestDistance=99;
                        foreach($headers as $candidateCol=>$candidateHeader){
                            $distance=abs(WorkbookIntelligence::columnIndex((string)$candidateCol)-$storeIndex);if($distance>7)continue;
                            $candidateRetailer=self::retailerKey((string)$candidateHeader);if($candidateRetailer!==''&&$candidateRetailer!==$retailer)continue;
                            $score=WorkbookIntelligence::headerMatch((string)$candidateHeader,$aliases);
                            if($score>=58&&($score>$bestScore||($score===$bestScore&&$distance<$bestDistance))){$bestScore=$score;$bestDistance=$distance;$bestCol=(string)$candidateCol;}
                        }
                        if($bestCol!=='')$mapped[$field]=$bestCol;
                    }
                    $blocks[]=['retailer'=>$retailer,'store_col'=>(string)$column,'fields'=>$mapped,'score'=>$storeScore+(count($mapped)*18)];
                }
                if(!$blocks)continue;
                $score=array_sum(array_column($blocks,'score'))-(($depth-1)*3);
                $candidate=['header_end'=>(int)end($slice),'blocks'=>$blocks,'score'=>$score];
                if($best===null||$candidate['score']>$best['score'])$best=$candidate;
            }
        }
        if(!$best)return false;
        $seen=[];
        foreach($storeItems as $item)$seen[self::storeItemIdentity($item)]=true;
        $added=0;
        foreach($rows as $rowNo=>$row){
            if((int)$rowNo<=(int)$best['header_end'])continue;
            foreach((array)$best['blocks'] as $block){
                $store=WorkbookIntelligence::rowValue((array)$row,(string)$block['store_col']);
                if($store===''||WorkbookIntelligence::isTotalLabel($store))continue;
                $f=(array)$block['fields'];
                $title=WorkbookIntelligence::rowValue((array)$row,(string)($f['title']??''));
                $period=WorkbookIntelligence::rowValue((array)$row,(string)($f['period']??''));
                $from=self::excelDate(WorkbookIntelligence::rowValue((array)$row,(string)($f['from']??'')));
                $to=self::excelDate(WorkbookIntelligence::rowValue((array)$row,(string)($f['to']??'')));
                if(($from===''||$to==='')&&$period!==''){[$pf,$pt]=self::parsePortuguesePeriod($period,$year);$from=$from?:$pf;$to=$to?:$pt;}
                $item=[
                    'retailer_key'=>(string)$block['retailer'],
                    'external_store_name'=>$store,
                    'item_type'=>'space',
                    'title'=>$title!==''?$title:'Espaco centralizado',
                    'details'=>array_filter([
                        'space'=>WorkbookIntelligence::rowValue((array)$row,(string)($f['space']??'')),
                        'store_type'=>WorkbookIntelligence::rowValue((array)$row,(string)($f['store_type']??'')),
                        'sales_rep'=>WorkbookIntelligence::rowValue((array)$row,(string)($f['sales_rep']??'')),
                        'period'=>$period,
                        'source_row'=>(int)$rowNo,
                        'parser'=>'intelligent_header',
                    ],static fn($v)=>$v!==''&&$v!==null),
                    'valid_from'=>$from,'valid_to'=>$to,'sort_order'=>10,
                ];
                $identity=self::storeItemIdentity($item);if(isset($seen[$identity]))continue;$seen[$identity]=true;$storeItems[]=$item;$added++;
            }
        }
        return $added>0;
    }

    private static function parseStoreAssortmentIntelligent(array $rows, array &$storeItems): void {
        if(!$rows)return;
        // If the legacy layout already yielded records, do not second-guess it.
        $legacyCount=0;foreach($storeItems as $item)if(($item['retailer_key']??'')==='auchan'&&($item['item_type']??'')==='assortment')$legacyCount++;
        if($legacyCount>0)return;
        $aliases=[
            'store'=>['loja','nome loja','store','store name','pdv'],
            'product'=>['produto','descricao produto','product','product name','material'],
            'code'=>['codigo','sku','artigo','product code','material code'],
            'retailer'=>['insignia','retailer','banner'],
        ];
        $header=WorkbookIntelligence::detectHeader($rows,$aliases,[['store'],['product']],40,3);
        if($header){
            $m=(array)$header['mapping'];
            foreach($rows as $rowNo=>$row){
                if((int)$rowNo<=(int)$header['header_end_row'])continue;
                $store=WorkbookIntelligence::rowValue((array)$row,(string)($m['store']??''));$product=WorkbookIntelligence::rowValue((array)$row,(string)($m['product']??''));
                if($store===''||$product===''||WorkbookIntelligence::isTotalLabel($store))continue;
                $retailer=self::retailerKey(WorkbookIntelligence::rowValue((array)$row,(string)($m['retailer']??'')))?:'auchan';
                $storeItems[]=['retailer_key'=>$retailer,'external_store_name'=>$store,'item_type'=>'assortment','title'=>$product,'details'=>['product_code'=>WorkbookIntelligence::identifier(WorkbookIntelligence::rowValue((array)$row,(string)($m['code']??''))),'product'=>$product,'source_row'=>(int)$rowNo,'parser'=>'intelligent_table'],'valid_from'=>'','valid_to'=>'','sort_order'=>20];
            }
            return;
        }
        // Matrix fallback: product names across the top, store lists below each product.
        $rowNumbers=array_keys($rows);sort($rowNumbers,SORT_NUMERIC);$top=array_slice($rowNumbers,0,8);$best=[];$bestScore=0;
        foreach($top as $headerRow){
            $defs=[];
            foreach((array)($rows[$headerRow]??[]) as $column=>$value){
                $product=WorkbookIntelligence::clean((string)$value);$normal=WorkbookIntelligence::normalise($product);
                if($product===''||WorkbookIntelligence::isTotalLabel($product)||in_array($normal,['loja','store','codigo','code','sku'],true))continue;
                $dataCount=0;foreach($rows as $rn=>$dataRow){if((int)$rn<=(int)$headerRow)continue;$v=WorkbookIntelligence::clean((string)($dataRow[$column]??''));if($v!==''&&!WorkbookIntelligence::isTotalLabel($v))$dataCount++;if($dataCount>=2)break;}
                if($dataCount===0)continue;
                $idx=WorkbookIntelligence::columnIndex((string)$column);$left=WorkbookIntelligence::columnLetter(max(1,$idx-1));$code=WorkbookIntelligence::identifier(WorkbookIntelligence::clean((string)($rows[$headerRow][$left]??'')));
                $productScore=(strpos($product,' ')!==false?20:0)+min(30,strlen($product));
                if($code!==''&&strlen($code)<=32)$productScore+=15;
                if($productScore>=28)$defs[]=['column'=>(string)$column,'product'=>$product,'code'=>$code];
            }
            $score=count($defs)*50;if($score>$bestScore){$bestScore=$score;$best=['row'=>(int)$headerRow,'defs'=>$defs];}
        }
        if(empty($best['defs']))return;
        foreach((array)$best['defs'] as $def){
            foreach($rows as $rowNo=>$row){
                if((int)$rowNo<=(int)$best['row'])continue;$store=WorkbookIntelligence::rowValue((array)$row,(string)$def['column']);
                if($store===''||WorkbookIntelligence::isTotalLabel($store)||preg_match('/^-+$/',$store))continue;
                $storeItems[]=['retailer_key'=>'auchan','external_store_name'=>$store,'item_type'=>'assortment','title'=>(string)$def['product'],'details'=>['product_code'=>(string)$def['code'],'product'=>(string)$def['product'],'source_row'=>(int)$rowNo,'parser'=>'intelligent_matrix'],'valid_from'=>'','valid_to'=>'','sort_order'=>20];
            }
        }
    }

    private static function bestHeaderColumn(array $headers, array $aliases, array $excluded=[]): array {
        $best='';$score=0;foreach($headers as $column=>$header){if(in_array((string)$column,$excluded,true))continue;$candidate=WorkbookIntelligence::headerMatch((string)$header,$aliases);if($candidate>$score){$score=$candidate;$best=(string)$column;}}return[$best,$score];
    }

    private static function storeItemIdentity(array $item): string {
        return implode('|',[sanitize_key((string)($item['retailer_key']??'')),WorkbookIntelligence::normalise((string)($item['external_store_name']??'')),sanitize_key((string)($item['item_type']??'')),WorkbookIntelligence::normalise((string)($item['title']??'')),(string)($item['valid_from']??''),(string)($item['valid_to']??'')]);
    }

    private static function dateBounds(array $sections, array $storeItems, int $year, int $week): array {
        // 9DK: dates for promotions/spaces are action-level validity; they must
        // not stretch the publication window of a weekly Wxx briefing.
        // DateTime::setISODate also handles ISO week transitions correctly.
        $dt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $from = $dt->setISODate($year, $week, 1);
        return ['from' => $from->format('Y-m-d'), 'to' => $from->modify('+6 days')->format('Y-m-d')];
    }

    private static function isRetailerMarker(string $value): bool {
        $n = self::normaliseText($value);
        if ($n === '') return false;
        return in_array($n, ['sonae','pingo doce','pd','auchan','jumbo','intermarche','itmi','itm','el corte ingles','eci'], true)
            || preg_match('/^(?:sonae|pingo doce|auchan|jumbo|intermarche|el corte ingles)(?:\s*[:\-])?$/', $n);
    }

    private static function retailerKey(string $value): string {
        $v = self::normaliseText($value);
        if ($v === '') return '';
        if (strpos($v, 'sonae') !== false || strpos($v, 'continente') !== false || preg_match('/(^|\s)(cnt|cbd|mdl)(\s|$)/', $v)) return 'sonae';
        if (strpos($v, 'pingo doce') !== false || preg_match('/(^|\s)pd(\s|$)/', $v)) return 'pingo_doce';
        if (strpos($v, 'auchan') !== false || strpos($v, 'jumbo') !== false) return 'auchan';
        if (strpos($v, 'intermarche') !== false || preg_match('/(^|\s)itm[i]?(\s|$)/', $v)) return 'intermarche';
        if (strpos($v, 'el corte') !== false || preg_match('/(^|\s)eci(\s|$)/', $v)) return 'eci';
        return '';
    }

    private static function retailerFromSheetName(string $name): string {
        $n = self::normaliseText($name);
        if (strpos($n, 'sonae') !== false) return 'sonae';
        if (preg_match('/(^|\s)pd(\s|$)/', $n) || strpos($n, 'pingo') !== false) return 'pingo_doce';
        if (strpos($n, 'auchan') !== false) return 'auchan';
        if (preg_match('/(^|\s)itm[i]?(\s|$)/', $n) || strpos($n, 'intermarche') !== false) return 'intermarche';
        if (preg_match('/(^|\s)eci(\s|$)/', $n) || strpos($n, 'el corte') !== false) return 'eci';
        return '';
    }

    private static function narrativeType(string $label): string {
        $n = self::normaliseText($label);
        if (strpos($n, 'folheto') !== false) return 'leaflets';
        if (strpos($n, 'espaco') !== false) return 'spaces_summary';
        if (strpos($n, 'acao') !== false) return 'actions';
        if (strpos($n, 'foco') !== false) return 'focus';
        return 'information';
    }

    private static function cleanSectionTitle(string $label): string {
        $label = trim(str_replace('€', '', $label));
        return $label !== '' ? $label : 'Informação';
    }

    private static function sectionOrder(string $type): int {
        return ['focus' => 10, 'leaflets' => 20, 'spaces_summary' => 25, 'actions' => 28, 'information' => 29][$type] ?? 29;
    }

    private static function splitTextItems(string $content): array {
        $content = preg_replace('/\s+(?=\d+\.)/u', "\n", trim($content));
        $parts = preg_split('/\R+/u', $content) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = preg_replace('/^\s*\d+[\.\)]\s*/u', '', trim($part));
            if ($part !== '') $out[] = $part;
        }
        return $out;
    }

    private static function findHeaderRow(array $rows, array $needles): array {
        foreach ($rows as $row) {
            $headers = self::headerMap($row);
            $joined = implode(' ', array_values($headers));
            foreach ($needles as $needle) {
                if (strpos($joined, self::normaliseText($needle)) !== false) return $row;
            }
        }
        return [];
    }

    private static function headerMap(array $row): array {
        $out = [];
        foreach ($row as $col => $value) $out[$col] = self::normaliseText((string)$value);
        return $out;
    }

    private static function columnByHeader(array $headers, array $names): string {
        foreach ($names as $name) {
            $needle = self::normaliseText($name);
            foreach ($headers as $col => $header) {
                if ($header === $needle || strpos($header, $needle) !== false) return (string)$col;
            }
        }
        return '';
    }

    private static function excelDate($value): string {
        if ($value === null || $value === '') return '';
        if (is_numeric($value)) {
            $days = (int)floor((float)$value);
            if ($days > 20000 && $days < 100000) {
                $date = new \DateTime('1899-12-30', new \DateTimeZone('UTC'));
                $date->modify('+' . $days . ' days');
                return $date->format('Y-m-d');
            }
        }
        $timestamp = strtotime((string)$value);
        return $timestamp ? date('Y-m-d', $timestamp) : '';
    }

    private static function parsePortuguesePeriod(string $value, int $year): array {
        $months = ['jan'=>1,'fev'=>2,'mar'=>3,'abr'=>4,'mai'=>5,'jun'=>6,'jul'=>7,'ago'=>8,'set'=>9,'out'=>10,'nov'=>11,'dez'=>12];
        $normal = trim($value);
        if (function_exists('remove_accents')) $normal = remove_accents($normal);
        else $normal = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normal) ?: $normal;
        $normal = strtolower($normal);
        if (preg_match('/(\d{1,2})\s*\/\s*([a-z]{3}).*?(\d{1,2})\s*\/\s*([a-z]{3})/u', $normal, $m)) {
            $m1 = $months[$m[2]] ?? 0;
            $m2 = $months[$m[4]] ?? 0;
            if ($m1 && $m2) return [sprintf('%04d-%02d-%02d', $year, $m1, (int)$m[1]), sprintf('%04d-%02d-%02d', $year, $m2, (int)$m[3])];
        }
        return ['', ''];
    }

    private static function capacity($value): float {
        $capacity = self::number($value);
        if ($capacity > 0 && $capacity < 1) $capacity *= 100;
        return round($capacity, 2);
    }

    private static function number($value): float {
        if (is_int($value) || is_float($value)) return round((float)$value, 6);
        $text = trim((string)$value);
        if ($text === '') return 0.0;
        $text = str_replace(["\xC2\xA0", ' '], '', $text);
        $text = str_replace(',', '.', $text);
        // Importante para valores como -6.8E-2 usados no Share Space.
        if (is_numeric($text)) return round((float)$text, 6);
        if (preg_match('/[-+]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][-+]?\d+)?/', $text, $m)) {
            return round((float)$m[0], 6);
        }
        return 0.0;
    }

    private static function normaliseText(string $value): string {
        if (function_exists('remove_accents')) $value = remove_accents($value);
        else $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private static function sourceManifest(array $sheets, array $sections, array $storeItems, int $week): array {
        $sectionCounts = [];
        foreach ($sections as $section) {
            $key = (string)($section['retailer_key'] ?? 'all') . '|' . (string)($section['section_type'] ?? 'information');
            $sectionCounts[$key] = ($sectionCounts[$key] ?? 0) + count((array)($section['content']['items'] ?? []));
        }
        $itemCounts = [];
        foreach ($storeItems as $item) {
            $key = (string)($item['retailer_key'] ?? 'all') . '|' . (string)($item['item_type'] ?? 'information');
            $itemCounts[$key] = ($itemCounts[$key] ?? 0) + 1;
        }
        $manifest = [];
        foreach ($sheets as $sheetName => $rows) {
            $normal = self::normaliseText((string)$sheetName);
            $type = 'support'; $label = 'Folha de apoio'; $records = count($rows); $retailer = '';
            if ($normal === 'o que e') { $type = 'guide'; $label = 'Enquadramento do briefing'; }
            elseif (strpos($normal, 'briefing') !== false) { $type = 'narrative'; $label = 'Prioridades e foco semanal'; }
            elseif (strpos($normal, 'status of share space') !== false) {
                $type = 'share_space'; $label = 'Status of Share Space';
                $records = $sectionCounts['all|share_space'] ?? 0;
            }
            elseif (strpos($normal, 'espacos centralizados') !== false) {
                $type = 'central_spaces'; $label = 'Espaços centralizados';
                $records = ($itemCounts['sonae|space'] ?? 0) + ($itemCounts['auchan|space'] ?? 0);
            }
            elseif (strpos($normal, 'vaps') !== false || preg_match('/\bvap\b/', $normal)) {
                $type = 'vaps'; $label = 'Materiais VAP';
                $records = 0; foreach (['sonae','pingo_doce','auchan','intermarche','eci'] as $r) $records += $sectionCounts[$r.'|vaps'] ?? 0;
            }
            elseif (strpos($normal, 'mc1l') !== false || strpos($normal, 'caramelo0 7l') !== false || strpos($normal, 'caramelo 0 7l') !== false) {
                $type = 'store_assortment'; $label = 'Sortido específico por loja';
                $records = $itemCounts['auchan|assortment'] ?? 0; $retailer = 'auchan';
            }
            elseif (strpos($normal, 'cardex') !== false) {
                $type = 'cardex'; $label = 'Cardex de produtos'; $retailer = self::retailerFromSheetName((string)$sheetName);
                $records = $retailer ? ($sectionCounts[$retailer.'|cardex'] ?? 0) : count($rows);
            }
            else {
                $retailer = self::retailerFromSheetName((string)$sheetName);
                if ($retailer) {
                    $type = 'promotions'; $label = 'Promoções da semana';
                    $records = $sectionCounts[$retailer.'|promotions'] ?? 0;
                }
            }
            $manifest[] = [
                'sheet' => (string)$sheetName,
                'type' => $type,
                'label' => $label,
                'retailer_key' => $retailer,
                'records' => (int)$records,
                'rows_read' => count($rows),
                'week_reference' => $type === 'share_space' ? max(1, $week - 1) : 0,
                'status' => 'integrated',
            ];
        }
        return $manifest;
    }

    private static function countSections(array $sections, string $type): int {
        $count = 0;
        foreach ($sections as $section) {
            if (($section['section_type'] ?? '') !== $type) continue;
            $count += count((array)($section['content']['items'] ?? []));
        }
        return $count;
    }
}
