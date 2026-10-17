<?php
namespace RoutesPro\AI;

use RoutesPro\Repositories\AIUsageRepository;
use RoutesPro\Support\Config;
use RoutesPro\Support\Logger;

if (!defined('ABSPATH')) exit;

/**
 * Central policy layer for every generative AI request.
 *
 * Governance 2.0 adds native FinOps attribution and budget tiers while keeping
 * the existing shadow/enforce behaviour and global monthly fuse intact.
 */
class Governance {
    public const VERSION = '2.0';
    public const CONTRACT = 'fieldflow-ai-governance-finops-v2';
    public const ALERT_THRESHOLDS = [70, 85, 100];

    public static function preflight(string $module, string $prompt, array $params = []): array {
        $mode = self::mode();
        $flags = self::detectRisks($prompt);
        $redactions = [];
        $prepared = $prompt;
        $attribution = self::attribution($module, $params);

        if ($mode === 'enforce' && !empty(Config::get('ai_redact_personal_data', 0))) {
            [$prepared, $redactions] = self::redactPersonalData($prepared);
        }

        $blocked = false;
        $reason = '';
        if ($mode === 'enforce' && !empty(Config::get('ai_block_prompt_injection', 1)) && in_array('prompt_injection', $flags, true)) {
            $blocked = true;
            $reason = 'Conteúdo potencialmente manipulador detetado antes do envio ao fornecedor de IA.';
        }

        $budgets = self::budgetSnapshot($attribution['client_id'], $attribution['project_id']);
        foreach ($budgets as $budget) {
            self::emitBudgetAlertOnce($budget, $attribution, $module);
            if ($mode === 'enforce' && !$blocked && !empty($budget['limit_eur']) && !empty($budget['reached'])) {
                $blocked = true;
                $flags[] = (string)$budget['flag'];
                $reason = (string)$budget['block_message'];
            }
        }

        $task = sanitize_key((string)($params['task'] ?? 'general')) ?: 'general';
        $promptVersion = sanitize_text_field((string)($params['prompt_version'] ?? ($task . '@1')));

        return [
            'prompt' => $prepared,
            'mode' => $mode,
            'blocked' => $blocked,
            'reason' => $reason,
            'risk_flags' => array_values(array_unique($flags)),
            'redaction_types' => array_values(array_unique($redactions)),
            'prompt_version' => substr($promptVersion, 0, 96),
            'prompt_hash' => hash('sha256', $prompt),
            'governance_version' => self::VERSION,
            'governance_contract' => self::CONTRACT,
            'attribution' => $attribution,
            'budgets' => $budgets,
        ];
    }

    public static function mode(): string {
        $mode = sanitize_key((string)Config::get('ai_governance_mode', 'shadow'));
        return in_array($mode, ['off', 'shadow', 'enforce'], true) ? $mode : 'shadow';
    }

    public static function attribution(string $module, array $params = []): array {
        $nested = is_array($params['context'] ?? null) ? (array)$params['context'] : [];
        $provided = is_array($params['fieldflow_attribution'] ?? null) ? (array)$params['fieldflow_attribution'] : [];
        $clientId = absint($provided['client_id'] ?? $params['client_id'] ?? $nested['client_id'] ?? 0);
        $projectId = absint($provided['project_id'] ?? $params['project_id'] ?? $nested['project_id'] ?? 0);
        $feature = sanitize_key((string)($provided['feature_key'] ?? $params['feature_key'] ?? $params['task'] ?? $module)) ?: sanitize_key($module) ?: 'general';
        $kind = sanitize_key((string)($provided['request_kind'] ?? $params['request_kind'] ?? ''));
        if ($kind === '') {
            if ($feature === 'connection_test') $kind = 'admin_test';
            elseif (!empty($params['automated']) || !empty($params['schedule_id'])) $kind = 'automation';
            elseif (!empty($params['manual'])) $kind = 'manual';
            else $kind = 'runtime';
        }
        return [
            'client_id'=>$clientId,
            'project_id'=>$projectId,
            'feature_key'=>$feature,
            'request_kind'=>$kind,
        ];
    }

    public static function detectRisks(string $text): array {
        $flags = [];
        $patterns = [
            '/\b(ignore|ignora|esquece|desconsidera)\b.{0,80}\b(instru[cç][oõ]es|prompt|regras|mensagem de sistema|system message)\b/iu',
            '/\b(jailbreak|developer mode|modo programador|revela o prompt|show system prompt)\b/iu',
            '/\b(executa|execute|corre|run)\b.{0,50}\b(sql|comando|command|shell|script)\b/iu',
            '/\b(api key|chave de api|password|palavra.passe|token secreto|secret token)\b/iu',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $flags[] = 'prompt_injection';
                break;
            }
        }
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $text)) $flags[] = 'email_address';
        if (preg_match('/(?<!\d)(?:\+?351[\s.-]?)?(?:2\d{8}|9\d{8})(?!\d)/u', $text)) $flags[] = 'phone_number';
        if (preg_match('/(?<!\d)\d{9}(?!\d)/u', $text)) $flags[] = 'possible_tax_or_identity_number';
        return array_values(array_unique($flags));
    }

    public static function redactPersonalData(string $text): array {
        $types = [];
        $replacements = [
            'email' => ['/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[EMAIL_REMOVIDO]'],
            'phone' => ['/(?<!\d)(?:\+?351[\s.-]?)?(?:2\d{8}|9\d{8})(?!\d)/u', '[TELEFONE_REMOVIDO]'],
        ];
        foreach ($replacements as $type => $definition) {
            $count = 0;
            $next = preg_replace($definition[0], $definition[1], $text, -1, $count);
            if (is_string($next)) $text = $next;
            if ($count > 0) $types[] = $type;
        }
        return [$text, $types];
    }

    public static function usageFor($provider, string $input, string $output): array {
        $usage = method_exists($provider, 'getLastUsage') ? (array)$provider->getLastUsage() : [];
        $inputTokens = max(0, (int)($usage['input_tokens'] ?? 0));
        $outputTokens = max(0, (int)($usage['output_tokens'] ?? 0));
        $source = ($inputTokens > 0 || $outputTokens > 0) ? 'provider' : 'estimated';
        if ($inputTokens <= 0) $inputTokens = self::estimateTokens($input);
        if ($outputTokens <= 0) $outputTokens = self::estimateTokens($output);
        return [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'usage_source' => $source,
            'estimated_cost_eur' => self::cost($inputTokens, $outputTokens),
        ];
    }

    public static function estimateTokens(string $text): int {
        if ($text === '') return 0;
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        return max(1, (int)ceil($length / 4));
    }

    public static function cost(int $inputTokens, int $outputTokens): float {
        $inputRate = max(0.0, (float)Config::get('ai_input_cost_per_million_eur', 0));
        $outputRate = max(0.0, (float)Config::get('ai_output_cost_per_million_eur', 0));
        return round((($inputTokens / 1000000) * $inputRate) + (($outputTokens / 1000000) * $outputRate), 6);
    }

    /** @return array<int,array<string,mixed>> */
    public static function budgetSnapshot(int $clientId = 0, int $projectId = 0): array {
        $items = [];
        $items[] = self::budgetItem(
            'global', 0, max(0.0, (float)Config::get('ai_monthly_budget_eur', 0)),
            class_exists(AIUsageRepository::class) ? AIUsageRepository::monthCost() : 0.0,
            'monthly_budget', 'O orçamento mensal global configurado para IA foi atingido.'
        );
        $nativeAttribution = class_exists(AIUsageRepository::class) && AIUsageRepository::hasAttributionColumns();
        if ($clientId > 0 && $nativeAttribution) {
            $items[] = self::budgetItem(
                'client', $clientId, max(0.0, (float)Config::get('ai_client_monthly_budget_eur', 0)),
                class_exists(AIUsageRepository::class) ? AIUsageRepository::monthCost(['client_id'=>$clientId]) : 0.0,
                'client_monthly_budget', 'O orçamento mensal de IA deste cliente foi atingido.'
            );
        }
        if ($projectId > 0 && $nativeAttribution) {
            $items[] = self::budgetItem(
                'project', $projectId, max(0.0, (float)Config::get('ai_project_monthly_budget_eur', 0)),
                class_exists(AIUsageRepository::class) ? AIUsageRepository::monthCost(['project_id'=>$projectId]) : 0.0,
                'project_monthly_budget', 'O orçamento mensal de IA deste projeto foi atingido.'
            );
        }
        return $items;
    }

    public static function reconcileBudgetAlerts(array $attribution, string $module = 'general'): void {
        $clientId = absint($attribution['client_id'] ?? 0);
        $projectId = absint($attribution['project_id'] ?? 0);
        foreach (self::budgetSnapshot($clientId, $projectId) as $budget) {
            self::emitBudgetAlertOnce($budget, $attribution, $module);
        }
    }

    private static function budgetItem(string $scope, int $scopeId, float $limit, float $cost, string $flag, string $message): array {
        $ratio = $limit > 0 ? (($cost / $limit) * 100) : 0.0;
        $threshold = 0;
        foreach (self::ALERT_THRESHOLDS as $candidate) if ($ratio >= $candidate) $threshold = $candidate;
        return [
            'scope'=>$scope,
            'scope_id'=>$scopeId,
            'limit_eur'=>$limit,
            'cost_eur'=>$cost,
            'remaining_eur'=>$limit > 0 ? max(0.0, $limit - $cost) : null,
            'usage_percent'=>$limit > 0 ? round($ratio, 2) : 0.0,
            'threshold'=>$threshold,
            'reached'=>$limit > 0 && $cost >= $limit,
            'flag'=>$flag,
            'block_message'=>$message,
        ];
    }

    private static function emitBudgetAlertOnce(array $budget, array $attribution, string $module): void {
        $usagePercent = (float)($budget['usage_percent'] ?? 0);
        if ($usagePercent < 70 || empty($budget['limit_eur'])) return;
        $month = function_exists('wp_date') ? wp_date('Ym', current_time('timestamp')) : gmdate('Ym');
        $option = 'fieldflow_ai_budget_alerts_' . $month;
        $state = get_option($option, []);
        if (!is_array($state)) $state = [];
        $changed = false;
        foreach (self::ALERT_THRESHOLDS as $threshold) {
            if ($usagePercent < $threshold) continue;
            $key = sanitize_key((string)$budget['scope']) . ':' . absint($budget['scope_id'] ?? 0) . ':' . $threshold;
            if (isset($state[$key])) continue;
            $state[$key] = current_time('mysql');
            $changed = true;
            $context = [
                'scope'=>(string)$budget['scope'],
                'scope_id'=>absint($budget['scope_id'] ?? 0),
                'threshold'=>$threshold,
                'usage_percent'=>$usagePercent,
                'cost_eur'=>(float)($budget['cost_eur'] ?? 0),
                'limit_eur'=>(float)($budget['limit_eur'] ?? 0),
                'client_id'=>absint($attribution['client_id'] ?? 0),
                'project_id'=>absint($attribution['project_id'] ?? 0),
                'module'=>sanitize_key($module),
            ];
            if (class_exists(Logger::class)) Logger::warning('AI budget threshold reached.', ['context_key'=>'ai_finops_budget'], $context);
            do_action('fieldflow_ai_budget_threshold', $context);
        }
        if ($changed) update_option($option, $state, false);
    }

    public static function status(): array {
        $summary = class_exists(AIUsageRepository::class) ? AIUsageRepository::summary(30) : [];
        $budget = max(0.0, (float)Config::get('ai_monthly_budget_eur', 0));
        $cost = class_exists(AIUsageRepository::class) ? AIUsageRepository::monthCost() : 0.0;
        $global = self::budgetItem('global', 0, $budget, $cost, 'monthly_budget', 'O orçamento mensal global configurado para IA foi atingido.');
        return [
            'version' => self::VERSION,
            'contract' => self::CONTRACT,
            'mode' => self::mode(),
            'redaction_enabled' => !empty(Config::get('ai_redact_personal_data', 0)),
            'injection_blocking' => !empty(Config::get('ai_block_prompt_injection', 1)),
            'monthly_budget_eur' => $budget,
            'client_monthly_budget_eur' => max(0.0, (float)Config::get('ai_client_monthly_budget_eur', 0)),
            'project_monthly_budget_eur' => max(0.0, (float)Config::get('ai_project_monthly_budget_eur', 0)),
            'month_cost_eur' => $cost,
            'budget_remaining_eur' => $global['remaining_eur'],
            'budget_usage_percent' => $global['usage_percent'],
            'budget_threshold' => $global['threshold'],
            'alert_thresholds' => self::ALERT_THRESHOLDS,
            'tracked_tokens' => (int)($summary['input_tokens'] ?? 0) + (int)($summary['output_tokens'] ?? 0),
            'native_attribution' => class_exists(AIUsageRepository::class) && AIUsageRepository::hasAttributionColumns(),
        ];
    }
}
