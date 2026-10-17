<?php
namespace RoutesPro\Analytics;

if (!defined('ABSPATH')) exit;

/**
 * Analytics 2.0 deterministic decision scores.
 *
 * Scores are calculated exclusively from aggregated FieldFlow facts already
 * available to Analytics. No cause is inferred and no operational data is
 * mutated. Risk scores are intentionally directional: higher means more risk,
 * while Execution, Trend and Confidence are better when higher. Opportunity is
 * potential upside, not a quality grade.
 */
final class DecisionScoreService {
    public const VERSION = '2.0.0';

    public static function calculate(array $stats, array $anomalies, array $stocks, array $confidence, array $comparison = []): array {
        $totalStops = max(0, (int)($stats['total_stops'] ?? 0));
        $doneStops = max(0, (int)($stats['done_stops'] ?? 0));
        $pending = max(0, $totalStops - $doneStops);
        $doneRate = self::clamp((float)($stats['done_rate'] ?? 0));
        $hasExecutionData = $totalStops > 0;
        $onTime = is_numeric($stats['on_time_rate'] ?? null) ? self::clamp((float)$stats['on_time_rate']) : null;
        $execution = $onTime === null ? $doneRate : self::clamp(($doneRate * .82) + ($onTime * .18));

        $comparisonAvailable = !array_key_exists('available', $comparison) || !empty($comparison['available']);
        $delta = $comparisonAvailable
            ? (float)($comparison['execution_delta_pp'] ?? ((float)($stats['done_rate'] ?? 0) - (float)($stats['previous_summary']['done_rate'] ?? 0)))
            : 0.0;
        $trend = self::clamp(50 + ($delta * 3.0));

        $severityWeight = ['critical'=>34,'high'=>20,'medium'=>9,'low'=>3];
        $risk = 0.0;
        foreach ($anomalies as $anomaly) {
            if (!is_array($anomaly)) continue;
            $risk += (float)($severityWeight[sanitize_key((string)($anomaly['severity'] ?? 'medium'))] ?? 6);
        }
        $risk = self::clamp($risk);

        $locationRows = array_values(array_filter((array)($stats['by_location'] ?? []), 'is_array'));
        $weakStores = count(array_filter($locationRows, static function(array $row): bool {
            return (int)($row['stops'] ?? 0) >= 2 && (float)($row['done_rate'] ?? 0) < 70;
        }));
        $locationCount = count($locationRows);
        $pendingRatio = $totalStops > 0 ? ($pending / $totalStops) * 100 : 0;
        $weakStoreRatio = $locationCount > 0 ? ($weakStores / $locationCount) * 100 : 0;
        $commercialSignal = 0.0;
        foreach ($anomalies as $anomaly) {
            $type = sanitize_key((string)($anomaly['type'] ?? ''));
            if ($type === 'share_space_below_target') $commercialSignal += 18;
            if ($type === 'fronts_decline') $commercialSignal += 12;
            if (in_array($type, ['stock_breaks','stock_low','stock_negative'], true)) $commercialSignal += 8;
        }
        $opportunity = self::clamp(($pendingRatio * .42) + ($weakStoreRatio * .30) + min(28, $commercialSignal));
        if ($totalStops === 0 && !$locationRows && !$commercialSignal) $opportunity = 0;

        $stockRisk = null;
        if (!empty($stocks['available'])) {
            $negative = max(0, (int)($stocks['negative_count'] ?? 0));
            $zero = max(0, (int)($stocks['zero_count'] ?? 0));
            $low = max(0, (int)($stocks['low_count'] ?? 0));
            $coverage = is_numeric($stocks['mapping_coverage'] ?? null) ? self::clamp((float)$stocks['mapping_coverage']) : 100;
            $stockRisk = self::clamp(
                min(38, $negative * 14) +
                min(42, $zero * 4.5) +
                min(15, $low * 1.2) +
                min(15, max(0, 90 - $coverage) * .75)
            );
        }

        $confidenceScore = self::clamp((float)($confidence['score'] ?? 0));
        $health = self::clamp(($execution * .62) + ($trend * .13) + ((100 - $risk) * .25));
        $hasAnySignal = $hasExecutionData || !empty($stocks['available']) || !empty($anomalies);
        $hasOpportunitySignal = $totalStops > 0 || $locationCount > 0 || $commercialSignal > 0;

        return [
            'version'=>self::VERSION,
            'operational_health'=>$hasExecutionData
                ? self::score($health, self::positiveLabel($health), 'higher_better', 'Combina execução, tendência e alertas operacionais.')
                : self::emptyScore('Sem visitas', 'higher_better', 'Não existem visitas planeadas suficientes para calcular saúde operacional.'),
            'execution'=>$hasExecutionData
                ? self::score($execution, self::positiveLabel($execution), 'higher_better', $onTime === null ? 'Baseado na taxa de conclusão das visitas.' : 'Combina conclusão das visitas e pontualidade registada.')
                : self::emptyScore('Sem visitas', 'higher_better', 'Não existem visitas planeadas no período selecionado.'),
            'trend'=>($hasExecutionData && $comparisonAvailable)
                ? self::score($trend, self::trendLabel($delta), 'higher_better', 'Normaliza a variação de execução face ao período anterior.', ['delta_pp'=>round($delta,1)])
                : self::emptyScore($hasExecutionData ? 'Rota individual' : 'Sem comparação', 'higher_better', $hasExecutionData ? 'Uma rota individual não é comparada com uma rota ID diferente do período anterior.' : 'A tendência exige atividade no período atual.'),
            'risk'=>$hasAnySignal
                ? self::score($risk, self::riskLabel($risk), 'higher_worse', 'Agrega a severidade dos alertas determinísticos ativos.')
                : self::emptyScore('Sem dados', 'higher_worse', 'Não existem fontes suficientes para afirmar ausência de risco.'),
            'opportunity'=>$hasOpportunitySignal
                ? self::score($opportunity, self::opportunityLabel($opportunity), 'higher_opportunity', 'Mede margem operacional observável em pendentes, lojas fracas e gaps comerciais.')
                : self::emptyScore('Sem dados', 'higher_opportunity', 'Não existem sinais operacionais suficientes para calcular oportunidade.'),
            'stock_risk'=>$stockRisk === null ? [
                'score'=>null,
                'label'=>'Sem snapshot',
                'direction'=>'higher_worse',
                'explanation'=>'Não existe snapshot de stock publicado para este contexto.',
            ] : self::score($stockRisk, self::riskLabel($stockRisk), 'higher_worse', 'Usa ruturas, stock baixo, valores negativos e cobertura de mapping.'),
            'confidence'=>self::score($confidenceScore, self::confidenceLabel($confidenceScore), 'higher_better', sanitize_text_field((string)($confidence['explanation'] ?? 'Confiança calculada pela cobertura das fontes.'))),
            'facts'=>[
                'pending_stops'=>$pending,
                'locations'=>$locationCount,
                'weak_locations'=>$weakStores,
                'high_priority_alerts'=>count(array_filter($anomalies, static fn($a)=>is_array($a) && in_array((string)($a['severity'] ?? ''), ['critical','high'], true))),
            ],
            'policy'=>[
                'causes_inferred'=>false,
                'self_learning'=>false,
                'human_review_required'=>true,
            ],
        ];
    }

    public static function priorityLocations(array $stats, int $limit = 12): array {
        $rows = array_values(array_filter((array)($stats['by_location'] ?? []), 'is_array'));
        $out = [];
        foreach ($rows as $row) {
            $stops = max(0, (int)($row['stops'] ?? 0));
            if ($stops <= 0) continue;
            $done = max(0, (int)($row['stops_done'] ?? 0));
            $pending = max(0, $stops - $done);
            $rate = self::clamp((float)($row['done_rate'] ?? 0));
            $pendingRatio = ($pending / $stops) * 100;
            $volumeSignal = min(14, log(max(1, $stops) + 1, 2) * 3.5);
            $score = self::clamp(((100 - $rate) * .68) + ($pendingRatio * .22) + $volumeSignal);
            $priority = $score >= 72 ? 'high' : ($score >= 48 ? 'medium' : 'monitor');
            $out[] = [
                'location_id'=>absint($row['location_id'] ?? 0),
                'location_name'=>sanitize_text_field((string)($row['location_name'] ?? 'PDV')),
                'city'=>sanitize_text_field((string)($row['city'] ?? '')),
                'stops'=>$stops,
                'done_stops'=>$done,
                'pending_stops'=>$pending,
                'done_rate'=>round($rate,1),
                'priority_score'=>round($score,1),
                'priority'=>$priority,
                'reason'=>$pending > 0
                    ? $pending . ' visita(s) pendente(s), execução em ' . number_format_i18n($rate, 1) . '%.'
                    : 'Execução em ' . number_format_i18n($rate, 1) . '%.',
            ];
        }
        usort($out, static function(array $a, array $b): int {
            $score = (float)$b['priority_score'] <=> (float)$a['priority_score'];
            if ($score !== 0) return $score;
            return (int)$b['pending_stops'] <=> (int)$a['pending_stops'];
        });
        return array_slice($out, 0, max(1, min(50, $limit)));
    }

    private static function emptyScore(string $label, string $direction, string $explanation): array {
        return [
            'score'=>null,
            'label'=>$label,
            'direction'=>$direction,
            'explanation'=>$explanation,
        ];
    }

    private static function score(float $score, string $label, string $direction, string $explanation, array $extra = []): array {
        return array_merge([
            'score'=>round(self::clamp($score),1),
            'label'=>$label,
            'direction'=>$direction,
            'explanation'=>$explanation,
        ], $extra);
    }

    private static function clamp(float $value): float {
        return max(0.0, min(100.0, $value));
    }

    private static function positiveLabel(float $score): string {
        return $score >= 85 ? 'Forte' : ($score >= 68 ? 'Estável' : ($score >= 48 ? 'A acompanhar' : 'Crítico'));
    }

    private static function trendLabel(float $delta): string {
        return $delta >= 5 ? 'A melhorar' : ($delta <= -5 ? 'Em queda' : 'Estável');
    }

    private static function riskLabel(float $score): string {
        return $score >= 70 ? 'Elevado' : ($score >= 40 ? 'Moderado' : ($score > 0 ? 'Baixo' : 'Sem sinal relevante'));
    }

    private static function opportunityLabel(float $score): string {
        return $score >= 70 ? 'Muito elevada' : ($score >= 45 ? 'Elevada' : ($score >= 20 ? 'Moderada' : 'Baixa'));
    }

    private static function confidenceLabel(float $score): string {
        return $score >= 80 ? 'Alta' : ($score >= 55 ? 'Média' : 'Baixa');
    }
}
