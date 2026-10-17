<?php
namespace RoutesPro\Admin;

use RoutesPro\Expenses\ExpenseService;
use RoutesPro\Expenses\ExpensesController;
use RoutesPro\Expenses\ExpenseStatementService;
use RoutesPro\Expenses\ExpenseSettlementService;
use RoutesPro\Support\AdminPage;

if (!defined('ABSPATH')) exit;

class Expenses {
    public const EXPENSE_STATEMENT_MOBILE_TICKET_CONTRACT = 'fieldflow-expense-statement-mobile-ticket-delivery-v1';
    private const EXPENSE_STATEMENT_TICKET_TTL = 900;

    public static function register_hooks(): void {
        add_action('admin_post_fieldflow_expense_export', [self::class, 'export']);
        add_action('admin_post_fieldflow_expense_export_zip', [self::class, 'exportZip']);
        add_action('admin_post_fieldflow_expense_statement_export', [self::class, 'exportStatement']);
        add_action('admin_post_fieldflow_expense_status', [self::class, 'updateStatus']);
        add_action('admin_post_fieldflow_expense_bulk_paid', [self::class, 'bulkMarkPaid']);
        add_action('admin_post_fieldflow_expense_receipt', [self::class, 'serveReceipt']);
    }

    public static function render(): void {
        if (!current_user_can('routespro_manage')) return;
        global $wpdb;
        $params = [
            'user_id' => absint($_GET['user_id'] ?? 0),
            'month' => sanitize_text_field((string)($_GET['month'] ?? '')),
            'date_from' => sanitize_text_field((string)($_GET['date_from'] ?? '')),
            'date_to' => sanitize_text_field((string)($_GET['date_to'] ?? '')),
            'category' => sanitize_key((string)($_GET['category'] ?? '')),
            'status' => sanitize_key((string)($_GET['status'] ?? '')),
            'search' => sanitize_text_field((string)($_GET['search'] ?? '')),
        ];
        $page = max(1, absint($_GET['paged'] ?? 1));
        $perPage = max(10, min(100, absint($_GET['per_page'] ?? 25)));
        [$where, $args] = ExpensesController::buildWhere($params, $params['user_id'], true);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $table = ExpenseService::table();
        $total = (int)($args ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table}{$whereSql}", ...$args)) : $wpdb->get_var("SELECT COUNT(*) FROM {$table}{$whereSql}"));
        $sum = (float)($args ? $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount_total),0) FROM {$table}{$whereSql}", ...$args)) : $wpdb->get_var("SELECT COALESCE(SUM(amount_total),0) FROM {$table}{$whereSql}"));
        $pending = (int)($args ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table}{$whereSql}" . ($whereSql ? ' AND' : ' WHERE') . " status='pending'", ...$args)) : $wpdb->get_var("SELECT COUNT(*) FROM {$table}{$whereSql}" . ($whereSql ? ' AND' : ' WHERE') . " status='pending'"));
        $paid = (int)($args ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table}{$whereSql}" . ($whereSql ? ' AND' : ' WHERE') . " status='paid'", ...$args)) : $wpdb->get_var("SELECT COUNT(*) FROM {$table}{$whereSql}" . ($whereSql ? ' AND' : ' WHERE') . " status='paid'"));
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$table}{$whereSql} ORDER BY expense_date DESC,id DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...array_merge($args, [$perPage, $offset])), ARRAY_A) ?: [];
        $users = get_users(['fields' => ['ID','display_name','user_email'], 'orderby' => 'display_name', 'order' => 'ASC']);
        $pages = max(1, (int)ceil($total / $perPage));
        $settlements = ExpenseSettlementService::adminList($params, 50);
        $periodDefined = (bool)($params['month'] !== '' || $params['date_from'] !== '' || $params['date_to'] !== '');

        AdminPage::open('Despesas de Operação', 'Gestão interna de despesas submetidas na app, com comprovativos privados, validação e exportação.');
        if (isset($_GET['ff_paid'])) echo '<div class="notice notice-success inline"><p><strong>' . esc_html((string)absint($_GET['ff_paid'])) . '</strong> despesas marcadas como Paga / Reembolsada.</p></div>';
        ?>
        <style>
        .ffe-admin-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:16px 0}.ffe-admin-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px}.ffe-admin-kpi span{display:block;color:#64748b}.ffe-admin-kpi strong{display:block;font-size:27px;margin-top:5px}.ffe-admin-filters{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:10px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:14px;margin-bottom:14px}.ffe-admin-filters input,.ffe-admin-filters select{width:100%}.ffe-admin-actions{display:flex;gap:8px;align-items:end;flex-wrap:wrap}.ffe-admin-table td{vertical-align:top}.ffe-status{display:inline-block;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:800;background:#f1f5f9}.ffe-status.approved{background:#dcfce7;color:#166534}.ffe-status.paid{background:#dbeafe;color:#1d4ed8}.ffe-status.rejected{background:#fee2e2;color:#991b1b}.ffe-status.pending{background:#fef3c7;color:#92400e}.ffe-admin-small{color:#64748b;font-size:12px}.ffe-admin-amount{font-weight:800;font-size:16px}.ffe-admin-pagination{display:flex;gap:6px;align-items:center;margin-top:14px;flex-wrap:wrap}.ffe-settlement-box{margin-top:18px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px}.ffe-settlement-box h3{margin:0 0 10px}.ffe-bulk-pay{display:flex;gap:10px;align-items:center;flex-wrap:wrap;background:#eff6ff;border:1px solid #bfdbfe;border-radius:14px;padding:12px 14px;margin:-4px 0 14px}@media(max-width:1000px){.ffe-admin-filters{grid-template-columns:1fr 1fr}.ffe-admin-kpis{grid-template-columns:1fr}}@media(max-width:600px){.ffe-admin-filters{grid-template-columns:1fr}}
        </style>
        <div class="ffe-admin-kpis">
          <div class="ffe-admin-kpi"><span>Registos filtrados</span><strong><?php echo esc_html(number_format_i18n($total)); ?></strong></div>
          <div class="ffe-admin-kpi"><span>Total filtrado</span><strong><?php echo esc_html(number_format_i18n($sum, 2)); ?> €</strong></div>
          <div class="ffe-admin-kpi"><span>Pendentes no filtro</span><strong><?php echo esc_html(number_format_i18n($pending)); ?></strong></div>
          <div class="ffe-admin-kpi"><span>Pagas / reembolsadas</span><strong><?php echo esc_html(number_format_i18n($paid)); ?></strong></div>
        </div>
        <form method="get" class="ffe-admin-filters">
          <input type="hidden" name="page" value="routespro-expenses">
          <div><label>Utilizador</label><select name="user_id"><option value="0">Todos</option><?php foreach($users as $u): ?><option value="<?php echo esc_attr($u->ID); ?>" <?php selected($params['user_id'], $u->ID); ?>><?php echo esc_html($u->display_name . ' · ' . $u->user_email); ?></option><?php endforeach; ?></select></div>
          <div><label>Mês</label><input type="month" name="month" value="<?php echo esc_attr($params['month']); ?>"></div>
          <div><label>Data inicial</label><input type="date" name="date_from" value="<?php echo esc_attr($params['date_from']); ?>"></div>
          <div><label>Data final</label><input type="date" name="date_to" value="<?php echo esc_attr($params['date_to']); ?>"></div>
          <div><label>Categoria</label><select name="category"><option value="">Todas</option><?php foreach(ExpenseService::categories() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($params['category'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div>
          <div><label>Estado</label><select name="status"><option value="">Todos</option><?php foreach(ExpenseService::statuses() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($params['status'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div>
          <div><label>Pesquisa</label><input type="search" name="search" value="<?php echo esc_attr($params['search']); ?>" placeholder="Fornecedor, NIF ou documento"></div>
          <div><label>Por página</label><select name="per_page"><?php foreach([10,25,50,100] as $n): ?><option value="<?php echo esc_attr($n); ?>" <?php selected($perPage,$n); ?>><?php echo esc_html($n); ?></option><?php endforeach; ?></select></div>
          <div class="ffe-admin-actions"><button class="button button-primary">Filtrar</button><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=routespro-expenses')); ?>">Limpar</a><?php $exportFilters=array_filter($params,fn($v)=>$v!==''&&$v!==0); $exportArgs=array_merge(['action'=>'fieldflow_expense_export','_wpnonce'=>wp_create_nonce('fieldflow_expense_export')],$exportFilters); $zipArgs=array_merge(['action'=>'fieldflow_expense_export_zip','_wpnonce'=>wp_create_nonce('fieldflow_expense_export_zip')],$exportFilters); $statementArgs=array_merge(['action'=>'fieldflow_expense_statement_export','_wpnonce'=>wp_create_nonce('fieldflow_expense_statement_export')],$exportFilters); ?><a class="button" href="<?php echo esc_url(add_query_arg($exportArgs, admin_url('admin-post.php'))); ?>">Exportar CSV</a><a class="button" href="<?php echo esc_url(add_query_arg($zipArgs, admin_url('admin-post.php'))); ?>">Exportar pacote ZIP</a><?php if($params['user_id']>0): ?><a class="button button-primary" data-role="expense export download" data-format="pdf" href="<?php echo esc_url(add_query_arg($statementArgs, admin_url('admin-post.php'))); ?>">Folha de despesas PDF</a><?php else: ?><span class="button disabled" title="Seleciona um utilizador para gerar uma folha individual pronta a assinar.">Folha PDF · selecionar operativo</span><?php endif; ?></div>
        </form>
        <?php if($params['user_id']>0 && $periodDefined): ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ffe-bulk-pay" onsubmit="return confirm('Marcar todas as despesas elegíveis deste filtro como Paga / Reembolsada? Esta ação fica auditada.');">
            <input type="hidden" name="action" value="fieldflow_expense_bulk_paid">
            <?php wp_nonce_field('fieldflow_expense_bulk_paid'); ?>
            <?php foreach($params as $key=>$value): if($value===''||$value===0) continue; ?><input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string)$value); ?>"><?php endforeach; ?>
            <button class="button button-primary">Marcar filtradas como Paga / Reembolsada</button>
            <span class="ffe-admin-small">Aplica-se apenas a despesas Pendente/Aprovada. Rejeitadas e já pagas são ignoradas. Guarda data e utilizador do pagamento.</span>
          </form>
        <?php elseif($params['user_id']>0): ?>
          <div class="ffe-bulk-pay"><span class="ffe-admin-small">Seleciona um mês ou intervalo De/Até para ativar o fecho de pagamento em lote deste operativo.</span></div>
        <?php endif; ?>
        <div style="overflow:auto;background:#fff;border:1px solid #e2e8f0;border-radius:16px">
          <table class="widefat striped ffe-admin-table">
            <thead><tr><th>Data</th><th>Utilizador</th><th>Despesa</th><th>Documento</th><th>Valor</th><th>Origem</th><th>Comprovativo</th><th>Estado</th></tr></thead>
            <tbody>
            <?php if(!$rows): ?><tr><td colspan="8">Não existem despesas para estes filtros.</td></tr><?php endif; ?>
            <?php foreach($rows as $row): $item=ExpenseService::publicRow($row); ?>
              <tr>
                <td><strong><?php echo esc_html(date_i18n('d/m/Y', strtotime($item['expense_date']))); ?></strong><div class="ffe-admin-small"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($item['created_at']))); ?></div></td>
                <td><?php echo esc_html($item['user_name']); ?><div class="ffe-admin-small">ID <?php echo esc_html($item['user_id']); ?></div></td>
                <td><strong><?php echo esc_html($item['merchant_name'] ?: $item['category_label']); ?></strong><div class="ffe-admin-small"><?php echo esc_html($item['category_label']); ?><?php if($item['description']): ?> · <?php echo esc_html(wp_trim_words($item['description'],12)); ?><?php endif; ?></div></td>
                <td><?php echo esc_html($item['document_no'] ?: 'Sem número'); ?><div class="ffe-admin-small"><?php if($item['issuer_tax_id']): ?>NIF <?php echo esc_html($item['issuer_tax_id']); ?><?php endif; ?><?php if($item['atcud']): ?><br>ATCUD <?php echo esc_html($item['atcud']); ?><?php endif; ?></div></td>
                <td><span class="ffe-admin-amount"><?php echo esc_html(number_format_i18n($item['amount_total'],2)); ?> €</span><div class="ffe-admin-small">Impostos <?php echo esc_html(number_format_i18n($item['tax_amount'],2)); ?> €</div></td>
                <td><?php echo $item['capture_source']==='qr_at'?'<span class="ffe-status approved">QR fiscal</span>':'<span class="ffe-status">Manual ou OCR</span>'; ?><?php if($item['duplicate_of_id']): ?><div class="ffe-admin-small" style="color:#9a3412">Possível duplicado de #<?php echo esc_html($item['duplicate_of_id']); ?></div><?php endif; ?></td>
                <td><?php if($item['receipt_url']): ?><a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url($item['receipt_url']); ?>">Abrir foto</a><?php else: ?>Sem foto<?php endif; ?></td>
                <td><span class="ffe-status <?php echo esc_attr($item['status']); ?>"><?php echo esc_html($item['status_label']); ?></span><?php if(!empty($item['paid_at'])): ?><div class="ffe-admin-small">Pago em <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($item['paid_at']))); ?><?php if(!empty($item['paid_by_name'])): ?><br>por <?php echo esc_html($item['paid_by_name']); ?><?php endif; ?></div><?php endif; ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:7px;display:flex;gap:5px"><input type="hidden" name="action" value="fieldflow_expense_status"><input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>"><?php wp_nonce_field('fieldflow_expense_status_'.$item['id']); ?><select name="status"><?php foreach(ExpenseService::statuses() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($item['status'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select><button class="button button-small">Guardar</button></form></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="ffe-admin-pagination"><span>Página <?php echo esc_html($page); ?> de <?php echo esc_html($pages); ?></span><?php for($p=max(1,$page-2);$p<=min($pages,$page+2);$p++): ?><a class="button <?php echo $p===$page?'button-primary':''; ?>" href="<?php echo esc_url(add_query_arg(array_merge($_GET,['paged'=>$p]))); ?>"><?php echo esc_html($p); ?></a><?php endfor; ?></div>
        <section class="ffe-settlement-box">
          <h3>Folhas assinadas e comprovativos de recebimento</h3>
          <p class="ffe-admin-small">Arquivo central dos fechos submetidos pelos operativos, quer por upload da folha assinada, quer por assinatura direta no FieldFlow.</p>
          <div style="overflow:auto"><table class="widefat striped"><thead><tr><th>Operativo</th><th>Período</th><th>Despesas</th><th>Total</th><th>Forma</th><th>Submetido</th><th>Documento</th></tr></thead><tbody>
          <?php if(!$settlements): ?><tr><td colspan="7">Ainda não existem folhas assinadas para estes filtros.</td></tr><?php endif; ?>
          <?php foreach($settlements as $settlement): ?>
            <tr><td><strong><?php echo esc_html($settlement['user_name']); ?></strong></td><td><?php echo esc_html(trim(($settlement['date_from']?:'') . (($settlement['date_to']??'') && $settlement['date_to']!==$settlement['date_from'] ? ' a '.$settlement['date_to'] : ''))); ?></td><td><?php echo esc_html(number_format_i18n((int)$settlement['count'])); ?></td><td><strong><?php echo esc_html(number_format_i18n((float)$settlement['total'],2)); ?> €</strong></td><td><?php echo esc_html($settlement['method_label']); ?></td><td><?php echo !empty($settlement['submitted_at']) ? esc_html(date_i18n('d/m/Y H:i', strtotime($settlement['submitted_at']))) : '-'; ?></td><td><?php if(!empty($settlement['evidence_url'])): ?><a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url($settlement['evidence_url']); ?>">Ver</a> <a class="button button-small" href="<?php echo esc_url($settlement['download_url']); ?>">Exportar</a><?php endif; ?></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
        </section>
        <?php
        AdminPage::close();
    }

    public static function updateStatus(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        $id = absint($_POST['id'] ?? 0);
        check_admin_referer('fieldflow_expense_status_' . $id);
        $status = sanitize_key((string)($_POST['status'] ?? ''));
        if ($id && isset(ExpenseService::statuses()[$status])) {
            ExpenseService::setStatus($id, $status, get_current_user_id(), 'admin_single');
        }
        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=routespro-expenses'));
        exit;
    }

    public static function bulkMarkPaid(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_expense_bulk_paid');
        $params = [
            'user_id'=>absint($_POST['user_id']??0),
            'month'=>sanitize_text_field((string)($_POST['month']??'')),
            'date_from'=>sanitize_text_field((string)($_POST['date_from']??'')),
            'date_to'=>sanitize_text_field((string)($_POST['date_to']??'')),
            'category'=>sanitize_key((string)($_POST['category']??'')),
            'status'=>sanitize_key((string)($_POST['status']??'')),
            'search'=>sanitize_text_field((string)($_POST['search']??'')),
        ];
        if ($params['user_id'] <= 0 || ($params['month']==='' && $params['date_from']==='' && $params['date_to']==='')) {
            wp_die('Seleciona um operativo e um período antes de fechar pagamentos.', 'FieldFlow', ['response'=>400]);
        }
        global $wpdb;
        [$where,$args] = ExpensesController::buildWhere($params, $params['user_id'], true);
        $whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';
        $sql = 'SELECT id,status FROM '.ExpenseService::table().$whereSql.' ORDER BY id ASC';
        $rows = $args ? ($wpdb->get_results($wpdb->prepare($sql,...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
        $changed = 0;
        foreach ($rows as $row) {
            if (!in_array((string)($row['status']??''), ['pending','approved'], true)) continue;
            if (ExpenseService::setStatus((int)$row['id'], 'paid', get_current_user_id(), 'admin_bulk_filter')) $changed++;
        }
        $redirect = add_query_arg(array_filter(array_merge(['page'=>'routespro-expenses','ff_paid'=>$changed], $params), static fn($v)=>$v!==''&&$v!==0), admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    public static function serveReceipt(): void {
        if (!is_user_logged_in()) auth_redirect();
        $id = absint($_GET['id'] ?? 0);
        check_admin_referer('fieldflow_expense_receipt_' . $id);
        $row = ExpenseService::get($id);
        if (!$row || !ExpenseService::canRead($row, get_current_user_id())) wp_die('Sem permissões.', 403);
        $path = ExpenseService::absoluteReceiptPath((string)$row['receipt_path']);
        if (!is_file($path) || !is_readable($path)) wp_die('Comprovativo não encontrado.', 404);
        nocache_headers();
        header('Content-Type: ' . ($row['receipt_mime'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . sanitize_file_name($row['receipt_original_name'] ?: 'comprovativo') . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }



    public static function exportStatement(): void {
        if (!is_user_logged_in()) auth_redirect();
        check_admin_referer('fieldflow_expense_statement_export');

        $manager = current_user_can('routespro_manage');
        $requestedUserId = absint($_GET['user_id'] ?? 0);
        $userId = $manager && $requestedUserId > 0 ? $requestedUserId : get_current_user_id();
        if ($userId <= 0) wp_die('Sem utilizador autenticado.', 'FieldFlow', ['response'=>403]);

        $filters = [
            'month'=>sanitize_text_field((string)($_GET['month'] ?? '')),
            'date_from'=>sanitize_text_field((string)($_GET['date_from'] ?? '')),
            'date_to'=>sanitize_text_field((string)($_GET['date_to'] ?? '')),
            'category'=>sanitize_key((string)($_GET['category'] ?? '')),
            'status'=>sanitize_key((string)($_GET['status'] ?? '')),
            'search'=>sanitize_text_field((string)($_GET['search'] ?? '')),
        ];
        $clientId = absint($_GET['client_id'] ?? 0);
        $projectId = absint($_GET['project_id'] ?? 0);
        $data = ExpenseStatementService::statementData($userId, $filters, $clientId, $projectId);
        if (class_exists('\RoutesPro\SaaS\EntitlementService')) {
            \RoutesPro\SaaS\EntitlementService::assertExportOrDie('pdf', (int)($data['client_id'] ?? 0), true);
        }
        $pdf = ExpenseStatementService::buildPdf($data);
        $filename = ExpenseStatementService::filename($data);
        while (ob_get_level() > 0) @ob_end_clean();
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . strlen($pdf));
        header('X-Content-Type-Options: nosniff');
        header('X-FieldFlow-Expense-Statement: ' . ExpenseStatementService::CONTRACT);
        echo $pdf;
        exit;
    }

    /**
     * Prepares the expense statement while the authenticated WebView session is
     * still available, then returns a short-lived opaque capability URL. The
     * Android/iOS system browser can download that URL without WordPress cookies.
     */
    public static function prepareStatementTicket(): void {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message'=>'Sem sessão ativa.'], 403);
        }
        $nonce = sanitize_text_field((string)($_POST['_wpnonce'] ?? ''));
        if ($nonce === '' || !wp_verify_nonce($nonce, 'fieldflow_expense_statement_export')) {
            wp_send_json_error(['message'=>'Pedido de folha de despesas inválido.'], 403);
        }

        try {
            $manager = current_user_can('routespro_manage');
            $requestedUserId = absint($_POST['user_id'] ?? 0);
            $userId = $manager && $requestedUserId > 0 ? $requestedUserId : get_current_user_id();
            if ($userId <= 0) throw new \RuntimeException('Sem utilizador autenticado.');

            $filters = self::statementFiltersFromRequest($_POST);
            $clientId = absint($_POST['client_id'] ?? 0);
            $projectId = absint($_POST['project_id'] ?? 0);
            $data = ExpenseStatementService::statementData($userId, $filters, $clientId, $projectId);
            if (class_exists('\\RoutesPro\\SaaS\\EntitlementService')) {
                \RoutesPro\SaaS\EntitlementService::assertExportOrDie('pdf', (int)($data['client_id'] ?? 0), true);
            }

            $pdf = ExpenseStatementService::buildPdf($data);
            if (strlen($pdf) < 64 || strncmp($pdf, '%PDF-', 5) !== 0) {
                throw new \RuntimeException('A folha de despesas não foi gerada corretamente.');
            }
            $filename = ExpenseStatementService::filename($data);
            $tmp = wp_tempnam('fieldflow-expense-statement.pdf');
            if (!$tmp) throw new \RuntimeException('Não foi possível preparar o ficheiro temporário.');
            $written = @file_put_contents($tmp, $pdf, LOCK_EX);
            if ($written === false || (int)$written !== strlen($pdf) || !is_file($tmp)) {
                @unlink($tmp);
                throw new \RuntimeException('Não foi possível guardar a folha de despesas preparada.');
            }
            @chmod($tmp, 0600);

            try {
                $ticket = bin2hex(random_bytes(32));
            } catch (\Throwable $e) {
                $ticket = hash('sha256', wp_generate_uuid4() . '|' . microtime(true) . '|' . $userId . '|' . wp_salt('nonce'));
            }
            $ticket = strtolower(preg_replace('/[^a-f0-9]/', '', (string)$ticket));
            if (strlen($ticket) !== 64) $ticket = hash('sha256', $ticket . '|' . wp_generate_uuid4() . '|' . microtime(true));

            $expires = time() + self::EXPENSE_STATEMENT_TICKET_TTL;
            $payload = [
                'path'=>$tmp,
                'filename'=>sanitize_file_name($filename ?: 'fieldflow-folha-despesas.pdf'),
                'mime'=>'application/pdf',
                'bytes'=>(int)$written,
                'sha256'=>hash('sha256', $pdf),
                'user_id'=>$userId,
                'created_by'=>get_current_user_id(),
                'created_at'=>time(),
                'expires'=>$expires,
                'downloads'=>0,
            ];
            $key = self::statementTicketKey($ticket);
            $stored = set_transient($key, $payload, self::EXPENSE_STATEMENT_TICKET_TTL);
            if (!$stored || !is_array(get_transient($key))) {
                @unlink($tmp);
                throw new \RuntimeException('Não foi possível criar o acesso temporário ao PDF.');
            }
            if (function_exists('wp_schedule_single_event')) {
                wp_schedule_single_event($expires + 90, 'fieldflow_expense_statement_ticket_cleanup', [$tmp]);
            }

            if (class_exists('\\RoutesPro\\Core\\AuditLog')) {
                try {
                    \RoutesPro\Core\AuditLog::record('expense_statement_ticket_created', 'expense_statement', 0, null, null, [
                        'user_id'=>$userId,
                        'created_by'=>get_current_user_id(),
                        'client_id'=>(int)($data['client_id'] ?? 0),
                        'project_id'=>(int)($data['project_id'] ?? 0),
                        'bytes'=>(int)$written,
                        'expires_at'=>gmdate('c', $expires),
                    ]);
                } catch (\Throwable $e) {}
            }

            wp_send_json_success([
                'url'=>self::statementTicketUrl($ticket),
                'filename'=>$payload['filename'],
                'format'=>'pdf',
                'mime'=>'application/pdf',
                'bytes'=>(int)$written,
                'expires'=>$expires,
            ]);
        } catch (\Throwable $e) {
            if (class_exists('\\RoutesPro\\Security\\SecurityService')) {
                try {
                    \RoutesPro\Security\SecurityService::record('expense_statement_ticket_failed', 'warning', [
                        'user_id'=>get_current_user_id(),
                        'error'=>$e->getMessage(),
                    ]);
                } catch (\Throwable $ignored) {}
            }
            wp_send_json_error(['message'=>$e->getMessage() ?: 'Não foi possível preparar a folha de despesas.'], 500);
        }
    }

    /**
     * Cookie-free, HEAD-safe and reusable during its short TTL. The ticket is
     * deliberately opaque so the native/system downloader never needs to carry
     * the authenticated WordPress session outside the FieldFlow WebView.
     */
    public static function handleStatementTicketDownload(): void {
        if (empty($_GET['fieldflow_expense_statement_download'])) return;

        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['GET','HEAD'], true)) {
            self::statementTicketHttpError(405, 'method_not_allowed', 'Método de download não suportado.');
        }
        $ticket = strtolower(sanitize_text_field((string)($_GET['ticket'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $ticket)) {
            self::statementTicketHttpError(400, 'invalid_ticket', 'Pedido de download inválido.');
        }
        $key = self::statementTicketKey($ticket);
        $payload = get_transient($key);
        if (!is_array($payload)) {
            self::statementTicketHttpError(410, 'ticket_expired', 'O acesso temporário à folha de despesas expirou. Gera novamente o PDF.');
        }

        $expires = (int)($payload['expires'] ?? 0);
        $path = (string)($payload['path'] ?? '');
        if ($expires < time() || $expires > time() + self::EXPENSE_STATEMENT_TICKET_TTL + 120) {
            delete_transient($key);
            self::cleanupStatementTicketFile($path);
            self::statementTicketHttpError(410, 'ticket_expired', 'O acesso temporário à folha de despesas expirou. Gera novamente o PDF.');
        }
        if (!self::isStatementTicketFile($path) || !is_readable($path)) {
            delete_transient($key);
            self::statementTicketHttpError(404, 'prepared_file_missing', 'A folha de despesas preparada deixou de estar disponível.');
        }

        clearstatcache(true, $path);
        $size = (int)@filesize($path);
        $fh = @fopen($path, 'rb');
        $magic = $fh ? (string)fread($fh, 5) : '';
        if ($fh) fclose($fh);
        if ($size < 64 || $magic !== '%PDF-') {
            delete_transient($key);
            self::cleanupStatementTicketFile($path);
            self::statementTicketHttpError(500, 'prepared_file_invalid', 'A folha de despesas preparada está vazia ou incompleta.');
        }

        // Keep the ticket alive for Android/Chrome HEAD probes and GET retries.
        $remaining = max(60, $expires - time());
        $payload['downloads'] = max(0, (int)($payload['downloads'] ?? 0)) + ($method === 'GET' ? 1 : 0);
        $payload['last_request_at'] = time();
        set_transient($key, $payload, $remaining);

        $filename = sanitize_file_name((string)($payload['filename'] ?? 'fieldflow-folha-despesas.pdf'));
        if ($filename === '') $filename = 'fieldflow-folha-despesas.pdf';
        while (ob_get_level() > 0) @ob_end_clean();
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . $size);
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow, nosnippet');
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0, no-transform');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-FieldFlow-Expense-Statement: ' . ExpenseStatementService::CONTRACT);
        header('X-FieldFlow-Expense-Statement-Ticket: ' . self::EXPENSE_STATEMENT_MOBILE_TICKET_CONTRACT);
        if ($method === 'HEAD') exit;

        $fh = @fopen($path, 'rb');
        if (!$fh) self::statementTicketHttpError(500, 'prepared_file_unreadable', 'Não foi possível abrir a folha de despesas preparada.');
        while (!feof($fh)) {
            $chunk = fread($fh, 1048576);
            if ($chunk === false) break;
            if ($chunk === '') continue;
            echo $chunk;
            flush();
        }
        fclose($fh);
        exit;
    }

    public static function cleanupStatementTicketFile(string $path): void {
        if (self::isStatementTicketFile($path) && is_file($path)) @unlink($path);
    }

    private static function statementFiltersFromRequest(array $source): array {
        return [
            'month'=>sanitize_text_field((string)($source['month'] ?? '')),
            'date_from'=>sanitize_text_field((string)($source['date_from'] ?? '')),
            'date_to'=>sanitize_text_field((string)($source['date_to'] ?? '')),
            'category'=>sanitize_key((string)($source['category'] ?? '')),
            'status'=>sanitize_key((string)($source['status'] ?? '')),
            'search'=>sanitize_text_field((string)($source['search'] ?? '')),
        ];
    }

    private static function statementTicketKey(string $ticket): string {
        return 'ff_expense_statement_' . substr(hash('sha256', strtolower($ticket)), 0, 40);
    }

    private static function statementTicketUrl(string $ticket): string {
        return add_query_arg([
            'fieldflow_expense_statement_download'=>'1',
            'ticket'=>$ticket,
        ], home_url('/'));
    }

    private static function isStatementTicketFile(string $path): bool {
        if ($path === '' || !is_file($path)) return false;
        $base = basename($path);
        return strpos($base, 'fieldflow-expense-statement') === 0;
    }

    private static function statementTicketHttpError(int $status, string $code, string $message): void {
        while (ob_get_level() > 0) @ob_end_clean();
        status_header($status);
        nocache_headers();
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-FieldFlow-Expense-Statement-Error: ' . sanitize_key($code));
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        echo wp_strip_all_tags($message);
        exit;
    }

    public static function exportZip(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_expense_export_zip');
        if (class_exists('\RoutesPro\SaaS\EntitlementService')) \RoutesPro\SaaS\EntitlementService::assertExportOrDie('csv', 0, false);
        if (!class_exists('ZipArchive')) wp_die('A extensão ZIP não está disponível neste servidor. Usa a exportação CSV.');
        $rows = self::filteredRowsFromRequest();
        $tmp = wp_tempnam('fieldflow-despesas.zip');
        if (!$tmp) wp_die('Não foi possível criar o pacote temporário.');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            wp_die('Não foi possível criar o pacote ZIP.');
        }
        $csv = fopen('php://temp', 'w+');
        fwrite($csv, "\xEF\xBB\xBF");
        self::writeCsv($csv, $rows);
        rewind($csv);
        $zip->addFromString('despesas.csv', stream_get_contents($csv));
        fclose($csv);
        foreach ($rows as $row) {
            $path = ExpenseService::absoluteReceiptPath((string)($row['receipt_path'] ?? ''));
            if (!empty($row['receipt_path']) && is_file($path) && is_readable($path)) {
                $name = sanitize_file_name((string)($row['receipt_original_name'] ?: basename($path)));
                $zip->addFile($path, 'comprovativos/' . (int)$row['id'] . '-' . $name);
            }
        }
        foreach (ExpenseSettlementService::adminList(self::expenseFilterParams($_GET), 500) as $settlement) {
            $relative = (string)get_post_meta((int)$settlement['id'], '_ff_evidence_path', true);
            $path = ExpenseSettlementService::absolutePath($relative);
            if ($path !== '' && is_readable($path)) {
                $name = sanitize_file_name((string)($settlement['evidence_name'] ?? basename($path)));
                $zip->addFile($path, 'folhas-assinadas/' . (int)$settlement['id'] . '-' . $name);
            }
        }
        $zip->close();
        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="fieldflow-despesas-' . gmdate('Y-m-d-His') . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    private static function expenseFilterParams(array $source): array {
        return [
            'user_id'=>absint($source['user_id']??0),
            'month'=>sanitize_text_field((string)($source['month']??'')),
            'date_from'=>sanitize_text_field((string)($source['date_from']??'')),
            'date_to'=>sanitize_text_field((string)($source['date_to']??'')),
            'category'=>sanitize_key((string)($source['category']??'')),
            'status'=>sanitize_key((string)($source['status']??'')),
            'search'=>sanitize_text_field((string)($source['search']??'')),
        ];
    }

    private static function filteredRowsFromRequest(): array {
        global $wpdb;
        $params = self::expenseFilterParams($_GET);
        [$where,$args]=ExpensesController::buildWhere($params,$params['user_id'],true);
        $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
        $sql='SELECT * FROM '.ExpenseService::table().$whereSql.' ORDER BY expense_date DESC,id DESC';
        return $args ? ($wpdb->get_results($wpdb->prepare($sql,...$args),ARRAY_A) ?: []) : ($wpdb->get_results($sql,ARRAY_A) ?: []);
    }

    private static function writeCsv($out, array $rows): void {
        fputcsv($out,['ID','Data despesa','Utilizador','Email','Categoria','Fornecedor','NIF emitente','Documento','ATCUD','Descrição','Pagamento','Valor líquido','Impostos','Total','Moeda','Origem','Verificação','Estado','Pago em','Pago por','Possível duplicado de','Ficheiro comprovativo','Criado em'],';');
        foreach($rows as $row){
            $u=get_userdata((int)$row['user_id']);
            $item=ExpenseService::publicRow($row);
            fputcsv($out,[$row['id'],$row['expense_date'],$u?$u->display_name:'',$u?$u->user_email:'',ExpenseService::categories()[$row['category']]??$row['category'],$row['merchant_name'],$row['issuer_tax_id'],$row['document_no'],$row['atcud'],$row['description'],ExpenseService::paymentMethods()[$row['payment_method']]??$row['payment_method'],number_format((float)$row['amount_net'],2,',',''),number_format((float)$row['tax_amount'],2,',',''),number_format((float)$row['amount_total'],2,',',''),$row['currency'],$row['capture_source'],$row['verification_state'],ExpenseService::statuses()[$row['status']]??$row['status'],$item['paid_at'],$item['paid_by_name'],$row['duplicate_of_id'],$row['receipt_original_name'],$row['created_at']],';');
        }
    }

    public static function export(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_expense_export');
        if (class_exists('\RoutesPro\SaaS\EntitlementService')) \RoutesPro\SaaS\EntitlementService::assertExportOrDie('csv', 0, false);
        $rows = self::filteredRowsFromRequest();
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="fieldflow-despesas-' . gmdate('Y-m-d-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        self::writeCsv($out, $rows);
        fclose($out);
        exit;
    }
}
