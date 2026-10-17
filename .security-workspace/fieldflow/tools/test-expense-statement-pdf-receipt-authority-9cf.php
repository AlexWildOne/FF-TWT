<?php
namespace {
    $root = dirname(__DIR__);
    if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
    if (!defined('ROUTESPRO_PATH')) define('ROUTESPRO_PATH', $root . '/');
    if (!function_exists('wp_date')) { function wp_date($format, $timestamp = null) { return date($format, $timestamp ?: time()); } }
    if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($text) { return strip_tags((string)$text); } }
    if (!function_exists('sanitize_key')) { function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$key)); } }
    if (!function_exists('sanitize_text_field')) { function sanitize_text_field($value) { return trim(strip_tags((string)$value)); } }
    if (!function_exists('sanitize_file_name')) { function sanitize_file_name($value) { return preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$value); } }
}
namespace RoutesPro\Admin {
    if (!class_exists(__NAMESPACE__ . '\\Branding')) {
        class Branding {
            public static function get_header_logos(array $context = []): array { return []; }
            public static function maybe_prepare_pdf_logo_file(string $url): string { return ''; }
        }
    }
}
namespace RoutesPro\Expenses {
    if (!class_exists(__NAMESPACE__ . '\\ExpenseService')) {
        class ExpenseService {
            public static function categories(): array { return ['fuel'=>'Combustível','parking'=>'Estacionamento','meals'=>'Alimentação']; }
            public static function statuses(): array { return ['pending'=>'Pendente','approved'=>'Aprovada','rejected'=>'Rejeitada']; }
        }
    }
}
namespace {
    $read = static function(string $relative) use ($root): string {
        $path = $root . '/' . $relative;
        return is_readable($path) ? (string)file_get_contents($path) : '';
    };
    require_once $root . '/src/Expenses/ExpenseStatementService.php';
    $service = $read('src/Expenses/ExpenseStatementService.php');
    $admin = $read('src/Admin/Expenses.php');
    $front = $read('src/Front/ExpensesShortcodeRenderer.php');
    $webview = $read('src/Mobile/WebViewBridge.php');
    $gate = $read('src/Support/ReleaseGate.php');

    $explicit = \RoutesPro\Expenses\ExpenseStatementService::normalizeFilters(['month'=>'2026-10','date_from'=>'2026-09-03','date_to'=>'2026-09-21','category'=>'fuel','status'=>'approved','search'=>'Galp']);
    $month = \RoutesPro\Expenses\ExpenseStatementService::normalizeFilters(['month'=>'2026-09']);
    $single = \RoutesPro\Expenses\ExpenseStatementService::normalizeFilters(['date_from'=>'2026-09-14']);

    $data = [
        'client_id'=>1,'project_id'=>1,'client_name'=>'Cliente Teste','project_name'=>'Campanha Teste',
        'document_id'=>'FIELDFLOW-EXP-2026-09-000007','user_name'=>'Operativo Teste','user_email'=>'teste@example.test',
        'period_label'=>'03/09/2026 a 21/09/2026','total'=>35.50,'count'=>2,'days'=>1,
        'items'=>[
            ['expense_date'=>'2026-09-03','category_label'=>'Combustível','merchant_name'=>'Fornecedor A','description'=>'Visita A','document_no'=>'A1','status_label'=>'Aprovada','amount_total'=>20.25],
            ['expense_date'=>'2026-09-03','category_label'=>'Estacionamento','merchant_name'=>'Fornecedor B','description'=>'Visita B','document_no'=>'B1','status_label'=>'Aprovada','amount_total'=>15.25],
        ],
        'daily'=>[[
            'date'=>'2026-09-03','count'=>2,'total'=>35.50,'items'=>[
                ['expense_date'=>'2026-09-03','category_label'=>'Combustível','merchant_name'=>'Fornecedor A','description'=>'Visita A','document_no'=>'A1','status_label'=>'Aprovada','amount_total'=>20.25],
                ['expense_date'=>'2026-09-03','category_label'=>'Estacionamento','merchant_name'=>'Fornecedor B','description'=>'Visita B','document_no'=>'B1','status_label'=>'Aprovada','amount_total'=>15.25],
            ],
        ]],
    ];
    $pdf = \RoutesPro\Expenses\ExpenseStatementService::buildPdf($data);
    $checks = [
        'personal_pdf' => strpos($front, 'ffe-statement-pdf') !== false && strpos($admin, '$manager && $requestedUserId > 0 ? $requestedUserId : get_current_user_id()') !== false,
        'admin_operational_pdf' => strpos($admin, "current_user_can('routespro_manage')") !== false && strpos($admin, 'Folha de despesas PDF') !== false && strpos($admin, "'user_id'") !== false,
        'date_filter_authority' => $explicit['date_from']==='2026-09-03' && $explicit['date_to']==='2026-09-21' && $explicit['month']==='' && $month['date_from']==='2026-09-01' && $month['date_to']==='2026-09-30' && $single['date_from']==='2026-09-14' && $single['date_to']==='2026-09-14',
        'daily_totals' => strpos($service, 'array_values($daily)') !== false && strpos($service, 'round($total, 2)') !== false && strpos($service, 'dayHeaderStream') !== false,
        'dual_branding' => strpos($service, 'Branding::get_header_logos') !== false && strpos($service, "assets/logo-twt.jpg") !== false,
        'signature_receipt' => strncmp($pdf, '%PDF-1.4', 8) === 0 && strpos($pdf, 'Folha de Despesas') !== false && strpos($pdf, 'Assinatura do colaborador') !== false && strpos($pdf, 'Data: ____ / ____ / ______') !== false,
        'webview_live_scope' => strpos($webview, 'function applyLiveExpenseStatementScope(anchor,url)') !== false && strpos($webview, 'url=applyLiveExpenseStatementScope(anchor,url);') !== false && strpos($webview, "month:'.ffe-filter-month'") !== false,
        'historical_continuity' => strpos($gate, 'PORTAL_EXPORT_RUNTIME_CERT_PARITY_9BV_CONTRACT') !== false && strpos($gate, 'PREMIUM_LOCALE_VISIT_MEMORY_9BZ_CONTRACT') !== false && strpos($gate, 'APP_SHELL_BROWSERLIKE_TRANSLATION_9CC_CONTRACT') !== false && strpos($gate, 'ADAPTIVE_LOCALE_DOCK_9CD_CONTRACT') !== false && strpos($gate, 'ANALYTICS_HEADER_DATE_EXPORT_AUTHORITY_9CE_CONTRACT') !== false,
    ];
    $failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
    if ($failed) {
        fwrite(STDERR, 'EXPENSE STATEMENT PDF & RECEIPT AUTHORITY 9CF RUNTIME FAIL | ' . implode(',', $failed) . "\n");
        exit(1);
    }
    echo 'EXPENSE STATEMENT PDF & RECEIPT AUTHORITY 9CF RUNTIME OK | personal_pdf=1 | admin_operational_pdf=1 | date_filter_authority=1 | daily_totals=1 | dual_branding=1 | signature_receipt=1 | webview_live_scope=1 | historical_continuity=1' . "\n";
}
