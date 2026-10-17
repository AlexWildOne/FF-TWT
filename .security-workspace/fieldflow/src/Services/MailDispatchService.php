<?php
namespace RoutesPro\Services;

if (!defined('ABSPATH')) exit;

/**
 * Canonical FieldFlow mail transport and reliability seam.
 *
 * The original 2.2.524 foundation contract remains stable for package-validator
 * continuity. 2.2.525 layers a persistent outbox, idempotency and bounded retry
 * around the same WordPress wp_mail() transport without changing SMTP/provider
 * configuration or the public caller signature.
 */
final class MailDispatchService {
    public const CONTRACT = 'fieldflow-mail-dispatch-foundation-v1';
    public const BUILD = '2.2.524';
    public const OUTBOX_CONTRACT = 'fieldflow-mail-reliability-outbox-v1';
    public const OUTBOX_BUILD = '2.2.525';
    public const CRON_HOOK = 'fieldflow_mail_outbox_process';
    public const CLEANUP_HOOK = 'fieldflow_mail_outbox_cleanup';
    public const FEATURE_OPTION = 'fieldflow_mail_outbox_enabled';
    public const DEFAULT_MAX_ATTEMPTS = 4;
    private const LOCK_TTL_SECONDS = 600;
    private const WORKER_BATCH = 10;
    private const ATTACHMENT_RETENTION_SECONDS = 14 * DAY_IN_SECONDS;
    private const OUTBOX_ROW_RETENTION_SECONDS = 90 * DAY_IN_SECONDS;

    public static function registerHooks(): void {
        add_action(self::CRON_HOOK, [self::class, 'processOutbox']);
        add_action(self::CLEANUP_HOOK, [self::class, 'cleanupAttachments']);
        add_action('wp_loaded', [self::class, 'ensureSchedules'], 90);
    }

    public static function isOutboxEnabled(): bool {
        $stored = get_option(self::FEATURE_OPTION, null);
        $enabled = $stored === null ? true : !in_array(strtolower((string)$stored), ['0','false','off','no'], true);
        return (bool) apply_filters('fieldflow_mail_outbox_enabled', $enabled);
    }

    public static function setOutboxEnabled(bool $enabled): void {
        update_option(self::FEATURE_OPTION, $enabled ? '1' : '0', false);
        if ($enabled) self::ensureSchedules();
    }

    public static function status(): array {
        $available = class_exists(MailOutboxRepository::class) && MailOutboxRepository::exists();
        $summary = $available ? MailOutboxRepository::summary() : [];
        return [
            'contract'=>self::OUTBOX_CONTRACT,
            'build'=>self::OUTBOX_BUILD,
            'enabled'=>self::isOutboxEnabled(),
            'available'=>$available,
            'summary'=>$summary,
            'next_worker_at'=>function_exists('wp_next_scheduled') ? (int)(wp_next_scheduled(self::CRON_HOOK) ?: 0) : 0,
            'next_cleanup_at'=>function_exists('wp_next_scheduled') ? (int)(wp_next_scheduled(self::CLEANUP_HOOK) ?: 0) : 0,
        ];
    }

    /**
     * Backwards-friendly boolean wrapper. A message accepted into the reliable
     * outbox is treated as accepted work even when the first transport attempt
     * failed and a retry is pending.
     *
     * @param string|array $to
     * @param string|array $headers
     * @param string|array $attachments
     */
    public static function send($to, string $subject, string $message, $headers = [], $attachments = [], array $context = []): bool {
        $result = self::dispatch($to, $subject, $message, $headers, $attachments, $context);
        return !empty($result['accepted']);
    }

    /**
     * Persist mail intent, attempt immediate delivery and retain failed work for
     * bounded retry. The caller signature remains compatible with 2.2.524.
     *
     * @param string|array $to
     * @param string|array $headers
     * @param string|array $attachments
     * @return array<string,mixed>
     */
    public static function dispatch($to, string $subject, string $message, $headers = [], $attachments = [], array $context = []): array {
        $context = is_array($context) ? $context : [];
        $context['contract'] = self::CONTRACT;
        $context['build'] = self::BUILD;
        $context['outbox_contract'] = self::OUTBOX_CONTRACT;
        $context['outbox_build'] = self::OUTBOX_BUILD;
        if (function_exists('apply_filters')) {
            $filtered = apply_filters('fieldflow_mail_dispatch_context', $context, $to, $subject);
            if (is_array($filtered)) $context = $filtered;
        }

        if (!self::isOutboxEnabled() || !class_exists(MailOutboxRepository::class) || !MailOutboxRepository::exists()) {
            if (!empty($context['reliable_required'])) {
                return self::reliableBlocked($context, 'Outbox indisponivel ou desativada. Daily Close suspenso para evitar um envio sem rastreabilidade.');
            }
            return self::dispatchDirect($to, $subject, $message, $headers, $attachments, $context);
        }

        $idempotencyKey = self::normalizeIdempotencyKey((string)($context['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '') {
            $existing = MailOutboxRepository::findByIdempotencyKey($idempotencyKey);
            if ($existing) return self::duplicateResult($existing, $context);
            // A 2.2.648 key omitted route_id. Only recognize it as an existing
            // dispatch when the Outbox explicitly proves the SAME route.
            $legacyKey = self::normalizeIdempotencyKey((string)($context['legacy_idempotency_key'] ?? ''));
            if ($legacyKey !== '' && $legacyKey !== $idempotencyKey && absint($context['route_id'] ?? 0) > 0) {
                $legacy = MailOutboxRepository::findByIdempotencyKey($legacyKey);
                if ($legacy && (int)($legacy['route_id'] ?? 0) === absint($context['route_id'])) {
                    return self::duplicateResult($legacy, $context);
                }
            }
        }

        $messageId = self::messageId();
        $recipientCount = self::recipientCount($to);
        $retryable = self::isRetryable($context);
        $maxAttempts = $retryable ? self::maxAttempts($context) : 1;
        $now = current_time('mysql', true);
        $source = sanitize_key((string)($context['source'] ?? 'system')) ?: 'system';
        $emailType = sanitize_key((string)($context['email_type'] ?? $source)) ?: 'system';
        $outboxId = MailOutboxRepository::insert([
            'message_id'=>$messageId,
            'idempotency_key'=>$idempotencyKey !== '' ? $idempotencyKey : null,
            'source'=>$source,
            'email_type'=>$emailType,
            'client_id'=>absint($context['client_id'] ?? 0),
            'project_id'=>absint($context['project_id'] ?? 0),
            'route_id'=>absint($context['route_id'] ?? 0),
            'entity_type'=>sanitize_key((string)($context['entity_type'] ?? '')),
            'entity_id'=>absint($context['entity_id'] ?? 0),
            'to_json'=>wp_json_encode(self::normalizeRecipients($to)),
            'cc_json'=>wp_json_encode(self::normalizeRecipients($context['cc'] ?? [])),
            'bcc_json'=>wp_json_encode(self::normalizeRecipients($context['bcc'] ?? [])),
            'subject'=>$subject,
            'body'=>$message,
            'headers_json'=>wp_json_encode(self::encodeTransportValue($headers)),
            'attachments_json'=>'[]',
            // Transport starts synchronously. Own the lock before invoking
            // wp_mail so a concurrent cron worker cannot send the same row.
            'status'=>'sending',
            'locked_at'=>$now,
            'locked_by'=>$messageId,
            'attempt_count'=>0,
            'max_attempts'=>$maxAttempts,
            'next_attempt_at'=>$now,
            'meta_json'=>wp_json_encode(['context'=>self::contextForStorage($context),'attachment_names'=>self::attachmentNames($attachments),'retryable'=>$retryable?1:0]),
        ]);

        if ($outboxId <= 0) {
            // A concurrent process may have inserted the same deterministic
            // idempotency key after our preflight lookup. Re-read before
            // falling back to direct transport so persistence contention can
            // never turn into a duplicate email.
            if ($idempotencyKey !== '') {
                $existing = MailOutboxRepository::findByIdempotencyKey($idempotencyKey);
                if ($existing) return self::duplicateResult($existing, $context);
            }
            $context['outbox_persistence_failed'] = 1;
            if (!empty($context['reliable_required'])) {
                return self::reliableBlocked($context, 'Falha ao gravar o pedido na Outbox. Nenhum email Daily Close foi enviado.');
            }
            return self::dispatchDirect($to, $subject, $message, $headers, $attachments, $context);
        }

        $transport = self::transport($to, $subject, $message, $headers, $attachments);
        $attemptCount = 1;
        MailOutboxRepository::appendAttempt($outboxId, self::attemptRecord($transport, $attemptCount, 'initial'));

        if (!empty($transport['sent'])) {
            $saved = MailOutboxRepository::update($outboxId, [
                'status'=>'sent',
                'attempt_count'=>$attemptCount,
                'next_attempt_at'=>null,
                'locked_at'=>null,
                'locked_by'=>null,
                'last_dispatch_id'=>(string)$transport['dispatch_id'],
                'last_error'=>null,
                'sent_at'=>$now,
            ]);
            if (!$saved) {
                // Delivery was accepted but the final status could not be saved.
                // The stale lock must become uncertain, never automatic retry.
                $transport['error'] = 'Transporte aceitou o email, mas a Outbox nao confirmou o estado final. Verificar antes de reenviar.';
                $result = self::resultFromTransport($transport, $context, $messageId, $outboxId, 'uncertain', false, false, $attemptCount, $maxAttempts, false);
                self::afterDispatch($result);
                return $result;
            }
            $result = self::resultFromTransport($transport, $context, $messageId, $outboxId, 'sent', true, false, $attemptCount, $maxAttempts, false);
            self::afterDispatch($result);
            return $result;
        }

        $persistentAttachments = self::persistAttachments($attachments, $messageId);
        $attachmentPaths = self::attachmentPaths($attachments);
        $canRetryAttachments = !$attachmentPaths || !empty($persistentAttachments['ok']);
        $error = (string)($transport['error'] ?? '');
        if ($attachmentPaths && !$canRetryAttachments) {
            $persistError = (string)($persistentAttachments['error'] ?? 'Não foi possível preservar anexos para retry.');
            $error = trim($error . ' ' . $persistError);
        }

        $canRetry = $retryable && $attemptCount < $maxAttempts && $canRetryAttachments;
        $status = $canRetry ? 'retry' : 'failed';
        $nextAttempt = $canRetry ? gmdate('Y-m-d H:i:s', time() + self::retryDelay($attemptCount)) : null;
        $saved = MailOutboxRepository::update($outboxId, [
            'status'=>$status,
            'attempt_count'=>$attemptCount,
            'next_attempt_at'=>$nextAttempt,
            'locked_at'=>null,
            'locked_by'=>null,
            'last_dispatch_id'=>(string)$transport['dispatch_id'],
            'last_error'=>$error,
            'attachments_json'=>wp_json_encode((array)($persistentAttachments['files'] ?? [])),
        ]);
        if (!$saved) {
            $transport['error'] = trim($error . ' A Outbox nao confirmou o estado; exige conciliacao manual.');
            $result = self::resultFromTransport($transport, $context, $messageId, $outboxId, 'uncertain', false, false, $attemptCount, $maxAttempts, false);
            self::afterDispatch($result);
            return $result;
        }
        if ($canRetry) self::scheduleWorker(strtotime((string)$nextAttempt . ' UTC') ?: time() + 60);

        $transport['error'] = $error;
        $result = self::resultFromTransport($transport, $context, $messageId, $outboxId, $status, $canRetry, $canRetry, $attemptCount, $maxAttempts, false);
        self::afterDispatch($result);
        return $result;
    }

    public static function ensureSchedules(): void {
        if (!self::isOutboxEnabled() || !class_exists(MailOutboxRepository::class) || !MailOutboxRepository::exists()) return;
        $due = MailOutboxRepository::nextDueTimestamp();
        if ($due > 0) self::scheduleWorker(max(time() + 5, $due));
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
        }
    }

    public static function processOutbox(): void {
        if (!self::isOutboxEnabled() || !class_exists(MailOutboxRepository::class) || !MailOutboxRepository::exists()) return;
        MailOutboxRepository::recoverStaleLocks(self::LOCK_TTL_SECONDS);
        $ids = MailOutboxRepository::dueIds(self::WORKER_BATCH);
        foreach ($ids as $id) self::processOutboxRow((int)$id);
        $next = MailOutboxRepository::nextDueTimestamp();
        if ($next > 0) self::scheduleWorker(max(time() + 10, $next));
    }

    public static function requeue(int $id, bool $verifiedNotDelivered = false): bool {
        $row = class_exists(MailOutboxRepository::class) ? MailOutboxRepository::get($id) : [];
        if (!$row) return false;
        $state = (string)($row['status'] ?? '');
        if (!in_array($state, ['failed','cancelled'], true) && !($state === 'uncertain' && $verifiedNotDelivered)) return false;
        $files = self::decodeAttachments((string)($row['attachments_json'] ?? '[]'));
        $meta = json_decode((string)($row['meta_json'] ?? '{}'), true);
        // Explicit verification of a non-delivery is not enough when this report
        // originally had attachments that were never durably stored. Otherwise
        // an operator could requeue a broken report without the required files.
        if (is_array($meta) && !empty($meta['attachment_names']) && !$files) return false;
        foreach ($files as $file) if (empty($file['path']) || !is_file((string)$file['path'])) return false;
        $ok = MailOutboxRepository::update($id, [
            'status'=>'retry',
            'attempt_count'=>0,
            'max_attempts'=>max(self::DEFAULT_MAX_ATTEMPTS, (int)($row['max_attempts'] ?? 0)),
            'next_attempt_at'=>current_time('mysql', true),
            'locked_at'=>null,
            'locked_by'=>null,
            'last_error'=>null,
            'cancelled_at'=>null,
        ]);
        if ($ok) self::scheduleWorker(time() + 5);
        return $ok;
    }

    public static function cancel(int $id): bool {
        $row = class_exists(MailOutboxRepository::class) ? MailOutboxRepository::get($id) : [];
        if (!$row || !in_array((string)($row['status'] ?? ''), ['pending','retry','failed'], true)) return false;
        return MailOutboxRepository::update($id, [
            'status'=>'cancelled',
            'next_attempt_at'=>null,
            'locked_at'=>null,
            'locked_by'=>null,
            'cancelled_at'=>current_time('mysql', true),
        ]);
    }

    public static function cleanupAttachments(): void {
        if (!class_exists(MailOutboxRepository::class) || !MailOutboxRepository::exists()) return;
        $rows = MailOutboxRepository::terminalAttachmentRows(self::ATTACHMENT_RETENTION_SECONDS, 100);
        foreach ($rows as $row) {
            $files = self::decodeAttachments((string)($row['attachments_json'] ?? '[]'));
            foreach ($files as $file) {
                $path = (string)($file['path'] ?? '');
                if ($path !== '' && is_file($path)) @unlink($path);
            }
            $dir = self::attachmentDirectoryForRow($files);
            if ($dir !== '' && is_dir($dir)) @rmdir($dir);
            MailOutboxRepository::update((int)$row['id'], ['attachments_json'=>'[]']);
        }
        // Keep operational history useful without retaining sent/cancelled message bodies indefinitely.
        MailOutboxRepository::purgeTerminalRows(self::OUTBOX_ROW_RETENTION_SECONDS, 200);
    }

    private static function processOutboxRow(int $id): void {
        $token = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : md5(uniqid('', true));
        if (!MailOutboxRepository::claim($id, $token)) return;
        $row = MailOutboxRepository::get($id);
        if (!$row) return;

        $meta = json_decode((string)($row['meta_json'] ?? '{}'), true);
        $storedFiles = self::decodeAttachments((string)($row['attachments_json'] ?? '[]'));
        // A pre-2.2.649 process could leave an intent without attachments
        // persisted. Never send a report with silently missing files, whether
        // on the first attempt or after a previously recorded failed attempt.
        if (is_array($meta) && !empty($meta['attachment_names']) && !$storedFiles) {
            $unattempted = (int)($row['attempt_count'] ?? 0) === 0;
            MailOutboxRepository::update($id, [
                'status'=>$unattempted ? 'uncertain' : 'failed',
                'next_attempt_at'=>null, 'locked_at'=>null, 'locked_by'=>null,
                'last_error'=>'Intento sem anexos persistidos. Reconstruir o reporte antes de enviar.',
            ]);
            return;
        }
        $to = json_decode((string)($row['to_json'] ?? '[]'), true);
        if (!is_array($to)) $to = [];
        $headers = self::decodeTransportValue((string)($row['headers_json'] ?? '[]'));
        $files = self::decodeAttachments((string)($row['attachments_json'] ?? '[]'));
        $attachments = [];
        foreach ($files as $file) {
            $path = (string)($file['path'] ?? '');
            if ($path === '' || !is_file($path)) {
                MailOutboxRepository::update($id, [
                    'status'=>'failed','next_attempt_at'=>null,'locked_at'=>null,'locked_by'=>null,
                    'last_error'=>'Um anexo persistido para retry deixou de estar disponível.',
                ]);
                return;
            }
            $attachments[] = $path;
        }

        $transport = self::transport($to, (string)$row['subject'], (string)$row['body'], $headers, $attachments);
        $attemptCount = max(0, (int)$row['attempt_count']) + 1;
        $maxAttempts = max(1, (int)$row['max_attempts']);
        MailOutboxRepository::appendAttempt($id, self::attemptRecord($transport, $attemptCount, 'retry'));
        $meta = json_decode((string)($row['meta_json'] ?? '{}'), true);
        $context = is_array($meta) && is_array($meta['context'] ?? null) ? $meta['context'] : [];
        $context['contract'] = self::CONTRACT;
        $context['build'] = self::BUILD;
        $context['outbox_contract'] = self::OUTBOX_CONTRACT;
        $context['outbox_build'] = self::OUTBOX_BUILD;

        if (!empty($transport['sent'])) {
            $saved = MailOutboxRepository::update($id, [
                'status'=>'sent','attempt_count'=>$attemptCount,'next_attempt_at'=>null,'locked_at'=>null,'locked_by'=>null,
                'last_dispatch_id'=>(string)$transport['dispatch_id'],'last_error'=>null,'sent_at'=>current_time('mysql', true),
            ]);
            if (!$saved) {
                $transport['error'] = 'Transporte aceitou, sem confirmacao persistente na Outbox. Rever antes de reenviar.';
                self::afterDispatch(self::resultFromTransport($transport, $context, (string)$row['message_id'], $id, 'uncertain', false, false, $attemptCount, $maxAttempts, false));
                return;
            }
            self::deletePersistedAttachments($files, $id);
            $result = self::resultFromTransport($transport, $context, (string)$row['message_id'], $id, 'sent', true, false, $attemptCount, $maxAttempts, false);
            self::afterDispatch($result);
            return;
        }

        $canRetry = $attemptCount < $maxAttempts;
        $status = $canRetry ? 'retry' : 'failed';
        $nextAttempt = $canRetry ? gmdate('Y-m-d H:i:s', time() + self::retryDelay($attemptCount)) : null;
        $saved = MailOutboxRepository::update($id, [
            'status'=>$status,'attempt_count'=>$attemptCount,'next_attempt_at'=>$nextAttempt,'locked_at'=>null,'locked_by'=>null,
            'last_dispatch_id'=>(string)$transport['dispatch_id'],'last_error'=>(string)$transport['error'],
        ]);
        if (!$saved) {
            self::afterDispatch(self::resultFromTransport($transport, $context, (string)$row['message_id'], $id, 'uncertain', false, false, $attemptCount, $maxAttempts, false));
            return;
        }
        if ($canRetry) self::scheduleWorker(strtotime((string)$nextAttempt . ' UTC') ?: time() + 60);
        $result = self::resultFromTransport($transport, $context, (string)$row['message_id'], $id, $status, $canRetry, $canRetry, $attemptCount, $maxAttempts, false);
        self::afterDispatch($result);
    }

    /** Keep the canonical wp_mail() call literal stable for the 2.2.524 validator. */
    private static function transport($to, string $subject, string $message, $headers, $attachments): array {
        $dispatchId = self::dispatchId();
        $startedAt = microtime(true);
        $error = '';
        $sent = false;
        $failureListener = static function ($mailError) use (&$error): void {
            if (is_wp_error($mailError)) {
                $error = sanitize_text_field((string)$mailError->get_error_message());
            } elseif (is_string($mailError) && $mailError !== '') {
                $error = sanitize_text_field($mailError);
            }
        };
        if (function_exists('add_action')) add_action('wp_mail_failed', $failureListener);
        try {
            $sent = (bool) wp_mail($to, $subject, $message, $headers, $attachments);
        } catch (\Throwable $exception) {
            $sent = false;
            $error = sanitize_text_field($exception->getMessage());
        } finally {
            if (function_exists('remove_action')) remove_action('wp_mail_failed', $failureListener);
        }
        if (!$sent && $error === '') $error = 'wp_mail devolveu false.';
        return [
            'sent'=>$sent,
            'error'=>$error,
            'dispatch_id'=>$dispatchId,
            'duration_ms'=>max(0, (int)round((microtime(true) - $startedAt) * 1000)),
            'recipient_count'=>self::recipientCount($to),
        ];
    }

    private static function reliableBlocked(array $context, string $error): array {
        $result = [
            'sent'=>false,'accepted'=>false,'queued'=>false,'status'=>'blocked','error'=>$error,
            'dispatch_id'=>'','message_id'=>'','outbox_id'=>0,'duration_ms'=>0,
            'attempt_count'=>0,'max_attempts'=>0,'deduplicated'=>false,'outbox_enabled'=>false,
            'context'=>$context,
        ];
        self::afterDispatch($result);
        return $result;
    }

    private static function dispatchDirect($to, string $subject, string $message, $headers, $attachments, array $context): array {
        $transport = self::transport($to, $subject, $message, $headers, $attachments);
        $result = self::resultFromTransport($transport, $context, self::messageId(), 0, !empty($transport['sent']) ? 'sent' : 'failed', !empty($transport['sent']), false, 1, 1, false);
        $result['outbox_enabled'] = false;
        self::afterDispatch($result);
        return $result;
    }

    private static function duplicateResult(array $row, array $context): array {
        $status = sanitize_key((string)($row['status'] ?? '')) ?: 'pending';
        $sent = $status === 'sent';
        $accepted = in_array($status, ['pending','sending','retry','sent'], true);
        // 'uncertain' intentionally does not qualify as accepted or retriable.
        if (in_array($status, ['pending','retry'], true)) self::scheduleWorker(time() + 5);
        $result = [
            'sent'=>$sent,
            'accepted'=>$accepted,
            'queued'=>in_array($status, ['pending','sending','retry'], true),
            'status'=>$status,
            'error'=>$accepted ? '' : (string)($row['last_error'] ?? 'A mensagem idempotente anterior terminou em falha.'),
            'dispatch_id'=>(string)($row['last_dispatch_id'] ?? ''),
            'message_id'=>(string)($row['message_id'] ?? ''),
            'outbox_id'=>(int)($row['id'] ?? 0),
            'duration_ms'=>0,
            'recipient_count'=>count((array)json_decode((string)($row['to_json'] ?? '[]'), true)),
            'attempt_count'=>(int)($row['attempt_count'] ?? 0),
            'max_attempts'=>(int)($row['max_attempts'] ?? 1),
            'deduplicated'=>true,
            'outbox_enabled'=>true,
            'context'=>$context,
        ];
        self::afterDispatch($result);
        return $result;
    }

    private static function resultFromTransport(array $transport, array $context, string $messageId, int $outboxId, string $status, bool $accepted, bool $queued, int $attemptCount, int $maxAttempts, bool $deduplicated): array {
        return [
            'sent'=>!empty($transport['sent']),
            'accepted'=>$accepted,
            'queued'=>$queued,
            'status'=>$status,
            'error'=>(string)($transport['error'] ?? ''),
            'dispatch_id'=>(string)($transport['dispatch_id'] ?? ''),
            'message_id'=>$messageId,
            'outbox_id'=>$outboxId,
            'duration_ms'=>(int)($transport['duration_ms'] ?? 0),
            'recipient_count'=>(int)($transport['recipient_count'] ?? 0),
            'attempt_count'=>$attemptCount,
            'max_attempts'=>$maxAttempts,
            'deduplicated'=>$deduplicated,
            'outbox_enabled'=>$outboxId > 0,
            'context'=>$context,
        ];
    }

    private static function afterDispatch(array $result): void {
        if (function_exists('do_action')) do_action('fieldflow_mail_dispatch_after', $result);
        if (empty($result['accepted']) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FieldFlow MailDispatch] ' . (string)($result['dispatch_id'] ?? '') . ' ' . (string)($result['error'] ?? 'Falha de envio.'));
        }
    }

    private static function scheduleWorker(int $timestamp): void {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_single_event')) return;
        $timestamp = max(time() + 5, $timestamp);
        $next = wp_next_scheduled(self::CRON_HOOK);
        if ($next && (int)$next <= $timestamp + 30) return;
        if ($next && function_exists('wp_unschedule_event')) wp_unschedule_event((int)$next, self::CRON_HOOK);
        wp_schedule_single_event($timestamp, self::CRON_HOOK);
    }

    private static function retryDelay(int $attemptCount): int {
        if ($attemptCount <= 1) return 5 * MINUTE_IN_SECONDS;
        if ($attemptCount === 2) return 30 * MINUTE_IN_SECONDS;
        return 2 * HOUR_IN_SECONDS;
    }

    private static function maxAttempts(array $context): int {
        $value = absint($context['max_attempts'] ?? self::DEFAULT_MAX_ATTEMPTS);
        return max(1, min(6, $value ?: self::DEFAULT_MAX_ATTEMPTS));
    }

    private static function isRetryable(array $context): bool {
        if (array_key_exists('retryable', $context)) return !empty($context['retryable']);
        if (!empty($context['test']) || !empty($context['manual_dispatch']) || !empty($context['is_resend'])) return false;
        $source = sanitize_key((string)($context['source'] ?? ''));
        return !in_array($source, ['mail_center_test'], true);
    }

    private static function normalizeIdempotencyKey(string $raw): string {
        $raw = trim($raw);
        return $raw === '' ? '' : 'ffmid_' . hash('sha256', $raw);
    }

    private static function messageId(): string {
        return function_exists('wp_generate_uuid4')
            ? 'ffm_msg_' . str_replace('-', '', (string)wp_generate_uuid4())
            : 'ffm_msg_' . md5(uniqid('', true));
    }

    private static function dispatchId(): string {
        return function_exists('wp_generate_uuid4')
            ? 'ffm_' . str_replace('-', '', (string)wp_generate_uuid4())
            : 'ffm_' . md5(uniqid('', true));
    }

    private static function recipientCount($to): int {
        return is_array($to) ? count(array_filter($to)) : (((string)$to !== '') ? 1 : 0);
    }

    private static function normalizeRecipients($value): array {
        $items = is_array($value) ? $value : preg_split('/\s*,\s*/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ((array)$items as $item) {
            $item = trim((string)$item);
            if ($item !== '') $out[] = $item;
        }
        return array_values(array_unique($out));
    }

    private static function encodeTransportValue($value): array {
        return ['type'=>is_array($value) ? 'array' : 'string', 'value'=>$value];
    }

    private static function decodeTransportValue(string $json) {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) return [];
        $value = $decoded['value'] ?? [];
        return (($decoded['type'] ?? '') === 'string') ? (string)$value : (array)$value;
    }

    private static function contextForStorage(array $context): array {
        $blocked = ['_recipient_claims','password','token','secret','authorization'];
        foreach ($blocked as $key) unset($context[$key]);
        return self::sanitizeContextValue($context, 0);
    }

    private static function sanitizeContextValue($value, int $depth) {
        if ($depth > 4) return null;
        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value)) return $value;
        if (is_string($value)) return strlen($value) > 1000 ? substr($value, 0, 1000) : $value;
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $key = is_int($key) ? $key : sanitize_key((string)$key);
                if ($key === '') continue;
                $out[$key] = self::sanitizeContextValue($item, $depth + 1);
            }
            return $out;
        }
        return null;
    }

    private static function attachmentPaths($attachments): array {
        $items = is_array($attachments) ? $attachments : (($attachments !== '' && $attachments !== null) ? [$attachments] : []);
        $out = [];
        $seen = [];
        foreach ($items as $path) {
            $path = (string)$path;
            if ($path === '' || !is_file($path)) continue;
            $resolved = realpath($path) ?: $path;
            $key = strtolower(str_replace('\\', '/', $resolved));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $path;
        }
        return $out;
    }

    private static function attachmentNames($attachments): array {
        return array_map('basename', self::attachmentPaths($attachments));
    }

    private static function persistAttachments($attachments, string $messageId): array {
        $paths = self::attachmentPaths($attachments);
        if (!$paths) return ['ok'=>true,'files'=>[],'error'=>''];
        $base = self::privateAttachmentBase();
        $dir = trailingslashit($base) . sanitize_file_name($messageId);
        if (!self::ensurePrivateDirectory($dir)) return ['ok'=>false,'files'=>[],'error'=>'Não foi possível criar o armazenamento privado dos anexos de retry.'];
        $files = [];
        foreach ($paths as $index => $source) {
            $name = sanitize_file_name(basename($source));
            if ($name === '') $name = 'attachment-' . ($index + 1) . '.bin';
            $target = trailingslashit($dir) . sprintf('%02d-', $index + 1) . $name;
            if (!@copy($source, $target) || !is_file($target)) {
                foreach ($files as $file) if (!empty($file['path']) && is_file($file['path'])) @unlink($file['path']);
                @rmdir($dir);
                return ['ok'=>false,'files'=>[],'error'=>'Não foi possível preservar todos os anexos para retry.'];
            }
            @chmod($target, 0600);
            $files[] = ['path'=>$target,'name'=>$name,'bytes'=>(int)filesize($target)];
        }
        return ['ok'=>true,'files'=>$files,'error'=>''];
    }

    private static function privateAttachmentBase(): string {
        return trailingslashit(WP_CONTENT_DIR) . 'fieldflow-private-mail-outbox';
    }

    private static function ensurePrivateDirectory(string $dir): bool {
        $base = self::privateAttachmentBase();
        if (!is_dir($base)) {
            if (!wp_mkdir_p($base)) return false;
            @file_put_contents(trailingslashit($base) . 'index.php', "<?php\nhttp_response_code(404);\nexit;\n");
            @file_put_contents(trailingslashit($base) . '.htaccess', "Options -Indexes\n<FilesMatch \".*\">\nRequire all denied\nDeny from all\n</FilesMatch>\n");
        }
        return is_dir($dir) || wp_mkdir_p($dir);
    }

    private static function decodeAttachments(string $json): array {
        $items = json_decode($json, true);
        return is_array($items) ? $items : [];
    }

    private static function deletePersistedAttachments(array $files, int $id): void {
        foreach ($files as $file) {
            $path = (string)($file['path'] ?? '');
            if ($path !== '' && is_file($path)) @unlink($path);
        }
        $dir = self::attachmentDirectoryForRow($files);
        if ($dir !== '' && is_dir($dir)) @rmdir($dir);
        MailOutboxRepository::update($id, ['attachments_json'=>'[]']);
    }

    private static function attachmentDirectoryForRow(array $files): string {
        foreach ($files as $file) {
            $path = (string)($file['path'] ?? '');
            if ($path !== '') return dirname($path);
        }
        return '';
    }

    private static function attemptRecord(array $transport, int $attempt, string $kind): array {
        return [
            'attempt'=>$attempt,
            'kind'=>$kind,
            'dispatch_id'=>(string)($transport['dispatch_id'] ?? ''),
            'sent'=>!empty($transport['sent']) ? 1 : 0,
            'duration_ms'=>(int)($transport['duration_ms'] ?? 0),
            'error'=>(string)($transport['error'] ?? ''),
            'at'=>current_time('mysql', true),
        ];
    }
}
