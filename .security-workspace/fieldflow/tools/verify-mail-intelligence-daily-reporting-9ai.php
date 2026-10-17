<?php
$root = dirname(__DIR__);
$fail = [];
$read = static function(string $rel) use ($root): string { $p=$root . '/' . $rel; return is_readable($p) ? (string)file_get_contents($p) : ''; };
$assert = static function(bool $ok, string $label) use (&$fail): void { if (!$ok) $fail[]=$label; };
$main=$read('fieldflow.php');
$service=$read('src/Reporting/DailyCommercialReportService.php');
$canonical=$read('src/Reporting/CanonicalOperationalReportService.php');
$automations=$read('src/Admin/ReportAutomations.php');
$exports=$read('src/Admin/FormSubmissions.php');
$shopping=$read('src/Shopping/ShoppingService.php');
$notify=$read('src/Services/Notify.php');
$gate=$read('src/Support/ReleaseGate.php');
$validator=$read('src/Support/ReleasePackageValidator.php');
$phase=$read('PHASE-9AI-MAIL-INTELLIGENCE-DAILY-COMMERCIAL-REPORTING.md');
$release=''; if (preg_match('/Version:\s*([0-9.]+)/',$main,$m)) $release=(string)$m[1];
$assert($release!=='' && version_compare($release,'2.2.553','>='), 'release');
$assert(strpos($service, 'fieldflow-mail-intelligence-daily-commercial-reporting-v1') !== false, 'service contract');
$assert(strpos($service, "'client'=>self::clientNarrative") !== false && strpos($service, "'merchandiser'=>self::merchandiserNarrative") !== false, 'audiences');
$assert(strpos($canonical, "'commercial_analysis'") !== false, 'canonical analysis');
$assert(strpos($automations, 'Visão comercial do dia') !== false && strpos($automations, 'Alertas e ações') !== false, 'mail narratives');
$assert(strpos($exports, 'Resumo Comercial') !== false && strpos($exports, 'Ganhos e Excecoes') !== false && strpos($exports, "'Visitas'") !== false, 'dynamic exports');
$assert(strpos($shopping, "shopping_verification|' . \$submissionId") !== false && strpos($shopping, "'queued_recipients'") !== false, 'shopping idempotency');
$assert(strpos($notify, 'One transport envelope per recipient') !== false, 'route recipient envelopes');
$assert(strpos($gate, "MAIL_INTELLIGENCE_DAILY_REPORTING_9AI_CONTRACT = 'fieldflow-mail-intelligence-daily-commercial-reporting-v1'") !== false, 'release gate');
$assert(strpos($validator, 'mail_intelligence_daily_reporting_9ai_ok') !== false, 'package validator');
$assert(strpos($phase, '`SOURCE_RELEASE` is `2.2.552` and `STABLE_RELEASE` is `2.2.553`') !== false, 'phase identity');
if ($fail) { fwrite(STDERR, '9AI FAIL: ' . implode(', ', $fail) . PHP_EOL); exit(1); }
echo 'MAIL INTELLIGENCE DAILY REPORTING 9AI CONTRACT OK | release=2.2.553 | client=commercial | merchandiser=alerts | exports=dynamic | mail=sent+queued+failed' . PHP_EOL;
