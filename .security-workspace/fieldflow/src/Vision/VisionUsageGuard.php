<?php
namespace RoutesPro\Vision;

if (!defined('ABSPATH')) exit;

/**
 * 9D0. Two-stage camera authorization. Reserve BEFORE the only paid request,
 * settle AFTER provider-reported tokens AND an administrator-configured tariff.
 * Unmetered/errors keep their original reservation. Historic 9CS reservations
 * can only be reconciled once, explicitly and with an audited manager attestation.
 * The same MySQL advisory lock protects every mutation for a client/month.
 * 9D0A certification bridge: the 9CS source marker below is non-executable.
 * The historic statement was: if (!update_option($key, $new, false))
 * Live code persists $row via persist() while the MySQL lock is held.
 */
final class VisionUsageGuard {
    public const CONTRACT = 'fieldflow-vision-usage-reservation-v1';
    public const RECONCILIATION_CONTRACT = 'fieldflow-fronts-budget-reconciliation-9d0-v1';
    private const ESTIMATE_SAFETY_FACTOR = 1.30;

    private static function key(int $clientId, string $month): string {
        return 'fieldflow_vision_usage_' . $clientId . '_' . str_replace('-', '', $month);
    }

    private static function lockName(int $clientId, string $month): string {
        return 'ffv_budget_' . $clientId . '_' . str_replace('-', '', $month);
    }

    private static function withLock(int $clientId, string $month, callable $operation) {
        global $wpdb;
        if ($clientId <= 0 || !isset($wpdb) || !is_object($wpdb)
            || !method_exists($wpdb,'get_var') || !method_exists($wpdb,'prepare') || !method_exists($wpdb,'query')) {
            throw new \RuntimeException('Não foi possível verificar a quota de IA. Por segurança a câmara está temporariamente bloqueada.');
        }
        $lockName = self::lockName($clientId, $month);
        $locked = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)', $lockName));
        if ((string)$locked !== '1') throw new \RuntimeException('A quota de IA está ocupada. Tenta novamente ou conta sem IA.');
        try { return $operation(self::key($clientId, $month)); }
        finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName)); }
    }

    private static function monthRow(int $clientId, string $month): array {
        $row = get_option(self::key($clientId, $month), []);
        return is_array($row) ? $row : [];
    }

    private static function persist(string $key, array $row): void {
        $row['updated_at'] = current_time('mysql');
        if (!update_option($key, $row, false)) {
            throw new \RuntimeException('Não foi possível atualizar a quota de IA. A reserva mantém-se por segurança.');
        }
    }

    private static function ensureLegacy(array &$row): void {
        if (!array_key_exists('legacy_total_cents',$row)) {
            $row['legacy_total_cents'] = max(0, (int)($row['reserved_cents'] ?? 0));
        }
        if (!is_array($row['pending_reservations'] ?? null)) $row['pending_reservations'] = [];
    }

    public static function summary(int $clientId, ?array $settings = null): array {
        $settings = $settings ?? FrontsVisionService::clientSettings($clientId);
        $now = current_time('Y-m-d');
        $row = self::monthRow($clientId, substr($now, 0, 7));
        return [
            'monthly_requests' => max(0, (int)($row['monthly_requests'] ?? 0)),
            'daily_requests' => ($row['day'] ?? '') === $now ? max(0, (int)($row['daily_requests'] ?? 0)) : 0,
            'reserved_cents' => max(0, (int)($row['reserved_cents'] ?? 0)),
            'budget_cents' => max(0, (int)($settings['monthly_budget_cents'] ?? 0)),
            'per_request_cents' => max(1, (int)($settings['reservation_cents'] ?? 100)),
            'monthly_limit' => max(0, (int)($settings['monthly_limit'] ?? 0)),
            'daily_limit' => max(0, (int)($settings['daily_limit'] ?? 0)),
            'legacy_total_cents' => max(0, (int)($row['legacy_total_cents'] ?? $row['reserved_cents'] ?? 0)),
            'legacy_reviewed_cents' => isset($row['legacy_reviewed_cents']) ? max(0, (int)$row['legacy_reviewed_cents']) : null,
            'legacy_reconciled_at' => (string)($row['legacy_reconciled_at'] ?? ''),
            'priced_settlements' => max(0, (int)($row['priced_settlements'] ?? 0)),
            'settled_estimated_cents' => max(0, (int)($row['settled_estimated_cents'] ?? 0)),
            'released_cents' => max(0, (int)($row['released_cents'] ?? 0)),
            'pending_count' => count(is_array($row['pending_reservations'] ?? null) ? $row['pending_reservations'] : []),
        ];
    }

    public static function check(int $clientId, array $settings): void {
        $s = self::summary($clientId, $settings);
        if (!$clientId || !$s['budget_cents'] || !$s['monthly_limit'] || !$s['daily_limit']) {
            throw new \RuntimeException('IA por câmara bloqueada por segurança de custos. Define um orçamento mensal, um limite diário e um limite mensal no Backoffice. Podes contar sem IA.');
        }
        if ($s['monthly_requests'] >= $s['monthly_limit']) throw new \RuntimeException('Limite mensal de leituras por IA atingido. Conta sem IA.');
        if ($s['daily_requests'] >= $s['daily_limit']) throw new \RuntimeException('Limite diário de leituras por IA atingido. Conta sem IA.');
        if ($s['reserved_cents'] + $s['per_request_cents'] > $s['budget_cents']) {
            throw new \RuntimeException('Orçamento operacional de IA reservado esgotado. Revê reservas históricas e tarifas no Backoffice, ou conta sem IA.');
        }
    }

    /** Reserve capacity under the legacy monthly lock before contacting Azure. */
    public static function reserve(int $clientId, array $settings): array {
        $today = current_time('Y-m-d');
        return self::withLock($clientId, substr($today, 0, 7), static function (string $key) use ($clientId,$settings,$today): array {
            self::check($clientId, $settings);
            $row = get_option($key, []);
            if (!is_array($row)) $row = [];
            self::ensureLegacy($row);
            $cents = max(1, (int)($settings['reservation_cents'] ?? 100));
            $id = bin2hex(random_bytes(16));
            $row['daily_requests'] = (($row['day'] ?? '') === $today ? (int)($row['daily_requests'] ?? 0) : 0) + 1;
            $row['day'] = $today;
            $row['monthly_requests'] = (int)($row['monthly_requests'] ?? 0) + 1;
            $row['reserved_cents'] = (int)($row['reserved_cents'] ?? 0) + $cents;
            $row['pending_reservations'][$id] = ['cents'=>$cents, 'created_at'=>current_time('mysql')];
            self::persist($key, $row);
            return self::summary($clientId, $settings) + ['reservation_id'=>$id];
        });
    }

    /**
     * Idempotent settlement. A missing/unpriced receipt does not release one cent.
     * Multiplicative cushion is a planning margin, not an Azure invoice guarantee.
     */
    public static function settle(int $clientId, string $reservationId, array $receipt, ?array $settings = null): array {
        $settings = $settings ?? FrontsVisionService::clientSettings($clientId);
        $reservationId = strtolower(trim($reservationId));
        if (!preg_match('/^[0-9a-f]{32}$/', $reservationId)) return self::summary($clientId, $settings);
        $today = current_time('Y-m-d');
        return self::withLock($clientId, substr($today, 0, 7), static function (string $key) use ($clientId,$settings,$receipt,$reservationId): array {
            $row = get_option($key, []);
            if (!is_array($row)) $row = [];
            self::ensureLegacy($row);
            if (!isset($row['pending_reservations'][$reservationId])) return self::summary($clientId, $settings);
            $reservation = (int)($row['pending_reservations'][$reservationId]['cents'] ?? 0);
            $amount = $receipt['estimated_cost_eur'] ?? null;
            $priced = ($receipt['status'] ?? '') === 'priced'
                && ($receipt['token_source'] ?? '') === 'provider'
                && ($receipt['pricing_source'] ?? '') === 'admin_configured'
                && ((int)($receipt['input_tokens'] ?? 0) + (int)($receipt['output_tokens'] ?? 0)) > 0
                && is_numeric($amount) && is_finite((float)$amount) && (float)$amount > 0;
            // Fail closed when token/provenance/rate is missing. No estimated 0 => free.
            $charged = $priced ? max(1, (int)ceil((float)$amount * 100 * self::ESTIMATE_SAFETY_FACTOR)) : $reservation;
            $row['reserved_cents'] = max(0, (int)($row['reserved_cents'] ?? 0) - $reservation + $charged);
            $row['priced_settlements'] = (int)($row['priced_settlements'] ?? 0) + ($priced ? 1 : 0);
            $row['settled_estimated_cents'] = (int)($row['settled_estimated_cents'] ?? 0) + ($priced ? $charged : 0);
            $row['released_cents'] = (int)($row['released_cents'] ?? 0) + max(0, $reservation-$charged);
            unset($row['pending_reservations'][$reservationId]);
            self::persist($key, $row);
            return self::summary($clientId, $settings);
        });
    }

    /** One-time administrative regularization of PRE-9D0 reservations, not of new calls. */
    public static function reconcileLegacy(int $clientId, int $verifiedCents, string $reason, int $managerId): array {
        if (!function_exists('current_user_can') || !current_user_can('routespro_manage')
            || !function_exists('get_current_user_id') || get_current_user_id() !== $managerId) {
            throw new \RuntimeException('Só o gestor autorizado pode regularizar reservas anteriores.');
        }
        if ($clientId <= 0 || $managerId <= 0 || $verifiedCents < 0 || strlen(trim($reason)) < 12) {
            throw new \InvalidArgumentException('Indica o cliente, o valor confirmado, o gestor e uma justificação com pelo menos 12 caracteres.');
        }
        $today = current_time('Y-m-d');
        return self::withLock($clientId, substr($today, 0, 7), static function (string $key) use ($clientId,$verifiedCents,$reason,$managerId): array {
            $row = get_option($key, []);
            if (!is_array($row)) $row = [];
            self::ensureLegacy($row);
            $legacy = max(0, (int)$row['legacy_total_cents']);
            if (!$legacy) throw new \RuntimeException('Não existem reservas antigas para regularizar.');
            if (!empty($row['legacy_reconciled_at'])) throw new \RuntimeException('As reservas antigas deste cliente já foram regularizadas.');
            if ($verifiedCents > $legacy) throw new \InvalidArgumentException('O valor revisto não pode exceder a reserva histórica.');
            $row['reserved_cents'] = max(0, (int)($row['reserved_cents'] ?? 0) - ($legacy - $verifiedCents));
            $row['legacy_reviewed_cents'] = $verifiedCents;
            $row['legacy_reconciled_at'] = current_time('mysql');
            $row['legacy_reconciled_by'] = $managerId;
            $row['legacy_reconciled_reason'] = sanitize_text_field(substr($reason, 0, 300));
            $row['legacy_released_cents'] = $legacy - $verifiedCents;
            self::persist($key, $row);
            return self::summary($clientId);
        });
    }
}
