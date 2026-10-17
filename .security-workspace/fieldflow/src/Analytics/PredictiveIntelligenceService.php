<?php
namespace RoutesPro\Analytics;

use RoutesPro\Support\Logger;

if (!defined('ABSPATH')) exit;

/**
 * FieldFlow Predictive Intelligence Foundation.
 *
 * Deterministic, explainable forward projection based only on observable
 * FieldFlow metrics. This is not a probability model and never infers causes.
 * It cannot mutate operational data, routes, stock, forms or thresholds.
 */
final class PredictiveIntelligenceService {
    public const VERSION = '1.0.0';
    private const MAX_HORIZON_DAYS = 30;

    public static function analyse(
        array $stats,
        array $context = [],
        array $scores = [],
        array $comparison = [],
        array $anomalies = [],
        array $adaptive = []
    ): array {
        try {
            $totalStops = max(0, (int)($stats['total_stops'] ?? 0));
            $currentExecution = self::clamp((float)($stats['done_rate'] ?? 0));
            $comparisonAvailable = !empty($comparison['available']);
            $previousSummary = is_array($stats['previous_summary'] ?? null) ? $stats['previous_summary'] : [];
            $previousExecution = $comparisonAvailable && array_key_exists('done_rate', $previousSummary)
                ? self::clamp((float)$previousSummary['done_rate'])
                : null;
            $executionDelta = $previousExecution === null ? null : round($currentExecution - $previousExecution, 1);

            $period = self::period($stats, $context);
            $baseConfidence = self::score($scores, 'confidence', 0.0);
            $adaptiveSummary = is_array($adaptive['summary'] ?? null) ? $adaptive['summary'] : [];
            $predictiveConfidence = self::predictiveConfidence(
                $baseConfidence,
                $totalStops,
                $comparisonAvailable,
                (int)($adaptiveSummary['patterns'] ?? 0),
                (int)($adaptiveSummary['established_patterns'] ?? 0)
            );

            $momentumWeight = min(0.62, 0.18 + ($predictiveConfidence / 100.0) * 0.44);
            $projectedExecution = $executionDelta === null
                ? $currentExecution
                : self::clamp($currentExecution + ($executionDelta * $momentumWeight));

            $currentRisk = self::score($scores, 'risk', 100.0 - $currentExecution);
            $currentOpportunity = self::score($scores, 'opportunity', max(0.0, 100.0 - $currentExecution));
            $stockRisk = self::nullableScore($scores, 'stock_risk');
            $trendRisk = $executionDelta === null ? 50.0 : self::clamp(50.0 - ($executionDelta * 4.0));
            $anomalyRisk = self::anomalyRisk($anomalies);

            $futureRisk = self::weighted([
                ['value'=>$currentRisk, 'weight'=>0.56, 'available'=>true],
                ['value'=>$trendRisk, 'weight'=>0.21, 'available'=>$executionDelta !== null],
                ['value'=>$stockRisk, 'weight'=>0.13, 'available'=>$stockRisk !== null],
                ['value'=>$anomalyRisk, 'weight'=>0.10, 'available'=>!empty($anomalies)],
            ]);
            $futureOpportunity = self::weighted([
                ['value'=>$currentOpportunity, 'weight'=>0.62, 'available'=>true],
                ['value'=>100.0 - $projectedExecution, 'weight'=>0.23, 'available'=>$totalStops > 0],
                ['value'=>$futureRisk, 'weight'=>0.15, 'available'=>true],
            ]);

            $executionChange = round($projectedExecution - $currentExecution, 1);
            $riskChange = round($futureRisk - $currentRisk, 1);
            $direction = self::direction($executionChange, $riskChange);
            $mode = $comparisonAvailable ? 'equal_window_projection' : 'baseline_projection';

            $leadingSignals = self::leadingSignals(
                $currentExecution,
                $executionDelta,
                $currentRisk,
                $futureRisk,
                $stockRisk,
                $anomalies,
                $adaptiveSummary
            );

            return [
                'version'=>self::VERSION,
                'available'=>$totalStops > 0 || $currentExecution > 0,
                'mode'=>$mode,
                'state'=>$direction,
                'horizon'=>$period,
                'summary'=>[
                    'projected_execution'=>round($projectedExecution, 1),
                    'execution_change_pp'=>$executionChange,
                    'forecast_risk'=>round($futureRisk, 1),
                    'risk_change'=>round($riskChange, 1),
                    'forecast_opportunity'=>round($futureOpportunity, 1),
                    'confidence'=>round($predictiveConfidence, 1),
                    'comparison_available'=>$comparisonAvailable,
                    'sample_stops'=>$totalStops,
                ],
                'forecasts'=>[
                    'execution'=>[
                        'label'=>'Execução projetada',
                        'current'=>round($currentExecution, 1),
                        'forecast'=>round($projectedExecution, 1),
                        'delta'=>$executionChange,
                        'direction'=>$executionChange >= 2 ? 'up' : ($executionChange <= -2 ? 'down' : 'stable'),
                        'unit'=>'%',
                        'explanation'=>$executionDelta === null
                            ? 'Sem janela anterior equivalente. A projeção mantém o nível atual e reduz a confiança.'
                            : 'Projeção conservadora baseada na execução atual, variação contra a janela anterior e confiança das fontes.',
                    ],
                    'risk'=>[
                        'label'=>'Risco projetado',
                        'current'=>round($currentRisk, 1),
                        'forecast'=>round($futureRisk, 1),
                        'delta'=>$riskChange,
                        'direction'=>$riskChange >= 5 ? 'up' : ($riskChange <= -5 ? 'down' : 'stable'),
                        'unit'=>'/100',
                        'explanation'=>'Combina risco atual, tendência de execução, anomalias e Stock Risk quando existe cobertura.',
                    ],
                    'opportunity'=>[
                        'label'=>'Oportunidade projetada',
                        'current'=>round($currentOpportunity, 1),
                        'forecast'=>round($futureOpportunity, 1),
                        'delta'=>round($futureOpportunity - $currentOpportunity, 1),
                        'direction'=>$futureOpportunity >= $currentOpportunity + 5 ? 'up' : ($futureOpportunity <= $currentOpportunity - 5 ? 'down' : 'stable'),
                        'unit'=>'/100',
                        'explanation'=>'Mantém o Opportunity Score como base e acrescenta pressão observável de execução e risco futuro.',
                    ],
                ],
                'leading_signals'=>$leadingSignals,
                'policy'=>self::policy(),
            ];
        } catch (\Throwable $e) {
            if (class_exists(Logger::class)) {
                Logger::warning('Predictive Intelligence indisponível; Analytics mantém leitura atual.', ['context_key'=>'predictive_intelligence'], ['error'=>$e->getMessage()]);
            }
            return self::emptyResult('projection_failed');
        }
    }

    /**
     * Adds future-risk fields to already isolated Store Intelligence profiles.
     * No database calls are made here.
     */
    public static function enrichStoreIntelligence(array $engine, array $context = []): array {
        if (empty($engine['available']) || empty($engine['profiles']) || !is_array($engine['profiles'])) return $engine;

        $profiles = [];
        foreach ($engine['profiles'] as $profile) {
            if (!is_array($profile)) continue;
            $baseRisk = self::clamp((float)($profile['risk_score'] ?? 0));
            $basePriority = self::clamp((float)($profile['adaptive_rank_score'] ?? $profile['priority_score'] ?? 0));
            $execution = self::clamp((float)($profile['execution_score'] ?? $profile['done_rate'] ?? 0));
            $trend = array_key_exists('trend_delta_pp', $profile) && $profile['trend_delta_pp'] !== null ? (float)$profile['trend_delta_pp'] : null;
            $shareGap = array_key_exists('share_gap_pp', $profile) && $profile['share_gap_pp'] !== null ? (float)$profile['share_gap_pp'] : null;
            $frontsGrowth = array_key_exists('fronts_growth_percent', $profile) && $profile['fronts_growth_percent'] !== null ? (float)$profile['fronts_growth_percent'] : null;
            $stockRisk = array_key_exists('stock_risk', $profile) && $profile['stock_risk'] !== null ? self::clamp((float)$profile['stock_risk']) : null;
            $confidence = self::clamp((float)($profile['adaptive_confidence_score'] ?? $profile['confidence_score'] ?? 0));

            $trendRisk = $trend === null ? null : self::clamp(50.0 - ($trend * 4.0));
            $sharePressure = $shareGap === null ? null : self::clamp(max(0.0, -$shareGap) * 6.0);
            $frontsPressure = $frontsGrowth === null ? null : self::clamp(max(0.0, -$frontsGrowth) * 2.6);
            $executionPressure = self::clamp(100.0 - $execution);

            $forecastRisk = self::weighted([
                ['value'=>$baseRisk, 'weight'=>0.52, 'available'=>true],
                ['value'=>$executionPressure, 'weight'=>0.13, 'available'=>true],
                ['value'=>$trendRisk, 'weight'=>0.16, 'available'=>$trendRisk !== null],
                ['value'=>$sharePressure, 'weight'=>0.08, 'available'=>$sharePressure !== null],
                ['value'=>$frontsPressure, 'weight'=>0.04, 'available'=>$frontsPressure !== null],
                ['value'=>$stockRisk, 'weight'=>0.07, 'available'=>$stockRisk !== null],
            ]);

            $forecastPriority = self::clamp(($basePriority * 0.64) + ($forecastRisk * 0.36));
            $forecastConfidence = self::clamp(($confidence * 0.76) + (self::storeCoverage($trend, $shareGap, $frontsGrowth, $stockRisk) * 0.24));
            $riskDelta = round($forecastRisk - $baseRisk, 1);
            $forecastDirection = $riskDelta >= 5 || ($trend !== null && $trend <= -3)
                ? 'deteriorating'
                : ($riskDelta <= -5 || ($trend !== null && $trend >= 5) ? 'improving' : 'stable');

            $profile['forecast_risk_score'] = round($forecastRisk, 1);
            $profile['forecast_priority_score'] = round($forecastPriority, 1);
            $profile['forecast_confidence_score'] = round($forecastConfidence, 1);
            $profile['forecast_direction'] = $forecastDirection;
            $profile['forecast_risk_delta'] = $riskDelta;
            $profile['forecast_reason'] = self::storeForecastReason($trend, $shareGap, $frontsGrowth, $stockRisk, $execution, $forecastRisk);
            $profile['policy']['predictive_changes_operational_scores'] = false;
            $profile['policy']['predictive_is_probability'] = false;
            $profiles[] = $profile;
        }

        $futurePriority = $profiles;
        usort($futurePriority, static function(array $a, array $b): int {
            $cmp = (float)($b['forecast_priority_score'] ?? 0) <=> (float)($a['forecast_priority_score'] ?? 0);
            return $cmp !== 0 ? $cmp : strnatcasecmp((string)($a['location_name'] ?? ''), (string)($b['location_name'] ?? ''));
        });
        $futureRisk = $profiles;
        usort($futureRisk, static function(array $a, array $b): int {
            return (float)($b['forecast_risk_score'] ?? 0) <=> (float)($a['forecast_risk_score'] ?? 0);
        });
        $likelyDeterioration = array_values(array_filter($futureRisk, static function(array $profile): bool {
            return (string)($profile['forecast_direction'] ?? '') === 'deteriorating' || (float)($profile['forecast_risk_score'] ?? 0) >= 70;
        }));

        $engine['profiles'] = $profiles;
        $engine['rankings']['future_priority'] = array_slice($futurePriority, 0, 12);
        $engine['rankings']['forecast_risk'] = array_slice($futureRisk, 0, 12);
        $engine['rankings']['likely_deterioration'] = array_slice($likelyDeterioration, 0, 12);
        $engine['summary']['predictive_profiles'] = count($profiles);
        $engine['summary']['forecast_high_risk'] = count(array_filter($profiles, static fn(array $p): bool => (float)($p['forecast_risk_score'] ?? 0) >= 70));
        $engine['summary']['forecast_deteriorating'] = count($likelyDeterioration);
        $conf = array_map(static fn(array $p): float => (float)($p['forecast_confidence_score'] ?? 0), $profiles);
        $engine['summary']['forecast_confidence_avg'] = $conf ? round(array_sum($conf) / count($conf), 1) : 0.0;
        $engine['predictive'] = [
            'version'=>self::VERSION,
            'available'=>true,
            'mode'=>'store_forward_risk',
            'policy'=>self::policy(),
        ];
        $engine['policy']['predictive_intelligence_enabled'] = true;
        $engine['policy']['predictive_changes_operational_scores'] = false;
        $engine['policy']['predictive_is_probability'] = false;
        return $engine;
    }

    private static function period(array $stats, array $context): array {
        $from = sanitize_text_field((string)($stats['from'] ?? $context['from'] ?? ''));
        $to = sanitize_text_field((string)($stats['to'] ?? $context['to'] ?? ''));
        $days = 7;
        if ($from !== '' && $to !== '') {
            $fromTs = strtotime($from . ' 00:00:00');
            $toTs = strtotime($to . ' 00:00:00');
            if ($fromTs && $toTs && $toTs >= $fromTs) $days = max(1, (int)floor(($toTs - $fromTs) / DAY_IN_SECONDS) + 1);
        }
        $days = min(self::MAX_HORIZON_DAYS, $days);
        $nextFrom = $to !== '' ? date('Y-m-d', strtotime($to . ' +1 day')) : '';
        $nextTo = $nextFrom !== '' ? date('Y-m-d', strtotime($nextFrom . ' +' . max(0, $days - 1) . ' days')) : '';
        return [
            'days'=>$days,
            'from'=>$nextFrom,
            'to'=>$nextTo,
            'label'=>$nextFrom !== '' && $nextTo !== '' ? $nextFrom . ' a ' . $nextTo : 'Próxima janela equivalente',
        ];
    }

    private static function predictiveConfidence(float $baseConfidence, int $totalStops, bool $comparisonAvailable, int $patterns, int $established): float {
        $sample = min(100.0, ($totalStops / 40.0) * 100.0);
        $comparison = $comparisonAvailable ? 100.0 : 25.0;
        $history = min(100.0, ($patterns * 8.0) + ($established * 10.0));
        return self::clamp(($baseConfidence * 0.45) + ($sample * 0.24) + ($comparison * 0.21) + ($history * 0.10));
    }

    private static function leadingSignals(float $execution, ?float $delta, float $risk, float $futureRisk, ?float $stockRisk, array $anomalies, array $adaptiveSummary): array {
        $signals = [];
        if ($delta !== null) {
            $signals[] = [
                'key'=>'execution_momentum',
                'label'=>'Momentum de execução',
                'value'=>($delta > 0 ? '+' : '') . number_format_i18n($delta, 1) . ' pp',
                'direction'=>$delta >= 2 ? 'positive' : ($delta <= -2 ? 'negative' : 'stable'),
                'detail'=>'Diferença observada contra a janela anterior equivalente.',
            ];
        }
        if ($stockRisk !== null) {
            $signals[] = [
                'key'=>'stock_risk',
                'label'=>'Stock Risk atual',
                'value'=>number_format_i18n($stockRisk, 1) . '/100',
                'direction'=>$stockRisk >= 65 ? 'negative' : ($stockRisk <= 30 ? 'positive' : 'stable'),
                'detail'=>'Snapshot atual. Não é uma previsão de vendas.',
            ];
        }
        if ($anomalies) {
            $high = count(array_filter($anomalies, static fn($a): bool => in_array((string)($a['severity'] ?? ''), ['high','critical'], true)));
            $signals[] = [
                'key'=>'anomaly_pressure',
                'label'=>'Pressão de alertas',
                'value'=>$high . ' alerta(s) alto/crítico',
                'direction'=>$high > 0 ? 'negative' : 'stable',
                'detail'=>'Alertas determinísticos ativos no período atual.',
            ];
        }
        if ((int)($adaptiveSummary['patterns'] ?? 0) > 0) {
            $signals[] = [
                'key'=>'learning_evidence',
                'label'=>'Evidência histórica',
                'value'=>(int)($adaptiveSummary['patterns'] ?? 0) . ' padrão(ões)',
                'direction'=>(float)($adaptiveSummary['effectiveness_avg'] ?? 50) >= 60 ? 'positive' : 'stable',
                'detail'=>'Usada apenas para confiança e priorização assistida, nunca para causalidade.',
            ];
        }
        $riskDelta = $futureRisk - $risk;
        $signals[] = [
            'key'=>'risk_trajectory',
            'label'=>'Trajetória de risco',
            'value'=>($riskDelta > 0 ? '+' : '') . number_format_i18n($riskDelta, 1) . ' pontos',
            'direction'=>$riskDelta >= 5 ? 'negative' : ($riskDelta <= -5 ? 'positive' : 'stable'),
            'detail'=>'Projeção derivada dos sinais atuais, não probabilidade de ocorrência.',
        ];
        return array_slice($signals, 0, 6);
    }

    private static function anomalyRisk(array $anomalies): float {
        $score = 0.0;
        foreach ($anomalies as $anomaly) {
            if (!is_array($anomaly)) continue;
            $severity = sanitize_key((string)($anomaly['severity'] ?? 'medium'));
            $score += ['critical'=>25.0,'high'=>15.0,'medium'=>8.0,'low'=>3.0][$severity] ?? 8.0;
        }
        return self::clamp($score);
    }

    private static function storeCoverage(?float $trend, ?float $shareGap, ?float $frontsGrowth, ?float $stockRisk): float {
        $coverage = 35.0;
        if ($trend !== null) $coverage += 25.0;
        if ($shareGap !== null) $coverage += 15.0;
        if ($frontsGrowth !== null) $coverage += 10.0;
        if ($stockRisk !== null) $coverage += 15.0;
        return self::clamp($coverage);
    }

    private static function storeForecastReason(?float $trend, ?float $shareGap, ?float $frontsGrowth, ?float $stockRisk, float $execution, float $forecastRisk): string {
        $parts = [];
        if ($execution < 80) $parts[] = 'execução atual ' . number_format_i18n($execution, 1) . '%';
        if ($trend !== null && $trend <= -3) $parts[] = 'tendência ' . number_format_i18n($trend, 1) . ' pp';
        if ($shareGap !== null && $shareGap <= -5) $parts[] = 'Share Gap ' . number_format_i18n($shareGap, 1) . ' pp';
        if ($frontsGrowth !== null && $frontsGrowth < -5) $parts[] = 'frentes ' . number_format_i18n($frontsGrowth, 1) . '%';
        if ($stockRisk !== null && $stockRisk >= 55) $parts[] = 'Stock Risk ' . number_format_i18n($stockRisk, 1) . '/100';
        if (!$parts) $parts[] = 'sinais atuais sem deterioração material';
        return sanitize_text_field('Risco futuro ' . number_format_i18n($forecastRisk, 1) . '/100 com base em ' . implode(', ', array_slice($parts, 0, 4)) . '.');
    }

    private static function direction(float $executionChange, float $riskChange): string {
        if ($executionChange <= -2 || $riskChange >= 5) return 'deteriorating';
        if ($executionChange >= 2 && $riskChange <= -3) return 'improving';
        return 'stable';
    }

    private static function score(array $scores, string $key, float $fallback): float {
        $row = is_array($scores[$key] ?? null) ? $scores[$key] : [];
        return array_key_exists('score', $row) && is_numeric($row['score']) ? self::clamp((float)$row['score']) : self::clamp($fallback);
    }

    private static function nullableScore(array $scores, string $key): ?float {
        $row = is_array($scores[$key] ?? null) ? $scores[$key] : [];
        if (!array_key_exists('score', $row) || $row['score'] === null || !is_numeric($row['score'])) return null;
        return self::clamp((float)$row['score']);
    }

    private static function weighted(array $components): float {
        $sum = 0.0;
        $weight = 0.0;
        foreach ($components as $component) {
            if (empty($component['available'])) continue;
            if (!isset($component['value']) || $component['value'] === null || !is_numeric($component['value'])) continue;
            $w = max(0.0, (float)($component['weight'] ?? 0));
            $sum += self::clamp((float)$component['value']) * $w;
            $weight += $w;
        }
        return $weight > 0 ? self::clamp($sum / $weight) : 0.0;
    }

    private static function emptyResult(string $reason): array {
        return [
            'version'=>self::VERSION,
            'available'=>false,
            'reason'=>$reason,
            'mode'=>'unavailable',
            'state'=>'stable',
            'horizon'=>[],
            'summary'=>[],
            'forecasts'=>[],
            'leading_signals'=>[],
            'policy'=>self::policy(),
        ];
    }

    private static function policy(): array {
        return [
            'predictive_intelligence_enabled'=>true,
            'deterministic_projection'=>true,
            'is_probability_model'=>false,
            'causes_inferred'=>false,
            'cross_client_learning'=>false,
            'autonomous_mutations'=>false,
            'operational_scores_mutated'=>false,
            'routes_mutated'=>false,
            'stocks_mutated'=>false,
            'human_review_required'=>true,
            'max_horizon_days'=>self::MAX_HORIZON_DAYS,
            'calibration_requires_future_outcomes'=>true,
        ];
    }

    private static function clamp(float $value): float {
        return max(0.0, min(100.0, $value));
    }
}
