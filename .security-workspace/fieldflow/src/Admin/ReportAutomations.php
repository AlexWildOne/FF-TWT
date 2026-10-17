<?php
namespace RoutesPro\Admin;

use RoutesPro\Support\AssignmentResolver;
use RoutesPro\Services\SubmissionStatusPolicy;
use RoutesPro\Operations\Queue\QueueManager;

if (!defined('ABSPATH')) exit;

class ReportAutomations {
    const CRON_HOOK = 'fieldflow_report_automation_run';
    const ORDER_RETRY_HOOK = 'fieldflow_order_suggestion_retry';
    const ORDER_RETRY_MAX_ATTEMPTS = 4;
    const DAILY_CLOSE_RETRY_HOOK = 'fieldflow_daily_close_dispatch_retry';
    const DAILY_CLOSE_RETRY_MAX_ATTEMPTS = 4;
    private static array $routeCloseQueue = [];
    private static bool $routeCloseShutdownRegistered = false;

    public static function register_hooks(): void {
        self::ensureSchema();
        add_action('admin_post_fieldflow_report_automation_save', [self::class, 'handleSave']);
        add_action('admin_post_fieldflow_report_automation_test', [self::class, 'handleTest']);
        add_action('admin_post_fieldflow_report_automation_run', [self::class, 'handleRunNow']);
        add_action('admin_post_fieldflow_report_automation_toggle', [self::class, 'handleToggle']);
        add_action('admin_post_fieldflow_report_automation_delete', [self::class, 'handleDelete']);
        add_action('admin_post_fieldflow_mailcenter_test', [self::class, 'handleTestCenter']);
        add_action('admin_post_fieldflow_mailcenter_manual_send', [self::class, 'handleManualSend']);
        // Form submission/visit handlers are invoked by the isolated post-commit dispatcher.
        add_action('fieldflow_core_event', [self::class, 'handleCoreEvent'], 30, 4);
        add_action(self::ORDER_RETRY_HOOK, [self::class, 'handleOrderSuggestionRetry'], 10, 2);
        add_action(self::DAILY_CLOSE_RETRY_HOOK, [self::class, 'handleDailyCloseRetry'], 10, 2);
        add_action(self::CRON_HOOK, [self::class, 'runScheduled'], 10, 1);
        add_action('wp_loaded', [self::class, 'repairSchedules']);
    }

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_report_schedules';
    }

    public static function ensureSchema(): void {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        $schemaVersion = '2.2.445';
        if ((string)get_option('fieldflow_report_schedule_schema_version', '') === $schemaVersion) return;
        global $wpdb;
        $table = self::table();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        if (!$exists) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset = $wpdb->get_charset_collate();
            dbDelta("CREATE TABLE {$table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                client_id BIGINT UNSIGNED NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                automation_type VARCHAR(32) NOT NULL DEFAULT 'weekly',
                client_delivery_mode VARCHAR(24) NOT NULL DEFAULT 'immediate',
                weekday TINYINT UNSIGNED NOT NULL DEFAULT 1,
                send_time TIME NOT NULL DEFAULT '08:00:00',
                start_date DATE NOT NULL,
                end_date DATE NULL,
                cumulative_start DATE NULL,
                periods_json LONGTEXT NULL,
                formats_json LONGTEXT NULL,
                to_team TINYINT(1) NOT NULL DEFAULT 1,
                to_clients TINYINT(1) NOT NULL DEFAULT 1,
                extra_emails TEXT NULL,
                test_email VARCHAR(190) NULL,
                test_scope VARCHAR(24) NOT NULL DEFAULT 'client',
                test_user_id BIGINT UNSIGNED NULL,
                subject_template VARCHAR(255) NOT NULL,
                intro_text TEXT NULL,
                brand_primary VARCHAR(16) NOT NULL DEFAULT '#172033',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by BIGINT UNSIGNED NULL,
                next_run_at DATETIME NULL,
                last_run_at DATETIME NULL,
                last_status VARCHAR(32) NULL,
                last_message VARCHAR(255) NULL,
                last_test_at DATETIME NULL,
                last_test_status VARCHAR(32) NULL,
                last_test_message VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY scope_idx (client_id, project_id),
                KEY active_next_idx (is_active, next_run_at),
                KEY created_by_idx (created_by)
            ) {$charset}");
            update_option('fieldflow_report_schedule_schema_version', $schemaVersion, false);
            return;
        }

        $columns = array_map('strtolower', (array)$wpdb->get_col("SHOW COLUMNS FROM {$table}", 0));
        $migrations = [
            'automation_type' => "ALTER TABLE {$table} ADD COLUMN automation_type VARCHAR(32) NOT NULL DEFAULT 'weekly' AFTER project_id",
            'client_delivery_mode' => "ALTER TABLE {$table} ADD COLUMN client_delivery_mode VARCHAR(24) NOT NULL DEFAULT 'immediate' AFTER automation_type",
            'test_email' => "ALTER TABLE {$table} ADD COLUMN test_email VARCHAR(190) NULL AFTER extra_emails",
            'test_scope' => "ALTER TABLE {$table} ADD COLUMN test_scope VARCHAR(24) NOT NULL DEFAULT 'client' AFTER test_email",
            'test_user_id' => "ALTER TABLE {$table} ADD COLUMN test_user_id BIGINT UNSIGNED NULL AFTER test_scope",
            'last_test_at' => "ALTER TABLE {$table} ADD COLUMN last_test_at DATETIME NULL AFTER last_message",
            'last_test_status' => "ALTER TABLE {$table} ADD COLUMN last_test_status VARCHAR(32) NULL AFTER last_test_at",
            'last_test_message' => "ALTER TABLE {$table} ADD COLUMN last_test_message VARCHAR(255) NULL AFTER last_test_status",
        ];
        foreach ($migrations as $column => $sql) {
            if (!in_array($column, $columns, true)) $wpdb->query($sql);
        }
        update_option('fieldflow_report_schedule_schema_version', $schemaVersion, false);
    }

    private static function guard(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
    }

    private static function assertSaasAccess(int $clientId, bool $creating = false): void {
        if ($clientId <= 0 || !class_exists('\RoutesPro\SaaS\EntitlementService')) return;
        if (!\RoutesPro\SaaS\EntitlementService::clientHas($clientId, 'reports.schedule')) {
            wp_die('Os relatórios automáticos não estão incluídos no plano deste cliente.', 'FieldFlow', ['response'=>403,'back_link'=>true]);
        }
        if ($creating && class_exists('\RoutesPro\SaaS\UsageService')) {
            $quota = \RoutesPro\SaaS\UsageService::canConsume($clientId, 'scheduled_reports', 1);
            if (empty($quota['allowed'])) wp_die('O limite de relatórios automáticos foi atingido.', 'FieldFlow', ['response'=>429,'back_link'=>true]);
        }
    }

    private static function redirect(array $args = []): void {
        $url = add_query_arg(array_merge(['page' => 'routespro-emails', 'mail_center_tab' => 'automations'], $args), admin_url('admin.php')) . '#fieldflow-report-automations';
        wp_safe_redirect($url);
        exit;
    }

    public static function handleSave(): void {
        self::guard();
        check_admin_referer('fieldflow_report_automation_save');
        global $wpdb;
        $table = self::table();
        $id = absint($_POST['schedule_id'] ?? 0);
        $clientId = absint($_POST['client_id'] ?? 0);
        $projectId = absint($_POST['project_id'] ?? 0);
        if (!$clientId || !$projectId) self::redirect(['ff_report_notice' => 'missing_scope']);
        self::assertSaasAccess($clientId, $id <= 0);
        $projectClient = (int)$wpdb->get_var($wpdb->prepare("SELECT client_id FROM {$wpdb->prefix}routespro_projects WHERE id=%d", $projectId));
        if ($projectClient !== $clientId) self::redirect(['ff_report_notice' => 'invalid_scope']);

        $automationType = sanitize_key((string)($_POST['automation_type'] ?? 'weekly'));
        if (!in_array($automationType, ['daily_close','daily_digest','weekly','order_suggestion'], true)) $automationType = 'weekly';
        $clientDeliveryMode = sanitize_key((string)($_POST['client_delivery_mode'] ?? 'immediate'));
        if (!in_array($clientDeliveryMode, ['immediate','consolidated'], true)) $clientDeliveryMode = 'immediate';

        $weekday = max(1, min(7, absint($_POST['weekday'] ?? 1)));
        $sendTime = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)($_POST['send_time'] ?? '20:00')) ? (string)$_POST['send_time'] : '20:00';
        $startDate = self::validDate((string)($_POST['start_date'] ?? '')) ?: wp_date('Y-m-d');
        $endDate = self::validDate((string)($_POST['end_date'] ?? ''));
        $cumulativeStart = self::validDate((string)($_POST['cumulative_start'] ?? '')) ?: $startDate;
        if ($endDate && $endDate < $startDate) $endDate = $startDate;

        $formats = [];
        foreach ((array)($_POST['formats'] ?? []) as $format) {
            $format = sanitize_key($format);
            if (in_array($format, ['xlsx','pdf','csv'], true)) $formats[$format] = $format;
        }
        $periods = [];
        foreach ((array)($_POST['periods'] ?? []) as $period) {
            $period = sanitize_key($period);
            if (in_array($period, ['daily','week_to_date','weekly','cumulative'], true)) $periods[$period] = $period;
        }
        if (!$periods) {
            $periods = $automationType === 'weekly'
                ? ['weekly'=>'weekly','cumulative'=>'cumulative']
                : ['daily'=>'daily','week_to_date'=>'week_to_date'];
        }
        if ($automationType === 'order_suggestion') {
            $periods = ['daily'=>'daily'];
            $formats = ['xlsx'=>'xlsx','pdf'=>'pdf'];
        } elseif (!$formats) {
            // Operational reports are complete by default. Email-only delivery
            // must never happen accidentally because old rows stored [].
            $formats = ['xlsx'=>'xlsx','pdf'=>'pdf'];
        }

        $testScope = sanitize_key((string)($_POST['test_scope'] ?? 'client'));
        if (!in_array($testScope, ['client','merchandiser'], true)) $testScope = 'client';
        $testUserId = $testScope === 'merchandiser' ? absint($_POST['test_user_id'] ?? 0) : 0;
        if ($testUserId && !self::isTeamUserForProject($testUserId, $projectId)) $testUserId = 0;

        $defaultName = $automationType === 'daily_close' ? 'Fecho diário do merchandiser' : ($automationType === 'daily_digest' ? 'Daily Intelligence consolidado' : ($automationType === 'order_suggestion' ? 'Sugestões de encomenda' : 'Weekly Intelligence'));
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? $defaultName),
            'client_id' => $clientId,
            'project_id' => $projectId,
            'automation_type' => $automationType,
            'client_delivery_mode' => $clientDeliveryMode,
            'weekday' => $weekday,
            'send_time' => $sendTime . ':00',
            'start_date' => $startDate,
            'end_date' => $endDate ?: null,
            'cumulative_start' => $cumulativeStart,
            'periods_json' => wp_json_encode(array_values($periods)),
            'formats_json' => wp_json_encode(array_values($formats)),
            'to_team' => $automationType === 'order_suggestion' ? 1 : (isset($_POST['to_team']) ? 1 : 0),
            'to_clients' => $automationType === 'order_suggestion' ? 1 : (isset($_POST['to_clients']) ? 1 : 0),
            'extra_emails' => self::sanitizeEmailList((string)($_POST['extra_emails'] ?? '')),
            'test_email' => sanitize_email((string)($_POST['test_email'] ?? '')),
            'test_scope' => $testScope,
            'test_user_id' => $testUserId ?: null,
            'subject_template' => sanitize_text_field($_POST['subject_template'] ?? ($automationType === 'order_suggestion' ? '[FieldFlow] Sugestão de encomenda, {store_name}, {operative_name}' : '[FieldFlow] {project_name}, {period_label}')),
            'intro_text' => sanitize_textarea_field($_POST['intro_text'] ?? ($automationType === 'order_suggestion' ? 'Foi registada uma sugestão de encomenda. Consulta abaixo o resumo e os ficheiros Excel e PDF em anexo.' : 'Segue o resumo FieldFlow Intelligence do período, com alertas por loja, recomendações e evolução operacional.')),
            'brand_primary' => sanitize_hex_color($_POST['brand_primary'] ?? '') ?: '#172033',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'updated_at' => current_time('mysql'),
        ];
        if ($id) {
            $wpdb->update($table, $data, ['id' => $id]);
        } else {
            $data['created_by'] = get_current_user_id();
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
            $id = (int)$wpdb->insert_id;
        }
        self::reschedule($id);
        $afterSaveAction = sanitize_key((string)($_POST['after_save_action'] ?? 'save'));
        if ($afterSaveAction === 'test') {
            $testEmail = sanitize_email((string)($data['test_email'] ?? ''));
            if (!$testEmail) {
                $user = wp_get_current_user();
                $testEmail = $user && !empty($user->user_email) ? sanitize_email((string)$user->user_email) : '';
            }
            $selectedTestUser = $testScope === 'merchandiser' ? $testUserId : 0;
            $result = $testEmail && ($testScope !== 'merchandiser' || $selectedTestUser)
                ? self::execute($id, true, $testEmail, $selectedTestUser, ['anchor_date'=>wp_date('Y-m-d')])
                : new \WP_Error('missing_test_scope', $testScope === 'merchandiser' ? 'Seleciona um merchandiser válido para o teste.' : 'Indica um email de teste válido.');
            self::redirect(['ff_report_notice' => is_wp_error($result) ? 'test_failed' : 'test_sent', 'edit_report_schedule' => $id]);
        }
        self::redirect(['ff_report_notice' => 'saved', 'edit_report_schedule' => $id]);
    }

    public static function handleTest(): void {
        self::guard();
        check_admin_referer('fieldflow_report_automation_action');
        $id = absint($_GET['schedule_id'] ?? 0);
        $row = $id ? self::get($id) : [];
        self::assertSaasAccess((int)($row['client_id'] ?? 0), false);
        $testEmail = sanitize_email((string)($_GET['test_email'] ?? ($row['test_email'] ?? '')));
        if (!$testEmail) {
            $user = wp_get_current_user();
            $testEmail = $user && !empty($user->user_email) ? sanitize_email((string)$user->user_email) : '';
        }
        if (!$id || !$testEmail) self::redirect(['ff_report_notice' => 'test_failed', 'edit_report_schedule' => $id]);
        $testScope = sanitize_key((string)($row['test_scope'] ?? 'client'));
        $testUserId = $testScope === 'merchandiser' ? absint($row['test_user_id'] ?? 0) : 0;
        if ($testScope === 'merchandiser' && !$testUserId) self::redirect(['ff_report_notice' => 'test_failed', 'edit_report_schedule' => $id]);
        $testDate = self::validDate((string)($_GET['test_date'] ?? '')) ?: wp_date('Y-m-d');
        $result = self::execute($id, true, $testEmail, $testUserId, ['anchor_date'=>$testDate]);
        self::redirect(['ff_report_notice' => is_wp_error($result) ? 'test_failed' : 'test_sent', 'edit_report_schedule' => $id]);
    }

    public static function handleRunNow(): void {
        self::guard();
        check_admin_referer('fieldflow_report_automation_action');
        $id = absint($_GET['schedule_id'] ?? 0);
        $row = $id ? self::get($id) : [];
        self::assertSaasAccess((int)($row['client_id'] ?? 0), false);
        $type = sanitize_key((string)($row['automation_type'] ?? 'weekly'));
        $context = ['trigger_type' => 'manual_run'];
        if (in_array($type, ['daily_close', 'daily_digest'], true)) {
            $context['anchor_date'] = wp_date('Y-m-d');
        }
        $result = self::execute($id, false, '', 0, $context);
        self::redirect(['ff_report_notice' => is_wp_error($result) ? 'run_failed' : 'run_sent', 'edit_report_schedule' => $id]);
    }

    public static function handleToggle(): void {
        self::guard();
        check_admin_referer('fieldflow_report_automation_action');
        global $wpdb;
        $id = absint($_GET['schedule_id'] ?? 0);
        $row = $id ? self::get($id) : [];
        self::assertSaasAccess((int)($row['client_id'] ?? 0), false);
        $active = absint($_GET['active'] ?? 0) ? 1 : 0;
        $wpdb->update(self::table(), ['is_active' => $active, 'updated_at' => current_time('mysql')], ['id' => $id], ['%d','%s'], ['%d']);
        self::reschedule($id);
        self::redirect(['ff_report_notice' => $active ? 'enabled' : 'paused']);
    }

    public static function handleDelete(): void {
        self::guard();
        check_admin_referer('fieldflow_report_automation_action');
        global $wpdb;
        $id = absint($_GET['schedule_id'] ?? 0);
        wp_clear_scheduled_hook(self::CRON_HOOK, [$id]);
        $wpdb->delete(self::table(), ['id' => $id], ['%d']);
        self::redirect(['ff_report_notice' => 'deleted']);
    }

    public static function runScheduled($scheduleId): void {
        $id = absint($scheduleId);
        if (!$id) return;
        $row = self::get($id);
        $clientId = (int)($row['client_id'] ?? 0);
        if ($clientId > 0 && class_exists('\RoutesPro\SaaS\EntitlementService') && !\RoutesPro\SaaS\EntitlementService::clientHas($clientId, 'reports.schedule')) {
            self::reschedule($id, time() + DAY_IN_SECONDS);
            return;
        }
        $type = sanitize_key((string)($row['automation_type'] ?? 'weekly'));
        $context = [];
        if ($type === 'daily_digest') {
            $context = ['anchor_date'=>wp_date('Y-m-d'), 'recipient_mode'=>'all', 'trigger_type'=>'daily_digest'];
        } elseif ($type === 'daily_close' && (string)($row['client_delivery_mode'] ?? '') === 'consolidated') {
            $context = ['anchor_date'=>wp_date('Y-m-d'), 'recipient_mode'=>'client_and_extra', 'trigger_type'=>'daily_close_consolidated'];
        }
        self::execute($id, false, '', 0, $context);
        self::reschedule($id, time() + 60);
    }

    public static function repairSchedules(): void {
        if (!function_exists('wp_next_scheduled')) return;
        global $wpdb;
        $table = self::table();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) return;
        self::recoverMissingProjectTemplateAutomations();
        self::reconcileMissedDailyClosures();
        $rows = $wpdb->get_results("SELECT id,next_run_at,automation_type,client_delivery_mode FROM {$table} WHERE is_active=1 ORDER BY id ASC LIMIT 100", ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $id = absint($row['id'] ?? 0);
            if (!$id) continue;
            $type = sanitize_key((string)($row['automation_type'] ?? 'weekly'));
            $immediateClose = $type === 'daily_close' && sanitize_key((string)($row['client_delivery_mode'] ?? 'immediate')) === 'immediate';
            $eventDriven = $type === 'order_suggestion' || $immediateClose;
            if ($eventDriven) continue;
            $nextRun = trim((string)($row['next_run_at'] ?? ''));
            $nextTs = $nextRun !== '' ? strtotime($nextRun) : 0;
            if ($nextTs > 0 && $nextTs <= time() - 30) {
                QueueManager::schedule(self::CRON_HOOK, [$id], 1, [
                    'force_durable'=>true,
                    'source'=>'report_schedule_repair',
                    'idempotency_key'=>'report-schedule:' . $id . ':' . $nextTs,
                    'max_attempts'=>4,
                ]);
                continue;
            }
            if (!wp_next_scheduled(self::CRON_HOOK, [$id])) self::reschedule($id);
        }
    }

    private static function recoverMissingProjectTemplateAutomations(): int {
        if (get_transient('fieldflow_project_template_automation_recovery_lock')) return 0;
        set_transient('fieldflow_project_template_automation_recovery_lock', 1, 10 * MINUTE_IN_SECONDS);
        $state = Emails::get();
        $templates = is_array($state['project_templates'] ?? null) ? $state['project_templates'] : [];
        if (!$templates) return 0;
        global $wpdb;
        $recovered = 0;
        foreach ($templates as $projectKey => $template) {
            $projectId = absint($projectKey);
            if ($projectId <= 0 || !is_array($template) || !$template) continue;
            $project = $wpdb->get_row($wpdb->prepare(
                "SELECT id,client_id FROM {$wpdb->prefix}routespro_projects WHERE id=%d LIMIT 1",
                $projectId
            ), ARRAY_A) ?: [];
            $clientId = absint($project['client_id'] ?? 0);
            if ($clientId <= 0) continue;
            $existing = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM ".self::table()." WHERE client_id=%d AND project_id=%d AND automation_type='daily_close' ORDER BY id ASC LIMIT 1",
                $clientId, $projectId
            ));
            // Missing rows are recoverable from the explicit project template.
            // Existing rows, including paused ones, are never auto-reactivated.
            if ($existing > 0) continue;
            if (self::ensureDailyCloseAutomation($clientId, $projectId, wp_date('Y-m-d'), true) > 0) $recovered++;
        }
        return $recovered;
    }

    private static function reconcileMissedDailyClosures(): void {
        if (get_transient('fieldflow_daily_close_reconcile_lock')) return;
        set_transient('fieldflow_daily_close_reconcile_lock', 1, 5 * MINUTE_IN_SECONDS);
        global $wpdb;
        $scheduleTable = self::table();
        $routeTable = $wpdb->prefix . 'routespro_routes';
        $stopTable = $wpdb->prefix . 'routespro_route_stops';
        $from = wp_date('Y-m-d', strtotime('-3 days'));
        $to = wp_date('Y-m-d');
        $remainingSql = \RoutesPro\Services\VisitCompletionPolicy::remainingSql('rs_open');
        $sql = "SELECT r.id FROM {$routeTable} r WHERE r.date BETWEEN %s AND %s AND r.status IN ('completed','done')
"
            . "AND NOT EXISTS (SELECT 1 FROM {$stopTable} rs_open WHERE rs_open.route_id=r.id AND {$remainingSql})
"
            . "AND EXISTS (SELECT 1 FROM {$scheduleTable} sch WHERE sch.is_active=1 AND sch.automation_type='daily_close' AND sch.client_id=r.client_id AND sch.project_id=r.project_id AND sch.start_date<=r.date AND (sch.end_date IS NULL OR sch.end_date='' OR sch.end_date>=r.date))
"
            . "ORDER BY r.date DESC,r.id DESC LIMIT 100";
        $routeIds = $wpdb->get_col($wpdb->prepare($sql, $from, $to)) ?: [];
        foreach ($routeIds as $routeId) self::queueDailyCloseForRoute((int)$routeId);
    }

    private static function reschedule(int $id, int $after = 0): void {
        wp_clear_scheduled_hook(self::CRON_HOOK, [$id]);
        $row = self::get($id);
        if (!$row || empty($row['is_active'])) return;
        $timestamp = self::nextTimestamp($row, $after ?: time());
        global $wpdb;
        if (!$timestamp) {
            $wpdb->update(self::table(), ['next_run_at' => null], ['id' => $id], ['%s'], ['%d']);
            return;
        }
        wp_schedule_single_event($timestamp, self::CRON_HOOK, [$id]);
        $wpdb->update(self::table(), ['next_run_at' => wp_date('Y-m-d H:i:s', $timestamp)], ['id' => $id], ['%s'], ['%d']);
    }

    private static function nextTimestamp(array $row, int $after): int {
        $tz = wp_timezone();
        $afterDt = (new \DateTimeImmutable('@' . $after))->setTimezone($tz);
        $start = new \DateTimeImmutable((string)$row['start_date'] . ' 00:00:00', $tz);
        if ($afterDt < $start) $afterDt = $start->modify('-1 second');
        $type = sanitize_key((string)($row['automation_type'] ?? 'weekly'));
        $time = substr((string)$row['send_time'], 0, 5) ?: '20:00';

        if ($type === 'order_suggestion') return 0;
        if ($type === 'daily_close' && (string)($row['client_delivery_mode'] ?? 'immediate') !== 'consolidated') return 0;

        if (in_array($type, ['daily_close','daily_digest'], true)) {
            $candidate = new \DateTimeImmutable($afterDt->format('Y-m-d') . ' ' . $time . ':00', $tz);
            if ($candidate->getTimestamp() <= $after) $candidate = $candidate->modify('+1 day');
        } else {
            $targetWeekday = max(1, min(7, (int)$row['weekday']));
            $candidate = new \DateTimeImmutable($afterDt->format('Y-m-d') . ' ' . $time . ':00', $tz);
            $currentWeekday = (int)$candidate->format('N');
            $days = ($targetWeekday - $currentWeekday + 7) % 7;
            if ($days > 0) $candidate = $candidate->modify('+' . $days . ' days');
            if ($candidate->getTimestamp() <= $after) $candidate = $candidate->modify('+7 days');
        }
        if ($candidate < $start) $candidate = new \DateTimeImmutable($start->format('Y-m-d') . ' ' . $time . ':00', $tz);
        if (!empty($row['end_date'])) {
            $end = new \DateTimeImmutable((string)$row['end_date'] . ' 23:59:59', $tz);
            if ($candidate > $end) return 0;
        }
        return $candidate->getTimestamp();
    }

    private static function get(int $id): array {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d', $id), ARRAY_A) ?: [];
    }

    private static function execute(int $id, bool $test = false, string $testEmail = '', int $testUserId = 0, array $context = []) {
        $row = self::get($id);
        if (!$row) return new \WP_Error('missing_schedule', 'Automação não encontrada.');
        if (!$test && empty($row['is_active']) && empty($context['manual_dispatch'])) return new \WP_Error('inactive_schedule', 'Automação em pausa.');
        $previousUser = get_current_user_id();
        $createdBy = absint($row['created_by'] ?? 0);
        if ($createdBy) wp_set_current_user($createdBy);

        $testEmail = sanitize_email($testEmail);
        if ($test && !$testEmail) {
            self::maybeUpdateExecutionState($id, true, 'failed', 'Email de teste inválido.', $context);
            wp_set_current_user($previousUser);
            return new \WP_Error('invalid_test_email', 'Indica um email de teste válido.');
        }
        if ($test && $testUserId > 0) {
            if (empty($context['manual_dispatch']) && !self::isTeamUserForProject($testUserId, (int)$row['project_id'])) {
                self::maybeUpdateExecutionState($id, true, 'failed', 'Merchandiser de teste fora da campanha.', $context);
                wp_set_current_user($previousUser);
                return new \WP_Error('invalid_test_user', 'O merchandiser selecionado não pertence à campanha.');
            }
            $testUser = get_userdata($testUserId);
            $recipients = [['email' => $testEmail, 'name' => $testUser ? (string)$testUser->display_name : ('Utilizador #' . $testUserId), 'user_id' => $testUserId, 'type' => 'team']];
        } else {
            if (!$test && !empty($context['_resolved_recipients']) && is_array($context['_resolved_recipients'])) {
                $recipients = array_values(array_filter($context['_resolved_recipients'], static function($recipient): bool {
                    return is_array($recipient) && sanitize_email((string)($recipient['email'] ?? '')) !== '';
                }));
            } else {
                $recipients = $test
                    ? [['email' => $testEmail, 'name' => 'Teste', 'user_id' => $createdBy, 'type' => 'test']]
                    : self::resolveRecipientsForContext($row, $context);
            }
        }
        if (!$recipients) {
            self::maybeUpdateExecutionState($id, $test, 'failed', 'Sem destinatários válidos.', $context);
            wp_set_current_user($previousUser);
            return new \WP_Error('no_recipients', 'Sem destinatários válidos.');
        }

        if (sanitize_key((string)($row['automation_type'] ?? '')) === 'order_suggestion') {
            if ($testUserId > 0 && empty($context['report_user_id'])) $context['report_user_id'] = $testUserId;
            return self::executeOrderSuggestion($id, $row, $recipients, $test, $context, $createdBy, $previousUser);
        }

        $periods = json_decode((string)$row['periods_json'], true);
        $formats = json_decode((string)$row['formats_json'], true);
        $periods = is_array($periods) ? array_values(array_intersect($periods, ['daily','week_to_date','weekly','cumulative'])) : ['weekly'];
        $formats = is_array($formats) ? array_values(array_intersect($formats, ['xlsx','pdf','csv'])) : ['xlsx','pdf'];
        if (!$periods) $periods = ['weekly'];
        if (!$formats) $formats = ['xlsx','pdf'];
        $ranges = self::reportRanges($row, $periods, self::validDate((string)($context['anchor_date'] ?? '')));

        self::$brand = sanitize_hex_color((string)($row['brand_primary'] ?? '')) ?: '#172033';
        $scope = self::scopeLabels((int)$row['client_id'], (int)$row['project_id']);
        $periodLabel = implode(' + ', array_map(static function($r){ return $r['label']; }, $ranges));
        $reportUserId = absint($context['report_user_id'] ?? $context['user_id'] ?? 0);
        $reportUser = $reportUserId > 0 ? get_userdata($reportUserId) : null;
        $subjectAnchorDate = self::validDate((string)($context['anchor_date'] ?? '')) ?: wp_date('Y-m-d');
        $tokens = [
            '{client_name}' => $scope['client_name'],
            '{project_name}' => $scope['project_name'],
            '{period_label}' => $periodLabel,
            '{date}' => wp_date('d/m/Y', strtotime($subjectAnchorDate)),
            '{route_id}' => (string)absint($context['route_id'] ?? 0),
            '#{route_id}' => '#' . (string)absint($context['route_id'] ?? 0),
            '{user_name}' => $reportUser ? (string)($reportUser->display_name ?: $reportUser->user_login) : '',
        ];
        $subject = strtr((string)$row['subject_template'], $tokens);
        $subject = trim((string)preg_replace('/\{[^}]+\}/', '', $subject));
        if ($test) $subject = '[TESTE] ' . $subject;
        $intro = strtr((string)$row['intro_text'], $tokens);
        $intro = trim((string)preg_replace('/\{[^}]+\}/', '', $intro));
        $headers = self::mailHeaders();

        $bundles = [];
        $recipientProfiles = [];
        $bundleUses = [];
        foreach ($recipients as $index => $recipient) {
            $profile = self::recipientReportProfile($recipient, $test);
            $scopeUserId = absint($context['report_user_id'] ?? 0);
            if ($scopeUserId > 0 && (string)($profile['audience'] ?? '') === 'client') {
                $scopeUser = get_userdata($scopeUserId);
                $scopeName = $scopeUser ? (string)($scopeUser->display_name ?: $scopeUser->user_login) : ('Utilizador #' . $scopeUserId);
                $profile['cache_key'] = 'client:user:' . $scopeUserId;
                $profile['scope_key'] = 'client_merchandiser_day';
                $profile['user_id'] = $scopeUserId;
                $profile['self_mode'] = false;
                $profile['file_suffix'] = 'cliente-merchandiser-' . $scopeUserId;
                $profile['title'] = 'Visão cliente do dia de ' . $scopeName;
                $profile['description'] = 'Leitura cliente limitada ao merchandiser que acabou de fechar o dia, com contexto acumulado do período selecionado.';
            }
            $recipientProfiles[$index] = $profile;
            $key = (string)$profile['cache_key'];
            $bundleUses[$key] = (int)($bundleUses[$key] ?? 0) + 1;
        }
        $accepted = 0;
        $delivered = 0;
        $queued = 0;
        $failed = 0;
        $generatedBundles = 0;
        $generatedAttachments = 0;
        $failureDetails = [];

        foreach ($recipients as $index => $recipient) {
            $profile = $recipientProfiles[$index];
            $bundleKey = (string)$profile['cache_key'];
            if (!array_key_exists($bundleKey, $bundles)) {
                try {
                    $bundles[$bundleKey] = self::buildReportBundle($row, $ranges, $formats, $profile);
                    $generatedBundles++;
                    $generatedAttachments += count((array)$bundles[$bundleKey]['attachments']);
                } catch (\Throwable $e) {
                    $bundles[$bundleKey] = new \WP_Error('report_generation_failed', $e->getMessage());
                }
            }

            $bundle = $bundles[$bundleKey];
            if (is_wp_error($bundle)) {
                $failed++;
                $failureDetails[] = 'Geração: ' . $bundle->get_error_message();
                Emails::log_email([
                    'email_type' => $test ? 'scheduled_report_test' : (!empty($context['manual_dispatch']) ? 'scheduled_report_manual' : 'scheduled_report'),
                    'context_key' => 'analytics_report',
                    'client_id' => (int)$row['client_id'], 'project_id' => (int)$row['project_id'],
                    'sender_user_id' => $createdBy,
                    'route_id' => absint($context['route_id'] ?? 0),
                    'recipient_user_id' => (int)($recipient['user_id'] ?? 0),
                    'recipient_email' => (string)$recipient['email'],
                    'recipient_name' => (string)($recipient['name'] ?? ''),
                    'message_kind' => (string)($recipient['type'] ?? ''),
                    'subject' => $subject,
                    'body' => wp_strip_all_tags($intro),
                    'meta' => [
                        'schedule_id' => $id,
                        'periods' => array_keys($ranges),
                        'formats' => $formats,
                        'report_scope' => (string)$profile['scope_key'],
                        'report_user_id' => (int)$profile['user_id'],
                        'audience' => (string)$profile['audience'],
                        'error' => $bundle->get_error_message(),
                        'test' => $test ? 1 : 0,
                        'recipient_source' => (string)($recipient['source'] ?? ''),
                        'trigger_type' => sanitize_key((string)($context['trigger_type'] ?? ($row['automation_type'] ?? 'weekly'))),
                        'anchor_date' => self::validDate((string)($context['anchor_date'] ?? '')),
                        'route_id' => absint($context['route_id'] ?? 0),
                    ],
                    'mail_result' => 'failed',
                ]);
                self::releaseReportBundle($bundles, $bundleUses, $bundleKey);
                continue;
            }

            $body = self::emailBody($intro, $scope, $ranges, (array)$bundle['labels'], $profile, (array)($bundle['summaries'] ?? []), $test);
            $routeId = absint($context['route_id'] ?? 0);
            $dailyClose = !$test && $routeId > 0 && sanitize_key((string)($row['automation_type'] ?? '')) === 'daily_close';
            $trigger = $dailyClose ? 'daily_close' : sanitize_key((string)($context['trigger_type'] ?? ($row['automation_type'] ?? 'weekly')));
            $dispatchContext = ['source'=>'scheduled_report','email_type'=>$trigger,'client_id'=>(int)$row['client_id'],'project_id'=>(int)$row['project_id'],'route_id'=>$routeId,'entity_type'=>$routeId > 0 ? 'route' : '','entity_id'=>$routeId,'manual_dispatch'=>!empty($context['manual_dispatch']),'test'=>$test];
            // 9I backwards-compatible source marker: $dispatchContext['idempotency_key'] = 'scheduled_report|';
            // The actual key is now built by recipientDispatchIdentity(), including route_id.
            // Daily Close manually recovered in Mail Center shares the exact automatic identity.
            // This prevents repeated accepted/queued messages, including old 2.2.648 entries.
            if (!$test && ($dailyClose || empty($context['manual_dispatch']))) {
                $identity = self::recipientDispatchIdentity($id, (string)$recipient['email'], $trigger,
                    (string)($context['anchor_date'] ?? ''), (string)$profile['scope_key'], (int)$profile['user_id'], $routeId);
                $dispatchContext['idempotency_key'] = $identity['key'];
                if ($dailyClose) {
                    $dispatchContext['legacy_idempotency_key'] = $identity['legacy'];
                    $dispatchContext['reliable_required'] = 1;
                }
            }
            $mailDispatch = \RoutesPro\Services\MailDispatchService::dispatch((string)$recipient['email'], $subject, $body, $headers, (array)$bundle['attachments'], $dispatchContext);
            $ok = !empty($mailDispatch['accepted']);
            $mailSent = !empty($mailDispatch['sent']);
            $mailError = (string)($mailDispatch['error'] ?? '');
            if ($ok) {
                $accepted++;
                if ($mailSent) $delivered++; else $queued++;
            } else $failed++;
            if (!$ok) $failureDetails[] = 'Envio: ' . ($mailError !== '' ? $mailError : 'wp_mail devolveu false.');
            Emails::log_email([
                'email_type' => $test ? 'scheduled_report_test' : (!empty($context['manual_dispatch']) ? 'scheduled_report_manual' : 'scheduled_report'),
                'context_key' => 'analytics_report',
                'client_id' => (int)$row['client_id'], 'project_id' => (int)$row['project_id'],
                'sender_user_id' => $createdBy,
                'route_id' => absint($context['route_id'] ?? 0),
                'recipient_user_id' => (int)($recipient['user_id'] ?? 0),
                'recipient_email' => (string)$recipient['email'],
                'recipient_name' => (string)($recipient['name'] ?? ''),
                'message_kind' => (string)($recipient['type'] ?? ''),
                'subject' => $subject,
                'body' => wp_strip_all_tags($intro),
                'meta' => [
                    'schedule_id' => $id,
                    'periods' => array_keys($ranges),
                    'formats' => $formats,
                    'attachments' => array_map('basename', (array)$bundle['attachments']),
                    'attachment_bytes' => (int)$bundle['total_size'],
                    'report_scope' => (string)$profile['scope_key'],
                    'report_user_id' => (int)$profile['user_id'],
                    'audience' => (string)$profile['audience'],
                    'test' => $test ? 1 : 0,
                    'recipient_source' => (string)($recipient['source'] ?? ''),
                        'trigger_type' => sanitize_key((string)($context['trigger_type'] ?? ($row['automation_type'] ?? 'weekly'))),
                        'anchor_date' => self::validDate((string)($context['anchor_date'] ?? '')),
                        'route_id' => absint($context['route_id'] ?? 0),
                    'error' => $ok ? '' : ($mailError !== '' ? $mailError : 'wp_mail devolveu false.'),
                    'outbox_id' => absint($mailDispatch['outbox_id'] ?? 0),
                    'transport_accepted' => $mailSent ? 1 : 0,
                    'outbox_status' => sanitize_key((string)($mailDispatch['status'] ?? '')),
                    'message_id' => (string)($mailDispatch['message_id'] ?? ''),
                    'deduplicated' => !empty($mailDispatch['deduplicated']) ? 1 : 0,
                ],
                // 9AI legacy Release Gate marker, inert for 9DO compatibility:
                // 'mail_result' => !empty($mailDispatch['sent']) ? 'sent' : ($ok ? 'queued' : 'failed')
                'mail_result' => $ok && $mailSent ? 'sent' : ($ok ? 'queued' : 'failed'),
            ]);
            self::releaseReportBundle($bundles, $bundleUses, $bundleKey);
        }

        foreach ($bundles as $bundle) self::cleanupReportBundle($bundle);

        $status = $failed ? ($accepted ? 'partial' : 'failed') : ($queued > 0 ? 'queued' : 'sent');
        $message = $delivered . ' enviados, ' . $queued . ' em fila, ' . $failed . ' falhados, ' . $generatedBundles . ' pacotes de dados e ' . $generatedAttachments . ' anexos gerados.';
        if ($failureDetails) {
            $details = array_values(array_unique(array_filter(array_map('sanitize_text_field', $failureDetails))));
            if ($details) $message .= ' ' . implode(' | ', array_slice($details, 0, 2));
        }
        if (strlen($message) > 250) $message = substr($message, 0, 247) . '...';
        self::maybeUpdateExecutionState($id, $test, $status, $message, $context);
        wp_set_current_user($previousUser);
        // 9DO: partial success is NOT a terminal Daily Close. Releasing only the
        // affected audience claim allows a bounded retry; accepted recipients are
        // protected individually by their persistent Outbox identity.
        if ($failed > 0 && !$test && absint($context['route_id'] ?? 0) > 0
            && sanitize_key((string)($row['automation_type'] ?? '')) === 'daily_close') {
            return new \WP_Error($accepted ? 'daily_close_partial' : 'daily_close_failed', $message);
        }
        return $accepted ? true : new \WP_Error('mail_failed', 'Não foi possível aceitar o relatório para envio.');
    }

    private static function executeOrderSuggestion(int $id, array $row, array $recipients, bool $test, array $context, int $createdBy, int $previousUser) {
        $submissionId = absint($context['submission_id'] ?? 0);
        if ($submissionId <= 0) {
            $submissionId = self::latestOrderSuggestionSubmissionId((int)$row['client_id'], (int)$row['project_id'], absint($context['report_user_id'] ?? 0));
        }
        $mailEligibility = $submissionId > 0 ? FormSubmissions::orderSuggestionMailEligibility($submissionId) : ['eligible'=>true,'reason'=>'latest_lookup'];
        if ($submissionId > 0 && empty($mailEligibility['eligible'])) {
            self::releaseOrderRecipientClaims($context);
            self::maybeUpdateExecutionState(
                $id,
                $test,
                'sent',
                'Sem envio: a Sugestão de encomenda estava inativa pela condição do formulário (' . sanitize_key((string)($mailEligibility['reason'] ?? 'inactive')) . ').',
                $context
            );
            wp_set_current_user($previousUser);
            return true;
        }
        $suggestion = $submissionId > 0 ? FormSubmissions::getOrderSuggestionSubmission($submissionId) : [];
        if (!$suggestion) {
            self::releaseOrderRecipientClaims($context);
            if (!$test && empty($context['manual_dispatch'])) self::scheduleOrderSuggestionRetry($submissionId, absint($context['order_retry_attempt'] ?? 0));
            self::maybeUpdateExecutionState($id, $test, 'failed', 'Não existe uma sugestão de encomenda disponível para este âmbito.', $context);
            wp_set_current_user($previousUser);
            return new \WP_Error('order_suggestion_missing', 'Não existe uma sugestão de encomenda disponível para este âmbito.');
        }
        // Excel and PDF are part of the order-suggestion delivery contract, not optional decorations.
        $formats = ['xlsx','pdf'];
        $summary = (array)($suggestion['summary'] ?? []);
        $tokens = [
            '{client_name}' => (string)($suggestion['client_name'] ?? ''),
            '{project_name}' => (string)($suggestion['project_name'] ?? ''),
            '{period_label}' => 'Sugestão de encomenda',
            '{date}' => wp_date('d/m/Y'),
            '{store_name}' => (string)($suggestion['location_name'] ?? ''),
            '{operative_name}' => (string)($suggestion['operative_name'] ?? ''),
            '{submission_id}' => (string)$submissionId,
            '{order_boxes}' => (string)($summary['order_boxes'] ?? 0),
            '{order_units}' => (string)($summary['order_units'] ?? 0),
            '{order_lines}' => (string)($summary['orders'] ?? count((array)$suggestion['rows'])),
        ];
        $subject = strtr((string)($row['subject_template'] ?? '[FieldFlow] Sugestão de encomenda, {store_name}'), $tokens);
        if ($test) $subject = '[TESTE] ' . $subject;
        $intro = strtr((string)($row['intro_text'] ?? ''), $tokens);
        self::$brand = sanitize_hex_color((string)($row['brand_primary'] ?? '')) ?: '#172033';
        $attachments = [];
        $labels = [];
        try {
            foreach ($formats as $format) {
                $file = FormSubmissions::createOrderSuggestionReportFile($submissionId, $format);
                if (!empty($file['path']) && is_file($file['path'])) {
                    $attachments[] = $file['path'];
                    $labels[] = strtoupper($format) . ' · ' . basename((string)($file['filename'] ?? $file['path']));
                }
            }
        } catch (\Throwable $e) {
            foreach ($attachments as $path) if (is_file($path)) @unlink($path);
            self::releaseOrderRecipientClaims($context);
            if (!$test && empty($context['manual_dispatch'])) self::scheduleOrderSuggestionRetry($submissionId, absint($context['order_retry_attempt'] ?? 0));
            self::maybeUpdateExecutionState($id, $test, 'failed', 'Falha a gerar anexos: ' . sanitize_text_field($e->getMessage()), $context);
            wp_set_current_user($previousUser);
            return new \WP_Error('order_suggestion_attachments', $e->getMessage());
        }
        if (count($attachments) !== count($formats)) {
            foreach ($attachments as $path) if (is_file($path)) @unlink($path);
            self::releaseOrderRecipientClaims($context);
            if (!$test && empty($context['manual_dispatch'])) self::scheduleOrderSuggestionRetry($submissionId, absint($context['order_retry_attempt'] ?? 0));
            self::maybeUpdateExecutionState($id, $test, 'failed', 'Excel e PDF são obrigatórios e não foram ambos gerados.', $context);
            wp_set_current_user($previousUser);
            return new \WP_Error('order_suggestion_attachments_incomplete', 'Não foi possível gerar os dois anexos obrigatórios, Excel e PDF.');
        }
        $totalSize = array_sum(array_map(static fn($path) => is_file($path) ? (int)filesize($path) : 0, $attachments));
        if ($totalSize > 18 * 1024 * 1024) {
            foreach ($attachments as $path) if (is_file($path)) @unlink($path);
            self::releaseOrderRecipientClaims($context);
            if (!$test && empty($context['manual_dispatch'])) self::scheduleOrderSuggestionRetry($submissionId, absint($context['order_retry_attempt'] ?? 0));
            self::maybeUpdateExecutionState($id, $test, 'failed', 'Os anexos da sugestão ultrapassam 18 MB.', $context);
            wp_set_current_user($previousUser);
            return new \WP_Error('order_suggestion_size', 'Os anexos da sugestão ultrapassam 18 MB.');
        }
        $headers = self::mailHeaders();
        $body = self::orderSuggestionEmailBody($intro, $suggestion, $labels, $test);
        $accepted=0;$delivered=0;$queued=0;$failed=0;$errors=[];
        foreach ($recipients as $recipient) {
            $dispatchContext=['source'=>'order_suggestion','client_id'=>(int)($context['client_id']??0),'project_id'=>(int)($context['project_id']??0),'route_id'=>(int)($context['route_id']??0),'test'=>$test,'manual_dispatch'=>!empty($context['manual_dispatch'])];
            if(!$test && empty($context['manual_dispatch'])){
                $dispatchContext['idempotency_key']='order_suggestion|'.$id.'|'.$submissionId.'|'.sanitize_key((string)($context['order_fingerprint']??'')).'|'.sanitize_key((string)($context['order_audience']??($recipient['type']??''))).'|'.strtolower((string)$recipient['email']);
            }
            $mailDispatch=\RoutesPro\Services\MailDispatchService::dispatch((string)$recipient['email'],$subject,$body,$headers,$attachments,$dispatchContext);
            $ok=!empty($mailDispatch['accepted']);
            $mailSent=!empty($mailDispatch['sent']);
            $mailError=(string)($mailDispatch['error']??'');
            if($ok){$accepted++;if($mailSent)$delivered++;else $queued++;}else{$failed++;}
            if(!$ok){
                $errors[]=$mailError!==''?$mailError:'wp_mail devolveu false.';
                self::releaseOrderRecipientClaims($context, [(string)($recipient['email'] ?? '')]);
            }
            Emails::log_email([
                'email_type'=>$test?'order_suggestion_test':(!empty($context['manual_dispatch'])?'order_suggestion_manual':'order_suggestion'),'context_key'=>'order_suggestion',
                'client_id'=>(int)$row['client_id'],'project_id'=>(int)$row['project_id'],'sender_user_id'=>$createdBy,
                'recipient_user_id'=>(int)($recipient['user_id']??0),'recipient_email'=>(string)$recipient['email'],'recipient_name'=>(string)($recipient['name']??''),'message_kind'=>(string)($recipient['type']??''),
                'subject'=>$subject,'body'=>wp_strip_all_tags($intro),'meta'=>[
                    'schedule_id'=>$id,'submission_id'=>$submissionId,'route_id'=>(int)($suggestion['route_id']??0),'route_stop_id'=>(int)($suggestion['route_stop_id']??0),
                    'location_id'=>(int)($suggestion['location_id']??0),'operative_user_id'=>(int)($suggestion['operative_user_id']??0),'formats'=>$formats,
                    'attachments'=>array_map('basename',$attachments),'attachment_bytes'=>$totalSize,'order_lines'=>(int)($summary['orders']??0),'order_boxes'=>$summary['order_boxes']??0,'order_units'=>$summary['order_units']??0,
                    'mail_eligibility_reason'=>sanitize_key((string)(($suggestion['mail_eligibility']['reason'] ?? $mailEligibility['reason'] ?? 'active'))),
                    'test'=>$test?1:0,'trigger_type'=>sanitize_key((string)($context['trigger_type']??'order_suggestion')),'error'=>$ok?'':($mailError!==''?$mailError:'wp_mail devolveu false.'),
                ],'mail_result'=>!empty($mailDispatch['sent'])?'sent':($ok?'queued':'failed'),
            ]);
        }
        foreach ($attachments as $path) if (is_file($path)) @unlink($path);
        if ($failed > 0 && !$test && empty($context['manual_dispatch'])) self::scheduleOrderSuggestionRetry($submissionId, absint($context['order_retry_attempt'] ?? 0));
        $status=$failed?($accepted?'partial':'failed'):($queued>0?'queued':'sent');
        $message=$delivered.' enviados, '.$queued.' em fila, '.$failed.' falhados. Sugestão #'.$submissionId.' com '.(int)($summary['orders']??0).' referências.';
        if($errors)$message.=' '.implode(' | ',array_slice(array_unique($errors),0,2));
        self::maybeUpdateExecutionState($id,$test,$status,substr($message,0,250),$context);
        wp_set_current_user($previousUser);
        return $accepted?true:new \WP_Error('mail_failed','Não foi possível aceitar a sugestão de encomenda para envio.');
    }

    private static function orderSuggestionEmailBody(string $intro, array $suggestion, array $labels, bool $test): string {
        $brand = self::$brand ?: '#172033';
        $summary = (array)($suggestion['summary'] ?? []);
        $cards = '';
        $visibleRows = array_slice((array)($suggestion['rows'] ?? []), 0, 12);
        foreach ($visibleRows as $order) {
            $product = trim(((string)($order['ref'] ?? '') !== '' ? (string)$order['ref'] . ' · ' : '') . (string)($order['name'] ?? ''));
            if ($product === '') $product = 'Produto';
            $category = trim((string)($order['category'] ?? '')) ?: 'Sem categoria';
            $cards .= '<table class="ff-order-card" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:100%;table-layout:fixed;border-collapse:collapse;margin:0 0 8px;background:#ffffff;border:1px solid #e2e8f0;box-sizing:border-box">'
                . '<tr><td style="padding:11px 12px;border-bottom:1px solid #e2e8f0;font-size:13px;line-height:18px;font-weight:700;color:#0f172a;overflow-wrap:anywhere;word-break:break-word">' . esc_html($product) . '</td></tr>'
                . '<tr><td style="padding:0"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:100%;table-layout:fixed;border-collapse:collapse">'
                . '<tr><td width="50%" valign="top" style="width:50%;padding:9px 10px;box-sizing:border-box;overflow-wrap:anywhere;word-break:break-word"><div style="font-size:9px;line-height:13px;letter-spacing:.06em;text-transform:uppercase;color:#64748b;font-weight:700">Categoria</div><div style="margin-top:2px;font-size:12px;line-height:17px;color:#334155;overflow-wrap:anywhere;word-break:break-word">' . esc_html($category) . '</div></td>'
                . '<td width="25%" valign="top" align="right" style="width:25%;padding:9px 6px;box-sizing:border-box"><div style="font-size:9px;line-height:13px;letter-spacing:.06em;text-transform:uppercase;color:#64748b;font-weight:700">Caixas</div><div style="margin-top:2px;font-size:14px;line-height:18px;color:#0f172a;font-weight:800">' . esc_html((string)($order['order_qty'] ?? '')) . '</div></td>'
                . '<td width="25%" valign="top" align="right" style="width:25%;padding:9px 10px 9px 6px;box-sizing:border-box"><div style="font-size:9px;line-height:13px;letter-spacing:.06em;text-transform:uppercase;color:#64748b;font-weight:700;overflow-wrap:anywhere;word-break:break-word">Unidades</div><div style="margin-top:2px;font-size:14px;line-height:18px;color:#0f172a;font-weight:800">' . esc_html((string)($order['units_total'] ?? '')) . '</div></td></tr>'
                . '</table></td></tr></table>';
        }
        $hiddenCount = max(0, count((array)($suggestion['rows'] ?? [])) - count($visibleRows));
        if ($hiddenCount > 0) {
            $cards .= '<div style="margin:8px 0 0;padding:10px 12px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;font-size:11px;line-height:16px">Mais ' . (int)$hiddenCount . ' referência(s) no Excel e PDF anexos.</div>';
        }
        $attachmentItems = '';
        foreach ($labels as $label) $attachmentItems .= '<li style="margin:5px 0;overflow-wrap:anywhere;word-break:break-word">' . esc_html($label) . '</li>';
        $testBanner = $test ? '<tr><td style="padding:11px 18px;background:#fef3c7;color:#92400e;font-weight:700;border-bottom:1px solid #fde68a">Envio de teste, sem impacto operacional.</td></tr>' : '';
        $css = Emails::responsive_email_css();
        return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="x-apple-disable-message-reformatting"><meta name="format-detection" content="telephone=no,date=no,address=no,email=no">'
            . '<!--[if mso]><style type="text/css">body,table,td,p,a,span{font-family:Arial,Helvetica,sans-serif!important}table{border-collapse:collapse!important}</style><![endif]--><style>' . $css . '</style></head>'
            . '<body class="ff-email-body" style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%">'
            . '<table class="ff-email-viewport" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f1f5f9" style="width:100%;max-width:100%;background:#f1f5f9;border-collapse:collapse"><tr><td class="ff-email-outer-pad" align="center" style="padding:24px 12px">'
            . '<!--[if mso]><table role="presentation" width="680" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->'
            . '<table class="ff-email-shell" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:100%;max-width:680px;background:#ffffff;border-collapse:collapse;border:1px solid #e2e8f0;box-sizing:border-box">'
            . $testBanner
            . '<tr><td class="ff-email-pad" bgcolor="' . esc_attr($brand) . '" style="padding:22px 24px;background:' . esc_attr($brand) . ';color:#ffffff;overflow-wrap:anywhere;word-break:break-word"><div style="font-size:11px;line-height:16px;letter-spacing:.08em;text-transform:uppercase;opacity:.8">FieldFlow · Sugestão de encomenda</div><div style="margin-top:7px;font-size:24px;line-height:31px;font-weight:700;overflow-wrap:anywhere;word-break:break-word">' . esc_html((string)($suggestion['location_name'] ?? 'Loja')) . '</div><div style="margin-top:5px;font-size:13px;line-height:19px;opacity:.9;overflow-wrap:anywhere;word-break:break-word">' . esc_html((string)($suggestion['project_name'] ?? '')) . ' · ' . esc_html((string)($suggestion['operative_name'] ?? '')) . '</div></td></tr>'
            . '<tr><td class="ff-email-content" style="padding:24px;max-width:100%;box-sizing:border-box;overflow-wrap:anywhere;word-break:break-word"><p style="margin:0 0 18px;line-height:1.55">' . nl2br(esc_html($intro)) . '</p>'
            . '<table class="ff-order-summary ff-data-table" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:100%;table-layout:fixed;border-collapse:collapse;margin:0 0 18px"><tr>'
            . '<td class="ff-order-summary-cell" width="33.33%" valign="top" style="width:33.33%;padding:0 4px 0 0;box-sizing:border-box"><div style="padding:12px 9px;background:#f8fafc;border:1px solid #e2e8f0"><div style="font-size:9px;line-height:13px;text-transform:uppercase;color:#64748b;font-weight:700;overflow-wrap:anywhere;word-break:break-word">Referências</div><div style="font-size:21px;line-height:27px;font-weight:800;margin-top:2px">' . (int)($summary['orders'] ?? 0) . '</div></div></td>'
            . '<td class="ff-order-summary-cell" width="33.33%" valign="top" style="width:33.33%;padding:0 2px;box-sizing:border-box"><div style="padding:12px 9px;background:#f8fafc;border:1px solid #e2e8f0"><div style="font-size:9px;line-height:13px;text-transform:uppercase;color:#64748b;font-weight:700">Caixas</div><div style="font-size:21px;line-height:27px;font-weight:800;margin-top:2px">' . esc_html((string)($summary['order_boxes'] ?? 0)) . '</div></div></td>'
            . '<td class="ff-order-summary-cell" width="33.33%" valign="top" style="width:33.33%;padding:0 0 0 4px;box-sizing:border-box"><div style="padding:12px 9px;background:#f8fafc;border:1px solid #e2e8f0"><div style="font-size:9px;line-height:13px;text-transform:uppercase;color:#64748b;font-weight:700;overflow-wrap:anywhere;word-break:break-word">Unidades</div><div style="font-size:21px;line-height:27px;font-weight:800;margin-top:2px">' . esc_html((string)($summary['order_units'] ?? 0)) . '</div></div></td></tr></table>'
            . '<div style="margin:0 0 8px;font-size:13px;font-weight:800;color:#334155">Produtos sugeridos</div>' . $cards
            . ($attachmentItems !== '' ? '<div style="margin-top:20px"><strong>Anexos</strong><ul style="padding-left:18px;margin-bottom:0">' . $attachmentItems . '</ul></div>' : '')
            . '<p style="margin:22px 0 0;color:#64748b;font-size:12px;line-height:18px;overflow-wrap:anywhere;word-break:break-word">Submissão #' . (int)($suggestion['submission_id'] ?? 0) . ' · ' . esc_html((string)($suggestion['submitted_at'] ?? '')) . '</p></td></tr>'
            . '</table><!--[if mso]></td></tr></table><![endif]--></td></tr></table></body></html>';
    }

    private static function buildReportBundle(array $row, array $ranges, array $formats, array $profile): array {
        $attachments = [];
        $labels = [];
        $summaries = [];
        $automationType = sanitize_key((string)($row['automation_type'] ?? ''));
        $attachmentPeriodKeys = array_keys($ranges);
        // Daily Close may show day + week-to-date KPIs in the body, but attaches one canonical XLSX + one PDF for the closed day.
        if ($automationType === 'daily_close' && isset($ranges['daily'])) $attachmentPeriodKeys = ['daily'];
        try {
            foreach ($ranges as $periodKey => $range) {
                $filters = [
                    'from' => $range['from'], 'to' => $range['to'],
                    'client_id' => (int)$row['client_id'], 'project_id' => (int)$row['project_id'],
                    'user_id' => (int)$profile['user_id'], 'location_id' => 0, 'category' => '', 'product' => '', 'role' => '',
                    // Canonical report data uses full operational metric visibility and remains constrained by the concrete recipient scope.
                    'audience' => 'manager', 'self_mode' => !empty($profile['self_mode']), 'prefer_snapshot' => false,
                    'export_full' => (bool)array_intersect(['xlsx','csv'], $formats),
                ];
                $prepared = \RoutesPro\Admin\FormSubmissions::prepareDashboardReport($filters);
                $prepared['context']['report_audience'] = (string)($profile['audience'] ?? 'client');
                $prepared['context']['report_scope'] = (string)($profile['scope_key'] ?? 'complete');
                $summaries[$periodKey] = [
                    'label'=>(string)$range['label'],
                    'from'=>(string)$range['from'],
                    'to'=>(string)$range['to'],
                    'canonical'=>(array)($prepared['data']['canonical_operational_report'] ?? []),
                    'intelligence'=>(array)($prepared['data']['report_intelligence'] ?? []),
                ];
                if (!in_array((string)$periodKey, $attachmentPeriodKeys, true)) continue;
                $generatedForPeriod = [];
                foreach ($formats as $format) {
                    $fileLabel = $periodKey . '-' . (string)$profile['file_suffix'];
                    $file = \RoutesPro\Admin\FormSubmissions::createDashboardReportFileFromPrepared($prepared, $format, $fileLabel);
                    if (!empty($file['path']) && file_exists($file['path']) && (int)@filesize($file['path']) > 0) {
                        $attachments[] = $file['path'];
                        $generatedForPeriod[$format] = true;
                        $friendlyFormat = $format === 'xlsx' ? 'Excel' : ($format === 'csv' ? 'CSV' : 'PDF');
                        $labels[] = $range['label'] . ' · ' . $friendlyFormat . ' · ' . basename((string)($file['filename'] ?? $file['path']));
                    }
                }
                $missingFormats = array_values(array_filter($formats, static fn($format): bool => empty($generatedForPeriod[$format])));
                if ($missingFormats) throw new \RuntimeException('Faltam anexos obrigatorios (' . implode(', ', $missingFormats) . ') para ' . (string)$range['label'] . '.');
            }
        } catch (\Throwable $e) {
            foreach ($attachments as $path) if (is_file($path)) @unlink($path);
            throw $e;
        }

        if ($formats && !$attachments) throw new \RuntimeException('Nenhum anexo foi gerado para este âmbito.');
        $totalSize = array_sum(array_map(static function($p){ return is_file($p) ? filesize($p) : 0; }, $attachments));
        if ($totalSize > 18 * 1024 * 1024) {
            foreach ($attachments as $path) if (is_file($path)) @unlink($path);
            throw new \RuntimeException('Os anexos deste destinatário ultrapassam 18 MB.');
        }
        return ['attachments' => $attachments, 'labels' => $labels, 'summaries'=>$summaries, 'total_size' => $totalSize];
    }

    private static function cleanupReportBundle($bundle): void {
        if (is_wp_error($bundle) || !is_array($bundle)) return;
        foreach ((array)($bundle['attachments'] ?? []) as $path) if (is_file($path)) @unlink($path);
    }

    private static function releaseReportBundle(array &$bundles, array &$uses, string $key): void {
        $uses[$key] = max(0, (int)($uses[$key] ?? 1) - 1);
        if ($uses[$key] > 0 || !array_key_exists($key, $bundles)) return;
        self::cleanupReportBundle($bundles[$key]);
        unset($bundles[$key], $uses[$key]);
    }

    private static function recipientReportProfile(array $recipient, bool $test = false): array {
        $type = sanitize_key((string)($recipient['type'] ?? 'client'));
        $userId = absint($recipient['user_id'] ?? 0);
        $name = trim((string)($recipient['name'] ?? ''));
        if ($type === 'team' && $userId > 0) {
            $safeName = $name !== '' ? $name : ('Utilizador #' . $userId);
            return [
                'cache_key' => 'team:' . $userId,
                'scope_key' => 'merchandiser_personal',
                'audience' => 'merchandiser',
                'user_id' => $userId,
                'self_mode' => true,
                'file_suffix' => 'merchandiser-' . $userId,
                'title' => 'Relatório pessoal de ' . $safeName,
                'description' => 'Inclui apenas rotas, lojas, formulários, métricas e evidências associados a este merchandiser.',
            ];
        }
        return [
            'cache_key' => 'client:complete',
            'scope_key' => $test ? 'client_complete_test' : 'client_complete',
            'audience' => 'client',
            'user_id' => 0,
            'self_mode' => false,
            'file_suffix' => $test ? 'teste-cliente-completo' : 'cliente-completo',
            'title' => $test ? 'Teste da visão completa do cliente' : 'Visão completa da campanha',
            'description' => 'Inclui todos os merchandisers, rotas, lojas, formulários, métricas e evidências do cliente e campanha selecionados.',
        ];
    }

    private static function reportRanges(array $row, array $periods, string $anchorDate = ''): array {
        $tz = wp_timezone();
        if ($anchorDate !== '') {
            $to = new \DateTimeImmutable($anchorDate . ' 00:00:00', $tz);
        } else {
            $today = new \DateTimeImmutable('today', $tz);
            $to = $today->modify('-1 day');
        }
        $out = [];
        if (in_array('daily', $periods, true)) $out['daily'] = ['from'=>$to->format('Y-m-d'),'to'=>$to->format('Y-m-d'),'label'=>'Dia, ' . $to->format('d/m/Y')];
        if (in_array('week_to_date', $periods, true)) {
            $from = $to->modify('-' . ((int)$to->format('N') - 1) . ' days');
            $out['week_to_date'] = ['from'=>$from->format('Y-m-d'),'to'=>$to->format('Y-m-d'),'label'=>'Acumulado semanal, ' . $from->format('d/m') . ' a ' . $to->format('d/m/Y')];
        }
        if (in_array('weekly', $periods, true)) {
            $from = $to->modify('-6 days');
            $out['weekly'] = ['from'=>$from->format('Y-m-d'),'to'=>$to->format('Y-m-d'),'label'=>'Semanal, ' . $from->format('d/m') . ' a ' . $to->format('d/m/Y')];
        }
        if (in_array('cumulative', $periods, true)) {
            $fromDate = self::validDate((string)($row['cumulative_start'] ?? '')) ?: (string)$row['start_date'];
            if ($fromDate > $to->format('Y-m-d')) $fromDate = $to->format('Y-m-d');
            $from = new \DateTimeImmutable($fromDate . ' 00:00:00', $tz);
            $out['cumulative'] = ['from'=>$from->format('Y-m-d'),'to'=>$to->format('Y-m-d'),'label'=>'Acumulado campanha, ' . $from->format('d/m/Y') . ' a ' . $to->format('d/m/Y')];
        }
        return $out;
    }

    private static function userIdsFromProjectMeta($metaJson): array {
        if (is_array($metaJson)) {
            $meta = $metaJson;
        } else {
            $meta = json_decode((string)$metaJson, true);
            if (!is_array($meta)) $meta = [];
        }
        $values = $meta['associated_user_ids'] ?? ($meta['user_ids'] ?? []);
        if (is_string($values)) $values = preg_split('/\s*,\s*/', trim($values));
        $ids = [];
        foreach ((array)$values as $value) {
            if (is_array($value)) $value = $value['user_id'] ?? ($value['id'] ?? 0);
            $uid = absint($value);
            if ($uid > 0) $ids[$uid] = $uid;
        }
        ksort($ids);
        return array_values($ids);
    }

    /**
     * Fonte de verdade dos merchandisers do projeto.
     * Corresponde exatamente a Intelligence Center > Projetos >
     * "Utilizadores com acesso ao projeto". Não herda owners, rotas,
     * lojas ou capacidades WordPress.
     */
    private static function merchandiserUserIds(int $projectId): array {
        global $wpdb;
        if ($projectId <= 0) return [];
        $metaJson = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_json FROM {$wpdb->prefix}routespro_projects WHERE id=%d",
            $projectId
        ));
        return self::userIdsFromProjectMeta($metaJson);
    }

    /**
     * Fonte de verdade dos utilizadores cliente.
     * Junta Intelligence Center > Projetos > "Responsáveis ativos do projeto"
     * e Intelligence Center > Clientes > "Utilizadores associados".
     */
    private static function clientUserIds(int $clientId, int $projectId): array {
        global $wpdb;
        $ids = [];
        foreach (AssignmentResolver::get_client_user_ids($clientId) as $uid) {
            $uid = absint($uid);
            if ($uid > 0) $ids[$uid] = $uid;
        }
        if ($projectId > 0) {
            $table = $wpdb->prefix . 'routespro_project_assignments';
            $rows = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT user_id FROM {$table} WHERE project_id=%d AND is_active=1 AND user_id>0",
                $projectId
            )) ?: [];
            foreach ($rows as $uid) {
                $uid = absint($uid);
                if ($uid > 0) $ids[$uid] = $uid;
            }
        }
        ksort($ids);
        return array_values($ids);
    }

    private static function isTeamUserForProject(int $userId, int $projectId): bool {
        return $userId > 0 && in_array($userId, self::merchandiserUserIds($projectId), true);
    }

    private static function resolveRecipients(array $row): array {
        global $wpdb;
        $clientId = (int)$row['client_id'];
        $projectId = (int)$row['project_id'];
        $px = $wpdb->prefix . 'routespro_';

        $merchandiserIds = self::merchandiserUserIds($projectId);
        $clientUserIds = self::clientUserIds($clientId, $projectId);
        $allUserIds = array_values(array_unique(array_merge($merchandiserIds, $clientUserIds)));
        $knownUsers = [];
        if ($allUserIds) {
            foreach ((array)get_users([
                'include' => $allUserIds,
                'orderby' => 'display_name',
                'order' => 'ASC',
                'fields' => ['ID','display_name','user_email','user_login'],
            ]) as $user) {
                $knownUsers[(int)$user->ID] = $user;
            }
        }

        $out = [];
        $addRecipient = static function(array &$target, array $recipient): void {
            $email = sanitize_email((string)($recipient['email'] ?? ''));
            if (!$email) return;
            $key = strtolower($email);
            $recipient['email'] = $email;
            $existing = $target[$key] ?? null;
            $existingType = is_array($existing) ? (string)($existing['type'] ?? '') : '';
            $newType = (string)($recipient['type'] ?? '');
            // Se o mesmo endereço estiver nos dois universos, prevalece o
            // relatório pessoal do merchandiser, por menor privilégio.
            if ($existingType === 'team' && $newType !== 'team') return;
            if ($newType === 'team') {
                $target[$key] = $recipient;
                return;
            }
            if ($existingType !== '' && $newType === 'extra') return;
            $target[$key] = $recipient;
        };

        if (!empty($row['to_team'])) {
            foreach ($merchandiserIds as $uid) {
                $user = $knownUsers[$uid] ?? null;
                if (!$user) continue;
                $addRecipient($out, [
                    'email' => (string)$user->user_email,
                    'name' => (string)($user->display_name ?: $user->user_login),
                    'user_id' => $uid,
                    'type' => 'team',
                    'source' => 'project_access_users',
                ]);
            }
        }

        if (!empty($row['to_clients'])) {
            foreach ($clientUserIds as $uid) {
                $user = $knownUsers[$uid] ?? null;
                if (!$user) continue;
                $addRecipient($out, [
                    'email' => (string)$user->user_email,
                    'name' => (string)($user->display_name ?: $user->user_login),
                    'user_id' => $uid,
                    'type' => 'client',
                    'source' => 'project_responsibles_or_client_tab',
                ]);
            }
            $client = $wpdb->get_row($wpdb->prepare("SELECT name,email FROM {$px}clients WHERE id=%d", $clientId), ARRAY_A) ?: [];
            $addRecipient($out, [
                'email' => (string)($client['email'] ?? ''),
                'name' => (string)($client['name'] ?? 'Cliente'),
                'user_id' => 0,
                'type' => 'client',
                'source' => 'client_record',
            ]);
        }

        foreach (preg_split('/[,;\s]+/', (string)$row['extra_emails']) ?: [] as $email) {
            $addRecipient($out, ['email' => $email, 'name' => '', 'user_id' => 0, 'type' => 'extra', 'source' => 'extra_email']);
        }
        return array_values($out);
    }

    private static function resolveRecipientsForContext(array $row, array $context): array {
        $mode = sanitize_key((string)($context['recipient_mode'] ?? 'all'));
        if ($mode === '' || $mode === 'all') return self::resolveRecipients($row);
        $all = self::resolveRecipients($row);
        if ($mode === 'client') return array_values(array_filter($all, static fn($r) => (string)($r['type'] ?? '') === 'client'));
        if ($mode === 'extra') return array_values(array_filter($all, static fn($r) => (string)($r['type'] ?? '') === 'extra'));
        if ($mode === 'client_and_extra') return array_values(array_filter($all, static fn($r) => in_array((string)($r['type'] ?? ''), ['client','extra'], true)));
        if ($mode === 'team') return array_values(array_filter($all, static fn($r) => (string)($r['type'] ?? '') === 'team'));
        if ($mode === 'team_user') {
            $uid = absint($context['user_id'] ?? 0);
            $personal = array_values(array_filter($all, static fn($r) => (string)($r['type'] ?? '') === 'team' && (int)($r['user_id'] ?? 0) === $uid));
            if ($personal || $uid <= 0) return $personal;
            // Para eventos associados a uma submissão, o autor persistido é a
            // fonte autoritativa do destinatário pessoal, mesmo que a lista
            // administrativa do projeto tenha sido alterada entretanto.
            $user = get_userdata($uid);
            $email = $user ? sanitize_email((string)$user->user_email) : '';
            if (!$email) return [];
            return [[
                'email' => $email,
                'name' => (string)($user->display_name ?: $user->user_login),
                'user_id' => $uid,
                'type' => 'team',
                'source' => 'submission_operative',
            ]];
        }
        return $all;
    }

    private static function dispatchOptionKey(int $scheduleId, string $audience, int $routeId, int $userId, string $date): string {
        return 'fieldflow_mailcenter_dispatch_' . md5($scheduleId . '|' . $audience . '|' . $routeId . '|' . $userId . '|' . $date);
    }

    /** 9DO: one logical report per recipient, route, schedule, date and audience scope. */
    private static function recipientDispatchIdentity(int $scheduleId, string $email, string $trigger, string $date, string $scope, int $scopeUserId, int $routeId): array {
        $base = 'scheduled_report|' . $scheduleId . '|' . strtolower(sanitize_email($email)) . '|'
            . sanitize_key($trigger) . '|' . self::validDate($date) . '|' . $scope . '|' . $scopeUserId;
        return [
            'key' => $routeId > 0 ? $base . '|route:' . $routeId : $base,
            'legacy' => $routeId > 0 ? $base : '',
        ];
    }

    private static function claimDispatch(int $scheduleId, string $audience, int $routeId, int $userId, string $date): string {
        $key = self::dispatchOptionKey($scheduleId, $audience, $routeId, $userId, $date);
        return add_option($key, current_time('mysql'), '', false) ? $key : '';
    }

    public static function handleCoreEvent(string $eventName, array $payload = [], array $context = [], string $eventUuid = ''): void {
        if ($eventName !== 'route.completed') return;
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        self::queueDailyCloseForRoute(absint($data['route_id'] ?? $context['route_id'] ?? 0));
    }

    /**
     * Queues the canonical Daily Close after a route reaches an operationally
     * terminal state. The in-memory shutdown queue gives immediate delivery,
     * while the single-event retry hook makes the trigger durable when report
     * generation or a transient dependency fails.
     */
    public static function queueDailyCloseForRoute(int $routeId): void {
        $routeId = absint($routeId);
        if ($routeId <= 0) return;
        self::$routeCloseQueue[$routeId] = $routeId;
        if (!self::$routeCloseShutdownRegistered) {
            self::$routeCloseShutdownRegistered = true;
            add_action('shutdown', [self::class, 'flushRouteCloseQueue'], 5);
        }
        self::scheduleDailyCloseRetry($routeId, 0, 5);
    }

    public static function flushRouteCloseQueue(): void {
        $routeIds = array_values(self::$routeCloseQueue);
        self::$routeCloseQueue = [];
        foreach ($routeIds as $routeId) {
            $result = self::dispatchDailyCloseForRoute((int)$routeId, 0);
            self::recordDailyCloseDiagnostic($result);
            if (!empty($result['retry'])) self::scheduleDailyCloseRetry((int)$routeId, 1);
        }
    }

    public static function handleDailyCloseRetry(int $routeId, int $attempt = 0): void {
        $routeId = absint($routeId);
        $attempt = max(0, $attempt);
        if ($routeId <= 0 || $attempt > self::DAILY_CLOSE_RETRY_MAX_ATTEMPTS) return;
        $result = self::dispatchDailyCloseForRoute($routeId, $attempt);
        self::recordDailyCloseDiagnostic($result);
        if (!empty($result['retry']) && $attempt < self::DAILY_CLOSE_RETRY_MAX_ATTEMPTS) {
            self::scheduleDailyCloseRetry($routeId, $attempt + 1);
        }
    }

    /** 9DO: bounded, non-sensitive diagnostic history, independent of email persistence. */
    private static function recordDailyCloseDiagnostic(array $result): void {
        $routeId = absint($result['route_id'] ?? 0);
        if (!$routeId || !function_exists('get_option') || !function_exists('update_option')) return;
        $key = 'fieldflow_daily_close_diagnostics_9do';
        $rows = get_option($key, []);
        if (!is_array($rows)) $rows = [];
        $rows[] = [
            'at'=>current_time('mysql', true),
            'route_id'=>$routeId,
            'attempt'=>absint($result['attempt'] ?? 0),
            'ok'=>!empty($result['ok']) ? 1 : 0,
            'accepted'=>absint($result['accepted'] ?? 0),
            'failed'=>absint($result['failed'] ?? 0),
            'retry'=>!empty($result['retry']) ? 1 : 0,
            'reason'=>substr(sanitize_key((string)($result['reason'] ?? 'unknown')), 0, 120),
        ];
        update_option($key, array_slice($rows, -60), false);
    }

    private static function scheduleDailyCloseRetry(int $routeId, int $attempt, int $overrideDelay = 0): bool {
        $routeId = absint($routeId);
        $attempt = max(0, $attempt);
        if ($routeId <= 0 || $attempt > self::DAILY_CLOSE_RETRY_MAX_ATTEMPTS) return false;
        $args = [$routeId, $attempt];
        if (wp_next_scheduled(self::DAILY_CLOSE_RETRY_HOOK, $args)) return true;
        $delays = [0 => 5, 1 => 60, 2 => 180, 3 => 600, 4 => 1800];
        $delay = $overrideDelay > 0 ? $overrideDelay : ($delays[$attempt] ?? 1800);
        return QueueManager::schedule(self::DAILY_CLOSE_RETRY_HOOK, $args, $delay, [
            'force_durable'=>true,
            'source'=>'daily_close',
            'idempotency_key'=>'daily-close:' . $routeId . ':' . $attempt,
            'max_attempts'=>self::DAILY_CLOSE_RETRY_MAX_ATTEMPTS + 1,
        ]);
    }

    public static function handleVisitCompleted(int $submissionId, array $completion = [], array $answers = []): void {
        $routeId = absint($completion['route_id'] ?? 0);
        if ($routeId <= 0) return;
        if (!empty($completion['route_completed_now'])
            || (!empty($completion['operationally_complete']) && sanitize_key((string)($completion['route_status'] ?? '')) === 'completed')) {
            self::queueDailyCloseForRoute($routeId);
        }
    }

    /**
     * @return array{ok:bool,retry:bool,route_id:int,attempt:int,reason:string,failed:int,accepted:int}
     */
    private static function dispatchDailyCloseForRoute(int $routeId, int $attempt = 0): array {
        global $wpdb;
        $state = ['ok'=>true,'retry'=>false,'route_id'=>absint($routeId),'attempt'=>max(0,$attempt),'reason'=>'','failed'=>0,'accepted'=>0];
        if ($routeId <= 0) { $state['reason']='invalid_route'; return $state; }

        $route = $wpdb->get_row($wpdb->prepare(
            "SELECT id,client_id,project_id,owner_user_id,date,status FROM {$wpdb->prefix}routespro_routes WHERE id=%d",
            $routeId
        ), ARRAY_A) ?: [];
        if (!$route) { $state['reason']='route_missing'; return $state; }
        if (!in_array(sanitize_key((string)($route['status'] ?? '')), ['completed','done'], true)) {
            $state['reason']='route_not_completed';
            $state['retry']=$attempt < self::DAILY_CLOSE_RETRY_MAX_ATTEMPTS;
            return $state;
        }

        $remainingSql = \RoutesPro\Services\VisitCompletionPolicy::remainingSql('rs');
        $remaining = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}routespro_route_stops rs WHERE rs.route_id=%d AND {$remainingSql}",
            $routeId
        ));
        if ($remaining > 0) { $state['reason']='route_not_terminal'; $state['retry']=$attempt < self::DAILY_CLOSE_RETRY_MAX_ATTEMPTS; return $state; }

        // 9AN geographic source-of-truth: legacy routes may carry visible PT/ES
        // endpoint labels with coordinates resolved in another country. Repair
        // that persisted geometry before any Analytics/mail snapshot is built.
        if (class_exists('\\RoutesPro\\Services\\RouteMetricsService')) {
            $geoIntegrity = \RoutesPro\Services\RouteMetricsService::ensureGeographicIntegrityForRoute($routeId, [], false);
            if (empty($geoIntegrity['ok']) && !in_array((string)($geoIntegrity['reason'] ?? ''), ['provider_disabled'], true)) {
                $state['ok'] = false;
                $state['failed'] = 1;
                $state['reason'] = 'route_geography_unresolved:' . sanitize_key((string)($geoIntegrity['reason'] ?? 'unknown'));
                $state['retry'] = $attempt < self::DAILY_CLOSE_RETRY_MAX_ATTEMPTS;
                return $state;
            }
        }

        $clientId = absint($route['client_id'] ?? 0);
        $projectId = absint($route['project_id'] ?? 0);
        $userId = absint($route['owner_user_id'] ?? 0);
        if (!$userId) {
            $userId = absint($wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}routespro_assignments WHERE route_id=%d ORDER BY id ASC LIMIT 1",
                $routeId
            )));
        }
        if (!$userId) {
            $userId = absint($wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}routespro_form_submissions WHERE route_id=%d AND " . SubmissionStatusPolicy::operationalSql() . " ORDER BY id DESC LIMIT 1",
                $routeId
            )));
        }
        $date = self::validDate((string)($route['date'] ?? '')) ?: wp_date('Y-m-d');

        $rows = [];
        if ($clientId > 0 && $projectId > 0) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM " . self::table() . " WHERE is_active=1 AND automation_type='daily_close' AND client_id=%d AND project_id=%d AND start_date<=%s AND (end_date IS NULL OR end_date='' OR end_date>=%s) AND (to_team=1 OR to_clients=1 OR TRIM(COALESCE(extra_emails,''))<>'') ORDER BY id ASC",
                $clientId, $projectId, $date, $date
            ), ARRAY_A) ?: [];
        }

        // If the schedule row disappeared but the project still owns a dedicated
        // email template, recover the daily-close automation from that persisted
        // project template. Deliberately paused rows are never reactivated here.
        if (!$rows) {
            $mailTemplateState = self::projectMailTemplateState($projectId);
            if (!empty($mailTemplateState['override'])) {
                self::ensureDailyCloseAutomation($clientId, $projectId, $date, true);
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM " . self::table() . " WHERE is_active=1 AND automation_type='daily_close' AND client_id=%d AND project_id=%d AND start_date<=%s AND (end_date IS NULL OR end_date='' OR end_date>=%s) AND (to_team=1 OR to_clients=1 OR TRIM(COALESCE(extra_emails,''))<>'') ORDER BY id ASC",
                    $clientId, $projectId, $date, $date
                ), ARRAY_A) ?: [];
            }
        }

        // No Mail Center automation owns this closure. Fall back to the stable
        // route-completed notification, which now has outbox idempotency too.
        if (!$rows) {
            $legacy = \RoutesPro\Services\Notify::route_event($routeId, 'completed', 'Fecho operacional: todas as visitas ficaram concluídas ou justificadas.');
            $state['accepted'] = !empty($legacy['sent']) ? 1 : 0;
            $state['reason'] = !empty($legacy['skipped']) ? 'legacy_' . sanitize_key((string)$legacy['skipped']) : 'legacy_route_notification';
            return $state;
        }

        if ($userId <= 0) {
            $state['ok'] = false;
            $state['failed'] = 1;
            $state['retry'] = $attempt < self::DAILY_CLOSE_RETRY_MAX_ATTEMPTS;
            $state['reason'] = 'route_user_missing';
            return $state;
        }

        foreach ($rows as $row) {
            $scheduleId = absint($row['id'] ?? 0);
            if (!$scheduleId) continue;
            $deliveryMode = sanitize_key((string)($row['client_delivery_mode'] ?? 'immediate')) ?: 'immediate';
            $audiences = [];
            if (!empty($row['to_team'])) $audiences[] = ['claim'=>'team','mode'=>'team_user'];
            if (!empty($row['to_clients']) && $deliveryMode === 'immediate') $audiences[] = ['claim'=>'client','mode'=>'client'];
            if (trim((string)($row['extra_emails'] ?? '')) !== '' && $deliveryMode === 'immediate') $audiences[] = ['claim'=>'extra','mode'=>'extra'];

            foreach ($audiences as $audience) {
                $context = [
                    'anchor_date'=>$date,
                    'recipient_mode'=>(string)$audience['mode'],
                    'user_id'=>$userId,
                    'report_user_id'=>$userId,
                    'route_id'=>$routeId,
                    'trigger_type'=>'daily_close',
                ];
                // An address present in two audience lists is assigned to the
                // stricter profile by resolveRecipients(). An empty audience is
                // not a delivery failure and must not poison retries.
                $resolved = self::resolveRecipientsForContext($row, $context);
                if (!$resolved) continue;
                $claim = self::claimDispatch($scheduleId, (string)$audience['claim'], $routeId, $userId, $date);
                if ($claim === '') continue;
                $context['_resolved_recipients'] = $resolved;
                $result = self::execute($scheduleId, false, '', 0, $context);
                if (is_wp_error($result)) {
                    delete_option($claim);
                    $state['failed']++;
                    $state['ok'] = false;
                } else {
                    $state['accepted']++;
                }
            }
        }

        if ($state['failed'] > 0 && $attempt < self::DAILY_CLOSE_RETRY_MAX_ATTEMPTS) $state['retry'] = true;
        $state['reason'] = $state['failed'] > 0 ? 'mail_center_partial_or_failed' : 'mail_center_dispatched';
        return $state;
    }

    public static function handleOrderSuggestionSubmission(int $submissionId, array $payload = [], array $answers = []): array {
        return self::dispatchOrderSuggestionSubmission($submissionId, 0);
    }

    public static function handleOrderSuggestionRetry(int $submissionId, int $attempt = 1): array {
        return self::dispatchOrderSuggestionSubmission($submissionId, max(1, $attempt));
    }

    private static function dispatchOrderSuggestionSubmission(int $submissionId, int $retryAttempt = 0): array {
        $submissionId = absint($submissionId);
        $mailEligibility = FormSubmissions::orderSuggestionMailEligibility($submissionId);
        $evidence = is_array($mailEligibility['persisted_evidence'] ?? null)
            ? (array)$mailEligibility['persisted_evidence']
            : FormSubmissions::persistedOrderSuggestionEvidence($submissionId);

        if (empty($mailEligibility['eligible'])) {
            $reason = sanitize_key((string)($mailEligibility['reason'] ?? 'inactive')) ?: 'inactive';
            self::recordOrderSuggestionMailState($submissionId, 'not_applicable', $reason, [
                'attempt' => $retryAttempt,
                'positive_rows' => absint($evidence['positive_rows'] ?? 0),
                'hidden_positive_keys' => (array)($evidence['hidden_positive_keys'] ?? []),
            ]);
            return ['status'=>'not_applicable','reason'=>$reason,'attempt'=>$retryAttempt];
        }

        $suggestion = FormSubmissions::getOrderSuggestionSubmission($submissionId);
        if (!$suggestion) {
            return self::orderSuggestionPreDispatchFailure($submissionId, $retryAttempt, 'suggestion_missing', 'A sugestão de encomenda positiva ficou gravada, mas não foi possível reconstruir as linhas para envio.');
        }
        $clientId = absint($suggestion['client_id'] ?? 0);
        $projectId = absint($suggestion['project_id'] ?? 0);
        $userId = absint($suggestion['operative_user_id'] ?? 0);
        if (!$clientId || !$projectId) {
            return self::orderSuggestionPreDispatchFailure($submissionId, $retryAttempt, 'scope_missing', 'A sugestão de encomenda ficou gravada sem cliente/projeto resolvido para o envio.');
        }
        $date = self::validDate((string)($suggestion['report_day'] ?? '')) ?: wp_date('Y-m-d');
        self::ensureOrderSuggestionAutomation($clientId, $projectId, $date);
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM ".self::table()." WHERE is_active=1 AND automation_type='order_suggestion' AND client_id=%d AND project_id=%d AND start_date<=%s AND (end_date IS NULL OR end_date='' OR end_date>=%s) ORDER BY id ASC",
            $clientId,$projectId,$date,$date
        ), ARRAY_A) ?: [];
        if (!$rows) {
            return self::orderSuggestionPreDispatchFailure($submissionId, $retryAttempt, 'automation_missing', 'Não existe uma automação ativa capaz de entregar esta sugestão de encomenda.');
        }

        $fingerprint = substr(hash('sha256', wp_json_encode($suggestion['rows'] ?? [])), 0, 20);
        $attempted = 0;
        $accepted = 0;
        $failed = 0;
        $reasons = [];
        foreach ($rows as $row) {
            $scheduleId = absint($row['id'] ?? 0);
            if (!$scheduleId) continue;
            if (!empty($row['to_team']) && $userId > 0) {
                $result = self::dispatchOrderSuggestionAudience($row, $submissionId, $fingerprint, 'team', $userId, $retryAttempt);
                $attempted += (int)($result['attempted'] ?? 0);
                $accepted += (int)($result['accepted'] ?? 0);
                $failed += (int)($result['failed'] ?? 0);
                if (!empty($result['reason'])) $reasons[] = sanitize_key((string)$result['reason']);
            }
            if (!empty($row['to_clients'])) {
                $result = self::dispatchOrderSuggestionAudience($row, $submissionId, $fingerprint, 'client', $userId, $retryAttempt);
                $attempted += (int)($result['attempted'] ?? 0);
                $accepted += (int)($result['accepted'] ?? 0);
                $failed += (int)($result['failed'] ?? 0);
                if (!empty($result['reason'])) $reasons[] = sanitize_key((string)$result['reason']);
            }
        }

        if ($attempted <= 0 && $accepted <= 0) {
            return self::orderSuggestionPreDispatchFailure($submissionId, $retryAttempt, 'recipients_missing', 'A sugestão de encomenda ficou gravada, mas não existem destinatários resolvidos para o envio.');
        }
        if ($failed > 0 && $accepted <= 0) {
            $scheduled = self::scheduleOrderSuggestionRetry($submissionId, $retryAttempt);
            $status = $scheduled ? 'failed_retry' : 'failed';
            self::recordOrderSuggestionMailState($submissionId, $status, $reasons ? implode(',', array_values(array_unique($reasons))) : 'dispatch_failed', [
                'attempt'=>$retryAttempt,'attempted'=>$attempted,'accepted'=>$accepted,'failed'=>$failed,
            ]);
            return ['status'=>$status,'reason'=>'dispatch_failed','attempt'=>$retryAttempt,'retry_scheduled'=>$scheduled];
        }

        $status = $failed > 0 ? 'partial_retry' : 'queued_or_sent';
        $scheduled = false;
        if ($failed > 0) $scheduled = self::scheduleOrderSuggestionRetry($submissionId, $retryAttempt);
        self::recordOrderSuggestionMailState($submissionId, $status, $failed > 0 ? 'partial_dispatch' : 'accepted_by_outbox', [
            'attempt'=>$retryAttempt,'attempted'=>$attempted,'accepted'=>$accepted,'failed'=>$failed,'retry_scheduled'=>$scheduled ? 1 : 0,
        ]);
        return ['status'=>$status,'reason'=>$failed > 0 ? 'partial_dispatch' : 'accepted_by_outbox','attempt'=>$retryAttempt,'attempted'=>$attempted,'accepted'=>$accepted,'failed'=>$failed,'retry_scheduled'=>$scheduled];
    }

    private static function dispatchOrderSuggestionAudience(array $row, int $submissionId, string $fingerprint, string $audience, int $userId, int $retryAttempt): array {
        $scheduleId = absint($row['id'] ?? 0);
        if (!$scheduleId) return ['attempted'=>0,'accepted'=>0,'failed'=>1,'reason'=>'schedule_missing'];
        $context = [
            'submission_id' => $submissionId,
            'recipient_mode' => $audience === 'team' ? 'team_user' : 'client',
            'report_user_id' => $userId,
            'trigger_type' => $retryAttempt > 0 ? 'order_suggestion_retry' : 'order_suggestion',
            'order_retry_attempt' => $retryAttempt,
            'order_fingerprint' => $fingerprint,
            'order_audience' => $audience,
        ];
        if ($audience === 'team') $context['user_id'] = $userId;
        $recipients = self::resolveRecipientsForContext($row, $context);
        if (!$recipients) return ['attempted'=>0,'accepted'=>0,'failed'=>1,'reason'=>'recipients_missing'];
        $claimed = [];
        $claims = [];
        foreach ($recipients as $recipient) {
            $email = strtolower(sanitize_email((string)($recipient['email'] ?? '')));
            if ($email === '') continue;
            $claim = self::claimOrderRecipientDispatch($scheduleId, $audience, $submissionId, $fingerprint, $email);
            if ($claim === '') continue;
            $claimed[] = $recipient;
            $claims[$email] = $claim;
        }
        // No new claims means this exact recipient/fingerprint was already
        // accepted before. Treat it as idempotent success, not a missing mail.
        if (!$claimed) return ['attempted'=>count($recipients),'accepted'=>count($recipients),'failed'=>0,'reason'=>'already_claimed'];
        $context['_resolved_recipients'] = $claimed;
        $context['_recipient_claims'] = $claims;
        $result = self::execute($scheduleId, false, '', 0, $context);
        if (is_wp_error($result)) {
            return ['attempted'=>count($claimed),'accepted'=>0,'failed'=>count($claimed),'reason'=>sanitize_key($result->get_error_code() ?: 'dispatch_failed')];
        }
        return ['attempted'=>count($claimed),'accepted'=>count($claimed),'failed'=>0,'reason'=>'accepted_by_outbox'];
    }

    private static function orderSuggestionPreDispatchFailure(int $submissionId, int $retryAttempt, string $reason, string $message): array {
        $scheduled = self::scheduleOrderSuggestionRetry($submissionId, $retryAttempt);
        $status = $scheduled ? 'failed_retry' : 'failed';
        self::recordOrderSuggestionMailState($submissionId, $status, $reason, [
            'attempt' => $retryAttempt,
            'message' => sanitize_text_field($message),
            'retry_scheduled' => $scheduled ? 1 : 0,
        ]);
        if (class_exists('\\RoutesPro\\Support\\Logger')) {
            \RoutesPro\Support\Logger::warning($message, [
                'context_key' => 'order_suggestion_mail_continuity',
                'submission_id' => $submissionId,
                'reason' => $reason,
                'attempt' => $retryAttempt,
                'retry_scheduled' => $scheduled ? 1 : 0,
            ]);
        }
        return ['status'=>$status,'reason'=>$reason,'attempt'=>$retryAttempt,'retry_scheduled'=>$scheduled];
    }

    private static function recordOrderSuggestionMailState(int $submissionId, string $status, string $reason, array $extra = []): void {
        global $wpdb;
        $submissionId = absint($submissionId);
        if ($submissionId <= 0 || !class_exists('\\RoutesPro\\Forms\\FormsModule')) return;
        $table = \RoutesPro\Forms\FormsModule::table_submissions();
        $row = $wpdb->get_row($wpdb->prepare("SELECT meta_json FROM {$table} WHERE id=%d LIMIT 1", $submissionId), ARRAY_A) ?: [];
        $meta = !empty($row['meta_json']) ? json_decode((string)$row['meta_json'], true) : [];
        if (!is_array($meta)) $meta = [];
        $history = is_array($meta['order_suggestion_mail_history'] ?? null) ? array_values($meta['order_suggestion_mail_history']) : [];
        $state = array_merge([
            'status' => sanitize_key($status),
            'reason' => sanitize_key($reason),
            'updated_at' => current_time('mysql'),
            'updated_at_gmt' => current_time('mysql', true),
        ], $extra);
        $history[] = $state;
        $meta['order_suggestion_mail'] = $state;
        $meta['order_suggestion_mail_history'] = array_slice($history, -12);
        $wpdb->update($table, ['meta_json'=>wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], ['id'=>$submissionId], ['%s'], ['%d']);
    }

    private static function claimOrderRecipientDispatch(int $scheduleId, string $audience, int $submissionId, string $fingerprint, string $email): string {
        $email = strtolower(sanitize_email($email));
        if ($email === '') return '';
        $key = 'fieldflow_order_suggestion_dispatch_' . md5($scheduleId . '|' . $audience . '|' . $submissionId . '|' . $fingerprint . '|' . $email);
        if (add_option($key, current_time('mysql'), '', false)) return $key;
        // A process can die after claiming and before handing the message to the
        // Outbox. Reclaim abandoned claims after two hours so they cannot block
        // the same order forever. Fresh claims remain strict idempotency guards.
        $claimedAt = (string)get_option($key, '');
        $claimedTs = $claimedAt !== '' ? strtotime($claimedAt) : false;
        if ($claimedTs && $claimedTs < (time() - 2 * HOUR_IN_SECONDS)) {
            delete_option($key);
            if (add_option($key, current_time('mysql'), '', false)) return $key;
        }
        return '';
    }

    private static function releaseOrderRecipientClaims(array $context, array $emails = []): void {
        $claims = is_array($context['_recipient_claims'] ?? null) ? $context['_recipient_claims'] : [];
        if (!$claims) return;
        $wanted = [];
        foreach ($emails as $email) {
            $email = strtolower(sanitize_email((string)$email));
            if ($email !== '') $wanted[$email] = true;
        }
        foreach ($claims as $email => $optionKey) {
            $normalized = strtolower(sanitize_email((string)$email));
            if ($wanted && empty($wanted[$normalized])) continue;
            if ((string)$optionKey !== '') delete_option((string)$optionKey);
        }
    }

    private static function scheduleOrderSuggestionRetry(int $submissionId, int $currentAttempt): bool {
        if ($submissionId <= 0 || $currentAttempt >= self::ORDER_RETRY_MAX_ATTEMPTS) return false;
        $nextAttempt = $currentAttempt + 1;
        $args = [$submissionId, $nextAttempt];
        $delay = min(1800, 300 * $nextAttempt);
        try {
            return QueueManager::schedule(self::ORDER_RETRY_HOOK, $args, $delay, [
                'force_durable'=>true,
                'source'=>'order_suggestion',
                'idempotency_key'=>'order-suggestion:' . $submissionId . ':' . $nextAttempt,
                'max_attempts'=>self::ORDER_RETRY_MAX_ATTEMPTS + 1,
            ]);
        } catch (\Throwable $error) {
            if (class_exists('\\RoutesPro\\Support\\Logger')) {
                \RoutesPro\Support\Logger::captureThrowable('Falha ao agendar retry da sugestão de encomenda.', $error, [
                    'context_key'=>'order_suggestion_retry_schedule',
                    'submission_id'=>$submissionId,
                    'attempt'=>$nextAttempt,
                ]);
            }
            return false;
        }
    }

    private static function projectMailTemplateState(int $projectId): array {
        $opts = Emails::get();
        $override = $projectId > 0 && !empty($opts['project_templates'][$projectId]) && is_array($opts['project_templates'][$projectId]);
        $template = Emails::get_project_template($projectId, $opts);
        return ['override'=>$override, 'template'=>$template, 'options'=>$opts];
    }

    private static function normaliseRecoveredIntro(string $body): string {
        $body = trim(wp_strip_all_tags($body));
        if ($body === '') return 'Segue o fecho operacional e comercial FieldFlow, com execução, routing, frentes, espaços extra, evidências, encomendas e briefing.';
        $body = (string)preg_replace('/\{(?:stops_list|route_url|changes|route_status|exception_[^}]+|completed_stops|pending_stops|completion_percent)\}/', '', $body);
        $body = (string)preg_replace('/\n{3,}/', "\n\n", $body);
        return trim($body);
    }

    private static function ensureDailyCloseAutomation(int $clientId, int $projectId, string $startDate = '', bool $activate = false): int {
        global $wpdb;
        self::ensureSchema();
        if ($clientId <= 0 || $projectId <= 0) return 0;
        $existing = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM ".self::table()." WHERE client_id=%d AND project_id=%d AND automation_type='daily_close' ORDER BY id ASC LIMIT 1",
            $clientId,$projectId
        ));
        if ($existing > 0) return $existing;
        $state = self::projectMailTemplateState($projectId);
        $tpl = (array)($state['template'] ?? []);
        $opts = (array)($state['options'] ?? []);
        $labels = self::scopeLabels($clientId, $projectId);
        $startDate = self::validDate($startDate) ?: wp_date('Y-m-d');
        $now = current_time('mysql');
        $subject = trim((string)($tpl['subject_completed'] ?? ''));
        if ($subject === '') $subject = '[FieldFlow] {project_name}, fecho de {date}';
        $intro = self::normaliseRecoveredIntro((string)($tpl['body_completed'] ?? ''));
        $name = trim((string)($labels['project_name'] ?? '')) . ' · Fecho diário';
        if (trim($name, " ·") === 'Fecho diário') $name = 'Fecho diário do merchandiser';
        $wpdb->insert(self::table(), [
            'name'=>$name,'client_id'=>$clientId,'project_id'=>$projectId,'automation_type'=>'daily_close','client_delivery_mode'=>'immediate',
            'weekday'=>1,'send_time'=>'20:00:00','start_date'=>$startDate,'end_date'=>null,'cumulative_start'=>$startDate,
            'periods_json'=>'["daily","week_to_date"]','formats_json'=>'["xlsx","pdf"]','to_team'=>1,'to_clients'=>1,'extra_emails'=>'','test_email'=>'','test_scope'=>'client','test_user_id'=>null,
            'subject_template'=>$subject,'intro_text'=>$intro,'brand_primary'=>sanitize_hex_color((string)($opts['brand_primary'] ?? '')) ?: '#172033',
            'is_active'=>$activate ? 1 : 0,'created_by'=>0,'next_run_at'=>null,'created_at'=>$now,'updated_at'=>$now,
        ]);
        $id=(int)$wpdb->insert_id;
        if($id>0&&$activate) self::reschedule($id);
        return $id;
    }

    private static function ensureOrderSuggestionAutomation(int $clientId, int $projectId, string $startDate = ''): int {
        global $wpdb;
        self::ensureSchema();
        $existing = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM ".self::table()." WHERE client_id=%d AND project_id=%d AND automation_type='order_suggestion' ORDER BY id ASC LIMIT 1",
            $clientId,$projectId
        ));
        if ($existing > 0) return $existing;
        $now = current_time('mysql');
        $startDate = self::validDate($startDate) ?: wp_date('Y-m-d');
        $wpdb->insert(self::table(), [
            'name'=>'Sugestões de encomenda','client_id'=>$clientId,'project_id'=>$projectId,'automation_type'=>'order_suggestion','client_delivery_mode'=>'immediate',
            'weekday'=>1,'send_time'=>'20:00:00','start_date'=>$startDate,'end_date'=>null,'cumulative_start'=>$startDate,
            'periods_json'=>'["daily"]','formats_json'=>'["xlsx","pdf"]','to_team'=>1,'to_clients'=>1,'extra_emails'=>'','test_email'=>'','test_scope'=>'client','test_user_id'=>null,
            'subject_template'=>'[FieldFlow] Sugestão de encomenda, {store_name}, {operative_name}',
            'intro_text'=>'Foi registada uma sugestão de encomenda. Consulta abaixo o resumo e os ficheiros Excel e PDF em anexo.',
            'brand_primary'=>'#172033','is_active'=>1,'created_by'=>0,'next_run_at'=>null,'created_at'=>$now,'updated_at'=>$now,
        ]);
        return (int)$wpdb->insert_id;
    }

    private static function latestOrderSuggestionSubmissionId(int $clientId, int $projectId, int $userId = 0): int {
        $dataset = FormSubmissions::get_submission_dataset([
            'client_id'=>$clientId,'project_id'=>$projectId,'owner_user_id'=>$userId,'limit'=>100,'scope_user_id'=>0,'include_wide'=>false,
        ]);
        foreach ((array)($dataset['rows'] ?? []) as $submission) {
            $summary = (array)($submission['presentation']['summary'] ?? []);
            if ((int)($summary['orders'] ?? 0) > 0) return absint($submission['id'] ?? 0);
        }
        return 0;
    }

    public static function handleTestCenter(): void {
        self::guard();check_admin_referer('fieldflow_mailcenter_test');
        $id=absint($_POST['schedule_id']??0);$row=$id?self::get($id):[];if(!$row)self::redirect(['ff_report_notice'=>'test_failed']);
        self::assertSaasAccess((int)($row['client_id']??0),false);
        $email=sanitize_email((string)($_POST['test_email']??''));if(!$email){$u=wp_get_current_user();$email=$u&&!empty($u->user_email)?sanitize_email((string)$u->user_email):'';}
        $scope=sanitize_key((string)($_POST['test_scope']??'client'));if(!in_array($scope,['client','merchandiser'],true))$scope='client';
        $userId=$scope==='merchandiser'?absint($_POST['test_user_id']??0):0;$date=self::validDate((string)($_POST['test_date']??''))?:wp_date('Y-m-d');
        $result=$email?self::execute($id,true,$email,$userId,['anchor_date'=>$date]):new \WP_Error('invalid_test_email','Email de teste inválido.');
        self::redirect(['mail_center_tab'=>'test','ff_report_notice'=>is_wp_error($result)?'test_failed':'test_sent','edit_report_schedule'=>$id]);
    }

    public static function hasActiveDailyClose(int $clientId, int $projectId, string $date = ''): bool {
        self::ensureSchema();
        if ($clientId <= 0 || $projectId <= 0) return false;
        $date = self::validDate($date) ?: wp_date('Y-m-d');
        global $wpdb;
        $count = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::table() . " WHERE is_active=1 AND automation_type='daily_close' AND client_id=%d AND project_id=%d AND start_date<=%s AND (end_date IS NULL OR end_date='' OR end_date>=%s) AND (to_team=1 OR to_clients=1 OR TRIM(COALESCE(extra_emails,''))<>'')",
            $clientId,
            $projectId,
            $date,
            $date
        ));
        return $count > 0;
    }

    public static function overviewData(): array {
        global $wpdb;self::ensureSchema();$table=self::table();$rows=$wpdb->get_results("SELECT automation_type,is_active,last_status,last_run_at,last_message,next_run_at FROM {$table} ORDER BY id DESC",ARRAY_A)?:[];
        $data=['total'=>count($rows),'active'=>0,'daily_close'=>0,'daily_digest'=>0,'weekly'=>0,'order_suggestion'=>0,'failed'=>0,'partial'=>0,'last_run'=>'','next_run'=>''];
        foreach($rows as $row){if(!empty($row['is_active']))$data['active']++;$type=sanitize_key((string)($row['automation_type']??'weekly'));if(isset($data[$type]))$data[$type]++;$st=sanitize_key((string)($row['last_status']??''));if(in_array($st,['failed','partial'],true))$data['failed']++;if($st==='partial')$data['partial']++;if($data['last_run']===''&&!empty($row['last_run_at']))$data['last_run']=(string)$row['last_run_at'];}
        $next=$wpdb->get_var("SELECT MIN(next_run_at) FROM {$table} WHERE is_active=1 AND next_run_at IS NOT NULL");$data['next_run']=(string)($next?:'');return $data;
    }

    private static function emailBody(string $intro, array $scope, array $ranges, array $labels, array $profile, array $summaries = [], bool $test = false): string {
        $items = '';
        foreach ($labels as $label) $items .= '<li style="margin:5px 0;overflow-wrap:anywhere">' . esc_html($label) . '</li>';
        if ($items === '') $items = '<li style="margin:5px 0;color:#991b1b;font-weight:700">Nenhum anexo foi produzido para este envio.</li>';
        $audience = (string)($profile['audience'] ?? 'client') === 'merchandiser' ? 'merchandiser' : 'client';
        $testMessage = $audience === 'merchandiser'
            ? 'Envio de teste. Este pacote reproduz o ambito pessoal do merchandiser selecionado.'
            : 'Envio de teste. Este pacote reproduz a visao do cliente.';
        $testBanner = $test ? '<div style="padding:12px 18px;background:#fef3c7;color:#92400e;font-weight:700;border-bottom:1px solid #fde68a">' . esc_html($testMessage) . '</div>' : '';
        $scopeCard = '<div style="margin:18px 0;padding:16px;border-radius:14px;background:#eff6ff;border:1px solid #bfdbfe"><small style="display:block;color:#1d4ed8;font-weight:700;text-transform:uppercase;letter-spacing:.08em">Ambito dos dados</small><div style="font-weight:800;margin-top:5px;color:#172033">' . esc_html((string)$profile['title']) . '</div><div style="margin-top:5px;color:#475569;line-height:1.5">' . esc_html((string)$profile['description']) . '</div></div>';

        $reportHtml = '';
        foreach ($summaries as $periodKey => $summary) {
            if (!is_array($summary)) continue;
            $report = is_array($summary['canonical'] ?? null) ? $summary['canonical'] : [];
            $k = is_array($report['kpis'] ?? null) ? $report['kpis'] : [];
            if (!$k) continue;
            $analysis = is_array($report['commercial_analysis'] ?? null)
                ? $report['commercial_analysis']
                : \RoutesPro\Reporting\DailyCommercialReportService::build($report);
            $cap = is_array($analysis['capabilities'] ?? null) ? $analysis['capabilities'] : [];
            $narrative = is_array($analysis[$audience] ?? null) ? $analysis[$audience] : [];
            $facts = is_array($analysis['facts'] ?? null) ? $analysis['facts'] : [];
            $visitFacts = is_array($facts['visits'] ?? null) ? $facts['visits'] : [];
            $frontFacts = is_array($facts['fronts'] ?? null) ? $facts['fronts'] : [];
            $commercialFacts = is_array($facts['commercial'] ?? null) ? $facts['commercial'] : [];
            $mobilityFacts = is_array($facts['mobility'] ?? null) ? $facts['mobility'] : [];

            $reportHtml .= '<div style="margin:24px 0 0;padding:18px;border:1px solid #dbeafe;border-radius:16px;background:#f8fbff;box-sizing:border-box">';
            $reportHtml .= '<div style="font-size:12px;color:#1d4ed8;font-weight:800;text-transform:uppercase;letter-spacing:.08em">' . esc_html((string)($narrative['eyebrow'] ?? ($audience === 'client' ? 'Visão comercial do dia' : 'O teu fecho do dia'))) . '</div>';
            $reportHtml .= '<h2 style="font-size:19px;margin:5px 0 5px;color:#0f172a">' . esc_html((string)($summary['label'] ?? ucfirst((string)$periodKey))) . '</h2>';
            if (!empty($narrative['headline'])) $reportHtml .= '<div style="font-size:15px;font-weight:800;color:#172033;margin-bottom:13px">' . esc_html((string)$narrative['headline']) . '</div>';
            foreach ((array)($narrative['paragraphs'] ?? []) as $paragraph) {
                $paragraph = trim((string)$paragraph);
                if ($paragraph !== '') $reportHtml .= '<p style="font-size:13px;line-height:20px;color:#334155;margin:7px 0">' . esc_html($paragraph) . '</p>';
            }

            $cards = [];
            if (!empty($cap['visits'])) {
                $cards[] = ['Execucao', number_format((float)($visitFacts['execution_rate'] ?? 0), 1, ',', '.') . '%', (int)($visitFacts['completed'] ?? 0) . ' realizadas / ' . (int)($visitFacts['planned'] ?? 0) . ' planeadas'];
            }
            if (!empty($cap['fronts'])) {
                $net = (float)($frontFacts['net'] ?? 0);
                $cards[] = ['Saldo de frentes', ($net > 0 ? '+' : '') . number_format($net, 1, ',', '.'), (int)($frontFacts['gain_locations'] ?? 0) . ' lojas com ganho | ' . (int)($frontFacts['loss_locations'] ?? 0) . ' com perda'];
            }
            if (!empty($cap['extra_spaces'])) {
                $cards[] = ['Conquistas', (string)(int)($commercialFacts['extra_spaces'] ?? 0), 'Implementacoes e espacos extra registados'];
            }
            if (!empty($cap['orders'])) {
                $cards[] = ['Oportunidades de encomenda', (string)(int)($commercialFacts['order_lines'] ?? 0), number_format((float)($commercialFacts['order_boxes'] ?? 0), 0, ',', '.') . ' caixas sugeridas'];
            }
            if (!empty($cap['briefing'])) {
                $briefTarget = (int)($commercialFacts['briefing_target_assignments'] ?? 0);
                $briefAnswered = (int)($commercialFacts['briefing_answered_assignments'] ?? 0);
                $briefStores = (int)($commercialFacts['briefing_target_stores'] ?? 0);
                $cards[] = ['Briefing', number_format((float)($commercialFacts['briefing_coverage_percent'] ?? 0), 1, ',', '.') . '%', $briefAnswered . '/' . $briefTarget . ' respostas alvo | ' . $briefStores . ' loja(s) mapeada(s) na rota'];
            }
            if (!empty($cap['evidence'])) {
                $cards[] = ['Evidencias', (string)(int)($commercialFacts['evidence_files'] ?? 0), 'Ficheiros associados aos reportes'];
            }
            if (!empty($cap['mobility'])) {
                $mobilityPartial = !empty($mobilityFacts['partial']);
                $mobilityRecovered = (int)($mobilityFacts['recovered_routes'] ?? 0) > 0;
                $mobilitySuffix = $mobilityPartial ? ' · parcial' : '';
                if ($mobilityRecovered) $mobilitySuffix .= ' · estimativa recuperada';
                $mobilityClosed = (int)($mobilityFacts['closed_routes'] ?? 0);
                $mobilityIncomplete = (int)($mobilityFacts['incomplete_routes'] ?? 0);
                $mobilityDistance = (float)($mobilityFacts['distance_km'] ?? 0);
                $mobilityHasDistance = $mobilityDistance > 0 || ($mobilityClosed > 0 && $mobilityIncomplete === 0);
                $mobilityHasCosts = (int)($mobilityFacts['costed_routes'] ?? 0) > 0;
                $cards[] = ['Km executados', $mobilityHasDistance ? number_format($mobilityDistance, 1, ',', '.') . ' km' : 'Por apurar', $mobilityClosed . ' rota(s) fechada(s)' . $mobilitySuffix];
                $cards[] = ['Combustivel / energia', $mobilityHasCosts ? number_format((float)($mobilityFacts['energy_cost_eur'] ?? 0), 2, ',', '.') . ' €' : 'Por calcular', ($mobilityRecovered ? 'Estimativa recuperada' : 'Estimativa canonica') . $mobilitySuffix];
                $cards[] = ['Portagens', $mobilityHasCosts ? number_format((float)($mobilityFacts['toll_cost_eur'] ?? 0), 2, ',', '.') . ' €' : 'Por calcular', ($mobilityRecovered ? 'Estimativa recuperada' : 'Estimativa canonica') . $mobilitySuffix];
            }
            if ($cards) {
                $reportHtml .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;table-layout:fixed;border-collapse:collapse;margin-top:12px"><tbody>';
                foreach (array_chunk($cards, 2) as $pair) {
                    $reportHtml .= '<tr>';
                    foreach ($pair as $card) {
                        $reportHtml .= '<td width="50%" valign="top" style="width:50%;padding:5px;box-sizing:border-box"><div style="padding:12px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;min-height:82px"><div style="font-size:10px;color:#64748b;text-transform:uppercase;font-weight:800">' . esc_html($card[0]) . '</div><div style="font-size:22px;font-weight:800;color:#0f172a;margin-top:4px">' . esc_html($card[1]) . '</div><div style="font-size:11px;color:#475569;margin-top:3px;line-height:16px">' . esc_html($card[2]) . '</div></div></td>';
                    }
                    if (count($pair) === 1) $reportHtml .= '<td width="50%"></td>';
                    $reportHtml .= '</tr>';
                }
                $reportHtml .= '</tbody></table>';
            }

            $mobilityDays = array_values(array_filter((array)($mobilityFacts['days'] ?? []), 'is_array'));
            if (!empty($cap['mobility']) && $mobilityDays) {
                $reportHtml .= '<div style="margin-top:17px;font-size:13px;font-weight:800;color:#334155">Mobilidade e custos por dia</div>';
                $reportHtml .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;margin-top:6px;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;font-size:11px"><thead><tr><th align="left" style="padding:8px;border-bottom:1px solid #e2e8f0">Data</th><th align="right" style="padding:8px;border-bottom:1px solid #e2e8f0">Km</th><th align="right" style="padding:8px;border-bottom:1px solid #e2e8f0">Comb./energia</th><th align="right" style="padding:8px;border-bottom:1px solid #e2e8f0">Portagens</th><th align="right" style="padding:8px;border-bottom:1px solid #e2e8f0">Total</th></tr></thead><tbody>';
                foreach ($mobilityDays as $day) {
                    $dayPartial = !empty($day['partial']);
                    $dateLabel = (string)($day['date'] ?? '');
                    if ($dateLabel !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateLabel)) $dateLabel = wp_date('d/m/Y', strtotime($dateLabel));
                    $dayRecovered = (int)($day['recovered_routes'] ?? 0) > 0;
                    $dayCosted = (int)($day['costed_routes'] ?? 0) > 0;
                    $dayDistance = (float)($day['distance_km'] ?? 0);
                    $dayHasDistance = $dayDistance > 0 || ((int)($day['closed_routes'] ?? 0) > 0 && (int)($day['incomplete_routes'] ?? 0) === 0);
                    $dayState = [];
                    if ($dayPartial) $dayState[] = 'parcial';
                    if ($dayRecovered) $dayState[] = 'estimativa recuperada';
                    if (!$dayCosted) $dayState[] = 'custos por calcular';
                    $dayBreakdown = 'Acesso ' . number_format((float)($day['access_distance_km'] ?? 0), 1, ',', '.') . ' km · PDVs ' . number_format((float)($day['inter_stop_distance_km'] ?? 0), 1, ',', '.') . ' km · Regresso ' . number_format((float)($day['return_distance_km'] ?? 0), 1, ',', '.') . ' km';
                    $reportHtml .= '<tr><td style="padding:8px;border-bottom:1px solid #f1f5f9"><strong>' . esc_html($dateLabel) . '</strong><div style="font-size:9px;color:#64748b;margin-top:2px">' . esc_html($dayBreakdown) . '</div>' . ($dayState ? '<div style="font-size:9px;color:#b45309">' . esc_html(implode(' · ', $dayState)) . '</div>' : '') . '</td>';
                    $reportHtml .= '<td align="right" style="padding:8px;border-bottom:1px solid #f1f5f9">' . esc_html($dayHasDistance ? number_format($dayDistance, 1, ',', '.') . ' km' : 'Por apurar') . '</td>';
                    $reportHtml .= '<td align="right" style="padding:8px;border-bottom:1px solid #f1f5f9">' . esc_html($dayCosted ? number_format((float)($day['energy_cost_eur'] ?? 0), 2, ',', '.') . ' €' : 'Por calcular') . '</td>';
                    $reportHtml .= '<td align="right" style="padding:8px;border-bottom:1px solid #f1f5f9">' . esc_html($dayCosted ? number_format((float)($day['toll_cost_eur'] ?? 0), 2, ',', '.') . ' €' : 'Por calcular') . '</td>';
                    $reportHtml .= '<td align="right" style="padding:8px;border-bottom:1px solid #f1f5f9;font-weight:800">' . esc_html($dayCosted ? number_format((float)($day['fuel_tolls_cost_eur'] ?? ((float)($day['energy_cost_eur'] ?? 0) + (float)($day['toll_cost_eur'] ?? 0))), 2, ',', '.') . ' €' : 'Por calcular') . '</td></tr>';
                }
                $totalCosted = (int)($mobilityFacts['costed_routes'] ?? 0) > 0;
                $totalDistance = (float)($mobilityFacts['distance_km'] ?? 0);
                $totalHasDistance = $totalDistance > 0 || ((int)($mobilityFacts['closed_routes'] ?? 0) > 0 && (int)($mobilityFacts['incomplete_routes'] ?? 0) === 0);
                $totalFlags = [];
                if (!empty($mobilityFacts['partial'])) $totalFlags[] = 'PARCIAL';
                if ((int)($mobilityFacts['recovered_routes'] ?? 0) > 0) $totalFlags[] = 'ESTIMATIVA RECUPERADA';
                if (!$totalCosted) $totalFlags[] = 'CUSTOS POR CALCULAR';
                $totalBreakdown = 'Acesso ' . number_format((float)($mobilityFacts['access_distance_km'] ?? 0), 1, ',', '.') . ' km · PDVs ' . number_format((float)($mobilityFacts['inter_stop_distance_km'] ?? 0), 1, ',', '.') . ' km · Regresso ' . number_format((float)($mobilityFacts['return_distance_km'] ?? 0), 1, ',', '.') . ' km';
                $reportHtml .= '<tr><td style="padding:9px;background:#f8fafc;font-weight:800">TOTAL GERAL' . ($totalFlags ? ' · ' . esc_html(implode(' · ', $totalFlags)) : '') . '<div style="font-size:9px;color:#64748b;margin-top:2px;font-weight:600">' . esc_html($totalBreakdown) . '</div></td>';
                $reportHtml .= '<td align="right" style="padding:9px;background:#f8fafc;font-weight:800">' . esc_html($totalHasDistance ? number_format($totalDistance, 1, ',', '.') . ' km' : 'Por apurar') . '</td>';
                $reportHtml .= '<td align="right" style="padding:9px;background:#f8fafc;font-weight:800">' . esc_html($totalCosted ? number_format((float)($mobilityFacts['energy_cost_eur'] ?? 0), 2, ',', '.') . ' €' : 'Por calcular') . '</td>';
                $reportHtml .= '<td align="right" style="padding:9px;background:#f8fafc;font-weight:800">' . esc_html($totalCosted ? number_format((float)($mobilityFacts['toll_cost_eur'] ?? 0), 2, ',', '.') . ' €' : 'Por calcular') . '</td>';
                $reportHtml .= '<td align="right" style="padding:9px;background:#f8fafc;font-weight:800">' . esc_html($totalCosted ? number_format((float)($mobilityFacts['fuel_tolls_cost_eur'] ?? ((float)($mobilityFacts['energy_cost_eur'] ?? 0) + (float)($mobilityFacts['toll_cost_eur'] ?? 0))), 2, ',', '.') . ' €' : 'Por calcular') . '</td></tr>';
                $reportHtml .= '</tbody></table>';
            }

            $highlights = array_values(array_filter((array)($narrative['highlights'] ?? []), 'is_array'));
            if ($highlights) {
                $reportHtml .= '<div style="margin-top:17px;font-size:13px;font-weight:800;color:#334155">' . ($audience === 'client' ? 'Principais ganhos e movimentos' : 'Conquistas do dia') . '</div>';
                $reportHtml .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;margin-top:6px;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0">';
                foreach (array_slice($highlights, 0, 5) as $highlight) {
                    $reportHtml .= '<tr><td style="padding:10px;border-bottom:1px solid #f1f5f9"><strong>' . esc_html((string)($highlight['label'] ?? '')) . '</strong><span style="float:right;font-weight:800">' . esc_html((string)($highlight['value'] ?? '')) . '</span>';
                    if (!empty($highlight['detail'])) $reportHtml .= '<div style="font-size:11px;color:#64748b;margin-top:4px">' . esc_html((string)$highlight['detail']) . '</div>';
                    $reportHtml .= '</td></tr>';
                }
                $reportHtml .= '</table>';
            }

            $attentionRows = $audience === 'merchandiser' ? (array)($analysis['alerts'] ?? []) : (array)($analysis['exceptions'] ?? []);
            if ($attentionRows) {
                $reportHtml .= '<div style="margin-top:17px;font-size:13px;font-weight:800;color:#991b1b">' . ($audience === 'merchandiser' ? 'Alertas e ações' : 'Exceções e pontos de atenção') . '</div>';
                $reportHtml .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;margin-top:6px;border-collapse:collapse;background:#fff7ed;border:1px solid #fed7aa">';
                foreach (array_slice(array_values(array_filter($attentionRows, 'is_array')), 0, 8) as $alert) {
                    $title = $audience === 'merchandiser' ? (string)($alert['title'] ?? 'Atenção') : ((string)($alert['location'] ?? '') ?: 'Exceção operacional');
                    $detail = (string)($alert['detail'] ?? $alert['reason'] ?? '');
                    $reportHtml .= '<tr><td style="padding:10px;border-bottom:1px solid #ffedd5"><div style="font-weight:800;color:#9a3412">' . esc_html($title) . '</div><div style="font-size:11px;color:#7c2d12;margin-top:3px;line-height:16px">' . esc_html($detail) . '</div></td></tr>';
                }
                $reportHtml .= '</table>';
            }

            $visits = array_values(array_filter((array)($analysis['visits'] ?? []), 'is_array'));
            if ($visits) {
                $reportHtml .= '<div style="margin-top:17px;font-size:13px;font-weight:800;color:#334155">Visitas loja a loja</div>';
                $reportHtml .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;margin-top:6px;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0">';
                foreach (array_slice($visits, 0, 20) as $visit) {
                    $location = trim((string)($visit['location_name'] ?? '')) ?: 'Loja não identificada';
                    $result = (string)($visit['result_label'] ?? '');
                    $reason = trim((string)($visit['justification'] ?? ''));
                    $reportHtml .= '<tr><td style="padding:9px;border-bottom:1px solid #f1f5f9"><strong>' . esc_html($location) . '</strong><span style="float:right;font-size:11px;font-weight:800">' . esc_html($result) . '</span>';
                    if ($reason !== '') $reportHtml .= '<div style="font-size:11px;color:#64748b;margin-top:3px">Justificação: ' . esc_html($reason) . '</div>';
                    $reportHtml .= '</td></tr>';
                }
                $reportHtml .= '</table>';
            }
            $reportHtml .= '</div>';
        }

        $css = Emails::responsive_email_css();
        return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="x-apple-disable-message-reformatting"><style>' . $css . '</style></head>'
            . '<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif;color:#0f172a"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f1f5f9"><tr><td align="center" style="padding:24px 12px">'
            . '<table class="ff-email-viewport" role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="width:100%;max-width:680px;background:#fff;border-collapse:collapse;border:1px solid #e2e8f0">'
            . ($testBanner !== '' ? '<tr><td>' . $testBanner . '</td></tr>' : '')
            . '<tr><td bgcolor="' . esc_attr(self::currentBrand()) . '" style="padding:24px;background:' . esc_attr(self::currentBrand()) . ';color:#fff"><div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;opacity:.8">FieldFlow | ' . ($audience === 'client' ? 'Relatorio comercial diario' : 'Fecho operacional pessoal') . '</div><div style="margin-top:8px;font-size:24px;line-height:31px;font-weight:700">' . esc_html($scope['project_name']) . '</div></td></tr>'
            . '<tr><td style="padding:26px"><p style="font-size:15px;line-height:1.6;margin-top:0">' . nl2br(esc_html($intro)) . '</p>' . $scopeCard . $reportHtml
            . '<h3 style="margin:24px 0 6px">Ficheiros anexos</h3><ul style="margin-top:0;padding-left:20px">' . $items . '</ul></td></tr>'
            . '<tr><td style="padding:16px 26px;background:#f8fafc;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px;line-height:18px">A analise, os KPIs, o Excel, o PDF, o CSV e o Analytics usam o mesmo snapshot canonico FieldFlow. Secoes sem dados relevantes sao omitidas automaticamente.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private static $brand = '#172033';
    private static function currentBrand(): string { return self::$brand; }

    private static function mailHeaders(): array {
        $opts = Emails::get();
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $fromEmail = sanitize_email((string)($opts['from_email'] ?? '')) ?: sanitize_email(get_bloginfo('admin_email'));
        $fromName = wp_specialchars_decode((string)($opts['from_name'] ?? '') ?: get_bloginfo('name'), ENT_QUOTES);
        if ($fromEmail) $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        if (!empty($opts['reply_to'])) $headers[] = 'Reply-To: ' . sanitize_email((string)$opts['reply_to']);
        return $headers;
    }

    private static function scopeLabels(int $clientId, int $projectId): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        return [
            'client_name' => (string)($wpdb->get_var($wpdb->prepare("SELECT name FROM {$px}clients WHERE id=%d", $clientId)) ?: ('Cliente #' . $clientId)),
            'project_name' => (string)($wpdb->get_var($wpdb->prepare("SELECT name FROM {$px}projects WHERE id=%d", $projectId)) ?: ('Projeto #' . $projectId)),
        ];
    }

    private static function maybeUpdateExecutionState(int $id, bool $test, string $status, string $message, array $context = []): void {
        if (!empty($context['manual_dispatch'])) return;
        self::updateExecutionState($id, $test, $status, $message);
    }

    private static function updateExecutionState(int $id, bool $test, string $status, string $message): void {
        global $wpdb;
        $data = $test ? [
            'last_test_at' => current_time('mysql'),
            'last_test_status' => sanitize_key($status),
            'last_test_message' => sanitize_text_field($message),
            'updated_at' => current_time('mysql'),
        ] : [
            'last_run_at' => current_time('mysql'),
            'last_status' => sanitize_key($status),
            'last_message' => sanitize_text_field($message),
            'updated_at' => current_time('mysql'),
        ];
        $formats = array_fill(0, count($data), '%s');
        $wpdb->update(self::table(), $data, ['id' => $id], $formats, ['%d']);
    }

    private static function sanitizeEmailList(string $value): string {
        $out = [];
        foreach (preg_split('/[,;\s]+/', $value) ?: [] as $email) {
            $email = sanitize_email($email);
            if ($email) $out[strtolower($email)] = $email;
        }
        return implode(', ', array_values($out));
    }

    private static function validDate(string $value): string {
        $value = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return '';
        [$y,$m,$d] = array_map('intval', explode('-', $value));
        return checkdate($m,$d,$y) ? $value : '';
    }

    private static function manualScheduleFor(string $type, int $clientId, int $projectId, string $date = ''): array {
        global $wpdb;
        self::ensureSchema();
        $type = sanitize_key($type);
        if (!in_array($type, ['daily_close','order_suggestion'], true) || $clientId <= 0 || $projectId <= 0) return [];
        $date = self::validDate($date) ?: wp_date('Y-m-d');
        $sql = "SELECT * FROM ".self::table()." WHERE automation_type=%s AND client_id=%d AND project_id=%d ORDER BY CASE WHEN start_date<=%s AND (end_date IS NULL OR end_date='' OR end_date>=%s) THEN 0 ELSE 1 END ASC, is_active DESC, id DESC LIMIT 1";
        return $wpdb->get_row($wpdb->prepare($sql, $type, $clientId, $projectId, $date, $date), ARRAY_A) ?: [];
    }

    private static function manualRecipientSet(array $row, string $audience, int $userId): array {
        $recipientRow = $row;
        $recipientRow['to_team'] = 1;
        $recipientRow['to_clients'] = 1;
        // Historic Mail Center manual client/team scoping remains unchanged.
        $recipientRow['extra_emails'] = '';
        $team = self::resolveRecipientsForContext($recipientRow, ['recipient_mode'=>'team_user','user_id'=>$userId]);
        $client = array_values(array_filter(self::resolveRecipients($recipientRow), static fn($r) => (string)($r['type'] ?? '') === 'client'));
        // 9DO: add only extras explicitly configured on THIS project template.
        // Resolve separately and deduplicate addresses without changing the
        // lower-privilege team-before-client semantics.
        $extraRow = $recipientRow;
        $extraRow['to_team'] = 0;
        $extraRow['to_clients'] = 0;
        $extraRow['extra_emails'] = (string)($row['extra_emails'] ?? '');
        $clientByEmail = [];
        foreach (array_merge($client, self::resolveRecipients($extraRow)) as $recipient) {
            $key = strtolower(sanitize_email((string)($recipient['email'] ?? '')));
            if ($key !== '' && !isset($clientByEmail[$key])) $clientByEmail[$key] = $recipient;
        }
        $client = array_values($clientByEmail);
        if ($audience === 'team') return $team;
        if ($audience === 'client') return $client;
        $out = [];
        foreach (array_merge($team, $client) as $recipient) {
            $email = strtolower(sanitize_email((string)($recipient['email'] ?? '')));
            if ($email === '') continue;
            if (isset($out[$email]) && (string)($out[$email]['type'] ?? '') === 'team') continue;
            $out[$email] = $recipient;
        }
        return array_values($out);
    }

    private static function manualReturnArgs(): array {
        $map = ['manual_type','manual_client_id','manual_project_id','manual_user_id','manual_from','manual_to'];
        $out = ['mail_center_tab'=>'manual'];
        foreach ($map as $key) {
            $postKey = 'return_' . $key;
            if (!isset($_POST[$postKey])) continue;
            $value = in_array($key, ['manual_client_id','manual_project_id','manual_user_id'], true) ? absint($_POST[$postKey]) : sanitize_text_field((string)$_POST[$postKey]);
            if ($value !== '' && $value !== 0) $out[$key] = $value;
        }
        return $out;
    }

    private static function redirectManual(string $notice, string $message = ''): void {
        $args = self::manualReturnArgs();
        $args['ff_manual_notice'] = sanitize_key($notice);
        if ($message !== '') $args['ff_manual_message'] = substr(sanitize_text_field($message), 0, 220);
        $url = add_query_arg(array_merge(['page'=>'routespro-emails'], $args), admin_url('admin.php')) . '#fieldflow-manual-mail-center';
        wp_safe_redirect($url);
        exit;
    }

    public static function handleManualSend(): void {
        self::guard();
        check_admin_referer('fieldflow_mailcenter_manual_send');
        $itemType = sanitize_key((string)($_POST['item_type'] ?? ''));
        $itemId = absint($_POST['item_id'] ?? 0);
        $audience = sanitize_key((string)($_POST['audience'] ?? 'both'));
        if (!in_array($audience, ['team','client','both','test','test_team','test_client'], true)) $audience = 'both';
        if ($itemId <= 0 || !in_array($itemType, ['route_close','order_suggestion'], true)) self::redirectManual('invalid', 'Registo de envio inválido.');
        $isTest = strpos($audience, 'test') === 0;
        $testEmail = sanitize_email((string)($_POST['test_email'] ?? ''));
        if ($isTest && !$testEmail) self::redirectManual('invalid', 'Indica um email de teste válido.');

        if ($itemType === 'route_close') {
            global $wpdb;
            $route = $wpdb->get_row($wpdb->prepare("SELECT id,client_id,project_id,owner_user_id,date,status FROM {$wpdb->prefix}routespro_routes WHERE id=%d LIMIT 1", $itemId), ARRAY_A) ?: [];
            if (!$route) self::redirectManual('missing', 'A rota já não existe.');
            $userId = absint($route['owner_user_id'] ?? 0);
            if (!$userId) $userId = absint($wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}routespro_assignments WHERE route_id=%d ORDER BY id ASC LIMIT 1", $itemId)));
            if (!$userId) self::redirectManual('invalid', 'A rota não tem merchandiser identificado.');
            $date = self::validDate((string)($route['date'] ?? '')) ?: wp_date('Y-m-d');
            $row = self::manualScheduleFor('daily_close', absint($route['client_id'] ?? 0), absint($route['project_id'] ?? 0), $date);
            if (!$row) {
                $recoveredId = self::ensureDailyCloseAutomation(absint($route['client_id'] ?? 0), absint($route['project_id'] ?? 0), $date, false);
                $row = $recoveredId > 0 ? self::get($recoveredId) : [];
            }
            if (!$row) self::redirectManual('missing_template', 'Não foi possível recuperar o template de Fecho diário para este cliente e projeto.');
            self::assertSaasAccess((int)$row['client_id'], false);
            if (class_exists('\\RoutesPro\\Services\\RouteProgressService')) {
                $progress = \RoutesPro\Services\RouteProgressService::sync($itemId, true, false);
                if (is_wp_error($progress)) self::redirectManual('failed', $progress->get_error_message());
                if ((int)($progress['remaining_stops'] ?? 0) > 0) self::redirectManual('route_open', 'A rota ainda tem ' . (int)$progress['remaining_stops'] . ' paragem(ns) por resolver.');
            }
            if (class_exists('\\RoutesPro\\Services\\RouteMetricsService')) {
                $geoIntegrity = \RoutesPro\Services\RouteMetricsService::ensureGeographicIntegrityForRoute($itemId, [], false);
                if (empty($geoIntegrity['ok']) && !in_array((string)($geoIntegrity['reason'] ?? ''), ['provider_disabled'], true)) {
                    self::redirectManual('failed', 'Não foi possível reconciliar a geografia da rota antes do envio: ' . (string)($geoIntegrity['reason'] ?? 'erro desconhecido') . '.');
                }
            }
            $context = [
                'anchor_date'=>$date,'report_user_id'=>$userId,'route_id'=>$itemId,'manual_dispatch'=>1,
                'manual_actor_user_id'=>get_current_user_id(),'trigger_type'=>$isTest?'manual_route_close_test':'manual_route_close',
            ];
            if ($isTest) {
                $testUserId = $audience === 'test_team' ? $userId : 0;
                $result = self::execute((int)$row['id'], true, $testEmail, $testUserId, $context);
            } else {
                $recipients = self::manualRecipientSet($row, $audience, $userId);
                if (!$recipients) self::redirectManual('no_recipients', 'Não foram encontrados destinatários válidos para este envio.');
                $context['_resolved_recipients'] = $recipients;
                $result = self::execute((int)$row['id'], false, '', 0, $context);
            }
            self::redirectManual(is_wp_error($result) ? 'failed' : ($isTest ? 'test_sent' : 'processed'), is_wp_error($result)
                ? $result->get_error_message()
                : 'Pedido processado sem duplicar mensagens anteriormente aceites. Confirma o estado de cada destinatario na Outbox.');
        }

        $suggestion = FormSubmissions::getOrderSuggestionSubmission($itemId);
        if (!$suggestion) self::redirectManual('missing', 'A submissão não contém uma sugestão de encomenda recuperável.');
        $clientId = absint($suggestion['client_id'] ?? 0);
        $projectId = absint($suggestion['project_id'] ?? 0);
        $userId = absint($suggestion['operative_user_id'] ?? 0);
        $date = self::validDate((string)($suggestion['report_day'] ?? '')) ?: wp_date('Y-m-d');
        $row = self::manualScheduleFor('order_suggestion', $clientId, $projectId, $date);
        if (!$row) {
            self::ensureOrderSuggestionAutomation($clientId, $projectId, $date);
            $row = self::manualScheduleFor('order_suggestion', $clientId, $projectId, $date);
        }
        if (!$row) self::redirectManual('missing_template', 'Não foi possível preparar o template de Sugestão de encomenda.');
        self::assertSaasAccess((int)$row['client_id'], false);
        $context = [
            'submission_id'=>$itemId,'report_user_id'=>$userId,'manual_dispatch'=>1,'manual_actor_user_id'=>get_current_user_id(),
            'trigger_type'=>$isTest?'manual_order_suggestion_test':'manual_order_suggestion',
        ];
        if ($isTest) {
            $result = self::execute((int)$row['id'], true, $testEmail, 0, $context);
        } else {
            $recipients = self::manualRecipientSet($row, $audience, $userId);
            if (!$recipients) self::redirectManual('no_recipients', 'Não foram encontrados destinatários válidos para este envio.');
            $context['_resolved_recipients'] = $recipients;
            $result = self::execute((int)$row['id'], false, '', 0, $context);
        }
        self::redirectManual(is_wp_error($result) ? 'failed' : ($isTest ? 'test_sent' : 'sent'), is_wp_error($result) ? $result->get_error_message() : 'Sugestão de encomenda reenviada a partir da submissão histórica #' . $itemId . '.');
    }

    private static function manualRouteRows(array $filters): array {
        global $wpdb;
        $where = ['r.date BETWEEN %s AND %s'];
        $args = [(string)$filters['from'], (string)$filters['to']];
        if (!empty($filters['client_id'])) { $where[] = 'r.client_id=%d'; $args[] = (int)$filters['client_id']; }
        if (!empty($filters['project_id'])) { $where[] = 'r.project_id=%d'; $args[] = (int)$filters['project_id']; }
        // O owner histórico nem sempre foi persistido na própria rota. Para o
        // centro manual, a assignment da rota é um fallback autoritativo.
        $sql = "SELECT r.id,r.date,r.status,r.client_id,r.project_id,r.owner_user_id,c.name AS client_name,p.name AS project_name,u.display_name AS operative_name,
                    COUNT(rs.id) total_stops,
                    SUM(CASE WHEN rs.status IN ('done','completed') THEN 1 ELSE 0 END) completed_stops,
                    SUM(CASE WHEN rs.status='failed' AND TRIM(COALESCE(rs.fail_reason,''))<>'' THEN 1 ELSE 0 END) justified_failed_stops,
                    SUM(CASE WHEN rs.status IS NULL OR (rs.status NOT IN ('done','completed') AND NOT (rs.status='failed' AND TRIM(COALESCE(rs.fail_reason,''))<>'')) THEN 1 ELSE 0 END) remaining_stops
                FROM {$wpdb->prefix}routespro_routes r
                LEFT JOIN {$wpdb->prefix}routespro_route_stops rs ON rs.route_id=r.id
                LEFT JOIN {$wpdb->prefix}routespro_clients c ON c.id=r.client_id
                LEFT JOIN {$wpdb->prefix}routespro_projects p ON p.id=r.project_id
                LEFT JOIN {$wpdb->users} u ON u.ID=r.owner_user_id
                WHERE ".implode(' AND ', $where)."
                GROUP BY r.id,r.date,r.status,r.client_id,r.project_id,r.owner_user_id,c.name,p.name,u.display_name
                ORDER BY r.date DESC,r.id DESC LIMIT 240";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [];
        $wantedUser = absint($filters['user_id'] ?? 0);
        $names = [];
        $out = [];
        foreach ($rows as $row) {
            $effectiveUser = absint($row['owner_user_id'] ?? 0);
            if (!$effectiveUser) {
                $effectiveUser = absint($wpdb->get_var($wpdb->prepare(
                    "SELECT user_id FROM {$wpdb->prefix}routespro_assignments WHERE route_id=%d ORDER BY id ASC LIMIT 1",
                    (int)$row['id']
                )));
            }
            if ($wantedUser > 0 && $effectiveUser !== $wantedUser) continue;
            $row['owner_user_id'] = $effectiveUser;
            if ($effectiveUser > 0 && trim((string)($row['operative_name'] ?? '')) === '') {
                if (!array_key_exists($effectiveUser, $names)) {
                    $user = get_userdata($effectiveUser);
                    $names[$effectiveUser] = $user ? (string)($user->display_name ?: $user->user_login) : '';
                }
                $row['operative_name'] = $names[$effectiveUser];
            }
            $out[] = $row;
            if (count($out) >= 120) break;
        }
        return $out;
    }

    private static function manualOrderRows(array $filters): array {
        $dataset = FormSubmissions::get_submission_dataset([
            'client_id'=>(int)$filters['client_id'],'project_id'=>(int)$filters['project_id'],'owner_user_id'=>(int)$filters['user_id'],
            'date_from'=>(string)$filters['from'],'date_to'=>(string)$filters['to'],'limit'=>500,'scope_user_id'=>0,'include_wide'=>false,
        ]);
        $out = [];
        foreach ((array)($dataset['rows'] ?? []) as $submission) {
            $summary = (array)($submission['presentation']['summary'] ?? []);
            if ((int)($summary['orders'] ?? 0) <= 0) continue;
            $out[] = $submission;
            if (count($out) >= 120) break;
        }
        return $out;
    }

    private static function manualActionForm(string $itemType, int $itemId, array $filters, string $defaultTestEmail, bool $allowProfileTests = true): string {
        ob_start();
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ff-mc-manual-action" data-manual-mail-form>
          <input type="hidden" name="action" value="fieldflow_mailcenter_manual_send"><input type="hidden" name="item_type" value="<?php echo esc_attr($itemType); ?>"><input type="hidden" name="item_id" value="<?php echo (int)$itemId; ?>">
          <?php wp_nonce_field('fieldflow_mailcenter_manual_send'); ?>
          <?php foreach(['type','client_id','project_id','user_id','from','to'] as $key): ?><input type="hidden" name="return_manual_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string)($filters[$key] ?? '')); ?>"><?php endforeach; ?>
          <select name="audience" aria-label="Destinatários">
            <option value="both">Cliente + merchandiser</option><option value="team">Só merchandiser</option><option value="client">Só cliente</option>
            <?php if($allowProfileTests): ?><option value="test_team">Teste, visão merchandiser</option><option value="test_client">Teste, visão cliente</option><?php else: ?><option value="test">Enviar teste</option><?php endif; ?>
          </select>
          <input type="hidden" name="test_email" value="<?php echo esc_attr($defaultTestEmail); ?>" data-manual-test-email-hidden>
          <button class="button button-small button-primary" type="submit">Enviar</button>
        </form>
        <?php
        return (string)ob_get_clean();
    }

    public static function renderManualCenter(): void {
        if (!current_user_can('routespro_manage')) return;
        global $wpdb;
        self::ensureSchema();
        $today = wp_date('Y-m-d');
        $defaultFrom = (new \DateTimeImmutable('today -30 days', wp_timezone()))->format('Y-m-d');
        $filters = [
            'type'=>sanitize_key((string)($_GET['manual_type'] ?? 'all')),
            'client_id'=>absint($_GET['manual_client_id'] ?? 0),'project_id'=>absint($_GET['manual_project_id'] ?? 0),'user_id'=>absint($_GET['manual_user_id'] ?? 0),
            'from'=>self::validDate((string)($_GET['manual_from'] ?? '')) ?: $defaultFrom,'to'=>self::validDate((string)($_GET['manual_to'] ?? '')) ?: $today,
        ];
        if (!in_array($filters['type'], ['all','route_close','order_suggestion'], true)) $filters['type']='all';
        if ($filters['from'] > $filters['to']) [$filters['from'],$filters['to']] = [$filters['to'],$filters['from']];
        $clients = $wpdb->get_results("SELECT id,name FROM {$wpdb->prefix}routespro_clients ORDER BY name ASC", ARRAY_A) ?: [];
        $projects = $wpdb->get_results("SELECT id,client_id,name FROM {$wpdb->prefix}routespro_projects ORDER BY name ASC", ARRAY_A) ?: [];
        $ownerIds = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT owner_user_id FROM {$wpdb->prefix}routespro_routes WHERE date BETWEEN %s AND %s AND owner_user_id>0", $filters['from'], $filters['to'])) ?: [];
        $assignmentIds = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT a.user_id FROM {$wpdb->prefix}routespro_assignments a INNER JOIN {$wpdb->prefix}routespro_routes r ON r.id=a.route_id WHERE r.date BETWEEN %s AND %s AND a.user_id>0", $filters['from'], $filters['to'])) ?: [];
        $ownerIds = array_values(array_unique(array_filter(array_map('absint', array_merge($ownerIds, $assignmentIds)))));
        $users = $ownerIds ? get_users(['include'=>$ownerIds,'orderby'=>'display_name','order'=>'ASC','fields'=>['ID','display_name','user_login']]) : [];
        $routes = $filters['type'] === 'order_suggestion' ? [] : self::manualRouteRows($filters);
        $orders = $filters['type'] === 'route_close' ? [] : self::manualOrderRows($filters);
        $me = wp_get_current_user();
        $defaultTestEmail = $me && !empty($me->user_email) ? sanitize_email((string)$me->user_email) : '';
        $notice = sanitize_key((string)($_GET['ff_manual_notice'] ?? ''));
        $message = sanitize_text_field((string)($_GET['ff_manual_message'] ?? ''));
        $noticeMap = ['sent'=>'Envio manual concluído.','processed'=>'Pedido de envio processado.','test_sent'=>'Email de teste enviado.','failed'=>'O envio manual falhou.','invalid'=>'Pedido inválido.','missing'=>'Registo não encontrado.','missing_template'=>'Falta um template compatível.','no_recipients'=>'Sem destinatários válidos.','route_open'=>'A rota ainda não está operacionalmente concluída.'];
        ?>
        <section id="fieldflow-manual-mail-center" class="ff-manual-mail-center">
          <style>
            .ff-manual-mail-center .ff-manual-filter{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:10px;align-items:end}.ff-manual-mail-center .ff-manual-table{overflow:auto;border:1px solid #e2e8f0;border-radius:16px}.ff-manual-mail-center table{min-width:1060px}.ff-manual-mail-center .ff-manual-badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:800}.ff-manual-mail-center .ok{background:#dcfce7;color:#166534}.ff-manual-mail-center .warn{background:#fef3c7;color:#92400e}.ff-manual-mail-center .bad{background:#fee2e2;color:#991b1b}.ff-manual-mail-center .ff-mc-manual-action{display:flex;gap:6px;align-items:center;min-width:330px}.ff-manual-mail-center .ff-mc-manual-action select{min-width:190px}.ff-manual-mail-center .ff-manual-test{display:grid;grid-template-columns:minmax(220px,420px) 1fr;gap:14px;align-items:end}@media(max-width:1100px){.ff-manual-mail-center .ff-manual-filter{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:782px){.ff-manual-mail-center .ff-manual-filter,.ff-manual-mail-center .ff-manual-test{grid-template-columns:1fr}}
          </style>
          <?php if($notice && isset($noticeMap[$notice])): ?><div class="notice <?php echo in_array($notice,['failed','invalid','missing','missing_template','no_recipients','route_open'],true)?'notice-error':'notice-success'; ?> is-dismissible"><p><strong><?php echo esc_html($noticeMap[$notice]); ?></strong><?php if($message!==''): ?> <?php echo esc_html($message); ?><?php endif; ?></p></div><?php endif; ?>
          <div class="rp-card"><h2 style="margin-top:0">Recuperação e reenvio histórico</h2><p class="rp-muted">Reenvia um fecho diário ou uma sugestão já existente usando os dados persistidos e o template atual. O processo não volta a fechar visitas, não cria novas submissões e não altera métricas.</p>
            <form method="get" class="ff-manual-filter"><input type="hidden" name="page" value="routespro-emails"><input type="hidden" name="mail_center_tab" value="manual">
              <div><label class="rp-label">Tipo</label><select name="manual_type"><option value="all" <?php selected($filters['type'],'all'); ?>>Todos</option><option value="route_close" <?php selected($filters['type'],'route_close'); ?>>Fechos de rota</option><option value="order_suggestion" <?php selected($filters['type'],'order_suggestion'); ?>>Sugestões de encomenda</option></select></div>
              <div><label class="rp-label">Cliente</label><select name="manual_client_id" id="ff-manual-client"><option value="0">Todos</option><?php foreach($clients as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php selected($filters['client_id'],(int)$c['id']); ?>><?php echo esc_html($c['name']); ?></option><?php endforeach; ?></select></div>
              <div><label class="rp-label">Projeto</label><select name="manual_project_id" id="ff-manual-project"><option value="0">Todos</option><?php foreach($projects as $p): ?><option value="<?php echo (int)$p['id']; ?>" data-client="<?php echo (int)$p['client_id']; ?>" <?php selected($filters['project_id'],(int)$p['id']); ?>><?php echo esc_html($p['name']); ?></option><?php endforeach; ?></select></div>
              <div><label class="rp-label">Merchandiser</label><select name="manual_user_id"><option value="0">Todos</option><?php foreach($users as $u): ?><option value="<?php echo (int)$u->ID; ?>" <?php selected($filters['user_id'],(int)$u->ID); ?>><?php echo esc_html((string)($u->display_name ?: $u->user_login)); ?></option><?php endforeach; ?></select></div>
              <div><label class="rp-label">De</label><input type="date" name="manual_from" value="<?php echo esc_attr($filters['from']); ?>"></div><div><label class="rp-label">Até</label><input type="date" name="manual_to" value="<?php echo esc_attr($filters['to']); ?>"></div>
              <div style="grid-column:1/-1"><button class="button button-primary">Aplicar filtros</button> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=routespro-emails&mail_center_tab=manual')); ?>">Últimos 30 dias</a></div>
            </form>
          </div>
          <div class="rp-card" style="margin-top:16px"><div class="ff-manual-test"><div><label class="rp-label">Email para testes manuais</label><input type="email" id="ff-manual-global-test-email" value="<?php echo esc_attr($defaultTestEmail); ?>" placeholder="o-teu-email@empresa.pt"></div><p class="rp-muted" style="margin:0">Nos testes nenhum cliente ou merchandiser real é contactado. Para fechos podes escolher a visão de cliente ou a visão pessoal do merchandiser.</p></div></div>
          <?php if($filters['type']!=='order_suggestion'): ?>
          <div class="rp-card" style="margin-top:16px"><div style="display:flex;justify-content:space-between;gap:12px;align-items:center"><div><h2 style="margin:0">Fechos de rota</h2><p class="rp-muted" style="margin:4px 0 0">Rotas históricas, incluindo as que podem ser reconciliadas pela nova regra de não visita justificada.</p></div><strong><?php echo count($routes); ?></strong></div><div class="ff-manual-table" style="margin-top:12px"><table class="widefat striped"><thead><tr><th>Data</th><th>Cliente / projeto</th><th>Merchandiser</th><th>Execução</th><th>Estado</th><th>Template</th><th>Enviar</th></tr></thead><tbody><?php if(!$routes): ?><tr><td colspan="7">Sem rotas para estes filtros.</td></tr><?php endif; ?><?php foreach($routes as $r): $remaining=(int)($r['remaining_stops']??0);$repairable=$remaining===0&&!in_array(sanitize_key((string)$r['status']),['completed','done'],true);$schedule=self::manualScheduleFor('daily_close',(int)$r['client_id'],(int)$r['project_id'],(string)$r['date']);$templateState=self::projectMailTemplateState((int)$r['project_id']);$recoverable=!$schedule; ?><tr><td><strong><?php echo esc_html(wp_date('d/m/Y',strtotime((string)$r['date']))); ?></strong><br><small>Rota #<?php echo (int)$r['id']; ?></small></td><td><?php echo esc_html((string)$r['client_name']); ?><br><small><?php echo esc_html((string)$r['project_name']); ?></small></td><td><?php echo esc_html((string)($r['operative_name']?:('User #'.(int)$r['owner_user_id']))); ?></td><td><?php echo (int)$r['completed_stops']; ?> visitadas / <?php echo (int)$r['total_stops']; ?><br><small><?php echo (int)$r['justified_failed_stops']; ?> justificadas</small></td><td><?php if($remaining===0): ?><span class="ff-manual-badge <?php echo $repairable?'warn':'ok'; ?>"><?php echo $repairable?'Reconciliável':'Concluída'; ?></span><?php else: ?><span class="ff-manual-badge bad"><?php echo $remaining; ?> por validar</span><br><small>A ação tenta reconciliar antes de enviar.</small><?php endif; ?></td><td><?php if($schedule): echo esc_html((string)$schedule['name']); elseif(!empty($templateState['override'])): ?><span class="ff-manual-badge warn">Template do projeto · recuperável</span><?php else: ?><span class="ff-manual-badge warn">Template canónico · recuperável</span><?php endif; ?></td><td><?php echo self::manualActionForm('route_close',(int)$r['id'],$filters,$defaultTestEmail,true); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
          <?php endif; ?>
          <?php if($filters['type']!=='route_close'): ?>
          <div class="rp-card" style="margin-top:16px"><div style="display:flex;justify-content:space-between;gap:12px;align-items:center"><div><h2 style="margin:0">Sugestões de encomenda existentes</h2><p class="rp-muted" style="margin:4px 0 0">Cada linha corresponde à submissão original. Excel e PDF são regenerados no momento do reenvio.</p></div><strong><?php echo count($orders); ?></strong></div><div class="ff-manual-table" style="margin-top:12px"><table class="widefat striped"><thead><tr><th>Data</th><th>Cliente / projeto</th><th>Loja</th><th>Merchandiser</th><th>Sugestão</th><th>Template</th><th>Enviar</th></tr></thead><tbody><?php if(!$orders): ?><tr><td colspan="7">Sem sugestões de encomenda para estes filtros.</td></tr><?php endif; ?><?php foreach($orders as $s): $summary=(array)($s['presentation']['summary']??[]);$sid=(int)($s['id']??0);$date=(string)($s['report_day']??substr((string)($s['submitted_at']??''),0,10));$schedule=self::manualScheduleFor('order_suggestion',(int)($s['client_id']??0),(int)($s['project_id']??0),$date); ?><tr><td><strong><?php echo esc_html($date?wp_date('d/m/Y',strtotime($date)):''); ?></strong><br><small>Submissão #<?php echo $sid; ?></small></td><td><?php echo esc_html((string)($s['client_name']??'')); ?><br><small><?php echo esc_html((string)($s['project_name']??'')); ?></small></td><td><?php echo esc_html((string)($s['location_name']??'')); ?></td><td><?php echo esc_html((string)($s['user_name']??$s['owner_name']??'')); ?></td><td><strong><?php echo (int)($summary['orders']??0); ?> referências</strong><br><small><?php echo esc_html((string)($summary['order_boxes']??0)); ?> caixas · <?php echo esc_html((string)($summary['order_units']??0)); ?> unidades</small></td><td><?php echo $schedule?esc_html((string)$schedule['name']):'<span class="ff-manual-badge warn">Auto ao enviar</span>'; ?></td><td><?php echo self::manualActionForm('order_suggestion',$sid,$filters,$defaultTestEmail,false); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
          <?php endif; ?>
          <script>(function(){const client=document.getElementById('ff-manual-client'),project=document.getElementById('ff-manual-project'),test=document.getElementById('ff-manual-global-test-email');function syncProjects(){if(!client||!project)return;const cid=client.value;Array.from(project.options).forEach(function(o){if(!o.value)return;o.hidden=cid!=='0'&&o.dataset.client!==cid;});if(project.selectedOptions.length&&project.selectedOptions[0].hidden)project.value='0';}if(client){client.addEventListener('change',syncProjects);syncProjects();}document.querySelectorAll('[data-manual-mail-form]').forEach(function(form){form.addEventListener('submit',function(ev){const audience=form.querySelector('select[name="audience"]');const hidden=form.querySelector('[data-manual-test-email-hidden]');if(hidden&&test)hidden.value=test.value.trim();if(audience&&audience.value.indexOf('test')===0&&(!hidden||!hidden.value)){ev.preventDefault();alert('Indica o email de teste no topo desta página.');}});});})();</script>
        </section>
        <?php
    }

    public static function renderPanel(): void {
        if (!current_user_can('routespro_manage')) return;
        global $wpdb;
        self::ensureSchema();
        $table = self::table();
        $clients = $wpdb->get_results("SELECT id,name FROM {$wpdb->prefix}routespro_clients ORDER BY name ASC", ARRAY_A) ?: [];
        $projects = $wpdb->get_results("SELECT id,client_id,name,status,meta_json FROM {$wpdb->prefix}routespro_projects ORDER BY name ASC", ARRAY_A) ?: [];
        $rows = $wpdb->get_results("SELECT s.*, c.name AS client_name, p.name AS project_name FROM {$table} s LEFT JOIN {$wpdb->prefix}routespro_clients c ON c.id=s.client_id LEFT JOIN {$wpdb->prefix}routespro_projects p ON p.id=s.project_id ORDER BY s.is_active DESC, s.id DESC", ARRAY_A) ?: [];
        $editId = absint($_GET['edit_report_schedule'] ?? 0);
        $edit = $editId ? self::get($editId) : [];
        $currentUser = wp_get_current_user();
        $defaultTestEmail = $currentUser && !empty($currentUser->user_email) ? sanitize_email((string)$currentUser->user_email) : '';
        $testProjectMembership = []; $testUserIds = [];
        foreach ($projects as $projectRow) {
            $pid=(int)($projectRow['id']??0); if(!$pid) continue;
            foreach(self::userIdsFromProjectMeta($projectRow['meta_json']??'') as $uid){$testUserIds[$uid]=$uid;$testProjectMembership[$uid][$pid]=$pid;}
        }
        $testUsers=$testUserIds?(array)get_users(['include'=>array_values($testUserIds),'orderby'=>'display_name','order'=>'ASC','fields'=>['ID','display_name','user_login']]):[];
        $defaults=[
            'id'=>0,'name'=>'Fecho diário do merchandiser','client_id'=>0,'project_id'=>0,'automation_type'=>'daily_close','client_delivery_mode'=>'immediate','weekday'=>1,'send_time'=>'20:00:00','start_date'=>wp_date('Y-m-d'),'end_date'=>'','cumulative_start'=>wp_date('Y-m-d'),'periods_json'=>'["daily","week_to_date"]','formats_json'=>'["xlsx","pdf"]','to_team'=>1,'to_clients'=>1,'extra_emails'=>'','test_email'=>$defaultTestEmail,'test_scope'=>'client','test_user_id'=>0,'subject_template'=>'[FieldFlow] {project_name}, {period_label}','intro_text'=>'Segue o fecho FieldFlow Intelligence do dia, com acumulado semanal, alertas por loja, recomendações e evolução operacional.','brand_primary'=>'#172033','is_active'=>1,'last_test_at'=>'','last_test_status'=>'','last_test_message'=>'','last_run_at'=>'','last_status'=>'','last_message'=>'','next_run_at'=>'',
        ];
        $edit=array_merge($defaults,$edit);
        $periods=json_decode((string)$edit['periods_json'],true); if(!is_array($periods))$periods=['daily','week_to_date'];
        $formats=json_decode((string)$edit['formats_json'],true); if(!is_array($formats)||!$formats)$formats=['xlsx','pdf'];
        $notice=sanitize_key($_GET['ff_report_notice']??'');
        $messages=['saved'=>'Automação guardada.','test_sent'=>'Teste enviado com sucesso.','test_failed'=>'O teste falhou. Consulta o detalhe técnico abaixo.','run_sent'=>'Envio executado.','run_failed'=>'O envio não foi concluído.','enabled'=>'Automação ativada.','paused'=>'Automação em pausa.','deleted'=>'Automação eliminada.','missing_scope'=>'Seleciona cliente e campanha.','invalid_scope'=>'A campanha não pertence ao cliente selecionado.'];
        $typeLabels=['daily_close'=>'Fecho diário','daily_digest'=>'Consolidado diário','weekly'=>'Weekly Intelligence','order_suggestion'=>'Sugestão de encomenda'];
        ?>
        <section id="fieldflow-report-automations" class="ff-mailcenter-automation">
          <style>
            .ff-mailcenter-automation .ff-mc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.ff-mailcenter-automation .ff-mc-step{border:1px solid #e2e8f0;border-radius:18px;padding:18px;background:#fff}.ff-mailcenter-automation .ff-mc-step h3{margin:0 0 12px;display:flex;gap:10px;align-items:center}.ff-mailcenter-automation .ff-mc-num{display:inline-grid;place-items:center;width:28px;height:28px;border-radius:999px;background:#0f172a;color:#fff;font-size:12px}.ff-mailcenter-automation .ff-mc-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.ff-mailcenter-automation .ff-mc-fields .wide{grid-column:1/-1}.ff-mailcenter-automation .ff-mc-choice{display:flex;gap:10px;flex-wrap:wrap}.ff-mailcenter-automation .ff-mc-choice label{padding:10px 12px;border:1px solid #dbe3ee;border-radius:12px;background:#f8fafc}.ff-mailcenter-automation .ff-mc-table{overflow:auto;border:1px solid #e2e8f0;border-radius:16px}.ff-mailcenter-automation .ff-mc-state{display:inline-block;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:800}.ff-mailcenter-automation .ff-mc-state.ok{background:#dcfce7;color:#166534}.ff-mailcenter-automation .ff-mc-state.off{background:#f1f5f9;color:#475569}.ff-mailcenter-automation .ff-mc-state.bad{background:#fee2e2;color:#991b1b}@media(max-width:1100px){.ff-mailcenter-automation .ff-mc-grid{grid-template-columns:1fr}}@media(max-width:782px){.ff-mailcenter-automation .ff-mc-fields{grid-template-columns:1fr}.ff-mailcenter-automation .ff-mc-fields .wide{grid-column:auto}}
          </style>
          <?php if($notice&&isset($messages[$notice])): ?><div class="notice <?php echo in_array($notice,['test_failed','run_failed','missing_scope','invalid_scope'],true)?'notice-error':'notice-success'; ?> is-dismissible"><p><?php echo esc_html($messages[$notice]); ?><?php if($notice==='test_failed'&&!empty($edit['last_test_message'])): ?><br><strong>Diagnóstico:</strong> <?php echo esc_html((string)$edit['last_test_message']); ?><?php endif; ?></p></div><?php endif; ?>
          <div class="rp-card" style="padding:0;overflow:hidden;margin-bottom:18px"><div style="padding:22px 24px;background:linear-gradient(135deg,#0f172a,#334155);color:#fff;display:flex;justify-content:space-between;gap:18px;align-items:center;flex-wrap:wrap"><div><div style="font-size:12px;text-transform:uppercase;letter-spacing:.12em;opacity:.72">Mail Center 2.0</div><h2 style="margin:6px 0 4px;color:#fff"><?php echo $editId?'Editar automação':'Criar automação'; ?></h2><p style="margin:0;max-width:900px;color:rgba(255,255,255,.82)">Um único fluxo para fecho diário, consolidado cliente e Weekly Intelligence. O email, Excel, PDF e CSV usam a mesma leitura Analytics.</p></div><div style="padding:10px 14px;border:1px solid rgba(255,255,255,.22);border-radius:999px"><?php echo count($rows); ?> automações</div></div></div>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="fieldflow_report_automation_save"><input type="hidden" name="schedule_id" value="<?php echo (int)$editId; ?>"><?php wp_nonce_field('fieldflow_report_automation_save'); ?>
            <div class="ff-mc-grid">
              <div class="ff-mc-step"><h3><span class="ff-mc-num">1</span>Quando enviar</h3><div class="ff-mc-fields"><div class="wide"><label class="rp-label">Tipo de automação</label><select name="automation_type" id="ff-mc-type"><option value="daily_close" <?php selected((string)$edit['automation_type'],'daily_close'); ?>>Fecho diário, quando o merchandiser termina a rota do dia</option><option value="daily_digest" <?php selected((string)$edit['automation_type'],'daily_digest'); ?>>Consolidado diário, a uma hora definida</option><option value="weekly" <?php selected((string)$edit['automation_type'],'weekly'); ?>>Weekly Intelligence, num dia da semana</option><option value="order_suggestion" <?php selected((string)$edit['automation_type'],'order_suggestion'); ?>>Sugestão de encomenda, imediatamente após uma sugestão gravada</option></select></div><div><label class="rp-label">Começa em</label><input type="date" name="start_date" value="<?php echo esc_attr($edit['start_date']); ?>" required></div><div><label class="rp-label">Termina em, opcional</label><input type="date" name="end_date" value="<?php echo esc_attr($edit['end_date']); ?>"></div><div class="ff-mc-weekday"><label class="rp-label">Dia semanal</label><select name="weekday"><?php foreach([1=>'Segunda',2=>'Terça',3=>'Quarta',4=>'Quinta',5=>'Sexta',6=>'Sábado',7=>'Domingo'] as $n=>$label): ?><option value="<?php echo $n; ?>" <?php selected((int)$edit['weekday'],$n); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div><div class="ff-mc-time"><label class="rp-label">Hora</label><input type="time" name="send_time" value="<?php echo esc_attr(substr((string)$edit['send_time'],0,5)); ?>"></div><div class="wide ff-mc-client-mode"><label class="rp-label">Envio ao cliente no fecho diário</label><select name="client_delivery_mode"><option value="immediate" <?php selected((string)$edit['client_delivery_mode'],'immediate'); ?>>Imediato, recebe quando cada merchandiser fecha o dia</option><option value="consolidated" <?php selected((string)$edit['client_delivery_mode'],'consolidated'); ?>>Consolidado, merchandiser recebe no fecho e cliente recebe um único resumo à hora definida</option></select></div></div></div>
              <div class="ff-mc-step"><h3><span class="ff-mc-num">2</span>Âmbito</h3><div class="ff-mc-fields"><div><label class="rp-label">Nome da automação</label><input type="text" name="name" value="<?php echo esc_attr($edit['name']); ?>"></div><div><label class="rp-label">Cliente</label><select name="client_id" id="ff-report-client" required><option value="0">Selecionar</option><?php foreach($clients as $client): ?><option value="<?php echo (int)$client['id']; ?>" <?php selected((int)$edit['client_id'],(int)$client['id']); ?>><?php echo esc_html($client['name']); ?></option><?php endforeach; ?></select></div><div class="wide"><label class="rp-label">Projeto / campanha</label><select name="project_id" id="ff-report-project" required><option value="0">Selecionar</option><?php foreach($projects as $project): ?><option data-client="<?php echo (int)$project['client_id']; ?>" value="<?php echo (int)$project['id']; ?>" <?php selected((int)$edit['project_id'],(int)$project['id']); ?>><?php echo esc_html($project['name']); ?></option><?php endforeach; ?></select></div><div class="wide ff-mc-cumulative"><label class="rp-label">Início do acumulado da campanha</label><input type="date" name="cumulative_start" value="<?php echo esc_attr($edit['cumulative_start']); ?>"></div></div></div>
              <div class="ff-mc-step ff-mc-recipients"><h3><span class="ff-mc-num">3</span>Para quem</h3><div class="ff-mc-choice"><label><input type="checkbox" name="to_team" value="1" <?php checked((int)$edit['to_team'],1); ?>> Merchandiser, visão pessoal</label><label><input type="checkbox" name="to_clients" value="1" <?php checked((int)$edit['to_clients'],1); ?>> Cliente, visão cliente</label><label><input type="checkbox" name="is_active" value="1" <?php checked((int)$edit['is_active'],1); ?>> Automação ativa</label></div><div style="margin-top:14px"><label class="rp-label">Emails adicionais</label><input type="text" name="extra_emails" value="<?php echo esc_attr($edit['extra_emails']); ?>" placeholder="direcao@empresa.pt, operacoes@empresa.pt"><small class="rp-muted">Recebem a mesma visão completa do cliente.</small></div></div>
              <div class="ff-mc-step ff-mc-periods"><h3><span class="ff-mc-num">4</span>O que incluir</h3><div class="ff-mc-choice"><label><input type="checkbox" name="periods[]" value="daily" <?php checked(in_array('daily',$periods,true)); ?>> Dia</label><label><input type="checkbox" name="periods[]" value="week_to_date" <?php checked(in_array('week_to_date',$periods,true)); ?>> Acumulado semanal</label><label><input type="checkbox" name="periods[]" value="weekly" <?php checked(in_array('weekly',$periods,true)); ?>> Últimos 7 dias</label><label><input type="checkbox" name="periods[]" value="cumulative" <?php checked(in_array('cumulative',$periods,true)); ?>> Acumulado campanha</label></div><p class="rp-muted" style="margin-bottom:0">O corpo inclui scores, alertas por loja, recomendações, Adaptive e Predictive Intelligence quando existirem dados.</p></div>
              <div class="ff-mc-step ff-mc-attachments"><h3><span class="ff-mc-num">5</span>Anexos e mensagem</h3><div class="ff-mc-choice"><label><input type="checkbox" name="formats[]" value="xlsx" <?php checked(in_array('xlsx',$formats,true)); ?>> Excel</label><label><input type="checkbox" name="formats[]" value="pdf" <?php checked(in_array('pdf',$formats,true)); ?>> PDF</label><label><input type="checkbox" name="formats[]" value="csv" <?php checked(in_array('csv',$formats,true)); ?>> CSV</label><label>Excel + PDF são predefinidos nos relatórios operacionais; CSV é opcional.</label></div><div style="margin-top:14px" class="ff-mc-fields"><div class="wide"><label class="rp-label">Assunto</label><input type="text" name="subject_template" value="<?php echo esc_attr($edit['subject_template']); ?>"><small class="rp-muted">Tokens gerais: {client_name}, {project_name}, {period_label}, {date}. Sugestão de encomenda: {store_name}, {operative_name}, {submission_id}, {order_lines}, {order_boxes}, {order_units}</small></div><div class="wide"><label class="rp-label">Introdução</label><textarea name="intro_text" rows="3" style="min-height:86px"><?php echo esc_textarea($edit['intro_text']); ?></textarea></div><div><label class="rp-label">Cor</label><input type="color" name="brand_primary" value="<?php echo esc_attr($edit['brand_primary']); ?>" style="height:43px;padding:4px"></div></div></div>
              <div class="ff-mc-step"><h3><span class="ff-mc-num">6</span>Testar e ativar</h3><div class="ff-mc-fields"><div class="wide"><label class="rp-label">Email de teste</label><input type="email" name="test_email" value="<?php echo esc_attr($edit['test_email']); ?>" placeholder="o-teu-email@empresa.pt"></div><div><label class="rp-label">Perfil</label><select name="test_scope" id="ff-report-test-scope"><option value="client" <?php selected((string)$edit['test_scope'],'client'); ?>>Cliente</option><option value="merchandiser" <?php selected((string)$edit['test_scope'],'merchandiser'); ?>>Merchandiser</option></select></div><div id="ff-report-test-user-wrap"><label class="rp-label">Merchandiser</label><select name="test_user_id" id="ff-report-test-user"><option value="0">Selecionar</option><?php foreach($testUsers as $testUser): $memberProjects=array_values((array)($testProjectMembership[(int)$testUser->ID]??[])); ?><option value="<?php echo (int)$testUser->ID; ?>" data-projects="<?php echo esc_attr(implode(',',array_map('intval',$memberProjects))); ?>" <?php selected((int)$edit['test_user_id'],(int)$testUser->ID); ?>><?php echo esc_html((string)($testUser->display_name?:$testUser->user_login)); ?></option><?php endforeach; ?></select></div></div><div class="rp-actions" style="margin-top:16px"><button class="button button-primary" type="submit" name="after_save_action" value="save"><?php echo $editId?'Guardar alterações':'Criar automação'; ?></button><button class="button" type="submit" name="after_save_action" value="test">Guardar e enviar teste</button><?php if($editId): ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=routespro-emails&mail_center_tab=automations')); ?>">Nova automação</a><?php endif; ?></div><?php if(!empty($edit['last_test_at'])): ?><div style="margin-top:12px;padding:10px 12px;border-radius:10px;background:#f8fafc"><strong>Último teste:</strong> <?php echo esc_html(wp_date('d/m/Y H:i',strtotime((string)$edit['last_test_at']))); ?> · <?php echo esc_html((string)$edit['last_test_status']); ?><br><small><?php echo esc_html((string)$edit['last_test_message']); ?></small></div><?php endif; ?></div>
            </div>
          </form>
          <div class="rp-card" style="margin-top:18px"><div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap"><div><h2 style="margin:0">Automações</h2><p class="rp-muted" style="margin:4px 0 0">Estado, próximo envio, último resultado e ações num só sítio.</p></div><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=routespro-email-log')); ?>">Ver histórico de envios</a></div><div class="ff-mc-table" style="margin-top:14px"><table class="widefat striped"><thead><tr><th>Automação</th><th>Âmbito</th><th>Trigger</th><th>Conteúdo</th><th>Estado</th><th>Ações</th></tr></thead><tbody><?php if(!$rows): ?><tr><td colspan="6">Ainda não existem automações.</td></tr><?php endif; ?><?php foreach($rows as $row): $rowPeriods=json_decode((string)$row['periods_json'],true)?:[];$rowFormats=json_decode((string)$row['formats_json'],true)?:[];$type=sanitize_key((string)($row['automation_type']??'weekly'));$days=[1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',7=>'Dom'];$trigger=$type==='daily_close'?'No fecho do dia':($type==='daily_digest'?'Diário às '.substr((string)$row['send_time'],0,5):($type==='order_suggestion'?'Ao gravar uma sugestão':(($days[(int)$row['weekday']]??'Sem').' às '.substr((string)$row['send_time'],0,5))));?><tr><td><strong><?php echo esc_html($row['name']); ?></strong><br><span class="ff-mc-state <?php echo $row['is_active']?'ok':'off'; ?>"><?php echo $row['is_active']?'ATIVA':'PAUSA'; ?></span></td><td><?php echo esc_html((string)$row['client_name']); ?><br><small><?php echo esc_html((string)$row['project_name']); ?></small></td><td><strong><?php echo esc_html($typeLabels[$type]??ucfirst($type)); ?></strong><br><small><?php echo esc_html($trigger); ?><?php if($type==='daily_close'&&(string)$row['client_delivery_mode']==='consolidated'): ?> · cliente consolidado <?php echo esc_html(substr((string)$row['send_time'],0,5)); ?><?php endif; ?></small></td><td><?php echo esc_html(implode(' + ',array_map(static fn($v)=>$v==='week_to_date'?'acum. semanal':$v,$rowPeriods))); ?><br><small><?php echo esc_html(strtoupper(implode(' + ',($rowFormats?:['xlsx','pdf'])))); ?></small></td><td><?php if((string)$row['last_status']==='failed'): ?><span class="ff-mc-state bad">FALHOU</span><?php elseif(!empty($row['last_run_at'])): ?><span class="ff-mc-state ok"><?php echo esc_html(strtoupper((string)$row['last_status'])); ?></span><?php else: ?><span class="ff-mc-state off">SEM ENVIO</span><?php endif; ?><br><small><?php echo esc_html($row['last_run_at']?wp_date('d/m/Y H:i',strtotime((string)$row['last_run_at'])):''); ?> <?php echo esc_html((string)$row['last_message']); ?></small><?php if(!empty($row['next_run_at'])): ?><br><small>Próximo: <?php echo esc_html(wp_date('d/m/Y H:i',strtotime((string)$row['next_run_at']))); ?></small><?php endif; ?></td><td><div style="display:flex;gap:6px;flex-wrap:wrap"><a class="button button-small" href="<?php echo esc_url(add_query_arg(['page'=>'routespro-emails','mail_center_tab'=>'automations','edit_report_schedule'=>(int)$row['id']],admin_url('admin.php'))); ?>">Editar</a><a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fieldflow_report_automation_test&schedule_id='.(int)$row['id']),'fieldflow_report_automation_action')); ?>">Testar</a><a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fieldflow_report_automation_run&schedule_id='.(int)$row['id']),'fieldflow_report_automation_action')); ?>">Enviar agora</a><a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fieldflow_report_automation_toggle&schedule_id='.(int)$row['id'].'&active='.($row['is_active']?0:1)),'fieldflow_report_automation_action')); ?>"><?php echo $row['is_active']?'Pausar':'Ativar'; ?></a><a class="button button-small" style="color:#b91c1c" onclick="return confirm('Eliminar esta automação?')" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fieldflow_report_automation_delete&schedule_id='.(int)$row['id']),'fieldflow_report_automation_action')); ?>">Eliminar</a></div></td></tr><?php endforeach; ?></tbody></table></div></div>
          <script>(function(){const type=document.getElementById('ff-mc-type'),weekday=document.querySelector('.ff-mc-weekday'),clientMode=document.querySelector('.ff-mc-client-mode'),time=document.querySelector('.ff-mc-time'),cumulative=document.querySelector('.ff-mc-cumulative'),periodStep=document.querySelector('.ff-mc-periods'),recipientStep=document.querySelector('.ff-mc-recipients'),attachmentStep=document.querySelector('.ff-mc-attachments');function sync(){if(!type)return;const v=type.value,isOrder=v==='order_suggestion';if(weekday)weekday.style.display=v==='weekly'?'block':'none';if(clientMode)clientMode.style.display=v==='daily_close'?'block':'none';if(time)time.style.display=(v==='daily_digest'||v==='weekly'||v==='daily_close')?'block':'none';if(cumulative)cumulative.style.display=isOrder?'none':'block';if(periodStep){periodStep.style.display=isOrder?'none':'block';}if(recipientStep){recipientStep.querySelectorAll('input[name="to_team"],input[name="to_clients"]').forEach(function(el){if(isOrder)el.checked=true;el.disabled=isOrder;});let note=recipientStep.querySelector('.ff-mc-order-note');if(isOrder&&!note){note=document.createElement('p');note.className='rp-muted ff-mc-order-note';note.style.marginTop='12px';note.textContent='Na sugestão de encomenda, o operativo que submeteu e os utilizadores associados ao cliente são destinatários obrigatórios.';recipientStep.appendChild(note);}else if(note){note.style.display=isOrder?'block':'none';}}if(attachmentStep){attachmentStep.querySelectorAll('input[name="formats[]"]').forEach(function(el){if(isOrder){el.checked=el.value==='xlsx'||el.value==='pdf';el.disabled=true;}else{el.disabled=false;}});let note=attachmentStep.querySelector('.ff-mc-order-attach-note');if(isOrder&&!note){note=document.createElement('p');note.className='rp-muted ff-mc-order-attach-note';note.textContent='Excel e PDF são obrigatórios neste evento. O assunto, introdução, cor, teste e emails adicionais continuam editáveis.';attachmentStep.appendChild(note);}else if(note){note.style.display=isOrder?'block':'none';}}}if(type){type.addEventListener('change',sync);sync();}})();</script>
        </section>
        <?php
    }

    public static function renderTestCenter(): void {
        if (!current_user_can('routespro_manage')) return;
        global $wpdb;
        self::ensureSchema();
        $rows=$wpdb->get_results("SELECT s.*,c.name client_name,p.name project_name FROM ".self::table()." s LEFT JOIN {$wpdb->prefix}routespro_clients c ON c.id=s.client_id LEFT JOIN {$wpdb->prefix}routespro_projects p ON p.id=s.project_id ORDER BY s.is_active DESC,s.id DESC",ARRAY_A)?:[];
        $userIds=[];$memberships=[];
        foreach($rows as $r){$pid=(int)$r['project_id'];foreach(self::merchandiserUserIds($pid) as $uid){$userIds[$uid]=$uid;$memberships[$uid][$pid]=$pid;}}
        $users=$userIds?(array)get_users(['include'=>array_values($userIds),'orderby'=>'display_name','order'=>'ASC','fields'=>['ID','display_name','user_login']]):[];
        $me=wp_get_current_user();$email=$me&&!empty($me->user_email)?sanitize_email((string)$me->user_email):'';
        ?>
        <div class="rp-card"><h2 style="margin-top:0">Test Center</h2><p class="rp-muted">Escolhe uma automação, data e perfil. O teste usa exatamente o mesmo motor que o envio real, sem contactar os destinatários configurados.</p><?php if(!$rows): ?><div class="notice notice-warning inline"><p>Cria primeiro uma automação.</p></div><?php else: ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px"><input type="hidden" name="action" value="fieldflow_mailcenter_test"><?php wp_nonce_field('fieldflow_mailcenter_test'); ?><div style="grid-column:1/-1"><label class="rp-label">Automação</label><select name="schedule_id" required><option value="">Selecionar</option><?php foreach($rows as $r): ?><option value="<?php echo (int)$r['id']; ?>"><?php echo esc_html($r['name'].' · '.$r['client_name'].' · '.$r['project_name']); ?></option><?php endforeach; ?></select></div><div><label class="rp-label">Data a simular</label><input type="date" name="test_date" value="<?php echo esc_attr(wp_date('Y-m-d')); ?>"></div><div><label class="rp-label">Enviar para</label><input type="email" name="test_email" value="<?php echo esc_attr($email); ?>" required></div><div><label class="rp-label">Perfil</label><select name="test_scope" id="ff-mct-scope"><option value="client">Cliente, visão completa</option><option value="merchandiser">Merchandiser, visão pessoal</option></select></div><div id="ff-mct-user-wrap"><label class="rp-label">Merchandiser</label><select name="test_user_id"><option value="0">Selecionar</option><?php foreach($users as $u): ?><option value="<?php echo (int)$u->ID; ?>"><?php echo esc_html((string)($u->display_name?:$u->user_login)); ?></option><?php endforeach; ?></select></div><div style="grid-column:1/-1"><button class="button button-primary">Enviar teste</button> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=routespro-email-log')); ?>">Ver histórico</a></div></form><script>(function(){const s=document.getElementById('ff-mct-scope'),w=document.getElementById('ff-mct-user-wrap');function sync(){if(w)w.style.display=s&&s.value==='merchandiser'?'block':'none';}if(s){s.addEventListener('change',sync);sync();}})();</script><?php endif; ?></div>
        <?php
    }

}
