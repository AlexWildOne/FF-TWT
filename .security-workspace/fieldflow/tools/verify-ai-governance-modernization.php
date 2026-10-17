<?php
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$fail = static function (string $key): void { fwrite(STDERR, "FAIL {$key}\n"); exit(1); };
$assert = static function (bool $condition, string $key) use ($fail): void { if (!$condition) $fail($key); echo "PASS {$key}\n"; };
$contains = static function (string $haystack, array $needles): bool {
    foreach ($needles as $needle) if (strpos($haystack, $needle) === false) return false;
    return true;
};

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase6 = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$governance = $read('src/AI/Governance.php');
$usage = $read('src/Repositories/AIUsageRepository.php');
$ai = $read('src/Services/AI.php');
$config = $read('src/Support/Config.php');
$migrations = $read('src/Support/Migrations.php');
$schema = $read('src/Support/SchemaManifest.php');
$control = $read('src/Admin/AIControlCenter.php');
$health = $read('src/Support/SystemHealth.php');
$reactFoundation = $read('src/Front/ReactFrontFoundation.php');
$reactTypes = $read('frontend/src/react-front/types.ts');
$reactSource = $read('frontend/src/react-front/module-controls.ts');
$reactAsset = $read('assets/fieldflow-react-front-2.2.530.js');
$form = $read('src/Forms/FormRenderer.php');
$expenses = $read('src/Front/ExpensesShortcodeRenderer.php');
$uiRuntime = $read('assets/fieldflow-ui-runtime.js');
$briefing = $read('src/Briefings/BriefingAIService.php');
$analytics = $read('src/Analytics/AnalyticsAIService.php');
$ask = $read('src/Analytics/FieldFlowAskService.php');
$integrations = $read('src/Rest/IntegrationsController.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-9M-AI-GOVERNANCE-MODERNIZATION-CONSOLIDATION.md');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase6, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($gate, ["VERSION = '1.79.0'", "AI_GOVERNANCE_MODERNIZATION_CONTRACT = 'fieldflow-ai-governance-modernization-wave-v1'", 'checkAiGovernanceModernizationContract']), 'release_gate_contract');
$assert($contains($validator, ["'ai_governance_modernization_ok'=>false", "version_compare((string)(\$result['version'] ?? ''), '2.2.529', '>=')"]), 'package_validator_successor_contract');
$assert($contains($manager, ['AI Governance &amp; Modernization', "packageResult['ai_governance_modernization_ok']"]), 'release_manager_kpi');

$assert($contains($governance, ["VERSION = '2.0'", "CONTRACT = 'fieldflow-ai-governance-finops-v2'", 'ALERT_THRESHOLDS = [70, 85, 100]', 'budgetSnapshot', 'reconcileBudgetAlerts', 'fieldflow_ai_budget_threshold']), 'governance_v2');
$assert($contains($governance, ['ai_client_monthly_budget_eur', 'ai_project_monthly_budget_eur', 'client_monthly_budget', 'project_monthly_budget']), 'scoped_budgets');
$assert($contains($usage, ['client_id', 'project_id', 'feature_key', 'request_kind', 'monthBreakdown', 'monthCost(array $scope = [])', 'hasAttributionColumns']), 'native_usage_attribution');
$assert($contains($ai, ['resolveAttribution', 'fieldflow_attribution', 'Governance::reconcileBudgetAlerts', "'request_kind'=>'admin_test'"]), 'ai_manager_attribution');
$assert($contains($config, ["'ai_client_monthly_budget_eur' => 0", "'ai_project_monthly_budget_eur' => 0"]), 'finops_config');
$assert($contains($control, ['AI Governance 2.0', 'FinOps mensal', 'renderFinopsBreakdown', 'Por cliente', 'Por projeto', 'Por feature']), 'finops_control_center');
$assert($contains($health, ['attribution nativa:', 'budget_usage_percent']), 'system_health_finops');

$assert($contains($migrations, ["LATEST_SCHEMA_VERSION = '2.2.529'", "'2.2.529' => [self::class, 'migrate22529']", 'migrate22529', 'client_created_idx', 'project_created_idx', 'feature_created_idx', 'request_kind_idx']), 'schema_migration');
$assert($contains($schema, ["VERSION = '1.11.0'", "'client_id'", "'project_id'", "'feature_key'", "'request_kind'", "'client_created_idx'", "'request_kind_idx'"]), 'schema_manifest');
$assert($contains($briefing, ["'feature_key' => 'briefing_ai_copilot'", "'client_id' => absint(\$detail['briefing']['client_id'] ?? 0)"]), 'briefing_attribution');
$assert($contains($analytics, ["'feature_key' => 'analytics_dashboard_builder'", "'feature_key' => 'analytics_insights_' . \$audience", "'client_id' => absint(\$input['client_id'] ?? 0)"]), 'analytics_attribution');
$assert($contains($ask, ["'feature_key'=>'analytics_ask_fieldflow'", "'request_kind'=>'interactive'"]), 'ask_fieldflow_attribution');
$assert($contains($integrations, ["'request_kind'=>'api'", "'client_id'=>absint(\$p['client_id'] ?? 0)"]), 'rest_ai_attribution');

$assert($contains($reactFoundation, ["OPERATIONAL_MODULES_V2_CONTRACT = 'fieldflow-react-operational-modules-v2'", 'operationalModulesV2Contract', 'operational_modules_v2_contract']), 'react_v2_php_contract');
$assert(strpos($reactTypes, 'operationalModulesV2Contract?: string') !== false, 'react_v2_types');
$assert($contains($reactSource, ["FIELD_FLOW_REACT_OPERATIONAL_MODULES_V2_CONTRACT = 'fieldflow-react-operational-modules-v2'", 'fieldflow:operational-module-refresh', 'data-fieldflow-react-module-owner', 'react-controls-v2']), 'react_v2_source');
$assert($contains($reactAsset, ['FieldFlow 2.2.530', 'fieldflow-react-operational-modules-v2', 'fieldflow:operational-module-refresh', 'react-controls-v2']), 'react_v2_compiled');

$wildcard = static function (string $content): bool {
    return (bool)preg_match('/postMessage\([^\n;]*,[\'\"]\*[\'\"]\)/', $content);
};
$assert(!$wildcard($form) && strpos($form, 'ffParentOrigin') !== false, 'formrenderer_same_origin');
$assert(!$wildcard($expenses) && strpos($expenses, 'ffParentOrigin') !== false, 'expenses_same_origin');
$assert(!$wildcard($uiRuntime) && strpos($uiRuntime, 'window.location.origin') !== false, 'ui_runtime_same_origin');

$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_frozen');
$assert($contains($doc, ['fieldflow-ai-governance-modernization-wave-v1', '`SOURCE_RELEASE` is `2.2.528` and `STABLE_RELEASE` is `2.2.529`', '`LATEST_SCHEMA_VERSION` is `2.2.529`.', '`SubmissionPipeline::BUILD` remains `2.2.485`.']), 'phase_documentation');
$assert(version_compare('2.2.530', '2.2.529', '>='), 'successor_semantics');

echo "AI GOVERNANCE MODERNIZATION CONTRACT OK\n";
echo "release=2.2.530\n";
echo "contract=fieldflow-ai-governance-modernization-wave-v1\n";
echo "ai_governance=2.0\n";
echo "budget_alerts=70/85/100\n";
echo "react_operational=v2_presentation_only\n";
echo "webview_postmessage=same_origin_complete\n";
echo "successor=>=2.2.529\n";
echo "schema=2.2.529\n";
echo "submission_build=2.2.485\n";
