<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => ['Version: 2.2.607', "define('FIELDFLOW_VERSION', '2.2.607')", "9CJ predecessor marker: define('FIELDFLOW_VERSION', '2.2.606')"],
    'src/Forms/Forms.php' => ['fieldflow-context-form-scope-authority-v1', 'private static function context_form_scope_error(array $context): string', '$scope_error = self::context_form_scope_error($context);', "'fieldflow_context_form_forbidden'"],
    'src/Services/AI.php' => ['fieldflow-ai-model-currency-v1', "public const DEFAULT_MODEL = 'gemini-2.5-flash-lite';", 'public static function resolveModel(string $model): string', "'x-goog-api-key'=>\$this->key"],
    'src/Support/Config.php' => ["'google_ai_model' => 'gemini-2.5-flash-lite'"],
    'tools/.htaccess' => ['fieldflow-build-tooling-web-isolation-v1', 'Require all denied'],
    'frontend/.htaccess' => ['fieldflow-build-tooling-web-isolation-v1', 'Require all denied'],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.606'", "public const STABLE_RELEASE = '2.2.607'", "// public const SOURCE_RELEASE = '2.2.605'", "// public const STABLE_RELEASE = '2.2.606'"],
    'src/Support/ReleaseGate.php' => ['SECURITY_HYGIENE_AUTHORITY_9CJ_CONTRACT', 'checkSecurityHygieneAuthority9CJContract'],
    'src/Support/ReleasePackageValidator.php' => ['security_hygiene_authority_9cj_ok', 'Security & Hygiene Authority 9CJ'],
    'src/Admin/ReleaseManager.php' => ["packageResult['security_hygiene_authority_9cj_ok']", 'SCOPE + TOOLS + IA OK'],
    'PHASE-9CJ-SECURITY-HYGIENE-AUTHORITY.md' => ['fieldflow-security-hygiene-authority-v1', '`SOURCE_RELEASE` is `2.2.606` and `STABLE_RELEASE` is `2.2.607`', 'same authority as submission', 'No database schema changes', 'No FlutterFlow source modification is required'],
];
$forbidden = [
    'src/Services/AI.php' => [':generateContent?key=', "'gemini-1.5-flash')"],
    'src/Support/Config.php' => ["'google_ai_model' => 'gemini-1.5-flash'"],
];
foreach ($need as $relative => $markers) {
    $path = $root . '/' . $relative;
    $raw = is_readable($path) ? (string)file_get_contents($path) : '';
    if ($raw === '') { $errors[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $errors[] = $relative . ':' . $marker;
}
foreach ($forbidden as $relative => $markers) {
    $raw = (string)@file_get_contents($root . '/' . $relative);
    foreach ($markers as $marker) if (strpos($raw, $marker) !== false) $errors[] = $relative . ':forbidden:' . $marker;
}
if ($errors) {
    fwrite(STDERR, "SECURITY & HYGIENE AUTHORITY 9CJ CONTRACT FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}
echo "SECURITY & HYGIENE AUTHORITY 9CJ CONTRACT OK successor=>=2.2.607\n";
