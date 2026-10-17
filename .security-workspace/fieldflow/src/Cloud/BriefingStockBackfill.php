<?php
namespace RoutesPro\Cloud;

use RoutesPro\Core\DataContract;

if (!defined('ABSPATH')) exit;

/**
 * Phase 3C, controlled snapshots for Briefings and Stocks.
 *
 * Briefings are transported as a compact aggregate snapshot (header, sections,
 * questions and store items). Stock batches are transported separately from
 * stock-item chunks so large imports never need to fit in a single Cloud request.
 */
final class BriefingStockBackfill {
    public const VERSION = '1.1.0';
    private const UUID_NAMESPACE = 'fieldflow-cloud-phase3c-backfill-v1';
    private const OPTION_PREFIX = 'fieldflow_cloud_backfill_phase3c_';
    private const LIVE_STOCK_CHUNK_SIZE = 100;
    public const STOCK_AUTO_CRON_HOOK = 'fieldflow_phase3c_stock_auto_tick';
    private const STOCK_AUTO_STATE_OPTION = 'fieldflow_cloud_backfill_phase3c_stock_items_auto_state';
    private const STOCK_AUTO_LOCK_OPTION = 'fieldflow_cloud_backfill_phase3c_stock_items_auto_lock';
    private const STOCK_AUTO_CHUNK_SIZE = 100;
    private const STOCK_AUTO_LOCK_TTL = 120;
    private static bool $hooksRegistered = false;

    private static array $entities = [
        'briefings' => [
            'table' => 'briefings',
            'event' => 'briefing.snapshot',
            'label' => 'Briefings',
            'setting' => 'azure_cloud_sync_briefings',
        ],
        'stock_batches' => [
            'table' => 'stock_batches',
            'event' => 'stock.batch.snapshot',
            'label' => 'Lotes de stock',
            'setting' => 'azure_cloud_sync_stocks',
        ],
        'stock_items' => [
            'table' => 'stock_items',
            'event' => 'stock.items.snapshot',
            'label' => 'Linhas de stock',
            'setting' => 'azure_cloud_sync_stocks',
            'chunked' => true,
        ],
    ];

    public static function registerHooks(): void {
        if (self::$hooksRegistered) return;
        self::$hooksRegistered = true;
        add_action(self::STOCK_AUTO_CRON_HOOK, [self::class, 'runScheduledStockAuto']);
        add_action('wp_ajax_fieldflow_phase3c_stock_auto_tick', [self::class, 'ajaxStockAutoTick']);
        add_action('init', [self::class, 'ensureStockAutoSchedule'], 46);
    }

    public static function ajaxStockAutoTick(): void {
        if (!self::canManage()) wp_send_json_error(['message'=>'Sem permissao para gerir o Data Hub.'], 403);
        check_ajax_referer('routespro_cloud_phase3c_stock_auto_ajax', 'nonce');
        $result = self::stockAutoTick('browser');
        if (!empty($result['ok'])) wp_send_json_success($result);
        wp_send_json_error($result, 409);
    }

    public static function runScheduledStockAuto(): void {
        self::stockAutoTick('background');
    }

    public static function ensureStockAutoSchedule(): void {
        $state = self::stockAutoState();
        if (($state['mode'] ?? 'idle') !== 'running') return;
        $stock = self::entityStatus('stock_items');
        if (!empty($stock['fully_completed'])) {
            self::markStockAutoCompleted($state);
            return;
        }
        self::scheduleStockAutoTick(8);
    }

    public static function startStockAuto(): array {
        $stock = self::entityStatus('stock_items');
        if (empty($stock['available'])) return ['ok'=>false,'reason'=>'table_missing'];
        if (empty($stock['started'])) {
            $started = self::startEntity('stock_items', false);
            if (empty($started['ok'])) return ['ok'=>false,'reason'=>(string)($started['reason'] ?? 'snapshot_not_started')];
            $stock = self::entityStatus('stock_items');
        }
        $validation = self::validateStockAuto($stock);
        if ($validation !== '') return ['ok'=>false,'reason'=>$validation,'stock'=>$stock];
        if (!empty($stock['fully_completed'])) {
            $state = self::stockAutoState();
            self::markStockAutoCompleted($state);
            return ['ok'=>true,'completed'=>true,'stock'=>$stock,'runner'=>self::stockAutoRuntime($stock)];
        }

        $state = self::stockAutoState();
        $state['mode'] = 'running';
        $state['started_at'] = current_time('mysql', true);
        $state['started_ts'] = time();
        $state['baseline_enqueued'] = (int)($stock['enqueued'] ?? 0);
        $state['rows_processed'] = 0;
        $state['chunks_processed'] = 0;
        $state['last_tick_at'] = '';
        $state['last_driver'] = '';
        $state['last_error'] = '';
        $state['completed_at'] = '';
        self::saveStockAutoState($state);
        self::scheduleStockAutoTick(3);
        return ['ok'=>true,'stock'=>$stock,'runner'=>self::stockAutoRuntime($stock, $state)];
    }

    public static function pauseStockAuto(): array {
        $state = self::stockAutoState();
        if (($state['mode'] ?? 'idle') === 'running') {
            $state['mode'] = 'paused';
            $state['paused_at'] = current_time('mysql', true);
            self::saveStockAutoState($state);
        }
        wp_clear_scheduled_hook(self::STOCK_AUTO_CRON_HOOK);
        $stock = self::entityStatus('stock_items');
        return ['ok'=>true,'stock'=>$stock,'runner'=>self::stockAutoRuntime($stock, $state)];
    }

    public static function stockAutoTick(string $driver = 'manual'): array {
        $state = self::stockAutoState();
        if (($state['mode'] ?? 'idle') !== 'running') {
            $stock = self::entityStatus('stock_items');
            return ['ok'=>true,'skipped'=>'not_running','stock'=>$stock,'runner'=>self::stockAutoRuntime($stock, $state)];
        }

        $token = wp_generate_uuid4();
        if (!self::acquireStockAutoLock($token)) {
            $stock = self::entityStatus('stock_items');
            return ['ok'=>true,'locked'=>true,'stock'=>$stock,'runner'=>self::stockAutoRuntime($stock, $state)];
        }

        try {
            $stock = self::entityStatus('stock_items');
            $validation = self::validateStockAuto($stock);
            if ($validation !== '') {
                $state['mode'] = 'error';
                $state['last_error'] = $validation;
                $state['last_tick_at'] = current_time('mysql', true);
                $state['last_driver'] = sanitize_key($driver);
                self::saveStockAutoState($state);
                wp_clear_scheduled_hook(self::STOCK_AUTO_CRON_HOOK);
                return ['ok'=>false,'reason'=>$validation,'stock'=>$stock,'runner'=>self::stockAutoRuntime($stock, $state)];
            }

            if (!empty($stock['fully_completed'])) {
                self::markStockAutoCompleted($state);
                return ['ok'=>true,'completed'=>true,'stock'=>$stock,'runner'=>self::stockAutoRuntime($stock)];
            }

            // Never outrun the Cloud queue. A retrying chunk is drained before
            // a new 100-row chunk advances the persistent source cursor.
            if ((int)($stock['queue_pending'] ?? 0) > 0) {
                $step = self::processPendingNow(25);
            } elseif ((int)($stock['remaining'] ?? 0) > 0) {
                $step = self::enqueueNext('stock_items', self::STOCK_AUTO_CHUNK_SIZE);
            } else {
                $step = self::processPendingNow(25);
            }

            $after = self::entityStatus('stock_items');
            $sourceRows = max(0, (int)($step['source_rows'] ?? 0));
            if ($sourceRows > 0) {
                $state['rows_processed'] = max(0, (int)($state['rows_processed'] ?? 0)) + $sourceRows;
                $state['chunks_processed'] = max(0, (int)($state['chunks_processed'] ?? 0)) + 1;
            }
            $state['last_tick_at'] = current_time('mysql', true);
            $state['last_driver'] = sanitize_key($driver);

            if (empty($step['ok'])) {
                $state['mode'] = 'error';
                $state['last_error'] = sanitize_key((string)($step['reason'] ?? 'runner_step_failed'));
                self::saveStockAutoState($state);
                wp_clear_scheduled_hook(self::STOCK_AUTO_CRON_HOOK);
                return ['ok'=>false,'reason'=>$state['last_error'],'step'=>self::compactAutoStep($step),'stock'=>$after,'runner'=>self::stockAutoRuntime($after, $state)];
            }
            if ((int)($after['queue_dead'] ?? 0) > 0) {
                $state['mode'] = 'error';
                $state['last_error'] = 'dead_jobs_detected';
                self::saveStockAutoState($state);
                wp_clear_scheduled_hook(self::STOCK_AUTO_CRON_HOOK);
                return ['ok'=>false,'reason'=>'dead_jobs_detected','step'=>self::compactAutoStep($step),'stock'=>$after,'runner'=>self::stockAutoRuntime($after, $state)];
            }
            if (!empty($after['fully_completed'])) {
                self::markStockAutoCompleted($state);
                return ['ok'=>true,'completed'=>true,'step'=>self::compactAutoStep($step),'stock'=>$after,'runner'=>self::stockAutoRuntime($after)];
            }

            $state['mode'] = 'running';
            $state['last_error'] = '';
            self::saveStockAutoState($state);
            self::scheduleStockAutoTick(8);
            return ['ok'=>true,'completed'=>false,'step'=>self::compactAutoStep($step),'stock'=>$after,'runner'=>self::stockAutoRuntime($after, $state)];
        } finally {
            self::releaseStockAutoLock($token);
        }
    }

    public static function status(): array {
        $settings = AzureConnectionManager::settings();
        $out = [
            'available' => true,
            'cloud_ready' => AzureConnectionManager::ready(),
            'sync_enabled' => AzureConnectionManager::syncEnabled(),
            'briefings_enabled' => !empty($settings['azure_cloud_sync_briefings']),
            'stocks_enabled' => !empty($settings['azure_cloud_sync_stocks']),
            'queue_ready' => !empty(AzureSyncFoundation::health()['table_ready']),
            'entities' => [],
            'queue_pending' => 0,
            'queue_running' => 0,
            'queue_dead' => 0,
            'queue_completed' => 0,
            'fully_completed' => true,
        ];
        foreach (array_keys(self::$entities) as $entity) {
            $status = self::entityStatus($entity);
            $out['entities'][$entity] = $status;
            if (empty($status['available'])) $out['available'] = false;
            foreach (['queue_pending','queue_running','queue_dead','queue_completed'] as $key) {
                $out[$key] += (int)($status[$key] ?? 0);
            }
            if (empty($status['fully_completed'])) $out['fully_completed'] = false;
        }
        $out['stock_auto'] = self::stockAutoRuntime((array)($out['entities']['stock_items'] ?? []));
        return $out;
    }

    public static function startAll(bool $reset = false): array {
        $result = ['ok'=>true,'entities'=>[]];
        foreach (array_keys(self::$entities) as $entity) {
            $started = self::startEntity($entity, $reset);
            $result['entities'][$entity] = $started;
            if (empty($started['ok'])) $result['ok'] = false;
        }
        $result['status'] = self::status();
        return $result;
    }

    public static function startEntity(string $entity, bool $reset = false): array {
        global $wpdb;
        $cfg = self::entityConfig($entity);
        if (!$cfg) return ['ok'=>false,'reason'=>'invalid_entity'];
        $table = $wpdb->prefix . 'routespro_' . $cfg['table'];
        if (!self::tableExists($table)) return ['ok'=>false,'reason'=>'table_missing','entity'=>$entity];

        $maxOption = self::option($entity, 'max_id');
        $cursorOption = self::option($entity, 'cursor');
        $startedOption = self::option($entity, 'started_at');
        $existingMax = max(0, (int)get_option($maxOption, 0));
        if ($existingMax > 0 && !$reset) {
            return array_merge(['ok'=>true,'already_started'=>true,'entity'=>$entity], self::entityStatus($entity));
        }

        $maxId = max(0, (int)$wpdb->get_var("SELECT MAX(id) FROM {$table}"));
        update_option($cursorOption, 0, false);
        update_option($maxOption, $maxId, false);
        update_option($startedOption, current_time('mysql', true), false);
        return array_merge(['ok'=>true,'already_started'=>false,'entity'=>$entity], self::entityStatus($entity));
    }

    public static function enqueueNext(string $entity, int $limit = 25): array {
        global $wpdb;
        $cfg = self::entityConfig($entity);
        if (!$cfg) return ['ok'=>false,'reason'=>'invalid_entity','enqueued'=>0];
        $limit = max(1, min(100, $limit));
        $status = self::entityStatus($entity);
        if (empty($status['available'])) return ['ok'=>false,'reason'=>'table_missing','enqueued'=>0];
        if (empty($status['started'])) $status = self::startEntity($entity, false);
        if (!AzureConnectionManager::ready()) return ['ok'=>false,'reason'=>'cloud_not_configured','enqueued'=>0];
        if (!AzureConnectionManager::syncEnabled()) return ['ok'=>false,'reason'=>'cloud_sync_disabled','enqueued'=>0];
        if (empty($status['enabled'])) return ['ok'=>false,'reason'=>'data_type_disabled','enqueued'=>0];
        if (empty(AzureSyncFoundation::health()['table_ready'])) return ['ok'=>false,'reason'=>'cloud_queue_missing','enqueued'=>0];

        $kickLimit = max(1, min(25, $limit));
        $dispatchBefore = !empty($status['queue_pending'])
            ? AzureSyncFoundation::dispatchNow($kickLimit)
            : ['ok'=>true,'claimed'=>0,'completed'=>0,'retried'=>0,'dead'=>0];

        $cursor = max(0, (int)($status['cursor'] ?? 0));
        $maxId = max(0, (int)($status['max_id'] ?? 0));
        if ($maxId <= 0) {
            $after = self::entityStatus($entity);
            return ['ok'=>true,'entity'=>$entity,'enqueued'=>0,'source_rows'=>0,'completed'=>!empty($after['fully_completed']),'dispatch_before'=>$dispatchBefore,'status'=>$after];
        }

        if (!empty($cfg['chunked'])) {
            $chunk = self::stockItemCandidateChunk($cursor, $maxId, $limit);
            if (!$chunk) {
                // No eligible row remains. Advance to the fixed snapshot ceiling.
                update_option(self::option($entity, 'cursor'), $maxId, false);
                $after = self::entityStatus($entity);
                return ['ok'=>true,'entity'=>$entity,'enqueued'=>0,'source_rows'=>0,'completed'=>!empty($after['fully_completed']),'dispatch_before'=>$dispatchBefore,'status'=>$after];
            }
            $ids = array_values(array_filter(array_map('absint', wp_list_pluck($chunk, 'id'))));
            $built = self::buildStockItemsContract($ids, true);
            if (empty($built['ok'])) return ['ok'=>false,'reason'=>(string)($built['reason'] ?? 'stock_chunk_failed'),'enqueued'=>0];
            $firstId = min($ids);
            $lastId = max($ids);
            $result = AzureSyncFoundation::enqueue(
                (string)$cfg['event'],
                (array)$built['contract'],
                (array)$built['context'],
                self::snapshotEventUuid($entity, $firstId . '-' . $lastId)
            );
            if (empty($result['ok'])) return ['ok'=>false,'reason'=>(string)($result['reason'] ?? 'enqueue_failed'),'enqueued'=>0];
            update_option(self::option($entity, 'cursor'), $lastId, false);
            $queueNow = self::entityQueueStats($entity);
            $dispatchAfter = ((int)$queueNow['pending'] > 0)
                ? AzureSyncFoundation::dispatchNow($kickLimit)
                : ['ok'=>true,'claimed'=>0,'completed'=>0,'retried'=>0,'dead'=>0];
            $after = self::entityStatus($entity);
            return [
                'ok'=>!empty($dispatchAfter['ok']),
                'entity'=>$entity,
                'enqueued'=>!empty($result['duplicate']) ? 0 : 1,
                'duplicates'=>!empty($result['duplicate']) ? 1 : 0,
                'source_rows'=>count($ids),
                'cursor'=>$lastId,
                'completed'=>!empty($after['fully_completed']),
                'dispatch_before'=>$dispatchBefore,
                'dispatch_after'=>$dispatchAfter,
                'status'=>$after,
            ];
        }

        $table = $wpdb->prefix . 'routespro_' . $cfg['table'];
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$table} WHERE id>%d AND id<=%d ORDER BY id ASC LIMIT %d",
            $cursor, $maxId, $limit
        ), ARRAY_A) ?: [];
        $enqueued = 0;
        $duplicates = 0;
        $failed = 0;
        $lastId = $cursor;
        foreach ($rows as $row) {
            $entityId = absint($row['id'] ?? 0);
            if ($entityId <= 0) continue;
            $built = $entity === 'briefings'
                ? self::buildBriefingContract($entityId, true)
                : self::buildStockBatchContract($entityId, true);
            if (empty($built['ok'])) {
                $lastId = $entityId;
                update_option(self::option($entity, 'cursor'), $lastId, false);
                continue;
            }
            $result = AzureSyncFoundation::enqueue(
                (string)$cfg['event'],
                (array)$built['contract'],
                (array)$built['context'],
                self::snapshotEventUuid($entity, (string)$entityId)
            );
            if (empty($result['ok'])) { $failed++; break; }
            if (!empty($result['duplicate'])) $duplicates++; else $enqueued++;
            $lastId = $entityId;
            update_option(self::option($entity, 'cursor'), $lastId, false);
        }
        $queueNow = self::entityQueueStats($entity);
        $dispatchAfter = ($enqueued > 0 || $duplicates > 0 || (int)$queueNow['pending'] > 0)
            ? AzureSyncFoundation::dispatchNow($kickLimit)
            : ['ok'=>true,'claimed'=>0,'completed'=>0,'retried'=>0,'dead'=>0];
        $after = self::entityStatus($entity);
        return [
            'ok'=>$failed===0 && !empty($dispatchAfter['ok']),
            'entity'=>$entity,
            'enqueued'=>$enqueued,
            'duplicates'=>$duplicates,
            'source_rows'=>$enqueued + $duplicates,
            'failed'=>$failed,
            'cursor'=>$lastId,
            'completed'=>!empty($after['fully_completed']),
            'dispatch_before'=>$dispatchBefore,
            'dispatch_after'=>$dispatchAfter,
            'status'=>$after,
        ];
    }

    public static function processPendingNow(int $limit = 25): array {
        $limit = max(1, min(25, $limit));
        if (!AzureConnectionManager::ready()) return ['ok'=>false,'reason'=>'cloud_not_configured'];
        if (!AzureConnectionManager::syncEnabled()) return ['ok'=>false,'reason'=>'cloud_sync_disabled'];
        $dispatch = AzureSyncFoundation::dispatchNow($limit);
        $status = self::status();
        return [
            'ok'=>!empty($dispatch['ok']),
            'dispatch'=>$dispatch,
            'queue_pending'=>(int)($status['queue_pending'] ?? 0),
            'queue_running'=>(int)($status['queue_running'] ?? 0),
            'queue_dead'=>(int)($status['queue_dead'] ?? 0),
            'completed'=>!empty($status['fully_completed']),
            'status'=>$status,
        ];
    }

    /** Full briefing snapshot used by both live publish and history. */
    public static function buildBriefingContract(int $briefingId, bool $backfill = false): array {
        global $wpdb;
        $briefingId = absint($briefingId);
        if ($briefingId <= 0) return ['ok'=>false,'reason'=>'invalid_briefing'];
        $px = $wpdb->prefix . 'routespro_';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}briefings WHERE id=%d LIMIT 1", $briefingId), ARRAY_A);
        if (!$row) return ['ok'=>false,'reason'=>'briefing_missing'];

        $clientId = absint($row['client_id'] ?? 0);
        $projectId = absint($row['project_id'] ?? 0);
        $scope = self::scopeNames($clientId, $projectId);
        if ($clientId <= 0) $clientId = (int)$scope['client_id'];

        $sections = $wpdb->get_results($wpdb->prepare(
            "SELECT id,retailer_key,section_type,title,content_json,sort_order,created_at FROM {$px}briefing_sections WHERE briefing_id=%d ORDER BY sort_order ASC,id ASC",
            $briefingId
        ), ARRAY_A) ?: [];
        foreach ($sections as &$section) {
            $section['id'] = absint($section['id'] ?? 0);
            $section['sort_order'] = (int)($section['sort_order'] ?? 0);
            $section['content'] = self::decodeJson((string)($section['content_json'] ?? ''));
            unset($section['content_json']);
        }
        unset($section);

        $questions = [];
        $questionTable = $px . 'report_context_questions';
        if (self::tableExists($questionTable)) {
            $questions = $wpdb->get_results($wpdb->prepare(
                "SELECT id,form_id,location_id,campaign_location_id,question_key,question_label,question_type,question_options_json,question_config_json,help_text,is_required,is_active,priority,visibility_rules_json,source_type,briefing_id,briefing_version_no,briefing_section_id,briefing_item_key,valid_from,valid_to,created_at,updated_at
                 FROM {$questionTable}
                 WHERE briefing_id=%d OR (source_type='briefing' AND briefing_id=%d)
                 ORDER BY priority ASC,id ASC",
                $briefingId, $briefingId
            ), ARRAY_A) ?: [];
            foreach ($questions as &$question) {
                foreach (['id','form_id','location_id','campaign_location_id','briefing_id','briefing_version_no','briefing_section_id','priority'] as $key) $question[$key] = (int)($question[$key] ?? 0);
                $question['is_required'] = !empty($question['is_required']);
                $question['is_active'] = !empty($question['is_active']);
                $question['options'] = self::decodeJson((string)($question['question_options_json'] ?? ''));
                $question['config'] = self::decodeJson((string)($question['question_config_json'] ?? ''));
                $question['visibility_rules'] = self::decodeJson((string)($question['visibility_rules_json'] ?? ''));
                unset($question['question_options_json'], $question['question_config_json'], $question['visibility_rules_json']);
            }
            unset($question);
        }

        $storeItems = $wpdb->get_results($wpdb->prepare(
            "SELECT id,retailer_key,external_store_name,normalized_store_name,alias_id,location_id,item_type,title,details_json,valid_from,valid_to,sort_order,created_at
             FROM {$px}briefing_store_items WHERE briefing_id=%d ORDER BY sort_order ASC,id ASC",
            $briefingId
        ), ARRAY_A) ?: [];
        foreach ($storeItems as &$item) {
            foreach (['id','alias_id','location_id','sort_order'] as $key) $item[$key] = (int)($item[$key] ?? 0);
            $item['details'] = self::decodeJson((string)($item['details_json'] ?? ''));
            unset($item['details_json']);
        }
        unset($item);

        $data = [
            'briefing_id'=>$briefingId,
            'client_id'=>$clientId,
            'client_name'=>$scope['client_name'],
            'project_id'=>$projectId,
            'project_name'=>$scope['project_name'],
            'title'=>sanitize_text_field((string)($row['title'] ?? '')),
            'year_no'=>(int)($row['year_no'] ?? 0),
            'week_no'=>(int)($row['week_no'] ?? 0),
            'valid_from'=>(string)($row['valid_from'] ?? ''),
            'valid_to'=>(string)($row['valid_to'] ?? ''),
            'status'=>sanitize_key((string)($row['status'] ?? 'draft')),
            'version_no'=>(int)($row['version_no'] ?? 1),
            'source_filename'=>sanitize_file_name((string)($row['source_filename'] ?? '')),
            'source_hash'=>sanitize_text_field((string)($row['source_hash'] ?? '')),
            'published_at'=>(string)($row['published_at'] ?? ''),
            'created_at'=>(string)($row['created_at'] ?? ''),
            'updated_at'=>(string)($row['updated_at'] ?? ''),
            'sections'=>$sections,
            'questions'=>$questions,
            'store_items'=>$storeItems,
            'section_count'=>count($sections),
            'question_count'=>count($questions),
            'store_item_count'=>count($storeItems),
            'target_location_count'=>count(array_unique(array_filter(array_map(static fn($i)=>absint($i['location_id'] ?? 0), $storeItems)))),
            'cloud_backfill'=>$backfill,
            'cloud_backfill_version'=>$backfill ? self::VERSION : '',
        ];
        $contract = DataContract::state('briefing', $data);
        $context = [
            'entity_type'=>'briefing','entity_id'=>$briefingId,'briefing_id'=>$briefingId,
            'client_id'=>$clientId,'project_id'=>$projectId,
        ];
        return ['ok'=>true,'contract'=>$contract,'context'=>$context,'data'=>$data];
    }

    public static function buildStockBatchContract(int $batchId, bool $backfill = false): array {
        global $wpdb;
        $batchId = absint($batchId);
        if ($batchId <= 0) return ['ok'=>false,'reason'=>'invalid_stock_batch'];
        $px = $wpdb->prefix . 'routespro_';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}stock_batches WHERE id=%d LIMIT 1", $batchId), ARRAY_A);
        if (!$row) return ['ok'=>false,'reason'=>'stock_batch_missing'];
        $clientId = absint($row['client_id'] ?? 0);
        $projectId = absint($row['project_id'] ?? 0);
        $scope = self::scopeNames($clientId, $projectId);
        if ($clientId <= 0) $clientId = (int)$scope['client_id'];
        $data = [
            'batch_id'=>$batchId,
            'client_id'=>$clientId,'client_name'=>$scope['client_name'],
            'project_id'=>$projectId,'project_name'=>$scope['project_name'],
            'insignia'=>sanitize_text_field((string)($row['insignia'] ?? '')),
            'snapshot_date'=>(string)($row['snapshot_date'] ?? ''),
            'status'=>sanitize_key((string)($row['status'] ?? 'draft')),
            'source_filename'=>sanitize_file_name((string)($row['source_filename'] ?? '')),
            'sheet_name'=>sanitize_text_field((string)($row['sheet_name'] ?? '')),
            'header_row'=>(int)($row['header_row'] ?? 1),
            'row_count'=>(int)($row['row_count'] ?? 0),
            'store_count'=>(int)($row['store_count'] ?? 0),
            'product_count'=>(int)($row['product_count'] ?? 0),
            'matched_store_count'=>(int)($row['matched_store_count'] ?? 0),
            'unmatched_row_count'=>(int)($row['unmatched_row_count'] ?? 0),
            'total_stock'=>(float)($row['total_stock'] ?? 0),
            'zero_count'=>(int)($row['zero_count'] ?? 0),
            'low_count'=>(int)($row['low_count'] ?? 0),
            'negative_count'=>(int)($row['negative_count'] ?? 0),
            'import_hash'=>sanitize_text_field((string)($row['import_hash'] ?? '')),
            'published_at'=>(string)($row['published_at'] ?? ''),
            'created_at'=>(string)($row['created_at'] ?? ''),
            'updated_at'=>(string)($row['updated_at'] ?? ''),
            'cloud_backfill'=>$backfill,
            'cloud_backfill_version'=>$backfill ? self::VERSION : '',
        ];
        $contract = DataContract::state('stock_batch', $data);
        $context = ['entity_type'=>'stock_batch','entity_id'=>$batchId,'client_id'=>$clientId,'project_id'=>$projectId];
        return ['ok'=>true,'contract'=>$contract,'context'=>$context,'data'=>$data];
    }

    public static function buildStockItemsContract(array $itemIds, bool $backfill = false): array {
        global $wpdb;
        $itemIds = array_values(array_unique(array_filter(array_map('absint', $itemIds))));
        if (!$itemIds) return ['ok'=>false,'reason'=>'no_stock_items'];
        $px = $wpdb->prefix . 'routespro_';
        $placeholders = implode(',', array_fill(0, count($itemIds), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*,b.snapshot_date,b.insignia,b.status AS batch_status
             FROM {$px}stock_items i
             INNER JOIN {$px}stock_batches b ON b.id=i.batch_id
             WHERE i.id IN ({$placeholders}) ORDER BY i.id ASC",
            ...$itemIds
        ), ARRAY_A) ?: [];
        if (!$rows) return ['ok'=>false,'reason'=>'stock_items_missing'];
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'stock_item_id'=>absint($row['id'] ?? 0),
                'batch_id'=>absint($row['batch_id'] ?? 0),
                'client_id'=>absint($row['client_id'] ?? 0),
                'project_id'=>absint($row['project_id'] ?? 0),
                'location_id'=>absint($row['location_id'] ?? 0),
                'source_store_key'=>sanitize_text_field((string)($row['source_store_key'] ?? '')),
                'store_external_ref'=>sanitize_text_field((string)($row['store_external_ref'] ?? '')),
                'store_name'=>sanitize_text_field((string)($row['store_name'] ?? '')),
                'locality'=>sanitize_text_field((string)($row['locality'] ?? '')),
                'article_ref'=>sanitize_text_field((string)($row['article_ref'] ?? '')),
                'ean'=>sanitize_text_field((string)($row['ean'] ?? '')),
                'product_name'=>sanitize_text_field((string)($row['product_name'] ?? '')),
                'unit'=>sanitize_text_field((string)($row['unit'] ?? '')),
                'conversion_factor'=>(float)($row['conversion_factor'] ?? 1),
                'stock_total'=>(float)($row['stock_total'] ?? 0),
                'match_confidence'=>(int)($row['match_confidence'] ?? 0),
                'match_method'=>sanitize_key((string)($row['match_method'] ?? '')),
                'cardex_item_id'=>absint($row['cardex_item_id'] ?? 0),
                'product_match_confidence'=>(int)($row['product_match_confidence'] ?? 0),
                'product_match_method'=>sanitize_key((string)($row['product_match_method'] ?? '')),
                'snapshot_date'=>(string)($row['snapshot_date'] ?? ''),
                'insignia'=>sanitize_text_field((string)($row['insignia'] ?? '')),
                'batch_status'=>sanitize_key((string)($row['batch_status'] ?? '')),
                'created_at'=>(string)($row['created_at'] ?? ''),
            ];
        }
        $first = $items[0];
        $data = [
            'batch_id'=>absint($first['batch_id'] ?? 0),
            'client_id'=>absint($first['client_id'] ?? 0),
            'project_id'=>absint($first['project_id'] ?? 0),
            'snapshot_date'=>(string)($first['snapshot_date'] ?? ''),
            'insignia'=>(string)($first['insignia'] ?? ''),
            'items'=>$items,
            'item_count'=>count($items),
            'first_item_id'=>absint($items[0]['stock_item_id'] ?? 0),
            'last_item_id'=>absint($items[count($items)-1]['stock_item_id'] ?? 0),
            'cloud_backfill'=>$backfill,
            'cloud_backfill_version'=>$backfill ? self::VERSION : '',
        ];
        $contract = DataContract::state('stock_items', $data);
        $context = [
            'entity_type'=>'stock_items_chunk','entity_id'=>absint($data['last_item_id']),
            'client_id'=>absint($data['client_id']),'project_id'=>absint($data['project_id']),
        ];
        return ['ok'=>true,'contract'=>$contract,'context'=>$context,'data'=>$data];
    }

    /** Live stock import fan-out: one batch snapshot plus bounded item chunks. */
    public static function enqueueLiveStockImport(int $batchId, string $sourceEventUuid): array {
        global $wpdb;
        $batch = self::buildStockBatchContract($batchId, false);
        if (empty($batch['ok'])) return ['ok'=>false,'reason'=>(string)($batch['reason'] ?? 'batch_missing')];
        $summary = ['ok'=>true,'batch'=>null,'item_chunks'=>0,'items'=>0,'failed'=>0];
        $summary['batch'] = AzureSyncFoundation::enqueue('stock.imported', (array)$batch['contract'], (array)$batch['context'], $sourceEventUuid);
        if (empty($summary['batch']['ok'])) return ['ok'=>false,'reason'=>'batch_enqueue_failed'];

        $table = $wpdb->prefix . 'routespro_stock_items';
        $cursor = 0;
        $chunkNo = 0;
        while (true) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$table} WHERE batch_id=%d AND id>%d ORDER BY id ASC LIMIT %d",
                $batchId, $cursor, self::LIVE_STOCK_CHUNK_SIZE
            ), ARRAY_A) ?: [];
            if (!$rows) break;
            $ids = array_values(array_filter(array_map('absint', wp_list_pluck($rows, 'id'))));
            if (!$ids) break;
            $built = self::buildStockItemsContract($ids, false);
            if (empty($built['ok'])) { $summary['failed']++; break; }
            $chunkNo++;
            $eventUuid = self::relatedEventUuid($sourceEventUuid, 'stock.items.snapshot', $batchId . ':' . $chunkNo . ':' . min($ids) . ':' . max($ids));
            $queued = AzureSyncFoundation::enqueue('stock.items.snapshot', (array)$built['contract'], (array)$built['context'], $eventUuid);
            if (empty($queued['ok'])) { $summary['failed']++; break; }
            $summary['item_chunks']++;
            $summary['items'] += count($ids);
            $cursor = max($ids);
        }
        $summary['ok'] = $summary['failed'] === 0;
        return $summary;
    }

    public static function relatedEventUuid(string $sourceEventUuid, string $eventName, $entityId): string {
        return self::uuidFromSeed('live|' . sanitize_text_field($sourceEventUuid) . '|' . sanitize_key($eventName) . '|' . sanitize_text_field((string)$entityId));
    }

    private static function stockItemCandidateChunk(int $cursor, int $maxId, int $limit): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $first = $wpdb->get_row($wpdb->prepare(
            "SELECT i.id,i.batch_id FROM {$px}stock_items i INNER JOIN {$px}stock_batches b ON b.id=i.batch_id WHERE i.id>%d AND i.id<=%d ORDER BY i.id ASC LIMIT 1",
            $cursor, $maxId
        ), ARRAY_A);
        if (!$first) return [];
        $batchId = absint($first['batch_id'] ?? 0);
        if ($batchId <= 0) return [];
        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.id FROM {$px}stock_items i INNER JOIN {$px}stock_batches b ON b.id=i.batch_id WHERE i.batch_id=%d AND i.id>%d AND i.id<=%d ORDER BY i.id ASC LIMIT %d",
            $batchId, $cursor, $maxId, $limit
        ), ARRAY_A) ?: [];
    }

    private static function entityStatus(string $entity): array {
        global $wpdb;
        $cfg = self::entityConfig($entity);
        if (!$cfg) return ['available'=>false,'started'=>false,'entity'=>$entity];
        $table = $wpdb->prefix . 'routespro_' . $cfg['table'];
        if (!self::tableExists($table)) {
            return ['available'=>false,'started'=>false,'entity'=>$entity,'label'=>$cfg['label'],'cursor'=>0,'max_id'=>0,'total'=>0,'remaining'=>0,'enqueued'=>0,'queue_pending'=>0,'queue_running'=>0,'queue_dead'=>0,'queue_completed'=>0,'fully_completed'=>false,'enabled'=>false];
        }
        $settings = AzureConnectionManager::settings();
        $enabled = !empty($settings[$cfg['setting']]);
        $cursor = max(0, (int)get_option(self::option($entity, 'cursor'), 0));
        $maxId = max(0, (int)get_option(self::option($entity, 'max_id'), 0));
        $startedAt = (string)get_option(self::option($entity, 'started_at'), '');
        $started = $maxId > 0 || $startedAt !== '';
        $total = 0;
        $remaining = 0;
        if ($started && $maxId > 0) {
            $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id<=%d", $maxId));
            $remaining = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id>%d AND id<=%d", $cursor, $maxId));
        }
        $queue = self::entityQueueStats($entity);
        return [
            'available'=>true,'enabled'=>$enabled,'entity'=>$entity,'label'=>$cfg['label'],'event_name'=>$cfg['event'],
            'started'=>$started,'started_at'=>$startedAt,'cursor'=>$cursor,'max_id'=>$maxId,'total'=>$total,'remaining'=>$remaining,
            'enqueued'=>max(0, $total - $remaining),
            'queue_pending'=>(int)$queue['pending'],'queue_running'=>(int)$queue['running'],'queue_dead'=>(int)$queue['dead'],'queue_completed'=>(int)$queue['completed'],
            'fully_completed'=>$started && $remaining===0 && (int)$queue['pending']===0 && (int)$queue['running']===0 && (int)$queue['dead']===0,
        ];
    }

    private static function entityQueueStats(string $entity): array {
        global $wpdb;
        $cfg = self::entityConfig($entity);
        $stats = ['pending'=>0,'running'=>0,'dead'=>0,'completed'=>0];
        if (!$cfg) return $stats;
        $table = AzureSyncFoundation::table();
        if (!self::tableExists($table)) return $stats;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(status='pending') pending,SUM(status='running') running,SUM(status='dead') dead,SUM(status='completed') completed
             FROM {$table}
             WHERE destination='azure' AND event_name=%s AND payload_json LIKE %s",
            (string)$cfg['event'], '%\"cloud_backfill\":true%'
        ), ARRAY_A) ?: [];
        foreach ($stats as $key=>$value) $stats[$key] = (int)($row[$key] ?? 0);
        return $stats;
    }

    private static function scopeNames(int $clientId, int $projectId): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $projectName = '';
        if ($projectId > 0) {
            $project = $wpdb->get_row($wpdb->prepare("SELECT id,client_id,name FROM {$px}projects WHERE id=%d LIMIT 1", $projectId), ARRAY_A);
            if ($project) {
                if ($clientId <= 0) $clientId = absint($project['client_id'] ?? 0);
                $projectName = sanitize_text_field((string)($project['name'] ?? ''));
            }
        }
        $clientName = $clientId > 0 ? sanitize_text_field((string)$wpdb->get_var($wpdb->prepare("SELECT name FROM {$px}clients WHERE id=%d LIMIT 1", $clientId))) : '';
        return ['client_id'=>$clientId,'client_name'=>$clientName,'project_name'=>$projectName];
    }

    private static function decodeJson(string $raw) {
        if ($raw === '') return null;
        $value = json_decode($raw, true);
        return is_array($value) ? $value : null;
    }

    private static function validateStockAuto(array $stock): string {
        if (!AzureConnectionManager::ready()) return 'cloud_not_configured';
        if (!AzureConnectionManager::syncEnabled()) return 'cloud_sync_disabled';
        if (empty($stock['enabled'])) return 'data_type_disabled';
        if (empty(AzureSyncFoundation::health()['table_ready'])) return 'cloud_queue_missing';
        if ((int)($stock['queue_dead'] ?? 0) > 0) return 'dead_jobs_detected';
        return '';
    }

    private static function stockAutoState(): array {
        $state = get_option(self::STOCK_AUTO_STATE_OPTION, []);
        if (!is_array($state)) $state = [];
        return wp_parse_args($state, [
            'mode'=>'idle','started_at'=>'','started_ts'=>0,'baseline_enqueued'=>0,
            'rows_processed'=>0,'chunks_processed'=>0,'last_tick_at'=>'','last_driver'=>'',
            'last_error'=>'','paused_at'=>'','completed_at'=>'',
        ]);
    }

    private static function saveStockAutoState(array $state): void {
        $allowedModes = ['idle','running','paused','completed','error'];
        $mode = sanitize_key((string)($state['mode'] ?? 'idle'));
        $state['mode'] = in_array($mode, $allowedModes, true) ? $mode : 'idle';
        $state['started_at'] = sanitize_text_field((string)($state['started_at'] ?? ''));
        $state['started_ts'] = max(0, (int)($state['started_ts'] ?? 0));
        $state['baseline_enqueued'] = max(0, (int)($state['baseline_enqueued'] ?? 0));
        $state['rows_processed'] = max(0, (int)($state['rows_processed'] ?? 0));
        $state['chunks_processed'] = max(0, (int)($state['chunks_processed'] ?? 0));
        $state['last_tick_at'] = sanitize_text_field((string)($state['last_tick_at'] ?? ''));
        $state['last_driver'] = sanitize_key((string)($state['last_driver'] ?? ''));
        $state['last_error'] = sanitize_key((string)($state['last_error'] ?? ''));
        $state['paused_at'] = sanitize_text_field((string)($state['paused_at'] ?? ''));
        $state['completed_at'] = sanitize_text_field((string)($state['completed_at'] ?? ''));
        update_option(self::STOCK_AUTO_STATE_OPTION, $state, false);
    }

    private static function markStockAutoCompleted(array $state): void {
        $state['mode'] = 'completed';
        $state['completed_at'] = current_time('mysql', true);
        $state['last_error'] = '';
        self::saveStockAutoState($state);
        wp_clear_scheduled_hook(self::STOCK_AUTO_CRON_HOOK);
    }

    private static function stockAutoRuntime(array $stock = [], ?array $state = null): array {
        if (!$stock) $stock = self::entityStatus('stock_items');
        $state = $state ?? self::stockAutoState();
        $mode = sanitize_key((string)($state['mode'] ?? 'idle'));
        $total = max(0, (int)($stock['total'] ?? 0));
        $enqueued = max(0, (int)($stock['enqueued'] ?? 0));
        $remaining = max(0, (int)($stock['remaining'] ?? 0));
        $baseline = max(0, (int)($state['baseline_enqueued'] ?? 0));
        $processedRun = max(0, $enqueued - $baseline);
        $startedTs = max(0, (int)($state['started_ts'] ?? 0));
        $elapsed = $startedTs > 0 ? max(1, time() - $startedTs) : 0;
        $rowsPerMinute = ($elapsed > 0 && $processedRun > 0) ? round(($processedRun * 60) / $elapsed, 1) : 0.0;
        $etaSeconds = $rowsPerMinute > 0 ? (int)ceil(($remaining / $rowsPerMinute) * 60) : 0;
        $progress = $total > 0 ? round(min(100, ($enqueued / $total) * 100), 1) : 100.0;
        if (!empty($stock['fully_completed']) && $mode !== 'completed') $mode = 'completed';
        return [
            'mode'=>$mode,
            'active'=>$mode === 'running',
            'paused'=>$mode === 'paused',
            'error'=>$mode === 'error',
            'completed'=>$mode === 'completed' || !empty($stock['fully_completed']),
            'chunk_size'=>self::STOCK_AUTO_CHUNK_SIZE,
            'total'=>$total,
            'enqueued'=>$enqueued,
            'remaining'=>$remaining,
            'progress_percent'=>$progress,
            'processed_this_run'=>$processedRun,
            'rows_per_minute'=>$rowsPerMinute,
            'eta_seconds'=>$etaSeconds,
            'queue_pending'=>(int)($stock['queue_pending'] ?? 0),
            'queue_running'=>(int)($stock['queue_running'] ?? 0),
            'queue_dead'=>(int)($stock['queue_dead'] ?? 0),
            'chunks_processed'=>max(0, (int)($state['chunks_processed'] ?? 0)),
            'started_at'=>(string)($state['started_at'] ?? ''),
            'last_tick_at'=>(string)($state['last_tick_at'] ?? ''),
            'last_driver'=>(string)($state['last_driver'] ?? ''),
            'last_error'=>(string)($state['last_error'] ?? ''),
            'completed_at'=>(string)($state['completed_at'] ?? ''),
            'poll_delay_ms'=>(int)($stock['queue_pending'] ?? 0) > 0 ? 2500 : 650,
        ];
    }

    private static function compactAutoStep(array $step): array {
        return [
            'ok'=>!empty($step['ok']),
            'reason'=>sanitize_key((string)($step['reason'] ?? '')),
            'source_rows'=>max(0, (int)($step['source_rows'] ?? 0)),
            'enqueued'=>max(0, (int)($step['enqueued'] ?? 0)),
            'duplicates'=>max(0, (int)($step['duplicates'] ?? 0)),
            'completed'=>!empty($step['completed']),
            'dispatch_completed'=>max(0, (int)($step['dispatch_after']['completed'] ?? $step['dispatch']['completed'] ?? 0)),
            'dispatch_retried'=>max(0, (int)($step['dispatch_after']['retried'] ?? $step['dispatch']['retried'] ?? 0)),
            'dispatch_dead'=>max(0, (int)($step['dispatch_after']['dead'] ?? $step['dispatch']['dead'] ?? 0)),
        ];
    }

    private static function scheduleStockAutoTick(int $delay): void {
        $state = self::stockAutoState();
        if (($state['mode'] ?? 'idle') !== 'running') return;
        $delay = max(2, min(60, $delay));
        $next = wp_next_scheduled(self::STOCK_AUTO_CRON_HOOK);
        if ($next && (int)$next <= time() + $delay + 3) return;
        if ($next) wp_clear_scheduled_hook(self::STOCK_AUTO_CRON_HOOK);
        wp_schedule_single_event(time() + $delay, self::STOCK_AUTO_CRON_HOOK);
    }

    private static function acquireStockAutoLock(string $token): bool {
        $payload = ['token'=>$token,'started_at'=>time()];
        if (add_option(self::STOCK_AUTO_LOCK_OPTION, $payload, '', 'no')) return true;
        $existing = get_option(self::STOCK_AUTO_LOCK_OPTION, []);
        $startedAt = is_array($existing) ? absint($existing['started_at'] ?? 0) : 0;
        if ($startedAt > 0 && (time() - $startedAt) > self::STOCK_AUTO_LOCK_TTL) {
            delete_option(self::STOCK_AUTO_LOCK_OPTION);
            return add_option(self::STOCK_AUTO_LOCK_OPTION, $payload, '', 'no');
        }
        return false;
    }

    private static function releaseStockAutoLock(string $token): void {
        $existing = get_option(self::STOCK_AUTO_LOCK_OPTION, []);
        if (is_array($existing) && hash_equals((string)($existing['token'] ?? ''), $token)) delete_option(self::STOCK_AUTO_LOCK_OPTION);
    }

    private static function canManage(): bool {
        return current_user_can('routespro_manage') || current_user_can('manage_options');
    }

    private static function snapshotEventUuid(string $entity, string $entityKey): string {
        return self::uuidFromSeed('snapshot|' . sanitize_key($entity) . '|' . sanitize_text_field($entityKey));
    }

    private static function uuidFromSeed(string $seed): string {
        $tenant = TenantIdentity::tenantId();
        $hash = substr(sha1(self::UUID_NAMESPACE . '|' . $tenant . '|' . $seed), 0, 32);
        $hash[12] = '5';
        $variant = hexdec($hash[16]);
        $hash[16] = dechex(($variant & 0x3) | 0x8);
        return sprintf('%s-%s-%s-%s-%s', substr($hash,0,8), substr($hash,8,4), substr($hash,12,4), substr($hash,16,4), substr($hash,20,12));
    }

    private static function entityConfig(string $entity): ?array {
        $entity = sanitize_key($entity);
        return self::$entities[$entity] ?? null;
    }

    private static function option(string $entity, string $suffix): string {
        return self::OPTION_PREFIX . sanitize_key($entity) . '_' . sanitize_key($suffix);
    }

    private static function tableExists(string $table): bool {
        global $wpdb;
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}
