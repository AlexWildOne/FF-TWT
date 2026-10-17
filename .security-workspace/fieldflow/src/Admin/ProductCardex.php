<?php
namespace RoutesPro\Admin;

use RoutesPro\Forms\ProductCardex as Cardex;

if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/CardexConsistency.php'; // 9DW, privileged read-only diagnostics

class ProductCardex {
    private static $location_lookup = [];

    public static function register_hooks(): void {
        add_action('admin_post_routespro_save_product_cardex', [self::class, 'handle_save']);
        add_action('admin_post_routespro_duplicate_product_cardex', [self::class, 'handle_duplicate']);
        add_action('admin_post_routespro_publish_product_cardex', [self::class, 'handle_publish']);
        add_action('admin_post_routespro_delete_product_cardex', [self::class, 'handle_delete']);
        add_action('admin_post_routespro_import_product_cardex', [self::class, 'handle_import']);
        add_action('admin_post_routespro_reset_product_cardex_store_override', [self::class, 'handle_reset_store_override']);
        add_action('admin_post_routespro_product_cardex_template', [self::class, 'download_template']);
    }

    private static function locations(array $context = [], array $linked_locations = []): array {
        global $wpdb;
        $locations_table = $wpdb->prefix . 'routespro_locations';
        $campaign_locations_table = $wpdb->prefix . 'routespro_campaign_locations';
        $client_id = absint($context['client_id'] ?? 0);
        $project_id = absint($context['project_id'] ?? 0);
        $active_only = !empty($context['active_only']);
        $linked_locations = array_values(array_unique(array_filter(array_map('absint', $linked_locations))));
        $linked_sql = $linked_locations ? implode(',', $linked_locations) : '0';
        $has_campaign_locations = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $campaign_locations_table)) === $campaign_locations_table;

        $project_match_sql = '0';
        if ($project_id && $has_campaign_locations) {
            $project_match_sql = "EXISTS (
                SELECT 1
                FROM {$campaign_locations_table} cl
                WHERE cl.location_id=l.id
                  AND cl.project_id=" . $project_id . "
                  AND COALESCE(cl.is_active,1)=1
            )";
        }
        if ($project_id) {
            $project_match_sql = '(' . $project_match_sql . ' OR l.project_id=' . $project_id . ')';
        }
        $client_match_sql = $client_id ? '(l.client_id=' . $client_id . ')' : '0';

        $sql = "SELECT l.id,l.name,l.external_ref,l.client_id,l.project_id,l.is_active,l.updated_at,l.tags,l.meta_json,
                       CASE WHEN l.id IN ({$linked_sql}) THEN 1 ELSE 0 END AS cardex_linked,
                       CASE WHEN {$project_match_sql} THEN 1 ELSE 0 END AS project_match,
                       CASE WHEN {$client_match_sql} THEN 1 ELSE 0 END AS client_match
                FROM {$locations_table} l
                WHERE " . ($active_only ? "(COALESCE(l.is_active,1)=1 OR l.id IN ({$linked_sql}))" : "1=1") . "
                ORDER BY cardex_linked DESC,
                         project_match DESC,
                         client_match DESC,
                         CASE WHEN l.updated_at IS NULL THEN 1 ELSE 0 END ASC,
                         l.updated_at DESC,
                         l.name ASC,
                         l.id DESC";

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /** 9DT: selector choices come from actual client/project records. */
    private static function commercial_scope_choices(): array {
        global $wpdb;
        $clients = $wpdb->get_results('SELECT id,name FROM '.$wpdb->prefix.'routespro_clients ORDER BY name ASC,id ASC',ARRAY_A) ?: [];
        $projects = $wpdb->get_results('SELECT id,name,client_id,status FROM '.$wpdb->prefix.'routespro_projects ORDER BY name ASC,id ASC',ARRAY_A) ?: [];
        return [$clients,$projects];
    }

    /** 9DT: diagnostic checks the actual route context; no automatic Cardex remapping. */
    private static function render_binding_diagnostics(int $cardex_id, array $linked, array $locations): void {
        if (!$cardex_id || !$linked) return;
        $linked_map=array_fill_keys(array_map('intval',$linked),true);
        $requested=absint($_GET['diagnose_location_id'] ?? 0);
        if ($requested && !isset($linked_map[$requested])) $requested=0;
        $url=admin_url('admin.php?page=routespro-product-cardex&edit='.$cardex_id);
        echo '<details class="ff-cardex-format" style="margin-top:12px"'.($requested?' open':'').'><summary><strong>Diagnosticar ligação ao formulário</strong></summary>';
        echo '<p class="description">Verifica a identidade da loja e o cliente/projeto das visitas. Selecionar uma loja no Cardex não corrige automaticamente um projeto diferente.</p>';
        echo '<p><label>Loja associada<br><select id="ff-cardex-diag-location" style="max-width:100%;width:100%"><option value="0">Escolhe a loja</option>';
        foreach ($locations as $location) {
            $id=absint($location['id'] ?? 0);
            if (!$id || !isset($linked_map[$id])) continue;
            echo '<option value="'.$id.'"'.selected($requested,$id,false).'>#'.$id.' · '.esc_html((string)$location['name']).'</option>';
        }
        echo '</select></label> <a id="ff-cardex-diag-link" class="button" href="'.esc_url($url).'">Analisar associação</a></p>';
        echo '<script>(function(){var e=document.getElementById("ff-cardex-diag-location"),b=document.getElementById("ff-cardex-diag-link");if(!e||!b)return;var base='.wp_json_encode($url).';function sync(){var id=parseInt(e.value||0,10)||0;b.href=id?base+"&diagnose_location_id="+encodeURIComponent(id):base;}e.addEventListener("change",sync);sync();})();</script>';
        if ($requested>0) {
            global $wpdb;
            $loc=$wpdb->get_row($wpdb->prepare('SELECT id,name,client_id,project_id FROM '.$wpdb->prefix.'routespro_locations WHERE id=%d',$requested),ARRAY_A);
            $routes=$wpdb->get_results($wpdb->prepare(
                'SELECT r.id,r.date,r.client_id,r.project_id FROM '.$wpdb->prefix.'routespro_route_stops rs INNER JOIN '.$wpdb->prefix.'routespro_routes r ON r.id=rs.route_id WHERE rs.location_id=%d ORDER BY r.date DESC,r.id DESC LIMIT 12',
                $requested
            ),ARRAY_A) ?: [];
            echo '<p><strong>PDV: </strong>'.esc_html((string)($loc['name'] ?? '')).' (#'.$requested.')';
            echo ' | Cliente da ficha #'.absint($loc['client_id'] ?? 0).' | Projeto da ficha #'.absint($loc['project_id'] ?? 0).'</p>';
            if (!$routes) echo '<p>Não existem rotas recentes para esta loja. Diagnóstico com o contexto da ficha do PDV:</p>';
            [$clients,$projects]=self::commercial_scope_choices();
            $client_names=[]; $project_names=[];
            foreach ($clients as $c) $client_names[absint($c['id'] ?? 0)]=(string)($c['name'] ?? '');
            foreach ($projects as $p) $project_names[absint($p['id'] ?? 0)]=(string)($p['name'] ?? '');
            echo '<div style="overflow:auto"><table class="widefat striped"><thead><tr><th>Visita / rota</th><th>Cliente</th><th>Projeto</th><th>Cardex do formulário</th><th>Verificação</th></tr></thead><tbody>';
            foreach ($routes ?: [['id'=>0,'date'=>'','client_id'=>absint($loc['client_id'] ?? 0),'project_id'=>absint($loc['project_id'] ?? 0)]] as $r) {
                $inspect=Cardex::inspect_location_binding($cardex_id,$requested,absint($r['client_id'] ?? 0),absint($r['project_id'] ?? 0));
                $ok=($inspect['code'] ?? '')==='ok';
                echo '<tr><td>'.($r['id']?'#'.absint($r['id']).' · '.esc_html((string)($r['date'] ?? '')):'Ficha do PDV').'</td>';
                $route_client_id=absint($r['client_id'] ?? 0);
                $route_project_id=absint($r['project_id'] ?? 0);
                echo '<td>'.esc_html(($client_names[$route_client_id] ?? 'Sem cliente').' (#'.$route_client_id.')').'</td>';
                echo '<td>'.esc_html(($project_names[$route_project_id] ?? 'Sem projeto').' (#'.$route_project_id.')').'</td>';

                echo '<td>#'.absint($inspect['resolved_id'] ?? 0).'</td>';
                echo '<td><strong style="color:'.($ok?'#067647':'#b42318').'">'.esc_html((string)($inspect['message'] ?? '')).'</strong></td></tr>';
            }
            echo '</tbody></table></div>';
            $inspect=Cardex::inspect_location_binding($cardex_id,$requested,absint($routes[0]['client_id'] ?? 0),absint($routes[0]['project_id'] ?? 0));
            $cardex_client_id=absint($inspect['cardex_client_id'] ?? 0);
            $cardex_project_id=absint($inspect['cardex_project_id'] ?? 0);
            echo '<p class="description">Cardex #'.$cardex_id.' | Insignia: '.esc_html((string)($inspect['insignia'] ?? '')).' | Cliente: '.esc_html(($client_names[$cardex_client_id] ?? 'Sem cliente').' (#'.$cardex_client_id.')').' | Projeto: '.esc_html(($project_names[$cardex_project_id] ?? 'Todos os projetos').' (#'.$cardex_project_id.')').'. Nenhuma ligacao foi alterada.</p>';

        }
        echo '</details>';
    }

    /**
     * 9DU: on-demand, read-only and paginated audit of EVERY stored Cardex/PDV
     * link. Uses the same resolver and visit context as forms; never changes
     * links, assortment, stocks, answers, project scopes or identity mapping.
     */
    private static function render_global_binding_audit(): void {
        global $wpdb;
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        $cardex_filter=absint($_GET['audit_cardex'] ?? 0);
        $client_filter=absint($_GET['audit_client'] ?? 0);
        $store_filter=absint($_GET['audit_store'] ?? 0);
        $page=max(1,min(10000,absint($_GET['audit_page'] ?? 1)));
        $limit=60;
        $where=['1=1'];$params=[];
        if ($cardex_filter) {$where[]='c.id=%d';$params[]=$cardex_filter;}
        if ($client_filter) {$where[]='c.client_id=%d';$params[]=$client_filter;}
        if ($store_filter) {$where[]='l.id=%d';$params[]=$store_filter;}
        $sql_where=implode(' AND ',$where);
        $from=' FROM '.Cardex::table_locations().' cl INNER JOIN '.Cardex::table_cardex().
            ' c ON c.id=cl.cardex_id INNER JOIN '.$wpdb->prefix.'routespro_locations l ON l.id=cl.location_id ';
        $count_sql='SELECT COUNT(*) '.$from.' WHERE '.$sql_where;
        $total=(int)$wpdb->get_var($params ? $wpdb->prepare($count_sql,...$params) : $count_sql);
        $total_pages=max(1,(int)ceil($total/$limit));
        $page=min($page,$total_pages);
        $offset=($page-1)*$limit;
        $rows=$wpdb->get_results($wpdb->prepare(
            'SELECT cl.id AS link_id,cl.cardex_id,cl.location_id,c.name AS cardex_name,c.insignia AS cardex_insignia,
'.
            'c.client_id AS cardex_client_id,c.project_id AS cardex_project_id,
'.
            'l.name AS location_name,l.client_id AS location_client_id,l.project_id AS location_project_id ' .
            $from.' WHERE '.$sql_where.' ORDER BY cl.id DESC LIMIT %d OFFSET %d',
            ...array_merge($params,[$limit,$offset])
        ),ARRAY_A) ?: [];
        [$clients,$projects]=self::commercial_scope_choices();
        $cardexes=Cardex::list_cardex();
        $client_names=[];$project_names=[];
        foreach ($clients as $row) $client_names[absint($row['id'] ?? 0)]=(string)($row['name'] ?? '');
        foreach ($projects as $row) $project_names[absint($row['id'] ?? 0)]=(string)($row['name'] ?? '');
        // Fetch the latest route per PDV in one bounded query. If there is no
        // route, use the PDV's own context, explicitly labelled as such.
        $latest=[];$ids=array_values(array_unique(array_filter(array_map('intval',array_column($rows,'location_id')))));
        if ($ids) {
            $placeholders=implode(',',array_fill(0,count($ids),'%d'));
            $routes=$wpdb->get_results($wpdb->prepare(
                'SELECT rs.location_id,r.id AS route_id,r.date,r.client_id,r.project_id '.
                'FROM '.$wpdb->prefix.'routespro_route_stops rs INNER JOIN '.$wpdb->prefix.'routespro_routes r ON r.id=rs.route_id '.
                'WHERE rs.location_id IN ('.$placeholders.') ORDER BY r.date DESC,r.id DESC LIMIT 1500',
                ...$ids
            ),ARRAY_A) ?: [];
            foreach ($routes as $route) {
                $id=absint($route['location_id'] ?? 0);
                if ($id && !isset($latest[$id])) $latest[$id]=$route;
            }
        }
        // Read-only base count: useful when an otherwise valid association has
        // no active SKU to expose in Frentes or Sugestao de Encomenda.
        $active_counts=[];$cardex_ids=array_values(array_unique(array_filter(array_map('intval',array_column($rows,'cardex_id')))));
        if ($cardex_ids) {
            $placeholders=implode(',',array_fill(0,count($cardex_ids),'%d'));
            $counts=$wpdb->get_results($wpdb->prepare(
                'SELECT cardex_id,COUNT(*) AS total FROM '.Cardex::table_items().
                ' WHERE cardex_id IN ('.$placeholders.') AND is_active=1 GROUP BY cardex_id',
                ...$cardex_ids
            ),ARRAY_A) ?: [];
            foreach ($counts as $row) $active_counts[absint($row['cardex_id'] ?? 0)]=absint($row['total'] ?? 0);
        }
        $page_params=['page'=>'routespro-product-cardex','audit_bindings'=>1];
        if ($cardex_filter) $page_params['audit_cardex']=$cardex_filter;
        if ($client_filter) $page_params['audit_client']=$client_filter;
        if ($store_filter) $page_params['audit_store']=$store_filter;
        $audit_url=admin_url('admin.php');
        echo '<section class="ff-cardex-card" style="margin:14px 0">';
        echo '<h2>Auditoria global de associações Cardex / lojas</h2>';
        echo '<p class="description">Consulta todas as ligações existentes, incluindo as que o formulário rejeita. Compara com a última rota identificada da loja, sem modificar dados. A amostragem é paginada, 60 ligações de cada vez.</p>';
        echo '<form method="get" action="'.esc_url($audit_url).'" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin:14px 0">';
        echo '<input type="hidden" name="page" value="routespro-product-cardex"><input type="hidden" name="audit_bindings" value="1">';
        echo '<label>Cliente<br><select name="audit_client"><option value="0">Todos os clientes</option>';
        foreach ($clients as $row) echo '<option value="'.absint($row['id']).'"'.selected($client_filter,absint($row['id']),false).'>'.esc_html((string)$row['name']).'</option>';
        echo '</select></label>';
        echo '<label>Cardex<br><select name="audit_cardex"><option value="0">Todos os cardex</option>';
        foreach ($cardexes as $row) echo '<option value="'.absint($row['id']).'"'.selected($cardex_filter,absint($row['id']),false).'>#'.absint($row['id']).' '.esc_html((string)$row['name']).'</option>';
        echo '</select></label>';
        echo '<label>PDV ID<br><input type="number" min="1" name="audit_store" value="'.($store_filter ?: '').'" style="width:90px"></label>';
        echo '<button type="submit" class="button button-primary">Analisar ligações</button>';
        echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=routespro-product-cardex')).'">Voltar aos cardex</a></form>';
        echo '<p><strong>'.count($rows).' ligações nesta página</strong> de '.absint($total).' filtradas, página '.$page.'/'.$total_pages.'. O resumo refere-se à página atual.</p>';
        $codes=[];
        echo '<div style="overflow:auto"><table class="widefat striped ff-cardex-table" style="min-width:1050px"><thead><tr>';
        echo '<th>PDV</th><th>Cardex</th><th>Identidade</th><th>Contexto da visita</th><th>Refs.</th><th>Resultado real</th><th>Diagnosticar</th>';
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $cid=absint($row['cardex_id'] ?? 0);$lid=absint($row['location_id'] ?? 0);
            $route=$latest[$lid] ?? null;
            $visit_client=$route ? absint($route['client_id'] ?? 0) : absint($row['location_client_id'] ?? 0);
            $visit_project=$route ? absint($route['project_id'] ?? 0) : absint($row['location_project_id'] ?? 0);
            $inspect=Cardex::inspect_location_binding($cid,$lid,$visit_client,$visit_project);
            $count=absint($active_counts[$cid] ?? 0);
            $code=(string)($inspect['code'] ?? 'missing');
            if ($code==='ok' && !$count) $code='empty_assortment';
            $codes[$code]=($codes[$code] ?? 0)+1;
            $label=$code==='empty_assortment'?'Cardex sem referências ativas':(string)($inspect['message'] ?? $code);
            $ok=$code==='ok';
            $edit=add_query_arg(['page'=>'routespro-product-cardex','edit'=>$cid,'diagnose_location_id'=>$lid],admin_url('admin.php'));
            echo '<tr><td><strong>#'.$lid.'</strong> '.esc_html((string)($row['location_name'] ?? '')).'</td>';
            echo '<td><strong>#'.$cid.'</strong> '.esc_html((string)($row['cardex_name'] ?? '')).'</td>';
            echo '<td>Declarada: '.esc_html((string)($inspect['insignia'] ?? '')).'<br>Efetiva: <strong>'.esc_html((string)($inspect['retailer_evidence'] ?? '')).'</strong>';
            if (!empty($inspect['legacy_sonae_umbrella'])) echo '<br><small>Grupo Sonae legado reconhecido</small>';
            echo '</td>';
            echo '<td>'.esc_html($client_names[$visit_client] ?? 'Sem cliente').' (#'.$visit_client.')<br>'.
                esc_html($project_names[$visit_project] ?? 'Sem projeto').' (#'.$visit_project.')<br>'.
                ($route ? '<small>Rota #'.absint($route['route_id'] ?? 0).'</small>' : '<small>Ficha do PDV; sem rota nesta amostra</small>').'</td>';
            echo '<td>'.$count.'</td>';
            echo '<td><strong style="color:'.($ok?'#067647':'#b42318').'">'.esc_html($label).'</strong><br><small>Resolvido #'.absint($inspect['resolved_id'] ?? 0).'</small></td>';
            echo '<td><a class="button button-small" href="'.esc_url($edit).'">Ver detalhe</a></td></tr>';
        }
        if (!$rows) echo '<tr><td colspan="7">Sem ligações nos filtros selecionados.</td></tr>';
        echo '</tbody></table></div>';
        if ($codes) {
            arsort($codes);
            echo '<p class="description"><strong>Resumo desta página:</strong> ';
            $parts=[];
            foreach ($codes as $code=>$count) $parts[]=esc_html($code).' = '.absint($count);
            echo implode(' | ',$parts).'</p>';
        }
        echo '<p style="display:flex;gap:12px;align-items:center">';
        if ($page>1) echo '<a class="button" href="'.esc_url(add_query_arg(array_merge($page_params,['audit_page'=>$page-1]),$audit_url)).'">Página anterior</a>';
        if ($page<$total_pages) echo '<a class="button" href="'.esc_url(add_query_arg(array_merge($page_params,['audit_page'=>$page+1]),$audit_url)).'">Página seguinte</a>';
        echo '</p><p class="description">Lojas sem cardex associado não entram nesta tabela. Uma ligação com cliente/projeto incompatível ou insígnia contraditória continua bloqueada até revisão comercial; esta auditoria não contorna essas proteções.</p></section>';
    }

    private static function linked_locations(int $cardex_id): array {
        global $wpdb;
        Cardex::install();
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT location_id FROM " . Cardex::table_locations() . " WHERE cardex_id=%d",
            $cardex_id
        ));
        return array_map('intval', $ids ?: []);
    }

    public static function render(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        // 9CL: "Cardex e Catálogo" hosts the visual product catalog and the briefing label mapping as tabs.
        if (ProductCatalog::current_tab() !== 'cardex') { ProductCatalog::render(); return; }
        Cardex::install();
        if (isset($_GET['consistency'])) { CardexConsistency::render(); return; }

        $duplicate_id = absint($_GET['duplicate'] ?? 0);
        $duplicate_source = $duplicate_id ? Cardex::get_cardex($duplicate_id) : null;
        $edit_id = absint($_GET['edit'] ?? 0);
        $edit = $edit_id ? Cardex::get_cardex($edit_id) : null;
        $items = $edit_id ? Cardex::get_items($edit_id) : [];
        $linked = $edit_id ? self::linked_locations($edit_id) : [];
        $category_counts = $edit_id ? Cardex::get_category_counts($edit_id) : [];
        $store_override_count = $edit_id ? Cardex::store_override_count($edit_id) : 0;
        $store_overrides = $edit_id ? Cardex::list_store_overrides($edit_id) : [];

        echo '<div class="wrap">';
        Branding::render_header(
            'Cardex de Produtos',
            'Cria cardex por insígnia, acrescenta dados comerciais do produto e controla o sortido disponível em cada loja.'
        );
        ProductCatalog::styles();
        ProductCatalog::render_tabs('cardex');
        if (isset($_GET['saved'])) echo '<div class="notice notice-success"><p>Cardex guardado. O estado de publicação foi preservado.</p></div>';
        if (isset($_GET['duplicated'])) echo '<div class="notice notice-success"><p>Cópia criada como rascunho, sem lojas ou ajustes históricos associados. Revê o sortido e publica quando estiver correto.</p></div>';
        if (isset($_GET['published'])) echo '<div class="notice notice-success"><p>Cardex publicado após validação da insígnia e abrangência.</p></div>';
        if (isset($_GET['cardex_error'])) echo '<div class="notice notice-error"><p>' . esc_html(sanitize_text_field(wp_unslash((string)($_GET['cardex_error'] ?? '')))) . '</p></div>';
        if (isset($_GET['scope_error'])) echo '<div class="notice notice-error"><p>Não foi possível gravar todas as lojas selecionadas. O Cardex anterior foi mantido. Revê a base de dados e tenta novamente.</p></div>';
        if (isset($_GET['imported'])) {
            $created = absint($_GET['products'] ?? 0);
            $mapped = absint($_GET['mapped'] ?? 0);
            $unmatched = absint($_GET['unmatched'] ?? 0);
            echo '<div class="notice notice-success"><p>Importação concluída. Produtos processados: <strong>' . $created . '</strong>. Ligações produto-loja: <strong>' . $mapped . '</strong>. Lojas não reconhecidas: <strong>' . $unmatched . '</strong>.</p></div>';
        }

        echo '<style>
            .ff-cardex-layout{display:grid;grid-template-columns:minmax(390px,620px) 1fr;gap:18px;align-items:start}
            .ff-cardex-card{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:16px}
            .ff-cardex-format{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;margin-top:8px}
            .ff-cardex-chips{display:flex;gap:7px;flex-wrap:wrap;margin:10px 0}
            .ff-cardex-chip{display:inline-flex;align-items:center;gap:6px;border:1px solid #cbd5e1;background:#fff;border-radius:999px;padding:5px 9px;font-size:12px}
            .ff-cardex-chip b{background:#e2e8f0;border-radius:999px;min-width:20px;text-align:center;padding:1px 5px}
            .ff-cardex-table td,.ff-cardex-table th{vertical-align:top}
            .ff-cardex-note{border-left:4px solid #2271b1;background:#eff6ff;padding:10px 12px;margin:12px 0}
            @media(max-width:1100px){.ff-cardex-layout{grid-template-columns:1fr}}
        </style>';

        if ($duplicate_source) {
            $sourceName = (string)($duplicate_source['name'] ?? '');
            $sourceItems = Cardex::get_items($duplicate_id);
            echo '<section class="ff-cardex-card" style="margin:14px 0;border-left:4px solid #f2622e"><h2>Duplicar cardex para outra insígnia</h2>';
            echo '<p>Origem: <strong>' . esc_html($sourceName) . '</strong>. Serão copiadas ' . count($sourceItems) . ' referências ativas, mantendo o original intacto. Lojas, stocks, revisões e ajustes históricos não serão copiados.</p>';
            echo '<p>Cliente #' . (int)($duplicate_source['client_id'] ?? 0) . ' · Projeto #' . (int)($duplicate_source['project_id'] ?? 0) . '</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('routespro_duplicate_product_cardex_' . $duplicate_id, 'routespro_product_cardex_nonce');
            echo '<input type="hidden" name="action" value="routespro_duplicate_product_cardex"><input type="hidden" name="source_id" value="' . $duplicate_id . '">';
            echo '<p><label><strong>Nome do novo cardex</strong><br><input name="name" class="regular-text" required value="' . esc_attr('Cópia de ' . $sourceName) . '"></label></p>';
            echo '<p><label><strong>Insígnia de destino</strong><br><input type="text" name="destination_insignia" list="ff-cardex-brand-options" class="regular-text" maxlength="80" required autocomplete="off" placeholder="Selecionar ou escrever uma nova insígnia"></label></p>';
            echo '<datalist id="ff-cardex-brand-options">';
            foreach (CardexCloneService::insignias() as $insignia => $label) echo '<option value="' . esc_attr($insignia) . '" label="' . esc_attr($label) . '"></option>';
            echo '</datalist>';
            echo '<p class="description">Podes escolher da lista ou escrever uma insígnia nova, por exemplo Poupança, Garcias, Malaquias ou Central Cash. O nome fica disponível nas próximas duplicações depois de criares o rascunho. Não são copiadas lojas nem associações comerciais.</p>';
            echo '<p class="description">A duplicação cria apenas um rascunho. A publicação terá validação independente das lojas elegíveis e do sortido.</p>';
            echo '<p><button type="submit" class="button button-primary">Criar rascunho</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=routespro-product-cardex')) . '">Cancelar</a></p></form></section>';
        }
        echo '<p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=routespro-product-cardex&consistency=1')).'">Centro de Consistência: Cardex / Formulários / Briefings / Stocks</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=routespro-product-cardex&audit_bindings=1')).'">Auditoria global: Cardex / PDVs / Formulários</a></p>'; 
        if (isset($_GET['audit_bindings'])) {
            self::render_global_binding_audit();
            echo '</div>';
            return;
        }
        echo '<div class="ff-cardex-layout">';
        echo '<div class="ff-cardex-card"><h2>' . ($edit ? 'Editar cardex' : 'Novo cardex') . '</h2>';
        if ($edit) {
            $isDraft = (string)($edit['status'] ?? '') === 'draft';
            echo '<p><strong>Estado: ' . ($isDraft ? 'Rascunho' : 'Ativo') . '</strong>.' . ($isDraft ? ' Não é apresentado no Portal Operativo.' : '') . '</p>';
            if (CardexCloneService::isClone($edit)) {
                $origin = json_decode((string)($edit['meta_json'] ?? ''), true);
                echo '<div class="ff-cardex-note"><strong>Cardex duplicado</strong><br>Origem #' . (int)($origin['clone_source_id'] ?? 0) . '. Produtos independentes, sem herdar ligações a lojas ou revisões.</div>';
            }
        }
        if (!$edit) echo '<div class="ff-cardex-note">Os novos cardex são guardados como rascunho. A publicação é uma ação separada, com confirmação das lojas.</div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="routespro_save_product_cardex">';
        echo '<input type="hidden" name="id" value="' . (int)$edit_id . '">';
        wp_nonce_field('routespro_save_product_cardex', 'routespro_product_cardex_nonce');

        echo '<p><label><strong>Nome</strong><br><input type="text" name="name" class="regular-text" value="' . esc_attr($edit['name'] ?? '') . '" placeholder="Ex, Pernod Ricard, Sonae" required></label></p>';
        if ($edit && CardexCloneService::isClone($edit)) {
            echo '<p><label><strong>Insígnia comercial</strong><br><input name="insignia" type="text" list="ff-cardex-brand-edit-options" class="regular-text" maxlength="80" required value="' . esc_attr((string)($edit['insignia'] ?? '')) . '" autocomplete="off"></label></p>';
            echo '<datalist id="ff-cardex-brand-edit-options">';
            foreach (CardexCloneService::insignias() as $insignia => $label) echo '<option value="' . esc_attr($insignia) . '" label="' . esc_attr($label) . '"></option>';
            echo '</datalist>';
            echo '<p class="description">A insígnia pode ser escolhida ou escrita. A alteração num cardex ativo continua bloqueada. Para publicar, as lojas têm de comprovar a mesma identidade comercial.</p>';
        } else {
            echo '<p><label><strong>Insígnia / palavra-chave</strong><br><input type="text" name="insignia" class="regular-text" value="' . esc_attr($edit['insignia'] ?? '') . '" placeholder="Ex, Continente, Modelo"></label><br><span class="description">Usado para auto-deteção quando a loja não está associada manualmente.</span></p>';
        }
        [$scope_clients,$scope_projects]=self::commercial_scope_choices();
        $selected_client=absint($edit['client_id'] ?? 0);
        $selected_project=absint($edit['project_id'] ?? 0);
        echo '<div class="ff-cardex-format" style="margin:12px 0"><h3 style="margin:0 0 9px">Cliente e projeto do Cardex</h3>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">';
        echo '<label><strong>Cliente</strong><br><select id="ff-cardex-client" name="client_id" style="width:100%"><option value="0">Sem cliente / legado</option>';
        foreach ($scope_clients as $c) echo '<option value="'.absint($c['id']).'"'.selected($selected_client,absint($c['id']),false).'>'.esc_html((string)$c['name']).' (#'.absint($c['id']).')</option>';
        echo '</select></label>';
        echo '<label><strong>Projeto / campanha</strong><br><select id="ff-cardex-project" name="project_id" style="width:100%"><option value="0">Todos os projetos do cliente (lojas aprovadas)</option>';
        foreach ($scope_projects as $p) echo '<option value="'.absint($p['id']).'" data-client="'.absint($p['client_id'] ?? 0).'"'.selected($selected_project,absint($p['id']),false).'>'.esc_html((string)$p['name']).' (#'.absint($p['id']).')</option>';
        echo '</select></label></div>';
        echo '<p class="description">A loja tem de estar associada ao Cardex. O projeto tem de corresponder ao da visita; escolhe “Todos os projetos do cliente” apenas para partilhar este cardex explicitamente entre campanhas do mesmo cliente. Isto não associa outras lojas nem muda sortidos.</p>';
        if ($edit && ($edit['status'] ?? '') === 'active') echo '<p><label><input type="checkbox" name="confirm_scope_change" value="1"> Confirmo uma eventual alteração de cliente/projeto deste Cardex ativo.</label></p>';
        echo '<script>(function(){var c=document.getElementById("ff-cardex-client"),p=document.getElementById("ff-cardex-project");if(!c||!p)return;function apply(){var id=c.value;Array.prototype.forEach.call(p.options,function(o){if(!o.value)return;var same=o.getAttribute("data-client")===id;o.disabled=!same;o.hidden=!same;});if(p.selectedOptions.length&&p.selectedOptions[0].disabled)p.value="0";}c.addEventListener("change",apply);apply();})();</script></div>';


        $products_text = implode("\n", array_map(static function(array $row): string {
            return implode(';', [
                (string)($row['category'] ?? ''),
                (string)($row['ref'] ?? ''),
                (string)($row['item_type'] ?? ''),
                (string)($row['name'] ?? ''),
                self::format_number($row['capacity_cl'] ?? ''),
                self::format_number($row['units_per_case'] ?? ''),
            ]);
        }, $items));

        echo '<p><label><strong>Produtos por categoria</strong><br><textarea name="products_text" rows="16" style="width:100%;font-family:ui-monospace,monospace" placeholder="categoria;codigo;tipo;produto;capacidade_cl;unidades_caixa">' . esc_textarea($products_text) . '</textarea></label></p>';
        echo '<div class="ff-cardex-format"><strong>Formato recomendado</strong><br><code>categoria;codigo;tipo;produto;capacidade_cl;unidades_caixa</code><br><span class="description">Exemplo: <code>GIN;2050501;PBL;GIN BEEFEATER 70CL;70;12</code>. Os formatos antigos continuam compatíveis. Ao guardar, o FieldFlow preserva o sortido das referências existentes e remove apenas as ligações de produtos realmente apagados.</span></div>';

        if ($store_override_count) {
            echo '<div class="ff-cardex-note"><strong>Cardex vivo por loja</strong><br>Existem <strong>' . (int)$store_override_count . '</strong> loja(s) com ajustes persistentes feitos no terreno. Remoções, alterações e novas referências ficam ligadas à loja, sem alterar as restantes. Novos produtos carregados no Cardex base continuam a ser herdados automaticamente.</div>';
        }

        if ($category_counts) {
            echo '<div class="ff-cardex-chips">';
            foreach ($category_counts as $category) {
                echo '<span class="ff-cardex-chip">' . esc_html($category['category']) . ' <b>' . (int)$category['total'] . '</b></span>';
            }
            echo '</div>';
        }

        $locations = self::locations([
            'client_id' => absint($edit['client_id'] ?? 0),
            'project_id' => absint($edit['project_id'] ?? 0),
            'active_only' => true,
        ], $linked);
        // 9DQ: a cloned cardex never offers unrelated stores in its selection list.
        if ($edit && CardexCloneService::isClone($edit)) {
            $locations = array_values(array_filter($locations, static function(array $location) use ($edit): bool {
                return CardexCloneService::locationMatchesInsignia((string)($edit['insignia'] ?? ''), $location)
                    && ((int)($location['client_id'] ?? 0) === 0 || (int)$location['client_id'] === (int)$edit['client_id'])
                    && !empty($location['project_match']);
            }));
        }
        echo '<p><strong>Associar o cardex a lojas existentes</strong><br>';
        echo '<input type="search" id="ff-cardex-location-search" placeholder="Pesquisar por nome, referência ou ID" style="width:100%;margin:7px 0 8px">';
        echo '<input type="hidden" name="location_scope_submitted" value="1"><select id="ff-cardex-location-ids" name="location_ids[]" multiple size="12" style="width:100%;max-width:100%">';
        foreach ($locations as $location) {
            $location_id = (int)$location['id'];
            $selected = in_array($location_id, $linked, true) ? ' selected' : '';
            $scope = [];
            if (!empty($location['project_match'])) $scope[] = 'campanha';
            elseif (!empty($location['client_match'])) $scope[] = 'cliente';
            if (!empty($location['cardex_linked'])) $scope[] = 'selecionada';
            $scope_label = $scope ? ' · ' . implode(' / ', $scope) : '';
            $option_label = '#' . $location_id . ' · ' . (string)$location['name']
                . (!empty($location['external_ref']) ? ' · ' . (string)$location['external_ref'] : '')
                . $scope_label;
            echo '<option value="' . $location_id . '"' . $selected . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select><br><span class="description"><strong><span id="ff-cardex-location-count">' . count($linked) . '</span> loja(s) selecionada(s).</strong> <span id="ff-cardex-location-visible">' . count($locations) . '</span> loja(s) disponível(eis). As lojas já associadas, da campanha e do cliente atual aparecem primeiro, seguidas pelas mais recentes. ' . (($edit && CardexCloneService::isClone($edit)) || (!$edit) || (($edit['status'] ?? '') === 'draft') ? 'Em rascunho, nenhuma associação se torna pública antes de validar e publicar.' : 'Sem lojas selecionadas, um cardex legacy ativo pode ser global.') . ' Usa Ctrl ou Cmd para selecionar várias lojas.</span></p>';
        if ($edit_id && $edit) self::render_binding_diagnostics($edit_id,$linked,$locations);
        if ($store_overrides) {
            echo '<h3>Cardex construído pelas equipas no terreno</h3><p class="description">Cada loja mantém o seu Cardex operacional entre visitas e entre merchandisers. O histórico de reportes não é alterado. Podes consultar o sortido efetivo ou repor apenas esta loja no Cardex base.</p>';
            echo '<div style="overflow:auto"><table class="widefat striped"><thead><tr><th>Loja</th><th>Referências</th><th>Modo</th><th>Última edição</th><th>Utilizador</th><th>Sortido efetivo</th><th>Ação</th></tr></thead><tbody>';
            foreach ($store_overrides as $override) {
                $location_id = absint($override['location_id'] ?? 0);
                $override_items = Cardex::get_store_override_items($edit_id, $location_id);
                $reset_url = wp_nonce_url(
                    admin_url('admin-post.php?action=routespro_reset_product_cardex_store_override&cardex_id=' . $edit_id . '&location_id=' . $location_id),
                    'routespro_reset_product_cardex_store_override_' . $edit_id . '_' . $location_id
                );
                $product_lines = [];
                foreach ($override_items as $override_item) {
                    $product_lines[] = trim(((string)($override_item['ref'] ?? '') !== '' ? (string)$override_item['ref'] . ' · ' : '') . (string)($override_item['name'] ?? 'Produto'));
                }
                $details = $product_lines
                    ? '<details><summary>Ver ' . count($product_lines) . ' referência(s)</summary><ul style="margin:8px 0 0 18px"><li>' . implode('</li><li>', array_map('esc_html', $product_lines)) . '</li></ul></details>'
                    : '<em>Sortido vazio nesta loja</em>';
                $mode = (string)($override['mode'] ?? '') === 'live_delta' ? 'Vivo' : 'Compatibilidade';
                $editor = trim((string)($override['updated_by_name'] ?? ''));
                if ($editor === '' && !empty($override['updated_by'])) $editor = 'User #' . (int)$override['updated_by'];
                if ($editor === '') $editor = 'Não identificado';
                $revision = !empty($override['revision_no']) ? ' · rev. ' . (int)$override['revision_no'] : '';
                echo '<tr><td><strong>' . esc_html((string)($override['location_name'] ?? ('Loja #' . $location_id))) . '</strong><br><code>#' . $location_id . '</code></td><td>' . (int)($override['item_count'] ?? 0) . '</td><td><strong>' . esc_html($mode) . '</strong>' . esc_html($revision) . '</td><td>' . esc_html((string)($override['updated_at'] ?? '')) . '</td><td>' . esc_html($editor) . '</td><td>' . $details . '</td><td><a class="button button-small" href="' . esc_url($reset_url) . '" onclick="return confirm(&quot;Repor esta loja no Cardex base?&quot;)">Repor base</a></td></tr>';
            }
            echo '</tbody></table></div>';
        }

        if ($edit) echo '<button type="submit" class="button button-secondary" name="scope_only" value="1" style="margin:6px 8px 10px 0">Guardar apenas cliente/projeto</button><p class="description">Esta ação não altera produtos, lojas selecionadas, revisões ou stock.</p>';
        submit_button($edit ? 'Guardar cardex' : 'Criar cardex');
        echo '</form>';
        if ($edit && ($edit['status'] ?? '') === 'draft') {
            $preview = [];
            $previewError = '';
            try {
                $preview = CardexCloneService::eligibleLocations(
                    absint($edit['client_id'] ?? 0),absint($edit['project_id'] ?? 0),(string)($edit['insignia'] ?? '')
                );
            } catch (\Throwable $e) { $previewError = $e->getMessage(); }
            echo '<section class="ff-cardex-format" style="margin-top:16px"><h3>Validar e publicar</h3>';
            echo '<p><strong>' . count($linked) . '</strong> lojas selecionadas. Elegíveis no projeto e na insígnia: <strong>' . count($preview) . '</strong>. O rascunho nunca é publicado por simplesmente guardar.</p>';
            if ($previewError !== '') echo '<p>' . esc_html($previewError) . '</p>';
            if ($preview) {
                echo '<details><summary>Pré-visualizar lojas elegíveis</summary><ol>';
                foreach (array_slice($preview,0,25) as $store) echo '<li>' . esc_html((string)$store['name']) . ' (#' . (int)$store['id'] . ')</li>';
                if (count($preview)>25) echo '<li>Mais ' . (count($preview)-25) . ' lojas elegíveis.</li>';
                echo '</ol></details>';
            }
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('routespro_publish_product_cardex_' . $edit_id,'routespro_product_cardex_nonce');
            echo '<input type="hidden" name="action" value="routespro_publish_product_cardex"><input type="hidden" name="id" value="' . $edit_id . '">';
            echo '<p><label><strong>Âmbito aprovado</strong><br><select name="publish_scope"><option value="manual">Apenas lojas selecionadas e guardadas</option><option value="global">Todas as lojas elegíveis da insígnia neste projeto</option></select></label></p>';
            echo '<p><label><input type="checkbox" name="confirm_publication" value="1" required> Confirmei as referências, a insígnia e a abrangência. Não publicar produtos ainda por rever.</label></p>';
            echo '<button type="submit" class="button button-primary" onclick="return confirm(&quot;Publicar este cardex após confirmar o âmbito?&quot;)">Validar e publicar cardex</button></form></section>';
        }

        echo '<hr><h3>Importar Cardex e sortido por loja</h3>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="routespro_import_product_cardex">';
        wp_nonce_field('routespro_import_product_cardex', 'routespro_product_cardex_import_nonce');
        echo '<p><input type="file" name="cardex_csv" accept=".csv,text/csv" required></p>';
        echo '<p><label><input type="checkbox" name="replace_items" value="1" checked> Substituir produtos e sortidos base dos cardex importados, preservando ajustes feitos por loja</label></p>';
        echo '<p><button class="button button-primary">Importar CSV</button> <a class="button" href="' . esc_url(admin_url('admin-post.php?action=routespro_product_cardex_template')) . '">Template CSV</a></p>';
        echo '<div class="ff-cardex-note"><strong>Sortido por loja</strong><br>Cada linha pode incluir <code>location_id</code>, <code>external_ref</code> ou <code>loja</code>. Quando a mesma referência aparece em várias lojas, o produto é criado uma vez e ligado apenas às lojas assinaladas.</div>';
        echo '<p class="description">Colunas: cardex, insignia, client_id, project_id, categoria, codigo, tipo, produto, capacidade_cl, unidades_caixa, location_id, external_ref, loja, ordem, ativo.</p>';
        echo '</form></div>';

        echo '<div><h2>Cardex existentes</h2><div style="overflow:auto"><table class="widefat striped ff-cardex-table"><thead><tr><th>ID</th><th>Nome</th><th>Insígnia</th><th>Estado</th><th>Categorias</th><th>Produtos</th><th>Lojas associadas</th><th>Ligações de sortido</th><th>Ajustes por loja</th><th>Ações</th></tr></thead><tbody>';
        $rows = Cardex::list_cardex();
        $client_names=[];$project_names=[];
        foreach ($scope_clients as $c) $client_names[absint($c['id'])]=(string)$c['name'];
        foreach ($scope_projects as $p) $project_names[absint($p['id'])]=(string)$p['name'];
        if (!$rows) echo '<tr><td colspan="10">Ainda não existem cardex.</td></tr>';
        global $wpdb;
        foreach ($rows as $row) {
            $product_count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . Cardex::table_items() . " WHERE cardex_id=%d AND is_active=1",
                (int)$row['id']
            ));
            $location_count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . Cardex::table_locations() . " WHERE cardex_id=%d",
                (int)$row['id']
            ));
            $assortment_count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . Cardex::table_item_locations() . " WHERE cardex_id=%d AND is_active=1",
                (int)$row['id']
            ));
            $override_count = Cardex::store_override_count((int)$row['id']);
            $categories = Cardex::get_category_counts((int)$row['id']);
            $category_names = array_map(static function(array $category): string { return (string)$category['category']; }, $categories);
            $edit_url = admin_url('admin.php?page=routespro-product-cardex&edit=' . (int)$row['id']);
            $delete_url = wp_nonce_url(admin_url('admin-post.php?action=routespro_delete_product_cardex&id=' . (int)$row['id']), 'routespro_delete_product_cardex_' . (int)$row['id']);
            $clone_url = admin_url('admin.php?page=routespro-product-cardex&duplicate=' . (int)$row['id']);
            echo '<tr><td>' . (int)$row['id'] . '</td><td><strong>' . esc_html($row['name']) . '</strong><br><small style="color:#64748b">'.esc_html($client_names[absint($row['client_id'] ?? 0)] ?? 'Cliente legado').' · '.esc_html($project_names[absint($row['project_id'] ?? 0)] ?? 'Todos os projetos').' </small></td><td>' . esc_html($row['insignia']) . '</td><td><strong>' . (($row['status'] ?? '') === 'draft' ? 'Rascunho' : 'Ativo') . '</strong></td><td>' . (count($categories) ? esc_html(implode(', ', $category_names)) : 'Sem categorias') . '</td><td>' . $product_count . '</td><td>' . $location_count . '</td><td>' . $assortment_count . '</td><td>' . $override_count . '</td><td><a class="button button-small" href="' . esc_url($edit_url) . '">Editar</a> <a class="button button-small" href="' . esc_url($clone_url) . '">Duplicar</a> <a class="button button-small" href="' . esc_url($delete_url) . '" onclick="return confirm(\'Apagar cardex?\')">Apagar</a></td></tr>';
        }
        echo '</tbody></table></div></div></div></div>';
    }

    public static function handle_save(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('routespro_save_product_cardex', 'routespro_product_cardex_nonce');
        Cardex::install();
        global $wpdb;

        $id = absint($_POST['id'] ?? 0);
        $existing = $id ? Cardex::get_cardex($id) : null;
        if ($id && !$existing) wp_die('O cardex já não existe.');
        $data = [
            'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')) ?: 'Cardex',
            'insignia' => sanitize_text_field(wp_unslash($_POST['insignia'] ?? '')),
            'client_id' => absint($_POST['client_id'] ?? 0),
            'project_id' => absint($_POST['project_id'] ?? 0),
            'status' => $existing ? (string)$existing['status'] : 'draft',
            'updated_at' => current_time('mysql'),
        ];
        $scope_only = $id > 0 && !empty($_POST['scope_only']);
        $previous_locations = $id ? self::linked_locations($id) : [];
        $scope_submitted = !empty($_POST['location_scope_submitted']);
        $requested_locations = $scope_submitted
            ? array_map('absint', (array)wp_unslash($_POST['location_ids'] ?? []))
            : $previous_locations;
        if ($wpdb->query('START TRANSACTION') === false) wp_die('Não foi possível iniciar a gravação segura do cardex.');
        try {
            // 9DT: project belongs to the chosen client, never a free numeric guess.
            if ($data['client_id'] > 0 && !(int)$wpdb->get_var($wpdb->prepare(
                'SELECT id FROM '.$wpdb->prefix.'routespro_clients WHERE id=%d LIMIT 1',
                $data['client_id']
            ))) throw new \RuntimeException('O cliente selecionado nao existe.');
            if ($data['project_id'] > 0) {
                $owner=(int)$wpdb->get_var($wpdb->prepare(
                    'SELECT client_id FROM '.$wpdb->prefix.'routespro_projects WHERE id=%d LIMIT 1',
                    $data['project_id']
                ));
                if (!$owner || $owner !== $data['client_id']) throw new \RuntimeException('O projeto selecionado nao pertence ao cliente escolhido.');
            }
            if ($existing && ($existing['status'] ?? '') === 'active' &&
                ((int)$existing['client_id'] !== $data['client_id'] || (int)$existing['project_id'] !== $data['project_id'])
                && empty($_POST['confirm_scope_change'])) {
                throw new \RuntimeException('Para alterar o cliente/projeto deste Cardex ativo confirma a alteracao no editor.');
            }
            if ($existing && ($existing['status'] ?? '') === 'active' && (int)$existing['client_id']>0 && $data['client_id']===0) {
                throw new \RuntimeException('Um Cardex ativo de cliente nao pode ser convertido num Cardex sem cliente.');
            }
            if ($existing && $data['client_id']>0 && (int)$existing['client_id']!==$data['client_id']) {
                $otherClientLinks=(int)$wpdb->get_var($wpdb->prepare(
                    'SELECT COUNT(*) FROM '.Cardex::table_locations().' cl INNER JOIN '.$wpdb->prefix.'routespro_locations l ON l.id=cl.location_id WHERE cl.cardex_id=%d AND l.client_id>0 AND l.client_id<>%d',
                    $id,$data['client_id']
                ));
                if ($otherClientLinks>0) throw new \RuntimeException('Este Cardex contem lojas de outro cliente. Revê as associacoes antes de mudar o cliente.');
            }
            // 9DP: editing a cloned active assortment must never silently move its brand.
            if ($existing && CardexCloneService::isClone($existing)) {
                if ($existing['status'] === 'active' && (string)$existing['insignia'] !== $data['insignia']) {
                    throw new \RuntimeException('Para mudar a insígnia de um cardex ativo, duplica-o como novo rascunho.');
                }
                if ((int)$existing['client_id'] !== $data['client_id'] || (int)$existing['project_id'] !== $data['project_id']) {
                    throw new \RuntimeException('O cliente e o projeto do cardex duplicado não podem ser alterados.');
                }
                if (!CardexCloneService::supportedInsignia($data['insignia'])) {
                    throw new \RuntimeException('Escreve uma insígnia comercial válida para o cardex.');
                }
                $data['insignia'] = CardexCloneService::canonicalInsignia($data['insignia']);
            }
            if ($scope_only) {
                if (!$existing) throw new \RuntimeException('Nao existe Cardex para alterar o contexto.');
                if ($wpdb->update(Cardex::table_cardex(), [
                    'client_id'=>$data['client_id'],
                    'project_id'=>$data['project_id'],
                    'updated_at'=>$data['updated_at'],
                ], ['id'=>$id]) === false) throw new \RuntimeException('Falha ao guardar o contexto comercial.');
            } elseif ($existing && $wpdb->update(Cardex::table_cardex(), $data, ['id'=>$id]) === false) {
                throw new \RuntimeException('Falha ao atualizar o cabeçalho do cardex.');
            }
            if (!$existing) {
                $data['created_at'] = current_time('mysql');
                if ($wpdb->insert(Cardex::table_cardex(), $data) === false || !($id = (int)$wpdb->insert_id)) {
                    throw new \RuntimeException('Falha ao criar o rascunho.');
                }
            }
            if ($existing && (($existing['status'] ?? '') === 'draft' || CardexCloneService::isClone($existing)) && $requested_locations) {
                $selected = array_unique(array_filter(array_map('absint',$requested_locations)));
                $placeholders = implode(',',array_fill(0,count($selected),'%d'));
                $rows = $wpdb->get_results($wpdb->prepare(
                    'SELECT id,name,tags,meta_json,client_id,project_id FROM '.$wpdb->prefix.'routespro_locations WHERE id IN ('.$placeholders.')',
                    ...array_values($selected)
                ),ARRAY_A) ?: [];
                if (count($rows)!==count($selected)) throw new \RuntimeException('Uma das lojas selecionadas não existe.');
                foreach ($rows as $row) {
                    if (!CardexCloneService::locationInScope($row,$data['client_id'],$data['project_id'],$data['insignia'])) {
                        throw new \RuntimeException('Loja incompatível com a insígnia, o cliente ou o projeto. A seleção não foi guardada.');
                    }
                }
            }
            if (!$scope_only) {
                self::sync_items($id, (string)wp_unslash($_POST['products_text'] ?? ''));
                self::replace_locations($id, $requested_locations);
            }
            if ($wpdb->query('COMMIT') === false) throw new \RuntimeException('Falha ao confirmar as alterações do cardex.');
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            wp_safe_redirect(add_query_arg([
                'page'=>'routespro-product-cardex','edit'=>$id,
                'cardex_error'=>$error->getMessage(),
            ], admin_url('admin.php')));
            exit;
        }
        if ($existing && CardexCloneService::isClone($existing)) {
            CardexCloneService::rememberInsignia($data['insignia']);
        }
        do_action('fieldflow_mapping_source_changed', 'cardex', $id);
        wp_safe_redirect(admin_url('admin.php?page=routespro-product-cardex&edit=' . $id . '&saved=1'));
        exit;
    }

    public static function handle_duplicate(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        $sourceId = absint($_POST['source_id'] ?? 0);
        check_admin_referer('routespro_duplicate_product_cardex_'.$sourceId,'routespro_product_cardex_nonce');
        try {
            $created = CardexCloneService::duplicate($sourceId,
                sanitize_text_field(wp_unslash((string)($_POST['destination_insignia'] ?? ''))),
                sanitize_text_field(wp_unslash((string)($_POST['name'] ?? '')))
            );
            wp_safe_redirect(admin_url('admin.php?page=routespro-product-cardex&edit='.$created.'&duplicated=1'));
        } catch (\Throwable $error) {
            wp_safe_redirect(add_query_arg(['page'=>'routespro-product-cardex','duplicate'=>$sourceId,
                'cardex_error'=>$error->getMessage()],admin_url('admin.php')));
        }
        exit;
    }

    public static function handle_publish(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        $id=absint($_POST['id'] ?? 0);
        check_admin_referer('routespro_publish_product_cardex_'.$id,'routespro_product_cardex_nonce');
        try {
            CardexCloneService::publish($id,
                sanitize_key((string)($_POST['publish_scope'] ?? '')),
                !empty($_POST['confirm_publication'])
            );
            wp_safe_redirect(admin_url('admin.php?page=routespro-product-cardex&edit='.$id.'&published=1'));
        } catch (\Throwable $error) {
            wp_safe_redirect(add_query_arg(['page'=>'routespro-product-cardex','edit'=>$id,
                'cardex_error'=>$error->getMessage()],admin_url('admin.php')));
        }
        exit;
    }

    private static function sync_items(int $cardex_id, string $text): void {
        global $wpdb;
        $existing = $wpdb->get_results($wpdb->prepare(
            "SELECT id, reference, product_name FROM " . Cardex::table_items() . " WHERE cardex_id=%d ORDER BY id ASC",
            $cardex_id
        ), ARRAY_A) ?: [];
        $by_reference = [];
        $by_name_without_ref = [];
        foreach ($existing as $item) {
            $item_id = absint($item['id'] ?? 0);
            $reference_key = self::identity_key((string)($item['reference'] ?? ''));
            $name_key = self::identity_key((string)($item['product_name'] ?? ''));
            if ($reference_key !== '' && !isset($by_reference[$reference_key])) $by_reference[$reference_key] = $item_id;
            if ($reference_key === '' && $name_key !== '' && !isset($by_name_without_ref[$name_key])) $by_name_without_ref[$name_key] = $item_id;
        }

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $sort_order = 0;
        $kept_ids = [];
        $seen_rows = [];

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') continue;
            $parts = str_getcsv($line, ';');
            if (count($parts) < 2) $parts = str_getcsv($line, ',');
            $parts = array_map(static function($value): string { return trim((string)$value); }, $parts);
            $first = strtolower((string)($parts[0] ?? ''));
            $second = strtolower((string)($parts[1] ?? ''));
            if (in_array($first, ['categoria','category'], true) && in_array($second, ['codigo','código','referencia','reference','ref'], true)) continue;

            $parsed = self::parse_product_parts($parts);
            if ($parsed['product_name'] === '') continue;
            $reference_key = self::identity_key($parsed['reference']);
            $name_key = self::identity_key($parsed['product_name']);
            $row_key = $reference_key !== '' ? 'r:' . $reference_key : 'n:' . $name_key;
            if ($row_key !== '' && isset($seen_rows[$row_key])) throw new \RuntimeException('Há referências repetidas no editor do cardex. Revê as linhas antes de guardar.');
            if ($row_key !== '') $seen_rows[$row_key] = true;

            $item_id = 0;
            if ($reference_key !== '' && isset($by_reference[$reference_key])) $item_id = (int)$by_reference[$reference_key];
            if (!$item_id && $reference_key === '' && $name_key !== '' && isset($by_name_without_ref[$name_key])) $item_id = (int)$by_name_without_ref[$name_key];
            $data = [
                'cardex_id' => $cardex_id,
                'category' => $parsed['category'],
                'reference' => $parsed['reference'],
                'item_type' => $parsed['item_type'],
                'product_name' => $parsed['product_name'],
                'capacity_cl' => $parsed['capacity_cl'] === '' ? null : $parsed['capacity_cl'],
                'units_per_case' => $parsed['units_per_case'] === '' ? null : $parsed['units_per_case'],
                'sort_order' => $sort_order++,
                'is_active' => 1,
                'updated_at' => current_time('mysql'),
            ];
            if ($item_id) {
                $wpdb->update(Cardex::table_items(), $data, ['id' => $item_id]);
            } else {
                $data['created_at'] = current_time('mysql');
                $wpdb->insert(Cardex::table_items(), $data);
                $item_id = (int)$wpdb->insert_id;
            }
            if ($item_id) $kept_ids[] = $item_id;
        }

        $existing_ids = array_map('intval', array_column($existing, 'id'));
        $removed_ids = array_values(array_diff($existing_ids, array_unique($kept_ids)));
        if ($removed_ids) {
            $id_list = implode(',', array_map('absint', $removed_ids));
            $wpdb->query("DELETE FROM " . Cardex::table_item_locations() . " WHERE item_id IN ({$id_list})");
            $wpdb->query("DELETE FROM " . Cardex::table_items() . " WHERE cardex_id=" . absint($cardex_id) . " AND id IN ({$id_list})");
        }
    }

    private static function identity_key(string $value): string {
        $value = strtolower(remove_accents(trim($value)));
        return preg_replace('/[^a-z0-9]+/', '', $value) ?: '';
    }

    private static function parse_product_parts(array $parts): array {
        $category = $reference = $item_type = $product = '';
        $capacity = $units = '';
        if (count($parts) >= 6) {
            [$category, $reference, $item_type, $product, $capacity, $units] = array_pad($parts, 6, '');
        } elseif (count($parts) === 5) {
            [$category, $reference, $item_type, $product, $capacity] = $parts;
        } elseif (count($parts) === 4) {
            [$category, $reference, $item_type, $product] = $parts;
        } elseif (count($parts) === 3) {
            [$category, $reference, $product] = $parts;
        } elseif (count($parts) === 2) {
            [$reference, $product] = $parts;
        } elseif (count($parts) === 1) {
            $product = $parts[0];
        }
        if ($product === '' && $reference !== '') { $product = $reference; $reference = ''; }
        return [
            'category' => sanitize_text_field($category),
            'reference' => sanitize_text_field($reference),
            'item_type' => sanitize_text_field($item_type),
            'product_name' => sanitize_text_field($product),
            'capacity_cl' => self::number_or_blank($capacity),
            'units_per_case' => self::number_or_blank($units),
        ];
    }

    private static function replace_locations(int $cardex_id, array $locations, bool $verify = true): void {
        global $wpdb;
        $requested = array_values(array_unique(array_filter(array_map('absint', $locations))));
        sort($requested, SORT_NUMERIC);
        $locations = $requested;

        if ($locations) {
            $placeholders = implode(',', array_fill(0, count($locations), '%d'));
            $existing = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}routespro_locations WHERE id IN ({$placeholders})",
                ...$locations
            ));
            $locations = array_values(array_unique(array_map('intval', $existing ?: [])));
            sort($locations, SORT_NUMERIC);
            if ($verify && $locations !== $requested) {
                throw new \RuntimeException('Uma ou mais lojas selecionadas já não existem.');
            }
        }

        if ($wpdb->delete(Cardex::table_locations(), ['cardex_id' => $cardex_id], ['%d']) === false) {
            throw new \RuntimeException('Falha ao limpar as associações anteriores do Cardex.');
        }
        foreach ($locations as $location_id) {
            $inserted = $wpdb->insert(Cardex::table_locations(), [
                'cardex_id' => $cardex_id,
                'location_id' => $location_id,
                'created_at' => current_time('mysql'),
            ], ['%d', '%d', '%s']);
            if ($inserted === false) {
                throw new \RuntimeException('Falha ao associar a loja #' . $location_id . ' ao Cardex.');
            }
        }

        if ($verify) {
            $persisted = self::linked_locations($cardex_id);
            sort($persisted, SORT_NUMERIC);
            if ($persisted !== $locations) {
                throw new \RuntimeException('As associações persistidas não correspondem às lojas selecionadas.');
            }
        }
    }

    public static function handle_reset_store_override(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        $cardex_id = absint($_GET['cardex_id'] ?? 0);
        $location_id = absint($_GET['location_id'] ?? 0);
        check_admin_referer('routespro_reset_product_cardex_store_override_' . $cardex_id . '_' . $location_id);
        Cardex::delete_store_override($cardex_id, $location_id);
        wp_safe_redirect(admin_url('admin.php?page=routespro-product-cardex&edit=' . $cardex_id . '&saved=1'));
        exit;
    }

    public static function handle_delete(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        $id = absint($_GET['id'] ?? 0);
        check_admin_referer('routespro_delete_product_cardex_' . $id);
        global $wpdb;
        Cardex::install();
        Cardex::delete_cardex_store_state($id);
        $wpdb->delete(Cardex::table_item_locations(), ['cardex_id' => $id]);
        $wpdb->delete(Cardex::table_items(), ['cardex_id' => $id]);
        $wpdb->delete(Cardex::table_locations(), ['cardex_id' => $id]);
        $wpdb->delete(Cardex::table_cardex(), ['id' => $id]);
        wp_safe_redirect(admin_url('admin.php?page=routespro-product-cardex'));
        exit;
    }

    public static function download_template(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        nocache_headers();
        header('Content-Type:text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="fieldflow-cardex-encomenda-template.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['cardex','insignia','client_id','project_id','categoria','codigo','tipo','produto','capacidade_cl','unidades_caixa','location_id','external_ref','loja','ordem','ativo']);
        fputcsv($out, ['Pernod Ricard Sonae','Continente','0','0','GIN','2050501','PBL','GIN BEEFEATER 70CL','70','12','','L0001','CNT MATOSINHOS','1','1']);
        fputcsv($out, ['Pernod Ricard Sonae','Continente','0','0','VODKA','2050752','PBL','VODKA ABSOLUT 70CL','70','12','','L0001','CNT MATOSINHOS','2','1']);
        fclose($out);
        exit;
    }

    public static function handle_import(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('routespro_import_product_cardex', 'routespro_product_cardex_import_nonce');
        Cardex::install();
        if (empty($_FILES['cardex_csv']['tmp_name'])) wp_die('CSV em falta.');

        $replace = !empty($_POST['replace_items']);
        $handle = fopen($_FILES['cardex_csv']['tmp_name'], 'r');
        if (!$handle) wp_die('Não foi possível ler o CSV.');
        $first_line = fgets($handle);
        if ($first_line === false) wp_die('CSV sem cabeçalho.');
        $delimiter = substr_count($first_line, ';') > substr_count($first_line, ',') ? ';' : ',';
        $headers = str_getcsv($first_line, $delimiter);
        if (!$headers) wp_die('CSV sem cabeçalho.');
        $headers = array_map(static function($header): string {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header);
            $header = remove_accents(strtolower(trim((string)$header)));
            return str_replace([' ', '-'], '_', $header);
        }, $headers);

        global $wpdb;
        $seen_cardex = [];
        $item_cache = [];
        $stats = ['products'=>0,'mapped'=>0,'unmatched'=>0];
        $unmatched_locations = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $data = [];
            foreach ($headers as $index => $header) $data[$header] = $row[$index] ?? '';

            $name = sanitize_text_field($data['cardex'] ?? $data['insignia'] ?? '');
            if ($name === '') continue;
            $insignia = sanitize_text_field($data['insignia'] ?? $name);
            $client_id = absint($data['client_id'] ?? 0);
            $project_id = absint($data['project_id'] ?? 0);
            $cardex_id = self::find_or_create_cardex($name, $insignia, $client_id, $project_id);

            if ($replace && empty($seen_cardex[$cardex_id])) {
                $wpdb->delete(Cardex::table_item_locations(), ['cardex_id' => $cardex_id]);
                $wpdb->delete(Cardex::table_items(), ['cardex_id' => $cardex_id]);
                $wpdb->delete(Cardex::table_locations(), ['cardex_id' => $cardex_id]);
                $seen_cardex[$cardex_id] = true;
            }

            $product = sanitize_text_field($data['produto'] ?? $data['product'] ?? $data['nome'] ?? '');
            $reference = sanitize_text_field($data['codigo'] ?? $data['código'] ?? $data['referencia'] ?? $data['reference'] ?? $data['ref'] ?? '');
            $item_id = 0;
            if ($product !== '') {
                $identity = $reference !== '' ? 'r:' . strtolower($reference) : 'n:' . strtolower($product);
                $cache_key = $cardex_id . '|' . $identity;
                if (isset($item_cache[$cache_key])) {
                    $item_id = $item_cache[$cache_key];
                } else {
                    $item_id = self::find_or_upsert_item($cardex_id, [
                        'category' => sanitize_text_field($data['categoria'] ?? $data['category'] ?? ''),
                        'reference' => $reference,
                        'item_type' => sanitize_text_field($data['tipo'] ?? $data['type'] ?? ''),
                        'product_name' => $product,
                        'capacity_cl' => self::number_or_blank($data['capacidade_cl'] ?? $data['cap_cl'] ?? $data['capacidade'] ?? ''),
                        'units_per_case' => self::number_or_blank($data['unidades_caixa'] ?? $data['un_cx'] ?? $data['units_per_case'] ?? ''),
                        'sort_order' => absint($data['ordem'] ?? $data['order'] ?? 0),
                        'is_active' => self::truthy($data['ativo'] ?? 1) ? 1 : 0,
                    ]);
                    $item_cache[$cache_key] = $item_id;
                    $stats['products']++;
                }
            }

            $has_location_input = !empty($data['location_id']) || !empty($data['external_ref']) || !empty($data['loja']) || !empty($data['store']);
            $location_id = self::resolve_location_id($data, $client_id, $project_id, $insignia);
            if ($location_id) {
                $wpdb->replace(Cardex::table_locations(), [
                    'cardex_id' => $cardex_id,
                    'location_id' => $location_id,
                    'created_at' => current_time('mysql'),
                ]);
                if ($item_id) {
                    $wpdb->replace(Cardex::table_item_locations(), [
                        'cardex_id' => $cardex_id,
                        'item_id' => $item_id,
                        'location_id' => $location_id,
                        'is_active' => 1,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ]);
                    $stats['mapped']++;
                }
            } elseif ($has_location_input) {
                $unmatched_key = self::normalize_text((string)($data['external_ref'] ?? '') . '|' . (string)($data['loja'] ?? $data['store'] ?? ''));
                if ($unmatched_key === '') $unmatched_key = md5(wp_json_encode($data));
                $unmatched_locations[$unmatched_key] = true;
            }
        }
        fclose($handle);
        $stats['unmatched'] = count($unmatched_locations);
        do_action('fieldflow_mapping_source_changed', 'cardex_import', 0);

        $url = add_query_arg([
            'page'=>'routespro-product-cardex',
            'imported'=>1,
            'products'=>$stats['products'],
            'mapped'=>$stats['mapped'],
            'unmatched'=>$stats['unmatched'],
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    private static function find_or_create_cardex(string $name, string $insignia, int $client_id, int $project_id): int {
        global $wpdb;
        $id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . Cardex::table_cardex() . " WHERE name=%s AND client_id=%d AND project_id=%d AND insignia=%s AND status='active' LIMIT 1",
            $name, $client_id, $project_id, $insignia
        ));
        if ($id) return $id;
        // 9DP: identical display names from different brands must never share an assortment.
        $collision=(int)$wpdb->get_var($wpdb->prepare(
            'SELECT id FROM '.Cardex::table_cardex().' WHERE name=%s AND client_id=%d AND project_id=%d LIMIT 1',
            $name,$client_id,$project_id
        ));
        if ($collision) {
            $name .= ' (' . $insignia . ')';
            $id = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM ".Cardex::table_cardex()." WHERE name=%s AND client_id=%d AND project_id=%d AND insignia=%s AND status='active' LIMIT 1",
                $name,$client_id,$project_id,$insignia
            ));
            if ($id) return $id;
        }
        $wpdb->insert(Cardex::table_cardex(), [
            'name'=>$name,'insignia'=>$insignia,'client_id'=>$client_id,'project_id'=>$project_id,
            'status'=>'active','created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'),
        ]);
        return (int)$wpdb->insert_id;
    }

    private static function find_or_upsert_item(int $cardex_id, array $item): int {
        global $wpdb;
        $item_id = 0;
        if ($item['reference'] !== '') {
            $item_id = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . Cardex::table_items() . " WHERE cardex_id=%d AND reference=%s LIMIT 1",
                $cardex_id, $item['reference']
            ));
        }
        if (!$item_id && $item['reference'] === '') {
            $item_id = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . Cardex::table_items() . " WHERE cardex_id=%d AND product_name=%s AND (reference='' OR reference IS NULL) LIMIT 1",
                $cardex_id, $item['product_name']
            ));
        }
        $data = [
            'cardex_id'=>$cardex_id,'category'=>$item['category'],'reference'=>$item['reference'],
            'item_type'=>$item['item_type'],'product_name'=>$item['product_name'],
            'capacity_cl'=>$item['capacity_cl']===''?null:$item['capacity_cl'],
            'units_per_case'=>$item['units_per_case']===''?null:$item['units_per_case'],
            'sort_order'=>$item['sort_order'],'is_active'=>$item['is_active'],'updated_at'=>current_time('mysql'),
        ];
        if ($item_id) {
            $wpdb->update(Cardex::table_items(), $data, ['id'=>$item_id]);
            return $item_id;
        }
        $data['created_at'] = current_time('mysql');
        $wpdb->insert(Cardex::table_items(), $data);
        return (int)$wpdb->insert_id;
    }

    private static function resolve_location_id(array $data, int $client_id = 0, int $project_id = 0, string $insignia = ''): int {
        $direct = absint($data['location_id'] ?? 0);
        if ($direct) {
            global $wpdb;
            $loc=$wpdb->get_row($wpdb->prepare(
                'SELECT id,name,tags,meta_json,client_id,project_id FROM '.$wpdb->prefix.'routespro_locations WHERE id=%d', $direct
            ),ARRAY_A);
            return ($loc && CardexCloneService::locationInScope($loc,$client_id,$project_id,$insignia)) ? $direct : 0;
        }
        $lookup = self::get_location_lookup($client_id, $project_id, $insignia);
        $external = sanitize_text_field($data['external_ref'] ?? '');
        $store = sanitize_text_field($data['loja'] ?? $data['store'] ?? '');
        foreach ([$external, $store] as $value) {
            if ($value === '') continue;
            $norm = self::normalize_text($value);
            $code = self::normalize_location_code($value);
            if ($norm !== '' && count($lookup['exact'][$norm] ?? []) === 1) return (int)$lookup['exact'][$norm][0];
            if ($code !== '' && count($lookup['code'][$code] ?? []) === 1) return (int)$lookup['code'][$code][0];
            $name = self::normalize_store_name($value);
            if ($name !== '' && count($lookup['name'][$name] ?? []) === 1) return (int)$lookup['name'][$name][0];
        }
        return 0;
    }

    private static function get_location_lookup(int $client_id = 0, int $project_id = 0, string $insignia = ''): array {
        $cache_key = $client_id . '|' . $project_id . '|' . self::normalize_text($insignia);
        if (isset(self::$location_lookup[$cache_key])) return self::$location_lookup[$cache_key];
        $lookup = ['exact'=>[],'code'=>[],'name'=>[]];
        foreach (self::locations() as $row) {
            if ($client_id && !empty($row['client_id']) && (int)$row['client_id'] !== $client_id) continue;
            if ($client_id && $project_id && $insignia !== ''
                && !CardexCloneService::locationInScope($row,$client_id,$project_id,$insignia)) continue;
            if ($project_id && !empty($row['project_id']) && (int)$row['project_id'] !== $project_id) {
                // Project membership may be provided via campaign_locations.
                if (!CardexCloneService::locationInScope($row,$client_id,$project_id,$insignia)) continue;
            }
            $hay = self::normalize_text((string)($row['name'] ?? '') . ' ' . (string)($row['tags'] ?? '') . ' ' . (string)($row['meta_json'] ?? ''));
            $brand = self::normalize_text($insignia);
            if ($brand !== '' && self::contains_known_other_brand($hay, $brand)) continue;
            $id = (int)$row['id'];
            foreach ([(string)($row['external_ref'] ?? ''),(string)($row['name'] ?? '')] as $value) {
                $norm = self::normalize_text($value);
                if ($norm !== '') $lookup['exact'][$norm][] = $id;
                $code = self::normalize_location_code($value);
                if ($code !== '') $lookup['code'][$code][] = $id;
                $name = self::normalize_store_name($value);
                if ($name !== '') $lookup['name'][$name][] = $id;
            }
        }
        foreach ($lookup as $type=>$values) foreach ($values as $key=>$ids) $lookup[$type][$key] = array_values(array_unique(array_map('intval',$ids)));
        self::$location_lookup[$cache_key] = $lookup;
        return $lookup;
    }

    private static function contains_known_other_brand(string $candidate, string $wanted): bool {
        $brands = ['CONTINENTE','MODELO','BOMDIA','AUCHAN','JUMBO','PINGODOCE','RECHEIO','INTERMARCHE','MINIPRECO','ELCORTEINGLES','MERCADONA','LIDL','ALDI','FROIZ','APOLONIA','MAKRO','ELECLERC'];
        $candidate_hits = array_values(array_filter($brands, static fn($brand) => strpos($candidate, $brand) !== false));
        if (!$candidate_hits) return false;
        foreach ($candidate_hits as $hit) if (strpos($wanted, $hit) !== false || strpos($hit, $wanted) !== false) return false;
        return true;
    }

    private static function normalize_text(string $value): string {
        $value = strtoupper(remove_accents(trim($value)));
        return preg_replace('/[^A-Z0-9]+/', '', $value) ?: '';
    }

    private static function normalize_location_code(string $value): string {
        $upper = strtoupper(remove_accents($value));
        if (preg_match('/(?:^|[^0-9])L?0*([0-9]{1,4})(?:[^0-9]|$)/', $upper, $m)) {
            return 'L' . str_pad((string)((int)$m[1]), 4, '0', STR_PAD_LEFT);
        }
        return '';
    }

    private static function normalize_store_name(string $value): string {
        $value = strtoupper(remove_accents(trim($value)));
        $value = preg_replace('/^L?0*[0-9]{1,4}\s*[-–:]\s*/', '', $value);
        return preg_replace('/[^A-Z0-9]+/', '', (string)$value) ?: '';
    }

    private static function truthy($value): bool {
        if (is_bool($value)) return $value;
        $value = strtolower(trim((string)$value));
        return !in_array($value, ['0','false','nao','não','no','inativo'], true);
    }

    private static function number_or_blank($value) {
        if ($value === '' || $value === null) return '';
        $value = str_replace(',', '.', trim((string)$value));
        return is_numeric($value) ? (float)$value : '';
    }

    private static function format_number($value): string {
        if ($value === '' || $value === null) return '';
        $number = (float)$value;
        return ((float)(int)$number === $number) ? (string)(int)$number : rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
