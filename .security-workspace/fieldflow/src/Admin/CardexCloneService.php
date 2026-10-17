<?php
namespace RoutesPro\Admin;

use RoutesPro\Forms\ProductCardex as Cardex;
use RoutesPro\Stores\StoreIdentityAuthority as Identity;

if (!defined('ABSPATH')) exit;

/** 9DP: a cloned assortment remains unpublished until store scope is explicitly approved. */
final class CardexCloneService {
    public const CONTRACT = 'fieldflow-safe-cardex-clone-publication-9dp-v1';
    public const MAX_ITEMS = 10000;
    public const MAX_PREVIEW_LOCATIONS = 2500;

    // 9DQ: options are suggestions, not the universe of permitted retailers.
    // Only an expressly named retailer is saved; there is no automatic cross-retailer alias learning.
    public const FLEXIBLE_INSIGNIA_CONTRACT = 'fieldflow-flexible-cardex-insignia-registry-9dq-v1';
    public const INSIGNIA_OPTION = 'fieldflow_cardex_insignias_9dq';

    private static function builtInInsignias(): array {
        return ['Continente'=>'Continente (CNT)', 'Continente Modelo'=>'Continente Modelo (MDL)',
            'Continente Bom Dia'=>'Continente Bom Dia (CBD)', 'Pingo Doce'=>'Pingo Doce',
            'Arcol'=>'Arcol', 'M. Cunha'=>'M. Cunha', 'Makro'=>'Makro', 'Recheio'=>'Recheio',
            'Auchan'=>'Auchan', 'Intermarché'=>'Intermarché', 'El Corte Inglés'=>'El Corte Inglés',
            'Minipreço'=>'Minipreço', 'Mercadona'=>'Mercadona', 'Lidl'=>'Lidl',
            'Aldi'=>'Aldi', 'Froiz'=>'Froiz', 'Apolónia'=>'Apolónia', 'E.Leclerc'=>'E.Leclerc'];
    }

    private static function normaliseLabel(string $label): string {
        $flat = strtolower(remove_accents(trim($label)));
        return (string)preg_replace('/[^a-z0-9]+/', '', $flat);
    }

    /** Return a validated commercial display name, or empty when ambiguous. */
    public static function canonicalInsignia(string $label): string {
        $label = trim(sanitize_text_field($label));
        $label = (string)(preg_replace('/\s+/u', ' ', $label) ?? '');
        if ($label === '' || strlen($label) > 90 || !preg_match('/^[\p{L}\p{N}][\p{L}\p{N} .&\x27\-()\/]*$/u', $label)) return '';
        $key = self::normaliseLabel($label);
        if ($key === '' || preg_match('/^[0-9]+$/', $key)) return '';
        $aliases = ['pd'=>'Pingo Doce', 'cnt'=>'Continente', 'mdl'=>'Continente Modelo',
            'cbd'=>'Continente Bom Dia', 'itm'=>'Intermarché', 'itmi'=>'Intermarché',
            'eci'=>'El Corte Inglés'];
        if (isset($aliases[$key])) return $aliases[$key];
        if (strlen($key) < 3) return '';
        foreach (self::builtInInsignias() as $official=>$display) {
            if ($key === self::normaliseLabel($official)) return $official;
        }
        // A retailer-family word inside a longer name is not sufficient to prove a new brand.
        // This also deliberately rejects generic Sonae (without CNT/MDL/CBD) and M as merchandiser.
        if (Identity::family($label) !== '' || in_array($key,
            ['sonae','sonaemc','loja','lojas','cash','cashcarry','outro','outros','seminsignia',
             'desconhecida','porever','geral','merchandiser'], true)) return '';
        return $label;
    }

    public static function supportedInsignia(string $value): bool {
        return self::canonicalInsignia($value) !== '';
    }

    /** Built-ins plus brand names already registered, including legacy cardex names. */
    public static function insignias(): array {
        $result = self::builtInInsignias();
        $known = [];
        foreach ($result as $name=>$display) $known[self::normaliseLabel($name)] = true;
        $candidates = function_exists('get_option') ? get_option(self::INSIGNIA_OPTION, []) : [];
        if (!is_array($candidates)) $candidates = [];
        global $wpdb;
        if (isset($wpdb) && isset($wpdb->prefix) && method_exists($wpdb,'get_col')) {
            // Read only. No inference from generic tags, route assignments or product brands.
            $existing = $wpdb->get_col('SELECT DISTINCT insignia FROM '.Cardex::table_cardex()." WHERE insignia<>'' ORDER BY insignia ASC LIMIT 500");
            if (is_array($existing)) $candidates = array_merge($candidates,$existing);
        }
        foreach ($candidates as $label) {
            if (!is_string($label)) continue;
            $canonical = self::canonicalInsignia($label);
            if ($canonical === '') continue;
            $key = self::normaliseLabel($canonical);
            if (isset($known[$key])) continue;
            $result[$canonical] = $canonical;
            $known[$key] = true;
        }
        return $result;
    }

    /** Called only after a successful clone/commit, never just by typing a label. */
    public static function rememberInsignia(string $label): void {
        $canonical = self::canonicalInsignia($label);
        if ($canonical === '' || !function_exists('get_option') || !function_exists('update_option')) return;
        $existing = get_option(self::INSIGNIA_OPTION, []);
        if (!is_array($existing)) $existing = [];
        $existing = array_values(array_filter($existing,'is_string'));
        foreach ($existing as $old) {
            if (self::normaliseLabel((string)$old) === self::normaliseLabel($canonical)) return;
        }
        if (count($existing) >= 250) return;
        $existing[] = $canonical;
        update_option(self::INSIGNIA_OPTION, $existing, false);
    }

    /** For unknown retailers, only explicit dedicated store identity or an exact name prefix counts. */
    public static function locationMatchesInsignia(string $insignia, array $location): bool {
        $canonical = self::canonicalInsignia($insignia);
        if ($canonical === '') return false;
        if (Identity::family($canonical) !== '') return Identity::locationMatches($canonical,$location);
        // Known family evidence is never overridden by a new, unverified retailer name.
        if (Identity::locationFamily($location) !== ''
            || Identity::family((string)($location['name'] ?? '')) !== '') return false;
        $meta = $location['meta_json'] ?? [];
        if (is_string($meta)) $meta = json_decode($meta,true);
        $declared=[];
        if (is_array($meta)) {
            foreach (['insignia','insignia_key','retailer','retailer_key','store_brand','chain'] as $key) {
                if (isset($meta[$key]) && is_string($meta[$key]) && trim($meta[$key]) !== '') {
                    $declared[self::normaliseLabel($meta[$key])] = true;
                }
            }
        }
        $wanted = self::normaliseLabel($canonical);
        // If the PDV declares its retailer, every declaration must agree.
        if ($declared) return count($declared) === 1 && isset($declared[$wanted]);
        $name = strtolower(remove_accents(trim((string)($location['name'] ?? ''))));
        $name = trim((string)(preg_replace('/[^a-z0-9]+/', ' ', $name) ?? ''));
        $brand = strtolower(remove_accents($canonical));
        $brand = trim((string)(preg_replace('/[^a-z0-9]+/', ' ', $brand) ?? ''));
        // Only a whole leading retailer phrase (optionally after "Loja") is trusted.
        if ($brand === '') return false;
        foreach ([$name, preg_replace('/^loja /', '', $name) ?: ''] as $candidate) {
            if ($candidate === $brand || str_starts_with($candidate,$brand.' ')) return true;
        }
        return false;
    }

    private static function meta(array $cardex): array {
        $meta = json_decode((string)($cardex['meta_json'] ?? ''), true);
        return is_array($meta) ? $meta : [];
    }

    public static function isClone(array $cardex): bool {
        return !empty(self::meta($cardex)['clone_source_id']);
    }

    public static function duplicate(int $sourceId, string $destination, string $name): int {
        global $wpdb;
        Cardex::install();
        $destination = self::canonicalInsignia($destination);
        if ($destination === '') throw new \RuntimeException('Escreve uma insígnia comercial válida, por exemplo Poupança, Garcias ou Central Cash.');
        $source = Cardex::get_cardex($sourceId);
        if (!$source) throw new \RuntimeException('O cardex de origem já não existe.');
        $clientId = absint($source['client_id'] ?? 0);
        $projectId = absint($source['project_id'] ?? 0);
        if (!$clientId || !$projectId) throw new \RuntimeException('O cardex de origem precisa de cliente e projeto para permitir a cópia segura.');
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT category,reference,item_type,product_name,capacity_cl,units_per_case,sort_order,is_active FROM '.Cardex::table_items().' WHERE cardex_id=%d AND is_active=1 ORDER BY sort_order ASC,id ASC',
            $sourceId
        ), ARRAY_A) ?: [];
        if (!$rows || count($rows)>self::MAX_ITEMS) throw new \RuntimeException('A origem não tem referências ativas válidas ou excede o limite de cópia.');
        $name = trim(sanitize_text_field($name));
        if ($name==='') $name = 'Cardex '. $destination .' (cópia)';
        if ($wpdb->query('START TRANSACTION') === false) throw new \RuntimeException('Não foi possível iniciar uma cópia transacional.');
        try {
            $date = current_time('mysql');
            $meta = ['clone_source_id'=>$sourceId,'clone_source_insignia'=>(string)($source['insignia'] ?? ''),
                'clone_source_name'=>(string)($source['name'] ?? ''),'clone_scope'=>'manual',
                'clone_created_by'=>get_current_user_id(),'clone_created_at'=>$date,'clone_publication_approved'=>false,
                'clone_copied_active_items'=>count($rows)];
            if ($wpdb->insert(Cardex::table_cardex(), [
                'name'=>$name,'insignia'=>$destination,'client_id'=>$clientId,'project_id'=>$projectId,
                'status'=>'draft','meta_json'=>wp_json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'created_at'=>$date,'updated_at'=>$date
            ]) === false) throw new \RuntimeException('A criação do cardex em rascunho falhou.');
            $newId = absint($wpdb->insert_id);
            if (!$newId) throw new \RuntimeException('A cópia não recebeu um identificador.');
            foreach ($rows as $row) {
                if ($wpdb->insert(Cardex::table_items(), [
                    'cardex_id'=>$newId,'category'=>(string)$row['category'],
                    'reference'=>(string)$row['reference'],'item_type'=>(string)$row['item_type'],
                    'product_name'=>(string)$row['product_name'],
                    'capacity_cl'=>$row['capacity_cl'],'units_per_case'=>$row['units_per_case'],
                    'sort_order'=>(int)$row['sort_order'],'is_active'=>1,'meta_json'=>null,
                    'created_at'=>$date,'updated_at'=>$date,
                ]) === false) throw new \RuntimeException('Falhou a cópia de uma referência.');
            }
            if ($wpdb->query('COMMIT') === false) throw new \RuntimeException('Não foi possível concluir a cópia.');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
        // Only a confirmed clone registers a new retail label for future selections.
        self::rememberInsignia($destination);
        // No store association, per-store delta, stock or historical meta is copied.
        do_action('fieldflow_mapping_source_changed','cardex_clone_draft',$newId);
        return $newId;
    }

    public static function locationInScope(array $location, int $clientId, int $projectId, string $insignia): bool {
        global $wpdb;
        $locationId = absint($location['id'] ?? 0);
        if (!$locationId || !$clientId || !$projectId || !self::supportedInsignia($insignia)) return false;
        if (!self::locationMatchesInsignia($insignia, $location)) return false;
        $locationClient = absint($location['client_id'] ?? 0);
        if ($locationClient !== 0 && $locationClient !== $clientId) return false;
        if (absint($location['project_id'] ?? 0) === $projectId) return true;
        return (bool)$wpdb->get_var($wpdb->prepare(
            'SELECT 1 FROM '.$wpdb->prefix.'routespro_campaign_locations WHERE location_id=%d AND project_id=%d AND COALESCE(is_active,1)=1 LIMIT 1',
            $locationId,$projectId
        ));
    }

    public static function eligibleLocations(int $clientId, int $projectId, string $insignia): array {
        global $wpdb;
        if (!$clientId || !$projectId || !self::supportedInsignia($insignia)) return [];
        $locations = $wpdb->prefix.'routespro_locations';
        $campaignLocations = $wpdb->prefix.'routespro_campaign_locations';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.id,l.name,l.tags,l.meta_json,l.client_id,l.project_id FROM {$locations} l
             WHERE COALESCE(l.is_active,1)=1 AND (l.client_id=%d OR l.client_id=0)
             AND (l.project_id=%d OR EXISTS (SELECT 1 FROM {$campaignLocations} cl
                 WHERE cl.location_id=l.id AND cl.project_id=%d AND COALESCE(cl.is_active,1)=1))
             ORDER BY l.name ASC LIMIT ".(self::MAX_PREVIEW_LOCATIONS+1),
            $clientId,$projectId,$projectId
        ),ARRAY_A) ?: [];
        if (count($rows)>self::MAX_PREVIEW_LOCATIONS) throw new \RuntimeException('Existem demasiadas lojas para uma publicação global. Usa associações manuais.');
        return array_values(array_filter($rows, static function(array $loc) use ($insignia): bool {
            return self::locationMatchesInsignia($insignia, $loc);
        }));
    }

    private static function isSameInsignia(string $a,string $b): bool {
        $a = self::canonicalInsignia($a);
        $b = self::canonicalInsignia($b);
        if ($a === '' || $b === '') return false;
        if (Identity::family($a) !== '' || Identity::family($b) !== '') {
            return Identity::family($a) !== '' && Identity::family($a) === Identity::family($b)
                && Identity::subtype($a) === Identity::subtype($b);
        }
        return self::normaliseLabel($a) === self::normaliseLabel($b);
    }

    private static function assertPublishableItems(int $id): int {
        global $wpdb;
        $items = $wpdb->get_results($wpdb->prepare(
            'SELECT reference,product_name,is_active FROM '.Cardex::table_items().' WHERE cardex_id=%d ORDER BY id ASC', $id
        ),ARRAY_A) ?: [];
        $seen=[];$active=0;
        foreach ($items as $item) {
            if (empty($item['is_active'])) continue;
            $active++;
            $ref = trim((string)($item['reference'] ?? ''));
            $name = trim((string)($item['product_name'] ?? ''));
            if ($name==='') throw new \RuntimeException('Um produto ativo não tem designação.');
            $key = $ref!=='' ? 'ref:'.strtolower($ref) : 'name:'.strtolower($name);
            if (isset($seen[$key])) throw new \RuntimeException('Existem referências repetidas. Revê o sortido antes de publicar.');
            $seen[$key]=true;
        }
        if ($active===0) throw new \RuntimeException('Não é possível publicar um cardex sem produtos ativos.');
        return $active;
    }

    public static function publish(int $id, string $scope, bool $confirmed): int {
        global $wpdb;
        Cardex::install();
        $cardex = Cardex::get_cardex($id);
        if (!$cardex || ($cardex['status'] ?? '')!=='draft') throw new \RuntimeException('Apenas um rascunho pode ser publicado.');
        $clientId=absint($cardex['client_id'] ?? 0);
        $projectId=absint($cardex['project_id'] ?? 0);
        $brand=trim((string)($cardex['insignia'] ?? ''));
        if (!$confirmed || !$clientId || !$projectId || !self::supportedInsignia($brand)) {
            throw new \RuntimeException('Confirma o cliente, projeto, insígnia e abrangência antes de publicar.');
        }
        if (!in_array($scope,['manual','global'],true)) throw new \RuntimeException('Escolhe a abrangência do cardex.');
        $count=self::assertPublishableItems($id);
        $linked=$wpdb->get_results($wpdb->prepare(
            'SELECT l.id,l.name,l.tags,l.meta_json,l.client_id,l.project_id FROM '.Cardex::table_locations().' cl
             INNER JOIN '.$wpdb->prefix.'routespro_locations l ON l.id=cl.location_id WHERE cl.cardex_id=%d',
            $id
        ),ARRAY_A) ?: [];
        if ($scope==='manual') {
            if (!$linked) throw new \RuntimeException('Para publicar apenas em lojas selecionadas, associa e guarda primeiro as lojas.');
            foreach ($linked as $loc) {
                if (!self::locationInScope($loc,$clientId,$projectId,$brand)) throw new \RuntimeException('Existe uma associação a loja incompatível com cliente, projeto ou insígnia.');
            }
        } else {
            if ($linked) throw new \RuntimeException('Remove primeiro as associações manuais antes de publicar globalmente.');
            if (!self::eligibleLocations($clientId,$projectId,$brand)) throw new \RuntimeException('Não existem lojas elegíveis nesta insígnia e projeto.');
        }
        // Never activate an ambiguous clone over a second automatic global assortment.
        $existing=$wpdb->get_results($wpdb->prepare(
            "SELECT id,insignia FROM ".Cardex::table_cardex()." WHERE status='active' AND client_id=%d AND project_id=%d AND id<>%d",
            $clientId,$projectId,$id
        ),ARRAY_A) ?: [];
        foreach ($existing as $prior) {
            if (!self::isSameInsignia($brand,(string)($prior['insignia'] ?? ''))) continue;
            if ($scope==='global' && !Cardex::cardex_has_location_links(absint($prior['id']))) {
                throw new \RuntimeException('Já existe outro cardex global ativo para esta insígnia e projeto.');
            }
            if ($scope==='manual') {
                foreach ($linked as $loc) {
                    if (Cardex::cardex_is_linked_to_location(absint($prior['id']),absint($loc['id']))) {
                        throw new \RuntimeException('Uma das lojas já tem outro cardex ativo desta insígnia. Revê a associação antes de publicar.');
                    }
                }
            }
        }
        if ($wpdb->query('START TRANSACTION')===false) throw new \RuntimeException('Não foi possível iniciar a publicação segura.');
        try {
            $meta=self::meta($cardex);
            $meta['clone_scope']=$scope;
            $meta['clone_publication_approved']=true;
            $meta['clone_published_at']=current_time('mysql');
            $meta['clone_published_by']=get_current_user_id();
            $changed=$wpdb->update(Cardex::table_cardex(),[
                'status'=>'active','meta_json'=>wp_json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'updated_at'=>current_time('mysql')
            ],['id'=>$id,'status'=>'draft']);
            if ($changed!==1) throw new \RuntimeException('O rascunho foi alterado antes da publicação. Atualiza a página.');
            if ($wpdb->query('COMMIT')===false) throw new \RuntimeException('Não foi possível concluir a publicação.');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
        do_action('fieldflow_mapping_source_changed','cardex_published',$id);
        return $count;
    }
}
