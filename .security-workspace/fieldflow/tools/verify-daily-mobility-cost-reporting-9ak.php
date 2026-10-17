<?php
$root = dirname(__DIR__);
$fail = [];
$read = static function(string $rel) use ($root): string { $p=$root . '/' . $rel; return is_readable($p) ? (string)file_get_contents($p) : ''; };
$assert = static function(bool $ok, string $label) use (&$fail): void { if (!$ok) $fail[]=$label; };
$main=$read('fieldflow.php');
$canonical=$read('src/Reporting/CanonicalOperationalReportService.php');
$commercial=$read('src/Reporting/DailyCommercialReportService.php');
$automations=$read('src/Admin/ReportAutomations.php');
$exports=$read('src/Admin/FormSubmissions.php');
$gate=$read('src/Support/ReleaseGate.php');
$validator=$read('src/Support/ReleasePackageValidator.php');
$phase=$read('PHASE-9AK-DAILY-MOBILITY-COST-REPORTING-CONTINUITY.md');
$release=''; if (preg_match('/Version:\s*([0-9.]+)/',$main,$m)) $release=(string)$m[1];
$assert($release!=='' && version_compare($release,'2.2.555','>='), 'release');
$assert(strpos($canonical, "'mobility' => \$mobility") !== false, 'canonical mobility envelope');
$assert(strpos($canonical, "'open_routes_excluded'") !== false && strpos($canonical, "'partial'") !== false, 'closed route and partial semantics');
$assert(strpos($commercial, "'mobility'=>") !== false && strpos($commercial, "'energy_cost_eur'") !== false && strpos($commercial, "'toll_cost_eur'") !== false, 'commercial mobility facts');
$assert(strpos($automations, 'Mobilidade e custos por dia') !== false && strpos($automations, 'TOTAL GERAL') !== false && strpos($automations, 'Km executados') !== false, 'mail daily and accumulated mobility');
$assert(strpos($exports, "'Mobilidade Diaria'") !== false && strpos($exports, 'Km executados') !== false && strpos($exports, 'Comb./energia EUR') !== false && strpos($exports, 'Portagens EUR') !== false, 'excel daily mobility');
$assert(strpos($gate, "DAILY_MOBILITY_COST_REPORTING_9AK_CONTRACT = 'fieldflow-daily-mobility-cost-reporting-v1'") !== false, 'release gate');
$assert(strpos($validator, 'daily_mobility_cost_reporting_9ak_ok') !== false, 'package validator');
$assert(strpos($phase, '`SOURCE_RELEASE` is `2.2.554` and `STABLE_RELEASE` is `2.2.555`') !== false, 'phase identity');
if ($fail) { fwrite(STDERR, '9AK FAIL: ' . implode(', ', $fail) . PHP_EOL); exit(1); }
echo 'DAILY MOBILITY COST REPORTING 9AK CONTRACT OK | release=>=2.2.555 | daily=km+energy+tolls | accumulated=total-general | source=canonical-mobility' . PHP_EOL;
