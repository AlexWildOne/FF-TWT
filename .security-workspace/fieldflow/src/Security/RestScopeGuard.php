<?php
namespace RoutesPro\Security;

use RoutesPro\Support\Permissions;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) exit;

/**
 * Cross-tenant REST guard for explicit FieldFlow client/project context.
 *
 * This is intentionally additive and conservative: it only evaluates explicit
 * top-level client_id/project_id parameters on authenticated FieldFlow requests.
 * Existing route/object permission callbacks remain authoritative and continue
 * to provide the deeper object-level checks.
 */
final class RestScopeGuard {
    public const VERSION = '1.0.0';
    public const CONTRACT = 'fieldflow-rest-scope-isolation-v1';

    private static bool $registered = false;

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_filter('rest_pre_dispatch', [self::class, 'guard'], 6, 3);
    }

    public static function guard($result, $server, WP_REST_Request $request) {
        if ($result !== null || !is_user_logged_in()) return $result;
        $route = (string)$request->get_route();
        if (!self::isProtectedFieldFlowRoute($route) || self::isExcludedPublicRoute($route)) return $result;

        $clientId = absint($request->get_param('client_id'));
        $projectId = absint($request->get_param('project_id'));
        if ($clientId <= 0 && $projectId <= 0) return $result;

        $projectClientId = $projectId > 0 ? self::projectClientId($projectId) : 0;
        if ($projectId > 0 && $projectClientId <= 0) {
            return new WP_Error('fieldflow_scope_project_not_found', 'A campanha selecionada não existe.', ['status' => 404]);
        }

        $decision = self::decision(Permissions::get_scope(), $clientId, $projectId, $projectClientId);
        if (!empty($decision['allowed'])) return $result;

        if (class_exists(SecurityService::class)) {
            SecurityService::record('rest.scope_denied', 'high', [
                'route' => $route,
                'method' => $request->get_method(),
                'client_id' => $clientId,
                'project_id' => $projectId,
                'classification' => 'authorization_denied',
                'reason_code' => (string)($decision['reason'] ?? 'scope_denied'),
                'status' => 403,
            ]);
        }

        $code = (string)($decision['reason'] ?? 'fieldflow_scope_denied');
        $message = $code === 'fieldflow_scope_context_conflict'
            ? 'A campanha selecionada não pertence ao cliente indicado.'
            : 'Sem acesso ao âmbito selecionado.';
        return new WP_Error($code, $message, ['status' => 403]);
    }

    /**
     * Pure decision helper used by runtime and negative contract tests.
     */
    public static function decision(array $scope, int $clientId, int $projectId, int $projectClientId): array {
        if (!empty($scope['is_manager'])) return ['allowed' => true, 'reason' => 'manager'];

        $clientIds = array_values(array_filter(array_map('absint', (array)($scope['client_ids'] ?? []))));
        $projectIds = array_values(array_filter(array_map('absint', (array)($scope['project_ids'] ?? []))));

        if ($projectId > 0 && $projectClientId > 0 && $clientId > 0 && $clientId !== $projectClientId) {
            return ['allowed' => false, 'reason' => 'fieldflow_scope_context_conflict'];
        }
        if ($clientId > 0 && !in_array($clientId, $clientIds, true)) {
            return ['allowed' => false, 'reason' => 'fieldflow_scope_client_denied'];
        }
        if ($projectId > 0 && !in_array($projectId, $projectIds, true)) {
            return ['allowed' => false, 'reason' => 'fieldflow_scope_project_denied'];
        }
        if ($projectClientId > 0 && !in_array($projectClientId, $clientIds, true)) {
            return ['allowed' => false, 'reason' => 'fieldflow_scope_project_client_denied'];
        }
        return ['allowed' => true, 'reason' => 'allowed'];
    }

    public static function status(): array {
        return [
            'version' => self::VERSION,
            'contract' => self::CONTRACT,
            'registered' => self::$registered,
            'explicit_scope_guard' => true,
            'project_client_consistency' => true,
            'object_permissions_preserved' => true,
        ];
    }

    private static function projectClientId(int $projectId): int {
        global $wpdb;
        if ($projectId <= 0) return 0;
        return absint($wpdb->get_var($wpdb->prepare(
            'SELECT client_id FROM ' . $wpdb->prefix . 'routespro_projects WHERE id=%d LIMIT 1',
            $projectId
        )));
    }

    private static function isProtectedFieldFlowRoute(string $route): bool {
        return strpos($route, '/routespro/v1/') === 0
            || strpos($route, '/fieldflow/v1/') === 0
            || strpos($route, '/fieldflow-mobile/v1/') === 0;
    }

    private static function isExcludedPublicRoute(string $route): bool {
        return in_array($route, [
            '/fieldflow-mobile/v1/auth/login',
            '/fieldflow-mobile/v1/auth/refresh',
            '/fieldflow-mobile/v1/app-config',
            '/fieldflow-mobile/v1/health',
            '/fieldflow/v1/billing/signup/intent',
        ], true);
    }
}
