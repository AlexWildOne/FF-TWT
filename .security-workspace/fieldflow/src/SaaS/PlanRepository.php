<?php
namespace RoutesPro\SaaS;

if (!defined('ABSPATH')) exit;

class PlanRepository {
    public static function plansTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_saas_plans'; }
    public static function subscriptionsTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_client_subscriptions'; }
    public static function usageTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_usage_counters'; }

    public static function exists(): bool {
        global $wpdb; $table = self::plansTable();
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function tablesReady(): bool {
        global $wpdb;
        foreach ([self::plansTable(), self::subscriptionsTable(), self::usageTable()] as $table) {
            if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return false;
        }
        return true;
    }

    public static function defaults(): array {
        $all = array_fill_keys(CapabilityCatalog::keys(), 1);
        $start = array_fill_keys([
            'portal.operational','portal.client','routes.view','routes.create','forms.execute','reporting.view',
            'reporting.submit','media.upload','commercial.view','team.view','messages.use','exports.csv','mobile.webview','billing.view','billing.checkout'
        ], 1);
        $operations = $start + array_fill_keys([
            'routes.optimise','forms.manage','briefings.view','briefings.manage','implementations.view','implementations.manage',
            'stocks.view','stocks.manage','cardex.manage','mapping.manage','commercial.manage','campaign_pdvs.manage',
            'expenses.use','academy.view','growth_hub.view','analytics.basic','reports.schedule','exports.excel','exports.pdf'
        ], 1);
        $intelligence = $operations + array_fill_keys([
            'analytics.advanced','ai.use','alerts.use','mobile.offline'
        ], 1);
        return [
            ['slug'=>'legacy','name'=>'Legacy completo','description'=>'Compatibilidade integral para clientes existentes.','monthly_price'=>0,'annual_price'=>0,'currency'=>'EUR','is_system'=>1,'is_active'=>1,'capabilities'=>$all,'limits'=>[]],
            ['slug'=>'start','name'=>'FieldFlow Start','description'=>'Rotas, formulários, submissões e operação essencial.','monthly_price'=>49,'annual_price'=>490,'currency'=>'EUR','is_system'=>0,'is_active'=>1,'capabilities'=>$start,'limits'=>['users_active'=>10,'locations_active'=>250,'routes_monthly'=>250,'submissions_monthly'=>1000,'exports_monthly'=>100]],
            ['slug'=>'operations','name'=>'FieldFlow Operations','description'=>'Briefings, stocks, cardex, mapping e operação avançada.','monthly_price'=>99,'annual_price'=>990,'currency'=>'EUR','is_system'=>0,'is_active'=>1,'capabilities'=>$operations,'limits'=>['users_active'=>30,'locations_active'=>1500,'routes_monthly'=>1200,'submissions_monthly'=>6000,'exports_monthly'=>500,'scheduled_reports'=>10]],
            ['slug'=>'intelligence','name'=>'FieldFlow Intelligence','description'=>'Analytics, IA, alertas, relatórios e offline.','monthly_price'=>179,'annual_price'=>1790,'currency'=>'EUR','is_system'=>0,'is_active'=>1,'capabilities'=>$intelligence,'limits'=>['users_active'=>80,'locations_active'=>5000,'routes_monthly'=>5000,'submissions_monthly'=>25000,'ai_requests_monthly'=>2000,'exports_monthly'=>3000,'scheduled_reports'=>50,'offline_devices'=>100]],
            ['slug'=>'enterprise','name'=>'FieldFlow Enterprise','description'=>'Capacidades completas, API, white-label, SSO e limites personalizados.','monthly_price'=>0,'annual_price'=>0,'currency'=>'EUR','is_system'=>0,'is_active'=>1,'capabilities'=>$all,'limits'=>[]],
        ];
    }

    public static function seed(): void {
        if (!self::exists()) return;
        global $wpdb;
        foreach (self::defaults() as $plan) {
            $id = (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::plansTable().' WHERE slug=%s', $plan['slug']));
            if ($id) continue;
            $wpdb->insert(self::plansTable(), [
                'slug'=>$plan['slug'],'name'=>$plan['name'],'description'=>$plan['description'],
                'monthly_price'=>$plan['monthly_price'],'annual_price'=>$plan['annual_price'],'currency'=>$plan['currency'],
                'capabilities_json'=>wp_json_encode($plan['capabilities']),
                'limits_json'=>wp_json_encode($plan['limits']),
                'is_system'=>$plan['is_system'],'is_active'=>$plan['is_active'],
                'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'),
            ], ['%s','%s','%s','%f','%f','%s','%s','%s','%d','%d','%s','%s']);
        }
    }

    public static function migrateExistingClients(string $previousVersion): void {
        if (!self::exists() || version_compare($previousVersion, '2.2.221', '>=')) return;
        global $wpdb;
        $legacy = self::planBySlug('legacy');
        if (!$legacy) return;
        $clients = $wpdb->get_col('SELECT id FROM '.$wpdb->prefix.'routespro_clients') ?: [];
        foreach ($clients as $clientId) {
            if (self::subscription((int)$clientId)) continue;
            self::assign((int)$clientId, (int)$legacy['id'], [
                'status'=>'active','starts_at'=>current_time('mysql'),'notes'=>'Migração automática 2.2.221, compatibilidade integral.',
            ], 0, false);
        }
    }

    public static function plans(bool $activeOnly = false): array {
        global $wpdb; if (!self::exists()) return [];
        $sql = 'SELECT * FROM '.self::plansTable().($activeOnly ? ' WHERE is_active=1' : '')." ORDER BY FIELD(slug,'legacy','start','operations','intelligence','enterprise') ASC, is_system DESC, monthly_price ASC, id ASC";
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map([self::class,'hydratePlan'], $rows);
    }

    public static function plan(int $id): ?array {
        global $wpdb; if (!$id || !self::exists()) return null;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::plansTable().' WHERE id=%d', $id), ARRAY_A);
        return $row ? self::hydratePlan($row) : null;
    }

    public static function planBySlug(string $slug): ?array {
        global $wpdb; if (!self::exists()) return null;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::plansTable().' WHERE slug=%s', sanitize_key($slug)), ARRAY_A);
        return $row ? self::hydratePlan($row) : null;
    }

    public static function firstPlanWithCapability(string $capability): ?array {
        $bySlug = [];
        foreach (self::plans(true) as $plan) $bySlug[(string)($plan['slug'] ?? '')] = $plan;
        foreach (['start','operations','intelligence','enterprise'] as $slug) {
            $plan = $bySlug[$slug] ?? null;
            if ($plan && !empty($plan['capabilities'][$capability])) return $plan;
        }
        foreach ($bySlug as $slug => $plan) {
            if ($slug === 'legacy') continue;
            if (!empty($plan['capabilities'][$capability])) return $plan;
        }
        return null;
    }

    public static function savePlan(array $input, int $id = 0): int {
        global $wpdb;
        if ($id) {
            $existing = self::plan($id);
            if ($existing && !empty($existing['is_system'])) return $id;
        }
        $slug = sanitize_key((string)($input['slug'] ?? ''));
        if ($slug === '') $slug = sanitize_title((string)($input['name'] ?? 'plano'));
        $baseSlug = $slug ?: 'plano';
        $suffix = 2;
        while ((int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::plansTable().' WHERE slug=%s AND id<>%d', $slug, $id)) > 0) {
            $slug = $baseSlug . '-' . $suffix++;
        }
        $capabilities = [];
        foreach (CapabilityCatalog::keys() as $key) if (!empty($input['capabilities'][$key])) $capabilities[$key] = 1;
        $limits = [];
        foreach (CapabilityCatalog::limitCatalog() as $key => $def) {
            $v = absint($input['limits'][$key] ?? 0); if ($v > 0) $limits[$key] = $v;
        }
        $data = [
            'slug'=>$slug,'name'=>sanitize_text_field((string)($input['name'] ?? 'Plano')),
            'description'=>sanitize_textarea_field((string)($input['description'] ?? '')),
            'monthly_price'=>(float)str_replace(',','.',(string)($input['monthly_price'] ?? 0)),
            'annual_price'=>(float)str_replace(',','.',(string)($input['annual_price'] ?? 0)),
            'currency'=>substr(strtoupper(preg_replace('/[^A-Z]/','',(string)($input['currency'] ?? 'EUR'))),0,3) ?: 'EUR',
            'capabilities_json'=>wp_json_encode($capabilities), 'limits_json'=>wp_json_encode($limits),
            'is_active'=>!empty($input['is_active']) ? 1 : 0,'updated_at'=>current_time('mysql'),
        ];
        if ($id) {
            $wpdb->update(self::plansTable(), $data, ['id'=>$id]);
            if (class_exists(EntitlementService::class)) EntitlementService::clearCache();
            return $id;
        }
        $data['is_system'] = 0; $data['created_at'] = current_time('mysql');
        $wpdb->insert(self::plansTable(), $data);
        if (class_exists(EntitlementService::class)) EntitlementService::clearCache();
        return (int)$wpdb->insert_id;
    }

    public static function subscription(int $clientId): ?array {
        global $wpdb; if (!$clientId || !self::tablesReady()) return null;
        $sql = 'SELECT s.*, p.slug plan_slug, p.name plan_name, p.capabilities_json plan_capabilities_json, p.limits_json plan_limits_json, p.currency, p.monthly_price, p.annual_price FROM '.self::subscriptionsTable().' s INNER JOIN '.self::plansTable().' p ON p.id=s.plan_id WHERE s.client_id=%d LIMIT 1';
        $row = $wpdb->get_row($wpdb->prepare($sql, $clientId), ARRAY_A);
        if (!$row && version_compare((string)get_option('routespro_version', '0.0.0'), '2.2.221', '>=')) {
            $clientExists = (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$wpdb->prefix.'routespro_clients WHERE id=%d', $clientId));
            $start = $clientExists ? self::planBySlug('start') : null;
            if ($start) {
                $wpdb->insert(self::subscriptionsTable(), [
                    'client_id'=>$clientId,
                    'plan_id'=>(int)$start['id'],
                    'status'=>'active',
                    'starts_at'=>current_time('mysql'),
                    'notes'=>'Atribuição automática do plano Start para novo cliente.',
                    'created_at'=>current_time('mysql'),
                    'updated_at'=>current_time('mysql'),
                ], ['%d','%d','%s','%s','%s','%s','%s']);
                $row = $wpdb->get_row($wpdb->prepare($sql, $clientId), ARRAY_A);
            }
        }
        if (!$row) return null;
        $row['capabilities'] = self::decode((string)$row['plan_capabilities_json']);
        $row['plan_limits'] = self::decode((string)$row['plan_limits_json']);
        $row['overrides'] = self::decode((string)($row['capability_overrides_json'] ?? ''));
        $row['limits'] = array_replace($row['plan_limits'], self::decode((string)($row['limit_overrides_json'] ?? '')));
        return $row;
    }

    public static function assign(int $clientId, int $planId, array $input = [], int $actorId = 0, bool $audit = true): int {
        global $wpdb; if (!$clientId || !$planId || !self::tablesReady() || !self::plan($planId)) return 0;
        $before = self::subscription($clientId);
        $overrides = [];
        foreach ((array)($input['capability_overrides'] ?? []) as $key=>$value) {
            if (!array_key_exists($key, CapabilityCatalog::all())) continue;
            if ($value === '' || $value === null) continue;
            $overrides[$key] = !empty($value) ? 1 : 0;
        }
        $limits = [];
        foreach (CapabilityCatalog::limitCatalog() as $key=>$def) {
            $v = absint($input['limit_overrides'][$key] ?? 0); if ($v > 0) $limits[$key] = $v;
        }
        $data = [
            'client_id'=>$clientId,'plan_id'=>$planId,
            'status'=>in_array(($input['status'] ?? 'active'), ['trial','active','past_due','suspended','expired','cancelled'], true) ? $input['status'] : 'active',
            'starts_at'=>self::dateTime((string)($input['starts_at'] ?? '')) ?: current_time('mysql'),
            'trial_ends_at'=>self::dateTime((string)($input['trial_ends_at'] ?? '')),
            'renews_at'=>self::dateTime((string)($input['renews_at'] ?? '')),
            'expires_at'=>self::dateTime((string)($input['expires_at'] ?? '')),
            'capability_overrides_json'=>wp_json_encode($overrides),
            'limit_overrides_json'=>wp_json_encode($limits),
            'status_reason'=>sanitize_text_field((string)($input['status_reason'] ?? '')),
            'notes'=>sanitize_textarea_field((string)($input['notes'] ?? '')),
            'updated_by'=>$actorId ?: get_current_user_id(), 'updated_at'=>current_time('mysql'),
        ];
        $existingId = (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::subscriptionsTable().' WHERE client_id=%d', $clientId));
        if ($existingId) { $wpdb->update(self::subscriptionsTable(), $data, ['id'=>$existingId]); $id=$existingId; }
        else { $data['created_at']=current_time('mysql'); $wpdb->insert(self::subscriptionsTable(), $data); $id=(int)$wpdb->insert_id; }
        if (class_exists(EntitlementService::class)) EntitlementService::clearCache();
        if ($audit && class_exists('RoutesPro\\Core\\AuditLog')) {
            $after = self::subscription($clientId);
            \RoutesPro\Core\AuditLog::record('subscription.changed','client_subscription',$id,$before,$after,['client_id'=>$clientId,'actor_user_id'=>$actorId ?: get_current_user_id()]);
            if (class_exists('RoutesPro\\Core\\EventBus')) \RoutesPro\Core\EventBus::emit('subscription.changed',['before'=>$before,'after'=>$after],['entity_type'=>'client_subscription','entity_id'=>$id,'client_id'=>$clientId], 'admin');
        }
        return $id;
    }

    public static function syncBilling(int $clientId, int $planId, string $status, $renewsAt = null, $graceUntil = null, string $reason = ''): int {
        global $wpdb;
        if (!$clientId || !$planId || !self::tablesReady()) return 0;
        $before = self::subscription($clientId);
        $allowed = ['trial','active','past_due','suspended','expired','cancelled'];
        $status = in_array($status, $allowed, true) ? $status : 'active';
        $data = [
            'plan_id'=>$planId,
            'status'=>$status,
            'status_reason'=>sanitize_text_field($reason),
            'updated_by'=>get_current_user_id(),
            'updated_at'=>current_time('mysql'),
        ];
        if ($renewsAt) $data['renews_at'] = is_numeric($renewsAt) ? wp_date('Y-m-d H:i:s', (int)$renewsAt) : self::dateTime((string)$renewsAt);
        if ($status === 'active') $data['expires_at'] = null;
        if ($status === 'trial' && $graceUntil) $data['trial_ends_at'] = is_numeric($graceUntil) ? wp_date('Y-m-d H:i:s',(int)$graceUntil) : self::dateTime((string)$graceUntil);
        $id = (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::subscriptionsTable().' WHERE client_id=%d', $clientId));
        if ($id) {
            $wpdb->update(self::subscriptionsTable(), $data, ['id'=>$id]);
        } else {
            $data['client_id']=$clientId;$data['starts_at']=current_time('mysql');$data['created_at']=current_time('mysql');
            $wpdb->insert(self::subscriptionsTable(),$data);$id=(int)$wpdb->insert_id;
        }
        if (class_exists(EntitlementService::class)) EntitlementService::clearCache();
        if (class_exists('RoutesPro\\Core\\AuditLog')) {
            $after=self::subscription($clientId);
            \RoutesPro\Core\AuditLog::record('subscription.billing_synced','client_subscription',$id,$before,$after,['client_id'=>$clientId]);
        }
        return $id;
    }

    public static function clientRows(): array {
        global $wpdb;
        if (!self::tablesReady()) {
            $rows = $wpdb->get_results('SELECT id,name FROM '.$wpdb->prefix.'routespro_clients ORDER BY name ASC', ARRAY_A) ?: [];
            foreach ($rows as &$row) $row += ['status'=>'legacy','renews_at'=>null,'expires_at'=>null,'plan_id'=>0,'plan_name'=>'','plan_slug'=>'legacy'];
            return $rows;
        }
        return $wpdb->get_results('SELECT c.id,c.name,s.status,s.renews_at,s.expires_at,p.id plan_id,p.name plan_name,p.slug plan_slug FROM '.$wpdb->prefix.'routespro_clients c LEFT JOIN '.self::subscriptionsTable().' s ON s.client_id=c.id LEFT JOIN '.self::plansTable().' p ON p.id=s.plan_id ORDER BY c.name ASC', ARRAY_A) ?: [];
    }

    private static function hydratePlan(array $row): array {
        $row['capabilities'] = self::decode((string)($row['capabilities_json'] ?? ''));
        $row['limits'] = self::decode((string)($row['limits_json'] ?? ''));
        return $row;
    }
    private static function decode(string $json): array { $v=json_decode($json,true); return is_array($v)?$v:[]; }
    private static function dateTime(string $value): ?string {
        $value=trim($value); if ($value==='') return null; $ts=strtotime($value); return $ts ? wp_date('Y-m-d H:i:s',$ts) : null;
    }
}
