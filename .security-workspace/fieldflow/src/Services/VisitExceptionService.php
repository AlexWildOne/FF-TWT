<?php
namespace RoutesPro\Services;

use RoutesPro\Core\AuditLog;
use RoutesPro\Support\Permissions;
use WP_Error;

if (!defined('ABSPATH')) exit;

/**
 * Operational exception ledger for justified non-visits.
 *
 * This deliberately lives outside the campaign cadence schema. A justification
 * explains why an expected visit is not operationally critical; it never turns
 * that visit into an execution and never rewrites P1/P2/P3/P4.
 */
final class VisitExceptionService {
    public const CONTRACT = 'fieldflow-visit-exception-justified-nonvisit-v1';
    public const OPTION = 'fieldflow_visit_exceptions_v1';
    public const VERSION = 1;

    private const REASONS = [
        'works' => 'Obras',
        'temporarily_closed' => 'Encerramento temporário',
        'permanently_closed' => 'Encerramento definitivo',
        'access_blocked' => 'Loja inacessível / sem acesso',
        'store_refusal' => 'Recusa da loja',
        'client_request' => 'Pedido do cliente',
        'operational_change' => 'Alteração operacional',
        'other' => 'Outro',
    ];

    public static function reasons(): array {
        return self::REASONS;
    }

    public static function canManageLink(int $linkId, ?int $userId = null): bool {
        $userId = $userId ?: get_current_user_id();
        $linkId = absint($linkId);
        $userId = absint($userId);
        if ($linkId <= 0 || $userId <= 0) return false;
        if (Permissions::is_manager($userId)) return true;
        $row = self::linkRow($linkId);
        return is_array($row) && absint($row['assigned_to'] ?? 0) === $userId;
    }

    public static function upsert(int $linkId, array $payload, ?int $actorUserId = null) {
        $linkId = absint($linkId);
        $actorUserId = absint($actorUserId ?: get_current_user_id());
        if ($linkId <= 0 || $actorUserId <= 0) return new WP_Error('fieldflow_visit_exception_invalid', 'Pedido inválido.', ['status'=>400]);
        if (!self::canManageLink($linkId, $actorUserId)) return new WP_Error('fieldflow_visit_exception_forbidden', 'Sem permissão para justificar esta loja.', ['status'=>403]);

        $link = self::linkRow($linkId);
        if (!$link) return new WP_Error('fieldflow_visit_exception_link_not_found', 'Atribuição da loja não encontrada.', ['status'=>404]);

        $reason = sanitize_key((string)($payload['reason'] ?? ''));
        if (!isset(self::REASONS[$reason])) return new WP_Error('fieldflow_visit_exception_reason_required', 'Seleciona o motivo da não visita.', ['status'=>400]);
        $note = sanitize_textarea_field((string)($payload['note'] ?? ''));
        $validFrom = self::date((string)($payload['valid_from'] ?? current_time('Y-m-d')));
        if ($validFrom === '') $validFrom = current_time('Y-m-d');
        $validUntil = self::date((string)($payload['valid_until'] ?? ''));
        $month = self::month((string)($payload['month'] ?? ''));

        // Temporary exceptions should not silently become permanent. If the
        // operator omits an end date we scope them to the selected month.
        if ($validUntil === '' && $reason !== 'permanently_closed') {
            $baseMonth = $month !== '' ? $month : substr($validFrom, 0, 7);
            $validUntil = self::monthEnd($baseMonth);
        }
        if ($validUntil !== '' && $validUntil < $validFrom) {
            return new WP_Error('fieldflow_visit_exception_date_order', 'A data final não pode ser anterior à data inicial.', ['status'=>400]);
        }

        $store = self::store();
        $records = array_values((array)($store['links'][(string)$linkId] ?? []));
        $now = current_time('mysql');
        $before = null;
        foreach ($records as &$record) {
            if ((string)($record['status'] ?? '') !== 'active') continue;
            $before = $record;
            $record['status'] = 'superseded';
            $record['superseded_at'] = $now;
            $record['superseded_by'] = $actorUserId;
        }
        unset($record);

        $id = substr(hash('sha256', $linkId . '|' . $actorUserId . '|' . $now . '|' . wp_generate_uuid4()), 0, 24);
        $record = [
            'id'=>$id,
            'contract'=>self::CONTRACT,
            'link_id'=>$linkId,
            'project_id'=>absint($link['project_id'] ?? 0),
            'client_id'=>absint($link['client_id'] ?? 0),
            'location_id'=>absint($link['location_id'] ?? 0),
            'assigned_to'=>absint($link['assigned_to'] ?? 0),
            'reason'=>$reason,
            'reason_label'=>self::REASONS[$reason],
            'note'=>$note,
            'valid_from'=>$validFrom,
            'valid_until'=>$validUntil,
            'status'=>'active',
            'created_at'=>$now,
            'created_by'=>$actorUserId,
            'updated_at'=>$now,
            'updated_by'=>$actorUserId,
        ];
        $records[] = $record;
        // Bound storage per campaign-location while keeping enough audit history.
        if (count($records) > 40) $records = array_slice($records, -40);
        $store['links'][(string)$linkId] = $records;
        self::saveStore($store);

        if (class_exists(AuditLog::class)) {
            AuditLog::record('visit_exception.upsert', 'campaign_location', $linkId, $before, $record, [
                'actor_user_id'=>$actorUserId,
                'client_id'=>absint($link['client_id'] ?? 0),
                'project_id'=>absint($link['project_id'] ?? 0),
                'location_id'=>absint($link['location_id'] ?? 0),
            ], ['contract'=>self::CONTRACT]);
        }
        return self::publicRecord($record);
    }

    public static function clear(int $linkId, ?int $actorUserId = null) {
        $linkId = absint($linkId);
        $actorUserId = absint($actorUserId ?: get_current_user_id());
        if ($linkId <= 0 || $actorUserId <= 0) return new WP_Error('fieldflow_visit_exception_invalid', 'Pedido inválido.', ['status'=>400]);
        if (!self::canManageLink($linkId, $actorUserId)) return new WP_Error('fieldflow_visit_exception_forbidden', 'Sem permissão para reabrir esta loja.', ['status'=>403]);
        $link = self::linkRow($linkId);
        if (!$link) return new WP_Error('fieldflow_visit_exception_link_not_found', 'Atribuição da loja não encontrada.', ['status'=>404]);

        $store = self::store();
        $records = array_values((array)($store['links'][(string)$linkId] ?? []));
        $now = current_time('mysql');
        $before = null;
        $changed = false;
        foreach ($records as &$record) {
            if ((string)($record['status'] ?? '') !== 'active') continue;
            $before = $record;
            $record['status'] = 'cleared';
            $record['cleared_at'] = $now;
            $record['cleared_by'] = $actorUserId;
            $record['updated_at'] = $now;
            $record['updated_by'] = $actorUserId;
            $changed = true;
        }
        unset($record);
        if ($changed) {
            $store['links'][(string)$linkId] = $records;
            self::saveStore($store);
            if (class_exists(AuditLog::class)) {
                AuditLog::record('visit_exception.clear', 'campaign_location', $linkId, $before, null, [
                    'actor_user_id'=>$actorUserId,
                    'client_id'=>absint($link['client_id'] ?? 0),
                    'project_id'=>absint($link['project_id'] ?? 0),
                    'location_id'=>absint($link['location_id'] ?? 0),
                ], ['contract'=>self::CONTRACT]);
            }
        }
        return ['ok'=>true,'link_id'=>$linkId,'cleared'=>$changed,'contract'=>self::CONTRACT];
    }

    public static function currentForLink(int $linkId, string $onDate = ''): ?array {
        $linkId = absint($linkId);
        if ($linkId <= 0) return null;
        $date = self::date($onDate);
        if ($date === '') $date = current_time('Y-m-d');
        $records = array_values((array)(self::store()['links'][(string)$linkId] ?? []));
        for ($i=count($records)-1; $i>=0; $i--) {
            $record = $records[$i];
            if ((string)($record['status'] ?? '') !== 'active') continue;
            if (self::recordCoversDate($record, $date)) return self::publicRecord($record);
        }
        return null;
    }

    /** @return array<int,array> keyed by campaign_location link id */
    public static function forLinksForMonth(array $linkIds, string $monthStart, string $monthEnd, string $asOf = ''): array {
        $ids = array_values(array_unique(array_filter(array_map('absint', $linkIds))));
        if (!$ids) return [];
        $monthStart = self::date($monthStart);
        $monthEnd = self::date($monthEnd);
        if ($monthStart === '' || $monthEnd === '') return [];
        $today = self::date($asOf);
        if ($today === '') $today = current_time('Y-m-d');
        $relation = $monthEnd < $today ? 'past' : ($monthStart > $today ? 'future' : 'current');
        $store = self::store();
        $out = [];
        foreach ($ids as $linkId) {
            $records = array_values((array)($store['links'][(string)$linkId] ?? []));
            for ($i=count($records)-1; $i>=0; $i--) {
                $record = $records[$i];
                if (!self::recordAppliesToWindow($record, $monthStart, $monthEnd, $relation)) continue;
                $out[$linkId] = self::publicRecord($record);
                break;
            }
        }
        return $out;
    }

    public static function recordAppliesToWindow(array $record, string $start, string $end, string $relation): bool {
        $status = (string)($record['status'] ?? '');
        if ($relation !== 'past' && $status !== 'active') return false;
        if ($relation === 'past' && !in_array($status, ['active','cleared','superseded'], true)) return false;
        $from = self::date((string)($record['valid_from'] ?? ''));
        if ($from === '') return false;
        $until = self::effectiveEnd($record);
        if ($until === '') $until = '9999-12-31';
        return $from <= $end && $until >= $start;
    }

    private static function recordCoversDate(array $record, string $date): bool {
        $from = self::date((string)($record['valid_from'] ?? ''));
        $until = self::date((string)($record['valid_until'] ?? ''));
        if ($from === '' || $date < $from) return false;
        return $until === '' || $date <= $until;
    }

    private static function effectiveEnd(array $record): string {
        $ends = [];
        $valid = self::date((string)($record['valid_until'] ?? ''));
        if ($valid !== '') $ends[] = $valid;
        foreach (['cleared_at','superseded_at'] as $key) {
            $value = (string)($record[$key] ?? '');
            if ($value !== '') {
                $date = self::date(substr($value, 0, 10));
                if ($date !== '') $ends[] = $date;
            }
        }
        return $ends ? min($ends) : '';
    }

    private static function publicRecord(array $record): array {
        return [
            'contract'=>self::CONTRACT,
            'id'=>sanitize_text_field((string)($record['id'] ?? '')),
            'link_id'=>absint($record['link_id'] ?? 0),
            'project_id'=>absint($record['project_id'] ?? 0),
            'client_id'=>absint($record['client_id'] ?? 0),
            'location_id'=>absint($record['location_id'] ?? 0),
            'reason'=>sanitize_key((string)($record['reason'] ?? 'other')),
            'reason_label'=>sanitize_text_field((string)($record['reason_label'] ?? (self::REASONS[(string)($record['reason'] ?? '')] ?? 'Outro'))),
            'note'=>sanitize_textarea_field((string)($record['note'] ?? '')),
            'valid_from'=>self::date((string)($record['valid_from'] ?? '')),
            'valid_until'=>self::date((string)($record['valid_until'] ?? '')),
            'status'=>sanitize_key((string)($record['status'] ?? '')),
            'created_at'=>sanitize_text_field((string)($record['created_at'] ?? '')),
            'created_by'=>absint($record['created_by'] ?? 0),
        ];
    }

    private static function linkRow(int $linkId): ?array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT cl.id,cl.project_id,cl.location_id,cl.assigned_to,p.client_id
             FROM {$px}campaign_locations cl
             INNER JOIN {$px}projects p ON p.id=cl.project_id
             WHERE cl.id=%d LIMIT 1",
            $linkId
        ), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private static function store(): array {
        $raw = get_option(self::OPTION, []);
        if (!is_array($raw)) $raw = [];
        if (!isset($raw['version'])) $raw['version'] = self::VERSION;
        if (!isset($raw['links']) || !is_array($raw['links'])) $raw['links'] = [];
        return $raw;
    }

    private static function saveStore(array $store): void {
        $store['version'] = self::VERSION;
        // false keeps this operational history out of WordPress autoload.
        if (get_option(self::OPTION, null) === null) add_option(self::OPTION, $store, '', false);
        else update_option(self::OPTION, $store, false);
    }

    private static function date(string $value): string {
        $value = trim($value);
        if ($value === '') return '';
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) return '';
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $value : '';
    }

    private static function month(string $value): string {
        $value = trim($value);
        if (!preg_match('/^(\d{4})-(\d{2})$/', $value, $m)) return '';
        $mon = (int)$m[2];
        return ((int)$m[1] >= 2000 && (int)$m[1] <= 2100 && $mon >= 1 && $mon <= 12) ? $value : '';
    }

    private static function monthEnd(string $month): string {
        if (self::month($month) === '') return '';
        return date('Y-m-t', strtotime($month . '-01 12:00:00'));
    }
}
