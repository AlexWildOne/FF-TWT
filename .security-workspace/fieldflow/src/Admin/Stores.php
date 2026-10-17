<?php
namespace RoutesPro\Admin;

use RoutesPro\Stores\StoreProfileService;

if (!defined('ABSPATH')) exit;

final class Stores {
    public const SLUG = 'routespro-stores';

    public static function register_hooks(): void {
        add_action('admin_post_routespro_save_store_profile_admin', [self::class, 'handle_save']);
        add_action('admin_post_routespro_export_stores', [self::class, 'handle_export']);
    }

    public static function render(): void {
        if (!current_user_can('routespro_manage')) return;
        global $wpdb;
        wp_enqueue_style('fieldflow-stores-admin', ROUTESPRO_URL . 'assets/fieldflow-stores-admin.css', [], defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : null);

        if (!StoreProfileService::tableExists()) {
            echo '<div class="wrap fieldflow-stores-admin"><div class="ff-stores-hero"><div><span>Store Intelligence</span><h1>Lojas</h1><p>A estrutura da Ficha da Loja ainda não foi criada. Atualiza ou reativa o FieldFlow para concluir a migração da base de dados.</p></div></div><div class="notice notice-error"><p>Não foram executadas consultas à nova área enquanto o schema estiver incompleto.</p></div></div>';
            return;
        }

        $filters = self::filters($_GET);
        $total = 0;
        $rows = self::queryRows($filters, (int)$filters['per_page'], ((int)$filters['page'] - 1) * (int)$filters['per_page'], $total);
        $pages = max(1, (int)ceil($total / max(1, (int)$filters['per_page'])));
        if ((int)$filters['page'] > $pages) $filters['page'] = $pages;

        $px = $wpdb->prefix . 'routespro_';
        $clients = $wpdb->get_results("SELECT id,name FROM {$px}clients ORDER BY name ASC", ARRAY_A) ?: [];
        $projects = $wpdb->get_results("SELECT p.id,p.name,p.client_id,c.name AS client_name FROM {$px}projects p LEFT JOIN {$px}clients c ON c.id=p.client_id ORDER BY c.name ASC,p.name ASC LIMIT 1000", ARRAY_A) ?: [];
        $merchandisers = $wpdb->get_results(
            "SELECT DISTINCT u.ID,u.display_name
             FROM {$px}campaign_locations cl
             INNER JOIN {$wpdb->users} u ON u.ID=cl.assigned_to
             WHERE cl.is_active=1 AND cl.assigned_to>0
             ORDER BY u.display_name ASC"
        ) ?: [];
        $summary = self::summary($filters);
        $storeFormats = $wpdb->get_col("SELECT DISTINCT store_format FROM {$px}location_profiles WHERE TRIM(COALESCE(store_format,''))<>'' ORDER BY store_format ASC") ?: [];
        $assortmentProfiles = $wpdb->get_col("SELECT DISTINCT assortment_profile FROM {$px}location_profiles WHERE TRIM(COALESCE(assortment_profile,''))<>'' ORDER BY assortment_profile ASC") ?: [];
        $editId = absint($_GET['edit_store'] ?? 0);
        $editSnapshot = $editId ? StoreProfileService::snapshot($editId, absint($filters['project_id'] ?? 0), 0) : null;
        if (is_wp_error($editSnapshot)) $editSnapshot = null;

        $notice = sanitize_key((string)($_GET['store_notice'] ?? ''));
        echo '<div class="wrap fieldflow-stores-admin">';
        echo '<div class="ff-stores-hero"><div><span>Store Intelligence</span><h1>Lojas</h1><p>Ficha operacional viva por PDV, separada dos dados de origem e ligada ao histórico de visitas.</p></div><div class="ff-stores-hero-badge">FieldFlow ' . esc_html(defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '') . '</div></div>';
        if ($notice === 'saved') echo '<div class="notice notice-success is-dismissible"><p>Ficha da loja atualizada com sucesso.</p></div>';
        if ($notice === 'error') echo '<div class="notice notice-error"><p>Não foi possível guardar a ficha da loja.</p></div>';

        self::renderSummary($summary);
        self::renderFilters($filters, $clients, $projects, $merchandisers, $storeFormats, $assortmentProfiles);
        if ($editSnapshot) self::renderEditor($editSnapshot, $filters);
        self::renderTable($rows, $filters, $total, $pages);
        echo '</div>';
    }

    public static function handle_save(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.', 'FieldFlow', ['response' => 403]);
        $locationId = absint($_POST['location_id'] ?? 0);
        if (!$locationId) wp_die('Loja inválida.', 'FieldFlow', ['response' => 400]);
        check_admin_referer('routespro_store_profile_' . $locationId);
        $payload = [
            'responsible_name' => wp_unslash((string)($_POST['responsible_name'] ?? '')),
            'store_format' => wp_unslash((string)($_POST['store_format'] ?? '')),
            'assortment_profile' => wp_unslash((string)($_POST['assortment_profile'] ?? 'general')),
            'operational_phone' => wp_unslash((string)($_POST['operational_phone'] ?? '')),
            'operational_email' => wp_unslash((string)($_POST['operational_email'] ?? '')),
            'pending_status' => wp_unslash((string)($_POST['pending_status'] ?? 'none')),
            'pending_actions' => wp_unslash((string)($_POST['pending_actions'] ?? '')),
            'store_notes' => wp_unslash((string)($_POST['store_notes'] ?? '')),
            'revision_no' => absint($_POST['revision_no'] ?? 0),
        ];
        $projectId = absint($_POST['project_id'] ?? 0);
        $result = StoreProfileService::save($locationId, $payload, get_current_user_id(), $projectId, 0);
        $fallback = admin_url('admin.php?page=' . self::SLUG . '&edit_store=' . $locationId);
        $redirect = wp_validate_redirect(esc_url_raw(wp_unslash((string)($_POST['redirect_to'] ?? ''))), $fallback);
        $redirect = add_query_arg('store_notice', is_wp_error($result) ? 'error' : 'saved', $redirect);
        wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_export(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.', 'FieldFlow', ['response' => 403]);
        check_admin_referer('routespro_export_stores');
        $filters = self::filters($_GET);
        $format = sanitize_key((string)($_GET['format'] ?? 'csv'));
        if (!in_array($format, ['csv','xls','pdf'], true)) $format = 'csv';
        $total = 0;
        $rows = self::queryRows($filters, 5000, 0, $total);
        $stamp = gmdate('Ymd-His');
        if ($format === 'csv') self::streamCsv($rows, 'fieldflow-lojas-' . $stamp . '.csv');
        if ($format === 'xls') self::streamXls($rows, 'fieldflow-lojas-' . $stamp . '.xls');
        self::streamPdf($rows, 'fieldflow-lojas-' . $stamp . '.pdf');
    }

    private static function filters(array $source): array {
        $perPage = absint($source['per_page'] ?? 50);
        if (!in_array($perPage, [25,50,100], true)) $perPage = 50;
        return [
            'client_id' => absint($source['client_id'] ?? 0),
            'project_id' => absint($source['project_id'] ?? 0),
            'merchandiser_id' => absint($source['merchandiser_id'] ?? 0),
            'pending' => sanitize_key((string)($source['pending'] ?? '')),
            'profile' => sanitize_key((string)($source['profile'] ?? '')),
            'visit' => sanitize_key((string)($source['visit'] ?? '')),
            'store_format' => sanitize_text_field(wp_unslash((string)($source['store_format'] ?? ''))),
            'assortment_profile' => sanitize_key((string)($source['assortment_profile'] ?? '')),
            'q' => sanitize_text_field(wp_unslash((string)($source['q'] ?? ''))),
            'per_page' => $perPage,
            'page' => max(1, absint($source['paged'] ?? $source['page_num'] ?? 1)),
        ];
    }

    private static function where(array $filters, array &$args): string {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $where = ['1=1'];
        if (!empty($filters['client_id'])) {
            $where[] = "(l.client_id=%d OR EXISTS(SELECT 1 FROM {$px}campaign_locations fcl INNER JOIN {$px}projects fp ON fp.id=fcl.project_id WHERE fcl.location_id=l.id AND fcl.is_active=1 AND fp.client_id=%d))";
            $args[] = (int)$filters['client_id']; $args[] = (int)$filters['client_id'];
        }
        if (!empty($filters['project_id'])) {
            $where[] = "(l.project_id=%d OR EXISTS(SELECT 1 FROM {$px}campaign_locations fpl WHERE fpl.location_id=l.id AND fpl.project_id=%d AND fpl.is_active=1))";
            $args[] = (int)$filters['project_id']; $args[] = (int)$filters['project_id'];
        }
        if (!empty($filters['merchandiser_id'])) {
            $where[] = "EXISTS(SELECT 1 FROM {$px}campaign_locations fmu WHERE fmu.location_id=l.id AND fmu.assigned_to=%d AND fmu.is_active=1)";
            $args[] = (int)$filters['merchandiser_id'];
        }
        if (($filters['pending'] ?? '') === 'open') $where[] = "sp.pending_status='open'";
        if (($filters['pending'] ?? '') === 'none') $where[] = "(sp.pending_status IS NULL OR sp.pending_status<>'open')";
        if (($filters['profile'] ?? '') === 'complete') $where[] = "(COALESCE(sp.responsible_name,'')<>'' AND (COALESCE(sp.operational_phone,'')<>'' OR COALESCE(sp.operational_email,'')<>''))";
        if (($filters['profile'] ?? '') === 'incomplete') $where[] = "NOT (COALESCE(sp.responsible_name,'')<>'' AND (COALESCE(sp.operational_phone,'')<>'' OR COALESCE(sp.operational_email,'')<>''))";
        if (($filters['visit'] ?? '') === 'never') $where[] = "NOT EXISTS(SELECT 1 FROM {$px}form_submissions fsv WHERE fsv.location_id=l.id)";
        if (($filters['visit'] ?? '') === 'visited') $where[] = "EXISTS(SELECT 1 FROM {$px}form_submissions fsv WHERE fsv.location_id=l.id)";
        if (!empty($filters['store_format'])) { $where[] = "sp.store_format=%s"; $args[] = (string)$filters['store_format']; }
        if (!empty($filters['assortment_profile'])) { $where[] = "sp.assortment_profile=%s"; $args[] = (string)$filters['assortment_profile']; }
        if (!empty($filters['q'])) {
            $like = '%' . $wpdb->esc_like((string)$filters['q']) . '%';
            $where[] = "(l.name LIKE %s OR l.address LIKE %s OR l.city LIKE %s OR l.postal_code LIKE %s OR l.phone LIKE %s OR l.email LIKE %s OR sp.responsible_name LIKE %s OR sp.operational_phone LIKE %s OR sp.operational_email LIKE %s OR sp.store_format LIKE %s OR sp.assortment_profile LIKE %s)";
            for ($i=0; $i<11; $i++) $args[] = $like;
        }
        return implode(' AND ', $where);
    }

    private static function queryRows(array $filters, int $limit, int $offset, int &$total): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $args = [];
        $where = self::where($filters, $args);
        $countSql = "SELECT COUNT(DISTINCT l.id) FROM {$px}locations l LEFT JOIN {$px}location_profiles sp ON sp.location_id=l.id WHERE {$where}";
        $total = (int)($args ? $wpdb->get_var($wpdb->prepare($countSql, ...$args)) : $wpdb->get_var($countSql));

        $sql = "SELECT l.id,l.name,l.address,l.city,l.postal_code,l.phone,l.email,l.website,l.source,l.place_id,l.client_id,l.project_id,
                       sp.operational_email,sp.operational_phone,sp.responsible_name,sp.store_format,sp.assortment_profile,sp.assortment_profile_source,sp.assortment_profile_confidence,sp.pending_status,sp.pending_actions,sp.store_notes,sp.revision_no,sp.updated_at,
                       dc.name AS direct_client_name,
                       ca.client_names,ca.project_names,ca.merchandisers,
                       ls.submitted_at AS last_visit_at,ls.status AS last_submission_status,ls.route_stop_id AS last_route_stop_id,
                       vu.display_name AS last_visit_user,vrs.status AS last_stop_status
                FROM {$px}locations l
                LEFT JOIN {$px}location_profiles sp ON sp.location_id=l.id
                LEFT JOIN {$px}clients dc ON dc.id=l.client_id
                LEFT JOIN (
                    SELECT cl.location_id,
                           GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS client_names,
                           GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS project_names,
                           GROUP_CONCAT(DISTINCT u.display_name ORDER BY u.display_name SEPARATOR ', ') AS merchandisers
                    FROM {$px}campaign_locations cl
                    LEFT JOIN {$px}projects p ON p.id=cl.project_id
                    LEFT JOIN {$px}clients c ON c.id=p.client_id
                    LEFT JOIN {$wpdb->users} u ON u.ID=cl.assigned_to
                    WHERE cl.is_active=1
                    GROUP BY cl.location_id
                ) ca ON ca.location_id=l.id
                LEFT JOIN {$px}form_submissions ls ON ls.id=(SELECT fs2.id FROM {$px}form_submissions fs2 WHERE fs2.location_id=l.id ORDER BY COALESCE(fs2.submitted_at,'1970-01-01 00:00:00') DESC,fs2.id DESC LIMIT 1)
                LEFT JOIN {$wpdb->users} vu ON vu.ID=ls.user_id
                LEFT JOIN {$px}route_stops vrs ON vrs.id=ls.route_stop_id
                WHERE {$where}
                ORDER BY l.name ASC,l.id DESC
                LIMIT %d OFFSET %d";
        $queryArgs = array_merge($args, [max(1, min(5000, $limit)), max(0, $offset)]);
        return $wpdb->get_results($wpdb->prepare($sql, ...$queryArgs), ARRAY_A) ?: [];
    }

    private static function summary(array $filters): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $args = [];
        $where = self::where($filters, $args);
        $sql = "SELECT COUNT(DISTINCT l.id) AS stores,
                       SUM(CASE WHEN COALESCE(sp.responsible_name,'')<>'' AND (COALESCE(sp.operational_phone,'')<>'' OR COALESCE(sp.operational_email,'')<>'') THEN 1 ELSE 0 END) AS complete_profiles,
                       SUM(CASE WHEN sp.pending_status='open' THEN 1 ELSE 0 END) AS open_pending,
                       SUM(CASE WHEN NOT EXISTS(SELECT 1 FROM {$px}form_submissions s0 WHERE s0.location_id=l.id) THEN 1 ELSE 0 END) AS never_visited
                FROM {$px}locations l LEFT JOIN {$px}location_profiles sp ON sp.location_id=l.id WHERE {$where}";
        $row = $wpdb->get_row($args ? $wpdb->prepare($sql, ...$args) : $sql, ARRAY_A) ?: [];
        return [
            'stores' => (int)($row['stores'] ?? 0),
            'complete_profiles' => (int)($row['complete_profiles'] ?? 0),
            'open_pending' => (int)($row['open_pending'] ?? 0),
            'never_visited' => (int)($row['never_visited'] ?? 0),
        ];
    }

    private static function renderSummary(array $summary): void {
        echo '<div class="ff-stores-kpis">';
        foreach ([
            ['Lojas', $summary['stores'], 'Resultado atual'],
            ['Perfis completos', $summary['complete_profiles'], 'Responsável + contacto'],
            ['Com pendentes', $summary['open_pending'], 'Atenção operacional'],
            ['Sem visita', $summary['never_visited'], 'Ainda sem reporte'],
        ] as $card) {
            echo '<div class="ff-stores-kpi"><span>' . esc_html((string)$card[0]) . '</span><strong>' . esc_html(number_format_i18n((int)$card[1])) . '</strong><small>' . esc_html((string)$card[2]) . '</small></div>';
        }
        echo '</div>';
    }

    private static function renderFilters(array $filters, array $clients, array $projects, array $merchandisers, array $storeFormats, array $assortmentProfiles): void {
        echo '<form method="get" class="ff-stores-filters"><input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">';
        echo '<label>Pesquisar<input type="search" name="q" value="' . esc_attr((string)$filters['q']) . '" placeholder="Loja, morada, contacto..."></label>';
        echo '<label>Cliente<select name="client_id"><option value="0">Todos</option>';
        foreach ($clients as $c) echo '<option value="' . esc_attr((string)$c['id']) . '" ' . selected((int)$filters['client_id'], (int)$c['id'], false) . '>' . esc_html((string)$c['name']) . '</option>';
        echo '</select></label>';
        echo '<label>Projeto<select name="project_id"><option value="0">Todos</option>';
        foreach ($projects as $p) {
            $label = trim((string)($p['client_name'] ?? '')) !== '' ? (string)$p['client_name'] . ' · ' . (string)$p['name'] : (string)$p['name'];
            echo '<option value="' . esc_attr((string)$p['id']) . '" ' . selected((int)$filters['project_id'], (int)$p['id'], false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Merchan<select name="merchandiser_id"><option value="0">Todos</option>';
        foreach ($merchandisers as $u) echo '<option value="' . esc_attr((string)$u->ID) . '" ' . selected((int)$filters['merchandiser_id'], (int)$u->ID, false) . '>' . esc_html((string)$u->display_name) . '</option>';
        echo '</select></label>';
        self::selectFilter('pending', 'Pendentes', (string)$filters['pending'], [''=>'Todos','open'=>'Com pendentes','none'=>'Sem pendentes']);
        self::selectFilter('profile', 'Ficha', (string)$filters['profile'], [''=>'Todas','complete'=>'Completa','incomplete'=>'Incompleta']);
        self::selectFilter('visit', 'Visitas', (string)$filters['visit'], [''=>'Todas','visited'=>'Já visitadas','never'=>'Sem visita']);
        $formatOptions = [''=>'Todos']; foreach ($storeFormats as $format) { $format = trim((string)$format); if ($format !== '') $formatOptions[$format] = $format; }
        self::selectFilter('store_format', 'Formato', (string)$filters['store_format'], $formatOptions);
        $profileOptions = [''=>'Todos']; foreach ($assortmentProfiles as $profile) { $profile = sanitize_key((string)$profile); if ($profile !== '') $profileOptions[$profile] = self::assortmentLabel($profile); }
        self::selectFilter('assortment_profile', 'Tipo Cardex', (string)$filters['assortment_profile'], $profileOptions);
        self::selectFilter('per_page', 'Por página', (string)$filters['per_page'], ['25'=>'25','50'=>'50','100'=>'100']);
        echo '<div class="ff-stores-filter-actions"><button class="button button-primary" type="submit">Aplicar filtros</button><a class="button" href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">Limpar</a></div>';
        echo '</form>';
    }

    private static function selectFilter(string $name, string $label, string $selectedValue, array $options): void {
        echo '<label>' . esc_html($label) . '<select name="' . esc_attr($name) . '">';
        foreach ($options as $value => $text) echo '<option value="' . esc_attr((string)$value) . '" ' . selected($selectedValue, (string)$value, false) . '>' . esc_html((string)$text) . '</option>';
        echo '</select></label>';
    }

    private static function renderEditor(array $snapshot, array $filters): void {
        $l = (array)$snapshot['location']; $p = (array)$snapshot['profile']; $assortment = (array)($snapshot['assortment'] ?? []);
        $redirect = self::currentListUrl($filters, ['edit_store' => (int)$l['id']]);
        echo '<section class="ff-store-editor" id="ff-store-editor"><div class="ff-store-editor-head"><div><span>Ficha operacional</span><h2>' . esc_html((string)$l['name']) . '</h2><p>Dados de origem protegidos e informação operacional editável.</p></div><a class="button" href="' . esc_url(self::currentListUrl($filters)) . '">Fechar ficha</a></div>';
        echo '<div class="ff-store-master"><div><span>Morada</span><strong>' . esc_html(self::fallback((string)$l['address'])) . '</strong></div><div><span>Localidade</span><strong>' . esc_html(self::fallback(trim((string)$l['postal_code'] . ' ' . (string)$l['city']))) . '</strong></div><div><span>Telefone base</span><strong>' . esc_html(self::fallback((string)$l['phone'])) . '</strong></div><div><span>Email base</span><strong>' . esc_html(self::fallback((string)$l['email'])) . '</strong></div><div><span>Website</span><strong>' . esc_html(self::fallback((string)$l['website'])) . '</strong></div><div><span>Origem</span><strong>' . esc_html(self::fallback((string)$l['source'])) . '</strong></div></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ff-store-editor-form"><input type="hidden" name="action" value="routespro_save_store_profile_admin"><input type="hidden" name="location_id" value="' . esc_attr((string)$l['id']) . '"><input type="hidden" name="project_id" value="' . esc_attr((string)($snapshot['scope']['project_id'] ?? 0)) . '"><input type="hidden" name="revision_no" value="' . esc_attr((string)($p['revision_no'] ?? 0)) . '"><input type="hidden" name="redirect_to" value="' . esc_attr($redirect) . '">';
        wp_nonce_field('routespro_store_profile_' . (int)$l['id']);
        echo '<label>Responsável<input type="text" name="responsible_name" value="' . esc_attr((string)$p['responsible_name']) . '"></label><label>Telefone operacional<input type="tel" name="operational_phone" value="' . esc_attr((string)$p['operational_phone']) . '"></label><label>Email operacional<input type="email" name="operational_email" value="' . esc_attr((string)$p['operational_email']) . '"></label>';
        echo '<label>Formato da loja<input type="text" name="store_format" value="' . esc_attr((string)($p['store_format'] ?? '')) . '" placeholder="Ex.: CNT, MDL, CBD, Hiper, Super"></label>';
        echo '<label>Tipo Cardex / Perfil<select name="assortment_profile">';
        $selectedProfile = (string)($p['assortment_profile'] ?? 'general');
        $profileOptions = is_array($assortment['options'] ?? null) ? (array)$assortment['options'] : [['value'=>'general','label'=>'Geral, Cardex completo']];
        foreach ($profileOptions as $option) { $profileCode=(string)($option['value']??'general'); $profileLabel=(string)($option['label']??$profileCode); echo '<option value="' . esc_attr($profileCode) . '" ' . selected($selectedProfile, $profileCode, false) . '>' . esc_html($profileLabel) . '</option>'; }
        echo '</select><small>' . esc_html(!empty($assortment['cardex_name']) ? 'Cardex ativo: ' . (string)$assortment['cardex_name'] . (!empty($assortment['cumulative']) ? ' · P1-Pn cumulativo' : '') : 'Sem Cardex específico, Geral mantém o sortido completo.') . '</small></label>';
        echo '<label>Estado dos pendentes<select name="pending_status"><option value="none" ' . selected((string)$p['pending_status'], 'none', false) . '>Sem pendentes</option><option value="open" ' . selected((string)$p['pending_status'], 'open', false) . '>Com pendentes</option><option value="resolved" ' . selected((string)$p['pending_status'], 'resolved', false) . '>Resolvidos</option></select></label>';
        echo '<label class="is-wide">Pendentes<textarea name="pending_actions" rows="4">' . esc_textarea((string)$p['pending_actions']) . '</textarea></label><label class="is-wide">Notas permanentes<textarea name="store_notes" rows="4">' . esc_textarea((string)$p['store_notes']) . '</textarea></label><div class="ff-store-editor-actions"><button class="button button-primary" type="submit">Guardar ficha</button></div></form>';
        echo '</section>';
    }

    private static function renderTable(array $rows, array $filters, int $total, int $pages): void {
        echo '<section class="ff-stores-list"><div class="ff-stores-list-head"><div><h2>Listagem de lojas</h2><p>' . esc_html(number_format_i18n($total)) . ' resultado(s).</p></div><div class="ff-stores-export">';
        foreach (['csv'=>'CSV','xls'=>'Excel','pdf'=>'PDF'] as $format => $label) {
            $url = wp_nonce_url(add_query_arg(array_merge(self::filterQueryArgs($filters), ['action'=>'routespro_export_stores','format'=>$format]), admin_url('admin-post.php')), 'routespro_export_stores');
            echo '<a class="button" href="' . esc_url($url) . '">Exportar ' . esc_html($label) . '</a>';
        }
        echo '</div></div><div class="ff-stores-table-wrap"><table class="widefat striped ff-stores-table"><thead><tr><th>Loja</th><th>Cliente / Projeto</th><th>Merchan</th><th>Tipo</th><th>Última visita</th><th>Responsável</th><th>Contacto</th><th>Pendentes</th><th></th></tr></thead><tbody>';
        if (!$rows) echo '<tr><td colspan="9"><div class="ff-stores-empty">Não existem lojas para os filtros selecionados.</div></td></tr>';
        foreach ($rows as $row) {
            $clientProject = trim(implode(' · ', array_filter([(string)($row['direct_client_name'] ?: $row['client_names']), (string)$row['project_names']])));
            $lastDate = !empty($row['last_visit_at']) ? mysql2date('d/m/Y H:i', (string)$row['last_visit_at']) : 'Nunca';
            $lastStatus = self::statusLabel((string)($row['last_stop_status'] ?: $row['last_submission_status']));
            $contact = trim(implode(' · ', array_filter([(string)$row['operational_phone'], (string)$row['operational_email']])));
            $pending = (string)($row['pending_status'] ?? 'none');
            $profileComplete = trim((string)$row['responsible_name']) !== '' && (trim((string)$row['operational_phone']) !== '' || trim((string)$row['operational_email']) !== '');
            $editUrl = self::currentListUrl($filters, ['edit_store'=>(int)$row['id']]);
            echo '<tr><td data-label="Loja"><strong>' . esc_html((string)$row['name']) . '</strong><small>' . esc_html(self::fallback(trim((string)$row['postal_code'] . ' ' . (string)$row['city']))) . '</small><span class="ff-store-mini ' . ($profileComplete ? 'is-ok' : 'is-warn') . '">' . ($profileComplete ? 'Perfil completo' : 'Ficha incompleta') . '</span></td><td data-label="Cliente / Projeto">' . esc_html(self::fallback($clientProject)) . '</td><td data-label="Merchan">' . esc_html(self::fallback((string)$row['merchandisers'])) . '</td><td data-label="Tipo"><strong>' . esc_html(self::assortmentLabel((string)($row['assortment_profile'] ?? 'general'))) . '</strong><small>' . esc_html(self::fallback((string)($row['store_format'] ?? ''))) . '</small></td><td data-label="Última visita"><strong>' . esc_html($lastDate) . '</strong><small>' . esc_html($lastStatus) . '</small></td><td data-label="Responsável">' . esc_html(self::fallback((string)$row['responsible_name'])) . '</td><td data-label="Contacto">' . esc_html(self::fallback($contact)) . '</td><td data-label="Pendentes"><span class="ff-store-pending ' . ($pending === 'open' ? 'is-open' : ($pending === 'resolved' ? 'is-resolved' : 'is-clear')) . '">' . esc_html(self::pendingLabel($pending)) . '</span></td><td class="ff-store-action"><a class="button" href="' . esc_url($editUrl) . '#ff-store-editor">Abrir ficha</a></td></tr>';
        }
        echo '</tbody></table></div>';
        if ($pages > 1) {
            echo '<div class="ff-stores-pagination"><span>Página ' . esc_html((string)$filters['page']) . ' de ' . esc_html((string)$pages) . '</span><div>';
            if ((int)$filters['page'] > 1) echo '<a class="button" href="' . esc_url(self::currentListUrl($filters, ['page_num'=>(int)$filters['page']-1])) . '">Anterior</a>';
            if ((int)$filters['page'] < $pages) echo '<a class="button" href="' . esc_url(self::currentListUrl($filters, ['page_num'=>(int)$filters['page']+1])) . '">Seguinte</a>';
            echo '</div></div>';
        }
        echo '</section>';
    }

    private static function currentListUrl(array $filters, array $extra = []): string {
        return add_query_arg(array_merge(['page'=>self::SLUG], self::filterQueryArgs($filters), $extra), admin_url('admin.php'));
    }

    private static function filterQueryArgs(array $filters): array {
        $out = [];
        foreach (['client_id','project_id','merchandiser_id','pending','profile','visit','store_format','assortment_profile','q','per_page'] as $key) if (($filters[$key] ?? '') !== '' && ($filters[$key] ?? 0) !== 0) $out[$key] = $filters[$key];
        if (!empty($filters['page']) && (int)$filters['page'] > 1) $out['page_num'] = (int)$filters['page'];
        return $out;
    }

    private static function fallback(string $value): string { return trim($value) !== '' ? trim($value) : 'Não disponível'; }
    private static function assortmentLabel(string $profile): string {
        $profile = sanitize_key($profile);
        if ($profile === '' || $profile === 'general') return 'Geral';
        if (preg_match('/^p(\d+)$/', $profile, $m)) return 'P' . (int)$m[1];
        if ($profile === 'fluxo_tenso') return 'Fluxo Tenso';
        return strtoupper(str_replace('_', ' ', $profile));
    }
    private static function pendingLabel(string $status): string { return $status === 'open' ? 'Com pendentes' : ($status === 'resolved' ? 'Resolvidos' : 'Sem pendentes'); }
    private static function statusLabel(string $status): string {
        $status = sanitize_key($status);
        $map = ['completed'=>'Concluída','complete'=>'Concluída','executed'=>'Executada','done'=>'Concluída','submitted'=>'Reporte submetido','in_progress'=>'Em curso','pending'=>'Pendente','failed'=>'Não concluída','cancelled'=>'Cancelada'];
        return $map[$status] ?? ($status !== '' ? ucfirst(str_replace('_',' ',$status)) : 'Sem estado');
    }

    private static function exportRows(array $rows): array {
        $out = [];
        foreach ($rows as $r) $out[] = [
            'Loja'=>(string)$r['name'], 'Morada'=>(string)$r['address'], 'Código Postal'=>(string)$r['postal_code'], 'Localidade'=>(string)$r['city'],
            'Cliente'=>(string)($r['direct_client_name'] ?: $r['client_names']), 'Projeto'=>(string)$r['project_names'], 'Merchan'=>(string)$r['merchandisers'],
            'Última Visita'=>!empty($r['last_visit_at']) ? mysql2date('d/m/Y H:i', (string)$r['last_visit_at']) : 'Nunca', 'Estado'=>self::statusLabel((string)($r['last_stop_status'] ?: $r['last_submission_status'])),
            'Formato Loja'=>(string)($r['store_format'] ?? ''), 'Tipo Cardex'=>self::assortmentLabel((string)($r['assortment_profile'] ?? 'general')), 'Responsável'=>(string)$r['responsible_name'], 'Telefone Operacional'=>(string)$r['operational_phone'], 'Email Operacional'=>(string)$r['operational_email'],
            'Pendentes'=>self::pendingLabel((string)$r['pending_status']), 'Detalhe Pendentes'=>(string)$r['pending_actions'], 'Notas'=>(string)$r['store_notes'],
            'Telefone Base'=>(string)$r['phone'], 'Email Base'=>(string)$r['email'], 'Origem'=>(string)$r['source'],
        ];
        return $out;
    }

    private static function streamCsv(array $rows, string $filename): void {
        nocache_headers(); header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="' . $filename . '"');
        $fh = fopen('php://output', 'w'); fwrite($fh, "\xEF\xBB\xBF"); $data = self::exportRows($rows);
        if ($data) { fputcsv($fh, array_keys($data[0]), ';'); foreach ($data as $row) fputcsv($fh, array_values($row), ';'); }
        fclose($fh); exit;
    }

    private static function streamXls(array $rows, string $filename): void {
        nocache_headers(); header('Content-Type: application/vnd.ms-excel; charset=UTF-8'); header('Content-Disposition: attachment; filename="' . $filename . '"');
        $data = self::exportRows($rows); echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';
        if ($data) { echo '<tr>'; foreach (array_keys($data[0]) as $h) echo '<th>' . esc_html($h) . '</th>'; echo '</tr>'; foreach ($data as $row) { echo '<tr>'; foreach ($row as $value) echo '<td>' . esc_html((string)$value) . '</td>'; echo '</tr>'; } }
        echo '</table></body></html>'; exit;
    }

    private static function streamPdf(array $rows, string $filename): void {
        $data = self::exportRows($rows); $lines = ['FIELDFLOW, LOJAS', 'Gerado em ' . current_time('d/m/Y H:i'), ''];
        foreach (array_slice($data, 0, 500) as $r) {
            $lines[] = (string)$r['Loja'] . ' | ' . (string)$r['Cliente'] . ' | ' . (string)$r['Projeto'];
            $lines[] = 'Merchan: ' . (string)$r['Merchan'] . ' | Última visita: ' . (string)$r['Última Visita'] . ' | ' . (string)$r['Estado'];
            $lines[] = 'Tipo: ' . (string)$r['Tipo Cardex'] . ' | Formato: ' . (string)$r['Formato Loja'];
            $lines[] = 'Responsável: ' . (string)$r['Responsável'] . ' | Contacto: ' . trim((string)$r['Telefone Operacional'] . ' ' . (string)$r['Email Operacional']);
            $lines[] = 'Pendentes: ' . (string)$r['Pendentes'] . (trim((string)$r['Detalhe Pendentes']) !== '' ? ' | ' . (string)$r['Detalhe Pendentes'] : '');
            $lines[] = '';
        }
        $pdf = self::buildTextPdf($lines);
        nocache_headers(); header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="' . $filename . '"'); header('Content-Length: ' . strlen($pdf)); echo $pdf; exit;
    }

    private static function buildTextPdf(array $lines): string {
        $pages = array_chunk($lines, 42); if (!$pages) $pages = [[]];
        $objects = []; $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $fontId = 3; $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $pageIds = []; $next = 4;
        foreach ($pages as $pageLines) {
            $pageId = $next++; $contentId = $next++; $pageIds[] = $pageId;
            $stream = "BT\n/F1 9 Tf\n42 800 Td\n";
            $first = true;
            foreach ($pageLines as $line) {
                $clean = self::pdfText((string)$line);
                if (!$first) $stream .= "0 -18 Td\n"; $first = false;
                $stream .= '(' . $clean . ") Tj\n";
            }
            $stream .= "ET\n";
            $objects[$contentId] = '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "endstream";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . $fontId . ' 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
        }
        $kids = implode(' ', array_map(static fn($id) => $id . ' 0 R', $pageIds));
        $objects[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageIds) . ' >>'; ksort($objects);
        $pdf = "%PDF-1.4\n"; $offsets = [0];
        foreach ($objects as $id => $body) { $offsets[$id] = strlen($pdf); $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n"; }
        $xref = strlen($pdf); $max = max(array_keys($objects)); $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($id=1; $id<=$max; $id++) $pdf .= sprintf('%010d 00000 n ', $offsets[$id] ?? 0) . "\n";
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }

    private static function pdfText(string $text): string {
        if (function_exists('iconv')) { $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text); if (is_string($converted)) $text = $converted; }
        $text = preg_replace('/[\r\n\t]+/', ' ', $text) ?: '';
        return str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], $text);
    }
}
