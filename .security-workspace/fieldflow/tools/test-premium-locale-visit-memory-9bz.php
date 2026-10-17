<?php
$root = dirname(__DIR__);
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower((string)$key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key) ?: '';
    }
}
require_once $root . '/src/Forms/DailySubmissionService.php';

use RoutesPro\Forms\DailySubmissionService;

$schema = ['questions'=>[
    ['key'=>'notes','type'=>'textarea'],
    ['key'=>'status_choice','type'=>'select'],
    ['key'=>'fronts','type'=>'product_matrix'],
    ['key'=>'photo','type'=>'image_upload'],
    ['key'=>'categorized_photo','type'=>'categorized_image_upload'],
    ['key'=>'file','type'=>'file_upload'],
    ['key'=>'order','type'=>'order_suggestion'],
    ['key'=>'brief','type'=>'briefing_block'],
]];
$input = [
    'notes'=>'última nota',
    'status_choice'=>'Sim',
    'fronts'=>[['ean'=>'123','before'=>3,'after'=>5]],
    'photo'=>['https://example.test/photo.jpg'],
    'categorized_photo'=>['main'=>['https://example.test/cat.jpg']],
    'file'=>['https://example.test/file.pdf'],
    'order'=>[['ean'=>'123','qty'=>4]],
    'brief'=>'briefing',
];
$prefill = DailySubmissionService::new_visit_autofill_prefill($input, $schema);
$checks = [
    'normal_answers_prefilled' => ($prefill['notes'] ?? null) === 'última nota' && ($prefill['status_choice'] ?? null) === 'Sim',
    'fronts_prefilled' => isset($prefill['fronts']) && is_array($prefill['fronts']),
    'photos_excluded' => !isset($prefill['photo']) && !isset($prefill['categorized_photo']),
    'files_excluded' => !isset($prefill['file']),
    'order_excluded' => !isset($prefill['order']),
    'briefing_excluded' => !isset($prefill['brief']),
];
$react = (string)file_get_contents($root . '/frontend/src/react-front/locale-switcher.ts');
$i18n = (string)file_get_contents($root . '/assets/fieldflow-i18n.js');
$css = (string)file_get_contents($root . '/assets/fieldflow-i18n.css');
$phase6 = (string)file_get_contents($root . '/src/Cloud/Phase6StableRelease.php');
$gate = (string)file_get_contents($root . '/src/Support/ReleaseGate.php');
$validator = (string)file_get_contents($root . '/src/Support/ReleasePackageValidator.php');
$checks += [
    'react_typescript_globe' => strpos($react,'fieldflow-react-locale-switcher-v1') !== false && strpos($react,'runtime.setLocale') !== false && strpos($css,'.ff-language-globe3d') !== false,
    'translation_coverage_guard' => strpos($i18n,'fieldflow-i18n-operational-coverage-v2') !== false && strpos($i18n,'data-fieldflow-i18n-user-content') !== false,
    'successor_continuity' => strpos($phase6,"public const SOURCE_RELEASE = '2.2.596'") !== false && strpos($phase6,"public const STABLE_RELEASE = '2.2.597'") !== false,
    'gate_validator_parity' => strpos($gate,'checkPremiumLocaleVisitMemory9BZContract') !== false && strpos($validator,'premium_locale_visit_memory_9bz_ok') !== false,
];
$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR, "PREMIUM LOCALE & VISIT MEMORY CONTINUITY 9BZ RUNTIME FAILED | " . implode(',', $failed) . "\n");
    exit(1);
}
echo "PREMIUM LOCALE & VISIT MEMORY CONTINUITY 9BZ RUNTIME OK | normal_answers_prefilled=1 fronts_prefilled=1 photos_excluded=1 files_excluded=1 order_excluded=1 briefing_excluded=1 react_typescript_globe=1 translation_coverage_guard=1 successor_continuity=1 gate_validator_parity=1\n";
