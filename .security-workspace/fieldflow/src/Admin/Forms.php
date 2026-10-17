<?php
namespace RoutesPro\Admin;
use RoutesPro\Forms\Forms as FormsModule;
use RoutesPro\Forms\ProductCardex;
use RoutesPro\Forms\ConditionalGraphIntegrity;
if (!defined('ABSPATH')) exit;
class Forms {
    public static function register_hooks() {
        add_action('admin_post_routespro_save_form', [self::class, 'handle_save']);
        add_action('admin_post_routespro_delete_form', [self::class, 'handle_delete']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }
    public static function enqueue_assets($hook) {
        if (empty($_GET['page']) || !in_array($_GET['page'], ['routespro-forms','routespro-form-edit'], true)) return;
        wp_enqueue_style('routespro-form-builder', ROUTESPRO_URL . 'assets/routespro-form-builder.css', [], ROUTESPRO_VERSION); wp_enqueue_script('jquery-ui-sortable'); wp_enqueue_media(); wp_enqueue_script('routespro-form-builder', ROUTESPRO_URL . 'assets/routespro-form-builder.js', ['jquery','jquery-ui-sortable'], ROUTESPRO_VERSION, true); if (class_exists('\\RoutesPro\\Forms\\ProductCardex')) { wp_localize_script('routespro-form-builder', 'RoutesProCardex', ['items'=>ProductCardex::list_cardex()]); }
    }
    public static function render() {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.'); $rows = FormsModule::list_forms(); echo '<div class="wrap">'; Branding::render_header('Formulários', 'Fase 1, motor interno de formulários dinâmicos já integrado no FieldFlow.'); echo '<p><a href="'.esc_url(admin_url('admin.php?page=routespro-form-edit')).'" class="button button-primary">Novo formulário</a></p>'; echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Título</th><th>Estado</th><th>Shortcode</th><th>Atualizado</th><th>Ações</th></tr></thead><tbody>'; if(!$rows){ echo '<tr><td colspan="6">Ainda não existem formulários.</td></tr>'; } else { foreach($rows as $row){ $edit=admin_url('admin.php?page=routespro-form-edit&id='.(int)$row['id']); $del=wp_nonce_url(admin_url('admin-post.php?action=routespro_delete_form&id='.(int)$row['id']), 'routespro_delete_form_'.(int)$row['id']); echo '<tr><td>'.(int)$row['id'].'</td><td><strong><a href="'.esc_url($edit).'">'.esc_html($row['title']).'</a></strong></td><td>'.esc_html($row['status']).'</td><td><code>[fieldflow_form id="'.(int)$row['id'].'"]</code></td><td>'.esc_html($row['updated_at']).'</td><td><a class="button button-small" href="'.esc_url($edit).'">Editar</a> <a class="button button-small" href="'.esc_url($del).'" onclick="return confirm(\'Apagar formulário?\')">Apagar</a></td></tr>'; } } echo '</tbody></table></div>';
    }
    public static function render_edit() {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.'); $id = absint($_GET['id'] ?? 0); $form = $id ? FormsModule::get_form($id) : null; $schema = FormsModule::decode_schema($form['schema_json'] ?? ''); $integrity = ConditionalGraphIntegrity::analyse($schema); $theme = FormsModule::decode_json_array($form['theme_json'] ?? ''); $theme = array_merge(['primary'=>'#2271b1','primary_hover'=>'#135e96','radius'=>12], $theme); echo '<div class="wrap">'; Branding::render_header($id ? 'Editar formulário' : 'Novo formulário', 'Builder interno, preparado para a fase 1 sem mexer no core das rotas.'); if(isset($_GET['saved'])) echo '<div class="notice notice-success"><p>Formulário guardado.</p></div>'; if (!empty($integrity['warning_count'])) echo '<div class="notice notice-warning"><p><strong>Auditoria condicional:</strong> existem '.(int)$integrity['warning_count'].' ligação(ões) a rever. O builder identifica abaixo as perguntas controladas e as colisões funcionais suspeitas, sem bloquear a gravação.</p></div>'; echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="routespro_save_form"><input type="hidden" name="id" value="'.(int)$id.'">'; wp_nonce_field('routespro_save_form', 'routespro_save_form_nonce'); echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="routespro_form_title">Título</label></th><td><input type="text" class="regular-text" id="routespro_form_title" name="title" value="'.esc_attr($form['title'] ?? '').'" required></td></tr><tr><th scope="row"><label for="routespro_form_status">Estado</label></th><td><select name="status" id="routespro_form_status"><option value="active"'.selected(($form['status'] ?? 'active'), 'active', false).'>Activo</option><option value="inactive"'.selected(($form['status'] ?? 'active'), 'inactive', false).'>Inactivo</option></select></td></tr><tr><th scope="row">Tema</th><td><label>Cor primária <input type="color" name="theme_primary" value="'.esc_attr($theme['primary']).'"></label> <label style="margin-left:12px">Hover <input type="color" name="theme_primary_hover" value="'.esc_attr($theme['primary_hover']).'"></label> <label style="margin-left:12px">Radius <input type="number" min="0" max="30" name="theme_radius" value="'.esc_attr((string)$theme['radius']).'" style="width:80px"></label></td></tr></tbody></table>';
        $encoded = wp_json_encode($schema, JSON_UNESCAPED_UNICODE); echo '<div id="routespro-form-builder-admin" class="twt-tcrm-admin twt-tcrm-form-builder"><div class="twt-fb-meta"><div class="twt-fb-row"><label><strong>Título interno do schema</strong></label><input type="text" class="twt-fb-input" data-fb-meta="title" value="'.esc_attr($schema['meta']['title'] ?? '').'" placeholder="Ex, Report Loja"></div><div class="twt-fb-row"><label><strong>Subtítulo</strong></label><input type="text" class="twt-fb-input" data-fb-meta="subtitle" value="'.esc_attr($schema['meta']['subtitle'] ?? '').'" placeholder="Ex, Semana 3, Norte"></div></div><hr style="margin:14px 0;"><div class="twt-fb-toolbar"><button type="button" class="button button-primary" id="twt-fb-add">Adicionar pergunta</button><span class="twt-fb-hint">Arrasta para reordenar. Guarda para aplicar.</span></div><div class="twt-fb-layout"><div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between;"><div><strong>Layout</strong><div class="twt-fb-small">Configura steps, progresso e largura por campo.</div></div><div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;"><label style="display:inline-flex;align-items:center;gap:8px;"><input type="checkbox" data-fb-layout-mode> <span>Wizard, Steps</span></label><label style="display:inline-flex;align-items:center;gap:8px;"><input type="checkbox" data-fb-layout-progress> <span>Mostrar progresso</span></label><button type="button" class="button" data-fb-add-step>Adicionar step</button></div></div><div class="twt-fb-steps"></div></div><div id="twt-fb-list" class="twt-fb-list"></div><input type="hidden" id="twt_form_schema_json" name="schema_json" value="'.esc_attr($encoded).'"><script type="text/template" id="twt-fb-item-tpl"><div class="twt-fb-item" data-id="{{id}}"><div class="twt-fb-head"><span class="twt-fb-drag" title="Arrastar">::</span><strong class="twt-fb-label">{{label}}</strong><span class="twt-fb-type">{{type}}</span><button type="button" class="button twt-fb-toggle">Fechar</button><button type="button" class="button-link-delete twt-fb-del">Apagar</button></div><div class="twt-fb-body"><div class="twt-fb-grid"><div class="twt-fb-row"><label>Label</label><input type="text" data-fb="label" value="{{label}}"></div><div class="twt-fb-row"><label>Key</label><input type="text" data-fb="key" value="{{key}}" placeholder="auto, se vazio"><div class="twt-fb-small">Sem espaços, minúsculas.</div></div><div class="twt-fb-row"><label>Tipo</label><select data-fb="type" class="twt-fb-type-select"><option value="text">Texto</option><option value="textarea">Texto longo</option><option value="number">Número</option><option value="currency">Euro</option><option value="percent">Percentagem</option><option value="date">Data</option><option value="time">Hora</option><option value="checkbox">Checkbox</option><option value="select">Selecção</option><option value="radio">Radio</option><option value="visual_choice">Seleção visual com imagens</option><option value="categorized_image_upload">Upload de imagens por categoria</option><option value="evidence_check">Conformidade + evidência</option><option value="image_upload">Upload imagem</option><option value="file_upload">Upload ficheiro</option><option value="product_matrix">Tabela de produtos / referências</option><option value="order_suggestion">Sugestão de encomenda</option><option value="briefing_block">Bloco de Briefing</option></select></div><div class="twt-fb-preview" data-fb-preview></div><div class="twt-fb-row"><label>Obrigatório</label><label class="twt-fb-check"><input type="checkbox" data-fb="required" {{required}}><span>Sim</span></label></div><div class="twt-fb-row"><label>Memória da visita</label><label class="twt-fb-check"><input type="checkbox" data-fb="show_previous_on_start" {{show_previous_on_start}}><span>Mostrar resposta anterior no início</span></label><div class="twt-fb-small">Na visita seguinte, apresenta esta resposta num aviso informativo e deixa o campo atual livre para uma nova resposta.</div></div></div><div class="twt-fb-row"><label>Ajuda</label><input type="text" data-fb="help_text" value="{{help_text}}"></div><div class="twt-fb-row twt-fb-briefing-settings" style="display:none"><div class="twt-fb-briefing-box"><strong>Integração do briefing</strong><div class="twt-fb-small">Este marcador não grava uma resposta. No front é substituído pelas perguntas aprovadas do briefing para a loja associada à pergunta, exatamente nesta posição.</div><label style="margin-top:10px">Briefings a mostrar</label><select data-fb="briefing_source"><option value="active_latest">Apenas o briefing ativo mais recente</option><option value="all_active">Todos os briefings ativos aplicáveis</option></select><label class="twt-fb-check" style="margin-top:10px"><input type="checkbox" data-fb="briefing_show_context"><span>Mostrar cabeçalho, semana e validade</span></label><label style="margin-top:10px">Máximo de perguntas</label><input type="number" min="1" max="100" data-fb="briefing_question_limit" value="30"><div class="twt-fb-small">As perguntas aparecem apenas nas lojas associadas a cada pergunta e durante a validade do briefing.</div></div></div><div class="twt-fb-row twt-fb-options"><label data-fb-options-label>Opções, uma por linha</label><textarea data-fb="options" rows="4">{{options_text}}</textarea><div class="twt-fb-small" data-fb-options-help></div></div><div class="twt-fb-row twt-fb-choice-settings"><label>Modo de seleção</label><select data-fb="selection_mode"><option value="single">Selecionar apenas uma opção</option><option value="multiple">Selecionar várias opções</option></select><div class="twt-fb-small">No modo múltiplo, as opções aparecem como checkboxes claros e adaptados a mobile.</div></div><div class="twt-fb-row twt-fb-visual-settings" style="display:none"><label>Opções visuais</label><div class="twt-fb-visual-options" data-fb-visual-options-list></div><button type="button" class="button" data-fb-add-visual-option>Adicionar opção visual</button><div class="twt-fb-small">Cada opção pode ter uma imagem da biblioteca multimédia. No front aparece como cartão selecionável, adaptado a desktop, tablet e mobile.</div></div><div class="twt-fb-row twt-fb-products"><label>Origem dos produtos</label><select data-fb="product_source"><option value="manual">Lista manual nesta pergunta</option><option value="cardex_fixed">Cardex fixo</option><option value="cardex_auto">Cardex automático pela loja</option></select><div class="twt-fb-cardex-fixed-picker"><label style="margin-top:8px">Cardex fixo</label><select data-fb="cardex_id"><option value="0">Sem cardex</option></select></div><div class="twt-fb-auto-cardex-note" style="display:none;border:1px solid #ead7b5;background:#fffbf3;padding:10px 12px;border-radius:10px;margin:10px 0"><strong>Cardex automático pela loja</strong><div class="twt-fb-small">O FieldFlow usa a loja, o cliente e o projeto da visita para resolver o sortido. O Cardex fixo anteriormente guardado não é utilizado neste modo.</div><a class="button button-small" target="_blank" rel="noopener" href="'.esc_url(admin_url('admin.php?page=routespro-product-cardex&consistency=1')).'">Verificar sortido por PDV no Centro de Consistência</a></div><label class="twt-fb-check" style="margin-top:10px"><input type="checkbox" data-fb="catalog_images_disabled" {{catalog_images_disabled}}><span>Não mostrar as imagens do catálogo visual nesta pergunta</span></label><div class="twt-fb-small">Por defeito, cada produto com imagem no separador Catálogo visual (Cardex e Catálogo) mostra a miniatura no front.</div><label class="twt-fb-check" style="margin-top:10px"><input type="checkbox" data-fb="camera_reading" {{camera_reading}}><span>Leitura por câmara (piloto)</span></label><div class="twt-fb-small">Mostra "Ler com câmara" nesta pergunta nos clientes com a leitura ligada em Cardex e Catálogo → Leitura por câmara. O operativo confirma sempre antes de preencher.</div><div class="twt-fb-manual-products"><label style="margin-top:10px">Produtos manuais, um por linha</label><textarea data-fb="products" rows="6" placeholder="categoria;referencia;produto">{{products_text}}</textarea><div class="twt-fb-small">Para frentes: categoria;referencia;produto. Para sugestão de encomenda: categoria;codigo;tipo;produto;capacidade_cl;unidades_caixa. Para listas extensas, usa o menu Cardex e Catálogo e importa CSV. <button type="button" class="button button-small" data-fb-products-template>Descarregar template CSV</button></div></div></div><div class="twt-fb-row twt-fb-order-settings"><label class="twt-fb-check"><input type="checkbox" data-fb="only_store_assortment" {{only_store_assortment}}><span>Mostrar apenas o sortido associado à loja</span></label><label class="twt-fb-check" style="margin-top:8px"><input type="checkbox" data-fb="order_quantity_decimals" {{order_quantity_decimals}}><span>Permitir caixas com casas decimais</span></label><label style="margin-top:10px">Caixas sugeridas quando o número de frentes é 0</label><input type="number" min="0" step="0.01" data-fb="order_zero_fronts_suggestion" value="{{order_zero_fronts_suggestion}}"><div class="twt-fb-small">Valor automático por produto em rutura. Por defeito, o FieldFlow sugere 1 caixa e mantém qualquer valor introduzido manualmente.</div><div class="twt-fb-small">A loja, a data e o vendedor são preenchidos automaticamente no front. Os campos do Cardex ficam bloqueados e apenas a sugestão e as observações são editáveis.</div></div><div class="twt-fb-row twt-fb-upload-settings"><label class="twt-fb-check"><input type="checkbox" data-fb="multiple" {{multiple}}><span data-fb-upload-multiple-label>Permitir vários ficheiros</span></label><div class="twt-fb-small" data-fb-upload-help>Disponível para uploads de imagem e ficheiro.</div></div><div class="twt-fb-grid twt-fb-grid-3"><div class="twt-fb-row"><label>Mínimo</label><input type="number" step="0.01" data-fb="min" value="{{min}}"></div><div class="twt-fb-row"><label>Máximo</label><input type="number" step="0.01" data-fb="max" value="{{max}}"></div><div class="twt-fb-row"><label>Unidade</label><input type="text" data-fb="unit" value="{{unit}}"></div></div><div class="twt-fb-conditions"><div class="twt-fb-row"><label class="twt-fb-check"><input type="checkbox" data-fb="condition_enabled" {{condition_enabled}}><span>Ativar condicionalidade</span></label><div class="twt-fb-small">Cria várias regras por resposta. Cada regra mostra a pergunta de destino quando o valor corresponde.</div></div><div class="twt-fb-condition-fields"><div class="twt-fb-condition-rules" data-fb-condition-rules></div><button type="button" class="button" data-fb-add-condition>Adicionar condição</button><div class="twt-fb-small">Funciona com checkbox, seleção, radio e seleção visual. Em seleção múltipla, podem ser abertas várias perguntas em simultâneo.</div></div></div></div></div></script></div><h2 style="margin-top:24px">JSON avançado</h2><p class="description">Opcionalmente podes editar o schema diretamente. Se o deixares inválido, o builder vai recuperar um schema vazio.</p><textarea name="schema_json_raw" style="width:100%;min-height:240px;font-family:ui-monospace,monospace">'.esc_textarea(wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)).'</textarea>';
        submit_button($id ? 'Guardar formulário' : 'Criar formulário'); echo '</form></div>';
    }
    public static function handle_save() {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('routespro_save_form', 'routespro_save_form_nonce');
        global $wpdb;

        $id = absint($_POST['id'] ?? 0);
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $status = sanitize_key(wp_unslash($_POST['status'] ?? 'active'));
        if (!in_array($status,['active','inactive'],true)) $status='active';

        $schema_hidden = trim((string) wp_unslash($_POST['schema_json'] ?? ''));
        $schema_raw = trim((string) wp_unslash($_POST['schema_json_raw'] ?? ''));

        $decoded_hidden = json_decode($schema_hidden, true);
        $decoded_raw = json_decode($schema_raw, true);

        // O builder visual manda em schema_json. O JSON avançado só entra como fallback.
        if (is_array($decoded_hidden)) {
            $schema_source = $decoded_hidden;
        } elseif (is_array($decoded_raw)) {
            $schema_source = $decoded_raw;
        } else {
            $schema_source = FormsModule::default_schema();
        }

        $schema = FormsModule::decode_schema(wp_json_encode($schema_source, JSON_UNESCAPED_UNICODE));
        $integrity = ConditionalGraphIntegrity::analyse($schema);

        $data = [
            'title'=>$title ?: 'Novo formulário',
            'status'=>$status,
            'schema_json'=>wp_json_encode($schema, JSON_UNESCAPED_UNICODE),
            'settings_json'=>wp_json_encode([], JSON_UNESCAPED_UNICODE),
            'theme_json'=>wp_json_encode([
                'primary'=>sanitize_hex_color(wp_unslash($_POST['theme_primary'] ?? '#2271b1')) ?: '#2271b1',
                'primary_hover'=>sanitize_hex_color(wp_unslash($_POST['theme_primary_hover'] ?? '#135e96')) ?: '#135e96',
                'radius'=>max(0,min(30,(int)($_POST['theme_radius'] ?? 12)))
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'=>current_time('mysql')
        ];

        if($id){
            $wpdb->update(FormsModule::table(), $data, ['id'=>$id], ['%s','%s','%s','%s','%s','%s'], ['%d']);
        } else {
            $data['created_at']=current_time('mysql');
            $wpdb->insert(FormsModule::table(), $data, ['%s','%s','%s','%s','%s','%s','%s']);
            $id=(int)$wpdb->insert_id;
        }

        if ($id && class_exists('\RoutesPro\Mobile\OperationalContentRevision')) {
            $bindings = \RoutesPro\Forms\BindingResolver::table();
            $projectIds = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT COALESCE(NULLIF(b.project_id,0),NULLIF(r.project_id,0),NULLIF(l.project_id,0)) AS project_id
                 FROM {$bindings} b
                 LEFT JOIN {$wpdb->prefix}routespro_routes r ON r.id=b.route_id
                 LEFT JOIN {$wpdb->prefix}routespro_locations l ON l.id=b.location_id
                 WHERE b.form_id=%d",
                $id
            )) ?: [];
            foreach ($projectIds as $projectId) if (absint($projectId)) \RoutesPro\Mobile\OperationalContentRevision::bump(absint($projectId), 'form_schema');
        }

        wp_safe_redirect(add_query_arg([
            'page'=>'routespro-form-edit',
            'id'=>$id,
            'saved'=>1,
            'integrity_warnings'=>(int)($integrity['warning_count'] ?? 0),
        ], admin_url('admin.php')));
        exit;
    }
    public static function handle_delete() {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        $id = absint($_GET['id'] ?? 0);
        check_admin_referer('routespro_delete_form_'.$id);
        global $wpdb;
        $projectIds = [];
        if ($id && class_exists('\RoutesPro\Mobile\OperationalContentRevision')) {
            $bindings = \RoutesPro\Forms\BindingResolver::table();
            $projectIds = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT COALESCE(NULLIF(b.project_id,0),NULLIF(r.project_id,0),NULLIF(l.project_id,0)) AS project_id
                 FROM {$bindings} b
                 LEFT JOIN {$wpdb->prefix}routespro_routes r ON r.id=b.route_id
                 LEFT JOIN {$wpdb->prefix}routespro_locations l ON l.id=b.location_id
                 WHERE b.form_id=%d",
                $id
            )) ?: [];
        }
        $wpdb->delete(FormsModule::table(), ['id'=>$id], ['%d']);
        foreach ($projectIds as $projectId) if (absint($projectId)) \RoutesPro\Mobile\OperationalContentRevision::bump(absint($projectId), 'form_delete');
        wp_safe_redirect(admin_url('admin.php?page=routespro-forms'));
        exit;
    }
}
