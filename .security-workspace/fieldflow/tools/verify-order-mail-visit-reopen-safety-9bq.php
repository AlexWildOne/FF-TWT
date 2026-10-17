<?php
$root = dirname(__DIR__);
$fail = [];
$req = [
    'src/Admin/FormSubmissions.php'=>['persistedOrderSuggestionEvidence','persisted_positive_order_authority','hidden_positive_keys'],
    'src/Admin/ReportAutomations.php'=>['recordOrderSuggestionMailState',"'failed_retry'", "'queued_or_sent'",'accepted_by_outbox'],
    'src/Shortcodes.php'=>['Queres mesmo concluir esta visita?','editingExisting'],
    'src/Forms/FormRenderer.php'=>['Queres mesmo concluir esta visita?','_rpVisitCloseConfirmed'],
    'src/Services/VisitResetService.php'=>['reopenToPending','visit_admin_reopen','RouteProgressService::sync($routeId, false, false)'],
    'src/Services/RouteProgressService.php'=>["visit_admin_reopen']['active",'passive reconciliation'],
    'src/Services/FormVisitCompletionService.php'=>['resolved_submission_id','visit_admin_reopen'],
    'src/Rest/RoutesController.php'=>["'/stops/(?P<id>\\d+)/reopen'",'reopen_stop_pending'],
    'src/Admin/Routes.php'=>['Reabrir loja','data-reopen-stop',"api+'stops/'+stopId+'/reopen"],
    'src/Support/ReleaseGate.php'=>['ORDER_MAIL_VISIT_REOPEN_9BQ_CONTRACT','checkOrderMailVisitReopen9BQContract'],
    'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.587'","public const STABLE_RELEASE = '2.2.588'","public const STABLE_RELEASE = '2.2.588'"],
    'PHASE-9BQ-ORDER-MAIL-VISIT-REOPEN-SAFETY.md'=>['fieldflow-order-mail-visit-reopen-safety-v1','No database schema'],
];
foreach ($req as $rel=>$markers) {
    $raw = is_readable($root.'/'.$rel) ? (string)file_get_contents($root.'/'.$rel) : '';
    foreach ($markers as $marker) if (strpos($raw,$marker) === false) $fail[]=$rel.':'.$marker;
}
if ($fail) { fwrite(STDERR, "ORDER MAIL VISIT REOPEN SAFETY 9BQ CONTRACT FAIL\n".implode("\n",$fail)."\n"); exit(1); }
echo "ORDER MAIL VISIT REOPEN SAFETY 9BQ CONTRACT OK successor=>=2.2.588\n";
