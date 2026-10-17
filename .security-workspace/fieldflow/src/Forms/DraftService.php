<?php
namespace RoutesPro\Forms;

if (!defined('ABSPATH')) exit;

class DraftService {
    public const TERRAIN_CONTINUITY_CONTRACT = 'fieldflow-field-form-terrain-continuity-v1';
    public const NONCE_ACTION = 'routespro_form_draft_sync';
    public const TTL_SECONDS = 2592000; // 30 dias
    public const MAX_PAYLOAD_BYTES = 2097152; // 2 MB

    private static $booted = false;
    private static $active_draft = null;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;
        add_action('wp_ajax_routespro_draft_save', [self::class, 'ajax_save']);
        add_action('wp_ajax_routespro_draft_load', [self::class, 'ajax_load']);
        add_action('wp_ajax_routespro_draft_delete', [self::class, 'ajax_delete']);
        add_action('wp_ajax_routespro_draft_upload', [self::class, 'ajax_upload']);
        add_action('wp_ajax_routespro_draft_download', [self::class, 'ajax_download']);
        add_action('fieldflow_form_draft_cleanup', [self::class, 'cleanup_expired']);
        add_action('fieldflow_delete_submitted_draft', [self::class, 'delete_submitted_draft'], 10, 2);
        if (!wp_next_scheduled('fieldflow_form_draft_cleanup')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'fieldflow_form_draft_cleanup');
        }
    }

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_form_drafts';
    }

    public static function files_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_form_draft_files';
    }

    /**
     * Último rascunho realmente preenchido para uma visita. É usado apenas em
     * recuperação administrativa, nunca para submeter silenciosamente em front.
     */
    public static function recovery_draft_for_stop(int $stop_id): ?array {
        $stop_id = absint($stop_id);
        if ($stop_id <= 0) return null;
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE route_stop_id=%d ORDER BY updated_at DESC,id DESC LIMIT 12',
            $stop_id
        ), ARRAY_A) ?: [];
        foreach ($rows as $row) {
            if (self::is_expired($row)) continue;
            $payload = self::decode_payload($row['payload_json'] ?? '');
            if (!$payload) continue;
            $answer_count = 0;
            foreach ((array)($payload['controls'] ?? []) as $control) {
                if (!is_array($control)) continue;
                $type = sanitize_key((string)($control['type'] ?? ''));
                if (in_array($type, ['checkbox','radio'], true) && empty($control['checked'])) continue;
                $value = $control['value'] ?? '';
                if (is_array($value)) {
                    $has_value = false;
                    foreach ($value as $part) if (trim((string)$part) !== '') { $has_value = true; break; }
                    if (!$has_value) continue;
                } elseif (trim((string)$value) === '') {
                    continue;
                }
                $answer_count++;
            }
            $file_count = (int)$wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . self::files_table() . ' WHERE draft_id=%d',
                (int)$row['id']
            ));
            if ($answer_count <= 0 && $file_count <= 0) continue;
            $row['_payload'] = $payload;
            $row['_answer_count'] = $answer_count;
            $row['_file_count'] = $file_count;
            return $row;
        }
        return null;
    }

    /**
     * Confirma que um rascunho pode ser promovido sem perder respostas ou
     * ficheiros. Se o formulário mudou ou um anexo não chegou ao servidor, a
     * recuperação administrativa é bloqueada e o rascunho fica intacto.
     *
     * @return array|\WP_Error
     */
    public static function validate_recovery_draft(array $row) {
        if (!$row || self::is_expired($row)) {
            return new \WP_Error('fieldflow_recovery_draft_expired', 'O rascunho já não está dentro do período de recuperação.', ['status' => 409]);
        }
        $payload = is_array($row['_payload'] ?? null) ? $row['_payload'] : self::decode_payload($row['payload_json'] ?? '');
        if (!$payload) {
            return new \WP_Error('fieldflow_recovery_draft_payload_invalid', 'O rascunho não contém um snapshot válido.', ['status' => 409]);
        }
        $form_id = absint($row['form_id'] ?? 0);
        $form = $form_id > 0 ? Forms::get_form($form_id) : null;
        if (!$form || ($form['status'] ?? '') !== 'active') {
            return new \WP_Error('fieldflow_recovery_form_inactive', 'O formulário deste rascunho já não está ativo.', ['status' => 409]);
        }
        $context = [
            'client_id' => absint($row['client_id'] ?? 0),
            'project_id' => absint($row['project_id'] ?? 0),
            'route_id' => absint($row['route_id'] ?? 0),
            'route_stop_id' => absint($row['route_stop_id'] ?? 0),
            'stop_id' => absint($row['route_stop_id'] ?? 0),
            'location_id' => absint($row['location_id'] ?? 0),
        ];
        $report_day = DailySubmissionService::normalise_report_day((string)($row['report_day'] ?? ''));
        $current_schema = self::schema_for_scope($form, $form_id, $context, $report_day);
        $current_revision = substr(hash('sha256', (string)wp_json_encode($current_schema)), 0, 20);
        $draft_revision = sanitize_text_field((string)($row['schema_revision'] ?? $payload['schemaRevision'] ?? ''));
        $schema_rebased = false;
        if ($draft_revision === '' || !hash_equals($current_revision, $draft_revision)) {
            $compatibility = self::recovery_schema_compatibility($payload, $current_schema);
            if (empty($compatibility['compatible'])) {
                return new \WP_Error(
                    'fieldflow_recovery_schema_changed',
                    'O formulário foi alterado e existem respostas ou fotografias antigas sem correspondência segura. O rascunho foi mantido intacto.',
                    [
                        'status' => 409,
                        'draft_revision' => $draft_revision,
                        'current_revision' => $current_revision,
                        'unknown_question_keys' => (array)($compatibility['unknown_question_keys'] ?? []),
                    ]
                );
            }
            $schema_rebased = true;
        }

        $desired = self::desired_file_counts($payload);
        $expected_files = 0;
        $verified_files = 0;
        global $wpdb;
        foreach ($desired as $group => $expected) {
            $expected = max(0, (int)$expected);
            if ($expected <= 0) continue;
            $expected_files += $expected;
            $parts = explode('|', (string)$group);
            $ordinal = (int)array_pop($parts);
            $input_name = implode('|', $parts);
            $rows = $wpdb->get_results($wpdb->prepare(
                'SELECT storage_path FROM ' . self::files_table() . ' WHERE draft_id=%d AND input_name=%s AND input_ordinal=%d ORDER BY file_index,id LIMIT %d',
                absint($row['id'] ?? 0), $input_name, $ordinal, $expected
            ), ARRAY_A) ?: [];
            if (count($rows) < $expected) {
                return new \WP_Error(
                    'fieldflow_recovery_files_not_synced',
                    'Existem fotografias ou ficheiros deste rascunho que ainda não chegaram totalmente ao servidor. A visita não foi fechada.',
                    ['status' => 409, 'input_name' => $input_name, 'expected' => $expected, 'available' => count($rows)]
                );
            }
            foreach ($rows as $file_row) {
                $path = (string)($file_row['storage_path'] ?? '');
                if ($path === '' || !is_file($path)) {
                    return new \WP_Error(
                        'fieldflow_recovery_file_missing',
                        'Um dos anexos sincronizados do rascunho já não está disponível no armazenamento temporário. A visita não foi fechada.',
                        ['status' => 409, 'input_name' => $input_name]
                    );
                }
                $verified_files++;
            }
        }
        return [
            'schema_revision' => $current_revision,
            'schema_rebased' => $schema_rebased,
            'expected_files' => $expected_files,
            'verified_files' => $verified_files,
        ];
    }

    /**
     * Reconstrói o POST PHP a partir do snapshot do browser. parse_str replica a
     * semântica real dos nomes com [] usados por matrizes, checkboxes e produtos.
     */
    public static function submission_post_from_draft(array $row): array {
        $payload = is_array($row['_payload'] ?? null) ? $row['_payload'] : self::decode_payload($row['payload_json'] ?? '');
        if (!$payload) return [];
        $pairs = [];
        foreach ((array)($payload['controls'] ?? []) as $control) {
            if (!is_array($control)) continue;
            $name = self::sanitize_control_name((string)($control['name'] ?? ''));
            if ($name === '') continue;
            $type = sanitize_key((string)($control['type'] ?? ''));
            if (in_array($type, ['checkbox','radio'], true) && empty($control['checked'])) continue;
            $values = $type === 'select-multiple' ? (array)($control['value'] ?? []) : [$control['value'] ?? ''];
            foreach ($values as $value) {
                if (is_array($value) || is_object($value)) continue;
                $pairs[] = rawurlencode($name) . '=' . rawurlencode((string)$value);
            }
        }
        $post = [];
        if ($pairs) parse_str(implode('&', $pairs), $post);
        $post['routespro_form_id'] = (int)($row['form_id'] ?? $payload['formId'] ?? 0);
        $post['routespro_binding_id'] = (int)($row['binding_id'] ?? 0);
        $post['routespro_client_id'] = (int)($row['client_id'] ?? 0);
        $post['routespro_project_id'] = (int)($row['project_id'] ?? 0);
        $post['routespro_route_id'] = (int)($row['route_id'] ?? 0);
        $post['routespro_stop_id'] = (int)($row['route_stop_id'] ?? 0);
        $post['routespro_location_id'] = (int)($row['location_id'] ?? 0);
        $post['routespro_context_date'] = sanitize_text_field((string)($row['report_day'] ?? ''));
        $post['routespro_report_day'] = sanitize_text_field((string)($row['report_day'] ?? ''));
        $post['routespro_existing_submission_id'] = (int)($payload['existingSubmissionId'] ?? 0);
        $post['routespro_edit_existing'] = !empty($payload['editMode']) ? 1 : 0;
        $post['routespro_draft_id'] = (int)($row['id'] ?? 0);
        $post['routespro_ajax'] = 1;
        return $post;
    }

    public static function ajax_save(): void {
        $scope = self::request_scope(true);
        if (is_wp_error($scope)) self::send_error($scope);

        $raw = isset($_POST['payload']) ? (string)wp_unslash($_POST['payload']) : '';
        if ($raw === '' || strlen($raw) > self::MAX_PAYLOAD_BYTES) {
            wp_send_json_error(['code' => 'draft_payload_invalid', 'message' => 'O rascunho é demasiado grande ou inválido.'], 400);
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload) || (int)($payload['version'] ?? 0) !== 2) {
            wp_send_json_error(['code' => 'draft_payload_invalid', 'message' => 'Formato de rascunho inválido.'], 400);
        }
        $payload = self::sanitize_payload($payload);
        // 9BJ: the server stamps the canonical store identity resolved by
        // SubmissionContext. Client-provided identity is never authoritative.
        $payload['identity'] = self::payload_identity_from_scope($scope);
        $payload_schema_revision = (string)($payload['schemaRevision'] ?? '');
        if ($payload_schema_revision === '') {
            $payload_schema_revision = (string)($scope['posted_schema_revision'] ?? $scope['schema_revision']);
            $payload['schemaRevision'] = $payload_schema_revision;
        }
        $validity = self::validate_daily_state($scope, $payload);
        if (is_wp_error($validity)) self::send_error($validity);

        $base_revision = isset($_POST['base_revision']) ? absint($_POST['base_revision']) : 0;
        $client_updated_at = max(0, (int)($payload['updatedAt'] ?? 0));
        $client_instance_id = sanitize_text_field((string)wp_unslash($_POST['client_instance_id'] ?? ''));
        if (strlen($client_instance_id) > 64) $client_instance_id = substr($client_instance_id, 0, 64);

        global $wpdb;
        $table = self::table();
        $wpdb->query('START TRANSACTION');
        $existing = self::find_scope_row($scope, true);
        $scope_rebased = false;
        if (!$existing) {
            $existing = self::find_recovery_scope_row($scope, true);
            $scope_rebased = (bool)$existing;
        }
        $now = current_time('mysql');
        $expires = wp_date('Y-m-d H:i:s', time() + self::TTL_SECONDS, wp_timezone());
        $revision = 1;
        $draft_id = 0;

        if ($existing) {
            $server_revision = (int)($existing['revision'] ?? 0);
            if ($base_revision !== $server_revision) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error([
                    'code' => 'draft_conflict',
                    'message' => 'Existe um rascunho mais recente noutro dispositivo.',
                    'draft' => self::row_payload($existing, self::payload_with_identity(self::decode_payload($existing['payload_json'] ?? ''), $scope)),
                    'files' => self::files_payload((int)$existing['id'], self::decode_payload($existing['payload_json'] ?? '')),
                ], 409);
            }
            $revision = $server_revision + 1;
            $draft_id = (int)$existing['id'];
            $updated = $wpdb->update($table, [
                'binding_id' => $scope['binding_id'],
                'client_id' => $scope['client_id'],
                'project_id' => $scope['project_id'],
                'route_id' => $scope['route_id'],
                'route_stop_id' => $scope['route_stop_id'],
                'location_id' => $scope['location_id'],
                'report_day' => $scope['report_day'],
                'schema_revision' => $payload_schema_revision,
                'revision' => $revision,
                'client_instance_id' => $client_instance_id,
                'client_updated_at' => $client_updated_at,
                'payload_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'expires_at' => $expires,
                'updated_at' => $now,
            ], ['id' => $draft_id, 'revision' => $base_revision], ['%d','%d','%d','%d','%d','%d','%s','%s','%d','%s','%d','%s','%s','%s'], ['%d','%d']);
            if ($updated === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error(['code' => 'draft_save_failed', 'message' => 'Não foi possível sincronizar o rascunho.'], 500);
            }
            if ((int)$updated !== 1) {
                $wpdb->query('ROLLBACK');
                $current = $wpdb->get_row($wpdb->prepare(
                    'SELECT * FROM ' . $table . ' WHERE id=%d AND user_id=%d LIMIT 1',
                    $draft_id, get_current_user_id()
                ), ARRAY_A);
                wp_send_json_error([
                    'code' => 'draft_conflict',
                    'message' => 'O rascunho foi alterado durante a sincronização. Recarrega a versão do servidor.',
                    'draft' => $current ? self::row_payload($current) : null,
                    'files' => $current ? self::files_payload($draft_id, self::decode_payload($current['payload_json'] ?? '')) : [],
                ], 409);
            }
        } else {
            $inserted = $wpdb->insert($table, [
                'user_id' => get_current_user_id(),
                'form_id' => $scope['form_id'],
                'binding_id' => $scope['binding_id'],
                'client_id' => $scope['client_id'],
                'project_id' => $scope['project_id'],
                'route_id' => $scope['route_id'],
                'route_stop_id' => $scope['route_stop_id'],
                'location_id' => $scope['location_id'],
                'report_day' => $scope['report_day'],
                'schema_revision' => $payload_schema_revision,
                'revision' => 1,
                'client_instance_id' => $client_instance_id,
                'client_updated_at' => $client_updated_at,
                'payload_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'expires_at' => $expires,
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%d','%d','%d','%d','%d','%d','%d','%d','%s','%s','%d','%s','%d','%s','%s','%s','%s']);
            if ($inserted === false) {
                $wpdb->query('ROLLBACK');
                $current = self::find_scope_row($scope, false);
                if ($current) {
                    wp_send_json_error([
                        'code' => 'draft_conflict',
                        'message' => 'Outro dispositivo criou primeiro este rascunho.',
                        'draft' => self::row_payload($current, self::payload_with_identity(self::decode_payload($current['payload_json'] ?? ''), $scope)),
                        'files' => self::files_payload((int)$current['id'], self::decode_payload($current['payload_json'] ?? '')),
                    ], 409);
                }
                wp_send_json_error(['code' => 'draft_save_failed', 'message' => 'Não foi possível criar o rascunho no servidor.'], 500);
            }
            $draft_id = (int)$wpdb->insert_id;
        }
        $wpdb->query('COMMIT');

        wp_send_json_success([
            'draft_id' => $draft_id,
            'revision' => $revision,
            'client_updated_at' => $client_updated_at,
            'server_updated_at' => $now,
            'expires_at' => $expires,
            'files' => self::files_payload($draft_id, $payload),
            'schema_changed' => !hash_equals((string)$scope['schema_revision'], (string)$payload_schema_revision),
            'current_schema_revision' => (string)$scope['schema_revision'],
            'scope_rebased' => $scope_rebased,
        ]);
    }

    public static function ajax_load(): void {
        $scope = self::request_scope(false);
        if (is_wp_error($scope)) self::send_error($scope);
        $row = self::find_scope_row($scope, false);
        $scope_rebased = false;
        if (!$row) {
            $row = self::find_recovery_scope_row($scope, false);
            $scope_rebased = (bool)$row;
        }
        if (!$row) wp_send_json_success(['has_draft' => false]);
        if (self::is_expired($row)) {
            self::delete_draft((int)$row['id']);
            wp_send_json_success(['has_draft' => false]);
        }
        $payload = self::decode_payload($row['payload_json'] ?? '');
        if (!$payload) {
            // Corrupt payloads cannot be restored, but valid draft rows are never
            // deleted merely because the active form/briefing evolved.
            wp_send_json_success(['has_draft' => false, 'retained' => 'invalid_payload']);
        }
        if (!self::payload_identity_matches_scope($payload, $scope, $scope_rebased)) {
            // Keep legacy/mismatched evidence intact, but never restore it into a
            // form whose canonical store identity cannot be proven.
            wp_send_json_success([
                'has_draft' => false,
                'retained' => 'identity',
                'draft_id' => (int)$row['id'],
                'message' => 'O rascunho foi mantido, mas a identidade da loja não coincide com a visita atual.',
            ]);
        }
        if ($scope_rebased) {
            // Route/stop may legitimately change after replanning, but only after
            // the stable store/campaign/day identity has been verified above.
            $payload['identity'] = self::payload_identity_from_scope($scope);
        }
        $schema_changed = (string)($row['schema_revision'] ?? '') !== (string)$scope['schema_revision'];
        $validity = self::validate_daily_state($scope, $payload);
        if (is_wp_error($validity)) {
            // Keep the server copy intact for diagnostics/manual recovery. A
            // transient daily-state conflict must never erase field evidence.
            wp_send_json_success([
                'has_draft' => false,
                'retained' => 'daily_state',
                'draft_id' => (int)$row['id'],
                'message' => $validity->get_error_message(),
            ]);
        }
        wp_send_json_success([
            'has_draft' => true,
            'schema_changed' => $schema_changed,
            'current_schema_revision' => (string)$scope['schema_revision'],
            'draft' => self::row_payload($row, $payload),
            'files' => self::files_payload((int)$row['id'], $payload),
            'scope_rebased' => $scope_rebased,
        ]);
    }

    public static function ajax_delete(): void {
        $scope = self::request_scope(false);
        if (is_wp_error($scope)) self::send_error($scope);
        $row = self::find_scope_row($scope, false);
        if ($row) self::delete_draft((int)$row['id']);
        wp_send_json_success(['deleted' => (bool)$row]);
    }

    public static function ajax_upload(): void {
        $scope = self::request_scope(true);
        if (is_wp_error($scope)) self::send_error($scope);
        $row = self::find_scope_row($scope, false);
        if (!$row) {
            wp_send_json_error(['code' => 'draft_missing', 'message' => 'Sincroniza primeiro as respostas do rascunho.'], 409);
        }
        $payload = self::decode_payload($row['payload_json'] ?? '');
        $input_name = sanitize_text_field((string)wp_unslash($_POST['input_name'] ?? ''));
        $ordinal = isset($_POST['ordinal']) ? absint($_POST['ordinal']) : 0;
        if ($input_name === '' || strlen($input_name) > 191) {
            wp_send_json_error(['code' => 'draft_file_input_invalid', 'message' => 'Campo de ficheiro inválido.'], 400);
        }
        $desired = self::desired_file_counts($payload);
        $desired_key = $input_name . '|' . $ordinal;
        if (!array_key_exists($desired_key, $desired)) {
            wp_send_json_error(['code' => 'draft_file_input_invalid', 'message' => 'O campo não pertence ao snapshot atual do rascunho.'], 400);
        }

        $draft_id = (int)$row['id'];
        $expected_count = min(300, max(0, (int)$desired[$desired_key]));
        $old_rows = self::input_file_rows($draft_id, $input_name, $ordinal);
        if ($expected_count === 0) {
            self::delete_file_rows($old_rows);
            wp_send_json_success([
                'draft_id' => $draft_id,
                'input_name' => $input_name,
                'ordinal' => $ordinal,
                'files' => [],
                'all_files' => self::files_payload($draft_id, $payload),
            ]);
        }

        $single_file = !empty($_POST['single_file']);
        $file_index = isset($_POST['file_index']) ? absint($_POST['file_index']) : 0;
        if ($single_file && $file_index >= $expected_count) {
            wp_send_json_error(['code' => 'draft_file_index_invalid', 'message' => 'A fotografia já não pertence ao snapshot atual do rascunho.'], 409);
        }
        $files = self::normalize_uploaded_files($_FILES['draft_files'] ?? null);
        if ($single_file) $files = array_slice($files, 0, 1);
        else $files = array_slice($files, 0, $expected_count);
        if (!$files) {
            wp_send_json_success([
                'draft_id' => $draft_id,
                'input_name' => $input_name,
                'ordinal' => $ordinal,
                'files' => [],
                'retained_existing' => true,
                'all_files' => self::files_payload($draft_id, $payload),
            ]);
        }
        if (!$single_file && count($files) !== $expected_count) {
            wp_send_json_error([
                'code' => 'draft_file_set_incomplete',
                'message' => 'O conjunto de ficheiros está incompleto. A cópia anterior foi mantida.',
            ], 422);
        }

        $dir = self::draft_dir($draft_id);
        if (!wp_mkdir_p($dir)) {
            wp_send_json_error(['code' => 'draft_file_storage_failed', 'message' => 'Não foi possível preparar o armazenamento temporário.'], 500);
        }
        self::protect_directory($dir);
        $allowed = get_allowed_mime_types();
        global $wpdb;
        $stored = [];
        $new_rows = [];

        foreach ($files as $batch_index => $file) {
            $target_index = $single_file ? $file_index : $batch_index;
            if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $size = (int)($file['size'] ?? 0);
            if ($size <= 0) continue;
            $tmp = (string)($file['tmp_name'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) continue;

            // There is deliberately no FieldFlow MB ceiling here. The draft
            // copy is constrained only by actual storage capacity and the web
            // server transport. Final report media uses the resumable channel.
            $free = @disk_free_space($dir);
            if (is_numeric($free) && (int)$free > 0 && $size > max(0, (int)$free - (8 * MB_IN_BYTES))) {
                wp_send_json_error([
                    'code' => 'draft_file_storage_capacity',
                    'message' => 'O servidor não tem espaço temporário suficiente para guardar esta fotografia do rascunho.',
                ], 507);
            }

            $original = sanitize_file_name((string)($file['name'] ?? 'ficheiro')) ?: 'ficheiro';
            $checked = wp_check_filetype_and_ext($tmp, $original, $allowed);
            $mime = sanitize_text_field((string)($checked['type'] ?? $file['type'] ?? ''));
            $ext = sanitize_key((string)($checked['ext'] ?? pathinfo($original, PATHINFO_EXTENSION)));
            if ($mime === '' || !in_array($mime, array_values($allowed), true)) continue;
            try { $token = bin2hex(random_bytes(24)); }
            catch (\Throwable $e) { $token = wp_generate_password(48, false, false); }
            $filename = $token . ($ext !== '' ? '.' . $ext : '');
            $path = trailingslashit($dir) . $filename;
            if (!@move_uploaded_file($tmp, $path)) continue;
            @chmod($path, 0600);
            $signature = hash('sha256', $original . '|' . $size . '|' . (int)($file['last_modified'] ?? 0));
            $ok = $wpdb->insert(self::files_table(), [
                'draft_id' => $draft_id,
                'user_id' => get_current_user_id(),
                'token' => $token,
                'input_name' => $input_name,
                'input_ordinal' => $ordinal,
                'file_index' => $target_index,
                'original_name' => $original,
                'mime_type' => $mime,
                'file_size' => $size,
                'file_signature' => $signature,
                'storage_path' => $path,
                'expires_at' => $row['expires_at'],
                'created_at' => current_time('mysql'),
            ], ['%d','%d','%s','%s','%d','%d','%s','%s','%d','%s','%s','%s','%s']);
            if ($ok === false) { @unlink($path); continue; }
            $new_id = (int)$wpdb->insert_id;
            $new_rows[] = ['id' => $new_id, 'storage_path' => $path, 'file_size' => $size, 'file_index' => $target_index];
            $stored[] = [
                'token' => $token,
                'inputName' => $input_name,
                'ordinal' => $ordinal,
                'fileIndex' => $target_index,
                'name' => $original,
                'type' => $mime,
                'size' => $size,
                'signature' => $signature,
            ];
        }

        if (count($stored) !== count($files)) {
            self::delete_file_rows($new_rows);
            wp_send_json_error([
                'code' => 'draft_file_upload_failed',
                'message' => 'Não foi possível sincronizar todos os ficheiros selecionados. A cópia local foi mantida.',
            ], 422);
        }

        if ($single_file) {
            // Replace only this index after its new copy is durable. Previous
            // indices remain available if the network drops halfway through a set.
            $replace = array_values(array_filter($old_rows, static function($old) use ($file_index): bool {
                return (int)($old['file_index'] ?? -1) === $file_index;
            }));
            self::delete_file_rows($replace);
        } else {
            self::delete_file_rows($old_rows);
        }
        self::prune_file_rows_to_payload($draft_id, $payload);
        wp_send_json_success([
            'draft_id' => $draft_id,
            'input_name' => $input_name,
            'ordinal' => $ordinal,
            'files' => $stored,
            'incremental' => $single_file,
            'all_files' => self::files_payload($draft_id, $payload),
        ]);
    }

    public static function ajax_download(): void {
        if (!is_user_logged_in()) {
            status_header(401);
            exit;
        }
        $nonce = sanitize_text_field((string)wp_unslash($_GET['nonce'] ?? ''));
        if (!$nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            status_header(403);
            exit;
        }
        $token = sanitize_text_field((string)wp_unslash($_GET['token'] ?? ''));
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT f.* FROM ' . self::files_table() . ' f INNER JOIN ' . self::table() . ' d ON d.id=f.draft_id WHERE f.token=%s AND f.user_id=%d AND d.user_id=%d LIMIT 1',
            $token,
            get_current_user_id(),
            get_current_user_id()
        ), ARRAY_A);
        if (!$row || empty($row['storage_path']) || !is_file($row['storage_path']) || self::is_expired($row)) {
            status_header(404); exit;
        }
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . ($row['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: ' . (string)filesize($row['storage_path']));
        header('Content-Disposition: attachment; filename="' . rawurlencode((string)$row['original_name']) . '"');
        readfile($row['storage_path']);
        exit;
    }

    public static function activate_for_submission(array $scope, string $schema_revision): int {
        self::$active_draft = null;
        $draft_id = isset($_POST['routespro_draft_id']) ? absint($_POST['routespro_draft_id']) : 0;
        if ($draft_id <= 0 || !is_user_logged_in()) return 0;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id=%d AND user_id=%d LIMIT 1',
            $draft_id,
            get_current_user_id()
        ), ARRAY_A);
        if (!$row || self::is_expired($row)) return 0;
        $checks = [
            'form_id' => (int)($scope['form_id'] ?? 0),
            'binding_id' => (int)($scope['binding_id'] ?? 0),
            'client_id' => (int)($scope['client_id'] ?? 0),
            'project_id' => (int)($scope['project_id'] ?? 0),
            'route_id' => (int)($scope['route_id'] ?? 0),
            'route_stop_id' => (int)($scope['route_stop_id'] ?? 0),
            'location_id' => (int)($scope['location_id'] ?? 0),
        ];
        foreach ($checks as $field => $value) if ((int)($row[$field] ?? 0) !== $value) return 0;
        if ((string)($row['report_day'] ?? '') !== (string)($scope['report_day'] ?? '')) return 0;
        if ((string)($row['schema_revision'] ?? '') !== $schema_revision) return 0;
        $row['_payload'] = self::decode_payload($row['payload_json'] ?? '');
        self::$active_draft = $row;
        return $draft_id;
    }

    public static function publish_simple_files(string $field_key, bool $image_only = false): array {
        $names = [$field_key, $field_key . '[]'];
        return self::publish_files($names, $image_only);
    }

    public static function publish_categorized_files(string $field_key, string $category_key): array {
        $names = [
            $field_key . '[' . $category_key . ']',
            $field_key . '[' . $category_key . '][]',
        ];
        return self::publish_files($names, true);
    }

    public static function delete_active_draft(): void {
        if (!self::$active_draft || empty(self::$active_draft['id'])) return;
        self::delete_draft((int)self::$active_draft['id']);
        self::$active_draft = null;
    }

    public static function schedule_active_draft_deletion(int $user_id = 0): bool {
        if (!self::$active_draft || empty(self::$active_draft['id'])) return true;
        $draft_id = (int)self::$active_draft['id'];
        $owner_id = (int)(self::$active_draft['user_id'] ?? $user_id);
        self::$active_draft = null;
        $args = [$draft_id, $owner_id];
        if (wp_next_scheduled('fieldflow_delete_submitted_draft', $args)) return true;
        $scheduled = wp_schedule_single_event(time() + 5, 'fieldflow_delete_submitted_draft', $args);
        return $scheduled !== false && !is_wp_error($scheduled);
    }

    public static function delete_submitted_draft(int $draft_id, int $user_id = 0): void {
        $draft_id = absint($draft_id);
        if ($draft_id <= 0) return;
        global $wpdb;
        if ($user_id > 0) {
            $owner_id = (int)$wpdb->get_var($wpdb->prepare('SELECT user_id FROM ' . self::table() . ' WHERE id=%d LIMIT 1', $draft_id));
            if ($owner_id > 0 && $owner_id !== absint($user_id)) return;
        }
        self::delete_draft($draft_id);
    }

    public static function cleanup_expired(): void {
        global $wpdb;
        $now = current_time('mysql');
        $cutoff = wp_date('Y-m-d H:i:s', time() - self::TTL_SECONDS, wp_timezone());
        for ($batch = 0; $batch < 5; $batch++) {
            $ids = $wpdb->get_col($wpdb->prepare(
                'SELECT id FROM ' . self::table() . ' WHERE updated_at < %s ORDER BY id ASC LIMIT 500',
                $cutoff
            )) ?: [];
            foreach ($ids as $id) self::delete_draft((int)$id);
            if (count($ids) < 500) break;
        }

        for ($batch = 0; $batch < 5; $batch++) {
            $rows = $wpdb->get_results($wpdb->prepare(
                'SELECT f.id,f.draft_id,f.storage_path,f.file_size FROM ' . self::files_table() . ' f LEFT JOIN ' . self::table() . ' d ON d.id=f.draft_id WHERE d.id IS NULL OR d.updated_at < %s ORDER BY f.id ASC LIMIT 500',
                $cutoff
            ), ARRAY_A) ?: [];
            if (!$rows) break;
            $draft_ids = [];
            foreach ($rows as $row) $draft_ids[(int)($row['draft_id'] ?? 0)] = true;
            self::delete_file_rows($rows);
            foreach (array_keys($draft_ids) as $draft_id) {
                if ($draft_id <= 0) continue;
                $dir = self::draft_dir($draft_id);
                if (is_dir($dir)) @rmdir($dir);
            }
            if (count($rows) < 500) break;
        }
    }

    private static function request_scope(bool $require_schema) {
        if (!is_user_logged_in()) return new \WP_Error('draft_login_required', 'A sessão terminou.', ['status' => 401]);
        $nonce = sanitize_text_field((string)wp_unslash($_POST['nonce'] ?? ''));
        if (!$nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) return new \WP_Error('draft_nonce_invalid', 'A página ficou desatualizada.', ['status' => 403]);
        $form_id = absint($_POST['form_id'] ?? 0);
        $binding_id = absint($_POST['binding_id'] ?? 0);
        $form = Forms::get_form($form_id);
        if (!$form || ($form['status'] ?? '') !== 'active') return new \WP_Error('draft_form_invalid', 'O formulário já não está ativo.', ['status' => 404]);
        $normalized = SubmissionContext::normalize_for_submission([
            'client_id' => absint($_POST['client_id'] ?? 0),
            'project_id' => absint($_POST['project_id'] ?? 0),
            'route_id' => absint($_POST['route_id'] ?? 0),
            'route_stop_id' => absint($_POST['route_stop_id'] ?? 0),
            'location_id' => absint($_POST['location_id'] ?? 0),
            'context_date' => sanitize_text_field((string)wp_unslash($_POST['context_date'] ?? '')),
        ], $form_id, $binding_id, get_current_user_id());
        if (is_wp_error($normalized)) return $normalized;
        $context = (array)($normalized['context'] ?? []);
        $report_day = DailySubmissionService::normalise_report_day(
            $_POST['report_day'] ?? ($normalized['route_date'] ?? $context['context_date'] ?? current_time('Y-m-d'))
        );
        $schema_revision = self::schema_revision($form, $form_id, $context, $report_day);
        $posted_revision = sanitize_text_field((string)wp_unslash($_POST['schema_revision'] ?? ''));
        if ($require_schema && $posted_revision === '') {
            return new \WP_Error('draft_schema_missing', 'Não foi possível identificar a versão do formulário deste rascunho.', ['status' => 400]);
        }
        // Drafts are recovery artefacts, not the final validation boundary.
        // A form that stayed open while a briefing/schema changed must still be
        // backed up remotely. Final submission performs compatibility checks.
        $schema_changed = $posted_revision !== '' && !hash_equals($schema_revision, $posted_revision);
        return [
            'form_id' => $form_id,
            'binding_id' => (int)($normalized['binding']['id'] ?? $binding_id),
            'client_id' => (int)($context['client_id'] ?? 0),
            'project_id' => (int)($context['project_id'] ?? 0),
            'route_id' => (int)($context['route_id'] ?? 0),
            'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
            'location_id' => (int)($context['location_id'] ?? 0),
            'report_day' => $report_day,
            'schema_revision' => $schema_revision,
            'posted_schema_revision' => $posted_revision,
            'schema_changed' => $schema_changed,
        ];
    }

    private static function schema_revision(array $form, int $form_id, array $context, string $report_day): string {
        $schema = self::schema_for_scope($form, $form_id, $context, $report_day);
        return substr(hash('sha256', (string)wp_json_encode($schema)), 0, 20);
    }

    private static function schema_for_scope(array $form, int $form_id, array $context, string $report_day): array {
        $schema = Forms::decode_schema((string)($form['schema_json'] ?? ''));
        $context['form_id'] = $form_id;
        $context['stop_id'] = (int)($context['route_stop_id'] ?? $context['stop_id'] ?? 0);
        $context['date'] = $report_day;
        $context['route_date'] = $report_day;
        if (class_exists('\RoutesPro\Forms\ContextQuestions')) {
            $schema = ContextQuestions::inject_into_schema($schema, ContextQuestions::regular_for_context($context));
        }
        if (class_exists('\RoutesPro\Forms\BriefingFormBridge')) {
            $schema = BriefingFormBridge::expand_blocks($schema, $context);
        }
        if (class_exists('\RoutesPro\Forms\EvidenceFlow')) {
            $schema = EvidenceFlow::expand_schema($schema);
        }
        if (class_exists('\RoutesPro\Forms\ConditionalFlow')) {
            $schema = ConditionalFlow::normalise_schema($schema);
        }
        if (class_exists('\RoutesPro\Forms\ProductCardex')) {
            $schema = ProductCardex::synchronise_order_questions_with_fronts($schema, $context);
        }
        return $schema;
    }

    private static function recovery_schema_compatibility(array $payload, array $schema): array {
        $current = [];
        foreach ((array)($schema['questions'] ?? []) as $question) {
            if (!is_array($question)) continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($key !== '') $current[$key] = true;
        }
        $posted = [];
        $capture = static function(string $name) use (&$posted): void {
            $name = trim($name);
            if ($name === '' || strpos($name, 'routespro_') === 0) return;
            $root = preg_replace('/\[.*$/', '', $name);
            $root = preg_replace('/__(?:cardex_present|cardex_id)$/', '', (string)$root);
            $root = sanitize_key((string)$root);
            if ($root !== '') $posted[$root] = true;
        };
        foreach ((array)($payload['controls'] ?? []) as $control) {
            if (!is_array($control)) continue;
            $type = sanitize_key((string)($control['type'] ?? ''));
            if (in_array($type, ['checkbox','radio'], true) && empty($control['checked'])) continue;
            $value = $control['value'] ?? '';
            $has_value = is_array($value)
                ? (bool)array_filter($value, static fn($part): bool => trim((string)$part) !== '')
                : trim((string)$value) !== '';
            if ($has_value) $capture((string)($control['name'] ?? ''));
        }
        foreach ((array)($payload['files'] ?? []) as $file) {
            if (!is_array($file) || max(0, (int)($file['count'] ?? 0)) <= 0) continue;
            $capture((string)($file['name'] ?? ''));
        }
        $unknown = array_values(array_diff(array_keys($posted), array_keys($current)));
        sort($unknown, SORT_STRING);
        return [
            'compatible' => empty($unknown),
            'posted_question_keys' => array_values(array_keys($posted)),
            'unknown_question_keys' => $unknown,
        ];
    }


    private static function validate_daily_state(array $scope, array $payload) {
        if ($scope['location_id'] <= 0) return true;
        $existing = DailySubmissionService::find_existing($scope['form_id'], $scope['location_id'], $scope['report_day']);
        $payload_existing = (int)($payload['existingSubmissionId'] ?? 0);
        if (!$existing) {
            if ($payload_existing > 0) return new \WP_Error('draft_daily_state_invalid', 'O reporte associado ao rascunho já não existe.', ['status' => 409]);
            return true;
        }
        if (!DailySubmissionService::can_edit($existing, get_current_user_id())) {
            return new \WP_Error('draft_daily_edit_forbidden', 'Não tens permissão para editar este reporte.', ['status' => 403]);
        }
        $context_conflict = DailySubmissionService::edit_context_conflict($existing, $scope);
        if ($context_conflict) {
            return new \WP_Error('draft_daily_context_conflict', 'Este reporte pertence a outra rota ou paragem e não pode receber este rascunho.', [
                'status' => 409,
                'conflict' => $context_conflict,
                'original_context' => DailySubmissionService::original_context($existing),
            ]);
        }
        if (empty($payload['editMode']) || $payload_existing !== (int)$existing['id']) {
            return new \WP_Error('draft_daily_state_invalid', 'Ativa a edição do reporte antes de sincronizar alterações.', ['status' => 409]);
        }
        return true;
    }

    private static function find_scope_row(array $scope, bool $for_update): ?array {
        global $wpdb;
        $sql = 'SELECT * FROM ' . self::table() . ' WHERE user_id=%d AND form_id=%d AND binding_id=%d AND client_id=%d AND project_id=%d AND route_id=%d AND route_stop_id=%d AND location_id=%d AND report_day=%s LIMIT 1';
        if ($for_update) $sql .= ' FOR UPDATE';
        $row = $wpdb->get_row($wpdb->prepare($sql,
            get_current_user_id(), $scope['form_id'], $scope['binding_id'], $scope['client_id'], $scope['project_id'],
            $scope['route_id'], $scope['route_stop_id'], $scope['location_id'], $scope['report_day']
        ), ARRAY_A);
        return $row ?: null;
    }


    /**
     * Recovery lookup for app/WebView resume. A route may be reconstructed with
     * a different route/stop id after replanning or native process recreation,
     * while the operational report still belongs to the same user, form,
     * binding, project, store and day. Recover only when there is exactly one
     * candidate, so we never guess between two genuine visits.
     */
    private static function find_recovery_scope_row(array $scope, bool $for_update): ?array {
        global $wpdb;
        $sql = 'SELECT * FROM ' . self::table() . ' WHERE user_id=%d AND form_id=%d AND binding_id=%d AND client_id=%d AND project_id=%d AND location_id=%d AND report_day=%s ORDER BY updated_at DESC,id DESC LIMIT 2';
        if ($for_update) $sql .= ' FOR UPDATE';
        $rows = $wpdb->get_results($wpdb->prepare($sql,
            get_current_user_id(), $scope['form_id'], $scope['binding_id'], $scope['client_id'], $scope['project_id'],
            $scope['location_id'], $scope['report_day']
        ), ARRAY_A) ?: [];
        if (count($rows) !== 1) return null;
        $row = $rows[0];
        if (self::is_expired($row)) return null;
        return $row;
    }

    private static function row_payload(array $row, ?array $payload = null): array {
        return [
            'id' => (int)($row['id'] ?? 0),
            'revision' => (int)($row['revision'] ?? 0),
            'clientUpdatedAt' => (int)($row['client_updated_at'] ?? 0),
            'serverUpdatedAt' => (string)($row['updated_at'] ?? ''),
            'expiresAt' => (string)($row['expires_at'] ?? ''),
            'payload' => $payload ?? self::decode_payload($row['payload_json'] ?? ''),
        ];
    }

    private static function payload_identity_from_scope(array $scope): array {
        return [
            'formId' => absint($scope['form_id'] ?? 0),
            'bindingId' => absint($scope['binding_id'] ?? 0),
            'clientId' => absint($scope['client_id'] ?? 0),
            'projectId' => absint($scope['project_id'] ?? 0),
            'routeId' => absint($scope['route_id'] ?? 0),
            'routeStopId' => absint($scope['route_stop_id'] ?? 0),
            'locationId' => absint($scope['location_id'] ?? 0),
            'reportDay' => DailySubmissionService::normalise_report_day((string)($scope['report_day'] ?? '')),
        ];
    }

    private static function payload_with_identity(array $payload, array $scope): array {
        if (!$payload) return [];
        $payload['identity'] = self::payload_identity_from_scope($scope);
        return $payload;
    }

    private static function payload_identity_matches_scope(array $payload, array $scope, bool $allow_route_rebase = false): bool {
        $identity = is_array($payload['identity'] ?? null) ? $payload['identity'] : [];
        if (!$identity) return false;
        $expected = self::payload_identity_from_scope($scope);
        foreach (['formId','bindingId','clientId','projectId','locationId'] as $key) {
            if (absint($identity[$key] ?? 0) !== absint($expected[$key] ?? 0)) return false;
        }
        $identity_day = DailySubmissionService::normalise_report_day((string)($identity['reportDay'] ?? ''));
        if ($identity_day !== (string)$expected['reportDay']) return false;
        if (!$allow_route_rebase) {
            foreach (['routeId','routeStopId'] as $key) {
                if (absint($identity[$key] ?? 0) !== absint($expected[$key] ?? 0)) return false;
            }
        }
        return absint($expected['formId']) > 0 && absint($expected['locationId']) > 0;
    }

    private static function decode_payload($raw): array {
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded) || (int)($decoded['version'] ?? 0) !== 2) return [];
        return self::sanitize_payload($decoded);
    }

    private static function sanitize_payload(array $payload): array {
        $clean = [
            'version' => 2,
            'createdAt' => max(0, (int)($payload['createdAt'] ?? 0)),
            'updatedAt' => max(0, (int)($payload['updatedAt'] ?? 0)),
            'expiresAt' => max(0, (int)($payload['expiresAt'] ?? 0)),
            'schemaRevision' => substr(sanitize_text_field((string)($payload['schemaRevision'] ?? '')), 0, 64),
            'formId' => absint($payload['formId'] ?? 0),
            'existingSubmissionId' => absint($payload['existingSubmissionId'] ?? 0),
            'editMode' => !empty($payload['editMode']),
            'step' => min(500, max(1, absint($payload['step'] ?? 1))),
            'controls' => [],
            'productRows' => [],
            'dynamicProductRows' => [],
            'categories' => [],
            'files' => [],
            'identity' => [],
        ];
        if (is_array($payload['identity'] ?? null)) {
            $identity = (array)$payload['identity'];
            $clean['identity'] = [
                'formId' => absint($identity['formId'] ?? 0),
                'bindingId' => absint($identity['bindingId'] ?? 0),
                'clientId' => absint($identity['clientId'] ?? 0),
                'projectId' => absint($identity['projectId'] ?? 0),
                'routeId' => absint($identity['routeId'] ?? 0),
                'routeStopId' => absint($identity['routeStopId'] ?? 0),
                'locationId' => absint($identity['locationId'] ?? 0),
                'reportDay' => substr(sanitize_text_field((string)($identity['reportDay'] ?? '')), 0, 10),
            ];
        }

        foreach (array_slice((array)($payload['controls'] ?? []), 0, 1500) as $entry) {
            if (!is_array($entry)) continue;
            $name = self::sanitize_control_name((string)($entry['name'] ?? ''));
            if ($name === '') continue;
            $type = substr(sanitize_key((string)($entry['type'] ?? 'text')), 0, 32);
            $item = [
                'name' => $name,
                'ordinal' => min(5000, max(0, (int)($entry['ordinal'] ?? 0))),
                'type' => $type,
            ];
            if ($type === 'checkbox' || $type === 'radio') {
                $item['checked'] = !empty($entry['checked']);
                $item['value'] = self::sanitize_control_value($entry['value'] ?? '');
            } elseif ($type === 'select-multiple') {
                $values = [];
                foreach (array_slice((array)($entry['value'] ?? []), 0, 500) as $value) {
                    $values[] = self::sanitize_control_value($value);
                }
                $item['value'] = $values;
            } else {
                $item['value'] = self::sanitize_control_value($entry['value'] ?? '');
            }
            $clean['controls'][] = $item;
        }

        foreach (array_slice((array)($payload['productRows'] ?? []), 0, 300) as $matrix) {
            if (!is_array($matrix)) continue;
            $matrix_key = self::sanitize_matrix_key((string)($matrix['matrix'] ?? ''));
            if ($matrix_key === '') continue;
            $tokens = [];
            foreach (array_slice((array)($matrix['tokens'] ?? []), 0, 1500) as $token) {
                $token = self::sanitize_control_name((string)$token);
                if ($token !== '') $tokens[] = $token;
            }
            $clean['productRows'][] = ['matrix' => $matrix_key, 'tokens' => $tokens];
        }

        $allowed_html = [
            'div' => [
                'class' => true,
                'data-product-row' => true,
                'data-product-item-id' => true,
                'data-rp-draft-dynamic' => true,
            ],
            'label' => ['class' => true],
            'small' => ['class' => true],
            'input' => [
                'type' => true,
                'name' => true,
                'value' => true,
                'placeholder' => true,
                'step' => true,
                'min' => true,
                'max' => true,
                'readonly' => true,
                'class' => true,
                'data-fronts-input' => true,
            ],
            'button' => [
                'type' => true,
                'class' => true,
                'aria-label' => true,
            ],
            'span' => ['class' => true],
        ];
        foreach (array_slice((array)($payload['dynamicProductRows'] ?? []), 0, 300) as $row) {
            if (!is_array($row)) continue;
            $matrix_key = self::sanitize_matrix_key((string)($row['matrix'] ?? ''));
            $html = substr((string)($row['html'] ?? ''), 0, 30000);
            if ($matrix_key === '' || $html === '') continue;
            $html = wp_kses($html, $allowed_html);
            if ($html === '' || stripos($html, 'data-product-row') === false || stripos($html, 'data-rp-draft-dynamic') === false) continue;
            preg_match_all('/<input\b[^>]*\bname=(?:"([^"]+)"|\'([^\']+)\')/i', $html, $matches);
            $input_names = array_merge((array)($matches[1] ?? []), (array)($matches[2] ?? []));
            $valid_names = true;
            foreach ($input_names as $input_name) {
                if ($input_name === '') continue;
                $input_name = self::sanitize_control_name(html_entity_decode((string)$input_name, ENT_QUOTES, 'UTF-8'));
                if ($input_name === '' || strpos($input_name, $matrix_key . '[') !== 0) {
                    $valid_names = false;
                    break;
                }
            }
            if (!$valid_names || !$input_names) continue;
            $clean['dynamicProductRows'][] = ['matrix' => $matrix_key, 'html' => $html];
        }

        foreach (array_slice((array)($payload['categories'] ?? []), 0, 1000) as $open) {
            $clean['categories'][] = !empty($open) ? 1 : 0;
        }

        foreach (array_slice((array)($payload['files'] ?? []), 0, 300) as $entry) {
            if (!is_array($entry)) continue;
            $name = self::sanitize_control_name((string)($entry['name'] ?? ''));
            if ($name === '') continue;
            $count = min(300, max(0, (int)($entry['count'] ?? 0)));
            $names = [];
            foreach (array_slice((array)($entry['names'] ?? []), 0, $count) as $filename) {
                $names[] = substr(sanitize_file_name((string)$filename), 0, 255);
            }
            $clean['files'][] = [
                'name' => $name,
                'ordinal' => min(5000, max(0, (int)($entry['ordinal'] ?? 0))),
                'count' => $count,
                'names' => $names,
            ];
        }

        return $clean;
    }

    private static function sanitize_control_name(string $name): string {
        $name = substr($name, 0, 191);
        return (string)preg_replace('/[^A-Za-z0-9_\-\[\]\.]/', '', $name);
    }

    private static function sanitize_matrix_key(string $key): string {
        $key = substr($key, 0, 191);
        return (string)preg_replace('/[^A-Za-z0-9_\-\.]/', '', $key);
    }

    private static function sanitize_control_value($value): string {
        if (is_array($value) || is_object($value)) return '';
        $value = wp_check_invalid_utf8((string)$value, true);
        return substr($value, 0, 100000);
    }

    private static function desired_file_counts(array $payload): array {
        $desired = [];
        foreach ((array)($payload['files'] ?? []) as $entry) {
            if (!is_array($entry) || empty($entry['name'])) continue;
            $key = (string)$entry['name'] . '|' . (int)($entry['ordinal'] ?? 0);
            $desired[$key] = max(0, (int)($entry['count'] ?? 0));
        }
        return $desired;
    }

    private static function files_payload(int $draft_id, array $payload): array {
        if ($draft_id <= 0) return [];
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT token,input_name,input_ordinal,file_index,original_name,mime_type,file_size,file_signature,created_at FROM ' . self::files_table() . ' WHERE draft_id=%d ORDER BY input_name,input_ordinal,file_index,id',
            $draft_id
        ), ARRAY_A) ?: [];
        $desired = self::desired_file_counts($payload);
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = (string)$row['input_name'] . '|' . (int)$row['input_ordinal'];
            $limit = (int)($desired[$key] ?? 0);
            $seen[$key] = (int)($seen[$key] ?? 0);
            if ($limit <= 0 || $seen[$key] >= $limit) continue;
            $seen[$key]++;
            $out[] = [
                'token' => (string)$row['token'],
                'inputName' => (string)$row['input_name'],
                'ordinal' => (int)$row['input_ordinal'],
                'fileIndex' => (int)$row['file_index'],
                'name' => (string)$row['original_name'],
                'type' => (string)$row['mime_type'],
                'size' => (int)$row['file_size'],
                'signature' => (string)$row['file_signature'],
                'createdAt' => (string)$row['created_at'],
            ];
        }
        return $out;
    }

    private static function normalize_uploaded_files($root): array {
        if (!is_array($root) || !isset($root['name'])) return [];
        if (!is_array($root['name'])) return [$root];
        $out = [];
        foreach ($root['name'] as $i => $name) {
            if ((string)$name === '') continue;
            $out[] = [
                'name' => $name,
                'type' => $root['type'][$i] ?? '',
                'tmp_name' => $root['tmp_name'][$i] ?? '',
                'error' => $root['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $root['size'][$i] ?? 0,
                'last_modified' => isset($_POST['last_modified'][$i]) ? absint($_POST['last_modified'][$i]) : 0,
            ];
        }
        return $out;
    }

    private static function publish_files(array $input_names, bool $image_only): array {
        $strict_recovery = class_exists('\RoutesPro\Forms\Forms') && Forms::manual_recovery_active();
        if (!self::$active_draft || empty(self::$active_draft['id'])) {
            if ($strict_recovery) throw new \RuntimeException('O rascunho não ficou ativo para publicar os anexos.');
            return [];
        }
        $payload = (array)(self::$active_draft['_payload'] ?? []);
        $desired = self::desired_file_counts($payload);
        $wanted = [];
        $wanted_names = [];
        foreach ($input_names as $name) {
            foreach ($desired as $key => $count) {
                if (strpos((string)$key, $name . '|') !== 0 || (int)$count <= 0) continue;
                $wanted[(string)$key] = (int)$count;
                $wanted_names[$name] = $name;
            }
        }
        if (!$wanted || !$wanted_names) return [];
        $expected_total = array_sum($wanted);
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($wanted_names), '%s'));
        $args = array_merge([(int)self::$active_draft['id']], array_values($wanted_names));
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::files_table() . " WHERE draft_id=%d AND input_name IN ({$placeholders}) ORDER BY input_name,input_ordinal,file_index,id",
            ...$args
        ), ARRAY_A) ?: [];
        if (!$rows) {
            if ($strict_recovery && $expected_total > 0) throw new \RuntimeException('Os anexos sincronizados do rascunho não foram encontrados.');
            return [];
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $mimes = $image_only ? array_filter(get_allowed_mime_types(), static function($mime): bool { return strpos((string)$mime, 'image/') === 0; }) : null;
        $seen = [];
        $published = [];
        foreach ($rows as $row) {
            $name = (string)$row['input_name'];
            $key = $name . '|' . (int)($row['input_ordinal'] ?? 0);
            $limit = (int)($wanted[$key] ?? 0);
            $seen[$key] = (int)($seen[$key] ?? 0);
            if ($limit <= 0 || $seen[$key] >= $limit) continue;
            $seen[$key]++;
            $source = (string)($row['storage_path'] ?? '');
            if ($source === '' || !is_file($source)) continue;
            $tmp = wp_tempnam((string)$row['original_name']);
            if (!$tmp || !@copy($source, $tmp)) continue;
            $uploaded = wp_handle_sideload([
                'name' => (string)$row['original_name'],
                'type' => (string)$row['mime_type'],
                'tmp_name' => $tmp,
                'error' => UPLOAD_ERR_OK,
                'size' => (int)$row['file_size'],
            ], ['test_form' => false, 'mimes' => $mimes]);
            if (!empty($uploaded['url'])) {
                $published[] = [
                    'url' => esc_url_raw((string)$uploaded['url']),
                    'file' => (string)($uploaded['file'] ?? ''),
                    'name' => sanitize_file_name((string)$row['original_name']),
                    'type' => sanitize_text_field((string)($uploaded['type'] ?? $row['mime_type'])),
                ];
            } elseif (is_file($tmp)) {
                @unlink($tmp);
            }
        }
        if ($strict_recovery && count($published) < $expected_total) {
            foreach ($published as $published_file) {
                $path = (string)($published_file['file'] ?? '');
                if ($path !== '' && is_file($path)) @unlink($path);
            }
            throw new \RuntimeException('Nem todos os anexos do rascunho puderam ser publicados. A recuperação foi interrompida para evitar perda de informação.');
        }
        return $published;
    }

    private static function prune_file_rows_to_payload(int $draft_id, array $payload): void {
        if ($draft_id <= 0) return;
        global $wpdb;
        $desired = self::desired_file_counts($payload);
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id,input_name,input_ordinal,file_index,storage_path FROM ' . self::files_table() . ' WHERE draft_id=%d ORDER BY id',
            $draft_id
        ), ARRAY_A) ?: [];
        $remove = [];
        foreach ($rows as $file_row) {
            $key = (string)($file_row['input_name'] ?? '') . '|' . (int)($file_row['input_ordinal'] ?? 0);
            $limit = max(0, (int)($desired[$key] ?? 0));
            if ($limit <= 0 || (int)($file_row['file_index'] ?? 0) >= $limit) $remove[] = $file_row;
        }
        self::delete_file_rows($remove);
    }

    private static function input_file_rows(int $draft_id, string $input_name, int $ordinal): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT id,storage_path,file_size,file_index FROM ' . self::files_table() . ' WHERE draft_id=%d AND input_name=%s AND input_ordinal=%d ORDER BY file_index,id',
            $draft_id, $input_name, $ordinal
        ), ARRAY_A) ?: [];
    }

    private static function delete_file_rows(array $rows): void {
        if (!$rows) return;
        global $wpdb;
        foreach ($rows as $row) {
            if (!empty($row['storage_path']) && is_file($row['storage_path'])) @unlink($row['storage_path']);
            if (!empty($row['id'])) $wpdb->delete(self::files_table(), ['id' => (int)$row['id']], ['%d']);
        }
    }

    private static function delete_draft(int $draft_id): void {
        if ($draft_id <= 0) return;
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT storage_path FROM ' . self::files_table() . ' WHERE draft_id=%d', $draft_id), ARRAY_A) ?: [];
        foreach ($rows as $row) if (!empty($row['storage_path']) && is_file($row['storage_path'])) @unlink($row['storage_path']);
        $wpdb->delete(self::files_table(), ['draft_id' => $draft_id], ['%d']);
        $wpdb->delete(self::table(), ['id' => $draft_id], ['%d']);
        $dir = self::draft_dir($draft_id);
        if (is_dir($dir)) @rmdir($dir);
    }

    private static function draft_dir(int $draft_id): string {
        $uploads = wp_upload_dir();
        return trailingslashit((string)$uploads['basedir']) . 'fieldflow-private-drafts/' . $draft_id;
    }

    private static function protect_directory(string $dir): void {
        $root = dirname($dir);
        if (!is_dir($root)) wp_mkdir_p($root);
        $rules = "Options -Indexes\n<FilesMatch \".*\">\nRequire all denied\nDeny from all\n</FilesMatch>\n";
        if (!file_exists(trailingslashit($root) . '.htaccess')) @file_put_contents(trailingslashit($root) . '.htaccess', $rules);
        if (!file_exists(trailingslashit($root) . 'index.php')) @file_put_contents(trailingslashit($root) . 'index.php', "<?php\nhttp_response_code(404);\nexit;\n");
    }

    private static function draft_file_total(int $draft_id): int {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare('SELECT COALESCE(SUM(file_size),0) FROM ' . self::files_table() . ' WHERE draft_id=%d', $draft_id));
    }

    private static function is_expired(array $row): bool {
        $updated = (string)($row['updated_at'] ?? $row['created_at'] ?? '');
        if ($updated !== '') {
            $cutoff = wp_date('Y-m-d H:i:s', time() - self::TTL_SECONDS, wp_timezone());
            return $updated < $cutoff;
        }
        $expires = (string)($row['expires_at'] ?? '');
        return $expires !== '' && $expires < current_time('mysql');
    }

    private static function send_error(\WP_Error $error): void {
        $data = $error->get_error_data();
        $status = is_array($data) && !empty($data['status']) ? (int)$data['status'] : 400;
        wp_send_json_error([
            'code' => $error->get_error_code(),
            'message' => $error->get_error_message(),
        ], $status);
    }
}
