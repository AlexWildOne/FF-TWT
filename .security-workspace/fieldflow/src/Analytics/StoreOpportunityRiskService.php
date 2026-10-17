<?php
namespace RoutesPro\Analytics;

use RoutesPro\Stocks\StockService;
use RoutesPro\Support\Logger;

if (!defined('ABSPATH')) exit;

/**
 * FieldFlow Store Opportunity & Risk Engine.
 *
 * Builds an explainable profile per PDV from current execution, equal-window
 * execution history, Share Space/fronts analytics, exact mapped stock signals
 * and the auditable Recommendation Learning dataset. Learning evidence may
 * adjust confidence only in this release. It never changes operational scores,
 * routes, stock, forms or recommendations autonomously.
 */
final class StoreOpportunityRiskService {
    public const VERSION = '1.0.2';

    public static function build(array $stats, array $context = [], array $confidence = [], array $aggregateStocks = [], array $options = []): array {
        $rows = array_values(array_filter((array)($stats['by_location'] ?? []), 'is_array'));
        if (!$rows) return self::emptyResult();

        $locationIds = array_values(array_unique(array_filter(array_map(static fn(array $row): int => absint($row['location_id'] ?? 0), $rows))));
        if (!$locationIds) return self::emptyResult();

        $previous = [];
        foreach ((array)($stats['previous_by_location'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $id = absint($row['location_id'] ?? 0);
            if ($id > 0) $previous[$id] = $row;
        }

        $commercial = self::commercialSignals((array)($stats['form_analytics']['metrics'] ?? []));
        $includeStock = !array_key_exists('include_stock', $options) || (bool)$options['include_stock'];
        $includeLearning = !array_key_exists('include_learning', $options) || (bool)$options['include_learning'];
        $stockMap = [];
        $stockScopeKnownEmpty = $aggregateStocks && empty($aggregateStocks['available']);
        if ($includeStock && !$stockScopeKnownEmpty && class_exists(StockService::class)) {
            try {
                $stockMap = StockService::analyticsLocationContexts($context, $locationIds);
            } catch (\Throwable $e) {
                Logger::warning('Falha ao calcular stock por loja para Store Intelligence.', ['context_key'=>'analytics_store_stock'], ['error'=>$e->getMessage()]);
                $stockMap = [];
            }
        }

        $learningMap = [];
        if ($includeLearning && class_exists(RecommendationLearningService::class)) {
            try { $learningMap = RecommendationLearningService::locationLearningStats($locationIds, $context); } catch (\Throwable $e) { $learningMap = []; }
        }

        $globalConfidence = self::clamp((float)($confidence['score'] ?? 0));
        $profiles = [];
        $profileErrors = 0;
        foreach ($rows as $row) {
            $id = absint($row['location_id'] ?? 0);
            if ($id <= 0) continue;
            $prev = $previous[$id] ?? [];
            $share = $commercial['share'][$id] ?? [];
            $fronts = $commercial['fronts'][$id] ?? [];
            $stock = $stockMap[$id] ?? [];
            $learning = $learningMap[$id] ?? self::emptyLearning();
            try {
                $profiles[] = self::profile($row, $prev, $share, $fronts, $stock, $learning, $globalConfidence);
            } catch (\Throwable $e) {
                $profileErrors++;
                if ($profileErrors <= 3) Logger::warning('Perfil Store Intelligence ignorado por dados incompatíveis.', ['context_key'=>'analytics_store_profile'], ['location_id'=>$id,'error'=>$e->getMessage()]);
            }
        }

        if (!$profiles) return self::emptyResult();

        usort($profiles, static fn(array $a, array $b): int => ((float)$b['priority_score'] <=> (float)$a['priority_score']) ?: strnatcasecmp((string)$a['location_name'], (string)$b['location_name']));
        $rankings = self::rankings($profiles);
        $selectedId = absint($context['location_id'] ?? 0);
        $selected = null;
        if ($selectedId > 0) {
            foreach ($profiles as $profile) if ((int)$profile['location_id'] === $selectedId) { $selected = $profile; break; }
        }

        $confidenceValues = array_map(static fn(array $row): float => (float)($row['confidence_score'] ?? 0), $profiles);
        $summary = [
            'stores'=>count($profiles),
            'high_priority'=>count(array_filter($profiles, static fn(array $p): bool => (string)($p['priority'] ?? '') === 'high')),
            'critical_priority'=>count(array_filter($profiles, static fn(array $p): bool => (string)($p['severity'] ?? '') === 'critical')),
            'high_risk'=>count(array_filter($profiles, static fn(array $p): bool => (float)($p['risk_score'] ?? 0) >= 70)),
            'high_opportunity'=>count(array_filter($profiles, static fn(array $p): bool => (float)($p['opportunity_score'] ?? 0) >= 70)),
            'stock_risk'=>count(array_filter($profiles, static fn(array $p): bool => ($p['stock_risk'] ?? null) !== null && (float)$p['stock_risk'] >= 55)),
            'deteriorating'=>count((array)($rankings['deteriorating'] ?? [])),
            'recovering'=>count((array)($rankings['recovered'] ?? [])),
            'confidence_avg'=>$confidenceValues ? round(array_sum($confidenceValues) / count($confidenceValues), 1) : 0.0,
            'stock_coverage'=>round((count(array_filter($profiles, static fn(array $p): bool => ($p['stock_risk'] ?? null) !== null)) / max(1, count($profiles))) * 100, 1),
            'learning_coverage'=>round((count(array_filter($profiles, static fn(array $p): bool => (int)($p['learning']['total'] ?? 0) > 0)) / max(1, count($profiles))) * 100, 1),
            'profile_errors'=>$profileErrors,
        ];

        return [
            'version'=>self::VERSION,
            'available'=>true,
            'profiles'=>$profiles,
            'rankings'=>$rankings,
            'summary'=>$summary,
            'selected'=>$selected,
            'policy'=>array_merge(self::policy(), [
                'stock_enrichment_enabled'=>$includeStock,
                'learning_enrichment_enabled'=>$includeLearning,
            ]),
        ];
    }

    private static function profile(array $row, array $previous, array $share, array $fronts, array $stock, array $learning, float $globalConfidence): array {
        $stops = max(0, (int)($row['stops'] ?? 0));
        $done = max(0, (int)($row['stops_done'] ?? 0));
        $pending = max(0, $stops - $done);
        $execution = self::clamp((float)($row['done_rate'] ?? 0));
        $pendingRatio = $stops > 0 ? ($pending / $stops) * 100 : 0.0;

        $previousStops = max(0, (int)($previous['stops'] ?? 0));
        $previousRate = $previousStops > 0 ? self::clamp((float)($previous['done_rate'] ?? 0)) : null;
        $trendDelta = $previousRate === null ? null : round($execution - $previousRate, 1);
        $trendScore = $trendDelta === null ? null : self::clamp(50 + ($trendDelta * 3.0));
        $trendLabel = self::trendLabel($trendDelta, $previousStops, $stops);

        $shareGap = isset($share['avg_gap']) ? round((float)$share['avg_gap'], 1) : null;
        $shareRisk = null;
        if ($shareGap !== null) {
            $belowRatio = (int)($share['count'] ?? 0) > 0 ? ((int)($share['below_target'] ?? 0) / max(1, (int)$share['count'])) * 100 : 0;
            $shareRisk = self::clamp(max(0, -$shareGap) * 4.2 + ($belowRatio * .22));
        }

        $frontsGrowth = isset($fronts['growth_percent']) ? round((float)$fronts['growth_percent'], 1) : null;
        $frontsDifference = isset($fronts['difference_total']) ? round((float)$fronts['difference_total'], 2) : null;
        $frontsRisk = null;
        if ($frontsDifference !== null || $frontsGrowth !== null) {
            $decline = max(0, -(float)($frontsDifference ?? 0));
            $declinePct = max(0, -(float)($frontsGrowth ?? 0));
            $downRatio = (int)($fronts['count'] ?? 0) > 0 ? ((int)($fronts['down'] ?? 0) / max(1, (int)$fronts['count'])) * 100 : 0;
            $frontsRisk = self::clamp(($decline * 7.5) + ($declinePct * .75) + ($downRatio * .18));
        }

        $stockRisk = self::stockRisk($stock);
        $executionRisk = self::clamp(100 - $execution);
        $learningRisk = null; // Learning is confidence evidence only in 2.2.437.

        $risk = self::weighted([
            ['value'=>$executionRisk,'weight'=>.43,'available'=>$stops > 0],
            ['value'=>$shareRisk,'weight'=>.18,'available'=>$shareRisk !== null],
            ['value'=>$frontsRisk,'weight'=>.11,'available'=>$frontsRisk !== null],
            ['value'=>$stockRisk,'weight'=>.20,'available'=>$stockRisk !== null],
        ]);

        $shareOpportunity = $shareGap === null ? null : self::clamp(max(0, -$shareGap) * 5.0);
        $frontsOpportunity = $frontsRisk === null ? null : $frontsRisk;
        $stockOpportunity = $stockRisk;
        $learningOpportunity = null; // Learning cannot raise Opportunity Score in this release.
        $opportunity = self::weighted([
            ['value'=>$pendingRatio,'weight'=>.38,'available'=>$stops > 0],
            ['value'=>$shareOpportunity,'weight'=>.27,'available'=>$shareOpportunity !== null],
            ['value'=>$frontsOpportunity,'weight'=>.13,'available'=>$frontsOpportunity !== null],
            ['value'=>$stockOpportunity,'weight'=>.14,'available'=>$stockOpportunity !== null],
        ]);

        $health = $trendScore === null
            ? self::clamp(($execution * .66) + ((100 - $risk) * .34))
            : self::clamp(($execution * .57) + ((100 - $risk) * .30) + ($trendScore * .13));

        $coverage = 0.0;
        if ($stops > 0) $coverage += 28;
        if ($previousRate !== null) $coverage += 14;
        if ($shareRisk !== null) $coverage += 16;
        if ($frontsRisk !== null) $coverage += 10;
        if ($stockRisk !== null) $coverage += 20;
        if ((int)($learning['total'] ?? 0) > 0) $coverage += 12;
        $confidence = self::clamp(($globalConfidence * .48) + ($coverage * .52));

        // 2.2.437 uses historical learning only as confidence evidence. It does
        // not change risk/opportunity/health weights yet.
        $observed = (int)($learning['observed'] ?? 0);
        if ($observed >= 2 && isset($learning['efficacy_rate'])) {
            $confidence += max(-4.0, min(4.0, (((float)$learning['efficacy_rate']) - 50.0) * .08));
        }
        $notRelevant = (int)($learning['not_relevant'] ?? 0);
        $totalLearning = max(1, (int)($learning['total'] ?? 0));
        if ($notRelevant > 0) $confidence -= min(3.0, ($notRelevant / $totalLearning) * 6.0);
        $confidence = self::clamp($confidence);

        $priorityScore = self::clamp(($risk * .47) + ($opportunity * .41) + ((100 - $health) * .12));
        $priority = $priorityScore >= 72 ? 'high' : ($priorityScore >= 48 ? 'medium' : 'monitor');
        $severity = $priorityScore >= 86 ? 'critical' : ($priorityScore >= 72 ? 'high' : ($priorityScore >= 48 ? 'medium' : 'low'));

        $signals = [];
        self::addSignal($signals, 'execution', 'Execução', $executionRisk, $execution . '%', $execution < 70 ? 'high' : ($execution < 85 ? 'medium' : 'low'));
        if ($trendDelta !== null) self::addSignal($signals, 'trend', 'Tendência', self::clamp(max(0, -$trendDelta) * 5), ($trendDelta > 0 ? '+' : '') . $trendDelta . ' pp', $trendDelta <= -10 ? 'high' : ($trendDelta < -3 ? 'medium' : 'low'));
        if ($shareRisk !== null) self::addSignal($signals, 'share_space', 'Share Space', $shareRisk, ($shareGap > 0 ? '+' : '') . $shareGap . ' pp gap', $shareRisk >= 65 ? 'high' : ($shareRisk >= 35 ? 'medium' : 'low'));
        if ($frontsRisk !== null) self::addSignal($signals, 'fronts', 'Frentes', $frontsRisk, ($frontsGrowth === null ? number_format_i18n((float)($frontsDifference ?? 0), 1) . ' dif.' : (($frontsGrowth > 0 ? '+' : '') . $frontsGrowth . '%')), $frontsRisk >= 65 ? 'high' : ($frontsRisk >= 35 ? 'medium' : 'low'));
        if ($stockRisk !== null) self::addSignal($signals, 'stock', 'Stock', $stockRisk, (int)($stock['zero_count'] ?? 0) . ' ruturas · ' . (int)($stock['low_count'] ?? 0) . ' baixos', $stockRisk >= 65 ? 'high' : ($stockRisk >= 35 ? 'medium' : 'low'));
        if ((int)($learning['total'] ?? 0) > 0) self::addSignal($signals, 'learning', 'Histórico', 0, (int)($learning['open'] ?? 0) . ' abertas · eficácia ' . number_format_i18n((float)($learning['efficacy_rate'] ?? 0), 1) . '%', 'low');
        usort($signals, static fn(array $a, array $b): int => (float)$b['score'] <=> (float)$a['score']);

        $reason = self::reason($pending, $execution, $trendDelta, $shareGap, $frontsGrowth, $stockRisk, $stock, $learning, $signals);
        $action = self::suggestedAction($signals, $pending);

        return [
            'location_id'=>absint($row['location_id'] ?? 0),
            'location_name'=>sanitize_text_field((string)($row['location_name'] ?? 'PDV')),
            'city'=>sanitize_text_field((string)($row['city'] ?? '')),
            'stops'=>$stops,
            'done_stops'=>$done,
            'pending_stops'=>$pending,
            'done_rate'=>round($execution, 1),
            'previous_done_rate'=>$previousRate === null ? null : round($previousRate, 1),
            'trend_delta_pp'=>$trendDelta,
            'trend'=>$trendLabel,
            'operational_health'=>round($health, 1),
            'execution_score'=>round($execution, 1),
            'risk_score'=>round($risk, 1),
            'opportunity_score'=>round($opportunity, 1),
            'stock_risk'=>$stockRisk === null ? null : round($stockRisk, 1),
            'share_gap_pp'=>$shareGap,
            'fronts_growth_percent'=>$frontsGrowth,
            'priority_score'=>round($priorityScore, 1),
            'priority'=>$priority,
            'severity'=>$severity,
            'confidence_score'=>round($confidence, 1),
            'reason'=>$reason,
            'suggested_action'=>$action,
            'signals'=>array_slice($signals, 0, 6),
            'share'=>self::publicShare($share),
            'fronts'=>self::publicFronts($fronts),
            'stock'=>self::publicStock($stock),
            'learning'=>$learning,
            'policy'=>[
                'causes_inferred'=>false,
                'learning_changes_scores'=>false,
                'learning_influences_confidence_only'=>true,
            ],
        ];
    }

    private static function commercialSignals(array $metrics): array {
        $share = [];
        $fronts = [];
        foreach ($metrics as $metric) {
            if (!is_array($metric)) continue;
            $isShare = !empty($metric['is_share_space_metric']) || sanitize_key((string)($metric['question_type'] ?? '')) === 'share_space_matrix';
            $isFronts = !empty($metric['is_fronts_metric']) || sanitize_key((string)($metric['question_type'] ?? '')) === 'product_matrix';
            if (!$isShare && !$isFronts) continue;
            foreach ((array)($metric['rows'] ?? []) as $row) {
                if (!is_array($row)) continue;
                $id = absint($row['location_id'] ?? 0);
                if ($id <= 0) continue;
                if ($isShare && array_key_exists('gap', $row) && is_numeric($row['gap'])) {
                    if (!isset($share[$id])) $share[$id] = ['count'=>0,'below_target'=>0,'gap_total'=>0.0,'share_total'=>0.0,'target_total'=>0.0,'products'=>[]];
                    $gap = (float)$row['gap'];
                    $share[$id]['count']++;
                    $share[$id]['gap_total'] += $gap;
                    $share[$id]['share_total'] += is_numeric($row['pr_share'] ?? null) ? (float)$row['pr_share'] : 0.0;
                    $share[$id]['target_total'] += is_numeric($row['target'] ?? null) ? (float)$row['target'] : 0.0;
                    if ($gap < 0) $share[$id]['below_target']++;
                    $product = sanitize_text_field((string)($row['product'] ?? $row['metric'] ?? ''));
                    if ($product !== '') $share[$id]['products'][] = ['label'=>$product,'gap'=>round($gap,1)];
                }
                if ($isFronts && (array_key_exists('difference', $row) || array_key_exists('growth_percent', $row))) {
                    if (!isset($fronts[$id])) $fronts[$id] = ['count'=>0,'down'=>0,'up'=>0,'difference_total'=>0.0,'growth_values'=>[],'products'=>[]];
                    $diff = is_numeric($row['difference'] ?? null) ? (float)$row['difference'] : 0.0;
                    $growth = is_numeric($row['growth_percent'] ?? null) ? (float)$row['growth_percent'] : null;
                    $fronts[$id]['count']++;
                    $fronts[$id]['difference_total'] += $diff;
                    if ($diff < 0 || ($growth !== null && $growth < 0)) $fronts[$id]['down']++;
                    elseif ($diff > 0 || ($growth !== null && $growth > 0)) $fronts[$id]['up']++;
                    if ($growth !== null) $fronts[$id]['growth_values'][] = $growth;
                    $product = sanitize_text_field((string)($row['product'] ?? ''));
                    if ($product !== '') $fronts[$id]['products'][] = ['label'=>$product,'difference'=>round($diff,2),'growth'=>$growth === null ? null : round($growth,1)];
                }
            }
        }
        foreach ($share as &$row) {
            $count = max(1, (int)$row['count']);
            $row['avg_gap'] = round((float)$row['gap_total'] / $count, 1);
            $row['avg_share'] = round((float)$row['share_total'] / $count, 1);
            $row['avg_target'] = round((float)$row['target_total'] / $count, 1);
            usort($row['products'], static fn(array $a,array $b): int => (float)$a['gap'] <=> (float)$b['gap']);
            $row['products'] = array_slice($row['products'], 0, 5);
            unset($row['gap_total'],$row['share_total'],$row['target_total']);
        }
        unset($row);
        foreach ($fronts as &$row) {
            $values = (array)$row['growth_values'];
            $row['difference_total'] = round((float)$row['difference_total'], 2);
            $row['growth_percent'] = $values ? round(array_sum($values) / count($values), 1) : null;
            usort($row['products'], static function(array $a,array $b): int {
                $ag = (float)($a['growth'] ?? 0); $bg = (float)($b['growth'] ?? 0);
                return $ag <=> $bg;
            });
            $row['products'] = array_slice($row['products'], 0, 5);
            unset($row['growth_values']);
        }
        unset($row);
        return ['share'=>$share,'fronts'=>$fronts];
    }

    private static function rankings(array $profiles): array {
        $sort = static function(array $profiles, string $field, bool $desc = true): array {
            usort($profiles, static function(array $a,array $b) use ($field,$desc): int {
                $av = $a[$field] ?? null; $bv = $b[$field] ?? null;
                if ($av === null && $bv === null) return 0;
                if ($av === null) return 1;
                if ($bv === null) return -1;
                $cmp = (float)$av <=> (float)$bv;
                return $desc ? -$cmp : $cmp;
            });
            return array_slice($profiles, 0, 12);
        };
        $deteriorating = array_values(array_filter($profiles, static function(array $p): bool {
            return (($p['trend_delta_pp'] ?? null) !== null && (float)$p['trend_delta_pp'] <= -3)
                || (($p['fronts_growth_percent'] ?? null) !== null && (float)$p['fronts_growth_percent'] < -5)
                || (($p['share_gap_pp'] ?? null) !== null && (float)$p['share_gap_pp'] <= -5);
        }));
        usort($deteriorating, static function(array $a,array $b): int {
            $as = abs(min(0,(float)($a['trend_delta_pp'] ?? 0))) + abs(min(0,(float)($a['share_gap_pp'] ?? 0))) + abs(min(0,(float)($a['fronts_growth_percent'] ?? 0))) * .2;
            $bs = abs(min(0,(float)($b['trend_delta_pp'] ?? 0))) + abs(min(0,(float)($b['share_gap_pp'] ?? 0))) + abs(min(0,(float)($b['fronts_growth_percent'] ?? 0))) * .2;
            return $bs <=> $as;
        });
        $recovered = array_values(array_filter($profiles, static function(array $p): bool {
            $learningImproving = (int)($p['learning']['improving'] ?? 0) > 0;
            return (($p['trend_delta_pp'] ?? null) !== null && (float)$p['trend_delta_pp'] >= 5)
                || (($p['fronts_growth_percent'] ?? null) !== null && (float)$p['fronts_growth_percent'] >= 8)
                || $learningImproving;
        }));
        usort($recovered, static function(array $a,array $b): int {
            $as = max(0,(float)($a['trend_delta_pp'] ?? 0)) + max(0,(float)($a['fronts_growth_percent'] ?? 0)) * .2 + (int)($a['learning']['improving'] ?? 0) * 8;
            $bs = max(0,(float)($b['trend_delta_pp'] ?? 0)) + max(0,(float)($b['fronts_growth_percent'] ?? 0)) * .2 + (int)($b['learning']['improving'] ?? 0) * 8;
            return $bs <=> $as;
        });
        return [
            'priority'=>$sort($profiles, 'priority_score', true),
            'risk'=>$sort($profiles, 'risk_score', true),
            'opportunity'=>$sort($profiles, 'opportunity_score', true),
            'stock_risk'=>$sort(array_values(array_filter($profiles, static fn(array $p): bool => ($p['stock_risk'] ?? null) !== null)), 'stock_risk', true),
            'deteriorating'=>array_slice($deteriorating, 0, 12),
            'recovered'=>array_slice($recovered, 0, 12),
        ];
    }

    private static function stockRisk(array $stock): ?float {
        if (empty($stock['available'])) return null;
        $negative = max(0, (int)($stock['negative_count'] ?? 0));
        $zero = max(0, (int)($stock['zero_count'] ?? 0));
        $low = max(0, (int)($stock['low_count'] ?? 0));
        $products = max(1, (int)($stock['products'] ?? 0));
        $exposure = (($negative * 1.5) + $zero + ($low * .35)) / $products * 100;
        return self::clamp(min(45, $negative * 18) + min(38, $zero * 6) + min(17, $low * 1.8) + min(25, $exposure * .35));
    }

    private static function weighted(array $components): float {
        $sum = 0.0; $weights = 0.0;
        foreach ($components as $component) {
            if (empty($component['available'])) continue;
            $weight = max(0.0, (float)($component['weight'] ?? 0));
            $value = self::clamp((float)($component['value'] ?? 0));
            $sum += $value * $weight; $weights += $weight;
        }
        return $weights > 0 ? self::clamp($sum / $weights) : 0.0;
    }

    private static function addSignal(array &$signals, string $key, string $label, float $score, string $value, string $severity): void {
        $signals[] = ['key'=>$key,'label'=>$label,'score'=>round(self::clamp($score),1),'value'=>sanitize_text_field($value),'severity'=>sanitize_key($severity)];
    }

    private static function reason(int $pending, float $execution, ?float $trendDelta, ?float $shareGap, ?float $frontsGrowth, ?float $stockRisk, array $stock, array $learning, array $signals): string {
        $parts = [];
        if ($pending > 0) $parts[] = $pending . ' visita(s) pendente(s)';
        if ($execution < 85) $parts[] = 'execução ' . number_format_i18n($execution,1) . '%';
        if ($trendDelta !== null && $trendDelta <= -3) $parts[] = 'tendência ' . ($trendDelta > 0 ? '+' : '') . number_format_i18n($trendDelta,1) . ' pp';
        if ($shareGap !== null && $shareGap < 0) $parts[] = 'Share Gap ' . number_format_i18n($shareGap,1) . ' pp';
        if ($frontsGrowth !== null && $frontsGrowth < 0) $parts[] = 'frentes ' . number_format_i18n($frontsGrowth,1) . '%';
        if ($stockRisk !== null && $stockRisk >= 35) $parts[] = (int)($stock['zero_count'] ?? 0) . ' rutura(s) e ' . (int)($stock['low_count'] ?? 0) . ' stock baixo';
        if ((int)($learning['open'] ?? 0) > 0) $parts[] = (int)$learning['open'] . ' recomendação(ões) em aberto';
        if (!$parts && $signals) $parts[] = (string)($signals[0]['label'] ?? 'Sinal operacional') . ' sob acompanhamento';
        return sanitize_text_field(implode(' · ', array_slice($parts, 0, 4)) ?: 'Sem sinais materiais de prioridade no período.');
    }

    private static function suggestedAction(array $signals, int $pending): string {
        $top = sanitize_key((string)($signals[0]['key'] ?? ''));
        if ($top === 'stock') return 'Validar ruturas e stock baixo desta loja, confirmar o snapshot e priorizar reposição quando aplicável.';
        if ($top === 'share_space') return 'Rever Share Space e referências abaixo do objetivo, confirmar evidências e preparar recuperação de visibilidade.';
        if ($top === 'fronts') return 'Rever as referências com perda de frentes e validar a execução na próxima visita.';
        if ($top === 'trend') return 'Comparar a loja com o período anterior e rever as visitas recentes antes de alterar o planeamento.';
        if ($top === 'learning') return 'Rever recomendações anteriores e respetivos outcomes antes de repetir a mesma ação.';
        if ($pending > 0) return 'Priorizar as visitas pendentes desta loja e validar briefing e evidências antes do fecho.';
        return 'Acompanhar a loja no próximo ciclo e confirmar se os sinais permanecem materiais.';
    }

    private static function publicShare(array $share): array {
        if (!$share) return ['available'=>false];
        return [
            'available'=>true,
            'count'=>(int)($share['count'] ?? 0),
            'below_target'=>(int)($share['below_target'] ?? 0),
            'avg_gap'=>isset($share['avg_gap']) ? round((float)$share['avg_gap'],1) : null,
            'avg_share'=>isset($share['avg_share']) ? round((float)$share['avg_share'],1) : null,
            'avg_target'=>isset($share['avg_target']) ? round((float)$share['avg_target'],1) : null,
            'products'=>array_slice((array)($share['products'] ?? []),0,5),
        ];
    }

    private static function publicFronts(array $fronts): array {
        if (!$fronts) return ['available'=>false];
        return [
            'available'=>true,
            'count'=>(int)($fronts['count'] ?? 0),
            'up'=>(int)($fronts['up'] ?? 0),
            'down'=>(int)($fronts['down'] ?? 0),
            'difference_total'=>isset($fronts['difference_total']) ? round((float)$fronts['difference_total'],2) : null,
            'growth_percent'=>isset($fronts['growth_percent']) && $fronts['growth_percent'] !== null ? round((float)$fronts['growth_percent'],1) : null,
            'products'=>array_slice((array)($fronts['products'] ?? []),0,5),
        ];
    }

    private static function publicStock(array $stock): array {
        if (!$stock) return ['available'=>false];
        return [
            'available'=>!empty($stock['available']),
            'snapshot_date'=>sanitize_text_field((string)($stock['snapshot_date'] ?? '')),
            'insignias'=>array_values(array_map('sanitize_text_field',(array)($stock['insignias'] ?? []))),
            'products'=>(int)($stock['products'] ?? 0),
            'stock_total'=>round((float)($stock['stock_total'] ?? 0),2),
            'zero_count'=>(int)($stock['zero_count'] ?? 0),
            'low_count'=>(int)($stock['low_count'] ?? 0),
            'negative_count'=>(int)($stock['negative_count'] ?? 0),
            'risk_products'=>array_slice((array)($stock['risk_products'] ?? []),0,5),
        ];
    }

    private static function trendLabel(?float $delta, int $previousStops, int $currentStops): string {
        if ($currentStops <= 0) return 'no_activity';
        if ($previousStops <= 0 || $delta === null) return 'new_baseline';
        if ($delta >= 5) return 'improving';
        if ($delta <= -5) return 'deteriorating';
        return 'stable';
    }

    private static function emptyLearning(): array {
        return ['total'=>0,'open'=>0,'active'=>0,'accepted'=>0,'deferred'=>0,'resolved'=>0,'not_relevant'=>0,'observed'=>0,'improving'=>0,'stable'=>0,'worsening'=>0,'efficacy_rate'=>0.0,'confidence_avg'=>0.0];
    }

    private static function emptyResult(): array {
        return ['version'=>self::VERSION,'available'=>false,'profiles'=>[],'rankings'=>['priority'=>[],'risk'=>[],'opportunity'=>[],'stock_risk'=>[],'deteriorating'=>[],'recovered'=>[]],'summary'=>['stores'=>0,'high_priority'=>0,'critical_priority'=>0,'high_risk'=>0,'high_opportunity'=>0,'stock_risk'=>0,'deteriorating'=>0,'recovering'=>0,'confidence_avg'=>0.0,'stock_coverage'=>0.0,'learning_coverage'=>0.0],'selected'=>null,'policy'=>self::policy()];
    }

    private static function policy(): array {
        return [
            'causes_inferred'=>false,
            'autonomous_mutations'=>false,
            'adaptive_scoring_enabled'=>false,
            'learning_changes_scores'=>false,
            'learning_influences_confidence_only'=>true,
            'stock_scope_requires_mapped_location'=>true,
            'human_review_required'=>true,
        ];
    }

    private static function clamp(float $value): float { return max(0.0, min(100.0, $value)); }
}
