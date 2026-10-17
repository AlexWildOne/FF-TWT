<?php
$routes=[
 ['date'=>'2026-09-01','km'=>10.0,'cost'=>4.0],
 ['date'=>'2026-09-15','km'=>20.0,'cost'=>8.0],
 ['date'=>'2026-10-01','km'=>30.0,'cost'=>12.0],
];
$from='2026-09-01';$to='2026-09-30';
$filtered=array_values(array_filter($routes,static fn($r)=>$r['date'] >= $from && $r['date'] <= $to));
$km=array_sum(array_column($filtered,'km'));$cost=array_sum(array_column($filtered,'cost'));
if(count($filtered)!==2 || abs($km-30.0)>0.001 || abs($cost-12.0)>0.001){fwrite(STDERR,"FAILED date_scope\n");exit(1);}echo "CLIENT COST DATE SCOPE ROUTE TOTALS 9AV RUNTIME OK\n";
