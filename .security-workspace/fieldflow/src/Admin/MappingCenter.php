<?php
namespace RoutesPro\Admin;

use RoutesPro\Mapping\MappingEngine;
use RoutesPro\Mapping\MappingRepository;
use RoutesPro\Mapping\MappingService;
use RoutesPro\Support\AdminPage;

if (!defined('ABSPATH')) exit;

class MappingCenter {
    public const SLUG = 'routespro-mapping-center';

    public static function register_hooks(): void {
        add_action('admin_post_fieldflow_mapping_sync', [self::class, 'handle_sync']);
        add_action('admin_post_fieldflow_mapping_suggest', [self::class, 'handle_suggest']);
        add_action('admin_post_fieldflow_mapping_bulk', [self::class, 'handle_bulk']);
        add_action('admin_post_fieldflow_mapping_save', [self::class, 'handle_save']);
        add_action('admin_post_fieldflow_mapping_undo', [self::class, 'handle_undo']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function enqueue_assets(string $hook): void {
        if (empty($_GET['page']) || sanitize_key((string)$_GET['page']) !== self::SLUG) return;
        wp_enqueue_style('fieldflow-mapping-center', ROUTESPRO_URL . 'assets/fieldflow-mapping-center.css', [], FIELDFLOW_VERSION);
        wp_enqueue_script('fieldflow-mapping-center', ROUTESPRO_URL . 'assets/fieldflow-mapping-center.js', [], FIELDFLOW_VERSION, true);
        wp_localize_script('fieldflow-mapping-center', 'FieldFlowMappingCenter', [
            'targetsEndpoint' => rest_url('fieldflow/v1/mapping/targets'),
            'nonce' => wp_create_nonce('wp_rest'),
            'searchPlaceholder' => 'Pesquisar destino...',
            'noResults' => 'Sem resultados neste âmbito.',
        ]);
    }

    public static function render(): void {
        if (!current_user_can('routespro_manage')) return;
        if (!MappingRepository::tablesReady()) {
            AdminPage::open('Centro Universal de Mapping', 'A estrutura ainda está a ser instalada.');
            echo '<div class="notice notice-error inline"><p>As tabelas do centro de mapping ainda não estão disponíveis. Desativa e volta a ativar o plugin ou abre novamente esta página.</p></div>';
            AdminPage::close();
            return;
        }

        $filters = self::filters();
        $page = max(1, absint($_GET['paged'] ?? 1));
        $perPage = 80;
        $records = MappingRepository::list($filters, $perPage, ($page - 1) * $perPage);
        $total = MappingRepository::count($filters);
        $counts = MappingRepository::counts(['client_id'=>$filters['client_id'],'project_id'=>$filters['project_id']]);
        $history = MappingRepository::history(60);
        $aliases = MappingRepository::aliases(['client_id'=>$filters['client_id'],'project_id'=>$filters['project_id'],'entity_type'=>$filters['entity_type']], 120);
        $clients = self::clients();
        $projects = self::projects((int)$filters['client_id']);
        $notice = sanitize_key((string)($_GET['ff_mapping_notice'] ?? ''));
        $noticeCount = absint($_GET['ff_mapping_count'] ?? 0);
        $noticeError = sanitize_text_field(wp_unslash((string)($_GET['ff_mapping_error'] ?? '')));

        AdminPage::open('Centro Universal de Mapping', 'Uma única fonte para correspondências de lojas, produtos, briefings, stocks, implementações e cardex.');
        self::render_notice($notice, $noticeCount, $noticeError);
        ?>
        <div class="ff-map-hero">
          <div>
            <span class="ff-map-eyebrow">FIELDFLOW DATA CONTROL</span>
            <h2>Mapping central, rastreável e reversível</h2>
            <p>Sincroniza as correspondências existentes, propõe candidatos com confiança, preserva decisões humanas e mantém histórico para desfazer alterações.</p>
          </div>
          <div class="ff-map-actions">
            <?php self::action_form('fieldflow_mapping_sync', 'Sincronizar fontes', 'button button-secondary', $filters); ?>
            <?php self::action_form('fieldflow_mapping_suggest', 'Gerar sugestões', 'button button-primary', $filters); ?>
          </div>
        </div>

        <div class="ff-map-kpis">
          <?php self::kpi('Total', $counts['total'], 'Registos agregados'); ?>
          <?php self::kpi('Pendentes', $counts['pending'], 'Sem correspondência'); ?>
          <?php self::kpi('Sugeridos', $counts['suggested'], 'Aguardam confirmação'); ?>
          <?php self::kpi('Confirmados', $counts['confirmed'], 'Fonte de verdade'); ?>
          <?php self::kpi('Lojas', $counts['store'], 'Aliases de PDV'); ?>
          <?php self::kpi('Produtos', $counts['product'], 'Stocks versus cardex'); ?>
        </div>

        <form method="get" class="ff-map-filterbar">
          <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
          <label>Cliente<select name="client_id"><option value="0">Todos</option><?php foreach($clients as $client): ?><option value="<?php echo (int)$client['id']; ?>" <?php selected($filters['client_id'],(int)$client['id']); ?>><?php echo esc_html($client['name']); ?></option><?php endforeach; ?></select></label>
          <label>Projeto<select name="project_id"><option value="0">Todos</option><?php foreach($projects as $project): ?><option value="<?php echo (int)$project['id']; ?>" <?php selected($filters['project_id'],(int)$project['id']); ?>><?php echo esc_html($project['name']); ?></option><?php endforeach; ?></select></label>
          <label>Entidade<select name="entity_type"><option value="all">Todas</option><?php foreach(MappingService::entityLabels() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filters['entity_type'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
          <label>Origem<select name="source_module"><option value="all">Todas</option><?php foreach(MappingService::sourceModules() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filters['source_module'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
          <label>Estado<select name="status"><option value="all">Todos</option><?php foreach(MappingService::statusLabels() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filters['status'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
          <label class="ff-map-search">Pesquisa<input type="search" name="search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="Loja, produto ou referência"></label>
          <button class="button">Aplicar filtros</button>
          <a class="button button-link" href="<?php echo esc_url(admin_url('admin.php?page='.self::SLUG)); ?>">Limpar</a>
        </form>

        <div class="ff-map-tabs" data-ff-map-tabs>
          <button type="button" class="active" data-tab="records">Mappings</button>
          <button type="button" data-tab="aliases">Aliases aprendidos</button>
          <button type="button" data-tab="history">Histórico e reversão</button>
        </div>

        <section class="ff-map-panel active" data-ff-map-panel="records">
          <form id="ff-map-bulk-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-ff-map-bulk-form>
            <input type="hidden" name="action" value="fieldflow_mapping_bulk">
            <?php wp_nonce_field('fieldflow_mapping_bulk'); self::hidden_filters($filters); ?>
            <div class="ff-map-bulkbar">
              <label><input type="checkbox" data-ff-map-select-all> Selecionar página</label>
              <select name="bulk_action"><option value="">Ação em massa</option><option value="approve">Confirmar sugestões</option><option value="reject">Rejeitar</option></select>
              <input type="text" name="reason" placeholder="Nota opcional para auditoria">
              <button class="button button-primary">Aplicar</button>
              <span><?php echo esc_html(number_format_i18n($total)); ?> resultado(s)</span>
            </div>
          </form>
          <div class="ff-map-table-wrap"><table class="widefat striped ff-map-table"><thead><tr><th></th><th>Origem</th><th>Elemento importado</th><th>Destino atual ou sugerido</th><th>Confiança</th><th>Estado</th><th>Ações</th></tr></thead><tbody>
          <?php if(!$records): ?><tr><td colspan="7"><div class="ff-map-empty"><strong>Sem mappings neste filtro.</strong><span>Sincroniza as fontes ou alarga os filtros.</span></div></td></tr><?php endif; ?>
          <?php foreach($records as $record): self::record_row($record); endforeach; ?>
          </tbody></table></div>
          <?php self::pagination($page, $total, $perPage, $filters); ?>
        </section>

        <section class="ff-map-panel" data-ff-map-panel="aliases">
          <div class="ff-map-card"><h2>Aliases aprendidos</h2><p>Quando confirmas uma associação, o FieldFlow guarda a designação como regra reutilizável para imports futuros no mesmo âmbito.</p>
          <div class="ff-map-table-wrap"><table class="widefat striped"><thead><tr><th>Tipo</th><th>Alias</th><th>Destino</th><th>Confiança</th><th>Utilizações</th><th>Origem</th><th>Atualizado</th></tr></thead><tbody>
          <?php if(!$aliases): ?><tr><td colspan="7">Ainda não existem aliases aprendidos neste âmbito.</td></tr><?php endif; ?>
          <?php foreach($aliases as $alias): ?><tr><td><?php echo esc_html(MappingService::entityLabels()[$alias['entity_type']]??$alias['entity_type']); ?></td><td><strong><?php echo esc_html($alias['alias_label']); ?></strong><br><code><?php echo esc_html($alias['normalized_alias']); ?></code></td><td><?php echo esc_html(MappingService::targetLabel((string)$alias['entity_type'],(int)$alias['target_id'])); ?> <small>#<?php echo (int)$alias['target_id']; ?></small></td><td><?php echo (int)$alias['confidence']; ?>%</td><td><?php echo (int)$alias['use_count']; ?></td><td><?php echo esc_html(MappingService::sourceModules()[$alias['source_module']]??$alias['source_module']); ?></td><td><?php echo esc_html(mysql2date('d/m/Y H:i',(string)$alias['updated_at'])); ?></td></tr><?php endforeach; ?>
          </tbody></table></div></div>
        </section>

        <section class="ff-map-panel" data-ff-map-panel="history">
          <div class="ff-map-card"><h2>Histórico completo</h2><p>Cada confirmação, rejeição ou reversão mantém o estado anterior e posterior. A reversão volta a aplicar a associação no módulo de origem.</p>
          <div class="ff-map-table-wrap"><table class="widefat striped"><thead><tr><th>Data</th><th>Ação</th><th>Elemento</th><th>Antes</th><th>Depois</th><th>Nota</th><th></th></tr></thead><tbody>
          <?php if(!$history): ?><tr><td colspan="7">Ainda não existem alterações registadas.</td></tr><?php endif; ?>
          <?php foreach($history as $entry): ?><tr><td><?php echo esc_html(mysql2date('d/m/Y H:i:s',(string)$entry['created_at'])); ?></td><td><span class="ff-map-status <?php echo esc_attr($entry['action_name']); ?>"><?php echo esc_html($entry['action_name']); ?></span></td><td><strong><?php echo esc_html((string)$entry['source_label']); ?></strong><br><small><?php echo esc_html((string)$entry['source_module']); ?> #<?php echo (int)$entry['mapping_id']; ?></small></td><td><?php echo esc_html(self::history_state((array)$entry['before'])); ?></td><td><?php echo esc_html(self::history_state((array)$entry['after'])); ?></td><td><?php echo esc_html((string)$entry['reason_text']); ?></td><td><?php if($entry['action_name']!=='undo'): ?><a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fieldflow_mapping_undo&history_id='.(int)$entry['id']),'fieldflow_mapping_undo_'.(int)$entry['id'])); ?>" onclick="return confirm('Repor o estado anterior deste mapping?');">Desfazer</a><?php endif; ?></td></tr><?php endforeach; ?>
          </tbody></table></div></div>
        </section>
        <?php
        AdminPage::close();
    }

    public static function handle_sync(): void {
        self::guard('fieldflow_mapping_sync');
        $filters=self::posted_filters();
        try{$result=MappingService::syncAll($filters,10000);self::redirect('synced',(int)$result['total'],$filters);}catch(\Throwable $e){self::redirect('error',0,$filters,$e->getMessage());}
    }
    public static function handle_suggest(): void {
        self::guard('fieldflow_mapping_suggest');
        $filters=self::posted_filters();unset($filters['status']);
        try{$result=MappingService::generateSuggestions($filters,500);self::redirect('suggested',(int)$result['suggested'],$filters);}catch(\Throwable $e){self::redirect('error',0,$filters,$e->getMessage());}
    }
    public static function handle_bulk(): void {
        self::guard('fieldflow_mapping_bulk');
        $filters=self::posted_filters();$ids=array_map('absint',(array)($_POST['mapping_ids']??[]));$action=sanitize_key((string)($_POST['bulk_action']??''));$reason=sanitize_text_field((string)($_POST['reason']??''));
        try{
            if($action==='approve'){$result=MappingService::approveSuggested($ids,$reason);$count=(int)$result['applied'];$notice='approved';$errors=(array)($result['errors']??[]);}
            elseif($action==='reject'){$result=MappingService::rejectMany($ids,$reason);$count=(int)$result['rejected'];$notice='rejected';$errors=(array)($result['errors']??[]);}
            else{$count=0;$notice='nothing';$errors=[];}
            self::redirect($errors?'partial':$notice,$count,$filters,$errors?implode(' | ',array_slice($errors,0,3)):'');
        }catch(\Throwable $e){self::redirect('error',0,$filters,$e->getMessage());}
    }
    public static function handle_save(): void {
        self::guard('fieldflow_mapping_save');
        $filters=self::posted_filters();$id=absint($_POST['mapping_id']??0);$target=absint($_POST['target_id']??0);$reason=sanitize_text_field((string)($_POST['reason']??''));
        try{MappingService::apply($id,$target,'confirmed',$reason!==''?$reason:'Confirmação manual');self::redirect('saved',1,$filters);}catch(\Throwable $e){self::redirect('error',0,$filters,$e->getMessage());}
    }
    public static function handle_undo(): void {
        if(!current_user_can('routespro_manage'))wp_die('Sem permissões.');$id=absint($_GET['history_id']??0);check_admin_referer('fieldflow_mapping_undo_'.$id);
        try{MappingService::undo($id);self::redirect('undone',1,[]);}catch(\Throwable $e){self::redirect('error',0,[],$e->getMessage());}
    }

    private static function record_row(array $record): void {
        $candidates=(array)($record['candidate_json']??[]);$top=$candidates[0]??[];$targetId=(int)($record['target_id']??0);$targetLabel=$targetId?MappingService::targetLabel((string)$record['entity_type'],$targetId):'';
        $sourceLabel=MappingService::sourceModules()[$record['source_module']]??$record['source_module'];$entityLabel=MappingService::entityLabels()[$record['entity_type']]??$record['entity_type'];$statusLabel=MappingService::statusLabels()[$record['status']]??$record['status'];
        ?>
        <tr data-ff-map-row>
          <td><?php if(empty($record['is_locked'])): ?><input type="checkbox" form="ff-map-bulk-form" name="mapping_ids[]" value="<?php echo (int)$record['id']; ?>" data-ff-map-checkbox><?php else: ?><span title="Registo informativo protegido">🔒</span><?php endif; ?></td>
          <td><span class="ff-map-module"><?php echo esc_html($sourceLabel); ?></span><br><small><?php echo esc_html($entityLabel); ?> · #<?php echo (int)$record['source_record_id']; ?></small></td>
          <td><strong><?php echo esc_html($record['source_label']); ?></strong><?php if($record['external_ref']): ?><br><code><?php echo esc_html($record['external_ref']); ?></code><?php endif; ?><br><small><?php echo esc_html($record['normalized_label']); ?></small></td>
          <td>
            <?php if($targetLabel): ?><div class="ff-map-target-current"><strong><?php echo esc_html($targetLabel); ?></strong><small>Destino #<?php echo $targetId; ?></small></div><?php elseif(!empty($top)): ?><div class="ff-map-target-suggested"><strong><?php echo esc_html((string)$top['label']); ?></strong><small><?php echo esc_html((string)($top['secondary']??'')); ?></small></div><?php else: ?><span class="ff-map-muted">Sem candidato</span><?php endif; ?>
            <?php if(empty($record['is_locked'])): ?>
            <form id="ff-map-manual-<?php echo (int)$record['id']; ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ff-map-inline-form" data-ff-map-manual-form>
              <input type="hidden" name="action" value="fieldflow_mapping_save"><input type="hidden" name="mapping_id" value="<?php echo (int)$record['id']; ?>"><input type="hidden" name="target_id" value="<?php echo $targetId; ?>" data-ff-map-target-id>
              <?php wp_nonce_field('fieldflow_mapping_save'); self::hidden_filters(self::filters()); ?>
              <div class="ff-map-target-picker" data-ff-map-target-picker data-entity-type="<?php echo esc_attr($record['entity_type']); ?>" data-client-id="<?php echo (int)$record['client_id']; ?>" data-project-id="<?php echo (int)$record['project_id']; ?>">
                <input type="search" data-ff-map-target-search placeholder="Pesquisar outro destino" autocomplete="off"><div class="ff-map-target-results" data-ff-map-target-results hidden></div>
              </div>
              <input type="text" name="reason" placeholder="Nota opcional"><button class="button button-small" <?php disabled(!$targetId&&!$top); ?> data-ff-map-save>Confirmar</button>
            </form>
            <?php endif; ?>
          </td>
          <td><div class="ff-map-confidence"><strong><?php echo (int)$record['confidence']; ?>%</strong><span><?php echo esc_html(MappingEngine::confidenceLabel((int)$record['confidence'])); ?></span></div><?php if(!empty($top['signals'])): ?><small><?php echo esc_html(implode(', ',array_slice((array)$top['signals'],0,3))); ?></small><?php endif; ?></td>
          <td><span class="ff-map-status <?php echo esc_attr($record['status']); ?>"><?php echo esc_html($statusLabel); ?></span></td>
          <td><?php if(empty($record['is_locked'])&&$record['status']==='suggested'): ?><button type="submit" form="ff-map-manual-<?php echo (int)$record['id']; ?>" class="button button-small button-primary">Aceitar</button><?php endif; ?><small class="ff-map-updated"><?php echo esc_html(mysql2date('d/m/Y H:i',(string)$record['updated_at'])); ?></small></td>
        </tr>
        <?php
    }

    private static function filters(): array {
        return ['client_id'=>absint($_GET['client_id']??0),'project_id'=>absint($_GET['project_id']??0),'entity_type'=>sanitize_key((string)($_GET['entity_type']??'all')),'source_module'=>sanitize_key((string)($_GET['source_module']??'all')),'status'=>sanitize_key((string)($_GET['status']??'all')),'search'=>sanitize_text_field((string)($_GET['search']??''))];
    }
    private static function posted_filters(): array {return ['client_id'=>absint($_POST['client_id']??0),'project_id'=>absint($_POST['project_id']??0),'entity_type'=>sanitize_key((string)($_POST['entity_type']??'all')),'source_module'=>sanitize_key((string)($_POST['source_module']??'all')),'status'=>sanitize_key((string)($_POST['status']??'all')),'search'=>sanitize_text_field((string)($_POST['search']??''))];}
    private static function hidden_filters(array $filters): void {foreach($filters as $key=>$value)echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr((string)$value).'">';}
    private static function action_form(string $action,string $label,string $class,array $filters): void {echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="'.esc_attr($action).'">';wp_nonce_field($action);self::hidden_filters($filters);echo '<button class="'.esc_attr($class).'">'.esc_html($label).'</button></form>';}
    private static function kpi(string $label,int $value,string $note): void {echo '<div class="ff-map-kpi"><span>'.esc_html($label).'</span><strong>'.esc_html(number_format_i18n($value)).'</strong><small>'.esc_html($note).'</small></div>';}
    private static function clients(): array {global $wpdb;return $wpdb->get_results('SELECT id,name FROM '.$wpdb->prefix.'routespro_clients ORDER BY name ASC',ARRAY_A)?:[];}
    private static function projects(int $clientId): array {global $wpdb;$sql='SELECT id,name,client_id FROM '.$wpdb->prefix.'routespro_projects';if($clientId)$sql.=$wpdb->prepare(' WHERE client_id=%d',$clientId);$sql.=' ORDER BY name ASC';return $wpdb->get_results($sql,ARRAY_A)?:[];}
    private static function guard(string $action): void {if(!current_user_can('routespro_manage'))wp_die('Sem permissões.');check_admin_referer($action);}
    private static function redirect(string $notice,int $count,array $filters,string $error=''): void {$args=['page'=>self::SLUG,'ff_mapping_notice'=>$notice,'ff_mapping_count'=>$count];if($error!=='')$args['ff_mapping_error']=substr(sanitize_text_field($error),0,240);foreach($filters as $key=>$value)if($value!==''&&$value!==0&&$value!=='all')$args[$key]=$value;wp_safe_redirect(add_query_arg($args,admin_url('admin.php')));exit;}
    private static function render_notice(string $notice,int $count,string $error=''): void {$messages=['synced'=>'Fontes sincronizadas no centro universal.','suggested'=>'Sugestões recalculadas.','approved'=>'Mappings confirmados.','rejected'=>'Mappings rejeitados.','saved'=>'Mapping confirmado e aplicado na origem.','undone'=>'Alteração revertida.','nothing'=>'Seleciona uma ação e pelo menos um registo.','partial'=>'A operação foi concluída parcialmente.','error'=>'Não foi possível concluir a operação.'];if(!$notice||empty($messages[$notice]))return;$class=in_array($notice,['nothing','partial'],true)?'notice-warning':($notice==='error'?'notice-error':'notice-success');echo '<div class="notice '.esc_attr($class).' is-dismissible"><p><strong>'.esc_html($messages[$notice]).'</strong> '.($count?esc_html(number_format_i18n($count)).' registo(s).':'').($error?' '.esc_html($error):'').'</p></div>';}
    private static function history_state(array $state): string {$status=MappingService::statusLabels()[$state['status']??'']??($state['status']??'');$target=(int)($state['target_id']??0);return trim((string)$status.($target?' · destino #'.$target:''));}
    private static function pagination(int $page,int $total,int $perPage,array $filters): void {$pages=(int)ceil($total/$perPage);if($pages<2)return;$base=add_query_arg(array_merge(['page'=>self::SLUG],array_filter($filters,static fn($v)=>$v!==''&&$v!==0&&$v!=='all')),admin_url('admin.php'));echo '<div class="tablenav"><div class="tablenav-pages">'.paginate_links(['base'=>add_query_arg('paged','%#%',$base),'format'=>'','current'=>$page,'total'=>$pages]).'</div></div>';}
}
