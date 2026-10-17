<?php
namespace RoutesPro\Admin;

use RoutesPro\Privacy\PrivacyGovernance;
use RoutesPro\Support\AdminPage;

if (!defined('ABSPATH')) exit;

class PrivacyCenter {
    public const SLUG = 'routespro-privacy';

    public static function render(): void {
        if (!current_user_can('routespro_manage')) return;
        wp_enqueue_style('fieldflow-privacy-governance', ROUTESPRO_URL . 'assets/fieldflow-privacy-governance.css', [], ROUTESPRO_VERSION);

        $notice = '';
        $noticeType = 'updated';
        $run = null;
        if (!empty($_POST['fieldflow_privacy_nonce']) && wp_verify_nonce((string)$_POST['fieldflow_privacy_nonce'], 'fieldflow_privacy_center')) {
            $action = sanitize_key((string)($_POST['fieldflow_privacy_action'] ?? 'save'));
            if ($action === 'save') {
                PrivacyGovernance::save(self::settingsFromPost());
                $notice = 'Política de privacidade e retenção atualizada.';
            } elseif ($action === 'preview') {
                $run = PrivacyGovernance::runRetention('preview', get_current_user_id(), 'manual');
                $notice = 'Simulação concluída. Nenhum dado foi alterado.';
            } elseif ($action === 'apply') {
                if (empty($_POST['confirm_apply'])) {
                    $notice = 'Confirma explicitamente a aplicação da retenção antes de continuar.';
                    $noticeType = 'error';
                } else {
                    $run = PrivacyGovernance::runRetention('apply', get_current_user_id(), 'manual');
                    if (($run['effective_mode'] ?? 'preview') !== 'apply') {
                        $notice = 'A execução permaneceu em simulação. Ativa o modo Aplicar e desativa a retenção legal para permitir remoções.';
                        $noticeType = 'warning';
                    } else {
                        $notice = 'Retenção aplicada apenas aos registos técnicos elegíveis.';
                    }
                }
            } elseif ($action === 'create_request') {
                $requestId = PrivacyGovernance::createRequest($_POST);
                $notice = $requestId > 0 ? 'Pedido RGPD registado com sucesso.' : 'Não foi possível registar o pedido RGPD.';
                if ($requestId <= 0) $noticeType = 'error';
            } elseif ($action === 'update_request') {
                $updated = PrivacyGovernance::updateRequestStatus(absint($_POST['request_id'] ?? 0), (string)($_POST['request_status'] ?? ''));
                $notice = $updated ? 'Estado do pedido atualizado.' : 'Não foi possível atualizar o pedido.';
                if (!$updated) $noticeType = 'error';
            }
        }

        $settings = PrivacyGovernance::settings();
        $status = PrivacyGovernance::status();
        $requests = PrivacyGovernance::recentRequests(50);
        $lastRun = is_array($status['last_run'] ?? null) ? $status['last_run'] : [];
        $typeLabels = ['access'=>'Acesso','export'=>'Exportação','erase'=>'Eliminação','anonymise'=>'Anonimização','rectify'=>'Retificação'];
        $statusLabels = ['received'=>'Recebido','verified'=>'Identidade validada','in_progress'=>'Em tratamento','completed'=>'Concluído','rejected'=>'Rejeitado','cancelled'=>'Cancelado'];
        $retentionFields = [
            'ai_usage_days'=>'Utilização de IA',
            'system_logs_days'=>'Logs técnicos',
            'email_logs_days'=>'Registos de email',
            'security_events_days'=>'Eventos de segurança resolvidos',
            'observability_days'=>'Eventos técnicos resolvidos',
            'process_runs_days'=>'Execuções concluídas',
            'core_events_days'=>'Eventos centrais processados',
            'audit_days'=>'Auditoria histórica',
            'expired_mobile_days'=>'Sessões e mutações mobile expiradas',
        ];

        AdminPage::open('Privacidade e Governação', 'Retenção, pedidos RGPD e controlo central do tratamento de dados no FieldFlow.');
        if ($notice !== '') AdminPage::notice($notice, $noticeType);
        ?>
        <div class="ffpg-shell">
          <section class="ffpg-hero">
            <div>
              <span class="ffpg-kicker">Privacy Center 1.0</span>
              <h2>Privacidade operacional, sem colocar a operação em risco</h2>
              <p>O motor começa em simulação. Submissões, fotografias, despesas e dados de negócio não entram na limpeza automática.</p>
            </div>
            <div class="ffpg-status-grid">
              <div><span>Motor</span><strong><?php echo !empty($settings['enabled']) ? 'Ativo' : 'Desativado'; ?></strong></div>
              <div><span>Modo</span><strong><?php echo $settings['mode'] === 'enforce' ? 'Aplicar' : 'Simular'; ?></strong></div>
              <div><span>Retenção legal</span><strong><?php echo !empty($settings['legal_hold']) ? 'Ativa' : 'Livre'; ?></strong></div>
              <div><span>Pedidos abertos</span><strong><?php echo esc_html(number_format_i18n((int)$status['open_requests'])); ?></strong></div>
            </div>
          </section>

          <div class="ffpg-grid">
            <section class="ffpg-card">
              <h2>Política de retenção</h2>
              <form method="post">
                <?php wp_nonce_field('fieldflow_privacy_center', 'fieldflow_privacy_nonce'); ?>
                <input type="hidden" name="fieldflow_privacy_action" value="save">
                <div class="ffpg-fields">
                  <label><span>Estado</span><select name="enabled"><option value="1" <?php selected((int)$settings['enabled'],1); ?>>Ativo</option><option value="0" <?php selected((int)$settings['enabled'],0); ?>>Desativado</option></select></label>
                  <label><span>Modo</span><select name="mode"><option value="shadow" <?php selected($settings['mode'],'shadow'); ?>>Simular, recomendado</option><option value="enforce" <?php selected($settings['mode'],'enforce'); ?>>Aplicar</option></select></label>
                  <label class="wide ffpg-check"><input type="checkbox" name="legal_hold" value="1" <?php checked(!empty($settings['legal_hold'])); ?>> <span>Ativar retenção legal global, bloqueia eliminações automáticas e pedidos de apagamento FieldFlow.</span></label>
                  <?php foreach ($retentionFields as $field=>$label): ?>
                    <label><span><?php echo esc_html($label); ?></span><input type="number" min="7" max="3650" name="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr((string)$settings[$field]); ?>"><small>dias</small></label>
                  <?php endforeach; ?>
                </div>
                <p><button class="button button-primary">Guardar política</button></p>
              </form>
            </section>

            <section class="ffpg-card">
              <h2>Execução controlada</h2>
              <p>A simulação conta registos elegíveis. A aplicação remove no máximo <?php echo esc_html(number_format_i18n(2000)); ?> registos por política e execução.</p>
              <?php if ($lastRun): ?>
                <div class="ffpg-last-run"><strong>Última execução</strong><span><?php echo esc_html((string)($lastRun['ran_at'] ?? '')); ?>, <?php echo esc_html((string)($lastRun['mode'] ?? 'preview')); ?></span><span><?php echo esc_html(number_format_i18n((int)($lastRun['eligible'] ?? 0))); ?> elegíveis, <?php echo esc_html(number_format_i18n((int)($lastRun['affected'] ?? 0))); ?> alterados</span></div>
              <?php endif; ?>
              <?php if (is_array($run)): ?>
                <div class="ffpg-run-results">
                  <?php foreach (($run['policies'] ?? []) as $row): ?><div><span><?php echo esc_html((string)$row['label']); ?></span><strong><?php echo esc_html(number_format_i18n((int)$row['eligible'])); ?></strong><small>elegíveis, <?php echo esc_html(number_format_i18n((int)$row['affected'])); ?> removidos</small></div><?php endforeach; ?>
                </div>
              <?php endif; ?>
              <form method="post" class="ffpg-action-form">
                <?php wp_nonce_field('fieldflow_privacy_center', 'fieldflow_privacy_nonce'); ?>
                <button class="button" name="fieldflow_privacy_action" value="preview">Simular agora</button>
                <label class="ffpg-confirm"><input type="checkbox" name="confirm_apply" value="1"> Confirmo a aplicação aos registos técnicos elegíveis.</label>
                <button class="button" name="fieldflow_privacy_action" value="apply">Aplicar retenção</button>
              </form>
              <div class="ffpg-safe-note">O FieldFlow preserva o histórico operacional. Esta rotina não elimina submissões, respostas, fotografias, lojas, rotas, stocks ou despesas.</div>
            </section>
          </div>

          <section class="ffpg-card">
            <h2>Novo pedido RGPD</h2>
            <form method="post" class="ffpg-request-form">
              <?php wp_nonce_field('fieldflow_privacy_center', 'fieldflow_privacy_nonce'); ?>
              <input type="hidden" name="fieldflow_privacy_action" value="create_request">
              <label><span>Tipo</span><select name="request_type"><?php foreach($typeLabels as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
              <label><span>Email do titular</span><input type="email" name="subject_email"><small>Preenche o email ou o ID.</small></label>
              <label><span>ID de utilizador</span><input type="number" min="0" name="subject_user_id"></label>
              <label><span>Cliente ID</span><input type="number" min="0" name="client_id"></label>
              <label><span>Projeto ID</span><input type="number" min="0" name="project_id"></label>
              <label class="wide"><span>Notas internas</span><textarea name="notes" rows="3"></textarea></label>
              <div class="wide"><button class="button button-primary">Registar pedido</button></div>
            </form>
            <p class="description">A exportação e eliminação de dados FieldFlow também estão integradas nas ferramentas nativas de privacidade do WordPress.</p>
          </section>

          <section class="ffpg-card">
            <h2>Pedidos recentes</h2>
            <div class="ffpg-table-wrap"><table class="widefat striped"><thead><tr><th>ID</th><th>Data</th><th>Tipo</th><th>Titular</th><th>Âmbito</th><th>Estado</th><th>Ação</th></tr></thead><tbody>
              <?php if (!$requests): ?><tr><td colspan="7">Ainda não existem pedidos registados.</td></tr><?php endif; ?>
              <?php foreach($requests as $request): ?>
                <tr>
                  <td>#<?php echo (int)$request['id']; ?></td>
                  <td><?php echo esc_html((string)$request['requested_at']); ?></td>
                  <td><?php echo esc_html($typeLabels[$request['request_type']] ?? $request['request_type']); ?></td>
                  <td><?php echo esc_html((string)($request['subject_email'] ?: ('User #'.(int)$request['subject_user_id']))); ?></td>
                  <td>Cliente #<?php echo (int)$request['client_id']; ?>, Projeto #<?php echo (int)$request['project_id']; ?></td>
                  <td><?php echo esc_html($statusLabels[$request['status']] ?? $request['status']); ?></td>
                  <td><form method="post" class="ffpg-inline-form"><?php wp_nonce_field('fieldflow_privacy_center', 'fieldflow_privacy_nonce'); ?><input type="hidden" name="fieldflow_privacy_action" value="update_request"><input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>"><select name="request_status"><?php foreach($statusLabels as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($request['status'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select><button class="button">Atualizar</button></form></td>
                </tr>
              <?php endforeach; ?>
            </tbody></table></div>
          </section>
        </div>
        <?php
        AdminPage::close();
    }

    private static function settingsFromPost(): array {
        $data = [
            'enabled'=>!empty($_POST['enabled']) ? 1 : 0,
            'mode'=>sanitize_key((string)($_POST['mode'] ?? 'shadow')),
            'legal_hold'=>!empty($_POST['legal_hold']) ? 1 : 0,
        ];
        foreach (array_keys(PrivacyGovernance::defaults()) as $key) {
            if (substr($key, -5) === '_days') $data[$key] = absint($_POST[$key] ?? PrivacyGovernance::defaults()[$key]);
        }
        return $data;
    }
}
