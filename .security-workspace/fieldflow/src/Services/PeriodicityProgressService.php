<?php
namespace RoutesPro\Services;

use WP_Error;
use RoutesPro\Support\Permissions;

if (!defined('ABSPATH')) exit;

/**
 * Read-only operational coverage for the field user's assigned stores.
 *
 * It translates campaign periodicity into a compact monthly progress model
 * without changing routing, planning or submission state.
 */
final class PeriodicityProgressService {
    public const CONTRACT = 'fieldflow-my-route-periodicity-control-v1';
    public const ACTION_UX_CONTRACT = 'fieldflow-my-route-periodicity-action-ux-v1';
    public const VERSION = 3;
    public const JUSTIFIED_EXCEPTION_CONTRACT = 'fieldflow-visit-exception-justified-nonvisit-v1';

    public static function forUser(int $userId, string $month = '', int $projectId = 0, int $clientId = 0) {
        global $wpdb;
        $userId = absint($userId);
        if ($userId <= 0) return new WP_Error('fieldflow_periodicity_user_required', 'Utilizador inválido.', ['status'=>400]);

        $month = self::normaliseMonth($month);
        if ($month === '') return new WP_Error('fieldflow_periodicity_month_invalid', 'Mês inválido. Usa YYYY-MM.', ['status'=>400]);
        [$monthStart, $monthEnd] = self::monthBounds($month);
        $today = current_time('Y-m-d');
        $relation = $monthEnd < $today ? 'past' : ($monthStart > $today ? 'future' : 'current');
        $weekMeta = self::businessWeeks($monthStart, $monthEnd);
        $businessDaysRemaining = self::businessDaysRemaining($monthStart, $monthEnd, $today, $relation);

        $px = $wpdb->prefix . 'routespro_';
        $projectId = absint($projectId);
        $clientId = absint($clientId);

        if ($projectId > 0) {
            $project = $wpdb->get_row($wpdb->prepare("SELECT id,client_id,name FROM {$px}projects WHERE id=%d LIMIT 1", $projectId), ARRAY_A);
            if (!$project) return new WP_Error('fieldflow_periodicity_project_not_found', 'Campanha não encontrada.', ['status'=>404]);
            $scope = Permissions::assert_scope_or_error((int)($project['client_id'] ?? 0), $projectId);
            if (is_wp_error($scope)) return $scope;
            if ($clientId > 0 && $clientId !== (int)($project['client_id'] ?? 0)) {
                return new WP_Error('fieldflow_periodicity_scope_mismatch', 'A campanha não pertence ao cliente indicado.', ['status'=>400]);
            }
            $clientId = (int)($project['client_id'] ?? 0);
        }

        $where = ['cl.assigned_to=%d', 'cl.is_active=1', "cl.status='active'"];
        $args = [$userId];
        if ($projectId > 0) { $where[] = 'cl.project_id=%d'; $args[] = $projectId; }
        if ($clientId > 0) { $where[] = 'p.client_id=%d'; $args[] = $clientId; }

        if (!Permissions::is_manager($userId)) {
            $scope = Permissions::get_scope($userId);
            $allowedProjects = array_values(array_filter(array_map('absint', (array)($scope['project_ids'] ?? []))));
            $allowedClients = array_values(array_filter(array_map('absint', (array)($scope['client_ids'] ?? []))));
            $scopeParts = [];
            if ($allowedProjects) {
                $scopeParts[] = 'cl.project_id IN (' . implode(',', array_fill(0, count($allowedProjects), '%d')) . ')';
                $args = array_merge($args, $allowedProjects);
            }
            if ($allowedClients) {
                $scopeParts[] = 'p.client_id IN (' . implode(',', array_fill(0, count($allowedClients), '%d')) . ')';
                $args = array_merge($args, $allowedClients);
            }
            $where[] = $scopeParts ? '(' . implode(' OR ', $scopeParts) . ')' : '1=0';
        }

        $assignedSql = "SELECT cl.id AS link_id, cl.project_id, p.client_id, cl.location_id,
                               cl.visit_frequency, cl.frequency_count, cl.min_gap_days, cl.max_gap_days,
                               cl.priority, l.name, l.city, l.address,
                               p.name AS project_name, c.name AS client_name
                        FROM {$px}campaign_locations cl
                        INNER JOIN {$px}locations l ON l.id=cl.location_id
                        INNER JOIN {$px}projects p ON p.id=cl.project_id
                        LEFT JOIN {$px}clients c ON c.id=p.client_id
                        WHERE " . implode(' AND ', $where) . "
                        ORDER BY p.name ASC, cl.priority DESC, l.city ASC, l.name ASC";
        $assigned = $wpdb->get_results($wpdb->prepare($assignedSql, ...$args), ARRAY_A) ?: [];

        if (!$assigned) {
            return [
                'contract'=>self::CONTRACT,
                'version'=>self::VERSION,
                'month'=>$month,
                'month_start'=>$monthStart,
                'month_end'=>$monthEnd,
                'month_relation'=>$relation,
                'as_of'=>$today,
                'scope'=>['user_id'=>$userId,'client_id'=>$clientId,'project_id'=>$projectId,'project_name'=>'','client_name'=>''],
                'summary'=>array_merge(self::emptySummary(), ['business_days_remaining'=>$businessDaysRemaining]),
                'rows'=>[],
            ];
        }

        $assignedMap = [];
        foreach ($assigned as $row) {
            $key = self::scopeKey((int)($row['project_id'] ?? 0), (int)($row['location_id'] ?? 0));
            if ($key !== '') $assignedMap[$key] = $row;
        }

        $linkIds = array_values(array_filter(array_map(static fn($r)=>absint($r['link_id'] ?? 0), $assigned)));
        $exceptions = class_exists(VisitExceptionService::class)
            ? VisitExceptionService::forLinksForMonth($linkIds, $monthStart, $monthEnd, $today)
            : [];

        $routeEvents = self::routeStopEvents($userId, $monthStart, $monthEnd, $assignedMap, $relation, $today);
        $submissionEvents = self::submissionCompletionEvents($userId, $monthStart, $monthEnd, $assignedMap);

        $completed = $routeEvents['completed'];
        foreach ($submissionEvents as $key => $event) $completed[$key] = $event;
        $planned = $routeEvents['planned'];
        foreach (array_keys($completed) as $eventKey) unset($planned[$eventKey]);
        $justified = $routeEvents['justified'];
        foreach (array_keys($completed) as $eventKey) unset($justified[$eventKey]);

        $completedByStore = self::groupEvents($completed);
        $plannedByStore = self::groupEvents($planned);
        $justifiedByStore = self::groupEvents($justified);
        $rows = [];
        $summary = self::emptySummary();
        $summary['business_days_remaining'] = $businessDaysRemaining;
        $summary['stores_total'] = count($assigned);

        foreach ($assigned as $row) {
            $project = absint($row['project_id'] ?? 0);
            $location = absint($row['location_id'] ?? 0);
            $storeKey = self::scopeKey($project, $location);
            $frequency = sanitize_key((string)($row['visit_frequency'] ?? 'monthly'));
            if (!in_array($frequency, ['weekly','monthly'], true)) $frequency = 'monthly';
            $frequencyCount = max(1, min(7, absint($row['frequency_count'] ?? 1)));
            $required = $frequency === 'weekly' ? $frequencyCount * max(1, count($weekMeta)) : $frequencyCount;

            $completedEvents = array_values((array)($completedByStore[$storeKey] ?? []));
            $plannedEvents = array_values((array)($plannedByStore[$storeKey] ?? []));
            $justifiedEvents = array_values((array)($justifiedByStore[$storeKey] ?? []));
            usort($completedEvents, static fn($a,$b)=>strcmp((string)($a['date'] ?? ''),(string)($b['date'] ?? '')));
            usort($plannedEvents, static fn($a,$b)=>strcmp((string)($a['date'] ?? ''),(string)($b['date'] ?? '')));
            usort($justifiedEvents, static fn($a,$b)=>strcmp((string)($a['date'] ?? ''),(string)($b['date'] ?? '')));
            $completedCount = count($completedEvents);
            $plannedCount = count($plannedEvents);
            $justifiedCount = count($justifiedEvents);
            $remaining = max(0, $required - $completedCount);
            $remainingAfterPlan = max(0, $required - $completedCount - $plannedCount);
            $exception = $exceptions[absint($row['link_id'] ?? 0)] ?? null;
            $hasStoreException = is_array($exception) && $completedCount < $required;
            $justifiedSlots = $hasStoreException ? $remaining : min($remainingAfterPlan, $justifiedCount);
            $remainingAfterJustification = $hasStoreException
                ? 0
                : max(0, $required - $completedCount - $plannedCount - $justifiedSlots);

            $overdueSlots = 0;
            if ($frequency === 'weekly') {
                $completedWeek = self::weekCounts($completedEvents);
                $plannedWeek = self::weekCounts($plannedEvents);
                $justifiedWeek = self::weekCounts($justifiedEvents);
                foreach ($weekMeta as $weekKey => $meta) {
                    $done = (int)($completedWeek[$weekKey] ?? 0);
                    $futurePlan = (int)($plannedWeek[$weekKey] ?? 0);
                    $justifiedInWeek = (int)($justifiedWeek[$weekKey] ?? 0);
                    if ((string)($meta['end'] ?? '') < $today) {
                        $overdueSlots += max(0, $frequencyCount - $done - $justifiedInWeek);
                    } elseif ($relation !== 'past') {
                        // A failed stop with a reason resolves that cadence slot without pretending execution.
                        $futureGap = max(0, $frequencyCount - $done - $futurePlan - $justifiedInWeek);
                        if ($futureGap > 0 && (string)($meta['start'] ?? '') <= $today && (string)($meta['end'] ?? '') >= $today) {
                            $remainingAfterJustification = max($remainingAfterJustification, $futureGap);
                        }
                    }
                }
            }
            if ($hasStoreException) {
                $overdueSlots = 0;
                $remainingAfterJustification = 0;
            }

            if ($completedCount >= $required) $status = 'met';
            elseif ($hasStoreException) $status = 'justified';
            elseif ($overdueSlots > 0) $status = 'overdue';
            elseif ($remainingAfterJustification === 0 && $justifiedSlots > 0) $status = 'justified';
            elseif ($remainingAfterPlan === 0 && $plannedCount > 0) $status = 'planned';
            elseif ($completedCount === 0 && $relation !== 'future') $status = 'no_visit';
            else $status = 'attention';

            $coverage = $required > 0 ? min(100.0, round(($completedCount / $required) * 100, 1)) : 100.0;
            $projected = $required > 0 ? min(100.0, round((min($required, $completedCount + $plannedCount) / $required) * 100, 1)) : 100.0;
            $lastCompleted = $completedEvents ? (string)($completedEvents[count($completedEvents)-1]['date'] ?? '') : '';
            $nextPlanned = '';
            foreach ($plannedEvents as $event) {
                $eventDate = (string)($event['date'] ?? '');
                if ($eventDate !== '' && ($nextPlanned === '' || $eventDate < $nextPlanned)) $nextPlanned = $eventDate;
            }

            $periodicityLabel = self::periodicityLabel($frequency, $frequencyCount);
            $risk = self::riskProfile($status, $relation, $remainingAfterJustification, $overdueSlots, $businessDaysRemaining);
            $rowOut = [
                'link_id'=>absint($row['link_id'] ?? 0),
                'client_id'=>absint($row['client_id'] ?? 0),
                'client_name'=>sanitize_text_field((string)($row['client_name'] ?? '')),
                'project_id'=>$project,
                'project_name'=>sanitize_text_field((string)($row['project_name'] ?? '')),
                'location_id'=>$location,
                'name'=>sanitize_text_field((string)($row['name'] ?? '')),
                'city'=>sanitize_text_field((string)($row['city'] ?? '')),
                'address'=>sanitize_text_field((string)($row['address'] ?? '')),
                'priority'=>(int)($row['priority'] ?? 0),
                'visit_frequency'=>$frequency,
                'frequency_count'=>$frequencyCount,
                'periodicity_label'=>$periodicityLabel,
                'required_visits'=>$required,
                'completed_visits'=>$completedCount,
                'planned_visits'=>$plannedCount,
                'remaining_visits'=>$remaining,
                'remaining_after_plan'=>$remainingAfterPlan,
                'remaining_after_justification'=>$remainingAfterJustification,
                'justified_visits'=>$justifiedSlots,
                'is_justified'=>$status === 'justified',
                'justification_source'=>$hasStoreException ? 'store_exception' : ($justifiedSlots > 0 ? 'route_failure' : ''),
                'justification_reason'=>$hasStoreException
                    ? sanitize_text_field((string)($exception['reason_label'] ?? 'Justificada'))
                    : sanitize_text_field((string)($justifiedEvents ? ($justifiedEvents[count($justifiedEvents)-1]['reason'] ?? 'Não visita justificada') : '')),
                'exception'=>$hasStoreException ? $exception : null,
                'overdue_slots'=>$overdueSlots,
                'coverage_percent'=>$coverage,
                'projected_percent'=>$projected,
                'last_completed_date'=>$lastCompleted,
                'next_planned_date'=>$nextPlanned,
                'min_gap_days'=>max(0, (int)($row['min_gap_days'] ?? 0)),
                'max_gap_days'=>max(0, (int)($row['max_gap_days'] ?? 0)),
                'status'=>$status,
                'status_label'=>self::statusLabel($status, $remainingAfterJustification, $overdueSlots),
                'risk_level'=>(string)$risk['level'],
                'risk_label'=>(string)$risk['label'],
                'risk_score'=>(int)$risk['score'],
            ];
            $rows[] = $rowOut;

            $summary['visits_required'] += $required;
            $summary['visits_completed'] += $completedCount;
            $summary['visits_planned'] += $plannedCount;
            $summary['visits_justified'] += $justifiedSlots;
            $summary['visits_missing_after_plan'] += $remainingAfterPlan;
            if ($completedCount === 0 && $relation !== 'future' && $status !== 'justified') $summary['without_visit']++;
            if ($status === 'met') $summary['stores_met']++;
            if ($status === 'justified') $summary['justified_stores']++;
            if (in_array($status, ['met','planned','justified'], true)) $summary['stores_operationally_resolved']++;
            // Covered remains execution/plan only. A justification never pretends the store was visited.
            if (in_array($status, ['met','planned'], true)) $summary['stores_covered']++;
            if (in_array($status, ['overdue','no_visit','attention'], true)) $summary['needs_attention']++;
            if ($status === 'overdue') $summary['overdue_stores']++;
            if ($status === 'planned') $summary['covered_by_plan']++;
            if (($risk['level'] ?? '') === 'critical') $summary['stores_at_risk_critical']++;
            elseif (($risk['level'] ?? '') === 'attention') $summary['stores_at_risk_attention']++;
        }

        $severity = ['overdue'=>0,'no_visit'=>1,'attention'=>2,'justified'=>3,'planned'=>4,'met'=>5];
        usort($rows, static function($a, $b) use ($severity) {
            $ra = (int)($a['risk_score'] ?? 0);
            $rb = (int)($b['risk_score'] ?? 0);
            if ($ra !== $rb) return $rb <=> $ra;
            $sa = $severity[(string)($a['status'] ?? '')] ?? 9;
            $sb = $severity[(string)($b['status'] ?? '')] ?? 9;
            if ($sa !== $sb) return $sa <=> $sb;
            $ma = (int)($a['remaining_after_plan'] ?? 0);
            $mb = (int)($b['remaining_after_plan'] ?? 0);
            if ($ma !== $mb) return $mb <=> $ma;
            $pa = (int)($a['priority'] ?? 0);
            $pb = (int)($b['priority'] ?? 0);
            if ($pa !== $pb) return $pb <=> $pa;
            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        $summary['visit_coverage_percent'] = $summary['visits_required'] > 0
            ? min(100.0, round(($summary['visits_completed'] / $summary['visits_required']) * 100, 1)) : 100.0;
        $summary['projected_coverage_percent'] = $summary['visits_required'] > 0
            ? min(100.0, round((min($summary['visits_required'], $summary['visits_completed'] + $summary['visits_planned']) / $summary['visits_required']) * 100, 1)) : 100.0;

        $scopeProjectName = '';
        $scopeClientName = '';
        if ($projectId > 0) {
            foreach ($assigned as $row) {
                if ((int)($row['project_id'] ?? 0) === $projectId) {
                    $scopeProjectName = sanitize_text_field((string)($row['project_name'] ?? ''));
                    $scopeClientName = sanitize_text_field((string)($row['client_name'] ?? ''));
                    break;
                }
            }
        }

        return [
            'contract'=>self::CONTRACT,
            'version'=>self::VERSION,
            'month'=>$month,
            'month_start'=>$monthStart,
            'month_end'=>$monthEnd,
            'month_relation'=>$relation,
            'as_of'=>$today,
            'scope'=>[
                'user_id'=>$userId,
                'client_id'=>$clientId,
                'project_id'=>$projectId,
                'project_name'=>$scopeProjectName,
                'client_name'=>$scopeClientName,
            ],
            'summary'=>$summary,
            'rows'=>$rows,
        ];
    }

    private static function routeStopEvents(int $userId, string $monthStart, string $monthEnd, array $assignedMap, string $relation, string $today): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $sql = "SELECT rs.id AS stop_id, rs.location_id, rs.status AS stop_status, rs.fail_reason,
                       r.id AS route_id, r.project_id, r.date AS route_date, r.status AS route_status
                FROM {$px}route_stops rs
                INNER JOIN {$px}routes r ON r.id=rs.route_id
                WHERE r.date BETWEEN %s AND %s
                  AND COALESCE(r.status,'') NOT IN ('canceled','cancelled')
                  AND (r.owner_user_id=%d OR EXISTS (
                        SELECT 1 FROM {$px}assignments a
                        WHERE a.route_id=r.id AND a.user_id=%d AND a.is_active=1
                  ))";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $monthStart, $monthEnd, $userId, $userId), ARRAY_A) ?: [];
        $completed = [];
        $planned = [];
        $justified = [];
        foreach ($rows as $row) {
            $storeKey = self::scopeKey((int)($row['project_id'] ?? 0), (int)($row['location_id'] ?? 0));
            if ($storeKey === '' || !isset($assignedMap[$storeKey])) continue;
            $stopId = absint($row['stop_id'] ?? 0);
            if ($stopId <= 0) continue;
            $eventKey = 'stop:' . $stopId;
            $date = sanitize_text_field((string)($row['route_date'] ?? ''));
            $stopStatus = sanitize_key((string)($row['stop_status'] ?? 'pending'));
            $routeStatus = sanitize_key((string)($row['route_status'] ?? ''));
            $event = ['key'=>$eventKey,'store_key'=>$storeKey,'date'=>$date,'route_id'=>absint($row['route_id'] ?? 0),'stop_id'=>$stopId];
            if (in_array($stopStatus, ['done','completed'], true)) {
                $completed[$eventKey] = $event;
                continue;
            }
            if ($stopStatus === 'failed') {
                $reason = sanitize_text_field((string)($row['fail_reason'] ?? ''));
                if ($reason !== '') {
                    $event['reason'] = $reason;
                    $justified[$eventKey] = $event;
                }
                continue;
            }
            if (!in_array($routeStatus, ['planned','in_progress'], true)) continue;
            if ($relation === 'past') continue;
            if ($relation === 'current' && $date < $today) continue;
            $planned[$eventKey] = $event;
        }
        return ['completed'=>$completed,'planned'=>$planned,'justified'=>$justified];
    }

    private static function submissionCompletionEvents(int $userId, string $monthStart, string $monthEnd, array $assignedMap): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $statusSql = class_exists(SubmissionStatusPolicy::class)
            ? SubmissionStatusPolicy::operationalSql('fs')
            : "COALESCE(fs.status,'submitted') IN ('submitted','reviewed','approved')";
        $sql = "SELECT fs.id, fs.route_stop_id, fs.route_id, fs.location_id, fs.project_id,
                       fs.report_day, fs.submitted_at, r.date AS route_date, r.project_id AS route_project_id
                FROM {$px}form_submissions fs
                LEFT JOIN {$px}routes r ON r.id=fs.route_id
                WHERE {$statusSql}
                  AND COALESCE(fs.report_day,r.date,DATE(fs.submitted_at)) BETWEEN %s AND %s
                  AND (
                        fs.user_id=%d
                        OR r.owner_user_id=%d
                        OR EXISTS (SELECT 1 FROM {$px}assignments a WHERE a.route_id=r.id AND a.user_id=%d AND a.is_active=1)
                  )";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $monthStart, $monthEnd, $userId, $userId, $userId), ARRAY_A) ?: [];
        $events = [];
        foreach ($rows as $row) {
            $projectId = absint($row['project_id'] ?? 0) ?: absint($row['route_project_id'] ?? 0);
            $locationId = absint($row['location_id'] ?? 0);
            $storeKey = self::scopeKey($projectId, $locationId);
            if ($storeKey === '' || !isset($assignedMap[$storeKey])) continue;
            $date = sanitize_text_field((string)($row['report_day'] ?? ''));
            if ($date === '') $date = sanitize_text_field((string)($row['route_date'] ?? ''));
            if ($date === '' && !empty($row['submitted_at'])) $date = substr((string)$row['submitted_at'], 0, 10);
            if ($date === '') continue;
            $stopId = absint($row['route_stop_id'] ?? 0);
            $routeId = absint($row['route_id'] ?? 0);
            if ($stopId > 0) $eventKey = 'stop:' . $stopId;
            elseif ($routeId > 0) $eventKey = 'route:' . $routeId . '|location:' . $locationId . '|date:' . $date;
            else $eventKey = 'location:' . $locationId . '|date:' . $date;
            $events[$eventKey] = ['key'=>$eventKey,'store_key'=>$storeKey,'date'=>$date,'route_id'=>$routeId,'stop_id'=>$stopId];
        }
        return $events;
    }

    private static function groupEvents(array $events): array {
        $grouped = [];
        foreach ($events as $event) {
            $key = (string)($event['store_key'] ?? '');
            if ($key === '') continue;
            if (!isset($grouped[$key])) $grouped[$key] = [];
            $grouped[$key][] = $event;
        }
        return $grouped;
    }

    private static function weekCounts(array $events): array {
        $out = [];
        foreach ($events as $event) {
            $date = (string)($event['date'] ?? '');
            $ts = $date !== '' ? strtotime($date . ' 12:00:00') : false;
            if (!$ts) continue;
            $key = date('o-W', $ts);
            $out[$key] = (int)($out[$key] ?? 0) + 1;
        }
        return $out;
    }

    private static function businessWeeks(string $start, string $end): array {
        $out = [];
        $cursor = strtotime($start . ' 12:00:00');
        $last = strtotime($end . ' 12:00:00');
        while ($cursor && $last && $cursor <= $last) {
            $weekday = (int)date('N', $cursor);
            if ($weekday <= 5) {
                $key = date('o-W', $cursor);
                $date = date('Y-m-d', $cursor);
                if (!isset($out[$key])) $out[$key] = ['start'=>$date,'end'=>$date];
                $out[$key]['start'] = min((string)$out[$key]['start'], $date);
                $out[$key]['end'] = max((string)$out[$key]['end'], $date);
            }
            $cursor = strtotime('+1 day', $cursor);
        }
        return $out;
    }

    private static function businessDaysRemaining(string $start, string $end, string $today, string $relation): int {
        if ($relation === 'past') return 0;
        $cursorDate = $relation === 'current' && $today > $start ? $today : $start;
        $cursor = strtotime($cursorDate . ' 12:00:00');
        $last = strtotime($end . ' 12:00:00');
        $count = 0;
        while ($cursor && $last && $cursor <= $last) {
            if ((int)date('N', $cursor) <= 5) $count++;
            $cursor = strtotime('+1 day', $cursor);
        }
        return $count;
    }

    private static function riskProfile(string $status, string $relation, int $remainingAfterPlan, int $overdueSlots, int $businessDaysRemaining): array {
        if ($status === 'justified') {
            return ['level'=>'normal','label'=>'Justificada','score'=>0];
        }
        if (in_array($status, ['met','planned'], true)) {
            return ['level'=>'normal','label'=>'Normal','score'=>0];
        }
        if ($relation === 'future') {
            return ['level'=>'normal','label'=>'Normal','score'=>max(1, $remainingAfterPlan)];
        }
        if ($relation === 'past' || $overdueSlots > 0) {
            return ['level'=>'critical','label'=>'Crítico','score'=>100 + ($overdueSlots * 10) + max(0, $remainingAfterPlan)];
        }
        $pressureLimit = max(3, $remainingAfterPlan * 2);
        if ($remainingAfterPlan > 0 && ($businessDaysRemaining <= $pressureLimit || ($status === 'no_visit' && $businessDaysRemaining <= 5))) {
            return ['level'=>'critical','label'=>'Crítico','score'=>90 + ($remainingAfterPlan * 5) + ($status === 'no_visit' ? 5 : 0)];
        }
        return ['level'=>'attention','label'=>'Atenção','score'=>50 + ($remainingAfterPlan * 5) + ($status === 'no_visit' ? 5 : 0)];
    }

    private static function periodicityLabel(string $frequency, int $count): string {
        if ($frequency === 'weekly') return $count . ' visita' . ($count === 1 ? '' : 's') . '/semana';
        $prefix = ($count >= 1 && $count <= 4) ? 'P' . $count . ' · ' : '';
        return $prefix . $count . ' visita' . ($count === 1 ? '' : 's') . '/mês';
    }

    private static function statusLabel(string $status, int $remainingAfterPlan, int $overdueSlots): string {
        if ($status === 'met') return 'Periodicidade cumprida';
        if ($status === 'planned') return 'Coberta no plano';
        if ($status === 'justified') return 'Não visita justificada';
        if ($status === 'overdue') return $overdueSlots > 1 ? $overdueSlots . ' visitas em atraso' : '1 visita em atraso';
        if ($status === 'no_visit') return 'Sem visita este mês';
        if ($remainingAfterPlan > 1) return 'Faltam planear ' . $remainingAfterPlan;
        if ($remainingAfterPlan === 1) return 'Falta planear 1';
        return 'A acompanhar';
    }

    private static function emptySummary(): array {
        return [
            'stores_total'=>0,
            'stores_met'=>0,
            'stores_covered'=>0,
            'stores_operationally_resolved'=>0,
            'justified_stores'=>0,
            'covered_by_plan'=>0,
            'needs_attention'=>0,
            'without_visit'=>0,
            'overdue_stores'=>0,
            'visits_required'=>0,
            'visits_completed'=>0,
            'visits_planned'=>0,
            'visits_justified'=>0,
            'visits_missing_after_plan'=>0,
            'visit_coverage_percent'=>0.0,
            'projected_coverage_percent'=>0.0,
            'business_days_remaining'=>0,
            'stores_at_risk_critical'=>0,
            'stores_at_risk_attention'=>0,
        ];
    }

    private static function scopeKey(int $projectId, int $locationId): string {
        return $projectId > 0 && $locationId > 0 ? $projectId . '|' . $locationId : '';
    }

    private static function normaliseMonth(string $month): string {
        $month = trim($month);
        if ($month === '') $month = current_time('Y-m');
        if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) return '';
        $year = (int)$m[1]; $mon = (int)$m[2];
        if ($year < 2000 || $year > 2100 || $mon < 1 || $mon > 12) return '';
        return sprintf('%04d-%02d', $year, $mon);
    }

    private static function monthBounds(string $month): array {
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start . ' 12:00:00'));
        return [$start, $end];
    }
}
