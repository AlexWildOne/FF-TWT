<?php
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string) file_get_contents($path) : '';
};
$fail = static function (string $key): void { fwrite(STDERR, "FAIL {$key}\n"); exit(1); };
$assert = static function (bool $condition, string $key) use ($fail): void { if (!$condition) $fail($key); echo "PASS {$key}\n"; };

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase6 = $read('src/Cloud/Phase6StableRelease.php');
$cardex = $read('src/Forms/ProductCardex.php');
$forms = $read('src/Forms/Forms.php');
$renderer = $read('src/Forms/FormRenderer.php');
$mobilePayload = $read('src/Mobile/FormPayloadService.php');
$mobileSubmit = $read('src/Mobile/SubmissionService.php');
$mail = $read('src/Admin/FormSubmissions.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$migrations = $read('src/Support/Migrations.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-9U-ORDER-SOURCE-CARDEX-CONSISTENCY.md');

$assert((bool)preg_match("/FIELDFLOW_VERSION'\s*,\s*'([^']+)'/", $plugin, $releaseMatch), 'release_identity_marker');
$release = (string)$releaseMatch[1];
$assert(version_compare($release, '2.2.537', '>='), 'release_identity');
$assert(strpos($readme, 'Stable tag: ' . $release) !== false, 'readme_identity');
$assert((bool)preg_match("/SOURCE_RELEASE\s*=\s*'([^']+)'/", $phase6, $sourceMatch) && (bool)preg_match("/STABLE_RELEASE\s*=\s*'([^']+)'/", $phase6, $stableMatch), 'phase6_markers');
$parts = array_map('intval', explode('.', $release));
$previous = count($parts) === 3 && $parts[2] > 0 ? $parts[0] . '.' . $parts[1] . '.' . ($parts[2] - 1) : '';
$assert((string)$stableMatch[1] === $release && (string)$sourceMatch[1] === $previous, 'phase6_continuity');
$assert(strpos($cardex, "ORDER_SOURCE_CARDEX_CONSISTENCY_CONTRACT = 'fieldflow-order-source-cardex-consistency-v1'") !== false, 'cardex_contract');
$assert(strpos($cardex, 'AND (c.client_id=0 OR %d=0 OR c.client_id=%d)') !== false && strpos($cardex, 'AND (c.project_id=0 OR %d=0 OR c.project_id=%d)') !== false, 'explicit_link_context_tolerance');
$assert(strpos($cardex, "['pd', 'pingo doce', 'p doce', 'pingodoce']") !== false && strpos($cardex, "['cbd', 'continente bom dia']") !== false, 'insignia_aliases');
$assert(strpos($forms, 'posted_front_selection($cardex_id, $paired_fronts_key)') !== false && strpos($forms, "\$quantity_source !== 'manual'") !== false && strpos($forms, "'quantity_source' => \$quantity_source") !== false, 'web_submission_manual_authority');
$assert(strpos($renderer, 'data-order-quantity-source') !== false && strpos($renderer, ".val('auto')") !== false && strpos($renderer, ".val('manual')") !== false, 'renderer_quantity_source');
$assert(strpos($mobilePayload, "\$available = \$type === 'order_suggestion' ? true : \$cardexAvailable") !== false && strpos($mobilePayload, "['paired_fronts_key']") !== false, 'mobile_payload_parity');
$assert(strpos($mobileSubmit, 'sanitize_order_suggestion_value') !== false && strpos($mobileSubmit, "if (\$type === 'product_matrix'") !== false, 'mobile_submission_parity');
$assert(strpos($mail, 'getOrderSuggestionSubmission') !== false && strpos($mail, "'order_qty' => \$order['order_qty'] ?? ''") !== false, 'mail_reads_persisted_quantity');
$assert(strpos($gate, "ORDER_SOURCE_CARDEX_CONSISTENCY_9U_CONTRACT = 'fieldflow-order-source-cardex-consistency-v1'") !== false && strpos($gate, 'checkOrderSourceCardexConsistency9UContract') !== false, 'release_gate_contract');
$assert(strpos($validator, "'order_source_cardex_consistency_9u_ok'=>false") !== false && strpos($validator, "version_compare((string)(\$result['version'] ?? ''), '2.2.537', '>=')") !== false, 'package_validator_contract');
$assert(strpos($manager, 'Order Source &amp; Cardex Consistency 9U') !== false && strpos($manager, "packageResult['order_source_cardex_consistency_9u_ok']") !== false, 'release_manager_kpi');
$assert(strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false, 'schema_frozen');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_frozen');
$assert(strpos($doc, 'fieldflow-order-source-cardex-consistency-v1') !== false && strpos($doc, '`SOURCE_RELEASE` is `2.2.536` and `STABLE_RELEASE` is `2.2.537`') !== false, 'phase_documentation');
$assert(version_compare($release, '2.2.537', '>='), 'successor_semantics');

echo "ORDER SOURCE CARDEX CONSISTENCY 9U CONTRACT OK\n";
echo "release={$release}\n";
echo "contract=fieldflow-order-source-cardex-consistency-v1\n";
echo "successor=>=2.2.537\n";
echo "schema=2.2.529\n";
echo "submission_build=2.2.485\n";
