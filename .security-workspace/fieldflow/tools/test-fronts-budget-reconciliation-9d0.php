<?php
/** PHP isolated regression: historical 5 EUR, reviewed correction, one-charge settlement and quota locks. */
define('ABSPATH','/tmp');
$GLOBALS['ff624_opts'] = [];
$GLOBALS['ff624_day'] = '2026-10-10';
function current_time($kind) { return $kind==='mysql'? $GLOBALS['ff624_day'].' 16:00:00':$GLOBALS['ff624_day']; }
function get_option($k,$default=[]) { return $GLOBALS['ff624_opts'][$k]??$default; }
function update_option($k,$v,$autoload=false) { $GLOBALS['ff624_opts'][$k]=$v; return true; }
function sanitize_text_field($s) { return strip_tags(trim((string)$s)); }
function current_user_can($cap) {return !empty($GLOBALS['ff624_manager']);}
function get_current_user_id() {return 9;}
$GLOBALS['ff624_manager']=true;
function ff624_assert($ok,$case) { if (!$ok) {fwrite(STDERR,"9D0 FAIL: $case\n");exit(1);} }
class FF9D0DB {
    public $queryFailed=false; public $prefix='wp_';
    public function prepare($sql,...$args) { return [$sql,$args]; }
    public function get_var($prepared) {
        if ($this->queryFailed) return 0;
        if (str_contains($prepared[0],'GET_LOCK')) return 1;
        if (str_contains($prepared[0],'RELEASE_LOCK')) return 1;
        return null;
    }
    public function query($sql) {return 1;}
}
$GLOBALS['wpdb']=new FF9D0DB();
class FF9D0VisionStub { public static function clientSettings(int $clientId): array { return $GLOBALS['ff624_settings']; } }
class_alias(FF9D0VisionStub::class, 'RoutesPro\\Vision\\FrontsVisionService');
require dirname(__DIR__).'/src/Vision/VisionUsageGuard.php';
use RoutesPro\Vision\VisionUsageGuard as G;
ff624_assert(G::RECONCILIATION_CONTRACT==='fieldflow-fronts-budget-reconciliation-9d0-v1','contract');
$key='fieldflow_vision_usage_17_202610';
$settings=['monthly_budget_cents'=>500,'reservation_cents'=>100,'monthly_limit'=>500,'daily_limit'=>45];
$GLOBALS['ff624_settings']=$settings;
$GLOBALS['ff624_opts'][$key]=['day'=>'2026-10-10','monthly_requests'=>5,'daily_requests'=>5,'reserved_cents'=>500];
try { G::check(17,$settings);ff624_assert(false,'historic_budget_block'); }
catch(RuntimeException $e) {ff624_assert(stripos($e->getMessage(),'reservado')!==false,'historic_budget_message');}
ff624_assert(G::summary(17,$settings)['legacy_total_cents']===500,'read_legacy_exact');
try { G::reconcileLegacy(17,600,'Azure Cost Management 2026-10',9);ff624_assert(false,'refuse_reconcile_more_than_legacy'); }
catch(InvalidArgumentException $e){}
 $GLOBALS['ff624_manager']=false;
try { G::reconcileLegacy(17,5,'Azure Cost Management 2026-10, comprovado',9);ff624_assert(false,'permission_guard'); }
catch(RuntimeException $e) {}
$GLOBALS['ff624_manager']=true;
$after=G::reconcileLegacy(17,5,'Azure Cost Management 2026-10, comprovado',9);
ff624_assert($after['reserved_cents']===5 && $after['monthly_requests']===5 && $after['legacy_reviewed_cents']===5,'historic_reconciled_without_restarting_count');
ff624_assert($GLOBALS['ff624_opts'][$key]['legacy_reconciled_by']===9 && $GLOBALS['ff624_opts'][$key]['legacy_released_cents']===495,'audited_manager');
try { G::reconcileLegacy(17,0,'Segunda tentativa Azure',9);ff624_assert(false,'historic_review_only_once'); }
catch(RuntimeException $e) {}
G::check(17,$settings);
$r1=G::reserve(17,$settings);
ff624_assert($r1['monthly_requests']===6 && $r1['reserved_cents']===105 && strlen($r1['reservation_id'])===32,'atomic_pre_call_reserve');
$receipt=['status'=>'priced','pricing_source'=>'admin_configured','token_source'=>'provider','input_tokens'=>10000,'output_tokens'=>2000,'estimated_cost_eur'=>.00216];
$s1=G::settle(17,$r1['reservation_id'],$receipt,$settings);
ff624_assert($s1['reserved_cents']===6 && $s1['released_cents']===99 && $s1['priced_settlements']===1 && $s1['pending_count']===0,'priced_refund_99_cents');
$twice=G::settle(17,$r1['reservation_id'],$receipt,$settings);
ff624_assert($twice['reserved_cents']===6 && $twice['released_cents']===99,'idempotent_settlement');
$r2=G::reserve(17,$settings);
$notPriced=['status'=>'tariff_missing','token_source'=>'provider','input_tokens'=>10000,'output_tokens'=>10,'estimated_cost_eur'=>null];
$noRelease=G::settle(17,$r2['reservation_id'],$notPriced,$settings);
ff624_assert($noRelease['reserved_cents']===106 && $noRelease['priced_settlements']===1,'no_tariff_fail_closed');
$r3=G::reserve(17,$settings);
$spurious=G::settle(17,$r3['reservation_id'],['status'=>'priced','pricing_source'=>'admin_configured','token_source'=>'unavailable','input_tokens'=>1,'estimated_cost_eur'=>.0001],$settings);
ff624_assert($spurious['reserved_cents']===206,'unmetered_not_free');
$r4=G::reserve(17,$settings);
$higher=G::settle(17,$r4['reservation_id'],['status'=>'priced','pricing_source'=>'admin_configured','token_source'=>'provider','input_tokens'=>999,'output_tokens'=>2,'estimated_cost_eur'=>1.2],$settings);
ff624_assert($higher['reserved_cents']===362,'accurate_overbudget_charge_156_cents');
$other=G::summary(99,$settings);
ff624_assert($other['reserved_cents']===0 && $other['monthly_requests']===0,'per_client_isolation');
$GLOBALS['wpdb']->queryFailed=true;
try {G::reserve(17,$settings);ff624_assert(false,'lock_fail_closed');}catch(RuntimeException $e){}
ff624_assert(G::summary(17,$settings)['monthly_requests']===9,'no_counter_increment_when_locked');
$GLOBALS['wpdb']->queryFailed=false;
$GLOBALS['ff624_day']='2026-10-11';
ff624_assert(G::summary(17,$settings)['daily_requests']===0,'new_day_reset');
$day=G::reserve(17,$settings);
ff624_assert($day['daily_requests']===1 && $day['monthly_requests']===10,'new_day_increment');
// A failed Azure request (no settle) stays charged in pending for safety.
ff624_assert(G::summary(17,$settings)['pending_count']===1,'failed_request_keeps_reserve');
echo 'FRONTS BUDGET RECONCILIATION 9D0 PHP OK | historic_review_only_once | idempotent_settlement | fail_closed | atomic_limits | client_scope | no_auto_reset' . PHP_EOL;
