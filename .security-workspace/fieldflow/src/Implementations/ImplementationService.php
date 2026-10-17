<?php
namespace RoutesPro\Implementations;

use RoutesPro\Support\Permissions;
use RoutesPro\Stores\StoreIdentityAuthority;

if (!defined('ABSPATH')) exit;

class ImplementationService {
    private static array $locationCache = [];

    public static function table(string $suffix): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_' . $suffix;
    }

    public static function documentStatusLabels(): array {
        return [
            'draft' => 'Rascunho',
            'published' => 'Publicado',
            'archived' => 'Arquivado',
        ];
    }

    public static function itemStatusLabels(): array {
        return [
            'implemented' => 'Implementado',
            'stock_blocked' => 'Bloqueado por stock',
            'order_pending' => 'Pedido em falta',
            'scheduled' => 'Encomenda feita ou agendado',
            'no_feedback' => 'Sem feedback',
            'sent' => 'Enviado',
            'awaiting' => 'A aguardar',
            'pending' => 'Pendente',
            'other' => 'Outro estado',
        ];
    }

    public static function importParsed(array $parsed, array $scope, array $source): int {
        global $wpdb;
        $clientId = absint($scope['client_id'] ?? 0);
        $projectId = absint($scope['project_id'] ?? 0);
        if (!$clientId || !$projectId) throw new \RuntimeException('Seleciona o cliente e a campanha.');

        $projectClient = absint($wpdb->get_var($wpdb->prepare(
            'SELECT client_id FROM ' . self::table('projects') . ' WHERE id=%d LIMIT 1',
            $projectId
        )));
        if (!$projectClient || $projectClient !== $clientId) {
            throw new \RuntimeException('A campanha selecionada não pertence ao cliente indicado.');
        }

        $sections = array_values(array_filter((array)($parsed['sections'] ?? []), static fn($s) => is_array($s) && !empty($s['items'])));
        if (!$sections) throw new \RuntimeException('O documento não contém implementações válidas para importar.');

        $title = sanitize_text_field((string)($scope['title'] ?? ''));
        if ($title === '') $title = sanitize_text_field((string)($parsed['title'] ?? 'Implementações de Materiais'));
        if ($title === '') $title = 'Implementações de Materiais';
        $validFrom = self::dateOrNull((string)($scope['valid_from'] ?? ''));
        $validTo = self::dateOrNull((string)($scope['valid_to'] ?? ''));
        if ($validFrom && $validTo && $validTo < $validFrom) {
            throw new \RuntimeException('A data final não pode ser anterior à data inicial.');
        }

        $documentsTable = self::table('implementation_documents');
        $sectionsTable = self::table('implementation_sections');
        $itemsTable = self::table('implementation_items');
        $aliasesTable = self::table('implementation_store_aliases');
        $version = 1 + (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(version_no),0) FROM {$documentsTable} WHERE client_id=%d AND project_id=%d AND title=%s",
            $clientId,
            $projectId,
            $title
        ));

        $summary = is_array($parsed['summary'] ?? null) ? $parsed['summary'] : [];
        $summary['mapped'] = 0;
        $summary['unmapped'] = 0;
        $summary['source_type'] = 'docx';

        $wpdb->query('START TRANSACTION');
        try {
            $inserted = $wpdb->insert($documentsTable, [
                'client_id' => $clientId,
                'project_id' => $projectId,
                'title' => $title,
                'status' => 'draft',
                'version_no' => $version,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'notes_text' => wp_kses_post((string)($parsed['notes'] ?? '')),
                'source_filename' => sanitize_file_name((string)($source['name'] ?? 'implementacoes.docx')),
                'source_hash' => sanitize_text_field((string)($source['hash'] ?? '')),
                'import_summary_json' => wp_json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ]);
            if ($inserted === false) throw new \RuntimeException('Não foi possível criar o documento de implementações.');
            $documentId = (int)$wpdb->insert_id;

            foreach ($sections as $sectionIndex => $section) {
                $ok = $wpdb->insert($sectionsTable, [
                    'document_id' => $documentId,
                    'section_key' => sanitize_key((string)($section['key'] ?? 'section_' . ($sectionIndex + 1))),
                    'title' => sanitize_text_field((string)($section['title'] ?? 'Implementações')),
                    'summary_text' => sanitize_textarea_field((string)($section['summary'] ?? '')),
                    'metadata_json' => wp_json_encode((array)($section['metadata'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'sort_order' => (int)($section['sort_order'] ?? (($sectionIndex + 1) * 10)),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
                if ($ok === false) throw new \RuntimeException('Não foi possível guardar uma secção do documento.');
                $sectionId = (int)$wpdb->insert_id;

                foreach ((array)($section['items'] ?? []) as $itemIndex => $item) {
                    if (!is_array($item)) continue;
                    $code = self::normaliseCode((string)($item['external_store_code'] ?? ''));
                    $externalName = sanitize_text_field((string)($item['external_store_name'] ?? ''));
                    if ($code === '' && $externalName === '') continue;
                    $normalizedName = self::normalizeStoreName($externalName);
                    $aliasKey = self::aliasKey($code, $normalizedName);
                    $alias = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$aliasesTable} WHERE client_id=%d AND project_id=%d AND alias_key=%s LIMIT 1",
                        $clientId,
                        $projectId,
                        $aliasKey
                    ), ARRAY_A);
                    if (!$alias) {
                        $locationId = self::autoMapLocation($clientId, $projectId, $code, $externalName);
                        $wpdb->insert($aliasesTable, [
                            'client_id' => $clientId,
                            'project_id' => $projectId,
                            'alias_key' => $aliasKey,
                            'external_store_code' => $code,
                            'external_name' => $externalName,
                            'normalized_name' => $normalizedName,
                            'location_id' => $locationId ?: null,
                            'created_by' => get_current_user_id(),
                            'updated_by' => get_current_user_id(),
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql'),
                        ]);
                        if ($wpdb->last_error) throw new \RuntimeException('Falha ao guardar uma associação de loja: ' . $wpdb->last_error);
                        $alias = [
                            'id' => (int)$wpdb->insert_id,
                            'location_id' => $locationId,
                        ];
                    }
                    $locationId = absint($alias['location_id'] ?? 0);
                    $statusLabel = sanitize_text_field((string)($item['status_label'] ?? 'Por atualizar'));
                    $statusKey = sanitize_key((string)($item['status_key'] ?? ''));
                    if ($statusKey === '') $statusKey = PernodMaterialImporter::statusKey($statusLabel . ' ' . (string)($item['observations'] ?? ''));

                    $ok = $wpdb->insert($itemsTable, [
                        'document_id' => $documentId,
                        'section_id' => $sectionId,
                        'alias_id' => absint($alias['id'] ?? 0) ?: null,
                        'location_id' => $locationId ?: null,
                        'external_store_code' => $code,
                        'external_store_name' => $externalName,
                        'normalized_store_name' => $normalizedName,
                        'sales_rep' => sanitize_text_field((string)($item['sales_rep'] ?? '')),
                        'implementation_type' => sanitize_text_field((string)($item['implementation_type'] ?? 'Implementação de material')),
                        'planned_date' => self::dateOrNull((string)($item['planned_date'] ?? '')),
                        'planned_date_text' => sanitize_text_field((string)($item['planned_date_text'] ?? '')),
                        'status_key' => $statusKey ?: 'pending',
                        'status_label' => $statusLabel ?: 'Por atualizar',
                        'observations' => sanitize_textarea_field((string)($item['observations'] ?? '')),
                        'materials_proposed' => max(0, (int)($item['materials_proposed'] ?? 0)),
                        'materials_missing' => max(0, (int)($item['materials_missing'] ?? 0)),
                        'materials_implemented' => max(0, (int)($item['materials_implemented'] ?? 0)),
                        'details_json' => wp_json_encode((array)($item['details'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'sort_order' => (int)($item['sort_order'] ?? (($itemIndex + 1) * 10)),
                        'updated_by' => get_current_user_id(),
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ]);
                    if ($ok === false) throw new \RuntimeException('Não foi possível guardar uma linha de implementação.');
                }
            }
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        self::refreshSummary($documentId);
        do_action('fieldflow_mapping_source_changed', 'implementation', $documentId);
        return $documentId;
    }

    public static function get(int $documentId): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table('implementation_documents') . ' WHERE id=%d LIMIT 1',
            $documentId
        ), ARRAY_A);
        return $row ?: null;
    }

    public static function listAdmin(array $filters = []): array {
        global $wpdb;
        $where = ['1=1'];
        $args = [];
        if (!empty($filters['client_id'])) { $where[] = 'd.client_id=%d'; $args[] = absint($filters['client_id']); }
        if (!empty($filters['project_id'])) { $where[] = 'd.project_id=%d'; $args[] = absint($filters['project_id']); }
        if (!empty($filters['status'])) { $where[] = 'd.status=%s'; $args[] = sanitize_key((string)$filters['status']); }
        $sql = 'SELECT d.*,c.name AS client_name,p.name AS project_name FROM ' . self::table('implementation_documents') . ' d '
             . 'LEFT JOIN ' . self::table('clients') . ' c ON c.id=d.client_id '
             . 'LEFT JOIN ' . self::table('projects') . ' p ON p.id=d.project_id '
             . 'WHERE ' . implode(' AND ', $where) . ' ORDER BY d.created_at DESC,d.id DESC LIMIT 100';
        return $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
    }

    public static function adminDetail(int $documentId): array {
        global $wpdb;
        $document = self::get($documentId);
        if (!$document) return [];
        $sections = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table('implementation_sections') . ' WHERE document_id=%d ORDER BY sort_order ASC,id ASC',
            $documentId
        ), ARRAY_A) ?: [];
        $items = $wpdb->get_results($wpdb->prepare(
            'SELECT i.*,l.name AS location_name,s.title AS section_title FROM ' . self::table('implementation_items') . ' i '
            . 'LEFT JOIN ' . self::table('locations') . ' l ON l.id=i.location_id '
            . 'LEFT JOIN ' . self::table('implementation_sections') . ' s ON s.id=i.section_id '
            . 'WHERE i.document_id=%d ORDER BY s.sort_order ASC,i.sort_order ASC,i.id ASC',
            $documentId
        ), ARRAY_A) ?: [];
        foreach ($items as &$item) $item['details'] = self::decodeJson((string)($item['details_json'] ?? ''));
        unset($item);
        $aliases = $wpdb->get_results($wpdb->prepare(
            'SELECT a.*,l.name AS location_name,COUNT(i.id) AS item_count FROM ' . self::table('implementation_store_aliases') . ' a '
            . 'INNER JOIN ' . self::table('implementation_items') . ' i ON i.alias_id=a.id AND i.document_id=%d '
            . 'LEFT JOIN ' . self::table('locations') . ' l ON l.id=a.location_id '
            . 'WHERE a.client_id=%d AND a.project_id=%d GROUP BY a.id '
            . 'ORDER BY CASE WHEN a.location_id IS NULL OR a.location_id=0 THEN 0 ELSE 1 END ASC,a.external_store_code ASC,a.external_name ASC',
            $documentId,
            (int)$document['client_id'],
            (int)$document['project_id']
        ), ARRAY_A) ?: [];
        return [
            'document' => $document,
            'sections' => $sections,
            'items' => $items,
            'aliases' => $aliases,
            'locations' => self::locationsForScope((int)$document['client_id'], (int)$document['project_id']),
        ];
    }

    public static function saveMappings(int $documentId, array $mapping): int {
        global $wpdb;
        $document = self::get($documentId);
        if (!$document) throw new \RuntimeException('Documento não encontrado.');
        $allowed = [];
        foreach (self::locationsForScope((int)$document['client_id'], (int)$document['project_id']) as $location) {
            $allowed[(int)$location['id']] = $location;
        }
        $aliasesTable = self::table('implementation_store_aliases');
        $itemsTable = self::table('implementation_items');
        $changed = 0;
        foreach ($mapping as $aliasIdRaw => $locationIdRaw) {
            $aliasId = absint($aliasIdRaw);
            $locationId = absint($locationIdRaw);
            if (!$aliasId) continue;
            if ($locationId && empty($allowed[$locationId])) throw new \RuntimeException('Uma das lojas não pertence ao âmbito selecionado.');
            $alias = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$aliasesTable} WHERE id=%d AND client_id=%d AND project_id=%d LIMIT 1",
                $aliasId,
                (int)$document['client_id'],
                (int)$document['project_id']
            ), ARRAY_A);
            if (!$alias) continue;
            if ($locationId && $locationId === (int)($alias['location_id'] ?? 0)) continue;
            if ($locationId) {
                $label = (string)($alias['external_name'] ?? '');
                $family = StoreIdentityAuthority::family($label);
                if ($family === '' || !StoreIdentityAuthority::sourceMatchesLocation($family, $label, (array)$allowed[$locationId], true)) {
                    throw new \RuntimeException('Loja de implementação sem identidade comercial compatível. Mantém a associação por rever.');
                }
            }
            $wpdb->update($aliasesTable, [
                'location_id' => $locationId ?: null,
                'updated_by' => get_current_user_id(),
                'updated_at' => current_time('mysql'),
            ], ['id' => $aliasId]);
            $wpdb->update($itemsTable, [
                'location_id' => $locationId ?: null,
                'updated_by' => get_current_user_id(),
                'updated_at' => current_time('mysql'),
            ], [
                'document_id' => $documentId,
                'alias_id' => $aliasId,
            ]);
            if (class_exists('\RoutesPro\Mapping\MappingService')) {
                \RoutesPro\Mapping\MappingService::captureStoreMapping(
                    'implementation',
                    $aliasId,
                    (int)$document['client_id'],
                    (int)$document['project_id'],
                    (string)($alias['external_name'] ?? ''),
                    (string)($alias['external_store_code'] ?? ''),
                    $locationId,
                    ['source_scope_id'=>$documentId]
                );
            }
            $changed++;
        }
        self::refreshSummary($documentId);
        return $changed;
    }

    public static function updateDocument(int $documentId, array $payload): void {
        global $wpdb;
        $document = self::get($documentId);
        if (!$document) throw new \RuntimeException('Documento não encontrado.');
        $validFrom = self::dateOrNull((string)($payload['valid_from'] ?? ''));
        $validTo = self::dateOrNull((string)($payload['valid_to'] ?? ''));
        if ($validFrom && $validTo && $validTo < $validFrom) throw new \RuntimeException('A validade final não pode ser anterior à inicial.');
        $title = sanitize_text_field((string)($payload['title'] ?? ''));
        if ($title === '') throw new \RuntimeException('O título é obrigatório.');
        $wpdb->update(self::table('implementation_documents'), [
            'title' => $title,
            'notes_text' => wp_kses_post((string)($payload['notes_text'] ?? '')),
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'updated_at' => current_time('mysql'),
        ], ['id' => $documentId]);
    }

    public static function updateItems(int $documentId, array $items): int {
        global $wpdb;
        $document = self::get($documentId);
        if (!$document) throw new \RuntimeException('Documento não encontrado.');
        $table = self::table('implementation_items');
        $updated = 0;
        foreach ($items as $itemIdRaw => $payload) {
            $itemId = absint($itemIdRaw);
            if (!$itemId || !is_array($payload)) continue;
            $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id=%d AND document_id=%d", $itemId, $documentId));
            if (!$exists) continue;
            $statusLabel = sanitize_text_field((string)($payload['status_label'] ?? 'Por atualizar'));
            $observations = sanitize_textarea_field((string)($payload['observations'] ?? ''));
            $statusKey = PernodMaterialImporter::statusKey($statusLabel . ' ' . $observations);
            $wpdb->update($table, [
                'sales_rep' => sanitize_text_field((string)($payload['sales_rep'] ?? '')),
                'implementation_type' => sanitize_text_field((string)($payload['implementation_type'] ?? '')),
                'planned_date' => self::dateOrNull((string)($payload['planned_date'] ?? '')),
                'planned_date_text' => sanitize_text_field((string)($payload['planned_date_text'] ?? '')),
                'status_key' => $statusKey,
                'status_label' => $statusLabel ?: 'Por atualizar',
                'observations' => $observations,
                'materials_proposed' => max(0, (int)($payload['materials_proposed'] ?? 0)),
                'materials_missing' => max(0, (int)($payload['materials_missing'] ?? 0)),
                'materials_implemented' => max(0, (int)($payload['materials_implemented'] ?? 0)),
                'updated_by' => get_current_user_id(),
                'updated_at' => current_time('mysql'),
            ], ['id' => $itemId, 'document_id' => $documentId]);
            $updated++;
        }
        self::refreshSummary($documentId);
        return $updated;
    }

    public static function publish(int $documentId): void {
        global $wpdb;
        $document = self::get($documentId);
        if (!$document) throw new \RuntimeException('Documento não encontrado.');
        $summary = self::refreshSummary($documentId);
        if ((int)($summary['items'] ?? 0) < 1) throw new \RuntimeException('O documento não contém implementações.');
        $wpdb->update(self::table('implementation_documents'), [
            'status' => 'published',
            'published_by' => get_current_user_id(),
            'published_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['id' => $documentId]);
    }

    public static function archive(int $documentId): void {
        global $wpdb;
        $wpdb->update(self::table('implementation_documents'), [
            'status' => 'archived',
            'updated_at' => current_time('mysql'),
        ], ['id' => $documentId]);
    }

    public static function delete(int $documentId): void {
        global $wpdb;
        if (!self::get($documentId)) return;
        $wpdb->query('START TRANSACTION');
        try {
            $wpdb->delete(self::table('implementation_items'), ['document_id' => $documentId]);
            $wpdb->delete(self::table('implementation_sections'), ['document_id' => $documentId]);
            $wpdb->delete(self::table('implementation_documents'), ['id' => $documentId]);
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public static function refreshSummary(int $documentId): array {
        global $wpdb;
        $document = self::get($documentId);
        if (!$document) return [];
        $itemsTable = self::table('implementation_items');
        $sectionsTable = self::table('implementation_sections');
        $summary = self::decodeJson((string)($document['import_summary_json'] ?? ''));
        $summary['sections'] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$sectionsTable} WHERE document_id=%d", $documentId));
        $summary['items'] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$itemsTable} WHERE document_id=%d", $documentId));
        $summary['mapped'] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$itemsTable} WHERE document_id=%d AND location_id IS NOT NULL AND location_id>0", $documentId));
        $summary['unmapped'] = max(0, $summary['items'] - $summary['mapped']);
        $summary['implemented'] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$itemsTable} WHERE document_id=%d AND status_key='implemented'", $documentId));
        $summary['blocked'] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$itemsTable} WHERE document_id=%d AND status_key IN ('stock_blocked','order_pending')", $documentId));
        $summary['pending'] = max(0, $summary['items'] - $summary['implemented']);
        $summary['materials_proposed'] = (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(materials_proposed),0) FROM {$itemsTable} WHERE document_id=%d", $documentId));
        $summary['materials_missing'] = (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(materials_missing),0) FROM {$itemsTable} WHERE document_id=%d", $documentId));
        $summary['materials_implemented'] = (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(materials_implemented),0) FROM {$itemsTable} WHERE document_id=%d", $documentId));
        $wpdb->update(self::table('implementation_documents'), [
            'import_summary_json' => wp_json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => current_time('mysql'),
        ], ['id' => $documentId]);
        return $summary;
    }

    public static function mine(int $userId, int $clientId = 0, int $projectId = 0): array {
        return self::frontList($userId, $clientId, $projectId, false);
    }

    public static function clientList(int $userId, int $clientId = 0, int $projectId = 0): array {
        return self::frontList($userId, $clientId, $projectId, true);
    }

    private static function frontList(int $userId, int $clientId, int $projectId, bool $clientMode): array {
        global $wpdb;
        $where = ["d.status='published'"];
        $args = [];
        if ($clientId) { $where[] = 'd.client_id=%d'; $args[] = $clientId; }
        if ($projectId) { $where[] = 'd.project_id=%d'; $args[] = $projectId; }
        if (!Permissions::is_manager($userId)) {
            [$scopeSql, $scopeArgs] = Permissions::scope_sql('d', 'd.client_id', 'd.project_id', $userId);
            $where[] = $scopeSql;
            $args = array_merge($args, $scopeArgs);
        }
        $sql = 'SELECT d.*,c.name AS client_name,p.name AS project_name FROM ' . self::table('implementation_documents') . ' d '
             . 'LEFT JOIN ' . self::table('clients') . ' c ON c.id=d.client_id '
             . 'LEFT JOIN ' . self::table('projects') . ' p ON p.id=d.project_id '
             . 'WHERE ' . implode(' AND ', $where) . ' ORDER BY COALESCE(d.valid_from,d.created_at) DESC,d.id DESC LIMIT 30';
        $rows = $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
        $out = [];
        foreach ($rows as $row) {
            $payload = self::payload((int)$row['id'], $userId, $clientMode, $row);
            if ($payload) $out[] = $payload;
        }
        return $out;
    }

    public static function payload(int $documentId, int $userId, bool $clientMode = false, array $documentRow = []): array {
        global $wpdb;
        $document = $documentRow ?: (self::get($documentId) ?: []);
        if (!$document) return [];
        $isManager = Permissions::is_manager($userId);
        $allowedLocations = [];
        if (!$clientMode && !$isManager) {
            $allowedLocations = array_fill_keys(self::userLocationIds(
                $userId,
                (int)$document['project_id'],
                (string)($document['valid_from'] ?? ''),
                (string)($document['valid_to'] ?? '')
            ), true);
            if (!$allowedLocations) return [];
        }

        $sections = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table('implementation_sections') . ' WHERE document_id=%d ORDER BY sort_order ASC,id ASC',
            $documentId
        ), ARRAY_A) ?: [];
        $items = $wpdb->get_results($wpdb->prepare(
            'SELECT i.*,l.name AS location_name,l.city AS location_city,l.tags AS location_tags,l.meta_json AS location_meta_json FROM ' . self::table('implementation_items') . ' i '
            . 'LEFT JOIN ' . self::table('locations') . ' l ON l.id=i.location_id '
            . 'WHERE i.document_id=%d ORDER BY i.sort_order ASC,i.id ASC',
            $documentId
        ), ARRAY_A) ?: [];

        $itemsBySection = [];
        $flat = [];
        foreach ($items as $item) {
            $locationId = absint($item['location_id'] ?? 0);
            $label = (string)($item['external_store_name'] ?? '');
            $family = StoreIdentityAuthority::family($label);
            $validIdentity = $locationId > 0 && $family !== '' && StoreIdentityAuthority::sourceMatchesLocation(
                $family, $label, [
                    'name'=>(string)($item['location_name'] ?? ''),
                    'tags'=>(string)($item['location_tags'] ?? ''),
                    'meta_json'=>(string)($item['location_meta_json'] ?? '')
                ], true
            );
            if (!$validIdentity) $locationId = 0;
            if (!$clientMode && !$isManager && (!$locationId || empty($allowedLocations[$locationId]))) continue;
            $payload = [
                'id' => (int)$item['id'],
                'section_id' => (int)$item['section_id'],
                'location_id' => $locationId,
                'location_name' => (string)($item['location_name'] ?: $item['external_store_name']),
                'location_city' => (string)($item['location_city'] ?? ''),
                'external_store_code' => (string)$item['external_store_code'],
                'external_store_name' => (string)$item['external_store_name'],
                'sales_rep' => (string)$item['sales_rep'],
                'implementation_type' => (string)$item['implementation_type'],
                'planned_date' => $item['planned_date'] ?: null,
                'planned_date_text' => (string)$item['planned_date_text'],
                'status_key' => sanitize_key((string)$item['status_key']) ?: 'pending',
                'status_label' => (string)$item['status_label'],
                'observations' => (string)$item['observations'],
                'materials_proposed' => (int)$item['materials_proposed'],
                'materials_missing' => (int)$item['materials_missing'],
                'materials_implemented' => (int)$item['materials_implemented'],
                'mapped' => $locationId > 0,
            ];
            $itemsBySection[(int)$item['section_id']][] = $payload;
            $flat[] = $payload;
        }
        if (!$flat && !$clientMode && !$isManager) return [];

        $sectionPayload = [];
        foreach ($sections as $section) {
            $sectionItems = $itemsBySection[(int)$section['id']] ?? [];
            if (!$sectionItems && !$clientMode && !$isManager) continue;
            $sectionPayload[] = [
                'id' => (int)$section['id'],
                'key' => (string)$section['section_key'],
                'title' => (string)$section['title'],
                'summary' => (string)$section['summary_text'],
                'metadata' => self::decodeJson((string)$section['metadata_json']),
                'items' => $sectionItems,
            ];
        }

        $metrics = self::metrics($flat);
        return [
            'id' => (int)$document['id'],
            'client_id' => (int)$document['client_id'],
            'project_id' => (int)$document['project_id'],
            'client_name' => (string)($document['client_name'] ?? ''),
            'project_name' => (string)($document['project_name'] ?? ''),
            'title' => (string)$document['title'],
            'version_no' => (int)$document['version_no'],
            'valid_from' => $document['valid_from'] ?: null,
            'valid_to' => $document['valid_to'] ?: null,
            'notes' => (string)$document['notes_text'],
            'published_at' => $document['published_at'] ?: null,
            'source_filename' => (string)$document['source_filename'],
            'metrics' => $metrics,
            'sections' => $sectionPayload,
            'items' => $flat,
            'mode' => $clientMode ? 'client' : ($isManager ? 'manager' : 'team'),
        ];
    }

    private static function metrics(array $items): array {
        $metrics = [
            'total' => count($items),
            'implemented' => 0,
            'blocked' => 0,
            'pending' => 0,
            'materials_proposed' => 0,
            'materials_missing' => 0,
            'materials_implemented' => 0,
        ];
        foreach ($items as $item) {
            $key = (string)($item['status_key'] ?? 'pending');
            if ($key === 'implemented') $metrics['implemented']++;
            if (in_array($key, ['stock_blocked', 'order_pending'], true)) $metrics['blocked']++;
            if ($key !== 'implemented') $metrics['pending']++;
            $metrics['materials_proposed'] += (int)($item['materials_proposed'] ?? 0);
            $metrics['materials_missing'] += (int)($item['materials_missing'] ?? 0);
            $metrics['materials_implemented'] += (int)($item['materials_implemented'] ?? 0);
        }
        return $metrics;
    }

    public static function locationsForScope(int $clientId, int $projectId): array {
        global $wpdb;
        $cacheKey = $clientId . '|' . $projectId;
        if (isset(self::$locationCache[$cacheKey])) return self::$locationCache[$cacheKey];
        $locations = self::table('locations');
        $campaign = self::table('campaign_locations');
        $where = ['l.is_active=1'];
        $args = [];
        if ($clientId && $projectId) {
            $where[] = '((l.client_id=%d AND (l.project_id IS NULL OR l.project_id=0 OR l.project_id=%d)) OR l.project_id=%d OR EXISTS(SELECT 1 FROM ' . $campaign . ' cl WHERE cl.location_id=l.id AND cl.project_id=%d))';
            array_push($args, $clientId, $projectId, $projectId, $projectId);
        } elseif ($clientId) {
            $where[] = 'l.client_id=%d';
            $args[] = $clientId;
        } elseif ($projectId) {
            $where[] = '(l.project_id=%d OR EXISTS(SELECT 1 FROM ' . $campaign . ' cl WHERE cl.location_id=l.id AND cl.project_id=%d))';
            array_push($args, $projectId, $projectId);
        }
        $sql = 'SELECT DISTINCT l.id,l.client_id,l.project_id,l.name,l.city,l.address,l.external_ref,l.tags,l.meta_json FROM ' . $locations . ' l WHERE ' . implode(' AND ', $where) . ' ORDER BY l.name ASC LIMIT 15000';
        $rows = $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
        self::$locationCache[$cacheKey] = $rows;
        return $rows;
    }

    private static function autoMapLocation(int $clientId, int $projectId, string $code, string $name): int {
        $locations = self::locationsForScope($clientId, $projectId);
        if (!$locations) return 0;
        // 9DN: a numeric store code is not a merchant identity.
        $family = StoreIdentityAuthority::family($name);
        if ($family === '') return 0;
        $locations = array_values(array_filter($locations, static function(array $location) use ($family, $name): bool {
            return StoreIdentityAuthority::sourceMatchesLocation($family, $name, $location, true);
        }));
        if (!$locations) return 0;
        $normalizedName = self::normalizeStoreName($name);
        $codeMatches = [];
        if ($code !== '') {
            foreach ($locations as $location) {
                $codes = self::locationCodes($location);
                if (in_array($code, $codes, true)) $codeMatches[] = (int)$location['id'];
            }
            $codeMatches = array_values(array_unique(array_filter($codeMatches)));
            if (count($codeMatches) === 1) return $codeMatches[0];
        }

        $exact = [];
        foreach ($locations as $location) {
            if ($normalizedName !== '' && self::normalizeStoreName((string)$location['name']) === $normalizedName) {
                $exact[] = (int)$location['id'];
            }
        }
        $exact = array_values(array_unique(array_filter($exact)));
        if (count($exact) === 1) return $exact[0];

        if ($normalizedName !== '') {
            $scores = [];
            foreach ($locations as $location) {
                $candidate = self::normalizeStoreName((string)$location['name']);
                if ($candidate === '') continue;
                similar_text($normalizedName, $candidate, $score);
                $scores[] = ['id' => (int)$location['id'], 'score' => (float)$score];
            }
            usort($scores, static fn($a, $b) => $b['score'] <=> $a['score']);
            $best = $scores[0] ?? null;
            $second = $scores[1] ?? ['score' => 0];
            if ($best && $best['score'] >= 94 && ($best['score'] - $second['score']) >= 4) return (int)$best['id'];
        }
        return 0;
    }

    private static function locationCodes(array $location): array {
        $codes = [];
        $add = static function ($value) use (&$codes, &$add): void {
            if (is_array($value)) {
                foreach ($value as $v) $add($v);
                return;
            }
            $code = ImplementationService::normaliseCode((string)$value);
            if ($code !== '') $codes[$code] = $code;
        };
        $add($location['external_ref'] ?? '');
        $meta = self::decodeJson((string)($location['meta_json'] ?? ''));
        $walk = static function ($value, string $key = '') use (&$walk, $add): void {
            if (is_array($value)) {
                foreach ($value as $childKey => $child) $walk($child, (string)$childKey);
                return;
            }
            $normalizedKey = strtolower($key);
            if ($normalizedKey === '' || preg_match('/code|codigo|ref|store|loja|pdv/', $normalizedKey)) $add($value);
        };
        $walk($meta);
        return array_values($codes);
    }

    private static function userLocationIds(int $userId, int $projectId, string $from, string $to): array {
        global $wpdb;
        if (!$userId || !$projectId) return [];
        $ids = [];
        $campaignRows = $wpdb->get_col($wpdb->prepare(
            'SELECT location_id FROM ' . self::table('campaign_locations') . ' WHERE project_id=%d AND assigned_to=%d AND is_active=1 AND location_id>0',
            $projectId,
            $userId
        )) ?: [];
        foreach ($campaignRows as $id) $ids[(int)$id] = (int)$id;

        $fromDate = self::dateOrNull($from) ?: date('Y-m-d', strtotime('-365 days', current_time('timestamp')));
        $toDate = self::dateOrNull($to) ?: date('Y-m-d', strtotime('+365 days', current_time('timestamp')));
        $routeRows = $wpdb->get_col($wpdb->prepare(
            'SELECT DISTINCT rs.location_id FROM ' . self::table('routes') . ' r INNER JOIN ' . self::table('route_stops') . ' rs ON rs.route_id=r.id '
            . 'WHERE r.project_id=%d AND r.owner_user_id=%d AND r.date BETWEEN %s AND %s AND rs.location_id>0',
            $projectId,
            $userId,
            $fromDate,
            $toDate
        )) ?: [];
        foreach ($routeRows as $id) $ids[(int)$id] = (int)$id;
        $assignedRows = $wpdb->get_col($wpdb->prepare(
            'SELECT DISTINCT rs.location_id FROM ' . self::table('assignments') . ' a '
            . 'INNER JOIN ' . self::table('routes') . ' r ON r.id=a.route_id '
            . 'INNER JOIN ' . self::table('route_stops') . ' rs ON rs.route_id=r.id '
            . 'WHERE a.user_id=%d AND a.is_active=1 AND r.project_id=%d AND r.date BETWEEN %s AND %s AND rs.location_id>0',
            $userId,
            $projectId,
            $fromDate,
            $toDate
        )) ?: [];
        foreach ($assignedRows as $id) $ids[(int)$id] = (int)$id;
        ksort($ids);
        return array_values($ids);
    }

    public static function normalizeStoreName(string $value): string {
        if (function_exists('remove_accents')) $value = remove_accents($value);
        else {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) $value = $converted;
        }
        $value = strtolower($value);
        $value = preg_replace('/\b(pingo doce|pd|neg|super|loja|hipermercado|supermercado)\b/', ' ', (string)$value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', (string)$value);
        return trim((string)preg_replace('/\s+/', ' ', (string)$value));
    }

    public static function normaliseCode(string $value): string {
        if (preg_match('/\d+/', trim($value), $match)) return ltrim($match[0], '0') ?: '0';
        return '';
    }

    private static function aliasKey(string $code, string $normalizedName): string {
        return hash('sha256', ($code !== '' ? 'code:' . $code : 'name:' . $normalizedName));
    }

    private static function dateOrNull(string $value): ?string {
        $value = trim($value);
        if ($value === '') return null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
        [$y, $m, $d] = array_map('intval', explode('-', $value));
        return checkdate($m, $d, $y) ? $value : null;
    }

    private static function decodeJson(string $value): array {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
