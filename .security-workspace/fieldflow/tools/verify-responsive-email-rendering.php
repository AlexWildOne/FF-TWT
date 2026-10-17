<?php
/**
 * Build-time, non-destructive verifier for FieldFlow 2.2.466 responsive mail rendering.
 */
$root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$checks = [];
$failures = [];

$read = static function(string $relative) use ($root, &$failures): string {
    $path = $root . $relative;
    if (!is_readable($path)) {
        $failures[] = $relative . ':file';
        return '';
    }
    return (string)file_get_contents($path);
};
$check = static function(string $name, bool $ok, string $detail = '') use (&$checks, &$failures): void {
    $checks[] = ['name'=>$name,'ok'=>$ok,'detail'=>$detail];
    if (!$ok) $failures[] = $name . ($detail !== '' ? ':' . $detail : '');
};

$emails = $read('src/Admin/Emails.php');
$automations = $read('src/Admin/ReportAutomations.php');
$shopping = $read('src/Shopping/ShoppingService.php');
$phase = $read('PHASE-7ZH-RESPONSIVE-EMAIL-RENDERING-HOTFIX.md');

$check('fluid_shell',
    $emails !== ''
    && strpos($emails, 'responsive_email_css') !== false
    && strpos($emails, 'ff-email-viewport') !== false
    && strpos($emails, 'harden_email_content_html') !== false
    && strpos($emails, 'width="100%"') !== false
    && strpos($emails, 'x-apple-disable-message-reformatting') !== false
    && strpos($emails, '<!--[if mso]>') !== false
);
$check('common_content_bounds',
    strpos($emails, '.ff-email-content table{max-width:100%!important') !== false
    && strpos($emails, '.ff-email-content img{max-width:100%!important') !== false
    && strpos($emails, '.ff-email-content td,.ff-email-content th{max-width:100%!important') !== false
    && strpos($emails, '.ff-data-table{width:100%!important;max-width:100%!important;table-layout:fixed!important') !== false
);
$check('newsletter_fluid_attribute',
    strpos($emails, '<table class="ff-news-wrap" role="presentation" width="100%"') !== false
    && strpos($emails, '<table class="ff-news-wrap" role="presentation" width="640"') === false
);

$orderStart = strpos($automations, 'private static function orderSuggestionEmailBody');
$orderEnd = $orderStart === false ? false : strpos($automations, 'private static function buildReportBundle', $orderStart);
$order = ($orderStart !== false && $orderEnd !== false) ? substr($automations, $orderStart, $orderEnd - $orderStart) : '';
$check('order_suggestion_no_flex',
    $order !== ''
    && strpos($order, 'ff-order-summary') !== false
    && strpos($order, 'ff-order-card') !== false
    && strpos($order, 'display:flex') === false
    && strpos($order, 'min-width:130') === false
);
$check('order_suggestion_fixed_layout',
    strpos($order, 'table-layout:fixed') !== false
    && strpos($order, 'Emails::responsive_email_css()') !== false
    && strpos($order, 'ff-email-viewport') !== false
);

$priceStart = strpos($shopping, 'private static function priceTableHtml');
$priceEnd = $priceStart === false ? false : strpos($shopping, 'public static function projectMerchandiserIds', $priceStart);
$price = ($priceStart !== false && $priceEnd !== false) ? substr($shopping, $priceStart, $priceEnd - $priceStart) : '';
$check('shopping_fixed_layout',
    $price !== ''
    && strpos($price, 'ff-price-table ff-data-table') !== false
    && strpos($price, 'table-layout:fixed') !== false
    && strpos($price, 'white-space:nowrap') === false
    && strpos($price, 'white-space:normal') !== false
);

$emailBodyStart = strpos($automations, 'private static function emailBody');
$emailBodyEnd = $emailBodyStart === false ? false : strpos($automations, 'private static $brand', $emailBodyStart);
$reportBody = ($emailBodyStart !== false && $emailBodyEnd !== false) ? substr($automations, $emailBodyStart, $emailBodyEnd - $emailBodyStart) : '';
$check('report_shared_contract',
    $reportBody !== ''
    && strpos($reportBody, 'Emails::responsive_email_css()') !== false
    && strpos($reportBody, 'ff-email-viewport') !== false
    && strpos($reportBody, 'width="100%"') !== false
);
$check('phase_contract',
    strpos($phase, 'No FieldFlow email requires horizontal scrolling') !== false
    && strpos($phase, 'No database migration is required') !== false
);

foreach ($checks as $row) {
    echo ($row['ok'] ? '[PASS] ' : '[FAIL] ') . $row['name'];
    if ($row['detail'] !== '') echo ' - ' . $row['detail'];
    echo PHP_EOL;
}
if ($failures) {
    fwrite(STDERR, 'RESPONSIVE EMAIL RENDERING CONTRACT FAILED: ' . implode(' | ', array_unique($failures)) . PHP_EOL);
    exit(1);
}
echo 'RESPONSIVE EMAIL RENDERING CONTRACT OK (' . count($checks) . '/' . count($checks) . ')' . PHP_EOL;
