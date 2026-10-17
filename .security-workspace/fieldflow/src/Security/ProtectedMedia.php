<?php
namespace RoutesPro\Security;

if (!defined('ABSPATH')) exit;

use RoutesPro\Integrations\SecretVault;
use RoutesPro\Support\Permissions;
use WP_Error;

/** New operational uploads are encrypted before entering the public uploads tree. */
final class ProtectedMedia {
    public const SCHEMA_VERSION = 1;

    public static function registerHooks(): void {
        add_filter('wp_get_attachment_url', [self::class, 'attachmentUrl'], 20, 2);
        add_action('template_redirect', [self::class, 'serve'], -30);
    }

    public static function isEnabled(): bool {
        return SecretVault::canEncrypt();
    }

    public static function attachmentIsProtected(int $attachmentId): bool {
        if ($attachmentId <= 0) return false;
        return (bool) get_post_meta($attachmentId, '_fieldflow_protected_media', true);
    }

    public static function attachmentUrl($url, int $attachmentId): string {
        return self::attachmentIsProtected($attachmentId)
            ? add_query_arg('fieldflow_evidence', $attachmentId, home_url('/'))
            : (string)$url;
    }

    private static function context(array $context) {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $routeId = absint($context['route_id'] ?? 0);
        $stopId = absint($context['route_stop_id'] ?? 0);
        if ($stopId) {
            $actualRoute = (int)$wpdb->get_var($wpdb->prepare("SELECT route_id FROM {$px}route_stops WHERE id=%d", $stopId));
            if (!$actualRoute || ($routeId && $routeId !== $actualRoute)) return new WP_Error('evidence_scope', 'Paragem inválida.', ['status'=>403]);
            $routeId = $actualRoute;
        }
        $clientId = absint($context['client_id'] ?? 0);
        $projectId = absint($context['project_id'] ?? 0);
        if ($routeId) {
            if (!Permissions::can_edit_route($routeId)) return new WP_Error('evidence_scope', 'Sem acesso à rota.', ['status'=>403]);
            $row = $wpdb->get_row($wpdb->prepare("SELECT client_id,project_id FROM {$px}routes WHERE id=%d", $routeId), ARRAY_A);
            if (!$row) return new WP_Error('evidence_scope', 'Rota inexistente.', ['status'=>404]);
            $clientId = (int)$row['client_id'];
            $projectId = (int)$row['project_id'];
        } elseif ($projectId) {
            $actualClient = (int)$wpdb->get_var($wpdb->prepare("SELECT client_id FROM {$px}projects WHERE id=%d", $projectId));
            if (!$actualClient || ($clientId && $actualClient !== $clientId)) return new WP_Error('evidence_scope', 'Campanha inválida.', ['status'=>403]);
            $clientId = $actualClient;
        }
        if (!$routeId && ($clientId || $projectId)) {
            $error = Permissions::assert_scope_or_error($clientId, $projectId);
            if (is_wp_error($error)) return $error;
        }
        return ['owner_user_id'=>get_current_user_id(), 'route_id'=>$routeId,
            'route_stop_id'=>$stopId, 'client_id'=>$clientId, 'project_id'=>$projectId];
    }

    public static function upload(string $field, array $context = []) {
        if (!is_user_logged_in()) return new WP_Error('evidence_auth', 'Autenticação necessária.', ['status'=>401]);
        $guard = Mfa::requestError();
        if (is_wp_error($guard)) return $guard;
        $context = self::context($context);
        if (is_wp_error($context)) return $context;
        if (!self::isEnabled()) return new WP_Error('evidence_storage_unavailable', 'Armazenamento privado indisponível.', ['status'=>503]);
        $file = $_FILES[$field] ?? [];
        $path = (string)($file['tmp_name'] ?? '');
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($path)) return new WP_Error('evidence_upload', 'Upload inválido.', ['status'=>400]);
        $size = filesize($path);
        if (!$size || $size > 20 * MB_IN_BYTES) return new WP_Error('evidence_size', 'Limite de 20 MB por evidência.', ['status'=>413]);
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $name = sanitize_file_name((string)($file['name'] ?? 'evidence'));
        $type = wp_check_filetype_and_ext($path, $name, get_allowed_mime_types());
        if (empty($type['ext']) || empty($type['type'])) return new WP_Error('evidence_type', 'Tipo de ficheiro não permitido.', ['status'=>400]);
        $plain = file_get_contents($path);
        if (!is_string($plain) || strlen($plain) !== $size) return new WP_Error('evidence_read', 'Não foi possível ler a evidência.', ['status'=>500]);
        $encrypted = SecretVault::encrypt($plain);
        unset($plain);
        if ($encrypted === '') return new WP_Error('evidence_encryption', 'Não foi possível proteger a evidência.', ['status'=>503]);
        // Only authenticated ciphertext reaches uploads: no plaintext window or derivatives.
        $stored = wp_upload_bits('fieldflow-evidence-' . bin2hex(random_bytes(24)) . '.txt', null, $encrypted);
        if (!empty($stored['error'])) return new WP_Error('evidence_storage', 'Não foi possível guardar a evidência.', ['status'=>500]);
        $id = wp_insert_attachment([
            'post_title'=>'FieldFlow evidence', 'post_status'=>'private', 'post_mime_type'=>$type['type'],
            'post_author'=>get_current_user_id(),
        ], $stored['file'], 0, true);
        if (is_wp_error($id) || !$id) {
            wp_delete_file($stored['file']);
            return new WP_Error('evidence_attachment', 'Não foi possível guardar a evidência.', ['status'=>500]);
        }
        $meta = ['context'=>$context, 'filename'=>$name, 'mime'=>$type['type']];
        $ok = update_post_meta($id, '_fieldflow_protected_media', 1)
            && update_post_meta($id, '_fieldflow_evidence', wp_slash($meta));
        if (!$ok) {
            wp_delete_attachment($id, true);
            wp_delete_file($stored['file']);
            return new WP_Error('evidence_metadata', 'Não foi possível autorizar a evidência.', ['status'=>500]);
        }
        return (int)$id;
    }

    public static function canRead(int $attachmentId): bool {
        if (!is_user_logged_in() || !self::attachmentIsProtected($attachmentId)) return false;
        $meta = get_post_meta($attachmentId, '_fieldflow_evidence', true);
        if (!is_array($meta) || empty($meta['context'])) return false;
        $context = (array)$meta['context'];
        if (!empty($context['route_id'])) return Permissions::can_access_route((int)$context['route_id']);
        if (!empty($context['client_id']) || !empty($context['project_id'])) {
            return !is_wp_error(Permissions::assert_scope_or_error((int)$context['client_id'], (int)$context['project_id']));
        }
        return (int)($context['owner_user_id'] ?? 0) === get_current_user_id();
    }

    public static function read(int $attachmentId) {
        if (!self::canRead($attachmentId)) return new WP_Error('evidence_not_found', 'Evidência indisponível.', ['status'=>404]);
        $guard = Mfa::requestError();
        if (is_wp_error($guard)) return $guard;
        $meta = get_post_meta($attachmentId, '_fieldflow_evidence', true);
        $path = get_attached_file($attachmentId);
        $uploads = wp_upload_dir();
        $base = realpath((string)($uploads['basedir'] ?? ''));
        $real = $path ? realpath($path) : false;
        if (!$base || !$real || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0 || !is_readable($real)
            || filesize($real) > 30 * MB_IN_BYTES) return new WP_Error('evidence_storage', 'Evidência indisponível.', ['status'=>404]);
        $encrypted = (string)file_get_contents($real);
        if (strpos($encrypted, 's1:') !== 0 && strpos($encrypted, 'o2:') !== 0) return new WP_Error('evidence_decrypt', 'Evidência indisponível.', ['status'=>503]);
        $plain = SecretVault::decrypt($encrypted);
        if ($plain === '') return new WP_Error('evidence_decrypt', 'Evidência indisponível.', ['status'=>503]);
        return ['bytes'=>$plain, 'filename'=>(string)$meta['filename'], 'mime'=>(string)$meta['mime']];
    }

    public static function serve(): void {
        if (!isset($_GET['fieldflow_evidence'])) return;
        if (!is_user_logged_in()) {
            $auth = \RoutesPro\Mobile\Auth::authenticate_bearer(null);
            if (is_wp_error($auth)) wp_die('Autenticação necessária.', '', ['response'=>401]);
        }
        $result = self::read(absint($_GET['fieldflow_evidence']));
        nocache_headers();
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('Cross-Origin-Resource-Policy: same-origin');
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            wp_die($result->get_error_message(), '', ['response'=>(int)($data['status'] ?? 403)]);
        }
        $mime = $result['mime'];
        $inline = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'audio/mpeg'], true);
        header('Content-Type: ' . ($inline ? $mime : 'application/octet-stream'));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($result['filename']) . '"');
        header('Content-Length: ' . strlen($result['bytes']));
        echo $result['bytes'];
        exit;
    }
}
