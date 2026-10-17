<?php
namespace RoutesPro\Mapping;

use RoutesPro\Core\AuditLog;
use RoutesPro\Core\EventBus;
use RoutesPro\Stores\StoreIdentityAuthority;

if (!defined('ABSPATH')) exit;

class MappingService {
    private static bool $applying = false;

    public static function registerHooks(): void {
        add_action('fieldflow_briefing_imported', [self::class, 'onSourceChanged'], 20);
        add_action('fieldflow_stock_imported', [self::class, 'onSourceChanged'], 20);
        add_action('fieldflow_mapping_source_changed', [self::class, 'onSourceChanged'], 20);
        add_action('admin_init', [self::class, 'maybeBootstrap'], 25);
    }

    public static function maybeBootstrap(): void {
        if (!is_admin() || !current_user_can('routespro_manage')) return;
        if (!MappingRepository::tablesReady()) return;
        if ((string)get_option('fieldflow_mapping_bootstrap_version', '') === '2.2.217') return;
        if (get_transient('fieldflow_mapping_bootstrap_lock')) return;
        set_transient('fieldflow_mapping_bootstrap_lock', 1, 120);
        try {
            self::syncAll([], 1000);
            update_option('fieldflow_mapping_bootstrap_version', '2.2.217', false);
        } catch (\Throwable $e) {
            if (class_exists('\RoutesPro\Support\Logger')) \RoutesPro\Support\Logger::captureThrowable('Falha ao inicializar o Centro Universal de Mapping.', $e, ['context_key'=>'mapping_bootstrap']);
        } finally {
            delete_transient('fieldflow_mapping_bootstrap_lock');
        }
    }

    public static function onSourceChanged(): void {
        if (!MappingRepository::tablesReady()) return;
        if (get_transient('fieldflow_mapping_sync_lock')) return;
        set_transient('fieldflow_mapping_sync_lock', 1, 30);
        try { self::syncAll([], 500); } catch (\Throwable $e) {
            if (class_exists('\\RoutesPro\\Support\\Logger')) \RoutesPro\Support\Logger::captureThrowable('Falha ao sincronizar o Centro Universal de Mapping.', $e, ['context_key'=>'mapping_sync']);
        }
        delete_transient('fieldflow_mapping_sync_lock');
    }

    public static function syncAll(array $filters = [], int $limitPerSource = 5000): array {
        if (!MappingRepository::tablesReady()) return ['total'=>0,'briefing'=>0,'implementation'=>0,'stock'=>0,'product'=>0,'cardex'=>0];
        set_transient('fieldflow_mapping_sync_lock', 1, 60);
        $summary = ['total'=>0,'briefing'=>0,'implementation'=>0,'stock'=>0,'product'=>0,'cardex'=>0];
        try {
            $summary['briefing'] = self::syncBriefingAliases($filters, $limitPerSource);
            $summary['implementation'] = self::syncImplementationAliases($filters, $limitPerSource);
            $summary['stock'] = self::syncStockStores($filters, $limitPerSource);
            $summary['product'] = self::syncStockProducts($filters, $limitPerSource);
            $summary['cardex'] = self::syncCardexAssignments($filters, $limitPerSource);
            $summary['total'] = array_sum(array_intersect_key($summary, array_flip(['briefing','implementation','stock','product','cardex'])));
            AuditLog::record('mapping.sync', 'mapping_center', 0, null, $summary, ['client_id'=>absint($filters['client_id']??0),'project_id'=>absint($filters['project_id']??0)]);
            EventBus::emit('mapping.synced', $summary, ['entity_type'=>'mapping_center','entity_id'=>0,'client_id'=>absint($filters['client_id']??0),'project_id'=>absint($filters['project_id']??0)]);
        } finally {
            delete_transient('fieldflow_mapping_sync_lock');
        }
        return $summary;
    }

    public static function generateSuggestions(array $filters = [], int $limit = 250): array {
        $filters['status'] = $filters['status'] ?? 'pending';
        $records = MappingRepository::list($filters, max(1,min(500,$limit)), 0);
        if (!$records && ($filters['status'] ?? '') === 'pending') {
            $filters['status'] = 'suggested';
            $records = MappingRepository::list($filters, max(1,min(500,$limit)), 0);
        }
        $locationCache=[];$productCache=[];$updated=0;$without=0;
        foreach($records as $record){
            if(!empty($record['is_locked'])||in_array((string)$record['status'],['confirmed','rejected'],true))continue;
            $alias=MappingRepository::findAlias((string)$record['entity_type'],(int)$record['client_id'],(int)$record['project_id'],(string)$record['source_label']);
            if ($alias && (string)($record['entity_type'] ?? '') === 'store' && !self::storeTargetCompatible($record, (int)($alias['target_id'] ?? 0))) {
                $alias = null; // 9DN: even a manually learned alias must obey identity.
            }
            if($alias&&self::targetExists((string)$record['entity_type'],(int)$record['client_id'],(int)$record['project_id'],(int)$alias['target_id'])){
                $candidates=[['target_id'=>(int)$alias['target_id'],'label'=>self::targetLabel((string)$record['entity_type'],(int)$alias['target_id']),'secondary'=>'Alias confirmado anteriormente','confidence'=>max(95,(int)$alias['confidence']),'signals'=>['alias conhecido']]];
            }elseif($record['entity_type']==='store'){
                $cacheKey=(int)$record['client_id'].':'.(int)$record['project_id'];
                if(!isset($locationCache[$cacheKey]))$locationCache[$cacheKey]=self::locationTargets((int)$record['client_id'],(int)$record['project_id'],'',10000);
                $candidates=MappingEngine::storeCandidates($record,$locationCache[$cacheKey]);
            }elseif($record['entity_type']==='product'){
                $cacheKey=(int)$record['client_id'].':'.(int)$record['project_id'];
                if(!isset($productCache[$cacheKey]))$productCache[$cacheKey]=self::productTargets((int)$record['client_id'],(int)$record['project_id'],'',10000);
                $candidates=MappingEngine::productCandidates($record,$productCache[$cacheKey]);
            }else continue;
            $top=$candidates[0]??null;
            if(!$top){MappingRepository::update((int)$record['id'],['candidate_json'=>[],'confidence'=>0,'match_method'=>'no_candidate','status'=>'pending']);$without++;continue;}
            $gap=(int)$top['confidence']-(int)($candidates[1]['confidence']??0);
            $method=in_array('alias conhecido',(array)($top['signals']??[]),true)?'known_alias':'smart_similarity';
            MappingRepository::update((int)$record['id'],['candidate_json'=>$candidates,'target_id'=>(int)$top['target_id'],'confidence'=>(int)$top['confidence'],'match_method'=>$method,'status'=>'suggested','meta_json'=>array_merge((array)$record['meta_json'],['candidate_gap'=>$gap,'suggested_at'=>current_time('mysql')])]);
            $updated++;
        }
        $result=['processed'=>count($records),'suggested'=>$updated,'without_candidate'=>$without];
        AuditLog::record('mapping.suggestions.generated','mapping_center',0,null,$result,['client_id'=>absint($filters['client_id']??0),'project_id'=>absint($filters['project_id']??0)]);
        return $result;
    }

    public static function approveSuggested(array $ids, string $reason = ''): array {
        $applied=0;$skipped=0;$errors=[];$batch=wp_generate_uuid4();
        foreach(array_values(array_unique(array_filter(array_map('absint',$ids)))) as $id){
            try{
                $record=MappingRepository::get($id);
                if(!$record||!in_array((string)$record['status'],['suggested','pending'],true)){$skipped++;continue;}
                $targetId=(int)($record['target_id']??0);
                if(!$targetId&&!empty($record['candidate_json'][0]['target_id']))$targetId=(int)$record['candidate_json'][0]['target_id'];
                if(!$targetId){$skipped++;continue;}
                self::apply($id,$targetId,'confirmed',$reason!==''?$reason:'Aprovação em massa',$batch);
                $applied++;
            }catch(\Throwable $e){$errors[]='#'.$id.': '.$e->getMessage();}
        }
        return ['applied'=>$applied,'skipped'=>$skipped,'errors'=>$errors,'batch_uuid'=>$batch];
    }

    public static function rejectMany(array $ids, string $reason = ''): array {
        $rejected=0;$errors=[];$batch=wp_generate_uuid4();
        foreach(array_values(array_unique(array_filter(array_map('absint',$ids)))) as $id){
            try{self::apply($id,0,'rejected',$reason!==''?$reason:'Rejeitado em massa',$batch);$rejected++;}catch(\Throwable $e){$errors[]='#'.$id.': '.$e->getMessage();}
        }
        return ['rejected'=>$rejected,'errors'=>$errors,'batch_uuid'=>$batch];
    }

    public static function apply(int $mappingId, int $targetId, string $status = 'confirmed', string $reason = '', string $batchUuid = ''): array {
        $record=MappingRepository::get($mappingId);
        if(!$record)throw new \RuntimeException('Mapping não encontrado.');
        if(!empty($record['is_locked']))throw new \RuntimeException('Este mapping é informativo e está protegido.');
        $status=sanitize_key($status);
        if(!in_array($status,['confirmed','rejected','pending'],true))throw new \RuntimeException('Estado de mapping inválido.');
        if($status==='confirmed'&&(!$targetId||!self::targetExists((string)$record['entity_type'],(int)$record['client_id'],(int)$record['project_id'],$targetId)))throw new \RuntimeException('O destino selecionado não pertence ao âmbito deste mapping.');
        if ($status === 'confirmed' && (string)$record['entity_type'] === 'store' && !self::storeTargetCompatible($record, $targetId)) {
            throw new \RuntimeException('Insígnia do PDV incompatível ou por identificar. A associação não foi aplicada.');
        }
        $before=$record;
        self::$applying=true;
        try{
            self::applyToSource($record,$status==='confirmed'?$targetId:0,$status);
            $confidence=$status==='confirmed'?max(100,(int)$record['confidence']):0;
            MappingRepository::update($mappingId,['target_id'=>$status==='confirmed'?$targetId:0,'confidence'=>$confidence,'match_method'=>$status==='confirmed'?'manual_confirmed':$status,'status'=>$status,'last_synced_at'=>current_time('mysql')]);
        }finally{self::$applying=false;}
        $after=MappingRepository::get($mappingId)?:$record;
        $action=$status==='confirmed'?'confirm':($status==='rejected'?'reject':'reset');
        $historyId=MappingRepository::addHistory($mappingId,$action,$before,$after,$reason,$batchUuid);
        if($status==='confirmed')MappingRepository::upsertAlias(['entity_type'=>$record['entity_type'],'client_id'=>(int)$record['client_id'],'project_id'=>(int)$record['project_id'],'alias_label'=>(string)$record['source_label'],'target_type'=>(string)$record['target_type'],'target_id'=>$targetId,'confidence'=>100,'source_module'=>(string)$record['source_module']]);
        $context=['client_id'=>(int)$record['client_id'],'project_id'=>(int)$record['project_id'],'location_id'=>$record['entity_type']==='store'?$targetId:0];
        AuditLog::record('mapping.'.$action,'mapping',$mappingId,$before,$after,$context,['reason'=>$reason,'history_id'=>$historyId]);
        EventBus::emit('mapping.'.$action,['mapping_id'=>$mappingId,'entity_type'=>$record['entity_type'],'source_module'=>$record['source_module'],'target_id'=>$targetId,'status'=>$status],array_merge($context,['entity_type'=>'mapping','entity_id'=>$mappingId]));
        return $after;
    }

    public static function undo(int $historyId): array {
        $history=MappingRepository::historyEntry($historyId);
        if(!$history)throw new \RuntimeException('Histórico não encontrado.');
        $before=(array)($history['before']??[]);
        if(!$before)throw new \RuntimeException('Esta alteração não contém um estado anterior recuperável.');
        $mappingId=(int)$history['mapping_id'];
        $current=MappingRepository::get($mappingId);
        if(!$current)throw new \RuntimeException('O mapping original já não existe.');
        $expectedAfter=(array)($history['after']??[]);
        if($expectedAfter && ((int)($current['target_id']??0)!==(int)($expectedAfter['target_id']??0) || (string)($current['status']??'')!==(string)($expectedAfter['status']??''))){
            throw new \RuntimeException('Este mapping já tem alterações posteriores. Desfaz primeiro a decisão mais recente.');
        }
        $targetId=(int)($before['target_id']??0);$status=sanitize_key((string)($before['status']??'pending'));
        if(!in_array($status,['confirmed','rejected','pending','suggested'],true))$status='pending';
        if ($status === 'confirmed' && (string)($current['entity_type'] ?? '') === 'store' && !self::storeTargetCompatible($current, $targetId)) {
            throw new \RuntimeException('O histórico contém um PDV de insígnia incompatível. Não é seguro restaurar esta associação.');
        }
        self::$applying=true;
        try{
            self::applyToSource($current,$status==='confirmed'?$targetId:0,$status);
            MappingRepository::update($mappingId,['target_id'=>$targetId,'confidence'=>(int)($before['confidence']??0),'match_method'=>(string)($before['match_method']??'undo'),'status'=>$status,'candidate_json'=>$before['candidate_json']??[],'meta_json'=>$before['meta_json']??[],'last_synced_at'=>current_time('mysql')]);
        }finally{self::$applying=false;}
        $after=MappingRepository::get($mappingId)?:$before;
        MappingRepository::addHistory($mappingId,'undo',$current,$after,'Reversão da alteração #'.$historyId,wp_generate_uuid4());
        AuditLog::record('mapping.undo','mapping',$mappingId,$current,$after,['client_id'=>(int)$current['client_id'],'project_id'=>(int)$current['project_id']],['history_id'=>$historyId]);
        EventBus::emit('mapping.undone',['mapping_id'=>$mappingId,'history_id'=>$historyId],['entity_type'=>'mapping','entity_id'=>$mappingId,'client_id'=>(int)$current['client_id'],'project_id'=>(int)$current['project_id']]);
        return $after;
    }

    public static function captureStoreMapping(string $module, int $sourceRecordId, int $clientId, int $projectId, string $sourceLabel, string $externalRef, int $locationId, array $meta = []): int {
        if(self::$applying||!MappingRepository::tablesReady())return 0;
        $sourceKey=$module.':'.$sourceRecordId;
        if($module==='stock'&&!empty($meta['source_store_key']))$sourceKey='stock:'.substr(hash('sha256',$clientId.'|'.$projectId.'|'.(string)($meta['insignia']??'').'|'.(string)$meta['source_store_key']),0,40);
        $check = ['entity_type'=>'store','source_label'=>$sourceLabel,'meta_json'=>$meta];
        if ($locationId > 0 && !self::storeTargetCompatible($check, $locationId)) $locationId = 0;
        return MappingRepository::upsert(['entity_type'=>'store','source_module'=>$module,'source_scope_id'=>absint($meta['source_scope_id']??0),'source_record_id'=>$sourceRecordId,'client_id'=>$clientId,'project_id'=>$projectId,'source_key'=>$sourceKey,'external_ref'=>$externalRef,'source_label'=>$sourceLabel,'normalized_label'=>MappingEngine::normalize($sourceLabel),'target_type'=>'location','target_id'=>$locationId,'confidence'=>$locationId?100:0,'match_method'=>$locationId?'source_confirmed':'','status'=>$locationId?'confirmed':'pending','meta_json'=>$meta,'force_status'=>true]);
    }

    public static function searchTargets(string $entityType,int $clientId,int $projectId,string $query='',int $limit=25): array {
        return $entityType==='product'?self::productTargets($clientId,$projectId,$query,$limit):self::locationTargets($clientId,$projectId,$query,$limit);
    }

    public static function targetLabel(string $entityType,int $targetId): string {
        global $wpdb;if(!$targetId)return '';
        if($entityType==='product'){\RoutesPro\Forms\ProductCardex::install();return (string)$wpdb->get_var($wpdb->prepare('SELECT product_name FROM '.\RoutesPro\Forms\ProductCardex::table_items().' WHERE id=%d',$targetId));}
        return (string)$wpdb->get_var($wpdb->prepare('SELECT name FROM '.$wpdb->prefix.'routespro_locations WHERE id=%d',$targetId));
    }

    private static function storeTargetCompatible(array $record, int $targetId): bool {
        if ($targetId <= 0) return false;
        $meta = is_array($record['meta_json'] ?? null) ? $record['meta_json'] : [];
        $insignia = (string)($meta['insignia'] ?? $meta['retailer_key'] ?? '');
        $sourceLabel = (string)($record['source_label'] ?? '');
        if ($insignia === '') $insignia = $sourceLabel;
        global $wpdb;
        $loc = $wpdb->get_row($wpdb->prepare(
            'SELECT name,tags,meta_json FROM '.$wpdb->prefix.'routespro_locations WHERE id=%d LIMIT 1',
            $targetId
        ), ARRAY_A);
        if (!$loc) return false;
        return StoreIdentityAuthority::sourceMatchesLocation($insignia, $sourceLabel, (array)$loc, true);
    }

    public static function sourceModules(): array { return ['briefing'=>'Briefings','implementation'=>'Implementações','stock'=>'Stocks, lojas','stock_product'=>'Stocks, produtos','cardex'=>'Cardex']; }
    public static function entityLabels(): array { return ['store'=>'Lojas','product'=>'Produtos','assortment'=>'Cardex e sortido']; }
    public static function statusLabels(): array { return ['pending'=>'Pendente','suggested'=>'Sugerido','confirmed'=>'Confirmado','rejected'=>'Rejeitado']; }

    /** 9DN: resolve legacy PDV ids in bulk when syncing the read-only Mapping Center projection. */
    private static function identityLocationsByIds(array $ids): array {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if (!$ids) return [];
        $locations = [];
        foreach (array_chunk($ids, 450) as $batch) {
            $placeholders = implode(',', array_fill(0, count($batch), '%d'));
            $sql = $wpdb->prepare('SELECT id,name,tags,meta_json FROM '.$wpdb->prefix.'routespro_locations WHERE id IN ('.$placeholders.')', ...$batch);
            foreach ((array)$wpdb->get_results($sql, ARRAY_A) as $location) {
                $locations[(int)$location['id']] = $location;
            }
        }
        return $locations;
    }

    private static function syncBriefingAliases(array $filters,int $limit): int {
        global $wpdb;$table=$wpdb->prefix.'routespro_briefing_store_aliases';$items=$wpdb->prefix.'routespro_briefing_store_items';if(!self::tableExists($table))return 0;
        $where=['1=1'];$args=[];self::appendScopeWhere($where,$args,$filters,'a');
        $sql="SELECT a.*,MIN(si.briefing_id) source_scope_id,COUNT(DISTINCT si.briefing_id) scope_count FROM {$table} a LEFT JOIN {$items} si ON si.alias_id=a.id WHERE ".implode(' AND ',$where).' GROUP BY a.id ORDER BY a.updated_at DESC LIMIT '.max(1,min(20000,$limit));if($args)$sql=$wpdb->prepare($sql,...$args);
        $rows=$wpdb->get_results($sql,ARRAY_A)?:[];$count=0;
        $identityLocations = self::identityLocationsByIds(array_column($rows, 'location_id'));
        foreach($rows as $row){
            $historicLocationId = (int)($row['location_id'] ?? 0);
            $identityValid = $historicLocationId > 0 && isset($identityLocations[$historicLocationId])
                && StoreIdentityAuthority::sourceMatchesLocation((string)$row['retailer_key'], (string)$row['external_name'], $identityLocations[$historicLocationId], true);
            if (!$identityValid) $row['location_id'] = 0; // Projection only; source alias stays in place for manual review.
            MappingRepository::upsert(['entity_type'=>'store','source_module'=>'briefing','source_scope_id'=>(int)$row['source_scope_id'],'source_record_id'=>(int)$row['id'],'client_id'=>(int)$row['client_id'],'project_id'=>(int)$row['project_id'],'source_key'=>'briefing:'.(int)$row['id'],'external_ref'=>(string)$row['retailer_key'],'source_label'=>(string)$row['external_name'],'normalized_label'=>(string)$row['normalized_name'],'target_type'=>'location','target_id'=>(int)$row['location_id'],'confidence'=>!empty($row['location_id'])?100:0,'match_method'=>!empty($row['location_id'])?'source_confirmed':'','status'=>!empty($row['location_id'])?'confirmed':'pending','meta_json'=>['retailer_key'=>$row['retailer_key'],'source_scope_id'=>(int)$row['source_scope_id'],'scope_count'=>(int)$row['scope_count'],'identity_review_required'=>$historicLocationId>0&&!$identityValid,'historical_target_id'=>$historicLocationId],'force_status'=>!empty($row['location_id'])]);$count++;}
        return $count;
    }

    private static function syncImplementationAliases(array $filters,int $limit): int {
        global $wpdb;$table=$wpdb->prefix.'routespro_implementation_store_aliases';$items=$wpdb->prefix.'routespro_implementation_items';if(!self::tableExists($table))return 0;
        $where=['1=1'];$args=[];self::appendScopeWhere($where,$args,$filters,'a');
        $sql="SELECT a.*,MIN(i.document_id) source_scope_id,COUNT(DISTINCT i.document_id) scope_count FROM {$table} a LEFT JOIN {$items} i ON i.alias_id=a.id WHERE ".implode(' AND ',$where).' GROUP BY a.id ORDER BY a.updated_at DESC LIMIT '.max(1,min(20000,$limit));if($args)$sql=$wpdb->prepare($sql,...$args);
        $rows=$wpdb->get_results($sql,ARRAY_A)?:[];$count=0;
        $identityLocations = self::identityLocationsByIds(array_column($rows, 'location_id'));
        foreach($rows as $row){
            $historicLocationId = (int)($row['location_id'] ?? 0);
            $identityFamily = StoreIdentityAuthority::family((string)($row['external_name'] ?? ''));
            $identityValid = $historicLocationId > 0 && $identityFamily !== '' && isset($identityLocations[$historicLocationId])
                && StoreIdentityAuthority::sourceMatchesLocation($identityFamily, (string)$row['external_name'], $identityLocations[$historicLocationId], true);
            if (!$identityValid) $row['location_id'] = 0;
            MappingRepository::upsert(['entity_type'=>'store','source_module'=>'implementation','source_scope_id'=>(int)$row['source_scope_id'],'source_record_id'=>(int)$row['id'],'client_id'=>(int)$row['client_id'],'project_id'=>(int)$row['project_id'],'source_key'=>'implementation:'.(int)$row['id'],'external_ref'=>(string)$row['external_store_code'],'source_label'=>(string)$row['external_name'],'normalized_label'=>(string)$row['normalized_name'],'target_type'=>'location','target_id'=>(int)$row['location_id'],'confidence'=>!empty($row['location_id'])?100:0,'match_method'=>!empty($row['location_id'])?'source_confirmed':'','status'=>!empty($row['location_id'])?'confirmed':'pending','meta_json'=>['source_scope_id'=>(int)$row['source_scope_id'],'scope_count'=>(int)$row['scope_count'],'identity_review_required'=>$historicLocationId>0&&!$identityValid,'historical_target_id'=>$historicLocationId],'force_status'=>!empty($row['location_id'])]);$count++;}
        return $count;
    }

    private static function syncStockStores(array $filters,int $limit): int {
        global $wpdb;$items=$wpdb->prefix.'routespro_stock_items';$batches=$wpdb->prefix.'routespro_stock_batches';$maps=$wpdb->prefix.'routespro_stock_store_mappings';if(!self::tableExists($items))return 0;
        $where=['1=1'];$args=[];self::appendScopeWhere($where,$args,$filters,'i');
        $sql="SELECT i.client_id,i.project_id,b.insignia,i.source_store_key,MAX(i.store_external_ref) store_external_ref,MAX(i.store_name) store_name,MAX(i.locality) locality,MAX(i.location_id) item_location_id,MAX(i.match_confidence) item_confidence,MAX(i.match_method) item_method,MAX(m.id) mapping_id,MAX(m.location_id) mapped_location_id,MAX(m.confidence) mapped_confidence,MAX(m.match_method) mapped_method,MAX(i.batch_id) source_scope_id FROM {$items} i INNER JOIN {$batches} b ON b.id=i.batch_id LEFT JOIN {$maps} m ON m.client_id=i.client_id AND m.project_id=i.project_id AND m.insignia=b.insignia AND m.source_store_key=i.source_store_key WHERE ".implode(' AND ',$where).' GROUP BY i.client_id,i.project_id,b.insignia,i.source_store_key ORDER BY MAX(i.id) DESC LIMIT '.max(1,min(20000,$limit));if($args)$sql=$wpdb->prepare($sql,...$args);
        $rows=$wpdb->get_results($sql,ARRAY_A)?:[];$count=0;
        $identityLocations = self::identityLocationsByIds(array_merge(array_column($rows, 'mapped_location_id'), array_column($rows, 'item_location_id')));
        foreach($rows as $row){$target=(int)($row['mapped_location_id']?:$row['item_location_id']);
            $historicLocationId = $target;
            $sourceLabel = (string)($row['store_name'] ?? '');
            $identityValid = $target > 0 && isset($identityLocations[$target])
                && StoreIdentityAuthority::sourceMatchesLocation((string)($row['insignia'] ?? ''), $sourceLabel, $identityLocations[$target], true);
            if (!$identityValid) $target = 0; // Stored stock mapping remains untouched.
            $confidence=$target>0?(int)($row['mapped_confidence']?:$row['item_confidence']):0;$method=(string)($row['mapped_method']?:$row['item_method']);$key='stock:'.substr(hash('sha256',$row['client_id'].'|'.$row['project_id'].'|'.$row['insignia'].'|'.$row['source_store_key']),0,40);MappingRepository::upsert(['entity_type'=>'store','source_module'=>'stock','source_scope_id'=>(int)$row['source_scope_id'],'source_record_id'=>(int)$row['mapping_id'],'client_id'=>(int)$row['client_id'],'project_id'=>(int)$row['project_id'],'source_key'=>$key,'external_ref'=>(string)$row['store_external_ref'],'source_label'=>(string)$row['store_name'],'normalized_label'=>MappingEngine::normalize((string)$row['store_name']),'target_type'=>'location','target_id'=>$target,'confidence'=>$confidence,'match_method'=>$method,'status'=>$target?'confirmed':'pending','meta_json'=>['insignia'=>$row['insignia'],'source_store_key'=>$row['source_store_key'],'source_store_ref'=>$row['store_external_ref'],'source_store_name'=>$row['store_name'],'locality'=>$row['locality'],'source_scope_id'=>(int)$row['source_scope_id'],'identity_review_required'=>$historicLocationId>0&&!$identityValid,'historical_target_id'=>$historicLocationId],'force_status'=>$target>0]);$count++;}
        return $count;
    }

    private static function syncStockProducts(array $filters,int $limit): int {
        global $wpdb;$items=$wpdb->prefix.'routespro_stock_items';$batches=$wpdb->prefix.'routespro_stock_batches';if(!self::tableExists($items))return 0;
        $where=['1=1'];$args=[];self::appendScopeWhere($where,$args,$filters,'i');$where[]='b.snapshot_date>=DATE_SUB(CURDATE(),INTERVAL 370 DAY)';
        $sql="SELECT i.client_id,i.project_id,MAX(i.batch_id) source_scope_id,MAX(i.ean) ean,MAX(i.article_ref) article_ref,i.product_name,MAX(COALESCE(i.cardex_item_id,0)) cardex_item_id,MAX(COALESCE(i.product_match_confidence,0)) product_match_confidence,MAX(COALESCE(i.product_match_method,'')) product_match_method FROM {$items} i INNER JOIN {$batches} b ON b.id=i.batch_id WHERE ".implode(' AND ',$where).' GROUP BY i.client_id,i.project_id,COALESCE(NULLIF(i.ean,\'\'),NULLIF(i.article_ref,\'\'),i.product_name) ORDER BY MAX(i.id) DESC LIMIT '.max(1,min(20000,$limit));if($args)$sql=$wpdb->prepare($sql,...$args);
        $rows=$wpdb->get_results($sql,ARRAY_A)?:[];$count=0;
        foreach($rows as $row){$identity=(string)($row['ean']?:($row['article_ref']?:$row['product_name']));$key='stock_product:'.substr(hash('sha256',$row['client_id'].'|'.$row['project_id'].'|'.$identity),0,40);$target=(int)$row['cardex_item_id'];MappingRepository::upsert(['entity_type'=>'product','source_module'=>'stock_product','source_scope_id'=>(int)$row['source_scope_id'],'source_record_id'=>0,'client_id'=>(int)$row['client_id'],'project_id'=>(int)$row['project_id'],'source_key'=>$key,'external_ref'=>(string)($row['article_ref']?:$row['ean']),'source_label'=>(string)$row['product_name'],'normalized_label'=>MappingEngine::normalize((string)$row['product_name']),'target_type'=>'cardex_item','target_id'=>$target,'confidence'=>(int)$row['product_match_confidence'],'match_method'=>(string)$row['product_match_method'],'status'=>$target?'confirmed':'pending','meta_json'=>['ean'=>$row['ean'],'article_ref'=>$row['article_ref'],'product_name'=>$row['product_name'],'source_scope_id'=>(int)$row['source_scope_id']],'force_status'=>$target>0]);$count++;}
        return $count;
    }

    private static function syncCardexAssignments(array $filters,int $limit): int {
        global $wpdb;if(!class_exists('\\RoutesPro\\Forms\\ProductCardex'))return 0;\RoutesPro\Forms\ProductCardex::install();$cardex=\RoutesPro\Forms\ProductCardex::table_cardex();$links=\RoutesPro\Forms\ProductCardex::table_locations();$locations=$wpdb->prefix.'routespro_locations';
        $where=['1=1'];$args=[];self::appendScopeWhere($where,$args,$filters,'c');
        $sql="SELECT cl.id link_id,c.id cardex_id,c.client_id,c.project_id,c.name cardex_name,c.insignia,cl.location_id,l.name location_name FROM {$links} cl INNER JOIN {$cardex} c ON c.id=cl.cardex_id LEFT JOIN {$locations} l ON l.id=cl.location_id WHERE ".implode(' AND ',$where).' ORDER BY cl.id DESC LIMIT '.max(1,min(20000,$limit));if($args)$sql=$wpdb->prepare($sql,...$args);
        $rows=$wpdb->get_results($sql,ARRAY_A)?:[];$count=0;
        $identityLocations = self::identityLocationsByIds(array_column($rows, 'location_id'));
        foreach($rows as $row){
            $historicLocationId = (int)($row['location_id'] ?? 0);
            $identityValid = $historicLocationId > 0 && isset($identityLocations[$historicLocationId])
                && StoreIdentityAuthority::locationMatches((string)$row['insignia'], $identityLocations[$historicLocationId]);
            if (!$identityValid) $row['location_id'] = 0;
            MappingRepository::upsert(['entity_type'=>'assortment','source_module'=>'cardex','source_scope_id'=>(int)$row['cardex_id'],'source_record_id'=>(int)$row['link_id'],'client_id'=>(int)$row['client_id'],'project_id'=>(int)$row['project_id'],'source_key'=>'cardex:'.(int)$row['cardex_id'].':location:'.$historicLocationId,'external_ref'=>(string)$row['insignia'],'source_label'=>(string)$row['cardex_name'],'normalized_label'=>MappingEngine::normalize((string)$row['cardex_name']),'target_type'=>'location','target_id'=>(int)$row['location_id'],'confidence'=>$identityValid?100:0,'match_method'=>$identityValid?'cardex_assignment':'identity_review','status'=>$identityValid?'confirmed':'pending','is_locked'=>1,'meta_json'=>['location_name'=>$row['location_name'],'cardex_id'=>(int)$row['cardex_id'],'identity_review_required'=>$historicLocationId>0&&!$identityValid,'historical_target_id'=>$historicLocationId],'force_status'=>true,'force'=>true]);$count++;}
        return $count;
    }

    private static function applyToSource(array $record,int $targetId,string $status): void {
        global $wpdb;$module=(string)$record['source_module'];$recordId=(int)$record['source_record_id'];$scopeId=(int)$record['source_scope_id'];
        if($module==='briefing'){
            if(!$scopeId)$scopeId=(int)$wpdb->get_var($wpdb->prepare('SELECT MIN(briefing_id) FROM '.$wpdb->prefix.'routespro_briefing_store_items WHERE alias_id=%d',$recordId));
            if(!$scopeId)throw new \RuntimeException('Não foi encontrado um briefing ligado a este alias.');
            \RoutesPro\Briefings\BriefingService::saveMappings($scopeId,[$recordId=>$targetId]);return;
        }
        if($module==='implementation'){
            if(!$scopeId)$scopeId=(int)$wpdb->get_var($wpdb->prepare('SELECT MIN(document_id) FROM '.$wpdb->prefix.'routespro_implementation_items WHERE alias_id=%d',$recordId));
            if(!$scopeId)throw new \RuntimeException('Não foi encontrado um documento ligado a este alias.');
            \RoutesPro\Implementations\ImplementationService::saveMappings($scopeId,[$recordId=>$targetId]);return;
        }
        if($module==='stock'){
            $meta=(array)$record['meta_json'];
            \RoutesPro\Stocks\StockService::saveMapping((int)$record['client_id'],(int)$record['project_id'],(string)($meta['insignia']??''),(string)($meta['source_store_ref']??$record['external_ref']),(string)($meta['source_store_name']??$record['source_label']),$targetId,$targetId?100:0,$targetId?'manual_center':'unmapped',true);return;
        }
        if($module==='stock_product'){
            $meta=(array)$record['meta_json'];$where=['client_id=%d','project_id=%d'];$args=[(int)$record['client_id'],(int)$record['project_id']];
            if(!empty($meta['ean'])){$where[]='ean=%s';$args[]=(string)$meta['ean'];}
            elseif(!empty($meta['article_ref'])){$where[]='article_ref=%s';$args[]=(string)$meta['article_ref'];}
            else{$where[]='product_name=%s';$args[]=(string)($meta['product_name']??$record['source_label']);}
            $sql='UPDATE '.$wpdb->prefix.'routespro_stock_items SET cardex_item_id=%d,product_match_confidence=%d,product_match_method=%s WHERE '.implode(' AND ',$where);
            $wpdb->query($wpdb->prepare($sql,...array_merge([$targetId,$targetId?100:0,$targetId?'manual_center':'unmapped'],$args)));return;
        }
        throw new \RuntimeException('Este módulo ainda não permite alterar o mapping a partir do centro universal.');
    }

    private static function targetExists(string $entityType,int $clientId,int $projectId,int $targetId): bool {
        if(!$targetId)return false;global $wpdb;
        if($entityType==='product'){
            \RoutesPro\Forms\ProductCardex::install();
            $sql='SELECT 1 FROM '.\RoutesPro\Forms\ProductCardex::table_items().' i INNER JOIN '.\RoutesPro\Forms\ProductCardex::table_cardex().' c ON c.id=i.cardex_id WHERE i.id=%d AND i.is_active=1 AND (c.client_id=0 OR c.client_id=%d) AND (c.project_id=0 OR c.project_id=%d) LIMIT 1';
            return (bool)$wpdb->get_var($wpdb->prepare($sql,$targetId,$clientId,$projectId));
        }
        $px=$wpdb->prefix.'routespro_';$sql="SELECT 1 FROM {$px}locations l LEFT JOIN {$px}campaign_locations cl ON cl.location_id=l.id AND cl.is_active=1 WHERE l.id=%d AND l.is_active=1 AND (l.client_id=0 OR l.client_id IS NULL OR l.client_id=%d) AND (l.project_id=0 OR l.project_id IS NULL OR l.project_id=%d OR cl.project_id=%d) LIMIT 1";
        return (bool)$wpdb->get_var($wpdb->prepare($sql,$targetId,$clientId,$projectId,$projectId));
    }

    private static function locationTargets(int $clientId,int $projectId,string $query='',int $limit=25): array {
        global $wpdb;$px=$wpdb->prefix.'routespro_';$where=['l.is_active=1'];$args=[];
        if($clientId){$where[]='(l.client_id=%d OR l.client_id=0 OR l.client_id IS NULL)';$args[]=$clientId;}
        if($projectId){$where[]='(l.project_id=%d OR l.project_id=0 OR l.project_id IS NULL OR cl.project_id=%d)';$args[]=$projectId;$args[]=$projectId;}
        if($query!==''){$like='%'.$wpdb->esc_like($query).'%';$where[]='(l.name LIKE %s OR l.external_ref LIKE %s OR l.city LIKE %s OR l.address LIKE %s)';array_push($args,$like,$like,$like,$like);}
        $sql="SELECT DISTINCT l.id,l.name,l.external_ref,l.city,l.county,l.address,l.tags FROM {$px}locations l LEFT JOIN {$px}campaign_locations cl ON cl.location_id=l.id AND cl.is_active=1 WHERE ".implode(' AND ',$where).' ORDER BY l.name ASC LIMIT '.max(1,min(10000,$limit));if($args)$sql=$wpdb->prepare($sql,...$args);
        return $wpdb->get_results($sql,ARRAY_A)?:[];
    }

    private static function productTargets(int $clientId,int $projectId,string $query='',int $limit=25): array {
        global $wpdb;if(!class_exists('\\RoutesPro\\Forms\\ProductCardex'))return [];\RoutesPro\Forms\ProductCardex::install();$where=['i.is_active=1','c.status=\'active\''];$args=[];
        if($clientId){$where[]='(c.client_id=0 OR c.client_id=%d)';$args[]=$clientId;}
        if($projectId){$where[]='(c.project_id=0 OR c.project_id=%d)';$args[]=$projectId;}
        if($query!==''){$like='%'.$wpdb->esc_like($query).'%';$where[]='(i.product_name LIKE %s OR i.reference LIKE %s OR i.category LIKE %s OR c.name LIKE %s)';array_push($args,$like,$like,$like,$like);}
        $sql='SELECT i.*,c.name cardex_name,c.insignia,c.client_id,c.project_id FROM '.\RoutesPro\Forms\ProductCardex::table_items().' i INNER JOIN '.\RoutesPro\Forms\ProductCardex::table_cardex().' c ON c.id=i.cardex_id WHERE '.implode(' AND ',$where).' ORDER BY i.product_name ASC LIMIT '.max(1,min(10000,$limit));if($args)$sql=$wpdb->prepare($sql,...$args);
        return $wpdb->get_results($sql,ARRAY_A)?:[];
    }

    private static function appendScopeWhere(array &$where,array &$args,array $filters,string $alias): void {
        if(!empty($filters['client_id'])){$where[]=$alias.'.client_id=%d';$args[]=absint($filters['client_id']);}
        if(!empty($filters['project_id'])){$where[]=$alias.'.project_id=%d';$args[]=absint($filters['project_id']);}
    }
    private static function tableExists(string $table): bool {global $wpdb;return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table;}
}
