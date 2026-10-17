<?php
namespace RoutesPro\Support;

if (!defined('ABSPATH')) exit;

/**
 * 9DA: read-only explanation of a release validation. Never authorises a release.
 * ReleasePackageValidator, ReleaseGate, Azure and Phase 6 remain authorities.
 */
final class ReleasePreflight {
    public const CONTRACT = 'fieldflow-release-manager-assisted-preflight-9da-v1';
    private const DISPLAY_LIMIT = 35;

    /** Pure transformation: no ZIP extraction, WordPress writes or external calls. */
    public static function evaluate(array $package, string $runtime, array $installedManifest = [], bool $installedIntegrityOk = true): array {
        $valid = !empty($package['ok']);
        $candidate = (string)($package['version'] ?? '');
        $expected = self::nextPatch($runtime);
        $sequential = $valid && $expected !== '' && hash_equals($candidate, $expected);
        $result = [
            'contract'=>self::CONTRACT,
            'validated'=>$valid,
            'can_publish'=>$valid && $sequential,
            'runtime'=>$runtime,
            'candidate'=>$candidate,
            'expected_next'=>$expected,
            'sha256'=>(string)($package['sha256'] ?? ''),
            'bytes'=>(int)($package['bytes'] ?? 0),
            'counts'=>['added'=>0,'modified'=>0,'removed'=>0,'unchanged'=>0],
            'diff_available'=>false,
            'changed'=>[],
            'warnings'=>[],
            'diagnostics'=>[],
            'checks'=>[],
        ];

        self::addCheck($result, 'package', 'Validação integral do ZIP', $valid,
            $valid ? 'Integridade, versão e contratos do pacote aprovados.' : (string)($package['error'] ?? 'Pacote inválido.'),
            'Corrige a condição indicada e volta a carregar o ZIP. Nenhuma publicação foi iniciada.');
        if (!$valid) return $result;

        self::addCheck($result, 'succession', 'Sucessão exata da versão instalada', $sequential,
            $sequential ? 'Versão seguinte à baseline instalada.' :
                'Versão instalada: ' . $runtime . '. Esperada: ' . ($expected ?: 'não identificada') . '. Recebida: ' . $candidate . '.',
            'Prepara uma release N+1 sobre a Stable instalada. Não saltes versões nem publiques uma versão anterior.');

        $hash = (string)($package['sha256'] ?? '');
        self::addCheck($result, 'sha256', 'Identificação SHA-256 do ZIP', (bool)preg_match('/^[a-f0-9]{64}$/', $hash),
            'O pacote está identificado por um hash SHA-256 completo.',
            'Regenera o pacote e valida novamente a sua integridade.');
        $result['can_publish'] = $result['can_publish'] && (bool)preg_match('/^[a-f0-9]{64}$/', $hash);

        $installedVersion = (string)($installedManifest['version'] ?? '');
        if (!$installedIntegrityOk || ($installedVersion !== '' && !hash_equals($runtime, $installedVersion))) {
            $result['warnings'][] = 'A integridade da instalação ou a versão do manifesto instalado não foi confirmada. Confirma a baseline antes de publicar.';
        }

        $candidateFiles = self::fileMap((array)($package['manifest_file_metadata'] ?? []));
        $installedFiles = self::fileMap((array)($installedManifest['files'] ?? []));
        if (!$candidateFiles || !$installedFiles) {
            $result['warnings'][] = 'Comparação de ficheiros indisponível: falta inventário de manifesto verificado. A validação do ZIP continua obrigatória.';
            return $result;
        }
        $added = array_diff_key($candidateFiles, $installedFiles);
        $removed = array_diff_key($installedFiles, $candidateFiles);
        $modified = [];
        $unchanged = 0;
        foreach (array_intersect_key($candidateFiles, $installedFiles) as $path => $meta) {
            if (hash_equals((string)$meta['sha256'], (string)$installedFiles[$path]['sha256']) && $meta['bytes'] === $installedFiles[$path]['bytes']) {
                $unchanged++;
            } else {
                $modified[$path] = $meta;
            }
        }
        $result['counts'] = [
            'added'=>count($added), 'modified'=>count($modified),
            'removed'=>count($removed), 'unchanged'=>$unchanged,
        ];
        $result['diff_available'] = true;
        $changes = [];
        foreach (['modified'=>$modified, 'removed'=>$removed, 'added'=>$added] as $kind => $items) {
            foreach (array_keys($items) as $path) {
                $changes[] = ['path'=>$path,'type'=>$kind,'sensitive'=>self::isSensitive($path)];
            }
        }
        usort($changes, static function(array $left, array $right): int {
            return (int)$right['sensitive'] <=> (int)$left['sensitive'] ?: strcmp($left['path'], $right['path']);
        });
        $result['changed'] = array_slice($changes, 0, self::DISPLAY_LIMIT);
        $result['omitted_changes'] = max(0, count($changes) - count($result['changed']));
        if ($removed) {
            $result['warnings'][] = 'Existem ficheiros removidos. Confirma se são apenas bundles de versões anteriores ou se existe impacto funcional.';
        }
        if (array_filter($changes, static function(array $item): bool { return !empty($item['sensitive']); })) {
            $result['warnings'][] = 'Foram alterados ficheiros sensíveis de release, segurança ou distribuição. Exige revisão técnica antes de publicar.';
        }
        return $result;
    }

    private static function addCheck(array &$report, string $id, string $label, bool $ok, string $detail, string $action): void {
        $report['checks'][] = ['id'=>$id,'label'=>$label,'ok'=>$ok,'detail'=>$detail];
        if (!$ok) $report['diagnostics'][] = [
            'code'=>strtoupper($id), 'severity'=>'blocker', 'title'=>$label,
            'detail'=>$detail,'action'=>$action,
        ];
    }

    private static function fileMap(array $raw): array {
        $map = [];
        foreach ($raw as $key => $value) {
            if (!is_array($value)) continue;
            $path = is_string($key) ? $key : (string)($value['path'] ?? '');
            $path = str_replace('\\', '/', $path);
            if ($path === '' || $path === 'production-manifest.json' || strpos($path, '/') === 0 || strpos($path, '..') !== false || strpos($path, "\0") !== false) continue;
            $sha = strtolower((string)($value['sha256'] ?? ''));
            $bytes = (int)($value['bytes'] ?? -1);
            if (!preg_match('/^[a-f0-9]{64}$/', $sha) || $bytes < 0) continue;
            $map[$path] = ['sha256'=>$sha,'bytes'=>$bytes];
        }
        return $map;
    }

    private static function isSensitive(string $path): bool {
        foreach (['src/Support/Release', 'src/Cloud/', 'src/Security/', 'src/Support/License', 'src/Support/ProductionManifest.php', 'uninstall.php', 'fieldflow.php'] as $prefix) {
            if (strpos($path, $prefix) === 0) return true;
        }
        return false;
    }

    private static function nextPatch(string $version): string {
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $m)) return '';
        return (int)$m[1] . '.' . (int)$m[2] . '.' . ((int)$m[3] + 1);
    }
}
