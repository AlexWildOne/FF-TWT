<?php
namespace RoutesPro\Admin;

use RoutesPro\Services\UserOperationalPurgeService;
use RoutesPro\Support\AdminPage;

if (!defined('ABSPATH')) exit;

/**
 * Controlled two-step operational purge for test/incorrect operative activity.
 *
 * This is intentionally separate from the RGPD retention centre. It removes a
 * bounded operational footprint while preserving master, fiscal, security and
 * governance records.
 */
final class UserOperationalPurge {
    public const SLUG = 'routespro-operational-purge';
    private const NONCE_ACTION = 'fieldflow_user_operational_purge';
    private const NONCE_FIELD = 'fieldflow_user_operational_purge_nonce';
    private const TOKEN_TTL = 900;

    public static function render(): void {
        if (!current_user_can('routespro_manage')) return;

        $notice = '';
        $noticeType = 'updated';
        $preview = null;
        $result = null;
        $selectedUserId = isset($_POST['target_user_id']) ? absint($_POST['target_user_id']) : (isset($_GET['target_user_id']) ? absint($_GET['target_user_id']) : 0);
        $dateFrom = isset($_POST['date_from']) ? sanitize_text_field((string)wp_unslash($_POST['date_from'])) : '';
        $dateTo = isset($_POST['date_to']) ? sanitize_text_field((string)wp_unslash($_POST['date_to'])) : '';
        $allowAllDates = !empty($_POST['allow_all_dates']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (empty($_POST[self::NONCE_FIELD]) || !wp_verify_nonce((string)wp_unslash($_POST[self::NONCE_FIELD]), self::NONCE_ACTION)) {
                $notice = 'A sessão de segurança expirou. Atualiza a página e volta a tentar.';
                $noticeType = 'error';
            } else {
                $action = sanitize_key((string)wp_unslash($_POST['fieldflow_purge_action'] ?? 'preview'));
                if ($action === 'preview') {
                    if ($dateFrom === '' && $dateTo === '' && !$allowAllDates) {
                        $notice = 'Define um intervalo de datas ou confirma explicitamente que pretendes simular todo o histórico.';
                        $noticeType = 'error';
                    } else {
                        $preview = UserOperationalPurgeService::preview($selectedUserId, $dateFrom, $dateTo);
                    }
                    if (is_wp_error($preview)) {
                        $notice = $preview->get_error_message();
                        $noticeType = 'error';
                        $preview = null;
                    } elseif (is_array($preview)) {
                        UserOperationalPurgeService::recordPreview($preview);
                        $token = self::storePreviewToken($preview);
                        if ($token === '') {
                            $notice = 'Não foi possível criar a confirmação temporária da purga. Tenta novamente.';
                            $noticeType = 'error';
                            $preview = null;
                        } elseif (!empty($preview['blocked'])) {
                            $notice = (string)($preview['block_reason'] ?? 'A simulação encontrou uma condição de segurança que bloqueia a purga.');
                            $noticeType = 'warning';
                            $preview['_token'] = $token;
                        } else {
                            $notice = 'Simulação concluída. Nenhum dado foi alterado.';
                            $preview['_token'] = $token;
                        }
                    }
                } elseif ($action === 'apply') {
                    $token = sanitize_text_field((string)wp_unslash($_POST['preview_token'] ?? ''));
                    $stored = self::loadPreviewToken($token);
                    if (!$stored) {
                        $notice = 'A pré-visualização expirou. Faz uma nova simulação antes de purgar.';
                        $noticeType = 'error';
                    } else {
                        $selectedUserId = absint($stored['user_id'] ?? 0);
                        $dateFrom = (string)($stored['date_from'] ?? '');
                        $dateTo = (string)($stored['date_to'] ?? '');
                        $user = get_userdata($selectedUserId);
                        $expectedText = $user ? 'PURGAR ' . (string)$user->user_login : '';
                        $confirmation = trim((string)wp_unslash($_POST['confirm_text'] ?? ''));
                        if ($expectedText === '' || !hash_equals($expectedText, $confirmation)) {
                            $notice = 'A confirmação não corresponde. Escreve exatamente: ' . $expectedText;
                            $noticeType = 'error';
                            $preview = UserOperationalPurgeService::preview($selectedUserId, $dateFrom, $dateTo);
                            if (!is_wp_error($preview)) $preview['_token'] = $token; else $preview = null;
                        } else {
                            $result = UserOperationalPurgeService::purge(
                                $selectedUserId,
                                $dateFrom,
                                $dateTo,
                                (string)($stored['signature'] ?? '')
                            );
                            if (is_wp_error($result)) {
                                $notice = $result->get_error_message();
                                $noticeType = 'error';
                                $preview = UserOperationalPurgeService::preview($selectedUserId, $dateFrom, $dateTo);
                                if (!is_wp_error($preview)) {
                                    $preview['_token'] = self::storePreviewToken($preview);
                                } else {
                                    $preview = null;
                                }
                            } else {
                                self::deletePreviewToken($token);
                                $notice = 'Purga operacional concluída e transação confirmada.';
                                $noticeType = 'updated';
                            }
                        }
                    }
                }
            }
        }

        $users = self::users();
        AdminPage::open('Purga Operacional', 'Limpeza controlada do histórico de teste de um utilizador, com simulação, limites de segurança e auditoria.');
        self::styles();
        if ($notice !== '') AdminPage::notice($notice, $noticeType);
        ?>
        <div class="ffpurge-shell">
          <section class="ffpurge-hero">
            <div>
              <span class="ffpurge-kicker">Operational Data Reset 1.0</span>
              <h2>Limpar teste sem apagar a operação real</h2>
              <p>Seleciona o merchan e, de preferência, o dia exato do teste. Primeiro o FieldFlow simula tudo o que seria removido. Só depois desbloqueia a confirmação final.</p>
            </div>
            <div class="ffpurge-safety">
              <strong>Preservado por desenho</strong>
              <span>Utilizador WordPress</span>
              <span>Clientes, projetos e lojas</span>
              <span>Ficha Viva da Loja</span>
              <span>Despesas e histórico fiscal</span>
              <span>Audit, segurança e ledgers Cloud</span>
            </div>
          </section>

          <section class="ffpurge-card">
            <div class="ffpurge-card-head">
              <div><span class="ffpurge-step">1</span><div><h2>Definir o perímetro</h2><p>Um dia é o cenário mais seguro para limpar testes de operação.</p></div></div>
            </div>
            <form method="post" class="ffpurge-scope-form">
              <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
              <input type="hidden" name="fieldflow_purge_action" value="preview">
              <label class="ffpurge-wide"><span>Utilizador</span>
                <select name="target_user_id" required>
                  <option value="">Selecionar merchan...</option>
                  <?php foreach ($users as $user): ?>
                    <option value="<?php echo esc_attr((string)$user->ID); ?>" <?php selected($selectedUserId, (int)$user->ID); ?>><?php echo esc_html(self::userLabel($user)); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label><span>Data inicial</span><input type="date" name="date_from" value="<?php echo esc_attr($dateFrom); ?>"></label>
              <label><span>Data final</span><input type="date" name="date_to" value="<?php echo esc_attr($dateTo); ?>"></label>
              <div class="ffpurge-wide ffpurge-scope-note">Para um teste de um dia, preenche a mesma data nos dois campos. Todo o histórico só é permitido com confirmação explícita.</div>
              <label class="ffpurge-wide ffpurge-all-history"><input type="checkbox" name="allow_all_dates" value="1" <?php checked($allowAllDates); ?>><span>Permitir simulação de todo o histórico se ambas as datas ficarem vazias.</span></label>
              <div class="ffpurge-wide"><button type="submit" class="button button-primary button-hero">Simular purga</button></div>
            </form>
          </section>

          <?php if (is_array($preview)): self::renderPreview($preview); endif; ?>
          <?php if (is_array($result)): self::renderResult($result); endif; ?>

          <section class="ffpurge-card ffpurge-info-card">
            <h2>O que esta ferramenta limpa</h2>
            <p>Rotas do utilizador no período, paragens filhas, atribuições dessas rotas, atribuições do próprio utilizador nesse período, submissões e respostas, versões do histórico de formulários, eventos de visita, rascunhos e submissões Shopping. Os registos canónicos de formulário são recalculados depois da remoção.</p>
            <p><strong>Importante:</strong> a ferramenta não apaga dados remotos do Azure em silêncio. Se a simulação detetar submissões já materializadas no Azure, mostra um aviso separado para reconciliação remota.</p>
          </section>
        </div>
        <?php
        AdminPage::close();
    }

    private static function renderPreview(array $preview): void {
        $counts = (array)($preview['counts'] ?? []);
        $user = (array)($preview['user'] ?? []);
        $scope = (array)($preview['scope'] ?? []);
        $azure = (array)($preview['azure'] ?? []);
        $risk = (array)($preview['risk'] ?? []);
        $token = (string)($preview['_token'] ?? '');
        $confirm = 'PURGAR ' . (string)($user['login'] ?? '');
        $range = !empty($scope['all_dates']) ? 'Todo o histórico' : trim((string)($scope['date_from'] ?? '') . ' a ' . (string)($scope['date_to'] ?? ''), ' a');
        $labels = [
            'routes'=>'Rotas',
            'route_stops'=>'Paragens',
            'route_assignments'=>'Atribuições de rota',
            'submissions'=>'Submissões',
            'answers'=>'Respostas',
            'record_versions'=>'Versões históricas',
            'record_values'=>'Valores históricos',
            'events'=>'Eventos de visita',
            'drafts'=>'Rascunhos',
            'draft_files'=>'Ficheiros de rascunho',
            'route_snapshots'=>'Snapshots de rota',
            'shopping_submissions'=>'Shopping',
        ];
        ?>
        <section class="ffpurge-card ffpurge-preview">
          <div class="ffpurge-card-head">
            <div><span class="ffpurge-step">2</span><div><h2>Pré-visualização</h2><p><?php echo esc_html((string)($user['name'] ?: $user['login'])); ?>, <?php echo esc_html($range); ?></p></div></div>
            <span class="ffpurge-total"><?php echo esc_html(number_format_i18n((int)($preview['destructive_total'] ?? 0))); ?> registos elegíveis</span>
          </div>

          <div class="ffpurge-count-grid">
            <?php foreach ($labels as $key => $label): ?>
              <div><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html(number_format_i18n((int)($counts[$key] ?? 0))); ?></strong></div>
            <?php endforeach; ?>
          </div>

          <div class="ffpurge-retained">
            <div><span>Despesas encontradas</span><strong><?php echo esc_html(number_format_i18n((int)($counts['expenses_retained'] ?? 0))); ?></strong><small>preservadas</small></div>
            <div><span>Registos canónicos</span><strong><?php echo esc_html(number_format_i18n((int)($counts['records_rebuilt'] ?? 0))); ?></strong><small>recalculados, não apagados cegamente</small></div>
            <div><span>Submissões Azure</span><strong><?php echo esc_html(number_format_i18n((int)($counts['azure_synced_submissions'] ?? 0))); ?></strong><small><?php echo !empty($azure['cleanup_required']) ? 'requerem reconciliação remota' : 'sem materialização concluída detetada'; ?></small></div>
          </div>

          <?php if (!empty($risk['shared_routes']) || !empty($risk['foreign_submissions']) || !empty($risk['foreign_versions'])): ?>
            <div class="ffpurge-alert ffpurge-alert-danger">
              <strong>Bloqueio de segurança, atividade partilhada detetada</strong>
              <p>Existem dados de outros utilizadores ligados às rotas selecionadas. O FieldFlow não permite uma purga destrutiva automática neste cenário para evitar apagar operação real.</p>
              <div class="ffpurge-risk-grid">
                <span>Rotas partilhadas: <b><?php echo esc_html(number_format_i18n((int)($risk['shared_routes'] ?? 0))); ?></b></span>
                <span>Submissões de outros users: <b><?php echo esc_html(number_format_i18n((int)($risk['foreign_submissions'] ?? 0))); ?></b></span>
                <span>Versões de outros users: <b><?php echo esc_html(number_format_i18n((int)($risk['foreign_versions'] ?? 0))); ?></b></span>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!empty($azure['cleanup_required'])): ?>
            <div class="ffpurge-alert ffpurge-alert-azure">
              <strong>Azure, reconciliação remota necessária</strong>
              <p><?php echo esc_html(number_format_i18n((int)($azure['synced_submissions'] ?? 0))); ?> submissão(ões) deste perímetro têm jobs Cloud concluídos. A purga local não altera esses dados remotos. Depois da limpeza local, usa os IDs abaixo para a purga Azure controlada.</p>
              <code><?php echo esc_html(implode(', ', array_slice(array_map('absint', (array)($azure['submission_ids'] ?? [])), 0, 40))); ?></code>
            </div>
          <?php else: ?>
            <div class="ffpurge-alert ffpurge-alert-ok"><strong>Azure limpo para este perímetro</strong><p>Não foram detetados jobs concluídos associados às submissões selecionadas.</p></div>
          <?php endif; ?>

          <?php if (!empty($preview['blocked'])): ?>
            <div class="ffpurge-alert ffpurge-alert-danger"><strong>Purga bloqueada</strong><p><?php echo esc_html((string)($preview['block_reason'] ?? 'A simulação encontrou uma condição de segurança.')); ?></p></div>
          <?php elseif ((int)($preview['destructive_total'] ?? 0) === 0): ?>
            <div class="ffpurge-alert ffpurge-alert-ok"><strong>Nada para remover</strong><p>Não existe rasto operacional destrutivo neste utilizador e período.</p></div>
          <?php else: ?>
            <div class="ffpurge-confirm-box">
              <div>
                <span class="ffpurge-step">3</span>
                <div><h2>Confirmação final</h2><p>O preview fica válido durante 15 minutos. Se os dados mudarem, a aplicação é automaticamente recusada.</p></div>
              </div>
              <form method="post">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                <input type="hidden" name="fieldflow_purge_action" value="apply">
                <input type="hidden" name="preview_token" value="<?php echo esc_attr($token); ?>">
                <input type="hidden" name="target_user_id" value="<?php echo esc_attr((string)($user['id'] ?? 0)); ?>">
                <input type="hidden" name="date_from" value="<?php echo esc_attr((string)($scope['date_from'] ?? '')); ?>">
                <input type="hidden" name="date_to" value="<?php echo esc_attr((string)($scope['date_to'] ?? '')); ?>">
                <label><span>Escreve exatamente <code><?php echo esc_html($confirm); ?></code></span><input type="text" name="confirm_text" autocomplete="off" spellcheck="false" required></label>
                <button type="submit" class="button ffpurge-danger-button">Aplicar purga operacional</button>
              </form>
            </div>
          <?php endif; ?>
        </section>
        <?php
    }

    private static function renderResult(array $result): void {
        $deleted = (array)($result['deleted'] ?? []);
        $azure = (array)($result['azure'] ?? []);
        $user = (array)($result['user'] ?? []);
        ?>
        <section class="ffpurge-card ffpurge-result">
          <div class="ffpurge-result-icon">✓</div>
          <div>
            <span class="ffpurge-kicker">Transação concluída</span>
            <h2>Rasto operacional local removido</h2>
            <p>O FieldFlow recalculou o histórico canónico e invalidou a cache de rota. O utilizador <?php echo esc_html((string)($user['name'] ?: $user['login'])); ?> continua existente, tal como os dados de negócio e auditoria.</p>
            <div class="ffpurge-result-grid">
              <?php foreach ($deleted as $key => $value): ?><span><?php echo esc_html(self::resultLabel((string)$key)); ?> <strong><?php echo esc_html(number_format_i18n((int)$value)); ?></strong></span><?php endforeach; ?>
            </div>
            <?php if (!empty($azure['cleanup_required'])): ?>
              <div class="ffpurge-alert ffpurge-alert-azure"><strong>Falta apenas reconciliar Azure</strong><p>A limpeza local terminou, mas <?php echo esc_html(number_format_i18n((int)($azure['synced_submissions'] ?? 0))); ?> submissão(ões) têm materialização remota. Guarda estes IDs para o passo Cloud Shell: <code><?php echo esc_html(implode(', ', array_map('absint', (array)($azure['submission_ids'] ?? [])))); ?></code></p></div>
            <?php endif; ?>
          </div>
        </section>
        <?php
    }

    private static function users(): array {
        return get_users([
            'orderby' => 'display_name',
            'order' => 'ASC',
            'number' => 500,
        ]);
    }

    private static function userLabel(\WP_User $user): string {
        $label = trim((string)$user->display_name);
        if ($label === '') $label = (string)$user->user_login;
        return $label . '  ·  ' . (string)$user->user_login . '  ·  ID ' . (int)$user->ID;
    }

    private static function transientKey(string $token): string {
        return 'ff_user_purge_' . get_current_user_id() . '_' . preg_replace('/[^a-zA-Z0-9]/', '', $token);
    }

    private static function storePreviewToken(array $preview): string {
        if (empty($preview['signature']) || empty($preview['scope']['user_id'])) return '';
        try {
            $token = bin2hex(random_bytes(18));
        } catch (\Throwable $e) {
            $token = wp_generate_password(36, false, false);
        }
        $ok = set_transient(self::transientKey($token), [
            'user_id' => absint($preview['scope']['user_id'] ?? 0),
            'date_from' => (string)($preview['scope']['date_from'] ?? ''),
            'date_to' => (string)($preview['scope']['date_to'] ?? ''),
            'signature' => (string)$preview['signature'],
            'created_by' => get_current_user_id(),
        ], self::TOKEN_TTL);
        return $ok ? $token : '';
    }

    private static function loadPreviewToken(string $token): ?array {
        if ($token === '') return null;
        $value = get_transient(self::transientKey($token));
        if (!is_array($value)) return null;
        if (absint($value['created_by'] ?? 0) !== get_current_user_id()) return null;
        return $value;
    }

    private static function deletePreviewToken(string $token): void {
        if ($token !== '') delete_transient(self::transientKey($token));
    }

    private static function resultLabel(string $key): string {
        $labels = [
            'routes'=>'Rotas', 'route_stops'=>'Paragens', 'route_assignments'=>'Atribuições',
            'submissions'=>'Submissões', 'answers'=>'Respostas', 'record_versions'=>'Versões',
            'record_values'=>'Valores', 'events'=>'Eventos', 'drafts'=>'Rascunhos',
            'draft_files'=>'Ficheiros', 'physical_draft_files'=>'Ficheiros físicos', 'physical_draft_files_skipped'=>'Ficheiros físicos preservados', 'route_snapshots'=>'Snapshots de rota', 'shopping_submissions'=>'Shopping',
        ];
        return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    private static function styles(): void {
        ?>
        <style>
          .ffpurge-shell{max-width:1480px;margin:18px 0 42px;display:grid;gap:18px}.ffpurge-hero,.ffpurge-card{background:#fff;border:1px solid #e2e8f0;border-radius:22px;box-shadow:0 12px 32px rgba(15,23,42,.05)}.ffpurge-hero{padding:28px;display:grid;grid-template-columns:minmax(0,1.5fr) minmax(280px,.65fr);gap:24px;background:linear-gradient(145deg,#fff 0%,#f8fafc 66%,#eff6ff 100%)}.ffpurge-kicker{display:inline-block;color:#0f766e;font-weight:800;font-size:11px;letter-spacing:.13em;text-transform:uppercase}.ffpurge-hero h2,.ffpurge-card h2{color:#0f172a;margin:6px 0 8px}.ffpurge-hero h2{font-size:30px;line-height:1.08}.ffpurge-hero p,.ffpurge-card p{color:#64748b;line-height:1.55;margin:0}.ffpurge-safety{display:grid;gap:8px;padding:18px;border-radius:16px;background:#0f172a;color:#e2e8f0}.ffpurge-safety strong{color:#fff;margin-bottom:4px}.ffpurge-safety span:before{content:'✓';margin-right:8px;color:#5eead4;font-weight:800}.ffpurge-card{padding:24px}.ffpurge-card-head{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:18px}.ffpurge-card-head>div,.ffpurge-confirm-box>div:first-child{display:flex;align-items:center;gap:12px}.ffpurge-card-head h2,.ffpurge-confirm-box h2{font-size:19px;margin:0 0 3px}.ffpurge-step{width:34px;height:34px;border-radius:11px;display:inline-grid;place-items:center;background:#e0f2fe;color:#0369a1;font-weight:900;flex:0 0 auto}.ffpurge-scope-form{display:grid;grid-template-columns:1fr 1fr;gap:15px}.ffpurge-scope-form label,.ffpurge-confirm-box label{display:grid;gap:7px}.ffpurge-scope-form label span,.ffpurge-confirm-box label span{font-size:12px;font-weight:800;color:#334155}.ffpurge-scope-form input,.ffpurge-scope-form select,.ffpurge-confirm-box input{width:100%;min-height:44px;border:1px solid #cbd5e1;border-radius:11px;padding:8px 11px}.ffpurge-wide{grid-column:1/-1}.ffpurge-scope-note{padding:12px 14px;border-radius:12px;background:#f8fafc;color:#64748b;font-size:12px}.ffpurge-all-history{display:flex!important;grid-template-columns:auto 1fr!important;align-items:center;gap:9px!important;color:#475569}.ffpurge-all-history input{width:auto!important;min-height:0!important;margin:0}.ffpurge-all-history span{font-weight:700!important}.ffpurge-total{padding:8px 12px;border-radius:999px;background:#f1f5f9;color:#334155;font-size:12px;font-weight:800;white-space:nowrap}.ffpurge-count-grid{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:10px}.ffpurge-count-grid>div,.ffpurge-retained>div{padding:14px;border-radius:14px;background:#f8fafc;border:1px solid #eef2f7;display:grid;gap:3px}.ffpurge-count-grid span,.ffpurge-retained span{color:#64748b;font-size:11px;font-weight:700}.ffpurge-count-grid strong{color:#0f172a;font-size:22px}.ffpurge-retained{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:12px}.ffpurge-retained strong{font-size:18px;color:#0f172a}.ffpurge-retained small{color:#64748b}.ffpurge-alert{margin-top:14px;padding:14px 16px;border-radius:14px;border:1px solid}.ffpurge-alert strong{display:block;margin-bottom:4px}.ffpurge-alert p{font-size:13px}.ffpurge-alert code{display:block;margin-top:9px;white-space:normal;overflow-wrap:anywhere}.ffpurge-alert-ok{background:#f0fdf4;border-color:#bbf7d0;color:#166534}.ffpurge-alert-ok p{color:#15803d}.ffpurge-alert-azure{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}.ffpurge-alert-azure p{color:#475569}.ffpurge-alert-danger{background:#fff7ed;border-color:#fed7aa;color:#9a3412}.ffpurge-alert-danger p{color:#7c2d12}.ffpurge-risk-grid{display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:8px;font-size:12px}.ffpurge-confirm-box{margin-top:18px;padding:18px;border-radius:16px;border:1px solid #fecaca;background:#fffafa;display:grid;grid-template-columns:minmax(250px,.8fr) minmax(320px,1.2fr);gap:22px}.ffpurge-confirm-box form{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end}.ffpurge-confirm-box label{min-width:0}.ffpurge-danger-button{min-height:44px!important;border-color:#b91c1c!important;color:#fff!important;background:#b91c1c!important;font-weight:800!important}.ffpurge-danger-button:hover{background:#991b1b!important}.ffpurge-info-card{background:#f8fafc}.ffpurge-info-card p+p{margin-top:10px}.ffpurge-result{display:grid;grid-template-columns:58px 1fr;gap:18px;border-color:#bbf7d0}.ffpurge-result-icon{width:58px;height:58px;border-radius:18px;background:#dcfce7;color:#15803d;display:grid;place-items:center;font-size:28px;font-weight:900}.ffpurge-result-grid{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}.ffpurge-result-grid span{padding:7px 10px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:12px}.ffpurge-result-grid strong{color:#0f172a;margin-left:4px}@media(max-width:1100px){.ffpurge-count-grid{grid-template-columns:repeat(3,1fr)}}@media(max-width:782px){.ffpurge-hero,.ffpurge-scope-form,.ffpurge-confirm-box{grid-template-columns:1fr}.ffpurge-count-grid{grid-template-columns:repeat(2,1fr)}.ffpurge-retained{grid-template-columns:1fr}.ffpurge-confirm-box form{grid-template-columns:1fr}.ffpurge-card-head{align-items:flex-start;flex-direction:column}.ffpurge-hero{padding:20px}.ffpurge-card{padding:18px}.ffpurge-hero h2{font-size:24px}}
        </style>
        <?php
    }
}
