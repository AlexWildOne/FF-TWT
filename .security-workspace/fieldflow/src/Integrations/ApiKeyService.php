<?php
namespace RoutesPro\Integrations;

use RoutesPro\SaaS\EntitlementService;
use RoutesPro\Security\SecurityService;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) exit;

class ApiKeyService {
    private static ?array $current = null;

    public static function table(): string { global $wpdb; return $wpdb->prefix . 'routespro_api_keys'; }
    public static function tableReady(): bool { global $wpdb; $table=self::table(); return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table; }

    public static function capabilityCatalog(): array {
        return [
            'api.clients.read'=>'Clientes', 'api.projects.read'=>'Projetos e campanhas', 'api.locations.read'=>'Lojas e PDV',
            'api.routes.read'=>'Rotas', 'api.visits.read'=>'Visitas e paragens', 'api.submissions.read'=>'Submissões e respostas',
            'api.stocks.read'=>'Stocks', 'api.briefings.read'=>'Briefings', 'api.implementations.read'=>'Implementações',
            'api.analytics.read'=>'Analytics e Power BI', 'api.webhooks.manage'=>'Gestão de webhooks',
        ];
    }

    public static function create(array $input, int $actorId = 0): array {
        global $wpdb;
        if(!self::tableReady()) return ['id'=>0,'key'=>'','error'=>'A tabela de chaves API ainda não está disponível.'];
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $prefix = substr($secret, 0, 10);
        $caps = array_values(array_intersect(array_keys(self::capabilityCatalog()), array_map('sanitize_key', (array)($input['capabilities'] ?? []))));
        $clientId = absint($input['client_id'] ?? 0);
        $projectId = absint($input['project_id'] ?? 0);
        if ($projectId) {
            $projectClient = absint($wpdb->get_var($wpdb->prepare('SELECT client_id FROM ' . $wpdb->prefix . 'routespro_projects WHERE id=%d', $projectId)));
            if (!$projectClient) return ['id'=>0,'key'=>'','error'=>'Projeto inválido.'];
            if ($clientId && $clientId !== $projectClient) return ['id'=>0,'key'=>'','error'=>'O projeto não pertence ao cliente selecionado.'];
            $clientId = $projectClient;
        }
        if ($clientId && class_exists(EntitlementService::class) && !EntitlementService::clientHas($clientId, 'api.access')) {
            return ['id'=>0,'key'=>'','error'=>'A API pública não está incluída no plano deste cliente.'];
        }
        $data = [
            'name'=>sanitize_text_field((string)($input['name'] ?? 'Integração')),
            'key_prefix'=>$prefix,
            'key_hash'=>hash_hmac('sha256', $secret, (string)wp_salt('auth')),
            'client_id'=>$clientId ?: null,
            'project_id'=>$projectId ?: null,
            'capabilities_json'=>wp_json_encode($caps),
            'allowed_ips_json'=>wp_json_encode(self::normaliseIps((string)($input['allowed_ips'] ?? ''))),
            'rate_limit_per_minute'=>max(10, min(5000, absint($input['rate_limit_per_minute'] ?? 120))),
            'expires_at'=>self::dateTime((string)($input['expires_at'] ?? '')),
            'status'=>'active',
            'created_by'=>$actorId ?: get_current_user_id(),
            'created_at'=>current_time('mysql'), 'updated_at'=>current_time('mysql'),
        ];
        $wpdb->insert(self::table(), $data);
        $id = (int)$wpdb->insert_id;
        if (!$id) return ['id'=>0,'key'=>''];
        $full = 'ffpk_' . $id . '_' . $secret;
        if (class_exists('RoutesPro\\Core\\AuditLog')) {
            \RoutesPro\Core\AuditLog::record('api_key.created','api_key',$id,null,array_merge($data,['key_hash'=>'[redacted]']),['client_id'=>(int)($data['client_id']??0),'project_id'=>(int)($data['project_id']??0)]);
        }
        return ['id'=>$id,'key'=>$full,'prefix'=>'ffpk_'.$id.'_'.$prefix];
    }

    public static function authenticate(WP_REST_Request $request) {
        self::$current = null;
        $raw = trim((string)$request->get_header('x-fieldflow-key'));
        if ($raw === '') {
            $auth = trim((string)$request->get_header('authorization'));
            if (stripos($auth, 'Bearer ') === 0) $raw = trim(substr($auth, 7));
        }
        if (!preg_match('/^ffpk_(\d+)_([A-Za-z0-9_-]{20,})$/', $raw, $m)) return new WP_Error('fieldflow_api_key_missing','Chave API inválida ou em falta.',['status'=>401]);
        global $wpdb;
        if(!self::tableReady()) return new WP_Error('fieldflow_api_unavailable','A API pública ainda não está inicializada.',['status'=>503]);
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE id=%d', absint($m[1])), ARRAY_A);
        if (!$row || (string)$row['status'] !== 'active') return new WP_Error('fieldflow_api_key_revoked','Chave API revogada ou inexistente.',['status'=>401]);
        if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) <= time()) return new WP_Error('fieldflow_api_key_expired','A chave API expirou.',['status'=>401]);
        $candidate = hash_hmac('sha256', (string)$m[2], (string)wp_salt('auth'));
        if (!hash_equals((string)$row['key_hash'], $candidate)) return new WP_Error('fieldflow_api_key_invalid','Chave API inválida.',['status'=>401]);
        $ip = self::requestIp();
        $allowed = json_decode((string)($row['allowed_ips_json'] ?? '[]'), true);
        if (is_array($allowed) && $allowed && !self::ipAllowed($ip, $allowed)) return new WP_Error('fieldflow_api_ip_denied','O endereço IP não está autorizado.',['status'=>403]);
        $clientId = absint($row['client_id'] ?? 0);
        $projectId = absint($row['project_id'] ?? 0);
        if (!$clientId && $projectId) {
            $clientId = absint($wpdb->get_var($wpdb->prepare('SELECT client_id FROM ' . $wpdb->prefix . 'routespro_projects WHERE id=%d', $projectId)));
            if (!$clientId) return new WP_Error('fieldflow_api_scope_invalid','O âmbito da chave já não é válido.',['status'=>403]);
            $row['client_id'] = $clientId;
        }
        if ($clientId && class_exists(EntitlementService::class) && !EntitlementService::clientHas($clientId, 'api.access')) {
            return new WP_Error('fieldflow_api_plan_required','A API pública não está incluída no plano deste cliente.',['status'=>403,'client_id'=>$clientId]);
        }
        $bucket = 'fieldflow_api_key_' . (int)$row['id'] . '_' . gmdate('YmdHi');
        $hits = (int)get_transient($bucket);
        $limit = max(10, (int)($row['rate_limit_per_minute'] ?? 120));
        if ($hits >= $limit) return new WP_Error('fieldflow_api_rate_limit','Limite de pedidos por minuto atingido.',['status'=>429,'retry_after'=>60]);
        set_transient($bucket, $hits + 1, 90);
        $caps = json_decode((string)$row['capabilities_json'], true);
        $row['capabilities'] = is_array($caps) ? $caps : [];
        self::$current = $row;
        $wpdb->update(self::table(), ['last_used_at'=>current_time('mysql'),'last_ip_hash'=>hash('sha256',$ip.'|'.wp_salt('nonce')),'updated_at'=>current_time('mysql')], ['id'=>(int)$row['id']]);
        return true;
    }

    public static function current(): array { return self::$current ?: []; }
    public static function has(string $cap): bool { return in_array($cap, (array)(self::current()['capabilities'] ?? []), true); }
    public static function require(string $cap) { return self::has($cap) ? true : new WP_Error('fieldflow_api_scope_denied','A chave não possui a capacidade necessária.',['status'=>403,'required_capability'=>$cap]); }

    public static function revoke(int $id): bool {
        global $wpdb;
        $ok = $wpdb->update(self::table(), ['status'=>'revoked','revoked_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')], ['id'=>$id]);
        if ($ok !== false && class_exists('RoutesPro\\Core\\AuditLog')) \RoutesPro\Core\AuditLog::record('api_key.revoked','api_key',$id,null,['status'=>'revoked']);
        return $ok !== false;
    }

    public static function rows(int $limit=100): array { global $wpdb; if(!self::tableReady()) return []; return $wpdb->get_results('SELECT * FROM '.self::table().' ORDER BY id DESC LIMIT '.max(1,min(500,$limit)), ARRAY_A) ?: []; }

    private static function dateTime(string $value): ?string {
        $value = trim($value); if ($value==='') return null; $ts=strtotime($value); return $ts ? wp_date('Y-m-d H:i:s',$ts) : null;
    }
    private static function normaliseIps(string $raw): array { return array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/', $raw) ?: [])))); }
    private static function requestIp(): string { return sanitize_text_field((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')); }
    private static function ipAllowed(string $ip, array $rules): bool {
        foreach ($rules as $rule) {
            $rule=trim((string)$rule); if ($rule===$ip) return true;
            if (strpos($rule,'/')!==false && filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)) {
                [$net,$bits]=array_pad(explode('/',$rule,2),2,'32'); $bits=(int)$bits;
                if (filter_var($net,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4) && $bits>=0 && $bits<=32) {
                    $mask=$bits===0?0:(-1 << (32-$bits)); if ((ip2long($ip)&$mask)===(ip2long($net)&$mask)) return true;
                }
            }
        }
        return false;
    }
}
