<?php
namespace RoutesPro\Admin;

use RoutesPro\Catalog\ProductCatalogService as Catalog;
use RoutesPro\Vision\FrontsVisionService as Vision;
use RoutesPro\Vision\VisionUsageGuard;
use RoutesPro\Vision\VisionCostMeter;

if (!defined('ABSPATH')) exit;

/**
 * 9CO back office: Cardex e Catálogo → Leitura por câmara.
 * Provider status, per-client pilot switch and limits, a test reading with an uploaded
 * photo and the reading history per store.
 */
final class FrontsVisionAdmin {
    public const CONTRACT = 'fieldflow-fronts-vision-admin-v1';
    private const PAGE = 'routespro-product-cardex';

    public static function register_hooks(): void {
        add_action('admin_post_fieldflow_vision_settings', [self::class, 'handle_settings']);
        add_action('admin_post_fieldflow_vision_tariffs', [self::class, 'handle_tariffs']);
        add_action('admin_post_fieldflow_vision_reconcile_legacy', [self::class, 'handle_reconcile_legacy']);
        add_action('admin_post_fieldflow_vision_test', [self::class, 'handle_test']);
    }

    public static function render(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        Vision::registerPostType();
        echo '<div class="wrap ff-cat-wrap ff-vision-wrap">';
        Branding::render_header('Contagem inteligente de frentes', 'Câmara com orçamento controlado, ou contagem sem IA por seleção, escrita e ditado no formulário.');
        ProductCatalog::render_tabs('vision');
        ProductCatalog::styles();
        self::styles();
        self::render_flash();

        $status = Vision::providerStatus();
        $clients = self::clients();
        echo '<div class="ff-vision-grid">';
        echo '<section class="ff-vision-card"><h2>Fornecedor de IA</h2>';
        echo '<p><span class="ff-cat-badge ' . ($status['ready'] ? 'is-ok' : 'is-warn') . '">' . ($status['ready'] ? 'Pronto' : 'Por configurar') . '</span> <strong>' . esc_html($status['label']) . '</strong>' . ($status['model'] !== '' ? ' · ' . esc_html($status['model']) : '') . '</p>';
        echo '<p class="description">Escolhe o fornecedor do módulo "Leitura de frentes" em <a href="' . esc_url(admin_url('admin.php?page=routespro-ai')) . '">Configuração de IA</a>. Com Azure OpenAI, o deployment tem de aceitar imagens (por exemplo gpt-4o, gpt-4.1 ou gpt-5); podes indicar um deployment só para imagens. Com Google Gemini, o ecrã de confirmação mostra também uma caixa por frente contada.</p>';
        echo '<p class="description">Fotografias da prateleira são transmitidas ao fornecedor escolhido e descartadas após a leitura; novas fotos de referência adicionadas ao Catálogo são persistentes. Os modos texto e seleção não fazem pedidos ao modelo. A voz do browser ou do teclado pode depender de serviços externos do dispositivo.</p>';
        echo '</section>';

        echo '<section class="ff-vision-card"><h2>Operação e segurança de custos</h2>';
        echo '<p>O operativo abre <strong>Contar frentes</strong> e escolhe: seleção, escrita, voz ou câmara. Seleção e texto são locais, sem IA paga. A voz depende do dispositivo. A câmara só aparece com fornecedor pronto e quota autorizada.</p>';
        echo '<p><strong>Controlo de custos:</strong> orçamento mensal inicial de 0 € (IA bloqueada), máximo de leituras por dia e mês, e reserva conservadora por tentativa, antes da chamada ao fornecedor. Falhas e timeouts também usam quota. Sem tentativas automáticas em cascata nem troca automática de modelo.</p>';
        echo '<p class="description"><strong>Atenção:</strong> a reserva em euros é uma margem operacional configurável, <strong>não</strong> um custo real medido ao cêntimo e <strong>não garante</strong> um teto na fatura do fornecedor. Confirma preços, limites de implantação, quotas e alertas de faturação na Azure ou Google. Outros módulos de IA têm consumos independentes.</p></section>';
        echo '</div>';

        self::render_pricing($status);
        self::render_clients($clients);
        self::render_legacy_reconciliation($clients);
        self::render_test($clients, $status);
        self::render_history($clients);
        echo '</div>';
    }

    private static function render_pricing(array $status): void {
        $provider = (string)($status['provider'] ?? '');
        $model = (string)($status['model'] ?? '');
        $tariff = VisionCostMeter::rateFor($provider, $model);
        echo '<section class="ff-vision-card"><h2>FinOps de IA, tarifa do modelo de leitura</h2>';
        echo '<p>Os tokens são medidos pela resposta do fornecedor, incluindo o processamento de imagens quando reportado. O custo é uma <strong>estimativa</strong> baseada nas tarifas que configuras. Nunca é uma fatura Azure.</p>';
        echo '<p><strong>Fornecedor:</strong> ' . esc_html($status['label'] ?? $provider) . ' · <strong>deployment/modelo:</strong> ' . esc_html($model ?: 'Sem modelo') . '</p>';
        if (!in_array($provider, VisionCostMeter::PROVIDERS, true) || $model === '') {
            echo '<p class="notice notice-warning inline">Configura primeiro o modelo de leitura de frentes no Centro de IA para ativar a tarifação.</p></section>';
            return;
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="fieldflow_vision_tariffs">';
        wp_nonce_field('fieldflow_vision_tariffs', 'fieldflow_vision_nonce');
        echo '<input type="hidden" name="provider" value="' . esc_attr($provider) . '">';
        echo '<input type="hidden" name="model" value="' . esc_attr($model) . '">';
        echo '<div class="ff-cat-filters"><label><span>Entrada, EUR por 1 milhão de tokens</span><input type="number" step="0.000001" min="0.000001" max="1000" name="input_rate" required value="' . esc_attr($tariff ? number_format((float)$tariff['input_eur_per_million'],6,'.','') : '') . '" placeholder="Tarifa confirmada na Azure"></label>';
        echo '<label><span>Saída, EUR por 1 milhão de tokens</span><input type="number" step="0.000001" min="0.000001" max="1000" name="output_rate" required value="' . esc_attr($tariff ? number_format((float)$tariff['output_eur_per_million'],6,'.','') : '') . '" placeholder="Tarifa confirmada na Azure"></label>';
        echo '<button class="button button-primary">Guardar tarifas deste modelo</button></div></form>';
        echo '<p class="description">Usa os preços e o câmbio efetivos do teu recurso Azure para valores em EUR. A tarifa é específica deste modelo, não é aplicada automaticamente a outros deployments. Os preços não são alterados pelo FieldFlow; não existe chamada adicional à IA. Sem tarifa, a aplicação assinala custo por calcular, nunca custo zero.</p>';
        if ($tariff) echo '<p class="description"><strong>Tarifa verificada manualmente em:</strong> ' . esc_html($tariff['updated_at']) . '. As alterações futuras não reescrevem os custos históricos.</p>';
        echo '</section>';
    }

    private static function render_clients(array $clients): void {
        $counts = Catalog::countsByClient();
        $costs = VisionCostMeter::monthByClient();
        echo '<section class="ff-vision-card"><h2>Orçamento e quotas de leitura por cliente</h2>';
        echo '<p class="description">Tudo começa bloqueado por defeito. A reserva é debitada ANTES da leitura, e na 9D0 é parcialmente libertada apenas após tokens medidos e tarifa configurada, com margem de segurança de 30%. Tentativas falhadas ou sem medição continuam integralmente reservadas. As reservas anteriores são regularizadas separadamente, mediante confirmação do gestor. Os limites aplicam-se aos testes.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="fieldflow_vision_settings">';
        wp_nonce_field('fieldflow_vision_settings', 'fieldflow_vision_nonce');
        echo '<div class="ff-vision-table-wrap"><table class="widefat striped ff-vision-table"><thead><tr><th>Cliente</th><th>Ativação</th><th>Share Space</th><th>Orçamento mensal (€)</th><th>Reserva por tentativa (€)</th><th>Máx. por dia</th><th>Máx. por mês</th><th>Imagens de referência</th><th>Fotos de referência no terreno</th><th>Quota utilizada</th><th>IA medida no mês</th><th>Catálogo</th></tr></thead><tbody>';
        // 9CQ historical label: 'questions' => 'Nas perguntas com a opção ligada'
        foreach ($clients as $id => $name) {
            $settings = Vision::clientSettings((int)$id);
            $usage = VisionUsageGuard::summary((int)$id, $settings);
            $summary = !empty($counts[$id]) ? Catalog::summary((int)$id) : ['total' => 0, 'with_images' => 0];
            $prefix = 'clients[' . (int)$id . ']';
            echo '<tr><td><strong>' . esc_html($name) . '</strong></td>';
            echo '<td data-label="Ativação"><select name="' . esc_attr($prefix) . '[mode]">';
            foreach (['questions' => 'Perguntas autorizadas', 'on' => 'Ligada (inclui Share Space)', 'off' => 'Desligada'] as $mode => $label) echo '<option value="' . esc_attr($mode) . '"' . selected($settings['mode'], $mode, false) . '>' . esc_html($label) . '</option>';
            echo '</select></td>';
            echo '<td data-label="Share Space"><label><input type="hidden" name="' . esc_attr($prefix) . '[share_space]" value="0"><input type="checkbox" name="' . esc_attr($prefix) . '[share_space]" value="1"' . checked($settings['share_space'], true, false) . '> Incluir</label></td>';
            echo '<td data-label="Orçamento mensal (€)"><input type="number" min="0" max="10000" step="0.01" name="' . esc_attr($prefix) . '[monthly_budget_eur]" value="' . esc_attr(number_format($settings['monthly_budget_cents'] / 100, 2, '.', '')) . '" class="small-text">' . (empty($settings['monthly_budget_cents']) ? '<br><span class="ff-cat-badge is-warn">IA bloqueada</span>' : '') . '</td>';
            echo '<td data-label="Reserva por tentativa (€)"><input type="number" min="0.01" max="100" step="0.01" name="' . esc_attr($prefix) . '[reservation_eur]" value="' . esc_attr(number_format($settings['reservation_cents'] / 100, 2, '.', '')) . '" class="small-text"></td>';
            echo '<td data-label="Máx. por dia"><input type="number" min="0" max="500" name="' . esc_attr($prefix) . '[daily_limit]" value="' . (int)$settings['daily_limit'] . '" class="small-text"></td>';
            echo '<td data-label="Máx. por mês"><input type="number" min="0" max="10000" name="' . esc_attr($prefix) . '[monthly_limit]" value="' . (int)$settings['monthly_limit'] . '" class="small-text"></td>';
            echo '<td data-label="Referências enviadas à IA"><input type="number" min="0" max="3" name="' . esc_attr($prefix) . '[reference_images]" value="' . (int)min(3,$settings['reference_images']) . '" class="small-text"></td>';
            echo '<td data-label="Fotos no terreno"><input type="hidden" name="' . esc_attr($prefix) . '[field_references]" value="0"><label><input type="checkbox" name="' . esc_attr($prefix) . '[field_references]" value="1"' . checked($settings['field_references'], true, false) . '> Permitir</label></td>';
            echo '<td data-label="Quota utilizada">' . (int)$usage['daily_requests'] . '/' . (int)$usage['daily_limit'] . ' hoje<br>' . (int)$usage['monthly_requests'] . '/' . (int)$usage['monthly_limit'] . ' no mês<br><strong>' . esc_html(number_format($usage['reserved_cents'] / 100, 2, ',', ' ')) . ' €</strong> / ' . esc_html(number_format($usage['budget_cents'] / 100, 2, ',', ' ')) . ' € de consumo protegido<br><span class="ff-cat-muted">' . (int)$usage['priced_settlements'] . ' liquidadas · ' . esc_html(number_format($usage['released_cents'] / 100, 2, ',', ' ')) . ' € libertados (novas)</span></td>';
            $cost = $costs[$id] ?? null;
            if ($cost) {
                $priced = (int)$cost['priced_calls'];
                $notPriced = (int)$cost['unpriced_calls'] + (int)$cost['unmetered_calls'] + (int)$cost['legacy_calls'];
                $priceCaption = $priced > 0
                    ? ('Estimativa' . ($notPriced ? ' parcial' : '') . ': ' . number_format((float)$cost['estimate_eur'], 5, ',', ' ') . ' €')
                    : 'Custo por calcular';
                echo '<td data-label="IA medida"><strong>' . esc_html($priceCaption) . '</strong><br>'
                    . (int)$cost['provider_input_tokens'] . ' entrada / ' . (int)$cost['provider_output_tokens'] . ' saída (tokens)<br>'
                    . $priced . ' tarifadas, ' . (int)$cost['unpriced_calls'] . ' sem tarifa, '
                    . (int)$cost['unmetered_calls'] . ' sem tokens, ' . (int)$cost['legacy_calls'] . ' anteriores</td>';
            } else echo '<td data-label="IA medida">Sem utilização registada</td>';
            echo '<td data-label="Catálogo">' . (int)$summary['with_images'] . ' / ' . (int)$summary['total'] . '</td></tr>';
        }
        if (!$clients) echo '<tr><td colspan="12">Ainda não existem clientes.</td></tr>';
        echo '</tbody></table></div>';
        echo '<p><button class="button button-primary">Guardar orçamentos e quotas</button></p></form></section>';
    }


    /** Manager-confirmed correction, never an automatic reset of past billable attempts. */
    private static function render_legacy_reconciliation(array $clients): void {
        $eligible=[];
        foreach ($clients as $id=>$name) {
            $sum=VisionUsageGuard::summary((int)$id);
            if ($sum['legacy_total_cents'] > 0 && !$sum['legacy_reconciled_at']) $eligible[(int)$id]=[$name,$sum];
        }
        if (!$eligible) return;
        echo '<section class="ff-vision-card"><h2>Regularizar reservas antigas, mediante verificação</h2>';
        echo '<p class="description">A 2.2.623 mantinha a reserva integral das leituras anteriores. Este procedimento é <strong>manual e auditável</strong>. Compara os tokens e a faturação no Azure antes de indicar o custo histórico confirmado. O valor não é obtido automaticamente nem constitui fatura. Uma regularização só pode acontecer uma vez por cliente e mês. Não afeta quotas, histórico nem leituras recentes.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return window.confirm(&quot;Confirmas que verificaste o valor histórico no Azure? A regularização só pode ser efetuada uma vez neste mês.&quot;);">';
        echo '<input type="hidden" name="action" value="fieldflow_vision_reconcile_legacy">';
        wp_nonce_field('fieldflow_vision_reconcile_legacy', 'fieldflow_vision_nonce');
        echo '<div class="ff-cat-filters"><label><span>Cliente e reserva antiga</span><select name="client_id" required>';
        foreach($eligible as $id=>$data) echo '<option value="' . (int)$id . '">' . esc_html($data[0] . ' · ' . number_format($data[1]['legacy_total_cents']/100,2,',',' ') . ' €') . '</option>';
        echo '</select></label><label><span>Custo histórico confirmado (€)</span><input name="verified_eur" type="number" required min="0.00" max="10000" step="0.01" placeholder="Valor verificado, não estimativa automática"></label>';
        echo '<label><span>Origem da verificação</span><input name="reason" maxlength="300" required minlength="12" placeholder="Ex.: Azure Cost Analysis de outubro, comprovado" type="text"></label></div>';
        echo '<p><label><input type="checkbox" name="confirmed" value="1" required> Confirmo que comparei este valor com os dados Azure. A diferença entre a reserva e o valor indicado será libertada.</label></p>';
        echo '<p><button type="submit" class="button button-primary">Regularizar reserva histórica, uma só vez</button></p></form></section>';
    }

    public static function handle_reconcile_legacy(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_vision_reconcile_legacy', 'fieldflow_vision_nonce');
        if (empty($_POST['confirmed'])) {
            self::flash('error','Confirma explicitamente a verificação externa antes de regularizar.');
        } else {
            try {
                $clientId=absint($_POST['client_id']??0);
                $verified=self::amountCents(wp_unslash($_POST['verified_eur']??''));
                if (!isset(self::clients()[$clientId]) || !preg_match('/^\d+(?:[.,]\d{1,2})?$/', trim((string)($_POST['verified_eur']??'')))) {
                    throw new \InvalidArgumentException('Cliente ou valor inválido.');
                }
                $reason=sanitize_text_field((string)wp_unslash($_POST['reason']??''));
                VisionUsageGuard::reconcileLegacy($clientId,$verified,$reason,get_current_user_id());
                self::flash('success','Reserva histórica regularizada. Limites diários e mensais preservados.');
            } catch (\Throwable $e) { self::flash('error',$e->getMessage()); }
        }
        wp_safe_redirect(admin_url('admin.php?page='.self::PAGE.'&tab=vision'));
        exit;
    }

    private static function render_test(array $clients, array $status): void {
        $clientId = absint($_GET['vision_client'] ?? 0);
        if (!isset($clients[$clientId])) {
            foreach ($clients as $id => $name) if (Vision::clientEnabled((int)$id)) { $clientId = (int)$id; break; }
        }
        if (!isset($clients[$clientId])) $clientId = $clients ? (int)array_key_first($clients) : 0;
        echo '<section class="ff-vision-card" id="ff-vision-test"><h2>Testar leitura com uma fotografia</h2>';
        echo '<p class="description">Usa uma foto real de prateleira para calibrar o piloto antes de o ligar aos operativos. Conta como uma leitura do cliente.</p>';
        echo '<form method="get" class="ff-cat-filters"><input type="hidden" name="page" value="' . esc_attr(self::PAGE) . '"><input type="hidden" name="tab" value="vision"><label><span>Cliente</span><select name="vision_client" onchange="this.form.submit()">';
        foreach ($clients as $id => $name) echo '<option value="' . (int)$id . '"' . selected($id, $clientId, false) . '>' . esc_html($name . (Vision::clientEnabled((int)$id) ? '' : ' (desligado)')) . '</option>';
        echo '</select></label></form>';
        $usageError = '';
        if ($clientId > 0) {
            try { VisionUsageGuard::check($clientId, Vision::clientSettings($clientId)); }
            catch (\RuntimeException $e) { $usageError = $e->getMessage(); }
        }
        $index = $clientId ? Catalog::clientIndex($clientId) : [];
        if (!$index) {
            echo '<div class="ff-cat-empty">O catálogo deste cliente está vazio.</div></section>';
            return;
        }
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="fieldflow_vision_test"><input type="hidden" name="client_id" value="' . (int)$clientId . '">';
        wp_nonce_field('fieldflow_vision_test', 'fieldflow_vision_nonce');
        echo '<div class="ff-vision-products">';
        foreach (['own' => 'Marca', 'competitor' => 'Concorrência'] as $kind => $title) {
            $rows = array_filter($index, static function ($row) use ($kind) { return $row['kind'] === $kind && !empty($row['active']); });
            if (!$rows) continue;
            echo '<fieldset><legend>' . esc_html($title) . '</legend><div class="ff-vision-checks">';
            foreach ($rows as $row) echo '<label><input type="checkbox" name="product_ids[]" value="' . (int)$row['id'] . '"> ' . esc_html($row['name']) . ($row['image_count'] ? '' : ' <em>(sem imagem)</em>') . '</label>';
            echo '</div></fieldset>';
        }
        echo '</div>';
        echo '<p><label><strong>Fotografia da prateleira</strong><br><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label></p>';
        echo '<p><button class="button button-primary"' . ($status['ready'] && Vision::clientEnabled($clientId) && $usageError === '' ? '' : ' disabled') . '>Ler fotografia</button>';
        if (!Vision::clientEnabled($clientId)) echo ' <span class="description">Liga a leitura para este cliente acima.</span>';
        elseif (!$status['ready']) echo ' <span class="description">Configura o fornecedor de IA.</span>';
        elseif ($usageError !== '') echo ' <span class="description">' . esc_html($usageError) . '</span>';
        echo '</p></form>';
        $result = get_transient('fieldflow_vision_test_' . get_current_user_id());
        if (is_array($result)) {
            delete_transient('fieldflow_vision_test_' . get_current_user_id());
            self::render_result($result);
        }
        echo '</section>';
    }

    private static function render_result(array $result): void {
        echo '<div class="ff-vision-result"><div class="ff-vision-result-head"><strong>Resultado</strong><span>' . esc_html($result['provider'] . ($result['model'] ? ' · ' . $result['model'] : '')) . ' · ' . number_format((int)$result['duration_ms'] / 1000, 1, ',', '') . ' s</span></div>';
        if (!empty($result['photo_url'])) echo '<a href="' . esc_url($result['photo_url']) . '" target="_blank" rel="noopener"><img src="' . esc_url($result['photo_url']) . '" alt="Fotografia lida" class="ff-vision-photo"></a>';
        echo '<table class="widefat striped"><thead><tr><th>Produto</th><th>Frentes</th><th>Confiança</th><th>Imagem de referência</th></tr></thead><tbody>';
        foreach ((array)$result['items'] as $item) {
            echo '<tr><td>' . esc_html($item['name']) . '</td><td><strong>' . ($item['fronts'] === null ? 'Rever' : (int)$item['fronts']) . '</strong></td><td>' . esc_html(self::confidence((float)$item['confidence'])) . '</td><td>' . (!empty($item['has_reference']) ? 'Sim' : 'Não') . '</td></tr>';
        }
        echo '</tbody></table>';
        if (!empty($result['unidentified'])) echo '<p><strong>' . (int)$result['unidentified'] . '</strong> frente(s) não identificada(s).</p>';
        if (!empty($result['notes'])) echo '<p class="description">Nota da IA: ' . esc_html($result['notes']) . '</p>';
        echo '</div>';
    }

    private static function render_history(array $clients): void {
        $filters = [
            'client_id' => absint($_GET['h_client'] ?? 0),
            'location_id' => absint($_GET['h_location'] ?? 0),
            'status' => sanitize_key((string)($_GET['h_status'] ?? '')),
        ];
        $page = max(1, absint($_GET['h_page'] ?? 1));
        $history = Vision::history($filters, $page, 25);
        echo '<section class="ff-vision-card" id="ff-vision-history"><h2>Histórico de leituras por loja</h2>';
        echo '<form method="get" class="ff-cat-filters"><input type="hidden" name="page" value="' . esc_attr(self::PAGE) . '"><input type="hidden" name="tab" value="vision">';
        echo '<label><span>Cliente</span><select name="h_client"><option value="">Todos</option>';
        foreach ($clients as $id => $name) echo '<option value="' . (int)$id . '"' . selected($id, $filters['client_id'], false) . '>' . esc_html($name) . '</option>';
        echo '</select></label><label><span>Loja (ID)</span><input type="number" min="0" name="h_location" value="' . ($filters['location_id'] ?: '') . '"></label>';
        echo '<label><span>Estado</span><select name="h_status"><option value="">Todos</option><option value="confirmed"' . selected($filters['status'], 'confirmed', false) . '>Confirmadas</option><option value="detected"' . selected($filters['status'], 'detected', false) . '>Sem confirmação</option></select></label><button class="button">Filtrar</button></form>';
        if (!$history['rows']) {
            echo '<div class="ff-cat-empty">Ainda não há leituras' . (array_filter($filters) ? ' com estes filtros' : '') . '.</div></section>';
            return;
        }
        $locations = self::location_names(array_column($history['rows'], 'location_id'));
        echo '<div class="ff-vision-table-wrap"><table class="widefat striped ff-vision-table"><thead><tr><th>Data</th><th>Loja</th><th>Operativo</th><th>Pergunta</th><th>Fotografia</th><th>Lido → confirmado</th><th>Correções</th><th>Fornecedor</th></tr></thead><tbody>';
        foreach ($history['rows'] as $row) {
            $user = $row['user_id'] ? get_userdata($row['user_id']) : null;
            $confirmedByKey = [];
            foreach ($row['confirmed'] as $c) if (is_array($c)) $confirmedByKey[(string)$c['key']] = (int)$c['fronts'];
            $lines = [];
            $prev = (array)($row['detected']['previous_visit']['items'] ?? []);
            foreach ((array)($row['detected']['items'] ?? []) as $item) {
                if (!is_array($item)) continue;
                $detected = $item['fronts'] === null ? null : (int)$item['fronts'];
                $final = $confirmedByKey[(string)$item['key']] ?? null;
                $previous = array_key_exists((string)$item['key'], $prev) ? (int)$prev[(string)$item['key']] : null;
                $delta = $previous !== null && $final !== null ? $final - $previous : null;
                $lines[] = esc_html($item['name']) . ': <strong>' . ($detected === null ? 'Por rever' : $detected) . '</strong>' . ($final !== null ? ($final === $detected ? ' ✓' : ' → <strong>' . (int)$final . '</strong>') : '')
                    . ($delta !== null ? '<br><small>Visita fechada anterior: ' . $previous . ' | variação: ' . ($delta > 0 ? '+' : '') . $delta . '</small>' : '');
            }
            echo '<tr><td data-label="Data">' . esc_html(mysql2date('d/m/Y H:i', $row['created_at'])) . '</td>';
            echo '<td data-label="Loja">' . esc_html($locations[$row['location_id']] ?? ('#' . $row['location_id'])) . '</td>';
            echo '<td data-label="Operativo">' . esc_html($user ? $user->display_name : ('#' . $row['user_id'])) . '</td>';
            echo '<td data-label="Pergunta">' . esc_html($row['scope_label'] !== '' ? $row['scope_label'] : $row['question_key']) . '</td>';
            echo '<td data-label="Fotografia">' . ($row['photo_url'] ? '<a href="' . esc_url($row['photo_url']) . '" target="_blank" rel="noopener"><img src="' . esc_url($row['photo_url']) . '" alt="" class="ff-vision-thumb" loading="lazy"></a>' : (($row['capture'] ?? '') === 'live' ? '<span class="ff-cat-badge">Ao vivo</span><br><span class="ff-cat-muted">sem fotografia guardada</span>' : '—')) . '</td>';
            echo '<td data-label="Lido → confirmado" class="ff-vision-lines">' . ($lines ? implode('<br>', $lines) : '—') . (!empty($row['detected']['unidentified']) ? '<br><em>' . (int)$row['detected']['unidentified'] . ' não identificada(s)</em>' : '') . '</td>';
            echo '<td data-label="Correções">' . ($row['status'] === 'confirmed' ? (int)$row['corrections'] : '<span class="ff-cat-badge is-warn">Sem confirmação</span>') . '</td>';
            $priceLabel = $row['ai_cost_status'] === 'priced'
                ? ('Estimativa: ' . number_format((float)$row['ai_estimated_cost_eur'], 5, ',', '') . ' €')
                : ($row['ai_cost_status'] === 'tariff_missing' ? 'Tarifa por configurar'
                    : ($row['ai_cost_status'] === 'unmetered' ? 'Fornecedor sem tokens' : 'Leitura anterior à medição 9CZ'));
            echo '<td data-label="Fornecedor">' . esc_html($row['provider'] . ($row['model'] ? ' · ' . $row['model'] : '')) . '<br><span class="ff-cat-muted">' . number_format($row['duration_ms'] / 1000, 1, ',', '') . ' s · ' . esc_html($priceLabel) . '</span></td></tr>';
        }
        echo '</tbody></table></div>';
        if ($history['pages'] > 1) {
            echo '<div class="ff-cat-pages"><span>' . (int)$history['total'] . ' leitura(s)</span>';
            for ($p = 1; $p <= $history['pages']; $p++) {
                if ($p === $history['page']) echo '<strong>' . $p . '</strong>';
                else echo '<a href="' . esc_url(add_query_arg(array_filter(['page' => self::PAGE, 'tab' => 'vision', 'h_client' => $filters['client_id'], 'h_location' => $filters['location_id'], 'h_status' => $filters['status'], 'h_page' => $p]), admin_url('admin.php'))) . '#ff-vision-history">' . $p . '</a>';
            }
            echo '</div>';
        }
        echo '</section>';
    }

    /** Decimal EUR from an admin text/number input to integer cents. Invalid => zero (safe). */
    private static function amountCents($value): int {
        $text = str_replace(',', '.', trim((string)$value));
        if (!preg_match('/^\d{1,5}(?:\.\d{1,2})?$/', $text)) return 0;
        return max(0, min(1000000, (int)round((float)$text * 100)));
    }

    public static function handle_tariffs(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_vision_tariffs', 'fieldflow_vision_nonce');
        $status = Vision::providerStatus();
        $provider = sanitize_key((string)($_POST['provider'] ?? ''));
        $model = VisionCostMeter::cleanModel((string)($_POST['model'] ?? ''));
        if ($provider !== $status['provider'] || $model !== $status['model']) {
            self::flash('error', 'O modelo de leitura mudou. Atualiza a página antes de guardar a tarifa.');
        } else {
            try {
                VisionCostMeter::saveRate($provider, $model, $_POST['input_rate'] ?? '', $_POST['output_rate'] ?? '');
                self::flash('success', 'Tarifas guardadas. Os custos futuros ficam estimados com os tokens devolvidos pelo fornecedor.');
            } catch (\Throwable $e) { self::flash('error', $e->getMessage()); }
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&tab=vision'));
        exit;
    }

    public static function handle_settings(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_vision_settings', 'fieldflow_vision_nonce');
        $posted = is_array($_POST['clients'] ?? null) ? $_POST['clients'] : [];
        foreach (array_keys(self::clients()) as $clientId) {
            $row = is_array($posted[$clientId] ?? null) ? wp_unslash($posted[$clientId]) : [];
            Vision::saveClientSettings((int)$clientId, [
                'mode' => sanitize_key((string)($row['mode'] ?? 'questions')),
                'enabled' => sanitize_key((string)($row['mode'] ?? '')) === 'on',
                'share_space' => !empty($row['share_space']),
                'monthly_limit' => absint($row['monthly_limit'] ?? 300),
                'daily_limit' => absint($row['daily_limit'] ?? 8),
                'monthly_budget_cents' => self::amountCents($row['monthly_budget_eur'] ?? '0'),
                'reservation_cents' => self::amountCents($row['reservation_eur'] ?? '1.00'),
                'reference_images' => min(3, absint($row['reference_images'] ?? 3)),
                'field_references' => !empty($row['field_references']),
            ]);
        }
        self::flash('success', 'Definições da leitura por câmara guardadas.');
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&tab=vision'));
        exit;
    }

    public static function handle_test(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_vision_test', 'fieldflow_vision_nonce');
        $clientId = absint($_POST['client_id'] ?? 0);
        try {
            $ids = array_map('absint', (array)($_POST['product_ids'] ?? []));
            if (!$ids) throw new \RuntimeException('Escolhe os produtos que estão na fotografia.');
            if (function_exists('set_time_limit')) @set_time_limit(150);
            $result = Vision::adminTestRead($clientId, $ids, $_FILES['photo'] ?? []);
            set_transient('fieldflow_vision_test_' . get_current_user_id(), $result, 600);
            self::flash('success', 'Leitura concluída.');
        } catch (\Throwable $e) {
            self::flash('error', $e->getMessage());
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&tab=vision&vision_client=' . $clientId . '#ff-vision-test'));
        exit;
    }

    private static function confidence(float $value): string {
        if ($value >= 0.8) return 'Alta (' . (int)round($value * 100) . '%)';
        if ($value >= 0.5) return 'Média (' . (int)round($value * 100) . '%)';
        return 'Baixa (' . (int)round($value * 100) . '%)';
    }

    private static function flash(string $type, string $text): void {
        set_transient('fieldflow_vision_flash_' . get_current_user_id(), ['type' => $type, 'text' => $text], 120);
    }

    private static function render_flash(): void {
        $key = 'fieldflow_vision_flash_' . get_current_user_id();
        $flash = get_transient($key);
        if (!is_array($flash) || empty($flash['text'])) return;
        delete_transient($key);
        echo '<div class="notice ' . (($flash['type'] ?? '') === 'error' ? 'notice-error' : 'notice-success') . ' is-dismissible"><p>' . esc_html((string)$flash['text']) . '</p></div>';
    }

    private static function clients(): array {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT id, name FROM ' . $wpdb->prefix . 'routespro_clients ORDER BY name ASC, id ASC', ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $row) $out[(int)$row['id']] = (string)$row['name'];
        return $out;
    }

    private static function location_names(array $ids): array {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if (!$ids) return [];
        $rows = $wpdb->get_results($wpdb->prepare('SELECT id, name FROM ' . $wpdb->prefix . 'routespro_locations WHERE id IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')', ...$ids), ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $row) $out[(int)$row['id']] = (string)$row['name'];
        return $out;
    }

    private static function styles(): void {
        echo '<style>
.ff-vision-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px;margin:0 0 14px}
.ff-vision-card{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:14px 16px;margin:0 0 14px}
.ff-vision-card h2{margin:0 0 8px;font-size:16px}
.ff-vision-steps{margin:0 0 8px 18px}
.ff-vision-table-wrap{overflow:auto}
.ff-vision-table td{vertical-align:top}
.ff-vision-thumb{width:64px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0}
.ff-vision-products{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px}
.ff-vision-products fieldset{border:1px solid #e2e8f0;border-radius:10px;padding:8px 12px}
.ff-vision-checks{display:flex;flex-direction:column;gap:4px;max-height:220px;overflow:auto}
.ff-vision-result{margin-top:12px;border-top:1px solid #e2e8f0;padding-top:12px}
.ff-vision-result-head{display:flex;justify-content:space-between;gap:10px;margin-bottom:8px}
.ff-vision-photo{max-width:min(420px,100%);border-radius:10px;border:1px solid #e2e8f0;margin-bottom:10px}
.ff-vision-lines{min-width:220px}
@media(max-width:782px){.ff-vision-table thead{display:none}.ff-vision-table,.ff-vision-table tbody,.ff-vision-table tr,.ff-vision-table td{display:block;width:auto}.ff-vision-table tr{padding:8px 0;border-bottom:1px solid #e2e8f0}.ff-vision-table td[data-label]::before{content:attr(data-label) ": ";color:#64748b;font-size:12px}}
</style>';
    }
}
