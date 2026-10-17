<?php
namespace RoutesPro\Forms;

if (!defined('ABSPATH')) exit;

class ProductCardex {
    public const ORDER_SOURCE_CARDEX_CONSISTENCY_CONTRACT = 'fieldflow-order-source-cardex-consistency-v1';
    private static $schema_checked = false;

    public static function table_cardex(): string { global $wpdb; return $wpdb->prefix . 'routespro_product_cardex'; }
    public static function table_items(): string { global $wpdb; return $wpdb->prefix . 'routespro_product_cardex_items'; }
    public static function table_locations(): string { global $wpdb; return $wpdb->prefix . 'routespro_product_cardex_locations'; }
    public static function table_item_locations(): string { global $wpdb; return $wpdb->prefix . 'routespro_product_cardex_item_locations'; }
    public static function table_store_overrides(): string { global $wpdb; return $wpdb->prefix . 'routespro_product_cardex_store_overrides'; }
    public static function table_store_state(): string { global $wpdb; return $wpdb->prefix . 'routespro_product_cardex_store_state'; }
    public static function table_store_revisions(): string { global $wpdb; return $wpdb->prefix . 'routespro_product_cardex_store_revisions'; }
    public static function table_store_revision_items(): string { global $wpdb; return $wpdb->prefix . 'routespro_product_cardex_store_revision_items'; }

    public static function install(): void {
        if (self::$schema_checked) return;
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE " . self::table_cardex() . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            insignia VARCHAR(191) NULL,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY insignia_idx (insignia),
            KEY status_idx (status)
        ) $charset;");

        dbDelta("CREATE TABLE " . self::table_items() . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cardex_id BIGINT UNSIGNED NOT NULL,
            category VARCHAR(191) NOT NULL DEFAULT '',
            reference VARCHAR(191) NULL,
            item_type VARCHAR(191) NOT NULL DEFAULT '',
            product_name VARCHAR(255) NOT NULL,
            capacity_cl DECIMAL(10,2) NULL,
            units_per_case DECIMAL(10,2) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY cardex_idx (cardex_id),
            KEY category_idx (category),
            KEY reference_idx (reference),
            KEY active_idx (is_active)
        ) $charset;");

        dbDelta("CREATE TABLE " . self::table_locations() . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cardex_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_cardex_location (cardex_id, location_id),
            KEY cardex_idx (cardex_id),
            KEY location_idx (location_id)
        ) $charset;");

        dbDelta("CREATE TABLE " . self::table_item_locations() . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cardex_id BIGINT UNSIGNED NOT NULL,
            item_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_item_location (item_id, location_id),
            KEY cardex_idx (cardex_id),
            KEY item_idx (item_id),
            KEY location_idx (location_id),
            KEY active_idx (is_active)
        ) $charset;");

        dbDelta("CREATE TABLE " . self::table_store_overrides() . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cardex_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            identity_key VARCHAR(191) NOT NULL,
            source_item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            category VARCHAR(191) NOT NULL DEFAULT '',
            reference VARCHAR(191) NULL,
            item_type VARCHAR(191) NOT NULL DEFAULT '',
            product_name VARCHAR(255) NOT NULL DEFAULT '',
            capacity_cl DECIMAL(10,2) NULL,
            units_per_case DECIMAL(10,2) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            updated_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_store_identity (cardex_id, location_id, identity_key),
            KEY cardex_location_idx (cardex_id, location_id),
            KEY source_item_idx (source_item_id),
            KEY active_idx (is_active)
        ) $charset;");


        // Cardex vivo por loja. A tabela legacy acima continua a ser lida como snapshot
        // para instalações existentes. Novas edições usam revisões imutáveis e um
        // ponteiro de estado, evitando janelas em que um delete+insert deixava a loja
        // temporariamente sem sortido e permitindo herdar futuras alterações do Cardex base.
        dbDelta("CREATE TABLE " . self::table_store_state() . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cardex_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            current_revision_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            revision_no BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source VARCHAR(64) NOT NULL DEFAULT 'form_submission',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_cardex_location (cardex_id, location_id),
            KEY revision_idx (current_revision_id),
            KEY updated_by_idx (updated_by)
        ) $charset;");

        dbDelta("CREATE TABLE " . self::table_store_revisions() . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cardex_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            revision_no BIGINT UNSIGNED NOT NULL DEFAULT 1,
            updated_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source VARCHAR(64) NOT NULL DEFAULT 'form_submission',
            base_hash CHAR(64) NOT NULL DEFAULT '',
            effective_hash CHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_store_revision (cardex_id, location_id, revision_no),
            KEY cardex_location_idx (cardex_id, location_id),
            KEY updated_by_idx (updated_by)
        ) $charset;");

        dbDelta("CREATE TABLE " . self::table_store_revision_items() . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            revision_id BIGINT UNSIGNED NOT NULL,
            cardex_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            identity_key VARCHAR(191) NOT NULL,
            source_item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_identity_key VARCHAR(191) NOT NULL DEFAULT '',
            action VARCHAR(16) NOT NULL DEFAULT 'upsert',
            category VARCHAR(191) NOT NULL DEFAULT '',
            reference VARCHAR(191) NULL,
            item_type VARCHAR(191) NOT NULL DEFAULT '',
            product_name VARCHAR(255) NOT NULL DEFAULT '',
            capacity_cl DECIMAL(10,2) NULL,
            units_per_case DECIMAL(10,2) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_revision_identity (revision_id, identity_key),
            KEY revision_idx (revision_id),
            KEY cardex_location_idx (cardex_id, location_id),
            KEY source_item_idx (source_item_id),
            KEY action_idx (action)
        ) $charset;");
        self::$schema_checked = true;
    }

    public static function list_cardex(): array {
        global $wpdb;
        self::install();
        return $wpdb->get_results(
            "SELECT * FROM " . self::table_cardex() . " ORDER BY name ASC, id DESC",
            ARRAY_A
        ) ?: [];
    }

    public static function get_cardex(int $id): ?array {
        if (!$id) return null;
        global $wpdb;
        self::install();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . self::table_cardex() . " WHERE id=%d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function get_item(int $item_id, int $cardex_id = 0): ?array {
        if (!$item_id) return null;
        global $wpdb;
        self::install();
        $where = 'id=%d';
        $args = [$item_id];
        if ($cardex_id) {
            $where .= ' AND cardex_id=%d';
            $args[] = $cardex_id;
        }
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id AS item_id, cardex_id, category, reference AS ref, item_type, product_name AS name, capacity_cl, units_per_case, sort_order, is_active FROM " . self::table_items() . " WHERE {$where} LIMIT 1",
                ...$args
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function question_cardex_id(array $question, array $context = []): int {
        return self::resolve_question_cardex_id($question, $context);
    }

    /**
     * Mantem a sugestao de encomenda ligada ao mesmo Cardex usado pela
     * pergunta de frentes no contexto atual da loja.
     *
     * Alguns formularios antigos ficaram com a pergunta de frentes em Cardex
     * fixo e a sugestao em Cardex automatico. Quando o projeto ou o cliente da
     * loja nao estavam completos, a segunda pergunta resolvia Cardex 0 e era
     * escondida, apesar de as frentes aparecerem corretamente. Esta camada
     * reconcilia o schema em runtime, sem alterar a configuracao guardada.
     */
    public static function synchronise_order_questions_with_fronts(array $schema, array $context = []): array {
        if (empty($schema['questions']) || !is_array($schema['questions'])) return $schema;

        $questions = array_values($schema['questions']);
        $fronts = [];

        foreach ($questions as $index => $question) {
            if (!is_array($question)) continue;
            if (sanitize_key((string)($question['type'] ?? '')) !== 'product_matrix') continue;
            if (!self::question_uses_cardex($question)) continue;

            $cardex_id = self::resolve_question_cardex_id($question, $context);
            if ($cardex_id && !self::cardex_available_for_location($cardex_id, absint($context['location_id'] ?? 0))) {
                $cardex_id = 0;
            }

            $search = strtolower(remove_accents(trim(
                (string)($question['key'] ?? '') . ' ' . (string)($question['label'] ?? '')
            )));
            $fronts[] = [
                'index' => (int)$index,
                'key' => sanitize_key((string)($question['key'] ?? '')),
                'cardex_id' => (int)$cardex_id,
                'fronts_priority' => strpos($search, 'frent') !== false ? 1 : 0,
            ];
        }

        if (!$fronts) return $schema;

        foreach ($questions as $index => $question) {
            if (!is_array($question)) continue;
            if (sanitize_key((string)($question['type'] ?? '')) !== 'order_suggestion') continue;

            $uses_cardex = self::question_uses_cardex($question);
            $manual_rows = array_values(array_filter((array)($question['product_rows'] ?? []), 'is_array'));
            // 2.2.467 legacy bridge: some old schemas saved an empty order
            // suggestion as manual while the paired Fronts question already used
            // Cardex. Empty manual questions may safely inherit that Cardex at
            // runtime. Explicit manual product lists are never changed.
            if (!$uses_cardex && $manual_rows) continue;

            $own_cardex_id = $uses_cardex ? self::resolve_question_cardex_id($question, $context) : 0;
            $selected = null;
            $selected_rank = null;

            foreach ($fronts as $front) {
                $same_cardex = $own_cardex_id > 0 && (int)$front['cardex_id'] === $own_cardex_id ? 1 : 0;
                // 9DV: a fixed/automatic Cardex must never be paired with
                // Fronts from a different or unresolved assortment.
                if ($uses_cardex && !$same_cardex) continue;
                $is_previous = (int)$front['index'] <= (int)$index ? 1 : 0;
                $distance = abs((int)$index - (int)$front['index']);
                $rank = [
                    $same_cardex,
                    (int)$front['fronts_priority'],
                    $is_previous,
                    -$distance,
                    (int)$front['index'],
                ];
                if ($selected_rank === null || $rank > $selected_rank) {
                    $selected_rank = $rank;
                    $selected = $front;
                }
            }

            if (!$selected) continue;
            if (!$uses_cardex) {
                if (empty($selected['cardex_id'])) continue;
                $question['_fieldflow_runtime_cardex_bridge'] = 1;
                $question['product_source'] = 'cardex_fixed';
            }
            if (!empty($selected['cardex_id'])) {
                $question['_fieldflow_paired_cardex_id'] = (int)$selected['cardex_id'];
            }
            $question['_fieldflow_paired_fronts_key'] = (string)$selected['key'];
            $question['_fieldflow_paired_fronts_index'] = (int)$selected['index'];
            $questions[$index] = $question;
        }

        $questions = self::ensure_order_questions_follow_paired_fronts($questions);
        $schema['questions'] = $questions;
        return $schema;
    }

    /**
     * Runtime dependency guard: an order suggestion must never render before
     * the Fronts question that supplies its Cardex and quantities. Legacy
     * schemas can contain the order question earlier in the raw question list
     * after builder edits. We only move the broken case; questions that are
     * already after their paired Fronts keep their configured position.
     */
    private static function ensure_order_questions_follow_paired_fronts(array $questions): array {
        $out = [];
        $seen = [];
        $deferred = [];

        foreach ($questions as $question) {
            if (!is_array($question)) {
                $out[] = $question;
                continue;
            }

            $type = sanitize_key((string)($question['type'] ?? ''));
            $key = sanitize_key((string)($question['key'] ?? ''));
            $paired_fronts_key = $type === 'order_suggestion'
                ? sanitize_key((string)($question['_fieldflow_paired_fronts_key'] ?? ''))
                : '';

            if ($type === 'order_suggestion' && $paired_fronts_key !== '' && empty($seen[$paired_fronts_key])) {
                if (!isset($deferred[$paired_fronts_key])) $deferred[$paired_fronts_key] = [];
                $question['_fieldflow_runtime_position_repaired'] = 1;
                $deferred[$paired_fronts_key][] = $question;
                continue;
            }

            $out[] = $question;
            if ($key !== '') {
                $seen[$key] = true;
                if (!empty($deferred[$key])) {
                    foreach ($deferred[$key] as $pending) $out[] = $pending;
                    unset($deferred[$key]);
                }
            }
        }

        // Defensive fallback. A missing paired question should never drop a
        // field from the form, so unresolved suggestions retain their order at
        // the end rather than disappearing.
        foreach ($deferred as $pendingQuestions) {
            foreach ($pendingQuestions as $pending) $out[] = $pending;
        }

        return array_values($out);
    }

    public static function question_uses_cardex(array $question): bool {
        $default_source = !empty($question['cardex_id']) ? 'cardex_fixed' : 'manual';
        $source = sanitize_key((string)($question['product_source'] ?? $default_source));
        return in_array($source, ['cardex_fixed', 'cardex_auto'], true) || !empty($question['cardex_id']);
    }

    public static function normalise_profile_code(string $value): string {
        $value = trim($value);
        if ($value === '') return 'general';
        $normal = strtolower(remove_accents($value));
        $normal = preg_replace('/[^a-z0-9]+/', ' ', $normal);
        $normal = trim(preg_replace('/\s+/', ' ', (string)$normal));
        if (in_array($normal, ['geral','general','todos','todas','all','completo','completa'], true)) return 'general';
        if (preg_match('/^p\s*0*([1-9])$/', $normal, $m)) return 'p' . (int)$m[1];
        if (in_array($normal, ['fluxo t','fluxo tenso','fluxot','fluxotenso'], true)) return 'fluxo_tenso';
        $key = sanitize_key(str_replace(' ', '_', $normal));
        return $key !== '' ? $key : 'general';
    }

    public static function profile_label(string $profile): string {
        $profile = self::normalise_profile_code($profile);
        if ($profile === 'general') return 'Geral';
        if ($profile === 'fluxo_tenso') return 'Fluxo Tenso';
        if (preg_match('/^p([1-9])$/', $profile, $m)) return 'P' . (int)$m[1];
        return strtoupper(str_replace('_', ' ', $profile));
    }

    public static function assortment_profile_context(int $location_id, int $client_id = 0, int $project_id = 0, string $selected = 'general'): array {
        global $wpdb;
        self::install();
        $selected = self::normalise_profile_code($selected);
        $cardex_id = self::resolve_cardex_id_for_location($location_id, $client_id, $project_id);
        $cardex = $cardex_id ? self::get_cardex($cardex_id) : null;
        $types = [];
        $reviewTypes = []; // 9DK: audit valid source codes outside known retailer profiles.
        if ($cardex_id) {
            $raw = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT item_type FROM " . self::table_items() . " WHERE cardex_id=%d AND is_active=1 AND TRIM(COALESCE(item_type,''))<>'' ORDER BY item_type ASC",
                $cardex_id
            )) ?: [];
            $family = class_exists('\RoutesPro\Stocks\StockService')
                ? \RoutesPro\Stocks\StockService::brandFamily(trim((string)($cardex['insignia'] ?? '') . ' ' . (string)($cardex['name'] ?? '')))
                : '';
            foreach ($raw as $type) {
                $key = self::normalise_profile_code((string)$type);
                if ($key === 'general') continue;
                $allowed = true;
                if ($family === 'pingodoce') $allowed = (bool)preg_match('/^p[1-5]$/', $key);
                elseif ($family === 'continente') $allowed = in_array($key, ['pbl'], true);
                elseif ($family === 'auchan') $allowed = in_array($key, ['pbl','pbs'], true);
                elseif ($family === 'intermarche') $allowed = in_array($key, ['std','fluxo_tenso'], true);
                if (!$allowed) {
                    // Preserve the literal Excel classification. It must be
                    // selectable only by explicit store configuration, never
                    // silently interpreted as a P tier (e.g. B4 != P4).
                    $reviewTypes[$key] = self::profile_label($key);
                    continue;
                }
                if (!isset($types[$key])) $types[$key] = self::profile_label($key);
            }
        }
        uksort($types, static function(string $a, string $b): int {
            $pa = preg_match('/^p([1-9])$/', $a, $ma) ? (int)$ma[1] : 99;
            $pb = preg_match('/^p([1-9])$/', $b, $mb) ? (int)$mb[1] : 99;
            if ($pa !== $pb) return $pa <=> $pb;
            return strnatcasecmp($a, $b);
        });
        if ($selected !== 'general' && !isset($types[$selected]) && !isset($reviewTypes[$selected])) $types[$selected] = self::profile_label($selected) . ' (fora do Cardex atual)';
        $options = [['value'=>'general','label'=>'Geral, Cardex completo']];
        foreach ($types as $value => $label) $options[] = ['value'=>$value,'label'=>$label];
        foreach ($reviewTypes as $value => $label) {
            // Unlike P1..P5, codes such as B4 have no inferred hierarchy.
            $options[] = ['value'=>$value,'label'=>$label . ' (confirmar com a marca)'];
        }
        $pLevels = array_values(array_filter(array_keys($types), static fn($key) => (bool)preg_match('/^p[1-9]$/', $key)));
        return [
            'cardex_id' => $cardex_id,
            'cardex_name' => sanitize_text_field((string)($cardex['name'] ?? '')),
            'insignia' => sanitize_text_field((string)($cardex['insignia'] ?? '')),
            'selected' => $selected,
            'selected_label' => $selected === 'general' ? 'Geral' : self::profile_label($selected),
            'options' => $options,
            'review_types' => array_values($reviewTypes),
            'review_count' => count($reviewTypes),
            'cumulative' => count($pLevels) >= 2,
            'store_override' => $cardex_id > 0 && $location_id > 0 ? self::has_store_override($cardex_id, $location_id) : false,
        ];
    }

    public static function item_type_matches_profile(string $item_type, string $profile): bool {
        $profile = self::normalise_profile_code($profile);
        if ($profile === 'general') return true;
        $item = self::normalise_profile_code($item_type);
        if ($item === 'general' || $item_type === '') return false;
        if (preg_match('/^p([1-9])$/', $profile, $pm) && preg_match('/^p([1-9])$/', $item, $im)) {
            return (int)$im[1] <= (int)$pm[1];
        }
        return $item === $profile;
    }

    public static function profile_for_location(int $location_id): string {
        if ($location_id <= 0 || !class_exists('\\RoutesPro\\Stores\\StoreProfileService')) return 'general';
        $selection = \RoutesPro\Stores\StoreProfileService::assortmentSelection($location_id);
        return self::normalise_profile_code((string)($selection['assortment_profile'] ?? 'general'));
    }

    public static function cardex_has_location_links(int $cardex_id): bool {
        if (!$cardex_id) return false;
        global $wpdb;
        self::install();
        return (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM " . self::table_locations() . " WHERE cardex_id=%d LIMIT 1",
            $cardex_id
        ));
    }

    public static function cardex_is_linked_to_location(int $cardex_id, int $location_id): bool {
        if (!$cardex_id || !$location_id) return false;
        global $wpdb;
        self::install();
        return (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM " . self::table_locations() . " WHERE cardex_id=%d AND location_id=%d LIMIT 1",
            $cardex_id,
            $location_id
        ));
    }

    public static function cardex_available_for_location(int $cardex_id, int $location_id): bool {
        if (!$cardex_id) return false;
        $cardex=self::get_cardex($cardex_id);
        if (!$cardex || ($cardex['status'] ?? '')!=='active') return false;
        // 9DN historical Gate marker: StoreIdentityAuthority::locationMatches(
        // 9DP: a cloned draft, a manual clone without links, or a cross-insignia link
        // can never become visible in a fixed-form question by bypassing auto resolution.
        $meta=json_decode((string)($cardex['meta_json'] ?? ''),true);
        $meta=is_array($meta) ? $meta : [];
        $scope=(string)($meta['clone_scope'] ?? '');
        $hasLinks=self::cardex_has_location_links($cardex_id);
        if ($scope==='manual' && !$hasLinks) return false;
        // 9DU: a historical group Cardex gains umbrella evidence only from an
        // approved store link; fixed form schemas must not expose it globally.
        if (self::legacy_sonae_umbrella($cardex) && !$hasLinks) return false;
        if ($hasLinks && !($location_id>0 && self::cardex_is_linked_to_location($cardex_id,$location_id))) return false;
        $isClone = !empty($meta['clone_source_id']) || !empty($meta['clone_publication_approved']);
        $declared = self::cardex_retailer_evidence($cardex);
        $verifiedRetailer = $declared !== ''
            && \RoutesPro\Stores\StoreIdentityAuthority::family($declared) !== '';
        // 9DR: the auto resolver and a fixed form must agree. A stale manual
        // Makro link to a PDV called Pingo Doce cannot expose Makro products in
        // a fixed question. Generic legacy cards without declared retailer are
        // left under their existing explicit-link contract, never auto matched.
        if ($isClone || ($hasLinks && $verifiedRetailer)) {
            if ($location_id<1) return false;
            global $wpdb;
            $loc=$wpdb->get_row($wpdb->prepare(
                'SELECT name,tags,meta_json,client_id,project_id FROM '.$wpdb->prefix.'routespro_locations WHERE id=%d',$location_id
            ),ARRAY_A);
            if (!$loc) return false;
            if ($isClone) {
                if (!\RoutesPro\Admin\CardexCloneService::locationMatchesInsignia($declared,$loc)) return false;
            } elseif (!self::cardex_retailer_matches_location($declared,$loc)) {
                return false;
            }
            if ($isClone) {
                if ((int)($loc['client_id'] ?? 0)>0 && (int)$loc['client_id']!==(int)$cardex['client_id']) return false;
                if ((int)$cardex['project_id']>0 && (int)($loc['project_id'] ?? 0)!==(int)$cardex['project_id']) {
                    return (bool)$wpdb->get_var($wpdb->prepare(
                        'SELECT 1 FROM '.$wpdb->prefix.'routespro_campaign_locations WHERE location_id=%d AND project_id=%d AND COALESCE(is_active,1)=1 LIMIT 1',
                        $location_id,(int)$cardex['project_id']
                    ));
                }
            }
        }
        return true;
    }

    public static function question_is_available_for_context(array $question, array $context = []): bool {
        if (!self::question_uses_cardex($question)) return true;
        $cardex_id = self::resolve_question_cardex_id($question, $context);
        if (!$cardex_id) return false;
        return self::cardex_available_for_location($cardex_id, absint($context['location_id'] ?? 0));
    }

    public static function location_has_item_assortment(int $cardex_id, int $location_id): bool {
        if (!$cardex_id || !$location_id) return false;
        global $wpdb;
        self::install();
        return (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM " . self::table_item_locations() . " WHERE cardex_id=%d AND location_id=%d AND is_active=1 LIMIT 1",
            $cardex_id,
            $location_id
        ));
    }

    public static function get_items(int $cardex_id, int $location_id = 0, bool $only_assortment = false): array {
        if (!$cardex_id) return [];
        self::install();
        if ($location_id > 0) {
            $state = self::get_store_state($cardex_id, $location_id);
            if ($state && !empty($state['current_revision_id'])) {
                return self::get_effective_store_items($cardex_id, $location_id, $only_assortment, $state);
            }
            // Compatibilidade total com os snapshots criados pelas versões <= 2.2.283.
            if (self::has_legacy_store_override($cardex_id, $location_id)) {
                return self::get_legacy_store_override_items($cardex_id, $location_id);
            }
        }
        return self::get_base_items($cardex_id, $location_id, $only_assortment);
    }

    private static function get_base_items(int $cardex_id, int $location_id = 0, bool $only_assortment = false): array {
        if (!$cardex_id) return [];
        global $wpdb;
        self::install();

        $where = "i.cardex_id=%d AND i.is_active=1";
        $params = [$cardex_id];
        $join = '';
        $profileFilter = 'general';
        $hasExplicitAssortment = $only_assortment && $location_id && self::location_has_item_assortment($cardex_id, $location_id);
        if ($hasExplicitAssortment) {
            $join = " INNER JOIN " . self::table_item_locations() . " il ON il.item_id=i.id AND il.cardex_id=i.cardex_id AND il.location_id=%d AND il.is_active=1";
            array_unshift($params, $location_id);
        } elseif ($only_assortment && $location_id) {
            $profileFilter = self::profile_for_location($location_id);
        }

        $sql = "SELECT i.id AS item_id, i.category, i.reference AS ref, i.item_type, i.product_name AS name,
                       i.capacity_cl, i.units_per_case, i.sort_order
                FROM " . self::table_items() . " i" . $join . "
                WHERE " . $where . "
                ORDER BY CASE WHEN TRIM(COALESCE(i.category, '')) = '' THEN 1 ELSE 0 END ASC,
                         i.category ASC,
                         i.product_name ASC,
                         i.reference ASC,
                         i.sort_order ASC,
                         i.id ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
        if ($profileFilter !== 'general') {
            $rows = array_values(array_filter($rows, static function(array $row) use ($profileFilter): bool {
                return self::item_type_matches_profile((string)($row['item_type'] ?? ''), $profileFilter);
            }));
        }
        return self::sort_product_rows($rows);
    }

    public static function has_store_override(int $cardex_id, int $location_id): bool {
        if (!$cardex_id || !$location_id) return false;
        self::install();
        $state = self::get_store_state($cardex_id, $location_id);
        if ($state && !empty($state['current_revision_id'])) return true;
        return self::has_legacy_store_override($cardex_id, $location_id);
    }

    public static function store_override_count(int $cardex_id): int {
        if (!$cardex_id) return 0;
        global $wpdb;
        self::install();
        $state_locations = $wpdb->get_col($wpdb->prepare(
            "SELECT location_id FROM " . self::table_store_state() . " WHERE cardex_id=%d",
            $cardex_id
        )) ?: [];
        $legacy_locations = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT location_id FROM " . self::table_store_overrides() . " WHERE cardex_id=%d",
            $cardex_id
        )) ?: [];
        return count(array_unique(array_map('intval', array_merge($state_locations, $legacy_locations))));
    }

    public static function list_store_overrides(int $cardex_id): array {
        if (!$cardex_id) return [];
        global $wpdb;
        self::install();
        $locations_table = $wpdb->prefix . 'routespro_locations';
        $users_table = $wpdb->users;
        $state_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.location_id, COALESCE(l.name, CONCAT('Loja #', s.location_id)) AS location_name,
                    s.updated_at, s.updated_by, s.revision_no, s.current_revision_id,
                    COALESCE(u.display_name,'') AS updated_by_name
             FROM " . self::table_store_state() . " s
             LEFT JOIN {$locations_table} l ON l.id=s.location_id
             LEFT JOIN {$users_table} u ON u.ID=s.updated_by
             WHERE s.cardex_id=%d
             ORDER BY location_name ASC,s.location_id ASC",
            $cardex_id
        ), ARRAY_A) ?: [];
        $legacy_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT o.location_id, COALESCE(l.name, CONCAT('Loja #', o.location_id)) AS location_name,
                    MAX(o.updated_at) AS updated_at, MAX(o.updated_by) AS updated_by
             FROM " . self::table_store_overrides() . " o
             LEFT JOIN {$locations_table} l ON l.id=o.location_id
             WHERE o.cardex_id=%d
             GROUP BY o.location_id,l.name
             ORDER BY location_name ASC,o.location_id ASC",
            $cardex_id
        ), ARRAY_A) ?: [];

        $out = [];
        foreach ($legacy_rows as $row) {
            $location_id = absint($row['location_id'] ?? 0);
            if (!$location_id) continue;
            $row['mode'] = 'legacy_snapshot';
            $row['revision_no'] = 0;
            $legacy_user = !empty($row['updated_by']) ? get_userdata(absint($row['updated_by'])) : false;
            $row['updated_by_name'] = $legacy_user ? (string)$legacy_user->display_name : '';
            $row['item_count'] = count(self::get_legacy_store_override_items($cardex_id, $location_id));
            $out[$location_id] = $row;
        }
        foreach ($state_rows as $row) {
            $location_id = absint($row['location_id'] ?? 0);
            if (!$location_id) continue;
            $row['mode'] = 'live_delta';
            $row['item_count'] = count(self::get_effective_store_items($cardex_id, $location_id, true, $row));
            $out[$location_id] = $row;
        }
        uasort($out, static function(array $left, array $right): int {
            return strnatcasecmp((string)($left['location_name'] ?? ''), (string)($right['location_name'] ?? ''));
        });
        return array_values($out);
    }

    public static function delete_store_override(int $cardex_id, int $location_id): bool {
        if (!$cardex_id || !$location_id) return false;
        global $wpdb;
        self::install();
        $revision_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM " . self::table_store_revisions() . " WHERE cardex_id=%d AND location_id=%d",
            $cardex_id,
            $location_id
        )) ?: [];
        if ($revision_ids) {
            $ids = implode(',', array_map('absint', $revision_ids));
            if ($wpdb->query("DELETE FROM " . self::table_store_revision_items() . " WHERE revision_id IN ({$ids})") === false) return false;
        }
        if ($wpdb->delete(self::table_store_revisions(), ['cardex_id'=>$cardex_id,'location_id'=>$location_id], ['%d','%d']) === false) return false;
        if ($wpdb->delete(self::table_store_state(), ['cardex_id'=>$cardex_id,'location_id'=>$location_id], ['%d','%d']) === false) return false;
        if ($wpdb->delete(self::table_store_overrides(), ['cardex_id'=>$cardex_id,'location_id'=>$location_id], ['%d','%d']) === false) return false;
        return true;
    }

    public static function delete_cardex_store_state(int $cardex_id): bool {
        if (!$cardex_id) return true;
        global $wpdb;
        self::install();
        $revision_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM " . self::table_store_revisions() . " WHERE cardex_id=%d",
            $cardex_id
        )) ?: [];
        if ($revision_ids) {
            $ids = implode(',', array_map('absint', $revision_ids));
            if ($wpdb->query("DELETE FROM " . self::table_store_revision_items() . " WHERE revision_id IN ({$ids})") === false) return false;
        }
        if ($wpdb->delete(self::table_store_revisions(), ['cardex_id'=>$cardex_id], ['%d']) === false) return false;
        if ($wpdb->delete(self::table_store_state(), ['cardex_id'=>$cardex_id], ['%d']) === false) return false;
        if ($wpdb->delete(self::table_store_overrides(), ['cardex_id'=>$cardex_id], ['%d']) === false) return false;
        return true;
    }

    public static function get_store_override_items(int $cardex_id, int $location_id): array {
        if (!$cardex_id || !$location_id) return [];
        self::install();
        $state = self::get_store_state($cardex_id, $location_id);
        if ($state && !empty($state['current_revision_id'])) {
            return self::get_effective_store_items($cardex_id, $location_id, true, $state);
        }
        return self::get_legacy_store_override_items($cardex_id, $location_id);
    }

    public static function sync_store_override_from_rows(array $question, array $context, array $rows, int $user_id = 0): bool {
        if (!self::question_uses_cardex($question)) return true;
        $cardex_id = self::resolve_question_cardex_id($question, $context);
        $location_id = absint($context['location_id'] ?? 0);
        if (!$cardex_id || !$location_id || !self::cardex_available_for_location($cardex_id, $location_id)) return true;

        self::install();
        $submitted = self::normalise_override_rows($rows);
        $base = self::normalise_override_rows(self::get_base_items($cardex_id, $location_id, true));
        global $wpdb;

        // Se o sortido final voltou a ser exatamente o Cardex base, a loja deixa de
        // precisar de diferenças próprias e volta a herdar todas as futuras alterações.
        if (self::override_rows_equal($submitted, $base)) {
            return self::delete_store_override($cardex_id, $location_id);
        }

        $base_by_item = [];
        $base_by_identity = [];
        foreach ($base as $index => $row) {
            $item_id = absint($row['item_id'] ?? 0);
            if ($item_id) $base_by_item[$item_id] = $index;
            $identity = (string)($row['identity_key'] ?? '');
            if ($identity !== '' && !array_key_exists($identity, $base_by_identity)) $base_by_identity[$identity] = $index;
        }

        $matched_base = [];
        $delta_rows = [];
        foreach ($submitted as $sort_order => $row) {
            $match_index = null;
            $item_id = absint($row['item_id'] ?? 0);
            if ($item_id && array_key_exists($item_id, $base_by_item)) {
                $match_index = (int)$base_by_item[$item_id];
            } elseif (!empty($row['identity_key']) && array_key_exists((string)$row['identity_key'], $base_by_identity)) {
                $match_index = (int)$base_by_identity[(string)$row['identity_key']];
            }

            if ($match_index !== null) {
                $base_row = $base[$match_index];
                $source_item_id = absint($base_row['item_id'] ?? 0);
                $matched_base[$match_index] = true;
                if (!self::override_row_fields_equal($row, $base_row)) {
                    $delta_rows[] = self::delta_row('source:' . $source_item_id, $source_item_id, 'upsert', $row, $sort_order, (string)($base_row['identity_key'] ?? ''));
                }
                continue;
            }

            $identity = (string)($row['identity_key'] ?? '');
            if ($identity === '') continue;
            $delta_rows[] = self::delta_row('custom:' . substr(hash('sha256', $identity), 0, 48), 0, 'upsert', $row, $sort_order);
        }

        foreach ($base as $index => $base_row) {
            if (!empty($matched_base[$index])) continue;
            $source_item_id = absint($base_row['item_id'] ?? 0);
            if (!$source_item_id) continue;
            $delta_rows[] = self::delta_row('source:' . $source_item_id, $source_item_id, 'remove', $base_row, count($delta_rows), (string)($base_row['identity_key'] ?? ''));
        }

        $state = self::get_store_state($cardex_id, $location_id);
        $next_revision = max(1, (int)($state['revision_no'] ?? 0) + 1);
        $source = sanitize_key((string)($context['cardex_source'] ?? 'form_submission')) ?: 'form_submission';
        $now = current_time('mysql');
        $revision_id = 0;

        // Em concorrência rara, dois merchandisers podem tentar criar o mesmo número
        // de revisão. Faz uma única recuperação, mantendo o último write como estado atual.
        for ($attempt = 0; $attempt < 2 && !$revision_id; $attempt++) {
            $ok = $wpdb->insert(self::table_store_revisions(), [
                'cardex_id'=>$cardex_id,
                'location_id'=>$location_id,
                'revision_no'=>$next_revision,
                'updated_by'=>$user_id,
                'source'=>$source,
                'base_hash'=>self::override_rows_hash($base),
                'effective_hash'=>self::override_rows_hash($submitted),
                'created_at'=>$now,
            ], ['%d','%d','%d','%d','%s','%s','%s','%s']);
            if ($ok !== false) {
                $revision_id = (int)$wpdb->insert_id;
                break;
            }
            $next_revision = 1 + (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(MAX(revision_no),0) FROM " . self::table_store_revisions() . " WHERE cardex_id=%d AND location_id=%d",
                $cardex_id,
                $location_id
            ));
        }
        if (!$revision_id) return false;

        foreach ($delta_rows as $row) {
            $ok = $wpdb->insert(self::table_store_revision_items(), [
                'revision_id'=>$revision_id,
                'cardex_id'=>$cardex_id,
                'location_id'=>$location_id,
                'identity_key'=>$row['identity_key'],
                'source_item_id'=>$row['source_item_id'],
                'source_identity_key'=>$row['source_identity_key'],
                'action'=>$row['action'],
                'category'=>$row['category'],
                'reference'=>$row['ref'],
                'item_type'=>$row['item_type'],
                'product_name'=>$row['name'],
                'capacity_cl'=>$row['capacity_cl'] === '' ? null : $row['capacity_cl'],
                'units_per_case'=>$row['units_per_case'] === '' ? null : $row['units_per_case'],
                'sort_order'=>$row['sort_order'],
                'created_at'=>$now,
            ], ['%d','%d','%d','%s','%d','%s','%s','%s','%s','%s','%s','%f','%f','%d','%s']);
            if ($ok === false) return false;
        }

        if ($state) {
            $ok = $wpdb->query($wpdb->prepare(
                "UPDATE " . self::table_store_state() . "
                 SET current_revision_id=%d, revision_no=%d, updated_by=%d, source=%s, updated_at=%s
                 WHERE id=%d AND revision_no<=%d",
                $revision_id,
                $next_revision,
                $user_id,
                $source,
                $now,
                absint($state['id'] ?? 0),
                $next_revision
            ));
        } else {
            $ok = $wpdb->insert(self::table_store_state(), [
                'cardex_id'=>$cardex_id,
                'location_id'=>$location_id,
                'current_revision_id'=>$revision_id,
                'revision_no'=>$next_revision,
                'updated_by'=>$user_id,
                'source'=>$source,
                'created_at'=>$now,
                'updated_at'=>$now,
            ], ['%d','%d','%d','%d','%d','%s','%s','%s']);
            if ($ok === false) {
                // Uma corrida pode ter criado o state depois da leitura inicial.
                // O WHERE mantém o ponteiro monotónico e impede uma revisão mais antiga
                // de substituir outra que já tenha terminado entretanto.
                $ok = $wpdb->query($wpdb->prepare(
                    "UPDATE " . self::table_store_state() . "
                     SET current_revision_id=%d, revision_no=%d, updated_by=%d, source=%s, updated_at=%s
                     WHERE cardex_id=%d AND location_id=%d AND revision_no<=%d",
                    $revision_id,
                    $next_revision,
                    $user_id,
                    $source,
                    $now,
                    $cardex_id,
                    $location_id,
                    $next_revision
                ));
            }
        }
        if ($ok === false) return false;

        // Só depois de o novo ponteiro estar ativo removemos o snapshot legacy. Assim,
        // uma falha intermédia nunca destrói o Cardex específico que já existia.
        $wpdb->delete(self::table_store_overrides(), ['cardex_id'=>$cardex_id,'location_id'=>$location_id], ['%d','%d']);
        return true;
    }

    private static function get_store_state(int $cardex_id, int $location_id): ?array {
        if (!$cardex_id || !$location_id) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table_store_state() . " WHERE cardex_id=%d AND location_id=%d LIMIT 1",
            $cardex_id,
            $location_id
        ), ARRAY_A);
        return $row ?: null;
    }

    private static function has_legacy_store_override(int $cardex_id, int $location_id): bool {
        global $wpdb;
        return (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM " . self::table_store_overrides() . " WHERE cardex_id=%d AND location_id=%d LIMIT 1",
            $cardex_id,
            $location_id
        ));
    }

    private static function get_legacy_store_override_items(int $cardex_id, int $location_id): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT source_item_id AS item_id, category, reference AS ref, item_type, product_name AS name, capacity_cl, units_per_case, sort_order
             FROM " . self::table_store_overrides() . "
             WHERE cardex_id=%d AND location_id=%d AND is_active=1 AND identity_key<>'__empty__'
             ORDER BY sort_order ASC, id ASC",
            $cardex_id,
            $location_id
        ), ARRAY_A) ?: [];
        return self::sort_product_rows($rows);
    }

    private static function get_effective_store_items(int $cardex_id, int $location_id, bool $only_assortment, ?array $state = null): array {
        global $wpdb;
        $state = $state ?: self::get_store_state($cardex_id, $location_id);
        if (!$state || empty($state['current_revision_id'])) return self::get_base_items($cardex_id, $location_id, $only_assortment);

        $base = self::get_base_items($cardex_id, $location_id, $only_assortment);
        $delta = $wpdb->get_results($wpdb->prepare(
            "SELECT identity_key,source_item_id,source_identity_key,action,category,reference AS ref,item_type,product_name AS name,capacity_cl,units_per_case,sort_order
             FROM " . self::table_store_revision_items() . " WHERE revision_id=%d ORDER BY sort_order ASC,id ASC",
            absint($state['current_revision_id'])
        ), ARRAY_A) ?: [];
        if (!$delta) return self::sort_product_rows($base);

        $effective = [];
        $by_item = [];
        $by_identity = [];
        foreach ($base as $index => $row) {
            $row = (array)$row;
            $effective[$index] = $row;
            $item_id = absint($row['item_id'] ?? 0);
            if ($item_id) $by_item[$item_id] = $index;
            $identity = self::override_identity((string)($row['ref'] ?? ''), (string)($row['name'] ?? ''));
            if ($identity !== '' && !array_key_exists($identity, $by_identity)) $by_identity[$identity] = $index;
        }

        foreach ($delta as $change) {
            $source_item_id = absint($change['source_item_id'] ?? 0);
            $source_identity = sanitize_text_field((string)($change['source_identity_key'] ?? ''));
            $identity = self::override_identity((string)($change['ref'] ?? ''), (string)($change['name'] ?? ''));
            $target = null;
            if ($source_item_id && array_key_exists($source_item_id, $by_item)) $target = $by_item[$source_item_id];
            elseif ($source_identity !== '' && array_key_exists($source_identity, $by_identity)) $target = $by_identity[$source_identity];
            elseif ($identity !== '' && array_key_exists($identity, $by_identity)) $target = $by_identity[$identity];

            if ((string)($change['action'] ?? 'upsert') === 'remove') {
                if ($target !== null) unset($effective[$target]);
                continue;
            }

            $resolved_item_id = $target !== null ? absint($effective[$target]['item_id'] ?? $source_item_id) : $source_item_id;
            $replacement = [
                'item_id'=>$resolved_item_id,
                'category'=>self::sanitize_category($change['category'] ?? ''),
                'ref'=>sanitize_text_field((string)($change['ref'] ?? '')),
                'item_type'=>sanitize_text_field((string)($change['item_type'] ?? '')),
                'name'=>sanitize_text_field((string)($change['name'] ?? '')),
                'capacity_cl'=>self::number_or_blank($change['capacity_cl'] ?? ''),
                'units_per_case'=>self::number_or_blank($change['units_per_case'] ?? ''),
                'sort_order'=>(int)($change['sort_order'] ?? 0),
            ];
            if ($target !== null) {
                $effective[$target] = $replacement;
            } else {
                $new_index = $effective ? max(array_keys($effective)) + 1 : 0;
                $effective[$new_index] = $replacement;
                if ($source_item_id) $by_item[$source_item_id] = $new_index;
                if ($identity !== '') $by_identity[$identity] = $new_index;
            }
        }
        return self::sort_product_rows(array_values($effective));
    }

    private static function delta_row(string $identity_key, int $source_item_id, string $action, array $row, int $sort_order, string $source_identity_key = ''): array {
        return [
            'identity_key'=>sanitize_text_field($identity_key),
            'source_item_id'=>$source_item_id,
            'source_identity_key'=>sanitize_text_field($source_identity_key),
            'action'=>$action === 'remove' ? 'remove' : 'upsert',
            'category'=>self::sanitize_category($row['category'] ?? ''),
            'ref'=>sanitize_text_field((string)($row['ref'] ?? '')),
            'item_type'=>sanitize_text_field((string)($row['item_type'] ?? '')),
            'name'=>sanitize_text_field((string)($row['name'] ?? '')),
            'capacity_cl'=>self::number_or_blank($row['capacity_cl'] ?? ''),
            'units_per_case'=>self::number_or_blank($row['units_per_case'] ?? ''),
            'sort_order'=>$sort_order,
        ];
    }

    private static function override_row_fields_equal(array $left, array $right): bool {
        $fields = ['category','ref','item_type','name','capacity_cl','units_per_case'];
        foreach ($fields as $field) {
            $a = strtolower(remove_accents(trim((string)($left[$field] ?? ''))));
            $b = strtolower(remove_accents(trim((string)($right[$field] ?? ''))));
            if ($a !== $b) return false;
        }
        return true;
    }

    private static function override_rows_hash(array $rows): string {
        $canonical = [];
        foreach ($rows as $row) {
            $canonical[] = [
                'identity'=>(string)($row['identity_key'] ?? self::override_identity((string)($row['ref'] ?? ''), (string)($row['name'] ?? ''))),
                'category'=>strtolower(remove_accents(trim((string)($row['category'] ?? '')))),
                'ref'=>strtolower(remove_accents(trim((string)($row['ref'] ?? '')))),
                'item_type'=>strtolower(remove_accents(trim((string)($row['item_type'] ?? '')))),
                'name'=>strtolower(remove_accents(trim((string)($row['name'] ?? '')))),
                'capacity'=>(string)($row['capacity_cl'] ?? ''),
                'units'=>(string)($row['units_per_case'] ?? ''),
            ];
        }
        usort($canonical, static function(array $a, array $b): int { return strcmp(wp_json_encode($a), wp_json_encode($b)); });
        return hash('sha256', (string)wp_json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function normalise_override_rows(array $rows): array {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $category = self::sanitize_category($row['category'] ?? $row['categoria'] ?? '');
            $ref = sanitize_text_field((string)($row['ref'] ?? $row['reference'] ?? ''));
            $name = sanitize_text_field((string)($row['name'] ?? $row['product'] ?? ''));
            $identity = self::override_identity($ref, $name);
            if ($identity === '' || isset($seen[$identity])) continue;
            $seen[$identity] = true;
            $out[] = [
                'identity_key'=>$identity,
                'item_id'=>absint($row['item_id'] ?? $row['source_item_id'] ?? 0),
                'category'=>$category,
                'ref'=>$ref,
                'item_type'=>sanitize_text_field((string)($row['item_type'] ?? $row['type'] ?? '')),
                'name'=>$name,
                'capacity_cl'=>self::number_or_blank($row['capacity_cl'] ?? $row['capacity'] ?? ''),
                'units_per_case'=>self::number_or_blank($row['units_per_case'] ?? $row['units_case'] ?? ''),
            ];
        }
        return array_values($out);
    }

    private static function override_rows_equal(array $left, array $right): bool {
        $canonical = static function(array $rows): array {
            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    (string)($row['identity_key'] ?? ''),
                    strtolower(remove_accents(trim((string)($row['category'] ?? '')))),
                    strtolower(remove_accents(trim((string)($row['ref'] ?? '')))),
                    strtolower(remove_accents(trim((string)($row['name'] ?? '')))),
                    strtolower(remove_accents(trim((string)($row['item_type'] ?? '')))),
                    (string)($row['capacity_cl'] ?? ''),
                    (string)($row['units_per_case'] ?? ''),
                ];
            }
            sort($out);
            return $out;
        };
        return $canonical($left) === $canonical($right);
    }

    private static function override_identity(string $ref, string $name): string {
        $ref = strtolower(remove_accents(trim($ref)));
        $name = strtolower(remove_accents(trim($name)));
        $ref = preg_replace('/[^a-z0-9]+/', '', $ref) ?: '';
        $name = preg_replace('/[^a-z0-9]+/', '', $name) ?: '';
        return $ref !== '' ? 'ref:' . $ref : ($name !== '' ? 'name:' . $name : '');
    }

    public static function get_category_counts(int $cardex_id): array {
        if (!$cardex_id) return [];
        global $wpdb;
        self::install();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT category, COUNT(*) AS total
                 FROM " . self::table_items() . "
                 WHERE cardex_id=%d AND is_active=1
                 GROUP BY category
                 ORDER BY CASE WHEN TRIM(COALESCE(category, '')) = '' THEN 1 ELSE 0 END ASC,
                          category ASC",
                $cardex_id
            ),
            ARRAY_A
        ) ?: [];
        foreach ($rows as &$row) {
            $row['category'] = trim((string)($row['category'] ?? '')) ?: 'Sem categoria';
            $row['total'] = (int)($row['total'] ?? 0);
        }
        unset($row);
        usort($rows, static function(array $left, array $right): int {
            $left_category = self::sort_value($left['category'] ?? '');
            $right_category = self::sort_value($right['category'] ?? '');
            if ($left_category === '' && $right_category !== '') return 1;
            if ($left_category !== '' && $right_category === '') return -1;
            return strnatcasecmp($left_category, $right_category);
        });
        return array_values($rows);
    }

    public static function resolve_cardex_id_for_location(int $location_id, int $client_id = 0, int $project_id = 0): int {
        if (!$location_id) return 0;
        global $wpdb;
        self::install();

        $loc = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT name, tags, meta_json, client_id, project_id FROM {$wpdb->prefix}routespro_locations WHERE id=%d",
                $location_id
            ),
            ARRAY_A
        );
        if (!$loc) return 0;

        $resolved_client_id = $client_id ?: absint($loc['client_id'] ?? 0);
        $resolved_project_id = $project_id ?: absint($loc['project_id'] ?? 0);

        // Uma loja pode ter ligacoes antigas ou cardex de contextos diferentes.
        // O front escolhe primeiro o projeto exato, depois o cliente exato e por fim
        // um cardex global. Dentro do mesmo nivel prevalece a associacao guardada
        // mais recentemente, sem deixar outro projeto roubar a loja.
        $linked_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.id, c.client_id, c.project_id, c.insignia, c.meta_json, c.name AS cardex_name, cl.id AS link_id
                 FROM " . self::table_locations() . " cl
                 INNER JOIN " . self::table_cardex() . " c ON c.id=cl.cardex_id
                 WHERE cl.location_id=%d
                   AND c.status='active'
                   AND (c.client_id=0 OR %d=0 OR c.client_id=%d)
                   AND (c.project_id=0 OR %d=0 OR c.project_id=%d)
                 ORDER BY
                   CASE WHEN %d>0 AND c.project_id=%d THEN 0 WHEN c.project_id=0 THEN 1 ELSE 2 END ASC,
                   CASE WHEN %d>0 AND c.client_id=%d THEN 0 WHEN c.client_id=0 THEN 1 ELSE 2 END ASC,
                   cl.id DESC
                 LIMIT 20",
                $location_id,
                $resolved_client_id,
                $resolved_client_id,
                $resolved_project_id,
                $resolved_project_id,
                $resolved_project_id,
                $resolved_project_id,
                $resolved_client_id,
                $resolved_client_id
            ),
            ARRAY_A
        ) ?: [];
        $firstVerified = 0;
        $firstProject = -1;
        $firstClient = -1;
        $preferredSubtype = \RoutesPro\Stores\StoreIdentityAuthority::targetSubtype($loc);
        foreach ($linked_rows as $linked) {
            $declared = self::cardex_retailer_evidence([
                'insignia'=>$linked['insignia'] ?? '',
                'name'=>$linked['cardex_name'] ?? '',
                'meta_json'=>$linked['meta_json'] ?? '',
            ]);
            // 9DR: restore legacy 9DN aliases (Sonae, pingo_doce, MDL/CBD)
            // before trying the new 9DQ names. A stored link is not sufficient
            // proof on its own: cross-retailer and cross-subtype links still fail.
            if (!self::cardex_retailer_matches_location($declared, $loc)) continue;
            $id = absint($linked['id'] ?? 0);
            if (!$id) continue;
            $linkProject = absint($linked['project_id'] ?? 0);
            $linkClient = absint($linked['client_id'] ?? 0);
            $projectRank = $resolved_project_id > 0 && $linkProject === $resolved_project_id ? 2 : ($linkProject === 0 ? 1 : 0);
            $clientRank = $resolved_client_id > 0 && $linkClient === $resolved_client_id ? 2 : ($linkClient === 0 ? 1 : 0);
            if (!$firstVerified) {
                $firstVerified = $id;
                $firstProject = $projectRank;
                $firstClient = $clientRank;
            }
            // The SQL is already ordered by project and client authority.
            // Only within the same authority level may an explicit CBD/MDL/CNT
            // outrank a generic historical Sonae cardex.
            if ($projectRank !== $firstProject || $clientRank !== $firstClient) break;
            $subtype = \RoutesPro\Stores\StoreIdentityAuthority::subtype($declared);
            if ($preferredSubtype !== '' && $subtype === $preferredSubtype) return $id;
        }
        if ($firstVerified) return $firstVerified;

        $hay = self::normalise_insignia_match_text(wp_strip_all_tags(
            ($loc['name'] ?? '') . ' ' . ($loc['tags'] ?? '') . ' ' . ($loc['meta_json'] ?? '')
        ));
        foreach (self::list_cardex() as $row) {
            if (($row['status'] ?? '') !== 'active') continue;
            if (!empty($row['client_id']) && (int)$row['client_id'] !== $resolved_client_id) continue;
            if (!empty($row['project_id']) && (int)$row['project_id'] !== $resolved_project_id) continue;
            // 9DP: a manual clone with zero approved stores must never become an implicit global cardex.
            $meta=json_decode((string)($row['meta_json'] ?? ''),true);
            if (is_array($meta) && ($meta['clone_scope'] ?? '')==='manual') continue;
            // A auto-deteccao nunca pode contornar uma lista explicita de lojas.
            // Se o Cardex tem associacoes, apenas essas lojas o podem resolver.
            if (self::cardex_has_location_links((int)$row['id'])) continue;
            // 9DU: a legacy title can establish Sonae umbrella intent ONLY
            // with an approved PDV link; never publish the umbrella by name alone.
            if (self::legacy_sonae_umbrella($row)) continue;
            $insignia = self::cardex_retailer_evidence($row);
            if (self::cardex_retailer_matches_location($insignia, $loc)) return (int)$row['id'];
        }
        return 0;
    }

    /**
     * 9DS: Some long-standing imported cardex used the client/brand as the
     * `insignia` column and recorded the retailer at the end of `name`, e.g.
     * `Pernod Ricard · Sonae`. Before 9DR the linked location was enough to
     * find the cardex. Infer a KNOWN retailer only for these non-clone legacy
     * cards, then verify the real PDV retailer/subtype normally. Never
     * reinterpret new/custom clone identities or accept mismatched brands.
     */
    /**
     * 9DU: a non-cloned historical cardex labelled 'Client - Sonae' sometimes
     * stores 'Continente' as a broad group keyword. In stock imports 'Continente'
     * is correctly a CNT subtype; inside an explicitly linked Sonae cardex the
     * _legacy cardex title_ is additional verified evidence of umbrella intent.
     * This exception is not applied to cloned cardex, CNT/MDL/CBD labels,
     * auto-matched stores outside the cardex's explicit links or other families.
     */
    private static function legacy_sonae_umbrella(array $cardex): bool {
        $raw = strtolower(remove_accents(trim((string)($cardex['insignia'] ?? ''))));
        if ($raw !== 'continente') return false;
        $meta = json_decode((string)($cardex['meta_json'] ?? ''), true);
        if (is_array($meta) && (!empty($meta['clone_source_id']) || !empty($meta['clone_publication_approved']))) return false;
        $name = trim((string)($cardex['name'] ?? ''));
        $parts = preg_split('/[\x{00B7}|:;]|\s+-\s+/u', $name) ?: [];
        if (count($parts) < 2) return false;
        $tail = strtolower(remove_accents(trim((string)end($parts))));
        $tail = preg_replace('/\s+/', ' ', $tail) ?: '';
        return in_array($tail, ['sonae', 'sonae mc'], true);
    }

    private static function cardex_retailer_evidence(array $cardex): string {
        $declared = trim((string)($cardex['insignia'] ?? ''));
        if (self::legacy_sonae_umbrella($cardex)) return 'Sonae';
        if (\RoutesPro\Stores\StoreIdentityAuthority::family($declared) !== '') return $declared;
        $meta=json_decode((string)($cardex['meta_json'] ?? ''),true);
        if (is_array($meta) && (!empty($meta['clone_source_id']) || !empty($meta['clone_publication_approved']))) return $declared;
        $name=trim((string)($cardex['name'] ?? ''));
        $known=\RoutesPro\Stores\StoreIdentityAuthority::family($name);
        if ($known === '') return $declared;
        // Avoid interpreting a custom retailer named Garcias as Pingo Doce
        // merely because an administrator wrote "based on Pingo Doce" in the
        // cardex title. Only exact legacy labels or a retailer after the
        // historical visual separator supply fallback identity evidence.
        $aliases=['Sonae','Sonae MC','Continente','Continente Modelo','Continente Bom Dia',
            'Modelo','Pingo Doce','Arcol','M. Cunha','Makro','Recheio','Auchan',
            'Intermarché','El Corte Inglés','Minipreço','Mercadona','Lidl',
            'Aldi','Froiz','Apolónia','E.Leclerc'];
        $fold=static fn(string $v): string => strtolower(remove_accents(trim($v)));
        $parts=preg_split('/[·|:;]|\s+-\s+/u',$name) ?: [$name];
        $retailerTail=$fold((string)end($parts));
        foreach ($aliases as $alias) {
            $tail=$fold($alias);
            if ($fold($name) === $tail || (count($parts)>1 && $retailerTail === $tail)) {
                return $name;
            }
        }
        return $declared;
    }

    /** 9DT: explain the actual Cardex-store-client-project decision to an authorised BO administrator. */
    public static function inspect_location_binding(int $cardex_id, int $location_id, int $visit_client_id = 0, int $visit_project_id = 0): array {
        global $wpdb;
        self::install();
        $cardex = self::get_cardex($cardex_id);
        $loc = $location_id > 0 ? $wpdb->get_row($wpdb->prepare(
            'SELECT id,name,tags,meta_json,client_id,project_id FROM '.$wpdb->prefix.'routespro_locations WHERE id=%d LIMIT 1',
            $location_id
        ), ARRAY_A) : null;
        if (!$cardex || !$loc) return ['code'=>'missing','message'=>'Cardex ou PDV inexistente','resolved_id'=>0];

        $client_id = $visit_client_id > 0 ? $visit_client_id : absint($loc['client_id'] ?? 0);
        $project_id = $visit_project_id > 0 ? $visit_project_id : absint($loc['project_id'] ?? 0);
        $linked = self::cardex_is_linked_to_location($cardex_id, $location_id);
        $active = (string)($cardex['status'] ?? '') === 'active';
        $declared = self::cardex_retailer_evidence($cardex);
        $retailer_ok = self::cardex_retailer_matches_location($declared, $loc);
        $cardex_client_id = absint($cardex['client_id'] ?? 0);
        $cardex_project_id = absint($cardex['project_id'] ?? 0);
        // A zero ID is intentionally unscoped, not evidence of a different tenant.
        $client_ok = !$cardex_client_id || !$client_id || $cardex_client_id === $client_id;
        $project_ok = !$cardex_project_id || !$project_id || $cardex_project_id === $project_id;
        $visible = self::cardex_available_for_location($cardex_id,$location_id);
        $resolved_id = self::resolve_cardex_id_for_location($location_id,$client_id,$project_id);
        $code = 'ok';
        if (!$active) $code='draft';
        elseif (!$linked && self::cardex_has_location_links($cardex_id)) $code='not_linked';
        elseif (!$retailer_ok) $code='retailer_mismatch';
        elseif (!$client_ok) $code='client_mismatch';
        elseif (!$project_ok) $code='project_mismatch';
        elseif (!$visible) $code='unavailable';
        elseif ($resolved_id !== $cardex_id) $code=$resolved_id>0?'different_cardex':'unresolved';
        $messages = [
            'ok'=>'Cardex resolvido para a visita',
            'draft'=>'Cardex por publicar',
            'not_linked'=>'PDV nao associado a este cardex',
            'retailer_mismatch'=>'Insignia ou subtipo do PDV nao coincide',
            'client_mismatch'=>'Cliente da visita diferente do cliente do cardex',
            'project_mismatch'=>'Projeto da visita diferente do projeto do cardex',
            'unavailable'=>'Cardex indisponivel para este PDV',
            'different_cardex'=>'Existe outro cardex com prioridade superior neste contexto',
            'unresolved'=>'Nenhum cardex disponivel neste contexto',
        ];
        return [
            'code'=>$code,'message'=>$messages[$code] ?? $code,
            'cardex_id'=>$cardex_id,'cardex_name'=>(string)($cardex['name'] ?? ''),
            'location_id'=>$location_id,'location_name'=>(string)($loc['name'] ?? ''),
            'insignia'=>(string)($cardex['insignia'] ?? ''),'retailer_evidence'=>$declared,
            'legacy_sonae_umbrella'=>self::legacy_sonae_umbrella($cardex),
            'linked'=>$linked,'active'=>$active,'retailer_ok'=>$retailer_ok,
            'client_ok'=>$client_ok,'project_ok'=>$project_ok,'available'=>$visible,
            'cardex_client_id'=>$cardex_client_id,'cardex_project_id'=>$cardex_project_id,
            'visit_client_id'=>$client_id,'visit_project_id'=>$project_id,
            'resolved_id'=>$resolved_id,
        ];
    }

    /** Read-only reason codes for a cardex question that must remain visible. */
    public static function question_binding_issue(array $question, array $context = []): string {
        if (!self::question_uses_cardex($question)) return '';
        $location_id=absint($context['location_id'] ?? 0);
        if ($location_id <= 0) return 'Loja da visita ainda não identificada';
        $cardex_id=self::resolve_question_cardex_id($question,$context);
        if ($cardex_id <= 0) {
            global $wpdb;
            $linked=$wpdb->get_results($wpdb->prepare(
                'SELECT c.id,c.name,c.insignia,c.status,c.client_id,c.project_id,c.meta_json FROM '.self::table_locations().' cl INNER JOIN '.self::table_cardex().' c ON c.id=cl.cardex_id WHERE cl.location_id=%d ORDER BY cl.id DESC LIMIT 20',
                $location_id
            ),ARRAY_A) ?: [];
            if (!$linked) return 'Não existe uma associação de cardex para esta loja';
            $active=array_filter($linked,static fn($row) => ($row['status'] ?? '') === 'active');
            return !$active ? 'Cardex associado ainda não publicado'
                : 'Cardex associado não coincide com a insígnia ou o cliente/projeto da visita';
        }
        $cardex=self::get_cardex($cardex_id);
        if (!$cardex) return 'Cardex configurado não existe';
        if (($cardex['status'] ?? '') !== 'active') return 'Cardex configurado ainda não publicado';
        if (!self::cardex_available_for_location($cardex_id,$location_id)) return 'A loja não pertence ao âmbito comercial autorizado deste cardex';
        return '';
    }

    /**
     * 9DR: 9DQ's editable retailer registry is intentionally stricter than the
     * legacy store-matching vocabulary. Keep established 9DN links working
     * without weakening the evidence required for genuinely new retailers.
     * A manual link or a shared numeric PDV reference never overrides a
     * known store brand or Sonae subtype mismatch.
     */
    private static function cardex_retailer_matches_location(string $declared, array $location): bool {
        $declared = trim($declared);
        if ($declared === '') return false;
        if (\RoutesPro\Stores\StoreIdentityAuthority::family($declared) !== '') {
            return \RoutesPro\Stores\StoreIdentityAuthority::locationMatches($declared, $location);
        }
        return class_exists('\RoutesPro\Admin\CardexCloneService')
            && \RoutesPro\Admin\CardexCloneService::locationMatchesInsignia($declared, $location);
    }

    /**
     * Normaliza nomes/codigos de insignia usados historicamente em modulos
     * diferentes. O matching e por token/frase completa para evitar falsos
     * positivos de siglas curtas como PD dentro de outras palavras.
     */
    private static function normalise_insignia_match_text(string $value): string {
        $value = strtolower(remove_accents(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';
        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }

    private static function insignia_match_aliases(string $value): array {
        $needle = self::normalise_insignia_match_text($value);
        if ($needle === '') return [];
        $groups = [
            ['pd', 'pingo doce', 'p doce', 'pingodoce'],
            ['cbd', 'continente bom dia'],
            ['cnt', 'continente'],
            ['mdl', 'modelo', 'continente modelo'],
            ['itm', 'intermarche', 'intermarche contact'],
        ];
        foreach ($groups as $group) {
            $normalised = array_values(array_unique(array_filter(array_map([self::class, 'normalise_insignia_match_text'], $group))));
            if (in_array($needle, $normalised, true)) return $normalised;
        }
        return [$needle];
    }

    private static function resolve_question_cardex_id(array $question, array $context): int {
        $paired_cardex_id = absint($question['_fieldflow_paired_cardex_id'] ?? 0);
        if ($paired_cardex_id) return $paired_cardex_id;

        $default_source = !empty($question['cardex_id']) ? 'cardex_fixed' : 'manual';
        $source = sanitize_key((string)($question['product_source'] ?? $default_source));

        if ($source === 'cardex_auto') {
            // Em modo automatico, nunca reutilizar um cardex_id residual que tenha
            // ficado no schema quando a pergunta mudou de fixo para automatico.
            return self::resolve_cardex_id_for_location(
                absint($context['location_id'] ?? 0),
                absint($context['client_id'] ?? 0),
                absint($context['project_id'] ?? 0)
            );
        }
        if ($source === 'cardex_fixed') return absint($question['cardex_id'] ?? 0);

        // Compatibilidade com schemas legados que tinham cardex_id mas ainda nao
        // guardavam explicitamente product_source.
        return !empty($question['cardex_id']) ? absint($question['cardex_id']) : 0;
    }

    /** 9DV: shared authoritative order data semantics for web and mobile. */
    public static function normalise_order_submission_rows(array $rows, array $question, array $context,
        array $paired_front_rows = [], bool $fronts_present = false): array {
        $uses_cardex = self::question_uses_cardex($question);
        $cardex_id = $uses_cardex ? self::question_cardex_id($question, $context) : 0;
        $location_id = absint($context['location_id'] ?? 0);
        if ($uses_cardex && (!$cardex_id || !self::question_is_available_for_context($question, $context))) return [];
        if ($uses_cardex) {
            $only_assortment = array_key_exists('only_store_assortment', $question)
                ? !empty($question['only_store_assortment']) : true;
            $allowed = self::get_items($cardex_id, $location_id, $only_assortment);
        } else {
            $allowed = array_values(array_filter((array)($question['product_rows'] ?? []), 'is_array'));
        }
        $zero = isset($question['order_zero_fronts_suggestion']) && $question['order_zero_fronts_suggestion'] !== ''
            ? max(0, (float)$question['order_zero_fronts_suggestion']) : 1.0;
        return OrderSuggestionIntegrity::normalise($rows, $allowed, $paired_front_rows,
            $fronts_present, $cardex_id, $zero, !empty($question['order_quantity_decimals']));
    }

    public static function normalise_fronts_submission_rows(array $rows, array $question = [], array $context = []): array {
        $cardex_id = self::question_uses_cardex($question) ? self::resolve_question_cardex_id($question, $context) : 0;
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $category = self::sanitize_category($row['category'] ?? $row['categoria'] ?? '');
            $ref = sanitize_text_field((string)($row['ref'] ?? $row['reference'] ?? ''));
            $name = sanitize_text_field((string)($row['name'] ?? $row['product'] ?? ''));
            $has_identity = ($category !== '' || $ref !== '' || $name !== '');
            if (!$has_identity) continue;

            $has_before_field = array_key_exists('before', $row);
            $has_after_field = array_key_exists('after', $row);
            $before_raw = $has_before_field ? str_replace(',', '.', trim((string)$row['before'])) : '';
            $after_raw = $has_after_field ? str_replace(',', '.', trim((string)$row['after'])) : '';
            $qty_raw = $has_after_field
                ? $after_raw
                : str_replace(',', '.', trim((string)($row['qty'] ?? $row['quantity'] ?? '')));

            $has_qty = ($qty_raw !== '' && is_numeric($qty_raw));
            $has_before = ($before_raw !== '' && is_numeric($before_raw));
            $has_after = ($after_raw !== '' && is_numeric($after_raw));
            $qty = $has_qty ? max(0, (float)$qty_raw) : 0.0;

            $stored = [
                'item_id' => absint($row['item_id'] ?? 0),
                'cardex_id' => $cardex_id ?: absint($row['cardex_id'] ?? 0),
                'category' => $category,
                'ref' => $ref,
                'item_type' => sanitize_text_field((string)($row['item_type'] ?? $row['type'] ?? '')),
                'name' => $name,
                'capacity_cl' => self::number_or_blank($row['capacity_cl'] ?? $row['capacity'] ?? ''),
                'units_per_case' => self::number_or_blank($row['units_per_case'] ?? $row['units_case'] ?? ''),
                'qty' => $qty,
                'fronts_confirmed' => array_key_exists('fronts_confirmed', $row) ? (!empty($row['fronts_confirmed']) ? 1 : 0) : 1,
            ];
            if ($has_before_field || $has_after_field) {
                $stored['before'] = $has_before ? max(0, (float)$before_raw) : $qty;
                $stored['after'] = $has_after ? max(0, (float)$after_raw) : $qty;
                $stored['qty'] = $stored['after'];
            }
            $out[] = $stored;
        }
        return array_values($out);
    }

    public static function question_rows(array $question, array $context = [], $prefill = null): array {
        $uses_cardex = self::question_uses_cardex($question);
        $cardex_id = self::resolve_question_cardex_id($question, $context);
        $location_id = absint($context['location_id'] ?? 0);
        $base = [];

        if ($uses_cardex) {
            if (!$cardex_id || !self::cardex_available_for_location($cardex_id, $location_id)) return [];
            // Frentes respeita tambem o sortido produto a produto, quando existe.
            foreach (self::get_items($cardex_id, $location_id, true) as $row) {
                $base[] = [
                    'item_id' => absint($row['item_id'] ?? 0),
                    'cardex_id' => $cardex_id,
                    'category' => self::sanitize_category($row['category'] ?? ''),
                    'ref' => (string)($row['ref'] ?? ''),
                    'item_type' => sanitize_text_field((string)($row['item_type'] ?? '')),
                    'name' => (string)($row['name'] ?? ''),
                    'capacity_cl' => self::number_or_blank($row['capacity_cl'] ?? ''),
                    'units_per_case' => self::number_or_blank($row['units_per_case'] ?? ''),
                    'qty' => '',
                ];
            }
            $has_persistent_state = $location_id > 0 && self::has_store_override($cardex_id, $location_id);
            $preserve_visit_pair = !empty($context['_daily_existing']);
            return self::sort_product_rows(self::merge_prefill_into_rows($base, $prefill, true, !$has_persistent_state, $preserve_visit_pair));
        }

        foreach (($question['product_rows'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $base[] = [
                'category' => self::sanitize_category($row['category'] ?? $row['categoria'] ?? ''),
                'ref' => (string)($row['ref'] ?? $row['reference'] ?? ''),
                'name' => (string)($row['name'] ?? $row['product'] ?? ''),
                'qty' => '',
            ];
        }
        $preserve_visit_pair = !empty($context['_daily_existing']);
        return self::sort_product_rows(self::merge_prefill_into_rows($base, $prefill, false, false, $preserve_visit_pair));
    }

    public static function order_question_rows(array $question, array $context = [], $prefill = null): array {
        $uses_cardex = self::question_uses_cardex($question);
        $cardex_id = self::resolve_question_cardex_id($question, $context);
        $location_id = absint($context['location_id'] ?? 0);
        $only_assortment = array_key_exists('only_store_assortment', $question) ? !empty($question['only_store_assortment']) : true;
        $base = [];

        if ($uses_cardex) {
            if (!$cardex_id || !self::cardex_available_for_location($cardex_id, $location_id)) return [];
            foreach (self::get_items($cardex_id, $location_id, $only_assortment) as $row) {
                $base[] = [
                    'item_id' => absint($row['item_id'] ?? 0),
                    'cardex_id' => $cardex_id,
                    'category' => self::sanitize_category($row['category'] ?? ''),
                    'ref' => sanitize_text_field((string)($row['ref'] ?? '')),
                    'item_type' => sanitize_text_field((string)($row['item_type'] ?? '')),
                    'name' => sanitize_text_field((string)($row['name'] ?? '')),
                    'capacity_cl' => self::number_or_blank($row['capacity_cl'] ?? ''),
                    'units_per_case' => self::number_or_blank($row['units_per_case'] ?? ''),
                    'order_qty' => '',
                    'notes' => '',
                ];
            }
        } else {
            foreach (($question['product_rows'] ?? []) as $row) {
                if (!is_array($row)) continue;
                $base[] = [
                    'item_id' => absint($row['item_id'] ?? 0),
                    'cardex_id' => 0,
                    'category' => self::sanitize_category($row['category'] ?? $row['categoria'] ?? ''),
                    'ref' => sanitize_text_field((string)($row['ref'] ?? $row['reference'] ?? $row['code'] ?? '')),
                    'item_type' => sanitize_text_field((string)($row['item_type'] ?? $row['type'] ?? '')),
                    'name' => sanitize_text_field((string)($row['name'] ?? $row['product'] ?? '')),
                    'capacity_cl' => self::number_or_blank($row['capacity_cl'] ?? $row['capacity'] ?? ''),
                    'units_per_case' => self::number_or_blank($row['units_per_case'] ?? $row['units_case'] ?? ''),
                    'order_qty' => '',
                    'notes' => '',
                ];
            }
        }

        if (!empty($question['order_prefill_previous']) && is_array($prefill)) {
            $base = self::merge_order_prefill($base, $prefill);
        }
        return self::sort_product_rows($base);
    }

    private static function merge_order_prefill(array $base, array $prefill): array {
        $lookup = [];
        foreach ($prefill as $row) {
            if (!is_array($row)) continue;
            $identity = self::row_identity('', (string)($row['ref'] ?? $row['reference'] ?? ''), (string)($row['name'] ?? $row['product'] ?? ''));
            if ($identity !== '') $lookup[$identity] = $row;
        }
        foreach ($base as &$row) {
            $identity = self::row_identity('', (string)($row['ref'] ?? ''), (string)($row['name'] ?? ''));
            if ($identity === '' || empty($lookup[$identity])) continue;
            $old = $lookup[$identity];
            $row['order_qty'] = $old['order_qty'] ?? $old['boxes'] ?? $old['qty'] ?? '';
            $row['notes'] = sanitize_textarea_field((string)($old['notes'] ?? $old['observations'] ?? ''));
        }
        unset($row);
        return $base;
    }

    public static function context_header(array $context = []): array {
        global $wpdb;
        $location_id = absint($context['location_id'] ?? 0);
        $location_name = '';
        if ($location_id) {
            $location_name = (string)$wpdb->get_var($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}routespro_locations WHERE id=%d",
                $location_id
            ));
        }
        $date_value = sanitize_text_field((string)($context['date'] ?? $context['visit_date'] ?? ''));
        if ($date_value === '' && !empty($context['route_id'])) {
            $date_value = (string)$wpdb->get_var($wpdb->prepare(
                "SELECT date FROM {$wpdb->prefix}routespro_routes WHERE id=%d",
                absint($context['route_id'])
            ));
        }
        $date_display = wp_date('d/m/Y');
        if ($date_value !== '') {
            $timestamp = strtotime($date_value);
            if ($timestamp) $date_display = wp_date('d/m/Y', $timestamp);
        }
        $user = wp_get_current_user();
        return [
            'location' => $location_name ?: ($location_id ? 'Loja #' . $location_id : 'Loja não definida'),
            'date' => $date_display,
            'seller' => ($user && $user->exists()) ? (string)$user->display_name : 'Utilizador',
        ];
    }

    private static function merge_prefill_into_rows(array $base, $prefill, bool $authoritative_product_set = false, bool $allow_legacy_custom_seed = false, bool $preserve_visit_pair = false): array {
        $history = [];
        if (is_array($prefill)) {
            foreach ($prefill as $row) {
                if (!is_array($row)) continue;
                $category = self::sanitize_category($row['category'] ?? $row['categoria'] ?? '');
                $ref = sanitize_text_field((string)($row['ref'] ?? $row['reference'] ?? ''));
                $name = sanitize_text_field((string)($row['name'] ?? $row['product'] ?? ''));
                // Numa nova visita, o valor final da visita anterior passa a ser
                // a base de comparação da visita atual. Ao editar o reporte do
                // próprio dia, preservamos o par before/after já gravado.
                $has_latest_after = array_key_exists('after', $row) && $row['after'] !== '' && $row['after'] !== null;
                $latest_qty = $has_latest_after
                    ? $row['after']
                    : ($row['qty'] ?? ($row['quantity'] ?? ''));
                $qty = $latest_qty;
                if ($preserve_visit_pair) {
                    $before = array_key_exists('before', $row) ? $row['before'] : $latest_qty;
                    $after = array_key_exists('after', $row) ? $row['after'] : $latest_qty;
                } else {
                    $before = $latest_qty;
                    $after = $latest_qty;
                }
                $legacy_identity = self::row_identity('', $ref, $name);
                if (!$legacy_identity) continue;
                $history[] = [
                    'item_id' => absint($row['item_id'] ?? 0),
                    'cardex_id' => absint($row['cardex_id'] ?? 0),
                    'category' => $category,
                    'ref' => $ref,
                    'item_type' => sanitize_text_field((string)($row['item_type'] ?? $row['type'] ?? '')),
                    'name' => $name,
                    'capacity_cl' => self::number_or_blank($row['capacity_cl'] ?? $row['capacity'] ?? ''),
                    'units_per_case' => self::number_or_blank($row['units_per_case'] ?? $row['units_case'] ?? ''),
                    'qty' => $qty,
                    'before' => $before,
                    'after' => $after,
                    'has_history' => 1,
                    'fronts_confirmed' => !empty($row['fronts_confirmed']) ? 1 : 0,
                    '_identity' => self::row_identity($category, $ref, $name),
                    '_legacy_identity' => $legacy_identity,
                    '_used' => false,
                ];
            }
        }

        $out = [];
        foreach ($base as $row) {
            if (!is_array($row)) continue;
            $category = self::sanitize_category($row['category'] ?? $row['categoria'] ?? '');
            $ref = sanitize_text_field((string)($row['ref'] ?? $row['reference'] ?? ''));
            $name = sanitize_text_field((string)($row['name'] ?? $row['product'] ?? ''));
            $identity = self::row_identity($category, $ref, $name);
            $legacy_identity = self::row_identity('', $ref, $name);
            $match_index = null;

            foreach ($history as $index => $hist) {
                if (!empty($hist['_used'])) continue;
                if ($identity !== '' && ($hist['_identity'] ?? '') === $identity) { $match_index = $index; break; }
            }
            if ($match_index === null && $legacy_identity !== '') {
                foreach ($history as $index => $hist) {
                    if (!empty($hist['_used'])) continue;
                    if (($hist['_legacy_identity'] ?? '') !== $legacy_identity) continue;
                    $match_index = $index;
                    break;
                }
            }

            if ($match_index !== null) {
                $hist = $history[$match_index];
                $history[$match_index]['_used'] = true;
                $out[] = [
                    'item_id' => absint($row['item_id'] ?? $hist['item_id'] ?? 0),
                    'cardex_id' => absint($row['cardex_id'] ?? $hist['cardex_id'] ?? 0),
                    'category' => $category ?: $hist['category'],
                    'ref' => $ref ?: $hist['ref'],
                    'item_type' => sanitize_text_field((string)($row['item_type'] ?? $hist['item_type'] ?? '')),
                    'name' => $name ?: $hist['name'],
                    'capacity_cl' => self::number_or_blank($row['capacity_cl'] ?? $hist['capacity_cl'] ?? ''),
                    'units_per_case' => self::number_or_blank($row['units_per_case'] ?? $hist['units_per_case'] ?? ''),
                    'qty' => $hist['qty'],
                    'before' => $hist['before'],
                    'after' => $hist['after'],
                    'has_history' => 1,
                    'fronts_confirmed' => $preserve_visit_pair ? (int)($hist['fronts_confirmed'] ?? 0) : 0,
                ];
            } else {
                $out[] = [
                    'item_id'=>absint($row['item_id'] ?? 0),
                    'cardex_id'=>absint($row['cardex_id'] ?? 0),
                    'category'=>$category,
                    'ref'=>$ref,
                    'item_type'=>sanitize_text_field((string)($row['item_type'] ?? '')),
                    'name'=>$name,
                    'capacity_cl'=>self::number_or_blank($row['capacity_cl'] ?? ''),
                    'units_per_case'=>self::number_or_blank($row['units_per_case'] ?? ''),
                    'qty'=>'','before'=>'','after'=>'','has_history'=>0,'fronts_confirmed'=>0
                ];
            }
        }

        foreach ($history as $hist) {
            if (!empty($hist['_used'])) continue;
            // Num Cardex persistente, o conjunto atual da loja é a fonte de verdade.
            // Histórico antigo serve para os valores antes/depois, mas nunca ressuscita
            // referências removidas. Na primeira visita após upgrade, permitimos apenas
            // linhas históricas sem item_id como semente de referências custom antigas.
            if ($authoritative_product_set && (!$allow_legacy_custom_seed || !empty($hist['item_id']))) continue;
            unset($hist['_identity'], $hist['_legacy_identity'], $hist['_used']);
            $out[] = $hist;
        }
        return $out;
    }


    private static function sort_product_rows(array $rows): array {
        usort($rows, static function($left, $right): int {
            $left = is_array($left) ? $left : [];
            $right = is_array($right) ? $right : [];
            $left_category = self::sort_value($left['category'] ?? $left['categoria'] ?? '');
            $right_category = self::sort_value($right['category'] ?? $right['categoria'] ?? '');
            if ($left_category === '' && $right_category !== '') return 1;
            if ($left_category !== '' && $right_category === '') return -1;
            $category_compare = strnatcasecmp($left_category, $right_category);
            if ($category_compare !== 0) return $category_compare;
            $name_compare = strnatcasecmp(
                self::sort_value($left['name'] ?? $left['product'] ?? ''),
                self::sort_value($right['name'] ?? $right['product'] ?? '')
            );
            if ($name_compare !== 0) return $name_compare;
            return strnatcasecmp(
                self::sort_value($left['ref'] ?? $left['reference'] ?? ''),
                self::sort_value($right['ref'] ?? $right['reference'] ?? '')
            );
        });
        return array_values($rows);
    }

    private static function sort_value($value): string {
        $value = trim((string)$value);
        if ($value === '') return '';
        return strtolower(remove_accents($value));
    }

    private static function row_identity(string $category, string $ref, string $name): string {
        $category = strtolower(trim($category));
        $ref = strtolower(trim($ref));
        $name = strtolower(trim($name));
        $product_identity = $ref !== '' ? 'ref:' . $ref : ($name !== '' ? 'name:' . $name : '');
        if ($product_identity === '') return '';
        return ($category !== '' ? 'category:' . $category . '|' : '') . $product_identity;
    }

    private static function sanitize_category($value): string { return sanitize_text_field(trim((string)$value)); }
    private static function number_or_blank($value) {
        if ($value === '' || $value === null) return '';
        $value = str_replace(',', '.', trim((string)$value));
        return is_numeric($value) ? (float)$value : '';
    }
}
