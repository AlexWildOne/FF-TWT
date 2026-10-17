<?php
namespace RoutesPro\Analytics;

use RoutesPro\Core\AuditLog;
use RoutesPro\Core\EventBus;
use RoutesPro\Support\Logger;

if (!defined('ABSPATH')) exit;

/**
 * FieldFlow Intelligence Learning Foundation.
 *
 * This service persists deterministic recommendations, human feedback and
 * observed outcome signals. It never trains or mutates scoring rules by itself.
 * The stored dataset is an auditable foundation for later adaptive learning.
 */
final class RecommendationLearningService {
    public const VERSION = '1.1.0';
    private static bool $registered = false;

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_intelligence_recommendations';
    }

    public static function eventsTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_intelligence_feedback_events';
    }

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_action('admin_post_fieldflow_intelligence_feedback', [self::class, 'handleFeedback']);
    }

    public static function tablesReady(): bool {
        global $wpdb;
        $table = self::table();
        $events = self::eventsTable();
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table
            && (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $events)) === $events;
    }

    /**
     * Persist explainable recommendations derived from current intelligence.
     * No autonomous operational action is performed.
     */
    public static function capture(
        array $stats,
        array $context,
        array $anomalies,
        array $scores,
        array $priorityLocations,
        array $actions,
        array $confidence
    ): array {
        if (!self::tablesReady()) {
            return ['available'=>false,'recommendations'=>[],'summary'=>self::emptySummary(),'policy'=>self::policy()];
        }

        $candidates = self::candidates($stats, $context, $anomalies, $scores, $priorityLocations, $actions, $confidence);
        $saved = [];
        foreach (array_slice($candidates, 0, 24) as $candidate) {
            try {
                $row = self::upsertCandidate($candidate, $context);
                if ($row) $saved[] = self::publicRow($row);
            } catch (\Throwable $e) {
                if (class_exists(Logger::class)) {
                    Logger::warning('Falha ao persistir recomendação Intelligence.', ['context_key'=>'intelligence_learning'], ['error'=>$e->getMessage()]);
                }
            }
        }

        $filters = self::scopeFilters($context);
        return [
            'available'=>true,
            'recommendations'=>self::recent(8, $filters, ['active','accepted','deferred']),
            'summary'=>self::summary($filters),
            'captured'=>count($saved),
            'policy'=>self::policy(),
        ];
    }

    public static function recent(int $limit = 20, array $filters = [], array $statuses = []): array {
        global $wpdb;
        if (!self::tablesReady()) return [];
        $limit = max(1, min(250, $limit));
        $where = ['1=1'];
        $args = [];
        foreach (['client_id','project_id','route_id','user_id','location_id'] as $field) {
            $value = absint($filters[$field] ?? 0);
            if ($value > 0) { $where[] = $field . '=%d'; $args[] = $value; }
        }
        if ($statuses) {
            $statuses = array_values(array_filter(array_map('sanitize_key', $statuses)));
            if ($statuses) {
                $where[] = 'status IN (' . implode(',', array_fill(0, count($statuses), '%s')) . ')';
                array_push($args, ...$statuses);
            }
        }
        $sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode(' AND ', $where)
            . " ORDER BY FIELD(severity,'critical','high','medium','low'), confidence_score DESC, last_seen_at DESC, id DESC LIMIT {$limit}";
        $rows = $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
        return array_map([self::class, 'publicRow'], $rows);
    }

    public static function summary(array $filters = []): array {
        global $wpdb;
        $out = self::emptySummary();
        if (!self::tablesReady()) return $out;
        $where = ['1=1'];
        $args = [];
        foreach (['client_id','project_id','route_id','user_id','location_id'] as $field) {
            $value = absint($filters[$field] ?? 0);
            if ($value > 0) { $where[] = $field . '=%d'; $args[] = $value; }
        }
        $base = ' FROM ' . self::table() . ' WHERE ' . implode(' AND ', $where);
        $statusSql = 'SELECT status,COUNT(*) total' . $base . ' GROUP BY status';
        $rows = $args ? ($wpdb->get_results($wpdb->prepare($statusSql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($statusSql, ARRAY_A) ?: []);
        foreach ($rows as $row) {
            $status = sanitize_key((string)($row['status'] ?? ''));
            $count = (int)($row['total'] ?? 0);
            $out['total'] += $count;
            if (array_key_exists($status, $out)) $out[$status] = $count;
        }
        $openStatuses = ['active','accepted','deferred'];
        $out['open'] = array_sum(array_map(static fn($s)=>(int)($out[$s] ?? 0), $openStatuses));

        $coverageWhere = $where;
        $coverageWhere[] = "outcome_status NOT IN ('','pending','not_observed')";
        $coverageSql = 'SELECT COUNT(*)' . ' FROM ' . self::table() . ' WHERE ' . implode(' AND ', $coverageWhere);
        $observed = (int)($args ? $wpdb->get_var($wpdb->prepare($coverageSql, ...$args)) : $wpdb->get_var($coverageSql));
        $out['outcomes_observed'] = $observed;
        $out['outcome_coverage'] = $out['total'] > 0 ? round(($observed / $out['total']) * 100, 1) : 0.0;

        $feedbackWhere = $where;
        $feedbackWhere[] = 'feedback_at IS NOT NULL';
        $feedbackSql = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . implode(' AND ', $feedbackWhere);
        $feedback = (int)($args ? $wpdb->get_var($wpdb->prepare($feedbackSql, ...$args)) : $wpdb->get_var($feedbackSql));
        $out['feedback_total'] = $feedback;
        $out['feedback_coverage'] = $out['total'] > 0 ? round(($feedback / $out['total']) * 100, 1) : 0.0;
        return $out;
    }

    public static function publicRecommendations(array $filters = [], int $limit = 6): array {
        return self::recent($limit, $filters, ['active','accepted','deferred']);
    }

    /**
     * Aggregated learning evidence per store. 2.2.437 consumes this only as
     * confidence evidence. It never changes risk/opportunity/health scores.
     */
    public static function locationLearningStats(array $locationIds, array $context = []): array {
        global $wpdb;
        if (!self::tablesReady()) return [];
        $ids = array_values(array_unique(array_filter(array_map('absint', $locationIds))));
        if (!$ids) return [];
        $where = ['location_id IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')'];
        $args = $ids;
        foreach (['client_id','project_id','route_id','user_id'] as $field) {
            $value = absint($context[$field] ?? 0);
            if ($value > 0) { $where[] = $field . '=%d'; $args[] = $value; }
        }
        $sql = 'SELECT location_id, COUNT(*) total,'
            . " SUM(CASE WHEN status IN ('active','accepted','deferred') THEN 1 ELSE 0 END) open_count,"
            . " SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) active_count,"
            . " SUM(CASE WHEN status='accepted' THEN 1 ELSE 0 END) accepted_count,"
            . " SUM(CASE WHEN status='deferred' THEN 1 ELSE 0 END) deferred_count,"
            . " SUM(CASE WHEN status='resolved' THEN 1 ELSE 0 END) resolved_count,"
            . " SUM(CASE WHEN status='not_relevant' THEN 1 ELSE 0 END) not_relevant_count,"
            . " SUM(CASE WHEN outcome_status IN ('improving','stable','worsening') THEN 1 ELSE 0 END) observed_count,"
            . " SUM(CASE WHEN outcome_status='improving' THEN 1 ELSE 0 END) improving_count,"
            . " SUM(CASE WHEN outcome_status='stable' THEN 1 ELSE 0 END) stable_count,"
            . " SUM(CASE WHEN outcome_status='worsening' THEN 1 ELSE 0 END) worsening_count,"
            . ' AVG(confidence_score) confidence_avg FROM ' . self::table()
            . ' WHERE ' . implode(' AND ', $where) . ' GROUP BY location_id';
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $id = absint($row['location_id'] ?? 0); if ($id <= 0) continue;
            $observed = (int)($row['observed_count'] ?? 0);
            $improving = (int)($row['improving_count'] ?? 0);
            $out[$id] = [
                'total'=>(int)($row['total'] ?? 0),
                'open'=>(int)($row['open_count'] ?? 0),
                'active'=>(int)($row['active_count'] ?? 0),
                'accepted'=>(int)($row['accepted_count'] ?? 0),
                'deferred'=>(int)($row['deferred_count'] ?? 0),
                'resolved'=>(int)($row['resolved_count'] ?? 0),
                'not_relevant'=>(int)($row['not_relevant_count'] ?? 0),
                'observed'=>$observed,
                'improving'=>$improving,
                'stable'=>(int)($row['stable_count'] ?? 0),
                'worsening'=>(int)($row['worsening_count'] ?? 0),
                'efficacy_rate'=>$observed > 0 ? round(($improving / $observed) * 100, 1) : 0.0,
                'confidence_avg'=>round((float)($row['confidence_avg'] ?? 0), 1),
            ];
        }
        return $out;
    }

    public static function forScope(array $context, int $limit = 8): array {
        $filters = self::scopeFilters($context);
        return [
            'available'=>self::tablesReady(),
            'recommendations'=>self::publicRecommendations($filters, $limit),
            'summary'=>self::summary($filters),
            'policy'=>self::policy(),
        ];
    }

    public static function handleFeedback(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_intelligence_feedback');
        $id = absint($_POST['recommendation_id'] ?? 0);
        $decision = sanitize_key((string)($_POST['decision'] ?? ''));
        $note = sanitize_textarea_field((string)wp_unslash($_POST['feedback_note'] ?? ''));
        $allowed = ['accepted','not_relevant','deferred','resolved'];
        $status = 'feedback_error';
        if ($id > 0 && in_array($decision, $allowed, true)) {
            $status = self::applyFeedback($id, $decision, $note) ? 'learning_feedback_saved' : 'feedback_error';
        }
        $args = ['page'=>'routespro-assignments-hub','tab'=>'learning','ff_status'=>$status];
        foreach (['client_id','project_id','route_id','user_id','location_id'] as $key) {
            $value = absint($_POST[$key] ?? 0);
            if ($value > 0) $args[$key] = $value;
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function applyFeedback(int $id, string $decision, string $note = ''): bool {
        global $wpdb;
        if (!self::tablesReady()) return false;
        $decision = sanitize_key($decision);
        $allowed = ['accepted','not_relevant','deferred','resolved'];
        if (!in_array($decision, $allowed, true)) return false;
        $before = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d LIMIT 1', $id), ARRAY_A);
        if (!$before) return false;

        $statusMap = [
            'accepted'=>'accepted',
            'not_relevant'=>'not_relevant',
            'deferred'=>'deferred',
            'resolved'=>'resolved',
        ];
        $now = current_time('mysql');
        $data = [
            'status'=>$statusMap[$decision],
            'feedback_label'=>$decision,
            'feedback_note'=>$note,
            'feedback_by'=>get_current_user_id() ?: 0,
            'feedback_at'=>$now,
            'feedback_count'=>(int)($before['feedback_count'] ?? 0) + 1,
            'updated_at'=>$now,
        ];
        $formats = ['%s','%s','%s','%d','%s','%d','%s'];
        if ($decision === 'resolved') {
            $data['resolved_at'] = $now;
            $data['outcome_status'] = 'human_resolved';
            $data['outcome_note'] = $note !== '' ? $note : 'Marcado como resolvido por revisão humana.';
            $formats[] = '%s'; $formats[] = '%s'; $formats[] = '%s';
        }
        $ok = $wpdb->update(self::table(), $data, ['id'=>$id], $formats, ['%d']);
        if ($ok === false) return false;
        $after = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d LIMIT 1', $id), ARRAY_A) ?: $before;
        self::recordEvent($id, 'feedback.' . $decision, [
            'decision'=>$decision,
            'note'=>$note,
            'before_status'=>(string)($before['status'] ?? ''),
            'after_status'=>(string)($after['status'] ?? ''),
        ], $after);
        $context = self::rowContext($after);
        if (class_exists(AuditLog::class)) AuditLog::record('intelligence.feedback.' . $decision, 'intelligence_recommendation', $id, self::publicRow($before), self::publicRow($after), $context);
        if (class_exists(EventBus::class)) EventBus::emit('intelligence.feedback.' . $decision, self::eventPayload($after, ['feedback_note'=>$note]), array_merge($context, ['entity_type'=>'intelligence_recommendation','entity_id'=>$id]), 'backoffice');
        return true;
    }

    private static function candidates(array $stats, array $context, array $anomalies, array $scores, array $priorityLocations, array $actions, array $confidence): array {
        $out = [];
        $confidenceScore = max(0.0, min(100.0, (float)($confidence['score'] ?? 0)));
        $actionMap = [];
        foreach ($actions as $action) {
            if (!is_array($action) || empty($action['id'])) continue;
            $actionMap[(string)$action['id']] = $action;
        }

        foreach ($anomalies as $anomaly) {
            if (!is_array($anomaly)) continue;
            $severity = sanitize_key((string)($anomaly['severity'] ?? 'medium'));
            if (!in_array($severity, ['medium','high','critical'], true)) continue;
            $type = sanitize_key((string)($anomaly['type'] ?? 'signal'));
            $meta = is_array($anomaly['meta'] ?? null) ? $anomaly['meta'] : [];
            $locationIds = array_values(array_filter(array_map('absint', (array)($meta['location_ids'] ?? []))));
            $locationNames = array_values(array_map('sanitize_text_field', (array)($meta['location_names'] ?? [])));
            if (!$locationIds && absint($context['location_id'] ?? 0) > 0) $locationIds = [absint($context['location_id'])];
            if (!$locationIds) $locationIds = [0];
            foreach (array_slice($locationIds, 0, 12) as $index=>$locationId) {
                $prescription = self::prescription($type, $actionMap);
                $title = (string)($anomaly['title'] ?? 'Sinal operacional');
                $locationName = sanitize_text_field((string)($locationNames[$index] ?? ''));
                if ($locationName !== '') $title .= ' · ' . $locationName;
                $out[] = [
                    'recommendation_type'=>'anomaly_' . $type,
                    'source_type'=>'anomaly',
                    'source_key'=>$type,
                    'severity'=>$severity,
                    'location_id'=>$locationId,
                    'title'=>$title,
                    'detail'=>sanitize_text_field((string)($anomaly['detail'] ?? '')),
                    'rationale'=>'Regra determinística FieldFlow: ' . sanitize_text_field((string)($anomaly['detail'] ?? '')),
                    'suggested_action'=>$prescription['action'],
                    'metric_key'=>sanitize_key((string)($anomaly['metric'] ?? '')),
                    'current_value'=>self::scalarString($anomaly['value'] ?? null),
                    'threshold_value'=>self::scalarString($anomaly['threshold'] ?? null),
                    'desired_direction'=>$prescription['direction'],
                    'confidence_score'=>$confidenceScore,
                    'snapshot'=>[
                        'source'=>'anomaly','anomaly'=>$anomaly,
                        'decision_scores'=>self::compactScores($scores),
                        'period'=>['from'=>(string)($stats['from'] ?? ''),'to'=>(string)($stats['to'] ?? '')],
                    ],
                ];
            }
        }

        foreach ($priorityLocations as $row) {
            if (!is_array($row)) continue;
            $score = (float)($row['priority_score'] ?? 0);
            if ($score < 48) continue;
            $locationId = absint($row['location_id'] ?? 0);
            if ($locationId <= 0) continue;
            $name = sanitize_text_field((string)($row['location_name'] ?? ('PDV #' . $locationId)));
            $isStoreEngine = isset($row['risk_score']) || isset($row['opportunity_score']) || isset($row['operational_health']);
            $rowConfidence = isset($row['confidence_score']) ? (float)$row['confidence_score'] : $confidenceScore;
            $out[] = [
                'recommendation_type'=>'priority_store',
                'source_type'=>$isStoreEngine ? 'store_engine' : 'priority_location',
                'source_key'=>$isStoreEngine ? 'store_priority' : 'execution_priority',
                'severity'=>sanitize_key((string)($row['severity'] ?? ($score >= 72 ? 'high' : 'medium'))),
                'location_id'=>$locationId,
                'title'=>'Priorizar loja: ' . $name,
                'detail'=>sanitize_text_field((string)($row['reason'] ?? 'A loja apresenta sinais de prioridade operacional.')),
                'rationale'=>$isStoreEngine
                    ? 'Store Opportunity & Risk Engine: prioridade ' . number_format_i18n($score,1) . '/100, risco ' . number_format_i18n((float)($row['risk_score'] ?? 0),1) . '/100, oportunidade ' . number_format_i18n((float)($row['opportunity_score'] ?? 0),1) . '/100 e health ' . number_format_i18n((float)($row['operational_health'] ?? 0),1) . '/100.'
                    : 'Priority Store Score ' . number_format_i18n($score, 1) . '/100, calculado por execução e visitas pendentes.',
                'suggested_action'=>$isStoreEngine ? sanitize_text_field((string)($row['suggested_action'] ?? 'Rever os sinais desta loja antes de definir a próxima ação operacional.')) : 'Rever visitas pendentes, briefing e evidências desta loja antes de definir a próxima ação operacional.',
                'metric_key'=>$isStoreEngine ? 'priority_score' : 'done_rate',
                'current_value'=>self::scalarString($isStoreEngine ? $score : ($row['done_rate'] ?? null)),
                'threshold_value'=>$isStoreEngine ? '48' : '85',
                'desired_direction'=>$isStoreEngine ? 'down' : 'up',
                'confidence_score'=>$rowConfidence,
                'snapshot'=>[
                    'source'=>$isStoreEngine ? 'store_opportunity_risk' : 'priority_location','location'=>$row,
                    'decision_scores'=>self::compactScores($scores),
                    'period'=>['from'=>(string)($stats['from'] ?? ''),'to'=>(string)($stats['to'] ?? '')],
                ],
            ];
        }

        $dedup = [];
        foreach ($out as $candidate) {
            $key = implode('|', [
                (string)($candidate['recommendation_type'] ?? ''),
                (string)($candidate['source_key'] ?? ''),
                (string)absint($candidate['location_id'] ?? 0),
                (string)($candidate['metric_key'] ?? ''),
            ]);
            if (!isset($dedup[$key]) || self::severityRank((string)$candidate['severity']) > self::severityRank((string)$dedup[$key]['severity'])) $dedup[$key] = $candidate;
        }
        $out = array_values($dedup);
        usort($out, static function(array $a,array $b): int {
            $sev = self::severityRank((string)($b['severity'] ?? 'medium')) <=> self::severityRank((string)($a['severity'] ?? 'medium'));
            if ($sev !== 0) return $sev;
            return (float)($b['confidence_score'] ?? 0) <=> (float)($a['confidence_score'] ?? 0);
        });
        return $out;
    }

    private static function upsertCandidate(array $candidate, array $context): array {
        global $wpdb;
        $table = self::table();
        $scope = [
            'client_id'=>absint($context['client_id'] ?? 0),
            'project_id'=>absint($context['project_id'] ?? 0),
            'route_id'=>absint($context['route_id'] ?? 0),
            'user_id'=>absint($context['user_id'] ?? 0),
            'location_id'=>absint($candidate['location_id'] ?? $context['location_id'] ?? 0),
        ];
        $fingerprint = hash('sha256', wp_json_encode([
            $scope['client_id'],$scope['project_id'],$scope['route_id'],$scope['user_id'],$scope['location_id'],
            sanitize_key((string)($candidate['recommendation_type'] ?? 'signal')),
            sanitize_key((string)($candidate['source_key'] ?? '')),
            sanitize_key((string)($candidate['metric_key'] ?? '')),
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE fingerprint=%s LIMIT 1", $fingerprint), ARRAY_A);
        $now = current_time('mysql');
        $latestJson = wp_json_encode((array)($candidate['snapshot'] ?? []), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $currentValue = self::scalarString($candidate['current_value'] ?? null);
        if (!$existing) {
            $insert = [
                'recommendation_uuid'=>wp_generate_uuid4(),
                'fingerprint'=>$fingerprint,
                'client_id'=>$scope['client_id'],
                'project_id'=>$scope['project_id'],
                'route_id'=>$scope['route_id'],
                'user_id'=>$scope['user_id'],
                'location_id'=>$scope['location_id'],
                'recommendation_type'=>sanitize_key((string)($candidate['recommendation_type'] ?? 'signal')),
                'source_type'=>sanitize_key((string)($candidate['source_type'] ?? 'analytics')),
                'source_key'=>sanitize_key((string)($candidate['source_key'] ?? '')),
                'severity'=>sanitize_key((string)($candidate['severity'] ?? 'medium')),
                'status'=>'active',
                'title'=>sanitize_text_field((string)($candidate['title'] ?? 'Recomendação FieldFlow')),
                'detail'=>sanitize_text_field((string)($candidate['detail'] ?? '')),
                'rationale'=>sanitize_text_field((string)($candidate['rationale'] ?? '')),
                'suggested_action'=>sanitize_text_field((string)($candidate['suggested_action'] ?? 'Rever os dados antes de atuar.')),
                'metric_key'=>sanitize_key((string)($candidate['metric_key'] ?? '')),
                'baseline_value'=>$currentValue,
                'latest_value'=>$currentValue,
                'threshold_value'=>self::scalarString($candidate['threshold_value'] ?? null),
                'desired_direction'=>sanitize_key((string)($candidate['desired_direction'] ?? 'observe')),
                'confidence_score'=>round((float)($candidate['confidence_score'] ?? 0), 1),
                'baseline_json'=>$latestJson,
                'latest_json'=>$latestJson,
                'outcome_status'=>'pending',
                'occurrence_count'=>1,
                'feedback_count'=>0,
                'first_seen_at'=>$now,
                'last_seen_at'=>$now,
                'created_at'=>$now,
                'updated_at'=>$now,
            ];
            $ok = $wpdb->insert($table, $insert);
            if (!$ok) throw new \RuntimeException($wpdb->last_error ?: 'Falha ao criar recomendação.');
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", (int)$wpdb->insert_id), ARRAY_A) ?: $insert;
            $id = (int)($row['id'] ?? $wpdb->insert_id);
            self::recordEvent($id, 'recommendation.created', self::eventPayload($row), $row);
            $eventContext = array_merge(self::rowContext($row), ['entity_type'=>'intelligence_recommendation','entity_id'=>$id]);
            if (class_exists(EventBus::class)) EventBus::emit('intelligence.recommendation.created', self::eventPayload($row), $eventContext, 'analytics');
            return $row;
        }

        $lastSeenDate = substr((string)($existing['last_seen_at'] ?? ''), 0, 10);
        $today = substr($now, 0, 10);
        $valueChanged = !hash_equals((string)($existing['latest_value'] ?? ''), $currentValue);
        $occurrence = (int)($existing['occurrence_count'] ?? 0) + (($lastSeenDate !== $today || $valueChanged) ? 1 : 0);
        $outcome = self::observeOutcome($existing, $currentValue);
        $update = [
            'severity'=>sanitize_key((string)($candidate['severity'] ?? 'medium')),
            'title'=>sanitize_text_field((string)($candidate['title'] ?? 'Recomendação FieldFlow')),
            'detail'=>sanitize_text_field((string)($candidate['detail'] ?? '')),
            'rationale'=>sanitize_text_field((string)($candidate['rationale'] ?? '')),
            'suggested_action'=>sanitize_text_field((string)($candidate['suggested_action'] ?? '')),
            'latest_value'=>$currentValue,
            'threshold_value'=>self::scalarString($candidate['threshold_value'] ?? null),
            'confidence_score'=>round((float)($candidate['confidence_score'] ?? 0),1),
            'latest_json'=>$latestJson,
            'occurrence_count'=>$occurrence,
            'last_seen_at'=>$now,
            'updated_at'=>$now,
        ];
        if ($outcome['status'] !== '') {
            $update['outcome_status'] = $outcome['status'];
            $update['outcome_score'] = $outcome['score'];
            $update['outcome_note'] = $outcome['note'];
            $update['outcome_json'] = wp_json_encode($outcome, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        $ok = $wpdb->update($table, $update, ['id'=>(int)$existing['id']]);
        if ($ok === false) throw new \RuntimeException($wpdb->last_error ?: 'Falha ao atualizar recomendação.');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", (int)$existing['id']), ARRAY_A) ?: array_merge($existing, $update);
        if ($valueChanged || $lastSeenDate !== $today) {
            self::recordEvent((int)$existing['id'], 'recommendation.observed', self::eventPayload($row, ['outcome'=>$outcome]), $row);
            if ($outcome['status'] !== '' && class_exists(EventBus::class)) {
                EventBus::emit('intelligence.outcome.observed', self::eventPayload($row, ['outcome'=>$outcome]), array_merge(self::rowContext($row), ['entity_type'=>'intelligence_recommendation','entity_id'=>(int)$existing['id']]), 'analytics');
            }
        }
        return $row;
    }

    private static function observeOutcome(array $existing, string $latestValue): array {
        $status = sanitize_key((string)($existing['status'] ?? 'active'));
        if (!in_array($status, ['accepted','deferred','resolved'], true)) return ['status'=>'','score'=>null,'note'=>''];
        if ($status === 'resolved') return ['status'=>'human_resolved','score'=>null,'note'=>'Resolução confirmada por revisão humana.'];
        $baseline = self::numeric($existing['baseline_value'] ?? null);
        $latest = self::numeric($latestValue);
        if ($baseline === null || $latest === null) return ['status'=>'not_observed','score'=>null,'note'=>'O indicador ainda não permite comparação numérica.'];
        $direction = sanitize_key((string)($existing['desired_direction'] ?? 'observe'));
        $delta = $latest - $baseline;
        if ($direction === 'down') $delta = $baseline - $latest;
        if (!in_array($direction, ['up','down'], true)) return ['status'=>'not_observed','score'=>null,'note'=>'A recomendação requer observação qualitativa.'];
        $statusOut = abs($delta) < 1 ? 'stable' : ($delta > 0 ? 'improving' : 'worsening');
        return [
            'status'=>$statusOut,
            'score'=>round(max(-100, min(100, $delta)), 1),
            'note'=>$statusOut === 'improving' ? 'O indicador evoluiu na direção recomendada.' : ($statusOut === 'worsening' ? 'O indicador evoluiu contra a direção recomendada.' : 'Ainda não existe variação material.'),
            'baseline'=>$baseline,
            'latest'=>$latest,
            'delta'=>round($delta,1),
            'direction'=>$direction,
        ];
    }

    private static function prescription(string $type, array $actionMap): array {
        if (in_array($type, ['execution_low','execution_watch','execution_drop','execution_drop_watch','pending_stops','routes_without_progress'], true)) {
            return ['direction'=>in_array($type,['pending_stops','routes_without_progress'],true)?'down':'up','action'=>'Rever rotas e visitas pendentes, validar causas no detalhe da operação e priorizar os PDVs com maior gap.'];
        }
        if (in_array($type, ['share_space_below_target','fronts_decline'], true)) {
            return ['direction'=>$type === 'share_space_below_target' ? 'up' : 'down','action'=>'Rever referências e lojas abaixo do objetivo, confirmar evidências e preparar ação de recuperação de visibilidade.'];
        }
        if (in_array($type, ['stock_negative','stock_breaks','stock_low'], true)) {
            return ['direction'=>'down','action'=>'Validar o snapshot de stock, confirmar ruturas e priorizar reposição ou correção de dados quando aplicável.'];
        }
        if (in_array($type, ['visit_duration_high','visit_duration_low','on_time_low'], true)) {
            return ['direction'=>$type === 'on_time_low' ? 'up' : 'observe','action'=>'Validar check-in, checkout, janela horária e evidência da visita antes de concluir a causa.'];
        }
        return ['direction'=>'observe','action'=>'Rever o detalhe do sinal e confirmar a ação com contexto operacional antes de alterar dados ou planeamento.'];
    }

    private static function recordEvent(int $recommendationId, string $eventType, array $payload, array $row): void {
        global $wpdb;
        if (!self::tablesReady() || $recommendationId <= 0) return;
        $wpdb->insert(self::eventsTable(), [
            'event_uuid'=>wp_generate_uuid4(),
            'recommendation_id'=>$recommendationId,
            'event_type'=>substr(sanitize_key(str_replace('.', '_', $eventType)), 0, 64),
            'actor_user_id'=>get_current_user_id() ?: 0,
            'client_id'=>absint($row['client_id'] ?? 0),
            'project_id'=>absint($row['project_id'] ?? 0),
            'route_id'=>absint($row['route_id'] ?? 0),
            'user_id'=>absint($row['user_id'] ?? 0),
            'location_id'=>absint($row['location_id'] ?? 0),
            'payload_json'=>wp_json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'created_at'=>current_time('mysql'),
        ]);
    }

    private static function eventPayload(array $row, array $extra = []): array {
        return array_merge([
            'contract'=>'fieldflow.intelligence.learning',
            'contract_version'=>self::VERSION,
            'recommendation'=>self::publicRow($row),
            'policy'=>self::policy(),
        ], $extra);
    }

    private static function publicRow(array $row): array {
        return [
            'id'=>absint($row['id'] ?? 0),
            'uuid'=>sanitize_text_field((string)($row['recommendation_uuid'] ?? '')),
            'client_id'=>absint($row['client_id'] ?? 0),
            'project_id'=>absint($row['project_id'] ?? 0),
            'route_id'=>absint($row['route_id'] ?? 0),
            'user_id'=>absint($row['user_id'] ?? 0),
            'location_id'=>absint($row['location_id'] ?? 0),
            'type'=>sanitize_key((string)($row['recommendation_type'] ?? 'signal')),
            'source_type'=>sanitize_key((string)($row['source_type'] ?? 'analytics')),
            'source_key'=>sanitize_key((string)($row['source_key'] ?? '')),
            'severity'=>sanitize_key((string)($row['severity'] ?? 'medium')),
            'status'=>sanitize_key((string)($row['status'] ?? 'active')),
            'title'=>sanitize_text_field((string)($row['title'] ?? 'Recomendação FieldFlow')),
            'detail'=>sanitize_text_field((string)($row['detail'] ?? '')),
            'rationale'=>sanitize_text_field((string)($row['rationale'] ?? '')),
            'suggested_action'=>sanitize_text_field((string)($row['suggested_action'] ?? '')),
            'metric_key'=>sanitize_key((string)($row['metric_key'] ?? '')),
            'baseline_value'=>(string)($row['baseline_value'] ?? ''),
            'latest_value'=>(string)($row['latest_value'] ?? ''),
            'threshold_value'=>(string)($row['threshold_value'] ?? ''),
            'desired_direction'=>sanitize_key((string)($row['desired_direction'] ?? 'observe')),
            'confidence_score'=>round((float)($row['confidence_score'] ?? 0),1),
            'feedback_label'=>sanitize_key((string)($row['feedback_label'] ?? '')),
            'feedback_note'=>sanitize_text_field((string)($row['feedback_note'] ?? '')),
            'feedback_by'=>absint($row['feedback_by'] ?? 0),
            'feedback_at'=>(string)($row['feedback_at'] ?? ''),
            'outcome_status'=>sanitize_key((string)($row['outcome_status'] ?? 'pending')),
            'outcome_score'=>is_numeric($row['outcome_score'] ?? null) ? round((float)$row['outcome_score'],1) : null,
            'outcome_note'=>sanitize_text_field((string)($row['outcome_note'] ?? '')),
            'occurrence_count'=>(int)($row['occurrence_count'] ?? 0),
            'feedback_count'=>(int)($row['feedback_count'] ?? 0),
            'first_seen_at'=>(string)($row['first_seen_at'] ?? ''),
            'last_seen_at'=>(string)($row['last_seen_at'] ?? ''),
            'resolved_at'=>(string)($row['resolved_at'] ?? ''),
        ];
    }

    private static function scopeFilters(array $context): array {
        $out = [];
        foreach (['client_id','project_id','route_id','user_id','location_id'] as $key) {
            $value = absint($context[$key] ?? 0);
            if ($value > 0) $out[$key] = $value;
        }
        return $out;
    }

    private static function rowContext(array $row): array {
        return [
            'client_id'=>absint($row['client_id'] ?? 0),
            'project_id'=>absint($row['project_id'] ?? 0),
            'route_id'=>absint($row['route_id'] ?? 0),
            'actor_user_id'=>get_current_user_id() ?: 0,
            'location_id'=>absint($row['location_id'] ?? 0),
            'source'=>'intelligence_learning',
        ];
    }

    private static function compactScores(array $scores): array {
        $out = [];
        foreach (['operational_health','execution','risk','opportunity','stock_risk','confidence'] as $key) {
            if (!isset($scores[$key]) || !is_array($scores[$key])) continue;
            $out[$key] = ['score'=>$scores[$key]['score'] ?? null,'label'=>$scores[$key]['label'] ?? ''];
        }
        return $out;
    }

    private static function scalarString($value): string {
        if ($value === null || is_array($value) || is_object($value)) return '';
        if (is_bool($value)) return $value ? '1' : '0';
        return substr(sanitize_text_field((string)$value), 0, 96);
    }

    private static function numeric($value): ?float {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (float)$value;
        $normalized = str_replace(',', '.', preg_replace('/[^0-9,.-]+/', '', (string)$value) ?: '');
        return is_numeric($normalized) ? (float)$normalized : null;
    }

    private static function severityRank(string $severity): int {
        return ['low'=>1,'medium'=>2,'high'=>3,'critical'=>4][sanitize_key($severity)] ?? 2;
    }

    private static function emptySummary(): array {
        return [
            'total'=>0,'open'=>0,'active'=>0,'accepted'=>0,'deferred'=>0,'resolved'=>0,'not_relevant'=>0,
            'outcomes_observed'=>0,'outcome_coverage'=>0.0,'feedback_total'=>0,'feedback_coverage'=>0.0,
        ];
    }

    private static function policy(): array {
        return [
            'self_learning_enabled'=>false,
            'autonomous_mutations'=>false,
            'human_feedback_required'=>true,
            'causes_inferred'=>false,
            'dataset_auditable'=>true,
            'azure_event_ready'=>true,
        ];
    }
}
