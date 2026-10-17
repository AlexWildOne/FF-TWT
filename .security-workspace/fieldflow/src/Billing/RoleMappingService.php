<?php
namespace RoutesPro\Billing;

use RoutesPro\Core\AuditLog;
use RoutesPro\Core\EventBus;
use RoutesPro\SaaS\PlanRepository;

if (!defined('ABSPATH')) exit;

class RoleMappingService {
    public const OPTION = 'routespro_billing_role_mappings';
    private static bool $registered = false;

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_filter('fieldflow_mobile_portal_access', [self::class, 'filterMobilePortalAccess'], 20, 4);
    }

    public static function defaults(): array {
        return [
            'plans' => [],
            'preserve_unmanaged_roles' => 1,
        ];
    }

    public static function get(): array {
        $saved = get_option(self::OPTION, []);
        $settings = array_replace_recursive(self::defaults(), is_array($saved) ? $saved : []);
        if (!isset($settings['plans']) || !is_array($settings['plans'])) $settings['plans'] = [];
        $settings['preserve_unmanaged_roles'] = 1;
        return $settings;
    }

    public static function save(array $input): array {
        $validRoles = array_fill_keys(array_keys(self::availableRoles()), true);
        $plans = [];
        foreach (PlanRepository::plans(false) as $plan) {
            $planId = (int)($plan['id'] ?? 0);
            if ($planId <= 0) continue;
            $row = (array)($input['plans'][$planId] ?? []);
            $active = sanitize_key((string)($row['active_role'] ?? ''));
            $pastDue = sanitize_key((string)($row['past_due_role'] ?? ''));
            $inactive = sanitize_key((string)($row['inactive_role'] ?? ''));
            $plans[$planId] = [
                'active_role' => isset($validRoles[$active]) ? $active : '',
                'past_due_role' => isset($validRoles[$pastDue]) ? $pastDue : '',
                'inactive_role' => isset($validRoles[$inactive]) ? $inactive : '',
            ];
        }
        $settings = [
            'plans' => $plans,
            'preserve_unmanaged_roles' => 1,
        ];
        update_option(self::OPTION, $settings, false);
        return $settings;
    }

    public static function availableRoles(): array {
        $roles = function_exists('wp_roles') ? wp_roles() : null;
        $out = [];
        if (!$roles || !is_array($roles->roles)) return $out;
        foreach ($roles->roles as $slug => $definition) {
            $capabilities = (array)($definition['capabilities'] ?? []);
            $protected = (string)$slug === 'administrator';
            foreach (['manage_options', 'promote_users', 'edit_users', 'delete_users', 'routespro_manage'] as $capability) {
                if (!empty($capabilities[$capability])) $protected = true;
            }
            if ($protected) continue;
            $out[(string)$slug] = translate_user_role((string)($definition['name'] ?? $slug));
        }
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }

    public static function mappingForPlan(int $planId): array {
        $settings = self::get();
        return array_replace([
            'active_role' => '',
            'past_due_role' => '',
            'inactive_role' => '',
        ], (array)($settings['plans'][$planId] ?? []));
    }

    public static function targetRole(int $planId, string $status): string {
        $mapping = self::mappingForPlan($planId);
        $status = sanitize_key($status);
        if (in_array($status, ['active', 'trial', 'trialing'], true)) return (string)$mapping['active_role'];
        if ($status === 'past_due') return (string)($mapping['past_due_role'] ?: $mapping['active_role']);
        return (string)$mapping['inactive_role'];
    }

    public static function managedRoles(): array {
        $settings = self::get();
        $roles = [];
        foreach ((array)$settings['plans'] as $mapping) {
            foreach (['active_role', 'past_due_role', 'inactive_role'] as $key) {
                $role = sanitize_key((string)($mapping[$key] ?? ''));
                if ($role !== '') $roles[$role] = $role;
            }
        }
        $pendingSettings = class_exists(Settings::class) ? Settings::get() : [];
        $pendingRole = sanitize_key((string)($pendingSettings['pending_role'] ?? ''));
        if ($pendingRole !== '') $roles[$pendingRole] = $pendingRole;
        return array_values($roles);
    }

    public static function userIdFromSubscription(array $subscription): int {
        $direct = absint($subscription['billing_user_id'] ?? 0);
        if ($direct > 0) return $direct;
        $meta = json_decode((string)($subscription['meta_json'] ?? ''), true);
        return is_array($meta) ? absint($meta['billing_user_id'] ?? $meta['wp_user_id'] ?? 0) : 0;
    }

    public static function syncSubscription(array $subscription, string $reason = ''): array {
        $userId = self::userIdFromSubscription($subscription);
        if ($userId <= 0) return ['changed' => false, 'reason' => 'no_user'];
        return self::syncUserFromSubscriptions($userId, $reason);
    }

    public static function syncClient(int $clientId, ?string $environment = null, string $reason = ''): array {
        $subscription = BillingRepository::subscriptionByClient($clientId, $environment ?: Settings::mode());
        if (!$subscription) return ['changed' => false, 'reason' => 'no_subscription'];
        return self::syncSubscription($subscription, $reason);
    }

    public static function syncAll(?string $environment = null): array {
        $userIds = method_exists(BillingRepository::class, 'subscriptionUserIds')
            ? BillingRepository::subscriptionUserIds($environment)
            : [];
        if (!$userIds) {
            $rows = BillingRepository::subscriptions(500, $environment ?: Settings::mode());
            foreach ($rows as $row) {
                $userId = self::userIdFromSubscription($row);
                if ($userId > 0) $userIds[$userId] = $userId;
            }
            $userIds = array_values($userIds);
        }
        $changed = 0;
        $skipped = 0;
        foreach ($userIds as $userId) {
            $result = self::syncUserFromSubscriptions((int)$userId, 'Sincronização manual em massa.');
            !empty($result['changed']) ? $changed++ : $skipped++;
        }
        return ['changed' => $changed, 'skipped' => $skipped, 'total' => count($userIds)];
    }

    public static function syncUserFromSubscriptions(int $userId, string $reason = ''): array {
        $subscriptions = self::subscriptionSetForUser($userId);
        if (!$subscriptions) return self::releaseUserRoles($userId, $reason ?: 'Sem subscrição associada.');
        return self::syncUserSubscriptionSet($userId, $subscriptions, $reason);
    }

    public static function syncUser(int $userId, int $planId, string $status, int $clientId = 0, string $reason = ''): array {
        if ($planId <= 0) return ['changed' => false, 'reason' => 'invalid_user_or_plan'];
        return self::syncUserSubscriptionSet($userId, [[
            'plan_id' => $planId,
            'status' => sanitize_key($status),
            'client_id' => $clientId,
            'environment' => class_exists(Settings::class) ? Settings::mode() : 'sandbox',
            'id' => 0,
        ]], $reason);
    }

    public static function releaseUser(int $userId, string $reason = ''): array {
        $subscriptions = self::subscriptionSetForUser($userId);
        if ($subscriptions) return self::syncUserSubscriptionSet($userId, $subscriptions, $reason);
        return self::releaseUserRoles($userId, $reason);
    }

    private static function subscriptionSetForUser(int $userId): array {
        if ($userId <= 0) return [];
        if (method_exists(BillingRepository::class, 'effectiveSubscriptionsForUser')) {
            return BillingRepository::effectiveSubscriptionsForUser($userId, false);
        }
        $preferred = BillingRepository::preferredSubscriptionForUser($userId);
        return $preferred ? [$preferred] : [];
    }

    private static function syncUserSubscriptionSet(int $userId, array $subscriptions, string $reason = ''): array {
        $user = get_userdata($userId);
        if (!$user || !$subscriptions) return ['changed' => false, 'reason' => 'invalid_user_or_subscriptions'];

        $activeStatuses = ['active','trial','trialing','past_due'];
        $active = [];
        $inactive = [];
        foreach ($subscriptions as $subscription) {
            $planId = absint($subscription['plan_id'] ?? 0);
            if ($planId <= 0) continue;
            $status = sanitize_key((string)($subscription['status'] ?? 'active'));
            $subscription['plan_id'] = $planId;
            $subscription['status'] = $status;
            if (in_array($status, $activeStatuses, true)) $active[] = $subscription;
            else $inactive[] = $subscription;
        }
        $effective = $active ?: (isset($inactive[0]) ? [$inactive[0]] : []);
        if (!$effective) return self::releaseUserRoles($userId, $reason ?: 'Sem subscrição válida.');

        $previousClientIds = array_values(array_unique(array_filter(array_map('absint', array_merge(
            (array)get_user_meta($userId, 'fieldflow_billing_client_ids', true),
            [get_user_meta($userId, 'fieldflow_billing_client_id', true)]
        )))));
        $targets = [];
        $planIds = [];
        $clientIds = [];
        $summaries = [];
        foreach ($effective as $subscription) {
            $planId = absint($subscription['plan_id'] ?? 0);
            $clientId = absint($subscription['client_id'] ?? 0);
            $status = sanitize_key((string)($subscription['status'] ?? 'active'));
            $target = sanitize_key(self::targetRole($planId, $status));
            if ($target !== '') $targets[$target] = $target;
            if ($planId > 0) $planIds[$planId] = $planId;
            if ($clientId > 0) {
                $clientIds[$clientId] = $clientId;
                self::ensureClientAssociation($clientId, $userId);
            }
            $summaries[] = [
                'subscription_id' => absint($subscription['id'] ?? 0),
                'client_id' => $clientId,
                'plan_id' => $planId,
                'status' => $status,
                'environment' => sanitize_key((string)($subscription['environment'] ?? '')),
                'role' => $target,
            ];
        }

        $previousRole = sanitize_key((string)get_user_meta($userId, 'fieldflow_billing_role', true));
        $previousRoles = array_values(array_filter(array_map('sanitize_key', (array)get_user_meta($userId, 'fieldflow_billing_roles', true))));
        $managed = self::managedRoles();
        if ($previousRole !== '') $managed[] = $previousRole;
        $managed = array_merge($managed, $previousRoles);
        $managed = array_values(array_unique(array_filter(array_map('sanitize_key', $managed))));
        $targets = array_values($targets);
        $before = array_values((array)$user->roles);

        foreach ($managed as $role) {
            if (!in_array($role, $targets, true) && in_array($role, (array)$user->roles, true)) $user->remove_role($role);
        }
        foreach ($targets as $target) {
            if (!in_array($target, (array)$user->roles, true)) $user->add_role($target);
        }

        $preferred = $effective[0];
        $preferredPlanId = absint($preferred['plan_id'] ?? 0);
        $preferredClientId = absint($preferred['client_id'] ?? 0);
        $preferredStatus = sanitize_key((string)($preferred['status'] ?? 'active'));
        $preferredRole = sanitize_key(self::targetRole($preferredPlanId, $preferredStatus));

        foreach (array_diff($previousClientIds, array_values($clientIds)) as $removedClientId) {
            self::detachUserFromClient((int)$removedClientId, $userId);
        }

        update_user_meta($userId, 'fieldflow_billing_plan_id', $preferredPlanId);
        update_user_meta($userId, 'fieldflow_billing_status', $preferredStatus);
        update_user_meta($userId, 'fieldflow_billing_client_id', $preferredClientId);
        update_user_meta($userId, 'fieldflow_billing_role', $preferredRole);
        update_user_meta($userId, 'fieldflow_billing_plan_ids', array_values($planIds));
        update_user_meta($userId, 'fieldflow_billing_client_ids', array_values($clientIds));
        update_user_meta($userId, 'fieldflow_billing_roles', $targets);
        update_user_meta($userId, 'fieldflow_billing_subscriptions', $summaries);
        update_user_meta($userId, 'fieldflow_billing_role_synced_at', current_time('mysql'));

        foreach ($active as $subscription) {
            if (in_array((string)$subscription['status'], ['active','trial','trialing'], true)) {
                delete_user_meta($userId, 'fieldflow_signup_pending_role');
                $signupTokenMeta = class_exists(SignupService::class) ? SignupService::USER_TOKEN_META : 'fieldflow_signup_intent_token';
                delete_user_meta($userId, $signupTokenMeta);
                break;
            }
        }

        $afterUser = get_userdata($userId);
        $after = $afterUser ? array_values((array)$afterUser->roles) : [];
        sort($before);
        sort($after);
        $changed = $before !== $after;
        if ($changed) self::auditRoleChange(
            $userId,
            $preferredClientId,
            $preferredPlanId,
            $preferredStatus,
            implode(',', $targets),
            $before,
            $after,
            $reason
        );
        return [
            'changed' => $changed,
            'target_role' => $preferredRole,
            'target_roles' => $targets,
            'client_ids' => array_values($clientIds),
            'plan_ids' => array_values($planIds),
            'subscriptions' => $summaries,
            'roles' => $after,
        ];
    }

    private static function releaseUserRoles(int $userId, string $reason = ''): array {
        $user = get_userdata($userId);
        if (!$user) return ['changed' => false, 'reason' => 'invalid_user'];
        $previousClientIds = array_values(array_unique(array_filter(array_map('absint', array_merge(
            (array)get_user_meta($userId, 'fieldflow_billing_client_ids', true),
            [get_user_meta($userId, 'fieldflow_billing_client_id', true)]
        )))));
        $previousRole = sanitize_key((string)get_user_meta($userId, 'fieldflow_billing_role', true));
        $previousRoles = array_values(array_filter(array_map('sanitize_key', (array)get_user_meta($userId, 'fieldflow_billing_roles', true))));
        $managed = self::managedRoles();
        if ($previousRole !== '') $managed[] = $previousRole;
        $managed = array_merge($managed, $previousRoles);
        $managed = array_values(array_unique(array_filter(array_map('sanitize_key', $managed))));
        $before = array_values((array)$user->roles);
        foreach ($managed as $role) {
            if (in_array($role, (array)$user->roles, true)) $user->remove_role($role);
        }
        foreach ($previousClientIds as $clientId) self::detachUserFromClient((int)$clientId, $userId);
        foreach ([
            'fieldflow_billing_plan_id', 'fieldflow_billing_status', 'fieldflow_billing_client_id',
            'fieldflow_billing_role', 'fieldflow_billing_plan_ids', 'fieldflow_billing_client_ids',
            'fieldflow_billing_roles', 'fieldflow_billing_subscriptions', 'fieldflow_billing_role_synced_at'
        ] as $key) delete_user_meta($userId, $key);
        $afterUser = get_userdata($userId);
        $after = $afterUser ? array_values((array)$afterUser->roles) : [];
        sort($before);
        sort($after);
        $changed = $before !== $after;
        if ($changed) self::auditRoleChange($userId, 0, 0, 'detached', '', $before, $after, $reason);
        return ['changed' => $changed, 'target_role' => '', 'target_roles' => [], 'roles' => $after];
    }

    public static function ensureClientAssociation(int $clientId, int $userId): bool {
        global $wpdb;
        if ($clientId <= 0 || $userId <= 0) return false;
        $table = $wpdb->prefix . 'routespro_clients';
        $raw = $wpdb->get_var($wpdb->prepare("SELECT meta_json FROM {$table} WHERE id=%d", $clientId));
        if ($raw === null) return false;
        $meta = json_decode((string)$raw, true);
        if (!is_array($meta)) $meta = [];
        $ids = [];
        foreach ((array)($meta['associated_user_ids'] ?? []) as $id) {
            $id = absint(is_array($id) ? ($id['user_id'] ?? $id['id'] ?? 0) : $id);
            if ($id > 0) $ids[$id] = $id;
        }
        if (isset($ids[$userId])) return true;
        $ids[$userId] = $userId;
        ksort($ids);
        $meta['associated_user_ids'] = array_values($ids);
        $updated = $wpdb->update($table, ['meta_json' => wp_json_encode($meta)], ['id' => $clientId], ['%s'], ['%d']) !== false;
        if ($updated) {
            $auto = array_map('absint', (array)get_user_meta($userId, 'fieldflow_billing_auto_client_ids', true));
            $auto[$clientId] = $clientId;
            update_user_meta($userId, 'fieldflow_billing_auto_client_ids', array_values(array_unique(array_filter($auto))));
        }
        return $updated;
    }

    public static function detachUserFromClient(int $clientId, int $userId): bool {
        global $wpdb;
        if ($clientId <= 0 || $userId <= 0) return false;
        if (method_exists(BillingRepository::class, 'hasActiveSubscriptionForUserClient') && BillingRepository::hasActiveSubscriptionForUserClient($userId, $clientId)) return false;
        if (!method_exists(BillingRepository::class, 'hasActiveSubscriptionForUserClient') && BillingRepository::hasSubscriptionForUserClient($userId, $clientId)) return false;
        $auto = array_values(array_unique(array_filter(array_map('absint', (array)get_user_meta($userId, 'fieldflow_billing_auto_client_ids', true)))));
        if (!in_array($clientId, $auto, true)) return false;
        $table = $wpdb->prefix . 'routespro_clients';
        $raw = $wpdb->get_var($wpdb->prepare("SELECT meta_json FROM {$table} WHERE id=%d", $clientId));
        if ($raw === null) return false;
        $meta = json_decode((string)$raw, true);
        if (!is_array($meta)) $meta = [];
        $ids = [];
        foreach ((array)($meta['associated_user_ids'] ?? []) as $id) {
            $id = absint(is_array($id) ? ($id['user_id'] ?? $id['id'] ?? 0) : $id);
            if ($id > 0 && $id !== $userId) $ids[$id] = $id;
        }
        $meta['associated_user_ids'] = array_values($ids);
        $updated = $wpdb->update($table, ['meta_json' => wp_json_encode($meta)], ['id' => $clientId], ['%s'], ['%d']) !== false;
        if ($updated) {
            $auto = array_values(array_filter($auto, static fn(int $id): bool => $id !== $clientId));
            if ($auto) update_user_meta($userId, 'fieldflow_billing_auto_client_ids', $auto);
            else delete_user_meta($userId, 'fieldflow_billing_auto_client_ids');
        }
        return $updated;
    }

    public static function filterMobilePortalAccess(array $payload, int $userId, array $scope, array $roles): array {
        $managed = self::managedRoles();
        $hasMappedRole = $managed && (bool)array_intersect($managed, array_map('sanitize_key', $roles));
        $billingClientIds = array_values(array_unique(array_filter(array_map('absint', array_merge(
            (array)get_user_meta($userId, 'fieldflow_billing_client_ids', true),
            [get_user_meta($userId, 'fieldflow_billing_client_id', true)]
        )))));
        $scopeClientIds = array_values(array_unique(array_filter(array_map('absint', (array)($scope['client_ids'] ?? [])))));
        $isBillingUser = (bool)array_intersect($billingClientIds, $scopeClientIds);
        if (!$hasMappedRole && !$isBillingUser) return $payload;
        if (!$scopeClientIds) return $payload;

        $payload['client'] = true;
        if (\RoutesPro\Mobile\Settings::target_enabled('client') && !in_array('client', (array)($payload['allowed_targets'] ?? []), true)) {
            $payload['allowed_targets'][] = 'client';
        }
        $billingAllowed = \RoutesPro\Mobile\Settings::target_enabled('billing')
            && ($isBillingUser || !class_exists('\\RoutesPro\\SaaS\\EntitlementService') || \RoutesPro\SaaS\EntitlementService::userHasAny('billing.view', $userId));
        if ($billingAllowed) {
            $payload['billing'] = true;
            if (!in_array('billing', (array)($payload['allowed_targets'] ?? []), true)) $payload['allowed_targets'][] = 'billing';
        }
        if (empty($payload['default_target'])) $payload['default_target'] = 'client';
        return $payload;
    }

    private static function auditRoleChange(int $userId, int $clientId, int $planId, string $status, string $target, array $before, array $after, string $reason): void {
        if (class_exists(AuditLog::class)) AuditLog::record(
            'billing.user_role_synced',
            'user',
            $userId,
            ['roles' => $before],
            ['roles' => $after],
            ['actor_user_id' => get_current_user_id()],
            ['client_id' => $clientId, 'plan_id' => $planId, 'status' => $status, 'target_role' => $target, 'reason' => $reason]
        );
        if (class_exists(EventBus::class)) EventBus::emit('billing.user_role_synced', [
            'user_id' => $userId,
            'client_id' => $clientId,
            'plan_id' => $planId,
            'status' => $status,
            'target_role' => $target,
            'reason' => $reason,
        ], ['entity_type' => 'user', 'entity_id' => $userId, 'client_id' => $clientId, 'actor_user_id' => get_current_user_id()], 'billing');
    }
}
