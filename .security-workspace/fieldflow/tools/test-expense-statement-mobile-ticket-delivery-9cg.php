<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$expenses = $read('src/Admin/Expenses.php');
$runtime = $read('src/Architecture/RuntimeRegistry.php');
$webview = $read('src/Mobile/WebViewBridge.php');
$gate = $read('src/Support/ReleaseGate.php');
$phase = $read('src/Cloud/Phase6StableRelease.php');

$prepareStart = strpos($expenses, 'public static function prepareStatementTicket()');
$prepareEnd = $prepareStart === false ? false : strpos($expenses, 'public static function handleStatementTicketDownload()', $prepareStart);
$prepare = ($prepareStart !== false && $prepareEnd !== false) ? substr($expenses, $prepareStart, $prepareEnd - $prepareStart) : '';
$publicStart = strpos($expenses, 'public static function handleStatementTicketDownload()');
$publicEnd = $publicStart === false ? false : strpos($expenses, 'public static function cleanupStatementTicketFile', $publicStart);
$public = ($publicStart !== false && $publicEnd !== false) ? substr($expenses, $publicStart, $publicEnd - $publicStart) : '';
$bridgeStart = strpos($webview, 'function requestExpenseStatement(meta)');
$bridgeEnd = $bridgeStart === false ? false : strpos($webview, 'function requestDashboardExport(meta)', $bridgeStart);
$bridge = ($bridgeStart !== false && $bridgeEnd !== false) ? substr($webview, $bridgeStart, $bridgeEnd - $bridgeStart) : '';
$requestStart = strpos($webview, 'function requestDownload(meta)');
$requestEnd = $requestStart === false ? false : strpos($webview, 'function isAllowedHost', $requestStart);
$request = ($requestStart !== false && $requestEnd !== false) ? substr($webview, $requestStart, $requestEnd - $requestStart) : '';

$checks = [
    'cookie_free_ticket' => $prepare !== ''
        && strpos($prepare, 'wp_verify_nonce($nonce, \'fieldflow_expense_statement_export\')') !== false
        && strpos($prepare, 'set_transient($key, $payload') !== false
        && strpos($expenses, "'fieldflow_expense_statement_download'=>'1'") !== false
        && $public !== ''
        && strpos($public, 'is_user_logged_in()') === false
        && strpos($public, 'wp_verify_nonce') === false,
    'head_safe' => $public !== ''
        && strpos($public, "['GET','HEAD']") !== false
        && strpos($public, 'if ($method === \'HEAD\') exit;') !== false
        && strpos($public, 'Keep the ticket alive for Android/Chrome HEAD probes and GET retries.') !== false
        && strpos($public, 'delete_transient($key);') !== false,
    'generic_flutter_bypass' => $bridge !== ''
        && strpos($bridge, 'nativeDownload(meta)') === false
        && strpos($bridge, "fieldflow.download.requested") === false
        && $request !== ''
        && strpos($request, 'if(meta&&meta.expenseStatement){requestExpenseStatement(meta);return;}') !== false
        && strpos($request, "nativeEvent('fieldflow.download.requested'") !== false
        && strpos($request, 'if(meta&&meta.expenseStatement){requestExpenseStatement(meta);return;}') < strpos($request, "nativeEvent('fieldflow.download.requested'"),
    'system_browser' => $bridge !== ''
        && strpos($bridge, "source:'expense_statement_pdf_ticket'") !== false
        && strpos($bridge, 'nativeExternalOpen(payload,3600)') !== false
        && strpos($bridge, "transport:'external_system_browser'") !== false
        && strpos($bridge, 'browserDownload(fallback)') !== false,
    'filter_scope' => strpos($webview, 'function applyLiveExpenseStatementScope(anchor,url)') !== false
        && strpos($webview, 'url=applyLiveExpenseStatementScope(anchor,url);') !== false
        && strpos($bridge, 'var body=new URLSearchParams(source.search);') !== false
        && strpos($prepare, 'ExpenseStatementService::statementData($userId, $filters, $clientId, $projectId)') !== false,
    'historical_continuity' => strpos($gate, 'EXPENSE_STATEMENT_PDF_RECEIPT_AUTHORITY_9CF_CONTRACT') !== false
        && strpos($gate, 'ANALYTICS_HEADER_DATE_EXPORT_AUTHORITY_9CE_CONTRACT') !== false
        && strpos($gate, 'PORTAL_EXPORT_RUNTIME_CERT_PARITY_9BV_CONTRACT') !== false
        && strpos($phase, "public const SOURCE_RELEASE = '2.2.603'") !== false
        && strpos($phase, "public const STABLE_RELEASE = '2.2.604'") !== false
        && strpos($phase, "// public const SOURCE_RELEASE = '2.2.602'") !== false
        && strpos($phase, "// public const STABLE_RELEASE = '2.2.603'") !== false
        && strpos($runtime, 'handlePreparedEvidencePublicDownload') !== false,
];
$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR, 'EXPENSE STATEMENT MOBILE TICKET DELIVERY 9CG RUNTIME FAIL | ' . implode(',', $failed) . "\n");
    exit(1);
}
echo 'EXPENSE STATEMENT MOBILE TICKET DELIVERY 9CG RUNTIME OK | cookie_free_ticket=1 | head_safe=1 | generic_flutter_bypass=1 | system_browser=1 | filter_scope=1 | historical_continuity=1' . "\n";
