<?php
namespace RoutesPro\Admin;

use RoutesPro\Catalog\ProductCatalogService as Catalog;
use RoutesPro\Briefings\BriefingCategoryMappingService as CategoryMapping;
use RoutesPro\Forms\ContextQuestions as ContextQuestionService;

if (!defined('ABSPATH')) exit;

/**
 * 9CL Visual Product Catalog back office.
 *
 * Lives inside "Cardex e Catálogo" (page routespro-product-cardex) as two tabs:
 * "Catálogo visual" (products de-duplicated per client, reference images,
 * competitor products, merge) and "Mapeamento do briefing" (Share Space labels
 * linked to catalog products). Every handler requires routespro_manage and a nonce.
 *
 * 9CP: one general catalog per client (never per insignia). Each product shows the insignias
 * where it is sold, probable duplicates are suggested for one-click merge, names edited here
 * are kept by the Cardex sync and field reference photos are tagged "Terreno".
 */
final class ProductCatalog {
    public const CONTRACT = 'fieldflow-visual-product-catalog-admin-v1';
    private const PAGE = 'routespro-product-cardex';
    private const PER_PAGE = 40;
    private const MAX_IMAGES = 12;

    public static function register_hooks(): void {
        add_action('admin_post_fieldflow_catalog_sync', [self::class, 'handle_sync']);
        add_action('admin_post_fieldflow_catalog_save', [self::class, 'handle_save']);
        add_action('admin_post_fieldflow_catalog_merge', [self::class, 'handle_merge']);
        add_action('admin_post_fieldflow_catalog_delete', [self::class, 'handle_delete']);
        add_action('admin_post_fieldflow_catalog_map_label', [self::class, 'handle_map_label']);
        add_action('admin_post_fieldflow_catalog_reuse', [self::class, 'handle_reuse']);
        add_action('admin_post_fieldflow_catalog_refresh_source', [self::class, 'handle_refresh_source']);
        add_action('admin_post_fieldflow_catalog_not_duplicate', [self::class, 'handle_not_duplicate']);
        add_action('admin_post_fieldflow_catalog_export', [self::class, 'handle_export']);
        add_action('admin_post_fieldflow_catalog_question_category', [self::class, 'handle_question_category']);
        add_action('admin_post_fieldflow_catalog_sync_categories', [self::class, 'handle_sync_categories']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue($hook = ''): void {
        if (sanitize_key((string)($_GET['page'] ?? '')) !== self::PAGE) return;
        if (self::current_tab() !== 'catalog') return;
        if (current_user_can('upload_files') && function_exists('wp_enqueue_media')) wp_enqueue_media();
    }

    public static function current_tab(): string {
        $tab = sanitize_key((string)($_GET['tab'] ?? ''));
        return in_array($tab, ['catalog', 'mapping', 'vision'], true) ? $tab : 'cardex';
    }

    public static function render_tabs(string $active): void {
        $tabs = ['cardex' => 'Cardex', 'catalog' => 'Catálogo visual', 'mapping' => 'Mapeamento do briefing', 'vision' => 'Leitura por câmara'];
        $client = absint($_GET['client_id'] ?? 0);
        $project = absint($_GET['project_id'] ?? 0);
        $subtitles = [
            'cardex'=>'Sortidos e lojas', 'catalog'=>'Identidade e fotografias',
            'mapping'=>'Perguntas e associações', 'vision'=>'Reconhecimento e custos',
        ];
        $icons = ['cardex'=>'dashicons-list-view','catalog'=>'dashicons-format-gallery','mapping'=>'dashicons-randomize','vision'=>'dashicons-camera'];
        echo '<div class="ff-cat-hub-title"><span class="dashicons dashicons-screenoptions"></span><div><strong>Gestão de produtos</strong><small>Cardex, catálogo, mapeamento e IA num único espaço</small></div></div>';
        echo '<nav class="nav-tab-wrapper ff-catalog-tabs" aria-label="Áreas de Cardex e Catálogo">';
        foreach ($tabs as $slug => $label) {
            $args = [];
            if ($slug !== 'cardex') {
                $args['tab'] = $slug;
                if ($slug === 'vision') { echo '<a class="nav-tab' . ($slug === $active ? ' nav-tab-active' : '') . '" href="' . esc_url(self::url($args)) . '" ' . ($slug === $active ? 'aria-current="page"' : '') . '><span class="dashicons ' . esc_attr($icons[$slug]) . '"></span><span><strong>' . esc_html($label) . '</strong><small>' . esc_html($subtitles[$slug]) . '</small></span></a>'; continue; }
                if ($client) $args['client_id'] = $client;
                if ($project) $args['project_id'] = $project;
            }
            echo '<a class="nav-tab' . ($slug === $active ? ' nav-tab-active' : '') . '" href="' . esc_url(self::url($args)) . '" ' . ($slug === $active ? 'aria-current="page"' : '') . '><span class="dashicons ' . esc_attr($icons[$slug]) . '"></span><span><strong>' . esc_html($label) . '</strong><small>' . esc_html($subtitles[$slug]) . '</small></span></a>';
        }
        echo '</nav>';
    }

    // ------------------------------------------------------------------
    // Page
    // ------------------------------------------------------------------

    public static function render(?string $tab = null): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        $tab = $tab ?: self::current_tab();
        // 9CO: camera reading pilot lives in its own tab.
        if ($tab === 'vision' && class_exists('\\RoutesPro\\Admin\\FrontsVisionAdmin')) { FrontsVisionAdmin::render(); return; }
        Catalog::registerPostType();

        $counts = Catalog::countsByClient();
        $clients = self::clients();
        $editParam = sanitize_key((string)($_GET['edit'] ?? ''));
        $editId = ctype_digit($editParam) ? absint($editParam) : 0;
        $editProduct = $editId ? Catalog::get($editId) : null;
        $clientId = $editProduct ? (int)$editProduct['client_id'] : self::selected_client($clients, $counts);
        $projects = $clientId ? self::projects($clientId) : [];
        $projectId = absint($_GET['project_id'] ?? 0);
        if ($projectId && !isset($projects[$projectId])) $projectId = 0;

        echo '<div class="wrap ff-cat-wrap">';
        Branding::render_header(
            $tab === 'mapping' ? 'Mapeamento do briefing' : 'Catálogo visual de produtos',
            $tab === 'mapping'
                ? 'Liga os rótulos de marca e concorrência dos briefings aos produtos do catálogo. O mapeamento fica guardado para os briefings seguintes.'
                : 'Produtos únicos por cliente, extraídos de todos os cardex e independentes da loja, com imagens de referência para as perguntas de frentes.'
        );
        self::render_tabs($tab);
        self::styles();
        self::render_flash();
        $reuseView = $tab === 'catalog' && sanitize_key((string)($_GET['view'] ?? '')) === 'reuse';
        if (!$reuseView) self::render_filters($tab, $clients, $counts, $clientId, $projects, $projectId);

        if (!$clientId) {
            echo '<div class="ff-cat-empty"><strong>Ainda não existem clientes.</strong><br>Cria um cliente e associa-o aos cardex para o catálogo ser preenchido.</div></div>';
            return;
        }
        if ($tab === 'mapping') self::render_mapping($clientId, $projectId, $projects);
        elseif ($reuseView) self::render_reuse($clientId, $clients, $counts);
        else self::render_catalog($clientId, $projectId, $projects, $editParam === 'new' ? 'new' : $editProduct);
        echo '</div>';
    }

    private static function render_filters(string $tab, array $clients, array $counts, int $clientId, array $projects, int $projectId): void {
        echo '<form method="get" class="ff-cat-filters">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE) . '"><input type="hidden" name="tab" value="' . esc_attr($tab) . '">';
        echo '<label><span>Cliente</span><select name="client_id" onchange="if(this.form.project_id){this.form.project_id.value=\'\';}this.form.submit()">';
        foreach ($clients as $id => $name) {
            $total = (int)($counts[$id] ?? 0);
            echo '<option value="' . (int)$id . '"' . selected($id, $clientId, false) . '>' . esc_html($name . ($total ? ' (' . $total . ')' : '')) . '</option>';
        }
        echo '</select></label>';
        echo '<label><span>Campanha</span><select name="project_id" onchange="this.form.submit()"><option value="">Todas</option>';
        foreach ($projects as $id => $project) {
            echo '<option value="' . (int)$id . '"' . selected($id, $projectId, false) . '>' . esc_html($project['name'] . ($project['status'] !== 'active' ? ' (inativa)' : '')) . '</option>';
        }
        echo '</select></label>';
        if ($tab === 'catalog') {
            $kind = sanitize_key((string)($_GET['kind'] ?? ''));
            $images = sanitize_key((string)($_GET['images'] ?? ''));
            echo '<label><span>Tipo</span><select name="kind"><option value="">Todos</option><option value="own"' . selected($kind, 'own', false) . '>Marca própria</option><option value="competitor"' . selected($kind, 'competitor', false) . '>Concorrência</option></select></label>';
            echo '<label><span>Imagens</span><select name="images"><option value="">Todas</option><option value="missing"' . selected($images, 'missing', false) . '>Sem imagem</option><option value="ready"' . selected($images, 'ready', false) . '>Com imagem</option></select></label>';
            echo '<label class="ff-cat-search"><span>Pesquisar</span><input type="search" name="s" value="' . esc_attr(sanitize_text_field(wp_unslash((string)($_GET['s'] ?? '')))) . '" placeholder="Nome do produto"></label>';
        }
        echo '<button class="button">Filtrar</button></form>';
    }

    // ------------------------------------------------------------------
    // Catalog tab
    // ------------------------------------------------------------------

    private static function render_catalog(int $clientId, int $projectId, array $projects, $edit): void {
        $filters = self::list_filters();
        $page = max(1, absint($_GET['paged'] ?? 1));
        // 9CQ: the catalog of the client is reorganised the first time it is opened after an identity update,
        // so the list shown is never the old one (WP-Cron may be disabled on the server).
        $rebuildNotice = '';
        if (!Catalog::identityCurrentFor($clientId)) {
            try {
                if (function_exists('set_time_limit')) @set_time_limit(180);
                $rebuilt = Catalog::syncFromCardex($clientId);
                $rebuildNotice = '<div class="notice notice-success inline ff-cat-rebuild"><p><strong>Catálogo reorganizado agora:</strong> ' . esc_html(self::sync_text($rebuilt)) . '</p></div>';
            } catch (\Throwable $e) {
                $rebuildNotice = '<div class="notice notice-warning inline ff-cat-rebuild"><p>O catálogo vai ser reorganizado num só catálogo geral por cliente. Carrega em <em>Sincronizar com cardex</em> para fazer já.</p></div>';
            }
        }
        $result = Catalog::query(['client_id' => $clientId, 'project_id' => $projectId] + $filters, $page, self::PER_PAGE);
        $summary = Catalog::summary($clientId);
        $lastSync = get_option('fieldflow_catalog_last_sync', []);

        echo '<div class="ff-cat-summary">';
        foreach ([
            ['Produtos', $summary['total'], ''],
            ['Com imagem', $summary['with_images'], 'is-ok'],
            ['Sem imagem', $summary['without_images'], $summary['without_images'] ? 'is-warn' : ''],
            ['Concorrência', $summary['competitors'], ''],
        ] as [$label, $value, $class]) {
            echo '<div class="ff-cat-kpi ' . esc_attr($class) . '"><span>' . esc_html($label) . '</span><strong>' . (int)$value . '</strong></div>';
        }
        echo '<div class="ff-cat-actions">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="fieldflow_catalog_sync"><input type="hidden" name="client_id" value="' . (int)$clientId . '">';
        self::return_fields(['tab' => 'catalog', 'client_id' => $clientId, 'project_id' => $projectId]);
        wp_nonce_field('fieldflow_catalog_sync', 'fieldflow_catalog_nonce');
        echo '<button class="button">Sincronizar com cardex</button></form>';
        echo '<a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=fieldflow_catalog_export&client_id=' . (int)$clientId), 'fieldflow_catalog_export_' . (int)$clientId)) . '">Exportar CSV</a>';
        if (count(array_filter(Catalog::countsByClient(), static function($total, $id) use ($clientId) { return $total > 0 && (int)$id !== $clientId; }, ARRAY_FILTER_USE_BOTH))) {
            echo '<a class="button" href="' . esc_url(self::url(['tab' => 'catalog', 'client_id' => $clientId, 'view' => 'reuse'])) . '">Reutilizar de outro cliente</a>';
        }
        echo '<a class="button button-primary" href="' . esc_url(self::url(['tab' => 'catalog', 'client_id' => $clientId, 'project_id' => $projectId, 'edit' => 'new'])) . '">Novo produto</a>';
        echo '</div></div>';
        if ($rebuildNotice !== '') echo $rebuildNotice;
        if (!empty($lastSync['at'])) {
            $stats = (array)($lastSync['stats'] ?? []);
            echo '<p class="description ff-cat-sync">Última sincronização: ' . esc_html((string)$lastSync['at']) . '. Um catálogo geral por cliente: o mesmo produto em várias insígnias é um só registo. Atualiza-se sozinho depois de cada importação ou gravação de cardex.';
            if (!empty($stats['skipped_no_client'])) echo ' <strong>' . (int)$stats['skipped_no_client'] . ' cardex sem cliente nem campanha ficaram de fora</strong>: indica o Cliente ID ou o Projeto ID no separador Cardex.';
            echo '</p>';
        }

        if (!$edit) self::render_duplicates($clientId, $projectId);

        echo '<div class="ff-cat-layout' . ($edit ? ' has-editor' : '') . '"><div class="ff-cat-list">';
        if (!$result['rows']) {
            if (!$summary['total']) {
                echo '<div class="ff-cat-empty"><strong>O catálogo deste cliente ainda está vazio.</strong><br>Carrega em <em>Sincronizar com cardex</em> para extrair todos os produtos dos cardex do cliente, sem duplicados. Os produtos da concorrência podes criá-los aqui ou a partir do Mapeamento do briefing.</div>';
            } else {
                echo '<div class="ff-cat-empty">Nenhum produto corresponde aos filtros.</div>';
            }
        } else {
            echo '<table class="widefat striped ff-cat-table"><thead><tr><th class="ff-cat-col-thumb">Imagem</th><th>Produto</th><th>Categoria</th><th>Tipo</th><th>Imagens</th><th>Campanhas</th><th></th></tr></thead><tbody>';
            foreach ($result['rows'] as $row) {
                $editUrl = self::url(['tab' => 'catalog', 'client_id' => $clientId, 'project_id' => $projectId, 'edit' => $row['id']] + self::list_filters() + ($page > 1 ? ['paged' => $page] : []));
                $isEditing = is_array($edit) && (int)$edit['id'] === (int)$row['id'];
                $meta = array_filter([
                    $row['capacity_cl'] !== '' ? $row['capacity_cl'] . ' cl' : '',
                    $row['brand'],
                    $row['ean'] !== '' ? 'EAN ' . $row['ean'] : '',
                ]);
                $projectNames = [];
                foreach ($row['project_ids'] as $pid) $projectNames[] = isset($projects[$pid]) ? $projects[$pid]['name'] : '#' . $pid;
                echo '<tr' . ($isEditing ? ' class="is-editing"' : '') . '>';
                echo '<td class="ff-cat-col-thumb">' . self::thumb_html($row) . '</td>';
                echo '<td class="ff-cat-col-name"><a href="' . esc_url($editUrl) . '"><strong>' . esc_html($row['name']) . '</strong></a>' . ($meta ? '<br><span class="ff-cat-muted">' . esc_html(implode(' · ', $meta)) . '</span>' : '') . (!$row['active'] ? ' <span class="ff-cat-badge is-off">Inativo</span>' : '') . self::insignia_chips($row) . '</td>';
                echo '<td data-label="Categoria">' . esc_html($row['category'] !== '' ? $row['category'] : '—') . '</td>';
                echo '<td data-label="Tipo">' . ($row['kind'] === 'competitor' ? '<span class="ff-cat-badge is-comp">Concorrência</span>' : '<span class="ff-cat-badge">Marca</span>') . '</td>';
                echo '<td data-label="Imagens">' . ($row['image_count'] ? '<span class="ff-cat-badge is-ok">' . (int)$row['image_count'] . '</span>' : '<span class="ff-cat-badge is-warn">Sem imagem</span>') . '</td>';
                echo '<td data-label="Campanhas">' . esc_html($projectNames ? implode(', ', $projectNames) : 'Todas') . '</td>';
                echo '<td class="ff-cat-col-action"><a class="button button-small" href="' . esc_url($editUrl) . '">Editar</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            self::pagination($result, ['tab' => 'catalog', 'client_id' => $clientId, 'project_id' => $projectId] + self::list_filters());
        }
        echo '</div>';
        if ($edit) self::render_editor($clientId, $projectId, $projects, $edit === 'new' ? null : $edit);
        echo '</div>';
        self::script();
    }

    private static function render_editor(int $clientId, int $projectId, array $projects, ?array $product): void {
        $flashOld = self::$flashOld;
        $data = $product ?: [
            'id' => 0,
            'name' => (string)($flashOld['name'] ?? ''),
            'reference' => (string)($flashOld['reference'] ?? ''),
            'brand' => (string)($flashOld['brand'] ?? ''),
            'category' => (string)($flashOld['category'] ?? ''),
            'capacity_cl' => (string)($flashOld['capacity_cl'] ?? ''),
            'ean' => (string)($flashOld['ean'] ?? ''),
            'kind' => (string)($flashOld['kind'] ?? 'competitor'),
            'active' => true,
            'aliases' => [],
            'images' => [],
            'project_ids' => $projectId ? [$projectId] : [],
            'origin' => 'manual',
            'cardex_ids' => [],
            'label_aliases' => [],
            'last_synced_at' => '',
        ];
        $closeUrl = self::url(['tab' => 'catalog', 'client_id' => $clientId, 'project_id' => $projectId] + self::list_filters());
        echo '<aside class="ff-cat-editor" id="ff-cat-editor"><div class="ff-cat-editor-head"><h2>' . ($product ? 'Editar produto' : 'Novo produto') . '</h2><a class="ff-cat-close" href="' . esc_url($closeUrl) . '" aria-label="Fechar">×</a></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="fieldflow_catalog_save"><input type="hidden" name="product_id" value="' . (int)$data['id'] . '"><input type="hidden" name="client_id" value="' . (int)$clientId . '">';
        self::return_fields(['tab' => 'catalog', 'client_id' => $clientId, 'project_id' => $projectId] + self::list_filters());
        wp_nonce_field('fieldflow_catalog_save', 'fieldflow_catalog_nonce');

        $canUpload = current_user_can('upload_files');
        echo '<div class="ff-cat-images" data-ff-cat-images data-max="' . self::MAX_IMAGES . '"><div class="ff-cat-images-head"><strong>Imagens de referência</strong><span data-ff-cat-count>' . count($data['images']) . '/' . self::MAX_IMAGES . '</span></div>';
        echo '<div class="ff-cat-images-list" data-ff-cat-list>';
        foreach ($data['images'] as $index => $image) {
            echo self::image_item_html((int)$image['id'], (string)$image['thumb'], $index === 0, !empty($image['field']));
        }
        echo '</div><p class="ff-cat-images-empty">Sem imagens. Junta pelo menos a frente da embalagem.</p>';
        if ($canUpload) echo '<button type="button" class="button" data-ff-cat-add>Adicionar imagens</button>';
        else echo '<p class="description">A tua conta não pode carregar ficheiros para a biblioteca de media.</p>';
        echo '<p class="description">A primeira imagem é a principal e aparece nas perguntas de frentes. Junta frente, lado e embalagens diferentes (70 cl, 1 L, packs) para ajudar a identificação.</p></div>';

        echo '<label class="ff-cat-field"><span>Nome *</span><input type="text" name="name" value="' . esc_attr($data['name']) . '" required></label>';
        echo '<div class="ff-cat-grid2">';
        echo '<label class="ff-cat-field"><span>Marca</span><input type="text" name="brand" value="' . esc_attr($data['brand']) . '" placeholder="Ex.: Jameson"></label>';
        echo '<label class="ff-cat-field"><span>Referência</span><input type="text" name="reference" value="' . esc_attr($data['reference']) . '"></label>';
        // 9D3: category picker is authoritative for this client, not an unvalidated free-text field.
        $categories = CategoryMapping::catalogCategories($clientId);
        $currentCategory = sanitize_text_field((string)$data['category']);
        $hasCurrent = false;
        foreach ($categories as $category) {
            if (Catalog::normalizeText((string)$category) === Catalog::normalizeText($currentCategory) && $currentCategory !== '') {
                $hasCurrent = true;
                break;
            }
        }
        if ($currentCategory !== '' && !$hasCurrent) $categories[] = $currentCategory;
        natcasesort($categories);
        echo '<div class="ff-cat-field ff-cat-category-picker" data-ff-cat-category-editor><label for="ff-cat-category-select">Categoria</label>';
        echo '<select id="ff-cat-category-select" name="category_choice" data-ff-cat-category-choice><option value="">Sem categoria</option>';
        foreach ($categories as $category) {
            $category = (string)$category;
            echo '<option value="' . esc_attr($category) . '"' . selected(Catalog::normalizeText($category), Catalog::normalizeText($currentCategory), false) . '>' . esc_html($category) . '</option>';
        }
        echo '<option value="__new__">+ Adicionar nova categoria…</option></select>';
        echo '<label class="ff-cat-new-category" data-ff-cat-new-category hidden for="ff-cat-category-new">Nome da nova categoria';
        echo '<input id="ff-cat-category-new" name="category_new" type="text" maxlength="80" autocomplete="off" placeholder="Ex.: GIN" disabled></label>';
        echo '<small>Mostra as categorias do Catálogo e dos Cardex deste cliente. As novas ficam disponíveis para as próximas perguntas e produtos.</small></div>';
        echo '<label class="ff-cat-field"><span>Capacidade (cl)</span><input type="number" step="0.1" min="0" name="capacity_cl" value="' . esc_attr($data['capacity_cl']) . '" inputmode="decimal"></label>';
        echo '<label class="ff-cat-field"><span>EAN</span><input type="text" name="ean" value="' . esc_attr($data['ean']) . '" inputmode="numeric"></label>';
        echo '<div class="ff-cat-field"><span>Tipo</span><div class="ff-cat-radios"><label><input type="radio" name="kind" value="own"' . checked($data['kind'] !== 'competitor', true, false) . '> Marca</label><label><input type="radio" name="kind" value="competitor"' . checked($data['kind'] === 'competitor', true, false) . '> Concorrência</label></div></div>';
        echo '</div>';

        echo '<label class="ff-cat-field"><span>Outros nomes</span><textarea name="aliases" rows="3" placeholder="Um por linha. Ex.: Jameson Original">' . esc_textarea(implode("\n", $data['aliases'])) . '</textarea></label>';
        if ($projects) {
            echo '<fieldset class="ff-cat-field"><span>Campanhas</span><div class="ff-cat-checks">';
            foreach ($projects as $pid => $project) {
                echo '<label><input type="checkbox" name="project_ids[]" value="' . (int)$pid . '"' . checked(in_array((int)$pid, $data['project_ids'], true), true, false) . '> ' . esc_html($project['name']) . '</label>';
            }
            echo '</div><small>Sem campanhas assinaladas, o produto vale para todas as campanhas do cliente. As campanhas dos cardex onde o produto existe são repostas em cada sincronização.</small></fieldset>';
        }
        echo '<label class="ff-cat-inline"><input type="checkbox" name="inactive" value="1"' . checked(empty($data['active']), true, false) . '> Produto inativo (não aparece nas perguntas)</label>';
        echo '<div class="ff-cat-editor-actions"><button class="button button-primary">Guardar produto</button> <a class="button" href="' . esc_url($closeUrl) . '">Cancelar</a></div>';
        echo '</form>';

        if ($product) {
            $origin = ['cardex' => 'Cardex', 'briefing' => 'Briefing', 'manual' => 'Manual', 'reused' => 'Outro cliente', 'field' => 'Terreno (câmara)'][$product['origin']] ?? ucfirst($product['origin']);
            echo '<div class="ff-cat-sources"><h3>Origem</h3><ul>';
            echo '<li><span>Criado a partir de</span><strong>' . esc_html($origin) . '</strong></li>';
            $insignias = Catalog::insigniasForCardexIds($product['cardex_ids']);
            if ($insignias) echo '<li><span>Insígnias</span><strong>' . esc_html(implode(', ', $insignias)) . '</strong></li>';
            if ($product['cardex_ids']) echo '<li><span>Cardex</span><strong>' . esc_html(implode(', ', self::cardex_names($product['cardex_ids']))) . '</strong></li>';
            if ($product['label_aliases']) echo '<li><span>Rótulos do briefing</span><strong>' . esc_html(implode(', ', array_map('ucwords', $product['label_aliases']))) . '</strong></li>';
            if ($product['last_synced_at']) echo '<li><span>Última sincronização</span><strong>' . esc_html($product['last_synced_at']) . '</strong></li>';
            $sourceProduct = $product['source_product_id'] ? Catalog::get((int)$product['source_product_id']) : null;
            if ($product['source_product_id']) {
                $sourceClients = self::clients();
                $sourceLabel = $sourceProduct
                    ? ($sourceClients[(int)$sourceProduct['client_id']] ?? ('Cliente #' . (int)$sourceProduct['client_id'])) . ' · ' . $sourceProduct['name']
                    : 'Produto #' . (int)$product['source_product_id'] . ' (removido)';
                echo '<li><span>Reutilizado de</span><strong>' . esc_html($sourceLabel) . '</strong></li>';
            }
            echo '</ul>';
            if ($sourceProduct) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ff-cat-refresh">';
                echo '<input type="hidden" name="action" value="fieldflow_catalog_refresh_source"><input type="hidden" name="product_id" value="' . (int)$product['id'] . '">';
                self::return_fields(['tab' => 'catalog', 'client_id' => $clientId, 'project_id' => $projectId]);
                wp_nonce_field('fieldflow_catalog_refresh_source_' . (int)$product['id'], 'fieldflow_catalog_nonce');
                echo '<button class="button button-small">Atualizar imagens da origem</button> <span class="description">' . (int)count($sourceProduct['images']) . ' imagem(ns) na origem.</span></form>';
            }
            echo '</div>';

            $others = array_filter(Catalog::clientIndex($clientId), static function(array $row) use ($product): bool { return (int)$row['id'] !== (int)$product['id']; });
            if ($others) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ff-cat-merge" onsubmit="return confirm(\'Juntar este produto ao produto escolhido? Este registo vai para o lixo e as imagens, os nomes e os rótulos passam para o outro.\')">';
                echo '<input type="hidden" name="action" value="fieldflow_catalog_merge"><input type="hidden" name="source_id" value="' . (int)$product['id'] . '">';
                self::return_fields(['tab' => 'catalog', 'client_id' => $clientId, 'project_id' => $projectId]);
                wp_nonce_field('fieldflow_catalog_merge_' . (int)$product['id'], 'fieldflow_catalog_nonce');
                echo '<h3>Duplicado?</h3><p class="description">Junta este produto a outro do mesmo cliente. Imagens, outros nomes, rótulos e campanhas passam para o produto escolhido.</p>';
                echo '<select name="target_id" required><option value="">Escolher produto…</option>';
                foreach ($others as $row) {
                    $suffix = array_filter([$row['reference'] !== '' ? 'Ref. ' . $row['reference'] : '', $row['capacity_cl'] !== '' ? $row['capacity_cl'] . ' cl' : '', $row['kind'] === 'competitor' ? 'Concorrência' : '']);
                    echo '<option value="' . (int)$row['id'] . '">' . esc_html($row['name'] . ($suffix ? ' · ' . implode(' · ', $suffix) : '')) . '</option>';
                }
                echo '</select> <button class="button">Juntar</button></form>';
            }
            if ($product['origin'] !== 'cardex') {
                $deleteUrl = wp_nonce_url(admin_url('admin-post.php?action=fieldflow_catalog_delete&product_id=' . (int)$product['id'] . '&client_id=' . (int)$clientId), 'fieldflow_catalog_delete_' . (int)$product['id']);
                echo '<p class="ff-cat-delete"><a href="' . esc_url($deleteUrl) . '" onclick="return confirm(\'Apagar este produto do catálogo?\')">Apagar produto</a></p>';
            }
        }
        echo '</aside>';
    }

    // ------------------------------------------------------------------
    // Reuse from another client (9CN)
    // ------------------------------------------------------------------

    private static function render_reuse(int $clientId, array $clients, array $counts): void {
        $sources = [];
        foreach ($clients as $id => $name) if ((int)$id !== $clientId && !empty($counts[$id])) $sources[(int)$id] = $name;
        $backUrl = self::url(['tab' => 'catalog', 'client_id' => $clientId]);
        echo '<div class="ff-cat-reuse-head"><div><h2>Reutilizar produtos de outro cliente</h2><p class="description">Os produtos escolhidos são copiados para o catálogo de <strong>' . esc_html($clients[$clientId] ?? ('#' . $clientId)) . '</strong>, com as mesmas imagens (os ficheiros não são duplicados). Cada cliente continua com o seu catálogo: alterar a cópia não muda a origem. Produtos que já existem neste cliente não são duplicados; ficam ligados à origem e recebem as imagens se ainda não tiverem.</p></div><a class="button" href="' . esc_url($backUrl) . '">Voltar ao catálogo</a></div>';
        if (!$sources) {
            echo '<div class="ff-cat-empty">Nenhum outro cliente tem produtos no catálogo.</div>';
            return;
        }
        $from = absint($_GET['from'] ?? 0);
        if (!isset($sources[$from])) $from = (int)array_key_first($sources);
        $kind = sanitize_key((string)($_GET['kind'] ?? ''));
        $images = sanitize_key((string)($_GET['images'] ?? ''));
        $search = trim(sanitize_text_field(wp_unslash((string)($_GET['s'] ?? ''))));
        $page = max(1, absint($_GET['paged'] ?? 1));

        echo '<form method="get" class="ff-cat-filters"><input type="hidden" name="page" value="' . esc_attr(self::PAGE) . '"><input type="hidden" name="tab" value="catalog"><input type="hidden" name="view" value="reuse"><input type="hidden" name="client_id" value="' . (int)$clientId . '">';
        echo '<label><span>Copiar de</span><select name="from" onchange="this.form.submit()">';
        foreach ($sources as $id => $name) echo '<option value="' . (int)$id . '"' . selected($id, $from, false) . '>' . esc_html($name . ' (' . (int)$counts[$id] . ')') . '</option>';
        echo '</select></label>';
        echo '<label><span>Tipo</span><select name="kind"><option value="">Todos</option><option value="own"' . selected($kind, 'own', false) . '>Marca própria</option><option value="competitor"' . selected($kind, 'competitor', false) . '>Concorrência</option></select></label>';
        echo '<label><span>Imagens</span><select name="images"><option value="">Todas</option><option value="ready"' . selected($images, 'ready', false) . '>Com imagem</option><option value="missing"' . selected($images, 'missing', false) . '>Sem imagem</option></select></label>';
        echo '<label class="ff-cat-search"><span>Pesquisar</span><input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Nome do produto"></label>';
        echo '<button class="button">Filtrar</button></form>';

        $filters = array_filter(['kind' => $kind, 'images' => $images, 's' => $search]);
        $result = Catalog::query(['client_id' => $from] + $filters, $page, 60);
        if (!$result['rows']) {
            echo '<div class="ff-cat-empty">Nenhum produto corresponde aos filtros.</div>';
            return;
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ff-cat-reuse-form">';
        echo '<input type="hidden" name="action" value="fieldflow_catalog_reuse"><input type="hidden" name="client_id" value="' . (int)$clientId . '">';
        self::return_fields(['tab' => 'catalog', 'client_id' => $clientId, 'view' => 'reuse', 'from' => $from, 'kind' => $kind, 'images' => $images, 's' => $search, 'paged' => $page > 1 ? $page : 0]);
        wp_nonce_field('fieldflow_catalog_reuse', 'fieldflow_catalog_nonce');
        echo '<table class="widefat striped ff-cat-table"><thead><tr><th class="ff-cat-col-check"></th><th class="ff-cat-col-thumb">Imagem</th><th>Produto</th><th>Categoria</th><th>Tipo</th><th>Imagens</th><th>Neste cliente</th></tr></thead><tbody>';
        foreach ($result['rows'] as $row) {
            $existingId = Catalog::resolveProductId($clientId, $row['reference'], $row['name'], $row['capacity_cl'] === '' ? null : $row['capacity_cl']);
            $meta = array_filter([$row['reference'] !== '' ? 'Ref. ' . $row['reference'] : '', $row['capacity_cl'] !== '' ? $row['capacity_cl'] . ' cl' : '', $row['brand']]);
            echo '<tr>';
            echo '<td class="ff-cat-col-check"><input type="checkbox" name="product_ids[]" value="' . (int)$row['id'] . '" aria-label="' . esc_attr('Selecionar ' . $row['name']) . '"></td>';
            echo '<td class="ff-cat-col-thumb">' . self::thumb_html($row) . '</td>';
            echo '<td class="ff-cat-col-name"><strong>' . esc_html($row['name']) . '</strong>' . ($meta ? '<br><span class="ff-cat-muted">' . esc_html(implode(' · ', $meta)) . '</span>' : '') . '</td>';
            echo '<td data-label="Categoria">' . esc_html($row['category'] !== '' ? $row['category'] : '—') . '</td>';
            echo '<td data-label="Tipo">' . ($row['kind'] === 'competitor' ? '<span class="ff-cat-badge is-comp">Concorrência</span>' : '<span class="ff-cat-badge">Marca</span>') . '</td>';
            echo '<td data-label="Imagens">' . ($row['image_count'] ? '<span class="ff-cat-badge is-ok">' . (int)$row['image_count'] . '</span>' : '<span class="ff-cat-badge is-warn">Sem imagem</span>') . '</td>';
            echo '<td data-label="Neste cliente">' . ($existingId ? '<a href="' . esc_url(self::url(['tab' => 'catalog', 'client_id' => $clientId, 'edit' => $existingId])) . '" class="ff-cat-badge is-ok">Já existe</a>' : '<span class="ff-cat-muted">Novo</span>') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<div class="ff-cat-reuse-bar"><label class="ff-cat-inline-check"><input type="checkbox" data-ff-cat-check-all> Selecionar todos nesta página</label><div class="ff-cat-radios"><span>Copiar como</span><label><input type="radio" name="kind_mode" value="keep" checked> Manter o tipo</label><label><input type="radio" name="kind_mode" value="own"> Marca</label><label><input type="radio" name="kind_mode" value="competitor"> Concorrência</label></div><button class="button button-primary" data-ff-cat-reuse-submit>Copiar selecionados</button> <span class="ff-cat-muted" data-ff-cat-selected>0 selecionados</span></div>';
        echo '</form>';
        self::pagination($result, ['tab' => 'catalog', 'client_id' => $clientId, 'view' => 'reuse', 'from' => $from] + $filters);
        echo '<script>(function(){var f=document.querySelector(".ff-cat-reuse-form");if(!f)return;var all=f.querySelector("[data-ff-cat-check-all]"),out=f.querySelector("[data-ff-cat-selected]"),btn=f.querySelector("[data-ff-cat-reuse-submit]");function boxes(){return Array.prototype.slice.call(f.querySelectorAll("input[name=\'product_ids[]\']"));}function sync(){var n=boxes().filter(function(b){return b.checked;}).length;if(out)out.textContent=n+(n===1?" selecionado":" selecionados");if(btn)btn.disabled=n===0;}if(all)all.addEventListener("change",function(){boxes().forEach(function(b){b.checked=all.checked;});sync();});f.addEventListener("change",function(e){if(e.target&&e.target.name==="product_ids[]")sync();});sync();})();</script>';
    }

    // ------------------------------------------------------------------
    // Briefing mapping tab
    // ------------------------------------------------------------------

    private static function render_mapping(int $clientId, int $projectId, array $projects): void {
        $labels = Catalog::briefingLabels($clientId, $projectId);
        $index = Catalog::clientIndex($clientId);
        $mapped = 0;
        foreach ($labels as $label) if ($label['product_ids']) $mapped++;

        echo '<div class="ff-cat-summary">';
        foreach ([['Rótulos', count($labels), ''], ['Mapeados', $mapped, 'is-ok'], ['Por mapear', count($labels) - $mapped, count($labels) - $mapped ? 'is-warn' : '']] as [$title, $value, $class]) {
            echo '<div class="ff-cat-kpi ' . esc_attr($class) . '"><span>' . esc_html($title) . '</span><strong>' . (int)$value . '</strong></div>';
        }
        echo '</div>';
        echo '<p class="description ff-cat-sync">Os rótulos vêm das secções Share Space (marca vs concorrência) dos briefings e das perguntas geradas a partir deles. Um rótulo pode corresponder a vários produtos, por exemplo uma família inteira. O mapeamento aplica-se a todos os briefings do cliente, incluindo os das próximas semanas.</p>';

        self::render_question_categories($clientId, $projectId);

        if (!$labels) {
            echo '<div class="ff-cat-empty">Ainda não há rótulos de Share Space nos briefings deste cliente' . ($projectId ? ' nesta campanha' : '') . '.</div>';
            return;
        }

        echo '<datalist id="ff-cat-products">';
        foreach ($index as $row) {
            if (empty($row['active'])) continue;
            echo '<option value="' . esc_attr('#' . $row['id'] . ' · ' . $row['name'] . ($row['kind'] === 'competitor' ? ' · Concorrência' : '')) . '"></option>';
        }
        echo '</datalist>';

        echo '<div class="ff-cat-labels">';
        foreach ($labels as $label) {
            $anchor = 'ff-label-' . substr(md5($label['normalized']), 0, 10);
            $sides = $label['sides'];
            $createKind = in_array('own', $sides, true) && !in_array('competitor', $sides, true) ? 'own' : 'competitor';
            $candidates = [];
            foreach ($label['product_ids'] as $pid) $candidates[$pid] = true;
            foreach (Catalog::suggestionsForLabel($clientId, $label['label'], 6) as $suggestion) $candidates[(int)$suggestion['id']] = $candidates[(int)$suggestion['id']] ?? false;
            echo '<section class="ff-cat-label' . ($label['product_ids'] ? ' is-mapped' : '') . '" id="' . esc_attr($anchor) . '">';
            echo '<header><div><strong>' . esc_html($label['label']) . '</strong><div class="ff-cat-label-meta">';
            if (in_array('own', $sides, true)) echo '<span class="ff-cat-badge">Marca</span>';
            if (in_array('competitor', $sides, true)) echo '<span class="ff-cat-badge is-comp">Concorrência</span>';
            echo '<span class="ff-cat-muted">' . (int)$label['occurrences'] . ' referência(s) nos briefings';
            $projectNames = [];
            foreach ($label['projects'] as $pid) $projectNames[] = isset($projects[$pid]) ? $projects[$pid]['name'] : '#' . $pid;
            if ($projectNames) echo ' · ' . esc_html(implode(', ', $projectNames));
            echo '</span></div></div><span class="ff-cat-state">' . ($label['product_ids'] ? 'Mapeado' : 'Por mapear') . '</span></header>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="fieldflow_catalog_map_label"><input type="hidden" name="client_id" value="' . (int)$clientId . '"><input type="hidden" name="label" value="' . esc_attr($label['label']) . '"><input type="hidden" name="create_kind" value="' . esc_attr($createKind) . '">';
            foreach ($label['projects'] as $pid) echo '<input type="hidden" name="label_projects[]" value="' . (int)$pid . '">';
            self::return_fields(['tab' => 'mapping', 'client_id' => $clientId, 'project_id' => $projectId, 'anchor' => $anchor]);
            wp_nonce_field('fieldflow_catalog_map_label', 'fieldflow_catalog_nonce');
            if ($candidates) {
                echo '<div class="ff-cat-candidates">';
                foreach ($candidates as $pid => $isMapped) {
                    $row = $index[$pid] ?? null;
                    if (!$row) continue;
                    echo '<label class="ff-cat-candidate"><input type="checkbox" name="product_ids[]" value="' . (int)$pid . '"' . checked($isMapped, true, false) . '>' . self::thumb_html($row, 36) . '<span><strong>' . esc_html($row['name']) . '</strong><small>' . esc_html(implode(' · ', array_filter([$row['capacity_cl'] !== '' ? $row['capacity_cl'] . ' cl' : '', $row['kind'] === 'competitor' ? 'Concorrência' : 'Marca', $row['image_count'] ? '' : 'sem imagem']))) . '</small></span></label>';
                }
                echo '</div>';
            } else {
                echo '<p class="ff-cat-muted">Sem sugestões no catálogo.</p>';
            }
            echo '<div class="ff-cat-label-actions"><input type="text" name="extra_product" list="ff-cat-products" placeholder="Adicionar outro produto do catálogo…" autocomplete="off">';
            echo '<button class="button button-primary">Guardar</button>';
            if (!$label['product_ids']) echo '<button class="button" name="create_product" value="1">' . ($createKind === 'own' ? 'Criar produto da marca' : 'Criar produto de concorrência') . '</button>';
            echo '</div>';
            echo '</form></section>';
        }
        echo '</div>';
    }

    /** 9D2 central review of all briefing execution questions, not just Share Space labels. */
    private static function render_question_categories(int $clientId, int $projectId): void {
        global $wpdb;
        $table = ContextQuestionService::table();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return;
        $params = [$clientId];
        $where = 'q.client_id=%d AND q.source_type=\'briefing\'';
        if ($projectId > 0) { $where .= ' AND q.project_id=%d'; $params[] = $projectId; }
        $sql = "SELECT q.id,q.briefing_id,q.project_id,q.question_key,q.question_label,q.briefing_section_id,q.visibility_rules_json,b.week_no,b.year_no,b.title AS briefing_title,b.valid_from
            FROM {$table} q INNER JOIN " . $wpdb->prefix . "routespro_briefings b ON b.id=q.briefing_id AND b.client_id=q.client_id AND b.project_id=q.project_id
            WHERE {$where} ORDER BY b.valid_from DESC,q.id DESC LIMIT 600";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
        $unique = [];
        foreach ($rows as $row) {
            $key = (int)$row['briefing_id'] . ':' . (string)$row['question_key'];
            if (!isset($unique[$key])) $unique[$key] = $row;
            if (count($unique) >= 80) break;
        }
        $categories = CategoryMapping::categories($clientId);
        $auto = 0; $manual = 0; $pending = 0;
        foreach ($unique as &$row) {
            $decision = CategoryMapping::resolveForQuestion(['id'=>(int)$row['briefing_id'], 'client_id'=>$clientId, 'project_id'=>(int)$row['project_id']], (string)$row['question_label'], (int)$row['briefing_section_id']);
            $rules = json_decode((string)$row['visibility_rules_json'], true);
            $rules = is_array($rules) ? $rules : [];
            if (!empty($rules['product_category']) && (string)($rules['category_identity'] ?? '') === (string)$decision['key']) {
                $decision['category'] = (string)$rules['product_category'];
                $decision['status'] = (($rules['category_source'] ?? '') === 'manual') ? 'confirmed' : 'automatic';
            }
            $row['_decision'] = $decision;
            if ($decision['status'] === 'automatic') $auto++;
            elseif ($decision['status'] === 'confirmed') $manual++;
            else $pending++;
        }
        unset($row);
        echo '<section class="ff-cat-qmap" id="ff-cat-question-mapping"><header class="ff-cat-qmap-head"><div><span class="ff-cat-qmap-kicker">Inteligência de briefing 9D2</span><h2>Categorias das perguntas</h2><p>Associação por identidade do produto e cliente. Guardar uma decisão manual permite reutilizá-la nos próximos briefings quando o produto é o mesmo.</p></div>';
        echo '<div class="ff-cat-qmap-cta"><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('fieldflow_catalog_sync_categories', 'fieldflow_catalog_nonce');
        echo '<input type="hidden" name="action" value="fieldflow_catalog_sync_categories"><input type="hidden" name="client_id" value="' . $clientId . '"><input type="hidden" name="project_id" value="' . $projectId . '">';
        self::return_fields(['tab'=>'mapping','client_id'=>$clientId,'project_id'=>$projectId,'anchor'=>'ff-cat-question-mapping']);
        echo '<button class="button button-secondary" type="submit">Associar categorias automaticamente</button></form>';
        echo '<a class="button" href="' . esc_url(add_query_arg(['page'=>'routespro-briefings','client_id'=>$clientId], admin_url('admin.php'))) . '">Abrir Briefings</a></div></header>';
        echo '<div class="ff-cat-qmap-kpis"><span><strong>' . (int)$auto . '</strong> automáticas</span><span><strong>' . (int)$manual . '</strong> confirmadas</span><span class="pending"><strong>' . (int)$pending . '</strong> por rever</span></div>';
        if (!$unique) {
            echo '<div class="ff-cat-empty">Ainda não existem perguntas de execução neste cliente. Aprova uma pergunta em Briefings para iniciar o mapeamento.</div></section>';
            return;
        }
        echo '<div class="ff-cat-qmap-list">';
        foreach ($unique as $row) {
            $decision = $row['_decision'];
            $category = (string)($decision['category'] ?? '');
            $state = (string)($decision['status'] ?? 'pending');
            $statusLabel = $state === 'confirmed' ? 'Confirmada' : ($state === 'automatic' ? 'Automática' : ($state === 'ambiguous' ? 'Ambígua' : 'Por rever'));
            echo '<form class="ff-cat-qmap-row" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('fieldflow_catalog_question_category', 'fieldflow_catalog_nonce');
            echo '<input type="hidden" name="action" value="fieldflow_catalog_question_category">';
            echo '<input type="hidden" name="client_id" value="' . $clientId . '"><input type="hidden" name="project_id" value="' . (int)$row['project_id'] . '">';
            echo '<input type="hidden" name="briefing_id" value="' . (int)$row['briefing_id'] . '"><input type="hidden" name="question_key" value="' . esc_attr($row['question_key']) . '">';
            self::return_fields(['tab'=>'mapping','client_id'=>$clientId,'project_id'=>$projectId,'anchor'=>'ff-cat-question-mapping']);
            echo '<div class="ff-cat-qmap-row-content"><span class="ff-cat-qmap-source">W' . (int)$row['week_no'] . ' / ' . (int)$row['year_no'] . '</span><strong>' . esc_html($row['question_label']) . '</strong><span class="ff-cat-badge ' . ($state === 'automatic' || $state === 'confirmed' ? 'is-ok' : 'is-warn') . '">' . esc_html($statusLabel) . '</span></div>';
            echo '<label><span class="screen-reader-text">Categoria da pergunta</span><select name="category" required><option value="">Escolher categoria</option>';
            foreach ($categories as $value) echo '<option value="' . esc_attr($value) . '"' . selected($category, $value, false) . '>' . esc_html($value) . '</option>';
            echo '</select></label><button class="button button-primary" type="submit">Guardar</button>';
            echo '<a class="button" href="' . esc_url(add_query_arg(['page'=>'routespro-briefings','briefing_id'=>(int)$row['briefing_id'],'ffb_tab'=>'questions','edit_question'=>(int)$row['id']],admin_url('admin.php'))) . '#ffb-question-editor">Detalhe</a>';
            echo '</form>';
        }
        echo '</div></section>';
    }

    public static function handle_sync_categories(): void {
        self::guard('fieldflow_catalog_sync_categories');
        $clientId = absint($_POST['client_id'] ?? 0);
        $projectId = absint($_POST['project_id'] ?? 0);
        global $wpdb;
        if ($projectId > 0) {
            $owner = (int)$wpdb->get_var($wpdb->prepare('SELECT client_id FROM ' . $wpdb->prefix . 'routespro_projects WHERE id=%d', $projectId));
            if ($owner !== $clientId) wp_die('Campanha fora do cliente.');
        }
        $stats = CategoryMapping::synchronizeExisting($clientId, $projectId);
        self::flash('success', sprintf('%d registos atualizados, %d por rever e %d confirmações manuais preservadas.', $stats['updated'], $stats['pending'], $stats['manual']));
        self::redirect_back();
    }

    public static function handle_question_category(): void {
        self::guard('fieldflow_catalog_question_category');
        $briefingId = absint($_POST['briefing_id'] ?? 0);
        $clientId = absint($_POST['client_id'] ?? 0);
        $projectId = absint($_POST['project_id'] ?? 0);
        $key = sanitize_key((string)($_POST['question_key'] ?? ''));
        $category = sanitize_text_field(wp_unslash((string)($_POST['category'] ?? '')));
        $updated = CategoryMapping::assign($briefingId, $key, $clientId, $projectId, $category);
        self::flash($updated > 0 ? 'success' : 'error', $updated > 0
            ? 'Categoria confirmada e guardada. A mesma identidade de produto manterá esta escolha nos próximos briefings.'
            : 'Não foi possível associar a categoria. Confirma a identidade do produto e a campanha.');
        self::redirect_back();
    }

    // ------------------------------------------------------------------
    // Handlers
    // ------------------------------------------------------------------

    public static function handle_sync(): void {
        self::guard('fieldflow_catalog_sync');
        if (function_exists('set_time_limit')) @set_time_limit(180);
        $clientId = absint($_POST['client_id'] ?? 0);
        $stats = Catalog::syncFromCardex($clientId);
        $text = 'Sincronização concluída: ' . self::sync_text($stats);
        if (!empty($stats['ean_enriched'])) $text .= ' EAN completado a partir dos Stocks em ' . (int)$stats['ean_enriched'] . ' produto(s).';
        if (!empty($stats['skipped_no_client'])) $text .= ' ' . (int)$stats['skipped_no_client'] . ' cardex sem cliente nem campanha ficaram de fora.';
        self::flash('success', $text);
        self::redirect_back();
    }

    /** One-line summary of a Cardex sync (lines, insignias, unique products, joined duplicates). */
    private static function sync_text(array $stats): string {
        $insignias = (array)($stats['insignias'] ?? []);
        $text = (int)($stats['items'] ?? 0) . ' linha(s) de ' . (int)($stats['cardex'] ?? 0) . ' cardex' . ($insignias ? ' (' . implode(', ', $insignias) . ')' : '') . ' deram ' . (int)($stats['groups'] ?? 0) . ' produto(s) único(s): ' . (int)($stats['created'] ?? 0) . ' novo(s), ' . (int)($stats['updated'] ?? 0) . ' já existente(s).';
        if (!empty($stats['variants'])) $text .= ' ' . (int)$stats['variants'] . ' nome(s) escritos de forma diferente entre insígnias reconhecidos como o mesmo produto.';
        if (!empty($stats['merged'])) $text .= ' ' . (int)$stats['merged'] . ' duplicado(s) juntos (imagens, nomes e mapeamentos mantidos).';
        if (!empty($stats['removed'])) $text .= ' ' . (int)$stats['removed'] . ' produto(s) antigo(s) sem imagem nem uso removido(s).';
        return $text;
    }

    /** CSV of the catalog of a client: one line per product with the names used by each insignia (for review). */
    public static function handle_export(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        $clientId = absint($_GET['client_id'] ?? 0);
        check_admin_referer('fieldflow_catalog_export_' . $clientId);
        $rows = Catalog::clientIndex($clientId);
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="catalogo-cliente-' . $clientId . '-' . gmdate('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'Produto', 'Outros nomes', 'Insígnias', 'Capacidade (cl)', 'EAN', 'Categoria', 'Marca', 'Tipo', 'Imagens', 'Origem', 'Ativo'], ';');
        foreach ($rows as $row) {
            fputcsv($out, [
                (int)$row['id'], $row['name'], implode(' | ', $row['aliases']), implode(', ', Catalog::insigniasForCardexIds($row['cardex_ids'])),
                $row['capacity_cl'], $row['ean'], $row['category'], $row['brand'], $row['kind'] === 'competitor' ? 'Concorrência' : 'Marca',
                (int)$row['image_count'], $row['origin'], $row['active'] ? 'Sim' : 'Não',
            ], ';');
        }
        fclose($out);
        exit;
    }

    public static function handle_save(): void {
        self::guard('fieldflow_catalog_save');
        $productId = absint($_POST['product_id'] ?? 0);
        $data = [
            'client_id' => absint($_POST['client_id'] ?? 0),
            'name' => sanitize_text_field(wp_unslash((string)($_POST['name'] ?? ''))),
            'reference' => sanitize_text_field(wp_unslash((string)($_POST['reference'] ?? ''))),
            'brand' => sanitize_text_field(wp_unslash((string)($_POST['brand'] ?? ''))),
            'category' => sanitize_text_field(wp_unslash((string)($_POST['category'] ?? ''))),
            'capacity_cl' => sanitize_text_field(wp_unslash((string)($_POST['capacity_cl'] ?? ''))),
            'ean' => sanitize_text_field(wp_unslash((string)($_POST['ean'] ?? ''))),
            'kind' => sanitize_key((string)($_POST['kind'] ?? 'own')),
            'inactive' => !empty($_POST['inactive']),
            'aliases' => sanitize_textarea_field(wp_unslash((string)($_POST['aliases'] ?? ''))),
            'image_ids' => array_map('absint', (array)($_POST['image_ids'] ?? [])),
            'project_ids' => array_map('absint', (array)($_POST['project_ids'] ?? [])),
            'replace_projects' => true,
            // A name saved here is kept by the Cardex sync (9CP).
            'lock_name' => true,
        ];
        try {
            // 9D3: existing products use their true client ownership, never a submitted tenant id.
            $existing = $productId ? Catalog::get($productId) : null;
            if ($productId && !$existing) throw new \RuntimeException('Produto não encontrado.');
            $ownerClient = $existing ? (int)$existing['client_id'] : (int)$data['client_id'];
            $data['client_id'] = $ownerClient;
            $newPicker = array_key_exists('category_choice', $_POST);
            $selected = $newPicker
                ? sanitize_text_field(wp_unslash((string)$_POST['category_choice']))
                : ((string)$data['category'] !== '' ? '__new__' : ''); // still accept an old form left open during upgrade
            $newName = $newPicker
                ? sanitize_text_field(wp_unslash((string)($_POST['category_new'] ?? '')))
                : (string)$data['category'];
            $data['category'] = CategoryMapping::catalogCategorySelection(
                $ownerClient, $selected, $newName, (string)($existing['category'] ?? '')
            );
            $id = Catalog::save($productId, $data);
            // A category becomes reusable only after the product save succeeds.
            if ($data['category'] !== '') CategoryMapping::rememberCatalogCategory($ownerClient, $data['category']);
            self::flash('success', $productId ? 'Produto guardado.' : 'Produto criado no catálogo.');
        } catch (\Throwable $e) {
            $id = $productId ?: 'new';
            self::flash('error', $e->getMessage(), $productId ? [] : $data);
        }
        self::redirect_back(['edit' => $id]);
    }

    public static function handle_merge(): void {
        $sourceId = absint($_POST['source_id'] ?? 0);
        self::guard('fieldflow_catalog_merge_' . $sourceId);
        $targetId = absint($_POST['target_id'] ?? 0);
        $editId = $targetId;
        try {
            Catalog::merge($sourceId, $targetId);
            self::flash('success', 'Produtos juntos. Ficou um só registo com as imagens, os nomes e os rótulos dos dois.');
        } catch (\Throwable $e) {
            $editId = $sourceId;
            self::flash('error', $e->getMessage());
        }
        // From the duplicates panel the list stays open for the next pair.
        self::redirect_back(!empty($_POST['from_duplicates']) ? [] : ['edit' => $editId]);
    }

    public static function handle_not_duplicate(): void {
        self::guard('fieldflow_catalog_not_duplicate');
        try {
            Catalog::markDistinct(absint($_POST['client_id'] ?? 0), absint($_POST['product_a'] ?? 0), absint($_POST['product_b'] ?? 0));
            self::flash('success', 'Marcados como produtos diferentes. Não voltam a ser sugeridos.');
        } catch (\Throwable $e) {
            self::flash('error', $e->getMessage());
        }
        self::redirect_back();
    }

    public static function handle_delete(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        $productId = absint($_GET['product_id'] ?? 0);
        check_admin_referer('fieldflow_catalog_delete_' . $productId);
        $clientId = absint($_GET['client_id'] ?? 0);
        $args = ['tab' => 'catalog', 'client_id' => $clientId];
        try {
            Catalog::delete($productId);
            self::flash('success', 'Produto apagado do catálogo.');
        } catch (\Throwable $e) {
            $args['edit'] = $productId;
            self::flash('error', $e->getMessage());
        }
        self::redirect_to($args);
    }

    public static function handle_reuse(): void {
        self::guard('fieldflow_catalog_reuse');
        $clientId = absint($_POST['client_id'] ?? 0);
        $ids = array_map('absint', (array)($_POST['product_ids'] ?? []));
        $kind = sanitize_key((string)($_POST['kind_mode'] ?? 'keep'));
        try {
            if (!$ids) throw new \RuntimeException('Seleciona pelo menos um produto.');
            $stats = Catalog::copyFromClient($clientId, $ids, $kind);
            $parts = [(int)$stats['copied'] . ' produto(s) copiado(s)'];
            if ($stats['linked']) $parts[] = (int)$stats['linked'] . ' já existiam e ficaram ligados à origem';
            if ($stats['images_added']) $parts[] = (int)$stats['images_added'] . ' receberam imagens';
            if ($stats['skipped']) $parts[] = (int)$stats['skipped'] . ' ignorado(s)';
            self::flash('success', implode(', ', $parts) . '.');
        } catch (\Throwable $e) {
            self::flash('error', $e->getMessage());
        }
        self::redirect_back();
    }

    public static function handle_refresh_source(): void {
        $productId = absint($_POST['product_id'] ?? 0);
        self::guard('fieldflow_catalog_refresh_source_' . $productId);
        try {
            $count = Catalog::refreshImagesFromSource($productId);
            self::flash('success', 'Imagens atualizadas a partir da origem (' . $count . ' na origem).');
        } catch (\Throwable $e) {
            self::flash('error', $e->getMessage());
        }
        self::redirect_back(['edit' => $productId]);
    }

    public static function handle_map_label(): void {
        self::guard('fieldflow_catalog_map_label');
        $clientId = absint($_POST['client_id'] ?? 0);
        $label = sanitize_text_field(wp_unslash((string)($_POST['label'] ?? '')));
        $ids = array_map('absint', (array)($_POST['product_ids'] ?? []));
        try {
            $extra = trim(sanitize_text_field(wp_unslash((string)($_POST['extra_product'] ?? ''))));
            if ($extra !== '') {
                $extraId = 0;
                if (preg_match('/^#?(\d+)\b/', $extra, $m)) $extraId = (int)$m[1];
                if (!$extraId) {
                    $wanted = Catalog::normalizeText($extra);
                    foreach (Catalog::clientIndex($clientId) as $row) {
                        if (Catalog::normalizeText($row['name']) === $wanted) { $extraId = (int)$row['id']; break; }
                    }
                }
                if (!$extraId) throw new \RuntimeException('Não encontrei "' . $extra . '" no catálogo deste cliente. Escolhe um produto da lista ou cria-o.');
                $ids[] = $extraId;
            }
            $created = false;
            if (!empty($_POST['create_product'])) {
                $kind = sanitize_key((string)($_POST['create_kind'] ?? 'competitor')) === 'own' ? 'own' : 'competitor';
                $existing = Catalog::findByKeys($clientId, Catalog::identityKeys('', $label, null));
                if (!$existing) {
                    $existing = Catalog::save(0, [
                        'client_id' => $clientId,
                        'name' => $label,
                        'kind' => $kind,
                        'origin' => 'briefing',
                        'project_ids' => array_map('absint', (array)($_POST['label_projects'] ?? [])),
                    ]);
                    $created = true;
                }
                $ids[] = $existing;
            }
            $count = Catalog::mapLabel($clientId, $label, $ids);
            if ($created) $text = '"' . $label . '" foi criado no catálogo e ligado ao rótulo. Junta-lhe imagens no Catálogo visual.';
            elseif ($count) $text = '"' . $label . '" ligado a ' . $count . ' produto(s).';
            else $text = 'O rótulo "' . $label . '" ficou sem produtos.';
            self::flash('success', $text);
        } catch (\Throwable $e) {
            self::flash('error', $e->getMessage());
        }
        self::redirect_back();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private static array $flashOld = [];

    private static function guard(string $action): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer($action, 'fieldflow_catalog_nonce');
    }

    private static function flash(string $type, string $text, array $old = []): void {
        set_transient('fieldflow_catalog_flash_' . get_current_user_id(), ['type' => $type, 'text' => $text, 'old' => $old], 120);
    }

    private static function render_flash(): void {
        $key = 'fieldflow_catalog_flash_' . get_current_user_id();
        $flash = get_transient($key);
        if (!is_array($flash) || empty($flash['text'])) return;
        delete_transient($key);
        self::$flashOld = is_array($flash['old'] ?? null) ? $flash['old'] : [];
        $class = ($flash['type'] ?? '') === 'error' ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html((string)$flash['text']) . '</p></div>';
    }

    private static function return_fields(array $args): void {
        foreach ($args as $key => $value) {
            if ($value === '' || $value === 0 || $value === null) continue;
            echo '<input type="hidden" name="ret[' . esc_attr($key) . ']" value="' . esc_attr((string)$value) . '">';
        }
    }

    private static function redirect_back(array $extra = []): void {
        $ret = (array)($_POST['ret'] ?? []);
        $args = [];
        foreach (['tab', 'kind', 'images', 'edit', 'view'] as $key) if (isset($ret[$key])) $args[$key] = sanitize_key((string)$ret[$key]);
        foreach (['client_id', 'project_id', 'paged', 'from'] as $key) if (!empty($ret[$key])) $args[$key] = absint($ret[$key]);
        if (isset($ret['s'])) $args['s'] = sanitize_text_field(wp_unslash((string)$ret['s']));
        $anchor = isset($ret['anchor']) ? sanitize_key((string)$ret['anchor']) : '';
        self::redirect_to(array_merge($args, $extra), $anchor);
    }

    private static function redirect_to(array $args, string $anchor = ''): void {
        $url = self::url(array_filter($args, static function($value) { return $value !== '' && $value !== 0 && $value !== null; }));
        if ($anchor !== '') $url .= '#' . $anchor;
        elseif (!empty($args['edit'])) $url .= '#ff-cat-editor';
        wp_safe_redirect($url);
        exit;
    }

    private static function url(array $args = []): string {
        $args = array_filter($args, static function($value) { return $value !== '' && $value !== 0 && $value !== null; });
        return add_query_arg(array_merge(['page' => self::PAGE], $args), admin_url('admin.php'));
    }

    private static function list_filters(): array {
        $filters = [];
        $kind = sanitize_key((string)($_GET['kind'] ?? ''));
        if (in_array($kind, ['own', 'competitor'], true)) $filters['kind'] = $kind;
        $images = sanitize_key((string)($_GET['images'] ?? ''));
        if (in_array($images, ['missing', 'ready'], true)) $filters['images'] = $images;
        $search = trim(sanitize_text_field(wp_unslash((string)($_GET['s'] ?? ''))));
        if ($search !== '') $filters['s'] = $search;
        return $filters;
    }

    private static function clients(): array {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT id, name FROM ' . $wpdb->prefix . 'routespro_clients ORDER BY name ASC, id ASC', ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $row) $out[(int)$row['id']] = (string)$row['name'];
        return $out;
    }

    private static function selected_client(array $clients, array $counts): int {
        $requested = absint($_GET['client_id'] ?? 0);
        if ($requested && isset($clients[$requested])) return $requested;
        foreach ($clients as $id => $name) if (!empty($counts[$id])) return (int)$id;
        return $clients ? (int)array_key_first($clients) : 0;
    }

    private static function projects(int $clientId): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT id, name, status FROM ' . $wpdb->prefix . "routespro_projects WHERE client_id=%d ORDER BY (status='active') DESC, name ASC", $clientId), ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $row) $out[(int)$row['id']] = ['name' => (string)$row['name'], 'status' => (string)($row['status'] ?: 'active')];
        return $out;
    }

    /** Insignias where the product is sold (from its Cardex), as small chips. */
    private static function insignia_chips(array $row): string {
        $insignias = Catalog::insigniasForCardexIds((array)($row['cardex_ids'] ?? []));
        if (!$insignias) return '';
        $html = '<span class="ff-cat-chips">';
        foreach ($insignias as $insignia) $html .= '<span class="ff-cat-chip">' . esc_html($insignia) . '</span>';
        return $html . '</span>';
    }

    /** Probable duplicates (9CP): never merged automatically, one click per pair. */
    private static function render_duplicates(int $clientId, int $projectId): void {
        $pairs = Catalog::duplicateSuggestions($clientId, 30);
        if (!$pairs) return;
        $index = Catalog::clientIndex($clientId);
        echo '<details class="ff-cat-dups" id="ff-cat-dups"' . (count($pairs) <= 6 ? ' open' : '') . '><summary><strong>Possíveis duplicados (' . count($pairs) . ')</strong> <span class="ff-cat-muted">Nomes muito parecidos, com a mesma capacidade, que nunca aparecem juntos na mesma insígnia. Confirma antes de juntar.</span></summary><ul>';
        foreach ($pairs as $pair) {
            $keep = $index[(int)$pair['keep']] ?? null;
            $drop = $index[(int)$pair['drop']] ?? null;
            if (!$keep || !$drop) continue;
            echo '<li class="ff-cat-dup">';
            foreach ([[$keep, 'Fica'], [$drop, 'Junta-se']] as [$row, $role]) {
                echo '<span class="ff-cat-dup-product">' . self::thumb_html($row, 40) . '<span><small>' . esc_html($role) . '</small><strong>' . esc_html($row['name']) . '</strong>' . self::insignia_chips($row) . '</span></span>';
            }
            echo '<span class="ff-cat-dup-actions">';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="fieldflow_catalog_merge"><input type="hidden" name="source_id" value="' . (int)$drop['id'] . '"><input type="hidden" name="target_id" value="' . (int)$keep['id'] . '"><input type="hidden" name="from_duplicates" value="1">';
            self::return_fields(['tab' => 'catalog', 'client_id' => $clientId, 'project_id' => $projectId, 'anchor' => 'ff-cat-dups'] + self::list_filters());
            wp_nonce_field('fieldflow_catalog_merge_' . (int)$drop['id'], 'fieldflow_catalog_nonce');
            echo '<button class="button button-primary button-small">Juntar</button></form>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="fieldflow_catalog_not_duplicate"><input type="hidden" name="client_id" value="' . (int)$clientId . '"><input type="hidden" name="product_a" value="' . (int)$keep['id'] . '"><input type="hidden" name="product_b" value="' . (int)$drop['id'] . '">';
            self::return_fields(['tab' => 'catalog', 'client_id' => $clientId, 'project_id' => $projectId, 'anchor' => 'ff-cat-dups'] + self::list_filters());
            wp_nonce_field('fieldflow_catalog_not_duplicate', 'fieldflow_catalog_nonce');
            echo '<button class="button button-small">São diferentes</button></form>';
            echo '</span></li>';
        }
        echo '</ul></details>';
    }

    private static function cardex_names(array $ids): array {
        $names = [];
        foreach ($ids as $id) {
            $cardex = class_exists('\\RoutesPro\\Forms\\ProductCardex') ? \RoutesPro\Forms\ProductCardex::get_cardex((int)$id) : null;
            $names[] = $cardex ? (string)$cardex['name'] . ' (#' . (int)$id . ')' : '#' . (int)$id . ' (removido)';
        }
        return $names;
    }

    /** Works with a full product (images rows) or a client index row (image_ids). */
    private static function thumb_html(array $row, int $size = 48): string {
        $url = '';
        if (!empty($row['images'][0]['thumb'])) $url = (string)$row['images'][0]['thumb'];
        elseif (!empty($row['image_ids'][0])) {
            $src = wp_get_attachment_image_src((int)$row['image_ids'][0], 'thumbnail');
            $url = $src ? (string)$src[0] : '';
        }
        $style = 'width:' . $size . 'px;height:' . $size . 'px';
        if ($url !== '') return '<span class="ff-cat-thumb" style="' . esc_attr($style) . '"><img src="' . esc_url($url) . '" alt="" loading="lazy"></span>';
        $initials = strtoupper(substr(Catalog::normalizeText((string)($row['name'] ?? '')), 0, 2));
        return '<span class="ff-cat-thumb is-empty" style="' . esc_attr($style) . '" title="Sem imagem">' . esc_html($initials) . '</span>';
    }

    private static function image_item_html(int $id, string $thumb, bool $primary, bool $field = false): string {
        return '<div class="ff-cat-image' . ($primary ? ' is-primary' : '') . ($field ? ' is-field' : '') . '" data-id="' . $id . '"><img src="' . esc_url($thumb) . '" alt=""><input type="hidden" name="image_ids[]" value="' . $id . '"><span class="ff-cat-image-tag">Principal</span>' . ($field ? '<span class="ff-cat-field-tag" title="Fotografada por um operativo no terreno">Terreno</span>' : '') . '<div class="ff-cat-image-actions"><button type="button" class="button-link" data-ff-cat-primary>Principal</button><button type="button" class="button-link ff-cat-remove" data-ff-cat-remove aria-label="Remover imagem">Remover</button></div></div>';
    }

    private static function pagination(array $result, array $args): void {
        if ((int)$result['pages'] <= 1) return;
        echo '<div class="ff-cat-pages"><span>' . (int)$result['total'] . ' produto(s)</span>';
        for ($page = 1; $page <= (int)$result['pages']; $page++) {
            if ($page === (int)$result['page']) echo '<strong>' . $page . '</strong>';
            else echo '<a href="' . esc_url(self::url($args + ['paged' => $page])) . '">' . $page . '</a>';
        }
        echo '</div>';
    }

    public static function styles(): void {
        echo '<style>
.ff-cat-wrap{--ff-cat-border:#dcdcde;--ff-cat-muted:#64748b;--ff-cat-ok:#15803d;--ff-cat-warn:#b45309;--ff-cat-comp:#7c3aed}
.ff-cat-wrap input,.ff-cat-wrap select,.ff-cat-wrap textarea{box-sizing:border-box;max-width:100%}
.ff-cat-filters{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;background:#fff;border:1px solid var(--ff-cat-border);border-radius:12px;padding:12px 14px;margin:0 0 14px}
.ff-cat-filters label{display:flex;flex-direction:column;gap:4px;font-size:12px;color:var(--ff-cat-muted)}
.ff-cat-filters select,.ff-cat-filters input{min-width:150px;max-width:100%}
.ff-cat-search input{min-width:220px}
.ff-cat-summary{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;margin:0 0 8px}
.ff-cat-kpi{background:#fff;border:1px solid var(--ff-cat-border);border-radius:12px;padding:10px 14px;min-width:120px;display:flex;flex-direction:column;gap:2px}
.ff-cat-kpi span{font-size:12px;color:var(--ff-cat-muted)}.ff-cat-kpi strong{font-size:22px;line-height:1.2}
.ff-cat-kpi.is-ok strong{color:var(--ff-cat-ok)}.ff-cat-kpi.is-warn strong{color:var(--ff-cat-warn)}
.ff-cat-actions{margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap}.ff-cat-actions form{margin:0}
.ff-cat-sync{margin:6px 0 14px!important}
.ff-cat-layout{display:grid;grid-template-columns:minmax(0,1fr);gap:16px;align-items:start}
.ff-cat-layout.has-editor{grid-template-columns:minmax(0,1fr) minmax(340px,440px)}
.ff-cat-table td{vertical-align:middle}.ff-cat-col-thumb{width:56px}.ff-cat-col-action{text-align:right;white-space:nowrap}
.ff-cat-table tr.is-editing td{background:#eff6ff}
.ff-cat-muted{color:var(--ff-cat-muted);font-size:12px}
.ff-cat-thumb{display:inline-flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;border-radius:8px;background:#fff;overflow:hidden;flex:0 0 auto}
.ff-cat-thumb img{width:100%;height:100%;object-fit:contain}
.ff-cat-thumb.is-empty{background:#f1f5f9;color:#94a3b8;font-weight:700;font-size:12px}
.ff-cat-badge{display:inline-block;white-space:nowrap;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:600;background:#e2e8f0;color:#1e293b}
.ff-cat-badge.is-ok{background:#dcfce7;color:var(--ff-cat-ok)}.ff-cat-badge.is-warn{background:#fef3c7;color:var(--ff-cat-warn)}
.ff-cat-badge.is-comp{background:#ede9fe;color:var(--ff-cat-comp)}.ff-cat-badge.is-off{background:#fee2e2;color:#b91c1c}
.ff-cat-empty{background:#fff;border:1px dashed #cbd5e1;border-radius:12px;padding:18px;color:#334155}
.ff-cat-pages{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:12px 0}.ff-cat-pages span{color:var(--ff-cat-muted);margin-right:6px}
.ff-cat-editor{background:#fff;border:1px solid var(--ff-cat-border);border-radius:14px;padding:14px 16px;position:sticky;top:46px;max-height:calc(100vh - 64px);overflow:auto}
.ff-cat-editor-head{display:flex;justify-content:space-between;align-items:center}.ff-cat-editor-head h2{margin:0}
.ff-cat-close{font-size:24px;text-decoration:none;color:#475569;line-height:1}
.ff-cat-field{display:flex;flex-direction:column;gap:4px;margin:10px 0;border:0;padding:0}
.ff-cat-field>span{font-weight:600;font-size:12px;color:#334155}.ff-cat-field input[type=text],.ff-cat-field input[type=number],.ff-cat-field textarea{width:100%}
.ff-cat-field small{color:var(--ff-cat-muted)}
/* 9D3: mobile-first catalog category selector, scoped to the current client. */
.ff-cat-category-picker>label:first-child{font-weight:600;font-size:12px;color:#334155}
.ff-cat-category-picker select{width:100%;min-height:42px;border-radius:9px;border:1px solid #dce4ea;background:#fff;font-weight:600;padding:6px 12px}
.ff-cat-new-category{display:flex;flex-direction:column;gap:5px;margin-top:7px;color:#334155;font-size:12px;font-weight:600}
.ff-cat-new-category[hidden]{display:none!important}
.ff-cat-new-category input[type=text]{width:100%;min-height:42px;border:1px solid #f2622e;border-radius:9px;padding:8px 12px;font-weight:500}
.ff-cat-category-picker small{line-height:1.5;color:#64748b}
.ff-cat-grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}
.ff-cat-radios,.ff-cat-checks{display:flex;flex-wrap:wrap;gap:6px 14px;padding-top:4px}
.ff-cat-checks{max-height:140px;overflow:auto}
.ff-cat-inline{display:block;margin:8px 0 12px}
.ff-cat-editor-actions{display:flex;gap:8px;flex-wrap:wrap;position:sticky;bottom:-14px;background:#fff;padding:10px 0}
.ff-cat-images{border:1px solid #e2e8f0;border-radius:12px;padding:10px;background:#f8fafc}
.ff-cat-images-head{display:flex;justify-content:space-between;margin-bottom:8px}.ff-cat-images-head span{color:var(--ff-cat-muted);font-size:12px}
.ff-cat-images-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(86px,1fr));gap:8px}
.ff-cat-image{position:relative;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:4px;display:flex;flex-direction:column;align-items:center}
.ff-cat-image img{width:100%;height:78px;object-fit:contain}
.ff-cat-image.is-primary{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
.ff-cat-image-tag{display:none;position:absolute;top:4px;left:4px;background:#2271b1;color:#fff;font-size:10px;border-radius:999px;padding:1px 6px}
.ff-cat-image.is-primary .ff-cat-image-tag{display:block}.ff-cat-image.is-primary [data-ff-cat-primary]{display:none}
.ff-cat-image-actions{display:flex;flex-wrap:wrap;gap:2px 8px;justify-content:center;font-size:11px}.ff-cat-image-actions .button-link{font-size:11px}.ff-cat-remove{color:#b91c1c!important}
.ff-cat-images-empty{display:none;color:var(--ff-cat-muted);margin:4px 0 8px}.ff-cat-images.is-empty .ff-cat-images-empty{display:block}
.ff-cat-sources{border-top:1px solid #e2e8f0;margin-top:8px;padding-top:4px}.ff-cat-sources h3,.ff-cat-merge h3{font-size:13px;margin:10px 0 6px}
.ff-cat-sources ul{margin:0;padding:0;list-style:none}.ff-cat-sources li{display:flex;justify-content:space-between;gap:10px;font-size:12px}.ff-cat-sources li span{color:var(--ff-cat-muted)}
.ff-cat-merge{border-top:1px solid #e2e8f0;margin-top:10px}.ff-cat-merge select{max-width:100%;width:100%;margin-bottom:6px}
.ff-cat-delete{margin-top:14px}.ff-cat-delete a{color:#b91c1c}
.ff-cat-refresh{margin:8px 0 0}
.ff-cat-chips{display:flex;flex-wrap:wrap;gap:4px;margin-top:4px}
.ff-cat-chip{display:inline-block;border:1px solid #cbd5e1;border-radius:999px;padding:0 7px;font-size:11px;line-height:18px;color:#334155;background:#f8fafc;white-space:nowrap}
.ff-cat-field-tag{position:absolute;top:4px;right:4px;background:#0f766e;color:#fff;font-size:10px;border-radius:999px;padding:1px 6px}
.ff-cat-rebuild{margin:6px 0 12px!important}
.ff-cat-dups{background:#fff;border:1px solid #fcd34d;border-radius:12px;padding:10px 14px;margin:0 0 14px}
.ff-cat-dups summary{cursor:pointer;display:flex;flex-wrap:wrap;gap:4px 10px;align-items:baseline}
.ff-cat-dups ul{margin:10px 0 0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px}
.ff-cat-dup{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto;gap:10px;align-items:center;border-top:1px solid #f1f5f9;padding-top:8px}
.ff-cat-dup-product{display:flex;gap:8px;align-items:center;min-width:0}.ff-cat-dup-product>span:last-child{display:flex;flex-direction:column;min-width:0}
.ff-cat-dup-product small{color:var(--ff-cat-muted);font-size:11px}.ff-cat-dup-product strong{overflow-wrap:anywhere}
.ff-cat-dup-actions{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.ff-cat-dup-actions form{margin:0}
.ff-cat-reuse-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;background:#fff;border:1px solid var(--ff-cat-border);border-radius:12px;padding:12px 14px;margin:0 0 14px}.ff-cat-reuse-head h2{margin:0 0 4px}.ff-cat-reuse-head p{margin:0;max-width:820px}
.ff-cat-col-check{width:28px}.ff-cat-inline-check{font-weight:600}
.ff-cat-reuse-bar{position:sticky;bottom:0;display:flex;flex-wrap:wrap;gap:10px 14px;align-items:center;background:#fff;border:1px solid var(--ff-cat-border);border-radius:12px;padding:10px 14px;margin-top:10px;box-shadow:0 -6px 18px rgba(15,23,42,.06)}
.ff-cat-reuse-bar .ff-cat-radios{padding:0;align-items:center}.ff-cat-reuse-bar .ff-cat-radios>span{font-weight:600}
.ff-cat-labels{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:12px}
.ff-cat-label{background:#fff;border:1px solid var(--ff-cat-border);border-left:4px solid #f59e0b;border-radius:12px;padding:12px 14px}
.ff-cat-label.is-mapped{border-left-color:#16a34a}
.ff-cat-label header{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
.ff-cat-label-meta{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-top:4px}
.ff-cat-state{font-size:11px;font-weight:700;color:var(--ff-cat-muted);text-transform:uppercase;white-space:nowrap}
.ff-cat-candidates{display:flex;flex-direction:column;gap:6px;margin:10px 0}
.ff-cat-candidate{display:flex;gap:8px;align-items:center;border:1px solid #e2e8f0;border-radius:10px;padding:6px 8px;cursor:pointer}
.ff-cat-candidate span{display:flex;flex-direction:column;min-width:0}.ff-cat-candidate small{color:var(--ff-cat-muted)}
.ff-cat-candidate:has(input:checked){border-color:#16a34a;background:#f0fdf4}
.ff-cat-label-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.ff-cat-label-actions input{flex:1 1 200px;min-width:0}
@media(max-width:1100px){.ff-cat-layout.has-editor{grid-template-columns:minmax(0,1fr)}.ff-cat-editor{position:static;max-height:none;order:-1}}
@media(max-width:782px){
 .ff-cat-filters label,.ff-cat-filters select,.ff-cat-filters input{width:100%}
 .ff-cat-actions{margin-left:0;width:100%}
 .ff-cat-table thead{display:none}
 .ff-cat-table,.ff-cat-table tbody{display:block}
 .ff-cat-table tr{display:grid;grid-template-columns:56px 1fr auto;gap:2px 10px;padding:10px;border-bottom:1px solid #e2e8f0}
 .ff-cat-table td{display:block;padding:0!important;border:0}
 .ff-cat-table td.ff-cat-col-thumb{grid-row:span 4}
 .ff-cat-table td.ff-cat-col-check{grid-column:3;grid-row:1}
 .ff-cat-reuse-head{flex-direction:column}
 .ff-cat-table td.ff-cat-col-action{grid-column:3;grid-row:1}
 .ff-cat-table td[data-label]{grid-column:2 / span 2;font-size:12px}
 .ff-cat-table td[data-label]::before{content:attr(data-label) ": ";color:var(--ff-cat-muted)}
 .ff-cat-grid2{grid-template-columns:1fr}
 .ff-cat-labels{grid-template-columns:1fr}
 .ff-cat-dup{grid-template-columns:1fr}.ff-cat-dup-actions{justify-content:flex-start}
}
/* 9D2 premium 4-area navigation and scoped review queue. */
.ff-cat-hub-title{display:flex;align-items:center;gap:12px;margin:10px 0 14px;color:#0f172a}.ff-cat-hub-title>.dashicons{width:46px;height:46px;display:grid;place-items:center;border-radius:14px;background:#fff1e8;color:#ec662f;font-size:22px}.ff-cat-hub-title div{display:grid;gap:3px}.ff-cat-hub-title strong{font-size:17px}.ff-cat-hub-title small{font-size:12px;color:#64748b}
.ff-catalog-tabs{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:0 0 18px!important;padding:0!important;border:0!important;background:transparent!important}.ff-catalog-tabs .nav-tab{margin:0!important;display:flex;align-items:center;gap:10px;min-width:0;background:#fff!important;border:1px solid #dce4ea!important;border-radius:16px!important;padding:15px 13px!important;color:#334155!important;text-decoration:none!important;box-shadow:0 4px 14px rgba(15,23,42,.04)}.ff-catalog-tabs .nav-tab .dashicons{color:#f2622e;flex:0 0 22px}.ff-catalog-tabs .nav-tab span:last-child{display:grid;gap:3px}.ff-catalog-tabs .nav-tab strong{font-size:13px;white-space:normal}.ff-catalog-tabs .nav-tab small{font-size:11px;font-weight:400;color:#64748b;white-space:normal}.ff-catalog-tabs .nav-tab-active{border-color:#f2622e!important;background:#fff7ed!important;box-shadow:0 8px 20px rgba(242,98,46,.12)!important}
.ff-cat-qmap{margin:18px 0 23px;background:white;border:1px solid #dce4ea;border-radius:18px;overflow:hidden;box-shadow:0 8px 26px rgba(15,23,42,.04)}.ff-cat-qmap-cta{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.ff-cat-qmap-cta form{margin:0}.ff-cat-qmap-head{display:flex;justify-content:space-between;align-items:start;gap:12px;padding:18px 20px}.ff-cat-qmap-head h2{margin:3px 0 7px}.ff-cat-qmap-head p{margin:0;color:#64748b;max-width:760px}.ff-cat-qmap-kicker{font-size:11px;text-transform:uppercase;font-weight:800;letter-spacing:.07em;color:#c2410c}.ff-cat-qmap-kpis{display:flex;flex-wrap:wrap;gap:12px;padding:0 20px 16px}.ff-cat-qmap-kpis span{padding:8px 12px;background:#f8fafc;border-radius:10px;font-size:12px}.ff-cat-qmap-kpis strong{font-size:17px;margin-right:5px}.ff-cat-qmap-kpis .pending{background:#fff7ed;color:#9a3412}.ff-cat-qmap-list{max-height:720px;overflow-y:auto;border-top:1px solid #eef2f6}.ff-cat-qmap-row{display:grid;grid-template-columns:minmax(200px,1fr) minmax(170px,260px) auto auto;gap:9px;align-items:center;padding:12px 18px;border-top:1px solid #edf2f7;margin:0}.ff-cat-qmap-row:nth-child(even){background:#fbfdff}.ff-cat-qmap-row-content{display:flex;flex-wrap:wrap;gap:5px 9px;align-items:center;min-width:0}.ff-cat-qmap-row-content strong{flex:1 1 100%;overflow-wrap:anywhere}.ff-cat-qmap-source{color:#64748b;font-size:11px}.ff-cat-qmap-row select{max-width:100%;width:100%}
@media(max-width:1050px){.ff-catalog-tabs{grid-template-columns:repeat(2,minmax(0,1fr))}.ff-cat-qmap-row{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}.ff-cat-qmap-row-content{grid-column:1/-1}}
@media(max-width:600px){.ff-catalog-tabs{gap:7px}.ff-catalog-tabs .nav-tab{padding:11px 8px!important;gap:6px}.ff-catalog-tabs .nav-tab small{display:none}.ff-cat-qmap-row{grid-template-columns:1fr 1fr;padding:12px}.ff-cat-qmap-row label,.ff-cat-qmap-row-content{grid-column:1/-1}.ff-cat-qmap-head{flex-direction:column}}
</style>';
    }

    private static function script(): void {
        echo '<script>(function(){
  "use strict";
  // 9D3: show the creation field only when the manager explicitly requests a new category.
  var categoryEditor=document.querySelector("[data-ff-cat-category-editor]");
  if(categoryEditor){
    var categorySelect=categoryEditor.querySelector("[data-ff-cat-category-choice]");
    var categoryNew=categoryEditor.querySelector("[data-ff-cat-new-category]");
    var categoryInput=categoryNew&&categoryNew.querySelector("input");
    function syncCategory(){var active=!!(categorySelect&&categorySelect.value==="__new__"); if(categoryNew)categoryNew.hidden=!active; if(categoryInput){categoryInput.disabled=!active;categoryInput.required=active;}}
    if(categorySelect){categorySelect.addEventListener("change",function(){syncCategory();if(categorySelect.value==="__new__"&&categoryInput)categoryInput.focus();}); syncCategory();}
  }
  var box=document.querySelector("[data-ff-cat-images]"); if(!box) return;
  var list=box.querySelector("[data-ff-cat-list]"), counter=box.querySelector("[data-ff-cat-count]"), max=parseInt(box.getAttribute("data-max")||"12",10)||12;
  function sync(){ var items=list.querySelectorAll(".ff-cat-image"); Array.prototype.forEach.call(items,function(el,i){ el.classList.toggle("is-primary",i===0); }); box.classList.toggle("is-empty",!items.length); if(counter) counter.textContent=items.length+"/"+max; }
  function item(att){ var sizes=att.sizes||{}; var url=(sizes.thumbnail||sizes.medium||{}).url||att.url||""; var el=document.createElement("div"); el.className="ff-cat-image"; el.setAttribute("data-id",String(att.id)); el.innerHTML="<img alt=\"\"><input type=\"hidden\" name=\"image_ids[]\"><span class=\"ff-cat-image-tag\">Principal</span><div class=\"ff-cat-image-actions\"><button type=\"button\" class=\"button-link\" data-ff-cat-primary>Principal</button><button type=\"button\" class=\"button-link ff-cat-remove\" data-ff-cat-remove aria-label=\"Remover imagem\">Remover</button></div>"; el.querySelector("img").src=url; el.querySelector("input").value=String(att.id); return el; }
  box.addEventListener("click",function(e){
    var rm=e.target.closest("[data-ff-cat-remove]"); if(rm){ rm.closest(".ff-cat-image").remove(); sync(); return; }
    var pr=e.target.closest("[data-ff-cat-primary]"); if(pr){ list.insertBefore(pr.closest(".ff-cat-image"),list.firstChild); sync(); }
  });
  var add=box.querySelector("[data-ff-cat-add]"), frame=null;
  if(add){ add.addEventListener("click",function(){
    if(!(window.wp&&wp.media)){ window.alert("A biblioteca de media não está disponível nesta página."); return; }
    if(!frame){ frame=wp.media({title:"Imagens de referência do produto",library:{type:"image"},multiple:"add",button:{text:"Usar imagens"}});
      frame.on("select",function(){ frame.state().get("selection").each(function(model){ var att=model.toJSON(); if(list.querySelector("[data-id=\""+att.id+"\"]")) return; if(list.querySelectorAll(".ff-cat-image").length>=max) return; if(att.type&&att.type!=="image") return; list.appendChild(item(att)); }); sync(); }); }
    frame.open();
  }); }
  sync();
})();</script>';
    }
}
