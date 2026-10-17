<?php
namespace RoutesPro\Forms;

use RoutesPro\Mobile\WebViewBridge;
use RoutesPro\Support\StabilityFlags;

if (!defined('ABSPATH')) exit;

/**
 * Controlled rollout for Forms Runtime V3.
 *
 * 2.2.241 adds an explicitly allowlisted pilot. In pilot mode V3 owns only
 * conditional visibility, visible-step calculation and the canonical wizard
 * navigation. The proven runtime keeps uploads, drafts, validation,
 * submission and visit completion. Any V3 exception or projection mismatch
 * falls back at form level to the legacy owner without reloading the page.
 */
class RuntimeV3 {
    public const OPTION = 'fieldflow_forms_runtime_v3_rollout';
    public const ROLLBACK_OPTION = 'fieldflow_forms_runtime_v3_last_rollback';
    public const RUNTIME_VERSION = '3.0.0-pilot';
    public const BUILD = '2.2.246';

    public static function defaults(): array {
        return [
            'enabled' => false,
            'mode' => 'shadow',
            'user_ids' => [],
            'project_ids' => [],
            'native_only' => false,
            'diagnostics' => true,
            'debug_ui' => false,
            'sample_percent' => 100,
        ];
    }

    public static function get(): array {
        $saved = get_option(self::OPTION, []);
        $settings = wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
        $settings['enabled'] = !empty($settings['enabled']);
        $settings['mode'] = in_array((string)($settings['mode'] ?? ''), ['shadow', 'pilot'], true)
            ? (string)$settings['mode'] : 'shadow';
        $settings['user_ids'] = self::ids($settings['user_ids'] ?? []);
        $settings['project_ids'] = self::ids($settings['project_ids'] ?? []);
        $settings['native_only'] = !empty($settings['native_only']);
        $settings['diagnostics'] = !empty($settings['diagnostics']);
        $settings['debug_ui'] = !empty($settings['debug_ui']);
        $settings['sample_percent'] = max(1, min(100, (int)($settings['sample_percent'] ?? 100)));
        return $settings;
    }

    public static function save(array $input): array {
        $userIds = self::ids($input['user_ids'] ?? []);
        $mode = in_array((string)($input['mode'] ?? ''), ['shadow', 'pilot'], true)
            ? (string)$input['mode'] : 'shadow';

        // Pilot ownership is never granted by capability alone. An explicit
        // user allowlist is mandatory, preventing an accidental broad rollout.
        if ($mode === 'pilot' && !$userIds) $mode = 'shadow';

        $settings = [
            'enabled' => !empty($input['enabled']),
            'mode' => $mode,
            'user_ids' => $userIds,
            'project_ids' => self::ids($input['project_ids'] ?? []),
            'native_only' => !empty($input['native_only']),
            'diagnostics' => !empty($input['diagnostics']),
            'debug_ui' => !empty($input['debug_ui']),
            'sample_percent' => max(1, min(100, (int)($input['sample_percent'] ?? 100))),
        ];
        update_option(self::OPTION, $settings, false);

        $flags = StabilityFlags::all();
        $flags['forms_runtime_v3'] = (bool)$settings['enabled'];
        update_option(StabilityFlags::OPTION, $flags, false);
        return $settings;
    }

    public static function emergencyRollback(int $actorId = 0): array {
        $settings = self::get();
        $previous = $settings;
        $settings['enabled'] = false;
        $settings['mode'] = 'shadow';
        update_option(self::OPTION, $settings, false);

        $flags = StabilityFlags::all();
        $flags['forms_runtime_v3'] = false;
        update_option(StabilityFlags::OPTION, $flags, false);

        $record = [
            'at' => gmdate('c'),
            'actor_user_id' => max(0, $actorId),
            'previous_mode' => (string)($previous['mode'] ?? 'shadow'),
            'previous_enabled' => !empty($previous['enabled']),
            'user_allowlist_count' => count($previous['user_ids'] ?? []),
            'project_allowlist_count' => count($previous['project_ids'] ?? []),
        ];
        update_option(self::ROLLBACK_OPTION, $record, false);
        return $record;
    }

    /**
     * Request-level eligibility. Shadow mode may default to FieldFlow managers
     * when no allowlist exists. Pilot mode always requires explicit user IDs.
     */
    public static function requestState(array $context = []): array {
        $settings = self::get();
        $userId = get_current_user_id();
        $native = class_exists(WebViewBridge::class) && WebViewBridge::is_native_context();
        $projectId = absint($context['project_id'] ?? 0);
        $reason = 'disabled';
        $eligible = false;

        if (!$settings['enabled'] || !StabilityFlags::enabled('forms_runtime_v3')) {
            $reason = 'feature_disabled';
        } elseif ($userId <= 0) {
            $reason = 'anonymous_user';
        } elseif ($settings['mode'] === 'pilot' && !$settings['user_ids']) {
            $reason = 'pilot_requires_user_allowlist';
        } elseif ($settings['native_only'] && !$native) {
            $reason = 'native_only';
        } elseif ($settings['user_ids'] && !in_array($userId, $settings['user_ids'], true)) {
            $reason = 'user_not_allowlisted';
        } elseif (!$settings['user_ids'] && !current_user_can('routespro_manage')) {
            $reason = 'internal_users_only';
        } elseif ($settings['project_ids'] && !in_array($projectId, $settings['project_ids'], true)) {
            $reason = 'project_not_allowlisted';
        } else {
            $eligible = self::sampled($userId, $projectId, $settings['sample_percent']);
            $reason = $eligible ? 'eligible' : 'outside_sample';
        }

        $mode = $eligible ? $settings['mode'] : 'legacy';
        $owner = $mode === 'pilot' ? 'v3' : 'legacy';

        return [
            'enabled' => $eligible,
            'mode' => $mode,
            'owner' => $owner,
            'runtime_version' => self::RUNTIME_VERSION,
            'build' => self::BUILD,
            'reason' => $reason,
            'native' => $native,
            'diagnostics' => $eligible && $settings['diagnostics'],
            'debug_ui' => $eligible && $settings['debug_ui'],
            'sample_percent' => $settings['sample_percent'],
            'automatic_fallback' => true,
        ];
    }

    public static function shouldEnqueue(): bool {
        $settings = self::get();
        if (!$settings['enabled'] || !StabilityFlags::enabled('forms_runtime_v3')) return false;
        if (!is_user_logged_in()) return false;
        if ($settings['mode'] === 'pilot' && !$settings['user_ids']) return false;
        if ($settings['native_only'] && !(class_exists(WebViewBridge::class) && WebViewBridge::is_native_context())) return false;
        $userId = get_current_user_id();
        if ($settings['user_ids']) return in_array($userId, $settings['user_ids'], true);
        return $settings['mode'] === 'shadow' && current_user_can('routespro_manage');
    }

    public static function browserConfig(): array {
        $settings = self::get();
        return [
            'version' => self::RUNTIME_VERSION,
            'build' => self::BUILD,
            'configuredMode' => (string)$settings['mode'],
            'diagnostics' => (bool)$settings['diagnostics'],
            'debugUi' => (bool)$settings['debug_ui'],
            'samplePercent' => (int)$settings['sample_percent'],
            'automaticFallback' => true,
            'diagnosticsEndpoint' => rest_url('fieldflow-mobile/v1/diagnostics/client-event'),
            'nonce' => wp_create_nonce('wp_rest'),
            'platform' => class_exists(WebViewBridge::class) && WebViewBridge::is_native_context() ? 'native_webview' : 'web',
            'pluginVersion' => defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '',
        ];
    }

    public static function publicState(): array {
        $settings = self::get();
        $rollback = get_option(self::ROLLBACK_OPTION, []);
        return [
            'runtime_version' => self::RUNTIME_VERSION,
            'build' => self::BUILD,
            'enabled' => (bool)$settings['enabled'],
            'mode' => (string)$settings['mode'],
            'owner' => $settings['mode'] === 'pilot' ? 'v3' : 'legacy',
            'automatic_fallback' => true,
            'pilot_requires_explicit_users' => true,
            'user_allowlist_count' => count($settings['user_ids']),
            'project_allowlist_count' => count($settings['project_ids']),
            'native_only' => (bool)$settings['native_only'],
            'diagnostics' => (bool)$settings['diagnostics'],
            'debug_ui' => (bool)$settings['debug_ui'],
            'sample_percent' => (int)$settings['sample_percent'],
            'last_rollback' => is_array($rollback) ? $rollback : [],
        ];
    }

    public static function ids($value): array {
        if (is_string($value)) $value = preg_split('/[\s,;]+/', $value) ?: [];
        if (!is_array($value)) $value = [];
        $ids = [];
        foreach ($value as $item) {
            $id = absint($item);
            if ($id > 0) $ids[$id] = $id;
        }
        ksort($ids, SORT_NUMERIC);
        return array_values($ids);
    }

    private static function sampled(int $userId, int $projectId, int $percent): bool {
        if ($percent >= 100) return true;
        $seed = $userId . '|' . $projectId . '|' . self::BUILD;
        $bucket = (int)(hexdec(substr(hash('sha256', $seed), 0, 8)) % 100) + 1;
        return $bucket <= $percent;
    }
}
