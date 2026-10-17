<?php
namespace RoutesPro\Admin;

use RoutesPro\Implementations\ImplementationService;
use RoutesPro\Implementations\PernodMaterialImporter;
use RoutesPro\Support\AdminPage;

if (!defined('ABSPATH')) exit;

class Implementations {
    public static function register_hooks(): void {
        add_action('admin_post_fieldflow_implementation_import', [self::class, 'handleImport']);
        add_action('admin_post_fieldflow_implementation_map', [self::class, 'handleMapping']);
        add_action('admin_post_fieldflow_implementation_document_save', [self::class, 'handleDocumentSave']);
        add_action('admin_post_fieldflow_implementation_items_save', [self::class, 'handleItemsSave']);
        add_action('admin_post_fieldflow_implementation_publish', [self::class, 'handlePublish']);
        add_action('admin_post_fieldflow_implementation_archive', [self::class, 'handleArchive']);
        add_action('admin_post_fieldflow_implementation_delete', [self::class, 'handleDelete']);
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
        $documentId = absint($_GET['implementation_id'] ?? 0);
        $documents = ImplementationService::listAdmin([
            'client_id' => $filterClient,
            'project_id' => $filterProject,
            'status' => $filterStatus,
        ]);

        AdminPage::open('Implementações de Materiais', 'Importa documentos Word, associa lojas, edita estados e publica a operação no FieldFlow.');
        self::styles();
        self::notice();
        ?>
        <div class="ffi-grid">
          <section class="ffi-card">
            <div class="ffi-head"><div><span class="ffi-kicker">Novo documento</span><h2>Importar Word operacional</h2><p>Leitura automática das notas e tabelas de implementações, sem alterar os briefings existentes.</p></div><span class="ffi-pill">DOCX</span></div>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ffi-form" data-scope-form>
              <input type="hidden" name="action" value="fieldflow_implementation_import">
              <?php wp_nonce_field('fieldflow_implementation_import'); ?>
              <label>Cliente<select name="client_id" required data-client-select><option value="">Selecionar</option><?php foreach($clients as $client): ?><option value="<?php echo (int)$client['id']; ?>"><?php echo esc_html($client['name']); ?></option><?php endforeach; ?></select></label>
              <label>Campanha<select name="project_id" required data-project-select><option value="">Selecionar</option><?php foreach($projects as $project): ?><option value="<?php echo (int)$project['id']; ?>" data-client="<?php echo (int)$project['client_id']; ?>"><?php echo esc_html($project['name']); ?></option><?php endforeach; ?></select></label>
              <label class="ffi-wide">Ficheiro Word<input type="file" name="implementation_file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required></label>
              <label class="ffi-wide">Título opcional<input type="text" name="title" placeholder="Implementações Pingo Doce, Agosto"></label>
              <label>Válido desde<input type="date" name="valid_from"></label>
              <label>Válido até<input type="date" name="valid_to"></label>
              <div class="ffi-wide"><button class="button button-primary button-hero">Importar e rever</button><p class="description">O código externo da loja é usado primeiro. Os nomes já confirmados são reutilizados nos próximos documentos.</p></div>
            </form>
          </section>

          <section class="ffi-card">
            <div class="ffi-head"><div><span class="ffi-kicker">Arquivo</span><h2>Documentos importados</h2></div><span class="ffi-pill"><?php echo count($documents); ?> registos</span></div>
            <form method="get" class="ffi-filters">
              <input type="hidden" name="page" value="routespro-implementations">
              <select name="client_id"><option value="">Todos os clientes</option><?php foreach($clients as $client): ?><option value="<?php echo (int)$client['id']; ?>" <?php selected($filterClient,(int)$client['id']); ?>><?php echo esc_html($client['name']); ?></option><?php endforeach; ?></select>
              <select name="project_id"><option value="">Todas as campanhas</option><?php foreach($projects as $project): ?><option value="<?php echo (int)$project['id']; ?>" <?php selected($filterProject,(int)$project['id']); ?>><?php echo esc_html($project['name']); ?></option><?php endforeach; ?></select>
              <select name="status"><option value="">Todos os estados</option><?php foreach(ImplementationService::documentStatusLabels() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filterStatus,$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
              <button class="button">Filtrar</button>
            </form>
            <div class="ffi-table-wrap"><table class="widefat striped"><thead><tr><th>Documento</th><th>Âmbito</th><th>Estado</th><th>Lojas</th><th>Execução</th><th>Criado</th><th>Ações</th></tr></thead><tbody>
            <?php if(!$documents): ?><tr><td colspan="7">Ainda não existem documentos de implementações.</td></tr><?php endif; ?>
            <?php foreach($documents as $document): $summary=self::summary($document); ?>
              <tr>
                <td><strong><?php echo esc_html($document['title']); ?></strong><br><small>v<?php echo (int)$document['version_no']; ?> · <?php echo esc_html($document['source_filename']); ?></small></td>
                <td><?php echo esc_html($document['client_name'] ?: 'Cliente #'.$document['client_id']); ?><br><small><?php echo esc_html($document['project_name'] ?: 'Campanha #'.$document['project_id']); ?></small></td>
                <td><span class="ffi-status <?php echo esc_attr($document['status']); ?>"><?php echo esc_html(ImplementationService::documentStatusLabels()[$document['status']] ?? $document['status']); ?></span></td>
                <td><strong><?php echo (int)($summary['mapped']??0); ?>/<?php echo (int)($summary['items']??0); ?></strong><br><small><?php echo (int)($summary['unmapped']??0); ?> por associar</small></td>
                <td><strong><?php echo (int)($summary['implemented']??0); ?> implementadas</strong><br><small><?php echo (int)($summary['blocked']??0); ?> bloqueadas</small></td>
                <td><?php echo esc_html(self::dateTimeLabel($document['created_at'] ?? '')); ?></td>
                <td><div class="ffi-row-actions"><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=routespro-implementations&implementation_id='.(int)$document['id'])); ?>">Abrir</a><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Eliminar definitivamente este documento e todas as linhas importadas?');"><input type="hidden" name="action" value="fieldflow_implementation_delete"><input type="hidden" name="implementation_id" value="<?php echo (int)$document['id']; ?>"><?php wp_nonce_field('fieldflow_implementation_delete_'.$document['id']); ?><button class="button button-small button-link-delete">Eliminar</button></form></div></td>
              </tr>
            <?php endforeach; ?>
            </tbody></table></div>
          </section>
        </div>
        <?php
        if ($documentId) self::renderDetail($documentId);
        self::scripts();
        AdminPage::close();
    }

    private static function renderDetail(int $documentId): void {
        $detail = ImplementationService::adminDetail($documentId);
        if (!$detail) return;
        $document = $detail['document'];
        $summary = self::summary($document);
        $statusLabels = ImplementationService::itemStatusLabels();
        ?>
        <section class="ffi-detail" id="ffi-detail">
          <div class="ffi-detail-hero">
            <div><span class="ffi-kicker">Versão <?php echo (int)$document['version_no']; ?> · <?php echo esc_html(ImplementationService::documentStatusLabels()[$document['status']] ?? $document['status']); ?></span><h2><?php echo esc_html($document['title']); ?></h2><p><?php echo esc_html($document['source_filename']); ?> · <?php echo esc_html(self::validityLabel($document)); ?></p></div>
            <div class="ffi-actions">
              <?php if($document['status']!=='published'): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="fieldflow_implementation_publish"><input type="hidden" name="implementation_id" value="<?php echo (int)$document['id']; ?>"><?php wp_nonce_field('fieldflow_implementation_publish_'.$document['id']); ?><button class="button button-primary">Publicar no front</button></form><?php endif; ?>
              <?php if($document['status']==='published'): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="fieldflow_implementation_archive"><input type="hidden" name="implementation_id" value="<?php echo (int)$document['id']; ?>"><?php wp_nonce_field('fieldflow_implementation_archive_'.$document['id']); ?><button class="button">Arquivar</button></form><?php endif; ?>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Eliminar definitivamente este documento?');"><input type="hidden" name="action" value="fieldflow_implementation_delete"><input type="hidden" name="implementation_id" value="<?php echo (int)$document['id']; ?>"><?php wp_nonce_field('fieldflow_implementation_delete_'.$document['id']); ?><button class="button button-link-delete">Eliminar</button></form>
            </div>
          </div>

          <div class="ffi-kpis">
            <?php self::kpi('Implementações',(int)($summary['items']??0),'Linhas importadas'); ?>
            <?php self::kpi('Lojas mapeadas',(int)($summary['mapped']??0),'Associações válidas'); ?>
            <?php self::kpi('Por associar',(int)($summary['unmapped']??0),'Revisão necessária'); ?>
            <?php self::kpi('Implementadas',(int)($summary['implemented']??0),'Execução concluída'); ?>
            <?php self::kpi('Bloqueadas',(int)($summary['blocked']??0),'Stock ou pedido'); ?>
            <?php self::kpi('Material em falta',(int)($summary['materials_missing']??0),'Quantidade registada'); ?>
          </div>

          <div class="ffi-tabs"><button type="button" class="active" data-ffi-tab="overview">Documento</button><button type="button" data-ffi-tab="mapping">Lojas e códigos</button><button type="button" data-ffi-tab="items">Editar implementações</button><button type="button" data-ffi-tab="source">Leitura importada</button></div>

          <div class="ffi-tab-panel active" data-ffi-panel="overview">
            <div class="ffi-card">
              <div class="ffi-head"><div><span class="ffi-kicker">Configuração</span><h2>Título, validade e notas operacionais</h2></div><span class="ffi-pill">Editável</span></div>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ffi-form">
                <input type="hidden" name="action" value="fieldflow_implementation_document_save"><input type="hidden" name="implementation_id" value="<?php echo (int)$document['id']; ?>"><?php wp_nonce_field('fieldflow_implementation_document_save_'.$document['id']); ?>
                <label class="ffi-wide">Título<input type="text" name="title" value="<?php echo esc_attr($document['title']); ?>" required></label>
                <label>Válido desde<input type="date" name="valid_from" value="<?php echo esc_attr($document['valid_from'] ?: ''); ?>"></label>
                <label>Válido até<input type="date" name="valid_to" value="<?php echo esc_attr($document['valid_to'] ?: ''); ?>"></label>
                <label class="ffi-wide">Notas operacionais<textarea name="notes_text" rows="10"><?php echo esc_textarea($document['notes_text']); ?></textarea></label>
                <div class="ffi-wide"><button class="button button-primary">Guardar documento</button></div>
              </form>
            </div>
          </div>

          <div class="ffi-tab-panel" data-ffi-panel="mapping">
            <div class="ffi-card">
              <div class="ffi-head"><div><span class="ffi-kicker">Mapa permanente</span><h2>Associar códigos e nomes às lojas FieldFlow</h2><p>O código externo é prioritário. A escolha fica memorizada para os próximos Word.</p></div><span class="ffi-pill"><?php echo count($detail['aliases']); ?> referências</span></div>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fieldflow_implementation_map"><input type="hidden" name="implementation_id" value="<?php echo (int)$document['id']; ?>"><?php wp_nonce_field('fieldflow_implementation_map_'.$document['id']); ?>
                <datalist id="ffi-location-list-<?php echo (int)$document['id']; ?>"><?php foreach($detail['locations'] as $location): ?><option value="<?php echo (int)$location['id']; ?> | <?php echo esc_attr($location['name']); ?><?php echo !empty($location['city'])?' · '.esc_attr($location['city']):''; ?>"></option><?php endforeach; ?></datalist>
                <div class="ffi-mapping-list">
                  <?php if(!$detail['aliases']): ?><p>Não existem referências para associar.</p><?php endif; ?>
                  <?php foreach($detail['aliases'] as $alias): $current=(int)($alias['location_id']??0); ?>
                    <article class="ffi-map-row <?php echo $current?'is-mapped':'is-unmapped'; ?>">
                      <div><span><?php echo $alias['external_store_code']!==''?'Código '.esc_html($alias['external_store_code']):'Sem código'; ?></span><strong><?php echo esc_html($alias['external_name']); ?></strong><small><?php echo (int)$alias['item_count']; ?> implementação(ões)</small></div>
                      <div class="ffi-picker"><input type="search" list="ffi-location-list-<?php echo (int)$document['id']; ?>" data-location-picker placeholder="Pesquisar por loja" value="<?php echo $current?esc_attr($current.' | '.($alias['location_name']?:'Loja #'.$current)):''; ?>"><input type="hidden" name="mapping[<?php echo (int)$alias['id']; ?>]" value="<?php echo $current; ?>" data-location-value><button type="button" class="button" data-clear-location>Limpar</button></div>
                    </article>
                  <?php endforeach; ?>
                </div>
                <p><button class="button button-primary">Guardar associações</button></p>
              </form>
            </div>
          </div>

          <div class="ffi-tab-panel" data-ffi-panel="items">
            <div class="ffi-card">
              <div class="ffi-head"><div><span class="ffi-kicker">Autonomia operacional</span><h2>Editar estados, datas e materiais</h2><p>As alterações ficam disponíveis no front após publicação, sem voltar a importar o Word.</p></div><span class="ffi-pill"><?php echo count($detail['items']); ?> linhas</span></div>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fieldflow_implementation_items_save"><input type="hidden" name="implementation_id" value="<?php echo (int)$document['id']; ?>"><?php wp_nonce_field('fieldflow_implementation_items_save_'.$document['id']); ?>
                <?php foreach($detail['sections'] as $section): $sectionItems=array_values(array_filter($detail['items'],static fn($item)=>(int)$item['section_id']===(int)$section['id'])); ?>
                  <div class="ffi-section-editor">
                    <div class="ffi-section-title"><div><span><?php echo esc_html($section['section_key']); ?></span><h3><?php echo esc_html($section['title']); ?></h3><p><?php echo esc_html($section['summary_text']); ?></p></div><b><?php echo count($sectionItems); ?> lojas</b></div>
                    <div class="ffi-item-grid">
                    <?php foreach($sectionItems as $item): ?>
                      <article class="ffi-item-editor">
                        <header><div><span><?php echo $item['external_store_code']!==''?'Código '.esc_html($item['external_store_code']):'Sem código'; ?></span><h4><?php echo esc_html($item['external_store_name']); ?></h4><small><?php echo esc_html($item['location_name'] ?: 'Por associar'); ?></small></div><span class="ffi-item-status <?php echo esc_attr($item['status_key']); ?>"><?php echo esc_html($statusLabels[$item['status_key']] ?? $item['status_label']); ?></span></header>
                        <div class="ffi-item-fields">
                          <label>Sales Rep<input type="text" name="items[<?php echo (int)$item['id']; ?>][sales_rep]" value="<?php echo esc_attr($item['sales_rep']); ?>"></label>
                          <label>Tipo de implementação<input type="text" name="items[<?php echo (int)$item['id']; ?>][implementation_type]" value="<?php echo esc_attr($item['implementation_type']); ?>"></label>
                          <label>Data prevista<input type="date" name="items[<?php echo (int)$item['id']; ?>][planned_date]" value="<?php echo esc_attr($item['planned_date'] ?: ''); ?>"></label>
                          <label>Texto de previsão<input type="text" name="items[<?php echo (int)$item['id']; ?>][planned_date_text]" value="<?php echo esc_attr($item['planned_date_text']); ?>"></label>
                          <label class="ffi-wide">Estado<input type="text" name="items[<?php echo (int)$item['id']; ?>][status_label]" value="<?php echo esc_attr($item['status_label']); ?>" list="ffi-status-list"></label>
                          <label class="ffi-wide">Observações<textarea name="items[<?php echo (int)$item['id']; ?>][observations]" rows="3"><?php echo esc_textarea($item['observations']); ?></textarea></label>
                          <label>Propostos<input type="number" min="0" name="items[<?php echo (int)$item['id']; ?>][materials_proposed]" value="<?php echo (int)$item['materials_proposed']; ?>"></label>
                          <label>Em falta<input type="number" min="0" name="items[<?php echo (int)$item['id']; ?>][materials_missing]" value="<?php echo (int)$item['materials_missing']; ?>"></label>
                          <label>Implementados<input type="number" min="0" name="items[<?php echo (int)$item['id']; ?>][materials_implemented]" value="<?php echo (int)$item['materials_implemented']; ?>"></label>
                        </div>
                      </article>
                    <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
                <datalist id="ffi-status-list"><option value="Implementado"><option value="Aguarda"><option value="Enviado CS"><option value="Sem stock para implementar"><option value="Não recebeu pedido"><option value="Encomenda feita, agendar"><option value="Sem feedback"></datalist>
                <div class="ffi-sticky-save"><button class="button button-primary button-hero">Guardar todas as implementações</button></div>
              </form>
            </div>
          </div>

          <div class="ffi-tab-panel" data-ffi-panel="source">
            <div class="ffi-card"><div class="ffi-head"><div><span class="ffi-kicker">Leitura automática</span><h2>Conteúdo reconhecido no Word</h2></div><span class="ffi-pill"><?php echo count($detail['sections']); ?> blocos</span></div>
              <?php foreach($detail['sections'] as $section): $sectionItems=array_values(array_filter($detail['items'],static fn($item)=>(int)$item['section_id']===(int)$section['id'])); ?>
                <div class="ffi-source-section"><h3><?php echo esc_html($section['title']); ?></h3><p><?php echo esc_html($section['summary_text']); ?></p><div class="ffi-table-wrap"><table class="widefat striped"><thead><tr><th>Código</th><th>Loja Word</th><th>Loja FieldFlow</th><th>Implementação</th><th>Previsão</th><th>Estado</th><th>Materiais</th><th>Observações</th></tr></thead><tbody><?php foreach($sectionItems as $item): ?><tr><td><?php echo esc_html($item['external_store_code']); ?></td><td><strong><?php echo esc_html($item['external_store_name']); ?></strong></td><td><?php echo esc_html($item['location_name'] ?: 'Por associar'); ?></td><td><?php echo esc_html($item['implementation_type']); ?></td><td><?php echo esc_html($item['planned_date'] ?: $item['planned_date_text']); ?></td><td><span class="ffi-item-status <?php echo esc_attr($item['status_key']); ?>"><?php echo esc_html($item['status_label']); ?></span></td><td><?php echo (int)$item['materials_implemented']; ?> impl. · <?php echo (int)$item['materials_missing']; ?> falta</td><td><?php echo esc_html($item['observations']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
        <?php
    }

    public static function handleImport(): void {
        self::guard();
        check_admin_referer('fieldflow_implementation_import');
        try {
            $file = $_FILES['implementation_file'] ?? [];
            if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) throw new \RuntimeException('Seleciona um ficheiro DOCX válido.');
            if (!empty($file['error'])) throw new \RuntimeException('O upload do ficheiro falhou.');
            if ((int)($file['size'] ?? 0) > 30 * 1024 * 1024) throw new \RuntimeException('O ficheiro excede o limite de 30 MB.');
            $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
            if ($ext !== 'docx') throw new \RuntimeException('Este importador aceita ficheiros DOCX.');
            $parsed = PernodMaterialImporter::parse($file['tmp_name']);
            $id = ImplementationService::importParsed($parsed, [
                'client_id' => absint($_POST['client_id'] ?? 0),
                'project_id' => absint($_POST['project_id'] ?? 0),
                'title' => sanitize_text_field((string)($_POST['title'] ?? '')),
                'valid_from' => sanitize_text_field((string)($_POST['valid_from'] ?? '')),
                'valid_to' => sanitize_text_field((string)($_POST['valid_to'] ?? '')),
            ], [
                'name' => (string)($file['name'] ?? 'implementacoes.docx'),
                'hash' => hash_file('sha256', $file['tmp_name']),
            ]);
            self::redirect($id, 'imported');
        } catch (\Throwable $e) {
            self::redirect(0, 'error', $e->getMessage());
        }
    }

    public static function handleMapping(): void {
        self::guard();
        $id = absint($_POST['implementation_id'] ?? 0);
        check_admin_referer('fieldflow_implementation_map_'.$id);
        try {
            ImplementationService::saveMappings($id, (array)($_POST['mapping'] ?? []));
            self::redirect($id, 'mapped');
        } catch (\Throwable $e) { self::redirect($id, 'error', $e->getMessage()); }
    }

    public static function handleDocumentSave(): void {
        self::guard();
        $id = absint($_POST['implementation_id'] ?? 0);
        check_admin_referer('fieldflow_implementation_document_save_'.$id);
        try {
            ImplementationService::updateDocument($id, wp_unslash((array)$_POST));
            self::redirect($id, 'document_saved');
        } catch (\Throwable $e) { self::redirect($id, 'error', $e->getMessage()); }
    }

    public static function handleItemsSave(): void {
        self::guard();
        $id = absint($_POST['implementation_id'] ?? 0);
        check_admin_referer('fieldflow_implementation_items_save_'.$id);
        try {
            $count = ImplementationService::updateItems($id, wp_unslash((array)($_POST['items'] ?? [])));
            self::redirect($id, 'items_saved', (string)$count);
        } catch (\Throwable $e) { self::redirect($id, 'error', $e->getMessage()); }
    }

    public static function handlePublish(): void {
        self::guard();
        $id = absint($_POST['implementation_id'] ?? 0);
        check_admin_referer('fieldflow_implementation_publish_'.$id);
        try { ImplementationService::publish($id); self::redirect($id, 'published'); }
        catch (\Throwable $e) { self::redirect($id, 'error', $e->getMessage()); }
    }

    public static function handleArchive(): void {
        self::guard();
        $id = absint($_POST['implementation_id'] ?? 0);
        check_admin_referer('fieldflow_implementation_archive_'.$id);
        try { ImplementationService::archive($id); self::redirect($id, 'archived'); }
        catch (\Throwable $e) { self::redirect($id, 'error', $e->getMessage()); }
    }

    public static function handleDelete(): void {
        self::guard();
        $id = absint($_POST['implementation_id'] ?? 0);
        check_admin_referer('fieldflow_implementation_delete_'.$id);
        try { ImplementationService::delete($id); self::redirect(0, 'deleted'); }
        catch (\Throwable $e) { self::redirect($id, 'error', $e->getMessage()); }
    }

    private static function guard(): void { if (!current_user_can('routespro_manage')) wp_die('Sem permissões.'); }

    private static function redirect(int $id, string $notice, string $message = ''): void {
        $args = ['page' => 'routespro-implementations', 'ffi_notice' => $notice];
        if ($id) $args['implementation_id'] = $id;
        if ($message !== '') $args['ffi_message'] = rawurlencode($message);
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private static function notice(): void {
        $key = sanitize_key((string)($_GET['ffi_notice'] ?? ''));
        if ($key === '') return;
        $messages = [
            'imported' => 'Word importado. Revê as lojas, ajusta os estados e publica quando estiver pronto.',
            'mapped' => 'Associações guardadas. Serão reutilizadas nos próximos documentos.',
            'document_saved' => 'Documento e notas operacionais atualizados.',
            'items_saved' => absint($_GET['ffi_message'] ?? 0) . ' implementação(ões) atualizadas.',
            'published' => 'Implementações publicadas no front.',
            'archived' => 'Documento arquivado.',
            'deleted' => 'Documento eliminado.',
        ];
        $text = $key === 'error' ? sanitize_text_field(rawurldecode((string)($_GET['ffi_message'] ?? 'Ocorreu um erro.'))) : ($messages[$key] ?? 'Operação concluída.');
        echo '<div class="notice '.($key==='error'?'notice-error':'notice-success').' is-dismissible"><p>'.esc_html($text).'</p></div>';
    }

    private static function summary(array $document): array {
        $summary = json_decode((string)($document['import_summary_json'] ?? ''), true);
        return is_array($summary) ? $summary : [];
    }

    private static function kpi(string $label, $value, string $note): void {
        echo '<div class="ffi-kpi"><span>'.esc_html($label).'</span><strong>'.esc_html((string)$value).'</strong><small>'.esc_html($note).'</small></div>';
    }

    private static function dateTimeLabel($value): string { return $value ? date_i18n('d/m/Y H:i', strtotime((string)$value)) : 'Ainda não'; }
    private static function validityLabel(array $document): string {
        if (empty($document['valid_from']) && empty($document['valid_to'])) return 'Sem janela de validade';
        return ($document['valid_from'] ? date_i18n('d/m/Y', strtotime($document['valid_from'])) : 'Início livre') . ' a ' . ($document['valid_to'] ? date_i18n('d/m/Y', strtotime($document['valid_to'])) : 'sem fim');
    }

    private static function styles(): void { ?>
<style>
.ffi-grid{display:grid;grid-template-columns:minmax(340px,.85fr) minmax(0,1.45fr);gap:18px;margin-top:18px}.ffi-card{background:#fff;border:1px solid #dbe5ef;border-radius:20px;padding:20px;box-shadow:0 16px 38px rgba(15,23,42,.06)}.ffi-head,.ffi-detail-hero,.ffi-section-title,.ffi-item-editor header{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.ffi-head h2,.ffi-detail-hero h2,.ffi-section-title h3,.ffi-item-editor h4{margin:3px 0 5px}.ffi-head p,.ffi-detail-hero p,.ffi-section-title p{margin:0;color:#64748b}.ffi-kicker{display:block;color:#2563eb;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.ffi-pill,.ffi-item-status,.ffi-status{display:inline-flex;align-items:center;border-radius:999px;padding:6px 10px;font-size:11px;font-weight:800;white-space:nowrap;background:#e0f2fe;color:#075985}.ffi-status.published,.ffi-item-status.implemented{background:#dcfce7;color:#166534}.ffi-status.archived{background:#e2e8f0;color:#475569}.ffi-status.draft,.ffi-item-status.pending,.ffi-item-status.awaiting{background:#fef3c7;color:#92400e}.ffi-item-status.stock_blocked,.ffi-item-status.order_pending{background:#fee2e2;color:#991b1b}.ffi-item-status.scheduled,.ffi-item-status.sent{background:#dbeafe;color:#1d4ed8}.ffi-item-status.no_feedback,.ffi-item-status.other{background:#f1f5f9;color:#475569}.ffi-form{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:18px}.ffi-form label,.ffi-item-fields label{display:grid;gap:6px;font-weight:700;color:#334155}.ffi-form input,.ffi-form select,.ffi-form textarea,.ffi-item-fields input,.ffi-item-fields textarea{width:100%;max-width:none;border:1px solid #cbd5e1;border-radius:10px;padding:9px 10px}.ffi-wide{grid-column:1/-1}.ffi-filters{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0}.ffi-table-wrap{overflow:auto}.ffi-table-wrap table{min-width:860px}.ffi-row-actions,.ffi-actions{display:flex;gap:7px;flex-wrap:wrap;align-items:center}.ffi-row-actions form,.ffi-actions form{margin:0}.ffi-detail{margin-top:22px}.ffi-detail-hero{padding:24px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#1d4ed8);color:#fff}.ffi-detail-hero p{color:rgba(255,255,255,.78)}.ffi-detail-hero .ffi-kicker{color:#bfdbfe}.ffi-kpis{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:10px;margin:14px 0}.ffi-kpi{background:#fff;border:1px solid #dbe5ef;border-radius:16px;padding:15px}.ffi-kpi span,.ffi-kpi small{display:block;color:#64748b}.ffi-kpi strong{display:block;font-size:24px;margin:4px 0}.ffi-tabs{display:flex;gap:7px;overflow:auto;margin-bottom:12px}.ffi-tabs button{border:1px solid #cbd5e1;background:#fff;border-radius:999px;padding:9px 14px;font-weight:800;cursor:pointer;white-space:nowrap}.ffi-tabs button.active{background:#0f172a;color:#fff;border-color:#0f172a}.ffi-tab-panel{display:none}.ffi-tab-panel.active{display:block}.ffi-mapping-list{display:grid;gap:10px;margin:16px 0}.ffi-map-row{display:grid;grid-template-columns:minmax(240px,.65fr) minmax(320px,1fr);gap:16px;padding:14px;border:1px solid #e2e8f0;border-radius:15px;background:#f8fafc}.ffi-map-row.is-unmapped{border-color:#fecaca;background:#fff7f7}.ffi-map-row span,.ffi-map-row small,.ffi-item-editor header span,.ffi-item-editor header small{display:block;color:#64748b}.ffi-map-row strong{display:block;font-size:15px;margin:4px 0}.ffi-picker{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center}.ffi-picker input[type=search]{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:9px}.ffi-section-editor{margin-top:20px;border-top:1px solid #e2e8f0;padding-top:20px}.ffi-section-title b{background:#0f172a;color:#fff;border-radius:999px;padding:6px 10px}.ffi-item-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}.ffi-item-editor{border:1px solid #dbe5ef;border-radius:18px;padding:15px;background:#f8fafc}.ffi-item-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:14px}.ffi-sticky-save{position:sticky;bottom:12px;margin-top:16px;padding:12px;border:1px solid #bfdbfe;border-radius:14px;background:rgba(239,246,255,.96);backdrop-filter:blur(8px)}.ffi-source-section{margin-top:18px;padding-top:18px;border-top:1px solid #e2e8f0}.ffi-source-section h3{margin:0 0 5px}@media(max-width:1180px){.ffi-grid{grid-template-columns:1fr}.ffi-kpis{grid-template-columns:repeat(3,1fr)}}@media(max-width:780px){.ffi-kpis{grid-template-columns:repeat(2,1fr)}.ffi-item-grid{grid-template-columns:1fr}.ffi-map-row{grid-template-columns:1fr}.ffi-form,.ffi-item-fields{grid-template-columns:1fr}.ffi-wide{grid-column:auto}.ffi-detail-hero{flex-direction:column}}
</style><?php }

    private static function scripts(): void { ?>
<script>
(function(){
  document.querySelectorAll('[data-scope-form]').forEach(form=>{const client=form.querySelector('[data-client-select]'),project=form.querySelector('[data-project-select]');if(!client||!project)return;const filter=()=>{const cid=client.value;Array.from(project.options).forEach(opt=>{if(!opt.value)return;opt.hidden=!!cid&&opt.dataset.client!==cid;if(opt.hidden&&opt.selected)project.value='';});};client.addEventListener('change',filter);filter();});
  const detail=document.getElementById('ffi-detail');if(!detail)return;
  detail.querySelectorAll('[data-ffi-tab]').forEach(btn=>btn.addEventListener('click',()=>{const key=btn.dataset.ffiTab;detail.querySelectorAll('[data-ffi-tab]').forEach(x=>x.classList.toggle('active',x===btn));detail.querySelectorAll('[data-ffi-panel]').forEach(x=>x.classList.toggle('active',x.dataset.ffiPanel===key));try{sessionStorage.setItem('ffi_admin_tab',key)}catch(e){}}));
  try{const saved=sessionStorage.getItem('ffi_admin_tab');if(saved)detail.querySelector('[data-ffi-tab="'+saved+'"]')?.click();}catch(e){}
  detail.querySelectorAll('[data-location-picker]').forEach(input=>{const row=input.closest('.ffi-picker'),hidden=row?.querySelector('[data-location-value]'),clear=row?.querySelector('[data-clear-location]');const sync=()=>{const match=String(input.value||'').match(/^\s*(\d+)\s*\|/);if(hidden)hidden.value=match?match[1]:'';};input.addEventListener('input',sync);input.addEventListener('change',sync);clear?.addEventListener('click',()=>{input.value='';if(hidden)hidden.value='';input.focus();});});
})();
</script><?php }
}
