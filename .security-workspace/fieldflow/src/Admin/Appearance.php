<?php
namespace RoutesPro\Admin;

use RoutesPro\Support\Theme;

if (!defined('ABSPATH')) exit;

class Appearance {
    const OPT_KEY = 'routespro_appearance';

    public static function get($key = null, $default = null) {
        $opts = Theme::global();
        if ($key === null) return $opts;
        return array_key_exists($key, $opts) ? $opts[$key] : $default;
    }

    public static function render(): void {
        if (!current_user_can('routespro_manage')) return;
        $view = sanitize_key((string)($_GET['view'] ?? 'global'));
        if (!in_array($view, ['global','campaigns'], true)) $view = 'global';

        if ($view === 'global' && !empty($_POST['routespro_appearance_nonce']) && wp_verify_nonce($_POST['routespro_appearance_nonce'], 'routespro_appearance')) {
            $input = wp_unslash($_POST);
            $input['show_hero_metrics'] = !empty($_POST['show_hero_metrics']) ? 1 : 0;
            $input['compact_headers'] = !empty($_POST['compact_headers']) ? 1 : 0;
            $input['evidence_lazy_load'] = !empty($_POST['evidence_lazy_load']) ? 1 : 0;
            $input['mobile_nav_compact'] = !empty($_POST['mobile_nav_compact']) ? 1 : 0;
            $input['fronts_tabs_enabled'] = !empty($_POST['fronts_tabs_enabled']) ? 1 : 0;
            $saved = Theme::sanitize($input, Theme::global());
            update_option(self::OPT_KEY, $saved, false);
            echo '<div class="notice notice-success is-dismissible"><p>Tema global guardado.</p></div>';
        }

        echo '<div class="wrap ff-design-admin">';
        Branding::render_header('Personalização', 'Sistema visual global e temas premium por campanha.');
        self::admin_styles();
        echo '<nav class="ff-design-tabs">';
        echo '<a class="' . ($view === 'global' ? 'active' : '') . '" href="' . esc_url(admin_url('admin.php?page=routespro-appearance&view=global')) . '">Tema global</a>';
        echo '<a class="' . ($view === 'campaigns' ? 'active' : '') . '" href="' . esc_url(admin_url('admin.php?page=routespro-appearance&view=campaigns')) . '">Temas por campanha</a>';
        echo '</nav>';

        if ($view === 'campaigns') {
            CampaignBranding::render_embedded();
        } else {
            self::render_global_panel();
        }
        echo '</div>';
    }

    private static function render_global_panel(): void {
        $o = Theme::global();
        $templates = Theme::templates();
        echo '<form method="post" class="ff-design-form" id="ff-global-theme-form">';
        wp_nonce_field('routespro_appearance','routespro_appearance_nonce');
        echo '<div class="ff-design-layout">';
        echo '<main class="ff-design-main">';
        self::render_template_picker($templates, (string)$o['design_template']);
        echo '<section class="ff-design-section"><div class="ff-section-head"><div><h2>Identidade visual</h2></div><p>Paleta, tipografia e superfícies usadas em toda a experiência.</p></div>';
        echo '<div class="ff-field-grid">';
        self::color_field('primary_color', 'Cor primária', $o['primary_color']);
        self::color_field('accent_color', 'Cor de destaque', $o['accent_color']);
        self::color_field('bg_color', 'Fundo geral', $o['bg_color']);
        self::color_field('surface_color', 'Superfícies', $o['surface_color']);
        self::color_field('border_color', 'Linhas e bordas', $o['border_color']);
        self::color_field('header_start_color', 'Header, início', $o['header_start_color']);
        self::color_field('header_end_color', 'Header, fim', $o['header_end_color']);
        self::text_field('font_family', 'Fonte de texto', $o['font_family'], 'Inter, system-ui, sans-serif');
        self::text_field('font_headings', 'Fonte de títulos', $o['font_headings'], 'Inter, system-ui, sans-serif');
        self::number_field('font_size_px', 'Tamanho base', $o['font_size_px'], 12, 20, 'px');
        echo '</div></section>';

        echo '<section class="ff-design-section"><div class="ff-section-head"><div><h2>Cores de texto</h2></div><p>Controlo completo do contraste em headers, navegação, tabelas, formulários, botões, badges e estados.</p></div><div class="ff-field-grid">';
        self::color_field('text_color', 'Texto principal', $o['text_color']);
        self::color_field('heading_color', 'Títulos', $o['heading_color']);
        self::color_field('muted_color', 'Texto secundário', $o['muted_color']);
        self::color_field('label_color', 'Labels e microtexto', $o['label_color']);
        self::color_field('hero_text_color', 'Títulos nos headers', $o['hero_text_color']);
        self::color_field('hero_muted_color', 'Texto nos headers', $o['hero_muted_color']);
        self::color_field('scope_title_color', 'Cor do título do Escopo atual', $o['scope_title_color']);
        self::color_field('scope_text_color', 'Cor do texto do Escopo atual', $o['scope_text_color']);
        self::color_field('link_color', 'Links', $o['link_color']);
        self::color_field('link_hover_color', 'Links em hover', $o['link_hover_color']);
        self::color_field('input_text_color', 'Texto dos campos', $o['input_text_color']);
        self::color_field('placeholder_color', 'Placeholders', $o['placeholder_color']);
        self::color_field('selection_text_color', 'Texto selecionado', $o['selection_text_color']);
        self::color_field('selection_bg_color', 'Fundo da seleção', $o['selection_bg_color']);
        self::color_field('nav_text_color', 'Texto das abas', $o['nav_text_color']);
        self::color_field('nav_hover_text_color', 'Aba em hover', $o['nav_hover_text_color']);
        self::color_field('nav_active_text_color', 'Aba ativa', $o['nav_active_text_color']);
        self::color_field('button_text_color', 'Botão primário', $o['button_text_color']);
        self::color_field('button_hover_text_color', 'Botão primário em hover', $o['button_hover_text_color']);
        self::color_field('button_secondary_text_color', 'Botão secundário', $o['button_secondary_text_color']);
        self::color_field('button_secondary_hover_text_color', 'Botão secundário em hover', $o['button_secondary_hover_text_color']);
        self::color_field('table_header_text_color', 'Cabeçalhos de tabela', $o['table_header_text_color']);
        self::color_field('badge_text_color', 'Badges e pills', $o['badge_text_color']);
        self::color_field('success_text_color', 'Estado positivo', $o['success_text_color']);
        self::color_field('warning_text_color', 'Estado de alerta', $o['warning_text_color']);
        self::color_field('danger_text_color', 'Estado crítico', $o['danger_text_color']);
        echo '</div></section>';

        echo '<section class="ff-design-section"><div class="ff-section-head"><div><h2>Componentes</h2></div><p>Define o comportamento visual de cards, botões, tabelas, gráficos e navegação.</p></div><div class="ff-field-grid">';
        self::select_field('header_style', 'Estilo do header', $o['header_style'], ['gradient'=>'Gradiente premium','aurora'=>'Aurora luminosa','light'=>'Claro minimal','solid'=>'Sólido']);
        self::select_field('nav_style', 'Navegação por abas', $o['nav_style'], ['floating_dark'=>'Flutuante escura','floating_light'=>'Flutuante clara','underline'=>'Linha editorial']);
        self::select_field('card_style', 'Estilo dos cards', $o['card_style'], ['elevated'=>'Elevados','outline'=>'Contorno limpo','glass'=>'Glass']);
        self::select_field('button_style', 'Estilo dos botões', $o['button_style'], ['soft'=>'Suave','pill'=>'Pill','square'=>'Geométrico']);
        self::select_field('table_style', 'Estilo das tabelas', $o['table_style'], ['airy'=>'Amplo','compact'=>'Compacto','zebra'=>'Zebra']);
        self::select_field('chart_style', 'Estilo dos gráficos', $o['chart_style'], ['soft'=>'Suave','vivid'=>'Vibrante','mono'=>'Monocromático']);
        self::select_field('density', 'Densidade', $o['density'], ['compact'=>'Compacta','comfortable'=>'Confortável','spacious'=>'Espaçosa']);
        self::select_field('card_shadow', 'Sombra dos cards', $o['card_shadow'], ['none'=>'Sem sombra','sm'=>'Pequena','md'=>'Média','lg'=>'Grande','xl'=>'Editorial']);
        self::number_field('radius_px', 'Raio dos cards', $o['radius_px'], 0, 36, 'px');
        self::number_field('input_radius_px', 'Raio dos campos', $o['input_radius_px'], 0, 24, 'px');
        self::number_field('button_radius_px', 'Raio dos botões', $o['button_radius_px'], 0, 999, 'px');
        self::number_field('spacing_px', 'Espaçamento base', $o['spacing_px'], 6, 24, 'px');
        echo '</div></section>';

        echo '<section class="ff-design-section"><div class="ff-section-head"><div><h2>Mobile e dados extensos</h2></div><p>Controla calendários, tabelas, evidências e navegação em telemóvel e tablet.</p></div><div class="ff-field-grid">';
        self::select_field('mobile_calendar_mode', 'Calendário em telemóvel', $o['mobile_calendar_mode'], ['agenda'=>'Agenda vertical legível','compact_grid'=>'Grelha mensal compacta']);
        self::select_field('mobile_table_mode', 'Tabelas em telemóvel', $o['mobile_table_mode'], ['cards'=>'Cartões por registo','scroll'=>'Tabela com deslocamento']);
        self::select_field('evidence_mobile_mode', 'Evidências no mobile', $o['evidence_mobile_mode'], ['accordion'=>'Acordeões por loja e data','expanded'=>'Tudo expandido']);
        self::select_field('evidence_thumb_size', 'Tamanho das miniaturas', $o['evidence_thumb_size'], ['compact'=>'Compactas','medium'=>'Médias','large'=>'Grandes']);
        self::number_field('evidence_initial_open', 'Lojas abertas inicialmente', $o['evidence_initial_open'], 0, 4, '');
        self::number_field('evidence_page_size', 'Lojas por bloco', $o['evidence_page_size'], 2, 24, '');
        self::number_field('analytics_rows_per_page', 'Linhas por página nos analytics', $o['analytics_rows_per_page'], 5, 30, '');
        echo '<label class="ff-toggle"><input type="checkbox" name="fronts_tabs_enabled" value="1" ' . checked(!empty($o['fronts_tabs_enabled']), true, false) . '><span></span><b>Frentes atuais organizadas por abas</b></label>';
        echo '<label class="ff-toggle"><input type="checkbox" name="evidence_lazy_load" value="1" ' . checked(!empty($o['evidence_lazy_load']), true, false) . '><span></span><b>Carregar imagens apenas ao abrir</b></label>';
        echo '<label class="ff-toggle"><input type="checkbox" name="mobile_nav_compact" value="1" ' . checked(!empty($o['mobile_nav_compact']), true, false) . '><span></span><b>Navegação inferior compacta</b></label>';
        echo '</div></section>';

        echo '<section class="ff-design-section"><div class="ff-section-head"><div><h2>Conteúdo dos headers</h2></div><p>Mensagens curtas, orientadas à ação e com menos ruído visual.</p></div><div class="ff-field-grid">';
        self::text_field('header_eyebrow', 'Etiqueta superior', $o['header_eyebrow'], 'FieldFlow');
        self::text_field('app_title', 'Título da app', $o['app_title'], 'Operação em campo');
        self::text_field('app_subtitle', 'Subtítulo da app', $o['app_subtitle'], 'Rotas, execução e resultados.');
        self::text_field('client_title', 'Título do portal cliente', $o['client_title'], 'Visão executiva da campanha');
        self::text_field('client_subtitle', 'Subtítulo do portal', $o['client_subtitle'], 'Resultados, operação e evidências.');
        echo '<label class="ff-toggle"><input type="checkbox" name="show_hero_metrics" value="1" ' . checked(!empty($o['show_hero_metrics']), true, false) . '><span></span><b>Mostrar métricas no header</b></label>';
        echo '<label class="ff-toggle"><input type="checkbox" name="compact_headers" value="1" ' . checked(!empty($o['compact_headers']), true, false) . '><span></span><b>Headers compactos</b></label>';
        echo '</div></section>';
        echo '<div class="ff-savebar"><div><strong>Tema global</strong><span>Serve de base a todas as campanhas sem personalização própria.</span></div><button class="button button-primary button-hero">Guardar personalização</button></div>';
        echo '</main>';
        self::render_preview($o, 'global');
        echo '</div></form>';
        self::render_template_script($templates, 'ff-global-theme-form');
    }

    public static function render_template_picker(array $templates, string $selected): void {
        echo '<section class="ff-design-section ff-template-section"><div class="ff-section-head"><div><span>Templates</span><h2>Escolhe um ponto de partida</h2></div><p>Cada template altera toda a linguagem visual, do header às tabelas.</p></div>';
        echo '<div class="ff-template-grid">';
        foreach ($templates as $slug => $template) {
            $colors = (array)($template['preview'] ?? []);
            echo '<label class="ff-template-card ' . ($selected === $slug ? 'selected' : '') . '" data-template-card="' . esc_attr($slug) . '">';
            echo '<input type="radio" name="design_template" value="' . esc_attr($slug) . '" ' . checked($selected, $slug, false) . '>';
            echo '<span class="ff-template-swatch">';
            foreach ($colors as $color) echo '<i style="background:' . esc_attr($color) . '"></i>';
            echo '</span><strong>' . esc_html((string)$template['label']) . '</strong><small>' . esc_html((string)$template['description']) . '</small></label>';
        }
        echo '</div></section>';
    }

    public static function render_preview(array $o, string $context = 'global'): void {
        echo '<aside class="ff-design-preview" data-preview-context="' . esc_attr($context) . '">';
        echo '<div class="ff-preview-sticky">';
        echo '<div class="ff-preview-label">Pré-visualização em direto</div>';
        echo '<div class="ff-preview-shell">';
        echo '<header class="ff-preview-hero"><div><small data-preview-eyebrow>' . esc_html($o['header_eyebrow']) . '</small><h3 data-preview-title>' . esc_html($context === 'campaign' ? $o['client_title'] : $o['app_title']) . '</h3><p data-preview-subtitle>' . esc_html($context === 'campaign' ? $o['client_subtitle'] : $o['app_subtitle']) . '</p></div><span class="ff-preview-badge">Premium</span></header>';
        echo '<div class="ff-preview-tabs"><button class="active">Resumo</button><button>Analytics</button><button>Relatórios</button></div>';
        echo '<div class="ff-preview-kpis"><article><small>Execução</small><strong>92%</strong><i></i></article><article><small>Lojas</small><strong>148</strong><i></i></article></div>';
        echo '<div class="ff-preview-card"><div class="ff-preview-card-head"><strong>Performance semanal</strong><span>Atualizado</span></div><div class="ff-preview-chart"><b style="height:42%"></b><b style="height:67%"></b><b style="height:53%"></b><b style="height:84%"></b><b style="height:74%"></b></div></div>';
        echo '<div class="ff-preview-table"><div><b>Loja</b><b>Estado</b></div><div><span>Continente Gaia</span><em>Concluído</em></div><div><span>Auchan Maia</span><em>Em curso</em></div></div>';
        echo '</div></div></aside>';
    }

    public static function render_template_script(array $templates, string $form_id): void {
        $payload = [];
        foreach ($templates as $slug => $template) $payload[$slug] = (array)($template['settings'] ?? []);
        echo '<script>(function(){const form=document.getElementById(' . wp_json_encode($form_id) . ');if(!form)return;const presets=' . wp_json_encode($payload) . ';const preview=form.querySelector(".ff-design-preview");const q=n=>form.querySelector(`[name="${n}"]`);const set=(n,v)=>{const el=q(n);if(!el||v===undefined)return;if(el.type==="checkbox")el.checked=!!v;else el.value=v;};function applyPreset(slug){const data=presets[slug]||{};Object.keys(data).forEach(k=>set(k,data[k]));paint();form.querySelectorAll("[data-template-card]").forEach(c=>c.classList.toggle("selected",c.dataset.templateCard===slug));}function paint(){if(!preview)return;const val=(n,f)=>q(n)?.value||f;preview.style.setProperty("--p",val("primary_color","#2563eb"));preview.style.setProperty("--a",val("accent_color","#06b6d4"));preview.style.setProperty("--bg",val("bg_color","#f4f7fb"));preview.style.setProperty("--s",val("surface_color","#fff"));preview.style.setProperty("--t",val("text_color","#0f172a"));preview.style.setProperty("--h",val("heading_color","#0f172a"));preview.style.setProperty("--m",val("muted_color","#64748b"));preview.style.setProperty("--l",val("label_color","#475569"));preview.style.setProperty("--ht",val("hero_text_color","#ffffff"));preview.style.setProperty("--hm",val("hero_muted_color","#dbeafe"));preview.style.setProperty("--sct",val("scope_title_color","#ffffff"));preview.style.setProperty("--scx",val("scope_text_color","#dbeafe"));preview.style.setProperty("--ln",val("link_color","#2563eb"));preview.style.setProperty("--it",val("input_text_color","#0f172a"));preview.style.setProperty("--st",val("selection_text_color","#ffffff"));preview.style.setProperty("--sb",val("selection_bg_color","#2563eb"));preview.style.setProperty("--nt",val("nav_text_color","#cbd5e1"));preview.style.setProperty("--nh",val("nav_hover_text_color","#ffffff"));preview.style.setProperty("--na",val("nav_active_text_color","#0f172a"));preview.style.setProperty("--bt",val("button_text_color","#ffffff"));preview.style.setProperty("--bh",val("button_hover_text_color","#ffffff"));preview.style.setProperty("--b2",val("button_secondary_text_color","#0f172a"));preview.style.setProperty("--b2h",val("button_secondary_hover_text_color","#0f172a"));preview.style.setProperty("--th",val("table_header_text_color","#475569"));preview.style.setProperty("--bd",val("badge_text_color","#0f172a"));preview.style.setProperty("--ok",val("success_text_color","#047857"));preview.style.setProperty("--b",val("border_color","#dbe4ef"));preview.style.setProperty("--hs",val("header_start_color","#0b1220"));preview.style.setProperty("--he",val("header_end_color","#1d4ed8"));preview.style.setProperty("--r",val("radius_px","22")+"px");preview.style.setProperty("--br",val("button_radius_px","12")+"px");preview.style.setProperty("--ff",val("font_family","Inter,system-ui,sans-serif"));const ctx=preview.dataset.previewContext;const title=q(ctx==="campaign"?"client_title":"app_title");const sub=q(ctx==="campaign"?"client_subtitle":"app_subtitle");const eye=q("header_eyebrow");if(title)preview.querySelector("[data-preview-title]").textContent=title.value;if(sub)preview.querySelector("[data-preview-subtitle]").textContent=sub.value;if(eye)preview.querySelector("[data-preview-eyebrow]").textContent=eye.value;}form.querySelectorAll("[name=design_template]").forEach(el=>el.addEventListener("change",()=>applyPreset(el.value)));form.addEventListener("input",function(ev){const input=ev.target;if(input&&input.type==="color"){const code=input.closest(".ff-color-input")?.querySelector("code");if(code)code.textContent=input.value;}paint();});paint();})();</script>';
    }

    public static function color_field(string $name, string $label, $value): void {
        echo '<label class="ff-field"><span>' . esc_html($label) . '</span><div class="ff-color-input"><input type="color" name="' . esc_attr($name) . '" value="' . esc_attr((string)$value) . '"><code>' . esc_html((string)$value) . '</code></div></label>';
    }

    public static function text_field(string $name, string $label, $value, string $placeholder = ''): void {
        echo '<label class="ff-field"><span>' . esc_html($label) . '</span><input type="text" name="' . esc_attr($name) . '" value="' . esc_attr((string)$value) . '" placeholder="' . esc_attr($placeholder) . '"></label>';
    }

    public static function number_field(string $name, string $label, $value, int $min, int $max, string $suffix = ''): void {
        echo '<label class="ff-field"><span>' . esc_html($label) . '</span><div class="ff-number-input"><input type="number" name="' . esc_attr($name) . '" min="' . (int)$min . '" max="' . (int)$max . '" value="' . esc_attr((string)$value) . '"><i>' . esc_html($suffix) . '</i></div></label>';
    }

    public static function select_field(string $name, string $label, $value, array $options): void {
        echo '<label class="ff-field"><span>' . esc_html($label) . '</span><select name="' . esc_attr($name) . '">';
        foreach ($options as $key => $text) echo '<option value="' . esc_attr($key) . '" ' . selected((string)$value, (string)$key, false) . '>' . esc_html($text) . '</option>';
        echo '</select></label>';
    }

    private static function admin_styles(): void {
        echo '<style>
        .ff-design-admin{--ffda:#2563eb;max-width:1500px}.ff-design-tabs{display:flex;gap:8px;margin:-4px 0 18px;padding:6px;background:#eef2f7;border:1px solid #dbe4ef;border-radius:16px;width:max-content}.ff-design-tabs a{padding:10px 16px;border-radius:11px;text-decoration:none;color:#475569;font-weight:800}.ff-design-tabs a.active{background:#fff;color:#0f172a;box-shadow:0 5px 16px rgba(15,23,42,.08)}
        .ff-design-layout{display:grid;grid-template-columns:minmax(0,1fr) 390px;gap:20px;align-items:start}.ff-design-main{display:flex;flex-direction:column;gap:16px}.ff-design-section{background:#fff;border:1px solid #dfe7f1;border-radius:22px;padding:20px;box-shadow:0 10px 30px rgba(15,23,42,.045)}.ff-section-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:18px}.ff-section-head>div{display:flex;align-items:center;gap:10px}.ff-section-head span{display:inline-flex;padding:5px 8px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:10px;text-transform:uppercase;letter-spacing:.06em;font-weight:900}.ff-section-head h2{margin:0;font-size:19px}.ff-section-head p{margin:2px 0 0;color:#64748b;max-width:520px;text-align:right}
        .ff-template-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}.ff-template-card{position:relative;display:flex;flex-direction:column;gap:7px;padding:13px;border:1px solid #dbe4ef;border-radius:16px;background:#fbfdff;cursor:pointer;transition:.18s ease}.ff-template-card:hover{transform:translateY(-2px);border-color:#93c5fd}.ff-template-card.selected{border-color:#2563eb;box-shadow:0 0 0 3px #dbeafe;background:#fff}.ff-template-card input{position:absolute;opacity:0}.ff-template-card strong{font-size:13px}.ff-template-card small{color:#64748b;line-height:1.35}.ff-template-swatch{display:grid;grid-template-columns:1.2fr 1fr 1fr;height:34px;overflow:hidden;border-radius:10px}.ff-template-swatch i{display:block}
        .ff-field-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.ff-field{display:flex;flex-direction:column;gap:7px;min-width:0}.ff-field>span{font-size:12px;font-weight:800;color:#475569}.ff-field input[type=text],.ff-field input[type=number],.ff-field select{width:100%;min-height:44px;border:1px solid #cbd5e1;border-radius:11px;padding:9px 11px;background:#fff}.ff-color-input{display:grid;grid-template-columns:48px 1fr;gap:8px;align-items:center;padding:5px;border:1px solid #cbd5e1;border-radius:11px;background:#fff}.ff-color-input input{width:48px;height:34px;border:0;padding:0;background:none}.ff-color-input code{color:#475569}.ff-number-input{display:flex;align-items:center;border:1px solid #cbd5e1;border-radius:11px;background:#fff;overflow:hidden}.ff-number-input input{border:0!important;border-radius:0!important}.ff-number-input i{padding:0 11px;color:#64748b;font-style:normal}
        .ff-toggle{display:flex;align-items:center;gap:10px;min-height:44px;padding:10px 12px;border:1px solid #dbe4ef;border-radius:12px;background:#f8fafc}.ff-toggle input{display:none}.ff-toggle span{position:relative;width:38px;height:22px;border-radius:999px;background:#cbd5e1;transition:.2s}.ff-toggle span:after{content:"";position:absolute;left:3px;top:3px;width:16px;height:16px;border-radius:50%;background:#fff;transition:.2s}.ff-toggle input:checked+span{background:#2563eb}.ff-toggle input:checked+span:after{transform:translateX(16px)}.ff-toggle b{font-size:12px;color:#334155}
        .ff-savebar{position:sticky;bottom:12px;z-index:5;display:flex;justify-content:space-between;gap:16px;align-items:center;padding:14px 16px;border:1px solid #bfdbfe;border-radius:17px;background:rgba(255,255,255,.94);backdrop-filter:blur(12px);box-shadow:0 16px 40px rgba(15,23,42,.12)}.ff-savebar strong,.ff-savebar span{display:block}.ff-savebar span{margin-top:3px;color:#64748b}.ff-savebar .button-hero{min-height:44px;padding:0 20px;border-radius:12px}
        .ff-campaign-filter{display:grid;grid-template-columns:minmax(220px,1fr) minmax(280px,1.4fr) auto;gap:12px;align-items:end}.ff-campaign-filter .button{min-height:44px;border-radius:11px}.ff-inheritance{display:flex;gap:9px;align-items:center;margin-top:14px;padding:11px 13px;border-radius:13px}.ff-inheritance strong,.ff-inheritance span{display:block}.ff-inheritance span{color:#64748b}.ff-inheritance.inherited{background:#f8fafc;border:1px dashed #cbd5e1}.ff-inheritance.custom{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}.ff-design-empty{padding:46px 24px;text-align:center;border:1px dashed #cbd5e1;border-radius:22px;background:linear-gradient(180deg,#fff,#f8fafc)}.ff-design-empty>div{font-size:34px;color:#2563eb}.ff-design-empty h2{margin:8px 0 6px}.ff-design-empty p{margin:0 auto;max-width:660px;color:#64748b}.ff-brand-grid{display:grid;grid-template-columns:260px minmax(0,1fr);gap:18px}.ff-logo-field>label{display:block;margin-bottom:7px;font-size:12px;font-weight:800;color:#475569}.ff-logo-preview{display:flex;align-items:center;justify-content:center;min-height:150px;padding:18px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#64748b;text-align:center}.ff-logo-preview img{display:block;max-width:100%;max-height:105px;width:auto;height:auto}.ff-logo-actions{display:flex;justify-content:center;gap:10px;align-items:center;margin-top:10px}.ff-save-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.ff-configured-themes{margin-top:16px}.ff-theme-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.ff-theme-list-item{display:grid;grid-template-columns:48px minmax(0,1fr) auto;gap:11px;align-items:center;padding:12px;border:1px solid #dbe4ef;border-radius:15px;background:#fbfdff;text-decoration:none;color:#0f172a;transition:.18s}.ff-theme-list-item:hover{transform:translateY(-1px);border-color:#93c5fd;background:#fff}.ff-theme-list-item strong,.ff-theme-list-item small{display:block}.ff-theme-list-item small{margin-top:3px;color:#64748b}.ff-theme-list-item em{font-style:normal;color:#2563eb;font-weight:800}.ff-theme-mini{width:48px;height:48px;border-radius:13px;background:linear-gradient(135deg,var(--c1),var(--c2));box-shadow:inset 0 0 0 1px rgba(255,255,255,.3)}.ff-inline-empty{padding:18px;border-radius:14px;background:#f8fafc;color:#64748b;text-align:center}
        .ff-design-preview{--p:#2563eb;--a:#06b6d4;--bg:#f4f7fb;--s:#fff;--t:#0f172a;--h:#0f172a;--m:#64748b;--l:#475569;--ht:#fff;--hm:#dbeafe;--sct:#fff;--scx:#dbeafe;--ln:#2563eb;--it:#0f172a;--st:#fff;--sb:#2563eb;--nt:#cbd5e1;--nh:#fff;--na:#0f172a;--bt:#fff;--bh:#fff;--b2:#0f172a;--b2h:#0f172a;--th:#475569;--bd:#0f172a;--ok:#047857;--b:#dbe4ef;--hs:#0b1220;--he:#1d4ed8;--r:22px;--br:12px;--ff:Inter,system-ui,sans-serif}.ff-preview-sticky{position:sticky;top:48px}.ff-preview-label{margin:0 0 8px;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:900}.ff-preview-shell{font-family:var(--ff);padding:12px;border:1px solid var(--b);border-radius:28px;background:var(--bg);box-shadow:0 24px 60px rgba(15,23,42,.13)}.ff-preview-hero{display:flex;justify-content:space-between;gap:12px;min-height:170px;padding:22px;border-radius:var(--r);background:radial-gradient(circle at top right,rgba(255,255,255,.22),transparent 30%),linear-gradient(135deg,var(--hs),var(--he));color:var(--ht)}.ff-preview-hero small{font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--hm)}.ff-preview-hero h3{margin:22px 0 7px;color:var(--ht);font-size:25px;line-height:1}.ff-preview-hero p{margin:0;max-width:260px;color:var(--hm);line-height:1.4}.ff-preview-badge{height:max-content;padding:6px 9px;border:1px solid color-mix(in srgb,var(--sb) 72%,#fff);border-radius:999px;background:var(--sb);color:var(--st);font-size:10px;font-weight:900}.ff-preview-tabs{display:flex;gap:6px;margin:10px 0;padding:6px;border-radius:14px;background:#111827}.ff-preview-tabs button{flex:1;border:0;border-radius:var(--br);padding:8px;background:transparent;color:var(--nt);font-weight:800}.ff-preview-tabs button:hover{color:var(--nh)}.ff-preview-tabs button.active{background:#fff;color:var(--na)}.ff-preview-kpis{display:grid;grid-template-columns:1fr 1fr;gap:8px}.ff-preview-kpis article,.ff-preview-card,.ff-preview-table{border:1px solid var(--b);border-radius:calc(var(--r) * .72);background:var(--s);box-shadow:0 7px 20px rgba(15,23,42,.05)}.ff-preview-kpis article{padding:12px}.ff-preview-kpis small{display:block;color:var(--m);text-transform:uppercase;font-size:9px;font-weight:900}.ff-preview-kpis strong{display:block;margin:5px 0;font-size:23px;color:var(--h)}.ff-preview-kpis i{display:block;width:44px;height:4px;border-radius:99px;background:linear-gradient(90deg,var(--p),var(--a))}.ff-preview-card{margin-top:8px;padding:13px}.ff-preview-card-head{display:flex;justify-content:space-between;color:var(--h)}.ff-preview-card-head span{font-size:10px;color:var(--m)}.ff-preview-chart{display:flex;align-items:end;gap:8px;height:90px;margin-top:12px;padding-top:10px;border-top:1px solid var(--b)}.ff-preview-chart b{flex:1;border-radius:6px 6px 2px 2px;background:linear-gradient(180deg,var(--a),var(--p))}.ff-preview-table{margin-top:8px;overflow:hidden}.ff-preview-table>div{display:grid;grid-template-columns:1fr auto;gap:8px;padding:10px 12px;border-top:1px solid var(--b);color:var(--t)}.ff-preview-table>div:first-child{border-top:0;background:#f8fafc;color:var(--th)}.ff-preview-table em{padding:4px 7px;border-radius:999px;background:#ecfdf5;color:var(--ok);font-size:10px;font-style:normal;font-weight:800}
        @media(max-width:1250px){.ff-theme-list{grid-template-columns:1fr 1fr}.ff-design-layout{grid-template-columns:1fr}.ff-design-preview{order:-1}.ff-preview-sticky{position:static}.ff-preview-shell{max-width:700px}.ff-template-grid{grid-template-columns:repeat(3,1fr)}}@media(max-width:850px){.ff-campaign-filter{grid-template-columns:1fr 1fr}.ff-campaign-filter .button{grid-column:1/-1}.ff-brand-grid{grid-template-columns:1fr}.ff-field-grid{grid-template-columns:1fr 1fr}.ff-template-grid{grid-template-columns:1fr 1fr}.ff-section-head{flex-direction:column}.ff-section-head p{text-align:left}.ff-savebar{position:static;flex-direction:column;align-items:stretch}}@media(max-width:560px){.ff-campaign-filter,.ff-theme-list,.ff-field-grid,.ff-template-grid{grid-template-columns:1fr}.ff-save-actions{flex-direction:column;align-items:stretch}}
        </style>';
    }
}
