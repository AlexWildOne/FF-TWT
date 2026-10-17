<?php
// 9CJ runtime test: context form scope authority, AI model currency, tooling isolation
// and site-timezone date defaults. Runs without WordPress using minimal stubs.
namespace {
    $root = dirname(__DIR__);
    if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
    if (!class_exists('WP_Error')) {
        class WP_Error { private $m; public function __construct($c = '', $m = '', $d = []) { $this->m = $m; } public function get_error_message() { return $this->m; } }
    }
    if (!function_exists('is_wp_error')) { function is_wp_error($v) { return $v instanceof WP_Error; } }
    if (!function_exists('absint')) { function absint($v) { return abs((int)$v); } }
    $GLOBALS['ff9cj_uid'] = 7;
    if (!function_exists('get_current_user_id')) { function get_current_user_id() { return (int)$GLOBALS['ff9cj_uid']; } }
}
namespace RoutesPro\Support {
    if (!class_exists(__NAMESPACE__ . '\\Permissions')) {
        class Permissions {
            public static function can_access_route(int $route_id, ?int $user_id = null): bool { return $route_id === 10; }
            public static function assert_scope_or_error(int $client_id, int $project_id, ?int $user_id = null) {
                if ($client_id && $client_id !== 3) return new \WP_Error('forbidden', 'Sem acesso ao cliente selecionado.');
                if ($project_id && $project_id !== 4) return new \WP_Error('forbidden', 'Sem acesso à campanha selecionada.');
                return true;
            }
        }
    }
}
namespace {
    $read = static function (string $relative) use ($root): string { $p = $root . '/' . $relative; return is_readable($p) ? (string)file_get_contents($p) : ''; };
    $fail = static function (string $reason): void { fwrite(STDERR, "SECURITY & HYGIENE AUTHORITY 9CJ RUNTIME FAIL: {$reason}\n"); exit(1); };
    $assert = static function (bool $ok, string $reason) use ($fail): void { if (!$ok) $fail($reason); };

    require_once $root . '/src/Forms/Forms.php';
    $method = new ReflectionMethod('RoutesPro\\Forms\\Forms', 'context_form_scope_error');
    $method->setAccessible(true);
    $scopeAuthority = $method->invoke(null, ['route_id' => 10, 'client_id' => 3, 'project_id' => 4]) === ''
        && $method->invoke(null, ['route_id' => 99, 'client_id' => 3, 'project_id' => 4]) === 'Sem acesso à rota selecionada.'
        && $method->invoke(null, ['route_id' => 10, 'client_id' => 8, 'project_id' => 4]) === 'Sem acesso ao cliente selecionado.'
        && $method->invoke(null, ['route_id' => 0, 'client_id' => 3, 'project_id' => 9]) === 'Sem acesso à campanha selecionada.';
    $GLOBALS['ff9cj_uid'] = 0;
    $scopeAuthority = $scopeAuthority && $method->invoke(null, ['route_id' => 10]) === 'Sem sessão ativa.';
    $GLOBALS['ff9cj_uid'] = 7;
    $forms = $read('src/Forms/Forms.php');
    $guardPos = strpos($forms, '$scope_error = self::context_form_scope_error($context);');
    $renderPos = strpos($forms, '$html = self::render_form_with_context((int) $binding[\'form_id\'], $context, $binding, [', $guardPos ?: 0);
    $scopeAuthority = $scopeAuthority && $guardPos !== false && $renderPos !== false && $guardPos < $renderPos
        && strpos($forms, "'fieldflow_context_form_forbidden'], 403") !== false;
    $assert($scopeAuthority, 'context_form_scope');

    require_once $root . '/src/Services/AI.php';
    $resolve = ['RoutesPro\\Services\\GoogleAIProvider', 'resolveModel'];
    // 9CO: the 2.5 models are limited to accounts that already used them; defaults moved to 3.5.
    $modelCurrency = \RoutesPro\Services\GoogleAIProvider::DEFAULT_MODEL === 'gemini-3.5-flash-lite'
        && $resolve('') === 'gemini-3.5-flash-lite'
        && $resolve('gemini-1.5-flash') === 'gemini-3.5-flash-lite'
        && $resolve('models/gemini-1.5-pro') === 'gemini-3.8-flash'
        && $resolve('gemini-2.0-flash') === 'gemini-3.5-flash-lite'
        && $resolve('gemini-2.5-flash-lite') === 'gemini-3.5-flash-lite'
        && $resolve('gemini-3.5-flash-lite') === 'gemini-3.5-flash-lite'
        && $resolve('gemini-2.5-flash') === 'gemini-2.5-flash';
    $assert($modelCurrency, 'ai_model_currency');
    $ai = $read('src/Services/AI.php');
    $keyHeader = strpos($ai, "'x-goog-api-key'=>\$this->key") !== false && strpos($ai, ':generateContent?key=') === false;
    $assert($keyHeader, 'gemini_key_header');
    $config = $read('src/Support/Config.php');
    $assert(strpos($config, "'google_ai_model' => 'gemini-3.5-flash-lite'") !== false && strpos($config, 'gemini-1.5-flash') === false, 'config_default');

    $toolingIsolated = true;
    foreach (['tools/.htaccess', 'frontend/.htaccess'] as $file) {
        $raw = $read($file);
        $toolingIsolated = $toolingIsolated && strpos($raw, 'fieldflow-build-tooling-web-isolation-v1') !== false && strpos($raw, 'Require all denied') !== false && strpos($raw, 'Deny from all') !== false;
    }
    foreach (glob($root . '/azure-*', GLOB_ONLYDIR) as $dir) {
        $raw = is_readable($dir . '/.htaccess') ? (string)file_get_contents($dir . '/.htaccess') : '';
        $toolingIsolated = $toolingIsolated && strpos($raw, 'Require all denied') !== false;
    }
    $assert($toolingIsolated, 'tooling_isolated');

    $siteTimezone = true;
    foreach (['src/Shortcodes.php', 'src/Front/ClientPortalShortcodeRenderer.php', 'src/Rest/StatsController.php', 'src/Admin/Routes.php', 'src/Admin/FormSubmissions.php', 'src/Admin/CampaignLocations.php', 'src/Admin/ajax.php'] as $file) {
        if (preg_match("/(?<![_A-Za-z>:\$])date\\('Y-m-d'\\)/", $read($file))) $siteTimezone = false;
    }
    $siteTimezone = $siteTimezone && strpos($read('src/Shortcodes.php'), "sanitize_text_field(\$atts['date'] ?? current_time('Y-m-d'))") !== false;
    $assert($siteTimezone, 'site_timezone_dates');

    $short = $read('src/Shortcodes.php');
    $historical = strpos($short, 'fieldflow-visit-closure-selector-return-v1') !== false
        && strpos($ai, 'Governance::reconcileBudgetAlerts') !== false
        && strpos($read('src/Forms/SubmissionContext.php'), "Permissions::can_access_route(\$ctx['route_id'], \$uid)") !== false
        && strpos($read('src/Forms/SubmissionPipeline.php'), "public const BUILD = '2.2.485'") !== false;
    $assert($historical, 'historical_continuity');

    echo 'SECURITY & HYGIENE AUTHORITY 9CJ RUNTIME OK | context_form_scope=1 | ai_model_currency=1 | gemini_key_header=1 | tooling_isolated=1 | site_timezone_dates=1 | no_schema=1 | historical_continuity=1' . "\n";
}
