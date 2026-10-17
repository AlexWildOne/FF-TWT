<?php
$root = dirname(__DIR__);
$checks = [
    'src/Forms/ConditionalGraphIntegrity.php' => ['fieldflow-condition-graph-integrity-v1','cross_domain_gate','controlled_by','non_blocking_graph_audit'],
    'src/Admin/Forms.php' => ['ConditionalGraphIntegrity::analyse','Auditoria condicional'],
    'assets/routespro-form-builder.js' => ['fieldflow-condition-graph-integrity-v1','Controlada por','Grafo condicional coerente','cross_domain_gate'],
    'assets/routespro-form-builder.css' => ['twt-fb-integrity','twt-fb-controlled-by','has-condition-integrity-warning'],
    'PHASE-9AQ-CONDITION-GRAPH-INTEGRITY.md' => ['fieldflow-condition-graph-integrity-v1','`SOURCE_RELEASE` is `2.2.560` and `STABLE_RELEASE` is `2.2.561`'],
    'tools/test-condition-graph-integrity-9aq.php' => ['CONDITION GRAPH INTEGRITY 9AQ RUNTIME OK'],
];
$missing = [];
foreach ($checks as $rel=>$markers) {
    $path = $root . '/' . $rel;
    $raw = is_readable($path) ? (string)file_get_contents($path) : '';
    if ($raw === '') { $missing[] = $rel . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $rel . ':' . $marker;
}
if ($missing) {
    fwrite(STDERR, "CONDITION GRAPH INTEGRITY 9AQ CONTRACT FAIL\n" . implode("\n", $missing) . "\n"); exit(1);
}
echo "CONDITION GRAPH INTEGRITY 9AQ CONTRACT OK\n";
