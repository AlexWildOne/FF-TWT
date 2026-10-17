<?php
namespace RoutesPro\Services;

use WP_Error;
use RoutesPro\Support\Permissions;

if (!defined('ABSPATH')) exit;

/**
 * FieldFlow 2.2.586 Daily Operational Priority.
 *
 * Read-only deterministic daily focus built exclusively from FieldFlow state.
 * It never creates, reorders or mutates routes and never calls Google/AI.
 */
final class DailyOperationalPriorityService {
    public const CONTRACT = 'fieldflow-daily-operational-priority-v1';
    public const VERSION = 1;

    public static function forUser(int $userId, string $date = '', int $projectId = 0, int $clientId = 0) {
        global $wpdb;
        $userId = absint($userId);
        if ($userId <= 0) return new WP_Error('fieldflow_daily_priority_user_required', 'Utilizador inválido.', ['status'=>400]);

        $date = self::normaliseDate($date);
        if ($date === '') return new WP_Error('fieldflow_daily_priority_date_invalid', 'Data inválida. Usa YYYY-MM-DD.', ['status'=>400]);
        $projectId = absint($projectId);
        $clientId = absint($clientId);

        if ($projectId > 0) {
            $px = $wpdb->prefix . 'routespro_';
            $project = $wpdb->get_row($wpdb->prepare("SELECT id,client_id,name FROM {$px}projects WHERE id=%d LIMIT 1", $projectId), ARRAY_A);
            if (!$project) return new WP_Error('fieldflow_daily_priority_project_not_found', 'Campanha não encontrada.', ['status'=>404]);
            // Authorize the logged-in caller to inspect the selected scope. The target
            // field user is constrained separately by PeriodicityProgressService and
            // the route-stop query below. This preserves coordinator oversight without
            // granting the target user's permissions to the caller.
            $scope = Permissions::assert_scope_or_error((int)($project['client_id'] ?? 0), $projectId);
            if (is_wp_error($scope)) return $scope;
            $clientId = (int)($project['client_id'] ?? 0);
        }

        $coverage = PeriodicityProgressService::forUser($userId, substr($date, 0, 7), $projectId, $clientId);
        if (is_wp_error($coverage)) return $coverage;

        $routeStops = self::routeStopsForDate($userId, $date, $projectId, $clientId);
        $coverageRows = array_values((array)($coverage['rows'] ?? []));
        $ranked = self::rankSignals($routeStops, $coverageRows, $date);

        $routeIds = [];
        $summary = [
            'routes_today'=>0,
            'stops_total'=>0,
            'stops_completed'=>0,
            'stops_active'=>0,
            'stops_pending'=>0,
            'stops_justified'=>0,
            'critical_unresolved'=>0,
            'attention_unresolved'=>0,
            'focus_items'=>count($ranked),
            'all_clear'=>empty($ranked),
        ];
        foreach ($routeStops as $stop) {
            $routeId = (int)($stop['route_id'] ?? 0);
            if ($routeId > 0) $routeIds[$routeId] = true;
            $summary['stops_total']++;
            $state = (string)($stop['operational_state'] ?? 'pending');
            if ($state === 'completed') $summary['stops_completed']++;
            elseif ($state === 'active') $summary['stops_active']++;
            elseif ($state === 'justified') $summary['stops_justified']++;
            else $summary['stops_pending']++;
        }
        $summary['routes_today'] = count($routeIds);
        foreach ($coverageRows as $row) {
            if ((int)($row['remaining_after_justification'] ?? $row['remaining_after_plan'] ?? 0) <= 0) continue;
            $risk = (string)($row['risk_level'] ?? 'normal');
            if ($risk === 'critical') $summary['critical_unresolved']++;
            elseif ($risk === 'attention') $summary['attention_unresolved']++;
        }

        $level = 'clear';
        $label = 'Operação controlada';
        if ($summary['stops_active'] > 0) { $level = 'active'; $label = 'Visita em curso'; }
        elseif ($summary['critical_unresolved'] > 0) { $level = 'critical'; $label = 'Prioridade crítica'; }
        elseif ($summary['stops_pending'] > 0 || $summary['attention_unresolved'] > 0) { $level = 'attention'; $label = 'Há trabalho por concluir'; }

        return [
            'contract'=>self::CONTRACT,
            'version'=>self::VERSION,
            'date'=>$date,
            'as_of'=>current_time('mysql'),
            'scope'=>[
                'user_id'=>$userId,
                'client_id'=>$clientId,
                'project_id'=>$projectId,
            ],
            'health'=>['level'=>$level,'label'=>$label],
            'summary'=>$summary,
            'priorities'=>$ranked,
            'policy'=>[
                'read_only'=>true,
                'external_api_calls'=>false,
                'ai_calls'=>false,
                'route_mutations'=>false,
                'human_action_required'=>true,
            ],
        ];
    }

    /**
     * Pure deterministic ranking used by runtime and certification fixtures.
     */
    public static function rankSignals(array $routeStops, array $coverageRows, string $date): array {
        $coverageMap = [];
        foreach ($coverageRows as $row) {
            $key = self::scopeKey((int)($row['project_id'] ?? 0), (int)($row['location_id'] ?? 0));
            if ($key !== '') $coverageMap[$key] = $row;
        }

        $todayLocations = [];
        $items = [];
        foreach ($routeStops as $stop) {
            $projectId = (int)($stop['project_id'] ?? 0);
            $locationId = (int)($stop['location_id'] ?? 0);
            $key = self::scopeKey($projectId, $locationId);
            if ($key !== '') $todayLocations[$key] = true;
            $state = (string)($stop['operational_state'] ?? 'pending');
            if (in_array($state, ['completed','justified'], true)) continue;
            $coverage = $coverageMap[$key] ?? [];
            $riskScore = max(0, (int)($coverage['risk_score'] ?? 0));
            $riskLevel = (string)($coverage['risk_level'] ?? 'normal');
            $active = $state === 'active';
            $score = ($active ? 1000 : 800) + min(150, $riskScore) - min(99, max(0, (int)($stop['seq'] ?? 0)));
            $items[] = self::item([
                'score'=>$score,
                'kind'=>$active ? 'continue_visit' : 'visit_today',
                'severity'=>$active ? 'active' : ($riskLevel === 'critical' ? 'critical' : 'attention'),
                'title'=>$active ? 'Continuar visita em curso' : 'Visita planeada para hoje',
                'reason'=>$active
                    ? 'A loja já tem atividade iniciada e deve ser concluída antes de abrir novo trabalho.'
                    : (($riskLevel === 'critical') ? 'Está na rota de hoje e a periodicidade encontra-se em risco crítico.' : 'Está na rota publicada de hoje e ainda não foi concluída.'),
                'store_name'=>(string)($stop['location_name'] ?? 'Loja'),
                'project_name'=>(string)($stop['project_name'] ?? ''),
                'client_name'=>(string)($stop['client_name'] ?? ''),
                'route_id'=>(int)($stop['route_id'] ?? 0),
                'stop_id'=>(int)($stop['stop_id'] ?? 0),
                'location_id'=>$locationId,
                'project_id'=>$projectId,
                'client_id'=>(int)($stop['client_id'] ?? 0),
                'link_id'=>(int)($coverage['link_id'] ?? 0),
                'action'=>'open_route',
                'action_label'=>$active ? 'Retomar visita' : 'Abrir rota',
                'risk_level'=>$riskLevel,
                'risk_score'=>$riskScore,
            ]);
        }

        foreach ($coverageRows as $row) {
            $projectId = (int)($row['project_id'] ?? 0);
            $locationId = (int)($row['location_id'] ?? 0);
            $key = self::scopeKey($projectId, $locationId);
            if ($key === '' || isset($todayLocations[$key])) continue;
            $status = (string)($row['status'] ?? '');
            $risk = (string)($row['risk_level'] ?? 'normal');
            $remaining = (int)($row['remaining_after_justification'] ?? $row['remaining_after_plan'] ?? 0);

            if ($status === 'justified') {
                $validUntil = (string)($row['exception']['valid_until'] ?? '');
                $days = self::dateDistanceDays($date, $validUntil);
                if ($validUntil !== '' && $days >= 0 && $days <= 3) {
                    $items[] = self::item([
                        'score'=>380 - $days,
                        'kind'=>'review_justification',
                        'severity'=>'attention',
                        'title'=>'Justificação perto do fim',
                        'reason'=>'A exceção operacional termina em breve e deve ser revista antes de voltar a estado crítico.',
                        'store_name'=>(string)($row['name'] ?? 'Loja'),
                        'project_name'=>(string)($row['project_name'] ?? ''),
                        'client_name'=>(string)($row['client_name'] ?? ''),
                        'location_id'=>$locationId,
                        'project_id'=>$projectId,
                        'client_id'=>(int)($row['client_id'] ?? 0),
                        'link_id'=>(int)($row['link_id'] ?? 0),
                        'action'=>'review_justification',
                        'action_label'=>'Rever motivo',
                        'risk_level'=>'attention',
                        'risk_score'=>(int)($row['risk_score'] ?? 0),
                        'valid_until'=>$validUntil,
                    ]);
                }
                continue;
            }
            if ($remaining <= 0 || !in_array($risk, ['critical','attention'], true)) continue;

            $riskScore = max(0, (int)($row['risk_score'] ?? 0));
            $score = ($risk === 'critical' ? 700 : 500) + min(180, $riskScore);
            $items[] = self::item([
                'score'=>$score,
                'kind'=>'plan_store',
                'severity'=>$risk,
                'title'=>$risk === 'critical' ? 'Planear loja crítica' : 'Garantir cobertura do mês',
                'reason'=>(string)($row['status_label'] ?? ($remaining > 1 ? 'Faltam visitas por planear.' : 'Falta uma visita por planear.')),
                'store_name'=>(string)($row['name'] ?? 'Loja'),
                'project_name'=>(string)($row['project_name'] ?? ''),
                'client_name'=>(string)($row['client_name'] ?? ''),
                'location_id'=>$locationId,
                'project_id'=>$projectId,
                'client_id'=>(int)($row['client_id'] ?? 0),
                'link_id'=>(int)($row['link_id'] ?? 0),
                'action'=>'open_planner',
                'action_label'=>'Planear',
                'risk_level'=>$risk,
                'risk_score'=>$riskScore,
                'remaining_visits'=>$remaining,
                'business_days_remaining'=>(int)($row['business_days_remaining'] ?? 0),
            ]);
        }

        usort($items, static function(array $a, array $b): int {
            $score = ((int)($b['score'] ?? 0)) <=> ((int)($a['score'] ?? 0));
            if ($score !== 0) return $score;
            $route = ((int)($a['route_id'] ?? 0)) <=> ((int)($b['route_id'] ?? 0));
            if ($route !== 0) return $route;
            return strcmp((string)($a['store_name'] ?? ''), (string)($b['store_name'] ?? ''));
        });
        return array_slice(array_values($items), 0, 12);
    }

    private static function routeStopsForDate(int $userId, string $date, int $projectId, int $clientId): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $where = ['r.date=%s', "COALESCE(r.status,'') NOT IN ('canceled','cancelled')", '(r.owner_user_id=%d OR EXISTS (SELECT 1 FROM ' . $px . 'assignments a WHERE a.route_id=r.id AND a.user_id=%d AND a.is_active=1))'];
        $args = [$date, $userId, $userId];
        if ($projectId > 0) { $where[] = 'r.project_id=%d'; $args[] = $projectId; }
        if ($clientId > 0) { $where[] = 'r.client_id=%d'; $args[] = $clientId; }
        $sql = "SELECT r.id AS route_id,r.client_id,r.project_id,r.status AS route_status,r.date,
                       rs.id AS stop_id,rs.location_id,rs.seq,rs.status AS stop_status,rs.fail_reason,rs.arrived_at,rs.departed_at,
                       COALESCE(l.name,'Loja') AS location_name,COALESCE(p.name,'') AS project_name,COALESCE(c.name,'') AS client_name
                FROM {$px}routes r
                INNER JOIN {$px}route_stops rs ON rs.route_id=r.id
                LEFT JOIN {$px}locations l ON l.id=rs.location_id
                LEFT JOIN {$px}projects p ON p.id=r.project_id
                LEFT JOIN {$px}clients c ON c.id=r.client_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY r.id ASC,rs.seq ASC,rs.id ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
        foreach ($rows as &$row) $row['operational_state'] = self::operationalState($row);
        unset($row);
        return $rows;
    }

    private static function operationalState(array $row): string {
        $status = strtolower(trim((string)($row['stop_status'] ?? 'pending')));
        if (in_array($status, ['done','completed'], true)) return 'completed';
        if ($status === 'failed' && trim((string)($row['fail_reason'] ?? '')) !== '') return 'justified';
        if (in_array($status, ['in_progress','active','started'], true)) return 'active';
        if (trim((string)($row['arrived_at'] ?? '')) !== '' && trim((string)($row['departed_at'] ?? '')) === '') return 'active';
        return 'pending';
    }

    private static function item(array $item): array {
        return array_merge([
            'score'=>0,'kind'=>'','severity'=>'attention','title'=>'','reason'=>'','store_name'=>'',
            'project_name'=>'','client_name'=>'','route_id'=>0,'stop_id'=>0,'location_id'=>0,'project_id'=>0,
            'client_id'=>0,'link_id'=>0,'action'=>'','action_label'=>'Abrir','risk_level'=>'normal','risk_score'=>0,
        ], $item);
    }

    private static function normaliseDate(string $date): string {
        $date = trim($date);
        if ($date === '') $date = current_time('Y-m-d');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) return '';
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $date : '';
    }

    private static function scopeKey(int $projectId, int $locationId): string {
        return $projectId > 0 && $locationId > 0 ? $projectId . '|' . $locationId : '';
    }

    private static function dateDistanceDays(string $from, string $to): int {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) return -9999;
        try {
            $a = new \DateTimeImmutable($from . ' 00:00:00');
            $b = new \DateTimeImmutable($to . ' 00:00:00');
            return (int)$a->diff($b)->format('%r%a');
        } catch (\Throwable $e) { return -9999; }
    }
}
