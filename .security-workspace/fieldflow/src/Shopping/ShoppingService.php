<?php
namespace RoutesPro\Shopping;

use RoutesPro\Admin\Emails;
use RoutesPro\Mapping\MappingEngine;
use RoutesPro\Support\AssignmentResolver;
use RoutesPro\Stocks\StockService;
use RoutesPro\Stores\StoreIdentityAuthority;

if (!defined('ABSPATH')) exit;

final class ShoppingService {
    private const SCHEMA_VERSION = '1.3.0';
    private const SCHEMA_OPTION = 'fieldflow_shopping_schema_version';
    private const TEMPLATE_OPTION = 'fieldflow_shopping_email_template';
    private static array $recipientDirectoryCache = [];

    public static function registerHooks(): void {
        add_action('plugins_loaded', [self::class, 'ensureSchema'], 5);
    }

    public static function batchesTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_shopping_batches'; }
    public static function assignmentsTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_shopping_assignments'; }
    public static function submissionsTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_shopping_submissions'; }
    public static function insigniaLocationsTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_shopping_insignia_locations'; }

    public static function ensureSchema(bool $force = false): void {
        global $wpdb;
        $batches = self::batchesTable();
        $assignments = self::assignmentsTable();
        $submissions = self::submissionsTable();
        $insigniaLocations = self::insigniaLocationsTable();
        $ready = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $batches)) === $batches
            && (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $assignments)) === $assignments
            && (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $submissions)) === $submissions
            && (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $insigniaLocations)) === $insigniaLocations;
        if (!$force && $ready && (string)get_option(self::SCHEMA_OPTION, '') === self::SCHEMA_VERSION) return;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$batches} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            week_no TINYINT UNSIGNED NOT NULL DEFAULT 0,
            year_no SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            source_sheet VARCHAR(191) NULL,
            source_file_name VARCHAR(255) NULL,
            price_note VARCHAR(255) NULL,
            products_json LONGTEXT NOT NULL,
            email_subject VARCHAR(255) NOT NULL,
            email_body LONGTEXT NULL,
            extra_emails TEXT NULL,
            notify_client TINYINT(1) NOT NULL DEFAULT 1,
            recipient_user_ids LONGTEXT NULL,
            attach_xlsx TINYINT(1) NOT NULL DEFAULT 1,
            attach_pdf TINYINT(1) NOT NULL DEFAULT 1,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            created_by BIGINT UNSIGNED NULL,
            imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY status_idx (status),
            KEY period_idx (year_no,week_no)
        ) {$charset};");
        dbDelta("CREATE TABLE {$assignments} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_id BIGINT UNSIGNED NOT NULL,
            source_store_name VARCHAR(255) NOT NULL,
            insignia VARCHAR(191) NULL,
            location_id BIGINT UNSIGNED NULL,
            last_location_id BIGINT UNSIGNED NULL,
            assigned_user_id BIGINT UNSIGNED NULL,
            mapping_confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
            price_seed_json LONGTEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            last_submission_id BIGINT UNSIGNED NULL,
            submitted_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY batch_store_idx (batch_id,source_store_name(150)),
            KEY batch_idx (batch_id),
            KEY location_idx (location_id),
            KEY last_location_idx (last_location_id),
            KEY user_idx (assigned_user_id),
            KEY status_idx (status)
        ) {$charset};");
        dbDelta("CREATE TABLE {$submissions} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_id BIGINT UNSIGNED NOT NULL,
            assignment_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NULL,
            insignia VARCHAR(191) NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'submitted',
            prices_json LONGTEXT NOT NULL,
            notes LONGTEXT NULL,
            notification_status VARCHAR(32) NOT NULL DEFAULT 'pending',
            notification_recipients TEXT NULL,
            notification_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            notification_last_sent_at DATETIME NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY assignment_idx (assignment_id),
            KEY batch_idx (batch_id),
            KEY scope_idx (client_id,project_id),
            KEY user_idx (user_id),
            KEY submitted_idx (submitted_at)
        ) {$charset};");
        dbDelta("CREATE TABLE {$insigniaLocations} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            insignia VARCHAR(191) NOT NULL,
            match_source VARCHAR(48) NOT NULL DEFAULT 'manual',
            confidence TINYINT UNSIGNED NOT NULL DEFAULT 100,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY project_location_idx (project_id,location_id),
            KEY scope_insignia_idx (client_id,project_id,insignia(120)),
            KEY location_idx (location_id)
        ) {$charset};");
        // Shopping 1.2.x turns the imported store header into an insignia scope.
        // Legacy assignments are normalised and then safely consolidated by batch + insignia.
        $legacyAssignments = $wpdb->get_results("SELECT id,source_store_name,insignia,location_id,last_location_id FROM {$assignments} ORDER BY id ASC", ARRAY_A) ?: [];
        foreach ($legacyAssignments as $legacyAssignment) {
            $insignia = self::canonicalInsigniaLabel((string)($legacyAssignment['insignia'] ?? $legacyAssignment['source_store_name'] ?? ''));
            $lastLocationId = (int)($legacyAssignment['last_location_id'] ?? 0);
            if (!$lastLocationId) $lastLocationId = (int)($legacyAssignment['location_id'] ?? 0);
            $wpdb->update($assignments, [
                'insignia'=>$insignia !== '' ? $insignia : sanitize_text_field((string)($legacyAssignment['source_store_name'] ?? '')),
                'last_location_id'=>$lastLocationId ?: null,
            ], ['id'=>(int)$legacyAssignment['id']], ['%s','%d'], ['%d']);
        }
        $wpdb->query("UPDATE {$submissions} s INNER JOIN {$assignments} a ON a.id=s.assignment_id SET s.insignia=COALESCE(NULLIF(a.insignia,''),a.source_store_name) WHERE s.insignia IS NULL OR s.insignia=''");
        self::consolidateLegacyInsigniaAssignments();
        // Shopping 1.2.1 is project-team scoped. The assignment itself is never owned
        // by one merchandiser. Submission.user_id remains the historical executor.
        $wpdb->query("UPDATE {$assignments} SET assigned_user_id=NULL WHERE assigned_user_id IS NOT NULL AND assigned_user_id<>0");
        $wpdb->query("UPDATE {$submissions} SET notification_attempts=1 WHERE notification_attempts=0 AND notification_status IN ('sent','partial','failed')");
        self::reconcileActiveAssignments();
        self::backfillInsigniaLocationMappings();
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
    }

    public static function defaultTemplate(): array {
        return [
            'subject' => '[FieldFlow] Shopping {insignia}, {store_name}, semana {week}',
            'body' => '<p>Olá,</p><p>Foi concluída uma nova verificação de preços no âmbito de <strong>{project_name}</strong>.</p><p><strong>Insígnia:</strong> {insignia}<br><strong>Loja:</strong> {store_name}<br><strong>Merchandiser:</strong> {merchandiser_name}<br><strong>Período:</strong> Semana {week}, {year}<br><strong>Submetido em:</strong> {submitted_at}</p><h3 style="margin:24px 0 10px">Preços verificados</h3>{prices_table}{notes_block}<p style="margin-top:24px">Cumprimentos,<br>Equipa FieldFlow</p>',
            'extra_emails' => '',
            'notify_client' => 1,
            'notify_client_users' => 1,
            'attach_xlsx' => 1,
            'attach_pdf' => 1,
            'brand_primary' => '#172033',
            'footer_text' => 'Notificação automática de verificação de preços gerada pelo FieldFlow.',
        ];
    }

    public static function getTemplate(): array {
        $saved = get_option(self::TEMPLATE_OPTION, []);
        return array_merge(self::defaultTemplate(), is_array($saved) ? $saved : []);
    }

    public static function saveTemplate(array $input): void {
        $template = self::getTemplate();
        $template['subject'] = sanitize_text_field((string)($input['subject'] ?? $template['subject']));
        $template['body'] = wp_kses_post((string)($input['body'] ?? $template['body']));
        $template['extra_emails'] = self::sanitiseEmailList((string)($input['extra_emails'] ?? ''));
        $template['notify_client'] = !empty($input['notify_client']) ? 1 : 0;
        $template['notify_client_users'] = !empty($input['notify_client_users']) ? 1 : 0;
        $template['attach_xlsx'] = !empty($input['attach_xlsx']) ? 1 : 0;
        $template['attach_pdf'] = !empty($input['attach_pdf']) ? 1 : 0;
        $template['brand_primary'] = sanitize_hex_color((string)($input['brand_primary'] ?? '')) ?: '#172033';
        $template['footer_text'] = sanitize_text_field((string)($input['footer_text'] ?? ''));
        update_option(self::TEMPLATE_OPTION, $template, false);
    }

    public static function importWorkbook(int $clientId, int $projectId, string $path, string $sourceName = '', array $overrides = []): int {
        self::ensureSchema();
        if (!$clientId || !$projectId) throw new \RuntimeException('Seleciona o cliente e o projeto da verificação Shopping.');
        if (!is_file($path) || !is_readable($path)) throw new \RuntimeException('O ficheiro temporário deixou de estar disponível para leitura.');

        global $wpdb;
        $projectsTable = $wpdb->prefix . 'routespro_projects';
        $projectClientId = (int)$wpdb->get_var($wpdb->prepare("SELECT client_id FROM {$projectsTable} WHERE id=%d LIMIT 1", $projectId));
        if (!$projectClientId || $projectClientId !== $clientId) throw new \RuntimeException('O projeto selecionado não pertence ao cliente indicado.');

        $parsed = ShoppingImporter::parse($path);
        if (empty($parsed['products']) || empty($parsed['stores'])) throw new \RuntimeException('O Excel não contém artigos e insígnias suficientes para criar os formulários.');
        $parsed['stores'] = self::normaliseParsedInsignias((array)$parsed['stores']);
        if (!$parsed['stores']) throw new \RuntimeException('Não foi possível identificar nenhuma insígnia válida no ficheiro Shopping.');
        $template = self::getTemplate();
        $defaultRecipientUserIds = !empty($template['notify_client_users']) ? self::clientRecipientUserIds($clientId) : [];
        $title = sanitize_text_field((string)($overrides['title'] ?? $parsed['title']));
        if ($title === '') $title = 'Verificação de preços Shopping';

        $batchId = 0;
        $transactionStarted = $wpdb->query('START TRANSACTION') !== false;
        try {
            $inserted = $wpdb->insert(self::batchesTable(), [
                'client_id' => $clientId,
                'project_id' => $projectId,
                'title' => $title,
                'week_no' => absint($overrides['week_no'] ?? $parsed['week_no']),
                'year_no' => absint($overrides['year_no'] ?? $parsed['year_no']),
                'source_sheet' => sanitize_text_field((string)$parsed['sheet_name']),
                'source_file_name' => sanitize_file_name($sourceName),
                'price_note' => sanitize_text_field((string)$parsed['price_note']),
                'products_json' => wp_json_encode($parsed['products'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'email_subject' => sanitize_text_field((string)$template['subject']),
                'email_body' => wp_kses_post((string)$template['body']),
                'extra_emails' => (string)$template['extra_emails'],
                'notify_client' => !empty($template['notify_client']) ? 1 : 0,
                'recipient_user_ids' => wp_json_encode($defaultRecipientUserIds),
                'attach_xlsx' => !empty($template['attach_xlsx']) ? 1 : 0,
                'attach_pdf' => !empty($template['attach_pdf']) ? 1 : 0,
                'status' => 'active',
                'created_by' => get_current_user_id() ?: null,
            ], ['%d','%d','%s','%d','%d','%s','%s','%s','%s','%s','%s','%s','%d','%s','%d','%d','%s','%d']);
            if (!$inserted) throw new \RuntimeException(self::databaseError('Não foi possível criar o lote Shopping.'));
            $batchId = (int)$wpdb->insert_id;

            $projectUserIds = self::projectMerchandiserIds($projectId);
            if (!$projectUserIds) {
                throw new \RuntimeException('O projeto selecionado não tem merchandisers em Intelligence Center > Projetos > Utilizadores com acesso ao projeto.');
            }
            foreach ((array)$parsed['stores'] as $store) {
                $insignia = self::canonicalInsigniaLabel((string)($store['insignia'] ?? $store['name'] ?? ''));
                if ($insignia === '') continue;
                $assignedUserId = 0;
                $assignmentInserted = $wpdb->insert(self::assignmentsTable(), [
                    'batch_id' => $batchId,
                    'source_store_name' => $insignia,
                    'insignia' => $insignia,
                    'location_id' => null,
                    'last_location_id' => null,
                    'assigned_user_id' => $assignedUserId ?: null,
                    'mapping_confidence' => 100,
                    'price_seed_json' => wp_json_encode((array)$store['prices'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'status' => 'pending',
                ], ['%d','%s','%s','%d','%d','%d','%d','%s','%s']);
                if (!$assignmentInserted) throw new \RuntimeException(self::databaseError('Não foi possível criar o formulário da insígnia ' . $insignia . '.'));
            }

            if ($transactionStarted) $wpdb->query('COMMIT');
            return $batchId;
        } catch (\Throwable $error) {
            if ($transactionStarted) $wpdb->query('ROLLBACK');
            if ($batchId > 0) {
                $wpdb->delete(self::submissionsTable(), ['batch_id'=>$batchId], ['%d']);
                $wpdb->delete(self::assignmentsTable(), ['batch_id'=>$batchId], ['%d']);
                $wpdb->delete(self::batchesTable(), ['id'=>$batchId], ['%d']);
            }
            throw $error;
        }
    }

    private static function matchStoreToLocation(string $storeName, array $locations, array $projectUserIds = []): array {
        $source = self::normaliseMatchText($storeName);
        $sourceCompact = str_replace(' ', '', $source);
        if ($source === '' || !$locations) return ['location_id'=>0,'assigned_user_id'=>0,'confidence'=>0];

        foreach ($locations as $location) {
            $candidate = self::normaliseMatchText((string)($location['name'] ?? ''));
            if ($candidate !== '' && $candidate === $source) {
                return [
                    'location_id'=>(int)($location['id'] ?? 0),
                    'assigned_user_id'=>self::resolveProjectUserId((int)($location['assigned_to'] ?? 0), $projectUserIds),
                    'confidence'=>100,
                ];
            }
            if ($candidate !== '' && str_replace(' ', '', $candidate) === $sourceCompact) {
                return [
                    'location_id'=>(int)($location['id'] ?? 0),
                    'assigned_user_id'=>self::resolveProjectUserId((int)($location['assigned_to'] ?? 0), $projectUserIds),
                    'confidence'=>97,
                ];
            }
        }

        $candidatePool = $locations;
        if (count($locations) > 800) {
            $sourceTokens = array_values(array_unique(array_filter(explode(' ', $source), static fn($token) => strlen($token) >= 3)));
            $ranked = [];
            foreach ($locations as $location) {
                $candidate = self::normaliseMatchText((string)($location['name'] ?? '') . ' ' . (string)($location['city'] ?? '') . ' ' . (string)($location['external_ref'] ?? ''));
                if ($candidate === '') continue;
                $score = 0;
                if (strpos($candidate, $source) !== false || strpos($source, self::normaliseMatchText((string)($location['name'] ?? ''))) !== false) $score += 80;
                foreach ($sourceTokens as $token) if (strpos($candidate, $token) !== false) $score += 12;
                if ($score > 0) $ranked[] = ['score'=>$score,'location'=>$location];
            }
            usort($ranked, static fn($left, $right) => ((int)$right['score']) <=> ((int)$left['score']));
            $candidatePool = array_map(static fn($row) => $row['location'], array_slice($ranked, 0, 250));
            if (!$candidatePool) $candidatePool = array_slice($locations, 0, 250);
        }

        $candidates = MappingEngine::storeCandidates([
            'source_label' => $storeName,
            'external_ref' => '',
            'meta_json' => [],
        ], $candidatePool, 3);
        if (!$candidates) return ['location_id'=>0,'assigned_user_id'=>0,'confidence'=>0];

        $top = $candidates[0];
        $confidence = absint($top['confidence'] ?? 0);
        $locationId = $confidence >= 70 ? absint($top['target_id'] ?? 0) : 0;
        $assignedUserId = 0;
        if ($locationId) {
            foreach ($locations as $location) {
                if ((int)($location['id'] ?? 0) === $locationId) {
                    $assignedUserId = self::resolveProjectUserId((int)($location['assigned_to'] ?? 0), $projectUserIds);
                    break;
                }
            }
        }
        return ['location_id'=>$locationId,'assigned_user_id'=>$assignedUserId,'confidence'=>$confidence];
    }

    private static function normaliseParsedInsignias(array $stores): array {
        $grouped = [];
        foreach ($stores as $store) {
            if (!is_array($store)) continue;
            $rawName = sanitize_text_field((string)($store['name'] ?? ''));
            $insignia = self::canonicalInsigniaLabel($rawName);
            if ($insignia === '') continue;
            $keyValue = function_exists('remove_accents') ? remove_accents($insignia) : $insignia;
            $key = sanitize_key($keyValue);
            if ($key === '') $key = substr(hash('sha256', $insignia), 0, 16);
            $prices = is_array($store['prices'] ?? null) ? $store['prices'] : [];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'name'=>$insignia,
                    'insignia'=>$insignia,
                    'prices'=>$prices,
                    'source_names'=>[$rawName],
                ];
                continue;
            }
            $grouped[$key]['source_names'][] = $rawName;
            $current = is_array($grouped[$key]['prices'] ?? null) ? $grouped[$key]['prices'] : [];
            $productKeys = array_values(array_unique(array_merge(array_keys($current), array_keys($prices))));
            $merged = [];
            foreach ($productKeys as $productKey) {
                $left = is_array($current[$productKey] ?? null) ? $current[$productKey] : [];
                $right = is_array($prices[$productKey] ?? null) ? $prices[$productKey] : [];
                $leftRegular = $left['regular'] ?? null;
                $rightRegular = $right['regular'] ?? null;
                $leftPromo = $left['promo'] ?? null;
                $rightPromo = $right['promo'] ?? null;
                $merged[$productKey] = [
                    'regular'=>$leftRegular === $rightRegular ? $leftRegular : null,
                    'promo'=>$leftPromo === $rightPromo ? $leftPromo : null,
                ];
            }
            $grouped[$key]['prices'] = $merged;
        }
        return array_values($grouped);
    }

    private static function consolidateLegacyInsigniaAssignments(): void {
        global $wpdb;
        $assignments = self::assignmentsTable();
        $submissions = self::submissionsTable();
        $rows = $wpdb->get_results("SELECT * FROM {$assignments} ORDER BY batch_id ASC,id ASC", ARRAY_A) ?: [];
        if (!$rows) return;

        $groups = [];
        foreach ($rows as $row) {
            $batchId = (int)($row['batch_id'] ?? 0);
            $insignia = self::canonicalInsigniaLabel((string)($row['insignia'] ?? $row['source_store_name'] ?? ''));
            if ($batchId <= 0 || $insignia === '') continue;
            $keyValue = function_exists('remove_accents') ? remove_accents($insignia) : $insignia;
            $groupKey = sanitize_key($keyValue);
            if ($groupKey === '') $groupKey = substr(hash('sha256', $insignia), 0, 16);
            $key = $batchId . ':' . $groupKey;
            if (!isset($groups[$key])) $groups[$key] = ['insignia'=>$insignia,'rows'=>[]];
            $groups[$key]['rows'][] = $row;
        }

        foreach ($groups as $group) {
            $groupRows = (array)($group['rows'] ?? []);
            if (!$groupRows) continue;
            $insignia = (string)($group['insignia'] ?? '');
            $keeper = array_shift($groupRows);
            $keeperId = (int)($keeper['id'] ?? 0);
            if ($keeperId <= 0) continue;

            $seedJson = (string)($keeper['price_seed_json'] ?? '');
            $assignedUserId = (int)($keeper['assigned_user_id'] ?? 0);
            $hadDuplicates = !empty($groupRows);
            foreach ($groupRows as $duplicate) {
                $duplicateId = (int)($duplicate['id'] ?? 0);
                if ($duplicateId <= 0 || $duplicateId === $keeperId) continue;
                $duplicateSeed = (string)($duplicate['price_seed_json'] ?? '');
                if ($seedJson === '' && $duplicateSeed !== '') $seedJson = $duplicateSeed;
                if ((int)($duplicate['assigned_user_id'] ?? 0) !== $assignedUserId) $assignedUserId = 0;

                $wpdb->query($wpdb->prepare(
                    "UPDATE {$submissions} SET assignment_id=%d,insignia=%s WHERE assignment_id=%d",
                    $keeperId,
                    $insignia,
                    $duplicateId
                ));
                $wpdb->delete($assignments, ['id'=>$duplicateId], ['%d']);
            }

            $latest = $wpdb->get_row($wpdb->prepare(
                "SELECT id,location_id,submitted_at FROM {$submissions} WHERE assignment_id=%d ORDER BY submitted_at DESC,id DESC LIMIT 1",
                $keeperId
            ), ARRAY_A) ?: [];
            $update = [
                'source_store_name'=>$insignia,
                'insignia'=>$insignia,
                'location_id'=>null,
                'assigned_user_id'=>$hadDuplicates ? null : ($assignedUserId ?: null),
                // A seed composed from more than one legacy shop is deliberately cleared.
                // Store-specific values remain available through each historic submission.
                'price_seed_json'=>$hadDuplicates ? wp_json_encode([]) : $seedJson,
            ];
            $formats = ['%s','%s','%d','%d','%s'];
            if ($latest) {
                $update['last_submission_id'] = (int)$latest['id'];
                $update['last_location_id'] = (int)($latest['location_id'] ?? 0) ?: null;
                $update['submitted_at'] = (string)($latest['submitted_at'] ?? '') ?: null;
                $update['status'] = 'completed';
                array_push($formats, '%d','%d','%s','%s');
            } else {
                $lastLocationId = (int)($keeper['last_location_id'] ?? $keeper['location_id'] ?? 0);
                $update['last_location_id'] = $lastLocationId ?: null;
                $formats[] = '%d';
            }
            $wpdb->update($assignments, $update, ['id'=>$keeperId], $formats, ['%d']);
        }
    }

    public static function canonicalInsigniaLabel(string $value): string {
        $value = sanitize_text_field(trim($value));
        if ($value === '') return '';
        $match = self::normaliseMatchText($value);
        // Continente operates distinct banners that may have different Shopping scopes.
        // Keep them separate even though StockService intentionally groups them into one family.
        if ($match === 'mdl' || strpos($match, 'continente modelo') !== false) return 'Continente Modelo';
        if ($match === 'cbd' || strpos($match, 'continente bom dia') !== false || strpos($match, 'bom dia') === 0) return 'Continente Bom Dia';
        if ($match === 'cnt') return 'Continente';
        $family = class_exists(StockService::class) ? StockService::brandFamily($value) : '';
        $labels = [
            'pingodoce'=>'Pingo Doce',
            'continente'=>'Continente',
            'auchan'=>'Auchan',
            'recheio'=>'Recheio',
            'intermarche'=>'Intermarché',
            'minipreco'=>'Minipreço',
            'elcorteingles'=>'El Corte Inglés',
            'mercadona'=>'Mercadona',
            'lidl'=>'Lidl',
            'aldi'=>'Aldi',
            'froiz'=>'Froiz',
            'apolonia'=>'Apolónia',
            'makro'=>'Makro',
            'arcol'=>'Arcol',
            'mcunha'=>'M. Cunha',
            'eclerc'=>'E.Leclerc',
        ];
        if ($family !== '' && isset($labels[$family])) return $labels[$family];
        return $value;
    }

    private static function normaliseMatchText(string $value): string {
        $value = trim(preg_replace('/\s+/u', ' ', str_replace(["\xC2\xA0", "\u{00A0}"], ' ', $value)));
        if (function_exists('remove_accents')) $value = remove_accents($value);
        $value = strtolower($value);
        return trim((string)preg_replace('/[^a-z0-9]+/u', ' ', $value));
    }

    private static function databaseError(string $message): string {
        global $wpdb;
        $detail = trim((string)$wpdb->last_error);
        return $detail !== '' ? $message . ' Detalhe técnico: ' . $detail : $message;
    }

    public static function getBatch(int $batchId): array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::batchesTable() . ' WHERE id=%d', $batchId), ARRAY_A);
        return is_array($row) ? $row : [];
    }

    public static function listBatches(int $limit = 100): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        return $wpdb->get_results("SELECT b.*,c.name AS client_name,p.name AS project_name,
            (SELECT COUNT(*) FROM " . self::assignmentsTable() . " a WHERE a.batch_id=b.id) AS store_count,
            (SELECT COUNT(*) FROM " . self::assignmentsTable() . " a WHERE a.batch_id=b.id AND a.status='completed') AS completed_count,
            (SELECT COUNT(DISTINCT s.location_id) FROM " . self::submissionsTable() . " s WHERE s.batch_id=b.id AND s.status='submitted' AND s.location_id>0) AS submitted_store_count
            FROM " . self::batchesTable() . " b
            LEFT JOIN {$px}clients c ON c.id=b.client_id
            LEFT JOIN {$px}projects p ON p.id=b.project_id
            ORDER BY b.id DESC LIMIT " . max(1, min(500, $limit)), ARRAY_A) ?: [];
    }

    public static function assignmentsForBatch(int $batchId): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        return $wpdb->get_results($wpdb->prepare("SELECT a.*,
            COALESCE(NULLIF(a.insignia,''),a.source_store_name) AS insignia_label,
            l.name AS legacy_location_name,ll.name AS last_location_name,ll.city AS last_location_city,
            u.display_name AS assigned_user_name,
            (SELECT COUNT(DISTINCT s.location_id) FROM " . self::submissionsTable() . " s WHERE s.assignment_id=a.id AND s.status='submitted' AND s.location_id>0) AS submitted_store_count
            FROM " . self::assignmentsTable() . " a
            LEFT JOIN {$px}locations l ON l.id=a.location_id
            LEFT JOIN {$px}locations ll ON ll.id=a.last_location_id
            LEFT JOIN {$wpdb->users} u ON u.ID=a.assigned_user_id
            WHERE a.batch_id=%d ORDER BY insignia_label ASC,a.id ASC", $batchId), ARRAY_A) ?: [];
    }

    public static function saveBatch(int $batchId, array $input): void {
        global $wpdb;
        $batch = self::getBatch($batchId);
        if (!$batch) throw new \RuntimeException('Lote Shopping não encontrado.');
        $status = sanitize_key((string)($input['status'] ?? $batch['status']));
        if (!in_array($status, ['active','archived','draft'], true)) $status = 'active';
        $wpdb->update(self::batchesTable(), [
            'title' => sanitize_text_field((string)($input['title'] ?? $batch['title'])),
            'week_no' => max(0, min(53, absint($input['week_no'] ?? $batch['week_no']))),
            'year_no' => max(0, min(9999, absint($input['year_no'] ?? $batch['year_no']))),
            'email_subject' => sanitize_text_field((string)($input['email_subject'] ?? $batch['email_subject'])),
            'email_body' => wp_kses_post((string)($input['email_body'] ?? $batch['email_body'])),
            'extra_emails' => self::sanitiseEmailList((string)($input['extra_emails'] ?? $batch['extra_emails'])),
            'notify_client' => !empty($input['notify_client']) ? 1 : 0,
            'recipient_user_ids' => wp_json_encode(self::filterRecipientUserIds((int)$batch['client_id'], is_array($input['recipient_user_ids'] ?? null) ? $input['recipient_user_ids'] : [])),
            'attach_xlsx' => !empty($input['attach_xlsx']) ? 1 : 0,
            'attach_pdf' => !empty($input['attach_pdf']) ? 1 : 0,
            'status' => $status,
        ], ['id'=>$batchId], ['%s','%d','%d','%s','%s','%s','%d','%s','%d','%d','%s'], ['%d']);

        // Desde 1.2.1 o Shopping pertence sempre à equipa operacional do projeto.
        // Nunca fica atribuído a um merchandiser individual. A loja concreta é escolhida
        // em front, entre todas as lojas válidas da insígnia dentro do projeto.
        foreach (self::assignmentsForBatch($batchId) as $assignment) {
            $assignmentId = (int)$assignment['id'];
            $wpdb->update(self::assignmentsTable(), [
                'assigned_user_id' => null,
                'insignia' => self::canonicalInsigniaLabel((string)($assignment['insignia_label'] ?? $assignment['source_store_name'] ?? '')),
            ], ['id'=>$assignmentId], ['%d','%s'], ['%d']);
        }
    }

    public static function deleteBatch(int $batchId): void {
        global $wpdb;
        $wpdb->delete(self::submissionsTable(), ['batch_id'=>$batchId], ['%d']);
        $wpdb->delete(self::assignmentsTable(), ['batch_id'=>$batchId], ['%d']);
        $wpdb->delete(self::batchesTable(), ['id'=>$batchId], ['%d']);
    }

    public static function listAssignmentsForUser(int $userId, array $filters = []): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $where = ["b.status='active'"];
        $args = [];
        if (!empty($filters['client_id'])) { $where[] = 'b.client_id=%d'; $args[] = absint($filters['client_id']); }
        if (!empty($filters['project_id'])) { $where[] = 'b.project_id=%d'; $args[] = absint($filters['project_id']); }
        $sql = "SELECT a.id,a.batch_id,a.source_store_name,COALESCE(NULLIF(a.insignia,''),a.source_store_name) AS insignia,
            a.last_location_id,a.assigned_user_id,a.status,a.submitted_at,
            b.title,b.week_no,b.year_no,b.price_note,b.client_id,b.project_id,
            ll.name AS last_location_name,ll.city AS last_location_city,c.name AS client_name,p.name AS project_name,
            (SELECT COUNT(DISTINCT s.location_id) FROM " . self::submissionsTable() . " s WHERE s.assignment_id=a.id AND s.status='submitted' AND s.location_id>0) AS submitted_store_count
            FROM " . self::assignmentsTable() . " a
            INNER JOIN " . self::batchesTable() . " b ON b.id=a.batch_id
            LEFT JOIN {$px}locations ll ON ll.id=a.last_location_id
            LEFT JOIN {$px}clients c ON c.id=b.client_id
            LEFT JOIN {$px}projects p ON p.id=b.project_id
            WHERE " . implode(' AND ', $where) . " ORDER BY b.year_no DESC,b.week_no DESC,insignia ASC,a.id ASC";
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['batch_id'] = (int)$row['batch_id'];
            $row['last_location_id'] = (int)($row['last_location_id'] ?? 0);
            $row['assigned_user_id'] = (int)($row['assigned_user_id'] ?? 0);
            $row['submitted_store_count'] = (int)($row['submitted_store_count'] ?? 0);
        }
        unset($row);
        if (!current_user_can('routespro_manage')) {
            $projectUserCache = [];
            $rows = array_values(array_filter($rows, static function(array $row) use ($userId, &$projectUserCache): bool {
                $projectId = (int)($row['project_id'] ?? 0);
                if (!array_key_exists($projectId, $projectUserCache)) $projectUserCache[$projectId] = self::projectMerchandiserIds($projectId);
                return in_array($userId, $projectUserCache[$projectId], true);
            }));
        }
        return $rows;
    }

    public static function assignmentPayload(int $assignmentId, int $userId, int $locationId = 0): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $row = $wpdb->get_row($wpdb->prepare("SELECT a.*,b.title,b.week_no,b.year_no,b.price_note,b.products_json,b.client_id,b.project_id,b.status AS batch_status,
            c.name AS client_name,p.name AS project_name
            FROM " . self::assignmentsTable() . " a
            INNER JOIN " . self::batchesTable() . " b ON b.id=a.batch_id
            LEFT JOIN {$px}clients c ON c.id=b.client_id
            LEFT JOIN {$px}projects p ON p.id=b.project_id
            WHERE a.id=%d LIMIT 1", $assignmentId), ARRAY_A);
        if (!$row) throw new \RuntimeException('Verificação Shopping não encontrada.');
        if (!current_user_can('routespro_manage')) {
            $projectUsers = self::projectMerchandiserIds((int)($row['project_id'] ?? 0));
            if (!in_array($userId, $projectUsers, true)) {
                throw new \RuntimeException('Este Shopping está disponível apenas para a equipa operacional com acesso a este projeto.');
            }
        }
        if ((string)$row['batch_status'] !== 'active' && !current_user_can('routespro_manage')) throw new \RuntimeException('Esta verificação já não está ativa.');

        $insignia = self::canonicalInsigniaLabel((string)($row['insignia'] ?? $row['source_store_name'] ?? ''));
        $locations = self::locationsForInsignia((int)$row['client_id'], (int)$row['project_id'], $insignia, $userId);
        $unmappedLocations = self::unmappedLocationsForInsignia((int)$row['client_id'], (int)$row['project_id'], $insignia);
        $locationIndex = [];
        foreach ($locations as $location) $locationIndex[(int)$location['id']] = $location;

        $requestedLocationId = max(0, $locationId);
        if ($requestedLocationId > 0 && !isset($locationIndex[$requestedLocationId])) {
            throw new \RuntimeException('A loja selecionada não pertence à insígnia ou ao âmbito disponível para este utilizador.');
        }
        // A loja concreta é sempre uma decisão explícita no front. Não reabrimos
        // silenciosamente a última loja, para evitar gravar preços no PDV errado.
        $selectedLocationId = $requestedLocationId;
        $selectedLocation = $selectedLocationId > 0 ? (array)($locationIndex[$selectedLocationId] ?? []) : [];

        $products = self::decodeJson((string)$row['products_json']);
        $prices = self::decodeJson((string)$row['price_seed_json']);
        $notes = '';
        $latestSubmission = [];
        if ($selectedLocationId > 0) {
            $latestSubmission = $wpdb->get_row($wpdb->prepare(
                'SELECT id,prices_json,notes,submitted_at FROM ' . self::submissionsTable() . " WHERE assignment_id=%d AND location_id=%d AND status='submitted' ORDER BY submitted_at DESC,id DESC LIMIT 1",
                $assignmentId,
                $selectedLocationId
            ), ARRAY_A) ?: [];
            if ($latestSubmission) {
                $latest = self::decodeJson((string)($latestSubmission['prices_json'] ?? ''));
                if ($latest) $prices = $latest;
                $notes = (string)($latestSubmission['notes'] ?? '');
            }
        }
        $submittedStoreCount = (int)$wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(DISTINCT location_id) FROM ' . self::submissionsTable() . " WHERE assignment_id=%d AND status='submitted' AND location_id>0",
            $assignmentId
        ));

        $publicLocations = [];
        foreach ($locations as $location) {
            $publicLocations[] = [
                'id'=>(int)$location['id'],
                'name'=>(string)($location['name'] ?? ''),
                'city'=>(string)($location['city'] ?? ''),
                'external_ref'=>(string)($location['external_ref'] ?? ''),
                'association_required'=>false,
            ];
        }
        $publicUnmappedLocations = [];
        foreach ($unmappedLocations as $location) {
            $publicUnmappedLocations[] = [
                'id'=>(int)$location['id'],
                'name'=>(string)($location['name'] ?? ''),
                'city'=>(string)($location['city'] ?? ''),
                'external_ref'=>(string)($location['external_ref'] ?? ''),
                'association_required'=>true,
            ];
        }
        return [
            'assignment' => [
                'id' => (int)$row['id'],
                'batch_id' => (int)$row['batch_id'],
                'title' => (string)$row['title'],
                'week_no' => (int)$row['week_no'],
                'year_no' => (int)$row['year_no'],
                'price_note' => (string)$row['price_note'],
                'status' => (string)$row['status'],
                'submitted_at' => (string)$row['submitted_at'],
                'client_id' => (int)$row['client_id'],
                'project_id' => (int)$row['project_id'],
                'client_name' => (string)$row['client_name'],
                'project_name' => (string)$row['project_name'],
                'insignia' => $insignia,
                'source_store_name' => (string)$row['source_store_name'],
                'location_id' => $selectedLocationId,
                'location_name' => (string)($selectedLocation['name'] ?? ''),
                'city' => (string)($selectedLocation['city'] ?? ''),
                'submitted_store_count' => $submittedStoreCount,
            ],
            'locations' => $publicLocations,
            'unmapped_locations' => $publicUnmappedLocations,
            'location_state' => [
                'selected_location_id'=>$selectedLocationId,
                'has_submission'=>!empty($latestSubmission),
                'submission_id'=>(int)($latestSubmission['id'] ?? 0),
                'submitted_at'=>(string)($latestSubmission['submitted_at'] ?? ''),
                'last_location_id'=>(int)($row['last_location_id'] ?? 0),
            ],
            'products' => array_values(is_array($products) ? $products : []),
            'prices' => is_array($prices) ? $prices : [],
            'notes' => $notes,
        ];
    }

    public static function submit(int $assignmentId, int $userId, array $input): array {
        $locationId = absint($input['location_id'] ?? 0);
        if (!$locationId) throw new \RuntimeException('Seleciona a loja desta insígnia antes de submeter.');
        $payload = self::assignmentPayload($assignmentId, $userId, $locationId);
        $assignment = $payload['assignment'];
        $products = $payload['products'];
        $submittedPrices = is_array($input['prices'] ?? null) ? $input['prices'] : [];
        $allowed = [];
        foreach ($products as $product) $allowed[(string)($product['key'] ?? '')] = true;
        $prices = [];
        $filled = 0;
        foreach ($allowed as $key => $_) {
            if ($key === '') continue;
            $row = is_array($submittedPrices[$key] ?? null) ? $submittedPrices[$key] : [];
            $regular = self::normaliseSubmittedPrice($row['regular'] ?? null);
            $promo = self::normaliseSubmittedPrice($row['promo'] ?? null);
            if ($regular !== null || $promo !== null) $filled++;
            $prices[$key] = ['regular'=>$regular,'promo'=>$promo];
        }
        if ($filled < 1) throw new \RuntimeException('Preenche pelo menos um preço antes de submeter.');
        $notes = sanitize_textarea_field((string)($input['notes'] ?? ''));

        global $wpdb;
        $wpdb->insert(self::submissionsTable(), [
            'batch_id' => (int)$assignment['batch_id'],
            'assignment_id' => $assignmentId,
            'client_id' => (int)$assignment['client_id'],
            'project_id' => (int)$assignment['project_id'],
            'location_id' => $locationId,
            'insignia' => sanitize_text_field((string)$assignment['insignia']),
            'user_id' => $userId,
            'status' => 'submitted',
            'prices_json' => wp_json_encode($prices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'notes' => $notes,
            'notification_status' => 'pending',
        ], ['%d','%d','%d','%d','%d','%s','%d','%s','%s','%s','%s']);
        if (!$wpdb->insert_id) throw new \RuntimeException('Não foi possível guardar a verificação de preços.');
        $submissionId = (int)$wpdb->insert_id;
        $now = current_time('mysql');
        $wpdb->update(self::assignmentsTable(), [
            'status' => 'completed',
            'last_submission_id' => $submissionId,
            'last_location_id' => $locationId,
            'submitted_at' => $now,
        ], ['id'=>$assignmentId], ['%s','%d','%d','%s'], ['%d']);
        self::saveInsigniaLocationMapping((int)$assignment['client_id'], (int)$assignment['project_id'], $locationId, (string)$assignment['insignia'], 'shopping_submission', 100, $userId, false);

        $mail = self::sendSubmissionEmail($submissionId, $assignment, $products, $prices, $notes, $userId, null, $userId, $now, false);
        $notificationStatus = (string)($mail['status'] ?? ((string)($mail['reason'] ?? '') === 'no_recipients' ? 'no_recipients' : 'failed'));
        $notificationUpdate = [
            'notification_status' => $notificationStatus,
            'notification_recipients' => implode(',', (array)($mail['recipients'] ?? [])),
            'notification_attempts' => 1,
        ];
        $notificationFormats = ['%s','%s','%d'];
        if (!empty($mail['sent'])) {
            $notificationUpdate['notification_last_sent_at'] = $now;
            $notificationFormats[] = '%s';
        }
        $wpdb->update(self::submissionsTable(), $notificationUpdate, ['id'=>$submissionId], $notificationFormats, ['%d']);

        $message = $notificationStatus === 'sent'
            ? 'Verificação guardada. O email foi enviado individualmente para todos os destinatários configurados.'
            : ($notificationStatus === 'queued'
                ? 'Verificação guardada. O email ficou em fila de envio com retry automático.'
            : ($notificationStatus === 'partial'
                ? 'Verificação guardada. O email foi aceite apenas para parte dos destinatários. Consulta o Mail Center para identificar a falha.'
                : ($notificationStatus === 'no_recipients'
                    ? 'Verificação guardada. Não existem destinatários válidos configurados para este lote.'
                    : ((string)($mail['reason'] ?? '') === 'attachment_generation_failed'
                        ? 'Verificação guardada. O email não foi enviado porque os anexos Shopping não ficaram completos. Consulta o Mail Center e usa o reenvio depois de corrigida a geração.'
                        : 'Verificação guardada. O servidor recusou o envio para todos os destinatários. Consulta o Mail Center e a configuração SMTP.'))));
        if (!empty($mail['attachment_errors'])) $message .= ' Atenção: um ou mais anexos não puderam ser gerados. Consulta o Mail Center.';
        return [
            'id' => $submissionId,
            'location_id'=>$locationId,
            'insignia'=>(string)$assignment['insignia'],
            'message' => $message,
            'mail' => $mail,
        ];
    }

    public static function resendSubmission(int $submissionId, array $input, int $actorUserId = 0): array {
        if ($submissionId <= 0) throw new \RuntimeException('Submissão Shopping inválida.');
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $row = $wpdb->get_row($wpdb->prepare("SELECT s.*,b.products_json,b.title,b.week_no,b.year_no,b.price_note,
            a.source_store_name,COALESCE(NULLIF(s.insignia,''),NULLIF(a.insignia,''),a.source_store_name) AS insignia,COALESCE(l.name,a.source_store_name) AS location_name,l.city,
            c.name AS client_name,p.name AS project_name
            FROM " . self::submissionsTable() . " s
            INNER JOIN " . self::batchesTable() . " b ON b.id=s.batch_id
            INNER JOIN " . self::assignmentsTable() . " a ON a.id=s.assignment_id
            LEFT JOIN {$px}locations l ON l.id=s.location_id
            LEFT JOIN {$px}clients c ON c.id=s.client_id
            LEFT JOIN {$px}projects p ON p.id=s.project_id
            WHERE s.id=%d LIMIT 1", $submissionId), ARRAY_A);
        if (!$row) throw new \RuntimeException('Submissão Shopping não encontrada.');

        $products = self::decodeJson((string)($row['products_json'] ?? ''));
        $prices = self::decodeJson((string)($row['prices_json'] ?? ''));
        $assignment = [
            'id'=>(int)$row['assignment_id'],
            'batch_id'=>(int)$row['batch_id'],
            'client_id'=>(int)$row['client_id'],
            'project_id'=>(int)$row['project_id'],
            'location_id'=>(int)$row['location_id'],
            'location_name'=>(string)$row['location_name'],
            'city'=>(string)$row['city'],
            'client_name'=>(string)$row['client_name'],
            'project_name'=>(string)$row['project_name'],
            'insignia'=>(string)($row['insignia'] ?? $row['source_store_name'] ?? ''),
            'title'=>(string)$row['title'],
            'week_no'=>(int)$row['week_no'],
            'year_no'=>(int)$row['year_no'],
            'price_note'=>(string)$row['price_note'],
        ];
        $override = [
            'notify_client'=>!empty($input['notify_client']) ? 1 : 0,
            'recipient_user_ids'=>is_array($input['recipient_user_ids'] ?? null) ? $input['recipient_user_ids'] : [],
            'extra_emails'=>self::sanitiseEmailList((string)($input['extra_emails'] ?? '')),
        ];
        $mail = self::sendSubmissionEmail(
            $submissionId,
            $assignment,
            $products,
            $prices,
            (string)($row['notes'] ?? ''),
            (int)$row['user_id'],
            $override,
            $actorUserId ?: get_current_user_id(),
            (string)($row['submitted_at'] ?? ''),
            true
        );
        $attempts = max(0, (int)($row['notification_attempts'] ?? 0)) + 1;
        $notificationStatus = (string)($mail['status'] ?? ((string)($mail['reason'] ?? '') === 'no_recipients' ? 'no_recipients' : 'failed'));
        $update = [
            'notification_status'=>$notificationStatus,
            'notification_recipients'=>implode(',', (array)($mail['recipients'] ?? [])),
            'notification_attempts'=>$attempts,
        ];
        $formats = ['%s','%s','%d'];
        if (!empty($mail['sent'])) {
            $update['notification_last_sent_at'] = current_time('mysql');
            $formats[] = '%s';
        }
        $wpdb->update(self::submissionsTable(), $update, ['id'=>$submissionId], $formats, ['%d']);
        $message = $notificationStatus === 'sent'
            ? 'Reenvio enviado individualmente para ' . count((array)($mail['delivered_recipients'] ?? [])) . ' destinatário(s).'
            : ($notificationStatus === 'queued'
                ? 'Reenvio colocado em fila para ' . count((array)($mail['queued_recipients'] ?? [])) . ' destinatário(s).'
            : ($notificationStatus === 'partial'
                ? 'Reenvio parcial: ' . count((array)($mail['accepted_recipients'] ?? [])) . ' aceite(s) e ' . count((array)($mail['failed_recipients'] ?? [])) . ' com falha. Consulta o Mail Center.'
                : ($notificationStatus === 'no_recipients'
                    ? 'Seleciona pelo menos um destinatário válido para reenviar.'
                    : ((string)($mail['reason'] ?? '') === 'attachment_generation_failed'
                        ? 'O reenvio não foi efetuado porque os anexos Shopping não ficaram completos. Consulta o Mail Center.'
                        : 'O WordPress não aceitou o reenvio para nenhum destinatário. Consulta o Mail Center e a configuração SMTP.'))));
        if (!empty($mail['attachment_errors'])) $message .= ' Atenção: um ou mais anexos não puderam ser gerados. Consulta o Mail Center.';
        return [
            'sent'=>!empty($mail['sent']),
            'status'=>$notificationStatus,
            'recipients'=>(array)($mail['recipients'] ?? []),
            'attempts'=>$attempts,
            'message'=>$message,
            'attachments'=>(array)($mail['attachments'] ?? []),
            'attachment_errors'=>(array)($mail['attachment_errors'] ?? []),
        ];
    }

    private static function sendSubmissionEmail(int $submissionId, array $assignment, array $products, array $prices, string $notes, int $merchandiserUserId, ?array $recipientOverride = null, int $actorUserId = 0, string $submittedAt = '', bool $isResend = false): array {
        global $wpdb;
        $batch = self::getBatch((int)$assignment['batch_id']);
        $px = $wpdb->prefix . 'routespro_';
        $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}clients WHERE id=%d", (int)$assignment['client_id']), ARRAY_A) ?: [];
        $user = get_userdata($merchandiserUserId);
        $template = self::getTemplate();
        $targets = self::resolveRecipientTargets($batch, $client, $recipientOverride);
        $recipients = array_values(array_map(static fn(array $target): string => (string)$target['email'], $targets));
        if (!$recipients) return ['sent'=>false,'all_sent'=>false,'status'=>'no_recipients','reason'=>'no_recipients','recipients'=>[],'accepted_recipients'=>[],'failed_recipients'=>[],'targets'=>[]];

        $priceTable = self::priceTableHtml($products, $prices);
        $notesBlock = $notes !== '' ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;margin-top:22px;background:#fff7ed;border:1px solid #fed7aa"><tr><td style="padding:14px 16px;font-family:Arial,Helvetica,sans-serif"><div style="font-size:11px;line-height:16px;letter-spacing:.08em;text-transform:uppercase;color:#9a3412;font-weight:700">Observações</div><div style="margin-top:5px;font-size:13px;line-height:20px;color:#7c2d12">' . nl2br(esc_html($notes)) . '</div></td></tr></table>' : '';
        $submittedLabel = '';
        if ($submittedAt !== '') {
            $timestamp = strtotime($submittedAt);
            if ($timestamp) $submittedLabel = wp_date('d/m/Y H:i', $timestamp);
        }
        if ($submittedLabel === '') $submittedLabel = wp_date('d/m/Y H:i');
        $tokens = [
            '{client_name}' => (string)($assignment['client_name'] ?? $client['name'] ?? ''),
            '{project_name}' => (string)($assignment['project_name'] ?? ''),
            '{insignia}' => (string)($assignment['insignia'] ?? ''),
            '{store_name}' => (string)($assignment['location_name'] ?? ''),
            '{merchandiser_name}' => (string)($user ? $user->display_name : ''),
            '{merchandiser_email}' => (string)($user ? $user->user_email : ''),
            '{week}' => (string)($assignment['week_no'] ?? ''),
            '{year}' => (string)($assignment['year_no'] ?? ''),
            '{submitted_at}' => $submittedLabel,
            '{prices_table}' => $priceTable,
            '{notes_block}' => $notesBlock,
            '{notes}' => $notes,
        ];
        $subject = sanitize_text_field(strtr((string)($batch['email_subject'] ?? $template['subject']), $tokens));
        $body = strtr((string)($batch['email_body'] ?? $template['body']), $tokens);
        $globalEmails = Emails::get();
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $fromEmail = sanitize_email((string)($globalEmails['from_email'] ?? ''));
        $fromName = wp_specialchars_decode((string)($globalEmails['from_name'] ?? ''), ENT_QUOTES);
        if ($fromEmail) $headers[] = 'From: ' . ($fromName ?: get_bloginfo('name')) . ' <' . $fromEmail . '>';
        $replyTo = sanitize_email((string)($globalEmails['reply_to'] ?? ''));
        if ($replyTo) $headers[] = 'Reply-To: ' . $replyTo;
        $productCount = count($products);
        $regularCount = 0;
        $promoCount = 0;
        foreach ($products as $product) {
            $priceKey = (string)($product['key'] ?? '');
            $priceRow = is_array($prices[$priceKey] ?? null) ? $prices[$priceKey] : [];
            if (array_key_exists('regular', $priceRow) && $priceRow['regular'] !== null && $priceRow['regular'] !== '') $regularCount++;
            if (array_key_exists('promo', $priceRow) && $priceRow['promo'] !== null && $priceRow['promo'] !== '') $promoCount++;
        }
        $attachmentSnapshotPrices = [];
        foreach ($products as $product) {
            if (!is_array($product)) continue;
            $priceKey = (string)($product['key'] ?? '');
            if ($priceKey === '') continue;
            $priceRow = is_array($prices[$priceKey] ?? null) ? $prices[$priceKey] : [];
            $attachmentSnapshotPrices[] = [
                'key'=>$priceKey,
                'type'=>(string)($product['type'] ?? ''),
                'name'=>(string)($product['name'] ?? ''),
                'regular'=>array_key_exists('regular', $priceRow) ? $priceRow['regular'] : null,
                'promo'=>array_key_exists('promo', $priceRow) ? $priceRow['promo'] : null,
            ];
        }
        $attachmentSnapshot = [
            'id'=>$submissionId,
            'submitted_at'=>$submittedAt !== '' ? $submittedAt : current_time('mysql'),
            'client_name'=>(string)($assignment['client_name'] ?? $client['name'] ?? ''),
            'project_name'=>(string)($assignment['project_name'] ?? ''),
            'title'=>(string)($assignment['title'] ?? $batch['title'] ?? ''),
            'week_no'=>(int)($assignment['week_no'] ?? $batch['week_no'] ?? 0),
            'year_no'=>(int)($assignment['year_no'] ?? $batch['year_no'] ?? 0),
            'price_note'=>(string)($assignment['price_note'] ?? $batch['price_note'] ?? ''),
            'insignia'=>(string)($assignment['insignia'] ?? ''),
            'location_name'=>(string)($assignment['location_name'] ?? ''),
            'city'=>(string)($assignment['city'] ?? ''),
            'merchandiser_name'=>(string)($user ? $user->display_name : ''),
            'merchandiser_email'=>(string)($user ? $user->user_email : ''),
            'notes'=>$notes,
            'prices'=>$attachmentSnapshotPrices,
            'source'=>'canonical_submission_snapshot',
        ];
        // Successor-safe 9AD initialization; 9AG immediately upgrades Shopping to the mandatory pair.
        $requestedFormats = [];
        // Shopping delivery contract: every verification email carries both canonical attachments.
        $requestedFormats = ['xlsx','pdf'];
        $attachmentBundle = ['attachments'=>[], 'files'=>[], 'errors'=>[], 'requested'=>$requestedFormats];
        try {
            $attachmentBundle = ShoppingExportService::createSubmissionAttachments(
                $submissionId,
                $actorUserId ?: $merchandiserUserId,
                true,
                true,
                $attachmentSnapshot
            );
        } catch (\Throwable $attachmentError) {
            $attachmentBundle['errors'][] = sanitize_text_field($attachmentError->getMessage());
        }
        $attachmentPaths = array_values(array_filter(array_map('strval', (array)($attachmentBundle['attachments'] ?? [])), 'is_file'));
        $attachmentFiles = (array)($attachmentBundle['files'] ?? []);
        $generatedFormats = [];
        foreach ($attachmentFiles as $attachmentFile) {
            $format = sanitize_key((string)($attachmentFile['format'] ?? ''));
            if ($format !== '') $generatedFormats[$format] = true;
        }
        $bundleRequestedFormats = array_values(array_filter(array_map('sanitize_key', (array)($attachmentBundle['requested'] ?? []))));
        if ($bundleRequestedFormats) $requestedFormats = array_values(array_unique(array_merge($requestedFormats, $bundleRequestedFormats)));
        $missingAttachmentFormats = array_values(array_filter($requestedFormats, static fn(string $format): bool => $format !== '' && empty($generatedFormats[$format])));
        $attachmentErrors = array_values(array_filter(array_map('sanitize_text_field', (array)($attachmentBundle['errors'] ?? []))));
        if ($requestedFormats && ($missingAttachmentFormats || $attachmentErrors)) {
            ShoppingExportService::cleanupAttachments($attachmentPaths);
            $attachmentFailure = 'Falhou a criação dos anexos obrigatórios do Shopping.';
            if ($missingAttachmentFormats) $attachmentFailure .= ' Em falta: ' . strtoupper(implode(', ', $missingAttachmentFormats)) . '.';
            if ($attachmentErrors) $attachmentFailure .= ' ' . implode(' | ', $attachmentErrors);
            foreach ($targets as $target) {
                Emails::log_email([
                    'email_type'=>$isResend ? 'shopping_verification_resend' : 'shopping_verification',
                    'context_key'=>'shopping_submission',
                    'client_id'=>(int)$assignment['client_id'],
                    'project_id'=>(int)$assignment['project_id'],
                    'sender_user_id'=>$actorUserId ?: $merchandiserUserId,
                    'recipient_user_id'=>(int)($target['user_id'] ?? 0) ?: null,
                    'recipient_email'=>(string)$target['email'],
                    'recipient_name'=>(string)($target['name'] ?? ''),
                    'message_kind'=>$isResend ? 'price_verification_resend' : 'price_verification',
                    'subject'=>$subject,
                    'body'=>$body,
                    'meta'=>[
                        'submission_id'=>$submissionId,
                        'assignment_id'=>(int)$assignment['id'],
                        'store_name'=>(string)$assignment['location_name'],
                        'insignia'=>(string)($assignment['insignia'] ?? ''),
                        'merchandiser_user_id'=>$merchandiserUserId,
                        'recipient_source'=>(string)($target['source'] ?? ''),
                        'resend'=>$isResend ? 1 : 0,
                        'mail_error'=>$attachmentFailure,
                        'attachment_contract'=>'fieldflow-shopping-canonical-attachment-v1',
                        'attachment_source'=>'canonical_submission_snapshot',
                        'attachment_requested'=>$requestedFormats,
                        'attachment_missing'=>$missingAttachmentFormats,
                        'attachment_errors'=>$attachmentErrors,
                    ],
                    'mail_result'=>'failed',
                ]);
            }
            return [
                'sent'=>false,
                'all_sent'=>false,
                'status'=>'failed',
                'reason'=>'attachment_generation_failed',
                'recipients'=>$recipients,
                'accepted_recipients'=>[],
                'failed_recipients'=>array_map(static fn(string $email): array => ['email'=>$email,'error'=>$attachmentFailure], $recipients),
                'targets'=>$targets,
                'subject'=>$subject,
                'attachments'=>[],
                'attachment_errors'=>$attachmentErrors ?: [$attachmentFailure],
                'attachment_missing'=>$missingAttachmentFormats,
            ];
        }
        if ($attachmentFiles) {
            $labels = [];
            foreach ($attachmentFiles as $attachmentFile) {
                $format = strtoupper((string)($attachmentFile['format'] ?? ''));
                if ($format !== '') $labels[$format] = $format;
            }
            $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;margin-top:18px;background:#f8fafc;border:1px solid #e2e8f0"><tr><td style="padding:12px 16px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#475569"><strong style="color:#172033">Documentos em anexo:</strong> ' . esc_html(implode(' + ', array_values($labels))) . '. Ficheiros gerados a partir desta submissão e desta loja.</td></tr></table>';
        }
        $message = Emails::render_newsletter_html($subject, $body, [
            'brand_primary' => $template['brand_primary'] ?? '#172033',
            'footer_text' => $template['footer_text'] ?? '',
            'eyebrow' => 'FieldFlow · Shopping & Preços',
            'preheader' => 'Shopping ' . (string)($assignment['insignia'] ?? '') . ', ' . (string)($assignment['location_name'] ?? '') . ', semana ' . (string)($assignment['week_no'] ?? ''),
            'stats' => [
                ['label'=>'Artigos','value'=>(string)$productCount],
                ['label'=>'PVP regular','value'=>$regularCount . '/' . $productCount],
                ['label'=>'PVP promo','value'=>$promoCount . '/' . $productCount],
            ],
        ]);
        $acceptedRecipients = [];
        $deliveredRecipients = [];
        $queuedRecipients = [];
        $failedRecipients = [];
        try {
        foreach ($targets as $target) {
            $targetEmail = (string)$target['email'];
            $dispatchContext = ['source'=>'shopping_verification','client_id'=>(int)$assignment['client_id'],'project_id'=>(int)$assignment['project_id'],'entity_type'=>'shopping_submission','entity_id'=>$submissionId,'is_resend'=>$isResend];
            if (!$isResend) $dispatchContext['idempotency_key'] = 'shopping_verification|' . $submissionId . '|' . strtolower($targetEmail);
            $mailDispatch = \RoutesPro\Services\MailDispatchService::dispatch($targetEmail, $subject, $message, $headers, $attachmentPaths, $dispatchContext);
            $targetAccepted = !empty($mailDispatch['accepted']);
            $targetDelivered = !empty($mailDispatch['sent']);
            $mailError = (string)($mailDispatch['error'] ?? '');
            if ($targetAccepted) {
                $acceptedRecipients[] = $targetEmail;
                if ($targetDelivered) $deliveredRecipients[] = $targetEmail;
                else $queuedRecipients[] = $targetEmail;
            } else $failedRecipients[] = ['email'=>$targetEmail,'error'=>$mailError !== '' ? $mailError : 'wp_mail devolveu false.'];

            Emails::log_email([
                'email_type' => $isResend ? 'shopping_verification_resend' : 'shopping_verification',
                'context_key' => 'shopping_submission',
                'client_id' => (int)$assignment['client_id'],
                'project_id' => (int)$assignment['project_id'],
                'sender_user_id' => $actorUserId ?: $merchandiserUserId,
                'recipient_user_id' => (int)($target['user_id'] ?? 0) ?: null,
                'recipient_email' => $targetEmail,
                'recipient_name' => (string)($target['name'] ?? ''),
                'message_kind' => $isResend ? 'price_verification_resend' : 'price_verification',
                'subject' => $subject,
                'body' => $body,
                'meta' => [
                    'submission_id'=>$submissionId,
                    'assignment_id'=>(int)$assignment['id'],
                    'store_name'=>(string)$assignment['location_name'],
                    'insignia'=>(string)($assignment['insignia'] ?? ''),
                    'merchandiser_user_id'=>$merchandiserUserId,
                    'recipient_source'=>(string)($target['source'] ?? ''),
                    'resend'=>$isResend ? 1 : 0,
                    'mail_error'=>$mailError,
                    'attachments'=>array_values(array_map(static fn(array $file): string => (string)($file['filename'] ?? ''), $attachmentFiles)),
                    'attachment_bytes'=>array_sum(array_map(static fn(array $file): int => (int)($file['bytes'] ?? 0), $attachmentFiles)),
                    'attachment_errors'=>(array)($attachmentBundle['errors'] ?? []),
                    'attachment_contract'=>'fieldflow-shopping-canonical-attachment-v1',
                    'attachment_source'=>(string)($attachmentBundle['source'] ?? ''),
                    'attachment_requested'=>$requestedFormats,
                ],
                'mail_result' => $targetDelivered ? 'sent' : ($targetAccepted ? 'queued' : 'failed'),
            ]);
        }
        } finally {
            ShoppingExportService::cleanupAttachments($attachmentPaths);
        }
        $acceptedCount = count($acceptedRecipients);
        $deliveredCount = count($deliveredRecipients);
        $targetCount = count($targets);
        $status = $acceptedCount === 0 ? 'failed' : ($acceptedCount < $targetCount ? 'partial' : ($deliveredCount === $targetCount ? 'sent' : 'queued'));
        return [
            'sent'=>$deliveredCount > 0,
            'accepted'=>$acceptedCount > 0,
            'all_sent'=>$status === 'sent',
            'status'=>$status,
            'recipients'=>$recipients,
            'accepted_recipients'=>$acceptedRecipients,
            'delivered_recipients'=>$deliveredRecipients,
            'queued_recipients'=>$queuedRecipients,
            'failed_recipients'=>$failedRecipients,
            'targets'=>$targets,
            'subject'=>$subject,
            'attachments'=>array_values(array_map(static fn(array $file): string => (string)($file['filename'] ?? ''), $attachmentFiles)),
            'attachment_errors'=>(array)($attachmentBundle['errors'] ?? []),
        ];
    }

    public static function recipientDirectory(int $clientId): array {
        if ($clientId <= 0) return ['client'=>[],'users'=>[]];
        if (isset(self::$recipientDirectoryCache[$clientId])) return self::$recipientDirectoryCache[$clientId];
        global $wpdb;
        $client = $wpdb->get_row($wpdb->prepare(
            'SELECT id,name,email FROM ' . $wpdb->prefix . 'routespro_clients WHERE id=%d LIMIT 1',
            $clientId
        ), ARRAY_A) ?: [];
        $userIds = class_exists(AssignmentResolver::class) ? AssignmentResolver::get_client_user_ids($clientId) : [];
        $users = [];
        if ($userIds) {
            $rows = get_users([
                'include'=>$userIds,
                'orderby'=>'display_name',
                'order'=>'ASC',
                'fields'=>['ID','display_name','user_email','user_login'],
            ]);
            foreach ((array)$rows as $user) {
                $email = sanitize_email((string)($user->user_email ?? ''));
                if (!$email) continue;
                $users[] = [
                    'id'=>(int)$user->ID,
                    'name'=>(string)$user->display_name,
                    'login'=>(string)$user->user_login,
                    'email'=>$email,
                ];
            }
        }
        return self::$recipientDirectoryCache[$clientId] = ['client'=>$client,'users'=>$users];
    }

    public static function clientRecipientUserIds(int $clientId): array {
        $directory = self::recipientDirectory($clientId);
        return array_values(array_map(static fn(array $user): int => (int)$user['id'], (array)($directory['users'] ?? [])));
    }

    public static function selectedRecipientUserIds(array $batch): array {
        return self::filterRecipientUserIds((int)($batch['client_id'] ?? 0), self::decodeJson((string)($batch['recipient_user_ids'] ?? '')));
    }

    private static function filterRecipientUserIds(int $clientId, array $ids): array {
        $allowed = array_fill_keys(self::clientRecipientUserIds($clientId), true);
        $out = [];
        foreach ($ids as $id) {
            $id = absint($id);
            if ($id > 0 && isset($allowed[$id])) $out[$id] = $id;
        }
        ksort($out, SORT_NUMERIC);
        return array_values($out);
    }

    private static function resolveRecipientTargets(array $batch, array $client, ?array $override = null): array {
        $clientId = (int)($batch['client_id'] ?? $client['id'] ?? 0);
        $directory = self::recipientDirectory($clientId);
        $notifyClient = $override === null ? !empty($batch['notify_client']) : !empty($override['notify_client']);
        $selectedIds = $override === null
            ? self::selectedRecipientUserIds($batch)
            : self::filterRecipientUserIds($clientId, is_array($override['recipient_user_ids'] ?? null) ? $override['recipient_user_ids'] : []);
        $extraEmails = $override === null
            ? self::sanitiseEmailList((string)($batch['extra_emails'] ?? ''))
            : self::sanitiseEmailList((string)($override['extra_emails'] ?? ''));

        $targets = [];
        $push = static function(array &$targets, string $email, string $name, int $userId, string $source): void {
            $email = sanitize_email($email);
            if (!$email) return;
            $key = strtolower($email);
            if (isset($targets[$key])) return;
            $targets[$key] = ['email'=>$email,'name'=>$name,'user_id'=>$userId,'source'=>$source];
        };
        if ($notifyClient) {
            $push($targets, (string)($directory['client']['email'] ?? $client['email'] ?? ''), (string)($directory['client']['name'] ?? $client['name'] ?? ''), 0, 'client_primary');
        }
        $selected = array_fill_keys($selectedIds, true);
        foreach ((array)($directory['users'] ?? []) as $user) {
            $userId = (int)($user['id'] ?? 0);
            if ($userId <= 0 || !isset($selected[$userId])) continue;
            $push($targets, (string)$user['email'], (string)$user['name'], $userId, 'client_user');
        }
        foreach (explode(',', $extraEmails) as $email) {
            $email = sanitize_email(trim($email));
            if ($email) $push($targets, $email, '', 0, 'extra');
        }
        return array_values($targets);
    }

    private static function priceTableHtml(array $products, array $prices): string {
        $rows = '';
        $position = 0;
        foreach ($products as $product) {
            $position++;
            $key = (string)($product['key'] ?? '');
            $price = is_array($prices[$key] ?? null) ? $prices[$key] : [];
            $regular = $price['regular'] ?? null;
            $promo = $price['promo'] ?? null;
            $type = trim((string)($product['type'] ?? ''));
            $typeLabel = $type !== '' ? $type : 'Artigo';
            $typeColor = stripos($typeLabel, 'concorr') !== false ? '#9a3412' : '#1d4ed8';
            $typeBg = stripos($typeLabel, 'concorr') !== false ? '#fff7ed' : '#eff6ff';
            $rows .= '<tr>'
                . '<td valign="top" width="54%" style="width:54%;padding:11px 10px;border-bottom:1px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif;box-sizing:border-box;overflow-wrap:anywhere;word-break:break-word">'
                . '<div style="font-size:13px;line-height:18px;font-weight:700;color:#0f172a">' . esc_html((string)($product['name'] ?? '')) . '</div>'
                . '<div class="ff-price-type" style="margin-top:4px"><span style="display:inline-block;padding:2px 6px;background:' . esc_attr($typeBg) . ';color:' . esc_attr($typeColor) . ';font-size:9px;line-height:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em">' . esc_html($typeLabel) . '</span> <span style="font-size:10px;color:#94a3b8">#' . (int)$position . '</span></div>'
                . '</td>'
                . '<td valign="middle" align="right" width="23%" style="width:23%;padding:11px 8px;border-bottom:1px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:18px;color:#0f172a;font-weight:700;white-space:normal;overflow-wrap:anywhere;word-break:break-word">' . ($regular !== null && $regular !== '' ? esc_html(number_format((float)$regular, 2, ',', '.') . ' €') : '<span style="color:#94a3b8;font-weight:400">Sem registo</span>') . '</td>'
                . '<td valign="middle" align="right" width="23%" style="width:23%;padding:11px 10px;border-bottom:1px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:18px;color:#0f172a;font-weight:700;white-space:normal;overflow-wrap:anywhere;word-break:break-word">' . ($promo !== null && $promo !== '' ? esc_html(number_format((float)$promo, 2, ',', '.') . ' €') : '<span style="color:#94a3b8;font-weight:400">Sem registo</span>') . '</td>'
                . '</tr>';
        }
        return '<table class="ff-price-table ff-data-table" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:100%;table-layout:fixed;border-collapse:collapse;border:1px solid #e2e8f0;background:#ffffff;box-sizing:border-box">'
            . '<thead><tr bgcolor="#f8fafc"><th align="left" width="54%" style="width:54%;padding:10px;font-family:Arial,Helvetica,sans-serif;font-size:10px;line-height:14px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #cbd5e1">Artigo</th><th align="right" width="23%" style="width:23%;padding:10px 8px;font-family:Arial,Helvetica,sans-serif;font-size:10px;line-height:14px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #cbd5e1">PVP regular</th><th align="right" width="23%" style="width:23%;padding:10px;font-family:Arial,Helvetica,sans-serif;font-size:10px;line-height:14px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;border-bottom:1px solid #cbd5e1">PVP promo</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    /**
     * Fonte única dos merchandisers Shopping.
     * Corresponde exatamente a Intelligence Center > Projetos >
     * Utilizadores com acesso ao projeto. Não herda roles, owners, rotas ou PDVs.
     */
    public static function projectMerchandiserIds(int $projectId): array {
        if ($projectId <= 0) return [];
        $ids = [];
        if (class_exists(AssignmentResolver::class)) {
            $context = AssignmentResolver::get_project_context($projectId);
            foreach ((array)($context['associated_user_ids'] ?? []) as $userId) {
                $userId = absint($userId);
                if ($userId > 0) $ids[$userId] = $userId;
            }
        }
        if (!$ids) {
            global $wpdb;
            $metaJson = (string)$wpdb->get_var($wpdb->prepare(
                "SELECT meta_json FROM {$wpdb->prefix}routespro_projects WHERE id=%d LIMIT 1",
                $projectId
            ));
            $meta = json_decode($metaJson, true);
            if (!is_array($meta)) $meta = [];
            $values = $meta['associated_user_ids'] ?? ($meta['user_ids'] ?? []);
            if (is_string($values)) $values = preg_split('/\s*,\s*/', trim($values));
            foreach ((array)$values as $value) {
                if (is_array($value)) $value = $value['user_id'] ?? ($value['id'] ?? 0);
                $userId = absint($value);
                if ($userId > 0) $ids[$userId] = $userId;
            }
        }
        ksort($ids, SORT_NUMERIC);
        return array_values($ids);
    }

    public static function projectMerchandisers(int $projectId): array {
        $ids = self::projectMerchandiserIds($projectId);
        if (!$ids) return [];
        $users = get_users([
            'include' => $ids,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID','display_name','user_email','user_login'],
        ]);
        return is_array($users) ? $users : [];
    }

    private static function resolveProjectUserId(int $userId, array $projectUserIds, bool $allowUniqueFallback = true): int {
        $allowed = array_fill_keys(array_map('absint', $projectUserIds), true);
        if ($userId > 0 && isset($allowed[$userId])) return $userId;
        if ($allowUniqueFallback && count($allowed) === 1) return (int)array_key_first($allowed);
        return 0;
    }

    private static function backfillInsigniaLocationMappings(): void {
        global $wpdb;
        $table = self::insigniaLocationsTable();
        $rows = $wpdb->get_results("SELECT client_id,project_id,location_id,insignia,user_id FROM " . self::submissionsTable() . " WHERE location_id>0 AND insignia IS NOT NULL AND insignia<>'' ORDER BY id ASC", ARRAY_A) ?: [];
        foreach ($rows as $row) {
            self::saveInsigniaLocationMapping(
                (int)($row['client_id'] ?? 0),
                (int)($row['project_id'] ?? 0),
                (int)($row['location_id'] ?? 0),
                (string)($row['insignia'] ?? ''),
                'shopping_history',
                100,
                (int)($row['user_id'] ?? 0),
                false
            );
        }
    }

    private static function saveInsigniaLocationMapping(int $clientId, int $projectId, int $locationId, string $insignia, string $source = 'manual', int $confidence = 100, int $actorUserId = 0, bool $overwrite = false): void {
        if ($projectId <= 0 || $locationId <= 0) return;
        $insignia = self::canonicalInsigniaLabel($insignia);
        if ($insignia === '') return;
        // Do not persist new unsupported identities from historical submissions.
        if (StoreIdentityAuthority::family($insignia) === '') return;
        global $wpdb;
        $loc = $wpdb->get_row($wpdb->prepare(
            'SELECT name,tags,meta_json FROM ' . $wpdb->prefix . 'routespro_locations WHERE id=%d LIMIT 1',
            $locationId
        ), ARRAY_A);
        if (!$loc || !StoreIdentityAuthority::locationMatches($insignia, (array)$loc)) return;
        $table = self::insigniaLocationsTable();
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE project_id=%d AND location_id=%d LIMIT 1", $projectId, $locationId), ARRAY_A) ?: [];
        if ($existing) {
            $existingInsignia = self::canonicalInsigniaLabel((string)($existing['insignia'] ?? ''));
            $existingSource = sanitize_key((string)($existing['match_source'] ?? ''));
            if (!$overwrite && $existingInsignia !== '' && $existingInsignia !== $insignia) return;
            if (!$overwrite && strpos($existingSource, 'manual') === 0 && $existingInsignia === $insignia) return;
            $wpdb->update($table, [
                'client_id'=>$clientId,
                'insignia'=>$insignia,
                'match_source'=>sanitize_key($source) ?: 'manual',
                'confidence'=>max(0, min(100, $confidence)),
                'created_by'=>$actorUserId > 0 ? $actorUserId : ($existing['created_by'] ?? null),
                'updated_at'=>current_time('mysql'),
            ], ['id'=>(int)$existing['id']], ['%d','%s','%s','%d','%d','%s'], ['%d']);
            return;
        }
        $wpdb->insert($table, [
            'client_id'=>$clientId,
            'project_id'=>$projectId,
            'location_id'=>$locationId,
            'insignia'=>$insignia,
            'match_source'=>sanitize_key($source) ?: 'manual',
            'confidence'=>max(0, min(100, $confidence)),
            'created_by'=>$actorUserId > 0 ? $actorUserId : null,
            'created_at'=>current_time('mysql'),
            'updated_at'=>current_time('mysql'),
        ], ['%d','%d','%d','%s','%s','%d','%d','%s','%s']);
    }

    private static function mappedInsigniasForProject(int $clientId, int $projectId): array {
        if ($projectId <= 0) return [];
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT location_id,insignia,match_source,confidence FROM " . self::insigniaLocationsTable() . " WHERE project_id=%d AND (client_id=%d OR client_id=0) ORDER BY confidence DESC,id DESC",
            $projectId,
            $clientId
        ), ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $locationId = (int)($row['location_id'] ?? 0);
            if ($locationId <= 0 || isset($out[$locationId])) continue;
            $out[$locationId] = [
                'insignia'=>self::canonicalInsigniaLabel((string)($row['insignia'] ?? '')),
                'source'=>(string)($row['match_source'] ?? ''),
                'confidence'=>(int)($row['confidence'] ?? 0),
            ];
        }
        return $out;
    }

    private static function historicalLocationIdsForInsignia(int $clientId, int $projectId, string $insignia): array {
        global $wpdb;
        $wanted = self::canonicalInsigniaLabel($insignia);
        if ($wanted === '' || $projectId <= 0) return [];
        $ids = [];
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT location_id,insignia FROM " . self::submissionsTable() . " WHERE client_id=%d AND project_id=%d AND location_id>0",
            $clientId,
            $projectId
        ), ARRAY_A) ?: [];
        foreach ($rows as $row) {
            if (self::canonicalInsigniaLabel((string)($row['insignia'] ?? '')) === $wanted) $ids[(int)$row['location_id']] = true;
        }
        if (class_exists(StockService::class)) {
            $batchTable = StockService::batchTable();
            $itemTable = StockService::itemTable();
            $batchExists = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $batchTable)) === $batchTable;
            $itemExists = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $itemTable)) === $itemTable;
            if ($batchExists && $itemExists) {
                $stockRows = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT i.location_id,b.insignia FROM {$itemTable} i INNER JOIN {$batchTable} b ON b.id=i.batch_id WHERE i.location_id>0 AND b.client_id=%d AND (b.project_id=%d OR b.project_id=0 OR b.project_id IS NULL)",
                    $clientId,
                    $projectId
                ), ARRAY_A) ?: [];
                foreach ($stockRows as $row) {
                    if (self::canonicalInsigniaLabel((string)($row['insignia'] ?? '')) === $wanted) $ids[(int)$row['location_id']] = true;
                }
            }
        }
        return $ids;
    }

    private static function locationInsigniaHaystack(array $location): string {
        return trim(implode(' ', array_filter([
            (string)($location['name'] ?? ''),
            (string)($location['external_ref'] ?? ''),
            (string)($location['tags'] ?? ''),
            (string)($location['meta_json'] ?? ''),
            (string)($location['category_name'] ?? ''),
            (string)($location['subcategory_name'] ?? ''),
            (string)($location['parent_category_name'] ?? ''),
        ], static fn($value): bool => trim($value) !== '')));
    }

    private static function locationMatchesInsigniaStrict(array $location, string $insignia): bool {
        return StoreIdentityAuthority::locationMatches($insignia, $location);
    }

    private static function unmappedLocationsForInsignia(int $clientId, int $projectId, string $insignia): array {
        $locations = self::locationsForProject($clientId, $projectId);
        if (!$locations) return [];
        $mapped = self::mappedInsigniasForProject($clientId, $projectId);
        $wanted = self::canonicalInsigniaLabel($insignia);
        $recognized = [];
        foreach (self::locationsForInsignia($clientId, $projectId, $insignia) as $row) $recognized[(int)($row['id'] ?? 0)] = true;
        $out = [];
        foreach ($locations as $location) {
            $id = (int)($location['id'] ?? 0);
            if ($id <= 0 || isset($recognized[$id])) continue;
            if (isset($mapped[$id])) continue;
            $haystack = self::locationInsigniaHaystack($location);
            $candidateFamily = class_exists(StockService::class) ? StockService::brandFamily($haystack) : '';
            $wantedFamily = class_exists(StockService::class) ? StockService::brandFamily($insignia) : '';
            if (!StoreIdentityAuthority::locationMatches($wanted, $location)) continue;
            if ($candidateFamily === 'continente' && $wantedFamily === 'continente') {
                $candidateCanonical = self::canonicalInsigniaLabel($haystack);
                if (in_array($candidateCanonical, ['Continente','Continente Modelo','Continente Bom Dia'], true) && $candidateCanonical !== $wanted) continue;
            }
            $out[] = $location;
        }
        usort($out, static function(array $left, array $right): int {
            $city = strnatcasecmp((string)($left['city'] ?? ''), (string)($right['city'] ?? ''));
            return $city ?: strnatcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
        });
        return array_values($out);
    }

    public static function associateLocationToInsignia(int $assignmentId, int $userId, int $locationId): array {
        if ($locationId <= 0) throw new \RuntimeException('Seleciona uma loja para associar.');
        $base = self::assignmentPayload($assignmentId, $userId, 0);
        $assignment = (array)($base['assignment'] ?? []);
        $candidates = self::unmappedLocationsForInsignia((int)($assignment['client_id'] ?? 0), (int)($assignment['project_id'] ?? 0), (string)($assignment['insignia'] ?? ''));
        $candidateIds = array_fill_keys(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $candidates), true);
        if (!isset($candidateIds[$locationId])) throw new \RuntimeException('Esta loja já está classificada noutra insígnia ou não pertence ao projeto.');
        self::saveInsigniaLocationMapping(
            (int)($assignment['client_id'] ?? 0),
            (int)($assignment['project_id'] ?? 0),
            $locationId,
            (string)($assignment['insignia'] ?? ''),
            'manual_front',
            100,
            $userId,
            true
        );
        return self::assignmentPayload($assignmentId, $userId, $locationId);
    }

    public static function locationsForInsignia(int $clientId, int $projectId, string $insignia, int $userId = 0): array {
        $locations = self::locationsForProject($clientId, $projectId);
        if (!$locations) return [];
        $wanted = self::canonicalInsigniaLabel($insignia);
        $mapped = self::mappedInsigniasForProject($clientId, $projectId);
        $historical = self::historicalLocationIdsForInsignia($clientId, $projectId, $wanted);
        $out = [];
        foreach ($locations as $location) {
            $id = (int)($location['id'] ?? 0);
            if ($id <= 0) continue;
            // Old explicit mappings and historical submissions are evidence to review,
            // never proof that the PDV actually belongs to this retailer.
            if (!self::locationMatchesInsigniaStrict($location, $wanted)) continue;
            if (isset($mapped[$id])) {
                if ((string)($mapped[$id]['insignia'] ?? '') === $wanted) $out[$id] = $location;
                continue;
            }
            if (isset($historical[$id]) || self::locationMatchesInsigniaStrict($location, $wanted)) $out[$id] = $location;
        }
        $out = array_values($out);
        usort($out, static function(array $left, array $right): int {
            $city = strnatcasecmp((string)($left['city'] ?? ''), (string)($right['city'] ?? ''));
            return $city ?: strnatcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
        });
        return $out;
    }

    public static function locationsForProject(int $clientId, int $projectId): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $projectUserIds = self::projectMerchandiserIds($projectId);
        $fields = "l.id,l.name,l.external_ref,l.city,l.county,l.address,l.postal_code,l.tags,l.meta_json,c.name AS category_name,sc.name AS subcategory_name,COALESCE(parent_cat.name,legacy_parent.name) AS parent_category_name";
        $joins = " LEFT JOIN {$px}categories c ON c.id=l.category_id LEFT JOIN {$px}categories sc ON sc.id=l.subcategory_id LEFT JOIN {$px}categories parent_cat ON parent_cat.id=sc.parent_id LEFT JOIN {$px}categories legacy_parent ON legacy_parent.id=c.parent_id ";
        $direct = $wpdb->get_results($wpdb->prepare("SELECT {$fields},0 AS assigned_to FROM {$px}locations l {$joins} WHERE l.is_active=1 AND (l.project_id=%d OR l.client_id=%d)", $projectId, $clientId), ARRAY_A) ?: [];
        $campaign = $wpdb->get_results($wpdb->prepare("SELECT {$fields},cl.assigned_to,cl.id AS campaign_location_id FROM {$px}campaign_locations cl INNER JOIN {$px}locations l ON l.id=cl.location_id AND l.is_active=1 {$joins} WHERE cl.project_id=%d AND cl.is_active=1 ORDER BY cl.id ASC", $projectId), ARRAY_A) ?: [];
        $indexed = [];
        foreach ($direct as $row) $indexed[(int)$row['id']] = $row;
        foreach ($campaign as $row) {
            $id = (int)$row['id'];
            $row['assigned_to'] = self::resolveProjectUserId((int)($row['assigned_to'] ?? 0), $projectUserIds);
            if (isset($indexed[$id]) && empty($row['assigned_to'])) $row['assigned_to'] = self::resolveProjectUserId((int)($indexed[$id]['assigned_to'] ?? 0), $projectUserIds);
            $indexed[$id] = $row;
        }
        foreach ($indexed as &$row) $row['assigned_to'] = self::resolveProjectUserId((int)($row['assigned_to'] ?? 0), $projectUserIds);
        unset($row);
        $locations = array_values($indexed);
        usort($locations, static fn($left, $right) => strnatcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? '')));
        return $locations;
    }

    public static function assignedUserForLocation(int $projectId, int $locationId): int {
        global $wpdb;
        $table = $wpdb->prefix . 'routespro_campaign_locations';
        $projectUserIds = self::projectMerchandiserIds($projectId);
        $assignedUserId = (int)$wpdb->get_var($wpdb->prepare("SELECT assigned_to FROM {$table} WHERE project_id=%d AND location_id=%d AND is_active=1 ORDER BY id DESC LIMIT 1", $projectId, $locationId));
        return self::resolveProjectUserId($assignedUserId, $projectUserIds);
    }

    public static function reconcileBatchAssignments(int $batchId): void {
        global $wpdb;
        if ($batchId <= 0 || !self::getBatch($batchId)) return;
        // Team-wide contract: an insignia assignment can never be owned by one user.
        $wpdb->update(self::assignmentsTable(), [
            'assigned_user_id' => null,
        ], ['batch_id'=>$batchId], ['%d'], ['%d']);
    }

    private static function reconcileActiveAssignments(): void {
        global $wpdb;
        $batchIds = $wpdb->get_col("SELECT id FROM " . self::batchesTable() . " WHERE status IN ('active','draft') ORDER BY id ASC") ?: [];
        foreach ($batchIds as $batchId) self::reconcileBatchAssignments((int)$batchId);
        if ($batchIds) {
            $ids = implode(',', array_map('absint', $batchIds));
            $wpdb->query("UPDATE " . self::assignmentsTable() . " SET assigned_user_id=NULL WHERE batch_id IN ({$ids})");
        }
    }

    private static function decodeJson(string $json): array {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function normaliseSubmittedPrice($value): ?string {
        if ($value === null || $value === '') return null;
        $value = str_replace([' ', '€'], '', (string)$value);
        $value = str_replace(',', '.', $value);
        if (!is_numeric($value)) return null;
        $number = (float)$value;
        if ($number < 0 || $number > 999999) return null;
        return number_format($number, 2, '.', '');
    }

    private static function sanitiseEmailList(string $value): string {
        $emails = [];
        foreach (preg_split('/[,;\s]+/', $value) ?: [] as $email) {
            $email = sanitize_email(trim($email));
            if ($email) $emails[] = $email;
        }
        return implode(',', array_values(array_unique($emails)));
    }
}
