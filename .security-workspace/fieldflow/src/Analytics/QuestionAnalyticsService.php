<?php
namespace RoutesPro\Analytics;

use RoutesPro\Forms\ContextQuestions;
use RoutesPro\Forms\Forms;
use RoutesPro\Forms\EvidenceFlow;

if (!defined('ABSPATH')) exit;

class QuestionAnalyticsService {
    public static function bindingsTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_form_analytics_bindings';
    }

    public static function dashboardsTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_analytics_dashboards';
    }

    /**
     * Legacy metric key kept for non-briefing/contextual questions.
     */
    public static function metricKey(string $questionKey): string {
        $base = sanitize_key($questionKey);
        if ($base === '') $base = 'pergunta';
        return sanitize_key(substr($base, 0, 165) . '_analytics');
    }


    public static function analyticsBehaviorLabels(): array {
        return [
            'rollup' => 'Automático no Briefing operacional',
            'existing' => 'Associar a métrica permanente existente',
            'new' => 'Criar nova métrica permanente',
        ];
    }

    /**
     * Families that deserve a stable, reusable widget. Everything else stays
     * inside the dynamic Briefing operational rollup unless the user explicitly
     * asks for a permanent metric.
     */
    public static function strategicFamilyKey(string $label, string $questionType = ''): string {
        $type = sanitize_key($questionType);
        $text = self::normalizedLabel($label);
        if ($text === '') return '';
        $has = static fn(string $part): bool => $part !== '' && strpos($text, $part) !== false;

        // 2.2.337 semantic guardrail: a technical question type is only a
        // supporting signal. It can no longer promote an unrelated label into
        // a strategic family by itself. This prevents, for example, a generic
        // "Comparativo de referências do briefing" stored as share_space_matrix
        // from contaminating the permanent Share Space widget.
        if ($has('espaco') && $has('centraliz')) return 'briefing_espacos_centralizados';
        if ($has('comparativ') && $has('referenc') && $has('briefing')) return 'briefing_comparativo_referencias';
        if (($has('materia') || $has('material') || $has('vap')) && $has('vap')) return 'briefing_materiais_vap';
        if ($has('promoc') && ($has('vis') || $has('dispon') || $has('expost'))) return 'briefing_promocoes_visibilidade';
        if ($has('preco') && $has('linear')) return 'briefing_precos_linear';
        if ($has('stock')) return 'briefing_stock';
        if ($has('produto') && $has('dispon')) return 'briefing_produto_disponibilidade';

        $hasPrToken = (bool)preg_match('/(^|\s)pr(\s|$)/', $text);
        $shareSemantic = ($has('share') && $has('space'))
            || ($has('quota') && ($has('concorr') || $has('pernod') || $hasPrToken))
            || ($type === 'share_space_matrix' && $has('frente') && $has('concorr'));
        if ($shareSemantic) return 'briefing_share_space';

        $frontsSemantic = (($has('numero') || $has('quantidade')) && $has('frente'))
            || ($has('frente') && $has('atuais'))
            || ($type === 'product_matrix' && $has('frente'));
        if ($frontsSemantic) return 'briefing_numero_frentes';

        $orderSemantic = $has('encomend') && ($has('sugest') || $has('recomend') || $has('pedido') || $type === 'order_suggestion');
        if ($orderSemantic) return 'briefing_sugestao_encomenda';

        if ($has('problema') || $has('oportunidade')) return 'briefing_problemas_oportunidades';
        return '';
    }

    public static function strategicFamilyLabel(string $metricKey): string {
        $labels = [
            'briefing_espacos_centralizados' => 'Espaços centralizados',
            'briefing_comparativo_referencias' => 'Comparativo de referências',
            'briefing_materiais_vap' => 'Materiais VAP',
            'briefing_promocoes_visibilidade' => 'Promoções e visibilidade',
            'briefing_precos_linear' => 'Preços em linear',
            'briefing_stock' => 'Stock',
            'briefing_produto_disponibilidade' => 'Disponibilidade de produto',
            'briefing_share_space' => 'Frentes e Share Space',
            'briefing_numero_frentes' => 'Frentes atuais',
            'briefing_sugestao_encomenda' => 'Sugestão de encomenda',
            'briefing_problemas_oportunidades' => 'Problemas e oportunidades',
        ];
        return $labels[sanitize_key($metricKey)] ?? '';
    }

    public static function analyticsBehaviorFromRow(array $row, bool $inferLegacy = true): string {
        if (empty($row['analytics_enabled'])) return 'off';
        $rules = self::decodeSettings($row['visibility_rules_json'] ?? '');
        $behavior = sanitize_key((string)($rules['analytics_behavior'] ?? ''));
        if (in_array($behavior, ['rollup','existing','new'], true)) return $behavior;
        if (!$inferLegacy) return 'rollup';
        return self::isStrategicPermanentQuestion((string)($row['question_label'] ?? ''), (string)($row['question_type'] ?? '')) ? 'existing' : 'rollup';
    }

    /**
     * 2.2.337 ownership rule: only an explicit, confirmed administrator action
     * pins the Analytics behavior. Older rows that merely carry source=user
     * without the confirmation marker are treated as legacy auto-saves and may
     * be safely re-inferred by the evergreen reconciler.
     */
    public static function analyticsChoiceIsUserConfirmed(array $rules): bool {
        return sanitize_key((string)($rules['analytics_behavior_source'] ?? '')) === 'user'
            && !empty($rules['analytics_behavior_user_confirmed']);
    }

    public static function isStrategicPermanentQuestion(string $label, string $questionType = ''): bool {
        return self::strategicFamilyKey($label, $questionType) !== '';
    }

    public static function newPermanentMetricKey(string $label, string $questionType, int $clientId = 0, int $projectId = 0, int $dashboardId = 0, string $questionKey = ''): string {
        global $wpdb;
        $base = self::canonicalMetricKey($label, $questionType);
        $table = self::bindingsTable();
        if (!self::tableExists($table)) return $base;
        $rows = $wpdb->get_results("SELECT metric_key,settings_json FROM {$table} WHERE is_active=1 ORDER BY id ASC", ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $settings = self::decodeSettings($row['settings_json'] ?? '');
            if (empty($settings['evergreen_briefing'])) continue;
            if ($clientId && absint($settings['client_id'] ?? 0) && absint($settings['client_id'] ?? 0) !== $clientId) continue;
            if ($projectId && absint($settings['project_id'] ?? 0) && absint($settings['project_id'] ?? 0) !== $projectId) continue;
            if ($dashboardId && absint($settings['dashboard_id'] ?? 0) && absint($settings['dashboard_id'] ?? 0) !== $dashboardId) continue;
            $existing = sanitize_key((string)($settings['canonical_metric_key'] ?? $row['metric_key'] ?? ''));
            if ($existing === $base) {
                $seed = sanitize_key($questionKey) ?: sanitize_key($label . '_' . $questionType);
                return sanitize_key(substr($base, 0, 150) . '_' . substr(hash('sha256', $seed . '|' . wp_generate_uuid4()), 0, 10));
            }
        }
        return $base;
    }

    public static function isSmartRollupBinding(array $binding, array $settings = []): bool {
        if (!$settings) $settings = self::decodeSettings($binding['settings_json'] ?? '');
        return !empty($settings['smart_briefing_rollup'])
            || !empty($settings['smart_operational_evidence_rollup'])
            || in_array(sanitize_key((string)($binding['chart_type'] ?? '')), ['briefing_rollup','briefing_evidence_rollup'], true);
    }

    /**
     * Stable identity for recurring briefing questions. The weekly question key
     * remains immutable for audit/history, while this key remains reusable.
     */
    public static function canonicalMetricKey(string $label, string $questionType = ''): string {
        $label = trim($label);
        if ($label === '') $label = 'pergunta';
        $strategicFamily = self::strategicFamilyKey($label, $questionType);
        if ($strategicFamily !== '') return $strategicFamily;
        $semanticText = self::normalizedLabel($label);
        if (function_exists('remove_accents')) $label = remove_accents($label);
        $label = strtolower($label);
        $label = preg_replace('/\s*[\.·:\-]\s*historico\s*$/u', '', $label) ?: $label;
        $label = preg_replace('/\b(?:s|w|semana|week)\s*[-_]?\s*\d{1,2}\b/u', ' ', $label) ?: $label;
        $label = preg_replace('/[^a-z0-9]+/u', ' ', $label) ?: $label;
        $tokens = preg_split('/\s+/', trim($label)) ?: [];
        $stop = array_fill_keys([
            'o','a','os','as','um','uma','uns','umas','de','da','do','das','dos','e','ou','em','no','na','nos','nas','ao','aos','para','por','com','sem',
            'esta','estao','estar','ser','sao','foi','foram','tem','têm','ha','que','qual','quais','se','nao','sim','aplicavel','loja','lojas','pdv','pdvs',
            'briefing','historico','histórico','semana','week','semanal','atualmente','actualmente','corretamente','correctamente','devidamente'
        ], true);
        $synonyms = [
            'fotografias'=>'foto','fotografia'=>'foto','fotos'=>'foto',
            'materiais'=>'material','material'=>'material',
            'expostos'=>'exposto','expostas'=>'exposto','exposta'=>'exposto',
            'implementados'=>'implementado','implementadas'=>'implementado','implementada'=>'implementado',
            'centralizados'=>'centralizado','centralizadas'=>'centralizado','centralizada'=>'centralizado',
            'referencias'=>'referencia','referência'=>'referencia','referências'=>'referencia',
            'comparativos'=>'comparativo','comparativa'=>'comparativo','comparativas'=>'comparativo',
            'folhetos'=>'folheto',
            'precos'=>'preco','preços'=>'preco','preço'=>'preco',
            'promocoes'=>'promocao','promoções'=>'promocao','promoção'=>'promocao',
            'visiveis'=>'visivel','visíveis'=>'visivel','visibilidade'=>'visivel',
            'disponiveis'=>'visivel','disponíveis'=>'visivel','disponivel'=>'visivel','disponível'=>'visivel','disponibilidade'=>'visivel',
            'espacos'=>'espaco','espaços'=>'espaco','espaço'=>'espaco',
            'acoes'=>'acao','ações'=>'acao','ação'=>'acao',
            'corretos'=>'correto','corretas'=>'correto','correta'=>'correto',
            'conformes'=>'conforme','conformidade'=>'conforme',
            'implementacao'=>'implementado','implementacoes'=>'implementado',
            'esgotados'=>'esgotado','esgotadas'=>'esgotado','esgotada'=>'esgotado',
            'frentes'=>'frente',
        ];
        $kept = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '' || isset($stop[$token]) || ctype_digit($token)) continue;
            $token = $synonyms[$token] ?? $token;
            if ($token === '' || isset($stop[$token])) continue;
            $kept[] = $token;
        }
        $kept = array_values(array_unique($kept));
        if (!$kept) $kept = ['pergunta'];
        $base = sanitize_key(implode('_', $kept));
        if ($base === '') $base = 'pergunta';

        $tokenMap = array_fill_keys($kept, true);
        $contains = static function(string $prefix) use ($kept): bool {
            foreach ($kept as $token) if (strpos($token, $prefix) === 0) return true;
            return false;
        };
        $semanticHas = static function(string $part) use ($semanticText): bool {
            return $part !== '' && strpos($semanticText, $part) !== false;
        };
        if ($semanticHas('espaco') && $semanticHas('centraliz')) $base = 'espacos_centralizados';
        elseif ($semanticHas('materia') && $semanticHas('vap')) $base = 'materiais_vap';
        elseif ($semanticHas('promoc') && ($semanticHas('vis') || $semanticHas('dispon'))) $base = 'promocoes_visibilidade';
        elseif ($semanticHas('preco') && $semanticHas('linear')) $base = 'precos_linear';
        elseif ($semanticHas('stock')) $base = 'stock';
        elseif ($semanticHas('produto') && $semanticHas('dispon')) $base = 'produto_disponibilidade';
        elseif (($semanticHas('share') && $semanticHas('space')) || ($semanticHas('frente') && $semanticHas('share'))) $base = 'share_space';
        elseif ($semanticHas('numero') && $semanticHas('frente')) $base = 'numero_frentes';

        $type = sanitize_key($questionType);
        $family = '';
        if (in_array($type, ['image_upload','categorized_image_upload','file_upload'], true)) $family = 'imagem';
        elseif ($type === 'evidence_check') $family = 'evidencia';
        elseif ($type === 'share_space_matrix') $family = 'share_space';
        elseif ($type === 'product_matrix') $family = 'frentes';
        elseif ($type === 'order_suggestion') $family = 'encomenda';
        $strategic = ['espacos_centralizados','materiais_vap','promocoes_visibilidade','precos_linear','stock','produto_disponibilidade','share_space','numero_frentes'];
        if ($family !== '' && !in_array($base, $strategic, true)) $base .= '_' . $family;

        return sanitize_key('briefing_' . substr($base, 0, 165));
    }

    public static function suggestCanonicalMetricKey(string $label, string $questionType, int $clientId = 0, int $projectId = 0, int $dashboardId = 0): string {
        global $wpdb;
        $candidate = self::canonicalMetricKey($label, $questionType);
        $table = self::bindingsTable();
        if (!self::tableExists($table)) return $candidate;
        $rows = $wpdb->get_results("SELECT metric_key,metric_label,settings_json FROM {$table} ORDER BY is_active DESC,id ASC", ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $settings = self::decodeSettings($row['settings_json'] ?? '');
            if (self::isSmartRollupBinding($row, $settings)) continue;
            if (empty($settings['managed_question_analytics']) || empty($settings['evergreen_briefing'])) continue;
            $rowClient = absint($settings['client_id'] ?? 0);
            $rowProject = absint($settings['project_id'] ?? 0);
            $rowDashboard = absint($settings['dashboard_id'] ?? 0);
            if ($clientId && $rowClient && $rowClient !== $clientId) continue;
            if ($projectId && $rowProject && $rowProject !== $projectId) continue;
            if ($dashboardId && $rowDashboard && $rowDashboard !== $dashboardId) continue;
            $existingCanonical = sanitize_key((string)($settings['canonical_metric_key'] ?? ''));
            if ($existingCanonical === '') {
                $existingCanonical = self::canonicalMetricKey((string)($row['metric_label'] ?? ''), (string)($settings['question_type'] ?? ''));
            }
            if ($existingCanonical === $candidate) return sanitize_key((string)($settings['canonical_metric_key'] ?? $row['metric_key'] ?? $candidate)) ?: $candidate;
        }
        return $candidate;
    }

    public static function preferredBriefingDashboardId(int $clientId = 0, int $projectId = 0, bool $create = true): int {
        global $wpdb;
        $table = self::dashboardsTable();
        if (!self::tableExists($table)) return 0;
        $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE is_active=1 ORDER BY sort_order ASC,id ASC", ARRAY_A) ?: [];
        $bestId = 0;
        $bestScore = -1;
        foreach ($rows as $row) {
            $rowClient = absint($row['client_id'] ?? 0);
            $rowProject = absint($row['project_id'] ?? 0);
            if ($clientId && $rowClient && $rowClient !== $clientId) continue;
            if ($projectId && $rowProject && $rowProject !== $projectId) continue;
            $title = strtolower(function_exists('remove_accents') ? remove_accents((string)($row['title'] ?? '')) : (string)($row['title'] ?? ''));
            $title = trim(preg_replace('/\s+/', ' ', $title) ?: $title);
            $score = 0;
            if ($title === 'briefing semanal') $score += 120;
            elseif (strpos($title, 'briefing') !== false) $score += 70;
            else continue;
            if ($clientId && $rowClient === $clientId) $score += 20;
            if ($projectId && $rowProject === $projectId) $score += 30;
            if (in_array((string)($row['visibility'] ?? ''), ['both','all'], true)) $score += 5;
            if ($score > $bestScore) { $bestScore = $score; $bestId = (int)$row['id']; }
        }
        if ($bestId || !$create) return $bestId;

        $result = $wpdb->insert($table, [
            'client_id' => $clientId ?: null,
            'project_id' => $projectId ?: null,
            'route_id' => null,
            'title' => 'Briefing semanal',
            'description' => 'Dashboard permanente para perguntas recorrentes dos briefings, com histórico consolidado por métrica.',
            'visibility' => 'both',
            'layout_type' => 'mixed',
            'sort_order' => 10,
            'is_active' => 1,
            'ai_enabled' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        return $result === false ? 0 : (int)$wpdb->insert_id;
    }

    public static function availableMetricOptions(int $clientId = 0, int $projectId = 0, int $dashboardId = 0): array {
        global $wpdb;
        $table = self::bindingsTable();
        if (!self::tableExists($table)) return [];
        $rows = $wpdb->get_results("SELECT id,question_key,metric_key,metric_label,settings_json,is_active FROM {$table} ORDER BY is_active DESC,id ASC", ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $settings = self::decodeSettings($row['settings_json'] ?? '');
            if (self::isSmartRollupBinding($row, $settings)) continue;
            if (empty($settings['managed_question_analytics']) || empty($settings['evergreen_briefing'])) continue;
            $rowClient = absint($settings['client_id'] ?? 0);
            $rowProject = absint($settings['project_id'] ?? 0);
            $rowDashboard = absint($settings['dashboard_id'] ?? 0);
            if ($clientId && $rowClient && $rowClient !== $clientId) continue;
            if ($projectId && $rowProject && $rowProject !== $projectId) continue;
            if ($dashboardId && $rowDashboard && $rowDashboard !== $dashboardId) continue;
            $key = sanitize_key((string)($settings['canonical_metric_key'] ?? $row['metric_key'] ?? ''));
            if ($key === '') continue;
            $keys = self::sanitizeKeyList($settings['managed_question_keys'] ?? [$row['question_key'] ?? '']);
            if (!isset($out[$key])) {
                $out[$key] = [
                    'key' => $key,
                    'label' => self::cleanMetricLabel((string)($row['metric_label'] ?? $key)),
                    'question_count' => count($keys),
                    'dashboard_id' => $rowDashboard,
                ];
            } else {
                $out[$key]['question_count'] = max((int)$out[$key]['question_count'], count($keys));
            }
        }
        uasort($out, static fn(array $a, array $b): int => strcasecmp((string)$a['label'], (string)$b['label']));
        return array_values($out);
    }


    public static function ensureSmartBriefingBindings(int $clientId = 0, int $projectId = 0, int $dashboardId = 0, array $formIds = []): array {
        global $wpdb;
        $table = self::bindingsTable();
        if (!self::tableExists($table)) return ['synced'=>0,'created'=>0,'updated'=>0];
        if (!$dashboardId) $dashboardId = self::preferredBriefingDashboardId($clientId, $projectId, true);
        $formIds = array_values(array_unique(array_filter(array_map('absint', $formIds))));
        if (!$formIds) {
            $context = ContextQuestions::table();
            if (self::tableExists($context)) {
                $where = ["analytics_enabled=1", "(source_type='briefing' OR briefing_id>0)"];
                $args = [];
                if ($clientId) { $where[]='client_id=%d'; $args[]=$clientId; }
                if ($projectId) { $where[]='project_id=%d'; $args[]=$projectId; }
                $sql = 'SELECT DISTINCT form_id FROM ' . $context . ' WHERE ' . implode(' AND ', $where);
                $rows = $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
                foreach ($rows as $row) if (absint($row['form_id'] ?? 0)) $formIds[] = absint($row['form_id']);
            }
        }
        if (!$formIds) return ['synced'=>0,'created'=>0,'updated'=>0,'dashboard_id'=>$dashboardId];

        $configs = [
            [
                'question_key'=>'__briefing_operational_rollup', 'metric_key'=>'briefing_operacional',
                'metric_label'=>'Briefing operacional', 'chart_type'=>'briefing_rollup', 'aggregation'=>'count',
                'sort_order'=>15, 'kind'=>'operational', 'show_table'=>0,
            ],
            [
                'question_key'=>'__briefing_evidence_rollup', 'metric_key'=>'briefing_evidencias',
                'metric_label'=>'Evidências do briefing', 'chart_type'=>'briefing_evidence_rollup', 'aggregation'=>'evidence',
                'sort_order'=>25, 'kind'=>'evidence', 'show_table'=>0,
            ],
        ];
        $created=0; $updated=0;
        foreach ($formIds as $formId) {
            foreach ($configs as $config) {
                $settings = [
                    'client_id'=>$clientId, 'project_id'=>$projectId, 'route_id'=>0, 'dashboard_id'=>$dashboardId,
                    'store_group_id'=>0, 'secondary_dimension'=>'', 'evidence_grouping'=>'location_date',
                    'evidence_show_thumbnails'=>1, 'question_type'=>$config['kind']==='evidence'?'image_upload':'briefing_rollup',
                    'show_kpi'=>0, 'show_table'=>$config['show_table'], 'show_empty'=>1,
                    'sort_order'=>$config['sort_order'], 'briefing_priority'=>1,
                    'managed_question_analytics'=>1, 'managed_question_key'=>$config['question_key'],
                    'managed_question_keys'=>[$config['question_key']], 'source_type'=>'briefing', 'briefing_id'=>0,
                    'briefing_ids'=>[], 'evergreen_briefing'=>1, 'canonical_metric_key'=>$config['metric_key'],
                    'smart_briefing_rollup'=>1, 'briefing_rollup_kind'=>$config['kind'],
                ];
                $data = [
                    'form_id'=>$formId, 'question_key'=>$config['question_key'], 'metric_key'=>$config['metric_key'],
                    'metric_label'=>$config['metric_label'], 'chart_type'=>$config['chart_type'],
                    'aggregation'=>$config['aggregation'], 'dimension'=>'location_id', 'scope_mode'=>$clientId&&$projectId?'client_project_location':($clientId?'client_only':'global'),
                    'settings_json'=>wp_json_encode($settings, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 'is_active'=>1,
                ];
                $existingId = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE form_id=%d AND question_key=%s AND metric_key=%s ORDER BY id DESC LIMIT 1", $formId, $config['question_key'], $config['metric_key']));
                if ($existingId) { if ($wpdb->update($table,$data,['id'=>$existingId])!==false) $updated++; }
                else { if ($wpdb->insert($table,$data)!==false) $created++; }
            }
        }
        return ['synced'=>$created+$updated,'created'=>$created,'updated'=>$updated,'dashboard_id'=>$dashboardId,'forms'=>count($formIds)];
    }


    /**
     * Compacts stable, non-briefing widgets that naturally belong together on
     * the permanent Briefing dashboard. It is intentionally conservative:
     * only exact scope matches are merged and source widgets are deactivated,
     * never deleted.
     */
    public static function compactOperationalDashboardBindings(int $filterClientId = 0, int $filterProjectId = 0): array {
        global $wpdb;
        $table = self::bindingsTable();
        $dashTable = self::dashboardsTable();
        if (!self::tableExists($table) || !self::tableExists($dashTable)) return ['families'=>0,'evidence_rollups'=>0,'deactivated'=>0,'synced'=>0];

        $dashRows = $wpdb->get_results("SELECT id,title,client_id,project_id FROM {$dashTable} WHERE is_active=1 ORDER BY id ASC", ARRAY_A) ?: [];
        $briefingDashboards = [];
        foreach ($dashRows as $dash) {
            $title = self::normalizedLabel((string)($dash['title'] ?? ''));
            if (strpos($title, 'briefing') === false) continue;
            $dc = absint($dash['client_id'] ?? 0); $dp = absint($dash['project_id'] ?? 0);
            if ($filterClientId && $dc && $dc !== $filterClientId) continue;
            if ($filterProjectId && $dp && $dp !== $filterProjectId) continue;
            $briefingDashboards[(int)$dash['id']] = $dash;
        }
        if (!$briefingDashboards) return ['families'=>0,'evidence_rollups'=>0,'deactivated'=>0,'synced'=>0];

        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY is_active DESC,id ASC", ARRAY_A) ?: [];
        $scopeSignature = static function(array $row, array $settings): string {
            return implode(':', [
                absint($row['form_id'] ?? 0), absint($settings['dashboard_id'] ?? 0),
                absint($settings['client_id'] ?? 0), absint($settings['project_id'] ?? 0),
                absint($settings['route_id'] ?? 0), absint($settings['store_group_id'] ?? 0),
                sanitize_key((string)($row['scope_mode'] ?? 'client_project_location')),
            ]);
        };
        $isBriefingSource = static function(array $row, array $settings): bool {
            $q = sanitize_key((string)($row['question_key'] ?? ''));
            return !empty($settings['evergreen_briefing'])
                || sanitize_key((string)($settings['source_type'] ?? '')) === 'briefing'
                || strpos($q, 'briefing_') === 0
                || strpos($q, '__briefing_') === 0;
        };
        $operationalFamily = static function(array $row, array $settings): string {
            $type = sanitize_key((string)($settings['question_type'] ?? ''));
            if (in_array($type, ['share_space_matrix','product_matrix','order_suggestion','image_upload','categorized_image_upload','file_upload'], true)) return '';
            $text = QuestionAnalyticsService::normalizedLabel((string)($row['metric_label'] ?? '') . ' ' . (string)($row['question_key'] ?? ''));
            if (strpos($text, 'problema') !== false || strpos($text, 'oportunidade') !== false) return 'problemas_oportunidades';
            if (strpos($text, 'stock') !== false) return 'stock_disponibilidade';
            if (strpos($text, 'produto') !== false && strpos($text, 'dispon') !== false) return 'stock_disponibilidade';
            return '';
        };
        $familyMeta = [
            'problemas_oportunidades' => ['metric_key'=>'problemas_oportunidades','metric_label'=>'Problemas e oportunidades'],
            'stock_disponibilidade' => ['metric_key'=>'stock_disponibilidade','metric_label'=>'Stock e disponibilidade'],
        ];

        $familyGroups = [];
        $evidenceGroups = [];
        $existingEvidenceRollups = [];
        foreach ($rows as $row) {
            if (empty($row['is_active'])) continue;
            $settings = self::decodeSettings($row['settings_json'] ?? '');
            if (!empty($settings['smart_operational_evidence_rollup'])) {
                $existingEvidenceRollups[$scopeSignature($row,$settings)] = ['row'=>$row,'settings'=>$settings];
                continue;
            }
            if (self::isSmartRollupBinding($row, $settings)) continue;
            $dashboardId = absint($settings['dashboard_id'] ?? 0);
            if (!$dashboardId || empty($briefingDashboards[$dashboardId])) continue;
            $rc = absint($settings['client_id'] ?? 0); $rp = absint($settings['project_id'] ?? 0);
            if ($filterClientId && $rc && $rc !== $filterClientId) continue;
            if ($filterProjectId && $rp && $rp !== $filterProjectId) continue;
            if ($isBriefingSource($row, $settings)) continue;
            $qKey = sanitize_key((string)($row['question_key'] ?? ''));
            if ($qKey === '' || strpos($qKey, '__') === 0) continue;
            $signature = $scopeSignature($row, $settings);

            $type = sanitize_key((string)($settings['question_type'] ?? ''));
            $isEvidence = (string)($row['chart_type'] ?? '') === 'image_gallery' || in_array($type, ['image_upload','categorized_image_upload','file_upload'], true);
            if ($isEvidence) {
                $evidenceGroups[$signature][] = ['row'=>$row,'settings'=>$settings];
                continue;
            }
            $family = $operationalFamily($row, $settings);
            if ($family !== '') $familyGroups[$signature . '|' . $family][] = ['row'=>$row,'settings'=>$settings,'family'=>$family];
        }

        $families = 0; $evidenceRollups = 0; $deactivated = 0; $synced = 0;
        foreach ($familyGroups as $items) {
            $keys = []; $labels = []; $allRows = [];
            foreach ($items as $item) {
                $row = $item['row']; $settings = $item['settings'];
                foreach (self::sanitizeKeyList(array_merge([(string)($row['question_key'] ?? '')], (array)($settings['managed_question_keys'] ?? []))) as $key) $keys[$key] = $key;
                $label = self::cleanMetricLabel((string)($row['metric_label'] ?? ''));
                if ($label !== '') $labels[$label] = $label;
                $allRows[] = $item;
            }
            if (count($keys) < 2) continue;
            usort($allRows, static function(array $a,array $b): int {
                $af = !empty($a['settings']['operational_family_rollup']) ? 0 : 1;
                $bf = !empty($b['settings']['operational_family_rollup']) ? 0 : 1;
                if ($af !== $bf) return $af <=> $bf;
                $ao = (int)($a['settings']['sort_order'] ?? 9999); $bo = (int)($b['settings']['sort_order'] ?? 9999);
                return $ao === $bo ? ((int)$a['row']['id'] <=> (int)$b['row']['id']) : ($ao <=> $bo);
            });
            $keep = $allRows[0]; $row = $keep['row']; $settings = $keep['settings']; $family = (string)$keep['family'];
            $meta = $familyMeta[$family] ?? null; if (!$meta) continue;
            $settings['managed_question_keys'] = array_values($keys);
            $settings['managed_question_labels'] = array_values($labels);
            $settings['operational_family_rollup'] = 1;
            $settings['operational_family_key'] = $family;
            $settings['canonical_metric_key'] = $meta['metric_key'];
            $settings['show_kpi'] = 0; $settings['show_table'] = 1; $settings['show_empty'] = 1;
            $settings['question_type'] = 'text';
            $settings['sort_order'] = min(array_map(static fn(array $i): int => (int)($i['settings']['sort_order'] ?? 9999), $allRows));
            $data = [
                'metric_key'=>$meta['metric_key'], 'metric_label'=>$meta['metric_label'], 'chart_type'=>'table',
                'aggregation'=>'latest', 'dimension'=>'location_id',
                'settings_json'=>wp_json_encode($settings, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 'is_active'=>1,
            ];
            if ($wpdb->update($table, $data, ['id'=>(int)$row['id']]) !== false) { $families++; $synced++; }
            foreach (array_slice($allRows,1) as $other) {
                $oid = (int)($other['row']['id'] ?? 0); if (!$oid || empty($other['row']['is_active'])) continue;
                if ($wpdb->update($table,['is_active'=>0],['id'=>$oid],['%d'],['%d']) !== false) $deactivated++;
            }
        }

        foreach ($evidenceGroups as $signature=>$items) {
            $keys = []; $labelsByKey = []; $sourceRows = [];
            $existing = $existingEvidenceRollups[$signature] ?? null;
            if ($existing) {
                foreach (self::sanitizeKeyList((array)($existing['settings']['managed_question_keys'] ?? [])) as $key) $keys[$key] = $key;
                foreach ((array)($existing['settings']['managed_question_labels_by_key'] ?? []) as $key=>$label) {
                    $key=sanitize_key((string)$key); if($key!=='')$labelsByKey[$key]=self::cleanMetricLabel((string)$label)?:$key;
                }
            }
            foreach ($items as $item) {
                $row = $item['row']; $settings = $item['settings'];
                $qKey = sanitize_key((string)($row['question_key'] ?? '')); if ($qKey === '') continue;
                $keys[$qKey] = $qKey;
                foreach (self::sanitizeKeyList((array)($settings['managed_question_keys'] ?? [])) as $key) $keys[$key] = $key;
                $labelsByKey[$qKey] = self::cleanMetricLabel((string)($row['metric_label'] ?? $qKey)) ?: $qKey;
                $sourceRows[] = $item;
            }
            if (count($keys) < 2 || !$sourceRows) continue;
            $first = $sourceRows[0]; $baseRow = $first['row']; $baseSettings = $first['settings'];
            $existingId = $existing ? (int)($existing['row']['id'] ?? 0) : 0;
            $existingSettings = $existing ? (array)($existing['settings'] ?? []) : [];
            $settings = array_merge($existingSettings, $baseSettings, [
                'managed_question_analytics'=>1, 'managed_question_key'=>'__operational_evidence_rollup',
                'managed_question_keys'=>array_values($keys), 'managed_question_labels_by_key'=>$labelsByKey,
                'smart_operational_evidence_rollup'=>1, 'smart_briefing_rollup'=>1, 'briefing_rollup_kind'=>'operational_evidence',
                'canonical_metric_key'=>'evidencias_operacionais', 'source_type'=>'analytics', 'evergreen_briefing'=>0,
                'question_type'=>'image_upload', 'show_kpi'=>0, 'show_table'=>0, 'show_empty'=>1,
                'evidence_grouping'=>'location_date', 'evidence_show_thumbnails'=>1,
                'sort_order'=>130,
            ]);
            $data = [
                'form_id'=>(int)$baseRow['form_id'], 'question_key'=>'__operational_evidence_rollup',
                'metric_key'=>'evidencias_operacionais', 'metric_label'=>'Evidências operacionais',
                'chart_type'=>'briefing_evidence_rollup', 'aggregation'=>'evidence', 'dimension'=>'location_id',
                'scope_mode'=>(string)($baseRow['scope_mode'] ?? 'client_project_location'),
                'settings_json'=>wp_json_encode($settings, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 'is_active'=>1,
            ];
            $ok = $existingId ? $wpdb->update($table,$data,['id'=>$existingId]) : $wpdb->insert($table,$data);
            if ($ok !== false) { $evidenceRollups++; $synced++; }
            foreach ($sourceRows as $source) {
                $sid = (int)($source['row']['id'] ?? 0); if (!$sid || $sid === $existingId || empty($source['row']['is_active'])) continue;
                if ($wpdb->update($table,['is_active'=>0],['id'=>$sid],['%d'],['%d']) !== false) $deactivated++;
            }
        }
        return ['families'=>$families,'evidence_rollups'=>$evidenceRollups,'deactivated'=>$deactivated,'synced'=>$synced];
    }

    public static function defaultsForType(string $questionType, array $overrides = []): array {
        $questionType = sanitize_key($questionType) ?: 'text';
        $chart = sanitize_key((string)($overrides['chart_type'] ?? ''));
        $aggregation = sanitize_key((string)($overrides['aggregation'] ?? ''));
        $dimension = sanitize_key((string)($overrides['dimension'] ?? ''));
        $secondary = sanitize_key((string)($overrides['secondary_dimension'] ?? ''));

        if ($chart === '') $chart = in_array($questionType, ['image_upload','file_upload','categorized_image_upload'], true) ? 'image_gallery' : (in_array($questionType, ['text','textarea','select','radio','checkbox','date','time','evidence_check'], true) ? 'bar' : 'kpi');
        if ($aggregation === '') $aggregation = in_array($questionType, ['image_upload','file_upload','categorized_image_upload'], true) ? 'evidence' : (in_array($questionType, ['number','currency','percent'], true) ? 'avg' : 'count');
        if ($dimension === '') $dimension = 'location_id';
        if ($secondary === '' && in_array($questionType, ['image_upload','file_upload','categorized_image_upload'], true)) $secondary = 'submitted_at';

        if (in_array($questionType, ['image_upload','file_upload','categorized_image_upload'], true)) {
            $chart = 'image_gallery';
            $aggregation = 'evidence';
            $dimension = 'location_id';
            if ($secondary === '' || $secondary === 'location_id') $secondary = 'submitted_at';
        }
        if (in_array($questionType, ['product_matrix','order_suggestion'], true)) {
            if ($chart === 'image_gallery') $chart = 'table';
            $dimension = 'location_id';
        }
        if ($questionType === 'share_space_matrix') {
            $chart = 'table';
            $aggregation = 'latest';
            $dimension = 'location_id';
            $secondary = '';
        }

        $allowedCharts = ['line','bar','pie','image_gallery','table','kpi'];
        $allowedAggregations = ['latest','sum','avg','count','evidence'];
        $allowedDimensions = ['submitted_at','location_id','route_id','project_id','client_id'];
        if (!in_array($chart, $allowedCharts, true)) $chart = 'bar';
        if (!in_array($aggregation, $allowedAggregations, true)) $aggregation = 'count';
        if (!in_array($dimension, $allowedDimensions, true)) $dimension = 'location_id';
        if ($secondary !== '' && !in_array($secondary, $allowedDimensions, true)) $secondary = '';

        return [
            'chart_type' => $chart,
            'aggregation' => $aggregation,
            'dimension' => $dimension,
            'secondary_dimension' => $secondary,
            'show_kpi' => array_key_exists('show_kpi', $overrides) ? (int)!empty($overrides['show_kpi']) : (($chart === 'kpi' || $questionType === 'share_space_matrix') ? 1 : 0),
            'show_table' => array_key_exists('show_table', $overrides) ? (int)!empty($overrides['show_table']) : (in_array($questionType, ['product_matrix','order_suggestion','share_space_matrix'], true) ? 1 : 0),
            'show_empty' => array_key_exists('show_empty', $overrides) ? (int)!empty($overrides['show_empty']) : 1,
        ];
    }

    public static function syncForQuestionKey(string $questionKey, int $briefingId = 0): array {
        global $wpdb;
        $questionKey = sanitize_key($questionKey);
        if ($questionKey === '') return ['synced' => 0, 'deactivated' => 0, 'message' => 'Chave da pergunta em falta.'];
        $table = ContextQuestions::table();
        $where = ['question_key=%s'];
        $args = [$questionKey];
        if ($briefingId > 0) { $where[] = 'briefing_id=%d'; $args[] = $briefingId; }
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $table . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY is_active DESC,id ASC', ...$args), ARRAY_A) ?: [];
        if (!$rows) return self::deactivateManaged($questionKey, []);
        return self::syncRows($rows);
    }

    public static function syncRows(array $rows): array {
        global $wpdb;
        $first = (array)($rows[0] ?? []);
        $questionKey = sanitize_key((string)($first['question_key'] ?? ''));
        if ($questionKey === '') return ['synced' => 0, 'deactivated' => 0, 'message' => 'Chave da pergunta em falta.'];

        $enabled = !empty($first['analytics_enabled']) && !empty($first['is_active']);
        $formIds = $enabled ? self::targetFormIds($first) : [];
        $deactivation = self::deactivateManaged($questionKey, $formIds);
        if (!$enabled) return $deactivation + ['synced' => 0, 'message' => 'Analytics desativado para a pergunta.'];
        if (!$formIds) return $deactivation + ['synced' => 0, 'message' => 'Seleciona um formulário, ou adiciona um Bloco de Briefing a um formulário ativo.'];

        $type = sanitize_key((string)($first['question_type'] ?? 'text')) ?: 'text';
        $sourceType = sanitize_key((string)($first['source_type'] ?? 'manual')) ?: 'manual';
        $briefingId = absint($first['briefing_id'] ?? 0);
        $isBriefing = $sourceType === 'briefing' || $briefingId > 0;
        $clientId = absint($first['client_id'] ?? 0);
        $projectId = absint($first['project_id'] ?? 0);
        $dashboardId = absint($first['analytics_dashboard_id'] ?? 0);
        if ($isBriefing && !$dashboardId) $dashboardId = self::preferredBriefingDashboardId($clientId, $projectId, true);

        $behavior = $isBriefing ? self::analyticsBehaviorFromRow($first, true) : 'existing';
        if ($isBriefing && $behavior === 'rollup') {
            self::persistQuestionAnalyticsBehavior($questionKey, $briefingId, 'rollup', '', $dashboardId);
            $deact = self::deactivateManaged($questionKey, []);
            $smart = self::ensureSmartBriefingBindings($clientId, $projectId, $dashboardId, $formIds);
            return [
                'synced'=>(int)($smart['synced'] ?? 0),
                'deactivated'=>(int)($deact['deactivated'] ?? 0),
                'message'=>'Pergunta reunida no widget dinâmico Briefing operacional.',
                'metric_key'=>'', 'dashboard_id'=>$dashboardId,
            ];
        }

        $metricKey = sanitize_key((string)($first['analytics_metric_key'] ?? ''));
        if ($metricKey === '') {
            if ($isBriefing && $behavior === 'new') $metricKey = self::newPermanentMetricKey((string)($first['question_label'] ?? $questionKey), $type, $clientId, $projectId, $dashboardId, $questionKey);
            else $metricKey = $isBriefing
                ? self::suggestCanonicalMetricKey((string)($first['question_label'] ?? $questionKey), $type, $clientId, $projectId, $dashboardId)
                : self::metricKey($questionKey);
        }
        if ($isBriefing && strpos($metricKey, 'briefing_') !== 0) $metricKey = sanitize_key($metricKey);
        if ($metricKey === '') $metricKey = self::metricKey($questionKey);

        $behaviorRules = self::decodeSettings($first['visibility_rules_json'] ?? '');
        $userPinned = self::analyticsChoiceIsUserConfirmed($behaviorRules);
        $familyKey = $isBriefing ? self::strategicFamilyKey((string)($first['question_label'] ?? $questionKey), $type) : '';
        if ($isBriefing && $behavior === 'existing' && $familyKey !== '' && !$userPinned) $metricKey = $familyKey;
        $forcedMetricLabel = ($isBriefing && $familyKey !== '' && !$userPinned) ? self::strategicFamilyLabel($familyKey) : '';
        $metricLabel = $forcedMetricLabel !== '' ? $forcedMetricLabel : self::cleanMetricLabel((string)($first['analytics_metric_label'] ?? $first['question_label'] ?? $questionKey));
        if ($metricLabel === '') $metricLabel = self::cleanMetricLabel((string)($first['question_label'] ?? $questionKey));
        $priority = max(1, min(9999, (int)($first['analytics_sort_order'] ?? $first['priority'] ?? 10)));
        $scopeMode = $clientId && $projectId ? 'client_project_location' : ($clientId ? 'client_only' : 'global');
        $defaults = self::defaultsForType($type, [
            'chart_type' => $first['analytics_chart_type'] ?? '',
            'aggregation' => $first['analytics_aggregation'] ?? '',
            'dimension' => $first['analytics_dimension'] ?? '',
            'secondary_dimension' => $first['analytics_secondary_dimension'] ?? '',
            'show_kpi' => $first['analytics_show_kpi'] ?? 0,
            'show_table' => $first['analytics_show_table'] ?? 0,
            'show_empty' => array_key_exists('analytics_show_empty', $first) ? $first['analytics_show_empty'] : 1,
        ]);

        if ($isBriefing) self::persistQuestionAnalyticsBehavior($questionKey, $briefingId, in_array($behavior,['existing','new'],true)?$behavior:'existing', $metricKey, $dashboardId);

        $synced = 0;
        $duplicates = 0;
        $bindingsTable = self::bindingsTable();
        foreach ($formIds as $formId) {
            $historical = $isBriefing ? self::historicalMetricSources($metricKey, $clientId, $projectId, (int)$formId) : ['question_keys'=>[$questionKey],'labels'=>[],'briefing_ids'=>[],'types'=>[$questionKey=>$type],'evidence_keys'=>[]];
            $managedKeys = self::sanitizeKeyList(array_merge([$questionKey], (array)($historical['question_keys'] ?? [])));
            $managedTypes = (array)($historical['types'] ?? []); $managedTypes[$questionKey] = $type;
            $managedEvidenceKeys = self::sanitizeKeyList((array)($historical['evidence_keys'] ?? []));
            if ($type === 'evidence_check') $managedEvidenceKeys = self::sanitizeKeyList(array_merge($managedEvidenceKeys,[EvidenceFlow::photo_key_for_parent($questionKey)]));
            elseif (in_array($type,['image_upload','categorized_image_upload','file_upload'],true)) $managedEvidenceKeys = self::sanitizeKeyList(array_merge($managedEvidenceKeys,[$questionKey]));
            $sourceLabels = array_values(array_unique(array_filter(array_map('sanitize_text_field', (array)($historical['labels'] ?? [])))));
            if ($metricLabel !== '') array_unshift($sourceLabels, $metricLabel);
            $sourceLabels = array_values(array_unique(array_filter($sourceLabels)));
            $briefingIds = array_values(array_unique(array_filter(array_map('absint', array_merge([$briefingId], (array)($historical['briefing_ids'] ?? []))))));

            $candidates = $isBriefing ? self::managedBindingsForMetric((int)$formId, $metricKey, $clientId, $projectId, $managedKeys, $type) : [];
            $keep = self::chooseManagedBinding($candidates, $dashboardId, $metricKey);
            $existingId = (int)($keep['id'] ?? 0);
            if (!$existingId) $existingId = self::managedBindingId((int)$formId, $questionKey);
            $existingSettings = $existingId ? self::bindingSettingsById($existingId) : [];
            $existingRow = $existingId ? self::bindingRowById($existingId) : [];
            $existingCanonical = sanitize_key((string)($existingSettings['canonical_metric_key'] ?? $existingRow['metric_key'] ?? ''));
            $systemFamilyChanged = $isBriefing
                && $existingId > 0
                && !$userPinned
                && $existingCanonical !== ''
                && $existingCanonical !== $metricKey;
            $preserveExistingVisual = $isBriefing && $existingId > 0 && !$systemFamilyChanged;

            foreach ($candidates as $candidate) {
                $candidateSettings = self::decodeSettings($candidate['settings_json'] ?? '');
                $managedKeys = self::sanitizeKeyList(array_merge($managedKeys, (array)($candidateSettings['managed_question_keys'] ?? []), [(string)($candidateSettings['managed_question_key'] ?? ''), (string)($candidate['question_key'] ?? '')]));
                foreach ((array)($candidateSettings['managed_question_types'] ?? []) as $k=>$v) { $k=sanitize_key((string)$k); if($k!=='')$managedTypes[$k]=sanitize_key((string)$v)?:'text'; }
                $managedEvidenceKeys = self::sanitizeKeyList(array_merge($managedEvidenceKeys,(array)($candidateSettings['managed_evidence_question_keys'] ?? [])));
                $briefingIds = array_values(array_unique(array_filter(array_map('absint', array_merge($briefingIds, (array)($candidateSettings['briefing_ids'] ?? []), [absint($candidateSettings['briefing_id'] ?? 0)])))));
            }

            $effectiveDashboardId = $dashboardId ?: absint($existingSettings['dashboard_id'] ?? 0);
            if ($isBriefing && !$effectiveDashboardId) $effectiveDashboardId = self::preferredBriefingDashboardId($clientId, $projectId, true);
            $settings = array_merge($existingSettings, [
                'client_id' => $clientId,
                'project_id' => $projectId,
                'route_id' => absint($existingSettings['route_id'] ?? 0),
                'dashboard_id' => $effectiveDashboardId,
                'store_group_id' => absint($existingSettings['store_group_id'] ?? 0),
                'secondary_dimension' => $defaults['secondary_dimension'],
                'evidence_grouping' => sanitize_key((string)($existingSettings['evidence_grouping'] ?? 'location_date')) ?: 'location_date',
                'evidence_show_thumbnails' => array_key_exists('evidence_show_thumbnails', $existingSettings) ? (int)!empty($existingSettings['evidence_show_thumbnails']) : 1,
                'question_type' => $type,
                'show_kpi' => $defaults['show_kpi'],
                'show_table' => $defaults['show_table'],
                'show_empty' => $defaults['show_empty'],
                'sort_order' => (int)($existingSettings['sort_order'] ?? $priority),
                'briefing_priority' => $briefingId > 0 ? 1 : (int)!empty($existingSettings['briefing_priority']),
                'managed_question_analytics' => 1,
                'managed_question_key' => $questionKey,
                'managed_question_keys' => $managedKeys,
                'managed_question_labels' => $sourceLabels,
                'managed_question_types' => $managedTypes,
                'managed_evidence_question_keys' => $managedEvidenceKeys,
                'source_type' => $sourceType,
                'briefing_id' => $briefingId,
                'briefing_ids' => $briefingIds,
                'evergreen_briefing' => $isBriefing ? 1 : 0,
                'canonical_metric_key' => $isBriefing ? $metricKey : sanitize_key((string)($existingSettings['canonical_metric_key'] ?? '')),
            ]);

            $representativeKey = sanitize_key((string)($existingRow['question_key'] ?? $questionKey)) ?: $questionKey;
            $data = [
                'form_id' => (int)$formId,
                'question_key' => $representativeKey,
                'metric_key' => $metricKey,
                'metric_label' => $forcedMetricLabel !== '' ? $forcedMetricLabel : ($preserveExistingVisual ? self::cleanMetricLabel((string)($existingRow['metric_label'] ?? $metricLabel)) : $metricLabel),
                'chart_type' => $preserveExistingVisual ? sanitize_key((string)($existingRow['chart_type'] ?? $defaults['chart_type'])) : $defaults['chart_type'],
                'aggregation' => $preserveExistingVisual ? sanitize_key((string)($existingRow['aggregation'] ?? $defaults['aggregation'])) : $defaults['aggregation'],
                'dimension' => $preserveExistingVisual ? sanitize_key((string)($existingRow['dimension'] ?? $defaults['dimension'])) : $defaults['dimension'],
                'scope_mode' => $preserveExistingVisual ? sanitize_key((string)($existingRow['scope_mode'] ?? $scopeMode)) : $scopeMode,
                'settings_json' => wp_json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_active' => 1,
            ];
            if ($data['metric_label'] === '') $data['metric_label'] = $metricLabel ?: $questionKey;
            if (!in_array($data['chart_type'], ['line','bar','pie','image_gallery','table','kpi'], true)) $data['chart_type'] = $defaults['chart_type'];
            if (!in_array($data['aggregation'], ['latest','sum','avg','count','evidence'], true)) $data['aggregation'] = $defaults['aggregation'];
            if (!in_array($data['dimension'], ['submitted_at','location_id','route_id','project_id','client_id'], true)) $data['dimension'] = $defaults['dimension'];
            if (!in_array($data['scope_mode'], ['global','client_only','client_project','client_project_location','route'], true)) $data['scope_mode'] = $scopeMode;

            if ($existingId) $result = $wpdb->update($bindingsTable, $data, ['id' => $existingId]);
            else $result = $wpdb->insert($bindingsTable, $data);
            if ($result !== false) {
                if (!$existingId) $existingId = (int)$wpdb->insert_id;
                $synced++;
                $duplicates += self::deactivateDuplicateManagedBindings((int)$formId, $existingId, $metricKey, $managedKeys, $clientId, $projectId, $type);
            }
        }
        return [
            'synced' => $synced,
            'deactivated' => (int)($deactivation['deactivated'] ?? 0) + $duplicates,
            'message' => $synced . ' widget(s) permanente(s) sincronizado(s)' . ($duplicates ? ', ' . $duplicates . ' duplicado(s) legado(s) desativado(s).' : '.'),
            'metric_key' => $metricKey,
            'dashboard_id' => $dashboardId,
        ];
    }

    public static function deactivateForQuestion(string $questionKey): array {
        return self::deactivateManaged(sanitize_key($questionKey), []);
    }

    /**
     * One-time safe migration and an admin repair action. It never deletes
     * questions, responses, submissions or evidence. It only backfills the
     * permanent identity and consolidates managed analytics bindings.
     */
    public static function reconcileEvergreenBriefingAnalytics(bool $force = false): array {
        return self::reconcileSmartBriefingAnalytics($force);
    }

    public static function reconcileSmartBriefingAnalytics(bool $force = false, int $filterClientId = 0, int $filterProjectId = 0): array {
        global $wpdb;
        $contextTable = ContextQuestions::table();
        if (!self::tableExists($contextTable)) return ['processed'=>0,'synced'=>0,'deactivated'=>0,'rollup'=>0,'permanent'=>0,'dashboards_assigned'=>0,'errors'=>['Tabela de perguntas contextuais indisponível.']];
        if (!self::columnExists($contextTable, 'analytics_metric_key')) return ['processed'=>0,'synced'=>0,'deactivated'=>0,'rollup'=>0,'permanent'=>0,'dashboards_assigned'=>0,'errors'=>['A coluna analytics_metric_key ainda não existe.']];

        $where = ["analytics_enabled=1", "(source_type='briefing' OR briefing_id>0)"];
        $args = [];
        if ($filterClientId) { $where[]='client_id=%d'; $args[]=$filterClientId; }
        if ($filterProjectId) { $where[]='project_id=%d'; $args[]=$filterProjectId; }
        $sql='SELECT * FROM '.$contextTable.' WHERE '.implode(' AND ',$where).' ORDER BY briefing_id ASC,question_key ASC,id ASC';
        $rows=$args?($wpdb->get_results($wpdb->prepare($sql,...$args),ARRAY_A)?:[]):($wpdb->get_results($sql,ARRAY_A)?:[]);
        $groups=[];
        foreach($rows as $row){$key=absint($row['briefing_id']??0).':'.sanitize_key((string)($row['question_key']??''));$groups[$key][]=$row;}
        $processed=0;$synced=0;$deactivated=0;$rollup=0;$permanent=0;$dashboardsAssigned=0;$ownershipRepaired=0;$errors=[];$policies=[];
        foreach($groups as $groupRows){
            $first=(array)($groupRows[0]??[]); $questionKey=sanitize_key((string)($first['question_key']??'')); if($questionKey==='')continue;
            $clientId=absint($first['client_id']??0);$projectId=absint($first['project_id']??0);$type=sanitize_key((string)($first['question_type']??'text'))?:'text';$label=(string)($first['question_label']??$questionKey);$briefingId=absint($first['briefing_id']??0);
            $rules=self::decodeSettings($first['visibility_rules_json']??'');$persisted=sanitize_key((string)($rules['analytics_behavior']??''));$behaviorSource=sanitize_key((string)($rules['analytics_behavior_source']??''));
            $strategicKey=self::strategicFamilyKey($label,$type);$userPinned=self::analyticsChoiceIsUserConfirmed($rules);
            if($behaviorSource==='user'&&!$userPinned)$ownershipRepaired++;
            if($userPinned&&in_array($persisted,['rollup','existing','new'],true))$behavior=$persisted;
            elseif($strategicKey!=='')$behavior='existing';
            else $behavior='rollup';
            $dashboardId=absint($first['analytics_dashboard_id']??0);$preferred=self::preferredBriefingDashboardId($clientId,$projectId,true);if($preferred&&$dashboardId!==$preferred){$dashboardId=$preferred;$dashboardsAssigned++;}
            $metricKey=sanitize_key((string)($first['analytics_metric_key']??''));
            if(!$userPinned&&$strategicKey!==''&&$behavior==='existing')$metricKey=$strategicKey;
            if($behavior==='rollup'){
                $metricKey='';self::persistQuestionAnalyticsBehavior($questionKey,$briefingId,'rollup','',$dashboardId,'system_22337');
                foreach($groupRows as &$gr){$r=self::decodeSettings($gr['visibility_rules_json']??'');$r['analytics_behavior']='rollup';$gr['visibility_rules_json']=wp_json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$gr['analytics_metric_key']='';if($dashboardId)$gr['analytics_dashboard_id']=$dashboardId;}unset($gr);
                $d=self::deactivateManaged($questionKey,[]);$deactivated+=(int)($d['deactivated']??0);$smart=self::ensureSmartBriefingBindings($clientId,$projectId,$dashboardId,self::targetFormIds($first));$synced+=(int)($smart['synced']??0);$rollup++;
            }else{
                if(!$userPinned&&$strategicKey!=='')$metricKey=$strategicKey;
                elseif($metricKey===''||$persisted===''){
                    if($behavior==='new')$metricKey=self::newPermanentMetricKey($label,$type,$clientId,$projectId,$dashboardId,$questionKey);
                    else $metricKey=self::suggestCanonicalMetricKey($label,$type,$clientId,$projectId,$dashboardId);
                }
                self::persistQuestionAnalyticsBehavior($questionKey,$briefingId,$behavior,$metricKey,$dashboardId,'system_22337');
                foreach($groupRows as &$gr){$r=self::decodeSettings($gr['visibility_rules_json']??'');$r['analytics_behavior']=$behavior;$gr['visibility_rules_json']=wp_json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$gr['analytics_metric_key']=$metricKey;if($dashboardId)$gr['analytics_dashboard_id']=$dashboardId;}unset($gr);
                $result=self::syncRows($groupRows);$synced+=(int)($result['synced']??0);$deactivated+=(int)($result['deactivated']??0);if(!empty($result['message'])&&empty($result['synced'])&&!empty($first['is_active']))$errors[]=$questionKey.': '.(string)$result['message'];$permanent++;
            }
            $processed++;$policies[$questionKey]=['behavior'=>$behavior,'metric_key'=>$metricKey,'client_id'=>$clientId,'project_id'=>$projectId,'user_pinned'=>$userPinned?1:0];
        }
        $legacy=self::deactivateRedundantBriefingBindings($policies,$filterClientId,$filterProjectId);$deactivated+=$legacy;
        $coveredLegacy=self::deactivateBindingsCoveredByManaged($filterClientId,$filterProjectId);$deactivated+=$coveredLegacy;
        $semanticLegacy=self::deactivateSemanticFamilyDuplicates($filterClientId,$filterProjectId);$deactivated+=$semanticLegacy;
        $operational=self::compactOperationalDashboardBindings($filterClientId,$filterProjectId);$deactivated+=(int)($operational['deactivated']??0);$synced+=(int)($operational['synced']??0);
        return ['processed'=>$processed,'synced'=>$synced,'deactivated'=>$deactivated,'legacy_deactivated'=>$legacy+$coveredLegacy+$semanticLegacy,'covered_legacy_deactivated'=>$coveredLegacy,'semantic_legacy_deactivated'=>$semanticLegacy,'rollup'=>$rollup,'permanent'=>$permanent,'operational_families'=>(int)($operational['families']??0),'operational_evidence_rollups'=>(int)($operational['evidence_rollups']??0),'dashboards_assigned'=>$dashboardsAssigned,'ownership_repaired'=>$ownershipRepaired,'errors'=>array_values(array_unique($errors))];
    }

    private static function persistQuestionAnalyticsBehavior(string $questionKey, int $briefingId, string $behavior, string $metricKey, int $dashboardId, string $source = 'system'): void {
        global $wpdb;
        $table=ContextQuestions::table(); if(!self::tableExists($table))return;
        $where=['question_key=%s'];$args=[$questionKey];if($briefingId>0){$where[]='briefing_id=%d';$args[]=$briefingId;}
        $sql='SELECT id,visibility_rules_json FROM '.$table.' WHERE '.implode(' AND ',$where);$rows=$wpdb->get_results($wpdb->prepare($sql,...$args),ARRAY_A)?:[];
        foreach($rows as $row){
            $rules=self::decodeSettings($row['visibility_rules_json']??'');
            $confirmedUser=self::analyticsChoiceIsUserConfirmed($rules);
            $rules['analytics_behavior']=in_array($behavior,['rollup','existing','new'],true)?$behavior:'rollup';
            if($source==='user'){
                $rules['analytics_behavior_source']='user';
                $rules['analytics_behavior_user_confirmed']=1;
            }elseif(!$confirmedUser){
                $rules['analytics_behavior_source']=sanitize_key($source)?:'system';
                $rules['analytics_behavior_user_confirmed']=0;
            }
            $wpdb->update($table,['visibility_rules_json'=>wp_json_encode($rules,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)],['id'=>(int)$row['id']],['%s'],['%d']);
        }
        self::persistQuestionAnalyticsIdentity($questionKey,$briefingId,$metricKey,$dashboardId);
    }

    private static function deactivateRedundantBriefingBindings(array $policies, int $filterClientId=0, int $filterProjectId=0): int {
        global $wpdb; if(!$policies||!self::tableExists(self::bindingsTable()))return 0;$table=self::bindingsTable();$rows=$wpdb->get_results("SELECT * FROM {$table} WHERE is_active=1 ORDER BY id ASC",ARRAY_A)?:[];
        $covered=[];
        foreach($rows as $row){$settings=self::decodeSettings($row['settings_json']??'');if(self::isSmartRollupBinding($row,$settings))continue;if(empty($settings['evergreen_briefing'])||empty($settings['managed_question_analytics']))continue;$keys=self::sanitizeKeyList(array_merge([(string)($row['question_key']??''),(string)($settings['managed_question_key']??'')],(array)($settings['managed_question_keys']??[])));$id=(int)($row['id']??0);$score=count($keys)*1000000+$id;foreach($keys as $key){if(!isset($covered[$key])||$score>(int)$covered[$key]['score'])$covered[$key]=['id'=>$id,'score'=>$score];}}
        $count=0;
        foreach($rows as $row){$id=(int)($row['id']??0);$settings=self::decodeSettings($row['settings_json']??'');if(self::isSmartRollupBinding($row,$settings))continue;$rc=absint($settings['client_id']??0);$rp=absint($settings['project_id']??0);if($filterClientId&&$rc&&$rc!==$filterClientId)continue;if($filterProjectId&&$rp&&$rp!==$filterProjectId)continue;$keys=self::sanitizeKeyList(array_merge([(string)($row['question_key']??''),(string)($settings['managed_question_key']??'')],(array)($settings['managed_question_keys']??[])));$relevant=[];foreach($keys as $key)if(isset($policies[$key]))$relevant[$key]=$policies[$key];if(!$relevant)continue;$off=false;foreach($relevant as $key=>$policy){if(($policy['behavior']??'')==='rollup'){$off=true;break;}$keeper=(int)($covered[$key]['id']??0);if($keeper&&$keeper!==$id){$off=true;break;}}if($off&&$wpdb->update($table,['is_active'=>0],['id'=>$id],['%d'],['%d'])!==false)$count++;}
        return $count;
    }


    /**
     * 2.2.335 cleanup for old bindings that pre-date managed analytics flags.
     * If a current evergreen binding already owns a question key, any other
     * active binding for that same key in the same operational scope becomes
     * legacy. This is intentionally a soft delete through is_active=0.
     */
    private static function deactivateBindingsCoveredByManaged(int $filterClientId = 0, int $filterProjectId = 0): int {
        global $wpdb;
        $table = self::bindingsTable(); if(!self::tableExists($table)) return 0;
        $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE is_active=1 ORDER BY id ASC", ARRAY_A) ?: [];
        $scope = static function(array $row,array $settings): string {
            return implode(':',[
                absint($row['form_id']??0), absint($settings['dashboard_id']??0),
                absint($settings['client_id']??0), absint($settings['project_id']??0),
                absint($settings['route_id']??0), absint($settings['store_group_id']??0),
                sanitize_key((string)($row['scope_mode']??'client_project_location')),
            ]);
        };
        $owners=[];$globalOwners=[];
        foreach($rows as $row){
            $settings=self::decodeSettings($row['settings_json']??'');
            if(self::isSmartRollupBinding($row,$settings)) continue;
            if(empty($settings['managed_question_analytics'])||empty($settings['evergreen_briefing'])) continue;
            $rc=absint($settings['client_id']??0);$rp=absint($settings['project_id']??0);
            if($filterClientId&&$rc&&$rc!==$filterClientId)continue;if($filterProjectId&&$rp&&$rp!==$filterProjectId)continue;
            $keys=self::sanitizeKeyList(array_merge([(string)($row['question_key']??''),(string)($settings['managed_question_key']??'')],(array)($settings['managed_question_keys']??[])));
            $sig=$scope($row,$settings);$id=(int)($row['id']??0);$score=count($keys)*1000000+$id;$formId=absint($row['form_id']??0);
            foreach($keys as $key){
                $ownerKey=$sig.'|'.$key;if(!isset($owners[$ownerKey])||$score>(int)$owners[$ownerKey]['score'])$owners[$ownerKey]=['id'=>$id,'score'=>$score];
                if(strpos($key,'briefing_')===0){$globalKey=$formId.'|'.$key;if(!isset($globalOwners[$globalKey])||$score>(int)$globalOwners[$globalKey]['score'])$globalOwners[$globalKey]=['id'=>$id,'score'=>$score];}
            }
        }
        if(!$owners&&!$globalOwners)return 0;
        $count=0;
        foreach($rows as $row){
            $id=(int)($row['id']??0);if(!$id)continue;$settings=self::decodeSettings($row['settings_json']??'');if(self::isSmartRollupBinding($row,$settings))continue;
            $rc=absint($settings['client_id']??0);$rp=absint($settings['project_id']??0);if($filterClientId&&$rc&&$rc!==$filterClientId)continue;if($filterProjectId&&$rp&&$rp!==$filterProjectId)continue;
            $sig=$scope($row,$settings);$keys=self::sanitizeKeyList(array_merge([(string)($row['question_key']??'')],(array)($settings['managed_question_keys']??[])));
            $covered=false;$keeper=false;$formId=absint($row['form_id']??0);
            foreach($keys as $key){
                $owner=$owners[$sig.'|'.$key]['id']??0;
                if(!$owner&&strpos($key,'briefing_')===0)$owner=$globalOwners[$formId.'|'.$key]['id']??0;
                if($owner){$covered=true;if((int)$owner===$id)$keeper=true;}
            }
            if(!$covered||$keeper)continue;
            if($wpdb->update($table,['is_active'=>0],['id'=>$id],['%d'],['%d'])!==false)$count++;
        }
        return $count;
    }

    /**
     * 2.2.336 semantic cleanup. When a strategic family already has a managed
     * evergreen owner in the same exact scope, older active briefing widgets
     * that resolve to that same family become legacy. The comparison is based
     * on the business meaning of the label, never on a technical field type.
     */
    private static function deactivateSemanticFamilyDuplicates(int $filterClientId = 0, int $filterProjectId = 0): int {
        global $wpdb;
        $table = self::bindingsTable();
        $dashTable = self::dashboardsTable();
        if (!self::tableExists($table) || !self::tableExists($dashTable)) return 0;

        $dashRows = $wpdb->get_results("SELECT id,title FROM {$dashTable} WHERE is_active=1 ORDER BY id ASC", ARRAY_A) ?: [];
        $briefingDashboards = [];
        foreach ($dashRows as $dash) {
            if (strpos(self::normalizedLabel((string)($dash['title'] ?? '')), 'briefing') !== false) $briefingDashboards[(int)$dash['id']] = true;
        }
        if (!$briefingDashboards) return 0;

        $knownFamilies = array_fill_keys(array_keys([
            'briefing_espacos_centralizados'=>1,
            // 2.2.338: the Comparativo family was introduced in 2.2.337, but
            // the semantic duplicate cleaner also needs to know it. Without
            // this entry the new evergreen owner could coexist with the old
            // Comparativo binding, making the active widget count rise by one.
            'briefing_comparativo_referencias'=>1,
            'briefing_materiais_vap'=>1,
            'briefing_promocoes_visibilidade'=>1,
            'briefing_precos_linear'=>1,
            'briefing_stock'=>1,
            'briefing_produto_disponibilidade'=>1,
            'briefing_share_space'=>1,
            'briefing_numero_frentes'=>1,
            'briefing_sugestao_encomenda'=>1,
            'briefing_problemas_oportunidades'=>1,
        ]), true);
        $scope = static function(array $row, array $settings): string {
            return implode(':', [
                absint($row['form_id'] ?? 0), absint($settings['dashboard_id'] ?? 0),
                absint($settings['client_id'] ?? 0), absint($settings['project_id'] ?? 0),
                absint($settings['route_id'] ?? 0), absint($settings['store_group_id'] ?? 0),
                sanitize_key((string)($row['scope_mode'] ?? 'client_project_location')),
            ]);
        };

        $contextMeta = [];
        $contextTable = ContextQuestions::table();
        if (self::tableExists($contextTable)) {
            $contextRows = $wpdb->get_results("SELECT question_key,question_label,question_type FROM {$contextTable} WHERE source_type='briefing' OR briefing_id>0 ORDER BY id ASC", ARRAY_A) ?: [];
            foreach ($contextRows as $contextRow) {
                $contextKey = sanitize_key((string)($contextRow['question_key'] ?? ''));
                if ($contextKey === '') continue;
                $contextMeta[$contextKey] = [
                    'label'=>(string)($contextRow['question_label'] ?? ''),
                    'type'=>sanitize_key((string)($contextRow['question_type'] ?? 'text')) ?: 'text',
                ];
            }
        }

        $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE is_active=1 ORDER BY id ASC", ARRAY_A) ?: [];
        $groups = [];
        foreach ($rows as $row) {
            $settings = self::decodeSettings($row['settings_json'] ?? '');
            if (self::isSmartRollupBinding($row, $settings)) continue;
            $dashboardId = absint($settings['dashboard_id'] ?? 0);
            if (!$dashboardId || empty($briefingDashboards[$dashboardId])) continue;
            $rc = absint($settings['client_id'] ?? 0); $rp = absint($settings['project_id'] ?? 0);
            if ($filterClientId && $rc && $rc !== $filterClientId) continue;
            if ($filterProjectId && $rp && $rp !== $filterProjectId) continue;
            $qKey = sanitize_key((string)($row['question_key'] ?? ''));
            $isBriefingSource = !empty($settings['evergreen_briefing'])
                || sanitize_key((string)($settings['source_type'] ?? '')) === 'briefing'
                || strpos($qKey, 'briefing_') === 0;
            if (!$isBriefingSource) continue;

            $canonical = sanitize_key((string)($settings['canonical_metric_key'] ?? ''));
            $metricKey = sanitize_key((string)($row['metric_key'] ?? ''));
            $keys = self::sanitizeKeyList(array_merge([$qKey, (string)($settings['managed_question_key'] ?? '')], (array)($settings['managed_question_keys'] ?? [])));
            $semanticFamilies = [];
            $hasContextSource = false;
            foreach ($keys as $sourceKey) {
                if (!isset($contextMeta[$sourceKey])) continue;
                $hasContextSource = true;
                $sourceFamily = self::strategicFamilyKey((string)($contextMeta[$sourceKey]['label'] ?? ''), (string)($contextMeta[$sourceKey]['type'] ?? ''));
                if ($sourceFamily !== '') $semanticFamilies[$sourceFamily] = true;
            }
            $family = '';
            if (count($semanticFamilies) === 1) {
                $family = (string)array_key_first($semanticFamilies);
            } elseif (count($semanticFamilies) > 1) {
                if (isset($semanticFamilies[$canonical])) $family = $canonical;
                elseif (isset($semanticFamilies[$metricKey])) $family = $metricKey;
                else continue;
            } elseif (!$hasContextSource) {
                // Very old bindings may no longer have a contextual-question row.
                // Only in that case may the human-facing metric label be used as
                // a fallback. A stale canonical key is never proof by itself.
                $family = self::strategicFamilyKey((string)($row['metric_label'] ?? ''), (string)($settings['question_type'] ?? ''));
            }
            if ($family === '' || !isset($knownFamilies[$family])) continue;

            $managed = !empty($settings['managed_question_analytics']) && !empty($settings['evergreen_briefing']);
            $score = ($managed ? 100000000 : 0)
                + (($metricKey === $family || $canonical === $family) ? 10000000 : 0)
                + count($keys) * 100000
                + (int)($row['id'] ?? 0);
            $groups[$scope($row, $settings) . '|' . $family][] = ['row'=>$row, 'settings'=>$settings, 'managed'=>$managed, 'score'=>$score];
        }

        $count = 0;
        foreach ($groups as $items) {
            if (count($items) < 2) continue;
            usort($items, static fn(array $a, array $b): int => (int)$b['score'] <=> (int)$a['score']);
            $keeper = $items[0];
            if (empty($keeper['managed'])) continue;
            $keepId = (int)($keeper['row']['id'] ?? 0);
            foreach (array_slice($items, 1) as $item) {
                $id = (int)($item['row']['id'] ?? 0);
                if (!$id || $id === $keepId || empty($item['row']['is_active'])) continue;
                if ($wpdb->update($table, ['is_active'=>0], ['id'=>$id], ['%d'], ['%d']) !== false) $count++;
            }
        }
        return $count;
    }

    private static function persistQuestionAnalyticsIdentity(string $questionKey, int $briefingId, string $metricKey, int $dashboardId): void {
        global $wpdb;
        $table = ContextQuestions::table();
        if (!self::tableExists($table) || !self::columnExists($table, 'analytics_metric_key')) return;
        $where = ['question_key=%s'];
        $args = [$questionKey];
        if ($briefingId > 0) { $where[] = 'briefing_id=%d'; $args[] = $briefingId; }
        $set = ['analytics_metric_key' => $metricKey];
        if ($dashboardId > 0) $set['analytics_dashboard_id'] = $dashboardId;
        $setSql = [];
        $setArgs = [];
        foreach ($set as $column => $value) {
            $setSql[] = $column . (is_int($value) ? '=%d' : '=%s');
            $setArgs[] = $value;
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(',', $setSql) . ' WHERE ' . implode(' AND ', $where);
        $wpdb->query($wpdb->prepare($sql, ...array_merge($setArgs, $args)));
    }

    private static function historicalMetricSources(string $metricKey, int $clientId, int $projectId, int $formId): array {
        global $wpdb;
        $table = ContextQuestions::table();
        if (!self::tableExists($table) || !self::columnExists($table, 'analytics_metric_key')) return ['question_keys'=>[],'labels'=>[],'briefing_ids'=>[],'types'=>[],'evidence_keys'=>[]];
        $where = ["analytics_enabled=1", "(source_type='briefing' OR briefing_id>0)", 'analytics_metric_key=%s'];
        $args = [$metricKey];
        if ($clientId) { $where[] = 'client_id=%d'; $args[] = $clientId; }
        if ($projectId) { $where[] = 'project_id=%d'; $args[] = $projectId; }
        if ($formId) { $where[] = '(form_id=0 OR form_id=%d)'; $args[] = $formId; }
        $sql = 'SELECT DISTINCT question_key,question_label,question_type,briefing_id FROM ' . $table . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY briefing_id ASC,id ASC';
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
        $keys = []; $labels = []; $ids = []; $types = []; $evidenceKeys = [];
        foreach ($rows as $row) {
            $key = sanitize_key((string)($row['question_key'] ?? ''));
            $type = sanitize_key((string)($row['question_type'] ?? 'text')) ?: 'text';
            if ($key !== '') {
                $keys[$key] = $key;
                $types[$key] = $type;
                if ($type === 'evidence_check') $evidenceKeys[EvidenceFlow::photo_key_for_parent($key)] = EvidenceFlow::photo_key_for_parent($key);
                elseif (in_array($type, ['image_upload','categorized_image_upload','file_upload'], true)) $evidenceKeys[$key] = $key;
            }
            $label = self::cleanMetricLabel((string)($row['question_label'] ?? ''));
            if ($label !== '') $labels[$label] = $label;
            $id = absint($row['briefing_id'] ?? 0);
            if ($id) $ids[$id] = $id;
        }
        return ['question_keys'=>array_values($keys),'labels'=>array_values($labels),'briefing_ids'=>array_values($ids),'types'=>$types,'evidence_keys'=>array_values($evidenceKeys)];
    }

    private static function managedBindingsForMetric(int $formId, string $metricKey, int $clientId, int $projectId, array $aliases, string $questionType): array {
        global $wpdb;
        $table = self::bindingsTable();
        if (!self::tableExists($table)) return [];
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE form_id=%d ORDER BY is_active DESC,id ASC", $formId), ARRAY_A) ?: [];
        $aliasMap = array_fill_keys(self::sanitizeKeyList($aliases), true);
        $matches = [];
        foreach ($rows as $row) {
            $settings = self::decodeSettings($row['settings_json'] ?? '');
            if (empty($settings['managed_question_analytics'])) continue;
            $rowClient = absint($settings['client_id'] ?? 0);
            $rowProject = absint($settings['project_id'] ?? 0);
            if ($clientId && $rowClient && $rowClient !== $clientId) continue;
            if ($projectId && $rowProject && $rowProject !== $projectId) continue;
            $canonical = sanitize_key((string)($settings['canonical_metric_key'] ?? ''));
            if ($canonical === '' && !empty($settings['evergreen_briefing'])) $canonical = sanitize_key((string)($row['metric_key'] ?? ''));
            if ($canonical === '') $canonical = self::canonicalMetricKey((string)($row['metric_label'] ?? $row['question_key'] ?? ''), (string)($settings['question_type'] ?? $questionType));
            $rowAliases = self::sanitizeKeyList(array_merge([(string)($row['question_key'] ?? ''), (string)($settings['managed_question_key'] ?? '')], (array)($settings['managed_question_keys'] ?? [])));
            $overlap = false;
            foreach ($rowAliases as $alias) if (isset($aliasMap[$alias])) { $overlap = true; break; }
            if ($canonical === $metricKey || $overlap) $matches[] = $row;
        }
        return $matches;
    }

    private static function chooseManagedBinding(array $rows, int $dashboardId, string $metricKey): array {
        $best = [];
        $bestScore = -1;
        foreach ($rows as $row) {
            $settings = self::decodeSettings($row['settings_json'] ?? '');
            $score = !empty($row['is_active']) ? 20 : 0;
            if (sanitize_key((string)($row['metric_key'] ?? '')) === $metricKey) $score += 60;
            if ($dashboardId && absint($settings['dashboard_id'] ?? 0) === $dashboardId) $score += 100;
            if (!empty($settings['evergreen_briefing'])) $score += 10;
            if ($score > $bestScore || ($score === $bestScore && (int)($row['id'] ?? PHP_INT_MAX) < (int)($best['id'] ?? PHP_INT_MAX))) {
                $bestScore = $score;
                $best = $row;
            }
        }
        return $best;
    }

    private static function deactivateDuplicateManagedBindings(int $formId, int $keepId, string $metricKey, array $aliases, int $clientId, int $projectId, string $questionType): int {
        global $wpdb;
        $rows = self::managedBindingsForMetric($formId, $metricKey, $clientId, $projectId, $aliases, $questionType);
        $count = 0;
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if (!$id || $id === $keepId || empty($row['is_active'])) continue;
            $result = $wpdb->update(self::bindingsTable(), ['is_active'=>0], ['id'=>$id], ['%d'], ['%d']);
            if ($result !== false) $count++;
        }
        return $count;
    }

    private static function managedBindingId(int $formId, string $questionKey): int {
        global $wpdb;
        $table = self::bindingsTable();
        if (!self::tableExists($table)) return 0;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id,question_key,settings_json FROM {$table} WHERE form_id=%d", $formId), ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $settings = self::decodeSettings($row['settings_json'] ?? '');
            if (empty($settings['managed_question_analytics'])) continue;
            $keys = self::sanitizeKeyList(array_merge([(string)($row['question_key'] ?? ''), (string)($settings['managed_question_key'] ?? '')], (array)($settings['managed_question_keys'] ?? [])));
            if (in_array($questionKey, $keys, true)) return (int)$row['id'];
        }
        return 0;
    }

    private static function deactivateManaged(string $questionKey, array $keepFormIds): array {
        global $wpdb;
        $questionKey = sanitize_key($questionKey);
        if ($questionKey === '') return ['deactivated' => 0];
        $keep = array_fill_keys(array_map('intval', $keepFormIds), true);
        $table = self::bindingsTable();
        if (!self::tableExists($table)) return ['deactivated' => 0];
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A) ?: [];
        $deactivated = 0;
        foreach ($rows as $row) {
            $settings = self::decodeSettings($row['settings_json'] ?? '');
            if (empty($settings['managed_question_analytics'])) continue;
            $keys = self::sanitizeKeyList(array_merge([(string)($row['question_key'] ?? ''), (string)($settings['managed_question_key'] ?? '')], (array)($settings['managed_question_keys'] ?? [])));
            if (!in_array($questionKey, $keys, true)) continue;
            if (!empty($keep[(int)$row['form_id']])) continue;
            $keys = array_values(array_filter($keys, static fn(string $key): bool => $key !== $questionKey));
            if ($keys && !empty($settings['evergreen_briefing'])) {
                $settings['managed_question_keys'] = $keys;
                if (sanitize_key((string)($settings['managed_question_key'] ?? '')) === $questionKey) $settings['managed_question_key'] = $keys[0];
                if (isset($settings['managed_question_types']) && is_array($settings['managed_question_types'])) unset($settings['managed_question_types'][$questionKey]);
                if (isset($settings['managed_question_labels_by_key']) && is_array($settings['managed_question_labels_by_key'])) unset($settings['managed_question_labels_by_key'][$questionKey]);
                if (isset($settings['managed_evidence_question_keys']) && is_array($settings['managed_evidence_question_keys'])) {
                    $photoKey = EvidenceFlow::photo_key_for_parent($questionKey);
                    $settings['managed_evidence_question_keys'] = self::sanitizeKeyList(array_values(array_filter(
                        $settings['managed_evidence_question_keys'],
                        static fn($key): bool => !in_array(sanitize_key((string)$key), [$questionKey, $photoKey], true)
                    )));
                }
                $update = ['settings_json'=>wp_json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
                if (sanitize_key((string)($row['question_key'] ?? '')) === $questionKey) $update['question_key'] = $keys[0];
                $formats = array_fill(0, count($update), '%s');
                $result = $wpdb->update($table, $update, ['id'=>(int)$row['id']], $formats, ['%d']);
            } else {
                $result = $wpdb->update($table, ['is_active'=>0], ['id'=>(int)$row['id']], ['%d'], ['%d']);
            }
            if ($result !== false) $deactivated++;
        }
        return ['deactivated' => $deactivated];
    }

    private static function bindingSettingsById(int $id): array {
        $row = self::bindingRowById($id);
        return self::decodeSettings($row['settings_json'] ?? '');
    }

    private static function bindingRowById(int $id): array {
        global $wpdb;
        if (!$id || !self::tableExists(self::bindingsTable())) return [];
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::bindingsTable() . ' WHERE id=%d LIMIT 1', $id), ARRAY_A);
        return is_array($row) ? $row : [];
    }

    private static function targetFormIds(array $question): array {
        global $wpdb;
        $formId = absint($question['form_id'] ?? 0);
        if ($formId > 0) return [$formId];
        $isBriefing = sanitize_key((string)($question['source_type'] ?? '')) === 'briefing' || !empty($question['briefing_id']);
        if (!$isBriefing) return [];
        $formsTable = Forms::table();
        if (!self::tableExists($formsTable)) return [];
        $forms = $wpdb->get_results("SELECT id,schema_json FROM {$formsTable} WHERE status='active' ORDER BY id ASC", ARRAY_A) ?: [];
        $ids = [];
        foreach ($forms as $form) {
            $schema = Forms::decode_schema((string)($form['schema_json'] ?? ''));
            if (self::schemaHasBriefingBlock($schema)) $ids[] = (int)$form['id'];
        }
        return array_values(array_unique(array_filter($ids)));
    }

    private static function schemaHasBriefingBlock(array $schema): bool {
        $found = false;
        $walk = static function($node) use (&$walk, &$found): void {
            if ($found || !is_array($node)) return;
            if (sanitize_key((string)($node['type'] ?? '')) === 'briefing_block') { $found = true; return; }
            foreach ($node as $key => $child) {
                if (in_array((string)$key, ['options','products','meta','theme'], true)) continue;
                if (is_array($child)) $walk($child);
            }
        };
        $walk($schema);
        return $found;
    }

    private static function normalizedLabel(string $label): string {
        if (function_exists('remove_accents')) $label = remove_accents($label);
        $label = strtolower((string)$label);
        $label = preg_replace('/[^a-z0-9]+/u', ' ', $label) ?: $label;
        return trim(preg_replace('/\s+/', ' ', $label) ?: $label);
    }

    private static function cleanMetricLabel(string $label): string {
        $label = sanitize_text_field($label);
        $label = preg_replace('/\s*[\.·:\-]\s*Hist[oó]rico\s*$/iu', '', $label) ?: $label;
        return trim($label);
    }

    private static function sanitizeKeyList(array $keys): array {
        $out = [];
        foreach ($keys as $key) {
            $key = sanitize_key((string)$key);
            if ($key !== '') $out[$key] = $key;
        }
        return array_values($out);
    }

    private static function decodeSettings($json): array {
        if (is_array($json)) return $json;
        $settings = json_decode((string)$json, true);
        return is_array($settings) ? $settings : [];
    }

    private static function tableExists(string $table): bool {
        global $wpdb;
        if ($table === '') return false;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private static function columnExists(string $table, string $column): bool {
        global $wpdb;
        if (!self::tableExists($table)) return false;
        return (bool)$wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE %s', $column));
    }
}
