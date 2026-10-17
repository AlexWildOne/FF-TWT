<?php
namespace RoutesPro\Privacy;

if (!defined('ABSPATH')) exit;

class PrivacyGovernance {
    public const OPTION = 'routespro_privacy_governance';
    public const CRON_HOOK = 'fieldflow_privacy_retention_daily';
    private const VERSION = '1.0';
    private const BATCH_SIZE = 2000;

    public static function registerHooks(): void {
        add_action('init', [self::class, 'ensureSchedule']);
        add_action(self::CRON_HOOK, [self::class, 'scheduledRun']);
        add_filter('wp_privacy_personal_data_exporters', [self::class, 'registerExporter']);
        add_filter('wp_privacy_personal_data_erasers', [self::class, 'registerEraser']);
    }

    public static function defaults(): array {
        return [
            'enabled' => 1,
            'mode' => 'shadow',
            'legal_hold' => 0,
            'ai_usage_days' => 90,
            'system_logs_days' => 90,
            'email_logs_days' => 365,
            'security_events_days' => 180,
            'observability_days' => 180,
            'media_shadow_days' => 90,
            'process_runs_days' => 180,
            'core_events_days' => 365,
            'audit_days' => 730,
            'expired_mobile_days' => 30,
        ];
    }

    public static function settings(): array {
        $saved = get_option(self::OPTION, []);
        return self::normalise(is_array($saved) ? $saved : []);
    }

    public static function save(array $input): array {
        $settings = self::normalise($input);
        update_option(self::OPTION, $settings, false);
        return $settings;
    }

    private static function normalise(array $input): array {
        $values = wp_parse_args($input, self::defaults());
        $values['enabled'] = !empty($values['enabled']) ? 1 : 0;
        $values['legal_hold'] = !empty($values['legal_hold']) ? 1 : 0;
        $values['mode'] = in_array((string)$values['mode'], ['shadow', 'enforce'], true) ? (string)$values['mode'] : 'shadow';
        foreach (array_keys(self::defaults()) as $key) {
            if (substr($key, -5) === '_days') $values[$key] = max(7, min(3650, absint($values[$key])));
        }
        return $values;
    }

    public static function ensureSchedule(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + (2 * HOUR_IN_SECONDS), 'daily', self::CRON_HOOK);
        }
    }

    public static function scheduledRun(): void {
        $settings = self::settings();
        if (empty($settings['enabled'])) return;
        self::runRetention($settings['mode'] === 'enforce' ? 'apply' : 'preview', 0, 'cron');
    }

    public static function policies(): array {
        global $wpdb;
        $prefix = $wpdb->prefix . 'routespro_';
        return [
            'ai_usage' => ['label'=>'Utilização de IA','table'=>$prefix.'ai_usage_logs','date'=>'created_at','days'=>'ai_usage_days','condition'=>'1=1'],
            'system_logs' => ['label'=>'Logs técnicos','table'=>$prefix.'system_logs','date'=>'created_at','days'=>'system_logs_days','condition'=>'1=1'],
            'email_logs' => ['label'=>'Registos de email','table'=>$prefix.'email_logs','date'=>'created_at','days'=>'email_logs_days','condition'=>'1=1'],
            'security_events' => ['label'=>'Eventos de segurança resolvidos','table'=>$prefix.'security_events','date'=>'last_seen_at','days'=>'security_events_days','condition'=>"status IN ('resolved','closed','dismissed')"],
            'observability' => ['label'=>'Eventos técnicos resolvidos','table'=>$prefix.'observability_events','date'=>'last_seen_at','days'=>'observability_days','condition'=>"status IN ('resolved','closed','dismissed')"],
            'media_shadow' => ['label'=>'Decisões Shadow de media','table'=>$prefix.'media_shadow_access','date'=>'last_seen_at','days'=>'media_shadow_days','condition'=>'1=1'],
            'process_runs' => ['label'=>'Execuções concluídas','table'=>$prefix.'process_runs','date'=>'created_at','days'=>'process_runs_days','condition'=>"status IN ('completed','failed','cancelled')"],
            'core_events' => ['label'=>'Eventos centrais processados','table'=>$prefix.'core_events','date'=>'created_at','days'=>'core_events_days','condition'=>"status IN ('processed','completed','failed')"],
            'audit' => ['label'=>'Auditoria histórica','table'=>$prefix.'audit_log','date'=>'created_at','days'=>'audit_days','condition'=>'1=1'],
            'mobile_sessions' => ['label'=>'Sessões mobile expiradas','table'=>$prefix.'mobile_sessions','date'=>'refresh_expires_at','days'=>'expired_mobile_days','condition'=>'(revoked_at IS NOT NULL OR refresh_expires_at < UTC_TIMESTAMP())'],
            'mobile_mutations' => ['label'=>'Mutações mobile expiradas','table'=>$prefix.'mobile_mutations','date'=>'expires_at','days'=>'expired_mobile_days','condition'=>'expires_at < UTC_TIMESTAMP()'],
            'mobile_web_sessions' => ['label'=>'Sessões WebView expiradas','table'=>$prefix.'mobile_web_sessions','date'=>'ticket_expires_at','days'=>'expired_mobile_days','condition'=>'(revoked_at IS NOT NULL OR ticket_expires_at < UTC_TIMESTAMP())'],
        ];
    }

    public static function runRetention(string $requestedMode = 'preview', int $actorUserId = 0, string $source = 'manual'): array {
        global $wpdb;
        $settings = self::settings();
        $requestedMode = $requestedMode === 'apply' ? 'apply' : 'preview';
        $canApply = $requestedMode === 'apply' && $settings['mode'] === 'enforce' && empty($settings['legal_hold']);
        $effectiveMode = $canApply ? 'apply' : 'preview';
        $summary = ['eligible'=>0,'affected'=>0,'policies'=>[],'requested_mode'=>$requestedMode,'effective_mode'=>$effectiveMode];

        foreach (self::policies() as $key => $policy) {
            if (!self::tableExists($policy['table'])) continue;
            $days = (int)$settings[$policy['days']];
            $cutoff = gmdate('Y-m-d H:i:s', current_time('timestamp', true) - ($days * DAY_IN_SECONDS));
            $where = $policy['date'] . ' < %s AND (' . $policy['condition'] . ')';
            $eligible = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$policy['table']} WHERE {$where}", $cutoff));
            $affected = 0;
            if ($effectiveMode === 'apply' && $eligible > 0) {
                $affected = $wpdb->query($wpdb->prepare("DELETE FROM {$policy['table']} WHERE {$where} ORDER BY {$policy['date']} ASC LIMIT " . self::BATCH_SIZE, $cutoff));
                $affected = is_numeric($affected) ? (int)$affected : 0;
            }
            $summary['eligible'] += $eligible;
            $summary['affected'] += $affected;
            $summary['policies'][$key] = ['label'=>$policy['label'],'days'=>$days,'eligible'=>$eligible,'affected'=>$affected];
        }

        self::recordRun($effectiveMode, $source, $actorUserId, $summary);
        update_option('routespro_privacy_last_run', [
            'mode'=>$effectiveMode,
            'source'=>sanitize_key($source),
            'eligible'=>(int)$summary['eligible'],
            'affected'=>(int)$summary['affected'],
            'ran_at'=>current_time('mysql', true),
        ], false);
        return $summary;
    }

    public static function createRequest(array $data): int {
        global $wpdb;
        $table = $wpdb->prefix . 'routespro_privacy_requests';
        if (!self::tableExists($table)) return 0;
        $type = sanitize_key((string)($data['request_type'] ?? 'access'));
        if (!in_array($type, ['access','export','erase','anonymise','rectify'], true)) $type = 'access';
        $email = sanitize_email((string)($data['subject_email'] ?? ''));
        $userId = absint($data['subject_user_id'] ?? 0);
        if ($userId <= 0 && $email !== '') {
            $user = get_user_by('email', $email);
            if ($user) $userId = (int)$user->ID;
        }
        if ($userId <= 0 && $email === '') return 0;
        $inserted = $wpdb->insert($table, [
            'request_uuid'=>wp_generate_uuid4(),
            'request_type'=>$type,
            'status'=>'received',
            'subject_user_id'=>$userId ?: null,
            'subject_email'=>$email ?: null,
            'client_id'=>absint($data['client_id'] ?? 0),
            'project_id'=>absint($data['project_id'] ?? 0),
            'notes'=>sanitize_textarea_field((string)($data['notes'] ?? '')),
            'requested_by'=>get_current_user_id(),
            'requested_at'=>current_time('mysql', true),
            'created_at'=>current_time('mysql', true),
            'updated_at'=>current_time('mysql', true),
        ], ['%s','%s','%s','%d','%s','%d','%d','%s','%d','%s','%s','%s']);
        return $inserted ? (int)$wpdb->insert_id : 0;
    }

    public static function updateRequestStatus(int $requestId, string $status): bool {
        global $wpdb;
        $allowed = ['received','verified','in_progress','completed','rejected','cancelled'];
        $status = sanitize_key($status);
        if ($requestId <= 0 || !in_array($status, $allowed, true)) return false;
        $data = ['status'=>$status,'updated_at'=>current_time('mysql', true)];
        $formats = ['%s','%s'];
        if (in_array($status, ['completed','rejected','cancelled'], true)) {
            $data['completed_at'] = current_time('mysql', true);
            $data['completed_by'] = get_current_user_id();
            $formats[] = '%s';
            $formats[] = '%d';
        }
        return $wpdb->update($wpdb->prefix.'routespro_privacy_requests', $data, ['id'=>$requestId], $formats, ['%d']) !== false;
    }

    public static function recentRequests(int $limit = 50): array {
        global $wpdb;
        $table = $wpdb->prefix . 'routespro_privacy_requests';
        if (!self::tableExists($table)) return [];
        $limit = max(1, min(200, $limit));
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT {$limit}", ARRAY_A) ?: [];
    }

    public static function status(): array {
        global $wpdb;
        $settings = self::settings();
        $requestTable = $wpdb->prefix . 'routespro_privacy_requests';
        $runTable = $wpdb->prefix . 'routespro_privacy_retention_runs';
        return [
            'version'=>self::VERSION,
            'enabled'=>!empty($settings['enabled']),
            'mode'=>$settings['mode'],
            'legal_hold'=>!empty($settings['legal_hold']),
            'tables_ready'=>self::tableExists($requestTable) && self::tableExists($runTable),
            'open_requests'=>self::tableExists($requestTable) ? (int)$wpdb->get_var("SELECT COUNT(*) FROM {$requestTable} WHERE status NOT IN ('completed','rejected','cancelled')") : 0,
            'last_run'=>get_option('routespro_privacy_last_run', []),
            'wordpress_exporter'=>true,
            'wordpress_eraser'=>true,
        ];
    }

    public static function registerExporter(array $exporters): array {
        $exporters['fieldflow'] = [
            'exporter_friendly_name'=>'FieldFlow',
            'callback'=>[self::class, 'exportPersonalData'],
        ];
        return $exporters;
    }

    public static function registerEraser(array $erasers): array {
        $erasers['fieldflow'] = [
            'eraser_friendly_name'=>'FieldFlow',
            'callback'=>[self::class, 'erasePersonalData'],
        ];
        return $erasers;
    }

    public static function exportPersonalData(string $emailAddress, int $page = 1): array {
        global $wpdb;
        $user = get_user_by('email', $emailAddress);
        if (!$user) return ['data'=>[], 'done'=>true];
        $userId = (int)$user->ID;
        $limit = 50;
        $offset = max(0, ($page - 1) * $limit);
        $table = $wpdb->prefix . 'routespro_form_submissions';
        if (!self::tableExists($table)) return ['data'=>[], 'done'=>true];
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,form_id,client_id,project_id,location_id,submitted_at,status FROM {$table} WHERE user_id=%d ORDER BY id ASC LIMIT %d OFFSET %d",
            $userId, $limit, $offset
        ), ARRAY_A) ?: [];
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'group_id'=>'fieldflow-submissions',
                'group_label'=>'Submissões FieldFlow',
                'item_id'=>'fieldflow-submission-'.(int)$row['id'],
                'data'=>[
                    ['name'=>'ID da submissão','value'=>(string)$row['id']],
                    ['name'=>'Formulário','value'=>(string)$row['form_id']],
                    ['name'=>'Cliente','value'=>(string)($row['client_id'] ?? 0)],
                    ['name'=>'Projeto','value'=>(string)($row['project_id'] ?? 0)],
                    ['name'=>'Local','value'=>(string)($row['location_id'] ?? 0)],
                    ['name'=>'Data','value'=>(string)$row['submitted_at']],
                    ['name'=>'Estado','value'=>(string)$row['status']],
                ],
            ];
        }
        if ($page === 1) {
            $data[] = [
                'group_id'=>'fieldflow-profile',
                'group_label'=>'Perfil FieldFlow',
                'item_id'=>'fieldflow-user-'.$userId,
                'data'=>[
                    ['name'=>'ID WordPress','value'=>(string)$userId],
                    ['name'=>'Email','value'=>$emailAddress],
                    ['name'=>'Funções','value'=>implode(', ', (array)$user->roles)],
                ],
            ];
        }
        return ['data'=>$data, 'done'=>count($rows) < $limit];
    }

    public static function erasePersonalData(string $emailAddress, int $page = 1): array {
        global $wpdb;
        $settings = self::settings();
        if (!empty($settings['legal_hold'])) {
            return ['items_removed'=>false,'items_retained'=>true,'messages'=>['A retenção legal global está ativa no Centro de Privacidade.'],'done'=>true];
        }
        $user = get_user_by('email', $emailAddress);
        if (!$user) return ['items_removed'=>false,'items_retained'=>false,'messages'=>[],'done'=>true];
        $userId = (int)$user->ID;
        $changed = 0;
        $updates = [
            ['form_submissions','user_id',['user_id'=>0]],
            ['form_record_versions','user_id',['user_id'=>0]],
            ['form_record_versions','owner_user_id',['owner_user_id'=>0]],
            ['ai_usage_logs','user_id',['user_id'=>0]],
            ['system_logs','user_id',['user_id'=>0]],
            ['events','user_id',['user_id'=>0]],
            ['audit_log','actor_user_id',['actor_user_id'=>0]],
            ['security_events','user_id',['user_id'=>0,'device_id'=>null]],
            ['observability_events','user_id',['user_id'=>0,'device_id'=>null]],
        ];
        foreach ($updates as $update) {
            $table = $wpdb->prefix . 'routespro_' . $update[0];
            if (!self::tableExists($table)) continue;
            $result = $wpdb->update($table, $update[2], [$update[1]=>$userId]);
            if (is_numeric($result)) $changed += (int)$result;
        }
        foreach (['mobile_web_sessions','mobile_mutations','mobile_sync_devices','mobile_sessions','media_shadow_access'] as $suffix) {
            $table = $wpdb->prefix . 'routespro_' . $suffix;
            if (!self::tableExists($table)) continue;
            $result = $wpdb->delete($table, ['user_id'=>$userId], ['%d']);
            if (is_numeric($result)) $changed += (int)$result;
        }
        $changed += self::eraseUserDrafts($userId);
        $emailTable = $wpdb->prefix . 'routespro_email_logs';
        if (self::tableExists($emailTable)) {
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$emailTable} SET recipient_user_id=NULL,recipient_email=NULL,recipient_name=NULL,body=NULL,meta_json=NULL WHERE recipient_user_id=%d OR recipient_email=%s",
                $userId, $emailAddress
            ));
            if (is_numeric($result)) $changed += (int)$result;
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$emailTable} SET sender_user_id=NULL,body=NULL,meta_json=NULL WHERE sender_user_id=%d",
                $userId
            ));
            if (is_numeric($result)) $changed += (int)$result;
        }
        $expenses = $wpdb->prefix . 'routespro_expenses';
        $expenseRecords = self::tableExists($expenses) ? (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$expenses} WHERE user_id=%d", $userId)) : 0;
        $billing = $wpdb->prefix . 'routespro_billing_subscriptions';
        $billingRecords = self::tableExists($billing) ? (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$billing} WHERE billing_user_id=%d", $userId)) : 0;
        $retained = $expenseRecords > 0 || $billingRecords > 0;
        $messages = [];
        if ($expenseRecords > 0) $messages[] = 'Os documentos de despesa permanecem retidos para validação fiscal e devem ser revistos segundo a obrigação legal aplicável.';
        if ($billingRecords > 0) $messages[] = 'Os registos de faturação permanecem retidos para cumprimento contratual, contabilístico e fiscal.';
        return ['items_removed'=>$changed > 0,'items_retained'=>$retained,'messages'=>$messages,'done'=>true];
    }

    private static function recordRun(string $mode, string $source, int $actorUserId, array $summary): void {
        global $wpdb;
        $table = $wpdb->prefix . 'routespro_privacy_retention_runs';
        if (!self::tableExists($table)) return;
        $wpdb->insert($table, [
            'run_uuid'=>wp_generate_uuid4(),
            'run_mode'=>$mode,
            'source'=>sanitize_key($source),
            'status'=>'completed',
            'eligible_count'=>(int)$summary['eligible'],
            'affected_count'=>(int)$summary['affected'],
            'summary_json'=>wp_json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'initiated_by'=>$actorUserId ?: null,
            'started_at'=>current_time('mysql', true),
            'completed_at'=>current_time('mysql', true),
            'created_at'=>current_time('mysql', true),
        ], ['%s','%s','%s','%s','%d','%d','%s','%d','%s','%s','%s']);
    }

    private static function eraseUserDrafts(int $userId): int {
        global $wpdb;
        $drafts = $wpdb->prefix . 'routespro_form_drafts';
        $files = $wpdb->prefix . 'routespro_form_draft_files';
        if (!self::tableExists($drafts) || !self::tableExists($files)) return 0;
        $ids = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT id FROM {$drafts} WHERE user_id=%d LIMIT 500", $userId)) ?: []);
        if (!$ids) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $paths = $wpdb->get_col($wpdb->prepare("SELECT storage_path FROM {$files} WHERE draft_id IN ({$placeholders})", ...$ids)) ?: [];
        $uploads = wp_upload_dir();
        $allowedRoot = trailingslashit(wp_normalize_path((string)($uploads['basedir'] ?? ''))) . 'fieldflow-private-drafts/';
        foreach ($paths as $path) {
            $normal = wp_normalize_path((string)$path);
            if ($allowedRoot !== 'fieldflow-private-drafts/' && strpos($normal, $allowedRoot) === 0 && is_file($normal)) @unlink($normal);
        }
        $deletedFiles = $wpdb->query($wpdb->prepare("DELETE FROM {$files} WHERE draft_id IN ({$placeholders})", ...$ids));
        $deletedDrafts = $wpdb->query($wpdb->prepare("DELETE FROM {$drafts} WHERE id IN ({$placeholders})", ...$ids));
        return max(0, (int)$deletedFiles) + max(0, (int)$deletedDrafts);
    }

    private static function tableExists(string $table): bool {
        global $wpdb;
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}
