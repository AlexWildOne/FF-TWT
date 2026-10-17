<?php
namespace RoutesPro\Mobile;

use RoutesPro\Billing\BillingRepository;
use RoutesPro\Billing\BillingService;
use RoutesPro\Billing\Settings as BillingSettings;
use RoutesPro\Billing\SignupService;
use RoutesPro\SaaS\PlanRepository;
use RoutesPro\Support\Permissions;

if (!defined('ABSPATH')) exit;

/**
 * Canonical mobile contract for subscription and billing screens.
 *
 * FlutterFlow consumes this contract to decide whether to show the plan
 * catalogue, active-plan summary, payment recovery, suspension or scheduled
 * cancellation without duplicating billing rules inside the application.
 */
class SubscriptionExperience {
    public const CONTRACT_VERSION = '1.0';

    public static function build(int $userId, int $requestedClientId = 0): array {
        $clients = self::clientsForUser($userId);
        $selectedClientId = self::selectedClientId($clients, $requestedClientId, $userId);
        $selected = null;
        foreach ($clients as $client) {
            if ((int)($client['client_id'] ?? 0) === $selectedClientId) {
                $selected = $client;
                break;
            }
        }

        $hasAny = false;
        $hasActive = false;
        foreach ($clients as $client) {
            if (!empty($client['has_subscription'])) $hasAny = true;
            if (in_array((string)($client['state'] ?? ''), ['active','trial','cancelling','payment_pending'], true)) $hasActive = true;
        }

        $subscribeEnabled = Settings::target_enabled('subscriptions')
            && class_exists(SignupService::class)
            && SignupService::isEnabled();
        $manageEnabled = Settings::target_enabled('billing') && !empty($selected['can_manage_billing']);
        $screen = $selected ? (string)$selected['screen'] : 'plans';
        $state = $selected ? (string)$selected['state'] : 'no_subscription';

        $primary = self::primaryAction($selected, $subscribeEnabled, $manageEnabled);
        $secondary = self::secondaryAction($selected, $subscribeEnabled, $manageEnabled, $primary);

        return [
            'contract_version' => self::CONTRACT_VERSION,
            'environment' => class_exists(BillingSettings::class) ? BillingSettings::mode() : 'sandbox',
            'selected_client_id' => $selectedClientId,
            'selected_client' => $selected,
            'clients' => array_values($clients),
            'client_count' => count($clients),
            'requires_client_selection' => count($clients) > 1,
            'has_any_subscription' => $hasAny,
            'has_active_subscription' => $hasActive,
            'state' => $state,
            'screen' => $screen,
            'state_label' => self::stateLabel($state),
            'state_message' => self::stateMessage($state, $selected),
            'subscribe_enabled' => $subscribeEnabled,
            'manage_enabled' => $manageEnabled,
            'primary_action' => $primary,
            'secondary_action' => $secondary,
            'targets' => [
                'plans' => 'subscriptions',
                'manage' => 'billing',
            ],
            'urls' => [
                'signup_page' => class_exists(BillingSettings::class) ? BillingSettings::signupUrl() : '',
                'billing_page' => class_exists(BillingSettings::class) ? BillingSettings::portalUrl() : '',
                'experience_endpoint' => rest_url(Controller::NS . '/billing/experience'),
                'web_session_endpoint' => rest_url(Controller::NS . '/auth/web-session'),
                'me_endpoint' => rest_url(Controller::NS . '/me'),
            ],
            'refresh' => [
                'after_checkout_seconds' => 3,
                'poll_seconds' => max(30, Settings::context_sync_seconds()),
                'refresh_me' => true,
                'refresh_experience' => true,
                'refresh_roles' => true,
                'refresh_navigation' => true,
            ],
            'catalog' => self::catalog(),
            'generated_at' => gmdate(DATE_ATOM),
        ];
    }

    private static function clientsForUser(int $userId): array {
        global $wpdb;
        if ($userId <= 0) return [];

        $manager = Permissions::is_manager($userId);
        $ids = [];
        if (!$manager) {
            $scope = Permissions::get_scope($userId);
            foreach ((array)($scope['client_ids'] ?? []) as $id) {
                $id = absint($id);
                if ($id > 0) $ids[$id] = $id;
            }
            foreach (array_merge(
                (array)get_user_meta($userId, 'fieldflow_billing_client_ids', true),
                [get_user_meta($userId, 'fieldflow_billing_client_id', true), get_user_meta($userId, SignupService::USER_CLIENT_META, true)]
            ) as $id) {
                $id = absint($id);
                if ($id > 0) $ids[$id] = $id;
            }
            if (class_exists(BillingRepository::class)) {
                foreach (BillingRepository::effectiveSubscriptionsForUser($userId, false) as $subscription) {
                    $id = absint($subscription['client_id'] ?? 0);
                    if ($id > 0) $ids[$id] = $id;
                }
            }
            if (!$ids) return [];
        }

        $environment = class_exists(BillingSettings::class) ? BillingSettings::mode() : 'sandbox';
        $sql = "SELECT c.id client_id,c.name client_name,c.email client_email,
                       s.id subscription_id,s.plan_id,s.billing_interval,s.status,s.environment,
                       s.current_period_start,s.current_period_end,s.trial_end,s.cancel_at_period_end,
                       s.grace_until,s.latest_invoice_id,s.external_customer_id,s.external_subscription_id,
                       p.name plan_name,p.slug plan_slug,p.currency
                FROM {$wpdb->prefix}routespro_clients c
                LEFT JOIN " . BillingRepository::subscriptionsTable() . " s ON s.id=(
                    SELECT s2.id FROM " . BillingRepository::subscriptionsTable() . " s2
                    WHERE s2.client_id=c.id AND s2.environment=%s
                    ORDER BY CASE
                      WHEN s2.status IN ('active','trial','trialing') THEN 0
                      WHEN s2.status IN ('past_due','incomplete','unpaid') THEN 1
                      WHEN s2.status='suspended' THEN 2
                      WHEN s2.status IN ('cancelled','canceled') THEN 3
                      WHEN s2.status='expired' THEN 4
                      ELSE 5 END ASC,
                      s2.id DESC LIMIT 1
                )
                LEFT JOIN {$wpdb->prefix}routespro_saas_plans p ON p.id=s.plan_id";
        $args = [$environment];
        if (!$manager) {
            $clientIds = array_values($ids);
            $sql .= ' WHERE c.id IN (' . implode(',', array_fill(0, count($clientIds), '%d')) . ')';
            $args = array_merge($args, $clientIds);
        }
        $sql .= ' ORDER BY c.name ASC LIMIT 1000';
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];

        $out = [];
        foreach ($rows as $row) $out[] = self::normalizeClient($row, $userId);
        return $out;
    }

    private static function normalizeClient(array $row, int $userId): array {
        $rawStatus = sanitize_key((string)($row['status'] ?? ''));
        $hasSubscription = absint($row['subscription_id'] ?? 0) > 0;
        $state = self::stateFromSubscription($rawStatus, !empty($row['cancel_at_period_end']), $hasSubscription);
        $clientId = absint($row['client_id'] ?? 0);
        $canAccess = $clientId > 0 && (Permissions::is_manager($userId) || Permissions::is_allowed_client($clientId, $userId));
        $canManage = $canAccess && $hasSubscription && in_array($state, ['active','trial','cancelling','payment_pending','suspended'], true);

        return [
            'client_id' => $clientId,
            'client_name' => (string)($row['client_name'] ?? ''),
            'client_email' => sanitize_email((string)($row['client_email'] ?? '')),
            'has_subscription' => $hasSubscription,
            'subscription_id' => absint($row['subscription_id'] ?? 0),
            'plan_id' => absint($row['plan_id'] ?? 0),
            'plan_name' => (string)($row['plan_name'] ?? ''),
            'plan_slug' => sanitize_key((string)($row['plan_slug'] ?? '')),
            'billing_interval' => (string)($row['billing_interval'] ?? 'month') === 'year' ? 'year' : 'month',
            'billing_interval_label' => (string)($row['billing_interval'] ?? 'month') === 'year' ? 'Anual' : 'Mensal',
            'raw_status' => $rawStatus,
            'state' => $state,
            'screen' => self::screenForState($state),
            'state_label' => self::stateLabel($state),
            'state_message' => self::stateMessage($state, $row),
            'severity' => self::severityForState($state),
            'currency' => strtoupper((string)($row['currency'] ?? 'EUR')),
            'current_period_start' => self::atom($row['current_period_start'] ?? ''),
            'current_period_end' => self::atom($row['current_period_end'] ?? ''),
            'trial_end' => self::atom($row['trial_end'] ?? ''),
            'grace_until' => self::atom($row['grace_until'] ?? ''),
            'cancel_at_period_end' => !empty($row['cancel_at_period_end']),
            'can_manage_billing' => $canManage,
            'can_view_plans' => Settings::target_enabled('subscriptions') && SignupService::isEnabled(),
            'actions' => [
                'manage' => $canManage ? ['enabled'=>true,'target'=>'billing','client_id'=>$clientId] : ['enabled'=>false,'target'=>'billing','client_id'=>$clientId],
                'plans' => ['enabled'=>Settings::target_enabled('subscriptions') && SignupService::isEnabled(),'target'=>'subscriptions','client_id'=>$clientId],
            ],
        ];
    }

    private static function selectedClientId(array $clients, int $requestedClientId, int $userId): int {
        $allowed = [];
        foreach ($clients as $client) $allowed[(int)($client['client_id'] ?? 0)] = $client;
        $candidates = [
            $requestedClientId,
            absint(get_user_meta($userId, 'fieldflow_billing_client_id', true)),
            absint(get_user_meta($userId, SignupService::USER_CLIENT_META, true)),
        ];
        foreach ($candidates as $candidate) if ($candidate > 0 && isset($allowed[$candidate])) return $candidate;
        foreach ($clients as $client) if (in_array((string)($client['state'] ?? ''), ['active','trial','cancelling','payment_pending'], true)) return (int)$client['client_id'];
        return (int)($clients[0]['client_id'] ?? 0);
    }

    private static function stateFromSubscription(string $status, bool $cancelAtPeriodEnd, bool $hasSubscription): string {
        if (!$hasSubscription) return 'no_subscription';
        if ($cancelAtPeriodEnd && in_array($status, ['active','trial','trialing'], true)) return 'cancelling';
        if ($status === 'trial' || $status === 'trialing') return 'trial';
        if ($status === 'active') return 'active';
        if (in_array($status, ['past_due','incomplete','unpaid'], true)) return 'payment_pending';
        if ($status === 'suspended') return 'suspended';
        if (in_array($status, ['cancelled','canceled'], true)) return 'cancelled';
        if ($status === 'expired') return 'expired';
        return 'inactive';
    }

    private static function screenForState(string $state): string {
        switch ($state) {
            case 'active':
            case 'trial': return 'active_plan';
            case 'cancelling': return 'cancellation_scheduled';
            case 'payment_pending': return 'payment_pending';
            case 'suspended': return 'subscription_suspended';
            case 'cancelled':
            case 'expired':
            case 'inactive': return 'subscription_inactive';
            default: return 'plans';
        }
    }

    private static function severityForState(string $state): string {
        if (in_array($state, ['active','trial'], true)) return 'success';
        if (in_array($state, ['cancelling','payment_pending'], true)) return 'warning';
        if (in_array($state, ['suspended','cancelled','expired'], true)) return 'danger';
        return 'neutral';
    }

    public static function stateLabel(string $state): string {
        switch ($state) {
            case 'active': return 'Subscrição ativa';
            case 'trial': return 'Período de teste';
            case 'cancelling': return 'Cancelamento agendado';
            case 'payment_pending': return 'Pagamento pendente';
            case 'suspended': return 'Subscrição suspensa';
            case 'cancelled': return 'Subscrição cancelada';
            case 'expired': return 'Subscrição expirada';
            case 'inactive': return 'Subscrição inativa';
            default: return 'Sem subscrição';
        }
    }

    private static function stateMessage(string $state, ?array $subscription): string {
        $end = self::humanDate($subscription['current_period_end'] ?? '');
        $grace = self::humanDate($subscription['grace_until'] ?? '');
        switch ($state) {
            case 'active': return $end !== '' ? 'O plano está ativo e renova em ' . $end . '.' : 'O plano está ativo.';
            case 'trial': return $end !== '' ? 'O período de teste termina em ' . $end . '.' : 'O período de teste está ativo.';
            case 'cancelling': return $end !== '' ? 'O acesso mantém-se até ' . $end . ', data prevista para o cancelamento.' : 'O cancelamento está agendado para o fim do período atual.';
            case 'payment_pending': return $grace !== '' ? 'Atualiza o pagamento antes de ' . $grace . ' para evitar a suspensão.' : 'Existe um pagamento pendente. Atualiza o método de pagamento.';
            case 'suspended': return 'O acesso pago está suspenso. Abre a faturação para regularizar ou reativar.';
            case 'cancelled': return 'A subscrição foi cancelada. Os dados permanecem guardados e podes escolher um novo plano.';
            case 'expired': return 'A subscrição expirou. Escolhe um plano para recuperar as capacidades pagas.';
            case 'inactive': return 'A subscrição não está ativa. Consulta a faturação ou escolhe outro plano.';
            default: return 'Escolhe um plano mensal ou anual para ativar o FieldFlow.';
        }
    }

    private static function primaryAction(?array $selected, bool $subscribeEnabled, bool $manageEnabled): array {
        $state = (string)($selected['state'] ?? 'no_subscription');
        $clientId = absint($selected['client_id'] ?? 0);
        if ($manageEnabled && in_array($state, ['active','trial','cancelling','payment_pending','suspended'], true)) {
            return [
                'id' => in_array($state, ['payment_pending','suspended'], true) ? 'resolve_payment' : 'manage_billing',
                'label' => in_array($state, ['payment_pending','suspended'], true) ? 'Regularizar pagamento' : 'Gerir plano e faturação',
                'target' => 'billing',
                'client_id' => $clientId,
                'enabled' => true,
            ];
        }
        if ($subscribeEnabled) {
            return ['id'=>'view_plans','label'=>'Ver planos e subscrições','target'=>'subscriptions','client_id'=>$clientId,'enabled'=>true];
        }
        return ['id'=>'none','label'=>'Indisponível','target'=>'','client_id'=>$clientId,'enabled'=>false];
    }

    private static function secondaryAction(?array $selected, bool $subscribeEnabled, bool $manageEnabled, array $primary): array {
        $clientId = absint($selected['client_id'] ?? 0);
        if ($subscribeEnabled && (string)($primary['target'] ?? '') !== 'subscriptions') {
            return ['id'=>'change_plan','label'=>'Comparar planos','target'=>'subscriptions','client_id'=>$clientId,'enabled'=>true];
        }
        if ($manageEnabled && (string)($primary['target'] ?? '') !== 'billing') {
            return ['id'=>'manage_billing','label'=>'Abrir faturação','target'=>'billing','client_id'=>$clientId,'enabled'=>true];
        }
        return ['id'=>'refresh','label'=>'Atualizar estado','target'=>'refresh','client_id'=>$clientId,'enabled'=>true];
    }

    private static function catalog(): array {
        if (!class_exists(PlanRepository::class)) return [];
        $items = [];
        foreach (PlanRepository::plans(true) as $plan) {
            if ((string)($plan['slug'] ?? '') === 'legacy') continue;
            $planId = absint($plan['id'] ?? 0);
            if ($planId <= 0) continue;
            $monthPrice = class_exists(BillingRepository::class) ? BillingRepository::priceForPlan($planId, 'month', BillingSettings::mode()) : null;
            $yearPrice = class_exists(BillingRepository::class) ? BillingRepository::priceForPlan($planId, 'year', BillingSettings::mode()) : null;
            $monthMinor = absint($monthPrice['amount_minor'] ?? round(((float)($plan['monthly_price'] ?? 0)) * 100));
            $yearMinor = absint($yearPrice['amount_minor'] ?? round(((float)($plan['annual_price'] ?? 0)) * 100));
            $currency = strtoupper((string)($monthPrice['currency'] ?? $yearPrice['currency'] ?? $plan['currency'] ?? 'EUR'));
            $items[] = [
                'id' => $planId,
                'slug' => sanitize_key((string)($plan['slug'] ?? '')),
                'name' => (string)($plan['name'] ?? ''),
                'description' => (string)($plan['description'] ?? ''),
                'currency' => $currency,
                'monthly_amount_minor' => $monthMinor,
                'annual_amount_minor' => $yearMinor,
                'monthly_amount' => $monthMinor / 100,
                'annual_amount' => $yearMinor / 100,
                'monthly_label' => $monthMinor > 0 ? number_format_i18n($monthMinor / 100, 2) . ' ' . $currency . '/mês' : 'Sob consulta',
                'annual_label' => $yearMinor > 0 ? number_format_i18n($yearMinor / 100, 2) . ' ' . $currency . '/ano' : 'Sob consulta',
                'capabilities' => array_values(array_keys(array_filter((array)($plan['capabilities'] ?? [])))),
                'limits' => (array)($plan['limits'] ?? []),
                'monthly_checkout' => $monthMinor > 0 && BillingService::checkoutAvailable($planId, 'month'),
                'annual_checkout' => $yearMinor > 0 && BillingService::checkoutAvailable($planId, 'year'),
            ];
        }
        return $items;
    }

    private static function atom($value): string {
        $timestamp = is_numeric($value) ? (int)$value : strtotime((string)$value);
        return $timestamp > 0 ? gmdate(DATE_ATOM, $timestamp) : '';
    }

    private static function humanDate($value): string {
        $timestamp = is_numeric($value) ? (int)$value : strtotime((string)$value);
        return $timestamp > 0 ? wp_date('d/m/Y', $timestamp) : '';
    }
}
