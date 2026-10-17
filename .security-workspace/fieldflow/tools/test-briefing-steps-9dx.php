<?php
/** 9DX | fieldflow-briefing-steps-continuity-9dx-v1. Runs without WP/SQL writes. */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
$GLOBALS['ff_options'] = [];
$GLOBALS['ff_uuid'] = 1;
function get_option($key, $default = false) {return $GLOBALS['ff_options'][$key] ?? $default;}
function update_option($key, $value, $autoload = null) {$GLOBALS['ff_options'][$key] = $value; return true;}
function wp_generate_uuid4() {return sprintf('00000000-0000-4000-8000-%012d', $GLOBALS['ff_uuid']++);}
function sanitize_key($value) {return trim((string)preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$value)));}
function sanitize_text_field($value) {return trim(strip_tags((string)$value));}
function absint($value) {return abs((int)$value);}
function current_time($type) {return $type === 'Y-m-d' ? '2026-10-16' : '2026-10-16 12:00:00';}
function wp_json_encode($value, $options=0) {return json_encode($value, $options);}
class TestBriefingDb9DX {
    public $prefix = 'wp_';
    public $rows = [];
    public function prepare($query, ...$args) {return $query;}
    public function get_var($query) {
        if (str_contains($query, 'SHOW TABLES')) return 'wp_routespro_report_context_questions';
        if (str_contains($query, 'SHOW COLUMNS')) return 'valid_from';
        return 0;
    }
    public function get_results($query, $mode = null) {
        if (str_contains($query, 'wp_routespro_report_context_questions')) return $this->rows;
        if (str_contains($query, 'wp_routespro_briefings')) {
            return [['id'=>33,'title'=>'Briefing W42','year_no'=>2026,'week_no'=>42,'version_no'=>1,
              'valid_from'=>'2026-10-12','valid_to'=>'2026-10-18','status'=>'published']];
        }
        return [];
    }
}
$wpdb = $GLOBALS['wpdb'] = new TestBriefingDb9DX();
require_once dirname(__DIR__) . '/src/Briefings/BriefingSteps.php';
require_once dirname(__DIR__) . '/src/Briefings/ShareSpaceMatrix.php';
require_once dirname(__DIR__) . '/src/Forms/ContextQuestions.php';
require_once dirname(__DIR__) . '/src/Forms/BriefingFormBridge.php';
use RoutesPro\Briefings\BriefingSteps;
use RoutesPro\Forms\BriefingFormBridge;
$assertions = 0;
function ff9dx($ok, $label) {global $assertions; $assertions++; if (!$ok) throw new RuntimeException('9DX assertion '.$assertions.' FAILED: '.$label);}
$stepA = BriefingSteps::save(33, '', 'Implementação', 'Ações na loja', 10);
$stepB = BriefingSteps::save(33, '', 'Frentes e concorrência', 'Share of space', 20);
ff9dx(empty($stepA['error']) && empty($stepB['error']), 'create step definitions');
$first = $stepA['key']; $second = $stepB['key'];
ff9dx($first !== $second, 'identities unique');
ff9dx(BriefingSteps::save(33, $second, 'Frentes e concorrência V2', 'Comparativo', 5)['key'] === $second, 'rename keeps stable id');
ff9dx(BriefingSteps::all(33)[0]['key'] === $second, 'step order independent of question keys');
ff9dx(!BriefingSteps::find(44, $first), 'tenant/briefing partitioning');
ff9dx(!empty(BriefingSteps::save(33, 'injected', 'X', '', 10)['error']), 'cannot silently attach unknown step key');
function briefingRow9dx($id, $key, $location, $stepKey, $priority) {
  return [
    'id'=>$id,'client_id'=>0,'project_id'=>0,'form_id'=>0,'location_id'=>$location,'campaign_location_id'=>0,
    'source_type'=>'briefing','briefing_id'=>33,'briefing_version_no'=>1,'question_key'=>$key,'question_label'=>'Pergunta '.$key,
    'question_type'=>'radio','is_active'=>1,'priority'=>$priority,'question_options_json'=>'["Sim","Não"]',
    'is_required'=>0,'valid_from'=>'2026-10-12','valid_to'=>'2026-10-18',
    'visibility_rules_json'=>json_encode(['target_scope'=>'project_active','allows_unmapped'=>true,'briefing_step_key'=>$stepKey]),
  ];
}
$wpdb->rows = [
  briefingRow9dx(1,'answer_implementation',12,$first,10),
  briefingRow9dx(2,'answer_competitor',12,$second,20),
  briefingRow9dx(3,'answer_ungrouped',12,'',30),
  briefingRow9dx(4,'foreign_store_question',444,$first,40),
];
$schema = [
  'meta'=>['title'=>'Visita'],
  'questions'=>[
    ['key'=>'before','type'=>'text','label'=>'Antes'],
    ['key'=>'weekly_briefing','type'=>'briefing_block','label'=>'Briefing semanal'],
    ['key'=>'after','type'=>'text','label'=>'Depois'],
    ['key'=>'safety','type'=>'text','label'=>'Fim'],
  ],
  'layout'=>['mode'=>'steps','show_progress'=>true,'steps'=>[
    ['title'=>'Rotina','fields'=>['before','weekly_briefing','after']],
    ['title'=>'Fecho','fields'=>['safety']],
  ]],
];
$context = ['location_id'=>12,'form_id'=>7,'project_id'=>0,'date'=>'2026-10-16'];
$expanded = BriefingFormBridge::expand_blocks($schema, $context);
$questionKeys = array_column($expanded['questions'], 'key');
ff9dx(!in_array('weekly_briefing', $questionKeys,true), 'placeholder removed');
ff9dx(!in_array('foreign_store_question', $questionKeys,true), 'another store excluded');
ff9dx(in_array('answer_implementation', $questionKeys,true) && in_array('answer_competitor', $questionKeys,true), 'question keys remain stable');
ff9dx(in_array('answer_ungrouped', $questionKeys,true), 'unassigned questions are retained');
$steps = $expanded['layout']['steps'];
ff9dx(count($steps) === 6, 'existing step safely split to before + 3 briefing + after + close');
ff9dx($steps[0]['fields'] === ['before'] && $steps[4]['fields'] === ['after'] && $steps[5]['fields'] === ['safety'], 'native form fields retain positions');
ff9dx($steps[1]['title'] === 'Frentes e concorrência V2' && $steps[1]['fields'][1] === 'answer_competitor', 'step reorder and rename reflected');
ff9dx($steps[2]['title'] === 'Implementação' && $steps[2]['fields'] === ['answer_implementation'], 'assigned group reflected');
ff9dx($steps[3]['title'] === 'Outras perguntas do briefing' && $steps[3]['fields'] === ['answer_ungrouped'], 'optional questions never disappear');
ff9dx($steps[1]['fields'][0] === 'weekly_briefing_intro_33', 'briefing context retained in first step');
$keysAcrossSteps = array_merge(...array_map(static fn($row) => (array)($row['fields']??[]), $steps));
ff9dx(count($keysAcrossSteps) === count(array_unique($keysAcrossSteps)), 'every form key appears once');
ff9dx($expanded['layout']['mode'] === 'steps', 'native form mode kept');
// Existing one-page forms opt in only when a briefing question is explicitly categorised.
$single = $schema;
$single['layout'] = ['mode'=>'single','show_progress'=>false,'steps'=>[]];
$singleRuntime = BriefingFormBridge::expand_blocks($single, $context);
ff9dx($singleRuntime['layout']['mode'] === 'steps' && $singleRuntime['layout']['show_progress'] === true, 'single mode becomes wizard after opt-in');
ff9dx($questionKeys === array_column($singleRuntime['questions'],'key'), 'single/wizard answers have identical keys');
$wpdb->rows = [briefingRow9dx(1,'answer_implementation',12,'',10), briefingRow9dx(2,'answer_competitor',12,'',20)];
$legacy = BriefingFormBridge::expand_blocks($schema, $context);
ff9dx(count($legacy['layout']['steps']) === 2 && $legacy['layout']['steps'][0]['title'] === 'Rotina', 'unconfigured briefing has unchanged wizard layout');
ff9dx($legacy['layout']['steps'][0]['fields'] === ['before','weekly_briefing_intro_33','answer_implementation','answer_competitor','after'], 'legacy question order exactly intact');
$singleLegacy = BriefingFormBridge::expand_blocks($single, $context);
ff9dx($singleLegacy['layout']['mode'] === 'single' && $singleLegacy['layout']['show_progress'] === false, 'unconfigured single forms untouched');
$beforeOptions = $GLOBALS['ff_options'];
BriefingFormBridge::expand_blocks($schema,$context);
ff9dx($beforeOptions === $GLOBALS['ff_options'], 'rendering does not mutate steps or history');
// Source-level guardrail: 9DX touches neither answer rows nor the storage service.
foreach (['src/Briefings/BriefingSteps.php','src/Forms/BriefingFormBridge.php'] as $relative) {
  $source = file_get_contents(dirname(__DIR__).'/'.$relative);
  foreach (['DELETE FROM', 'TRUNCATE', 'wpdb->delete(', 'wp_delete_post('] as $token) ff9dx(strpos($source,$token) === false,$relative.' is non-destructive for '.$token);
}
echo 'BRIEFING STEPS CONTINUITY 9DX PASS ('.$assertions.' assertions)' . PHP_EOL;
