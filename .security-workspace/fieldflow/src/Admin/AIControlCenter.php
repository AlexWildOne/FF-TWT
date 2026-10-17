<?php
namespace RoutesPro\Admin;

use RoutesPro\Support\AdminPage;
use RoutesPro\Support\Config;
use RoutesPro\Services\AIManager;
use RoutesPro\Services\AIFactory;
use RoutesPro\Repositories\AIUsageRepository;
use RoutesPro\AI\Governance;

if (!defined('ABSPATH')) exit;

class AIControlCenter {
    private const PROVIDERS = ['none','google','azure','openai','copilot'];
    private const MODULE_PROVIDERS = ['inherit','none','google','azure','openai','copilot'];

    public static function render(): void {
        if (!current_user_can('routespro_manage')) return;

        $notice = '';
        $noticeType = 'updated';
        $testResult = null;

        if (!empty($_POST['fieldflow_ai_nonce']) && wp_verify_nonce((string)$_POST['fieldflow_ai_nonce'], 'fieldflow_ai_control')) {
            $action = sanitize_text_field((string)($_POST['fieldflow_ai_action'] ?? 'save'));
            if ($action === 'clear_logs') {
                AIUsageRepository::clear();
                $notice = 'Histórico técnico de IA limpo.';
            } else {
                self::saveFromPost();
                if (Config::storageError() !== '') {
                    $notice = Config::storageError();
                    $noticeType = 'error';
                } else {
                    $notice = 'Centro de IA atualizado.';
                }
                if ($noticeType !== 'error' && strpos($action, 'test:') === 0) {
                    $provider = sanitize_key(substr($action, 5));
                    $module = sanitize_key((string)($_POST['test_module'] ?? 'general')) ?: 'general';
                    $testResult = AIManager::test($provider, $module);
                    if (!empty($testResult['ok'])) {
                        $notice = 'Ligação de IA validada com sucesso.';
                    } else {
                        $notice = 'O teste de IA falhou. Consulta o diagnóstico abaixo.';
                        $noticeType = 'error';
                    }
                }
            }
        }

        $o = Config::all();
        $diagnostics = AIManager::diagnostics();
        $summary = AIUsageRepository::summary(7);
        $recent = AIUsageRepository::recent(60);
        $today = AIUsageRepository::todayCount();
        $governance = Governance::status();
        $finops = AIUsageRepository::monthBreakdown(12);
        $activeProvider = Config::aiProviderForModule('general', $o);
        $moduleRows = [
            'briefings'=>['Briefings','Importação, auditoria, resumos e comparação semanal.'],
            'routing'=>['Routing','Sugestões operacionais, notas e apoio à construção de rotas.'],
            'analytics'=>['Analytics','Análise de execução, formulários, fotografias e resultados.'],
            'fronts_vision'=>['Leitura de frentes','Contagem de frentes por fotografia nas perguntas de frentes e Share Space (piloto 9CO). Precisa de um modelo que aceite imagens.'],
        ];

        AdminPage::open('Configuração de IA', 'Providers, credenciais, governação e consumo da IA. A inteligência operacional e os dashboards vivem no FieldFlow Intelligence Center.');
        if ($notice !== '') AdminPage::notice($notice, $noticeType);
        ?>
        <style>
          .ffai-shell{max-width:1440px}.ffai-hero{display:grid;grid-template-columns:1.5fr 1fr;gap:18px;margin:18px 0}.ffai-card{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:20px;box-shadow:0 12px 32px rgba(15,23,42,.055)}.ffai-card h2,.ffai-card h3{margin-top:0}.ffai-kicker{display:block;color:#6366f1;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:7px}.ffai-status{display:inline-flex;align-items:center;gap:7px;padding:6px 11px;border-radius:999px;font-size:12px;font-weight:800}.ffai-status.ok{background:#dcfce7;color:#166534}.ffai-status.no{background:#fee2e2;color:#991b1b}.ffai-status.warn{background:#fef3c7;color:#92400e}.ffai-metrics{display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:12px}.ffai-metric{padding:14px;border:1px solid #e2e8f0;border-radius:16px;background:#f8fafc}.ffai-metric strong{display:block;font-size:25px;margin-top:5px}.ffai-tabs{display:flex;gap:8px;overflow:auto;margin:18px 0 14px;padding-bottom:3px}.ffai-tabs button{border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:999px;padding:10px 15px;font-weight:800;cursor:pointer;white-space:nowrap}.ffai-tabs button.active,.ffai-tabs button:hover{background:#111827;color:#fff;border-color:#111827}.ffai-panel{display:none}.ffai-panel.active{display:block}.ffai-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.ffai-provider{border:1px solid #e2e8f0;border-radius:18px;padding:18px;background:#fff}.ffai-provider-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:12px}.ffai-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.ffai-field.wide{grid-column:1/-1}.ffai-field label{display:block;font-weight:750;margin-bottom:6px}.ffai-field input,.ffai-field select,.ffai-field textarea{width:100%;max-width:none}.ffai-help{color:#64748b;font-size:12px;margin:5px 0 0}.ffai-secret-wrap{display:flex;gap:8px}.ffai-secret-wrap input{flex:1}.ffai-module-table td,.ffai-module-table th{vertical-align:middle}.ffai-test{padding:14px;border-radius:15px;margin:14px 0}.ffai-test.ok{background:#ecfdf5;border:1px solid #86efac}.ffai-test.no{background:#fef2f2;border:1px solid #fecaca}.ffai-table-wrap{overflow:auto}.ffai-log-error{max-width:500px;white-space:normal;color:#991b1b}.ffai-note{background:#eef2ff;border:1px solid #c7d2fe;color:#3730a3;padding:14px;border-radius:16px}.ffai-danger{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;padding:14px;border-radius:16px}.ffai-actions{position:sticky;bottom:0;background:rgba(255,255,255,.94);backdrop-filter:blur(10px);padding:14px 0 4px;margin-top:20px;border-top:1px solid #e2e8f0;z-index:3}.ffai-actions .button-primary{min-height:42px;padding:0 22px}.ffai-provider-badge{font-size:11px;font-weight:800;padding:5px 9px;border-radius:999px;background:#f1f5f9;color:#475569}.ffai-constant{color:#0369a1;font-weight:700}.ffai-disabled{opacity:.55}.ffai-section-title{display:flex;justify-content:space-between;gap:12px;align-items:center;margin:0 0 12px}.ffai-section-title p{margin:0;color:#64748b}.ffai-toggle{display:flex;align-items:center;gap:9px;font-weight:750}.ffai-toggle input{width:18px;height:18px}.ffai-provider-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
          @media(max-width:1050px){.ffai-hero,.ffai-grid{grid-template-columns:1fr}.ffai-metrics{grid-template-columns:repeat(2,1fr)}}@media(max-width:680px){.ffai-fields{grid-template-columns:1fr}.ffai-field.wide{grid-column:auto}.ffai-metrics{grid-template-columns:1fr 1fr}}
        </style>
        <div class="ffai-shell">
          <div class="ffai-hero">
            <div class="ffai-card">
              <span class="ffai-kicker">Operational AI Control Center</span>
              <h2>Um cérebro, vários módulos, zero confusão</h2>
              <p>Esta área controla apenas IA generativa. O licenciamento Azure e o Azure Maps continuam separados e não fornecem modelos de linguagem.</p>
              <p><span class="ffai-status <?php echo $activeProvider !== 'none' && Config::aiProviderReady($activeProvider, $o) ? 'ok' : 'warn'; ?>"><?php echo esc_html(AIFactory::providerLabel($activeProvider)); ?></span></p>
              <div class="ffai-note">As credenciais existentes foram mantidas. Campos secretos vazios não apagam a chave guardada. O FieldFlow não grava prompts nem conteúdo comercial nos logs de utilização.</div>
            </div>
            <div class="ffai-card">
              <span class="ffai-kicker">Últimos 7 dias</span>
              <div class="ffai-metrics">
                <div class="ffai-metric"><span>Chamadas</span><strong><?php echo esc_html(number_format_i18n($summary['total'])); ?></strong></div>
                <div class="ffai-metric"><span>Sucesso</span><strong><?php echo esc_html(number_format_i18n($summary['success'])); ?></strong></div>
                <div class="ffai-metric"><span>Erros</span><strong><?php echo esc_html(number_format_i18n($summary['error'])); ?></strong></div>
                <div class="ffai-metric"><span>Média</span><strong><?php echo esc_html(number_format_i18n($summary['avg_ms'])); ?> ms</strong></div>
                <div class="ffai-metric"><span>Tokens</span><strong><?php echo esc_html(number_format_i18n((int)$summary['input_tokens'] + (int)$summary['output_tokens'])); ?></strong></div>
                <div class="ffai-metric"><span>Custo estimado</span><strong><?php echo esc_html(number_format_i18n((float)$summary['estimated_cost_eur'], 4)); ?> €</strong></div>
              </div>
              <p style="margin-bottom:0;color:#64748b">Hoje: <?php echo esc_html(number_format_i18n($today)); ?> chamadas<?php if((int)$o['ai_daily_limit']>0): ?> de <?php echo esc_html(number_format_i18n((int)$o['ai_daily_limit'])); ?> permitidas<?php endif; ?>.</p>
            </div>
          </div>

          <div class="ffai-tabs" role="tablist">
            <button type="button" class="active" data-ffai-tab="general">Geral</button>
            <button type="button" data-ffai-tab="providers">Fornecedores</button>
            <button type="button" data-ffai-tab="modules">Módulos</button>
            <button type="button" data-ffai-tab="governance">Governação</button>
            <button type="button" data-ffai-tab="finops">FinOps</button>
            <button type="button" data-ffai-tab="diagnostics">Diagnóstico</button>
            <button type="button" data-ffai-tab="usage">Utilização</button>
          </div>

          <form method="post" autocomplete="off">
            <?php wp_nonce_field('fieldflow_ai_control','fieldflow_ai_nonce'); ?>
            <section class="ffai-panel active" data-ffai-panel="general">
              <div class="ffai-grid">
                <div class="ffai-card">
                  <div class="ffai-section-title"><div><h2>Motor principal</h2><p>Fornecedor usado quando um módulo está em “Herdar”.</p></div></div>
                  <p><label class="ffai-toggle"><input type="checkbox" name="ai_enabled" value="1" <?php checked(!empty($o['ai_enabled'])); ?>> Ativar IA operacional no FieldFlow</label></p>
                  <div class="ffai-field"><label for="ai_provider">Fornecedor principal</label><select id="ai_provider" name="ai_provider"><?php self::providerOptions((string)$o['ai_provider'], false); ?></select></div>
                  <div class="ffai-field" style="margin-top:14px"><label for="ai_fallback_provider">Fornecedor alternativo</label><select id="ai_fallback_provider" name="ai_fallback_provider"><?php self::providerOptions((string)$o['ai_fallback_provider'], false); ?></select><p class="ffai-help">Só é usado quando o principal falha e as credenciais do alternativo estão completas.</p></div>
                </div>
                <div class="ffai-card">
                  <h2>Limites e desempenho</h2>
                  <div class="ffai-fields">
                    <div class="ffai-field"><label>Timeout</label><input type="number" min="10" max="180" name="ai_timeout" value="<?php echo esc_attr((string)$o['ai_timeout']); ?>"><p class="ffai-help">Segundos por tentativa.</p></div>
                    <div class="ffai-field"><label>Tokens máximos</label><input type="number" min="50" max="12000" name="ai_default_max_tokens" value="<?php echo esc_attr((string)$o['ai_default_max_tokens']); ?>"></div>
                    <div class="ffai-field"><label>Limite diário</label><input type="number" min="0" max="100000" name="ai_daily_limit" value="<?php echo esc_attr((string)$o['ai_daily_limit']); ?>"><p class="ffai-help">0 significa ilimitado.</p></div>
                    <div class="ffai-field"><label>Retenção de logs</label><input type="number" min="7" max="3650" name="ai_log_retention_days" value="<?php echo esc_attr((string)$o['ai_log_retention_days']); ?>"><p class="ffai-help">Compatibilidade. A política efetiva é gerida no Centro de Privacidade.</p></div>
                  </div>
                </div>
              </div>
            </section>

            <section class="ffai-panel" data-ffai-panel="providers">
              <div class="ffai-grid">
                <?php self::renderGoogle($o, $diagnostics['google']); ?>
                <?php self::renderAzure($o, $diagnostics['azure']); ?>
                <?php self::renderOpenAI($o, $diagnostics['openai']); ?>
                <?php self::renderWebhook($o, $diagnostics['copilot']); ?>
              </div>
            </section>

            <section class="ffai-panel" data-ffai-panel="modules">
              <div class="ffai-card">
                <h2>Fornecedor por módulo</h2>
                <p>Escolhe “Herdar” para usar o motor principal. Desligar um módulo não afeta os restantes.</p>
                <div class="ffai-table-wrap"><table class="widefat striped ffai-module-table"><thead><tr><th>Módulo</th><th>Finalidade</th><th>Motor</th><th>Estado efetivo</th></tr></thead><tbody>
                <?php foreach($moduleRows as $key=>$row): $effective=Config::aiProviderForModule($key,$o); ?>
                  <tr><td><strong><?php echo esc_html($row[0]); ?></strong></td><td><?php echo esc_html($row[1]); ?></td><td><select name="ai_module_<?php echo esc_attr($key); ?>_provider"><?php self::providerOptions((string)$o['ai_module_'.$key.'_provider'], true); ?></select></td><td><span class="ffai-status <?php echo $effective!=='none'&&Config::aiProviderReady($effective,$o)?'ok':'warn'; ?>"><?php echo esc_html(AIFactory::providerLabel($effective)); ?></span></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
                <div class="ffai-danger" style="margin-top:16px">A opção Azure do routing geográfico continua a significar Azure Maps. Só esta página controla Azure OpenAI ou Microsoft Foundry.</div>
              </div>
            </section>

            <section class="ffai-panel" data-ffai-panel="governance">
              <div class="ffai-grid">
                <div class="ffai-card">
                  <span class="ffai-kicker">AI Governance 2.0</span>
                  <h2>Proteção de dados e instruções</h2>
                  <div class="ffai-fields">
                    <div class="ffai-field"><label>Modo</label><select name="ai_governance_mode"><option value="off" <?php selected($o['ai_governance_mode'],'off'); ?>>Desativado</option><option value="shadow" <?php selected($o['ai_governance_mode'],'shadow'); ?>>Observação, recomendado</option><option value="enforce" <?php selected($o['ai_governance_mode'],'enforce'); ?>>Aplicar políticas</option></select><p class="ffai-help">Em observação, mede riscos sem alterar pedidos.</p></div>
                    <div class="ffai-field"><label>Política de prompt injection</label><select name="ai_block_prompt_injection"><option value="1" <?php selected((int)$o['ai_block_prompt_injection'],1); ?>>Bloquear em modo Aplicar</option><option value="0" <?php selected((int)$o['ai_block_prompt_injection'],0); ?>>Apenas registar</option></select></div>
                    <div class="ffai-field wide"><label class="ffai-toggle"><input type="checkbox" name="ai_redact_personal_data" value="1" <?php checked(!empty($o['ai_redact_personal_data'])); ?>> Remover emails e telefones antes do envio, apenas em modo Aplicar</label></div>
                  </div>
                  <div class="ffai-note" style="margin-top:14px">Os prompts continuam a não ser guardados. O histórico conserva apenas hash, versão, indicadores de risco, tokens e custo.</div>
                </div>
                <div class="ffai-card">
                  <span class="ffai-kicker">FinOps</span>
                  <h2>Orçamento e custos</h2>
                  <div class="ffai-fields">
                    <div class="ffai-field"><label>Orçamento mensal global, EUR</label><input type="number" min="0" step="0.01" name="ai_monthly_budget_eur" value="<?php echo esc_attr((string)$o['ai_monthly_budget_eur']); ?>"><p class="ffai-help">0 significa sem bloqueio orçamental.</p></div>
                    <div class="ffai-field"><label>Custo atual do mês</label><input value="<?php echo esc_attr(number_format_i18n((float)$governance['month_cost_eur'], 6)); ?> €" readonly></div>
                    <div class="ffai-field"><label>Limite mensal por cliente, EUR</label><input type="number" min="0" step="0.01" name="ai_client_monthly_budget_eur" value="<?php echo esc_attr((string)$o['ai_client_monthly_budget_eur']); ?>"><p class="ffai-help">Aplica-se a cada cliente com attribution conhecida.</p></div>
                    <div class="ffai-field"><label>Limite mensal por projeto, EUR</label><input type="number" min="0" step="0.01" name="ai_project_monthly_budget_eur" value="<?php echo esc_attr((string)$o['ai_project_monthly_budget_eur']); ?>"><p class="ffai-help">Aplica-se a cada projeto com attribution conhecida.</p></div>
                    <div class="ffai-field"><label>Entrada por 1 milhão tokens</label><input type="number" min="0" step="0.000001" name="ai_input_cost_per_million_eur" value="<?php echo esc_attr((string)$o['ai_input_cost_per_million_eur']); ?>"></div>
                    <div class="ffai-field"><label>Saída por 1 milhão tokens</label><input type="number" min="0" step="0.000001" name="ai_output_cost_per_million_eur" value="<?php echo esc_attr((string)$o['ai_output_cost_per_million_eur']); ?>"></div>
                  </div>
                  <p class="ffai-help">Quando o fornecedor devolve tokens, o FieldFlow usa os valores reais. Nos restantes casos apresenta uma estimativa identificada como tal.</p>
                </div>
              </div>
            </section>

            <section class="ffai-panel" data-ffai-panel="finops">
              <div class="ffai-grid">
                <div class="ffai-card">
                  <span class="ffai-kicker">FinOps mensal</span>
                  <h2>Orçamento e alertas</h2>
                  <div class="ffai-metrics">
                    <div class="ffai-metric"><span>Custo</span><strong><?php echo esc_html(number_format_i18n((float)$governance['month_cost_eur'],4)); ?> €</strong></div>
                    <div class="ffai-metric"><span>Orçamento</span><strong><?php echo (float)$governance['monthly_budget_eur']>0 ? esc_html(number_format_i18n((float)$governance['monthly_budget_eur'],2)).' €' : 'Sem limite'; ?></strong></div>
                    <div class="ffai-metric"><span>Utilização</span><strong><?php echo esc_html(number_format_i18n((float)$governance['budget_usage_percent'],1)); ?>%</strong></div>
                    <div class="ffai-metric"><span>Patamar</span><strong><?php echo (int)$governance['budget_threshold']>0 ? esc_html((string)$governance['budget_threshold']).'%' : 'Normal'; ?></strong></div>
                  </div>
                  <p class="ffai-help">Alertas únicos por mês aos 70%, 85% e 100%. Em modo Aplicar, 100% mantém o bloqueio fail-closed já existente.</p>
                  <p><span class="ffai-status <?php echo !empty($governance['native_attribution'])?'ok':'warn'; ?>"><?php echo !empty($governance['native_attribution'])?'Attribution nativa ativa':'A aguardar migração de attribution'; ?></span></p>
                </div>
                <div class="ffai-card">
                  <span class="ffai-kicker">Privacidade</span>
                  <h2>O que o ledger guarda</h2>
                  <p>Cliente, projeto, feature, tipo de pedido, fornecedor, modelo, tokens, custo, duração, estado e hash/versão do prompt.</p>
                  <div class="ffai-note">O conteúdo do prompt e a resposta comercial continuam fora do ledger de FinOps.</div>
                </div>
              </div>
              <div class="ffai-grid" style="margin-top:18px">
                <?php self::renderFinopsBreakdown('Por cliente', (array)($finops['by_client'] ?? [])); ?>
                <?php self::renderFinopsBreakdown('Por projeto', (array)($finops['by_project'] ?? [])); ?>
                <?php self::renderFinopsBreakdown('Por feature', (array)($finops['by_feature'] ?? [])); ?>
                <?php self::renderFinopsBreakdown('Por fornecedor', (array)($finops['by_provider'] ?? [])); ?>
                <?php self::renderFinopsBreakdown('Por modelo', (array)($finops['by_model'] ?? [])); ?>
                <?php self::renderFinopsBreakdown('Por tipo de pedido', (array)($finops['by_kind'] ?? [])); ?>
              </div>
            </section>

            <section class="ffai-panel" data-ffai-panel="diagnostics">
              <div class="ffai-card">
                <h2>Teste de ligação</h2>
                <p>Guarda primeiro os campos do formulário e envia uma chamada mínima. O teste apresenta o erro técnico real, sem misturar licenciamento ou mapas.</p>
                <div class="ffai-field" style="max-width:360px;margin-bottom:14px"><label>Módulo usado no teste</label><select name="test_module"><option value="general">Geral</option><option value="briefings">Briefings</option><option value="routing">Routing</option><option value="analytics">Analytics</option></select></div>
                <div class="ffai-provider-actions">
                  <?php foreach(['google','azure','openai','copilot'] as $provider): ?>
                    <button class="button" name="fieldflow_ai_action" value="test:<?php echo esc_attr($provider); ?>">Testar <?php echo esc_html(AIFactory::providerLabel($provider)); ?></button>
                  <?php endforeach; ?>
                </div>
                <?php if(is_array($testResult)): ?>
                  <div class="ffai-test <?php echo !empty($testResult['ok'])?'ok':'no'; ?>">
                    <strong><?php echo esc_html((string)$testResult['label']); ?></strong>, <?php echo esc_html((string)($testResult['model'] ?: 'modelo não indicado')); ?>, <?php echo esc_html(number_format_i18n((int)$testResult['duration_ms'])); ?> ms.<br>
                    <?php echo esc_html((string)($testResult['ok'] ? $testResult['response'] : $testResult['message'])); ?>
                  </div>
                <?php endif; ?>
              </div>
              <div class="ffai-grid" style="margin-top:18px">
                <?php foreach($diagnostics as $provider=>$diag): ?>
                  <div class="ffai-card"><div class="ffai-provider-head"><div><h3><?php echo esc_html($diag['label']); ?></h3><p style="margin:0;color:#64748b"><?php echo esc_html($diag['model'] ?: 'Sem modelo ou deployment definido'); ?></p></div><span class="ffai-status <?php echo !empty($diag['ready'])?'ok':'no'; ?>"><?php echo !empty($diag['ready'])?'Pronto':'Incompleto'; ?></span></div></div>
                <?php endforeach; ?>
              </div>
            </section>

            <section class="ffai-panel" data-ffai-panel="usage">
              <div class="ffai-card">
                <div class="ffai-section-title"><div><h2>Histórico técnico</h2><p>Sem prompts, briefings ou conteúdos submetidos.</p></div></div>
                <div class="ffai-table-wrap"><table class="widefat striped"><thead><tr><th>Data</th><th>Módulo</th><th>Cliente</th><th>Projeto</th><th>Feature</th><th>Tipo</th><th>Fornecedor</th><th>Modelo</th><th>Estado</th><th>Duração</th><th>Tokens</th><th>Custo</th><th>Versão</th><th>Riscos</th><th>Erro</th></tr></thead><tbody>
                <?php if(!$recent): ?><tr><td colspan="15">Ainda não existem chamadas registadas.</td></tr><?php endif; ?>
                <?php foreach($recent as $log): ?>
                  <?php $riskFlags=json_decode((string)($log['risk_flags_json'] ?? ''),true); if(!is_array($riskFlags))$riskFlags=[]; ?>
                  <tr><td><?php echo esc_html((string)$log['created_at']); ?></td><td><?php echo esc_html((string)$log['module_key']); ?></td><td><?php echo esc_html((string)($log['client_id'] ?? 0)); ?></td><td><?php echo esc_html((string)($log['project_id'] ?? 0)); ?></td><td><?php echo esc_html((string)($log['feature_key'] ?? '')); ?></td><td><?php echo esc_html((string)($log['request_kind'] ?? '')); ?></td><td><?php echo esc_html(AIFactory::providerLabel((string)$log['provider'])); ?></td><td><?php echo esc_html((string)$log['model_name']); ?></td><td><span class="ffai-status <?php echo $log['status']==='success'?'ok':($log['status']==='blocked'?'warn':'no'); ?>"><?php echo esc_html((string)$log['status']); ?></span></td><td><?php echo esc_html(number_format_i18n((int)$log['duration_ms'])); ?> ms</td><td><?php echo esc_html(number_format_i18n((int)($log['input_tokens'] ?? 0)+(int)($log['output_tokens'] ?? 0))); ?> <small><?php echo esc_html((string)($log['usage_source'] ?? '')); ?></small></td><td><?php echo esc_html(number_format_i18n((float)($log['estimated_cost_eur'] ?? 0),6)); ?> €</td><td><?php echo esc_html((string)($log['prompt_version'] ?? '')); ?></td><td><?php echo esc_html(implode(', ',$riskFlags)); ?></td><td class="ffai-log-error"><?php echo esc_html((string)$log['error_message']); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
                <p><button class="button" name="fieldflow_ai_action" value="clear_logs" onclick="return confirm('Limpar o histórico técnico de IA?')">Limpar histórico</button></p>
              </div>
            </section>

            <div class="ffai-actions"><button class="button button-primary" name="fieldflow_ai_action" value="save">Guardar Centro de IA</button> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=routespro-privacy')); ?>">Centro de Privacidade</a> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=routespro-settings')); ?>">Abrir Settings gerais</a></div>
          </form>
        </div>
        <script>
        (function(){
          const buttons=[...document.querySelectorAll('[data-ffai-tab]')], panels=[...document.querySelectorAll('[data-ffai-panel]')];
          function openTab(id){buttons.forEach(b=>b.classList.toggle('active',b.dataset.ffaiTab===id));panels.forEach(p=>p.classList.toggle('active',p.dataset.ffaiPanel===id));try{localStorage.setItem('fieldflow_ai_tab',id)}catch(e){}}
          buttons.forEach(b=>b.addEventListener('click',()=>openTab(b.dataset.ffaiTab)));
          let initial='general';try{initial=localStorage.getItem('fieldflow_ai_tab')||initial}catch(e){}
          if(<?php echo is_array($testResult)?'true':'false'; ?>) initial='diagnostics';
          openTab(initial);
        })();
        </script>
        <?php
        AdminPage::close();
    }

    private static function saveFromPost(): void {
        $current = Config::all();
        $provider = sanitize_key((string)($_POST['ai_provider'] ?? 'none'));
        $fallback = sanitize_key((string)($_POST['ai_fallback_provider'] ?? 'none'));
        $data = [
            'ai_enabled'=>!empty($_POST['ai_enabled']) ? 1 : 0,
            'ai_provider'=>in_array($provider,self::PROVIDERS,true)?$provider:'none',
            'ai_fallback_provider'=>in_array($fallback,self::PROVIDERS,true)?$fallback:'none',
            'ai_timeout'=>max(10,min(180,absint($_POST['ai_timeout'] ?? 60))),
            'ai_default_max_tokens'=>max(50,min(12000,absint($_POST['ai_default_max_tokens'] ?? 1600))),
            'ai_daily_limit'=>max(0,min(100000,absint($_POST['ai_daily_limit'] ?? 0))),
            'ai_log_retention_days'=>max(7,min(3650,absint($_POST['ai_log_retention_days'] ?? 90))),
            'ai_governance_mode'=>in_array((string)($_POST['ai_governance_mode'] ?? 'shadow'),['off','shadow','enforce'],true)?sanitize_key((string)$_POST['ai_governance_mode']):'shadow',
            'ai_redact_personal_data'=>!empty($_POST['ai_redact_personal_data'])?1:0,
            'ai_block_prompt_injection'=>!empty($_POST['ai_block_prompt_injection'])?1:0,
            'ai_monthly_budget_eur'=>max(0,(float)($_POST['ai_monthly_budget_eur'] ?? 0)),
            'ai_client_monthly_budget_eur'=>max(0,(float)($_POST['ai_client_monthly_budget_eur'] ?? 0)),
            'ai_project_monthly_budget_eur'=>max(0,(float)($_POST['ai_project_monthly_budget_eur'] ?? 0)),
            'ai_input_cost_per_million_eur'=>max(0,(float)($_POST['ai_input_cost_per_million_eur'] ?? 0)),
            'ai_output_cost_per_million_eur'=>max(0,(float)($_POST['ai_output_cost_per_million_eur'] ?? 0)),
            'google_ai_model'=>sanitize_text_field((string)($_POST['google_ai_model'] ?? 'gemini-3.5-flash-lite')),
            'azure_openai_endpoint'=>esc_url_raw((string)($_POST['azure_openai_endpoint'] ?? '')),
            'azure_openai_deployment'=>sanitize_text_field((string)($_POST['azure_openai_deployment'] ?? '')),
            'azure_openai_vision_deployment'=>sanitize_text_field((string)($_POST['azure_openai_vision_deployment'] ?? '')),
            'azure_openai_api_version'=>sanitize_text_field((string)($_POST['azure_openai_api_version'] ?? '2024-06-01')),
            'openai_base_url'=>esc_url_raw((string)($_POST['openai_base_url'] ?? '')),
            'openai_model'=>sanitize_text_field((string)($_POST['openai_model'] ?? 'gpt-4o-mini')),
            'copilot_webhook_url'=>esc_url_raw((string)($_POST['copilot_webhook_url'] ?? '')),
        ];
        foreach(['briefings','routing','analytics','fronts_vision'] as $module){
            $value=sanitize_key((string)($_POST['ai_module_'.$module.'_provider'] ?? 'inherit'));
            $data['ai_module_'.$module.'_provider']=in_array($value,self::MODULE_PROVIDERS,true)?$value:'inherit';
        }
        foreach(['google_ai_key','azure_openai_key','openai_api_key','copilot_auth_header'] as $key){
            if (!empty($_POST['remove_'.$key])) {
                $data[$key]='';
            } else {
                $new=trim((string)wp_unslash($_POST[$key] ?? ''));
                if($new!=='') $data[$key]=sanitize_text_field($new);
                elseif(array_key_exists($key,$current)) $data[$key]=$current[$key];
            }
        }
        Config::mergeAndSave($data);
    }

    private static function renderFinopsBreakdown(string $title, array $rows): void { ?>
      <div class="ffai-card">
        <div class="ffai-section-title"><div><h3><?php echo esc_html($title); ?></h3><p>Mês corrente, agregado.</p></div></div>
        <div class="ffai-table-wrap"><table class="widefat striped"><thead><tr><th>Âmbito</th><th>Pedidos</th><th>Tokens</th><th>Custo</th></tr></thead><tbody>
          <?php if(!$rows): ?><tr><td colspan="4">Sem consumo atribuído neste mês.</td></tr><?php endif; ?>
          <?php foreach($rows as $row): $label=(string)($row['label'] ?? $row['id'] ?? 'Sem âmbito'); if($label==='' || $label==='0') $label='Sem attribution'; ?>
            <tr><td><?php echo esc_html($label); ?></td><td><?php echo esc_html(number_format_i18n((int)($row['requests'] ?? 0))); ?></td><td><?php echo esc_html(number_format_i18n((int)($row['tokens'] ?? 0))); ?></td><td><?php echo esc_html(number_format_i18n((float)($row['cost'] ?? 0),6)); ?> €</td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      </div>
    <?php }

    private static function providerOptions(string $selected, bool $inherit): void {
        $options = $inherit ? ['inherit'=>'Herdar motor principal'] : [];
        $options += ['none'=>'Sem IA','google'=>'Google Gemini','azure'=>'Azure OpenAI / Microsoft Foundry','openai'=>'OpenAI','copilot'=>'Webhook próprio'];
        foreach($options as $value=>$label) printf('<option value="%s" %s>%s</option>',esc_attr($value),selected($selected,$value,false),esc_html($label));
    }

    private static function secretField(string $key, array $o, string $constant): void {
        $has = trim((string)($o[$key] ?? '')) !== '';
        $defined = defined($constant);
        echo '<div class="ffai-secret-wrap"><input type="password" name="'.esc_attr($key).'" value="" placeholder="'.esc_attr($has ? Config::maskSecret((string)$o[$key]) : 'Introduzir chave').'" autocomplete="new-password"></div>';
        echo '<p class="ffai-help">'.($defined ? '<span class="ffai-constant">Gerida por '.esc_html($constant).' no wp-config.php.</span>' : ($has ? 'Chave guardada. Deixa vazio para manter.' : 'Ainda não configurada.')).'</p>';
        if($has && !$defined) echo '<label class="ffai-help"><input type="checkbox" name="remove_'.esc_attr($key).'" value="1"> Remover credencial guardada</label>';
    }

    private static function renderGoogle(array $o, array $diag): void { ?>
      <div class="ffai-provider"><div class="ffai-provider-head"><div><span class="ffai-kicker">Fornecedor</span><h2>Google Gemini</h2></div><span class="ffai-status <?php echo $diag['ready']?'ok':'no'; ?>"><?php echo $diag['ready']?'Pronto':'Por configurar'; ?></span></div><div class="ffai-fields"><div class="ffai-field"><label>Modelo</label><input name="google_ai_model" value="<?php echo esc_attr((string)$o['google_ai_model']); ?>" placeholder="gemini-3.5-flash-lite"><?php /* 9CJ certification marker: placeholder="gemini-2.5-flash-lite" (default until 2.2.611). */ ?><small class="ffai-help">Modelo efetivo: <?php echo esc_html(\RoutesPro\Services\GoogleAIProvider::resolveModel((string)$o['google_ai_model'])); ?>. Modelos descontinuados (1.5 e 2.0) e o antigo default 2.5 Flash-Lite passam automaticamente para um modelo atual; se a conta não tiver acesso ao 2.5 Flash ou Pro, o FieldFlow usa o sucessor.</small></div><div class="ffai-field"><label>API Key</label><?php self::secretField('google_ai_key',$o,'ROUTESPRO_GOOGLE_AI_KEY'); ?></div></div></div>
    <?php }

    private static function renderAzure(array $o, array $diag): void { ?>
      <div class="ffai-provider"><div class="ffai-provider-head"><div><span class="ffai-kicker">Fornecedor</span><h2>Azure OpenAI / Foundry</h2></div><span class="ffai-status <?php echo $diag['ready']?'ok':'no'; ?>"><?php echo $diag['ready']?'Pronto':'Por configurar'; ?></span></div><div class="ffai-fields"><div class="ffai-field wide"><label>Endpoint</label><input name="azure_openai_endpoint" value="<?php echo esc_attr((string)$o['azure_openai_endpoint']); ?>" placeholder="https://recurso.openai.azure.com ou endpoint completo do projeto"><p class="ffai-help">O caminho <code>/api/projects/...</code> é preservado quando usas Microsoft Foundry.</p></div><div class="ffai-field"><label>Deployment</label><input name="azure_openai_deployment" value="<?php echo esc_attr((string)$o['azure_openai_deployment']); ?>"></div><div class="ffai-field"><label>Deployment para leitura de imagens</label><input name="azure_openai_vision_deployment" value="<?php echo esc_attr((string)($o['azure_openai_vision_deployment'] ?? '')); ?>" placeholder="Vazio = o deployment acima"><small class="ffai-help">Usado na leitura de frentes por câmara. Tem de aceitar imagens (por exemplo gpt-4o, gpt-4.1 ou gpt-5).</small></div><div class="ffai-field"><label>API Version</label><input name="azure_openai_api_version" value="<?php echo esc_attr((string)$o['azure_openai_api_version']); ?>"></div><div class="ffai-field wide"><label>API Key</label><?php self::secretField('azure_openai_key',$o,'ROUTESPRO_AZURE_OPENAI_KEY'); ?></div></div></div>
    <?php }

    private static function renderOpenAI(array $o, array $diag): void { ?>
      <div class="ffai-provider"><div class="ffai-provider-head"><div><span class="ffai-kicker">Fornecedor</span><h2>OpenAI</h2></div><span class="ffai-status <?php echo $diag['ready']?'ok':'no'; ?>"><?php echo $diag['ready']?'Pronto':'Por configurar'; ?></span></div><div class="ffai-fields"><div class="ffai-field"><label>Modelo</label><input name="openai_model" value="<?php echo esc_attr((string)$o['openai_model']); ?>" placeholder="gpt-4o-mini"></div><div class="ffai-field"><label>Base URL</label><input name="openai_base_url" value="<?php echo esc_attr((string)$o['openai_base_url']); ?>" placeholder="https://api.openai.com"></div><div class="ffai-field wide"><label>API Key</label><?php self::secretField('openai_api_key',$o,'ROUTESPRO_OPENAI_API_KEY'); ?></div></div></div>
    <?php }

    private static function renderWebhook(array $o, array $diag): void { ?>
      <div class="ffai-provider"><div class="ffai-provider-head"><div><span class="ffai-kicker">Fornecedor</span><h2>Webhook próprio</h2></div><span class="ffai-status <?php echo $diag['ready']?'ok':'no'; ?>"><?php echo $diag['ready']?'Pronto':'Por configurar'; ?></span></div><div class="ffai-fields"><div class="ffai-field wide"><label>URL</label><input name="copilot_webhook_url" value="<?php echo esc_attr((string)$o['copilot_webhook_url']); ?>" placeholder="https://..."></div><div class="ffai-field wide"><label>Header de autenticação</label><?php self::secretField('copilot_auth_header',$o,'ROUTESPRO_COPILOT_AUTH_HEADER'); ?><p class="ffai-help">Formato: <code>Authorization: Bearer ...</code></p></div></div></div>
    <?php }
}
