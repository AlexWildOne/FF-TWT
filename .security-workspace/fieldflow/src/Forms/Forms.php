<?php
namespace RoutesPro\Forms;
if (!defined('ABSPATH')) exit;
class Forms {
    private static string $offlineMutationId = '';
    private static string $submissionStage = 'idle';
    private static bool $submissionCommitted = false;
    private static array $committedResponse = [];
    private static array $submissionWarnings = [];
    private static array $uploadErrors = [];
    private static array $uploadedPaths = [];
    private static float $submissionStartedAt = 0.0;
    private static bool $mediaTelemetryRecorded = false;
    private static bool $internalSubmissionMode = false;
    private static array $internalSubmissionResult = [];
    private static array $manualRecoveryContext = [];
    private static array $resumableMediaRefs = [];

    const ACTION_SUBMIT = 'routespro_submit_form';
    const NONCE_FIELD = 'routespro_form_nonce';
    const NONCE_ACTION = 'routespro_form_submit';
    public static function boot() {
        if (class_exists('\\RoutesPro\\Forms\\DraftService')) DraftService::boot();
        if (class_exists('\\RoutesPro\\Forms\\SubmissionSideEffectDispatcher')) SubmissionSideEffectDispatcher::registerHooks();
        add_shortcode('fieldflow_form', [self::class, 'shortcode_form']);
        add_shortcode('fieldflow_route_form', [self::class, 'shortcode_route_form']);
        // aliases legados
        add_shortcode('routespro_form', [self::class, 'shortcode_form']);
        add_shortcode('routespro_route_form', [self::class, 'shortcode_route_form']);
        add_action('admin_post_' . self::ACTION_SUBMIT, [self::class, 'handle_submit_safely']);
        add_action('admin_post_nopriv_' . self::ACTION_SUBMIT, [self::class, 'handle_submit_safely']);
        add_action('wp_ajax_' . self::ACTION_SUBMIT, [self::class, 'handle_submit_safely']);
        add_action('wp_ajax_nopriv_' . self::ACTION_SUBMIT, [self::class, 'handle_submit_safely']);
        add_action('wp_ajax_routespro_context_form', [self::class, 'ajax_context_form']);
        add_action('wp_ajax_nopriv_routespro_context_form', [self::class, 'ajax_context_form']);
        add_action('wp_enqueue_scripts', [FormRenderer::class, 'enqueue_assets']);
        add_action('fieldflow_reconcile_submission_history', [self::class, 'reconcile_submission_history'], 10, 1);
        add_action('fieldflow_reconcile_submission_cardex', [self::class, 'reconcile_submission_cardex'], 10, 1);
        add_action('fieldflow_reconcile_route_after_submission', [self::class, 'reconcile_route_after_submission'], 10, 1);
        add_action('fieldflow_submission_post_commit', [self::class, 'dispatch_submission_integrations'], 10, 3);
    }
    public static function table(): string { global $wpdb; return $wpdb->prefix . 'routespro_forms'; }
    public static function table_submissions(): string { global $wpdb; return $wpdb->prefix . 'routespro_form_submissions'; }
    public static function table_answers(): string { global $wpdb; return $wpdb->prefix . 'routespro_form_submission_answers'; }
    public static function default_schema(): array {
        return ['meta'=>['title'=>'','subtitle'=>''],'layout'=>['mode'=>'single','show_progress'=>false,'steps'=>[],'field_layout'=>[]],'questions'=>[]];
    }
    public static function get_form(int $id): ?array { global $wpdb; $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d', $id), ARRAY_A); return $row ?: null; }

    public static function definition_version(array $form): string {
        $basis = [
            'id' => absint($form['id'] ?? 0),
            'schema_json' => (string)($form['schema_json'] ?? ''),
            'settings_json' => (string)($form['settings_json'] ?? ''),
            'updated_at' => (string)($form['updated_at'] ?? ''),
        ];
        return substr(hash('sha256', (string)wp_json_encode($basis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 24);
    }

    public static function schema_hash(array $schema): string {
        return hash('sha256', (string)wp_json_encode($schema));
    }

    /**
     * Decide whether an offline submission captured against an older schema can
     * be replayed safely against the current schema without discarding answers.
     *
     * Cosmetic changes and newly-added optional questions are compatible when
     * every answer/media field that exists in the queued payload still maps to
     * a question with the same key. Removed/renamed answered questions remain a
     * real conflict and the device copy is preserved for review.
     */
    private static function offline_replay_schema_compatibility(array $schema): array {
        $current = [];
        foreach ((array)($schema['questions'] ?? []) as $question) {
            if (!is_array($question)) continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($key !== '') $current[$key] = true;
        }

        $posted = [];
        $capture = static function($name) use (&$posted): void {
            if (!is_string($name) || $name === '') return;
            if (strpos($name, 'routespro_') === 0 || in_array($name, ['action','_wpnonce','_wp_http_referer'], true)) return;
            $root = preg_replace('/\[.*$/', '', $name);
            $root = preg_replace('/__(?:cardex_present|cardex_id)$/', '', (string)$root);
            $root = sanitize_key((string)$root);
            if ($root !== '') $posted[$root] = true;
        };
        $meaningful = static function($value) use (&$meaningful): bool {
            if ($value === null || $value === '' || $value === false) return false;
            if (is_array($value)) {
                foreach ($value as $part) if ($meaningful($part)) return true;
                return false;
            }
            return true; // Numeric zero and the string "0" are real answers.
        };
        foreach ((array)$_POST as $name => $value) {
            if ($meaningful(wp_unslash($value))) $capture($name);
        }
        $file_has_bytes = static function($sizes) use (&$file_has_bytes): bool {
            if (is_array($sizes)) {
                foreach ($sizes as $size) if ($file_has_bytes($size)) return true;
                return false;
            }
            return is_numeric($sizes) && (int)$sizes > 0;
        };
        foreach ((array)$_FILES as $name => $file) {
            if (!is_array($file)) continue;
            if ($file_has_bytes($file['size'] ?? 0)) $capture($name);
        }

        $manifest_raw = isset($_POST['routespro_offline_media_manifest']) ? (string)wp_unslash($_POST['routespro_offline_media_manifest']) : '';
        if ($manifest_raw !== '' && strlen($manifest_raw) <= 524288) {
            $manifest = json_decode($manifest_raw, true);
            if (is_array($manifest) && is_array($manifest['files'] ?? null)) {
                foreach (array_slice($manifest['files'], 0, 300) as $file) {
                    if (is_array($file)) $capture((string)($file['field_name'] ?? ''));
                }
            }
        }

        $refs_raw = isset($_POST['routespro_resumable_media_refs']) ? (string)wp_unslash($_POST['routespro_resumable_media_refs']) : '';
        if ($refs_raw !== '' && strlen($refs_raw) <= 1048576) {
            $refs_payload = json_decode($refs_raw, true);
            if (is_array($refs_payload) && is_array($refs_payload['refs'] ?? null)) {
                foreach (array_slice($refs_payload['refs'], 0, 300) as $ref) {
                    if (is_array($ref)) $capture((string)($ref['field_name'] ?? ''));
                }
            }
        }

        $unknown = array_values(array_diff(array_keys($posted), array_keys($current)));
        sort($unknown, SORT_STRING);
        return [
            'compatible' => empty($unknown),
            'posted_question_keys' => array_values(array_keys($posted)),
            'unknown_question_keys' => $unknown,
        ];
    }
    public static function list_forms(): array { global $wpdb; return $wpdb->get_results('SELECT * FROM ' . self::table() . ' ORDER BY updated_at DESC, id DESC', ARRAY_A) ?: []; }

    public static function manual_recovery_active(): bool {
        return !empty(self::$manualRecoveryContext);
    }

    /**
     * Promove um rascunho sincronizado no servidor a submissão real durante uma
     * correção administrativa. O utilizador original continua a ser o autor dos
     * dados e o administrador fica registado apenas como ator da recuperação.
     *
     * @return array|\WP_Error
     */
    public static function submit_recovered_draft(int $draft_id, int $actor_user_id, string $completed_at, string $reason) {
        if (!class_exists('\RoutesPro\Forms\DraftService')) {
            return new \WP_Error('fieldflow_recovery_draft_service_missing', 'O serviço de rascunhos não está disponível.', ['status' => 500]);
        }
        global $wpdb;
        $draft_id = absint($draft_id);
        $actor_user_id = absint($actor_user_id);
        if ($draft_id <= 0) return new \WP_Error('fieldflow_recovery_draft_invalid', 'O rascunho indicado não é válido.', ['status' => 400]);
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DraftService::table() . ' WHERE id=%d LIMIT 1', $draft_id), ARRAY_A) ?: [];
        if (!$row) return new \WP_Error('fieldflow_recovery_draft_missing', 'O rascunho já não está disponível no servidor.', ['status' => 404]);
        $owner_user_id = absint($row['user_id'] ?? 0);
        if ($owner_user_id <= 0 || !get_user_by('id', $owner_user_id)) {
            return new \WP_Error('fieldflow_recovery_draft_owner_missing', 'Não foi possível identificar o utilizador que preencheu o rascunho.', ['status' => 409]);
        }
        $compatibility = DraftService::validate_recovery_draft($row);
        if (is_wp_error($compatibility)) return $compatibility;
        $post = DraftService::submission_post_from_draft($row);
        if (!$post || empty($post['routespro_form_id']) || empty($post['routespro_stop_id'])) {
            return new \WP_Error('fieldflow_recovery_draft_payload_invalid', 'O rascunho não contém contexto suficiente para ser recuperado.', ['status' => 409]);
        }

        $previous_post = $_POST;
        $previous_files = $_FILES;
        $previous_user_id = get_current_user_id();
        $previous_xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? null;
        self::$internalSubmissionMode = true;
        self::$internalSubmissionResult = [];
        self::$manualRecoveryContext = [
            'draft_id' => $draft_id,
            'actor_user_id' => $actor_user_id,
            'owner_user_id' => $owner_user_id,
            'completed_at' => sanitize_text_field($completed_at),
            'reason' => sanitize_textarea_field($reason),
            'recovered_at' => current_time('mysql'),
            'schema_rebased' => !empty($compatibility['schema_rebased']),
            'expected_files' => (int)($compatibility['expected_files'] ?? 0),
            'verified_files' => (int)($compatibility['verified_files'] ?? 0),
        ];
        self::$submissionStage = 'manual_recovery_bootstrap';
        self::$submissionStartedAt = microtime(true);
        self::$submissionCommitted = false;
        self::$committedResponse = [];
        self::$submissionWarnings = [];
        self::$uploadErrors = [];
        self::$resumableMediaRefs = [];
        self::$uploadedPaths = [];
        self::$mediaTelemetryRecorded = false;

        try {
            wp_set_current_user($owner_user_id);
            $_POST = $post;
            $_POST[self::NONCE_FIELD] = wp_create_nonce(self::NONCE_ACTION);
            $_POST['routespro_ajax'] = 1;
            $_POST['routespro_draft_id'] = $draft_id;
            $_FILES = [];
            $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
            self::handle_submit();
            $result = self::$internalSubmissionResult;
            if (!empty($result['success']) && is_array($result['data'] ?? null)) return $result['data'];
            if (self::$submissionCommitted && self::$committedResponse) return self::$committedResponse;
            return new \WP_Error('fieldflow_recovery_submit_unknown', 'O rascunho não pôde ser convertido num reporte concluído.', ['status' => 500]);
        } catch (\Throwable $error) {
            if (self::$submissionCommitted && self::$committedResponse) return self::$committedResponse;
            $internal = self::$internalSubmissionResult;
            if (empty($internal['success']) && is_array($internal['data'] ?? null)) {
                $data = $internal['data'];
                return new \WP_Error(
                    sanitize_key((string)($data['code'] ?? 'fieldflow_recovery_submit_failed')),
                    sanitize_text_field((string)($data['message'] ?? 'Não foi possível recuperar o rascunho.')),
                    ['status' => max(400, (int)($internal['status'] ?? 500)), 'details' => $data]
                );
            }
            return new \WP_Error('fieldflow_recovery_submit_failed', $error->getMessage() ?: 'Não foi possível recuperar o rascunho.', ['status' => 500]);
        } finally {
            $_POST = $previous_post;
            $_FILES = $previous_files;
            if ($previous_xrw === null) unset($_SERVER['HTTP_X_REQUESTED_WITH']); else $_SERVER['HTTP_X_REQUESTED_WITH'] = $previous_xrw;
            wp_set_current_user($previous_user_id);
            self::$internalSubmissionMode = false;
            self::$internalSubmissionResult = [];
            self::$manualRecoveryContext = [];
        }
    }

    public static function shortcode_route_form($atts = []) {
        $atts = shortcode_atts(['client_id'=>0,'project_id'=>0,'route_id'=>0,'stop_id'=>0,'location_id'=>0,'fallback'=>'','show_title'=>1], $atts);

        $context = [
            'client_id' => absint($atts['client_id']),
            'project_id' => absint($atts['project_id']),
            'route_id' => absint($atts['route_id']),
            'stop_id' => absint($atts['stop_id']),
            'location_id' => absint($atts['location_id']),
        ];

        foreach (array_keys($context) as $field) {
            if (empty($context[$field]) && isset($_GET[$field])) {
                $context[$field] = absint(wp_unslash($_GET[$field]));
            }
        }

        $context = BindingResolver::get_context($context);
        $binding = BindingResolver::resolve($context);
        if (!$binding || empty($binding['form_id'])) {
            $fallback = trim((string) ($atts['fallback'] ?? ''));
            if ($fallback !== '') return do_shortcode($fallback);
            return '<p>Nenhum formulário activo para este contexto.</p>';
        }
        return self::render_form_with_context((int) $binding['form_id'], $context, $binding, [
            'show_title' => !empty($atts['show_title']),
        ]);
    }

    public static function shortcode_form($atts = []) {
        $atts = shortcode_atts(['id'=>0,'client_id'=>0,'project_id'=>0,'route_id'=>0,'stop_id'=>0,'location_id'=>0,'binding_id'=>0,'show_title'=>1], $atts);
        $context = [
            'client_id' => absint($atts['client_id']),
            'project_id' => absint($atts['project_id']),
            'route_id' => absint($atts['route_id']),
            'stop_id' => absint($atts['stop_id']),
            'location_id' => absint($atts['location_id']),
        ];
        $binding = !empty($atts['binding_id']) ? ['id' => absint($atts['binding_id'])] : null;
        return self::render_form_with_context(absint($atts['id']), $context, $binding, [
            'show_title' => !empty($atts['show_title']),
        ]);
    }

    public static function render_form_with_context(int $form_id, array $context = [], ?array $binding = null, array $opts = []) {
        if (!$form_id) return '<p>Formulário inválido.</p>';
        if (!is_user_logged_in()) return '<p>Precisas de login para submeter.</p>';
        if (empty($context['stop_id']) && !empty($context['route_stop_id'])) {
            $context['stop_id'] = absint($context['route_stop_id']);
        }
        $contextIds = [
            absint($context['client_id'] ?? 0),
            absint($context['project_id'] ?? 0),
            absint($context['route_id'] ?? 0),
            absint($context['stop_id'] ?? 0),
            absint($context['location_id'] ?? 0),
        ];
        if (array_filter($contextIds)) {
            $context = BindingResolver::get_context($context);
        } elseif (empty($context['date'])) {
            $context['date'] = current_time('Y-m-d');
            $context['route_date'] = $context['date'];
        }
        $form = self::get_form($form_id);
        if (!$form || ($form['status'] ?? '') !== 'active') return '<p>Formulário não encontrado ou inativo.</p>';
        $context_for_questions = $context;
        $context_for_questions['form_id'] = $form_id;
        $schema = SubmissionApplicationService::buildSchema($form, $context_for_questions);
        if (empty($schema['questions'])) return '<p>O formulário não tem perguntas configuradas.</p>';
        $report_day = DailySubmissionService::normalise_report_day(
            $context['route_date'] ?? $context['date'] ?? current_time('Y-m-d')
        );
        $daily_existing = !empty($context['location_id'])
            ? DailySubmissionService::find_existing($form_id, (int)$context['location_id'], $report_day)
            : null;
        $daily_context_conflict = $daily_existing ? DailySubmissionService::edit_context_conflict($daily_existing, $context) : null;
        $daily_can_edit = $daily_existing
            ? (DailySubmissionService::can_edit($daily_existing, get_current_user_id()) && !$daily_context_conflict)
            : false;
        $needs_multipart = FormRenderer::schema_needs_multipart($schema);
        $record_state = RecordService::get_record_state(
            $form_id,
            (int) ($context['client_id'] ?? 0),
            (int) ($context['project_id'] ?? 0),
            (int) ($context['location_id'] ?? 0)
        );
        $record_prefill = is_array($record_state['prefill'] ?? null) ? $record_state['prefill'] : [];
        $record = is_array($record_state['record'] ?? null) ? $record_state['record'] : null;
        $version = is_array($record_state['version'] ?? null) ? $record_state['version'] : null;
        if ($record) {
            $record_prefill = RecordService::prepare_prefill_for_schema((int)($record['id'] ?? 0), $schema);
        }
        $record_prefill = RecordService::without_historical_media($record_prefill, $schema);

        // 9BH: resolve the latest operational visit through this report day,
        // including other same-day visits. Never use the generic record pointer.
        $previous_submission = !empty($context['location_id'])
            ? DailySubmissionService::find_previous_operational(
                $form_id,
                (int)$context['location_id'],
                $report_day,
                (int)($context['client_id'] ?? 0),
                (int)($context['project_id'] ?? 0),
                true,
                (int)($daily_existing['id'] ?? 0)
            )
            : null;
        $historical_prefill = $previous_submission
            ? DailySubmissionService::submission_prefill((int)$previous_submission['id'], $schema)
            : [];
        $historical_prefill = DailySubmissionService::visit_memory_prefill($historical_prefill, $schema);
        $history_source = $previous_submission;

        // 9BZ: a nova visita parte da última visita operacional da mesma loja.
        // Reutilizamos respostas elegíveis e Frentes, mas nunca fotografias,
        // ficheiros, briefing ou Sugestão de encomenda.
        // 9BD historical certification marker only: Frentes reutiliza o valor final anterior.
        // A política runtime continua governada por new_visit_autofill_prefill().
        $prefill = // 9BH predecessor certification marker: DailySubmissionService::new_visit_prefill
        $prefill = DailySubmissionService::new_visit_autofill_prefill($historical_prefill, $schema);
        $previous_visit_notices = !$daily_existing
            ? self::previous_visit_notices($schema, $historical_prefill, $history_source)
            : [];
        if ($daily_existing && !$daily_context_conflict) {
            $daily_prefill = DailySubmissionService::submission_prefill((int)$daily_existing['id'], $schema);
            // Editing restores only this report, including its own media/order.
            // 9BJ: a report from another route/stop/store must never prefill this
            // form, even when a historical numeric location_id happens to collide.
            $prefill = $daily_prefill;
        }
        $render_context = $context;
        $render_context['_record_exists'] = !empty($previous_submission);
        $render_context['_record_version_no'] = (int)($version['version_no'] ?? 0);
        $render_context['_daily_existing'] = !empty($daily_existing);
        $render_context['_daily_can_edit'] = $daily_can_edit;
        $render_context['_daily_submission_id'] = (int)($daily_existing['id'] ?? 0);
        $render_context['_suppress_meta'] = true;
        $legacy_form_theme = self::decode_json_array($form['theme_json'] ?? '');
        $theme = class_exists('\\RoutesPro\\Support\\Theme')
            ? \RoutesPro\Support\Theme::resolve((int)($context['client_id'] ?? 0), (int)($context['project_id'] ?? 0))
            : $legacy_form_theme;
        // A personalização da campanha é a fonte visual principal.
        $style_attr = FormRenderer::theme_style_attr($theme ?: $legacy_form_theme);
        $theme_classes = class_exists('\\RoutesPro\\Support\\Theme')
            ? \RoutesPro\Support\Theme::root_classes($theme)
            : '';
        $show_title = array_key_exists('show_title', $opts) ? !empty($opts['show_title']) : true;
        $hide_actions = !empty($opts['hide_actions']);
        // Quando as ações internas estão ocultas, a submissão é controlada pelo
        // shortcode exterior (reporte rápido ou fluxo focado). O wizard mantém
        // apenas Anterior/Seguinte e nunca cria um segundo botão de submissão.
        $external_submit = $hide_actions || !empty($opts['external_submit']);
        $render_context['_external_submit'] = $external_submit;
        $binding_id = (int) ($binding['id'] ?? 0);
        $return_url = isset($opts['return_url']) ? esc_url_raw((string) $opts['return_url']) : self::current_request_url();
        $draft_user_id = (int) get_current_user_id();
        $draft_enabled = $draft_user_id > 0;
        $schema_hash = self::schema_hash($schema);
        $schema_revision = substr($schema_hash, 0, 20);
        $form_version = self::definition_version($form);
        $form_downloaded_at = current_time('mysql', true);
        $draft_endpoint = admin_url('admin-ajax.php');
        $draft_nonce = $draft_enabled && class_exists('\\RoutesPro\\Forms\\DraftService') ? wp_create_nonce(DraftService::NONCE_ACTION) : '';
        $offline_token = class_exists('\\RoutesPro\\Mobile\\OfflineSyncService')
            ? \RoutesPro\Mobile\OfflineSyncService::issueFormToken($form_id, $context, $draft_user_id, $report_day)
            : '';
        $submit_success = isset($_GET['routespro_form_ok']) && wp_unslash($_GET['routespro_form_ok']) === '1';
        $runtime_v3 = class_exists('\RoutesPro\Forms\RuntimeV3')
            ? RuntimeV3::requestState($context)
            : ['mode'=>'legacy','owner'=>'legacy','runtime_version'=>'','reason'=>'unavailable','diagnostics'=>false,'debug_ui'=>false];
        $previous_modal_context = [
            (int)$form_id,
            (int)($context['client_id'] ?? 0),
            (int)($context['project_id'] ?? 0),
            (int)($context['location_id'] ?? 0),
            (string)$report_day,
            (int)($context['route_id'] ?? 0),
            (int)($context['stop_id'] ?? 0),
        ];
        // 9BI: stable visit identity and independently versioned memory.
        $previous_modal_revision = substr(hash('sha256', wp_json_encode([
            'fieldflow-visit-memory-refresh-v1',
            (int)($history_source['id'] ?? 0),
            (string)($history_source['submitted_at'] ?? ''),
            $previous_visit_notices,
        ])), 0, 24);
        // 2.2.292: chave estável por visita. A app pode renderizar o mesmo
        // formulário mais do que uma vez enquanto resolve rota/loja. Uma chave
        // aleatória fazia o runtime interpretar cada render como um popup novo.
        $previous_modal_key = substr(hash('sha256', implode('|', array_map('strval', $previous_modal_context))), 0, 24);
        $previous_modal_id = 'routespro-previous-visit-' . $previous_modal_key;
        $store_profile_html = '';
        if (!empty($context['location_id']) && class_exists('\\RoutesPro\\Stores\\StoreProfileService') && class_exists('\\RoutesPro\\Stores\\StoreProfileCard')) {
            $store_profile_snapshot = \RoutesPro\Stores\StoreProfileService::snapshot(
                (int)$context['location_id'],
                (int)($context['project_id'] ?? 0),
                (int)($context['route_id'] ?? 0)
            );
            if (!is_wp_error($store_profile_snapshot)) {
                $store_profile_html = \RoutesPro\Stores\StoreProfileCard::render($store_profile_snapshot);
            }
        }
        ob_start(); ?>
        <div class="routespro-form-wrap rp-form-ui <?php echo esc_attr($theme_classes); ?>" data-rp-theme-template="<?php echo esc_attr((string)($theme['design_template'] ?? '')); ?>"<?php echo $style_attr ? ' style="' . esc_attr($style_attr) . '"' : ''; ?>>
            <?php if ($previous_visit_notices): ?>
                <div id="<?php echo esc_attr($previous_modal_id); ?>" class="routespro-previous-visit-modal" data-rp-previous-visit-modal data-rp-previous-visit-key="<?php echo esc_attr($previous_modal_key); ?>" data-rp-previous-visit-revision="<?php echo esc_attr($previous_modal_revision); ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($previous_modal_id); ?>-title" hidden>
                    <div class="routespro-previous-visit-backdrop" data-rp-close-previous-visit></div>
                    <div class="routespro-previous-visit-dialog">
                        <div class="routespro-previous-visit-kicker">Memória da loja</div>
                        <h3 id="<?php echo esc_attr($previous_modal_id); ?>-title">Pendências e notas da visita anterior</h3>
                        <p>Consulta esta informação antes de iniciares o reporte atual. O conteúdo é apenas informativo e não é copiado para a nova visita.</p>
                        <div class="routespro-previous-visit-list">
                            <?php foreach ($previous_visit_notices as $previousNotice): ?>
                                <article class="routespro-previous-visit-item">
                                    <strong><?php echo esc_html((string)($previousNotice['label'] ?? 'Informação anterior')); ?></strong>
                                    <div><?php echo nl2br(esc_html((string)($previousNotice['value'] ?? ''))); ?></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <?php $previousSubmittedAt = (string)($history_source['submitted_at'] ?? ''); if ($previousSubmittedAt !== ''): ?>
                            <small>Última atualização: <?php echo esc_html(mysql2date('d/m/Y H:i', $previousSubmittedAt)); ?></small>
                        <?php endif; ?>
                        <button type="button" class="routespro-form-btn" data-rp-close-previous-visit>Compreendi, iniciar visita</button>
                    </div>
                </div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="routespro-dyn-form<?php echo $daily_existing ? ' routespro-daily-report-existing' : ''; ?>" novalidate data-routespro-form-id="<?php echo esc_attr($form_id); ?>" data-rp-daily-existing="<?php echo $daily_existing ? '1' : '0'; ?>" data-rp-daily-can-edit="<?php echo $daily_can_edit ? '1' : '0'; ?>" data-rp-daily-context-conflict="<?php echo $daily_context_conflict ? '1' : '0'; ?>" data-rp-report-day="<?php echo esc_attr($report_day); ?>" data-rp-draft-enabled="<?php echo $draft_enabled ? '1' : '0'; ?>" data-rp-user-id="<?php echo esc_attr((string) $draft_user_id); ?>" data-rp-schema-revision="<?php echo esc_attr($schema_revision); ?>" data-rp-form-version="<?php echo esc_attr($form_version); ?>" data-rp-schema-hash="<?php echo esc_attr($schema_hash); ?>" data-rp-form-downloaded-at="<?php echo esc_attr($form_downloaded_at); ?>" data-rp-external-submit="<?php echo $external_submit ? '1' : '0'; ?>" data-rp-submit-success="<?php echo $submit_success ? '1' : '0'; ?>" data-rp-submit-ajax-endpoint="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-rp-draft-endpoint="<?php echo esc_url($draft_endpoint); ?>" data-rp-draft-nonce="<?php echo esc_attr($draft_nonce); ?>" data-rp-draft-server-enabled="<?php echo ($draft_enabled && $draft_nonce !== '') ? '1' : '0'; ?>" data-rp-runtime-mode="<?php echo esc_attr((string)($runtime_v3['mode'] ?? 'legacy')); ?>" data-rp-runtime-owner="<?php echo esc_attr((string)($runtime_v3['owner'] ?? 'legacy')); ?>" data-rp-runtime-version="<?php echo esc_attr((string)($runtime_v3['runtime_version'] ?? '')); ?>" data-rp-runtime-reason="<?php echo esc_attr((string)($runtime_v3['reason'] ?? '')); ?>" data-rp-runtime-diagnostics="<?php echo !empty($runtime_v3['diagnostics']) ? '1' : '0'; ?>" data-rp-runtime-debug-ui="<?php echo !empty($runtime_v3['debug_ui']) ? '1' : '0'; ?>" data-rp-media-diag-endpoint="<?php echo esc_url(rest_url('fieldflow-mobile/v1/diagnostics/client-event')); ?>" data-rp-media-diag-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>"<?php echo $needs_multipart ? ' enctype="multipart/form-data"' : ''; ?>>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_SUBMIT); ?>">
                <input type="hidden" name="routespro_form_id" value="<?php echo esc_attr($form_id); ?>">
                <input type="hidden" name="routespro_binding_id" value="<?php echo esc_attr($binding_id); ?>">
                <input type="hidden" name="routespro_client_id" value="<?php echo esc_attr((int) ($context['client_id'] ?? 0)); ?>">
                <input type="hidden" name="routespro_project_id" value="<?php echo esc_attr((int) ($context['project_id'] ?? 0)); ?>">
                <input type="hidden" name="routespro_route_id" value="<?php echo esc_attr((int) ($context['route_id'] ?? 0)); ?>">
                <input type="hidden" name="routespro_stop_id" value="<?php echo esc_attr((int) ($context['stop_id'] ?? $context['route_stop_id'] ?? 0)); ?>">
                <input type="hidden" name="routespro_location_id" value="<?php echo esc_attr((int) ($context['location_id'] ?? 0)); ?>">
                <input type="hidden" name="routespro_context_date" value="<?php echo esc_attr(sanitize_text_field((string)($context['date'] ?? $context['route_date'] ?? current_time('Y-m-d')))); ?>">
                <input type="hidden" name="routespro_record_id" value="<?php echo esc_attr((int) ($record['id'] ?? 0)); ?>">
                <input type="hidden" name="routespro_record_version_id" value="<?php echo esc_attr((int) ($version['id'] ?? 0)); ?>">
                <input type="hidden" name="routespro_report_day" value="<?php echo esc_attr($report_day); ?>">
                <input type="hidden" name="routespro_existing_submission_id" value="<?php echo esc_attr((int)($daily_existing['id'] ?? 0)); ?>">
                <input type="hidden" name="routespro_edit_existing" value="0">
                <input type="hidden" name="routespro_return_url" value="<?php echo esc_attr($return_url); ?>">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                <input type="hidden" name="routespro_ajax" value="0">
                <input type="hidden" name="routespro_draft_id" value="0">
                <input type="hidden" name="routespro_offline_token" value="<?php echo esc_attr($offline_token); ?>">
                <input type="hidden" name="routespro_offline_replay" value="0">
                <input type="hidden" name="routespro_offline_mutation_id" value="">
                <input type="hidden" name="routespro_offline_base_revision" value="<?php echo esc_attr($schema_revision); ?>">
                <input type="hidden" name="routespro_form_version" value="<?php echo esc_attr($form_version); ?>">
                <input type="hidden" name="routespro_schema_hash" value="<?php echo esc_attr($schema_hash); ?>">
                <input type="hidden" name="routespro_form_downloaded_at" value="<?php echo esc_attr($form_downloaded_at); ?>">
                <input type="hidden" name="routespro_media_file_count" value="0">
                <input type="hidden" name="routespro_media_original_bytes" value="0">
                <input type="hidden" name="routespro_media_optimized_bytes" value="0">
                <input type="hidden" name="routespro_media_optimized_files" value="0">
                <input type="hidden" name="routespro_media_duplicate_files" value="0">
                <input type="hidden" name="routespro_media_retry_count" value="0">
                <input type="hidden" name="routespro_client_submit_started_ms" value="0">
                <div class="routespro-form-live-region" aria-live="polite" aria-atomic="true"></div>
                <?php if ($store_profile_html !== '') echo $store_profile_html; ?>
                <?php if (isset($_GET['routespro_form_ok']) && wp_unslash($_GET['routespro_form_ok']) === '1'): ?>
                    <div class="routespro-form-notice success"><?php echo !empty($_GET['routespro_form_updated']) ? 'Reporte atualizado com sucesso. Continua a existir apenas um reporte para esta loja neste dia.' : (!empty($_GET['routespro_visit_done']) ? 'Reporte gravado com sucesso. A visita à loja foi marcada como concluída.' : 'Submissão gravada com sucesso. O histórico desta visita foi atualizado.'); ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['routespro_form_err'])): ?><div class="routespro-form-notice error">Falha ao submeter: <?php echo esc_html(self::submit_error_message(sanitize_key((string)wp_unslash($_GET['routespro_form_err'])))); ?></div><?php endif; ?>
                <?php if ($daily_existing): ?>
                    <div class="routespro-form-notice warning routespro-daily-report-notice" data-rp-daily-notice>
                        <div>
                            <strong>Reporte já efetuado.</strong>
                            Esta loja já tem um reporte deste formulário para <?php echo esc_html(date_i18n('d/m/Y', strtotime($report_day))); ?>, submetido às <?php echo esc_html(mysql2date('H:i', (string)($daily_existing['submitted_at'] ?? ''))); ?>.
                            <span data-rp-daily-mode-copy><?php echo $daily_context_conflict ? 'Este reporte pertence a outra rota ou paragem e fica disponível apenas para consulta neste contexto.' : ($daily_can_edit ? 'Podes consultar as respostas ou ativar a edição para corrigir algum engano.' : 'As respostas ficam disponíveis apenas para consulta.'); ?></span>
                        </div>
                        <?php if ($daily_can_edit): ?>
                            <button type="button" class="routespro-form-btn secondary routespro-daily-edit-btn" data-rp-enable-daily-edit>Editar reporte</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($previous_submission && !$daily_existing): ?>
                    <div class="routespro-form-notice info routespro-form-context-note">
                        <strong>Histórico da última visita carregado.</strong> As respostas elegíveis foram pré-preenchidas para acelerar a visita. Fotografias, ficheiros e Sugestão de encomenda nunca são reutilizados. Frentes mantém a continuidade da última visita.
                        <?php if (!empty($history_source['submitted_at'])): ?>
                            <span>Última visita: <?php echo esc_html(mysql2date('d/m/Y H:i', (string)$history_source['submitted_at'])); ?>.</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($show_title && (!empty($schema['meta']['title']) || !empty($schema['meta']['subtitle']))): ?>
                    <div class="routespro-form-head">
                        <?php if (!empty($schema['meta']['title'])): ?><h3><?php echo esc_html($schema['meta']['title']); ?></h3><?php endif; ?>
                        <?php if (!empty($schema['meta']['subtitle'])): ?><p><?php echo esc_html($schema['meta']['subtitle']); ?></p><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php echo FormRenderer::render_questions($schema, $prefill, $render_context); ?>
                <?php if (!$hide_actions): ?><div class="routespro-form-actions"><button type="submit" class="routespro-form-btn">Submeter</button></div><?php endif; ?>
            </form>
        </div>
        <?php return ob_get_clean();
    }

    private static function previous_visit_notices(array $schema, array $prefill, ?array $version = null): array {
        $out = [];
        foreach ((array)($schema['questions'] ?? []) as $question) {
            if (!is_array($question) || !DailySubmissionService::visit_memory_question_eligible($question)) continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($key === '' || !array_key_exists($key, $prefill)) continue;
            $value = self::previous_visit_value_text($prefill[$key]);
            if ($value === '') continue;
            $out[] = [
                'key' => $key,
                'label' => sanitize_text_field((string)($question['label'] ?? $key)),
                'value' => $value,
                'submitted_at' => sanitize_text_field((string)($version['submitted_at'] ?? '')),
            ];
        }
        return $out;
    }

    private static function previous_visit_value_text($value): string {
        if (is_bool($value)) return $value ? 'Sim' : 'Não';
        if (is_scalar($value)) return trim((string)$value);
        if (!is_array($value)) return '';
        $flat = [];
        array_walk_recursive($value, static function($item) use (&$flat) {
            if (is_bool($item)) $item = $item ? 'Sim' : 'Não';
            if (!is_scalar($item)) return;
            $text = trim((string)$item);
            if ($text !== '') $flat[] = $text;
        });
        $flat = array_values(array_unique($flat));
        return implode("\n", array_slice($flat, 0, 30));
    }


    /**
     * 9CJ Context Form Scope Authority. Returns an empty string when the current user may
     * read the resolved route/client/project context, or a user-facing reason otherwise.
     */
    private static function context_form_scope_error(array $context): string {
        $uid = get_current_user_id();
        if ($uid <= 0) return 'Sem sessão ativa.';
        $route_id = absint($context['route_id'] ?? 0);
        if ($route_id > 0 && !\RoutesPro\Support\Permissions::can_access_route($route_id, $uid)) {
            return 'Sem acesso à rota selecionada.';
        }
        $scope = \RoutesPro\Support\Permissions::assert_scope_or_error(absint($context['client_id'] ?? 0), absint($context['project_id'] ?? 0), $uid);
        if (is_wp_error($scope)) return (string)$scope->get_error_message();
        return '';
    }

    public static function ajax_context_form() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Sem sessão ativa.'], 401);
        }
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            wp_send_json_error(['message' => 'Nonce inválido.'], 403);
        }

        // 9CV fieldflow-report-context-recovery-9cv-v1. The WordPress bootstrap can fail
        // before this handler runs; once here, always return a safe structured failure.
        try {
        $context = BindingResolver::get_context([
            'client_id' => isset($_POST['client_id']) ? absint($_POST['client_id']) : 0,
            'project_id' => isset($_POST['project_id']) ? absint($_POST['project_id']) : 0,
            'route_id' => isset($_POST['route_id']) ? absint($_POST['route_id']) : 0,
            'stop_id' => isset($_POST['stop_id']) ? absint($_POST['stop_id']) : 0,
            'location_id' => isset($_POST['location_id']) ? absint($_POST['location_id']) : 0,
            'date' => isset($_POST['date']) ? sanitize_text_field((string)wp_unslash($_POST['date'])) : '',
        ]);

        // 9CJ fieldflow-context-form-scope-authority-v1: o formulário de contexto inclui o
        // pré-preenchimento da última visita da loja. A leitura segue a mesma autoridade de
        // rota e scope cliente/campanha que a submissão (SubmissionContext).
        $scope_error = self::context_form_scope_error($context);
        if ($scope_error !== '') {
            wp_send_json_error(['message' => $scope_error, 'code' => 'fieldflow_context_form_forbidden'], 403);
        }

        $binding = BindingResolver::resolve($context);
        if (!$binding || empty($binding['form_id'])) {
            wp_send_json_success([
                'has_form' => false,
                'binding_mode' => '',
                'html' => '',
                'context' => $context,
            ]);
        }

        $binding_mode = sanitize_key($binding['mode'] ?? '');
        if ($binding_mode !== 'route_and_form' && $binding_mode !== 'form_only') {
            wp_send_json_success([
                'has_form' => false,
                'binding_mode' => $binding_mode,
                'html' => '',
                'context' => $context,
            ]);
        }

        $return_url = isset($_POST['return_url']) ? esc_url_raw((string) wp_unslash($_POST['return_url'])) : '';
        $html = self::render_form_with_context((int) $binding['form_id'], $context, $binding, [
            'show_title' => true,
            'hide_actions' => true,
            'return_url' => $return_url,
        ]);

        wp_send_json_success([
            'has_form' => true,
            'binding_mode' => $binding_mode,
            'html' => $html,
            'context' => $context,
            'form_id' => (int) ($binding['form_id'] ?? 0),
            'binding_id' => (int) ($binding['id'] ?? 0),
        ]);
        } catch (\Throwable $e) {
            // Never return connection details, raw SQL, hostnames or wp-config paths.
            if (class_exists('\RoutesPro\Support\Logger')) {
                try {
                    \RoutesPro\Support\Logger::captureThrowable('Reportar: contexto do formulario indisponivel.', $e, [
                        'context_key' => 'report_context_form',
                        'route_id' => isset($_POST['route_id']) ? absint($_POST['route_id']) : 0,
                        'stop_id' => isset($_POST['stop_id']) ? absint($_POST['stop_id']) : 0,
                        'user_id' => get_current_user_id(),
                    ]);
                } catch (\Throwable $ignored) {}
            }
            wp_send_json_error([
                'code' => 'fieldflow_report_context_unavailable',
                'message' => 'O formulario da loja nao esta disponivel neste momento. Tenta novamente dentro de momentos.',
            ], 503);
        }
    }


    public static function handle_submit_safely() {
        $is_ajax = !empty($_POST['routespro_ajax'])
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        self::$submissionStage = 'bootstrap';
        self::$submissionStartedAt = microtime(true);
        self::$submissionCommitted = false;
        self::$committedResponse = [];
        self::$submissionWarnings = [];
        self::$uploadedPaths = [];
        self::$mediaTelemetryRecorded = false;
        $incident_id = 'FF-' . gmdate('Ymd-His') . '-' . strtoupper(substr(wp_generate_password(8, false, false), 0, 8));
        try {
            self::handle_submit();
        } catch (\Throwable $e) {
            global $wpdb;
            if (!self::$submissionCommitted) self::cleanup_current_uploads();
            if (!self::$submissionCommitted && isset($wpdb) && is_object($wpdb)) {
                try { $wpdb->query('ROLLBACK'); } catch (\Throwable $ignored) {}
            }
            self::log_submission_throwable('Erro inesperado na submissão do formulário.', $e, [
                'context_key' => 'form_submission_emergency',
                'incident_id' => $incident_id,
                'submission_stage' => self::$submissionStage,
                'submission_committed' => self::$submissionCommitted ? 1 : 0,
                'user_id' => get_current_user_id(),
                'route_id' => isset($_POST['routespro_route_id']) ? absint($_POST['routespro_route_id']) : 0,
                'client_id' => isset($_POST['routespro_client_id']) ? absint($_POST['routespro_client_id']) : 0,
                'project_id' => isset($_POST['routespro_project_id']) ? absint($_POST['routespro_project_id']) : 0,
            ]);

            // Se a transação principal já foi confirmada, nunca devolve erro ao
            // operacional. O reporte existe e as tarefas secundárias ficam para
            // reconciliação, evitando submissões repetidas no terreno.
            if (self::$submissionCommitted) {
                $payload = self::$committedResponse;
                $payload['recovered_after_commit'] = true;
                $payload['incident_id'] = $incident_id;
                $payload['submission_stage'] = self::$submissionStage;
                $payload['processing_ms'] = self::submission_elapsed_ms();
                $payload['warnings'] = array_values(array_unique(array_merge(
                    (array)($payload['warnings'] ?? []),
                    self::$submissionWarnings,
                    ['Uma tarefa secundária falhou depois da gravação. O reporte está guardado e será reconciliado.']
                )));
                $payload['message'] = (string)($payload['message'] ?? 'Reporte gravado com sucesso.') . ' O reporte ficou guardado. A sincronização complementar será reconciliada automaticamente.';
                self::record_media_telemetry('success', 'recovered_after_commit');
                if ($is_ajax) {
                    if (!headers_sent()) nocache_headers();
                    wp_send_json_success($payload, 200);
                }
                wp_safe_redirect(self::redirect_back_with_success(!empty($payload['visit_completion']['applied']), !empty($payload['updated_existing'])));
                exit;
            }

            $payload = [
                'code' => 'unexpected_submission_error',
                'incident_id' => $incident_id,
                'submission_stage' => self::$submissionStage,
                'processing_ms' => self::submission_elapsed_ms(),
                'message' => 'O servidor encontrou um erro antes de concluir o reporte. As respostas continuam no formulário. Referência ' . $incident_id . '.',
            ];
            self::record_media_telemetry('error', 'unexpected_submission_error');
            if ($is_ajax) {
                if (!headers_sent()) nocache_headers();
                wp_send_json_error($payload, 500);
            }
            $url = add_query_arg([
                'routespro_form_err' => 'unexpected_submission_error',
                'routespro_incident' => rawurlencode($incident_id),
            ], self::get_return_url());
            wp_safe_redirect($url);
            exit;
        }
    }

    private static function set_submission_stage(string $stage): void {
        self::$submissionStage = sanitize_key($stage) ?: 'unknown';
    }

    private static function add_submission_warning(string $message): void {
        $message = sanitize_text_field($message);
        if ($message !== '') self::$submissionWarnings[] = $message;
    }

    private static function log_submission_throwable(string $label, \Throwable $e, array $context = []): void {
        if (!class_exists('\\RoutesPro\\Support\\Logger')) return;
        try {
            $context['submission_stage'] = $context['submission_stage'] ?? self::$submissionStage;
            \RoutesPro\Support\Logger::captureThrowable($label, $e, $context);
            \RoutesPro\Support\Logger::error('Diagnóstico da submissão: ' . (string)$context['submission_stage'], $context, [
                'submission_stage' => (string)$context['submission_stage'],
                'incident_id' => (string)($context['incident_id'] ?? ''),
                'submission_committed' => !empty($context['submission_committed']),
                'exception' => get_class($e),
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } catch (\Throwable $ignored) {}
    }

    private static function savepoint(string $name): string {
        global $wpdb;
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $name) ?: 'fieldflow_stage';
        try { $wpdb->query('SAVEPOINT ' . $name); } catch (\Throwable $ignored) {}
        return $name;
    }

    private static function rollback_to_savepoint(string $name): void {
        global $wpdb;
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $name);
        if ($name === '') return;
        try { $wpdb->query('ROLLBACK TO SAVEPOINT ' . $name); } catch (\Throwable $ignored) {}
    }

    private static function run_non_blocking(string $label, callable $callback, array $context = []): void {
        try {
            $callback();
        } catch (\Throwable $e) {
            if (class_exists('\\RoutesPro\\Support\\Logger')) {
                try {
                    $context['context_key'] = $context['context_key'] ?? 'form_post_commit';
                    \RoutesPro\Support\Logger::captureThrowable($label, $e, $context);
                } catch (\Throwable $ignored) {}
            }
        }
    }

    public static function handle_submit() {
        self::$offlineMutationId = '';
        self::$uploadErrors = [];
        self::$resumableMediaRefs = [];
        self::set_submission_stage('authorisation');
        $is_ajax = !empty($_POST['routespro_ajax']);
        if (!is_user_logged_in()) { self::finish_submit_error('sem_login', $is_ajax); }

        $form_id = isset($_POST['routespro_form_id']) ? absint($_POST['routespro_form_id']) : 0;
        $form = self::get_form($form_id);
        if (!$form || ($form['status'] ?? '') !== 'active') { self::finish_submit_error('form', $is_ajax); }

        $nonce = isset($_POST[self::NONCE_FIELD]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])) : '';
        $nonce_valid = $nonce !== '' && (bool) wp_verify_nonce($nonce, self::NONCE_ACTION);
        if (!$nonce_valid && $is_ajax) {
            $rest_nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_X_WP_NONCE'])) : '';
            $nonce_valid = $rest_nonce !== '' && (bool) wp_verify_nonce($rest_nonce, 'wp_rest');
        }
        $offline_replay = !empty($_POST['routespro_offline_replay']);
        if (!$nonce_valid && $offline_replay && class_exists('\RoutesPro\Mobile\OfflineSyncService')) {
            $offline_context = [
                'client_id' => isset($_POST['routespro_client_id']) ? absint($_POST['routespro_client_id']) : 0,
                'project_id' => isset($_POST['routespro_project_id']) ? absint($_POST['routespro_project_id']) : 0,
                'route_id' => isset($_POST['routespro_route_id']) ? absint($_POST['routespro_route_id']) : 0,
                'route_stop_id' => isset($_POST['routespro_stop_id']) ? absint($_POST['routespro_stop_id']) : 0,
                'location_id' => isset($_POST['routespro_location_id']) ? absint($_POST['routespro_location_id']) : 0,
            ];
            $offline_day = isset($_POST['routespro_report_day']) ? sanitize_text_field((string)wp_unslash($_POST['routespro_report_day'])) : '';
            $offline_token = isset($_POST['routespro_offline_token']) ? sanitize_text_field((string)wp_unslash($_POST['routespro_offline_token'])) : '';
            $nonce_valid = \RoutesPro\Mobile\OfflineSyncService::verifyFormToken($offline_token, $form_id, $offline_context, get_current_user_id(), $offline_day);
        }
        if (!$nonce_valid) { self::finish_submit_error('nonce', $is_ajax); }

        if ($offline_replay && class_exists('\RoutesPro\Mobile\MutationStore')) {
            self::$offlineMutationId = isset($_POST['routespro_offline_mutation_id'])
                ? sanitize_text_field((string)wp_unslash($_POST['routespro_offline_mutation_id']))
                : '';
            if (self::$offlineMutationId !== '') {
                $reservation = \RoutesPro\Mobile\MutationStore::beginExternal(self::$offlineMutationId, 'web_form_submission');
                if ($reservation !== true) self::finishOfflineReplay($reservation);
            }
        }

        $offline_trust_status = ['present'=>false,'valid'=>false,'state'=>'not_supplied','protocol'=>'fieldflow-offline-trust-v1'];
        $offline_media_integrity = ['present'=>false,'verified'=>false,'protocol'=>'fieldflow-media-integrity-v1','file_count'=>0,'hashed_files'=>0,'total_bytes'=>0];
        if ($offline_replay) {
            $offline_trust_status = self::offline_trust_status();
            if (class_exists('\RoutesPro\Mobile\ResumableMediaService')) {
                $resolved_refs = \RoutesPro\Mobile\ResumableMediaService::resolve_posted_refs();
                if (is_wp_error($resolved_refs)) {
                    $error_data = is_array($resolved_refs->get_error_data()) ? $resolved_refs->get_error_data() : [];
                    self::finish_submit_error((string)$resolved_refs->get_error_code(), $is_ajax, array_merge($error_data, [
                        'message' => (string)$resolved_refs->get_error_message(),
                    ]), (int)($error_data['status'] ?? 422));
                }
                if (is_array($resolved_refs)) self::$resumableMediaRefs = $resolved_refs;
            }
            $media_integrity_result = self::validate_offline_media_manifest();
            if (is_wp_error($media_integrity_result)) {
                $error_data = is_array($media_integrity_result->get_error_data()) ? $media_integrity_result->get_error_data() : [];
                self::finish_submit_error((string)$media_integrity_result->get_error_code(), $is_ajax, array_merge($error_data, [
                    'message' => (string)$media_integrity_result->get_error_message(),
                ]), (int)($error_data['status'] ?? 422));
            }
            if (is_array($media_integrity_result)) $offline_media_integrity = $media_integrity_result;
        }

        self::set_submission_stage('context_resolution');
        $binding_id = isset($_POST['routespro_binding_id']) ? absint($_POST['routespro_binding_id']) : 0;
        $normalized = SubmissionContext::normalize_for_submission([
            'client_id' => isset($_POST['routespro_client_id']) ? absint($_POST['routespro_client_id']) : 0,
            'project_id' => isset($_POST['routespro_project_id']) ? absint($_POST['routespro_project_id']) : 0,
            'route_id' => isset($_POST['routespro_route_id']) ? absint($_POST['routespro_route_id']) : 0,
            'route_stop_id' => isset($_POST['routespro_stop_id']) ? absint($_POST['routespro_stop_id']) : 0,
            'location_id' => isset($_POST['routespro_location_id']) ? absint($_POST['routespro_location_id']) : 0,
            'context_date' => isset($_POST['routespro_context_date']) ? sanitize_text_field((string)wp_unslash($_POST['routespro_context_date'])) : '',
        ], $form_id, $binding_id, get_current_user_id());
        if (is_wp_error($normalized)) { self::finish_submit_error((string)$normalized->get_error_code(), $is_ajax); }

        $context = (array)($normalized['context'] ?? []);
        $binding = is_array($normalized['binding'] ?? null) ? $normalized['binding'] : null;
        $binding_id = (int)($binding['id'] ?? $binding_id);
        $owner_user_id = (int)($normalized['owner_user_id'] ?? 0);
        $report_day = DailySubmissionService::normalise_report_day(
            $normalized['route_date']
                ?? ($_POST['routespro_report_day'] ?? '')
                ?? ($_POST['routespro_context_date'] ?? '')
                ?? current_time('Y-m-d')
        );

        $submit_context = [
            'client_id' => (int)($context['client_id'] ?? 0),
            'project_id' => (int)($context['project_id'] ?? 0),
            'route_id' => (int)($context['route_id'] ?? 0),
            'stop_id' => (int)($context['route_stop_id'] ?? 0),
            'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
            'location_id' => (int)($context['location_id'] ?? 0),
            'date' => $report_day,
            'route_date' => $report_day,
            'form_id' => $form_id,
        ];

        self::set_submission_stage('schema_build');
        // Web submit uses the same schema assembly service as render and mobile.
        $schema = SubmissionApplicationService::buildSchema($form, $submit_context);
        if (empty($schema['questions'])) { self::finish_submit_error('schema', $is_ajax); }
        $offline_schema_rebased = false;
        $offline_schema_compatibility = [];
        if ($offline_replay) {
            $base_revision = isset($_POST['routespro_offline_base_revision'])
                ? sanitize_text_field((string)wp_unslash($_POST['routespro_offline_base_revision']))
                : '';
            $server_schema_hash = self::schema_hash($schema);
            $server_revision = substr($server_schema_hash, 0, 20);
            $client_schema_hash = isset($_POST['routespro_schema_hash']) ? sanitize_text_field((string)wp_unslash($_POST['routespro_schema_hash'])) : '';
            $schema_changed = ($base_revision !== '' && !hash_equals($base_revision, $server_revision))
                || ($client_schema_hash !== '' && !hash_equals($client_schema_hash, $server_schema_hash));
            if ($schema_changed) {
                $offline_schema_compatibility = self::offline_replay_schema_compatibility($schema);
                if (!empty($offline_schema_compatibility['compatible'])) {
                    $offline_schema_rebased = true;
                    self::add_submission_warning('O formulário foi atualizado enquanto o dispositivo esteve offline. As respostas compatíveis foram preservadas e sincronizadas com a versão atual.');
                } else {
                    self::finish_submit_error('fieldflow_offline_conflict', $is_ajax, [
                        'message' => 'O formulário mudou enquanto o dispositivo esteve offline e existem respostas para perguntas removidas ou renomeadas. A cópia local foi mantida para recuperação.',
                        'conflict' => [
                            'type' => 'form_schema_changed',
                            'entity_type' => 'form',
                            'entity_id' => $form_id,
                            'base_revision' => $base_revision,
                            'server_revision' => $server_revision,
                            'client_schema_hash' => $client_schema_hash,
                            'server_schema_hash' => $server_schema_hash,
                            'unknown_question_keys' => array_slice((array)($offline_schema_compatibility['unknown_question_keys'] ?? []), 0, 30),
                        ],
                    ], 409);
                }
            }
        }

        self::set_submission_stage('draft_activation');
        if (class_exists('\\RoutesPro\\Forms\\DraftService')) {
            try {
                DraftService::activate_for_submission([
                    'form_id' => $form_id,
                    'binding_id' => $binding_id,
                    'client_id' => (int)($context['client_id'] ?? 0),
                    'project_id' => (int)($context['project_id'] ?? 0),
                    'route_id' => (int)($context['route_id'] ?? 0),
                    'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
                    'location_id' => (int)($context['location_id'] ?? 0),
                    'report_day' => $report_day,
                ], substr(hash('sha256', (string)wp_json_encode($schema)), 0, 20));
            } catch (\Throwable $e) {
                self::log_submission_throwable('Falha não bloqueante ao ativar o rascunho do formulário.', $e, [
                    'context_key' => 'form_draft_activation',
                    'route_id' => (int)($context['route_id'] ?? 0),
                ]);
                self::add_submission_warning('O rascunho do servidor não pôde ser ligado, mas as respostas visíveis foram processadas.');
            }
        }

        $daily_existing = DailySubmissionService::find_existing(
            $form_id,
            (int)($context['location_id'] ?? 0),
            $report_day
        );
        $requested_existing_id = isset($_POST['routespro_existing_submission_id']) ? absint($_POST['routespro_existing_submission_id']) : 0;
        $requested_edit = !empty($_POST['routespro_edit_existing']);
        $is_update = false;

        if ($daily_existing) {
            $editable = DailySubmissionService::can_edit($daily_existing, get_current_user_id());
            if (!$requested_edit || $requested_existing_id !== (int)$daily_existing['id']) {
                self::finish_submit_error('daily_report_exists', $is_ajax, [
                    'submission_id' => (int)$daily_existing['id'],
                    'editable' => $editable,
                    'report_day' => $report_day,
                ], 409);
            }
            if (!$editable) {
                self::finish_submit_error('daily_report_edit_forbidden', $is_ajax, [
                    'submission_id' => (int)$daily_existing['id'],
                    'report_day' => $report_day,
                ], 403);
            }
            $context_conflict = DailySubmissionService::edit_context_conflict($daily_existing, $context);
            if ($context_conflict) {
                self::finish_submit_error('daily_report_context_conflict', $is_ajax, [
                    'submission_id' => (int)$daily_existing['id'],
                    'report_day' => $report_day,
                    'conflict' => $context_conflict,
                    'original_context' => DailySubmissionService::original_context($daily_existing),
                ], 409);
            }
            $is_update = true;
        } elseif ($requested_edit || $requested_existing_id > 0) {
            self::finish_submit_error('daily_report_missing', $is_ajax, ['report_day' => $report_day], 409);
        }

        $saas_client_id = (int)($context['client_id'] ?? 0);
        if ($saas_client_id > 0 && class_exists('\RoutesPro\SaaS\EntitlementService')) {
            if (!\RoutesPro\SaaS\EntitlementService::clientHas($saas_client_id, 'reporting.submit')) {
                self::finish_submit_error('plan_reporting_disabled', $is_ajax, ['client_id' => $saas_client_id], 403);
            }
            if (!$is_update && class_exists('\RoutesPro\SaaS\UsageService')) {
                $quota = \RoutesPro\SaaS\UsageService::canConsume($saas_client_id, 'submissions_monthly', 1);
                if (empty($quota['allowed'])) {
                    self::finish_submit_error('plan_submission_limit', $is_ajax, $quota, 429);
                }
            }
        }

        self::set_submission_stage('history_prefill');
        try {
            $record_state = RecordService::get_record_state(
                $form_id,
                (int)($context['client_id'] ?? 0),
                (int)($context['project_id'] ?? 0),
                (int)($context['location_id'] ?? 0)
            );
        } catch (\Throwable $e) {
            $record_state = ['record' => null, 'version' => null, 'prefill' => []];
            self::log_submission_throwable('Falha não bloqueante ao ler o histórico da loja.', $e, [
                'context_key' => 'form_history_prefill',
                'route_id' => (int)($context['route_id'] ?? 0),
                'location_id' => (int)($context['location_id'] ?? 0),
            ]);
            self::add_submission_warning('O histórico anterior da loja não pôde ser lido, mas o novo reporte será guardado.');
        }
        $existing_prefill = is_array($record_state['prefill'] ?? null) ? $record_state['prefill'] : [];
        if (!empty($record_state['record']['id'])) {
            $existing_prefill = RecordService::prepare_prefill_for_schema((int)$record_state['record']['id'], $schema);
        }
        $existing_prefill = []; // 9BH: no generic-record fallback in submission extraction.
        if (!$daily_existing && !empty($context['location_id'])) {
            try {
                $previous_submission = DailySubmissionService::find_previous_operational(
                    $form_id,
                    (int)$context['location_id'],
                    $report_day,
                    (int)($context['client_id'] ?? 0),
                    (int)($context['project_id'] ?? 0),
                    true
                );
                if ($previous_submission) {
                    $previous_prefill = DailySubmissionService::submission_prefill((int)$previous_submission['id'], $schema);
                    $existing_prefill = DailySubmissionService::new_visit_autofill_prefill($previous_prefill, $schema);
                }
            } catch (\Throwable $e) {
                self::log_submission_throwable('Non-blocking failure while resolving the previous operational visit.', $e, [
                    'context_key' => 'form_previous_visit_prefill',
                    'location_id' => (int)($context['location_id'] ?? 0),
                    'project_id' => (int)($context['project_id'] ?? 0),
                ]);
            }
        }
        if ($daily_existing) {
            try {
                $daily_prefill = DailySubmissionService::submission_prefill((int)$daily_existing['id'], $schema);
                $existing_prefill = $daily_prefill;
            } catch (\Throwable $e) {
                self::log_submission_throwable('Falha não bloqueante ao recuperar as respostas do reporte diário.', $e, [
                    'context_key' => 'form_daily_prefill',
                    'submission_id' => (int)$daily_existing['id'],
                    'route_id' => (int)($context['route_id'] ?? 0),
                ]);
                self::add_submission_warning('As respostas do reporte anterior não puderam ser recuperadas automaticamente.');
            }
        }

        self::set_submission_stage('answer_extraction');
        $answers = [];
        $cardex_override_payloads = [];
        $manual_recovery = !empty(self::$manualRecoveryContext);
        // Mirror the browser's conditional visibility before required checks.
        // Hidden required targets must never block a complete visible form.
        $conditional_visibility = self::conditional_visibility_for_submission($schema, $existing_prefill);
        foreach ($schema['questions'] as $question) {
            if (!is_array($question)) continue;
            $key = sanitize_key($question['key'] ?? '');
            if (!$key) continue;
            $type = sanitize_key($question['type'] ?? 'text');
            if (in_array($type, ['briefing_block', 'briefing_intro'], true)) continue;
            if (in_array($type, ['product_matrix','order_suggestion'], true)
                && class_exists('\\RoutesPro\\Forms\\ProductCardex')
                && ProductCardex::question_uses_cardex($question)
                && !ProductCardex::question_is_available_for_context($question, $submit_context)) {
                // 9DS: update replaces the entire answer set. A temporarily
                // unavailable Cardex must never delete this visit's stored fronts.
                if ($daily_existing && array_key_exists($key, $existing_prefill)) {
                    $answers[$key] = [
                        'type' => $type,
                        'value' => $existing_prefill[$key],
                        'label' => sanitize_text_field((string)($question['label'] ?? $key)),
                    ];
                }
                self::add_submission_warning('Uma pergunta dependente do Cardex nao pode sincronizar. As respostas existentes desta visita foram preservadas.');
                continue;
            }
            if (!SubmissionValidation::is_question_active($key, $conditional_visibility)) {
                continue;
            }
            $required = !empty($question['required']);
            try {
                $value = self::extract_value_from_request($key, $type, $question, $submit_context, $existing_prefill[$key] ?? null);
            } catch (\Throwable $e) {
                self::log_submission_throwable('Falha ao processar uma resposta do formulário.', $e, [
                    'context_key' => 'form_answer_extraction',
                    'question_key' => $key,
                    'question_type' => $type,
                    'route_id' => (int)($context['route_id'] ?? 0),
                ]);
                if ($manual_recovery && in_array($type, ['image_upload','file_upload','categorized_image_upload'], true)) {
                    throw $e;
                }
                if (array_key_exists($key, $existing_prefill)) {
                    $value = $existing_prefill[$key];
                    self::add_submission_warning('Uma resposta histórica foi preservada durante a gravação.');
                } elseif (!$required || $manual_recovery) {
                    $value = '';
                    self::add_submission_warning($manual_recovery
                        ? 'Um campo incompleto do rascunho foi preservado como vazio durante a recuperação administrativa.'
                        : 'Um campo opcional não pôde ser processado e foi ignorado.');
                } else {
                    throw $e;
                }
            }
            if (class_exists(AnswerSemantics::class)) {
                $semantic = AnswerSemantics::normalize($question, $value, true);
                if (empty($semantic['valid'])) {
                    self::finish_submit_error('invalid_answer_semantics', $is_ajax, [
                        'field' => $key,
                        'question_type' => $type,
                        'issue' => sanitize_key((string)($semantic['issue'] ?? 'invalid_answer')),
                        'message' => sanitize_text_field((string)($semantic['message'] ?? 'Resposta invalida.')),
                        'semantic_contract' => AnswerSemantics::CONTRACT,
                    ], 422);
                }
                $value = $semantic['value'] ?? null;
            }
            if (!empty(self::$uploadErrors[$key])) {
                self::finish_submit_error('upload_failed', $is_ajax, [
                    'field' => $key,
                    'message' => implode(' ', array_values(array_unique(self::$uploadErrors[$key]))),
                ], 422);
            }
            $is_cardex_matrix_present = false;
            if ($type === 'product_matrix'
                && class_exists('\RoutesPro\Forms\ProductCardex')
                && ProductCardex::question_uses_cardex($question)
                && !empty($_POST[$key . '__cardex_present'])) {
                $cardex_id = ProductCardex::question_cardex_id($question, $submit_context);
                if ($cardex_id) {
                    $is_cardex_matrix_present = true;
                    $cardex_override_payloads[$cardex_id] = [
                        'question' => $question,
                        'rows' => is_array($value) ? $value : [],
                    ];
                }
            }
            if (self::is_empty_value($value, $type)
                && in_array($type, ['image_upload','file_upload'], true)
                && !empty($existing_prefill[$key])
                && !self::existing_upload_removal_requested($key)) {
                $value = $existing_prefill[$key];
            }
            if (!$manual_recovery && $type === 'categorized_image_upload' && self::categorized_upload_has_missing_selection($key, $value, $required)) {
                self::finish_required_field_error($key, $question, $is_ajax, 'Seleciona pelo menos uma categoria e adiciona a evidência pedida.');
            }
            if (!$manual_recovery && $type === 'share_space_matrix' && $required && !\RoutesPro\Briefings\ShareSpaceMatrix::isComplete(is_array($value) ? $value : [], (array)($question['question_config'] ?? []))) {
                self::finish_required_field_error($key, $question, $is_ajax, 'Completa todos os comparativos obrigatórios. Quando não existirem frentes, introduz 0 para registar esse valor explicitamente.');
            }
            if (!$manual_recovery && SubmissionValidation::required_missing($question, $value, $conditional_visibility)) {
                self::finish_required_field_error($key, $question, $is_ajax);
            }
            if (!$is_cardex_matrix_present && !SubmissionValidation::should_persist($value, $type, true)) continue;
            $answers[$key] = [
                'type' => $type,
                'value' => $is_cardex_matrix_present && !is_array($value) ? [] : $value,
                'label' => sanitize_text_field($question['label'] ?? $key),
            ];
        }

        if (!$manual_recovery) {
            $client_media_count = isset($_POST['routespro_media_file_count']) ? max(0, (int)wp_unslash($_POST['routespro_media_file_count'])) : 0;
            if ($client_media_count > 0) {
                $server_media = self::server_upload_metrics();
                $server_media_count = max(0, (int)($server_media['file_count'] ?? 0));
                if ($server_media_count < $client_media_count) {
                    self::finish_submit_error('upload_count_mismatch', $is_ajax, [
                        'expected_files' => $client_media_count,
                        'received_files' => $server_media_count,
                        'message' => sprintf(
                            'Foram selecionados %d ficheiros, mas o servidor recebeu apenas %d. O reporte não foi gravado para evitar perda de fotografias. Mantém a app aberta, confirma a ligação e tenta novamente.',
                            $client_media_count,
                            $server_media_count
                        ),
                    ], 422);
                }
            }
        }

        if (!$manual_recovery && class_exists('\\RoutesPro\\Forms\\EvidenceFlow')) {
            $evidence_error = EvidenceFlow::validate_answers($schema, $answers);
            if (is_wp_error($evidence_error)) {
                $evidence_data = is_array($evidence_error->get_error_data()) ? $evidence_error->get_error_data() : [];
                self::finish_submit_error((string)$evidence_error->get_error_code(), $is_ajax, array_merge($evidence_data, [
                    'message' => (string)$evidence_error->get_error_message(),
                ]), (int)($evidence_data['status'] ?? 422));
            }
        }

        if (!$manual_recovery && class_exists('\\RoutesPro\\Forms\\SpecializedValidation')) {
            $specialized_error = SpecializedValidation::validate_schema($schema, $answers, $conditional_visibility);
            if (is_wp_error($specialized_error)) {
                $specialized_data = is_array($specialized_error->get_error_data()) ? $specialized_error->get_error_data() : [];
                self::finish_submit_error((string)$specialized_error->get_error_code(), $is_ajax, array_merge($specialized_data, [
                    'message' => (string)$specialized_error->get_error_message(),
                ]), (int)($specialized_data['status'] ?? 422));
            }
        }

        $saved_at = !empty(self::$manualRecoveryContext['completed_at'])
            ? sanitize_text_field((string)self::$manualRecoveryContext['completed_at'])
            : current_time('mysql');
        $server_schema_hash = isset($server_schema_hash) ? $server_schema_hash : self::schema_hash($schema);
        $server_revision = isset($server_revision) ? $server_revision : substr($server_schema_hash, 0, 20);
        $server_form_version = self::definition_version($form);
        $client_form_version = isset($_POST['routespro_form_version']) ? sanitize_text_field((string)wp_unslash($_POST['routespro_form_version'])) : '';
        $client_schema_hash = isset($_POST['routespro_schema_hash']) ? sanitize_text_field((string)wp_unslash($_POST['routespro_schema_hash'])) : '';
        $form_downloaded_at = isset($_POST['routespro_form_downloaded_at']) ? sanitize_text_field((string)wp_unslash($_POST['routespro_form_downloaded_at'])) : '';
        $base_meta = [
            'source_url' => esc_url_raw(wp_get_referer() ?: ''),
            'context_model' => 'record_version_v1',
            'previous_record_id' => isset($_POST['routespro_record_id']) ? absint($_POST['routespro_record_id']) : 0,
            'previous_version_id' => isset($_POST['routespro_record_version_id']) ? absint($_POST['routespro_record_version_id']) : 0,
            'route_date' => $report_day,
            'schema_revision' => $server_revision,
            'form_definition' => [
                'server_version' => $server_form_version,
                'client_version' => $client_form_version,
                'server_schema_hash' => $server_schema_hash,
                'client_schema_hash' => $client_schema_hash,
                'downloaded_at' => $form_downloaded_at,
                'offline_replay' => $offline_replay ? 1 : 0,
                'version_match' => $client_form_version === '' || hash_equals($server_form_version, $client_form_version),
                'schema_match' => $client_schema_hash === '' || hash_equals($server_schema_hash, $client_schema_hash),
                'offline_schema_rebased' => !empty($offline_schema_rebased) ? 1 : 0,
            ],
            'submission_context' => [
                'client_id' => (int)($context['client_id'] ?? 0),
                'project_id' => (int)($context['project_id'] ?? 0),
                'route_id' => (int)($context['route_id'] ?? 0),
                'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
                'location_id' => (int)($context['location_id'] ?? 0),
            ],
        ];
        if (class_exists('\RoutesPro\Forms\ConditionalFlow')) {
            $base_meta['conditional_flow'] = ConditionalFlow::submission_meta($schema, $conditional_visibility);
        }
        $location_snapshot = self::submission_location_snapshot($context);
        if ($location_snapshot) $base_meta['location_snapshot'] = $location_snapshot;
        if (!empty(self::$manualRecoveryContext)) {
            $base_meta['manual_recovery'] = [
                'draft_id' => absint(self::$manualRecoveryContext['draft_id'] ?? 0),
                'recovered_by' => absint(self::$manualRecoveryContext['actor_user_id'] ?? 0),
                'original_user_id' => absint(self::$manualRecoveryContext['owner_user_id'] ?? get_current_user_id()),
                'recovered_at' => sanitize_text_field((string)(self::$manualRecoveryContext['recovered_at'] ?? current_time('mysql'))),
                'completed_at' => sanitize_text_field((string)(self::$manualRecoveryContext['completed_at'] ?? $saved_at)),
                'reason' => sanitize_textarea_field((string)(self::$manualRecoveryContext['reason'] ?? '')),
                'expected_files' => absint(self::$manualRecoveryContext['expected_files'] ?? 0),
                'verified_files' => absint(self::$manualRecoveryContext['verified_files'] ?? 0),
                'allow_incomplete' => 1,
            ];
        }
        self::set_submission_stage('submission_application_persist');
        $application_result = SubmissionApplicationService::persist([
            'form_id' => $form_id,
            'binding_id' => $binding_id,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'owner_user_id' => $owner_user_id,
            'report_day' => $report_day,
            'saved_at' => $saved_at,
            'base_meta' => $base_meta,
            'daily_existing' => $daily_existing,
            'is_update' => $is_update,
            'answers' => $answers,
            'channel' => 'web',
            'commit_after_persist' => true,
        ]);
        if (is_wp_error($application_result)) {
            self::finish_application_service_error($application_result, $is_ajax, $is_update, $report_day);
        }

        $submission_id = (int)($application_result['submission_id'] ?? 0);
        $original_submitted_at = (string)($application_result['original_submitted_at'] ?? $saved_at);
        $audit_before = $application_result['audit_before'] ?? null;
        $meta = is_array($application_result['meta'] ?? null) ? $application_result['meta'] : $base_meta;
        $meta_json = (string)($application_result['meta_json'] ?? wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Fast path: the durable submission and all answers are confirmed before
        // cardex, route and analytical integrations. This keeps database locks short
        // and prevents a slow secondary service from rolling back field data.
        $synced = ['record_id' => 0, 'version_id' => 0, 'version_no' => 0, 'deferred' => true];
        $visit_completion = [
            'applied' => false,
            'reason' => 'pending_after_commit',
            'route_id' => (int)($context['route_id'] ?? 0),
            'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
            'location_id' => (int)($context['location_id'] ?? 0),
            'route_completed_now' => false,
        ];

        self::set_submission_stage('transaction_commit');
        self::$submissionCommitted = true;
        self::$uploadedPaths = [];
        self::$committedResponse = [
            'submission_id' => $submission_id,
            'record_id' => 0,
            'record_version_id' => 0,
            'visit_completion' => $visit_completion,
            'updated_existing' => $is_update,
            'report_day' => $report_day,
            'saved_at' => $saved_at,
            'warnings' => self::$submissionWarnings,
            'message' => $is_update ? 'Reporte atualizado com sucesso.' : 'Reporte gravado com sucesso.',
        ];

        $application_state = [
            'submission_id' => $submission_id,
            'form_id' => $form_id,
            'binding_id' => $binding_id,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'owner_user_id' => $owner_user_id,
            'original_submitted_at' => $original_submitted_at,
            'saved_at' => $saved_at,
            'report_day' => $report_day,
            'is_update' => $is_update,
            'meta_json' => $meta_json,
            'answers' => $answers,
            'audit_before' => $audit_before,
            'has_cardex_override' => !empty($cardex_override_payloads),
        ];
        $submission_payload = SubmissionApplicationService::buildSubmissionPayload($application_state);
        $pipeline_state = class_exists('\RoutesPro\Forms\SubmissionPipeline')
            ? SubmissionPipeline::requestState('web', array_merge($context, ['user_id' => get_current_user_id()]))
            : ['owner' => 'legacy'];

        if (($pipeline_state['owner'] ?? 'legacy') === 'pipeline') {
            self::set_submission_stage('submission_pipeline_post_commit');
            try {
                $pipeline_result = SubmissionPipeline::afterCommit(
                    SubmissionApplicationService::buildPipelineJob(array_merge($application_state, [
                        'source' => 'frontend',
                        'history_mode' => 'inline',
                    ]))
                );
                $pipeline_record = is_array($pipeline_result['record'] ?? null) ? $pipeline_result['record'] : [];
                $synced = [
                    'record_id' => (int)($pipeline_record['record_id'] ?? 0),
                    'version_id' => (int)($pipeline_record['version_id'] ?? 0),
                    'version_no' => (int)($pipeline_record['version_no'] ?? 0),
                    'deferred' => !empty($pipeline_record['deferred']),
                ];
                $visit_completion = is_array($pipeline_result['visit_completion'] ?? null)
                    ? $pipeline_result['visit_completion'] : $visit_completion;
                foreach ((array)($pipeline_result['warnings'] ?? []) as $warning) self::add_submission_warning((string)$warning);
            } catch (\Throwable $pipeline_error) {
                $pipeline_state['owner'] = 'legacy';
                self::log_submission_throwable('Falha protegida na Submission Pipeline web. O fluxo pós-commit estável assumiu a execução.', $pipeline_error, [
                    'context_key' => 'submission_pipeline_web_fallback',
                    'submission_id' => $submission_id,
                    'route_id' => (int)($context['route_id'] ?? 0),
                ]);
            }
        }

        if (($pipeline_state['owner'] ?? 'legacy') !== 'pipeline') {

        // O fluxo legacy também reutiliza a mesma submissão em edições do mesmo
        // dia. Limpar a ligação antiga impede o worker de considerar a versão
        // anterior como se já representasse as respostas acabadas de gravar.
        if ($is_update && class_exists('\RoutesPro\Forms\SubmissionPipeline')) {
            if (!SubmissionPipeline::prepareUpdateReplay($submission_id)) {
                self::add_submission_warning('A atualização ficou guardada, mas o novo histórico analítico aguarda reconciliação.');
            }
        }



        // 9BD: project store memory immediately after the durable submission commit.
        // The scheduled worker remains a fallback only, never the primary source.
        try {
            $history_row = array_merge($submission_payload, [
                'owner_user_id' => $owner_user_id,
                'submitted_at' => $saved_at,
                'meta_json' => $meta_json,
            ]);
            $history_sync = RecordService::sync_submission($submission_id, $history_row, $answers);
            if (is_wp_error($history_sync)) {
                throw new \RuntimeException($history_sync->get_error_code() . ': ' . $history_sync->get_error_message());
            }
            $history_linked = $wpdb->update(self::table_submissions(), [
                'record_id' => (int)($history_sync['record_id'] ?? 0),
                'record_version_id' => (int)($history_sync['version_id'] ?? 0),
            ], ['id' => $submission_id], ['%d', '%d'], ['%d']);
            if ($history_linked === false) {
                throw new \RuntimeException($wpdb->last_error ?: 'Failed to link the store-memory version.');
            }
            $synced = array_merge($history_sync, ['deferred' => false]);
        } catch (\Throwable $history_error) {
            $synced['deferred'] = true;
            self::log_submission_throwable('Non-blocking failure while projecting store memory after commit.', $history_error, [
                'context_key' => 'form_record_history_inline',
                'submission_id' => $submission_id,
                'location_id' => (int)($context['location_id'] ?? 0),
            ]);
            self::add_submission_warning('Store memory will be reconciled after the report is saved.');
        }

        // Cardex/store assortment reconciliation is intentionally deferred. It can
        // touch several tables and should never extend the user's upload request.
        if ($cardex_override_payloads) {
            $cardex_args = [$submission_id];
            if (!wp_next_scheduled('fieldflow_reconcile_submission_cardex', $cardex_args)) {
                $scheduled = wp_schedule_single_event(time() + 2, 'fieldflow_reconcile_submission_cardex', $cardex_args);
                if ($scheduled === false || is_wp_error($scheduled)) {
                    self::add_submission_warning('O sortido específico da loja ficou pendente de reconciliação.');
                }
            }
        }

        self::set_submission_stage('visit_completion_post_commit');
        try {
            $visit_completion = \RoutesPro\Services\FormVisitCompletionService::complete_from_submission($submission_id, [
                'form_id' => $form_id,
                'route_id' => (int)($context['route_id'] ?? 0),
                'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
                'location_id' => (int)($context['location_id'] ?? 0),
                'user_id' => get_current_user_id(),
                'submitted_at' => $saved_at,
            ]);
            if (is_wp_error($visit_completion)) throw new \RuntimeException($visit_completion->get_error_code() . ': ' . $visit_completion->get_error_message());
        } catch (\Throwable $e) {
            $visit_completion = [
                'applied' => false,
                'reason' => 'deferred_after_submission',
                'route_id' => (int)($context['route_id'] ?? 0),
                'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
                'location_id' => (int)($context['location_id'] ?? 0),
                'route_completed_now' => false,
            ];
            self::log_submission_throwable('Falha não bloqueante na conclusão da visita.', $e, [
                'context_key' => 'form_visit_completion_deferred',
                'submission_id' => $submission_id,
                'route_id' => (int)($context['route_id'] ?? 0),
            ]);
            self::add_submission_warning('A visita será reconciliada com a rota depois da gravação do reporte.');
        }

        if (!empty($synced['deferred'])) {
            $args = [$submission_id];
            if (!wp_next_scheduled('fieldflow_reconcile_submission_history', $args)) {
                $scheduled = wp_schedule_single_event(time() + 1, 'fieldflow_reconcile_submission_history', $args);
                if ($scheduled === false || is_wp_error($scheduled)) {
                    self::add_submission_warning('O histórico analítico da loja ficou pendente de reconciliação.');
                }
            }
        }
        if (empty($visit_completion['applied']) && !empty($context['route_id'])) {
            $args = [(int)$context['route_id']];
            if (!wp_next_scheduled('fieldflow_reconcile_route_after_submission', $args)) {
                $scheduled = wp_schedule_single_event(time() + 5, 'fieldflow_reconcile_route_after_submission', $args);
                if ($scheduled === false || is_wp_error($scheduled)) {
                    self::add_submission_warning('A reconciliação da rota ficou pendente.');
                }
            }
        }
        }

        self::$committedResponse['visit_completion'] = $visit_completion;
        self::$committedResponse['warnings'] = self::$submissionWarnings;

        self::set_submission_stage('draft_cleanup_schedule');
        if (class_exists('\RoutesPro\Forms\DraftService')) {
            try {
                if (!DraftService::schedule_active_draft_deletion(get_current_user_id())) {
                    self::add_submission_warning('A limpeza do rascunho ficou pendente.');
                }
            } catch (\Throwable $e) {
                self::log_submission_throwable('Falha não bloqueante ao agendar a limpeza do rascunho submetido.', $e, [
                    'context_key' => 'form_draft_cleanup_schedule',
                    'submission_id' => $submission_id,
                    'route_id' => (int)($context['route_id'] ?? 0),
                ]);
            }
        }

        if (class_exists('\\RoutesPro\\Security\\MediaRegistry')) {
            self::run_non_blocking('Falha não bloqueante ao observar media da submissão.', static function() use ($submission_id, $answers, $context): void {
                \RoutesPro\Security\MediaRegistry::registerSubmissionAnswers($submission_id, $answers, [
                    'client_id' => (int)($context['client_id'] ?? 0),
                    'project_id' => (int)($context['project_id'] ?? 0),
                    'route_id' => (int)($context['route_id'] ?? 0),
                    'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
                    'location_id' => (int)($context['location_id'] ?? 0),
                ], get_current_user_id());
            }, ['context_key' => 'media_registry_observe', 'submission_id' => $submission_id]);
        }

        if (($pipeline_state['owner'] ?? 'legacy') !== 'pipeline') {
        $integration_args = [
            $submission_id,
            $is_update ? 1 : 0,
            wp_json_encode($visit_completion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        if (!wp_next_scheduled('fieldflow_submission_post_commit', $integration_args)) {
            $scheduled = wp_schedule_single_event(time() + 5, 'fieldflow_submission_post_commit', $integration_args);
            if ($scheduled === false || is_wp_error($scheduled)) {
                self::add_submission_warning('As automações posteriores ao reporte ficaram pendentes de execução.');
            }
        }

        if (class_exists('\RoutesPro\Core\AuditLog')) {
            self::run_non_blocking('Falha ao registar auditoria posterior à submissão.', static function() use ($submission_id, $submission_payload, $answers, $audit_before, $context, $is_update, $synced): void {
                $contract = \RoutesPro\Core\DataContract::submission($submission_id, $submission_payload, $answers);
                \RoutesPro\Core\AuditLog::record('submission.persisted', 'submission', $submission_id, $audit_before, $contract, [
                    'client_id' => (int)($context['client_id'] ?? 0),
                    'project_id' => (int)($context['project_id'] ?? 0),
                    'route_id' => (int)($context['route_id'] ?? 0),
                    'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
                    'location_id' => (int)($context['location_id'] ?? 0),
                    'submission_id' => $submission_id,
                    'actor_user_id' => get_current_user_id(),
                    'source' => 'frontend',
                ], ['is_update' => $is_update, 'record_id' => (int)($synced['record_id'] ?? 0), 'record_version_id' => (int)($synced['version_id'] ?? 0)]);
            }, ['context_key' => 'form_submission_audit', 'route_id' => (int)($context['route_id'] ?? 0)]);
        }
        }
        self::set_submission_stage('response_build');
        $success_message = $is_update
            ? 'Reporte atualizado com sucesso. Mantém-se um único reporte para esta loja neste dia.'
            : (!empty($visit_completion['applied'])
                ? 'Reporte gravado com sucesso. A visita à loja foi marcada como concluída.'
                : 'Reporte gravado com sucesso. A visita será sincronizada com a rota.');
        if (self::$submissionWarnings) {
            $success_message .= ' Existem tarefas de reconciliação pendentes, sem perda das respostas.';
        }

        $ack_mutation_id = self::$offlineMutationId;
        $ajax_payload = [
            'submission_id' => $submission_id,
            'persisted' => true,
            'ack' => [
                'protocol' => 'fieldflow-form-ack-v1',
                'persisted' => true,
                'submission_id' => $submission_id,
                'mutation_id' => $ack_mutation_id,
                'committed_at' => $saved_at,
            ],
            'record_id' => (int)($synced['record_id'] ?? 0),
            'record_version_id' => (int)($synced['version_id'] ?? 0),
            'visit_completion' => $visit_completion,
            'updated_existing' => $is_update,
            'report_day' => $report_day,
            'saved_at' => $saved_at,
            'message' => $success_message,
            'warnings' => array_values(array_unique(self::$submissionWarnings)),
            'submission_stage' => 'completed',
            'processing_ms' => self::submission_elapsed_ms(),
            'media' => self::request_media_metrics(),
            'media_integrity' => $offline_media_integrity,
            'offline_trust' => $offline_trust_status,
        ];
        self::record_media_telemetry('success');
        self::$committedResponse = $ajax_payload;
        self::set_submission_stage('post_commit_response');
        if ($ack_mutation_id !== '' && class_exists('\RoutesPro\Mobile\MutationStore')) {
            self::run_non_blocking('Falha não bloqueante ao fechar a mutação offline.', static function() use ($ajax_payload, $ack_mutation_id): void {
                \RoutesPro\Mobile\MutationStore::finishExternal($ack_mutation_id, 200, $ajax_payload, false);
            }, ['context_key' => 'form_offline_mutation_finish', 'submission_id' => $submission_id]);
            self::$offlineMutationId = '';
        }
        if (self::$internalSubmissionMode) {
            self::$internalSubmissionResult = ['success' => true, 'status' => 200, 'data' => $ajax_payload];
            return;
        }
        if ($is_ajax) {
            wp_send_json_success($ajax_payload);
        }
        wp_safe_redirect(self::redirect_back_with_success(!empty($visit_completion['applied']), $is_update));
        exit;
    }

    public static function reconcile_submission_cardex(int $submission_id): void {
        global $wpdb;
        $submission_id = absint($submission_id);
        if ($submission_id <= 0 || !class_exists('\\RoutesPro\\Forms\\ProductCardex')) return;

        $done_key = 'fieldflow_submission_cardex_done_' . $submission_id;
        $lock_key = 'fieldflow_submission_cardex_lock_' . $submission_id;
        $retry_key = 'fieldflow_submission_cardex_retry_' . $submission_id;
        if (get_transient($done_key) || get_transient($lock_key)) return;
        set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);

        $completed = false;
        try {
            $row = $wpdb->get_row($wpdb->prepare(
                'SELECT * FROM ' . self::table_submissions() . ' WHERE id=%d LIMIT 1',
                $submission_id
            ), ARRAY_A);
            if (!$row) throw new \RuntimeException('Submissão não encontrada para reconciliar o cardex.');

            $form = self::get_form((int)($row['form_id'] ?? 0));
            if (!$form) throw new \RuntimeException('Formulário não encontrado para reconciliar o cardex.');

            $submit_context = [
                'client_id' => (int)($row['client_id'] ?? 0),
                'project_id' => (int)($row['project_id'] ?? 0),
                'route_id' => (int)($row['route_id'] ?? 0),
                'stop_id' => (int)($row['route_stop_id'] ?? 0),
                'route_stop_id' => (int)($row['route_stop_id'] ?? 0),
                'location_id' => (int)($row['location_id'] ?? 0),
                'date' => (string)($row['report_day'] ?? ''),
                'route_date' => (string)($row['report_day'] ?? ''),
                'form_id' => (int)($row['form_id'] ?? 0),
                'cardex_source' => 'frontend',
            ];

            $schema = self::decode_schema($form['schema_json'] ?? '');
            if (class_exists('\\RoutesPro\\Forms\\ContextQuestions')) {
                $schema = ContextQuestions::inject_into_schema($schema, ContextQuestions::regular_for_context($submit_context));
            }
            if (class_exists('\\RoutesPro\\Forms\\BriefingFormBridge')) {
                $schema = BriefingFormBridge::expand_blocks($schema, $submit_context);
            }
            if (class_exists('\\RoutesPro\\Forms\\EvidenceFlow')) {
                $schema = EvidenceFlow::expand_schema($schema);
            }

            $answer_rows = $wpdb->get_results($wpdb->prepare(
                'SELECT question_key,value_text,value_number,value_json FROM ' . self::table_answers() . ' WHERE submission_id=%d ORDER BY id ASC',
                $submission_id
            ), ARRAY_A) ?: [];
            $answer_values = [];
            foreach ($answer_rows as $answer) {
                $key = sanitize_key((string)($answer['question_key'] ?? ''));
                if ($key === '') continue;
                $json = (string)($answer['value_json'] ?? '');
                if ($json !== '') {
                    $decoded = json_decode($json, true);
                    $answer_values[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $json;
                } elseif ($answer['value_number'] !== null && $answer['value_number'] !== '') {
                    $answer_values[$key] = (float)$answer['value_number'];
                } else {
                    $answer_values[$key] = (string)($answer['value_text'] ?? '');
                }
            }

            foreach ((array)($schema['questions'] ?? []) as $question) {
                if (!is_array($question)) continue;
                $type = sanitize_key((string)($question['type'] ?? ''));
                $key = sanitize_key((string)($question['key'] ?? ''));
                if ($type !== 'product_matrix' || $key === '' || !array_key_exists($key, $answer_values)) continue;
                if (!ProductCardex::question_uses_cardex($question)) continue;
                if (!ProductCardex::question_is_available_for_context($question, $submit_context)) continue;
                $rows = is_array($answer_values[$key]) ? $answer_values[$key] : [];
                $ok = ProductCardex::sync_store_override_from_rows(
                    $question,
                    $submit_context,
                    $rows,
                    (int)($row['user_id'] ?? 0)
                );
                if (!$ok) throw new \RuntimeException('Falha ao sincronizar o sortido específico da loja.');
            }

            $completed = true;
        } catch (\Throwable $e) {
            self::log_submission_throwable('Falha na reconciliação assíncrona do sortido específico da loja.', $e, [
                'context_key' => 'form_cardex_override_async',
                'submission_id' => $submission_id,
                'submission_stage' => 'scheduled_post_commit',
            ]);
            $attempt = max(0, (int)get_transient($retry_key)) + 1;
            set_transient($retry_key, $attempt, DAY_IN_SECONDS);
            if ($attempt <= 3) {
                $args = [$submission_id];
                if (!wp_next_scheduled('fieldflow_reconcile_submission_cardex', $args)) {
                    wp_schedule_single_event(time() + (60 * $attempt), 'fieldflow_reconcile_submission_cardex', $args);
                }
            }
        } finally {
            delete_transient($lock_key);
            if ($completed) {
                delete_transient($retry_key);
                set_transient($done_key, 1, 30 * DAY_IN_SECONDS);
            }
        }
    }

    public static function dispatch_submission_integrations(int $submission_id, int $is_update, string $visit_json = ''): void {
        $submission_id = absint($submission_id);
        if ($submission_id <= 0) return;
        $done_key = 'fieldflow_submission_integrations_done_' . $submission_id;
        $lock_key = 'fieldflow_submission_integrations_lock_' . $submission_id;
        $retry_key = 'fieldflow_submission_integrations_retry_' . $submission_id;
        if (get_transient($done_key) || get_transient($lock_key)) return;
        set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);
        $completed = false;
        try {
            if (!class_exists('\\RoutesPro\\Forms\\SubmissionSideEffectDispatcher')) {
                throw new \RuntimeException('Submission Side Effect Dispatcher unavailable.');
            }
            SubmissionSideEffectDispatcher::dispatchFromSubmission(
                $submission_id,
                !empty($is_update),
                $visit_json,
                'scheduled_post_commit'
            );
            $completed = true;
        } catch (\Throwable $e) {
            self::log_submission_throwable('Falha no dispatcher pós-submissão. A persistência da visita mantém-se confirmada.', $e, [
                'context_key' => 'form_submission_side_effect_dispatch',
                'submission_id' => $submission_id,
                'submission_stage' => 'scheduled_post_commit',
            ]);
            $attempt = max(0, (int)get_transient($retry_key)) + 1;
            set_transient($retry_key, $attempt, DAY_IN_SECONDS);
            if ($attempt <= 3) {
                $args = [$submission_id, $is_update, $visit_json];
                if (!wp_next_scheduled('fieldflow_submission_post_commit', $args)) {
                    wp_schedule_single_event(time() + (60 * $attempt), 'fieldflow_submission_post_commit', $args);
                }
            }
        } finally {
            delete_transient($lock_key);
            if ($completed) {
                delete_transient($retry_key);
                set_transient($done_key, 1, 30 * DAY_IN_SECONDS);
            }
        }
    }

    public static function reconcile_submission_history(int $submission_id): void {
        global $wpdb;
        $submission_id = absint($submission_id);
        if ($submission_id <= 0) return;
        $done_key = 'fieldflow_submission_history_done_' . $submission_id;
        $lock_key = 'fieldflow_submission_history_lock_' . $submission_id;
        $retry_key = 'fieldflow_submission_history_retry_' . $submission_id;
        if (get_transient($done_key) || get_transient($lock_key)) return;
        set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);
        $completed = false;
        try {
            $row = $wpdb->get_row($wpdb->prepare(
                'SELECT s.*,r.owner_user_id FROM ' . self::table_submissions() . ' s LEFT JOIN ' . $wpdb->prefix . 'routespro_routes r ON r.id=s.route_id WHERE s.id=%d LIMIT 1',
                $submission_id
            ), ARRAY_A);
            if (!$row) throw new \RuntimeException('Submissao nao encontrada para reconciliar o historico.');
            if ((int)($row['record_id'] ?? 0) > 0 && (int)($row['record_version_id'] ?? 0) > 0) {
                $completed = true;
                return;
            }
            $answer_rows = $wpdb->get_results($wpdb->prepare(
                'SELECT question_key,question_label,value_text,value_number,value_json FROM ' . self::table_answers() . ' WHERE submission_id=%d ORDER BY id ASC',
                $submission_id
            ), ARRAY_A) ?: [];
            $answers = [];
            foreach ($answer_rows as $answer) {
                $key = sanitize_key((string)($answer['question_key'] ?? ''));
                if ($key === '') continue;
                $json = (string)($answer['value_json'] ?? '');
                if ($json !== '') {
                    $decoded = json_decode($json, true);
                    $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $json;
                    $type = 'json';
                } elseif ($answer['value_number'] !== null && $answer['value_number'] !== '') {
                    $value = (float)$answer['value_number'];
                    $type = 'number';
                } else {
                    $value = (string)($answer['value_text'] ?? '');
                    $type = 'text';
                }
                $answers[$key] = [
                    'type' => $type,
                    'label' => sanitize_text_field((string)($answer['question_label'] ?? $key)),
                    'value' => $value,
                ];
            }
            $synced = RecordService::sync_submission($submission_id, $row, $answers);
            if (is_wp_error($synced)) throw new \RuntimeException($synced->get_error_code() . ': ' . $synced->get_error_message());
            $updated = $wpdb->update(self::table_submissions(), [
                'record_id' => (int)($synced['record_id'] ?? 0),
                'record_version_id' => (int)($synced['version_id'] ?? 0),
            ], ['id' => $submission_id], ['%d','%d'], ['%d']);
            if ($updated === false) throw new \RuntimeException($wpdb->last_error ?: 'Falha ao ligar a versao reconciliada.');
            $completed = true;
        } catch (\Throwable $e) {
            self::log_submission_throwable('Falha na reconciliacao automatica do historico da submissao.', $e, [
                'context_key' => 'form_record_history_reconcile',
                'submission_id' => $submission_id,
                'submission_stage' => 'scheduled_history_reconcile',
            ]);
            $attempt = max(0, (int)get_transient($retry_key)) + 1;
            set_transient($retry_key, $attempt, DAY_IN_SECONDS);
            if ($attempt <= 3) {
                $args = [$submission_id];
                if (!wp_next_scheduled('fieldflow_reconcile_submission_history', $args)) {
                    wp_schedule_single_event(time() + (60 * $attempt), 'fieldflow_reconcile_submission_history', $args);
                }
            }
        } finally {
            delete_transient($lock_key);
            if ($completed) {
                delete_transient($retry_key);
                set_transient($done_key, 1, 30 * DAY_IN_SECONDS);
            }
        }
    }

    public static function reconcile_route_after_submission(int $route_id): void {
        $route_id = absint($route_id);
        if ($route_id <= 0 || !class_exists('\\RoutesPro\\Services\\RouteProgressService')) return;
        $lock_key = 'fieldflow_submission_route_lock_' . $route_id;
        $retry_key = 'fieldflow_submission_route_retry_' . $route_id;
        if (get_transient($lock_key)) return;
        set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);
        $completed = false;
        try {
            $result = \RoutesPro\Services\RouteProgressService::sync($route_id, true);
            if (is_wp_error($result)) throw new \RuntimeException($result->get_error_code() . ': ' . $result->get_error_message());
            $completed = true;
        } catch (\Throwable $e) {
            self::log_submission_throwable('Falha na reconciliacao automatica da rota apos a submissao.', $e, [
                'context_key' => 'form_route_reconcile',
                'route_id' => $route_id,
                'submission_stage' => 'scheduled_route_reconcile',
            ]);
            $attempt = max(0, (int)get_transient($retry_key)) + 1;
            set_transient($retry_key, $attempt, DAY_IN_SECONDS);
            if ($attempt <= 3) {
                $args = [$route_id];
                if (!wp_next_scheduled('fieldflow_reconcile_route_after_submission', $args)) {
                    wp_schedule_single_event(time() + (60 * $attempt), 'fieldflow_reconcile_route_after_submission', $args);
                }
            }
        } finally {
            delete_transient($lock_key);
            if ($completed) delete_transient($retry_key);
        }
    }

    private static function submission_location_snapshot(array $context): array {
        global $wpdb;
        $location_id = absint($context['location_id'] ?? 0);
        if ($location_id <= 0) return [];
        $table = $wpdb->prefix . 'routespro_locations';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id,name,address,district,county,city,parish,postal_code,country,external_ref,lat,lng,place_id,source FROM {$table} WHERE id=%d LIMIT 1",
            $location_id
        ), ARRAY_A);
        if (!$row) return [];
        $snapshot = [
            'location_id' => (int)($row['id'] ?? $location_id),
            'route_id' => absint($context['route_id'] ?? 0),
            'route_stop_id' => absint($context['route_stop_id'] ?? 0),
            'name' => sanitize_text_field((string)($row['name'] ?? '')),
            'address' => sanitize_text_field((string)($row['address'] ?? '')),
            'district' => sanitize_text_field((string)($row['district'] ?? '')),
            'county' => sanitize_text_field((string)($row['county'] ?? '')),
            'city' => sanitize_text_field((string)($row['city'] ?? '')),
            'parish' => sanitize_text_field((string)($row['parish'] ?? '')),
            'postal_code' => sanitize_text_field((string)($row['postal_code'] ?? '')),
            'country' => sanitize_text_field((string)($row['country'] ?? '')),
            'external_ref' => sanitize_text_field((string)($row['external_ref'] ?? '')),
            'lat' => isset($row['lat']) && $row['lat'] !== null ? (float)$row['lat'] : null,
            'lng' => isset($row['lng']) && $row['lng'] !== null ? (float)$row['lng'] : null,
            'place_id' => sanitize_text_field((string)($row['place_id'] ?? '')),
            'source' => sanitize_key((string)($row['source'] ?? '')),
            'captured_at' => current_time('mysql'),
        ];
        return array_filter($snapshot, static function($value): bool { return $value !== '' && $value !== null && $value !== 0; });
    }

    private static function finishOfflineReplay($result): void {
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            $status = is_array($data) && !empty($data['status']) ? (int)$data['status'] : 400;
            wp_send_json_error([
                'code' => $result->get_error_code(),
                'message' => $result->get_error_message(),
                'data' => $data,
                '_idempotent_replay' => true,
            ], $status);
        }
        if ($result instanceof \WP_REST_Response) {
            $data = $result->get_data();
            $status = (int)$result->get_status();
            $payload = is_array($data) ? $data : ['response' => $data];
            $payload['_idempotent_replay'] = true;
            if ($status >= 400) wp_send_json_error($payload, $status);
            $submission_id = isset($payload['submission_id']) ? absint($payload['submission_id']) : 0;
            if ($submission_id > 0) {
                $payload['persisted'] = true;
                $payload['ack'] = [
                    'protocol' => 'fieldflow-form-ack-v1',
                    'persisted' => true,
                    'submission_id' => $submission_id,
                    'mutation_id' => self::$offlineMutationId,
                    'committed_at' => isset($payload['saved_at']) ? (string)$payload['saved_at'] : '',
                    'idempotent_replay' => true,
                ];
            }
            wp_send_json_success($payload, $status);
        }
        wp_send_json_error(['code' => 'fieldflow_offline_replay_invalid', 'message' => 'Resposta offline inválida.'], 500);
    }

    private static function offline_trust_status(): array {
        $token = isset($_POST['routespro_offline_trust_lease']) ? sanitize_text_field((string)wp_unslash($_POST['routespro_offline_trust_lease'])) : '';
        if ($token === '') return ['present'=>false,'valid'=>false,'state'=>'not_supplied','protocol'=>'fieldflow-offline-trust-v1'];
        $deviceId = isset($_SERVER['HTTP_X_FIELDFLOW_DEVICE_ID']) ? sanitize_text_field((string)wp_unslash($_SERVER['HTTP_X_FIELDFLOW_DEVICE_ID'])) : '';
        if (!class_exists('\\RoutesPro\\Mobile\\OfflineTrustLease')) {
            return ['present'=>true,'valid'=>false,'state'=>'runtime_unavailable','protocol'=>'fieldflow-offline-trust-v1'];
        }
        $verified = \RoutesPro\Mobile\OfflineTrustLease::verify($token, get_current_user_id(), $deviceId);
        $claims = is_array($verified['claims'] ?? null) ? $verified['claims'] : [];
        return [
            'present'=>true,
            'valid'=>!empty($verified['valid']),
            'state'=>sanitize_key((string)($verified['reason'] ?? 'unknown')),
            'protocol'=>'fieldflow-offline-trust-v1',
            'release'=>sanitize_text_field((string)($claims['release'] ?? '')),
            'expires_at'=>absint($claims['exp'] ?? 0),
            'certified_at'=>sanitize_text_field((string)($claims['certified_at'] ?? '')),
        ];
    }

    private static function validate_offline_media_manifest() {
        $raw = isset($_POST['routespro_offline_media_manifest']) ? (string)wp_unslash($_POST['routespro_offline_media_manifest']) : '';
        if ($raw === '') return ['present'=>false,'verified'=>false,'protocol'=>'fieldflow-media-integrity-v1','file_count'=>0,'hashed_files'=>0,'total_bytes'=>0];
        if (strlen($raw) > 524288) return new \WP_Error('fieldflow_offline_media_manifest_invalid', 'O manifesto das fotografias excede o limite de segurança.', ['status'=>422]);
        $manifest = json_decode($raw, true);
        if (!is_array($manifest) || (string)($manifest['protocol'] ?? '') !== 'fieldflow-media-integrity-v1') {
            return new \WP_Error('fieldflow_offline_media_manifest_invalid', 'O manifesto de integridade das fotografias não é válido.', ['status'=>422]);
        }
        $expected = is_array($manifest['files'] ?? null) ? array_slice($manifest['files'], 0, 300) : [];
        $expectedCount = max(0, (int)($manifest['file_count'] ?? count($expected)));
        if ($expectedCount !== count($expected)) {
            return new \WP_Error('fieldflow_offline_media_manifest_invalid', 'A contagem do manifesto das fotografias não é consistente.', ['status'=>422]);
        }
        $actual = [];
        foreach ((array)($_FILES ?? []) as $file) {
            if (!is_array($file)) continue;
            self::flatten_uploaded_files($file['name'] ?? '', $file['tmp_name'] ?? '', $file['size'] ?? 0, $file['error'] ?? UPLOAD_ERR_NO_FILE, $file['type'] ?? '', $actual);
        }
        foreach (self::$resumableMediaRefs as $file) {
            if (!is_array($file) || empty($file['tmp_name'])) continue;
            $actual[] = [
                'name'=>(string)($file['name'] ?? ''),
                'tmp_name'=>(string)($file['tmp_name'] ?? ''),
                'size'=>(int)($file['size'] ?? 0),
                'error'=>(int)($file['error'] ?? UPLOAD_ERR_OK),
                'type'=>(string)($file['type'] ?? ''),
                'sha256'=>(string)($file['sha256'] ?? ''),
            ];
        }
        if (count($actual) !== $expectedCount) {
            return new \WP_Error('fieldflow_offline_media_integrity_mismatch', 'As fotografias recebidas não coincidem com a cópia protegida no dispositivo. O reporte foi mantido localmente.', [
                'status'=>422,'expected_files'=>$expectedCount,'received_files'=>count($actual),'integrity'=>'count_mismatch'
            ]);
        }
        $used = array_fill(0, count($actual), false);
        $hashed = 0;
        $totalBytes = 0;
        foreach ($expected as $entry) {
            if (!is_array($entry)) return new \WP_Error('fieldflow_offline_media_manifest_invalid', 'O manifesto de fotografias contém uma entrada inválida.', ['status'=>422]);
            $name = wp_basename(sanitize_file_name((string)($entry['name'] ?? '')));
            $size = max(0, (int)($entry['size'] ?? 0));
            $sha = strtolower(sanitize_text_field((string)($entry['sha256'] ?? '')));
            if ($sha !== '' && !preg_match('/^[a-f0-9]{64}$/', $sha)) {
                return new \WP_Error('fieldflow_offline_media_manifest_invalid', 'O hash de uma fotografia não é válido.', ['status'=>422]);
            }
            $matched = false;
            foreach ($actual as $index => &$candidate) {
                if ($used[$index]) continue;
                if ((int)$candidate['size'] !== $size) continue;
                if ($name !== '' && wp_basename(sanitize_file_name((string)$candidate['name'])) !== $name) continue;
                if ($sha !== '') {
                    if (empty($candidate['sha256'])) $candidate['sha256'] = is_file((string)$candidate['tmp_name']) ? strtolower((string)hash_file('sha256', (string)$candidate['tmp_name'])) : '';
                    if ($candidate['sha256'] === '' || !hash_equals($sha, (string)$candidate['sha256'])) continue;
                    $hashed++;
                }
                $used[$index] = true;
                $matched = true;
                $totalBytes += $size;
                break;
            }
            unset($candidate);
            if (!$matched) {
                return new \WP_Error('fieldflow_offline_media_integrity_mismatch', 'Uma ou mais fotografias chegaram diferentes da cópia protegida no dispositivo. O reporte não foi gravado.', [
                    'status'=>422,'expected_files'=>$expectedCount,'received_files'=>count($actual),'integrity'=>'file_mismatch'
                ]);
            }
        }
        $manifestBytes = max(0, (int)($manifest['total_bytes'] ?? $totalBytes));
        if ($manifestBytes !== $totalBytes) {
            return new \WP_Error('fieldflow_offline_media_integrity_mismatch', 'O tamanho total das fotografias não coincide com a cópia protegida no dispositivo.', [
                'status'=>422,'expected_bytes'=>$manifestBytes,'received_bytes'=>$totalBytes,'integrity'=>'bytes_mismatch'
            ]);
        }
        return [
            'present'=>true,
            'verified'=>true,
            'protocol'=>'fieldflow-media-integrity-v1',
            'file_count'=>$expectedCount,
            'hashed_files'=>$hashed,
            'total_bytes'=>$totalBytes,
        ];
    }

    private static function flatten_uploaded_files($names, $tmpNames, $sizes, $errors, $types, array &$out): void {
        if (is_array($names)) {
            foreach ($names as $key => $name) {
                self::flatten_uploaded_files(
                    $name,
                    is_array($tmpNames) ? ($tmpNames[$key] ?? '') : '',
                    is_array($sizes) ? ($sizes[$key] ?? 0) : 0,
                    is_array($errors) ? ($errors[$key] ?? UPLOAD_ERR_NO_FILE) : $errors,
                    is_array($types) ? ($types[$key] ?? '') : $types,
                    $out
                );
            }
            return;
        }
        $error = is_numeric($errors) ? (int)$errors : UPLOAD_ERR_NO_FILE;
        $size = max(0, (int)$sizes);
        $tmp = (string)$tmpNames;
        if ($error !== UPLOAD_ERR_OK || $size <= 0 || $tmp === '') return;
        $out[] = [
            'name'=>(string)$names,
            'tmp_name'=>$tmp,
            'size'=>$size,
            'type'=>sanitize_text_field((string)$types),
            'sha256'=>'',
        ];
    }

    private static function request_media_metrics(): array {
        $post = static function(string $key): int {
            return isset($_POST[$key]) ? max(0,(int)wp_unslash($_POST[$key])) : 0;
        };
        $server = self::server_upload_metrics();
        $fileCount = max($post('routespro_media_file_count'), (int)$server['file_count']);
        $optimizedBytes = $post('routespro_media_optimized_bytes');
        if ($optimizedBytes <= 0) $optimizedBytes = (int)$server['received_bytes'];
        $originalBytes = max($post('routespro_media_original_bytes'), $optimizedBytes);
        return [
            'file_count' => $fileCount,
            'original_bytes' => $originalBytes,
            'optimized_bytes' => $optimizedBytes,
            'server_received_bytes' => (int)$server['received_bytes'],
            'optimized_files' => $post('routespro_media_optimized_files'),
            'duplicate_files' => $post('routespro_media_duplicate_files'),
            'retry_count' => $post('routespro_media_retry_count'),
        ];
    }

    private static function server_upload_metrics(): array {
        $count = 0;
        $bytes = 0;
        $walk = static function($sizes, $errors) use (&$walk, &$count, &$bytes): void {
            if (is_array($sizes)) {
                foreach ($sizes as $key=>$size) $walk($size, is_array($errors) ? ($errors[$key] ?? UPLOAD_ERR_NO_FILE) : $errors);
                return;
            }
            $size = max(0,(int)$sizes);
            $error = is_numeric($errors) ? (int)$errors : UPLOAD_ERR_OK;
            if ($error === UPLOAD_ERR_OK && $size > 0) { $count++; $bytes += $size; }
        };
        foreach ((array)($_FILES ?? []) as $file) {
            if (!is_array($file)) continue;
            $walk($file['size'] ?? 0, $file['error'] ?? UPLOAD_ERR_NO_FILE);
        }
        foreach (self::$resumableMediaRefs as $file) {
            if (!is_array($file)) continue;
            $size = max(0,(int)($file['size'] ?? 0));
            $error = (int)($file['error'] ?? UPLOAD_ERR_OK);
            if ($error === UPLOAD_ERR_OK && $size > 0) { $count++; $bytes += $size; }
        }
        return ['file_count'=>$count,'received_bytes'=>$bytes];
    }

    private static function record_media_telemetry(string $status, string $errorCode = ''): void {
        if (self::$mediaTelemetryRecorded || !class_exists('\\RoutesPro\\Observability\\MediaTelemetry')) return;
        $metrics = self::request_media_metrics();
        if ((int)$metrics['file_count'] <= 0 && $errorCode !== 'upload_failed') return;
        try {
            $recorded = \RoutesPro\Observability\MediaTelemetry::record($status, array_merge($metrics, [
                'error_code' => $errorCode,
                'form_id' => absint($_POST['routespro_form_id'] ?? 0),
                'client_id' => absint($_POST['routespro_client_id'] ?? 0),
                'project_id' => absint($_POST['routespro_project_id'] ?? 0),
                'route_id' => absint($_POST['routespro_route_id'] ?? 0),
                'location_id' => absint($_POST['routespro_location_id'] ?? 0),
                'user_id' => get_current_user_id(),
                'processing_ms' => self::submission_elapsed_ms(),
            ]));
            if ($recorded > 0) self::$mediaTelemetryRecorded = true;
        } catch (\Throwable $ignored) {}
    }

    private static function cleanup_current_uploads(): void {
        if (!self::$uploadedPaths) return;
        foreach (array_values(array_unique(self::$uploadedPaths)) as $path) {
            $path = (string)$path;
            if ($path === '' || !is_file($path)) continue;
            if (function_exists('wp_delete_file')) wp_delete_file($path);
            else @unlink($path);
        }
        self::$uploadedPaths = [];
    }

    private static function submission_elapsed_ms(): int {
        if (self::$submissionStartedAt <= 0) return 0;
        return max(0, (int)round((microtime(true) - self::$submissionStartedAt) * 1000));
    }

    /**
     * Resolve the same conditional visibility used by FormRenderer before
     * validating required answers on the server.
     */
    private static function conditional_visibility_for_submission(array $schema, array $existing_prefill = []): array {
        if (!class_exists('\RoutesPro\Forms\ConditionalFlow')) return [];
        $values = is_array($existing_prefill) ? $existing_prefill : [];
        foreach ((array)($schema['questions'] ?? []) as $question) {
            if (!is_array($question)) continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($key === '') continue;
            $type = sanitize_key((string)($question['type'] ?? 'text')) ?: 'text';
            $multiple = self::question_allows_multiple_selection($question, $type);
            if ($type === 'checkbox') {
                $values[$key] = isset($_POST[$key]) ? 1 : 0;
                continue;
            }
            if ($multiple && in_array($type, ['select', 'radio', 'visual_choice'], true)) {
                $raw = $_POST[$key] ?? [];
                if (!is_array($raw)) $raw = ($raw === null || $raw === '') ? [] : [$raw];
                $values[$key] = array_values(array_map('sanitize_text_field', wp_unslash($raw)));
                continue;
            }
            // Choice controls are omitted by the browser when no option is
            // selected. Do not retain an old prefill value in that case, or a
            // previously visible conditional branch could stay falsely active.
            if (in_array($type, ['select', 'radio', 'visual_choice'], true) && !array_key_exists($key, $_POST)) {
                $values[$key] = '';
                continue;
            }
            if (!array_key_exists($key, $_POST)) continue;
            $raw = wp_unslash($_POST[$key]);
            $values[$key] = self::conditional_scalar_value($raw);
        }
        $payload = ConditionalFlow::payload($schema, $values);
        return is_array($payload['initial_visibility'] ?? null) ? $payload['initial_visibility'] : [];
    }

    private static function conditional_scalar_value($raw) {
        if (!is_array($raw)) return sanitize_text_field((string)$raw);
        $values = [];
        $walk = static function($value) use (&$values, &$walk): void {
            if (is_array($value)) {
                foreach ($value as $item) $walk($item);
                return;
            }
            if (is_scalar($value) || $value === null) $values[] = sanitize_text_field((string)$value);
        };
        $walk($raw);
        $values = array_values(array_filter($values, static fn($value) => $value !== ''));
        if (count($values) === 1) return $values[0];
        return $values;
    }

    private static function finish_required_field_error(string $key, array $question, bool $is_ajax, string $detail = ''): void {
        $key = sanitize_key($key);
        $label = sanitize_text_field((string)($question['label'] ?? $key));
        if ($label === '') $label = 'Pergunta obrigatória';
        $message = 'Falta responder ao campo obrigatório: ' . $label . '.';
        if ($detail !== '') $message .= ' ' . sanitize_text_field($detail);
        self::finish_submit_error('campo_' . $key, $is_ajax, [
            'field' => $key,
            'question_label' => $label,
            'message' => $message,
        ], 422);
    }

    private static function finish_application_service_error(\WP_Error $error, bool $is_ajax, bool $is_update, string $report_day): void {
        $data = is_array($error->get_error_data()) ? $error->get_error_data() : [];
        $reason = sanitize_key((string)($data['reason'] ?? ''));
        $status = max(400, min(599, (int)($data['status'] ?? 500)));
        $payload = $data;
        unset($payload['reason'], $payload['status'], $payload['contract']);
        if (!isset($payload['report_day']) && $report_day !== '') $payload['report_day'] = $report_day;

        switch ($reason) {
            case 'daily_report_exists':
                self::finish_submit_error('daily_report_exists', $is_ajax, $payload, 409);
                break;
            case 'daily_report_missing':
                self::finish_submit_error('daily_report_missing', $is_ajax, $payload, 409);
                break;
            case 'daily_report_edit_forbidden':
                self::finish_submit_error('daily_report_edit_forbidden', $is_ajax, $payload, 403);
                break;
            case 'daily_report_context_conflict':
                self::finish_submit_error('daily_report_context_conflict', $is_ajax, $payload, 409);
                break;
            case 'answer_reset_failed':
            case 'answer_save_failed':
                self::finish_submit_error($is_update ? 'answer_update' : 'answer_insert', $is_ajax, $payload, $status);
                break;
            case 'submission_update_failed':
            case 'submission_finalize_failed':
                self::finish_submit_error('submission_update', $is_ajax, $payload, $status);
                break;
            case 'submission_insert_failed':
            case 'transaction_start_failed':
            case 'transaction_commit_failed':
            case 'invalid_command':
            default:
                self::finish_submit_error($is_update ? 'submission_update' : 'submission_insert', $is_ajax, $payload, $status);
                break;
        }
    }

    private static function finish_submit_error(string $code, bool $is_ajax, array $data = [], int $status = 400): void {
        $code = sanitize_key($code) ?: 'submission_error';
        $status = max(400, min(599, $status));
        self::set_submission_stage('validation_' . $code);
        if (!self::$submissionCommitted) self::cleanup_current_uploads();

        $payload = array_merge([
            'code' => $code,
            'message' => self::submit_error_message($code),
            'submission_stage' => self::$submissionStage,
            'processing_ms' => self::submission_elapsed_ms(),
            'media' => self::request_media_metrics(),
        ], $data);
        self::record_media_telemetry('error', $code);

        // A resposta de erro tem de ser à prova de falhas. Na build 2.2.230
        // esta linha usava uma variável inexistente ($ajax_payload), fazendo
        // uma validação normal transformar-se num falso erro crítico FF.
        self::$committedResponse = $payload;

        if (self::$offlineMutationId !== '' && class_exists('\RoutesPro\Mobile\MutationStore')) {
            try {
                \RoutesPro\Mobile\MutationStore::finishExternal(self::$offlineMutationId, $status, $payload, true);
            } catch (\Throwable $e) {
                self::log_submission_throwable('Falha não bloqueante ao fechar uma mutação offline com erro.', $e, [
                    'context_key' => 'form_offline_mutation_error_finish',
                    'error_code' => $code,
                ]);
            }
            self::$offlineMutationId = '';
        }

        if (self::$internalSubmissionMode) {
            self::$internalSubmissionResult = ['success' => false, 'status' => $status, 'data' => $payload];
            throw new \RuntimeException('__fieldflow_internal_submission_error__');
        }
        if ($is_ajax) {
            if (!headers_sent()) nocache_headers();
            wp_send_json_error($payload, $status);
        }
        wp_safe_redirect(self::redirect_back_with_error($code));
        exit;
    }
    private static function submit_error_message(string $code): string {
        $messages = [
            'sem_login' => 'A sessão terminou. Inicia sessão novamente antes de submeter.',
            'nonce' => 'A página ficou desatualizada. Atualiza e volta a submeter.',
            'form' => 'O formulário já não está ativo.',
            'schema' => 'O formulário não tem perguntas válidas.',
            'binding_invalid' => 'A ligação entre a rota e o formulário já não é válida.',
            'binding_context_mismatch' => 'Este formulário não pertence à loja ou rota selecionada.',
            'stop_invalid' => 'A paragem selecionada já não existe.',
            'stop_ambiguous' => 'Esta loja aparece mais do que uma vez na mesma rota. Abre o formulário a partir da paragem correta.',
            'route_required' => 'Não foi possível identificar a rota associada à paragem.',
            'route_stop_mismatch' => 'A paragem não pertence à rota selecionada.',
            'location_stop_mismatch' => 'A paragem não pertence à loja selecionada.',
            'route_invalid' => 'A rota selecionada já não existe.',
            'client_route_mismatch' => 'O cliente associado ao formulário não coincide com a rota selecionada.',
            'project_route_mismatch' => 'A campanha associada ao formulário não coincide com a rota selecionada.',
            'forbidden_route' => 'Esta rota não está atribuída ao teu utilizador.',
            'route_not_assigned' => 'Esta rota está atribuída a outro utilizador.',
            'location_invalid' => 'A loja selecionada já não existe.',
            'daily_report_exists' => 'O reporte desta loja já foi efetuado hoje. Abre o reporte existente e escolhe “Editar reporte” para corrigir alguma resposta.',
            'daily_report_edit_forbidden' => 'O reporte já existe, mas o teu utilizador não tem permissão para o editar.',
            'daily_report_missing' => 'O reporte que tentaste editar já não está disponível. Atualiza a página e tenta novamente.',
            'daily_report_context_conflict' => 'Este reporte pertence a outra rota ou paragem. Abre a visita original para o editar, evitando concluir duas visitas com o mesmo reporte.',
            'plan_reporting_disabled' => 'A submissão de reportes não está incluída no plano deste cliente.',
            'plan_submission_limit' => 'O limite mensal de submissões foi atingido.',
            'submission_insert' => 'Não foi possível criar a submissão. Nenhuma resposta foi gravada.',
            'submission_update' => 'Não foi possível atualizar o reporte existente. As alterações foram anuladas.',
            'answer_insert' => 'Não foi possível guardar todas as respostas. A submissão foi anulada.',
            'answer_update' => 'Não foi possível atualizar todas as respostas. O reporte anterior foi mantido sem alterações.',
            'upload_failed' => 'Não foi possível receber uma ou mais imagens. Revê o formato, o tamanho e a ligação e tenta novamente.',
            'upload_count_mismatch' => 'Nem todas as fotografias selecionadas chegaram ao servidor. O reporte não foi gravado para evitar perda de evidências.',
            'cardex_override_update' => 'Não foi possível atualizar o sortido específico desta loja. Nenhuma alteração foi gravada.',
            'submission_link' => 'As respostas foram anuladas porque não foi possível ligá-las ao histórico da loja.',
            'visit_stop_ambiguous' => 'Existem várias visitas à mesma loja nesta rota. Atualiza a página e seleciona a paragem correta.',
            'visit_stop_missing' => 'A paragem associada ao formulário já não existe.',
            'visit_stop_route_mismatch' => 'A paragem não pertence à rota desta submissão.',
            'visit_stop_location_mismatch' => 'A paragem não pertence à loja desta submissão.',
            'visit_stop_update' => 'Não foi possível marcar a visita como concluída. Nenhuma resposta foi gravada.',
            'visit_submission_link' => 'Não foi possível ligar o reporte à paragem da rota. Nenhuma resposta foi gravada.',
            'visit_route_missing' => 'A rota associada à visita já não existe.',
            'visit_route_update' => 'Não foi possível sincronizar o estado da rota. Nenhuma resposta foi gravada.',
            'unexpected_submission_error' => 'O servidor encontrou um erro inesperado ao concluir o reporte. As respostas continuam disponíveis para nova tentativa.',
        ];
        if (strpos($code, 'campo_') === 0) return 'Existe pelo menos uma resposta obrigatória por preencher.';
        return $messages[$code] ?? 'Não foi possível guardar o formulário. Revê os dados e tenta novamente.';
    }

    private static function extract_value_from_request(string $key, string $type, array $question = [], array $context = [], $existing_value = null) {
        if (in_array($type, ['select', 'radio', 'visual_choice'], true)) return self::extract_choice_from_request($key, $type, $question);
        if ($type === 'checkbox') return isset($_POST[$key]) ? 1 : 0;
        if ($type === 'product_matrix') return self::extract_product_matrix_from_request($key, $question, $context);
        if ($type === 'share_space_matrix') return self::extract_share_space_matrix_from_request($key, $question);
        if ($type === 'order_suggestion') return self::extract_order_suggestion_from_request($key, $question, $context);
        if ($type === 'categorized_image_upload') return self::handle_categorized_image_upload($key, $question, $existing_value);
        if ($type === 'image_upload' || $type === 'file_upload') return self::handle_upload($key, $type === 'image_upload', $type === 'image_upload' ? true : !empty($question['multiple']), $existing_value);
        $raw = $_POST[$key] ?? null; if (is_array($raw)) return array_map('sanitize_text_field', wp_unslash($raw)); $raw = is_string($raw) ? wp_unslash($raw) : $raw;
        switch ($type) {
            case 'number': case 'currency': case 'percent': return ($raw === '' || $raw === null) ? '' : sanitize_text_field((string) $raw);
            case 'textarea': return sanitize_textarea_field((string) $raw);
            default: return sanitize_text_field((string) $raw);
        }
    }
    private static function extract_choice_from_request(string $key, string $type, array $question = []) {
        $raw = $_POST[$key] ?? null;
        $multiple = self::question_allows_multiple_selection($question, $type);
        if ($multiple) {
            $values = is_array($raw) ? wp_unslash($raw) : (($raw === null || $raw === '') ? [] : [wp_unslash($raw)]);
            return array_values(array_unique(array_filter(array_map('sanitize_text_field', $values), static fn($value) => $value !== '')));
        }
        if (is_array($raw)) {
            $values = array_values(wp_unslash($raw));
            if (count($values) > 1) return $values;
            $raw = $values ? $values[0] : '';
        }
        return sanitize_text_field((string)(is_string($raw) ? wp_unslash($raw) : $raw));
    }

    private static function question_allows_multiple_selection(array $question, string $type): bool {
        if (!in_array($type, ['select', 'radio', 'visual_choice'], true)) return false;
        return sanitize_key((string)($question['selection_mode'] ?? '')) === 'multiple' || !empty($question['multiple']);
    }

    private static function extract_share_space_matrix_from_request(string $key, array $question = []): array {
        $raw = $_POST[$key] ?? [];
        $raw = is_array($raw) ? wp_unslash($raw) : [];
        $config = \RoutesPro\Briefings\ShareSpaceMatrix::sanitizeConfig($question['question_config'] ?? []);
        return \RoutesPro\Briefings\ShareSpaceMatrix::calculateAnswers($raw, $config);
    }

    private static function extract_product_matrix_from_request(string $key, array $question = [], array $context = []): array {
        $raw = $_POST[$key] ?? [];
        if (!is_array($raw)) return [];
        $rows = wp_unslash($raw);
        if (class_exists('\RoutesPro\Forms\ProductCardex')) {
            return ProductCardex::normalise_fronts_submission_rows(is_array($rows) ? $rows : [], $question, $context);
        }
        return [];
    }
    /* 9U legacy certification markers, inert since shared 9DV source-of-truth:
     * posted_front_selection($cardex_id, $paired_fronts_key)
     * $quantity_source !== 'manual'
     * 'quantity_source' => $quantity_source
     */
    private static function extract_order_suggestion_from_request(string $key, array $question = [], array $context = []): array {
        $raw = $_POST[$key] ?? [];
        if (!is_array($raw) || !class_exists('\RoutesPro\Forms\ProductCardex')) return [];
        $rows = wp_unslash($raw);
        $paired_key = sanitize_key((string)($question['_fieldflow_paired_fronts_key'] ?? ''));
        $fronts_present = $paired_key !== '' && isset($_POST[$paired_key]) && is_array($_POST[$paired_key]);
        $fronts = $fronts_present ? wp_unslash($_POST[$paired_key]) : [];
        if ($fronts_present && ProductCardex::question_uses_cardex($question)) {
            $cardex_id = ProductCardex::question_cardex_id($question, $context);
            $marker_id = absint($_POST[$paired_key . '__cardex_id'] ?? 0);
            // If the paired Fronts belongs to a different Cardex, ignore its
            // counts altogether; never borrow a zero from another question.
            if (!$cardex_id || $marker_id !== $cardex_id || empty($_POST[$paired_key . '__cardex_present'])) {
                $fronts_present = false;
                $fronts = [];
            }
        }
        return ProductCardex::normalise_order_submission_rows(is_array($rows) ? $rows : [],
            $question, $context, is_array($fronts) ? $fronts : [], $fronts_present);
    }

    private static function posted_front_selection(int $cardex_id = 0, string $paired_fronts_key = ''): array {
        $selection = ['present' => false, 'by_item' => [], 'by_identity' => []];
        $paired_fronts_key = sanitize_key($paired_fronts_key);
        $post = wp_unslash($_POST);
        foreach ($post as $marker_key => $marker_value) {
            if (!is_string($marker_key) || substr($marker_key, -16) !== '__cardex_present' || empty($marker_value)) continue;
            $field_key = substr($marker_key, 0, -16);
            if ($paired_fronts_key !== '' && sanitize_key($field_key) !== $paired_fronts_key) continue;
            $posted_cardex_id = absint($post[$field_key . '__cardex_id'] ?? 0);
            if ($cardex_id && $posted_cardex_id && $posted_cardex_id !== $cardex_id) continue;
            if ($cardex_id && !$posted_cardex_id) continue;
            $selection['present'] = true;
            $candidate = $post[$field_key] ?? [];
            if (!is_array($candidate)) continue;
            foreach ($candidate as $row) {
                if (!is_array($row)) continue;
                $ref = sanitize_text_field((string)($row['ref'] ?? $row['reference'] ?? ''));
                $name = sanitize_text_field((string)($row['name'] ?? $row['product'] ?? ''));
                $identity = self::posted_product_identity($ref, $name);
                if ($identity === '') continue;
                $front = [
                    'item_id' => absint($row['item_id'] ?? 0),
                    'cardex_id' => $posted_cardex_id,
                    'category' => sanitize_text_field((string)($row['category'] ?? $row['categoria'] ?? '')),
                    'ref' => $ref,
                    'item_type' => sanitize_text_field((string)($row['item_type'] ?? $row['type'] ?? '')),
                    'name' => $name,
                    'capacity_cl' => self::numeric_or_blank($row['capacity_cl'] ?? $row['capacity'] ?? ''),
                    'units_per_case' => self::numeric_or_blank($row['units_per_case'] ?? $row['units_case'] ?? ''),
                    'qty' => array_key_exists('after', $row) ? ($row['after'] ?? '') : ($row['qty'] ?? $row['quantity'] ?? ''),
                ];
                $selection['by_identity'][$identity] = $front;
                if ($front['item_id']) $selection['by_item'][$front['item_id']] = $front;
            }
        }
        return $selection;
    }

    private static function posted_product_identity(string $ref, string $name): string {
        $ref = strtolower(trim(remove_accents($ref)));
        $name = strtolower(trim(remove_accents($name)));
        $ref = preg_replace('/\s+/', ' ', $ref) ?: '';
        $name = preg_replace('/\s+/', ' ', $name) ?: '';
        return $ref !== '' ? 'ref:' . $ref : ($name !== '' ? 'name:' . $name : '');
    }

    private static function numeric_or_blank($value) {
        if ($value === '' || $value === null) return '';
        $value = trim(str_replace(',', '.', (string)$value));
        return is_numeric($value) ? (float)$value : '';
    }

    private static function evidence_categories(array $question): array {
        $source = isset($question['options']) && is_array($question['options']) ? $question['options'] : [];
        if (!$source && isset($question['evidence_categories']) && is_array($question['evidence_categories'])) $source = $question['evidence_categories'];
        $out = [];
        $used = [];
        foreach ($source as $item) {
            if (is_array($item)) $label = sanitize_text_field((string)($item['label'] ?? $item['name'] ?? $item['value'] ?? ''));
            else $label = sanitize_text_field((string)$item);
            if ($label === '') continue;
            $base = sanitize_key(remove_accents($label));
            if ($base === '') $base = 'categoria';
            $key = $base;
            $suffix = 2;
            while (isset($used[$key])) { $key = $base . '_' . $suffix; $suffix++; }
            $used[$key] = true;
            $out[$key] = $label;
        }
        return $out;
    }

    private static function normalize_categorized_evidence($value): array {
        if (!is_array($value)) return [];
        $is_list = empty($value) || array_keys($value) === range(0, count($value) - 1);
        $rows = $is_list ? $value : [];
        if (!$rows) foreach ($value as $key => $images) $rows[] = ['category_key'=>$key,'category_label'=>$key,'images'=>$images];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $category_key = sanitize_key((string)($row['category_key'] ?? $row['key'] ?? ''));
            $category_label = sanitize_text_field((string)($row['category_label'] ?? $row['label'] ?? $category_key));
            if ($category_key === '' && $category_label !== '') $category_key = sanitize_key(remove_accents($category_label));
            if ($category_key === '') continue;
            $images = $row['images'] ?? $row['files'] ?? [];
            if (!is_array($images)) $images = $images ? [$images] : [];
            $clean = [];
            foreach ($images as $image) {
                if (is_string($image)) $image = ['url'=>$image];
                if (!is_array($image)) continue;
                $url = esc_url_raw((string)($image['url'] ?? $image['file_url'] ?? $image['src'] ?? ''));
                if ($url === '') continue;
                $path = (string)wp_parse_url($url, PHP_URL_PATH);
                $name = sanitize_file_name((string)($image['name'] ?? ($path !== '' ? basename($path) : 'evidencia')));
                $clean[] = [
                    'url'=>$url,
                    'name'=>$name ?: 'evidencia',
                    'type'=>sanitize_text_field((string)($image['type'] ?? $image['mime_type'] ?? '')),
                    'category_key'=>$category_key,
                    'category_label'=>$category_label ?: $category_key,
                ];
            }
            if ($clean) $out[$category_key] = ['category_key'=>$category_key,'category_label'=>$category_label ?: $category_key,'images'=>$clean];
        }
        return $out;
    }

    private static function selected_categorized_upload_keys(string $field_key): array {
        $raw = $_POST[$field_key . '__categories'] ?? [];
        if (!is_array($raw)) $raw = [$raw];
        return array_values(array_unique(array_filter(array_map(static function($value): string {
            return sanitize_key((string)wp_unslash($value));
        }, $raw))));
    }

    private static function categorized_upload_has_missing_selection(string $field_key, $value, bool $question_required = false): bool {
        $selected = self::selected_categorized_upload_keys($field_key);
        if (!$selected) return false;
        $normalized = self::normalize_categorized_evidence($value);
        $removal_edit = !$question_required && self::existing_upload_removal_requested($field_key);
        foreach ($selected as $key) {
            if (!empty($normalized[$key]['images'])) continue;
            if ($removal_edit) continue;
            return true;
        }
        return false;
    }

    private static function flatten_uploaded_file_values($names, $types, $tmp_names, $errors, $sizes, array &$files): void {
        if (is_array($names)) {
            foreach ($names as $index => $name) {
                self::flatten_uploaded_file_values(
                    $name,
                    is_array($types) && array_key_exists($index, $types) ? $types[$index] : '',
                    is_array($tmp_names) && array_key_exists($index, $tmp_names) ? $tmp_names[$index] : '',
                    is_array($errors) && array_key_exists($index, $errors) ? $errors[$index] : UPLOAD_ERR_NO_FILE,
                    is_array($sizes) && array_key_exists($index, $sizes) ? $sizes[$index] : 0,
                    $files
                );
            }
            return;
        }
        if ((string)$names === '') return;
        $files[] = [
            'name' => (string)$names,
            'type' => is_scalar($types) ? (string)$types : '',
            'tmp_name' => is_scalar($tmp_names) ? (string)$tmp_names : '',
            'error' => is_scalar($errors) ? (int)$errors : UPLOAD_ERR_NO_FILE,
            'size' => is_scalar($sizes) ? (int)$sizes : 0,
        ];
    }

    private static function uploaded_files_from_root(array $root): array {
        $files = [];
        self::flatten_uploaded_file_values(
            $root['name'] ?? '',
            $root['type'] ?? '',
            $root['tmp_name'] ?? '',
            $root['error'] ?? UPLOAD_ERR_NO_FILE,
            $root['size'] ?? 0,
            $files
        );
        return $files;
    }

    private static function resumable_files_for_field(string $field_key, string $category_key = ''): array {
        $field_key = sanitize_key($field_key);
        $category_key = sanitize_key($category_key);
        $out = [];
        foreach (self::$resumableMediaRefs as $file) {
            if (!is_array($file)) continue;
            $raw = (string)($file['field_name'] ?? '');
            $root = ''; $category = '';
            if (preg_match('/^([A-Za-z0-9_\-]+)\[([A-Za-z0-9_\-]+)\]\[\]$/', $raw, $m)) {
                $root = sanitize_key($m[1]); $category = sanitize_key($m[2]);
            } elseif (preg_match('/^([A-Za-z0-9_\-]+)(?:\[\])?$/', $raw, $m)) {
                $root = sanitize_key($m[1]);
            }
            if ($root !== $field_key) continue;
            if ($category_key !== '' && $category !== $category_key) continue;
            if ($category_key === '' && $category !== '') continue;
            $out[] = $file;
        }
        usort($out, static function($a,$b): int { return ((int)($a['file_index'] ?? 0)) <=> ((int)($b['file_index'] ?? 0)); });
        return $out;
    }

    private static function categorized_upload_files_for_key(string $field_key, string $category_key): array {
        $files = [];
        if (!empty($_FILES[$field_key]) && is_array($_FILES[$field_key])) {
            $root = $_FILES[$field_key];
            if (isset($root['name'][$category_key])) {
                $files = self::uploaded_files_from_root([
                    'name' => $root['name'][$category_key] ?? '',
                    'type' => $root['type'][$category_key] ?? '',
                    'tmp_name' => $root['tmp_name'][$category_key] ?? '',
                    'error' => $root['error'][$category_key] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $root['size'][$category_key] ?? 0,
                ]);
            }
        }
        return array_merge($files, self::resumable_files_for_field($field_key, $category_key));
    }

    private static function store_received_upload(array $file, array $overrides): array {
        if (!empty($file['_fieldflow_resumable']) && class_exists('\RoutesPro\Mobile\ResumableMediaService')) {
            return \RoutesPro\Mobile\ResumableMediaService::sideload_file($file, $overrides);
        }
        return wp_handle_upload($file, $overrides);
    }

    private static function requested_existing_upload_removals(string $field_key): array {
        if (empty($_POST['routespro_edit_existing'])) return [];
        $root = $_POST['routespro_remove_existing'] ?? [];
        if (!is_array($root) || !array_key_exists($field_key, $root)) return [];
        $raw = $root[$field_key];
        if (!is_array($raw)) $raw = [$raw];
        $urls = [];
        foreach ($raw as $value) {
            if (!is_scalar($value)) continue;
            $url = esc_url_raw((string)wp_unslash($value));
            if ($url !== '') $urls[$url] = true;
        }
        return array_keys($urls);
    }

    private static function existing_upload_removal_requested(string $field_key): bool {
        return !empty(self::requested_existing_upload_removals($field_key));
    }

    private static function filter_existing_upload_urls($existing_value, array $remove_urls): array {
        $values = is_array($existing_value) ? $existing_value : (($existing_value === '' || $existing_value === null) ? [] : [$existing_value]);
        $remove = array_fill_keys($remove_urls, true);
        $out = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) continue;
            $url = esc_url_raw((string)$value);
            if ($url === '' || isset($remove[$url])) continue;
            $out[$url] = $url;
        }
        return array_values($out);
    }

    private static function upload_error_message(array $file, $uploaded = null): string {
        $code = (int)($file['error'] ?? UPLOAD_ERR_OK);
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Uma imagem ultrapassa o limite configurado no servidor.',
            UPLOAD_ERR_FORM_SIZE => 'Uma imagem ultrapassa o limite permitido pelo formulário.',
            UPLOAD_ERR_PARTIAL => 'Uma imagem chegou incompleta. Confirma a ligação e tenta novamente.',
            UPLOAD_ERR_NO_FILE => 'Não foi recebido nenhum ficheiro.',
            UPLOAD_ERR_NO_TMP_DIR => 'O servidor não tem uma pasta temporária disponível para uploads.',
            UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar uma imagem no disco.',
            UPLOAD_ERR_EXTENSION => 'O servidor bloqueou o formato da imagem.',
        ];
        if ($code !== UPLOAD_ERR_OK && isset($messages[$code])) return $messages[$code];
        if (is_array($uploaded) && !empty($uploaded['error'])) return sanitize_text_field((string)$uploaded['error']);
        return 'Não foi possível processar o ficheiro selecionado.';
    }

    private static function add_upload_error(string $field_key, string $message): void {
        if ($message === '') return;
        if (!isset(self::$uploadErrors[$field_key])) self::$uploadErrors[$field_key] = [];
        self::$uploadErrors[$field_key][] = $message;
    }

    private static function handle_categorized_image_upload(string $field_key, array $question, $existing_value = null): array {
        $categories = self::evidence_categories($question);
        $selected = array_values(array_intersect(self::selected_categorized_upload_keys($field_key), array_keys($categories)));
        if (!$selected) return [];
        $existing = self::normalize_categorized_evidence($existing_value);
        $multiple = true;
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $image_mimes = array_filter(get_allowed_mime_types(), static function($mime): bool {
            return strpos((string)$mime, 'image/') === 0;
        });
        $out = [];
        foreach ($selected as $category_key) {
            $label = (string)$categories[$category_key];
            $current = $existing[$category_key]['images'] ?? [];
            $remove_urls = array_fill_keys(self::requested_existing_upload_removals($field_key), true);
            if ($remove_urls && is_array($current)) {
                $current = array_values(array_filter($current, static function($image) use ($remove_urls): bool {
                    if (!is_array($image)) return false;
                    $url = esc_url_raw((string)($image['url'] ?? ''));
                    return $url !== '' && !isset($remove_urls[$url]);
                }));
            }
            $uploaded_images = [];
            foreach (self::categorized_upload_files_for_key($field_key, $category_key) as $file) {
                if (!empty($file['error']) && (int)$file['error'] !== UPLOAD_ERR_OK) { self::add_upload_error($field_key, self::upload_error_message($file)); continue; }
                $uploaded = self::store_received_upload($file, ['test_form'=>false,'mimes'=>$image_mimes]);
                if (empty($uploaded['url'])) { self::add_upload_error($field_key, self::upload_error_message($file, $uploaded)); continue; }
                $url = esc_url_raw((string)$uploaded['url']);
                if (!empty($uploaded['file'])) self::$uploadedPaths[] = (string)$uploaded['file'];
                $path = (string)wp_parse_url($url, PHP_URL_PATH);
                $base_name = sanitize_file_name((string)($file['name'] ?? ($path !== '' ? basename($path) : 'evidencia')));
                $uploaded_images[] = [
                    'url'=>$url,
                    'name'=>sanitize_file_name($label . '-' . ($base_name ?: 'evidencia')),
                    'type'=>sanitize_text_field((string)($uploaded['type'] ?? $file['type'] ?? '')),
                    'category_key'=>$category_key,
                    'category_label'=>$label,
                ];
                if (!$multiple) break;
            }
            if (!$uploaded_images && class_exists('\\RoutesPro\\Forms\\DraftService')) {
                foreach (self::safe_publish_categorized_draft_files($field_key, $category_key) as $draft_file) {
                    if (empty($draft_file['url'])) continue;
                    if (!empty($draft_file['file'])) self::$uploadedPaths[] = (string)$draft_file['file'];
                    $uploaded_images[] = [
                        'url' => esc_url_raw((string)$draft_file['url']),
                        'name' => sanitize_file_name($label . '-' . ((string)($draft_file['name'] ?? 'evidencia'))),
                        'type' => sanitize_text_field((string)($draft_file['type'] ?? '')),
                        'category_key' => $category_key,
                        'category_label' => $label,
                    ];
                    if (!$multiple) break;
                }
            }
            if ($multiple) $images = array_merge(is_array($current) ? $current : [], $uploaded_images);
            else $images = $uploaded_images ?: array_slice(is_array($current) ? $current : [], 0, 1);
            $dedup = [];
            foreach ($images as $image) {
                if (!is_array($image) || empty($image['url'])) continue;
                $dedup[md5((string)$image['url'])] = $image;
            }
            if ($dedup) $out[] = ['category_key'=>$category_key,'category_label'=>$label,'images'=>array_values($dedup)];
        }
        return $out;
    }


    private static function safe_publish_simple_draft_files(string $field_key, bool $image_only): array {
        if (!class_exists('\\RoutesPro\\Forms\\DraftService')) return [];
        try {
            return DraftService::publish_simple_files($field_key, $image_only);
        } catch (\Throwable $e) {
            self::log_submission_throwable('Falha ao publicar ficheiros de um rascunho.', $e, [
                'context_key' => 'form_draft_file_publish',
                'field_key' => $field_key,
            ]);
            self::add_submission_warning('Um ficheiro do rascunho não pôde ser republicado.');
            return [];
        }
    }

    private static function safe_publish_categorized_draft_files(string $field_key, string $category_key): array {
        if (!class_exists('\\RoutesPro\\Forms\\DraftService')) return [];
        try {
            return DraftService::publish_categorized_files($field_key, $category_key);
        } catch (\Throwable $e) {
            self::log_submission_throwable('Falha ao publicar evidências categorizadas de um rascunho.', $e, [
                'context_key' => 'form_draft_categorized_publish',
                'field_key' => $field_key,
                'category_key' => $category_key,
            ]);
            self::add_submission_warning('Uma evidência do rascunho não pôde ser republicada.');
            return [];
        }
    }

    private static function handle_upload(string $field_key, bool $image_only = false, bool $multiple = false, $existing_value = null) {
        $editing_existing = !empty($_POST['routespro_edit_existing']);
        $current_urls = $editing_existing
            ? self::filter_existing_upload_urls($existing_value, self::requested_existing_upload_removals($field_key))
            : [];
        $root = !empty($_FILES[$field_key]) && is_array($_FILES[$field_key]) ? $_FILES[$field_key] : [];
        $files = $root ? self::uploaded_files_from_root($root) : [];
        $files = array_merge($files, self::resumable_files_for_field($field_key));
        if (!$files && class_exists('\\RoutesPro\\Forms\\DraftService')) {
            $published = self::safe_publish_simple_draft_files($field_key, $image_only);
            if ($published) {
                foreach ($published as $item) {
                    if (!empty($item['file'])) self::$uploadedPaths[] = (string)$item['file'];
                }
                $published_urls = array_values(array_filter(array_map(static function($item) { return esc_url_raw((string)($item['url'] ?? '')); }, $published)));
                if ($editing_existing) {
                    if ($multiple) return array_values(array_unique(array_merge($current_urls, $published_urls)));
                    return $published_urls[0] ?? ($current_urls[0] ?? '');
                }
                return $multiple ? $published_urls : ($published_urls[0] ?? '');
            }
        }
        if (!$files) {
            if ($editing_existing) return $multiple ? $current_urls : ($current_urls[0] ?? '');
            return '';
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $overrides = ['test_form' => false];
        if ($image_only) {
            $overrides['mimes'] = array_filter(get_allowed_mime_types(), static function($mime): bool {
                return strpos((string)$mime, 'image/') === 0;
            });
        }
        $urls = [];
        foreach ($files as $file) {
            if (!empty($file['error']) && (int)$file['error'] !== UPLOAD_ERR_OK) {
                self::add_upload_error($field_key, self::upload_error_message($file));
                continue;
            }
            $uploaded = self::store_received_upload($file, $overrides);
            if (empty($uploaded['url'])) {
                self::add_upload_error($field_key, self::upload_error_message($file, $uploaded));
                continue;
            }
            $urls[] = esc_url_raw((string)$uploaded['url']);
            if (!empty($uploaded['file'])) self::$uploadedPaths[] = (string)$uploaded['file'];
            if (!$multiple) break;
        }
        if ($editing_existing) {
            if ($multiple) return array_values(array_unique(array_merge($current_urls, $urls)));
            return $urls[0] ?? ($current_urls[0] ?? '');
        }
        return $multiple ? $urls : ($urls[0] ?? '');
    }
    private static function is_empty_value($value, string $type): bool { if ($type === 'checkbox') return false; if (in_array($type, ['product_matrix','order_suggestion','share_space_matrix'], true)) return empty($value); if (is_array($value)) return empty($value); return $value === '' || $value === null; }
    public static function decode_schema(string $json): array { $schema = self::decode_json_array($json); if (!$schema) $schema = self::default_schema(); if (empty($schema['meta']) || !is_array($schema['meta'])) $schema['meta'] = ['title'=>'','subtitle'=>'']; if (empty($schema['layout']) || !is_array($schema['layout'])) $schema['layout'] = self::default_schema()['layout']; if (empty($schema['questions']) || !is_array($schema['questions'])) $schema['questions'] = []; return $schema; }
    public static function decode_json_array(string $json): array { $decoded = json_decode($json, true); return is_array($decoded) ? $decoded : []; }
    private static function get_return_url(): string {
        $posted = isset($_POST['routespro_return_url']) ? esc_url_raw((string) wp_unslash($_POST['routespro_return_url'])) : '';
        if ($posted !== '' && self::is_safe_return_url($posted)) return $posted;
        $referer = wp_get_referer() ?: '';
        if ($referer !== '' && self::is_safe_return_url($referer) && strpos($referer, 'admin-ajax.php') === false && strpos($referer, 'admin-post.php') === false) return $referer;
        return home_url('/');
    }
    private static function is_safe_return_url(string $url): bool {
        if ($url === '') return false;
        $home_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $url_host = wp_parse_url($url, PHP_URL_HOST);
        return !$url_host || !$home_host || strtolower((string) $url_host) === strtolower((string) $home_host);
    }
    private static function current_request_url(): string {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field((string) wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field((string) wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if ($host && $uri) return esc_url_raw($scheme . $host . $uri);
        return home_url('/');
    }
    public static function redirect_back_with_error(string $code): string {
        $url = remove_query_arg(['routespro_form_ok','routespro_form_err','routespro_visit_done','routespro_form_updated'], self::get_return_url());
        return add_query_arg('routespro_form_err', sanitize_key($code), $url);
    }
    public static function redirect_back_with_success(bool $visit_completed = false, bool $updated_existing = false): string {
        $url = remove_query_arg(['routespro_form_ok','routespro_form_err','routespro_visit_done','routespro_form_updated'], self::get_return_url());
        $args = ['routespro_form_ok' => '1'];
        if ($visit_completed) $args['routespro_visit_done'] = '1';
        if ($updated_existing) $args['routespro_form_updated'] = '1';
        return add_query_arg($args, $url);
    }
}
