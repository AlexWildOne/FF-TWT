<?php
namespace RoutesPro\Briefings;

use RoutesPro\Support\Permissions;
use RoutesPro\Mapping\MappingEngine;
use RoutesPro\Stores\StoreIdentityAuthority;

if (!defined('ABSPATH')) exit;

class BriefingService {
    public static function table(string $name): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_' . $name;
    }

    public static function retailerLabels(): array {
        return [
            'all' => 'Todas as insígnias',
            'sonae' => 'Sonae',
            'pingo_doce' => 'Pingo Doce',
            'auchan' => 'Auchan',
            'intermarche' => 'Intermarché',
            'eci' => 'El Corte Inglés',
        ];
    }

    public static function statusLabels(): array {
        return [
            'draft' => 'Rascunho',
            'published' => 'Publicado',
            'archived' => 'Arquivado',
        ];
    }

    public static function normalizeStoreName(string $name): string {
        $name = trim($name);
        if (function_exists('remove_accents')) $name = remove_accents($name);
        else $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $name = strtolower($name);
        $name = preg_replace('/\b(loja|hiper|supermercado)\b/u', ' ', $name);
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name);
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    public static function importParsed(array $parsed, array $meta, array $fileInfo): int {
        global $wpdb;
        $clientId = absint($meta['client_id'] ?? 0);
        $projectId = absint($meta['project_id'] ?? 0);
        if (!$clientId || !$projectId) throw new \RuntimeException('Seleciona o cliente e a campanha antes de importar.');
        $projectClientId = (int)$wpdb->get_var($wpdb->prepare(
            'SELECT client_id FROM ' . self::table('projects') . ' WHERE id=%d LIMIT 1',
            $projectId
        ));
        if (!$projectClientId || $projectClientId !== $clientId) {
            throw new \RuntimeException('A campanha selecionada não pertence ao cliente indicado.');
        }

        $weekOverride = absint($meta['week_no'] ?? 0);
        $yearOverride = absint($meta['year_no'] ?? 0);
        $week = max(1, min(53, $weekOverride ?: absint($parsed['week_no'] ?? date('W'))));
        $year = max(2020, min(2100, $yearOverride ?: absint($parsed['year_no'] ?? date('Y'))));
        $titleOverride = trim((string)($meta['title'] ?? ''));
        $title = sanitize_text_field($titleOverride !== '' ? $titleOverride : (string)($parsed['title'] ?? ('Briefing W' . $week)));
        $validFrom = self::validDate((string)($meta['valid_from'] ?? '')) ?: self::validDate((string)($parsed['valid_from'] ?? ''));
        $validTo = self::validDate((string)($meta['valid_to'] ?? '')) ?: self::validDate((string)($parsed['valid_to'] ?? ''));
        if (!$validFrom || !$validTo) {
            $dt = new \DateTime();
            $dt->setISODate($year, $week, 1);
            $validFrom = $validFrom ?: $dt->format('Y-m-d');
            $dt->modify('+6 days');
            $validTo = $validTo ?: $dt->format('Y-m-d');
        }
        if ($validTo < $validFrom) [$validFrom, $validTo] = [$validTo, $validFrom];

        $briefings = self::table('briefings');
        $sectionsTable = self::table('briefing_sections');
        $itemsTable = self::table('briefing_store_items');
        $aliasesTable = self::table('briefing_store_aliases');
        $version = 1 + (int)$wpdb->get_var($wpdb->prepare(
            "SELECT MAX(version_no) FROM {$briefings} WHERE client_id=%d AND project_id=%d AND year_no=%d AND week_no=%d",
            $clientId, $projectId, $year, $week
        ));

        $sourceName = sanitize_file_name((string)($fileInfo['name'] ?? 'briefing.xlsx'));
        $sourceHash = sanitize_text_field((string)($fileInfo['hash'] ?? ''));
        $summary = is_array($parsed['summary'] ?? null) ? $parsed['summary'] : [];
        $summary['template'] = sanitize_key((string)($parsed['template'] ?? 'generic'));
        $summary['retailers'] = $parsed['retailers'] ?? [];

        // O Excel semanal pode repetir blocos exatamente iguais em folhas de
        // apoio, impressão ou consolidação. Guardamos uma única ocorrência de
        // cada conteúdo canónico para evitar duplicações no BO e no front sem
        // apagar informação historicamente importada noutras versões.
        $rawSections = (array)($parsed['sections'] ?? []);
        $rawStoreItems = (array)($parsed['store_items'] ?? []);
        $parsedSections = self::dedupeImportedSections($rawSections);
        $parsedStoreItems = self::dedupeImportedStoreItems($rawStoreItems);
        $summary['deduplication'] = [
            'sections_received' => count($rawSections),
            'sections_saved' => count($parsedSections),
            'sections_removed' => max(0, count($rawSections) - count($parsedSections)),
            'store_items_received' => count($rawStoreItems),
            'store_items_saved' => count($parsedStoreItems),
            'store_items_removed' => max(0, count($rawStoreItems) - count($parsedStoreItems)),
        ];

        $wpdb->query('START TRANSACTION');
        try {
            $ok = $wpdb->insert($briefings, [
                'client_id' => $clientId,
                'project_id' => $projectId,
                'title' => $title,
                'year_no' => $year,
                'week_no' => $week,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'status' => 'draft',
                'version_no' => $version,
                'source_filename' => $sourceName,
                'source_hash' => $sourceHash,
                'import_summary_json' => wp_json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ]);
            if (!$ok) throw new \RuntimeException('Não foi possível criar o briefing: ' . $wpdb->last_error);
            $briefingId = (int)$wpdb->insert_id;

            foreach ($parsedSections as $section) {
                $content = is_array($section['content'] ?? null) ? $section['content'] : [];
                $wpdb->insert($sectionsTable, [
                    'briefing_id' => $briefingId,
                    'retailer_key' => sanitize_key((string)($section['retailer_key'] ?? 'all')) ?: 'all',
                    'section_type' => sanitize_key((string)($section['section_type'] ?? 'information')) ?: 'information',
                    'title' => sanitize_text_field((string)($section['title'] ?? 'Informação')),
                    'content_json' => wp_json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'sort_order' => (int)($section['sort_order'] ?? 50),
                    'created_at' => current_time('mysql'),
                ]);
            }

            $aliasIds = [];
            foreach ($parsedStoreItems as $item) {
                $external = sanitize_text_field((string)($item['external_store_name'] ?? ''));
                if ($external === '') continue;
                $retailer = sanitize_key((string)($item['retailer_key'] ?? 'all')) ?: 'all';
                $normalized = self::normalizeStoreName($external);
                if ($normalized === '') continue;
                $aliasKey = $retailer . '|' . $normalized;
                if (!isset($aliasIds[$aliasKey])) {
                    $alias = $wpdb->get_row($wpdb->prepare(
                        "SELECT id,location_id FROM {$aliasesTable} WHERE client_id=%d AND project_id=%d AND retailer_key=%s AND normalized_name=%s LIMIT 1",
                        $clientId, $projectId, $retailer, $normalized
                    ), ARRAY_A);
                    if (!$alias) {
                        $wpdb->insert($aliasesTable, [
                            'client_id' => $clientId,
                            'project_id' => $projectId,
                            'retailer_key' => $retailer,
                            'external_name' => $external,
                            'normalized_name' => $normalized,
                            'location_id' => null,
                            'created_by' => get_current_user_id(),
                            'updated_by' => get_current_user_id(),
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql'),
                        ]);
                        $alias = ['id' => (int)$wpdb->insert_id, 'location_id' => 0];
                        if (empty($alias['id'])) {
                            $alias = $wpdb->get_row($wpdb->prepare(
                                "SELECT id,location_id FROM {$aliasesTable} WHERE client_id=%d AND project_id=%d AND retailer_key=%s AND normalized_name=%s LIMIT 1",
                                $clientId, $projectId, $retailer, $normalized
                            ), ARRAY_A);
                        }
                    }
                    $aliasIds[$aliasKey] = $alias ?: ['id' => 0, 'location_id' => 0];
                }
                $alias = $aliasIds[$aliasKey];
                $details = is_array($item['details'] ?? null) ? $item['details'] : [];
                $wpdb->insert($itemsTable, [
                    'briefing_id' => $briefingId,
                    'retailer_key' => $retailer,
                    'external_store_name' => $external,
                    'normalized_store_name' => $normalized,
                    'alias_id' => absint($alias['id'] ?? 0) ?: null,
                    'location_id' => absint($alias['location_id'] ?? 0) ?: null,
                    'item_type' => sanitize_key((string)($item['item_type'] ?? 'information')) ?: 'information',
                    'title' => sanitize_text_field((string)($item['title'] ?? 'Informação de loja')),
                    'details_json' => wp_json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'valid_from' => self::validDate((string)($item['valid_from'] ?? '')) ?: null,
                    'valid_to' => self::validDate((string)($item['valid_to'] ?? '')) ?: null,
                    'sort_order' => (int)($item['sort_order'] ?? 50),
                    'created_at' => current_time('mysql'),
                ]);
            }

            self::refreshImportSummary($briefingId);
            $wpdb->query('COMMIT');
            do_action('fieldflow_briefing_imported', $briefingId, ['client_id'=>(int)$clientId,'project_id'=>(int)$projectId]);
            return $briefingId;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public static function refreshImportSummary(int $briefingId): void {
        global $wpdb;
        $briefing = self::get($briefingId);
        if (!$briefing) return;
        $summary = self::decodeJson($briefing['import_summary_json'] ?? '');
        $itemsTable = self::table('briefing_store_items');
        $summary['store_aliases_total'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT CONCAT(retailer_key,'|',normalized_store_name)) FROM {$itemsTable} WHERE briefing_id=%d",
            $briefingId
        ));
        $summary['store_aliases_mapped'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT CONCAT(retailer_key,'|',normalized_store_name)) FROM {$itemsTable} WHERE briefing_id=%d AND location_id IS NOT NULL AND location_id>0",
            $briefingId
        ));
        $summary['store_aliases_unmapped'] = max(0, $summary['store_aliases_total'] - $summary['store_aliases_mapped']);
        $wpdb->update(self::table('briefings'), [
            'import_summary_json' => wp_json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => current_time('mysql'),
        ], ['id' => $briefingId]);
    }

    public static function get(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('briefings') . ' WHERE id=%d', $id), ARRAY_A);
        return $row ?: null;
    }

    public static function listAdmin(array $filters = []): array {
        global $wpdb;
        $table = self::table('briefings');
        $where = ['1=1'];
        $args = [];
        if (!empty($filters['client_id'])) { $where[] = 'client_id=%d'; $args[] = absint($filters['client_id']); }
        if (!empty($filters['project_id'])) { $where[] = 'project_id=%d'; $args[] = absint($filters['project_id']); }
        if (!empty($filters['status'])) { $where[] = 'status=%s'; $args[] = sanitize_key($filters['status']); }
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY year_no DESC,week_no DESC,version_no DESC,id DESC LIMIT 100';
        return $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
    }

    public static function adminDetail(int $briefingId): array {
        global $wpdb;
        $briefing = self::get($briefingId);
        if (!$briefing) return [];
        $sections = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table('briefing_sections') . ' WHERE briefing_id=%d ORDER BY sort_order ASC,id ASC,retailer_key ASC',
            $briefingId
        ), ARRAY_A) ?: [];
        $sections = self::dedupePersistedSections($sections);
        foreach ($sections as &$section) $section['content'] = self::decodeJson($section['content_json'] ?? '');
        unset($section);
        $items = $wpdb->get_results($wpdb->prepare(
            'SELECT si.*,l.name AS location_name FROM ' . self::table('briefing_store_items') . ' si LEFT JOIN ' . self::table('locations') . ' l ON l.id=si.location_id WHERE si.briefing_id=%d ORDER BY si.retailer_key ASC,si.external_store_name ASC,si.sort_order ASC,si.id ASC',
            $briefingId
        ), ARRAY_A) ?: [];
        $items = self::dedupePersistedStoreItems($items);
        foreach ($items as &$item) $item['details'] = self::decodeJson($item['details_json'] ?? '');
        unset($item);
        $aliases = self::aliasesForBriefing($briefingId);
        $recipients = self::recipientRows($briefingId, (int)$briefing['version_no']);
        $executionQuestions = class_exists('\RoutesPro\Forms\ContextQuestions') ? \RoutesPro\Forms\ContextQuestions::for_briefing($briefingId, [], true) : [];
        $executionStats = class_exists('\RoutesPro\Forms\ContextQuestions') ? \RoutesPro\Forms\ContextQuestions::execution_stats($briefingId) : [];
        $executionStates = class_exists('\RoutesPro\Forms\ContextQuestions') ? \RoutesPro\Forms\ContextQuestions::execution_states($briefingId) : [];
        return compact('briefing', 'sections', 'items', 'aliases', 'recipients', 'executionQuestions', 'executionStats', 'executionStates');
    }

    public static function aliasesForBriefing(int $briefingId): array {
        global $wpdb;
        $briefing = self::get($briefingId);
        if (!$briefing) return [];
        $aliases = self::table('briefing_store_aliases');
        $items = self::table('briefing_store_items');
        $locations = self::table('locations');
        $aliasesWithScope = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*,l.name AS location_name,l.tags AS location_tags,l.meta_json AS location_meta_json,COUNT(si.id) AS item_count
             FROM {$aliases} a
             INNER JOIN {$items} si ON si.alias_id=a.id AND si.briefing_id=%d
             LEFT JOIN {$locations} l ON l.id=a.location_id
             WHERE a.client_id=%d AND a.project_id=%d
             GROUP BY a.id
             ORDER BY CASE WHEN a.location_id IS NULL OR a.location_id=0 THEN 0 ELSE 1 END ASC,a.retailer_key ASC,a.external_name ASC",
            $briefingId, (int)$briefing['client_id'], (int)$briefing['project_id']
        ), ARRAY_A) ?: [];
        foreach ($aliasesWithScope as &$alias) {
            $alias['identity_review_required'] = !empty($alias['location_id']) && !StoreIdentityAuthority::sourceMatchesLocation(
                (string)($alias['retailer_key'] ?? ''), (string)($alias['external_name'] ?? ''),
                ['name'=>(string)($alias['location_name'] ?? ''), 'tags'=>(string)($alias['location_tags'] ?? ''),
                 'meta_json'=>(string)($alias['location_meta_json'] ?? '')], true
            );
        }
        unset($alias);
        return $aliasesWithScope;
    }

    public static function inferRetailerKey(string $name, string $tags = '', string $metaJson = ''): string {
        $value = self::normalizeStoreName(trim($name . ' ' . $tags . ' ' . $metaJson));
        if ($value === '') return '';
        if (preg_match('/(^|\s)(pingo doce|pd)(\s|$)/', $value)) return 'pingo_doce';
        if (strpos($value, 'auchan') !== false || strpos($value, 'jumbo') !== false) return 'auchan';
        if (strpos($value, 'intermarche') !== false || preg_match('/(^|\s)itmi?(\s|$)/', $value)) return 'intermarche';
        if (strpos($value, 'el corte ingles') !== false || preg_match('/(^|\s)eci(\s|$)/', $value)) return 'eci';
        if (
            strpos($value, 'continente') !== false || strpos($value, 'bom dia') !== false ||
            strpos($value, 'modelo') !== false || preg_match('/(^|\s)(cnt|cbd|mdl)(\s|$)/', $value)
        ) return 'sonae';
        return '';
    }

    public static function locationsForScope(int $clientId, int $projectId): array {
        global $wpdb;
        $locations = self::table('locations');
        $campaign = self::table('campaign_locations');
        $where = ['l.is_active=1'];
        $args = [];

        if ($clientId && $projectId) {
            // Inclui lojas diretamente da campanha e todas as lojas genéricas do mesmo cliente.
            // Muitas bases mantêm os PDVs no cliente e fazem a associação à campanha numa tabela própria.
            $where[] = '((l.client_id=%d AND (l.project_id IS NULL OR l.project_id=0 OR l.project_id=%d)) OR l.project_id=%d OR EXISTS(SELECT 1 FROM ' . $campaign . ' cl WHERE cl.location_id=l.id AND cl.project_id=%d))';
            array_push($args, $clientId, $projectId, $projectId, $projectId);
        } elseif ($clientId) {
            $where[] = 'l.client_id=%d';
            $args[] = $clientId;
        } elseif ($projectId) {
            $where[] = '(l.project_id=%d OR EXISTS(SELECT 1 FROM ' . $campaign . ' cl WHERE cl.location_id=l.id AND cl.project_id=%d))';
            array_push($args, $projectId, $projectId);
        }

        $sql = "SELECT DISTINCT l.id,l.client_id,l.project_id,l.name,l.city,l.county,l.address,l.external_ref,l.tags,l.meta_json FROM {$locations} l WHERE " . implode(' AND ', $where) . ' ORDER BY l.name ASC LIMIT 10000';
        $rows = $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
        $labels = self::retailerLabels();
        foreach ($rows as &$row) {
            $row['retailer_key'] = self::inferRetailerKey((string)($row['name'] ?? ''), (string)($row['tags'] ?? ''), (string)($row['meta_json'] ?? ''));
            $row['retailer_label'] = $labels[$row['retailer_key']] ?? 'Outras';
        }
        unset($row);
        return $rows;
    }

    public static function questionScopeLabels(): array {
        return [
            'section' => 'Lojas mapeadas desta insígnia/secção',
            'mapped' => 'Todas as lojas mapeadas no briefing',
            'selected' => 'Lojas mapeadas selecionadas',
            'retailer_active' => 'Todas as lojas ativas desta insígnia',
            'project_active' => 'Todas as lojas ativas do projeto/campanha',
            'selected_active' => 'Lojas ativas selecionadas do projeto',
        ];
    }

    public static function questionScopeLabel(string $scope): string {
        $scope = sanitize_key($scope) ?: 'section';
        $labels = self::questionScopeLabels();
        return $labels[$scope] ?? $labels['section'];
    }

    public static function questionScopeAllowsUnmapped(string $scope): bool {
        $scope = sanitize_key($scope);
        return in_array($scope, ['retailer_active','project_active','selected_active'], true);
    }

    public static function locationsForQuestionScope(int $clientId, int $projectId): array {
        global $wpdb;
        $locations = self::locationsForScope($clientId, $projectId);
        if (!$projectId || !$locations) return $locations;
        $campaignTable = self::table('campaign_locations');
        $campaignRows = (int)$wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . $campaignTable . ' WHERE project_id=%d',
            $projectId
        ));
        $campaignIds = array_fill_keys(array_map('intval', $wpdb->get_col($wpdb->prepare(
            'SELECT location_id FROM ' . $campaignTable . ' WHERE project_id=%d AND location_id>0 AND is_active=1',
            $projectId
        )) ?: []), true);
        if ($campaignRows > 0) {
            // Quando a campanha já tem uma carteira explícita, essa tabela é a fonte de verdade.
            // Ligações pausadas ou inativas não podem reaparecer através do âmbito geral do cliente.
            return array_values(array_filter($locations, static function(array $location) use ($campaignIds): bool {
                return !empty($campaignIds[(int)($location['id'] ?? 0)]);
            }));
        }
        $direct = array_values(array_filter($locations, static function(array $location) use ($projectId): bool {
            return (int)($location['project_id'] ?? 0) === $projectId;
        }));
        // Compatibilidade com instalações antigas: usa as lojas ligadas diretamente ao projeto
        // e só recorre ao cliente quando ainda não existe qualquer associação de campanha/projeto.
        return $direct ?: $locations;
    }

    public static function applySafeMappings(int $briefingId): int {
        global $wpdb;
        $briefing = self::get($briefingId);
        if (!$briefing) throw new \RuntimeException('Briefing não encontrado.');

        $aliases = self::aliasesForBriefing($briefingId);
        $locations = self::locationsForQuestionScope((int)$briefing['client_id'], (int)$briefing['project_id']);
        if (!$aliases || !$locations) return 0;

        $byRetailer = [];
        foreach ($locations as $location) {
            $retailer = sanitize_key((string)($location['retailer_key'] ?? ''));
            $byRetailer[$retailer][] = $location;
        }

        $mapping = [];
        foreach ($aliases as $alias) {
            if (!empty($alias['location_id'])) continue;
            $retailer = sanitize_key((string)($alias['retailer_key'] ?? ''));
            $candidates = array_values(array_filter($byRetailer[$retailer] ?? [], static function (array $location) use ($retailer, $alias): bool {
                return StoreIdentityAuthority::sourceMatchesLocation($retailer, (string)($alias['external_name'] ?? ''), $location, true);
            }));
            if (!$candidates) continue;

            $aliasName = self::normalizeStoreName((string)($alias['external_name'] ?? ''));
            $aliasCore = self::mappingCoreName($aliasName, $retailer);
            if ($aliasName === '' || $aliasCore === '') continue;

            $exact = [];
            $canonical = [];
            foreach ($candidates as $location) {
                $locationName = self::normalizeStoreName((string)($location['name'] ?? ''));
                $locationCore = self::mappingCoreName($locationName, $retailer);
                if ($locationName === $aliasName) $exact[(int)$location['id']] = true;
                if ($locationCore !== '' && $locationCore === $aliasCore) $canonical[(int)$location['id']] = true;
            }
            $matches = $exact ?: $canonical;

            if (!$matches && strlen($aliasCore) >= 5) {
                foreach ($candidates as $location) {
                    $locationCore = self::mappingCoreName(self::normalizeStoreName((string)($location['name'] ?? '')), $retailer);
                    if ($locationCore === '' || strlen($locationCore) < 5) continue;
                    $short = strlen($aliasCore) <= strlen($locationCore) ? $aliasCore : $locationCore;
                    $long = $short === $aliasCore ? $locationCore : $aliasCore;
                    if (preg_match('/(^|\s)' . preg_quote($short, '/') . '(\s|$)/', $long)) $matches[(int)$location['id']] = true;
                }
            }

            $ids = array_keys($matches);
            if (count($ids) === 1) {
                $mapping[(int)$alias['id']] = (int)$ids[0];
                continue;
            }

            // Secondary deterministic pass through the shared mapping engine.
            // It is intentionally strict: only a very-high-confidence winner with
            // a clear gap to the runner-up can be applied automatically.
            $ranked = MappingEngine::storeCandidates([
                'source_label' => (string)($alias['external_name'] ?? ''),
                'external_ref' => '',
                'meta_json' => ['retailer_key'=>$retailer, 'insignia'=>$retailer],
            ], $candidates, 3);
            $top = $ranked[0] ?? null;
            $runner = $ranked[1] ?? null;
            $topConfidence = (int)($top['confidence'] ?? 0);
            $runnerConfidence = (int)($runner['confidence'] ?? 0);
            if ($top && $topConfidence >= 97 && ($runner === null || ($topConfidence - $runnerConfidence) >= 12)) {
                $mapping[(int)$alias['id']] = (int)($top['target_id'] ?? 0);
            }
        }

        return $mapping ? self::saveMappings($briefingId, $mapping) : 0;
    }

    private static function mappingCoreName(string $normalizedName, string $retailerKey = ''): string {
        $name = ' ' . self::normalizeStoreName($normalizedName) . ' ';
        $tokens = [
            'sonae' => ['continente bom dia', 'continente modelo', 'continente', 'modelo', 'bom dia', 'cbd', 'cnt', 'mdl'],
            'pingo_doce' => ['pingo doce', 'pd'],
            'auchan' => ['auchan', 'jumbo'],
            'intermarche' => ['intermarche', 'itm', 'itmi'],
            'eci' => ['el corte ingles', 'eci'],
        ];
        foreach (($tokens[$retailerKey] ?? []) as $token) {
            $name = preg_replace('/\s+' . preg_quote($token, '/') . '\s+/', ' ', $name);
        }
        $name = trim(preg_replace('/\s+/', ' ', $name));
        return $name;
    }

    public static function saveMappings(int $briefingId, array $mapping): int {
        global $wpdb;
        $briefing = self::get($briefingId);
        if (!$briefing) throw new \RuntimeException('Briefing não encontrado.');
        $aliasesTable = self::table('briefing_store_aliases');
        $itemsTable = self::table('briefing_store_items');
        $allowedLocationIds = [];
        foreach (self::locationsForQuestionScope((int)$briefing['client_id'], (int)$briefing['project_id']) as $location) {
            $allowedLocationIds[(int)$location['id']] = $location;
        }
        $changed = 0;
        foreach ($mapping as $aliasIdRaw => $locationIdRaw) {
            $aliasId = absint($aliasIdRaw);
            $locationId = absint($locationIdRaw);
            if (!$aliasId) continue;
            if ($locationId && empty($allowedLocationIds[$locationId])) {
                throw new \RuntimeException('Uma das lojas selecionadas não pertence ao âmbito deste briefing.');
            }
            $alias = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$aliasesTable} WHERE id=%d AND client_id=%d AND project_id=%d",
                $aliasId, (int)$briefing['client_id'], (int)$briefing['project_id']
            ), ARRAY_A);
            if (!$alias) continue;
            // Legacy invalid links stay visible for review. An unchanged link is
            // not rewritten merely because the manager saved other aliases.
            if ($locationId && $locationId === (int)($alias['location_id'] ?? 0)) continue;
            if ($locationId && !StoreIdentityAuthority::sourceMatchesLocation(
                (string)($alias['retailer_key'] ?? ''),
                (string)($alias['external_name'] ?? ''),
                (array)$allowedLocationIds[$locationId],
                true
            )) throw new \RuntimeException('A loja não tem identidade comercial compatível com a insígnia do briefing. Corrige o PDV ou mantém por associar.');
            $wpdb->update($aliasesTable, [
                'location_id' => $locationId ?: null,
                'updated_by' => get_current_user_id(),
                'updated_at' => current_time('mysql'),
            ], ['id' => $aliasId]);
            $wpdb->update($itemsTable, [
                'location_id' => $locationId ?: null,
            ], ['alias_id' => $aliasId]);
            if (class_exists('\RoutesPro\Mapping\MappingService')) {
                \RoutesPro\Mapping\MappingService::captureStoreMapping(
                    'briefing',
                    $aliasId,
                    (int)$briefing['client_id'],
                    (int)$briefing['project_id'],
                    (string)($alias['external_name'] ?? ''),
                    (string)($alias['retailer_key'] ?? ''),
                    $locationId,
                    ['source_scope_id'=>$briefingId,'retailer_key'=>(string)($alias['retailer_key'] ?? '')]
                );
            }
            $changed++;
        }
        self::refreshImportSummary($briefingId);
        $published = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT b.id FROM " . self::table('briefings') . " b INNER JOIN {$itemsTable} si ON si.briefing_id=b.id INNER JOIN {$aliasesTable} a ON a.id=si.alias_id WHERE a.client_id=%d AND a.project_id=%d AND b.status='published'",
            (int)$briefing['client_id'], (int)$briefing['project_id']
        )) ?: [];
        foreach ($published as $id) self::generateRecipients((int)$id);
        if ($changed > 0 && class_exists('\RoutesPro\Mobile\OperationalContentRevision')) {
            \RoutesPro\Mobile\OperationalContentRevision::bump((int)$briefing['project_id'], 'briefing_mapping');
        }
        return $changed;
    }

    public static function publish(int $briefingId): void {
        global $wpdb;
        $briefing = self::get($briefingId);
        if (!$briefing) throw new \RuntimeException('Briefing não encontrado.');
        $table = self::table('briefings');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status='archived',updated_at=%s WHERE client_id=%d AND project_id=%d AND year_no=%d AND week_no=%d AND status='published' AND id<>%d",
            current_time('mysql'), (int)$briefing['client_id'], (int)$briefing['project_id'], (int)$briefing['year_no'], (int)$briefing['week_no'], $briefingId
        ));
        $wpdb->update($table, [
            'status' => 'published',
            'published_by' => get_current_user_id(),
            'published_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['id' => $briefingId]);
        self::generateRecipients($briefingId);
        if (class_exists('\RoutesPro\Mobile\OperationalContentRevision')) {
            \RoutesPro\Mobile\OperationalContentRevision::bump((int)$briefing['project_id'], 'briefing_publish');
        }
        do_action('fieldflow_briefing_published', $briefingId, [
            'briefing_id' => $briefingId,
            'client_id' => (int)$briefing['client_id'],
            'project_id' => (int)$briefing['project_id'],
            'year_no' => (int)$briefing['year_no'],
            'week_no' => (int)$briefing['week_no'],
            'published_by' => get_current_user_id(),
            'published_at' => current_time('mysql'),
        ]);
    }

    public static function archive(int $briefingId): void {
        global $wpdb;
        $briefing = self::get($briefingId);
        $wpdb->update(self::table('briefings'), ['status' => 'archived', 'updated_at' => current_time('mysql')], ['id' => $briefingId]);
        if ($briefing && class_exists('\RoutesPro\Mobile\OperationalContentRevision')) {
            \RoutesPro\Mobile\OperationalContentRevision::bump((int)$briefing['project_id'], 'briefing_archive');
        }
    }

    public static function deleteBriefing(int $briefingId): void {
        global $wpdb;
        $briefing = self::get($briefingId);
        if (!$briefing) throw new \RuntimeException('Briefing não encontrado.');
        $wpdb->query('START TRANSACTION');
        try {
            foreach (['briefing_sections', 'briefing_store_items', 'briefing_recipients'] as $name) {
                $wpdb->delete(self::table($name), ['briefing_id' => $briefingId]);
            }
            if (class_exists('\RoutesPro\Forms\ContextQuestions')) {
                $contextTable = \RoutesPro\Forms\ContextQuestions::table();
                $hasBriefingColumn = $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $contextTable . ' LIKE %s', 'briefing_id'));
                if ($hasBriefingColumn) $wpdb->delete($contextTable, ['briefing_id' => $briefingId]);
            }
            $wpdb->delete(self::table('briefings'), ['id' => $briefingId]);
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
        if (class_exists('\RoutesPro\Mobile\OperationalContentRevision')) {
            \RoutesPro\Mobile\OperationalContentRevision::bump((int)$briefing['project_id'], 'briefing_delete');
        }
    }

    public static function deleteDraft(int $briefingId): void {
        self::deleteBriefing($briefingId);
    }

    private static function clientAssociatedUserIds(int $clientId): array {
        global $wpdb;
        if ($clientId <= 0) return [];
        $metaJson = (string)$wpdb->get_var($wpdb->prepare(
            'SELECT meta_json FROM ' . self::table('clients') . ' WHERE id=%d LIMIT 1',
            $clientId
        ));
        $meta = json_decode($metaJson, true);
        if (!is_array($meta)) $meta = [];
        $candidates = [
            $meta['associated_user_ids'] ?? null,
            $meta['user_ids'] ?? null,
            $meta['users'] ?? null,
            $meta['assigned_users'] ?? null,
        ];
        $ids = [];
        foreach ($candidates as $candidate) {
            if (is_string($candidate)) $candidate = preg_split('/\s*,\s*/', trim($candidate));
            if (!is_array($candidate)) continue;
            foreach ($candidate as $value) {
                if (is_array($value)) $value = $value['user_id'] ?? $value['id'] ?? 0;
                $uid = absint($value);
                if ($uid) $ids[$uid] = $uid;
            }
        }
        ksort($ids);
        return array_values($ids);
    }

    private static function projectAssociatedUserIds(int $projectId): array {
        global $wpdb;
        if ($projectId <= 0) return [];
        $metaJson = (string)$wpdb->get_var($wpdb->prepare(
            'SELECT meta_json FROM ' . self::table('projects') . ' WHERE id=%d LIMIT 1',
            $projectId
        ));
        $meta = json_decode($metaJson, true);
        if (!is_array($meta)) $meta = [];
        $candidates = [
            $meta['associated_user_ids'] ?? null,
            $meta['user_ids'] ?? null,
            $meta['users'] ?? null,
            $meta['assigned_users'] ?? null,
        ];
        $ids = [];
        foreach ($candidates as $candidate) {
            if (is_string($candidate)) $candidate = preg_split('/\s*,\s*/', trim($candidate));
            if (!is_array($candidate)) continue;
            foreach ($candidate as $value) {
                if (is_array($value)) $value = $value['user_id'] ?? $value['id'] ?? 0;
                $uid = absint($value);
                if ($uid) $ids[$uid] = $uid;
            }
        }
        ksort($ids);
        return array_values($ids);
    }

    public static function generateRecipients(int $briefingId): int {
        global $wpdb;
        $briefing = self::get($briefingId);
        if (!$briefing) return 0;
        $projectId = (int)$briefing['project_id'];
        $version = (int)$briefing['version_no'];
        $from = (string)$briefing['valid_from'];
        $to = (string)$briefing['valid_to'];
        $itemsTable = self::table('briefing_store_items');
        $recipientsTable = self::table('briefing_recipients');
        $locationsTable = self::table('locations');

        // O mapeamento melhora a precisão das instruções por loja, mas nunca condiciona
        // quem recebe o briefing. O universo operacional nasce do projeto e das rotas.
        $locationRetailer = [];
        $mappedRows = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT si.location_id,si.retailer_key,si.external_store_name,l.name,l.tags,l.meta_json
             FROM {$itemsTable} si
             LEFT JOIN {$locationsTable} l ON l.id=si.location_id
             WHERE si.briefing_id=%d AND si.location_id IS NOT NULL AND si.location_id>0",
            $briefingId
        ), ARRAY_A) ?: [];
        foreach ($mappedRows as $row) {
            $lid = (int)$row['location_id'];
            if (!$lid) continue;
            $key = sanitize_key((string)($row['retailer_key'] ?? ''));
            if (!$key || $key === 'all') $key = self::inferRetailerKey((string)($row['name'] ?? ''), (string)($row['tags'] ?? ''), (string)($row['meta_json'] ?? ''));
            if (!$key || !StoreIdentityAuthority::sourceMatchesLocation($key, (string)($row['external_store_name'] ?? ''), $row, true)) continue;
            $locationRetailer[$lid] = $key;
        }

        $allRetailers = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT retailer_key FROM " . self::table('briefing_sections') . " WHERE briefing_id=%d AND retailer_key<>'all'
             UNION SELECT DISTINCT retailer_key FROM {$itemsTable} WHERE briefing_id=%d AND retailer_key<>'all'",
            $briefingId, $briefingId
        )) ?: [];
        $allRetailers = array_values(array_unique(array_filter(array_map('sanitize_key', $allRetailers))));

        $users = [];
        $operationalSignals = [];
        $explicitProjectUsers = [];
        $clientUsers = array_fill_keys(self::clientAssociatedUserIds((int)$briefing['client_id']), true);
        foreach (self::projectAssociatedUserIds($projectId) as $uid) {
            $users[$uid] = $uid;
            $explicitProjectUsers[$uid] = true;
        }
        $assigned = $wpdb->get_col($wpdb->prepare(
            'SELECT user_id FROM ' . self::table('project_assignments') . ' WHERE project_id=%d AND is_active=1',
            $projectId
        )) ?: [];
        foreach ($assigned as $uid) {
            $uid = (int)$uid;
            if (!$uid) continue;
            $users[$uid] = $uid;
            $operationalSignals[$uid] = true;
        }

        $userLocations = [];
        $registerLocation = static function (array $row) use (&$users, &$operationalSignals, &$userLocations, &$locationRetailer): void {
            $uid = (int)($row['user_id'] ?? 0);
            $lid = (int)($row['location_id'] ?? 0);
            if (!$uid) return;
            $users[$uid] = $uid;
            $operationalSignals[$uid] = true;
            if (!$lid) return;
            $userLocations[$uid][$lid] = $lid;
            if (empty($locationRetailer[$lid])) {
                $key = BriefingService::inferRetailerKey((string)($row['name'] ?? ''), (string)($row['tags'] ?? ''), (string)($row['meta_json'] ?? ''));
                if ($key) $locationRetailer[$lid] = $key;
            }
        };

        // Carteira de lojas da campanha. Não depende de as lojas já terem alias no Excel.
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT cl.assigned_to AS user_id,cl.location_id,l.name,l.tags,l.meta_json
             FROM ' . self::table('campaign_locations') . ' cl
             LEFT JOIN ' . $locationsTable . ' l ON l.id=cl.location_id
             WHERE cl.project_id=%d AND cl.is_active=1 AND cl.assigned_to IS NOT NULL AND cl.assigned_to>0',
            $projectId
        ), ARRAY_A) ?: [];
        foreach ($rows as $row) $registerLocation($row);

        // Rotas do período, incluindo owner e elementos atribuídos à rota.
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT r.owner_user_id AS user_id,rs.location_id,l.name,l.tags,l.meta_json
             FROM ' . self::table('routes') . ' r
             INNER JOIN ' . self::table('route_stops') . ' rs ON rs.route_id=r.id
             LEFT JOIN ' . $locationsTable . ' l ON l.id=rs.location_id
             WHERE r.project_id=%d AND r.date BETWEEN %s AND %s AND r.owner_user_id IS NOT NULL AND r.owner_user_id>0',
            $projectId, $from, $to
        ), ARRAY_A) ?: [];
        foreach ($rows as $row) $registerLocation($row);

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT a.user_id,rs.location_id,l.name,l.tags,l.meta_json
             FROM ' . self::table('assignments') . ' a
             INNER JOIN ' . self::table('routes') . ' r ON r.id=a.route_id
             INNER JOIN ' . self::table('route_stops') . ' rs ON rs.route_id=r.id
             LEFT JOIN ' . $locationsTable . ' l ON l.id=rs.location_id
             WHERE a.is_active=1 AND r.project_id=%d AND r.date BETWEEN %s AND %s',
            $projectId, $from, $to
        ), ARRAY_A) ?: [];
        foreach ($rows as $row) $registerLocation($row);

        $existing = [];
        foreach (self::recipientRows($briefingId, $version) as $row) $existing[(int)$row['user_id']] = $row;
        $wpdb->delete($recipientsTable, ['briefing_id' => $briefingId, 'version_no' => $version]);
        $inserted = 0;
        foreach ($users as $uid) {
            $uid = (int)$uid;
            if (!$uid || !get_userdata($uid) || user_can($uid, 'routespro_manage')) continue;

            $hasOperationalSignal = !empty($operationalSignals[$uid]);
            $canExecute = user_can($uid, 'routespro_execute');
            $isExplicitProjectUser = !empty($explicitProjectUsers[$uid]);
            $isClientAccount = !empty($clientUsers[$uid]);
            if (!$hasOperationalSignal && !$canExecute && (!$isExplicitProjectUser || $isClientAccount)) continue;

            $locations = array_values($userLocations[$uid] ?? []);
            $retailers = [];
            foreach ($locations as $lid) {
                if (!empty($locationRetailer[$lid])) $retailers[$locationRetailer[$lid]] = $locationRetailer[$lid];
            }
            // Se a insígnia da loja ainda não for identificável, o utilizador recebe o
            // briefing completo. A informação nunca desaparece por falta de mapping.
            if (!$retailers) foreach ($allRetailers as $key) $retailers[$key] = $key;

            $old = $existing[$uid] ?? [];
            $ok = $wpdb->insert($recipientsTable, [
                'briefing_id' => $briefingId,
                'user_id' => $uid,
                'version_no' => $version,
                'location_ids_json' => wp_json_encode($locations),
                'retailer_keys_json' => wp_json_encode(array_values($retailers)),
                'first_viewed_at' => $old['first_viewed_at'] ?? null,
                'last_viewed_at' => $old['last_viewed_at'] ?? null,
                'acknowledged_at' => $old['acknowledged_at'] ?? null,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ]);
            if ($ok !== false) $inserted++;
        }
        return $inserted;
    }

    public static function refreshActiveRecipientsForProject(int $projectId): int {
        global $wpdb;
        if ($projectId <= 0) return 0;
        $today = current_time('Y-m-d');
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM " . self::table('briefings') . " WHERE project_id=%d AND status='published' AND (valid_to IS NULL OR valid_to='0000-00-00' OR valid_to>=%s) ORDER BY id DESC",
            $projectId,
            $today
        )) ?: [];
        $refreshed = 0;
        foreach ($ids as $briefingId) {
            self::generateRecipients((int)$briefingId);
            $refreshed++;
        }
        return $refreshed;
    }

    public static function recipientRows(int $briefingId, int $version): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT r.*,u.display_name,u.user_email FROM ' . self::table('briefing_recipients') . ' r LEFT JOIN ' . $wpdb->users . ' u ON u.ID=r.user_id WHERE r.briefing_id=%d AND r.version_no=%d ORDER BY u.display_name ASC',
            $briefingId, $version
        ), ARRAY_A) ?: [];
    }

    public static function mine(int $userId, int $clientId = 0, int $projectId = 0): array {
        global $wpdb;
        $briefings = self::table('briefings');
        $recipients = self::table('briefing_recipients');
        $scopeWhere = ["b.status='published'"];
        $scopeArgs = [];
        if ($clientId > 0) { $scopeWhere[] = 'b.client_id=%d'; $scopeArgs[] = $clientId; }
        if ($projectId > 0) { $scopeWhere[] = 'b.project_id=%d'; $scopeArgs[] = $projectId; }
        if (Permissions::is_manager($userId)) {
            $sql = "SELECT b.* FROM {$briefings} b WHERE " . implode(' AND ', $scopeWhere) . ' ORDER BY b.year_no DESC,b.week_no DESC,b.version_no DESC LIMIT 20';
            $rows = $scopeArgs ? ($wpdb->get_results($wpdb->prepare($sql, ...$scopeArgs), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
        } else {
            $loadRows = static function () use ($wpdb, $briefings, $recipients, $userId, $scopeWhere, $scopeArgs): array {
                $where = array_merge(['r.user_id=%d'], $scopeWhere);
                $args = array_merge([$userId], $scopeArgs);
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT b.*,r.location_ids_json,r.retailer_keys_json,r.first_viewed_at,r.last_viewed_at,r.acknowledged_at
                     FROM {$briefings} b INNER JOIN {$recipients} r ON r.briefing_id=b.id AND r.version_no=b.version_no
                     WHERE " . implode(' AND ', $where) . '
                     ORDER BY b.year_no DESC,b.week_no DESC,b.version_no DESC LIMIT 20',
                    ...$args
                ), ARRAY_A) ?: [];
            };
            $rows = $loadRows();
            if (!$rows) {
                $scope = Permissions::get_scope($userId);
                $projectIds = array_values(array_filter(array_map('absint', (array)($scope['project_ids'] ?? []))));
                if ($projectId > 0) $projectIds = array_values(array_intersect($projectIds, [$projectId]));
                if ($projectIds) {
                    $ids = $wpdb->get_col($wpdb->prepare(
                        "SELECT id FROM {$briefings} WHERE status='published' AND project_id IN (" . implode(',', array_fill(0, count($projectIds), '%d')) . ') ORDER BY id DESC LIMIT 30',
                        ...$projectIds
                    )) ?: [];
                    foreach ($ids as $briefingId) self::generateRecipients((int)$briefingId);
                    $rows = $loadRows();
                }
            }
        }
        $out = [];
        foreach ($rows as $row) $out[] = self::payload((int)$row['id'], $userId, false, $row);
        return array_values(array_filter($out));
    }

    public static function clientList(int $clientId, int $projectId, int $userId = 0): array {
        global $wpdb;
        $briefings = self::table('briefings');
        $recipients = self::table('briefing_recipients');
        $where = ["b.status='published'"];
        $args = [];
        if ($clientId) { $where[] = 'b.client_id=%d'; $args[] = $clientId; }
        if ($projectId) { $where[] = 'b.project_id=%d'; $args[] = $projectId; }
        $uid = $userId ?: get_current_user_id();
        if (!Permissions::is_manager($uid)) {
            [$scopeSql, $scopeArgs] = Permissions::scope_sql('b', 'b.client_id', 'b.project_id', $uid);
            $where[] = $scopeSql;
            $args = array_merge($args, $scopeArgs);
        }
        $sql = "SELECT b.*,COUNT(r.id) AS recipients_count,SUM(CASE WHEN r.first_viewed_at IS NOT NULL THEN 1 ELSE 0 END) AS viewed_count,SUM(CASE WHEN r.acknowledged_at IS NOT NULL THEN 1 ELSE 0 END) AS acknowledged_count
                FROM {$briefings} b LEFT JOIN {$recipients} r ON r.briefing_id=b.id AND r.version_no=b.version_no
                WHERE " . implode(' AND ', $where) . ' GROUP BY b.id ORDER BY b.year_no DESC,b.week_no DESC,b.version_no DESC LIMIT 24';
        $rows = $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
        $out = [];
        foreach ($rows as $row) {
            $payload = self::payload((int)$row['id'], 0, true, $row);
            $payload['stats'] = [
                'recipients' => (int)$row['recipients_count'],
                'viewed' => (int)$row['viewed_count'],
                'acknowledged' => (int)$row['acknowledged_count'],
                'pending' => max(0, (int)$row['recipients_count'] - (int)$row['acknowledged_count']),
                'view_rate' => (int)$row['recipients_count'] ? round(((int)$row['viewed_count'] / (int)$row['recipients_count']) * 100, 1) : 0,
                'ack_rate' => (int)$row['recipients_count'] ? round(((int)$row['acknowledged_count'] / (int)$row['recipients_count']) * 100, 1) : 0,
            ];
            $payload['evidence'] = array_map(static function (array $recipient): array {
                return [
                    'user_id' => (int)$recipient['user_id'],
                    'display_name' => (string)($recipient['display_name'] ?? 'Utilizador'),
                    'first_viewed_at' => $recipient['first_viewed_at'] ?: null,
                    'last_viewed_at' => $recipient['last_viewed_at'] ?: null,
                    'acknowledged_at' => $recipient['acknowledged_at'] ?: null,
                    'location_count' => count(self::decodeJson($recipient['location_ids_json'] ?? '')),
                ];
            }, self::recipientRows((int)$row['id'], (int)$row['version_no']));
            $out[] = $payload;
        }
        return $out;
    }

    /**
     * 9DK: route membership is the only authority for "Tua loja". A campaign
     * portfolio and spreadsheet M references remain briefing visibility only.
     * The result is read-only and limited to stops in the briefing's ISO window.
     */
    public static function routeLocationIdsForBriefing(int $projectId, int $userId, string $from, string $to): array {
        global $wpdb;
        if ($projectId <= 0 || $userId <= 0 || $from === '' || $to === '') return [];
        $rows = $wpdb->get_col($wpdb->prepare(
            'SELECT DISTINCT rs.location_id FROM ' . self::table('routes') . ' r
             INNER JOIN ' . self::table('route_stops') . ' rs ON rs.route_id=r.id
             LEFT JOIN ' . self::table('assignments') . ' a
               ON a.route_id=r.id AND a.is_active=1 AND a.user_id=%d
             WHERE r.project_id=%d AND r.date BETWEEN %s AND %s
               AND (r.owner_user_id=%d OR a.user_id IS NOT NULL)
               AND rs.location_id IS NOT NULL AND rs.location_id>0',
            $userId, $projectId, $from, $to, $userId
        )) ?: [];
        return array_values(array_unique(array_filter(array_map('absint', $rows))));
    }

    /** Read-only, deterministic briefing-versus-route label contract. */
    public static function storeVisibility9DK(int $locationId, array $routeLookup, array $briefingLookup, bool $retailerRelevant): array {
        if ($locationId > 0 && isset($routeLookup[$locationId])) {
            return ['scope'=>'assigned', 'note'=>'Loja na tua rota nesta semana.'];
        }
        if ($locationId > 0 && isset($briefingLookup[$locationId])) {
            return ['scope'=>'briefing_only', 'note'=>'Só briefing: a loja aparece no briefing, mas não está atribuída à tua rota nesta semana.'];
        }
        if ($locationId === 0 && $retailerRelevant) {
            return ['scope'=>'unmapped_relevant','note'=>'Informação da tua insígnia sem associação a uma loja. Confirma no terreno se se aplica.'];
        }
        if ($retailerRelevant) {
            return ['scope'=>'retailer_context','note'=>'Informação adicional da insígnia, associada a outra loja do briefing.'];
        }
        return ['scope'=>'general_context','note'=>'Contexto geral do briefing, não associado às tuas lojas em rota.'];
    }

    public static function payload(int $briefingId, int $userId = 0, bool $clientMode = false, array $briefingRow = []): array {
        global $wpdb;
        $briefing = $briefingRow ?: (self::get($briefingId) ?: []);
        if (!$briefing) return [];
        $retailerKeys = [];
        $locationIds = [];
        $recipient = [];
        $isManager = !$clientMode && Permissions::is_manager($userId);
        if (!$clientMode && !$isManager) {
            $recipient = $wpdb->get_row($wpdb->prepare(
                'SELECT * FROM ' . self::table('briefing_recipients') . ' WHERE briefing_id=%d AND user_id=%d AND version_no=%d LIMIT 1',
                $briefingId, $userId, (int)$briefing['version_no']
            ), ARRAY_A) ?: [];
            if (!$recipient) return [];
            $retailerKeys = array_values(array_filter(array_map('sanitize_key', self::decodeJson($recipient['retailer_keys_json'] ?? ''))));
            $locationIds = array_values(array_filter(array_map('absint', self::decodeJson($recipient['location_ids_json'] ?? ''))));
        }

        // 9DL: o Cliente/BO recebe o briefing integral. O Operativo recebe apenas
        // insígnias relevantes e instruções mapeadas a lojas efetivas da sua rota.
        $sectionRows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table('briefing_sections') . ' WHERE briefing_id=%d ORDER BY sort_order ASC,retailer_key ASC,id ASC',
            $briefingId
        ), ARRAY_A) ?: [];
        $sectionRows = self::dedupePersistedSections($sectionRows);
        $sections = [];
        foreach ($sectionRows as $section) {
            $key = sanitize_key((string)$section['retailer_key']);
            $relevant = $clientMode || $isManager || $key === 'all' || ($retailerKeys && in_array($key, $retailerKeys, true));
            // 9DL: team briefings show only the person's retailers, not every brand in the workbook.
            if (!$relevant) continue;
            $sections[] = [
                'id' => (int)$section['id'],
                'retailer_key' => $key,
                'retailer_label' => self::retailerLabels()[$key] ?? $key,
                'type' => $section['section_type'],
                'title' => $section['title'],
                'content' => self::decodeJson($section['content_json'] ?? ''),
                'sort_order' => (int)($section['sort_order'] ?? 50),
                'is_relevant' => $relevant,
                'scope' => $relevant ? 'assigned' : 'context',
                'scope_note' => $relevant ? '' : 'Informação adicional do briefing. Não está ligada às insígnias atualmente identificadas nas tuas lojas.',
            ];
        }
        usort($sections, static function (array $a, array $b): int {
            if ($a['is_relevant'] !== $b['is_relevant']) return $a['is_relevant'] ? -1 : 1;
            $order = ((int)($a['sort_order'] ?? 50)) <=> ((int)($b['sort_order'] ?? 50));
            return $order !== 0 ? $order : (((int)$a['id']) <=> ((int)$b['id']));
        });

        $itemRows = $wpdb->get_results($wpdb->prepare(
            'SELECT si.*,l.name AS location_name,l.tags AS location_tags,l.meta_json AS location_meta_json FROM ' . self::table('briefing_store_items') . ' si LEFT JOIN ' . self::table('locations') . ' l ON l.id=si.location_id WHERE si.briefing_id=%d ORDER BY si.sort_order ASC,si.external_store_name ASC,si.id ASC',
            $briefingId
        ), ARRAY_A) ?: [];
        $itemRows = self::dedupePersistedStoreItems($itemRows);
        $briefingLocationLookup = array_fill_keys($locationIds, true);
        $routeLocationLookup = (!$clientMode && !$isManager)
            ? array_fill_keys(self::routeLocationIdsForBriefing(
                (int)($briefing['project_id'] ?? 0), $userId,
                (string)($briefing['valid_from'] ?? ''), (string)($briefing['valid_to'] ?? '')
              ), true)
            : [];
        $items = []; $excludedStoreInstructions = 0;
        foreach ($itemRows as $item) {
            $locationId = (int)($item['location_id'] ?? 0);
            $retailerKey = sanitize_key((string)($item['retailer_key'] ?? 'all')) ?: 'all';
            $identityValid = $locationId > 0 && StoreIdentityAuthority::sourceMatchesLocation(
                $retailerKey,
                (string)($item['external_store_name'] ?? ''),
                ['name'=>(string)($item['location_name'] ?? ''), 'tags'=>(string)($item['location_tags'] ?? ''),
                 'meta_json'=>(string)($item['location_meta_json'] ?? '')],
                true
            );
            if (!$identityValid) $locationId = 0; // 9DN: retain DB mapping for manual review, not operative use.

            $retailerRelevant = $clientMode || $isManager || !$retailerKeys || $retailerKey === 'all' || in_array($retailerKey, $retailerKeys, true);
            $assigned = !$clientMode && !$isManager && $locationId > 0 && isset($routeLocationLookup[$locationId]);
            $mapped = $locationId > 0;

            if ($clientMode || $isManager) {
                $scope = $mapped ? 'mapped' : 'unmapped';
                $scopeNote = $mapped ? '' : 'Esta designação do Excel ainda não está associada a uma loja FieldFlow.';
            } else {
                $visibility = self::storeVisibility9DK($locationId, $routeLocationLookup, $briefingLocationLookup, $retailerRelevant);
                $scope = (string)$visibility['scope'];
                $scopeNote = (string)$visibility['note'];
                // 9DL: store-specific instructions (especially centralized spaces) require an actual route stop.
                // An M marker or briefing recipient mapping is not a route assignment.
                if ($scope !== 'assigned') { $excludedStoreInstructions++; continue; }
            }

            $items[] = [
                'id' => (int)$item['id'],
                'retailer_key' => $retailerKey,
                'retailer_label' => self::retailerLabels()[$retailerKey] ?? $retailerKey,
                'external_store_name' => $item['external_store_name'],
                'location_id' => $locationId,
                'location_name' => $item['location_name'] ?: $item['external_store_name'],
                'mapped' => $mapped,
                'is_assigned' => $assigned,
                'is_relevant' => $assigned || $retailerRelevant,
                'scope' => $scope,
                'scope_note' => $scopeNote,
                'type' => $item['item_type'],
                'title' => $item['title'],
                'details' => self::decodeJson($item['details_json'] ?? ''),
                'valid_from' => $item['valid_from'],
                'valid_to' => $item['valid_to'],
                'sort_order' => (int)($item['sort_order'] ?? 50),
            ];
        }
        $scopeOrder = ['assigned' => 0, 'briefing_only' => 1, 'unmapped_relevant' => 2, 'retailer_context' => 3, 'mapped' => 3, 'unmapped' => 4, 'general_context' => 5];
        usort($items, static function (array $a, array $b) use ($scopeOrder): int {
            $oa = $scopeOrder[$a['scope']] ?? 9;
            $ob = $scopeOrder[$b['scope']] ?? 9;
            if ($oa !== $ob) return $oa <=> $ob;
            $retailer = strcmp((string)$a['retailer_label'], (string)$b['retailer_label']);
            if ($retailer !== 0) return $retailer;
            $order = ((int)($a['sort_order'] ?? 50)) <=> ((int)($b['sort_order'] ?? 50));
            if ($order !== 0) return $order;
            return strcmp((string)$a['location_name'], (string)$b['location_name']);
        });

        $summary = self::decodeJson($briefing['import_summary_json'] ?? '');
        $aiCopilot = [];
        if (class_exists('\RoutesPro\Briefings\BriefingAIService')) {
            $aiCopilot = BriefingAIService::frontPayload(
                is_array($summary['ai_copilot'] ?? null) ? $summary['ai_copilot'] : [],
                $retailerKeys,
                $clientMode
            );
        }
        $teamScopedExecution = !$clientMode && !$isManager;
        $executionLocationIds = $teamScopedExecution ? $locationIds : [];
        if ($teamScopedExecution && !$executionLocationIds) {
            // Um utilizador operacional sem lojas atribuídas não pode herdar o universo
            // completo de perguntas. O array vazio significa aqui, explicitamente, zero âmbito.
            $executionQuestions = [];
            $executionStats = [
                'questions' => 0,
                'assignments' => 0,
                'mapped_stores' => 0,
                'answers' => 0,
                'submissions' => 0,
                'conforming' => 0,
                'non_conforming' => 0,
                'pending' => 0,
            ];
            $executionStates = [];
        } else {
            $executionQuestions = class_exists('\RoutesPro\Forms\ContextQuestions') ? \RoutesPro\Forms\ContextQuestions::for_briefing($briefingId, $executionLocationIds) : [];
            $executionStats = class_exists('\RoutesPro\Forms\ContextQuestions') ? \RoutesPro\Forms\ContextQuestions::execution_stats($briefingId, $executionLocationIds) : [];
            $executionStates = class_exists('\RoutesPro\Forms\ContextQuestions') ? \RoutesPro\Forms\ContextQuestions::execution_states($briefingId, $executionLocationIds) : [];
        }
        $executionGroups = [];
        foreach ($executionQuestions as $q) {
            $key = sanitize_key((string)($q['question_key'] ?? '')) ?: ('question_' . (int)($q['id'] ?? 0));
            if (!isset($executionGroups[$key])) {
                $visibilityRules = self::decodeJson((string)($q['visibility_rules_json'] ?? ''));
                $targetScope = sanitize_key((string)($visibilityRules['target_scope'] ?? 'section')) ?: 'section';
                $executionGroups[$key] = [
                    'id' => (int)($q['id'] ?? 0),
                    'question_key' => $key,
                    'label' => (string)($q['question_label'] ?? ''),
                    'type' => (string)($q['question_type'] ?? ''),
                    'required' => !empty($q['is_required']),
                    'form_id' => (int)($q['form_id'] ?? 0),
                    'form_title' => (string)($q['form_title'] ?? ''),
                    'section_id' => (int)($q['briefing_section_id'] ?? 0),
                    'priority' => (int)($q['priority'] ?? 100),
                    'valid_from' => $q['valid_from'] ?? null,
                    'valid_to' => $q['valid_to'] ?? null,
                    'target_scope' => $targetScope,
                    'target_scope_label' => self::questionScopeLabel($targetScope),
                    'category' => sanitize_text_field((string)($visibilityRules['product_category'] ?? '')),
                    'category_source' => sanitize_key((string)($visibilityRules['category_source'] ?? '')),
                    'location_ids' => [],
                    'location_names' => [],
                ];
            }
            $locationId = (int)($q['location_id'] ?? 0);
            if ($locationId) $executionGroups[$key]['location_ids'][$locationId] = $locationId;
            $locationName = trim((string)($q['location_name'] ?? ''));
            if ($locationName !== '') $executionGroups[$key]['location_names'][$locationId ?: $locationName] = $locationName;
        }
        $executionPayload = array_values(array_map(static function(array $q) use ($executionStates): array {
            $q['location_ids'] = array_values($q['location_ids']);
            $q['location_names'] = array_values($q['location_names']);
            $q['store_count'] = count($q['location_ids']);
            $q['location_name'] = count($q['location_names']) === 1 ? (string)$q['location_names'][0] : '';
            $state = (array)($executionStates[$q['question_key']] ?? []);
            $q['execution_state'] = sanitize_key((string)($state['state'] ?? 'pending')) ?: 'pending';
            $q['execution_counts'] = [
                'assignments' => (int)($state['assignments'] ?? $q['store_count']),
                'answered' => (int)($state['answered'] ?? 0),
                'implemented' => (int)($state['implemented'] ?? 0),
                'not_implemented' => (int)($state['not_implemented'] ?? 0),
                'not_applicable' => (int)($state['not_applicable'] ?? 0),
                'pending' => (int)($state['pending'] ?? $q['store_count']),
            ];
            $q['location_states'] = array_values((array)($state['location_states'] ?? []));
            return $q;
        }, $executionGroups));
        return [
            'id' => (int)$briefing['id'],
            'client_id' => (int)$briefing['client_id'],
            'project_id' => (int)$briefing['project_id'],
            'title' => $briefing['title'],
            'year_no' => (int)$briefing['year_no'],
            'week_no' => (int)$briefing['week_no'],
            'valid_from' => $briefing['valid_from'],
            'valid_to' => $briefing['valid_to'],
            'version_no' => (int)$briefing['version_no'],
            'published_at' => $briefing['published_at'],
            'source_filename' => $briefing['source_filename'],
            'summary' => $summary,
            'ai_copilot' => $aiCopilot,
            'execution' => ['questions' => $executionPayload, 'stats' => $executionStats],
            'sections' => $sections,
            'store_items' => $items,
            'scope' => [
                'retailer_keys' => $retailerKeys,
                'location_ids' => $locationIds,
                'assigned_items' => count(array_filter($items, static fn($i) => !empty($i['is_assigned']))),
                'unmapped_items' => count(array_filter($items, static fn($i) => empty($i['mapped']))),
                'context_items' => count(array_filter($items, static fn($i) => in_array($i['scope'] ?? '', ['retailer_context','general_context'], true))),
                'excluded_store_items' => $excludedStoreInstructions, // 9DL not shown in operative route briefing
            ],
            'can_acknowledge' => !$clientMode && !$isManager && !empty($recipient),
            'read' => [
                'first_viewed_at' => $recipient['first_viewed_at'] ?? ($briefing['first_viewed_at'] ?? null),
                'last_viewed_at' => $recipient['last_viewed_at'] ?? ($briefing['last_viewed_at'] ?? null),
                'acknowledged_at' => $recipient['acknowledged_at'] ?? ($briefing['acknowledged_at'] ?? null),
            ],
        ];
    }

    public static function markViewed(int $briefingId, int $userId): void {
        global $wpdb;
        $briefing = self::get($briefingId);
        if (!$briefing || $briefing['status'] !== 'published') throw new \RuntimeException('Briefing indisponível.');
        $table = self::table('briefing_recipients');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id,first_viewed_at FROM {$table} WHERE briefing_id=%d AND user_id=%d AND version_no=%d LIMIT 1",
            $briefingId, $userId, (int)$briefing['version_no']
        ), ARRAY_A);
        if (!$row && !Permissions::is_manager($userId)) throw new \RuntimeException('Este briefing não está atribuído ao utilizador.');
        if ($row) {
            $data = ['last_viewed_at' => current_time('mysql'), 'updated_at' => current_time('mysql')];
            if (empty($row['first_viewed_at'])) $data['first_viewed_at'] = current_time('mysql');
            $wpdb->update($table, $data, ['id' => (int)$row['id']]);
        }
    }

    public static function acknowledge(int $briefingId, int $userId): void {
        global $wpdb;
        self::markViewed($briefingId, $userId);
        $briefing = self::get($briefingId);
        if (!$briefing || Permissions::is_manager($userId)) return;
        $wpdb->update(self::table('briefing_recipients'), [
            'acknowledged_at' => current_time('mysql'),
            'last_viewed_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], [
            'briefing_id' => $briefingId,
            'user_id' => $userId,
            'version_no' => (int)$briefing['version_no'],
        ]);
    }

    public static function evidenceRows(int $briefingId): array {
        $briefing = self::get($briefingId);
        if (!$briefing) return [];
        return self::recipientRows($briefingId, (int)$briefing['version_no']);
    }

    private static function dedupeImportedSections(array $rows): array {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $fingerprint = self::sectionFingerprint([
                'retailer_key' => $row['retailer_key'] ?? 'all',
                'section_type' => $row['section_type'] ?? 'information',
                'title' => $row['title'] ?? 'Informação',
                'content_json' => wp_json_encode(is_array($row['content'] ?? null) ? $row['content'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            if (isset($seen[$fingerprint])) continue;
            $seen[$fingerprint] = true;
            $out[] = $row;
        }
        return $out;
    }

    private static function dedupeImportedStoreItems(array $rows): array {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $fingerprint = self::storeItemFingerprint([
                'retailer_key' => $row['retailer_key'] ?? 'all',
                'external_store_name' => $row['external_store_name'] ?? '',
                'normalized_store_name' => self::normalizeStoreName((string)($row['external_store_name'] ?? '')),
                'item_type' => $row['item_type'] ?? 'information',
                'title' => $row['title'] ?? 'Informação de loja',
                'details_json' => wp_json_encode(is_array($row['details'] ?? null) ? $row['details'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'valid_from' => $row['valid_from'] ?? '',
                'valid_to' => $row['valid_to'] ?? '',
            ]);
            if (isset($seen[$fingerprint])) continue;
            $seen[$fingerprint] = true;
            $out[] = $row;
        }
        return $out;
    }

    private static function dedupePersistedSections(array $rows): array {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $fingerprint = self::sectionFingerprint($row);
            if (isset($seen[$fingerprint])) continue;
            $seen[$fingerprint] = true;
            $out[] = $row;
        }
        return $out;
    }

    private static function dedupePersistedStoreItems(array $rows): array {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $fingerprint = self::storeItemFingerprint($row);
            if (isset($seen[$fingerprint])) continue;
            $seen[$fingerprint] = true;
            $out[] = $row;
        }
        return $out;
    }

    private static function sectionFingerprint(array $row): string {
        $content = self::decodeJson($row['content_json'] ?? ($row['content'] ?? []));
        return hash('sha256', implode('|', [
            sanitize_key((string)($row['retailer_key'] ?? 'all')) ?: 'all',
            sanitize_key((string)($row['section_type'] ?? 'information')) ?: 'information',
            self::fingerprintText((string)($row['title'] ?? '')),
            self::canonicalJson($content),
        ]));
    }

    private static function storeItemFingerprint(array $row): string {
        $details = self::decodeJson($row['details_json'] ?? ($row['details'] ?? []));
        $normalizedStore = trim((string)($row['normalized_store_name'] ?? ''));
        if ($normalizedStore === '') $normalizedStore = self::normalizeStoreName((string)($row['external_store_name'] ?? ''));
        return hash('sha256', implode('|', [
            sanitize_key((string)($row['retailer_key'] ?? 'all')) ?: 'all',
            $normalizedStore,
            sanitize_key((string)($row['item_type'] ?? 'information')) ?: 'information',
            self::fingerprintText((string)($row['title'] ?? '')),
            self::canonicalJson($details),
            self::validDate((string)($row['valid_from'] ?? '')),
            self::validDate((string)($row['valid_to'] ?? '')),
        ]));
    }

    private static function canonicalJson(array $value): string {
        $normalise = static function ($item) use (&$normalise) {
            if (!is_array($item)) {
                if (is_string($item)) return trim(preg_replace('/\\s+/u', ' ', $item));
                return $item;
            }
            $isList = $item === [] || array_keys($item) === range(0, count($item) - 1);
            if ($isList) return array_map($normalise, $item);
            ksort($item, SORT_STRING);
            foreach ($item as $key => $child) $item[$key] = $normalise($child);
            return $item;
        };
        return (string)wp_json_encode($normalise($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function fingerprintText(string $value): string {
        $value = trim(preg_replace('/\\s+/u', ' ', $value));
        if (function_exists('remove_accents')) $value = remove_accents($value);
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private static function validDate(string $value): string {
        $value = sanitize_text_field($value);
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        return ($dt && $dt->format('Y-m-d') === $value) ? $value : '';
    }

    private static function decodeJson($json): array {
        if (is_array($json)) return $json;
        if (!is_string($json) || trim($json) === '') return [];
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
