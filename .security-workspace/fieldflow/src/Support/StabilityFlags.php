<?php
namespace RoutesPro\Support;

if (!defined('ABSPATH')) exit;

/**
 * Safe rollout switches for architecture changes.
 *
 * All replacement engines stay disabled by default. This lets FieldFlow ship
 * compatibility scaffolding without changing the behaviour used by the web
 * portals or the current FlutterFlow application.
 */
class StabilityFlags {
    public const OPTION = 'fieldflow_stability_flags';

    public static function defaults(): array {
        return [
            'contract_headers' => true,
            'client_diagnostics' => true,
            'contract_enforcement' => false,
            'forms_runtime_v3' => false,
            'mobile_api_v2' => false,
            'new_submission_engine' => false,
        ];
    }

    public static function all(): array {
        $saved = get_option(self::OPTION, []);
        $flags = wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
        foreach ($flags as $key => $value) $flags[$key] = (bool)$value;
        $filtered = apply_filters('fieldflow_stability_flags', $flags);
        return is_array($filtered) ? array_map('boolval', $filtered) : $flags;
    }

    public static function enabled(string $flag): bool {
        $flags = self::all();
        return array_key_exists($flag, $flags) && (bool)$flags[$flag];
    }

    public static function publicState(): array {
        $flags = self::all();
        return [
            'baseline' => '2.2.246',
            'compatibility_mode' => true,
            'contract_headers' => (bool)($flags['contract_headers'] ?? true),
            'client_diagnostics' => (bool)($flags['client_diagnostics'] ?? true),
            'contract_enforcement' => (bool)($flags['contract_enforcement'] ?? false),
            'forms_runtime_v3' => (bool)($flags['forms_runtime_v3'] ?? false),
            'mobile_api_v2' => (bool)($flags['mobile_api_v2'] ?? false),
            'new_submission_engine' => (bool)($flags['new_submission_engine'] ?? false),
            'forms_runtime_v3_state' => class_exists('\RoutesPro\Forms\RuntimeV3')
                ? \RoutesPro\Forms\RuntimeV3::publicState()
                : [],
            'submission_pipeline_state' => class_exists('\RoutesPro\Forms\SubmissionPipeline')
                ? \RoutesPro\Forms\SubmissionPipeline::publicState()
                : [],
        ];
    }
}
