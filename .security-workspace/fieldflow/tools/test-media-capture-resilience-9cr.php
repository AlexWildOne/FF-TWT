<?php
// 9CR runtime test: photo questions open the gallery and the camera with the method that works on the device,
// switch method after a tap without result, fall back to the phone camera when the in-page camera fails and
// report each failing step to the authenticated client-event diagnostics.
// The JavaScript helpers of the real FormRenderer are executed with Node.js when it is available
// (otherwise the semantic markers are verified statically).
$root = dirname(__DIR__);
function ff9cr_fail(string $reason): void { fwrite(STDERR, "MEDIA CAPTURE RESILIENCE 9CR RUNTIME FAIL: {$reason}\n"); exit(1); }
function ff9cr_assert(bool $ok, string $reason): void { if (!$ok) ff9cr_fail($reason); }
$read = static function (string $relative) use ($root): string { $p = $root . '/' . $relative; return is_readable($p) ? (string)file_get_contents($p) : ''; };
$renderer = $read('src/Forms/FormRenderer.php');
$forms = $read('src/Forms/Forms.php');
$stability = $read('src/Mobile/StabilityController.php');
ff9cr_assert($renderer !== '' && $forms !== '' && $stability !== '', 'files');

$start = strpos($renderer, "<<<'JS'\n");
$end = $start === false ? false : strpos($renderer, "\nJS;", $start);
ff9cr_assert($start !== false && $end !== false, 'inline_js');
$js = substr($renderer, $start + 8, $end - $start - 8);
$slice = static function (string $from, string $to) use ($js): string {
    $a = strpos($js, $from); $b = $a === false ? false : strpos($js, $to, $a);
    ff9cr_assert($a !== false && $b !== false, 'slice ' . $from);
    return substr($js, $a, $b - $a);
};
$helpers = $slice('  var RP_MEDIA_RESILIENCE_CONTRACT=', '  function rpAssignCapturedImage(');
$camera = $slice('  function rpOpenCameraFlow($input,$zone){', '  function rpCheckpointExternalMediaHandoff(');
$watch = $slice('  (function rpMediaWatchOfflineSubmit(){', "  $('.routespro-dyn-form').each(");
$trigger = $slice("  $(document).on('click','[data-rp-upload-trigger]',function(e){", "  $(document).on('click','[data-rp-upload-add]',function(e){");

// Static semantics (always).
ff9cr_assert(strpos($helpers, "return rpMediaIsNativeApp()?'click':'picker';") !== false && strpos($helpers, "if(opener==='picker'&&typeof input.showPicker==='function'){") !== false, 'openers_static');
ff9cr_assert(strpos($trigger, 'var retapOpener=rpMediaCheckRetap(zoneNode,source,this);') !== false && strpos($trigger, 'rpCheckpointExternalMediaHandoff($(this),source);') !== false && strpos($trigger, "rpMediaReport('media_input_disabled'") !== false && strpos($trigger, 'rpMediaZoneEditable($input)') !== false, 'trigger_static');
ff9cr_assert(strpos($helpers, "document.addEventListener('pointerup',rpMediaReviveTrigger,true);") !== false && strpos($helpers, "not('[data-rp-evidence-disabled]')") !== false && strpos($helpers, "hasClass('is-daily-locked')") !== false, 'disabled_guard_static');
ff9cr_assert(strpos($camera, "rpMediaSet('camera','native');") !== false && strpos($camera, "rpIsNativeIOS()||rpMediaGet('camera')==='native'") !== false && strpos($camera, 'A abrir a camara do dispositivo...') !== false, 'camera_static');
ff9cr_assert(strpos($forms, "data-rp-media-diag-endpoint=\"<?php echo esc_url(rest_url('fieldflow-mobile/v1/diagnostics/client-event')); ?>\"") !== false && strpos($stability, "'media_error'") !== false && strpos($stability, "\$context['detail'] = substr(sanitize_textarea_field(\$body['detail']), 0, 600);") !== false && strpos($stability, "'permission_callback' => [\$this, 'authenticated']") !== false, 'diagnostics_static');
ff9cr_assert(strpos($renderer, "if(input.dataset.rpOptimisedPass!=='1') rpMediaNoteChange(input,$(input).closest('[data-rp-upload-zone]'));") !== false && strpos($helpers, 'input.__rpMediaNoted===signature') !== false, 'draft_capture_note');
ff9cr_assert(strpos($watch, 'throw error;') !== false && strpos($watch, 'offline.submitForm=watched;') !== false, 'watch_static');

// Behaviour with Node.js.
$node = trim((string)@shell_exec('command -v node 2>/dev/null'));
$nodeRan = 0;
if ($node !== '') {
    $dir = sys_get_temp_dir() . '/ff9cr-' . getmypid();
    @mkdir($dir);
    file_put_contents($dir . '/helpers.js', $helpers . "\n" . $camera);
    file_put_contents($dir . '/watch.js', $watch);
    $harness = <<<'NODE'
const fs = require('fs'), vm = require('vm'), dir = process.argv[2];
const fail = (r) => { console.log('FAIL ' + r); process.exit(1); };
const ok = (c, r) => { if (!c) fail(r); };
const storage = {}, timers = [], fetches = [], reports = [];
function W(node) {
  return { node, length: node ? 1 : 0,
    closest(sel) { if (!node) return W(null); if (sel === '.routespro-dyn-form') return W(node.form || null); if (sel === '[data-rp-question-wrap]') return W(node.wrap || null); return W(null); },
    attr(n) { return node && node.attrs ? node.attrs[n] : undefined; },
    find(sel) { if (!node) return W(null); if (node.fields && sel in node.fields) return W({ value: node.fields[sel] }); if (node.children && node.children[sel]) return W(node.children[sel]); return W(null); },
    first() { return this; }, last() { return this; }, val() { return node ? node.value : undefined; }, get() { return node; },
    prop(n) { return node ? node[n] : undefined; }, hasClass(c) { return !!(node && node.classes && node.classes.indexOf(c) >= 0); } };
}
const $ = (x) => (x && x.node !== undefined && typeof x.closest === 'function') ? x : W(x);
const ctx = { console, Date, Math, JSON, String, Number, Array, Object, Promise, RegExp, parseInt, isFinite,
  $, navigator: { userAgent: 'Mozilla/5.0 (Linux; Android 14; Pixel 7; wv) AppleWebKit/537.36 Chrome/129.0 Mobile Safari/537.36', onLine: true },
  document: { visibilityState: 'visible', addEventListener() {} } };
ctx.window = { sessionStorage: { getItem: (k) => storage[k] || null, setItem: (k, v) => { storage[k] = String(v); }, removeItem: (k) => { delete storage[k]; } },
  addEventListener() {}, setTimeout: (f, ms) => { timers.push(f); return 1; }, location: { href: 'https://report.test/fieldflow-mobile/portal/operational/?x=1#a', origin: 'https://report.test' },
  fetch: (url, opts) => { fetches.push({ url, opts }); return Promise.resolve({}); }, FieldFlowMobile: null };
ctx.window.parent = ctx.window;
ctx.window.postMessage = () => {};
vm.createContext(ctx);
vm.runInContext(`
  function rpNativeMobilePlatform(){return String((window.FieldFlowMobile&&window.FieldFlowMobile.platform)||'android');}
  function rpIsNativeIOS(){return !!(window.FieldFlowMobile&&window.FieldFlowMobile.native)&&rpNativeMobilePlatform()==='ios';}
  function rpCameraErrorCode(error){return String((error&&error.name)||'')+' '+String((error&&error.message)||error||'');}
  function rpUploadableFiles(files){return Array.prototype.slice.call(files||[]).filter(function(f){return Number(f.size||0)>0;});}
  var statuses=[];function rpUploadStatus($zone,message,type){statuses.push([message,type]);}
  function rpConfigureUploadSource(){}
  var embeddedCalls=0,embeddedError=null;function rpOpenEmbeddedCamera(){embeddedCalls++;return embeddedError?Promise.reject(embeddedError):Promise.resolve(true);}
`, ctx);
vm.runInContext(fs.readFileSync(dir + '/helpers.js', 'utf8'), ctx);
const R = (code) => vm.runInContext(code, ctx);
const input = (opts) => Object.assign({ clicks: 0, pickers: 0, value: 'x', click() { this.clicks++; }, showPicker() { this.pickers++; if (this.pickerThrows) throw Object.assign(new Error('blocked'), { name: 'NotAllowedError' }); } }, opts || {});
(async () => {
  // Openers: app WebView uses click(), browsers showPicker(); a remembered opener wins.
  ok(R('rpMediaOpener()') === 'picker', 'browser opener');
  ctx.window.FieldFlowMobile = { native: true, platform: 'android' };
  ok(R('rpMediaOpener()') === 'click', 'app opener');
  let a = input(); ctx.tmp = W(a);
  ok(R('rpOpenNativePicker(tmp)') === 'click' && a.clicks === 1 && a.pickers === 0, 'app click');
  ctx.window.FieldFlowMobile = null;
  a = input(); ctx.tmp = W(a);
  ok(R('rpOpenNativePicker(tmp)') === 'picker' && a.pickers === 1 && a.clicks === 0, 'browser picker');
  a = input({ pickerThrows: true }); ctx.tmp = W(a);
  ok(R('rpOpenNativePicker(tmp)') === 'click' && a.clicks === 1, 'picker fallback click');
  ctx.window.FieldFlowMobile = { native: true, platform: 'ios' };
  a = input(); ctx.tmp = W(a);
  ok(R('rpOpenNativePicker(tmp)') === 'click' && a.value === '' && a.pickers === 0, 'ios click');
  ctx.window.FieldFlowMobile = { native: true, platform: 'android' };
  // Diagnostics through the authenticated client-event endpoint.
  const form = { attrs: { 'data-rp-media-diag-endpoint': 'https://report.test/wp-json/fieldflow-mobile/v1/diagnostics/client-event', 'data-rp-media-diag-nonce': 'abc', 'data-routespro-form-id': '9' },
    fields: { '[name="routespro_route_id"]': '12', '[name="routespro_stop_id"],[name="routespro_route_stop_id"]': '34', '[name="routespro_location_id"]': '56', '[name="routespro_client_id"]': '3', '[name="routespro_project_id"]': '4' } };
  const wrap = { attrs: { 'data-rp-question-wrap': 'destaque_pr__evidence_photo' } };
  const origin = { form, wrap };
  ctx.origin = origin;
  ok(R("rpMediaReport('media_test','Teste',origin)") === true && fetches.length === 1, 'report sent');
  const sent = JSON.parse(fetches[0].opts.body);
  ok(fetches[0].url.indexOf('/diagnostics/client-event') > 0 && fetches[0].opts.headers['X-WP-Nonce'] === 'abc' && fetches[0].opts.credentials === 'same-origin', 'report transport');
  ok(typeof sent.detail === 'string' && sent.detail.length > 0 && sent.message.length <= 250, 'report detail');
  ok(sent.event_type === 'media_error' && sent.platform === 'android' && sent.question_key === 'destaque_pr__evidence_photo' && sent.route_id === 12 && sent.stop_id === 34 && sent.form_id === 9 && sent.webview_url.indexOf('#') < 0 && /Android 14/.test(sent.message) && /WebView/.test(sent.message), 'report payload');
  // Retap without any sign of the picker switches the opener; cancel (left) and delivered files do not.
  R("rpMediaReport=function(code,summary){reportsSeen.push(code);return true;};var reportsSeen=[];");
  ctx.Z = { id: 'zone' };
  R("rpMediaPending={zone:Z,source:'gallery',method:'click',at:Date.now()-3000,left:'',done:false}");
  ok(R("rpMediaCheckRetap(Z,'gallery',origin)") === 'picker' && R("rpMediaGet('opener')") === 'picker' && R('reportsSeen[0]') === 'media_picker_no_signal', 'retap switch');
  ok(R('rpMediaOpener()') === 'picker', 'retap remembered');
  R("rpMediaPending={zone:Z,source:'gallery',method:'picker',at:Date.now()-3000,left:'hidden',done:false}");
  ok(R("rpMediaCheckRetap(Z,'gallery',origin)") === '' && R("rpMediaGet('opener')") === 'picker', 'cancel kept');
  R("rpMediaPending={zone:Z,source:'gallery',method:'picker',at:Date.now()-3000,left:'',done:true}");
  ok(R("rpMediaCheckRetap(Z,'gallery',origin)") === '', 'delivered kept');
  R("rpMediaPending={zone:Z,source:'camera',method:'embedded',at:Date.now(),left:'',done:false}");
  ok(R("rpMediaCheckRetap(Z,'camera',origin)") === '', 'embedded not counted');
  R("rpMediaSet('opener','')");
  R("rpMediaReport=function(code){reportsSeen.push(code);return true;};");
  ctx.NZ = W({ id: 'nz', children: {} }); ctx.ninput = { files: [{ name: 'a.jpg', size: 0, lastModified: 1, type: 'image/jpeg' }] };
  R("rpMediaPending={zone:NZ.get(0),source:'gallery',method:'click',at:Date.now(),left:'',done:false}");
  const before0 = R('reportsSeen.length'); R('rpMediaNoteChange(ninput,NZ)'); R('rpMediaNoteChange(ninput,NZ)');
  ok(R('rpMediaPending.done') === true && R('reportsSeen.length') === before0 + 1 && R('reportsSeen[reportsSeen.length-1]') === 'media_file_empty', 'note once');
  R('rpMediaPending=null');
  // Camera: refused in-page camera opens the phone camera and the session keeps it.
  const camInput = input(); ctx.ci = W(camInput); ctx.cz = W({ attrs: {}, children: {} });
  R("embeddedError=Object.assign(new Error('denied'),{name:'NotAllowedError'})");
  ok((await R('rpOpenCameraFlow(ci,cz)')) === false, 'camera flow result');
  ok(R("rpMediaGet('camera')") === 'native' && camInput.clicks === 1 && R('reportsSeen.indexOf("media_camera_unavailable")>=0'), 'camera fallback');
  const before = R('embeddedCalls'); const camInput2 = input(); ctx.ci2 = W(camInput2);
  const res = R("rpRequestNativeMedia('camera',ci2,cz)");
  ok(res === 'native-click' && R('embeddedCalls') === before && camInput2.clicks === 1, 'camera native session');
  R("rpMediaSet('camera','')"); R("embeddedError=Object.assign(new Error('none'),{name:'NotFoundError'})");
  ctx.ci3 = W(input());
  await R('rpOpenCameraFlow(ci3,cz)');
  ok(R("rpMediaGet('camera')") === '', 'no camera keeps message');
  // Gallery request returns the opener used.
  const g = input(); ctx.gi = W(g);
  ok(R("rpRequestNativeMedia('gallery',gi,cz)") === 'click' && g.clicks === 1, 'gallery request');
  // Submit watch: a failing submission with photos is reported and still rejected; success passes through.
  vm.runInContext("rpMediaReport=function(code){reportsSeen.push(code);return true;};", ctx);
  let calls = 0; ctx.window.FieldFlowOffline = { submitForm(formNode, meta) { calls++; return meta && meta.fail ? Promise.reject(new Error('media_start_failed')) : Promise.resolve({ ok: true }); } };
  vm.runInContext(fs.readFileSync(dir + '/watch.js', 'utf8'), ctx);
  const photoForm = { querySelectorAll: () => [{ disabled: false, files: [{ size: 10 }] }] };
  ok(ctx.window.FieldFlowOffline.submitForm.__rpMediaWatch === 1, 'watch installed');
  const okRes = await ctx.window.FieldFlowOffline.submitForm(photoForm, {});
  let rejected = false; try { await ctx.window.FieldFlowOffline.submitForm(photoForm, { fail: true }); } catch (e) { rejected = e.message === 'media_start_failed'; }
  ok(okRes.ok === true && rejected && calls === 2 && R('reportsSeen.indexOf("media_submit_failed")>=0'), 'submit watch');
  console.log('NODE OK');
})().catch((e) => fail(String(e && e.stack || e)));
NODE;
    file_put_contents($dir . '/harness.js', $harness);
    $out = (string)shell_exec(escapeshellarg($node) . ' ' . escapeshellarg($dir . '/harness.js') . ' ' . escapeshellarg($dir) . ' 2>&1');
    foreach (['helpers.js', 'watch.js', 'harness.js'] as $file) @unlink($dir . '/' . $file);
    @rmdir($dir);
    ff9cr_assert(strpos($out, 'NODE OK') !== false, 'node ' . trim($out));
    $nodeRan = 1;
}

$historical = strpos($renderer, 'function rpVisionLiveStart(){') !== false && strpos($renderer, "fd.append('capture',capture||'camera')") !== false && strpos($renderer, 'function rpFrontsDecorateDynamicRow($row,$box){') !== false
    && strpos($read('src/Forms/SubmissionPipeline.php'), "public const BUILD = '2.2.485'") !== false && stripos($renderer, 'CREATE TABLE') === false;
ff9cr_assert($historical, 'historical_continuity');

echo 'MEDIA CAPTURE RESILIENCE 9CR RUNTIME OK | openers=1 | retap=1 | camera_fallback=1 | disabled_guard=1 | diagnostics=1 | submit_watch=1 | historical_continuity=1 | node=' . $nodeRan . "\n";
