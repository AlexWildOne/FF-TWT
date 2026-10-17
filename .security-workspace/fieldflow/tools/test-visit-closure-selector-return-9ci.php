<?php
// 9CI runtime structure test: every terminal branch of the focused Report returns
// to the store selector, the closed store is never re-selected and My Route paints
// before Google Maps. Static source analysis only; no WordPress bootstrap required.
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$fail = static function (string $reason): void { fwrite(STDERR, "VISIT CLOSURE SELECTOR RETURN 9CI RUNTIME FAIL: {$reason}\n"); exit(1); };
$assert = static function (bool $ok, string $reason) use ($fail): void { if (!$ok) $fail($reason); };
$slice = static function (string $source, string $start, string $end): string {
    $a = strpos($source, $start);
    if ($a === false) return '';
    $b = strpos($source, $end, $a + strlen($start));
    return $b === false ? '' : substr($source, $a, $b - $a);
};

$short = $read('src/Shortcodes.php');
$plugin = $read('fieldflow.php');
$assert($short !== '' && $plugin !== '', 'sources');

$report = $slice($short, 'private static function report_visit_app(', 'public static function performance_dashboard(');
$assert($report !== '', 'report_visit_app source');
$submit = $slice($report, 'async function submitForm(){', 'async function saveFailure(){');
$failure = $slice($report, 'async function saveFailure(){', "document.addEventListener('fieldflow:visit-completed'");
$loadVisits = $slice($report, 'async function loadVisits(date,preferred={}){', '// 9CI Visit Closure Selector Return');
$helper = $slice($report, 'async function returnToStoreSelector(closedContext,state){', 'async function refreshVisitsAfterClosure(date){');
$assert($submit !== '' && $failure !== '' && $loadVisits !== '' && $helper !== '', 'function slices');

// The confirmation remains the gate before any closure.
$confirm = strpos($submit, 'Queres mesmo concluir esta visita?');
$firstReturn = strpos($submit, 'returnToStoreSelector(');
$assert($confirm !== false && $firstReturn !== false && $confirm < $firstReturn, 'confirm_before_return');

// Every terminal branch returns to the selector; no branch reloads the closed store.
$closedStoreNotReselected = strpos($report, 'await loadVisits(completedContext.date,{stop_id:completedContext.stop_id,force:true})') === false
    && strpos($report, 'loadVisits(currentDate,{stop_id:parseInt(els.stop.value||0,10),force:true})') === false
    && strpos($loadVisits, "if(preferred.selection==='none')") !== false
    && strpos($loadVisits, "els.store.value=match?String(match.stop_id):'';") !== false;
$assert($closedStoreNotReselected, 'closed_store_not_reselected');
$assert(substr_count($submit, 'returnToStoreSelector(') >= 4, 'submit_terminal_branches');
$assert(strpos($submit, "await returnToStoreSelector(completedContext,editingExisting?'updated':'done');") !== false, 'online_return');
$assert(strpos($submit, "await returnToStoreSelector(fallbackClosedContext,'done');") !== false, 'fallback_return');
$offlineReturn = substr_count($submit, "await returnToStoreSelector(closureContextFromSelection(),'offline');") === 2;
$assert($offlineReturn, 'offline_return');
$failureReturn = strpos($failure, "await returnToStoreSelector(failureClosedContext,'failed_offline');") !== false
    && strpos($failure, "await returnToStoreSelector(failureClosedContext,'failed');") !== false;
$assert($failureReturn, 'failure_return');

// The selector return clears the closed context before swapping the form.
$clearPos = strpos($helper, 'clearStoredReportContext();');
$slotPos = strpos($helper, "els.slot.innerHTML='';");
$valuePos = strpos($helper, "els.store.value='';");
$renderPos = strpos($helper, "await renderForm({force:true,source:'visit-closure-return'});");
$storedContextCleared = $clearPos !== false && $slotPos !== false && $valuePos !== false && $renderPos !== false && $clearPos < $renderPos && $slotPos < $renderPos && $valuePos < $renderPos;
$assert($storedContextCleared, 'stored_context_cleared');

// Exactly one scroll after the terminal swap, no continuous guard.
$scroll = $slice($report, 'const scrollToStoreSelector=()=>{', 'async function returnToStoreSelector(');
$selectorScrollOnce = $scroll !== ''
    && substr_count($helper, 'scrollToStoreSelector') === 1
    && strpos($helper, 'window.requestAnimationFrame(()=>window.requestAnimationFrame(scrollToStoreSelector));') !== false
    && strpos($scroll, 'setInterval') === false
    && strpos($scroll, 'prefers-reduced-motion') !== false
    && strpos($report, 'reportViewportGuardRaf=window.requestAnimationFrame') === false;
$assert($selectorScrollOnce, 'selector_scroll_once');

// Closure card with the next pending store; never auto-selected.
$nextStoreCard = strpos($report, 'id="rpf-closure"') !== false
    && strpos($report, 'const nextPendingVisit=excludeStopId=>') !== false
    && strpos($report, '<small>Próxima loja</small>') !== false
    && strpos($helper, 'els.store.value=String') === false
    && strpos($report, "els.store.dispatchEvent(new Event('change',{bubbles:true}));") !== false;
$assert($nextStoreCard, 'next_store_card');

// The app shell resume checkpoint is cleared and idle closure actions stay hidden.
$app = $read('src/Front/AppShortcodeRenderer.php');
$resumeCheckpointCleared = strpos($helper, "document.dispatchEvent(new CustomEvent('fieldflow:report-selection-changed',{detail:{date:currentDate,source:'visit-closure-return'") !== false
    && strpos($app, "reason:'manual-report-selection-cleared'") !== false;
$assert($resumeCheckpointCleared, 'resume_checkpoint_cleared');
$idleActionsHidden = strpos($report, '.rp-report-focus.is-closure-return #rpf-submit') !== false
    && strpos($report, "root.classList.toggle('is-closure-return'") !== false
    && strpos($report, "attributeFilter:['data-selected-stop-id']") !== false;
$assert($idleActionsHidden, 'idle_actions_hidden');

// My Route paints before Google and the legacy autocomplete is opt-in only.
$myRoute = $slice($short, 'public static function my_daily_route(', 'public static function front_guard(');
$routeFirstPaint = $myRoute !== ''
    && strpos($myRoute, 'Promise.resolve().then(()=>loadRoutesForDay(inpDate.value)).catch(()=>{});') !== false
    && strpos($myRoute, 'loadGoogle(()=>{ loadRoutesForDay(inpDate.value); });') === false
    && strpos($myRoute, 'loadGoogle(()=> {') !== false;
$assert($routeFirstPaint, 'route_first_paint');
$legacyAutocompleteRetired = strpos($plugin, "if (!apply_filters('fieldflow_legacy_pdv_autocomplete_enabled', false)) return;") !== false
    && strpos($plugin, "wp_enqueue_script('ff-autocomplete'") !== false;
$assert($legacyAutocompleteRetired, 'legacy_autocomplete_retired');

// Historical authorities preserved.
$form = $read('src/Forms/FormRenderer.php');
$historicalContinuity = strpos($short, 'editingExisting') !== false
    && strpos($form, '_rpVisitCloseConfirmed') !== false
    && strpos($short, "reportViewportContract='fieldflow-form-viewport-continuity-v2'") !== false
    && strpos($short, 'applyVisitState(detail,status,{remember:true,announce:true})') !== false
    && strpos($short, 'else clearStoredReportContext()') !== false
    && strpos($read('src/Forms/SubmissionPipeline.php'), "public const BUILD = '2.2.485'") !== false;
$assert($historicalContinuity, 'historical_continuity');

echo 'VISIT CLOSURE SELECTOR RETURN 9CI RUNTIME OK | closed_store_not_reselected=1 | selector_scroll_once=1 | next_store_card=1 | offline_return=1 | failure_return=1 | stored_context_cleared=1 | resume_checkpoint_cleared=1 | idle_actions_hidden=1 | route_first_paint=1 | legacy_autocomplete_retired=1 | historical_continuity=1' . "\n";
