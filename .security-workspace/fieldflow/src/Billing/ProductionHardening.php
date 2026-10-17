<?php
namespace RoutesPro\Billing;

use RoutesPro\Core\AuditLog;
use RoutesPro\Core\EventBus;
use RoutesPro\Core\ReconciliationService;
use RoutesPro\Integrations\SecretVault;
use RoutesPro\SaaS\PlanRepository;
use RoutesPro\SaaS\UsageService;
use WP_Error;

if (!defined('ABSPATH')) exit;

class ProductionHardening {
    private static bool $registered = false;

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_action('fieldflow_core_event', [self::class, 'onCoreEvent'], 30, 4);
        add_action('fieldflow_billing_maintenance', [self::class, 'maintenance'], 30);
    }

    public static function consentTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_billing_consents';
    }

    public static function emailLogTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_billing_email_log';
    }

    public static function tablesReady(): bool {
        global $wpdb;
        foreach ([self::consentTable(), self::emailLogTable()] as $table) {
            if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return false;
        }
        return true;
    }

    public static function templateDefaults(): array {
        return [
            'account_created' => [
                'label' => 'Conta criada',
                'subject' => 'A tua conta FieldFlow está pronta para concluir a subscrição',
                'body' => "Olá {{name}},\n\nA tua conta foi criada e o plano {{plan}} ficou reservado para {{client}}.\n\nContinua o processo aqui: {{resume_url}}\n\nO acesso pago só será ativado depois da confirmação do pagamento.\n\n{{site_name}}",
            ],
            'checkout_abandoned' => [
                'label' => 'Checkout abandonado',
                'subject' => 'A tua subscrição FieldFlow ficou por concluir',
                'body' => "Olá {{name}},\n\nA subscrição do plano {{plan}} para {{client}} ainda não foi concluída.\n\nRetoma o processo em segurança: {{resume_url}}\n\nEsta ligação expira automaticamente.\n\n{{site_name}}",
            ],
            'payment_confirmed' => [
                'label' => 'Pagamento confirmado',
                'subject' => 'Pagamento confirmado e acesso FieldFlow ativado',
                'body' => "Olá {{name}},\n\nO pagamento da subscrição {{plan}} para {{client}} foi confirmado.\n\nPodes gerir o plano e a faturação aqui: {{portal_url}}\n\n{{site_name}}",
            ],
            'payment_failed' => [
                'label' => 'Pagamento falhado',
                'subject' => 'Ação necessária na subscrição FieldFlow',
                'body' => "Olá {{name}},\n\nNão foi possível concluir o pagamento da subscrição {{plan}} para {{client}}.\n\nO acesso permanece disponível até {{grace_until}}. Atualiza o pagamento em: {{portal_url}}\n\nOs dados operacionais permanecem guardados.\n\n{{site_name}}",
            ],
            'subscription_suspended' => [
                'label' => 'Subscrição suspensa',
                'subject' => 'A subscrição FieldFlow foi suspensa',
                'body' => "Olá {{name}},\n\nO período de tolerância da subscrição {{plan}} para {{client}} terminou.\n\nOs módulos pagos estão restritos, mas nenhum dado foi eliminado.\n\nRegulariza a situação em: {{portal_url}}\n\n{{site_name}}",
            ],
            'plan_changed' => [
                'label' => 'Plano alterado',
                'subject' => 'O plano FieldFlow foi atualizado',
                'body' => "Olá {{name}},\n\nO plano de {{client}} foi alterado para {{plan}}.\n\nAs capacidades e os limites foram reconciliados automaticamente. Os dados existentes permanecem preservados.\n\nConsulta a subscrição em: {{portal_url}}\n\n{{site_name}}",
            ],
            'renewal_upcoming' => [
                'label' => 'Renovação próxima',
                'subject' => 'A renovação FieldFlow aproxima-se',
                'body' => "Olá {{name}},\n\nA subscrição {{plan}} de {{client}} renova em {{period_end}}.\n\nPodes consultar o método de pagamento e a faturação em: {{portal_url}}\n\n{{site_name}}",
            ],
            'subscription_cancelled' => [
                'label' => 'Cancelamento agendado',
                'subject' => 'Cancelamento da subscrição FieldFlow',
                'body' => "Olá {{name}},\n\nA subscrição {{plan}} de {{client}} está marcada para terminar em {{period_end}}.\n\nAté essa data o serviço permanece disponível. Os dados não são apagados automaticamente.\n\nConsulta ou reativa a subscrição em: {{portal_url}}\n\n{{site_name}}",
            ],
        ];
    }

    public static function recordConsent(int $intentId, array $consent, int $userId = 0, int $clientId = 0): int {
        global $wpdb;
        if ($intentId <= 0 || !self::tablesReady()) return 0;
        $termsAccepted = !empty($consent['terms_accepted']);
        $privacyAccepted = !empty($consent['privacy_accepted']);
        if (!$termsAccepted || !$privacyAccepted) return 0;
        $settings = Settings::get();
        $existing = (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::consentTable().' WHERE intent_id=%d LIMIT 1', $intentId));
        $row = [
            'intent_id' => $intentId,
            'user_id' => $userId,
            'client_id' => $clientId,
            'terms_version' => sanitize_text_field((string)($settings['terms_version'] ?? '1.0')),
            'privacy_version' => sanitize_text_field((string)($settings['privacy_version'] ?? '1.0')),
            'terms_url' => esc_url_raw((string)($settings['terms_url'] ?? '')),
            'privacy_url' => esc_url_raw((string)($settings['privacy_url'] ?? '')),
            'marketing_consent' => !empty($consent['marketing_consent']) ? 1 : 0,
            'source' => sanitize_key((string)($consent['source'] ?? 'public_page')),
            'ip_hash' => self::ipHash(),
            'user_agent' => substr(sanitize_text_field((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255),
            'accepted_at' => current_time('mysql'),
        ];
        if ($existing > 0) {
            $wpdb->update(self::consentTable(), $row, ['id'=>$existing]);
            $id = $existing;
        } else {
            $wpdb->insert(self::consentTable(), $row);
            $id = (int)$wpdb->insert_id;
        }
        AuditLog::record('billing.consent.recorded', 'billing_consent', $id, null, [
            'intent_id'=>$intentId,
            'user_id'=>$userId,
            'client_id'=>$clientId,
            'terms_version'=>$row['terms_version'],
            'privacy_version'=>$row['privacy_version'],
            'marketing_consent'=>(bool)$row['marketing_consent'],
        ], ['actor_user_id'=>$userId, 'client_id'=>$clientId]);
        return $id;
    }

    public static function bindConsent(int $intentId, int $userId, int $clientId): void {
        global $wpdb;
        if ($intentId <= 0 || !self::tablesReady()) return;
        $wpdb->update(self::consentTable(), [
            'user_id'=>max(0, $userId),
            'client_id'=>max(0, $clientId),
        ], ['intent_id'=>$intentId]);
    }

    public static function hasRequiredConsent(int $intentId): bool {
        global $wpdb;
        if (empty(Settings::get()['legal_consent_required'])) return true;
        if ($intentId <= 0 || !self::tablesReady()) return false;
        return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.self::consentTable().' WHERE intent_id=%d', $intentId)) > 0;
    }

    public static function onCoreEvent(string $eventName, array $payload, array $context, string $eventUuid): void {
        if (strpos($eventName, 'billing.') !== 0) return;
        $template = '';
        if ($eventName === 'billing.signup_user_attached') $template = 'account_created';
        elseif (in_array($eventName, ['billing.invoice_paid','billing.payment_succeeded'], true)) $template = 'payment_confirmed';
        elseif (in_array($eventName, ['billing.invoice_payment_failed','billing.payment_failed'], true)) $template = 'payment_failed';
        elseif ($eventName === 'billing.grace_expired') $template = 'subscription_suspended';
        elseif (in_array($eventName, ['billing.subscription_changed','billing.subscription_updated'], true)) $template = 'plan_changed';
        elseif ($eventName === 'billing.subscription_synced' && !empty($payload['cancel_at_period_end'])) $template = 'subscription_cancelled';
        if ($template === '') return;
        $clientId = absint($payload['client_id'] ?? $context['client_id'] ?? 0);
        $userId = absint($payload['user_id'] ?? $payload['billing_user_id'] ?? 0);
        $intentId = (string)($context['entity_type'] ?? '') === 'billing_signup_intent' ? absint($context['entity_id'] ?? 0) : absint($payload['intent_id'] ?? 0);
        $reference = $payload['invoice_id'] ?? $payload['external_subscription_id'] ?? ($intentId > 0 ? (string)$intentId : '');
        if ($reference === '') $reference = $eventUuid;
        $messageKey = $template . ':' . $reference;
        self::sendTransactional($template, [
            'client_id'=>$clientId,
            'user_id'=>$userId,
            'intent_id'=>$intentId,
            'grace_until'=>(string)($payload['grace_until'] ?? ''),
            'period_end'=>(string)($payload['current_period_end'] ?? ''),
            'plan_id'=>absint($payload['plan_id'] ?? 0),
        ], $messageKey);
    }

    public static function sendTransactional(string $templateKey, array $context = [], string $messageKey = '') {
        global $wpdb;
        $settings = Settings::get();
        if (empty($settings['transactional_emails_enabled']) && empty($context['force'])) return new WP_Error('fieldflow_billing_email_disabled', 'Os emails transacionais estão desativados.');
        $templates = self::templateDefaults();
        if (!isset($templates[$templateKey])) return new WP_Error('fieldflow_billing_email_template', 'Template de email desconhecido.');
        $custom = (array)($settings['email_templates'][$templateKey] ?? []);
        $subject = sanitize_text_field((string)($custom['subject'] ?? $templates[$templateKey]['subject']));
        $body = (string)($custom['body'] ?? $templates[$templateKey]['body']);
        $resolved = self::resolveContext($context);
        $to = sanitize_email((string)($context['to'] ?? $resolved['email'] ?? ''));
        if ($to === '') return new WP_Error('fieldflow_billing_email_recipient', 'Não foi possível determinar o destinatário do email.');
        $messageKey = sanitize_text_field($messageKey !== '' ? $messageKey : ($templateKey . ':' . md5($to . '|' . wp_json_encode($context))));
        if (self::emailAlreadySent($messageKey)) return ['sent'=>false, 'duplicate'=>true, 'message_key'=>$messageKey];
        $replace = [];
        foreach ($resolved as $key=>$value) $replace['{{'.$key.'}}'] = (string)$value;
        $subject = strtr($subject, $replace);
        $body = strtr($body, $replace);
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        $replyTo = sanitize_email((string)($settings['billing_reply_to'] ?? ''));
        if ($replyTo !== '') $headers[] = 'Reply-To: ' . $replyTo;
        $mailDispatch = \RoutesPro\Services\MailDispatchService::dispatch($to, $subject, $body, $headers, [], ['source'=>'billing_transactional','template_key'=>$templateKey,'message_key'=>$messageKey,'idempotency_key'=>'billing_transactional|'.$messageKey]);
        $sent = !empty($mailDispatch['accepted']);
        self::logEmail($messageKey, $templateKey, $to, $subject, $sent ? 'sent' : 'failed', $sent ? '' : (string)($mailDispatch['error'] ?? 'wp_mail devolveu false.'), $context + ['mail_dispatch_id'=>(string)($mailDispatch['dispatch_id'] ?? '')]);
        return $sent ? ['sent'=>true, 'message_key'=>$messageKey] : new WP_Error('fieldflow_billing_email_failed', 'O WordPress não conseguiu enviar o email.');
    }

    public static function sendTestEmail(string $to = '') {
        $to = sanitize_email($to ?: (string)Settings::get()['notification_email']);
        return self::sendTransactional('payment_confirmed', [
            'to'=>$to,
            'name'=>'Teste FieldFlow',
            'client_name'=>'Organização de teste',
            'plan_name'=>'FieldFlow Intelligence',
            'force'=>true,
        ], 'test:' . md5($to . '|' . microtime(true)));
    }

    public static function sendRecoveryIntent(int $intentId, bool $manual = false) {
        global $wpdb;
        if ($intentId <= 0) return new WP_Error('fieldflow_billing_intent', 'Intent inválido.');
        $intent = $wpdb->get_row($wpdb->prepare(
            'SELECT i.*,p.name plan_name,u.display_name user_name,u.user_email FROM '.SignupService::table().' i LEFT JOIN '.$wpdb->prefix.'routespro_saas_plans p ON p.id=i.plan_id LEFT JOIN '.$wpdb->users.' u ON u.ID=i.user_id WHERE i.id=%d LIMIT 1',
            $intentId
        ), ARRAY_A);
        if (!$intent) return new WP_Error('fieldflow_billing_intent_missing', 'O processo de subscrição já não existe.');
        if (in_array((string)$intent['status'], ['completed','expired','cancelled'], true)) return new WP_Error('fieldflow_billing_intent_closed', 'Este processo já está fechado.');
        $meta = json_decode((string)($intent['meta_json'] ?? ''), true);
        if (!is_array($meta)) $meta = [];
        $token = SecretVault::decrypt((string)($meta['recovery_token_enc'] ?? ''));
        if ($token === '') return new WP_Error('fieldflow_billing_recovery_token', 'Este processo antigo não tem uma ligação de recuperação disponível.');
        $email = sanitize_email((string)($intent['user_email'] ?: $intent['email']));
        if ($email === '') return new WP_Error('fieldflow_billing_recovery_email', 'O processo não tem um email válido.');
        $result = self::sendTransactional('checkout_abandoned', [
            'to'=>$email,
            'name'=>(string)($intent['user_name'] ?: $email),
            'client_id'=>absint($intent['client_id'] ?? 0),
            'plan_id'=>absint($intent['plan_id'] ?? 0),
            'plan_name'=>(string)($intent['plan_name'] ?? ''),
            'resume_url'=>SignupService::resumeUrl($token, $intent, true),
            'intent_id'=>$intentId,
        ], 'recovery:' . $intentId . ':' . (int)($meta['recovery_count'] ?? 0));
        if (is_wp_error($result)) return $result;
        $meta['last_recovery_at'] = current_time('mysql');
        $meta['recovery_count'] = (int)($meta['recovery_count'] ?? 0) + 1;
        $meta['recovery_manual'] = $manual;
        $wpdb->update(SignupService::table(), ['meta_json'=>wp_json_encode($meta), 'updated_at'=>current_time('mysql')], ['id'=>$intentId]);
        return $result;
    }

    public static function maintenance(): array {
        return [
            'recovery' => self::recoverAbandonedIntents(),
            'renewals' => self::sendRenewalReminders(),
        ];
    }

    public static function recoverAbandonedIntents(): array {
        global $wpdb;
        $settings = Settings::get();
        if (empty($settings['checkout_recovery_enabled'])) return ['sent'=>0,'skipped'=>0,'failed'=>0];
        $hours = max(1, min(168, absint($settings['abandoned_checkout_hours'] ?? 12)));
        $max = max(1, min(5, absint($settings['max_recovery_emails'] ?? 2)));
        $cutoff = wp_date('Y-m-d H:i:s', current_time('timestamp') - $hours * HOUR_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,meta_json FROM ".SignupService::table()." WHERE status IN ('registered','checkout_pending','checkout_open') AND updated_at<=%s AND (expires_at IS NULL OR expires_at>%s) ORDER BY updated_at ASC LIMIT 100",
            $cutoff,
            current_time('mysql')
        ), ARRAY_A) ?: [];
        $sent=0;$skipped=0;$failed=0;
        foreach ($rows as $row) {
            $meta = json_decode((string)$row['meta_json'], true);
            if (!is_array($meta)) $meta=[];
            if ((int)($meta['recovery_count'] ?? 0) >= $max) {$skipped++;continue;}
            $last = strtotime((string)($meta['last_recovery_at'] ?? '')) ?: 0;
            if ($last > 0 && $last > current_time('timestamp') - $hours * HOUR_IN_SECONDS) {$skipped++;continue;}
            $result = self::sendRecoveryIntent((int)$row['id']);
            if (is_wp_error($result)) $failed++; else $sent++;
        }
        return compact('sent','skipped','failed');
    }

    public static function sendRenewalReminders(): array {
        global $wpdb;
        $settings = Settings::get();
        if (empty($settings['renewal_emails_enabled'])) return ['sent'=>0,'skipped'=>0,'failed'=>0];
        $days = max(1, min(30, absint($settings['renewal_notice_days'] ?? 5)));
        $from = current_time('mysql');
        $to = wp_date('Y-m-d H:i:s', current_time('timestamp') + $days * DAY_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM ".BillingRepository::subscriptionsTable()." WHERE status IN ('active','trial','trialing') AND cancel_at_period_end=0 AND current_period_end BETWEEN %s AND %s ORDER BY current_period_end ASC LIMIT 200",
            $from,
            $to
        ), ARRAY_A) ?: [];
        $sent=0;$skipped=0;$failed=0;
        foreach ($rows as $row) {
            $key = 'renewal:' . (int)$row['id'] . ':' . (string)$row['current_period_end'];
            if (self::emailAlreadySent($key)) {$skipped++;continue;}
            $result = self::sendTransactional('renewal_upcoming', [
                'client_id'=>(int)$row['client_id'],
                'user_id'=>(int)$row['billing_user_id'],
                'plan_id'=>(int)$row['plan_id'],
                'period_end'=>(string)$row['current_period_end'],
            ], $key);
            if (is_wp_error($result)) $failed++; else $sent++;
        }
        return compact('sent','skipped','failed');
    }

    public static function conversionMetrics(int $days = 30): array {
        global $wpdb;
        $days = max(1, min(365, $days));
        $since = wp_date('Y-m-d H:i:s', current_time('timestamp') - $days * DAY_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare('SELECT status,COUNT(*) total FROM '.SignupService::table().' WHERE created_at>=%s GROUP BY status', $since), ARRAY_A) ?: [];
        $statuses=[];$total=0;
        foreach ($rows as $row) {$statuses[(string)$row['status']] = (int)$row['total'];$total += (int)$row['total'];}
        $completed = (int)($statuses['completed'] ?? 0);
        $startedCheckout = $completed + (int)($statuses['checkout_open'] ?? 0) + (int)($statuses['checkout_pending'] ?? 0);
        $emails = ['sent'=>0,'failed'=>0];
        if (self::tablesReady()) {
            $emailRows = $wpdb->get_results($wpdb->prepare('SELECT status,COUNT(*) total FROM '.self::emailLogTable().' WHERE created_at>=%s GROUP BY status', $since), ARRAY_A) ?: [];
            foreach ($emailRows as $row) $emails[(string)$row['status']] = (int)$row['total'];
        }
        return [
            'days'=>$days,
            'intents'=>$total,
            'registered'=>(int)($statuses['registered'] ?? 0),
            'checkout_started'=>$startedCheckout,
            'completed'=>$completed,
            'abandoned'=>(int)($statuses['expired'] ?? 0) + (int)($statuses['cancelled'] ?? 0),
            'conversion_rate'=>$total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
            'statuses'=>$statuses,
            'emails'=>$emails,
            'consents'=>self::tablesReady() ? (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.self::consentTable().' WHERE accepted_at>=%s', $since)) : 0,
        ];
    }

    public static function diagnostics(): array {
        global $wpdb;
        $settings = Settings::get();
        $mode = Settings::mode();
        $checks = [];
        $checks[] = self::check('payments_enabled', 'Pagamentos ativos', Settings::isEnabled(), Settings::isEnabled() ? 'Checkout disponível.' : 'Ativa os pagamentos na configuração.');
        $local = $mode === 'sandbox' && (string)($settings['sandbox_driver'] ?? 'local') === 'local';
        $checks[] = self::check('secret_key', 'Chave do fornecedor', $local || Settings::secretKey($mode) !== '', $local ? 'O simulador local não precisa de chave.' : 'Falta configurar a chave secreta deste ambiente.');
        $checks[] = self::check('webhook_secret', 'Webhook', $local || Settings::webhookSecret($mode) !== '', $local ? 'O simulador local não precisa de webhook.' : 'Falta configurar o webhook secret.');
        $signupUrl = Settings::signupUrl();
        $signupPageId = Settings::signupPageId();
        $portalUrl = Settings::portalUrl();
        $portalPageId = Settings::portalPageId();
        $checks[] = self::check('signup_page', 'Página pública', $signupPageId > 0 && get_post_status($signupPageId) === 'publish', $signupUrl);
        $portalReady = $portalPageId > 0 && Settings::pageContainsShortcode($portalPageId, 'fieldflow_billing_portal');
        $checks[] = self::check('portal_page', 'Portal de faturação', $portalReady, $portalReady ? $portalUrl : 'Seleciona uma página publicada com [fieldflow_billing_portal] em Faturação > Configuração.');
        $checks[] = self::check('hardening_tables', 'Tabelas de produção', self::tablesReady(), self::tablesReady() ? 'Consentimentos e emails disponíveis.' : 'Executa novamente a atualização do plugin.');
        $checks[] = self::check('legal_urls', 'Documentos legais', empty($settings['legal_consent_required']) || (esc_url_raw((string)$settings['terms_url']) !== '' && esc_url_raw((string)$settings['privacy_url']) !== ''), 'Configura Termos e Política de Privacidade.');
        $failedEvents = BillingRepository::tablesReady() ? (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".BillingRepository::eventsTable()." WHERE environment=%s AND status='failed'", $mode)) : 0;
        $checks[] = self::check('failed_events', 'Eventos de pagamento', $failedEvents === 0, $failedEvents ? $failedEvents.' eventos falharam.' : 'Sem falhas registadas.');
        $missingRoles=0;
        foreach (PlanRepository::plans(true) as $plan) {
            if ((string)$plan['slug']==='legacy') continue;
            if (RoleMappingService::targetRole((int)$plan['id'], 'active') === '') $missingRoles++;
        }
        $checks[] = self::check('role_mapping', 'Mapeamento de roles', $missingRoles === 0, $missingRoles ? $missingRoles.' planos ativos sem role.' : 'Todos os planos ativos têm role.');
        return $checks;
    }

    public static function reconcile(string $mode = 'preview', string $environment = ''): array {
        global $wpdb;
        $mode = $mode === 'apply' ? 'apply' : 'preview';
        $environment = in_array($environment, ['sandbox','live'], true) ? $environment : Settings::mode();
        $started = current_time('mysql');
        $runId = 0;
        $runTable = ReconciliationService::runsTable();
        $runExists = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $runTable)) === $runTable;
        if ($runExists) {
            $wpdb->insert($runTable, [
                'run_type'=>'billing_production',
                'run_mode'=>$mode,
                'status'=>'running',
                'initiated_by'=>get_current_user_id() ?: null,
                'started_at'=>$started,
                'created_at'=>$started,
            ]);
            $runId=(int)$wpdb->insert_id;
        }
        $issues = self::scanBillingIssues($environment);
        $applied=[];$errors=[];
        if ($mode === 'apply') {
            foreach ($issues as $issue) {
                $result = self::applyBillingIssue($issue, $environment);
                if (!empty($result['applied'])) $applied[]=$result;
                elseif (!empty($result['error'])) $errors[]=$result;
            }
        }
        $summary = [
            'environment'=>$environment,
            'total_issues'=>count($issues),
            'safe_fixes_available'=>count(array_filter($issues, static fn($issue)=>!empty($issue['safe_fix']))),
            'applied'=>count($applied),
            'errors'=>count($errors),
            'by_type'=>[],
        ];
        foreach ($issues as $issue) $summary['by_type'][$issue['type']] = (int)($summary['by_type'][$issue['type']] ?? 0) + 1;
        ksort($summary['by_type']);
        if ($runId > 0) {
            $wpdb->update($runTable, [
                'status'=>$errors?'completed_with_errors':'completed',
                'summary_json'=>wp_json_encode($summary),
                'details_json'=>wp_json_encode(['issues'=>$issues,'applied'=>$applied,'errors'=>$errors]),
                'completed_at'=>current_time('mysql'),
            ], ['id'=>$runId]);
        }
        AuditLog::record('billing.reconciliation.'.$mode, 'reconciliation_run', $runId, null, $summary, ['actor_user_id'=>get_current_user_id()], ['environment'=>$environment]);
        EventBus::emit('billing.reconciliation.'.$mode, $summary, ['entity_type'=>'reconciliation_run','entity_id'=>$runId,'actor_user_id'=>get_current_user_id()], 'billing');
        return compact('runId','mode','environment','summary','issues','applied','errors');
    }

    public static function recentEmailLog(int $limit = 100): array {
        global $wpdb;
        if (!self::tablesReady()) return [];
        return $wpdb->get_results('SELECT * FROM '.self::emailLogTable().' ORDER BY id DESC LIMIT '.max(1,min(500,$limit)), ARRAY_A) ?: [];
    }

    public static function recentConsents(int $limit = 100): array {
        global $wpdb;
        if (!self::tablesReady()) return [];
        return $wpdb->get_results('SELECT c.*,u.display_name,u.user_email FROM '.self::consentTable().' c LEFT JOIN '.$wpdb->users.' u ON u.ID=c.user_id ORDER BY c.id DESC LIMIT '.max(1,min(500,$limit)), ARRAY_A) ?: [];
    }

    public static function downgradeImpact(int $clientId, int $targetPlanId): array {
        $plan = PlanRepository::plan($targetPlanId);
        if (!$plan) return [];
        $limits = (array)($plan['limits'] ?? []);
        $usage = UsageService::snapshot($clientId);
        $over=[];
        foreach ($limits as $metric=>$limit) {
            $used = (int)($usage[$metric]['used'] ?? 0);
            if ((int)$limit > 0 && $used > (int)$limit) $over[$metric] = ['used'=>$used,'limit'=>(int)$limit];
        }
        return $over;
    }

    private static function scanBillingIssues(string $environment): array {
        global $wpdb;
        $issues=[];
        $subscriptions = BillingRepository::subscriptions(1000, $environment);
        foreach ($subscriptions as $subscription) {
            $id=(int)$subscription['id'];$clientId=(int)$subscription['client_id'];$planId=(int)$subscription['plan_id'];$userId=(int)$subscription['billing_user_id'];
            $clientExists=$clientId>0&&(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$wpdb->prefix.'routespro_clients WHERE id=%d',$clientId))>0;
            if(!$clientExists){$issues[]=self::issue('missing_client','critical','Subscrição sem cliente válido.',['subscription_id'=>$id,'client_id'=>$clientId],false);continue;}
            if(!$planId||!PlanRepository::plan($planId))$issues[]=self::issue('missing_plan','critical','Subscrição sem plano válido.',['subscription_id'=>$id,'client_id'=>$clientId,'plan_id'=>$planId],false);
            if($userId<=0||!get_userdata($userId))$issues[]=self::issue('missing_billing_user','warning','Subscrição sem utilizador WordPress válido.',['subscription_id'=>$id,'client_id'=>$clientId,'user_id'=>$userId],false);
            $saas=PlanRepository::subscription($clientId);
            $expectedStatus=self::saasStatus((string)$subscription['status']);
            if(!$saas||(int)$saas['plan_id']!==$planId||(string)$saas['status']!==$expectedStatus)$issues[]=self::issue('saas_mismatch','warning','Plano ou estado SaaS divergente da faturação.',['subscription_id'=>$id,'client_id'=>$clientId,'plan_id'=>$planId,'status'=>$expectedStatus,'renews_at'=>(string)$subscription['current_period_end'],'grace_until'=>(string)$subscription['grace_until']],true);
            if($userId>0&&get_userdata($userId)){
                $target=RoleMappingService::targetRole($planId,(string)$subscription['status']);
                $roles=(array)get_userdata($userId)->roles;
                if($target!==''&&!in_array($target,$roles,true))$issues[]=self::issue('role_mismatch','warning','Role do utilizador não corresponde à subscrição.',['subscription_id'=>$id,'client_id'=>$clientId,'user_id'=>$userId,'target_role'=>$target],true);
                if($target===''&&in_array((string)$subscription['status'],['active','trial','trialing','past_due'],true))$issues[]=self::issue('role_mapping_missing','warning','Plano ativo sem role configurada.',['subscription_id'=>$id,'client_id'=>$clientId,'plan_id'=>$planId],false);
            }
        }
        $expiredSessions=$wpdb->get_results($wpdb->prepare("SELECT id,client_id FROM ".BillingRepository::sessionsTable()." WHERE status IN ('created','open') AND expires_at IS NOT NULL AND expires_at<%s LIMIT 500",current_time('mysql')),ARRAY_A)?:[];
        foreach($expiredSessions as$row)$issues[]=self::issue('stale_checkout','info','Checkout expirado ainda marcado como aberto.',['checkout_session_id'=>(int)$row['id'],'client_id'=>(int)$row['client_id']],true);
        $orphanInvoices=$wpdb->get_results($wpdb->prepare("SELECT id,client_id FROM ".BillingRepository::invoicesTable()." WHERE environment=%s AND client_id>0 AND client_id NOT IN (SELECT id FROM {$wpdb->prefix}routespro_clients) LIMIT 500",$environment),ARRAY_A)?:[];
        foreach($orphanInvoices as$row)$issues[]=self::issue('orphan_invoice','warning','Fatura associada a um cliente inexistente.',['invoice_id'=>(int)$row['id'],'client_id'=>(int)$row['client_id']],false);
        return $issues;
    }

    private static function applyBillingIssue(array $issue, string $environment): array {
        $type=(string)($issue['type']??'');$data=(array)($issue['data']??[]);
        if(empty($issue['safe_fix']))return['applied'=>false,'type'=>$type,'reason'=>'manual_review_required'];
        if($type==='saas_mismatch'){
            $id=PlanRepository::syncBilling((int)$data['client_id'],(int)$data['plan_id'],(string)$data['status'],(string)$data['renews_at'],(string)$data['grace_until'],'Reconciliação automática da faturação.');
            return $id>0?['applied'=>true,'type'=>$type,'client_id'=>(int)$data['client_id']]:['applied'=>false,'type'=>$type,'error'=>'Não foi possível sincronizar o plano SaaS.'];
        }
        if($type==='role_mismatch'){
            $result=RoleMappingService::syncClient((int)$data['client_id'],$environment,'Reconciliação automática da faturação.');
            return ['applied'=>true,'type'=>$type,'client_id'=>(int)$data['client_id'],'result'=>$result];
        }
        if($type==='stale_checkout'){
            BillingRepository::updateCheckout((int)$data['checkout_session_id'],['status'=>'expired']);
            return ['applied'=>true,'type'=>$type,'checkout_session_id'=>(int)$data['checkout_session_id']];
        }
        return ['applied'=>false,'type'=>$type,'error'=>'Sem reparação implementada.'];
    }

    private static function resolveContext(array $context): array {
        global $wpdb;
        $clientId=absint($context['client_id']??0);$userId=absint($context['user_id']??0);$planId=absint($context['plan_id']??0);$intentId=absint($context['intent_id']??0);
        if($intentId>0){$intent=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.SignupService::table().' WHERE id=%d LIMIT 1',$intentId),ARRAY_A);if($intent){$clientId=$clientId?:absint($intent['client_id']);$userId=$userId?:absint($intent['user_id']);$planId=$planId?:absint($intent['plan_id']);}}
        $subscription=$clientId>0?BillingRepository::subscriptionByClient($clientId,null):null;
        if($subscription){$userId=$userId?:absint($subscription['billing_user_id']);$planId=$planId?:absint($subscription['plan_id']);}
        $client=$clientId>0?$wpdb->get_row($wpdb->prepare('SELECT name,email FROM '.$wpdb->prefix.'routespro_clients WHERE id=%d',$clientId),ARRAY_A):null;
        $user=$userId>0?get_userdata($userId):null;
        $plan=$planId>0?PlanRepository::plan($planId):null;
        $resumeUrl=(string)($context['resume_url']??'');
        if($resumeUrl===''&&$intentId>0){$intent=$intent??$wpdb->get_row($wpdb->prepare('SELECT * FROM '.SignupService::table().' WHERE id=%d LIMIT 1',$intentId),ARRAY_A);if($intent){$meta=json_decode((string)$intent['meta_json'],true);$token=SecretVault::decrypt((string)($meta['recovery_token_enc']??''));if($token!=='')$resumeUrl=SignupService::resumeUrl($token,$intent,true);}}
        $periodEnd=(string)($context['period_end']??($subscription['current_period_end']??''));
        $grace=(string)($context['grace_until']??($subscription['grace_until']??''));
        return [
            'email'=>sanitize_email((string)($context['to']??($user?$user->user_email:($client['email']??'')))),
            'name'=>sanitize_text_field((string)($context['name']??($user?$user->display_name:'Cliente FieldFlow'))),
            'client'=>sanitize_text_field((string)($context['client_name']??($client['name']??'Organização'))),
            'plan'=>sanitize_text_field((string)($context['plan_name']??($plan['name']??($subscription['plan_name']??'FieldFlow')))),
            'amount'=>sanitize_text_field((string)($context['amount']??'')),
            'portal_url'=>Settings::portalUrl(),
            'resume_url'=>$resumeUrl?:Settings::signupUrl(),
            'grace_until'=>$grace?date_i18n('d/m/Y H:i',strtotime($grace)):'sem data definida',
            'period_end'=>$periodEnd?date_i18n('d/m/Y',strtotime($periodEnd)):'sem data definida',
            'site_name'=>wp_specialchars_decode((string)get_bloginfo('name'),ENT_QUOTES),
        ];
    }

    private static function emailAlreadySent(string $messageKey): bool {
        global $wpdb;
        if (!self::tablesReady()) return false;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".self::emailLogTable()." WHERE message_key=%s AND status='sent'", $messageKey)) > 0;
    }

    private static function logEmail(string $messageKey,string $template,string $recipient,string $subject,string $status,string $error,array $context):void{
        global$wpdb;if(!self::tablesReady())return;
        $key=substr($messageKey,0,191);
        $row=['template_key'=>sanitize_key($template),'recipient'=>sanitize_email($recipient),'subject'=>substr(sanitize_text_field($subject),0,255),'status'=>sanitize_key($status),'error_message'=>sanitize_textarea_field($error),'client_id'=>absint($context['client_id']??0),'user_id'=>absint($context['user_id']??0),'intent_id'=>absint($context['intent_id']??0),'meta_json'=>wp_json_encode($context),'sent_at'=>$status==='sent'?current_time('mysql'):null];
        $id=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::emailLogTable().' WHERE message_key=%s LIMIT 1',$key));
        if($id>0){$wpdb->update(self::emailLogTable(),$row,['id'=>$id]);return;}
        $row['message_key']=$key;$row['created_at']=current_time('mysql');$wpdb->insert(self::emailLogTable(),$row);
    }

    private static function check(string$key,string$label,bool$ok,string$detail):array{return['key'=>$key,'label'=>$label,'ok'=>$ok,'detail'=>$detail];}
    private static function issue(string$type,string$severity,string$message,array$data,bool$safe):array{return['type'=>$type,'severity'=>$severity,'message'=>$message,'data'=>$data,'safe_fix'=>$safe];}
    private static function saasStatus(string$status):string{$status=sanitize_key($status);return in_array($status,['trial','trialing'],true)?'trial':(in_array($status,['active','past_due','suspended','expired','cancelled'],true)?$status:'active');}
    private static function ipHash():string{return substr(hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'),wp_salt('auth')),0,32);}
}
