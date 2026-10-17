<?php
namespace RoutesPro\Stores;

use RoutesPro\Core\AuditLog;
use RoutesPro\Support\Permissions;
use RoutesPro\Services\SubmissionStatusPolicy;
use RoutesPro\Services\OperationalReportDayPolicy;
use RoutesPro\Forms\DailySubmissionService;
use WP_Error;

if (!defined('ABSPATH')) exit;

/**
 * Store Intelligence operational layer.
 *
 * Master data in routespro_locations remains owned by imports/Google/Base
 * Comercial. Human-maintained operational data lives in a separate table so
 * source refreshes can never overwrite field knowledge.
 */
final class StoreProfileService {
    private static ?bool $tableExistsCache = null;
    public const PENDING_NONE = 'none';
    public const PENDING_OPEN = 'open';
    public const PENDING_RESOLVED = 'resolved';
    public const ASSORTMENT_GENERAL = 'general';

    public static function tableName(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_location_profiles';
    }

    public static function tableExists(): bool {
        if (self::$tableExistsCache !== null) return self::$tableExistsCache;
        global $wpdb;
        $table = self::tableName();
        return self::$tableExistsCache = ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
    }

    public static function snapshot(int $locationId, int $projectId = 0, int $routeId = 0, ?int $userId = null) {
        global $wpdb;
        $locationId = absint($locationId);
        $projectId = absint($projectId);
        $routeId = absint($routeId);
        $userId = $userId ?: get_current_user_id();
        if (!$locationId) return new WP_Error('fieldflow_store_invalid_location', 'Loja inválida.', ['status' => 400]);

        $px = $wpdb->prefix . 'routespro_';
        $location = $wpdb->get_row($wpdb->prepare(
            "SELECT l.*, c.name AS category_name, sc.name AS subcategory_name
             FROM {$px}locations l
             LEFT JOIN {$px}categories c ON c.id=l.category_id
             LEFT JOIN {$px}categories sc ON sc.id=l.subcategory_id
             WHERE l.id=%d LIMIT 1",
            $locationId
        ), ARRAY_A);
        if (!$location) return new WP_Error('fieldflow_store_not_found', 'Loja não encontrada.', ['status' => 404]);

        $scope = self::resolvedScope($location, $projectId, $routeId);
        if (!self::canRead($locationId, (int)$scope['client_id'], (int)$scope['project_id'], $routeId, $userId)) {
            return new WP_Error('fieldflow_store_forbidden', 'Sem acesso a esta loja.', ['status' => 403]);
        }

        $profile = self::emptyProfile($locationId);
        if (self::tableExists()) {
            $stored = $wpdb->get_row($wpdb->prepare(
                'SELECT * FROM ' . self::tableName() . ' WHERE location_id=%d LIMIT 1',
                $locationId
            ), ARRAY_A);
            if ($stored) $profile = array_merge($profile, $stored);
        }

        $profile = self::normaliseProfileForOutput($profile);
        $profileComplete = self::profileComplete($profile);
        $lastVisit = self::lastVisit($locationId, (int)$scope['project_id']);
        $canEdit = self::canWrite($locationId, (int)$scope['project_id'], $routeId, $userId);
        $assortment = self::assortmentContext($locationId, (int)$scope['client_id'], (int)$scope['project_id'], (string)$profile['assortment_profile']);

        return [
            'location' => self::normaliseLocationForOutput($location),
            'profile' => $profile,
            'profile_complete' => $profileComplete,
            'profile_state' => $profileComplete ? 'complete' : 'incomplete',
            'assortment' => $assortment,
            'pending' => [
                'status' => (string)$profile['pending_status'],
                'open' => (string)$profile['pending_status'] === self::PENDING_OPEN,
                'count' => ((string)$profile['pending_status'] === self::PENDING_OPEN && trim((string)$profile['pending_actions']) !== '') ? 1 : 0,
            ],
            'last_visit' => $lastVisit,
            'scope' => [
                'client_id' => (int)$scope['client_id'],
                'project_id' => (int)$scope['project_id'],
                'route_id' => $routeId,
            ],
            'permissions' => [
                'can_read' => true,
                'can_edit' => $canEdit,
            ],
        ];
    }

    public static function save(int $locationId, array $payload, ?int $actorUserId = null, int $projectId = 0, int $routeId = 0) {
        global $wpdb;
        $locationId = absint($locationId);
        $projectId = absint($projectId);
        $routeId = absint($routeId);
        $actorUserId = $actorUserId ?: get_current_user_id();
        if (!$locationId || !$actorUserId) return new WP_Error('fieldflow_store_invalid_request', 'Pedido inválido.', ['status' => 400]);
        if (!self::tableExists()) return new WP_Error('fieldflow_store_schema_missing', 'A estrutura da Ficha da Loja ainda não está disponível.', ['status' => 503]);

        $px = $wpdb->prefix . 'routespro_';
        $location = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}locations WHERE id=%d LIMIT 1", $locationId), ARRAY_A);
        if (!$location) return new WP_Error('fieldflow_store_not_found', 'Loja não encontrada.', ['status' => 404]);
        $scope = self::resolvedScope($location, $projectId, $routeId);
        if (!self::canWrite($locationId, (int)$scope['project_id'], $routeId, $actorUserId)) {
            return new WP_Error('fieldflow_store_forbidden', 'Sem permissão para editar esta ficha de loja.', ['status' => 403]);
        }

        $table = self::tableName();
        $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE location_id=%d LIMIT 1", $locationId), ARRAY_A);
        $before = is_array($before) ? $before : null;
        $currentRevision = $before ? max(1, (int)($before['revision_no'] ?? 1)) : 0;
        $expectedRevision = isset($payload['revision_no']) ? max(0, absint($payload['revision_no'])) : 0;
        if ($before && $expectedRevision !== $currentRevision) {
            return new WP_Error('fieldflow_store_profile_conflict', 'A ficha foi alterada noutro dispositivo. Atualiza a loja antes de voltares a guardar.', [
                'status' => 409,
                'current_revision' => $currentRevision,
            ]);
        }

        // PATCH semantics are deliberate here. Older mobile/web clients may not know
        // about fields introduced in later releases; an omitted field must never be
        // interpreted as an instruction to erase an existing store profile value.
        $baseline = array_merge(self::emptyProfile($locationId), is_array($before) ? $before : []);

        $pendingStatus = sanitize_key((string)(array_key_exists('pending_status', $payload) ? $payload['pending_status'] : $baseline['pending_status']));
        if (!in_array($pendingStatus, [self::PENDING_NONE, self::PENDING_OPEN, self::PENDING_RESOLVED], true)) {
            $pendingStatus = self::PENDING_NONE;
        }
        $pendingActions = sanitize_textarea_field((string)(array_key_exists('pending_actions', $payload) ? $payload['pending_actions'] : $baseline['pending_actions']));
        if ($pendingStatus === self::PENDING_NONE && $pendingActions !== '') $pendingStatus = self::PENDING_OPEN;
        if ($pendingStatus === self::PENDING_OPEN && $pendingActions === '') $pendingStatus = self::PENDING_NONE;

        $currentAssortment = self::normaliseAssortmentProfile((string)($baseline['assortment_profile'] ?? self::ASSORTMENT_GENERAL));
        $assortmentWasSubmitted = array_key_exists('assortment_profile', $payload);
        $assortmentProfile = $assortmentWasSubmitted
            ? self::normaliseAssortmentProfile((string)$payload['assortment_profile'])
            : $currentAssortment;

        // Only allow controlled values exposed by the active Cardex. The already
        // stored value is kept as a compatibility option if the current Cardex no
        // longer exposes it, so editing a phone number can never erase typology.
        if ($assortmentWasSubmitted && $assortmentProfile !== $currentAssortment) {
            $currentContext = self::assortmentContext($locationId, (int)$scope['client_id'], (int)$scope['project_id'], $currentAssortment);
            $allowedProfiles = [];
            foreach ((array)($currentContext['options'] ?? []) as $option) {
                if (!is_array($option)) continue;
                $value = self::normaliseAssortmentProfile((string)($option['value'] ?? ''));
                if ($value !== '') $allowedProfiles[$value] = true;
            }
            if (!isset($allowedProfiles[$assortmentProfile])) {
                return new WP_Error('fieldflow_store_invalid_assortment_profile', 'O tipo de Cardex selecionado não pertence ao Cardex ativo desta loja.', [
                    'status' => 422,
                    'profile' => $assortmentProfile,
                ]);
            }
        }

        $storeFormat = sanitize_text_field((string)(array_key_exists('store_format', $payload) ? $payload['store_format'] : $baseline['store_format']));
        $assortmentSource = sanitize_key((string)($baseline['assortment_profile_source'] ?? ''));
        $assortmentConfidence = max(0, min(100, absint($baseline['assortment_profile_confidence'] ?? 0)));
        if ($assortmentWasSubmitted) {
            $assortmentSource = 'manual';
            $assortmentConfidence = $assortmentProfile === self::ASSORTMENT_GENERAL ? 0 : 100;
        }

        $data = [
            'operational_email' => sanitize_email((string)(array_key_exists('operational_email', $payload) ? $payload['operational_email'] : $baseline['operational_email'])),
            'operational_phone' => sanitize_text_field((string)(array_key_exists('operational_phone', $payload) ? $payload['operational_phone'] : $baseline['operational_phone'])),
            'responsible_name' => sanitize_text_field((string)(array_key_exists('responsible_name', $payload) ? $payload['responsible_name'] : $baseline['responsible_name'])),
            'store_format' => $storeFormat,
            'assortment_profile' => $assortmentProfile,
            'assortment_profile_source' => $assortmentSource,
            'assortment_profile_confidence' => $assortmentConfidence,
            'pending_status' => $pendingStatus,
            'pending_actions' => $pendingActions,
            'store_notes' => sanitize_textarea_field((string)(array_key_exists('store_notes', $payload) ? $payload['store_notes'] : $baseline['store_notes'])),
            'updated_by' => $actorUserId,
            'updated_at' => current_time('mysql'),
        ];

        if ($before) {
            $data['revision_no'] = $currentRevision + 1;
            $ok = $wpdb->update($table, $data, ['location_id' => $locationId]);
        } else {
            $data['location_id'] = $locationId;
            $data['revision_no'] = 1;
            $data['created_by'] = $actorUserId;
            $data['created_at'] = current_time('mysql');
            $ok = $wpdb->insert($table, $data);
        }
        if ($ok === false) {
            return new WP_Error('fieldflow_store_save_failed', $wpdb->last_error ?: 'Não foi possível guardar a ficha da loja.', ['status' => 500]);
        }

        $after = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE location_id=%d LIMIT 1", $locationId), ARRAY_A) ?: [];
        if (class_exists(AuditLog::class)) {
            AuditLog::record(
                $before ? 'location_profile.updated' : 'location_profile.created',
                'location_profile',
                $locationId,
                $before,
                $after,
                [
                    'actor_user_id' => $actorUserId,
                    'client_id' => (int)$scope['client_id'],
                    'project_id' => (int)$scope['project_id'],
                    'route_id' => $routeId,
                    'location_id' => $locationId,
                ],
                ['profile_revision' => (int)($after['revision_no'] ?? 1)]
            );
        }

        self::invalidateMobileCaches($locationId, $actorUserId);
        self::syncLocationSnapshotToAzure($locationId);
        return self::snapshot($locationId, (int)$scope['project_id'], $routeId, $actorUserId);
    }

    /**
     * Bulk read model for the offline bundle. Contexts must originate from
     * routes already returned by the authenticated mobile route controller.
     * Route access is rechecked once per unique route, then all store rows are
     * loaded in one query to avoid an N+1 performance penalty.
     */
    public static function bulkOfflineSnapshots(array $contexts, ?int $userId = null): array {
        global $wpdb;
        $userId = $userId ?: get_current_user_id();
        if (!$userId || !$contexts || !self::tableExists()) return [];
        $normal = [];
        $routeAccess = [];
        $routeEdit = [];
        foreach ($contexts as $locationId => $context) {
            $locationId = absint($locationId);
            $context = is_array($context) ? $context : [];
            $routeId = absint($context['route_id'] ?? 0);
            if (!$locationId || !$routeId) continue;
            if (!array_key_exists($routeId, $routeAccess)) $routeAccess[$routeId] = Permissions::can_access_route($routeId, $userId);
            if (!$routeAccess[$routeId]) continue;
            if (!array_key_exists($routeId, $routeEdit)) $routeEdit[$routeId] = Permissions::can_edit_route($routeId, $userId);
            $normal[$locationId] = [
                'route_id' => $routeId,
                'project_id' => absint($context['project_id'] ?? 0),
                'can_edit' => (bool)$routeEdit[$routeId],
            ];
        }
        if (!$normal) return [];
        $ids = array_keys($normal);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $px = $wpdb->prefix . 'routespro_';
        $operationalStatusFs2 = SubmissionStatusPolicy::operationalSql('fs2');
        $operationalDayFs2 = OperationalReportDayPolicy::sqlExpression('fs2');
        $canonicalLocationFs2 = DailySubmissionService::canonical_location_sql('fs2');
        $sql = "SELECT l.*,c.name AS category_name,sc.name AS subcategory_name,
                       sp.id AS profile_id,sp.operational_email,sp.operational_phone,sp.responsible_name,sp.store_format,sp.assortment_profile,sp.assortment_profile_source,sp.assortment_profile_confidence,sp.pending_status,sp.pending_actions,sp.store_notes,
                       sp.revision_no,sp.created_by,sp.updated_by,sp.created_at AS profile_created_at,sp.updated_at AS profile_updated_at,
                       ls.id AS last_submission_id,ls.user_id AS last_user_id,ls.submitted_at AS last_submitted_at,ls.report_day AS last_report_day,
                       ls.status AS last_submission_status,ls.route_id AS last_route_id,ls.route_stop_id AS last_route_stop_id,
                       vrs.status AS last_stop_status,vu.display_name AS last_user_name
                FROM {$px}locations l
                LEFT JOIN {$px}categories c ON c.id=l.category_id
                LEFT JOIN {$px}categories sc ON sc.id=l.subcategory_id
                LEFT JOIN {$px}location_profiles sp ON sp.location_id=l.id
                LEFT JOIN {$px}form_submissions ls ON ls.id=(SELECT fs2.id FROM {$px}form_submissions fs2 WHERE ({$canonicalLocationFs2})=l.id AND {$operationalStatusFs2} ORDER BY {$operationalDayFs2} DESC,COALESCE(fs2.submitted_at,'1970-01-01 00:00:00') DESC,fs2.id DESC LIMIT 1)
                LEFT JOIN {$px}route_stops vrs ON vrs.id=ls.route_stop_id
                LEFT JOIN {$wpdb->users} vu ON vu.ID=ls.user_id
                WHERE l.id IN ({$placeholders})";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$ids), ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $locationId = absint($row['id'] ?? 0);
            if (!$locationId || empty($normal[$locationId])) continue;
            $profile = self::emptyProfile($locationId);
            if (!empty($row['profile_id'])) {
                $profile = [
                    'id' => absint($row['profile_id']),
                    'location_id' => $locationId,
                    'operational_email' => (string)($row['operational_email'] ?? ''),
                    'operational_phone' => (string)($row['operational_phone'] ?? ''),
                    'responsible_name' => (string)($row['responsible_name'] ?? ''),
                    'store_format' => (string)($row['store_format'] ?? ''),
                    'assortment_profile' => (string)($row['assortment_profile'] ?? self::ASSORTMENT_GENERAL),
                    'assortment_profile_source' => (string)($row['assortment_profile_source'] ?? ''),
                    'assortment_profile_confidence' => absint($row['assortment_profile_confidence'] ?? 0),
                    'pending_status' => (string)($row['pending_status'] ?? self::PENDING_NONE),
                    'pending_actions' => (string)($row['pending_actions'] ?? ''),
                    'store_notes' => (string)($row['store_notes'] ?? ''),
                    'revision_no' => absint($row['revision_no'] ?? 0),
                    'created_by' => absint($row['created_by'] ?? 0),
                    'updated_by' => absint($row['updated_by'] ?? 0),
                    'created_at' => (string)($row['profile_created_at'] ?? ''),
                    'updated_at' => (string)($row['profile_updated_at'] ?? ''),
                ];
            }
            $profile = self::normaliseProfileForOutput($profile);
            $lastExists = !empty($row['last_submission_id']);
            $lastStatus = sanitize_key((string)($row['last_stop_status'] ?? '')) ?: sanitize_key((string)($row['last_submission_status'] ?? 'submitted'));
            $out[$locationId] = [
                'location' => self::normaliseLocationForOutput($row),
                'profile' => $profile,
                'profile_complete' => self::profileComplete($profile),
                'profile_state' => self::profileComplete($profile) ? 'complete' : 'incomplete',
                'pending' => [
                    'status' => (string)$profile['pending_status'],
                    'open' => (string)$profile['pending_status'] === self::PENDING_OPEN,
                    'count' => ((string)$profile['pending_status'] === self::PENDING_OPEN && trim((string)$profile['pending_actions']) !== '') ? 1 : 0,
                ],
                'last_visit' => [
                    'exists' => $lastExists,
                    'submission_id' => absint($row['last_submission_id'] ?? 0),
                    'submitted_at' => (string)($row['last_submitted_at'] ?? ''),
                    'report_day' => (string)($row['last_report_day'] ?? ''),
                    'user_id' => absint($row['last_user_id'] ?? 0),
                    'user_name' => sanitize_text_field((string)($row['last_user_name'] ?? '')),
                    'status' => $lastExists ? ($lastStatus ?: 'submitted') : 'never',
                    'submission_status' => sanitize_key((string)($row['last_submission_status'] ?? '')),
                    'route_id' => absint($row['last_route_id'] ?? 0),
                    'route_stop_id' => absint($row['last_route_stop_id'] ?? 0),
                ],
                'scope' => [
                    'client_id' => absint($row['client_id'] ?? 0),
                    'project_id' => (int)$normal[$locationId]['project_id'],
                    'route_id' => (int)$normal[$locationId]['route_id'],
                ],
                'permissions' => ['can_read' => true, 'can_edit' => !empty($normal[$locationId]['can_edit'])],
            ];
        }
        return $out;
    }

    public static function canRead(int $locationId, int $clientId = 0, int $projectId = 0, int $routeId = 0, ?int $userId = null): bool {
        global $wpdb;
        $userId = $userId ?: get_current_user_id();
        $locationId = absint($locationId);
        $clientId = absint($clientId);
        $projectId = absint($projectId);
        $routeId = absint($routeId);
        if (!$userId || !$locationId) return false;
        if (Permissions::is_manager($userId)) return true;
        if (!Permissions::can_access_front($userId)) return false;
        if ($routeId > 0 && self::routeContainsLocation($routeId, $locationId) && Permissions::can_access_route($routeId, $userId)) return true;

        // Direct master-data scope is sufficient only when the location really
        // carries a client/project scope. Never let an unscoped location become
        // globally readable merely because assert_scope_or_error(0, 0) is true.
        if (($clientId > 0 || $projectId > 0) && !is_wp_error(Permissions::assert_scope_or_error($clientId, $projectId, $userId))) {
            return true;
        }

        $px = $wpdb->prefix . 'routespro_';
        if ($wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$px}campaign_locations WHERE location_id=%d AND assigned_to=%d AND is_active=1 LIMIT 1",
            $locationId,
            $userId
        ))) return true;

        $campaigns = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT cl.project_id,p.client_id
             FROM {$px}campaign_locations cl
             LEFT JOIN {$px}projects p ON p.id=cl.project_id
             WHERE cl.location_id=%d AND cl.is_active=1",
            $locationId
        ), ARRAY_A) ?: [];
        foreach ($campaigns as $campaign) {
            $campaignProjectId = absint($campaign['project_id'] ?? 0);
            $campaignClientId = absint($campaign['client_id'] ?? 0);
            if (($campaignProjectId > 0 || $campaignClientId > 0)
                && !is_wp_error(Permissions::assert_scope_or_error($campaignClientId, $campaignProjectId, $userId))) {
                return true;
            }
        }

        return (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT 1
             FROM {$px}route_stops rs
             INNER JOIN {$px}routes r ON r.id=rs.route_id
             LEFT JOIN {$px}assignments a ON a.route_id=r.id AND a.user_id=%d AND a.is_active=1
             WHERE rs.location_id=%d AND (r.owner_user_id=%d OR a.user_id=%d)
             LIMIT 1",
            $userId,
            $locationId,
            $userId,
            $userId
        ));
    }

    public static function canWrite(int $locationId, int $projectId = 0, int $routeId = 0, ?int $userId = null): bool {
        global $wpdb;
        $userId = $userId ?: get_current_user_id();
        $locationId = absint($locationId);
        $projectId = absint($projectId);
        $routeId = absint($routeId);
        if (!$userId || !$locationId) return false;
        if (Permissions::is_manager($userId)) return true;
        if (!Permissions::can_access_front($userId)) return false;

        if ($routeId > 0 && self::routeContainsLocation($routeId, $locationId) && Permissions::can_edit_route($routeId, $userId)) {
            return true;
        }

        $px = $wpdb->prefix . 'routespro_';
        $campaignSql = "SELECT 1 FROM {$px}campaign_locations cl WHERE cl.location_id=%d AND cl.assigned_to=%d AND cl.is_active=1";
        $campaignArgs = [$locationId, $userId];
        if ($projectId > 0) {
            $campaignSql .= ' AND cl.project_id=%d';
            $campaignArgs[] = $projectId;
        }
        $campaignSql .= ' LIMIT 1';
        if ($wpdb->get_var($wpdb->prepare($campaignSql, ...$campaignArgs))) return true;

        $routeSql = "SELECT 1
                     FROM {$px}route_stops rs
                     INNER JOIN {$px}routes r ON r.id=rs.route_id
                     LEFT JOIN {$px}assignments a ON a.route_id=r.id AND a.user_id=%d AND a.is_active=1
                     WHERE rs.location_id=%d AND (r.owner_user_id=%d OR a.user_id=%d)";
        $routeArgs = [$userId, $locationId, $userId, $userId];
        if ($projectId > 0) {
            $routeSql .= ' AND r.project_id=%d';
            $routeArgs[] = $projectId;
        }
        $routeSql .= ' LIMIT 1';
        return (bool)$wpdb->get_var($wpdb->prepare($routeSql, ...$routeArgs));
    }

    public static function assortmentSelection(int $locationId): array {
        global $wpdb;
        $locationId = absint($locationId);
        if (!$locationId || !self::tableExists()) return ['store_format'=>'','assortment_profile'=>self::ASSORTMENT_GENERAL,'source'=>'','confidence'=>0];
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT store_format,assortment_profile,assortment_profile_source,assortment_profile_confidence FROM ' . self::tableName() . ' WHERE location_id=%d LIMIT 1',
            $locationId
        ), ARRAY_A) ?: [];
        return [
            'store_format' => sanitize_text_field((string)($row['store_format'] ?? '')),
            'assortment_profile' => self::normaliseAssortmentProfile((string)($row['assortment_profile'] ?? self::ASSORTMENT_GENERAL)),
            'source' => sanitize_key((string)($row['assortment_profile_source'] ?? '')),
            'confidence' => max(0, min(100, absint($row['assortment_profile_confidence'] ?? 0))),
        ];
    }

    public static function normaliseAssortmentProfile(string $value): string {
        $value = trim($value);
        if ($value === '') return self::ASSORTMENT_GENERAL;
        if (class_exists('\RoutesPro\Forms\ProductCardex') && method_exists('\RoutesPro\Forms\ProductCardex', 'normalise_profile_code')) {
            return \RoutesPro\Forms\ProductCardex::normalise_profile_code($value);
        }
        $key = sanitize_key(str_replace(['.', ' '], ['_', '_'], strtolower(remove_accents($value))));
        return $key !== '' ? $key : self::ASSORTMENT_GENERAL;
    }

    private static function assortmentContext(int $locationId, int $clientId, int $projectId, string $selected): array {
        $selected = self::normaliseAssortmentProfile($selected);
        if (class_exists('\RoutesPro\Forms\ProductCardex') && method_exists('\RoutesPro\Forms\ProductCardex', 'assortment_profile_context')) {
            return \RoutesPro\Forms\ProductCardex::assortment_profile_context($locationId, $clientId, $projectId, $selected);
        }
        return [
            'cardex_id' => 0,
            'cardex_name' => '',
            'insignia' => '',
            'selected' => $selected,
            'selected_label' => $selected === self::ASSORTMENT_GENERAL ? 'Geral' : strtoupper($selected),
            'options' => [['value'=>self::ASSORTMENT_GENERAL,'label'=>'Geral, Cardex completo']],
            'cumulative' => false,
        ];
    }

    private static function syncLocationSnapshotToAzure(int $locationId): void {
        if (!class_exists('\RoutesPro\Cloud\AzureConnectionManager') || !\RoutesPro\Cloud\AzureConnectionManager::syncEnabled()) return;
        if (!class_exists('\RoutesPro\Cloud\AzureSyncFoundation') || !class_exists('\RoutesPro\Cloud\OperationalBackfill')) return;
        $settings = \RoutesPro\Cloud\AzureConnectionManager::settings();
        if (empty($settings['azure_cloud_sync_visits'])) return;
        $location = \RoutesPro\Cloud\OperationalBackfill::buildLocationContract($locationId, false);
        if (empty($location['ok'])) return;
        $eventUuid = \RoutesPro\Cloud\OperationalBackfill::relatedEventUuid(wp_generate_uuid4(), 'location.profile.snapshot', $locationId);
        \RoutesPro\Cloud\AzureSyncFoundation::enqueue('location.snapshot', (array)$location['contract'], (array)$location['context'], $eventUuid);
    }

    public static function profileComplete(array $profile): bool {
        $responsible = trim((string)($profile['responsible_name'] ?? ''));
        $phone = trim((string)($profile['operational_phone'] ?? ''));
        $email = trim((string)($profile['operational_email'] ?? ''));
        return $responsible !== '' && ($phone !== '' || $email !== '');
    }

    private static function invalidateMobileCaches(int $locationId, int $actorUserId): void {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $userIds = [$actorUserId => $actorUserId];
        $assigned = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT assigned_to FROM {$px}campaign_locations WHERE location_id=%d AND assigned_to>0 AND is_active=1",
            $locationId
        )) ?: [];
        foreach ($assigned as $id) { $id = absint($id); if ($id) $userIds[$id] = $id; }
        $routeUsers = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM (
                SELECT r.owner_user_id AS user_id FROM {$px}route_stops rs INNER JOIN {$px}routes r ON r.id=rs.route_id WHERE rs.location_id=%d AND r.owner_user_id>0
                UNION
                SELECT a.user_id FROM {$px}route_stops rs INNER JOIN {$px}assignments a ON a.route_id=rs.route_id AND a.is_active=1 WHERE rs.location_id=%d AND a.user_id>0
             ) users",
            $locationId,
            $locationId
        )) ?: [];
        foreach ($routeUsers as $id) { $id = absint($id); if ($id) $userIds[$id] = $id; }
        foreach ($userIds as $id) {
            $generation = max(1, absint(get_user_meta($id, '_fieldflow_route_cache_generation', true)));
            update_user_meta($id, '_fieldflow_route_cache_generation', $generation + 1);
        }
    }

    private static function routeContainsLocation(int $routeId, int $locationId): bool {
        global $wpdb;
        if ($routeId <= 0 || $locationId <= 0) return false;
        $px = $wpdb->prefix . 'routespro_';
        return (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$px}route_stops WHERE route_id=%d AND location_id=%d LIMIT 1",
            $routeId,
            $locationId
        ));
    }

    private static function resolvedScope(array $location, int $projectId, int $routeId): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $clientId = absint($location['client_id'] ?? 0);
        $resolvedProjectId = $projectId ?: absint($location['project_id'] ?? 0);
        if ($routeId > 0) {
            $route = $wpdb->get_row($wpdb->prepare("SELECT client_id,project_id FROM {$px}routes WHERE id=%d LIMIT 1", $routeId), ARRAY_A);
            if ($route) {
                $clientId = absint($route['client_id'] ?? $clientId);
                $resolvedProjectId = absint($route['project_id'] ?? $resolvedProjectId);
            }
        }
        if ($resolvedProjectId > 0) {
            $projectClient = absint($wpdb->get_var($wpdb->prepare("SELECT client_id FROM {$px}projects WHERE id=%d LIMIT 1", $resolvedProjectId)));
            if ($projectClient > 0) $clientId = $projectClient;
        }
        return ['client_id' => $clientId, 'project_id' => $resolvedProjectId];
    }

    private static function lastVisit(int $locationId, int $projectId = 0): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $canonicalLocation = DailySubmissionService::canonical_location_sql('s');
        $where = ["({$canonicalLocation})=%d", SubmissionStatusPolicy::operationalSql('s')];
        $args = [$locationId];
        if ($projectId > 0) {
            $where[] = 's.project_id=%d';
            $args[] = $projectId;
        }
        $sql = "SELECT s.id,s.form_id,s.user_id,s.submitted_at,s.report_day,s.status AS submission_status,
                       s.route_id,s.route_stop_id,rs.status AS stop_status,u.display_name
                FROM {$px}form_submissions s
                LEFT JOIN {$px}route_stops rs ON rs.id=s.route_stop_id
                LEFT JOIN {$wpdb->users} u ON u.ID=s.user_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY " . OperationalReportDayPolicy::sqlExpression('s') . " DESC,COALESCE(s.submitted_at,'1970-01-01 00:00:00') DESC,s.id DESC LIMIT 1";
        $row = $wpdb->get_row($wpdb->prepare($sql, ...$args), ARRAY_A);
        if (!$row) {
            return [
                'exists' => false,
                'submission_id' => 0,
                'submitted_at' => '',
                'report_day' => '',
                'user_id' => 0,
                'user_name' => '',
                'status' => 'never',
                'route_id' => 0,
                'route_stop_id' => 0,
            ];
        }
        $stopStatus = sanitize_key((string)($row['stop_status'] ?? ''));
        $submissionStatus = sanitize_key((string)($row['submission_status'] ?? 'submitted'));
        return [
            'exists' => true,
            'submission_id' => (int)($row['id'] ?? 0),
            'submitted_at' => (string)($row['submitted_at'] ?? ''),
            'report_day' => (string)($row['report_day'] ?? ''),
            'user_id' => (int)($row['user_id'] ?? 0),
            'user_name' => sanitize_text_field((string)($row['display_name'] ?? '')),
            'status' => $stopStatus !== '' ? $stopStatus : $submissionStatus,
            'submission_status' => $submissionStatus,
            'route_id' => (int)($row['route_id'] ?? 0),
            'route_stop_id' => (int)($row['route_stop_id'] ?? 0),
        ];
    }

    private static function emptyProfile(int $locationId): array {
        return [
            'id' => 0,
            'location_id' => $locationId,
            'operational_email' => '',
            'operational_phone' => '',
            'responsible_name' => '',
            'store_format' => '',
            'assortment_profile' => self::ASSORTMENT_GENERAL,
            'assortment_profile_source' => '',
            'assortment_profile_confidence' => 0,
            'pending_status' => self::PENDING_NONE,
            'pending_actions' => '',
            'store_notes' => '',
            'revision_no' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => '',
            'updated_at' => '',
        ];
    }

    private static function normaliseProfileForOutput(array $profile): array {
        $profile = array_merge(self::emptyProfile(absint($profile['location_id'] ?? 0)), $profile);
        $status = sanitize_key((string)$profile['pending_status']);
        if (!in_array($status, [self::PENDING_NONE, self::PENDING_OPEN, self::PENDING_RESOLVED], true)) $status = self::PENDING_NONE;
        $updatedBy = absint($profile['updated_by'] ?? 0);
        $updatedName = '';
        if ($updatedBy > 0) {
            $user = get_userdata($updatedBy);
            if ($user) $updatedName = sanitize_text_field((string)$user->display_name);
        }
        return [
            'id' => absint($profile['id'] ?? 0),
            'location_id' => absint($profile['location_id'] ?? 0),
            'operational_email' => sanitize_email((string)($profile['operational_email'] ?? '')),
            'operational_phone' => sanitize_text_field((string)($profile['operational_phone'] ?? '')),
            'responsible_name' => sanitize_text_field((string)($profile['responsible_name'] ?? '')),
            'store_format' => sanitize_text_field((string)($profile['store_format'] ?? '')),
            'assortment_profile' => self::normaliseAssortmentProfile((string)($profile['assortment_profile'] ?? self::ASSORTMENT_GENERAL)),
            'assortment_profile_source' => sanitize_key((string)($profile['assortment_profile_source'] ?? '')),
            'assortment_profile_confidence' => max(0, min(100, absint($profile['assortment_profile_confidence'] ?? 0))),
            'pending_status' => $status,
            'pending_actions' => sanitize_textarea_field((string)($profile['pending_actions'] ?? '')),
            'store_notes' => sanitize_textarea_field((string)($profile['store_notes'] ?? '')),
            'revision_no' => max(0, absint($profile['revision_no'] ?? 0)),
            'created_by' => absint($profile['created_by'] ?? 0),
            'updated_by' => $updatedBy,
            'updated_by_name' => $updatedName,
            'created_at' => sanitize_text_field((string)($profile['created_at'] ?? '')),
            'updated_at' => sanitize_text_field((string)($profile['updated_at'] ?? '')),
        ];
    }

    private static function normaliseLocationForOutput(array $location): array {
        $keys = [
            'id','client_id','project_id','name','address','district','county','city','parish','postal_code','country',
            'category_id','subcategory_id','category_name','subcategory_name','contact_person','phone','email','website',
            'place_id','source','external_ref','location_type','is_active','is_validated','updated_at'
        ];
        $out = [];
        foreach ($keys as $key) $out[$key] = $location[$key] ?? '';
        foreach (['id','client_id','project_id','category_id','subcategory_id','is_active','is_validated'] as $key) $out[$key] = absint($out[$key]);
        foreach (array_diff($keys, ['id','client_id','project_id','category_id','subcategory_id','is_active','is_validated']) as $key) {
            $out[$key] = sanitize_text_field((string)$out[$key]);
        }
        return $out;
    }
}
