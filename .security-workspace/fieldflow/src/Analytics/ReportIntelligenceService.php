<?php
namespace RoutesPro\Analytics;

use RoutesPro\Stocks\StockService;

if (!defined('ABSPATH')) exit;

/**
 * Single intelligence envelope shared by email, Excel, PDF and CSV reports.
 */
final class ReportIntelligenceService {
    public const VERSION = '1.6.0';

    public static function build(array $data, array $filters, array $context = []): array {
        $audience = sanitize_key((string)($filters['audience'] ?? 'client'));
        if (!in_array($audience, ['client','merchandiser'], true)) $audience = !empty($filters['self_mode']) ? 'merchandiser' : 'client';
        $intelligenceContext = [
            'audience'=>$audience,
            'client_id'=>absint($filters['client_id'] ?? 0),
            'project_id'=>absint($filters['project_id'] ?? 0),
            'route_id'=>absint($filters['route_id'] ?? 0),
            'user_id'=>absint($filters['user_id'] ?? 0),
            'location_id'=>absint($filters['location_id'] ?? 0),
            'from'=>(string)($filters['from'] ?? ''),
            'to'=>(string)($filters['to'] ?? ''),
        ];
        $stocks = [];
        $narrowStockScope = !empty($intelligenceContext['route_id']) || !empty($intelligenceContext['user_id']) || !empty($intelligenceContext['location_id']);
        try {
            if (class_exists(StockService::class)) {
                if ($narrowStockScope) {
                    $locationIds = array_values(array_unique(array_filter(array_map(static fn($row)=>absint($row['location_id'] ?? 0), (array)($data['by_location'] ?? [])))));
                    $stocks = StockService::analyticsScopedContext($intelligenceContext, $locationIds);
                } else {
                    $stocks = StockService::analyticsContext($intelligenceContext);
                }
            }
        } catch (\Throwable $e) {
            $stocks = [];
        }

        $intelligence = [];
        try {
            $intelligence = OperationalIntelligenceService::analyse($data, $intelligenceContext, [], $stocks);
        } catch (\Throwable $e) {
            $intelligence = [];
        }

        $fa = is_array($data['form_analytics'] ?? null) ? $data['form_analytics'] : [];
        $dashboards = array_values(array_filter((array)($fa['dashboards'] ?? []), 'is_array'));
        $metrics = array_values(array_filter((array)($fa['metrics'] ?? []), 'is_array'));
        $compact = WidgetOrchestrator::compactMetrics($metrics, $dashboards, $audience, 0, $intelligence);
        $storeAlerts = WidgetOrchestrator::storeAlerts($data, $intelligence, 10);

        $scores = is_array($intelligence['decision_scores'] ?? null) ? $intelligence['decision_scores'] : [];
        $scoreOrder = $audience === 'merchandiser'
            ? ['execution','risk','opportunity','confidence']
            : ['operational_health','execution','risk','opportunity','stock_risk','confidence'];
        $compactScores = [];
        foreach ($scoreOrder as $key) {
            if (!isset($scores[$key]) || !is_array($scores[$key])) continue;
            $compactScores[$key] = $scores[$key];
        }

        return [
            'version'=>self::VERSION,
            'audience'=>$audience,
            'period'=>[
                'from'=>(string)($filters['from'] ?? ''),
                'to'=>(string)($filters['to'] ?? ''),
                'label'=>(string)($context['period'] ?? ''),
            ],
            'scores'=>$compactScores,
            'summary_metrics'=>array_map([self::class, 'compactMetric'], $compact),
            'store_alerts'=>$storeAlerts,
            'recommendations'=>array_values(array_filter((array)($intelligence['recommendations'] ?? []), 'is_array')),
            'learning'=>is_array($intelligence['learning'] ?? null) ? $intelligence['learning'] : [],
            'adaptive_intelligence'=>is_array($intelligence['adaptive_intelligence'] ?? null) ? $intelligence['adaptive_intelligence'] : [],
            'predictive_intelligence'=>is_array($intelligence['predictive_intelligence'] ?? null) ? $intelligence['predictive_intelligence'] : [],
            'contextual_predictive_intelligence'=>is_array($intelligence['contextual_predictive_intelligence'] ?? null) ? $intelligence['contextual_predictive_intelligence'] : [],
            'store_intelligence'=>is_array($intelligence['store_intelligence'] ?? null) ? $intelligence['store_intelligence'] : [],
            'anomalies'=>array_values(array_filter((array)($intelligence['anomalies'] ?? []), 'is_array')),
            'confidence'=>is_array($intelligence['confidence'] ?? null) ? $intelligence['confidence'] : [],
            'stock_context'=>$stocks,
            'widget_orchestration'=>is_array($fa['orchestration'] ?? null) ? $fa['orchestration'] : [],
            'policy'=>[
                'same_source_for_email_excel_pdf_csv'=>true,
                'causes_inferred'=>false,
                'full_export_detail_preserved'=>true,
                'recommendations_are_advisory'=>true,
                'self_learning_enabled'=>false,
                'adaptive_intelligence_enabled'=>!empty($intelligence['adaptive_intelligence']['available']),
                'adaptive_changes_operational_scores'=>false,
                'predictive_intelligence_enabled'=>!empty($intelligence['predictive_intelligence']['available']),
                'predictive_is_probability_model'=>false,
                'predictive_changes_operational_scores'=>false,
                'contextual_predictive_enabled'=>!empty($intelligence['contextual_predictive_intelligence']['available']),
                'contextual_predictive_is_probability_model'=>false,
                'contextual_predictive_changes_operational_scores'=>false,
                'store_engine_enabled'=>!empty($intelligence['store_intelligence']['available']),
                'learning_influences_store_confidence_only'=>false,
                'learning_influences_confidence_and_assisted_ranking'=>!empty($intelligence['adaptive_intelligence']['available']),
            ],
        ];
    }

    private static function compactMetric(array $metric): array {
        return [
            'dashboard_id'=>(int)($metric['dashboard_id'] ?? 0),
            'dashboard_title'=>sanitize_text_field((string)($metric['dashboard_title'] ?? 'Analytics operacional')),
            'binding_id'=>(int)($metric['binding_id'] ?? 0),
            'metric_key'=>sanitize_key((string)($metric['metric_key'] ?? '')),
            'metric_label'=>sanitize_text_field((string)($metric['metric_label'] ?? 'Métrica')),
            'question_label'=>sanitize_text_field((string)($metric['question_label'] ?? '')),
            'topic'=>sanitize_key((string)($metric['orchestration_topic'] ?? 'general')),
            'value'=>$metric['value'] ?? null,
            'record_count'=>(int)($metric['record_count'] ?? 0),
            'note'=>sanitize_text_field((string)($metric['note'] ?? '')),
            'score'=>round((float)($metric['orchestration_score'] ?? 0),1),
            'reason'=>sanitize_text_field((string)($metric['orchestration_reason'] ?? '')),
        ];
    }
}
