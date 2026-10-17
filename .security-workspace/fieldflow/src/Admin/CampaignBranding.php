<?php
namespace RoutesPro\Admin;

use RoutesPro\Support\Theme;

if (!defined('ABSPATH')) exit;

class CampaignBranding {
    const OPT_KEY = 'routespro_campaign_branding';

    public static function render(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        echo '<div class="wrap">';
        Branding::render_header('Temas por campanha', 'Personalização completa do FieldFlow por cliente e campanha.');
        self::render_embedded();
        echo '</div>';
    }

    public static function render_embedded(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        global $wpdb;
        $clients_tbl = $wpdb->prefix . 'routespro_clients';
        $projects_tbl = $wpdb->prefix . 'routespro_projects';
        $items = get_option(self::OPT_KEY, []);
        if (!is_array($items)) $items = [];

        $selected_client_id = absint($_REQUEST['client_id'] ?? 0);
        $selected_project_id = absint($_REQUEST['project_id'] ?? 0);
        if ($selected_project_id && !$selected_client_id) {
            $selected_client_id = absint($wpdb->get_var($wpdb->prepare("SELECT client_id FROM {$projects_tbl} WHERE id=%d", $selected_project_id)));
        }

        if (!empty($_POST['routespro_campaign_branding_nonce']) && wp_verify_nonce($_POST['routespro_campaign_branding_nonce'], 'routespro_campaign_branding')) {
            $client_id = absint($_POST['client_id'] ?? 0);
            $project_id = absint($_POST['project_id'] ?? 0);
            if ($project_id) {
                $project_client_id = absint($wpdb->get_var($wpdb->prepare("SELECT client_id FROM {$projects_tbl} WHERE id=%d LIMIT 1", $project_id)));
                if ($project_client_id) $client_id = $project_client_id;
            }
            $key = self::make_key($client_id, $project_id);
            $action = sanitize_key((string)($_POST['branding_action'] ?? 'save'));
            if ($action === 'delete') {
                unset($items[$key]);
                update_option(self::OPT_KEY, $items, false);
                echo '<div class="notice notice-success is-dismissible"><p>Personalização removida. A campanha voltou a herdar o tema global.</p></div>';
            } elseif ($project_id <= 0) {
                echo '<div class="notice notice-error"><p>Seleciona uma campanha antes de guardar.</p></div>';
            } else {
                $input = wp_unslash($_POST);
                $input['show_hero_metrics'] = isset($_POST['show_hero_metrics']) ? 1 : 0;
                $input['compact_headers'] = isset($_POST['compact_headers']) ? 1 : 0;
                $input['evidence_lazy_load'] = isset($_POST['evidence_lazy_load']) ? 1 : 0;
                $input['mobile_nav_compact'] = isset($_POST['mobile_nav_compact']) ? 1 : 0;
                $input['fronts_tabs_enabled'] = isset($_POST['fronts_tabs_enabled']) ? 1 : 0;
                $saved = Theme::sanitize($input, Theme::global());
                $logo_id = absint($_POST['logo_id'] ?? 0);
                $logo_url = esc_url_raw((string)($_POST['logo_url'] ?? ''));
                if (!$logo_url && $logo_id) $logo_url = wp_get_attachment_url($logo_id) ?: '';
                $saved['client_id'] = $client_id;
                $saved['project_id'] = $project_id;
                $saved['logo_id'] = $logo_id;
                $saved['logo_url'] = $logo_url;
                $saved['updated_at'] = current_time('mysql');
                $items[$key] = $saved;
                update_option(self::OPT_KEY, $items, false);
                echo '<div class="notice notice-success is-dismissible"><p>Tema premium da campanha guardado.</p></div>';
            }
            $selected_client_id = $client_id;
            $selected_project_id = $project_id;
        }

        $clients = $wpdb->get_results("SELECT id,name FROM {$clients_tbl} ORDER BY name ASC", ARRAY_A) ?: [];
        $projects = $wpdb->get_results("SELECT p.id,p.name,p.client_id,c.name AS client_name FROM {$projects_tbl} p LEFT JOIN {$clients_tbl} c ON c.id=p.client_id ORDER BY c.name ASC,p.name ASC", ARRAY_A) ?: [];
        $visible_projects = $selected_client_id ? array_values(array_filter($projects, function($p) use ($selected_client_id){ return (int)$p['client_id'] === $selected_client_id; })) : $projects;
        $key = self::make_key($selected_client_id, $selected_project_id);
        $has_custom = !empty($items[$key]) && is_array($items[$key]);
        $current = $has_custom ? Theme::resolve($selected_client_id, $selected_project_id) : Theme::global();
        $current['logo_id'] = $has_custom ? absint($items[$key]['logo_id'] ?? 0) : 0;
        $current['logo_url'] = $has_custom ? esc_url_raw((string)($items[$key]['logo_url'] ?? '')) : '';
        $templates = Theme::templates();

        echo '<section class="ff-design-section ff-campaign-selector">';
        echo '<div class="ff-section-head"><div><span>Campanha</span><h2>Escolhe o contexto visual</h2></div><p>O tema selecionado será aplicado ao app da equipa e ao portal do cliente.</p></div>';
        echo '<form method="get" class="ff-campaign-filter"><input type="hidden" name="page" value="routespro-appearance"><input type="hidden" name="view" value="campaigns">';
        echo '<label class="ff-field"><span>Cliente</span><select name="client_id" onchange="this.form.project_id.value=\'0\';this.form.submit()"><option value="0">Selecionar cliente</option>';
        foreach ($clients as $client) echo '<option value="' . (int)$client['id'] . '" ' . selected($selected_client_id, (int)$client['id'], false) . '>' . esc_html((string)$client['name']) . '</option>';
        echo '</select></label>';
        echo '<label class="ff-field"><span>Campanha</span><select name="project_id"><option value="0">Selecionar campanha</option>';
        foreach ($visible_projects as $project) {
            $label = ($project['client_name'] ? $project['client_name'] . ' · ' : '') . ($project['name'] ?: ('#' . (int)$project['id']));
            echo '<option value="' . (int)$project['id'] . '" ' . selected($selected_project_id, (int)$project['id'], false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label><button class="button button-primary">Abrir personalização</button></form>';
        if ($selected_project_id) {
            echo '<div class="ff-inheritance ' . ($has_custom ? 'custom' : 'inherited') . '"><strong>' . ($has_custom ? 'Tema personalizado ativo' : 'A herdar o tema global') . '</strong><span>' . ($has_custom ? 'As alterações desta campanha substituem a base global.' : 'Ao guardar, esta campanha passa a ter identidade visual própria.') . '</span></div>';
        }
        echo '</section>';

        if ($selected_project_id <= 0) {
            echo '<div class="ff-design-empty"><div>✦</div><h2>Seleciona uma campanha</h2><p>Depois podes personalizar o header, todas as abas, analytics, tabelas, gráficos, botões, hover, bordas e tipografia.</p></div>';
            self::render_configured_list($items, $clients_tbl, $projects_tbl);
            return;
        }

        echo '<form method="post" class="ff-design-form" id="ff-campaign-theme-form">';
        wp_nonce_field('routespro_campaign_branding', 'routespro_campaign_branding_nonce');
        echo '<input type="hidden" name="client_id" value="' . (int)$selected_client_id . '"><input type="hidden" name="project_id" value="' . (int)$selected_project_id . '">';
        echo '<div class="ff-design-layout"><main class="ff-design-main">';
        Appearance::render_template_picker($templates, (string)$current['design_template']);

        echo '<section class="ff-design-section"><div class="ff-section-head"><div><span>Branding</span><h2>Logo e cabeçalho</h2></div><p>Identidade visível no topo do app e do portal cliente.</p></div><div class="ff-brand-grid">';
        echo '<div class="ff-logo-field"><label>Logo da campanha</label><input type="hidden" name="logo_id" id="routespro-campaign-logo-id" value="' . (int)$current['logo_id'] . '"><input type="hidden" name="logo_url" id="routespro-campaign-logo-url" value="' . esc_attr((string)$current['logo_url']) . '"><div id="routespro-campaign-logo-preview" class="ff-logo-preview">' . self::logo_preview((string)$current['logo_url']) . '</div><div class="ff-logo-actions"><button type="button" class="button" id="routespro-campaign-logo-pick">Escolher logo</button><button type="button" class="button-link-delete" id="routespro-campaign-logo-clear">Remover</button></div></div>';
        echo '<div class="ff-field-grid">';
        Appearance::text_field('header_eyebrow', 'Etiqueta superior', $current['header_eyebrow'], 'Nome da marca');
        Appearance::text_field('app_title', 'Título da app', $current['app_title'], 'Operação em campo');
        Appearance::text_field('app_subtitle', 'Subtítulo da app', $current['app_subtitle'], 'Rotas, execução e resultados.');
        Appearance::text_field('client_title', 'Título do portal cliente', $current['client_title'], 'Visão executiva da campanha');
        Appearance::text_field('client_subtitle', 'Subtítulo do portal', $current['client_subtitle'], 'Resultados, operação e evidências.');
        echo '<label class="ff-toggle"><input type="checkbox" name="show_hero_metrics" value="1" ' . checked(!empty($current['show_hero_metrics']), true, false) . '><span></span><b>Mostrar métricas no header</b></label>';
        echo '<label class="ff-toggle"><input type="checkbox" name="compact_headers" value="1" ' . checked(!empty($current['compact_headers']), true, false) . '><span></span><b>Headers compactos</b></label>';
        echo '</div></div></section>';

        echo '<section class="ff-design-section"><div class="ff-section-head"><div><span>Paleta</span><h2>Cores e tipografia</h2></div><p>A campanha pode ter uma linguagem visual totalmente própria.</p></div><div class="ff-field-grid">';
        Appearance::color_field('primary_color', 'Cor primária', $current['primary_color']);
        Appearance::color_field('accent_color', 'Cor de destaque', $current['accent_color']);
        Appearance::color_field('bg_color', 'Fundo geral', $current['bg_color']);
        Appearance::color_field('surface_color', 'Superfícies', $current['surface_color']);
        Appearance::color_field('border_color', 'Linhas e bordas', $current['border_color']);
        Appearance::color_field('header_start_color', 'Header, início', $current['header_start_color']);
        Appearance::color_field('header_end_color', 'Header, fim', $current['header_end_color']);
        Appearance::text_field('font_family', 'Fonte de texto', $current['font_family'], 'Inter, system-ui, sans-serif');
        Appearance::text_field('font_headings', 'Fonte de títulos', $current['font_headings'], 'Inter, system-ui, sans-serif');
        Appearance::number_field('font_size_px', 'Tamanho base', $current['font_size_px'], 12, 20, 'px');
        echo '</div></section>';

        echo '<section class="ff-design-section"><div class="ff-section-head"><div><span>Texto</span><h2>Contraste e leitura</h2></div><p>Personaliza cada família de texto da campanha, incluindo headers, abas, campos, botões, tabelas e estados.</p></div><div class="ff-field-grid">';
        Appearance::color_field('text_color', 'Texto principal', $current['text_color']);
        Appearance::color_field('heading_color', 'Títulos', $current['heading_color']);
        Appearance::color_field('muted_color', 'Texto secundário', $current['muted_color']);
        Appearance::color_field('label_color', 'Labels e microtexto', $current['label_color']);
        Appearance::color_field('hero_text_color', 'Texto do header', $current['hero_text_color']);
        Appearance::color_field('hero_muted_color', 'Subtexto do header', $current['hero_muted_color']);
        Appearance::color_field('scope_title_color', 'Cor do título do Escopo atual', $current['scope_title_color']);
        Appearance::color_field('scope_text_color', 'Cor do texto do Escopo atual', $current['scope_text_color']);
        Appearance::color_field('link_color', 'Links', $current['link_color']);
        Appearance::color_field('link_hover_color', 'Links em hover', $current['link_hover_color']);
        Appearance::color_field('input_text_color', 'Texto dos campos', $current['input_text_color']);
        Appearance::color_field('placeholder_color', 'Placeholders', $current['placeholder_color']);
        Appearance::color_field('selection_text_color', 'Texto selecionado', $current['selection_text_color']);
        Appearance::color_field('selection_bg_color', 'Fundo da seleção', $current['selection_bg_color']);
        Appearance::color_field('nav_text_color', 'Texto das abas', $current['nav_text_color']);
        Appearance::color_field('nav_hover_text_color', 'Aba em hover', $current['nav_hover_text_color']);
        Appearance::color_field('nav_active_text_color', 'Aba ativa', $current['nav_active_text_color']);
        Appearance::color_field('button_text_color', 'Botão primário', $current['button_text_color']);
        Appearance::color_field('button_hover_text_color', 'Botão primário em hover', $current['button_hover_text_color']);
        Appearance::color_field('button_secondary_text_color', 'Botão secundário', $current['button_secondary_text_color']);
        Appearance::color_field('button_secondary_hover_text_color', 'Botão secundário em hover', $current['button_secondary_hover_text_color']);
        Appearance::color_field('table_header_text_color', 'Cabeçalhos de tabela', $current['table_header_text_color']);
        Appearance::color_field('badge_text_color', 'Badges e pills', $current['badge_text_color']);
        Appearance::color_field('success_text_color', 'Estado positivo', $current['success_text_color']);
        Appearance::color_field('warning_text_color', 'Estado de alerta', $current['warning_text_color']);
        Appearance::color_field('danger_text_color', 'Estado crítico', $current['danger_text_color']);
        echo '</div></section>';

        echo '<section class="ff-design-section"><div class="ff-section-head"><div><span>Sistema</span><h2>Componentes e densidade</h2></div><p>Controla todo o detalhe, incluindo hover, bordas, cards, abas e leitura de dados.</p></div><div class="ff-field-grid">';
        Appearance::select_field('header_style', 'Estilo do header', $current['header_style'], ['gradient'=>'Gradiente premium','aurora'=>'Aurora luminosa','light'=>'Claro minimal','solid'=>'Sólido']);
        Appearance::select_field('nav_style', 'Navegação por abas', $current['nav_style'], ['floating_dark'=>'Flutuante escura','floating_light'=>'Flutuante clara','underline'=>'Linha editorial']);
        Appearance::select_field('card_style', 'Estilo dos cards', $current['card_style'], ['elevated'=>'Elevados','outline'=>'Contorno limpo','glass'=>'Glass']);
        Appearance::select_field('button_style', 'Estilo dos botões', $current['button_style'], ['soft'=>'Suave','pill'=>'Pill','square'=>'Geométrico']);
        Appearance::select_field('table_style', 'Estilo das tabelas', $current['table_style'], ['airy'=>'Amplo','compact'=>'Compacto','zebra'=>'Zebra']);
        Appearance::select_field('chart_style', 'Estilo dos gráficos', $current['chart_style'], ['soft'=>'Suave','vivid'=>'Vibrante','mono'=>'Monocromático']);
        Appearance::select_field('density', 'Densidade', $current['density'], ['compact'=>'Compacta','comfortable'=>'Confortável','spacious'=>'Espaçosa']);
        Appearance::select_field('card_shadow', 'Sombra dos cards', $current['card_shadow'], ['none'=>'Sem sombra','sm'=>'Pequena','md'=>'Média','lg'=>'Grande','xl'=>'Editorial']);
        Appearance::number_field('radius_px', 'Raio dos cards', $current['radius_px'], 0, 36, 'px');
        Appearance::number_field('input_radius_px', 'Raio dos campos', $current['input_radius_px'], 0, 24, 'px');
        Appearance::number_field('button_radius_px', 'Raio dos botões', $current['button_radius_px'], 0, 999, 'px');
        Appearance::number_field('spacing_px', 'Espaçamento base', $current['spacing_px'], 6, 24, 'px');
        echo '</div></section>';

        echo '<section class="ff-design-section"><div class="ff-section-head"><div><span>Mobile</span><h2>Calendários, tabelas e evidências</h2></div><p>Define o comportamento dos elementos extensos no portal do cliente e na app.</p></div><div class="ff-field-grid">';
        Appearance::select_field('mobile_calendar_mode', 'Calendário em telemóvel', $current['mobile_calendar_mode'], ['agenda'=>'Agenda vertical legível','compact_grid'=>'Grelha mensal compacta']);
        Appearance::select_field('mobile_table_mode', 'Tabelas em telemóvel', $current['mobile_table_mode'], ['cards'=>'Cartões por registo','scroll'=>'Tabela com deslocamento']);
        Appearance::select_field('evidence_mobile_mode', 'Evidências no mobile', $current['evidence_mobile_mode'], ['accordion'=>'Acordeões por loja e data','expanded'=>'Tudo expandido']);
        Appearance::select_field('evidence_thumb_size', 'Tamanho das miniaturas', $current['evidence_thumb_size'], ['compact'=>'Compactas','medium'=>'Médias','large'=>'Grandes']);
        Appearance::number_field('evidence_initial_open', 'Lojas abertas inicialmente', $current['evidence_initial_open'], 0, 4, '');
        Appearance::number_field('evidence_page_size', 'Lojas por bloco', $current['evidence_page_size'], 2, 24, '');
        Appearance::number_field('analytics_rows_per_page', 'Linhas por página nos analytics', $current['analytics_rows_per_page'], 5, 30, '');
        echo '<label class="ff-toggle"><input type="checkbox" name="fronts_tabs_enabled" value="1" ' . checked(!empty($current['fronts_tabs_enabled']), true, false) . '><span></span><b>Frentes atuais organizadas por abas</b></label>';
        echo '<label class="ff-toggle"><input type="checkbox" name="evidence_lazy_load" value="1" ' . checked(!empty($current['evidence_lazy_load']), true, false) . '><span></span><b>Carregar imagens apenas ao abrir</b></label>';
        echo '<label class="ff-toggle"><input type="checkbox" name="mobile_nav_compact" value="1" ' . checked(!empty($current['mobile_nav_compact']), true, false) . '><span></span><b>Navegação inferior compacta</b></label>';
        echo '</div></section>';

        echo '<div class="ff-savebar"><div><strong>' . esc_html(self::resolve_project_name($projects, $selected_project_id)) . '</strong><span>Este tema é aplicado aos shortcodes [fieldflow_app] e [fieldflow_client_portal].</span></div><div class="ff-save-actions"><button class="button button-primary button-hero" name="branding_action" value="save">Guardar tema da campanha</button>';
        if ($has_custom) echo '<button class="button-link-delete" name="branding_action" value="delete" onclick="return confirm(\'Restaurar a herança global desta campanha?\')">Restaurar tema global</button>';
        echo '</div></div></main>';
        Appearance::render_preview($current, 'campaign');
        echo '</div></form>';
        Appearance::render_template_script($templates, 'ff-campaign-theme-form');
        self::render_media_script();
        self::render_configured_list($items, $clients_tbl, $projects_tbl);
    }

    private static function render_configured_list(array $items, string $clients_tbl, string $projects_tbl): void {
        global $wpdb;
        echo '<section class="ff-design-section ff-configured-themes"><div class="ff-section-head"><div><span>Ativos</span><h2>Temas configurados</h2></div><p>Mapa rápido das campanhas com identidade visual própria.</p></div>';
        if (!$items) {
            echo '<div class="ff-inline-empty">Ainda não existem temas por campanha.</div></section>';
            return;
        }
        echo '<div class="ff-theme-list">';
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $client_id = absint($item['client_id'] ?? 0);
            $project_id = absint($item['project_id'] ?? 0);
            $client_name = $client_id ? ($wpdb->get_var($wpdb->prepare("SELECT name FROM {$clients_tbl} WHERE id=%d", $client_id)) ?: ('#' . $client_id)) : 'Sem cliente';
            $project_name = $project_id ? ($wpdb->get_var($wpdb->prepare("SELECT name FROM {$projects_tbl} WHERE id=%d", $project_id)) ?: ('#' . $project_id)) : 'Sem campanha';
            $template = Theme::template((string)($item['design_template'] ?? 'executive'));
            $url = admin_url('admin.php?page=routespro-appearance&view=campaigns&client_id=' . $client_id . '&project_id=' . $project_id);
            echo '<a class="ff-theme-list-item" href="' . esc_url($url) . '"><span class="ff-theme-mini" style="--c1:' . esc_attr((string)($item['header_start_color'] ?? '#0b1220')) . ';--c2:' . esc_attr((string)($item['primary_color'] ?? '#2563eb')) . '"></span><span><strong>' . esc_html((string)$project_name) . '</strong><small>' . esc_html((string)$client_name) . ' · ' . esc_html((string)($template['label'] ?? 'Tema')) . '</small></span><em>Editar</em></a>';
        }
        echo '</div></section>';
    }

    private static function logo_preview(string $url): string {
        if (!$url) return '<span>Sem logo, será usado apenas o branding FieldFlow.</span>';
        return '<img src="' . esc_url($url) . '" alt="Logo da campanha">';
    }

    private static function render_media_script(): void {
        wp_enqueue_media();
        echo '<script>(function(){const pick=document.getElementById("routespro-campaign-logo-pick"),clear=document.getElementById("routespro-campaign-logo-clear"),url=document.getElementById("routespro-campaign-logo-url"),id=document.getElementById("routespro-campaign-logo-id"),preview=document.getElementById("routespro-campaign-logo-preview");if(!pick||!url||!preview)return;let frame=null;function paint(){const value=url.value||"";preview.innerHTML=value?`<img src="${value.replace(/"/g,"&quot;")}" alt="Logo da campanha">`:`<span>Sem logo, será usado apenas o branding FieldFlow.</span>`;}pick.addEventListener("click",e=>{e.preventDefault();if(frame){frame.open();return;}frame=wp.media({title:"Escolher logo da campanha",button:{text:"Usar este logo"},multiple:false});frame.on("select",()=>{const a=frame.state().get("selection").first().toJSON();url.value=a.url||"";id.value=a.id||"0";paint();});frame.open();});clear?.addEventListener("click",e=>{e.preventDefault();url.value="";id.value="0";paint();});paint();})();</script>';
    }

    private static function resolve_project_name(array $projects, int $project_id): string {
        foreach ($projects as $project) if ((int)$project['id'] === $project_id) return (string)$project['name'];
        return $project_id ? ('Campanha #' . $project_id) : 'Sem campanha';
    }

    private static function make_key(int $client_id, int $project_id): string {
        return $client_id . '|' . $project_id;
    }
}
