<?php
namespace RoutesPro\Briefings;

use RoutesPro\Catalog\ProductCatalogService as Catalog;
use RoutesPro\Forms\ContextQuestions;
use RoutesPro\Forms\ProductCardex;

if (!defined('ABSPATH')) exit;

/**
 * 9D2. Deterministic, tenant-scoped briefing category authority.
 *
 * Decisions are stored in regular WordPress options (no schema migration).
 * A manual decision can only follow the same product identity or an unchanged,
 * product-specific section fingerprint. Ambiguity always requires review.
 */
final class BriefingCategoryMappingService {
    public const CONTRACT = 'fieldflow-briefing-category-continuity-9d2-v1';
    public const CATALOG_CATEGORY_PICKER_9D3_CONTRACT = 'fieldflow-catalog-category-picker-9d3-v1';
    private const REGISTRY_LIMIT = 1500;
    private const CLIENT_CATEGORY_LIMIT_9D3 = 250;
    private static $categoryCache = [];

    public static function categories(int $clientId): array {
        if ($clientId <= 0) return [];
        if (isset(self::$categoryCache[$clientId])) return self::$categoryCache[$clientId];
        $values = [];
        foreach (Catalog::clientIndex($clientId) as $row) {
            if (empty($row['active'])) continue;
            $value = sanitize_text_field(trim((string)($row['category'] ?? '')));
            if ($value !== '') $values[Catalog::normalizeText($value)] = $value;
        }
        // 9D3: explicit categories created in the catalog stay available for this client,
        // even if their original product was archived/deactivated later.
        foreach ((array)get_option(self::clientCategoryOption9D3($clientId), []) as $stored) {
            if (!is_scalar($stored)) continue;
            $value = sanitize_text_field(trim((string)$stored));
            $key = Catalog::normalizeText($value);
            if ($key !== '') $values[$key] = $value;
        }
        // Include Cardex-only categories that have not yet been synchronised to the visual catalog.
        // This only informs the dropdown; a category alone never proves a product identity.
        global $wpdb;
        if (class_exists(ProductCardex::class) && isset($wpdb)) {
            $items = ProductCardex::table_items();
            $cardex = ProductCardex::table_cardex();
            $projects = $wpdb->prefix . 'routespro_projects';
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $items)) === $items
                && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $cardex)) === $cardex) {
                $found = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT i.category FROM {$items} i JOIN {$cardex} c ON c.id=i.cardex_id LEFT JOIN {$projects} p ON p.id=c.project_id WHERE i.is_active=1 AND i.category<>'' AND (c.client_id=%d OR p.client_id=%d) LIMIT 300",
                    $clientId, $clientId
                )) ?: [];
                foreach ($found as $raw) {
                    $value = sanitize_text_field(trim((string)$raw));
                    if ($value !== '') $values[Catalog::normalizeText($value)] = $value;
                }
            }
        }
        natcasesort($values);
        return self::$categoryCache[$clientId] = array_values($values);
    }

    /** Editor includes legacy/inactive catalog labels without making inactive SKUs eligible for automatic matching. */
    public static function catalogCategories(int $clientId): array {
        if ($clientId <= 0) return [];
        $known = [];
        foreach (self::categories($clientId) as $value) {
            $value = (string)$value;
            $key = Catalog::normalizeText($value);
            if ($key !== '') $known[$key] = $value;
        }
        foreach (Catalog::clientIndex($clientId) as $product) {
            $value = sanitize_text_field(trim((string)($product['category'] ?? '')));
            $key = Catalog::normalizeText($value);
            if ($key !== '' && !isset($known[$key])) $known[$key] = $value;
        }
        natcasesort($known);
        return array_values($known);
    }

    /** 9D3: client-bound category selection. Never mistake location/commercial categories for product taxonomy. */
    private static function clientCategoryOption9D3(int $clientId): string {
        return 'fieldflow_catalog_categories_9d3_client_' . $clientId;
    }

    public static function catalogCategorySelection(int $clientId, string $selected, string $newName = '', string $current = ''): string {
        if ($clientId <= 0) throw new \RuntimeException('Seleciona o cliente antes de definir uma categoria.');
        $creating = $selected === '__new__';
        if ($creating) {
            $name = sanitize_text_field(trim($newName));
            if ($name === '') throw new \RuntimeException('Escreve o nome da nova categoria.');
            $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : preg_match_all('/./us', $name);
            if (!is_int($length) || $length > 80) {
                throw new \RuntimeException('A categoria não pode ter mais de 80 caracteres.');
            }
            if (Catalog::normalizeText($name) === '') throw new \RuntimeException('Indica um nome de categoria válido.');
            // Reuse the authoritative spelling instead of creating a second category by case or accents.
            $selected = $name;
        } elseif ($selected === '') {
            return '';
        }
        $name = sanitize_text_field(trim($selected));
        $allowed = self::catalogCategories($clientId);
        // Preserve a legacy value already stored on THIS product, even if its only product was inactive.
        if ($current !== '') $allowed[] = sanitize_text_field(trim($current));
        $key = Catalog::normalizeText($name);
        if ($key === '') throw new \RuntimeException('Indica uma categoria válida.');
        foreach ($allowed as $known) {
            if (Catalog::normalizeText((string)$known) === $key) return (string)$known;
        }
        if ($creating) return $name;
        throw new \RuntimeException('A categoria selecionada não pertence a este cliente. Atualiza a página e volta a escolher.');
    }

    /** Persist the catalog editor's confirmed category in a tenant-specific option, without a schema migration. */
    public static function rememberCatalogCategory(int $clientId, string $category): bool {
        $category = sanitize_text_field(trim($category));
        if ($clientId <= 0 || $category === '' || Catalog::normalizeText($category) === '') return false;
        $key = self::clientCategoryOption9D3($clientId);
        $old = (array)get_option($key, []);
        $values = [];
        foreach ($old as $value) {
            if (!is_scalar($value)) continue;
            $value = sanitize_text_field(trim((string)$value));
            $normalized = Catalog::normalizeText($value);
            if ($normalized !== '') $values[$normalized] = $value;
        }
        $needle = Catalog::normalizeText($category);
        if (isset($values[$needle])) return true;
        if (count($values) >= self::CLIENT_CATEGORY_LIMIT_9D3) return false;
        $values[$needle] = $category;
        natcasesort($values);
        update_option($key, array_values($values), false);
        unset(self::$categoryCache[$clientId]);
        return true;
    }

    public static function sectionFingerprint(array $section): string {
        $raw = $section['content'] ?? $section['content_json'] ?? [];
        $content = is_array($raw) ? $raw : json_decode((string)$raw, true);
        if (!is_array($content)) return '';
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
        $identities = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $values = [];
            foreach (['ean', 'reference', 'sku', 'code', 'product', 'product_name', 'name'] as $field) {
                if (empty($item[$field]) || !is_scalar($item[$field])) continue;
                $normalized = Catalog::normalizeText((string)$item[$field]);
                if ($normalized !== '') $values[] = $field . ':' . $normalized;
            }
            if ($values) $identities[] = implode('|', $values);
        }
        if (!$identities) return '';
        sort($identities, SORT_STRING);
        return hash('sha256', implode(';', array_values(array_unique($identities))));
    }

    public static function productMatch(int $clientId, string $question): array {
        $text = Catalog::normalizeText($question);
        if ($text === '' || $clientId <= 0) return ['id'=>0, 'category'=>'', 'ambiguous'=>false];
        $capacity = Catalog::capacityFromName($question);
        $candidates = [];
        foreach (Catalog::clientIndex($clientId) as $id => $row) {
            if (empty($row['active'])) continue;
            $score = 0;
            $reference = Catalog::normalizeReference((string)($row['reference'] ?? ''));
            $ean = Catalog::normalizeEan((string)($row['ean'] ?? ''));
            $rowCapacity = Catalog::capacityKey($row['capacity_cl'] ?? '');
            // Different explicitly stated formats must never be treated as one SKU.
            if ($capacity !== '' && $rowCapacity !== '' && $capacity !== $rowCapacity) continue;
            $normalizedReference = Catalog::normalizeText($reference);
            if ($ean !== '' && preg_match('/(?<!\d)' . preg_quote($ean, '/') . '(?!\d)/', preg_replace('/\D+/', ' ', $question))) $score = 120;
            if ($normalizedReference !== '' && strlen($normalizedReference) >= 5 && preg_match('/(?:^| )' . preg_quote($normalizedReference, '/') . '(?: |$)/', $text)) $score = max($score, 112);
            foreach (array_merge([(string)($row['name'] ?? '')], (array)($row['aliases'] ?? [])) as $label) {
                $value = Catalog::normalizeText((string)$label);
                if (strlen($value) < 6) continue;
                if ($text === $value) $score = max($score, 110);
                elseif (strpos(' '.$text.' ', ' '.$value.' ') !== false) $score = max($score, 90);
            }
            if ($score >= 90) $candidates[(int)$id] = ['id'=>(int)$id, 'category'=>sanitize_text_field((string)($row['category'] ?? '')), 'score'=>$score];
        }
        if (!$candidates) return ['id'=>0, 'category'=>'', 'ambiguous'=>false];
        usort($candidates, static fn($a,$b) => $b['score'] <=> $a['score']);
        $best = $candidates[0];
        if (count($candidates) > 1 && $candidates[1]['score'] === $best['score']) return ['id'=>0, 'category'=>'', 'ambiguous'=>true];
        // If two products match through a brand prefix, the full exact product name
        // must win unequivocally; otherwise even a good category is not authoritative.
        return ['id'=>$best['id'], 'category'=>$best['category'], 'ambiguous'=>false];
    }

    /**
     * If a section changes because other products were added, the same product
     * can still keep its question, but only when a full explicit product token
     * (name/reference/EAN) is present in both sections and in its question.
     * Brand-only or fuzzy resemblance must never authorise a carry-over.
     */
    private static function sameProductEvidence(array $old, array $new, string $question): bool {
        $first = self::sectionQuestionEvidence($old, $question);
        $second = self::sectionQuestionEvidence($new, $question);
        return $first !== [] && $second !== [] && count(array_intersect($first, $second)) > 0;
    }

    private static function sectionQuestionEvidence(array $section, string $question): array {
        $raw = $section['content'] ?? $section['content_json'] ?? [];
        $content = is_array($raw) ? $raw : json_decode((string)$raw, true);
        if (!is_array($content)) return [];
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
        $label = Catalog::normalizeText($question);
        $found = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            foreach (['ean', 'reference', 'sku', 'code', 'product', 'product_name', 'name'] as $field) {
                if (empty($item[$field]) || !is_scalar($item[$field])) continue;
                $value = Catalog::normalizeText((string)$item[$field]);
                if (strlen($value) < 6 || strpos(' '.$label.' ', ' '.$value.' ') === false) continue;
                // Match the exact identifier, including variant and explicit format.
                $found[] = $field . ':' . $value;
            }
        }
        return array_values(array_unique($found));
    }

    private static function optionKey(int $clientId, int $projectId): string {
        return 'fieldflow_briefing_category_9d2_' . $clientId . '_' . $projectId;
    }

    public static function decisionKey(string $question, string $retailer, int $productId, string $sectionFingerprint): string {
        $name = Catalog::normalizeText($question);
        $identity = $productId > 0 ? 'catalog:' . $productId : 'section:' . ($sectionFingerprint ?: 'unknown');
        return hash('sha256', sanitize_key($retailer) . '|' . $name . '|' . $identity);
    }

    public static function resolve(int $clientId, int $projectId, string $question, string $retailer = 'all', string $sectionFingerprint = ''): array {
        $product = self::productMatch($clientId, $question);
        $productId = (int)$product['id'];
        $key = self::decisionKey($question, $retailer, $productId, $sectionFingerprint);
        $registry = get_option(self::optionKey($clientId, $projectId), []);
        $registry = is_array($registry) ? $registry : [];
        if (isset($registry[$key]) && is_array($registry[$key])) {
            $entry = $registry[$key];
            $category = sanitize_text_field((string)($entry['category'] ?? ''));
            // Keep a manual decision only while the exact identity is unchanged.
            if ($category !== '' && in_array($category, self::categories($clientId), true)) {
                return ['category'=>$category, 'source'=>'manual', 'status'=>'confirmed', 'product_id'=>$productId, 'key'=>$key];
            }
        }
        if ($productId && !empty($product['category']) && in_array($product['category'], self::categories($clientId), true)) {
            return ['category'=>$product['category'], 'source'=>'catalog', 'status'=>'automatic', 'product_id'=>$productId, 'key'=>$key];
        }
        return ['category'=>'', 'source'=>'', 'status'=>!empty($product['ambiguous'])?'ambiguous':'pending', 'product_id'=>$productId, 'key'=>$key];
    }

    public static function sectionContext(int $sectionId, int $briefingId): array {
        if ($sectionId <= 0 || $briefingId <= 0) return ['retailer'=>'all', 'fingerprint'=>''];
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT retailer_key,content_json FROM ' . BriefingService::table('briefing_sections') . ' WHERE id=%d AND briefing_id=%d LIMIT 1',
            $sectionId, $briefingId
        ), ARRAY_A);
        if (!is_array($row)) return ['retailer'=>'all', 'fingerprint'=>''];
        return ['retailer'=>sanitize_key((string)($row['retailer_key'] ?? 'all')) ?: 'all', 'fingerprint'=>self::sectionFingerprint($row)];
    }

    public static function resolveForQuestion(array $briefing, string $question, int $sectionId): array {
        $scope = self::sectionContext($sectionId, absint($briefing['id'] ?? 0));
        return self::resolve((int)($briefing['client_id'] ?? 0), (int)($briefing['project_id'] ?? 0), $question, $scope['retailer'], $scope['fingerprint']);
    }

    public static function saveManual(int $clientId, int $projectId, string $question, string $retailer, string $fingerprint, string $category): bool {
        $category = sanitize_text_field(trim($category));
        if ($clientId <= 0 || $projectId <= 0 || $question === '' || !in_array($category, self::categories($clientId), true)) return false;
        $product = self::productMatch($clientId, $question);
        // A generic question without a verified product/section can be classified
        // for this briefing, but must not silently teach future briefings.
        if ((int)$product['id'] <= 0 && $fingerprint === '') return true;
        $key = self::decisionKey($question, $retailer, (int)$product['id'], $fingerprint);
        $option = self::optionKey($clientId, $projectId);
        $entries = get_option($option, []);
        $entries = is_array($entries) ? $entries : [];
        $entries[$key] = [
            'category'=>$category,
            'product_id'=>(int)$product['id'],
            'fingerprint'=>$fingerprint,
            'updated_by'=>get_current_user_id(),
            'updated_at'=>current_time('mysql'),
        ];
        if (count($entries) > self::REGISTRY_LIMIT) $entries = array_slice($entries, -self::REGISTRY_LIMIT, null, true);
        return update_option($option, $entries, false) || (array)get_option($option, []) === $entries;
    }

    /** Update every location row belonging to the same briefing/question (never another client). */
    public static function assign(int $briefingId, string $questionKey, int $clientId, int $projectId, string $category): int {
        global $wpdb;
        if ($briefingId <= 0 || $clientId <= 0 || $projectId <= 0 || $questionKey === '') return 0;
        $briefing = BriefingService::get($briefingId);
        if (!$briefing || (int)$briefing['client_id'] !== $clientId || (int)$briefing['project_id'] !== $projectId) return 0;
        if (!in_array($category, self::categories($clientId), true)) return 0;
        $table = ContextQuestions::table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,question_label,briefing_section_id,visibility_rules_json FROM {$table} WHERE briefing_id=%d AND client_id=%d AND project_id=%d AND question_key=%s",
            $briefingId, $clientId, $projectId, $questionKey
        ), ARRAY_A) ?: [];
        if (!$rows) return 0;
        $context = self::sectionContext((int)$rows[0]['briefing_section_id'], $briefingId);
        if (!self::saveManual($clientId, $projectId, (string)$rows[0]['question_label'], $context['retailer'], $context['fingerprint'], $category)) return 0;
        $updated = 0;
        foreach ($rows as $row) {
            $rules = json_decode((string)$row['visibility_rules_json'], true);
            $rules = is_array($rules) ? $rules : [];
            $rules['product_category'] = $category;
            $rules['category_source'] = 'manual';
            $rules['category_identity'] = self::decisionKey((string)$row['question_label'], $context['retailer'], (int)self::productMatch($clientId, (string)$row['question_label'])['id'], $context['fingerprint']);
            $result = $wpdb->update($table, [
                'visibility_rules_json'=>wp_json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at'=>current_time('mysql'),
            ], ['id'=>(int)$row['id']]);
            if ($result !== false) $updated++;
        }
        return $updated;
    }

    /** Explicit, idempotent Backoffice backfill. Never overrides a confirmed manual choice. */
    public static function synchronizeExisting(int $clientId, int $projectId = 0): array {
        global $wpdb;
        $stats = ['updated'=>0,'pending'=>0,'manual'=>0];
        if ($clientId <= 0) return $stats;
        $table = ContextQuestions::table();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return $stats;
        $where = 'q.client_id=%d AND q.source_type=\'briefing\' AND b.status IN (\'draft\',\'published\')';
        $args = [$clientId];
        if ($projectId > 0) { $where .= ' AND q.project_id=%d'; $args[] = $projectId; }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT q.id,q.briefing_id,q.project_id,q.briefing_section_id,q.question_label,q.visibility_rules_json
             FROM {$table} q INNER JOIN " . BriefingService::table('briefings') . " b ON b.id=q.briefing_id AND b.client_id=q.client_id AND b.project_id=q.project_id
             WHERE {$where} ORDER BY q.id DESC LIMIT 2000", ...$args
        ), ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $rules = json_decode((string)$row['visibility_rules_json'], true);
            $rules = is_array($rules) ? $rules : [];
            $briefing = ['id'=>(int)$row['briefing_id'],'client_id'=>$clientId,'project_id'=>(int)$row['project_id']];
            $decision = self::resolveForQuestion($briefing, (string)$row['question_label'], (int)$row['briefing_section_id']);
            if (($rules['category_source'] ?? '') === 'manual' && (string)($rules['category_identity'] ?? '') === (string)$decision['key']) {
                $stats['manual']++; continue;
            }
            if (empty($decision['category'])) { $stats['pending']++; continue; }
            if ((string)($rules['product_category'] ?? '') === (string)$decision['category']
                && (string)($rules['category_identity'] ?? '') === (string)$decision['key']) continue;
            $rules['product_category'] = (string)$decision['category'];
            $rules['category_source'] = (string)$decision['source'];
            $rules['category_identity'] = (string)$decision['key'];
            if ($wpdb->update($table, ['visibility_rules_json'=>wp_json_encode($rules,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 'updated_at'=>current_time('mysql')], ['id'=>(int)$row['id']]) !== false) $stats['updated']++;
        }
        return $stats;
    }

    /**
     * Restore only confirmed product-specific question assignments. A changed
     * product list, absent catalog identity or missing mapping never auto-copies.
     * Old records and submissions remain untouched; re-imports are idempotent.
     */
    public static function inheritVerifiedQuestions(int $briefingId): array {
        global $wpdb;
        $new = BriefingService::get($briefingId);
        $stats = ['restored'=>0,'review'=>0];
        if (!$new) return $stats;
        $clientId = (int)$new['client_id']; $projectId = (int)$new['project_id'];
        $old = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . BriefingService::table('briefings') . " WHERE client_id=%d AND project_id=%d AND id<>%d AND status IN ('published','archived') AND valid_from<=%s ORDER BY valid_from DESC,id DESC LIMIT 1",
            $clientId, $projectId, $briefingId, (string)$new['valid_from']
        ), ARRAY_A);
        if (!$old) return $stats;
        $sectionsTable = BriefingService::table('briefing_sections');
        $oldSections = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$sectionsTable} WHERE briefing_id=%d", (int)$old['id']), ARRAY_A) ?: [];
        $newSections = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$sectionsTable} WHERE briefing_id=%d", $briefingId), ARRAY_A) ?: [];
        $oldById = [];
        foreach ($oldSections as $section) $oldById[(int)$section['id']] = $section;
        $newByIdentity = [];
        foreach ($newSections as $section) {
            $key = sanitize_key((string)$section['retailer_key']) . '|' . sanitize_key((string)$section['section_type']) . '|' . Catalog::normalizeText((string)$section['title']);
            $newByIdentity[$key][] = $section;
        }
        $table = ContextQuestions::table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE briefing_id=%d AND source_type='briefing' AND is_active=1 ORDER BY priority ASC,id ASC LIMIT 1500",
            (int)$old['id']
        ), ARRAY_A) ?: [];
        $newMappingIds = array_fill_keys(array_map('intval', $wpdb->get_col($wpdb->prepare(
            'SELECT DISTINCT location_id FROM ' . BriefingService::table('briefing_store_items') . ' WHERE briefing_id=%d AND location_id>0', $briefingId
        )) ?: []), true);
        // Existing shared store-alias mappings also count as linked new stores.
        $already = [];
        foreach ($wpdb->get_results($wpdb->prepare("SELECT question_key,location_id FROM {$table} WHERE briefing_id=%d", $briefingId), ARRAY_A) ?: [] as $r) {
            $already[$r['question_key'].'|'.$r['location_id']] = true;
        }
        $keys = [];
        foreach ($rows as $row) {
            $oldSection = $oldById[(int)$row['briefing_section_id']] ?? null;
            if (!$oldSection) { $stats['review']++; continue; }
            $sectionKey = sanitize_key((string)$oldSection['retailer_key']) . '|' . sanitize_key((string)$oldSection['section_type']) . '|' . Catalog::normalizeText((string)$oldSection['title']);
            $matches = $newByIdentity[$sectionKey] ?? [];
            $oldFingerprint = self::sectionFingerprint($oldSection);
            if (count($matches) !== 1) { $stats['review']++; continue; }
            $product = self::productMatch($clientId, (string)$row['question_label']);
            if ((int)$product['id'] <= 0 || !empty($product['ambiguous'])) { $stats['review']++; continue; }
            $sameSection = $oldFingerprint !== '' && $oldFingerprint === self::sectionFingerprint($matches[0]);
            if (!$sameSection && !self::sameProductEvidence($oldSection, $matches[0], (string)$row['question_label'])) {
                $stats['review']++; continue;
            }
            $scope = json_decode((string)($row['visibility_rules_json'] ?? ''), true);
            $scope = is_array($scope) ? $scope : [];
            $scopeType = sanitize_key((string)($scope['target_scope'] ?? 'section')) ?: 'section';
            $locationId = (int)$row['location_id'];
            if (!$locationId) continue;
            $allowUnmapped = BriefingService::questionScopeAllowsUnmapped($scopeType);
            if (!$allowUnmapped && !isset($newMappingIds[$locationId])) { $stats['review']++; continue; }
            $newSectionId = (int)$matches[0]['id'];
            $newKey = 'brf_' . $briefingId . '_' . substr(hash('sha256', $row['question_key'] . '|' . $newSectionId), 0, 20);
            if (isset($already[$newKey.'|'.$locationId])) continue;
            $newRules = $scope;
            $decision = self::resolveForQuestion($new, (string)$row['question_label'], $newSectionId);
            $category = sanitize_text_field((string)($decision['category'] ?? ''));
            if ($category !== '') {
                $newRules['product_category'] = $category;
                $newRules['category_source'] = (string)$decision['source'];
                $newRules['category_identity'] = (string)$decision['key'];
            } else {
                unset($newRules['product_category'], $newRules['category_source'], $newRules['category_identity']);
            }
            $copy = $row;
            unset($copy['id'], $copy['location_name'], $copy['form_title']);
            $copy['briefing_id'] = $briefingId;
            $copy['briefing_version_no'] = (int)$new['version_no'];
            $copy['briefing_section_id'] = $newSectionId;
            $copy['question_key'] = $newKey;
            $copy['valid_from'] = $new['valid_from'];
            $copy['valid_to'] = $new['valid_to'];
            $copy['visibility_rules_json'] = wp_json_encode($newRules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $copy['created_at'] = current_time('mysql');
            $copy['updated_at'] = current_time('mysql');
            $result = $wpdb->insert($table, $copy);
            if ($result !== false) { $stats['restored']++; $already[$newKey.'|'.$locationId] = true; $keys[$newKey] = true; }
        }
        if ($stats['restored'] > 0 && class_exists('\\RoutesPro\\Mobile\\OperationalContentRevision')) {
            \RoutesPro\Mobile\OperationalContentRevision::bump($projectId, 'briefing_question_continuity');
        }
        return $stats;
    }
}
