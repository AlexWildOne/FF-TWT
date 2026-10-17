<?php
namespace RoutesPro\Vision;

use RoutesPro\Repositories\AIUsageRepository;

if (!defined('ABSPATH')) exit;

/**
 * 9CZ. Provider-reported tokens + explicitly configured EUR prices per model.
 * An estimate is NEVER a provider invoice. Unpriced or unmetered calls stay
 * distinguishable from genuinely priced zero-value calls.
 * The pre-call reservation guard is deliberately independent and unchanged.
 */
final class VisionCostMeter {
    public const CONTRACT = 'fieldflow-fronts-ai-usage-finops-9cz-v1';
    public const OPTION = 'fieldflow_fronts_vision_model_tariffs';
    public const PROVIDERS = ['azure', 'openai', 'google'];

    public static function cleanModel(string $model): string {
        return substr((string)preg_replace('/[^a-zA-Z0-9_.:\/\-]/', '', trim($model)), 0, 96);
    }

    private static function key(string $provider, string $model): string {
        return sanitize_key($provider) . ':' . strtolower(self::cleanModel($model));
    }

    /** Prices in EUR per 1 million of the tokens *reported by the provider*. */
    public static function rateFor(string $provider, string $model): ?array {
        $all = get_option(self::OPTION, []);
        if (!is_array($all) || !in_array($provider, self::PROVIDERS, true) || self::cleanModel($model) === '') return null;
        $row = $all[self::key($provider, $model)] ?? null;
        if (!is_array($row)) return null;
        $input = (float)($row['input_eur_per_million'] ?? 0);
        $output = (float)($row['output_eur_per_million'] ?? 0);
        if (!is_finite($input) || !is_finite($output) || $input <= 0 || $output <= 0) return null;
        return [
            'input_eur_per_million'=>$input,
            'output_eur_per_million'=>$output,
            'updated_at'=>sanitize_text_field((string)($row['updated_at'] ?? '')),
            'source'=>'manual_rate_verified_by_admin',
        ];
    }

    private static function validRate($value): float {
        $text = str_replace(',', '.', trim((string)$value));
        if (!preg_match('/^\d{1,4}(?:\.\d{1,6})?$/', $text)) throw new \InvalidArgumentException('Tarifa inválida. Indica um valor em euros com até 6 casas decimais.');
        $rate = (float)$text;
        if (!is_finite($rate) || $rate <= 0 || $rate > 1000) throw new \InvalidArgumentException('A tarifa deve ser superior a 0 e não ultrapassar 1000 € por milhão de tokens.');
        return $rate;
    }

    public static function saveRate(string $provider, string $model, $input, $output): array {
        $provider = sanitize_key($provider);
        $model = self::cleanModel($model);
        if (!in_array($provider, self::PROVIDERS, true) || $model === '') throw new \InvalidArgumentException('Fornecedor ou modelo inválido.');
        $rate = [
            'input_eur_per_million'=>self::validRate($input),
            'output_eur_per_million'=>self::validRate($output),
            'updated_at'=>current_time('mysql'),
        ];
        $all = get_option(self::OPTION, []);
        if (!is_array($all)) $all=[];
        $all[self::key($provider, $model)]=$rate;
        update_option(self::OPTION, $all, false);
        return $rate;
    }

    /** Invoice-independent estimate; 0 tokens is NOT evidence of 0 cost. */
    public static function quote(string $provider, string $model, array $providerUsage): array {
        $provider = sanitize_key($provider);
        $model = self::cleanModel($model);
        $input = max(0, (int)($providerUsage['input_tokens'] ?? 0));
        $output = max(0, (int)($providerUsage['output_tokens'] ?? 0));
        $metered = ($input + $output) > 0;
        $tariff = self::rateFor($provider, $model);
        $status = !$metered ? 'unmetered' : ($tariff === null ? 'tariff_missing' : 'priced');
        $cost = $status === 'priced'
            ? round((($input * $tariff['input_eur_per_million']) + ($output * $tariff['output_eur_per_million'])) / 1000000, 8)
            : null;
        return [
            'contract'=>self::CONTRACT,
            'provider'=>$provider,
            'model'=>$model,
            'status'=>$status,
            'token_source'=>$metered ? 'provider' : 'unavailable',
            'input_tokens'=>$input,
            'output_tokens'=>$output,
            'estimated_cost_eur'=>$cost,
            'tariff_input_eur_per_million'=>$tariff['input_eur_per_million'] ?? null,
            'tariff_output_eur_per_million'=>$tariff['output_eur_per_million'] ?? null,
            'tariff_updated_at'=>$tariff['updated_at'] ?? '',
            'pricing_source'=>$tariff === null ? 'missing' : 'admin_configured',
        ];
    }

    /**
     * One aggregation across every client for the current month. Legacy camera
     * calls without 9CZ metadata are reported separately, never assumed free.
     */
    public static function monthByClient(): array {
        global $wpdb;
        if (!class_exists(AIUsageRepository::class) || !AIUsageRepository::exists()
            || !AIUsageRepository::hasAttributionColumns()) return [];
        $since = wp_date('Y-m-01 00:00:00', current_time('timestamp'));
        $pricedLike = '%"vision_cost_status":"priced"%';
        $missingLike = '%"vision_cost_status":"tariff_missing"%';
        $unmeteredLike = '%"vision_cost_status":"unmetered"%';
        $sql = 'SELECT client_id, COUNT(*) attempts, '
            . "SUM(status='success') successes, SUM(status='error') failures, "
            . 'COALESCE(SUM(CASE WHEN usage_source=%s THEN input_tokens ELSE 0 END),0) provider_input_tokens, '
            . 'COALESCE(SUM(CASE WHEN usage_source=%s THEN output_tokens ELSE 0 END),0) provider_output_tokens, '
            . 'SUM(CASE WHEN meta_json LIKE %s THEN 1 ELSE 0 END) priced_calls, '
            . 'SUM(CASE WHEN meta_json LIKE %s THEN 1 ELSE 0 END) unpriced_calls, '
            . 'SUM(CASE WHEN meta_json LIKE %s THEN 1 ELSE 0 END) unmetered_calls, '
            . 'COALESCE(SUM(CASE WHEN meta_json LIKE %s THEN estimated_cost_eur ELSE 0 END),0) estimate_eur '
            . 'FROM ' . AIUsageRepository::table() . ' WHERE created_at >= %s AND module_key=%s GROUP BY client_id';
        $prepared = $wpdb->prepare($sql, 'provider', 'provider', $pricedLike, $missingLike, $unmeteredLike,
            $pricedLike, $since, 'fronts_vision');
        $out=[];
        foreach (($wpdb->get_results($prepared, ARRAY_A) ?: []) as $row) {
            $clientId = max(0, (int)($row['client_id'] ?? 0));
            $row['attempts']=(int)$row['attempts'];
            foreach (['successes','failures','priced_calls','unpriced_calls','unmetered_calls',
                      'provider_input_tokens','provider_output_tokens'] as $key) $row[$key]=(int)($row[$key] ?? 0);
            $row['estimate_eur']=(float)($row['estimate_eur'] ?? 0);
            $row['legacy_calls']=max(0, $row['attempts']-$row['priced_calls']-$row['unpriced_calls']-$row['unmetered_calls']);
            $out[$clientId]=$row;
        }
        return $out;
    }
}
