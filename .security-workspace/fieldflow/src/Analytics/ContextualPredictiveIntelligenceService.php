<?php
namespace RoutesPro\Analytics;

use RoutesPro\Support\Logger;

if (!defined('ABSPATH')) exit;

/**
 * Contextual Predictive Intelligence and Next Best Action.
 *
 * Builds explainable, read-only actions from Store Intelligence evidence.
 * The service never performs database fan-out, never infers causes and never
 * mutates routes, stocks, forms, thresholds or operational scores.
 */
final class ContextualPredictiveIntelligenceService {
    public const VERSION = '1.0.0';
    private const MAX_STORE_ACTIONS = 5;
    private const MAX_PORTFOLIO_ACTIONS = 12;

    public static function enrichStoreIntelligence(array $engine, array $context = [], array $recommendations = []): array {
        if (empty($engine['available']) || empty($engine['profiles']) || !is_array($engine['profiles'])) return $engine;

        try {
            $recommendationMap = self::recommendationsByLocation($recommendations);
            $profiles = [];
            $allActions = [];
            $productNames = [];
            $issueCounts = [];

            foreach ($engine['profiles'] as $profile) {
                if (!is_array($profile)) continue;
                $locationId = absint($profile['location_id'] ?? 0);
                $actions = self::buildActions($profile, $context, $recommendationMap[$locationId] ?? []);
                $primary = $actions[0] ?? null;

                $profile['contextual_actions'] = array_slice($actions, 0, self::MAX_STORE_ACTIONS);
                $profile['next_best_action'] = $primary;
                $profile['contextual_primary_issue'] = is_array($primary) ? (string)($primary['issue_type'] ?? '') : '';
                $profile['contextual_product'] = is_array($primary) ? (string)($primary['product'] ?? '') : '';
                $profile['policy']['contextual_predictive_changes_operational_scores'] = false;
                $profile['policy']['contextual_predictive_is_probability'] = false;
                $profile['policy']['contextual_predictive_causes_inferred'] = false;
                $profiles[] = $profile;

                foreach ($profile['contextual_actions'] as $action) {
                    if (!is_array($action)) continue;
                    $allActions[] = $action;
                    $product = trim((string)($action['product'] ?? ''));
                    if ($product !== '') $productNames[self::normaliseProductKey($product)] = $product;
                    $issue = sanitize_key((string)($action['issue_type'] ?? 'general')) ?: 'general';
                    $issueCounts[$issue] = (int)($issueCounts[$issue] ?? 0) + 1;
                }
            }

            usort($allActions, [self::class, 'compareActions']);
            $nextBest = self::uniquePortfolioActions($allActions, self::MAX_PORTFOLIO_ACTIONS);
            $productActions = array_values(array_filter($nextBest, static fn(array $row): bool => trim((string)($row['product'] ?? '')) !== ''));

            $engine['profiles'] = $profiles;
            $engine['rankings']['next_best_actions'] = $nextBest;
            $engine['rankings']['contextual_product_actions'] = array_slice($productActions, 0, self::MAX_PORTFOLIO_ACTIONS);
            $engine['summary']['contextual_actions'] = count($nextBest);
            $engine['summary']['contextual_product_actions'] = count($productActions);
            $engine['summary']['contextual_products'] = count($productNames);
            $engine['summary']['contextual_issue_types'] = count($issueCounts);
            $engine['contextual_predictive'] = [
                'version'=>self::VERSION,
                'available'=>!empty($nextBest),
                'mode'=>'store_product_issue_next_best_action',
                'summary'=>[
                    'actions'=>count($nextBest),
                    'product_actions'=>count($productActions),
                    'products'=>count($productNames),
                    'issue_types'=>$issueCounts,
                ],
                'next_best_actions'=>$nextBest,
                'policy'=>self::policy(),
            ];
            $engine['policy']['contextual_predictive_enabled'] = !empty($nextBest);
            $engine['policy']['contextual_predictive_changes_operational_scores'] = false;
            $engine['policy']['contextual_predictive_is_probability'] = false;
            $engine['policy']['contextual_predictive_causes_inferred'] = false;
            return $engine;
        } catch (\Throwable $e) {
            if (class_exists(Logger::class)) {
                Logger::warning('Contextual Predictive Intelligence indisponível; Store Intelligence mantém-se operacional.', ['context_key'=>'contextual_predictive_intelligence'], ['error'=>$e->getMessage()]);
            }
            $engine['contextual_predictive'] = self::emptyResult('contextual_enrichment_failed');
            return $engine;
        }
    }

    public static function portfolio(array $engine, int $limit = 8): array {
        $ctx = is_array($engine['contextual_predictive'] ?? null) ? $engine['contextual_predictive'] : [];
        $actions = array_values(array_filter((array)($ctx['next_best_actions'] ?? $engine['rankings']['next_best_actions'] ?? []), 'is_array'));
        if (!$actions) return self::emptyResult('no_contextual_actions');
        $limit = max(1, min(self::MAX_PORTFOLIO_ACTIONS, $limit));
        $actions = array_slice($actions, 0, $limit);
        $products = [];
        $issues = [];
        foreach ($actions as $action) {
            $product = trim((string)($action['product'] ?? ''));
            if ($product !== '') $products[self::normaliseProductKey($product)] = $product;
            $issue = sanitize_key((string)($action['issue_type'] ?? 'general')) ?: 'general';
            $issues[$issue] = (int)($issues[$issue] ?? 0) + 1;
        }
        return [
            'version'=>self::VERSION,
            'available'=>true,
            'mode'=>'store_product_issue_next_best_action',
            'summary'=>[
                'actions'=>count($actions),
                'product_actions'=>count(array_filter($actions, static fn(array $a): bool => trim((string)($a['product'] ?? '')) !== '')),
                'products'=>count($products),
                'issue_types'=>$issues,
            ],
            'next_best_actions'=>$actions,
            'policy'=>self::policy(),
        ];
    }

    private static function buildActions(array $profile, array $context, array $recommendations): array {
        $actions = [];
        $locationId = absint($profile['location_id'] ?? 0);
        $locationName = sanitize_text_field((string)($profile['location_name'] ?? 'PDV'));
        $futureRisk = self::clamp((float)($profile['forecast_risk_score'] ?? $profile['risk_score'] ?? 0));
        $futurePriority = self::clamp((float)($profile['forecast_priority_score'] ?? $profile['adaptive_rank_score'] ?? $profile['priority_score'] ?? 0));
        $baseRisk = self::clamp((float)($profile['risk_score'] ?? 0));
        $confidence = self::clamp((float)($profile['forecast_confidence_score'] ?? $profile['adaptive_confidence_score'] ?? $profile['confidence_score'] ?? 0));
        $forecastDirection = sanitize_key((string)($profile['forecast_direction'] ?? 'stable')) ?: 'stable';
        $forecastDelta = round($futureRisk - $baseRisk, 1);

        $stock = is_array($profile['stock'] ?? null) ? $profile['stock'] : [];
        foreach (array_slice(array_values(array_filter((array)($stock['risk_products'] ?? []), 'is_array')), 0, 5) as $row) {
            $product = sanitize_text_field((string)($row['product_name'] ?? $row['article_ref'] ?? 'Produto'));
            $qty = is_numeric($row['stock_total'] ?? null) ? (float)$row['stock_total'] : null;
            if ($product === '' || $qty === null) continue;
            $signal = $qty < 0 ? 98.0 : ($qty == 0.0 ? 92.0 : 78.0);
            $issue = $qty < 0 ? 'stock_negative' : ($qty == 0.0 ? 'stockout' : 'stock_low');
            $actionText = $qty <= 0
                ? 'Validar o stock físico e a disponibilidade de ' . $product . ' na próxima intervenção. Se o sinal se confirmar, registar evidência e seguir o fluxo de reposição ou encomenda definido para o projeto.'
                : 'Validar cobertura de stock e exposição de ' . $product . ' na próxima visita, confirmando se o nível observado continua material antes de qualquer ação.';
            $evidence = [
                self::evidence('stock_snapshot', 'Stock observado', number_format_i18n($qty, 2), 'observed'),
                self::evidence('future_risk', 'Risco futuro da loja', number_format_i18n($futureRisk, 1) . '/100', 'projected'),
            ];
            $actions[] = self::action($locationId, $locationName, $product, $issue, $actionText, $signal, $futurePriority, $futureRisk, $confidence, $forecastDirection, $forecastDelta, $evidence, $context);
        }

        $share = is_array($profile['share'] ?? null) ? $profile['share'] : [];
        foreach (array_slice(array_values(array_filter((array)($share['products'] ?? []), 'is_array')), 0, 5) as $row) {
            $product = sanitize_text_field((string)($row['label'] ?? 'Produto'));
            $gap = is_numeric($row['gap'] ?? null) ? (float)$row['gap'] : null;
            if ($product === '' || $gap === null || $gap >= 0) continue;
            $signal = self::clamp(48.0 + (abs($gap) * 7.0));
            $actionText = 'Rever o Share Space de ' . $product . ' na próxima visita, validar exposição e concorrência e registar a nova medição antes de concluir a intervenção.';
            $evidence = [
                self::evidence('share_gap', 'Share Gap observado', number_format_i18n($gap, 1) . ' pp', 'observed'),
                self::evidence('future_risk', 'Risco futuro da loja', number_format_i18n($futureRisk, 1) . '/100', 'projected'),
            ];
            $actions[] = self::action($locationId, $locationName, $product, 'share_space_gap', $actionText, $signal, $futurePriority, $futureRisk, $confidence, $forecastDirection, $forecastDelta, $evidence, $context);
        }

        $fronts = is_array($profile['fronts'] ?? null) ? $profile['fronts'] : [];
        foreach (array_slice(array_values(array_filter((array)($fronts['products'] ?? []), 'is_array')), 0, 5) as $row) {
            $product = sanitize_text_field((string)($row['label'] ?? 'Produto'));
            $difference = is_numeric($row['difference'] ?? null) ? (float)$row['difference'] : null;
            $growth = is_numeric($row['growth'] ?? null) ? (float)$row['growth'] : null;
            $declining = ($difference !== null && $difference < 0) || ($growth !== null && $growth < 0);
            if ($product === '' || !$declining) continue;
            $pressure = max(abs(min(0.0, (float)($difference ?? 0))) * 12.0, abs(min(0.0, (float)($growth ?? 0))) * 1.9);
            $signal = self::clamp(46.0 + $pressure);
            $actionText = 'Validar as frentes de ' . $product . ' na próxima visita e comparar a evidência antes/depois. Se a redução continuar, priorizar a recuperação de exposição dentro das regras da campanha.';
            $evidence = [];
            if ($difference !== null) $evidence[] = self::evidence('fronts_difference', 'Diferença de frentes', number_format_i18n($difference, 1), 'observed');
            if ($growth !== null) $evidence[] = self::evidence('fronts_growth', 'Evolução de frentes', number_format_i18n($growth, 1) . '%', 'observed');
            $evidence[] = self::evidence('future_risk', 'Risco futuro da loja', number_format_i18n($futureRisk, 1) . '/100', 'projected');
            $actions[] = self::action($locationId, $locationName, $product, 'fronts_decline', $actionText, $signal, $futurePriority, $futureRisk, $confidence, $forecastDirection, $forecastDelta, $evidence, $context);
        }

        $pending = max(0, (int)($profile['pending_stops'] ?? 0));
        $execution = self::clamp((float)($profile['execution_score'] ?? $profile['done_rate'] ?? 0));
        if ($pending > 0 || $execution < 85.0) {
            $signal = self::clamp(max(45.0, (100.0 - $execution) + 48.0 + min(18.0, $pending * 4.0)));
            $actionText = $pending > 0
                ? 'Priorizar as visitas pendentes desta loja e validar briefing, evidências e campos obrigatórios antes do fecho do próximo ciclo.'
                : 'Rever a execução recente desta loja e confirmar se existe uma visita ou evidência operacional que necessite de acompanhamento no próximo ciclo.';
            $evidence = [
                self::evidence('execution', 'Execução observada', number_format_i18n($execution, 1) . '%', 'observed'),
                self::evidence('pending_stops', 'Visitas pendentes', (string)$pending, 'observed'),
                self::evidence('future_risk', 'Risco futuro da loja', number_format_i18n($futureRisk, 1) . '/100', 'projected'),
            ];
            $actions[] = self::action($locationId, $locationName, '', 'execution_followup', $actionText, $signal, $futurePriority, $futureRisk, $confidence, $forecastDirection, $forecastDelta, $evidence, $context);
        }

        foreach (array_slice(array_values(array_filter($recommendations, 'is_array')), 0, 4) as $rec) {
            if (in_array((string)($rec['status'] ?? ''), ['resolved','not_relevant'], true)) continue;
            $title = sanitize_text_field((string)($rec['title'] ?? 'Recomendação FieldFlow'));
            $actionText = sanitize_text_field((string)($rec['suggested_action'] ?? 'Rever a recomendação antes da próxima intervenção.'));
            $signal = self::clamp((float)($rec['adaptive_rank_score'] ?? $rec['priority_score'] ?? 55));
            $recConfidence = self::clamp((float)($rec['adaptive_confidence_score'] ?? $rec['confidence_score'] ?? $confidence));
            $type = sanitize_key((string)($rec['type'] ?? $rec['recommendation_type'] ?? 'recommendation_followup')) ?: 'recommendation_followup';
            $evidence = [
                self::evidence('recommendation', 'Recomendação ativa', $title, 'observed'),
                self::evidence('future_risk', 'Risco futuro da loja', number_format_i18n($futureRisk, 1) . '/100', 'projected'),
            ];
            $actions[] = self::action($locationId, $locationName, '', $type, $actionText, $signal, $futurePriority, $futureRisk, max($confidence, $recConfidence), $forecastDirection, $forecastDelta, $evidence, $context, $title);
        }

        if (!$actions && ($forecastDirection === 'deteriorating' || $futureRisk >= 65.0)) {
            $evidence = [
                self::evidence('forecast_direction', 'Direção prevista', $forecastDirection, 'projected'),
                self::evidence('future_risk', 'Risco futuro da loja', number_format_i18n($futureRisk, 1) . '/100', 'projected'),
            ];
            $actions[] = self::action(
                $locationId,
                $locationName,
                '',
                'forecast_watch',
                'Rever os sinais atuais desta loja antes da próxima janela e confirmar em campo se a tendência prevista continua material. Não alterar planeamento sem validação humana.',
                max(55.0, $futureRisk),
                $futurePriority,
                $futureRisk,
                $confidence,
                $forecastDirection,
                $forecastDelta,
                $evidence,
                $context
            );
        }

        $actions = self::deduplicateActions($actions);
        usort($actions, [self::class, 'compareActions']);
        return array_slice($actions, 0, self::MAX_STORE_ACTIONS);
    }

    private static function action(
        int $locationId,
        string $locationName,
        string $product,
        string $issueType,
        string $actionText,
        float $signalScore,
        float $futurePriority,
        float $futureRisk,
        float $confidence,
        string $forecastDirection,
        float $forecastDelta,
        array $evidence,
        array $context,
        string $customTitle = ''
    ): array {
        $rank = self::clamp(($signalScore * .42) + ($futurePriority * .30) + ($futureRisk * .18) + ($confidence * .10));
        $coverage = min(100.0, 42.0 + (count($evidence) * 14.0) + ($product !== '' ? 12.0 : 0.0));
        $actionConfidence = self::clamp(($confidence * .72) + ($coverage * .28));
        $urgency = $rank >= 86 ? 'critical' : ($rank >= 72 ? 'high' : ($rank >= 52 ? 'medium' : 'monitor'));
        $issueType = sanitize_key($issueType) ?: 'general';
        $issueLabel = self::issueLabel($issueType);
        $title = $customTitle !== '' ? sanitize_text_field($customTitle) : $issueLabel . ($product !== '' ? ' · ' . $product : '');
        $evidenceLabels = array_values(array_filter(array_map(static fn(array $row): string => sanitize_text_field((string)($row['label'] ?? '')), $evidence)));
        $reason = 'Prioridade assistida por ' . implode(', ', array_slice($evidenceLabels, 0, 3)) . '. A direção futura é ' . $forecastDirection . ' e não representa probabilidade nem prova causalidade.';
        $idSeed = implode('|', [$locationId, $issueType, self::normaliseProductKey($product), $actionText]);

        return [
            'id'=>'ctx_' . substr(hash('sha256', $idSeed), 0, 16),
            'location_id'=>$locationId,
            'location_name'=>$locationName,
            'product'=>sanitize_text_field($product),
            'issue_type'=>$issueType,
            'issue_label'=>$issueLabel,
            'title'=>$title,
            'action'=>sanitize_text_field($actionText),
            'urgency'=>$urgency,
            'rank_score'=>round($rank, 1),
            'confidence_score'=>round($actionConfidence, 1),
            'forecast_risk_score'=>round($futureRisk, 1),
            'forecast_priority_score'=>round($futurePriority, 1),
            'forecast_direction'=>$forecastDirection,
            'forecast_risk_delta'=>round($forecastDelta, 1),
            'evidence'=>$evidence,
            'reason'=>sanitize_text_field($reason),
            'scope'=>[
                'client_id'=>absint($context['client_id'] ?? 0),
                'project_id'=>absint($context['project_id'] ?? 0),
                'route_id'=>absint($context['route_id'] ?? 0),
                'user_id'=>absint($context['user_id'] ?? 0),
                'location_id'=>$locationId,
            ],
            'policy'=>self::policy(),
        ];
    }

    private static function evidence(string $key, string $label, string $value, string $kind): array {
        return [
            'key'=>sanitize_key($key),
            'label'=>sanitize_text_field($label),
            'value'=>sanitize_text_field($value),
            'kind'=>in_array($kind, ['observed','projected'], true) ? $kind : 'observed',
        ];
    }

    private static function recommendationsByLocation(array $recommendations): array {
        $map = [];
        foreach ($recommendations as $rec) {
            if (!is_array($rec)) continue;
            $id = absint($rec['location_id'] ?? 0);
            if ($id <= 0) continue;
            $map[$id][] = $rec;
        }
        return $map;
    }

    private static function deduplicateActions(array $actions): array {
        $out = [];
        $seen = [];
        foreach ($actions as $action) {
            if (!is_array($action)) continue;
            $key = sanitize_key((string)($action['issue_type'] ?? 'general')) . '|' . self::normaliseProductKey((string)($action['product'] ?? ''));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $action;
        }
        return $out;
    }

    private static function uniquePortfolioActions(array $actions, int $limit): array {
        $out = [];
        $seen = [];
        foreach ($actions as $action) {
            if (!is_array($action)) continue;
            $locationId = absint($action['location_id'] ?? 0);
            $issue = sanitize_key((string)($action['issue_type'] ?? 'general')) ?: 'general';
            $product = self::normaliseProductKey((string)($action['product'] ?? ''));
            $key = $locationId . '|' . $issue . '|' . $product;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $action;
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    private static function compareActions(array $a, array $b): int {
        $rank = (float)($b['rank_score'] ?? 0) <=> (float)($a['rank_score'] ?? 0);
        if ($rank !== 0) return $rank;
        $confidence = (float)($b['confidence_score'] ?? 0) <=> (float)($a['confidence_score'] ?? 0);
        if ($confidence !== 0) return $confidence;
        return strnatcasecmp((string)($a['location_name'] ?? ''), (string)($b['location_name'] ?? ''));
    }

    private static function issueLabel(string $issueType): string {
        $map = [
            'stock_negative'=>'Stock negativo',
            'stockout'=>'Rutura de stock',
            'stock_low'=>'Stock baixo',
            'share_space_gap'=>'Share Space abaixo do objetivo',
            'fronts_decline'=>'Redução de frentes',
            'execution_followup'=>'Execução a acompanhar',
            'forecast_watch'=>'Risco futuro a acompanhar',
            'recommendation_followup'=>'Recomendação a acompanhar',
        ];
        if (isset($map[$issueType])) return $map[$issueType];
        return ucwords(str_replace('_', ' ', sanitize_key($issueType) ?: 'Ação recomendada'));
    }

    private static function normaliseProductKey(string $product): string {
        $product = strtolower(remove_accents(trim($product)));
        $product = preg_replace('/[^a-z0-9]+/', '-', $product) ?: '';
        return trim($product, '-');
    }

    private static function emptyResult(string $reason): array {
        return [
            'version'=>self::VERSION,
            'available'=>false,
            'reason'=>$reason,
            'mode'=>'unavailable',
            'summary'=>['actions'=>0,'product_actions'=>0,'products'=>0,'issue_types'=>[]],
            'next_best_actions'=>[],
            'policy'=>self::policy(),
        ];
    }

    private static function policy(): array {
        return [
            'contextual_predictive_enabled'=>true,
            'store_product_issue_context'=>true,
            'is_probability_model'=>false,
            'causes_inferred'=>false,
            'autonomous_mutations'=>false,
            'operational_scores_mutated'=>false,
            'routes_mutated'=>false,
            'stocks_mutated'=>false,
            'forms_mutated'=>false,
            'human_review_required'=>true,
            'observed_and_projected_evidence_separated'=>true,
            'no_database_fanout'=>true,
        ];
    }

    private static function clamp(float $value): float {
        return max(0.0, min(100.0, $value));
    }
}
