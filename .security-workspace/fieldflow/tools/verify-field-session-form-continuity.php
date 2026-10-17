<?php
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$fail = static function (string $reason): void { fwrite(STDERR, "FAIL: {$reason}\n"); exit(1); };
$plugin = $read('fieldflow.php');
$release = preg_match("/define\\('FIELDFLOW_VERSION',\\s*'([^']+)'\\)/", $plugin, $m) ? (string)$m[1] : '';
if ($release === '' || version_compare($release, '2.2.535', '<')) $fail('release must be >= 2.2.535');
$auth = $read('src/Mobile/Auth.php');
$bridge = $read('src/Mobile/WebViewBridge.php');
$policy = $read('src/Security/SessionPolicy.php');
$form = $read('src/Forms/FormRenderer.php');
$schema = $read('src/Support/Migrations.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$markers = [
    strpos($auth, "FIELD_SESSION_FORM_CONTINUITY_CONTRACT = 'fieldflow-field-session-form-continuity-v1'") !== false,
    strpos($auth, 'REFRESH_REPLAY_TTL = 30') !== false,
    strpos($auth, 'refresh_replay_get') !== false,
    strpos($auth, 'refresh_replay_store') !== false,
    strpos($auth, "'aes-256-gcm'") !== false,
    strpos($bridge, "NATIVE_SHELL_VERSION = '1.10'") !== false,
    strpos($bridge, 'function queueNativeResume(reason)') !== false,
    strpos($bridge, "queueNativeResume('visibility-visible')") !== false,
    strpos($bridge, "queueNativeResume('pageshow')") !== false,
    (strpos($bridge, "queueNativeResume('focus')") !== false) || (strpos($bridge, "queueNativeResume('focus-after-hidden')") !== false && strpos($bridge, 'consumeNativeResumeArm()') !== false),
    strpos($policy, '!self::isPrivilegedUser($userId)') !== false,
    strpos($form, "RP_FIELD_SESSION_FORM_CONTINUITY_CONTRACT='fieldflow-field-session-form-continuity-v1'") !== false,
    strpos($form, 'function rpQueueResumeDraftForms(reason)') !== false,
    (strpos($form, "rpQueueResumeDraftForms('focus')") !== false) || (strpos($form, "rpQueueResumeDraftForms('focus-after-hidden')") !== false && strpos($form, 'rpConsumeResumeDraftArm()') !== false),
    strpos($form, 'function draftPreferredInitialStep()') !== false,
    strpos($form, 'showStep(draftPreferredInitialStep(),false);') !== false,
    strpos($form, "if (\$type === 'product_matrix'") !== false,
    strpos($form, 'data-paired-fronts-key') !== false,
    strpos($form, 'pairedKey===frontKey') !== false,
    strpos($form, "in_array(\$type, ['product_matrix', 'order_suggestion'], true)") === false,
    strpos($schema, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false,
    strpos($submission, "public const BUILD = '2.2.485'") !== false,
];
foreach ($markers as $i => $ok) if (!$ok) $fail('contract marker #' . ($i + 1));
echo 'FIELD SESSION FORM CONTINUITY CONTRACT OK | release=' . $release . ' | successor=>=2.2.535 | refresh_replay_ttl=30 | native_shell=1.10 | schema=2.2.529 | submission_build=2.2.485' . PHP_EOL;
