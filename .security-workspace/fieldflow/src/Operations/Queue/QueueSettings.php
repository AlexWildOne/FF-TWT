<?php
namespace RoutesPro\Operations\Queue;

if (!defined('ABSPATH')) exit;

final class QueueSettings {
    public const VERSION = '1.0.3';
    public const OPTION = 'fieldflow_queue_modernization';
    public const ROLLBACK_OPTION = 'fieldflow_queue_last_rollback';
    public const LAST_PROBE_OPTION = 'fieldflow_queue_last_probe';

    public static function defaults(): array {
        return [
            'mode' => 'observe',
            'driver' => 'auto',
            'pilot_hooks' => [],
            'batch_size' => 10,
            'max_runtime_seconds' => 20,
            'lock_ttl_seconds' => 600,
            'retention_days' => 30,
            'diagnostics' => 1,
        ];
    }

    public static function allowedHooks(): array {
        return [
            'fieldflow_reconcile_submission_history' => ['label'=>'Histórico pós-submissão','args'=>1,'queue'=>'submissions','max_attempts'=>3],
            'fieldflow_reconcile_submission_cardex' => ['label'=>'Cardex pós-submissão','args'=>1,'queue'=>'submissions','max_attempts'=>3],
            'fieldflow_reconcile_route_after_submission' => ['label'=>'Rota pós-submissão','args'=>1,'queue'=>'submissions','max_attempts'=>3],
            'fieldflow_submission_post_commit' => ['label'=>'Integrações pós-submissão','args'=>3,'queue'=>'integrations','max_attempts'=>3],
            'fieldflow_submission_side_effect_retry' => ['label'=>'Retry isolado de side effect','args'=>3,'queue'=>'integrations','max_attempts'=>4],
            'fieldflow_azure_sync_dispatch' => ['label'=>'Azure Cloud Sync','args'=>0,'queue'=>'cloud_sync','max_attempts'=>1],
            'fieldflow_client_azure_dispatch' => ['label'=>'Client Azure Relay','args'=>0,'queue'=>'client_azure','max_attempts'=>1],
            'fieldflow_connector_delivery_dispatch' => ['label'=>'Connector Delivery 5C.5','args'=>0,'queue'=>'connector_delivery','max_attempts'=>1],
            'fieldflow_queue_health_probe' => ['label'=>'Teste seguro da fila','args'=>1,'queue'=>'system','max_attempts'=>1],
            'fieldflow_schema_batch_run' => ['label'=>'Integridade do schema em lotes','args'=>1,'queue'=>'schema','max_attempts'=>3],
            'fieldflow_report_automation_run' => ['label'=>'Automacao de reporting','args'=>1,'queue'=>'reporting','max_attempts'=>4],
            'fieldflow_daily_close_dispatch_retry' => ['label'=>'Retry de fecho diario','args'=>2,'queue'=>'reporting','max_attempts'=>5],
            'fieldflow_order_suggestion_retry' => ['label'=>'Retry de sugestao de encomenda','args'=>2,'queue'=>'reporting','max_attempts'=>5],
        ];
    }

    public static function get(): array {
        $stored = get_option(self::OPTION, []);
        $settings = wp_parse_args(is_array($stored) ? $stored : [], self::defaults());
        $settings['mode'] = in_array((string)$settings['mode'], ['legacy','observe','pilot'], true) ? (string)$settings['mode'] : 'observe';
        $settings['driver'] = in_array((string)$settings['driver'], ['auto','wp_cron','action_scheduler'], true) ? (string)$settings['driver'] : 'auto';
        $settings['pilot_hooks'] = self::normaliseHooks($settings['pilot_hooks'] ?? []);
        $settings['batch_size'] = max(1, min(50, absint($settings['batch_size'] ?? 10)));
        $settings['max_runtime_seconds'] = max(5, min(50, absint($settings['max_runtime_seconds'] ?? 20)));
        $settings['lock_ttl_seconds'] = max(120, min(3600, absint($settings['lock_ttl_seconds'] ?? 600)));
        $settings['retention_days'] = max(7, min(180, absint($settings['retention_days'] ?? 30)));
        $settings['diagnostics'] = !empty($settings['diagnostics']) ? 1 : 0;
        if ($settings['mode'] === 'pilot' && !$settings['pilot_hooks']) $settings['mode'] = 'observe';
        return $settings;
    }

    public static function save(array $input): array {
        $mode = in_array((string)($input['mode'] ?? ''), ['legacy','observe','pilot'], true) ? (string)$input['mode'] : 'observe';
        $driver = in_array((string)($input['driver'] ?? ''), ['auto','wp_cron','action_scheduler'], true) ? (string)$input['driver'] : 'auto';
        $hooks = self::normaliseHooks($input['pilot_hooks'] ?? []);
        if ($mode === 'pilot' && !$hooks) $mode = 'observe';
        $settings = [
            'mode' => $mode,
            'driver' => $driver,
            'pilot_hooks' => $hooks,
            'batch_size' => max(1, min(50, absint($input['batch_size'] ?? 10))),
            'max_runtime_seconds' => max(5, min(50, absint($input['max_runtime_seconds'] ?? 20))),
            'lock_ttl_seconds' => max(120, min(3600, absint($input['lock_ttl_seconds'] ?? 600))),
            'retention_days' => max(7, min(180, absint($input['retention_days'] ?? 30))),
            'diagnostics' => !empty($input['diagnostics']) ? 1 : 0,
        ];
        update_option(self::OPTION, $settings, false);
        return $settings;
    }

    public static function emergencyFallback(int $actorId = 0): array {
        $previous = self::get();
        $settings = $previous;
        $settings['mode'] = 'legacy';
        update_option(self::OPTION, $settings, false);
        $rollback = [
            'at' => gmdate('c'),
            'actor_user_id' => max(0, $actorId),
            'previous_mode' => (string)$previous['mode'],
            'previous_driver' => (string)$previous['driver'],
            'pilot_hook_count' => count($previous['pilot_hooks']),
        ];
        update_option(self::ROLLBACK_OPTION, $rollback, false);
        return $rollback;
    }

    public static function isAllowed(string $hook): bool {
        return isset(self::allowedHooks()[$hook]);
    }

    public static function isPilotOwner(string $hook, ?array $settings = null): bool {
        $settings = $settings ?: self::get();
        return (string)$settings['mode'] === 'pilot' && in_array($hook, (array)$settings['pilot_hooks'], true) && self::isAllowed($hook);
    }

    public static function effectiveDriver(?array $settings = null): string {
        $settings = $settings ?: self::get();
        $selected = (string)($settings['driver'] ?? 'auto');
        $available = self::actionSchedulerAvailable();
        if ($selected === 'action_scheduler') return $available ? 'action_scheduler' : 'wp_cron';
        if ($selected === 'auto') return $available ? 'action_scheduler' : 'wp_cron';
        return 'wp_cron';
    }

    public static function actionSchedulerAvailable(): bool {
        return function_exists('as_schedule_single_action') && (function_exists('as_next_scheduled_action') || function_exists('as_has_scheduled_action'));
    }

    private static function normaliseHooks($hooks): array {
        if (is_string($hooks)) $hooks = preg_split('/[\s,;]+/', $hooks) ?: [];
        if (!is_array($hooks)) $hooks = [];
        $allowed = self::allowedHooks();
        $out = [];
        foreach ($hooks as $hook) {
            $hook = sanitize_key((string)$hook);
            if ($hook !== '' && isset($allowed[$hook])) $out[$hook] = $hook;
        }
        return array_values($out);
    }
}
