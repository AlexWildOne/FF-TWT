<?php
namespace RoutesPro\Localization;

use RoutesPro\Support\Permissions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) exit;

final class LanguageController {
    public function register_routes(): void {
        register_rest_route('fieldflow/v1', '/language', [
            [
                'methods'=>'GET',
                'callback'=>[$this, 'read'],
                'permission_callback'=>[$this, 'permission'],
            ],
            [
                'methods'=>'POST',
                'callback'=>[$this, 'save'],
                'permission_callback'=>[$this, 'permission'],
                'args'=>[
                    'locale'=>['required'=>true, 'sanitize_callback'=>'sanitize_text_field'],
                    'client_id'=>['required'=>false, 'sanitize_callback'=>'absint'],
                ],
            ],
        ]);
        register_rest_route('fieldflow/v1', '/language/translate', [
            [
                'methods'=>'POST',
                'callback'=>[$this, 'translate'],
                'permission_callback'=>[$this, 'permission'],
            ],
        ]);
    }

    public function permission() {
        if (!is_user_logged_in()) return new WP_Error('fieldflow_i18n_auth_required', 'É necessário iniciar sessão.', ['status'=>401]);
        return true;
    }

    public function read(WP_REST_Request $request): WP_REST_Response {
        $clientId = absint($request->get_param('client_id'));
        if ($clientId > 0 && !$this->canUseClient($clientId)) $clientId = 0;
        return new WP_REST_Response(LanguageService::runtimeConfig('front', $clientId), 200);
    }

    public function save(WP_REST_Request $request) {
        $clientId = absint($request->get_param('client_id'));
        if ($clientId > 0 && !$this->canUseClient($clientId)) {
            return new WP_Error('fieldflow_i18n_scope_denied', 'Sem acesso ao cliente indicado.', ['status'=>403]);
        }
        $locale = LanguageService::normaliseLocale((string)$request->get_param('locale'));
        if ($locale === '') return new WP_Error('fieldflow_i18n_locale_invalid', 'Idioma inválido.', ['status'=>400]);
        $client = LanguageService::clientConfig($clientId);
        if ($clientId > 0 && empty($client['allow_user_switch']) && !Permissions::is_manager()) {
            return new WP_Error('fieldflow_i18n_switch_locked', 'A alteração de idioma está bloqueada para este cliente.', ['status'=>403]);
        }
        if (!in_array($locale, (array)$client['enabled_locales'], true)) {
            return new WP_Error('fieldflow_i18n_locale_disabled', 'Este idioma não está disponível para o cliente.', ['status'=>400]);
        }
        if (!LanguageService::persistPreference(get_current_user_id(), $locale, $clientId)) {
            return new WP_Error('fieldflow_i18n_save_failed', 'Não foi possível guardar a preferência de idioma.', ['status'=>500]);
        }
        return new WP_REST_Response(LanguageService::runtimeConfig('front', $clientId), 200);
    }


    public function translate(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) $params = [];
        $clientId = absint($params['client_id'] ?? $request->get_param('client_id'));
        if ($clientId > 0 && !$this->canUseClient($clientId)) {
            return new WP_Error('fieldflow_i18n_scope_denied', 'Sem acesso ao cliente indicado.', ['status'=>403]);
        }
        $locale = LanguageService::normaliseLocale((string)($params['locale'] ?? $request->get_param('locale')));
        if ($locale === '' || !isset(LanguageService::supportedLocales()[$locale])) {
            return new WP_Error('fieldflow_i18n_locale_invalid', 'Idioma inválido.', ['status'=>400]);
        }
        $client = LanguageService::clientConfig($clientId);
        if ($clientId > 0 && !in_array($locale, (array)$client['enabled_locales'], true)) {
            return new WP_Error('fieldflow_i18n_locale_disabled', 'Este idioma não está disponível para o cliente.', ['status'=>400]);
        }
        $texts = is_array($params['texts'] ?? null) ? $params['texts'] : [];
        if (!$texts) return new WP_REST_Response(['translations'=>[], 'provider'=>'empty'], 200);
        $rateKey = 'fieldflow_i18n_translate_' . get_current_user_id() . '_' . gmdate('YmdHi');
        $hits = (int)get_transient($rateKey);
        if ($hits >= 30) return new WP_Error('fieldflow_i18n_rate_limited', 'Demasiados pedidos de tradução.', ['status'=>429]);
        set_transient($rateKey, $hits + 1, MINUTE_IN_SECONDS);
        $result = TranslationService::translateBatch($texts, $locale, $clientId);
        $result['contract'] = TranslationService::CONTRACT;
        return new WP_REST_Response($result, 200);
    }

    private function canUseClient(int $clientId): bool {
        if ($clientId <= 0) return true;
        if (Permissions::is_manager()) return true;
        if (Permissions::is_allowed_client($clientId)) return true;
        $scope = Permissions::get_scope();
        return in_array($clientId, array_map('absint', (array)($scope['client_ids'] ?? [])), true);
    }
}
