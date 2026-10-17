<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
if (!function_exists('sanitize_key')) { function sanitize_key($value){ return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$value)); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($value){ return trim(strip_tags((string)$value)); } }
require_once dirname(__DIR__) . '/src/Forms/ConditionalGraphIntegrity.php';

use RoutesPro\Forms\ConditionalGraphIntegrity;

$bad = ['questions'=>[
    ['key'=>'fizeste_alguma_conquista','label'=>'Fizeste alguma conquista?','type'=>'select','conditions'=>['enabled'=>true,'rules'=>[
        ['value'=>'Não','go_to'=>'sugestaoencomenda'],
        ['value'=>'Sim','go_to'=>'quais_as_conquistas'],
    ]]],
    ['key'=>'quais_as_conquistas','label'=>'Quais as conquistas?','type'=>'categorized_image_upload'],
    ['key'=>'sugestaoencomenda','label'=>'Foi efetuada uma sugestão de encomenda?','type'=>'radio','conditions'=>['enabled'=>true,'rules'=>[
        ['value'=>'Sim','go_to'=>'sugestaodeencomenda'],
        ['value'=>'Sim','go_to'=>'fotosugestao'],
    ]]],
    ['key'=>'sugestaodeencomenda','label'=>'Sugestão de encomenda','type'=>'order_suggestion'],
    ['key'=>'fotosugestao','label'=>'Foto Sugestão Encomenda','type'=>'image_upload'],
]];
$badAudit = ConditionalGraphIntegrity::analyse($bad);
if (($badAudit['warning_count'] ?? 0) !== 1) {
    fwrite(STDERR, "CONDITION GRAPH INTEGRITY 9AQ RUNTIME FAIL: expected 1 warning\n"); exit(1);
}
$warning = $badAudit['warnings'][0] ?? [];
if (($warning['source_domain'] ?? '') !== 'conquest' || ($warning['target_domain'] ?? '') !== 'order') {
    fwrite(STDERR, "CONDITION GRAPH INTEGRITY 9AQ RUNTIME FAIL: expected conquest->order warning\n"); exit(1);
}
if (count($badAudit['controlled_by']['sugestaoencomenda'] ?? []) !== 1) {
    fwrite(STDERR, "CONDITION GRAPH INTEGRITY 9AQ RUNTIME FAIL: controlled_by missing\n"); exit(1);
}

$fixed = $bad;
$fixed['questions'][0]['conditions']['rules'] = [
    ['value'=>'Sim','go_to'=>'quais_as_conquistas'],
];
$fixedAudit = ConditionalGraphIntegrity::analyse($fixed);
if (!empty($fixedAudit['warnings'])) {
    fwrite(STDERR, "CONDITION GRAPH INTEGRITY 9AQ RUNTIME FAIL: fixed graph should be clean\n"); exit(1);
}
if (($fixedAudit['combination'] ?? '') !== 'or' || ($fixedAudit['semantics'] ?? '') !== 'non_blocking_graph_audit') {
    fwrite(STDERR, "CONDITION GRAPH INTEGRITY 9AQ RUNTIME FAIL: audit semantics changed\n"); exit(1);
}

echo "CONDITION GRAPH INTEGRITY 9AQ RUNTIME OK\n";
