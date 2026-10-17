<?php
$root=dirname(__DIR__);
$client=(string)file_get_contents($root.'/src/Front/ClientPortalShortcodeRenderer.php');
$operative=(string)file_get_contents($root.'/src/Shortcodes.php');
$assert=function($ok,$label){if(!$ok){fwrite(STDERR,"FAIL {$label}\n");exit(1);}echo "PASS {$label}\n";};
$assert(strpos($client,'data-rp-evidence-store-detail')!==false && strpos($client,'data-rp-evidence-date-detail')!==false,'client_evidence_collapsible');
$assert(strpos($operative,'ffad-evidence-section')!==false && strpos($operative,'data-ffad-detail')!==false,'operative_evidence_collapsible');
$assert(substr_count($client,'width:100vw!important')>=1 && substr_count($operative,'width:100vw!important')>=1,'true_mobile_viewport');
$assert(strpos($client,'.rp-performance-leader :is(span,h5,strong,p,small){color:#fff!important}')!==false && strpos($operative,'.ffad-performance-focus :is(span,h6,strong,p,small){color:#fff!important}')!==false,'performance_contrast');
$assert(strpos($client,'height:50px!important;min-height:50px!important;max-height:96px!important')!==false && strpos($operative,'height:50px!important;min-height:50px!important;max-height:96px!important')!==false,'ask_height_compact');
$assert(strpos($client,"content:'Abrir'!important")!==false && strpos($client,"content:'Fechar'!important")!==false,'client_data_labels_compact');
$assert(strpos($client,'calc(194px + env(safe-area-inset-bottom,0px))')!==false && strpos($operative,'safe-area-inset-bottom')!==false,'bottom_nav_clearance');
echo "MOBILE ANALYTICS READABILITY EVIDENCE DENSITY 9BT RUNTIME OK client_evidence_collapsible operative_evidence_collapsible true_mobile_viewport performance_contrast ask_height_compact client_data_labels_compact bottom_nav_clearance\n";
