<?php
namespace RoutesPro\Mapping;

if (!defined('ABSPATH')) exit;

class MappingRepository {
    public static function table(): string { global $wpdb; return $wpdb->prefix . 'routespro_mapping_records'; }
    public static function aliasTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_mapping_aliases'; }
    public static function historyTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_mapping_history'; }

    public static function tablesReady(): bool {
        global $wpdb;
        foreach ([self::table(), self::aliasTable(), self::historyTable()] as $table) {
            if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return false;
        }
        return true;
    }

    public static function upsert(array $record): int {
        global $wpdb;
        $defaults = [
            'mapping_uuid' => wp_generate_uuid4(),
            'entity_type' => 'store',
            'source_module' => 'manual',
            'source_scope_id' => 0,
            'source_record_id' => 0,
            'client_id' => 0,
            'project_id' => 0,
            'source_key' => '',
            'external_ref' => '',
            'source_label' => '',
            'normalized_label' => '',
            'target_type' => 'location',
            'target_id' => 0,
            'confidence' => 0,
            'match_method' => '',
            'status' => 'pending',
            'is_locked' => 0,
            'candidate_json' => null,
            'meta_json' => null,
            'last_synced_at' => current_time('mysql'),
            'created_by' => get_current_user_id() ?: null,
            'updated_by' => get_current_user_id() ?: null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        $record = array_merge($defaults, $record);
        $sourceKey = substr(sanitize_text_field((string)$record['source_key']), 0, 191);
        if ($sourceKey === '') return 0;

        $existing = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE source_module=%s AND client_id=%d AND project_id=%d AND source_key=%s LIMIT 1',
            sanitize_key((string)$record['source_module']), absint($record['client_id']), absint($record['project_id']), $sourceKey
        ), ARRAY_A);

        $payload = [
            'entity_type' => sanitize_key((string)$record['entity_type']),
            'source_module' => sanitize_key((string)$record['source_module']),
            'source_scope_id' => absint($record['source_scope_id']),
            'source_record_id' => absint($record['source_record_id']),
            'client_id' => absint($record['client_id']),
            'project_id' => absint($record['project_id']),
            'source_key' => $sourceKey,
            'external_ref' => substr(sanitize_text_field((string)$record['external_ref']), 0, 191),
            'source_label' => substr(sanitize_text_field((string)$record['source_label']), 0, 255),
            'normalized_label' => substr(sanitize_text_field((string)$record['normalized_label']), 0, 191),
            'target_type' => sanitize_key((string)$record['target_type']),
            'target_id' => absint($record['target_id']),
            'confidence' => max(0, min(100, (int)$record['confidence'])),
            'match_method' => substr(sanitize_key((string)$record['match_method']), 0, 64),
            'status' => sanitize_key((string)$record['status']),
            'is_locked' => !empty($record['is_locked']) ? 1 : 0,
            'candidate_json' => self::encode($record['candidate_json']),
            'meta_json' => self::encode($record['meta_json']),
            'last_synced_at' => (string)$record['last_synced_at'],
            'updated_by' => get_current_user_id() ?: null,
            'updated_at' => current_time('mysql'),
        ];

        if ($existing) {
            if (!empty($existing['is_locked']) && empty($record['force'])) {
                foreach (['target_id','confidence','match_method','status','candidate_json'] as $protected) unset($payload[$protected]);
            } elseif (($existing['status'] ?? '') === 'rejected' && empty($record['force_status'])) {
                unset($payload['target_id'], $payload['confidence'], $payload['match_method'], $payload['status']);
            }
            $wpdb->update(self::table(), $payload, ['id' => (int)$existing['id']]);
            return (int)$existing['id'];
        }

        $payload['mapping_uuid'] = substr(sanitize_text_field((string)$record['mapping_uuid']), 0, 36);
        $payload['created_by'] = get_current_user_id() ?: null;
        $payload['created_at'] = current_time('mysql');
        $wpdb->insert(self::table(), $payload);
        return (int)$wpdb->insert_id;
    }

    public static function get(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d', $id), ARRAY_A);
        if (!$row) return null;
        return self::hydrate($row);
    }

    public static function findBySource(string $module, int $clientId, int $projectId, string $sourceKey): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE source_module=%s AND client_id=%d AND project_id=%d AND source_key=%s LIMIT 1',
            sanitize_key($module), $clientId, $projectId, $sourceKey
        ), ARRAY_A);
        return $row ? self::hydrate($row) : null;
    }

    public static function update(int $id, array $changes): bool {
        global $wpdb;
        $allowed = ['target_id','confidence','match_method','status','is_locked','candidate_json','meta_json','last_synced_at'];
        $payload = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $changes)) continue;
            if (in_array($key, ['target_id','confidence','is_locked'], true)) $payload[$key] = (int)$changes[$key];
            elseif (in_array($key, ['candidate_json','meta_json'], true)) $payload[$key] = self::encode($changes[$key]);
            else $payload[$key] = sanitize_text_field((string)$changes[$key]);
        }
        if (!$payload) return false;
        $payload['updated_by'] = get_current_user_id() ?: null;
        $payload['updated_at'] = current_time('mysql');
        return $wpdb->update(self::table(), $payload, ['id' => $id]) !== false;
    }

    public static function list(array $filters = [], int $limit = 100, int $offset = 0): array {
        global $wpdb;
        $where = ['1=1'];
        $args = [];
        foreach (['client_id','project_id','target_id'] as $field) {
            if (!empty($filters[$field])) { $where[] = $field . '=%d'; $args[] = absint($filters[$field]); }
        }
        foreach (['entity_type','source_module','status'] as $field) {
            if (!empty($filters[$field]) && $filters[$field] !== 'all') { $where[] = $field . '=%s'; $args[] = sanitize_key((string)$filters[$field]); }
        }
        if (!empty($filters['search'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field((string)$filters['search'])) . '%';
            $where[] = '(source_label LIKE %s OR external_ref LIKE %s OR normalized_label LIKE %s)';
            array_push($args, $like, $like, $like);
        }
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode(' AND ', $where) . " ORDER BY CASE status WHEN 'pending' THEN 0 WHEN 'suggested' THEN 1 WHEN 'confirmed' THEN 2 ELSE 3 END, confidence DESC, updated_at DESC LIMIT {$limit} OFFSET {$offset}";
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function count(array $filters = []): int {
        global $wpdb;
        $where = ['1=1']; $args = [];
        foreach (['client_id','project_id'] as $field) if (!empty($filters[$field])) { $where[] = $field . '=%d'; $args[] = absint($filters[$field]); }
        foreach (['entity_type','source_module','status'] as $field) if (!empty($filters[$field]) && $filters[$field] !== 'all') { $where[] = $field . '=%s'; $args[] = sanitize_key((string)$filters[$field]); }
        if (!empty($filters['search'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field((string)$filters['search'])) . '%';
            $where[] = '(source_label LIKE %s OR external_ref LIKE %s OR normalized_label LIKE %s)';
            array_push($args, $like, $like, $like);
        }
        $sql = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . implode(' AND ', $where);
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        return (int)$wpdb->get_var($sql);
    }

    public static function counts(array $filters = []): array {
        global $wpdb;
        $where = ['1=1']; $args = [];
        foreach (['client_id','project_id'] as $field) if (!empty($filters[$field])) { $where[] = $field . '=%d'; $args[] = absint($filters[$field]); }
        $sql = 'SELECT status,entity_type,COUNT(*) total FROM ' . self::table() . ' WHERE ' . implode(' AND ', $where) . ' GROUP BY status,entity_type';
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        $out = ['total'=>0,'pending'=>0,'suggested'=>0,'confirmed'=>0,'rejected'=>0,'store'=>0,'product'=>0,'assortment'=>0];
        foreach ($rows as $row) {
            $count = (int)$row['total']; $out['total'] += $count;
            $status = sanitize_key((string)$row['status']); $entity = sanitize_key((string)$row['entity_type']);
            if (isset($out[$status])) $out[$status] += $count;
            if (isset($out[$entity])) $out[$entity] += $count;
        }
        return $out;
    }

    public static function addHistory(int $mappingId, string $action, $before, $after, string $reason = '', string $batchUuid = ''): int {
        global $wpdb;
        $wpdb->insert(self::historyTable(), [
            'mapping_id' => $mappingId,
            'action_name' => substr(sanitize_key($action), 0, 64),
            'before_json' => self::encode($before),
            'after_json' => self::encode($after),
            'reason_text' => substr(sanitize_textarea_field($reason), 0, 1000),
            'batch_uuid' => substr(sanitize_text_field($batchUuid), 0, 36),
            'actor_user_id' => get_current_user_id() ?: null,
            'created_at' => current_time('mysql'),
        ]);
        return (int)$wpdb->insert_id;
    }

    public static function history(int $limit = 100, int $mappingId = 0): array {
        global $wpdb;
        $limit = max(1, min(300, $limit));
        $sql = 'SELECT h.*,r.source_label,r.source_module,r.entity_type FROM ' . self::historyTable() . ' h LEFT JOIN ' . self::table() . ' r ON r.id=h.mapping_id';
        if ($mappingId) $sql .= $wpdb->prepare(' WHERE h.mapping_id=%d', $mappingId);
        $sql .= ' ORDER BY h.id DESC LIMIT ' . $limit;
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        foreach ($rows as &$row) {
            $row['before'] = self::decode($row['before_json'] ?? null);
            $row['after'] = self::decode($row['after_json'] ?? null);
        }
        unset($row);
        return $rows;
    }

    public static function historyEntry(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::historyTable() . ' WHERE id=%d', $id), ARRAY_A);
        if (!$row) return null;
        $row['before'] = self::decode($row['before_json'] ?? null);
        $row['after'] = self::decode($row['after_json'] ?? null);
        return $row;
    }

    public static function upsertAlias(array $data): int {
        global $wpdb;
        $normalized = MappingEngine::normalize((string)($data['alias_label'] ?? ''));
        if ($normalized === '' || empty($data['target_id'])) return 0;
        $entity = sanitize_key((string)($data['entity_type'] ?? 'store'));
        $clientId = absint($data['client_id'] ?? 0); $projectId = absint($data['project_id'] ?? 0);
        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::aliasTable() . ' WHERE entity_type=%s AND client_id=%d AND project_id=%d AND normalized_alias=%s LIMIT 1',
            $entity, $clientId, $projectId, $normalized
        ));
        $payload = [
            'entity_type' => $entity,
            'client_id' => $clientId,
            'project_id' => $projectId,
            'alias_label' => substr(sanitize_text_field((string)$data['alias_label']), 0, 255),
            'normalized_alias' => substr($normalized, 0, 191),
            'target_type' => sanitize_key((string)($data['target_type'] ?? ($entity === 'product' ? 'cardex_item' : 'location'))),
            'target_id' => absint($data['target_id']),
            'confidence' => max(0, min(100, (int)($data['confidence'] ?? 100))),
            'source_module' => sanitize_key((string)($data['source_module'] ?? 'manual')),
            'updated_by' => get_current_user_id() ?: null,
            'updated_at' => current_time('mysql'),
        ];
        if ($existing) {
            $wpdb->query($wpdb->prepare('UPDATE ' . self::aliasTable() . ' SET use_count=use_count+1 WHERE id=%d', (int)$existing));
            $wpdb->update(self::aliasTable(), $payload, ['id'=>(int)$existing]);
            return (int)$existing;
        }
        $payload['use_count'] = 1;
        $payload['created_by'] = get_current_user_id() ?: null;
        $payload['created_at'] = current_time('mysql');
        $wpdb->insert(self::aliasTable(), $payload);
        return (int)$wpdb->insert_id;
    }

    public static function findAlias(string $entityType, int $clientId, int $projectId, string $label): ?array {
        global $wpdb;
        $normalized = MappingEngine::normalize($label);
        if ($normalized === '') return null;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::aliasTable() . ' WHERE entity_type=%s AND normalized_alias=%s AND ((client_id=%d AND project_id=%d) OR (client_id=%d AND project_id=0) OR (client_id=0 AND project_id=0)) ORDER BY project_id DESC,client_id DESC,confidence DESC LIMIT 1',
            sanitize_key($entityType), $normalized, $clientId, $projectId, $clientId
        ), ARRAY_A);
        return $row ?: null;
    }

    public static function aliases(array $filters = [], int $limit = 200): array {
        global $wpdb;
        $where=['1=1'];$args=[];
        foreach(['client_id','project_id'] as $field) if(!empty($filters[$field])){$where[]=$field.'=%d';$args[]=absint($filters[$field]);}
        if(!empty($filters['entity_type'])&&$filters['entity_type']!=='all'){$where[]='entity_type=%s';$args[]=sanitize_key((string)$filters['entity_type']);}
        $sql='SELECT * FROM '.self::aliasTable().' WHERE '.implode(' AND ',$where).' ORDER BY use_count DESC,updated_at DESC LIMIT '.max(1,min(500,$limit));
        if($args)$sql=$wpdb->prepare($sql,...$args);
        return $wpdb->get_results($sql,ARRAY_A)?:[];
    }

    public static function hydrate(array $row): array {
        $row['candidate_json'] = self::decode($row['candidate_json'] ?? null);
        $row['meta_json'] = self::decode($row['meta_json'] ?? null);
        return $row;
    }

    public static function encode($value): ?string {
        if ($value === null || $value === '' || $value === []) return null;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) $value = $decoded;
        }
        return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function decode($value): array {
        if (is_array($value)) return $value;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
