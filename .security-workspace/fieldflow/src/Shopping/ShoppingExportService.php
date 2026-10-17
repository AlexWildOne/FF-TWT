<?php
namespace RoutesPro\Shopping;

if (!defined('ABSPATH')) exit;

final class ShoppingExportService {
    public static function stream(string $format, array $filters = [], int $userId = 0): void {
        $format = sanitize_key($format);
        if (!in_array($format, ['csv','xlsx','pdf'], true)) $format = 'csv';
        $dataset = ShoppingResultsService::exportDataset($filters, $userId ?: get_current_user_id());
        $stamp = wp_date('Y-m-d-His');
        if ($format === 'xlsx') {
            self::streamXlsx('fieldflow-shopping-resultados-' . $stamp . '.xlsx', $dataset);
        } elseif ($format === 'pdf') {
            self::streamPdf('fieldflow-shopping-resultados-' . $stamp . '.pdf', $dataset);
        } else {
            self::streamCsv('fieldflow-shopping-resultados-' . $stamp . '.csv', $dataset);
        }
    }

    /**
     * Creates the XLSX/PDF bundle attached to a single Shopping submission.
     * Files are generated once per email operation and can be reused for every recipient.
     */
    public static function createSubmissionAttachments(int $submissionId, int $userId = 0, bool $xlsx = true, bool $pdf = true, array $snapshot = []): array {
        $requested = [];
        if ($xlsx) $requested[] = 'xlsx';
        if ($pdf) $requested[] = 'pdf';
        if (!$requested) return ['attachments'=>[], 'files'=>[], 'errors'=>[], 'requested'=>[]];

        // Email, XLSX and PDF must all come from the exact same canonical
        // submission snapshot. Re-querying through a user-scoped results view
        // can legitimately return a different scope and used to make Shopping
        // emails silently lose their attachments.
        $detail = $snapshot
            ? self::normaliseSubmissionDetail($snapshot, $submissionId)
            : self::normaliseSubmissionDetail(ShoppingResultsService::detail($submissionId, $userId ?: get_current_user_id()), $submissionId);
        $base = self::submissionFilenameBase($detail);
        $attachments = [];
        $files = [];
        $errors = [];

        if ($xlsx) {
            try {
                $prices = array_values((array)($detail['prices'] ?? []));
                $regularCount = 0;
                $promoCount = 0;
                foreach ($prices as $price) {
                    if (($price['regular'] ?? null) !== null) $regularCount++;
                    if (($price['promo'] ?? null) !== null) $promoCount++;
                }
                $summaryRows = [
                    ['Submissão', '#' . (int)($detail['id'] ?? $submissionId)],
                    ['Data de submissão', self::displayDateTime((string)($detail['submitted_at'] ?? ''))],
                    ['Cliente', (string)($detail['client_name'] ?? '')],
                    ['Projeto', (string)($detail['project_name'] ?? '')],
                    ['Shopping / lote', (string)($detail['title'] ?? '')],
                    ['Período', self::periodLabel((int)($detail['week_no'] ?? 0), (int)($detail['year_no'] ?? 0))],
                    ['Insígnia', (string)($detail['insignia'] ?? '')],
                    ['Loja', (string)($detail['location_name'] ?? '')],
                    ['Cidade', (string)($detail['city'] ?? '')],
                    ['Merchandiser', (string)($detail['merchandiser_name'] ?? '')],
                    ['Email merchandiser', (string)($detail['merchandiser_email'] ?? '')],
                    ['Nota de preços', (string)($detail['price_note'] ?? '')],
                    ['Artigos', (string)count($prices)],
                    ['PVP regular preenchido', $regularCount . ' / ' . count($prices)],
                    ['PVP promoção preenchido', $promoCount . ' / ' . count($prices)],
                    ['Observações', (string)($detail['notes'] ?? '')],
                ];
                $priceRows = [];
                $position = 0;
                foreach ($prices as $price) {
                    $position++;
                    $regular = self::priceValue($price['regular'] ?? null);
                    $promo = self::priceValue($price['promo'] ?? null);
                    $analysis = self::priceAnalysis($regular, $promo);
                    $priceRows[] = [
                        $position,
                        (string)($price['type'] ?? ''),
                        (string)($price['name'] ?? ''),
                        $regular === null ? '' : $regular,
                        $promo === null ? '' : $promo,
                        $analysis['delta'] === null ? '' : $analysis['delta'],
                        $analysis['variation'] === null ? '' : $analysis['variation'],
                        $analysis['status'],
                    ];
                }
                $path = self::createXlsx($base . '.xlsx', [
                    ['name'=>'Resumo','title'=>'Shopping, resumo da submissão','headers'=>['Campo','Valor'],'rows'=>$summaryRows,'types'=>['auto','auto'],'widths'=>[28,72]],
                    ['name'=>'Preços','title'=>'Shopping, preços verificados','headers'=>['Ordem','Tipo','Artigo','PVP regular €','PVP promoção €','Diferença €','Variação %','Leitura'],'rows'=>$priceRows,'types'=>['integer','auto','auto','number','number','number','number','auto'],'widths'=>[10,20,48,16,16,14,14,24]],
                ]);
                $attachments[] = $path;
                $files[] = ['format'=>'xlsx','path'=>$path,'filename'=>basename($path),'bytes'=>(int)@filesize($path),'source'=>'canonical_submission_snapshot'];
            } catch (\Throwable $error) {
                $errors[] = 'Excel: ' . sanitize_text_field($error->getMessage());
            }
        }

        if ($pdf) {
            try {
                $content = self::buildSubmissionPdf($detail);
                $path = self::writeTempNamedFile($base . '.pdf', $content);
                $attachments[] = $path;
                $files[] = ['format'=>'pdf','path'=>$path,'filename'=>basename($path),'bytes'=>(int)@filesize($path),'source'=>'canonical_submission_snapshot'];
            } catch (\Throwable $error) {
                $errors[] = 'PDF: ' . sanitize_text_field($error->getMessage());
            }
        }

        return ['attachments'=>$attachments, 'files'=>$files, 'errors'=>$errors, 'requested'=>$requested, 'source'=>'canonical_submission_snapshot'];
    }

    public static function cleanupAttachments(array $paths): void {
        foreach ($paths as $path) {
            $path = (string)$path;
            if ($path !== '' && is_file($path)) @unlink($path);
        }
    }

    private static function submissionFilenameBase(array $detail): string {
        $insignia = (string)($detail['insignia'] ?? 'Insignia');
        $store = (string)($detail['location_name'] ?? 'Loja');
        $week = (int)($detail['week_no'] ?? 0);
        $year = (int)($detail['year_no'] ?? 0);
        $raw = 'Shopping_' . $insignia . '_' . $store . ($week > 0 ? '_S' . $week : '') . ($year > 0 ? '_' . $year : '');
        $filename = sanitize_file_name($raw);
        $filename = str_replace(['-', ' '], '_', $filename);
        $filename = trim((string)preg_replace('/_+/', '_', $filename), '_');
        return $filename !== '' ? $filename : ('Shopping_' . max(1, (int)($detail['id'] ?? 0)));
    }

    private static function buildSubmissionPdf(array $detail): string {
        $prices = array_values((array)($detail['prices'] ?? []));
        $lines = [];
        $lines[] = 'RESUMO DA SUBMISSÃO';
        $lines[] = 'Submissão: #' . (int)($detail['id'] ?? 0);
        $lines[] = 'Data: ' . self::displayDateTime((string)($detail['submitted_at'] ?? ''));
        $lines[] = 'Cliente: ' . (string)($detail['client_name'] ?? '');
        $lines[] = 'Projeto: ' . (string)($detail['project_name'] ?? '');
        if ((string)($detail['title'] ?? '') !== '') $lines[] = 'Shopping / lote: ' . (string)$detail['title'];
        $lines[] = 'Período: ' . self::periodLabel((int)($detail['week_no'] ?? 0), (int)($detail['year_no'] ?? 0));
        $lines[] = 'Insígnia: ' . (string)($detail['insignia'] ?? '');
        $lines[] = 'Loja: ' . (string)($detail['location_name'] ?? '');
        if ((string)($detail['city'] ?? '') !== '') $lines[] = 'Cidade: ' . (string)$detail['city'];
        $lines[] = 'Merchandiser: ' . (string)($detail['merchandiser_name'] ?? '');
        if ((string)($detail['merchandiser_email'] ?? '') !== '') $lines[] = 'Email: ' . (string)$detail['merchandiser_email'];
        if ((string)($detail['price_note'] ?? '') !== '') $lines[] = 'Nota de preços: ' . (string)$detail['price_note'];
        $lines[] = 'Artigos verificados: ' . count($prices);
        $lines[] = '';
        $lines[] = 'PREÇOS VERIFICADOS';
        $position = 0;
        foreach ($prices as $price) {
            $position++;
            $regular = self::priceValue($price['regular'] ?? null);
            $promo = self::priceValue($price['promo'] ?? null);
            $analysis = self::priceAnalysis($regular, $promo);
            $lines[] = sprintf('%02d. %s', $position, (string)($price['name'] ?? 'Artigo'));
            if ((string)($price['type'] ?? '') !== '') $lines[] = '    Tipo: ' . (string)$price['type'];
            $lines[] = '    PVP regular: ' . self::priceLabel($regular);
            $lines[] = '    PVP promoção: ' . self::priceLabel($promo);
            if ($analysis['delta'] !== null) {
                $deltaLabel = ($analysis['delta'] > 0 ? '+' : '') . number_format((float)$analysis['delta'], 2, ',', '.') . ' EUR';
                if ($analysis['variation'] !== null) $deltaLabel .= ' | ' . ($analysis['variation'] > 0 ? '+' : '') . number_format((float)$analysis['variation'], 1, ',', '.') . '%';
                $lines[] = '    Diferença: ' . $deltaLabel;
            }
            $lines[] = '    Leitura: ' . $analysis['status'];
            $lines[] = '';
        }
        $notes = trim((string)($detail['notes'] ?? ''));
        if ($notes !== '') {
            $lines[] = 'OBSERVAÇÕES';
            $lines[] = $notes;
        }
        return self::buildTextPdf($lines, 'FieldFlow Shopping, verificação de preços');
    }

    private static function normaliseSubmissionDetail(array $detail, int $submissionId): array {
        $detail['id'] = max(1, (int)($detail['id'] ?? $submissionId));
        foreach (['submitted_at','client_name','project_name','title','insignia','location_name','city','merchandiser_name','merchandiser_email','price_note'] as $key) {
            $detail[$key] = sanitize_text_field((string)($detail[$key] ?? ''));
        }
        $detail['notes'] = sanitize_textarea_field((string)($detail['notes'] ?? ''));
        $detail['week_no'] = max(0, min(53, (int)($detail['week_no'] ?? 0)));
        $detail['year_no'] = max(0, min(9999, (int)($detail['year_no'] ?? 0)));
        $prices = [];
        foreach ((array)($detail['prices'] ?? []) as $price) {
            if (!is_array($price)) continue;
            $prices[] = [
                'key'=>sanitize_key((string)($price['key'] ?? '')),
                'type'=>sanitize_text_field((string)($price['type'] ?? '')),
                'name'=>sanitize_text_field((string)($price['name'] ?? '')),
                'regular'=>self::priceValue($price['regular'] ?? null),
                'promo'=>self::priceValue($price['promo'] ?? null),
            ];
        }
        $detail['prices'] = $prices;
        return $detail;
    }

    private static function priceValue($value): ?float {
        if ($value === null || $value === '') return null;
        if (is_string($value)) $value = str_replace(',', '.', trim($value));
        if (!is_numeric($value)) return null;
        $value = (float)$value;
        return is_finite($value) && $value >= 0 ? round($value, 2) : null;
    }

    private static function priceAnalysis(?float $regular, ?float $promo): array {
        if ($regular === null && $promo === null) return ['delta'=>null,'variation'=>null,'status'=>'Sem preço registado'];
        if ($regular === null) return ['delta'=>null,'variation'=>null,'status'=>'Sem PVP regular'];
        if ($promo === null) return ['delta'=>null,'variation'=>null,'status'=>'Sem PVP promoção'];
        $delta = round($promo - $regular, 2);
        $variation = $regular > 0 ? round(($delta / $regular) * 100, 1) : null;
        if (abs($delta) < 0.005) $status = 'Promo igual ao regular';
        elseif ($delta < 0) $status = 'Promo abaixo do regular';
        else $status = 'Promo acima do regular';
        return ['delta'=>$delta,'variation'=>$variation,'status'=>$status];
    }

    private static function priceLabel(?float $value): string {
        return $value === null ? 'Sem registo' : number_format($value, 2, ',', '.') . ' EUR';
    }

    private static function periodLabel(int $week, int $year): string {
        if ($week > 0 && $year > 0) return 'Semana ' . $week . ', ' . $year;
        if ($week > 0) return 'Semana ' . $week;
        return $year > 0 ? (string)$year : 'Não indicado';
    }

    private static function displayDateTime(string $value): string {
        $timestamp = $value !== '' ? strtotime($value) : false;
        return $timestamp ? wp_date('d/m/Y H:i', $timestamp) : self::plain($value);
    }

    private static function writeTempNamedFile(string $filename, string $content): string {
        $base = wp_tempnam($filename);
        if (!$base) throw new \RuntimeException('Não foi possível preparar o ficheiro temporário.');
        $dir = dirname($base);
        @unlink($base);
        $target = trailingslashit($dir) . wp_unique_filename($dir, sanitize_file_name($filename));
        if (@file_put_contents($target, $content, LOCK_EX) === false) throw new \RuntimeException('Não foi possível gravar o ficheiro temporário.');
        clearstatcache(true, $target);
        if (!is_file($target) || (int)@filesize($target) < 1) {
            @unlink($target);
            throw new \RuntimeException('O ficheiro temporário ficou vazio.');
        }
        return $target;
    }

    private static function streamCsv(string $filename, array $dataset): void {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Data','Cliente','Projeto','Semana','Ano','Insígnia','Loja','Merchandiser','Categoria','Artigo','PVP regular','PVP promoção','Observações'], ';');
        foreach ((array)($dataset['prices'] ?? []) as $row) fputcsv($out, array_map([self::class, 'csvValue'], (array)$row), ';');
        fclose($out);
        exit;
    }

    private static function streamXlsx(string $filename, array $dataset): void {
        $summaryHeaders = ['ID','Data','Cliente','Projeto','Lote','Semana','Ano','Insígnia','Loja','Cidade','Merchandiser','Email merchandiser','Campos preenchidos','Estado do email','Destinatários','Observações'];
        $priceHeaders = ['ID','Data','Cliente','Projeto','Semana','Ano','Insígnia','Loja','Merchandiser','Categoria','Artigo','PVP regular','PVP promoção','Observações'];
        $sheets = [
            ['name'=>'Resumo','title'=>'Shopping, resumo das submissões','headers'=>$summaryHeaders,'rows'=>(array)($dataset['submissions'] ?? []),'types'=>['integer','auto','auto','auto','auto','integer','integer','auto','auto','auto','auto','auto','integer','auto','auto','auto'],'widths'=>[10,20,24,26,24,10,10,18,28,18,24,28,18,18,30,42]],
            ['name'=>'Preços','title'=>'Shopping, detalhe de preços','headers'=>$priceHeaders,'rows'=>(array)($dataset['prices'] ?? []),'types'=>['integer','auto','auto','auto','integer','integer','auto','auto','auto','auto','auto','number','number','auto'],'widths'=>[10,20,24,26,10,10,18,28,24,20,42,14,14,42]],
        ];
        $path = self::createXlsx($filename, $sheets);
        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        @unlink($path);
        exit;
    }

    private static function streamPdf(string $filename, array $dataset): void {
        $lines = [];
        $summary = (array)($dataset['summary'] ?? []);
        $lines[] = 'Submissões: ' . (int)($summary['submissions'] ?? 0) . ' | Lojas: ' . (int)($summary['stores'] ?? 0) . ' | Merchandisers: ' . (int)($summary['merchandisers'] ?? 0) . ' | Emails enviados: ' . (int)($summary['emails_sent'] ?? 0);
        $lines[] = '';
        foreach ((array)($dataset['submissions'] ?? []) as $submission) {
            $lines[] = '#' . (int)($submission[0] ?? 0) . ' | ' . (string)($submission[1] ?? '') . ' | ' . (string)($submission[8] ?? '') . ' | ' . (string)($submission[10] ?? '');
            $lines[] = 'Cliente: ' . (string)($submission[2] ?? '') . ' | Projeto: ' . (string)($submission[3] ?? '') . ' | Semana ' . (string)($submission[5] ?? '') . '/' . (string)($submission[6] ?? '');
            $lines[] = 'Email: ' . (string)($submission[13] ?? '') . ' | Destinatários: ' . (string)($submission[14] ?? '');
            if ((string)($submission[15] ?? '') !== '') $lines[] = 'Observações: ' . (string)$submission[15];
            foreach ((array)($dataset['prices'] ?? []) as $price) {
                if ((int)($price[0] ?? 0) !== (int)($submission[0] ?? 0)) continue;
                $regular = $price[11] === '' ? 'n.d.' : number_format((float)$price[11], 2, ',', '.') . ' EUR';
                $promo = $price[12] === '' ? 'n.d.' : number_format((float)$price[12], 2, ',', '.') . ' EUR';
                $lines[] = '  ' . (string)($price[9] ?? '') . ' | ' . (string)($price[10] ?? '') . ' | Regular ' . $regular . ' | Promo ' . $promo;
            }
            $lines[] = str_repeat('-', 110);
        }
        $pdf = self::buildTextPdf($lines, 'FieldFlow Shopping, resultados');
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    private static function createXlsx(string $filename, array $sheets): string {
        $base = wp_tempnam($filename);
        if (!$base) throw new \RuntimeException('Não foi possível preparar o ficheiro Excel.');
        $archive = $base . '.zip';
        $target = trailingslashit(dirname($base)) . wp_unique_filename(dirname($base), sanitize_file_name($filename));
        @unlink($archive); @unlink($target); @unlink($base);
        $zip = null;
        $phar = null;
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('Não foi possível criar o ficheiro Excel.');
            $add = static function(string $path, string $content) use ($zip): void {
                if (!$zip->addFromString($path, $content)) throw new \RuntimeException('Falha ao escrever o pacote Excel.');
                if (method_exists($zip, 'setCompressionName')) $zip->setCompressionName($path, \ZipArchive::CM_DEFLATE);
            };
        } elseif (class_exists('PharData')) {
            try {
                $phar = new \PharData($archive, 0, null, \Phar::ZIP);
            } catch (\Throwable $error) {
                throw new \RuntimeException('Não foi possível criar o ficheiro Excel.');
            }
            $add = static function(string $path, string $content) use ($phar): void {
                $phar->addFromString($path, $content);
            };
        } else {
            throw new \RuntimeException('O servidor não suporta a criação de ficheiros XLSX.');
        }
        $sheetNodes = [];
        $relations = '';
        $overrides = '';
        $used = [];
        foreach (array_values($sheets) as $index => $sheet) {
            $id = $index + 1;
            $add('xl/worksheets/sheet' . $id . '.xml', self::sheetXml($sheet));
            $name = self::uniqueSheetName((string)($sheet['name'] ?? ('Folha ' . $id)), $used);
            $sheetNodes[] = '<sheet name="' . self::xml($name) . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
            $relations .= '<Relationship Id="rId' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $id . '.xml"/>';
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $id . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $styleId = count($sheets) + 1;
        $relations .= '<Relationship Id="rId' . $styleId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $add('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>' . $overrides . '</Types>');
        $add('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>');
        $add('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . implode('', $sheetNodes) . '</sheets><calcPr calcId="191029"/></workbook>');
        $add('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relations . '</Relationships>');
        $add('xl/styles.xml', self::stylesXml());
        $add('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>FieldFlow Shopping</dc:title><dc:creator>The Wild Theory</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created></cp:coreProperties>');
        $add('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Company>The Wild Theory</Company><Application>FieldFlow</Application><AppVersion>2.2</AppVersion></Properties>');
        if ($zip instanceof \ZipArchive) $zip->close();
        if ($phar instanceof \PharData) unset($phar);
        if (!@rename($archive, $target)) { @copy($archive, $target); @unlink($archive); }
        if (!is_file($target) || filesize($target) < 1000) throw new \RuntimeException('O ficheiro Excel ficou incompleto.');
        if (class_exists('ZipArchive')) {
            $check = new \ZipArchive();
            if ($check->open($target) !== true || $check->locateName('xl/workbook.xml') === false || $check->locateName('xl/worksheets/sheet1.xml') === false) {
                if ($check instanceof \ZipArchive) $check->close();
                @unlink($target);
                throw new \RuntimeException('A validação do ficheiro Excel falhou.');
            }
            $check->close();
        } elseif (class_exists('PharData')) {
            try {
                $check = new \PharData($target, 0, null, \Phar::ZIP);
                if (!isset($check['xl/workbook.xml']) || !isset($check['xl/worksheets/sheet1.xml'])) throw new \RuntimeException('Estrutura incompleta.');
                unset($check);
            } catch (\Throwable $error) {
                @unlink($target);
                throw new \RuntimeException('A validação do ficheiro Excel falhou.');
            }
        }
        return $target;
    }

    private static function sheetXml(array $sheet): string {
        $headers = array_values((array)($sheet['headers'] ?? []));
        $rows = array_values((array)($sheet['rows'] ?? []));
        $types = array_values((array)($sheet['types'] ?? []));
        $widths = array_values((array)($sheet['widths'] ?? []));
        $cols = max(1, count($headers));
        $last = self::columnName($cols);
        $lastRow = max(4, count($rows) + 4);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:' . $last . $lastRow . '"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols>';
        for ($i=0; $i<$cols; $i++) $xml .= '<col min="' . ($i+1) . '" max="' . ($i+1) . '" width="' . max(8, min(60, (float)($widths[$i] ?? 18))) . '" customWidth="1"/>';
        $xml .= '</cols><sheetData>';
        $rowNo = 1;
        $xml .= self::rowXml($rowNo++, [(string)($sheet['title'] ?? $sheet['name'] ?? 'Shopping')], ['title'], $cols);
        $xml .= self::rowXml($rowNo++, ['Relatório gerado pelo FieldFlow'], ['subtitle'], $cols);
        $xml .= self::rowXml($rowNo++, ['Gerado em ' . wp_date('d/m/Y H:i')], ['subtitle'], $cols);
        $xml .= self::rowXml($rowNo++, $headers, array_fill(0, $cols, 'header'), $cols);
        foreach ($rows as $row) $xml .= self::rowXml($rowNo++, array_values((array)$row), $types, $cols);
        $xml .= '</sheetData><autoFilter ref="A4:' . $last . max(4, $rowNo - 1) . '"/>';
        if ($cols > 1) $xml .= '<mergeCells count="3"><mergeCell ref="A1:' . $last . '1"/><mergeCell ref="A2:' . $last . '2"/><mergeCell ref="A3:' . $last . '3"/></mergeCells>';
        return $xml . '<pageMargins left="0.35" right="0.35" top="0.55" bottom="0.55" header="0.2" footer="0.2"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
    }

    private static function rowXml(int $rowNo, array $values, array $types, int $columnCount): string {
        $xml = '<row r="' . $rowNo . '">';
        for ($i=0; $i<$columnCount; $i++) {
            if ($rowNo <= 3 && $i > 0) continue;
            $ref = self::columnName($i + 1) . $rowNo;
            $value = $values[$i] ?? '';
            $type = $types[$i] ?? 'auto';
            if ($rowNo <= 3) {
                $style = $rowNo === 1 ? 1 : 2;
                $xml .= '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . self::xml((string)$value) . '</t></is></c>';
            } elseif ($type === 'header') {
                $xml .= '<c r="' . $ref . '" t="inlineStr" s="3"><is><t xml:space="preserve">' . self::xml((string)$value) . '</t></is></c>';
            } elseif (($type === 'number' || $type === 'integer') && $value !== '' && is_numeric($value)) {
                $xml .= '<c r="' . $ref . '" s="' . ($type === 'integer' ? '5' : '4') . '"><v>' . ($type === 'integer' ? (int)$value : number_format((float)$value, 2, '.', '')) . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" t="inlineStr" s="4"><is><t xml:space="preserve">' . self::xml((string)$value) . '</t></is></c>';
            }
        }
        return $xml . '</row>';
    }

    private static function stylesXml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="4"><font><sz val="10"/><name val="Aptos"/></font><font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Aptos Display"/></font><font><sz val="10"/><color rgb="FF64748B"/><name val="Aptos"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right><top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="2" fillId="0" borderId="0" applyFont="1"/><xf numFmtId="0" fontId="3" fillId="3" borderId="1" applyFont="1" applyFill="1" applyBorder="1"><alignment wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1"><alignment wrapText="1" vertical="top"/></xf><xf numFmtId="1" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function buildTextPdf(array $lines, string $title): string {
        $pageW = 841.89; $pageH = 595.28; $marginX = 38; $startY = 545; $lineH = 12; $maxChars = 130;
        $pages = [[]]; $y = $startY;
        foreach ($lines as $raw) {
            $raw = self::plain((string)$raw, true);
            $chunks = $raw === '' ? [''] : preg_split('/\n/', wordwrap($raw, $maxChars, "\n", true));
            foreach ($chunks as $line) {
                if ($y < 36) { $pages[] = []; $y = $startY; }
                $pages[count($pages)-1][] = ['text'=>(string)$line,'x'=>$marginX,'y'=>$y];
                $y -= $lineH;
            }
        }
        $objects = [];
        $add = static function(string $content) use (&$objects): int { $objects[] = $content; return count($objects); };
        $f1 = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $f2 = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        $contentIds = []; $pageIds = []; $hint = 9999;
        foreach ($pages as $i=>$pageLines) {
            $stream = "BT\n/F2 15 Tf\n{$marginX} 570 Td (" . self::pdfEscape($title) . ") Tj\nET\nBT\n/F1 8 Tf\n";
            foreach ($pageLines as $line) $stream .= '1 0 0 1 ' . $line['x'] . ' ' . $line['y'] . ' Tm (' . self::pdfEscape($line['text']) . ") Tj\n";
            $stream .= "ET\n";
            $contentIds[$i] = $add('<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "endstream");
        }
        foreach ($pages as $i=>$_) $pageIds[$i] = $add('<< /Type /Page /Parent ' . $hint . ' 0 R /MediaBox [0 0 ' . $pageW . ' ' . $pageH . '] /Resources << /Font << /F1 ' . $f1 . ' 0 R /F2 ' . $f2 . ' 0 R >> >> /Contents ' . $contentIds[$i] . ' 0 R >>');
        $kids = implode(' ', array_map(static fn($id)=>$id . ' 0 R', $pageIds));
        $pagesRoot = $add('<< /Type /Pages /Kids [ ' . $kids . ' ] /Count ' . count($pageIds) . ' >>');
        foreach ($objects as &$object) $object = str_replace($hint . ' 0 R', $pagesRoot . ' 0 R', $object);
        unset($object);
        $catalog = $add('<< /Type /Catalog /Pages ' . $pagesRoot . ' 0 R >>');
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $offsets = [0];
        foreach ($objects as $idx=>$object) { $offsets[] = strlen($pdf); $pdf .= ($idx+1) . " 0 obj\n" . $object . "\nendobj\n"; }
        $xref = strlen($pdf); $pdf .= "xref\n0 " . (count($objects)+1) . "\n0000000000 65535 f \n";
        for ($i=1; $i<=count($objects); $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        return $pdf . 'trailer << /Size ' . (count($objects)+1) . ' /Root ' . $catalog . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    }

    private static function uniqueSheetName(string $name, array &$used): string {
        $base = preg_replace('~[\\/?*\[\]:]+~u', ' ', trim($name)) ?: 'Dados';
        $base = function_exists('mb_substr') ? mb_substr($base, 0, 31, 'UTF-8') : substr($base, 0, 31);
        $candidate = $base; $n = 2;
        while (isset($used[strtolower($candidate)])) {
            $suffix = ' ' . $n++;
            $limit = 31 - strlen($suffix);
            $candidate = rtrim(function_exists('mb_substr') ? mb_substr($base, 0, $limit, 'UTF-8') : substr($base, 0, $limit)) . $suffix;
        }
        $used[strtolower($candidate)] = true;
        return $candidate;
    }

    private static function columnName(int $index): string {
        $name = '';
        while ($index > 0) { $index--; $name = chr(65 + ($index % 26)) . $name; $index = intdiv($index, 26); }
        return $name;
    }

    private static function xml(string $value): string {
        return htmlspecialchars(self::plain($value), ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function plain(string $value, bool $forPdf = false): string {
        $value = html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\r\n\t]+/u', ' ', $value) ?: $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?: $value;
        $value = trim(strtr($value, ['–'=>'-','—'=>'-','−'=>'-','•'=>'-','·'=>'-','“'=>'"','”'=>'"','’'=>"'",'…'=>'...']));
        if (function_exists('mb_substr') && mb_strlen($value, 'UTF-8') > 32767) $value = mb_substr($value, 0, 32764, 'UTF-8') . '...';
        if ($forPdf) {
            if (function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
                if ($converted !== false) return $converted;
            }
            if (function_exists('iconv')) {
                $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
                if ($converted !== false) return $converted;
            }
        }
        return $value;
    }

    private static function pdfEscape(string $value): string {
        return str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], self::plain($value, true));
    }

    private static function csvValue($value): string {
        if ($value === null) return '';
        if (is_float($value)) return number_format($value, 2, ',', '');
        return self::plain((string)$value);
    }
}
