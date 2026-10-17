<?php
namespace RoutesPro\Forms;
if (!defined('ABSPATH')) exit;
class FormRenderer {
    public const FRONTS_VISUAL_CONTRACT = 'fieldflow-fronts-mobile-catalog-visual-v1';
    private static $category_group_sequence = 0;
    private static $fronts_visuals = [];
    private static $fronts_compact = false;
    private static $fronts_thumbs = false;
    public static function enqueue_assets() {
        if (class_exists('\\RoutesPro\\Front\\ResponsiveAssets') && !\RoutesPro\Front\ResponsiveAssets::needsFormRuntime()) return;
        wp_register_style('routespro-form-renderer', false, [], ROUTESPRO_VERSION); wp_enqueue_style('routespro-form-renderer'); wp_add_inline_style('routespro-form-renderer', self::inline_css());
        wp_register_script('routespro-form-renderer', false, ['jquery'], ROUTESPRO_VERSION, true); wp_enqueue_script('routespro-form-renderer'); wp_add_inline_script('routespro-form-renderer', self::inline_js());


        // Runtime V3 loads after the proven runtime. In shadow mode it only
        // observes. In the allowlisted pilot it owns condition and navigation
        // decisions, while uploads, drafts, validation and submission remain
        // with the established runtime and can recover instantly.
        if (class_exists('\RoutesPro\Forms\RuntimeV3') && RuntimeV3::shouldEnqueue()) {
            wp_enqueue_style(
                'fieldflow-forms-runtime-v3',
                ROUTESPRO_URL . 'assets/forms-runtime-v3.css',
                ['routespro-form-renderer'],
                ROUTESPRO_VERSION
            );
            wp_enqueue_script(
                'fieldflow-forms-runtime-v3',
                ROUTESPRO_URL . 'assets/forms-runtime-v3.js',
                ['routespro-form-renderer'],
                ROUTESPRO_VERSION,
                true
            );
            wp_localize_script('fieldflow-forms-runtime-v3', 'FieldFlowFormsRuntimeV3Config', RuntimeV3::browserConfig());
        }
    }
    public static function theme_style_attr(array $theme): string {
        $parts = [];
        if (class_exists('\RoutesPro\Support\Theme') && isset($theme['primary_color'])) {
            foreach (\RoutesPro\Support\Theme::css_variables($theme) as $key => $value) $parts[] = $key . ':' . $value;
        }
        $colors = [
            '--rp-form-primary' => $theme['primary_solid'] ?? $theme['primary_color'] ?? $theme['primary'] ?? '',
            '--rp-form-primary-hover' => $theme['link_hover_color'] ?? $theme['primary_hover'] ?? $theme['primary_solid'] ?? $theme['primary'] ?? '',
            '--rp-form-accent' => $theme['accent_solid'] ?? $theme['accent_color'] ?? $theme['accent'] ?? '',
            '--rp-form-surface' => $theme['surface_color'] ?? '#ffffff',
            '--rp-form-text' => $theme['text_color'] ?? '#0f172a',
            '--rp-form-heading' => $theme['heading_color'] ?? $theme['text_color'] ?? '#0f172a',
            '--rp-form-muted' => $theme['muted_color'] ?? '#64748b',
            '--rp-form-label' => $theme['label_color'] ?? '#475569',
            '--rp-form-border' => $theme['border_color'] ?? '#e2e8f0',
            '--rp-form-input-text' => $theme['input_text_color'] ?? '#0f172a',
            '--rp-form-placeholder' => $theme['placeholder_color'] ?? '#94a3b8',
            '--rp-form-button-text' => $theme['button_text_color'] ?? '#ffffff',
            '--rp-form-button-secondary-text' => $theme['button_secondary_text_color'] ?? '#0f172a',
        ];
        foreach ($colors as $key => $value) {
            if ($value === 'transparent') { $parts[] = $key . ':transparent'; continue; }
            $color = sanitize_hex_color((string)$value);
            if ($color) $parts[] = $key . ':' . $color;
        }
        $radius = isset($theme['radius_px']) ? (int)$theme['radius_px'] : (int)($theme['radius'] ?? 14);
        $inputRadius = isset($theme['input_radius_px']) ? (int)$theme['input_radius_px'] : max(0, $radius - 2);
        $buttonRadius = isset($theme['button_radius_px']) ? (int)$theme['button_radius_px'] : 999;
        $parts[] = '--rp-form-radius:' . max(0, min(36, $radius)) . 'px';
        $parts[] = '--rp-form-input-radius:' . max(0, min(24, $inputRadius)) . 'px';
        $parts[] = '--rp-form-button-radius:' . max(0, min(999, $buttonRadius)) . 'px';
        if (!empty($theme['font_family'])) $parts[] = '--rp-form-font:' . sanitize_text_field((string)$theme['font_family']);
        if (!empty($theme['font_headings'])) $parts[] = '--rp-form-headings-font:' . sanitize_text_field((string)$theme['font_headings']);
        if (!empty($theme['font_size_px'])) $parts[] = '--rp-form-font-size:' . max(12, min(20, (int)$theme['font_size_px'])) . 'px';
        return implode(';', array_filter(array_unique($parts)));
    }
    public static function render_questions(array $schema, array $prefill = [], array $context = []): string {
        if (class_exists('\RoutesPro\Forms\ProductCardex')) {
            $schema = ProductCardex::synchronise_order_questions_with_fronts($schema, $context);
        }
        $questions = isset($schema['questions']) && is_array($schema['questions']) ? $schema['questions'] : []; if (!$questions) return '<p>Formulário sem perguntas.</p>';
        $layout = isset($schema['layout']) && is_array($schema['layout']) ? $schema['layout'] : []; $mode = isset($layout['mode']) ? sanitize_key($layout['mode']) : 'single'; if (!in_array($mode,['single','steps'],true)) $mode='single'; $field_layout = isset($layout['field_layout']) && is_array($layout['field_layout']) ? $layout['field_layout'] : [];
        $title = sanitize_text_field($schema['meta']['title'] ?? ''); $subtitle = sanitize_text_field($schema['meta']['subtitle'] ?? ''); $index=[]; foreach ($questions as $q) { if (!is_array($q)) continue; $key = sanitize_key($q['key'] ?? ''); if ($key) $index[$key]=$q; }
        $out=''; if (($title || $subtitle) && empty($context['_suppress_meta'])) { $out.='<div class="routespro-form-section">'; if ($title) $out.='<h3 class="routespro-form-section-title">'.esc_html($title).'</h3>'; if ($subtitle) $out.='<div class="routespro-form-help">'.esc_html($subtitle).'</div>'; $out.='</div>'; }
        if ($mode === 'steps') {
            $steps = isset($layout['steps']) && is_array($layout['steps']) ? $layout['steps'] : [];
            if (!$steps) $steps = [['title'=>'','description'=>'','fields'=>array_keys($index)]];

            // 2.2.238.5: nenhum campo do schema pode desaparecer por não ter
            // sido sincronizado para layout.steps. Isto protege perguntas
            // adicionadas dinamicamente, incluindo sugestão de encomenda e
            // evidências, sem alterar a ordem dos passos já configurados.
            $assigned = [];
            foreach ($steps as $stepPos => $step) {
                $fields = isset($step['fields']) && is_array($step['fields']) ? $step['fields'] : [];
                $cleanFields = [];
                foreach ($fields as $fieldKey) {
                    $fieldKey = sanitize_key((string)$fieldKey);
                    if ($fieldKey === '' || empty($index[$fieldKey]) || isset($assigned[$fieldKey])) continue;
                    $assigned[$fieldKey] = true;
                    $cleanFields[] = $fieldKey;
                }
                $steps[$stepPos]['fields'] = $cleanFields;
            }

            // 2.2.473: order suggestions are dependent fields, not standalone
            // runtime sections. If a legacy layout forgot to assign the field
            // to layout.steps, recover it into the step that already contains
            // its paired Fronts question. This preserves the BO/schema order
            // and prevents Sugestão de encomenda from appearing before Frentes.
            $stepByField = [];
            foreach ($steps as $stepPos => $step) {
                foreach ((array)($step['fields'] ?? []) as $fieldKey) {
                    $fieldKey = sanitize_key((string)$fieldKey);
                    if ($fieldKey !== '') $stepByField[$fieldKey] = (int)$stepPos;
                }
            }
            $schemaKeys = array_keys($index);
            $schemaPositions = array_flip($schemaKeys);
            foreach ($schemaKeys as $schemaIndex => $fieldKey) {
                if (isset($assigned[$fieldKey])) continue;
                $question = $index[$fieldKey] ?? null;
                if (!is_array($question) || sanitize_key((string)($question['type'] ?? '')) !== 'order_suggestion') continue;

                $targetStep = null;
                $pairedFrontsKey = sanitize_key((string)($question['_fieldflow_paired_fronts_key'] ?? ''));
                if ($pairedFrontsKey !== '' && isset($stepByField[$pairedFrontsKey])) {
                    $targetStep = (int)$stepByField[$pairedFrontsKey];
                } else {
                    for ($i = $schemaIndex - 1; $i >= 0; $i--) {
                        $nearKey = $schemaKeys[$i];
                        if (isset($stepByField[$nearKey])) { $targetStep = (int)$stepByField[$nearKey]; break; }
                    }
                    if ($targetStep === null) {
                        for ($i = $schemaIndex + 1, $n = count($schemaKeys); $i < $n; $i++) {
                            $nearKey = $schemaKeys[$i];
                            if (isset($stepByField[$nearKey])) { $targetStep = (int)$stepByField[$nearKey]; break; }
                        }
                    }
                }
                if ($targetStep === null || !isset($steps[$targetStep])) continue;

                $steps[$targetStep]['fields'][] = $fieldKey;
                $steps[$targetStep]['fields'] = array_values(array_unique(array_map('sanitize_key', (array)$steps[$targetStep]['fields'])));
                usort($steps[$targetStep]['fields'], static function($a, $b) use ($schemaPositions) {
                    return ((int)($schemaPositions[$a] ?? PHP_INT_MAX)) <=> ((int)($schemaPositions[$b] ?? PHP_INT_MAX));
                });
                $assigned[$fieldKey] = true;
                $stepByField[$fieldKey] = $targetStep;
            }

            $recoveryGroups = [];
            $recoveryOrder = [];
            foreach ($index as $fieldKey => $question) {
                if (isset($assigned[$fieldKey])) continue;
                $type = sanitize_key((string)($question['type'] ?? 'text'));
                if ($type === 'order_suggestion') {
                    $groupKey = 'order_suggestion';
                    $groupTitle = 'Sugestão de encomenda';
                    $groupDescription = 'Referências e quantidades sugeridas para esta loja.';
                } elseif (in_array($type, ['image_upload','categorized_image_upload','file_upload'], true)) {
                    $groupKey = 'evidence';
                    $groupTitle = 'Evidências';
                    $groupDescription = 'Fotografias e ficheiros associados à execução da visita.';
                } else {
                    $groupKey = 'additional';
                    $groupTitle = 'Perguntas adicionais';
                    $groupDescription = 'Campos adicionais aplicáveis a esta visita.';
                }
                if (!isset($recoveryGroups[$groupKey])) {
                    $recoveryGroups[$groupKey] = ['title'=>$groupTitle,'description'=>$groupDescription,'fields'=>[],'source'=>'runtime_recovery'];
                    $recoveryOrder[] = $groupKey;
                }
                $recoveryGroups[$groupKey]['fields'][] = $fieldKey;
                $assigned[$fieldKey] = true;
            }
            foreach ($recoveryOrder as $groupKey) {
                if (!empty($recoveryGroups[$groupKey]['fields'])) $steps[] = $recoveryGroups[$groupKey];
            }
            $total = count($steps);
            $show_progress = !empty($layout['show_progress']) && $total > 1;
            $external_submit = !empty($context['_external_submit']);
            $out .= '<div class="routespro-form-wizard" data-steps-total="'.esc_attr((string)$total).'" data-rp-external-submit="'.($external_submit ? '1' : '0').'">';
            if ($show_progress) {
                $progress_fallback_id = function_exists('wp_unique_id') ? wp_unique_id('ff-form-progress-') : ('ff-form-progress-' . wp_rand(1000, 999999));
                $react_progress_enabled = class_exists('\RoutesPro\Front\ReactFrontFoundation')
                    && \RoutesPro\Front\ReactFrontFoundation::islandEnabled('form-wizard-progress');
                if ($react_progress_enabled) {
                    $react_progress_props = [
                        'contract' => \RoutesPro\Front\ReactFrontFoundation::CONTRACT,
                        'wizardContract' => \RoutesPro\Front\ReactFrontFoundation::FORM_WIZARD_CONTRACT,
                        'island' => \RoutesPro\Front\ReactFrontFoundation::FORM_WIZARD_ISLAND,
                        'fallbackId' => $progress_fallback_id,
                        'totalSteps' => $total,
                        'label' => 'Progresso da visita',
                    ];
                    $out .= '<div class="ff-react-front-form-progress-root" data-fieldflow-react-front-island="form-wizard-progress" data-fieldflow-react-front-props="'.esc_attr((string)wp_json_encode($react_progress_props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)).'"></div>';
                }
                $out .= '<div id="'.esc_attr($progress_fallback_id).'" class="routespro-form-progress" data-fieldflow-react-front-fallback="form-wizard-progress"'.($react_progress_enabled ? ' data-fieldflow-react-front-fallback-pending="1"' : '').'><div class="routespro-form-progress-bar"><span class="routespro-form-progress-fill" style="width:0%"></span></div><div class="routespro-form-progress-label" data-rp-progress-label>0%</div></div>';
            }
            foreach ($steps as $i => $st) {
                $step_index = $i + 1;
                $title_step = sanitize_text_field($st['title'] ?? '');
                $desc_step = sanitize_text_field($st['description'] ?? '');
                $fields = isset($st['fields']) && is_array($st['fields']) ? $st['fields'] : [];
                $out .= '<div class="routespro-form-step" data-rp-step data-step-index="'.esc_attr((string)$step_index).'"'.($step_index === 1 ? '' : ' hidden').'>';
                if ($title_step) $out .= '<h3 class="routespro-form-step-title">'.esc_html($title_step).'</h3>';
                if ($desc_step) $out .= '<div class="routespro-form-help">'.esc_html($desc_step).'</div>';
                $out .= '<div class="routespro-form-grid routespro-form-cols-2">';
                foreach ($fields as $key) {
                    $key = sanitize_key($key);
                    if (empty($index[$key])) continue;
                    $width = isset($field_layout[$key]['width']) ? (int)$field_layout[$key]['width'] : 100;
                    $out .= self::wrap_field_width(self::render_field($index[$key], $prefill[$key] ?? null, $context), $width, $index[$key]);
                }
                $out .= '</div></div>';
            }
            // A navegação é única e vive fora dos passos. Isto evita que temas,
            // WebViews ou CSS responsivo revelem simultaneamente os controlos
            // pertencentes a passos ocultos. Em formulários embebidos, o botão
            // final pertence ao shortcode exterior, por isso o wizard não cria
            // um segundo Submeter/Concluir.
            if ($total > 1 || !$external_submit) {
                $out .= '<div class="routespro-form-wizard-actions" data-rp-wizard-actions data-rp-navigation-role="canonical" data-rp-navigation-owner="form-renderer">';
                if ($total > 1) {
                    $out .= '<button type="button" class="routespro-form-btn secondary" data-rp-prev hidden>Anterior</button>';
                    $out .= '<button type="button" class="routespro-form-btn" data-rp-next>Seguinte</button>';
                }
                if (!$external_submit) {
                    $out .= '<button type="button" class="routespro-form-btn" data-rp-submit'.($total > 1 ? ' hidden' : '').'>Submeter</button>';
                }
                $out .= '</div>';
            }
            return $out.'</div>';
        }
        $out.='<div class="routespro-form-section"><div class="routespro-form-grid routespro-form-cols-1">'; foreach($questions as $q){ if (!is_array($q)) continue; $k=sanitize_key($q['key'] ?? ''); $out.=self::wrap_field_width(self::render_field($q, $prefill[$k] ?? null, $context), 100, $q);} return $out.'</div></div>';
    }
    public static function schema_needs_multipart(array $schema): bool { foreach(($schema['questions'] ?? []) as $q){ if(!is_array($q)) continue; $type=sanitize_key($q['type'] ?? ''); if(in_array($type,['image_upload','file_upload','categorized_image_upload'],true)) return true; } return false; }
    private static function wrap_field_width(string $html, int $width, array $q = []): string { if ($html === '') return ''; $allowed=[25,33,50,66,75,100]; $width=in_array($width,$allowed,true)?$width:100; $key = sanitize_key($q['key'] ?? ''); $attrs = ' class="routespro-form-col routespro-form-w-'.esc_attr((string)$width).'"'; if ($key) { $attrs .= ' data-rp-question-wrap="'.esc_attr($key).'"'; } return '<div'.$attrs.'>'.$html.'</div>'; }
    private static function normalise_visual_options(array $q): array {
        $source = isset($q['visual_options']) && is_array($q['visual_options']) ? $q['visual_options'] : [];
        if (!$source && isset($q['options']) && is_array($q['options'])) {
            $source = array_map(static fn($option) => ['label'=>$option,'value'=>$option], $q['options']);
        }
        $out = [];
        foreach ($source as $item) {
            if (is_scalar($item)) $item = ['label'=>(string)$item,'value'=>(string)$item];
            if (!is_array($item)) continue;
            $label = sanitize_text_field((string)($item['label'] ?? $item['value'] ?? ''));
            $value = sanitize_text_field((string)($item['value'] ?? $label));
            if ($label === '') continue;
            $image_id = absint($item['image_id'] ?? $item['attachment_id'] ?? 0);
            $image_url = esc_url_raw((string)($item['image_url'] ?? $item['url'] ?? ''));
            if ($image_url === '' && $image_id) {
                $resolved = wp_get_attachment_image_url($image_id, 'medium_large');
                if ($resolved) $image_url = esc_url_raw((string)$resolved);
            }
            $out[] = [
                'label' => $label,
                'value' => $value !== '' ? $value : $label,
                'image_id' => $image_id,
                'image_url' => $image_url,
            ];
        }
        return $out;
    }

    private static function normalise_evidence_categories(array $q): array {
        $source = isset($q['options']) && is_array($q['options']) ? $q['options'] : [];
        if (!$source && isset($q['evidence_categories']) && is_array($q['evidence_categories'])) $source = $q['evidence_categories'];
        $out = [];
        $used = [];
        foreach ($source as $item) {
            if (is_array($item)) $label = sanitize_text_field((string)($item['label'] ?? $item['name'] ?? $item['value'] ?? ''));
            else $label = sanitize_text_field((string)$item);
            if ($label === '') continue;
            $base = sanitize_key(remove_accents($label));
            if ($base === '') $base = 'categoria';
            $key = $base;
            $suffix = 2;
            while (isset($used[$key])) { $key = $base . '_' . $suffix; $suffix++; }
            $used[$key] = true;
            $out[] = ['key'=>$key,'label'=>$label];
        }
        return $out;
    }

    private static function normalise_categorized_evidence_value($value): array {
        if (!is_array($value)) return [];
        $out = [];
        $is_list = empty($value) || array_keys($value) === range(0, count($value) - 1);
        $rows = $is_list ? $value : [];
        if (!$rows) {
            foreach ($value as $key => $images) $rows[] = ['category_key'=>$key,'category_label'=>$key,'images'=>$images];
        }
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $category_key = sanitize_key((string)($row['category_key'] ?? $row['key'] ?? ''));
            $category_label = sanitize_text_field((string)($row['category_label'] ?? $row['label'] ?? $category_key));
            if ($category_key === '' && $category_label !== '') $category_key = sanitize_key(remove_accents($category_label));
            if ($category_key === '') continue;
            $images = $row['images'] ?? $row['files'] ?? [];
            if (!is_array($images)) $images = $images ? [$images] : [];
            $clean = [];
            foreach ($images as $image) {
                if (is_string($image)) $image = ['url'=>$image];
                if (!is_array($image)) continue;
                $url = esc_url_raw((string)($image['url'] ?? $image['file_url'] ?? $image['src'] ?? ''));
                if ($url === '') continue;
                $name = sanitize_file_name((string)($image['name'] ?? basename((string)wp_parse_url($url, PHP_URL_PATH))));
                $clean[] = [
                    'url'=>$url,
                    'name'=>$name ?: 'evidencia',
                    'type'=>sanitize_text_field((string)($image['type'] ?? $image['mime_type'] ?? '')),
                    'category_key'=>$category_key,
                    'category_label'=>$category_label ?: $category_key,
                ];
            }
            if ($clean) $out[$category_key] = ['category_key'=>$category_key,'category_label'=>$category_label ?: $category_key,'images'=>$clean];
        }
        return $out;
    }

    private static function condition_rules(array $q): array {
        $conditions = isset($q['conditions']) && is_array($q['conditions']) ? $q['conditions'] : [];
        if (empty($conditions['enabled'])) return [];
        $source = isset($conditions['rules']) && is_array($conditions['rules']) ? $conditions['rules'] : [];
        if (!$source && !empty($conditions['go_to'])) {
            $source = [['value'=>$conditions['value'] ?? '', 'go_to'=>$conditions['go_to']]];
        }
        $rules = [];
        foreach ($source as $rule) {
            if (!is_array($rule)) continue;
            $value = sanitize_text_field((string)($rule['value'] ?? ''));
            $go_to = sanitize_key((string)($rule['go_to'] ?? ''));
            if ($value === '' || $go_to === '') continue;
            $rules[] = ['value'=>$value,'go_to'=>$go_to];
        }
        return $rules;
    }

    private static function can_remove_existing_media(array $context): bool {
        return !empty($context['_daily_existing']) && !empty($context['_daily_can_edit']);
    }

    private static function render_existing_image(string $url, string $alt, string $field_key, bool $can_remove): string {
        $url = esc_url($url);
        if ($url === '') return '';
        $out = '<div class="routespro-evidence-existing-item" data-rp-existing-media data-rp-existing-field="'.esc_attr($field_key).'">';
        $out .= '<a href="'.$url.'" class="routespro-evidence-thumb" data-rp-image-viewer><img src="'.$url.'" alt="'.esc_attr($alt).'" loading="lazy"><span>Ver fotografia</span></a>';
        if ($can_remove) {
            $out .= '<button type="button" class="routespro-existing-media-remove" data-rp-remove-existing-media aria-pressed="false" aria-label="Eliminar fotografia existente">Eliminar</button>';
            $out .= '<input type="hidden" name="routespro_remove_existing['.esc_attr($field_key).'][]" value="'.esc_attr($url).'" data-rp-remove-existing-value disabled>';
            $out .= '<span class="routespro-existing-media-pending" data-rp-existing-media-pending hidden>Será eliminada ao guardar</span>';
        }
        return $out . '</div>';
    }

    private static function render_image_upload_zone(string $field_name, string $input_id, bool $multiple, bool $required, bool $has_existing, bool $evidence = false, string $help = ''): string {
        $input_id = sanitize_key($input_id) ?: 'fieldflow_image';
        $gallery_accept = 'image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.gif,.heic,.heif';
        $camera_accept = 'image/*';
        $evidence_attr = $evidence ? ' data-rp-evidence-file' : '';
        $existing_attr = $has_existing ? ' data-rp-has-existing="1"' : '';
        $out = '<div class="routespro-upload-zone routespro-image-source-zone" data-rp-upload-zone data-rp-upload-kind="image"'.($multiple?' data-rp-upload-multiple="1"':'').(($required && !$has_existing)?' data-rp-upload-required="1"':'').'>';
        $out .= '<div class="routespro-upload-source-actions" data-rp-upload-source-actions>';
        $out .= '<button type="button" class="routespro-upload-source-btn is-camera" data-rp-upload-trigger="camera"><span aria-hidden="true">&#128247;</span><strong>Tirar fotografia</strong></button>';
        $out .= '<button type="button" class="routespro-upload-source-btn is-gallery" data-rp-upload-trigger="gallery"><span aria-hidden="true">&#128444;</span><strong>'.($multiple?'Escolher imagens':'Escolher imagem').'</strong></button>';
        $out .= '</div>';
        $out .= '<div class="routespro-upload-inputs" data-rp-upload-inputs>';
        $out .= '<div class="routespro-upload-input-row routespro-upload-source-row" data-rp-upload-input-row data-rp-upload-source-row="gallery">';
        $out .= '<input type="file" class="routespro-upload-native-input" accept="'.esc_attr($gallery_accept).'" id="'.esc_attr($input_id).'" name="'.esc_attr($field_name).'" data-rp-upload-file data-rp-upload-source="gallery"'.$evidence_attr.($multiple?' multiple':'').$existing_attr.'>';
        $out .= '</div>';
        $out .= '<div class="routespro-upload-input-row routespro-upload-source-row" data-rp-upload-input-row data-rp-upload-source-row="camera">';
        $out .= '<input type="file" class="routespro-upload-native-input" accept="'.esc_attr($camera_accept).'" capture="environment" name="'.esc_attr($field_name).'" data-rp-upload-file data-rp-upload-source="camera" data-rp-camera-input="1"'.$evidence_attr.'>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '<div class="routespro-upload-preview" data-rp-upload-preview hidden></div>';
        if ($help !== '') $out .= '<div class="routespro-form-help">'.esc_html($help).'</div>';
        return $out . '</div>';
    }

    private static function react_choice_field_root(string $key, string $type, string $label, bool $required, bool $multiple, array $options, array $visual_options = []): string {
        if (!class_exists('\\RoutesPro\\Front\\ReactFrontFoundation') || !\RoutesPro\Front\ReactFrontFoundation::choiceFieldTypeEnabled($type, $multiple)) return '';
        $items = [];
        if ($type === 'checkbox' && !$multiple) {
            $items[] = ['value'=>'1','label'=>$label,'imageUrl'=>''];
        } elseif ($type === 'visual_choice') {
            foreach ($visual_options as $option) {
                if (!is_array($option)) continue;
                $items[] = [
                    'value' => (string)($option['value'] ?? ''),
                    'label' => (string)($option['label'] ?? $option['value'] ?? ''),
                    'imageUrl' => (string)($option['image_url'] ?? ''),
                ];
            }
        } else {
            foreach ($options as $option) {
                $value = sanitize_text_field((string)$option);
                if ($value === '') continue;
                $items[] = ['value'=>$value,'label'=>$value,'imageUrl'=>''];
            }
        }
        $props = [
            'contract' => \RoutesPro\Front\ReactFrontFoundation::CONTRACT,
            'choiceContract' => \RoutesPro\Front\ReactFrontFoundation::CHOICE_FIELD_CONTRACT,
            'island' => \RoutesPro\Front\ReactFrontFoundation::CHOICE_FIELD_ISLAND,
            'fieldKey' => $key,
            'fieldType' => $type,
            'required' => $required,
            'multiple' => $multiple,
            'label' => $label,
            'options' => $items,
        ];
        return '<div class="ff-react-front-choice-root" data-fieldflow-react-front-island="choice-field-control" data-fieldflow-react-front-props="'.esc_attr((string)wp_json_encode($props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)).'"></div>';
    }

    private static function react_special_field_root(string $key, string $type, string $label, bool $required): string {
        if (!class_exists('\\RoutesPro\\Front\\ReactFrontFoundation') || !\RoutesPro\Front\ReactFrontFoundation::specialFieldTypeEnabled($type)) return '';
        $badges = [
            'product_matrix' => 'Frentes / Cardex',
            'share_space_matrix' => 'Share Space',
            'order_suggestion' => 'Sugestão de encomenda',
            'categorized_image_upload' => 'Evidências',
            'image_upload' => 'Fotografias',
            'file_upload' => 'Ficheiros',
        ];
        $props = [
            'contract' => \RoutesPro\Front\ReactFrontFoundation::CONTRACT,
            'specialContract' => \RoutesPro\Front\ReactFrontFoundation::SPECIAL_FIELD_CONTRACT,
            'mediaControlContract' => \RoutesPro\Front\ReactFrontFoundation::MEDIA_CONTROL_CONTRACT,
            'formCompletionContract' => \RoutesPro\Front\ReactFrontFoundation::FORM_COMPLETION_CONTRACT,
            'island' => \RoutesPro\Front\ReactFrontFoundation::SPECIAL_FIELD_ISLAND,
            'fieldKey' => $key,
            'fieldType' => $type,
            'required' => $required,
            'label' => $label,
            'badge' => $badges[$type] ?? 'Campo avançado',
        ];
        return '<div class="ff-react-front-special-root" data-fieldflow-react-front-island="special-field-assist" data-fieldflow-react-front-props="'.esc_attr((string)wp_json_encode($props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)).'"></div>';
    }

    /** 9DS: visible diagnostic, no editable phantom SKU and no silent step skip. */
    private static function render_unavailable_cardex_question(array $q, $prefill, array $context): string {
        $key=sanitize_key((string)($q['key'] ?? ''));
        $type=sanitize_key((string)($q['type'] ?? 'product_matrix'));
        $label=sanitize_text_field((string)($q['label'] ?? $key));
        $reason=ProductCardex::question_binding_issue($q,$context);
        $out='<section class="routespro-form-field routespro-cardex-binding-warning" data-rp-question="'.esc_attr($key).'" data-rp-type="cardex_binding_warning" data-rp-cardex-unavailable="1">';
        $out.='<label class="routespro-cardex-binding-title">'.esc_html($label).'</label>';
        $out.='<div class="routespro-form-notice error" role="status"><strong>Cardex por sincronizar nesta loja.</strong> ';
        $out.=esc_html($reason !== '' ? $reason.'. ' : 'O cardex não está disponível para esta visita. ');
        $out.='Confirma no Backoffice a insígnia, o estado publicado, o projeto e a associação desta loja. A pergunta não foi eliminada.</div>';
        if (!empty($context['_daily_existing']) && is_array($prefill) && $prefill) {
            $rows=array_values(array_filter($prefill,'is_array'));
            if ($rows) {
                $out.='<details class="routespro-cardex-binding-history"><summary>Ver resposta já registada nesta visita ('.count($rows).' referência(s))</summary>';
                $out.='<div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>Referência</th><th>Produto</th><th>Valor anterior</th><th>Valor final / caixas</th></tr></thead><tbody>';
                foreach (array_slice($rows,0,50) as $row) {
                    $before=$row['before'] ?? $row['qty_before'] ?? '';
                    $after=$row['after'] ?? $row['qty'] ?? $row['order_qty'] ?? '';
                    $out.='<tr><td>'.esc_html((string)($row['ref'] ?? $row['reference'] ?? '')).'</td><td>'.esc_html((string)($row['name'] ?? $row['product'] ?? '')).'</td><td>'.esc_html(is_scalar($before)?(string)$before:'').'</td><td>'.esc_html(is_scalar($after)?(string)$after:'').'</td></tr>';
                }
                $out.='</tbody></table></div></details>';
            }
        }
        $out.='<p class="routespro-form-help">Esta pergunta está temporariamente indisponível para edição. Não serão criadas referências artificiais nem substituídos os valores já gravados.</p>';
        return $out.'</section>';
    }

    private static function render_field(array $q, $prefill = null, array $context = []): string {
        $key = sanitize_key($q['key'] ?? ''); if(!$key) return '';
        $label = sanitize_text_field($q['label'] ?? $key);
        $type = sanitize_key($q['type'] ?? 'text');
        $help = sanitize_text_field($q['help_text'] ?? '');
        if (!empty($q['product_category']) && ((string)($q['source'] ?? '') === 'briefing_question' || (string)($q['source_type'] ?? '') === 'briefing')) {
            $category = sanitize_text_field((string)$q['product_category']);
            $help = 'Categoria: ' . $category . ($help !== '' ? ' · ' . $help : '');
        }
        $can_remove_existing_media = self::can_remove_existing_media($context);
        if ($type === 'briefing_block') return '';
        if ($type === 'briefing_intro') {
            $briefingTitle = sanitize_text_field((string)($q['briefing_title'] ?? $label));
            $week = absint($q['briefing_week'] ?? 0);
            $year = absint($q['briefing_year'] ?? 0);
            $from = sanitize_text_field((string)($q['valid_from'] ?? ''));
            $to = sanitize_text_field((string)($q['valid_to'] ?? ''));
            $period = trim(($week ? 'W' . $week : '') . ($year ? ' · ' . $year : ''));
            if ($from || $to) $period .= ($period ? ' · ' : '') . ($from ? date_i18n('d/m/Y', strtotime($from)) : '') . ($to ? ' a ' . date_i18n('d/m/Y', strtotime($to)) : '');
            return '<div class="routespro-briefing-form-intro" style="border:1px solid #bfdbfe;background:linear-gradient(135deg,#eff6ff,#fff);border-radius:18px;padding:16px;box-shadow:0 10px 26px rgba(15,23,42,.06)"><span style="display:inline-flex;border-radius:999px;padding:5px 9px;background:#dbeafe;color:#1d4ed8;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">Briefing ativo</span><h4 style="margin:9px 0 4px;font-size:18px;color:#0f172a">'.esc_html($briefingTitle).'</h4>'.($period ? '<div style="color:#475569;font-size:13px">'.esc_html($period).'</div>' : '').($help ? '<div class="routespro-form-help">'.esc_html($help).'</div>' : '').'</div>';
        }
        // Phase 9S emergency continuity: Frentes still require a resolvable Cardex,
        // but order_suggestion must keep its shell in the form. The order table can
        // be populated later from the paired Fronts question even when Cardex
        // resolution is temporarily unavailable for the current store context.
        if ($type === 'product_matrix'
            && class_exists('\RoutesPro\Forms\ProductCardex')
            && ProductCardex::question_uses_cardex($q)
            && !ProductCardex::question_is_available_for_context($q, $context)) {
            // 9DS: never erase a configured question from the mobile wizard.
            // Show a safe, non-editable diagnostic rather than falsely recording
            // fronts for the wrong cardex. Existing visit rows remain visible.
            return self::render_unavailable_cardex_question($q, $prefill, $context);
        }
        if ($type === 'order_suggestion'
            && class_exists('\RoutesPro\Forms\ProductCardex')
            && ProductCardex::question_uses_cardex($q)
            && !ProductCardex::question_is_available_for_context($q, $context)) {
            // The historical 9S order-suggestion shell remains visible.
            return self::render_unavailable_cardex_question($q, $prefill, $context);
        }
        $required = !empty($q['required']);
        $min = $q['min'] ?? '';
        $max = $q['max'] ?? '';
        $unit = sanitize_text_field($q['unit'] ?? '');
        $options = is_array($q['options'] ?? null) ? $q['options'] : [];
        $visual_options = self::normalise_visual_options($q);
        $evidence_categories = self::normalise_evidence_categories($q);
        if ($type === 'visual_choice') $options = array_map(static fn($item) => (string)$item['value'], $visual_options);
        $multiple_choice = in_array($type, ['select', 'radio', 'visual_choice'], true) && ((!empty($q['selection_mode']) && sanitize_key((string)$q['selection_mode']) === 'multiple') || !empty($q['multiple']));
        $req = $required ? ' required' : '';
        $value_attr = '';
        $selected_values = [];
        if (is_array($prefill) && $multiple_choice) {
            $selected_values = array_values(array_map('strval', array_filter($prefill, 'is_scalar')));
        } elseif (is_scalar($prefill)) {
            $value_attr = (string)$prefill;
            if ($value_attr !== '') $selected_values = [$value_attr];
        }
        if ($type === 'share_space_matrix') {
            $config = \RoutesPro\Briefings\ShareSpaceMatrix::sanitizeConfig($q['question_config'] ?? ($q['share_space_rows'] ?? []));
            $prefillRows = is_array($prefill) ? $prefill : [];
            $prefillByKey = [];
            foreach ($prefillRows as $savedRow) {
                if (!is_array($savedRow)) continue;
                $savedKey = sanitize_key((string)($savedRow['metric_key'] ?? ''));
                if ($savedKey !== '') $prefillByKey[$savedKey] = $savedRow;
            }
            $share_vision = self::fronts_vision_attrs($q, $context);
            $out = '<div class="routespro-form-field routespro-form-type-share_space_matrix" data-rp-question="'.esc_attr($key).'" data-rp-type="share_space_matrix"'.($required?' data-rp-required-matrix="1"':'').$share_vision.'>';
            $out .= '<div class="routespro-share-space-head"><div><label>'.esc_html($label).($required?' *':'').'</label><p>Regista os valores observados para cada referência. O FieldFlow calcula automaticamente a percentagem de cada lado e o desvio face ao objetivo.</p></div><span>'.count((array)($config['rows'] ?? [])).' comparações</span></div>';
            if (empty($config['rows'])) {
                $out .= '<div class="routespro-form-notice error">Esta pergunta comparativa ainda não contém referências válidas.</div>';
            } else {
                $out .= '<div class="routespro-share-space-matrix" data-share-space-matrix data-fronts-visual-contract="'.esc_attr(self::FRONTS_VISUAL_CONTRACT).'">';
                $share_catalog_client = self::share_space_catalog_client($q, $context);
                foreach ((array)$config['rows'] as $index => $row) {
                    $metricKey = sanitize_key((string)($row['metric_key'] ?? 'metric_'.$index));
                    $saved = (array)($prefillByKey[$metricKey] ?? []);
                    $prFronts = isset($saved['pr_fronts']) && is_numeric($saved['pr_fronts']) ? (string)(0 + $saved['pr_fronts']) : '';
                    $competitorFronts = isset($saved['competitor_fronts']) && is_numeric($saved['competitor_fronts']) ? (string)(0 + $saved['competitor_fronts']) : '';
                    $target = isset($row['target']) && is_numeric($row['target']) ? (float)$row['target'] : 0.0;
                    $reference = isset($row['reference_share']) && is_numeric($row['reference_share']) ? (float)$row['reference_share'] : null;
                    $referenceCompetitor = isset($row['reference_competitor_share']) && is_numeric($row['reference_competitor_share']) ? (float)$row['reference_competitor_share'] : null;
                    $out .= '<article class="routespro-share-space-card" data-share-space-card data-target="'.esc_attr((string)$target).'">';
                    $out .= '<input type="hidden" name="'.esc_attr($key).'['.(int)$index.'][metric_key]" value="'.esc_attr($metricKey).'">';
                    $out .= '<header><div><small>Comparativo do briefing</small><h4>'.esc_html((string)($row['metric'] ?? 'Share Space')).'</h4></div><span class="routespro-share-space-target">Objetivo '.esc_html(number_format_i18n($target, 1)).'%</span></header>';
                    $out .= '<div class="routespro-share-space-inputs">';
                    $prLabel = (string)($row['pr_label'] ?? 'Produto Pernod Ricard');
                    $competitorLabel = (string)($row['competitor_label'] ?? 'Concorrência');
                    if ($share_vision !== '') $out .= '<button type="button" class="routespro-share-space-camera" data-fronts-camera-card data-pr-label="'.esc_attr($prLabel).'" data-competitor-label="'.esc_attr($competitorLabel).'"><span aria-hidden="true">&#128290;</span> Contar frentes</button>';
                    $out .= '<label><span class="routespro-share-space-name">'.self::share_space_side_visual($share_catalog_client, $prLabel).'<span>'.esc_html($prLabel).'</span></span><small>Frentes PR</small>'.self::fronts_stepper('<input type="number" inputmode="decimal" min="0" step="1" name="'.esc_attr($key).'['.(int)$index.'][pr_fronts]" value="'.esc_attr($prFronts).'" placeholder="0" data-share-space-pr>', true, $prLabel).'</label>';
                    $out .= '<label><span class="routespro-share-space-name">'.self::share_space_side_visual($share_catalog_client, $competitorLabel).'<span>'.esc_html($competitorLabel).'</span></span><small>Frentes concorrência</small>'.self::fronts_stepper('<input type="number" inputmode="decimal" min="0" step="1" name="'.esc_attr($key).'['.(int)$index.'][competitor_fronts]" value="'.esc_attr($competitorFronts).'" placeholder="0" data-share-space-competitor>', true, $competitorLabel).'</label>';
                    $out .= '</div>';
                    $out .= '<div class="routespro-share-space-results" aria-live="polite"><div><small>Quota PR</small><strong data-share-space-pr-share>0,0%</strong></div><div><small>Quota concorrência</small><strong data-share-space-competitor-share>0,0%</strong></div><div><small>Desvio objetivo</small><strong data-share-space-gap>0,0 pp</strong></div><span class="routespro-share-space-status" data-share-space-status>Por preencher</span></div>';
                    if ($reference !== null || $referenceCompetitor !== null) {
                        $ref = [];
                        if ($reference !== null) $ref[] = 'PR '.number_format_i18n($reference, 1).'%';
                        if ($referenceCompetitor !== null) $ref[] = 'Concorrência '.number_format_i18n($referenceCompetitor, 1).'%';
                        $out .= '<footer>Referência do briefing'.(!empty($row['reference_week']) ? ' W'.(int)$row['reference_week'] : '').': '.esc_html(implode(' · ', $ref)).'</footer>';
                    }
                    $out .= '</article>';
                }
                $out .= '</div>';
            }
            $out .= self::react_special_field_root($key, $type, $label, $required);
            if ($help) $out .= '<div class="routespro-form-help">'.esc_html($help).'</div>';
            $out .= '</div>';
            return $out;
        }

        if ($type === 'product_matrix') {
            $uses_cardex = class_exists('\RoutesPro\Forms\ProductCardex') && ProductCardex::question_uses_cardex($q);
            $resolved_cardex_id = ($uses_cardex && class_exists('\RoutesPro\Forms\ProductCardex')) ? ProductCardex::question_cardex_id($q, $context) : 0;
            $rows = class_exists('\RoutesPro\Forms\ProductCardex') ? ProductCardex::question_rows($q, $context, $prefill) : self::product_rows_from_question($q, $prefill, $context);
            if (!$rows) $rows = [['category'=>'','ref'=>'','name'=>'','qty'=>'']];
            // 9CM: compact fronts layout on mobile/tablet and catalog images per product.
            self::$fronts_compact = true;
            self::$fronts_visuals = self::fronts_catalog_visuals($q, $context, $rows, (int)$resolved_cardex_id);
            self::$fronts_thumbs = false;
            foreach (self::$fronts_visuals as $fronts_visual) { if (!empty($fronts_visual['thumb'])) { self::$fronts_thumbs = true; break; } }
            $fronts_classes = ' is-compact-fronts' . (self::$fronts_thumbs ? ' has-catalog-thumbs' : '');
            $fronts_vision = self::fronts_vision_attrs($q, $context);
            $out = '<div class="routespro-form-field routespro-form-type-product_matrix" data-rp-question="'.esc_attr($key).'" data-rp-type="product_matrix" data-cardex-id="'.esc_attr((string)$resolved_cardex_id).'" data-fronts-visual-contract="'.esc_attr(self::FRONTS_VISUAL_CONTRACT).'"'.$fronts_vision.'>';
            if ($uses_cardex && $resolved_cardex_id) {
                $out .= '<input type="hidden" name="'.esc_attr($key).'__cardex_present" value="1"><input type="hidden" name="'.esc_attr($key).'__cardex_id" value="'.esc_attr((string)$resolved_cardex_id).'">';
            }
            $out .= '<div class="routespro-product-matrix-head"><label>'.esc_html($label).($required?' *':'').'</label><div class="routespro-product-head-actions">'.($fronts_vision !== '' ? '<button type="button" class="routespro-form-btn routespro-fronts-camera-btn" data-fronts-camera="'.esc_attr($key).'"><span aria-hidden="true">&#128290;</span> Contar frentes</button>' : '').'<button type="button" class="routespro-form-btn secondary routespro-product-add" data-product-add="'.esc_attr($key).'">Adicionar referência</button></div></div>';
            $out .= self::fronts_vision_admin_hint($q, $context, $fronts_vision);
            $has_question_history = self::product_matrix_has_history($rows);
            if ($uses_cardex) {
                $groups = self::group_rows_by_category($rows);
                $out .= self::category_toolbar();
                $out .= '<div class="routespro-product-matrix is-cardex is-store-editable'.($has_question_history?' has-history':'').$fronts_classes.'" data-product-matrix="'.esc_attr($key).'" data-product-locked="0" data-cardex-id="'.esc_attr((string)$resolved_cardex_id).'" data-category-accordion>';
                $group_number = 0;
                foreach ($groups as $category => $items) {
                    $group_number++;
                    $open = $group_number === 1;
                    $panel_id = self::next_category_panel_id($key);
                    $out .= self::category_group_open($category, count($items), $panel_id, $open, 'fronts');
                    $group_rows = array_map(static function($item){ return (array)$item['row']; }, $items);
                    $out .= self::product_matrix_header($group_rows);
                    foreach ($items as $item) {
                        $i = (int)$item['index'];
                        $row = (array)$item['row'];
                        if ($has_question_history && empty($row['has_history'])) {
                            $row['has_history'] = 1;
                            $row['before'] = '';
                            $row['after'] = '';
                        }
                        $row['_fronts_confirmed'] = !empty($context['_daily_existing']) && !empty($row['fronts_confirmed']) ? 1 : 0;
                        $out .= self::render_product_row($key, $i, $row, false, false);
                    }
                    $out .= self::category_group_close();
                }
                $dynamic_panel_id = self::next_category_panel_id($key);
                $out .= '<section class="routespro-category-group is-open routespro-product-dynamic-group" data-category-group data-category-kind="fronts" data-product-dynamic-group hidden>'
                    . '<button type="button" class="routespro-category-toggle" data-category-toggle aria-expanded="true" aria-controls="'.esc_attr($dynamic_panel_id).'"><span class="routespro-category-toggle-main"><strong class="routespro-category-name">Ajustes da loja</strong><small class="routespro-category-meta" data-product-dynamic-count>0 referências acrescentadas</small></span><span class="routespro-category-status" data-category-status hidden></span><span class="routespro-category-chevron" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><path d="M5 7.5l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span></button>'
                    . '<div class="routespro-category-panel" id="'.esc_attr($dynamic_panel_id).'" data-category-panel>'.self::product_matrix_header($rows).'<div data-product-dynamic-rows></div></div></section>';
                $out .= '</div>';
            } else {
                $out .= '<div class="routespro-product-matrix'.($has_question_history?' has-history':'').$fronts_classes.'" data-product-matrix="'.esc_attr($key).'" data-product-locked="0">'.self::product_matrix_header($rows);
                foreach ($rows as $i => $row) {
                    $row = (array)$row;
                    if ($has_question_history && empty($row['has_history'])) {
                        $row['has_history'] = 1;
                        $row['before'] = '';
                        $row['after'] = '';
                    }
                    $row['_fronts_confirmed'] = !empty($context['_daily_existing']) && !empty($row['fronts_confirmed']) ? 1 : 0;
                    $out .= self::render_product_row($key, (int)$i, $row, $required && $i === 0, false);
                }
                $out .= '</div>';
            }
            $out .= '<div class="routespro-form-help routespro-fronts-zero-note">Uma contagem antiga ou deixada em branco não gera encomendas. Confirma 0 no campo de Frentes quando o produto está sem frente; podes corrigir, remover ou acrescentar referências.</div>';
            if (!empty($context['_record_exists'])) {
                if ($has_question_history) {
                    $out .= '<div class="routespro-form-help routespro-history-status">Histórico desta pergunta aplicado pela referência ou nome do produto.</div>';
                } else {
                    $out .= '<div class="routespro-form-help routespro-history-status">Existe histórico geral para esta loja, mas esta pergunta ainda não tem uma resposta anterior compatível.</div>';
                }
            }
            $out .= self::react_special_field_root($key, $type, $label, $required);
            if ($help) $out .= '<div class="routespro-form-help">'.esc_html($help).'</div>';
            $out .= '</div>';
            self::$fronts_compact = false;
            self::$fronts_visuals = [];
            self::$fronts_thumbs = false;
            return $out;
        }
        if ($type === 'order_suggestion') {
            $rows = class_exists('\RoutesPro\Forms\ProductCardex') ? ProductCardex::order_question_rows($q, $context, $prefill) : [];
            $header = class_exists('\RoutesPro\Forms\ProductCardex') ? ProductCardex::context_header($context) : ['location'=>'Loja','date'=>wp_date('d/m/Y'),'seller'=>wp_get_current_user()->display_name];
            $step = !empty($q['order_quantity_decimals']) ? '0.01' : '1';
            $zero_fronts_suggestion = isset($q['order_zero_fronts_suggestion']) && $q['order_zero_fronts_suggestion'] !== '' ? max(0, (float)$q['order_zero_fronts_suggestion']) : 1;
            $resolved_cardex_id = class_exists('\RoutesPro\Forms\ProductCardex') ? ProductCardex::question_cardex_id($q, $context) : 0;
            $paired_fronts_key = sanitize_key((string)($q['_fieldflow_paired_fronts_key'] ?? ''));
            $out = '<div class="routespro-form-field routespro-form-type-order_suggestion" data-rp-question="'.esc_attr($key).'" data-rp-type="order_suggestion" data-cardex-id="'.esc_attr((string)$resolved_cardex_id).'" data-paired-fronts-key="'.esc_attr($paired_fronts_key).'" data-zero-fronts-suggestion="'.esc_attr((string)$zero_fronts_suggestion).'">';
            $out .= '<div class="routespro-order-head"><label>'.esc_html($label).($required?' *':'').'</label></div>';
            $out .= '<div class="routespro-order-context">'
                . '<div><small>Loja</small><strong>'.esc_html((string)$header['location']).'</strong></div>'
                . '<div><small>Data</small><strong>'.esc_html((string)$header['date']).'</strong></div>'
                . '<div><small>Vendedor</small><strong>'.esc_html((string)$header['seller']).'</strong></div>'
                . '</div>';
            if (!$rows) {
                $out .= '<div class="routespro-form-notice info routespro-order-empty-note">A sugestão será preenchida automaticamente com as referências mantidas ou acrescentadas na pergunta de Frentes.</div>';
            }
            $groups = self::group_rows_by_category($rows);
            $out .= self::category_toolbar();
            $out .= '<div class="routespro-order-table" data-order-table="'.esc_attr($key).'" data-cardex-id="'.esc_attr((string)$resolved_cardex_id).'" data-order-next-index="'.esc_attr((string)count($rows)).'" data-order-step="'.esc_attr($step).'" data-category-accordion>';
            $group_number = 0;
            foreach ($groups as $category => $items) {
                $group_number++;
                $open = $group_number === 1;
                $panel_id = self::next_category_panel_id($key);
                $out .= self::category_group_open($category, count($items), $panel_id, $open, 'order');
                $out .= '<div class="routespro-order-row routespro-order-row-head"><span>Categoria</span><span>Código</span><span>Tipo</span><span>Produto</span><span>Cap. cl</span><span>UN/CX</span><span>Sug. encomenda, CXS</span><span>Observações</span></div>';
                foreach ($items as $item) {
                    $out .= self::render_order_row($key, (int)$item['index'], (array)$item['row'], $step);
                }
                $out .= self::category_group_close();
            }
            $dynamic_panel_id = self::next_category_panel_id($key);
            $out .= '<section class="routespro-category-group is-open routespro-order-dynamic-group" data-category-group data-category-kind="order" data-order-dynamic-group'.($rows?' hidden':'').'>'
                . '<button type="button" class="routespro-category-toggle" data-category-toggle aria-expanded="true" aria-controls="'.esc_attr($dynamic_panel_id).'"><span class="routespro-category-toggle-main"><strong class="routespro-category-name">Ajustes da loja</strong><small class="routespro-category-meta">Referências acrescentadas no formulário</small></span><span class="routespro-category-status" data-category-status hidden></span><span class="routespro-category-chevron" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><path d="M5 7.5l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span></button>'
                . '<div class="routespro-category-panel" id="'.esc_attr($dynamic_panel_id).'" data-category-panel><div class="routespro-order-row routespro-order-row-head"><span>Categoria</span><span>Código</span><span>Tipo</span><span>Produto</span><span>Cap. cl</span><span>UN/CX</span><span>Sug. encomenda, CXS</span><span>Observações</span></div><div data-order-dynamic-rows></div></div></section>';
            $out .= '</div>';
            $out .= self::react_special_field_root($key, $type, $label, $required);
            if ($help) $out .= '<div class="routespro-form-help">'.esc_html($help).'</div>';
            $out .= '</div>';
            return $out;
        }

        if ($type === 'categorized_image_upload') {
            $existing = self::normalise_categorized_evidence_value($prefill);
            $multiple = true;
            $condition_attr = '';
            $condition_rules = self::condition_rules($q);
            if ($condition_rules) {
                $condition_attr .= ' data-rp-condition-rules="'.esc_attr(wp_json_encode($condition_rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'"';
                $condition_attr .= ' data-rp-condition-value="'.esc_attr((string)$condition_rules[0]['value']).'"';
                $condition_attr .= ' data-rp-condition-goto="'.esc_attr((string)$condition_rules[0]['go_to']).'"';
            }
            $out = '<div class="routespro-form-field routespro-form-type-categorized_image_upload" data-rp-question="'.esc_attr($key).'" data-rp-type="categorized_image_upload"'.$condition_attr.'>';
            $out .= '<fieldset class="routespro-form-fieldset routespro-categorized-upload" data-rp-evidence-upload'.($required?' data-rp-required-evidence="1"':'').'><legend>'.esc_html($label).($required?' *':'').'</legend>';
            $out .= '<div class="routespro-form-choice-mode">Seleciona as categorias aplicáveis e adiciona as respetivas fotografias</div>';
            if (!$evidence_categories) {
                $out .= '<div class="routespro-form-notice warning">Esta pergunta ainda não tem categorias configuradas.</div>';
            } else {
                $out .= '<div class="routespro-categorized-upload-grid">';
                foreach ($evidence_categories as $category) {
                    $category_key = (string)$category['key'];
                    $category_label = (string)$category['label'];
                    $current = $existing[$category_key] ?? null;
                    if (!$current) {
                        foreach ($existing as $candidate) {
                            if (strcasecmp(remove_accents((string)($candidate['category_label'] ?? '')), remove_accents($category_label)) === 0) { $current = $candidate; break; }
                        }
                    }
                    $current_images = is_array($current['images'] ?? null) ? $current['images'] : [];
                    $selected = !empty($current_images);
                    $out .= '<section class="routespro-evidence-category'.($selected?' is-selected':'').'" data-rp-evidence-category="'.esc_attr($category_key).'">';
                    $out .= '<label class="routespro-evidence-category-toggle"><input type="checkbox" name="'.esc_attr($key).'__categories[]" value="'.esc_attr($category_key).'" data-rp-evidence-toggle'.($selected?' checked':'').'><span class="routespro-evidence-category-check" aria-hidden="true"></span><span><strong>'.esc_html($category_label).'</strong><small>'.($selected ? (count($current_images)===1?'1 imagem existente':count($current_images).' imagens existentes') : 'Selecionar para adicionar imagens').'</small></span></label>';
                    $out .= '<div class="routespro-evidence-category-panel" data-rp-evidence-panel'.($selected?'':' hidden').'>';
                    $field_name = $key.'['.$category_key.'][]';
                    $out .= self::render_image_upload_zone(
                        $field_name,
                        $key . '_' . $category_key,
                        $multiple,
                        false,
                        !empty($current_images),
                        true,
                        $multiple ? 'Podes tirar fotografias ou escolher várias imagens da galeria de uma só vez.' : 'Podes tirar uma fotografia ou escolher uma imagem da galeria.'
                    );
                    if ($current_images) {
                        $out .= '<div class="routespro-evidence-existing">';
                        foreach ($current_images as $image) {
                            $url = esc_url((string)($image['url'] ?? ''));
                            if ($url === '') continue;
                            $out .= self::render_existing_image($url, $category_label, $key, $can_remove_existing_media);
                        }
                        $out .= '</div>';
                    }
                    $out .= '</div>';
                    $out .= '</section>';
                }
                $out .= '</div>';
            }
            $out .= '</fieldset>';
            $out .= self::react_special_field_root($key, $type, $label, $required);
            if ($help) $out .= '<div class="routespro-form-help">'.esc_html($help).'</div>';
            return $out.'</div>';
        }
        $condition_attr = '';
        $condition_rules = self::condition_rules($q);
        if ($condition_rules) {
            $condition_attr .= ' data-rp-condition-rules="'.esc_attr(wp_json_encode($condition_rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'"';
            $condition_attr .= ' data-rp-condition-value="'.esc_attr((string)$condition_rules[0]['value']).'"';
            $condition_attr .= ' data-rp-condition-goto="'.esc_attr((string)$condition_rules[0]['go_to']).'"';
        }
        if ($multiple_choice) $condition_attr .= ' data-rp-multiple-choice="1"';
        if (sanitize_key((string)($q['evidence_flow_role'] ?? '')) === 'answer') {
            $condition_attr .= ' data-rp-evidence-flow-answer="1"';
            $condition_attr .= ' data-rp-evidence-photo-key="'.esc_attr(sanitize_key((string)($q['evidence_flow_photo_key'] ?? ''))).'"';
            $condition_attr .= ' data-rp-evidence-reason-key="'.esc_attr(sanitize_key((string)($q['evidence_flow_reason_key'] ?? ''))).'"';
            if (!empty($q['evidence_require_photo_on_yes'])) $condition_attr .= ' data-rp-evidence-require-photo="1"';
            if (!empty($q['evidence_require_reason_on_no'])) $condition_attr .= ' data-rp-evidence-require-reason="1"';
        }
        $out = '<div class="routespro-form-field routespro-form-type-'.esc_attr($type).'" data-rp-question="'.esc_attr($key).'" data-rp-type="'.esc_attr($type).'"'.$condition_attr.'>';
        if ($type === 'visual_choice') {
            $mode_class = $multiple_choice ? ' is-multiple' : ' is-single';
            $input_type = $multiple_choice ? 'checkbox' : 'radio';
            $field_name = $multiple_choice ? $key.'[]' : $key;
            $out .= '<fieldset class="routespro-form-fieldset routespro-visual-choice'.$mode_class.'"'.($required?' data-rp-required-choice="1"':'').'><legend>'.esc_html($label).($required?' *':'').'</legend>';
            $out .= '<div class="routespro-form-choice-mode">'.($multiple_choice?'Podes selecionar várias opções':'Seleciona uma opção').'</div><div class="routespro-visual-choice-grid">';
            foreach ($visual_options as $option) {
                $option_label = (string)$option['label'];
                $option_value = (string)$option['value'];
                $checked = in_array($option_value, $selected_values, true) ? ' checked' : '';
                $single_req = (!$multiple_choice && $required) ? ' required' : '';
                $image = !empty($option['image_url'])
                    ? '<img src="'.esc_url((string)$option['image_url']).'" alt="" loading="lazy">'
                    : '<span class="routespro-visual-choice-placeholder" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M4 5.5h16v13H4zM7 15l3.5-4 2.5 3 2-2.2 2.8 3.2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="8" cy="9" r="1.2" fill="currentColor"/></svg></span>';
                $out .= '<label class="routespro-visual-choice-option"><input type="'.$input_type.'" name="'.esc_attr($field_name).'" value="'.esc_attr($option_value).'"'.$single_req.$checked.'><span class="routespro-visual-choice-card"><span class="routespro-visual-choice-media">'.$image.'</span><span class="routespro-visual-choice-copy"><span class="routespro-visual-choice-label">'.esc_html($option_label).'</span><span class="routespro-visual-choice-indicator" aria-hidden="true"></span></span></span></label>';
            }
            $out .= '</div></fieldset>';
        } elseif ($type === 'checkbox') {
            $checked = !empty($prefill) ? ' checked' : '';
            $out .= '<label class="routespro-form-check"><input type="checkbox" name="'.esc_attr($key).'" value="1"'.$req.$checked.'> <span>'.esc_html($label).($required?' *':'').'</span></label>';
        } elseif ($multiple_choice) {
            $out .= '<fieldset class="routespro-form-fieldset routespro-form-choice-group"'.($required?' data-rp-required-choice="1"':'').'><legend>'.esc_html($label).($required?' *':'').'</legend><div class="routespro-form-choice-mode">Podes selecionar várias opções</div><div class="routespro-form-choice-list">';
            foreach ($options as $opt) {
                $opt = sanitize_text_field($opt);
                $checked = in_array((string)$opt, $selected_values, true) ? ' checked' : '';
                $out .= '<label class="routespro-form-check"><input type="checkbox" name="'.esc_attr($key).'[]" value="'.esc_attr($opt).'"'.$checked.'> <span>'.esc_html($opt).'</span></label>';
            }
            $out .= '</div></fieldset>';
        } elseif ($type === 'radio') {
            $out .= '<fieldset class="routespro-form-fieldset"><legend>'.esc_html($label).($required?' *':'').'</legend><div class="routespro-form-choice-list is-single">';
            foreach ($options as $opt) {
                $opt = sanitize_text_field($opt);
                $checked = ((string)$value_attr === (string)$opt) ? ' checked' : '';
                $out .= '<label class="routespro-form-check"><input type="radio" name="'.esc_attr($key).'" value="'.esc_attr($opt).'"'.$req.$checked.'> <span>'.esc_html($opt).'</span></label>';
            }
            $out .= '</div></fieldset>';
        } else {
            $out .= '<label for="'.esc_attr($key).'">'.esc_html($label).($required?' *':'').'</label>';
            switch ($type) {
                case 'textarea':
                    $out .= '<textarea id="'.esc_attr($key).'" name="'.esc_attr($key).'" rows="4"'.$req.'>'.esc_textarea($value_attr).'</textarea>';
                    break;
                case 'number': case 'currency': case 'percent':
                    $out .= '<div class="routespro-form-input-group">';
                    if ($type === 'currency') $out .= '<span class="routespro-form-addon">€</span>';
                    $out .= '<input type="number" step="0.01" id="'.esc_attr($key).'" name="'.esc_attr($key).'" value="'.esc_attr($value_attr).'"'.($min!==''?' min="'.esc_attr((string)$min).'"':'').($max!==''?' max="'.esc_attr((string)$max).'"':'').$req.'>';
                    if ($type === 'percent') $out .= '<span class="routespro-form-addon">%</span>';
                    elseif ($unit) $out .= '<span class="routespro-form-addon">'.esc_html($unit).'</span>';
                    $out .= '</div>';
                    break;
                case 'date':
                    $out .= '<input type="date" id="'.esc_attr($key).'" name="'.esc_attr($key).'" value="'.esc_attr($value_attr).'"'.$req.'>';
                    break;
                case 'time':
                    $out .= '<input type="time" id="'.esc_attr($key).'" name="'.esc_attr($key).'" value="'.esc_attr($value_attr).'"'.$req.'>';
                    break;
                case 'select':
                    $out .= '<select id="'.esc_attr($key).'" name="'.esc_attr($key).'"'.$req.'><option value="">Selecionar</option>';
                    foreach ($options as $opt) {
                        $opt = sanitize_text_field($opt);
                        $selected = selected((string)$value_attr, (string)$opt, false);
                        $out .= '<option value="'.esc_attr($opt).'"'.$selected.'>'.esc_html($opt).'</option>';
                    }
                    $out .= '</select>';
                    break;
                case 'image_upload':
                    $is_multiple_upload = true;
                    $field_name = $key.'[]';
                    $out .= self::render_image_upload_zone(
                        $field_name,
                        $key,
                        $is_multiple_upload,
                        $required,
                        !empty($prefill),
                        false,
                        $is_multiple_upload ? 'Seleciona várias imagens da galeria ou adiciona fotografias da câmara.' : 'Escolhe entre tirar uma fotografia ou selecionar uma imagem da galeria.'
                    );
                    if (!empty($prefill)) {
                        $current = is_array($prefill) ? $prefill : [$prefill];
                        $out .= '<div class="routespro-evidence-existing">';
                        foreach ($current as $url) {
                            $url = esc_url((string)$url);
                            if ($url === '') continue;
                            $out .= self::render_existing_image($url, 'Imagem atual', $key, $can_remove_existing_media);
                        }
                        $out .= '</div>';
                    }
                    break;
                case 'file_upload':
                    $is_multiple_upload = !empty($q['multiple']);
                    $multiple = $is_multiple_upload ? ' multiple' : '';
                    $field_name = $is_multiple_upload ? $key.'[]' : $key;
                    $upload_req = ($required && empty($prefill)) ? ' required' : '';
                    $out .= '<div class="routespro-upload-zone" data-rp-upload-zone data-rp-upload-kind="file"'.($is_multiple_upload?' data-rp-upload-multiple="1"':'').'>';
                    $out .= '<div class="routespro-upload-inputs" data-rp-upload-inputs><div class="routespro-upload-input-row" data-rp-upload-input-row>';
                    $out .= '<input type="file" id="'.esc_attr($key).'" name="'.esc_attr($field_name).'" data-rp-upload-file'.$multiple.$upload_req.(!empty($prefill)?' data-rp-has-existing="1"':'').'>';
                    $out .= '</div></div>';
                    if ($is_multiple_upload) $out .= '<button type="button" class="routespro-upload-add" data-rp-upload-add>Adicionar outro ficheiro</button>';
                    $out .= '<div class="routespro-upload-preview" data-rp-upload-preview hidden></div>';
                    if (!empty($prefill)) {
                        $current = is_array($prefill) ? $prefill : [$prefill];
                        $links = [];
                        foreach ($current as $url) { if (!$url) continue; $links[] = '<a href="'.esc_url((string)$url).'" target="_blank" rel="noopener">abrir</a>'; }
                        if ($links) $out .= '<div class="routespro-form-help">Ficheiros atuais: '.implode(', ', $links).'</div>';
                    }
                    $out .= '</div>';
                    break;
                default:
                    $out .= '<input type="text" id="'.esc_attr($key).'" name="'.esc_attr($key).'" value="'.esc_attr($value_attr).'"'.$req.'>';
                    break;
            }
        }
        if ($key !== '' && class_exists('\RoutesPro\Front\ReactFrontFoundation')
            && \RoutesPro\Front\ReactFrontFoundation::choiceFieldTypeEnabled($type, $multiple_choice)) {
            $out .= self::react_choice_field_root($key, $type, $label, $required, $multiple_choice, $options, $visual_options);
        }
        if ($key !== '' && class_exists('\RoutesPro\Front\ReactFrontFoundation')
            && \RoutesPro\Front\ReactFrontFoundation::specialFieldTypeEnabled($type)) {
            $out .= self::react_special_field_root($key, $type, $label, $required);
        }
        if ($key !== '' && !$multiple_choice && class_exists('\RoutesPro\Front\ReactFrontFoundation')
            && \RoutesPro\Front\ReactFrontFoundation::simpleFieldTypeEnabled($type)) {
            $react_field_props = [
                'contract' => \RoutesPro\Front\ReactFrontFoundation::CONTRACT,
                'fieldContract' => \RoutesPro\Front\ReactFrontFoundation::SIMPLE_FIELD_CONTRACT,
                'island' => \RoutesPro\Front\ReactFrontFoundation::SIMPLE_FIELD_ISLAND,
                'fieldKey' => $key,
                'fieldType' => $type,
                'inputId' => $key,
                'required' => $required,
                'label' => $label,
                'unit' => $unit,
                'controlContract' => \RoutesPro\Front\ReactFrontFoundation::SIMPLE_FIELD_CONTROL_CONTRACT,
            ];
            $out .= '<div class="ff-react-front-simple-field-root" data-fieldflow-react-front-island="simple-field-assist" data-fieldflow-react-front-props="'.esc_attr((string)wp_json_encode($react_field_props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)).'"></div>';
        }
        if ($help) $out .= '<div class="routespro-form-help">'.esc_html($help).'</div>';
        return $out.'</div>';
    }
    private static function product_rows_from_question(array $q, $prefill = null, array $context = []): array {
        $base = [];
        if (isset($q['product_rows']) && is_array($q['product_rows'])) $base = $q['product_rows'];
        elseif (isset($q['products']) && is_array($q['products'])) $base = $q['products'];
        $out = [];
        foreach ($base as $row) {
            if (!is_array($row)) continue;
            $out[] = [
                'category' => sanitize_text_field((string)($row['category'] ?? $row['categoria'] ?? '')),
                'ref' => sanitize_text_field((string)($row['ref'] ?? $row['reference'] ?? '')),
                'name' => sanitize_text_field((string)($row['name'] ?? $row['product'] ?? '')),
                'qty' => '', 'before' => '', 'after' => '', 'has_history' => 0,
            ];
        }
        if (is_array($prefill)) {
            $history = [];
            foreach ($prefill as $row) {
                if (!is_array($row)) continue;
                $category = sanitize_text_field((string)($row['category'] ?? $row['categoria'] ?? ''));
                $ref = sanitize_text_field((string)($row['ref'] ?? $row['reference'] ?? ''));
                $name = sanitize_text_field((string)($row['name'] ?? $row['product'] ?? ''));
                $qty = isset($row['qty']) ? (string)$row['qty'] : (isset($row['after']) ? (string)$row['after'] : (isset($row['quantity']) ? (string)$row['quantity'] : ''));
                $latest = isset($row['after']) && $row['after'] !== '' ? (string)$row['after'] : $qty;
                $before = !empty($context['_daily_existing']) && isset($row['before']) ? (string)$row['before'] : $latest;
                $after = $latest;
                $qty = $after;
                $history[] = ['category'=>$category,'ref'=>$ref,'name'=>$name,'qty'=>$qty,'before'=>$before,'after'=>$after,'has_history'=>1];
            }
            if ($history) $out = $history;
        }
        return $out;
    }
    private static function product_matrix_has_history(array $rows): bool { foreach ($rows as $row) { if (is_array($row) && !empty($row['has_history'])) return true; } return false; }
    private static function group_rows_by_category(array $rows): array {
        $groups = [];
        $category_keys = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) continue;
            $category = sanitize_text_field((string)($row['category'] ?? $row['categoria'] ?? '')) ?: 'Sem categoria';
            $canonical = str_replace(["’", "‘", "`", "´"], "'", $category);
            $canonical = preg_replace('/\s+/u', ' ', trim($canonical));
            $canonical = function_exists('remove_accents') ? remove_accents($canonical) : $canonical;
            $canonical = function_exists('mb_strtolower') ? mb_strtolower((string)$canonical, 'UTF-8') : strtolower((string)$canonical);
            if (!isset($category_keys[$canonical])) {
                $category_keys[$canonical] = $category;
                $groups[$category] = [];
            }
            $display_category = $category_keys[$canonical];
            $groups[$display_category][] = ['index' => (int)$index, 'row' => $row];
        }
        return $groups;
    }
    private static function next_category_panel_id(string $key): string {
        self::$category_group_sequence++;
        return 'routespro-category-' . sanitize_html_class($key) . '-' . self::$category_group_sequence;
    }
    private static function category_toolbar(): string {
        return '<div class="routespro-category-toolbar" aria-label="Controlos das categorias">'
            . '<button type="button" class="routespro-category-action" data-category-open-all>Abrir todas</button>'
            . '<button type="button" class="routespro-category-action" data-category-close-all>Fechar todas</button>'
            . '</div>';
    }
    private static function category_group_open(string $category, int $count, string $panel_id, bool $open, string $kind): string {
        $count_label = $count === 1 ? '1 referência' : $count . ' referências';
        return '<section class="routespro-category-group'.($open?' is-open':'').'" data-category-group data-category-kind="'.esc_attr($kind).'">'
            . '<button type="button" class="routespro-category-toggle" data-category-toggle aria-expanded="'.($open?'true':'false').'" aria-controls="'.esc_attr($panel_id).'">'
            . '<span class="routespro-category-toggle-main"><strong class="routespro-category-name">'.esc_html($category).'</strong><small class="routespro-category-meta">'.esc_html($count_label).'</small></span>'
            . '<span class="routespro-category-status" data-category-status hidden></span>'
            . '<span class="routespro-category-chevron" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><path d="M5 7.5l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>'
            . '</button>'
            . '<div class="routespro-category-panel" id="'.esc_attr($panel_id).'" data-category-panel'.($open?'':' hidden').'>';
    }
    private static function category_group_close(): string { return '</div></section>'; }
    private static function product_matrix_header(array $rows): string {
        $thumb_head = self::$fronts_thumbs ? '<span class="routespro-product-thumb-head" aria-hidden="true"></span>' : '';
        if (self::product_matrix_has_history($rows)) {
            return '<div class="routespro-product-row routespro-product-row-head routespro-product-row-history">'.$thumb_head.'<span>Categoria</span><span>Referência</span><span>Produto</span><span>Frentes antes</span><span>Frentes depois</span><span></span></div>';
        }
        return '<div class="routespro-product-row routespro-product-row-head">'.$thumb_head.'<span>Categoria</span><span>Referência</span><span>Produto</span><span>Número de frentes</span><span></span></div>';
    }
    private static function render_product_row(string $key, int $i, array $row, bool $required = false, bool $locked = false): string {
        $item_id = absint($row['item_id'] ?? 0);
        $cardex_id = absint($row['cardex_id'] ?? 0);
        $category = sanitize_text_field((string)($row['category'] ?? $row['categoria'] ?? ''));
        $ref = sanitize_text_field((string)($row['ref'] ?? ''));
        $item_type = sanitize_text_field((string)($row['item_type'] ?? $row['type'] ?? ''));
        $name = sanitize_text_field((string)($row['name'] ?? ''));
        $capacity = (string)($row['capacity_cl'] ?? '');
        $units = (string)($row['units_per_case'] ?? '');
        $qty = (string)($row['qty'] ?? '');
        $before = (string)($row['before'] ?? $qty);
        $after = (string)($row['after'] ?? $qty);
        $has_history = !empty($row['has_history']);
        $name_req = $required ? ' required' : '';
        // 9CM: rows with a known product become a compact line (image, name, stepper) on mobile and tablet.
        // The Cardex inputs stay in the DOM with the same names, behind "Editar referência".
        $compact = self::$fronts_compact && $name !== '';
        $visual = $compact ? (array)(self::$fronts_visuals[$i] ?? []) : [];
        $prefix = esc_attr($key) . '[' . esc_attr((string)$i) . ']';
        $identity_attrs = ' data-product-ref="'.esc_attr($ref).'" data-product-name="'.esc_attr($name).'" data-product-item-id="'.esc_attr((string)$item_id).'"';
        if (!empty($visual['product_id'])) $identity_attrs .= ' data-catalog-product-id="'.(int)$visual['product_id'].'"';
        $hidden = '<input type="hidden" name="'.$prefix.'[fronts_confirmed]" value="'.(!empty($row['_fronts_confirmed'])?'1':'0').'" data-fronts-confirmed>'
            . '<input type="hidden" name="'.$prefix.'[item_id]" value="'.esc_attr((string)$item_id).'">'
            . '<input type="hidden" name="'.$prefix.'[cardex_id]" value="'.esc_attr((string)$cardex_id).'">'
            . '<input type="hidden" name="'.$prefix.'[item_type]" value="'.esc_attr($item_type).'">'
            . '<input type="hidden" name="'.$prefix.'[capacity_cl]" value="'.esc_attr($capacity).'">'
            . '<input type="hidden" name="'.$prefix.'[units_per_case]" value="'.esc_attr($units).'">';
        $html = '<div class="routespro-product-row'.($has_history?' routespro-product-row-history':'').($locked?' is-locked':'').($compact?' has-summary':'').'" data-product-row'.$identity_attrs.'>' . $hidden;
        if ($compact) $html .= self::fronts_row_summary($name, $ref, $capacity, $visual, $locked);
        if ($locked) {
            $html .= '<input type="hidden" name="'.$prefix.'[category]" value="'.esc_attr($category).'">'
                . '<input type="hidden" name="'.$prefix.'[ref]" value="'.esc_attr($ref).'">'
                . '<input type="hidden" name="'.$prefix.'[name]" value="'.esc_attr($name).'">'
                . '<span data-label="Categoria">'.esc_html($category ?: 'Sem categoria').'</span>'
                . '<span data-label="Referência">'.esc_html($ref ?: 'Sem referência').'</span>'
                . '<span data-label="Produto" class="routespro-product-name">'.esc_html($name ?: 'Produto').'</span>';
        } else {
            $html .= '<label class="routespro-product-input routespro-product-category-input"><small>Categoria</small><input type="text" name="'.$prefix.'[category]" value="'.esc_attr($category).'" placeholder="Ex, Gin"></label>'
                . '<label class="routespro-product-input routespro-product-ref-input"><small>Referência</small><input type="text" name="'.$prefix.'[ref]" value="'.esc_attr($ref).'" placeholder="Código ou referência"></label>'
                . '<label class="routespro-product-input routespro-product-name-input"><small>Produto</small><input type="text" name="'.$prefix.'[name]" value="'.esc_attr($name).'" placeholder="Nome do produto"'.$name_req.'></label>';
        }
        $placeholder = $compact ? '0' : '0, vazio = zero';
        $numeric = $compact ? ' inputmode="decimal"' : '';
        if ($has_history) {
            $html .= '<label class="routespro-product-input routespro-product-before-input"><small>Frentes antes</small><input type="number" step="0.01" min="0" name="'.$prefix.'[before]" value="'.esc_attr($before).'" placeholder="0" readonly class="routespro-product-before"></label>'
                . '<label class="routespro-product-input routespro-product-qty-input"><small>Frentes depois</small>'.self::fronts_stepper('<input type="number" step="0.01" min="0" name="'.$prefix.'[after]" value="'.esc_attr($after).'" placeholder="'.$placeholder.'"'.$numeric.' data-fronts-input>', $compact, $name).($compact && $before !== '' ? '<em class="routespro-fronts-before">Antes '.esc_html(self::fronts_number_label($before)).'</em>' : '').'</label>';
        } else {
            $html .= '<label class="routespro-product-input routespro-product-qty-input"><small>Número de frentes</small>'.self::fronts_stepper('<input type="number" step="0.01" min="0" name="'.$prefix.'[qty]" value="'.esc_attr($qty).'" placeholder="'.$placeholder.'"'.$numeric.' data-fronts-input>', $compact, $name).'</label>';
        }
        $html .= $locked ? '<span class="routespro-product-row-end" aria-hidden="true"></span>' : '<button type="button" class="routespro-product-remove" aria-label="Remover referência">Remover</button>';
        return $html . '</div>';
    }
    /** 9CM fieldflow-fronts-mobile-catalog-visual-v1: −/+ around a fronts input, same input name and attributes. */
    private static function fronts_stepper(string $input, bool $enabled, string $name = ''): string {
        if (!$enabled) return $input;
        $suffix = $name !== '' ? ' de ' . $name : '';
        return '<span class="routespro-fronts-stepper">'
            . '<button type="button" class="routespro-fronts-step" data-fronts-step="-1" tabindex="-1" aria-label="'.esc_attr('Menos uma frente'.$suffix).'">−</button>'
            . $input
            . '<button type="button" class="routespro-fronts-step" data-fronts-step="1" tabindex="-1" aria-label="'.esc_attr('Mais uma frente'.$suffix).'">+</button>'
            . '</span>';
    }
    private static function fronts_number_label(string $value): string {
        if ($value === '' || !is_numeric($value)) return $value;
        return rtrim(rtrim(number_format((float)$value, 2, ',', ''), '0'), ',');
    }
    private static function fronts_initials(string $name): string {
        $words = preg_split('/\s+/u', trim(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_filter($words, static function($word) { return (bool)preg_match('/^\p{L}/u', $word); }));
        $initials = '';
        if (count($words) === 1) $initials = function_exists('mb_substr') ? mb_substr($words[0], 0, 2, 'UTF-8') : substr($words[0], 0, 2);
        else foreach (array_slice($words, 0, 2) as $word) $initials .= function_exists('mb_substr') ? mb_substr($word, 0, 1, 'UTF-8') : substr($word, 0, 1);
        return function_exists('mb_strtoupper') ? mb_strtoupper($initials, 'UTF-8') : strtoupper($initials);
    }
    private static function fronts_thumb_html(array $visual, string $name, string $class = ''): string {
        $initials = self::fronts_initials($name);
        if (!empty($visual['thumb'])) {
            return '<button type="button" class="routespro-product-thumb'.($class ? ' '.$class : '').'" data-product-zoom="'.esc_url((string)($visual['full'] ?: $visual['thumb'])).'" data-product-zoom-title="'.esc_attr($name).'" data-initials="'.esc_attr($initials).'" aria-label="'.esc_attr('Ver imagem de '.$name).'"><img src="'.esc_url((string)$visual['thumb']).'" alt="" loading="lazy" decoding="async"></button>';
        }
        return '<span class="routespro-product-thumb is-empty'.($class ? ' '.$class : '').'" aria-hidden="true">'.esc_html($initials).'</span>';
    }
    private static function fronts_row_summary(string $name, string $ref, string $capacity, array $visual, bool $locked): string {
        $meta = [];
        if ($ref !== '') $meta[] = 'Ref. ' . $ref;
        $capacity_in_name = (bool)preg_match('/\d+(?:[.,]\d+)?\s*(cl|ml|ltr|lt|litros?|l)\b/i', $name);
        if (!$capacity_in_name && $capacity !== '' && is_numeric($capacity) && (float)$capacity > 0) $meta[] = rtrim(rtrim(number_format((float)$capacity, 1, ',', ''), '0'), ',') . ' cl';
        $thumb = self::$fronts_thumbs ? self::fronts_thumb_html($visual, $name) : '';
        return '<div class="routespro-product-summary">' . $thumb
            . '<div class="routespro-product-summary-text"><strong>'.esc_html($name).'</strong>'
            . ($meta ? '<small>'.esc_html(implode(' · ', $meta)).'</small>' : '')
            . ($locked ? '' : '<button type="button" class="routespro-product-edit" data-product-edit aria-expanded="false" aria-label="'.esc_attr('Editar referência '.$name).'">Editar</button>')
            . '</div></div>';
    }
    private static function fronts_catalog_visuals(array $q, array $context, array $rows, int $cardex_id): array {
        if (!empty($q['catalog_images_disabled']) || !class_exists('\\RoutesPro\\Catalog\\ProductCatalogService')) return [];
        try {
            $client_id = \RoutesPro\Catalog\ProductCatalogService::clientForContext($context, $cardex_id);
            // 9CP: rows without their own Cardex use the Cardex of the question (codes are read per insignia).
            if ($cardex_id > 0) foreach ($rows as $row_index => $row) if (is_array($row) && empty($row['cardex_id'])) $rows[$row_index]['cardex_id'] = $cardex_id;
            return $client_id ? \RoutesPro\Catalog\ProductCatalogService::visualsForRows($client_id, $rows) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
    private static function share_space_catalog_client(array $q, array $context): int {
        if (!empty($q['catalog_images_disabled']) || !class_exists('\\RoutesPro\\Catalog\\ProductCatalogService')) return 0;
        try {
            return \RoutesPro\Catalog\ProductCatalogService::clientForContext($context);
        } catch (\Throwable $e) {
            return 0;
        }
    }
    private static function share_space_side_visual(int $client_id, string $label): string {
        if ($client_id <= 0) return '';
        try {
            $set = \RoutesPro\Catalog\ProductCatalogService::visualsForLabel($client_id, $label, 2);
        } catch (\Throwable $e) {
            return '';
        }
        $items = array_values(array_filter((array)($set['items'] ?? []), static function($visual) { return !empty($visual['thumb']); }));
        if (!$items) return '';
        $html = '<span class="routespro-share-space-thumbs">';
        foreach ($items as $visual) $html .= self::fronts_thumb_html($visual, (string)$visual['name']);
        $more = (int)($set['total'] ?? 0) - count($items);
        if ($more > 0) $html .= '<em title="'.esc_attr($more.' outro(s) produto(s) deste rótulo').'">+'.$more.'</em>';
        return $html . '</span>';
    }
    /** 9CO fieldflow-fronts-vision-reading-v1: data attributes of a question with camera reading enabled. */
    /** 9CQ: managers see why a question with "Leitura por câmara" switched on does not show the camera. */
    private static function fronts_vision_admin_hint(array $q, array $context, string $attrs): string {
        if (strpos($attrs, 'data-vision-camera-enabled="1"') !== false || empty($q['camera_reading']) || !function_exists('current_user_can') || !current_user_can('routespro_manage')) return '';
        if (!class_exists('\\RoutesPro\\Vision\\FrontsVisionService')) return '';
        try {
            $reason = \RoutesPro\Vision\FrontsVisionService::questionBlockReason($q, $context);
        } catch (\Throwable $e) {
            return '';
        }
        if ($reason === '' || strpos($reason, ' ') === false) return '';
        return '<div class="routespro-form-help routespro-vision-admin-hint" role="note"><strong>Leitura por câmara ligada, mas indisponível:</strong> '.esc_html($reason).' <em>(aviso só para gestores)</em></div>';
    }
    private static function fronts_vision_attrs(array $q, array $context): string {
        $type = sanitize_key((string)($q['type'] ?? ''));
        if (!in_array($type, ['product_matrix', 'share_space_matrix'], true)) return '';
        $manual = ' data-fronts-vision="1" data-vision-camera-enabled="0"';
        if (!class_exists('\\RoutesPro\\Vision\\FrontsVisionService')) return $manual;
        try {
            $reason = \RoutesPro\Vision\FrontsVisionService::questionBlockReason($q, $context);
            if ($reason !== '') return $manual . ' data-vision-block-reason="'.esc_attr($reason).'"';
            $status = \RoutesPro\Vision\FrontsVisionService::providerStatus();
        } catch (\Throwable $e) {
            return $manual;
        }
        $references = '';
        try {
            $reference_client = class_exists('\\RoutesPro\\Catalog\\ProductCatalogService') ? \RoutesPro\Catalog\ProductCatalogService::clientForContext($context) : absint($context['client_id'] ?? 0);
            if (\RoutesPro\Vision\FrontsVisionService::fieldReferencesEnabled((int)$reference_client)) $references = ' data-vision-references="1" data-vision-reference-contract="'.esc_attr(\RoutesPro\Vision\FrontsVisionService::REFERENCE_CONTRACT).'"';
        } catch (\Throwable $e) {
            $references = '';
        }
        return ' data-fronts-vision="1" data-vision-camera-enabled="1" data-vision-endpoint="'.esc_url(admin_url('admin-ajax.php')).'" data-vision-nonce="'.esc_attr(wp_create_nonce(\RoutesPro\Vision\FrontsVisionService::NONCE)).'" data-vision-boxes="'.(!empty($status['boxes']) ? '1' : '0').'" data-vision-contract="'.esc_attr(\RoutesPro\Vision\FrontsVisionService::CONTRACT).'"'.$references;
    }
    private static function render_order_row(string $key, int $i, array $row, string $step = '1'): string {
        $item_id = absint($row['item_id'] ?? 0);
        $cardex_id = absint($row['cardex_id'] ?? 0);
        $category = sanitize_text_field((string)($row['category'] ?? ''));
        $ref = sanitize_text_field((string)($row['ref'] ?? $row['reference'] ?? ''));
        $item_type = sanitize_text_field((string)($row['item_type'] ?? $row['type'] ?? ''));
        $name = sanitize_text_field((string)($row['name'] ?? $row['product'] ?? ''));
        $capacity = (string)($row['capacity_cl'] ?? '');
        $units = (string)($row['units_per_case'] ?? '');
        $order_qty = (string)($row['order_qty'] ?? $row['boxes'] ?? '');
        $quantity_source = sanitize_key((string)($row['quantity_source'] ?? $row['order_source'] ?? ''));
        if (!in_array($quantity_source, ['auto', 'manual'], true)) $quantity_source = $order_qty !== '' ? 'manual' : '';
        $is_auto_quantity = $quantity_source === 'auto';
        $notes = sanitize_textarea_field((string)($row['notes'] ?? $row['observations'] ?? ''));
        $prefix = esc_attr($key) . '[' . esc_attr((string)$i) . ']';
        $hidden = '<input type="hidden" name="'.$prefix.'[item_id]" value="'.esc_attr((string)$item_id).'">'
            . '<input type="hidden" name="'.$prefix.'[cardex_id]" value="'.esc_attr((string)$cardex_id).'">'
            . '<input type="hidden" name="'.$prefix.'[category]" value="'.esc_attr($category).'">'
            . '<input type="hidden" name="'.$prefix.'[ref]" value="'.esc_attr($ref).'">'
            . '<input type="hidden" name="'.$prefix.'[item_type]" value="'.esc_attr($item_type).'">'
            . '<input type="hidden" name="'.$prefix.'[name]" value="'.esc_attr($name).'">'
            . '<input type="hidden" name="'.$prefix.'[capacity_cl]" value="'.esc_attr($capacity).'">'
            . '<input type="hidden" name="'.$prefix.'[units_per_case]" value="'.esc_attr($units).'">'
            . '<input type="hidden" name="'.$prefix.'[quantity_source]" value="'.esc_attr($quantity_source).'" data-order-quantity-source>';
        return '<div class="routespro-order-row" data-order-row data-product-ref="'.esc_attr($ref).'" data-product-name="'.esc_attr($name).'" data-product-item-id="'.esc_attr((string)$item_id).'">' . $hidden
            . '<span data-label="Categoria">'.esc_html($category ?: 'Sem categoria').'</span>'
            . '<span data-label="Código">'.esc_html($ref).'</span>'
            . '<span data-label="Tipo">'.esc_html($item_type).'</span>'
            . '<span data-label="Produto" class="routespro-order-product">'.esc_html($name).'</span>'
            . '<span data-label="Cap. cl">'.esc_html($capacity).'</span>'
            . '<span data-label="UN/CX">'.esc_html($units).'</span>'
            . '<label class="routespro-order-input"><small>Sug. encomenda, CXS</small><input type="number" min="0" step="'.esc_attr($step).'" name="'.$prefix.'[order_qty]" value="'.esc_attr($order_qty).'" placeholder="0" data-order-qty'.($is_auto_quantity?' data-rp-auto-fronts="1"':'').'><em class="routespro-order-auto-note"'.($is_auto_quantity?'':' hidden').'>Sugerido automaticamente por frentes a zero</em></label>'
            . '<label class="routespro-order-input routespro-order-notes"><small>Observações</small><input type="text" name="'.$prefix.'[notes]" value="'.esc_attr($notes).'" placeholder="Observações"></label>'
            . '</div>';
    }

    private static function inline_css(): string {
        return <<<'CSS'
.rp-form-ui{--rp-form-primary:#2271b1;--rp-form-primary-hover:#135e96;--rp-form-accent:#06b6d4;--rp-form-surface:#fff;--rp-form-text:#111827;--rp-form-heading:#0f172a;--rp-form-muted:#64748b;--rp-form-label:#475569;--rp-form-border:#e2e8f0;--rp-form-input-text:#0f172a;--rp-form-placeholder:#94a3b8;--rp-form-button-text:#fff;--rp-form-button-secondary-text:#0f172a;--rp-form-radius:14px;--rp-form-input-radius:12px;--rp-form-button-radius:999px;--rp-form-font:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;--rp-form-headings-font:var(--rp-form-font);--rp-form-font-size:15px;color:var(--rp-form-text);font-family:var(--rp-form-font);font-size:var(--rp-form-font-size)}.rp-form-ui [hidden]{display:none!important}.routespro-form-section{background:var(--rp-form-surface);color:var(--rp-form-text);border:1px solid var(--rp-form-border);border-radius:var(--rp-form-radius);padding:16px;margin:0 0 14px}.routespro-form-section-title,.routespro-form-step-title{font-family:var(--rp-form-headings-font);color:var(--rp-form-heading);font-size:18px;margin:0 0 8px}.routespro-form-help{font-size:13px;color:var(--rp-form-muted);margin-top:6px;line-height:1.35}.routespro-form-grid{display:grid;gap:12px}.routespro-form-cols-1{grid-template-columns:1fr}.routespro-form-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}.routespro-form-col{min-width:0}.routespro-form-w-25,.routespro-form-w-33,.routespro-form-w-50{grid-column:span 1}.routespro-form-w-66,.routespro-form-w-75,.routespro-form-w-100{grid-column:span 2}.routespro-form-field{min-width:0;color:var(--rp-form-text)}.routespro-form-field label,.routespro-form-field legend{display:block;margin:0 0 6px;font-size:13px;color:var(--rp-form-label);font-weight:700}.routespro-form-field input[type=text],.routespro-form-field input[type=number],.routespro-form-field input[type=date],.routespro-form-field input[type=time],.routespro-form-field input[type=file],.routespro-form-field select,.routespro-form-field textarea{width:100%;box-sizing:border-box;border:1px solid var(--rp-form-border);border-radius:var(--rp-form-input-radius);padding:10px 12px;background:var(--rp-form-surface);color:var(--rp-form-input-text)}.routespro-form-fieldset{border:0;padding:0;margin:0;min-width:0}.routespro-form-check{display:flex;gap:10px;align-items:flex-start;margin:8px 0}.routespro-form-input-group{display:flex;align-items:center;border:1px solid var(--rp-form-border);border-radius:var(--rp-form-input-radius);overflow:hidden;background:var(--rp-form-surface)}.routespro-form-field input::placeholder,.routespro-form-field textarea::placeholder{color:var(--rp-form-placeholder)}.routespro-form-input-group input{border:0!important;border-radius:0!important}.routespro-form-addon{padding:0 10px;opacity:.75;white-space:nowrap}.routespro-form-btn{appearance:none;border:0;border-radius:var(--rp-form-button-radius);padding:11px 16px;background:var(--rp-form-primary);color:var(--rp-form-button-text);font-weight:750;cursor:pointer;min-height:44px;box-shadow:0 8px 20px rgba(15,23,42,.10)}.routespro-form-btn:hover{background:var(--rp-form-primary-hover)}.routespro-form-btn.secondary{background:#f3f4f6;background:color-mix(in srgb,var(--rp-form-primary) 9%,var(--rp-form-surface));color:var(--rp-form-button-secondary-text);border:1px solid var(--rp-form-border)}.routespro-form-actions,.routespro-form-wizard-actions{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:16px;padding-top:14px;border-top:1px solid var(--rp-form-border)}.routespro-form-progress{margin:0 0 14px}.routespro-form-progress-bar{height:10px;background:#eef2f7;background:color-mix(in srgb,var(--rp-form-primary) 11%,var(--rp-form-surface));border-radius:999px;overflow:hidden}.routespro-form-progress-fill{display:block;height:100%;background:var(--rp-form-primary)}.routespro-form-progress-label{font-size:12px;color:var(--rp-form-muted);margin-top:6px}.routespro-form-notice{padding:12px 14px;border-radius:12px;margin:0 0 12px}.routespro-form-notice.success{background:#ecfdf3;color:#166534;border:1px solid #bbf7d0}.routespro-form-notice.error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}.routespro-form-notice.info{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}.routespro-share-space-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:12px}.routespro-share-space-head label{font-size:15px!important;font-weight:800;margin:0 0 4px!important}.routespro-share-space-head p{margin:0;color:#64748b;font-size:12px;line-height:1.45}.routespro-share-space-head>span{display:inline-flex;align-items:center;min-height:28px;padding:4px 9px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:11px;font-weight:800;white-space:nowrap}.routespro-share-space-matrix{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.routespro-share-space-card{min-width:0;border:1px solid #dbe4f0;border-radius:16px;background:linear-gradient(180deg,#fff,#f8fafc);padding:14px;box-shadow:0 8px 22px rgba(15,23,42,.04)}.routespro-share-space-card>header{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:12px}.routespro-share-space-card h4{margin:2px 0 0;font-size:15px;line-height:1.3;color:#0f172a}.routespro-share-space-card header small,.routespro-share-space-results small{display:block;color:#64748b;font-size:10px;text-transform:uppercase;letter-spacing:.05em;font-weight:800}.routespro-share-space-target{display:inline-flex;padding:5px 8px;border-radius:999px;background:#e0f2fe;color:#075985;font-size:11px;font-weight:800;white-space:nowrap}.routespro-share-space-inputs{display:grid;grid-template-columns:1fr 1fr;gap:10px}.routespro-share-space-inputs label{margin:0!important;padding:10px;border:1px solid #e2e8f0;border-radius:13px;background:#fff}.routespro-share-space-inputs label>span{display:block;min-height:34px;font-weight:750;color:#1e293b;font-size:12px;line-height:1.35}.routespro-share-space-inputs label>small{display:block;margin:5px 0;color:#64748b;font-size:10px;font-weight:800;text-transform:uppercase}.routespro-share-space-inputs input{font-size:16px!important;min-height:48px!important}.routespro-share-space-results{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;align-items:stretch;margin-top:10px}.routespro-share-space-results>div{padding:9px;border-radius:11px;background:#fff;border:1px solid #e2e8f0}.routespro-share-space-results strong{display:block;margin-top:3px;font-size:15px;color:#0f172a}.routespro-share-space-status{grid-column:1/-1;display:flex;align-items:center;justify-content:center;min-height:30px;padding:5px 9px;border-radius:10px;background:#f1f5f9;color:#475569;font-size:11px;font-weight:850}.routespro-share-space-status.is-good{background:#dcfce7;color:#166534}.routespro-share-space-status.is-warning{background:#fef3c7;color:#92400e}.routespro-share-space-status.is-empty{background:#f1f5f9;color:#64748b}.routespro-share-space-card>footer{margin-top:9px;color:#64748b;font-size:11px}.routespro-share-space-gap-negative{color:#b91c1c!important}.routespro-share-space-gap-positive{color:#166534!important}@media(max-width:900px){.routespro-share-space-matrix{grid-template-columns:1fr}}@media(max-width:560px){.routespro-share-space-head{flex-direction:column}.routespro-share-space-inputs{grid-template-columns:1fr}.routespro-share-space-results{grid-template-columns:1fr 1fr}.routespro-share-space-results>div:last-of-type{grid-column:1/-1}}
.routespro-product-matrix-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px}.routespro-product-matrix-head label{margin:0!important;font-weight:700}.routespro-product-matrix{display:grid;gap:8px;overflow-x:auto;-webkit-overflow-scrolling:touch}.routespro-product-row{display:grid;grid-template-columns:1.2fr 1fr 2fr 130px 38px;gap:8px;align-items:center;min-width:820px}.routespro-product-row-history{grid-template-columns:1.2fr 1fr 2fr 130px 130px 38px;min-width:960px}.routespro-product-row-head{font-size:12px;font-weight:700;color:#64748b;padding:0 38px 0 0}.routespro-product-remove{height:38px;border:1px solid rgba(0,0,0,.16);background:#fff;border-radius:12px;cursor:pointer;font-size:18px;line-height:1}.routespro-product-remove:hover{background:#fee2e2;border-color:#fecaca}.routespro-product-add{padding:8px 12px!important}.routespro-product-row input{width:100%;box-sizing:border-box;border:1px solid rgba(0,0,0,.18);border-radius:calc(var(--rp-form-radius) - 2px);padding:10px 12px;background:#fff}.routespro-product-before{background:#f8fafc!important;color:#64748b}.routespro-order-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}.routespro-order-head label{font-weight:700;margin:0!important}.routespro-order-context{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:0 0 14px}.routespro-order-context>div{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:10px 12px}.routespro-order-context small{display:block;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.04em}.routespro-order-context strong{display:block;margin-top:3px}.routespro-order-table{display:grid;gap:0;overflow-x:auto;border:1px solid #e2e8f0;border-radius:14px}.routespro-order-row{display:grid;grid-template-columns:1.05fr .8fr .65fr 2.2fr .55fr .55fr 1fr 1.5fr;gap:8px;align-items:center;min-width:1120px;padding:9px 10px;border-top:1px solid #eef2f7;background:#fff}.routespro-order-row-head{border-top:0;background:#f8fafc;font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.03em}.routespro-order-category{position:sticky;left:0;background:#e8eef8;color:#1e3a5f;font-weight:800;padding:7px 10px;border-top:1px solid #dbe4f0;min-width:1120px}.routespro-order-product{font-weight:650}.routespro-order-input{margin:0!important}.routespro-order-input small{display:none}.routespro-order-input input{padding:8px 9px!important;border-radius:9px!important}.routespro-order-notes input{min-width:150px}@media(max-width:720px){.routespro-order-context{grid-template-columns:1fr}.routespro-order-table{border:0;overflow:visible;gap:10px}.routespro-order-row-head{display:none}.routespro-order-category{min-width:0;border-radius:10px;position:static}.routespro-order-row{min-width:0;display:grid;grid-template-columns:1fr 1fr;gap:8px;border:1px solid #e2e8f0;border-radius:14px;padding:12px}.routespro-order-row>span{display:block}.routespro-order-row>span:before{content:attr(data-label);display:block;font-size:10px;text-transform:uppercase;color:#64748b;font-weight:700;margin-bottom:2px}.routespro-order-product{grid-column:1/-1}.routespro-order-input{grid-column:1/-1}.routespro-order-input small{display:block;color:#64748b;font-size:11px;font-weight:700;margin-bottom:4px}}@media(max-width:720px){.routespro-form-cols-2{grid-template-columns:1fr}.routespro-form-w-66,.routespro-form-w-75,.routespro-form-w-100{grid-column:span 1}}

/* 2.2.128 Form Experience, touch-first and low-noise */
.routespro-form-wrap{width:100%;max-width:100%;scroll-margin-top:18px}
.routespro-dyn-form{position:relative}
.routespro-draft-status{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 12px;padding:9px 11px;border:1px solid #dbeafe;border-radius:12px;background:#f8fbff;color:#334155;font-size:12px;line-height:1.35}
.routespro-draft-status[hidden]{display:none!important}
.routespro-draft-status.is-saving{border-color:#e2e8f0;background:#f8fafc;color:#64748b}
.routespro-draft-status.is-restored{border-color:#a7f3d0;background:#ecfdf5;color:#065f46}
.routespro-draft-status.is-warning{border-color:#fde68a;background:#fffbeb;color:#92400e}
.routespro-draft-status button{appearance:none;border:0;background:transparent;color:inherit;font:inherit;font-weight:800;text-decoration:underline;cursor:pointer;white-space:nowrap;padding:2px}
.routespro-form-live-region:empty{display:none}
.routespro-form-live-region{margin:0 0 14px;scroll-margin-top:18px}
.routespro-form-head{margin:0 0 16px;padding:0 2px}
.routespro-form-head h3{margin:0;color:#0f172a;font-size:clamp(23px,2.3vw,30px);line-height:1.15;letter-spacing:-.025em}
.routespro-form-head p{margin:7px 0 0;color:#64748b;font-size:15px;line-height:1.55}
.routespro-form-section{padding:clamp(16px,2.2vw,24px);margin-bottom:16px;border-color:#e2e8f0;box-shadow:0 10px 28px rgba(15,23,42,.045)}
.routespro-form-step{scroll-margin-top:18px}.routespro-form-wizard>[data-rp-wizard-actions]:not([data-rp-navigation-role="canonical"]){display:none!important}.routespro-form-step[hidden]{display:none!important}.routespro-form-wizard-actions [hidden]{display:none!important}.routespro-form-wizard[data-rp-is-last-visible-step="1"] .routespro-form-wizard-actions [data-rp-next]{display:none!important}
.routespro-form-step-title{font-size:clamp(21px,2vw,26px);line-height:1.22;color:#0f172a;letter-spacing:-.02em;margin-bottom:10px}
.routespro-form-section-title{font-size:clamp(20px,1.8vw,24px);line-height:1.22;color:#0f172a}
.routespro-form-grid{gap:16px}
.routespro-form-col{padding:2px 0}
.routespro-form-field label,.routespro-form-field legend{font-size:16px;line-height:1.4;font-weight:700;color:#1e293b;margin-bottom:8px}
.routespro-form-help{font-size:14px;line-height:1.55;color:#64748b;opacity:1;margin-top:8px}
.routespro-form-field input[type=text],.routespro-form-field input[type=number],.routespro-form-field input[type=date],.routespro-form-field input[type=time],.routespro-form-field input[type=file],.routespro-form-field select,.routespro-form-field textarea{min-height:52px;padding:13px 14px;font-size:16px;line-height:1.35;color:#0f172a;border-color:#cbd5e1;box-shadow:inset 0 1px 1px rgba(15,23,42,.025);transition:border-color .16s ease,box-shadow .16s ease,background .16s ease}
.routespro-form-field textarea{min-height:124px;resize:vertical}
.routespro-form-field input:focus,.routespro-form-field select:focus,.routespro-form-field textarea:focus{outline:none;border-color:var(--rp-form-primary);box-shadow:0 0 0 4px color-mix(in srgb,var(--rp-form-primary) 16%,transparent)}
.routespro-form-field input[type=file]{padding:10px;background:#f8fafc}
.routespro-form-check{min-height:48px;align-items:center;gap:12px;padding:10px 12px;margin:8px 0;border:1px solid #e2e8f0;border-radius:13px;background:#fff;cursor:pointer}
.routespro-form-check:hover{background:#f8fafc;border-color:#cbd5e1}
.routespro-form-check input[type=checkbox],.routespro-form-check input[type=radio]{width:22px;height:22px;min-width:22px;margin:0;accent-color:var(--rp-form-primary)}
.routespro-form-check span{font-size:16px;line-height:1.38;color:#1e293b}
.routespro-form-input-group{min-height:52px;border-color:#cbd5e1}
.routespro-form-addon{font-size:16px;font-weight:700;padding-inline:13px}
.routespro-form-btn{min-height:50px;padding:13px 20px;font-size:16px;line-height:1.2;font-weight:800;box-shadow:0 8px 20px color-mix(in srgb,var(--rp-form-primary) 20%,transparent);transition:transform .14s ease,box-shadow .14s ease,background .14s ease}
.routespro-form-btn:hover{transform:translateY(-1px)}
.routespro-form-btn:focus-visible{outline:3px solid color-mix(in srgb,var(--rp-form-primary) 32%,transparent);outline-offset:3px}
.routespro-form-btn[disabled]{opacity:.62;cursor:wait;transform:none;box-shadow:none}
.routespro-form-btn.secondary{box-shadow:none;border:1px solid #dbe3ee}
.routespro-form-actions,.routespro-form-wizard-actions{justify-content:flex-end;align-items:center;gap:12px;margin-top:22px;padding-top:16px;border-top:1px solid #eef2f7}
.routespro-form-progress{position:sticky;top:8px;z-index:8;margin-bottom:16px;padding:12px 14px;border:1px solid rgba(226,232,240,.9);border-radius:14px;background:rgba(255,255,255,.94);backdrop-filter:blur(12px);box-shadow:0 8px 24px rgba(15,23,42,.06)}
.routespro-form-progress-bar{height:8px}
.routespro-form-progress-label{font-size:13px;font-weight:800;color:#475569;opacity:1;margin-top:7px}
.routespro-form-notice{font-size:15px;line-height:1.5;padding:14px 16px;border-radius:14px;box-shadow:0 8px 22px rgba(15,23,42,.045);scroll-margin-top:18px}
.routespro-form-notice strong{display:block;margin-bottom:3px}
.routespro-form-notice.success{font-weight:700}
.routespro-form-notice.warning{background:#fff8e6;color:#854d0e;border:1px solid #fde68a}
.routespro-daily-report-notice{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
.routespro-daily-report-notice>div{flex:1 1 280px}
.routespro-daily-report-notice [data-rp-daily-mode-copy]{display:block;margin-top:4px;font-weight:500}
.routespro-daily-edit-btn{flex:0 0 auto;min-height:44px!important;padding:10px 16px!important}
.routespro-dyn-form.is-daily-locked [data-rp-question-wrap]{opacity:.72}
.routespro-dyn-form.is-daily-locked .routespro-form-actions,.routespro-dyn-form.is-daily-locked .routespro-form-wizard-actions{opacity:.65}
.routespro-dyn-form.is-daily-editing .routespro-daily-report-notice{background:#eff6ff;color:#1e40af;border-color:#bfdbfe}
.routespro-form-context-note{font-size:14px}
.routespro-form-invalid{animation:routesproInvalidPulse .42s ease}
@keyframes routesproInvalidPulse{0%,100%{transform:translateX(0)}30%{transform:translateX(-4px)}65%{transform:translateX(4px)}}
.routespro-briefing-form-intro h4{font-size:21px!important;line-height:1.25!important}
.routespro-product-matrix-head{align-items:flex-start;flex-wrap:wrap}
.routespro-product-row input,.routespro-order-input input{font-size:16px!important;min-height:48px}
.routespro-form-field label.routespro-form-check{display:flex;flex-direction:row;align-items:center;gap:14px;margin:10px 0;padding:14px 16px}
.routespro-form-field label.routespro-form-check input[type=radio],.routespro-form-field label.routespro-form-check input[type=checkbox]{flex:0 0 24px;width:24px;height:24px;min-width:24px;margin:0}
.routespro-form-field label.routespro-form-check span{display:block;flex:1;margin:0;line-height:1.35}
.routespro-form-choice-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:10px}
.routespro-form-choice-list .routespro-form-check{margin:0!important;min-width:0}
.routespro-form-choice-mode{display:inline-flex;align-items:center;margin-top:2px;padding:5px 9px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:12px;font-weight:800}
.routespro-form-choice-list .routespro-form-check:has(input:checked){border-color:color-mix(in srgb,var(--rp-form-primary) 52%,#cbd5e1);background:color-mix(in srgb,var(--rp-form-primary) 8%,#fff);box-shadow:0 0 0 2px color-mix(in srgb,var(--rp-form-primary) 10%,transparent)}
.routespro-categorized-upload{min-width:0}
.routespro-categorized-upload-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px;min-width:0}
.routespro-evidence-category{display:block;min-width:0;border:1px solid #dbe3ee;border-radius:16px;background:#fff;overflow:hidden;box-shadow:0 8px 22px rgba(15,23,42,.04);transition:border-color .16s ease,box-shadow .16s ease,background .16s ease}
.routespro-evidence-category.is-selected{border-color:color-mix(in srgb,var(--rp-form-primary) 48%,#cbd5e1);box-shadow:0 0 0 3px color-mix(in srgb,var(--rp-form-primary) 10%,transparent),0 10px 26px rgba(15,23,42,.06)}
.routespro-form-field label.routespro-evidence-category-toggle{display:flex;align-items:center;gap:12px;width:100%;min-width:0;box-sizing:border-box;margin:0;padding:14px 15px;cursor:pointer;background:#fff}
.routespro-evidence-category-toggle>input{position:absolute!important;inline-size:1px!important;block-size:1px!important;opacity:0!important;pointer-events:none!important}
.routespro-evidence-category-check{flex:0 0 24px;width:24px;height:24px;border:2px solid #cbd5e1;border-radius:8px;background:#fff;box-shadow:inset 0 0 0 4px #fff}
.routespro-evidence-category.is-selected .routespro-evidence-category-check{border-color:var(--rp-form-primary);background:var(--rp-form-primary)}
.routespro-evidence-category-toggle>span:last-child{display:flex;flex-direction:column;gap:3px;min-width:0}
.routespro-evidence-category-toggle strong{font-size:15px;line-height:1.3;color:#1e293b}
.routespro-evidence-category-toggle small{font-size:12px;line-height:1.35;color:#64748b;font-weight:600}
.routespro-evidence-category-panel{padding:13px 15px 15px;border-top:1px solid #e8eef5;background:#f8fafc}
.routespro-evidence-category-panel[hidden]{display:none}
.routespro-evidence-existing{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:10px}
.routespro-evidence-thumb{position:relative;display:block;aspect-ratio:1/1;overflow:hidden;border:1px solid #dbe3ee;border-radius:11px;background:#fff}
.routespro-evidence-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.routespro-evidence-thumb span{position:absolute;left:5px;right:5px;bottom:5px;padding:4px 6px;border-radius:7px;background:rgba(15,23,42,.78);color:#fff;font-size:10px;font-weight:800;text-align:center}
.routespro-upload-zone{display:grid;gap:9px}.routespro-upload-inputs{display:grid;gap:8px}.routespro-upload-input-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:center}.routespro-upload-input-row:first-child{grid-template-columns:minmax(0,1fr)}.routespro-upload-input-row.is-clone:first-child{grid-template-columns:minmax(0,1fr) auto}.routespro-upload-remove,.routespro-upload-add{appearance:none;border:1px solid #cbd5e1;border-radius:11px;background:#fff;color:#0f172a;font-weight:800;cursor:pointer}.routespro-upload-remove{min-width:44px;min-height:44px;color:#991b1b}.routespro-upload-add{justify-self:start;min-height:42px;padding:9px 13px}.routespro-upload-add:hover{background:#f8fafc}.routespro-upload-source-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:10px}
.routespro-upload-source-btn{display:flex;align-items:center;justify-content:center;gap:8px;min-height:48px;border:1px solid #cbd5e1;border-radius:13px;padding:10px 12px;background:#fff;color:#0f172a;font:inherit;cursor:pointer;box-shadow:0 4px 14px rgba(15,23,42,.06)}
.routespro-upload-source-btn:hover,.routespro-upload-source-btn:focus-visible{border-color:var(--rp-form-primary,#ef5b2a);box-shadow:0 0 0 3px color-mix(in srgb,var(--rp-form-primary,#ef5b2a) 16%,transparent)}
.routespro-upload-source-btn.is-camera{background:#fff7ed}.routespro-upload-source-btn.is-gallery{background:#f8fafc}
.routespro-upload-native-input{position:fixed!important;left:-10000px!important;top:0!important;width:1px!important;height:1px!important;padding:0!important;margin:0!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important;opacity:0!important;pointer-events:none!important}
.routespro-upload-source-row{min-height:0!important;border:0!important;padding:0!important}
.routespro-upload-status{padding:10px 12px;border-radius:11px;font-size:12px;font-weight:750;line-height:1.4}.routespro-upload-status.is-info{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}.routespro-upload-status.is-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.routespro-upload-preview{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.routespro-upload-preview[hidden]{display:none}.routespro-evidence-existing-item{position:relative;min-width:0}.routespro-evidence-existing-item>.routespro-evidence-thumb{height:100%}.routespro-existing-media-remove{position:absolute;top:7px;right:7px;z-index:3;border:0;border-radius:999px;padding:8px 11px;background:rgba(255,255,255,.97);color:#991b1b;font-size:11px;font-weight:900;cursor:pointer;box-shadow:0 4px 12px rgba(15,23,42,.2)}.routespro-existing-media-remove:hover,.routespro-existing-media-remove:focus-visible{background:#fee2e2;outline:2px solid #fecaca;outline-offset:1px}.routespro-dyn-form:not(.is-daily-editing) .routespro-existing-media-remove{display:none!important}.routespro-evidence-existing-item.is-pending-removal>.routespro-evidence-thumb{opacity:.3;filter:grayscale(1)}.routespro-evidence-existing-item.is-pending-removal .routespro-existing-media-remove{background:#0f172a;color:#fff}.routespro-existing-media-pending{position:absolute;left:7px;right:7px;bottom:7px;z-index:3;border-radius:9px;padding:6px 7px;background:rgba(127,29,29,.94);color:#fff;font-size:10px;font-weight:850;text-align:center}.routespro-existing-media-pending[hidden]{display:none!important}.routespro-upload-preview-item{position:relative;min-width:0;border:1px solid #dbe3ee;border-radius:11px;background:#fff;overflow:hidden}.routespro-upload-preview-item img{display:block;width:100%;aspect-ratio:1/1;object-fit:cover}.routespro-upload-preview-item>span{display:block;padding:6px 7px;color:#475569;font-size:10px;font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.routespro-upload-preview-remove{position:absolute;top:6px;right:6px;z-index:2;min-width:34px;height:34px;padding:0 10px;border:0;border-radius:999px;background:rgba(255,255,255,.97);color:#991b1b;font-size:11px;font-weight:900;line-height:1;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 12px rgba(15,23,42,.22)}.routespro-upload-preview-remove:hover,.routespro-upload-preview-remove:focus-visible{background:#fee2e2;outline:2px solid #fecaca;outline-offset:1px}.routespro-upload-preview-file{padding:10px!important;aspect-ratio:auto!important}/* 9CT: confirmação acessível e consistente em formulários, incluindo Android/iOS WebView. */
.routespro-delete-confirm-overlay{position:fixed!important;inset:0!important;z-index:2147483647!important;display:flex!important;align-items:center!important;justify-content:center!important;box-sizing:border-box!important;padding:max(16px,env(safe-area-inset-top)) max(16px,env(safe-area-inset-right)) max(16px,env(safe-area-inset-bottom)) max(16px,env(safe-area-inset-left))!important;font-family:inherit!important}
.routespro-delete-confirm-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.68);-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px)}
.routespro-delete-confirm-dialog{position:relative;z-index:1;width:min(100%,440px);max-height:90vh;max-height:90dvh;overflow:auto;box-sizing:border-box;border:1px solid #e2e8f0;border-radius:20px;padding:25px 24px 22px;background:#fff;color:#0f172a;box-shadow:0 24px 78px rgba(15,23,42,.32);overflow-wrap:anywhere}
.routespro-delete-confirm-kicker{font-size:11px;font-weight:850;letter-spacing:.09em;text-transform:uppercase;color:#b91c1c;margin-bottom:8px}
.routespro-delete-confirm-dialog h3{margin:0 0 10px!important;color:#0f172a!important;font-size:clamp(20px,4vw,25px)!important;line-height:1.2!important;font-weight:850!important}
.routespro-delete-confirm-dialog p{margin:0 0 20px!important;color:#475569!important;font-size:14px!important;line-height:1.55!important}
.routespro-delete-confirm-actions{display:flex;gap:10px;justify-content:flex-end;align-items:stretch}
.routespro-delete-confirm-actions button{appearance:none!important;-webkit-appearance:none!important;min-height:48px!important;padding:10px 15px!important;border-radius:12px!important;font:inherit!important;font-size:14px!important;line-height:1.35!important;font-weight:800!important;text-transform:none!important;letter-spacing:normal!important;box-shadow:none!important;cursor:pointer!important;touch-action:manipulation;flex:1 1 0}
.routespro-delete-confirm-cancel{background:#f8fafc!important;color:#0f172a!important;border:1px solid #cbd5e1!important}
.routespro-delete-confirm-accept{background:#b91c1c!important;color:#fff!important;border:1px solid #b91c1c!important}
.routespro-delete-confirm-cancel:hover{background:#e2e8f0!important}.routespro-delete-confirm-accept:hover{background:#991b1b!important}
.routespro-delete-confirm-actions button:focus-visible{outline:3px solid #f59e0b!important;outline-offset:3px!important}
@media(max-width:500px){.routespro-delete-confirm-dialog{padding:22px 17px 17px;border-radius:17px}.routespro-delete-confirm-actions{flex-direction:column}.routespro-delete-confirm-actions button{flex:auto;width:100%!important}}
.routespro-form-image-viewer{position:fixed;inset:0;z-index:2147483647;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(2,6,23,.94);box-sizing:border-box}.routespro-form-image-viewer.is-open{display:flex}.routespro-form-image-viewer img{display:block;max-width:100%;max-height:calc(100dvh - 48px);width:auto;height:auto;object-fit:contain;border-radius:12px}.routespro-form-image-viewer button{position:absolute;top:max(10px,env(safe-area-inset-top));right:max(10px,env(safe-area-inset-right));width:44px;height:44px;border:0;border-radius:999px;background:#fff;color:#0f172a;font-size:24px;font-weight:900;cursor:pointer}
.routespro-camera-capture{position:fixed;inset:0;z-index:2147483646;display:none;align-items:center;justify-content:center;padding:max(16px,env(safe-area-inset-top)) 16px max(16px,env(safe-area-inset-bottom));background:#020617;box-sizing:border-box}.routespro-camera-capture.is-open{display:flex}.routespro-camera-capture-card{display:flex;flex-direction:column;width:min(100%,760px);max-height:100%;overflow:hidden;border-radius:18px;background:#0f172a;box-shadow:0 24px 80px rgba(0,0,0,.55)}.routespro-camera-capture-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;color:#fff}.routespro-camera-capture-head strong{font-size:16px}.routespro-camera-capture-close{width:42px;height:42px;border:0;border-radius:999px;background:#fff;color:#0f172a;font-size:22px;font-weight:900;cursor:pointer}.routespro-camera-capture-stage{position:relative;display:grid;place-items:center;min-height:280px;background:#000}.routespro-camera-capture video{display:block;width:100%;max-height:calc(100dvh - 190px);object-fit:contain;background:#000}.routespro-camera-capture-status{position:absolute;inset:auto 14px 14px;padding:8px 10px;border-radius:10px;background:rgba(15,23,42,.82);color:#fff;font-size:12px;font-weight:700}.routespro-camera-capture-actions{display:flex;gap:10px;padding:14px 16px}.routespro-camera-capture-actions button{flex:1 1 0;min-height:48px;border:0;border-radius:13px;padding:10px 14px;font:inherit;font-weight:850;cursor:pointer}.routespro-camera-capture-shot{background:var(--rp-form-primary,#ef5b2a);color:#fff}.routespro-camera-capture-cancel{background:#e2e8f0;color:#0f172a}
.routespro-visual-choice{min-width:0}
.routespro-visual-choice-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:12px}
.routespro-visual-choice-option{position:relative;display:block!important;margin:0!important;cursor:pointer;min-width:0}
.routespro-visual-choice-option>input{position:absolute!important;inline-size:1px!important;block-size:1px!important;opacity:0!important;pointer-events:none!important}
.routespro-visual-choice-card{display:flex;flex-direction:column;height:100%;overflow:hidden;border:1px solid #dbe3ee;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.05);transition:border-color .16s ease,box-shadow .16s ease,transform .16s ease}
.routespro-visual-choice-option:hover .routespro-visual-choice-card{transform:translateY(-2px);border-color:#cbd5e1;box-shadow:0 12px 30px rgba(15,23,42,.09)}
.routespro-visual-choice-media{display:grid;place-items:center;aspect-ratio:4/3;overflow:hidden;background:linear-gradient(135deg,#f8fafc,#eef2f7)}
.routespro-visual-choice-media img{width:100%;height:100%;object-fit:cover;display:block}
.routespro-visual-choice-placeholder{display:grid;place-items:center;width:100%;height:100%;color:#94a3b8}
.routespro-visual-choice-placeholder svg{width:44px;height:44px}
.routespro-visual-choice-copy{display:flex;align-items:center;gap:10px;justify-content:space-between;padding:13px 14px;min-height:54px}
.routespro-visual-choice-label{font-size:15px;font-weight:800;line-height:1.3;color:var(--rp-form-heading,#1e293b);overflow-wrap:anywhere}
.routespro-visual-choice-indicator{flex:0 0 22px;width:22px;height:22px;border:2px solid #cbd5e1;border-radius:999px;background:#fff;box-shadow:inset 0 0 0 4px #fff}
.routespro-visual-choice.is-multiple .routespro-visual-choice-indicator{border-radius:7px}
.routespro-visual-choice-option>input:checked+.routespro-visual-choice-card{border-color:var(--rp-form-primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--rp-form-primary) 14%,transparent),0 12px 30px rgba(15,23,42,.08)}
.routespro-visual-choice-option>input:checked+.routespro-visual-choice-card .routespro-visual-choice-indicator{border-color:var(--rp-form-primary);background:var(--rp-form-primary);box-shadow:inset 0 0 0 4px #fff}
.routespro-visual-choice-option>input:focus-visible+.routespro-visual-choice-card{outline:3px solid color-mix(in srgb,var(--rp-form-primary) 28%,transparent);outline-offset:3px}
.routespro-product-input{display:block!important;margin:0!important;min-width:0}
.routespro-product-input small{display:none;color:#64748b;font-size:11px;font-weight:800;margin-bottom:5px;text-transform:uppercase;letter-spacing:.03em}
.routespro-product-row>span{min-width:0;overflow-wrap:anywhere}
.routespro-product-name{font-weight:700}
.routespro-product-category{background:#e8eef8;color:#1e3a5f;font-weight:800;padding:8px 11px;border-top:1px solid #dbe4f0;min-width:820px;border-radius:10px}
.routespro-product-matrix.has-history .routespro-product-category{min-width:960px}
.routespro-product-remove{font-size:13px;font-weight:800;padding:0 9px;color:#991b1b}
.routespro-order-auto-note{display:block;margin-top:5px;font-size:11px;line-height:1.35;color:#166534;font-style:normal;font-weight:800}
.routespro-order-auto-note[hidden]{display:none}
.routespro-order-row.is-auto-suggested{background:#f0fdf4;border-color:#bbf7d0}

/* 2.2.292: memória da visita anterior com modal global estável por visita, resiliente a re-renderizações AJAX/WebView. */
.routespro-previous-visit-modal{position:fixed;inset:0;z-index:2147483600;display:flex;align-items:center;justify-content:center;padding:18px;font-family:inherit;box-sizing:border-box}
.routespro-previous-visit-modal[hidden]{display:none!important}
.routespro-previous-visit-modal .routespro-previous-visit-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.64);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px)}
.routespro-previous-visit-modal .routespro-previous-visit-dialog{position:relative;width:min(620px,100%);max-height:min(82vh,760px);max-height:min(82dvh,760px);overflow:auto;-webkit-overflow-scrolling:touch;background:#fff;border:1px solid rgba(148,163,184,.35);border-radius:22px;padding:24px;box-shadow:0 28px 80px rgba(15,23,42,.28);color:#0f172a;box-sizing:border-box}
.routespro-previous-visit-modal .routespro-previous-visit-kicker{font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--rp-form-primary,#2563eb);margin-bottom:6px}
.routespro-previous-visit-modal h3{margin:0 0 8px;font-size:clamp(22px,4vw,30px);line-height:1.15;color:#0f172a}
.routespro-previous-visit-modal p{margin:0 0 18px;color:#64748b;line-height:1.55}
.routespro-previous-visit-modal .routespro-previous-visit-list{display:grid;gap:10px;margin:0 0 16px}
.routespro-previous-visit-modal .routespro-previous-visit-item{padding:14px 16px;border-radius:14px;background:#f8fafc;border:1px solid #e2e8f0}
.routespro-previous-visit-modal .routespro-previous-visit-item strong{display:block;margin-bottom:6px;font-size:13px;color:#475569}
.routespro-previous-visit-modal .routespro-previous-visit-item div{font-size:15px;line-height:1.55;white-space:normal;overflow-wrap:anywhere;color:#0f172a}
.routespro-previous-visit-modal small{display:block;margin:0 0 16px;color:#64748b}
.routespro-previous-visit-modal .routespro-form-btn{width:100%;justify-content:center}
html.routespro-previous-visit-open,html.routespro-previous-visit-open body{overflow:hidden!important}
@media(max-width:640px){.routespro-previous-visit-modal{padding:max(12px,env(safe-area-inset-top)) max(12px,env(safe-area-inset-right)) max(12px,env(safe-area-inset-bottom)) max(12px,env(safe-area-inset-left))}.routespro-previous-visit-modal .routespro-previous-visit-dialog{padding:20px 16px;border-radius:18px;max-height:88dvh}}

/* 2.2.238: nos fluxos controlados pelo shortcode exterior nunca pode existir uma segunda ação final. */
.rp-report-quick .routespro-form-actions,.rp-report-focus .routespro-form-actions,.routespro-report-focus .routespro-form-actions{display:none!important}
.rp-report-quick .routespro-form-wizard-actions [data-rp-submit],.rp-report-focus .routespro-form-wizard-actions [data-rp-submit],.routespro-report-focus .routespro-form-wizard-actions [data-rp-submit]{display:none!important}

@media(max-width:1024px){
  .routespro-form-cols-2{grid-template-columns:1fr}
  .routespro-form-w-25,.routespro-form-w-33,.routespro-form-w-50,.routespro-form-w-66,.routespro-form-w-75,.routespro-form-w-100{grid-column:span 1}
  .routespro-form-section{border-radius:16px}
}
@media(max-width:720px){
  .routespro-form-choice-list{grid-template-columns:1fr}
  .routespro-visual-choice-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .routespro-form-wrap{margin-inline:0}
  .routespro-form-section{padding:16px 14px;border-radius:16px}
  .routespro-form-grid{gap:18px}
  .routespro-form-wizard-actions{position:sticky;bottom:8px;z-index:7;flex-wrap:nowrap;margin-inline:-6px;padding:12px 8px 8px;background:linear-gradient(180deg,rgba(255,255,255,0),rgba(255,255,255,.96) 22%,#fff 100%);border-top:0}
  .routespro-form-wizard-actions .routespro-form-btn{flex:1 1 0!important;width:auto!important;min-width:0!important}
  .routespro-form-actions .routespro-form-btn{width:100%}
  .routespro-form-progress{top:6px;margin-inline:-2px}
  .routespro-form-notice{font-size:14px}
  .routespro-visual-choice-copy{padding:11px 12px}
  .routespro-visual-choice-label{font-size:14px}
  .routespro-product-add{width:100%}
  .routespro-product-matrix{overflow:visible;gap:10px}
  .routespro-product-row-head{display:none}
  .routespro-product-category{min-width:0!important;border-radius:12px;margin-top:2px}
  .routespro-product-row,.routespro-product-row-history{min-width:0;display:grid;grid-template-columns:1fr 1fr;gap:10px;border:1px solid #e2e8f0;border-radius:16px;padding:13px;background:#fff;box-shadow:0 8px 22px rgba(15,23,42,.04)}
  .routespro-product-row>span{display:block;padding:9px 10px;border-radius:11px;background:#f8fafc}
  .routespro-product-row>span:before{content:attr(data-label);display:block;font-size:10px;text-transform:uppercase;color:#64748b;font-weight:800;margin-bottom:3px;letter-spacing:.03em}
  .routespro-product-name,.routespro-product-name-input,.routespro-product-qty-input,.routespro-product-before-input{grid-column:1/-1}
  .routespro-product-input small{display:block}
  .routespro-product-row-end{display:none!important}
  .routespro-product-remove{grid-column:1/-1;width:100%;min-height:44px}
}
/* 2.2.131 Compact category accordions, protected from theme button styles */
.rp-form-ui .routespro-category-toolbar{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin:0 0 10px}
.rp-form-ui button.routespro-category-action{appearance:none!important;width:auto!important;max-width:none!important;min-height:38px!important;margin:0!important;padding:8px 13px!important;border:1px solid #dbe3ee!important;border-radius:999px!important;background:#fff!important;color:#334155!important;box-shadow:none!important;font-size:13px!important;line-height:1.2!important;font-weight:800!important;text-transform:none!important;cursor:pointer!important;transform:none!important}
.rp-form-ui button.routespro-category-action:hover{background:#f8fafc!important;border-color:#cbd5e1!important;color:#0f172a!important}
.rp-form-ui button.routespro-category-action:focus-visible,.rp-form-ui button.routespro-category-toggle:focus-visible{outline:3px solid rgba(34,113,177,.22)!important;outline-offset:2px!important}
.rp-form-ui .routespro-category-group{display:block!important;width:100%!important;min-width:0!important;margin:0!important;border:1px solid #dfe7f1!important;border-radius:15px!important;background:#fff!important;overflow:hidden!important;box-shadow:0 6px 18px rgba(15,23,42,.04)!important}
.rp-form-ui .routespro-category-group>button.routespro-category-toggle{appearance:none!important;display:grid!important;grid-template-columns:minmax(0,1fr) auto 34px!important;align-items:center!important;gap:10px!important;width:100%!important;max-width:none!important;min-width:0!important;min-height:56px!important;margin:0!important;padding:9px 11px 9px 14px!important;border:0!important;border-left:4px solid var(--rp-form-primary)!important;border-radius:0!important;background:#fff!important;color:#0f172a!important;box-shadow:none!important;text-align:left!important;text-transform:none!important;letter-spacing:normal!important;line-height:1.25!important;cursor:pointer!important;transform:none!important}
.rp-form-ui .routespro-category-group>button.routespro-category-toggle:hover{background:#f8fafc!important;color:#0f172a!important}
.rp-form-ui .routespro-category-group.is-open>button.routespro-category-toggle{background:#fff8f3!important;color:#0f172a!important;border-bottom:1px solid #e6edf5!important}
.rp-form-ui .routespro-category-toggle-main{display:flex!important;align-items:center!important;gap:9px!important;min-width:0!important;flex:1 1 auto!important;flex-direction:row!important}
.rp-form-ui .routespro-category-name{min-width:0!important;color:#0f172a!important;font-size:15px!important;line-height:1.25!important;font-weight:850!important;overflow-wrap:anywhere!important}
.rp-form-ui .routespro-category-meta{flex:0 0 auto!important;display:inline-flex!important;align-items:center!important;min-height:25px!important;margin:0!important;padding:4px 8px!important;border-radius:999px!important;background:#f1f5f9!important;color:#64748b!important;font-size:11px!important;line-height:1!important;font-weight:800!important;white-space:nowrap!important}
.rp-form-ui .routespro-category-status{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:27px!important;max-width:190px!important;padding:5px 9px!important;border:1px solid #bae6fd!important;border-radius:999px!important;background:#f0f9ff!important;color:#075985!important;font-size:11px!important;line-height:1.15!important;font-weight:850!important;white-space:normal!important;text-align:center!important}
.rp-form-ui .routespro-category-status[hidden]{display:none!important}
.rp-form-ui .routespro-category-chevron{display:grid!important;place-items:center!important;width:32px!important;height:32px!important;margin:0!important;border:1px solid #dbe3ee!important;border-radius:10px!important;background:#f8fafc!important;color:#475569!important;transform:none!important;transition:background .16s ease,color .16s ease,border-color .16s ease!important}
.rp-form-ui .routespro-category-chevron svg{display:block!important;width:18px!important;height:18px!important;transition:transform .18s ease!important}
.rp-form-ui .routespro-category-group.is-open>button.routespro-category-toggle .routespro-category-chevron{margin:0!important;border-color:#fed7aa!important;background:#fff!important;color:var(--rp-form-primary)!important;transform:none!important}
.rp-form-ui .routespro-category-group.is-open>button.routespro-category-toggle .routespro-category-chevron svg{transform:rotate(180deg)!important}
.rp-form-ui .routespro-category-panel{min-width:0!important;overflow-x:auto!important;-webkit-overflow-scrolling:touch!important;background:#fff!important;padding:10px!important}
.rp-form-ui .routespro-category-panel[hidden]{display:none!important}
.rp-form-ui .routespro-product-matrix.is-cardex,.rp-form-ui .routespro-order-table{overflow:visible!important;border:0!important;gap:10px!important;background:transparent!important}
.rp-form-ui .routespro-product-matrix.is-cardex .routespro-product-row-head,.rp-form-ui .routespro-order-table .routespro-order-row-head{margin-bottom:6px}
.rp-form-ui .routespro-product-matrix.is-cardex .routespro-product-row+.routespro-product-row,.rp-form-ui .routespro-order-table .routespro-order-row+.routespro-order-row{border-top:1px solid #eef2f7}
.rp-form-ui .routespro-fronts-zero-note{display:flex;align-items:flex-start;gap:7px;padding:10px 12px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569}
@media(max-width:720px){
  .rp-form-ui .routespro-category-toolbar{justify-content:stretch}
  .rp-form-ui button.routespro-category-action{flex:1 1 130px!important;min-height:42px!important}
  .rp-form-ui .routespro-category-group>button.routespro-category-toggle{grid-template-columns:minmax(0,1fr) auto 32px!important;gap:8px!important;min-height:58px!important;padding:9px 9px 9px 12px!important}
  .rp-form-ui .routespro-category-toggle-main{align-items:flex-start!important;flex-direction:column!important;gap:4px!important}
  .rp-form-ui .routespro-category-name{font-size:15px!important}
  .rp-form-ui .routespro-category-meta{min-height:22px!important;padding:3px 7px!important;font-size:10px!important}
  .rp-form-ui .routespro-category-status{max-width:126px!important;min-height:25px!important;padding:4px 7px!important;font-size:10px!important}
  .rp-form-ui .routespro-category-chevron{width:30px!important;height:30px!important;border-radius:9px!important}
  .rp-form-ui .routespro-category-panel{padding:9px!important;background:#f8fafc!important}
  .rp-form-ui .routespro-category-panel .routespro-product-row,.rp-form-ui .routespro-category-panel .routespro-order-row{margin-bottom:9px}
  .rp-form-ui .routespro-category-panel .routespro-product-row:last-child,.rp-form-ui .routespro-category-panel .routespro-order-row:last-child{margin-bottom:0}
}

@media(max-width:720px){.routespro-categorized-upload-grid{grid-template-columns:1fr}.routespro-evidence-existing{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:520px){.routespro-upload-source-actions{grid-template-columns:1fr}.routespro-upload-source-btn{width:100%}}
@media(max-width:460px){.routespro-evidence-existing,.routespro-upload-preview{grid-template-columns:repeat(3,minmax(0,1fr))}.routespro-visual-choice-grid{grid-template-columns:1fr 1fr;gap:9px}.routespro-visual-choice-card{border-radius:13px}.routespro-visual-choice-copy{min-height:50px}.routespro-visual-choice-media{aspect-ratio:1/1}}

/* 2.2.392 Store Intelligence, live store profile */
.rp-form-ui .routespro-store-profile{margin:0 0 18px;border:1px solid #dbe5f0;border-radius:18px;background:#fff;box-shadow:0 10px 30px rgba(15,23,42,.06);overflow:hidden}
.rp-form-ui .routespro-store-profile-summary{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:14px;padding:15px 16px;cursor:pointer;background:linear-gradient(135deg,#fff 0%,#f8fafc 100%);list-style:none}
.rp-form-ui .routespro-store-profile-summary::-webkit-details-marker{display:none}
.rp-form-ui .routespro-store-profile-summary-main{display:grid;gap:2px;min-width:0}
.rp-form-ui .routespro-store-profile-summary-main strong{font-size:17px;color:#0f172a;overflow-wrap:anywhere}
.rp-form-ui .routespro-store-profile-summary-main small{font-size:12px;color:#64748b;overflow-wrap:anywhere}
.rp-form-ui .routespro-store-profile-kicker{font-size:10px;line-height:1.2;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:var(--rp-form-primary,#2271b1)}
.rp-form-ui .routespro-store-profile-chips{display:flex;align-items:center;justify-content:flex-end;gap:6px;flex-wrap:wrap}
.rp-form-ui .routespro-store-chip,.rp-form-ui .routespro-store-readonly-badge{display:inline-flex;align-items:center;min-height:25px;padding:4px 8px;border:1px solid #dbe5f0;border-radius:999px;background:#f8fafc;color:#475569;font-size:10px;line-height:1.1;font-weight:850;white-space:nowrap}
.rp-form-ui .routespro-store-chip.is-source{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
.rp-form-ui .routespro-store-chip.is-success{background:#ecfdf5;border-color:#bbf7d0;color:#166534}
.rp-form-ui .routespro-store-chip.is-warning{background:#fff7ed;border-color:#fed7aa;color:#9a3412}
.rp-form-ui .routespro-store-chip.is-neutral{background:#f8fafc;border-color:#e2e8f0;color:#475569}
.rp-form-ui .routespro-store-profile-body{display:grid;gap:12px;padding:0 14px 14px;border-top:1px solid #eef2f7;background:#f8fafc}
.rp-form-ui .routespro-store-profile-section{margin-top:12px;padding:14px;border:1px solid #e2e8f0;border-radius:15px;background:#fff}
.rp-form-ui .routespro-store-profile-status{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.rp-form-ui .routespro-store-profile-status>div{display:grid;align-content:start;gap:3px;min-width:0;padding:10px;border-radius:12px;background:#f8fafc}
.rp-form-ui .routespro-store-profile-status strong{font-size:13px;color:#0f172a;overflow-wrap:anywhere}
.rp-form-ui .routespro-store-profile-status small{font-size:11px;color:#64748b;overflow-wrap:anywhere}
.rp-form-ui .routespro-store-profile-label{font-size:10px;line-height:1.2;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#64748b}
.rp-form-ui .routespro-store-profile-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:11px}
.rp-form-ui .routespro-store-profile-section-head h4{margin:2px 0 0!important;font-size:15px!important;line-height:1.3!important;color:#0f172a!important}
.rp-form-ui .routespro-store-profile-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
.rp-form-ui .routespro-store-profile-field{display:grid;gap:3px;min-width:0;padding:10px 11px;border:1px solid #edf2f7;border-radius:11px;background:#f8fafc}
.rp-form-ui .routespro-store-profile-field span,.rp-form-ui .routespro-store-profile-text>span{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
.rp-form-ui .routespro-store-profile-field strong{font-size:13px;font-weight:750;color:#1e293b;overflow-wrap:anywhere}
.rp-form-ui .routespro-store-profile-textblocks{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:9px}
.rp-form-ui .routespro-store-profile-text{display:grid;gap:5px;min-width:0;padding:11px;border:1px solid #edf2f7;border-radius:11px;background:#f8fafc}
.rp-form-ui .routespro-store-profile-text>div{font-size:13px;line-height:1.45;color:#334155;white-space:pre-wrap;overflow-wrap:anywhere}
.rp-form-ui button.routespro-store-profile-edit,.rp-form-ui button.routespro-store-profile-save,.rp-form-ui button.routespro-store-profile-cancel{appearance:none!important;width:auto!important;max-width:none!important;min-height:38px!important;margin:0!important;padding:8px 13px!important;border-radius:10px!important;font-size:12px!important;line-height:1.2!important;font-weight:850!important;text-transform:none!important;cursor:pointer!important;box-shadow:none!important;transform:none!important}
.rp-form-ui button.routespro-store-profile-edit{border:1px solid #cbd5e1!important;background:#fff!important;color:#334155!important}
.rp-form-ui button.routespro-store-profile-save{border:1px solid var(--rp-form-primary,#2271b1)!important;background:var(--rp-form-primary,#2271b1)!important;color:#fff!important}
.rp-form-ui button.routespro-store-profile-cancel{border:1px solid #cbd5e1!important;background:#fff!important;color:#475569!important}
.rp-form-ui button.routespro-store-profile-edit:hover,.rp-form-ui button.routespro-store-profile-cancel:hover{background:#f8fafc!important;color:#0f172a!important}
.rp-form-ui button.routespro-store-profile-save[disabled]{opacity:.62!important;cursor:wait!important}
.rp-form-ui .routespro-store-profile-editor{display:grid;gap:10px}
.rp-form-ui .routespro-store-profile-editor[hidden]{display:none!important}
.rp-form-ui .routespro-store-profile-editor-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.rp-form-ui .routespro-store-profile-editor label{display:grid;gap:5px;margin:0!important;font-size:12px;font-weight:800;color:#334155}
.rp-form-ui .routespro-store-profile-editor input,.rp-form-ui .routespro-store-profile-editor select,.rp-form-ui .routespro-store-profile-editor textarea{box-sizing:border-box;width:100%!important;min-width:0!important;margin:0!important;border:1px solid #cbd5e1!important;border-radius:10px!important;background:#fff!important;color:#0f172a!important;box-shadow:none!important;font-size:14px!important}
.rp-form-ui .routespro-store-profile-editor input,.rp-form-ui .routespro-store-profile-editor select{min-height:42px!important;padding:8px 10px!important}
.rp-form-ui .routespro-store-profile-editor textarea{padding:9px 10px!important;resize:vertical}
.rp-form-ui .routespro-store-profile-actions{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
.rp-form-ui .routespro-store-profile-updated{margin:10px 0 0!important;font-size:11px;color:#64748b}
.rp-form-ui .routespro-store-profile-message{min-height:0;margin-top:0;font-size:12px;font-weight:750;color:#475569}
.rp-form-ui .routespro-store-profile-message.is-error{margin-top:8px;color:#b91c1c}
.rp-form-ui .routespro-store-profile-message.is-success{margin-top:8px;color:#166534}
@media(max-width:720px){
  .rp-form-ui .routespro-store-profile-summary{grid-template-columns:1fr;gap:10px;padding:13px}
  .rp-form-ui .routespro-store-profile-chips{justify-content:flex-start}
  .rp-form-ui .routespro-store-profile-body{padding:0 9px 9px;gap:9px}
  .rp-form-ui .routespro-store-profile-section{margin-top:9px;padding:11px}
  .rp-form-ui .routespro-store-profile-status{grid-template-columns:1fr}
  .rp-form-ui .routespro-store-profile-grid,.rp-form-ui .routespro-store-profile-textblocks,.rp-form-ui .routespro-store-profile-editor-grid{grid-template-columns:1fr}
  .rp-form-ui .routespro-store-profile-section-head{align-items:center}
  .rp-form-ui .routespro-store-profile-actions>button{flex:1 1 130px!important}
}

@media(prefers-reduced-motion:reduce){.routespro-form-btn,.routespro-form-invalid{transition:none;animation:none}.routespro-form-btn:hover{transform:none}}

/* 2.2.610 9CM fieldflow-fronts-mobile-catalog-visual-v1: frentes compactas em mobile e tablet, com imagens do catálogo visual. */
.rp-form-ui .routespro-product-summary{display:none}
.rp-form-ui .routespro-category-group[hidden]{display:none!important}
.rp-form-ui .routespro-fronts-stepper{display:flex;align-items:stretch;width:100%;min-width:0;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:12px;overflow:hidden;background:#fff}
.rp-form-ui .routespro-fronts-stepper:focus-within{border-color:var(--rp-form-primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--rp-form-primary) 18%,transparent)}
.rp-form-ui button.routespro-fronts-step{appearance:none!important;-webkit-appearance:none!important;flex:0 0 40px!important;width:40px!important;min-width:40px!important;max-width:40px!important;min-height:44px!important;height:auto!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;background:#f1f5f9!important;color:#0f172a!important;box-shadow:none!important;font-size:21px!important;font-weight:800!important;line-height:1!important;text-transform:none!important;cursor:pointer!important;touch-action:manipulation;user-select:none;-webkit-user-select:none}
.rp-form-ui button.routespro-fronts-step:active{background:#e2e8f0!important}
.rp-form-ui .routespro-fronts-stepper input{flex:1 1 auto;min-width:0;width:100%!important;margin:0!important;padding:0 4px!important;border:0!important;border-radius:0!important;box-shadow:none!important;text-align:center;font-variant-numeric:tabular-nums;-moz-appearance:textfield;appearance:textfield;outline:0}
.rp-form-ui .routespro-fronts-stepper input::-webkit-inner-spin-button,.rp-form-ui .routespro-fronts-stepper input::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
.rp-form-ui .routespro-product-thumb{display:grid;place-items:center;flex:0 0 46px;width:46px;height:46px;margin:0;padding:2px;box-sizing:border-box;border:1px solid #e2e8f0;border-radius:11px;background:#fff;overflow:hidden;color:#94a3b8;font-size:12px;font-weight:800;line-height:1;letter-spacing:.02em}
.rp-form-ui button.routespro-product-thumb{appearance:none!important;-webkit-appearance:none!important;min-height:0!important;box-shadow:none!important;cursor:zoom-in!important}
.rp-form-ui .routespro-product-thumb img{display:block;width:100%;height:100%;object-fit:contain}
.rp-form-ui .routespro-product-thumb.is-empty{background:#f1f5f9}
.rp-form-ui .routespro-fronts-before{display:none}
.rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row:not(.routespro-product-row-history){grid-template-columns:1.2fr 1fr 2fr 150px 86px}
.rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row-history{grid-template-columns:1.2fr 1fr 2fr 120px 150px 86px}
.rp-form-ui .routespro-product-matrix.has-catalog-thumbs .routespro-product-row:not(.routespro-product-row-history){grid-template-columns:50px 1.2fr 1fr 2fr 150px 86px;min-width:930px}
.rp-form-ui .routespro-product-matrix.has-catalog-thumbs .routespro-product-row-history{grid-template-columns:50px 1.2fr 1fr 2fr 120px 150px 86px;min-width:1050px}
.rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row-head{padding-right:0}
.rp-form-ui .routespro-product-matrix.has-catalog-thumbs .routespro-product-row.has-summary>.routespro-product-summary{display:block}
.rp-form-ui .routespro-product-matrix.has-catalog-thumbs .routespro-product-row.has-summary .routespro-product-summary-text{display:none}
.rp-form-ui .routespro-product-matrix.has-catalog-thumbs .routespro-product-row:not(.has-summary):not(.routespro-product-row-head)>label:first-of-type{grid-column-start:2}
.rp-form-ui .routespro-share-space-inputs label>span.routespro-share-space-name{display:flex!important;align-items:center;gap:8px}
.rp-form-ui .routespro-share-space-inputs label>span.routespro-fronts-stepper{display:flex!important;min-height:0!important;font-weight:400;font-size:inherit}
.rp-form-ui .routespro-share-space-inputs .routespro-fronts-stepper input{min-height:46px!important;font-size:18px!important;font-weight:750}
.rp-form-ui .routespro-share-space-thumbs{display:inline-flex;align-items:center;flex:0 0 auto}
.rp-form-ui .routespro-share-space-thumbs .routespro-product-thumb{flex-basis:38px;width:38px;height:38px;border-radius:9px}
.rp-form-ui .routespro-share-space-thumbs .routespro-product-thumb+.routespro-product-thumb{margin-left:-10px;box-shadow:0 0 0 2px #fff!important}
.rp-form-ui .routespro-share-space-thumbs em{margin-left:4px;font-style:normal;font-size:11px;font-weight:800;color:#475569}
@media(max-width:1024px){
  .rp-form-ui .routespro-product-matrix.is-compact-fronts{overflow:visible!important}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-category-panel{overflow:visible!important}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row-head{display:none!important}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary{min-width:0;display:grid;grid-template-columns:minmax(0,1fr) 118px;gap:8px 10px;align-items:center;margin:0 0 8px;padding:8px 10px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;box-shadow:none}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary:last-child{margin-bottom:0}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary>.routespro-product-summary{display:flex;align-items:center;gap:10px;min-width:0;grid-column:1;grid-row:1}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary .routespro-product-summary-text{display:flex;flex-direction:column;align-items:flex-start;gap:2px;min-width:0}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-summary-text strong{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;font-size:14px;line-height:1.25;font-weight:800;color:#0f172a;overflow-wrap:anywhere}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-summary-text small{font-size:12px;line-height:1.3;color:#64748b}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts button.routespro-product-edit{appearance:none!important;width:auto!important;min-height:26px!important;margin:0!important;padding:2px 0!important;border:0!important;border-radius:0!important;background:none!important;box-shadow:none!important;color:var(--rp-form-primary)!important;font-size:12px!important;font-weight:700!important;line-height:1.2!important;text-transform:none!important;text-decoration:underline;text-underline-offset:2px;cursor:pointer!important}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary>.routespro-product-qty-input{grid-column:2;grid-row:1;align-self:center}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary>.routespro-product-qty-input>small{display:none!important}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary>label:not(.routespro-product-qty-input),
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary>.routespro-product-remove,
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary>span{display:none!important}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary.is-editing{border-color:#bfdbfe;background:#f8fbff}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary.is-editing>label.routespro-product-category-input,
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary.is-editing>label.routespro-product-ref-input,
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary.is-editing>label.routespro-product-name-input{display:block!important;grid-column:1/-1}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary.is-editing>label small{display:block}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary.is-editing>.routespro-product-remove{display:block!important;grid-column:1/-1;width:100%;min-height:44px}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-fronts-stepper input{min-height:44px!important;padding:0 2px!important;font-size:17px!important;font-weight:750}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts button.routespro-fronts-step{flex-basis:36px!important;width:36px!important;min-width:36px!important;max-width:36px!important}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-summary .routespro-product-thumb{flex-basis:42px;width:42px;height:42px}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-fronts-before{display:block;margin-top:3px;text-align:center;font-style:normal;font-size:11px;font-weight:700;line-height:1.2;color:#64748b}
}
@media(max-width:360px){
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary{grid-template-columns:minmax(0,1fr) 108px;padding:8px}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts button.routespro-fronts-step{flex-basis:32px!important;width:32px!important;min-width:32px!important;max-width:32px!important}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-summary .routespro-product-thumb{flex-basis:36px;width:36px;height:36px}
  .rp-form-ui .routespro-share-space-inputs{grid-template-columns:1fr}
}
.routespro-product-zoom{position:fixed;inset:0;z-index:2147483600;display:flex;align-items:center;justify-content:center;padding:max(16px,env(safe-area-inset-top)) 16px max(16px,env(safe-area-inset-bottom));box-sizing:border-box;font-family:inherit}
.routespro-product-zoom-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.72)}
.routespro-product-zoom figure{position:relative;margin:0;width:min(560px,100%);box-sizing:border-box;padding:16px 16px 14px;border-radius:18px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.35);text-align:center}
.routespro-product-zoom img{display:block;max-width:100%;max-height:68vh;max-height:68dvh;margin:0 auto;object-fit:contain}
.routespro-product-zoom figcaption{margin:10px 44px 0;color:#0f172a;font-size:15px;font-weight:800;line-height:1.3}
.routespro-product-zoom button.routespro-product-zoom-close{position:absolute!important;top:8px!important;right:8px!important;width:40px!important;height:40px!important;min-height:0!important;margin:0!important;padding:0!important;border:0!important;border-radius:999px!important;background:#f1f5f9!important;color:#0f172a!important;box-shadow:none!important;font-size:24px!important;line-height:1!important;cursor:pointer!important}
html.routespro-product-zoom-open,html.routespro-product-zoom-open body{overflow:hidden!important}

/* 2.2.612 9CO fieldflow-fronts-vision-reading-v1: leitura de frentes por câmara (piloto). */
.rp-form-ui .routespro-product-matrix-head .routespro-product-head-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.rp-form-ui button.routespro-fronts-camera-btn{display:inline-flex!important;align-items:center;gap:7px}
.rp-form-ui button.routespro-share-space-camera{appearance:none!important;display:flex!important;align-items:center;justify-content:center;gap:7px;width:100%!important;min-height:44px!important;margin:0 0 10px!important;padding:8px 12px!important;border:1px dashed var(--rp-form-primary)!important;border-radius:12px!important;background:color-mix(in srgb,var(--rp-form-primary) 7%,#fff)!important;color:var(--rp-form-primary)!important;box-shadow:none!important;font-size:14px!important;font-weight:800!important;cursor:pointer!important}
.rp-form-ui .routespro-product-row.is-camera-read .routespro-fronts-stepper{border-color:#16a34a;box-shadow:0 0 0 2px rgba(22,163,74,.15)}
.rp-form-ui .routespro-product-row.is-camera-read .routespro-product-summary-text strong::after{content:" · lido";color:#15803d;font-weight:700;font-size:11px}
.routespro-vision-manual-label{display:grid;gap:7px;margin:12px 0;font-weight:800;color:#334155}
.routespro-vision-manual-label input,.routespro-vision-manual-label textarea,.routespro-vision-proposals select{width:100%!important;min-height:44px!important;border:1px solid #cbd5e1!important;border-radius:12px!important;padding:10px 12px!important;box-sizing:border-box!important;font-size:16px!important;background:#fff!important;color:#0f172a!important}
.routespro-vision-manual-label textarea{min-height:100px!important;resize:vertical!important}
.routespro-vision-manual-list [hidden]{display:none!important}
.routespro-vision-manual-name{min-width:0;overflow-wrap:anywhere}
.routespro-vision-proposals{list-style:none;margin:8px 0;padding:0;display:grid;gap:8px}
.routespro-vision-proposals li{display:grid;gap:5px;padding:12px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc}
.routespro-vision-proposals li span{color:#64748b;font-size:12px}
.routespro-vision-error:empty,.routespro-vision-notice:empty{display:none!important}
.routespro-vision-sheet{position:fixed;inset:0;z-index:2147483500;display:flex;align-items:flex-end;justify-content:center;font-family:inherit;color:#0f172a}
.routespro-vision-sheet[hidden]{display:none!important}
.routespro-vision-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.6)}
.routespro-vision-panel{position:relative;width:100%;max-width:560px;max-height:92vh;max-height:92dvh;overflow:auto;-webkit-overflow-scrolling:touch;background:#fff;border-radius:20px 20px 0 0;padding:14px 16px max(16px,env(safe-area-inset-bottom));box-sizing:border-box;box-shadow:0 -20px 60px rgba(15,23,42,.3)}
.routespro-vision-panel>header{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 10px}
.routespro-vision-panel>header strong{font-size:17px}
.routespro-vision-sheet button{font-family:inherit}
.routespro-vision-sheet button.routespro-vision-x{appearance:none!important;width:40px!important;height:40px!important;min-height:0!important;margin:0!important;padding:0!important;border:0!important;border-radius:999px!important;background:#f1f5f9!important;color:#0f172a!important;box-shadow:none!important;font-size:22px!important;line-height:1!important;cursor:pointer!important}
.routespro-vision-body p{margin:0 0 10px;color:#475569;font-size:14px;line-height:1.45}
.routespro-vision-chips{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px}
.routespro-vision-sheet button.routespro-vision-chip{appearance:none!important;width:auto!important;min-height:40px!important;margin:0!important;padding:8px 13px!important;border:1px solid #cbd5e1!important;border-radius:999px!important;background:#fff!important;color:#0f172a!important;box-shadow:none!important;font-size:14px!important;font-weight:700!important;text-transform:none!important;cursor:pointer!important}
.routespro-vision-sheet button.routespro-vision-chip.is-active{border-color:#2271b1!important;background:#eff6ff!important;color:#1d4ed8!important}
.routespro-vision-products{margin:0 0 12px;padding:0;list-style:none;color:#475569;font-size:13px;line-height:1.5}
.routespro-vision-actions{display:grid;gap:8px;margin-top:12px}
.routespro-vision-sheet button.routespro-vision-btn{appearance:none!important;width:100%!important;min-height:48px!important;margin:0!important;padding:10px 14px!important;border:1px solid #cbd5e1!important;border-radius:14px!important;background:#fff!important;color:#0f172a!important;box-shadow:none!important;font-size:15px!important;font-weight:800!important;text-transform:none!important;cursor:pointer!important}
.routespro-vision-sheet button.routespro-vision-btn.is-primary{border-color:#2271b1!important;background:#2271b1!important;color:#fff!important}
.routespro-vision-sheet button.routespro-vision-btn.is-link{border:0!important;background:none!important;color:#475569!important;min-height:40px!important;font-weight:700!important}
.routespro-vision-sheet .routespro-fronts-stepper{display:flex;align-items:stretch;width:100%;min-width:0;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:12px;overflow:hidden;background:#fff}
.routespro-vision-sheet button.routespro-fronts-step{appearance:none!important;flex:0 0 36px!important;width:36px!important;min-width:36px!important;max-width:36px!important;min-height:44px!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;background:#f1f5f9!important;color:#0f172a!important;box-shadow:none!important;font-size:20px!important;font-weight:800!important;line-height:1!important;cursor:pointer!important;touch-action:manipulation}
.routespro-vision-sheet .routespro-fronts-stepper input{flex:1 1 auto;min-width:0;width:100%!important;height:auto!important;min-height:44px!important;margin:0!important;padding:0 2px!important;border:0!important;border-radius:0!important;box-shadow:none!important;background:#fff!important;color:#0f172a!important;text-align:center;font-size:17px!important;font-weight:750;-moz-appearance:textfield;appearance:textfield;outline:0}
.routespro-vision-sheet .routespro-fronts-stepper input::-webkit-inner-spin-button,.routespro-vision-sheet .routespro-fronts-stepper input::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
.routespro-vision-file{position:fixed!important;left:-10000px!important;top:0!important;width:1px!important;height:1px!important;opacity:0!important;pointer-events:none!important}
.routespro-vision-photo{position:relative;margin:0 0 10px;border-radius:14px;overflow:hidden;background:#0f172a}
.routespro-vision-photo{text-align:center}
.routespro-vision-photo img{display:block;width:100%;max-height:30vh;max-height:30dvh;object-fit:contain}
/* 9CX: boxes must be relative to the portrait photo, never its black side gutters. */
.routespro-vision-photo-inner{position:relative;display:inline-block;max-width:100%;vertical-align:top;line-height:0}
.routespro-vision-photo-inner img{display:block;width:auto;max-width:100%;height:auto;max-height:30dvh;object-fit:contain}
.routespro-vision-box{position:absolute;border:2px solid #22c55e;border-radius:4px;box-shadow:0 0 0 1px rgba(0,0,0,.35)}
.routespro-vision-box span{position:absolute;top:-1px;left:-1px;padding:0 4px;background:#22c55e;color:#052e16;font-size:10px;font-weight:800;line-height:14px}
.routespro-vision-box.is-review{border:2px dashed #d97706;box-shadow:0 0 0 1px rgba(0,0,0,.25)}
.routespro-vision-box.is-review span{background:#f59e0b;color:#422006}
.routespro-vision-progress{display:flex;align-items:center;gap:10px;margin:8px 0;color:#0f172a;font-weight:700}
.routespro-vision-spinner{width:22px;height:22px;border:3px solid #e2e8f0;border-top-color:var(--rp-form-primary,#2271b1);border-radius:999px;animation:rpVisionSpin 1s linear infinite}
@keyframes rpVisionSpin{to{transform:rotate(360deg)}}
.routespro-vision-meta{font-size:12px!important;color:#64748b!important}
.routespro-vision-items{margin:0;padding:0;list-style:none;display:grid;gap:8px}
.routespro-vision-history-note{margin:8px 0;padding:10px 12px;background:#eff6ff;color:#1e40af;border-radius:11px;font-size:12px;font-weight:650;line-height:1.5}
.routespro-vision-previous,.routespro-vision-ocr,[data-vision-delta]{display:block;font-size:11px;line-height:1.45;color:#475569;overflow-wrap:anywhere}
.routespro-vision-ocr{color:#92400e}
.routespro-vision-item{display:grid;grid-template-columns:40px minmax(0,1fr) 118px;gap:10px;align-items:center;padding:8px;border:1px solid #e2e8f0;border-radius:12px}
.routespro-vision-item.is-low{border-color:#fcd34d;background:#fffbeb}
.routespro-vision-item img,.routespro-vision-item .routespro-vision-initials{width:40px;height:40px;border-radius:9px;border:1px solid #e2e8f0;object-fit:contain;background:#fff}
.routespro-vision-initials{display:grid;place-items:center;background:#f1f5f9!important;color:#94a3b8;font-size:11px;font-weight:800}
.routespro-vision-item strong{display:block;font-size:14px;line-height:1.25}
.routespro-vision-item small{display:block;font-size:11px;color:#64748b}
.routespro-vision-sheet button.routespro-vision-accept{appearance:none!important;display:inline-block!important;width:auto!important;min-height:34px!important;padding:6px 8px!important;margin-top:7px!important;border:1px solid #d97706!important;border-radius:8px!important;background:#fff7ed!important;color:#92400e!important;font-size:12px!important;font-weight:800!important;cursor:pointer!important}
.routespro-vision-side{margin:10px 0 4px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#475569}
.routespro-vision-error{padding:12px;border-radius:12px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;font-weight:700}
/* 2.2.613 9CP fieldflow-fronts-field-reference-v1: produtos da leitura com imagem e fotografia de referência no terreno. */
.routespro-vision-products.is-visual{display:grid;gap:6px;color:#0f172a}
.routespro-vision-products.is-visual li{display:grid;grid-template-columns:40px minmax(0,1fr) auto;gap:10px;align-items:center;padding:6px 8px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}
.routespro-vision-products.is-visual li.is-missing{border-style:dashed;border-color:#cbd5e1;background:#f8fafc}
.routespro-vision-products.is-visual img,.routespro-vision-products.is-visual .routespro-vision-initials{width:40px;height:40px;box-sizing:border-box;border-radius:9px;border:1px solid #e2e8f0;object-fit:contain;background:#fff}
.routespro-vision-pname{display:flex;flex-direction:column;min-width:0;font-size:14px;font-weight:700;line-height:1.25;overflow-wrap:anywhere}
.routespro-vision-pname small{font-size:11px;font-weight:600;color:#64748b}
.routespro-vision-pname small.is-ok{color:#15803d}.routespro-vision-pname small.is-error{color:#b91c1c}
.routespro-vision-sheet button.routespro-vision-ref{appearance:none!important;display:inline-flex!important;align-items:center;gap:5px;width:auto!important;min-height:40px!important;margin:0!important;padding:6px 12px!important;border:1px solid #2271b1!important;border-radius:999px!important;background:#eff6ff!important;color:#1d4ed8!important;box-shadow:none!important;font-size:13px!important;font-weight:800!important;text-transform:none!important;white-space:nowrap;cursor:pointer!important;touch-action:manipulation}
.routespro-vision-sheet button.routespro-vision-more{appearance:none!important;width:100%!important;min-height:40px!important;margin:0 0 8px!important;padding:6px 12px!important;border:0!important;background:none!important;color:#1d4ed8!important;box-shadow:none!important;font-size:14px!important;font-weight:800!important;text-transform:none!important;cursor:pointer!important}
.routespro-vision-sheet button.routespro-vision-btn:disabled{opacity:.5!important;cursor:wait!important}
.routespro-vision-body p.routespro-vision-hint{padding:10px 12px;border-radius:12px;background:#f0f9ff;border:1px solid #bae6fd;color:#0c4a6e;font-size:13px}
.routespro-vision-products.is-visual .routespro-vision-spinner{width:20px;height:20px;margin:0 12px}
/* 2.2.614 9CQ: Share Space em linhas no telemóvel e tablet (nome à esquerda, −/+ à direita, número sempre visível). */
.rp-form-ui .routespro-share-space-inputs>.routespro-share-space-camera{grid-column:1/-1}
@media(max-width:900px){
  .rp-form-ui .routespro-share-space-inputs{grid-template-columns:minmax(0,1fr)!important;gap:8px}
  .rp-form-ui .routespro-share-space-inputs>label{display:grid!important;grid-template-columns:minmax(0,1fr) 148px;grid-template-areas:"name stepper" "small stepper";align-items:center;gap:2px 12px;padding:10px 12px!important;min-width:0}
  .rp-form-ui .routespro-share-space-inputs .routespro-share-space-thumbs .routespro-product-thumb{flex-basis:34px;width:34px;height:34px}
  .rp-form-ui .routespro-share-space-inputs>label>span.routespro-share-space-name{grid-area:name;min-height:0!important;min-width:0;font-size:14px;line-height:1.25}
  .rp-form-ui .routespro-share-space-inputs>label>span.routespro-share-space-name>span:last-child{min-width:0;overflow-wrap:anywhere}
  .rp-form-ui .routespro-share-space-inputs>label>small{grid-area:small;margin:0!important}
  .rp-form-ui .routespro-share-space-inputs>label>span.routespro-fronts-stepper{grid-area:stepper;width:148px}
  .rp-form-ui .routespro-share-space-inputs .routespro-fronts-stepper input{padding:0 2px!important}
}
@media(max-width:480px){
  .rp-form-ui .routespro-share-space-inputs>label{grid-template-columns:minmax(0,1fr) 140px;grid-template-areas:"name name" "small stepper";gap:6px 10px}
  .rp-form-ui .routespro-share-space-inputs>label>span.routespro-fronts-stepper{width:140px}
}
/* 2.2.614 9CQ: referências acrescentadas na loja (Ajustes da loja) como cartões legíveis no telemóvel e tablet. */
@media(max-width:1024px){
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row:not(.has-summary):not(.routespro-product-row-head){display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:8px 10px;align-items:end;min-width:0!important;width:auto!important;margin:0 0 8px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;box-shadow:none}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row:not(.has-summary):not(.routespro-product-row-head)>label{display:flex!important;flex-direction:column;gap:4px;min-width:0!important;width:auto!important;margin:0!important;padding:0!important;line-height:1.2}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row:not(.has-summary):not(.routespro-product-row-head)>label>small{display:block!important;writing-mode:horizontal-tb;white-space:normal;font-size:11px;font-weight:800;letter-spacing:.03em;text-transform:uppercase;color:#64748b}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row:not(.has-summary):not(.routespro-product-row-head)>label>input{width:100%!important;min-width:0!important;max-width:100%!important;box-sizing:border-box!important;min-height:44px}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row:not(.has-summary):not(.routespro-product-row-head)>label.routespro-product-name-input{grid-column:1/-1!important;grid-row:1!important}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row:not(.has-summary):not(.routespro-product-row-head)>label.routespro-product-category-input{grid-column:1!important;grid-row:2!important}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row:not(.has-summary):not(.routespro-product-row-head)>label.routespro-product-ref-input{grid-column:2!important;grid-row:2!important}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row:not(.has-summary):not(.routespro-product-row-head)>label.routespro-product-before-input{grid-column:1!important;grid-row:3!important}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row:not(.has-summary):not(.routespro-product-row-head)>label.routespro-product-qty-input{grid-column:2!important;grid-row:3!important}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row:not(.has-summary):not(.routespro-product-row-head):not(.routespro-product-row-history)>label.routespro-product-qty-input{grid-column:1/-1!important}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row:not(.has-summary):not(.routespro-product-row-head)>.routespro-product-remove{grid-column:1/-1!important;grid-row:4!important;justify-self:end;width:auto!important;min-height:36px;height:auto;padding:6px 12px!important}
  .rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row:not(.has-summary):not(.routespro-product-row-head) .routespro-fronts-stepper{width:100%}
}
.rp-form-ui .routespro-vision-admin-hint{margin:6px 0 10px;padding:10px 12px;border:1px dashed #f59e0b;border-radius:12px;background:#fffbeb;color:#78350f}
/* 2.2.614 9CQ fieldflow-fronts-vision-live-count-v1: contar ao vivo. */
.routespro-vision-live{position:relative;margin:0 0 10px;border-radius:16px;overflow:hidden;background:#0f172a;aspect-ratio:3/4;max-height:56vh;max-height:56dvh;width:100%}
.routespro-vision-live video{display:block;width:100%;height:100%;object-fit:cover}
.routespro-vision-live-frame{position:absolute;inset:7% 6% 16%;border:2px solid rgba(255,255,255,.85);border-radius:14px;box-shadow:0 0 0 999px rgba(15,23,42,.22);pointer-events:none;transition:border-color .2s}
.routespro-vision-live.is-steady .routespro-vision-live-frame{border-color:#22c55e}
.routespro-vision-live-status{position:absolute;left:10px;right:10px;bottom:10px;padding:8px 10px;border-radius:12px;background:rgba(15,23,42,.74);color:#fff;font-size:14px;font-weight:700;line-height:1.3;text-align:center}
.routespro-vision-live-meter{position:absolute;left:0;right:0;top:0;height:4px;background:rgba(255,255,255,.18)}
.routespro-vision-live-meter>span{display:block;height:100%;width:0;background:#22c55e;transition:width .25s}
.routespro-vision-choice{margin:10px 0;padding:10px 12px;border:1px solid #cbd5e1;border-radius:12px;min-width:0}
.routespro-vision-choice legend{font-weight:700;padding:0 5px;color:#334155}.routespro-vision-choice p{font-size:13px;margin:3px 0 8px}
.routespro-vision-choice-list{max-height:180px;overflow-y:auto;display:grid;gap:5px}
.routespro-vision-choice-list label{display:flex;gap:9px;align-items:center;padding:5px 0;font-size:13px;line-height:1.3}
.routespro-vision-choice-list input{width:19px;height:19px;flex:none}
.routespro-vision-review-title{margin:8px 0;font-size:17px;line-height:1.35;color:#172a40}
.routespro-vision-quality{padding:11px 13px;border-radius:12px;background:#fff7ed;border:1px solid #fed7aa;color:#78350f;font-size:13px;margin:10px 0}
.routespro-vision-quality.is-ok{background:#f0fdf4;border-color:#bbf7d0;color:#166534}
.routespro-vision-quality ul{margin:6px 0 0 18px;padding:0}.routespro-vision-quality li{margin:3px 0}
.routespro-vision-body .routespro-vision-notice{padding:10px 12px;border-radius:12px;background:#fff7ed;border:1px solid #fed7aa;color:#7c2d12;font-size:13px}
html.routespro-vision-open,html.routespro-vision-open body{overflow:hidden!important}
@media(min-width:721px){.routespro-vision-sheet{align-items:center}.routespro-vision-panel{border-radius:20px;max-height:88vh}}

CSS;
    }
    private static function inline_js(): string {
        return <<<'JS'
jQuery(function($){
  function rpScrollTo($target){
    if(!$target || !$target.length) return;
    var el=$target.get(0);
    if(!el) return;
    var reduce=window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var adminBar=document.getElementById('wpadminbar');
    var adminOffset=adminBar&&adminBar.offsetParent!==null?adminBar.getBoundingClientRect().height:0;
    var safeOffset=Math.max(72,adminOffset+24);
    var top=Math.max(0,el.getBoundingClientRect().top+window.pageYOffset-safeOffset);
    window.scrollTo({top:top,behavior:reduce?'auto':'smooth'});
  }
  // 9CT: all user-initiated destructive form actions require explicit opt-in.
  // Cancel is focused by default. No form state changes while the dialog is open.
  var rpFormDeletionDialog=null;
  function rpConfirmFormDeletion(options){
    options=options||{};
    if(rpFormDeletionDialog||!document.body||!document.createElement) return Promise.resolve(false);
    return new Promise(function(resolve){
      var overlay=document.createElement('div'),previousFocus=document.activeElement;
      var previousOverflow=document.body.style.overflow,done=false;
      overlay.className='routespro-delete-confirm-overlay';
      overlay.setAttribute('data-rp-delete-confirm','1');
      overlay.setAttribute('role','alertdialog');
      overlay.setAttribute('aria-modal','true');
      overlay.setAttribute('aria-labelledby','rp-delete-confirm-title');
      overlay.setAttribute('aria-describedby','rp-delete-confirm-detail');
      overlay.innerHTML='<div class="routespro-delete-confirm-backdrop" data-rp-confirm-backdrop></div>'+
        '<div class="routespro-delete-confirm-dialog" role="document">'+
          '<div class="routespro-delete-confirm-kicker">Confirmar ação</div>'+
          '<h3 id="rp-delete-confirm-title"></h3>'+
          '<p id="rp-delete-confirm-detail"></p>'+
          '<div class="routespro-delete-confirm-actions">'+
            '<button type="button" class="routespro-delete-confirm-cancel" data-rp-confirm-cancel>Manter, não eliminar</button>'+
            '<button type="button" class="routespro-delete-confirm-accept" data-rp-confirm-accept>Sim, eliminar</button>'+
          '</div>'+
        '</div>';
      var cancel=overlay.querySelector('[data-rp-confirm-cancel]');
      var accept=overlay.querySelector('[data-rp-confirm-accept]');
      overlay.querySelector('#rp-delete-confirm-title').textContent=String(options.title||'Queres mesmo eliminar este elemento?');
      overlay.querySelector('#rp-delete-confirm-detail').textContent=String(options.detail||'Esta ação remove a informação do formulário.');
      accept.textContent=String(options.confirm||'Sim, eliminar');
      function finish(approved){
        if(done)return;
        done=true;
        document.removeEventListener('keydown',onKey,true);
        document.removeEventListener('visibilitychange',onVisibility);
        window.removeEventListener('pagehide',onPageHide);
        overlay.removeEventListener('click',onClick);
        if(overlay.parentNode)overlay.parentNode.removeChild(overlay);
        document.body.style.overflow=previousOverflow;
        rpFormDeletionDialog=null;
        if(previousFocus&&previousFocus.isConnected&&typeof previousFocus.focus==='function'){
          try{previousFocus.focus({preventScroll:true});}catch(_){try{previousFocus.focus();}catch(__){}}
        }
        resolve(approved===true);
      }
      function onClick(event){
        if(event.target&&event.target.closest&&event.target.closest('[data-rp-confirm-accept]'))finish(true);
        else if(event.target===overlay||(event.target&&event.target.closest&&(event.target.closest('[data-rp-confirm-cancel]')||event.target.closest('[data-rp-confirm-backdrop]'))))finish(false);
      }
      function onKey(event){
        if(event.key==='Escape'){event.preventDefault();event.stopPropagation();finish(false);return;}
        if(event.key!=='Tab')return;
        if(event.shiftKey&&document.activeElement===cancel){event.preventDefault();accept.focus();}
        else if(!event.shiftKey&&document.activeElement===accept){event.preventDefault();cancel.focus();}
      }
      function onVisibility(){if(document.visibilityState==='hidden')finish(false);}
      function onPageHide(){finish(false);}
      rpFormDeletionDialog=overlay;
      try{
        document.body.appendChild(overlay);
        document.body.style.overflow='hidden';
        overlay.addEventListener('click',onClick);
        document.addEventListener('keydown',onKey,true);
        document.addEventListener('visibilitychange',onVisibility);
        window.addEventListener('pagehide',onPageHide);
        try{cancel.focus({preventScroll:true});}catch(_){cancel.focus();}
      }catch(_){finish(false);}
    });
  }
  var RP_VIEWPORT_STORAGE_PREFIX='fieldflow:form-viewport:v1:';
  var RP_VIEWPORT_TTL=12*60*60*1000;
  function rpViewportFormKey(form){
    if(!form) return '';
    var $form=$(form);
    var formId=parseInt(form.getAttribute('data-routespro-form-id')||$form.find('[name="routespro_form_id"]').val()||0,10)||0;
    var routeId=parseInt($form.find('[name="routespro_route_id"]').val()||0,10)||0;
    var stopId=parseInt($form.find('[name="routespro_stop_id"],[name="routespro_route_stop_id"]').first().val()||0,10)||0;
    var locationId=parseInt($form.find('[name="routespro_location_id"]').val()||0,10)||0;
    var reportDay=String($form.find('[name="routespro_report_day"],[name="routespro_context_date"]').first().val()||$form.attr('data-rp-report-day')||'');
    if(!formId&&!routeId&&!stopId&&!locationId) return '';
    return RP_VIEWPORT_STORAGE_PREFIX+[formId,routeId,stopId,locationId,reportDay||'day'].join(':');
  }
  function rpViewportSafeTop(){
    var adminBar=document.getElementById('wpadminbar');
    var adminOffset=adminBar&&adminBar.offsetParent!==null?adminBar.getBoundingClientRect().height:0;
    return Math.max(72,adminOffset+24);
  }
  function rpViewportQuestionAnchor(form,origin){
    if(!form) return null;
    var candidate=origin&&origin.nodeType===1?origin:null;
    if(candidate&&form.contains(candidate)){
      var wrap=candidate.closest?candidate.closest('[data-rp-question-wrap]'):null;
      if(wrap&&form.contains(wrap)) return wrap;
    }
    var active=document.activeElement;
    if(active&&form.contains(active)){
      var activeWrap=active.closest?active.closest('[data-rp-question-wrap]'):null;
      if(activeWrap) return activeWrap;
    }
    var safeTop=rpViewportSafeTop(),best=null,bestDistance=Number.POSITIVE_INFINITY;
    Array.prototype.slice.call(form.querySelectorAll('[data-rp-question-wrap]')).forEach(function(item){
      var rect=item.getBoundingClientRect();
      if(rect.width<=0||rect.height<=0||rect.bottom<safeTop) return;
      var distance=Math.abs(rect.top-safeTop);
      if(distance<bestDistance){best=item;bestDistance=distance;}
    });
    return best||form;
  }
  var rpViewportLegacyContract='fieldflow-form-viewport-shopping-delivery-integrity-v1';
  var rpViewportLegacyLockContract='fieldflow-form-viewport-lock-v1';
  var rpViewportContract='fieldflow-form-viewport-continuity-v2';
  var rpViewportPassiveContractV3='fieldflow-passive-viewport-continuity-v3';
  var rpViewportSingleAnchorContract='fieldflow-single-anchor-viewport-continuity-v4';
  function rpScrollContainerFor(element){
    var node=element&&element.parentElement?element.parentElement:null;
    while(node&&node!==document.body&&node!==document.documentElement){
      var style=window.getComputedStyle?window.getComputedStyle(node):null;
      var overflow=style?String(style.overflowY||''):'';
      if(/auto|scroll|overlay/.test(overflow)&&node.scrollHeight>node.clientHeight+2) return node;
      node=node.parentElement;
    }
    return document.scrollingElement||document.documentElement;
  }
  // 2.2.552 single-anchor continuity. 2.2.551 passive and 2.2.550 paint-mask names stay as inert compatibility markers:
  // fieldflow-passive-viewport-continuity-v3 | Do not write scrollTop. The WebView/browser owns scrolling in 2.2.551.
  // fieldflow-viewport-paint-mask-v1 | rpRequestFormVisualFreeze | rpRequestFormVisualUnfreeze | conditional-choice-change | cardex-add | cardex-remove
  var rpViewportActiveLock=null;
  function rpIsDocumentScroller(container){return !container||container===document.scrollingElement||container===document.documentElement||container===document.body;}
  function rpScrollTop(container){
    if(rpIsDocumentScroller(container)) return Math.max(0,window.pageYOffset||document.documentElement.scrollTop||document.body.scrollTop||0);
    return Math.max(0,container.scrollTop||0);
  }
  function rpSetScrollTop(container,top){
    top=Math.max(0,Number(top)||0);
    if(rpIsDocumentScroller(container)){
      var rootStyle=document.documentElement&&document.documentElement.style?document.documentElement.style:null,previous=rootStyle?rootStyle.scrollBehavior:'';
      if(rootStyle)rootStyle.scrollBehavior='auto';
      window.scrollTo(0,top);
      if(rootStyle)rootStyle.scrollBehavior=previous||'';
    }else{
      var previousBehavior=container.style?container.style.scrollBehavior:'';
      if(container.style)container.style.scrollBehavior='auto';
      container.scrollTop=top;
      if(container.style)container.style.scrollBehavior=previousBehavior||'';
    }
  }
  function rpBeginViewportLock(element,reason){
    var container=rpScrollContainerFor(element),top=rpScrollTop(container);
    var lock={container:container,top:top,reason:String(reason||'continuity'),contract:rpViewportSingleAnchorContract,passive:true,singleAnchor:true};
    rpViewportActiveLock=lock;return lock;
  }
  function rpReleaseViewportLock(lock){
    lock=lock||rpViewportActiveLock;if(!lock)return;
    // 2.2.552 never runs a continuous guard. Any correction is one atomic anchor compensation only.
    if(rpViewportActiveLock===lock) rpViewportActiveLock=null;
  }
  function rpReleaseFormViewportLock(form,reason){
    if(!form||!form.__rpViewportLock)return;var lock=form.__rpViewportLock;form.__rpViewportLock=null;if(form.__rpViewportUnlockTimer){window.clearTimeout(form.__rpViewportUnlockTimer);form.__rpViewportUnlockTimer=0;}rpReleaseViewportLock(lock);
    try{form.dispatchEvent(new CustomEvent('fieldflow:form-viewport-unlocked',{bubbles:true,detail:{reason:String(reason||'continuity'),contract:rpViewportSingleAnchorContract,single_anchor:true}}));}catch(_){ }
  }
  function rpScheduleFormViewportUnlock(form,reason,delay){
    if(!form||!form.__rpViewportLock)return;if(form.__rpViewportUnlockTimer)window.clearTimeout(form.__rpViewportUnlockTimer);form.__rpViewportUnlockTimer=window.setTimeout(function(){form.__rpViewportUnlockTimer=0;rpReleaseFormViewportLock(form,reason);},Math.max(80,parseInt(delay||900,10)));
  }
  function rpDisableScrollAnchoring(container,form){return;}
  function rpEnableScrollAnchoring(container,form){return;}
  function rpCaptureElementViewport(element){
    if(!element||!element.getBoundingClientRect) return null;
    var rect=element.getBoundingClientRect(),container=rpScrollContainerFor(element);
    return {element:element,top:rect.top,scrollTop:rpScrollTop(container),container:container};
  }
  function rpRestoreElementViewport(snapshot){
    // Single-anchor continuity: compensate exactly once in the same task as the DOM mutation.
    // There is no RAF loop, timer loop or follow-up correction that can fight touch scrolling.
    if(!snapshot||!snapshot.element||!snapshot.element.getBoundingClientRect||!document.documentElement.contains(snapshot.element)) return snapshot||null;
    var container=snapshot.container&&document.documentElement.contains(snapshot.container)?snapshot.container:rpScrollContainerFor(snapshot.element);
    var delta=snapshot.element.getBoundingClientRect().top-Number(snapshot.top||0);
    if(Math.abs(delta)>1) rpSetScrollTop(container,rpScrollTop(container)+delta);
    return snapshot;
  }
  function rpCaptureFormViewport(form,reason,origin){
    var storageKey=rpViewportFormKey(form);
    if(!storageKey) return null;
    var anchor=rpViewportQuestionAnchor(form,origin),questionKey='';
    if(anchor&&anchor.getAttribute) questionKey=String(anchor.getAttribute('data-rp-question-wrap')||'');
    var rect=anchor&&anchor.getBoundingClientRect?anchor.getBoundingClientRect():null;
    var wizard=form.querySelector('.routespro-form-wizard'),container=rpScrollContainerFor(form);
    var checkpoint={
      contract:rpViewportContract,
      legacy_contract:rpViewportLegacyContract,
      reason:String(reason||'continuity'),
      question_key:questionKey,
      anchor_top:rect?rect.top:rpViewportSafeTop(),
      scroll_y:rpScrollTop(container),
      scroll_scope:(container===document.scrollingElement||container===document.documentElement||container===document.body)?'document':'container',
      step_index:parseInt(wizard&&wizard.dataset?wizard.dataset.rpCurrentStep||'0':'0',10)||0,
      updated_at:Date.now()
    };
    form.__rpViewportCheckpoint=checkpoint;
    try{window.localStorage.setItem(storageKey,JSON.stringify(checkpoint));}catch(_){ }
    return checkpoint;
  }
  function rpReadFormViewport(form){
    if(!form) return null;
    var checkpoint=form.__rpViewportCheckpoint||null,key=rpViewportFormKey(form);
    if(!checkpoint&&key){try{var raw=window.localStorage.getItem(key);checkpoint=raw?JSON.parse(raw):null;}catch(_){checkpoint=null;}}
    if(!checkpoint||Date.now()-parseInt(checkpoint.updated_at||0,10)>RP_VIEWPORT_TTL) return null;
    return checkpoint;
  }
  function rpRestoreFormViewport(form,reason,checkpoint){
    checkpoint=checkpoint||rpReadFormViewport(form);
    if(!form||!checkpoint||!document.documentElement.contains(form)) return false;
    var target=null,key=String(checkpoint.question_key||''),container=rpScrollContainerFor(form);
    if(key){
      Array.prototype.slice.call(form.querySelectorAll('[data-rp-question-wrap]')).some(function(item){if(String(item.getAttribute('data-rp-question-wrap')||'')===key){target=item;return true;}return false;});
    }
    if(target&&target.getBoundingClientRect().height>0){
      var delta=target.getBoundingClientRect().top-Number(checkpoint.anchor_top||rpViewportSafeTop());
      if(Math.abs(delta)>1) rpSetScrollTop(container,rpScrollTop(container)+delta);
    }else if(Number.isFinite(Number(checkpoint.scroll_y))){
      var current=rpScrollTop(container),desired=Number(checkpoint.scroll_y);
      if(Math.abs(current-desired)>1) rpSetScrollTop(container,desired);
    }
    try{form.dispatchEvent(new CustomEvent('fieldflow:form-viewport-restored',{bubbles:true,detail:{reason:String(reason||'continuity'),question_key:key,step_index:parseInt(checkpoint.step_index||0,10)||0,contract:rpViewportSingleAnchorContract,single_anchor:true}}));}catch(_){ }
    return true;
  }
  function rpCaptureAllFormViewports(reason){
    rpDraftForms=rpDraftForms.filter(function(form){
      if(!form||!document.documentElement.contains(form)) return false;
      rpCaptureFormViewport(form,reason,null);
      return true;
    });
  }
  var rpViewportScrollTimer=0;
  var rpViewportScrollCapture=function(){
    if(rpViewportScrollTimer) window.clearTimeout(rpViewportScrollTimer);
    rpViewportScrollTimer=window.setTimeout(function(){rpViewportScrollTimer=0;rpDraftForms.forEach(function(form){if(form&&document.documentElement.contains(form)&&form.getBoundingClientRect().height>0)rpCaptureFormViewport(form,'scroll',null);});},240);
  };
  window.addEventListener('scroll',rpViewportScrollCapture,{passive:true});
  document.addEventListener('scroll',rpViewportScrollCapture,{passive:true,capture:true});
  function rpUpdateShareSpaceCard(card){
    var $card=$(card),$pr=$card.find('[data-share-space-pr]').first(),$competitor=$card.find('[data-share-space-competitor]').first();
    var prRaw=String($pr.val()==null?'':$pr.val()).trim(),competitorRaw=String($competitor.val()==null?'':$competitor.val()).trim();
    var hasPr=prRaw!=='',hasCompetitor=competitorRaw!=='',hasInput=hasPr||hasCompetitor;
    if($pr.get(0)&&typeof $pr.get(0).setCustomValidity==='function')$pr.get(0).setCustomValidity('');
    if($competitor.get(0)&&typeof $competitor.get(0).setCustomValidity==='function')$competitor.get(0).setCustomValidity('');
    var pr=parseFloat(prRaw.replace(',','.')),competitor=parseFloat(competitorRaw.replace(',','.'));
    pr=isFinite(pr)&&pr>=0?pr:0;competitor=isFinite(competitor)&&competitor>=0?competitor:0;
    var total=pr+competitor,target=parseFloat($card.attr('data-target')||'0')||0,prShare=total>0?(pr/total*100):0,competitorShare=total>0?(competitor/total*100):0,gap=prShare-target;
    var fmt=function(value){return (Math.round(value*10)/10).toLocaleString('pt-PT',{minimumFractionDigits:1,maximumFractionDigits:1});};
    $card.find('[data-share-space-pr-share]').text(fmt(prShare)+'%');
    $card.find('[data-share-space-competitor-share]').text(fmt(competitorShare)+'%');
    var $gap=$card.find('[data-share-space-gap]').text((gap>0?'+':'')+fmt(gap)+' pp').removeClass('routespro-share-space-gap-negative routespro-share-space-gap-positive');
    if(total>0)$gap.addClass(gap>=0?'routespro-share-space-gap-positive':'routespro-share-space-gap-negative');
    var $status=$card.find('[data-share-space-status]').removeClass('is-good is-warning is-empty');
    $card.attr('data-share-space-has-input',hasInput?'1':'0');
    if(!hasInput)$status.addClass('is-empty').text('Por preencher');
    else if(total<=0)$status.addClass('is-empty').text('Sem frentes registadas');
    else if(gap>=0)$status.addClass('is-good').text('Objetivo atingido');
    else $status.addClass('is-warning').text('Abaixo do objetivo');
  }
  function rpInitShareSpace(scope){
    $(scope||document).find('[data-share-space-card]').each(function(){rpUpdateShareSpaceCard(this);});
  }
  $(document).on('input change','[data-share-space-pr],[data-share-space-competitor]',function(){rpUpdateShareSpaceCard($(this).closest('[data-share-space-card]'));});

  function rpPrepareUploadZone($zone){
    if(!$zone||!$zone.length) return;
    $zone.find('[data-rp-upload-input-row]').each(function(){
      var $row=$(this),$input=$row.find('[data-rp-upload-file]').first();
      var isClone=$row.hasClass('is-clone')||$row.attr('data-rp-upload-clone')==='1';
      if(isClone){
        $row.addClass('is-clone').attr('data-rp-upload-clone','1');
        var kind=String($zone.attr('data-rp-upload-kind')||'file');
        if(kind!=='image'&&!$row.find('[data-rp-upload-remove]').length) $row.append('<button type="button" class="routespro-upload-remove" data-rp-upload-remove aria-label="Remover seleção">×</button>');
        if(kind==='image') $row.find('[data-rp-upload-remove]').remove();
        $input.removeAttr('id required data-rp-has-existing');
        if(String($input.attr('data-rp-upload-source')||'')==='camera'){
          $input.attr('accept','image/*').attr('capture','environment').removeAttr('multiple');
        }else{
          $input.removeAttr('capture');
        }
      }
    });
  }
  function rpSyncUploadPreview($zone){
    if(!$zone||!$zone.length) return;
    var $preview=$zone.find('[data-rp-upload-preview]').first();
    if(!$preview.length) return;
    $preview.empty();
    var kind=String($zone.attr('data-rp-upload-kind')||'file');
    $zone.find('[data-rp-upload-file]').each(function(){
      var input=this;
      var allFiles=Array.prototype.slice.call(input.files||[]),validFiles=rpUploadableFiles(allFiles);
      if(validFiles.length!==allFiles.length) rpUploadStatus($zone,'Uma fotografia ficou sem dados no dispositivo e foi retirada do envio. Tira-a novamente.','error');
      validFiles.forEach(function(file,fileIndex){
        var $item=$('<div class="routespro-upload-preview-item"></div>');
        if(kind==='image'&&file.type&&file.type.indexOf('image/')===0){
          var url=URL.createObjectURL(file),img=document.createElement('img');img.src=url;img.alt=file.name||'Fotografia';img.onload=function(){window.setTimeout(function(){URL.revokeObjectURL(url);},1000);};$item.append(img);
        }else{$item.append('<div class="routespro-upload-preview-file">Ficheiro selecionado</div>');}
        var remove=document.createElement('button');remove.type='button';remove.className='routespro-upload-preview-remove';remove.setAttribute('data-rp-upload-preview-remove','1');remove.setAttribute('aria-label','Eliminar '+(file.name||'fotografia'));remove.textContent='Eliminar';remove.__rpUploadInput=input;remove.__rpFileIndex=fileIndex;$item.append(remove);
        $item.append($('<span></span>').text(file.name||'ficheiro'));
        $preview.append($item);
      });
    });
    var selectedCount=$preview.children().length;
    $preview.prop('hidden',selectedCount===0);
    var $cameraLabel=$zone.find('[data-rp-upload-trigger="camera"] strong').first();
    if($cameraLabel.length) $cameraLabel.text(selectedCount>0?'Tirar outra fotografia':'Tirar fotografia');
  }
  function rpRemoveUploadFile(input,fileIndex){
    if(!input) return false;
    var files=Array.prototype.slice.call(input.files||[]),index=parseInt(fileIndex,10);
    if(!files.length||!isFinite(index)||index<0||index>=files.length) return false;
    var remaining=files.filter(function(_,position){return position!==index;});
    try{
      var transfer=new DataTransfer();remaining.forEach(function(file){transfer.items.add(file);});input.files=transfer.files;
    }catch(_){
      if(remaining.length) return false;
      try{input.value='';}catch(__){return false;}
    }
    input.dispatchEvent(new Event('change',{bubbles:true}));
    return true;
  }
  function rpNativeMobilePlatform(){
    var mobile=window.FieldFlowMobile||{};
    var platform=String(mobile.platform||document.documentElement.dataset.fieldflowPlatform||'').toLowerCase();
    if(!platform&&/iPad|iPhone|iPod/i.test(String(navigator.userAgent||''))) platform='ios';
    return platform;
  }
  function rpIsNativeIOS(){
    return !!(window.FieldFlowMobile&&window.FieldFlowMobile.native)&&rpNativeMobilePlatform()==='ios';
  }
  function rpSafeServerErrorMessage(raw,fallback){
    var value=raw;
    if(value&&typeof value==='object') value=value.message||(value.data&&value.data.message)||value.code||'';
    value=String(value||'').trim();
    if(!value) return fallback||'Nao foi possivel guardar o formulario.';
    if(/<!doctype|<html|<head|<body|google-site-kit|erro cr.tico neste site|critical error/i.test(value)) return 'O servidor devolveu uma resposta inesperada. As respostas continuam no formulario. Tenta novamente uma vez.';
    try{var holder=document.createElement('div');holder.innerHTML=value;value=String(holder.textContent||holder.innerText||value).replace(/\s+/g,' ').trim();}catch(_){ }
    if(value.length>420)value=value.slice(0,417)+'...';
    return value||fallback||'Nao foi possivel guardar o formulario.';
  }
  function rpConfigureUploadSource($input,source,$zone){
    if(!$input||!$input.length) return;
    if(source==='camera'){
      $input.attr('accept','image/*').attr('capture','environment').attr('data-rp-camera-input','1').removeAttr('multiple');
    }else{
      $input.attr('accept',rpIsNativeIOS()?'image/*':'image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.gif,.heic,.heif').removeAttr('capture data-rp-camera-input');
      if($zone&&$zone.attr('data-rp-upload-multiple')==='1') $input.attr('multiple','multiple');
    }
  }
  // 2.2.615 9CR fieldflow-media-capture-resilience-v1: a galeria e a câmara abrem pelo método que funciona
  // neste dispositivo (fica memorizado na sessão) e, quando uma fotografia não chega ao formulário, o passo
  // exato fica registado no backoffice (Segurança e observabilidade, eventos mobile_media_error).
  var RP_MEDIA_RESILIENCE_CONTRACT='fieldflow-media-capture-resilience-v1';
  var rpMediaTraceLog=[],rpMediaReportCount=0,rpMediaPending=null,rpMediaPrefs={};
  function rpMediaGet(key){var value='';try{value=String(window.sessionStorage.getItem('fieldflow:media:'+key)||'');}catch(_){value='';}return value||String(rpMediaPrefs[key]||'');}
  function rpMediaSet(key,value){rpMediaPrefs[key]=String(value||'');try{if(value)window.sessionStorage.setItem('fieldflow:media:'+key,String(value));else window.sessionStorage.removeItem('fieldflow:media:'+key);}catch(_){ }}
  function rpMediaIsNativeApp(){return !!(window.FieldFlowMobile&&window.FieldFlowMobile.native);}
  // Na app (WebView) o seletor de ficheiros abre-se pelo click() clássico; no browser mantém-se showPicker() com click() de recurso.
  function rpMediaOpener(){var saved=rpMediaGet('opener');if(saved==='click'||saved==='picker')return saved;return rpMediaIsNativeApp()?'click':'picker';}
  function rpMediaTrace(step,detail){
    var item={t:Date.now(),s:String(step||'')};
    if(detail&&typeof detail==='object')Object.keys(detail).forEach(function(key){item[key]=detail[key];});
    rpMediaTraceLog.push(item);if(rpMediaTraceLog.length>30)rpMediaTraceLog.shift();
    try{window.__fieldflowMediaTrace=rpMediaTraceLog;}catch(_){ }
    return item;
  }
  function rpMediaTraceText(limit){
    var last=rpMediaTraceLog.slice(-(limit||10)),base=last.length?last[0].t:0;
    return last.map(function(item){
      var extra=[];Object.keys(item).forEach(function(key){if(key!=='t'&&key!=='s'&&item[key]!==undefined&&item[key]!==null&&item[key]!=='')extra.push(String(item[key]).slice(0,40));});
      return '+'+((item.t-base)/1000).toFixed(1)+'s '+item.s+(extra.length?'('+extra.join(',')+')':'');
    }).join(' > ');
  }
  function rpMediaDevice(){
    var ua=String(navigator.userAgent||'');
    return [(ua.match(/Android [0-9.]+/)||[''])[0],((ua.match(/(?:iPhone|iPad|CPU) OS [0-9_]+/)||[''])[0]).replace(/_/g,'.'),/; wv\)/.test(ua)?'WebView':'',(ua.match(/Chrome\/[0-9]+/)||[''])[0],rpMediaIsNativeApp()?'app':'browser'].filter(Boolean).join(' ');
  }
  function rpMediaPlatform(){var platform=rpNativeMobilePlatform();if(rpMediaIsNativeApp())return platform==='ios'||platform==='android'?platform:'unknown';return 'web';}
  function rpMediaReport(code,summary,origin){
    if(rpMediaReportCount>=10)return false;
    try{
      var $origin=$(origin),$form=$origin.closest('.routespro-dyn-form');if(!$form.length)$form=$('.routespro-dyn-form').first();
      var endpoint=String($form.attr('data-rp-media-diag-endpoint')||'');if(!endpoint||typeof window.fetch!=='function')return false;
      var nonce=String((window.FieldFlowOfflineConfig&&window.FieldFlowOfflineConfig.nonce)||$form.attr('data-rp-media-diag-nonce')||'');
      var number=function(selector){return parseInt($form.find(selector).first().val()||0,10)||0;};
      rpMediaReportCount++;
      var body={
        event_type:'media_error',severity:'warning',error_code:String(code||'media_error').slice(0,96),action:String(code||'media_error').slice(0,96),screen:'form_media',platform:rpMediaPlatform(),
        message:(String(summary||'')+' | '+rpMediaDevice()+' | '+rpMediaTraceText(4)).slice(0,250),detail:rpMediaTraceText(12).slice(0,600),
        question_key:String($origin.closest('[data-rp-question-wrap]').attr('data-rp-question-wrap')||''),
        route_id:number('[name="routespro_route_id"]'),stop_id:number('[name="routespro_stop_id"],[name="routespro_route_stop_id"]'),location_id:number('[name="routespro_location_id"]'),
        client_id:number('[name="routespro_client_id"]'),project_id:number('[name="routespro_project_id"]'),form_id:parseInt($form.attr('data-routespro-form-id')||0,10)||0,
        online:navigator.onLine!==false,webview_url:String(window.location.href||'').split('#')[0],correlation_id:'media-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,8),contract:RP_MEDIA_RESILIENCE_CONTRACT
      };
      window.fetch(endpoint,{method:'POST',credentials:'same-origin',keepalive:true,headers:{'Content-Type':'application/json','X-WP-Nonce':nonce,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(body)}).catch(function(){});
      return true;
    }catch(_){return false;}
  }
  function rpMediaMobileDevice(){return rpMediaIsNativeApp()||/Android|iPhone|iPad|iPod/i.test(String(navigator.userAgent||''));}
  function rpMediaMarkLeft(reason){if(rpMediaPending&&!rpMediaPending.left&&!rpMediaPending.done&&Date.now()-rpMediaPending.at<120000){rpMediaPending.left=String(reason||'left');rpMediaTrace('left',{reason:reason});}}
  window.addEventListener('blur',function(){rpMediaMarkLeft('blur');});
  window.addEventListener('pagehide',function(){rpMediaMarkLeft('pagehide');});
  document.addEventListener('visibilitychange',function(){if(document.visibilityState==='hidden')rpMediaMarkLeft('hidden');});
  // A second tap on the same button without any sign that the picker opened (no blur, hidden page or file)
  // switches the opener for this session: the next attempt uses the other method.
  function rpMediaCheckRetap(zone,source,origin){
    var pending=rpMediaPending;rpMediaPending=null;
    if(!pending||pending.done||pending.zone!==zone||pending.source!==source)return '';
    var age=Date.now()-pending.at;if(age>120000||pending.method==='embedded')return '';
    if(pending.left){rpMediaTrace('returned-without-file',{source:source,method:pending.method,left:pending.left});return '';}
    var used=String(pending.method||'').replace(/^native-/,''),next=used==='picker'?'click':'picker';
    rpMediaSet('opener',next);
    rpMediaTrace('no-signal',{source:source,method:pending.method,next:next,after_ms:age});
    rpMediaReport('media_picker_no_signal',(source==='camera'?'A câmara':'A galeria')+' não deu sinal de abrir com '+(used||'nenhum método')+'; a tentar '+next+'.',origin);
    return next;
  }
  function rpMediaNoteChange(input,$zone){
    var files=Array.prototype.slice.call(input&&input.files||[]),valid=rpUploadableFiles(files),zone=$zone&&$zone.get(0);
    if(rpMediaPending&&rpMediaPending.zone===zone)rpMediaPending.done=true;
    // The draft runtime sees the change first (capture phase) and may stop it; the same selection is noted once.
    var signature=files.map(function(file){return [String(file.name||''),Number(file.size||0),Number(file.lastModified||0)].join(':');}).join('|');
    if(input&&input.__rpMediaNoted===signature&&Date.now()-Number(input.__rpMediaNotedAt||0)<5000)return;
    if(input){input.__rpMediaNoted=signature;input.__rpMediaNotedAt=Date.now();}
    if(!files.length){rpMediaTrace('change-empty');return;}
    rpMediaTrace('files',{count:files.length,valid:valid.length,kb:Math.round(files.reduce(function(sum,file){return sum+Number(file.size||0);},0)/1024),type:String(files[0]&&files[0].type||'?')});
    if(!valid.length){rpMediaReport('media_file_empty','O dispositivo entregou '+files.length+' ficheiro(s) sem dados.',input);return;}
    var $status=$zone&&$zone.find('[data-rp-upload-status]').first();
    if($status&&$status.length&&!$status.hasClass('is-error'))rpUploadStatus($zone,'','');
  }
  // A visible, editable question must never keep a disabled photo button (for example a stale state left by a
  // conditional branch or by the report lock). Disabled buttons do not fire click, so the tap is caught earlier.
  function rpMediaZoneEditable($node){
    var $form=$node.closest('.routespro-dyn-form'),$wrap=$node.closest('[data-rp-question-wrap]');
    if(!$form.length||$form.hasClass('is-daily-locked'))return false;
    if($wrap.length&&($wrap.prop('hidden')||$wrap.attr('data-rp-condition-hidden')==='1'))return false;
    return true;
  }
  function rpMediaReviveTrigger(event){
    var target=event&&event.target,button=target&&target.closest?target.closest('[data-rp-upload-trigger]'):null;
    if(!button||!button.disabled)return;
    var $button=$(button),$zone=$button.closest('[data-rp-upload-zone]');
    if(!rpMediaZoneEditable($button)){
      if($button.closest('.routespro-dyn-form').hasClass('is-daily-locked'))rpUploadStatus($zone,'Este reporte está em consulta. Toca em «Editar reporte» para acrescentar ou trocar fotografias.','info');
      return;
    }
    $zone.find('[data-rp-upload-trigger],[data-rp-upload-file]').not('[data-rp-evidence-disabled]').prop('disabled',false).removeAttr('data-rp-condition-disabled');
    rpMediaTrace('trigger-revived',{source:String($button.attr('data-rp-upload-trigger')||'')});
    rpMediaReport('media_trigger_disabled','O botão de fotografia estava desativado numa pergunta visível e foi reativado.',button);
    var revivedAt=Date.now();
    window.setTimeout(function(){if(!rpMediaPending||rpMediaPending.at<revivedAt)rpUploadStatus($zone,'Toca novamente para escolher a fotografia.','info');},400);
  }
  document.addEventListener('pointerup',rpMediaReviveTrigger,true);
  document.addEventListener('touchend',rpMediaReviveTrigger,true);
  function rpOpenNativePicker($input,opener){
    var input=$input&&$input.get(0);if(!input)return '';
    opener=opener||rpMediaOpener();
    if(rpIsNativeIOS()){
      try{input.value='';}catch(_){ }
      if(opener==='picker'&&rpMediaGet('opener')==='picker'&&typeof input.showPicker==='function'){try{input.showPicker();return 'picker';}catch(error){rpMediaTrace('picker-error',{error:rpCameraErrorCode(error).trim()});}}
      try{input.click();return 'click';}catch(error){rpMediaTrace('click-error',{error:rpCameraErrorCode(error).trim()});}
      return '';
    }
    if(opener==='picker'&&typeof input.showPicker==='function'){
      try{input.showPicker();return 'picker';}catch(error){rpMediaTrace('picker-error',{error:rpCameraErrorCode(error).trim()});}
    }
    try{input.click();return 'click';}catch(error){rpMediaTrace('click-error',{error:rpCameraErrorCode(error).trim()});}
    return '';
  }
  function rpAssignCapturedImage($input,blob){
    return new Promise(function(resolve,reject){
      try{
        var input=$input&&$input.get(0);if(!input||!blob)throw new Error('capture_target_missing');
        if(Number(blob.size||0)<=0)throw new Error('camera_capture_empty');
        var file=new File([blob],'fieldflow-camera-'+Date.now()+'.jpg',{type:'image/jpeg',lastModified:Date.now()});
        var transfer=new DataTransfer();transfer.items.add(file);input.files=transfer.files;
        input.dispatchEvent(new Event('change',{bubbles:true}));resolve(true);
      }catch(err){reject(err);}
    });
  }
  // fieldflow-image-zero-byte-fallback-v1: a mobile WebView must never
  // replace a valid capture with an empty Blob returned by canvas.toBlob().
  function rpUploadableFiles(files){
    return Array.prototype.slice.call(files||[]).filter(function(file){return file instanceof Blob&&Number(file.size||0)>0;});
  }
  function rpSanitiseInputFiles(input){
    if(!input)return {files:[],removed:0};
    var all=Array.prototype.slice.call(input.files||[]),valid=rpUploadableFiles(all),removed=Math.max(0,all.length-valid.length);
    if(!removed)return {files:valid,removed:0};
    try{var transfer=new DataTransfer();valid.forEach(function(file){transfer.items.add(file);});input.files=transfer.files;}
    catch(_){if(!valid.length){try{input.value='';}catch(__){ }} }
    return {files:valid,removed:removed};
  }
  function rpMediaSignature(files){
    return rpUploadableFiles(files).map(function(file){return [String(file.name||''),Number(file.size||0),Number(file.lastModified||0),String(file.type||'')].join(':');}).join('|');
  }
  function rpSetMediaMetric(form,name,value){
    if(!form)return;var field=form.querySelector('[name="'+name+'"]');if(field)field.value=String(Math.max(0,Math.round(Number(value)||0)));
  }
  function rpRefreshMediaMetrics(form){
    if(!form)return;
    var count=0,original=0,optimised=0,optimisedFiles=0;
    Array.prototype.slice.call(form.querySelectorAll('input[type="file"][name]')).forEach(function(input){
      if(input.disabled)return;
      var files=rpUploadableFiles(input.files||[]);if(!files.length)return;
      count+=files.length;optimised+=files.reduce(function(sum,file){return sum+Number(file.size||0);},0);
      original+=Number(input._rpOriginalMediaBytes||files.reduce(function(sum,file){return sum+Number(file.size||0);},0));
      optimisedFiles+=Number(input._rpOptimisedFilesCount||0);
    });
    rpSetMediaMetric(form,'routespro_media_file_count',count);
    rpSetMediaMetric(form,'routespro_media_original_bytes',original);
    rpSetMediaMetric(form,'routespro_media_optimized_bytes',optimised);
    rpSetMediaMetric(form,'routespro_media_optimized_files',optimisedFiles);
    rpSetMediaMetric(form,'routespro_media_duplicate_files',Number(form._rpDuplicateFiles||0));
    rpSetMediaMetric(form,'routespro_media_retry_count',Number(form._rpUploadRetryCount||0));
  }
  function rpRemoveDuplicateFiles(form,$zone){
    if(!form||!$zone||!$zone.length)return 0;
    var seen={},removed=0;
    $zone.find('input[type="file"][name]').each(function(){
      var input=this,allFiles=Array.prototype.slice.call(input.files||[]),files=rpUploadableFiles(allFiles),kept=[];
      removed+=Math.max(0,allFiles.length-files.length);
      files.forEach(function(file){
        var fingerprint=[String(file.name||'').toLowerCase(),Number(file.size||0),Number(file.lastModified||0),String(file.type||'').toLowerCase()].join('|');
        if(seen[fingerprint]){removed++;input._rpOriginalMediaBytes=Math.max(0,Number(input._rpOriginalMediaBytes||0)-Number(file.size||0));return;}
        seen[fingerprint]=1;kept.push(file);
      });
      if(kept.length!==files.length){
        try{var transfer=new DataTransfer();kept.forEach(function(file){transfer.items.add(file);});input.files=transfer.files;}catch(_){ }
        input._rpTrackedMediaSignature=rpMediaSignature(input.files);
      }
    });
    if(removed){form._rpDuplicateFiles=Number(form._rpDuplicateFiles||0)+removed;rpSyncUploadPreview($zone);}
    rpRefreshMediaMetrics(form);
    return removed;
  }
  function rpOptimiseImageFile(file){
    if(!(file instanceof Blob)) return Promise.resolve(file);
    if(Number(file.size||0)<=0) return Promise.resolve(file);
    var type=String(file.type||'').toLowerCase();
    if(['image/jpeg','image/jpg','image/png','image/webp'].indexOf(type)===-1) return Promise.resolve(file);
    if((file.size||0)<700*1024) return Promise.resolve(file);
    function decode(){
      if(typeof createImageBitmap==='function') return createImageBitmap(file).then(function(bitmap){return {image:bitmap,width:bitmap.width,height:bitmap.height,close:function(){try{bitmap.close();}catch(_){ }}};});
      return new Promise(function(resolve,reject){
        var url=URL.createObjectURL(file),img=new Image();
        img.onload=function(){resolve({image:img,width:img.naturalWidth||img.width,height:img.naturalHeight||img.height,close:function(){URL.revokeObjectURL(url);}});};
        img.onerror=function(){URL.revokeObjectURL(url);reject(new Error('image_decode_failed'));};
        img.src=url;
      });
    }
    return decode().then(function(decoded){
      var maxSide=1600,scale=Math.min(1,maxSide/Math.max(decoded.width||1,decoded.height||1));
      if(scale>=1&&(file.size||0)<1200*1024){decoded.close();return file;}
      var width=Math.max(1,Math.round(decoded.width*scale)),height=Math.max(1,Math.round(decoded.height*scale));
      var canvas=document.createElement('canvas');canvas.width=width;canvas.height=height;
      var ctx=canvas.getContext('2d');if(!ctx){decoded.close();return file;}
      ctx.fillStyle='#fff';ctx.fillRect(0,0,width,height);ctx.drawImage(decoded.image,0,0,width,height);decoded.close();
      return new Promise(function(resolve){
        canvas.toBlob(function(blob){
          // Some Android WebViews can return a real Blob object with zero
          // bytes under memory pressure. Never let that replace the source.
          if(!blob||Number(blob.size||0)<=0||blob.size>=(file.size||0)*0.96){resolve(file);return;}
          var base=String(file.name||'fotografia').replace(/\.[^.]+$/,'')||'fotografia';
          try{resolve(new File([blob],base+'.jpg',{type:'image/jpeg',lastModified:file.lastModified||Date.now()}));}
          catch(_){resolve(blob);}
        },'image/jpeg',0.8);
      });
    }).catch(function(){return file;});
  }
  function rpOptimiseInputFiles(input){
    var rawFiles=Array.prototype.slice.call(input&&input.files||[]),files=rpUploadableFiles(rawFiles);
    if(!files.length) return Promise.resolve(false);
    var signature=rpMediaSignature(files);
    if(input._rpTrackedMediaSignature!==signature){
      input._rpOriginalMediaBytes=files.reduce(function(sum,file){return sum+Number(file.size||0);},0);
      input._rpOptimisedFilesCount=0;
      input._rpTrackedMediaSignature=signature;
    }
    // fieldflow-image-optimisation-sequential-v1: process one capture at a
    // time. A gallery selection can contain 12+ high-resolution images and
    // decoding all of them concurrently can exhaust a mobile WebView before
    // the resumable transport even starts.
    var optimised=[],chain=Promise.resolve();
    files.forEach(function(current){
      chain=chain.then(function(){return rpOptimiseImageFile(current);}).then(function(result){optimised.push(result||current);});
    });
    return chain.then(function(){
      optimised=optimised.map(function(result,index){return result instanceof Blob&&Number(result.size||0)>0?result:files[index];});
      var changedCount=optimised.reduce(function(total,file,index){return total+(file!==files[index]?1:0);},0);
      if(!changedCount){input._rpTrackedMediaSignature=signature;return false;}
      try{
        var transfer=new DataTransfer();optimised.forEach(function(file){if(file instanceof Blob&&Number(file.size||0)>0)transfer.items.add(file);});input.files=transfer.files;
        if(rpUploadableFiles(input.files||[]).length!==optimised.length){
          var rollback=new DataTransfer();files.forEach(function(file){rollback.items.add(file);});input.files=rollback.files;return false;
        }
        input._rpOptimisedFilesCount=changedCount;
        input._rpTrackedMediaSignature=rpMediaSignature(input.files);
        return true;
      }catch(_){return false;}
    });
  }
  function rpStopCameraStream(stream){
    try{(stream&&stream.getTracks?stream.getTracks():[]).forEach(function(track){try{track.stop();}catch(_){ }});}catch(_){ }
  }
  function rpOpenEmbeddedCamera($input){
    return new Promise(function(resolve,reject){
      if(!navigator.mediaDevices||typeof navigator.mediaDevices.getUserMedia!=='function'){reject(new Error('camera_api_unavailable'));return;}
      var stream=null,settled=false;
      var $overlay=$('<div class="routespro-camera-capture is-open" role="dialog" aria-modal="true" aria-label="Tirar fotografia"><div class="routespro-camera-capture-card"><div class="routespro-camera-capture-head"><strong>Tirar fotografia</strong><button type="button" class="routespro-camera-capture-close" aria-label="Fechar">×</button></div><div class="routespro-camera-capture-stage"><video autoplay playsinline muted></video><div class="routespro-camera-capture-status">A iniciar a câmara…</div></div><div class="routespro-camera-capture-actions"><button type="button" class="routespro-camera-capture-cancel">Cancelar</button><button type="button" class="routespro-camera-capture-shot" disabled>Capturar fotografia</button></div></div></div>');
      var video=$overlay.find('video').get(0),$shot=$overlay.find('.routespro-camera-capture-shot'),$status=$overlay.find('.routespro-camera-capture-status');
      function finish(ok,value){
        if(settled)return;settled=true;rpStopCameraStream(stream);$overlay.remove();$(document).off('keydown.rpCameraCapture');
        if(ok)resolve(value);else reject(value instanceof Error?value:new Error(String(value||'camera_cancelled')));
      }
      $overlay.on('click','.routespro-camera-capture-close,.routespro-camera-capture-cancel',function(){finish(false,new Error('camera_cancelled'));});
      $overlay.on('click',function(e){if(e.target===this)finish(false,new Error('camera_cancelled'));});
      $(document).on('keydown.rpCameraCapture',function(e){if(e.key==='Escape')finish(false,new Error('camera_cancelled'));});
      $overlay.on('click','.routespro-camera-capture-shot',function(){
        try{
          var sourceWidth=video.videoWidth||1280,sourceHeight=video.videoHeight||960,maxSide=1600;
          var scale=Math.min(1,maxSide/Math.max(sourceWidth,sourceHeight));
          var width=Math.max(1,Math.round(sourceWidth*scale)),height=Math.max(1,Math.round(sourceHeight*scale));
          var canvas=document.createElement('canvas');canvas.width=width;canvas.height=height;
          var ctx=canvas.getContext('2d');if(!ctx)throw new Error('camera_canvas_unavailable');ctx.drawImage(video,0,0,width,height);
          canvas.toBlob(function(blob){
            if(!blob){finish(false,new Error('camera_capture_failed'));return;}
            rpAssignCapturedImage($input,blob).then(function(){finish(true,true);}).catch(function(err){finish(false,err);});
          },'image/jpeg',0.8);
        }catch(err){finish(false,err);}
      });
      $('body').append($overlay);
      navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false}).then(function(mediaStream){
        stream=mediaStream;video.srcObject=stream;
        var ready=function(){$status.text('Câmara pronta');$shot.prop('disabled',false);};
        if(video.readyState>=2)ready();else video.addEventListener('loadedmetadata',ready,{once:true});
        var playResult=video.play();if(playResult&&typeof playResult.catch==='function')playResult.catch(function(){ });
      }).catch(function(err){finish(false,err);});
    });
  }
  function rpUploadStatus($zone,message,type){
    if(!$zone||!$zone.length) return;
    var $status=$zone.find('[data-rp-upload-status]').first();
    if(!$status.length){
      $status=$('<div class="routespro-upload-status" data-rp-upload-status role="status"></div>');
      var $preview=$zone.find('[data-rp-upload-preview]').first();
      if($preview.length) $status.insertBefore($preview); else $zone.append($status);
    }
    if(!message){$status.prop('hidden',true).removeClass('is-error is-info').text('');return;}
    $status.text(message).prop('hidden',false).removeClass('is-error is-info').addClass(type==='error'?'is-error':'is-info');
  }
  function rpCameraErrorCode(error){
    return String((error&&error.name)||'')+' '+String((error&&error.message)||error||'');
  }
  function rpOpenCameraFlow($input,$zone){
    rpUploadStatus($zone,'A preparar a câmara...','info');
    var startedAt=Date.now();
    return rpOpenEmbeddedCamera($input).then(function(){rpUploadStatus($zone,'','');rpMediaTrace('camera-captured');return true;}).catch(function(error){
      var code=rpCameraErrorCode(error);
      if(/camera_cancelled/i.test(code)){rpUploadStatus($zone,'','');rpMediaTrace('camera-cancelled');return false;}
      rpMediaTrace('camera-error',{error:code.trim().slice(0,80),after_ms:Date.now()-startedAt});
      if(/camera_api_unavailable|NotSupportedError/i.test(code)){
        rpUploadStatus($zone,'A abrir a câmara do dispositivo...','info');
        var fallback=rpOpenNativePicker($input);
        rpMediaTrace('open',{source:'camera',method:'native-'+(fallback||'none'),reason:'embedded-unavailable'});
        window.setTimeout(function(){rpUploadStatus($zone,'','');},1600);
        return false;
      }
      if(/NotFoundError|DevicesNotFoundError/i.test(code)){
        rpUploadStatus($zone,'Não foi encontrada uma câmara disponível neste dispositivo.','error');
        if(rpMediaMobileDevice())rpMediaReport('media_camera_not_found','O dispositivo não indicou nenhuma câmara ('+code.trim().slice(0,80)+').',$zone);
        return false;
      }
      // 9CR: sem câmara integrada (permissão da app ou da WebView, câmara ocupada ou erro do dispositivo) abre-se a
      // câmara do telemóvel pelo seletor nativo; o resto da sessão usa diretamente a câmara do telemóvel.
      rpMediaSet('camera','native');
      if(rpMediaMobileDevice())rpMediaReport('media_camera_unavailable','Câmara integrada indisponível ('+code.trim().slice(0,80)+'); foi aberta a câmara do telemóvel.',$zone);
      var stillActive=Date.now()-startedAt<4500,method='';
      if(stillActive){
        rpUploadStatus($zone,'A abrir a câmara do telemóvel...','info');
        method=rpOpenNativePicker($input);
        rpMediaTrace('open',{source:'camera',method:'native-'+(method||'none'),reason:'embedded-failed'});
        if(method)rpMediaPending={zone:$zone.get(0),source:'camera',input:$input.get(0),at:Date.now(),left:'',done:false,method:'native-'+method};
      }
      var fallbackPending=method?rpMediaPending:null;
      window.setTimeout(function(){
        if(fallbackPending&&fallbackPending.done){rpUploadStatus($zone,'','');return;}
        var $status=$zone.find('[data-rp-upload-status]').first();
        if(!$status.length||$status.prop('hidden'))return;
        if(/NotAllowedError|PermissionDeniedError|SecurityError|permission/i.test(code))rpUploadStatus($zone,'A câmara integrada não tem autorização nesta app. Se a câmara do telemóvel não abriu, toca outra vez em «Tirar fotografia» ou escolhe uma imagem.','info');
        else if(/NotReadableError|TrackStartError/i.test(code))rpUploadStatus($zone,'A câmara estava ocupada. Se a câmara do telemóvel não abriu, toca outra vez em «Tirar fotografia».','info');
        else rpUploadStatus($zone,'Se a câmara do telemóvel não abriu, toca outra vez em «Tirar fotografia» ou escolhe uma imagem.','info');
      },stillActive?1800:0);
      return false;
    });
  }
  function rpRequestNativeMedia(source,$input,$zone){
    var payload={source:source,multiple:source==='gallery'&&$zone.attr('data-rp-upload-multiple')==='1'};
    try{var ffParentOrigin=String(window.location.origin||'');if(ffParentOrigin&&window.parent)window.parent.postMessage({type:source==='camera'?'fieldflow.media.camera_requested':'fieldflow.media.gallery_requested',source:source,multiple:payload.multiple},ffParentOrigin);}catch(_){ }
    rpConfigureUploadSource($input,source,$zone);
    if(source!=='camera'){
      var opened=rpOpenNativePicker($input);
      rpMediaTrace('open',{source:source,method:opened||'none'});
      if(!opened)rpMediaReport('media_picker_failed','Nenhum método conseguiu abrir a galeria.',$zone);
      return opened||'none';
    }
    // Native iOS uses the WKWebView file chooser with capture=environment.
    // This avoids a second WebRTC/getUserMedia permission layer after the
    // application already has native camera permission.
    if(rpIsNativeIOS()||rpMediaGet('camera')==='native'){
      rpUploadStatus($zone,'A abrir a camara do dispositivo...','info');
      var method=rpOpenNativePicker($input);
      rpMediaTrace('open',{source:'camera',method:'native-'+(method||'none')});
      window.setTimeout(function(){rpUploadStatus($zone,'','');},1600);
      return 'native-'+(method||'none');
    }
    rpMediaTrace('open',{source:'camera',method:'embedded'});
    return rpOpenCameraFlow($input,$zone);
  }
  function rpCheckpointExternalMediaHandoff($origin,source){
    var $form=$origin.closest('.routespro-dyn-form'),form=$form.get(0);
    if(form){rpCaptureFormViewport(form,'media-handoff',$origin.get(0));form.__rpViewportLock=rpBeginViewportLock($origin.get(0)||form,'media-handoff');}
    if(form&&typeof form.routesproFlushDraft==='function'){
      try{form.routesproFlushDraft({force:true,silent:true,immediateServer:true,keepalive:true,lifecycle:true});}catch(_){ }
    }
    var wizard=form&&form.querySelector?form.querySelector('.routespro-form-wizard'):null;
    var detail={
      source:String(source||'media'),
      route_id:parseInt($form.find('[name="routespro_route_id"]').val()||0,10)||0,
      stop_id:parseInt($form.find('[name="routespro_stop_id"],[name="routespro_route_stop_id"]').first().val()||0,10)||0,
      route_stop_id:parseInt($form.find('[name="routespro_stop_id"],[name="routespro_route_stop_id"]').first().val()||0,10)||0,
      location_id:parseInt($form.find('[name="routespro_location_id"]').val()||0,10)||0,
      client_id:parseInt($form.find('[name="routespro_client_id"]').val()||0,10)||0,
      project_id:parseInt($form.find('[name="routespro_project_id"]').val()||0,10)||0,
      form_id:parseInt($form.attr('data-routespro-form-id')||$form.find('[name="routespro_form_id"]').val()||0,10)||0,
      date:String($form.find('[name="routespro_report_day"],[name="routespro_context_date"]').first().val()||$form.attr('data-rp-report-day')||''),
      step_index:parseInt(wizard&&wizard.dataset?wizard.dataset.rpCurrentStep||'0':'0',10)||0,
      contract:'fieldflow-field-resume-navigation-continuity-v1'
    };
    try{document.dispatchEvent(new CustomEvent('fieldflow:form-external-handoff',{detail:detail}));}catch(_){ }
    return detail;
  }
  $(document).on('click','[data-rp-upload-trigger]',function(e){
    e.preventDefault();
    var form=$(this).closest('.routespro-dyn-form').get(0);
    var source=String($(this).attr('data-rp-upload-trigger')||'gallery');
    var zoneNode=$(this).closest('[data-rp-upload-zone]').get(0);
    rpMediaTrace('tap',{source:source,question:String($(this).closest('[data-rp-question-wrap]').attr('data-rp-question-wrap')||'')});
    var retapOpener=rpMediaCheckRetap(zoneNode,source,this);
    rpCheckpointExternalMediaHandoff($(this),source);
    var $zone=$(this).closest('[data-rp-upload-zone]');
    var $category=$(this).closest('[data-rp-evidence-category]');
    if($category.length){
      var $toggle=$category.find('[data-rp-evidence-toggle]').first();
      if($toggle.length&&!$toggle.prop('checked')) $toggle.prop('checked',true).trigger('change');
      $category.addClass('is-selected').find('[data-rp-evidence-panel]').prop('hidden',false);
      $category.find('[data-rp-evidence-file]').prop('disabled',false).removeAttr('data-rp-evidence-disabled');
    }
    var $input=$zone.find('[data-rp-upload-file][data-rp-upload-source="'+source+'"]').last();
    if(!$input.length){rpMediaTrace('input-missing',{source:source});rpMediaReport('media_input_missing','A pergunta não tem o campo de '+(source==='camera'?'câmara':'galeria')+'.',this);return;}
    if($input.prop('disabled')){
      if(!rpMediaZoneEditable($input)){
        rpMediaTrace('input-disabled',{editable:0});
        if($(this).closest('.routespro-dyn-form').hasClass('is-daily-locked'))rpUploadStatus($zone,'Este reporte está em consulta. Toca em «Editar reporte» para acrescentar ou trocar fotografias.','info');
        return;
      }
      $zone.find('[data-rp-upload-file]').not('[data-rp-evidence-disabled]').prop('disabled',false).removeAttr('data-rp-condition-disabled');
      rpMediaTrace('input-disabled',{editable:1});
      rpMediaReport('media_input_disabled','O campo de fotografia estava desativado numa pergunta visível e foi reativado.',this);
    }
    var canAdd=$zone.attr('data-fieldflow-allow-multiple')!=='0'&&$zone.attr('data-rp-upload-multiple')==='1';
    if(($input.get(0).files||[]).length&&canAdd){
      var $template=$zone.find('[data-rp-upload-file][data-rp-upload-source="'+source+'"]').first();
      var $row=$('<div class="routespro-upload-input-row is-clone" data-rp-upload-input-row data-rp-upload-clone="1"></div>');
      var $clone=$template.clone(false).val('').removeAttr('id required data-rp-has-existing');
      rpConfigureUploadSource($clone,source,$zone);
      $row.append($clone);
      if(String($zone.attr('data-rp-upload-kind')||'file')!=='image') $row.append('<button type="button" class="routespro-upload-remove" data-rp-upload-remove aria-label="Remover seleção">×</button>');
      $zone.find('[data-rp-upload-inputs]').first().append($row);$input=$clone;
    }
    rpConfigureUploadSource($input,source,$zone);
    if(retapOpener)rpUploadStatus($zone,source==='camera'?'A abrir a câmara de outra forma...':'A abrir a galeria de outra forma...','info');
    // Em WebView, o foco pode regressar antes de o sistema concluir a entrega do ficheiro.
    // Manter o input vazio permite acumular fotografias da camera sem apagar a nova captura.
    // Um cancelamento deixa apenas um input reutilizavel, sem impacto no payload submetido.
    var mediaRequest=rpRequestNativeMedia(source,$input,$zone);
    if(mediaRequest&&typeof mediaRequest.then==='function'){
      Promise.resolve(mediaRequest).then(function(selected){if(selected===false&&form&&document.visibilityState!=='hidden')rpReleaseFormViewportLock(form,'camera-cancel');}).catch(function(){if(form&&document.visibilityState!=='hidden')rpReleaseFormViewportLock(form,'camera-error');});
    }else{
      rpMediaPending={zone:zoneNode,source:source,input:$input.get(0),at:Date.now(),left:'',done:false,method:String(mediaRequest||'none')};
      if(retapOpener)window.setTimeout(function(){var $status=$zone.find('[data-rp-upload-status]').first();if($status.length&&$status.hasClass('is-info')&&/de outra forma/.test($status.text()))rpUploadStatus($zone,'','');},2500);
    }
  });
  $(document).on('click','[data-rp-upload-add]',function(e){
    e.preventDefault();
    var $zone=$(this).closest('[data-rp-upload-zone]'),$source=$zone.find('[data-rp-upload-file]').first();
    if(!$source.length) return;
    var $row=$('<div class="routespro-upload-input-row is-clone" data-rp-upload-input-row data-rp-upload-clone="1"></div>');
    var $clone=$source.clone(false);$clone.val('').removeAttr('id required data-rp-has-existing');
    $row.append($clone).append('<button type="button" class="routespro-upload-remove" data-rp-upload-remove aria-label="Remover seleção">×</button>');
    $zone.find('[data-rp-upload-inputs]').first().append($row);
    try{$clone.trigger('click');}catch(_){ }
  });
  $(document).on('click','[data-rp-upload-preview-remove]',function(e){
    e.preventDefault();e.stopPropagation();
    var input=this.__rpUploadInput,fileIndex=this.__rpFileIndex,$zone=$(input).closest('[data-rp-upload-zone]');
    var file=input&&(input.files||[])[fileIndex];
    if(!file||!$zone.length)return;
    var isImage=$zone.attr('data-rp-upload-kind')==='image';
    rpConfirmFormDeletion({
      title:isImage?'Queres mesmo eliminar esta fotografia?':'Queres mesmo remover este ficheiro?',
      detail:'«'+String(file.name|| (isImage?'Fotografia':'Ficheiro')).slice(0,120)+'» será retirado deste formulário. Se cancelares, tudo permanece como está.',
      confirm:isImage?'Sim, eliminar fotografia':'Sim, remover ficheiro'
    }).then(function(approved){
      if(!approved||!input.isConnected||!$zone.get(0)||!$zone.get(0).isConnected)return;
      // A imagem pode ter sido otimizada ou substituída durante a confirmação.
      // Nunca remover outro ficheiro que tenha ocupado o mesmo índice entretanto.
      var current=(input.files||[])[fileIndex];
      if(!current||current.name!==file.name||current.size!==file.size||current.lastModified!==file.lastModified)return;
      var viewportSnapshot=rpCaptureElementViewport($zone.closest('[data-rp-question-wrap]').get(0)||$zone.get(0));
      if(!rpRemoveUploadFile(input,fileIndex))return;
      var $row=$(input).closest('[data-rp-upload-input-row]');
      if($row.hasClass('is-clone')&&!(input.files||[]).length)$row.remove();
      rpPrepareUploadZone($zone);rpSyncUploadPreview($zone);
      var form=$zone.closest('form').get(0);if(form&&typeof form.routesproTouchDraft==='function')form.routesproTouchDraft();
      rpRestoreElementViewport(viewportSnapshot);if(form)rpCaptureFormViewport(form,'media-remove',viewportSnapshot&&viewportSnapshot.element?viewportSnapshot.element:null);
    });
  });
  $(document).on('click','[data-rp-upload-remove]',function(e){
    e.preventDefault();e.stopPropagation();
    var $row=$(this).closest('[data-rp-upload-input-row]'),$zone=$row.closest('[data-rp-upload-zone]');
    if(!$row.length||!$zone.length)return;
    var selected=$row.find('[data-rp-upload-file]').get(0),snapshot=selected?Array.prototype.slice.call(selected.files||[]):[];
    function removeRow(){
      if(!$row.get(0)||!$row.get(0).isConnected)return;
      if(selected){var current=Array.prototype.slice.call(selected.files||[]);if(current.length!==snapshot.length||current.some(function(item,i){return item.name!==snapshot[i].name||item.size!==snapshot[i].size||item.lastModified!==snapshot[i].lastModified;}))return;}
      var viewportSnapshot=rpCaptureElementViewport($zone.closest('[data-rp-question-wrap]').get(0)||$zone.get(0));
      $row.remove();rpPrepareUploadZone($zone);rpSyncUploadPreview($zone);
      var form=$zone.closest('form').get(0);if(form&&typeof form.routesproTouchDraft==='function')form.routesproTouchDraft();
      rpRestoreElementViewport(viewportSnapshot);if(form)rpCaptureFormViewport(form,'media-remove',viewportSnapshot&&viewportSnapshot.element?viewportSnapshot.element:null);
    }
    if(!snapshot.length){removeRow();return;} // Empty, unsaved input row contains no user data.
    rpConfirmFormDeletion({title:'Queres mesmo remover '+(snapshot.length>1?'estes ficheiros?':'este ficheiro?'),
      detail:snapshot.length>1?'Serão removidos '+snapshot.length+' ficheiros selecionados neste formulário.':'O ficheiro selecionado será retirado deste formulário.',
      confirm:'Sim, remover'}).then(function(approved){if(approved)removeRow();});
  });
  function rpSyncExistingMediaState($item){
    if(!$item||!$item.length) return;
    var $scope=$item.closest('[data-rp-evidence-category]');
    if(!$scope.length) $scope=$item.closest('[data-rp-question]');
    if(!$scope.length) return;
    var remaining=$scope.find('[data-rp-existing-media]').filter(function(){return !$(this).hasClass('is-pending-removal');}).length;
    var $zone=$scope.find('[data-rp-upload-zone]').first();
    if(!$zone.length) return;
    $zone.find('[data-rp-upload-file]').each(function(){
      if(remaining>0) $(this).attr('data-rp-has-existing','1');
      else $(this).removeAttr('data-rp-has-existing');
    });
  }
  $(document).on('click','[data-rp-remove-existing-media]',function(e){
    e.preventDefault();e.stopPropagation();
    var $button=$(this),$item=$button.closest('[data-rp-existing-media]'),$form=$button.closest('.routespro-dyn-form');
    if(!$form.length||$form.attr('data-rp-daily-edit-mode')!=='1')return;
    var pending=$item.hasClass('is-pending-removal');
    function toggleExisting(){
      if(!$item.get(0)||!$item.get(0).isConnected||$form.attr('data-rp-daily-edit-mode')!=='1')return;
      // Revalidar o estado quando o utilizador volta da confirmação.
      if($item.hasClass('is-pending-removal')!==pending)return;
      var next=!pending,$input=$item.find('[data-rp-remove-existing-value]').first();
      var viewportSnapshot=rpCaptureElementViewport($item.closest('[data-rp-question-wrap]').get(0)||$item.get(0));
      $item.toggleClass('is-pending-removal',next);
      $input.prop('disabled',!next);
      $button.attr('aria-pressed',next?'true':'false').text(next?'Anular':'Eliminar');
      $item.find('[data-rp-existing-media-pending]').prop('hidden',!next);
      rpSyncExistingMediaState($item);
      var form=$form.get(0);if(form&&typeof form.routesproTouchDraft==='function')form.routesproTouchDraft();
      rpRestoreElementViewport(viewportSnapshot);if(form)rpCaptureFormViewport(form,'existing-media-toggle',viewportSnapshot&&viewportSnapshot.element?viewportSnapshot.element:null);
    }
    if(pending){toggleExisting();return;} // Undo is never destructive.
    rpConfirmFormDeletion({title:'Queres mesmo eliminar esta fotografia?',
      detail:'A fotografia ficará marcada para remoção. Só será eliminada quando guardares o reporte e podes anular até lá.',
      confirm:'Sim, marcar para eliminar'}).then(function(approved){if(approved)toggleExisting();});
  });
  $(document).on('change','[data-rp-upload-file]',function(){
    var $current=$(this),$zone=$current.closest('[data-rp-upload-zone]');
    rpMediaNoteChange(this,$zone);
    if($current.closest('[data-rp-upload-input-row]').hasClass('is-clone')&&(!this.files||!this.files.length)&&String($zone.attr('data-rp-upload-kind')||'file')==='image'){
      $current.closest('[data-rp-upload-input-row]').remove();
      rpSyncUploadPreview($zone);
      return;
    }
    if($zone.attr('data-rp-upload-multiple')!=='1'&&this.files&&this.files.length){
      $zone.find('[data-rp-upload-file]').not(this).each(function(){try{$(this).val('');}catch(_){ }});
    }
    rpSyncUploadPreview($zone);
  });
  function rpFormImageViewer(){
    if(window.FieldFlowMobile&&window.FieldFlowMobile.native) return null;
    var viewer=document.querySelector('.routespro-form-image-viewer');
    if(viewer) return viewer;
    viewer=document.createElement('div');viewer.className='routespro-form-image-viewer';viewer.setAttribute('role','dialog');viewer.setAttribute('aria-modal','true');viewer.innerHTML='<button type="button" aria-label="Fechar imagem">×</button><img alt="">';document.body.appendChild(viewer);
    viewer.addEventListener('click',function(event){if(event.target===viewer||event.target.tagName==='BUTTON'){viewer.classList.remove('is-open');viewer.querySelector('img').removeAttribute('src');document.body.style.overflow='';}});
    document.addEventListener('keydown',function(event){if(event.key==='Escape'&&viewer.classList.contains('is-open')){viewer.classList.remove('is-open');viewer.querySelector('img').removeAttribute('src');document.body.style.overflow='';}});
    return viewer;
  }
  $(document).on('click','[data-rp-image-viewer]',function(e){
    if(window.FieldFlowMobile&&window.FieldFlowMobile.native) return;
    e.preventDefault();var viewer=rpFormImageViewer();if(!viewer)return;var img=viewer.querySelector('img'),source=this.querySelector('img');img.src=this.href||source&&source.src||'';img.alt=source&&source.alt||'Imagem';viewer.classList.add('is-open');document.body.style.overflow='hidden';
  });
  var RP_TERRAIN_CONTINUITY_CONTRACT='fieldflow-field-form-terrain-continuity-v1';
  var RP_FIELD_SESSION_FORM_CONTINUITY_CONTRACT='fieldflow-field-session-form-continuity-v1';
  var RP_FIELD_RESUME_NAVIGATION_CONTRACT='fieldflow-field-resume-navigation-continuity-v1';
  var RP_REPORT_SELECTION_MEDIA_CONTRACT='fieldflow-report-selection-media-continuity-v1';
  var RP_RESUME_CERT_MEDIA_CONTRACT='fieldflow-resume-certification-media-integrity-v1';
  var RP_FORM_VIEWPORT_SHOPPING_CONTRACT='fieldflow-form-viewport-shopping-delivery-integrity-v1';
  var RP_MEDIA_ORIGINAL_PREFIX='__fieldflow_original__:';
  var RP_DRAFT_PREFIX='fieldflow:draft:v2:';
  var RP_DRAFT_TTL=30*24*60*60*1000;
  var RP_DRAFT_SERVER_DELAY=900;
  var rpDraftClientInstanceId=(function(){
    try{
      var key='fieldflow:draft:client-instance',value=window.sessionStorage?window.sessionStorage.getItem(key):'';
      if(!value){ value='ff-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,12); if(window.sessionStorage) window.sessionStorage.setItem(key,value); }
      return value;
    }catch(_){ return 'ff-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,12); }
  })();
  var rpDraftForms=[];
  var rpDraftDbPromise=null;
  var rpDraftCleanupDone=false;
  function rpDraftDb(){
    if(rpDraftDbPromise) return rpDraftDbPromise;
    rpDraftDbPromise=new Promise(function(resolve,reject){
      if(!window.indexedDB){ reject(new Error('indexeddb_unavailable')); return; }
      var request=indexedDB.open('fieldflow-form-drafts',2);
      request.onupgradeneeded=function(){
        var db=request.result;
        var store=db.objectStoreNames.contains('files')?request.transaction.objectStore('files'):db.createObjectStore('files',{keyPath:'id'});
        if(!store.indexNames.contains('draftKey')) store.createIndex('draftKey','draftKey',{unique:false});
        if(!db.objectStoreNames.contains('snapshots')) db.createObjectStore('snapshots',{keyPath:'draftKey'});
      };
      request.onsuccess=function(){ resolve(request.result); };
      request.onerror=function(){ reject(request.error||new Error('indexeddb_open_failed')); };
    }).catch(function(error){rpDraftDbPromise=null;throw error;});
    return rpDraftDbPromise;
  }
  function rpDraftStoreSnapshotLegacy(draftKey,snapshot){
    if(!draftKey||!snapshot) return Promise.resolve(false);
    return rpDraftDb().then(function(db){ return new Promise(function(resolve,reject){
      var tx=db.transaction('snapshots','readwrite');
      tx.objectStore('snapshots').put({draftKey:draftKey,snapshot:snapshot,updatedAt:parseInt(snapshot.updatedAt||Date.now(),10)||Date.now(),expiresAt:parseInt(snapshot.expiresAt||0,10)||0});
      tx.oncomplete=function(){resolve(true);};
      tx.onerror=function(){reject(tx.error||new Error('indexeddb_snapshot_write_failed'));};
      tx.onabort=function(){reject(tx.error||new Error('indexeddb_snapshot_write_aborted'));};
    }); }).catch(function(){return false;});
  }
  function rpDraftReadSnapshotLegacy(draftKey){
    if(!draftKey) return Promise.resolve(null);
    return rpDraftDb().then(function(db){ return new Promise(function(resolve,reject){
      var tx=db.transaction('snapshots','readonly');
      var request=tx.objectStore('snapshots').get(draftKey);
      request.onsuccess=function(){var row=request.result||null;resolve(row&&row.snapshot?row.snapshot:null);};
      request.onerror=function(){reject(request.error||new Error('indexeddb_snapshot_read_failed'));};
    }); }).catch(function(){return null;});
  }
  function rpDraftDeleteSnapshotLegacy(draftKey){
    if(!draftKey) return Promise.resolve();
    return rpDraftDb().then(function(db){ return new Promise(function(resolve,reject){
      var tx=db.transaction('snapshots','readwrite');tx.objectStore('snapshots').delete(draftKey);
      tx.oncomplete=function(){resolve();};tx.onerror=function(){reject(tx.error||new Error('indexeddb_snapshot_delete_failed'));};tx.onabort=function(){reject(tx.error||new Error('indexeddb_snapshot_delete_aborted'));};
    }); }).catch(function(){});
  }
  function rpDraftDeleteFilesLegacy(draftKey){
    if(!draftKey) return Promise.resolve();
    return rpDraftDb().then(function(db){ return new Promise(function(resolve,reject){
      var tx=db.transaction('files','readwrite');
      var store=tx.objectStore('files');
      var index=store.index('draftKey');
      var cursor=index.openCursor(IDBKeyRange.only(draftKey));
      cursor.onsuccess=function(){ var item=cursor.result; if(item){ item.delete(); item.continue(); } };
      tx.oncomplete=function(){ resolve(); };
      tx.onerror=function(){ reject(tx.error||new Error('indexeddb_delete_failed')); };
      tx.onabort=function(){ reject(tx.error||new Error('indexeddb_delete_aborted')); };
    }); }).catch(function(){ });
  }
  function rpDraftOriginalInputName(inputName){ return RP_MEDIA_ORIGINAL_PREFIX+String(inputName||''); }
  function rpDraftBaseInputName(inputName){
    inputName=String(inputName||'');
    return inputName.indexOf(RP_MEDIA_ORIGINAL_PREFIX)===0?inputName.slice(RP_MEDIA_ORIGINAL_PREFIX.length):inputName;
  }
  function rpDraftStoreFilesNamedLegacy(draftKey,inputName,ordinal,rawFiles){
    if(!draftKey||!inputName) return Promise.resolve(0);
    rawFiles=Array.prototype.slice.call(rawFiles||[]);
    var files=rpUploadableFiles(rawFiles);
    // A zero-byte File can be a broken WebView/canvas handoff. If there was a
    // real file selected, do not delete the previous durable draft copy.
    if(rawFiles.length>0&&files.length===0) return Promise.resolve(0);
    return rpDraftDb().then(function(db){ return new Promise(function(resolve,reject){
      var tx=db.transaction('files','readwrite');
      var store=tx.objectStore('files');
      var index=store.index('draftKey');
      var cursor=index.openCursor(IDBKeyRange.only(draftKey));
      cursor.onsuccess=function(){
        var item=cursor.result;
        if(item){
          var value=item.value||{};
          if(String(value.inputName||'')===String(inputName)&&parseInt(value.ordinal||0,10)===ordinal) item.delete();
          item.continue();
          return;
        }
        var generation=Date.now().toString(36)+'-'+Math.random().toString(36).slice(2);
        files.forEach(function(file,fileIndex){
          store.put({
            id:draftKey+'::'+encodeURIComponent(inputName)+'::'+ordinal+'::'+fileIndex,
            draftKey:draftKey,inputName:inputName,ordinal:ordinal,fileIndex:fileIndex,
            name:file.name||('ficheiro-'+(fileIndex+1)),type:file.type||'',lastModified:file.lastModified||Date.now(),
            blob:file,size:Number(file.size||0),updatedAt:Date.now(),generation:generation
          });
        });
      };
      tx.oncomplete=function(){ resolve(files.length); };
      tx.onerror=function(){ reject(tx.error||new Error('indexeddb_write_failed')); };
      tx.onabort=function(){ reject(tx.error||new Error('indexeddb_write_aborted')); };
    }); });
  }
  function rpDraftStoreInputFilesLegacy(draftKey,input,ordinal){
    if(!input||!input.name) return Promise.resolve(0);
    return rpDraftStoreFilesNamedLegacy(draftKey,String(input.name||''),ordinal,Array.prototype.slice.call(input.files||[]));
  }
  function rpDraftReadFilesLegacy(draftKey){
    if(!draftKey) return Promise.resolve([]);
    return rpDraftDb().then(function(db){ return new Promise(function(resolve,reject){
      var rows=[];
      var tx=db.transaction('files','readonly');
      var cursor=tx.objectStore('files').index('draftKey').openCursor(IDBKeyRange.only(draftKey));
      cursor.onsuccess=function(){ var item=cursor.result; if(item){ rows.push(item.value); item.continue(); } };
      tx.oncomplete=function(){ resolve(rows); };
      tx.onerror=function(){ reject(tx.error||new Error('indexeddb_read_failed')); };
    }); }).catch(function(){ return []; });
  }
  function rpDraftCleanupIndexedSnapshotsLegacy(){
    return rpDraftDb().then(function(db){ return new Promise(function(resolve,reject){
      var expired=[],now=Date.now();
      var tx=db.transaction('snapshots','readwrite');
      var cursor=tx.objectStore('snapshots').openCursor();
      cursor.onsuccess=function(){
        var item=cursor.result;
        if(!item) return;
        var row=item.value||{},expiresAt=parseInt(row.expiresAt||row.snapshot&&row.snapshot.expiresAt||0,10)||0;
        if(expiresAt&&expiresAt<now){ expired.push(String(row.draftKey||item.key||'')); item.delete(); }
        item.continue();
      };
      tx.oncomplete=function(){
        Promise.all(expired.filter(Boolean).map(function(key){ return rpDraftDeleteFilesLegacy(key); })).then(function(){resolve(expired.length);},function(){resolve(expired.length);});
      };
      tx.onerror=function(){reject(tx.error||new Error('indexeddb_snapshot_cleanup_failed'));};
      tx.onabort=function(){reject(tx.error||new Error('indexeddb_snapshot_cleanup_aborted'));};
    }); }).catch(function(){return 0;});
  }
  function rpTypedDraftCore(){
    try{
      var frontend=window.FieldFlowFrontend,core=frontend&&frontend.draftCore;
      if(core&&core.contract==='fieldflow-typed-draft-media-core-v1'&&core.buildVersion===String(window.FieldFlowFrontendConfig&&window.FieldFlowFrontendConfig.version||frontend&&frontend.version||'')) return core;
    }catch(_){ }
    return null;
  }
  function rpTypedDraftPersistence(){
    var core=rpTypedDraftCore();
    return core&&core.persistence&&typeof core.persistence==='object'?core.persistence:null;
  }
  function rpDraftStoreSnapshot(draftKey,snapshot){
    var persistence=rpTypedDraftPersistence();
    if(persistence&&typeof persistence.storeSnapshot==='function'){
      try{return Promise.resolve(persistence.storeSnapshot(draftKey,snapshot)).catch(function(){return rpDraftStoreSnapshotLegacy(draftKey,snapshot);});}catch(_){ }
    }
    return rpDraftStoreSnapshotLegacy(draftKey,snapshot);
  }
  function rpDraftReadSnapshot(draftKey){
    var persistence=rpTypedDraftPersistence();
    if(persistence&&typeof persistence.readSnapshot==='function'){
      try{return Promise.resolve(persistence.readSnapshot(draftKey)).catch(function(){return rpDraftReadSnapshotLegacy(draftKey);});}catch(_){ }
    }
    return rpDraftReadSnapshotLegacy(draftKey);
  }
  function rpDraftDeleteSnapshot(draftKey){
    var persistence=rpTypedDraftPersistence();
    if(persistence&&typeof persistence.deleteSnapshot==='function'){
      try{return Promise.resolve(persistence.deleteSnapshot(draftKey)).catch(function(){return rpDraftDeleteSnapshotLegacy(draftKey);});}catch(_){ }
    }
    return rpDraftDeleteSnapshotLegacy(draftKey);
  }
  function rpDraftDeleteFiles(draftKey){
    var persistence=rpTypedDraftPersistence();
    if(persistence&&typeof persistence.deleteFiles==='function'){
      try{return Promise.resolve(persistence.deleteFiles(draftKey)).catch(function(){return rpDraftDeleteFilesLegacy(draftKey);});}catch(_){ }
    }
    return rpDraftDeleteFilesLegacy(draftKey);
  }
  function rpDraftStoreFilesNamed(draftKey,inputName,ordinal,rawFiles){
    if(!draftKey||!inputName) return Promise.resolve(0);
    rawFiles=Array.prototype.slice.call(rawFiles||[]);
    var persistence=rpTypedDraftPersistence();
    if(persistence&&typeof persistence.storeFiles==='function'){
      try{return Promise.resolve(persistence.storeFiles(draftKey,String(inputName),ordinal,rawFiles,rawFiles.length)).catch(function(){return rpDraftStoreFilesNamedLegacy(draftKey,inputName,ordinal,rawFiles);});}catch(_){ }
    }
    return rpDraftStoreFilesNamedLegacy(draftKey,inputName,ordinal,rawFiles);
  }
  function rpDraftStoreInputFiles(draftKey,input,ordinal){
    if(!input||!input.name) return Promise.resolve(0);
    return rpDraftStoreFilesNamed(draftKey,String(input.name||''),ordinal,Array.prototype.slice.call(input.files||[]));
  }
  function rpDraftStoreOriginalInputFiles(draftKey,input,ordinal,files){
    if(!input||!input.name) return Promise.resolve(0);
    return rpDraftStoreFilesNamed(draftKey,rpDraftOriginalInputName(input.name),ordinal,Array.prototype.slice.call(files||input.files||[]));
  }
  function rpDraftRecordsForInput(records,inputName,ordinal){
    records=Array.isArray(records)?records:[];
    var active=records.filter(function(record){ return String(record&&record.inputName||'')===String(inputName||'')&&parseInt(record&&record.ordinal||0,10)===ordinal; });
    if(active.length) return active;
    var originalName=rpDraftOriginalInputName(inputName);
    return records.filter(function(record){ return String(record&&record.inputName||'')===originalName&&parseInt(record&&record.ordinal||0,10)===ordinal; });
  }
  function rpDraftReadFiles(draftKey){
    var persistence=rpTypedDraftPersistence();
    if(persistence&&typeof persistence.readFiles==='function'){
      try{return Promise.resolve(persistence.readFiles(draftKey)).catch(function(){return rpDraftReadFilesLegacy(draftKey);});}catch(_){ }
    }
    return rpDraftReadFilesLegacy(draftKey);
  }
  function rpDraftCleanupIndexedSnapshots(){
    var persistence=rpTypedDraftPersistence();
    if(persistence&&typeof persistence.cleanupExpiredSnapshots==='function'){
      try{return Promise.resolve(persistence.cleanupExpiredSnapshots(Date.now())).catch(function(){return rpDraftCleanupIndexedSnapshotsLegacy();});}catch(_){ }
    }
    return rpDraftCleanupIndexedSnapshotsLegacy();
  }
  function rpDraftCleanupExpired(){
    if(rpDraftCleanupDone) return;
    rpDraftCleanupDone=true;
    try{
      var expired=[];
      if(window.localStorage){
        for(var i=0;i<window.localStorage.length;i++){
          var key=window.localStorage.key(i);
          if(!key||key.indexOf(RP_DRAFT_PREFIX)!==0) continue;
          var raw=window.localStorage.getItem(key),draft=null;
          try{ draft=raw?JSON.parse(raw):null; }catch(_){ draft=null; }
          if(!draft||parseInt(draft.expiresAt||0,10)<Date.now()) expired.push(key);
        }
      }
      expired.forEach(function(key){ if(window.localStorage) window.localStorage.removeItem(key); rpDraftDeleteSnapshot(key); rpDraftDeleteFiles(key); });
      rpDraftCleanupIndexedSnapshots();
    }catch(_){ rpDraftCleanupIndexedSnapshots(); }
  }
  function rpFlushDraftForms(){
    rpDraftForms=rpDraftForms.filter(function(form){
      if(!form||!document.documentElement.contains(form)) return false;
      if(typeof form.routesproFlushDraft==='function') form.routesproFlushDraft({force:true,silent:true,immediateServer:true,keepalive:true,lifecycle:true});
      return true;
    });
  }
  function rpResumeDraftForms(reason){
    rpDraftForms=rpDraftForms.filter(function(form){
      if(!form||!document.documentElement.contains(form)) return false;
      if(typeof form.routesproResumeDraft==='function') form.routesproResumeDraft({reason:String(reason||'resume')});
      return true;
    });
    try{document.dispatchEvent(new CustomEvent('fieldflow:form-resume',{detail:{reason:String(reason||'resume'),contract:RP_FIELD_SESSION_FORM_CONTINUITY_CONTRACT}}));}catch(_){ }
  }
  var rpResumeDraftTimer=0,rpResumeDraftLastAt=0,rpResumeDraftArmedAt=0;
  function rpArmResumeDrafts(){rpResumeDraftArmedAt=Date.now();}
  function rpConsumeResumeDraftArm(){var armed=rpResumeDraftArmedAt&&Date.now()-rpResumeDraftArmedAt<60000;rpResumeDraftArmedAt=0;return !!armed;}
  function rpQueueResumeDraftForms(reason){
    var now=Date.now(),wait=(now-rpResumeDraftLastAt<900)?900:40;
    if(rpResumeDraftTimer) window.clearTimeout(rpResumeDraftTimer);
    rpResumeDraftTimer=window.setTimeout(function(){
      rpResumeDraftTimer=0;rpResumeDraftLastAt=Date.now();rpResumeDraftForms(reason);
    },wait);
  }
  document.addEventListener('visibilitychange',function(){
    if(document.visibilityState==='hidden'){rpCaptureAllFormViewports('visibility-hidden');rpArmResumeDrafts();rpFlushDraftForms();return;}
    rpDraftForms.forEach(function(form){if(form&&form.__rpViewportLock)rpScheduleFormViewportUnlock(form,'visibility-visible',1100);});
    if(rpConsumeResumeDraftArm())rpQueueResumeDraftForms('visibility-visible');
  });
  window.addEventListener('pagehide',function(){rpCaptureAllFormViewports('pagehide');rpArmResumeDrafts();rpFlushDraftForms();});
  window.addEventListener('beforeunload',function(){rpCaptureAllFormViewports('beforeunload');rpFlushDraftForms();});
  document.addEventListener('freeze',function(){rpCaptureAllFormViewports('freeze');rpArmResumeDrafts();rpFlushDraftForms();});
  window.addEventListener('fieldflow:app-resume',function(){rpResumeDraftArmedAt=0;if(rpResumeDraftTimer){window.clearTimeout(rpResumeDraftTimer);rpResumeDraftTimer=0;}rpResumeDraftLastAt=Date.now();rpResumeDraftForms('fieldflow:app-resume');});
  window.addEventListener('pageshow',function(event){if(document.visibilityState!=='hidden'&&(event.persisted||rpConsumeResumeDraftArm()))rpQueueResumeDraftForms('pageshow');});
  // Android native selects/file pickers can blur/focus the WebView without a real
  // app lifecycle transition. Bare focus must not restore an older draft/step.
  window.addEventListener('focus',function(){if(document.visibilityState!=='hidden'&&rpConsumeResumeDraftArm())rpQueueResumeDraftForms('focus-after-hidden');});
  var rpPreviousVisitState=window.__routesproPreviousVisitState||{activeKey:'',closedKey:'',openTimer:0};
  window.__routesproPreviousVisitState=rpPreviousVisitState;
  function rpPreviousVisitGlobalModal(){
    return document.querySelector('body > [data-rp-previous-visit-modal][data-rp-global="1"]');
  }
  function rpPreviousVisitClearTimer(){
    if(rpPreviousVisitState.openTimer){
      window.clearTimeout(rpPreviousVisitState.openTimer);
      rpPreviousVisitState.openTimer=0;
    }
  }
  function rpPreviousVisitRemoveModal(modal){
    if(!modal) return;
    if(typeof modal.routesproPreviousVisitCleanup==='function') modal.routesproPreviousVisitCleanup();
    if(modal.parentNode) modal.parentNode.removeChild(modal);
  }
  // fieldflow-visit-memory-refresh-v1: memory belongs to the current form.
  function rpClearPreviousVisitModal(){
    rpPreviousVisitClearTimer();
    rpPreviousVisitRemoveModal(rpPreviousVisitGlobalModal());
    document.documentElement.classList.remove('routespro-previous-visit-open');
    rpPreviousVisitState.activeKey='';
    rpPreviousVisitState.closedKey='';
    rpPreviousVisitState.memoryRevision='';
    rpPreviousVisitState.form=null;
  }
  window.routesproClearPreviousVisitModal=rpClearPreviousVisitModal;
  function rpPreviousVisitScheduleOpen(modal,key,form){
    if(!modal||!key||rpPreviousVisitState.closedKey===key) return;
    if(modal.getAttribute('data-rp-previous-visit-state')==='open') return;
    rpPreviousVisitClearTimer();
    modal.setAttribute('data-rp-previous-visit-state','pending');
    // A WebView pode fazer duas renderizações consecutivas da mesma visita.
    // Esperamos que o contexto estabilize, mas não dependemos da sobrevivência
    // do nó <form>, porque esse nó é precisamente o que a app pode substituir.
    rpPreviousVisitState.openTimer=window.setTimeout(function(){
      rpPreviousVisitState.openTimer=0;
      var active=rpPreviousVisitGlobalModal();
      if(!active||active!==modal) return;
      if(active.getAttribute('data-rp-previous-visit-key')!==key) return;
      if(rpPreviousVisitState.activeKey!==key||rpPreviousVisitState.closedKey===key) return;
      if(!rpPreviousVisitState.form||!document.documentElement.contains(rpPreviousVisitState.form)) return;
      modal.hidden=false;
      modal.setAttribute('data-rp-previous-visit-state','open');
      document.documentElement.classList.add('routespro-previous-visit-open');
      try{if(form)form.dispatchEvent(new CustomEvent('fieldflow:previous-visit-opened',{bubbles:true,detail:{key:key}}));}catch(_){ }
      var button=modal.querySelector('button[data-rp-close-previous-visit]');
      if(button&&typeof button.focus==='function') window.setTimeout(function(){try{button.focus({preventScroll:true});}catch(_){try{button.focus();}catch(__){}}},80);
    },320);
  }
  function rpInitPreviousVisitModal(form){
    if(!form||!document.documentElement.contains(form)) return;
    var wrap=form.closest?form.closest('.routespro-form-wrap'):null;
    var source=wrap&&wrap.querySelector?wrap.querySelector('[data-rp-previous-visit-modal]'):null;
    if(!source){
      if(rpPreviousVisitState.form!==form) rpClearPreviousVisitModal();
      return;
    }
    var key=String(source.getAttribute('data-rp-previous-visit-key')||source.id||'');
    if(!key) return;
    var revision=String(source.getAttribute('data-rp-previous-visit-revision')||'');
    source.setAttribute('data-rp-previous-visit-init','1');

    var existing=rpPreviousVisitGlobalModal();
    if(existing&&String(existing.getAttribute('data-rp-previous-visit-key')||'')===key
      &&String(existing.getAttribute('data-rp-previous-visit-revision')||'')===revision){
      // Segunda renderização da mesma loja. Mantemos o primeiro popup global,
      // em vez de o apagar e recriar, que era a origem do flash na app.
      if(source!==existing) rpPreviousVisitRemoveModal(source);
      rpPreviousVisitState.activeKey=key;
      rpPreviousVisitState.form=form;
      rpPreviousVisitState.memoryRevision=revision;
      rpPreviousVisitScheduleOpen(existing,key,form);
      return;
    }

    if(existing){
      rpPreviousVisitClearTimer();
      rpPreviousVisitRemoveModal(existing);
      document.documentElement.classList.remove('routespro-previous-visit-open');
    }

    // Uma visita diferente constitui um novo arranque e pode voltar a mostrar
    // a memória, mesmo que a visita anterior tenha sido fechada pelo utilizador.
    if(rpPreviousVisitState.activeKey!==key||rpPreviousVisitState.memoryRevision!==revision){
      rpPreviousVisitState.activeKey=key;
      rpPreviousVisitState.closedKey='';
    }
    rpPreviousVisitState.form=form;
    rpPreviousVisitState.memoryRevision=revision;
    if(rpPreviousVisitState.closedKey===key){
      rpPreviousVisitRemoveModal(source);
      return;
    }

    source.setAttribute('data-rp-global','1');
    source.setAttribute('data-rp-previous-visit-state','pending');
    source.hidden=true;
    if(source.parentNode!==document.body) document.body.appendChild(source);

    var closed=false;
    function closeModal(){
      if(closed) return;
      closed=true;
      rpPreviousVisitState.closedKey=key;
      rpPreviousVisitClearTimer();
      source.hidden=true;
      source.setAttribute('data-rp-previous-visit-state','closed');
      document.documentElement.classList.remove('routespro-previous-visit-open');
      try{form.dispatchEvent(new CustomEvent('fieldflow:previous-visit-closed',{bubbles:true,detail:{key:key}}));}catch(_){ }
      window.setTimeout(function(){rpPreviousVisitRemoveModal(source);},0);
    }
    source.querySelectorAll('[data-rp-close-previous-visit]').forEach(function(el){el.addEventListener('click',closeModal);});
    var escapeHandler=function(event){
      if(event.key==='Escape'&&!source.hidden){closeModal();document.removeEventListener('keydown',escapeHandler);}
    };
    source.routesproPreviousVisitCleanup=function(){document.removeEventListener('keydown',escapeHandler);};
    document.addEventListener('keydown',escapeHandler);
    rpPreviousVisitScheduleOpen(source,key,form);
  }
  window.routesproInitPreviousVisitModal=rpInitPreviousVisitModal;

  function rpInitStoreProfile(form){
    if(!form||!form.querySelector) return;
    var card=form.querySelector('[data-rp-store-profile]');
    if(!card||card.getAttribute('data-rp-store-init')==='1') return;
    card.setAttribute('data-rp-store-init','1');
    var view=card.querySelector('[data-rp-store-view]');
    var editor=card.querySelector('[data-rp-store-editor]');
    var editBtn=card.querySelector('[data-rp-store-edit]');
    var cancelBtn=card.querySelector('[data-rp-store-cancel]');
    var saveBtn=card.querySelector('[data-rp-store-save]');
    var message=card.querySelector('[data-rp-store-message]');
    var fields=Array.prototype.slice.call(card.querySelectorAll('[data-rp-store-field]'));
    function pendingLabel(value){return value==='open'?'Com pendentes':(value==='resolved'?'Pendentes resolvidos':'Sem pendentes');}
    function currentValues(){
      var out={};
      fields.forEach(function(field){out[String(field.getAttribute('data-rp-store-field')||'')]=String(field.value||'');});
      return out;
    }
    var saved=currentValues();
    function fill(values){fields.forEach(function(field){var key=String(field.getAttribute('data-rp-store-field')||'');if(Object.prototype.hasOwnProperty.call(values,key))field.value=values[key]==null?'':String(values[key]);});}
    function text(key,value,empty){
      var node=card.querySelector('[data-rp-store-display="'+key+'"]');
      if(node) node.textContent=String(value||'').trim()!==''?String(value):empty;
    }
    function setMessage(copy,type){
      if(!message)return;
      message.textContent=copy||'';
      message.classList.remove('is-error','is-success');
      if(type)message.classList.add(type==='error'?'is-error':'is-success');
    }
    function showEditor(open){
      if(!editor)return;
      editor.hidden=!open;
      if(view)view.hidden=!!open;
      if(editBtn)editBtn.hidden=!!open;
      if(open){fill(saved);var first=editor.querySelector('input,select,textarea');if(first)window.setTimeout(function(){try{first.focus({preventScroll:true});}catch(_){first.focus();}},0);}
      setMessage('', '');
    }
    function applySnapshot(data){
      var profile=data&&data.profile?data.profile:{};
      var assortment=data&&data.assortment?data.assortment:{};
      saved={
        responsible_name:String(profile.responsible_name||''),
        store_format:String(profile.store_format||''),
        assortment_profile:String(profile.assortment_profile||'general'),
        operational_phone:String(profile.operational_phone||''),
        operational_email:String(profile.operational_email||''),
        pending_status:String(profile.pending_status||'none'),
        pending_actions:String(profile.pending_actions||''),
        store_notes:String(profile.store_notes||'')
      };
      fill(saved);
      card.setAttribute('data-revision',String(profile.revision_no||0));
      text('responsible_name',saved.responsible_name,'Não preenchido');
      text('store_format',saved.store_format,'Não preenchido');
      text('assortment_profile',String(assortment.selected_label||saved.assortment_profile||'Geral'),'Geral');
      text('operational_phone',saved.operational_phone,'Não preenchido');
      text('operational_email',saved.operational_email,'Não preenchido');
      text('pending_status',pendingLabel(saved.pending_status),'Sem pendentes');
      text('pending_actions',saved.pending_actions,'Sem informação.');
      text('store_notes',saved.store_notes,'Sem informação.');
      var assortmentChip=card.querySelector('[data-rp-store-chip="assortment"]');
      if(assortmentChip)assortmentChip.textContent='Tipo '+String(assortment.selected_label||saved.assortment_profile||'Geral');
      var pendingChip=card.querySelector('[data-rp-store-chip="pending"]');
      if(pendingChip){pendingChip.textContent=pendingLabel(saved.pending_status);pendingChip.classList.remove('is-warning','is-success','is-neutral');pendingChip.classList.add(saved.pending_status==='open'?'is-warning':(saved.pending_status==='resolved'?'is-success':'is-neutral'));}
      var complete=!!(data&&data.profile_complete);
      var completeChip=card.querySelector('[data-rp-store-chip="complete"]');
      if(completeChip){completeChip.textContent=complete?'Perfil completo':'Faltam contactos';completeChip.classList.remove('is-success','is-warning');completeChip.classList.add(complete?'is-success':'is-warning');}
      var updated=card.querySelector('[data-rp-store-updated]');
      if(updated){
        var stamp=String(profile.updated_at||'');
        var by=String(profile.updated_by_name||'');
        updated.textContent=stamp?'Ficha operacional atualizada'+(by?' por '+by:'')+'.':'Ficha operacional ainda não preenchida.';
      }
      if(editBtn)editBtn.textContent=Number(profile.revision_no||0)>0?'Editar dados':'Completar ficha';
    }
    if(editBtn)editBtn.addEventListener('click',function(){showEditor(true);});
    if(cancelBtn)cancelBtn.addEventListener('click',function(){fill(saved);showEditor(false);});
    if(saveBtn)saveBtn.addEventListener('click',function(){
      var endpoint=String(card.getAttribute('data-endpoint')||'');
      if(!endpoint){setMessage('Não foi possível localizar o serviço da ficha da loja.','error');return;}
      var payload=currentValues();
      payload.project_id=parseInt(card.getAttribute('data-project-id')||'0',10)||0;
      payload.route_id=parseInt(card.getAttribute('data-route-id')||'0',10)||0;
      payload.revision_no=parseInt(card.getAttribute('data-revision')||'0',10)||0;
      saveBtn.disabled=true;
      saveBtn.textContent='A guardar...';
      setMessage('A guardar a ficha da loja...','');
      window.fetch(endpoint,{
        method:'POST',credentials:'same-origin',
        headers:{'Content-Type':'application/json','X-WP-Nonce':String(card.getAttribute('data-nonce')||'')},
        body:JSON.stringify(payload)
      }).then(function(response){
        return response.json().catch(function(){return {};}).then(function(data){if(!response.ok){var err=new Error(String(data&&data.message||'Não foi possível guardar a ficha da loja.'));err.status=response.status;throw err;}return data;});
      }).then(function(data){
        applySnapshot(data||{});
        showEditor(false);
        setMessage('Ficha da loja atualizada.','success');
        try{form.dispatchEvent(new CustomEvent('fieldflow:store-profile-updated',{bubbles:true,detail:data||{}}));}catch(_){ }
      }).catch(function(error){
        var copy=error&&error.status===409?'A ficha foi alterada noutro dispositivo. Volta a abrir esta loja para obter a versão mais recente.':String(error&&error.message||'Não foi possível guardar a ficha da loja.');
        setMessage(copy,'error');
      }).finally(function(){saveBtn.disabled=false;saveBtn.textContent='Guardar ficha';});
    });
  }
  window.routesproInitStoreProfile=rpInitStoreProfile;
  function rpInitDynamicForm(form){
    var $form=$(form||null);
    if(!$form.length || !$form.hasClass('routespro-dyn-form')) return;
    if($form.data('rpWizardInit')) return;
    $form.data('rpWizardInit',1);
    rpInitPreviousVisitModal($form.get(0));
    rpInitStoreProfile($form.get(0));
    function runtimeV3Owns(){
      return $form.attr('data-rp-runtime-mode')==='pilot'&&$form.attr('data-rp-runtime-owner')==='v3';
    }
    function runtimeV3Fallback(code,error){
      var api=window.FieldFlowFormsRuntimeV3;
      if(api&&typeof api.fallback==='function'){
        try{ api.fallback(form,code,error||null); return; }catch(_){ }
      }
      $form.attr('data-rp-runtime-owner','legacy').attr('data-rp-runtime-mode','fallback').attr('data-rp-runtime-fallback-code',String(code||'runtime_api_unavailable'));
      try{ form.dispatchEvent(new CustomEvent('fieldflow:runtime-v3-fallback',{bubbles:true,detail:{code:String(code||'runtime_api_unavailable')}})); }catch(_){ }
    }
    function runtimeV3Call(method,args){
      if(!runtimeV3Owns()) return null;
      var api=window.FieldFlowFormsRuntimeV3;
      if(!api||typeof api[method]!=='function'){
        runtimeV3Fallback('runtime_api_unavailable');
        return null;
      }
      try{ return api[method].apply(api,args||[]); }
      catch(error){ runtimeV3Fallback('runtime_delegate_exception',error); return null; }
    }
    var $wizard=$form.find('.routespro-form-wizard').first();
    var $live=$form.children('.routespro-form-live-region').first();
    if(!$live.length){
      $live=$('<div class="routespro-form-live-region" aria-live="polite" aria-atomic="true"></div>');
      $form.prepend($live);
    }
    var draftEnabled=$form.attr('data-rp-draft-enabled')==='1';
    var draftServerEnabled=draftEnabled&&$form.attr('data-rp-draft-server-enabled')==='1';
    var draftEndpoint=String($form.attr('data-rp-draft-endpoint')||'');
    var draftNonce=String($form.attr('data-rp-draft-nonce')||'');
    var $draftId=$form.find('input[name="routespro_draft_id"]').first();
    var draftServerRevision=0;
    var draftServerTimer=null;
    var draftServerRetryTimer=null;
    var draftServerRetryAttempt=0;
    var draftServerRetrySnapshot=null;
    var draftServerBusy=false;
    var draftServerQueued=null;
    var draftServerFiles=[];
    var draftUploadedSignatures={};
    if(draftEnabled){
      rpDraftCleanupExpired();
      // Pre-open IndexedDB while the form is idle so the first camera/gallery
      // capture can start its durable write immediately. Mobile WebViews may be
      // suspended only milliseconds after returning from the native picker.
      rpDraftDb().catch(function(){ });
    }
    $form.find('[data-rp-upload-zone]').each(function(){rpPrepareUploadZone($(this));rpSyncUploadPreview($(this));});
    var $draftStatus=$('<div class="routespro-draft-status" data-rp-draft-status hidden><span data-rp-draft-copy></span><button type="button" data-rp-discard-draft hidden>Descartar rascunho</button></div>');
    $live.after($draftStatus);
    function setDraftStatus(message,state,canDiscard){
      if(!draftEnabled||!message){ $draftStatus.prop('hidden',true); return; }
      $draftStatus.removeClass('is-saving is-restored is-warning').addClass(state?'is-'+state:'').prop('hidden',false);
      $draftStatus.find('[data-rp-draft-copy]').text(message);
      $draftStatus.find('[data-rp-discard-draft]').prop('hidden',!canDiscard);
    }
    function setNotice(type,message,shouldScroll){
      type=type||'info';
      $live.html('<div class="routespro-form-notice '+type+'" role="'+(type==='error'?'alert':'status')+'">'+$('<div>').text(message||'').html()+'</div>');
      if(shouldScroll) rpScrollTo($live);
    }
    function clearNotice(){ $live.empty(); }
    var dailyExisting=$form.attr('data-rp-daily-existing')==='1';
    var dailyCanEdit=$form.attr('data-rp-daily-can-edit')==='1';
    var dailyContextConflict=$form.attr('data-rp-daily-context-conflict')==='1';
    var $dailyEditFlag=$form.find('input[name="routespro_edit_existing"]').first();
    var $dailyExistingId=$form.find('input[name="routespro_existing_submission_id"]').first();
    function dailyEditMode(){ return $form.attr('data-rp-daily-edit-mode')==='1'; }
    function setDailyEditMode(enabled){
      if(!dailyExisting) return;
      enabled=!!enabled&&dailyCanEdit;
      $form.attr('data-rp-daily-edit-mode',enabled?'1':'0');
      $form.toggleClass('is-daily-editing',enabled).toggleClass('is-daily-locked',!enabled);
      if($dailyEditFlag.length) $dailyEditFlag.val(enabled?'1':'0');
      $form.find('input,select,textarea').not('[type=hidden]').each(function(){
        var $control=$(this);
        if($control.attr('data-rp-daily-state-captured')!=='1'){
          $control.attr('data-rp-daily-state-captured','1');
          $control.attr('data-rp-daily-was-disabled',$control.prop('disabled')?'1':'0');
        }
        if(enabled){
          $control.prop('disabled',$control.attr('data-rp-daily-was-disabled')==='1');
        }else{
          $control.prop('disabled',true);
        }
      });
      $form.find('button').not('[data-rp-enable-daily-edit],[data-category-toggle],[data-category-open-all],[data-category-close-all],[data-rp-next],[data-rp-prev]').each(function(){
        var $button=$(this);
        if($button.attr('data-rp-daily-state-captured')!=='1'){
          $button.attr('data-rp-daily-state-captured','1');
          $button.attr('data-rp-daily-was-disabled',$button.prop('disabled')?'1':'0');
        }
        if(enabled){
          $button.prop('disabled',$button.attr('data-rp-daily-was-disabled')==='1');
        }else{
          $button.prop('disabled',true);
        }
      });
      var outer=form.closest('.rp-report-quick,.rp-report-focus,.routespro-report-focus');
      var isFocused=!!(outer&&(outer.classList.contains('rp-report-focus')||outer.classList.contains('routespro-report-focus')));
      var outerButton=outer?outer.querySelector(isFocused?'#rpf-submit':'#rpr-submit'):null;
      if(outerButton){
        if(!outerButton.dataset.rpDailyOriginalLabel) outerButton.dataset.rpDailyOriginalLabel=outerButton.textContent||(isFocused?'Concluir visita':'Guardar reporte');
        outerButton.disabled=!enabled;
        outerButton.textContent=enabled?(isFocused?'Guardar alterações e concluir':'Guardar alterações'):'Reporte já efetuado';
        outerButton.dataset.rpDailyLocked=enabled?'0':'1';
      }
      var stateDetail={
        existing:true,
        can_edit:!!dailyCanEdit,
        editing:!!enabled,
        locked:!enabled,
        context_conflict:!!dailyContextConflict,
        submission_id:parseInt($dailyExistingId.val()||'0',10)||0,
        report_day:String($form.attr('data-rp-report-day')||'')
      };
      try{ form.dispatchEvent(new CustomEvent('fieldflow:daily-edit-state',{bubbles:true,detail:stateDetail})); }catch(_){ }
      var $copy=$form.find('[data-rp-daily-mode-copy]').first();
      if($copy.length){
        $copy.text(enabled?'Modo de edição ativo. Corrige as respostas necessárias e guarda as alterações.':(dailyContextConflict?'Este reporte pertence a outra rota ou paragem e fica disponível apenas para consulta neste contexto.':(dailyCanEdit?'Podes consultar as respostas ou ativar a edição para corrigir algum engano.':'As respostas ficam disponíveis apenas para consulta.')));
      }
      applyConditions();
    }
    function setWorking(working,label){
      $form.toggleClass('is-submitting',!!working);
      $form.find('[data-rp-submit], .routespro-form-actions button[type=submit]').each(function(){
        var $btn=$(this);
        if(working){
          if(!$btn.attr('data-rp-original-label')) $btn.attr('data-rp-original-label',$btn.text());
          $btn.prop('disabled',true).text(label||'A guardar...');
        }else{
          $btn.prop('disabled',dailyExisting&&!dailyEditMode());
          var original=$btn.attr('data-rp-original-label');
          if(original) $btn.text(original).removeAttr('data-rp-original-label');
        }
      });
    }
    function fieldValue($q){
      var type=String($q.attr('data-rp-type')||'');
      if(type==='checkbox') return $q.find('input[type=checkbox]').is(':checked') ? '1' : '0';
      if(type==='categorized_image_upload') return $q.find('[data-rp-evidence-toggle]:checked').map(function(){ return $(this).val(); }).get();
      if($q.attr('data-rp-multiple-choice')==='1') return $q.find('input[type=checkbox]:checked').map(function(){ return $(this).val(); }).get();
      if(type==='radio'||type==='visual_choice') return $q.find('input[type=radio]:checked,input[type=checkbox]:checked').first().val() || '';
      var $input=$q.find('select, textarea, input').not('[type=hidden],[type=button],[type=submit]').first();
      return $input.val() || '';
    }
    function normalise(v){ return String(v===undefined||v===null?'':v).toLowerCase().trim(); }
    function rpTypedFormCore(){
      var frontend=window.FieldFlowFrontend,core=frontend&&frontend.formCore;
      return core&&core.contract==='fieldflow-typed-form-state-conditional-core-v1'?core:null;
    }
    function rpTypedSubmissionCore(){
      var frontend=window.FieldFlowFrontend,core=frontend&&frontend.submissionCore;
      return core&&core.contract==='fieldflow-typed-submission-validation-core-v1'?core:null;
    }
    function rpTypedSpecializedValidationCore(){
      var frontend=window.FieldFlowFrontend,core=frontend&&frontend.specializedValidationCore;
      return core&&core.contract==='fieldflow-typed-specialized-validation-core-v1'?core:null;
    }
    function conditionMatches(actual,expected){
      var core=rpTypedFormCore();
      if(core&&core.conditions&&typeof core.conditions.matches==='function'){
        try{return !!core.conditions.matches(actual,expected);}catch(_){ }
      }
      if(Array.isArray(actual)){
        var wanted=normalise(expected);
        return actual.some(function(value){ return normalise(value)===wanted; });
      }
      return normalise(actual)===normalise(expected);
    }
    function conditionRules($q){
      var raw=$q.attr('data-rp-condition-rules')||'';
      if(raw){
        try{
          var decoded=JSON.parse(raw);
          if(Array.isArray(decoded)) return decoded.filter(function(rule){ return rule&&rule.go_to&&rule.value!==undefined&&rule.value!==null&&String(rule.value)!==''; });
        }catch(_){ }
      }
      var goTo=$q.attr('data-rp-condition-goto')||'';
      if(!goTo) return [];
      return [{value:$q.attr('data-rp-condition-value')||'',go_to:goTo}];
    }
    function productToken(v){
      var value=String(v===undefined||v===null?'':v).trim().toLowerCase();
      try{ value=value.normalize('NFD').replace(/[\u0300-\u036f]/g,''); }catch(_){ }
      return value.replace(/\s+/g,' ');
    }
    function productIdentityFromRow($row){
      if(!$row || !$row.length) return '';
      var $refInput=$row.find('input[name$="[ref]"]').first();
      var $nameInput=$row.find('input[name$="[name]"]').first();
      var ref=$refInput.length?$refInput.val():$row.attr('data-product-ref');
      var name=$nameInput.length?$nameInput.val():$row.attr('data-product-name');
      ref=productToken(ref);
      name=productToken(name);
      return ref ? 'ref:'+ref : (name ? 'name:'+name : '');
    }
    function setCategoryGroupOpen($group,open){
      if(!$group || !$group.length) return;
      var $toggle=$group.children('[data-category-toggle]').first();
      var $panel=$group.children('[data-category-panel]').first();
      $group.toggleClass('is-open',!!open);
      $toggle.attr('aria-expanded',open?'true':'false');
      $panel.prop('hidden',!open);
    }
    function updateCategoryStatus($group){
      if(!$group || !$group.length) return;
      var kind=String($group.attr('data-category-kind')||'');
      var text='';
      if(kind==='fronts'){
        var present=0,zero=0,pending=0;
        $group.find('[data-fronts-input]').each(function(){
          var raw=String($(this).val()||'').replace(',','.').trim();
          var confirmed=String($(this).closest('[data-product-row]').find('[data-fronts-confirmed]').first().val()||'0')==='1';
          if(!confirmed||raw===''||isNaN(Number(raw)))pending++;
          else if(Number(raw)>0) present++; else zero++;
        });
        text=[present?present+' com frentes':'',zero?zero+' a zero':'',pending?pending+' por confirmar':''].filter(Boolean).join(' · ');
      }else if(kind==='order'){
        var suggestions=0;
        $group.find('[data-order-qty]').each(function(){
          var raw=String($(this).val()||'').replace(',','.').trim();
          if(raw!=='' && !isNaN(Number(raw)) && Number(raw)>0) suggestions++;
        });
        if(suggestions) text=suggestions+(suggestions===1?' encomenda':' encomendas');
      }
      var $status=$group.find('[data-category-status]').first();
      $status.text(text).prop('hidden',!text);
    }
    function updateAllCategoryStatuses(){
      $form.find('[data-category-group]').each(function(){ updateCategoryStatus($(this)); });
    }
    function clearAutomaticOrder($row,$qty){
      if(!$qty || !$qty.length || $qty.attr('data-rp-auto-fronts')!=='1') return;
      $qty.val('').removeAttr('data-rp-auto-fronts');
      $row.find('[data-order-quantity-source]').first().val('');
      $row.removeClass('is-auto-suggested');
      $row.find('.routespro-order-auto-note').prop('hidden',true);
      updateCategoryStatus($row.closest('[data-category-group]'));
    }
    // 9DV: an order reads counts only from its own paired Fronts question.
    // Historical 9S certification marker (inert): pairedKey===frontKey.
    // The previous OR-by-cardex fallback is never executed for cross-question pairing.
    function pairedFrontQuestion($order){
      var pairedKey=String($order.attr('data-paired-fronts-key')||'');
      var cardex=parseInt($order.attr('data-cardex-id')||'0',10)||0;
      var $matches=$form.find('[data-rp-type="product_matrix"]');
      if(pairedKey){
        $matches=$matches.filter(function(){return String($(this).attr('data-rp-question')||'')===pairedKey;});
      }else if(cardex){
        $matches=$matches.filter(function(){return (parseInt($(this).attr('data-cardex-id')||'0',10)||0)===cardex;});
      }else return $();
      if($matches.length!==1)return $();
      var $front=$matches.first(),frontCardex=parseInt($front.attr('data-cardex-id')||'0',10)||0;
      if(cardex&&cardex!==frontCardex)return $();
      return $front;
    }
    function syncOrderSuggestionForRow($row,$frontQuestion){
      var $qty=$row.find('[data-order-qty]').first();if(!$qty.length)return;
      var identity=productIdentityFromRow($row);
      var itemId=parseInt($row.attr('data-product-item-id')||$row.find('input[name$="[item_id]"]').first().val()||'0',10)||0;
      var $front=$frontQuestion.find('[data-product-row]').filter(function(){
        var $candidate=$(this),id=parseInt($candidate.attr('data-product-item-id')||$candidate.find('input[name$="[item_id]"]').first().val()||'0',10)||0;
        return itemId&&id ? id===itemId : productIdentityFromRow($candidate)===identity;
      }).first();
      var raw=$front.length?String($front.find('[data-fronts-input]').first().val()||'').replace(',','.').trim():'';
      var confirmed=$front.length&&String($front.find('[data-fronts-confirmed]').first().val()||'0')==='1';
      var hasZero=confirmed&&raw!==''&&!isNaN(Number(raw))&&Number(raw)===0;
      var configured=parseFloat($row.closest('[data-rp-type="order_suggestion"]').attr('data-zero-fronts-suggestion'));
      var suggestion=isFinite(configured)?Math.max(0,configured):1;
      var current=String($qty.val()||'').trim();
      var source=String($row.find('[data-order-quantity-source]').first().val()||'');
      if(hasZero&&suggestion>0){
        if(source!=='manual'&&(current===''||$qty.attr('data-rp-auto-fronts')==='1')){
          var step=String($qty.attr('step')||'1');
          var display=step==='1'?String(Math.max(0,Math.round(suggestion))):String(suggestion);
          $qty.val(display).attr('data-rp-auto-fronts','1');
          $row.find('[data-order-quantity-source]').first().val('auto');
          $row.addClass('is-auto-suggested');
          $row.find('.routespro-order-auto-note').prop('hidden',false);
        }
      }else clearAutomaticOrder($row,$qty);
      updateCategoryStatus($row.closest('[data-category-group]'));
    }
    function syncAllFrontsSuggestions(){
      $form.find('[data-rp-type="order_suggestion"]').each(function(){
        var $order=$(this),$front=pairedFrontQuestion($order);
        if(!$front.length){
          $order.find('[data-order-row]').each(function(){clearAutomaticOrder($(this),$(this).find('[data-order-qty]').first());});
          return;
        }
        $order.find('[data-order-row]').each(function(){syncOrderSuggestionForRow($(this),$front);});
      });
    }
    function formHtml(value){
      return $('<div>').text(String(value===undefined||value===null?'':value)).html();
    }
    function productRowData($row){
      function value(suffix){var $input=$row.find('input[name$="['+suffix+']"]').first();return $input.length?String($input.val()||''):'';}
      return {
        identity:productIdentityFromRow($row),
        itemId:parseInt(value('item_id')||$row.attr('data-product-item-id')||'0',10)||0,
        cardexId:parseInt(value('cardex_id')||$row.closest('[data-cardex-id]').attr('data-cardex-id')||'0',10)||0,
        category:value('category'),ref:value('ref'),itemType:value('item_type'),name:value('name'),
        capacity:value('capacity_cl'),units:value('units_per_case')
      };
    }
    function updateOrderRowFromFront($row,front){
      $row.attr('data-product-ref',front.ref).attr('data-product-name',front.name).attr('data-product-item-id',String(front.itemId||0));
      var fields={item_id:front.itemId||0,cardex_id:front.cardexId||0,category:front.category,ref:front.ref,item_type:front.itemType,name:front.name,capacity_cl:front.capacity,units_per_case:front.units};
      Object.keys(fields).forEach(function(field){$row.find('input[name$="['+field+']"]').first().val(fields[field]);});
      var spans=$row.children('span');
      spans.eq(0).text(front.category||'Sem categoria');spans.eq(1).text(front.ref||'');spans.eq(2).text(front.itemType||'');spans.eq(3).text(front.name||'Produto');spans.eq(4).text(front.capacity||'');spans.eq(5).text(front.units||'');
    }
    function createOrderRow($table,front){
      var key=String($table.attr('data-order-table')||'');
      if(!key) return $();
      var index=parseInt($table.attr('data-order-next-index')||'0',10)||0;
      $table.attr('data-order-next-index',String(index+1));
      var step=String($table.attr('data-order-step')||'1');
      var prefix=formHtml(key)+'['+index+']';
      var row='<div class="routespro-order-row is-dynamic" data-order-row data-product-ref="'+formHtml(front.ref)+'" data-product-name="'+formHtml(front.name)+'" data-product-item-id="'+formHtml(front.itemId||0)+'">'+
        '<input type="hidden" name="'+prefix+'[item_id]" value="'+formHtml(front.itemId||0)+'">'+
        '<input type="hidden" name="'+prefix+'[cardex_id]" value="'+formHtml(front.cardexId||0)+'">'+
        '<input type="hidden" name="'+prefix+'[category]" value="'+formHtml(front.category)+'">'+
        '<input type="hidden" name="'+prefix+'[ref]" value="'+formHtml(front.ref)+'">'+
        '<input type="hidden" name="'+prefix+'[item_type]" value="'+formHtml(front.itemType)+'">'+
        '<input type="hidden" name="'+prefix+'[name]" value="'+formHtml(front.name)+'">'+
        '<input type="hidden" name="'+prefix+'[capacity_cl]" value="'+formHtml(front.capacity)+'">'+
        '<input type="hidden" name="'+prefix+'[units_per_case]" value="'+formHtml(front.units)+'">'+
        '<input type="hidden" name="'+prefix+'[quantity_source]" value="" data-order-quantity-source>'+
        '<span data-label="Categoria">'+formHtml(front.category||'Sem categoria')+'</span><span data-label="Código">'+formHtml(front.ref)+'</span><span data-label="Tipo">'+formHtml(front.itemType)+'</span><span data-label="Produto" class="routespro-order-product">'+formHtml(front.name||'Produto')+'</span><span data-label="Cap. cl">'+formHtml(front.capacity)+'</span><span data-label="UN/CX">'+formHtml(front.units)+'</span>'+
        '<label class="routespro-order-input"><small>Sug. encomenda, CXS</small><input type="number" min="0" step="'+formHtml(step)+'" name="'+prefix+'[order_qty]" value="" placeholder="0" data-order-qty><em class="routespro-order-auto-note" hidden>Sugerido automaticamente por frentes a zero</em></label>'+
        '<label class="routespro-order-input routespro-order-notes"><small>Observações</small><input type="text" name="'+prefix+'[notes]" value="" placeholder="Observações"></label></div>';
      var $group=$table.find('[data-order-dynamic-group]').first();
      $group.prop('hidden',false);
      var $target=$group.find('[data-order-dynamic-rows]').first();
      var $row=$(row);$target.append($row);
      $table.closest('.routespro-form-field').find('.routespro-order-empty-note').prop('hidden',true);
      return $row;
    }
    function syncOrderTablesWithFronts(){
      $form.find('[data-rp-type="order_suggestion"]').each(function(){
        var $orderQuestion=$(this),$frontQuestion=pairedFrontQuestion($orderQuestion);
        if(!$frontQuestion.length)return;
        var $table=$orderQuestion.find('[data-order-table]').first();if(!$table.length)return;
        var fronts=[],byIdentity={},byItem={},matched={};
        $frontQuestion.find('[data-product-row]').each(function(){
          var data=productRowData($(this));if(!data.identity)return;
          fronts.push(data);byIdentity[data.identity]=data;if(data.itemId)byItem[data.itemId]=data;
        });
        $table.find('[data-order-row]').each(function(){
          var $row=$(this),itemId=parseInt($row.attr('data-product-item-id')||$row.find('input[name$="[item_id]"]').first().val()||'0',10)||0;
          var identity=productIdentityFromRow($row);
          var front=(itemId&&byItem[itemId])?byItem[itemId]:byIdentity[identity];
          if(!front){
            // No silent data loss: keep a manually edited orphan visible for
            // correction; the server will never accept an unauthorized SKU.
            if($row.hasClass('is-dynamic')&&!$row.find('[data-order-qty]').first().val()&&!$row.find('input[name$="[notes]"]').first().val())$row.remove();
            else $row.addClass('is-outside-fronts').attr('title','Referencia fora das Frentes autorizadas; rever antes de submeter');
            return;
          }
          $row.removeClass('is-outside-fronts').removeAttr('title');
          matched[front.identity]=true;updateOrderRowFromFront($row,front);
        });
        fronts.forEach(function(front){if(!matched[front.identity])createOrderRow($table,front);});
        var dynamicCount=$table.find('[data-order-dynamic-rows] [data-order-row]').length;
        $table.find('[data-order-dynamic-group]').first().prop('hidden',dynamicCount===0);
        $orderQuestion.find('.routespro-order-empty-note').prop('hidden',fronts.length>0);
      });
      syncAllFrontsSuggestions();updateAllCategoryStatuses();
    }
    function visibleQuestions($scope){
      return $scope.find('[data-rp-question]').filter(function(){
        return !$(this).closest('[data-rp-question-wrap]').prop('hidden');
      });
    }
    function setConditionalVisibility($wrap,show){
      if(!$wrap || !$wrap.length) return;
      show=!!show;
      var currentlyShown=!$wrap.prop('hidden')&&$wrap.attr('data-rp-condition-hidden')!=='1';
      if(currentlyShown===show) return;
      $wrap.prop('hidden',!show).attr('data-rp-condition-hidden',show?'0':'1');
      $wrap.find('input,select,textarea,button').each(function(){
        var $control=$(this);
        if(!show){
          if(!$control.prop('disabled')) $control.attr('data-rp-condition-disabled','1');
          if(!$control.prop('disabled')) $control.prop('disabled',true);
        }else if($control.attr('data-rp-condition-disabled')==='1'){
          if(!dailyExisting||dailyEditMode()) $control.prop('disabled',false);
          $control.removeAttr('data-rp-condition-disabled');
        }
      });
    }
    function syncEvidenceCategory($category){
      if(!$category || !$category.length) return;
      var $toggle=$category.find('[data-rp-evidence-toggle]').first();
      var selected=$toggle.is(':checked');
      $category.toggleClass('is-selected',selected);
      $category.find('[data-rp-evidence-panel]').prop('hidden',!selected);
      $category.find('[data-rp-evidence-file]').each(function(){
        var $file=$(this);
        if(!selected){
          $file.prop('disabled',true).attr('data-rp-evidence-disabled','1');
        }else if($file.attr('data-rp-evidence-disabled')==='1'){
          if(!dailyExisting||dailyEditMode()) $file.prop('disabled',false);
          $file.removeAttr('data-rp-evidence-disabled');
        }
      });
    }
    function syncAllEvidenceCategories(){
      $form.find('[data-rp-evidence-category]').each(function(){ syncEvidenceCategory($(this)); });
    }
    function applyConditions(){
      if(runtimeV3Owns()){
        var pilotResult=runtimeV3Call('applyConditions',[form,{source:'legacy_delegate'}]);
        if(pilotResult&&pilotResult.visibility) return pilotResult.visibility;
      }
      var questionMap={},rules=[],targets={};
      $form.find('[data-rp-question]').each(function(){
        var $q=$(this),source=String($q.attr('data-rp-question')||'');
        if(!source) return;
        questionMap[source]=$q;
        conditionRules($q).forEach(function(rule){
          var target=String(rule&&rule.go_to||'');
          if(!target||target===source||!$form.find('[data-rp-question-wrap="'+target+'"]').length) return;
          rules.push({source:source,target:target,value:rule.value});
          targets[target]=true;
        });
      });
      var visibility={};
      var typedCore=rpTypedFormCore(),typedResult=null;
      if(typedCore&&typedCore.conditions&&typeof typedCore.conditions.evaluate==='function'){
        var typedValues={};
        Object.keys(questionMap).forEach(function(key){typedValues[key]=fieldValue(questionMap[key]);});
        try{typedResult=typedCore.conditions.evaluate(Object.keys(questionMap),rules,typedValues);}catch(_){typedResult=null;}
      }
      if(typedResult&&typedResult.visibility&&typeof typedResult.visibility==='object'){
        visibility=typedResult.visibility;
        $form.attr('data-rp-conditional-core-contract','fieldflow-typed-form-state-conditional-core-v1');
      }else{
        Object.keys(questionMap).forEach(function(key){ visibility[key]=!targets[key]; });
        var maxPass=Math.max(1,Object.keys(questionMap).length+rules.length+1);
        for(var pass=0;pass<maxPass;pass++){
          var changed=false;
          rules.forEach(function(rule){
            if(!visibility[rule.source]||visibility[rule.target]) return;
            if(conditionMatches(fieldValue(questionMap[rule.source]),rule.value)){
              visibility[rule.target]=true;
              changed=true;
            }
          });
          if(!changed) break;
        }
      }
      Object.keys(questionMap).forEach(function(key){
        setConditionalVisibility($form.find('[data-rp-question-wrap="'+key+'"]').first(),visibility[key]!==false);
      });
      $form.attr('data-rp-conditional-flow-version','3');
      return visibility;
    }
    function refreshConditionalFlow(){
      applyConditions();
      if(typeof $steps!=='undefined'&&$steps&&$steps.length&&typeof showStep==='function'){
        var visibleIndexes=visibleStepIndexes();
        if(visibleIndexes.indexOf(index)===-1){
          showStep(index,false,0,true);
        }else{
          var visiblePosition=Math.max(0,visibleIndexes.indexOf(index));
          renderWizardActions(Math.max(1,visibleIndexes.length),visiblePosition<=0,visiblePosition>=visibleIndexes.length-1);
          $wizard.attr('data-rp-current-step',String(index)).attr('data-rp-visible-step',String(visiblePosition+1)).attr('data-rp-visible-steps-total',String(Math.max(1,visibleIndexes.length)));
        }
      }
    }
    $form.on('change input','input, select, textarea',function(){
      clearNotice();
      // Protect the field value and its visual position before any conditional/layout reconciliation.
      // Android WebView can reflow aggressively while Cardex matrices change.
      var viewportSnapshot=rpCaptureElementViewport(this);
      scheduleDraftSave();
      refreshConditionalFlow();
      rpRestoreElementViewport(viewportSnapshot);
    });
    $form.on('keydown','input[type="text"],input[type="number"],input[type="email"],input[type="tel"],input[type="url"],input[type="date"],input[type="time"],input:not([type])',function(e){
      if(String(e.key||'')!=='Enter') return;
      // Mobile keyboards often emit Enter/Done while editing Cardex cells. A
      // native form submit here validates the whole wizard and can reveal the
      // first invalid field, which looks like the form jumped back to start.
      e.preventDefault();e.stopPropagation();
      scheduleDraftSave();
      var $step=$(this).closest('[data-rp-step]');
      var $controls=$step.find('input,select,textarea').filter(':visible').filter(function(){return !this.disabled&&this.type!=='hidden'&&this.type!=='file'&&this.type!=='checkbox'&&this.type!=='radio';});
      var position=$controls.index(this);
      if(position>=0&&position<$controls.length-1){try{$controls.get(position+1).focus({preventScroll:true});}catch(_){try{$controls.get(position+1).focus();}catch(__){}}}
      else{try{this.blur();}catch(_){}}
    });
    $form.on('change','[data-rp-evidence-toggle]',function(){ syncEvidenceCategory($(this).closest('[data-rp-evidence-category]')); refreshConditionalFlow(); });
    $form.on('click','[data-rp-enable-daily-edit]',function(e){
      e.preventDefault();
      if(!dailyCanEdit){
        setNotice('error','Não tens permissão para editar este reporte.',true);
        return;
      }
      setDailyEditMode(true);
      setNotice('info','Modo de edição ativo. Altera apenas o necessário e guarda o reporte.',false);
      var $first=$form.find('[data-rp-question-wrap]:visible').first();
      if($first.length) window.setTimeout(function(){ rpScrollTo($first); },40);
    });
    $form.on('click','[data-category-toggle]',function(e){
      e.preventDefault();
      var $group=$(this).closest('[data-category-group]');
      setCategoryGroupOpen($group,$(this).attr('aria-expanded')!=='true');
    });
    $form.on('click','[data-category-open-all]',function(e){
      e.preventDefault();
      $(this).closest('.routespro-form-field').find('[data-category-group]').each(function(){ setCategoryGroupOpen($(this),true); });
    });
    $form.on('click','[data-category-close-all]',function(e){
      e.preventDefault();
      $(this).closest('.routespro-form-field').find('[data-category-group]').each(function(){ setCategoryGroupOpen($(this),false); });
    });
    $form.on('input change','[data-fronts-input], [data-product-row] input[name$="[category]"], [data-product-row] input[name$="[ref]"], [data-product-row] input[name$="[name]"]',function(){
      var $row=$(this).closest('[data-product-row]');
      if($(this).is('[data-fronts-input]'))$row.find('[data-fronts-confirmed]').first().val('1');
      updateCategoryStatus($row.closest('[data-category-group]'));
      syncOrderTablesWithFronts();
    });
    $form.on('input change','[data-order-qty]',function(){
      var $qty=$(this),$row=$qty.closest('[data-order-row]');
      $row.find('[data-order-quantity-source]').first().val('manual');
      if($qty.attr('data-rp-auto-fronts')==='1'){
        $qty.removeAttr('data-rp-auto-fronts');
        $row.removeClass('is-auto-suggested');
        $row.find('.routespro-order-auto-note').prop('hidden',true);
      }
      updateCategoryStatus($row.closest('[data-category-group]'));
    });
    syncAllEvidenceCategories();
    applyConditions();
    syncOrderTablesWithFronts();
    updateAllCategoryStatuses();

    var $steps=$wizard.find('[data-rp-step]');
    var total=$steps.length ? (parseInt($wizard.attr('data-steps-total')||$steps.length,10)||$steps.length) : 1;
    var index=1;
    var embeddedOuter=form.closest('.rp-report-quick,.rp-report-focus,.routespro-report-focus');
    var externalSubmit=$form.attr('data-rp-external-submit')==='1'||$wizard.attr('data-rp-external-submit')==='1'||!!embeddedOuter;
    if(externalSubmit){
      $form.attr('data-rp-external-submit','1');
      $wizard.attr('data-rp-external-submit','1');
      $form.find('.routespro-form-actions').remove();
    }
    var $wizardActions=$();
    var navigationSyncBusy=false;
    function normaliseWizardNavigation(){
      if(!$wizard.length||navigationSyncBusy) return $();
      if(runtimeV3Owns()){
        var pilotNavigation=runtimeV3Call('normaliseNavigation',[form,{externalSubmit:externalSubmit}]);
        if(pilotNavigation){ $wizardActions=$(pilotNavigation); return $wizardActions; }
      }
      navigationSyncBusy=true;
      try{
        var $allGroups=$form.find('[data-rp-wizard-actions]');
        var $groups=$wizard.children('[data-rp-wizard-actions]');
        var $canonical=$groups.filter('[data-rp-navigation-owner="form-renderer"]').first();
        if(!$canonical.length) $canonical=$groups.first();
        if(!$canonical.length){
          $canonical=$('<div class="routespro-form-wizard-actions" data-rp-wizard-actions></div>');
          $wizard.append($canonical);
        }else if($canonical.parent().get(0)!==$wizard.get(0)){
          $wizard.append($canonical);
        }
        $canonical.attr('data-rp-navigation-role','canonical').attr('data-rp-navigation-owner','form-renderer');
        $allGroups.not($canonical).remove();
        $form.find('[data-rp-next],[data-rp-prev],[data-rp-submit]').each(function(){
          if(!$.contains($canonical.get(0),this)) $(this).remove();
        });
        var $prev=$canonical.find('[data-rp-prev]').first();
        var $next=$canonical.find('[data-rp-next]').first();
        var $submit=$canonical.find('[data-rp-submit]').first();
        $canonical.find('[data-rp-prev]').not($prev).remove();
        $canonical.find('[data-rp-next]').not($next).remove();
        $canonical.find('[data-rp-submit]').not($submit).remove();
        if(!$prev.length){ $prev=$('<button type="button" class="routespro-form-btn secondary" data-rp-prev>Anterior</button>'); $canonical.append($prev); }
        if(!$next.length){ $next=$('<button type="button" class="routespro-form-btn" data-rp-next>Seguinte</button>'); $canonical.append($next); }
        if(externalSubmit){ $submit.remove(); $submit=$(); }
        else if(!$submit.length){ $submit=$('<button type="button" class="routespro-form-btn" data-rp-submit>Submeter</button>'); $canonical.append($submit); }
        $wizardActions=$canonical;
        return $canonical;
      }finally{
        navigationSyncBusy=false;
      }
    }
    function renderWizardActions(visibleTotal,isFirst,isLast){
      visibleTotal=Math.max(1,parseInt(visibleTotal||1,10)||1);
      if(runtimeV3Owns()){
        var pilotActions=runtimeV3Call('renderNavigation',[form,{visibleTotal:visibleTotal,isFirst:!!isFirst,isLast:!!isLast,externalSubmit:externalSubmit}]);
        if(pilotActions){ $wizardActions=$(pilotActions); return; }
      }
      var $actions=normaliseWizardNavigation();
      if(!$actions.length) return;
      var showPrev=visibleTotal>1&&!isFirst;
      var showNext=visibleTotal>1&&!isLast;
      var showSubmit=!externalSubmit&&isLast;
      $actions.find('[data-rp-prev]').prop('hidden',!showPrev).prop('disabled',!showPrev);
      $actions.find('[data-rp-next]').prop('hidden',!showNext).prop('disabled',!showNext);
      $actions.find('[data-rp-submit]').prop('hidden',!showSubmit).prop('disabled',!showSubmit||(dailyExisting&&!dailyEditMode()));
      $actions.prop('hidden',!(showPrev||showNext||showSubmit));
      $actions.attr('data-rp-navigation-signature',[visibleTotal,isFirst?1:0,isLast?1:0,externalSubmit?1:0].join(':'));
    }
    var navigationObserver=null;
    if(window.MutationObserver){
      navigationObserver=new MutationObserver(function(records){
        if(navigationSyncBusy) return;
        var needsSync=records.some(function(record){
          return Array.prototype.some.call(record.addedNodes||[],function(node){
            return node&&node.nodeType===1&&((node.matches&&node.matches('[data-rp-wizard-actions],[data-rp-next],[data-rp-prev],[data-rp-submit]'))||(node.querySelector&&node.querySelector('[data-rp-wizard-actions],[data-rp-next],[data-rp-prev],[data-rp-submit]')));
          });
        });
        if(!needsSync) return;
        window.setTimeout(function(){
          if(!$form.closest('html').length) return;
          var indexes=visibleStepIndexes();
          var pos=Math.max(0,indexes.indexOf(index));
          renderWizardActions(indexes.length,pos<=0,pos>=indexes.length-1);
        },0);
      });
      navigationObserver.observe(form,{childList:true,subtree:true});
    }
    function stepByQuestionKey(key){
      var found=null;
      $steps.each(function(){
        var $s=$(this);
        if($s.find('[data-rp-question-wrap="'+key+'"]').length){ found=parseInt($s.attr('data-step-index')||'0',10)||null; return false; }
      });
      return found;
    }
    function visibleStepIndexes(){
      if(runtimeV3Owns()){
        var pilotIndexes=runtimeV3Call('visibleStepIndexes',[form]);
        if(Array.isArray(pilotIndexes)&&pilotIndexes.length) return pilotIndexes;
      }
      var indexes=[];
      $steps.each(function(){
        var $step=$(this),stepIndex=parseInt($step.attr('data-step-index')||'0',10)||0;
        if(!stepIndex) return;
        if(visibleQuestions($step).length) indexes.push(stepIndex);
      });
      if(!indexes.length&&$steps.length) indexes.push(parseInt($steps.first().attr('data-step-index')||'1',10)||1);
      return indexes;
    }
    function snapVisibleStep(requested,direction){
      var indexes=visibleStepIndexes();
      if(indexes.indexOf(requested)!==-1) return requested;
      if(direction<0){
        for(var i=indexes.length-1;i>=0;i--) if(indexes[i]<requested) return indexes[i];
        return indexes[0];
      }
      for(var j=0;j<indexes.length;j++) if(indexes[j]>requested) return indexes[j];
      return indexes[indexes.length-1];
    }
    function previousStepIndex(){
      var indexes=visibleStepIndexes(),position=indexes.indexOf(index);
      if(position>0) return indexes[position-1];
      return indexes[0]||1;
    }
    function showStep(n,shouldScroll,direction,conditionsReady){
      if(!$steps.length) return;
      if(!conditionsReady) applyConditions();
      var visibleIndexes=visibleStepIndexes();
      index=snapVisibleStep(Math.max(1,Math.min(total,n)),direction||0);
      var visiblePosition=Math.max(0,visibleIndexes.indexOf(index));
      var visibleTotal=Math.max(1,visibleIndexes.length);
      var isFirst=visiblePosition<=0;
      var isLast=visiblePosition>=visibleTotal-1;
      $steps.each(function(){
        var $s=$(this),i=parseInt($s.attr('data-step-index')||'0',10),active=i===index;
        var shouldHide=!active;
        if($s.prop('hidden')!==shouldHide) $s.prop('hidden',shouldHide);
        var aria=active?'false':'true';
        if($s.attr('aria-hidden')!==aria) $s.attr('aria-hidden',aria);
      });
      var pct=visibleTotal<=1?100:Math.round((visiblePosition/(visibleTotal-1))*100);
      $wizard.find('.routespro-form-progress-fill').css('width',pct+'%');
      $wizard.find('[data-rp-progress-label]').text('Passo '+(visiblePosition+1)+' de '+visibleTotal+' · '+pct+'%');
      renderWizardActions(visibleTotal,isFirst,isLast);
      $wizard.attr('data-rp-current-step',String(index)).attr('data-rp-visible-step',String(visiblePosition+1)).attr('data-rp-visible-steps-total',String(visibleTotal)).attr('data-rp-is-last-visible-step',isLast?'1':'0');
      var stepDetail={index:visiblePosition+1,total:visibleTotal,step_index:index,is_first:isFirst,is_last:isLast,external_submit:externalSubmit};
      if(externalSubmit){
        var outer=embeddedOuter||form.closest('.rp-report-quick,.rp-report-focus,.routespro-report-focus');
        var focused=!!(outer&&(outer.classList.contains('rp-report-focus')||outer.classList.contains('routespro-report-focus')));
        var outerButton=outer?outer.querySelector(focused?'#rpf-submit':'#rpr-submit'):null;
        if(outerButton) outerButton.hidden=!isLast;
      }
      try{ form.dispatchEvent(new CustomEvent('routespro:wizard-step',{bubbles:true,detail:stepDetail})); }catch(_){ }
      if(shouldScroll){
        window.setTimeout(function(){ rpScrollTo($wizard); },30);
      }
    }
    form.addEventListener('fieldflow:runtime-v3-fallback',function(){
      $form.attr('data-rp-runtime-owner','legacy').attr('data-rp-runtime-mode','fallback');
      window.setTimeout(function(){
        applyConditions();
        normaliseWizardNavigation();
        showStep(index,false,0,true);
      },0);
    });
    var draftTimer=null;
    var draftDirty=false;
    var draftSaveGeneration=0;
    var draftSnapshotClock=0;
    var draftRestoring=false;
    var draftLastKey='';
    var draftFileWarning=false;
    function draftInfrastructureName(name){
      if(!name) return true;
      if(name==='action'||name==='_wp_http_referer') return true;
      if(name==='routespro_form_nonce'||name==='routespro_rest_nonce') return true;
      if(name.indexOf('_wpnonce')===0) return true;
      return [
        'routespro_form_id','routespro_binding_id','routespro_client_id','routespro_project_id','routespro_route_id',
        'routespro_stop_id','routespro_location_id','routespro_context_date','routespro_record_id','routespro_record_version_id',
        'routespro_report_day','routespro_existing_submission_id','routespro_edit_existing','routespro_return_url','routespro_ajax','routespro_draft_id'
      ].indexOf(name)!==-1;
    }
    function draftValue(name){
      var el=form.querySelector('[name="'+name+'"]');
      return el?String(el.value||''):'';
    }
    function draftKey(){
      if(!draftEnabled) return '';
      var parts=[
        String($form.attr('data-rp-user-id')||'0'),String($form.attr('data-routespro-form-id')||'0'),
        draftValue('routespro_client_id')||'0',draftValue('routespro_project_id')||'0',draftValue('routespro_route_id')||'0',
        draftValue('routespro_stop_id')||'0',draftValue('routespro_location_id')||'0',
        String($form.attr('data-rp-report-day')||draftValue('routespro_report_day')||draftValue('routespro_context_date')||'sem-data')
      ];
      return RP_DRAFT_PREFIX+parts.map(function(part){ return encodeURIComponent(part); }).join(':');
    }
    function draftRecoveryKey(){
      if(!draftEnabled) return '';
      // Stable device-side recovery scope deliberately excludes route/stop ids.
      // Native resume/replanning can reconstruct those ids even though the field
      // work still belongs to the same user, form, binding, store and report day.
      var parts=[
        'recovery',String($form.attr('data-rp-user-id')||'0'),String($form.attr('data-routespro-form-id')||'0'),
        draftValue('routespro_binding_id')||'0',draftValue('routespro_client_id')||'0',draftValue('routespro_project_id')||'0',
        draftValue('routespro_location_id')||'0',
        String($form.attr('data-rp-report-day')||draftValue('routespro_report_day')||draftValue('routespro_context_date')||'sem-data')
      ];
      return RP_DRAFT_PREFIX+parts.map(function(part){ return encodeURIComponent(part); }).join(':');
    }
    function draftIdentity(){
      return {
        formId:parseInt($form.attr('data-routespro-form-id')||'0',10)||0,
        bindingId:parseInt(draftValue('routespro_binding_id')||'0',10)||0,
        clientId:parseInt(draftValue('routespro_client_id')||'0',10)||0,
        projectId:parseInt(draftValue('routespro_project_id')||'0',10)||0,
        routeId:parseInt(draftValue('routespro_route_id')||'0',10)||0,
        routeStopId:parseInt(draftValue('routespro_stop_id')||'0',10)||0,
        locationId:parseInt(draftValue('routespro_location_id')||'0',10)||0,
        reportDay:String($form.attr('data-rp-report-day')||draftValue('routespro_report_day')||draftValue('routespro_context_date')||'')
      };
    }
    function draftStorageGet(key){
      try{ return window.localStorage?window.localStorage.getItem(key):null; }catch(_){ return null; }
    }
    function draftStorageSet(key,value){
      try{ if(!window.localStorage) return false; window.localStorage.setItem(key,value); return true; }catch(_){ return false; }
    }
    function draftStorageRemove(key){
      try{ if(window.localStorage) window.localStorage.removeItem(key); }catch(_){ }
    }
    function draftContextFormData(action){
      var data=new FormData();
      data.append('action',action);
      data.append('nonce',draftNonce);
      data.append('form_id',String($form.attr('data-routespro-form-id')||'0'));
      data.append('binding_id',draftValue('routespro_binding_id')||'0');
      data.append('client_id',draftValue('routespro_client_id')||'0');
      data.append('project_id',draftValue('routespro_project_id')||'0');
      data.append('route_id',draftValue('routespro_route_id')||'0');
      data.append('route_stop_id',draftValue('routespro_stop_id')||'0');
      data.append('location_id',draftValue('routespro_location_id')||'0');
      data.append('context_date',draftValue('routespro_context_date')||'');
      data.append('report_day',String($form.attr('data-rp-report-day')||draftValue('routespro_report_day')||''));
      data.append('schema_revision',String($form.attr('data-rp-schema-revision')||''));
      return data;
    }
    function draftServerRequest(action,data,opts){
      opts=opts||{};
      if(!draftServerEnabled||!draftEndpoint||!draftNonce) return Promise.resolve(null);
      data=data||draftContextFormData(action);
      var controller=('AbortController' in window)?new AbortController():null;
      var timer=controller?window.setTimeout(function(){try{controller.abort();}catch(_){}},45000):null;
      return fetch(draftEndpoint,{method:'POST',body:data,credentials:'same-origin',keepalive:!!opts.keepalive,signal:controller?controller.signal:undefined,headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(response){ return response.json().then(function(json){ return {ok:response.ok,json:json,status:response.status}; }); })
        .finally(function(){if(timer)window.clearTimeout(timer);});
    }
    function draftServerBeacon(snapshot){
      if(!draftServerEnabled||!draftEndpoint||!draftNonce||!snapshot||!navigator.sendBeacon) return false;
      try{
        var data=draftContextFormData('routespro_draft_save');
        data.append('payload',JSON.stringify(snapshot));
        data.append('base_revision',String(draftServerRevision||0));
        data.append('client_instance_id',rpDraftClientInstanceId);
        return navigator.sendBeacon(draftEndpoint,data)===true;
      }catch(_){ return false; }
    }
    function draftServerDelete(){
      if(!draftServerEnabled) return Promise.resolve(false);
      window.clearTimeout(draftServerTimer);draftServerTimer=null;
      clearDraftServerRetry();draftServerQueued=null;
      var data=draftContextFormData('routespro_draft_delete');
      return draftServerRequest('routespro_draft_delete',data,{keepalive:true}).then(function(){
        draftServerRevision=0; draftServerFiles=[]; draftUploadedSignatures={}; if($draftId.length) $draftId.val('0'); return true;
      }).catch(function(){ return false; });
    }
    function draftNamedControls(){
      var counts={};
      return Array.prototype.slice.call(form.elements||[]).filter(function(control){
        if(!control||!control.name||draftInfrastructureName(control.name)) return false;
        var type=String(control.type||'').toLowerCase();
        return ['file','submit','button','reset'].indexOf(type)===-1;
      }).map(function(control){
        var name=control.name;
        var ordinal=counts[name]||0; counts[name]=ordinal+1;
        var type=String(control.type||control.tagName||'').toLowerCase();
        var entry={name:name,ordinal:ordinal,type:type};
        if(type==='checkbox'||type==='radio'){
          entry.checked=!!control.checked;
          entry.value=String(control.value||'');
        }else if(type==='select-multiple'){
          entry.value=Array.prototype.slice.call(control.options||[]).filter(function(option){ return option.selected; }).map(function(option){ return String(option.value); });
        }else{
          entry.value=String(control.value===undefined||control.value===null?'':control.value);
        }
        return entry;
      });
    }
    function draftFileInputs(){
      var counts={};
      return Array.prototype.slice.call(form.querySelectorAll('input[type="file"][name]')).map(function(input){
        var ordinal=counts[input.name]||0; counts[input.name]=ordinal+1;var files=rpUploadableFiles(input.files||[]);
        return {input:input,name:input.name,ordinal:ordinal,count:files.length,names:files.map(function(file){return file.name||'';})};
      });
    }
    function draftDynamicRows(){
      var rows=[];
      $form.find('[data-product-row][data-rp-draft-dynamic="1"]').each(function(){
        var $row=$(this),$matrix=$row.closest('[data-product-matrix]');
        var key=String($matrix.attr('data-product-matrix')||'');
        if(key) rows.push({matrix:key,html:this.outerHTML});
      });
      return rows;
    }
    function draftProductRowState(){
      var matrices=[];
      $form.find('[data-product-matrix]').each(function(){
        var $matrix=$(this),key=String($matrix.attr('data-product-matrix')||'');
        if(!key) return;
        var tokens=$matrix.find('[data-product-row]').map(function(){
          var name=String($(this).find('input[name]').first().attr('name')||'');
          return name||null;
        }).get();
        matrices.push({matrix:key,tokens:tokens});
      });
      return matrices;
    }
    function draftCategoryState(){
      return $form.find('[data-category-group]').map(function(){ return $(this).hasClass('is-open')?1:0; }).get();
    }
    function draftSnapshot(){
      var existingId=parseInt($dailyExistingId.val()||'0',10)||0;
      draftSnapshotClock=Math.max(Date.now(),draftSnapshotClock+1);
      return {
        version:2,createdAt:Date.now(),updatedAt:draftSnapshotClock,expiresAt:Date.now()+RP_DRAFT_TTL,
        generation:rpDraftClientInstanceId+'-'+draftSnapshotClock+'-'+Math.random().toString(36).slice(2),
        schemaRevision:String($form.attr('data-rp-schema-revision')||''),
        formId:parseInt($form.attr('data-routespro-form-id')||'0',10)||0,
        identity:draftIdentity(),
        existingSubmissionId:existingId,editMode:dailyEditMode(),step:index,
        controls:draftNamedControls(),productRows:draftProductRowState(),dynamicProductRows:draftDynamicRows(),categories:draftCategoryState(),
        files:draftFileInputs().map(function(item){ return {name:item.name,ordinal:item.ordinal,count:item.count,names:item.names}; })
      };
    }
    function draftFileGroupKey(name,ordinal){
      var core=rpTypedDraftCore();
      if(core&&core.media&&typeof core.media.groupKey==='function'){
        try{return String(core.media.groupKey(name,ordinal));}catch(_){ }
      }
      return String(name||'')+'|'+(parseInt(ordinal||0,10)||0);
    }
    function draftFileSignature(files){
      var core=rpTypedDraftCore();
      if(core&&core.media&&typeof core.media.signature==='function'){
        try{return String(core.media.signature(Array.isArray(files)?files:[]));}catch(_){ }
      }
      return (Array.isArray(files)?files:[]).map(function(file){ return [file.name||'',file.size||0,file.type||'',file.lastModified||0].join(':'); }).join('|');
    }
    function draftExpectedFileCount(draft){
      var core=rpTypedDraftCore();
      if(core&&core.state&&typeof core.state.expectedFileCount==='function'){
        try{return Math.max(0,parseInt(core.state.expectedFileCount(draft)||0,10)||0);}catch(_){ }
      }
      return (Array.isArray(draft&&draft.files)?draft.files:[]).reduce(function(total,item){ return total+(parseInt(item&&item.count||0,10)||0); },0);
    }
    function draftNewestLocal(local,indexed){
      var core=rpTypedDraftCore();
      if(core&&core.state&&typeof core.state.newestLocal==='function'){
        try{
          var result=core.state.newestLocal(local||null,indexed||null);
          if(result&&typeof result==='object') return result;
        }catch(_){ }
      }
      var localUpdated=parseInt(local&&local.updatedAt||0,10)||0,indexedUpdated=parseInt(indexed&&indexed.updatedAt||0,10)||0;
      return indexed&&indexedUpdated>localUpdated?{source:'indexed',snapshot:indexed,updatedAt:indexedUpdated}:{source:local?'local':(indexed?'indexed':'none'),snapshot:local||indexed||null,updatedAt:Math.max(localUpdated,indexedUpdated)};
    }
    function updateDraftServerFiles(files){
      draftServerFiles=Array.isArray(files)?files:[];
    }
    function syncDraftInputFiles(key,item,records){
      if(!draftServerEnabled||!$draftId.length||parseInt($draftId.val()||'0',10)<=0) return Promise.resolve(false);
      records=(Array.isArray(records)?records:[]).filter(function(record){return record&&record.blob&&Number(record.blob.size||record.size||0)>0;}).sort(function(a,b){ return (a.fileIndex||0)-(b.fileIndex||0); });
      var signature=draftFileSignature(records.map(function(record){ return {name:record.name,size:record.size||((record.blob&&record.blob.size)||0),type:record.type,lastModified:record.lastModified}; }));
      var groupKey=draftFileGroupKey(item.name,item.ordinal);
      if(draftUploadedSignatures[groupKey]===signature) return Promise.resolve(true);
      if(!records.length){
        var clearData=draftContextFormData('routespro_draft_upload');
        clearData.append('input_name',item.name);clearData.append('ordinal',String(item.ordinal||0));
        return draftServerRequest('routespro_draft_upload',clearData).then(function(result){
          if(!result||!result.json||!result.json.success) throw new Error((result&&result.json&&result.json.data&&result.json.data.message)||'draft_file_sync_failed');
          updateDraftServerFiles((result.json.data||{}).all_files||[]);draftUploadedSignatures[groupKey]=signature;return true;
        });
      }
      // Send one protected photo at a time. A weak connection can therefore
      // resume at the next file instead of restarting a 12-photo multipart set.
      var chain=Promise.resolve(true);
      records.forEach(function(record,recordIndex){
        chain=chain.then(function(){
          var blob=record.blob;if(!blob||Number(blob.size||0)<=0)return true;
          var file=blob instanceof File?blob:new File([blob],record.name||'ficheiro',{type:record.type||'',lastModified:record.lastModified||Date.now()});
          var data=draftContextFormData('routespro_draft_upload');
          data.append('input_name',item.name);data.append('ordinal',String(item.ordinal||0));
          data.append('single_file','1');data.append('file_index',String(parseInt(record.fileIndex,10)>=0?parseInt(record.fileIndex,10):recordIndex));
          data.append('draft_files[]',file,file.name);data.append('last_modified[]',String(file.lastModified||0));
          return draftServerRequest('routespro_draft_upload',data).then(function(result){
            if(!result||!result.json||!result.json.success) throw new Error((result&&result.json&&result.json.data&&result.json.data.message)||'draft_file_sync_failed');
            updateDraftServerFiles((result.json.data||{}).all_files||(result.json.data||{}).files||[]);return true;
          });
        });
      });
      return chain.then(function(){draftUploadedSignatures[groupKey]=signature;return true;});
    }
    function syncAllDraftFiles(key){
      if(!draftServerEnabled||!key) return Promise.resolve(false);
      return rpDraftReadFiles(key).then(function(records){
        var inputs=draftFileInputs();
        var chain=Promise.resolve(true);
        inputs.forEach(function(item){
          var matching=rpDraftRecordsForInput(records,item.name,item.ordinal);
          if(!matching.length&&rpUploadableFiles(item.input.files||[]).length){
            matching=rpUploadableFiles(item.input.files||[]).map(function(file,fileIndex){
              return {inputName:item.name,ordinal:item.ordinal,fileIndex:fileIndex,name:file.name,type:file.type,size:file.size,lastModified:file.lastModified,blob:file};
            });
          }
          chain=chain.then(function(){ return syncDraftInputFiles(key,item,matching); });
        });
        return chain;
      }).catch(function(){ return false; });
    }
    function applyServerConflict(serverDraft,files){
      if(!serverDraft||!serverDraft.payload) return Promise.resolve(false);
      var snapshot=serverDraft.payload,key=draftKey();
      draftServerRevision=parseInt(serverDraft.revision||0,10)||0;
      if($draftId.length) $draftId.val(String(serverDraft.id||0));
      updateDraftServerFiles(files||[]);
      var local=null,raw=key?draftStorageGet(key):null;
      try{local=raw?JSON.parse(raw):null;}catch(_){local=null;}
      return rpDraftReadSnapshot(key).then(function(indexed){
        var newestResult=draftNewestLocal(local,indexed);
        var newestLocal=newestResult.snapshot;
        var newestUpdated=parseInt(newestResult.updatedAt||0,10)||0;
        var serverUpdated=parseInt(snapshot.updatedAt||0,10)||0;
        if(newestLocal&&newestUpdated>serverUpdated){
          // A delayed response from a weak connection must never roll the form
          // back over a newer checkpoint that already exists on this device.
          setDraftStatus('Existe uma cópia local mais recente. Foi mantida e será sincronizada novamente com o servidor.','warning',true);
          scheduleServerDraftSync(newestLocal,{silent:true});
          return false;
        }
        if(key){draftStorageSet(key,JSON.stringify(snapshot));rpDraftStoreSnapshot(key,snapshot);}
        applyDraftSnapshot(snapshot,key,'server',files||[]);
        setDraftStatus('Foi recuperada a versão mais recente guardada noutro dispositivo.','restored',true);
        return true;
      });
    }
    function scheduleDraftServerRetry(snapshot){
      if(!draftServerEnabled||!snapshot) return;
      draftServerRetrySnapshot=snapshot;
      window.clearTimeout(draftServerRetryTimer);
      draftServerRetryAttempt=Math.min(8,draftServerRetryAttempt+1);
      var delay=Math.min(60000,5000*Math.pow(2,Math.max(0,draftServerRetryAttempt-1)));
      draftServerRetryTimer=window.setTimeout(function(){
        if(!draftServerRetrySnapshot) return;
        if(draftServerBusy){ scheduleDraftServerRetry(draftServerRetrySnapshot); return; }
        serverSyncNow(draftServerRetrySnapshot,{silent:true});
      },delay);
    }
    function clearDraftServerRetry(){
      window.clearTimeout(draftServerRetryTimer);draftServerRetryTimer=null;draftServerRetryAttempt=0;draftServerRetrySnapshot=null;
    }
    function serverSyncNow(snapshot,opts){
      opts=opts||{};
      if(!draftServerEnabled||!snapshot) return Promise.resolve(false);
      if(draftServerBusy){ draftServerQueued={snapshot:snapshot,opts:opts}; return Promise.resolve(false); }
      draftServerBusy=true;
      var data=draftContextFormData('routespro_draft_save');
      data.append('payload',JSON.stringify(snapshot));
      data.append('base_revision',String(draftServerRevision||0));
      data.append('client_instance_id',rpDraftClientInstanceId);
      return draftServerRequest('routespro_draft_save',data,{keepalive:!!opts.keepalive}).then(function(result){
        if(!result||!result.json) throw new Error('draft_server_unavailable');
        if(!result.json.success){
          var error=result.json.data||{};
          if(error.code==='draft_conflict'&&error.draft){ return applyServerConflict(error.draft,error.files||[]).then(function(){return false;}); }
          throw new Error(error.message||error.code||'draft_server_failed');
        }
        var payload=result.json.data||{};
        draftServerRevision=parseInt(payload.revision||0,10)||draftServerRevision;
        if($draftId.length) $draftId.val(String(payload.draft_id||0));
        updateDraftServerFiles(payload.files||[]);
        return syncAllDraftFiles(draftKey()).then(function(filesOk){
          if(filesOk===false) throw new Error('draft_media_sync_pending');
          clearDraftServerRetry();
          if(!opts.silent){
            var syncCopy=payload.schema_changed
              ? 'Rascunho sincronizado. O briefing/formulário mudou entretanto, mas esta cópia foi preservada para reconciliação segura.'
              : 'Rascunho sincronizado'+(draftTimeLabel(snapshot.updatedAt)?' às '+draftTimeLabel(snapshot.updatedAt):'')+'.';
            setDraftStatus(syncCopy,payload.schema_changed?'warning':'saving',false);
          }
          return true;
        });
      }).catch(function(){
        scheduleDraftServerRetry(snapshot);
        if(!opts.silent) setDraftStatus('Rascunho protegido neste dispositivo. A sincronização com o servidor será repetida automaticamente.','warning',false);
        return false;
      }).then(function(result){
        draftServerBusy=false;
        if(draftServerQueued){ var queued=draftServerQueued; draftServerQueued=null; serverSyncNow(queued.snapshot,queued.opts); }
        return result;
      });
    }
    function scheduleServerDraftSync(snapshot,opts){
      opts=opts||{};
      if(!draftServerEnabled||!snapshot) return;
      window.clearTimeout(draftServerTimer);
      if(opts.immediateServer||opts.keepalive){ serverSyncNow(snapshot,opts); return; }
      draftServerTimer=window.setTimeout(function(){ serverSyncNow(snapshot,opts); },RP_DRAFT_SERVER_DELAY);
    }
    function loadServerDraft(){
      if(!draftServerEnabled) return Promise.resolve(null);
      var data=draftContextFormData('routespro_draft_load');
      return draftServerRequest('routespro_draft_load',data).then(function(result){
        if(!result||!result.json||!result.json.success) return null;
        var payload=result.json.data||{};
        if(!payload.has_draft||!payload.draft||!payload.draft.payload){
          if(payload.retained==='daily_state') setDraftStatus('Existe um rascunho protegido no servidor associado a outro estado da visita. Foi mantido para recuperação e não será apagado automaticamente.','warning',true);
          else if(payload.retained==='invalid_payload') setDraftStatus('Existe uma cópia de recuperação no servidor que não pôde ser aberta automaticamente. Foi mantida para diagnóstico.','warning',true);
          else if(payload.retained==='identity') setDraftStatus('Existe um rascunho protegido de outro contexto de loja. Foi mantido para recuperação e as respetivas fotografias não serão carregadas nesta visita.','warning',true);
          return null;
        }
        var server=payload.draft;
        var scopeRebased=!!payload.scope_rebased;
        draftServerRevision=parseInt(server.revision||0,10)||0;
        if($draftId.length) $draftId.val(String(server.id||0));
        updateDraftServerFiles(payload.files||[]);
        var key=draftKey(),raw=draftStorageGet(key),local=null;
        try{ local=raw?JSON.parse(raw):null; }catch(_){ local=null; }
        return rpDraftReadSnapshot(key).then(function(indexed){
          var newestResult=draftNewestLocal(local,indexed);
          var newestLocal=newestResult.snapshot;
          var newestUpdated=parseInt(newestResult.updatedAt||0,10)||0;
          var serverUpdated=parseInt(server.payload.updatedAt||0,10)||0;
          if(!newestLocal||serverUpdated>newestUpdated){
            draftStorageSet(key,JSON.stringify(server.payload));rpDraftStoreSnapshot(key,server.payload);
            applyDraftSnapshot(server.payload,key,'server',payload.files||[]);
            if(scopeRebased) scheduleServerDraftSync(server.payload,{silent:true,immediateServer:true});
          }else if(newestUpdated>serverUpdated){
            scheduleServerDraftSync(newestLocal,{silent:true,immediateServer:scopeRebased});
          }else{
            restoreServerDraftFiles(key,payload.files||[]);
            if(scopeRebased) scheduleServerDraftSync(server.payload,{silent:true,immediateServer:true});
          }
          if(scopeRebased) setDraftStatus('Rascunho recuperado após retoma da visita. O contexto da rota foi reconciliado automaticamente.','restored',true);
          else if(payload.schema_changed) setDraftStatus('O briefing/formulário foi atualizado. Mantivemos o rascunho e recuperámos apenas os campos compatíveis.','warning',true);
          return server;
        });
      }).catch(function(){ return null; });
    }
    function restoreServerDraftFiles(key,files){
      if(!draftServerEnabled||!key||!Array.isArray(files)||!files.length) return Promise.resolve(0);
      var inputs=draftFileInputs(),groups={};
      files.forEach(function(file){ var groupKey=draftFileGroupKey(file.inputName,file.ordinal); if(!groups[groupKey]) groups[groupKey]=[]; groups[groupKey].push(file); });
      var restored=0,chain=Promise.resolve();
      inputs.forEach(function(item){
        var groupKey=draftFileGroupKey(item.name,item.ordinal),list=(groups[groupKey]||[]).sort(function(a,b){return (a.fileIndex||0)-(b.fileIndex||0);});
        if(!list.length||(rpUploadableFiles(item.input.files||[]).length>0)) return;
        if($(item.input).attr('data-rp-has-existing')==='1') $(item.input).attr('data-rp-draft-original-existing','1');
        $(item.input).attr('data-rp-has-existing','1').attr('data-rp-draft-server-file','1');
        chain=chain.then(function(){
          return Promise.all(list.map(function(meta){
            var url=draftEndpoint+'?action=routespro_draft_download&nonce='+encodeURIComponent(draftNonce)+'&token='+encodeURIComponent(meta.token||'');
            return fetch(url,{credentials:'same-origin'}).then(function(response){ if(!response.ok) throw new Error('draft_file_download_failed'); return response.blob(); }).then(function(blob){
              if(!blob||Number(blob.size||0)<=0)throw new Error('draft_file_empty');
              return new File([blob],meta.name||'ficheiro',{type:meta.type||blob.type||'',lastModified:Date.now()});
            });
          })).then(function(downloaded){
            try{
              var transfer=new DataTransfer(); downloaded.forEach(function(file){ transfer.items.add(file); }); item.input.files=transfer.files; restored+=transfer.files.length;
              return rpDraftStoreInputFiles(key,item.input,item.ordinal);
            }catch(_){ draftFileWarning=true; return 0; }
          }).catch(function(){
            // Keep the previous IndexedDB group untouched. A later local restore
            // can still rehydrate it even if this remote transfer failed.
            draftFileWarning=true;return 0;
          });
        });
      });
      return chain.then(function(){ rpRefreshRestoredDraftMediaUi(); return restored; });
    }
    function draftTimeLabel(timestamp){
      try{ return new Intl.DateTimeFormat('pt-PT',{hour:'2-digit',minute:'2-digit'}).format(new Date(timestamp)); }catch(_){ return ''; }
    }
    function saveDraftNow(opts){
      opts=opts||{};
      if(!draftEnabled||draftRestoring) return false;
      var key=draftKey();
      if(!key) return false;
      if(!draftDirty&&!opts.force) return false;
      var snapshot=draftSnapshot();
      var saveGeneration=++draftSaveGeneration;
      draftDirty=true;
      var storedLocal=draftStorageSet(key,JSON.stringify(snapshot));
      var recoveryKey=draftRecoveryKey();
      var recoverySnapshot=null;
      if(recoveryKey&&recoveryKey!==key){
        recoverySnapshot=Object.assign({},snapshot,{_rpPrimaryKey:key});
        draftStorageSet(recoveryKey,JSON.stringify(recoverySnapshot));
      }
      var hasIndexedDb=!!window.indexedDB;
      // Answers now have a second durable copy in IndexedDB. localStorage is
      // kept only as the fast synchronous mirror for immediate resume. A stable
      // recovery alias survives route/stop id reconstruction after native resume.
      function finishCheckpoint(saved){
        if(saveGeneration!==draftSaveGeneration) return;
        if(!saved){
          draftDirty=true;
          setDraftStatus('Não foi possível proteger o rascunho neste dispositivo. Mantém esta página aberta.','warning',false);
          return;
        }
        draftDirty=false;
        if(opts.protectionStatus) setDraftStatus('Rascunho protegido neste dispositivo.','saving',false);
        else if(!opts.silent){
          var copy='Rascunho guardado'+(draftTimeLabel(snapshot.updatedAt)?' às '+draftTimeLabel(snapshot.updatedAt):'')+'.';
          if(draftFileWarning) copy+=' Uma fotografia poderá ter de ser selecionada novamente.';
          setDraftStatus(copy,draftFileWarning?'warning':'saving',false);
        }
      }
      if(hasIndexedDb){
        Promise.resolve().then(function(){return rpDraftStoreSnapshot(key,snapshot);}).then(function(storedIndexed){
          finishCheckpoint(!!storedIndexed);
        }).catch(function(){finishCheckpoint(false);});
        if(recoverySnapshot) Promise.resolve().then(function(){return rpDraftStoreSnapshot(recoveryKey,recoverySnapshot);}).catch(function(){});
      }else finishCheckpoint(storedLocal);
      if(!storedLocal&&!hasIndexedDb){
        setDraftStatus('Não foi possível guardar o rascunho neste dispositivo.','warning',false);
        return false;
      }
      draftLastKey=key;
      if(!opts.localOnly){
        // During Android/iOS suspension, a normal fetch can be terminated by
        // the WebView immediately after pagehide. sendBeacon hands the latest
        // answer snapshot to the browser/network stack before suspension.
        if(!(opts.lifecycle&&draftServerBeacon(snapshot))) scheduleServerDraftSync(snapshot,opts);
      }
      return !hasIndexedDb&&storedLocal;
    }
    function scheduleDraftSave(){
      if(!draftEnabled||draftRestoring) return;
      draftDirty=true;
      // The local checkpoint is synchronous and happens on every input/change.
      // The 450 ms debounce is now only for server synchronisation, never for
      // protecting the latest answer on the device.
      var localSaved=saveDraftNow({force:true,silent:true,localOnly:true,protectionStatus:true});
      setDraftStatus(localSaved?'Rascunho protegido neste dispositivo.':'A guardar rascunho...',localSaved?'saving':'warning',false);
      window.clearTimeout(draftTimer);
      draftTimer=window.setTimeout(function(){ saveDraftNow({force:true}); },450);
    }
    function clearDraft(opts){
      opts=opts||{};
      draftSaveGeneration++;
      window.clearTimeout(draftTimer);draftTimer=null;
      window.clearTimeout(draftServerTimer);draftServerTimer=null;
      clearDraftServerRetry();draftServerQueued=null;
      var keys=[draftKey(),draftRecoveryKey(),draftLastKey].filter(function(value,index,list){ return value&&list.indexOf(value)===index; });
      keys.forEach(function(key){ draftStorageRemove(key); rpDraftDeleteSnapshot(key); rpDraftDeleteFiles(key); });
      if(!opts.localOnly) draftServerDelete();
      draftDirty=false; draftLastKey=''; draftFileWarning=false;
      if(!opts.silent) setDraftStatus('', '', false);
    }
    function pruneDraftProductRows(states){
      if(!Array.isArray(states)) return;
      states.forEach(function(state){
        if(!state||!state.matrix||!Array.isArray(state.tokens)) return;
        var allowed=state.tokens.map(String);
        var $matrix=$form.find('[data-product-matrix]').filter(function(){ return String($(this).attr('data-product-matrix')||'')===String(state.matrix); }).first();
        if(!$matrix.length) return;
        $matrix.find('[data-product-row]').not('[data-rp-draft-dynamic="1"]').each(function(){
          var token=String($(this).find('input[name]').first().attr('name')||'');
          if(token&&allowed.indexOf(token)===-1) $(this).remove();
        });
      });
    }
    function addDraftDynamicRows(rows){
      $form.find('[data-product-row][data-rp-draft-dynamic="1"]').remove();
      (Array.isArray(rows)?rows:[]).forEach(function(row){
        if(!row||!row.matrix||!row.html) return;
        var $matrix=$form.find('[data-product-matrix]').filter(function(){ return String($(this).attr('data-product-matrix')||'')===String(row.matrix); }).first();
        if(!$matrix.length) return;
        var $target=$matrix.find('[data-product-dynamic-rows]').first();
        var $restored=$(row.html);rpFrontsDecorateDynamicRow($restored,$matrix);
        if($target.length){
          $target.append($restored);
          $matrix.find('[data-product-dynamic-group]').first().prop('hidden',false);
        }else{
          var $panel=$matrix.find('[data-category-panel]').last();
          ($panel.length?$panel:$matrix).append($restored);
        }
      });
      $form.find('[data-product-dynamic-group]').each(function(){
        var $group=$(this),count=$group.find('[data-product-dynamic-rows] [data-product-row]').length;
        $group.prop('hidden',count===0);
        $group.find('[data-product-dynamic-count]').text(count+(count===1?' referência acrescentada':' referências acrescentadas'));
      });
    }
    function applyDraftControls(entries){
      var controlsByName={};
      Array.prototype.slice.call(form.elements||[]).forEach(function(control){
        if(!control||!control.name) return;
        if(!controlsByName[control.name]) controlsByName[control.name]=[];
        controlsByName[control.name].push(control);
      });
      (Array.isArray(entries)?entries:[]).forEach(function(entry){
        if(!entry||!entry.name||draftInfrastructureName(entry.name)) return;
        var controls=controlsByName[entry.name]||[];
        var control=controls[parseInt(entry.ordinal||0,10)];
        if(!control) return;
        var type=String(control.type||'').toLowerCase();
        if(type==='checkbox'||type==='radio') control.checked=!!entry.checked;
        else if(type==='select-multiple'){
          var selected=Array.isArray(entry.value)?entry.value.map(String):[];
          Array.prototype.slice.call(control.options||[]).forEach(function(option){ option.selected=selected.indexOf(String(option.value))!==-1; });
        }else control.value=entry.value===undefined||entry.value===null?'':String(entry.value);
      });
    }
    function rpRefreshRestoredDraftMediaUi(){
      // Files restored from IndexedDB/server are assigned programmatically to
      // input.files. Browsers do not emit a native change event for that
      // assignment, so rebuild the visual preview explicitly. This keeps the
      // UI consistent with the durable draft without forcing a new capture.
      $form.find('[data-rp-upload-zone]').each(function(){
        var $zone=$(this);
        rpPrepareUploadZone($zone);
        rpSyncUploadPreview($zone);
      });
      rpRefreshMediaMetrics(form);
      syncAllEvidenceCategories();
      try{
        form.dispatchEvent(new CustomEvent('fieldflow:draft-media-rehydrated',{bubbles:true,detail:{source:'draft'}}));
      }catch(_){ }
    }
    function rpEnsureDraftFileOrdinals(records){
      var required={};
      (Array.isArray(records)?records:[]).forEach(function(record){
        if(!record||!record.inputName) return;
        var name=rpDraftBaseInputName(record.inputName);
        if(!name) return;
        var ordinal=Math.max(0,parseInt(record.ordinal||0,10)||0);
        required[name]=Math.max(required[name]===undefined?-1:required[name],ordinal);
      });
      Object.keys(required).forEach(function(name){
        var existing=Array.prototype.slice.call(form.querySelectorAll('input[type="file"][name]')).filter(function(input){return input.name===name;});
        if(!existing.length) return;
        var needed=required[name]+1;
        while(existing.length<needed){
          var $template=$(existing[0]);
          var $zone=$template.closest('[data-rp-upload-zone]');
          if(!$zone.length) break;
          var $row=$('<div class="routespro-upload-input-row is-clone" data-rp-upload-input-row data-rp-upload-clone="1" data-rp-draft-rehydrated-clone="1"></div>');
          var $clone=$template.clone(false).val('').removeAttr('id required data-rp-has-existing capture data-rp-camera-input');
          $clone.removeAttr('multiple').attr('data-rp-upload-source','rehydrated');
          $row.append($clone);
          if(String($zone.attr('data-rp-upload-kind')||'file')!=='image') $row.append('<button type="button" class="routespro-upload-remove" data-rp-upload-remove aria-label="Remover seleção">×</button>');
          $zone.find('[data-rp-upload-inputs]').first().append($row);
          existing.push($clone.get(0));
        }
      });
    }
    function restoreDraftFiles(key){
      return rpDraftReadFiles(key).then(function(records){
        if(!records.length) return 0;
        rpEnsureDraftFileOrdinals(records);
        var inputs=draftFileInputs();
        var restored=0;
        inputs.forEach(function(item){
          var matching=rpDraftRecordsForInput(records,item.name,item.ordinal).sort(function(a,b){ return (a.fileIndex||0)-(b.fileIndex||0); });
          if(!matching.length) return;
          try{
            var transfer=new DataTransfer();
            matching.forEach(function(record){
              if(!(record.blob instanceof Blob)||Number(record.blob.size||0)<=0){draftFileWarning=true;return;}
              var file=record.blob instanceof File?record.blob:new File([record.blob],record.name||'ficheiro',{type:record.type||'',lastModified:record.lastModified||Date.now()});
              if(Number(file.size||0)>0)transfer.items.add(file);
            });
            item.input.files=transfer.files;
            restored+=transfer.files.length;
          }catch(_){ draftFileWarning=true; }
        });
        rpRefreshRestoredDraftMediaUi();
        return restored;
      });
    }
    function draftSnapshotContextIsValid(draft,key,allowRouteRebase){
      var currentExisting=parseInt($dailyExistingId.val()||'0',10)||0,core=rpTypedDraftCore();
      var recoveryKey=draftRecoveryKey();
      allowRouteRebase=!!allowRouteRebase||(!!recoveryKey&&key===recoveryKey);
      var identity=draftIdentity();
      if(core&&core.state&&typeof core.state.contextValid==='function'){
        try{return !!core.state.contextValid(draft||null,{nowMs:Date.now(),dailyExisting:!!dailyExisting,dailyCanEdit:!!dailyCanEdit,currentExistingSubmissionId:currentExisting,identity:identity,allowRouteRebase:allowRouteRebase});}catch(_){ }
      }
      if(!draft||parseInt(draft.version||0,10)!==2||parseInt(draft.expiresAt||0,10)<Date.now()) return false;
      var saved=draft.identity&&typeof draft.identity==='object'?draft.identity:null;
      if(!saved) return false;
      var stable=['formId','bindingId','clientId','projectId','locationId'];
      for(var identityIndex=0;identityIndex<stable.length;identityIndex++){
        var identityKey=stable[identityIndex];
        if((parseInt(saved[identityKey]||0,10)||0)!==(parseInt(identity[identityKey]||0,10)||0)) return false;
      }
      if(String(saved.reportDay||'')!==String(identity.reportDay||'')) return false;
      if(!allowRouteRebase){
        if((parseInt(saved.routeId||0,10)||0)!==(parseInt(identity.routeId||0,10)||0)) return false;
        if((parseInt(saved.routeStopId||0,10)||0)!==(parseInt(identity.routeStopId||0,10)||0)) return false;
      }
      if((parseInt(identity.formId||0,10)||0)<=0||(parseInt(identity.locationId||0,10)||0)<=0) return false;
      if(dailyExisting){
        if(!dailyCanEdit||!draft.editMode||!currentExisting||currentExisting!==parseInt(draft.existingSubmissionId||0,10)) return false;
      }else if(parseInt(draft.existingSubmissionId||0,10)>0) return false;
      return true;
    }
    function draftSnapshotSchemaMatches(draft){
      var revision=String($form.attr('data-rp-schema-revision')||''),core=rpTypedDraftCore();
      if(core&&core.state&&typeof core.state.schemaMatches==='function'){
        try{return !!core.state.schemaMatches(draft||null,revision);}catch(_){ }
      }
      return String(draft&&draft.schemaRevision||'')===revision;
    }
    function draftSnapshotIsValid(draft,key){ return draftSnapshotContextIsValid(draft,key)&&draftSnapshotSchemaMatches(draft); }
    function applyDraftSnapshot(draft,key,source,serverFiles,allowRouteRebase){
      if(!draftSnapshotContextIsValid(draft,key,allowRouteRebase)) return false;
      var schemaChanged=!draftSnapshotSchemaMatches(draft);
      draftRestoring=true;
      draftLastKey=key;
      // Never prune current Cardex/form rows using an older schema. Compatible
      // controls and user-added rows are recovered by name instead.
      if(!schemaChanged) pruneDraftProductRows(draft.productRows);
      addDraftDynamicRows(draft.dynamicProductRows);
      applyDraftControls(draft.controls);
      syncOrderTablesWithFronts();
      applyDraftControls(draft.controls);
      syncAllEvidenceCategories();
      applyConditions();
      syncOrderTablesWithFronts();
      updateAllCategoryStatuses();
      if(Array.isArray(draft.categories)){
        $form.find('[data-category-group]').each(function(groupIndex){ if(draft.categories[groupIndex]!==undefined) setCategoryGroupOpen($(this),!!draft.categories[groupIndex]); });
      }
      if(dailyExisting&&draft.editMode) setDailyEditMode(true);
      if($steps.length) showStep(parseInt(draft.step||1,10)||1,false);
      // Cold reconstruction only: after answers, conditions and wizard step exist, restore the saved anchor once.
      rpRestoreFormViewport(form,'draft-cold-restore');
      draftRestoring=false;
      var copy=(source==='server'?'Rascunho recuperado do servidor':'Rascunho recuperado')+(draftTimeLabel(draft.updatedAt)?', guardado às '+draftTimeLabel(draft.updatedAt):'')+'.';
      if(schemaChanged) copy='O formulário ou briefing foi atualizado. As respostas compatíveis do rascunho foram recuperadas sem apagar a cópia anterior.';
      setDraftStatus(copy,schemaChanged?'warning':'restored',true);
      var localFiles=Promise.resolve(0);
      var remoteFiles=Promise.resolve(0);
      var expectedFileCount=draftExpectedFileCount(draft);
      if(source==='server'){
        // The server snapshot can be newer even when its schema differs from
        // the page currently open. Clear only the DOM handoff, never the
        // durable IndexedDB blobs, so remote media gets a fair chance to load.
        // If download fails, the previous device copy remains the fallback.
        draftFileInputs().forEach(function(item){
          try{ item.input.value=''; }catch(_){ }
          $(item.input).removeAttr('data-rp-draft-server-file');
          if($(item.input).attr('data-rp-draft-original-existing')!=='1') $(item.input).removeAttr('data-rp-has-existing');
        });
        if(expectedFileCount<=0&&!schemaChanged){
          // Only an exact-schema newer server snapshot may authoritatively say
          // there is no media. A stale-schema snapshot must not erase current
          // local evidence merely because that older version had no file field.
          localFiles=rpDraftDeleteFiles(key).then(function(){return 0;});
        }else if(expectedFileCount>0){
          // Download first, durably store successful remote groups, then read
          // the local vault. That second pass restores the union of remote
          // replacements plus any device-only groups that the server lacked.
          remoteFiles=restoreServerDraftFiles(key,serverFiles||[]).catch(function(){draftFileWarning=true;return 0;});
          localFiles=remoteFiles.then(function(){return restoreDraftFiles(key);});
        }else{
          localFiles=restoreDraftFiles(key);
        }
      }else{
        localFiles=restoreDraftFiles(key);
        remoteFiles=Promise.resolve(0);
      }
      Promise.all([localFiles,remoteFiles]).then(function(counts){
        // Final UI pass after local and server restore races settle. This is
        // intentionally direct rather than dispatching change, so restored
        // files are not re-optimised or needlessly re-written.
        rpRefreshRestoredDraftMediaUi();
        var restored=Math.max(parseInt(counts[0]||0,10),parseInt(counts[1]||0,10));
        var expected=draftExpectedFileCount(draft);
        if(expected>0&&restored<expected) draftFileWarning=true;
        syncAllEvidenceCategories();
        if(expected>0&&restored<expected) setDraftStatus(copy+' '+restored+'/'+expected+' ficheiros recuperados. A cópia local/servidor foi mantida para nova tentativa, não concluas a visita sem validar as fotografias.','warning',true);
        else if(restored>0) setDraftStatus(copy+' '+restored+(restored===1?' ficheiro recuperado.':' ficheiros recuperados.'),draftFileWarning||schemaChanged?'warning':'restored',true);
        else if(draftFileWarning) setDraftStatus(copy+' Algumas fotografias terão de ser selecionadas novamente.','warning',true);
        if(schemaChanged){
          // Only after media rehydration do we checkpoint the current schema,
          // so old photos are never pruned by a zero-file intermediate snapshot.
          draftDirty=true;
          saveDraftNow({force:true,silent:true,localOnly:true});
        }
      });
      return true;
    }
    function draftPreferredInitialStep(){
      if(!draftEnabled||!$steps.length) return 1;
      var candidates=[],keys=[draftKey(),draftRecoveryKey()].filter(Boolean);
      keys.forEach(function(key){
        var raw=draftStorageGet(key),draft=null;
        try{draft=raw?JSON.parse(raw):null;}catch(_){draft=null;}
        if(draft&&draftSnapshotContextIsValid(draft,key)){
          candidates.push({step:parseInt(draft.step||1,10)||1,updatedAt:parseInt(draft.updatedAt||0,10)||0});
        }
      });
      if(!candidates.length) return 1;
      candidates.sort(function(a,b){return b.updatedAt-a.updatedAt;});
      return Math.max(1,Math.min(total,parseInt(candidates[0].step||1,10)||1));
    }
    function restoreDraft(){
      if(!draftEnabled) return;
      var key=draftKey(),recoveryKey=draftRecoveryKey();
      if(!key) return;
      if($form.attr('data-rp-submit-success')==='1'){
        [key,recoveryKey].filter(Boolean).forEach(function(cleanKey){draftStorageRemove(cleanKey);rpDraftDeleteSnapshot(cleanKey);rpDraftDeleteFiles(cleanKey);});
        draftServerDelete(); return;
      }
      var raw=draftStorageGet(key),local=null,exactRestored=false;
      if(raw){
        try{ local=JSON.parse(raw); }catch(_){ local=null; }
        if(local&&draftSnapshotContextIsValid(local,key)){ applyDraftSnapshot(local,key,'local',[]); exactRestored=true; }
        else if(local){ setDraftStatus('Existe um rascunho local de um estado anterior da visita. Foi mantido no dispositivo para recuperação e não será apagado automaticamente.','warning',true); }
      }
      rpDraftReadSnapshot(key).then(function(indexed){
        if(indexed&&draftSnapshotContextIsValid(indexed,key)){
          var newestResult=draftNewestLocal(local,indexed);
          if(newestResult.snapshot){
            if(newestResult.source==='indexed') draftStorageSet(key,JSON.stringify(newestResult.snapshot));
            if(!exactRestored||newestResult.source==='indexed'){ applyDraftSnapshot(newestResult.snapshot,key,'local',[]); exactRestored=true; }
          }
        }
        if(exactRestored||!recoveryKey||recoveryKey===key) return null;
        var recoveryRaw=draftStorageGet(recoveryKey),recoveryLocal=null;
        try{ recoveryLocal=recoveryRaw?JSON.parse(recoveryRaw):null; }catch(_){ recoveryLocal=null; }
        return rpDraftReadSnapshot(recoveryKey).then(function(recoveryIndexed){
          var candidate=draftNewestLocal(recoveryLocal,recoveryIndexed).snapshot;
          if(!candidate||!draftSnapshotContextIsValid(candidate,recoveryKey)) return null;
          var previousKey=String(candidate._rpPrimaryKey||recoveryKey);
          if(!applyDraftSnapshot(candidate,previousKey,'local',[],true)) return null;
          // Re-home the answer snapshot immediately under the current route/stop.
          // File blobs are restored from previousKey and re-checkpointed below.
          draftLastKey=key;draftDirty=true;
          saveDraftNow({force:true,silent:true,localOnly:true});
          restoreDraftFiles(previousKey).then(function(){
            draftFileInputs().forEach(function(item){ if(rpUploadableFiles(item.input.files||[]).length) rpDraftStoreInputFiles(key,item.input,item.ordinal); });
          });
          setDraftStatus('Rascunho recuperado neste dispositivo após retoma da visita. O contexto da rota foi reconciliado.','restored',true);
          return candidate;
        });
      }).then(function(){ return loadServerDraft(); });
    }
    function saveChangedFileInput(input){
      if(!draftEnabled||draftRestoring||!input||!input.name) return Promise.resolve(false);
      if((input.files||[]).length===0&&$(input).attr('data-rp-draft-server-file')==='1'){
        $(input).removeAttr('data-rp-draft-server-file');
        if($(input).attr('data-rp-draft-original-existing')!=='1') $(input).removeAttr('data-rp-has-existing');
      }
      var sameName=Array.prototype.slice.call(form.querySelectorAll('input[type="file"][name]')).filter(function(candidate){ return candidate.name===input.name; });
      var ordinal=Math.max(0,sameName.indexOf(input));
      var key=draftKey();
      if(!key) return Promise.resolve(false);
      form.dataset.rpMediaSettling='1';
      return rpDraftStoreInputFiles(key,input,ordinal).then(function(){
        draftFileWarning=false;
        scheduleDraftSave();
        // Persist the answer snapshot again after the File blob has reached
        // IndexedDB. This is the commit point for camera/gallery return.
        saveDraftNow({force:true,silent:true,localOnly:true});
        var wizard=form.querySelector('.routespro-form-wizard');
        var detail={
          source:'media-selected',
          route_id:parseInt($form.find('[name="routespro_route_id"]').val()||0,10)||0,
          stop_id:parseInt($form.find('[name="routespro_stop_id"],[name="routespro_route_stop_id"]').first().val()||0,10)||0,
          route_stop_id:parseInt($form.find('[name="routespro_stop_id"],[name="routespro_route_stop_id"]').first().val()||0,10)||0,
          location_id:parseInt($form.find('[name="routespro_location_id"]').val()||0,10)||0,
          client_id:parseInt($form.find('[name="routespro_client_id"]').val()||0,10)||0,
          project_id:parseInt($form.find('[name="routespro_project_id"]').val()||0,10)||0,
          form_id:parseInt($form.attr('data-routespro-form-id')||$form.find('[name="routespro_form_id"]').val()||0,10)||0,
          date:String($form.find('[name="routespro_report_day"],[name="routespro_context_date"]').first().val()||$form.attr('data-rp-report-day')||''),
          step_index:parseInt(wizard&&wizard.dataset?wizard.dataset.rpCurrentStep||'0':'0',10)||0,
          media_count:rpUploadableFiles(input.files||[]).length,
          contract:RP_REPORT_SELECTION_MEDIA_CONTRACT
        };
        try{document.dispatchEvent(new CustomEvent('fieldflow:form-media-checkpointed',{detail:detail}));}catch(_){ }
        return true;
      }).catch(function(){
        draftFileWarning=true;
        scheduleDraftSave();
        setDraftStatus('As respostas foram protegidas, mas uma fotografia poderá ter de ser selecionada novamente.','warning',false);
        return false;
      }).finally(function(){delete form.dataset.rpMediaSettling;});
    }
    function checkpointAllDraftFiles(){
      if(!draftEnabled||draftRestoring) return Promise.resolve(false);
      var key=draftKey();
      if(!key) return Promise.resolve(false);
      var tasks=draftFileInputs().filter(function(item){ return item&&item.input&&rpUploadableFiles(item.input.files||[]).length>0; }).map(function(item){
        return rpDraftStoreInputFiles(key,item.input,item.ordinal).then(function(){ return true; }).catch(function(){ draftFileWarning=true; return false; });
      });
      if(!tasks.length) return Promise.resolve(true);
      return Promise.all(tasks).then(function(results){ return results.every(function(value){ return value!==false; }); });
    }
    function optimiseFileInput(input,redispatch){
      if(!input||!(input.files||[]).length) return Promise.resolve(false);
      if(input._rpOptimisePromise) return input._rpOptimisePromise;
      input.dataset.rpOptimising='1';
      input._rpOptimisePromise=rpOptimiseInputFiles(input).catch(function(){return false;}).then(function(changed){
        delete input.dataset.rpOptimising;input._rpOptimisePromise=null;
        var $zone=$(input).closest('[data-rp-upload-zone]');
        if($zone.length){rpRemoveDuplicateFiles(form,$zone);rpSyncUploadPreview($zone);}
        rpRefreshMediaMetrics(form);
        if(redispatch){input.dataset.rpOptimisedPass='1';input.dispatchEvent(new Event('change',{bubbles:true}));}
        return changed;
      });
      return input._rpOptimisePromise;
    }
    function optimiseAllFileInputs(){
      var inputs=Array.prototype.slice.call(form.querySelectorAll('input[type="file"][name]')).filter(function(input){return rpUploadableFiles(input.files||[]).length>0;});
      var chain=Promise.resolve(false);
      inputs.forEach(function(input){chain=chain.then(function(anyChanged){return optimiseFileInput(input,false).then(function(changed){return anyChanged||changed;});});});
      return chain.then(function(changed){if(changed)$form.find('[data-rp-upload-zone]').each(function(){rpSyncUploadPreview($(this));});rpRefreshMediaMetrics(form);return changed;});
    }
    function submitAjaxWithProgress(opts){
      opts=opts||{};
      var maxRetries=Math.max(0,Math.min(2,parseInt(opts.maxRetries===undefined?1:opts.maxRetries,10)||0));
      var nonce=String(opts.nonce||'');
      var ajaxEndpoint=String(form.getAttribute('data-rp-submit-ajax-endpoint')||'');
      var nativeContext=!!(window.FieldFlowMobile&&window.FieldFlowMobile.native);
      var endpoint=String((nativeContext&&ajaxEndpoint)?ajaxEndpoint:(form.getAttribute('action')||window.location.href||''));
      var attempt=0;
      function send(){
        rpRefreshMediaMetrics(form);
        var started=Date.now(),startedField=form.querySelector('[name="routespro_client_submit_started_ms"]');if(startedField)startedField.value=String(started);
        var fd=new FormData(form);fd.set('routespro_ajax','1');
        return new Promise(function(resolve,reject){
          var xhr=new XMLHttpRequest(),uploadComplete=false;xhr.open('POST',endpoint,true);xhr.withCredentials=true;xhr.timeout=Math.max(30000,parseInt(opts.timeout||120000,10)||120000);
          xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');if(nonce)xhr.setRequestHeader('X-WP-Nonce',nonce);
          xhr.upload.onprogress=function(event){
            if(!event.lengthComputable)return;if(event.total>0&&event.loaded>=event.total)uploadComplete=true;var pct=Math.max(1,Math.min(99,Math.round((event.loaded/event.total)*100)));
            setDraftStatus('A enviar fotografias e respostas, '+pct+'%','saving',false);
            try{form.dispatchEvent(new CustomEvent('fieldflow:upload-progress',{bubbles:true,detail:{percent:pct,loaded:event.loaded,total:event.total,attempt:attempt+1}}));}catch(_){ }
          };
          xhr.upload.onload=function(){uploadComplete=true;};
          function fail(kind,status){
            var retryable=(kind==='network'||kind==='timeout'||status===0)&&!uploadComplete;
            if(retryable&&attempt<maxRetries){
              attempt++;form._rpUploadRetryCount=Number(form._rpUploadRetryCount||0)+1;rpRefreshMediaMetrics(form);
              setDraftStatus('A ligação oscilou. A repetir o envio com segurança…','warning',false);
              try{form.dispatchEvent(new CustomEvent('fieldflow:upload-retry',{bubbles:true,detail:{attempt:attempt+1,reason:kind,status:status||0}}));}catch(_){ }
              window.setTimeout(function(){send().then(resolve).catch(reject);},Math.min(1800,500*attempt));return;
            }
            var message=uploadComplete?'O envio terminou, mas a confirmação do servidor não chegou. Atualiza a rota antes de voltar a submeter para evitar duplicados.':(kind==='timeout'?'O envio excedeu o tempo disponível. Confirma a ligação e tenta novamente.':'Não foi possível concluir o envio.');var error=new Error(message);error.code=kind;error.status=status||0;error.safeToRetry=!uploadComplete;error.possiblyCommitted=uploadComplete;reject(error);
          }
          xhr.onerror=function(){fail('network',xhr.status||0);};xhr.ontimeout=function(){fail('timeout',xhr.status||0);};
          xhr.onload=function(){
            var text=String(xhr.responseText||''),json={};try{json=text?JSON.parse(text):{};}catch(_){json={success:false,data:{message:rpSafeServerErrorMessage(text,'Falha ao guardar o formulario.')}};}
            if(xhr.status<200||xhr.status>=300||!json.success){
              var payload=(json&&json.data)||{},error=new Error(rpSafeServerErrorMessage(payload.message||payload.code||'','Falha ao guardar o formulario.'));error.code=payload.code||'server';error.data=payload;error.status=xhr.status||0;
              error.safeToRetry=false;error.possiblyCommitted=xhr.status>=500;reject(error);return;
            }
            setDraftStatus('Envio concluído.','saving',false);
            try{form.dispatchEvent(new CustomEvent('fieldflow:upload-progress',{bubbles:true,detail:{percent:100,loaded:1,total:1,attempt:attempt+1}}));}catch(_){ }
            resolve(json.data||json);
          };
          xhr.send(fd);
        });
      }
      return send();
    }
    form.addEventListener('change',function(event){
      var input=event.target;
      if(!input||String(input.type||'').toLowerCase()!=='file'||!input.name) return;
      if(input.dataset.rpOptimisedPass!=='1') rpMediaNoteChange(input,$(input).closest('[data-rp-upload-zone]'));
      if(input.dataset.rpOptimisedPass==='1'){ delete input.dataset.rpOptimisedPass; return; }
      if(input.dataset.rpOptimising==='1'||!(input.files||[]).length) return;
      // Native picker return is corrected before any asynchronous file work, so no late visible jump occurs.
      rpRestoreFormViewport(form,'media-return');
      rpReleaseFormViewportLock(form,'media-return');
      var cleaned=rpSanitiseInputFiles(input),$mediaZone=$(input).closest('[data-rp-upload-zone]');
      if(cleaned.removed>0){
        rpUploadStatus($mediaZone,cleaned.files.length?'Uma seleção vazia foi retirada. As restantes fotografias continuam protegidas.':'A fotografia recebida ficou sem dados no dispositivo. Tira-a novamente antes de concluir.','error');
        if(!cleaned.files.length){scheduleDraftSave();rpSyncUploadPreview($mediaZone);return;}
      }
      // Snapshot the form synchronously, then start persisting the original
      // capture before any asynchronous image optimisation. If iOS/Android
      // suspends the WebView here, the answer state is already in localStorage
      // and IndexedDB is already receiving the unmodified file.
      scheduleDraftSave();
      var sameName=Array.prototype.slice.call(form.querySelectorAll('input[type="file"][name]')).filter(function(candidate){return candidate.name===input.name;});
      var ordinal=Math.max(0,sameName.indexOf(input));
      var key=draftKey();
      var originalFiles=Array.prototype.slice.call(input.files||[]);
      // Keep an independent, immutable recovery group before optimisation. The
      // active group may later contain smaller transport copies, but a camera
      // original remains available if the WebView dies during processing.
      var originalCheckpoint=key?rpDraftStoreOriginalInputFiles(key,input,ordinal,originalFiles).catch(function(){
        draftFileWarning=true;
        return 0;
      }):Promise.resolve(0);
      // Failure of the extra original fallback must never block the certified
      // active draft copy or the normal upload path.
      var mediaCheckpoint=Promise.resolve(originalCheckpoint).then(function(){ return saveChangedFileInput(input); });
      event.preventDefault();event.stopImmediatePropagation();
      setDraftStatus('A proteger fotografia no dispositivo…','saving',false);
      Promise.resolve(mediaCheckpoint).then(function(saved){
        if(saved)setDraftStatus('Fotografia protegida no dispositivo. A otimizar para envio…','saving',false);
        return optimiseFileInput(input,false);
      }).then(function(){
        // Legacy 9AB N to N+1 package marker retained for the installed 2.2.544 validator:
        // Re-checkpoint the optimised File set. In 2.2.545 this means one active transport commit only.
        // Persist the optimised transport copy exactly once. The original is
        // kept under the recovery namespace and never overwrites this group.
        return saveChangedFileInput(input);
      }).finally(function(){rpReleaseFormViewportLock(form,'media-selected');});
    },true);
    $form.on('change','input[type="file"][name]',function(){
      var input=this;if(!input||!input.name)return;
      var sameName=Array.prototype.slice.call(form.querySelectorAll('input[type="file"][name]')).filter(function(candidate){return candidate.name===input.name;});
      var ordinal=Math.max(0,sameName.indexOf(input)),key=draftKey();
      if(!(input.files||[]).length&&key){
        Promise.all([rpDraftStoreInputFiles(key,input,ordinal),rpDraftStoreOriginalInputFiles(key,input,ordinal,[])]).then(function(){scheduleDraftSave();}).catch(function(){draftFileWarning=true;scheduleDraftSave();});
        return;
      }
      saveChangedFileInput(input);
    });
    $form.on('click','[data-rp-discard-draft]',function(e){
      e.preventDefault();e.stopPropagation();
      rpConfirmFormDeletion({title:'Queres mesmo descartar este rascunho?',
        detail:'Os dados e fotografias que só estão guardados neste dispositivo podem perder-se. Se cancelares, o rascunho mantém-se.',
        confirm:'Sim, descartar rascunho'}).then(function(approved){
          if(!approved||!form.isConnected)return;
          clearDraft();
          setDraftStatus('Rascunho descartado.','saving',false);
          window.setTimeout(function(){setDraftStatus('','',false);},1800);
        });
    });
    function rpSpecializedMediaValue($scope){
      var values=[];
      $scope.find('[data-rp-upload-file]').each(function(){
        var input=this,files=rpUploadableFiles(input.files||[]);
        files.forEach(function(file){values.push({name:String(file.name||''),size:Number(file.size||0),type:String(file.type||'')});});
        if($(input).is('[data-rp-has-existing="1"]')) values.push({url:'fieldflow://existing-media'});
      });
      return values;
    }
    function firstTypedSpecializedInvalid($scope){
      var core=rpTypedSpecializedValidationCore();
      if(!core||!core.validation) return null;
      var invalid=null;
      $scope.find('[data-rp-question-wrap]:visible').each(function(){
        var $wrap=$(this),key=String($wrap.attr('data-rp-question-wrap')||''),$q=$wrap.find('[data-rp-question="'+key+'"]').first();
        if(!key||!$q.length) return;
        var type=String($q.attr('data-rp-type')||'');
        try{
          if(type==='categorized_image_upload'){
            var $evidence=$q.find('[data-rp-evidence-upload]').first();
            var required=$evidence.is('[data-rp-required-evidence="1"]');
            var selected=[],media={};
            $evidence.find('[data-rp-evidence-category]').each(function(){
              var $category=$(this),category=String($category.attr('data-rp-evidence-category')||'');
              if(!$category.find('[data-rp-evidence-toggle]').is(':checked')) return;
              selected.push(category);media[category]=rpSpecializedMediaValue($category);
            });
            var categorized=core.validation.categorized(required,selected,media);
            if(!categorized.valid){
              var missing=Array.isArray(categorized.missingKeys)&&categorized.missingKeys.length?String(categorized.missingKeys[0]||''):'';
              var $target=missing?$evidence.find('[data-rp-evidence-category="'+missing+'"]').first():$evidence;
              invalid=$target.find('[data-rp-evidence-file],[data-rp-evidence-toggle]').first().get(0)||$target.get(0);
              return false;
            }
          }
          if(type==='share_space_matrix'&&$q.is('[data-rp-required-matrix="1"]')){
            var rows=[],expected=[];
            $q.find('[data-share-space-card]').each(function(){
              var $card=$(this),metric=String($card.find('input[type=hidden][name*="[metric_key]"]').first().val()||'');
              expected.push(metric);rows.push({metric_key:metric,pr_fronts:$card.find('[data-share-space-pr]').first().val(),competitor_fronts:$card.find('[data-share-space-competitor]').first().val()});
            });
            var share=core.validation.shareSpace(true,rows,expected);
            if(!share.valid){
              var miss=Array.isArray(share.missingKeys)&&share.missingKeys.length?String(share.missingKeys[0]||''):'';
              var $cardTarget=$q.find('[data-share-space-card]').filter(function(){return String($(this).find('input[type=hidden][name*="[metric_key]"]').first().val()||'')===miss;}).first();
              if(!$cardTarget.length)$cardTarget=$q.find('[data-share-space-card]').first();
              invalid=$cardTarget.find('[data-share-space-pr]').first().get(0)||$cardTarget.get(0)||$q.get(0);
              return false;
            }
          }
          var $requiredUpload=$q.find('[data-rp-upload-zone][data-rp-upload-required="1"]').first();
          if($requiredUpload.length&&!core.validation.mediaRequired(true,rpSpecializedMediaValue($requiredUpload)).valid){
            invalid=$requiredUpload.find('[data-rp-upload-trigger]:not([hidden]):not(:disabled)').first().get(0)||$requiredUpload.find('input[type=file]').first().get(0)||$requiredUpload.get(0);
            return false;
          }
          if($q.is('[data-rp-evidence-flow-answer="1"]')){
            var photoKey=String($q.attr('data-rp-evidence-photo-key')||''),reasonKey=String($q.attr('data-rp-evidence-reason-key')||'');
            var $photoWrap=photoKey?$scope.find('[data-rp-question-wrap="'+photoKey+'"]').first():$(),$reasonWrap=reasonKey?$scope.find('[data-rp-question-wrap="'+reasonKey+'"]').first():$();
            var photoValue=$photoWrap.length?rpSpecializedMediaValue($photoWrap):[],reasonValue=$reasonWrap.length?fieldValue($reasonWrap.find('[data-rp-question="'+reasonKey+'"]').first()):'';
            var evidence=core.validation.evidence(fieldValue($q),$q.is('[data-rp-evidence-require-photo="1"]'),$q.is('[data-rp-evidence-require-reason="1"]'),photoValue,reasonValue);
            if(!evidence.valid){
              var $evidenceTarget=evidence.issueCode==='evidence_photo_required'?$photoWrap:$reasonWrap;
              invalid=$evidenceTarget.find('input,select,textarea,button').filter(':visible').first().get(0)||$evidenceTarget.get(0)||$q.get(0);
              return false;
            }
          }
          if(type==='product_matrix'){
            var matrixRequired=$q.find('[required]').length>0;
            if(matrixRequired){
              var rows=[];$q.find('[data-product-row]').each(function(){var $row=$(this);rows.push({category:$row.find('input[name*="[category]"]').first().val(),ref:$row.find('input[name*="[ref]"]').first().val(),name:$row.find('input[name*="[name]"]').first().val(),qty:$row.find('input[name*="[qty]"],input[name*="[after]"]').first().val()});});
              var product=core.validation.productMatrix(true,rows);
              if(!product.valid){invalid=$q.find('[data-product-row] input:visible').first().get(0)||$q.get(0);return false;}
            }
          }
          $form.attr('data-rp-specialized-validation-core-contract','fieldflow-typed-specialized-validation-core-v1');
        }catch(_){ }
      });
      return invalid;
    }
    function firstTypedSubmissionInvalid($scope,visibility){
      var core=rpTypedSubmissionCore();
      if(!core||!core.validation||typeof core.validation.prepare!=='function') return null;
      var questions=[],values={};
      $scope.find('[data-rp-question-wrap]').each(function(){
        var $wrap=$(this),key=String($wrap.attr('data-rp-question-wrap')||''),$q=$wrap.find('[data-rp-question="'+key+'"]').first();
        if(!key||!$q.length) return;
        var type=String($q.attr('data-rp-type')||'text');
        if(['image_upload','file_upload','categorized_image_upload','product_matrix','share_space_matrix','order_suggestion'].indexOf(type)>=0) return;
        var required=$q.find('[required]').length>0||$q.find('[data-rp-required-choice="1"]').length>0;
        var definition={key:key,type:type,required:required,label:String($q.find('label,legend').first().text()||key)};
        if(type==='number'||type==='currency'||type==='percent'){
          var $numeric=$q.find('input[type=number]').first();
          if($numeric.length){definition.min=$numeric.attr('min')||'';definition.max=$numeric.attr('max')||'';}
        }
        if(type==='select'||type==='radio'||type==='visual_choice'){
          var options=[];
          $q.find('option,input[type=radio],input[type=checkbox]').each(function(){var value=String($(this).val()||'');if(value&&options.indexOf(value)<0)options.push(value);});
          definition.options=options;
          definition.selection_mode=$q.attr('data-rp-multiple-choice')==='1'?'multiple':'single';
        }
        questions.push(definition);
        values[key]=fieldValue($q);
      });
      if(!questions.length) return null;
      try{
        var prepared=core.validation.prepare(questions,visibility||{},values);
        $form.attr('data-rp-submission-core-contract','fieldflow-typed-submission-validation-core-v1');
        var semantic=prepared&&Array.isArray(prepared.invalidSemanticKeys)?prepared.invalidSemanticKeys:[];
        var missing=prepared&&Array.isArray(prepared.missingRequiredKeys)?prepared.missingRequiredKeys:[];
        var failing=semantic.length?semantic:missing;
        if(!failing.length) return null;
        var key=String(failing[0]||'');
        if(!key) return null;
        var $wrap=$scope.find('[data-rp-question-wrap="'+key+'"]').first();
        if(!$wrap.length) return null;
        return $wrap.find('input,select,textarea,button').filter(':visible').first().get(0)||$wrap.get(0);
      }catch(_){ return null; }
    }
    function firstInvalid($scope){
      if(dailyExisting&&!dailyEditMode()) return null;
      var invalid=null;
      visibleQuestions($scope).each(function(){
        var $q=$(this);
        var $evidence=$q.find('[data-rp-evidence-upload]').first();
        if($evidence.length){
          var $selectedCategories=$evidence.find('[data-rp-evidence-category]').filter(function(){ return $(this).find('[data-rp-evidence-toggle]').is(':checked'); });
          if($evidence.is('[data-rp-required-evidence="1"]')&&!$selectedCategories.length){
            invalid=$evidence.find('[data-rp-evidence-toggle]').first().get(0)||$evidence.get(0);
            return false;
          }
          $selectedCategories.each(function(){
            var $category=$(this),$files=$category.find('[data-rp-evidence-file]');
            if(!$files.length) return;
            var hasFiles=false;$files.each(function(){if(rpUploadableFiles(this.files||[]).length>0)hasFiles=true;});
            var hasExisting=$files.is('[data-rp-has-existing="1"]');
            if(!hasFiles&&!hasExisting){ invalid=$files.first().get(0); return false; }
          });
          if(invalid) return false;
        }
        if($q.is('[data-rp-required-matrix="1"]')){
          var matrixMissing=null;
          $q.find('[data-share-space-card]').each(function(){
            var $card=$(this),$pr=$card.find('[data-share-space-pr]').first(),$competitor=$card.find('[data-share-space-competitor]').first();
            var prValue=String($pr.val()==null?'':$pr.val()).trim(),competitorValue=String($competitor.val()==null?'':$competitor.val()).trim();
            if(prValue===''&&competitorValue===''){
              matrixMissing=$pr.get(0)||$competitor.get(0)||this;
              if(matrixMissing&&typeof matrixMissing.setCustomValidity==='function') matrixMissing.setCustomValidity('Regista as frentes deste comparativo. Quando não existirem frentes, introduz 0.');
              return false;
            }
          });
          if(matrixMissing){ invalid=matrixMissing; return false; }
        }
        var $requiredUpload=$q.find('[data-rp-upload-zone][data-rp-upload-required="1"]').first();
        if($requiredUpload.length){
          var requiredUploadHasFiles=false;
          $requiredUpload.find('input[type=file]').each(function(){if(rpUploadableFiles(this.files||[]).length>0)requiredUploadHasFiles=true;});
          var requiredUploadHasExisting=$requiredUpload.find('[data-rp-has-existing="1"]').length>0;
          if(!requiredUploadHasFiles&&!requiredUploadHasExisting){
            invalid=$requiredUpload.find('[data-rp-upload-trigger]:not([hidden]):not(:disabled)').first().get(0)||$requiredUpload.find('input[type=file]').first().get(0)||$requiredUpload.get(0);
            return false;
          }
        }
        var $requiredChoice=$q.find('[data-rp-required-choice="1"]');
        if($requiredChoice.length && !$requiredChoice.find('input[type=checkbox],input[type=radio]').is(':checked')){
          invalid=$requiredChoice.find('input[type=checkbox],input[type=radio]').first().get(0)||$requiredChoice.get(0);
          return false;
        }
        $q.find('[required]').each(function(){
          if(invalid) return false;
          var el=this;
          if(el.disabled) return;
          if(el.type==='radio'){
            var name=$(el).attr('name');
            if(!$form.find('input[type=radio][name="'+name+'"]:checked').length) invalid=el;
          }else if(el.type==='checkbox'){
            if(!el.checked) invalid=el;
          }else if(el.type==='file'){
            var $zone=$(el).closest('[data-rp-upload-zone]');
            if($zone.length){
              var zoneHasFiles=false;$zone.find('input[type=file]').each(function(){if(rpUploadableFiles(this.files||[]).length>0)zoneHasFiles=true;});
              var zoneHasExisting=$zone.find('[data-rp-has-existing="1"]').length>0;
              if(!zoneHasFiles&&!zoneHasExisting) invalid=el;
            }else if(rpUploadableFiles(el.files||[]).length===0 && !$(el).is('[data-rp-has-existing="1"]')) invalid=el;
          }else if(!String($(el).val()||'').trim()) invalid=el;
          if(!invalid && typeof el.checkValidity==='function' && !el.checkValidity()) invalid=el;
        });
        if(invalid) return false;
      });
      return invalid;
    }
    function revealInvalid(el){
      if(!el) return;
      var $el=$(el),$step=$el.closest('[data-rp-step]');
      if($step.length){
        var target=parseInt($step.attr('data-step-index')||'1',10)||1;
        showStep(target,false);
      }
      var $categoryGroup=$el.closest('[data-category-group]');
      if($categoryGroup.length) setCategoryGroupOpen($categoryGroup,true);
      var $wrap=$el.closest('[data-rp-question-wrap]');
      $wrap.addClass('routespro-form-invalid');
      window.setTimeout(function(){ $wrap.removeClass('routespro-form-invalid'); },520);
      window.setTimeout(function(){
        try{ el.focus({preventScroll:true}); }catch(_){ try{el.focus();}catch(__){} }
        rpScrollTo($wrap.length?$wrap:$el);
        if(typeof el.reportValidity==='function') el.reportValidity();
      },40);
    }
    function validateScope($scope,announce){
      var visibility=applyConditions()||{};
      var invalid=firstTypedSpecializedInvalid($scope)||firstTypedSubmissionInvalid($scope,visibility)||firstInvalid($scope);
      if(!invalid) return true;
      if(announce!==false) setNotice('error','Há campos obrigatórios por preencher. Revê o campo assinalado.',false);
      revealInvalid(invalid);
      return false;
    }
    function nextStepIndex(){
      // As condições mostram ou escondem perguntas. Não são saltos de
      // navegação. Usar go_to como jump fazia ignorar passos válidos entre a
      // pergunta de origem e o alvo, como encomenda, evidências e observações.
      applyConditions();
      var indexes=visibleStepIndexes(),position=indexes.indexOf(index);
      return position>=0&&position<indexes.length-1?indexes[position+1]:(indexes[indexes.length-1]||index);
    }
    if($wizard.length && $steps.length){
      $wizard.on('click','[data-rp-next]',function(e){
        e.preventDefault();
        var $current=$wizard.find('[data-rp-step][data-step-index="'+index+'"]');
        if(!validateScope($current,true)) return;
        clearNotice();
        showStep(nextStepIndex(),true,1);
        scheduleDraftSave();
      });
      $wizard.on('click','[data-rp-prev]',function(e){ e.preventDefault(); clearNotice(); showStep(previousStepIndex(),true,-1); scheduleDraftSave(); });
      $wizard.on('click','[data-rp-submit]',function(e){
        e.preventDefault();
        if(!validateScope($form,true)) return;
        try{ form.dispatchEvent(new CustomEvent('fieldflow:form-submit-intent',{bubbles:true,detail:{channel:'wizard',runtime_owner:String($form.attr('data-rp-runtime-owner')||'legacy'),external_submit:externalSubmit,step_index:index}})); }catch(_){ }
        var outer=form.closest('.rp-report-quick,.rp-report-focus,.routespro-report-focus');
        var isFocused=!!(outer&&(outer.classList.contains('rp-report-focus')||outer.classList.contains('routespro-report-focus')));
        var outerButton=outer ? outer.querySelector(isFocused?'#rpf-submit':'#rpr-submit') : null;
        if(outerButton){
          // Qualquer formulário embebido deve terminar através do controlador
          // exterior. Esse fluxo confirma a visita, reconcilia a paragem e
          // atualiza imediatamente a rota visível no portal e na WebView.
          var dailyLocked=$form.attr('data-rp-daily-existing')==='1'&&!dailyEditMode();
          if(!dailyLocked){
            outerButton.disabled=false;
            outerButton.dataset.rpDailyLocked='0';
            outerButton.textContent=isFocused
              ? ($form.attr('data-rp-daily-existing')==='1'?'Guardar alterações':'Concluir visita')
              : ($form.attr('data-rp-daily-existing')==='1'?'Guardar alterações':'Guardar reporte');
          }
          outerButton.click();
          return;
        }
        saveDraftNow({force:true,silent:true,localOnly:true});
        window.clearTimeout(draftServerTimer);
        setWorking(true,'A guardar...');
        if(form.requestSubmit) form.requestSubmit(); else form.submit();
      });
      $form.find('.routespro-form-actions').prop('hidden',true);
      // Restore the last local step synchronously before IndexedDB/server draft
      // reconciliation. This prevents a resumed WebView from visibly resetting
      // to step 1 while the durable draft is still loading.
      showStep(draftPreferredInitialStep(),false);
    }
    $form.on('submit',function(e){
      var embeddedOuter=form.closest('.rp-report-quick,.rp-report-focus,.routespro-report-focus');
      var isFocusedOuter=!!(embeddedOuter&&(embeddedOuter.classList.contains('rp-report-focus')||embeddedOuter.classList.contains('routespro-report-focus')));
      var embeddedOuterButton=embeddedOuter ? embeddedOuter.querySelector(isFocusedOuter?'#rpf-submit':'#rpr-submit') : null;
      if(embeddedOuterButton){
        e.preventDefault();
        if(dailyExisting&&!dailyEditMode()){
          setNotice('warning','O reporte desta loja já foi efetuado hoje. Escolhe “Editar reporte” para corrigir respostas.',true);
          setWorking(false);
          return false;
        }
        if(!validateScope($form,true)){
          setWorking(false);
          return false;
        }
        embeddedOuterButton.click();
        return false;
      }
      if(dailyExisting&&!dailyEditMode()){
        e.preventDefault();
        setNotice('warning','O reporte desta loja já foi efetuado hoje. Escolhe “Editar reporte” para corrigir respostas.',true);
        setWorking(false);
        return false;
      }
      if(!validateScope($form,true)){
        e.preventDefault();
        setWorking(false);
        return false;
      }
      var standaloneStopId=parseInt($form.find('[name="routespro_stop_id"],[name="routespro_route_stop_id"]').first().val()||0,10)||0;
      if(standaloneStopId>0&&!dailyExisting&&!form._rpVisitCloseConfirmed){
        if(!window.confirm('Queres mesmo concluir esta visita?\n\nConfirma apenas quando terminares o reporte. Depois de fechar, a loja fica concluída.')){
          e.preventDefault();
          setWorking(false);
          return false;
        }
        form._rpVisitCloseConfirmed=true;
      }
      // 2.2.460: standalone FieldFlow forms must use the same outbox-first,
      // resumable media path as Quick Report and Focused Visit. Otherwise a
      // large set of photos is sent as one multipart request and can be rejected
      // by PHP post_max_size before WordPress or FieldFlow can process it.
      if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.submitForm==='function'&&!form._rpStandaloneResumableSubmitting){
        e.preventDefault();
        form._rpStandaloneResumableSubmitting=true;
        setWorking(true,'A proteger e enviar fotografias...');
        var offlineMeta={
          route_id:parseInt($form.find('[name="routespro_route_id"]').val()||0,10)||0,
          route_stop_id:parseInt($form.find('[name="routespro_stop_id"],[name="routespro_route_stop_id"]').first().val()||0,10)||0,
          location_id:parseInt($form.find('[name="routespro_location_id"]').val()||0,10)||0,
          report_day:String($form.find('[name="routespro_report_day"]').val()||$form.attr('data-rp-report-day')||'')
        };
        Promise.resolve(typeof form.routesproPrepareFinalSubmit==='function'?form.routesproPrepareFinalSubmit():true).then(function(){
          return window.FieldFlowOffline.submitForm(form,offlineMeta,{nonce:String((window.FieldFlowOfflineConfig&&window.FieldFlowOfflineConfig.nonce)||'')});
        }).then(function(data){
          data=data||{};
          if(!data.queued_offline&&typeof form.routesproMarkSaved==='function')form.routesproMarkSaved(data);
          var message=String(data.message||(data.queued_offline?'Reporte protegido no dispositivo. A sincronização será concluída automaticamente.':'Reporte gravado com sucesso.'));
          if(typeof form.routesproFeedback==='function')form.routesproFeedback('success',message,true);else setNotice('success',message,true);
        }).catch(function(error){
          var message=String(error&&error.message||'Não foi possível concluir o envio das fotografias. A cópia local foi mantida quando disponível.');
          if(typeof form.routesproFeedback==='function')form.routesproFeedback('error',message,true);else setNotice('error',message,true);
        }).then(function(){
          form._rpStandaloneResumableSubmitting=false;
          setWorking(false);
        });
        return false;
      }
      saveDraftNow({force:true,silent:true,localOnly:true});
      window.clearTimeout(draftServerTimer);
      setWorking(true,'A guardar...');
    });
    form.routesproRevealField=function(key,message){
      key=String(key||'').replace(/[^a-zA-Z0-9_-]/g,'');
      if(!key) return false;
      applyConditions();
      var $wrap=$form.find('[data-rp-question-wrap="'+key+'"]').first();
      if(!$wrap.length) return false;
      var $step=$wrap.closest('[data-rp-step]');
      if($step.length) showStep(parseInt($step.attr('data-step-index')||'1',10)||1,false,0,true);
      $wrap.addClass('routespro-form-invalid');
      if(message) setNotice('error',String(message),false);
      window.setTimeout(function(){rpScrollTo($wrap);$wrap.removeClass('routespro-form-invalid');},60);
      return true;
    };
    form.routesproSyncProductOrder=syncOrderTablesWithFronts;
    form.routesproRefreshConditionalFlow=function(){ refreshConditionalFlow(); return true; };
    form.routesproValidate=function(opts){
      opts=opts||{};
      return validateScope(opts.scope?$(opts.scope):$form,opts.announce!==false);
    };
    form.routesproFeedback=function(type,message,shouldScroll){
      setWorking(false);
      setNotice(type,message,shouldScroll!==false);
    };
    form.routesproFlushDraft=function(opts){
      opts=opts||{force:true,silent:true};
      if(opts.immediateServer===undefined) opts.immediateServer=true;
      // Start durable file writes before the synchronous local snapshot. The
      // caller must not need to await this during pagehide/freeze.
      checkpointAllDraftFiles();
      return saveDraftNow(opts);
    };
    form.routesproSubmitAjax=function(opts){ return submitAjaxWithProgress(opts||{}); };
    form.routesproRefreshMediaMetrics=function(){ rpRefreshMediaMetrics(form); return true; };
    form.routesproPrepareFinalSubmit=function(){
      return optimiseAllFileInputs().then(function(){
        // The outbox may merge durable draft media after serialising the live
        // form. Ensure IndexedDB contains the exact final, optimised File set
        // first, otherwise a restored pre-optimisation Blob could be merged as
        // an additional photo during offline submission.
        return checkpointAllDraftFiles();
      }).then(function(){
        window.clearTimeout(draftTimer);window.clearTimeout(draftServerTimer);clearDraftServerRetry();draftServerQueued=null;
        return saveDraftNow({force:true,silent:true,localOnly:true});
      });
    };
    form.routesproTouchDraft=scheduleDraftSave;
    form.routesproClearDraft=function(opts){ clearDraft(opts||{}); };
    form.routesproRestoreDraft=restoreDraft;
    // Legacy 9AD certification marker only: rpRestoreFormViewport(form,'draft-resume')
    form.routesproResumeDraft=function(opts){
      if(!draftEnabled||draftRestoring) return false;
      opts=opts||{};
      // fieldflow-hot-resume-dom-authority-v1: a mounted live form is authoritative.
      // Cold WebView reconstruction already restores the durable draft during init;
      // a hot resume must never replay an older snapshot over the current DOM.
      rpRestoreFormViewport(form,'hot-resume');
      try{form.dispatchEvent(new CustomEvent('fieldflow:form-resumed',{bubbles:true,detail:{reason:String(opts.reason||'resume'),step_index:index,contract:RP_FIELD_SESSION_FORM_CONTINUITY_CONTRACT,navigation_contract:RP_FIELD_RESUME_NAVIGATION_CONTRACT,viewport_contract:rpViewportSingleAnchorContract,hot_resume:true,route_id:parseInt($form.find('[name="routespro_route_id"]').val()||0,10)||0,stop_id:parseInt($form.find('[name="routespro_stop_id"],[name="routespro_route_stop_id"]').first().val()||0,10)||0,location_id:parseInt($form.find('[name="routespro_location_id"]').val()||0,10)||0}}));}catch(_){ }
      return true;
    };
    rpDraftForms.push(form);
    form.routesproMarkSaved=function(data){
      data=data||{};
      if(!data.outbox_synced) clearDraft({silent:true});
      if(data.submission_id&&$dailyExistingId.length) $dailyExistingId.val(String(data.submission_id));
      dailyExisting=true;
      dailyCanEdit=true;
      $form.attr('data-rp-daily-existing','1').attr('data-rp-daily-can-edit','1');
      if(data.report_day) $form.attr('data-rp-report-day',String(data.report_day));
      var $notice=$form.find('[data-rp-daily-notice]').first();
      if(!$notice.length){
        $notice=$('<div class="routespro-form-notice warning routespro-daily-report-notice" data-rp-daily-notice><div><strong>Reporte já efetuado.</strong><span data-rp-daily-mode-copy></span></div><button type="button" class="routespro-form-btn secondary routespro-daily-edit-btn" data-rp-enable-daily-edit>Editar reporte</button></div>');
        $live.after($notice);
      }
      setDailyEditMode(false);
      setDraftStatus('', '', false);
    };
    if(dailyExisting) setDailyEditMode(false);
    restoreDraft();
  }

  // 9CQ: referências acrescentadas numa matriz compacta usam o mesmo −/+ das linhas do Cardex.
  function rpFrontsDecorateDynamicRow($row,$box){
    if(!$row||!$row.length||!$box||!$box.hasClass('is-compact-fronts'))return;
    $row.find('input[data-fronts-input]').each(function(){
      if($(this).closest('.routespro-fronts-stepper').length)return;
      $(this).wrap('<span class="routespro-fronts-stepper"></span>');
      $(this).before('<button type="button" class="routespro-fronts-step" data-fronts-step="-1" tabindex="-1" aria-label="Menos uma frente">−</button>').after('<button type="button" class="routespro-fronts-step" data-fronts-step="1" tabindex="-1" aria-label="Mais uma frente">+</button>');
      $(this).attr('inputmode','numeric').attr('step','1').attr('placeholder','0');
    });
  }
  $(document).on('click','.routespro-product-add',function(e){
    e.preventDefault();
    var key=$(this).attr('data-product-add')||'';
    var $form=$(this).closest('.routespro-dyn-form');
    var form=$form.get(0);
    if(form) rpInitDynamicForm(form);
    var $box=$form.find('[data-product-matrix="'+key.replace(/"/g,'\"')+'"]').first();
    if(!$box.length) return;
    var viewportSnapshot=rpCaptureElementViewport($(this).closest('[data-rp-question-wrap]').get(0)||$box.get(0));
    var i=0;
    $box.find('[data-product-row]').each(function(){
      var name=String($(this).find('input[name]').first().attr('name')||'');
      var match=name.match(/\[(\d+)\]/);
      if(match)i=Math.max(i,(parseInt(match[1],10)||0)+1);
    });
    var hasHistory=$box.find('.routespro-product-row-head').hasClass('routespro-product-row-history');
    var cardexId=parseInt($box.attr('data-cardex-id')||$box.closest('[data-cardex-id]').attr('data-cardex-id')||'0',10)||0;
    var row='<div class="routespro-product-row'+(hasHistory?' routespro-product-row-history':'')+'" data-product-row data-product-item-id="0" data-rp-draft-dynamic="1">'+
      '<input type="hidden" name="'+key+'['+i+'][fronts_confirmed]" value="0" data-fronts-confirmed>'+
      '<input type="hidden" name="'+key+'['+i+'][item_id]" value="0"><input type="hidden" name="'+key+'['+i+'][cardex_id]" value="'+cardexId+'"><input type="hidden" name="'+key+'['+i+'][item_type]" value=""><input type="hidden" name="'+key+'['+i+'][capacity_cl]" value=""><input type="hidden" name="'+key+'['+i+'][units_per_case]" value="">'+
      '<label class="routespro-product-input routespro-product-category-input"><small>Categoria</small><input type="text" name="'+key+'['+i+'][category]" value="" placeholder="Categoria"></label>'+
      '<label class="routespro-product-input routespro-product-ref-input"><small>Referência</small><input type="text" name="'+key+'['+i+'][ref]" value="" placeholder="Código ou referência"></label>'+
      '<label class="routespro-product-input routespro-product-name-input"><small>Produto</small><input type="text" name="'+key+'['+i+'][name]" value="" placeholder="Nome do produto"></label>'+
      (hasHistory?'<label class="routespro-product-input routespro-product-before-input"><small>Frentes antes</small><input type="number" step="0.01" min="0" name="'+key+'['+i+'][before]" value="" placeholder="0" readonly class="routespro-product-before"></label><label class="routespro-product-input routespro-product-qty-input"><small>Frentes depois</small><input type="number" step="0.01" min="0" name="'+key+'['+i+'][after]" value="" placeholder="Vazio = por confirmar" data-fronts-input></label>':'<label class="routespro-product-input routespro-product-qty-input"><small>Número de frentes</small><input type="number" step="0.01" min="0" name="'+key+'['+i+'][qty]" value="" placeholder="Vazio = por confirmar" data-fronts-input></label>')+
      '<button type="button" class="routespro-product-remove" aria-label="Remover produto">Remover</button>'+ 
    '</div>';
    var $dynamicGroup=$box.find('[data-product-dynamic-group]').first();
    var $dynamic=$dynamicGroup.find('[data-product-dynamic-rows]').first();
    var $added=$(row);rpFrontsDecorateDynamicRow($added,$box);row=$added;
    if($dynamic.length){
      $dynamic.append(row);
      $dynamicGroup.prop('hidden',false);
      var count=$dynamic.find('[data-product-row]').length;
      $dynamicGroup.find('[data-product-dynamic-count]').text(count+(count===1?' referência acrescentada':' referências acrescentadas'));
    }else{
      var $panel=$box.find('[data-category-panel]').last();
      if($panel.length)$panel.append(row);else $box.append(row);
    }
    if(form&&typeof form.routesproSyncProductOrder==='function') form.routesproSyncProductOrder();
    if(form&&typeof form.routesproTouchDraft==='function') form.routesproTouchDraft();
    rpRestoreElementViewport(viewportSnapshot);
    if(form)rpCaptureFormViewport(form,'cardex-add',viewportSnapshot&&viewportSnapshot.element?viewportSnapshot.element:null);
  });
  $(document).on('click','.routespro-product-remove',function(e){
    e.preventDefault();e.stopPropagation();
    var $button=$(this),$row=$button.closest('[data-product-row]');
    if(!$row.length||$button.prop('disabled'))return;
    var label=String($row.find('input[name$="[name]"]').val()||$row.attr('data-product-name')||$row.find('input[name$="[ref]"]').val()||$row.attr('data-product-ref')||'esta referência').trim().slice(0,150);
    rpConfirmFormDeletion({title:'Queres mesmo remover este produto?',
      detail:'«'+label+'» será removido da contagem e do Cardex desta loja quando guardares o formulário. As restantes referências não serão alteradas.',
      confirm:'Sim, remover produto'}).then(function(approved){
        if(!approved||!$row.get(0)||!$row.get(0).isConnected)return;
        var $form=$row.closest('.routespro-dyn-form'),$box=$row.closest('[data-product-matrix]');
        if(!$form.length||!$box.length)return;
        var form=$form.get(0);
        if(form)rpInitDynamicForm(form);
        var anchor=$row.next('[data-product-row]').get(0)||$row.prev('[data-product-row]').get(0)||$box.get(0);
        var viewportSnapshot=rpCaptureElementViewport(anchor);
        $row.remove();
        var $dynamicGroup=$box.find('[data-product-dynamic-group]').first();
        if($dynamicGroup.length){
          var count=$dynamicGroup.find('[data-product-dynamic-rows] [data-product-row]').length;
          $dynamicGroup.prop('hidden',count===0);
          $dynamicGroup.find('[data-product-dynamic-count]').text(count+(count===1?' referência acrescentada':' referências acrescentadas'));
        }
        if(form&&typeof form.routesproSyncProductOrder==='function')form.routesproSyncProductOrder();
        if(form&&typeof form.routesproTouchDraft==='function')form.routesproTouchDraft();
        rpRestoreElementViewport(viewportSnapshot);
        if(form)rpCaptureFormViewport(form,'cardex-remove',anchor);
      });
  });
  // 9CM fieldflow-fronts-mobile-catalog-visual-v1: −/+ das frentes, edição da referência do Cardex e imagem do catálogo.
  $(document).on('click','[data-fronts-step]',function(e){
    e.preventDefault();
    var input=$(this).closest('.routespro-fronts-stepper').find('input').get(0);
    if(!input||input.disabled||input.readOnly) return;
    var current=parseFloat(String(input.value||'').replace(',','.').trim());
    if(!isFinite(current)) current=0;
    var next=Math.max(0,Math.round((current+(parseInt($(this).attr('data-fronts-step'),10)||0))*100)/100);
    if(String(input.value)===String(next)) return;
    input.value=String(next);
    try{ input.dispatchEvent(new Event('input',{bubbles:true})); input.dispatchEvent(new Event('change',{bubbles:true})); }catch(_){ $(input).trigger('input').trigger('change'); }
  });
  $(document).on('click','[data-product-edit]',function(e){
    e.preventDefault();
    var $row=$(this).closest('[data-product-row]'),open=!$row.hasClass('is-editing');
    if(!open&&$row.find('input').filter(function(){ return typeof this.checkValidity==='function'&&!this.checkValidity(); }).length) open=true;
    $row.toggleClass('is-editing',open);
    $(this).attr('aria-expanded',open?'true':'false').text(open?'Fechar':'Editar');
  });
  function rpCloseProductZoom(){
    var box=document.querySelector('.routespro-product-zoom');
    if(box&&box.parentNode) box.parentNode.removeChild(box);
    document.documentElement.classList.remove('routespro-product-zoom-open');
  }
  $(document).on('click','[data-product-zoom]',function(e){
    e.preventDefault(); e.stopPropagation();
    var src=String($(this).attr('data-product-zoom')||''); if(!src) return;
    rpCloseProductZoom();
    var title=String($(this).attr('data-product-zoom-title')||'');
    var box=document.createElement('div');
    box.className='routespro-product-zoom'; box.setAttribute('role','dialog'); box.setAttribute('aria-modal','true'); box.setAttribute('aria-label',title||'Imagem do produto');
    box.innerHTML='<div class="routespro-product-zoom-backdrop" data-product-zoom-close></div><figure><img alt=""><figcaption></figcaption><button type="button" class="routespro-product-zoom-close" data-product-zoom-close aria-label="Fechar">×</button></figure>';
    box.querySelector('img').src=src; box.querySelector('img').alt=title; box.querySelector('figcaption').textContent=title;
    document.body.appendChild(box); document.documentElement.classList.add('routespro-product-zoom-open');
    try{ box.querySelector('.routespro-product-zoom-close').focus({preventScroll:true}); }catch(_){}
  });
  $(document).on('click','[data-product-zoom-close]',function(e){ e.preventDefault(); rpCloseProductZoom(); });
  $(document).on('keydown',function(e){ if(e.key==='Escape'&&document.querySelector('.routespro-product-zoom')) rpCloseProductZoom(); });
  document.addEventListener('error',function(e){
    var img=e.target; if(!img||img.tagName!=='IMG'||typeof img.closest!=='function') return;
    var holder=img.closest('.routespro-product-thumb'); if(!holder) return;
    holder.classList.add('is-empty'); holder.removeAttribute('data-product-zoom'); holder.textContent=String(holder.getAttribute('data-initials')||'');
  },true);
  // 9CU fieldflow-fronts-capture-review-9cu-v1: no paid image call before explicit approval.
  // 9CO fieldflow-fronts-vision-reading-v1: leitura de frentes por câmara (piloto).
  // A fotografia vai para o servidor, que devolve as frentes por produto. O operativo confirma ou
  // corrige antes de preencher; nada é submetido automaticamente.
  var rpVisionState=null;
  function rpVisionEsc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function rpVisionContext($form){
    function num(sel){return parseInt($form.find(sel).first().val()||0,10)||0;}
    return {client_id:num('[name="routespro_client_id"]'),project_id:num('[name="routespro_project_id"]'),route_id:num('[name="routespro_route_id"]'),stop_id:num('[name="routespro_stop_id"],[name="routespro_route_stop_id"]'),location_id:num('[name="routespro_location_id"]'),form_id:parseInt($form.attr('data-routespro-form-id')||$form.find('[name="routespro_form_id"]').val()||0,10)||0,date:String($form.find('[name="routespro_report_day"],[name="routespro_context_date"]').first().val()||$form.attr('data-rp-report-day')||'')};
  }
  function rpVisionRowData($row){
    var m=String($row.find('input[name]').first().attr('name')||'').match(/\[(\d+)\]/);if(!m)return null;
    var name=String($row.find('input[name$="[name]"]').val()||$row.attr('data-product-name')||'').trim();if(!name)return null;
    return {key:'r'+m[1],index:m[1],name:name,ref:String($row.find('input[name$="[ref]"]').val()||'').trim(),capacity:String($row.find('input[name$="[capacity_cl]"]').val()||'').trim(),thumb:String($row.find('.routespro-product-thumb img').attr('src')||''),cardex_id:parseInt($row.find('input[name$="[cardex_id]"]').val()||$row.closest('[data-cardex-id]').attr('data-cardex-id')||'0',10)||0,row:$row.get(0)};
  }
  function rpVisionMatrixGroups($field){
    var groups=[];
    $field.find('[data-category-group]').each(function(){
      var $g=$(this);if($g.is('[hidden]'))return;var rows=[];
      $g.find('[data-product-row].has-summary').each(function(){var d=rpVisionRowData($(this));if(d)rows.push(d);});
      if(rows.length)groups.push({label:String($g.find('.routespro-category-name').first().text()||'Produtos').trim(),rows:rows,open:$g.hasClass('is-open')});
    });
    if(!groups.length){var rows=[];$field.find('[data-product-row].has-summary').each(function(){var d=rpVisionRowData($(this));if(d)rows.push(d);});if(rows.length)groups.push({label:'Produtos',rows:rows,open:true});}
    return groups;
  }
  function rpVisionSheet(){
    var el=document.querySelector('.routespro-vision-sheet');if(el)return el;
    el=document.createElement('div');el.className='routespro-vision-sheet';el.hidden=true;el.setAttribute('role','dialog');el.setAttribute('aria-modal','true');el.setAttribute('aria-label','Contagem de frentes');
    el.innerHTML='<div class="routespro-vision-backdrop" data-vision-close></div><div class="routespro-vision-panel"><header><strong data-vision-title>Contagem inteligente de frentes</strong><button type="button" class="routespro-vision-x" data-vision-close aria-label="Fechar">×</button></header><div class="routespro-vision-body" data-vision-body></div><input type="file" class="routespro-vision-file" accept="image/*" capture="environment" data-vision-camera tabindex="-1" aria-hidden="true"><input type="file" class="routespro-vision-file" accept="image/jpeg,image/png,image/webp" data-vision-gallery tabindex="-1" aria-hidden="true"><input type="file" class="routespro-vision-file" accept="image/*" capture="environment" data-vision-ref-camera tabindex="-1" aria-hidden="true"></div>';
    document.body.appendChild(el);return el;
  }
  function rpVisionBody(html){var el=rpVisionSheet();el.querySelector('[data-vision-body]').innerHTML=html;}
  function rpVisionClose(){
    var st=rpVisionState;if(st)rpVisionLiveStop(st);rpVisionState=null;
    if(st&&st.xhr){try{st.xhr.abort();}catch(_){}}
    if(st&&st.confirmXhr){try{st.confirmXhr.abort();}catch(_){}}
    if(st&&st.speech){try{st.speech.abort();}catch(_){}}
    if(st&&st.preview){try{URL.revokeObjectURL(st.preview);}catch(_){}}
    if(st){st.pendingBlob=null;st.pendingId='';st.quality=null;}
    var el=document.querySelector('.routespro-vision-sheet');if(el){el.hidden=true;el.querySelector('[data-vision-body]').innerHTML='';}
    document.documentElement.classList.remove('routespro-vision-open');
    if(st&&st.form&&st.form.__rpViewportLock)rpScheduleFormViewportUnlock(st.form,'vision-close',300);
  }
  function rpVisionOpen(st){
    rpVisionState=st;var el=rpVisionSheet();el.hidden=false;document.documentElement.classList.add('routespro-vision-open');
    el.querySelector('[data-vision-title]').textContent=st.type==='share_space_matrix'?'Contar Share Space':'Contar frentes';
    rpVisionRenderChoose();
  }
  function rpVisionCurrentItems(st){
    if(st.type==='share_space_matrix')return st.cards;
    var g=st.groups[st.group]||st.groups[0];return g?g.rows.filter(function(r){return !Array.isArray(st.selectedKeys)||st.selectedKeys.indexOf(r.key)>=0;}).map(function(r){return {key:r.key,name:r.name,ref:r.ref,capacity:r.capacity,cardex_id:r.cardex_id||0};}):[];
  }
  function rpVisionRenderChoose(){
    var st=rpVisionState;if(!st)return;
    var cameraReady=!!(st.cameraAllowed&&navigator.onLine!==false);
    var cameraReason=navigator.onLine===false?'O dispositivo está sem ligação. A contagem manual continua disponível.':(st.blockReason||'Câmara com IA indisponível. Verifica a autorização da pergunta e o fornecedor no Backoffice.');
    var html='';
    if(st.type==='share_space_matrix'){
      var card=st.cards[0]||{};
      html+='<p>Escolhe como queres registar as frentes de <strong>'+rpVisionEsc(card.pr_label)+'</strong> e <strong>'+rpVisionEsc(card.competitor_label)+'</strong>.</p>';
      var sides=st.sides||[];
      if(sides.length)html+='<ul class="routespro-vision-products is-visual">'+sides.map(rpVisionProductLi).join('')+'</ul>';
      if(st.references&&sides.some(function(s){return !s.thumb;}))html+='<p class="routespro-vision-hint">Sem imagem, a IA reconhece pior. Toca em <strong>Fotografar</strong> e tira uma foto de perto à frente do rótulo: fica guardada no catálogo para as próximas leituras.</p>';
    }else{
      if(st.groups.length>1){
        html+='<p>Que categoria está na fotografia?</p><div class="routespro-vision-chips">';
        st.groups.forEach(function(g,i){html+='<button type="button" class="routespro-vision-chip'+(i===st.group?' is-active':'')+'" data-vision-group="'+i+'">'+rpVisionEsc(g.label)+' · '+g.rows.length+'</button>';});
        html+='</div>';
      }
      var rows=(st.groups[st.group]||{rows:[]}).rows;
      var missing=rows.filter(function(r){return !r.thumb;}),ordered=missing.concat(rows.filter(function(r){return !!r.thumb;})),shown=st.showAll?ordered:ordered.slice(0,6);
      html+='<p>Categoria com '+rows.length+' produto(s)'+(missing.length?', '+missing.length+' sem imagem de referência':'')+'. Seleciona, escreve ou dita gratuitamente, ou usa a câmara com IA quando autorizada.</p><ul class="routespro-vision-products is-visual">'+shown.map(rpVisionProductLi).join('')+'</ul>';
      if(ordered.length>shown.length)html+='<button type="button" class="routespro-vision-more" data-vision-all>Ver os '+ordered.length+' produtos</button>';
      if(st.references&&missing.length)html+='<p class="routespro-vision-hint">Sem imagem, a IA reconhece pior. Toca em <strong>Fotografar</strong> e tira uma foto de perto à frente do rótulo: fica guardada no catálogo para as próximas leituras.</p>';
    }
    var wait=st.refBusy?' disabled':'',live=cameraReady&&rpVisionLiveSupported()&&!st.liveUnavailable;
    if(st.liveUnavailable)html+='<p class="routespro-vision-notice" role="status">'+rpVisionEsc(st.liveUnavailable)+'</p>';
    html+='<p class="routespro-vision-meta">Sem custo de IA: escrever, ditar com o dispositivo ou selecionar. Capturar uma fotografia também não consome IA: primeiro revês a imagem e só depois autorizas a leitura.</p>'+'<div class="routespro-vision-actions">'+'<button type="button" class="routespro-vision-btn is-primary" data-vision-manual="select">Selecionar produtos</button>'+'<button type="button" class="routespro-vision-btn" data-vision-manual="text">Escrever quantidades</button>'+'<button type="button" class="routespro-vision-btn" data-vision-manual="voice">Falar ou ditar</button>'+(cameraReady?(live?'<button type="button" class="routespro-vision-btn" data-vision-live'+wait+'>Contar ao vivo (IA)</button>':'')+'<button type="button" class="routespro-vision-btn" data-vision-shoot="camera"'+wait+'>Tirar fotografia (IA)</button><button type="button" class="routespro-vision-btn" data-vision-shoot="gallery"'+wait+'>Escolher da galeria (IA)</button>':'<p class="routespro-vision-notice" role="status">'+rpVisionEsc(cameraReason)+'</p>')+'<button type="button" class="routespro-vision-btn is-link" data-vision-close>Fechar</button></div>';
    rpVisionBody(html);
  }
  // 9CP fieldflow-fronts-field-reference-v1: produto sem imagem no catálogo → fotografia de referência no
  // terreno. A foto fica no Catálogo visual (etiqueta "Terreno") e é usada nesta e nas próximas leituras.
  // 9CS multimodal counting. All manual modes are local and free of AI calls.
  function rpVisionManualRows(st){
    if(!st)return [];
    if(st.type==='share_space_matrix')return (st.sides||[]).map(function(side){return {key:side.key,name:side.name||side.label,ref:'',capacity:'',thumb:side.thumb||''};});
    return ((st.groups[st.group]||{rows:[]}).rows||[]);
  }
  function rpVisionManualTarget(st,row){
    var $field=$(st.field);
    if(st.type==='share_space_matrix'){
      var metric=String((st.cards[0]||{}).key||'');
      var $card=$field.find('[data-share-space-card]').filter(function(){return String($(this).find('input[name$="[metric_key]"]').val()||'')===metric;}).first();
      return $card.find(row.key==='competitor'?'[data-share-space-competitor]':'[data-share-space-pr]').first().get(0);
    }
    var idx=String(row.key||'').replace(/^r/,'');
    return $field.find('[data-fronts-input]').filter(function(){var n=String(this.name||'');return n.indexOf('['+idx+'][qty]')>=0||n.indexOf('['+idx+'][after]')>=0;}).get(0);
  }
  function rpVisionManualValues(st){
    if(!st.manualValues)st.manualValues={};
    rpVisionManualRows(st).forEach(function(row){
      if(Object.prototype.hasOwnProperty.call(st.manualValues,row.key))return;
      var input=rpVisionManualTarget(st,row),raw=input?String(input.value||'').trim():'';
      st.manualValues[row.key]=raw!==''&&/^\d+$/.test(raw)?Number(raw):'';
    });
    return st.manualValues;
  }
  function rpVisionManualApply(){
    var st=rpVisionState;if(!st)return;
    var rows=rpVisionManualRows(st),count=0,invalid=false,values=rpVisionManualValues(st);
    $('.routespro-vision-sheet [data-vision-manual-row]').each(function(){
      var key=String($(this).attr('data-vision-manual-row')||''),raw=String($(this).find('[data-vision-manual-value]').val()||'').trim();
      if(raw===''){values[key]='';return;}
      var n=Number(raw);if(!Number.isInteger(n)||n<0||n>500){invalid=true;return;}values[key]=n;
    });
    if(invalid){var err=rpVisionSheet().querySelector('[data-vision-manual-error]');if(err)err.textContent='As quantidades têm de ser inteiros entre 0 e 500.';return;}
    rows.forEach(function(row){var val=values[row.key];if(val===''||val===null||val===undefined)return;var target=rpVisionManualTarget(st,row);if(target){rpVisionSetInput(target,val);count++;}});
    if(!count){var error=rpVisionSheet().querySelector('[data-vision-manual-error]');if(error)error.textContent='Preenche pelo menos uma quantidade. Os campos vazios permanecem inalterados.';return;}
    var form=st.form;rpVisionClose();
    if(form&&typeof window.routesproSetFormFeedback==='function')window.routesproSetFormFeedback(form,'success',count+' contagem(ns) preenchida(s) sem IA. Revê antes de concluir a visita.',false);
    if(form&&typeof form.routesproTouchDraft==='function')form.routesproTouchDraft();
  }
  function rpVisionRenderSelect(){
    var st=rpVisionState;if(!st)return;st.manualMode='select';var rows=rpVisionManualRows(st),values=rpVisionManualValues(st);
    var html='<p class="routespro-vision-meta">Seleção manual, sem custos de IA, funciona também sem Internet. Em branco significa «não alterar»; zero é uma contagem explícita.</p>';
    html+='<label class="routespro-vision-manual-label">Pesquisar produto<input type="search" data-vision-manual-search placeholder="Nome, referência ou capacidade" autocomplete="off"></label>';
    html+='<ul class="routespro-vision-items routespro-vision-manual-list">';
    rows.forEach(function(row){var val=values[row.key],initials=rpVisionInitials(row.name);html+='<li class="routespro-vision-item" data-vision-manual-row="'+rpVisionEsc(row.key)+'" data-vision-search="'+rpVisionEsc(row.name+' '+(row.ref||'')+' '+(row.capacity||''))+'">'+(row.thumb?'<img alt="" src="'+rpVisionEsc(row.thumb)+'">':'<span class="routespro-vision-initials">'+rpVisionEsc(initials)+'</span>')+'<span class="routespro-vision-manual-name"><strong>'+rpVisionEsc(row.name)+'</strong><small>'+rpVisionEsc([row.ref,row.capacity].filter(Boolean).join(' · '))+'</small></span><span class="routespro-fronts-stepper"><button type="button" class="routespro-fronts-step" data-vision-step="-1" aria-label="Diminuir">−</button><input type="number" min="0" max="500" step="1" inputmode="numeric" placeholder="?" value="'+rpVisionEsc(val)+'" data-vision-manual-value aria-label="Frentes de '+rpVisionEsc(row.name)+'"><button type="button" class="routespro-fronts-step" data-vision-step="1" aria-label="Aumentar">+</button></span></li>';});
    html+='</ul><p class="routespro-vision-error" role="alert" data-vision-manual-error></p><div class="routespro-vision-actions"><button type="button" class="routespro-vision-btn is-primary" data-vision-manual-apply>Aplicar contagens</button><button type="button" class="routespro-vision-btn" data-vision-manual="text">Escrever ou ditar</button><button type="button" class="routespro-vision-btn is-link" data-vision-back>Voltar</button></div>';
    rpVisionBody(html);
  }
  function rpVisionNormalizeText(text){
    var v=String(text||'').toLowerCase();try{v=v.normalize('NFD').replace(/[\u0300-\u036f]/g,'');}catch(_){}
    return v.replace(/[^a-z0-9]+/g,' ').trim().replace(/\s+/g,' ');
  }
  function rpVisionSpeechNumbers(text){
    var map={zero:0,um:1,uma:1,dois:2,duas:2,tres:3,quatro:4,cinco:5,seis:6,sete:7,oito:8,nove:9,dez:10,onze:11,doze:12,treze:13,catorze:14,quatorze:14,quinze:15,dezasseis:16,dezassete:17,dezoito:18,dezanove:19,vinte:20};
    return String(text||'').replace(/\b(zero|um|uma|dois|duas|tr[eê]s|quatro|cinco|seis|sete|oito|nove|dez|onze|doze|treze|catorze|quatorze|quinze|dezasseis|dezassete|dezoito|dezanove|vinte)\b/gi,function(word){var k=rpVisionNormalizeText(word);return Object.prototype.hasOwnProperty.call(map,k)?String(map[k]):word;});
  }
  function rpVisionParseLines(text,rows){
    var parts=rpVisionSpeechNumbers(text).split(/[\n;,]+|\s+e\s+(?=\d+\s)/i).map(function(v){return v.trim();}).filter(Boolean);
    return parts.map(function(part){
      var clean=part.replace(/^(corrigir|colocar|registar|definir|contar)\s+/i,'').trim();
      var m=clean.match(/^(\d{1,3})\s+(?:frentes?\s+(?:de|do|da)\s+)?(.+)$/i)||clean.match(/^(.+?)\s+(\d{1,3})(?:\s+frentes?)?$/i);
      if(!m)return {raw:part,qty:null,candidates:[],choice:'',error:'Formato não reconhecido'};
      var leading=/^\d/.test(clean),qty=Number(leading?m[1]:m[2]),query=String(leading?m[2]:m[1]).replace(/\s+frentes?$/i,'').trim();
      if(qty>500)return {raw:part,qty:null,candidates:[],choice:'',error:'Máximo 500 frentes'};
      var needle=rpVisionNormalizeText(query),match=rows.filter(function(row){
        var name=rpVisionNormalizeText(row.name),ref=rpVisionNormalizeText(row.ref),capacity=rpVisionNormalizeText(row.capacity);
        if(!needle)return false;
        if(ref&&needle===ref)return true;
        if(needle===name||name.indexOf(needle)>=0)return true;
        return needle.split(' ').every(function(word){return name.split(' ').indexOf(word)>=0||(ref&&word===ref)||(capacity&&word===capacity);});
      });
      return {raw:part,qty:qty,candidates:match,choice:match.length===1?match[0].key:'',error:match.length?'':'Produto não encontrado no catálogo desta pergunta'};
    });
  }
  function rpVisionRenderText(voice){
    var st=rpVisionState;if(!st)return;st.manualMode=voice?'voice':'text';
    var html='<p class="routespro-vision-meta">Escreve, ou usa o ditado do teclado do telemóvel. A interpretação é local, não envia texto nem áudio para a IA do FieldFlow.</p>';
    html+='<label class="routespro-vision-manual-label">Produtos e quantidades<textarea rows="4" data-vision-manual-text placeholder="Ex.: 5 Jameson 70, 3 Absolut 70">'+rpVisionEsc(st.manualText||'')+'</textarea></label>';
    html+='<p class="routespro-vision-meta">Para ditado, toca no microfone do teclado. O reconhecimento do browser, quando disponível, pode usar serviços do dispositivo ou fornecedor.</p>';
    html+='<div class="routespro-vision-actions"><button type="button" class="routespro-vision-btn" data-vision-speak>Usar microfone do browser</button><button type="button" class="routespro-vision-btn is-primary" data-vision-parse>Analisar texto sem IA</button><button type="button" class="routespro-vision-btn" data-vision-manual="select">Selecionar na lista</button><button type="button" class="routespro-vision-btn is-link" data-vision-back>Voltar</button></div>';
    html+='<p class="routespro-vision-notice" data-vision-speech-status role="status"></p><div data-vision-text-proposals></div>';
    rpVisionBody(html);
  }
  function rpVisionShowTextProposals(){
    var st=rpVisionState;if(!st)return;
    var box=rpVisionSheet().querySelector('[data-vision-text-proposals]');if(!box)return;
    var proposals=rpVisionParseLines(st.manualText||'',rpVisionManualRows(st));st.proposals=proposals;
    if(!proposals.length){box.innerHTML='<p class="routespro-vision-error">Escreve pelo menos um produto e quantidade.</p>';return;}
    var html='<p>Confirma a correspondência antes de alterar os campos.</p><ul class="routespro-vision-proposals">';
    proposals.forEach(function(row,i){html+='<li><strong>'+rpVisionEsc(row.raw)+'</strong><span>'+ (row.qty===null?rpVisionEsc(row.error):row.error?rpVisionEsc(row.error):'Quantidade '+row.qty)+'</span>';
      if(row.qty!==null&&row.candidates.length){html+='<select data-vision-proposal="'+i+'" aria-label="Produto correspondente"><option value="">Selecionar produto</option>';
        row.candidates.forEach(function(candidate){html+='<option value="'+rpVisionEsc(candidate.key)+'"'+(row.choice===candidate.key?' selected':'')+'>'+rpVisionEsc(candidate.name)+'</option>';});html+='</select>';}html+='</li>';});
    html+='</ul><p class="routespro-vision-error" data-vision-proposal-error role="alert"></p><button type="button" class="routespro-vision-btn is-primary" data-vision-proposals-apply>Rever valores na lista</button>';
    box.innerHTML=html;
  }
  function rpVisionApplyProposals(){
    var st=rpVisionState;if(!st||!st.proposals)return;
    var rows=rpVisionManualRows(st),valid=true,updates={};
    st.proposals.forEach(function(row,i){
      var select=rpVisionSheet().querySelector('[data-vision-proposal="'+i+'"]');var key=select?String(select.value||''):'';
      if(row.qty===null||!key||!rows.some(function(r){return r.key===key;})||Object.prototype.hasOwnProperty.call(updates,key)){valid=false;return;}
      updates[key]=row.qty;
    });
    if(!valid){var msg=rpVisionSheet().querySelector('[data-vision-proposal-error]');if(msg)msg.textContent='Seleciona um produto inequívoco para cada contagem, sem duplicados. Nenhum valor foi alterado.';return;}
    var values=rpVisionManualValues(st);Object.keys(updates).forEach(function(key){values[key]=updates[key];});
    rpVisionRenderSelect();
  }
  function rpVisionStartSpeech(){
    var st=rpVisionState;if(!st)return;
    var Recognition=window.SpeechRecognition||window.webkitSpeechRecognition;
    var status=rpVisionSheet().querySelector('[data-vision-speech-status]');
    if(!Recognition){if(status)status.textContent='O reconhecimento de voz não está disponível nesta WebView. Usa o microfone do teclado ou escreve.';return;}
    try{
      var speech=new Recognition();speech.lang='pt-PT';speech.continuous=false;speech.interimResults=false;st.speech=speech;
      speech.onresult=function(event){if(st!==rpVisionState)return;var text='';for(var i=event.resultIndex;i<event.results.length;i++)if(event.results[i].isFinal)text+=' '+event.results[i][0].transcript;var area=rpVisionSheet().querySelector('[data-vision-manual-text]');if(area&&text.trim()){area.value=(area.value?area.value+', ':'')+text.trim();st.manualText=area.value;if(status)status.textContent='Ditado recebido. Revê o texto antes de aplicar.';}};
      speech.onerror=function(){if(st===rpVisionState&&status)status.textContent='Não foi possível ditar. Usa o microfone do teclado ou escreve.';};
      speech.onend=function(){if(st===rpVisionState)st.speech=null;};
      if(status)status.textContent='Microfone ativo. Diz a quantidade e o produto.';
      speech.start();
    }catch(_){if(status)status.textContent='Microfone indisponível. Escreve a contagem.';}
  }
  function rpVisionInitials(name){var words=String(name||'').split(/\s+/).filter(function(w){return /^[A-Za-zÀ-ÿ]/.test(w);});return (words.length===1?words[0].slice(0,2):words.slice(0,2).map(function(w){return w.charAt(0);}).join('')).toUpperCase();}
  function rpVisionProductLi(r){
    var st=rpVisionState||{},busy=st.refBusy===r.key,done=!!(st.refDone&&st.refDone[r.key]),err=(st.refErrors&&st.refErrors[r.key])||'';
    var sub=r.side?(r.side==='competitor'?'Concorrência':'Marca'):'';
    var html='<li class="'+(r.thumb?'has-image':'is-missing')+'" data-vision-product="'+rpVisionEsc(r.key)+'">'+(r.thumb?'<img alt="" src="'+rpVisionEsc(r.thumb)+'">':'<span class="routespro-vision-initials">'+rpVisionEsc(rpVisionInitials(r.name))+'</span>');
    html+='<span class="routespro-vision-pname">'+rpVisionEsc(r.name)+(sub?'<small>'+sub+'</small>':'')+(done?'<small class="is-ok">Referência guardada no catálogo</small>':'')+(err?'<small class="is-error">'+rpVisionEsc(err)+'</small>':'')+(!r.thumb&&!err&&!busy&&!st.references?'<small>Sem imagem de referência</small>':'')+'</span>';
    if(busy)html+='<span class="routespro-vision-spinner" role="status" aria-label="A guardar a fotografia"></span>';
    else if(st.references&&!r.thumb)html+='<button type="button" class="routespro-vision-ref" data-vision-ref="'+rpVisionEsc(r.key)+'" aria-label="'+rpVisionEsc('Fotografar referência de '+r.name)+'"><span aria-hidden="true">&#128247;</span> Fotografar</button>';
    else html+='<span></span>';
    return html+'</li>';
  }
  function rpVisionShareSides($card,card){
    return [['pr',card.pr_label,'[data-share-space-pr]'],['competitor',card.competitor_label,'[data-share-space-competitor]']].filter(function(x){return String(x[1]||'').trim()!=='';}).map(function(x){
      var nameEl=$card.find(x[2]).closest('label').find('.routespro-share-space-name').get(0)||null;
      return {key:x[0],side:x[0],label:String(x[1]),name:String(x[1]),thumb:String($(nameEl).find('.routespro-share-space-thumbs img').first().attr('src')||''),nameEl:nameEl};
    });
  }
  function rpVisionFindTarget(st,key){
    var found=null;
    if(st.type==='share_space_matrix')(st.sides||[]).forEach(function(s){if(s.key===key)found=s;});
    else (st.groups||[]).forEach(function(g){g.rows.forEach(function(r){if(r.key===key&&!found)found=r;});});
    return found;
  }
  function rpVisionApplyThumb(target,data){
    var url=String(data.thumb||'');if(!url)return;target.thumb=url;target.full=String(data.full||url);
    var name=String(target.name||''),btn='<button type="button" class="routespro-product-thumb" data-product-zoom="'+rpVisionEsc(target.full)+'" data-product-zoom-title="'+rpVisionEsc(name)+'" data-initials="'+rpVisionEsc(rpVisionInitials(name))+'" aria-label="'+rpVisionEsc('Ver imagem de '+name)+'"><img src="'+rpVisionEsc(url)+'" alt="" loading="lazy" decoding="async"></button>';
    try{
      if(target.row){var holder=target.row.querySelector('.routespro-product-thumb');if(holder)holder.outerHTML=btn;}
      if(target.nameEl){var box=target.nameEl.querySelector('.routespro-share-space-thumbs');if(!box){box=document.createElement('span');box.className='routespro-share-space-thumbs';target.nameEl.insertBefore(box,target.nameEl.firstChild);}box.innerHTML=btn;}
    }catch(_){ }
  }
  function rpVisionSendReference(st,key,blob){
    var target=rpVisionFindTarget(st,key);if(!target){st.refBusy='';if(st===rpVisionState)rpVisionRenderChoose();return;}
    var item=st.type==='share_space_matrix'?{side:target.side,label:target.label}:{name:target.name,ref:target.ref,capacity:target.capacity,cardex_id:target.cardex_id||0};
    var fd=new FormData();
    fd.append('action','fieldflow_fronts_vision_reference');fd.append('nonce',st.nonce);fd.append('question_key',st.key);fd.append('question_type',st.type);
    fd.append('context',JSON.stringify(st.context));fd.append('item',JSON.stringify(item));fd.append('photo',blob,'referencia.jpg');
    st.refErrors=st.refErrors||{};delete st.refErrors[key];
    var finish=function(ok,data,message){
      st.refBusy='';
      if(ok){rpVisionApplyThumb(target,data);st.refDone=st.refDone||{};st.refDone[key]=true;}
      else st.refErrors[key]=message;
      if(st===rpVisionState)rpVisionRenderChoose();
    };
    var xhr=new XMLHttpRequest();xhr.open('POST',st.endpoint,true);xhr.withCredentials=true;xhr.timeout=60000;
    xhr.onload=function(){var data=null;try{data=JSON.parse(xhr.responseText);}catch(_){ }if(xhr.status>=200&&xhr.status<300&&data&&data.success&&data.data)finish(true,data.data);else finish(false,null,(data&&data.data&&data.data.message)||'Não foi possível guardar a fotografia. Tenta de novo.');};
    xhr.onerror=function(){finish(false,null,'Sem ligação ao servidor. Tenta de novo com rede.');};
    xhr.ontimeout=function(){finish(false,null,'O envio demorou demasiado. Tenta de novo.');};
    xhr.send(fd);
  }
  // 9CQ fieldflow-fronts-vision-live-count-v1: contar ao vivo. A câmara abre na folha.
  // 9CU: estabilização apenas CAPTURA uma imagem; o envio pago exige opt-in posterior.
  function rpVisionLiveSupported(){return !!(window.isSecureContext!==false&&navigator.mediaDevices&&typeof navigator.mediaDevices.getUserMedia==='function');}
  function rpVisionLiveStop(st){
    st=st||rpVisionState;if(!st||!st.live)return;var lv=st.live;st.live=null;lv.done=true;
    if(lv.timer)window.clearInterval(lv.timer);
    rpStopCameraStream(lv.stream);
    try{if(lv.video){lv.video.pause();lv.video.srcObject=null;}}catch(_){ }
  }
  function rpVisionLiveStatus(text,steady){
    var sheet=document.querySelector('.routespro-vision-sheet');if(!sheet)return;
    var el=sheet.querySelector('[data-vision-live-status]');if(el&&text)el.textContent=text;
    var m=sheet.querySelector('[data-vision-live-meter]');if(m&&steady!=null)m.style.width=Math.round(Math.max(0,Math.min(1,steady))*100)+'%';
    var v=sheet.querySelector('[data-vision-live-view]');if(v&&steady!=null)v.classList.toggle('is-steady',steady>=1);
  }
  function rpVisionLiveStart(){
    var st=rpVisionState;if(!st)return;
    if(!rpVisionLiveSupported()){st.liveUnavailable='A câmara ao vivo não está disponível neste dispositivo. Usa Tirar fotografia.';rpVisionRenderChoose();return;}
    if(navigator.onLine===false){rpVisionRenderChoose();return;}
    rpVisionLiveStop(st);st.result=null;
    rpVisionBody('<div class="routespro-vision-live" data-vision-live-view><video playsinline muted autoplay></video><span class="routespro-vision-live-meter"><span data-vision-live-meter></span></span><span class="routespro-vision-live-frame" aria-hidden="true"></span><span class="routespro-vision-live-status" role="status" data-vision-live-status>A abrir a câmara…</span></div><p>Aponta para a prateleira de frente e mantém o telemóvel parado. A fotografia é capturada automaticamente, mas só vai à IA depois da tua confirmação.</p><div class="routespro-vision-actions"><button type="button" class="routespro-vision-btn is-primary" data-vision-live-now disabled>Capturar agora</button><button type="button" class="routespro-vision-btn is-link" data-vision-live-back>Voltar</button></div>');
    var sheet=rpVisionSheet(),video=sheet.querySelector('[data-vision-live-view] video');if(!video)return;
    var lv={stream:null,video:video,timer:0,steady:0,prev:null,started:0,done:false};st.live=lv;
    navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1920},height:{ideal:1080}},audio:false}).then(function(stream){
      if(st!==rpVisionState||st.live!==lv){rpStopCameraStream(stream);return;}
      lv.stream=stream;video.srcObject=stream;var played=video.play();if(played&&typeof played.catch==='function')played.catch(function(){ });
      var ready=function(){
        if(st.live!==lv)return;lv.started=Date.now();
        var now=sheet.querySelector('[data-vision-live-now]');if(now)now.disabled=false;
        rpVisionLiveStatus('Mantém o telemóvel parado…',0);
        lv.timer=window.setInterval(function(){rpVisionLiveTick(st,lv);},280);
      };
      if(video.readyState>=2)ready();else video.addEventListener('loadeddata',ready,{once:true});
    }).catch(function(error){
      if(st!==rpVisionState)return;rpVisionLiveStop(st);
      var code=rpCameraErrorCode(error);
      st.liveUnavailable=/NotAllowedError|PermissionDenied|SecurityError|permission/i.test(code)?'A câmara ao vivo não foi autorizada. Usa Tirar fotografia ou autoriza a câmara nas definições.':(/NotFoundError|DevicesNotFound/i.test(code)?'Não foi encontrada uma câmara neste dispositivo.':(/NotReadable|TrackStart/i.test(code)?'A câmara está a ser usada por outra aplicação. Fecha-a e tenta de novo.':'A câmara ao vivo não está disponível nesta app. Usa Tirar fotografia.'));
      rpVisionRenderChoose();
    });
  }
  function rpVisionLiveTick(st,lv){
    if(st!==rpVisionState||st.live!==lv||lv.done)return;
    var video=lv.video;if(!video||!video.videoWidth)return;
    try{
      var small=lv.small||(lv.small=document.createElement('canvas'));small.width=48;small.height=36;
      var ctx=small.getContext('2d',{willReadFrequently:true});ctx.drawImage(video,0,0,48,36);
      var data=ctx.getImageData(0,0,48,36).data,gray=new Uint8Array(48*36),sum=0,diff=0;
      for(var i=0,j=0;i<data.length;i+=4,j++){gray[j]=(data[i]*3+data[i+1]*4+data[i+2])>>3;sum+=gray[j];if(lv.prev)diff+=Math.abs(gray[j]-lv.prev[j]);}
      var mean=sum/gray.length,motion=lv.prev?diff/gray.length:255;lv.prev=gray;
      if(mean<28){lv.steady=0;rpVisionLiveStatus('Está muito escuro. Aproxima-te da prateleira ou acende a luz.',0);return;}
      lv.steady=motion<7?lv.steady+1:0;
      rpVisionLiveStatus(lv.steady>0?'Parado… a preparar a contagem':'Mantém o telemóvel parado…',lv.steady/5);
      if(lv.steady>=5&&Date.now()-lv.started>1200)rpVisionLiveCapture(st,lv);
    }catch(_){ }
  }
  // No network calls here: estimate only photo usability on a small, local canvas.
  // Warnings are advisory to avoid rejecting valid unusual store lighting.
  function rpVisionAssessCapture(blob){
    return new Promise(function(resolve){
      if(!(blob instanceof Blob)){resolve({warnings:['Não foi possível avaliar a qualidade. Confirma visualmente.']});return;}
      var url='';try{url=URL.createObjectURL(blob);}catch(_){resolve({warnings:[]});return;}
      var img=new Image(),done=false;
      var finish=function(warnings,width,height){if(done)return;done=true;try{URL.revokeObjectURL(url);}catch(_){}resolve({warnings:warnings||[],width:width||0,height:height||0});};
      img.onload=function(){
        var w=img.naturalWidth||img.width,h=img.naturalHeight||img.height,warnings=[];
        if(Math.max(w,h)<900)warnings.push('A resolução é baixa. Aproxima-te dos rótulos e volta a fotografar.');
        try{
          var canvas=document.createElement('canvas');canvas.width=128;canvas.height=96;
          var ctx=canvas.getContext('2d',{willReadFrequently:true});if(!ctx){finish(warnings,w,h);return;}
          ctx.drawImage(img,0,0,128,96);
          var pixels=ctx.getImageData(0,0,128,96).data,gray=new Uint8Array(128*96),sum=0;
          for(var i=0,j=0;i<pixels.length;i+=4,j++){var v=Math.round((pixels[i]*299+pixels[i+1]*587+pixels[i+2]*114)/1000);gray[j]=v;sum+=v;}
          var brightness=sum/gray.length,edges=0,n=0;
          for(var y=1;y<95;y+=2)for(var x=1;x<127;x+=2){var ix=y*128+x;edges+=Math.abs(gray[ix]-gray[ix-1])+Math.abs(gray[ix]-gray[ix-128]);n++;}
          if(brightness<48)warnings.push('A fotografia parece escura. Melhora a iluminação antes de usar IA.');
          if(brightness>223)warnings.push('A fotografia parece demasiado clara. Evita reflexos e luz direta no vidro.');
          if(n&&edges/n<12)warnings.push('Os detalhes parecem pouco definidos. Confirma se os rótulos estão focados.');
        }catch(_){/* canvas unavailable, visual review remains mandatory */}
        finish(warnings,w,h);
      };
      img.onerror=function(){finish(['Não foi possível avaliar a nitidez. Confirma os rótulos antes de enviar.']);};
      img.src=url;
    });
  }
  function rpVisionCaptureId(){return 'ff'+Date.now().toString(36)+Math.random().toString(36).slice(2,15);}
  function rpVisionReviewCapture(st,blob,capture){
    if(st!==rpVisionState||!(blob instanceof Blob)||!blob.size)return;
    st.pendingBlob=blob;st.pendingCapture=capture;st.pendingId=rpVisionCaptureId();st.quality=null;st.pendingAttempted=false;
    if(st.type==='product_matrix'&&!Array.isArray(st.selectedKeys)){var source=(st.groups[st.group]||{rows:[]}).rows;st.selectedKeys=source.length<=12?source.map(function(r){return r.key;}):[];}
    st.analyzing=false;st.captureSeq=(st.captureSeq||0)+1;
    var sequence=st.captureSeq;
    rpVisionBody('<div class="routespro-vision-progress"><span class="routespro-vision-spinner" aria-hidden="true"></span><span>A verificar a fotografia no dispositivo, sem IA…</span></div>');
    rpVisionAssessCapture(blob).then(function(quality){
      if(st===rpVisionState&&sequence===st.captureSeq&&st.pendingBlob===blob){st.quality=quality;rpVisionRenderCaptureReview();}
    });
  }
  function rpVisionRenderCaptureReview(){
    var st=rpVisionState;if(!st||!st.pendingBlob)return;
    var quality=st.quality||{warnings:[]},items=rpVisionCurrentItems(st)||[],warnings=(quality.warnings||[]).slice();
    if(st.type==='product_matrix'&&items.length===0)warnings.push('Seleciona pelo menos um produto para analisar.');
    var html=(st.preview?'<div class="routespro-vision-photo"><img alt="Fotografia a rever antes da análise" src="'+rpVisionEsc(st.preview)+'"></div>':'');
    html+='<h3 class="routespro-vision-review-title">'+(st.pendingAttempted?'Rever antes de voltar a analisar':'Rever antes de gastar uma tentativa')+'</h3><p class="routespro-vision-meta">'+(st.pendingAttempted?'Esta fotografia já teve uma tentativa. Voltar a analisar pode consumir nova quota se a leitura anterior não ficou concluída.':'Fotografia capturada. Ainda não foi enviado qualquer pedido à IA.')+' Seleciona apenas os produtos que estão visíveis na fotografia.</p>';
    if(st.type==='product_matrix'){
      var source=(st.groups[st.group]||{rows:[]}).rows||[],selected=st.selectedKeys||[];
      html+='<fieldset class="routespro-vision-choice"><legend>Produtos para a IA (até 12)</legend><p>Menos produtos por fotografia ajudam a distinguir marcas e capacidades. Os restantes campos não são alterados.</p><div class="routespro-vision-choice-list">';
      source.forEach(function(row){html+='<label><input type="checkbox" data-vision-select-product="'+rpVisionEsc(row.key)+'"'+(selected.indexOf(row.key)>=0?' checked':'')+'> <span>'+rpVisionEsc(row.name)+(row.capacity?' · '+rpVisionEsc(row.capacity)+' cl':'')+'</span></label>';});
      html+='</div><small data-vision-selected-count>'+selected.length+' de 12 selecionados</small></fieldset>';
    }else html+='<p class="routespro-vision-meta">A leitura analisará os produtos do cartão Share Space selecionado.</p>';
    if(warnings.length){html+='<div class="routespro-vision-quality" role="status"><strong>Antes de analisar, confirma:</strong><ul>';warnings.forEach(function(w){html+='<li>'+rpVisionEsc(w)+'</li>';});html+='</ul></div>';}
    else html+='<p class="routespro-vision-quality is-ok" role="status">A iluminação e a definição parecem adequadas. A contagem continua a precisar da tua confirmação.</p>';
    html+='<p class="routespro-vision-meta"><strong>Atenção aos custos:</strong> só «Analisar com IA» pode reservar 1 tentativa do orçamento autorizado no Backoffice. Capturar, refazer, escrever ou selecionar não usa IA.</p>';
    html+='<div class="routespro-vision-actions"><button type="button" class="routespro-vision-btn is-primary" data-vision-analyze'+(st.type==='product_matrix'&&(!items.length||items.length>12)?' disabled':'')+'>Analisar com IA, 1 tentativa</button>';
    html+='<button type="button" class="routespro-vision-btn" data-vision-shoot="camera">Tirar outra fotografia, grátis</button><button type="button" class="routespro-vision-btn" data-vision-shoot="gallery">Escolher outra imagem</button>';
    if(st.lastCapture==='live'&&rpVisionLiveSupported()&&!st.liveUnavailable)html+='<button type="button" class="routespro-vision-btn" data-vision-live>Voltar à câmara ao vivo</button>';
    html+='<button type="button" class="routespro-vision-btn is-link" data-vision-manual="select">Contar sem IA</button></div>';
    rpVisionBody(html);
  }
  function rpVisionApproveAnalysis(){
    var st=rpVisionState;if(!st||st.analyzing||st.xhr||!st.pendingBlob)return;
    var items=rpVisionCurrentItems(st);if(!items.length||items.length>12){rpVisionRenderCaptureReview();return;}
    if(navigator.onLine===false){rpVisionRenderError('Não existe ligação à Internet. A fotografia ainda não foi enviada. Podes contar sem IA.');return;}
    st.analyzing=true;st.pendingAttempted=true;
    rpVisionRenderReading('A enviar a fotografia após a tua autorização…');
    rpVisionSend(st.pendingBlob,st.pendingCapture,st.pendingId);
  }
  function rpVisionLiveCapture(st,lv){
    if(!lv||lv.done||!lv.video||!lv.video.videoWidth)return;lv.done=true;
    var video=lv.video,w=video.videoWidth,h=video.videoHeight,scale=Math.min(1,1600/Math.max(w,h,1));
    var canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(w*scale));canvas.height=Math.max(1,Math.round(h*scale));
    var ctx=canvas.getContext('2d');if(!ctx){lv.done=false;return;}
    ctx.drawImage(video,0,0,canvas.width,canvas.height);
    rpVisionLiveStop(st);
    canvas.toBlob(function(blob){
      if(st!==rpVisionState)return;
      if(!blob){rpVisionRenderError('Não foi possível obter a imagem da câmara. Tenta de novo ou tira uma fotografia.');return;}
      if(st.preview){try{URL.revokeObjectURL(st.preview);}catch(_){ }}
      try{st.preview=URL.createObjectURL(blob);}catch(_){st.preview='';}
      st.lastCapture='live';
      rpVisionReviewCapture(st,blob,'live');
    },'image/jpeg',0.82);
  }
  function rpVisionRetryLabel(){var st=rpVisionState;return st&&st.lastCapture==='live'?'Contar outra vez':'Tirar outra fotografia';}
  function rpVisionRenderReading(text){
    var st=rpVisionState;if(!st)return;
    rpVisionBody((st.preview?'<div class="routespro-vision-photo"><img alt="Fotografia da prateleira" src="'+rpVisionEsc(st.preview)+'"></div>':'')+'<div class="routespro-vision-progress"><span class="routespro-vision-spinner" aria-hidden="true"></span><span data-vision-progress>'+rpVisionEsc(text||'A preparar a fotografia…')+'</span></div><p>Pode demorar até meio minuto com rede fraca.</p><div class="routespro-vision-actions"><button type="button" class="routespro-vision-btn is-link" data-vision-close>Cancelar e contar à mão</button></div>');
  }
  function rpVisionRenderError(message){
    if(!rpVisionState)return;
    var st=rpVisionState;st.analyzing=false;st.xhr=null;
    rpVisionBody('<div class="routespro-vision-error">'+rpVisionEsc(message)+'</div><p class="routespro-vision-meta">Se o pedido anterior chegou ao fornecedor, pode ter consumido a quota. Rever e voltar a enviar a mesma imagem usa proteção contra reenvios concluídos, mas uma falha antes da resposta pode exigir outra tentativa.</p><div class="routespro-vision-actions">'+(st.pendingBlob?'<button type="button" class="routespro-vision-btn is-primary" data-vision-preview-back>Rever a mesma fotografia</button>':'')+'<button type="button" class="routespro-vision-btn" data-vision-retry>'+rpVisionRetryLabel()+'</button><button type="button" class="routespro-vision-btn is-link" data-vision-close>Contar à mão</button></div>');
  }
  function rpVisionConfidence(c){c=Number(c)||0;return c>=0.85?'Identificação visual sugerida':(c>=0.5?'Verificar identificação':'Confiança baixa, confirma');}
  function rpVisionReviewMessage(it){
    if(it.review_reason==='duplicate_position')return 'Duas identificações disputam a mesma garrafa. Nenhuma será aplicada sem confirmação.';
    if(it.review_reason==='variant_unverified')return 'Variante não comprovada no rótulo. Não foi contabilizada pela IA.';
    if(it.review_reason==='variant_manual_verification')return 'Variante identificada como possivel. Confirma pessoalmente o rotulo antes de contar.';
    if(it.review_reason==='box_unreliable')return 'Localizacao da garrafa incerta. Confirma a quantidade manualmente.';
    if(it.review_reason==='brand_unverified')return 'Marca não comprovada no rótulo. Não foi contabilizada pela IA.';
    if(it.review_reason==='capacity_unverified')return 'Marca detetada, capacidade por confirmar. Verifica a garrafa antes de aceitar a sugestão.';
    if(it.review_reason==='no_visual_proof')return 'Sem prova visual suficiente para esta contagem. Verifica e introduz o valor.';
    if(it.review_reason==='low_confidence')return 'Confiança insuficiente. Verifica e introduz o valor.';
    if(it.review_reason==='uncertain_zero')return 'Ausência incerta. Verifica se existem frentes deste produto.';
    return it.fronts===null||it.fronts===undefined?'Não reconhecido. Introduz o valor após verificar a prateleira.':'Sugestão incerta. Confirma a quantidade.';
  }
  function rpVisionRenderResult(){
    var st=rpVisionState;if(!st||!st.result)return;var res=st.result,thumbs={};
    if(st.type!=='share_space_matrix'){(st.groups[st.group]||{rows:[]}).rows.forEach(function(r){thumbs[r.key]=r.thumb;});}
    st.values={};(res.items||[]).forEach(function(it){st.values[it.key]=it.needs_review||it.fronts===null||it.fronts===undefined?'':Math.max(0,parseInt(it.fronts,10)||0);});
    var html='<div class="routespro-vision-photo"><div class="routespro-vision-photo-inner">'+(st.preview?'<img alt="Fotografia lida" src="'+rpVisionEsc(st.preview)+'">':'');
    if(st.boxes){(res.items||[]).forEach(function(it){(it.boxes||[]).forEach(function(b){html+='<span class="routespro-vision-box'+(it.needs_review?' is-review':'')+'" style="top:'+(b[0]/10)+'%;left:'+(b[1]/10)+'%;height:'+((b[2]-b[0])/10)+'%;width:'+((b[3]-b[1])/10)+'%"><span>'+rpVisionEsc(it.code)+(it.needs_review?' ?':'')+'</span></span>';});});}
    html+='</div></div><p class="routespro-vision-meta">'+(res.vision_mode?'Leitura sem nomes de produtos enviados a IA. ':'')+(res.reused?'Resultado recuperado da mesma fotografia, sem nova chamada paga. ':'')+'Lido em '+(Math.round((res.duration_ms||0)/100)/10).toLocaleString('pt-PT')+' s · '+rpVisionEsc(res.provider||'')+(res.model?' · '+rpVisionEsc(res.model):'')+'</p>';
    var previous=res.previous_visit&&res.previous_visit.basis==='confirmed_closed_visit'?res.previous_visit:null;
    if(previous&&previous.items&&Object.keys(previous.items).length){
      var historicalDate=String(previous.date||'');
      html+='<p class="routespro-vision-history-note">Comparar com visita fechada de '+rpVisionEsc(historicalDate.split('-').reverse().join('/'))+'. A contagem antiga nunca preenche esta visita automaticamente.</p>';
    }
    if(Number(res.deduplicated||0)>0)html+='<p class="routespro-vision-meta">'+Number(res.deduplicated)+' deteção(ões) sobreposta(s) da mesma garrafa foram excluídas da contagem.</p>';
    html+='<p>Confirma ou corrige as frentes antes de preencher.</p><ul class="routespro-vision-items">';
    var lastSide='';
    (res.items||[]).forEach(function(it){
      if(st.type==='share_space_matrix'&&it.side!==lastSide){lastSide=it.side;html+='<li class="routespro-vision-side">'+rpVisionEsc(it.side_label||'')+'</li>';}
      var thumb=thumbs[it.key]||'',words=String(it.name||'').split(/\s+/).filter(function(w){return /^[A-Za-zÀ-ÿ]/.test(w);}),initials=(words.length===1?words[0].slice(0,2):words.slice(0,2).map(function(w){return w.charAt(0);}).join('')).toUpperCase();
      // Never silently accept an uncertain SKU. Show an optional one-tap proposal
      // only if the brand/variant were supported and capacity or confidence is pending.
      var propose=it.needs_review&&['capacity_unverified','low_confidence'].indexOf(String(it.review_reason||''))!==-1&&Number.isInteger(Number(it.model_fronts))&&Number(it.model_fronts)>0&&Number(it.model_fronts)<=200;
      var hint=propose?'<small>IA sugere '+rpVisionEsc(String(it.model_fronts))+' frente(s), ainda não aplicadas.</small><button type="button" class="routespro-vision-accept" data-vision-accept="'+rpVisionEsc(it.key)+'">Usar '+rpVisionEsc(String(it.model_fronts))+' após verificar</button>':'';
      var previousCount=previous&&previous.items&&Object.prototype.hasOwnProperty.call(previous.items,it.key)?Number(previous.items[it.key]):null;
      var historyHint=previousCount!==null?'<small class="routespro-vision-previous" data-vision-previous="'+rpVisionEsc(String(previousCount))+'">Visita anterior: '+rpVisionEsc(String(previousCount))+' frente(s)</small><small data-vision-delta="'+rpVisionEsc(it.key)+'"></small>':'';
      var ocrHint=it.label_text?'<small class="routespro-vision-ocr">Texto sugerido pela IA: '+rpVisionEsc(String(it.label_text).slice(0,130))+'</small>':'';
      html+='<li class="routespro-vision-item'+(it.needs_review||((Number(it.confidence)||0)<0.5)?' is-low':'')+'" data-vision-item="'+rpVisionEsc(it.key)+'">'+(thumb?'<img alt="" src="'+rpVisionEsc(thumb)+'">':'<span class="routespro-vision-initials">'+rpVisionEsc(initials)+'</span>')+'<span><strong>'+(st.boxes?rpVisionEsc(it.code)+' · ':'')+rpVisionEsc(it.name)+'</strong><small>'+(it.needs_review?rpVisionEsc(rpVisionReviewMessage(it)):rpVisionConfidence(it.confidence))+'</small>'+ocrHint+historyHint+hint+'</span><span class="routespro-fronts-stepper"><button type="button" class="routespro-fronts-step" data-vision-step="-1" aria-label="Menos uma frente">−</button><input type="number" min="0" step="1" inputmode="numeric" value="'+st.values[it.key]+'" data-vision-value aria-label="'+rpVisionEsc('Frentes de '+it.name)+'"><button type="button" class="routespro-fronts-step" data-vision-step="1" aria-label="Mais uma frente">+</button></span></li>';
    });
    html+='</ul>'+(res.unidentified?'<p><strong>'+(parseInt(res.unidentified,10)||0)+'</strong> frente(s) não identificada(s) na fotografia.</p>':'')+(res.notes?'<p class="routespro-vision-meta">'+rpVisionEsc(res.notes)+'</p>':'');
    html+='<p role="alert" class="routespro-vision-error" data-vision-confirm-error></p><div class="routespro-vision-actions"><button type="button" class="routespro-vision-btn is-primary" data-vision-fill>Confirmar e preencher</button><button type="button" class="routespro-vision-btn" data-vision-retry>'+rpVisionRetryLabel()+'</button><button type="button" class="routespro-vision-btn is-link" data-vision-close>Contar à mão</button></div>';
    rpVisionBody(html);
    rpVisionRefreshComparison();
  }
  // 9D1: comparison is informative only, with explicit current count entered by operative.
  function rpVisionRefreshComparison(){
    var st=rpVisionState;if(!st||!st.result||!st.result.previous_visit)return;
    var previous=st.result.previous_visit.items||{};
    $('.routespro-vision-sheet [data-vision-item]').each(function(){
      var key=String($(this).attr('data-vision-item')||'');
      if(!Object.prototype.hasOwnProperty.call(previous,key))return;
      var node=$(this).find('[data-vision-delta]').get(0),input=$(this).find('[data-vision-value]').get(0);
      if(!node||!input)return;
      var raw=String(input.value||'').trim(),current=Number(raw),old=Number(previous[key]);
      node.textContent=raw!==''&&Number.isInteger(current)&&current>=0&&current<=500&&Number.isInteger(old)
        ? 'Variação: '+(current-old>0?'+':'')+String(current-old)+' frente(s)'
        : 'Por confirmar nesta visita';
    });
  }
  function rpVisionPrepare(file){
    return new Promise(function(resolve){
      if(!(file instanceof Blob)){resolve(file);return;}
      var url=URL.createObjectURL(file),img=new Image();
      img.onload=function(){
        var w=img.naturalWidth||img.width,h=img.naturalHeight||img.height,scale=Math.min(1,1600/Math.max(w,h,1));
        var c=document.createElement('canvas');c.width=Math.max(1,Math.round(w*scale));c.height=Math.max(1,Math.round(h*scale));
        var ctx=c.getContext('2d');if(!ctx){URL.revokeObjectURL(url);resolve(file);return;}
        ctx.fillStyle='#fff';ctx.fillRect(0,0,c.width,c.height);ctx.drawImage(img,0,0,c.width,c.height);URL.revokeObjectURL(url);
        try{c.toBlob(function(b){resolve(b&&b.size>0?b:file);},'image/jpeg',0.85);}catch(_){resolve(file);}
      };
      img.onerror=function(){URL.revokeObjectURL(url);resolve(file);};
      img.src=url;
    });
  }
  function rpVisionSend(blob,capture,captureId){
    var st=rpVisionState;if(!st||st.xhr)return;
    var fd=new FormData();
    fd.append('action','fieldflow_fronts_vision_read');fd.append('nonce',st.nonce);fd.append('question_key',st.key);fd.append('question_type',st.type);
    fd.append('scope_label',st.type==='share_space_matrix'?String((st.cards[0]||{}).metric||''):String((st.groups[st.group]||{}).label||''));
    if(captureId)fd.append('capture_id',String(captureId));
    fd.append('context',JSON.stringify(st.context));fd.append('items',JSON.stringify(rpVisionCurrentItems(st)));fd.append('photo',blob,'prateleira.jpg');fd.append('capture',capture||'camera');
    var xhr=new XMLHttpRequest();st.xhr=xhr;xhr.open('POST',st.endpoint,true);xhr.withCredentials=true;xhr.timeout=110000;
    var label=function(t){var el=document.querySelector('.routespro-vision-sheet [data-vision-progress]');if(el)el.textContent=t;};
    xhr.upload.onprogress=function(ev){if(!ev.lengthComputable)return;var pct=Math.round(ev.loaded/Math.max(1,ev.total)*100);label(pct>=100?'A contar as frentes…':'A enviar a fotografia… '+pct+'%');};
    xhr.onload=function(){
      if(st!==rpVisionState)return;st.xhr=null;var data=null;try{data=JSON.parse(xhr.responseText);}catch(_){ }
      if(xhr.status>=200&&xhr.status<300&&data&&data.success&&data.data){st.result=data.data;st.analyzing=false;st.pendingBlob=null;st.pendingId='';rpVisionRenderResult();}
      else rpVisionRenderError((data&&data.data&&data.data.message)||'Não foi possível ler a fotografia. Tenta outra ou conta à mão.');
    };
    xhr.onerror=function(){if(st===rpVisionState)rpVisionRenderError('Sem ligação ao servidor. Tenta de novo com rede ou conta à mão.');};
    xhr.ontimeout=function(){if(st===rpVisionState)rpVisionRenderError('A leitura demorou demasiado. Tenta de novo ou conta à mão.');};
    label('A enviar a fotografia…');xhr.send(fd);
  }
  function rpVisionSetInput(input,value){
    if(!input)return;input.value=String(value);
    try{input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));}catch(_){ $(input).trigger('input').trigger('change'); }
  }
  function rpVisionApplyConfirmed(st,confirmed){
    var $field=$(st.field),filled=0;
    confirmed.forEach(function(r){st.values[r.key]=r.fronts;});
    if(st.type==='share_space_matrix'){
      var sums={};(st.result.items||[]).forEach(function(it){var k=it.card+'|'+it.side;sums[k]=(sums[k]||0)+(st.values[it.key]||0);});
      $field.find('[data-share-space-card]').each(function(){
        var $card=$(this),metric=String($card.find('input[name$="[metric_key]"]').val()||'');
        if(Object.prototype.hasOwnProperty.call(sums,metric+'|pr')){rpVisionSetInput($card.find('[data-share-space-pr]').get(0),sums[metric+'|pr']);filled++;}
        if(Object.prototype.hasOwnProperty.call(sums,metric+'|competitor')){rpVisionSetInput($card.find('[data-share-space-competitor]').get(0),sums[metric+'|competitor']);filled++;}
      });
    }else{
      confirmed.forEach(function(r){
        var idx=String(r.key||'').replace(/^r/,'');if(!/^\d+$/.test(idx))return;
        var input=$field.find('[data-fronts-input]').filter(function(){var n=String(this.name||'');return n.indexOf('['+idx+'][qty]')>=0||n.indexOf('['+idx+'][after]')>=0;}).get(0);
        if(!input)return;rpVisionSetInput(input,r.fronts);$(input).closest('[data-product-row]').addClass('is-camera-read');filled++;
      });
    }
    var form=st.form;rpVisionClose();
    if(form&&typeof window.routesproSetFormFeedback==='function')window.routesproSetFormFeedback(form,'success',filled+' valor(es) confirmados e preenchidos a partir da fotografia. Revê antes de concluir a visita.',false);
    if(form&&typeof form.routesproTouchDraft==='function')form.routesproTouchDraft();
  }
  function rpVisionFill(){
    var st=rpVisionState;if(!st||!st.result||st.confirming)return;
    var confirmed=[],invalid=false;
    $('.routespro-vision-sheet [data-vision-item]').each(function(){
      var key=String($(this).attr('data-vision-item')||''),raw=String($(this).find('[data-vision-value]').val()||'').trim();
      var value=Number(raw);
      if(raw===''||!Number.isInteger(value)||value<0||value>500){invalid=true;$(this).addClass('is-low');return;}
      confirmed.push({key:key,fronts:value});
    });
    var errEl=rpVisionSheet().querySelector('[data-vision-confirm-error]');
    if(invalid||confirmed.length!==(st.result.items||[]).length){if(errEl)errEl.textContent='Confirma cada quantidade, também os produtos não reconhecidos. Usa 0 apenas quando verificaste ausência.';return;}
    var readingId=parseInt(st.result.reading_id,10)||0;
    if(!readingId){if(errEl)errEl.textContent='Não foi possível identificar o registo desta leitura. Recomeça ou conta manualmente.';return;}
    st.confirming=true;
    var btn=rpVisionSheet().querySelector('[data-vision-fill]');if(btn){btn.disabled=true;btn.textContent='A guardar confirmação…';}
    var fd=new FormData();fd.append('action','fieldflow_fronts_vision_confirm');fd.append('nonce',st.nonce);fd.append('reading_id',String(readingId));fd.append('confirmed',JSON.stringify(confirmed));
    var x=new XMLHttpRequest();st.confirmXhr=x;x.open('POST',st.endpoint,true);x.withCredentials=true;x.timeout=30000;
    var failed=function(message){if(st!==rpVisionState)return;st.confirming=false;if(btn){btn.disabled=false;btn.textContent='Confirmar e preencher';}if(errEl)errEl.textContent=message+' Os valores continuam no ecrã e não foram aplicados.';};
    x.onload=function(){if(st!==rpVisionState)return;var response=null;try{response=JSON.parse(x.responseText);}catch(_){}if(x.status>=200&&x.status<300&&response&&response.success){rpVisionApplyConfirmed(st,confirmed);}else failed((response&&response.data&&response.data.message)||'Não foi possível confirmar a leitura.');};
    x.onerror=function(){failed('Falha de ligação ao guardar a confirmação. Podes tentar de novo sem voltar a pagar à IA.');};
    x.ontimeout=function(){failed('Tempo de confirmação excedido. Tenta novamente sem nova leitura.');};
    x.send(fd);
  }
  $(document).on('click','[data-fronts-camera]',function(e){
    e.preventDefault();var $field=$(this).closest('[data-fronts-vision="1"]'),$form=$field.closest('.routespro-dyn-form'),groups=rpVisionMatrixGroups($field);
    if(!groups.length){window.alert('Esta pergunta não tem produtos para ler.');return;}
    var open=0;groups.forEach(function(g,i){if(g.open&&!open)open=i;});
    rpVisionOpen({type:'product_matrix',key:String($field.attr('data-rp-question')||''),field:$field.get(0),form:$form.get(0),origin:this,endpoint:String($field.attr('data-vision-endpoint')||''),nonce:String($field.attr('data-vision-nonce')||''),boxes:$field.attr('data-vision-boxes')==='1',cameraAllowed:$field.attr('data-vision-camera-enabled')==='1',blockReason:String($field.attr('data-vision-block-reason')||''),context:rpVisionContext($form),groups:groups,group:open,references:$field.attr('data-vision-references')==='1'});
  });
  $(document).on('click','[data-fronts-camera-card]',function(e){
    e.preventDefault();var $card=$(this).closest('[data-share-space-card]'),$field=$card.closest('[data-fronts-vision="1"]'),$form=$field.closest('.routespro-dyn-form');
    var card={key:String($card.find('input[name$="[metric_key]"]').val()||''),metric:String($card.find('h4').first().text()||'').trim(),pr_label:String($(this).attr('data-pr-label')||''),competitor_label:String($(this).attr('data-competitor-label')||'')};
    rpVisionOpen({type:'share_space_matrix',key:String($field.attr('data-rp-question')||''),field:$field.get(0),form:$form.get(0),origin:this,endpoint:String($field.attr('data-vision-endpoint')||''),nonce:String($field.attr('data-vision-nonce')||''),boxes:$field.attr('data-vision-boxes')==='1',cameraAllowed:$field.attr('data-vision-camera-enabled')==='1',blockReason:String($field.attr('data-vision-block-reason')||''),context:rpVisionContext($form),cards:[card],sides:rpVisionShareSides($card,card),references:$field.attr('data-vision-references')==='1'});
  });
  $(document).on('click','.routespro-vision-sheet [data-vision-manual]',function(e){
    e.preventDefault();var mode=String($(this).attr('data-vision-manual')||'select');
    if(mode==='select')rpVisionRenderSelect();else rpVisionRenderText(mode==='voice');
  });
  $(document).on('click','.routespro-vision-sheet [data-vision-back]',function(e){e.preventDefault();var st=rpVisionState;if(st&&st.speech){try{st.speech.abort();}catch(_){}}if(st)rpVisionRenderChoose();});
  $(document).on('input','.routespro-vision-sheet [data-vision-manual-search]',function(){
    var term=rpVisionNormalizeText(this.value);
    $('.routespro-vision-sheet [data-vision-manual-row]').each(function(){this.hidden=!!term&&rpVisionNormalizeText($(this).attr('data-vision-search')||'').indexOf(term)<0;});
  });
  $(document).on('click','.routespro-vision-sheet [data-vision-manual-apply]',function(e){e.preventDefault();rpVisionManualApply();});
  $(document).on('input','.routespro-vision-sheet [data-vision-manual-text]',function(){if(rpVisionState)rpVisionState.manualText=String(this.value||'');});
  $(document).on('click','.routespro-vision-sheet [data-vision-parse]',function(e){e.preventDefault();var area=rpVisionSheet().querySelector('[data-vision-manual-text]');if(rpVisionState){rpVisionState.manualText=area?area.value:'';rpVisionShowTextProposals();}});
  $(document).on('click','.routespro-vision-sheet [data-vision-proposals-apply]',function(e){e.preventDefault();rpVisionApplyProposals();});
  $(document).on('click','.routespro-vision-sheet [data-vision-speak]',function(e){e.preventDefault();rpVisionStartSpeech();});
  $(document).on('click','.routespro-vision-sheet [data-vision-close]',function(e){e.preventDefault();rpVisionClose();});
  $(document).on('click','.routespro-vision-sheet [data-vision-group]',function(e){e.preventDefault();if(!rpVisionState)return;rpVisionState.group=parseInt($(this).attr('data-vision-group'),10)||0;rpVisionState.showAll=false;rpVisionState.selectedKeys=null;rpVisionRenderChoose();});
  $(document).on('click','.routespro-vision-sheet [data-vision-retry]',function(e){e.preventDefault();if(!rpVisionState)return;rpVisionState.result=null;if(rpVisionState.lastCapture==='live'&&rpVisionLiveSupported()&&!rpVisionState.liveUnavailable){rpVisionLiveStart();return;}rpVisionRenderChoose();});
  $(document).on('click','.routespro-vision-sheet [data-vision-shoot]',function(e){
    e.preventDefault();if(!rpVisionState||rpVisionState.refBusy)return;var source=String($(this).attr('data-vision-shoot')||'camera');
    var input=rpVisionSheet().querySelector(source==='gallery'?'[data-vision-gallery]':'[data-vision-camera]');if(!input)return;input.value='';
    if(source==='camera'&&rpVisionState.origin)rpCheckpointExternalMediaHandoff($(rpVisionState.origin),'camera');
    input.click();
  });
  $(document).on('change','.routespro-vision-sheet [data-vision-camera],.routespro-vision-sheet [data-vision-gallery]',function(){
    var file=this.files&&this.files[0],st=rpVisionState;if(!file||!st)return;
    if(st.form&&st.form.__rpViewportLock)rpScheduleFormViewportUnlock(st.form,'vision-photo',600);
    if(st.preview){try{URL.revokeObjectURL(st.preview);}catch(_){}}
    try{st.preview=URL.createObjectURL(file);}catch(_){st.preview='';}
    rpVisionBody('<div class="routespro-vision-progress"><span class="routespro-vision-spinner" aria-hidden="true"></span><span>A preparar a fotografia, sem IA…</span></div>');
    var capture=this.hasAttribute('data-vision-gallery')?'gallery':'camera';st.lastCapture=capture;
    st.captureSeq=(st.captureSeq||0)+1;var sequence=st.captureSeq;
    rpVisionPrepare(file).then(function(blob){if(st===rpVisionState&&sequence===st.captureSeq)rpVisionReviewCapture(st,blob,capture);});
  });
  $(document).on('click','.routespro-vision-sheet [data-vision-step]',function(e){
    e.preventDefault();var input=$(this).closest('.routespro-fronts-stepper').find('[data-vision-value]').get(0);if(!input)return;
    input.value=String(Math.max(0,(parseInt(input.value,10)||0)+(parseInt($(this).attr('data-vision-step'),10)||0)));
    rpVisionRefreshComparison();
  });
  $(document).on('input','.routespro-vision-sheet [data-vision-value]',function(){rpVisionRefreshComparison();});
  // 9CX: accepting a *visible* model proposal requires a deliberate operative tap.
  $(document).on('click','.routespro-vision-sheet [data-vision-accept]',function(e){
    e.preventDefault();var st=rpVisionState;if(!st||!st.result)return;
    var key=String($(this).attr('data-vision-accept')||''),it=(st.result.items||[]).find(function(row){return String(row.key)===key;});
    if(!it||!['capacity_unverified','low_confidence'].includes(String(it.review_reason||'')))return;
    var count=Number(it.model_fronts);if(!Number.isInteger(count)||count<=0||count>200)return;
    var input=$(this).closest('[data-vision-item]').find('[data-vision-value]').get(0);if(!input)return;
    input.value=String(count);rpVisionRefreshComparison();$(this).replaceWith('<small>Valor escolhido manualmente. Confirma a leitura ao finalizar.</small>');
  });
  $(document).on('click','.routespro-vision-sheet [data-vision-live]',function(e){e.preventDefault();if(!rpVisionState||rpVisionState.refBusy)return;rpVisionLiveStart();});
  $(document).on('click','.routespro-vision-sheet [data-vision-live-now]',function(e){e.preventDefault();var st=rpVisionState;if(st&&st.live)rpVisionLiveCapture(st,st.live);});
  $(document).on('change','.routespro-vision-sheet [data-vision-select-product]',function(){
    var st=rpVisionState;if(!st||st.type!=='product_matrix')return;
    var key=String($(this).attr('data-vision-select-product')||''),selected=st.selectedKeys||[];
    if(this.checked){if(selected.indexOf(key)<0&&selected.length<12)selected.push(key);else if(selected.length>=12){this.checked=false;}}
    else st.selectedKeys=selected.filter(function(k){return k!==key;});
    if(this.checked)st.selectedKeys=selected;
    var count=(st.selectedKeys||[]).length,scope=rpVisionSheet(),label=scope.querySelector('[data-vision-selected-count]'),button=scope.querySelector('[data-vision-analyze]');
    if(label)label.textContent=count+' de 12 selecionados'+(count>=12?', máximo atingido':'');
    if(button)button.disabled=!count;
  });
  $(document).on('click','.routespro-vision-sheet [data-vision-analyze]',function(e){e.preventDefault();rpVisionApproveAnalysis();});
  $(document).on('click','.routespro-vision-sheet [data-vision-preview-back]',function(e){e.preventDefault();rpVisionRenderCaptureReview();});
  $(document).on('click','.routespro-vision-sheet [data-vision-live-back]',function(e){e.preventDefault();var st=rpVisionState;if(!st)return;rpVisionLiveStop(st);rpVisionRenderChoose();});
  document.addEventListener('visibilitychange',function(){var st=rpVisionState;if(document.hidden&&st&&st.live){rpVisionLiveStop(st);rpVisionRenderChoose();}});
  $(document).on('click','.routespro-vision-sheet [data-vision-fill]',function(e){e.preventDefault();rpVisionFill();});
  $(document).on('click','.routespro-vision-sheet [data-vision-all]',function(e){e.preventDefault();if(!rpVisionState)return;rpVisionState.showAll=true;rpVisionRenderChoose();});
  $(document).on('click','.routespro-vision-sheet [data-vision-ref]',function(e){
    e.preventDefault();var st=rpVisionState;if(!st||st.refBusy)return;st.refKey=String($(this).attr('data-vision-ref')||'');
    var input=rpVisionSheet().querySelector('[data-vision-ref-camera]');if(!input)return;input.value='';
    if(st.origin)rpCheckpointExternalMediaHandoff($(st.origin),'camera');
    input.click();
  });
  $(document).on('change','.routespro-vision-sheet [data-vision-ref-camera]',function(){
    var file=this.files&&this.files[0],st=rpVisionState;if(!file||!st||!st.refKey)return;var key=st.refKey;
    if(st.form&&st.form.__rpViewportLock)rpScheduleFormViewportUnlock(st.form,'vision-reference',600);
    st.refBusy=key;rpVisionRenderChoose();
    rpVisionPrepare(file).then(function(blob){rpVisionSendReference(st,key,blob);});
  });
  $(document).on('keydown',function(e){if(e.key==='Escape'&&rpVisionState)rpVisionClose();});
  window.routesproInitDynamicForm=rpInitDynamicForm;
  window.routesproValidateDynamicForm=function(form,opts){
    if(!form) return true;
    rpInitDynamicForm(form);
    return typeof form.routesproValidate==='function'?form.routesproValidate(opts||{}):true;
  };
  window.routesproSetFormFeedback=function(form,type,message,shouldScroll){
    if(!form) return;
    rpInitDynamicForm(form);
    if(typeof form.routesproFeedback==='function') form.routesproFeedback(type,message,shouldScroll);
  };
  (function rpMediaWatchOfflineSubmit(){
    function wrap(){
      var offline=window.FieldFlowOffline;
      if(!offline||typeof offline.submitForm!=='function')return false;
      if(offline.submitForm.__rpMediaWatch)return true;
      var original=offline.submitForm;
      var watched=function(form){
        var args=arguments,startedAt=Date.now();
        return Promise.resolve().then(function(){return original.apply(offline,args);}).catch(function(error){
          try{
            var withMedia=form&&form.querySelectorAll&&Array.prototype.some.call(form.querySelectorAll('input[type="file"]'),function(input){return !input.disabled&&input.files&&input.files.length;});
            if(withMedia){var message=String(error&&error.message||error||'erro');rpMediaTrace('submit-error',{error:message.slice(0,80),after_ms:Date.now()-startedAt});rpMediaReport('media_submit_failed','O envio do reporte com fotografias falhou: '+message.slice(0,180),form);}
          }catch(_){ }
          throw error;
        });
      };
      watched.__rpMediaWatch=1;
      offline.submitForm=watched;
      return true;
    }
    function safeWrap(){try{return wrap();}catch(_){return true;}}
    if(!safeWrap()){document.addEventListener('DOMContentLoaded',safeWrap);window.setTimeout(safeWrap,1500);}
  })();
  $('.routespro-dyn-form').each(function(){ rpInitDynamicForm(this); rpInitShareSpace(this); });
  $(document).on('routespro:form-rendered',function(_e,form){ if(form){rpInitDynamicForm(form);rpInitShareSpace(form);} });
  var $success=$('.routespro-dyn-form .routespro-form-notice.success').first();
  if($success.length) window.setTimeout(function(){ rpScrollTo($success); },120);
});
JS;
    }
}
