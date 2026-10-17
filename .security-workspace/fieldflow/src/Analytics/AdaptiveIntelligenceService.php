<?php
namespace RoutesPro\Analytics;

use RoutesPro\Support\Logger;

if (!defined('ABSPATH')) exit;

/**
 * FieldFlow Adaptive Intelligence Foundation.
 *
 * Reads the auditable Recommendation Learning dataset and derives bounded,
 * explainable advisory signals. It can reorder recommendations and Store
 * Intelligence rankings, and can adjust presentation confidence within a
 * small guardrail. It never changes Risk, Opportunity, Health, routes, stock,
 * forms, thresholds or persisted recommendation confidence autonomously.
 */
final class AdaptiveIntelligenceService {
    public const VERSION = '1.0.0';
    private const PRIOR_SUCCESSES = 2.0;
    private const PRIOR_TRIALS = 4.0;
    private const MAX_CONFIDENCE_DELTA = 8.0;
    private const MAX_RANK_DELTA = 10.0;

    public static function analyse(array $context = [], array $recommendations = [], int $patternLimit = 12): array {
        $clientId = absint($context['client_id'] ?? 0);
        if ($clientId <= 0 || !class_exists(RecommendationLearningService::class) || !RecommendationLearningService::tablesReady()) {
            return self::emptyResult($clientId <= 0 ? 'client_scope_required' : 'learning_dataset_unavailable');
        }

        $patterns = self::patterns($context, max(4, min(50, $patternLimit)));
        $adapted = self::adaptRecommendations($recommendations, $patterns);
        $nextBest = self::nextBestActions($adapted, 6);

        $weightedEffectiveness = 0.0;
        $weightedRelevance = 0.0;
        $weight = 0.0;
        $established = 0;
        foreach ($patterns as $pattern) {
            $w = max(1.0, (float)($pattern['evidence_count'] ?? 0));
            $weightedEffectiveness += (float)($pattern['effectiveness_score'] ?? 50) * $w;
            $weightedRelevance += (float)($pattern['human_relevance_score'] ?? 50) * $w;
            $weight += $w;
            if (($pattern['maturity'] ?? '') === 'established') $established++;
        }

        return [
            'version'=>self::VERSION,
            'available'=>true,
            'scope'=>self::scopeLabel($context),
            'summary'=>[
                'patterns'=>count($patterns),
                'established_patterns'=>$established,
                'effectiveness_avg'=>$weight > 0 ? round($weightedEffectiveness / $weight, 1) : 50.0,
                'human_relevance_avg'=>$weight > 0 ? round($weightedRelevance / $weight, 1) : 50.0,
                'recommendations_ranked'=>count($adapted),
                'next_best_actions'=>count($nextBest),
            ],
            'patterns'=>$patterns,
            'recommendations'=>$adapted,
            'next_best_actions'=>$nextBest,
            'policy'=>self::policy(),
        ];
    }

    /**
     * Aggregate evidence by recommendation type/source within the selected
     * client/project. No evidence is ever mixed across clients.
     */
    public static function patterns(array $context = [], int $limit = 20): array {
        global $wpdb;
        if (!class_exists(RecommendationLearningService::class) || !RecommendationLearningService::tablesReady()) return [];
        $clientId = absint($context['client_id'] ?? 0);
        if ($clientId <= 0) return [];

        $where = ['client_id=%d'];
        $args = [$clientId];
        $projectId = absint($context['project_id'] ?? 0);
        if ($projectId > 0) { $where[] = 'project_id=%d'; $args[] = $projectId; }

        $sql = 'SELECT recommendation_type, source_key, COUNT(*) total,'
            . ' SUM(occurrence_count) occurrences,'
            . " SUM(CASE WHEN status IN ('accepted','resolved') THEN 1 ELSE 0 END) human_positive,"
            . " SUM(CASE WHEN status='not_relevant' THEN 1 ELSE 0 END) human_negative,"
            . " SUM(CASE WHEN outcome_status='improving' THEN 1 ELSE 0 END) improving,"
            . " SUM(CASE WHEN outcome_status='stable' THEN 1 ELSE 0 END) stable,"
            . " SUM(CASE WHEN outcome_status='worsening' THEN 1 ELSE 0 END) worsening,"
            . ' AVG(confidence_score) confidence_avg, MAX(last_seen_at) last_seen_at'
            . ' FROM ' . RecommendationLearningService::table()
            . ' WHERE ' . implode(' AND ', $where)
            . ' GROUP BY recommendation_type, source_key'
            . ' ORDER BY (SUM(CASE WHEN outcome_status IN (\'improving\',\'stable\',\'worsening\') THEN 1 ELSE 0 END)'
            . " + SUM(CASE WHEN status IN ('accepted','resolved','not_relevant') THEN 1 ELSE 0 END)) DESC, total DESC"
            . ' LIMIT ' . max(1, min(100, $limit));

        try {
            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
        } catch (\Throwable $e) {
            if (class_exists(Logger::class)) Logger::warning('Adaptive Intelligence não conseguiu agregar padrões.', ['context_key'=>'adaptive_intelligence_patterns'], ['error'=>$e->getMessage()]);
            return [];
        }

        $out = [];
        foreach ($rows as $row) $out[] = self::patternFromAggregate($row);
        return $out;
    }

    /**
     * One grouped query for all requested stores. This is intentionally safe to
     * call from the isolated Store Intelligence endpoint.
     */
    public static function locationEvidence(array $locationIds, array $context = []): array {
        global $wpdb;
        if (!class_exists(RecommendationLearningService::class) || !RecommendationLearningService::tablesReady()) return [];
        $clientId = absint($context['client_id'] ?? 0);
        $ids = array_values(array_unique(array_filter(array_map('absint', $locationIds))));
        if ($clientId <= 0 || !$ids) return [];

        $where = ['client_id=%d', 'location_id IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')'];
        $args = array_merge([$clientId], $ids);
        $projectId = absint($context['project_id'] ?? 0);
        if ($projectId > 0) { $where[] = 'project_id=%d'; $args[] = $projectId; }

        $sql = 'SELECT location_id, COUNT(*) total, SUM(occurrence_count) occurrences,'
            . " SUM(CASE WHEN status IN ('accepted','resolved') THEN 1 ELSE 0 END) human_positive,"
            . " SUM(CASE WHEN status='not_relevant' THEN 1 ELSE 0 END) human_negative,"
            . " SUM(CASE WHEN outcome_status='improving' THEN 1 ELSE 0 END) improving,"
            . " SUM(CASE WHEN outcome_status='stable' THEN 1 ELSE 0 END) stable,"
            . " SUM(CASE WHEN outcome_status='worsening' THEN 1 ELSE 0 END) worsening,"
            . ' AVG(confidence_score) confidence_avg, MAX(last_seen_at) last_seen_at'
            . ' FROM ' . RecommendationLearningService::table()
            . ' WHERE ' . implode(' AND ', $where) . ' GROUP BY location_id';
        try {
            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
        } catch (\Throwable $e) {
            if (class_exists(Logger::class)) Logger::warning('Adaptive Intelligence não conseguiu agregar evidência por loja.', ['context_key'=>'adaptive_intelligence_locations'], ['error'=>$e->getMessage()]);
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $id = absint($row['location_id'] ?? 0);
            if ($id <= 0) continue;
            $out[$id] = self::evidenceFromAggregate($row);
        }
        return $out;
    }

    public static function adaptRecommendations(array $recommendations, array $patterns): array {
        if (!$recommendations) return [];
        $map = [];
        $typeFallback = [];
        foreach ($patterns as $pattern) {
            if (!is_array($pattern)) continue;
            $type = sanitize_key((string)($pattern['recommendation_type'] ?? ''));
            $source = sanitize_key((string)($pattern['source_key'] ?? ''));
            if ($type === '') continue;
            $map[$type . '|' . $source] = $pattern;
            if (!isset($typeFallback[$type]) || (int)($pattern['evidence_count'] ?? 0) > (int)($typeFallback[$type]['evidence_count'] ?? 0)) {
                $typeFallback[$type] = $pattern;
            }
        }

        $out = [];
        foreach ($recommendations as $rec) {
            if (!is_array($rec)) continue;
            $type = sanitize_key((string)($rec['type'] ?? $rec['recommendation_type'] ?? ''));
            $source = sanitize_key((string)($rec['source_key'] ?? ''));
            $pattern = $map[$type . '|' . $source] ?? ($typeFallback[$type] ?? null);
            $baseConfidence = self::clamp((float)($rec['confidence_score'] ?? 0));
            $severity = sanitize_key((string)($rec['severity'] ?? 'medium'));
            $severityBase = ['critical'=>96.0,'high'=>78.0,'medium'=>58.0,'low'=>38.0][$severity] ?? 58.0;
            $deltaConfidence = is_array($pattern) ? (float)($pattern['confidence_delta'] ?? 0) : 0.0;
            $deltaRank = is_array($pattern) ? (float)($pattern['rank_delta'] ?? 0) : 0.0;
            $adaptiveConfidence = self::clamp($baseConfidence + $deltaConfidence);
            $rank = self::clamp(($severityBase * .62) + ($adaptiveConfidence * .38) + $deltaRank);

            $rec['base_confidence_score'] = round($baseConfidence, 1);
            $rec['adaptive_confidence_score'] = round($adaptiveConfidence, 1);
            $rec['adaptive_rank_score'] = round($rank, 1);
            $rec['adaptive'] = is_array($pattern) ? [
                'evidence_count'=>(int)($pattern['evidence_count'] ?? 0),
                'effectiveness_score'=>(float)($pattern['effectiveness_score'] ?? 50),
                'human_relevance_score'=>(float)($pattern['human_relevance_score'] ?? 50),
                'maturity'=>(string)($pattern['maturity'] ?? 'initial'),
                'confidence_delta'=>round($deltaConfidence,1),
                'rank_delta'=>round($deltaRank,1),
                'explanation'=>(string)($pattern['explanation'] ?? ''),
            ] : self::emptyEvidence();
            $out[] = $rec;
        }

        usort($out, static function(array $a, array $b): int {
            $rank = (float)($b['adaptive_rank_score'] ?? 0) <=> (float)($a['adaptive_rank_score'] ?? 0);
            if ($rank !== 0) return $rank;
            return (float)($b['adaptive_confidence_score'] ?? 0) <=> (float)($a['adaptive_confidence_score'] ?? 0);
        });
        return $out;
    }

    public static function enrichStoreIntelligence(array $engine, array $context = []): array {
        if (empty($engine['available']) || empty($engine['profiles']) || !is_array($engine['profiles'])) return $engine;
        $ids = array_values(array_filter(array_map(static fn(array $p): int => absint($p['location_id'] ?? 0), $engine['profiles'])));
        $evidence = self::locationEvidence($ids, $context);
        $covered = 0;
        $effectiveness = 0.0;
        $profiles = [];
        foreach ($engine['profiles'] as $profile) {
            if (!is_array($profile)) continue;
            $id = absint($profile['location_id'] ?? 0);
            $ev = $evidence[$id] ?? self::emptyEvidence();
            $has = (int)($ev['evidence_count'] ?? 0) > 0;
            if ($has) { $covered++; $effectiveness += (float)($ev['effectiveness_score'] ?? 50); }
            $baseConfidence = self::clamp((float)($profile['confidence_score'] ?? 0));
            $basePriority = self::clamp((float)($profile['priority_score'] ?? 0));
            $profile['base_confidence_score'] = round($baseConfidence,1);
            $profile['adaptive_confidence_score'] = round(self::clamp($baseConfidence + (float)($ev['confidence_delta'] ?? 0)),1);
            $profile['base_priority_score'] = round($basePriority,1);
            $profile['adaptive_rank_score'] = round(self::clamp($basePriority + (float)($ev['rank_delta'] ?? 0)),1);
            $profile['adaptive'] = $ev;
            $profile['policy']['adaptive_changes_operational_scores'] = false;
            $profile['policy']['adaptive_changes_store_ranking_only'] = true;
            $profiles[] = $profile;
        }

        $engine['profiles'] = $profiles;
        $basePriority = array_values((array)($engine['rankings']['priority'] ?? []));
        $adaptivePriority = $profiles;
        usort($adaptivePriority, static function(array $a, array $b): int {
            $rank = (float)($b['adaptive_rank_score'] ?? $b['priority_score'] ?? 0) <=> (float)($a['adaptive_rank_score'] ?? $a['priority_score'] ?? 0);
            if ($rank !== 0) return $rank;
            return strnatcasecmp((string)($a['location_name'] ?? ''), (string)($b['location_name'] ?? ''));
        });
        $engine['rankings']['base_priority'] = $basePriority;
        $engine['rankings']['priority'] = array_slice($adaptivePriority, 0, max(12, count($basePriority)));
        $engine['rankings']['adaptive_priority'] = $engine['rankings']['priority'];
        $total = count($profiles);
        $engine['summary']['adaptive_coverage'] = $total > 0 ? round(($covered / $total) * 100, 1) : 0.0;
        $engine['summary']['adaptive_effectiveness_avg'] = $covered > 0 ? round($effectiveness / $covered, 1) : 50.0;
        $engine['adaptive'] = [
            'version'=>self::VERSION,
            'covered_stores'=>$covered,
            'total_stores'=>$total,
            'policy'=>self::policy(),
        ];
        $engine['policy']['adaptive_intelligence_enabled'] = true;
        $engine['policy']['adaptive_changes_operational_scores'] = false;
        $engine['policy']['adaptive_changes_store_ranking_only'] = true;
        return $engine;
    }

    public static function nextBestActions(array $recommendations, int $limit = 6): array {
        $out = [];
        foreach ($recommendations as $rec) {
            if (!is_array($rec)) continue;
            if (in_array((string)($rec['status'] ?? ''), ['resolved','not_relevant'], true)) continue;
            $out[] = [
                'recommendation_id'=>absint($rec['id'] ?? 0),
                'location_id'=>absint($rec['location_id'] ?? 0),
                'title'=>sanitize_text_field((string)($rec['title'] ?? 'Recomendação FieldFlow')),
                'action'=>sanitize_text_field((string)($rec['suggested_action'] ?? 'Rever o contexto antes de atuar.')),
                'severity'=>sanitize_key((string)($rec['severity'] ?? 'medium')),
                'rank_score'=>round((float)($rec['adaptive_rank_score'] ?? 0),1),
                'confidence_score'=>round((float)($rec['adaptive_confidence_score'] ?? $rec['confidence_score'] ?? 0),1),
                'evidence_count'=>(int)($rec['adaptive']['evidence_count'] ?? 0),
                'maturity'=>sanitize_key((string)($rec['adaptive']['maturity'] ?? 'initial')),
                'explanation'=>sanitize_text_field((string)($rec['adaptive']['explanation'] ?? 'Sem histórico suficiente; prioridade baseada no motor determinístico atual.')),
            ];
        }
        usort($out, static fn(array $a, array $b): int => ((float)$b['rank_score'] <=> (float)$a['rank_score']) ?: ((float)$b['confidence_score'] <=> (float)$a['confidence_score']));
        return array_slice($out, 0, max(1, min(20, $limit)));
    }

    private static function patternFromAggregate(array $row): array {
        $evidence = self::evidenceFromAggregate($row);
        return array_merge([
            'recommendation_type'=>sanitize_key((string)($row['recommendation_type'] ?? 'signal')),
            'source_key'=>sanitize_key((string)($row['source_key'] ?? '')),
            'label'=>self::patternLabel((string)($row['recommendation_type'] ?? ''), (string)($row['source_key'] ?? '')),
        ], $evidence);
    }

    private static function evidenceFromAggregate(array $row): array {
        $improving = max(0, (int)($row['improving'] ?? 0));
        $stable = max(0, (int)($row['stable'] ?? 0));
        $worsening = max(0, (int)($row['worsening'] ?? 0));
        $observed = $improving + $stable + $worsening;
        $humanPositive = max(0, (int)($row['human_positive'] ?? 0));
        $humanNegative = max(0, (int)($row['human_negative'] ?? 0));
        $humanDecisive = $humanPositive + $humanNegative;
        $evidenceCount = $observed + $humanDecisive;

        $effectiveness = (($improving + ($stable * .5)) + self::PRIOR_SUCCESSES) / ($observed + self::PRIOR_TRIALS) * 100;
        $relevance = ($humanPositive + self::PRIOR_SUCCESSES) / ($humanDecisive + self::PRIOR_TRIALS) * 100;
        $maturityFactor = min(1.0, $evidenceCount / 12.0);
        $combined = ($effectiveness * .65) + ($relevance * .35);
        $confidenceDelta = self::bound(($combined - 50.0) * .18 * $maturityFactor, self::MAX_CONFIDENCE_DELTA);
        $rankDelta = self::bound(($combined - 50.0) * .22 * $maturityFactor, self::MAX_RANK_DELTA);
        $maturity = $evidenceCount >= 12 ? 'established' : ($evidenceCount >= 5 ? 'learning' : 'initial');

        return [
            'total'=>(int)($row['total'] ?? 0),
            'occurrences'=>(int)($row['occurrences'] ?? 0),
            'observed'=>$observed,
            'improving'=>$improving,
            'stable'=>$stable,
            'worsening'=>$worsening,
            'human_positive'=>$humanPositive,
            'human_negative'=>$humanNegative,
            'evidence_count'=>$evidenceCount,
            'effectiveness_score'=>round($effectiveness,1),
            'human_relevance_score'=>round($relevance,1),
            'confidence_delta'=>round($confidenceDelta,1),
            'rank_delta'=>round($rankDelta,1),
            'maturity'=>$maturity,
            'base_confidence_avg'=>round((float)($row['confidence_avg'] ?? 0),1),
            'last_seen_at'=>(string)($row['last_seen_at'] ?? ''),
            'explanation'=>self::explanation($observed, $humanDecisive, $effectiveness, $relevance, $maturity),
        ];
    }

    private static function explanation(int $observed, int $humanDecisive, float $effectiveness, float $relevance, string $maturity): string {
        if ($observed === 0 && $humanDecisive === 0) return 'Ainda não existe feedback ou outcome suficiente para adaptar esta recomendação.';
        return sprintf(
            'Evidência %s: %d outcome(s), %d decisão(ões) humanas, eficácia suavizada %.1f%% e relevância humana %.1f%%. O ajuste é limitado e não prova causalidade.',
            $maturity,
            $observed,
            $humanDecisive,
            $effectiveness,
            $relevance
        );
    }

    private static function patternLabel(string $type, string $source): string {
        $type = str_replace('_', ' ', sanitize_key($type));
        $source = str_replace('_', ' ', sanitize_key($source));
        $label = ucwords($type ?: 'Recomendação');
        return $source !== '' ? $label . ' · ' . $source : $label;
    }

    private static function scopeLabel(array $context): array {
        return [
            'client_id'=>absint($context['client_id'] ?? 0),
            'project_id'=>absint($context['project_id'] ?? 0),
            'location_id'=>absint($context['location_id'] ?? 0),
            'policy'=>'client_isolated',
        ];
    }

    private static function emptyResult(string $reason): array {
        return [
            'version'=>self::VERSION,
            'available'=>false,
            'reason'=>$reason,
            'summary'=>['patterns'=>0,'established_patterns'=>0,'effectiveness_avg'=>50.0,'human_relevance_avg'=>50.0,'recommendations_ranked'=>0,'next_best_actions'=>0],
            'patterns'=>[],
            'recommendations'=>[],
            'next_best_actions'=>[],
            'policy'=>self::policy(),
        ];
    }

    private static function emptyEvidence(): array {
        return [
            'evidence_count'=>0,
            'effectiveness_score'=>50.0,
            'human_relevance_score'=>50.0,
            'confidence_delta'=>0.0,
            'rank_delta'=>0.0,
            'maturity'=>'initial',
            'explanation'=>'Sem histórico suficiente; sem ajuste adaptativo.',
        ];
    }

    private static function policy(): array {
        return [
            'adaptive_intelligence_enabled'=>true,
            'cross_client_learning'=>false,
            'causes_inferred'=>false,
            'autonomous_mutations'=>false,
            'operational_scores_mutated'=>false,
            'recommendation_ranking_adaptive'=>true,
            'store_ranking_adaptive'=>true,
            'confidence_adjustment_bounded'=>true,
            'max_confidence_delta'=>self::MAX_CONFIDENCE_DELTA,
            'max_rank_delta'=>self::MAX_RANK_DELTA,
            'human_review_required'=>true,
        ];
    }

    private static function bound(float $value, float $limit): float {
        return max(-$limit, min($limit, $value));
    }

    private static function clamp(float $value): float {
        return max(0.0, min(100.0, $value));
    }
}
