<?php
namespace RoutesPro\Admin;

use RoutesPro\Services\RouteOptimizationReconciliationService;
use RoutesPro\Support\BatchMigrationManager;

if (!defined('ABSPATH')) exit;

/** Existing Process Center panel, no new menu. */
final class RouteOptimizationReconciliation {
    public static function registerHooks(): void {
        add_action('admin_post_fieldflow_route_reconciliation_start', [self::class, 'handleStart']);
    }

    public static function handleStart(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_route_reconciliation_start');

        $requestedMode = sanitize_key((string)($_POST['reconciliation_mode'] ?? 'preview'));
        $mode = $requestedMode === 'apply' && !empty($_POST['confirm_apply']) ? 'apply' : 'preview';
        $routingMode = sanitize_key((string)($_POST['routing_mode'] ?? 'hybrid'));
        if (!in_array($routingMode, ['hybrid', 'internal'], true)) $routingMode = 'hybrid';
        $meta = [
            'mode' => $mode,
            'routing_mode' => $routingMode,
            'client_id' => absint($_POST['client_id'] ?? 0),
            'project_id' => absint($_POST['project_id'] ?? 0),
            'owner_user_id' => absint($_POST['owner_user_id'] ?? 0),
            'date_from' => self::date((string)($_POST['date_from'] ?? '')),
            'date_to' => self::date((string)($_POST['date_to'] ?? '')),
        ];
        $total = RouteOptimizationReconciliationService::countEligible($meta);
        if ($total <= 0) self::redirect('route_reconciliation_empty', 'Não foram encontradas rotas manuais elegíveis para os filtros selecionados.');

        $result = BatchMigrationManager::start('route_optimization_reconciliation', get_current_user_id(), 5, $total, $meta);
        if (empty($result['ok'])) self::redirect('error', 'Não foi possível iniciar a reconciliação de rotas.');
        self::redirect('route_reconciliation_started', ($mode === 'apply' ? 'Aplicação' : 'Simulação') . ' iniciada para ' . $total . ' rota(s) elegível(eis).');
    }

    public static function renderProcessPanel(): void {
        if (!current_user_can('routespro_manage')) return;
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $clients = $wpdb->get_results("SELECT id,name FROM {$px}clients ORDER BY name ASC", ARRAY_A) ?: [];
        $projects = $wpdb->get_results("SELECT id,client_id,name FROM {$px}projects ORDER BY name ASC", ARRAY_A) ?: [];
        $users = get_users(['fields'=>['ID','display_name'], 'orderby'=>'display_name', 'order'=>'ASC']);
        $recent = array_values(array_filter(BatchMigrationManager::recent(30), static function(array $row): bool {
            return (string)($row['task_key'] ?? '') === 'route_optimization_reconciliation';
        }));
        $last = $recent[0] ?? [];
        $aggregate = self::aggregate($last);
        ?>
        <div class="ffpc-card ff-route-reconciliation">
          <div class="ffpc-card-head">
            <div>
              <span class="ffpc-kicker">Rotas manuais</span>
              <h2>Reconciliação e otimização</h2>
              <p>Simula ou aplica uma sequência mais eficiente apenas em rotas criadas no frontend, ainda em rascunho ou planeadas e sem visitas iniciadas.</p>
            </div>
          </div>
          <div class="ffrr-grid">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ffrr-form">
              <input type="hidden" name="action" value="fieldflow_route_reconciliation_start">
              <?php wp_nonce_field('fieldflow_route_reconciliation_start'); ?>
              <label>Cliente<select name="client_id"><option value="0">Todos</option><?php foreach ($clients as $row): ?><option value="<?php echo (int)$row['id']; ?>"><?php echo esc_html((string)$row['name']); ?></option><?php endforeach; ?></select></label>
              <label>Campanha<select name="project_id"><option value="0">Todas</option><?php foreach ($projects as $row): ?><option value="<?php echo (int)$row['id']; ?>" data-client="<?php echo (int)$row['client_id']; ?>"><?php echo esc_html((string)$row['name']); ?></option><?php endforeach; ?></select></label>
              <label>Utilizador<select name="owner_user_id"><option value="0">Todos</option><?php foreach ($users as $user): ?><option value="<?php echo (int)$user->ID; ?>"><?php echo esc_html((string)$user->display_name); ?></option><?php endforeach; ?></select></label>
              <label>Data inicial<input type="date" name="date_from"></label>
              <label>Data final<input type="date" name="date_to"></label>
              <label>Cálculo<select name="routing_mode"><option value="hybrid">Google Routes + estimador interno</option><option value="internal">Estimador interno</option></select></label>
              <label>Operação<select name="reconciliation_mode"><option value="preview">Simular, não altera rotas</option><option value="apply">Aplicar sequência otimizada</option></select></label>
              <label class="ffrr-confirm"><input type="checkbox" name="confirm_apply" value="1"><span>Confirmo a aplicação apenas nas rotas elegíveis</span></label>
              <div><button class="button button-primary">Iniciar em lotes</button></div>
            </form>
            <div class="ffrr-summary">
              <h3>Última execução</h3>
              <?php if (!$last): ?>
                <p class="ffpc-muted">Ainda não foi executada qualquer reconciliação.</p>
              <?php else: ?>
                <p><span class="ffpc-status <?php echo esc_attr((string)$last['status']); ?>"><?php echo esc_html((string)$last['status']); ?></span> <strong>#<?php echo (int)$last['id']; ?></strong></p>
                <div class="ffrr-kpis">
                  <span><strong><?php echo (int)$aggregate['analysed']; ?></strong>Analisadas</span>
                  <span><strong><?php echo (int)$aggregate['would_change']; ?></strong>Com melhoria</span>
                  <span><strong><?php echo (int)$aggregate['applied']; ?></strong>Aplicadas</span>
                  <span><strong><?php echo esc_html(number_format_i18n((float)$aggregate['saving_km'], 1)); ?> km</strong>Poupança estimada</span>
                </div>
                <?php if (!empty($last['last_error_message'])): ?><p class="ffpc-error"><?php echo esc_html((string)$last['last_error_message']); ?></p><?php endif; ?>
              <?php endif; ?>
              <p class="description">Rotas em execução, concluídas, com visitas iniciadas ou com ordem manual bloqueada nunca são alteradas.</p>
            </div>
          </div>
        </div>
        <style>
        .ffrr-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(280px,.8fr);gap:16px}.ffrr-form{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:16px;border:1px solid #dbe5ef;border-radius:16px;background:#f8fafc}.ffrr-form label{display:grid;gap:5px;font-weight:750;color:#334155}.ffrr-form select,.ffrr-form input[type=date]{width:100%}.ffrr-confirm{grid-column:1/-1;display:flex!important;grid-auto-flow:column;justify-content:start;align-items:center}.ffrr-confirm input{width:18px;height:18px}.ffrr-summary{padding:16px;border:1px solid #dbe5ef;border-radius:16px;background:#fff}.ffrr-summary h3{margin-top:0}.ffrr-kpis{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:12px 0}.ffrr-kpis span{padding:10px;border-radius:12px;background:#f8fafc;color:#64748b;font-size:11px}.ffrr-kpis strong{display:block;color:#0f172a;font-size:19px}@media(max-width:1050px){.ffrr-grid{grid-template-columns:1fr}.ffrr-form{grid-template-columns:1fr 1fr}}@media(max-width:650px){.ffrr-form{grid-template-columns:1fr}}
        </style>
        <?php
    }

    private static function aggregate(array $row): array {
        $out = ['analysed'=>0,'would_change'=>0,'applied'=>0,'saving_km'=>0.0];
        $result = json_decode((string)($row['result_json'] ?? ''), true);
        foreach ((array)($result['runs'] ?? []) as $run) {
            $data = is_array($run['data'] ?? null) ? $run['data'] : [];
            $out['analysed'] += (int)($data['analysed'] ?? 0);
            $out['would_change'] += (int)($data['would_change'] ?? 0);
            $out['applied'] += (int)($data['applied'] ?? 0);
            $out['saving_km'] += (float)($data['saving_km'] ?? 0);
        }
        return $out;
    }

    private static function date(string $value): string {
        $value = trim($value);
        if ($value === '') return '';
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    private static function redirect(string $notice, string $message): void {
        $url = add_query_arg([
            'page' => ProcessCenter::SLUG,
            'ff_process_notice' => sanitize_key($notice),
            'ff_process_message' => substr(sanitize_text_field($message), 0, 180),
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
