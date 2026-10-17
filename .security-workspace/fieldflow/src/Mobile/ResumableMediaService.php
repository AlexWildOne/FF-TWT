<?php
namespace RoutesPro\Mobile;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) exit;

/**
 * FieldFlow 2.2.464 resilient media transport.
 *
 * Chunks are kept outside the normal media library until the protected form
 * replay is ready to commit. Sessions are bound to user + device + mutation +
 * file hash and are never trusted without the authenticated WordPress session.
 */
class ResumableMediaService {
    public const PROTOCOL = 'fieldflow-resumable-media-v1';
    private const TOKEN_VERSION = 1;
    private const TTL_SECONDS = 172800; // 48 h
    private const REF_TTL_SECONDS = 172800;
    private const MIN_CHUNK_BYTES = 131072; // 128 KiB
    private const MAX_CHUNK_BYTES = 1048576; // 1 MiB
    private const DEFAULT_CHUNK_BYTES = 524288; // 512 KiB
    private const MAX_FILES = 300;
    private const MAX_TOTAL_CHUNKS = 16384;
    public const SIZE_POLICY = 'storage-capacity-only';

    public static function start(WP_REST_Request $request) {
        self::maybe_cleanup();
        $payload = $request->get_json_params();
        if (!is_array($payload)) $payload = [];

        $user_id = get_current_user_id();
        $device_id = self::request_device_id($request);
        if ($user_id <= 0 || $device_id === '') return self::error('fieldflow_resumable_media_identity_missing', 'Não foi possível validar o dispositivo para o upload retomável.', 401);

        $mutation_id = self::clean_identifier((string)($payload['mutation_id'] ?? ''), 120);
        $field_name = self::clean_field_name((string)($payload['field_name'] ?? ''));
        $file_index = max(0, (int)($payload['file_index'] ?? 0));
        $name = sanitize_file_name((string)($payload['name'] ?? 'ficheiro')) ?: 'ficheiro';
        $mime = sanitize_text_field((string)($payload['mime_type'] ?? 'application/octet-stream'));
        $size = max(0, (int)($payload['size'] ?? 0));
        $sha256 = strtolower(sanitize_text_field((string)($payload['sha256'] ?? '')));
        $chunk_size = max(self::MIN_CHUNK_BYTES, min(self::MAX_CHUNK_BYTES, (int)($payload['chunk_size'] ?? self::DEFAULT_CHUNK_BYTES)));

        if ($mutation_id === '' || $field_name === '' || $file_index >= self::MAX_FILES) return self::error('fieldflow_resumable_media_request_invalid', 'O pedido de upload retomável não é válido.', 400);
        if ($size <= 0) return self::error('fieldflow_resumable_media_size_invalid', 'A fotografia não contém dados para upload.', 422, ['recovery'=>'refresh-runtime-and-rehydrate-draft','runtime_min'=>'2.2.464']);
        $capacity = self::available_file_capacity();
        if ($capacity > 0 && $size > $capacity) {
            return self::error('fieldflow_resumable_media_storage_capacity', 'O servidor não tem espaço temporário suficiente para receber esta fotografia neste momento.', 507, ['required_bytes'=>$size,'available_file_capacity'=>$capacity]);
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $sha256)) return self::error('fieldflow_resumable_media_hash_invalid', 'O hash SHA-256 da fotografia não é válido.', 422);

        $total_chunks = (int)ceil($size / $chunk_size);
        if ($total_chunks <= 0 || $total_chunks > self::MAX_TOTAL_CHUNKS) return self::error('fieldflow_resumable_media_chunks_invalid', 'A fotografia requer demasiados blocos de upload.', 413);

        $upload_id = self::upload_id($user_id, $device_id, $mutation_id, $field_name, $file_index, $sha256);
        $dir = self::session_dir($upload_id);
        if (!self::ensure_dir($dir)) return self::error('fieldflow_resumable_media_storage_failed', 'Não foi possível preparar o armazenamento temporário do upload.', 500);

        $manifest = self::read_manifest($upload_id);
        $expected = [
            'protocol'=>self::PROTOCOL,'upload_id'=>$upload_id,'user_id'=>$user_id,'device_id'=>$device_id,'mutation_id'=>$mutation_id,
            'field_name'=>$field_name,'file_index'=>$file_index,'name'=>$name,'mime_type'=>$mime,'size'=>$size,'sha256'=>$sha256,
            'chunk_size'=>$chunk_size,'total_chunks'=>$total_chunks,
        ];
        if ($manifest) {
            foreach (['user_id','device_id','mutation_id','field_name','file_index','size','sha256'] as $key) {
                if ((string)($manifest[$key] ?? '') !== (string)$expected[$key]) {
                    return self::error('fieldflow_resumable_media_session_mismatch', 'A sessão de upload existente não corresponde à fotografia protegida.', 409);
                }
            }
        } else {
            $manifest = array_merge($expected, ['created_at'=>time(),'updated_at'=>time(),'expires_at'=>time()+self::TTL_SECONDS,'completed'=>false]);
            if (!self::write_manifest($upload_id, $manifest)) return self::error('fieldflow_resumable_media_storage_failed', 'Não foi possível iniciar a sessão de upload.', 500);
        }
        $manifest['updated_at'] = time();
        $manifest['expires_at'] = time()+self::TTL_SECONDS;
        self::write_manifest($upload_id, $manifest);

        $status = self::session_status($manifest);
        $token = self::sign_token(['kind'=>'upload','upload_id'=>$upload_id,'uid'=>$user_id,'did'=>$device_id,'exp'=>time()+self::TTL_SECONDS]);
        return new WP_REST_Response(array_merge(['ok'=>true,'protocol'=>self::PROTOCOL,'upload_token'=>$token], $status), 200);
    }

    public static function chunk(WP_REST_Request $request) {
        $upload_id = self::clean_upload_id((string)$request['upload_id']);
        $verified = self::verify_request_token($request, $upload_id, 'upload');
        if (is_wp_error($verified)) return $verified;
        $manifest = self::read_manifest($upload_id);
        if (!$manifest) return self::error('fieldflow_resumable_media_session_missing', 'A sessão de upload já não está disponível.', 410);
        if (!empty($manifest['completed']) && is_file(self::assembled_path($upload_id))) {
            return new WP_REST_Response(array_merge(['ok'=>true,'protocol'=>self::PROTOCOL,'already_complete'=>true], self::session_status($manifest)), 200);
        }

        $index = isset($_SERVER['HTTP_X_FIELDFLOW_CHUNK_INDEX']) ? (int)$_SERVER['HTTP_X_FIELDFLOW_CHUNK_INDEX'] : -1;
        $chunk_sha = isset($_SERVER['HTTP_X_FIELDFLOW_CHUNK_SHA256']) ? strtolower(sanitize_text_field((string)wp_unslash($_SERVER['HTTP_X_FIELDFLOW_CHUNK_SHA256']))) : '';
        $total_chunks = (int)($manifest['total_chunks'] ?? 0);
        $chunk_size = (int)($manifest['chunk_size'] ?? 0);
        $total_size = (int)($manifest['size'] ?? 0);
        if ($index < 0 || $index >= $total_chunks) return self::error('fieldflow_resumable_media_chunk_invalid', 'O índice do bloco não é válido.', 400);
        if ($chunk_sha !== '' && !preg_match('/^[a-f0-9]{64}$/', $chunk_sha)) return self::error('fieldflow_resumable_media_chunk_hash_invalid', 'O hash do bloco não é válido.', 422);

        $body = (string)$request->get_body();
        $expected_size = min($chunk_size, max(0, $total_size - ($index * $chunk_size)));
        if ($expected_size <= 0 || strlen($body) !== $expected_size) {
            return self::error('fieldflow_resumable_media_chunk_size_mismatch', 'O bloco recebido não tem o tamanho esperado.', 422, ['expected_bytes'=>$expected_size,'received_bytes'=>strlen($body)]);
        }
        $actual_sha = hash('sha256', $body);
        if ($chunk_sha !== '' && !hash_equals($chunk_sha, $actual_sha)) return self::error('fieldflow_resumable_media_chunk_hash_mismatch', 'O bloco recebido falhou a verificação de integridade.', 422);

        $path = self::chunk_path($upload_id, $index);
        if (is_file($path)) {
            $existing_size = (int)filesize($path);
            $existing_sha = $existing_size === $expected_size ? strtolower((string)hash_file('sha256', $path)) : '';
            if ($existing_size === $expected_size && hash_equals($actual_sha, $existing_sha)) {
                return new WP_REST_Response(array_merge(['ok'=>true,'protocol'=>self::PROTOCOL,'chunk_index'=>$index,'already_received'=>true], self::session_status($manifest)), 200);
            }
        }

        $tmp = $path . '.tmp-' . wp_generate_password(8, false, false);
        $written = @file_put_contents($tmp, $body, LOCK_EX);
        if ($written !== $expected_size || !@rename($tmp, $path)) {
            @unlink($tmp);
            return self::error('fieldflow_resumable_media_chunk_store_failed', 'Não foi possível guardar o bloco recebido.', 500);
        }
        $manifest['updated_at'] = time();
        $manifest['expires_at'] = time()+self::TTL_SECONDS;
        self::write_manifest($upload_id, $manifest);
        return new WP_REST_Response(array_merge(['ok'=>true,'protocol'=>self::PROTOCOL,'chunk_index'=>$index,'chunk_sha256'=>$actual_sha], self::session_status($manifest)), 200);
    }

    public static function complete(WP_REST_Request $request) {
        $upload_id = self::clean_upload_id((string)$request['upload_id']);
        $verified = self::verify_request_token($request, $upload_id, 'upload');
        if (is_wp_error($verified)) return $verified;
        $manifest = self::read_manifest($upload_id);
        if (!$manifest) return self::error('fieldflow_resumable_media_session_missing', 'A sessão de upload já não está disponível.', 410);

        $assembled = self::assembled_path($upload_id);
        if (empty($manifest['completed']) || !is_file($assembled)) {
            $status = self::session_status($manifest);
            if (!empty($status['missing_chunks'])) return self::error('fieldflow_resumable_media_incomplete', 'Ainda faltam blocos para concluir esta fotografia.', 409, ['missing_chunks'=>$status['missing_chunks']]);

            $tmp = $assembled . '.tmp-' . wp_generate_password(8, false, false);
            $out = @fopen($tmp, 'wb');
            if (!$out) return self::error('fieldflow_resumable_media_assemble_failed', 'Não foi possível reconstruir a fotografia.', 500);
            $total = 0;
            $hash = hash_init('sha256');
            $ok = true;
            for ($i=0; $i<(int)$manifest['total_chunks']; $i++) {
                $part = self::chunk_path($upload_id, $i);
                $in = @fopen($part, 'rb');
                if (!$in) { $ok=false; break; }
                while (!feof($in)) {
                    $buf = fread($in, 1048576);
                    if ($buf === false) { $ok=false; break 2; }
                    if ($buf === '') continue;
                    $len = strlen($buf);$total += $len;hash_update($hash, $buf);
                    if (fwrite($out, $buf) !== $len) { $ok=false; break 2; }
                }
                fclose($in);
            }
            fclose($out);
            $actual_sha = $ok ? hash_final($hash) : '';
            if (!$ok || $total !== (int)$manifest['size'] || $actual_sha === '' || !hash_equals((string)$manifest['sha256'], $actual_sha)) {
                @unlink($tmp);
                return self::error('fieldflow_resumable_media_integrity_mismatch', 'A fotografia reconstruída não coincide com a cópia protegida no dispositivo.', 422, ['expected_bytes'=>(int)$manifest['size'],'received_bytes'=>$total]);
            }
            if (!@rename($tmp, $assembled)) { @unlink($tmp); return self::error('fieldflow_resumable_media_assemble_failed', 'Não foi possível finalizar a fotografia reconstruída.', 500); }
            $manifest['completed'] = true;
            $manifest['completed_at'] = time();
            $manifest['updated_at'] = time();
            $manifest['expires_at'] = time()+self::TTL_SECONDS;
            self::write_manifest($upload_id, $manifest);
            // The assembled file becomes the durable resumable source. Parts are no longer required.
            for ($i=0; $i<(int)$manifest['total_chunks']; $i++) @unlink(self::chunk_path($upload_id, $i));
        }

        $ref = self::sign_token([
            'kind'=>'ref','upload_id'=>$upload_id,'uid'=>(int)$manifest['user_id'],'did'=>(string)$manifest['device_id'],
            'field'=>(string)$manifest['field_name'],'idx'=>(int)$manifest['file_index'],'sha'=>(string)$manifest['sha256'],'exp'=>time()+self::REF_TTL_SECONDS,
        ]);
        return new WP_REST_Response([
            'ok'=>true,'protocol'=>self::PROTOCOL,'complete'=>true,'upload_id'=>$upload_id,'media_ref'=>$ref,
            'field_name'=>(string)$manifest['field_name'],'file_index'=>(int)$manifest['file_index'],'name'=>(string)$manifest['name'],
            'mime_type'=>(string)$manifest['mime_type'],'size'=>(int)$manifest['size'],'sha256'=>(string)$manifest['sha256'],
        ], 200);
    }

    /** @return array|WP_Error */
    public static function resolve_posted_refs() {
        $raw = isset($_POST['routespro_resumable_media_refs']) ? (string)wp_unslash($_POST['routespro_resumable_media_refs']) : '';
        if ($raw === '') return [];
        if (strlen($raw) > 1048576) return new WP_Error('fieldflow_resumable_media_refs_invalid', 'As referências de media excedem o limite de segurança.', ['status'=>422]);
        $payload = json_decode($raw, true);
        if (!is_array($payload) || (string)($payload['protocol'] ?? '') !== self::PROTOCOL || !is_array($payload['refs'] ?? null)) {
            return new WP_Error('fieldflow_resumable_media_refs_invalid', 'As referências de media retomável não são válidas.', ['status'=>422]);
        }
        $refs = array_slice($payload['refs'], 0, self::MAX_FILES);
        if (count($refs) !== count($payload['refs'])) return new WP_Error('fieldflow_resumable_media_refs_invalid', 'Foram recebidas demasiadas referências de media.', ['status'=>422]);
        $device_id = self::request_device_id(null);
        $resolved = [];
        foreach ($refs as $row) {
            if (!is_array($row)) return new WP_Error('fieldflow_resumable_media_ref_invalid', 'Foi recebida uma referência de media inválida.', ['status'=>422]);
            $token = (string)($row['token'] ?? '');
            $claims = self::verify_token($token, 'ref');
            if (is_wp_error($claims)) return $claims;
            if ((int)($claims['uid'] ?? 0) !== get_current_user_id() || !hash_equals((string)($claims['did'] ?? ''), $device_id)) {
                return new WP_Error('fieldflow_resumable_media_ref_forbidden', 'A fotografia pertence a outro utilizador ou dispositivo.', ['status'=>403]);
            }
            $upload_id = self::clean_upload_id((string)($claims['upload_id'] ?? ''));
            $manifest = self::read_manifest($upload_id);
            $assembled = self::assembled_path($upload_id);
            if (!$manifest || empty($manifest['completed']) || !is_file($assembled)) return new WP_Error('fieldflow_resumable_media_ref_missing', 'Uma fotografia retomável já não está disponível no armazenamento temporário.', ['status'=>410]);
            if ((int)$manifest['user_id'] !== get_current_user_id() || !hash_equals((string)$manifest['device_id'], $device_id)) return new WP_Error('fieldflow_resumable_media_ref_forbidden', 'A fotografia não pertence a este dispositivo.', ['status'=>403]);
            $posted_mutation = isset($_POST['routespro_offline_mutation_id']) ? self::clean_identifier((string)wp_unslash($_POST['routespro_offline_mutation_id']), 120) : '';
            if ($posted_mutation === '' || !hash_equals((string)$manifest['mutation_id'], $posted_mutation)) return new WP_Error('fieldflow_resumable_media_mutation_mismatch', 'A fotografia retomável não pertence a este reporte.', ['status'=>409]);
            if (!hash_equals((string)$manifest['sha256'], (string)($claims['sha'] ?? ''))) return new WP_Error('fieldflow_resumable_media_ref_invalid', 'A referência da fotografia falhou a verificação de integridade.', ['status'=>422]);
            if ((int)filesize($assembled) !== (int)$manifest['size']) return new WP_Error('fieldflow_resumable_media_integrity_mismatch', 'A fotografia temporária não tem o tamanho esperado.', ['status'=>422]);
            $actual_sha = strtolower((string)hash_file('sha256', $assembled));
            if ($actual_sha === '' || !hash_equals((string)$manifest['sha256'], $actual_sha)) return new WP_Error('fieldflow_resumable_media_integrity_mismatch', 'A fotografia temporária falhou a verificação SHA-256.', ['status'=>422]);
            $resolved[] = [
                'upload_id'=>$upload_id,'field_name'=>(string)$manifest['field_name'],'file_index'=>(int)$manifest['file_index'],
                'name'=>(string)$manifest['name'],'type'=>(string)$manifest['mime_type'],'tmp_name'=>$assembled,'size'=>(int)$manifest['size'],
                'sha256'=>(string)$manifest['sha256'],'error'=>UPLOAD_ERR_OK,'_fieldflow_resumable'=>true,
            ];
        }
        usort($resolved, static function($a,$b){ return ((int)$a['file_index']) <=> ((int)$b['file_index']); });
        return $resolved;
    }

    public static function sideload_file(array $file, array $overrides = []): array {
        if (empty($file['_fieldflow_resumable'])) return [];
        $source = (string)($file['tmp_name'] ?? '');
        if ($source === '' || !is_file($source)) return ['error'=>'Ficheiro retomável temporário indisponível.'];
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $copy = wp_tempnam((string)($file['name'] ?? 'fieldflow-media'));
        if (!$copy || !@copy($source, $copy)) { if ($copy) @unlink($copy); return ['error'=>'Não foi possível preparar a fotografia retomável para publicação.']; }
        $sideload = ['name'=>(string)($file['name'] ?? 'fieldflow-media'),'type'=>(string)($file['type'] ?? ''),'tmp_name'=>$copy,'error'=>UPLOAD_ERR_OK,'size'=>(int)($file['size'] ?? filesize($copy))];
        $overrides['test_form'] = false;
        $result = wp_handle_sideload($sideload, $overrides);
        if (!empty($result['error'])) @unlink($copy);
        return is_array($result) ? $result : ['error'=>'Falha ao publicar fotografia retomável.'];
    }

    private static function session_status(array $manifest): array {
        $total = max(0, (int)($manifest['total_chunks'] ?? 0));
        $received = [];$received_bytes = 0;
        if (!empty($manifest['completed']) && is_file(self::assembled_path((string)$manifest['upload_id']))) {
            $received = $total > 0 ? range(0, $total-1) : [];
            $received_bytes = (int)($manifest['size'] ?? 0);
            return ['upload_id'=>(string)$manifest['upload_id'],'complete'=>true,'received_chunks'=>$received,'missing_chunks'=>[],'received_bytes'=>$received_bytes,'total_bytes'=>(int)$manifest['size'],'total_chunks'=>$total,'chunk_size'=>(int)$manifest['chunk_size']];
        }
        $missing = [];
        for ($i=0;$i<$total;$i++) {
            $path=self::chunk_path((string)$manifest['upload_id'],$i);
            $expected=min((int)$manifest['chunk_size'],max(0,(int)$manifest['size']-($i*(int)$manifest['chunk_size'])));
            if (is_file($path) && (int)filesize($path)===$expected) { $received[]=$i;$received_bytes += $expected; } else $missing[]=$i;
        }
        return ['upload_id'=>(string)$manifest['upload_id'],'complete'=>false,'received_chunks'=>$received,'missing_chunks'=>$missing,'received_bytes'=>$received_bytes,'total_bytes'=>(int)$manifest['size'],'total_chunks'=>$total,'chunk_size'=>(int)$manifest['chunk_size']];
    }

    private static function verify_request_token(WP_REST_Request $request, string $upload_id, string $kind) {
        if ($upload_id === '') return self::error('fieldflow_resumable_media_session_invalid', 'A sessão de upload não é válida.', 400);
        $token = isset($_SERVER['HTTP_X_FIELDFLOW_UPLOAD_TOKEN']) ? (string)wp_unslash($_SERVER['HTTP_X_FIELDFLOW_UPLOAD_TOKEN']) : '';
        $claims = self::verify_token($token, $kind);
        if (is_wp_error($claims)) return $claims;
        $device_id = self::request_device_id($request);
        if ((int)($claims['uid'] ?? 0) !== get_current_user_id() || !hash_equals((string)($claims['did'] ?? ''), $device_id) || !hash_equals((string)($claims['upload_id'] ?? ''), $upload_id)) {
            return self::error('fieldflow_resumable_media_token_forbidden', 'A sessão de upload não pertence a este dispositivo.', 403);
        }
        return $claims;
    }

    private static function sign_token(array $claims): string {
        $claims['v'] = self::TOKEN_VERSION;
        $payload = self::b64url_encode((string)wp_json_encode($claims, JSON_UNESCAPED_SLASHES));
        $sig = hash_hmac('sha256', $payload, self::secret());
        return $payload . '.' . $sig;
    }

    private static function verify_token(string $token, string $kind) {
        $parts = explode('.', trim($token));
        if (count($parts) !== 2 || !preg_match('/^[a-f0-9]{64}$/', $parts[1])) return new WP_Error('fieldflow_resumable_media_token_invalid', 'O token de upload retomável não é válido.', ['status'=>403]);
        $expected = hash_hmac('sha256', $parts[0], self::secret());
        if (!hash_equals($expected, $parts[1])) return new WP_Error('fieldflow_resumable_media_token_invalid', 'O token de upload retomável falhou a assinatura.', ['status'=>403]);
        $json = self::b64url_decode($parts[0]);$claims = json_decode($json, true);
        if (!is_array($claims) || (int)($claims['v'] ?? 0) !== self::TOKEN_VERSION || (string)($claims['kind'] ?? '') !== $kind || (int)($claims['exp'] ?? 0) < time()) {
            return new WP_Error('fieldflow_resumable_media_token_expired', 'O token de upload retomável expirou.', ['status'=>403]);
        }
        return $claims;
    }

    private static function request_device_id(?WP_REST_Request $request): string {
        $raw = '';
        if ($request) $raw = (string)$request->get_header('x-fieldflow-device-id');
        if ($raw === '' && isset($_SERVER['HTTP_X_FIELDFLOW_DEVICE_ID'])) $raw = (string)wp_unslash($_SERVER['HTTP_X_FIELDFLOW_DEVICE_ID']);
        return self::clean_identifier($raw, 128);
    }

    private static function upload_id(int $uid, string $did, string $mutation, string $field, int $index, string $sha): string {
        return substr(hash_hmac('sha256', implode('|', [$uid,$did,$mutation,$field,$index,$sha]), self::secret()), 0, 48);
    }
    private static function clean_upload_id(string $value): string { $value=strtolower(trim($value));return preg_match('/^[a-f0-9]{48}$/',$value)?$value:''; }
    private static function clean_identifier(string $value, int $max): string { $value=preg_replace('/[^A-Za-z0-9:_\-.]/','',trim($value)) ?: '';return substr($value,0,$max); }
    private static function clean_field_name(string $value): string { $value=trim($value);if ($value===''||strlen($value)>190||!preg_match('/^[A-Za-z0-9_\-\[\]]+$/',$value)) return '';return $value; }
    private static function secret(): string { return hash('sha256', wp_salt('auth') . '|fieldflow|' . self::PROTOCOL); }
    private static function b64url_encode(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
    private static function b64url_decode(string $value): string { $value=strtr($value,'-_','+/');$value.=str_repeat('=',(4-strlen($value)%4)%4);$decoded=base64_decode($value,true);return $decoded===false?'':$decoded; }
    /**
     * There is deliberately no fixed FieldFlow image-size ceiling here.
     * Resumable uploads never place the complete photo in one HTTP request, so
     * upload_max_filesize/post_max_size are not transport limits. We only gate
     * against real temporary storage capacity, reserving room for assembly and
     * the final media-library copy.
     */
    private static function available_file_capacity(): int {
        $probe = is_dir(WP_CONTENT_DIR) ? WP_CONTENT_DIR : dirname(__DIR__, 3);
        $free = @disk_free_space($probe);
        if (!is_numeric($free) || (float)$free <= 0) return 0;
        $free = (float)$free;
        $reserve = max(256 * MB_IN_BYTES, (int)floor($free * 0.10));
        $usable = max(0.0, $free - $reserve);
        // During completion the server can temporarily hold chunk parts plus the
        // assembled source, and sideloading can briefly add the final copy.
        $capacity = (int)floor($usable / 2.25);
        return max(0, $capacity);
    }

    private static function base_dir(): string { return trailingslashit(WP_CONTENT_DIR) . 'fieldflow-private-resumable'; }
    private static function session_dir(string $upload_id): string { return trailingslashit(self::base_dir()) . $upload_id; }
    private static function manifest_path(string $upload_id): string { return trailingslashit(self::session_dir($upload_id)) . 'manifest.json'; }
    private static function assembled_path(string $upload_id): string { return trailingslashit(self::session_dir($upload_id)) . 'assembled.bin'; }
    private static function chunk_path(string $upload_id, int $index): string { return trailingslashit(self::session_dir($upload_id)) . sprintf('part-%04d.bin', $index); }

    private static function ensure_dir(string $dir): bool {
        if (!is_dir(self::base_dir())) {
            if (!wp_mkdir_p(self::base_dir())) return false;
            @file_put_contents(trailingslashit(self::base_dir()).'index.php', "<?php\nhttp_response_code(404);\nexit;\n");
            @file_put_contents(trailingslashit(self::base_dir()).'.htaccess', "Require all denied\nDeny from all\n");
        }
        return is_dir($dir) || wp_mkdir_p($dir);
    }
    private static function read_manifest(string $upload_id): array { $path=self::manifest_path($upload_id);if(!is_file($path))return[];$raw=@file_get_contents($path);$data=$raw!==false?json_decode($raw,true):null;return is_array($data)?$data:[]; }
    private static function write_manifest(string $upload_id, array $manifest): bool { $dir=self::session_dir($upload_id);if(!self::ensure_dir($dir))return false;$path=self::manifest_path($upload_id);$tmp=$path.'.tmp-'.wp_generate_password(8,false,false);$ok=@file_put_contents($tmp,wp_json_encode($manifest,JSON_UNESCAPED_SLASHES),LOCK_EX)!==false&&@rename($tmp,$path);if(!$ok)@unlink($tmp);return $ok; }

    private static function maybe_cleanup(): void {
        try { if (random_int(1, 20) !== 1) return; } catch (\Throwable $e) { return; }
        $base=self::base_dir();if(!is_dir($base))return;$dirs=glob(trailingslashit($base).'*',GLOB_ONLYDIR) ?: [];$now=time();$seen=0;
        foreach($dirs as $dir){if(++$seen>200)break;$upload_id=basename($dir);if(!preg_match('/^[a-f0-9]{48}$/',$upload_id))continue;$manifest=self::read_manifest($upload_id);$expires=(int)($manifest['expires_at']??0);if($expires>0&&$expires>$now)continue;self::delete_dir($dir);}
    }
    private static function delete_dir(string $dir): void { if(!is_dir($dir))return;$files=scandir($dir)?:[];foreach($files as $file){if($file==='.'||$file==='..')continue;$path=$dir.DIRECTORY_SEPARATOR.$file;if(is_dir($path))self::delete_dir($path);else @unlink($path);}@rmdir($dir); }

    private static function error(string $code, string $message, int $status, array $extra = []) {
        return new WP_Error($code, $message, array_merge(['status'=>$status,'protocol'=>self::PROTOCOL], $extra));
    }
}
