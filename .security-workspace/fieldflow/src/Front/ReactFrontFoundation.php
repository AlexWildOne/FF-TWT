<?php
namespace RoutesPro\Front;

if (!defined('ABSPATH')) exit;

/**
 * Progressive React islands for the operational front-end.
 *
 * The legacy/PHP/typed runtimes remain canonical. React enhances bounded
 * presentation and interaction surfaces while submission, validation, media,
 * offline recovery and REST contracts stay owned by the certified runtimes.
 */
final class ReactFrontFoundation {
    public const MODERNIZATION_CLOSURE_CONTRACT = 'fieldflow-modernization-stable-closure-v1';
    public const FULLWIDTH_NAVIGATION_CONTRACT = 'fieldflow-front-fullwidth-navigation-v1';
    public const CONTRACT = 'fieldflow-react-front-foundation-v1';
    public const LOCALE_SWITCHER_CONTRACT = 'fieldflow-react-locale-switcher-v1';
    public const FORM_WIZARD_CONTRACT = 'fieldflow-react-form-wizard-progress-v1';
    public const FORM_WIZARD_ISLAND = 'form-wizard-progress';
    public const OPERATIONAL_SHELL_CONTRACT = 'fieldflow-react-operational-shell-v1';
    public const OPERATIONAL_SHELL_ISLAND = 'operational-shell';
    public const OPERATIONAL_MODULES_CONTRACT = 'fieldflow-react-operational-modules-v1';
    public const OPERATIONAL_MODULES_V2_CONTRACT = 'fieldflow-react-operational-modules-v2';
    public const OPERATIONAL_MODERNIZATION_CONTRACT = 'fieldflow-operational-modernization-wave-v1';
    public const OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT = 'fieldflow-operational-modernization-wave2-v1';
    public const CLIENT_WAVE1_CONTRACT = 'fieldflow-react-client-wave1-v1';
    public const CLIENT_WAVE2_CONTRACT = 'fieldflow-react-client-wave2-v1';
    public const CROSS_PORTAL_ISOLATION_CONTRACT = 'fieldflow-cross-portal-render-isolation-v1';
    public const DUAL_PORTAL_LAYOUT_CONTRACT = 'fieldflow-dual-portal-true-fullwidth-v1';
    public const APP_RESUME_PAINT_GUARD_CONTRACT = 'fieldflow-app-resume-paint-guard-v1';
    public const DUAL_PORTAL_FULLWIDTH_PARITY_CONTRACT = 'fieldflow-dual-portal-fullwidth-parity-v2';
    public const APP_RESUME_PAINT_GUARD_V2_CONTRACT = 'fieldflow-app-resume-paint-guard-v2';
    public const UI_COMPOSITION_CONTRACT = 'fieldflow-ui-composition-ownership-v1';
    public const RESUME_DIAGNOSTICS_CONTRACT = 'fieldflow-resume-diagnostics-v1';
    public const PORTAL_PAINT_STABILITY_CONTRACT = 'fieldflow-shared-portal-paint-stability-v1';
    public const OPERATIONAL_MODULES_ISLAND = 'operational-module-controls';
    public const MY_ROUTE_CONTRACT = 'fieldflow-react-my-route-toolbar-v1';
    public const MY_ROUTE_ISLAND = 'my-route-toolbar';
    public const SIMPLE_FIELD_CONTRACT = 'fieldflow-react-simple-field-assist-v1';
    public const SIMPLE_FIELD_CONTROL_CONTRACT = 'fieldflow-react-simple-field-control-v1';
    public const SIMPLE_FIELD_ISLAND = 'simple-field-assist';
    public const CHOICE_FIELD_CONTRACT = 'fieldflow-react-choice-field-control-v1';
    public const CHOICE_FIELD_ISLAND = 'choice-field-control';
    public const SPECIAL_FIELD_CONTRACT = 'fieldflow-react-special-field-assist-v1';
    public const MEDIA_CONTROL_CONTRACT = 'fieldflow-react-media-control-v1';
    public const FORM_COMPLETION_CONTRACT = 'fieldflow-react-form-completion-v1';
    public const FRONT_TRANSITION_STABILITY_CONTRACT = 'fieldflow-front-transition-stability-v1';
    public const SPECIAL_FIELD_ISLAND = 'special-field-assist';

    public static function enabled(): bool {
        if (defined('FIELDFLOW_REACT_FRONT') && !FIELDFLOW_REACT_FRONT) return false;
        return true;
    }

    public static function islandEnabled(string $island): bool {
        if (!self::enabled()) return false;
        return in_array(sanitize_key($island), [
            self::FORM_WIZARD_ISLAND,
            self::OPERATIONAL_SHELL_ISLAND,
            self::OPERATIONAL_MODULES_ISLAND,
            self::MY_ROUTE_ISLAND,
            self::SIMPLE_FIELD_ISLAND,
            self::CHOICE_FIELD_ISLAND,
            self::SPECIAL_FIELD_ISLAND,
        ], true);
    }

    public static function operationalModuleMount(string $rootId, string $module, string $label = ''): string {
        $module = sanitize_key($module);
        if (!self::islandEnabled(self::OPERATIONAL_MODULES_ISLAND)) return '';
        if (!in_array($module, ['briefings','implementations','stocks','shopping','planner','expenses','messages','academy'], true)) return '';
        $props = [
            'contract' => self::CONTRACT,
            'operationalModulesContract' => self::OPERATIONAL_MODULES_CONTRACT,
            'operationalModulesV2Contract' => self::OPERATIONAL_MODULES_V2_CONTRACT,
            'operationalModernizationContract' => in_array($module, ['briefings','stocks','shopping','messages'], true) ? self::OPERATIONAL_MODERNIZATION_CONTRACT : '',
            'operationalModernizationWave2Contract' => in_array($module, ['academy','expenses'], true) ? self::OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT : '',
            'clientWave1Contract' => in_array($module, ['stocks','shopping','messages'], true) ? self::CLIENT_WAVE1_CONTRACT : '',
            'clientWave2Contract' => in_array($module, ['briefings','implementations','planner','expenses'], true) ? self::CLIENT_WAVE2_CONTRACT : '',
            'crossPortalIsolationContract' => self::CROSS_PORTAL_ISOLATION_CONTRACT,
            'island' => self::OPERATIONAL_MODULES_ISLAND,
            'rootId' => sanitize_html_class($rootId),
            'module' => $module,
            'label' => sanitize_text_field($label),
        ];
        return sprintf(
            '<div class="ff-react-operational-module-root" data-fieldflow-react-front-island="%1$s" data-fieldflow-react-front-props="%2$s"></div>',
            esc_attr(self::OPERATIONAL_MODULES_ISLAND),
            esc_attr((string)wp_json_encode($props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
        );
    }

    public static function simpleFieldTypeEnabled(string $type): bool {
        return self::islandEnabled(self::SIMPLE_FIELD_ISLAND)
            && in_array(sanitize_key($type), ['text','textarea','number','currency','percent','date','time','select'], true);
    }

    public static function choiceFieldTypeEnabled(string $type, bool $multiple = false): bool {
        $type = sanitize_key($type);
        if (!self::islandEnabled(self::CHOICE_FIELD_ISLAND)) return false;
        if ($type === 'checkbox' || $type === 'radio' || $type === 'visual_choice') return true;
        return $type === 'select' && $multiple;
    }

    public static function specialFieldTypeEnabled(string $type): bool {
        return self::islandEnabled(self::SPECIAL_FIELD_ISLAND)
            && in_array(sanitize_key($type), ['product_matrix','share_space_matrix','order_suggestion','image_upload','categorized_image_upload','file_upload'], true);
    }

    public static function status(): array {
        return [
            'modernization_closure_contract' => self::MODERNIZATION_CLOSURE_CONTRACT,
            'fullwidth_navigation_contract' => self::FULLWIDTH_NAVIGATION_CONTRACT,
            'contract' => self::CONTRACT,
            'locale_switcher_contract' => self::LOCALE_SWITCHER_CONTRACT,
            'form_wizard_contract' => self::FORM_WIZARD_CONTRACT,
            'form_wizard_island' => self::FORM_WIZARD_ISLAND,
            'operational_shell_contract' => self::OPERATIONAL_SHELL_CONTRACT,
            'operational_shell_island' => self::OPERATIONAL_SHELL_ISLAND,
            'operational_modules_contract' => self::OPERATIONAL_MODULES_CONTRACT,
            'operational_modules_v2_contract' => self::OPERATIONAL_MODULES_V2_CONTRACT,
            'operational_modernization_contract' => self::OPERATIONAL_MODERNIZATION_CONTRACT,
            'operational_modernization_wave2_contract' => self::OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT,
            'client_wave1_contract' => self::CLIENT_WAVE1_CONTRACT,
            'client_wave2_contract' => self::CLIENT_WAVE2_CONTRACT,
            'cross_portal_isolation_contract' => self::CROSS_PORTAL_ISOLATION_CONTRACT,
            'dual_portal_layout_contract' => self::DUAL_PORTAL_LAYOUT_CONTRACT,
            'app_resume_paint_guard_contract' => self::APP_RESUME_PAINT_GUARD_CONTRACT,
            'dual_portal_fullwidth_parity_contract' => self::DUAL_PORTAL_FULLWIDTH_PARITY_CONTRACT,
            'app_resume_paint_guard_v2_contract' => self::APP_RESUME_PAINT_GUARD_V2_CONTRACT,
            'ui_composition_contract' => self::UI_COMPOSITION_CONTRACT,
            'resume_diagnostics_contract' => self::RESUME_DIAGNOSTICS_CONTRACT,
            'portal_paint_stability_contract' => self::PORTAL_PAINT_STABILITY_CONTRACT,
            'operational_modules_island' => self::OPERATIONAL_MODULES_ISLAND,
            'my_route_contract' => self::MY_ROUTE_CONTRACT,
            'my_route_island' => self::MY_ROUTE_ISLAND,
            'simple_field_contract' => self::SIMPLE_FIELD_CONTRACT,
            'simple_field_control_contract' => self::SIMPLE_FIELD_CONTROL_CONTRACT,
            'simple_field_island' => self::SIMPLE_FIELD_ISLAND,
            'choice_field_contract' => self::CHOICE_FIELD_CONTRACT,
            'choice_field_island' => self::CHOICE_FIELD_ISLAND,
            'special_field_contract' => self::SPECIAL_FIELD_CONTRACT,
            'media_control_contract' => self::MEDIA_CONTROL_CONTRACT,
            'form_completion_contract' => self::FORM_COMPLETION_CONTRACT,
            'front_transition_stability_contract' => self::FRONT_TRANSITION_STABILITY_CONTRACT,
            'special_field_island' => self::SPECIAL_FIELD_ISLAND,
            'enabled' => self::enabled(),
            'script_ready' => is_file(ROUTESPRO_PATH . 'assets/fieldflow-react-front-' . FIELDFLOW_VERSION . '.js'),
            'style_ready' => is_file(ROUTESPRO_PATH . 'assets/fieldflow-react-front.css'),
            'wordpress_react' => 'wp-element',
            'php_fallback' => true,
            'submission_owner' => 'legacy_typed_runtime',
            'conditional_owner' => 'fieldflow-typed-form-state-conditional-core-v1',
            'offline_owner' => 'fieldflow_offline_runtime',
            'route_data_owner' => 'routespro_rest_runtime',
        ];
    }
}
