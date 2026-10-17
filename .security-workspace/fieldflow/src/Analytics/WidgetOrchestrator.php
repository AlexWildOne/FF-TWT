<?php
namespace RoutesPro\Analytics;

if (!defined('ABSPATH')) exit;

/**
 * FieldFlow Widget Intelligence.
 *
 * Selects a compact, explainable set of widgets for each audience from the
 * existing Analytics registry. It never invents metrics and never mutates
 * operational data. Full exports keep every metric and receive recommendation
 * metadata only.
 */
final class WidgetOrchestrator {
    public const VERSION = '1.0.0';

    public static function settingsForDashboard(array $dashboard): array {
        $aiSettings = $dashboard['ai_settings'] ?? null;
        if (!is_array($aiSettings)) {
            $decoded = json_decode((string)($dashboard['ai_settings_json'] ?? ''), true);
            $aiSettings = is_array($decoded) ? $decoded : [];
        }
        $raw = is_array($aiSettings['widget_orchestration'] ?? null) ? $aiSettings['widget_orchestration'] : [];
        $mode = sanitize_key((string)($raw['mode'] ?? 'automatic'));
        if (!in_array($mode, ['automatic','assisted','manual'], true)) $mode = 'automatic';
        return [
            'mode' => $mode,
            'max_client' => max(3, min(12, absint($raw['max_client'] ?? 7))),
            'max_merchandiser' => max(3, min(12, absint($raw['max_merchandiser'] ?? 6))),
            'max_email' => max(3, min(8, absint($raw['max_email'] ?? 5))),
            'alert_boost' => array_key_exists('alert_boost', $raw) ? !empty($raw['alert_boost']) : true,
            'version' => self::VERSION,
        ];
    }

    /**
     * @return array{metrics:array,recommendations:array,mode_summary:array,version:string}
     */
    public static function orchestrate(array $metrics, array $dashboards, string $audience = 'client', array $context = [], bool $preserveAll = false, array $intelligence = []): array {
        $audience = self::audience($audience);
        if (!self::scoringContractOk()) {
            return [
                'metrics'=>array_values($metrics),
                'recommendations'=>[],
                'mode_summary'=>[],
                'audience'=>$audience,
                'preserved_full_dataset'=>1,
                'version'=>self::VERSION,
                'policy'=>[
                    'invented_metrics'=>false,
                    'deterministic'=>true,
                    'full_exports_preserved'=>true,
                    'scoring_contract_ok'=>false,
                    'fail_open'=>true,
                ],
            ];
        }
        $dashboardMap = [];
        foreach ($dashboards as $dashboard) {
            if (!is_array($dashboard)) continue;
            $dashboardMap[(int)($dashboard['id'] ?? 0)] = $dashboard;
        }

        $grouped = [];
        foreach ($metrics as $index => $metric) {
            if (!is_array($metric)) continue;
            $dashboardId = (int)($metric['dashboard_id'] ?? 0);
            $dashboard = $dashboardMap[$dashboardId] ?? ['id'=>$dashboardId,'title'=>(string)($metric['dashboard_title'] ?? 'Analytics operacional'),'ai_settings'=>[]];
            $settings = self::settingsForDashboard($dashboard);
            $scored = self::scoreMetric($metric, $audience, $settings, $intelligence);
            $scored['_orchestration_source_index'] = $index;
            $grouped[$dashboardId][] = $scored;
        }

        $output = [];
        $recommendations = [];
        $modeSummary = [];
        foreach ($grouped as $dashboardId => $rows) {
            $dashboard = $dashboardMap[$dashboardId] ?? ['id'=>$dashboardId,'title'=>'Analytics operacional','ai_settings'=>[]];
            $settings = self::settingsForDashboard($dashboard);
            $limit = self::limitForAudience($settings, $audience);
            usort($rows, static function(array $a, array $b): int {
                $pin = (int)!empty($b['orchestration_pinned']) <=> (int)!empty($a['orchestration_pinned']);
                if ($pin !== 0) return $pin;
                $score = (float)($b['orchestration_score'] ?? 0) <=> (float)($a['orchestration_score'] ?? 0);
                if ($score !== 0) return $score;
                $sort = (int)($a['sort_order'] ?? 10) <=> (int)($b['sort_order'] ?? 10);
                if ($sort !== 0) return $sort;
                return (int)($a['binding_id'] ?? 0) <=> (int)($b['binding_id'] ?? 0);
            });

            $selectedIds = [];
            $rank = 0;
            foreach ($rows as &$row) {
                $rank++;
                $row['orchestration_rank'] = $rank;
                $selected = $settings['mode'] === 'manual' || $rank <= $limit || !empty($row['orchestration_pinned']);
                $row['orchestration_recommended'] = $selected ? 1 : 0;
                $row['orchestration_mode'] = $settings['mode'];
                if ($selected) $selectedIds[] = (int)($row['binding_id'] ?? 0);
                if ($selected) {
                    $recommendations[] = [
                        'dashboard_id'=>(int)$dashboardId,
                        'dashboard_title'=>(string)($dashboard['title'] ?? $row['dashboard_title'] ?? 'Analytics operacional'),
                        'binding_id'=>(int)($row['binding_id'] ?? 0),
                        'metric_key'=>(string)($row['metric_key'] ?? ''),
                        'metric_label'=>(string)($row['metric_label'] ?? ''),
                        'topic'=>(string)($row['orchestration_topic'] ?? ''),
                        'score'=>(float)($row['orchestration_score'] ?? 0),
                        'reason'=>(string)($row['orchestration_reason'] ?? ''),
                        'pinned'=>!empty($row['orchestration_pinned']) ? 1 : 0,
                        'rank'=>$rank,
                    ];
                }
            }
            unset($row);

            $modeSummary[] = [
                'dashboard_id'=>(int)$dashboardId,
                'dashboard_title'=>(string)($dashboard['title'] ?? 'Analytics operacional'),
                'mode'=>$settings['mode'],
                'available'=>count($rows),
                'recommended'=>count($selectedIds),
                'limit'=>$limit,
                'audience'=>$audience,
            ];

            foreach ($rows as $row) {
                $include = $preserveAll || $settings['mode'] !== 'automatic' || !empty($row['orchestration_recommended']);
                if (!$include) continue;
                unset($row['_orchestration_source_index']);
                $output[] = $row;
            }
        }

        usort($output, static function(array $a, array $b): int {
            $ado = (int)($a['dashboard_sort_order'] ?? 999); $bdo = (int)($b['dashboard_sort_order'] ?? 999);
            if ($ado !== $bdo) return $ado <=> $bdo;
            $ad = (int)($a['dashboard_id'] ?? 0); $bd = (int)($b['dashboard_id'] ?? 0);
            if ($ad !== $bd) return $ad <=> $bd;
            if (($a['orchestration_mode'] ?? '') === 'automatic' || ($b['orchestration_mode'] ?? '') === 'automatic') {
                $rank = (int)($a['orchestration_rank'] ?? 999) <=> (int)($b['orchestration_rank'] ?? 999);
                if ($rank !== 0) return $rank;
            }
            $sort = (int)($a['sort_order'] ?? 10) <=> (int)($b['sort_order'] ?? 10);
            return $sort !== 0 ? $sort : ((int)($a['binding_id'] ?? 0) <=> (int)($b['binding_id'] ?? 0));
        });

        return [
            'metrics'=>$output,
            'recommendations'=>$recommendations,
            'mode_summary'=>$modeSummary,
            'audience'=>$audience,
            'preserved_full_dataset'=>$preserveAll ? 1 : 0,
            'version'=>self::VERSION,
            'policy'=>[
                'invented_metrics'=>false,
                'deterministic'=>true,
                'full_exports_preserved'=>true,
            ],
        ];
    }

    public static function compactMetrics(array $metrics, array $dashboards, string $audience = 'client', int $limit = 0, array $intelligence = []): array {
        $audience = self::audience($audience);
        if (!self::scoringContractOk()) {
            if ($limit <= 0) $limit = 5;
            return array_slice(array_values($metrics), 0, max(1, min(12, $limit)));
        }
        $dashboardMap = [];
        foreach ($dashboards as $dashboard) if (is_array($dashboard)) $dashboardMap[(int)($dashboard['id'] ?? 0)] = $dashboard;
        $scored = [];
        foreach ($metrics as $metric) {
            if (!is_array($metric)) continue;
            $dashboardId = (int)($metric['dashboard_id'] ?? 0);
            $dashboard = $dashboardMap[$dashboardId] ?? ['id'=>$dashboardId,'title'=>(string)($metric['dashboard_title'] ?? ''),'ai_settings'=>[]];
            $settings = self::settingsForDashboard($dashboard);
            $row = self::scoreMetric($metric, $audience, $settings, $intelligence);
            if (isset($metric['orchestration_recommended']) && empty($metric['orchestration_recommended']) && $settings['mode'] === 'automatic') continue;
            $scored[] = $row;
        }
        usort($scored, static fn(array $a, array $b): int => ((float)($b['orchestration_score'] ?? 0) <=> (float)($a['orchestration_score'] ?? 0)) ?: ((int)($a['sort_order'] ?? 10) <=> (int)($b['sort_order'] ?? 10)));
        if ($limit <= 0) {
            $limits = [];
            foreach ($dashboardMap as $dashboard) $limits[] = (int)self::settingsForDashboard($dashboard)['max_email'];
            $limit = $limits ? max($limits) : 5;
        }
        return array_slice($scored, 0, max(1, min(12, $limit)));
    }

    public static function storeAlerts(array $stats, array $intelligence, int $limit = 8): array {
        $alerts = [];
        $storeRows = (array)($intelligence['store_intelligence']['rankings']['priority'] ?? []);
        if (!$storeRows) $storeRows = (array)($intelligence['priority_locations'] ?? []);
        foreach ($storeRows as $row) {
            if (!is_array($row)) continue;
            $priority = sanitize_key((string)($row['priority'] ?? 'monitor'));
            if ($priority === 'monitor' && (float)($row['priority_score'] ?? 0) < 38) continue;
            $id = absint($row['location_id'] ?? 0);
            $key = $id > 0 ? 'id:' . $id : 'name:' . sanitize_title((string)($row['location_name'] ?? ''));
            $signalKeys=[]; foreach((array)($row['signals']??[]) as $signal) if(is_array($signal)&&!empty($signal['key']))$signalKeys[]=sanitize_key((string)$signal['key']);
            if(!$signalKeys)$signalKeys=['execution'];
            $parts=[];
            if(isset($row['risk_score']))$parts[]='Risco '.number_format_i18n((float)$row['risk_score'],1).'/100';
            if(isset($row['opportunity_score']))$parts[]='Oportunidade '.number_format_i18n((float)$row['opportunity_score'],1).'/100';
            if(($row['stock_risk']??null)!==null)$parts[]='Stock '.number_format_i18n((float)$row['stock_risk'],1).'/100';
            $reason=sanitize_text_field((string)($row['reason']??'')); if($reason!=='')$parts[]=$reason;
            $alerts[$key] = [
                'location_id'=>$id,'location_name'=>sanitize_text_field((string)($row['location_name'] ?? 'PDV')),'city'=>sanitize_text_field((string)($row['city'] ?? '')),
                'severity'=>sanitize_key((string)($row['severity'] ?? ($priority === 'high' ? 'high' : 'medium'))),'priority_score'=>round((float)($row['priority_score'] ?? 0),1),
                'done_rate'=>isset($row['done_rate'])?round((float)$row['done_rate'],1):null,'pending_stops'=>(int)($row['pending_stops'] ?? 0),
                'risk_score'=>isset($row['risk_score'])?round((float)$row['risk_score'],1):null,'opportunity_score'=>isset($row['opportunity_score'])?round((float)$row['opportunity_score'],1):null,
                'operational_health'=>isset($row['operational_health'])?round((float)$row['operational_health'],1):null,'stock_risk'=>($row['stock_risk']??null)===null?null:round((float)$row['stock_risk'],1),
                'trend_delta_pp'=>($row['trend_delta_pp']??null)===null?null:round((float)$row['trend_delta_pp'],1),'confidence_score'=>isset($row['confidence_score'])?round((float)$row['confidence_score'],1):null,
                'title'=>$priority === 'high' ? 'Loja prioritária' : 'Loja a acompanhar','detail'=>sanitize_text_field(implode(' · ',$parts)),'signals'=>$signalKeys,
            ];
        }
        foreach ((array)($intelligence['anomalies'] ?? []) as $anomaly) {
            if (!is_array($anomaly)) continue;
            $meta=is_array($anomaly['meta']??null)?$anomaly['meta']:[];$ids=array_values(array_map('absint',(array)($meta['location_ids']??[])));$names=array_values(array_map('sanitize_text_field',(array)($meta['location_names']??[])));
            foreach($ids as $idx=>$id){if($id<=0)continue;$key='id:'.$id;if(!isset($alerts[$key]))$alerts[$key]=['location_id'=>$id,'location_name'=>(string)($names[$idx]??('PDV #'.$id)),'city'=>'','severity'=>sanitize_key((string)($anomaly['severity']??'medium')),'priority_score'=>55.0,'done_rate'=>null,'pending_stops'=>0,'risk_score'=>null,'opportunity_score'=>null,'operational_health'=>null,'stock_risk'=>null,'trend_delta_pp'=>null,'confidence_score'=>null,'title'=>sanitize_text_field((string)($anomaly['title']??'Alerta operacional')),'detail'=>sanitize_text_field((string)($anomaly['detail']??'')),'signals'=>[]];$alerts[$key]['signals'][]=sanitize_key((string)($anomaly['type']??'alert'));if(self::severityRank((string)($anomaly['severity']??'medium'))>self::severityRank((string)$alerts[$key]['severity']))$alerts[$key]['severity']=sanitize_key((string)$anomaly['severity']);}
        }
        $alerts=array_values($alerts);foreach($alerts as &$alert)$alert['signals']=array_values(array_unique(array_filter((array)$alert['signals'])));unset($alert);
        usort($alerts,static function(array $a,array $b):int{$sev=self::severityRank((string)($b['severity']??'medium'))<=>self::severityRank((string)($a['severity']??'medium'));return $sev!==0?$sev:((float)($b['priority_score']??0)<=>(float)($a['priority_score']??0));});
        return array_slice($alerts,0,max(1,min(50,$limit)));
    }

    public static function runtimeContractStatus(): array {
        $ok = self::scoringContractOk();
        return [
            'ok'=>$ok,
            'score_metric'=>method_exists(self::class, 'scoreMetric'),
            'topic'=>method_exists(self::class, 'topic'),
            'version'=>self::VERSION,
        ];
    }

    private static function scoringContractOk(): bool {
        return method_exists(self::class, 'scoreMetric') && method_exists(self::class, 'topic');
    }

    private static function scoreMetric(array $metric, string $audience, array $settings, array $intelligence = []): array {
        $topic = self::topic($metric);
        $score = 10.0;
        $reasons = [];
        $pinned = !empty($metric['orchestration_pinned']);
        if ($pinned) { $score += 160; $reasons[] = 'fixado pelo administrador'; }
        if (!empty($metric['briefing_priority'])) { $score += 56; $reasons[] = 'prioridade do briefing'; }
        if (sanitize_key((string)($metric['source_type'] ?? '')) === 'briefing') { $score += 18; $reasons[] = 'origem briefing'; }
        if (sanitize_key((string)($metric['source_type'] ?? '')) === 'route_mobility') { $score += 18; $reasons[] = 'fonte canónica de rota'; }
        if (!empty($metric['evergreen_briefing'])) { $score += 12; $reasons[] = 'métrica permanente'; }
        if (!empty($metric['smart_briefing_rollup'])) { $score += 10; $reasons[] = 'resumo inteligente'; }
        if ((int)($metric['record_count'] ?? 0) > 0) { $score += 16; $reasons[] = 'tem dados no período'; }
        else { $score -= 18; $reasons[] = 'sem dados no período'; }
        if (!empty($metric['show_kpi'])) $score += 5;
        if ((string)($metric['value'] ?? '') !== '' && ($metric['value'] ?? null) !== null) $score += 4;

        $family = sanitize_key((string)($metric['canonical_metric_key'] ?? ''));
        if ($family === '') $family = QuestionAnalyticsService::strategicFamilyKey((string)($metric['metric_label'] ?? $metric['question_label'] ?? ''), (string)($metric['question_type'] ?? ''));
        if ($family !== '') { $score += 16; $reasons[] = 'conceito estratégico'; }

        $weights = self::audienceWeights($audience);
        if (isset($weights[$topic])) { $score += $weights[$topic]; $reasons[] = 'relevante para ' . ($audience === 'merchandiser' ? 'execução no terreno' : 'leitura do cliente'); }
        if ($settings['alert_boost']) {
            $boost = self::alertBoost($topic, $intelligence);
            if ($boost > 0) { $score += $boost; $reasons[] = 'alerta ativo no tema'; }
        }
        $score -= min(8, max(0, (int)($metric['sort_order'] ?? 10)) / 50);

        $metric['orchestration_score'] = round(max(0, min(250, $score)),1);
        $metric['orchestration_topic'] = $topic;
        $metric['orchestration_reason'] = implode(' · ', array_slice(array_values(array_unique($reasons)), 0, 4));
        $metric['orchestration_pinned'] = $pinned ? 1 : 0;
        return $metric;
    }

    private static function topic(array $metric): string {
        $family = sanitize_key((string)($metric['canonical_metric_key'] ?? ''));
        $type = sanitize_key((string)($metric['question_type'] ?? ''));
        $text = self::normalized((string)($metric['metric_key'] ?? '') . ' ' . (string)($metric['metric_label'] ?? '') . ' ' . (string)($metric['question_label'] ?? '') . ' ' . $family);
        if (sanitize_key((string)($metric['source_type'] ?? '')) === 'route_mobility' || $type === 'system_route_mobility' || self::has($text, ['mobilidade','km execut','quilometr','combustivel','energia','portagens','custo mobilidade'])) return 'mobility';
        if (strpos($family, 'share_space') !== false || $type === 'share_space_matrix' || self::has($text, ['share space','quota','concorr'])) return 'share_space';
        if (strpos($family, 'frente') !== false || $type === 'product_matrix' || self::has($text, ['frente'])) return 'fronts';
        if (strpos($family, 'stock') !== false || self::has($text, ['stock','rutura','ruptura'])) return 'stock';
        if (strpos($family, 'encomenda') !== false || $type === 'order_suggestion' || self::has($text, ['encomenda','pedido','reposicao'])) return 'orders';
        if (strpos($family, 'preco') !== false || self::has($text, ['preco','pvp'])) return 'price';
        if (strpos($family, 'promoco') !== false || self::has($text, ['promoc','visibilidade','expositor','ilha','implement'])) return 'implementation';
        if (strpos($family, 'problemas_oportunidades') !== false || self::has($text, ['problema','oportunidade','incumpr'])) return 'issues';
        if (in_array($type, ['image_upload','file_upload','categorized_image_upload'], true) || (string)($metric['chart_type'] ?? '') === 'image_gallery' || self::has($text, ['evidencia','foto','imagem'])) return 'evidence';
        if (self::has($text, ['execucao','visita','rota','conclus'])) return 'execution';
        if (self::has($text, ['disponibilidade','disponivel'])) return 'availability';
        if (!empty($metric['briefing_priority']) || strpos($family, 'briefing_') === 0) return 'briefing';
        return 'general';
    }

    private static function audienceWeights(string $audience): array {
        if ($audience === 'merchandiser') return [
            'execution'=>24,'mobility'=>21,'stock'=>22,'issues'=>22,'briefing'=>20,'orders'=>19,'evidence'=>18,
            'implementation'=>17,'availability'=>16,'fronts'=>14,'share_space'=>12,'price'=>10,'general'=>5,
        ];
        return [
            'execution'=>24,'mobility'=>22,'share_space'=>23,'fronts'=>21,'stock'=>21,'issues'=>20,'availability'=>17,
            'implementation'=>16,'price'=>15,'orders'=>13,'briefing'=>12,'evidence'=>7,'general'=>5,
        ];
    }

    private static function alertBoost(string $topic, array $intelligence): float {
        $map = [
            'stock'=>['stock_breaks','stock_low','stock_negative'],
            'execution'=>['execution_low','execution_watch','execution_drop','execution_drop_watch','pending_stops','routes_without_progress','stores_low_execution'],
            'share_space'=>['share_space_below_target'],
            'fronts'=>['fronts_decline'],
            'issues'=>['stores_low_execution','pending_stops','routes_without_progress'],
        ];
        if (empty($map[$topic])) return 0.0;
        $boost = 0.0;
        foreach ((array)($intelligence['anomalies'] ?? []) as $anomaly) {
            if (!is_array($anomaly)) continue;
            if (!in_array(sanitize_key((string)($anomaly['type'] ?? '')), $map[$topic], true)) continue;
            $severity = sanitize_key((string)($anomaly['severity'] ?? 'medium'));
            $boost = max($boost, $severity === 'critical' ? 35 : ($severity === 'high' ? 28 : 18));
        }
        return $boost;
    }

    private static function audience(string $audience): string {
        $audience = sanitize_key($audience);
        return in_array($audience, ['merchandiser','fieldflow_app','team'], true) ? 'merchandiser' : 'client';
    }

    private static function limitForAudience(array $settings, string $audience): int {
        return $audience === 'merchandiser' ? (int)$settings['max_merchandiser'] : (int)$settings['max_client'];
    }

    private static function severityRank(string $severity): int {
        return ['low'=>1,'medium'=>2,'high'=>3,'critical'=>4][sanitize_key($severity)] ?? 2;
    }

    private static function normalized(string $text): string {
        $text = remove_accents(wp_strip_all_tags($text));
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text);
        return trim((string)$text);
    }

    private static function has(string $text, array $needles): bool {
        foreach ($needles as $needle) if ($needle !== '' && strpos($text, $needle) !== false) return true;
        return false;
    }
}
