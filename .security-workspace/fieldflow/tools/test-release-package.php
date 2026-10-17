<?php
/** Validate the final candidate ZIP with the production ReleasePackageValidator.
 * Usage: php tools/test-release-package.php /absolute/path/candidate.zip [expected-sha256]
 */
if (PHP_SAPI !== 'cli') exit(1);
if ($argc < 2 || $argc > 3) {
    fwrite(STDERR, "Usage: php tools/test-release-package.php candidate.zip [expected-sha256]\n");
    exit(2);
}
$root = dirname(__DIR__);
$plugin = (string)file_get_contents($root . '/fieldflow.php');
if (!preg_match("/^define\\('FIELDFLOW_VERSION',\\s*'(\\d+\\.\\d+\\.\\d+)'\\);/m", $plugin, $match)) {
    fwrite(STDERR, "Cannot resolve the active plugin version.\n");
    exit(1);
}
$expectedVersion = $match[1];
$zip = realpath($argv[1]);
if ($zip === false || !is_file($zip) || !is_readable($zip)) {
    fwrite(STDERR, "Candidate ZIP is not readable.\n");
    exit(1);
}
if (isset($argv[2]) && (!preg_match('/^[a-f0-9]{64}$/i', $argv[2])
    || !hash_equals(strtolower($argv[2]), (string)hash_file('sha256', $zip)))) {
    fwrite(STDERR, "Candidate ZIP SHA-256 does not match the supplied digest.\n");
    exit(1);
}
define('ABSPATH', $root . '/');
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string)$value)));
    }
}
require_once $root . '/src/Support/ReleasePackageValidator.php';
$result = \RoutesPro\Support\ReleasePackageValidator::validate($zip);
$failed = [];
foreach ($result as $key => $value) {
    if (substr($key, -3) === '_ok' && $value !== true) $failed[] = $key;
}
$valid = ($result['ok'] ?? false) === true
    && ($result['version'] ?? '') === $expectedVersion
    && ($result['stable_tag'] ?? '') === $expectedVersion
    && ($result['manifest_version'] ?? '') === $expectedVersion
    && !$failed;
echo json_encode([
    'ok'=>$valid,
    'expected_version'=>$expectedVersion,
    'package_version'=>$result['version'] ?? '',
    'sha256'=>$result['sha256'] ?? '',
    'archive_engine'=>$result['archive_engine'] ?? '',
    'file_count'=>$result['file_count'] ?? 0,
    'failed_checks'=>$failed,
    'error'=>$result['error'] ?? '',
    'warnings'=>$result['warnings'] ?? [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($valid ? 0 : 1);
