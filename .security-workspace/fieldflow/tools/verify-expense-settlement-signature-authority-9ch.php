<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => ["Version: 2.2.605", "define('FIELDFLOW_VERSION', '2.2.605')", "9CH predecessor marker: define('FIELDFLOW_VERSION', '2.2.604')"],
    'src/Expenses/ExpenseService.php' => ["'paid' => 'Paga / Reembolsada'", 'public static function setStatus(', 'payment_history', "'paid_at' => \$now", "'paid_by' => \$actorUserId"],
    'src/Expenses/ExpenseSettlementService.php' => [
        'fieldflow-expense-settlement-signature-authority-v1',
        "POST_TYPE = 'ff_exp_settlement'",
        "'public' => false",
        'normalizeSettlementFilters(array $input)',
        "\$filters['status'] = 'paid'",
        'submitUpload(',
        'submitDirectSignature(',
        'snapshotHash(array $data)',
        'fieldflow-private-expense-settlements',
        'uploaded_signed_file',
        'fieldflow_direct_signature',
        'serveEvidence()',
    ],
    'src/Expenses/ExpensesController.php' => ['/expenses/statement-context','/expenses/statement-evidence','statementContext(','submitStatementEvidence(','declaration_confirmed'],
    'src/Expenses/ExpenseStatementService.php' => ['signature_image_path','signature_signed_at','/Sig1','Assinado no FieldFlow em','signedFilename(array $data)'],
    'src/Admin/Expenses.php' => ['fieldflow_expense_bulk_paid','bulkMarkPaid()','Marcar filtradas como Paga / Reembolsada','Folhas assinadas e comprovativos de recebimento','folhas-assinadas/','Pago em','Pago por'],
    'src/Front/ExpensesShortcodeRenderer.php' => ['Fecho e comprovativo de recebimento','Enviar folha já assinada','Assinar diretamente no FieldFlow','ffe-signature','statement-context','statement-evidence',"fd.set('mode','upload')","fd.set('mode','signature')"],
    'src/Support/Loader.php' => ['src/Expenses/ExpenseSettlementService.php'],
    'src/Architecture/RuntimeRegistry.php' => ['ExpenseSettlementService::registerHooks()'],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.604'", "public const STABLE_RELEASE = '2.2.605'", "// public const SOURCE_RELEASE = '2.2.603'", "// public const STABLE_RELEASE = '2.2.604'"],
    'src/Support/ReleaseGate.php' => ['EXPENSE_SETTLEMENT_SIGNATURE_9CH_CONTRACT','checkExpenseSettlementSignature9CHContract'],
    'src/Support/ReleasePackageValidator.php' => ['expense_settlement_signature_9ch_ok','Expense Settlement, Payment & Signature Authority 9CH'],
    'src/Admin/ReleaseManager.php' => ["packageResult['expense_settlement_signature_9ch_ok']",'PAGAMENTO + ASSINATURA OK'],
    'PHASE-9CH-EXPENSE-SETTLEMENT-SIGNATURE-AUTHORITY.md' => [
        'fieldflow-expense-settlement-signature-authority-v1',
        '`SOURCE_RELEASE` is `2.2.604` and `STABLE_RELEASE` is `2.2.605`',
        'Paga / Reembolsada',
        'upload an already signed statement',
        'draw a handwritten signature directly',
        'No schema migration is required',
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
    fwrite(STDERR, "EXPENSE SETTLEMENT PAYMENT & SIGNATURE AUTHORITY 9CH CONTRACT FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}
echo "EXPENSE SETTLEMENT PAYMENT & SIGNATURE AUTHORITY 9CH CONTRACT OK successor=>=2.2.605\n";
