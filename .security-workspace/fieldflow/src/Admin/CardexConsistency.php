<?php
namespace RoutesPro\Admin;

use RoutesPro\Forms\ProductCardex as Cardex;
use RoutesPro\Forms\ContextQuestions;
use RoutesPro\Forms\Forms as FieldForms;
use RoutesPro\Stores\StoreIdentityAuthority;

if (!defined('ABSPATH')) exit;

/**
 * 9DW: read-only, bounded Cardex / PDV / Forms / Briefing / Stocks diagnostics.
 * This page deliberately does NOT repair mappings, create links, publish Cardex,
 * change store assortment, overwrite imported stock or touch visit answers.
 */
final class CardexConsistency {
    public const CONTRACT = 'fieldflow-cardex-data-consistency-center-9dw-v1';
    private const PER_PAGE = 30;

    public static function classify(array $row): array {
        $code = 'ok';
        $label = 'Cardex resolvido';
        if (empty($row['cardex_id'])) {
            $code = 'missing_cardex'; $label = 'Sem cardex válido para a visita';
        } elseif (empty($row['items_count'])) {
            $code = 'empty_assortment'; $label = 'Sortido efetivo vazio';
        } elseif (!empty($row['duplicate_sku'])) {
            $code = 'duplicate_sku'; $label = 'SKU repetidos no sortido da loja';
        } elseif (!empty($row['stock_retailer_mismatch'])) {
            $code = 'stock_retailer_mismatch'; $label = 'Origem de stock de outra insígnia';
        } elseif (!empty($row['stock_foreign_sku'])) {
            $code = 'stock_foreign_sku'; $label = 'Stock ligado a SKU de outro cardex';
        } elseif (!empty($row['linked_count']) && (int)$row['linked_count'] > 1) {
            $code = 'multiple_links'; $label = 'Vários cardex ligados; rever prioridade';
        } elseif (!empty($row['stock_unmatched_sku'])) {
            $code = 'stock_unmatched_sku'; $label = 'SKU de stock por reconciliar';
        }
        return ['code'=>$code,'label'=>$label,'ok'=>$code==='ok'];
    }

    private static function installed(string $table): bool {
        global $wpdb;
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private static function scopeChoices(): array {
        global $wpdb;
        return [
            $wpdb->get_results('SELECT id,name FROM '.$wpdb->prefix.'routespro_clients ORDER BY name,id', ARRAY_A) ?: [],
            $wpdb->get_results('SELECT id,name,client_id FROM '.$wpdb->prefix.'routespro_projects ORDER BY name,id', ARRAY_A) ?: [],
        ];
    }

    private static function url(array $params = []): string {
        return add_query_arg(array_merge(['page'=>'routespro-product-cardex','consistency'=>1],$params),admin_url('admin.php'));
    }

    private static function storeList(int $client, int $project, string $retailer, string $query, int $page): array {
        global $wpdb;
        $locations=$wpdb->prefix.'routespro_locations';
        $campaign=$wpdb->prefix.'routespro_campaign_locations';
        $where=['COALESCE(l.is_active,1)=1']; $args=[];
        if ($client>0) {
            $where[]='(l.client_id=%d OR EXISTS (SELECT 1 FROM '.$campaign.' ac INNER JOIN '.$wpdb->prefix.'routespro_projects p ON p.id=ac.project_id WHERE ac.location_id=l.id AND ac.is_active=1 AND p.client_id=%d))';
            array_push($args,$client,$client);
        }
        if ($project>0) {
            $where[]='(l.project_id=%d OR EXISTS (SELECT 1 FROM '.$campaign.' ac WHERE ac.location_id=l.id AND ac.project_id=%d AND ac.is_active=1))';
            array_push($args,$project,$project);
        }
        // Only a candidate text filter; the real acceptance test is the 9DN identity authority.
        $retailerNames = [
            'sonae'=>['continente','modelo','bom dia','sonae'],
            'pingo_doce'=>['pingo doce','pingo_doce'],
            'makro'=>['makro'], 'recheio'=>['recheio'],
            'auchan'=>['auchan'], 'intermarche'=>['intermarche','intermarché'],
            'arcol'=>['arcol'], 'garcias'=>['garcias'],
        ];
        if ($retailer!=='' && isset($retailerNames[$retailer])) {
            $terms=[];
            foreach ($retailerNames[$retailer] as $candidate) {
                $terms[]="CONCAT_WS(' ',l.name,l.tags,l.meta_json) LIKE %s";
                $args[]='%'.$wpdb->esc_like($candidate).'%';
            }
            $where[]='('.implode(' OR ',$terms).')';
        }
        if ($query!=='') {
            $where[]='(l.name LIKE %s OR l.external_ref LIKE %s OR CAST(l.id AS CHAR)=%s)';
            $like='%'.$wpdb->esc_like($query).'%';
            array_push($args,$like,$like,$query);
        }
        $clause=implode(' AND ',$where);
        $totalSql='SELECT COUNT(*) FROM '.$locations.' l WHERE '.$clause;
        $total=(int)$wpdb->get_var($args ? $wpdb->prepare($totalSql,...$args) : $totalSql);
        $pages=max(1,(int)ceil($total/self::PER_PAGE));
        $page=max(1,min($pages,$page));
        $sql='SELECT l.id,l.name,l.external_ref,l.tags,l.meta_json,l.client_id,l.project_id FROM '.$locations.' l WHERE '.$clause.' ORDER BY l.name ASC,l.id ASC LIMIT %d OFFSET %d';
        $rows=$wpdb->get_results($wpdb->prepare($sql,...array_merge($args,[self::PER_PAGE,($page-1)*self::PER_PAGE])),ARRAY_A) ?: [];
        return [$rows,$total,$page,$pages];
    }

    private static function recentContexts(array $ids, int $project = 0, int $client = 0): array {
        global $wpdb;
        if (!$ids) return [];
        $params=array_values(array_unique(array_filter(array_map('absint',$ids))));
        if (!$params) return [];
        $ph=implode(',',array_fill(0,count($params),'%d'));
        $where=' WHERE rs.location_id IN ('.$ph.')';
        if ($project>0) { $where.=' AND r.project_id=%d';$params[]=$project; }
        if ($client>0) { $where.=' AND r.client_id=%d';$params[]=$client; }
        $sql='SELECT rs.location_id,r.id AS route_id,r.date,r.client_id,r.project_id FROM '.$wpdb->prefix.'routespro_route_stops rs INNER JOIN '.$wpdb->prefix.'routespro_routes r ON r.id=rs.route_id'.$where.' ORDER BY r.date DESC,r.id DESC LIMIT 1200';
        $routes=$wpdb->get_results($wpdb->prepare($sql,...$params),ARRAY_A) ?: [];
        $latest=[];
        foreach ($routes as $row) { $id=absint($row['location_id'] ?? 0);if ($id && !isset($latest[$id])) $latest[$id]=$row; }
        return $latest;
    }

    private static function linkedCounts(array $ids): array {
        global $wpdb;
        if (!$ids) return [];
        $ph=implode(',',array_fill(0,count($ids),'%d'));
        $rows=$wpdb->get_results($wpdb->prepare('SELECT cl.location_id,COUNT(*) total FROM '.Cardex::table_locations().' cl INNER JOIN '.Cardex::table_cardex(). ' c ON c.id=cl.cardex_id AND c.status=%s WHERE cl.location_id IN ('.$ph.') GROUP BY cl.location_id',...array_merge(['active'],$ids)),ARRAY_A) ?: [];
        $result=[];
        foreach ($rows as $row) $result[absint($row['location_id'] ?? 0)]=absint($row['total'] ?? 0);
        return $result;
    }

    private static function context(array $loc, ?array $route, int $requestedClient=0, int $requestedProject=0): array {
        $client=absint($route['client_id'] ?? $loc['client_id'] ?? 0);
        $project=absint($route['project_id'] ?? $loc['project_id'] ?? 0);
        // An explicit admin filter is a preview of that commercial project,
        // never a persisted remapping of the PDV or its route.
        if ($requestedClient>0) $client=$requestedClient;
        if ($requestedProject>0) $project=$requestedProject;
        return ['location_id'=>absint($loc['id'] ?? 0),'client_id'=>$client,'project_id'=>$project,'date'=>current_time('Y-m-d'),'route_id'=>absint($route['route_id'] ?? 0)];
    }

    private static function stockSnapshot(int $locationId, int $clientId, int $projectId, int $cardexId, array $location): array {
        global $wpdb;
        $base=['available'=>false,'batch_id'=>0,'date'=>'','insignia'=>'','rows'=>0,'unmatched_sku'=>0,'foreign_sku'=>0,'retailer_mismatch'=>false,'source_names'=>[]];
        if (!$locationId || !$clientId || !$projectId) return $base;
        $b=$wpdb->prefix.'routespro_stock_batches';$i=$wpdb->prefix.'routespro_stock_items';
        if (!self::installed($b) || !self::installed($i)) return $base;
        $batch=$wpdb->get_row($wpdb->prepare(
            'SELECT b.id,b.insignia,b.snapshot_date FROM '.$b.' b INNER JOIN '.$i.' i ON i.batch_id=b.id WHERE i.location_id=%d AND b.client_id=%d AND b.project_id=%d AND b.status=%s ORDER BY b.snapshot_date DESC,b.id DESC LIMIT 1',
            $locationId,$clientId,$projectId,'published'
        ),ARRAY_A);
        if (!$batch) return $base;
        $batchId=absint($batch['id'] ?? 0);
        $items=$wpdb->get_results($wpdb->prepare(
            'SELECT i.store_name,i.article_ref,i.ean,i.product_name,i.cardex_item_id,ci.cardex_id AS valid_cardex FROM '.$i.' i LEFT JOIN '.Cardex::table_items().' ci ON ci.id=i.cardex_item_id AND ci.is_active=1 WHERE i.batch_id=%d AND i.location_id=%d ORDER BY i.id ASC LIMIT 1000',
            $batchId,$locationId
        ),ARRAY_A) ?: [];
        $base['available']=true;$base['batch_id']=$batchId;$base['date']=(string)($batch['snapshot_date'] ?? '');$base['insignia']=(string)($batch['insignia'] ?? '');
        // Counts are exact even when the display sample is capped at 1000.
        $agg=$wpdb->get_row($wpdb->prepare('SELECT COUNT(*) AS rows_total,SUM(CASE WHEN cardex_item_id=0 THEN 1 ELSE 0 END) AS unmatched FROM '.$i.' WHERE batch_id=%d AND location_id=%d',$batchId,$locationId),ARRAY_A) ?: [];
        $base['rows']=absint($agg['rows_total'] ?? 0);$base['unmatched_sku']=absint($agg['unmatched'] ?? 0);
        $names=[];$foreign=0;$sourceMismatch=false;
        $storeFamily=StoreIdentityAuthority::locationFamily($location);
        $batchFamily=StoreIdentityAuthority::family($base['insignia']);
        if ($storeFamily!=='' && $batchFamily!=='' && $batchFamily!==$storeFamily) $sourceMismatch=true;
        foreach ($items as $item) {
            if (absint($item['cardex_item_id'] ?? 0) && $cardexId && absint($item['valid_cardex'] ?? 0)!==$cardexId) $foreign++;
            $source=trim((string)($item['store_name'] ?? ''));
            if ($source!=='' && count($names)<8) $names[$source]=true;
            $sourceFamily=StoreIdentityAuthority::family($source);
            if ($storeFamily!=='' && $sourceFamily!=='' && $sourceFamily!==$storeFamily) $sourceMismatch=true;
        }
        $base['foreign_sku']=$foreign;
        // If samples were capped, avoid presenting a precise foreign-SKU total.
        $base['foreign_sample_limited']=$base['rows']>count($items);
        $base['retailer_mismatch']=$sourceMismatch;
        $base['source_names']=array_keys($names);
        return $base;
    }

    private static function briefingQuestions(array $context): array {
        global $wpdb;
        $table=ContextQuestions::table();
        if (!self::installed($table)) return [];
        $rows=ContextQuestions::briefing_for_context($context);
        if (!$rows) return [];
        $ids=array_values(array_unique(array_filter(array_map(static fn($row)=>absint($row['briefing_id'] ?? 0),$rows))));
        if (!$ids) return [];
        $ph=implode(',',array_fill(0,count($ids),'%d'));
        $published=$wpdb->get_col($wpdb->prepare('SELECT id FROM '.$wpdb->prefix.'routespro_briefings WHERE id IN ('.$ph.') AND status=%s',...array_merge($ids,['published']))) ?: [];
        $map=array_fill_keys(array_map('intval',$published),true);
        return array_values(array_filter($rows,static fn($row)=>isset($map[absint($row['briefing_id'] ?? 0)])));
    }

    public static function inspectStore(array $location, array $context, int $linkedCount = 0, bool $includeDetail = false): array {
        $id=absint($location['id'] ?? 0);
        $cid=absint($context['client_id'] ?? 0);
        $pid=absint($context['project_id'] ?? 0);
        $cardexId=Cardex::resolve_cardex_id_for_location($id,$cid,$pid);
        $cardex=$cardexId ? Cardex::get_cardex($cardexId) : null;
        $items=$cardexId ? Cardex::get_items($cardexId,$id,true) : [];
        $seen=[];$duplicates=[];
        foreach ($items as $item) {
            $sku=strtolower(trim((string)($item['ref'] ?? '')));
            if ($sku==='') continue;
            if (isset($seen[$sku])) $duplicates[$sku]=true;
            $seen[$sku]=true;
        }
        $result=['location'=>$location,'context'=>$context,'cardex_id'=>$cardexId,'cardex'=>$cardex,'duplicate_sku'=>array_keys($duplicates),'linked_count'=>$linkedCount,'items_count'=>count($items),'items'=>$includeDetail ? array_slice($items,0,24) : [],'stock'=>[],'briefings'=>[]];
        // Stock and Briefing are context-sensitive, and are fetched on demand only
        // in a specific-store detail to avoid n x all-history database queries.
        if ($includeDetail) {
            $result['stock']=self::stockSnapshot($id,$cid,$pid,$cardexId,$location);
            $result['briefings']=self::briefingQuestions($context);
            $result['stock_retailer_mismatch']=!empty($result['stock']['retailer_mismatch']);
            $result['stock_foreign_sku']=!empty($result['stock']['foreign_sku']);
            $result['stock_unmatched_sku']=!empty($result['stock']['unmatched_sku']);
        }
        $result['status']=self::classify($result);
        return $result;
    }

    private static function colour(string $code): string {
        return $code==='ok' ? '#087b4d' : (in_array($code,['missing_cardex','empty_assortment','stock_retailer_mismatch','stock_foreign_sku','duplicate_sku'],true)?'#b42318':'#975c00');
    }

    public static function render(): void {
        global $wpdb;
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        $client=absint($_GET['cc_client'] ?? 0);
        $project=absint($_GET['cc_project'] ?? 0);
        $query=substr(sanitize_text_field(wp_unslash((string)($_GET['cc_query'] ?? ''))),0,80);
        $retailer=sanitize_key((string)($_GET['cc_retailer'] ?? ''));
        $page=max(1,absint($_GET['cc_page'] ?? 1));
        $store=absint($_GET['cc_store'] ?? 0);
        $form=absint($_GET['cc_form'] ?? 0);
        [$clients,$projects]=self::scopeChoices();
        // An explicitly selected project has a client owner. Never preview it
        // under a different client's last route when no client was selected.
        if ($project>0 && !$client) {
            foreach ($projects as $p) if (absint($p['id'] ?? 0)===$project) { $client=absint($p['client_id'] ?? 0); break; }
        }
        [$rows,$total,$page,$pages]=self::storeList($client,$project,$retailer,$query,$page);
        $ids=array_values(array_filter(array_map(static fn($r)=>absint($r['id'] ?? 0),$rows)));
        $contexts=self::recentContexts($ids,$project,$client);
        $counts=self::linkedCounts($ids);
        $clientNames=[];$projectNames=[];
        foreach ($clients as $c) $clientNames[absint($c['id'])]=(string)$c['name'];
        foreach ($projects as $p) $projectNames[absint($p['id'])]=(string)$p['name'];
        echo '<div class="wrap ff-consistency-9dw">';
        Branding::render_header('Centro de Consistência · Cardex & Dados','Diagnóstico preventivo por PDV de sortido, perguntas, briefings e stocks. Nenhuma associação é alterada.');
        echo '<style>.ff-consistency-9dw .ff-cc-panel{background:#fff;border:1px solid #e6e9ed;border-radius:14px;padding:18px;margin:16px 0;box-shadow:0 2px 12px rgba(22,30,45,.04)}.ff-consistency-9dw .ff-cc-fields{display:flex;align-items:end;flex-wrap:wrap;gap:12px}.ff-consistency-9dw .ff-cc-fields label{font-weight:600}.ff-consistency-9dw .ff-cc-fields select,.ff-consistency-9dw .ff-cc-fields input{max-width:260px;min-width:140px}.ff-consistency-9dw .ff-cc-tag{border-radius:24px;padding:4px 9px;border:1px solid currentColor;white-space:normal;display:inline-block;font-size:12px;font-weight:600}.ff-consistency-9dw td{vertical-align:middle}.ff-consistency-9dw .ff-cc-muted{color:#656b75}.ff-consistency-9dw .ff-cc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(185px,1fr));gap:12px}.ff-consistency-9dw .ff-cc-metric{padding:14px;border:1px solid #e8ebef;border-radius:12px}.ff-consistency-9dw .ff-cc-metric strong{font-size:22px;display:block;margin-top:6px}.ff-consistency-9dw .ff-cc-scroll{overflow:auto}</style>';
        echo '<p><a class="button" href="'.esc_url(admin_url('admin.php?page=routespro-product-cardex')).'">← Cardex e Catálogo</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=routespro-product-cardex&audit_bindings=1')).'">Auditoria de ligações 9DU</a></p>';
        echo '<div class="ff-cc-panel"><h2>1. Selecionar contexto comercial</h2><p class="description">A insígnia procura candidatos por nome/tags, a identidade é confirmada pelo motor 9DN. Os resultados mostram a última rota conhecida; se escolheres um projeto, o diagnóstico é simulado nesse âmbito, sem alterar dados. Só lojas ativas. Máximo de '.self::PER_PAGE.' PDV por página.</p>';
        echo '<form method="get" action="'.esc_url(admin_url('admin.php')).'" class="ff-cc-fields">';
        echo '<input type="hidden" name="page" value="routespro-product-cardex"><input type="hidden" name="consistency" value="1">';
        echo '<label>Cliente<br><select name="cc_client"><option value="0">Todos os clientes</option>';
        foreach ($clients as $c) echo '<option value="'.absint($c['id']).'"'.selected($client,absint($c['id']),false).'>'.esc_html((string)$c['name']).'</option>';
        echo '</select></label><label>Projeto<br><select name="cc_project"><option value="0">Todos os projetos</option>';
        foreach ($projects as $p) {
            if ($client && absint($p['client_id'] ?? 0)!==$client) continue;
            echo '<option value="'.absint($p['id']).'"'.selected($project,absint($p['id']),false).'>'.esc_html((string)$p['name']).'</option>';
        }
        echo '</select></label><label>Insígnia candidata<br><select name="cc_retailer"><option value="">Todas</option>';
        foreach (['sonae'=>'Sonae / Continente / Modelo / Bom Dia','pingo_doce'=>'Pingo Doce','makro'=>'Makro','recheio'=>'Recheio','auchan'=>'Auchan','intermarche'=>'Intermarché','arcol'=>'Arcol','garcias'=>'Garcias'] as $key=>$name) echo '<option value="'.esc_attr($key).'"'.selected($retailer,$key,false).'>'.esc_html($name).'</option>';
        echo '</select></label><label>Loja / código<br><input type="search" name="cc_query" value="'.esc_attr($query).'" placeholder="Ex.: Santo Tirso ou 314"></label>';
        echo '<button class="button button-primary" type="submit">Analisar</button></form></div>';
        echo '<div class="ff-cc-panel"><h2>2. Cobertura de cardex e produtos</h2><p class="description">São incluídas lojas sem qualquer cardex ligado. O estado é calculado pelo mesmo resolvedor utilizado por Frentes e Sugestão de Encomenda.</p>';
        echo '<p><strong>'.number_format_i18n($total).'</strong> lojas no filtro; página '.absint($page).' de '.absint($pages).'. Resultados desta página, não uma certificação de todos os PDV.</p>';
        $totals=['ok'=>0,'attention'=>0];
        echo '<div class="ff-cc-scroll"><table class="widefat striped" style="min-width:950px"><thead><tr><th>PDV</th><th>Cliente / projeto da análise</th><th>Cardex efetivo</th><th>Refs. loja</th><th>Ligações</th><th>Resultado</th><th>Detalhe</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $id=absint($row['id']);
            $ctx=self::context($row,$contexts[$id] ?? null,$client,$project);
            $audit=self::inspectStore($row,$ctx,$counts[$id] ?? 0);
            $cid=absint($audit['cardex_id']);$status=$audit['status'];
            $totals[$status['ok']?'ok':'attention']++;
            $params=['cc_client'=>$client,'cc_project'=>$project,'cc_retailer'=>$retailer,'cc_query'=>$query,'cc_page'=>$page,'cc_store'=>$id];
            if ($form) $params['cc_form']=$form;
            echo '<tr><td><strong>#'.$id.'</strong> '.esc_html((string)$row['name']).'</td>';
            echo '<td>'.esc_html($clientNames[absint($ctx['client_id'])] ?? 'Sem cliente').' (#'.absint($ctx['client_id']).')<br>'.esc_html($projectNames[absint($ctx['project_id'])] ?? 'Sem projeto').' (#'.absint($ctx['project_id']).')</td>';
            echo '<td>'.($cid?'#'.$cid.' '.esc_html((string)($audit['cardex']['name'] ?? '')):'<span class="ff-cc-muted">Nenhum resolvido</span>').'</td>';
            echo '<td>'.absint($audit['items_count']).'</td><td>'.absint($audit['linked_count']).'</td>';
            echo '<td><span class="ff-cc-tag" style="color:'.self::colour($status['code']).'">'.esc_html($status['label']).'</span></td>';
            echo '<td><a class="button button-small" href="'.esc_url(self::url($params)).'">Abrir diagnóstico</a></td></tr>';
        }
        if (!$rows) echo '<tr><td colspan="7">Não existem lojas para estes filtros.</td></tr>';
        echo '</tbody></table></div>';
        echo '<p><strong>Nesta página:</strong> '.absint($totals['ok']).' com sortido resolvido; '.absint($totals['attention']).' a rever. O stock e as perguntas de briefing são verificados no diagnóstico individual.</p>';
        $base=['cc_client'=>$client,'cc_project'=>$project,'cc_retailer'=>$retailer,'cc_query'=>$query];
        if ($page>1) echo '<a class="button" href="'.esc_url(self::url(array_merge($base,['cc_page'=>$page-1]))).'">← Página anterior</a> ';
        if ($page<$pages) echo '<a class="button" href="'.esc_url(self::url(array_merge($base,['cc_page'=>$page+1]))).'">Página seguinte →</a>';
        echo '</div>';
        if ($store) self::renderDetail($store,$client,$project,$form,$clientNames,$projectNames);
        echo '<p class="description">Auditoria apenas de leitura. Não corrige SKU ou insígnias automaticamente, não edita encomendas e não modifica Stocks, Briefings, rotas ou respostas.</p></div>';
    }

    private static function renderDetail(int $storeId, int $client, int $project, int $formId, array $clientNames, array $projectNames): void {
        global $wpdb;
        $loc=$wpdb->get_row($wpdb->prepare('SELECT id,name,external_ref,tags,meta_json,client_id,project_id FROM '.$wpdb->prefix.'routespro_locations WHERE id=%d',$storeId),ARRAY_A);
        if (!$loc) {echo '<div class="notice notice-error"><p>Loja não encontrada.</p></div>';return;}
        $route=self::recentContexts([$storeId],$project,$client);
        $ctx=self::context($loc,$route[$storeId] ?? null,$client,$project);
        $links=self::linkedCounts([$storeId]);
        $audit=self::inspectStore($loc,$ctx,$links[$storeId] ?? 0,true);
        $cid=absint($audit['cardex_id']);$stock=$audit['stock'];$briefings=$audit['briefings'];
        $brand=StoreIdentityAuthority::locationFamily($loc);
        $subtype=StoreIdentityAuthority::targetSubtype($loc);
        echo '<div class="ff-cc-panel" id="ff-cc-detail"><h2>3. Diagnóstico completo da loja #'.$storeId.' · '.esc_html((string)$loc['name']).'</h2>';
        echo '<p class="description">Contexto: '.esc_html($clientNames[absint($ctx['client_id'])] ?? 'Sem cliente').' / '.esc_html($projectNames[absint($ctx['project_id'])] ?? 'Sem projeto').' | '.(!empty($ctx['route_id'])?'Última rota #'.absint($ctx['route_id']):'Ficha do PDV; sem rota recente').'. Família: '.esc_html($brand ?: 'não confirmada').($subtype?' ('.esc_html($subtype).')':'').'.</p>';
        echo '<div class="ff-cc-grid">';
        echo '<div class="ff-cc-metric"><span>Cardex resolvido</span><strong>'.($cid?'#'.$cid:'Nenhum').'</strong><small>'.esc_html((string)($audit['cardex']['name'] ?? 'Por associar')).'</small></div>';
        echo '<div class="ff-cc-metric"><span>Frentes / sortido por PDV</span><strong>'.absint($audit['items_count']).'</strong><small>Referências efetivas (inclui ajustes desta loja)</small></div>';
        echo '<div class="ff-cc-metric"><span>Briefings aplicáveis hoje</span><strong>'.count($briefings).'</strong><small>Perguntas publicadas associadas ao PDV</small></div>';
        echo '<div class="ff-cc-metric"><span>Último stock publicado</span><strong>'.(!empty($stock['available'])?absint($stock['rows']):'N/D').'</strong><small>'.(!empty($stock['available'])?'Snapshot '.esc_html((string)$stock['date']):'Sem snapshot neste cliente/projeto').'</small></div>';
        echo '</div>';
        echo '<p><span class="ff-cc-tag" style="color:'.self::colour($audit['status']['code']).'">'.esc_html($audit['status']['label']).'</span> '; 
        if ($cid) echo '<a class="button button-small" href="'.esc_url(admin_url('admin.php?page=routespro-product-cardex&edit='.$cid.'&diagnose_location_id='.$storeId)).'">Ver cardex e associação</a>';
        echo '</p>';
        if (!empty($audit['duplicate_sku'])) echo '<div class="notice notice-warning inline"><p><strong>SKU repetidos no sortido:</strong> '.esc_html(implode(', ',array_slice((array)$audit['duplicate_sku'],0,15))).'. Revê o cardex, sem eliminação automática.</p></div>';
        if ($cid) {
            echo '<details><summary><strong>Referências efetivas para Frentes e Sugestão de Encomenda</strong> (primeiras 24)</summary><div class="ff-cc-scroll"><table class="widefat striped"><thead><tr><th>SKU</th><th>Produto</th><th>Categoria</th></tr></thead><tbody>';
            foreach ($audit['items'] as $item) echo '<tr><td>'.esc_html((string)($item['ref'] ?? '')).'</td><td>'.esc_html((string)($item['name'] ?? '')).'</td><td>'.esc_html((string)($item['category'] ?? '')).'</td></tr>';
            echo '</tbody></table></div></details>';
        }
        if (!$cid && absint($audit['linked_count'] ?? 0)) {
            $associations=$wpdb->get_results($wpdb->prepare(
                'SELECT c.id,c.name FROM '.Cardex::table_locations().' cl INNER JOIN '.Cardex::table_cardex().' c ON c.id=cl.cardex_id WHERE cl.location_id=%d ORDER BY cl.id DESC LIMIT 12',
                $storeId
            ),ARRAY_A) ?: [];
            echo '<h3>Associações presentes, mas não aceites pelo formulário</h3><ul>';
            foreach ($associations as $associated) {
                $check=Cardex::inspect_location_binding(absint($associated['id']),$storeId,absint($ctx['client_id']),absint($ctx['project_id']));
                echo '<li><strong>#'.absint($associated['id']).' '.esc_html((string)$associated['name']).'</strong>: '.esc_html((string)($check['message'] ?? 'Não resolvido')).'</li>';
            }
            echo '</ul>';
        }
        echo '<h3>Stocks: comparação com o último snapshot publicado</h3>';
        if (empty($stock['available'])) echo '<p>Não há snapshot publicado deste PDV no cliente e projeto selecionados. <strong>Isto não significa um erro de cardex.</strong></p>';
        else {
            echo '<p><strong>Lote #'.absint($stock['batch_id']).'</strong> | '.esc_html((string)$stock['insignia']).' | '.esc_html((string)$stock['date']).' | '.absint($stock['rows']).' linhas importadas.</p>';
            echo '<p>Referências sem ligação SKU: <strong>'.absint($stock['unmatched_sku']).'</strong>. Ligações a SKU de outro cardex na amostra: <strong>'.absint($stock['foreign_sku']).'</strong>'.(!empty($stock['foreign_sample_limited'])?' (amostra limitada a 1000 linhas)':'').'.</p>';
            if (!empty($stock['retailer_mismatch'])) echo '<div class="notice notice-warning inline"><p><strong>Rever insígnia:</strong> os dados do lote ou a identificação da loja importada não coincidem com a identidade reconhecida deste PDV. Nenhum mapeamento foi alterado.</p></div>';
            if (!empty($stock['source_names'])) echo '<p><small>Loja(s) de origem no ficheiro: '.esc_html(implode('; ',(array)$stock['source_names'])).'</small></p>';
        }
        echo '<h3>Briefings e Share Space</h3>';
        if (!$briefings) echo '<p>Não há perguntas publicadas de briefing atribuídas a esta loja para a data de hoje neste contexto. Isto pode ser normal fora de uma campanha/semana ativa.</p>';
        else {
            echo '<div class="ff-cc-scroll"><table class="widefat striped"><thead><tr><th>Briefing</th><th>Pergunta</th><th>Tipo</th></tr></thead><tbody>';
            foreach (array_slice($briefings,0,40) as $q) echo '<tr><td>#'.absint($q['briefing_id'] ?? 0).'</td><td>'.esc_html((string)($q['question_label'] ?? $q['label'] ?? $q['title'] ?? $q['question_key'] ?? '')).'</td><td>'.esc_html((string)($q['question_type'] ?? $q['type'] ?? '')).'</td></tr>';
            echo '</tbody></table></div>';
        }
        echo '<h3>Pré-visualização de perguntas de um formulário</h3>';
        $forms=FieldForms::list_forms();
        $forms=array_slice($forms,0,100);
        $selected=null;
        foreach ($forms as $f) if (absint($f['id'] ?? 0)===$formId) {$selected=$f;break;}
        echo '<form method="get" action="'.esc_url(admin_url('admin.php')).'" class="ff-cc-fields">';
        foreach (['page'=>'routespro-product-cardex','consistency'=>1,'cc_client'=>$client,'cc_project'=>$project,'cc_store'=>$storeId] as $key=>$value) echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr((string)$value).'">';
        echo '<label>Formulário<br><select name="cc_form"><option value="0">Escolher formulário</option>';
        foreach ($forms as $f) echo '<option value="'.absint($f['id']).'"'.selected($formId,absint($f['id']),false).'>#'.absint($f['id']).' · '.esc_html((string)($f['name'] ?? $f['title'] ?? 'Formulário')).'</option>';
        echo '</select></label><button class="button" type="submit">Pré-visualizar ligação</button></form>';
        if ($selected) {
            $schema=FieldForms::decode_schema($selected['schema_json'] ?? '');
            $questions=array_values(array_filter((array)($schema['questions'] ?? []),static fn($q)=>is_array($q) && in_array((string)($q['type'] ?? ''),['product_matrix','order_suggestion','briefing_block'],true)));
            if (!$questions) echo '<p>O formulário escolhido não contém Frentes, Sugestão de Encomenda ou Bloco de Briefing.</p>';
            else {
                echo '<div class="ff-cc-scroll"><table class="widefat striped"><thead><tr><th>Pergunta</th><th>Origem configurada</th><th>Cardex efetivo</th><th>Estado</th></tr></thead><tbody>';
                foreach (array_slice($questions,0,40) as $q) {
                    $type=(string)($q['type'] ?? '');
                    if ($type==='briefing_block') { echo '<tr><td>'.esc_html((string)($q['label'] ?? 'Briefing')).'</td><td>Bloco de Briefing</td><td>Por PDV</td><td>'.count($briefings).' perguntas publicadas aplicáveis (antes dos filtros do bloco)</td></tr>';continue; }
                    $source=(string)($q['product_source'] ?? (!empty($q['cardex_id'])?'cardex_fixed':'manual'));
                    $actual=Cardex::question_cardex_id($q,$ctx);
                    $available=Cardex::question_is_available_for_context($q,$ctx);
                    $message=$source==='manual'?'Lista manual desta pergunta':(!$actual?'Sem cardex válido':($available?'Disponível':'Incompatível com esta loja'));
                    echo '<tr><td>'.esc_html((string)($q['label'] ?? $q['key'] ?? '')).'</td><td>'.esc_html($source).'</td><td>'.($actual?'#'.$actual:'N/D').'</td><td>'.esc_html($message).'</td></tr>';
                }
                echo '</tbody></table></div>';
            }
        }
        echo '<p class="description">A pré-visualização de Briefing depende ainda dos filtros internos de cada bloco e da data da visita. Os stocks são sempre uma fotografia independente, não uma contagem de Frentes.</p></div>';
    }
}
