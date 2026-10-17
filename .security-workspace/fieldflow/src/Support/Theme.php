<?php
namespace RoutesPro\Support;

if (!defined('ABSPATH')) exit;

class Theme {
    const GLOBAL_OPT = 'routespro_appearance';
    const CAMPAIGN_OPT = 'routespro_campaign_branding';

    public static function defaults(): array {
        return [
            'design_template' => 'executive',
            'primary_color' => '#2563eb',
            'primary_transparent' => 0,
            'accent_color' => '#06b6d4',
            'accent_transparent' => 0,
            'bg_color' => '#f4f7fb',
            'bg_transparent' => 0,
            'surface_color' => '#ffffff',
            'text_color' => '#0f172a',
            'heading_color' => '#0f172a',
            'muted_color' => '#627289',
            'label_color' => '#475569',
            'hero_text_color' => '#ffffff',
            'hero_muted_color' => '#dbeafe',
            'scope_title_color' => '#ffffff',
            'scope_text_color' => '#dbeafe',
            'link_color' => '#2563eb',
            'link_hover_color' => '#1d4ed8',
            'input_text_color' => '#0f172a',
            'placeholder_color' => '#94a3b8',
            'selection_text_color' => '#ffffff',
            'selection_bg_color' => '#2563eb',
            'nav_text_color' => '#cbd5e1',
            'nav_hover_text_color' => '#ffffff',
            'nav_active_text_color' => '#0f172a',
            'button_text_color' => '#ffffff',
            'button_hover_text_color' => '#ffffff',
            'button_secondary_text_color' => '#0f172a',
            'button_secondary_hover_text_color' => '#0f172a',
            'table_header_text_color' => '#475569',
            'badge_text_color' => '#0f172a',
            'success_text_color' => '#047857',
            'warning_text_color' => '#b45309',
            'danger_text_color' => '#b91c1c',
            'border_color' => '#dbe4ef',
            'header_start_color' => '#0b1220',
            'header_end_color' => '#1d4ed8',
            'font_family' => 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            'font_headings' => 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            'font_size_px' => 15,
            'radius_px' => 22,
            'input_radius_px' => 12,
            'button_radius_px' => 12,
            'card_shadow' => 'md',
            'spacing_px' => 14,
            'density' => 'comfortable',
            'header_style' => 'gradient',
            'nav_style' => 'floating_dark',
            'card_style' => 'elevated',
            'button_style' => 'soft',
            'table_style' => 'airy',
            'chart_style' => 'soft',
            'app_title' => 'Operação em campo',
            'app_subtitle' => 'Rotas, execução e resultados, num único fluxo.',
            'client_title' => 'Visão executiva da campanha',
            'client_subtitle' => 'Resultados, operação e evidências com leitura imediata.',
            'header_eyebrow' => 'FieldFlow',
            'show_hero_metrics' => 1,
            'compact_headers' => 1,
            'mobile_calendar_mode' => 'agenda',
            'mobile_table_mode' => 'cards',
            'evidence_mobile_mode' => 'accordion',
            'evidence_thumb_size' => 'medium',
            'evidence_initial_open' => 1,
            'evidence_page_size' => 6,
            'analytics_rows_per_page' => 10,
            'fronts_tabs_enabled' => 1,
            'evidence_lazy_load' => 1,
            'mobile_nav_compact' => 1,
            'logo_id' => 0,
            'logo_url' => '',
        ];
    }

    public static function templates(): array {
        return [
            'executive' => [
                'label' => 'Executive Blue',
                'description' => 'Navy profundo, azul elétrico e leitura executiva limpa.',
                'preview' => ['#04070f', '#2563eb', '#06b6d4'],
                'settings' => [
                    'primary_color' => '#2563eb', 'accent_color' => '#06b6d4', 'bg_color' => '#f4f7fb',
                    'surface_color' => '#ffffff', 'text_color' => '#0f172a', 'heading_color' => '#0f172a', 'muted_color' => '#627289', 'label_color' => '#475569', 'hero_text_color' => '#ffffff', 'hero_muted_color' => '#dbeafe', 'scope_title_color' => '#ffffff', 'scope_text_color' => '#dbeafe', 'link_color' => '#2563eb', 'link_hover_color' => '#1d4ed8', 'input_text_color' => '#0f172a', 'placeholder_color' => '#94a3b8', 'selection_text_color' => '#ffffff', 'selection_bg_color' => '#2563eb', 'nav_text_color' => '#cbd5e1', 'nav_hover_text_color' => '#ffffff', 'nav_active_text_color' => '#0f172a', 'button_text_color' => '#ffffff', 'button_hover_text_color' => '#ffffff', 'button_secondary_text_color' => '#0f172a', 'button_secondary_hover_text_color' => '#0f172a', 'table_header_text_color' => '#475569', 'badge_text_color' => '#0f172a', 'success_text_color' => '#047857', 'warning_text_color' => '#b45309', 'danger_text_color' => '#b91c1c',
                    'border_color' => '#dbe4ef', 'header_start_color' => '#04070f', 'header_end_color' => '#1d4ed8',
                    'header_style' => 'gradient', 'nav_style' => 'floating_dark', 'card_style' => 'elevated',
                    'button_style' => 'soft', 'table_style' => 'airy', 'chart_style' => 'soft',
                    'card_shadow' => 'md', 'radius_px' => 22, 'button_radius_px' => 12, 'input_radius_px' => 12,
                    'density' => 'comfortable',
                ],
            ],
            'aurora' => [
                'label' => 'Aurora Glass',
                'description' => 'Violeta, cyan e superfícies translúcidas com energia digital.',
                'preview' => ['#170a2e', '#7c3aed', '#22d3ee'],
                'settings' => [
                    'primary_color' => '#7c3aed', 'accent_color' => '#22d3ee', 'bg_color' => '#f6f3ff',
                    'surface_color' => '#ffffff', 'text_color' => '#171126', 'heading_color' => '#171126', 'muted_color' => '#6b6478', 'label_color' => '#554b68', 'hero_text_color' => '#ffffff', 'hero_muted_color' => '#ede9fe', 'scope_title_color' => '#ffffff', 'scope_text_color' => '#ede9fe', 'link_color' => '#7c3aed', 'link_hover_color' => '#5b21b6', 'input_text_color' => '#171126', 'placeholder_color' => '#9a8eaa', 'selection_text_color' => '#ffffff', 'selection_bg_color' => '#7c3aed', 'nav_text_color' => '#6b6478', 'nav_hover_text_color' => '#5b21b6', 'nav_active_text_color' => '#171126', 'button_text_color' => '#ffffff', 'button_hover_text_color' => '#ffffff', 'button_secondary_text_color' => '#171126', 'button_secondary_hover_text_color' => '#171126', 'table_header_text_color' => '#554b68', 'badge_text_color' => '#3b166f', 'success_text_color' => '#047857', 'warning_text_color' => '#a16207', 'danger_text_color' => '#b91c1c',
                    'border_color' => '#ddd6fe', 'header_start_color' => '#170a2e', 'header_end_color' => '#6d28d9',
                    'header_style' => 'aurora', 'nav_style' => 'floating_light', 'card_style' => 'glass',
                    'button_style' => 'pill', 'table_style' => 'airy', 'chart_style' => 'vivid',
                    'card_shadow' => 'lg', 'radius_px' => 26, 'button_radius_px' => 999, 'input_radius_px' => 14,
                    'density' => 'comfortable',
                ],
            ],
            'nordic' => [
                'label' => 'Nordic Minimal',
                'description' => 'Muito branco, contraste preciso e quase nenhum ruído visual.',
                'preview' => ['#f8fafc', '#111827', '#64748b'],
                'settings' => [
                    'primary_color' => '#111827', 'accent_color' => '#64748b', 'bg_color' => '#f8fafc',
                    'surface_color' => '#ffffff', 'text_color' => '#111827', 'heading_color' => '#111827', 'muted_color' => '#64748b', 'label_color' => '#475569', 'hero_text_color' => '#111827', 'hero_muted_color' => '#475569', 'scope_title_color' => '#111827', 'scope_text_color' => '#475569', 'link_color' => '#111827', 'link_hover_color' => '#000000', 'input_text_color' => '#111827', 'placeholder_color' => '#94a3b8', 'selection_text_color' => '#ffffff', 'selection_bg_color' => '#111827', 'nav_text_color' => '#475569', 'nav_hover_text_color' => '#111827', 'nav_active_text_color' => '#111827', 'button_text_color' => '#ffffff', 'button_hover_text_color' => '#ffffff', 'button_secondary_text_color' => '#111827', 'button_secondary_hover_text_color' => '#111827', 'table_header_text_color' => '#475569', 'badge_text_color' => '#111827', 'success_text_color' => '#047857', 'warning_text_color' => '#92400e', 'danger_text_color' => '#b91c1c',
                    'border_color' => '#e5e7eb', 'header_start_color' => '#ffffff', 'header_end_color' => '#f1f5f9',
                    'header_style' => 'light', 'nav_style' => 'underline', 'card_style' => 'outline',
                    'button_style' => 'square', 'table_style' => 'compact', 'chart_style' => 'mono',
                    'card_shadow' => 'sm', 'radius_px' => 16, 'button_radius_px' => 8, 'input_radius_px' => 8,
                    'density' => 'compact',
                ],
            ],
            'emerald' => [
                'label' => 'Emerald Ops',
                'description' => 'Verde operacional, grafite e sensação de controlo em tempo real.',
                'preview' => ['#04140f', '#008659', '#84cc16'],
                'settings' => [
                    'primary_color' => '#008659', 'accent_color' => '#84cc16', 'bg_color' => '#f2f8f5',
                    'surface_color' => '#ffffff', 'text_color' => '#10231d', 'heading_color' => '#10231d', 'muted_color' => '#5f716b', 'label_color' => '#496159', 'hero_text_color' => '#ffffff', 'hero_muted_color' => '#d1fae5', 'scope_title_color' => '#ffffff', 'scope_text_color' => '#d1fae5', 'link_color' => '#047857', 'link_hover_color' => '#065f46', 'input_text_color' => '#10231d', 'placeholder_color' => '#8ba198', 'selection_text_color' => '#ffffff', 'selection_bg_color' => '#059669', 'nav_text_color' => '#d1fae5', 'nav_hover_text_color' => '#ffffff', 'nav_active_text_color' => '#10231d', 'button_text_color' => '#ffffff', 'button_hover_text_color' => '#ffffff', 'button_secondary_text_color' => '#10231d', 'button_secondary_hover_text_color' => '#10231d', 'table_header_text_color' => '#496159', 'badge_text_color' => '#065f46', 'success_text_color' => '#047857', 'warning_text_color' => '#a16207', 'danger_text_color' => '#b91c1c',
                    'border_color' => '#cfe4da', 'header_start_color' => '#04140f', 'header_end_color' => '#047857',
                    'header_style' => 'gradient', 'nav_style' => 'floating_dark', 'card_style' => 'elevated',
                    'button_style' => 'soft', 'table_style' => 'zebra', 'chart_style' => 'vivid',
                    'card_shadow' => 'md', 'radius_px' => 22, 'button_radius_px' => 12, 'input_radius_px' => 12,
                    'density' => 'comfortable',
                ],
            ],
            'editorial' => [
                'label' => 'Editorial Sand',
                'description' => 'Marfim, carvão e dourado seco, sofisticado sem ser ostensivo.',
                'preview' => ['#28231f', '#a16207', '#e7d6b5'],
                'settings' => [
                    'primary_color' => '#92400e', 'accent_color' => '#ca8a04', 'bg_color' => '#f7f3eb',
                    'surface_color' => '#fffdf8', 'text_color' => '#292524', 'heading_color' => '#292524', 'muted_color' => '#746d68', 'label_color' => '#57534e', 'hero_text_color' => '#fffdf8', 'hero_muted_color' => '#e7ded1', 'scope_title_color' => '#fffdf8', 'scope_text_color' => '#e7ded1', 'link_color' => '#92400e', 'link_hover_color' => '#78350f', 'input_text_color' => '#292524', 'placeholder_color' => '#a8a29e', 'selection_text_color' => '#fffdf8', 'selection_bg_color' => '#92400e', 'nav_text_color' => '#746d68', 'nav_hover_text_color' => '#fffdf8', 'nav_active_text_color' => '#292524', 'button_text_color' => '#fffdf8', 'button_hover_text_color' => '#fffdf8', 'button_secondary_text_color' => '#292524', 'button_secondary_hover_text_color' => '#292524', 'table_header_text_color' => '#57534e', 'badge_text_color' => '#78350f', 'success_text_color' => '#047857', 'warning_text_color' => '#92400e', 'danger_text_color' => '#991b1b',
                    'border_color' => '#e7ded1', 'header_start_color' => '#292524', 'header_end_color' => '#57534e',
                    'header_style' => 'solid', 'nav_style' => 'floating_light', 'card_style' => 'outline',
                    'button_style' => 'square', 'table_style' => 'compact', 'chart_style' => 'mono',
                    'card_shadow' => 'sm', 'radius_px' => 14, 'button_radius_px' => 6, 'input_radius_px' => 6,
                    'density' => 'compact',
                    'font_headings' => 'Georgia, "Times New Roman", serif',
                ],
            ],
            'neon_flux' => [
                'label' => 'Neon Flux',
                'description' => 'Futurista e dinâmico: hero quase-preto com glow, magenta e cyan elétricos.',
                'preview' => ['#0a0612', '#d6169f', '#00b8d9'],
                'settings' => [
                    'primary_color' => '#d6169f', 'accent_color' => '#00b8d9', 'bg_color' => '#f5f3fb',
                    'surface_color' => '#ffffff', 'text_color' => '#161221', 'heading_color' => '#161221', 'muted_color' => '#6b6478', 'label_color' => '#57506a', 'hero_text_color' => '#ffffff', 'hero_muted_color' => '#f0d9f7', 'scope_title_color' => '#ffffff', 'scope_text_color' => '#f0d9f7', 'link_color' => '#b0138a', 'link_hover_color' => '#8a0e6c', 'input_text_color' => '#161221', 'placeholder_color' => '#9a92ab', 'selection_text_color' => '#ffffff', 'selection_bg_color' => '#d6169f', 'nav_text_color' => '#d9b8e6', 'nav_hover_text_color' => '#ffffff', 'nav_active_text_color' => '#161221', 'button_text_color' => '#ffffff', 'button_hover_text_color' => '#ffffff', 'button_secondary_text_color' => '#161221', 'button_secondary_hover_text_color' => '#161221', 'table_header_text_color' => '#57506a', 'badge_text_color' => '#7a0f5e', 'success_text_color' => '#047857', 'warning_text_color' => '#a16207', 'danger_text_color' => '#b91c1c',
                    'border_color' => '#e3d9ee', 'header_start_color' => '#0a0612', 'header_end_color' => '#7c1fa2',
                    'header_style' => 'aurora', 'nav_style' => 'floating_dark', 'card_style' => 'glass',
                    'button_style' => 'pill', 'table_style' => 'airy', 'chart_style' => 'vivid',
                    'card_shadow' => 'xl', 'radius_px' => 28, 'button_radius_px' => 999, 'input_radius_px' => 16,
                    'density' => 'comfortable',
                ],
            ],
        ];
    }

    public static function template(string $slug): array {
        $templates = self::templates();
        return $templates[$slug] ?? $templates['executive'];
    }

    public static function global(): array {
        $saved = get_option(self::GLOBAL_OPT, []);
        if (!is_array($saved)) $saved = [];
        $slug = sanitize_key((string)($saved['design_template'] ?? self::defaults()['design_template']));
        $preset = self::template($slug);
        return self::normalize(array_merge(self::defaults(), (array)($preset['settings'] ?? []), $saved));
    }

    public static function campaign_override(int $client_id = 0, int $project_id = 0): array {
        if (!$client_id && !$project_id) return [];
        $items = get_option(self::CAMPAIGN_OPT, []);
        if (!is_array($items)) return [];
        $keys = [];
        if ($client_id && $project_id) $keys[] = $client_id . '|' . $project_id;
        if ($project_id) $keys[] = '0|' . $project_id;
        if ($client_id) $keys[] = $client_id . '|0';
        foreach ($keys as $key) {
            if (!empty($items[$key]) && is_array($items[$key])) return $items[$key];
        }
        return [];
    }

    private static function canonical_context(int $client_id = 0, int $project_id = 0): array {
        global $wpdb;
        $client_id = absint($client_id);
        $project_id = absint($project_id);
        if ($project_id > 0) {
            $project_client_id = absint($wpdb->get_var($wpdb->prepare(
                "SELECT client_id FROM {$wpdb->prefix}routespro_projects WHERE id=%d LIMIT 1",
                $project_id
            )));
            if ($project_client_id > 0) $client_id = $project_client_id;
        }
        return [$client_id, $project_id];
    }

    public static function has_campaign_override(int $client_id = 0, int $project_id = 0): bool {
        return !empty(self::campaign_override($client_id, $project_id));
    }

    public static function resolve(int $client_id = 0, int $project_id = 0): array {
        [$client_id, $project_id] = self::canonical_context($client_id, $project_id);
        $global = self::global();
        $override = self::campaign_override($client_id, $project_id);
        if (!$override) return self::with_aliases($global);
        $slug = sanitize_key((string)($override['design_template'] ?? $global['design_template']));
        $preset = self::template($slug);
        $resolved = array_merge($global, (array)($preset['settings'] ?? []), $override, [
            'design_template' => $slug,
            'client_id' => $client_id,
            'project_id' => $project_id,
        ]);
        return self::with_aliases(self::normalize($resolved));
    }

    public static function normalize(array $settings): array {
        $defaults = self::defaults();
        $out = array_merge($defaults, $settings);
        $templates = self::templates();
        $out['design_template'] = isset($templates[$out['design_template']]) ? sanitize_key((string)$out['design_template']) : 'executive';

        foreach (['primary_color','accent_color','bg_color','surface_color','text_color','heading_color','muted_color','label_color','hero_text_color','hero_muted_color','scope_title_color','scope_text_color','link_color','link_hover_color','input_text_color','placeholder_color','selection_text_color','selection_bg_color','nav_text_color','nav_hover_text_color','nav_active_text_color','button_text_color','button_hover_text_color','button_secondary_text_color','button_secondary_hover_text_color','table_header_text_color','badge_text_color','success_text_color','warning_text_color','danger_text_color','border_color','header_start_color','header_end_color'] as $key) {
            $color = sanitize_hex_color((string)($out[$key] ?? ''));
            $out[$key] = $color ?: $defaults[$key];
        }
        foreach (['primary_transparent','accent_transparent','bg_transparent','show_hero_metrics','compact_headers','evidence_lazy_load','mobile_nav_compact'] as $key) {
            $out[$key] = !empty($out[$key]) ? 1 : 0;
        }
        $out['font_family'] = sanitize_text_field((string)($out['font_family'] ?? $defaults['font_family']));
        $out['font_headings'] = sanitize_text_field((string)($out['font_headings'] ?? $out['font_family']));
        if ($out['font_headings'] === '') $out['font_headings'] = $out['font_family'];
        foreach (['app_title','app_subtitle','client_title','client_subtitle','header_eyebrow'] as $key) {
            $out[$key] = sanitize_text_field((string)($out[$key] ?? $defaults[$key]));
        }
        $out['logo_id'] = absint($out['logo_id'] ?? 0);
        $out['logo_url'] = esc_url_raw((string)($out['logo_url'] ?? ''));

        $out['font_size_px'] = self::clamp($out['font_size_px'] ?? 15, 12, 20, 15);
        $out['radius_px'] = self::clamp($out['radius_px'] ?? 22, 0, 36, 22);
        $out['input_radius_px'] = self::clamp($out['input_radius_px'] ?? 12, 0, 24, 12);
        $out['button_radius_px'] = self::clamp($out['button_radius_px'] ?? 12, 0, 999, 12);
        $out['spacing_px'] = self::clamp($out['spacing_px'] ?? 14, 6, 24, 14);
        $out['evidence_initial_open'] = self::clamp($out['evidence_initial_open'] ?? 1, 0, 4, 1);
        $out['evidence_page_size'] = self::clamp($out['evidence_page_size'] ?? 6, 2, 24, 6);
        $out['analytics_rows_per_page'] = self::clamp($out['analytics_rows_per_page'] ?? 10, 5, 30, 10);

        $out['card_shadow'] = self::enum($out['card_shadow'] ?? 'md', ['none','sm','md','lg','xl'], 'md');
        $out['density'] = self::enum($out['density'] ?? 'comfortable', ['compact','comfortable','spacious'], 'comfortable');
        $out['header_style'] = self::enum($out['header_style'] ?? 'gradient', ['gradient','aurora','light','solid'], 'gradient');
        $out['nav_style'] = self::enum($out['nav_style'] ?? 'floating_dark', ['floating_dark','floating_light','underline'], 'floating_dark');
        $out['card_style'] = self::enum($out['card_style'] ?? 'elevated', ['elevated','outline','glass'], 'elevated');
        $out['button_style'] = self::enum($out['button_style'] ?? 'soft', ['soft','pill','square'], 'soft');
        $out['table_style'] = self::enum($out['table_style'] ?? 'airy', ['airy','compact','zebra'], 'airy');
        $out['chart_style'] = self::enum($out['chart_style'] ?? 'soft', ['soft','vivid','mono'], 'soft');
        $out['mobile_calendar_mode'] = self::enum($out['mobile_calendar_mode'] ?? 'agenda', ['agenda','compact_grid'], 'agenda');
        $out['mobile_table_mode'] = self::enum($out['mobile_table_mode'] ?? 'cards', ['cards','scroll'], 'cards');
        $out['evidence_mobile_mode'] = self::enum($out['evidence_mobile_mode'] ?? 'accordion', ['accordion','expanded'], 'accordion');
        $out['evidence_thumb_size'] = self::enum($out['evidence_thumb_size'] ?? 'medium', ['compact','medium','large'], 'medium');
        return $out;
    }

    public static function sanitize(array $input, array $fallback = []): array {
        $base = array_merge(self::defaults(), $fallback);
        $slug = sanitize_key((string)($input['design_template'] ?? $base['design_template']));
        $preset = self::template($slug);
        $candidate = array_merge($base, (array)($preset['settings'] ?? []));
        foreach (array_keys(self::defaults()) as $key) {
            if (array_key_exists($key, $input)) $candidate[$key] = $input[$key];
        }
        $candidate['design_template'] = $slug;
        return self::normalize($candidate);
    }

    public static function with_aliases(array $theme): array {
        $theme['primary'] = !empty($theme['primary_transparent']) ? 'transparent' : $theme['primary_color'];
        $theme['accent'] = !empty($theme['accent_transparent']) ? 'transparent' : $theme['accent_color'];
        $theme['bg'] = !empty($theme['bg_transparent']) ? 'transparent' : $theme['bg_color'];
        $theme['font'] = $theme['font_family'];
        $theme['headings_font'] = $theme['font_headings'];
        $theme['size'] = (int)$theme['font_size_px'];
        $theme['primary_solid'] = $theme['primary'] === 'transparent' ? '#2563eb' : $theme['primary'];
        $theme['accent_solid'] = $theme['accent'] === 'transparent' ? '#06b6d4' : $theme['accent'];
        return $theme;
    }

    public static function root_classes(array $theme): string {
        $tokens = [
            'template-' . ($theme['design_template'] ?? 'executive'),
            'header-' . ($theme['header_style'] ?? 'gradient'),
            'nav-' . ($theme['nav_style'] ?? 'floating_dark'),
            'card-' . ($theme['card_style'] ?? 'elevated'),
            'button-' . ($theme['button_style'] ?? 'soft'),
            'table-' . ($theme['table_style'] ?? 'airy'),
            'chart-' . ($theme['chart_style'] ?? 'soft'),
            'density-' . ($theme['density'] ?? 'comfortable'),
            'mobile-calendar-' . ($theme['mobile_calendar_mode'] ?? 'agenda'),
            'mobile-table-' . ($theme['mobile_table_mode'] ?? 'cards'),
            'evidence-' . ($theme['evidence_mobile_mode'] ?? 'accordion'),
            'evidence-thumb-' . ($theme['evidence_thumb_size'] ?? 'medium'),
        ];
        if (!empty($theme['compact_headers'])) $tokens[] = 'compact-headers';
        if (!empty($theme['evidence_lazy_load'])) $tokens[] = 'evidence-lazy';
        if (!empty($theme['mobile_nav_compact'])) $tokens[] = 'mobile-nav-compact';
        $tokens[] = !empty($theme['fronts_tabs_enabled']) ? 'fronts-tabs' : 'fronts-flat';
        return implode(' ', array_map(function($token){ return 'fft-' . sanitize_html_class($token); }, $tokens));
    }

    public static function css_variables(array $theme): array {
        $theme = self::with_aliases(self::normalize($theme));
        $density = ['compact' => .82, 'comfortable' => 1, 'spacious' => 1.18][$theme['density']] ?? 1;
        return [
            '--rp-primary' => $theme['primary_solid'],
            '--rp-accent' => $theme['accent_solid'],
            '--rp-primary-solid' => $theme['primary_solid'],
            '--rp-accent-solid' => $theme['accent_solid'],
            '--ff-primary' => $theme['primary_solid'],
            '--ff-accent' => $theme['accent_solid'],
            '--ff-canvas' => $theme['bg_color'],
            '--ff-surface' => $theme['surface_color'],
            '--ff-text' => $theme['text_color'],
            '--ff-heading' => $theme['heading_color'],
            '--ff-muted' => $theme['muted_color'],
            '--ff-label' => $theme['label_color'],
            '--ff-hero-text' => $theme['hero_text_color'],
            '--ff-hero-muted' => $theme['hero_muted_color'],
            '--ff-header-title-text' => $theme['hero_text_color'],
            '--ff-header-body-text' => $theme['hero_muted_color'],
            '--ff-scope-title' => $theme['scope_title_color'],
            '--ff-scope-text' => $theme['scope_text_color'],
            '--ff-link' => $theme['link_color'],
            '--ff-link-hover' => $theme['link_hover_color'],
            '--ff-input-text' => $theme['input_text_color'],
            '--ff-placeholder' => $theme['placeholder_color'],
            '--ff-selection-text' => $theme['selection_text_color'],
            '--ff-selection-bg' => $theme['selection_bg_color'],
            '--ff-nav-text' => $theme['nav_text_color'],
            '--ff-nav-hover-text' => $theme['nav_hover_text_color'],
            '--ff-nav-active-text' => $theme['nav_active_text_color'],
            '--ff-button-text' => $theme['button_text_color'],
            '--ff-button-hover-text' => $theme['button_hover_text_color'],
            '--ff-button-secondary-text' => $theme['button_secondary_text_color'],
            '--ff-button-secondary-hover-text' => $theme['button_secondary_hover_text_color'],
            '--ff-table-header-text' => $theme['table_header_text_color'],
            '--ff-badge-text' => $theme['badge_text_color'],
            '--ff-success-text' => $theme['success_text_color'],
            '--ff-warning-text' => $theme['warning_text_color'],
            '--ff-danger-text' => $theme['danger_text_color'],
            '--ff-border' => $theme['border_color'],
            '--ff-header-start' => $theme['header_start_color'],
            '--ff-header-end' => $theme['header_end_color'],
            '--ff-font' => $theme['font_family'],
            '--ff-font-headings' => $theme['font_headings'],
            '--ff-font-size' => ((int)$theme['font_size_px']) . 'px',
            '--ff-radius' => ((int)$theme['radius_px']) . 'px',
            '--ff-input-radius' => ((int)$theme['input_radius_px']) . 'px',
            '--ff-button-radius' => ((int)$theme['button_radius_px']) . 'px',
            '--ff-space' => ((int)$theme['spacing_px']) . 'px',
            '--ff-density' => (string)$density,
            '--ff-shadow' => self::shadow((string)$theme['card_shadow']),
            '--ff-evidence-initial-open' => (string)((int)$theme['evidence_initial_open']),
            '--ff-evidence-page-size' => (string)((int)$theme['evidence_page_size']),
            '--ff-analytics-rows-per-page' => (string)((int)$theme['analytics_rows_per_page']),
        ];
    }

    public static function inline_style(array $theme): string {
        $parts = [];
        foreach (self::css_variables($theme) as $key => $value) $parts[] = $key . ':' . $value;
        return implode(';', $parts);
    }

    public static function js_payload(array $theme): array {
        return [
            'classes' => self::root_classes($theme),
            'vars' => self::css_variables($theme),
            'logo_url' => (string)($theme['logo_url'] ?? ''),
            'app_title' => (string)($theme['app_title'] ?? ''),
            'app_subtitle' => (string)($theme['app_subtitle'] ?? ''),
            'client_title' => (string)($theme['client_title'] ?? ''),
            'client_subtitle' => (string)($theme['client_subtitle'] ?? ''),
            'header_eyebrow' => (string)($theme['header_eyebrow'] ?? 'FieldFlow'),
            'show_hero_metrics' => !empty($theme['show_hero_metrics']),
            'compact_headers' => !empty($theme['compact_headers']),
            'mobile_calendar_mode' => (string)($theme['mobile_calendar_mode'] ?? 'agenda'),
            'mobile_table_mode' => (string)($theme['mobile_table_mode'] ?? 'cards'),
            'evidence_mobile_mode' => (string)($theme['evidence_mobile_mode'] ?? 'accordion'),
            'evidence_thumb_size' => (string)($theme['evidence_thumb_size'] ?? 'medium'),
            'evidence_initial_open' => (int)($theme['evidence_initial_open'] ?? 1),
            'evidence_page_size' => (int)($theme['evidence_page_size'] ?? 6),
            'analytics_rows_per_page' => (int)($theme['analytics_rows_per_page'] ?? 10),
            'fronts_tabs_enabled' => !empty($theme['fronts_tabs_enabled']),
            'evidence_lazy_load' => !empty($theme['evidence_lazy_load']),
            'mobile_nav_compact' => !empty($theme['mobile_nav_compact']),
        ];
    }

    public static function campaign_theme_map(): array {
        $items = get_option(self::CAMPAIGN_OPT, []);
        if (!is_array($items)) return [];
        $map = [];
        foreach ($items as $key => $item) {
            if (!is_array($item)) continue;
            $client_id = absint($item['client_id'] ?? 0);
            $project_id = absint($item['project_id'] ?? 0);
            if (($client_id <= 0 || $project_id <= 0) && is_string($key) && strpos($key, '|') !== false) {
                [$key_client, $key_project] = array_pad(explode('|', $key, 2), 2, 0);
                if ($client_id <= 0) $client_id = absint($key_client);
                if ($project_id <= 0) $project_id = absint($key_project);
            }
            if ($project_id <= 0) continue;
            [$client_id, $project_id] = self::canonical_context($client_id, $project_id);
            $canonical_key = $client_id . '|' . $project_id;
            $payload = self::js_payload(self::resolve($client_id, $project_id));
            $map[$canonical_key] = $payload;
            $map['0|' . $project_id] = $payload;
        }
        return $map;
    }

    public static function shadow(string $key): string {
        switch ($key) {
            case 'xl': return '0 28px 70px rgba(15,23,42,.16),0 2px 8px rgba(15,23,42,.05)';
            case 'lg': return '0 20px 50px rgba(15,23,42,.13),0 2px 7px rgba(15,23,42,.04)';
            case 'md': return '0 14px 36px rgba(15,23,42,.09),0 1px 4px rgba(15,23,42,.04)';
            case 'sm': return '0 7px 20px rgba(15,23,42,.065)';
            default: return 'none';
        }
    }

    private static function enum($value, array $allowed, string $fallback): string {
        $value = sanitize_key((string)$value);
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private static function clamp($value, int $min, int $max, int $fallback): int {
        $n = is_numeric($value) ? (int)$value : $fallback;
        return max($min, min($max, $n));
    }
}
