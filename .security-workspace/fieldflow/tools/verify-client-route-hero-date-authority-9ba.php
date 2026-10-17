<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string { $p=$root.'/'.$relative; return is_readable($p)?(string)file_get_contents($p):''; };
$failed=[];
$assert=static function(bool $ok,string $name) use (&$failed): void { if(!$ok)$failed[]=$name; };
$front=$read('src/Front/ClientPortalShortcodeRenderer.php');
$phase=$read('src/Cloud/Phase6StableRelease.php');
$gate=$read('src/Support/ReleaseGate.php');
$validator=$read('src/Support/ReleasePackageValidator.php');
$manager=$read('src/Admin/ReleaseManager.php');
$doc=$read('PHASE-9BA-CLIENT-ROUTE-HERO-DATE-AUTHORITY.md');
foreach([
    'function agendaRange(){ return selectedFilterRange(); }',
    'const routes=routesInSelectedRange(state.routes);',
    'Calendário validado exclusivamente por <strong>De → Até</strong>',
    "p.set('date_from',range.from)",
    "p.set('date_to',range.to)",
    'routeMobilityTotal(r)'
] as $m)$assert(strpos($front,$m)!==false,'front:'.$m);
foreach(['rpcp-route-month','rpcp-summary-month','data-cal-prev','data-cal-next','ownerRoutesMonth','routeCalendarMonth','Agenda anual'] as $m)$assert(strpos($front,$m)===false,'front:no_'.$m);
$assert(strpos($front,'els.from.disabled=!!date')===false && strpos($front,'els.to.disabled=!!date')===false,'front:date_inputs_free');
$assert(strpos($front,'if(date) els.from.value=date;')===false && strpos($front,'if(date) els.to.value=date;')===false,'front:no_date_rewrite');
$assert(strpos($phase,"SOURCE_RELEASE = '2.2.570'")!==false && strpos($phase,"STABLE_RELEASE = '2.2.571'")!==false,'phase6_successor');
$assert(strpos($gate,"CLIENT_ROUTE_HERO_DATE_AUTHORITY_9BA_CONTRACT = 'fieldflow-client-route-hero-date-authority-v1'")!==false,'gate_contract');
$assert(strpos($gate,'checkClientRouteHeroDateAuthority9BAContract')!==false,'gate_check');
$assert(strpos($validator,'client_route_hero_date_authority_9ba_ok')!==false,'validator_check');
$assert(strpos($manager,"packageResult['client_route_hero_date_authority_9ba_ok']")!==false,'manager_kpi');
$assert(strpos($doc,'fieldflow-client-route-hero-date-authority-v1')!==false,'doc_contract');
if($failed){fwrite(STDERR,'FAILED '.implode(',',$failed).PHP_EOL);exit(1);}echo "CLIENT ROUTE HERO DATE AUTHORITY 9BA CONTRACT OK\n";
