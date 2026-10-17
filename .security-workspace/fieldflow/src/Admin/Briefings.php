<?php
namespace RoutesPro\Admin;

use RoutesPro\Briefings\BriefingService;
use RoutesPro\Briefings\BriefingCategoryMappingService as CategoryMapping;
use RoutesPro\Briefings\BriefingAIService;
use RoutesPro\Briefings\BriefingSteps;
use RoutesPro\Briefings\PernodWeeklyImporter;
use RoutesPro\Support\AdminPage;
use RoutesPro\Forms\ContextQuestions as ContextQuestionService;
use RoutesPro\Forms\Forms as FormsModule;
use RoutesPro\Analytics\QuestionAnalyticsService;

if (!defined('ABSPATH')) exit;

class Briefings {
    public static function register_hooks(): void {
        add_action('admin_post_fieldflow_briefing_import', [self::class, 'handleImport']);
        add_action('admin_post_fieldflow_briefing_map', [self::class, 'handleMapping']);
        add_action('admin_post_fieldflow_briefing_publish', [self::class, 'handlePublish']);
        add_action('admin_post_fieldflow_briefing_archive', [self::class, 'handleArchive']);
        add_action('admin_post_fieldflow_briefing_delete', [self::class, 'handleDelete']);
        add_action('admin_post_fieldflow_briefing_export', [self::class, 'handleExport']);
        add_action('admin_post_fieldflow_briefing_ai_analyze', [self::class, 'handleAIAnalyze']);
        add_action('admin_post_fieldflow_briefing_ai_apply_mappings', [self::class, 'handleAIApplyMappings']);
        add_action('admin_post_fieldflow_briefing_safe_mappings', [self::class, 'handleSafeMappings']);
        add_action('admin_post_fieldflow_briefing_cardex_sync', [self::class, 'handleCardexSync']);
        add_action('admin_post_fieldflow_briefing_share_space_repair', [self::class, 'handleShareSpaceRepair']);
        add_action('admin_post_fieldflow_briefing_question_save', [self::class, 'handleQuestionSave']);
        add_action('admin_post_fieldflow_briefing_step_save', [self::class, 'handleStepSave']);
        add_action('admin_post_fieldflow_briefing_questions_bulk_approve', [self::class, 'handleQuestionsBulkApprove']);
        add_action('admin_post_fieldflow_briefing_question_delete', [self::class, 'handleQuestionDelete']);
        add_action('admin_post_fieldflow_briefing_questions_bulk_delete', [self::class, 'handleQuestionsBulkDelete']);
    }

    public static function render(): void {
        if (!current_user_can('routespro_manage')) return;
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $clients = $wpdb->get_results("SELECT id,name FROM {$px}clients ORDER BY name ASC", ARRAY_A) ?: [];
        $projects = $wpdb->get_results("SELECT id,client_id,name FROM {$px}projects ORDER BY name ASC", ARRAY_A) ?: [];
        $filterClient = absint($_GET['client_id'] ?? 0);
        $filterProject = absint($_GET['project_id'] ?? 0);
        $filterStatus = sanitize_key((string)($_GET['status'] ?? ''));
        $briefingId = absint($_GET['briefing_id'] ?? 0);
        $briefings = BriefingService::listAdmin(['client_id' => $filterClient, 'project_id' => $filterProject, 'status' => $filterStatus]);

        AdminPage::open('Briefings Operacionais', 'Importação semanal, associação permanente de lojas, publicação e prova de comunicação à equipa.');
        self::styles();
        self::notice();
        ?>
        <div class="ffb-grid">
          <section class="ffb-card">
            <div class="ffb-head"><div><span class="ffb-kicker">Novo briefing</span><h2>Importar Excel semanal</h2></div><span class="ffb-pill">Pernod Wxx</span></div>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ffb-form">
              <input type="hidden" name="action" value="fieldflow_briefing_import">
              <?php wp_nonce_field('fieldflow_briefing_import'); ?>
              <label>Cliente<select name="client_id" required><option value="">Selecionar</option><?php foreach($clients as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo esc_html($c['name']); ?></option><?php endforeach; ?></select></label>
              <label>Campanha<select name="project_id" required><option value="">Selecionar</option><?php foreach($projects as $p): ?><option value="<?php echo (int)$p['id']; ?>" data-client="<?php echo (int)$p['client_id']; ?>"><?php echo esc_html($p['name']); ?></option><?php endforeach; ?></select></label>
              <label class="ffb-wide">Ficheiro Excel<input type="file" name="briefing_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label>
              <label>Título opcional<input type="text" name="title" placeholder="Briefing Semanal W29"></label>
              <label>Semana opcional<input type="number" min="1" max="53" name="week_no" placeholder="Detetada no Excel"></label>
              <label>Ano opcional<input type="number" min="2020" max="2100" name="year_no" placeholder="Detetado no Excel"></label>
              <label>Válido desde<input type="date" name="valid_from"></label>
              <label>Válido até<input type="date" name="valid_to"></label>
              <?php if(BriefingAIService::available()): ?><label class="ffb-wide ffb-check"><input type="checkbox" name="ai_analyze" value="1" checked><span><strong>Analisar automaticamente com IA</strong><small>Usa o fornecedor já configurado no FieldFlow e guarda a análise no briefing. Podes desativar em alojamentos com limites de execução apertados.</small></span></label><?php endif; ?>
              <div class="ffb-wide"><button class="button button-primary button-hero">Importar e rever</button><p class="description">As associações de lojas já conhecidas são reutilizadas automaticamente. Só os novos nomes ficam pendentes.</p></div>
            </form>
          </section>

          <section class="ffb-card">
            <div class="ffb-head"><div><span class="ffb-kicker">Arquivo</span><h2>Briefings importados</h2></div><span class="ffb-pill"><?php echo count($briefings); ?> registos</span></div>
            <form method="get" class="ffb-filters">
              <input type="hidden" name="page" value="routespro-briefings">
              <select name="client_id"><option value="">Todos os clientes</option><?php foreach($clients as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php selected($filterClient,(int)$c['id']); ?>><?php echo esc_html($c['name']); ?></option><?php endforeach; ?></select>
              <select name="project_id"><option value="">Todas as campanhas</option><?php foreach($projects as $p): ?><option value="<?php echo (int)$p['id']; ?>" <?php selected($filterProject,(int)$p['id']); ?>><?php echo esc_html($p['name']); ?></option><?php endforeach; ?></select>
              <select name="status"><option value="">Todos os estados</option><?php foreach(BriefingService::statusLabels() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filterStatus,$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
              <button class="button">Filtrar</button>
            </form>
            <div class="ffb-table-wrap"><table class="widefat striped"><thead><tr><th>Semana</th><th>Briefing</th><th>Validade</th><th>Estado</th><th>Mapeamento</th><th>IA</th><th>Criado</th><th>Ações</th></tr></thead><tbody>
            <?php if(!$briefings): ?><tr><td colspan="8">Ainda não existem briefings importados.</td></tr><?php endif; ?>
            <?php foreach($briefings as $row): $summary=json_decode((string)$row['import_summary_json'],true)?:[]; ?>
              <tr>
                <td><strong>W<?php echo (int)$row['week_no']; ?></strong><br><small><?php echo (int)$row['year_no']; ?> · v<?php echo (int)$row['version_no']; ?></small></td>
                <td><strong><?php echo esc_html($row['title']); ?></strong><br><small><?php echo esc_html($row['source_filename']); ?></small></td>
                <td><?php echo esc_html(self::dateLabel($row['valid_from'])); ?><br><small>até <?php echo esc_html(self::dateLabel($row['valid_to'])); ?></small></td>
                <td><span class="ffb-status <?php echo esc_attr($row['status']); ?>"><?php echo esc_html(BriefingService::statusLabels()[$row['status']] ?? $row['status']); ?></span></td>
                <td><strong><?php echo (int)($summary['store_aliases_mapped'] ?? 0); ?>/<?php echo (int)($summary['store_aliases_total'] ?? 0); ?></strong><br><small><?php echo (int)($summary['store_aliases_unmapped'] ?? 0); ?> por associar</small></td>
                <?php $rowAi=is_array($summary['ai_copilot']??null)?$summary['ai_copilot']:[]; $rowAudit=is_array($rowAi['audit']??null)?$rowAi['audit']:[]; ?>
                <td><?php if($rowAi): ?><span class="ffb-ai-score <?php echo esc_attr($rowAudit['status']??'warning'); ?>"><?php echo (int)($rowAudit['score']??0); ?>/100</span><br><small><?php echo !empty($rowAi['stale'])?'Desatualizada':esc_html($rowAi['provider']??'IA FieldFlow'); ?></small><?php else: ?><span class="ffb-status">Por analisar</span><?php endif; ?></td>
                <td><?php echo esc_html(self::dateTimeLabel($row['created_at'] ?? '')); ?></td>
                <td><div class="ffb-row-actions"><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=routespro-briefings&briefing_id='.(int)$row['id'])); ?>">Abrir</a><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Eliminar definitivamente este briefing e a respetiva evidência de leitura?');"><input type="hidden" name="action" value="fieldflow_briefing_delete"><input type="hidden" name="briefing_id" value="<?php echo (int)$row['id']; ?>"><?php wp_nonce_field('fieldflow_briefing_delete_'.$row['id']); ?><button class="button button-small button-link-delete">Eliminar</button></form></div></td>
              </tr>
            <?php endforeach; ?>
            </tbody></table></div>
          </section>
        </div>
        <?php
        if ($briefingId) self::renderDetail($briefingId);
        self::scripts();
        AdminPage::close();
    }

    private static function renderDetail(int $briefingId): void {
        $detail = BriefingService::adminDetail($briefingId);
        if (!$detail) return;
        $b = $detail['briefing'];
        $summary = json_decode((string)$b['import_summary_json'], true) ?: [];
        $ai = is_array($summary['ai_copilot'] ?? null) ? $summary['ai_copilot'] : [];
        $aiReady = BriefingAIService::available();
        $aiJob = BriefingAIService::jobStatus($briefingId);
        $aiJobStatus = sanitize_key((string)($aiJob['status'] ?? ''));
        $aiAudit = is_array($ai['audit'] ?? null) ? $ai['audit'] : [];
        $aiSuggestions = [];
        foreach ((!empty($ai['stale']) ? [] : (array)($ai['store_suggestions'] ?? [])) as $suggestion) {
            $aliasId = absint($suggestion['alias_id'] ?? 0);
            if ($aliasId) $aiSuggestions[$aliasId] = $suggestion;
        }
        $locations = BriefingService::locationsForQuestionScope((int)$b['client_id'], (int)$b['project_id']);
        $questionLocations = $locations;
        $labels = BriefingService::retailerLabels();
        $locationLabels = [];
        $locationCounts = [];
        $locationCountsByKey = [];
        foreach ($locations as $location) {
            $retailerKey = (string)($location['retailer_key'] ?? '');
            $retailerKey = $retailerKey !== '' ? $retailerKey : 'other';
            $retailerLabel = (string)($location['retailer_label'] ?? ($labels[$retailerKey] ?? 'Outras'));
            $locationCounts[$retailerLabel] = ($locationCounts[$retailerLabel] ?? 0) + 1;
            $locationCountsByKey[$retailerKey] = ($locationCountsByKey[$retailerKey] ?? 0) + 1;
            $locationLabels[(int)$location['id']] = '[' . $retailerLabel . '] ' . $location['name'] . (!empty($location['city']) ? ' · ' . $location['city'] : '') . ' [#' . (int)$location['id'] . ']';
        }
        ksort($locationCounts, SORT_NATURAL | SORT_FLAG_CASE);
        // Mantém sempre as cinco insígnias do modelo Pernod visíveis, mesmo quando
        // uma folha semanal não contém nomes específicos de lojas para criar aliases.
        $fixedRetailers = ['sonae','pingo_doce','auchan','intermarche','eci'];
        $summaryRetailers = array_values(array_filter(array_keys((array)($summary['retailers'] ?? [])), static fn($key) => $key !== 'all'));
        $briefingRetailers = array_values(array_unique(array_merge($fixedRetailers, $summaryRetailers)));
        $aliasCoverage = [];
        foreach ($briefingRetailers as $retailerKey) {
            $aliasCoverage[$retailerKey] = ['total' => 0, 'mapped' => 0, 'available' => (int)($locationCountsByKey[$retailerKey] ?? 0)];
        }
        foreach ($detail['aliases'] as $alias) {
            $key = (string)($alias['retailer_key'] ?? 'all');
            if (!isset($aliasCoverage[$key])) $aliasCoverage[$key] = ['total' => 0, 'mapped' => 0, 'available' => (int)($locationCountsByKey[$key] ?? 0)];
            $aliasCoverage[$key]['total']++;
            if (!empty($alias['location_id']) && empty($alias['identity_review_required'])) $aliasCoverage[$key]['mapped']++;
        }
        $locationsByRetailer = [];
        foreach ($briefingRetailers as $retailerKey) $locationsByRetailer[$retailerKey] = [];
        foreach ($locations as $location) {
            $retailerKey = (string)($location['retailer_key'] ?? '');
            if ($retailerKey !== '' && isset($locationsByRetailer[$retailerKey])) $locationsByRetailer[$retailerKey][] = $location;
        }
        $spaceItems = array_values(array_filter($detail['items'], static fn($item) => ($item['item_type'] ?? '') === 'space'));
        $assortmentItems = array_values(array_filter($detail['items'], static fn($item) => ($item['item_type'] ?? '') === 'assortment'));
        $sourceSheets = is_array($summary['source_sheets'] ?? null) ? $summary['source_sheets'] : [];
        $importIntelligence = is_array($summary['import_intelligence'] ?? null) ? $summary['import_intelligence'] : [];
        $cardexSync = is_array($summary['cardex_sync'] ?? null) ? $summary['cardex_sync'] : [];
        $cardexRead = ['retailers'=>0,'items'=>0,'anomalies'=>0,'by_retailer'=>[],'anomaly_details'=>[]];
        foreach ((array)($detail['sections'] ?? []) as $section) {
            if (sanitize_key((string)($section['section_type'] ?? '')) !== 'cardex') continue;
            $retailerKey = sanitize_key((string)($section['retailer_key'] ?? '')) ?: 'other';
            $content = is_array($section['content'] ?? null) ? $section['content'] : [];
            $itemsRead = count(array_filter((array)($content['items'] ?? []), 'is_array'));
            $anomaliesRead = array_values(array_filter((array)($content['anomalies'] ?? []), 'is_array'));
            if (!isset($cardexRead['by_retailer'][$retailerKey])) {
                $cardexRead['by_retailer'][$retailerKey] = ['items'=>0,'anomalies'=>0];
                $cardexRead['retailers']++;
            }
            $cardexRead['by_retailer'][$retailerKey]['items'] += $itemsRead;
            $cardexRead['by_retailer'][$retailerKey]['anomalies'] += count($anomaliesRead);
            $cardexRead['items'] += $itemsRead;
            $cardexRead['anomalies'] += count($anomaliesRead);
            foreach ($anomaliesRead as $anomaly) {
                if (count($cardexRead['anomaly_details']) >= 20) break;
                $cardexRead['anomaly_details'][] = array_merge(['retailer_key'=>$retailerKey], $anomaly);
            }
        }
        global $wpdb;
        $forms = $wpdb->get_results("SELECT id,title FROM {$wpdb->prefix}routespro_forms WHERE status='active' ORDER BY title ASC", ARRAY_A) ?: [];
        $preferredBriefingDashboardId = QuestionAnalyticsService::preferredBriefingDashboardId((int)$b['client_id'], (int)$b['project_id'], true);
        $analyticsDashboards = $wpdb->get_results($wpdb->prepare(
            "SELECT id,title,visibility,client_id,project_id FROM {$wpdb->prefix}routespro_analytics_dashboards WHERE is_active=1 AND (client_id IS NULL OR client_id=0 OR client_id=%d) AND (project_id IS NULL OR project_id=0 OR project_id=%d) ORDER BY sort_order ASC,id ASC",
            (int)$b['client_id'], (int)$b['project_id']
        ), ARRAY_A) ?: [];
        $evergreenMetricOptions = QuestionAnalyticsService::availableMetricOptions((int)$b['client_id'], (int)$b['project_id'], $preferredBriefingDashboardId);
        $executionQuestions = (array)($detail['executionQuestions'] ?? []);
        $logicalExecutionQuestions = self::groupExecutionQuestions($executionQuestions);
        // 9DX: presentation-only steps, never stored in submitted answers.
        $briefingSteps = BriefingSteps::all($briefingId);
        $briefingStepsByKey = [];
        foreach ($briefingSteps as $step) $briefingStepsByKey[$step['key']] = $step;
        $executionStates = (array)($detail['executionStates'] ?? []);
        foreach ($logicalExecutionQuestions as &$logicalExecutionQuestion) {
            $logicalKey = sanitize_key((string)($logicalExecutionQuestion['question_key'] ?? ''));
            $logicalExecutionQuestion['execution_state'] = (array)($executionStates[$logicalKey] ?? []);
        }
        unset($logicalExecutionQuestion);
        $shareSpaceSectionIds = [];
        foreach ((array)($detail['sections'] ?? []) as $sectionRow) {
            if (sanitize_key((string)($sectionRow['section_type'] ?? '')) === 'share_space') $shareSpaceSectionIds[absint($sectionRow['id'] ?? 0)] = true;
        }
        $comparisonConfigsBySection = [];
        foreach ((array)($detail['sections'] ?? []) as $sectionRow) {
            $sectionIdForConfig = absint($sectionRow['id'] ?? 0);
            if (!$sectionIdForConfig) continue;
            $sectionContent = is_array($sectionRow['content'] ?? null) ? $sectionRow['content'] : [];
            $sectionConfig = \RoutesPro\Briefings\ShareSpaceMatrix::configFromSection(
                $sectionContent,
                sanitize_key((string)($sectionRow['section_type'] ?? '')),
                ['reference_week'=>(int)($sectionContent['week'] ?? 0)]
            );
            if (!empty($sectionConfig['rows'])) $comparisonConfigsBySection[(string)$sectionIdForConfig] = $sectionConfig;
        }
        $legacyShareSpaceQuestions = array_values(array_filter($logicalExecutionQuestions, static function($row) use ($shareSpaceSectionIds) {
            return !empty($shareSpaceSectionIds[absint($row['briefing_section_id'] ?? 0)]) && sanitize_key((string)($row['question_type'] ?? '')) === 'percent';
        }));
        $editQuestionId = absint($_GET['edit_question'] ?? 0);
        $editingQuestion = [];
        foreach ($logicalExecutionQuestions as $logicalQuestion) {
            if ($editQuestionId > 0 && (int)($logicalQuestion['id'] ?? 0) === $editQuestionId) { $editingQuestion = $logicalQuestion; break; }
        }
        $nextQuestionOrder = $logicalExecutionQuestions ? (max(array_map(static fn($row) => (int)($row['priority'] ?? 0), $logicalExecutionQuestions)) + 10) : 10;
        $questionPerPageOptions = [10, 20, 50, 100];
        $questionPerPage = absint($_GET['ffb_qper_page'] ?? 20);
        if (!in_array($questionPerPage, $questionPerPageOptions, true)) $questionPerPage = 20;
        $questionTotal = count($logicalExecutionQuestions);
        $questionTotalPages = max(1, (int)ceil($questionTotal / $questionPerPage));
        $questionPage = max(1, min($questionTotalPages, absint($_GET['ffb_qpage'] ?? 1)));
        $questionOffset = ($questionPage - 1) * $questionPerPage;
        $pagedExecutionQuestions = array_slice($logicalExecutionQuestions, $questionOffset, $questionPerPage);
        $executionStats = (array)($detail['executionStats'] ?? []);
        $questionSuggestions = BriefingAIService::executionQuestionSuggestions($briefingId);
        foreach ($questionSuggestions as $suggestionIndex => $suggestion) {
            $questionSuggestions[$suggestionIndex]['_suggestion_id'] = self::questionSuggestionId((array)$suggestion, (int)$suggestionIndex);
        }
        $approvedSuggestionIds = [];
        $approvedLegacySignatures = [];
        foreach ($executionQuestions as $existingQuestion) {
            $itemKey = sanitize_key((string)($existingQuestion['briefing_item_key'] ?? ''));
            if (strpos($itemKey, 'ffq_') === 0) {
                $approvedSuggestionIds[$itemKey] = true;
            } else {
                $signature = self::questionSignature((int)($existingQuestion['briefing_section_id'] ?? 0), (string)($existingQuestion['question_label'] ?? ''));
                if ($signature !== '') $approvedLegacySignatures[$signature] = true;
            }
        }
        $questionSuggestions = array_values(array_filter($questionSuggestions, static function($suggestion) use ($approvedSuggestionIds, $approvedLegacySignatures) {
            $suggestionId = sanitize_key((string)($suggestion['_suggestion_id'] ?? ''));
            if ($suggestionId !== '' && !empty($approvedSuggestionIds[$suggestionId])) return false;
            $signature = self::questionSignature((int)($suggestion['briefing_section_id'] ?? 0), (string)($suggestion['label'] ?? ''));
            return $signature !== '' && empty($approvedLegacySignatures[$signature]);
        }));
        $formsWithBriefingBlock = [];
        foreach ($forms as $formRow) {
            $fullForm = FormsModule::get_form((int)$formRow['id']);
            $formSchema = FormsModule::decode_schema((string)($fullForm['schema_json'] ?? ''));
            foreach ((array)($formSchema['questions'] ?? []) as $formQuestion) {
                if (is_array($formQuestion) && sanitize_key((string)($formQuestion['type'] ?? '')) === 'briefing_block') {
                    $formsWithBriefingBlock[(int)$formRow['id']] = true;
                    break;
                }
            }
        }
        $questionForm = $editingQuestion ?: [
            'id'=>0,'question_key'=>'','question_label'=>'','form_id'=>0,'briefing_section_id'=>0,'question_type'=>'radio','target_scope'=>'section',
            'location_ids'=>[],'question_options'=>['Sim','Não','Não aplicável'],'priority'=>$nextQuestionOrder,'help_text'=>'','is_required'=>0,'is_active'=>1,
            'analytics_enabled'=>1,'analytics_behavior'=>'rollup','analytics_dashboard_id'=>$preferredBriefingDashboardId,'analytics_metric_key'=>'','analytics_metric_label'=>'','analytics_chart_type'=>'bar','analytics_aggregation'=>'count',
            'analytics_dimension'=>'location_id','analytics_secondary_dimension'=>'','analytics_sort_order'=>$nextQuestionOrder,
            'analytics_show_kpi'=>0,'analytics_show_table'=>0,'analytics_show_empty'=>1,'question_config'=>[],'briefing_step_key'=>'',
        ];
        if (!empty($questionForm['analytics_enabled']) && empty($questionForm['analytics_dashboard_id']) && $preferredBriefingDashboardId) {
            $questionForm['analytics_dashboard_id'] = $preferredBriefingDashboardId;
        }
        if (!isset($questionForm['analytics_metric_key'])) $questionForm['analytics_metric_key'] = '';
        $questionForm['analytics_behavior'] = QuestionAnalyticsService::analyticsBehaviorFromRow($questionForm, false);
        if ($questionForm['analytics_behavior'] === 'off') $questionForm['analytics_behavior'] = 'rollup';
        $questionAnalyticsRules = json_decode((string)($questionForm['visibility_rules_json'] ?? ''), true);
        $questionAnalyticsRules = is_array($questionAnalyticsRules) ? $questionAnalyticsRules : [];
        $questionAnalyticsUserConfirmed = QuestionAnalyticsService::analyticsChoiceIsUserConfirmed($questionAnalyticsRules);
        $questionOptionsText = implode("\n", (array)($questionForm['question_options'] ?? []));
        $productCategories = CategoryMapping::categories((int)$b['client_id']);
        $questionCategory = CategoryMapping::resolveForQuestion(
            $b, (string)($questionForm['question_label'] ?? ''), (int)($questionForm['briefing_section_id'] ?? 0)
        );
        $editingCategoryRules = json_decode((string)($questionForm['visibility_rules_json'] ?? ''), true);
        $editingCategoryRules = is_array($editingCategoryRules) ? $editingCategoryRules : [];
        if (!empty($editingCategoryRules['product_category']) &&
            (string)($editingCategoryRules['category_identity'] ?? '') === (string)$questionCategory['key']) {
            $questionCategory['category'] = (string)$editingCategoryRules['product_category'];
            $questionCategory['source'] = (string)($editingCategoryRules['category_source'] ?? 'manual');
        }

        $questionConfig = \RoutesPro\Briefings\ShareSpaceMatrix::sanitizeConfig($questionForm['question_config'] ?? ($questionForm['question_config_json'] ?? []));
        $questionConfigJson = !empty($questionConfig['rows']) ? wp_json_encode($questionConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $questionSelectedLocations = array_values(array_unique(array_map('absint', (array)($questionForm['location_ids'] ?? []))));
        $questionFormDefaults = QuestionAnalyticsService::defaultsForType((string)($questionForm['question_type'] ?? 'radio'), [
            'chart_type'=>$questionForm['analytics_chart_type'] ?? '',
            'aggregation'=>$questionForm['analytics_aggregation'] ?? '',
            'dimension'=>$questionForm['analytics_dimension'] ?? '',
            'secondary_dimension'=>$questionForm['analytics_secondary_dimension'] ?? '',
            'show_kpi'=>$questionForm['analytics_show_kpi'] ?? 0,
            'show_table'=>$questionForm['analytics_show_table'] ?? 0,
            'show_empty'=>array_key_exists('analytics_show_empty',$questionForm)?$questionForm['analytics_show_empty']:1,
        ]);
        ?>
        <?php if(in_array($aiJobStatus,['queued','running'],true)): ?><script>window.setTimeout(function(){window.location.reload();},6000);</script><?php endif; ?>
        <section class="ffb-detail" id="ffb-detail" data-comparison-configs="<?php echo esc_attr(wp_json_encode($comparisonConfigsBySection, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>">
          <div class="ffb-detail-hero">
            <div><span class="ffb-kicker">W<?php echo (int)$b['week_no']; ?> · <?php echo (int)$b['year_no']; ?> · versão <?php echo (int)$b['version_no']; ?></span><h2><?php echo esc_html($b['title']); ?></h2><p><?php echo esc_html(self::dateLabel($b['valid_from'])); ?> a <?php echo esc_html(self::dateLabel($b['valid_to'])); ?>, <?php echo esc_html($b['source_filename']); ?></p></div>
            <div class="ffb-actions">
              <?php if($b['status']!=='published'): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="fieldflow_briefing_publish"><input type="hidden" name="briefing_id" value="<?php echo (int)$b['id']; ?>"><?php wp_nonce_field('fieldflow_briefing_publish_'.$b['id']); ?><button class="button button-primary">Publicar para a equipa</button></form><?php endif; ?>
              <?php if($b['status']==='published'): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="fieldflow_briefing_archive"><input type="hidden" name="briefing_id" value="<?php echo (int)$b['id']; ?>"><?php wp_nonce_field('fieldflow_briefing_archive_'.$b['id']); ?><button class="button">Arquivar</button></form><?php endif; ?>
              <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fieldflow_briefing_export&briefing_id='.(int)$b['id']), 'fieldflow_briefing_export_'.$b['id'])); ?>">Exportar prova CSV</a>
              <?php if($aiReady): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="fieldflow_briefing_ai_analyze"><input type="hidden" name="briefing_id" value="<?php echo (int)$b['id']; ?>"><?php wp_nonce_field('fieldflow_briefing_ai_analyze_'.$b['id']); ?><button class="button ffb-ai-button" <?php disabled(in_array($aiJobStatus,['queued','running'],true)); ?>><?php echo in_array($aiJobStatus,['queued','running'],true)?'IA em processamento':($ai?'Atualizar análise IA':'Analisar com IA'); ?></button></form><?php endif; ?>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Eliminar definitivamente este briefing e a respetiva evidência de leitura?');"><input type="hidden" name="action" value="fieldflow_briefing_delete"><input type="hidden" name="briefing_id" value="<?php echo (int)$b['id']; ?>"><?php wp_nonce_field('fieldflow_briefing_delete_'.$b['id']); ?><button class="button button-link-delete">Eliminar</button></form>
            </div>
          </div>
          <div class="ffb-kpis">
            <?php self::kpi('Secções',(int)($summary['sections']??count($detail['sections'])),'Conteúdo operacional'); ?>
            <?php self::kpi('Promoções',(int)($summary['promotions']??0),'Produtos promocionais'); ?>
            <?php self::kpi('Espaços',count($spaceItems),'Espaços centralizados'); ?>
            <?php self::kpi('Share Space',(int)($summary['share_space_kpis']??0),'KPI importados'); ?>
            <?php self::kpi('Lojas mapeadas',(int)($summary['store_aliases_mapped']??0),'Associações reutilizáveis'); ?>
            <?php self::kpi('Por associar',(int)($summary['store_aliases_unmapped']??0),'Revisão necessária'); ?>
            <?php self::kpi('Perguntas execução',(int)($executionStats['questions']??count($executionQuestions)),(int)($executionStats['answers']??0).' respostas registadas'); ?>
            <?php self::kpi('Destinatários',count($detail['recipients']),'Snapshot publicado'); ?>
            <?php self::kpi('Qualidade IA',$ai?((int)($aiAudit['score']??0).'/100'):'Por analisar',$ai?(!empty($ai['stale'])?'Análise desatualizada':($ai['provider']??'IA FieldFlow')):($aiReady?'Copiloto disponível':'IA não configurada')); ?>
          </div>

          <div class="ffb-tabs"><button type="button" class="active" data-ffb-tab="ai">Copiloto IA</button><button type="button" data-ffb-tab="mapping">Lojas e aliases</button><button type="button" data-ffb-tab="content">Conteúdo importado</button><button type="button" data-ffb-tab="questions">Perguntas de execução</button><button type="button" data-ffb-tab="sources">Folhas do Excel</button><button type="button" data-ffb-tab="evidence">Leitura da equipa</button></div>

          <div class="ffb-tab-panel active" data-ffb-panel="ai">
            <?php self::renderAIPanel($b, $ai, $aiReady, $aiJob); ?>
          </div>

          <div class="ffb-tab-panel" data-ffb-panel="mapping">
            <div class="ffb-card">
              <div class="ffb-head"><div><span class="ffb-kicker">Mapa permanente</span><h2>Associar nomes externos às lojas existentes</h2><p>Esta associação fica guardada e será reutilizada nos próximos ficheiros semanais.</p></div><span class="ffb-pill"><?php echo count($detail['aliases']); ?> nomes</span></div>
              <div class="ffb-alias-tools"><input type="search" id="ffb-alias-search" placeholder="Pesquisar nome do Excel, insígnia ou loja associada"><span class="description">Seleciona uma loja da pesquisa. O ID interno é guardado automaticamente.</span></div>
              <div class="ffb-retailer-coverage">
                <?php foreach($aliasCoverage as $retailerKey=>$coverage): ?><button type="button" class="ffb-retailer-coverage-card" data-retailer-filter="<?php echo esc_attr($retailerKey); ?>"><strong><?php echo esc_html($labels[$retailerKey]??ucwords(str_replace('_',' ',$retailerKey))); ?></strong><span><?php echo (int)$coverage['mapped']; ?>/<?php echo (int)$coverage['total']; ?> nomes associados</span><small><?php echo (int)$coverage['available']; ?> lojas FieldFlow disponíveis<?php echo !$coverage['total']?' · sem nomes específicos nesta folha':''; ?></small></button><?php endforeach; ?>
                <button type="button" class="ffb-retailer-coverage-card active" data-retailer-filter="all"><strong>Todas</strong><span><?php echo count($detail['aliases']); ?> nomes externos</span><small><?php echo count($locations); ?> lojas disponíveis no âmbito</small></button>
              </div>
              <div class="ffb-dictionary-note"><strong>Como funciona:</strong> o mapping apresenta apenas nomes de lojas realmente encontrados no Excel. Neste W29, os nomes específicos surgem em Sonae e Auchan. Pingo Doce, Intermarché e El Corte Inglés estão integrados através das respetivas prioridades, promoções, VAPs e cardex, e aparecem acima com todas as lojas FieldFlow disponíveis para a campanha.</div>
              <div class="ffb-internal-store-coverage">
                <?php foreach($briefingRetailers as $retailerKey): $retailerLocations=$locationsByRetailer[$retailerKey]??[]; ?>
                  <details><summary><strong><?php echo esc_html($labels[$retailerKey]??$retailerKey); ?></strong><span><?php echo count($retailerLocations); ?> lojas FieldFlow disponíveis</span></summary><div><?php if(!$retailerLocations): ?><p>Não foram identificadas lojas internas desta insígnia no âmbito atual.</p><?php else: ?><p><?php echo esc_html(implode(' · ',array_map(static fn($row)=>(string)$row['name'],array_slice($retailerLocations,0,12)))); ?><?php if(count($retailerLocations)>12): ?> · +<?php echo count($retailerLocations)-12; ?> lojas<?php endif; ?></p><?php endif; ?></div></details>
                <?php endforeach; ?>
              </div>
              <div class="ffb-alias-empty" data-alias-empty hidden>Esta insígnia não contém nomes de lojas específicos no Excel W29. O respetivo briefing, promoções, VAPs e cardex continuam integrados e visíveis nos shortcodes.</div>
              <?php if($aiSuggestions): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ffb-ai-map-form" onsubmit="return confirm('Aplicar automaticamente apenas as sugestões de IA com confiança igual ou superior a 85%?');"><input type="hidden" name="action" value="fieldflow_briefing_ai_apply_mappings"><input type="hidden" name="briefing_id" value="<?php echo (int)$b['id']; ?>"><?php wp_nonce_field('fieldflow_briefing_ai_apply_mappings_'.$b['id']); ?><button class="button ffb-ai-button">Aplicar sugestões IA ≥ 85%</button><span class="description"><?php echo count($aiSuggestions); ?> correspondências propostas. As restantes continuam para validação manual.</span></form><?php endif; ?>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ffb-ai-map-form" onsubmit="return confirm('Aplicar apenas correspondências exatas ou inequivocamente normalizadas dentro da mesma insígnia?');"><input type="hidden" name="action" value="fieldflow_briefing_safe_mappings"><input type="hidden" name="briefing_id" value="<?php echo (int)$b['id']; ?>"><?php wp_nonce_field('fieldflow_briefing_safe_mappings_'.$b['id']); ?><button class="button">Aplicar mapping seguro</button><span class="description">Cruza nome normalizado, insígnia e lojas ativas da campanha. Casos ambíguos ficam por validar.</span></form>
              <datalist id="ffb-location-list"><?php foreach($locations as $location): $locationLabel=$locationLabels[(int)$location['id']]; ?><option value="<?php echo esc_attr($locationLabel); ?>" data-id="<?php echo (int)$location['id']; ?>"></option><?php endforeach; ?></datalist>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fieldflow_briefing_map"><input type="hidden" name="briefing_id" value="<?php echo (int)$b['id']; ?>"><?php wp_nonce_field('fieldflow_briefing_map_'.$b['id']); ?>
                <div class="ffb-table-wrap"><table class="widefat striped"><thead><tr><th>Insígnia</th><th>Nome no Excel</th><th>Loja FieldFlow</th><th>Uso</th></tr></thead><tbody>
                <?php foreach($detail['aliases'] as $alias): ?>
                  <?php $mappedId=(int)$alias['location_id']; $mappedLabel=$mappedId&&isset($locationLabels[$mappedId])?$locationLabels[$mappedId]:''; $suggestion=$aiSuggestions[(int)$alias['id']]??[]; $suggestionId=absint($suggestion['location_id']??0); $suggestionLabel=$suggestionId&&isset($locationLabels[$suggestionId])?$locationLabels[$suggestionId]:(string)($suggestion['location_label']??''); $rowSearch=trim(($labels[$alias['retailer_key']]??$alias['retailer_key']).' '.$alias['external_name'].' '.$alias['normalized_name'].' '.$mappedLabel.' '.$suggestionLabel); ?>
                  <tr class="<?php echo empty($alias['location_id'])?'ffb-unmapped':''; ?>" data-alias-row data-retailer="<?php echo esc_attr($alias['retailer_key']); ?>" data-search="<?php echo esc_attr(function_exists('mb_strtolower')?mb_strtolower($rowSearch):strtolower($rowSearch)); ?>"><td><?php echo esc_html($labels[$alias['retailer_key']]??$alias['retailer_key']); ?></td><td><strong><?php echo esc_html($alias['external_name']); ?></strong><br><small><?php echo esc_html($alias['normalized_name']); ?></small></td><td><div class="ffb-location-picker-wrap"><input class="regular-text ffb-location-picker" type="search" list="ffb-location-list" value="<?php echo esc_attr($mappedLabel); ?>" placeholder="Pesquisar loja FieldFlow"><input type="hidden" class="ffb-location-id" name="mapping[<?php echo (int)$alias['id']; ?>]" value="<?php echo $mappedId; ?>"><button type="button" class="button button-small ffb-clear-location" title="Retirar associação">Limpar</button></div><div class="description ffb-location-state"><?php echo esc_html($alias['location_name'] ?: 'Ainda não associada'); ?></div><?php if(!empty($alias['identity_review_required'])): ?><small class="ffb-negative">Por rever: insígnia ou subtipo incompatível. Limpa e seleciona um PDV compatível.</small><?php endif; ?><?php if(!$mappedId&&$suggestionId&&$suggestionLabel): ?><button type="button" class="button button-small ffb-ai-suggestion" data-location-id="<?php echo $suggestionId; ?>" data-location-label="<?php echo esc_attr($suggestionLabel); ?>">Usar sugestão IA, <?php echo (int)($suggestion['confidence']??0); ?>%</button><small class="ffb-ai-reason"><?php echo esc_html((string)($suggestion['reason']??'')); ?></small><?php endif; ?></td><td><?php echo (int)$alias['item_count']; ?> instruções</td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
                <p><button class="button button-primary">Guardar associações</button></p>
              </form>
            </div>
          </div>

          <div class="ffb-tab-panel" data-ffb-panel="content">
            <div class="ffb-content-grid">
            <?php foreach($detail['sections'] as $section): ?><article class="ffb-card ffb-content-card"><div class="ffb-head"><div><span class="ffb-kicker"><?php echo esc_html($labels[$section['retailer_key']]??$section['retailer_key']); ?></span><h3><?php echo esc_html($section['title']); ?></h3></div><span class="ffb-pill"><?php echo esc_html($section['section_type']); ?></span></div><?php self::renderContentPreview($section['content']); ?></article><?php endforeach; ?>
            </div>
            <div class="ffb-card ffb-store-source-card"><div class="ffb-head"><div><span class="ffb-kicker">Folha ESPAÇOS CENTRALIZADOS</span><h2>Espaços centralizados</h2><p>Todos os registos Sonae e Auchan encontrados no Excel, mesmo antes do mapping.</p></div><span class="ffb-pill"><?php echo count($spaceItems); ?> linhas</span></div><?php self::renderStoreItemsTable($spaceItems,$labels); ?></div>
            <div class="ffb-card ffb-store-source-card"><div class="ffb-head"><div><span class="ffb-kicker">Folha CARDEX por loja</span><h2>Sortido específico por loja</h2><p>Associações de Macieira Cream importadas da folha dedicada.</p></div><span class="ffb-pill"><?php echo count($assortmentItems); ?> linhas</span></div><?php self::renderStoreItemsTable($assortmentItems,$labels); ?></div>
          </div>

          <div class="ffb-tab-panel" data-ffb-panel="questions">
            <?php if($legacyShareSpaceQuestions): ?><div class="ffb-card ffb-repair-card"><div class="ffb-head"><div><span class="ffb-kicker">Atualização Share Space</span><h2>Converter percentagens manuais em frentes calculadas</h2><p>Foram encontradas <?php echo count($legacyShareSpaceQuestions); ?> pergunta(s) antiga(s) de percentagem. A conversão cria uma matriz única por loja, preserva o histórico e desativa as perguntas antigas.</p></div><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Converter as perguntas antigas de Share Space para a nova matriz de frentes?');"><input type="hidden" name="action" value="fieldflow_briefing_share_space_repair"><input type="hidden" name="briefing_id" value="<?php echo (int)$b['id']; ?>"><?php wp_nonce_field('fieldflow_briefing_share_space_repair_'.$b['id']); ?><button class="button button-primary">Converter agora</button></form></div></div><?php endif; ?>
            <div class="ffb-grid ffb-questions-layout">
              <div class="ffb-card ffb-wide ffb-steps-card">
                <div class="ffb-head"><div><span class="ffb-kicker">Organização do formulário</span><h2>Steps do briefing</h2><p>Cria categorias para organizar as perguntas na visita. Depois associa cada pergunta a um step no editor ou nas sugestões de IA. As respostas e identificadores das perguntas ficam inalterados.</p></div><span class="ffb-pill"><?php echo count($briefingSteps); ?> steps</span></div>
                <?php if ($briefingSteps): ?><div class="ffb-steps-stack">
                  <?php foreach ($briefingSteps as $step): ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ffb-step-row">
                      <input type="hidden" name="action" value="fieldflow_briefing_step_save"><input type="hidden" name="briefing_id" value="<?php echo (int)$b['id']; ?>"><input type="hidden" name="step_key" value="<?php echo esc_attr($step['key']); ?>"><?php wp_nonce_field('fieldflow_briefing_step_save_'.$b['id']); ?>
                      <label>Título do step<input type="text" name="step_title" required maxlength="100" value="<?php echo esc_attr($step['title']); ?>"></label>
                      <label>Descrição opcional<input type="text" name="step_description" maxlength="240" value="<?php echo esc_attr($step['description']); ?>"></label>
                      <label>Ordem<input type="number" name="step_order" min="1" max="9999" value="<?php echo (int)$step['order']; ?>"></label>
                      <button class="button">Guardar step</button>
                    </form>
                  <?php endforeach; ?>
                </div><?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ffb-step-row ffb-step-create">
                  <input type="hidden" name="action" value="fieldflow_briefing_step_save"><input type="hidden" name="briefing_id" value="<?php echo (int)$b['id']; ?>"><?php wp_nonce_field('fieldflow_briefing_step_save_'.$b['id']); ?>
                  <label>Novo step<input type="text" name="step_title" maxlength="100" required placeholder="Ex.: Frentes e concorrência"></label>
                  <label>Descrição opcional<input type="text" name="step_description" maxlength="240" placeholder="Ex.: Medição, frentes e share of space"></label>
                  <label>Ordem<input type="number" name="step_order" min="1" max="9999" value="<?php echo count($briefingSteps) ? min(9999, max(array_column($briefingSteps,'order')) + 10) : 10; ?>"></label>
                  <button class="button button-primary">Adicionar step</button>
                </form>
                <p class="description">Steps são exclusivos deste briefing e aparecem apenas nas lojas com perguntas atribuídas. Um step sem perguntas não aparece na app. Não apagues ou recries perguntas para as organizar.</p>
              </div>
              <div class="ffb-card ffb-wide ffb-ai-suggestions-card">
                <div class="ffb-head"><div><span class="ffb-kicker">Copiloto IA</span><h2>Sugestões automáticas de perguntas</h2><p>A IA transforma prioridades, promoções, VAPs, espaços e Share Space em verificações objetivas. São apresentadas todas as referências detetadas e podes alterar cada sugestão para fotografia de evidência antes de aprovar.</p></div><span class="ffb-pill"><?php echo count($questionSuggestions); ?> sugestões</span></div>
                <?php if(!$questionSuggestions): ?><p class="description">Atualiza a análise IA do briefing para gerar sugestões.</p><?php else: ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ffb-ai-question-bulk-form" data-ai-question-bulk-form>
                  <input type="hidden" name="action" value="fieldflow_briefing_questions_bulk_approve">
                  <input type="hidden" name="briefing_id" value="<?php echo (int)$b['id']; ?>">
                  <?php wp_nonce_field('fieldflow_briefing_questions_bulk_approve_'.$b['id']); ?>
                  <input type="hidden" name="selected_suggestions_json" value="" data-selected-suggestions-json>
                  <div class="ffb-ai-question-toolbar">
                    <label class="ffb-ai-select-all"><input type="checkbox" data-select-all-ai-suggestions> Selecionar todas</label>
                    <div class="ffb-ai-question-actions"><button type="submit" class="button button-primary" name="approve_selected" value="1">Aprovar selecionadas</button><span class="description" data-ai-selected-count>0 selecionadas</span></div>
                  </div>
                  <div class="ffb-preview-list">
                  <?php foreach($questionSuggestions as $suggestionIndex=>$suggestion): $suggestionId=sanitize_key((string)($suggestion['_suggestion_id']??self::questionSuggestionId((array)$suggestion,(int)$suggestionIndex))); ?>
                    <article class="ffb-ai-question-suggestion" data-ai-suggestion-card data-suggestion-id="<?php echo esc_attr($suggestionId); ?>">
                      <div class="ffb-ai-suggestion-select"><input type="checkbox" name="suggestion_ids[]" value="<?php echo esc_attr($suggestionId); ?>" data-ai-suggestion-checkbox aria-label="Selecionar sugestão"></div>
                      <input type="hidden" name="suggestions[<?php echo esc_attr($suggestionId); ?>][question_label]" value="<?php echo esc_attr((string)($suggestion['label']??'')); ?>">
                      <input type="hidden" name="suggestions[<?php echo esc_attr($suggestionId); ?>][briefing_section_id]" value="<?php echo (int)($suggestion['briefing_section_id']??0); ?>">
                      <input type="hidden" name="suggestions[<?php echo esc_attr($suggestionId); ?>][options]" value="<?php echo esc_attr(implode("\n",(array)($suggestion['options']??[]))); ?>">
                      <input type="hidden" name="suggestions[<?php echo esc_attr($suggestionId); ?>][help_text]" value="<?php echo esc_attr((string)($suggestion['help_text']??'')); ?>">
                      <input type="hidden" name="suggestions[<?php echo esc_attr($suggestionId); ?>][question_config_json]" value="<?php echo esc_attr(wp_json_encode((array)($suggestion['question_config']??[]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>">
                      <input type="hidden" name="suggestions[<?php echo esc_attr($suggestionId); ?>][target_scope]" value="section"><input type="hidden" name="suggestions[<?php echo esc_attr($suggestionId); ?>][question_order]" value="<?php echo (int)($nextQuestionOrder+($suggestionIndex*10)); ?>">
                      <div><strong><?php echo esc_html((string)($suggestion['label']??'Pergunta sugerida')); ?></strong><small><?php echo esc_html((string)($suggestion['reason']??'')); ?></small><?php $suggestionRows=(array)($suggestion['question_config']['rows']??[]); if($suggestionRows): ?><span class="ffb-matrix-preview"><?php echo count($suggestionRows); ?> comparações, cálculo automático de quota e objetivo</span><?php endif; ?></div>
                      <label>Tipo de resposta<select name="suggestions[<?php echo esc_attr($suggestionId); ?>][question_type]"><?php $suggestedType=sanitize_key((string)($suggestion['question_type']??'radio')); ?><option value="radio" <?php selected($suggestedType,'radio'); ?>>Sim / Não / Não aplicável</option><option value="text" <?php selected($suggestedType,'text'); ?>>Texto</option><option value="textarea" <?php selected($suggestedType,'textarea'); ?>>Texto longo</option><option value="number" <?php selected($suggestedType,'number'); ?>>Número</option><option value="percent" <?php selected($suggestedType,'percent'); ?>>Percentagem manual</option><option value="share_space_matrix" <?php selected($suggestedType,'share_space_matrix'); ?>>Comparativo automático, referências do briefing</option><option value="evidence_check" <?php selected($suggestedType,'evidence_check'); ?>>Conformidade + evidência, Sim/Não + foto/motivo opcionais</option><option value="image_upload" <?php selected($suggestedType,'image_upload'); ?>>Fotografia de evidência</option><option value="categorized_image_upload" <?php selected($suggestedType,'categorized_image_upload'); ?>>Fotografias por categoria</option><option value="file_upload" <?php selected($suggestedType,'file_upload'); ?>>Ficheiro</option></select></label>
                      <label>Formulário<select name="suggestions[<?php echo esc_attr($suggestionId); ?>][form_id]"><option value="0">Qualquer formulário com Bloco de Briefing</option><?php foreach($forms as $form): ?><option value="<?php echo (int)$form['id']; ?>"><?php echo esc_html($form['title']); ?><?php echo !empty($formsWithBriefingBlock[(int)$form['id']])?' · integrado':' · sem bloco'; ?></option><?php endforeach; ?></select></label>
                      <label>Step / categoria<select name="suggestions[<?php echo esc_attr($suggestionId); ?>][briefing_step_key]"><option value="">Sem step específico</option><?php foreach($briefingSteps as $step): ?><option value="<?php echo esc_attr($step['key']); ?>"><?php echo esc_html($step['title']); ?></option><?php endforeach; ?></select></label>
                      <label>Widget<select name="suggestions[<?php echo esc_attr($suggestionId); ?>][analytics_chart_type]"><option value="bar">Barras</option><option value="kpi">KPI</option><option value="table">Tabela</option><option value="pie">Circular</option><option value="image_gallery">Galeria</option></select><input type="hidden" name="suggestions[<?php echo esc_attr($suggestionId); ?>][analytics_enabled]" value="1"></label>
                      <label class="ffb-check ffb-ai-required"><input type="hidden" name="suggestions[<?php echo esc_attr($suggestionId); ?>][is_required]" value="0"><input type="checkbox" name="suggestions[<?php echo esc_attr($suggestionId); ?>][is_required]" value="1" <?php checked(!empty($suggestion['required'])); ?>><span><strong>Obrigatória</strong><small>Para fotografia de evidência, mantém ativo quando a execução tem de ficar comprovada.</small></span></label>
                      <button type="submit" class="button button-primary" name="single_suggestion_id" value="<?php echo esc_attr($suggestionId); ?>">Aprovar sugestão</button>
                    </article>
                  <?php endforeach; ?>
                  </div>
                </form><?php endif; ?>
              </div>
              <div class="ffb-card ffb-question-editor-card" id="ffb-question-editor">
                <div class="ffb-head"><div><span class="ffb-kicker">Briefing + formulário + analytics</span><h2><?php echo $editingQuestion?'Editar pergunta de execução':'Criar pergunta de execução'; ?></h2><p>Edita o conteúdo, o tipo de resposta, o âmbito e o widget que será usado no portal do cliente e na app do merchandiser.</p></div><span class="ffb-pill"><?php echo (int)($executionStats['questions']??0); ?> perguntas · <?php echo (int)($executionStats['mapped_stores']??0); ?> lojas</span></div>
                <div class="ffb-scope-banner" style="margin-bottom:14px"><strong>Fonte única:</strong> a atribuição às lojas é definida aqui. O widget é sincronizado para o dashboard permanente e alimenta o mesmo histórico no portal do cliente e no FieldFlow App, sem criar um widget novo por semana.</div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ffb-form" data-question-editor>
                  <input type="hidden" name="action" value="fieldflow_briefing_question_save"><input type="hidden" name="briefing_id" value="<?php echo (int)$b['id']; ?>"><input type="hidden" name="question_id" value="<?php echo (int)($questionForm['id']??0); ?>"><input type="hidden" name="original_question_key" value="<?php echo esc_attr((string)($questionForm['question_key']??'')); ?>"><?php wp_nonce_field('fieldflow_briefing_question_save_'.$b['id']); ?>
                  <label class="ffb-wide">Pergunta<input type="text" name="question_label" oninput="var c=this.form.querySelector('[data-ffb-product-category]');if(c){c.value='';this.form.querySelector('[data-ffb-category-override]').value='0';}" required value="<?php echo esc_attr((string)($questionForm['question_label']??'')); ?>" placeholder="Ex.: O espaço centralizado está implementado?"></label>
                  <label>Formulário<select name="form_id"><option value="0">Qualquer formulário com Bloco de Briefing</option><?php foreach($forms as $form): ?><option value="<?php echo (int)$form['id']; ?>" <?php selected((int)($questionForm['form_id']??0),(int)$form['id']); ?>><?php echo esc_html($form['title']); ?><?php echo !empty($formsWithBriefingBlock[(int)$form['id']])?' · integrado':' · sem bloco'; ?></option><?php endforeach; ?></select></label>
                  <label>Secção do briefing<select name="briefing_section_id" onchange="var c=this.form.querySelector('[data-ffb-product-category]');if(c){c.value='';this.form.querySelector('[data-ffb-category-override]').value='0';}"><option value="0">Briefing geral</option><?php foreach($detail['sections'] as $section): ?><option value="<?php echo (int)$section['id']; ?>" <?php selected((int)($questionForm['briefing_section_id']??0),(int)$section['id']); ?>><?php echo esc_html(($labels[$section['retailer_key']]??$section['retailer_key']).' · '.$section['title']); ?></option><?php endforeach; ?></select></label>
                  <label>Step / categoria<select name="briefing_step_key"><option value="">Sem step específico, manter fluxo atual</option><?php foreach($briefingSteps as $step): ?><option value="<?php echo esc_attr($step['key']); ?>" <?php selected((string)($questionForm['briefing_step_key']??''),$step['key']); ?>><?php echo esc_html($step['title']); ?></option><?php endforeach; ?></select><small>O step organiza a apresentação, sem mudar a chave, a resposta ou as lojas da pergunta.</small></label>
                  <label>Tipo de resposta<select name="question_type" data-question-type><?php foreach(['radio'=>'Sim / Não / Não aplicável','select'=>'Lista','text'=>'Texto','textarea'=>'Texto longo','number'=>'Número','currency'=>'Moeda','percent'=>'Percentagem manual','share_space_matrix'=>'Comparativo automático, referências do briefing','evidence_check'=>'Conformidade + evidência, Sim/Não + foto/motivo opcionais','date'=>'Data','time'=>'Hora','checkbox'=>'Checkbox','image_upload'=>'Fotografia de evidência','categorized_image_upload'=>'Fotografias por categoria','file_upload'=>'Ficheiro'] as $value=>$typeLabel): ?><option value="<?php echo esc_attr($value); ?>" <?php selected((string)($questionForm['question_type']??'radio'),$value); ?>><?php echo esc_html($typeLabel); ?></option><?php endforeach; ?></select></label>
                  <label>Categoria do produto
                    <input type="hidden" name="product_category_identity" value="<?php echo esc_attr((string)($questionCategory['key'] ?? '')); ?>">
                    <input type="hidden" name="product_category_override" value="0" data-ffb-category-override>
                    <select name="product_category" data-ffb-product-category onchange="this.form.querySelector('[data-ffb-category-override]').value='1'">
                      <option value="">Detetar automaticamente<?php echo !empty($questionCategory['category']) ? ' · ' . esc_html($questionCategory['category']) : ' ou deixar por rever'; ?></option>
                      <?php foreach ($productCategories as $productCategory): ?>
                      <option value="<?php echo esc_attr($productCategory); ?>" <?php selected((string)($questionCategory['source'] ?? '') === 'manual' ? (string)($questionCategory['category'] ?? '') : '', $productCategory); ?>><?php echo esc_html($productCategory); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <small>Se o produto e a categoria forem inequívocos, o FieldFlow associa automaticamente ao guardar. Qualquer escolha manual mantém-se nas próximas semanas para a mesma identidade de produto.</small>
                    <small class="ffb-category-status">Estado: <?php echo esc_html((string)($questionCategory['status'] ?? 'pending') === 'automatic' ? 'Associação automática' : ((string)($questionCategory['status'] ?? '') === 'confirmed' ? 'Confirmada manualmente' : 'Por rever')); ?></small>
                  </label>
                  <label>Aplicação<select name="target_scope"><?php foreach(BriefingService::questionScopeLabels() as $scopeValue=>$scopeLabel): ?><option value="<?php echo esc_attr($scopeValue); ?>" <?php selected((string)($questionForm['target_scope']??'section'),$scopeValue); ?>><?php echo esc_html($scopeLabel); ?></option><?php endforeach; ?></select><small>Os modos “ativas” usam a carteira ativa do projeto/campanha e não dependem do mapping do ficheiro.</small></label>
                  <label class="ffb-wide">Lojas específicas<select name="location_ids[]" multiple size="9"><option value="0">Nenhuma seleção específica</option><?php foreach($questionLocations as $location): ?><option value="<?php echo (int)$location['id']; ?>" <?php selected(in_array((int)$location['id'],$questionSelectedLocations,true),true); ?>><?php echo esc_html($locationLabels[(int)$location['id']]??$location['name']); ?></option><?php endforeach; ?></select><small>Usado em “Lojas mapeadas selecionadas” e “Lojas ativas selecionadas do projeto”. No segundo modo podes escolher qualquer loja ativa da campanha, mesmo que não esteja mapeada no briefing.</small></label>
                  <label class="ffb-wide">Opções<textarea name="options" rows="3" placeholder="Sim&#10;Não&#10;Não aplicável"><?php echo esc_textarea($questionOptionsText); ?></textarea></label>
                  <div class="ffb-wide ffb-question-config ffb-comparison-config" data-comparison-config hidden>
                    <div class="ffb-comparison-head"><div><strong>Comparativo automático</strong><small>Escolhe uma secção e o FieldFlow identifica as referências existentes no briefing. Podes corrigir, remover ou acrescentar pares antes de guardar.</small></div><div class="ffb-comparison-actions"><button type="button" class="button" data-comparison-detect>Detetar referências da secção</button><button type="button" class="button" data-comparison-add>Adicionar comparação</button></div></div>
                    <input type="hidden" name="question_config_json" value="<?php echo esc_attr($questionConfigJson); ?>" data-comparison-json>
                    <div class="ffb-comparison-status" data-comparison-status></div>
                    <div class="ffb-comparison-rows" data-comparison-rows></div>
                    <template data-comparison-row-template><article class="ffb-comparison-row"><label>Referência A<input type="text" data-comparison-field="pr_label" placeholder="Ex.: Beefeater Dry"></label><label>Referência B<input type="text" data-comparison-field="competitor_label" placeholder="Ex.: Bombay Sapphire"></label><label>Objetivo, %<input type="number" min="0" max="100" step="0.01" data-comparison-field="target" placeholder="50"></label><label class="ffb-comparison-metric">Nome do comparativo<input type="text" data-comparison-field="metric" placeholder="Preenchido automaticamente"></label><button type="button" class="button-link-delete ffb-comparison-remove" data-comparison-remove>Remover</button></article></template>
                    <small class="description">No formulário, o utilizador introduz os valores ou frentes das duas referências. As percentagens e o desvio face ao objetivo são calculados pelo servidor.</small>
                  </div>
                  <label>Ordem da pergunta<input type="number" name="question_order" min="1" max="9999" value="<?php echo (int)($questionForm['priority']??$nextQuestionOrder); ?>"><small>Menor número aparece primeiro no formulário.</small></label>
                  <label class="ffb-wide">Ajuda<input type="text" name="help_text" value="<?php echo esc_attr((string)($questionForm['help_text']??'')); ?>" placeholder="Contexto que o operacional vê junto da pergunta"></label>
                  <label class="ffb-check"><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" <?php checked(!empty($questionForm['is_required'])); ?>><span><strong>Resposta obrigatória</strong><small>Impede a submissão sem resposta.</small></span></label>
                  <label class="ffb-check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" <?php checked(!isset($questionForm['is_active'])||!empty($questionForm['is_active'])); ?>><span><strong>Pergunta ativa</strong><small>Podes desativar sem apagar o histórico.</small></span></label>
                  <div class="ffb-wide ffb-analytics-config">
                    <div class="ffb-head" style="margin-bottom:10px"><div><span class="ffb-kicker">Analytics inteligente</span><h3 style="margin:2px 0">Destino analítico da pergunta</h3></div><label class="ffb-check" style="margin:0"><input type="hidden" name="analytics_enabled" value="0"><input type="checkbox" name="analytics_enabled" value="1" data-analytics-enabled <?php checked(!empty($questionForm['analytics_enabled'])); ?>><span><strong>Incluir em Analytics</strong><small>O mesmo resultado aparece no portal e no FieldFlow App.</small></span></label></div>
                    <div class="ffb-form" data-analytics-fields>
                      <label>Dashboard<select name="analytics_dashboard_id"><option value="0">Automático, Briefing semanal</option><?php foreach($analyticsDashboards as $dashboard): ?><option value="<?php echo (int)$dashboard['id']; ?>" <?php selected((int)($questionForm['analytics_dashboard_id']??0),(int)$dashboard['id']); ?>><?php echo esc_html($dashboard['title'].' #'.(int)$dashboard['id']); ?></option><?php endforeach; ?></select><small>Por defeito reutiliza o dashboard permanente do cliente/projeto.</small></label>
                      <label>Comportamento no Analytics<input type="hidden" name="analytics_behavior_user_intent" value="0" data-analytics-behavior-intent><select name="analytics_behavior" data-analytics-behavior><?php foreach(QuestionAnalyticsService::analyticsBehaviorLabels() as $behaviorValue=>$behaviorLabel): ?><option value="<?php echo esc_attr($behaviorValue); ?>" <?php selected((string)($questionForm['analytics_behavior']??'rollup'),$behaviorValue); ?>><?php echo esc_html($behaviorLabel); ?></option><?php endforeach; ?></select><small>As perguntas pontuais ficam dentro do widget dinâmico. Só as métricas estratégicas precisam de widget próprio.</small><small data-analytics-ownership-status><?php echo $questionAnalyticsUserConfirmed ? 'Escolha fixada manualmente. O otimizador respeita esta decisão.' : 'Gerido automaticamente pelo FieldFlow. Só fica fixo quando alterares explicitamente o comportamento ou a configuração permanente.'; ?></small></label>
                      <div class="ffb-wide ffb-scope-banner" data-analytics-rollup-note><strong>Briefing operacional:</strong> esta pergunta será apresentada dentro do widget dinâmico, sem criar mais um widget permanente. Se tiver evidência, também entra em <strong>Evidências do briefing</strong>.</div>
                      <label data-analytics-permanent-field>Métrica permanente<input type="text" name="analytics_metric_key" list="ffb-evergreen-metrics" value="<?php echo esc_attr((string)($questionForm['analytics_metric_key']??'')); ?>" placeholder="Escolhe ou deixa o FieldFlow sugerir"><small>Usa esta chave para reunir semanas e perguntas equivalentes na mesma métrica estratégica.</small></label>
                      <datalist id="ffb-evergreen-metrics"><?php foreach($evergreenMetricOptions as $metricOption): ?><option value="<?php echo esc_attr((string)$metricOption['key']); ?>"><?php echo esc_html((string)$metricOption['label'].' · '.(int)$metricOption['question_count'].' fonte(s)'); ?></option><?php endforeach; ?></datalist>
                      <label data-analytics-permanent-field>Label do widget<input type="text" name="analytics_metric_label" value="<?php echo esc_attr((string)($questionForm['analytics_metric_label']??$questionForm['question_label']??'')); ?>" placeholder="Usa a pergunta se ficar vazio"></label>
                      <label data-analytics-permanent-field>Tipo de widget<select name="analytics_chart_type" data-analytics-chart><?php foreach(['bar'=>'Barras','line'=>'Linha','pie'=>'Circular','kpi'=>'KPI','table'=>'Tabela','image_gallery'=>'Galeria de evidências'] as $value=>$chartLabel): ?><option value="<?php echo esc_attr($value); ?>" <?php selected((string)$questionFormDefaults['chart_type'],$value); ?>><?php echo esc_html($chartLabel); ?></option><?php endforeach; ?></select></label>
                      <label data-analytics-permanent-field>Agregação<select name="analytics_aggregation"><?php foreach(['count'=>'Contagem','latest'=>'Último valor','sum'=>'Soma','avg'=>'Média','evidence'=>'Evidências'] as $value=>$aggLabel): ?><option value="<?php echo esc_attr($value); ?>" <?php selected((string)$questionFormDefaults['aggregation'],$value); ?>><?php echo esc_html($aggLabel); ?></option><?php endforeach; ?></select></label>
                      <label data-analytics-permanent-field>Dimensão principal<select name="analytics_dimension"><?php foreach(['location_id'=>'Loja','submitted_at'=>'Data de submissão','route_id'=>'Rota','project_id'=>'Campanha','client_id'=>'Cliente'] as $value=>$dimLabel): ?><option value="<?php echo esc_attr($value); ?>" <?php selected((string)$questionFormDefaults['dimension'],$value); ?>><?php echo esc_html($dimLabel); ?></option><?php endforeach; ?></select></label>
                      <label data-analytics-permanent-field>Dimensão secundária<select name="analytics_secondary_dimension"><option value="">Nenhuma</option><?php foreach(['location_id'=>'Loja','submitted_at'=>'Data de submissão','route_id'=>'Rota','project_id'=>'Campanha','client_id'=>'Cliente'] as $value=>$dimLabel): ?><option value="<?php echo esc_attr($value); ?>" <?php selected((string)$questionFormDefaults['secondary_dimension'],$value); ?>><?php echo esc_html($dimLabel); ?></option><?php endforeach; ?></select></label>
                      <label data-analytics-permanent-field>Ordem do widget<input type="number" name="analytics_sort_order" min="1" max="9999" value="<?php echo (int)($questionForm['analytics_sort_order']??$questionForm['priority']??$nextQuestionOrder); ?>"><small>Controla a posição nos analytics.</small></label>
                      <label class="ffb-check" data-analytics-permanent-field><input type="hidden" name="analytics_show_kpi" value="0"><input type="checkbox" name="analytics_show_kpi" value="1" <?php checked(!empty($questionFormDefaults['show_kpi'])); ?>><span><strong>Mostrar KPI</strong></span></label>
                      <label class="ffb-check" data-analytics-permanent-field><input type="hidden" name="analytics_show_table" value="0"><input type="checkbox" name="analytics_show_table" value="1" <?php checked(!empty($questionFormDefaults['show_table'])); ?>><span><strong>Mostrar tabela</strong></span></label>
                      <label class="ffb-check" data-analytics-permanent-field><input type="hidden" name="analytics_show_empty" value="0"><input type="checkbox" name="analytics_show_empty" value="1" <?php checked(!empty($questionFormDefaults['show_empty'])); ?>><span><strong>Mostrar sem dados</strong></span></label>
                    </div>
                  </div>
                  <div class="ffb-wide ffb-question-savebar"><button class="button button-primary"><?php echo $editingQuestion?'Atualizar pergunta':'Criar pergunta'; ?></button><?php if($editingQuestion): ?><a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'routespro-briefings','briefing_id'=>(int)$b['id'],'ffb_tab'=>'questions'],admin_url('admin.php'))); ?>#ffb-question-editor">Cancelar edição</a><?php endif; ?></div>
                </form>
              </div>
              <div class="ffb-card ffb-question-list-card">
                <div class="ffb-head"><div><span class="ffb-kicker">Execução</span><h2>Perguntas ligadas ao briefing</h2></div><span class="ffb-pill"><?php echo (int)($executionStats['answers']??0); ?> respostas</span></div>
                <div class="ffb-kpis" style="margin-bottom:16px"><div class="ffb-kpi"><span>Perguntas</span><strong><?php echo (int)($executionStats['questions']??0); ?></strong><small>Configurações ativas</small></div><div class="ffb-kpi"><span>Submissões</span><strong><?php echo (int)($executionStats['submissions']??0); ?></strong><small>Últimas respostas por loja</small></div><div class="ffb-kpi"><span>Implementadas</span><strong><?php echo (int)($executionStats['conforming']??0); ?></strong><small>Respostas positivas atuais</small></div><div class="ffb-kpi"><span>Não implementadas</span><strong><?php echo (int)($executionStats['non_conforming']??0); ?></strong><small>Requerem atenção</small></div></div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ffb-question-bulk-form" data-question-bulk-form>
                  <input type="hidden" name="action" value="fieldflow_briefing_questions_bulk_delete">
                  <input type="hidden" name="briefing_id" value="<?php echo (int)$b['id']; ?>">
                  <input type="hidden" name="ffb_qpage" value="<?php echo (int)$questionPage; ?>">
                  <input type="hidden" name="ffb_qper_page" value="<?php echo (int)$questionPerPage; ?>">
                  <?php wp_nonce_field('fieldflow_briefing_questions_bulk_'.$b['id']); ?>
                  <div class="ffb-question-toolbar">
                    <div class="ffb-question-bulk-actions">
                      <select name="bulk_action" aria-label="Ação em massa"><option value="">Ações em massa</option><option value="save_order">Guardar ordem</option><option value="save_steps">Guardar steps desta página</option><option value="activate">Ativar selecionadas</option><option value="deactivate">Desativar selecionadas</option><option value="delete">Apagar selecionadas</option></select>
                      <button type="submit" class="button" name="apply_bulk" value="1">Aplicar</button>
                      <span class="description" data-selected-count>0 selecionadas</span>
                    </div>
                    <label class="ffb-question-per-page">Mostrar
                      <select data-question-per-page aria-label="Perguntas por página"><?php foreach($questionPerPageOptions as $option): ?><option value="<?php echo (int)$option; ?>" <?php selected($questionPerPage,$option); ?>><?php echo (int)$option; ?></option><?php endforeach; ?></select>
                      por página
                    </label>
                  </div>
                  <?php if($briefingSteps): ?><p class="description ffb-step-hint">Atribuição rápida: escolhe o step em cada pergunta e usa <strong>Ações em massa, Guardar steps desta página</strong>. Esta ação modifica apenas a categoria visual, sem atualizar lojas, respostas ou analytics.</p><?php endif; ?>
                  <div class="ffb-table-wrap ffb-question-table-wrap"><table class="widefat striped ffb-question-table"><thead><tr><th class="check-column"><input type="checkbox" data-select-all-questions aria-label="Selecionar todas as perguntas desta página"></th><th>Pergunta</th><th>Estado</th><th>Widget</th><th>Formulário</th><th>Lojas</th><th>Ordem</th><th>Validade</th><th>Ações</th></tr></thead><tbody>
                    <?php if(!$pagedExecutionQuestions): ?><tr><td colspan="9">Ainda não existem perguntas ligadas a este briefing.</td></tr><?php endif; ?>
                    <?php foreach($pagedExecutionQuestions as $q): ?><tr>
                      <th class="check-column"><input type="checkbox" name="question_ids[]" value="<?php echo (int)$q['id']; ?>" data-question-checkbox aria-label="Selecionar <?php echo esc_attr($q['question_label']); ?>"></th>
                      <td><strong><?php echo esc_html($q['question_label']); ?></strong><br><small><?php $displayQuestionType=sanitize_key((string)$q['question_type']); echo esc_html($displayQuestionType==='evidence_check' ? 'Conformidade + evidência' : (string)$q['question_type']); ?><?php echo !empty($q['is_required'])?' · obrigatória':''; ?></small><?php $stepLabel=(string)($briefingStepsByKey[(string)($q['briefing_step_key']??'')]['title']??''); if($stepLabel!==''): ?><br><small class="ffb-question-step-label">Step: <?php echo esc_html($stepLabel); ?></small><?php endif; ?><?php if($briefingSteps): ?><select class="ffb-inline-step-select" name="question_step_keys[<?php echo (int)$q['id']; ?>]" aria-label="Step da pergunta <?php echo esc_attr($q['question_label']); ?>"><option value="">Sem step específico</option><?php foreach($briefingSteps as $step): ?><option value="<?php echo esc_attr($step['key']); ?>" <?php selected((string)($q['briefing_step_key']??''),$step['key']); ?>><?php echo esc_html($step['title']); ?></option><?php endforeach; ?></select><?php endif; ?></td>
                      <td><?php $executionState=(array)($q['execution_state']??[]); $executionStateKey=sanitize_key((string)($executionState['state']??'pending')); $executionStateLabels=['implemented'=>'Implementado','attention'=>'Não implementado','not_implemented'=>'Não implementado','partial'=>'Parcial','pending'=>'Pendente','not_applicable'=>'Não aplicável','answered'=>'Respondido']; ?><span class="ffb-status <?php echo !empty($q['is_active'])?'is-active':'is-inactive'; ?>"><?php echo !empty($q['is_active'])?'Ativa':'Inativa'; ?></span><?php if(!empty($q['is_active'])): ?><br><small><strong><?php echo esc_html($executionStateLabels[$executionStateKey]??'Pendente'); ?></strong> · <?php echo (int)($executionState['answered']??0); ?>/<?php echo (int)($executionState['assignments']??($q['store_count']??0)); ?> loja(s)</small><?php endif; ?></td>
                      <td><?php if(!empty($q['analytics_enabled'])): $analyticsBehavior=(string)($q['analytics_behavior']??'rollup'); if($analyticsBehavior==='rollup'): ?><strong>Briefing operacional</strong><br><small>Widget dinâmico · sem widget individual</small><?php if(in_array(sanitize_key((string)$q['question_type']),['evidence_check','image_upload','categorized_image_upload','file_upload'],true)): ?><br><small class="ffb-evergreen-key">Também em Evidências do briefing</small><?php endif; ?><?php else: ?><strong><?php echo esc_html((string)($q['analytics_metric_label']??$q['question_label'])); ?></strong><br><small>Métrica permanente · ordem <?php echo (int)($q['analytics_sort_order']??$q['priority']??10); ?></small><?php if(!empty($q['analytics_metric_key'])): ?><br><small class="ffb-evergreen-key"><code><?php echo esc_html((string)$q['analytics_metric_key']); ?></code></small><?php endif; ?><?php endif; ?><?php else: ?><span class="description">Sem Analytics</span><?php endif; ?></td>
                      <td><?php echo esc_html($q['form_title']?:'Qualquer formulário com bloco'); ?></td>
                      <td><?php $locationStates=(array)($executionState['location_states']??[]); $answeredStores=(int)($executionState['answered']??0); $assignedStores=(int)($executionState['assignments']??($q['store_count']??0)); $pendingStores=max(0,$assignedStores-$answeredStores); ?><strong><?php echo $assignedStores; ?> loja(s) alvo</strong><br><small><?php echo $answeredStores; ?> respondida(s) · <?php echo $pendingStores; ?> pendente(s)</small><br><small class="description">Âmbito: <?php echo esc_html((string)($q['target_scope_label']??BriefingService::questionScopeLabel((string)($q['target_scope']??'section')))); ?></small><?php if($locationStates): ?><details class="ffb-store-coverage" data-store-coverage><summary>Ver lojas e estado</summary><div class="ffb-store-coverage-panel"><input type="search" placeholder="Pesquisar loja, resposta ou estado" data-store-coverage-search><div class="ffb-store-coverage-list"><?php foreach($locationStates as $locationState): $locationStateKey=sanitize_key((string)($locationState['state']??'pending')); $locationStateLabel=$executionStateLabels[$locationStateKey]??'Pendente'; $locationName=(string)($locationState['location_name']??('Loja #'.(int)($locationState['location_id']??0))); $locationAnswer=trim((string)($locationState['answer']??'')); ?><div class="ffb-store-coverage-row" data-store-row data-search-text="<?php echo esc_attr(strtolower(remove_accents($locationName.' '.$locationStateLabel.' '.$locationAnswer))); ?>"><div><strong><?php echo esc_html($locationName); ?></strong><?php if(!empty($locationState['submitted_at'])): ?><small><?php echo esc_html(self::dateTimeLabel((string)$locationState['submitted_at'])); ?></small><?php endif; ?></div><span class="ffb-store-state is-<?php echo esc_attr($locationStateKey); ?>"><?php echo esc_html($locationStateLabel); ?></span><?php if($locationAnswer!==''): ?><p><?php echo esc_html($locationAnswer); ?></p><?php endif; ?></div><?php endforeach; ?></div><small class="ffb-store-coverage-empty" data-store-coverage-empty hidden>Nenhuma loja corresponde à pesquisa.</small></div></details><?php elseif($assignedStores): ?><small class="description" style="display:block;margin-top:5px">Ainda sem execução. As <?php echo $assignedStores; ?> lojas estão atribuídas à pergunta.</small><?php endif; ?></td>
                      <td class="ffb-question-order"><input type="number" min="1" max="9999" name="question_priorities[<?php echo (int)$q['id']; ?>]" value="<?php echo (int)($q['priority']??100); ?>"></td>
                      <td class="ffb-question-validity"><strong><?php echo esc_html(self::dateLabel($q['valid_from'])); ?></strong><small>até <?php echo esc_html(self::dateLabel($q['valid_to'])); ?></small></td>
                      <td class="ffb-question-actions-cell"><div class="ffb-question-row-actions"><a class="button button-small" href="<?php echo esc_url(add_query_arg(['page'=>'routespro-briefings','briefing_id'=>(int)$b['id'],'ffb_tab'=>'questions','edit_question'=>(int)$q['id']],admin_url('admin.php'))); ?>#ffb-question-editor">Editar</a><button type="submit" class="button button-small button-link-delete" name="single_question_id" value="<?php echo (int)$q['id']; ?>" data-delete-single-question>Remover</button></div></td>
                    </tr><?php endforeach; ?>
                  </tbody></table></div>
                  <div class="ffb-question-footer">
                    <span class="description"><?php echo (int)$questionTotal; ?> pergunta(s) lógica(s) · página <?php echo (int)$questionPage; ?> de <?php echo (int)$questionTotalPages; ?></span>
                    <?php if($questionTotalPages>1): ?><nav class="ffb-question-pagination" aria-label="Paginação das perguntas">
                      <?php if($questionPage>1): ?><a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'routespro-briefings','briefing_id'=>(int)$b['id'],'ffb_tab'=>'questions','ffb_qpage'=>$questionPage-1,'ffb_qper_page'=>$questionPerPage],admin_url('admin.php'))); ?>">‹ Anterior</a><?php endif; ?>
                      <?php $pageStart=max(1,$questionPage-2); $pageEnd=min($questionTotalPages,$questionPage+2); for($pageNo=$pageStart;$pageNo<=$pageEnd;$pageNo++): ?><a class="button <?php echo $pageNo===$questionPage?'button-primary':''; ?>" href="<?php echo esc_url(add_query_arg(['page'=>'routespro-briefings','briefing_id'=>(int)$b['id'],'ffb_tab'=>'questions','ffb_qpage'=>$pageNo,'ffb_qper_page'=>$questionPerPage],admin_url('admin.php'))); ?>"><?php echo (int)$pageNo; ?></a><?php endfor; ?>
                      <?php if($questionPage<$questionTotalPages): ?><a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'routespro-briefings','briefing_id'=>(int)$b['id'],'ffb_tab'=>'questions','ffb_qpage'=>$questionPage+1,'ffb_qper_page'=>$questionPerPage],admin_url('admin.php'))); ?>">Seguinte ›</a><?php endif; ?>
                    </nav><?php endif; ?>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <div class="ffb-tab-panel" data-ffb-panel="sources">
            <div class="ffb-card"><div class="ffb-head"><div><span class="ffb-kicker">Cobertura do ficheiro</span><h2>Folhas reconhecidas e integradas</h2><p>Este painel confirma o que foi efetivamente lido do Excel e onde aparece no FieldFlow.</p></div><span class="ffb-pill"><?php echo count($sourceSheets); ?> folhas</span></div>
              <?php if($importIntelligence): ?><div class="ffb-dictionary-note"><strong>Excel Intelligence ativo.</strong> <?php echo (int)($importIntelligence['workbook_sheets']??count($sourceSheets)); ?> folha(s) analisada(s), cabeçalhos fundidos e multilinha suportados, <?php echo (int)($summary['store_aliases_mapped']??0); ?> loja(s) associada(s) e <?php echo (int)($summary['store_aliases_unmapped']??0); ?> caso(s) ainda para validação manual. O motor automático só aplica correspondências de confiança muito alta.</div><?php endif; ?>
              <?php if($cardexRead['retailers']): ?><div class="ffb-dictionary-note"><strong>Cardex detetados no ficheiro.</strong> <?php echo (int)$cardexRead['retailers']; ?> insígnia(s), <?php echo (int)$cardexRead['items']; ?> referência(s) e <?php echo (int)$cardexRead['anomalies']; ?> classificação(ões) fora do dicionário conhecido.<?php if($b['status']!=='published'): ?> O Cardex vivo permanece intacto até publicares o briefing.<?php endif; ?></div><?php endif; ?>
              <?php if($cardexRead['anomaly_details']): ?><details class="ffb-dictionary-note"><summary><strong>Ver classificações Cardex a rever</strong></summary><div style="margin-top:8px"><?php foreach($cardexRead['anomaly_details'] as $anomaly): $rk=(string)($anomaly['retailer_key']??''); ?><div style="padding:6px 0;border-top:1px solid #e2e8f0"><strong><?php echo esc_html($labels[$rk]??$rk); ?></strong> · <code><?php echo esc_html((string)($anomaly['code']??'')); ?></code> · <?php echo esc_html((string)($anomaly['product']??'')); ?> · tipo <strong><?php echo esc_html((string)($anomaly['type']??'')); ?></strong></div><?php endforeach; ?></div></details><?php endif; ?>
              <?php if($cardexSync): $ct=(array)($cardexSync['totals']??[]); ?><div class="ffb-dictionary-note"><strong>Cardex semanal sincronizado.</strong> <?php echo (int)($ct['retailers']??0); ?> insígnia(s), <?php echo (int)($ct['items']??0); ?> referência(s), <?php echo (int)($ct['created']??0); ?> nova(s), <?php echo (int)($ct['updated']??0); ?> atualizada(s), <?php echo (int)($ct['deactivated']??0); ?> desativada(s) e <?php echo (int)($ct['anomalies']??0); ?> classificação(ões) a rever. Ajustes específicos por loja permanecem intactos.</div><?php endif; ?>
              <?php if(!$sourceSheets): ?><div class="ffb-dictionary-note"><strong>Briefing importado numa versão anterior.</strong> Volta a carregar o mesmo Excel no formulário abaixo para criar uma nova versão com o manifesto completo, Share Space corrigido e separação dos espaços centralizados.</div><?php else: ?>
              <div class="ffb-source-grid"><?php foreach($sourceSheets as $sheet): $retailerKey=(string)($sheet['retailer_key']??''); ?><article><span><?php echo esc_html($sheet['type']??'folha'); ?></span><strong><?php echo esc_html($sheet['sheet']??''); ?></strong><small><?php echo esc_html($sheet['label']??'Conteúdo integrado'); ?><?php if($retailerKey): ?> · <?php echo esc_html($labels[$retailerKey]??$retailerKey); ?><?php endif; ?></small><b><?php echo (int)($sheet['records']??0); ?> registos</b></article><?php endforeach; ?></div><?php endif; ?>
              <?php if($b['status']==='published'): ?>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:14px 0" onsubmit="return confirm('Reaplicar os Cardex desta versão? Os ajustes específicos por loja serão preservados.');"><input type="hidden" name="action" value="fieldflow_briefing_cardex_sync"><input type="hidden" name="briefing_id" value="<?php echo (int)$briefingId; ?>"><?php wp_nonce_field('fieldflow_briefing_cardex_sync_'.$briefingId); ?><button class="button">Reaplicar Cardex desta versão</button> <span class="description">Permite recuperar uma versão semanal anterior sem apagar ajustes específicos das lojas.</span></form>
              <?php else: ?>
              <div class="ffb-dictionary-note"><strong>Cardex protegido até à publicação.</strong> As folhas Cardex já foram lidas e podem ser revistas. O Cardex operacional só é sincronizado quando carregares em “Publicar para a equipa”, evitando alterar a operação com um briefing ainda em revisão.</div>
              <?php endif; ?>
              <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ffb-reimport-form"><input type="hidden" name="action" value="fieldflow_briefing_import"><input type="hidden" name="client_id" value="<?php echo (int)$b['client_id']; ?>"><input type="hidden" name="project_id" value="<?php echo (int)$b['project_id']; ?>"><input type="hidden" name="title" value="<?php echo esc_attr($b['title']); ?>"><input type="hidden" name="week_no" value="<?php echo (int)$b['week_no']; ?>"><input type="hidden" name="year_no" value="<?php echo (int)$b['year_no']; ?>"><?php wp_nonce_field('fieldflow_briefing_import'); ?><label><strong>Importar novamente como nova versão</strong><input type="file" name="briefing_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label><?php if($aiReady): ?><label class="ffb-check"><input type="checkbox" name="ai_analyze" value="1" checked><span><strong>Analisar com IA</strong><small>Atualiza também a auditoria e as sugestões.</small></span></label><?php endif; ?><button class="button button-primary">Criar nova versão e rever</button></form>
            </div>
          </div>

          <div class="ffb-tab-panel" data-ffb-panel="evidence">
            <div class="ffb-card"><div class="ffb-head"><div><span class="ffb-kicker">Evidência</span><h2>Distribuição e confirmações</h2></div><span class="ffb-pill"><?php echo count($detail['recipients']); ?> destinatários</span></div><div class="ffb-table-wrap"><table class="widefat striped"><thead><tr><th>Utilizador</th><th>Primeira visualização</th><th>Última visualização</th><th>Confirmação</th><th>Lojas</th></tr></thead><tbody><?php if(!$detail['recipients']): ?><tr><td colspan="5">A lista de destinatários é criada quando o briefing é publicado.</td></tr><?php endif; ?><?php foreach($detail['recipients'] as $recipient): $locs=json_decode((string)$recipient['location_ids_json'],true)?:[]; ?><tr><td><strong><?php echo esc_html($recipient['display_name']?:'Utilizador #'.$recipient['user_id']); ?></strong><br><small><?php echo esc_html($recipient['user_email']); ?></small></td><td><?php echo esc_html(self::dateTimeLabel($recipient['first_viewed_at'])); ?></td><td><?php echo esc_html(self::dateTimeLabel($recipient['last_viewed_at'])); ?></td><td><?php echo $recipient['acknowledged_at']?'<span class="ffb-status published">Confirmado</span>':'<span class="ffb-status draft">Pendente</span>'; ?><br><small><?php echo esc_html(self::dateTimeLabel($recipient['acknowledged_at'])); ?></small></td><td><?php echo count($locs); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
          </div>
        </section>
        <?php
    }

    public static function handleImport(): void {
        self::guard();
        check_admin_referer('fieldflow_briefing_import');
        try {
            $file = $_FILES['briefing_file'] ?? [];
            if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) throw new \RuntimeException('Seleciona um ficheiro XLSX válido.');
            if (!empty($file['error'])) throw new \RuntimeException('O upload do ficheiro falhou.');
            if ((int)($file['size'] ?? 0) > 30 * 1024 * 1024) throw new \RuntimeException('O ficheiro excede o limite de 30 MB.');
            $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
            if ($ext !== 'xlsx') throw new \RuntimeException('Este importador aceita ficheiros XLSX.');
            $parsed = PernodWeeklyImporter::parse($file['tmp_name']);
            $id = BriefingService::importParsed($parsed, [
                'client_id' => absint($_POST['client_id'] ?? 0),
                'project_id' => absint($_POST['project_id'] ?? 0),
                'title' => sanitize_text_field((string)($_POST['title'] ?? '')),
                'week_no' => absint($_POST['week_no'] ?? 0),
                'year_no' => absint($_POST['year_no'] ?? 0),
                'valid_from' => sanitize_text_field((string)($_POST['valid_from'] ?? '')),
                'valid_to' => sanitize_text_field((string)($_POST['valid_to'] ?? '')),
            ], [
                'name' => (string)($file['name'] ?? 'briefing.xlsx'),
                'hash' => hash_file('sha256', $file['tmp_name']),
            ]);
            $autoMapped = BriefingService::applySafeMappings($id);
            // 9D2: carry forward only verified product-specific execution mapping.
            // Changed or unverified products remain in the briefing review queue.
            CategoryMapping::inheritVerifiedQuestions($id);
            // The weekly Cardex is materialised at the publish boundary, not while
            // the workbook is still under review. This keeps the live operation
            // unchanged until an admin explicitly publishes the briefing.
            $notice = $autoMapped > 0 ? 'imported_mapped' : 'imported';
            $autoAnalyze = !empty($_POST['ai_analyze']);
            if ($autoAnalyze && BriefingAIService::available()) {
                $queued = BriefingAIService::queue($id);
                $notice = $queued
                    ? ($autoMapped > 0 ? 'imported_mapped_ai_queued' : 'imported_ai_queued')
                    : 'imported_ai_warning';
            }
            self::redirect($id, $notice, (string)$autoMapped);
        } catch (\Throwable $e) {
            self::redirect(0, 'error', $e->getMessage());
        }
    }

    public static function handleMapping(): void {
        self::guard();
        $id = absint($_POST['briefing_id'] ?? 0);
        check_admin_referer('fieldflow_briefing_map_'.$id);
        try { BriefingService::saveMappings($id, (array)($_POST['mapping'] ?? [])); BriefingAIService::markStale($id, 'mapping_changed'); self::redirect($id, 'mapped'); }
        catch (\Throwable $e) { self::redirect($id, 'error', $e->getMessage()); }
    }

    public static function handlePublish(): void {
        self::guard(); $id=absint($_POST['briefing_id']??0); check_admin_referer('fieldflow_briefing_publish_'.$id);
        try {
            $analysis = BriefingAIService::analysis($id);
            $cardexSync = \RoutesPro\Briefings\CardexSyncService::syncBriefing($id);
            if (empty($cardexSync['ok'])) throw new \RuntimeException('O briefing foi lido, mas o Cardex não ficou sincronizado. A publicação foi interrompida para proteger a operação.');
            BriefingService::publish($id);
            if (BriefingAIService::available() && (!$analysis || !empty($analysis['stale']))) BriefingAIService::queue($id);
            self::redirect($id,'published');
        } catch(\Throwable $e){ self::redirect($id,'error',$e->getMessage()); }
    }

    public static function handleArchive(): void {
        self::guard(); $id=absint($_POST['briefing_id']??0); check_admin_referer('fieldflow_briefing_archive_'.$id);
        BriefingService::archive($id); self::redirect($id,'archived');
    }

    public static function handleDelete(): void {
        self::guard(); $id=absint($_POST['briefing_id']??0); check_admin_referer('fieldflow_briefing_delete_'.$id);
        try { BriefingService::deleteBriefing($id); self::redirect(0,'deleted'); } catch(\Throwable $e){ self::redirect($id,'error',$e->getMessage()); }
    }

    public static function handleAIAnalyze(): void {
        self::guard();
        $id = absint($_POST['briefing_id'] ?? 0);
        check_admin_referer('fieldflow_briefing_ai_analyze_'.$id);
        try { if (!BriefingAIService::queue($id)) throw new \RuntimeException('Não foi possível colocar a análise em fila.'); self::redirect($id, 'ai_queued'); }
        catch (\Throwable $e) { self::redirect($id, 'error', $e->getMessage()); }
    }

    public static function handleAIApplyMappings(): void {
        self::guard();
        $id = absint($_POST['briefing_id'] ?? 0);
        check_admin_referer('fieldflow_briefing_ai_apply_mappings_'.$id);
        try {
            $count = BriefingAIService::applyHighConfidenceMappings($id, 85);
            if (BriefingAIService::available()) BriefingAIService::queue($id);
            self::redirect($id, 'ai_mapped', (string)$count);
        } catch (\Throwable $e) { self::redirect($id, 'error', $e->getMessage()); }
    }

    public static function handleCardexSync(): void {
        self::guard();
        $id = absint($_POST['briefing_id'] ?? 0);
        check_admin_referer('fieldflow_briefing_cardex_sync_'.$id);
        try {
            $result = \RoutesPro\Briefings\CardexSyncService::syncBriefing($id);
            $count = (int)($result['totals']['items'] ?? 0);
            self::redirect($id, 'cardex_synced', (string)$count);
        } catch (\Throwable $e) {
            self::redirect($id, 'error', $e->getMessage());
        }
    }

    public static function handleSafeMappings(): void {
        self::guard();
        $id = absint($_POST['briefing_id'] ?? 0);
        check_admin_referer('fieldflow_briefing_safe_mappings_'.$id);
        try {
            $count = BriefingService::applySafeMappings($id);
            if ($count > 0) BriefingAIService::markStale($id, 'mapping_changed');
            self::redirect($id, 'safe_mapped', (string)$count);
        } catch (\Throwable $e) {
            self::redirect($id, 'error', $e->getMessage());
        }
    }

    public static function handleShareSpaceRepair(): void {
        self::guard();
        global $wpdb;
        $briefingId = absint($_POST['briefing_id'] ?? 0);
        check_admin_referer('fieldflow_briefing_share_space_repair_'.$briefingId);
        try {
            $suggestions = BriefingAIService::executionQuestionSuggestions($briefingId);
            $created = 0;
            $assignments = 0;
            foreach ($suggestions as $suggestion) {
                if (!is_array($suggestion) || sanitize_key((string)($suggestion['question_type'] ?? '')) !== 'share_space_matrix') continue;
                $payload = [
                    'suggestion_id' => self::questionSuggestionId($suggestion),
                    'question_label' => (string)($suggestion['label'] ?? 'Frentes e Share Space face à concorrência'),
                    'question_type' => 'share_space_matrix',
                    'question_config' => (array)($suggestion['question_config'] ?? []),
                    'briefing_section_id' => absint($suggestion['briefing_section_id'] ?? 0),
                    'help_text' => (string)($suggestion['help_text'] ?? ''),
                    'target_scope' => 'section',
                    'question_order' => 40,
                    'is_required' => !empty($suggestion['required']) ? 1 : 0,
                    'is_active' => 1,
                    'analytics_enabled' => 1,
                    'analytics_behavior' => 'existing',
                    'analytics_chart_type' => 'table',
                    'analytics_aggregation' => 'latest',
                    'analytics_dimension' => 'location_id',
                    'analytics_show_kpi' => 1,
                    'analytics_show_table' => 1,
                    'analytics_show_empty' => 1,
                ];
                $result = self::saveQuestionPayload($briefingId, $payload);
                if ((int)($result['saved'] ?? 0) > 0) {
                    $created++;
                    $assignments += (int)$result['saved'];
                }
            }
            if ($created < 1) throw new \RuntimeException('Não foi encontrada uma secção Share Space válida para converter.');

            $sectionIds = array_map('intval', $wpdb->get_col($wpdb->prepare(
                'SELECT id FROM ' . BriefingService::table('briefing_sections') . ' WHERE briefing_id=%d AND section_type=%s',
                $briefingId, 'share_space'
            )) ?: []);
            $deactivated = 0;
            if ($sectionIds) {
                $placeholders = implode(',', array_fill(0, count($sectionIds), '%d'));
                $table = ContextQuestionService::table();
                $keys = array_values(array_unique(array_filter(array_map('sanitize_key', $wpdb->get_col($wpdb->prepare(
                    'SELECT DISTINCT question_key FROM ' . $table . ' WHERE briefing_id=%d AND briefing_section_id IN (' . $placeholders . ") AND question_type IN ('percent','number')",
                    ...array_merge([$briefingId], $sectionIds)
                )) ?: []))));
                foreach ($keys as $key) {
                    $deactivated += (int)$wpdb->query($wpdb->prepare(
                        'UPDATE ' . $table . ' SET is_active=0,updated_at=%s WHERE briefing_id=%d AND question_key=%s',
                        current_time('mysql'), $briefingId, $key
                    ));
                    QuestionAnalyticsService::deactivateForQuestion($key);
                }
            }
            self::redirectQuestionApproval($briefingId, 'share_space_repaired', $created, $assignments, 0, (string)$deactivated);
        } catch (\Throwable $e) {
            self::redirectQuestionApproval($briefingId, 'error', 0, 0, 1, $e->getMessage());
        }
    }

    /** 9DX: write only step layout options, never the question or answer tables. */
    public static function handleStepSave(): void {
        self::guard();
        $briefingId = absint($_POST['briefing_id'] ?? 0);
        check_admin_referer('fieldflow_briefing_step_save_'.$briefingId);
        if (!BriefingService::get($briefingId)) self::redirectQuestionApproval($briefingId, 'error', 0, 0, 1, 'Briefing não encontrado.');
        $key = sanitize_key((string)($_POST['step_key'] ?? ''));
        $result = BriefingSteps::save(
            $briefingId,
            $key,
            (string)wp_unslash($_POST['step_title'] ?? ''),
            (string)wp_unslash($_POST['step_description'] ?? ''),
            absint($_POST['step_order'] ?? 100)
        );
        if (!empty($result['error'])) self::redirectQuestionApproval($briefingId, 'error', 0, 0, 1, (string)$result['error']);
        self::redirectQuestionApproval($briefingId, $key === '' ? 'step_saved' : 'step_updated');
    }

    public static function handleQuestionSave(): void {
        self::guard();
        $briefingId = absint($_POST['briefing_id'] ?? 0);
        check_admin_referer('fieldflow_briefing_question_save_'.$briefingId);
        $payload = is_array($_POST) ? wp_unslash($_POST) : [];
        $result = self::saveQuestionPayload($briefingId, $payload);
        if ((int)($result['saved'] ?? 0) < 1) {
            self::redirectQuestionApproval($briefingId, 'error', 0, 0, 1, (string)($result['error'] ?? 'Não foi possível guardar a pergunta.'));
        }
        $notice = !empty($payload['question_id']) ? 'question_updated' : 'question_saved';
        $message = trim((string)($result['analytics_message'] ?? ''));
        self::redirectQuestionApproval($briefingId, $notice, 1, (int)$result['saved'], 0, $message);
    }

    public static function handleQuestionsBulkApprove(): void {
        self::guard();
        $briefingId = absint($_POST['briefing_id'] ?? 0);
        check_admin_referer('fieldflow_briefing_questions_bulk_approve_'.$briefingId);
        $suggestions = is_array($_POST['suggestions'] ?? null) ? wp_unslash($_POST['suggestions']) : [];
        $singleId = sanitize_key((string)($_POST['single_suggestion_id'] ?? ''));
        $selectedIds = $singleId !== '' ? [$singleId] : array_values(array_unique(array_filter(array_map('sanitize_key', (array)($_POST['suggestion_ids'] ?? [])))));
        $compactRaw = isset($_POST['selected_suggestions_json']) ? wp_unslash((string)$_POST['selected_suggestions_json']) : '';
        if ($compactRaw !== '') {
            $compact = json_decode($compactRaw, true);
            if (is_array($compact)) {
                $suggestions = [];
                $selectedIds = [];
                foreach ($compact as $item) {
                    if (!is_array($item)) continue;
                    $itemId = sanitize_key((string)($item['suggestion_id'] ?? ''));
                    if ($itemId === '') continue;
                    $suggestions[$itemId] = $item;
                    $selectedIds[] = $itemId;
                }
                $selectedIds = array_values(array_unique($selectedIds));
            }
        }
        if (!$selectedIds) {
            self::redirectQuestionApproval($briefingId, 'questions_none_selected_approve', 0, 0, 0, '');
        }
        $approved = 0;
        $assignments = 0;
        $failed = 0;
        $errors = [];
        foreach ($selectedIds as $suggestionId) {
            if (empty($suggestions[$suggestionId]) || !is_array($suggestions[$suggestionId])) {
                $failed++;
                $errors[] = 'Sugestão inválida ou já desatualizada.';
                continue;
            }
            $payload = (array)$suggestions[$suggestionId];
            $payload['suggestion_id'] = $suggestionId;
            $result = self::saveQuestionPayload($briefingId, $payload);
            if ((int)($result['saved'] ?? 0) > 0) {
                $approved++;
                $assignments += (int)$result['saved'];
                if (!empty($result['error'])) $errors[] = (string)$result['error'];
            } else {
                $failed++;
                $errors[] = (string)($result['error'] ?? 'Não foi possível guardar a sugestão.');
            }
        }
        $notice = $approved > 0 && $failed === 0 ? 'questions_approved' : ($approved > 0 ? 'questions_partial' : 'error');
        self::redirectQuestionApproval($briefingId, $notice, $approved, $assignments, $failed, implode(' ', array_values(array_unique(array_filter($errors)))));
    }

    private static function saveQuestionPayload(int $briefingId, array $payload): array {
        global $wpdb;
        $b = BriefingService::get($briefingId);
        if (!$b) return ['saved'=>0,'error'=>'Briefing não encontrado.'];
        \RoutesPro\Admin\ContextQuestions::ensure_table();
        $label = sanitize_text_field((string)($payload['question_label'] ?? ''));
        if ($label === '') return ['saved'=>0,'error'=>'Indica a pergunta.'];

        $questionId = absint($payload['question_id'] ?? 0);
        $originalKey = sanitize_key((string)($payload['original_question_key'] ?? ''));
        if ($questionId && $originalKey === '') {
            $originalKey = sanitize_key((string)$wpdb->get_var($wpdb->prepare(
                'SELECT question_key FROM '.ContextQuestionService::table().' WHERE id=%d AND briefing_id=%d LIMIT 1',
                $questionId, $briefingId
            )));
        }

        $scope = sanitize_key((string)($payload['target_scope'] ?? 'section')) ?: 'section';
        if (!array_key_exists($scope, BriefingService::questionScopeLabels())) $scope = 'section';
        $itemTable = BriefingService::table('briefing_store_items');
        $aliasTable = BriefingService::table('briefing_store_aliases');
        $mappedIds = array_values(array_unique(array_filter(array_map('absint', $wpdb->get_col($wpdb->prepare(
            "SELECT location_id FROM {$itemTable} WHERE briefing_id=%d AND location_id>0 UNION SELECT location_id FROM {$aliasTable} WHERE location_id>0 AND id IN (SELECT alias_id FROM {$itemTable} WHERE briefing_id=%d AND alias_id>0)",
            $briefingId, $briefingId
        )) ?: []))));
        $eligibleLocations = BriefingService::locationsForQuestionScope((int)$b['client_id'], (int)$b['project_id']);
        $eligibleById = [];
        foreach ($eligibleLocations as $location) $eligibleById[(int)$location['id']] = $location;
        $sectionId = absint($payload['briefing_section_id'] ?? 0);
        $sectionRetailer = 'all';
        if ($sectionId) {
            $sectionRetailer = sanitize_key((string)$wpdb->get_var($wpdb->prepare(
                'SELECT retailer_key FROM ' . BriefingService::table('briefing_sections') . ' WHERE id=%d AND briefing_id=%d LIMIT 1',
                $sectionId, $briefingId
            ))) ?: 'all';
        }
        $sectionIds = [];
        foreach ($eligibleById as $locationId => $location) {
            $retailerKey = sanitize_key((string)($location['retailer_key'] ?? ''));
            if ($sectionRetailer === 'all' || $retailerKey === $sectionRetailer) $sectionIds[] = (int)$locationId;
        }
        $eligibleIds = array_values(array_map('intval', array_keys($eligibleById)));
        $mappedEligibleIds = array_values(array_intersect($eligibleIds, $mappedIds));
        $mappedSectionIds = array_values(array_intersect($sectionIds, $mappedEligibleIds));
        $selectedIds = array_values(array_unique(array_filter(array_map('absint', (array)($payload['location_ids'] ?? [])))));

        switch ($scope) {
            case 'mapped':
                $locationIds = $mappedEligibleIds;
                break;
            case 'selected':
                $locationIds = array_values(array_intersect($mappedSectionIds, $selectedIds));
                break;
            case 'retailer_active':
                $locationIds = $sectionIds;
                break;
            case 'project_active':
                $locationIds = $eligibleIds;
                break;
            case 'selected_active':
                $locationIds = array_values(array_intersect($eligibleIds, $selectedIds));
                break;
            case 'section':
            default:
                $locationIds = $mappedSectionIds;
                break;
        }
        $locationIds = array_values(array_unique(array_map('absint', $locationIds)));

        if (!$locationIds) {
            if ($scope === 'selected') return ['saved'=>0,'error'=>'Seleciona pelo menos uma loja mapeada e ativa desta insígnia/secção para a pergunta.'];
            if ($scope === 'selected_active') return ['saved'=>0,'error'=>'Seleciona pelo menos uma loja ativa do projeto/campanha. Pode ser uma loja sem mapping neste briefing.'];
            if ($scope === 'project_active') return ['saved'=>0,'error'=>'Não existem lojas ativas no projeto/campanha para receber esta pergunta.'];
            if ($scope === 'retailer_active') {
                $scopeText = $sectionRetailer !== 'all' ? ' da insígnia '.($labels[$sectionRetailer] ?? $sectionRetailer) : '';
                return ['saved'=>0,'error'=>'Não existem lojas ativas'.$scopeText.' no projeto/campanha.'];
            }
            $scopeText = $sectionRetailer !== 'all' ? ' para a insígnia desta secção' : '';
            return ['saved'=>0,'error'=>'Não existem lojas mapeadas e elegíveis na campanha'.$scopeText.'. Confirma o mapeamento do briefing e a secção escolhida.'];
        }

        $type = sanitize_key((string)($payload['question_type'] ?? 'radio')) ?: 'radio';
        if (!in_array($type, ['radio','select','text','textarea','number','currency','percent','share_space_matrix','evidence_check','date','time','checkbox','image_upload','categorized_image_upload','file_upload'], true)) $type = 'radio';
        $options = preg_split('/\\r\\n|\\r|\\n/', (string)($payload['options'] ?? '')) ?: [];
        $options = array_values(array_filter(array_map('sanitize_text_field', $options)));
        if (in_array($type, ['select','radio'], true) && !$options) $options = ['Sim','Não','Não aplicável'];
        if ($type === 'categorized_image_upload' && !$options) $options = ['Expositores','Arcas','Displays','Topos'];
        $questionConfig = \RoutesPro\Briefings\ShareSpaceMatrix::sanitizeConfig($payload['question_config_json'] ?? $payload['question_config'] ?? []);
        if ($type === 'share_space_matrix' && empty($questionConfig['rows']) && $sectionId) {
            $sectionRow = $wpdb->get_row($wpdb->prepare('SELECT section_type,content_json FROM ' . BriefingService::table('briefing_sections') . ' WHERE id=%d AND briefing_id=%d LIMIT 1', $sectionId, $briefingId), ARRAY_A) ?: [];
            $content = json_decode((string)($sectionRow['content_json'] ?? ''), true);
            $content = is_array($content) ? $content : [];
            $questionConfig = \RoutesPro\Briefings\ShareSpaceMatrix::configFromSection($content, sanitize_key((string)($sectionRow['section_type'] ?? '')), ['reference_week'=>(int)($content['week'] ?? 0)]);
        }
        if ($type === 'share_space_matrix' && empty($questionConfig['rows'])) return ['saved'=>0,'error'=>'A pergunta comparativa precisa de pelo menos duas referências válidas. Escolhe uma secção com referências detetáveis ou adiciona manualmente uma comparação.'];
        $formId = absint($payload['form_id'] ?? 0);
        $key = $originalKey !== '' ? $originalKey : ContextQuestionService::make_briefing_question_key($briefingId, $sectionId, $label, $formId);
        $suggestionKey = sanitize_key((string)($payload['suggestion_id'] ?? ''));
        if ($originalKey === '' && $suggestionKey !== '') $key = sanitize_key($key . '_' . substr(hash('sha256', $suggestionKey), 0, 12));
        $requestedPriority = absint($payload['question_order'] ?? 0);
        $priority = $requestedPriority > 0 ? min(9999, $requestedPriority) : ((int)$wpdb->get_var($wpdb->prepare(
            'SELECT COALESCE(MAX(priority),0) FROM '.ContextQuestionService::table().' WHERE briefing_id=%d', $briefingId
        )) + 10);
        $isActive = array_key_exists('is_active', $payload) ? !empty($payload['is_active']) : true;
        $analyticsEnabled = !empty($payload['analytics_enabled']);
        $analyticsDashboardId = absint($payload['analytics_dashboard_id'] ?? 0);
        if ($analyticsEnabled && !$analyticsDashboardId) {
            $analyticsDashboardId = QuestionAnalyticsService::preferredBriefingDashboardId((int)$b['client_id'], (int)$b['project_id'], true);
        }
        $analyticsBehavior = sanitize_key((string)($payload['analytics_behavior'] ?? 'rollup'));
        if (!in_array($analyticsBehavior, ['rollup','existing','new'], true)) $analyticsBehavior = 'rollup';
        $existingAnalyticsRules = [];
        if ($originalKey !== '') {
            $existingRulesJson = (string)$wpdb->get_var($wpdb->prepare(
                'SELECT visibility_rules_json FROM '.ContextQuestionService::table().' WHERE briefing_id=%d AND question_key=%s ORDER BY id ASC LIMIT 1',
                $briefingId,
                $originalKey
            ));
            $existingAnalyticsRules = json_decode($existingRulesJson, true);
            $existingAnalyticsRules = is_array($existingAnalyticsRules) ? $existingAnalyticsRules : [];
        }
        $explicitAnalyticsIntent = !empty($payload['analytics_behavior_user_intent']);
        $existingAnalyticsUserConfirmed = QuestionAnalyticsService::analyticsChoiceIsUserConfirmed($existingAnalyticsRules);
        if ($analyticsEnabled && !$explicitAnalyticsIntent) {
            if ($existingAnalyticsUserConfirmed) {
                $persistedBehavior = sanitize_key((string)($existingAnalyticsRules['analytics_behavior'] ?? ''));
                if (in_array($persistedBehavior, ['rollup','existing','new'], true)) $analyticsBehavior = $persistedBehavior;
            } else {
                $analyticsBehavior = QuestionAnalyticsService::isStrategicPermanentQuestion($label, $type) ? 'existing' : 'rollup';
            }
        }
        if (!$analyticsEnabled) $analyticsBehavior = 'off';
        $analyticsBehaviorSource = ($explicitAnalyticsIntent || $existingAnalyticsUserConfirmed) ? 'user' : 'system';
        $analyticsBehaviorUserConfirmed = ($explicitAnalyticsIntent || $existingAnalyticsUserConfirmed) ? 1 : 0;
        $analyticsMetricKey = sanitize_key((string)($payload['analytics_metric_key'] ?? ''));
        $analyticsMetricLabel = sanitize_text_field((string)($payload['analytics_metric_label'] ?? $label));
        if ($analyticsEnabled && $analyticsBehavior === 'rollup') {
            $analyticsMetricKey = '';
        } elseif ($analyticsEnabled && !$explicitAnalyticsIntent && !$existingAnalyticsUserConfirmed && $analyticsBehavior === 'existing') {
            // 2.2.337: system-managed questions always converge to the current
            // semantic family, even if the editor submitted a stale legacy key.
            $systemFamilyKey = QuestionAnalyticsService::strategicFamilyKey($label, $type);
            if ($systemFamilyKey !== '') {
                $analyticsMetricKey = $systemFamilyKey;
                $systemFamilyLabel = QuestionAnalyticsService::strategicFamilyLabel($systemFamilyKey);
                if ($systemFamilyLabel !== '') $analyticsMetricLabel = $systemFamilyLabel;
            } elseif ($analyticsMetricKey === '') {
                $analyticsMetricKey = QuestionAnalyticsService::suggestCanonicalMetricKey($label, $type, (int)$b['client_id'], (int)$b['project_id'], $analyticsDashboardId);
            }
        } elseif ($analyticsEnabled && $analyticsBehavior === 'new' && $analyticsMetricKey === '') {
            $analyticsMetricKey = QuestionAnalyticsService::newPermanentMetricKey($label, $type, (int)$b['client_id'], (int)$b['project_id'], $analyticsDashboardId, $key);
        } elseif ($analyticsEnabled && $analyticsMetricKey === '') {
            $analyticsMetricKey = QuestionAnalyticsService::suggestCanonicalMetricKey($label, $type, (int)$b['client_id'], (int)$b['project_id'], $analyticsDashboardId);
        }
        $analyticsDefaults = QuestionAnalyticsService::defaultsForType($type, [
            'chart_type' => sanitize_key((string)($payload['analytics_chart_type'] ?? '')),
            'aggregation' => sanitize_key((string)($payload['analytics_aggregation'] ?? '')),
            'dimension' => sanitize_key((string)($payload['analytics_dimension'] ?? '')),
            'secondary_dimension' => sanitize_key((string)($payload['analytics_secondary_dimension'] ?? '')),
            'show_kpi' => !empty($payload['analytics_show_kpi']),
            'show_table' => !empty($payload['analytics_show_table']),
            'show_empty' => array_key_exists('analytics_show_empty', $payload) ? !empty($payload['analytics_show_empty']) : true,
        ]);
        $analyticsSort = max(1, min(9999, absint($payload['analytics_sort_order'] ?? $priority) ?: $priority));
        $categoryDecision = CategoryMapping::resolveForQuestion($b, $label, $sectionId);
        $requestedCategory = sanitize_text_field(trim((string)($payload['product_category'] ?? '')));
        $postedIdentity = sanitize_text_field((string)($payload['product_category_identity'] ?? ''));
        $manuallyChanged = !empty($payload['product_category_override']);
        $categoryManual = $requestedCategory !== ''
            && in_array($requestedCategory, CategoryMapping::categories((int)$b['client_id']), true)
            && ($manuallyChanged || ($postedIdentity !== '' && hash_equals((string)$categoryDecision['key'], $postedIdentity)));
        if ($categoryManual) {
            $catScope = CategoryMapping::sectionContext($sectionId, $briefingId);
            $categoryManual = CategoryMapping::saveManual((int)$b['client_id'], (int)$b['project_id'], $label, $catScope['retailer'], $catScope['fingerprint'], $requestedCategory);
        }
        if ($categoryManual) {
            $categoryDecision = CategoryMapping::resolveForQuestion($b, $label, $sectionId);
            if ((string)$categoryDecision['category'] !== $requestedCategory) {
                // No safe cross-week identity: save for this question only, not a generic alias.
                $categoryDecision['category'] = $requestedCategory;
                $categoryDecision['source'] = 'manual';
                $categoryDecision['status'] = 'confirmed';
            }
        }
        $stepKey = array_key_exists('briefing_step_key', $payload)
            ? sanitize_key((string)$payload['briefing_step_key'])
            : sanitize_key((string)($existingAnalyticsRules['briefing_step_key'] ?? ''));
        if ($stepKey !== '' && !BriefingSteps::find($briefingId, $stepKey)) {
            return ['saved'=>0, 'error'=>'O step escolhido não pertence a este briefing. Atualiza a página.'];
        }
        // Preserve unrelated future rule keys on an existing question.
        $visibilityRules = wp_json_encode(array_merge($existingAnalyticsRules, [
            'briefing_step_key' => $stepKey,
            'target_scope' => $scope,
            'target_scope_label' => BriefingService::questionScopeLabel($scope),
            'selected_location_ids' => $selectedIds,
            'product_category' => (string)($categoryDecision['category'] ?? ''),
            'category_source' => (string)($categoryDecision['source'] ?? ''),
            'category_identity' => (string)($categoryDecision['key'] ?? ''),
            'retailer_key' => $sectionRetailer,
            'allows_unmapped' => BriefingService::questionScopeAllowsUnmapped($scope),
            'analytics_behavior' => $analyticsBehavior === 'off' ? 'rollup' : $analyticsBehavior,
            'analytics_behavior_source' => $analyticsBehaviorSource,
            'analytics_behavior_user_confirmed' => $analyticsBehaviorUserConfirmed,
        ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $saved = 0;
        $errors = [];
        $targetLocationIds = array_values(array_unique(array_map('absint', $locationIds)));
        foreach ($targetLocationIds as $locationId) {
            $campaignLocationId = 0;
            if ((int)$b['project_id'] && $locationId) $campaignLocationId = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}routespro_campaign_locations WHERE project_id=%d AND location_id=%d AND is_active=1 LIMIT 1", (int)$b['project_id'], $locationId));
            $row = [
                'client_id'=>(int)$b['client_id'],'project_id'=>(int)$b['project_id'],'form_id'=>$formId,'location_id'=>(int)$locationId,'campaign_location_id'=>$campaignLocationId,
                'source_type'=>'briefing','briefing_id'=>$briefingId,'briefing_version_no'=>(int)$b['version_no'],'briefing_section_id'=>$sectionId,'briefing_item_key'=>sanitize_text_field((string)($payload['suggestion_id'] ?? '')),
                'valid_from'=>$b['valid_from'],'valid_to'=>$b['valid_to'],'context_type'=>'briefing','question_key'=>$key,'question_label'=>$label,'question_type'=>$type,
                'question_options_json'=>wp_json_encode($options, JSON_UNESCAPED_UNICODE),'question_config_json'=>wp_json_encode($questionConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),'help_text'=>sanitize_text_field((string)($payload['help_text'] ?? '')),
                'is_required'=>!empty($payload['is_required'])?1:0,'is_active'=>$isActive?1:0,'priority'=>$priority,'visibility_rules_json'=>$visibilityRules,
                'analytics_enabled'=>$analyticsEnabled?1:0,'analytics_dashboard_id'=>$analyticsDashboardId,
                'analytics_metric_key'=>$analyticsMetricKey,
                'analytics_metric_label'=>$analyticsMetricLabel,
                'analytics_chart_type'=>$analyticsDefaults['chart_type'],'analytics_aggregation'=>$analyticsDefaults['aggregation'],
                'analytics_dimension'=>$analyticsDefaults['dimension'],'analytics_secondary_dimension'=>$analyticsDefaults['secondary_dimension'],
                'analytics_sort_order'=>$analyticsSort,'analytics_show_kpi'=>$analyticsDefaults['show_kpi'],'analytics_show_table'=>$analyticsDefaults['show_table'],
                'analytics_show_empty'=>$analyticsDefaults['show_empty'],'updated_at'=>current_time('mysql'),
            ];
            $lookupKey = $originalKey !== '' ? $originalKey : $key;
            $existing = (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.ContextQuestionService::table().' WHERE briefing_id=%d AND location_id=%d AND question_key=%s LIMIT 1',$briefingId,$locationId,$lookupKey));
            if ($existing) $result = $wpdb->update(ContextQuestionService::table(), $row, ['id'=>$existing]);
            else { $row['created_at']=current_time('mysql'); $result = $wpdb->insert(ContextQuestionService::table(), $row); }
            if ($result === false) $errors[] = $wpdb->last_error ?: 'Erro desconhecido na gravação da pergunta.';
            else $saved++;
        }
        if ($saved > 0 && $targetLocationIds) {
            $placeholders = implode(',', array_fill(0, count($targetLocationIds), '%d'));
            $cleanupKey = $originalKey !== '' ? $originalKey : $key;
            $wpdb->query($wpdb->prepare(
                'DELETE FROM '.ContextQuestionService::table().' WHERE briefing_id=%d AND question_key=%s AND location_id NOT IN ('.$placeholders.')',
                ...array_merge([$briefingId, $cleanupKey], $targetLocationIds)
            ));
            $analytics = QuestionAnalyticsService::syncForQuestionKey($key, $briefingId);
        } else {
            $analytics = ['message'=>''];
        }
        return [
            'saved'=>$saved,
            'error'=>implode(' ', array_values(array_unique(array_filter($errors)))),
            'analytics_message'=>(string)($analytics['message'] ?? ''),
            'question_key'=>$key,
            'updated'=>$originalKey !== '',
        ];
    }

    private static function groupExecutionQuestions(array $questions): array {
        $groups = [];
        foreach ($questions as $row) {
            $key = sanitize_key((string)($row['question_key'] ?? '')) ?: ('question_' . (int)($row['id'] ?? 0));
            if (!isset($groups[$key])) {
                $groups[$key] = $row;
                $groups[$key]['id'] = (int)($row['id'] ?? 0);
                $groups[$key]['question_key'] = $key;
                $groups[$key]['location_ids'] = [];
                $groups[$key]['location_names'] = [];
                $groups[$key]['location_map'] = [];
            }
            $locationId = (int)($row['location_id'] ?? 0);
            if ($locationId) $groups[$key]['location_ids'][$locationId] = $locationId;
            $name = trim((string)($row['location_name'] ?? ''));
            if ($name !== '') {
                $groups[$key]['location_names'][$locationId ?: $name] = $name;
                if ($locationId) $groups[$key]['location_map'][$locationId] = $name;
            }
        }
        foreach ($groups as &$group) {
            $group['location_ids'] = array_values($group['location_ids']);
            $group['location_names'] = array_values($group['location_names']);
            $group['store_count'] = count($group['location_ids']);
            $options = json_decode((string)($group['question_options_json'] ?? ''), true);
            $group['question_options'] = is_array($options) ? array_values(array_map('strval', $options)) : [];
            $group['question_config'] = \RoutesPro\Briefings\ShareSpaceMatrix::sanitizeConfig((string)($group['question_config_json'] ?? ''));
            $rules = json_decode((string)($group['visibility_rules_json'] ?? ''), true);
            if (!is_array($rules)) $rules = [];
            $group['target_scope'] = sanitize_key((string)($rules['target_scope'] ?? 'selected')) ?: 'selected';
            $group['briefing_step_key'] = sanitize_key((string)($rules['briefing_step_key'] ?? ''));
            $group['product_category'] = sanitize_text_field((string)($rules['product_category'] ?? ''));
            $group['category_source'] = sanitize_key((string)($rules['category_source'] ?? ''));
            $group['target_scope_label'] = BriefingService::questionScopeLabel((string)$group['target_scope']);
            $group['analytics_enabled'] = !empty($group['analytics_enabled']);
            $group['analytics_behavior'] = QuestionAnalyticsService::analyticsBehaviorFromRow($group, true);
            $group['analytics_metric_key'] = sanitize_key((string)($group['analytics_metric_key'] ?? ''));
            $group['analytics_sort_order'] = (int)($group['analytics_sort_order'] ?? $group['priority'] ?? 10);
        }
        unset($group);
        return array_values($groups);
    }

    private static function redirectQuestionApproval(int $briefingId, string $notice, int $approved=0, int $assignments=0, int $failed=0, string $message=''): void {
        $args = [
            'page'=>'routespro-briefings',
            'briefing_id'=>$briefingId,
            'ffb_tab'=>'questions',
            'ffb_notice'=>$notice,
            'ffb_approved'=>max(0,$approved),
            'ffb_assignments'=>max(0,$assignments),
            'ffb_failed'=>max(0,$failed),
        ];
        if ($message !== '') $args['ffb_message'] = rawurlencode($message);
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function handleQuestionDelete(): void {
        self::guard(); global $wpdb;
        $briefingId=absint($_POST['briefing_id']??0); $questionId=absint($_POST['question_id']??0);
        check_admin_referer('fieldflow_briefing_question_delete_'.$briefingId.'_'.$questionId);
        if ($questionId) {
            $table = ContextQuestionService::table();
            $key = sanitize_key((string)$wpdb->get_var($wpdb->prepare('SELECT question_key FROM '.$table.' WHERE id=%d AND briefing_id=%d LIMIT 1', $questionId, $briefingId)));
            if ($key !== '') { QuestionAnalyticsService::deactivateForQuestion($key); $wpdb->query($wpdb->prepare('DELETE FROM '.$table.' WHERE briefing_id=%d AND question_key=%s', $briefingId, $key)); }
            else $wpdb->delete($table,['id'=>$questionId,'briefing_id'=>$briefingId],['%d','%d']);
        }
        self::redirect($briefingId,'question_deleted');
    }

    public static function handleQuestionsBulkDelete(): void {
        self::guard(); global $wpdb;
        $briefingId = absint($_POST['briefing_id'] ?? 0);
        check_admin_referer('fieldflow_briefing_questions_bulk_'.$briefingId);
        $singleId = absint($_POST['single_question_id'] ?? 0);
        $ids = $singleId ? [$singleId] : array_values(array_unique(array_filter(array_map('absint', (array)($_POST['question_ids'] ?? [])))));
        $action = $singleId ? 'delete' : sanitize_key((string)($_POST['bulk_action'] ?? ''));
        $table = ContextQuestionService::table();
        if ($action === 'save_steps') {
            // 9DX: change ONLY visibility_rules_json for the requested logical questions.
            // No insert, delete, reassignment, answer migration or analytics recalculation.
            $assignments = is_array($_POST['question_step_keys'] ?? null) ? wp_unslash($_POST['question_step_keys']) : [];
            $assignments = array_slice($assignments, 0, 100, true);
            $definitions = BriefingSteps::all($briefingId);
            $known = array_fill_keys(array_column($definitions, 'key'), true);
            foreach ($assignments as $rawStep) {
                $candidate = sanitize_key((string)$rawStep);
                if ($candidate !== '' && !isset($known[$candidate])) {
                    self::redirectQuestionApproval($briefingId,'error',0,0,1,'Um dos steps já não pertence a este briefing. Atualiza a página.');
                }
            }
            $updated = 0;
            $saved = 0;
            $failed = 0;
            $processed = [];
            foreach ($assignments as $rawId => $rawStep) {
                $id = absint($rawId);
                if (!$id) { $failed++; continue; }
                $stepKey = sanitize_key((string)$rawStep);
                $questionKey = sanitize_key((string)$wpdb->get_var($wpdb->prepare(
                    'SELECT question_key FROM '.$table.' WHERE briefing_id=%d AND id=%d LIMIT 1', $briefingId, $id
                )));
                if ($wpdb->last_error !== '' || $questionKey === '') { $failed++; continue; }
                if (isset($processed[$questionKey])) continue;
                $processed[$questionKey] = true;
                $rows = $wpdb->get_results($wpdb->prepare(
                    'SELECT id,visibility_rules_json FROM '.$table.' WHERE briefing_id=%d AND question_key=%s', $briefingId, $questionKey
                ), ARRAY_A) ?: [];
                if ($wpdb->last_error !== '' || !$rows) { $failed++; continue; }
                foreach ($rows as $row) {
                    $rules = json_decode((string)($row['visibility_rules_json'] ?? ''), true);
                    if (!is_array($rules)) $rules = [];
                    if (sanitize_key((string)($rules['briefing_step_key'] ?? '')) === $stepKey) { $saved++; continue; }
                    $rules['briefing_step_key'] = $stepKey;
                    $result = $wpdb->update($table, [
                        'visibility_rules_json'=>wp_json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    ], ['id'=>(int)$row['id']], ['%s'], ['%d']);
                    if ($result === false) { $failed++; continue; }
                    $updated += (int)$result;
                    $saved++;
                }
            }
            if ($failed > 0 || !$assignments) {
                self::redirectQuestionApproval(
                    $briefingId, $saved > 0 ? 'questions_steps_partial' : 'error', 0, $saved, $failed ?: 1,
                    sprintf('Não foi possível guardar %d aplicação(ões) de step. Atualiza a página e tenta novamente.', $failed ?: 1)
                );
            }
            self::redirectQuestions($briefingId, 'questions_steps_saved', $updated, absint($_POST['ffb_qpage'] ?? 1), absint($_POST['ffb_qper_page'] ?? 20));
        }
        if ($action === 'save_order') {
            $priorities = is_array($_POST['question_priorities'] ?? null) ? $_POST['question_priorities'] : [];
            $updated = 0;
            foreach ($priorities as $idRaw => $priorityRaw) {
                $id = absint($idRaw); $priority = max(1, min(9999, absint($priorityRaw)));
                if (!$id) continue;
                $key = sanitize_key((string)$wpdb->get_var($wpdb->prepare('SELECT question_key FROM '.$table.' WHERE briefing_id=%d AND id=%d LIMIT 1', $briefingId, $id)));
                if ($key === '') continue;
                $updated += (int)$wpdb->query($wpdb->prepare('UPDATE '.$table.' SET priority=%d,analytics_sort_order=%d,updated_at=%s WHERE briefing_id=%d AND question_key=%s', $priority, $priority, current_time('mysql'), $briefingId, $key));
                QuestionAnalyticsService::syncForQuestionKey($key, $briefingId);
            }
            self::redirectQuestions($briefingId, 'questions_reordered', $updated, absint($_POST['ffb_qpage'] ?? 1), absint($_POST['ffb_qper_page'] ?? 20));
        }
        if (in_array($action, ['activate','deactivate'], true)) {
            if (!$ids) self::redirectQuestions($briefingId, 'questions_none_selected', 0, absint($_POST['ffb_qpage'] ?? 1), absint($_POST['ffb_qper_page'] ?? 20));
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $keys = array_values(array_unique(array_filter(array_map('sanitize_key', $wpdb->get_col($wpdb->prepare(
                'SELECT question_key FROM '.$table.' WHERE briefing_id=%d AND id IN ('.$placeholders.')', ...array_merge([$briefingId], $ids)
            )) ?: []))));
            $changed = 0;
            foreach ($keys as $key) {
                $changed += (int)$wpdb->query($wpdb->prepare('UPDATE '.$table.' SET is_active=%d,updated_at=%s WHERE briefing_id=%d AND question_key=%s', $action === 'activate' ? 1 : 0, current_time('mysql'), $briefingId, $key));
                QuestionAnalyticsService::syncForQuestionKey($key, $briefingId);
            }
            self::redirectQuestions($briefingId, $action === 'activate' ? 'questions_activated' : 'questions_deactivated', $changed, absint($_POST['ffb_qpage'] ?? 1), absint($_POST['ffb_qper_page'] ?? 20));
        }
        if ($action !== 'delete' || !$ids) self::redirectQuestions($briefingId, 'questions_none_selected', 0, absint($_POST['ffb_qpage'] ?? 1), absint($_POST['ffb_qper_page'] ?? 20));
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $keys = array_values(array_unique(array_filter(array_map('sanitize_key', $wpdb->get_col($wpdb->prepare(
            'SELECT question_key FROM '.$table.' WHERE briefing_id=%d AND id IN ('.$placeholders.')', ...array_merge([$briefingId], $ids)
        )) ?: []))));
        if (!$keys) self::redirectQuestions($briefingId, 'questions_none_selected', 0, absint($_POST['ffb_qpage'] ?? 1), absint($_POST['ffb_qper_page'] ?? 20));
        foreach ($keys as $key) QuestionAnalyticsService::deactivateForQuestion($key);
        $keyPlaceholders = implode(',', array_fill(0, count($keys), '%s'));
        $deleted = (int)$wpdb->query($wpdb->prepare('DELETE FROM '.$table.' WHERE briefing_id=%d AND question_key IN ('.$keyPlaceholders.')', ...array_merge([$briefingId], $keys)));
        self::redirectQuestions($briefingId, 'questions_deleted', $deleted, absint($_POST['ffb_qpage'] ?? 1), absint($_POST['ffb_qper_page'] ?? 20));
    }

    private static function redirectQuestions(int $briefingId, string $notice, int $deleted=0, int $page=1, int $perPage=20): void {
        if (!in_array($perPage, [10,20,50,100], true)) $perPage = 20;
        $args = [
            'page'=>'routespro-briefings',
            'briefing_id'=>$briefingId,
            'ffb_tab'=>'questions',
            'ffb_qpage'=>max(1,$page),
            'ffb_qper_page'=>$perPage,
            'ffb_notice'=>$notice,
        ];
        if ($deleted > 0) $args['ffb_deleted'] = $deleted;
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function handleExport(): void {
        self::guard(); $id=absint($_GET['briefing_id']??0); check_admin_referer('fieldflow_briefing_export_'.$id);
        $b=BriefingService::get($id); if(!$b) wp_die('Briefing não encontrado.');
        $rows=BriefingService::evidenceRows($id);
        nocache_headers(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="fieldflow-briefing-W'.(int)$b['week_no'].'-evidencia.csv"');
        $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,['Briefing','Ano','Semana','Versão','Utilizador','Email','Primeira visualização','Última visualização','Confirmação','Lojas associadas'],';');
        foreach($rows as $row){ $locs=json_decode((string)$row['location_ids_json'],true)?:[]; fputcsv($out,[$b['title'],$b['year_no'],$b['week_no'],$b['version_no'],$row['display_name'],$row['user_email'],$row['first_viewed_at'],$row['last_viewed_at'],$row['acknowledged_at'],count($locs)],';'); }
        fclose($out); exit;
    }

    private static function guard(): void { if(!current_user_can('routespro_manage')) wp_die('Sem permissões.'); }
    private static function redirect(int $id, string $notice, string $message=''): void { $args=['page'=>'routespro-briefings','ffb_notice'=>$notice]; if($id)$args['briefing_id']=$id; if($message)$args['ffb_message']=rawurlencode($message); wp_safe_redirect(add_query_arg($args,admin_url('admin.php'))); exit; }
    private static function notice(): void {
        $key=sanitize_key((string)($_GET['ffb_notice']??'')); if(!$key)return;
        $messages=[
            'imported'=>'Briefing importado. RevÃª as lojas e publica quando estiver pronto.',
            'imported_mapped'=>sprintf('Briefing importado. %d associação(ões) de loja foram resolvidas automaticamente com confiança muito alta. Os casos ambíguos ficaram por validar.', absint(rawurldecode((string)($_GET['ffb_message'] ?? '0')))),
            'imported_ai'=>'Briefing importado e analisado pelo Briefing AI Copilot.',
            'imported_ai_queued'=>'Briefing importado. A análise IA foi iniciada em segundo plano, sem bloquear o backoffice.',
            'imported_mapped_ai_queued'=>sprintf('Briefing importado, %d associação(ões) seguras aplicadas e análise IA iniciada em segundo plano.', absint(rawurldecode((string)($_GET['ffb_message'] ?? '0')))),
            'imported_ai_warning'=>'Briefing importado. A análise de IA não ficou concluída, podes executá-la no detalhe.',
            'mapped'=>'Associações guardadas e reutilizáveis nos próximos briefings.',
            'ai_analyzed'=>'Análise de IA atualizada com sucesso.',
            'ai_queued'=>'Análise IA iniciada em segundo plano. A página atualiza o estado automaticamente.',
            'ai_mapped'=>'Sugestões de associação com confiança elevada aplicadas. Revê as restantes lojas.',
            'safe_mapped'=>sprintf('%d associação(ões) seguras aplicadas por nome normalizado e insígnia. Os casos ambíguos ficaram por validar.', absint(rawurldecode((string)($_GET['ffb_message'] ?? '0')))),
            'cardex_synced'=>sprintf('Cardex desta versão sincronizado. %d referência(s) processadas sem apagar ajustes específicos das lojas.', absint(rawurldecode((string)($_GET['ffb_message'] ?? '0')))),
            'share_space_repaired'=>sprintf('Share Space convertido em %d matriz(es), aplicado a %d loja(s). %d aplicação(ões) antigas de percentagem foram desativadas, sem apagar histórico.', absint($_GET['ffb_approved'] ?? 0), absint($_GET['ffb_assignments'] ?? 0), absint(rawurldecode((string)($_GET['ffb_message'] ?? '0')))),
            'published'=>'Briefing publicado para a equipa.',
            'archived'=>'Briefing arquivado.',
            'deleted'=>'Briefing eliminado.',
            'step_saved'=>'Step criado. Agora podes atribuir perguntas a esta categoria sem perder respostas antigas.',
            'step_updated'=>'Step atualizado. Os formulários passam a mostrar o novo título e ordem, sem alterar respostas.',
            'question_saved'=>sprintf('Pergunta criada e gravada em %d loja(s). %s', absint($_GET['ffb_assignments'] ?? 0), sanitize_text_field(rawurldecode((string)($_GET['ffb_message'] ?? '')))),
            'question_updated'=>sprintf('Pergunta atualizada em %d loja(s). %s', absint($_GET['ffb_assignments'] ?? 0), sanitize_text_field(rawurldecode((string)($_GET['ffb_message'] ?? '')))),
            'questions_approved'=>sprintf('%d sugestão(ões) aprovadas, com %d aplicação(ões) criadas nas lojas elegíveis da campanha.', absint($_GET['ffb_approved'] ?? 0), absint($_GET['ffb_assignments'] ?? 0)),
            'questions_partial'=>sprintf('%d sugestão(ões) aprovadas e %d não gravadas. %s', absint($_GET['ffb_approved'] ?? 0), absint($_GET['ffb_failed'] ?? 0), sanitize_text_field(rawurldecode((string)($_GET['ffb_message'] ?? 'Revê o mapping das secções em falta.')))),
            'questions_none_selected_approve'=>'Seleciona pelo menos uma sugestão para aprovar.',
            'question_deleted'=>'Pergunta de execução removida.',
            'questions_deleted'=>sprintf('%d aplicação(ões) removida(s). As respostas históricas foram preservadas.', absint($_GET['ffb_deleted'] ?? 0)),
            'questions_reordered'=>'Ordem das perguntas e dos respetivos widgets guardada.',
            'questions_steps_saved'=>'Steps atualizados, mantendo todas as perguntas, lojas, respostas e configurações analíticas.',
            'questions_steps_partial'=>sprintf('Steps parcialmente guardados: %d aplicação(ões) confirmada(s) e %d não gravada(s). Atualiza a página e tenta novamente.', absint($_GET['ffb_assignments'] ?? 0), absint($_GET['ffb_failed'] ?? 0)),
            'questions_activated'=>'Perguntas selecionadas ativadas e analytics sincronizado.',
            'questions_deactivated'=>'Perguntas selecionadas desativadas sem apagar o histórico.',
            'questions_none_selected'=>'Seleciona pelo menos uma pergunta e escolhe uma ação.',
        ];
        $text=$key==='error'?sanitize_text_field(rawurldecode((string)($_GET['ffb_message']??'Ocorreu um erro.'))):($messages[$key]??'Operação concluída.');
        $warningKeys = ['imported_ai_warning','questions_partial','questions_steps_partial'];
        echo '<div class="notice '.($key==='error'?'notice-error':(in_array($key,$warningKeys,true)?'notice-warning':'notice-success')).' is-dismissible"><p>'.esc_html($text).'</p></div>';
    }
    private static function questionSignature(int $sectionId, string $label): string {
        $normalized = sanitize_key(remove_accents(strtolower(trim($label))));
        return $normalized === '' ? '' : $sectionId . '|' . $normalized;
    }
    private static function questionSuggestionId(array $suggestion, int $index=0): string {
        $options = array_values(array_map('strval', (array)($suggestion['options'] ?? [])));
        $seed = implode('|', [
            (string)(int)($suggestion['briefing_section_id'] ?? 0),
            trim((string)($suggestion['label'] ?? '')),
            sanitize_key((string)($suggestion['question_type'] ?? '')),
            wp_json_encode($options, JSON_UNESCAPED_UNICODE),
            wp_json_encode((array)($suggestion['question_config'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            trim((string)($suggestion['help_text'] ?? '')),
            trim((string)($suggestion['reason'] ?? '')),
        ]);
        if (trim(str_replace('|', '', $seed)) === '') $seed = 'suggestion|' . $index;
        return 'ffq_' . substr(hash('sha256', $seed), 0, 16);
    }
    private static function dateLabel($date): string { return $date?date_i18n('d/m/Y',strtotime((string)$date)):'Sem data'; }
    private static function dateTimeLabel($date): string { return $date?date_i18n('d/m/Y H:i',strtotime((string)$date)):'Ainda não'; }
    private static function detailsText(array $details): string { $out=[]; foreach($details as $key=>$value){ if(is_scalar($value)&&trim((string)$value)!=='')$out[]=ucfirst(str_replace('_',' ',$key)).': '.$value; } return implode(' · ',$out); }
    private static function kpi(string $label,$value,string $note): void { echo '<div class="ffb-kpi"><span>'.esc_html($label).'</span><strong>'.esc_html((string)$value).'</strong><small>'.esc_html($note).'</small></div>'; }
    private static function renderContentPreview(array $content): void {
        $items=(array)($content['items']??[]);
        if(!empty($content['text'])) echo '<p>'.nl2br(esc_html((string)$content['text'])).'</p>';
        if(!$items) return;
        $isShare = isset($items[0]['metric'],$items[0]['share'],$items[0]['target']);
        if($isShare){
            usort($items, static fn($a,$b)=>((float)($a['gap']??0))<=>((float)($b['gap']??0)));
            echo '<div class="ffb-share-admin"><table class="widefat striped"><thead><tr><th>KPI</th><th>Quota PR</th><th>Quota concorrência</th><th>Objetivo</th><th>Desvio</th><th>Vs. semana anterior</th><th>Estado</th></tr></thead><tbody>';
            foreach($items as $item){$gap=(float)($item['gap']??0);$delta=(float)($item['delta']??0);echo '<tr><td><strong>'.esc_html((string)$item['metric']).'</strong></td><td>'.esc_html(number_format_i18n(((float)$item['share'])*100,1)).'%</td><td>'.esc_html(number_format_i18n(((float)($item['competitor_share']??0))*100,1)).'%</td><td>'.esc_html(number_format_i18n(((float)$item['target'])*100,1)).'%</td><td class="'.($gap<0?'ffb-negative':'ffb-positive').'">'.esc_html(($gap>0?'+':'').number_format_i18n($gap*100,1)).' p.p.</td><td>'.esc_html(($delta>0?'+':'').number_format_i18n($delta*100,1)).' p.p.</td><td><span class="ffb-status '.($gap<0?'draft':'published').'">'.($gap<0?'Abaixo':'Atingido').'</span></td></tr>';}
            echo '</tbody></table></div>'; return;
        }
        echo '<div class="ffb-preview-list">'; foreach(array_slice($items,0,12) as $item){ if(is_string($item))echo '<div>'.esc_html($item).'</div>'; elseif(is_array($item)){ $title=$item['product']??$item['name']??$item['metric']??$item['code']??'Item'; echo '<div><strong>'.esc_html((string)$title).'</strong><small>'.esc_html(self::detailsText($item)).'</small></div>'; } } if(count($items)>12)echo '<div><strong>+'.(count($items)-12).' itens</strong><small>Disponíveis na app e no portal.</small></div>'; echo '</div>';
    }
    private static function renderStoreItemsTable(array $items,array $labels): void {
        echo '<div class="ffb-table-wrap"><table class="widefat striped"><thead><tr><th>Insígnia</th><th>Loja Excel</th><th>Loja FieldFlow</th><th>Instrução</th><th>Detalhe</th><th>Validade</th></tr></thead><tbody>';
        if(!$items) echo '<tr><td colspan="6">Não foram encontrados registos deste tipo.</td></tr>';
        foreach(array_slice($items,0,1000) as $item){ echo '<tr><td>'.esc_html($labels[$item['retailer_key']]??$item['retailer_key']).'</td><td><strong>'.esc_html($item['external_store_name']).'</strong></td><td>'.esc_html($item['location_name']?:'Por associar').'</td><td>'.esc_html($item['title']).'</td><td><small>'.esc_html(self::detailsText($item['details'])).'</small></td><td>'.esc_html(self::dateLabel($item['valid_from'])); if($item['valid_to'])echo '<br><small>até '.esc_html(self::dateLabel($item['valid_to'])).'</small>'; echo '</td></tr>'; }
        echo '</tbody></table></div>';
    }

    private static function renderAIPanel(array $briefing, array $ai, bool $aiReady, array $aiJob = []): void {
        $jobStatus = sanitize_key((string)($aiJob['status'] ?? ''));
        $audit = is_array($ai['audit'] ?? null) ? $ai['audit'] : [];
        $structure = is_array($ai['detected_structure'] ?? null) ? $ai['detected_structure'] : [];
        $changes = is_array($ai['changes'] ?? null) ? $ai['changes'] : [];
        $retailerSummaries = is_array($ai['retailer_summaries'] ?? null) ? $ai['retailer_summaries'] : [];
        $storeDetection = is_array($ai['store_detection'] ?? null) ? $ai['store_detection'] : [];
        $labels = BriefingService::retailerLabels();
        $auditStatusLabels = ['good' => 'Pronto', 'warning' => 'Atenção', 'critical' => 'Crítico'];
        $changeTypeLabels = ['new' => 'Novo', 'removed' => 'Removido', 'changed' => 'Alterado'];
        ?>
        <div class="ffb-card ffb-ai-card">
          <div class="ffb-head"><div><span class="ffb-kicker">Inteligência operacional</span><h2>Briefing AI Copilot</h2><p>Analisa estrutura, qualidade, alterações semanais e associações de lojas usando a IA já configurada no FieldFlow.</p></div><span class="ffb-pill"><?php echo esc_html($aiReady ? BriefingAIService::providerLabel() : 'IA não configurada'); ?></span></div>
          <?php if(in_array($jobStatus,['queued','running'],true)): ?><div class="ffb-ai-empty"><strong><?php echo $jobStatus==='running'?'Análise IA em processamento.':'Análise IA em fila.'; ?></strong><p>O trabalho decorre fora do pedido do navegador, evitando o erro 504. Esta página atualiza o estado automaticamente.</p></div><?php elseif($jobStatus==='error'): ?><div class="notice notice-error inline"><p><strong>A análise IA falhou.</strong> <?php echo esc_html((string)($aiJob['message']??'Revê o Centro de IA e tenta novamente.')); ?></p></div><?php endif; ?>
          <?php if(!$aiReady): ?>
            <div class="ffb-ai-empty"><strong>Configura um fornecedor de IA em FieldFlow, Definições.</strong><p>O briefing continua funcional sem IA, mas não terá auditoria inteligente, comparação narrativa nem sugestões de associação.</p></div>
          <?php elseif(!$ai): ?>
            <?php if(!in_array($jobStatus,['queued','running'],true)): ?><div class="ffb-ai-empty"><strong>Este briefing ainda não foi analisado.</strong><p>A análise não altera o briefing nem publica conteúdo. Apenas cria recomendações para validação humana.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="fieldflow_briefing_ai_analyze"><input type="hidden" name="briefing_id" value="<?php echo (int)$briefing['id']; ?>"><?php wp_nonce_field('fieldflow_briefing_ai_analyze_'.$briefing['id']); ?><button class="button button-primary">Analisar agora</button></form></div><?php endif; ?>
          <?php else: ?>
            <?php if(!empty($ai['stale'])): ?><div class="notice notice-warning inline"><p><strong>Análise desatualizada.</strong> O briefing ou o mapeamento de lojas mudou depois da última análise. Atualiza o copiloto antes de publicar.</p></div><?php endif; ?>
            <div class="ffb-ai-metrics">
              <div class="ffb-ai-metric"><span>Qualidade</span><strong><?php echo (int)($audit['score']??0); ?>/100</strong><small><?php $auditStatus=sanitize_key((string)($audit['status']??'warning')); echo esc_html($auditStatusLabels[$auditStatus]??'Atenção'); ?></small></div>
              <div class="ffb-ai-metric"><span>Estrutura</span><strong><?php echo (int)($structure['confidence']??0); ?>%</strong><small><?php echo esc_html((string)($structure['template']??'briefing semanal')); ?></small></div>
              <div class="ffb-ai-metric"><span>Semana detetada</span><strong>W<?php echo (int)($structure['week']??$briefing['week_no']); ?></strong><small><?php echo (int)($structure['year']??$briefing['year_no']); ?></small></div>
              <div class="ffb-ai-metric"><span>Lojas detetadas</span><strong><?php echo (int)($storeDetection['total_names']??0); ?></strong><small><?php echo (int)($storeDetection['scanned']??0); ?> pendentes verificadas</small></div>
              <div class="ffb-ai-metric"><span>Com candidatos</span><strong><?php echo (int)($storeDetection['with_candidates']??0); ?></strong><small><?php echo (int)($storeDetection['without_candidates']??0); ?> sem correspondência provável</small></div>
              <div class="ffb-ai-metric"><span>Sugestões de lojas</span><strong><?php echo count((array)($ai['store_suggestions']??[])); ?></strong><small>Exigem validação ou confiança ≥ 85%</small></div>
            </div>
            <div class="ffb-ai-summary-grid">
              <article><h3>Resumo executivo</h3><p><?php echo nl2br(esc_html((string)($ai['executive_summary']??'Sem resumo.'))); ?></p></article>
              <article><h3>Resumo para a equipa</h3><p><?php echo nl2br(esc_html((string)($ai['team_summary']??'Sem resumo.'))); ?></p></article>
              <article><h3>Resumo para o cliente</h3><p><?php echo nl2br(esc_html((string)($ai['client_summary']??'Sem resumo.'))); ?></p></article>
            </div>
            <?php if($retailerSummaries): ?><div class="ffb-ai-section"><h3>Síntese por insígnia</h3><div class="ffb-ai-retailers"><?php foreach($retailerSummaries as $key=>$text): ?><div><strong><?php echo esc_html($labels[$key]??$key); ?></strong><p><?php echo nl2br(esc_html((string)$text)); ?></p></div><?php endforeach; ?></div></div><?php endif; ?>
            <div class="ffb-ai-two">
              <section class="ffb-ai-section"><h3>Auditoria antes da publicação</h3><div class="ffb-ai-issues"><?php foreach((array)($audit['issues']??[]) as $issue): ?><div class="ffb-ai-issue <?php echo esc_attr(sanitize_key((string)($issue['severity']??'info'))); ?>"><strong><?php echo esc_html((string)($issue['title']??'Alerta')); ?></strong><p><?php echo esc_html((string)($issue['detail']??'')); ?></p><?php if(!empty($issue['action'])): ?><small>Ação: <?php echo esc_html((string)$issue['action']); ?></small><?php endif; ?></div><?php endforeach; ?></div></section>
              <section class="ffb-ai-section"><h3>Comparação com a semana anterior</h3><p class="ffb-ai-headline"><?php echo esc_html((string)($changes['headline']??'Sem briefing anterior para comparação.')); ?></p><div class="ffb-ai-changes"><?php foreach(array_slice((array)($changes['items']??[]),0,12) as $change): ?><div><span class="ffb-change-type <?php echo esc_attr(sanitize_key((string)($change['type']??'changed'))); ?>"><?php $changeType=sanitize_key((string)($change['type']??'changed')); echo esc_html($changeTypeLabels[$changeType]??'Alterado'); ?></span><strong><?php echo esc_html((string)($change['label']??'Alteração')); ?></strong><small><?php echo esc_html((string)($change['detail']??'')); ?></small></div><?php endforeach; ?></div></section>
            </div>
            <p class="description">Gerado em <?php echo esc_html(self::dateTimeLabel($ai['generated_at']??'')); ?> por <?php echo esc_html((string)($ai['provider']??'IA FieldFlow')); ?>. A IA recomenda, a publicação e as alterações continuam sob controlo humano.</p>
          <?php endif; ?>
        </div>
        <?php
    }

    private static function styles(): void { ?>
<style>
.ffb-grid{display:grid;grid-template-columns:minmax(320px,.8fr) minmax(520px,1.2fr);gap:18px;margin-top:18px}.ffb-card{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:20px;box-shadow:0 12px 30px rgba(15,23,42,.06)}.ffb-head,.ffb-detail-hero{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}.ffb-head h2,.ffb-head h3,.ffb-detail-hero h2{margin:3px 0 0}.ffb-kicker{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:800}.ffb-pill{background:#eff6ff;border:1px solid #dbeafe;border-radius:999px;padding:7px 10px;font-size:12px;font-weight:800}.ffb-form{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px}.ffb-form label{font-weight:700}.ffb-form input,.ffb-form select,.ffb-filters select{width:100%;margin-top:5px}.ffb-wide{grid-column:1/-1}.ffb-check{display:flex!important;align-items:flex-start;gap:10px;padding:13px 14px;border:1px solid #dbe3ee;border-radius:12px;background:#f8fafc}.ffb-check input{width:auto!important;margin-top:3px!important}.ffb-check span{display:flex;flex-direction:column;gap:3px}.ffb-check small{font-weight:400;color:#526070;line-height:1.45}.ffb-filters{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}.ffb-table-wrap{overflow:auto}.ffb-table-wrap table{min-width:780px}.ffb-status{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:800;background:#f1f5f9}.ffb-status.published{background:#dcfce7;color:#166534}.ffb-status.draft{background:#fef3c7;color:#92400e}.ffb-status.archived{background:#e2e8f0;color:#475569}.ffb-detail{margin-top:22px}.ffb-detail-hero{background:linear-gradient(135deg,#0f172a,#243b64);color:#fff;border-radius:24px;padding:24px}.ffb-detail-hero h2,.ffb-detail-hero p,.ffb-detail-hero .ffb-kicker{color:#fff}.ffb-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.ffb-actions form,.ffb-row-actions form{margin:0}.ffb-row-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.ffb-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin:14px 0}.ffb-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:15px}.ffb-kpi span,.ffb-kpi small{display:block;color:#64748b}.ffb-kpi strong{display:block;font-size:25px;margin:5px 0}.ffb-tabs{display:flex;gap:8px;overflow:auto;margin:18px 0 10px}.ffb-tabs button{border:0;border-radius:12px;padding:10px 14px;font-weight:800;cursor:pointer}.ffb-tabs button.active{background:#0f172a;color:#fff}.ffb-tab-panel{display:none}.ffb-tab-panel.active{display:block}.ffb-unmapped{background:#fff7ed!important}.ffb-alias-tools{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:14px 0}.ffb-dictionary-note{margin:-4px 0 14px;padding:11px 13px;border:1px solid #bae6fd;background:#f0f9ff;border-radius:12px;color:#0c4a6e;font-size:13px;line-height:1.45}.ffb-location-coverage{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin:-3px 0 12px;color:#475569}.ffb-location-coverage span{display:inline-flex;padding:5px 8px;border-radius:999px;background:#f1f5f9;border:1px solid #e2e8f0;font-size:11px;font-weight:800;color:#334155}.ffb-alias-tools input{min-width:320px;max-width:620px;width:100%}.ffb-location-picker-wrap{display:flex;gap:6px;align-items:center;min-width:390px}.ffb-location-picker-wrap .ffb-location-picker{min-width:290px;flex:1}.ffb-location-state.is-valid{color:#166534;font-weight:700}.ffb-location-state.is-invalid{color:#b45309;font-weight:700}.ffb-content-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:14px}.ffb-content-card p{line-height:1.55}.ffb-preview-list{display:grid;gap:7px}.ffb-preview-list>div{background:#f8fafc;border-radius:11px;padding:9px}.ffb-preview-list small{display:block;color:#64748b;margin-top:3px}.ffb-ai-question-suggestion{display:grid;grid-template-columns:minmax(260px,1.2fr) minmax(180px,.55fr) minmax(220px,.7fr) minmax(200px,.65fr) auto;gap:12px;align-items:end;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:13px}.ffb-ai-question-suggestion label{font-weight:700}.ffb-ai-question-suggestion .ffb-ai-required{margin:0;padding:9px 10px;min-height:58px}.ffb-ai-question-suggestion select{width:100%;margin-top:5px}.ffb-ai-question-suggestion small{display:block;color:#64748b;margin-top:4px}.ffb-ai-button{border-color:#7c3aed!important;color:#6d28d9!important}.ffb-ai-button:hover{background:#7c3aed!important;color:#fff!important}.ffb-ai-score{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:900;background:#ede9fe;color:#5b21b6}.ffb-ai-score.good{background:#dcfce7;color:#166534}.ffb-ai-score.warning{background:#fef3c7;color:#92400e}.ffb-ai-score.critical{background:#fee2e2;color:#991b1b}.ffb-ai-card{border-color:#ddd6fe;background:linear-gradient(180deg,#fff,#faf8ff)}.ffb-ai-empty{margin-top:16px;padding:18px;border:1px dashed #c4b5fd;border-radius:16px;background:#faf5ff}.ffb-ai-empty p{color:#64748b}.ffb-ai-metrics{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin:16px 0}.ffb-ai-metric{border:1px solid #ddd6fe;border-radius:16px;padding:14px;background:#fff}.ffb-ai-metric span,.ffb-ai-metric small{display:block;color:#64748b}.ffb-ai-metric strong{display:block;font-size:24px;margin:5px 0;color:#5b21b6}.ffb-ai-summary-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.ffb-ai-summary-grid article,.ffb-ai-section{border:1px solid #e2e8f0;border-radius:16px;padding:16px;background:#fff}.ffb-ai-summary-grid h3,.ffb-ai-section h3{margin-top:0}.ffb-ai-summary-grid p,.ffb-ai-section p{line-height:1.55;color:#475569}.ffb-ai-two{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px}.ffb-ai-retailers{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.ffb-ai-retailers>div{background:#f8fafc;border-radius:12px;padding:12px}.ffb-ai-issues,.ffb-ai-changes{display:grid;gap:8px}.ffb-ai-issue,.ffb-ai-changes>div{border-radius:12px;padding:11px;background:#f8fafc}.ffb-ai-issue.warning{border-left:4px solid #f59e0b}.ffb-ai-issue.critical{border-left:4px solid #dc2626}.ffb-ai-issue.info{border-left:4px solid #2563eb}.ffb-ai-issue p{margin:5px 0}.ffb-ai-issue small,.ffb-ai-changes small{display:block;color:#64748b;margin-top:4px}.ffb-ai-headline{font-weight:800;color:#334155!important}.ffb-change-type{display:inline-flex;margin-right:7px;padding:3px 7px;border-radius:999px;background:#e2e8f0;font-size:10px;font-weight:900;text-transform:uppercase}.ffb-change-type.new{background:#dcfce7;color:#166534}.ffb-change-type.removed{background:#fee2e2;color:#991b1b}.ffb-change-type.changed{background:#dbeafe;color:#1d4ed8}.ffb-ai-map-form{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:10px 0 14px}.ffb-ai-suggestion{margin-top:7px!important}.ffb-ai-reason{display:block;color:#6d28d9;margin-top:4px}.ffb-retailer-coverage{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin:12px 0}.ffb-retailer-coverage-card{display:flex;flex-direction:column;align-items:flex-start;gap:4px;border:1px solid #dbe3ee;border-radius:14px;background:#fff;padding:12px;text-align:left;cursor:pointer}.ffb-retailer-coverage-card:hover,.ffb-retailer-coverage-card.active{border-color:#2563eb;background:#eff6ff}.ffb-retailer-coverage-card span{font-size:12px;font-weight:800;color:#334155}.ffb-retailer-coverage-card small{color:#64748b}.ffb-internal-store-coverage{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:12px 0}.ffb-internal-store-coverage details{border:1px solid #e2e8f0;border-radius:13px;background:#f8fafc}.ffb-internal-store-coverage summary{display:flex;justify-content:space-between;gap:10px;padding:11px 13px;cursor:pointer}.ffb-internal-store-coverage summary span{color:#64748b;font-size:12px}.ffb-internal-store-coverage details>div{padding:0 13px 12px;color:#475569}.ffb-internal-store-coverage p{margin:0;line-height:1.5}.ffb-alias-empty{padding:12px 14px;border:1px solid #fde68a;background:#fffbeb;border-radius:12px;color:#854d0e;margin:10px 0}.ffb-store-source-card{margin-top:14px}.ffb-source-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:14px}.ffb-source-grid article{border:1px solid #e2e8f0;border-radius:14px;padding:13px;background:#f8fafc;display:flex;flex-direction:column;gap:4px}.ffb-source-grid article span{font-size:10px;text-transform:uppercase;font-weight:900;color:#64748b}.ffb-source-grid article small{color:#64748b}.ffb-source-grid article b{margin-top:5px;color:#1d4ed8}.ffb-reimport-form{display:grid;grid-template-columns:minmax(260px,1fr) minmax(260px,1fr) auto;gap:12px;align-items:end;margin-top:18px;padding:15px;border:1px solid #bfdbfe;border-radius:16px;background:#eff6ff}.ffb-reimport-form input[type=file]{display:block;margin-top:7px}.ffb-share-admin{overflow:auto}.ffb-share-admin table{min-width:760px}.ffb-negative{color:#b91c1c;font-weight:800}.ffb-positive{color:#166534;font-weight:800}
.ffb-comparison-config{padding:16px;border:1px solid #c7d2fe;border-radius:16px;background:linear-gradient(145deg,#eef2ff,#fff 62%,#ecfeff)}.ffb-comparison-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap}.ffb-comparison-head>div:first-child{max-width:720px}.ffb-comparison-head strong{display:block;font-size:15px}.ffb-comparison-head small{display:block;margin-top:4px;color:#526070;font-weight:500;line-height:1.45}.ffb-comparison-actions{display:flex;gap:8px;flex-wrap:wrap}.ffb-comparison-status{margin:12px 0 8px;padding:9px 11px;border:1px solid #dbeafe;border-radius:11px;background:#fff;color:#334155;font-size:12px;font-weight:750}.ffb-comparison-status.is-empty{border-color:#fde68a;background:#fffbeb;color:#854d0e}.ffb-comparison-rows{display:grid;gap:9px}.ffb-comparison-row{display:grid;grid-template-columns:minmax(160px,1fr) minmax(160px,1fr) minmax(110px,.42fr) minmax(180px,1.15fr) auto;gap:9px;align-items:end;padding:12px;border:1px solid #dbe3ee;border-radius:13px;background:#fff}.ffb-comparison-row label{min-width:0;font-size:11px;color:#475569}.ffb-comparison-row input{width:100%;margin-top:5px}.ffb-comparison-remove{align-self:center;margin-top:18px;white-space:nowrap}.ffb-question-config[hidden]{display:none!important}@media(max-width:1100px){.ffb-comparison-row{grid-template-columns:1fr 1fr}.ffb-comparison-remove{justify-self:start;margin-top:0}}@media(max-width:680px){.ffb-comparison-head{display:grid}.ffb-comparison-actions{display:grid;grid-template-columns:1fr}.ffb-comparison-row{grid-template-columns:1fr}.ffb-comparison-remove{width:max-content}}
.ffb-question-toolbar,.ffb-question-footer{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin:12px 0}.ffb-question-bulk-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.ffb-question-per-page{display:flex;gap:7px;align-items:center;font-weight:700}.ffb-question-per-page select{min-width:72px}.ffb-analytics-config{padding:15px;border:1px solid #c7d2fe;border-radius:16px;background:#f8faff}.ffb-status{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:900}.ffb-status.is-active{background:#dcfce7;color:#166534}.ffb-status.is-inactive{background:#fee2e2;color:#991b1b}.ffb-question-table .check-column{width:42px;text-align:center}.ffb-question-table .check-column input{margin:0}.ffb-question-pagination{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.ffb-question-pagination .button{min-width:38px;text-align:center}.ffb-question-bulk-form.is-working,.ffb-ai-question-bulk-form.is-working{opacity:.68;pointer-events:none}.ffb-ai-question-toolbar{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin:12px 0}.ffb-ai-select-all{display:flex;gap:8px;align-items:center;font-weight:800}.ffb-ai-question-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.ffb-ai-suggestion-select{display:flex;align-items:center;justify-content:center;align-self:stretch}.ffb-ai-suggestion-select input{width:18px;height:18px}.ffb-ai-question-suggestion{grid-template-columns:42px minmax(260px,1.2fr) minmax(180px,.55fr) minmax(220px,.7fr) minmax(200px,.65fr) auto}
.ffb-repair-card{border-color:#c4b5fd;background:linear-gradient(135deg,#faf5ff,#eff6ff)}.ffb-repair-card .ffb-head{align-items:center}.ffb-question-config textarea{min-height:220px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;line-height:1.45}.ffb-matrix-preview{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px;margin-top:8px}.ffb-matrix-preview span{border:1px solid #dbeafe;background:#f8fbff;border-radius:10px;padding:8px 10px;font-size:12px}
.ffb-steps-card{border-color:#fed7aa;background:linear-gradient(150deg,#fff7ed,#fff 44%,#f8fafc)}.ffb-steps-stack{display:grid;gap:9px;margin-top:16px}.ffb-step-row{display:grid;grid-template-columns:minmax(190px,1fr) minmax(180px,1.2fr) 88px auto;align-items:end;gap:10px;padding:13px;border:1px solid #e2e8f0;background:#fff;border-radius:13px}.ffb-step-row label{display:block;min-width:0;font-weight:700}.ffb-step-row input{display:block;width:100%;box-sizing:border-box;margin-top:5px}.ffb-step-row .button{min-height:32px}.ffb-step-create{margin-top:12px;border-color:#fdba74}.ffb-inline-step-select{display:block;width:100%;max-width:100%;min-width:0;box-sizing:border-box;margin-top:7px;font-size:12px}.ffb-step-hint{margin:5px 0 12px;padding:10px 12px;border-radius:10px;background:#fff7ed;border:1px solid #fed7aa}.ffb-question-step-label{display:inline-block!important;margin-top:5px;color:#c2410c;font-weight:800}@media(max-width:900px){.ffb-step-row{grid-template-columns:1fr 1fr}.ffb-step-row .button{justify-self:start}}@media(max-width:600px){.ffb-step-row{grid-template-columns:1fr}}
.ffb-questions-layout{grid-template-columns:minmax(0,1fr);gap:18px}.ffb-questions-layout>.ffb-wide{grid-column:1}.ffb-question-editor-card,.ffb-question-list-card,.ffb-ai-suggestions-card{min-width:0}.ffb-question-editor-card{border-color:#dbeafe;box-shadow:0 16px 38px rgba(37,99,235,.08)}.ffb-question-editor-card .ffb-form{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 16px}.ffb-question-editor-card .ffb-form>label{min-width:0}.ffb-question-editor-card .ffb-form select,.ffb-question-editor-card .ffb-form input,.ffb-question-editor-card .ffb-form textarea{max-width:100%;box-sizing:border-box}.ffb-question-editor-card select[multiple]{min-height:190px;max-height:260px}.ffb-question-savebar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:4px;padding:14px 16px;border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(180deg,#f8fbff,#eff6ff)}.ffb-question-savebar .button-primary{min-width:160px}.ffb-question-list-card{overflow:hidden}.ffb-question-list-card>.ffb-head{align-items:center}.ffb-question-list-card .ffb-kpis{grid-template-columns:repeat(4,minmax(0,1fr));margin:16px 0}.ffb-question-list-card .ffb-kpi{min-width:0}.ffb-question-table-wrap{margin:0 -4px;padding:0 4px 8px;overflow-x:auto;overscroll-behavior-inline:contain;scrollbar-gutter:stable}.ffb-question-table{width:100%;min-width:1160px!important;table-layout:fixed;border-collapse:separate;border-spacing:0}.ffb-question-table th,.ffb-question-table td{vertical-align:top;word-break:normal;overflow-wrap:break-word;hyphens:none}.ffb-question-table thead th{position:sticky;top:0;z-index:2;background:#f8fafc;color:#475569;font-size:11px;line-height:1.2;text-transform:uppercase;letter-spacing:.035em;white-space:nowrap}.ffb-question-table th:nth-child(1),.ffb-question-table td:nth-child(1){width:42px}.ffb-question-table th:nth-child(2),.ffb-question-table td:nth-child(2){width:18%}.ffb-question-table th:nth-child(3),.ffb-question-table td:nth-child(3){width:11%}.ffb-question-table th:nth-child(4),.ffb-question-table td:nth-child(4){width:16%}.ffb-question-table th:nth-child(5),.ffb-question-table td:nth-child(5){width:13%}.ffb-question-table th:nth-child(6),.ffb-question-table td:nth-child(6){width:20%}.ffb-question-table th:nth-child(7),.ffb-question-table td:nth-child(7){width:82px}.ffb-question-table th:nth-child(8),.ffb-question-table td:nth-child(8){width:126px}.ffb-question-table th:nth-child(9),.ffb-question-table td:nth-child(9){width:104px}.ffb-question-table tbody tr:hover td,.ffb-question-table tbody tr:hover th{background:#f8fbff}.ffb-question-table .description{line-height:1.35}.ffb-question-order input{width:72px;max-width:100%;text-align:center}.ffb-question-validity strong,.ffb-question-validity small{display:block;white-space:nowrap}.ffb-question-validity small{margin-top:4px;color:#64748b}.ffb-question-row-actions{display:grid;gap:6px}.ffb-question-row-actions .button{width:100%;margin:0;text-align:center;justify-content:center}.ffb-question-toolbar{padding:10px 0 6px;border-top:1px solid #eef2f7}.ffb-question-footer{padding-top:10px;border-top:1px solid #eef2f7}.ffb-store-coverage-panel{z-index:4}.ffb-comparison-row{grid-template-columns:minmax(180px,1fr) minmax(180px,1fr) minmax(110px,.42fr) minmax(210px,1.15fr) auto}
@media(max-width:1400px){.ffb-question-editor-card .ffb-form{grid-template-columns:1fr 1fr}.ffb-question-table{min-width:1080px!important}.ffb-question-list-card .ffb-kpis{grid-template-columns:repeat(4,minmax(140px,1fr))}}
@media(max-width:900px){.ffb-question-editor-card .ffb-form,.ffb-question-list-card .ffb-kpis{grid-template-columns:1fr 1fr}.ffb-question-savebar{align-items:stretch}.ffb-question-savebar .button{flex:1}.ffb-question-table{min-width:1020px!important}}
@media(max-width:680px){.ffb-question-editor-card .ffb-form,.ffb-question-list-card .ffb-kpis{grid-template-columns:1fr}.ffb-question-savebar{display:grid;grid-template-columns:1fr}.ffb-question-savebar .button{width:100%}}
@media(max-width:1100px){.ffb-grid,.ffb-content-grid,.ffb-ai-summary-grid,.ffb-ai-two,.ffb-source-grid{grid-template-columns:1fr}.ffb-kpis,.ffb-ai-metrics{grid-template-columns:repeat(2,1fr)}}@media(max-width:680px){.ffb-form,.ffb-kpis,.ffb-ai-metrics,.ffb-ai-retailers,.ffb-retailer-coverage,.ffb-internal-store-coverage,.ffb-reimport-form,.ffb-ai-question-suggestion{grid-template-columns:1fr}.ffb-wide{grid-column:auto}}
.ffb-evergreen-key{display:inline-block;margin-top:4px;color:#475569}.ffb-evergreen-key code{font-size:10px}.ffb-store-coverage{margin-top:7px}.ffb-store-coverage summary{cursor:pointer;font-weight:800;color:#334155}.ffb-store-coverage-panel{margin-top:8px;padding:10px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;min-width:310px;max-width:520px}.ffb-store-coverage-panel>input{width:100%;margin:0 0 8px}.ffb-store-coverage-list{display:grid;gap:6px;max-height:330px;overflow:auto;padding-right:2px}.ffb-store-coverage-row{display:grid;grid-template-columns:minmax(150px,1fr) auto;gap:6px 10px;align-items:start;padding:8px;border:1px solid #eef2f7;border-radius:10px;background:#f8fafc}.ffb-store-coverage-row>div{min-width:0}.ffb-store-coverage-row strong{display:block;white-space:normal}.ffb-store-coverage-row small{display:block;color:#64748b;margin-top:2px}.ffb-store-coverage-row p{grid-column:1/-1;margin:0;color:#475569;font-size:12px;line-height:1.4}.ffb-store-state{display:inline-flex;padding:4px 7px;border-radius:999px;font-size:10px;font-weight:900;background:#e2e8f0;color:#334155}.ffb-store-state.is-implemented{background:#dcfce7;color:#166534}.ffb-store-state.is-not_implemented,.ffb-store-state.is-attention{background:#fee2e2;color:#991b1b}.ffb-store-state.is-pending,.ffb-store-state.is-partial{background:#fef3c7;color:#92400e}.ffb-store-state.is-not_applicable{background:#e0e7ff;color:#3730a3}.ffb-store-coverage-empty{display:block;padding:8px;color:#64748b}
</style><?php }
    private static function scripts(): void { ?>
<script>
(function(){
 const root=document.querySelector('.routespro-admin-wrap')||document;
 const detailRoot=root.querySelector('[data-comparison-configs]');
 let comparisonConfigs={};
 try{comparisonConfigs=JSON.parse(detailRoot?.dataset.comparisonConfigs||'{}')||{};}catch(e){comparisonConfigs={};}
 const importForm=root.querySelector('form[action*="admin-post.php"] input[value="fieldflow_briefing_import"]')?.closest('form');
 const client=importForm?.querySelector('select[name="client_id"]');
 const project=importForm?.querySelector('select[name="project_id"]');
 function sync(){if(!client||!project)return;const cid=client.value;Array.from(project.options).forEach(o=>{if(!o.value)return;o.hidden=!!cid&&o.dataset.client!==cid;});if(project.selectedOptions[0]&&project.selectedOptions[0].hidden)project.value='';}
 client&&client.addEventListener('change',sync);sync();
 const activateTab=(key)=>{const btn=root.querySelector('[data-ffb-tab="'+key+'"]');if(!btn)return;root.querySelectorAll('[data-ffb-tab]').forEach(x=>x.classList.toggle('active',x===btn));root.querySelectorAll('[data-ffb-panel]').forEach(x=>x.classList.toggle('active',x.dataset.ffbPanel===key));};
 root.querySelectorAll('[data-ffb-tab]').forEach(btn=>btn.addEventListener('click',()=>activateTab(btn.dataset.ffbTab)));
 const initialTab=new URLSearchParams(window.location.search).get('ffb_tab');if(initialTab)activateTab(initialTab);
 const options=Array.from(root.querySelectorAll('#ffb-location-list option'));
 const byLabel=new Map(options.map(o=>[o.value,String(o.dataset.id||'')]));
 root.querySelectorAll('[data-alias-row]').forEach(row=>{
   const picker=row.querySelector('.ffb-location-picker'),hidden=row.querySelector('.ffb-location-id'),state=row.querySelector('.ffb-location-state'),clear=row.querySelector('.ffb-clear-location');
   const apply=()=>{const id=byLabel.get((picker?.value||'').trim())||'';if(hidden)hidden.value=id;if(state){state.textContent=id?'Loja selecionada, ID '+id:'Seleciona uma opção da lista';state.classList.toggle('is-valid',!!id);state.classList.toggle('is-invalid',!id);}row.classList.toggle('ffb-unmapped',!id);};
   picker&&picker.addEventListener('input',apply);picker&&picker.addEventListener('change',apply);
   clear&&clear.addEventListener('click',()=>{if(picker)picker.value='';apply();picker?.focus();});
   const suggestion=row.querySelector('.ffb-ai-suggestion');
   suggestion&&suggestion.addEventListener('click',()=>{if(picker)picker.value=suggestion.dataset.locationLabel||'';if(hidden)hidden.value=suggestion.dataset.locationId||'';apply();});
 });
 const aliasSearch=root.querySelector('#ffb-alias-search');let retailerFilter='all';
 const filterAliases=()=>{const q=(aliasSearch?.value||'').trim().toLocaleLowerCase('pt-PT');let visible=0;root.querySelectorAll('[data-alias-row]').forEach(row=>{const okRetailer=retailerFilter==='all'||row.dataset.retailer===retailerFilter;const okSearch=!q||String(row.dataset.search||'').includes(q);const show=okRetailer&&okSearch;row.style.display=show?'':'none';if(show)visible++;});const empty=root.querySelector('[data-alias-empty]');if(empty)empty.hidden=visible>0||retailerFilter==='all';};
 aliasSearch&&aliasSearch.addEventListener('input',filterAliases);
 root.querySelectorAll('[data-retailer-filter]').forEach(btn=>btn.addEventListener('click',()=>{retailerFilter=btn.dataset.retailerFilter||'all';root.querySelectorAll('[data-retailer-filter]').forEach(x=>x.classList.toggle('active',x===btn));filterAliases();}));

 const aiQuestionBulkForm=root.querySelector('[data-ai-question-bulk-form]');
 if(aiQuestionBulkForm){
   const all=aiQuestionBulkForm.querySelector('[data-select-all-ai-suggestions]');
   const boxes=Array.from(aiQuestionBulkForm.querySelectorAll('[data-ai-suggestion-checkbox]'));
   const count=aiQuestionBulkForm.querySelector('[data-ai-selected-count]');
   const jsonField=aiQuestionBulkForm.querySelector('[data-selected-suggestions-json]');
   const update=()=>{const selected=boxes.filter(x=>x.checked).length;if(count)count.textContent=selected+' selecionada'+(selected===1?'':'s');if(all){all.checked=boxes.length>0&&selected===boxes.length;all.indeterminate=selected>0&&selected<boxes.length;}};
   const value=(card,suffix)=>card.querySelector('[name$="['+suffix+']"]')?.value||'';
   const serializeCard=(card)=>({
     suggestion_id:card.dataset.suggestionId||'',
     question_label:value(card,'question_label'),
     briefing_section_id:value(card,'briefing_section_id'),
     options:value(card,'options'),
     help_text:value(card,'help_text'),
     target_scope:value(card,'target_scope')||'section',
     question_order:value(card,'question_order')||'100',
     question_type:value(card,'question_type')||'radio',
     form_id:value(card,'form_id')||'0',
     briefing_step_key:value(card,'briefing_step_key'),
     analytics_enabled:value(card,'analytics_enabled')||'1',
     analytics_behavior:(value(card,'question_type')==='share_space_matrix'?'existing':'rollup'),
     analytics_chart_type:value(card,'analytics_chart_type')||'bar',
     question_config_json:value(card,'question_config_json'),
     analytics_aggregation:value(card,'analytics_aggregation')||'',
     analytics_dimension:value(card,'analytics_dimension')||'',
     analytics_show_kpi:value(card,'analytics_show_kpi')||'1',
     analytics_show_table:value(card,'analytics_show_table')||'1',
     is_required:card.querySelector('[name$="[is_required]"][type="checkbox"]')?.checked?'1':'0'
   });
   all&&all.addEventListener('change',()=>{boxes.forEach(x=>x.checked=all.checked);update();});boxes.forEach(x=>x.addEventListener('change',update));update();
   aiQuestionBulkForm.querySelectorAll('[data-ai-suggestion-card]').forEach(card=>{
     const suggestionType=card.querySelector('[name$="[question_type]"]');
     const suggestionConfig=card.querySelector('[name$="[question_config_json]"]');
     const suggestionSection=card.querySelector('[name$="[briefing_section_id]"]');
     const suggestionChart=card.querySelector('[name$="[analytics_chart_type]"]');
     const syncSuggestion=()=>{if(!suggestionType||suggestionType.value!=='share_space_matrix')return;if(suggestionChart)suggestionChart.value='table';let parsed={};try{parsed=JSON.parse(suggestionConfig?.value||'{}')||{};}catch(e){}if((parsed.rows||[]).length)return;const detected=comparisonConfigs[String(suggestionSection?.value||'')]||{};if(suggestionConfig&&(detected.rows||[]).length)suggestionConfig.value=JSON.stringify(detected);};
     suggestionType&&suggestionType.addEventListener('change',syncSuggestion);syncSuggestion();
   });
   aiQuestionBulkForm.addEventListener('submit',ev=>{
     const submitter=ev.submitter;
     const single=submitter&&submitter.name==='single_suggestion_id';
     const chosenCards=single
       ? [submitter.closest('[data-ai-suggestion-card]')].filter(Boolean)
       : boxes.filter(x=>x.checked).map(x=>x.closest('[data-ai-suggestion-card]')).filter(Boolean);
     if(chosenCards.length<1){ev.preventDefault();window.alert('Seleciona pelo menos uma sugestão para aprovar.');return;}
     if(!window.confirm('Aprovar '+chosenCards.length+' sugestão(ões) e criar as perguntas nas lojas elegíveis da campanha?')){ev.preventDefault();return;}
     if(jsonField)jsonField.value=JSON.stringify(chosenCards.map(serializeCard));
     aiQuestionBulkForm.querySelectorAll('[name^="suggestions["],[name="suggestion_ids[]"]').forEach(el=>el.disabled=true);
     aiQuestionBulkForm.classList.add('is-working');
   });
 }

 const questionEditor=root.querySelector('[data-question-editor]');
 if(questionEditor){
   const enabled=questionEditor.querySelector('[data-analytics-enabled]'),fields=questionEditor.querySelector('[data-analytics-fields]'),type=questionEditor.querySelector('[data-question-type]'),chart=questionEditor.querySelector('[data-analytics-chart]'),section=questionEditor.querySelector('[name=briefing_section_id]'),behavior=questionEditor.querySelector('[data-analytics-behavior]'),behaviorIntent=questionEditor.querySelector('[data-analytics-behavior-intent]'),ownershipStatus=questionEditor.querySelector('[data-analytics-ownership-status]');
   const comparisonBox=questionEditor.querySelector('[data-comparison-config]'),comparisonJson=questionEditor.querySelector('[data-comparison-json]'),comparisonRows=questionEditor.querySelector('[data-comparison-rows]'),comparisonTemplate=questionEditor.querySelector('[data-comparison-row-template]'),comparisonStatus=questionEditor.querySelector('[data-comparison-status]');
   const emptyConfig=()=>({version:1,calculation:'fronts_ratio',rows:[],reference_week:0,source:'briefing_comparison'});
   const readConfig=()=>{try{const parsed=JSON.parse(comparisonJson?.value||'{}')||{};return {...emptyConfig(),...parsed,rows:Array.isArray(parsed.rows)?parsed.rows:[]};}catch(e){return emptyConfig();}};
   const numeric=v=>{const n=parseFloat(String(v??'').replace(',','.'));return Number.isFinite(n)?Math.max(0,Math.min(100,n)):0;};
   const slug=v=>String(v||'').normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'').slice(0,120);
   const syncComparison=()=>{if(!comparisonRows||!comparisonJson)return emptyConfig();const previous=readConfig();const rows=Array.from(comparisonRows.querySelectorAll('[data-comparison-row]')).map((row,index)=>{const get=name=>row.querySelector('[data-comparison-field="'+name+'"]')?.value.trim()||'';const a=get('pr_label'),b=get('competitor_label'),metric=get('metric')||(a&&b?a+' vs '+b:'');return {metric_key:slug(metric)||'comparison_'+(index+1),metric,pr_label:a,competitor_label:b,target:numeric(get('target')),reference_share:0,reference_competitor_share:0,reference_week:Number(previous.reference_week||0)};}).filter(row=>row.pr_label&&row.competitor_label);const config={...emptyConfig(),...previous,rows};comparisonJson.value=JSON.stringify(config);if(comparisonStatus){comparisonStatus.textContent=rows.length?rows.length+' comparação'+(rows.length===1?'':'ões')+' pronta'+(rows.length===1?'':'s')+' para o formulário.':'Ainda não existem referências configuradas.';comparisonStatus.classList.toggle('is-empty',rows.length===0);}return config;};
   const addComparisonRow=(data={})=>{if(!comparisonRows||!comparisonTemplate)return;const fragment=comparisonTemplate.content.cloneNode(true),row=fragment.querySelector('.ffb-comparison-row');row.setAttribute('data-comparison-row','');['pr_label','competitor_label','target','metric'].forEach(name=>{const input=row.querySelector('[data-comparison-field="'+name+'"]');if(input)input.value=data[name]??'';});row.querySelector('[data-comparison-remove]')?.addEventListener('click',()=>{row.remove();syncComparison();});row.querySelectorAll('input').forEach(input=>input.addEventListener('input',syncComparison));comparisonRows.appendChild(fragment);};
   const renderComparison=(config)=>{if(!comparisonRows)return;comparisonRows.innerHTML='';(Array.isArray(config?.rows)?config.rows:[]).forEach(addComparisonRow);if(comparisonJson)comparisonJson.value=JSON.stringify({...emptyConfig(),...(config||{}),rows:Array.isArray(config?.rows)?config.rows:[]});syncComparison();};
   const detectComparison=(overwrite=false)=>{const detected=comparisonConfigs[String(section?.value||'')]||emptyConfig();const current=readConfig();if(!overwrite&&current.rows.length)return current;if((detected.rows||[]).length){renderComparison(detected);return detected;}if(overwrite)renderComparison(emptyConfig());if(comparisonStatus){comparisonStatus.textContent='A secção escolhida não contém pares explícitos. Adiciona as duas referências manualmente.';comparisonStatus.classList.add('is-empty');}return emptyConfig();};
   const markAnalyticsIntent=()=>{if(behaviorIntent)behaviorIntent.value='1';if(ownershipStatus)ownershipStatus.textContent='Escolha fixada manualmente. O otimizador irá respeitar esta decisão.';};
   const toggle=()=>{if(fields){fields.style.opacity=enabled&&enabled.checked?'1':'.52';fields.setAttribute('aria-disabled',enabled&&!enabled.checked?'true':'false');}const rollup=!behavior||behavior.value==='rollup';questionEditor.querySelectorAll('[data-analytics-permanent-field]').forEach(el=>{el.hidden=rollup;});const note=questionEditor.querySelector('[data-analytics-rollup-note]');if(note)note.hidden=!rollup;};
   const recommend=()=>{if(!type||!chart)return;const value=type.value;if(comparisonBox)comparisonBox.hidden=value!=='share_space_matrix';if(value==='share_space_matrix'){chart.value='table';if(behavior)behavior.value='existing';const aggregation=questionEditor.querySelector('[name=analytics_aggregation]');const dimension=questionEditor.querySelector('[name=analytics_dimension]');if(aggregation)aggregation.value='latest';if(dimension)dimension.value='location_id';const kpi=questionEditor.querySelector('[name=analytics_show_kpi][type=checkbox]');const table=questionEditor.querySelector('[name=analytics_show_table][type=checkbox]');if(kpi)kpi.checked=true;if(table)table.checked=true;detectComparison(false);}else if(value==='evidence_check'){chart.value='bar';const aggregation=questionEditor.querySelector('[name=analytics_aggregation]');if(aggregation)aggregation.value='count';}else if(['image_upload','file_upload','categorized_image_upload'].includes(value)){chart.value='image_gallery';const aggregation=questionEditor.querySelector('[name=analytics_aggregation]');if(aggregation)aggregation.value='evidence';}else if(['number','currency','percent'].includes(value)&&chart.value==='image_gallery'){chart.value='kpi';}else if(chart.value==='image_gallery'||chart.value==='table'){chart.value='bar';}};
   questionEditor.querySelector('[data-comparison-detect]')?.addEventListener('click',()=>detectComparison(true));questionEditor.querySelector('[data-comparison-add]')?.addEventListener('click',()=>{addComparisonRow({});syncComparison();});section&&section.addEventListener('change',()=>{if(type?.value==='share_space_matrix')detectComparison(true);});
   enabled&&enabled.addEventListener('change',toggle);behavior&&behavior.addEventListener('change',()=>{markAnalyticsIntent();toggle();});questionEditor.querySelectorAll('[data-analytics-permanent-field] input,[data-analytics-permanent-field] select').forEach(el=>{el.addEventListener('change',markAnalyticsIntent);if(el.matches('input[type=text],input[type=number]'))el.addEventListener('input',markAnalyticsIntent);});type&&type.addEventListener('change',recommend);questionEditor.addEventListener('submit',ev=>{if(type?.value==='share_space_matrix'&&!syncComparison().rows.length){ev.preventDefault();window.alert('Adiciona pelo menos uma comparação com Referência A e Referência B.');}});renderComparison(readConfig());recommend();toggle();
 }
 const questionBulkForm=root.querySelector('[data-question-bulk-form]');
 if(questionBulkForm){
   const all=questionBulkForm.querySelector('[data-select-all-questions]');
   const boxes=Array.from(questionBulkForm.querySelectorAll('[data-question-checkbox]'));
   const count=questionBulkForm.querySelector('[data-selected-count]');
   const update=()=>{const selected=boxes.filter(x=>x.checked).length;if(count)count.textContent=selected+' selecionada'+(selected===1?'':'s');if(all){all.checked=boxes.length>0&&selected===boxes.length;all.indeterminate=selected>0&&selected<boxes.length;}};
   all&&all.addEventListener('change',()=>{boxes.forEach(x=>x.checked=all.checked);update();});boxes.forEach(x=>x.addEventListener('change',update));update();
   questionBulkForm.querySelectorAll('[data-delete-single-question]').forEach(btn=>btn.addEventListener('click',ev=>{if(!window.confirm('Remover esta pergunta do briefing? As respostas históricas serão preservadas.'))ev.preventDefault();}));
   questionBulkForm.addEventListener('submit',ev=>{const submitter=ev.submitter;if(submitter&&submitter.matches('[data-delete-single-question]'))return;const action=questionBulkForm.querySelector('select[name="bulk_action"]')?.value||'';const selected=boxes.filter(x=>x.checked).length;if(action==='save_order'||action==='save_steps'){questionBulkForm.classList.add('is-working');return;}if(!['activate','deactivate','delete'].includes(action)||selected<1){ev.preventDefault();window.alert('Escolhe uma ação e seleciona pelo menos uma pergunta.');return;}const message=action==='delete'?'Apagar '+selected+' pergunta(s)? As respostas históricas serão preservadas.':(action==='activate'?'Ativar '+selected+' pergunta(s)?':'Desativar '+selected+' pergunta(s) sem apagar o histórico?');if(!window.confirm(message)){ev.preventDefault();return;}questionBulkForm.classList.add('is-working');});
   const perPage=questionBulkForm.querySelector('[data-question-per-page]');perPage&&perPage.addEventListener('change',()=>{const url=new URL(window.location.href);url.searchParams.set('ffb_tab','questions');url.searchParams.set('ffb_qpage','1');url.searchParams.set('ffb_qper_page',perPage.value);window.location.href=url.toString();});
 }
 root.querySelectorAll('[data-store-coverage]').forEach(details=>{
   const input=details.querySelector('[data-store-coverage-search]');
   const rows=Array.from(details.querySelectorAll('[data-store-row]'));
   const empty=details.querySelector('[data-store-coverage-empty]');
   if(!input||!rows.length)return;
   const normalize=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
   input.addEventListener('input',()=>{const term=normalize(input.value);let visible=0;rows.forEach(row=>{const match=!term||normalize(row.dataset.searchText||row.textContent).includes(term);row.hidden=!match;if(match)visible++;});if(empty)empty.hidden=visible>0;});
 });
})();
</script><?php }
}
