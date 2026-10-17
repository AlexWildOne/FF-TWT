<?php
namespace RoutesPro\Analytics;

use RoutesPro\Core\AuditLog;
use RoutesPro\Core\EventBus;
use RoutesPro\Support\Logger;

if (!defined('ABSPATH')) exit;

/**
 * Deterministic operational intelligence for analytics.
 *
 * The service never invents causes. It analyses only aggregated values already
 * returned by the FieldFlow analytics contract and adds a traceable executive
 * layer: temporal comparison, anomaly detection, source provenance, confidence
 * and safe actions that do not mutate operational data.
 */
class OperationalIntelligenceService {
    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_analytics_alerts';
    }

    public static function registerHooks(): void {
        add_action('admin_post_fieldflow_analytics_alert_ack', [self::class, 'handleAcknowledge']);
        add_action('admin_post_fieldflow_analytics_alert_close', [self::class, 'handleClose']);
    }

    public static function analyse(array $stats, array $context = [], array $briefing = [], array $stocks = [], bool $persist = true, bool $includeStoreIntelligence = true): array {
        // 2.2.467: operational scoring evaluates closed past work only when the
        // selected window reaches today or the future. The raw Analytics payload
        // remains unchanged and the full plan stays available in temporal_context.
        if (class_exists('\RoutesPro\Analytics\TemporalAnalyticsService')) {
            $stats = TemporalAnalyticsService::forEvaluation($stats);
        }
        $comparison = self::temporalComparison($stats, $context);
        $anomalies = self::detectAnomalies($stats, $comparison, $stocks);
        $sources = self::sources($stats, $briefing, $stocks, $comparison);
        $confidence = self::confidence($stats, $sources);
        $executive = self::executiveSummary($stats, $comparison, $anomalies);
        $scores = class_exists('\RoutesPro\Analytics\DecisionScoreService')
            ? DecisionScoreService::calculate($stats, $anomalies, $stocks, $confidence, $comparison)
            : [];
        $storeIntelligence = [];
        if ($includeStoreIntelligence && class_exists('\RoutesPro\Analytics\StoreOpportunityRiskService')) {
            try {
                $storeIntelligence = StoreOpportunityRiskService::build($stats, $context, $confidence, $stocks);
            } catch (\Throwable $e) {
                Logger::warning('Store Intelligence indisponível; aplicado fallback de prioridade.', ['context_key'=>'analytics_store_engine'], [
                    'error'=>$e->getMessage(),
                    'client_id'=>absint($context['client_id'] ?? 0),
                    'project_id'=>absint($context['project_id'] ?? 0),
                    'route_id'=>absint($context['route_id'] ?? 0),
                    'location_id'=>absint($context['location_id'] ?? 0),
                ]);
                $storeIntelligence = [];
            }
        }
        $priorityLocations = !empty($storeIntelligence['available'])
            ? array_slice((array)($storeIntelligence['rankings']['priority'] ?? []), 0, 12)
            : (class_exists('\RoutesPro\Analytics\DecisionScoreService') ? DecisionScoreService::priorityLocations($stats, 12) : []);
        $actions = self::controlledActions($anomalies, $context, $stats);

        if ($persist) self::persistAnomalies($anomalies, $context, $stats);

        $learning = [];
        if (class_exists('\RoutesPro\Analytics\RecommendationLearningService')) {
            $learning = $persist
                ? RecommendationLearningService::capture($stats, $context, $anomalies, $scores, $priorityLocations, $actions, $confidence)
                : RecommendationLearningService::forScope($context, 8);
        }

        // Adaptive Intelligence is deliberately advisory. To protect the
        // Analytics critical path restored in 2.2.440, historical aggregation
        // runs only on persisted insight/report requests. Read-only widget
        // orchestration continues on the deterministic path.
        $recommendations = array_values((array)($learning['recommendations'] ?? []));
        $adaptive = [];
        if ($persist && class_exists('\RoutesPro\Analytics\AdaptiveIntelligenceService')) {
            try {
                $adaptive = AdaptiveIntelligenceService::analyse($context, $recommendations, 16);
                if (!empty($adaptive['available']) && is_array($adaptive['recommendations'] ?? null)) {
                    $recommendations = array_values($adaptive['recommendations']);
                }
                if (!empty($storeIntelligence['available'])) {
                    $storeIntelligence = AdaptiveIntelligenceService::enrichStoreIntelligence($storeIntelligence, $context);
                    if (!empty($storeIntelligence['rankings']['priority'])) {
                        $priorityLocations = array_slice((array)$storeIntelligence['rankings']['priority'], 0, 12);
                    }
                }
            } catch (\Throwable $e) {
                Logger::warning('Adaptive Intelligence indisponível; recomendações mantêm a ordem determinística.', ['context_key'=>'adaptive_intelligence'], ['error'=>$e->getMessage()]);
                $adaptive = [];
            }
        }

        // 2.2.444 Predictive Intelligence is a read-only, deterministic forward
        // projection. It never blocks the current Analytics response and never
        // mutates operational scores or data.
        $predictive = [];
        if (class_exists('\RoutesPro\Analytics\PredictiveIntelligenceService')) {
            try {
                $predictive = PredictiveIntelligenceService::analyse($stats, $context, $scores, $comparison, $anomalies, $adaptive);
                if (!empty($storeIntelligence['available'])) {
                    $storeIntelligence = PredictiveIntelligenceService::enrichStoreIntelligence($storeIntelligence, $context);
                }
            } catch (\Throwable $e) {
                Logger::warning('Predictive Intelligence indisponível; Analytics atual mantém-se operacional.', ['context_key'=>'predictive_intelligence'], ['error'=>$e->getMessage()]);
                $predictive = [];
            }
        }

        // 2.2.446 Contextual Predictive Intelligence converts already calculated
        // store/product/problem signals into explainable Next Best Actions. It is
        // fail-open, read-only and performs no database fan-out.
        $contextualPredictive = [];
        if (!empty($storeIntelligence['available']) && class_exists('\RoutesPro\Analytics\ContextualPredictiveIntelligenceService')) {
            try {
                $storeIntelligence = ContextualPredictiveIntelligenceService::enrichStoreIntelligence($storeIntelligence, $context, $recommendations);
                $contextualPredictive = ContextualPredictiveIntelligenceService::portfolio($storeIntelligence, 10);
            } catch (\Throwable $e) {
                Logger::warning('Contextual Predictive Intelligence indisponível; Store Intelligence mantém-se operacional.', ['context_key'=>'contextual_predictive_intelligence'], ['error'=>$e->getMessage()]);
                $contextualPredictive = [];
            }
        }

        return [
            'executive' => $executive,
            'comparison' => $comparison,
            'anomalies' => $anomalies,
            'sources' => $sources,
            'confidence' => $confidence,
            'decision_scores' => $scores,
            'priority_locations' => $priorityLocations,
            'store_intelligence' => $storeIntelligence,
            'controlled_actions' => $actions,
            'recommendations' => $recommendations,
            'learning' => is_array($learning) ? $learning : [],
            'adaptive_intelligence' => is_array($adaptive) ? $adaptive : [],
            'predictive_intelligence' => is_array($predictive) ? $predictive : [],
            'contextual_predictive_intelligence' => is_array($contextualPredictive) ? $contextualPredictive : [],
            'policy' => [
                'causes_inferred' => false,
                'mutations_allowed' => false,
                'latest_active_submissions_only' => true,
                'human_review_required' => true,
                'learning_dataset_enabled' => !empty($learning['available']),
                'self_learning_enabled' => false,
                'store_engine_requested' => $includeStoreIntelligence,
                'store_engine_enabled' => !empty($storeIntelligence['available']),
                'adaptive_intelligence_enabled' => !empty($adaptive['available']),
                'adaptive_store_scoring_enabled' => false,
                'adaptive_store_ranking_enabled' => !empty($storeIntelligence['policy']['adaptive_intelligence_enabled']),
                'adaptive_changes_operational_scores' => false,
                'adaptive_changes_recommendation_ranking' => !empty($adaptive['available']),
                'predictive_intelligence_enabled' => !empty($predictive['available']),
                'predictive_is_probability_model' => false,
                'predictive_changes_operational_scores' => false,
                'contextual_predictive_enabled' => !empty($contextualPredictive['available']),
                'contextual_predictive_is_probability_model' => false,
                'contextual_predictive_changes_operational_scores' => false,
                'learning_influences_store_confidence_only' => false,
                'learning_influences_store_confidence_and_assisted_ranking' => !empty($adaptive['available']),
            ],
        ];
    }

    public static function recentAlerts(int $limit = 20, array $filters = []): array {
        global $wpdb;
        $table = self::table();
        if (!self::tableReady()) return [];
        $where = ['1=1'];
        $args = [];
        if (!empty($filters['status'])) { $where[] = 'status=%s'; $args[] = sanitize_key((string)$filters['status']); }
        if (!empty($filters['client_id'])) { $where[] = 'client_id=%d'; $args[] = absint($filters['client_id']); }
        if (!empty($filters['project_id'])) { $where[] = 'project_id=%d'; $args[] = absint($filters['project_id']); }
        $limit = max(1, min(200, $limit));
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(severity,'critical','high','medium','low'), last_seen_at DESC, id DESC LIMIT {$limit}";
        return $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
    }

    public static function alertCounts(): array {
        global $wpdb;
        $table = self::table();
        $out = ['open'=>0,'critical'=>0,'high'=>0,'medium'=>0,'acknowledged'=>0,'closed'=>0];
        if (!self::tableReady()) return $out;
        $rows = $wpdb->get_results("SELECT status,severity,COUNT(*) total FROM {$table} GROUP BY status,severity", ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $status = sanitize_key((string)($row['status'] ?? ''));
            $severity = sanitize_key((string)($row['severity'] ?? ''));
            $total = (int)($row['total'] ?? 0);
            if (isset($out[$status])) $out[$status] += $total;
            if ($status === 'open' && isset($out[$severity])) $out[$severity] += $total;
        }
        return $out;
    }

    public static function tableReady(): bool {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function handleAcknowledge(): void {
        self::changeAlertStatus('acknowledged');
    }

    public static function handleClose(): void {
        self::changeAlertStatus('closed');
    }

    private static function changeAlertStatus(string $status): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_analytics_alert_status');
        $id = absint($_POST['alert_id'] ?? 0);
        if (!$id || !self::tableReady()) { wp_safe_redirect(admin_url('admin.php?page=routespro-system-health')); exit; }
        global $wpdb;
        $table = self::table();
        $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);
        if ($before) {
            $wpdb->update($table, [
                'status' => $status,
                'acknowledged_by' => get_current_user_id(),
                'acknowledged_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ], ['id' => $id], ['%s','%d','%s','%s'], ['%d']);
            $after = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);
            if (class_exists(AuditLog::class)) AuditLog::record('analytics.alert_' . $status, 'analytics_alert', $id, $before, $after, ['source'=>'admin']);
            if (class_exists(EventBus::class)) EventBus::emit('analytics.alert.' . $status, ['alert_id'=>$id,'status'=>$status], ['entity_type'=>'analytics_alert','entity_id'=>$id], 'admin');
        }
        wp_safe_redirect(add_query_arg(['page'=>'routespro-system-health','ff_alert_status'=>$status], admin_url('admin.php')));
        exit;
    }

    private static function temporalComparison(array $stats, array $context = []): array {
        $previous = is_array($stats['previous_summary'] ?? null) ? $stats['previous_summary'] : [];
        $routeSpecific = absint($context['route_id'] ?? 0) > 0;
        $definitions = [
            'total_routes' => ['label'=>'Rotas','format'=>'number'],
            'completed_routes' => ['label'=>'Rotas concluídas','format'=>'number'],
            'total_stops' => ['label'=>'Paragens','format'=>'number'],
            'done_stops' => ['label'=>'Paragens concluídas','format'=>'number'],
            'done_rate' => ['label'=>'Taxa de execução','format'=>'percent'],
            'completion_rate' => ['label'=>'Taxa de rotas concluídas','format'=>'percent'],
        ];
        $metrics = [];
        foreach ($definitions as $key => $meta) {
            $current = (float)($stats[$key] ?? 0);
            $prev = (float)($previous[$key] ?? 0);
            $delta = round($current - $prev, 1);
            $relative = abs($prev) > 0.0001 ? round(($delta / abs($prev)) * 100, 1) : null;
            $metrics[] = [
                'key'=>$key,
                'label'=>$meta['label'],
                'format'=>$meta['format'],
                'current'=>$current,
                'previous'=>$prev,
                'delta'=>$delta,
                'relative_delta'=>$relative,
                'direction'=>$delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'stable'),
            ];
        }
        $openOperationalWindow = !empty($stats['temporal_context']['open_window']);
        if ($routeSpecific || $openOperationalWindow) {
            return [
                'available'=>false,
                'reason'=>$routeSpecific ? 'route_specific_scope' : 'open_operational_window',
                'current_period'=>['from'=>(string)($stats['from'] ?? ''),'to'=>(string)($stats['to'] ?? '')],
                'previous_period'=>is_array($stats['previous_period'] ?? null) ? $stats['previous_period'] : [],
                'metrics'=>[],
                'execution_delta_pp'=>0.0,
            ];
        }
        return [
            'available'=>true,
            'reason'=>'equal_window',
            'current_period'=>['from'=>(string)($stats['from'] ?? ''),'to'=>(string)($stats['to'] ?? '')],
            'previous_period'=>is_array($stats['previous_period'] ?? null) ? $stats['previous_period'] : [],
            'metrics'=>$metrics,
            'execution_delta_pp'=>round((float)($stats['done_rate'] ?? 0) - (float)($previous['done_rate'] ?? 0), 1),
        ];
    }

    private static function detectAnomalies(array $stats, array $comparison, array $stocks): array {
        $out = [];
        $totalStops = (int)($stats['total_stops'] ?? 0);
        $doneStops = (int)($stats['done_stops'] ?? 0);
        $doneRate = (float)($stats['done_rate'] ?? 0);
        $pending = max(0, $totalStops - $doneStops);
        $delta = (float)($comparison['execution_delta_pp'] ?? 0);

        if ($totalStops > 0 && $doneRate < 70) self::addAnomaly($out, 'execution_low', 'high', 'Execução abaixo do esperado', 'A taxa de conclusão está em ' . self::fmt($doneRate) . '%, abaixo do limiar operacional de 70%.', 'done_rate', $doneRate, 70, ['pending'=>$pending]);
        elseif ($totalStops > 0 && $doneRate < 85) self::addAnomaly($out, 'execution_watch', 'medium', 'Execução requer acompanhamento', 'A taxa de conclusão está em ' . self::fmt($doneRate) . '%. Existem ' . $pending . ' paragens por concluir.', 'done_rate', $doneRate, 85, ['pending'=>$pending]);
        if ($delta <= -10) self::addAnomaly($out, 'execution_drop', 'high', 'Quebra relevante face ao período anterior', 'A execução recuou ' . self::fmt(abs($delta)) . ' pontos percentuais.', 'done_rate_delta', $delta, -10);
        elseif ($delta <= -5) self::addAnomaly($out, 'execution_drop_watch', 'medium', 'Execução em queda', 'A execução recuou ' . self::fmt(abs($delta)) . ' pontos percentuais face ao período anterior.', 'done_rate_delta', $delta, -5);
        if ($pending > max(3, (int)ceil($totalStops * .25))) self::addAnomaly($out, 'pending_stops', $pending > max(8, (int)ceil($totalStops * .4)) ? 'high' : 'medium', 'Volume elevado de visitas pendentes', $pending . ' de ' . $totalStops . ' paragens continuam por concluir.', 'pending_stops', $pending, max(3, (int)ceil($totalStops * .25)));

        $routeRows = array_values(array_filter((array)($stats['by_day'] ?? []), 'is_array'));
        $weakRoutes = array_values(array_filter($routeRows, static function($row){
            return (int)($row['stops'] ?? 0) > 0 && (float)($row['done_rate'] ?? 0) <= 30 && !in_array(sanitize_key((string)($row['route_status'] ?? '')), ['completed','done','cancelled'], true);
        }));
        if ($weakRoutes) self::addAnomaly($out, 'routes_without_progress', count($weakRoutes) >= 3 ? 'high' : 'medium', 'Rotas com progresso muito reduzido', count($weakRoutes) . ' rota(s) apresentam execução igual ou inferior a 30%.', 'route_count', count($weakRoutes), 0, ['route_ids'=>array_values(array_map('intval', array_column(array_slice($weakRoutes,0,12),'route_id')))]);

        // 2.2.423: os alertas acima são só agregados — nunca dizem QUAL loja.
        // Esta deteção adicional identifica as lojas concretas com execução
        // baixa, para o alerta (e a IA, que recebe este mesmo objeto) poder
        // nomeá-las em vez de só falar em percentagens médias. Exige pelo
        // menos 2 paragens na loja no período, para não sinalizar uma única
        // visita ainda por fazer como se fosse um problema.
        $locationRows = array_values(array_filter((array)($stats['by_location'] ?? []), 'is_array'));
        $weakStores = array_values(array_filter($locationRows, static function($row){
            return (int)($row['stops'] ?? 0) >= 2 && (float)($row['done_rate'] ?? 0) <= 30;
        }));
        if ($weakStores) {
            $storeNames = array_values(array_map(static function($row){
                return sanitize_text_field((string)($row['location_name'] ?? ''));
            }, array_slice($weakStores, 0, 8)));
            $storeNames = array_values(array_filter($storeNames, static fn($n) => $n !== ''));
            $namesText = $storeNames ? implode(', ', $storeNames) : '';
            self::addAnomaly(
                $out,
                'stores_low_execution',
                count($weakStores) >= 3 ? 'high' : 'medium',
                'Lojas com execução muito reduzida',
                count($weakStores) . ' loja(s) apresentam execução igual ou inferior a 30%' . ($namesText !== '' ? ': ' . $namesText : '') . '.',
                'store_count',
                count($weakStores),
                0,
                [
                    'location_ids' => array_values(array_map('intval', array_column(array_slice($weakStores, 0, 12), 'location_id'))),
                    'location_names' => array_values(array_map(static function($row){
                        return sanitize_text_field((string)($row['location_name'] ?? ''));
                    }, array_slice($weakStores, 0, 12))),
                ]
            );
        }

        $avgStop = $stats['avg_stop_minutes'] ?? null;
        if (is_numeric($avgStop) && (float)$avgStop > 150) self::addAnomaly($out, 'visit_duration_high', 'medium', 'Duração média de visita muito elevada', 'A duração média registada é de ' . self::fmt((float)$avgStop) . ' minutos. Deve ser validada antes de concluir uma causa.', 'avg_stop_minutes', (float)$avgStop, 150);
        if (is_numeric($avgStop) && (float)$avgStop > 0 && (float)$avgStop < 4) self::addAnomaly($out, 'visit_duration_low', 'medium', 'Duração média de visita muito curta', 'A duração média registada é de ' . self::fmt((float)$avgStop) . ' minutos. Confirma check-in e checkout.', 'avg_stop_minutes', (float)$avgStop, 4);
        $onTime = $stats['on_time_rate'] ?? null;
        if (is_numeric($onTime) && (float)$onTime < 70) self::addAnomaly($out, 'on_time_low', 'medium', 'Pontualidade abaixo do esperado', 'A taxa dentro da janela horária está em ' . self::fmt((float)$onTime) . '%.', 'on_time_rate', (float)$onTime, 70);

        if (!empty($stocks['available'])) {
            $negative = (int)($stocks['negative_count'] ?? 0);
            $zero = (int)($stocks['zero_count'] ?? 0);
            $low = (int)($stocks['low_count'] ?? 0);
            if ($negative > 0) self::addAnomaly($out, 'stock_negative', 'high', 'Valores negativos no snapshot de stock', $negative . ' linha(s) apresentam stock negativo e requerem validação.', 'stock_negative', $negative, 0);
            if ($zero > 0) self::addAnomaly($out, 'stock_breaks', $zero >= 10 ? 'high' : 'medium', 'Ruturas no stock publicado', $zero . ' linha(s) estão a zero no último snapshot.', 'stock_zero', $zero, 0);
            elseif ($low >= 10) self::addAnomaly($out, 'stock_low', 'medium', 'Concentração de stock baixo', $low . ' linha(s) apresentam stock baixo.', 'stock_low', $low, 10);
        }

        foreach ((array)($stats['form_analytics']['metrics'] ?? []) as $metric) {
            if (!is_array($metric)) continue;
            $label = sanitize_text_field((string)($metric['metric_label'] ?? $metric['metric_key'] ?? 'Métrica'));
            $share = is_array($metric['share_space_summary'] ?? null) ? $metric['share_space_summary'] : [];
            if ($share) {
                $below = (int)($share['below_target'] ?? 0);
                $gap = (float)($share['avg_gap'] ?? 0);
                if ($below > 0) self::addAnomaly($out, 'share_space_below_target', $gap <= -5 ? 'high' : 'medium', 'Share Space abaixo do objetivo', $below . ' comparação(ões) de “' . $label . '” estão abaixo do objetivo, com desvio médio de ' . self::fmt($gap) . ' pp.', 'share_space_gap', $gap, 0, ['metric_label'=>$label,'below_target'=>$below]);
            }
            $fronts = is_array($metric['fronts_summary'] ?? null) ? $metric['fronts_summary'] : [];
            if ($fronts && (int)($fronts['down'] ?? 0) > (int)($fronts['up'] ?? 0)) {
                self::addAnomaly($out, 'fronts_decline', 'medium', 'Mais referências perderam frentes do que ganharam', 'Na métrica “' . $label . '”, ' . (int)($fronts['down'] ?? 0) . ' referências diminuíram e ' . (int)($fronts['up'] ?? 0) . ' cresceram.', 'fronts_down', (int)($fronts['down'] ?? 0), (int)($fronts['up'] ?? 0), ['metric_label'=>$label]);
            }
        }

        usort($out, static function($a,$b){
            $rank=['critical'=>4,'high'=>3,'medium'=>2,'low'=>1];
            return ($rank[$b['severity']] ?? 0) <=> ($rank[$a['severity']] ?? 0);
        });
        return array_slice($out, 0, 12);
    }

    private static function addAnomaly(array &$out, string $type, string $severity, string $title, string $detail, string $metric, $value, $threshold, array $meta = []): void {
        $out[] = [
            'type'=>sanitize_key($type),
            'severity'=>in_array($severity,['low','medium','high','critical'],true)?$severity:'medium',
            'title'=>sanitize_text_field($title),
            'detail'=>sanitize_text_field($detail),
            'metric'=>sanitize_key($metric),
            'value'=>is_numeric($value)?round((float)$value,2):sanitize_text_field((string)$value),
            'threshold'=>is_numeric($threshold)?round((float)$threshold,2):sanitize_text_field((string)$threshold),
            'meta'=>$meta,
        ];
    }

    private static function executiveSummary(array $stats, array $comparison, array $anomalies): array {
        $score = 100;
        foreach ($anomalies as $anomaly) {
            $score -= ['critical'=>28,'high'=>16,'medium'=>8,'low'=>3][$anomaly['severity']] ?? 5;
        }
        if ((int)($stats['total_stops'] ?? 0) === 0) $score = min($score, 45);
        $score = max(0, min(100, $score));
        $status = $score >= 85 ? 'healthy' : ($score >= 65 ? 'watch' : 'critical');
        $label = $status === 'healthy' ? 'Operação saudável' : ($status === 'watch' ? 'Operação sob acompanhamento' : 'Intervenção recomendada');
        $top = array_slice($anomalies, 0, 3);
        return [
            'score'=>$score,
            'status'=>$status,
            'label'=>$label,
            'done_rate'=>round((float)($stats['done_rate'] ?? 0),1),
            'execution_delta_pp'=>round((float)($comparison['execution_delta_pp'] ?? 0),1),
            'pending_stops'=>max(0,(int)($stats['total_stops'] ?? 0)-(int)($stats['done_stops'] ?? 0)),
            'open_anomalies'=>count($anomalies),
            'high_anomalies'=>count(array_filter($anomalies, static fn($a)=>in_array($a['severity'],['high','critical'],true))),
            'priority_titles'=>array_values(array_map(static fn($a)=>(string)$a['title'], $top)),
        ];
    }

    private static function sources(array $stats, array $briefing, array $stocks, array $comparison = []): array {
        $metricRecords = 0;
        $metrics = (array)($stats['form_analytics']['metrics'] ?? []);
        foreach ($metrics as $metric) if (is_array($metric)) $metricRecords += (int)($metric['record_count'] ?? 0);
        $comparisonAvailable = !array_key_exists('available', $comparison) || !empty($comparison['available']);
        $previousAvailable = $comparisonAvailable && !empty($stats['previous_summary']);
        $sources = [
            ['id'=>'routes','label'=>'Rotas e paragens','available'=>(int)($stats['total_routes'] ?? 0)>0,'records'=>(int)($stats['total_routes'] ?? 0),'detail'=>(int)($stats['total_stops'] ?? 0).' paragens'],
            ['id'=>'submissions','label'=>'Submissões ativas','available'=>$metricRecords>0,'records'=>$metricRecords,'detail'=>count($metrics).' métricas configuradas'],
            ['id'=>'previous_period','label'=>'Período anterior','available'=>$previousAvailable,'records'=>$previousAvailable?1:0,'detail'=>$comparisonAvailable?((string)($stats['previous_period']['from'] ?? '').' a '.(string)($stats['previous_period']['to'] ?? '')):'Não aplicável a uma rota individual'],
            ['id'=>'briefing','label'=>'Briefing publicado','available'=>!empty($briefing),'records'=>count((array)($briefing['ids'] ?? [])),'detail'=>sanitize_text_field((string)($briefing['primary_title'] ?? ''))],
            ['id'=>'stocks','label'=>'Último stock publicado','available'=>!empty($stocks['available']),'records'=>(int)($stocks['stores'] ?? 0),'detail'=>sanitize_text_field((string)($stocks['snapshot_date'] ?? ''))],
        ];
        return $sources;
    }

    private static function confidence(array $stats, array $sources): array {
        $score = 25;
        $stops = (int)($stats['total_stops'] ?? 0);
        $score += min(30, $stops * 2);
        foreach ($sources as $source) {
            if (!empty($source['available']) && ($source['id'] ?? '') === 'previous_period') $score += 15;
        }
        if (!empty($stats['form_analytics']['metrics'])) $score += 15;
        foreach ($sources as $source) if (!empty($source['available']) && in_array($source['id'],['briefing','stocks'],true)) $score += 5;
        $score = max(0,min(100,$score));
        $label = $score >= 80 ? 'high' : ($score >= 55 ? 'medium' : 'low');
        $available = count(array_filter($sources, static fn($s)=>!empty($s['available'])));
        return [
            'score'=>$score,
            'label'=>$label,
            'available_sources'=>$available,
            'total_sources'=>count($sources),
            'explanation'=>$label==='high'?'A leitura combina execução, comparação temporal e dados de reporte suficientes.':($label==='medium'?'A leitura é útil, mas algumas fontes ou amostras são limitadas.':'A amostra é reduzida. Interpreta as recomendações como orientação inicial.'),
        ];
    }

    private static function controlledActions(array $anomalies, array $context, array $stats): array {
        $actions = [];
        $baseParams = array_filter([
            'client_id'=>absint($context['client_id'] ?? 0),
            'project_id'=>absint($context['project_id'] ?? 0),
            'route_id'=>absint($context['route_id'] ?? 0),
            'user_id'=>absint($context['user_id'] ?? 0),
            'location_id'=>absint($context['location_id'] ?? 0),
            'from'=>sanitize_text_field((string)($stats['from'] ?? '')),
            'to'=>sanitize_text_field((string)($stats['to'] ?? '')),
        ], static fn($v)=>$v!==0&&$v!=='');
        $types = array_values(array_unique(array_column($anomalies,'type')));
        if (array_intersect($types,['execution_low','execution_watch','execution_drop','pending_stops','routes_without_progress'])) {
            $actions[] = self::action('review_routes','Rever rotas pendentes','Abrir o detalhe das rotas e validar paragens sem conclusão.','now','filter','routes',$baseParams);
        }
        if (array_intersect($types,['share_space_below_target','fronts_decline'])) {
            $actions[] = self::action('inspect_distribution','Analisar referências em risco','Filtrar tabelas de Share Space e frentes para identificar lojas e referências prioritárias.','now','filter','analytics',array_merge($baseParams,['focus'=>'distribution']));
        }
        if (array_intersect($types,['stock_negative','stock_breaks','stock_low'])) {
            $actions[] = self::action('review_stocks','Validar stocks publicados','Abrir Stocks e confirmar ruturas, valores negativos e data do snapshot.','next','navigate','stocks',$baseParams);
        }
        if (array_intersect($types,['visit_duration_high','visit_duration_low','on_time_low'])) {
            $actions[] = self::action('review_timing','Validar tempos e eventos','Comparar check-in, checkout e janelas horárias antes de corrigir dados.','next','filter','routes',array_merge($baseParams,['focus'=>'timing']));
        }
        $actions[] = self::action('export_evidence','Exportar relatório de suporte','Gerar Excel ou PDF com o mesmo âmbito para validação e partilha.','monitor','export','analytics',$baseParams);
        return array_slice($actions,0,5);
    }

    private static function action(string $id,string $title,string $detail,string $priority,string $kind,string $target,array $params): array {
        return ['id'=>$id,'title'=>$title,'detail'=>$detail,'priority'=>$priority,'kind'=>$kind,'target'=>$target,'params'=>$params,'requires_confirmation'=>false,'mutates_data'=>false];
    }

    private static function persistAnomalies(array $anomalies, array $context, array $stats): void {
        if (!self::tableReady() || !$anomalies) return;
        global $wpdb;
        $table = self::table();
        $now = current_time('mysql');
        $scope = [
            'client_id'=>absint($context['client_id'] ?? 0),
            'project_id'=>absint($context['project_id'] ?? 0),
            'user_id'=>absint($context['user_id'] ?? 0),
            'location_id'=>absint($context['location_id'] ?? 0),
            'from'=>sanitize_text_field((string)($stats['from'] ?? '')),
            'to'=>sanitize_text_field((string)($stats['to'] ?? '')),
        ];
        foreach ($anomalies as $anomaly) {
            if (!in_array($anomaly['severity'],['medium','high','critical'],true)) continue;
            $fingerprint = hash('sha256', wp_json_encode([$scope['client_id'],$scope['project_id'],$scope['user_id'],$scope['location_id'],$anomaly['type'],$anomaly['metric']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            $sql = "INSERT INTO {$table} (alert_uuid,fingerprint,client_id,project_id,user_id,location_id,alert_type,severity,status,title,detail,metric_key,current_value,threshold_value,period_from,period_to,payload_json,first_seen_at,last_seen_at,created_at,updated_at) VALUES (%s,%s,%d,%d,%d,%d,%s,%s,'open',%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE severity=VALUES(severity),status=IF(status='closed','open',status),title=VALUES(title),detail=VALUES(detail),current_value=VALUES(current_value),threshold_value=VALUES(threshold_value),period_from=VALUES(period_from),period_to=VALUES(period_to),payload_json=VALUES(payload_json),last_seen_at=VALUES(last_seen_at),updated_at=VALUES(updated_at)";
            $wpdb->query($wpdb->prepare($sql,
                wp_generate_uuid4(),$fingerprint,$scope['client_id'],$scope['project_id'],$scope['user_id'],$scope['location_id'],
                $anomaly['type'],$anomaly['severity'],$anomaly['title'],$anomaly['detail'],$anomaly['metric'],(string)$anomaly['value'],(string)$anomaly['threshold'],
                $scope['from'],$scope['to'],wp_json_encode($anomaly,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$now,$now,$now,$now
            ));
        }
    }

    private static function fmt(float $value): string {
        return number_format_i18n($value, 1);
    }
}
