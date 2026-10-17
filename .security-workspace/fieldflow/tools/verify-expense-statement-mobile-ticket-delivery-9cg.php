<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => ["Version: 2.2.604", "define('FIELDFLOW_VERSION', '2.2.604')", "9CG predecessor marker: define('FIELDFLOW_VERSION', '2.2.603')"],
    'src/Admin/Expenses.php' => [
        'fieldflow-expense-statement-mobile-ticket-delivery-v1',
        'prepareStatementTicket()',
        'handleStatementTicketDownload()',
        "wp_tempnam('fieldflow-expense-statement.pdf')",
        'fieldflow_expense_statement_download',
        'in_array($method, [\'GET\',\'HEAD\'], true)',
        'X-FieldFlow-Expense-Statement-Ticket',
        'Keep the ticket alive for Android/Chrome HEAD probes and GET retries.',
    ],
    'src/Architecture/RuntimeRegistry.php' => [
        'wp_ajax_fieldflow_prepare_expense_statement_ticket',
        'handleStatementTicketDownload',
        'fieldflow_expense_statement_ticket_cleanup',
    ],
    'src/Mobile/WebViewBridge.php' => [
        'fieldflow-expense-statement-mobile-ticket-delivery-v1',
        "expenseStatement:action==='fieldflow_expense_statement_export'",
        'function requestExpenseStatement(meta)',
        "body.set('action','fieldflow_prepare_expense_statement_ticket')",
        "source:'expense_statement_pdf_ticket'",
        "transport:'external_system_browser'",
        'if(meta&&meta.expenseStatement){requestExpenseStatement(meta);return;}',
    ],
    'src/Cloud/Phase6StableRelease.php' => [
        "public const SOURCE_RELEASE = '2.2.603'",
        "public const STABLE_RELEASE = '2.2.604'",
        "// public const SOURCE_RELEASE = '2.2.602'",
        "// public const STABLE_RELEASE = '2.2.603'",
    ],
    'src/Support/ReleaseGate.php' => ['EXPENSE_STATEMENT_MOBILE_TICKET_DELIVERY_9CG_CONTRACT','checkExpenseStatementMobileTicketDelivery9CGContract'],
    'src/Support/ReleasePackageValidator.php' => ['expense_statement_mobile_ticket_delivery_9cg_ok','Expense Statement Mobile Ticket Delivery 9CG'],
    'src/Admin/ReleaseManager.php' => ["packageResult['expense_statement_mobile_ticket_delivery_9cg_ok']",'PDF APP OK'],
    'PHASE-9CG-EXPENSE-STATEMENT-MOBILE-TICKET-DELIVERY.md' => [
        'fieldflow-expense-statement-mobile-ticket-delivery-v1',
        '`SOURCE_RELEASE` is `2.2.603` and `STABLE_RELEASE` is `2.2.604`',
        'authenticated WebView session',
        'cookie-free capability URL',
        'HEAD probes',
        'generic Flutter downloader',
        'No database schema changes',
        'No FlutterFlow source modification is required',
    ],
];
foreach ($need as $relative => $markers) {
    $path = $root . '/' . $relative;
    $raw = is_readable($path) ? (string)file_get_contents($path) : '';
    if ($raw === '') { $errors[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $errors[] = $relative . ':' . $marker;
}
if ($errors) {
    fwrite(STDERR, "EXPENSE STATEMENT MOBILE TICKET DELIVERY 9CG CONTRACT FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}
echo "EXPENSE STATEMENT MOBILE TICKET DELIVERY 9CG CONTRACT OK successor=>=2.2.604\n";
