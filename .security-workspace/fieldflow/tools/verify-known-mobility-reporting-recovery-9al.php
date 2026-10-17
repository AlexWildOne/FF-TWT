<?php
$root = dirname(__DIR__);
$fail = [];
$read = static function(string $rel) use ($root): string { $p=$root . '/' . $rel; return is_readable($p) ? (string)file_get_contents($p) : ''; };
$assert = static function(bool $ok, string $label) use (&$fail): void { if (!$ok) $fail[]=$label; };
$main=$read('fieldflow.php');
$mobility=$read('src/Services/MobilityCostService.php');
$canonical=$read('src/Reporting/CanonicalOperationalReportService.php');
$commercial=$read('src/Reporting/DailyCommercialReportService.php');
$automations=$read('src/Admin/ReportAutomations.php');
$gate=$read('src/Support/ReleaseGate.php');
$validator=$read('src/Support/ReleasePackageValidator.php');
$phase=$read('PHASE-9AL-KNOWN-MOBILITY-REPORTING-RECOVERY.md');
$mail=$read('src/Admin/ReportAutomations.php');
$exports=$read('src/Admin/FormSubmissions.php');
$release=''; if (preg_match('/Version:\s*([0-9.]+)/',$main,$m)) $release=(string)$m[1];
$assert($release!=='' && version_compare($release,'2.2.556','>='), 'release');
$assert(strpos($mobility, 'reportingProjection') !== false && strpos($mobility, "'known_toll_cost_eur'") !== false, 'mobility projection');
$assert(strpos($canonical, "'mobility_reporting_recovered'") !== false && strpos($canonical, "'recovered_routes'") !== false, 'canonical recovery');
$assert(strpos($commercial, "'recovered_routes'") !== false && strpos($commercial, 'estimativa recuperada a partir dos km conhecidos') !== false, 'narrative recovery');
$assert(strpos($automations, 'Mobilidade e custos por dia') !== false && strpos($automations, 'parcial') !== false, 'mail partial semantics');
$assert(strpos($gate, "KNOWN_MOBILITY_REPORTING_RECOVERY_9AL_CONTRACT = 'fieldflow-known-mobility-reporting-recovery-v1'") !== false, 'release gate');
$assert(strpos($mail, 'Por calcular') !== false && strpos($mail, 'Por apurar') !== false, 'mail false-zero guard');
$assert(strpos($exports, 'ESTIMATIVA RECUPERADA') !== false && strpos($exports, 'POR CALCULAR') !== false, 'export false-zero guard');
$assert(strpos($validator, 'known_mobility_reporting_recovery_9al_ok') !== false, 'package validator');
$assert(strpos($phase, '`SOURCE_RELEASE` is `2.2.555` and `STABLE_RELEASE` is `2.2.556`') !== false, 'phase identity');
if ($fail) { fwrite(STDERR, '9AL FAIL: ' . implode(', ', $fail) . PHP_EOL); exit(1); }
echo 'KNOWN MOBILITY REPORTING RECOVERY 9AL CONTRACT OK | release=>=2.2.556 | known-km=fallback | costs=canonical-estimate | partial=explicit'.PHP_EOL;
