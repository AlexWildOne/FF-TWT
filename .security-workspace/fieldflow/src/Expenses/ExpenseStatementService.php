<?php
namespace RoutesPro\Expenses;

use RoutesPro\Admin\Branding;
use RoutesPro\Support\Permissions;

if (!defined('ABSPATH')) exit;

/**
 * Builds a signed-ready premium expense statement from the canonical expense table.
 *
 * Contract 9CF: FieldFlow expense data stays the single source of truth. The statement
 * only projects the selected user + filters into a printable PDF, with no duplicate
 * storage and no FlutterFlow dependency.
 */
class ExpenseStatementService {
    public const CONTRACT = 'fieldflow-expense-statement-pdf-receipt-authority-v1';

    public static function normalizeFilters(array $input): array {
        $dateFrom = self::validDate((string)($input['date_from'] ?? ''));
        $dateTo = self::validDate((string)($input['date_to'] ?? ''));
        $month = self::validMonth((string)($input['month'] ?? ''));

        // Explicit De/Ate is the strongest authority. If neither date exists,
        // month becomes a convenient monthly shortcut. With no period selected,
        // keep the statement unbounded so it mirrors the active Expenses view.
        if ($dateFrom !== '' || $dateTo !== '') {
            $month = '';
            if ($dateFrom === '') $dateFrom = $dateTo;
            if ($dateTo === '') $dateTo = $dateFrom;
        } elseif ($month !== '') {
            [$dateFrom, $dateTo] = self::monthBounds($month);
        }

        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $category = sanitize_key((string)($input['category'] ?? ''));
        if ($category !== '' && !isset(ExpenseService::categories()[$category])) $category = '';
        $status = sanitize_key((string)($input['status'] ?? ''));
        if ($status !== '' && !isset(ExpenseService::statuses()[$status])) $status = '';

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'month' => $month,
            'category' => $category,
            'status' => $status,
            'search' => sanitize_text_field((string)($input['search'] ?? '')),
        ];
    }

    public static function statementData(int $userId, array $input = [], int $clientId = 0, int $projectId = 0): array {
        global $wpdb;
        $userId = absint($userId);
        if (!$userId) throw new \RuntimeException('Seleciona um operativo para gerar a folha de despesas.');

        $filters = self::normalizeFilters($input);
        [$clientId, $projectId] = Permissions::resolve_front_context(absint($clientId), absint($projectId), $userId);

        [$where, $args] = ExpensesController::buildWhere($filters, $userId, true);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = 'SELECT * FROM ' . ExpenseService::table() . $whereSql . ' ORDER BY expense_date ASC,id ASC';
        $rows = $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);

        $items = [];
        $daily = [];
        $categoryTotals = [];
        $statusCounts = ['approved' => 0, 'pending' => 0, 'paid' => 0, 'rejected' => 0];
        $total = 0.0;
        foreach ($rows as $row) {
            $item = ExpenseService::publicRow($row);
            $items[] = $item;
            $date = (string)$item['expense_date'];
            if (!isset($daily[$date])) $daily[$date] = ['date' => $date, 'total' => 0.0, 'count' => 0, 'items' => []];
            $daily[$date]['items'][] = $item;
            $daily[$date]['total'] += (float)$item['amount_total'];
            $daily[$date]['count']++;
            $total += (float)$item['amount_total'];
            $category = (string)$item['category'];
            $categoryTotals[$category] = ($categoryTotals[$category] ?? 0.0) + (float)$item['amount_total'];
            $status = (string)$item['status'];
            if (isset($statusCounts[$status])) $statusCounts[$status]++;
        }

        $user = get_userdata($userId);
        if (!$user) throw new \RuntimeException('O utilizador selecionado já não existe.');

        $clientName = '';
        $projectName = '';
        if ($clientId > 0) {
            $clientName = (string)$wpdb->get_var($wpdb->prepare('SELECT name FROM ' . $wpdb->prefix . 'routespro_clients WHERE id=%d', $clientId));
        }
        if ($projectId > 0) {
            $project = $wpdb->get_row($wpdb->prepare('SELECT client_id,name FROM ' . $wpdb->prefix . 'routespro_projects WHERE id=%d', $projectId), ARRAY_A);
            if ($project) {
                $projectName = (string)($project['name'] ?? '');
                if ($clientId <= 0 && (int)($project['client_id'] ?? 0) > 0) {
                    $clientId = (int)$project['client_id'];
                    $clientName = (string)$wpdb->get_var($wpdb->prepare('SELECT name FROM ' . $wpdb->prefix . 'routespro_clients WHERE id=%d', $clientId));
                }
            }
        }

        ksort($daily);
        arsort($categoryTotals);
        $period = self::periodLabel($filters['date_from'], $filters['date_to']);
        $documentId = self::documentId($userId, $filters['date_from'], $filters['date_to']);

        return [
            'contract' => self::CONTRACT,
            'user_id' => $userId,
            'user_name' => (string)$user->display_name,
            'user_email' => (string)$user->user_email,
            'client_id' => $clientId,
            'project_id' => $projectId,
            'client_name' => $clientName,
            'project_name' => $projectName,
            'filters' => $filters,
            'period_label' => $period,
            'document_id' => $documentId,
            'items' => $items,
            'daily' => array_values($daily),
            'category_totals' => $categoryTotals,
            'status_counts' => $statusCounts,
            'count' => count($items),
            'days' => count($daily),
            'total' => round($total, 2),
        ];
    }

    public static function buildPdf(array $data): string {
        $pageW = 595.28;
        $pageH = 841.89;
        $left = 36.0;
        $right = 36.0;
        $bottom = 44.0;
        $contentW = $pageW - $left - $right;

        $objects = [];
        $add = static function (string $content) use (&$objects): int {
            $objects[] = $content;
            return count($objects);
        };
        $fontRegular = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $fontBold = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');

        $logoObjects = [];
        $tmpLogos = [];
        foreach (Branding::get_header_logos(['client_id' => (int)($data['client_id'] ?? 0), 'project_id' => (int)($data['project_id'] ?? 0)]) as $logo) {
            $prepared = Branding::maybe_prepare_pdf_logo_file((string)($logo['url'] ?? ''));
            if ($prepared && is_readable($prepared)) {
                $image = self::imageObject($prepared, $add);
                if ($image) $logoObjects[] = $image;
                if (strpos($prepared, sys_get_temp_dir()) === 0) $tmpLogos[] = $prepared;
            }
        }
        if (!$logoObjects && is_readable(ROUTESPRO_PATH . 'assets/logo-twt.jpg')) {
            $image = self::imageObject(ROUTESPRO_PATH . 'assets/logo-twt.jpg', $add);
            if ($image) $logoObjects[] = $image;
        }

        $signatureObject = null;
        $signaturePath = (string)($data['signature_image_path'] ?? '');
        if ($signaturePath !== '' && is_readable($signaturePath)) {
            $signatureObject = self::imageObject($signaturePath, $add);
        }

        $pages = [];
        $page = [];
        $y = 0.0;
        $pageIndex = -1;
        $newPage = static function () use (&$pages, &$page, &$y, &$pageIndex, $pageH): void {
            if ($pageIndex >= 0) $pages[] = $page;
            $pageIndex++;
            $page = [];
            $y = $pageH - 176.0;
        };
        $newPage();

        $drawTableHeader = static function () use (&$page, &$y, $left, $contentW): void {
            $page[] = ['kind' => 'table_header', 'y' => $y];
            $y -= 22.0;
        };

        $drawTableHeader();
        foreach ((array)($data['daily'] ?? []) as $day) {
            $items = (array)($day['items'] ?? []);
            $needed = 24.0 + max(1, count($items)) * 30.0 + 10.0;
            if (($y - $needed) < $bottom) {
                $newPage();
                $drawTableHeader();
            }
            $page[] = [
                'kind' => 'day',
                'y' => $y,
                'date' => self::humanDate((string)($day['date'] ?? '')),
                'count' => (int)($day['count'] ?? 0),
                'total' => self::moneyLabel((float)($day['total'] ?? 0)),
            ];
            $y -= 22.0;

            foreach ($items as $item) {
                if (($y - 30.0) < $bottom) {
                    $newPage();
                    $drawTableHeader();
                }
                $page[] = ['kind' => 'row', 'y' => $y, 'item' => $item];
                $y -= 30.0;
            }
            $y -= 7.0;
        }

        if (!$data['items']) {
            $page[] = ['kind' => 'empty', 'y' => $y, 'text' => 'Não existem despesas para o período e filtros selecionados.'];
            $y -= 36.0;
        }

        // Signature/receipt declaration always stays together. If the table used the
        // bottom of the last page, move the complete legal-signature block to a new page.
        $signatureHeight = 150.0;
        if (($y - $signatureHeight) < $bottom) {
            $newPage();
        }
        $page[] = ['kind' => 'signature', 'y' => $y];
        $pages[] = $page;

        $contentIds = [];
        $pageIds = [];
        foreach ($pages as $idx => $instructions) {
            $stream = self::pageHeaderStream($data, $idx + 1, count($pages), $logoObjects, $pageW, $pageH, $left, $contentW);
            foreach ($instructions as $instruction) {
                $kind = (string)($instruction['kind'] ?? '');
                $iy = (float)($instruction['y'] ?? 0);
                if ($kind === 'table_header') {
                    $stream .= self::tableHeaderStream($iy, $left, $contentW);
                } elseif ($kind === 'day') {
                    $stream .= self::dayHeaderStream($instruction, $left, $contentW);
                } elseif ($kind === 'row') {
                    $stream .= self::rowStream((array)($instruction['item'] ?? []), $iy, $left, $contentW);
                } elseif ($kind === 'empty') {
                    $stream .= "0.38 0.43 0.52 rg\n";
                    $stream .= 'BT /F1 10 Tf ' . self::num($left + 8) . ' ' . self::num($iy) . ' Td (' . self::pdfEscape((string)$instruction['text']) . ") Tj ET\n";
                } elseif ($kind === 'signature') {
                    $stream .= self::signatureStream($data, $iy, $left, $contentW, $signatureObject);
                }
            }
            $stream .= "0.85 0.88 0.93 RG 36 28 523 0.7 re f\n";
            $stream .= "0.38 0.43 0.52 rg\n";
            $stream .= 'BT /F1 7.5 Tf 36 16 Td (' . self::pdfEscape('FieldFlow | The Wild Theory | ' . (string)($data['document_id'] ?? '')) . ") Tj ET\n";
            $stream .= 'BT /F1 7.5 Tf 510 16 Td (' . self::pdfEscape('Página ' . ($idx + 1) . '/' . count($pages)) . ") Tj ET\n";
            $contentIds[] = $add("<< /Length " . strlen($stream) . ">>\nstream\n" . $stream . "endstream");
            $pageIds[] = 0;
        }

        $pagesRootHint = count($objects) + count($pages) + 1;
        foreach ($pages as $i => $_) {
            $resources = '<< /Font << /F1 ' . $fontRegular . ' 0 R /F2 ' . $fontBold . ' 0 R >>';
            if ($logoObjects || $signatureObject) {
                $resources .= ' /XObject << ';
                foreach ($logoObjects as $idx => $image) $resources .= '/Im' . ($idx + 1) . ' ' . $image['id'] . ' 0 R ';
                if ($signatureObject) $resources .= '/Sig1 ' . $signatureObject['id'] . ' 0 R ';
                $resources .= '>>';
            }
            $resources .= ' >>';
            $pageIds[$i] = $add('<< /Type /Page /Parent ' . $pagesRootHint . ' 0 R /MediaBox [0 0 ' . self::num($pageW) . ' ' . self::num($pageH) . '] /Resources ' . $resources . ' /Contents ' . $contentIds[$i] . ' 0 R >>');
        }
        $kids = implode(' ', array_map(static fn($id) => $id . ' 0 R', $pageIds));
        $pagesId = $add('<< /Type /Pages /Kids [ ' . $kids . ' ] /Count ' . count($pageIds) . ' >>');
        $catalogId = $add('<< /Type /Catalog /Pages ' . $pagesId . ' 0 R >>');

        $pdf = "%PDF-1.4\n%âãÏÓ\n";
        $offsets = [0];
        foreach ($objects as $idx => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($idx + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root ' . $catalogId . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

        foreach ($tmpLogos as $tmp) if (is_string($tmp) && file_exists($tmp)) @unlink($tmp);
        return $pdf;
    }

    public static function filename(array $data): string {
        $name = sanitize_title((string)($data['user_name'] ?? 'operativo')) ?: 'operativo';
        $from = str_replace('-', '', (string)($data['filters']['date_from'] ?? ''));
        $to = str_replace('-', '', (string)($data['filters']['date_to'] ?? ''));
        $period = $from !== '' ? $from . ($to !== '' && $to !== $from ? '-' . $to : '') : 'todos-periodos';
        return sanitize_file_name('fieldflow-folha-despesas-' . $name . '-' . $period . '.pdf');
    }

    public static function signedFilename(array $data): string {
        $base = self::filename($data);
        return sanitize_file_name(preg_replace('/\.pdf$/i', '', $base) . '-assinada.pdf');
    }

    private static function pageHeaderStream(array $data, int $page, int $pages, array $logos, float $pageW, float $pageH, float $left, float $contentW): string {
        $stream = '';
        $stream .= "0.965 0.973 0.984 rg 0 0 " . self::num($pageW) . ' ' . self::num($pageH) . " re f\n";
        $stream .= "1 1 1 rg 24 676 547 140 re f\n0.86 0.89 0.94 RG 24 676 547 140 re S\n";
        $stream .= "0.949 0.384 0.180 rg 24 676 7 140 re f\n";

        $logoX = 42.0;
        foreach ($logos as $idx => $logo) {
            $drawH = 27.0;
            $ratio = (float)$logo['w'] / max(1, (float)$logo['h']);
            $drawW = min(98.0, max(50.0, $ratio * $drawH));
            $boxW = $drawW + 12.0;
            $stream .= '1 1 1 rg ' . self::num($logoX) . ' 775 ' . self::num($boxW) . " 32 re f\n";
            $stream .= '0.88 0.90 0.94 RG ' . self::num($logoX) . ' 775 ' . self::num($boxW) . " 32 re S\n";
            $stream .= 'q ' . self::num($drawW) . ' 0 0 ' . self::num($drawH) . ' ' . self::num($logoX + 6) . ' 778 cm /Im' . ($idx + 1) . " Do Q\n";
            $logoX += $boxW + 8.0;
        }

        $stream .= "0.13 0.17 0.25 rg\n";
        $stream .= 'BT /F2 20 Tf 42 748 Td (' . self::pdfEscape('Folha de Despesas') . ") Tj ET\n";
        $subtitle = trim((string)($data['client_name'] ?? '') . ((string)($data['project_name'] ?? '') !== '' ? ' | ' . (string)$data['project_name'] : ''));
        if ($subtitle === '') $subtitle = 'The Wild Theory | FieldFlow';
        $stream .= "0.40 0.45 0.54 rg\n";
        $stream .= 'BT /F1 9.5 Tf 42 732 Td (' . self::pdfEscape($subtitle) . ") Tj ET\n";
        $stream .= 'BT /F1 8 Tf 42 716 Td (' . self::pdfEscape('Documento ' . (string)($data['document_id'] ?? '') . ' | Gerado em ' . wp_date('d/m/Y H:i')) . ") Tj ET\n";

        $cardY = 679.0;
        $cards = [
            ['Operativo', (string)($data['user_name'] ?? '')],
            ['Período', (string)($data['period_label'] ?? '')],
            ['Total despesas', self::moneyLabel((float)($data['total'] ?? 0))],
            ['Registos / dias', (int)($data['count'] ?? 0) . ' / ' . (int)($data['days'] ?? 0)],
        ];
        $cardW = ($contentW - 18.0) / 4.0;
        foreach ($cards as $i => $card) {
            $x = $left + $i * ($cardW + 6.0);
            $stream .= '0.985 0.988 0.996 rg ' . self::num($x) . ' ' . self::num($cardY) . ' ' . self::num($cardW) . " 30 re f\n";
            $stream .= '0.88 0.90 0.94 RG ' . self::num($x) . ' ' . self::num($cardY) . ' ' . self::num($cardW) . " 30 re S\n";
            $stream .= "0.42 0.47 0.56 rg\nBT /F1 7 Tf " . self::num($x + 7) . ' ' . self::num($cardY + 19) . ' Td (' . self::pdfEscape($card[0]) . ") Tj ET\n";
            $stream .= "0.13 0.17 0.25 rg\nBT /F2 8.5 Tf " . self::num($x + 7) . ' ' . self::num($cardY + 7) . ' Td (' . self::pdfEscape(self::trimText((string)$card[1], 27)) . ") Tj ET\n";
        }
        return $stream;
    }

    private static function tableHeaderStream(float $y, float $left, float $contentW): string {
        $stream = '0.13 0.17 0.25 rg ' . self::num($left) . ' ' . self::num($y - 3) . ' ' . self::num($contentW) . " 18 re f\n";
        $cols = [
            [$left + 7, 'Data'],
            [$left + 70, 'Categoria'],
            [$left + 171, 'Descrição / fornecedor'],
            [$left + 408, 'Estado'],
            [$left + 476, 'Valor'],
        ];
        foreach ($cols as [$x, $label]) {
            $stream .= "1 1 1 rg\nBT /F2 7.5 Tf " . self::num($x) . ' ' . self::num($y + 3) . ' Td (' . self::pdfEscape($label) . ") Tj ET\n";
        }
        return $stream;
    }

    private static function dayHeaderStream(array $instruction, float $left, float $contentW): string {
        $y = (float)$instruction['y'];
        $stream = '0.94 0.96 0.99 rg ' . self::num($left) . ' ' . self::num($y - 2) . ' ' . self::num($contentW) . " 18 re f\n";
        $stream .= '0.82 0.86 0.92 RG ' . self::num($left) . ' ' . self::num($y - 2) . ' ' . self::num($contentW) . " 18 re S\n";
        $leftText = (string)$instruction['date'] . ' | ' . (int)$instruction['count'] . ((int)$instruction['count'] === 1 ? ' despesa' : ' despesas');
        $stream .= "0.16 0.21 0.30 rg\nBT /F2 8.5 Tf " . self::num($left + 7) . ' ' . self::num($y + 3) . ' Td (' . self::pdfEscape($leftText) . ") Tj ET\n";
        $stream .= 'BT /F2 8.5 Tf ' . self::num($left + $contentW - 72) . ' ' . self::num($y + 3) . ' Td (' . self::pdfEscape((string)$instruction['total']) . ") Tj ET\n";
        return $stream;
    }

    private static function rowStream(array $item, float $y, float $left, float $contentW): string {
        $stream = '1 1 1 rg ' . self::num($left) . ' ' . self::num($y - 8) . ' ' . self::num($contentW) . " 27 re f\n";
        $stream .= '0.90 0.92 0.95 RG ' . self::num($left) . ' ' . self::num($y - 8) . ' ' . self::num($contentW) . " 27 re S\n";
        $date = self::humanDateShort((string)($item['expense_date'] ?? ''));
        $category = self::trimText((string)($item['category_label'] ?? ''), 17);
        $merchant = trim((string)($item['merchant_name'] ?? ''));
        $description = trim((string)($item['description'] ?? ''));
        $label = $merchant !== '' ? $merchant : ($description !== '' ? $description : 'Despesa');
        if ($merchant !== '' && $description !== '') $label .= ' | ' . $description;
        $label = self::trimText($label, 48);
        $document = trim((string)($item['document_no'] ?? ''));
        if ($document !== '') $label = self::trimText($label . ' | Doc. ' . $document, 58);
        $status = self::trimText((string)($item['status_label'] ?? ''), 12);
        $amount = self::moneyLabel((float)($item['amount_total'] ?? 0));
        $yText = $y + 3;
        $stream .= "0.20 0.24 0.31 rg\n";
        $stream .= 'BT /F1 7.5 Tf ' . self::num($left + 7) . ' ' . self::num($yText) . ' Td (' . self::pdfEscape($date) . ") Tj ET\n";
        $stream .= 'BT /F1 7.5 Tf ' . self::num($left + 70) . ' ' . self::num($yText) . ' Td (' . self::pdfEscape($category) . ") Tj ET\n";
        $stream .= 'BT /F1 7.5 Tf ' . self::num($left + 171) . ' ' . self::num($yText) . ' Td (' . self::pdfEscape($label) . ") Tj ET\n";
        $stream .= 'BT /F1 7.5 Tf ' . self::num($left + 408) . ' ' . self::num($yText) . ' Td (' . self::pdfEscape($status) . ") Tj ET\n";
        $stream .= 'BT /F2 7.8 Tf ' . self::num($left + 476) . ' ' . self::num($yText) . ' Td (' . self::pdfEscape($amount) . ") Tj ET\n";
        return $stream;
    }

    private static function signatureStream(array $data, float $y, float $left, float $contentW, ?array $signatureObject = null): string {
        $boxY = $y - 138.0;
        $stream = '1 1 1 rg ' . self::num($left) . ' ' . self::num($boxY) . ' ' . self::num($contentW) . " 138 re f\n";
        $stream .= '0.82 0.86 0.92 RG ' . self::num($left) . ' ' . self::num($boxY) . ' ' . self::num($contentW) . " 138 re S\n";
        $stream .= "0.13 0.17 0.25 rg\nBT /F2 11 Tf " . self::num($left + 12) . ' ' . self::num($y - 20) . ' Td (' . self::pdfEscape('Declaração de recebimento') . ") Tj ET\n";
        $declaration = 'Declaro que recebi o valor total de ' . self::moneyLabel((float)($data['total'] ?? 0)) . ' referente às despesas listadas neste documento, relativas ao período ' . (string)($data['period_label'] ?? '') . '.';
        $lines = self::wrapText($declaration, 92);
        $lineY = $y - 39.0;
        foreach ($lines as $line) {
            $stream .= "0.32 0.37 0.45 rg\nBT /F1 8.5 Tf " . self::num($left + 12) . ' ' . self::num($lineY) . ' Td (' . self::pdfEscape($line) . ") Tj ET\n";
            $lineY -= 12.0;
        }
        $stream .= "0.52 0.56 0.64 RG\n";
        $stream .= self::num($left + 12) . ' ' . self::num($boxY + 30) . ' m ' . self::num($left + 255) . ' ' . self::num($boxY + 30) . " l S\n";
        $stream .= self::num($left + 302) . ' ' . self::num($boxY + 30) . ' m ' . self::num($left + $contentW - 12) . ' ' . self::num($boxY + 30) . " l S\n";
        if ($signatureObject) {
            $maxW = 210.0; $maxH = 48.0;
            $ratio = (float)$signatureObject['w'] / max(1.0, (float)$signatureObject['h']);
            $drawW = min($maxW, $maxH * $ratio);
            $drawH = min($maxH, $drawW / max(0.1, $ratio));
            $sigX = $left + 18.0;
            $sigY = $boxY + 34.0;
            $stream .= 'q ' . self::num($drawW) . ' 0 0 ' . self::num($drawH) . ' ' . self::num($sigX) . ' ' . self::num($sigY) . " cm /Sig1 Do Q\n";
            $signedAt = (string)($data['signature_signed_at'] ?? '');
            $dateLabel = $signedAt !== '' ? wp_date('d/m/Y H:i', strtotime($signedAt)) : wp_date('d/m/Y H:i');
            $stream .= "0.16 0.21 0.30 rg\nBT /F1 7 Tf " . self::num($left + 302) . ' ' . self::num($boxY + 44) . ' Td (' . self::pdfEscape('Assinado no FieldFlow em ' . $dateLabel) . ") Tj ET\n";
        }
        $stream .= "0.38 0.43 0.52 rg\nBT /F1 7.5 Tf " . self::num($left + 12) . ' ' . self::num($boxY + 16) . ' Td (' . self::pdfEscape('Assinatura do colaborador') . ") Tj ET\n";
        $dateText = $signatureObject ? 'Data registada eletronicamente' : 'Data: ____ / ____ / ______';
        $stream .= 'BT /F1 7.5 Tf ' . self::num($left + 302) . ' ' . self::num($boxY + 16) . ' Td (' . self::pdfEscape($dateText) . ") Tj ET\n";
        return $stream;
    }

    private static function imageObject(string $path, callable $add): ?array {
        $info = @getimagesize($path);
        if (!$info || empty($info[0]) || empty($info[1])) return null;
        $mime = strtolower((string)($info['mime'] ?? ''));
        if (!in_array($mime, ['image/jpeg', 'image/jpg'], true)) return null;
        $raw = @file_get_contents($path);
        if ($raw === false) return null;
        $id = $add('<< /Type /XObject /Subtype /Image /Width ' . (int)$info[0] . ' /Height ' . (int)$info[1] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($raw) . " >>\nstream\n" . $raw . "\nendstream");
        return ['id' => $id, 'w' => (int)$info[0], 'h' => (int)$info[1]];
    }

    private static function validDate(string $value): string {
        $value = sanitize_text_field($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return '';
        $dt = \DateTime::createFromFormat('!Y-m-d', $value);
        return ($dt && $dt->format('Y-m-d') === $value) ? $value : '';
    }

    private static function validMonth(string $value): string {
        $value = sanitize_text_field($value);
        if (!preg_match('/^\d{4}-\d{2}$/', $value)) return '';
        $dt = \DateTime::createFromFormat('!Y-m-d', $value . '-01');
        return ($dt && $dt->format('Y-m') === $value) ? $value : '';
    }

    private static function monthBounds(string $month): array {
        $dt = \DateTime::createFromFormat('!Y-m-d', $month . '-01');
        if (!$dt) {
            $today = current_time('Y-m-d');
            $dt = \DateTime::createFromFormat('!Y-m-d', substr($today, 0, 7) . '-01');
        }
        return [$dt->format('Y-m-01'), $dt->format('Y-m-t')];
    }

    private static function periodLabel(string $from, string $to): string {
        if ($from === '' && $to === '') return 'Todos os períodos';
        $fromDt = \DateTime::createFromFormat('!Y-m-d', $from);
        $toDt = \DateTime::createFromFormat('!Y-m-d', $to);
        if (!$fromDt || !$toDt) return trim($from . ' - ' . $to);
        if ($fromDt->format('Y-m-01') === $from && $fromDt->format('Y-m-t') === $to && $fromDt->format('Y-m') === $toDt->format('Y-m')) {
            $months = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
            return ($months[(int)$fromDt->format('n')] ?? $fromDt->format('m')) . ' ' . $fromDt->format('Y');
        }
        return $fromDt->format('d/m/Y') . ' a ' . $toDt->format('d/m/Y');
    }

    private static function documentId(int $userId, string $from, string $to): string {
        $scope = $from !== '' ? str_replace('-', '', substr($from, 0, 7)) : 'ALL';
        return 'FIELDFLOW-EXP-' . $scope . '-' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(hash('sha256', $userId . '|' . $from . '|' . $to), 0, 6));
    }

    private static function humanDate(string $date): string {
        $dt = \DateTime::createFromFormat('!Y-m-d', $date);
        return $dt ? $dt->format('d/m/Y') : $date;
    }

    private static function humanDateShort(string $date): string {
        $dt = \DateTime::createFromFormat('!Y-m-d', $date);
        return $dt ? $dt->format('d/m') : $date;
    }

    private static function moneyLabel(float $value): string {
        return number_format($value, 2, ',', '.') . ' €';
    }

    private static function trimText(string $text, int $max): string {
        $text = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($text)) ?: $text);
        if ($max <= 0) return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') <= $max) return $text;
            return rtrim(mb_substr($text, 0, max(1, $max - 1), 'UTF-8')) . '…';
        }
        if (strlen($text) <= $max) return $text;
        return rtrim(substr($text, 0, max(1, $max - 3))) . '...';
    }

    private static function wrapText(string $text, int $max): array {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?: $text);
        if ($text === '') return [];
        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            $length = function_exists('mb_strlen') ? mb_strlen($candidate, 'UTF-8') : strlen($candidate);
            if ($line !== '' && $length > $max) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') $lines[] = $line;
        return $lines;
    }

    private static function pdfEscape(string $text): string {
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strtr($text, ['–'=>'-','—'=>'-','−'=>'-','•'=>'-','·'=>'-','“'=>'"','”'=>'"','’'=>"'",'‘'=>"'",'…'=>'...']);
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
            if ($converted !== false) $text = $converted;
        } elseif (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
            if ($converted !== false) $text = $converted;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private static function num(float $value): string {
        return number_format($value, 2, '.', '');
    }
}
