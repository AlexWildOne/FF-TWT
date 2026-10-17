<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_key')) {
    function sanitize_key($value) {
        $value = strtolower((string)$value);
        return preg_replace('/[^a-z0-9_\-]/', '', $value) ?: '';
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
}
require_once dirname(__DIR__) . '/src/Forms/AnswerSemantics.php';

use RoutesPro\Forms\AnswerSemantics;

$assert = static function($condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
};

$currency = AnswerSemantics::normalize(['type'=>'currency'], '1.234,50', true);
$assert(!empty($currency['valid']) && abs((float)$currency['value'] - 1234.5) < 0.0001, 'currency_locale');
$invalid = AnswerSemantics::normalize(['type'=>'number'], 'abc', true);
$assert(empty($invalid['valid']) && ($invalid['issue'] ?? '') === 'number_invalid', 'invalid_number');
$max = AnswerSemantics::normalize(['type'=>'percent','min'=>0,'max'=>100], '101', true);
$assert(empty($max['valid']) && ($max['issue'] ?? '') === 'number_above_max', 'max_guard');
$choice = AnswerSemantics::normalize(['type'=>'select','options'=>['A','B']], 'B', true);
$assert(!empty($choice['valid']) && ($choice['value'] ?? '') === 'B', 'choice_allowed');
$choiceBad = AnswerSemantics::normalize(['type'=>'select','options'=>['A','B']], 'C', true);
$assert(empty($choiceBad['valid']) && ($choiceBad['issue'] ?? '') === 'choice_not_allowed', 'choice_rejected');
$multi = AnswerSemantics::normalize(['type'=>'select','selection_mode'=>'multiple','options'=>['A','B']], ['A','A','B'], true);
$assert(!empty($multi['valid']) && ($multi['value'] ?? []) === ['A','B'], 'multiple_unique');
$checkbox = AnswerSemantics::normalize(['type'=>'checkbox'], false, true);
$assert(!empty($checkbox['valid']) && ($checkbox['value'] ?? null) === 0, 'checkbox_zero');
$structured = AnswerSemantics::normalize(['type'=>'product_matrix'], 'bad', true);
$assert(empty($structured['valid']) && ($structured['issue'] ?? '') === 'structured_value_invalid', 'structured_shape');

echo "CANONICAL ANSWER SEMANTICS RUNTIME OK\n";
echo "currency_locale=1234.5\n";
echo "invalid_number=rejected\n";
echo "choice_domain=enforced\n";
echo "min_max=enforced\n";
