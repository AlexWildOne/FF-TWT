<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => ["Version: 2.2.603", "define('FIELDFLOW_VERSION', '2.2.603')"],
    'src/Expenses/ExpenseStatementService.php' => ['fieldflow-expense-statement-pdf-receipt-authority-v1','normalizeFilters(array $input)','statementData(int $userId','ExpensesController::buildWhere($filters, $userId, true)','Branding::get_header_logos','Declaração de recebimento','Assinatura do colaborador','Data: ____ / ____ / ______'],
    'src/Admin/Expenses.php' => ['admin_post_fieldflow_expense_statement_export','exportStatement()','Folha de despesas PDF'],
    'src/Front/ExpensesShortcodeRenderer.php' => ['fieldflow_expense_statement_export','ffe-statement-pdf','syncStatementUrl()',"['month','.ffe-filter-month']","['date_from','.ffe-filter-from']","['date_to','.ffe-filter-to']","['category','.ffe-filter-category']","['status','.ffe-filter-status']","['search','.ffe-filter-search']"],
    'src/Mobile/WebViewBridge.php' => ['fieldflow-webview-expense-statement-scope-authority-v1','function applyLiveExpenseStatementScope(anchor,url)',"action!=='fieldflow_expense_statement_export'",'url=applyLiveExpenseStatementScope(anchor,url);'],
    'src/Support/Loader.php' => ['src/Expenses/ExpenseStatementService.php'],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.602'","public const STABLE_RELEASE = '2.2.603'","// public const SOURCE_RELEASE = '2.2.601'","// public const STABLE_RELEASE = '2.2.602'"],
    'src/Support/ReleaseGate.php' => ['EXPENSE_STATEMENT_PDF_RECEIPT_AUTHORITY_9CF_CONTRACT','checkExpenseStatementPdfReceiptAuthority9CFContract'],
    'src/Support/ReleasePackageValidator.php' => ['expense_statement_pdf_receipt_authority_9cf_ok','Expense Statement PDF & Receipt Authority 9CF'],
    'src/Admin/ReleaseManager.php' => ["packageResult['expense_statement_pdf_receipt_authority_9cf_ok']",'FOLHA DESPESAS OK'],
    'PHASE-9CF-EXPENSE-STATEMENT-PDF-RECEIPT-AUTHORITY.md' => ['fieldflow-expense-statement-pdf-receipt-authority-v1','Existing expenses remain the single source of truth','Explicit De/Até has precedence over the month shortcut','No database schema changes','No FlutterFlow source modification is required'],
];
foreach ($need as $relative => $markers) {
    $path = $root . '/' . $relative;
    $raw = is_readable($path) ? (string)file_get_contents($path) : '';
    if ($raw === '') { $errors[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $errors[] = $relative . ':' . $marker;
}
if ($errors) {
    fwrite(STDERR, "EXPENSE STATEMENT PDF & RECEIPT AUTHORITY 9CF CONTRACT FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}
echo "EXPENSE STATEMENT PDF & RECEIPT AUTHORITY 9CF CONTRACT OK successor=>=2.2.603\n";
