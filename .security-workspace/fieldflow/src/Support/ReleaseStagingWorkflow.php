<?php
namespace RoutesPro\Support;

use RoutesPro\Cloud\PrivatePluginUpdater;
use RoutesPro\Cloud\PrivateUpdateLifecycle;
use RoutesPro\Cloud\ReleasePublicationReceipt;
use RoutesPro\Cloud\ReleasePublisherClient;
use RoutesPro\Core\AuditLog;

if (!defined('ABSPATH')) exit;

/**
 * 9DB: isolated, operator-owned staging and restart-safe publication journal.
 * The original direct ZIP publication flow remains available in ReleaseManager.
 * A journal is evidence, never an alternative to the authoritative release gate.
 */
final class ReleaseStagingWorkflow {
    public const CONTRACT = 'fieldflow-release-manager-assisted-workflow-9db-v1';
    public const TTL_SECONDS = 86400;
    public const JOURNAL_TTL_SECONDS = 2592000;
    private const OPTION_PREFIX = 'fieldflow_release_workflow_9db_';
    private const HISTORY_OPTION = 'fieldflow_release_workflow_9db_history';
    private const EVENT_LIMIT = 12;

    public static function current(int $userId): array {
        self::requireOperator($userId);
        $raw = get_option(self::optionName($userId), []);
        if (!is_array($raw) || !$raw) return [];
        if ((int)($raw['user_id'] ?? -1) !== $userId || (int)($raw['blog_id'] ?? -1) !== get_current_blog_id()) {
            return [];
        }
        $raw['integrity_ok'] = false;
        $raw['expired'] = (int)($raw['expires'] ?? 0) <= time();
        if ($raw['expired']) {
            self::unlinkPrevious($raw, self::privateDirectory());
            if (in_array((string)($raw['status'] ?? ''), ['staged','completed'], true)
                || (int)($raw['created'] ?? 0) + self::JOURNAL_TTL_SECONDS < time()) {
                self::archiveEvent($raw, 'Registo temporário concluído ou expirado.');
                delete_option(self::optionName($userId));
                return [];
            }
            $raw['integrity_message'] = 'O ZIP temporário expirou. A publicação não pode ser repetida; mantém-se apenas a consulta remota do recibo durante 30 dias.';
        } else {
            $path = self::filePath($raw);
            if ($path !== '' && is_file($path) && !is_link($path)
                && (int)filesize($path) === (int)($raw['bytes'] ?? -1)
                && hash_equals((string)($raw['sha256'] ?? ''), (string)hash_file('sha256', $path))) {
                $raw['integrity_ok'] = true;
            } else {
                $raw['integrity_message'] = 'O ZIP privado não está acessível ou deixou de corresponder ao SHA-256 inicial. Publicação bloqueada.';
            }
        }
        unset($raw['filename']); // Never expose filesystem paths to the view.
        return $raw;
    }

    /** Called only after PackageValidator + ReleasePreflight approve the uploaded ZIP. */
    public static function stage(array $upload, int $userId, array $package): array {
        self::requireOperator($userId);
        $stageLock = self::acquireLock('stage-' . $userId);
        try {
        $src = (string)($upload['tmp_name'] ?? '');
        $bytes = (int)($upload['size'] ?? 0);
        $sha = strtolower((string)($package['sha256'] ?? ''));
        if (empty($package['ok']) || !empty($upload['error']) || $bytes <= 0
            || $bytes > ReleasePackageValidator::MAX_BYTES || !is_uploaded_file($src)
            || strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION)) !== 'zip'
            || !preg_match('/^[a-f0-9]{64}$/', $sha)
            || $bytes !== (int)($package['bytes'] ?? -1)
            || !hash_equals($sha, (string)hash_file('sha256', $src))) {
            throw new \RuntimeException('Upload recusado: validação, tamanho ou integridade do ZIP não comprovados.');
        }
        $previous = self::current($userId);
        $previousStorage = get_option(self::optionName($userId), []);
        if ($previous && !in_array((string)($previous['status'] ?? ''), ['staged', 'completed'], true)) {
            throw new \RuntimeException('Existe uma publicação interrompida. Verifica ou descarta essa operação explicitamente antes de substituir o ZIP.');
        }
        $dir = self::privateDirectory();
        $filename = bin2hex(random_bytes(24)) . '.zip';
        $dest = $dir . '/' . $filename;
        if (!move_uploaded_file($src, $dest)) throw new \RuntimeException('Não foi possível guardar o ZIP na pasta temporária privada. O modo manual mantém-se disponível.');
        @chmod($dest, 0600);
        if (!is_file($dest) || is_link($dest) || filesize($dest) !== $bytes || !hash_equals($sha, (string)hash_file('sha256', $dest))) {
            @unlink($dest);
            throw new \RuntimeException('A cópia privada do ZIP não passou a verificação SHA-256.');
        }
        $now = time();
        $record = [
            'contract'=>self::CONTRACT, 'token'=>bin2hex(random_bytes(24)),
            'filename'=>$filename, 'user_id'=>$userId, 'blog_id'=>get_current_blog_id(),
            'version'=>(string)$package['version'], 'sha256'=>$sha, 'bytes'=>$bytes,
            'created'=>$now, 'updated'=>$now, 'expires'=>$now + self::TTL_SECONDS,
            'status'=>'staged', 'channel'=>'', 'blob'=>[], 'events'=>[],
        ];
        self::event($record, 'ZIP validado e guardado temporariamente. Nenhuma publicação foi iniciada.');
        try {
            self::save($record);
        } catch (\Throwable $e) {
            @unlink($dest);
            throw $e;
        }
        if (is_array($previousStorage) && (int)($previousStorage['user_id'] ?? -1) === $userId) self::unlinkPrevious($previousStorage, $dir);
        return self::current($userId);
        } finally { self::unlock($stageLock); }
    }

    /** Explicit deletion only, including partially completed operations. */
    public static function discard(int $userId, string $token): void {
        self::requireOperator($userId);
        $record = self::lockedRecord($userId, $token, false, true);
        $lock = self::acquireLock('stage-' . $userId);
        try {
            $record = self::lockedRecord($userId, $token, false, true);
            self::archiveEvent($record, 'Descartada pelo administrador. O catálogo remoto não foi alterado.');
            delete_option(self::optionName($userId));
            self::unlinkPrevious($record, self::privateDirectory());
        } finally { self::unlock($lock); }
    }

    /**
     * No implicit retries: upload and catalog requests are journaled BEFORE I/O.
     * A timeout becomes uncertain, not "failed so retry".
     */
    public static function publish(int $userId, string $token, string $channel, array $options): array {
        self::requireOperator($userId);
        if (!in_array($channel, ['stable','beta'], true)) throw new \RuntimeException('Canal inválido.');
        $record = self::lockedRecord($userId, $token, true);
        $lock = self::acquireLock('publish-' . $record['version'] . '-' . $channel);
        $stageLock = null;
        try {
            $stageLock = self::acquireLock('stage-' . $userId);
            $record = self::lockedRecord($userId, $token, true);
            if ((string)($record['status'] ?? '') !== 'staged') {
                throw new \RuntimeException('Esta operação já começou. Usa Verificar no catálogo para evitar duplicar publicações.');
            }
            $package = self::revalidate($record);
            $health = ReleasePublisherClient::health();
            if (empty($health['ok'])) throw new \RuntimeException('Release Publisher indisponível: ' . (string)($health['message'] ?? 'health check falhou.'));
            // Before any side effect, detect an existing immutable release.
            $existing = ReleasePublisherClient::findRelease(self::confirmedInspect($record['version'], $channel), $record['version'], $channel);
            if ($existing) {
                $record['channel'] = $channel;
                self::saveStatus($record, 'catalog_confirmed', 'Release já encontrada no catálogo; verificar SHA antes de registar recibo.');
                return self::completeFromRemote($record, $existing, $health);
            }
            $record['channel'] = $channel;
            $record['publish_options'] = self::publicationOptions($options);
            self::saveStatus($record, 'upload_unknown', 'Upload iniciado; se a ligação falhar, não repetir sem reconciliação.');
            $uploaded = ReleasePublisherClient::uploadPackage(self::verifiedPath($record), $package, $channel);
            if (empty($uploaded['success']) && empty($uploaded['ok'])) {
                self::saveStatus($record, 'upload_unknown', 'Upload sem confirmação. Verifica remotamente antes de qualquer nova tentativa.');
                throw new \RuntimeException('Upload sem confirmação: ' . (string)($uploaded['message'] ?? 'resposta inesperada') . '. A publicação foi suspensa para impedir duplicações.');
            }
            $record['blob'] = self::verifiedBlob($uploaded, $record, $channel);
            self::saveStatus($record, 'blob_ready', 'Blob privado confirmado com o mesmo SHA-256. Catálogo ainda não iniciado.');
            return self::publishCatalogOnce($record, $package, $record['publish_options'], $health);
        } finally { if ($stageLock !== null) self::unlock($stageLock); self::unlock($lock); }
    }

    /** Resume ONLY when the remote Blob was acknowledged but catalog POST never started. */
    public static function resumeCatalog(int $userId, string $token): array {
        self::requireOperator($userId);
        $record = self::lockedRecord($userId, $token, true);
        $channel = (string)($record['channel'] ?? '');
        if (!in_array($channel, ['stable','beta'], true)) throw new \RuntimeException('Canal da operação indisponível.');
        $lock = self::acquireLock('publish-' . $record['version'] . '-' . $channel);
        $stageLock = null;
        try {
            $stageLock = self::acquireLock('stage-' . $userId);
            $record = self::lockedRecord($userId, $token, true);
            if (($record['status'] ?? '') !== 'blob_ready') throw new \RuntimeException('Esta etapa já foi iniciada ou não tem um Blob confirmado. Verifica o catálogo antes de prosseguir.');
            $package = self::revalidate($record);
            $health = ReleasePublisherClient::health();
            if (empty($health['ok'])) throw new \RuntimeException('Release Publisher indisponível.');
            $existing = ReleasePublisherClient::findRelease(self::confirmedInspect($record['version'], $channel), $record['version'], $channel);
            if ($existing) return self::completeFromRemote($record, $existing, $health);
            self::verifiedBlob((array)$record['blob'], $record, $channel);
            return self::publishCatalogOnce($record, $package, (array)($record['publish_options'] ?? []), $health);
        } finally { if ($stageLock !== null) self::unlock($stageLock); self::unlock($lock); }
    }

    /** Reconciliation reads the remote catalogue; never repeats a remote write. */
    public static function reconcile(int $userId, string $token): array {
        self::requireOperator($userId);
        $record = self::lockedRecord($userId, $token, false, true);
        $channel = (string)($record['channel'] ?? '');
        if (!in_array($channel, ['stable','beta'], true)) throw new \RuntimeException('Ainda não existe uma tentativa de publicação a reconciliar.');
        $lock = self::acquireLock('publish-' . $record['version'] . '-' . $channel);
        $stageLock = null;
        try {
            $stageLock = self::acquireLock('stage-' . $userId);
            $record = self::lockedRecord($userId, $token, false, true);
            if (($record['status'] ?? '') === 'completed') return ['ok'=>true,'message'=>'Publicação anteriormente confirmada e registada.'];
            // Recovery does not resend bytes. Original immutable SHA remains the authority
            // even if the 24-hour ZIP staging copy has already been purged.
            if ((int)$record['expires'] > time()) self::verifiedPath($record);
            $health = ReleasePublisherClient::health();
            if (empty($health['ok'])) throw new \RuntimeException('O Publisher está indisponível. A operação continua pendente, sem repetição automática.');
            $response = self::confirmedInspect($record['version'], $channel);
            $remote = ReleasePublisherClient::findRelease($response, $record['version'], $channel);
            if (!$remote) {
                self::saveStatus($record, (string)$record['status'], 'Catálogo consultado sem confirmação. Nenhum pedido de publicação foi repetido.');
                return ['ok'=>false,'message'=>'Sem release confirmada no catálogo. Estado conservado; aguarda a consistência remota. Apenas um Blob confirmado permite retomar a etapa de catálogo.'];
            }
            return self::completeFromRemote($record, $remote, $health);
        } finally { if ($stageLock !== null) self::unlock($stageLock); self::unlock($lock); }
    }

    public static function history(): array {
        self::requireOperator(get_current_user_id());
        $history = get_option(self::HISTORY_OPTION, []);
        return is_array($history) ? array_slice($history, 0, 10) : [];
    }

    /** An unavailable inspect request never authorises a new remote upload. */
    private static function confirmedInspect(string $version, string $channel): array {
        $result = ReleasePublisherClient::inspect($version, $channel);
        if ((array_key_exists('success', $result) && empty($result['success']))
            || (array_key_exists('ok', $result) && empty($result['ok']))) {
            throw new \RuntimeException('A consulta remota falhou. A publicação fica suspensa para evitar duplicação.');
        }
        if ((!empty($result['ok']) || !empty($result['success']))
            || array_key_exists('releases', $result)
            || array_key_exists('release', $result)
            || (isset($result['data']) && is_array($result['data']) && array_key_exists('releases', $result['data']))) {
            return $result;
        }
        throw new \RuntimeException('Não foi possível comprovar uma leitura válida do catálogo remoto. Por segurança não será iniciado nem repetido um pedido de publicação.');
    }

    private static function publicationOptions(array $data): array {
        return [
            'requires_wp'=>sanitize_text_field((string)($data['requires_wp'] ?? '6.4')),
            'requires_php'=>sanitize_text_field((string)($data['requires_php'] ?? '7.4')),
            'tested_wp'=>sanitize_text_field((string)($data['tested_wp'] ?? get_bloginfo('version'))),
            'notes'=>substr(sanitize_text_field((string)($data['notes'] ?? '')), 0, 1000),
        ];
    }

    private static function publishCatalogOnce(array &$record, array $package, array $options, array $health): array {
        self::verifiedBlob((array)$record['blob'], $record, (string)$record['channel']);
        self::saveStatus($record, 'catalog_unknown', 'Pedido ao catálogo iniciado; o resultado pode ficar indeterminado após timeout.');
        $published = ReleasePublisherClient::publishCatalog($record['blob'], $package, $record['channel'], $options);
        if (empty($published['success']) && empty($published['ok'])) {
            self::saveStatus($record, 'catalog_unknown', 'Catálogo sem confirmação. Reconciliar sem publicar novamente.');
            throw new \RuntimeException('Catálogo sem confirmação: ' . (string)($published['message'] ?? 'resposta inesperada') . '. Não será reenviado automaticamente.');
        }
        $remote = ReleasePublisherClient::findRelease(self::confirmedInspect($record['version'], $record['channel']), $record['version'], $record['channel']);
        if (!$remote) {
            self::saveStatus($record, 'catalog_unknown', 'Resposta OK, mas a leitura do catálogo ainda não confirmou a release.');
            throw new \RuntimeException('O catálogo respondeu OK, mas a release ainda não pode ser confirmada. Usa Verificar no catálogo.');
        }
        return self::completeFromRemote($record, $remote, $health);
    }

    private static function completeFromRemote(array &$record, array $remote, array $health): array {
        $remoteStatus = sanitize_key((string)($remote['status'] ?? ''));
        if (in_array($remoteStatus, ['draft','disabled','unpublished','revoked','withdrawn'], true)
            || (array_key_exists('published', $remote) && empty($remote['published']))) {
            throw new \RuntimeException('O catálogo devolveu uma release desativada ou ainda não publicada. Não será registada como publicada.');
        }
        $remoteChannel = sanitize_key((string)($remote['channel'] ?? ''));
        if ($remoteChannel !== '' && !hash_equals((string)$record['channel'], $remoteChannel)) {
            throw new \RuntimeException('O catálogo respondeu com um canal diferente do autorizado.');
        }
        $remoteSha = strtolower(sanitize_text_field((string)($remote['sha256'] ?? $remote['package_sha256'] ?? '')));
        $remoteBytes = (int)($remote['package_size'] ?? $remote['bytes'] ?? 0);
        if (!preg_match('/^[a-f0-9]{64}$/', $remoteSha) || !hash_equals($record['sha256'], $remoteSha)
            || ($remoteBytes > 0 && $remoteBytes !== (int)$record['bytes'])) {
            self::saveStatus($record, 'catalog_unknown', 'Identidade remota incompleta ou incompatível. Registo local bloqueado.');
            throw new \RuntimeException('O catálogo remoto não comprovou o mesmo SHA-256 e identidade do ZIP. A operação requer diagnóstico manual.');
        }
        self::saveStatus($record, 'catalog_confirmed', 'Release confirmada por consulta remota do catálogo e SHA-256.');
        $prior = ReleasePublicationReceipt::current($record['version']);
        $priorVersion = (string)($prior['version'] ?? '');
        $alreadyRecorded = $priorVersion === $record['version'];
        if ($alreadyRecorded && (!hash_equals($record['sha256'], (string)($prior['sha256'] ?? '')) || (string)($prior['channel'] ?? '') !== (string)$record['channel'])) {
            throw new \RuntimeException('Existe um recibo local conflituante para esta versão. Não será sobrescrito.');
        }
        if (!$alreadyRecorded) {
            $state = [
                'version'=>$record['version'], 'channel'=>$record['channel'], 'sha256'=>$record['sha256'],
                'bytes'=>$record['bytes'], 'release_id'=>sanitize_text_field((string)($remote['release_id'] ?? '')),
                'published_at'=>current_time('mysql', true), 'published_by'=>$record['user_id'],
                'publisher_service'=>sanitize_text_field((string)($health['service'] ?? 'fieldflow-release-publisher')),
            ];
            if (!ReleasePublicationReceipt::persist($state)) throw new \RuntimeException('Publicação remota confirmada, mas o recibo WordPress não pôde ser guardado. Usa Verificar no catálogo.');
            AuditLog::record('release.published', 'fieldflow_release', 0, null, $state,
                ['source'=>'backoffice_assisted_9db'], ['manifest_verified'=>true,'canonical_root'=>true]);
        }
        PrivatePluginUpdater::clearCache();
        delete_site_transient('update_plugins');
        if (function_exists('wp_clean_plugins_cache')) wp_clean_plugins_cache(true);
        if (function_exists('wp_update_plugins')) wp_update_plugins();
        PrivateUpdateLifecycle::resetHealthCache();
        self::saveStatus($record, 'completed', 'Catálogo e recibo local confirmados. Instalação e certificação continuam manuais.');
        self::archiveEvent($record, 'Publicação confirmada remotamente, sem instalar ou certificar automaticamente.');
        return ['ok'=>true,'message'=>'Release ' . $record['version'] . ' confirmada no catálogo ' . strtoupper($record['channel']) . ' e recibo WordPress persistido. Instala e certifica manualmente.'];
    }

    private static function revalidate(array $record): array {
        $path = self::verifiedPath($record);
        $installed = ProductionManifest::verify(true);
        if (empty($installed['ok'])) throw new \RuntimeException('A integridade da Stable instalada não foi comprovada. O assistente não irá publicar.');
        $result = ReleasePackageValidator::validate($path);
        $runtime = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $preflight = ReleasePreflight::evaluate($result, $runtime);
        if (empty($result['ok']) || empty($preflight['can_publish'])
            || !hash_equals((string)$record['sha256'], (string)($result['sha256'] ?? ''))
            || (int)$record['bytes'] !== (int)($result['bytes'] ?? -1)
            || !hash_equals((string)$record['version'], (string)($result['version'] ?? ''))) {
            throw new \RuntimeException('A release guardada deixou de cumprir a continuidade N+1, o Release Gate ou o SHA-256 original.');
        }
        return $result;
    }

    private static function verifiedBlob(array $blob, array $record, string $channel): array {
        $expected = 'fieldflow/' . $channel . '/' . $record['version'] . '/fieldflow-' . $record['version'] . '.zip';
        $name = sanitize_text_field((string)($blob['blob_name'] ?? ''));
        $container = sanitize_text_field((string)($blob['blob_container'] ?? 'fieldflow-private-releases'));
        if (!in_array($channel, ['stable','beta'], true) || !hash_equals($expected, $name)
            || !preg_match('/^[a-z0-9-]{3,63}$/', $container)) {
            throw new \RuntimeException('A evidência do Blob não corresponde ao pacote autorizado.');
        }
        return ['blob_name'=>$name,'blob_container'=>$container];
    }

    private static function lockedRecord(int $userId, string $token, bool $requireFile, bool $allowExpiredForRemoteCheck = false): array {
        $record = get_option(self::optionName($userId), []);
        if (!is_array($record) || !preg_match('/^[a-f0-9]{48}$/', $token)
            || !hash_equals((string)($record['token'] ?? ''), $token)
            || (int)($record['user_id'] ?? -1) !== $userId
            || (int)($record['blog_id'] ?? -1) !== get_current_blog_id()
            || (int)($record['created'] ?? 0) + self::JOURNAL_TTL_SECONDS <= time()
            || (!$allowExpiredForRemoteCheck && (int)($record['expires'] ?? 0) <= time())) {
            throw new \RuntimeException('Sessão de publicação indisponível, expirada ou sem permissões.');
        }
        if ($requireFile) self::verifiedPath($record);
        return $record;
    }

    private static function verifiedPath(array $record): string {
        $path = self::filePath($record);
        if ($path === '' || is_link($path) || !is_file($path) || !is_readable($path)
            || (int)filesize($path) !== (int)($record['bytes'] ?? -1)
            || !preg_match('/^[a-f0-9]{64}$/', (string)($record['sha256'] ?? ''))
            || !hash_equals((string)$record['sha256'], (string)hash_file('sha256', $path))) {
            throw new \RuntimeException('ZIP privado indisponível ou alterado. Por segurança não será publicado.');
        }
        return $path;
    }

    private static function filePath(array $record): string {
        $name = (string)($record['filename'] ?? '');
        if (!preg_match('/^[a-f0-9]{48}\.zip$/', $name)) return '';
        return self::privateDirectory() . '/' . $name;
    }

    private static function privateDirectory(): string {
        $temp = realpath(sys_get_temp_dir());
        if ($temp === false || !is_dir($temp) || !is_writable($temp)) throw new \RuntimeException('Pasta temporária privada indisponível. Usa a publicação manual.');
        // Refuse temporary directories inside any web-served tree, even when .htaccess is available.
        foreach ([ABSPATH, defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : '', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')] as $public) {
            $real = $public ? realpath($public) : false;
            if ($real !== false && ($temp === $real || strpos($temp . '/', rtrim($real, '/') . '/') === 0)) {
                throw new \RuntimeException('A pasta temporária pode estar acessível pela web. Armazenamento assistido bloqueado, modo manual disponível.');
            }
        }
        $suffix = substr(hash_hmac('sha256', (string)realpath(ROUTESPRO_PATH), wp_salt('auth')), 0, 24);
        $dir = $temp . '/fieldflow-release-9db-' . $suffix;
        if (is_link($dir)) throw new \RuntimeException('Pasta temporária insegura.');
        if (!is_dir($dir) && !@mkdir($dir, 0700, false) && !is_dir($dir)) throw new \RuntimeException('Não foi possível criar uma pasta temporária privada.');
        @chmod($dir, 0700);
        if (is_link($dir) || (fileperms($dir) & 0077) !== 0 || !is_writable($dir)) throw new \RuntimeException('A pasta temporária não tem permissões privadas.');
        return $dir;
    }

    private static function optionName(int $userId): string { return self::OPTION_PREFIX . $userId; }
    private static function requireOperator(int $userId): void {
        if ($userId <= 0 || get_current_user_id() !== $userId || !current_user_can('routespro_manage')) {
            throw new \RuntimeException('A operação requer o administrador autenticado com permissões FieldFlow.');
        }
    }
    private static function saveStatus(array &$record, string $status, string $message): void {
        $record['status'] = $status;
        $record['updated'] = time();
        self::event($record, $message);
        self::save($record);
    }
    private static function event(array &$record, string $message): void {
        $record['events'][] = ['at'=>gmdate('Y-m-d H:i:s'),'status'=>(string)$record['status'],'message'=>sanitize_text_field($message)];
        $record['events'] = array_slice($record['events'], -self::EVENT_LIMIT);
    }
    private static function save(array $record): void {
        update_option(self::optionName((int)$record['user_id']), $record, false);
        $stored = get_option(self::optionName((int)$record['user_id']), []);
        if (!is_array($stored) || !hash_equals((string)$record['token'], (string)($stored['token'] ?? ''))
            || (string)($stored['status'] ?? '') !== (string)$record['status']) {
            throw new \RuntimeException('Não foi possível persistir o estado de publicação. Nenhuma repetição remota será efetuada automaticamente.');
        }
    }
    private static function archiveEvent(array $record, string $message): void {
        $row = [
            'version'=>(string)$record['version'], 'channel'=>(string)$record['channel'],
            'sha256'=>(string)$record['sha256'], 'status'=>(string)$record['status'],
            'at'=>gmdate('Y-m-d H:i:s'), 'message'=>sanitize_text_field($message),
            'user_id'=>(int)$record['user_id'],
        ];
        $previous = get_option(self::HISTORY_OPTION, []);
        if (!is_array($previous)) $previous = [];
        update_option(self::HISTORY_OPTION, array_slice(array_merge([$row], $previous), 0, 10), false);
    }
    private static function unlinkPrevious(array $record, string $dir): void {
        $name = (string)($record['filename'] ?? '');
        if (preg_match('/^[a-f0-9]{48}\.zip$/', $name)) {
            $path = $dir . '/' . $name;
            if (is_file($path) && !is_link($path)) @unlink($path);
        }
    }
    private static function acquireLock(string $key) {
        $dir = self::privateDirectory();
        $name = $dir . '/lock-' . hash('sha256', $key) . '.lck';
        $handle = @fopen($name, 'c');
        if (!is_resource($handle)) throw new \RuntimeException('Não foi possível bloquear a operação.');
        @chmod($name, 0600);
        if (!flock($handle, LOCK_EX | LOCK_NB)) { fclose($handle); throw new \RuntimeException('Outra operação de publicação está em curso. Não dupliques o envio.'); }
        return $handle;
    }
    private static function unlock($handle): void { if (is_resource($handle)) { flock($handle, LOCK_UN); fclose($handle); } }
}
