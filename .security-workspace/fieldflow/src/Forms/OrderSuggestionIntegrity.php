<?php
namespace RoutesPro\Forms;

if (!defined('ABSPATH')) exit;

/**
 * 9DV: canonical order rows for both web and native/offline submissions.
 * Only server-approved assortment entries and products actually declared in
 * the paired Fronts question can reach a persisted order or its mail lane.
 */
final class OrderSuggestionIntegrity {
    public const CONTRACT = 'fieldflow-order-suggestion-integrity-9dv-v1';

    private static function identity(string $ref, string $name): string {
        $ref = strtolower(trim(remove_accents($ref)));
        $name = strtolower(trim(remove_accents($name)));
        $ref = preg_replace('/\s+/', ' ', $ref) ?: '';
        $name = preg_replace('/\s+/', ' ', $name) ?: '';
        return $ref !== '' ? 'ref:' . $ref : ($name !== '' ? 'name:' . $name : '');
    }

    private static function number($value): ?float {
        if ($value === null || is_array($value) || is_object($value)) return null;
        $raw = trim(str_replace(',', '.', (string)$value));
        if ($raw === '' || !is_numeric($raw)) return null;
        $number = (float)$raw;
        return is_finite($number) && $number >= 0 ? $number : null;
    }

    /** Raw Fronts value: empty is UNKNOWN; only a real numeric 0 is confirmed. */
    public static function confirmedZero(array $row): bool {
        if (array_key_exists('fronts_confirmed', $row) && empty($row['fronts_confirmed'])) return false;
        $raw = array_key_exists('after', $row) ? $row['after'] : ($row['qty'] ?? $row['quantity'] ?? null);
        $number = self::number($raw);
        return $number !== null && $number === 0.0;
    }

    /**
     * @param array $rawRows Incoming order rows (untrusted)
     * @param array $allowedRows Server-authoritative cardex assortment / manual list
     * @param array $pairedFrontRows Raw paired Fronts values, not normalized zeros
     * @param bool $frontsPresent Paired Fronts payload is supplied for this submission
     */
    public static function normalise(array $rawRows, array $allowedRows, array $pairedFrontRows,
        bool $frontsPresent, int $cardexId, float $zeroSuggestion = 1.0, bool $decimals = false): array {
        $byId = [];
        $byIdentity = [];
        foreach ($allowedRows as $allowed) {
            if (!is_array($allowed)) continue;
            $id = absint($allowed['item_id'] ?? 0);
            $identity = self::identity((string)($allowed['ref'] ?? $allowed['reference'] ?? ''), (string)($allowed['name'] ?? $allowed['product'] ?? ''));
            if ($identity === '') continue;
            if ($id) $byId[$id] = $allowed;
            if (!isset($byIdentity[$identity])) $byIdentity[$identity] = $allowed;
        }

        $frontsById = [];
        $frontsByIdentity = [];
        if ($frontsPresent) {
            foreach ($pairedFrontRows as $front) {
                if (!is_array($front)) continue;
                $id = absint($front['item_id'] ?? 0);
                $identity = self::identity((string)($front['ref'] ?? $front['reference'] ?? ''), (string)($front['name'] ?? $front['product'] ?? ''));
                if ($identity === '') continue;
                // A non-zero ID must belong to the *current store assortment*.
                if ($cardexId && $id && !isset($byId[$id])) continue;
                if ($cardexId && $id && isset($byId[$id])) {
                    $canonical = $byId[$id];
                    if (self::identity((string)($canonical['ref'] ?? ''), (string)($canonical['name'] ?? '')) !== $identity) continue;
                }
                if ($id) $frontsById[$id] = $front;
                $frontsByIdentity[$identity] = $front;
            }
        }

        $suggestion = is_finite($zeroSuggestion) ? max(0.0, $zeroSuggestion) : 0.0;
        if (!$decimals) $suggestion = (float)round($suggestion);
        $out = [];
        $used = [];
        foreach (array_slice($rawRows, 0, 2000) as $row) {
            if (!is_array($row)) continue;
            $id = absint($row['item_id'] ?? 0);
            $postedRef = sanitize_text_field((string)($row['ref'] ?? $row['reference'] ?? $row['code'] ?? ''));
            $postedName = sanitize_text_field((string)($row['name'] ?? $row['product'] ?? ''));
            $postedIdentity = self::identity($postedRef, $postedName);
            if ($postedIdentity === '') continue;

            $trusted = null;
            if ($id && isset($byId[$id])) {
                $trusted = $byId[$id];
                // Do not accept a known ID impersonating a different reference.
                if (self::identity((string)($trusted['ref'] ?? ''), (string)($trusted['name'] ?? '')) !== $postedIdentity) continue;
            } elseif (!$id && isset($byIdentity[$postedIdentity])) {
                $trusted = $byIdentity[$postedIdentity];
            }
            $front = $frontsPresent
                ? (($id && isset($frontsById[$id])) ? $frontsById[$id] : ($frontsByIdentity[$postedIdentity] ?? null))
                : null;
            if ($frontsPresent && !$front) continue;

            if (!$trusted) {
                // Custom additions must be in the paired Fronts, not a free-form
                // order payload. They cannot impersonate another stored item ID.
                if ($id || !$frontsPresent || !is_array($front)) continue;
                $trusted = $front;
            }
            $ref = sanitize_text_field((string)($trusted['ref'] ?? $trusted['reference'] ?? $postedRef));
            $name = sanitize_text_field((string)($trusted['name'] ?? $trusted['product'] ?? $postedName));
            $identity = self::identity($ref, $name);
            if ($identity === '' || isset($used[$identity])) continue;

            $source = sanitize_key((string)($row['quantity_source'] ?? $row['order_source'] ?? ''));
            if (!in_array($source, ['auto', 'manual'], true)) $source = '';
            $qtyRaw = $row['order_qty'] ?? $row['boxes'] ?? $row['qty'] ?? '';
            $qty = self::number($qtyRaw);
            $manual = $source === 'manual' || ($source === '' && $qty !== null);
            $confirmedZero = is_array($front) && self::confirmedZero($front);
            if ($manual) {
                $source = 'manual';
            } elseif ($confirmedZero && $suggestion > 0) {
                $qty = $suggestion;
                $source = 'auto';
            } else {
                // Discard a stale/forged auto quantity when Fronts is not
                // explicitly zero, including offline replays and edits.
                $qty = null;
                $source = '';
            }
            if ($qty !== null && !$decimals) $qty = (float)round($qty);
            $notes = sanitize_textarea_field((string)($row['notes'] ?? $row['observations'] ?? ''));
            if ($qty === null && $notes === '') continue;
            $used[$identity] = true;
            $units = self::number($trusted['units_per_case'] ?? $trusted['units_case'] ?? $row['units_per_case'] ?? null);
            $canonicalItem = absint($trusted['item_id'] ?? 0);
            $out[] = [
                'item_id' => $canonicalItem,
                'cardex_id' => $cardexId > 0 ? $cardexId : 0,
                'category' => sanitize_text_field((string)($trusted['category'] ?? $trusted['categoria'] ?? $row['category'] ?? '')),
                'ref' => $ref,
                'item_type' => sanitize_text_field((string)($trusted['item_type'] ?? $trusted['type'] ?? $row['item_type'] ?? '')),
                'name' => $name,
                'capacity_cl' => self::number($trusted['capacity_cl'] ?? $trusted['capacity'] ?? $row['capacity_cl'] ?? null) ?? '',
                'units_per_case' => $units ?? '',
                'order_qty' => $qty ?? '',
                'quantity_source' => $source,
                'notes' => $notes,
                'units_total' => ($qty !== null && $units !== null) ? round($qty * $units, 2) : '',
            ];
        }
        return $out;
    }
}
