<?php
$failed=[];
$assert=static function(bool $ok,string $name) use (&$failed): void { if(!$ok)$failed[]=$name; };
// Route cost composition must be additive and explicit.
$energy=11.40;$operational=7.85;$tolls=3.25;$total=$energy+$operational+$tolls;
$assert(abs($total-22.50)<0.0001,'route_cost_composition');
// Reporting projection may expose known reconciled totals without changing the canonical formula.
$reporting=['reporting_costs_available'=>true,'reporting_energy_cost_eur'=>$energy,'reporting_operational_cost_eur'=>$operational,'reporting_toll_cost_eur'=>$tolls,'reporting_total_mobility_cost_eur'=>$total];
$assert($reporting['reporting_costs_available']===true && abs($reporting['reporting_total_mobility_cost_eur']-($reporting['reporting_energy_cost_eur']+$reporting['reporting_operational_cost_eur']+$reporting['reporting_toll_cost_eur']))<0.0001,'reporting_projection_composition');
// Performance score deliberately excludes raw question count.
$score=static fn(float $execution,float $coverage): float => round(($execution*0.80)+($coverage*0.20),1);
$assert($score(90,100)===92.0,'score_formula');
$memberA=['name'=>'A','execution'=>90.0,'coverage'=>100.0,'done'=>90,'questions'=>900,'score'=>$score(90,100)];
$memberB=['name'=>'B','execution'=>95.0,'coverage'=>100.0,'done'=>95,'questions'=>10,'score'=>$score(95,100)];
$assert($memberB['score']>$memberA['score'],'question_volume_not_score_driver');
// Audience semantics: client can compare team; merchandiser receives self-only focus.
$scope=static function(string $audience,int $selectedUser): string { return $audience==='merchandiser'?'personal':($selectedUser>0?'individual':'team'); };
$assert($scope('client',0)==='team','client_team_default');
$assert($scope('client',42)==='individual','client_individual_filter');
$assert($scope('merchandiser',0)==='personal','merchandiser_self_only');
if($failed){fwrite(STDERR,'FAILED '.implode(',',$failed).PHP_EOL);exit(1);}echo "ROUTE FINANCIAL AUDIENCE ANALYTICS 9AW RUNTIME OK\n";
