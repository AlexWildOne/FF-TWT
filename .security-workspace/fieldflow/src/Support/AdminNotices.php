<?php
namespace RoutesPro\Support;

if (!defined('ABSPATH')) exit;

class AdminNotices {
    public static function register(): void {
        add_action('admin_notices', [self::class, 'render']);
    }

    public static function render(): void {
        if (!current_user_can('routespro_manage')) return;
        if (!is_admin()) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_id = $screen && !empty($screen->id) ? (string) $screen->id : '';
        if ($screen_id !== '' && strpos($screen_id, 'routespro') === false && strpos($screen_id, 'toplevel_page_routespro') === false) {
            return;
        }

        $messages = [];
        if (LicenseManager::isRemoteMode() && !LicenseManager::remoteReady()) {
            $messages[] = ['error', 'Modo remoto ativo, mas a API Azure de licenciamento não está configurada.'];
        } elseif (!LicenseManager::isActive()) {
            $messages[] = ['warning', 'Licença por ativar. O produto continua funcional, mas a distribuição comercial deve sair com licença validada.'];
        } elseif (!LicenseManager::isBoundToCurrentDomain()) {
            $messages[] = ['error', 'A licença ativa não coincide com o domínio atual. Revê a ativação antes de distribuir esta instalação.'];
        }

        $remoteError = (string) LicenseManager::get('remote_last_error', '');
        if ($remoteError !== '' && !LicenseManager::isActive()) {
            $messages[] = ['warning', 'Licenciamento remoto: ' . $remoteError];
        }

        $migrationError = get_option('fieldflow_last_schema_migration_error', []);
        if (is_array($migrationError) && !empty($migrationError['message'])) {
            $messages[] = ['error', 'Migração de schema pendente na versão ' . (string)($migrationError['version'] ?? 'desconhecida') . ': ' . (string)$migrationError['message'] . ' Abre FieldFlow > Saúde do sistema > Schema e releases.'];
        }

        // Do not execute the full SystemHealth graph on every FieldFlow admin page.
        // It traverses schema, queues, cloud phases and release integrity and became
        // increasingly expensive as 6B added nested certification layers. Routine
        // notices use only the last explicit Release Gate result. Full diagnostics
        // remain available on FieldFlow > Saúde do sistema and certification actions.
        if (class_exists(ReleaseGate::class)) {
            $release = ReleaseGate::latest();
            $releaseVersion = sanitize_text_field((string)($release['plugin_version'] ?? ''));
            if ($releaseVersion !== '' && defined('FIELDFLOW_VERSION') && hash_equals((string)FIELDFLOW_VERSION, $releaseVersion) && (string)($release['status'] ?? '') === 'blocked') {
                $messages[] = ['error', 'O último Release Gate desta versão está bloqueado. Abre FieldFlow > Saúde do sistema para ver o diagnóstico completo.'];
            }
        }

        $aiProvider = Config::aiProviderForModule('general');
        if (!empty(Config::get('ai_enabled', 1)) && $aiProvider !== 'none' && !Config::aiProviderReady($aiProvider)) {
            $messages[] = ['warning', 'O motor principal de IA está selecionado, mas tem credenciais incompletas. Revê FieldFlow > Inteligência Artificial.'];
        }

        foreach ($messages as [$type, $message]) {
            printf('<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr($type), esc_html($message));
        }
    }
}
