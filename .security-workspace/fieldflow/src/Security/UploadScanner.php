<?php
namespace RoutesPro\Security;

use RoutesPro\Support\Config;

if (!defined('ABSPATH')) exit;

/**
 * FieldFlow upload scanner — hash-based lookup against a known-malware
 * database (VirusTotal). Deliberately narrow in scope:
 *
 * - Checks the SHA-256 of the uploaded file against VirusTotal's existing
 *   analysis database. This is a fast, single HTTP GET (no file content is
 *   ever uploaded to a third party) — it only works for malware VirusTotal
 *   has already seen before, not novel/unknown threats. That is a real and
 *   useful first layer (most real-world malicious uploads are reused
 *   samples), not a substitute for full content scanning.
 * - Fail-open by design, exactly like every other rollout in this codebase
 *   (MediaShadow, SessionPolicy, Mfa): any network error, timeout, missing
 *   API key or unexpected response is treated as "unknown", never as
 *   "malicious". A scanner outage must never block a field agent's upload.
 * - Off by default. Only runs when both `upload_scan_enabled` and
 *   `virustotal_api_key` are configured in FieldFlow > Segurança.
 */
final class UploadScanner {
    private const API_HOST = 'www.virustotal.com';
    private const MALICIOUS_THRESHOLD = 2; // avoid single-engine false positives

    public static function isEnabled(): bool {
        $opts = Config::all();
        return !empty($opts['upload_scan_enabled']) && !empty($opts['virustotal_api_key']);
    }

    /**
     * @return array{status:string,detections:int,total_engines:int,reason:string}
     *   status is one of: clean, malicious, unknown, error
     */
    public static function scanFile(string $path): array {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return self::result('error', 0, 0, 'unreadable_file');
        }
        $hash = hash_file('sha256', $path);
        if ($hash === false || $hash === '') {
            return self::result('error', 0, 0, 'hash_failed');
        }
        return self::scanByHash($hash);
    }

    public static function scanByHash(string $sha256): array {
        $sha256 = strtolower(trim($sha256));
        if (!preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            return self::result('error', 0, 0, 'invalid_hash');
        }
        $key = (string) Config::get('virustotal_api_key');
        if ($key === '') {
            return self::result('unknown', 0, 0, 'no_api_key');
        }

        try {
            $url = 'https://' . self::API_HOST . '/api/v3/files/' . $sha256;
            $res = OutboundHttp::get($url, [
                'timeout' => 5,
                'headers' => ['x-apikey' => $key],
            ], [
                'purpose' => 'upload_scan_virustotal',
                'allowed_hosts' => [self::API_HOST],
                'allowed_ports' => [443],
                'max_redirects' => 0,
                'max_timeout' => 8,
                'max_response_bytes' => 1048576,
            ]);
        } catch (\Throwable $e) {
            return self::result('error', 0, 0, 'request_exception');
        }

        if (is_wp_error($res)) {
            return self::result('error', 0, 0, 'request_error');
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code === 404) {
            // VirusTotal has never seen this exact file. That does not mean
            // it is safe — it means we have no signal either way. Fail open.
            return self::result('unknown', 0, 0, 'hash_not_indexed');
        }
        if ($code !== 200) {
            return self::result('error', 0, 0, 'unexpected_status_' . $code);
        }

        $body = json_decode((string) wp_remote_retrieve_body($res), true);
        $stats = $body['data']['attributes']['last_analysis_stats'] ?? null;
        if (!is_array($stats)) {
            return self::result('error', 0, 0, 'malformed_response');
        }

        $malicious = (int) ($stats['malicious'] ?? 0);
        $suspicious = (int) ($stats['suspicious'] ?? 0);
        $totalEngines = array_sum(array_map('intval', $stats));
        $detections = $malicious + $suspicious;

        if ($malicious >= self::MALICIOUS_THRESHOLD) {
            return self::result('malicious', $detections, $totalEngines, 'known_malware_hash');
        }
        return self::result('clean', $detections, $totalEngines, 'below_threshold');
    }

    private static function result(string $status, int $detections, int $totalEngines, string $reason): array {
        return [
            'status' => $status,
            'detections' => $detections,
            'total_engines' => $totalEngines,
            'reason' => $reason,
        ];
    }
}
