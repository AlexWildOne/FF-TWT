<?php
namespace RoutesPro\Security;

if (!defined('ABSPATH')) exit;

/**
 * FieldFlow 2.2.304 media registry foundation.
 *
 * Observe-only and fail-open by design:
 * - never moves, deletes or rewrites media;
 * - never changes existing public URLs;
 * - never blocks an upload/submission if registry persistence fails;
 * - does not backfill historical media automatically.
 */
final class MediaRegistry {
    public const VERSION = '1.0';
    public const MODE = 'observe';

    private static $tableReady = null;

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_media_registry';
    }

    public static function tableReady(): bool {
        if (self::$tableReady !== null) return (bool) self::$tableReady;
        global $wpdb;
        $table = self::table();
        self::$tableReady = ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
        return (bool) self::$tableReady;
    }

    public static function registerAttachment(int $attachmentId, array $context = []): bool {
        if ($attachmentId <= 0) return false;
        $url = (string) wp_get_attachment_url($attachmentId);
        if ($url === '') return false;
        $path = (string) get_attached_file($attachmentId);
        $context['attachment_id'] = $attachmentId;
        if ($path !== '') $context['storage_path'] = $path;
        return self::registerUrl($url, $context);
    }

    public static function registerSubmissionAnswers(int $submissionId, array $answers, array $context = [], int $ownerUserId = 0): int {
        if ($submissionId <= 0 || !$answers) return 0;
        $base = array_merge($context, [
            'submission_id' => $submissionId,
            'owner_user_id' => $ownerUserId > 0 ? $ownerUserId : get_current_user_id(),
            'source' => 'form_submission',
        ]);
        $registered = 0;
        foreach ($answers as $questionKey => $answer) {
            $value = is_array($answer) && array_key_exists('value', $answer) ? $answer['value'] : $answer;
            $urls = self::extractUrls($value);
            foreach ($urls as $url) {
                $rowContext = $base;
                $rowContext['question_key'] = sanitize_key((string)$questionKey);
                if (self::registerUrl($url, $rowContext)) $registered++;
            }
        }
        return $registered;
    }

    public static function registerUrl(string $url, array $context = []): bool {
        try {
            if (!self::tableReady()) return false;
            $url = esc_url_raw(trim($url));
            if ($url === '' || !self::isLocalUploadUrl($url)) return false;

            global $wpdb;
            $table = self::table();
            $hash = hash('sha256', $url);
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE url_hash=%s LIMIT 1", $hash), ARRAY_A);

            $data = self::normaliseContext($context);
            $data['url_hash'] = $hash;
            $data['public_url'] = $url;
            if ($data['storage_path'] === '') $data['storage_path'] = self::pathFromUploadUrl($url);
            $now = current_time('mysql', true);

            if ($existing) {
                $update = [
                    'last_seen_at' => $now,
                    'updated_at' => $now,
                ];
                foreach (['attachment_id','owner_user_id','client_id','project_id','route_id','route_stop_id','location_id','submission_id'] as $key) {
                    if ((int)($existing[$key] ?? 0) <= 0 && (int)$data[$key] > 0) $update[$key] = (int)$data[$key];
                }
                foreach (['storage_path','question_key','category','source'] as $key) {
                    if ((string)($existing[$key] ?? '') === '' && (string)$data[$key] !== '') $update[$key] = (string)$data[$key];
                }
                $result = $wpdb->update($table, $update, ['id' => (int)$existing['id']]);
                return $result !== false;
            }

            $data['protection_status'] = 'observed';
            $data['first_seen_at'] = $now;
            $data['last_seen_at'] = $now;
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            return $wpdb->insert($table, $data) !== false;
        } catch (\Throwable $e) {
            // Registry is intentionally non-blocking. Operational media remains on the legacy path.
            return false;
        }
    }

    public static function diagnostics(): array {
        if (!self::tableReady()) {
            return ['version'=>self::VERSION,'mode'=>self::MODE,'table_ready'=>false,'total'=>0,'attachments'=>0,'direct_files'=>0,'linked_submissions'=>0,'protected'=>0];
        }
        global $wpdb;
        $table = self::table();
        return [
            'version' => self::VERSION,
            'mode' => self::MODE,
            'table_ready' => true,
            'total' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
            'attachments' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE attachment_id > 0"),
            'direct_files' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE attachment_id = 0"),
            'linked_submissions' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE submission_id > 0"),
            'protected' => (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE protection_status=%s", 'protected')),
        ];
    }

    private static function normaliseContext(array $context): array {
        return [
            'attachment_id' => absint($context['attachment_id'] ?? 0),
            'storage_path' => self::cleanPath((string)($context['storage_path'] ?? '')),
            'owner_user_id' => absint($context['owner_user_id'] ?? get_current_user_id()),
            'client_id' => absint($context['client_id'] ?? 0),
            'project_id' => absint($context['project_id'] ?? 0),
            'route_id' => absint($context['route_id'] ?? 0),
            'route_stop_id' => absint($context['route_stop_id'] ?? 0),
            'location_id' => absint($context['location_id'] ?? 0),
            'submission_id' => absint($context['submission_id'] ?? 0),
            'question_key' => sanitize_key((string)($context['question_key'] ?? '')),
            'category' => sanitize_text_field((string)($context['category'] ?? '')),
            'source' => sanitize_key((string)($context['source'] ?? 'unknown')),
        ];
    }

    private static function extractUrls($value): array {
        $out = [];
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if ($key === 'url' && is_scalar($item)) {
                    $candidate = esc_url_raw((string)$item);
                    if ($candidate !== '') $out[$candidate] = $candidate;
                } else {
                    foreach (self::extractUrls($item) as $url) $out[$url] = $url;
                }
            }
        } elseif (is_string($value)) {
            $trimmed = trim($value);
            if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
                $candidate = esc_url_raw($trimmed);
                if ($candidate !== '') $out[$candidate] = $candidate;
            }
        }
        return array_values($out);
    }

    private static function isLocalUploadUrl(string $url): bool {
        $uploads = wp_upload_dir();
        $baseurl = untrailingslashit((string)($uploads['baseurl'] ?? ''));
        return $baseurl !== '' && (strpos($url, $baseurl . '/') === 0 || $url === $baseurl);
    }

    private static function pathFromUploadUrl(string $url): string {
        $uploads = wp_upload_dir();
        $baseurl = untrailingslashit((string)($uploads['baseurl'] ?? ''));
        $basedir = untrailingslashit((string)($uploads['basedir'] ?? ''));
        if ($baseurl === '' || $basedir === '' || strpos($url, $baseurl) !== 0) return '';
        $relative = ltrim(rawurldecode(substr($url, strlen($baseurl))), '/');
        if ($relative === '' || strpos($relative, '../') !== false || strpos($relative, '..\\') !== false) return '';
        return self::cleanPath($basedir . '/' . $relative);
    }

    private static function cleanPath(string $path): string {
        $path = trim($path);
        if ($path === '') return '';
        return wp_normalize_path($path);
    }
}
