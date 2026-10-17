<?php
namespace RoutesPro\Analytics;

use RoutesPro\Forms\Forms;
use RoutesPro\Services\AIManager;
use RoutesPro\Services\SubmissionStatusPolicy;
use RoutesPro\Services\OperationalReportDayPolicy;
use RoutesPro\Support\Logger;

if (!defined('ABSPATH')) exit;

class AnalyticsAIService {
    private const CACHE_TTL = 21600;

    public static function registerHooks(): void {
        add_action('wp_ajax_routespro_ai_dashboard_preview', [self::class, 'ajaxPreviewDashboard']);
        add_action('wp_ajax_routespro_ai_dashboard_create', [self::class, 'ajaxCreateDashboard']);
    }

    public static function available(): bool {
        return class_exists(AIManager::class) && AIManager::available('analytics');
    }

    public static function providerLabel(): string {
        return class_exists(AIManager::class) ? AIManager::providerLabel('analytics') : 'Motor analítico';
    }

    public static function ajaxPreviewDashboard(): void {
        self::assertManagerAjax();
        try {
            $input = self::sanitizeBuilderInput($_POST);
            $proposal = self::buildDashboardProposal($input);
            wp_send_json_success([
                'proposal' => $proposal,
                'provider' => self::providerLabel(),
                'ai_available' => self::available(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Falha ao gerar proposta de dashboard IA.', ['context_key' => 'analytics_ai_builder'], ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => $e->getMessage()], 400);
        }
    }

    public static function ajaxCreateDashboard(): void {
        self::assertManagerAjax();
        try {
            $input = self::sanitizeBuilderInput($_POST);
            $proposal = json_decode(wp_unslash((string)($_POST['proposal'] ?? '')), true);
            if (!is_array($proposal)) throw new \RuntimeException('A proposta recebida é inválida. Gera novamente a pré-visualização.');
            wp_send_json_success(self::createDashboardFromProposal($proposal, $input));
        } catch (\Throwable $e) {
            Logger::error('Falha ao criar dashboard IA.', ['context_key' => 'analytics_ai_builder'], ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => $e->getMessage()], 400);
        }
    }

    private static function assertManagerAjax(): void {
        if (!current_user_can('routespro_manage')) wp_send_json_error(['message' => 'Sem permissões.'], 403);
        check_ajax_referer('routespro_ai_dashboard_builder', 'nonce');
    }

    public static function sanitizeBuilderInput(array $source): array {
        $objective = sanitize_key($source['objective'] ?? 'mixed');
        $audience = sanitize_key($source['audience'] ?? 'both');
        $layout = sanitize_key($source['layout_type'] ?? 'mixed');
        if (!in_array($objective, ['execution','availability','fronts','orders','quality','mixed'], true)) $objective = 'mixed';
        if (!in_array($audience, ['client_portal','fieldflow_app','both'], true)) $audience = 'both';
        if (!in_array($layout, ['executive','mixed','table'], true)) $layout = 'mixed';
        return [
            'client_id' => absint($source['client_id'] ?? 0),
            'project_id' => absint($source['project_id'] ?? 0),
            'route_id' => absint($source['route_id'] ?? 0),
            'store_group_id' => absint($source['store_group_id'] ?? 0),
            'form_ids' => array_values(array_unique(array_filter(array_map('absint', (array)($source['form_ids'] ?? []))))),
            'objective' => $objective,
            'audience' => $audience,
            'layout_type' => $layout,
            'period_days' => max(7, min(365, absint($source['period_days'] ?? 30))),
            'max_widgets' => max(3, min(10, absint($source['max_widgets'] ?? 7))),
            'title_hint' => sanitize_text_field($source['title_hint'] ?? ''),
            'business_context' => sanitize_textarea_field($source['business_context'] ?? ''),
        ];
    }

    public static function buildDashboardProposal(array $input): array {
        $catalog = self::questionCatalog($input);
        if (!$catalog) throw new \RuntimeException('Não foram encontradas perguntas nos formulários selecionados.');
        $coverage = self::dataCoverage($input, $catalog);
        $briefing = self::briefingContext(array_merge($input, [
            'from' => wp_date('Y-m-d', strtotime('-' . max(7, (int)$input['period_days']) . ' days'), wp_timezone()),
            'to' => current_time('Y-m-d'),
        ]));
        $fallback = self::ruleBasedProposal($input, $catalog, $coverage, $briefing);
        if (!self::available()) {
            $fallback['generation_source'] = 'smart_rules';
            $fallback['generation_note'] = 'A IA de Analytics não está configurada. A proposta foi criada pelo motor inteligente do FieldFlow.';
            return $fallback;
        }
        try {
            $raw = AIManager::complete('analytics', self::builderPrompt($input, $catalog, $coverage, $briefing), [
                'task' => 'analytics_dashboard_builder',
                'feature_key' => 'analytics_dashboard_builder',
                'request_kind' => 'builder',
                'client_id' => absint($input['client_id'] ?? 0),
                'project_id' => absint($input['project_id'] ?? 0),
                'temperature' => 0.15,
                'max_tokens' => 4200,
                'timeout' => 90,
            ]);
            $proposal = self::validateProposal(self::decodeJson($raw), $input, $catalog, $fallback);
            $proposal['generation_source'] = 'ai';
            $proposal['generation_note'] = 'Proposta criada por ' . self::providerLabel() . ' e validada pelo catálogo FieldFlow.';
            return $proposal;
        } catch (\Throwable $e) {
            Logger::warning('IA indisponível no builder, aplicado fallback.', ['context_key' => 'analytics_ai_builder'], ['error' => $e->getMessage()]);
            $fallback['generation_source'] = 'smart_rules';
            $fallback['generation_note'] = 'A IA não respondeu dentro das regras esperadas. O FieldFlow criou uma proposta segura com base nas perguntas e dados disponíveis.';
            return $fallback;
        }
    }

    public static function createDashboardFromProposal(array $proposal, array $input): array {
        global $wpdb;
        $catalog = self::questionCatalog($input);
        $briefing = self::briefingContext(array_merge($input, [
            'from' => wp_date('Y-m-d', strtotime('-' . max(7, (int)$input['period_days']) . ' days'), wp_timezone()),
            'to' => current_time('Y-m-d'),
        ]));
        $fallback = self::ruleBasedProposal($input, $catalog, self::dataCoverage($input, $catalog), $briefing);
        $proposal = self::validateProposal($proposal, $input, $catalog, $fallback);
        $dashTable = $wpdb->prefix . 'routespro_analytics_dashboards';
        $bindingTable = $wpdb->prefix . 'routespro_form_analytics_bindings';
        $wpdb->query('START TRANSACTION');
        try {
            $settings = [
                'objective' => $input['objective'],
                'audience' => $input['audience'],
                'period_days' => $input['period_days'],
                'form_ids' => $input['form_ids'],
                'business_context' => $input['business_context'],
                'insight_focus' => array_values((array)($proposal['insight_focus'] ?? [])),
                'generation_source' => (string)($proposal['generation_source'] ?? 'ai'),
                'provider' => self::providerLabel(),
                'briefing_priority' => !empty($briefing),
                'briefing_ids' => array_values(array_map('intval', (array)($briefing['ids'] ?? []))),
                'briefing_titles' => array_values(array_map('sanitize_text_field', (array)($briefing['titles'] ?? []))),
            ];
            $ok = $wpdb->insert($dashTable, [
                'client_id' => $input['client_id'] ?: null,
                'project_id' => $input['project_id'] ?: null,
                'route_id' => $input['route_id'] ?: null,
                'title' => sanitize_text_field($proposal['title'] ?? 'Dashboard Analytics IA'),
                'description' => sanitize_textarea_field($proposal['description'] ?? ''),
                'visibility' => $input['audience'],
                'layout_type' => sanitize_key($proposal['layout_type'] ?? $input['layout_type']),
                'sort_order' => 10,
                'is_active' => 1,
                'ai_enabled' => 1,
                'ai_objective' => $input['objective'],
                'ai_settings_json' => wp_json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ], ['%d','%d','%d','%s','%s','%s','%s','%d','%d','%d','%s','%s']);
            if (!$ok) throw new \RuntimeException('Não foi possível criar o dashboard: ' . $wpdb->last_error);
            $dashboardId = (int)$wpdb->insert_id;
            $createdWidgets = 0;
            foreach ((array)$proposal['widgets'] as $index => $widget) {
                $metricKey = self::uniqueMetricKey($bindingTable, (int)$widget['form_id'], (string)$widget['question_key'], sanitize_key((string)$widget['metric_key']));
                $settingsJson = wp_json_encode([
                    'client_id' => $input['client_id'],
                    'project_id' => $input['project_id'],
                    'route_id' => $input['route_id'],
                    'dashboard_id' => $dashboardId,
                    'store_group_id' => $input['store_group_id'],
                    'secondary_dimension' => sanitize_key((string)($widget['secondary_dimension'] ?? '')),
                    'question_type' => sanitize_key((string)($widget['question_type'] ?? '')),
                    'evidence_grouping' => sanitize_key((string)($widget['evidence_grouping'] ?? 'location_date')),
                    'evidence_show_thumbnails' => array_key_exists('evidence_show_thumbnails', $widget) ? (int)!empty($widget['evidence_show_thumbnails']) : 1,
                    'show_kpi' => !empty($widget['show_kpi']) ? 1 : 0,
                    'show_table' => !empty($widget['show_table']) ? 1 : 0,
                    'show_empty' => !empty($widget['show_empty']) ? 1 : 0,
                    'sort_order' => (int)($widget['sort_order'] ?? (($index + 1) * 10)),
                    'ai_generated' => 1,
                    'ai_reason' => sanitize_text_field((string)($widget['reason'] ?? '')),
                    'briefing_priority' => !empty($widget['briefing_priority']) ? 1 : 0,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $inserted = $wpdb->insert($bindingTable, [
                    'form_id' => (int)$widget['form_id'],
                    'question_key' => sanitize_key((string)$widget['question_key']),
                    'metric_key' => $metricKey,
                    'metric_label' => sanitize_text_field((string)$widget['metric_label']),
                    'chart_type' => sanitize_key((string)$widget['chart_type']),
                    'aggregation' => sanitize_key((string)$widget['aggregation']),
                    'dimension' => sanitize_key((string)$widget['dimension']),
                    'scope_mode' => 'client_project_location',
                    'settings_json' => $settingsJson,
                    'is_active' => 1,
                ], ['%d','%s','%s','%s','%s','%s','%s','%s','%s','%d']);
                if (!$inserted) throw new \RuntimeException('Não foi possível criar uma métrica do dashboard: ' . $wpdb->last_error);
                $createdWidgets++;
            }
            $wpdb->query('COMMIT');
            Logger::info('Dashboard IA criado.', ['context_key' => 'analytics_ai_builder'], ['dashboard_id' => $dashboardId, 'widgets' => $createdWidgets]);
            return [
                'dashboard_id' => $dashboardId,
                'widgets_created' => $createdWidgets,
                'message' => 'Dashboard Analytics IA criado com ' . $createdWidgets . ' widgets.',
                'redirect_url' => add_query_arg(['page' => 'routespro-assignments-hub', 'tab' => 'analytics', 'ff_status' => 'analytics_ai_dashboard_created'], admin_url('admin.php')),
            ];
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public static function generateInsights(array $stats, array $context = [], bool $refresh = false): array {
        $audience = sanitize_key((string)($context['audience'] ?? 'client'));
        if (!in_array($audience, ['client','merchandiser','manager'], true)) $audience = 'client';
        $briefing = self::briefingContext(array_merge($context, [
            'from' => (string)($stats['from'] ?? ''),
            'to' => (string)($stats['to'] ?? ''),
        ]));
        $stocks = [];
        if (class_exists('\RoutesPro\Stocks\StockService')) {
            try {
                $narrow = !empty($context['route_id']) || !empty($context['user_id']) || !empty($context['location_id']);
                // Restore the proven 2.2.436 critical path. Broad analytics may
                // use the aggregate stock snapshot; narrow scopes stay fail-safe
                // until exact store enrichment is loaded independently.
                if (!$narrow) $stocks = \RoutesPro\Stocks\StockService::analyticsContext($context);
            } catch (\Throwable $e) {
                Logger::warning('Stock Intelligence indisponível no Analytics; leitura continua sem stock.', ['context_key'=>'analytics_ai_stock'], ['error'=>$e->getMessage()]);
                $stocks = [];
            }
        }
        $analysisStats = class_exists('\RoutesPro\Analytics\TemporalAnalyticsService')
            ? TemporalAnalyticsService::forEvaluation($stats)
            : $stats;
        $operational = [];
        if (class_exists('\RoutesPro\Analytics\OperationalIntelligenceService')) {
            try {
                // Store Intelligence is no longer part of the blocking AI/score
                // request. It is fetched from its own endpoint after the Command
                // View is already usable. Temporal evaluation excludes planned
                // future work from operational penalties.
                $operational = OperationalIntelligenceService::analyse($analysisStats, $context, $briefing, $stocks, true, false);
            } catch (\Throwable $e) {
                Logger::warning('Intelligence base indisponível; resposta Analytics continua sem camada operacional.', ['context_key'=>'analytics_ai_base_fallback'], ['error'=>$e->getMessage()]);
                $operational = [];
            }
        }
        $compact = self::compactStats($analysisStats, $audience, $briefing, $stocks, $context);
        $compact['operational'] = self::compactOperationalForAI($operational);
        $cacheKey = 'routespro_ai_insight_' . substr(hash('sha256', wp_json_encode([$compact, $audience, get_current_user_id()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 32);
        if (!$refresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                $cached['explainability'] = self::explainability($audience, $analysisStats, $context);
                return $cached;
            }
        }
        $fallback = self::ruleBasedInsights($compact, $audience);
        if (!self::available()) {
            $fallback['briefing'] = self::publicBriefingMeta($briefing);
            $fallback['stocks'] = $stocks;
            $fallback = self::attachOperationalIntelligence($fallback, $operational);
            $fallback['explainability'] = self::explainability($audience, $analysisStats, $context);
            set_transient($cacheKey, $fallback, self::CACHE_TTL);
            return $fallback;
        }
        try {
            $raw = AIManager::complete('analytics', self::insightPrompt($compact, $audience), [
                'task' => 'analytics_insights_' . $audience,
                'feature_key' => 'analytics_insights_' . $audience,
                'request_kind' => 'insight',
                'client_id' => absint($context['client_id'] ?? 0),
                'project_id' => absint($context['project_id'] ?? 0),
                'temperature' => 0.15,
                'max_tokens' => 2200,
                'timeout' => 75,
            ]);
            $out = self::validateInsights(self::decodeJson($raw), $fallback, $audience);
            $out['source'] = 'ai';
            $out['provider'] = self::providerLabel();
            $out['briefing'] = self::publicBriefingMeta($briefing);
            $out['stocks'] = $stocks;
            $out = self::attachOperationalIntelligence($out, $operational);
            $out['explainability'] = self::explainability($audience, $analysisStats, $context);
            $out['generated_at'] = current_time('mysql');
            set_transient($cacheKey, $out, self::CACHE_TTL);
            return $out;
        } catch (\Throwable $e) {
            Logger::warning('Falha na análise IA, usado motor analítico.', ['context_key' => 'analytics_ai_insights'], ['error' => $e->getMessage()]);
            $fallback['warning'] = 'A IA não respondeu. Foi apresentada uma leitura calculada pelo motor FieldFlow.';
            $fallback['briefing'] = self::publicBriefingMeta($briefing);
            $fallback['stocks'] = $stocks;
            $fallback = self::attachOperationalIntelligence($fallback, $operational);
            $fallback['explainability'] = self::explainability($audience, $analysisStats, $context);
            set_transient($cacheKey, $fallback, 1800);
            return $fallback;
        }
    }


    public static function explainability(string $audience, array $stats = [], array $context = []): array {
        $audience = sanitize_key($audience);
        if (!in_array($audience, ['client','merchandiser','manager'], true)) $audience = 'client';
        $from = sanitize_text_field((string)($stats['from'] ?? $context['from'] ?? ''));
        $to = sanitize_text_field((string)($stats['to'] ?? $context['to'] ?? ''));
        $canonical = is_array($stats['canonical_operational_report'] ?? null) ? $stats['canonical_operational_report'] : [];
        $hasForms = !empty($stats['form_analytics']['metrics']);
        $hasLocations = !empty($stats['by_location']);
        $hasTeam = !empty($stats['team_performance']['members']);
        $hasCanonical = !empty($canonical);
        $uses = [
            ['label'=>'Execução','detail'=>'Rotas e paragens do período, distinguindo concluídas, pendentes e não visitas justificadas.'],
            ['label'=>'Reportes','detail'=>'Últimas submissões ativas e métricas válidas dos formulários no âmbito selecionado.'],
        ];
        if ($hasForms) $uses[] = ['label'=>'Perguntas e evidências','detail'=>'Widgets, respostas, frentes, encomendas e evidências configuradas no Analytics.'];
        if ($hasLocations) $uses[] = ['label'=>'PDVs','detail'=>'Execução por loja para identificar concentração de pendências e prioridades.'];
        if ($hasTeam && $audience !== 'merchandiser') $uses[] = ['label'=>'Equipa','detail'=>'Performance agregada ou individual apenas dentro das permissões do utilizador.'];
        if ($hasCanonical) $uses[] = ['label'=>'Mobilidade','detail'=>'Distância e custos estimados pela autoridade financeira FieldFlow, quando disponíveis.'];

        $summary = $audience === 'client'
            ? 'A leitura cruza apenas dados FieldFlow do cliente, período e campanha selecionados para explicar execução, risco e evolução. Não altera a operação.'
            : ($audience === 'merchandiser'
                ? 'A leitura usa apenas as tuas rotas, lojas e reportes autorizados. O dia de hoje é trabalho em curso e dias futuros são planeamento, não atraso.'
                : 'A leitura cruza a equipa dentro do âmbito autorizado, preservando a diferença entre factos observados, projeções e recomendações.');

        return [
            'contract'=>'fieldflow-explainable-analytics-v1',
            'audience'=>$audience,
            'title'=>'Como o FieldFlow chegou a esta leitura',
            'summary'=>$summary,
            'period'=>trim($from . ($from && $to ? ' a ' : '') . $to),
            'uses'=>$uses,
            'rules'=>[
                'Hoje é trabalho em curso e não é penalizado como atraso enquanto a operação decorre.',
                'Rotas futuras são planeamento e não são convertidas em incumprimento.',
                'Uma não visita justificada fecha operacionalmente a paragem, mas nunca conta como visita executada.',
                'Factos observados, projeções e sinais aprendidos permanecem separados.',
            ],
            'confidence'=>[
                'label'=>'Dependente da cobertura dos dados',
                'detail'=>'A confiança aumenta quando rotas, reportes, lojas e métricas do período têm cobertura consistente. Lacunas reduzem a força da recomendação, não são preenchidas com dados inventados.',
            ],
            'does_not_do'=>[
                'Não altera rotas ou datas automaticamente.',
                'Não altera stock, formulários ou reportes.',
                'Não transforma recomendações em factos.',
                'Não inventa causas, lojas, produtos ou valores ausentes.',
            ],
        ];
    }

    private static function attachOperationalIntelligence(array $result, array $operational): array {
        if (!$operational) return $result;
        $result['executive'] = $operational['executive'] ?? [];
        $result['comparison'] = $operational['comparison'] ?? [];
        $result['anomalies'] = $operational['anomalies'] ?? [];
        $result['sources'] = $operational['sources'] ?? [];
        $result['confidence_detail'] = $operational['confidence'] ?? [];
        $result['decision_scores'] = $operational['decision_scores'] ?? [];
        $result['priority_locations'] = $operational['priority_locations'] ?? [];
        $result['store_intelligence'] = $operational['store_intelligence'] ?? [];
        $result['controlled_actions'] = $operational['controlled_actions'] ?? [];
        $result['recommendations'] = $operational['recommendations'] ?? [];
        $result['learning'] = $operational['learning'] ?? [];
        $result['adaptive_intelligence'] = $operational['adaptive_intelligence'] ?? [];
        $result['predictive_intelligence'] = $operational['predictive_intelligence'] ?? [];
        $result['contextual_predictive_intelligence'] = $operational['contextual_predictive_intelligence'] ?? [];
        $result['intelligence_policy'] = $operational['policy'] ?? [];
        if (!empty($result['confidence_detail']['label'])) {
            $result['confidence'] = sanitize_key((string)$result['confidence_detail']['label']);
        }
        return $result;
    }

    private static function questionCatalog(array $input): array {
        global $wpdb;
        $where = ['1=1']; $args = [];
        if ($input['form_ids']) {
            $where[] = 'id IN (' . implode(',', array_fill(0, count($input['form_ids']), '%d')) . ')';
            $args = array_merge($args, $input['form_ids']);
        }
        $sql = 'SELECT id,title,status,schema_json FROM ' . Forms::table() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 100';
        $forms = $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
        $catalog = [];
        foreach ($forms as $form) {
            foreach (self::extractQuestions((string)$form['schema_json']) as $question) {
                $catalog[] = [
                    'form_id' => (int)$form['id'],
                    'form_title' => (string)($form['title'] ?: ('Formulário #' . $form['id'])),
                    'question_key' => $question['key'],
                    'question_label' => $question['label'],
                    'question_type' => $question['type'],
                    'source' => 'form',
                    'briefing_id' => 0,
                    'priority' => 100,
                ];
            }
        }
        $contextTable = class_exists('\RoutesPro\Forms\ContextQuestions')
            ? \RoutesPro\Forms\ContextQuestions::table()
            : $wpdb->prefix . 'routespro_report_context_questions';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $contextTable)) === $contextTable) {
            $ctxWhere = ['is_active=1']; $ctxArgs = [];
            if ($input['client_id']) { $ctxWhere[] = '(client_id=0 OR client_id=%d)'; $ctxArgs[] = $input['client_id']; }
            if ($input['project_id']) { $ctxWhere[] = '(project_id=0 OR project_id=%d)'; $ctxArgs[] = $input['project_id']; }
            if ($input['form_ids']) {
                $ctxWhere[] = '(form_id=0 OR form_id IN (' . implode(',', array_fill(0, count($input['form_ids']), '%d')) . '))';
                $ctxArgs = array_merge($ctxArgs, $input['form_ids']);
            }
            $ctxSql = 'SELECT form_id,question_key,question_label,question_type,source_type,briefing_id,priority FROM ' . $contextTable . ' WHERE ' . implode(' AND ', $ctxWhere) . ' ORDER BY CASE WHEN (source_type=\'briefing\' OR briefing_id>0) THEN 0 ELSE 1 END ASC,priority ASC,id ASC LIMIT 1000';
            $ctxRows = $ctxArgs ? ($wpdb->get_results($wpdb->prepare($ctxSql, ...$ctxArgs), ARRAY_A) ?: []) : ($wpdb->get_results($ctxSql, ARRAY_A) ?: []);
            $targetForms = $input['form_ids'] ?: array_values(array_unique(array_map(static fn($r) => (int)$r['form_id'], $catalog)));
            foreach ($ctxRows as $row) {
                $formIds = (int)$row['form_id'] ? [(int)$row['form_id']] : $targetForms;
                foreach ($formIds as $formId) {
                    $key = sanitize_key((string)$row['question_key']);
                    if (!$formId || $key === '') continue;
                    $exists = false;
                    $isBriefingRow = (sanitize_key((string)($row['source_type'] ?? '')) === 'briefing' || !empty($row['briefing_id']));
                    foreach ($catalog as $catalogIndex => $existing) {
                        if ((int)$existing['form_id'] === $formId && $existing['question_key'] === $key) {
                            $exists = true;
                            if ($isBriefingRow) {
                                $catalog[$catalogIndex]['source'] = 'briefing';
                                $catalog[$catalogIndex]['briefing_id'] = absint($row['briefing_id'] ?? 0);
                                $catalog[$catalogIndex]['priority'] = (int)($row['priority'] ?? 50);
                                $catalog[$catalogIndex]['question_label'] = sanitize_text_field((string)($row['question_label'] ?: $catalog[$catalogIndex]['question_label']));
                                $catalog[$catalogIndex]['question_type'] = sanitize_key((string)($row['question_type'] ?: $catalog[$catalogIndex]['question_type']));
                            }
                            break;
                        }
                    }
                    if (!$exists) $catalog[] = [
                        'form_id' => $formId,
                        'form_title' => 'Formulário #' . $formId,
                        'question_key' => $key,
                        'question_label' => sanitize_text_field((string)($row['question_label'] ?: $key)),
                        'question_type' => sanitize_key((string)($row['question_type'] ?: 'text')),
                        'source' => $isBriefingRow ? 'briefing' : 'context',
                        'briefing_id' => absint($row['briefing_id'] ?? 0),
                        'priority' => (int)($row['priority'] ?? 50),
                    ];
                }
            }
        }
        return array_slice($catalog, 0, 500);
    }

    private static function extractQuestions(string $schemaJson): array {
        $decoded = json_decode($schemaJson, true);
        if (!is_array($decoded)) return [];
        $rows = $decoded['questions'] ?? $decoded['fields'] ?? $decoded;
        if (!is_array($rows)) return [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $key = sanitize_key((string)($row['key'] ?? $row['name'] ?? $row['slug'] ?? ''));
            if ($key === '') continue;
            $out[] = [
                'key' => $key,
                'label' => sanitize_text_field((string)($row['label'] ?? $row['title'] ?? $key)),
                'type' => sanitize_key((string)($row['type'] ?? 'text')),
            ];
        }
        return $out;
    }

    private static function dataCoverage(array $input, array $catalog): array {
        global $wpdb;
        $formIds = array_values(array_unique(array_map(static fn($r) => (int)$r['form_id'], $catalog)));
        if (!$formIds) return [];
        $values = $wpdb->prefix . 'routespro_form_record_values';
        $versions = $wpdb->prefix . 'routespro_form_record_versions';
        $records = $wpdb->prefix . 'routespro_form_records';
        $submissions = $wpdb->prefix . 'routespro_form_submissions';
        foreach ([$values,$versions,$records,$submissions] as $table) if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return [];
        $where = [
            'r.form_id IN (' . implode(',', array_fill(0, count($formIds), '%d')) . ')',
            SubmissionStatusPolicy::operationalSql('s'),
            "NOT EXISTS (SELECT 1 FROM {$versions} v_new WHERE v_new.submission_id=v.submission_id AND v_new.record_id=v.record_id AND (v_new.submitted_at>v.submitted_at OR (v_new.submitted_at=v.submitted_at AND v_new.id>v.id)))"
        ]; $args = $formIds;
        if ($input['client_id']) { $where[] = 'v.client_id=%d'; $args[] = $input['client_id']; }
        if ($input['project_id']) { $where[] = 'v.project_id=%d'; $args[] = $input['project_id']; }
        if ($input['route_id']) { $where[] = 'v.route_id=%d'; $args[] = $input['route_id']; }
        $where[] = OperationalReportDayPolicy::versionSqlExpression('s', 'v') . '>=%s'; $args[] = gmdate('Y-m-d', strtotime('-' . max(7, (int)$input['period_days']) . ' days'));
        $sql = "SELECT r.form_id,rv.question_key,COUNT(*) values_count,COUNT(DISTINCT v.location_id) stores_count,MAX(v.submitted_at) last_value_at
                FROM {$values} rv INNER JOIN {$versions} v ON v.id=rv.version_id AND v.record_id=rv.record_id
                INNER JOIN {$records} r ON r.id=rv.record_id INNER JOIN {$submissions} s ON s.id=v.submission_id
                WHERE " . implode(' AND ', $where) . " GROUP BY r.form_id,rv.question_key";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
        $map = [];
        foreach ($rows as $row) $map[(int)$row['form_id'] . ':' . (string)$row['question_key']] = [
            'values_count' => (int)$row['values_count'], 'stores_count' => (int)$row['stores_count'], 'last_value_at' => (string)$row['last_value_at'],
        ];
        return $map;
    }

    private static function builderPrompt(array $input, array $catalog, array $coverage, array $briefing = []): string {
        $compactCatalog = [];
        foreach ($catalog as $row) {
            $key = (int)$row['form_id'] . ':' . $row['question_key'];
            $compactCatalog[] = array_merge($row, ['coverage' => $coverage[$key] ?? ['values_count'=>0,'stores_count'=>0,'last_value_at'=>'']]);
        }
        $schema = [
            'title'=>'string','description'=>'string','layout_type'=>'executive|mixed|table','narrative'=>'string','insight_focus'=>['string'],
            'widgets'=>[['form_id'=>'integer from catalogue','question_key'=>'exact key from catalogue','metric_key'=>'lowercase slug','metric_label'=>'short Portuguese label','chart_type'=>'kpi|line|bar|pie|table|image_gallery','aggregation'=>'latest|sum|avg|count|evidence','dimension'=>'submitted_at|location_id|route_id|project_id|client_id|product_label','secondary_dimension'=>'empty or supported dimension','evidence_grouping'=>'location_date|date_location|location','show_kpi'=>'0|1','show_table'=>'0|1','show_empty'=>'0|1','sort_order'=>'10,20,...','reason'=>'string']],
        ];
        return "És o arquiteto de Analytics do FieldFlow. Cria uma proposta sem inventar números, perguntas, fórmulas ou chaves. Usa apenas o catálogo.\n"
            . "Objetivo: {$input['objective']}\nPúblico: {$input['audience']}\nLayout: {$input['layout_type']}\nMáximo de widgets: {$input['max_widgets']}\nContexto: {$input['business_context']}\nTítulo sugerido: {$input['title_hint']}\n"
            . "Regras: o briefing publicado é a prioridade editorial e operacional; começa pelas perguntas marcadas source=briefing quando tenham dados; prioriza cobertura real; matrizes de frentes usam latest; Share Space usa share_space_matrix com latest, KPI e tabela, interpreta quota PR, quota concorrência, objetivo e desvio, e nunca pede percentagens manuais; encomendas usam sum; seleção usa count/pie; imagens usam count; evita duplicados; inclui KPIs e comparações quando existirem dados. Não declares cumprimento do briefing sem uma métrica correspondente.\n"
            . "Briefing prioritário: " . wp_json_encode(self::publicBriefingMeta($briefing), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
            . "Responde SOMENTE com JSON válido, sem markdown: " . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\nCatálogo: " . wp_json_encode($compactCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function ruleBasedProposal(array $input, array $catalog, array $coverage, array $briefing = []): array {
        $scored = [];
        foreach ($catalog as $row) {
            $key = (int)$row['form_id'] . ':' . $row['question_key'];
            $cov = $coverage[$key] ?? ['values_count'=>0,'stores_count'=>0];
            $type = strtolower((string)$row['question_type']);
            $label = strtolower(remove_accents((string)$row['question_label']));
            $score = min(80, (int)$cov['values_count']) + min(30, ((int)$cov['stores_count']) * 3);
            $isBriefing = (($row['source'] ?? '') === 'briefing' || !empty($row['briefing_id']));
            if ($isBriefing) $score += 180 + max(0, 50 - (int)($row['priority'] ?? 50));
            if (preg_match('/front|frente|matrix|matriz|product/', $type . ' ' . $label)) $score += 70;
            if (preg_match('/encomenda|order|ruptura|rutura|stock/', $type . ' ' . $label)) $score += 55;
            if (preg_match('/image|photo|upload|evidenc/', $type . ' ' . $label)) $score += 20;
            if (preg_match('/number|range|numeric/', $type)) $score += 30;
            switch ($input['objective']) {
                case 'fronts':
                    if (preg_match('/front|frente|matrix|matriz|product/', $type . ' ' . $label)) $score += 120;
                    break;
                case 'orders':
                    if (preg_match('/encomenda|order|reposi/', $type . ' ' . $label)) $score += 120;
                    break;
                case 'availability':
                    if (preg_match('/ruptura|rutura|stock|disponib|front|frente/', $type . ' ' . $label)) $score += 100;
                    break;
                case 'quality':
                    if (preg_match('/image|photo|upload|evidenc|observ|comment/', $type . ' ' . $label)) $score += 90;
                    break;
                case 'execution':
                    if (preg_match('/execu|presen|material|condi|complet|status/', $type . ' ' . $label)) $score += 75;
                    break;
            }
            $scored[] = ['row'=>$row,'score'=>$score];
        }
        usort($scored, static fn($a,$b) => $b['score'] <=> $a['score']);
        $widgets = [];
        foreach ($scored as $item) {
            if (count($widgets) >= $input['max_widgets']) break;
            $row = $item['row'];
            $type = strtolower((string)$row['question_type']);
            $search = strtolower(remove_accents($type . ' ' . $row['question_label'] . ' ' . $row['question_key']));
            $isBriefing = (($row['source'] ?? '') === 'briefing' || !empty($row['briefing_id']));
            $widget = ['form_id'=>(int)$row['form_id'],'question_key'=>$row['question_key'],'metric_key'=>sanitize_key($row['question_key'].'_analytics'),'metric_label'=>$row['question_label'],'chart_type'=>'bar','aggregation'=>'count','dimension'=>'location_id','secondary_dimension'=>'','show_kpi'=>1,'show_table'=>1,'show_empty'=>0,'sort_order'=>(count($widgets)+1)*10,'reason'=>$isBriefing?'Prioridade definida no briefing operacional e validada por reportes submetidos.':'Métrica selecionada pela cobertura e relevância operacional.','briefing_priority'=>$isBriefing?1:0];
            if (preg_match('/front|frente|matrix|matriz|product/', $search)) {
                $widget['metric_key']='frentes_atuais_'.sanitize_key($row['question_key']); $widget['metric_label']='Frentes atuais'; $widget['aggregation']='latest'; $widget['chart_type']=count($widgets)?'bar':'kpi'; $widget['secondary_dimension']='product_label'; $widget['reason']='Estado atual das frentes no último reporte válido de cada loja.';
            } elseif (preg_match('/encomenda|order/', $search)) {
                $widget['metric_key']='encomendas_'.sanitize_key($row['question_key']); $widget['metric_label']='Sugestões de encomenda'; $widget['aggregation']='sum'; $widget['dimension']='product_label'; $widget['reason']='Quantifica necessidades de reposição e permite priorizar produtos.';
            } elseif (preg_match('/ruptura|rutura|stock/', $search)) {
                $widget['metric_key']='ruturas_'.sanitize_key($row['question_key']); $widget['metric_label']='Ruturas identificadas'; $widget['reason']='Destaca lojas e produtos com risco de disponibilidade.';
            } elseif (preg_match('/number|range|numeric/', $type)) {
                $widget['aggregation']='avg'; $widget['chart_type']='line'; $widget['dimension']='submitted_at'; $widget['reason']='Mostra evolução média ao longo do período.';
            } elseif (preg_match('/select|radio|checkbox|choice/', $type)) {
                $widget['chart_type']='pie'; $widget['reason']='Distribui as respostas por opção selecionada.';
            } elseif (preg_match('/image|photo|upload|file/', $type)) {
                $widget['metric_key']='evidencias_'.sanitize_key($row['question_key']);
                $widget['metric_label']='Evidências submetidas';
                $widget['chart_type']='image_gallery';
                $widget['aggregation']='evidence';
                $widget['dimension']='location_id';
                $widget['secondary_dimension']='submitted_at';
                $widget['evidence_grouping']='location_date';
                $widget['evidence_show_thumbnails']=1;
                $widget['show_kpi']=1;
                $widget['show_table']=0;
                $widget['question_type']=$type;
                $widget['reason']='Combina local, data da visita e imagens, com leitura visual e detalhe exportável.';
            }
            $widgets[] = $widget;
        }
        if (!$widgets && $catalog) {
            $row = reset($catalog);
            $widgets[] = ['form_id'=>(int)$row['form_id'],'question_key'=>$row['question_key'],'metric_key'=>'respostas_'.sanitize_key($row['question_key']),'metric_label'=>$row['question_label'],'chart_type'=>'kpi','aggregation'=>'count','dimension'=>'location_id','secondary_dimension'=>'','show_kpi'=>1,'show_table'=>1,'show_empty'=>0,'sort_order'=>10,'reason'=>'Contagem de respostas disponíveis.'];
        }
        $labels = ['execution'=>'Execução','availability'=>'Disponibilidade e ruturas','fronts'=>'Frentes e visibilidade','orders'=>'Encomendas','quality'=>'Qualidade do reporte','mixed'=>'Performance operacional'];
        return [
            'title'=>$input['title_hint'] ?: (($labels[$input['objective']] ?? 'Analytics') . ' com IA'),
            'description'=>'Dashboard inteligente criado apenas a partir de reportes FieldFlow ativos, com prioridade para o briefing operacional publicado.',
            'layout_type'=>$input['layout_type'],
            'narrative'=>!empty($briefing) ? 'Começa pelas prioridades do briefing e cruza-as com execução, cobertura e detalhe dos reportes válidos.' : 'Combina indicadores executivos, evolução, prioridades e detalhe de suporte.',
            'insight_focus'=>!empty($briefing) ? array_values(array_slice(array_merge(['prioridades do briefing'], (array)($briefing['priorities'] ?? []), ['execução e evolução','anomalias e riscos']),0,8)) : ['execução e evolução','anomalias e riscos','lojas e produtos prioritários','ações recomendadas'],
            'widgets'=>$widgets,
        ];
    }

    private static function validateProposal(array $proposal, array $input, array $catalog, array $fallback): array {
        $map = [];
        foreach ($catalog as $row) $map[(int)$row['form_id'].':'.$row['question_key']] = $row;
        $widgets = [];
        foreach ((array)($proposal['widgets'] ?? []) as $index => $widget) {
            if (!is_array($widget)) continue;
            $formId=absint($widget['form_id']??0); $qKey=sanitize_key((string)($widget['question_key']??'')); $k=$formId.':'.$qKey;
            if (!$formId || $qKey==='' || !isset($map[$k])) continue;
            $catalogType=sanitize_key((string)($map[$k]['question_type']??''));
            $isEvidence=in_array($catalogType,['image_upload','file_upload','categorized_image_upload'],true)||preg_match('/image|photo|upload|file/',strtolower($catalogType));
            $chart=sanitize_key((string)($widget['chart_type']??'bar')); if(!in_array($chart,['kpi','line','bar','pie','table','image_gallery'],true))$chart='bar';
            $agg=sanitize_key((string)($widget['aggregation']??'count')); if(!in_array($agg,['latest','sum','avg','count','evidence'],true))$agg='count';
            $dim=sanitize_key((string)($widget['dimension']??'location_id')); if(!in_array($dim,['submitted_at','location_id','route_id','project_id','client_id','product_label','product'],true))$dim='location_id';
            $second=sanitize_key((string)($widget['secondary_dimension']??'')); if($second!==''&&!in_array($second,['submitted_at','location_id','route_id','project_id','client_id','product_label','product'],true))$second='';
            if($isEvidence){$chart='image_gallery';$agg='evidence';$dim='location_id';$second='submitted_at';}
            $grouping=sanitize_key((string)($widget['evidence_grouping']??'location_date'));if(!in_array($grouping,['location_date','date_location','location'],true))$grouping='location_date';
            $isBriefing = (($map[$k]['source'] ?? '') === 'briefing' || !empty($map[$k]['briefing_id']));
            $widgets[]=['form_id'=>$formId,'question_key'=>$qKey,'metric_key'=>sanitize_key((string)($widget['metric_key']??($qKey.'_analytics'))),'metric_label'=>sanitize_text_field((string)($widget['metric_label']??$map[$k]['question_label'])),'chart_type'=>$chart,'aggregation'=>$agg,'dimension'=>$dim,'secondary_dimension'=>$second,'question_type'=>$catalogType,'evidence_grouping'=>$grouping,'evidence_show_thumbnails'=>$isEvidence?1:(!empty($widget['evidence_show_thumbnails'])?1:0),'show_kpi'=>$isEvidence?1:(!empty($widget['show_kpi'])?1:0),'show_table'=>$isEvidence?0:(!empty($widget['show_table'])?1:0),'show_empty'=>!empty($widget['show_empty'])?1:0,'sort_order'=>max(1,min(999,(int)($widget['sort_order']??(($index+1)*10)))),'reason'=>sanitize_text_field((string)($widget['reason']??($isBriefing?'Prioridade definida no briefing operacional.':'Métrica proposta para este objetivo.'))),'briefing_priority'=>$isBriefing?1:0];
            if(count($widgets)>=$input['max_widgets'])break;
        }
        if(!$widgets)$widgets=$fallback['widgets'];
        $layout=sanitize_key((string)($proposal['layout_type']??'')); if(!in_array($layout,['executive','mixed','table'],true))$layout=$input['layout_type'];
        return ['title'=>sanitize_text_field((string)($proposal['title']??$fallback['title'])),'description'=>sanitize_textarea_field((string)($proposal['description']??$fallback['description'])),'layout_type'=>$layout,'narrative'=>sanitize_textarea_field((string)($proposal['narrative']??$fallback['narrative'])),'insight_focus'=>array_values(array_slice(array_filter(array_map('sanitize_text_field',(array)($proposal['insight_focus']??$fallback['insight_focus']))),0,8)),'widgets'=>$widgets,'generation_source'=>(string)($proposal['generation_source']??''),'generation_note'=>(string)($proposal['generation_note']??'')];
    }

    private static function uniqueMetricKey(string $table, int $formId, string $questionKey, string $base): string {
        global $wpdb;
        $base=sanitize_key($base)?:'metric'; $candidate=$base; $i=2;
        while($wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE form_id=%d AND question_key=%s AND metric_key=%s LIMIT 1",$formId,$questionKey,$candidate))){$candidate=substr($base,0,175).'_'.$i;$i++;}
        return $candidate;
    }

    private static function compactOperationalForAI(array $operational): array {
        if (!$operational) return [];
        $compactStore = static function(array $row): array {
            return [
                'location_id'=>absint($row['location_id'] ?? 0),
                'location_name'=>sanitize_text_field((string)($row['location_name'] ?? '')),
                'execution'=>isset($row['execution_score']) ? round((float)$row['execution_score'],1) : (isset($row['done_rate']) ? round((float)$row['done_rate'],1) : null),
                'risk'=>isset($row['risk_score']) ? round((float)$row['risk_score'],1) : null,
                'opportunity'=>isset($row['opportunity_score']) ? round((float)$row['opportunity_score'],1) : null,
                'stock_risk'=>isset($row['stock_risk']) && $row['stock_risk'] !== null ? round((float)$row['stock_risk'],1) : null,
                'share_gap'=>isset($row['share_gap_pp']) && $row['share_gap_pp'] !== null ? round((float)$row['share_gap_pp'],1) : null,
                'trend'=>isset($row['trend_delta_pp']) && $row['trend_delta_pp'] !== null ? round((float)$row['trend_delta_pp'],1) : null,
                'priority'=>isset($row['priority_score']) ? round((float)$row['priority_score'],1) : null,
                'confidence'=>isset($row['confidence_score']) ? round((float)$row['confidence_score'],1) : null,
                'reason'=>sanitize_text_field((string)($row['reason'] ?? '')),
            ];
        };
        $store = is_array($operational['store_intelligence'] ?? null) ? $operational['store_intelligence'] : [];
        $rankings = is_array($store['rankings'] ?? null) ? $store['rankings'] : [];
        $compactRankings = [];
        foreach (['priority','risk','opportunity','stock_risk','deteriorating','recovered'] as $key) {
            $compactRankings[$key] = array_map($compactStore, array_slice(array_values(array_filter((array)($rankings[$key] ?? []), 'is_array')), 0, 8));
        }
        return [
            'executive'=>$operational['executive'] ?? [],
            'comparison'=>$operational['comparison'] ?? [],
            'anomalies'=>array_slice((array)($operational['anomalies'] ?? []), 0, 8),
            'sources'=>$operational['sources'] ?? [],
            'confidence'=>$operational['confidence'] ?? [],
            'decision_scores'=>$operational['decision_scores'] ?? [],
            'priority_locations'=>array_map($compactStore, array_slice(array_values(array_filter((array)($operational['priority_locations'] ?? []), 'is_array')), 0, 8)),
            'store_intelligence'=>[
                'available'=>!empty($store['available']),
                'summary'=>$store['summary'] ?? [],
                'rankings'=>$compactRankings,
                'policy'=>$store['policy'] ?? [],
            ],
            'controlled_actions'=>array_slice((array)($operational['controlled_actions'] ?? []), 0, 6),
            'recommendations'=>array_slice((array)($operational['recommendations'] ?? []), 0, 6),
            'adaptive_intelligence'=>[
                'available'=>!empty($operational['adaptive_intelligence']['available']),
                'summary'=>$operational['adaptive_intelligence']['summary'] ?? [],
                'next_best_actions'=>array_slice((array)($operational['adaptive_intelligence']['next_best_actions'] ?? []), 0, 5),
                'policy'=>$operational['adaptive_intelligence']['policy'] ?? [],
            ],
            'predictive_intelligence'=>[
                'available'=>!empty($operational['predictive_intelligence']['available']),
                'mode'=>$operational['predictive_intelligence']['mode'] ?? '',
                'state'=>$operational['predictive_intelligence']['state'] ?? '',
                'horizon'=>$operational['predictive_intelligence']['horizon'] ?? [],
                'summary'=>$operational['predictive_intelligence']['summary'] ?? [],
                'forecasts'=>$operational['predictive_intelligence']['forecasts'] ?? [],
                'leading_signals'=>array_slice((array)($operational['predictive_intelligence']['leading_signals'] ?? []), 0, 5),
                'policy'=>$operational['predictive_intelligence']['policy'] ?? [],
            ],
            'contextual_predictive_intelligence'=>[
                'available'=>!empty($operational['contextual_predictive_intelligence']['available']),
                'mode'=>$operational['contextual_predictive_intelligence']['mode'] ?? '',
                'summary'=>$operational['contextual_predictive_intelligence']['summary'] ?? [],
                'next_best_actions'=>array_slice((array)($operational['contextual_predictive_intelligence']['next_best_actions'] ?? []), 0, 6),
                'policy'=>$operational['contextual_predictive_intelligence']['policy'] ?? [],
            ],
            'policy'=>$operational['policy'] ?? [],
        ];
    }

    private static function compactStats(array $stats, string $audience, array $briefing = [], array $stocks = [], array $context = []): array {
        $metrics=[];
        foreach((array)($stats['form_analytics']['metrics']??[]) as $metric){
            $shareSummary = is_array($metric['share_space_summary'] ?? null) ? $metric['share_space_summary'] : [];
            $metrics[]=['dashboard'=>sanitize_text_field((string)($metric['dashboard_title']??'')),'label'=>sanitize_text_field((string)($metric['metric_label']??$metric['metric_key']??'')),'question_type'=>sanitize_key((string)($metric['question_type']??'')),'aggregation'=>sanitize_key((string)($metric['aggregation']??'')),'value'=>$metric['value']??null,'record_count'=>(int)($metric['record_count']??0),'note'=>sanitize_text_field((string)($metric['note']??'')),'share_space'=>$shareSummary ? ['metric_count'=>(int)($shareSummary['metric_count']??0),'stores_count'=>(int)($shareSummary['stores_count']??0),'below_target'=>(int)($shareSummary['below_target']??0),'on_target'=>(int)($shareSummary['on_target']??0),'avg_share'=>round((float)($shareSummary['avg_share']??0),1),'avg_gap'=>round((float)($shareSummary['avg_gap']??0),1),'pr_fronts_total'=>round((float)($shareSummary['pr_fronts_total']??0),1),'competitor_fronts_total'=>round((float)($shareSummary['competitor_fronts_total']??0),1)] : null,'series'=>array_map(static fn($r)=>['label'=>sanitize_text_field((string)($r['label']??'')),'value'=>is_numeric($r['value']??null)?round((float)$r['value'],2):sanitize_text_field((string)($r['value']??'')),'target'=>is_numeric($r['target']??null)?round((float)$r['target'],2):null,'gap'=>is_numeric($r['gap']??null)?round((float)$r['gap'],2):null],array_slice((array)($metric['series']??[]),0,12))];
            if(count($metrics)>=24)break;
        }
        $routes=[];
        foreach(array_slice((array)($stats['by_day']??[]),0,30) as $row)$routes[]=['date'=>(string)($row['date']??''),'project'=>(string)($row['project_name']??''),'stops'=>(int)($row['stops']??0),'done'=>(int)($row['stops_done']??0),'rate'=>(float)($row['done_rate']??0),'status'=>(string)($row['route_status']??'')];
        $temporal = self::compactTemporalForAI(is_array($stats['temporal_context'] ?? null) ? $stats['temporal_context'] : []);
        $comparisonAvailable = absint($context['route_id'] ?? 0) === 0 && empty($temporal['open_window']);
        return ['audience'=>$audience,'from'=>(string)($stats['from']??''),'to'=>(string)($stats['to']??''),'total_routes'=>(int)($stats['total_routes']??0),'completed_routes'=>(int)($stats['completed_routes']??0),'completion_rate'=>(float)($stats['completion_rate']??0),'total_stops'=>(int)($stats['total_stops']??0),'done_stops'=>(int)($stats['done_stops']??0),'done_rate'=>(float)($stats['done_rate']??0),'avg_stops_per_route'=>(float)($stats['avg_stops_per_route']??0),'avg_stop_minutes'=>$stats['avg_stop_minutes']??null,'on_time_rate'=>$stats['on_time_rate']??null,'comparison_available'=>$comparisonAvailable,'previous_summary'=>$comparisonAvailable?($stats['previous_summary']??[]):[],'rankings'=>$stats['rankings']??[],'routes'=>$routes,'temporal_context'=>$temporal,'metrics'=>$metrics,'briefing'=>self::publicBriefingMeta($briefing),'stocks'=>$stocks,'data_policy'=>'closed_days_for_evaluation_today_in_progress_future_planned'];
    }

    private static function compactTemporalForAI(array $temporal): array {
        if (!$temporal) return [];
        $summary = static function($row): array {
            $row = is_array($row) ? $row : [];
            return [
                'routes'=>(int)($row['routes']??0),
                'completed_routes'=>(int)($row['completed_routes']??0),
                'stops'=>(int)($row['stops']??0),
                'done_stops'=>(int)($row['done_stops']??0),
                'justified_failed_stops'=>(int)($row['justified_failed_stops']??0),
                'terminal_stops'=>(int)($row['terminal_stops']??0),
                'unresolved_stops'=>(int)($row['unresolved_stops']??0),
                'done_rate'=>round((float)($row['done_rate']??0),1),
                'operational_completion_rate'=>round((float)($row['operational_completion_rate']??0),1),
            ];
        };
        $routeRows = static function($rows, int $limit): array {
            $out=[];
            foreach(array_slice(array_values(array_filter((array)$rows,'is_array')),0,$limit) as $row){
                $out[]=[
                    'date'=>sanitize_text_field((string)($row['date']??'')),
                    'route_id'=>absint($row['route_id']??$row['id']??0),
                    'project'=>sanitize_text_field((string)($row['project_name']??'')),
                    'status'=>sanitize_key((string)($row['route_status']??'')),
                    'stops'=>(int)($row['stops']??0),
                    'done'=>(int)($row['stops_done']??0),
                    'justified_failed'=>(int)($row['stops_justified_failed']??0),
                    'remaining'=>(int)($row['stops_remaining']??0),
                ];
            }
            return $out;
        };
        $futureDays=[];
        foreach(array_slice(array_values(array_filter((array)($temporal['future_route_days']??[]),'is_array')),0,14) as $row){
            $futureDays[]=['date'=>sanitize_text_field((string)($row['date']??'')),'routes'=>(int)($row['routes']??0),'stops'=>(int)($row['stops']??0)];
        }
        return [
            'as_of_date'=>sanitize_text_field((string)($temporal['as_of_date']??'')),
            'selected_from'=>sanitize_text_field((string)($temporal['selected_from']??'')),
            'selected_to'=>sanitize_text_field((string)($temporal['selected_to']??'')),
            'open_window'=>!empty($temporal['open_window']),
            'evaluation_scope'=>sanitize_key((string)($temporal['evaluation_scope']??'')),
            'closed'=>$summary($temporal['closed']??[]),
            'today'=>$summary($temporal['today']??[]),
            'future'=>$summary($temporal['future']??[]),
            'future_route_days'=>$futureDays,
            'today_routes'=>$routeRows($temporal['today_by_day']??[],12),
            'future_routes'=>$routeRows($temporal['future_by_day']??[],20),
            'policy'=>is_array($temporal['policy']??null)?$temporal['policy']:[],
        ];
    }

    private static function insightPrompt(array $compact, string $audience): string {
        $audienceRule=$audience==='merchandiser'?'Fala diretamente com o merchandiser, sem comparação punitiva. Dá prioridades concretas para próximas visitas.':'Escreve para um decisor do cliente. Destaca impacto, risco, oportunidade e ações de gestão.';
        $schema=['headline'=>'string','summary'=>'string','highlights'=>[['title'=>'string','detail'=>'string','impact'=>'positive|neutral|warning']],'risks'=>[['title'=>'string','detail'=>'string','severity'=>'low|medium|high']],'actions'=>[['title'=>'string','detail'=>'string','priority'=>'now|next|monitor']],'confidence'=>'low|medium|high'];
        return "Analisa métricas agregadas do FieldFlow provenientes exclusivamente das versões mais recentes de submissões ativas. Não inventes valores, causas, lojas ou produtos ausentes. Respeita Dados.temporal_context: dias passados fechados são avaliáveis, hoje é trabalho em progresso e não deve ser classificado como falha apenas por ter visitas ainda por concluir, e rotas futuras são planeamento, nunca atraso nem incumprimento. Uma não visita justificada pode fechar operacionalmente uma rota, mas não conta como visita executada. Se Dados.comparison_available for falso, não faças comparações com o período anterior. O briefing publicado em Dados.briefing tem prioridade: avalia primeiro as suas prioridades, mas só afirma execução, incumprimento ou risco quando existir uma métrica correspondente. Quando Dados.stocks.available for verdadeiro, cruza ruturas, stock baixo e valores negativos com a execução, sem inventar causas nem assumir que o stock do ficheiro substitui a contagem física. Nas métricas Share Space, usa apenas share_space e series para avaliar quota PR, concorrência, objetivo e desvio. Um desvio negativo é um risco, não uma causa. Usa Dados.operational como leitura determinística: não contradigas anomalias calculadas, não inventes fontes e não proponhas ações que alterem dados. Quando Dados.operational.adaptive_intelligence.available for verdadeiro, usa essa evidência apenas para priorizar recomendações e explicar confiança histórica; nunca a apresentes como prova de causalidade nem como alteração dos scores operacionais. Quando Dados.operational.predictive_intelligence.available for verdadeiro, trata-a como uma projeção determinística da próxima janela, não como probabilidade nem facto futuro. Quando Dados.operational.contextual_predictive_intelligence.available for verdadeiro, usa as Next Best Actions como prioridades assistidas por loja, produto e tipo de problema; preserva a separação entre evidência observada e projetada, não inventes produtos ausentes e não transformes a ação sugerida numa execução automática. Distingue sempre observado de projetado e nunca afirmes causalidade. {$audienceRule}\nIdentifica no máximo 3 destaques, 3 riscos e 4 ações. Se os dados forem escassos, diz isso. Responde SOMENTE com JSON válido: ".wp_json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\nDados: ".wp_json_encode($compact,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    private static function ruleBasedInsights(array $data, string $audience): array {
        $doneRate=(float)($data['done_rate']??0);$comparisonAvailable=!empty($data['comparison_available']);$prevRate=(float)($data['previous_summary']['done_rate']??0);$delta=$comparisonAvailable?round($doneRate-$prevRate,1):0.0;$total=(int)($data['total_stops']??0);$done=(int)($data['done_stops']??0);$pending=max(0,$total-$done);
        $temporal=is_array($data['temporal_context']??null)?$data['temporal_context']:[];$openWindow=!empty($temporal['open_window']);$today=is_array($temporal['today']??null)?$temporal['today']:[];$future=is_array($temporal['future']??null)?$temporal['future']:[];$todayRoutes=(int)($today['routes']??0);$todayStops=(int)($today['stops']??0);$todayDone=(int)($today['done_stops']??0);$todayJustified=(int)($today['justified_failed_stops']??0);$todayRemaining=(int)($today['unresolved_stops']??0);$futureRoutes=(int)($future['routes']??0);$futureStops=(int)($future['stops']??0);$futureDays=count((array)($temporal['future_route_days']??[]));
        $highlights=[];if($doneRate>=90&&$total>0)$highlights[]=['title'=>'Execução forte','detail'=>'A taxa de conclusão está acima de 90% nos dias já fechados do período.','impact'=>'positive'];elseif($doneRate>=70&&$total>0)$highlights[]=['title'=>'Execução estável','detail'=>'A maioria das visitas dos dias já fechados está concluída, mas ainda existe margem de fecho.','impact'=>'neutral'];if($comparisonAvailable&&$delta>0)$highlights[]=['title'=>'Evolução positiva','detail'=>'A execução melhorou '.$delta.' pontos percentuais face ao período anterior.','impact'=>'positive'];
        if($openWindow&&$todayRoutes>0&&count($highlights)<3)$highlights[]=['title'=>'Rota de hoje em progresso','detail'=>$todayDone.' de '.$todayStops.' visitas de hoje estão executadas'.($todayJustified>0?', com '.$todayJustified.' não visita'.($todayJustified===1?'':'s').' justificada'.($todayJustified===1?'':'s'):'').'.','impact'=>'neutral'];
        if($openWindow&&$futureRoutes>0&&count($highlights)<3)$highlights[]=['title'=>'Planeamento futuro carregado','detail'=>$futureRoutes.' rota'.($futureRoutes===1?'':'s').' futura'.($futureRoutes===1?'':'s').' com '.$futureStops.' visitas distribuídas por '.$futureDays.' dia'.($futureDays===1?'':'s').' com rota.','impact'=>'neutral'];
        $risks=[];if($pending>0)$risks[]=['title'=>'Visitas por resolver em dias fechados','detail'=>$pending.' paragens de dias passados ainda não estão concluídas nem justificadas.','severity'=>$pending>max(3,$total*.25)?'high':'medium'];if($comparisonAvailable&&$delta<-5)$risks[]=['title'=>'Quebra de execução','detail'=>'A execução recuou '.abs($delta).' pontos percentuais face ao período anterior.','severity'=>'high'];
        $stockData=(array)($data['stocks']??[]);$stockAlerts=(int)($stockData['zero_count']??0)+(int)($stockData['negative_count']??0);if($stockAlerts>0)$risks[]=['title'=>'Disponibilidade em risco','detail'=>(int)($stockData['zero_count']??0).' linhas em rutura e '.(int)($stockData['negative_count']??0).' com stock negativo no snapshot de '.sanitize_text_field((string)($stockData['snapshot_date']??'')).'.','severity'=>$stockAlerts>20?'high':'medium'];
        $shareActions=[];
        foreach((array)$data['metrics'] as $metric){
            $share=(array)($metric['share_space']??[]);
            if($share){
                $below=(int)($share['below_target']??0);$onTarget=(int)($share['on_target']??0);$gap=(float)($share['avg_gap']??0);$label=sanitize_text_field((string)($metric['label']??'Share Space'));
                if($below>0&&count($risks)<3)$risks[]=['title'=>'Share Space abaixo do objetivo','detail'=>$label.' apresenta '.$below.' comparação'.($below===1?'':'ões').' abaixo do objetivo, com desvio médio de '.number_format_i18n($gap,1).' pontos percentuais.','severity'=>$below>=3||$gap<=-5?'high':'medium'];
                elseif($onTarget>0&&count($highlights)<3)$highlights[]=['title'=>'Objetivos de Share Space atingidos','detail'=>$onTarget.' comparação'.($onTarget===1?'':'ões').' atingiram ou superaram o objetivo no período.','impact'=>'positive'];
                if($below>0)$shareActions[]=['title'=>$audience==='merchandiser'?'Reforçar frentes nas próximas visitas':'Priorizar os gaps de Share Space','detail'=>'Abrir o widget Share Space, ordenar pelos maiores desvios negativos e validar primeiro as comparações abaixo do objetivo.','priority'=>'now'];
            }
            if(count($risks)<3&&(int)($metric['record_count']??0)===0&&(!$openWindow||$total>0))$risks[]=['title'=>'Métrica sem cobertura','detail'=>'“'.($metric['label']?:'Métrica').'” não tem dados nos dias avaliáveis do período.','severity'=>'low'];
        }
        $actions=$shareActions;
        $briefingPriorities=(array)($data['briefing']['priorities']??[]);
        foreach(array_slice($briefingPriorities,0,2) as $priority){
            $actions[]=['title'=>'Validar prioridade do briefing','detail'=>sanitize_text_field((string)$priority),'priority'=>'now'];
        }
        if(!empty($stockData['available'])&&((int)($stockData['zero_count']??0)>0||(int)($stockData['low_count']??0)>0))$actions[]=['title'=>'Priorizar lojas com risco de stock','detail'=>'Cruzar as ruturas e stocks baixos do snapshot mais recente com as próximas visitas e validar a situação em loja.','priority'=>'now'];
        if($pending>0)$actions[]=['title'=>$audience==='merchandiser'?'Resolver os dias fechados em aberto':'Priorizar o fecho de dias passados','detail'=>'Rever apenas as rotas de dias passados com paragens ainda em aberto e validar bloqueios ou justificações.','priority'=>'now'];
        if($openWindow&&$todayRoutes>0&&$todayRemaining>0)$actions[]=['title'=>'Continuar a rota de hoje','detail'=>$todayRemaining.' visita'.($todayRemaining===1?'':'s').' de hoje ainda '.($todayRemaining===1?'está':'estão').' por resolver. Este progresso não é tratado como incumprimento enquanto o dia está em curso.','priority'=>'now'];
        if($openWindow&&$futureRoutes>0)$actions[]=['title'=>'Preparar os próximos dias com rota','detail'=>$futureRoutes.' rota'.($futureRoutes===1?'':'s').' futura'.($futureRoutes===1?'':'s').' já '.($futureRoutes===1?'está':'estão').' planeada'.($futureRoutes===1?'':'s').'. Validar briefings, Cardex e prioridades antes das visitas, sem as contabilizar como pendência.','priority'=>'next'];
        if($comparisonAvailable&&$delta<0)$actions[]=['title'=>'Comparar com o período anterior','detail'=>'Identificar os dias ou campanhas onde ocorreu a quebra antes de ajustar a operação.','priority'=>'next'];$actions[]=['title'=>'Rever anomalias dos formulários','detail'=>'Usar os dashboards configurados para localizar ruturas, frentes e encomendas fora do padrão.','priority'=>'monitor'];
        $briefingTitle=(string)($data['briefing']['primary_title']??'');
        if($total>0){
            $summary=$done.' de '.$total.' paragens dos dias já fechados estão concluídas.'.($comparisonAvailable?' A variação face ao período anterior é de '.($delta>0?'+':'').$delta.' pontos percentuais.':($openWindow?' Hoje e os dias futuros são apresentados como progresso e planeamento, sem penalizar os scores.':' A comparação temporal está desativada neste drill-down de rota.'));
            $headline='Execução fechada em '.$doneRate.'%';
        }elseif($openWindow&&$todayRoutes>0){
            $headline='Hoje: '.$todayDone.' de '.$todayStops.' visitas executadas';
            $summary='O período está aberto. Hoje existem '.$todayRoutes.' rota'.($todayRoutes===1?'':'s').' com '.$todayStops.' visitas, '.$todayDone.' executadas, '.$todayJustified.' não visita'.($todayJustified===1?'':'s').' justificada'.($todayJustified===1?'':'s').' e '.$todayRemaining.' ainda por resolver. O trabalho de hoje é progresso operacional, não uma falha enquanto o dia decorre.';
        }elseif($openWindow&&$futureRoutes>0){
            $headline='Planeamento futuro disponível';
            $summary='Ainda não existem dias passados fechados para avaliar neste período. Estão planeadas '.$futureRoutes.' rota'.($futureRoutes===1?'':'s').' futura'.($futureRoutes===1?'':'s').' com '.$futureStops.' visitas em '.$futureDays.' dia'.($futureDays===1?'':'s').' com rota. Este planeamento não reduz os scores nem é tratado como atraso.';
        }else{
            $headline='Ainda não existem visitas suficientes para análise';
            $summary='O motor precisa de rotas e reportes concluídos para produzir recomendações mais específicas.';
        }
        if($openWindow&&$futureRoutes>0&&$todayRoutes>0)$summary.=' Existem ainda '.$futureRoutes.' rota'.($futureRoutes===1?'':'s').' futura'.($futureRoutes===1?'':'s').' com '.$futureStops.' visitas em '.$futureDays.' dia'.($futureDays===1?'':'s').' com rota, tratadas apenas como planeamento.';
        if($briefingTitle!=='')$summary.=' A leitura prioriza o briefing “'.$briefingTitle.'”.';if(!empty($stockData['available']))$summary.=' O snapshot de stock de '.sanitize_text_field((string)($stockData['snapshot_date']??'')).' acrescenta '.(int)($stockData['zero_count']??0).' ruturas e '.(int)($stockData['low_count']??0).' linhas de stock baixo ao contexto.';
        $confidenceBase=$total>=10?'high':($total>0?'medium':(($todayRoutes>0||$futureRoutes>0)?'medium':'low'));
        return ['headline'=>$headline,'summary'=>$summary,'highlights'=>array_slice($highlights,0,3),'risks'=>array_slice($risks,0,3),'actions'=>array_slice($actions,0,4),'confidence'=>$confidenceBase,'source'=>'smart_rules','provider'=>'Motor analítico FieldFlow','generated_at'=>current_time('mysql'),'briefing'=>$data['briefing']??[],'stocks'=>$data['stocks']??[]];
    }

    private static function validateInsights(array $decoded, array $fallback, string $audience): array {
        $clean=static function($items,string $kind):array{$out=[];foreach((array)$items as $item){if(!is_array($item))continue;$row=['title'=>sanitize_text_field((string)($item['title']??'')),'detail'=>sanitize_textarea_field((string)($item['detail']??''))];if($row['title']===''&&$row['detail']==='')continue;if($kind==='highlight')$row['impact']=in_array(($item['impact']??''),['positive','neutral','warning'],true)?$item['impact']:'neutral';if($kind==='risk')$row['severity']=in_array(($item['severity']??''),['low','medium','high'],true)?$item['severity']:'medium';if($kind==='action')$row['priority']=in_array(($item['priority']??''),['now','next','monitor'],true)?$item['priority']:'next';$out[]=$row;if(count($out)>=($kind==='action'?4:3))break;}return $out;};
        $confidence=sanitize_key((string)($decoded['confidence']??''));if(!in_array($confidence,['low','medium','high'],true))$confidence=$fallback['confidence'];
        return ['headline'=>sanitize_text_field((string)($decoded['headline']??$fallback['headline'])),'summary'=>sanitize_textarea_field((string)($decoded['summary']??$fallback['summary'])),'highlights'=>$clean($decoded['highlights']??$fallback['highlights'],'highlight')?:$fallback['highlights'],'risks'=>$clean($decoded['risks']??$fallback['risks'],'risk'),'actions'=>$clean($decoded['actions']??$fallback['actions'],'action')?:$fallback['actions'],'confidence'=>$confidence,'audience'=>$audience];
    }


    private static function briefingContext(array $context): array {
        global $wpdb;
        $clientId = absint($context['client_id'] ?? 0);
        $projectId = absint($context['project_id'] ?? 0);
        $locationId = absint($context['location_id'] ?? 0);
        if (!$clientId && !$projectId) return [];

        $from = self::normaliseDate((string)($context['from'] ?? '')) ?: wp_date('Y-m-d', strtotime('-30 days'), wp_timezone());
        $to = self::normaliseDate((string)($context['to'] ?? '')) ?: current_time('Y-m-d');
        $briefingsTable = $wpdb->prefix . 'routespro_briefings';
        $sectionsTable = $wpdb->prefix . 'routespro_briefing_sections';
        $itemsTable = $wpdb->prefix . 'routespro_briefing_store_items';
        $questionsTable = class_exists('\\RoutesPro\\Forms\\ContextQuestions') ? \RoutesPro\Forms\ContextQuestions::table() : $wpdb->prefix . 'routespro_report_context_questions';
        foreach ([$briefingsTable, $sectionsTable, $itemsTable] as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return [];
        }

        $where = ["b.status='published'", "(b.valid_from IS NULL OR b.valid_from='' OR b.valid_from<=%s)", "(b.valid_to IS NULL OR b.valid_to='' OR b.valid_to>=%s)"];
        $args = [$to, $from];
        if ($clientId) { $where[] = 'b.client_id=%d'; $args[] = $clientId; }
        if ($projectId) { $where[] = 'b.project_id=%d'; $args[] = $projectId; }
        $sql = "SELECT b.id,b.title,b.valid_from,b.valid_to,b.week_no,b.year_no,b.version_no FROM {$briefingsTable} b WHERE " . implode(' AND ', $where) . ' ORDER BY b.valid_to DESC,b.version_no DESC,b.id DESC LIMIT 5';
        $briefings = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
        if (!$briefings) return [];

        $ids = array_values(array_unique(array_map(static fn($row) => (int)$row['id'], $briefings)));
        $idIn = implode(',', array_map('intval', $ids));
        $priorities = [];
        $questions = [];
        $sections = $wpdb->get_results("SELECT briefing_id,title,section_type,content_json FROM {$sectionsTable} WHERE briefing_id IN ({$idIn}) ORDER BY sort_order ASC,id ASC LIMIT 80", ARRAY_A) ?: [];
        foreach ($sections as $section) {
            self::pushBriefingPriority($priorities, (string)($section['title'] ?? ''));
            self::collectBriefingStrings(json_decode((string)($section['content_json'] ?? ''), true), $priorities, 18);
        }

        $itemWhere = "briefing_id IN ({$idIn})";
        if ($locationId) $itemWhere .= $wpdb->prepare(' AND (location_id=%d OR location_id IS NULL OR location_id=0)', $locationId);
        $items = $wpdb->get_results("SELECT briefing_id,title,item_type,details_json,location_id FROM {$itemsTable} WHERE {$itemWhere} ORDER BY sort_order ASC,id ASC LIMIT 100", ARRAY_A) ?: [];
        foreach ($items as $item) {
            self::pushBriefingPriority($priorities, (string)($item['title'] ?? ''));
            self::collectBriefingStrings(json_decode((string)($item['details_json'] ?? ''), true), $priorities, 18);
        }

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $questionsTable)) === $questionsTable) {
            $qWhere = "briefing_id IN ({$idIn}) AND is_active=1";
            if ($locationId) $qWhere .= $wpdb->prepare(' AND (location_id=0 OR location_id=%d)', $locationId);
            $qRows = $wpdb->get_results("SELECT DISTINCT question_label FROM {$questionsTable} WHERE {$qWhere} ORDER BY priority ASC,id ASC LIMIT 30", ARRAY_A) ?: [];
            foreach ($qRows as $row) {
                $label = sanitize_text_field((string)($row['question_label'] ?? ''));
                if ($label !== '') $questions[$label] = $label;
            }
        }

        $priorities = array_values(array_slice($priorities, 0, 12));
        $titles = array_values(array_map(static fn($row) => sanitize_text_field((string)($row['title'] ?? '')), $briefings));
        return [
            'ids' => $ids,
            'titles' => array_values(array_filter($titles)),
            'primary_title' => (string)($titles[0] ?? ''),
            'valid_from' => (string)($briefings[0]['valid_from'] ?? ''),
            'valid_to' => (string)($briefings[0]['valid_to'] ?? ''),
            'priorities' => $priorities,
            'questions' => array_values(array_slice($questions, 0, 12)),
            'matched_items' => count($items),
            'source' => 'published_briefing',
        ];
    }

    private static function publicBriefingMeta(array $briefing): array {
        if (!$briefing) return [];
        return [
            'ids' => array_values(array_map('intval', (array)($briefing['ids'] ?? []))),
            'titles' => array_values(array_map('sanitize_text_field', (array)($briefing['titles'] ?? []))),
            'primary_title' => sanitize_text_field((string)($briefing['primary_title'] ?? '')),
            'valid_from' => self::normaliseDate((string)($briefing['valid_from'] ?? '')),
            'valid_to' => self::normaliseDate((string)($briefing['valid_to'] ?? '')),
            'priorities' => array_values(array_slice(array_filter(array_map('sanitize_text_field', (array)($briefing['priorities'] ?? []))), 0, 12)),
            'questions' => array_values(array_slice(array_filter(array_map('sanitize_text_field', (array)($briefing['questions'] ?? []))), 0, 12)),
            'matched_items' => (int)($briefing['matched_items'] ?? 0),
            'source' => 'published_briefing',
        ];
    }

    private static function collectBriefingStrings($value, array &$out, int $limit = 12): void {
        if (count($out) >= $limit) return;
        if (is_array($value)) {
            foreach ($value as $child) {
                self::collectBriefingStrings($child, $out, $limit);
                if (count($out) >= $limit) break;
            }
            return;
        }
        if (!is_scalar($value)) return;
        self::pushBriefingPriority($out, (string)$value);
    }

    private static function pushBriefingPriority(array &$out, string $text): void {
        $text = sanitize_text_field(wp_strip_all_tags($text));
        $text = trim(preg_replace('/\\s+/u', ' ', $text));
        if ($text === '' || strlen($text) < 4 || preg_match('~^https?://~i', $text)) return;
        if (function_exists('mb_strlen') && mb_strlen($text) > 180) $text = mb_substr($text, 0, 177) . '...';
        elseif (strlen($text) > 180) $text = substr($text, 0, 177) . '...';
        $key = function_exists('remove_accents') ? strtolower(remove_accents($text)) : strtolower($text);
        foreach ($out as $existing) {
            $existingKey = function_exists('remove_accents') ? strtolower(remove_accents((string)$existing)) : strtolower((string)$existing);
            if ($existingKey === $key) return;
        }
        $out[] = $text;
    }

    private static function normaliseDate(string $date): string {
        $date = trim($date);
        if ($date !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) return $date;
        return '';
    }

    private static function decodeJson(string $raw): array {
        $raw=trim($raw);$raw=preg_replace('/^```(?:json)?\s*/i','',$raw);$raw=preg_replace('/\s*```$/','',(string)$raw);$decoded=json_decode((string)$raw,true);if(is_array($decoded))return $decoded;$first=strpos((string)$raw,'{');$last=strrpos((string)$raw,'}');if($first!==false&&$last!==false&&$last>$first){$decoded=json_decode(substr((string)$raw,$first,$last-$first+1),true);if(is_array($decoded))return $decoded;}throw new \RuntimeException('A resposta da IA não contém JSON válido.');
    }
}
