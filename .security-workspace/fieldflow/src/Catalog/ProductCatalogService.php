<?php
namespace RoutesPro\Catalog;

use RoutesPro\Forms\ProductCardex;

if (!defined('ABSPATH')) exit;

/**
 * 9CL Visual Product Catalog.
 *
 * Stable, de-duplicated product identity per client, independent of stores and
 * of Cardex item IDs (a Cardex import with "Substituir" deletes and recreates
 * items). Each catalog product keeps reference images used to help operatives
 * identify products in fronts questions and, in a later phase, camera reading.
 *
 * Storage is a private, non-public WordPress record (no release-time DDL), the
 * same low-volume pattern certified in 9CH. Identity keys, project links and
 * briefing label aliases are stored as repeatable post meta so lookups use the
 * standard meta index.
 */
final class ProductCatalogService {
    public const CONTRACT = 'fieldflow-visual-product-catalog-v1';
    public const MAPPING_CONTRACT = 'fieldflow-briefing-product-label-mapping-v1';
    public const POST_TYPE = 'ff_catalog_product';
    public const SYNC_HOOK = 'fieldflow_catalog_sync_from_cardex';

    private const META_CLIENT = '_ff_client_id';
    private const META_KEY = '_ff_identity_key';
    private const META_PROJECT = '_ff_project_id';
    private const META_LABEL = '_ff_label_alias';
    private const META_REFERENCE = '_ff_reference';
    private const META_BRAND = '_ff_brand';
    private const META_CATEGORY = '_ff_category';
    private const META_CAPACITY = '_ff_capacity_cl';
    private const META_EAN = '_ff_ean';
    private const META_KIND = '_ff_kind';
    private const META_ALIASES = '_ff_aliases';
    private const META_IMAGES = '_ff_image_ids';
    private const META_ACTIVE = '_ff_active';
    private const META_ORIGIN = '_ff_origin';
    private const META_SYNCED = '_ff_last_synced_at';
    private const META_CARDEX = '_ff_cardex_ids';
    private const META_SOURCE = '_ff_source_product';
    private const META_SOURCE_CLIENT = '_ff_source_client';
    public const REUSE_CONTRACT = 'fieldflow-catalog-cross-client-reuse-v1';
    public const IDENTITY_CONTRACT = 'fieldflow-catalog-general-identity-v1';
    // 9CP marker: public const IDENTITY_VERSION = 2;
    // 9CQ: version 3 adds names written differently by each insignia (packaging words, abbreviations, bare ml).
    public const IDENTITY_VERSION = 3;
    public const IDENTITY_FUZZY_CONTRACT = 'fieldflow-catalog-insignia-name-variants-v1';
    private const META_NAME_LOCKED = '_ff_name_locked';
    /**
     * Generic words ignored when comparing product names across insignias: categories,
     * packaging and filler words. "WHISKY JAMESON 70CL" and "JAMESON IRISH WHISKEY 0,7L"
     * become the same signature; brands, variants, ages and pack sizes are kept.
     */
    private const GENERIC_WORDS = [
        'whisky' => 1, 'whiskey' => 1, 'whiskies' => 1, 'irish' => 1, 'scotch' => 1, 'scottish' => 1, 'blended' => 1, 'bourbon' => 1, 'tennessee' => 1, 'single' => 1, 'malt' => 1,
        'gin' => 1, 'vodka' => 1, 'vodca' => 1, 'rum' => 1, 'rhum' => 1, 'ron' => 1, 'licor' => 1, 'licores' => 1, 'liqueur' => 1, 'liquore' => 1, 'aguardente' => 1,
        'brandy' => 1, 'cognac' => 1, 'armagnac' => 1, 'tequila' => 1, 'mezcal' => 1, 'anis' => 1, 'aperitivo' => 1, 'aperitif' => 1, 'vermute' => 1, 'vermouth' => 1,
        'vinho' => 1, 'wine' => 1, 'espumante' => 1, 'sparkling' => 1, 'champagne' => 1, 'champanhe' => 1, 'cerveja' => 1, 'beer' => 1, 'sidra' => 1, 'cider' => 1,
        'garrafa' => 1, 'garrafas' => 1, 'grf' => 1, 'gfa' => 1, 'gf' => 1, 'btl' => 1, 'bottle' => 1, 'un' => 1, 'und' => 1, 'unid' => 1, 'unidade' => 1, 'unidades' => 1,
        'cx' => 1, 'caixa' => 1, 'emb' => 1, 'embalagem' => 1, 'original' => 1, 'london' => 1, 'dry' => 1,
        'bebida' => 1, 'bebidas' => 1, 'espirituosa' => 1, 'espirituosas' => 1, 'espirituoso' => 1, 'espirituosos' => 1, 'destilado' => 1,
        'lata' => 1, 'latas' => 1, 'can' => 1, 'cans' => 1, 'rtd' => 1, 'pet' => 1, 'vidro' => 1, 'cl' => 1, 'ml' => 1, 'lt' => 1, 'ltr' => 1,
        'de' => 1, 'da' => 1, 'do' => 1, 'das' => 1, 'dos' => 1, 'the' => 1, 'and' => 1, 'e' => 1, 'com' => 1, 'with' => 1,
    ];

    private static array $labelCache = [];
    private static array $indexCache = [];

    public static function registerHooks(): void {
        add_action('init', [self::class, 'registerPostType'], 4);
        add_action('fieldflow_mapping_source_changed', [self::class, 'onSourceChanged'], 20, 2);
        add_action(self::SYNC_HOOK, [self::class, 'runScheduledSync'], 10, 1);
        add_action('admin_init', [self::class, 'maybeScheduleIdentityRebuild'], 30);
    }

    public static function registerPostType(): void {
        if (post_type_exists(self::POST_TYPE)) return;
        register_post_type(self::POST_TYPE, [
            'label' => 'Catálogo visual de produtos',
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    /** A Cardex import or weekly Cardex sync refreshes the catalog in background. */
    public static function onSourceChanged($source = '', $scopeId = 0): void {
        $source = sanitize_key((string)$source);
        if (!in_array($source, ['cardex_import', 'cardex', 'cardex_briefing', 'cardex_sync'], true)) return;
        if (!wp_next_scheduled(self::SYNC_HOOK, [0])) wp_schedule_single_event(time() + 15, self::SYNC_HOOK, [0]);
    }

    public static function runScheduledSync($clientId = 0): void {
        self::syncFromCardex(absint($clientId));
    }

    // ------------------------------------------------------------------
    // Identity
    // ------------------------------------------------------------------

    public static function normalizeText(string $value): string {
        $value = wp_strip_all_tags($value);
        if (function_exists('remove_accents')) $value = remove_accents($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim((string)preg_replace('/\s+/', ' ', (string)$value));
    }

    public static function normalizeReference(string $value): string {
        $value = strtoupper(trim(wp_strip_all_tags($value)));
        return (string)preg_replace('/[^A-Z0-9]+/', '', $value);
    }

    public static function capacityKey($capacity): string {
        if ($capacity === null || $capacity === '' || !is_numeric($capacity)) return '';
        $value = round((float)$capacity, 1);
        return $value > 0 ? rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') : '';
    }

    private const CAPACITY_PATTERN = '/(\d+(?:[.,]\d+)?)\s*(cl|ml|ltr|lt|litros?|l)\b/i';

    /** Capacity written in the product name ("70CL", "0,7 L", "700ml", "1L"), in centilitres. */
    public static function capacityFromName(string $name): string {
        if (!preg_match(self::CAPACITY_PATTERN, $name, $match)) {
            // 9CQ: a bare bottle size at the end of the name ("JAMESON ORANGE 700" = 700 ml). Ages (12, 18, 25)
            // and other small numbers are never read as capacities.
            if (preg_match('/(?:^|[\s.\-])(\d{2,4})\s*$/', trim($name), $bare)) {
                $number = (int)$bare[1];
                if (in_array($number, [200, 250, 275, 330, 350, 375, 500, 700, 750, 1000, 1500, 1750, 3000, 4500], true)) return self::capacityKey($number / 10);
                if (in_array($number, [33, 35, 50, 70, 75], true)) return self::capacityKey($number);
            }
            return '';
        }
        $value = (float)str_replace(',', '.', $match[1]);
        $unit = strtolower($match[2]);
        if ($unit === 'ml') $value = $value / 10;
        elseif ($unit !== 'cl') $value = $value * 100;
        return self::capacityKey($value);
    }

    /** Normalized name without the capacity token, so "Jameson 70CL" and "Jameson 70 cl" share one identity. */
    public static function nameKey(string $name): string {
        return self::normalizeText((string)preg_replace(self::CAPACITY_PATTERN, ' ', $name));
    }

    /**
     * 9CP fieldflow-catalog-general-identity-v1: capacity in cl. A numeric field above 4.5 is
     * already in cl; up to 4.5 it is litres (0,7 / 1 / 1,5) unless the name says otherwise.
     */
    public static function normalizeCapacity($field, string $name = ''): string {
        $fromName = self::capacityFromName($name);
        $value = ($field === null || $field === '' || !is_numeric($field)) ? 0.0 : (float)$field;
        if ($value > 4.5) return self::capacityKey($value);
        if ($fromName !== '') return $fromName;
        if ($value > 0) return self::capacityKey($value * 100);
        return '';
    }

    public static function normalizeEan(string $value): string {
        $digits = (string)preg_replace('/\D+/', '', $value);
        return strlen($digits) >= 8 && strlen($digits) <= 14 && trim($digits, '0') !== '' ? ltrim($digits, '0') : '';
    }

    /** Word signature of a name, independent of the insignia: order, generic words, capacity and age formats are ignored. */
    public static function nameSignature(string $name, string $capacity = ''): string {
        $value = str_replace(["'", '’', '`', '´'], '', $name);
        $value = (string)preg_replace(self::CAPACITY_PATTERN, ' ', $value);
        $value = (string)preg_replace('/(\d+)\s*(anos|ano|years|year|yrs|yr|yo|y)(?![a-z])/i', '$1 ', $value);
        $tokens = array_values(array_filter(explode(' ', self::normalizeText($value)), 'strlen'));
        $capacityTokens = $capacity !== '' ? [$capacity => 1, (string)((int)round((float)$capacity * 10)) => 1] : [];
        $kept = [];
        foreach ($tokens as $token) {
            if (isset(self::GENERIC_WORDS[$token]) || isset($capacityTokens[$token])) continue;
            $kept[$token] = true;
        }
        if (!$kept) foreach ($tokens as $token) $kept[$token] = true;
        $words = array_keys($kept);
        sort($words, SORT_STRING);
        return implode(' ', $words);
    }

    /** Insignia of a Cardex: chain article codes are only comparable inside the same insignia. */
    public static function cardexScope(array $cardex): string {
        $scope = str_replace(' ', '_', self::normalizeText((string)($cardex['insignia'] ?? '')));
        return $scope !== '' ? $scope : 'k' . absint($cardex['id'] ?? 0);
    }

    /**
     * Identity keys: EAN (the same in every insignia), the article code scoped by insignia
     * (or unscoped for a reference typed in the back office) and the word signature of the
     * name with the capacity.
     */
    public static function identityKeys(string $reference, string $name, $capacity = null, string $ean = '', string $scope = ''): array {
        $keys = [];
        $ean = self::normalizeEan($ean);
        if ($ean !== '') $keys[] = 'e:' . $ean;
        $ref = self::normalizeReference($reference);
        if ($ref !== '') $keys[] = $scope !== '' ? 'c:' . $scope . ':' . $ref : 'r:' . $ref;
        $capacityKey = self::normalizeCapacity($capacity, $name);
        $signature = self::nameSignature($name, $capacityKey);
        if ($signature !== '') $keys[] = 's:' . substr($signature . '|' . $capacityKey, 0, 180);
        return array_values(array_unique($keys));
    }

    /** Keys written by 2.2.609–2.2.612, used once to find the products to reorganise. */
    public static function legacyKeys(string $reference, string $name, $capacity = null): array {
        $keys = [];
        $ref = self::normalizeReference($reference);
        if ($ref !== '') $keys[] = 'r:' . $ref;
        $normalizedName = self::nameKey($name);
        $capacityKey = self::capacityKey($capacity);
        if ($capacityKey === '') $capacityKey = self::capacityFromName($name);
        if ($normalizedName !== '') $keys[] = 'n:' . substr($normalizedName . '|' . $capacityKey, 0, 180);
        return array_values(array_unique($keys));
    }

    public static function findByKeys(int $clientId, array $keys): int {
        $keys = array_values(array_filter(array_map('strval', $keys)));
        if (!$keys) return 0;
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'private'],
            'fields' => 'ids',
            'numberposts' => 1,
            'no_found_rows' => true,
            'suppress_filters' => true,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => self::META_CLIENT, 'value' => (string)$clientId],
                ['key' => self::META_KEY, 'value' => $keys, 'compare' => 'IN'],
            ],
        ]);
        return $ids ? (int)$ids[0] : 0;
    }

    private static function syncKeyMap(int $clientId): array {
        $map = [];
        foreach (self::clientIndex($clientId) as $id => $row) {
            foreach ($row['keys'] as $key) if (!isset($map[$key])) $map[$key] = (int)$id;
        }
        return $map;
    }

    // ------------------------------------------------------------------
    // Sync from Cardex
    // ------------------------------------------------------------------

    public static function clientForCardex(array $cardex): int {
        $clientId = absint($cardex['client_id'] ?? 0);
        if ($clientId > 0) return $clientId;
        $projectId = absint($cardex['project_id'] ?? 0);
        if ($projectId <= 0) return 0;
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT client_id FROM {$wpdb->prefix}routespro_projects WHERE id=%d", $projectId));
    }

    /**
     * 9CP: one general catalog per client, never per insignia. Every active Cardex line of the
     * client (all insignias, all campaigns) is grouped by EAN, by the article code inside its own
     * insignia and by the word signature of the name with the capacity. Each group is one product.
     * Existing duplicates of the same group are merged (images, other names, briefing labels and
     * campaigns are kept on the surviving product); manual work is never overwritten.
     */
    public static function syncFromCardex(int $onlyClientId = 0): array {
        global $wpdb;
        $stats = ['contract' => self::CONTRACT, 'identity' => self::IDENTITY_CONTRACT, 'cardex' => 0, 'items' => 0, 'groups' => 0, 'created' => 0, 'updated' => 0, 'merged' => 0, 'removed' => 0, 'conflicts' => 0, 'variants' => 0, 'skipped_no_client' => 0, 'clients' => [], 'insignias' => [], 'ean_enriched' => 0];
        if (!class_exists(ProductCardex::class)) return $stats;
        ProductCardex::install();
        self::registerPostType();
        $itemsTable = ProductCardex::table_items();
        self::$indexCache = [];
        $entries = [];
        foreach (ProductCardex::list_cardex() as $cardex) {
            // 9DP: draft clones are not commercial assortment sources or catalog products.
            if (($cardex['status'] ?? 'active') !== 'active') continue;
            $clientId = self::clientForCardex($cardex);
            if ($onlyClientId > 0 && $clientId !== $onlyClientId) continue;
            $cardexId = absint($cardex['id'] ?? 0);
            if (!$cardexId) continue;
            $stats['cardex']++;
            if ($clientId <= 0) { $stats['skipped_no_client']++; continue; }
            $stats['clients'][$clientId] = true;
            $scope = self::cardexScope($cardex);
            $insignia = trim((string)($cardex['insignia'] ?? ''));
            if ($insignia !== '') $stats['insignias'][$insignia] = true;
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT id, category, reference, product_name, capacity_cl, meta_json FROM {$itemsTable} WHERE cardex_id=%d AND is_active=1",
                $cardexId
            ), ARRAY_A) ?: [];
            foreach ($items as $item) {
                $name = trim(sanitize_text_field((string)($item['product_name'] ?? '')));
                if ($name === '') continue;
                $meta = json_decode((string)($item['meta_json'] ?? ''), true);
                $meta = is_array($meta) ? $meta : [];
                $reference = sanitize_text_field((string)($item['reference'] ?? ''));
                $ean = self::normalizeEan((string)($meta['ean'] ?? ''));
                $capacity = self::normalizeCapacity($item['capacity_cl'] ?? null, $name);
                $entries[$clientId][] = [
                    'name' => $name,
                    'reference' => $reference,
                    'category' => sanitize_text_field((string)($item['category'] ?? '')),
                    'brand' => sanitize_text_field((string)($meta['brand'] ?? '')),
                    'ean' => $ean,
                    'capacity' => $capacity,
                    'cardex_id' => $cardexId,
                    'project_id' => absint($cardex['project_id'] ?? 0),
                    'keys' => self::identityKeys($reference, $name, $capacity, $ean, $scope),
                    'scope' => $scope,
                ];
                $stats['items']++;
            }
        }
        foreach ($entries as $clientId => $list) {
            $result = self::rebuildClient((int)$clientId, $list);
            foreach (['groups', 'created', 'updated', 'merged', 'removed', 'conflicts', 'variants'] as $key) $stats[$key] += (int)$result[$key];
        }
        foreach (array_keys($stats['clients']) as $clientId) $stats['ean_enriched'] += self::enrichEanFromStocks((int)$clientId);
        $stats['clients'] = array_map('intval', array_keys($stats['clients']));
        $stats['insignias'] = array_keys($stats['insignias']);
        self::$indexCache = [];
        self::$labelCache = [];
        update_option('fieldflow_catalog_last_sync', ['at' => current_time('mysql'), 'stats' => $stats], false);
        if ($onlyClientId === 0) update_option('fieldflow_catalog_identity_version', self::IDENTITY_VERSION, false);
        $current = get_option('fieldflow_catalog_identity_clients', []);
        $current = is_array($current) ? $current : [];
        foreach (array_keys($entries) as $clientId) $current[(int)$clientId] = self::IDENTITY_VERSION;
        if ($entries) update_option('fieldflow_catalog_identity_clients', $current, false);
        return $stats;
    }

    /** Whether the catalog of a client was already rebuilt with the current identity (whole site or that client). */
    public static function identityCurrentFor(int $clientId): bool {
        if ((int)get_option('fieldflow_catalog_identity_version', 0) >= self::IDENTITY_VERSION) return true;
        $clients = get_option('fieldflow_catalog_identity_clients', []);
        return is_array($clients) && (int)($clients[$clientId] ?? 0) >= self::IDENTITY_VERSION;
    }

    /**
     * Groups the Cardex lines of one client, reuses or merges the existing products and creates the missing ones.
     *
     * Pass 1 joins lines that share an identity key (EAN, code inside the same insignia, word signature).
     * Pass 2 attaches the existing products through their EAN, the signature of their name and other
     * names, and the keys stored by the last sync. A product never joins two groups that hold different
     * codes of the same insignia: one insignia lists each product once, so such groups are different products.
     */
    private static function rebuildClient(int $clientId, array $list): array {
        $result = ['groups' => 0, 'created' => 0, 'updated' => 0, 'merged' => 0, 'removed' => 0, 'conflicts' => 0, 'variants' => 0];
        $list = array_values($list);
        self::$indexCache = [];
        $index = self::clientIndex($clientId);
        $lineCount = count($list);
        $parent = []; $codes = []; $node = $lineCount; $productNode = [];
        for ($i = 0; $i < $lineCount; $i++) {
            $parent[$i] = $i;
            foreach ($list[$i]['keys'] as $key) if (preg_match('/^c:([^:]+):(.+)$/', $key, $m)) $codes[$i][$m[1]][$m[2]] = true;
        }
        foreach (array_keys($index) as $id) { $productNode[$id] = $node; $parent[$node] = $node; $node++; }
        $find = static function (int $i) use (&$parent): int { while ($parent[$i] !== $i) { $parent[$i] = $parent[$parent[$i]]; $i = $parent[$i]; } return $i; };
        $union = static function (int $a, int $b, bool $guard) use (&$parent, &$codes, $find): bool {
            $ra = $find($a); $rb = $find($b);
            if ($ra === $rb) return true;
            if ($guard) {
                foreach ($codes[$ra] ?? [] as $scope => $set) {
                    if (isset($codes[$rb][$scope]) && !array_intersect_key($set, $codes[$rb][$scope])) return false;
                }
            }
            $parent[$ra] = $rb;
            foreach ($codes[$ra] ?? [] as $scope => $set) $codes[$rb][$scope] = ($codes[$rb][$scope] ?? []) + $set;
            unset($codes[$ra]);
            return true;
        };
        // Pass 1: lines.
        $owner = [];
        foreach ($list as $i => $entry) {
            foreach ($entry['keys'] as $key) {
                if (!isset($owner[$key])) { $owner[$key] = $i; continue; }
                $union($i, $owner[$key], false);
            }
        }
        // Pass 2: existing products, strongest evidence first.
        foreach ($index as $id => $row) {
            $capacity = $row['capacity_cl'] === '' ? null : $row['capacity_cl'];
            $keys = [];
            $ean = self::normalizeEan((string)$row['ean']);
            if ($ean !== '') $keys[] = 'e:' . $ean;
            $keys = array_merge($keys, self::identityKeys('', $row['name'], $capacity));
            foreach ($row['aliases'] as $alias) $keys = array_merge($keys, self::identityKeys('', (string)$alias, $capacity));
            foreach ($row['keys'] as $key) if (preg_match('/^[ecs]:/', (string)$key)) $keys[] = (string)$key;
            foreach (array_values(array_unique($keys)) as $key) {
                if (!isset($owner[$key])) continue;
                if (!$union($productNode[$id], $owner[$key], true)) $result['conflicts']++;
            }
        }
        // Pass 3 (9CQ fieldflow-catalog-insignia-name-variants-v1): the same product written differently by
        // two insignias ("MARTINI LATA" / "MART.LATA ... SDR", "JAMESON" / "JAMESON STD").
        $result['variants'] = self::joinInsigniaVariants($list, $find, $union);
        $groups = [];
        for ($i = 0; $i < $lineCount; $i++) $groups[$find($i)]['lines'][] = $list[$i];
        foreach ($productNode as $id => $n) {
            $root = $find($n);
            if (isset($groups[$root])) $groups[$root]['products'][] = (int)$id;
        }
        $assigned = [];
        $now = current_time('mysql');
        foreach ($groups as $group) {
            $members = $group['lines'];
            $result['groups']++;
            $groupKeys = [];
            foreach ($members as $member) foreach ($member['keys'] as $key) $groupKeys[$key] = true;
            $candidates = $group['products'] ?? [];
            if ($candidates) {
                usort($candidates, static function ($a, $b) use ($index) { return self::keepScore($index[$b]) <=> self::keepScore($index[$a]) ?: $a <=> $b; });
                $productId = (int)array_shift($candidates);
                foreach ($candidates as $duplicateId) {
                    try { self::merge((int)$duplicateId, $productId); $result['merged']++; $assigned[$duplicateId] = true; } catch (\Throwable $e) { }
                }
                $result['updated']++;
            } else {
                $productId = (int)wp_insert_post(['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'post_title' => wp_slash(self::groupName($members))], true);
                if (!$productId || is_wp_error($productId)) continue;
                update_post_meta($productId, self::META_CLIENT, (string)$clientId);
                update_post_meta($productId, self::META_KIND, 'own');
                update_post_meta($productId, self::META_ACTIVE, '1');
                update_post_meta($productId, self::META_ORIGIN, 'cardex');
                update_post_meta($productId, self::META_IMAGES, wp_json_encode([]));
                update_post_meta($productId, self::META_ALIASES, wp_json_encode([]));
                $result['created']++;
            }
            $assigned[$productId] = true;
            self::applyGroup($productId, $members, array_keys($groupKeys), $now);
        }
        // Products of the previous identity that no Cardex line claims any more and that hold no
        // manual work (images, briefing labels, names edited in the back office) are removed.
        foreach ($index as $id => $row) {
            if (isset($assigned[$id]) || $row['origin'] !== 'cardex' || $row['image_count'] > 0 || $row['labels']) continue;
            if ((string)get_post_meta($id, self::META_NAME_LOCKED, true) === '1') continue;
            $post = get_post($id);
            if (!$post || $post->post_status === 'trash') continue;
            wp_trash_post((int)$id);
            $result['removed']++;
        }
        self::$indexCache = [];
        self::$labelCache = [];
        return $result;
    }

    /** Name tokens used to compare insignias: signature words (generic words and capacity removed). */
    public static function signatureTokens(string $name, string $capacity = ''): array {
        $signature = self::nameSignature($name, $capacity);
        return $signature === '' ? [] : explode(' ', $signature);
    }

    /**
     * Joins the groups of lines that are the same product written differently by two insignias. Only groups
     * with the same capacity and the same numbers (ages, pack sizes), never listed in the same insignia, that
     * share the brand word, where every other word matches, is an abbreviation of the other side ("MART." for
     * "MARTINI", "BLK" for "BLACK") or a short chain code without vowels ("SDR", "STD"). A different variant
     * word (Orange, Pink, Black Barrel, Red) always keeps two products. The closest pairs are joined first and a
     * group never takes two products of the same insignia.
     */
    private static function joinInsigniaVariants(array $list, callable $find, callable $union): int {
        $components = [];
        foreach ($list as $i => $entry) {
            $root = $find($i);
            $scope = (string)($entry['scope'] ?? '');
            if ($scope !== '') $components[$root]['scopes'][$scope] = true;
            else $components[$root]['scopes'] = $components[$root]['scopes'] ?? [];
            $components[$root]['capacities'][(string)$entry['capacity']] = true;
            $tokens = self::signatureTokens((string)$entry['name'], (string)$entry['capacity']);
            if ($tokens) $components[$root]['names'][implode(' ', $tokens)] = $tokens;
        }
        $buckets = [];
        foreach ($components as $root => $component) {
            if (count($component['capacities']) !== 1 || empty($component['names'])) continue;
            $capacity = (string)array_key_first($component['capacities']);
            if ($capacity === '') continue;
            $buckets[$capacity][] = $root;
        }
        $pairs = [];
        foreach ($buckets as $roots) {
            $count = min(count($roots), 1500);
            for ($a = 0; $a < $count; $a++) {
                for ($b = $a + 1; $b < $count; $b++) {
                    $ca = $components[$roots[$a]]; $cb = $components[$roots[$b]];
                    if (array_intersect_key($ca['scopes'], $cb['scopes'])) continue;
                    $best = 0.0;
                    foreach ($ca['names'] as $ta) foreach ($cb['names'] as $tb) $best = max($best, self::variantScore($ta, $tb));
                    if ($best > 0) $pairs[] = [$best, $roots[$a], $roots[$b]];
                }
            }
        }
        usort($pairs, static function ($x, $y) { return $y[0] <=> $x[0] ?: $x[1] <=> $y[1] ?: $x[2] <=> $y[2]; });
        $scopes = [];
        foreach ($components as $root => $component) $scopes[$root] = $component['scopes'];
        $joined = 0;
        foreach ($pairs as [$score, $a, $b]) {
            $ra = $find($a); $rb = $find($b);
            if ($ra === $rb) continue;
            if (array_intersect_key($scopes[$ra] ?? [], $scopes[$rb] ?? [])) continue;
            if (!$union($ra, $rb, true)) continue;
            $root = $find($ra);
            $scopes[$root] = ($scopes[$ra] ?? []) + ($scopes[$rb] ?? []);
            $joined++;
        }
        return $joined;
    }

    /** Similarity of two token lists when they can only differ by abbreviations and chain codes; 0 otherwise. */
    public static function variantScore(array $a, array $b): float {
        $digits = static function (array $tokens): string { $d = array_values(array_filter($tokens, 'ctype_digit')); sort($d); return implode(',', $d); };
        if ($digits($a) !== $digits($b)) return 0.0;
        $alphaA = array_values(array_filter($a, static function ($t) { return !ctype_digit($t); }));
        $alphaB = array_values(array_filter($b, static function ($t) { return !ctype_digit($t); }));
        if (!$alphaA || !$alphaB) return 0.0;
        $usedA = []; $usedB = []; $matched = 0; $anchor = false;
        foreach ($alphaA as $i => $x) {
            foreach ($alphaB as $j => $y) {
                if (isset($usedB[$j]) || $x !== $y) continue;
                $usedA[$i] = $usedB[$j] = true; $matched++;
                if (strlen($x) >= 4) $anchor = true;
                break;
            }
        }
        if (!$anchor) return 0.0;
        $abbreviates = static function (string $short, string $long): bool {
            if (strlen($short) >= strlen($long) || strlen($short) < 2) return false;
            if (strlen($short) >= 3 && strpos($long, $short) === 0) return true;
            if (preg_match('/[aeiouy]/', $short) || $short[0] !== $long[0]) return false;
            $pos = 0;
            foreach (str_split($short) as $char) { $pos = strpos($long, $char, $pos); if ($pos === false) return false; $pos++; }
            return true;
        };
        foreach ($alphaA as $i => $x) {
            if (isset($usedA[$i])) continue;
            foreach ($alphaB as $j => $y) {
                if (isset($usedB[$j])) continue;
                if ($abbreviates($x, $y) || $abbreviates($y, $x)) { $usedA[$i] = $usedB[$j] = true; $matched++; break; }
            }
        }
        $code = static function (string $t): bool { return strlen($t) <= 3 && !preg_match('/[aeiouy]/', $t); };
        foreach ($alphaA as $i => $x) if (!isset($usedA[$i]) && !$code($x)) return 0.0;
        foreach ($alphaB as $j => $y) if (!isset($usedB[$j]) && !$code($y)) return 0.0;
        return round($matched / max(count($alphaA), count($alphaB)), 3);
    }

    /** Most common name of the group; on a tie the shortest one ("JAMESON 70CL" over "WHISKY JAMESON IRISH 70CL"). */
    private static function groupName(array $members): string {
        $counts = [];
        foreach ($members as $member) $counts[$member['name']] = ($counts[$member['name']] ?? 0) + 1;
        uksort($counts, static function ($a, $b) use ($counts) { return $counts[$b] <=> $counts[$a] ?: strlen($a) <=> strlen($b) ?: strcmp($a, $b); });
        return (string)array_key_first($counts);
    }

    private static function applyGroup(int $productId, array $members, array $groupKeys, string $now): void {
        $origin = (string)get_post_meta($productId, self::META_ORIGIN, true) ?: 'manual';
        $cardexIds = [];
        $projects = [];
        $first = ['category' => '', 'brand' => '', 'ean' => '', 'reference' => ''];
        $capacities = [];
        foreach ($members as $member) {
            $cardexIds[(int)$member['cardex_id']] = true;
            if (!empty($member['project_id'])) $projects[] = (string)absint($member['project_id']);
            foreach ($first as $field => $value) if ($value === '' && (string)$member[$field] !== '') $first[$field] = (string)$member[$field];
            if ($member['capacity'] !== '') $capacities[$member['capacity']] = ($capacities[$member['capacity']] ?? 0) + 1;
        }
        $capacity = (string)get_post_meta($productId, self::META_CAPACITY, true);
        if ($capacities) {
            arsort($capacities);
            // Fix capacities stored in litres by the previous identity (1 → 100 cl).
            if ($capacity === '' || ($origin === 'cardex' && is_numeric($capacity) && (float)$capacity <= 4.5)) {
                $capacity = (string)array_key_first($capacities);
                update_post_meta($productId, self::META_CAPACITY, $capacity);
            }
        }
        $title = (string)(get_post($productId)->post_title ?? '');
        $aliases = array_values(array_map('strval', self::jsonList(get_post_meta($productId, self::META_ALIASES, true))));
        // The names used by the other insignias become "other names" of the product (search, camera reading).
        $nameId = static function (string $name): string { $key = self::nameKey($name); return $key === '' ? '' : $key . '|' . self::capacityFromName($name); };
        $known = [$nameId($title) => true];
        foreach ($aliases as $alias) $known[$nameId($alias)] = true;
        foreach ($members as $member) {
            $normalized = $nameId((string)$member['name']);
            if ($normalized === '' || isset($known[$normalized]) || count($aliases) >= 40) continue;
            $known[$normalized] = true;
            $aliases[] = (string)$member['name'];
        }
        update_post_meta($productId, self::META_ALIASES, wp_slash(wp_json_encode($aliases, JSON_UNESCAPED_UNICODE)));
        if ($origin === 'cardex') {
            // Cardex products keep only the current identity (insignia codes are no longer global).
            delete_post_meta($productId, self::META_KEY);
            $keyCapacity = $capacity === '' ? null : $capacity;
            foreach ($aliases as $alias) $groupKeys = array_merge($groupKeys, self::identityKeys('', (string)$alias, $keyCapacity));
            $groupKeys = array_merge($groupKeys, self::identityKeys('', $title, $keyCapacity));
        }
        self::addMetaValues($productId, self::META_KEY, $groupKeys);
        if ($projects) self::addMetaValues($productId, self::META_PROJECT, $projects);
        $current = $origin === 'cardex' ? [] : array_map('intval', self::jsonList(get_post_meta($productId, self::META_CARDEX, true)));
        update_post_meta($productId, self::META_CARDEX, wp_json_encode(array_values(array_unique(array_merge($current, array_keys($cardexIds))))));
        foreach ([self::META_CATEGORY => $first['category'], self::META_BRAND => $first['brand'], self::META_EAN => $first['ean'], self::META_REFERENCE => $first['reference']] as $metaKey => $value) {
            if ($value !== '' && (string)get_post_meta($productId, $metaKey, true) === '') update_post_meta($productId, $metaKey, wp_slash($value));
        }
        update_post_meta($productId, self::META_SYNCED, $now);
    }

    /** Once after updating: reorganise the catalogs built with the previous identity. */
    public static function maybeScheduleIdentityRebuild(): void {
        if ((int)get_option('fieldflow_catalog_identity_version', 0) >= self::IDENTITY_VERSION) return;
        if (get_transient('fieldflow_catalog_identity_rebuild_scheduled')) return;
        set_transient('fieldflow_catalog_identity_rebuild_scheduled', 1, HOUR_IN_SECONDS);
        if (!wp_next_scheduled(self::SYNC_HOOK, [0])) wp_schedule_single_event(time() + 30, self::SYNC_HOOK, [0]);
    }

    /** EAN is copied from Stocks when the Cardex reference matches a stock article. */
    public static function enrichEanFromStocks(int $clientId): int {
        global $wpdb;
        $table = $wpdb->prefix . 'routespro_stock_items';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return 0;
        $ids = get_posts([
            'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => 2000, 'no_found_rows' => true, 'suppress_filters' => true,
            'meta_query' => [['key' => self::META_CLIENT, 'value' => (string)$clientId]],
        ]);
        $count = 0;
        foreach ($ids as $id) {
            if ((string)get_post_meta($id, self::META_EAN, true) !== '') continue;
            $reference = (string)get_post_meta($id, self::META_REFERENCE, true);
            if ($reference === '') continue;
            $ean = (string)$wpdb->get_var($wpdb->prepare(
                "SELECT ean FROM {$table} WHERE client_id=%d AND article_ref=%s AND ean IS NOT NULL AND ean<>'' GROUP BY ean ORDER BY COUNT(*) DESC LIMIT 1",
                $clientId, $reference
            ));
            $ean = preg_replace('/[^0-9]/', '', $ean);
            if ($ean !== '' && strlen($ean) >= 8) { update_post_meta($id, self::META_EAN, $ean); $count++; }
        }
        return $count;
    }

    // ------------------------------------------------------------------
    // Read model
    // ------------------------------------------------------------------

    public static function get(int $productId): ?array {
        $post = get_post($productId);
        if (!$post || $post->post_type !== self::POST_TYPE || $post->post_status === 'trash') return null;
        $images = array_values(array_filter(array_map('absint', self::jsonList(get_post_meta($productId, self::META_IMAGES, true)))));
        $imageRows = [];
        foreach ($images as $attachmentId) {
            $thumb = wp_get_attachment_image_src($attachmentId, 'thumbnail');
            $full = wp_get_attachment_image_src($attachmentId, 'large');
            if (!$thumb && !$full) continue;
            $imageRows[] = ['id' => $attachmentId, 'thumb' => (string)($thumb[0] ?? $full[0] ?? ''), 'url' => (string)($full[0] ?? $thumb[0] ?? ''), 'field' => (string)get_post_meta($attachmentId, '_ff_field_reference', true) !== ''];
        }
        $aliases = array_values(array_filter(array_map('strval', self::jsonList(get_post_meta($productId, self::META_ALIASES, true)))));
        return [
            'id' => (int)$productId,
            'name' => (string)$post->post_title,
            'client_id' => absint(get_post_meta($productId, self::META_CLIENT, true)),
            'reference' => (string)get_post_meta($productId, self::META_REFERENCE, true),
            'brand' => (string)get_post_meta($productId, self::META_BRAND, true),
            'category' => (string)get_post_meta($productId, self::META_CATEGORY, true),
            'capacity_cl' => (string)get_post_meta($productId, self::META_CAPACITY, true),
            'ean' => (string)get_post_meta($productId, self::META_EAN, true),
            'kind' => ((string)get_post_meta($productId, self::META_KIND, true) === 'competitor') ? 'competitor' : 'own',
            'active' => (string)get_post_meta($productId, self::META_ACTIVE, true) !== '0',
            'origin' => (string)get_post_meta($productId, self::META_ORIGIN, true) ?: 'manual',
            'aliases' => $aliases,
            'images' => $imageRows,
            'image_count' => count($imageRows),
            'ready_for_reading' => count($imageRows) > 0,
            'project_ids' => array_values(array_unique(array_map('absint', (array)get_post_meta($productId, self::META_PROJECT)))),
            'cardex_ids' => array_values(array_map('absint', self::jsonList(get_post_meta($productId, self::META_CARDEX, true)))),
            'label_aliases' => array_values(array_unique(array_map('strval', (array)get_post_meta($productId, self::META_LABEL)))),
            'last_synced_at' => (string)get_post_meta($productId, self::META_SYNCED, true),
            'source_product_id' => absint(get_post_meta($productId, self::META_SOURCE, true)),
            'source_client_id' => absint(get_post_meta($productId, self::META_SOURCE_CLIENT, true)),
        ];
    }

    public static function query(array $filters = [], int $page = 1, int $perPage = 50): array {
        $clientId = absint($filters['client_id'] ?? 0);
        $meta = ['relation' => 'AND'];
        if ($clientId > 0) $meta[] = ['key' => self::META_CLIENT, 'value' => (string)$clientId];
        $projectId = absint($filters['project_id'] ?? 0);
        // A Cardex without project applies to every campaign of the client.
        if ($projectId > 0) $meta[] = ['relation' => 'OR', ['key' => self::META_PROJECT, 'value' => (string)$projectId], ['key' => self::META_PROJECT, 'compare' => 'NOT EXISTS']];
        $kind = sanitize_key((string)($filters['kind'] ?? ''));
        if ($kind === 'competitor') $meta[] = ['key' => self::META_KIND, 'value' => 'competitor'];
        if ($kind === 'own') $meta[] = ['key' => self::META_KIND, 'value' => 'competitor', 'compare' => '!='];
        $images = sanitize_key((string)($filters['images'] ?? ''));
        if ($images === 'missing') $meta[] = ['relation' => 'OR', ['key' => self::META_IMAGES, 'value' => '[]'], ['key' => self::META_IMAGES, 'compare' => 'NOT EXISTS']];
        if ($images === 'ready') $meta[] = ['key' => self::META_IMAGES, 'value' => '[]', 'compare' => '!='];
        $args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(200, $perPage)),
            'paged' => max(1, $page),
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => $meta,
            'suppress_filters' => true,
        ];
        // The back office sends the search box as 's'.
        $search = trim(sanitize_text_field((string)($filters['search'] ?? $filters['s'] ?? '')));
        if ($search !== '') $args['s'] = $search;
        $query = new \WP_Query($args);
        $rows = [];
        foreach ($query->posts as $post) {
            $row = self::get((int)$post->ID);
            if ($row) $rows[] = $row;
        }
        return ['rows' => $rows, 'total' => (int)$query->found_posts, 'pages' => (int)$query->max_num_pages, 'page' => max(1, $page)];
    }

    public static function summary(int $clientId): array {
        $index = self::clientIndex($clientId);
        $ready = 0; $competitors = 0;
        foreach ($index as $row) {
            if ($row['image_count'] > 0) $ready++;
            if ($row['kind'] === 'competitor') $competitors++;
        }
        return ['total' => count($index), 'with_images' => $ready, 'without_images' => count($index) - $ready, 'competitors' => $competitors];
    }

    /**
     * Light per-request index of a client's catalog (one query, primed meta cache).
     * Used for suggestions, selectors and the 9CM runtime resolution.
     */
    public static function clientIndex(int $clientId): array {
        if ($clientId <= 0) return [];
        if (isset(self::$indexCache[$clientId])) return self::$indexCache[$clientId];
        $posts = get_posts([
            'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'numberposts' => 5000, 'no_found_rows' => true, 'suppress_filters' => true,
            'orderby' => 'title', 'order' => 'ASC',
            'meta_query' => [['key' => self::META_CLIENT, 'value' => (string)$clientId]],
        ]);
        $rows = [];
        foreach ($posts as $post) {
            $id = (int)$post->ID;
            $aliases = array_values(array_filter(array_map('strval', self::jsonList(get_post_meta($id, self::META_ALIASES, true)))));
            $brand = (string)get_post_meta($id, self::META_BRAND, true);
            $images = array_values(array_filter(array_map('absint', self::jsonList(get_post_meta($id, self::META_IMAGES, true)))));
            $rows[$id] = [
                'id' => $id,
                'name' => (string)$post->post_title,
                'reference' => (string)get_post_meta($id, self::META_REFERENCE, true),
                'brand' => $brand,
                'category' => (string)get_post_meta($id, self::META_CATEGORY, true),
                'capacity_cl' => (string)get_post_meta($id, self::META_CAPACITY, true),
                'kind' => (string)get_post_meta($id, self::META_KIND, true) === 'competitor' ? 'competitor' : 'own',
                'active' => (string)get_post_meta($id, self::META_ACTIVE, true) !== '0',
                'image_ids' => $images,
                'image_count' => count($images),
                'keys' => array_values(array_map('strval', (array)get_post_meta($id, self::META_KEY))),
                'labels' => array_values(array_map('strval', (array)get_post_meta($id, self::META_LABEL))),
                'ean' => (string)get_post_meta($id, self::META_EAN, true),
                'origin' => (string)get_post_meta($id, self::META_ORIGIN, true) ?: 'manual',
                'aliases' => $aliases,
                'cardex_ids' => array_values(array_map('intval', self::jsonList(get_post_meta($id, self::META_CARDEX, true)))),
                'hay' => self::normalizeText($post->post_title . ' ' . $brand . ' ' . implode(' ', $aliases)),
            ];
        }
        return self::$indexCache[$clientId] = $rows;
    }

    // ------------------------------------------------------------------
    // Write model
    // ------------------------------------------------------------------

    public static function save(int $productId, array $data): int {
        $clientId = absint($data['client_id'] ?? 0);
        $name = trim(sanitize_text_field((string)($data['name'] ?? '')));
        if ($name === '') throw new \RuntimeException('Indica o nome do produto.');
        if (!$productId) {
            if ($clientId <= 0) throw new \RuntimeException('Seleciona o cliente do produto.');
            $existing = self::findByKeys($clientId, self::identityKeys((string)($data['reference'] ?? ''), $name, $data['capacity_cl'] ?? null, (string)($data['ean'] ?? '')));
            if ($existing) throw new \RuntimeException('Este produto já existe no catálogo deste cliente (#' . $existing . ').');
            $productId = (int)wp_insert_post(['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'post_title' => wp_slash($name)], true);
            if (!$productId || is_wp_error($productId)) throw new \RuntimeException('Não foi possível criar o produto.');
            update_post_meta($productId, self::META_CLIENT, (string)$clientId);
            update_post_meta($productId, self::META_ORIGIN, sanitize_key((string)($data['origin'] ?? 'manual')) ?: 'manual');
        } else {
            if (!self::get($productId)) throw new \RuntimeException('Produto não encontrado.');
            wp_update_post(['ID' => $productId, 'post_title' => wp_slash($name)]);
            $clientId = absint(get_post_meta($productId, self::META_CLIENT, true));
        }
        $reference = sanitize_text_field((string)($data['reference'] ?? ''));
        $capacity = self::capacityKey($data['capacity_cl'] ?? null);
        $ean = self::normalizeEan((string)($data['ean'] ?? ''));
        // A name saved in the back office is manual work: the Cardex sync never renames or removes it.
        if (!empty($data['lock_name'])) update_post_meta($productId, self::META_NAME_LOCKED, '1');
        update_post_meta($productId, self::META_REFERENCE, wp_slash($reference));
        update_post_meta($productId, self::META_BRAND, wp_slash(sanitize_text_field((string)($data['brand'] ?? ''))));
        update_post_meta($productId, self::META_CATEGORY, wp_slash(sanitize_text_field((string)($data['category'] ?? ''))));
        update_post_meta($productId, self::META_CAPACITY, $capacity);
        update_post_meta($productId, self::META_EAN, preg_replace('/[^0-9]/', '', (string)($data['ean'] ?? '')));
        update_post_meta($productId, self::META_KIND, sanitize_key((string)($data['kind'] ?? 'own')) === 'competitor' ? 'competitor' : 'own');
        update_post_meta($productId, self::META_ACTIVE, empty($data['inactive']) ? '1' : '0');
        $aliases = preg_split('/\r\n|\r|\n/', (string)($data['aliases'] ?? '')) ?: [];
        $aliases = array_values(array_unique(array_filter(array_map(static function($alias) { return trim(sanitize_text_field((string)$alias)); }, $aliases))));
        update_post_meta($productId, self::META_ALIASES, wp_slash(wp_json_encode(array_slice($aliases, 0, 40), JSON_UNESCAPED_UNICODE)));
        $images = array_values(array_unique(array_filter(array_map('absint', (array)($data['image_ids'] ?? [])))));
        $images = array_values(array_filter($images, static function(int $attachmentId): bool { return wp_attachment_is_image($attachmentId); }));
        update_post_meta($productId, self::META_IMAGES, wp_json_encode(array_slice($images, 0, 12)));
        self::addMetaValues($productId, self::META_KEY, self::identityKeys($reference, $name, $capacity, $ean));
        foreach ($aliases as $alias) self::addMetaValues($productId, self::META_KEY, self::identityKeys('', $alias, $capacity));
        // Editing in the back office sets the exact campaign list; the next Cardex sync re-adds Cardex campaigns.
        if (!empty($data['replace_projects'])) delete_post_meta($productId, self::META_PROJECT);
        foreach ((array)($data['project_ids'] ?? []) as $projectId) {
            if (absint($projectId) > 0) self::addMetaValues($productId, self::META_PROJECT, [(string)absint($projectId)]);
        }
        self::$labelCache = [];
        self::$indexCache = [];
        return $productId;
    }

    /** Moves identity keys, labels, projects, aliases and images of $sourceId into $targetId, then trashes $sourceId. */
    public static function merge(int $sourceId, int $targetId): void {
        $source = self::get($sourceId);
        $target = self::get($targetId);
        if (!$source || !$target || $sourceId === $targetId) throw new \RuntimeException('Seleciona dois produtos diferentes do catálogo.');
        if ($source['client_id'] !== $target['client_id']) throw new \RuntimeException('Só é possível juntar produtos do mesmo cliente.');
        $sourceKeys = array_map('strval', (array)get_post_meta($sourceId, self::META_KEY));
        // Keys of the previous identity (global article codes) are not carried from Cardex products.
        if ($source['origin'] === 'cardex') $sourceKeys = array_values(array_filter($sourceKeys, static function (string $key): bool { return (bool)preg_match('/^[ecs]:/', $key); }));
        self::addMetaValues($targetId, self::META_KEY, $sourceKeys);
        self::addMetaValues($targetId, self::META_PROJECT, array_map('strval', (array)get_post_meta($sourceId, self::META_PROJECT)));
        self::addMetaValues($targetId, self::META_LABEL, array_map('strval', (array)get_post_meta($sourceId, self::META_LABEL)));
        $aliases = array_values(array_unique(array_merge($target['aliases'], $source['aliases'], [$source['name']])));
        update_post_meta($targetId, self::META_ALIASES, wp_slash(wp_json_encode(array_slice($aliases, 0, 40), JSON_UNESCAPED_UNICODE)));
        $images = array_values(array_unique(array_merge(array_column($target['images'], 'id'), array_column($source['images'], 'id'))));
        update_post_meta($targetId, self::META_IMAGES, wp_json_encode(array_slice($images, 0, 12)));
        $cardex = array_values(array_unique(array_merge($target['cardex_ids'], $source['cardex_ids'])));
        update_post_meta($targetId, self::META_CARDEX, wp_json_encode($cardex));
        foreach ([self::META_REFERENCE => 'reference', self::META_EAN => 'ean', self::META_BRAND => 'brand', self::META_CATEGORY => 'category', self::META_CAPACITY => 'capacity_cl'] as $metaKey => $field) {
            if ((string)$target[$field] === '' && (string)$source[$field] !== '') update_post_meta($targetId, $metaKey, wp_slash((string)$source[$field]));
        }
        wp_trash_post($sourceId);
        self::$labelCache = [];
        self::$indexCache = [];
    }

    /**
     * Manual and briefing products can be removed. Cardex products would be recreated
     * by the next sync, so they are deactivated or merged instead.
     */
    public static function delete(int $productId): void {
        $product = self::get($productId);
        if (!$product) throw new \RuntimeException('Produto não encontrado.');
        if ($product['origin'] === 'cardex') throw new \RuntimeException('Os produtos vindos do cardex não se apagam: desativa-os ou junta-os a outro produto.');
        wp_trash_post($productId);
        self::$labelCache = [];
        self::$indexCache = [];
    }

    // ------------------------------------------------------------------
    // Cross-client reuse (9CN, fieldflow-catalog-cross-client-reuse-v1)
    // ------------------------------------------------------------------

    /**
     * Copies products of another client into $targetClientId. The catalog stays per client:
     * each copy is an independent record of the target client that shares the same image
     * attachments (no file is duplicated) and remembers its source product.
     * A product that already exists in the target (same identity) is not duplicated: it is
     * linked to the source and receives the source images only when it has none.
     *
     * @param string $kind 'keep' keeps the source type; 'own' or 'competitor' sets it.
     */
    public static function copyFromClient(int $targetClientId, array $sourceProductIds, string $kind = 'keep'): array {
        $stats = ['contract' => self::REUSE_CONTRACT, 'copied' => 0, 'linked' => 0, 'images_added' => 0, 'skipped' => 0, 'product_ids' => []];
        if ($targetClientId <= 0) throw new \RuntimeException('Seleciona o cliente de destino.');
        $kind = in_array($kind, ['own', 'competitor'], true) ? $kind : 'keep';
        self::$indexCache = [];
        $keyMap = self::syncKeyMap($targetClientId);
        foreach (array_values(array_unique(array_filter(array_map('absint', $sourceProductIds)))) as $sourceId) {
            $source = self::get($sourceId);
            if (!$source || (int)$source['client_id'] === $targetClientId || (int)$source['client_id'] <= 0) { $stats['skipped']++; continue; }
            $keys = array_values(array_unique(array_merge(
                array_map('strval', (array)get_post_meta($sourceId, self::META_KEY)),
                self::identityKeys((string)$source['reference'], (string)$source['name'], $source['capacity_cl'] === '' ? null : $source['capacity_cl'], (string)$source['ean'])
            )));
            $imageIds = array_values(array_map('intval', array_column($source['images'], 'id')));
            $existing = 0;
            foreach ($keys as $key) if (isset($keyMap[$key])) { $existing = (int)$keyMap[$key]; break; }
            if ($existing) {
                if ((int)get_post_meta($existing, self::META_SOURCE, true) === 0) {
                    update_post_meta($existing, self::META_SOURCE, (string)$sourceId);
                    update_post_meta($existing, self::META_SOURCE_CLIENT, (string)$source['client_id']);
                }
                if ($imageIds && !self::jsonList(get_post_meta($existing, self::META_IMAGES, true))) {
                    update_post_meta($existing, self::META_IMAGES, wp_json_encode(array_slice($imageIds, 0, 12)));
                    $stats['images_added']++;
                }
                $stats['linked']++;
                $stats['product_ids'][] = $existing;
                continue;
            }
            $productId = (int)wp_insert_post(['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'post_title' => wp_slash((string)$source['name'])], true);
            if (!$productId || is_wp_error($productId)) { $stats['skipped']++; continue; }
            update_post_meta($productId, self::META_CLIENT, (string)$targetClientId);
            update_post_meta($productId, self::META_ORIGIN, 'reused');
            update_post_meta($productId, self::META_SOURCE, (string)$sourceId);
            update_post_meta($productId, self::META_SOURCE_CLIENT, (string)$source['client_id']);
            update_post_meta($productId, self::META_KIND, $kind === 'keep' ? ($source['kind'] === 'competitor' ? 'competitor' : 'own') : $kind);
            update_post_meta($productId, self::META_ACTIVE, '1');
            foreach ([self::META_REFERENCE => 'reference', self::META_BRAND => 'brand', self::META_CATEGORY => 'category', self::META_CAPACITY => 'capacity_cl', self::META_EAN => 'ean'] as $metaKey => $field) {
                update_post_meta($productId, $metaKey, wp_slash((string)$source[$field]));
            }
            update_post_meta($productId, self::META_ALIASES, wp_slash(wp_json_encode(array_slice($source['aliases'], 0, 40), JSON_UNESCAPED_UNICODE)));
            update_post_meta($productId, self::META_IMAGES, wp_json_encode(array_slice($imageIds, 0, 12)));
            update_post_meta($productId, self::META_CARDEX, wp_json_encode([]));
            self::addMetaValues($productId, self::META_KEY, $keys);
            foreach ($keys as $key) if (!isset($keyMap[$key])) $keyMap[$key] = $productId;
            $stats['copied']++;
            $stats['product_ids'][] = $productId;
        }
        self::$indexCache = [];
        self::$labelCache = [];
        return $stats;
    }

    /** Brings the current images of the source product: source images first, then the images only this copy has. */
    public static function refreshImagesFromSource(int $productId): int {
        $product = self::get($productId);
        if (!$product || !$product['source_product_id']) throw new \RuntimeException('Este produto não foi reutilizado de outro cliente.');
        $source = self::get((int)$product['source_product_id']);
        if (!$source) throw new \RuntimeException('O produto de origem já não existe.');
        $images = array_values(array_unique(array_merge(array_map('intval', array_column($source['images'], 'id')), array_map('intval', array_column($product['images'], 'id')))));
        update_post_meta($productId, self::META_IMAGES, wp_json_encode(array_slice($images, 0, 12)));
        self::$indexCache = [];
        return count($source['images']);
    }

    /** Catalog size per client, in one query (client selector). */
    public static function countsByClient(): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.meta_value AS client_id, COUNT(*) AS total FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE p.post_type=%s AND p.post_status='publish' AND pm.meta_key=%s GROUP BY pm.meta_value",
            self::POST_TYPE, self::META_CLIENT
        ), ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $clientId = absint($row['client_id'] ?? 0);
            if ($clientId > 0) $out[$clientId] = (int)($row['total'] ?? 0);
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Briefing label mapping (Share Space "marca vs concorrência")
    // ------------------------------------------------------------------

    /**
     * Distinct labels used by Share Space questions of the client: generated
     * context questions and published/draft briefing sections.
     */
    public static function briefingLabels(int $clientId, int $projectId = 0): array {
        global $wpdb;
        $labels = [];
        $add = static function(string $label, string $side, int $project, string $source) use (&$labels): void {
            $label = trim($label);
            if ($label === '' || in_array(self::normalizeText($label), ['concorrencia', 'referencia a', 'referencia b', 'produto pernod ricard'], true)) return;
            $key = self::normalizeText($label);
            if ($key === '') return;
            if (!isset($labels[$key])) $labels[$key] = ['label' => $label, 'normalized' => $key, 'sides' => [], 'projects' => [], 'sources' => [], 'occurrences' => 0];
            $labels[$key]['sides'][$side] = true;
            if ($project) $labels[$key]['projects'][$project] = true;
            $labels[$key]['sources'][$source] = true;
            $labels[$key]['occurrences']++;
        };
        $collect = static function(array $config, int $project, string $source) use ($add): void {
            foreach ((array)($config['rows'] ?? []) as $row) {
                if (!is_array($row)) continue;
                $add((string)($row['pr_label'] ?? ''), 'own', $project, $source);
                $add((string)($row['competitor_label'] ?? ''), 'competitor', $project, $source);
            }
        };
        $contextTable = $wpdb->prefix . 'routespro_report_context_questions';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $contextTable)) === $contextTable) {
            // Context questions are generated per store: the same configuration repeats, so read distinct configurations.
            $sql = "SELECT DISTINCT project_id, question_config_json FROM {$contextTable} WHERE client_id=%d AND question_type='share_space_matrix' AND question_config_json IS NOT NULL";
            $args = [$clientId];
            if ($projectId > 0) { $sql .= ' AND project_id=%d'; $args[] = $projectId; }
            $sql .= ' LIMIT 800';
            foreach ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [] as $row) {
                $config = json_decode((string)($row['question_config_json'] ?? ''), true);
                if (is_array($config)) $collect($config, absint($row['project_id'] ?? 0), 'context_question');
            }
        }
        $briefings = $wpdb->prefix . 'routespro_briefings';
        $sections = $wpdb->prefix . 'routespro_briefing_sections';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $sections)) === $sections && class_exists('\\RoutesPro\\Briefings\\ShareSpaceMatrix')) {
            $sql = "SELECT b.project_id, s.section_type, s.content_json FROM {$sections} s INNER JOIN {$briefings} b ON b.id=s.briefing_id WHERE b.client_id=%d AND s.section_type='share_space' AND b.status<>'archived'";
            $args = [$clientId];
            if ($projectId > 0) { $sql .= ' AND b.project_id=%d'; $args[] = $projectId; }
            $sql .= ' ORDER BY b.valid_from DESC, s.id DESC LIMIT 600';
            foreach ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [] as $row) {
                $content = json_decode((string)($row['content_json'] ?? ''), true);
                if (!is_array($content)) continue;
                $config = \RoutesPro\Briefings\ShareSpaceMatrix::configFromSection($content, 'share_space', []);
                $collect($config, absint($row['project_id'] ?? 0), 'briefing_section');
            }
        }
        foreach ($labels as $key => $row) {
            $labels[$key]['sides'] = array_keys($row['sides']);
            $labels[$key]['projects'] = array_map('intval', array_keys($row['projects']));
            $labels[$key]['sources'] = array_keys($row['sources']);
            $labels[$key]['product_ids'] = self::productIdsForLabel($clientId, $row['label']);
        }
        uasort($labels, static function(array $a, array $b): int {
            $am = $a['product_ids'] ? 1 : 0; $bm = $b['product_ids'] ? 1 : 0;
            if ($am !== $bm) return $am <=> $bm;
            return strcasecmp($a['label'], $b['label']);
        });
        return array_values($labels);
    }

    public static function productIdsForLabel(int $clientId, string $label): array {
        $normalized = self::normalizeText($label);
        if ($normalized === '') return [];
        $cacheKey = $clientId . '|' . $normalized;
        if (isset(self::$labelCache[$cacheKey])) return self::$labelCache[$cacheKey];
        $ids = get_posts([
            'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => 30, 'no_found_rows' => true, 'suppress_filters' => true,
            'orderby' => 'title', 'order' => 'ASC',
            'meta_query' => ['relation' => 'AND', ['key' => self::META_CLIENT, 'value' => (string)$clientId], ['key' => self::META_LABEL, 'value' => $normalized]],
        ]);
        return self::$labelCache[$cacheKey] = array_map('intval', $ids);
    }

    /** Sets the exact product set for a briefing label (one label may map to several SKUs). */
    public static function mapLabel(int $clientId, string $label, array $productIds): int {
        $normalized = self::normalizeText($label);
        if ($clientId <= 0 || $normalized === '') throw new \RuntimeException('Rótulo ou cliente inválido.');
        $productIds = array_values(array_unique(array_filter(array_map('absint', $productIds))));
        foreach (self::productIdsForLabel($clientId, $label) as $existing) {
            if (!in_array($existing, $productIds, true)) delete_post_meta($existing, self::META_LABEL, $normalized);
        }
        $mapped = 0;
        foreach ($productIds as $productId) {
            $product = self::get($productId);
            if (!$product || (int)$product['client_id'] !== $clientId) continue;
            self::addMetaValues($productId, self::META_LABEL, [$normalized]);
            $mapped++;
        }
        self::$labelCache = [];
        return $mapped;
    }

    /** Ranked suggestions using normalized name, brand and alias token overlap. */
    public static function suggestionsForLabel(int $clientId, string $label, int $limit = 5): array {
        $tokens = array_values(array_filter(explode(' ', self::normalizeText($label)), static function($t) { return strlen($t) > 1 && !in_array($t, ['cl', 'ml', 'lt', 'vs', 'de', 'da', 'do'], true); }));
        if (!$tokens) return [];
        $scored = [];
        foreach (self::clientIndex($clientId) as $id => $row) {
            $hay = (string)$row['hay'];
            if ($hay === '') continue;
            $words = explode(' ', $hay);
            $hits = 0;
            foreach ($tokens as $token) {
                foreach ($words as $word) { if ($word === $token || (strlen($token) >= 4 && strpos($word, $token) === 0)) { $hits++; break; } }
            }
            if ($hits === 0) continue;
            $scored[] = ['id' => (int)$id, 'name' => (string)$row['name'], 'score' => round($hits / count($tokens), 2), 'kind' => (string)$row['kind'], 'image_count' => (int)$row['image_count']];
        }
        usort($scored, static function($a, $b) { return $b['score'] <=> $a['score'] ?: strcasecmp($a['name'], $b['name']); });
        return array_slice($scored, 0, max(1, $limit));
    }

    // ------------------------------------------------------------------
    // Runtime resolution used by form questions (9CM)
    // ------------------------------------------------------------------

    public static function resolveProduct(int $clientId, string $reference, string $name, $capacity = null): ?array {
        $id = self::resolveProductId($clientId, $reference, $name, $capacity);
        return $id ? self::get($id) : null;
    }

    /** Index-backed resolution: no query per row once the client index is loaded. */
    public static function resolveProductId(int $clientId, string $reference, string $name, $capacity = null): int {
        return self::resolveProductIdScoped($clientId, $reference, $name, $capacity, '', '');
    }

    /**
     * 9CP: resolution of a Cardex row. The article code counts inside its insignia ($scope);
     * an unscoped code only resolves when it is unique for the client.
     */
    public static function resolveProductIdScoped(int $clientId, string $reference, string $name, $capacity = null, string $scope = '', string $ean = ''): int {
        if ($clientId <= 0) return 0;
        $mapKey = 'keys:' . $clientId;
        if (!isset(self::$indexCache[$mapKey])) {
            $map = []; $codes = [];
            foreach (self::clientIndex($clientId) as $id => $row) {
                if (empty($row['active'])) continue;
                foreach ($row['keys'] as $key) {
                    if (!isset($map[$key])) $map[$key] = (int)$id;
                    if (preg_match('/^c:[^:]+:(.+)$/', $key, $m)) $codes[$m[1]][(int)$id] = true;
                }
                if ($row['ean'] !== '') { $eanKey = 'e:' . self::normalizeEan($row['ean']); if (!isset($map[$eanKey])) $map[$eanKey] = (int)$id; }
            }
            foreach ($codes as $code => $ids) if (count($ids) === 1 && !isset($map['r:' . $code])) $map['r:' . $code] = (int)array_key_first($ids);
            self::$indexCache[$mapKey] = $map;
        }
        $map = self::$indexCache[$mapKey];
        $candidates = self::identityKeys($reference, $name, $capacity, $ean, $scope);
        if ($scope !== '' && $reference !== '') $candidates[] = 'r:' . self::normalizeReference($reference);
        $candidates = array_merge($candidates, self::identityKeys('', $name, null), self::legacyKeys('', $name, $capacity));
        foreach ($candidates as $key) if (isset($map[$key])) return $map[$key];
        return 0;
    }

    public static function primaryThumb(?array $product): string {
        if (!$product || empty($product['images'][0])) return '';
        return (string)($product['images'][0]['thumb'] ?? '');
    }

    // ------------------------------------------------------------------
    // Fronts questions visuals (9CM, fieldflow-fronts-mobile-catalog-visual-v1)
    // ------------------------------------------------------------------

    /** Client of a form context: explicit client, then campaign, then the Cardex of the question. */
    public static function clientForContext(array $context, int $cardexId = 0): int {
        $clientId = absint($context['client_id'] ?? 0);
        if ($clientId > 0) return $clientId;
        $clientId = self::clientForCardex(['project_id' => absint($context['project_id'] ?? 0)]);
        if ($clientId > 0 || $cardexId <= 0 || !class_exists(ProductCardex::class)) return $clientId;
        $cardex = ProductCardex::get_cardex($cardexId);
        return $cardex ? self::clientForCardex($cardex) : 0;
    }

    /** Thumbnail, large image and display name of a catalog product; empty image fields when it has none. */
    public static function visualForProduct(int $clientId, int $productId): array {
        $row = self::clientIndex($clientId)[$productId] ?? null;
        if (!$row) return [];
        $visual = ['product_id' => (int)$productId, 'name' => (string)$row['name'], 'kind' => (string)$row['kind'], 'thumb' => '', 'full' => ''];
        $attachmentId = (int)($row['image_ids'][0] ?? 0);
        if ($attachmentId > 0) {
            $thumb = wp_get_attachment_image_src($attachmentId, 'thumbnail');
            $full = wp_get_attachment_image_src($attachmentId, 'large');
            $visual['thumb'] = (string)($thumb[0] ?? $full[0] ?? '');
            $visual['full'] = (string)($full[0] ?? $thumb[0] ?? '');
        }
        return $visual;
    }

    /** Loads the attachment records of the given products in one query before their images are read. */
    public static function primeImages(int $clientId, array $productIds): void {
        $index = self::clientIndex($clientId);
        $attachments = [];
        foreach ($productIds as $productId) {
            $first = (int)($index[(int)$productId]['image_ids'][0] ?? 0);
            if ($first > 0) $attachments[$first] = true;
        }
        if (!$attachments) return;
        get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'post__in' => array_keys($attachments), 'numberposts' => count($attachments), 'no_found_rows' => true, 'suppress_filters' => true]);
    }

    /** Visuals for product rows (index => visual), resolved through the catalog identity of each row. */
    public static function visualsForRows(int $clientId, array $rows): array {
        if ($clientId <= 0 || !$rows || !self::clientIndex($clientId)) return [];
        $resolved = [];
        foreach ($rows as $index => $row) {
            $row = (array)$row;
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') continue;
            $scope = self::scopeForCardexId(absint($row['cardex_id'] ?? 0));
            $productId = self::resolveProductIdScoped($clientId, (string)($row['ref'] ?? $row['reference'] ?? ''), $name, ($row['capacity_cl'] ?? '') === '' ? null : $row['capacity_cl'], $scope);
            if ($productId) $resolved[$index] = $productId;
        }
        self::primeImages($clientId, array_values($resolved));
        $out = [];
        foreach ($resolved as $index => $productId) {
            $visual = self::visualForProduct($clientId, $productId);
            if ($visual) $out[$index] = $visual;
        }
        return $out;
    }

    /** Insignia scope of a Cardex id (cached per request). */
    public static function scopeForCardexId(int $cardexId): string {
        static $cache = [];
        if ($cardexId <= 0 || !class_exists(ProductCardex::class)) return '';
        if (!array_key_exists($cardexId, $cache)) {
            $cardex = ProductCardex::get_cardex($cardexId);
            $cache[$cardexId] = $cardex ? self::cardexScope($cardex) : '';
        }
        return $cache[$cardexId];
    }

    /** Display names of the insignias of a product's Cardex (Continente, Pingo Doce, ...), without repeats. */
    public static function insigniasForCardexIds(array $cardexIds): array {
        static $cache = [];
        $out = [];
        if (!class_exists(ProductCardex::class)) return $out;
        foreach (array_values(array_unique(array_filter(array_map('absint', $cardexIds)))) as $cardexId) {
            if (!array_key_exists($cardexId, $cache)) {
                $cardex = ProductCardex::get_cardex($cardexId);
                $label = $cardex ? trim((string)($cardex['insignia'] ?? '')) : '';
                if ($cardex && $label === '') $label = trim((string)($cardex['name'] ?? ''));
                $cache[$cardexId] = $label;
            }
            if ($cache[$cardexId] !== '') $out[self::normalizeText($cache[$cardexId])] = $cache[$cardexId];
        }
        return array_values($out);
    }

    /**
     * Probable duplicates the automatic identity did not join (names written very differently by
     * two insignias). Only products with the same capacity, never listed together in the same
     * insignia, with the same numbers (ages) and most words in common. Nothing is merged here:
     * the back office shows the pairs and the user confirms.
     */
    public static function duplicateSuggestions(int $clientId, int $limit = 40): array {
        $index = self::clientIndex($clientId);
        $distinct = self::distinctPairs($clientId);
        $buckets = [];
        foreach ($index as $id => $row) {
            if (empty($row['active'])) continue;
            $capacity = self::normalizeCapacity($row['capacity_cl'] === '' ? null : $row['capacity_cl'], (string)$row['name']);
            if ($capacity === '') continue;
            $tokens = array_values(array_filter(explode(' ', self::nameSignature((string)$row['name'], $capacity)), static function ($t) { return strlen($t) > 1 || ctype_digit($t); }));
            if (!$tokens) continue;
            $scopes = [];
            foreach ($row['cardex_ids'] as $cardexId) { $scope = self::scopeForCardexId((int)$cardexId); if ($scope !== '') $scopes[$scope] = true; }
            $numbers = array_values(array_filter($tokens, 'ctype_digit'));
            sort($numbers);
            $buckets[$capacity][] = ['id' => (int)$id, 'tokens' => $tokens, 'scopes' => $scopes, 'numbers' => implode(',', $numbers)];
        }
        $matches = static function (array $from, array $to): int {
            $hits = 0;
            foreach ($from as $a) {
                foreach ($to as $b) {
                    if ($a === $b || (strlen($a) >= 3 && strlen($b) >= 3 && !ctype_digit($a) && !ctype_digit($b) && (strpos($a, $b) === 0 || strpos($b, $a) === 0))) { $hits++; break; }
                }
            }
            return $hits;
        };
        $pairs = [];
        foreach ($buckets as $capacity => $rows) {
            $rows = array_slice($rows, 0, 800);
            $count = count($rows);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $rows[$i]; $b = $rows[$j];
                    if ($a['scopes'] && $b['scopes'] && array_intersect_key($a['scopes'], $b['scopes'])) continue;
                    if ($a['numbers'] !== $b['numbers']) continue;
                    $pairKey = min($a['id'], $b['id']) . '-' . max($a['id'], $b['id']);
                    if (isset($distinct[$pairKey])) continue;
                    $hitsA = $matches($a['tokens'], $b['tokens']);
                    $hitsB = $matches($b['tokens'], $a['tokens']);
                    $strong = false;
                    foreach ($a['tokens'] as $token) if (strlen($token) >= 4 && in_array($token, $b['tokens'], true)) { $strong = true; break; }
                    if (!$strong) continue;
                    $similarity = ($hitsA + $hitsB) / (count($a['tokens']) + count($b['tokens']));
                    if ($similarity < 0.6) continue;
                    $keep = self::keepScore($index[$a['id']]) >= self::keepScore($index[$b['id']]) ? $a['id'] : $b['id'];
                    $pairs[] = ['keep' => $keep, 'drop' => $keep === $a['id'] ? $b['id'] : $a['id'], 'pair' => $pairKey, 'similarity' => round($similarity, 2), 'capacity_cl' => (string)$capacity];
                }
            }
        }
        usort($pairs, static function ($x, $y) { return $y['similarity'] <=> $x['similarity'] ?: strcmp($x['pair'], $y['pair']); });
        return array_slice($pairs, 0, max(1, $limit));
    }

    /** Remembers that two products are different, so they are not suggested again. */
    public static function markDistinct(int $clientId, int $productA, int $productB): void {
        if ($clientId <= 0 || $productA <= 0 || $productB <= 0 || $productA === $productB) throw new \RuntimeException('Produtos inválidos.');
        foreach ([$productA, $productB] as $productId) {
            if ((int)(self::get($productId)['client_id'] ?? 0) !== $clientId) throw new \RuntimeException('Os produtos não são deste cliente.');
        }
        $all = get_option('fieldflow_catalog_distinct_pairs', []);
        $all = is_array($all) ? $all : [];
        $pairs = is_array($all[$clientId] ?? null) ? $all[$clientId] : [];
        $pairs[min($productA, $productB) . '-' . max($productA, $productB)] = 1;
        $all[$clientId] = array_slice($pairs, -3000, null, true);
        update_option('fieldflow_catalog_distinct_pairs', $all, false);
    }

    private static function distinctPairs(int $clientId): array {
        $all = get_option('fieldflow_catalog_distinct_pairs', []);
        return is_array($all) && is_array($all[$clientId] ?? null) ? $all[$clientId] : [];
    }

    /** Which product survives a merge: images first, then manual work, briefing labels, active state and the number of Cardex where it is sold. */
    private static function keepScore(array $row): int {
        return ((int)$row['image_count'] * 100) + ((string)$row['origin'] !== 'cardex' ? 50 : 0) + (!empty($row['labels']) ? 20 : 0) + (!empty($row['active']) ? 5 : 0) + min(4, count((array)($row['cardex_ids'] ?? [])));
    }

    /** Appends an image (for example a reference photographed in the field) to a product, up to 12. */
    public static function addImage(int $productId, int $attachmentId): int {
        if (!self::get($productId) || $attachmentId <= 0) throw new \RuntimeException('Produto ou imagem inválidos.');
        $images = array_values(array_map('intval', self::jsonList(get_post_meta($productId, self::META_IMAGES, true))));
        if (!in_array($attachmentId, $images, true)) {
            if (count($images) >= 12) throw new \RuntimeException('Este produto já tem 12 imagens.');
            $images[] = $attachmentId;
            update_post_meta($productId, self::META_IMAGES, wp_json_encode($images));
        }
        self::$indexCache = [];
        return count($images);
    }

    /** Visuals of the products mapped to a briefing label, products with images first. */
    public static function visualsForLabel(int $clientId, string $label, int $limit = 3): array {
        if ($clientId <= 0 || trim($label) === '') return ['items' => [], 'total' => 0];
        $ids = self::productIdsForLabel($clientId, $label);
        if (!$ids) return ['items' => [], 'total' => 0];
        self::primeImages($clientId, $ids);
        $index = self::clientIndex($clientId);
        $items = [];
        foreach ($ids as $productId) {
            if (empty($index[(int)$productId]['active'])) continue;
            $visual = self::visualForProduct($clientId, (int)$productId);
            if ($visual) $items[] = $visual;
        }
        usort($items, static function(array $a, array $b): int { return ($b['thumb'] !== '' ? 1 : 0) <=> ($a['thumb'] !== '' ? 1 : 0); });
        return ['items' => array_slice($items, 0, max(1, $limit)), 'total' => count($items)];
    }

    // ------------------------------------------------------------------

    private static function addMetaValues(int $postId, string $metaKey, array $values): void {
        $existing = array_map('strval', (array)get_post_meta($postId, $metaKey));
        foreach (array_values(array_unique(array_filter(array_map('strval', $values), 'strlen'))) as $value) {
            if (in_array($value, $existing, true)) continue;
            add_post_meta($postId, $metaKey, wp_slash($value));
            $existing[] = $value;
        }
    }

    private static function jsonList($raw): array {
        if (is_array($raw)) return array_values($raw);
        $decoded = json_decode((string)$raw, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }
}
