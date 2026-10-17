<?php
/** 9DT realistic SQL scope regression: a linked store does not imply valid route client/project. */
require_once __DIR__.'/test-form-cardex-identity-continuity-9dr.php';

final class ScopeDb9DT {
    public string $prefix='ff_';
    public array $locations=[];
    public array $cardex=[];
    public array $links=[];
    public function prepare($sql,...$args) { return vsprintf((string)$sql,$args); }
    public function get_row($sql,$out=null) {
        if (preg_match('/FROM ff_routespro_locations WHERE id=(\d+)/',$sql,$m)) return $this->locations[(int)$m[1]] ?? null;
        if (preg_match('/FROM ff_routespro_product_cardex WHERE id=(\d+)/',$sql,$m)) return $this->cardex[(int)$m[1]] ?? null;
        return null;
    }
    public function get_results($sql,$out=null) {
        if (str_contains($sql,'FROM ff_routespro_product_cardex_locations cl') && str_contains($sql,'JOIN')) {
            preg_match('/cl.location_id=(\d+)/',$sql,$m);
            $loc=(int)($m[1] ?? 0); $rows=[];
            preg_match('/c\.client_id=0 OR (\d+)=0 OR c\.client_id=(\d+)/',$sql,$c);
            preg_match('/c\.project_id=0 OR (\d+)=0 OR c\.project_id=(\d+)/',$sql,$p);
            foreach ($this->links as $link) {
                if ($link['location_id']!==$loc) continue;
                $cardex=$this->cardex[$link['cardex_id']] ?? null;
                if (!$cardex || $cardex['status']!=='active') continue;
                if ($c && (int)$cardex['client_id']!==0 && (int)$c[1]!==0 && (int)$cardex['client_id']!==(int)$c[2]) continue;
                if ($p && (int)$cardex['project_id']!==0 && (int)$p[1]!==0 && (int)$cardex['project_id']!==(int)$p[2]) continue;
                $rows[]=array_merge($cardex,['cardex_name'=>$cardex['name'],'link_id'=>$link['id']]);
            }
            usort($rows,fn($a,$b)=>((int)$b['link_id']<=>(int)$a['link_id']));
            return $rows;
        }
        if (str_contains($sql,'FROM ff_routespro_product_cardex') && str_contains($sql,'ORDER BY name')) return array_values($this->cardex);
        return [];
    }
    public function get_var($sql) {
        if (!str_contains($sql,'FROM ff_routespro_product_cardex_locations')) return null;
        preg_match('/cardex_id=(\d+)/',$sql,$c); preg_match('/location_id=(\d+)/',$sql,$l);
        foreach ($this->links as $link) {
            if ((int)$link['cardex_id']!==(int)($c[1]??0)) continue;
            if ($l && (int)$link['location_id']!==(int)$l[1]) continue;
            return 1;
        }
        return null;
    }
    public function get_col($sql) { return []; }
}
$wpdb=new ScopeDb9DT();
$wpdb->locations[314]=['id'=>314,'name'=>'Continente Modelo Santo Tirso',
    'tags'=>'','meta_json'=>'','client_id'=>10,'project_id'=>20];
$wpdb->cardex[60]=['id'=>60,'name'=>'Pernod Ricard - Sonae','insignia'=>'Sonae',
    'client_id'=>10,'project_id'=>20,'status'=>'active','meta_json'=>'{}'];
$wpdb->links[]=['cardex_id'=>60,'location_id'=>314,'id'=>1];
$n=0;
$assert9dt=function(bool $ok,string $why) use (&$n) {
    $n++; if (!$ok) throw new RuntimeException('9DT check '.$n.' failed: '.$why);
};
use RoutesPro\Forms\ProductCardex as Cardex;
$assert9dt(\RoutesPro\Stores\StoreIdentityAuthority::locationMatches('Sonae',$wpdb->locations[314]),'Umbrella Sonae matches a Modelo store');
$assert9dt(Cardex::cardex_is_linked_to_location(60,314),'Realistic store has explicit Cardex link');
$same=Cardex::inspect_location_binding(60,314,10,20);
$assert9dt($same['code']==='ok' && $same['resolved_id']===60,'Same client/project and verified retailer resolves cardex');
$otherProject=Cardex::inspect_location_binding(60,314,10,21);
$assert9dt($otherProject['code']==='project_mismatch' && $otherProject['resolved_id']===0,'Different route project is diagnosed rather than silently merged');
$wpdb->cardex[60]['project_id']=0;
$clientScope=Cardex::inspect_location_binding(60,314,10,21);
$assert9dt($clientScope['code']==='ok' && $clientScope['resolved_id']===60,'Explicit client-wide project scope restores matching linked store');
$otherClient=Cardex::inspect_location_binding(60,314,11,21);
$assert9dt($otherClient['code']==='client_mismatch' && $otherClient['resolved_id']===0,'Other client is still rejected');
$wpdb->cardex[60]['insignia']='Pingo Doce';
$otherBrand=Cardex::inspect_location_binding(60,314,10,21);
$assert9dt($otherBrand['code']==='retailer_mismatch' && $otherBrand['resolved_id']===0,'Explicit store link cannot override wrong retailer');
$wpdb->cardex[60]['insignia']='Sonae';
$wpdb->cardex[60]['status']='draft';
$draft=Cardex::inspect_location_binding(60,314,10,21);
$assert9dt($draft['code']==='draft' && $draft['resolved_id']===0,'Draft cannot resolve');
$admin=file_get_contents(dirname(__DIR__).'/src/Admin/ProductCardex.php');
$assert9dt(str_contains($admin,'commercial_scope_choices') && str_contains($admin,'ff-cardex-client') && str_contains($admin,'ff-cardex-project'),'Named client/project dropdowns present');
$assert9dt(str_contains($admin,'render_binding_diagnostics(') && str_contains($admin,'diagnose_location_id'),'Per-store live route diagnostic present');
$assert9dt(str_contains($admin,"if (!\$scope_only)") && str_contains($admin,"name=\"scope_only\""),'Dedicated context-only save leaves products/links unchanged');
$assert9dt(str_contains($admin,"empty(\$_POST['confirm_scope_change'])") && str_contains($admin,"\$owner !== \$data['client_id']"),'Explicit approval and tenant ownership checks');
echo "CARDEX SCOPE TRUTH 9DT PASS | checks={$n} | explicit_link_project_scope | Sonae_Modelo | client_isolation\n";
