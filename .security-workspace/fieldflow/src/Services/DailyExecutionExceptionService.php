<?php
namespace RoutesPro\Services;

use WP_Error;
use RoutesPro\Support\Permissions;

if (!defined('ABSPATH')) exit;

/**
 * FieldFlow 2.2.587 Daily Execution & Exception Control.
 *
 * Read-only execution authority that reconciles route-stop state with the
 * latest operational submission and exposes deterministic exceptions. It does
 * not mutate routes, submissions or planning and never calls external APIs.
 */
final class DailyExecutionExceptionService {
    public const CONTRACT = 'fieldflow-daily-execution-exception-control-v1';
    public const VERSION = 1;

    public static function forUser(int $userId, string $date = '', int $projectId = 0, int $clientId = 0) {
        global $wpdb;
        $userId = absint($userId);
        if ($userId <= 0) return new WP_Error('fieldflow_daily_execution_user_required', 'Utilizador inválido.', ['status'=>400]);
        $date = self::normaliseDate($date);
        if ($date === '') return new WP_Error('fieldflow_daily_execution_date_invalid', 'Data inválida. Usa YYYY-MM-DD.', ['status'=>400]);
        $projectId = absint($projectId);
        $clientId = absint($clientId);

        if ($projectId > 0) {
            $px = $wpdb->prefix . 'routespro_';
            $project = $wpdb->get_row($wpdb->prepare("SELECT id,client_id,name FROM {$px}projects WHERE id=%d LIMIT 1", $projectId), ARRAY_A);
            if (!$project) return new WP_Error('fieldflow_daily_execution_project_not_found', 'Campanha não encontrada.', ['status'=>404]);
            $scope = Permissions::assert_scope_or_error((int)($project['client_id'] ?? 0), $projectId);
            if (is_wp_error($scope)) return $scope;
            $clientId = (int)($project['client_id'] ?? 0);
        }

        $rows = self::rowsForDate($userId, $date, $projectId, $clientId);
        $today = current_time('Y-m-d');
        $states = [];
        $exceptions = [];
        $routes = [];
        $summary = [
            'routes'=>0,
            'planned'=>0,
            'in_progress'=>0,
            'completed'=>0,
            'justified'=>0,
            'pending'=>0,
            'exceptions'=>0,
            'critical_exceptions'=>0,
            'attention_exceptions'=>0,
            'all_clear'=>true,
        ];

        foreach ($rows as $row) {
            $state = self::classifyRecord($row, $date, $today);
            $states[] = $state;
            $routeId = (int)($state['route_id'] ?? 0);
            if ($routeId > 0) {
                if (!isset($routes[$routeId])) $routes[$routeId] = ['route_id'=>$routeId,'route_status'=>(string)($row['route_status'] ?? ''),'total'=>0,'resolved'=>0,'pending'=>0];
                $routes[$routeId]['total']++;
                if (!empty($state['resolved'])) $routes[$routeId]['resolved']++;
                else $routes[$routeId]['pending']++;
            }
            $bucket = (string)($state['summary_bucket'] ?? 'pending');
            if (isset($summary[$bucket])) $summary[$bucket]++;
            if (!empty($state['is_exception'])) $exceptions[] = self::exceptionFromState($state);
        }

        foreach ($routes as $route) {
            $routeStatus = self::key((string)($route['route_status'] ?? ''));
            if (in_array($routeStatus, ['completed','done'], true) && (int)($route['pending'] ?? 0) > 0) {
                $exceptions[] = [
                    'key'=>'route_completion_mismatch',
                    'severity'=>'critical',
                    'title'=>'Rota fechada com visitas por resolver',
                    'reason'=>'A rota está marcada como concluída mas ainda contém paragens sem estado terminal canónico.',
                    'route_id'=>(int)$route['route_id'],
                    'stop_id'=>0,
                    'location_id'=>0,
                    'store_name'=>'Rota #' . (int)$route['route_id'],
                    'project_name'=>'',
                    'client_name'=>'',
                    'action'=>'open_route',
                    'action_label'=>'Abrir rota',
                ];
            }
        }

        usort($exceptions, static function(array $a, array $b): int {
            $rank = ['critical'=>0,'attention'=>1,'info'=>2];
            $ar = $rank[$a['severity'] ?? 'info'] ?? 9;
            $br = $rank[$b['severity'] ?? 'info'] ?? 9;
            if ($ar !== $br) return $ar <=> $br;
            return ((int)($a['route_id'] ?? 0) <=> (int)($b['route_id'] ?? 0)) ?: ((int)($a['stop_id'] ?? 0) <=> (int)($b['stop_id'] ?? 0));
        });
        $exceptions = array_values(array_slice($exceptions, 0, 20));
        $summary['routes'] = count($routes);
        $summary['exceptions'] = count($exceptions);
        foreach ($exceptions as $exception) {
            if (($exception['severity'] ?? '') === 'critical') $summary['critical_exceptions']++;
            elseif (($exception['severity'] ?? '') === 'attention') $summary['attention_exceptions']++;
        }
        $summary['all_clear'] = $summary['exceptions'] === 0 && $summary['pending'] === 0 && $summary['in_progress'] === 0;

        return [
            'contract'=>self::CONTRACT,
            'version'=>self::VERSION,
            'date'=>$date,
            'as_of'=>current_time('mysql'),
            'scope'=>['user_id'=>$userId,'client_id'=>$clientId,'project_id'=>$projectId],
            'summary'=>$summary,
            'states'=>$states,
            'exceptions'=>$exceptions,
            'policy'=>[
                'read_only'=>true,
                'external_api_calls'=>false,
                'ai_calls'=>false,
                'route_mutations'=>false,
                'submission_mutations'=>false,
                'canonical_stop_authority'=>'VisitCompletionPolicy',
                'canonical_submission_authority'=>'SubmissionStatusPolicy',
            ],
        ];
    }

    /**
     * Pure deterministic classifier used by runtime and certification fixtures.
     */
    public static function classifyRecord(array $row, string $date, string $today): array {
        $status = self::key((string)($row['stop_status'] ?? 'pending'));
        $reason = trim((string)($row['fail_reason'] ?? ''));
        $hasReport = !empty($row['submission_id']);
        $submissionStatus = self::key((string)($row['submission_status'] ?? 'submitted'));
        $operationalReport = $hasReport && !in_array($submissionStatus, ['void','cancelled','deleted','trash','rejected','draft'], true);
        $executed = in_array($status, ['done','completed'], true);
        $justified = $status === 'failed' && $reason !== '';
        $active = in_array($status, ['in_progress','active','started'], true)
            || (trim((string)($row['arrived_at'] ?? '')) !== '' && trim((string)($row['departed_at'] ?? '')) === '' && !$executed && !$justified);
        $past = $date < $today;
        $future = $date > $today;

        $base = [
            'state'=>'pending','label'=>'Pendente','severity'=>'info','resolved'=>false,'is_exception'=>false,
            'exception_key'=>'','reason'=>'','summary_bucket'=>'pending',
            'route_id'=>(int)($row['route_id'] ?? 0),'stop_id'=>(int)($row['stop_id'] ?? 0),'location_id'=>(int)($row['location_id'] ?? 0),
            'project_id'=>(int)($row['project_id'] ?? 0),'client_id'=>(int)($row['client_id'] ?? 0),
            'store_name'=>(string)($row['location_name'] ?? 'Loja'),'project_name'=>(string)($row['project_name'] ?? ''),'client_name'=>(string)($row['client_name'] ?? ''),
            'route_status'=>(string)($row['route_status'] ?? ''),'stop_status'=>$status,'submission_id'=>(int)($row['submission_id'] ?? 0),'submission_status'=>$submissionStatus,
            'action'=>'open_route','action_label'=>'Abrir rota',
        ];

        if ($operationalReport && !$executed && !$justified) {
            return array_merge($base, [
                'state'=>'report_without_completion','label'=>'Reporte sem fecho','severity'=>'critical','is_exception'=>true,
                'exception_key'=>'report_without_completion','reason'=>'Existe um reporte operacional final mas a paragem ainda não está concluída. Minha Rota e Reportar precisam de ser reconciliados.',
                'summary_bucket'=>'pending','action_label'=>'Rever visita',
            ]);
        }
        if ($executed && !$operationalReport) {
            return array_merge($base, [
                'state'=>'completed_without_report','label'=>'Concluída sem reporte','severity'=>'attention','resolved'=>true,'is_exception'=>true,
                'exception_key'=>'completed_without_report','reason'=>'A paragem está concluída mas não foi encontrado um reporte operacional final associado.',
                'summary_bucket'=>'completed','action_label'=>'Rever reporte',
            ]);
        }
        if ($executed) {
            return array_merge($base, ['state'=>'completed','label'=>'Concluída','severity'=>'clear','resolved'=>true,'summary_bucket'=>'completed','action'=>'','action_label'=>'']);
        }
        if ($justified) {
            return array_merge($base, ['state'=>'justified','label'=>'Não visita justificada','severity'=>'clear','resolved'=>true,'summary_bucket'=>'justified','reason'=>$reason,'action'=>'','action_label'=>'']);
        }
        if ($status === 'failed' && $reason === '') {
            return array_merge($base, [
                'state'=>'failed_without_reason','label'=>'Falha sem motivo','severity'=>'critical','is_exception'=>true,
                'exception_key'=>'failed_without_reason','reason'=>'A visita está marcada como falhada mas não tem motivo de não visita. Deve ser justificada ou corrigida.',
                'summary_bucket'=>'pending','action_label'=>'Corrigir',
            ]);
        }
        if ($active) {
            if ($past) {
                return array_merge($base, [
                    'state'=>'visit_left_open','label'=>'Visita ficou aberta','severity'=>'critical','is_exception'=>true,
                    'exception_key'=>'visit_left_open','reason'=>'A visita ficou em curso num dia anterior e precisa de fecho operacional.',
                    'summary_bucket'=>'in_progress','action_label'=>'Retomar / fechar',
                ]);
            }
            return array_merge($base, ['state'=>'in_progress','label'=>'Em curso','severity'=>'active','summary_bucket'=>'in_progress','reason'=>'A visita está iniciada e ainda não terminou.','action_label'=>'Retomar visita']);
        }
        if ($past) {
            return array_merge($base, [
                'state'=>'planned_visit_not_closed','label'=>'Visita não resolvida','severity'=>'critical','is_exception'=>true,
                'exception_key'=>'planned_visit_not_closed','reason'=>'A visita ficou pendente depois do fim do dia operacional.',
                'summary_bucket'=>'pending','action_label'=>'Resolver',
            ]);
        }
        if ($future) {
            return array_merge($base, ['state'=>'planned','label'=>'Planeada','severity'=>'info','summary_bucket'=>'planned','reason'=>'Visita planeada para uma data futura.']);
        }
        return array_merge($base, ['state'=>'pending','label'=>'Pendente hoje','severity'=>'attention','summary_bucket'=>'pending','reason'=>'Visita planeada para hoje ainda por iniciar ou concluir.']);
    }

    private static function rowsForDate(int $userId, string $date, int $projectId, int $clientId): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $where = [
            'r.date=%s',
            "COALESCE(r.status,'') NOT IN ('canceled','cancelled')",
            '(r.owner_user_id=%d OR EXISTS (SELECT 1 FROM ' . $px . 'assignments a WHERE a.route_id=r.id AND a.user_id=%d AND a.is_active=1))',
        ];
        $args = [$date,$userId,$userId];
        if ($projectId > 0) { $where[]='r.project_id=%d'; $args[]=$projectId; }
        if ($clientId > 0) { $where[]='r.client_id=%d'; $args[]=$clientId; }
        $operationalSql = SubmissionStatusPolicy::operationalSql('fs2');
        $sql = "SELECT r.id AS route_id,r.client_id,r.project_id,r.status AS route_status,r.date,
                       rs.id AS stop_id,rs.location_id,rs.seq,rs.status AS stop_status,rs.fail_reason,rs.arrived_at,rs.departed_at,
                       COALESCE(l.name,'Loja') AS location_name,COALESCE(p.name,'') AS project_name,COALESCE(c.name,'') AS client_name,
                       fs.id AS submission_id,fs.status AS submission_status,fs.submitted_at
                FROM {$px}routes r
                INNER JOIN {$px}route_stops rs ON rs.route_id=r.id
                LEFT JOIN {$px}locations l ON l.id=rs.location_id
                LEFT JOIN {$px}projects p ON p.id=r.project_id
                LEFT JOIN {$px}clients c ON c.id=r.client_id
                LEFT JOIN {$px}form_submissions fs ON fs.id=(
                    SELECT fs2.id FROM {$px}form_submissions fs2
                    WHERE {$operationalSql}
                      AND (
                        fs2.route_stop_id=rs.id
                        OR (
                            COALESCE(fs2.route_stop_id,0)=0
                            AND fs2.location_id=rs.location_id
                            AND NOT EXISTS (
                                SELECT 1 FROM {$px}route_stops rs_dup
                                WHERE rs_dup.route_id=r.id AND rs_dup.location_id=rs.location_id AND rs_dup.id<>rs.id
                            )
                            AND (
                                fs2.route_id=r.id
                                OR (COALESCE(fs2.route_id,0)=0 AND (fs2.report_day=r.date OR (fs2.report_day IS NULL AND DATE(fs2.submitted_at)=r.date)))
                            )
                        )
                      )
                    ORDER BY CASE WHEN fs2.route_stop_id=rs.id THEN 0 WHEN fs2.route_id=r.id THEN 1 ELSE 2 END ASC,fs2.id DESC
                    LIMIT 1
                )
                WHERE " . implode(' AND ', $where) . "
                ORDER BY r.id ASC,rs.seq ASC,rs.id ASC";
        return $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
    }

    private static function exceptionFromState(array $state): array {
        return [
            'key'=>(string)($state['exception_key'] ?? $state['state'] ?? 'exception'),
            'severity'=>(string)($state['severity'] ?? 'attention'),
            'title'=>(string)($state['label'] ?? 'Exceção operacional'),
            'reason'=>(string)($state['reason'] ?? ''),
            'route_id'=>(int)($state['route_id'] ?? 0),
            'stop_id'=>(int)($state['stop_id'] ?? 0),
            'location_id'=>(int)($state['location_id'] ?? 0),
            'project_id'=>(int)($state['project_id'] ?? 0),
            'client_id'=>(int)($state['client_id'] ?? 0),
            'store_name'=>(string)($state['store_name'] ?? 'Loja'),
            'project_name'=>(string)($state['project_name'] ?? ''),
            'client_name'=>(string)($state['client_name'] ?? ''),
            'action'=>(string)($state['action'] ?? 'open_route'),
            'action_label'=>(string)($state['action_label'] ?? 'Abrir rota'),
        ];
    }

    private static function normaliseDate(string $date): string {
        $date = trim($date);
        if ($date === '') $date = current_time('Y-m-d');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) return '';
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $date : '';
    }

    private static function key(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-]/', '', $value) ?: '';
        return $value;
    }
}
