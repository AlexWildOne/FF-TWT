<?php
/** Reproducible 9DO mail resilience tests with a synthetic WordPress/Outbox. */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS', 60);
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);
if (!defined('WP_DEBUG')) define('WP_DEBUG', false);
$memoryOptions = [];
$mailCalls = 0;
function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$v)); }
function sanitize_email($v) { return strtolower(trim((string)$v)); }
function sanitize_text_field($v) { return trim((string)$v); }
function absint($v) { return abs((int)$v); }
function get_option($k, $default=null) { global $memoryOptions; return $memoryOptions[$k] ?? $default; }
function update_option($k, $v, $autoload=false) { global $memoryOptions; $memoryOptions[$k]=$v; return true; }
function current_time($v, $utc=false) { return gmdate('Y-m-d H:i:s'); }
function apply_filters($k, $v, ...$rest) { return $v; }
function wp_json_encode($v) { return json_encode($v); }
function wp_generate_uuid4() { static $n=1; return sprintf('00000000-0000-4000-8000-%012d', $n++); }
function add_action(...$a) {}
function remove_action(...$a) {}
function do_action(...$a) {}
function wp_mail($to, $subject, $body, $headers=[], $attachments=[]) { global $mailCalls; $mailCalls++; return true; }
function wp_next_scheduled($hook, $args=[]) { return false; }
function wp_schedule_single_event($when, $hook, $args=[]) { return true; }
class SyntheticWpdb {
    public string $prefix='wp_';
    public int $insert_id=0;
    public bool $tableExists=false;
    public bool $failUpdates=false;
    public array $rows=[];
    public string $lastQuery='';
    public function prepare($sql, ...$args) {
        $i=0;
        return preg_replace_callback('/%[sd]/', static function($m) use (&$i,$args) {
            $v=$args[$i++] ?? '';
            return $m[0]==='%d' ? (string)(int)$v : "'" . str_replace("'", "''", (string)$v) . "'";
        }, $sql);
    }
    public function get_var($sql) {
        if (strpos($sql, 'SHOW TABLES LIKE')!==false) return $this->tableExists ? $this->prefix . 'routespro_mail_outbox' : null;
        return null;
    }
    public function get_row($sql, $format=null) {
        if (preg_match('/WHERE id=(\d+)/', $sql, $m)) return $this->rows[(int)$m[1]] ?? null;
        if (preg_match("/WHERE idempotency_key='([^']+)'/",$sql,$m)) {
            foreach ($this->rows as $row) if (($row['idempotency_key']??'')===$m[1]) return $row;
        }
        return null;
    }
    public function insert($table, $data) {
        $key=(string)($data['idempotency_key']??'');
        if ($key!=='') foreach ($this->rows as $row) if (($row['idempotency_key']??'')===$key) return false;
        $id=++$this->insert_id;
        $this->rows[$id]=['id'=>$id]+$data;
        return 1;
    }
    public function update($table, $data, $where) {
        if ($this->failUpdates) return false;
        $id=(int)($where['id']??0);
        if (!$id || !isset($this->rows[$id])) return false;
        $this->rows[$id]=array_merge($this->rows[$id],$data);
        return 1;
    }
    public function query($sql) {
        $this->lastQuery=$sql;
        if (strpos($sql, "SET status='uncertain'")!==false) {
            $affected=0;
            foreach ($this->rows as &$row) {
                if (($row['status']??'')==='sending' && !empty($row['locked_at']) && strtotime($row['locked_at'].' UTC') < time()-600) {
                    $row['status']='uncertain'; $row['locked_at']=null;$row['locked_by']=null;$row['next_attempt_at']=null;
                    $affected++;
                }
            }
            unset($row);
            return $affected;
        }
        return 0;
    }
    public function get_col($sql) { return []; }
    public function get_results($sql, $format=null) { return []; }
}
$wpdb = new SyntheticWpdb();
require_once dirname(__DIR__) . '/src/Services/MailOutboxRepository.php';
require_once dirname(__DIR__) . '/src/Services/MailDispatchService.php';
require_once dirname(__DIR__) . '/src/Admin/ReportAutomations.php';
use RoutesPro\Services\MailDispatchService as Dispatch;
use RoutesPro\Services\MailOutboxRepository as Repo;
use RoutesPro\Admin\ReportAutomations as Reports;

$checks=0;
$assert=function($condition,$label) use (&$checks) {
    if (!$condition) { fwrite(STDERR, '9DO FAIL: ' . $label . "\n"); exit(1); }
    $checks++;
};
$makeIdentity=(new ReflectionClass(Reports::class))->getMethod('recipientDispatchIdentity');
$a=$makeIdentity->invoke(null,41,'TEAM@EXAMPLE.test','daily_close','2026-10-14','client_merchandiser_day',9,100);
$b=$makeIdentity->invoke(null,41,'team@example.test','daily_close','2026-10-14','client_merchandiser_day',9,101);
$again=$makeIdentity->invoke(null,41,'team@example.test','daily_close','2026-10-14','client_merchandiser_day',9,100);
$assert($a['key']!==$b['key'],'different routes produce different idempotency keys');
$assert($a['key']===$again['key'],'manual and automatic route identity stable');
$assert($a['legacy']===$b['legacy'],'legacy key remains detectable');
$assert(strpos($a['key'],'|route:100')!==false,'route included explicitly');

$base=['source'=>'scheduled_report','email_type'=>'daily_close','route_id'=>100,'reliable_required'=>1,'idempotency_key'=>$a['key'],'legacy_idempotency_key'=>$a['legacy']];
$r=Dispatch::dispatch('team@example.test','Daily Close','Payload',[],[], $base);
$assert(empty($r['accepted']) && ($r['status']??'')==='blocked','fail closed if Outbox unavailable');
$assert($mailCalls===0,'fail closed without transport');
$r=Dispatch::dispatch('ordinary@example.test','Ordinary','Payload');
$assert(!empty($r['accepted']) && $mailCalls===1,'unrelated notifications preserve direct fallback');
$wpdb->tableExists=true;
$r=Dispatch::dispatch('team@example.test','Daily Close','Payload',[],[],$base);
$assert(!empty($r['accepted']) && ($r['status']??'')==='sent' && $mailCalls===2,'first reliable route is accepted and persisted');
$assert(($wpdb->rows[$r['outbox_id']]['status']??'')==='sent','outbox persists sent status');
$replay=Dispatch::dispatch('team@example.test','Daily Close','Payload',[],[],$base+['manual_dispatch'=>true]);
$assert(!empty($replay['deduplicated']) && $mailCalls===2,'manual/automatic overlap does not call transport again');

$different=$base;
$different['route_id']=101; $different['idempotency_key']=$b['key'];
$r2=Dispatch::dispatch('team@example.test','Daily Close','Payload',[],[], $different);
$assert(!empty($r2['accepted']) && $mailCalls===3,'second route is not suppressed');
$assert(count($wpdb->rows)===2,'one outbox row per route');

// A pre-9DO row with the old key is a duplicate only for the same route.
$old=$base; $old['idempotency_key']=$a['legacy']; unset($old['legacy_idempotency_key']); $old['route_id']=102;
$legacy=Dispatch::dispatch('team@example.test','Legacy','Payload',[],[],$old);
$assert(!empty($legacy['accepted']) && $mailCalls===4,'pre-9DO key row exists');
$new102=$base;
$new102['route_id']=102; $new102['idempotency_key']=$a['key'].'-102';
$dedup102=Dispatch::dispatch('team@example.test','Legacy','Payload',[],[],$new102);
$assert(!empty($dedup102['deduplicated']) && $mailCalls===4,'legacy row with exact matching route blocks duplicate');
$new103=$new102; $new103['route_id']=103; $new103['idempotency_key']=$a['key'].'-103';
$otherRoute=Dispatch::dispatch('team@example.test','New route','Payload',[],[],$new103);
$assert(!empty($otherRoute['accepted']) && $mailCalls===5,'legacy row from another route does not suppress delivery');

$wpdb->failUpdates=true;
$uncertain=Dispatch::dispatch('team@example.test','SMTP result uncertain','Payload',[],[],array_merge($base,['idempotency_key'=>'unique-uncertain','legacy_idempotency_key'=>'']));
$assert(($uncertain['status']??'')==='uncertain' && empty($uncertain['accepted']),'accepted transport without DB confirmation becomes uncertain');
$wpdb->failUpdates=false;
$uncertainId=(int)$uncertain['outbox_id'];
$assert(($wpdb->rows[$uncertainId]['status']??'')==='sending','original row stays locked until diagnostic recovery');
$wpdb->rows[$uncertainId]['locked_at']=gmdate('Y-m-d H:i:s',time()-3600);
$recovered=Repo::recoverStaleLocks(600);
$assert($recovered===1 && ($wpdb->rows[$uncertainId]['status']??'')==='uncertain','stale sending moves to uncertain without automatic replay');
$assert(!Dispatch::requeue($uncertainId),'uncertain delivery cannot be requeued without manual verification');
$wpdb->rows[$uncertainId]['meta_json'] = json_encode(['attachment_names'=>['report.pdf']]);
$assert(!Dispatch::requeue($uncertainId,true),'cannot requeue report lacking original persisted attachments');
$wpdb->rows[$uncertainId]['meta_json'] = json_encode(['attachment_names'=>[]]);
$assert(Dispatch::requeue($uncertainId,true),'explicit verification can requeue uncertain row with no missing attachments');

$src=file_get_contents(dirname(__DIR__).'/src/Admin/ReportAutomations.php');
$assert(strpos($src, "new \\WP_Error(\$accepted ? 'daily_close_partial'")!==false,'partial route returns an error for targeted retry');
$assert(strpos($src, "self::recordDailyCloseDiagnostic(\$result);")!==false,'route dispatch records diagnostics');
$assert(strpos($src, "['client','extra']")!==false,'manual recovery includes Mail Center extra recipients');
echo "DAILY CLOSE RECIPIENT RELIABILITY 9DO PASS | checks={$checks} | reliable_outbox=fail_closed | identity=route_scoped | manual=deduplicated | stale=uncertain\n";
