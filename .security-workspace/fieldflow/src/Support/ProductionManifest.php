<?php
namespace RoutesPro\Support;

if (!defined('ABSPATH')) exit;

/**
 * Runtime verifier for the build-time production-manifest.json.
 * The manifest is evidence of release consistency, not a replacement for an
 * externally stored package signature/checksum.
 */
final class ProductionManifest {
    public const FILE = 'production-manifest.json';
    public const CACHE_TRANSIENT = 'fieldflow_production_manifest_verify_cache';
    public const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

    /**
     * Manifest verification hashes every file in the release. Routine health
     * views reuse a short cache keyed by runtime version and manifest metadata,
     * while Release Gate and certification actions explicitly force a fresh scan.
     */
    private static ?array $requestCache = null;

    public static function verify(bool $fresh = false): array {
        if (!$fresh && self::$requestCache !== null) return self::$requestCache;

        $root = defined('ROUTESPRO_PATH') ? rtrim((string)ROUTESPRO_PATH, '/\\') . DIRECTORY_SEPARATOR : '';
        $manifestPath = $root !== '' ? $root . self::FILE : '';
        $runtimeVersion = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $manifestMtime = $manifestPath !== '' && is_file($manifestPath) ? (int)@filemtime($manifestPath) : 0;
        $manifestSize = $manifestPath !== '' && is_file($manifestPath) ? (int)@filesize($manifestPath) : 0;

        if (!$fresh) {
            $cached = get_transient(self::CACHE_TRANSIENT);
            if (is_array($cached)
                && (string)($cached['_runtime_version'] ?? '') === $runtimeVersion
                && (int)($cached['_manifest_mtime'] ?? -1) === $manifestMtime
                && (int)($cached['_manifest_size'] ?? -1) === $manifestSize
                && is_array($cached['result'] ?? null)) {
                return self::$requestCache = $cached['result'];
            }
        }

        $result = self::verifyFresh();
        self::$requestCache = $result;
        if ($manifestPath !== '') {
            set_transient(self::CACHE_TRANSIENT, [
                '_runtime_version'=>$runtimeVersion,
                '_manifest_mtime'=>$manifestMtime,
                '_manifest_size'=>$manifestSize,
                'result'=>$result,
            ], self::CACHE_TTL);
        }
        return $result;
    }

    public static function resetRequestCache(): void {
        self::$requestCache = null;
    }

    public static function invalidateCache(): void {
        self::$requestCache = null;
        delete_transient(self::CACHE_TRANSIENT);
    }

    private static function verifyFresh(): array {
        $root = defined('ROUTESPRO_PATH') ? rtrim((string)ROUTESPRO_PATH, '/\\') . DIRECTORY_SEPARATOR : '';
        $result = [
            'ok'=>false,
            'manifest_found'=>false,
            'manifest_version'=>'',
            'runtime_version'=>defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '',
            'expected_file_count'=>0,
            'actual_file_count'=>0,
            'expected_aggregate_sha256'=>'',
            'actual_aggregate_sha256'=>'',
            'key_file_mismatches'=>[],
            'manifest_file_mismatches'=>[],
            'unexpected_symlinks'=>[],
            'error'=>'',
        ];
        if ($root === '' || !is_dir($root)) {
            $result['error'] = 'Plugin root indisponível.';
            return $result;
        }
        $manifestPath = $root . self::FILE;
        if (!is_readable($manifestPath)) {
            $result['error'] = 'production-manifest.json em falta ou ilegível.';
            return $result;
        }
        $result['manifest_found'] = true;
        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            $result['error'] = 'production-manifest.json não contém JSON válido.';
            return $result;
        }

        $result['manifest_version'] = sanitize_text_field((string)($manifest['version'] ?? ''));
        $result['expected_file_count'] = max(0, (int)($manifest['file_count'] ?? 0));
        $result['expected_aggregate_sha256'] = strtolower(trim((string)($manifest['aggregate_sha256'] ?? '')));

        $scan = self::scan($root);
        $result['actual_file_count'] = count($scan['files']);
        $result['actual_aggregate_sha256'] = $scan['aggregate_sha256'];
        $result['unexpected_symlinks'] = $scan['symlinks'];

        // New 2.2.320 manifests include every file hash. Certified releases use
        // an associative path => metadata map. A short-lived 2.2.394/2.2.395
        // build pipeline emitted a list of {path,sha256,bytes}; normalise both
        // shapes so integrity diagnostics remain recoverable.
        $manifestFilesRaw = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
        $manifestFiles = [];
        foreach ($manifestFilesRaw as $manifestKey => $manifestMeta) {
            if (!is_array($manifestMeta)) continue;
            $relative = is_string($manifestKey)
                ? self::normalizeRelative($manifestKey)
                : self::normalizeRelative((string)($manifestMeta['path'] ?? ''));
            if ($relative === '' || $relative === self::FILE) continue;
            $manifestFiles[$relative] = [
                'sha256'=>strtolower((string)($manifestMeta['sha256'] ?? '')),
                'bytes'=>(int)($manifestMeta['bytes'] ?? -1),
            ];
        }
        $keyFiles = is_array($manifest['key_files'] ?? null) ? $manifest['key_files'] : [];
        foreach ($keyFiles as $relative => $expected) {
            // Current manifests may store key_files as a simple list of paths.
            // Older manifests may store an associative path => metadata map.
            if (is_int($relative) && is_string($expected)) {
                $relative = self::normalizeRelative($expected);
                $expected = is_array($manifestFiles[$relative] ?? null) ? $manifestFiles[$relative] : null;
            } else {
                $relative = self::normalizeRelative((string)$relative);
            }
            if ($relative === '' || $relative === self::FILE) continue;
            $actual = $scan['files'][$relative] ?? null;
            if (!$actual) {
                $result['key_file_mismatches'][] = $relative;
                continue;
            }
            // For very old list-only manifests, existence plus the aggregate is the
            // strongest compatible check available. New manifests validate metadata.
            if (!is_array($expected)) continue;
            if (strtolower((string)($expected['sha256'] ?? '')) !== $actual['sha256'] || (int)($expected['bytes'] ?? -1) !== $actual['bytes']) {
                $result['key_file_mismatches'][] = $relative;
            }
        }

        if ($manifestFiles) {
            foreach ($manifestFiles as $relative => $expected) {
                $relative = self::normalizeRelative((string)$relative);
                if ($relative === '' || $relative === self::FILE || !is_array($expected)) continue;
                $actual = $scan['files'][$relative] ?? null;
                if (!$actual || strtolower((string)($expected['sha256'] ?? '')) !== $actual['sha256'] || (int)($expected['bytes'] ?? -1) !== $actual['bytes']) {
                    $result['manifest_file_mismatches'][] = $relative;
                }
            }
            foreach ($scan['files'] as $relative => $_actual) {
                if (!isset($manifestFiles[$relative])) $result['manifest_file_mismatches'][] = $relative;
            }
            $result['manifest_file_mismatches'] = array_values(array_unique($result['manifest_file_mismatches']));
        }

        $hashOk = preg_match('/^[a-f0-9]{64}$/', $result['expected_aggregate_sha256'])
            && hash_equals($result['expected_aggregate_sha256'], $result['actual_aggregate_sha256']);
        $versionOk = $result['runtime_version'] !== '' && hash_equals($result['runtime_version'], $result['manifest_version']);
        $countOk = $result['expected_file_count'] === $result['actual_file_count'];
        $result['ok'] = $versionOk && $countOk && $hashOk
            && !$result['key_file_mismatches'] && !$result['manifest_file_mismatches'] && !$result['unexpected_symlinks'];
        if (!$result['ok'] && $result['error'] === '') $result['error'] = 'A instalação não corresponde integralmente ao manifesto da release.';
        return $result;
    }

    private static function scan(string $root): array {
        $files = [];
        $symlinks = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $path = (string)$file->getPathname();
            $relative = self::normalizeRelative(substr($path, strlen($root)));
            if ($relative === '' || $relative === self::FILE) continue;
            if ($file->isLink()) {
                $symlinks[] = $relative;
                continue;
            }
            if (!$file->isFile() || !is_readable($path)) continue;
            $hash = hash_file('sha256', $path);
            if (!is_string($hash) || $hash === '') continue;
            $files[$relative] = ['sha256'=>strtolower($hash),'bytes'=>(int)$file->getSize()];
        }
        ksort($files, SORT_STRING);
        $aggregate = '';
        foreach ($files as $relative => $meta) {
            $aggregate .= $relative . "\t" . $meta['sha256'] . "\t" . $meta['bytes'] . "\n";
        }
        sort($symlinks, SORT_STRING);
        return ['files'=>$files,'aggregate_sha256'=>hash('sha256', $aggregate),'symlinks'=>$symlinks];
    }

    private static function normalizeRelative(string $relative): string {
        $relative = str_replace('\\', '/', ltrim($relative, '/\\'));
        if ($relative === '' || strpos($relative, '../') !== false) return '';
        return $relative;
    }
}
