<?php
namespace RoutesPro\Services;

use RoutesPro\Forms\Forms;
use RoutesPro\Forms\RecordService;
use WP_Error;

if (!defined('ABSPATH')) exit;

/**
 * Canonical backoffice correction boundary for persisted submissions.
 *
 * Corrections never hard-delete answers or submission history. They append an
 * audit marker to meta_json, create a new record version when answers change,
 * reconcile the operational record pointer and re-evaluate route progress
 * without re-emitting route.completed / Daily Close mail side effects.
 */
final class SubmissionCorrectionService {
    public const VERSION = 1;
    public const CONTRACT = 'fieldflow-submission-corrections-audit-v1';
    private const HISTORY_LIMIT = 24;
    private const ALLOWED_STATUSES = ['submitted','reviewed','approved','rejected','draft','void'];

    /** @return array|WP_Error */
    public static function correct(int $submissionId, string $status, array $answers, string $reason, int $actorUserId = 0) {
        return self::apply($submissionId, $status, $answers, $reason, $actorUserId, false);
    }

    /** @return array|WP_Error */
    public static function void(int $submissionId, string $reason, int $actorUserId = 0) {
        return self::apply($submissionId, 'void', [], $reason, $actorUserId, true);
    }

    /** @return array|WP_Error */
    private static function apply(int $submissionId, string $status, array $answers, string $reason, int $actorUserId, bool $voidOnly) {
        global $wpdb;

        $submissionId = absint($submissionId);
        $actorUserId = $actorUserId > 0 ? $actorUserId : get_current_user_id();
        $status = SubmissionStatusPolicy::normalise($status);
        $reason = trim(sanitize_textarea_field($reason));

        if ($submissionId <= 0) return new WP_Error('fieldflow_correction_invalid', 'Submissão inválida.', ['status'=>400]);
        if (!in_array($status, self::ALLOWED_STATUSES, true)) return new WP_Error('fieldflow_correction_status', 'Estado de correção inválido.', ['status'=>422]);
        if ($reason === '') return new WP_Error('fieldflow_correction_reason', 'Indica o motivo da correção.', ['status'=>422]);

        if ($wpdb->query('START TRANSACTION') === false) {
            return new WP_Error('fieldflow_correction_transaction', 'Não foi possível iniciar a correção segura.', ['status'=>500]);
        }

        try {
            $table = Forms::table_submissions();
            $answerTable = Forms::table_answers();
            $px = $wpdb->prefix . 'routespro_';
            $submission = $wpdb->get_row($wpdb->prepare(
                "SELECT s.*, r.owner_user_id
                 FROM {$table} s
                 LEFT JOIN {$px}routes r ON r.id=s.route_id
                 WHERE s.id=%d LIMIT 1 FOR UPDATE",
                $submissionId
            ), ARRAY_A) ?: [];
            if (!$submission) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_correction_missing', 'A submissão já não existe.', ['status'=>404]);
            }

            $beforeStatus = SubmissionStatusPolicy::normalise($submission['status'] ?? 'submitted');
            $beforeRows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$answerTable} WHERE submission_id=%d ORDER BY id ASC FOR UPDATE",
                $submissionId
            ), ARRAY_A) ?: [];
            $beforeByKey = [];
            foreach ($beforeRows as $row) {
                $key = sanitize_key((string)($row['question_key'] ?? ''));
                if ($key !== '') $beforeByKey[$key] = $row;
            }

            $changedKeys = [];
            if (!$voidOnly) {
                foreach ($answers as $key => $item) {
                    if (!is_array($item)) continue;
                    $key = sanitize_key((string)$key);
                    if ($key === '') continue;
                    $type = sanitize_key((string)($item['type'] ?? 'text')) ?: 'text';
                    $label = sanitize_text_field((string)($item['label'] ?? $key));
                    $stored = self::storage($item['value'] ?? null, $type);
                    $existing = $beforeByKey[$key] ?? null;
                    if (!self::answerChanged($existing, $label, $stored)) continue;

                    $data = [
                        'question_label'=>$label,
                        'value_text'=>$stored['text'],
                        'value_number'=>$stored['number'],
                        'value_json'=>$stored['json'],
                    ];
                    $formats = ['%s','%s','%f','%s'];
                    if ($existing) {
                        $ok = $wpdb->update($answerTable, $data, ['id'=>(int)$existing['id']], $formats, ['%d']);
                    } else {
                        $ok = $wpdb->insert($answerTable, array_merge([
                            'submission_id'=>$submissionId,
                            'question_key'=>$key,
                            'created_at'=>current_time('mysql'),
                        ], $data), ['%d','%s','%s','%s','%s','%f','%s']);
                    }
                    if ($ok === false) throw new \RuntimeException($wpdb->last_error ?: 'Falha ao guardar uma resposta corrigida.');
                    $changedKeys[] = $key;
                }
            }

            $statusChanged = $status !== $beforeStatus;
            if (!$statusChanged && !$changedKeys) {
                $wpdb->query('ROLLBACK');
                return [
                    'ok'=>true,
                    'no_change'=>true,
                    'submission_id'=>$submissionId,
                    'status'=>$beforeStatus,
                    'changed_keys'=>[],
                ];
            }

            $meta = self::decodeMeta($submission['meta_json'] ?? '');
            $correctionId = wp_generate_uuid4();
            $entry = [
                'correction_id'=>$correctionId,
                'action'=>$voidOnly ? 'void' : 'correct',
                'corrected_at'=>current_time('mysql'),
                'corrected_at_gmt'=>current_time('mysql', true),
                'corrected_by'=>$actorUserId,
                'reason'=>$reason,
                'status_before'=>$beforeStatus,
                'status_after'=>$status,
                'changed_keys'=>array_values(array_unique($changedKeys)),
            ];
            $history = is_array($meta['admin_correction_history'] ?? null) ? array_values($meta['admin_correction_history']) : [];
            $history[] = $entry;
            $meta['admin_correction_history'] = array_slice($history, -self::HISTORY_LIMIT);
            $meta['last_admin_correction'] = $entry;
            $metaJson = wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $updated = $wpdb->update($table, [
                'status'=>$status,
                'meta_json'=>$metaJson,
            ], ['id'=>$submissionId], ['%s','%s'], ['%d']);
            if ($updated === false) throw new \RuntimeException($wpdb->last_error ?: 'Falha ao atualizar a submissão.');

            $afterSubmission = $wpdb->get_row($wpdb->prepare(
                "SELECT s.*, r.owner_user_id FROM {$table} s LEFT JOIN {$px}routes r ON r.id=s.route_id WHERE s.id=%d LIMIT 1",
                $submissionId
            ), ARRAY_A) ?: $submission;

            $record = ['record_id'=>absint($submission['record_id'] ?? 0), 'version_id'=>0, 'version_no'=>0];
            if ($changedKeys) {
                $currentRows = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$answerTable} WHERE submission_id=%d ORDER BY id ASC",
                    $submissionId
                ), ARRAY_A) ?: [];
                $recordAnswers = self::recordAnswers($currentRows, $answers);
                $synced = RecordService::sync_submission($submissionId, $afterSubmission, $recordAnswers);
                if (is_wp_error($synced)) {
                    $wpdb->query('ROLLBACK');
                    return $synced;
                }
                $record = array_merge($record, $synced);
                $linkUpdated = $wpdb->update($table, [
                    'record_id'=>absint($synced['record_id'] ?? 0),
                    'record_version_id'=>absint($synced['version_id'] ?? 0),
                ], ['id'=>$submissionId], ['%d','%d'], ['%d']);
                if ($linkUpdated === false) throw new \RuntimeException($wpdb->last_error ?: 'Falha ao ligar a versão de correção.');
            }

            $recordId = absint($record['record_id'] ?? $submission['record_id'] ?? 0);
            if ($recordId > 0) {
                $reconciledRecord = RecordService::reconcile_operational_records([$recordId]);
                if (is_wp_error($reconciledRecord)) {
                    $wpdb->query('ROLLBACK');
                    return $reconciledRecord;
                }
            }

            $visitAdjustment = self::reconcileVisitInsideTransaction($afterSubmission, $beforeStatus, $status, $correctionId, $reason, $actorUserId);
            if (is_wp_error($visitAdjustment)) {
                $wpdb->query('ROLLBACK');
                return $visitAdjustment;
            }

            $afterSubmission = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d LIMIT 1", $submissionId), ARRAY_A) ?: $afterSubmission;
            $context = self::context($afterSubmission, $actorUserId);
            if (class_exists('\\RoutesPro\\Core\\AuditLog')) {
                \RoutesPro\Core\AuditLog::record(
                    $voidOnly ? 'submission.voided' : 'submission.corrected',
                    'submission',
                    $submissionId,
                    ['submission'=>$submission, 'answers'=>$beforeRows],
                    ['submission'=>$afterSubmission],
                    $context,
                    [
                        'correction_id'=>$correctionId,
                        'reason'=>$reason,
                        'status_before'=>$beforeStatus,
                        'status_after'=>$status,
                        'changed_keys'=>array_values(array_unique($changedKeys)),
                        'record_id'=>$recordId,
                        'record_version_id'=>absint($record['version_id'] ?? 0),
                        'visit_adjustment'=>$visitAdjustment,
                    ]
                );
            }

            if ($wpdb->query('COMMIT') === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_correction_commit', 'Não foi possível confirmar a correção.', ['status'=>500]);
            }

            $routeId = absint($afterSubmission['route_id'] ?? 0);
            $routeProgress = null;
            $warning = '';
            if ($routeId > 0) {
                $routeProgress = RouteProgressService::sync($routeId, true, false);
                if (is_wp_error($routeProgress)) {
                    $warning = $routeProgress->get_error_message();
                    if (class_exists('\\RoutesPro\\Support\\Logger')) {
                        \RoutesPro\Support\Logger::captureThrowable('Correção guardada mas a rota precisa de reconciliação.', new \RuntimeException($warning), [
                            'context_key'=>'submission_correction_route_reconciliation',
                            'route_id'=>$routeId,
                            'submission_id'=>$submissionId,
                        ]);
                    }
                    $routeProgress = null;
                }
                if (class_exists(RouteOptimizationService::class)) {
                    RouteOptimizationService::invalidateUserRouteCache(absint($afterSubmission['owner_user_id'] ?? 0));
                }
            }

            if (class_exists('\\RoutesPro\\Core\\EventBus')) {
                \RoutesPro\Core\EventBus::emit($voidOnly ? 'submission.admin_voided' : 'submission.admin_corrected', [
                    'submission_id'=>$submissionId,
                    'status_before'=>$beforeStatus,
                    'status_after'=>$status,
                    'changed_keys'=>array_values(array_unique($changedKeys)),
                    'reason'=>$reason,
                    'correction_id'=>$correctionId,
                    'route_progress'=>$routeProgress,
                ], $context, 'backoffice');
            }

            return [
                'ok'=>true,
                'no_change'=>false,
                'submission_id'=>$submissionId,
                'status_before'=>$beforeStatus,
                'status'=>$status,
                'reason'=>$reason,
                'correction_id'=>$correctionId,
                'changed_keys'=>array_values(array_unique($changedKeys)),
                'record_id'=>$recordId,
                'record_version_id'=>absint($record['version_id'] ?? 0),
                'visit_adjustment'=>$visitAdjustment,
                'route_progress'=>$routeProgress,
                'warning'=>$warning,
                'mail_side_effects_emitted'=>false,
            ];
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            if (class_exists('\\RoutesPro\\Support\\Logger')) {
                \RoutesPro\Support\Logger::captureThrowable('Falha ao aplicar correção administrativa de submissão.', $error, [
                    'context_key'=>'submission_admin_correction',
                    'submission_id'=>$submissionId,
                ]);
            }
            return new WP_Error('fieldflow_correction_failed', $error->getMessage() ?: 'Não foi possível corrigir a submissão.', ['status'=>500]);
        }
    }

    /** @return array|WP_Error */
    private static function reconcileVisitInsideTransaction(array $submission, string $beforeStatus, string $afterStatus, string $correctionId, string $reason, int $actorUserId) {
        global $wpdb;
        $routeStopId = absint($submission['route_stop_id'] ?? 0);
        $routeId = absint($submission['route_id'] ?? 0);
        if ($routeStopId <= 0 || $routeId <= 0) return ['changed'=>false, 'reason'=>'no_route_stop'];
        if (SubmissionStatusPolicy::isOperational($afterStatus)) return ['changed'=>false, 'reason'=>'operational_submission'];
        if (!SubmissionStatusPolicy::isOperational($beforeStatus) && $beforeStatus === $afterStatus) return ['changed'=>false, 'reason'=>'already_non_operational'];

        $px = $wpdb->prefix . 'routespro_';
        $activeSql = SubmissionStatusPolicy::operationalSql('fs');
        $stop = $wpdb->get_row($wpdb->prepare(
            "SELECT rs.*, r.date AS route_date FROM {$px}route_stops rs INNER JOIN {$px}routes r ON r.id=rs.route_id WHERE rs.id=%d AND rs.route_id=%d LIMIT 1 FOR UPDATE",
            $routeStopId,
            $routeId
        ), ARRAY_A) ?: [];
        if (!$stop) return ['changed'=>false, 'reason'=>'stop_missing'];
        $locationId = absint($stop['location_id'] ?? 0);
        $routeDate = sanitize_text_field((string)($stop['route_date'] ?? ''));
        $otherOperational = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$px}form_submissions fs
             WHERE fs.id<>%d AND {$activeSql}
               AND (fs.route_stop_id=%d OR (fs.location_id=%d AND (fs.route_id=%d OR (COALESCE(fs.route_id,0)=0 AND fs.report_day=%s))))",
            absint($submission['id'] ?? 0),
            $routeStopId,
            $locationId,
            $routeId,
            $routeDate
        ));
        if ($otherOperational > 0) return ['changed'=>false, 'reason'=>'another_operational_submission', 'count'=>$otherOperational];
        $stopStatus = sanitize_key((string)($stop['status'] ?? 'pending'));
        if (!in_array($stopStatus, ['done','completed'], true)) return ['changed'=>false, 'reason'=>'stop_not_completed', 'status'=>$stopStatus];

        $meta = self::decodeMeta($stop['meta_json'] ?? '');
        $completion = is_array($meta['form_visit_completion'] ?? null) ? $meta['form_visit_completion'] : [];
        if (sanitize_key((string)($completion['source'] ?? '')) !== 'form_submission') {
            return ['changed'=>false, 'reason'=>'completion_not_owned_by_submission'];
        }

        $restoreStatus = sanitize_key((string)($completion['previous_status'] ?? 'pending'));
        if (!in_array($restoreStatus, ['pending','planned','in_progress','active','started'], true)) $restoreStatus = 'pending';
        $invalidations = is_array($completion['admin_invalidations'] ?? null) ? array_values($completion['admin_invalidations']) : [];
        $invalidations[] = [
            'correction_id'=>$correctionId,
            'submission_id'=>absint($submission['id'] ?? 0),
            'invalidated_at'=>current_time('mysql'),
            'invalidated_by'=>$actorUserId,
            'reason'=>$reason,
            'status_after'=>$afterStatus,
        ];
        $completion['admin_invalidations'] = array_slice($invalidations, -12);
        $completion['last_invalidated_submission_id'] = absint($submission['id'] ?? 0);
        $completion['last_invalidated_at'] = current_time('mysql');
        $meta['form_visit_completion'] = $completion;

        $updates = [
            'status'=>$restoreStatus,
            'departed_at'=>null,
            'duration_s'=>null,
            'meta_json'=>wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        $formats = ['%s','%s','%d','%s'];
        if (in_array($restoreStatus, ['pending','planned'], true)) {
            $updates['arrived_at'] = null;
            $formats[] = '%s';
        }
        $ok = $wpdb->update($px . 'route_stops', $updates, ['id'=>$routeStopId], $formats, ['%d']);
        if ($ok === false) return new WP_Error('fieldflow_correction_visit', $wpdb->last_error ?: 'Não foi possível reabrir a visita corrigida.', ['status'=>500]);

        if (class_exists('\\RoutesPro\\Core\\AuditLog')) {
            \RoutesPro\Core\AuditLog::record('visit.reopened_from_submission_correction', 'visit', $routeStopId, $stop, array_merge($stop, $updates), [
                'source'=>'backoffice',
                'route_id'=>$routeId,
                'route_stop_id'=>$routeStopId,
                'location_id'=>absint($stop['location_id'] ?? 0),
                'submission_id'=>absint($submission['id'] ?? 0),
                'actor_user_id'=>$actorUserId,
            ], ['correction_id'=>$correctionId, 'reason'=>$reason]);
        }
        return ['changed'=>true, 'previous_status'=>$stopStatus, 'status'=>$restoreStatus, 'route_stop_id'=>$routeStopId];
    }

    private static function recordAnswers(array $rows, array $typedAnswers): array {
        $out = [];
        foreach ($rows as $row) {
            $key = sanitize_key((string)($row['question_key'] ?? ''));
            if ($key === '') continue;
            $typed = is_array($typedAnswers[$key] ?? null) ? $typedAnswers[$key] : [];
            $type = sanitize_key((string)($typed['type'] ?? '')) ?: self::inferType($row);
            $out[$key] = [
                'type'=>$type,
                'label'=>sanitize_text_field((string)($typed['label'] ?? $row['question_label'] ?? $key)),
                'value'=>self::rowValue($row, $type),
            ];
        }
        return $out;
    }

    private static function inferType(array $row): string {
        $json = trim((string)($row['value_json'] ?? ''));
        if ($json !== '') return 'json';
        if (array_key_exists('value_number', $row) && $row['value_number'] !== null && $row['value_number'] !== '') return 'number';
        return 'text';
    }

    private static function rowValue(array $row, string $type) {
        $json = trim((string)($row['value_json'] ?? ''));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE) return $decoded;
        }
        if (in_array($type, ['number','currency','percent'], true) && $row['value_number'] !== null && $row['value_number'] !== '') return (float)$row['value_number'];
        if ($type === 'checkbox') return !empty($row['value_number']) || (string)($row['value_text'] ?? '') === '1' ? 1 : 0;
        return (string)($row['value_text'] ?? '');
    }

    private static function answerChanged(?array $existing, string $label, array $stored): bool {
        if (!$existing) return true;
        $existingNumber = $existing['value_number'] === null || $existing['value_number'] === '' ? null : (float)$existing['value_number'];
        $newNumber = $stored['number'] === null || $stored['number'] === '' ? null : (float)$stored['number'];
        return (string)($existing['question_label'] ?? '') !== $label
            || (string)($existing['value_text'] ?? '') !== (string)($stored['text'] ?? '')
            || $existingNumber !== $newNumber
            || (string)($existing['value_json'] ?? '') !== (string)($stored['json'] ?? '');
    }

    private static function storage($value, string $type): array {
        $text = null; $number = null; $json = null;
        if (is_array($value) || is_object($value)) {
            $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $text = $json;
        } elseif (in_array($type, ['number','currency','percent'], true) && $value !== '' && $value !== null) {
            $number = (float)$value;
            $text = (string)$value;
        } elseif ($type === 'checkbox') {
            $number = !empty($value) ? 1.0 : 0.0;
            $text = !empty($value) ? '1' : '0';
        } else {
            $text = is_scalar($value) ? (string)$value : wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return ['text'=>$text, 'number'=>$number, 'json'=>$json];
    }

    private static function context(array $submission, int $actorUserId): array {
        return [
            'source'=>'backoffice',
            'entity_type'=>'submission',
            'entity_id'=>absint($submission['id'] ?? 0),
            'submission_id'=>absint($submission['id'] ?? 0),
            'client_id'=>absint($submission['client_id'] ?? 0),
            'project_id'=>absint($submission['project_id'] ?? 0),
            'route_id'=>absint($submission['route_id'] ?? 0),
            'route_stop_id'=>absint($submission['route_stop_id'] ?? 0),
            'location_id'=>absint($submission['location_id'] ?? 0),
            'actor_user_id'=>$actorUserId,
        ];
    }

    private static function decodeMeta($raw): array {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
