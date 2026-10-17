<?php
$root = dirname(__DIR__);
$fail = [];
$read = static function(string $rel) use ($root): string { $p=$root . '/' . $rel; return is_readable($p) ? (string)file_get_contents($p) : ''; };
$assert = static function(bool $ok, string $label) use (&$fail): void { if (!$ok) $fail[]=$label; };
$main=$read('fieldflow.php');
$stats=$read('src/Rest/StatsController.php');
$canonical=$read('src/Reporting/CanonicalOperationalReportService.php');
$commercial=$read('src/Reporting/DailyCommercialReportService.php');
$automations=$read('src/Admin/ReportAutomations.php');
$exports=$read('src/Admin/FormSubmissions.php');
$gate=$read('src/Support/ReleaseGate.php');
$validator=$read('src/Support/ReleasePackageValidator.php');
$phase=$read('PHASE-9AJ-BRIEFING-STORE-SCOPE-REPORTING-CONTINUITY.md');
$release=''; if (preg_match('/Version:\s*([0-9.]+)/',$main,$m)) $release=(string)$m[1];
$assert($release!=='' && version_compare($release,'2.2.554','>='), 'release');
$assert(strpos($stats, "'target_assignment_keys'=>") !== false && strpos($stats, "'answered_assignment_keys'=>") !== false, 'analytics assignment identity');
$assert(strpos($canonical, 'question_store_mapping_x_report_routes') !== false, 'canonical store-route intersection');
$assert(strpos($canonical, 'briefingAssignmentLocationId') !== false, 'assignment location parser');
$assert(strpos($commercial, 'briefing_pending_assignments') !== false && strpos($commercial, 'mapeadas às lojas presentes nesta rota/período') !== false, 'scoped alert');
$assert(strpos($automations, 'respostas alvo') !== false && strpos($automations, 'loja(s) mapeada(s) na rota') !== false, 'mail scope copy');
$assert(strpos($exports, 'Respostas alvo') !== false && strpos($exports, 'Lojas alvo') !== false, 'excel scope');
$assert(strpos($gate, "BRIEFING_STORE_SCOPE_REPORTING_9AJ_CONTRACT = 'fieldflow-briefing-store-scope-reporting-v1'") !== false, 'release gate');
$assert(strpos($validator, 'briefing_store_scope_reporting_9aj_ok') !== false, 'package validator');
$assert(strpos($phase, '`SOURCE_RELEASE` is `2.2.553` and `STABLE_RELEASE` is `2.2.554`') !== false, 'phase identity');
if ($fail) { fwrite(STDERR, '9AJ FAIL: ' . implode(', ', $fail) . PHP_EOL); exit(1); }
echo 'BRIEFING STORE SCOPE REPORTING 9AJ CONTRACT OK | release=' . $release . ' | scope=question-store-mapping+x+report-routes | mail=client+merch | excel=scoped' . PHP_EOL;
