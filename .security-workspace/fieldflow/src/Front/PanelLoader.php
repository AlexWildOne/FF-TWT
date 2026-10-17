<?php
namespace RoutesPro\Front;

if (!defined('ABSPATH')) exit;

/**
 * Carregamento progressivo dos painéis pesados dos portais FieldFlow.
 *
 * Mantém os shortcodes existentes como fonte de verdade. A única diferença é
 * temporal: o HTML do módulo é produzido quando o utilizador abre o separador,
 * em vez de ser produzido todo no primeiro pedido.
 */
class PanelLoader {
    private const ACTION = 'fieldflow_load_panel';

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION, [self::class, 'handle']);
    }

    public static function placeholder(string $portal, string $panel, array $context = []): string {
        $portal = sanitize_key($portal);
        $panel = sanitize_key($panel);
        $clientId = absint($context['client_id'] ?? 0);
        $projectId = absint($context['project_id'] ?? 0);
        $messageId = absint($context['message_id'] ?? 0);

        return sprintf(
            '<div class="ff-lazy-panel" data-ff-lazy-panel="1" data-ff-portal="%1$s" data-ff-panel="%2$s" data-client-id="%3$d" data-project-id="%4$d" data-message-id="%5$d" data-endpoint="%6$s" data-nonce="%7$s" aria-live="polite"><div class="ff-skeleton" aria-hidden="true" hidden><span></span><span></span><span></span><span></span></div><p class="ff-lazy-label" hidden>%8$s</p><noscript><p>%9$s</p></noscript></div>',
            esc_attr($portal),
            esc_attr($panel),
            $clientId,
            $projectId,
            $messageId,
            esc_url(admin_url('admin-ajax.php')),
            esc_attr(wp_create_nonce('fieldflow_lazy_panel')),
            esc_html__('O módulo será carregado quando abrires este separador.', 'routes-pro'),
            esc_html__('Ativa JavaScript para abrir este módulo.', 'routes-pro')
        );
    }

    public static function handle(): void {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('A sessão terminou. Inicia sessão novamente.', 'routes-pro')], 401);
        }

        $nonce = sanitize_text_field((string)($_POST['nonce'] ?? ''));
        if (!$nonce || !wp_verify_nonce($nonce, 'fieldflow_lazy_panel')) {
            wp_send_json_error(['message' => __('Não foi possível validar o pedido.', 'routes-pro')], 403);
        }

        $guard = \RoutesPro\Shortcodes::front_guard();
        if ($guard) {
            wp_send_json_error(['message' => wp_strip_all_tags($guard)], 403);
        }

        $portal = sanitize_key((string)($_POST['portal'] ?? ''));
        $panel = sanitize_key((string)($_POST['panel'] ?? ''));
        $context = [
            'client_id' => absint($_POST['client_id'] ?? 0),
            'project_id' => absint($_POST['project_id'] ?? 0),
            'message_id' => absint($_POST['message_id'] ?? 0),
        ];

        if (!in_array($portal, ['app', 'client'], true)) {
            wp_send_json_error(['message' => __('Portal inválido.', 'routes-pro')], 400);
        }

        if (!self::scopeAllowed($context)) {
            wp_send_json_error(['message' => __('Sem acesso ao âmbito solicitado.', 'routes-pro')], 403);
        }

        $shortcode = self::shortcodeFor($portal, $panel, $context);
        if ($shortcode === '') {
            wp_send_json_error(['message' => __('Módulo desconhecido.', 'routes-pro')], 404);
        }

        $started = microtime(true);
        $queriesBefore = isset($GLOBALS['wpdb']->num_queries) ? (int)$GLOBALS['wpdb']->num_queries : 0;
        $html = do_shortcode($shortcode);
        $queriesAfter = isset($GLOBALS['wpdb']->num_queries) ? (int)$GLOBALS['wpdb']->num_queries : $queriesBefore;

        nocache_headers();
        wp_send_json_success([
            'html' => (string)$html,
            'panel' => $panel,
            'portal' => $portal,
            'performance' => [
                'duration_ms' => (int)round((microtime(true) - $started) * 1000),
                'queries' => max(0, $queriesAfter - $queriesBefore),
            ],
        ]);
    }

    private static function scopeAllowed(array $context): bool {
        if (current_user_can('routespro_manage')) return true;
        $clientId = absint($context['client_id'] ?? 0);
        $projectId = absint($context['project_id'] ?? 0);
        if ($clientId && !\RoutesPro\Support\Permissions::is_allowed_client($clientId)) return false;
        if ($projectId && !\RoutesPro\Support\Permissions::is_allowed_project($projectId)) return false;
        return \RoutesPro\Support\Permissions::can_access_front();
    }

    private static function shortcodeFor(string $portal, string $panel, array $context): string {
        $clientId = absint($context['client_id'] ?? 0);
        $projectId = absint($context['project_id'] ?? 0);

        if ($portal === 'app') {
            $map = [
                'briefings' => sprintf('[fieldflow_briefings client_id="%d" project_id="%d"]', $clientId, $projectId),
                'implementations' => sprintf('[fieldflow_implementations client_id="%d" project_id="%d"]', $clientId, $projectId),
                'stocks' => sprintf('[fieldflow_stocks client_id="%d" project_id="%d"]', $clientId, $projectId),
                'shopping' => sprintf('[fieldflow_shopping client_id="%d" project_id="%d"]', $clientId, $projectId),
                'discovery' => '[fieldflow_discovery]',
                'report' => '[fieldflow_report_visit mode="app"]',
                'academy' => sprintf('[fieldflow_academy client_id="%d" project_id="%d"]', $clientId, $projectId),
                'expenses' => '[fieldflow_expenses]',
                'messages' => sprintf('[fieldflow_team_inbox client_id="%d" project_id="%d"]', $clientId, $projectId),
                'analytics' => sprintf('[fieldflow_dashboard client_id="%d" project_id="%d" mode="self" title="O meu desempenho"]', $clientId, $projectId),
            ];
            if ($panel === 'analytics' && !current_user_can('routespro_manage') && !\RoutesPro\Support\Permissions::can_access_front()) return '';
            return $map[$panel] ?? '';
        }

        $map = [
            'commercial' => sprintf('[fieldflow_front_commercial client_id="%d" project_id="%d"]', $clientId, $projectId),
            'briefings' => sprintf('[fieldflow_client_briefings client_id="%d" project_id="%d"]', $clientId, $projectId),
            'implementations' => sprintf('[fieldflow_client_implementations client_id="%d" project_id="%d"]', $clientId, $projectId),
            'stocks' => sprintf('[fieldflow_client_stocks client_id="%d" project_id="%d"]', $clientId, $projectId),
            'team' => sprintf('[fieldflow_client_team_mail client_id="%d" project_id="%d"]', $clientId, $projectId),
            'performance' => sprintf('[fieldflow_performance_dashboard client_id="%d" project_id="%d" title="Growth Hub da Campanha"]', $clientId, $projectId),
        ];
        return $map[$panel] ?? '';
    }
}
