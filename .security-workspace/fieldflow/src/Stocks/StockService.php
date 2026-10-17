<?php
namespace RoutesPro\Stocks;

use RoutesPro\Support\Permissions;

if (!defined('ABSPATH')) exit;

class StockService {
    public const SNAPSHOT_MODE = 'replace';
    public const SNAPSHOT_POLICY_VERSION = '2.2.398';

    public static function batchTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_stock_batches'; }
    public static function itemTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_stock_items'; }
    public static function mappingTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_stock_store_mappings'; }
    public static function viewTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_stock_views'; }
    public static function lowThreshold(): float { return max(0.0, (float)apply_filters('fieldflow_stock_low_threshold', 2)); }

    public static function normaliseText(string $value): string {
        $value = remove_accents(wp_strip_all_tags($value));
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', (string)$value));
    }

    public static function storeKey(string $ref, string $name): string {
        $ref = self::normaliseText($ref);
        $name = self::normaliseText($name);
        return substr(hash('sha256', $ref . '|' . $name), 0, 40);
    }

    private static function compactStoreName(string $name): string {
        $name = self::normaliseText($name);
        $noise = ['PINGO DOCE','CONTINENTE','MODELO','BOM DIA','CNT','MDL','CBD','AUCHAN','INTERMARCHE','INTERMARCHÉ','HIPER','SUPERMERCADO','SUPER','LOJA','STORE','CENTRO'];
        foreach ($noise as $token) $name = preg_replace('/\b' . preg_quote(self::normaliseText($token), '/') . '\b/', ' ', $name);
        return trim(preg_replace('/\s+/', ' ', (string)$name));
    }

    public static function locationIndex(int $clientId, int $projectId, string $insignia = ''): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $where = ['l.is_active=1'];
        $args = [];
        if ($clientId) { $where[] = '(l.client_id=%d OR l.client_id IS NULL OR l.client_id=0)'; $args[] = $clientId; }
        if ($projectId) {
            $where[] = '(l.project_id=%d OR l.project_id IS NULL OR l.project_id=0 OR cl.project_id=%d)';
            $args[] = $projectId; $args[] = $projectId;
        }
        $sql = "SELECT DISTINCT l.id,l.name,l.external_ref,l.city,l.address,l.postal_code,l.county,l.district,l.tags,l.meta_json FROM {$px}locations l LEFT JOIN {$px}campaign_locations cl ON cl.location_id=l.id AND cl.is_active=1 WHERE " . implode(' AND ', $where) . ' ORDER BY l.name ASC';
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        $index = ['rows'=>[], 'by_ref'=>[], 'by_ref_compact'=>[], 'by_ref_digits'=>[], 'by_name'=>[], 'by_compact'=>[]];
        $wantedBrand = self::brandFamily($insignia);
        // An unidentified source retailer never receives a generic PDV candidate list.
        if ($insignia !== '' && $wantedBrand === '') return $index;
        foreach ((array)$rows as $row) {
            // 9DL: store identity in its name prevails over ambiguous tags or shared metadata.
            if ($wantedBrand !== '' && !self::locationMatchesInsignia($row, $insignia)) continue;
            $id = (int)$row['id'];
            $index['rows'][$id] = $row;
            $ref = self::normaliseText((string)($row['external_ref'] ?? ''));
            $name = self::normaliseText((string)($row['name'] ?? ''));
            $compact = self::compactStoreName((string)($row['name'] ?? ''));
            if ($ref !== '') {
                $index['by_ref'][$ref][] = $id;
                $refCompact = str_replace(' ', '', $ref);
                if ($refCompact !== '') $index['by_ref_compact'][$refCompact][] = $id;
                $digits = preg_replace('/\D+/', '', $ref);
                if ($digits !== '') {
                    $digits = ltrim($digits, '0');
                    if ($digits === '') $digits = '0';
                    $index['by_ref_digits'][$digits][] = $id;
                }
            }
            if ($name !== '') $index['by_name'][$name][] = $id;
            if ($compact !== '') $index['by_compact'][$compact][] = $id;
        }
        return $index;
    }


    public static function brandFamily(string $value): string {
        $value = self::normaliseText($value);
        if ($value === '') return '';
        $flat = str_replace(' ', '', $value);
        $families = [
            'pingodoce' => ['PINGO DOCE','PINGODOCE','P DOCE','PD'],
            'continente' => ['CONTINENTE','CONTINENTE MODELO','MODELO','CONTINENTE BOM DIA','BOM DIA','BOMDIA','CNT','MDL','CBD','SONAE','SONAE MC'], // 9DL: supplier's Sonae is the Continente family
            'auchan' => ['AUCHAN','JUMBO'],
            'recheio' => ['RECHEIO'],
            'intermarche' => ['INTERMARCHE'],
            'minipreco' => ['MINIPRECO'],
            'elcorteingles' => ['EL CORTE INGLES','ELCORTEINGLES'],
            'mercadona' => ['MERCADONA'],
            'lidl' => ['LIDL'],
            'aldi' => ['ALDI'],
            'froiz' => ['FROIZ'],
            'apolonia' => ['APOLONIA'],
            'makro' => ['MAKRO'],
            'arcol' => ['ARCOL'],
            'mcunha' => ['M CUNHA','MCUNHA','M. CUNHA'],
            'eclerc' => ['E LECLERC','ELECLERC','LECLERC'],
        ];
        foreach ($families as $family => $aliases) {
            foreach ($aliases as $alias) {
                $alias = self::normaliseText($alias);
                $aliasFlat = str_replace(' ', '', $alias);
                $matched = strlen($alias) <= 3
                    ? (bool)preg_match('/(?:^|\s)' . preg_quote($alias, '/') . '(?:$|\s)/', $value)
                    : (strpos($value, $alias) !== false || strpos($flat, $aliasFlat) !== false);
                if ($matched) return $family;
            }
        }
        return '';
    }


    /** 9DM: a source file family is NOT proof that its PDV belongs to that family. */
    private static function explicitLocationFamily(string $name): string {
        $value = self::normaliseText($name);
        if ($value === '') return '';
        // Multiple explicit retailer names mean a disputed identity, never an approval.
        $families = [
            'continente'=>'/(?:^|\s)(?:CONTINENTE|MODELO|BOM\s+DIA|BOMDIA|CNT|MDL|CBD)(?:\s|$)/',
            'pingodoce'=>'/(?:^|\s)(?:PINGO\s+DOCE|PINGODOCE|PD)(?:\s|$)/',
            'arcol'=>'/(?:^|\s)ARCOL(?:\s|$)/',
            'mcunha'=>'/(?:^|\s)(?:M\s+CUNHA|MCUNHA)(?:\s|$)/',
            'makro'=>'/(?:^|\s)MAKRO(?:\s|$)/',
            'recheio'=>'/(?:^|\s)RECHEIO(?:\s|$)/',
        ];
        $explicit = [];
        foreach ($families as $key=>$pattern) if (preg_match($pattern, $value)) $explicit[$key] = true;
        if (count($explicit) > 1) return '!ambiguous';
        if ($explicit) return (string)array_key_first($explicit);
        $family = self::brandFamily($value);
        // SONAE is a group/file label, never sufficient evidence of a CNT/MDL/CBD PDV.
        if ($family === 'continente') return '';
        return $family;
    }

    /** A conflicting retailer explicitly named in an XLSX row must stay unmatched. */
    public static function sourceMatchesInsignia(string $sourceName, string $insignia): bool {
        $wanted = self::brandFamily($insignia);
        if ($wanted === '') return false;
        $identified = self::explicitLocationFamily($sourceName);
        return $identified === '' || $identified === $wanted;
    }

    /** CNT/MDL/CBD remain distinct even inside the permitted Sonae family. */
    public static function sonaeSubtype(string $name): string {
        $value = self::normaliseText($name);
        if (preg_match('/(?:^|\s)(?:MDL|MODELO)(?:\s|$)/', $value)) return 'mdl';
        if (preg_match('/(?:^|\s)(?:CBD|BOM\s+DIA|BOMDIA)(?:\s|$)/', $value)) return 'cbd';
        if (preg_match('/(?:^|\s)(?:CNT|CONTINENTE)(?:\s|$)/', $value)) return 'cnt';
        return '';
    }

    private static function locationSonaeSubtype(array $location): string {
        $name = (string)($location['name'] ?? '');
        if (self::explicitLocationFamily($name) === 'continente') return self::sonaeSubtype($name);
        $inputs = [(string)($location['tags'] ?? '')];
        $meta = $location['meta_json'] ?? '';
        if (is_string($meta)) $meta = json_decode($meta, true);
        if (is_array($meta)) foreach (['insignia','insignia_key','retailer','retailer_key','store_brand','chain','brand'] as $key) {
            if (isset($meta[$key]) && is_string($meta[$key])) $inputs[] = $meta[$key];
        }
        $result = [];
        foreach ($inputs as $value) {
            $subtype = self::sonaeSubtype($value);
            if ($subtype !== '') $result[$subtype] = true;
        }
        return count($result) === 1 ? (string)array_key_first($result) : '';
    }

    public static function sourceMatchesLocation(string $sourceName, array $location, string $insignia): bool {
        if (!self::sourceMatchesInsignia($sourceName, $insignia)
            || !self::locationMatchesInsignia($location, $insignia)) return false;
        if (self::brandFamily($insignia) !== 'continente') return true;
        $required = self::sonaeSubtype($insignia);
        $source = self::sonaeSubtype($sourceName);
        $target = self::locationSonaeSubtype($location);
        if ($required !== '' && $target !== $required) return false;
        // A source with an explicit MDL/CBD/CNT code never falls into another subtype.
        return $source === '' || ($target !== '' && $target === $source);
    }

    /**
     * A known target name is authoritative. Missing identity is NOT a match.
     * Only explicit store-brand tags or dedicated metadata keys may prove
     * an otherwise unlabelled PDV, never an arbitrary campaign/brand mention.
     */
    public static function locationMatchesInsignia(array $location, string $insignia): bool {
        $wanted = self::brandFamily($insignia);
        if ($wanted === '') return false; // 9DM: no unknown-family auto matching.
        $nameFamily = self::explicitLocationFamily((string)($location['name'] ?? ''));
        if ($nameFamily !== '') {
            if ($nameFamily !== $wanted) return false;
            $required = $wanted === 'continente' ? self::sonaeSubtype($insignia) : '';
            return $required === '' || self::locationSonaeSubtype($location) === $required;
        }

        $identities = [];
        $tags = (string)($location['tags'] ?? '');
        $parsedTags = json_decode($tags, true);
        if (is_array($parsedTags)) {
            foreach ($parsedTags as $value) if (is_string($value)) $identities[] = $value;
        } elseif ($tags !== '') {
            foreach (preg_split('/[,;|]+/', $tags) as $tag) $identities[] = (string)$tag;
        }
        $metadata = $location['meta_json'] ?? '';
        if (is_string($metadata)) $metadata = json_decode($metadata, true);
        if (is_array($metadata)) {
            foreach (['insignia','insignia_key','retailer','retailer_key','store_brand','chain','brand'] as $key) {
                if (isset($metadata[$key]) && is_string($metadata[$key])) $identities[] = $metadata[$key];
            }
        }
        $found = [];
        foreach ($identities as $identity) {
            $family = self::explicitLocationFamily((string)$identity);
            if ($family === '!ambiguous') return false;
            if ($family !== '') $found[$family] = true;
        }
        if (count($found) !== 1 || !isset($found[$wanted])) return false;
        $required = $wanted === 'continente' ? self::sonaeSubtype($insignia) : '';
        return $required === '' || self::locationSonaeSubtype($location) === $required;
    }

    /** Read-only legacy cross-retailer audit: no implicit DELETE or reassignment. */
    public static function invalidBatchStoreAssociations(int $batchId): array {
        global $wpdb;
        $batch = self::getBatch($batchId);
        if (!$batch || self::brandFamily((string)$batch['insignia']) === '') return [];
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT i.source_store_key,i.store_external_ref,i.store_name,i.locality,i.location_id,l.name linked_location_name,l.tags linked_location_tags,l.meta_json linked_location_meta_json,COUNT(*) row_count,COUNT(DISTINCT COALESCE(NULLIF(i.ean,\'\'),NULLIF(i.article_ref,\'\'),i.product_name)) product_count FROM '.self::itemTable().' i LEFT JOIN '.$wpdb->prefix.'routespro_locations l ON l.id=i.location_id WHERE i.batch_id=%d AND i.location_id>0 GROUP BY i.source_store_key,i.store_external_ref,i.store_name,i.locality,i.location_id,l.name,l.tags,l.meta_json',
            $batchId
        ), ARRAY_A) ?: [];
        $invalid = [];
        foreach ($rows as $row) {
            $location = ['name'=>$row['linked_location_name'] ?? '', 'tags'=>$row['linked_location_tags'] ?? '', 'meta_json'=>$row['linked_location_meta_json'] ?? ''];
            $sourceConflict = !self::sourceMatchesInsignia((string)$row['store_name'], (string)$batch['insignia']);
            if (self::sourceMatchesLocation((string)$row['store_name'], $location, (string)$batch['insignia'])) continue;
            $row['review_reason'] = $sourceConflict ? 'source_brand_conflict' : 'pdv_brand_unverified';
            $invalid[(string)$row['source_store_key']] = $row;
        }
        return array_values($invalid);
    }

    // Historical name kept for 9DL callers. 9DM is deliberately read-only.
    public static function repairBatchScope(int $batchId): array {
        $rows = self::invalidBatchStoreAssociations($batchId);
        return ['removed'=>0,'keys'=>array_values(array_map(static fn($row)=>(string)$row['source_store_key'],$rows)),
                'review_count'=>count($rows),'review_rows'=>$rows];
    }

    public static function resetBatchMappings(int $batchId): int {
        global $wpdb;
        $batch=self::getBatch($batchId);
        if(!$batch) return 0;
        $keys=$wpdb->get_col($wpdb->prepare('SELECT DISTINCT source_store_key FROM '.self::itemTable().' WHERE batch_id=%d',$batchId))?:[];
        $wpdb->query($wpdb->prepare("UPDATE ".self::itemTable()." SET location_id=0,match_confidence=0,match_method='reset' WHERE batch_id=%d",$batchId));
        if($keys){
            $ph=implode(',',array_fill(0,count($keys),'%s'));
            $wpdb->query($wpdb->prepare('DELETE FROM '.self::mappingTable()." WHERE client_id=%d AND project_id=%d AND insignia=%s AND source_store_key IN ($ph)",...array_merge([(int)$batch['client_id'],(int)$batch['project_id'],(string)$batch['insignia']],$keys)));
        }
        self::refreshBatchStats($batchId);
        return count($keys);
    }

    public static function mappingIndex(int $clientId, int $projectId, string $insignia): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT source_store_key,location_id,confidence,match_method FROM ' . self::mappingTable() . ' WHERE client_id=%d AND project_id=%d AND insignia=%s',
            $clientId, $projectId, $insignia
        ), ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $row) $out[(string)$row['source_store_key']] = $row;
        return $out;
    }

    public static function resolveLocation(string $ref, string $name, array $locationIndex, array $mappingIndex, string $locality = '', string $insignia = ''): array {
        $key = self::storeKey($ref, $name);
        if ($insignia !== '' && !self::sourceMatchesInsignia($name, $insignia)) return ['location_id'=>0, 'confidence'=>0, 'method'=>'source_insignia_review'];
        if ($insignia !== '' && self::brandFamily($insignia) === 'continente') {
            foreach ($locationIndex['rows'] as $candidateId=>$candidate) {
                if (!self::sourceMatchesLocation($name, (array)$candidate, $insignia)) unset($locationIndex['rows'][$candidateId]);
            }
            $idsAllowed = array_fill_keys(array_keys($locationIndex['rows']), true);
            foreach (['by_ref','by_ref_compact','by_ref_digits','by_name','by_compact'] as $lookup) {
                foreach ($locationIndex[$lookup] as $key=>$ids) {
                    $eligible = array_values(array_filter($ids, static fn($id)=>isset($idsAllowed[$id])));
                    if ($eligible) $locationIndex[$lookup][$key] = $eligible;
                    else unset($locationIndex[$lookup][$key]);
                }
            }
        }
        if (!empty($mappingIndex[$key]['location_id'])) {
            $mappedId=(int)$mappingIndex[$key]['location_id'];
            if (!empty($locationIndex['rows'][$mappedId])) {
                return ['location_id'=>$mappedId, 'confidence'=>(int)($mappingIndex[$key]['confidence'] ?? 100), 'method'=>(string)($mappingIndex[$key]['match_method'] ?? 'manual_mapping')];
            }
        }
        $refKey = self::normaliseText($ref);
        if ($refKey !== '' && count($locationIndex['by_ref'][$refKey] ?? []) === 1) {
            return ['location_id'=>(int)$locationIndex['by_ref'][$refKey][0], 'confidence'=>100, 'method'=>'external_ref'];
        }
        $refCompact = str_replace(' ', '', $refKey);
        if ($refCompact !== '' && count($locationIndex['by_ref_compact'][$refCompact] ?? []) === 1) {
            return ['location_id'=>(int)$locationIndex['by_ref_compact'][$refCompact][0], 'confidence'=>99, 'method'=>'external_ref_compact'];
        }
        $refDigits = preg_replace('/\D+/', '', $refKey);
        if ($refDigits !== '') {
            $refDigits = ltrim($refDigits, '0');
            if ($refDigits === '') $refDigits = '0';
            if (count($locationIndex['by_ref_digits'][$refDigits] ?? []) === 1) {
                return ['location_id'=>(int)$locationIndex['by_ref_digits'][$refDigits][0], 'confidence'=>99, 'method'=>'external_ref_numeric'];
            }
        }
        $nameKey = self::normaliseText($name);
        if ($nameKey !== '' && count($locationIndex['by_name'][$nameKey] ?? []) === 1) {
            return ['location_id'=>(int)$locationIndex['by_name'][$nameKey][0], 'confidence'=>96, 'method'=>'exact_name'];
        }
        $compact = self::compactStoreName($name);
        if ($compact !== '' && count($locationIndex['by_compact'][$compact] ?? []) === 1) {
            return ['location_id'=>(int)$locationIndex['by_compact'][$compact][0], 'confidence'=>92, 'method'=>'compact_name'];
        }
        if (strlen($compact) >= 7) {
            $matches = [];
            foreach ((array)$locationIndex['by_compact'] as $candidate => $ids) {
                if ($candidate === '' || (strpos($candidate, $compact) === false && strpos($compact, $candidate) === false)) continue;
                foreach ($ids as $id) $matches[(int)$id] = (int)$id;
            }
            if (count($matches) === 1) return ['location_id'=>(int)array_values($matches)[0], 'confidence'=>80, 'method'=>'partial_name'];
        }

        // Last deterministic pass: use the universal ranking engine, but only
        // auto-accept a very high confidence candidate with a clear gap. A
        // conflicting explicit store code always blocks this shortcut.
        if (class_exists('\RoutesPro\Mapping\MappingEngine') && !empty($locationIndex['rows'])) {
            $candidates = \RoutesPro\Mapping\MappingEngine::storeCandidates([
                'source_label'=>$name,
                'external_ref'=>$ref,
                'meta_json'=>['locality'=>$locality,'insignia'=>$insignia],
            ], array_values((array)$locationIndex['rows']), 3);
            $top = $candidates[0] ?? [];
            $second = $candidates[1]['confidence'] ?? 0;
            $topConfidence = (int)($top['confidence'] ?? 0);
            $gap = $topConfidence - (int)$second;
            $targetId = (int)($top['target_id'] ?? 0);
            if ($targetId && $topConfidence >= 97 && $gap >= 14) {
                $target = $locationIndex['rows'][$targetId] ?? [];
                $targetRef = self::normaliseText((string)($target['external_ref'] ?? ''));
                $explicitRefConflict = false;
                if ($refKey !== '' && $targetRef !== '') {
                    $sourceDigits = ltrim((string)preg_replace('/\D+/', '', $refKey), '0');
                    $targetDigits = ltrim((string)preg_replace('/\D+/', '', $targetRef), '0');
                    $sameRef = $refKey === $targetRef || str_replace(' ', '', $refKey) === str_replace(' ', '', $targetRef)
                        || ($sourceDigits !== '' && $targetDigits !== '' && $sourceDigits === $targetDigits);
                    $explicitRefConflict = !$sameRef;
                }
                if (!$explicitRefConflict) return ['location_id'=>$targetId, 'confidence'=>$topConfidence, 'method'=>'smart_high_confidence'];
            }
        }
        return ['location_id'=>0, 'confidence'=>0, 'method'=>'unmatched'];
    }

    public static function saveMapping(int $clientId, int $projectId, string $insignia, string $storeRef, string $storeName, int $locationId, int $confidence = 100, string $method = 'manual', bool $refreshStats = true): void {
        global $wpdb;
        $key = self::storeKey($storeRef, $storeName);
        // 9DL: reject cross-insignia targets, but keep location_id=0 for explicit unmapping.
        if ($locationId > 0) {
            if (!self::sourceMatchesInsignia($storeName, $insignia)) {
                throw new \RuntimeException('A loja do ficheiro contradiz a insígnia do lote. Revê a origem antes de associar.');
            }
            $target = $wpdb->get_row($wpdb->prepare('SELECT id,name,tags,meta_json FROM ' . $wpdb->prefix . 'routespro_locations WHERE id=%d', $locationId), ARRAY_A);
            if (!$target || !self::sourceMatchesLocation($storeName, $target, $insignia)) {
                throw new \RuntimeException('PDV incompatível com a insígnia do ficheiro de stock. Confirma a loja antes de associar.');
            }
        }
        $wpdb->replace(self::mappingTable(), [
            'client_id'=>$clientId,
            'project_id'=>$projectId,
            'insignia'=>$insignia,
            'source_store_key'=>$key,
            'source_store_ref'=>$storeRef,
            'source_store_name'=>$storeName,
            'location_id'=>$locationId,
            'confidence'=>max(0,min(100,$confidence)),
            'match_method'=>$method,
            'updated_by'=>get_current_user_id(),
            'created_at'=>current_time('mysql'),
            'updated_at'=>current_time('mysql'),
        ]);
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . self::itemTable() . ' i INNER JOIN ' . self::batchTable() . ' b ON b.id=i.batch_id SET i.location_id=%d,i.match_confidence=%d,i.match_method=%s WHERE b.client_id=%d AND b.project_id=%d AND b.insignia=%s AND i.source_store_key=%s',
            $locationId, max(0,min(100,$confidence)), $method, $clientId, $projectId, $insignia, $key
        ));
        if (class_exists('\RoutesPro\Mapping\MappingService')) {
            $mappingId = (int)$wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . self::mappingTable() . ' WHERE client_id=%d AND project_id=%d AND insignia=%s AND source_store_key=%s LIMIT 1',
                $clientId, $projectId, $insignia, $key
            ));
            \RoutesPro\Mapping\MappingService::captureStoreMapping(
                'stock',
                $mappingId,
                $clientId,
                $projectId,
                $storeName,
                $storeRef,
                $locationId,
                ['insignia'=>$insignia,'source_store_key'=>$key,'source_store_ref'=>$storeRef,'source_store_name'=>$storeName]
            );
        }
        if ($refreshStats) self::refreshBatchStatsForScope($clientId, $projectId, $insignia);
    }

    public static function refreshBatchStatsForScope(int $clientId, int $projectId, string $insignia): void {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . self::batchTable() . ' WHERE client_id=%d AND project_id=%d AND insignia=%s', $clientId, $projectId, $insignia)) ?: [];
        foreach ($ids as $id) self::refreshBatchStats((int)$id);
    }

    public static function refreshBatchStats(int $batchId): void {
        global $wpdb;
        $threshold = self::lowThreshold();
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT COUNT(*) row_count,COUNT(DISTINCT source_store_key) store_count,COUNT(DISTINCT COALESCE(NULLIF(ean,\'\'),NULLIF(article_ref,\'\'),product_name)) product_count,COUNT(DISTINCT CASE WHEN location_id>0 THEN source_store_key END) matched_store_count,SUM(CASE WHEN location_id=0 THEN 1 ELSE 0 END) unmatched_row_count,COALESCE(SUM(stock_total),0) total_stock,SUM(CASE WHEN stock_total=0 THEN 1 ELSE 0 END) zero_count,SUM(CASE WHEN stock_total>0 AND stock_total<=%f THEN 1 ELSE 0 END) low_count,SUM(CASE WHEN stock_total<0 THEN 1 ELSE 0 END) negative_count FROM ' . self::itemTable() . ' WHERE batch_id=%d',
            $threshold, $batchId
        ), ARRAY_A) ?: [];
        $wpdb->update(self::batchTable(), [
            'row_count'=>(int)($row['row_count'] ?? 0),
            'store_count'=>(int)($row['store_count'] ?? 0),
            'product_count'=>(int)($row['product_count'] ?? 0),
            'matched_store_count'=>(int)($row['matched_store_count'] ?? 0),
            'unmatched_row_count'=>(int)($row['unmatched_row_count'] ?? 0),
            'total_stock'=>(float)($row['total_stock'] ?? 0),
            'zero_count'=>(int)($row['zero_count'] ?? 0),
            'low_count'=>(int)($row['low_count'] ?? 0),
            'negative_count'=>(int)($row['negative_count'] ?? 0),
            'updated_at'=>current_time('mysql'),
        ], ['id'=>$batchId]);
    }

    public static function getBatch(int $batchId): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT b.*,c.name client_name,p.name project_name,u.display_name created_by_name FROM ' . self::batchTable() . " b LEFT JOIN {$wpdb->prefix}routespro_clients c ON c.id=b.client_id LEFT JOIN {$wpdb->prefix}routespro_projects p ON p.id=b.project_id LEFT JOIN {$wpdb->users} u ON u.ID=b.created_by WHERE b.id=%d",
            $batchId
        ), ARRAY_A);
        return $row ?: null;
    }

    public static function listBatches(array $filters = []): array {
        global $wpdb;
        $where = ['1=1']; $args = [];
        foreach (['client_id','project_id'] as $field) if (!empty($filters[$field])) { $where[] = 'b.' . $field . '=%d'; $args[] = absint($filters[$field]); }
        if (!empty($filters['status'])) { $where[]='b.status=%s'; $args[]=sanitize_key($filters['status']); }
        $sql = 'SELECT b.*,c.name client_name,p.name project_name,u.display_name created_by_name FROM ' . self::batchTable() . " b LEFT JOIN {$wpdb->prefix}routespro_clients c ON c.id=b.client_id LEFT JOIN {$wpdb->prefix}routespro_projects p ON p.id=b.project_id LEFT JOIN {$wpdb->users} u ON u.ID=b.created_by WHERE " . implode(' AND ', $where) . ' ORDER BY b.snapshot_date DESC,b.id DESC LIMIT 200';
        return $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
    }

    public static function unmatchedStores(int $batchId): array {
        global $wpdb;
        $unmatched = $wpdb->get_results($wpdb->prepare(
            'SELECT source_store_key,store_external_ref,store_name,locality,COUNT(*) row_count,COUNT(DISTINCT COALESCE(NULLIF(ean,\'\'),NULLIF(article_ref,\'\'),product_name)) product_count,MIN(match_method) match_method FROM ' . self::itemTable() . ' WHERE batch_id=%d AND location_id=0 GROUP BY source_store_key,store_external_ref,store_name,locality ORDER BY store_name ASC',
            $batchId
        ), ARRAY_A) ?: [];
        $keys = array_fill_keys(array_map(static fn($row)=>(string)$row['source_store_key'], $unmatched), true);
        foreach (self::invalidBatchStoreAssociations($batchId) as $row) {
            $key = (string)$row['source_store_key'];
            if (isset($keys[$key])) continue;
            $row['match_method'] = 'needs_insignia_review';
            $unmatched[] = $row;
            $keys[$key] = true;
        }
        usort($unmatched, static fn($a,$b)=>strnatcasecmp((string)$a['store_name'],(string)$b['store_name']));
        return $unmatched;
    }

    public static function deleteBatch(int $batchId): void {
        global $wpdb;
        $wpdb->delete(self::viewTable(), ['batch_id'=>$batchId]);
        $wpdb->delete(self::itemTable(), ['batch_id'=>$batchId]);
        $wpdb->delete(self::batchTable(), ['id'=>$batchId]);
    }

    /**
     * Stock is a point-in-time snapshot, never an incremental ledger.
     * Publishing a batch makes it the only active snapshot for the exact
     * client/project/insignia scope. Older published batches are archived,
     * but their rows remain untouched for history, audit and rollback.
     *
     * Call this inside the same DB transaction that publishes the new batch.
     * The SELECT ... FOR UPDATE serialises concurrent publications in scope.
     */
    private static function acquireSnapshotScopeLock(int $clientId, int $projectId, string $insignia): string {
        global $wpdb;
        $name = 'ff_stock_' . substr(hash('sha256', $clientId . '|' . $projectId . '|' . self::normaliseText($insignia)), 0, 48);
        $value = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,%d)', $name, 10));
        if ((string)$value === '1') return $name;
        // Some managed MySQL proxies disable advisory locks. Existing-row
        // FOR UPDATE locking still protects the common case, so fall back
        // rather than making stock publication unavailable on those hosts.
        if ($value === null) return '';
        throw new \RuntimeException('Já existe outra publicação de stock em curso para esta insígnia. Tenta novamente dentro de alguns segundos.');
    }

    private static function releaseSnapshotScopeLock(string $name): void {
        global $wpdb;
        if ($name === '') return;
        try { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name)); } catch (\Throwable $ignored) {}
    }

    public static function activeSnapshotForScope(int $clientId, int $projectId, string $insignia, int $excludeBatchId = 0): ?array {
        global $wpdb;
        if ($clientId <= 0 || $projectId <= 0 || trim($insignia) === '') return null;
        $sql = 'SELECT * FROM ' . self::batchTable() . " WHERE client_id=%d AND project_id=%d AND LOWER(TRIM(insignia))=LOWER(TRIM(%s)) AND status='published'";
        $args = [$clientId, $projectId, $insignia];
        if ($excludeBatchId > 0) { $sql .= ' AND id<>%d'; $args[] = $excludeBatchId; }
        $sql .= ' ORDER BY snapshot_date DESC,published_at DESC,id DESC LIMIT 1';
        $row = $wpdb->get_row($wpdb->prepare($sql, ...$args), ARRAY_A);
        return is_array($row) && $row ? $row : null;
    }

    public static function archivePreviousPublishedSnapshots(int $clientId, int $projectId, string $insignia, int $excludeBatchId = 0): array {
        global $wpdb;
        if ($clientId <= 0 || $projectId <= 0 || trim($insignia) === '') return [];
        $sql = 'SELECT id FROM ' . self::batchTable() . " WHERE client_id=%d AND project_id=%d AND LOWER(TRIM(insignia))=LOWER(TRIM(%s)) AND status='published'";
        $args = [$clientId, $projectId, $insignia];
        if ($excludeBatchId > 0) { $sql .= ' AND id<>%d'; $args[] = $excludeBatchId; }
        $sql .= ' ORDER BY snapshot_date DESC,published_at DESC,id DESC FOR UPDATE';
        $ids = array_values(array_filter(array_map('absint', $wpdb->get_col($wpdb->prepare($sql, ...$args)) ?: [])));
        if (!$ids) return [];
        $ph = implode(',', array_fill(0, count($ids), '%d'));
        $params = array_merge([current_time('mysql')], $ids);
        $updated = $wpdb->query($wpdb->prepare('UPDATE ' . self::batchTable() . " SET status='archived',updated_at=%s WHERE id IN ({$ph})", ...$params));
        if ($updated === false) throw new \RuntimeException('Não foi possível arquivar o snapshot de stock anterior: ' . $wpdb->last_error);
        return $ids;
    }

    /**
     * Publish one existing batch atomically using replace semantics.
     * Re-publishing an archived historical batch acts as an explicit rollback:
     * the currently active snapshot is archived and the selected batch becomes
     * the sole published snapshot in that scope.
     */
    public static function publishSnapshot(int $batchId, int $actorUserId = 0, string $source = 'status_change'): array {
        global $wpdb;
        $batch = self::getBatch($batchId);
        if (!$batch) throw new \RuntimeException('Lote de stock não encontrado.');
        $clientId = (int)$batch['client_id'];
        $projectId = (int)$batch['project_id'];
        $insignia = (string)$batch['insignia'];
        $now = current_time('mysql');
        $scopeLock = self::acquireSnapshotScopeLock($clientId, $projectId, $insignia);
        $wpdb->query('START TRANSACTION');
        try {
            $replacedIds = self::archivePreviousPublishedSnapshots($clientId, $projectId, $insignia, $batchId);
            $mapping = json_decode((string)($batch['mapping_json'] ?? ''), true);
            if (!is_array($mapping)) $mapping = [];
            $mapping['_snapshot_policy'] = [
                'mode'=>self::SNAPSHOT_MODE,
                'active_batch_id'=>$batchId,
                'replaced_batch_ids'=>$replacedIds,
                'replaced_count'=>count($replacedIds),
                'activated_at'=>$now,
                'source'=>sanitize_key($source),
            ];
            $ok = $wpdb->update(self::batchTable(), [
                'status'=>'published',
                'published_at'=>$now,
                'updated_at'=>$now,
                'mapping_json'=>wp_json_encode($mapping, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ], ['id'=>$batchId]);
            if ($ok === false) throw new \RuntimeException('Não foi possível publicar o novo snapshot de stock: ' . $wpdb->last_error);
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        } finally {
            self::releaseSnapshotScopeLock($scopeLock);
        }
        $fresh = self::getBatch($batchId) ?: $batch;
        $payload = [
            'batch_id'=>$batchId,
            'client_id'=>$clientId,
            'project_id'=>$projectId,
            'insignia'=>$insignia,
            'snapshot_date'=>(string)($fresh['snapshot_date'] ?? ''),
            'status'=>'published',
            'user_id'=>$actorUserId ?: get_current_user_id(),
            'source'=>sanitize_key($source),
            'snapshot_mode'=>self::SNAPSHOT_MODE,
            'replaced_batch_ids'=>$replacedIds,
            'replaced_count'=>count($replacedIds),
        ];
        do_action('fieldflow_stock_imported', $batchId, $payload);
        return ['batch'=>$fresh,'replaced_batch_ids'=>$replacedIds,'replaced_count'=>count($replacedIds),'payload'=>$payload];
    }

    /**
     * One-time normalisation for historical installations that may contain
     * more than one published batch in the same scope. The newest remains
     * active; older published batches become archived history.
     */
    public static function ensureSnapshotPolicyMigration(): void {
        if ((string)get_option('fieldflow_stock_snapshot_policy_version', '') === self::SNAPSHOT_POLICY_VERSION) return;
        if (class_exists('\\RoutesPro\\Stocks\\StockSchema') && \RoutesPro\Stocks\StockSchema::missingTables()) return;
        global $wpdb;
        $rows = $wpdb->get_results('SELECT id,client_id,project_id,insignia,snapshot_date,published_at FROM ' . self::batchTable() . " WHERE status='published' ORDER BY client_id ASC,project_id ASC,insignia ASC,snapshot_date DESC,published_at DESC,id DESC", ARRAY_A) ?: [];
        $seen = []; $archive = [];
        foreach ($rows as $row) {
            $key = (int)$row['client_id'] . '|' . (int)$row['project_id'] . '|' . self::normaliseText((string)$row['insignia']);
            if (!isset($seen[$key])) { $seen[$key] = (int)$row['id']; continue; }
            $archive[] = (int)$row['id'];
        }
        if ($archive) {
            $ph = implode(',', array_fill(0, count($archive), '%d'));
            $params = array_merge([current_time('mysql')], $archive);
            $updated = $wpdb->query($wpdb->prepare('UPDATE ' . self::batchTable() . " SET status='archived',updated_at=%s WHERE id IN ({$ph})", ...$params));
            if ($updated === false) return;
        }
        update_option('fieldflow_stock_snapshot_policy_version', self::SNAPSHOT_POLICY_VERSION, false);
    }

    public static function latestBatches(int $clientId = 0, int $projectId = 0, ?array $scope = null): array {
        global $wpdb;
        $where = ["b.status='published'"]; $args = [];
        if ($clientId) { $where[]='b.client_id=%d'; $args[]=$clientId; }
        if ($projectId) { $where[]='b.project_id=%d'; $args[]=$projectId; }
        $scope = $scope ?? Permissions::get_scope();
        if (empty($scope['is_manager'])) {
            $projectIds = array_values(array_filter(array_map('absint',(array)($scope['project_ids'] ?? []))));
            $clientIds = array_values(array_filter(array_map('absint',(array)($scope['client_ids'] ?? []))));
            if ($projectIds) { $where[]='b.project_id IN (' . implode(',', array_fill(0,count($projectIds),'%d')) . ')'; $args=array_merge($args,$projectIds); }
            elseif ($clientIds) { $where[]='b.client_id IN (' . implode(',', array_fill(0,count($clientIds),'%d')) . ')'; $args=array_merge($args,$clientIds); }
            else return [];
        }
        $sql = 'SELECT b.*,c.name client_name,p.name project_name FROM ' . self::batchTable() . " b LEFT JOIN {$wpdb->prefix}routespro_clients c ON c.id=b.client_id LEFT JOIN {$wpdb->prefix}routespro_projects p ON p.id=b.project_id WHERE " . implode(' AND ',$where) . ' ORDER BY b.snapshot_date DESC,b.published_at DESC,b.id DESC';
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql,...$args),ARRAY_A) : $wpdb->get_results($sql,ARRAY_A);
        $latest=[];
        foreach ((array)$rows as $row) {
            $key=(int)$row['client_id'].'|'.(int)$row['project_id'].'|'.self::normaliseText((string)$row['insignia']);
            if (!isset($latest[$key])) $latest[$key]=$row;
        }
        return array_values($latest);
    }

    private static function accessibleLocationIds(int $userId, array $batches, bool $forcePersonal = false): array {
        global $wpdb;
        if (!$batches || $userId <= 0) return [];
        $batchProjects=array_values(array_unique(array_filter(array_map(static fn($b)=>(int)($b['project_id']??0),$batches))));
        $batchClients=array_values(array_unique(array_filter(array_map(static fn($b)=>(int)($b['client_id']??0),$batches))));
        $px=$wpdb->prefix.'routespro_';
        $ids=[];
        if (Permissions::is_manager($userId) && !$forcePersonal) return [];

        // Fonte principal: lojas explicitamente atribuídas ao utilizador na campanha.
        if($batchProjects){
            $sql="SELECT DISTINCT location_id FROM {$px}campaign_locations WHERE is_active=1 AND status='active' AND assigned_to=%d AND project_id IN (".implode(',',array_fill(0,count($batchProjects),'%d')).')';
            $rows=$wpdb->get_col($wpdb->prepare($sql,...array_merge([$userId],$batchProjects)))?:[];
            foreach($rows as $id)if((int)$id>0)$ids[(int)$id]=(int)$id;
        }

        // Complemento: qualquer loja presente numa rota atribuída ao utilizador, não apenas a rota de hoje.
        // Limita-se aos clientes/projetos dos snapshots publicados para impedir fugas entre âmbitos.
        $where=['(r.owner_user_id=%d OR a.user_id=%d)'];
        $args=[$userId,$userId];
        if($batchProjects){
            $where[]='r.project_id IN ('.implode(',',array_fill(0,count($batchProjects),'%d')).')';
            $args=array_merge($args,$batchProjects);
        } elseif($batchClients){
            $where[]='r.client_id IN ('.implode(',',array_fill(0,count($batchClients),'%d')).')';
            $args=array_merge($args,$batchClients);
        }
        $routeSql="SELECT DISTINCT rs.location_id FROM {$px}routes r INNER JOIN {$px}route_stops rs ON rs.route_id=r.id LEFT JOIN {$px}assignments a ON a.route_id=r.id AND a.is_active=1 WHERE ".implode(' AND ',$where);
        $routeRows=$wpdb->get_col($wpdb->prepare($routeSql,...$args))?:[];
        foreach($routeRows as $id)if((int)$id>0)$ids[(int)$id]=(int)$id;

        ksort($ids);
        return array_values($ids);
    }

    public static function teamPayload(int $userId, int $clientId = 0, int $projectId = 0, bool $forcePersonal = true): array {
        global $wpdb;
        [$clientId,$projectId]=Permissions::sanitize_scope_selection($clientId,$projectId,$userId);
        $scope=Permissions::get_scope($userId);
        $batches=self::latestBatches($clientId,$projectId,$scope);
        if(!$batches)return ['available'=>false,'message'=>'Ainda não existem stocks publicados para o teu âmbito.','batches'=>[],'stores'=>[],'summary'=>[]];
        $batchIds=array_map(static fn($b)=>(int)$b['id'],$batches);
        $locationIds=self::accessibleLocationIds($userId,$batches,$forcePersonal);
        $where=['i.batch_id IN ('.implode(',',array_fill(0,count($batchIds),'%d')).')','i.location_id>0'];
        $args=$batchIds;
        if($locationIds){$where[]='i.location_id IN ('.implode(',',array_fill(0,count($locationIds),'%d')).')';$args=array_merge($args,$locationIds);}
        elseif($forcePersonal || !Permissions::is_manager($userId)){$where[]='1=0';}
        $sql='SELECT i.*,l.name location_name,l.tags location_tags,l.meta_json location_meta_json,b.snapshot_date,b.insignia,b.project_id,b.client_id,b.source_filename FROM '.self::itemTable()." i INNER JOIN ".self::batchTable()." b ON b.id=i.batch_id LEFT JOIN {$wpdb->prefix}routespro_locations l ON l.id=i.location_id WHERE ".implode(' AND ',$where).' ORDER BY i.location_id ASC,i.stock_total ASC,i.product_name ASC LIMIT 5000';
        $rows=$wpdb->get_results($wpdb->prepare($sql,...$args),ARRAY_A)?:[];
        $threshold=self::lowThreshold();$stores=[];$totalStock=0;$zero=0;$low=0;$negative=0;$products=0;$crossInsigniaRows=0;
        foreach($rows as $row){
            // 9DL: published legacy stock mappings are not silently renamed or transferred.
            if (!self::locationMatchesInsignia(['name'=>$row['location_name'] ?? '', 'tags'=>$row['location_tags'] ?? '', 'meta_json'=>$row['location_meta_json'] ?? ''], (string)$row['insignia'])) { $crossInsigniaRows++; continue; }
            $loc=(int)$row['location_id'];
            $storeKey=self::normaliseText((string)$row['insignia']).'|'.$loc;
            if(!isset($stores[$storeKey]))$stores[$storeKey]=['store_key'=>$storeKey,'location_id'=>$loc,'name'=>(string)($row['location_name']?:$row['store_name']),'source_name'=>(string)$row['store_name'],'locality'=>(string)$row['locality'],'snapshot_date'=>(string)$row['snapshot_date'],'insignia'=>(string)$row['insignia'],'project_id'=>(int)$row['project_id'],'client_id'=>(int)$row['client_id'],'product_count'=>0,'stock_total'=>0.0,'zero_count'=>0,'low_count'=>0,'negative_count'=>0,'products'=>[]];
            $stock=(float)$row['stock_total'];$totalStock+=$stock;$products++;
            $stores[$storeKey]['product_count']++;$stores[$storeKey]['stock_total']+=$stock;
            if($stock<0){$negative++;$stores[$storeKey]['negative_count']++;}
            elseif($stock==0.0){$zero++;$stores[$storeKey]['zero_count']++;}
            elseif($stock<=$threshold){$low++;$stores[$storeKey]['low_count']++;}
            if(count($stores[$storeKey]['products'])<160)$stores[$storeKey]['products'][]=['article_ref'=>(string)$row['article_ref'],'ean'=>(string)$row['ean'],'name'=>(string)$row['product_name'],'unit'=>(string)$row['unit'],'conversion_factor'=>(float)$row['conversion_factor'],'stock'=>(float)$row['stock_total'],'status'=>$stock<0?'negative':($stock==0.0?'zero':($stock<=$threshold?'low':'ok'))];
        }
        foreach($stores as &$store){$store['stock_total']=round((float)$store['stock_total'],2);usort($store['products'],static fn($a,$b)=>($a['stock']<=>$b['stock'])?:strnatcasecmp($a['name'],$b['name']));}unset($store);
        uasort($stores,static fn($a,$b)=>(($b['negative_count']+$b['zero_count']+$b['low_count'])<=>($a['negative_count']+$a['zero_count']+$a['low_count']))?:strnatcasecmp($a['name'],$b['name']));
        $insignias=array_values(array_unique(array_map(static fn($b)=>sanitize_text_field((string)$b['insignia']),$batches)));
        return ['available'=>true,'message'=>'Stock operacional publicado e pronto para consulta.','low_threshold'=>$threshold,'batch_ids'=>$batchIds,'batches'=>array_map([self::class,'publicBatch'],$batches),'insignias'=>$insignias,'summary'=>['stores'=>count($stores),'products'=>$products,'stock_total'=>round($totalStock,2),'zero_count'=>$zero,'low_count'=>$low,'negative_count'=>$negative,'insignias'=>count($insignias),'scope_label'=>$locationIds?'Apenas lojas atribuídas ao utilizador':'Sem lojas atribuídas','cross_insignia_rows'=>$crossInsigniaRows],'stores'=>array_values($stores)];
    }

    public static function clientPayload(int $clientId, int $projectId, int $userId): array {
        global $wpdb;
        $permission=Permissions::assert_scope_or_error($clientId,$projectId,$userId);
        if(is_wp_error($permission))return ['error'=>$permission];
        $batches=self::latestBatches($clientId,$projectId,Permissions::get_scope($userId));
        if(!$batches)return ['available'=>false,'message'=>'Ainda não existem stocks publicados neste âmbito.','batches'=>[],'summary'=>[],'risks'=>[]];
        $batchIds=array_map(static fn($b)=>(int)$b['id'],$batches);$ph=implode(',',array_fill(0,count($batchIds),'%d'));$threshold=self::lowThreshold();
        $sourceStores=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(DISTINCT CONCAT(LOWER(TRIM(b.insignia)),\'|\',i.source_store_key)) FROM '.self::itemTable()." i INNER JOIN ".self::batchTable()." b ON b.id=i.batch_id WHERE i.batch_id IN ({$ph})",...$batchIds));
        $summary=$wpdb->get_row($wpdb->prepare('SELECT COUNT(*) rows_count,COUNT(DISTINCT i.location_id) matched_locations,COUNT(DISTINCT COALESCE(NULLIF(i.ean,\'\'),NULLIF(i.article_ref,\'\'),i.product_name)) products,COALESCE(SUM(i.stock_total),0) stock_total,SUM(CASE WHEN i.stock_total=0 THEN 1 ELSE 0 END) zero_count,SUM(CASE WHEN i.stock_total>0 AND i.stock_total<=%f THEN 1 ELSE 0 END) low_count,SUM(CASE WHEN i.stock_total<0 THEN 1 ELSE 0 END) negative_count FROM '.self::itemTable()." i WHERE i.batch_id IN ({$ph}) AND i.location_id>0",...array_merge([$threshold],$batchIds)),ARRAY_A)?:[];
        $riskRows=$wpdb->get_results($wpdb->prepare('SELECT i.location_id,COALESCE(l.name,i.store_name) store_name,i.store_external_ref,i.product_name,i.ean,i.article_ref,i.stock_total,b.insignia,b.snapshot_date FROM '.self::itemTable()." i INNER JOIN ".self::batchTable()." b ON b.id=i.batch_id LEFT JOIN {$wpdb->prefix}routespro_locations l ON l.id=i.location_id WHERE i.batch_id IN ({$ph}) AND i.location_id>0 AND i.stock_total<=%f ORDER BY i.stock_total ASC,i.store_name ASC,i.product_name ASC LIMIT 240",...array_merge($batchIds,[$threshold])),ARRAY_A)?:[];
        $storeRisks=$wpdb->get_results($wpdb->prepare('SELECT COALESCE(l.name,i.store_name) store_name,b.insignia,COUNT(*) product_count,SUM(CASE WHEN i.stock_total<0 THEN 1 ELSE 0 END) negative_count,SUM(CASE WHEN i.stock_total=0 THEN 1 ELSE 0 END) zero_count,SUM(CASE WHEN i.stock_total>0 AND i.stock_total<=%f THEN 1 ELSE 0 END) low_count FROM '.self::itemTable()." i INNER JOIN ".self::batchTable()." b ON b.id=i.batch_id LEFT JOIN {$wpdb->prefix}routespro_locations l ON l.id=i.location_id WHERE i.batch_id IN ({$ph}) AND i.location_id>0 GROUP BY i.location_id,b.insignia,COALESCE(l.name,i.store_name) ORDER BY negative_count DESC,zero_count DESC,low_count DESC LIMIT 40",...array_merge([$threshold],$batchIds)),ARRAY_A)?:[];
        $audience=self::audienceUserIds($clientId,$projectId);
        $viewRows=$wpdb->get_results($wpdb->prepare('SELECT user_id,MAX(last_viewed_at) last_viewed_at,SUM(view_count) view_count FROM '.self::viewTable()." WHERE batch_id IN ({$ph}) GROUP BY user_id",...$batchIds),ARRAY_A)?:[];
        $viewedIds=array_values(array_unique(array_map(static fn($r)=>(int)$r['user_id'],$viewRows)));
        $batchViewRows=$wpdb->get_results($wpdb->prepare('SELECT batch_id,COUNT(DISTINCT user_id) viewed_users FROM '.self::viewTable()." WHERE batch_id IN ({$ph}) GROUP BY batch_id",...$batchIds),ARRAY_A)?:[];
        $viewedByBatch=[];foreach($batchViewRows as $viewRow)$viewedByBatch[(int)$viewRow['batch_id']]=(int)$viewRow['viewed_users'];
        $batchPublic=[];foreach($batches as $batch){$row=self::publicBatch($batch);$row['viewed_users']=$viewedByBatch[(int)$batch['id']]??0;$batchPublic[]=$row;}
        $insignias=array_values(array_unique(array_map(static fn($b)=>sanitize_text_field((string)$b['insignia']),$batches)));
        return ['available'=>true,'message'=>'A informação foi publicada para a equipa de terreno.','low_threshold'=>$threshold,'insignias'=>$insignias,'summary'=>['rows'=>(int)($summary['rows_count']??0),'stores'=>$sourceStores,'matched_locations'=>(int)($summary['matched_locations']??0),'products'=>(int)($summary['products']??0),'stock_total'=>round((float)($summary['stock_total']??0),2),'zero_count'=>(int)($summary['zero_count']??0),'low_count'=>(int)($summary['low_count']??0),'negative_count'=>(int)($summary['negative_count']??0),'insignias'=>count($insignias),'audience_count'=>count($audience),'viewed_count'=>count(array_intersect($audience,$viewedIds)),'pending_views'=>max(0,count($audience)-count(array_intersect($audience,$viewedIds)))],'batches'=>$batchPublic,'risks'=>array_map(static function($r)use($threshold){$stock=(float)$r['stock_total'];$r['stock_total']=$stock;$r['status']=$stock<0?'negative':($stock==0.0?'zero':($stock<=$threshold?'low':'ok'));return $r;},$riskRows),'store_risks'=>$storeRisks,'views'=>$viewRows];
    }

    private static function audienceUserIds(int $clientId,int $projectId): array {
        global $wpdb;$px=$wpdb->prefix.'routespro_';$ids=[];
        foreach(Permissions::get_associated_user_ids($clientId,$projectId) as $id)$ids[(int)$id]=(int)$id;
        if($projectId){
            foreach($wpdb->get_col($wpdb->prepare("SELECT user_id FROM {$px}project_assignments WHERE project_id=%d AND is_active=1",$projectId))?:[] as $id)$ids[(int)$id]=(int)$id;
            foreach($wpdb->get_col($wpdb->prepare("SELECT DISTINCT assigned_to FROM {$px}campaign_locations WHERE project_id=%d AND is_active=1 AND assigned_to>0",$projectId))?:[] as $id)$ids[(int)$id]=(int)$id;
            foreach($wpdb->get_col($wpdb->prepare("SELECT DISTINCT owner_user_id FROM {$px}routes WHERE project_id=%d AND owner_user_id>0 AND date>=DATE_SUB(CURDATE(),INTERVAL 120 DAY)",$projectId))?:[] as $id)$ids[(int)$id]=(int)$id;
        }
        ksort($ids);return array_values(array_filter($ids));
    }

    public static function recordViews(array $batchIds,int $userId): void {
        global $wpdb;if(!$userId)return;$now=current_time('mysql');
        foreach(array_values(array_unique(array_filter(array_map('absint',$batchIds)))) as $batchId){
            $existing=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::viewTable().' WHERE batch_id=%d AND user_id=%d',$batchId,$userId));
            if($existing)$wpdb->query($wpdb->prepare('UPDATE '.self::viewTable().' SET last_viewed_at=%s,view_count=view_count+1 WHERE id=%d',$now,$existing));
            else $wpdb->insert(self::viewTable(),['batch_id'=>$batchId,'user_id'=>$userId,'first_viewed_at'=>$now,'last_viewed_at'=>$now,'view_count'=>1]);
        }
    }

    /** Exact stock context by mapped FieldFlow location. */
    public static function analyticsLocationContexts(array $context, array $locationIds): array {
        global $wpdb;
        $clientId=absint($context['client_id']??0); $projectId=absint($context['project_id']??0);
        $ids=array_values(array_unique(array_filter(array_map('absint',$locationIds))));
        if((!$clientId&&!$projectId)||!$ids)return [];
        $batches=self::latestBatches($clientId,$projectId,Permissions::get_scope()); if(!$batches)return [];
        $batchIds=array_map(static fn($b)=>(int)$b['id'],$batches);
        sort($batchIds); $cacheIds=$ids; sort($cacheIds); $threshold=self::lowThreshold();
        $cacheKey='ff_stock_locctx_'.substr(hash('sha256',wp_json_encode([$clientId,$projectId,$batchIds,$cacheIds,$threshold],JSON_UNESCAPED_SLASHES)),0,32);
        $cached=get_transient($cacheKey); if(is_array($cached))return $cached;
        $bph=implode(',',array_fill(0,count($batchIds),'%d')); $lph=implode(',',array_fill(0,count($ids),'%d'));
        $args=array_merge([$threshold],$batchIds,$ids);
        $sql='SELECT location_id,COUNT(DISTINCT COALESCE(NULLIF(ean,\'\'),NULLIF(article_ref,\'\'),product_name)) products,COALESCE(SUM(stock_total),0) stock_total,'
            .'SUM(CASE WHEN stock_total=0 THEN 1 ELSE 0 END) zero_count,SUM(CASE WHEN stock_total>0 AND stock_total<=%f THEN 1 ELSE 0 END) low_count,SUM(CASE WHEN stock_total<0 THEN 1 ELSE 0 END) negative_count '
            .'FROM '.self::itemTable()." WHERE batch_id IN ({$bph}) AND location_id IN ({$lph}) GROUP BY location_id";
        $summaryRows=$wpdb->get_results($wpdb->prepare($sql,...$args),ARRAY_A)?:[];
        $batchById=[]; foreach($batches as $batch)$batchById[(int)$batch['id']]=$batch;
        $insigniasByLocation=[]; $snapshotByLocation=[];
        $metaRows=$wpdb->get_results($wpdb->prepare('SELECT DISTINCT location_id,batch_id FROM '.self::itemTable()." WHERE batch_id IN ({$bph}) AND location_id IN ({$lph})",...array_merge($batchIds,$ids)),ARRAY_A)?:[];
        foreach($metaRows as $row){$lid=(int)$row['location_id'];$batch=$batchById[(int)$row['batch_id']]??[];if(!$batch)continue;$ins=trim((string)($batch['insignia']??''));if($ins!=='')$insigniasByLocation[$lid][$ins]=true;$d=(string)($batch['snapshot_date']??'');if($d!==''&&($d>($snapshotByLocation[$lid]??'')))$snapshotByLocation[$lid]=$d;}
        $riskRows=$wpdb->get_results($wpdb->prepare('SELECT location_id,product_name,article_ref,ean,stock_total FROM '.self::itemTable()." WHERE batch_id IN ({$bph}) AND location_id IN ({$lph}) AND stock_total<=%f ORDER BY stock_total ASC LIMIT 500",...array_merge($batchIds,$ids,[$threshold])),ARRAY_A)?:[];
        $risks=[]; foreach($riskRows as $row){$lid=(int)$row['location_id'];if(count($risks[$lid]??[])>=5)continue;$risks[$lid][]= ['product_name'=>sanitize_text_field((string)$row['product_name']),'article_ref'=>sanitize_text_field((string)$row['article_ref']),'ean'=>sanitize_text_field((string)$row['ean']),'stock_total'=>round((float)$row['stock_total'],2)];}
        $out=[]; foreach($summaryRows as $row){$lid=(int)$row['location_id'];if($lid<=0)continue;$out[$lid]=['available'=>true,'location_id'=>$lid,'snapshot_date'=>(string)($snapshotByLocation[$lid]??($batches[0]['snapshot_date']??'')),'insignias'=>array_keys($insigniasByLocation[$lid]??[]),'products'=>(int)($row['products']??0),'stock_total'=>round((float)($row['stock_total']??0),2),'zero_count'=>(int)($row['zero_count']??0),'low_count'=>(int)($row['low_count']??0),'negative_count'=>(int)($row['negative_count']??0),'low_threshold'=>$threshold,'risk_products'=>$risks[$lid]??[]];}
        set_transient($cacheKey,$out,120);
        return $out;
    }

    /** Aggregate exact mapped locations for a narrow route/user/store scope. */
    public static function analyticsScopedContext(array $context, array $locationIds): array {
        $map=self::analyticsLocationContexts($context,$locationIds); if(!$map)return [];
        $snapshot='';$ins=[];$products=0;$stock=0.0;$zero=0;$low=0;$negative=0;
        foreach($map as $row){$snapshot=max($snapshot,(string)($row['snapshot_date']??''));foreach((array)($row['insignias']??[]) as $i)$ins[$i]=true;$products+=(int)($row['products']??0);$stock+=(float)($row['stock_total']??0);$zero+=(int)($row['zero_count']??0);$low+=(int)($row['low_count']??0);$negative+=(int)($row['negative_count']??0);}
        return ['available'=>true,'snapshot_date'=>$snapshot,'insignias'=>array_keys($ins),'source_stores'=>count($map),'stores'=>count($map),'mapping_coverage'=>100.0,'products'=>$products,'stock_total'=>round($stock,2),'zero_count'=>$zero,'low_count'=>$low,'negative_count'=>$negative,'low_threshold'=>self::lowThreshold(),'scope'=>'mapped_locations'];
    }

    public static function analyticsContext(array $context): array {
        global $wpdb;$clientId=absint($context['client_id']??0);$projectId=absint($context['project_id']??0);
        if(!$clientId&&!$projectId)return [];
        $batches=self::latestBatches($clientId,$projectId,Permissions::get_scope());if(!$batches)return [];
        $ids=array_map(static fn($b)=>(int)$b['id'],$batches);$ph=implode(',',array_fill(0,count($ids),'%d'));$threshold=self::lowThreshold();
        $sourceStores=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(DISTINCT source_store_key) FROM '.self::itemTable()." WHERE batch_id IN ({$ph})",...$ids));
        $s=$wpdb->get_row($wpdb->prepare('SELECT COUNT(DISTINCT location_id) stores,COUNT(DISTINCT COALESCE(NULLIF(ean,\'\'),NULLIF(article_ref,\'\'),product_name)) products,COALESCE(SUM(stock_total),0) stock_total,SUM(CASE WHEN stock_total=0 THEN 1 ELSE 0 END) zero_count,SUM(CASE WHEN stock_total>0 AND stock_total<=%f THEN 1 ELSE 0 END) low_count,SUM(CASE WHEN stock_total<0 THEN 1 ELSE 0 END) negative_count FROM '.self::itemTable()." WHERE batch_id IN ({$ph}) AND location_id>0",...array_merge([$threshold],$ids)),ARRAY_A)?:[];
        $latest=$batches[0];return ['available'=>true,'snapshot_date'=>(string)$latest['snapshot_date'],'insignias'=>array_values(array_unique(array_map(static fn($b)=>sanitize_text_field((string)$b['insignia']),$batches))),'source_stores'=>$sourceStores,'stores'=>(int)($s['stores']??0),'mapping_coverage'=>$sourceStores?round(((int)($s['stores']??0)/$sourceStores)*100,1):0,'products'=>(int)($s['products']??0),'stock_total'=>round((float)($s['stock_total']??0),2),'zero_count'=>(int)($s['zero_count']??0),'low_count'=>(int)($s['low_count']??0),'negative_count'=>(int)($s['negative_count']??0),'low_threshold'=>$threshold,'published_at'=>(string)($latest['published_at']??'')];
    }

    public static function publicBatch(array $row): array {
        return ['id'=>(int)$row['id'],'client_id'=>(int)$row['client_id'],'project_id'=>(int)$row['project_id'],'client_name'=>sanitize_text_field((string)($row['client_name']??'')),'project_name'=>sanitize_text_field((string)($row['project_name']??'')),'insignia'=>sanitize_text_field((string)$row['insignia']),'snapshot_date'=>(string)$row['snapshot_date'],'status'=>sanitize_key((string)$row['status']),'source_filename'=>sanitize_file_name((string)$row['source_filename']),'sheet_name'=>sanitize_text_field((string)$row['sheet_name']),'row_count'=>(int)$row['row_count'],'store_count'=>(int)$row['store_count'],'product_count'=>(int)$row['product_count'],'matched_store_count'=>(int)$row['matched_store_count'],'unmatched_row_count'=>(int)$row['unmatched_row_count'],'total_stock'=>round((float)$row['total_stock'],2),'zero_count'=>(int)$row['zero_count'],'low_count'=>(int)$row['low_count'],'negative_count'=>(int)$row['negative_count'],'published_at'=>(string)($row['published_at']??''),'created_at'=>(string)($row['created_at']??''),'snapshot_mode'=>self::SNAPSHOT_MODE];
    }
}
