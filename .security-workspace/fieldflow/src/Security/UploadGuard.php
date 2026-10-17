<?php
namespace RoutesPro\Security;

if (!defined('ABSPATH')) exit;

class UploadGuard {
    public const IMAGE_SIZE_POLICY = 'no-fixed-image-limit';
    private const DANGEROUS = ['php','phtml','php3','php4','php5','phar','cgi','pl','py','sh','bash','exe','dll','com','bat','cmd','js','html','htm','svg'];

    public static function registerHooks(): void {
        // Never attach FieldFlow media policy to unrelated WordPress/plugin uploads.
        // The request scope is already known when WordPress loads plugins.
        if (!self::isFieldFlowRequest()) return;
        add_filter('wp_handle_upload_prefilter', [self::class, 'filterUpload']);
        add_filter('wp_check_filetype_and_ext', [self::class, 'verifyFiletype'], 20, 5);
        add_filter('upload_mimes', [self::class, 'extendImageMimes']);
    }

    public static function filterUpload(array $file): array {
        $name = sanitize_file_name((string)($file['name'] ?? ''));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!self::isFieldFlowRequest()) return $file;
        if (in_array($ext, self::DANGEROUS, true)) {
            $file['error'] = 'Este tipo de ficheiro não é permitido pelo FieldFlow.';
            SecurityService::record('upload.blocked_extension', 'high', ['extension' => $ext, 'name_hash' => hash('sha256', $name)]);
            return $file;
        }
        $limit=(int)Settings::get()['rate_limits']['upload_per_minute'];
        $rate=RateLimiter::enforce('upload', $limit, 60);
        if(is_wp_error($rate)){ $file['error']=$rate->get_error_message(); return $file; }

        $allowed = Settings::allowedExtensions();
        if ($ext === '' || !in_array($ext, $allowed, true)) {
            $file['error'] = 'O formato do ficheiro não está autorizado na configuração do FieldFlow.';
            SecurityService::record('upload.disallowed_type', 'medium', ['extension' => $ext]);
            return $file;
        }
        $size = absint($file['size'] ?? 0);
        $isImage = in_array($ext, ['jpg','jpeg','png','webp','heic','heif'], true);
        // 2.2.461: FieldFlow evidence photos have no plugin-level fixed MB cap.
        // The normal form runtime uses authenticated resumable chunks, so PHP's
        // per-request upload ceiling is not a meaningful media-size policy. Keep
        // the configured document ceiling because documents still use the normal
        // upload pipeline and have a materially different attack surface.
        if (!$isImage) {
            $limitMb = (int)Settings::get()['uploads']['max_document_mb'];
            if ($size > $limitMb * MB_IN_BYTES) {
                $file['error'] = sprintf('O ficheiro ultrapassa o limite de %d MB.', $limitMb);
                SecurityService::record('upload.size_exceeded', 'medium', ['extension' => $ext, 'size' => $size, 'limit_mb' => $limitMb]);
                return $file;
            }
        }

        // 2.2.302: validate ZIP-based documents before WordPress stores them.
        // This is intentionally conservative and does not rewrite, extract or migrate files.
        if (in_array($ext, ['zip','docx','xlsx'], true)) {
            $archiveError = self::validateArchiveContainer((string)($file['tmp_name'] ?? ''), $ext);
            if ($archiveError !== '') {
                $file['error'] = $archiveError;
                SecurityService::record('upload.archive_rejected', 'high', [
                    'extension' => $ext,
                    'name_hash' => hash('sha256', $name),
                    'reason_hash' => hash('sha256', $archiveError),
                ]);
                return $file;
            }
        }

        // 2.2.417: optional hash-lookup malware check (off by default; see
        // UploadScanner). Scoped to documents/archives only — never images.
        // Images cannot execute and are the overwhelming majority of field
        // uploads by volume; scanning every photo synchronously added
        // several seconds per submission (one HTTP round-trip per file).
        // Documents/archives are lower-volume and higher-risk, the same
        // set already validated more deeply above. Fail-open on anything
        // other than a confirmed known-malware match — never blocks an
        // upload on scanner error, timeout or an unindexed hash.
        $scannableExt = ['pdf','csv','xlsx','xls','docx','zip'];
        if (in_array($ext, $scannableExt, true) && class_exists(UploadScanner::class) && UploadScanner::isEnabled()) {
            $scan = UploadScanner::scanFile((string)($file['tmp_name'] ?? ''));
            if (($scan['status'] ?? '') === 'malicious') {
                $file['error'] = 'Este ficheiro foi identificado como malicioso e não foi aceite.';
                SecurityService::record('upload.malware_blocked', 'critical', [
                    'extension' => $ext,
                    'name_hash' => hash('sha256', $name),
                    'detections' => (int)($scan['detections'] ?? 0),
                    'total_engines' => (int)($scan['total_engines'] ?? 0),
                ]);
                return $file;
            }
        }
        return $file;
    }

    public static function verifyFiletype(array $data, string $file, string $filename, array $mimes, $realMime = null): array {
        if (!self::isFieldFlowRequest()) return $data;
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') return $data;
        $allowedMimeByExt = [
            'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'],
            'heic' => ['image/heic','image/heif','image/heic-sequence','image/heif-sequence','image/x-heic','image/x-heif'],
            'heif' => ['image/heif','image/heic','image/heif-sequence','image/heic-sequence','image/x-heif','image/x-heic'],
            'pdf' => ['application/pdf'], 'csv' => ['text/plain','text/csv','application/csv','application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip'],
            'xls' => ['application/vnd.ms-excel','application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip'],
            'zip' => ['application/zip','application/x-zip-compressed','application/octet-stream'],
        ];
        $detected = strtolower((string)($realMime ?: ($data['type'] ?? '')));
        if (in_array($ext, ['heic','heif'], true)
            && ($detected === '' || $detected === 'application/octet-stream')
            && self::isHeifContainer($file)) {
            $detected = $ext === 'heic' ? 'image/heic' : 'image/heif';
        }
        if (isset($allowedMimeByExt[$ext]) && $detected !== '' && !in_array($detected, $allowedMimeByExt[$ext], true)) {
            SecurityService::record('upload.mime_mismatch', 'high', ['extension' => $ext, 'detected_mime' => $detected]);
            return ['ext' => false, 'type' => false, 'proper_filename' => false];
        }
        if (in_array($ext, ['heic','heif'], true) && in_array($detected, $allowedMimeByExt[$ext], true)) {
            $data['ext'] = $ext;
            $data['type'] = $detected;
            $data['proper_filename'] = false;
        }
        return $data;
    }

    private static function validateArchiveContainer(string $file, string $ext): string {
        if ($file === '' || !is_file($file) || !is_readable($file)) return 'Não foi possível validar o ficheiro comprimido.';
        if (!class_exists('ZipArchive')) {
            // Preserve compatibility on hosts without ZipArchive. MIME and extension checks still apply.
            return '';
        }

        $zip = new \ZipArchive();
        $flags = defined('ZipArchive::CHECKCONS') ? \ZipArchive::CHECKCONS : 0;
        $opened = $zip->open($file, $flags);
        if ($opened !== true) return 'O ficheiro comprimido está danificado ou não é válido.';

        $entries = (int)$zip->numFiles;
        if ($entries < 1 || $entries > 5000) { $zip->close(); return 'O ficheiro comprimido contém uma estrutura inválida ou excessiva.'; }

        $totalUncompressed = 0;
        $maxUncompressed = 256 * MB_IN_BYTES;
        $names = [];
        for ($i = 0; $i < $entries; $i++) {
            $stat = $zip->statIndex($i);
            if (!is_array($stat)) { $zip->close(); return 'Não foi possível validar a estrutura do ficheiro comprimido.'; }
            $entryName = str_replace('\\', '/', (string)($stat['name'] ?? ''));
            $names[$entryName] = true;
            if ($entryName === '' || strpos($entryName, "\0") !== false || preg_match('#(^|/)\.\.(/|$)#', $entryName) || preg_match('#^[A-Za-z]:/#', $entryName) || strpos($entryName, '/') === 0) {
                $zip->close(); return 'O ficheiro comprimido contém caminhos não autorizados.';
            }
            $usize = max(0, (int)($stat['size'] ?? 0));
            $csize = max(0, (int)($stat['comp_size'] ?? 0));
            $totalUncompressed += $usize;
            if ($totalUncompressed > $maxUncompressed) { $zip->close(); return 'O conteúdo descomprimido do ficheiro excede o limite de segurança.'; }
            if ($usize > 10 * MB_IN_BYTES && $csize > 0 && ($usize / $csize) > 200) { $zip->close(); return 'O ficheiro comprimido apresenta uma taxa de compressão não segura.'; }
        }

        if ($ext === 'xlsx') {
            foreach (['[Content_Types].xml','xl/workbook.xml'] as $required) {
                if (!isset($names[$required])) { $zip->close(); return 'O ficheiro XLSX não contém uma estrutura Excel válida.'; }
            }
        } elseif ($ext === 'docx') {
            foreach (['[Content_Types].xml','word/document.xml'] as $required) {
                if (!isset($names[$required])) { $zip->close(); return 'O ficheiro DOCX não contém uma estrutura Word válida.'; }
            }
        }
        $zip->close();
        return '';
    }

    private static function isHeifContainer(string $file): bool {
        if ($file === '' || !is_readable($file)) return false;
        $header = @file_get_contents($file, false, null, 0, 32);
        if (!is_string($header) || strlen($header) < 12 || substr($header, 4, 4) !== 'ftyp') return false;
        $brands = ['heic','heix','hevc','hevx','heim','heis','hevm','hevs','mif1','msf1'];
        $major = strtolower(substr($header, 8, 4));
        if (in_array($major, $brands, true)) return true;
        for ($offset = 16; $offset + 4 <= strlen($header); $offset += 4) {
            if (in_array(strtolower(substr($header, $offset, 4)), $brands, true)) return true;
        }
        return false;
    }

    public static function extendImageMimes(array $mimes): array {
        if (!self::isFieldFlowRequest()) return $mimes;
        $mimes['heic'] = 'image/heic';
        $mimes['heif'] = 'image/heif';
        return $mimes;
    }

    private static function isFieldFlowRequest(): bool {
        if (class_exists('RoutesPro\\Support\\Plugin', false)
            && \RoutesPro\Support\Plugin::isWordPressMediaProcessingRequest()) return false;

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if (strpos($uri, '/fieldflow') !== false || strpos($uri, '/routespro') !== false) return true;

        $actionRaw = $_REQUEST['action'] ?? '';
        $action = is_scalar($actionRaw) ? sanitize_key((string)$actionRaw) : '';
        if (strpos($action, 'fieldflow') !== false || strpos($action, 'routespro') !== false) return true;

        $pageRaw = $_REQUEST['page'] ?? '';
        $page = is_scalar($pageRaw) ? sanitize_key((string)$pageRaw) : '';
        if (strpos($page, 'fieldflow') === 0 || strpos($page, 'routespro') === 0) return true;

        // Avoid the generic `form_id` marker. Elementor and other plugins use it
        // too, which could incorrectly opt their uploads into FieldFlow policy.
        foreach (['routespro_form_id','route_stop_id','fieldflow_offline_replay_token'] as $key) {
            if (isset($_REQUEST[$key])) return true;
        }
        return false;
    }
}
