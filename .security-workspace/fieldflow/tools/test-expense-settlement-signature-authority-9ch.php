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
    if (!function_exists('sanitize_title')) { function sanitize_title($value) { return strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', (string)$value), '-')); } }
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
            public static function categories(): array { return ['fuel'=>'Combustível']; }
            public static function statuses(): array { return ['pending'=>'Pendente','approved'=>'Aprovada','paid'=>'Paga / Reembolsada','rejected'=>'Rejeitada']; }
        }
    }
}
namespace {
    $read = static function(string $relative) use ($root): string {
        $path = $root . '/' . $relative;
        return is_readable($path) ? (string)file_get_contents($path) : '';
    };
    require_once $root . '/src/Expenses/ExpenseStatementService.php';
    $expenseService = $read('src/Expenses/ExpenseService.php');
    $settlement = $read('src/Expenses/ExpenseSettlementService.php');
    $admin = $read('src/Admin/Expenses.php');
    $front = $read('src/Front/ExpensesShortcodeRenderer.php');
    $controller = $read('src/Expenses/ExpensesController.php');
    $phase = $read('src/Cloud/Phase6StableRelease.php');
    $gate = $read('src/Support/ReleaseGate.php');

    $signaturePath = $root . '/assets/logo-twt.jpg';
    $data = [
        'client_id'=>1,'project_id'=>1,'client_name'=>'Cliente Teste','project_name'=>'Campanha Teste',
        'document_id'=>'FIELDFLOW-EXP-2026-08-000001','user_name'=>'Operativo Teste','user_email'=>'teste@example.test',
        'period_label'=>'01/08/2026 a 31/08/2026','total'=>82.40,'count'=>1,'days'=>1,
        'filters'=>['date_from'=>'2026-08-01','date_to'=>'2026-08-31','month'=>'2026-08','category'=>'','status'=>'paid','search'=>''],
        'items'=>[['id'=>1,'expense_date'=>'2026-08-10','category_label'=>'Combustível','merchant_name'=>'Fornecedor','description'=>'','document_no'=>'A1','status'=>'paid','status_label'=>'Paga / Reembolsada','amount_total'=>82.40]],
        'daily'=>[['date'=>'2026-08-10','count'=>1,'total'=>82.40,'items'=>[['id'=>1,'expense_date'=>'2026-08-10','category_label'=>'Combustível','merchant_name'=>'Fornecedor','description'=>'','document_no'=>'A1','status'=>'paid','status_label'=>'Paga / Reembolsada','amount_total'=>82.40]]]],
        'signature_image_path'=>$signaturePath,
        'signature_signed_at'=>'2026-09-01 09:15:00',
        'signature_method'=>'fieldflow_direct',
    ];
    $pdf = \RoutesPro\Expenses\ExpenseStatementService::buildPdf($data);
    $checks = [
        'paid_state' => strpos($expenseService, "'paid' => 'Paga / Reembolsada'") !== false && strpos($expenseService, 'payment_history') !== false && strpos($expenseService, "'paid_by' => \$actorUserId") !== false,
        'bulk_payment' => strpos($admin, 'fieldflow_expense_bulk_paid') !== false && strpos($admin, "['pending','approved']") !== false && strpos($admin, 'admin_bulk_filter') !== false,
        'dual_evidence' => strpos($front, 'Enviar folha já assinada') !== false && strpos($front, 'Assinar diretamente no FieldFlow') !== false && strpos($controller, "fd.set") === false && strpos($controller, 'submitStatementEvidence') !== false && strpos($settlement, 'submitUpload') !== false && strpos($settlement, 'submitDirectSignature') !== false,
        'direct_signature_pdf' => strncmp($pdf, '%PDF-1.4', 8) === 0 && strpos($pdf, '/Sig1') !== false && strpos($pdf, 'Assinado no FieldFlow em') !== false && strpos($pdf, 'Data registada eletronicamente') !== false,
        'private_archive' => strpos($settlement, "'public' => false") !== false && strpos($settlement, 'fieldflow-private-expense-settlements') !== false && strpos($settlement, 'snapshotHash(array $data)') !== false && strpos($settlement, 'serveEvidence()') !== false,
        'bo_archive' => strpos($admin, 'Folhas assinadas e comprovativos de recebimento') !== false && strpos($admin, "'evidence_url'") !== false && strpos($admin, "'download_url'") !== false,
        'zip_archive' => strpos($admin, "'folhas-assinadas/'") !== false && strpos($admin, 'ExpenseSettlementService::adminList') !== false,
        'no_schema' => strpos($settlement, 'CREATE TABLE') === false && strpos($settlement, 'dbDelta') === false && strpos($settlement, "POST_TYPE = 'ff_exp_settlement'") !== false,
        'historical_continuity' => strpos($gate, 'EXPENSE_STATEMENT_MOBILE_TICKET_DELIVERY_9CG_CONTRACT') !== false && strpos($gate, 'EXPENSE_STATEMENT_PDF_RECEIPT_AUTHORITY_9CF_CONTRACT') !== false && strpos($phase, "public const SOURCE_RELEASE = '2.2.604'") !== false && strpos($phase, "public const STABLE_RELEASE = '2.2.605'") !== false,
    ];
    $failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
    if ($failed) {
        fwrite(STDERR, 'EXPENSE SETTLEMENT PAYMENT & SIGNATURE AUTHORITY 9CH RUNTIME FAIL | ' . implode(',', $failed) . "\n");
        exit(1);
    }
    echo 'EXPENSE SETTLEMENT PAYMENT & SIGNATURE AUTHORITY 9CH RUNTIME OK | paid_state=1 | bulk_payment=1 | dual_evidence=1 | direct_signature_pdf=1 | private_archive=1 | bo_archive=1 | zip_archive=1 | no_schema=1 | historical_continuity=1' . "\n";
}
