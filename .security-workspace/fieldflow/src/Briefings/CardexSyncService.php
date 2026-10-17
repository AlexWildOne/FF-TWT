<?php
namespace RoutesPro\Briefings;

use RoutesPro\Core\AuditLog;
use RoutesPro\Forms\ProductCardex;
use RoutesPro\Stocks\StockService;

if (!defined('ABSPATH')) exit;

/**
 * Materialises the Cardex blocks embedded in a weekly operational briefing.
 *
 * The briefing remains the immutable weekly source/version. The active Product
 * Cardex is updated in place so item IDs and store overrides remain stable.
 * Missing items are only deactivated when they were previously managed by this
 * weekly sync. Manual items and per-store overrides are never deleted.
 */
final class CardexSyncService {
    private const MANAGED_BY = 'briefing_weekly';

    private const RETAILERS = [
        'sonae' => ['label'=>'Sonae', 'insignia'=>'Continente'],
        'pingo_doce' => ['label'=>'Pingo Doce', 'insignia'=>'Pingo Doce'],
        'auchan' => ['label'=>'Auchan', 'insignia'=>'Auchan'],
        'intermarche' => ['label'=>'Intermarché', 'insignia'=>'Intermarché'],
        'eci' => ['label'=>'El Corte Inglés', 'insignia'=>'El Corte Inglés'],
    ];

    public static function syncBriefing(int $briefingId): array {
        global $wpdb;
        $briefingId = absint($briefingId);
        if (!$briefingId) return ['ok'=>false,'reason'=>'invalid_briefing','cardex'=>[]];

        $briefing = BriefingService::get($briefingId);
        if (!$briefing) return ['ok'=>false,'reason'=>'briefing_missing','cardex'=>[]];
        $clientId = absint($briefing['client_id'] ?? 0);
        $projectId = absint($briefing['project_id'] ?? 0);
        if (!$clientId || !$projectId) return ['ok'=>false,'reason'=>'briefing_scope_missing','cardex'=>[]];

        ProductCardex::install();
        $sectionsTable = BriefingService::table('briefing_sections');
        $sections = $wpdb->get_results($wpdb->prepare(
            "SELECT id,retailer_key,title,content_json FROM {$sectionsTable} WHERE briefing_id=%d AND section_type='cardex' ORDER BY id ASC",
            $briefingId
        ), ARRAY_A) ?: [];
        if (!$sections) {
            $summary = ['ok'=>true,'briefing_id'=>$briefingId,'cardex'=>[],'totals'=>['retailers'=>0,'items'=>0,'created'=>0,'updated'=>0,'deactivated'=>0,'anomalies'=>0]];
            self::persistSummary($briefingId, $summary);
            return $summary;
        }

        // Weekly Cardex publication is an atomic boundary. If the database cannot
        // open a transaction, do not mutate the live assortment at all.
        if ($wpdb->query('START TRANSACTION') === false) {
            $summary = ['ok'=>false,'briefing_id'=>$briefingId,'reason'=>'transaction_unavailable','cardex'=>[],'totals'=>['retailers'=>0,'items'=>0,'created'=>0,'updated'=>0,'deactivated'=>0,'unchanged'=>0,'anomalies'=>0,'linked_locations'=>0]];
            self::persistSummary($briefingId, $summary);
            return $summary;
        }

        $results = [];
        try {
            foreach ($sections as $section) {
                $retailer = sanitize_key((string)($section['retailer_key'] ?? ''));
                if (!isset(self::RETAILERS[$retailer])) continue;
                $content = json_decode((string)($section['content_json'] ?? ''), true);
                if (!is_array($content)) $content = [];
                $items = array_values(array_filter((array)($content['items'] ?? []), 'is_array'));
                if (!$items) continue;
                $results[$retailer] = self::syncRetailer($briefing, $retailer, $items, (array)($content['anomalies'] ?? []));
            }
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            $summary = [
                'ok'=>false,'briefing_id'=>$briefingId,'reason'=>'cardex_sync_write_failed','cardex'=>$results,
                'error'=>substr((string)$error->getMessage(), 0, 600),
                'totals'=>['retailers'=>count($results),'items'=>0,'created'=>0,'updated'=>0,'deactivated'=>0,'unchanged'=>0,'anomalies'=>0,'linked_locations'=>0],
            ];
            self::persistSummary($briefingId, $summary);
            return $summary;
        }

        $totals = ['retailers'=>count($results),'items'=>0,'created'=>0,'updated'=>0,'deactivated'=>0,'unchanged'=>0,'anomalies'=>0,'linked_locations'=>0];
        foreach ($results as $result) {
            foreach (['items','created','updated','deactivated','unchanged','anomalies','linked_locations'] as $key) $totals[$key] += (int)($result[$key] ?? 0);
        }
        $allOk = true;
        foreach ($results as $result) if (empty($result['ok'])) { $allOk = false; break; }
        if (!$allOk) {
            $wpdb->query('ROLLBACK');
            $summary = ['ok'=>false,'briefing_id'=>$briefingId,'week_no'=>absint($briefing['week_no'] ?? 0),'year_no'=>absint($briefing['year_no'] ?? 0),'reason'=>'cardex_sync_incomplete','rolled_back'=>true,'cardex'=>$results,'totals'=>$totals];
            self::persistSummary($briefingId, $summary);
            return $summary;
        }
        if ($wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            $summary = ['ok'=>false,'briefing_id'=>$briefingId,'week_no'=>absint($briefing['week_no'] ?? 0),'year_no'=>absint($briefing['year_no'] ?? 0),'reason'=>'cardex_sync_commit_failed','rolled_back'=>true,'cardex'=>$results,'totals'=>$totals];
            self::persistSummary($briefingId, $summary);
            return $summary;
        }
        $summary = ['ok'=>true,'briefing_id'=>$briefingId,'week_no'=>absint($briefing['week_no'] ?? 0),'year_no'=>absint($briefing['year_no'] ?? 0),'cardex'=>$results,'totals'=>$totals];
        self::persistSummary($briefingId, $summary);
        self::invalidateProjectMobileCaches($projectId);
        return $summary;
    }

    private static function syncRetailer(array $briefing, string $retailer, array $items, array $anomalies): array {
        global $wpdb;
        $cfg = self::RETAILERS[$retailer];
        $clientId = absint($briefing['client_id'] ?? 0);
        $projectId = absint($briefing['project_id'] ?? 0);
        $briefingId = absint($briefing['id'] ?? 0);
        $week = absint($briefing['week_no'] ?? 0);
        $year = absint($briefing['year_no'] ?? 0);

        $cardex = self::findOrCreateCardex($clientId, $projectId, $retailer, $cfg);
        $cardexId = absint($cardex['id'] ?? 0);
        if (!$cardexId) return ['ok'=>false,'retailer_key'=>$retailer,'reason'=>'cardex_create_failed','items'=>count($items),'created'=>0,'updated'=>0,'deactivated'=>0,'unchanged'=>0,'anomalies'=>count($anomalies),'linked_locations'=>0];

        $beforeMeta = self::decode((string)($cardex['meta_json'] ?? ''));
        $existing = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . ProductCardex::table_items() . ' WHERE cardex_id=%d ORDER BY id ASC',
            $cardexId
        ), ARRAY_A) ?: [];
        $index = self::itemIndex($existing);
        $seenIds = [];
        $created = 0; $updated = 0; $unchanged = 0;
        $sort = 0;

        foreach ($items as $source) {
            $sort += 10;
            $normal = self::normaliseIncomingItem($source, $retailer, $briefingId, $week, $year);
            if ($normal['product_name'] === '') continue;
            $itemId = self::matchExistingId($normal, $index);
            if ($itemId) {
                $current = $index['rows'][$itemId] ?? [];
                $meta = array_merge(self::decode((string)($current['meta_json'] ?? '')), $normal['meta']);
                $payload = [
                    'category'=>$normal['category'],
                    'reference'=>$normal['reference'],
                    'item_type'=>$normal['item_type'],
                    'product_name'=>$normal['product_name'],
                    'capacity_cl'=>$normal['capacity_cl'],
                    'units_per_case'=>$normal['units_per_case'],
                    'sort_order'=>$sort,
                    'is_active'=>1,
                    'meta_json'=>wp_json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                    'updated_at'=>current_time('mysql'),
                ];
                if (self::itemChanged($current, $payload)) {
                    if ($wpdb->update(ProductCardex::table_items(), $payload, ['id'=>$itemId,'cardex_id'=>$cardexId]) === false) {
                        throw new \RuntimeException('Falha ao atualizar um item do Cardex semanal: ' . (string)$wpdb->last_error);
                    }
                    $updated++;
                } else {
                    $unchanged++;
                }
            } else {
                $payload = [
                    'cardex_id'=>$cardexId,
                    'category'=>$normal['category'],
                    'reference'=>$normal['reference'],
                    'item_type'=>$normal['item_type'],
                    'product_name'=>$normal['product_name'],
                    'capacity_cl'=>$normal['capacity_cl'],
                    'units_per_case'=>$normal['units_per_case'],
                    'sort_order'=>$sort,
                    'is_active'=>1,
                    'meta_json'=>wp_json_encode($normal['meta'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                    'created_at'=>current_time('mysql'),
                    'updated_at'=>current_time('mysql'),
                ];
                if ($wpdb->insert(ProductCardex::table_items(), $payload) === false) {
                    throw new \RuntimeException('Falha ao criar um item do Cardex semanal: ' . (string)$wpdb->last_error);
                }
                $itemId = (int)$wpdb->insert_id;
                if (!$itemId) throw new \RuntimeException('O item do Cardex semanal foi inserido sem identificador persistente.');
                if ($itemId) {
                    $created++;
                    $index = self::itemIndex(array_merge($existing, [array_merge($payload, ['id'=>$itemId])]));
                    $existing[] = array_merge($payload, ['id'=>$itemId]);
                }
            }
            if ($itemId) $seenIds[$itemId] = true;
        }

        $deactivated = 0;
        foreach ($existing as $row) {
            $id = absint($row['id'] ?? 0);
            if (!$id || isset($seenIds[$id]) || empty($row['is_active'])) continue;
            $meta = self::decode((string)($row['meta_json'] ?? ''));
            if (($meta['managed_by'] ?? '') !== self::MANAGED_BY || sanitize_key((string)($meta['retailer_key'] ?? '')) !== $retailer) continue;
            if ($wpdb->update(ProductCardex::table_items(), ['is_active'=>0,'updated_at'=>current_time('mysql')], ['id'=>$id,'cardex_id'=>$cardexId]) === false) {
                throw new \RuntimeException('Falha ao desativar um item antigo do Cardex semanal: ' . (string)$wpdb->last_error);
            }
            $deactivated++;
        }

        $linkedLocations = self::linkProjectLocations($cardexId, $projectId, (string)$cfg['insignia']);
        $cardexMeta = array_merge($beforeMeta, [
            'managed_by'=>self::MANAGED_BY,
            'retailer_key'=>$retailer,
            'source_briefing_id'=>$briefingId,
            'source_week'=>$week,
            'source_year'=>$year,
            'source_hash'=>(string)($briefing['source_hash'] ?? ''),
            'source_filename'=>(string)($briefing['source_filename'] ?? ''),
            'last_sync_at'=>current_time('mysql'),
            'last_sync_diff'=>[
                'incoming'=>count($items),'created'=>$created,'updated'=>$updated,'unchanged'=>$unchanged,'deactivated'=>$deactivated,'anomalies'=>count($anomalies),
            ],
        ]);
        if ($wpdb->update(ProductCardex::table_cardex(), [
            'name'=>'Pernod Ricard · ' . (string)$cfg['label'],
            'insignia'=>(string)$cfg['insignia'],
            'client_id'=>$clientId,
            'project_id'=>$projectId,
            'status'=>'active',
            'meta_json'=>wp_json_encode($cardexMeta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'updated_at'=>current_time('mysql'),
        ], ['id'=>$cardexId]) === false) {
            throw new \RuntimeException('Falha ao gravar o estado do Cardex semanal: ' . (string)$wpdb->last_error);
        }

        $after = [
            'briefing_id'=>$briefingId,'week_no'=>$week,'year_no'=>$year,'cardex_id'=>$cardexId,'retailer_key'=>$retailer,
            'incoming'=>count($items),'created'=>$created,'updated'=>$updated,'unchanged'=>$unchanged,'deactivated'=>$deactivated,'anomalies'=>count($anomalies),'linked_locations'=>$linkedLocations,
        ];
        if (class_exists(AuditLog::class)) {
            AuditLog::record('cardex.briefing_sync', 'product_cardex', $cardexId, ['meta'=>$beforeMeta], $after, [
                'client_id'=>$clientId,'project_id'=>$projectId,'actor_user_id'=>get_current_user_id(),
            ], ['source_briefing_id'=>$briefingId,'retailer_key'=>$retailer]);
        }
        do_action('fieldflow_mapping_source_changed', 'cardex_briefing', $cardexId);

        return array_merge(['ok'=>true,'retailer_key'=>$retailer,'retailer_label'=>$cfg['label'],'cardex_id'=>$cardexId,'items'=>count($items),'anomaly_details'=>$anomalies], $after);
    }

    private static function findOrCreateCardex(int $clientId, int $projectId, string $retailer, array $cfg): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . ProductCardex::table_cardex() . " WHERE client_id=%d AND project_id=%d AND status='active' ORDER BY id DESC",
            $clientId, $projectId
        ), ARRAY_A) ?: [];
        $wanted = StockService::brandFamily((string)$cfg['insignia']);
        $wantedSubtype = \RoutesPro\Stores\StoreIdentityAuthority::subtype((string)$cfg['insignia']);
        foreach ($rows as $row) {
            $meta = self::decode((string)($row['meta_json'] ?? ''));
            // 9DP: a clone must never be seized and rewritten by the weekly briefing sync.
            if (!empty($meta['clone_source_id'])) continue;
            // 9DR: importing one generic Sonae section must never turn a CBD
            // or MDL Cardex into CNT (nor take ownership of manually curated
            // Pingo Doce / other Cardex). Only an established weekly-managed
            // target with the same retailer and verified Sonae subtype is reused.
            if (($meta['retailer_key'] ?? '') !== $retailer
                || ($meta['managed_by'] ?? '') !== self::MANAGED_BY) continue;
            $label = trim((string)($row['insignia'] ?? ''));
            if ($label === '') $label = trim((string)($row['name'] ?? ''));
            $family = StockService::brandFamily($label);
            if ($wanted === '' || $family !== $wanted) continue;
            if ($wanted === 'continente'
                && \RoutesPro\Stores\StoreIdentityAuthority::subtype($label) !== $wantedSubtype) continue;
            return $row;
        }
        $data = [
            'name'=>'Pernod Ricard · ' . (string)$cfg['label'],
            'insignia'=>(string)$cfg['insignia'],
            'client_id'=>$clientId,'project_id'=>$projectId,'status'=>'active',
            'meta_json'=>wp_json_encode(['managed_by'=>self::MANAGED_BY,'retailer_key'=>$retailer], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'),
        ];
        if ($wpdb->insert(ProductCardex::table_cardex(), $data) === false) {
            throw new \RuntimeException('Falha ao criar o Cardex semanal: ' . (string)$wpdb->last_error);
        }
        $data['id'] = (int)$wpdb->insert_id;
        if (!$data['id']) throw new \RuntimeException('O Cardex semanal foi criado sem identificador persistente.');
        return $data;
    }

    private static function normaliseIncomingItem(array $source, string $retailer, int $briefingId, int $week, int $year): array {
        $reference = sanitize_text_field((string)($source['code'] ?? ''));
        $purchaseRef = sanitize_text_field((string)($source['purchase_ref'] ?? ''));
        $saleRef = sanitize_text_field((string)($source['sale_ref'] ?? ''));
        if ($reference === '' && $purchaseRef !== '') $reference = $purchaseRef;
        $ean = preg_replace('/\D+/', '', (string)($source['ean'] ?? '')) ?: '';
        $rawType = sanitize_text_field((string)($source['type'] ?? ''));
        $typeKey = ProductCardex::normalise_profile_code($rawType);
        $type = self::canonicalItemTypeLabel($rawType, $typeKey);
        return [
            'category'=>sanitize_text_field((string)($source['category'] ?? '')),
            'reference'=>$reference,
            'item_type'=>$type,
            'product_name'=>sanitize_text_field((string)($source['product'] ?? '')),
            'capacity_cl'=>self::nullableFloat($source['capacity_cl'] ?? null),
            'units_per_case'=>self::nullableFloat($source['units_box'] ?? null),
            'meta'=>[
                'managed_by'=>self::MANAGED_BY,'retailer_key'=>$retailer,'ean'=>$ean,'purchase_ref'=>$purchaseRef,'sale_ref'=>$saleRef,
                'brand'=>sanitize_text_field((string)($source['brand'] ?? '')),'observations'=>sanitize_textarea_field((string)($source['observations'] ?? '')),
                'source_briefing_id'=>$briefingId,'source_week'=>$week,'source_year'=>$year,'source_row'=>absint($source['source_row'] ?? 0),
                'raw_item_type'=>$rawType,
            ],
        ];
    }

    private static function canonicalItemTypeLabel(string $rawType, string $typeKey): string {
        $rawType = trim(sanitize_text_field($rawType));
        if ($rawType === '') return '';
        if ($typeKey === 'general') return $rawType;
        if (preg_match('/^p([1-9])$/', $typeKey, $m)) return 'P' . (int)$m[1];
        if ($typeKey === 'pbl') return 'PBL';
        if ($typeKey === 'pbs') return 'PBS';
        if ($typeKey === 'std') return 'STD';
        if ($typeKey === 'fluxo_tenso') return 'Fluxo Tenso';
        return $rawType;
    }

    private static function itemIndex(array $rows): array {
        $index = ['rows'=>[],'ref'=>[],'ean'=>[],'name'=>[]];
        foreach ($rows as $row) {
            $id = absint($row['id'] ?? 0); if (!$id) continue;
            $index['rows'][$id] = $row;
            $ref = self::norm((string)($row['reference'] ?? '')); if ($ref !== '') $index['ref'][$ref][] = $id;
            $meta = self::decode((string)($row['meta_json'] ?? '')); $ean = preg_replace('/\D+/', '', (string)($meta['ean'] ?? '')); if ($ean !== '') $index['ean'][$ean][] = $id;
            $name = self::norm((string)($row['product_name'] ?? '')); if ($name !== '') $index['name'][$name][] = $id;
        }
        return $index;
    }

    private static function matchExistingId(array $item, array $index): int {
        $ref = self::norm((string)$item['reference']);
        if ($ref !== '' && count($index['ref'][$ref] ?? []) === 1) return (int)$index['ref'][$ref][0];
        $ean = preg_replace('/\D+/', '', (string)($item['meta']['ean'] ?? ''));
        if ($ean !== '' && count($index['ean'][$ean] ?? []) === 1) return (int)$index['ean'][$ean][0];
        $name = self::norm((string)$item['product_name']);
        if ($name !== '' && count($index['name'][$name] ?? []) === 1) return (int)$index['name'][$name][0];
        return 0;
    }

    private static function itemChanged(array $current, array $next): bool {
        foreach (['category','reference','item_type','product_name','sort_order','is_active'] as $key) if ((string)($current[$key] ?? '') !== (string)($next[$key] ?? '')) return true;
        foreach (['capacity_cl','units_per_case'] as $key) if (round((float)($current[$key] ?? 0), 3) !== round((float)($next[$key] ?? 0), 3)) return true;
        return self::decode((string)($current['meta_json'] ?? '')) !== self::decode((string)($next['meta_json'] ?? ''));
    }

    private static function linkProjectLocations(int $cardexId, int $projectId, string $insignia): int {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT l.id,l.name,l.tags,l.meta_json FROM {$px}campaign_locations cl INNER JOIN {$px}locations l ON l.id=cl.location_id WHERE cl.project_id=%d AND cl.is_active=1 AND COALESCE(l.is_active,1)=1",
            $projectId
        ), ARRAY_A) ?: [];
        $desired = [];
        foreach ($rows as $location) {
            $locationId = absint($location['id'] ?? 0);
            if ($locationId > 0 && StockService::locationMatchesInsignia($location, $insignia)) $desired[$locationId] = $locationId;
        }

        // Reconcile, rather than only INSERT IGNORE. Weekly Cardex mappings must
        // stop following stores that no longer belong to the configured insignia.
        $projectLocationIds = [];
        foreach ($rows as $location) {
            $locationId = absint($location['id'] ?? 0);
            if ($locationId > 0) $projectLocationIds[$locationId] = $locationId;
        }
        if ($projectLocationIds) {
            $in = implode(',', array_map('intval', array_values($projectLocationIds)));
            $current = $wpdb->get_col($wpdb->prepare(
                'SELECT location_id FROM ' . ProductCardex::table_locations() . " WHERE cardex_id=%d AND location_id IN ({$in})",
                $cardexId
            )) ?: [];
            foreach ($current as $locationId) {
                $locationId = absint($locationId);
                if ($locationId > 0 && !isset($desired[$locationId])) {
                    $deleted = $wpdb->delete(ProductCardex::table_locations(), ['cardex_id'=>$cardexId,'location_id'=>$locationId], ['%d','%d']);
                    if ($deleted === false) throw new \RuntimeException('Falha ao remover uma associação obsoleta do Cardex semanal: ' . (string)$wpdb->last_error);
                }
            }
        }

        $linked = 0;
        foreach ($desired as $locationId) {
            $result = $wpdb->query($wpdb->prepare(
                'INSERT IGNORE INTO ' . ProductCardex::table_locations() . ' (cardex_id,location_id,created_at) VALUES (%d,%d,%s)',
                $cardexId, $locationId, current_time('mysql')
            ));
            if ($result === false) throw new \RuntimeException('Falha ao associar uma loja ao Cardex semanal: ' . (string)$wpdb->last_error);
            if ($result) $linked++;
        }
        return $linked;
    }

    /**
     * Force the next mobile/offline request to rebuild its operational bundle
     * after a weekly Cardex changes. This uses the same generation key already
     * consumed by OfflineSyncService, so no new mobile contract is required.
     */
    private static function invalidateProjectMobileCaches(int $projectId): void {
        global $wpdb;
        if (class_exists('\RoutesPro\Mobile\OperationalContentRevision')) {
            \RoutesPro\Mobile\OperationalContentRevision::bump($projectId, 'cardex');
        }
        $projectId = absint($projectId);
        if (!$projectId) return;
        $px = $wpdb->prefix . 'routespro_';
        $userIds = [];
        $assigned = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT assigned_to FROM {$px}campaign_locations WHERE project_id=%d AND assigned_to>0 AND is_active=1",
            $projectId
        )) ?: [];
        foreach ($assigned as $id) { $id = absint($id); if ($id) $userIds[$id] = $id; }
        $routeUsers = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM (
                SELECT owner_user_id AS user_id FROM {$px}routes WHERE project_id=%d AND owner_user_id>0
                UNION
                SELECT a.user_id FROM {$px}assignments a INNER JOIN {$px}routes r ON r.id=a.route_id WHERE r.project_id=%d AND a.is_active=1 AND a.user_id>0
             ) ff_cardex_users",
            $projectId, $projectId
        )) ?: [];
        foreach ($routeUsers as $id) { $id = absint($id); if ($id) $userIds[$id] = $id; }
        foreach ($userIds as $id) {
            $generation = max(1, absint(get_user_meta($id, '_fieldflow_route_cache_generation', true)));
            update_user_meta($id, '_fieldflow_route_cache_generation', $generation + 1);
        }
    }

    private static function persistSummary(int $briefingId, array $sync): void {
        global $wpdb;
        $briefing = BriefingService::get($briefingId); if (!$briefing) return;
        $summary = self::decode((string)($briefing['import_summary_json'] ?? ''));
        $summary['cardex_sync'] = $sync;
        $wpdb->update(BriefingService::table('briefings'), [
            'import_summary_json'=>wp_json_encode($summary, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'updated_at'=>current_time('mysql'),
        ], ['id'=>$briefingId]);
    }

    private static function decode(string $json): array { $value = json_decode($json, true); return is_array($value) ? $value : []; }
    private static function norm(string $value): string { return StockService::normaliseText($value); }
    private static function nullableFloat($value): ?float { if ($value === '' || $value === null) return null; return is_numeric($value) ? (float)$value : null; }
}
