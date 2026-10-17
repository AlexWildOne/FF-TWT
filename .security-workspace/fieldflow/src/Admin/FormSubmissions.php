<?php
namespace RoutesPro\Admin;
use RoutesPro\Forms\Forms as FormsModule;
use RoutesPro\Forms\ContextQuestions as ContextQuestionService;
use RoutesPro\Forms\EvidenceFlow;
use RoutesPro\Forms\RecordService;
use RoutesPro\Forms\AnswerSemantics;
use RoutesPro\Analytics\ReportIntelligenceService;
use RoutesPro\Services\OperationalReportDayPolicy;
use RoutesPro\Services\SubmissionCorrectionService;
use RoutesPro\Reporting\CanonicalOperationalReportService;
if (!defined('ABSPATH')) exit;
class FormSubmissions {
    public static function register_hooks() {
        add_action('admin_post_routespro_save_submission', [self::class, 'handle_save']);
        add_action('admin_post_routespro_delete_submission', [self::class, 'handle_delete']);
        add_action('admin_post_routespro_export_submissions', [self::class, 'handle_export']);
        add_action('admin_post_routespro_export_dashboard', [self::class, 'handle_dashboard_export']);
    }

    public static function render() {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        global $wpdb;

        $filters = [
            'client_id' => absint($_GET['client_id'] ?? 0),
            'project_id' => absint($_GET['project_id'] ?? 0),
            'owner_user_id' => absint($_GET['owner_user_id'] ?? 0),
            'route_id' => absint($_GET['route_id'] ?? 0),
            'location_id' => absint($_GET['location_id'] ?? 0),
            'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_GET['date_to'] ?? ''),
        ];
        $allowedPerPage = [10, 25, 50, 100];
        $perPage = absint($_GET['per_page'] ?? 25);
        if (!in_array($perPage, $allowedPerPage, true)) $perPage = 25;
        $currentPage = max(1, absint($_GET['submission_page'] ?? 1));

        $dataset = self::get_submission_dataset($filters + [
            'limit' => $perPage,
            'offset' => ($currentPage - 1) * $perPage,
            'with_total' => true,
            'include_wide' => false,
        ]);
        $rows = $dataset['rows'];
        $total = max(0, (int)($dataset['total'] ?? count($rows)));
        $totalPages = max(1, (int)ceil($total / max(1, $perPage)));
        if ($currentPage > $totalPages && $total > 0) {
            $currentPage = $totalPages;
            $dataset = self::get_submission_dataset($filters + [
                'limit' => $perPage,
                'offset' => ($currentPage - 1) * $perPage,
                'with_total' => true,
                'include_wide' => false,
            ]);
            $rows = $dataset['rows'];
        }

        $clients = $wpdb->get_results('SELECT id, name FROM ' . $wpdb->prefix . 'routespro_clients ORDER BY name ASC', ARRAY_A) ?: [];
        $projects = $wpdb->get_results('SELECT id, name FROM ' . $wpdb->prefix . 'routespro_projects ORDER BY name ASC', ARRAY_A) ?: [];
        $owners = $wpdb->get_results('SELECT DISTINCT u.ID AS id, u.display_name AS name FROM ' . $wpdb->prefix . 'routespro_routes r INNER JOIN ' . $wpdb->users . ' u ON u.ID = r.owner_user_id WHERE r.owner_user_id IS NOT NULL ORDER BY u.display_name ASC', ARRAY_A) ?: [];

        $pageMetrics = ['answers'=>0,'product_rows'=>0,'zero_products'=>0,'orders'=>0,'files'=>0];
        foreach ($rows as $row) {
            $summary = (array)($row['presentation']['summary'] ?? []);
            foreach ($pageMetrics as $key => $unused) $pageMetrics[$key] += (int)($summary[$key] ?? 0);
        }

        echo '<div class="wrap routespro-submissions-v2">';
        Branding::render_header('Submissões', 'Uma grelha compacta para decidir rapidamente, com o detalhe completo organizado por respostas, categorias, produtos, encomendas e evidências.');
        if (isset($_GET['saved']) || isset($_GET['corrected'])) echo '<div class="notice notice-success"><p>Submissão corrigida e auditada.</p></div>';
        if (isset($_GET['deleted']) || isset($_GET['voided'])) echo '<div class="notice notice-success"><p>Submissão anulada. O histórico e as respostas foram preservados.</p></div>';
        if (!empty($_GET['warning'])) echo '<div class="notice notice-warning"><p>' . esc_html(sanitize_text_field(wp_unslash((string)$_GET['warning']))) . '</p></div>';
        echo '<style>
        .routespro-submissions-v2{--ff-ink:#0f172a;--ff-muted:#64748b;--ff-border:#e2e8f0;--ff-soft:#f8fafc;--ff-accent:#ea611d}
        .routespro-submissions-v2 .ff-submission-kpis{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:12px;margin:16px 0}
        .routespro-submissions-v2 .ff-submission-kpi{background:#fff;border:1px solid var(--ff-border);border-radius:16px;padding:14px 16px;box-shadow:0 8px 24px rgba(15,23,42,.05)}
        .routespro-submissions-v2 .ff-submission-kpi span{display:block;color:var(--ff-muted);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.routespro-submissions-v2 .ff-submission-kpi strong{display:block;color:var(--ff-ink);font-size:26px;line-height:1.1;margin-top:5px}
        .routespro-submissions-v2 .ff-submission-table-wrap{overflow:auto;background:#fff;border:1px solid var(--ff-border);border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.06)}
        .routespro-submissions-v2 .ff-submission-table{width:100%;border-collapse:collapse;min-width:980px}.routespro-submissions-v2 .ff-submission-table th{padding:12px 14px;background:#f8fafc;border-bottom:1px solid var(--ff-border);text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#475569}.routespro-submissions-v2 .ff-submission-table td{padding:14px;border-bottom:1px solid #eef2f7;vertical-align:top;color:var(--ff-ink)}
        .routespro-submissions-v2 .ff-submission-table tr.ff-submission-main:hover td{background:#fcfcfd}.routespro-submissions-v2 .ff-title{font-weight:800;color:var(--ff-ink);line-height:1.35}.routespro-submissions-v2 .ff-meta{color:var(--ff-muted);font-size:12px;line-height:1.45;margin-top:3px}
        .routespro-submissions-v2 .ff-badges{display:flex;gap:5px;flex-wrap:wrap}.routespro-submissions-v2 .ff-badge{display:inline-flex;align-items:center;border-radius:999px;padding:4px 8px;background:#f1f5f9;border:1px solid #e2e8f0;color:#334155;font-size:11px;font-weight:700}.routespro-submissions-v2 .ff-badge.alert{background:#fff7ed;border-color:#fed7aa;color:#9a3412}.routespro-submissions-v2 .ff-badge.good{background:#ecfdf5;border-color:#a7f3d0;color:#047857}.routespro-submissions-v2 .ff-badge.file{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
        .routespro-submissions-v2 .ff-status{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:800;background:#eef2ff;color:#3730a3}.routespro-submissions-v2 .ff-detail-row td{padding:0 14px 14px;background:#fbfdff}.routespro-submissions-v2 .ff-detail-row details{border:1px solid var(--ff-border);border-radius:14px;background:#fff}.routespro-submissions-v2 .ff-detail-row summary{cursor:pointer;list-style:none;padding:11px 14px;font-weight:800;color:#334155}.routespro-submissions-v2 .ff-detail-row summary::-webkit-details-marker{display:none}.routespro-submissions-v2 .ff-detail-row summary:after{content:"Abrir";float:right;color:var(--ff-accent);font-size:11px;text-transform:uppercase;letter-spacing:.05em}.routespro-submissions-v2 .ff-detail-row details[open] summary:after{content:"Fechar"}.routespro-submissions-v2 .ff-detail-body{padding:0 14px 16px}
        .routespro-submissions-v2 .ff-answer-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.routespro-submissions-v2 .ff-answer-card{border:1px solid var(--ff-border);border-radius:12px;padding:10px 12px;background:var(--ff-soft)}.routespro-submissions-v2 .ff-answer-card strong{display:block;font-size:12px;margin-bottom:5px}.routespro-submissions-v2 .ff-answer-card div{white-space:pre-wrap;overflow-wrap:anywhere}
        .routespro-submissions-v2 .ff-report-section{margin-top:14px}.routespro-submissions-v2 .ff-report-section h4{margin:0 0 8px;font-size:14px}.routespro-submissions-v2 .ff-category-detail{margin:7px 0;border:1px solid var(--ff-border);border-radius:12px;overflow:hidden}.routespro-submissions-v2 .ff-category-detail>summary{background:#f8fafc;padding:9px 11px}.routespro-submissions-v2 .ff-mini-table{width:100%;border-collapse:collapse}.routespro-submissions-v2 .ff-mini-table th,.routespro-submissions-v2 .ff-mini-table td{padding:8px 10px;border-top:1px solid #eef2f7;font-size:12px}.routespro-submissions-v2 .ff-mini-table th{background:#fff;color:#64748b;text-transform:none;letter-spacing:0}.routespro-submissions-v2 .ff-zero{color:#b45309;font-weight:800}.routespro-submissions-v2 .ff-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:14px 0}.routespro-submissions-v2 .ff-pagination-links{display:flex;gap:6px;flex-wrap:wrap}
        @media(max-width:1100px){.routespro-submissions-v2 .ff-submission-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.routespro-submissions-v2 .ff-answer-grid{grid-template-columns:1fr 1fr}}
        @media(max-width:700px){.routespro-submissions-v2 .ff-submission-kpis{grid-template-columns:1fr 1fr}.routespro-submissions-v2 .ff-answer-grid{grid-template-columns:1fr}}
        </style>';

        self::render_filters($filters + ['per_page'=>$perPage], $clients, $projects, $owners);
        self::render_export_actions($filters);

        echo '<div class="ff-submission-kpis">';
        self::render_submission_kpi('Submissões filtradas', $total);
        self::render_submission_kpi('Respostas nesta página', $pageMetrics['answers']);
        self::render_submission_kpi('Referências nesta página', $pageMetrics['product_rows']);
        self::render_submission_kpi('Ruturas nesta página', $pageMetrics['zero_products']);
        self::render_submission_kpi('Encomendas nesta página', $pageMetrics['orders']);
        echo '</div>';

        echo '<div class="ff-submission-table-wrap"><table class="ff-submission-table"><thead><tr><th>Reporte</th><th>Loja e rota</th><th>Formulário e âmbito</th><th>Equipa</th><th>Conteúdo</th><th>Ações</th></tr></thead><tbody>';
        if (!$rows) {
            echo '<tr><td colspan="6">Ainda não existem submissões para estes filtros.</td></tr>';
        } else {
            foreach ($rows as $row) {
                $presentation = (array)($row['presentation'] ?? []);
                $summary = (array)($presentation['summary'] ?? []);
                $edit = admin_url('admin.php?page=routespro-form-submission-edit&id=' . (int)$row['id']);
                $void = $edit . '#ff-submission-void';
                echo '<tr class="ff-submission-main">';
                echo '<td><div class="ff-title">#' . (int)$row['id'] . '</div><div class="ff-meta">' . esc_html(self::format_submission_date((string)($row['submitted_at'] ?? ''))) . '</div><div style="margin-top:7px"><span class="ff-status">' . esc_html(self::submission_status_label((string)($row['status'] ?? ''))) . '</span></div></td>';
                echo '<td><div class="ff-title">' . esc_html($row['location_name'] ?: 'Sem loja associada') . '</div><div class="ff-meta">Rota #' . (int)($row['route_id'] ?? 0) . ' · Paragem #' . (int)($row['route_stop_id'] ?? 0) . '<br>' . esc_html((string)($row['route_date'] ?? '')) . ' · ' . esc_html(self::stop_status_label((string)($row['stop_status'] ?? ''))) . '</div></td>';
                echo '<td><div class="ff-title">' . esc_html($row['form_title'] ?: ('Formulário #' . (int)$row['form_id'])) . '</div><div class="ff-meta">' . esc_html($row['client_name'] ?: 'Sem cliente') . '<br>' . esc_html($row['project_name'] ?: 'Sem campanha') . '</div></td>';
                echo '<td><div class="ff-title">' . esc_html($row['owner_name'] ?: 'Sem operativo') . '</div><div class="ff-meta">Submetido por ' . esc_html($row['user_name'] ?: ('User #' . (int)$row['user_id'])) . '</div></td>';
                echo '<td>' . self::render_submission_badges($summary) . '<div class="ff-meta">' . esc_html((string)($presentation['preview'] ?? '')) . '</div></td>';
                echo '<td><a class="button button-small button-primary" href="' . esc_url($edit) . '">Editar</a> <a class="button button-small" href="' . esc_url($void) . '">Anular</a></td>';
                echo '</tr>';
                echo '<tr class="ff-detail-row"><td colspan="6"><details><summary>Ver reporte completo</summary><div class="ff-detail-body">' . self::render_submission_detail($presentation) . '</div></details></td></tr>';
            }
        }
        echo '</tbody></table></div>';
        self::render_submission_pagination($filters, $currentPage, $totalPages, $perPage, $total);
        echo '</div>';
    }


    public static function get_submission_dataset(array $filters = []) : array {
        global $wpdb;
        $filters = wp_parse_args($filters, [
            'submission_id' => 0,
            'client_id' => 0,
            'project_id' => 0,
            'owner_user_id' => 0,
            'route_id' => 0,
            'location_id' => 0,
            'date_from' => '',
            'date_to' => '',
            'limit' => 300,
            'scope_user_id' => 0,
            'offset' => 0,
            'with_total' => false,
            'include_wide' => true,
        ]);

        $bindings = \RoutesPro\Forms\BindingResolver::table();
        $routeExpr = 'COALESCE(NULLIF(s.route_id,0),NULLIF(rs.route_id,0),NULLIF(fb.route_id,0))';
        $stopExpr = 'COALESCE(NULLIF(s.route_stop_id,0),NULLIF(fb.stop_id,0))';
        $locationExpr = 'COALESCE(NULLIF(s.location_id,0),NULLIF(rs.location_id,0),NULLIF(fb.location_id,0))';
        $projectExpr = 'COALESCE(NULLIF(s.project_id,0),NULLIF(r.project_id,0),NULLIF(fb.project_id,0),NULLIF(loc.project_id,0))';
        $clientExpr = 'COALESCE(NULLIF(s.client_id,0),NULLIF(r.client_id,0),NULLIF(fb.client_id,0),NULLIF(p.client_id,0),NULLIF(loc.client_id,0))';

        $sql = 'SELECT ' . (!empty($filters['with_total']) ? 'SQL_CALC_FOUND_ROWS ' : '') . 's.*, 
            ' . $clientExpr . ' AS resolved_client_id,
            ' . $projectExpr . ' AS resolved_project_id,
            ' . $routeExpr . ' AS resolved_route_id,
            ' . $stopExpr . ' AS resolved_route_stop_id,
            ' . $locationExpr . ' AS resolved_location_id,
            f.title AS form_title,
            submitter.display_name AS user_name,
            c.name AS client_name,
            p.name AS project_name,
            r.id AS joined_route_id,
            r.date AS route_date,
            r.status AS route_status,
            r.owner_user_id AS joined_owner_user_id,
            owner.display_name AS owner_name,
            rs.id AS stop_row_id,
            rs.seq AS stop_seq,
            rs.status AS stop_status,
            rs.planned_arrival,
            rs.planned_departure,
            rs.arrived_at,
            rs.departed_at,
            rs.duration_s,
            rs.note AS stop_note,
            rs.fail_reason,
            rs.real_lat,
            rs.real_lng,
            rs.qty,
            rs.weight,
            rs.volume,
            loc.name AS location_name
        FROM ' . FormsModule::table_submissions() . ' s
        LEFT JOIN ' . FormsModule::table() . ' f ON f.id = s.form_id
        LEFT JOIN ' . $wpdb->users . ' submitter ON submitter.ID = s.user_id
        LEFT JOIN ' . $bindings . ' fb ON fb.id = s.binding_id
        LEFT JOIN ' . $wpdb->prefix . 'routespro_route_stops rs ON rs.id = ' . $stopExpr . '
        LEFT JOIN ' . $wpdb->prefix . 'routespro_routes r ON r.id = ' . $routeExpr . '
        LEFT JOIN ' . $wpdb->prefix . 'routespro_locations loc ON loc.id = ' . $locationExpr . '
        LEFT JOIN ' . $wpdb->prefix . 'routespro_projects p ON p.id = ' . $projectExpr . '
        LEFT JOIN ' . $wpdb->prefix . 'routespro_clients c ON c.id = ' . $clientExpr . '
        LEFT JOIN ' . $wpdb->users . ' owner ON owner.ID = r.owner_user_id
        WHERE 1=1';
        $params = [];
        $operationalDateExpr = OperationalReportDayPolicy::sqlExpression('s', 'r');
        if (!empty($filters['operational_only'])) $sql .= ' AND ' . \RoutesPro\Services\SubmissionStatusPolicy::operationalSql('s');
        if (!empty($filters['submission_id'])) { $sql .= ' AND s.id = %d'; $params[] = (int) $filters['submission_id']; }
        if (!empty($filters['client_id'])) { $sql .= ' AND ' . $clientExpr . ' = %d'; $params[] = (int) $filters['client_id']; }
        if (!empty($filters['project_id'])) { $sql .= ' AND ' . $projectExpr . ' = %d'; $params[] = (int) $filters['project_id']; }
        if (!empty($filters['owner_user_id'])) { $sql .= ' AND r.owner_user_id = %d'; $params[] = (int) $filters['owner_user_id']; }
        $reportUserId = absint($filters['report_user_id'] ?? 0);
        $reportRole = sanitize_key((string)($filters['report_role'] ?? ''));
        if ($reportUserId) {
            $assignmentSql = 'ax.route_id=r.id AND ax.user_id=%d';
            $assignmentArgs = [$reportUserId];
            if ($reportRole !== '') { $assignmentSql .= ' AND ax.role=%s'; $assignmentArgs[] = $reportRole; }
            $sql .= ' AND (r.owner_user_id=%d OR EXISTS (SELECT 1 FROM ' . $wpdb->prefix . 'routespro_assignments ax WHERE ' . $assignmentSql . '))';
            $params = array_merge($params, [$reportUserId], $assignmentArgs);
        } elseif ($reportRole !== '') {
            $sql .= ' AND EXISTS (SELECT 1 FROM ' . $wpdb->prefix . 'routespro_assignments ax WHERE ax.route_id=r.id AND ax.role=%s)';
            $params[] = $reportRole;
        }
        if (!empty($filters['route_id'])) { $sql .= ' AND ' . $routeExpr . ' = %d'; $params[] = (int) $filters['route_id']; }
        if (!empty($filters['location_id'])) { $sql .= ' AND ' . $locationExpr . ' = %d'; $params[] = (int) $filters['location_id']; }
        if (!empty($filters['date_from'])) { $sql .= ' AND ' . $operationalDateExpr . ' >= %s'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to'])) { $sql .= ' AND ' . $operationalDateExpr . ' <= %s'; $params[] = $filters['date_to']; }
        $scopeUserId = absint($filters['scope_user_id'] ?? 0);
        if ($scopeUserId > 0 && !\RoutesPro\Support\Permissions::is_manager($scopeUserId)) {
            [$scopeSql, $scopeArgs] = \RoutesPro\Support\Permissions::scope_sql('', $clientExpr, $projectExpr, $scopeUserId);
            $sql .= ' AND ' . $scopeSql;
            $params = array_merge($params, $scopeArgs);
        }
        $sql .= ' ORDER BY s.submitted_at DESC, s.id DESC';
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . max(1, (int) $filters['limit']);
            if (!empty($filters['offset'])) $sql .= ' OFFSET ' . max(0, (int)$filters['offset']);
        }
        if ($params) $sql = $wpdb->prepare($sql, $params);
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        $total = !empty($filters['with_total']) ? (int)$wpdb->get_var('SELECT FOUND_ROWS()') : count($rows);
        if ($total < count($rows)) $total = count($rows);

        $answersBySubmission = self::load_answers_by_submission($rows);
        $columns = [];
        $seen = [];
        foreach ($rows as &$row) {
            $row['client_id'] = (int)($row['resolved_client_id'] ?? $row['client_id'] ?? 0);
            $row['project_id'] = (int)($row['resolved_project_id'] ?? $row['project_id'] ?? 0);
            $row['route_id'] = (int)($row['resolved_route_id'] ?? $row['route_id'] ?? 0);
            $row['route_stop_id'] = (int)($row['resolved_route_stop_id'] ?? $row['route_stop_id'] ?? 0);
            $row['location_id'] = (int)($row['resolved_location_id'] ?? $row['location_id'] ?? 0);
            $answers = (array)($answersBySubmission[(int)$row['id']] ?? []);
            $answers = self::enrich_answers_with_question_types($answers, (int)($row['form_id'] ?? 0));
            $presentation = \RoutesPro\Forms\SubmissionPresentation::build($answers);
            // fieldflow-client-report-scope-briefing-assignment-v1
            // Reports must expose only briefing questions that were eligible for the
            // exact store/date/form context. When an Operative is selected, the
            // campaign location assignment is the authority for briefing visibility.
            $briefingQuestions = self::briefing_questions_for_submission($row, $answers, $filters);
            $presentation['briefing_questions'] = $briefingQuestions;
            $presentation['summary']['briefing_questions'] = count($briefingQuestions);
            $presentation['summary']['briefing_answered'] = count(array_filter($briefingQuestions, static fn($q) => !empty($q['answered'])));
            if ($briefingQuestions) {
                $briefingAnswered = (int)$presentation['summary']['briefing_answered'];
                $presentation['preview'] = trim((string)($presentation['preview'] ?? ''));
                $briefingPreview = count($briefingQuestions) . ' briefing · ' . $briefingAnswered . ' respondida' . ($briefingAnswered === 1 ? '' : 's');
                $presentation['preview'] = $presentation['preview'] !== '' ? ($presentation['preview'] . ' · ' . $briefingPreview) : $briefingPreview;
            }
            $row['presentation'] = $presentation;
            $row['answers'] = [];
            if (empty($filters['include_wide'])) continue;
            foreach ($answers as $a) {
                $key = sanitize_key($a['question_key'] ?: ('q_' . $a['id']));
                $label = (string) ($a['question_label'] ?: $a['question_key']);
                $order_rows = self::order_suggestion_rows_from_answer($a);
                if ($order_rows !== null) {
                    foreach ($order_rows as $orow) {
                        $category = trim((string)($orow['category'] ?? ''));
                        $ref = trim((string)($orow['ref'] ?? ''));
                        $name = trim((string)($orow['name'] ?? ''));
                        $product_label = trim(($category ? $category . ' | ' : '') . ($ref ? $ref . ' · ' : '') . ($name ?: 'Produto'));
                        $boxes_key = sanitize_key($key . '_' . md5($product_label . '_order_boxes'));
                        $units_key = sanitize_key($key . '_' . md5($product_label . '_order_units'));
                        $notes_key = sanitize_key($key . '_' . md5($product_label . '_order_notes'));
                        if (!isset($seen[$boxes_key])) { $seen[$boxes_key] = true; $columns[] = ['key'=>$boxes_key,'label'=>$label . ' | ' . $product_label . ' | Caixas','parent_key'=>$key,'product'=>$product_label,'category'=>$category,'metric'=>'order_boxes']; }
                        if (!isset($seen[$units_key])) { $seen[$units_key] = true; $columns[] = ['key'=>$units_key,'label'=>$label . ' | ' . $product_label . ' | Unidades','parent_key'=>$key,'product'=>$product_label,'category'=>$category,'metric'=>'order_units']; }
                        if (!isset($seen[$notes_key])) { $seen[$notes_key] = true; $columns[] = ['key'=>$notes_key,'label'=>$label . ' | ' . $product_label . ' | Observações','parent_key'=>$key,'product'=>$product_label,'category'=>$category,'metric'=>'order_notes']; }
                        $row['answers'][$boxes_key] = (string)($orow['order_qty'] ?? '');
                        $row['answers'][$units_key] = (string)($orow['units_total'] ?? '');
                        $row['answers'][$notes_key] = (string)($orow['notes'] ?? '');
                    }
                    continue;
                }
                $matrix_rows = self::product_matrix_rows_from_answer($a);
                if ($matrix_rows !== null) {
                    $summary_parts = [];
                    foreach ($matrix_rows as $mrow) {
                        $category = trim((string)($mrow['category'] ?? $mrow['categoria'] ?? ''));
                        $ref = trim((string)($mrow['ref'] ?? ''));
                        $name = trim((string)($mrow['name'] ?? ''));
                        $qty = $mrow['qty'] ?? '';
                        $before = $mrow['before'] ?? null;
                        $after = $mrow['after'] ?? null;
                        $prod_label = trim(($category ? $category . ' | ' : '') . ($ref ? $ref . ' · ' : '') . ($name ?: 'Produto'));
                        if ($before !== null || $after !== null) {
                            $before_key = sanitize_key($key . '_' . md5($prod_label . '_before'));
                            $after_key = sanitize_key($key . '_' . md5($prod_label . '_after'));
                            if (!isset($seen[$before_key])) { $seen[$before_key] = true; $columns[] = ['key' => $before_key, 'label' => $label . ' | ' . $prod_label . ' | Antes', 'parent_key' => $key, 'product' => $prod_label, 'category' => $category, 'period' => 'before']; }
                            if (!isset($seen[$after_key])) { $seen[$after_key] = true; $columns[] = ['key' => $after_key, 'label' => $label . ' | ' . $prod_label . ' | Depois', 'parent_key' => $key, 'product' => $prod_label, 'category' => $category, 'period' => 'after']; }
                            $row['answers'][$before_key] = (string)($before ?? '');
                            $row['answers'][$after_key] = (string)($after ?? $qty);
                        } else {
                            $col_key = sanitize_key($key . '_' . md5($prod_label));
                            if (!isset($seen[$col_key])) {
                                $seen[$col_key] = true;
                                $columns[] = ['key' => $col_key, 'label' => $label . ' | ' . $prod_label, 'parent_key' => $key, 'product' => $prod_label, 'category' => $category];
                            }
                            $row['answers'][$col_key] = (string)$qty;
                        }
                    }
                    // Product matrix answers are expanded into one column per product.
                    // Do not add the old aggregated "Resumo" column to the tabular dataset,
                    // because it makes BO, client portal and exports unnecessarily wide.
                    continue;
                }
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $columns[] = ['key' => $key, 'label' => $label];
                }
                $row['answers'][$key] = self::answer_to_string($a);
            }
            foreach ($briefingQuestions as $briefingQuestion) {
                $briefingKey = sanitize_key((string)($briefingQuestion['key'] ?? ''));
                if ($briefingKey === '') continue;
                if (!array_key_exists($briefingKey, $row['answers'])) {
                    $row['answers'][$briefingKey] = (string)($briefingQuestion['answer'] ?? '');
                }
            }
        }
        unset($row);
        if (!empty($filters['include_wide'])) $columns = self::append_context_question_columns($columns, $seen, $filters, $rows);
        return ['rows' => $rows, 'columns' => $columns, 'total' => $total];
    }

    /**
     * Client reporting dataset with visit identity as the authority.
     *
     * fieldflow-visit-centric-reporting-briefing-continuity-v1
     * A report represents one terminal route stop, not one form submission. The
     * latest operational submission is optional content attached to that visit.
     * This keeps completed visits and justified non-visits visible even when no
     * final form row exists, while preserving the canonical store briefing scope.
     */
    public static function get_visit_report_dataset(array $filters = []): array {
        global $wpdb;
        $filters = wp_parse_args($filters, [
            'client_id' => 0,
            'project_id' => 0,
            'owner_user_id' => 0,
            'route_id' => 0,
            'location_id' => 0,
            'date_from' => '',
            'date_to' => '',
            'limit' => 1000,
            'scope_user_id' => 0,
            'offset' => 0,
            'with_total' => false,
            'include_wide' => false,
        ]);

        $px = $wpdb->prefix . 'routespro_';
        $submissions = FormsModule::table_submissions();
        $forms = FormsModule::table();
        $terminalSql = \RoutesPro\Services\VisitCompletionPolicy::terminalSql('rs');
        $operationalSubmissionSql = \RoutesPro\Services\SubmissionStatusPolicy::operationalSql('s2');

        // A terminal route_stop is the report identity. LEFT JOIN is deliberate:
        // a visit remains reportable when no operational form submission exists.
        $sql = 'SELECT ' . (!empty($filters['with_total']) ? 'SQL_CALC_FOUND_ROWS ' : '') . '
            rs.id AS visit_report_id,
            rs.id AS stop_row_id,
            rs.id AS route_stop_id,
            rs.route_id,
            rs.location_id,
            rs.seq AS stop_seq,
            rs.status AS stop_status,
            rs.planned_arrival,
            rs.planned_departure,
            rs.arrived_at,
            rs.departed_at,
            rs.duration_s,
            rs.note AS stop_note,
            rs.fail_reason,
            rs.real_lat,
            rs.real_lng,
            rs.qty,
            rs.weight,
            rs.volume,
            r.client_id,
            r.project_id,
            r.date AS route_date,
            r.status AS route_status,
            r.owner_user_id AS joined_owner_user_id,
            route_owner.display_name AS route_owner_name,
            c.name AS client_name,
            p.name AS project_name,
            loc.name AS location_name,
            s.id AS submission_id,
            s.form_id,
            s.binding_id,
            s.user_id,
            s.status AS submission_status,
            s.submitted_at,
            s.report_day AS submission_report_day,
            s.meta_json,
            f.title AS form_title,
            submitter.display_name AS user_name,
            (SELECT cl.assigned_to
             FROM ' . $px . 'campaign_locations cl
             WHERE cl.project_id=r.project_id AND cl.location_id=rs.location_id
               AND cl.is_active=1 AND COALESCE(cl.assigned_to,0)>0
             ORDER BY cl.id DESC LIMIT 1) AS campaign_assigned_to,
            (SELECT ucl.display_name
             FROM ' . $px . 'campaign_locations cln
             INNER JOIN ' . $wpdb->users . ' ucl ON ucl.ID=cln.assigned_to
             WHERE cln.project_id=r.project_id AND cln.location_id=rs.location_id
               AND cln.is_active=1 AND COALESCE(cln.assigned_to,0)>0
             ORDER BY cln.id DESC LIMIT 1) AS campaign_assigned_name
        FROM ' . $px . 'routes r
        INNER JOIN ' . $px . 'route_stops rs ON rs.route_id=r.id
        LEFT JOIN ' . $px . 'locations loc ON loc.id=rs.location_id
        LEFT JOIN ' . $px . 'projects p ON p.id=r.project_id
        LEFT JOIN ' . $px . 'clients c ON c.id=r.client_id
        LEFT JOIN ' . $wpdb->users . ' route_owner ON route_owner.ID=r.owner_user_id
        LEFT JOIN ' . $submissions . ' s ON s.id=(
            SELECT s2.id
            FROM ' . $submissions . ' s2
            WHERE ' . $operationalSubmissionSql . '
              AND (
                s2.route_stop_id=rs.id
                OR (
                    COALESCE(s2.route_stop_id,0)=0
                    AND s2.location_id=rs.location_id
                    AND NOT EXISTS (
                        SELECT 1 FROM ' . $px . 'route_stops rs_dup
                        WHERE rs_dup.route_id=r.id AND rs_dup.location_id=rs.location_id AND rs_dup.id<>rs.id
                    )
                    AND (
                        s2.route_id=r.id
                        OR (
                            COALESCE(s2.route_id,0)=0
                            AND (s2.report_day=r.date OR (s2.report_day IS NULL AND DATE(s2.submitted_at)=r.date))
                        )
                    )
                    AND (COALESCE(s2.project_id,0)=0 OR s2.project_id=r.project_id)
                    AND (COALESCE(s2.client_id,0)=0 OR s2.client_id=r.client_id)
                )
              )
            ORDER BY CASE WHEN s2.route_stop_id=rs.id THEN 0 WHEN s2.route_id=r.id THEN 1 ELSE 2 END ASC, s2.id DESC
            LIMIT 1
        )
        LEFT JOIN ' . $forms . ' f ON f.id=s.form_id
        LEFT JOIN ' . $wpdb->users . ' submitter ON submitter.ID=s.user_id
        WHERE ' . $terminalSql;

        $params = [];
        if (!empty($filters['client_id'])) { $sql .= ' AND r.client_id=%d'; $params[] = (int)$filters['client_id']; }
        if (!empty($filters['project_id'])) { $sql .= ' AND r.project_id=%d'; $params[] = (int)$filters['project_id']; }
        if (!empty($filters['route_id'])) { $sql .= ' AND r.id=%d'; $params[] = (int)$filters['route_id']; }
        if (!empty($filters['location_id'])) { $sql .= ' AND rs.location_id=%d'; $params[] = (int)$filters['location_id']; }
        if (!empty($filters['date_from'])) { $sql .= ' AND r.date>=%s'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to'])) { $sql .= ' AND r.date<=%s'; $params[] = $filters['date_to']; }

        $ownerId = absint($filters['owner_user_id'] ?? 0);
        if ($ownerId > 0) {
            // Operative scope is intentionally wider than route.owner_user_id.
            // Historical route/team assignment, current campaign-store assignment
            // and the actual submitter can all prove that this visit belongs to the
            // selected operative. Briefing visibility remains stricter and uses the
            // campaign-store mapping below.
            $sql .= ' AND (
                r.owner_user_id=%d
                OR EXISTS (SELECT 1 FROM ' . $px . 'assignments ao WHERE ao.route_id=r.id AND ao.user_id=%d AND ao.is_active=1)
                OR EXISTS (SELECT 1 FROM ' . $px . 'campaign_locations clo WHERE clo.project_id=r.project_id AND clo.location_id=rs.location_id AND clo.assigned_to=%d AND clo.is_active=1)
                OR s.user_id=%d
            )';
            array_push($params, $ownerId, $ownerId, $ownerId, $ownerId);
        }

        $scopeUserId = absint($filters['scope_user_id'] ?? 0);
        if ($scopeUserId > 0 && !\RoutesPro\Support\Permissions::is_manager($scopeUserId)) {
            [$scopeSql, $scopeArgs] = \RoutesPro\Support\Permissions::scope_sql('r', 'r.client_id', 'r.project_id', $scopeUserId);
            $sql .= ' AND ' . $scopeSql;
            $params = array_merge($params, $scopeArgs);
        }

        $sql .= ' ORDER BY r.date DESC,r.id DESC,rs.seq ASC,rs.id ASC';
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . max(1, (int)$filters['limit']);
            if (!empty($filters['offset'])) $sql .= ' OFFSET ' . max(0, (int)$filters['offset']);
        }
        if ($params) $sql = $wpdb->prepare($sql, $params);
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        $total = !empty($filters['with_total']) ? (int)$wpdb->get_var('SELECT FOUND_ROWS()') : count($rows);
        if ($total < count($rows)) $total = count($rows);

        $submissionRows = [];
        foreach ($rows as $row) {
            $submissionId = absint($row['submission_id'] ?? 0);
            if ($submissionId > 0) $submissionRows[] = ['id'=>$submissionId];
        }
        $answersBySubmission = self::load_answers_by_submission($submissionRows);

        $selectedOwnerName = '';
        if ($ownerId > 0) {
            $selectedOwnerName = (string)$wpdb->get_var($wpdb->prepare('SELECT display_name FROM ' . $wpdb->users . ' WHERE ID=%d LIMIT 1', $ownerId));
        }

        foreach ($rows as &$row) {
            $submissionId = absint($row['submission_id'] ?? 0);
            $row['id'] = $submissionId > 0 ? $submissionId : absint($row['visit_report_id'] ?? 0);
            $row['report_id'] = absint($row['visit_report_id'] ?? 0);
            $row['report_day'] = sanitize_text_field((string)($row['route_date'] ?? ''));
            $row['submitted_at'] = trim((string)($row['submitted_at'] ?? ''));
            if ($row['submitted_at'] === '') {
                $row['submitted_at'] = trim((string)($row['departed_at'] ?? '')) ?: trim((string)($row['arrived_at'] ?? ''));
            }
            if ($row['submitted_at'] === '' && $row['report_day'] !== '') $row['submitted_at'] = $row['report_day'] . ' 00:00:00';

            if ($submissionId <= 0 && absint($row['form_id'] ?? 0) <= 0) {
                $binding = \RoutesPro\Forms\BindingResolver::resolve([
                    'client_id'=>absint($row['client_id'] ?? 0),
                    'project_id'=>absint($row['project_id'] ?? 0),
                    'route_id'=>absint($row['route_id'] ?? 0),
                    'route_stop_id'=>absint($row['route_stop_id'] ?? 0),
                    'location_id'=>absint($row['location_id'] ?? 0),
                    'date'=>(string)($row['route_date'] ?? ''),
                ]);
                if (is_array($binding)) {
                    $row['form_id'] = absint($binding['form_id'] ?? 0);
                    $row['binding_id'] = absint($binding['id'] ?? 0);
                    $row['form_title'] = sanitize_text_field((string)($binding['form_title'] ?? ''));
                }
            }

            $campaignOwnerId = absint($row['campaign_assigned_to'] ?? 0);
            $effectiveOwnerId = $ownerId > 0 ? $ownerId : ($campaignOwnerId ?: absint($row['joined_owner_user_id'] ?? 0));
            if ($effectiveOwnerId <= 0) $effectiveOwnerId = absint($row['user_id'] ?? 0);
            $row['owner_user_id'] = $effectiveOwnerId;
            $row['owner_name'] = $selectedOwnerName !== '' ? $selectedOwnerName : trim((string)($row['campaign_assigned_name'] ?? ''));
            if ($row['owner_name'] === '') $row['owner_name'] = trim((string)($row['route_owner_name'] ?? ''));
            if ($row['owner_name'] === '') $row['owner_name'] = trim((string)($row['user_name'] ?? ''));

            $answers = $submissionId > 0 ? (array)($answersBySubmission[$submissionId] ?? []) : [];
            $answers = self::enrich_answers_with_question_types($answers, absint($row['form_id'] ?? 0));
            $presentation = \RoutesPro\Forms\SubmissionPresentation::build($answers);

            $briefingQuestions = self::briefing_questions_for_submission($row, $answers, $filters);
            $presentation['briefing_questions'] = $briefingQuestions;
            $presentation['summary']['briefing_questions'] = count($briefingQuestions);
            $presentation['summary']['briefing_answered'] = count(array_filter($briefingQuestions, static fn($q) => !empty($q['answered'])));

            $justified = \RoutesPro\Services\VisitCompletionPolicy::stopIsJustifiedNonVisit((string)($row['stop_status'] ?? ''), (string)($row['fail_reason'] ?? ''));
            $executed = \RoutesPro\Services\VisitCompletionPolicy::stopIsExecuted((string)($row['stop_status'] ?? ''));
            $hasSubmission = $submissionId > 0;
            $visitState = $justified ? 'justified_non_visit' : ($hasSubmission ? 'completed_with_report' : 'completed_without_submission');
            $visitLabel = $justified ? 'Não visita justificada' : ($hasSubmission ? 'Visita concluída' : 'Visita concluída sem submissão');
            $presentation['visit'] = [
                'id'=>absint($row['route_stop_id'] ?? 0),
                'state'=>$visitState,
                'label'=>$visitLabel,
                'executed'=>$executed,
                'justified_non_visit'=>$justified,
                'has_submission'=>$hasSubmission,
                'submission_id'=>$submissionId,
                'reason'=>$justified ? sanitize_text_field((string)($row['fail_reason'] ?? '')) : '',
            ];
            $presentation['summary']['visit_closed'] = 1;
            $presentation['summary']['justified_non_visit'] = $justified ? 1 : 0;
            $presentation['summary']['has_submission'] = $hasSubmission ? 1 : 0;

            $previewParts = [];
            if ($justified) $previewParts[] = $visitLabel . ': ' . sanitize_text_field((string)($row['fail_reason'] ?? ''));
            elseif (!$hasSubmission) $previewParts[] = $visitLabel;
            $basePreview = trim((string)($presentation['preview'] ?? ''));
            if ($basePreview !== '') $previewParts[] = $basePreview;
            if ($briefingQuestions) {
                $briefingAnswered = (int)$presentation['summary']['briefing_answered'];
                $previewParts[] = count($briefingQuestions) . ' briefing · ' . $briefingAnswered . ' respondida' . ($briefingAnswered === 1 ? '' : 's');
            }
            $presentation['preview'] = implode(' · ', array_values(array_filter($previewParts, static fn($v) => trim((string)$v) !== '')));
            $row['presentation'] = $presentation;
            $row['answers'] = [];
            $row['status'] = $hasSubmission ? sanitize_key((string)($row['submission_status'] ?? 'submitted')) : ($justified ? 'justified_non_visit' : 'completed');
        }
        unset($row);

        return ['rows'=>$rows,'columns'=>[],'total'=>$total,'report_mode'=>'visits'];
    }

    /**
     * Carrega todas as respostas das submissões pedidas em lotes.
     *
     * A implementação anterior executava uma query por submissão. Em exportações
     * com milhares de reportes isso criava milhares de round-trips à base de dados
     * antes de o ficheiro começar sequer a ser construído.
     */
    /**
     * Resolve which order-suggestion questions were actually active for a
     * submission. New submissions carry a conditional-flow snapshot. Legacy
     * submissions are evaluated from their saved answers and the current form
     * schema as a conservative fallback.
     *
     * null means the visibility could not be resolved safely and legacy
     * behaviour should be preserved. An empty array means the suggestion was
     * explicitly inactive and must not be mailed.
     */
    /**
     * Resolve the effective order-suggestion mail state from the submission-time
     * snapshot and, when safe, a fresh evaluation of the answers persisted in DB.
     *
     * Email is intentionally conservative: when either trustworthy source says
     * the conditional branch was inactive, no order-suggestion email is allowed.
     */
    public static function resolveOrderSuggestionMailEligibility(
        array $orderKeys,
        array $snapshotActiveKeys,
        array $snapshotHiddenKeys,
        ?array $canonicalActiveKeys,
        bool $snapshotResolved = true
    ): array {
        $normalise = static function(array $keys): array {
            return array_values(array_unique(array_filter(array_map('sanitize_key', $keys))));
        };
        $all = $normalise($orderKeys);
        $snapshotActive = $normalise($snapshotActiveKeys);
        $snapshotHidden = $normalise($snapshotHiddenKeys);
        $canonical = is_array($canonicalActiveKeys) ? $normalise($canonicalActiveKeys) : null;

        if ($snapshotResolved) {
            if (!$all) return ['eligible'=>false, 'active_keys'=>[], 'reason'=>'no_order_questions'];
            $snapshotActive = array_values(array_diff(array_intersect($all, $snapshotActive), $snapshotHidden));
            if (!$snapshotActive) return ['eligible'=>false, 'active_keys'=>[], 'reason'=>'snapshot_inactive'];
        } else {
            $snapshotActive = null;
        }

        if (is_array($canonical) && !$canonical) {
            return ['eligible'=>false, 'active_keys'=>[], 'reason'=>'canonical_inactive'];
        }

        if (is_array($snapshotActive) && is_array($canonical)) {
            $effective = array_values(array_intersect($snapshotActive, $canonical));
            if (!$effective) return ['eligible'=>false, 'active_keys'=>[], 'reason'=>'conditional_disagreement_inactive'];
            return ['eligible'=>true, 'active_keys'=>$effective, 'reason'=>'snapshot_and_canonical_active'];
        }
        if (is_array($snapshotActive)) {
            return ['eligible'=>true, 'active_keys'=>$snapshotActive, 'reason'=>'snapshot_active'];
        }
        if (is_array($canonical)) {
            return ['eligible'=>true, 'active_keys'=>$canonical, 'reason'=>'canonical_active'];
        }
        // Legacy/unresolvable submissions preserve the previous behaviour. This
        // branch cannot override an explicit inactive snapshot or canonical veto.
        return ['eligible'=>true, 'active_keys'=>null, 'reason'=>'legacy_unresolved'];
    }

    public static function orderSuggestionMailEligibility(int $submissionId): array {
        global $wpdb;
        $submissionId = absint($submissionId);
        if ($submissionId <= 0) return ['eligible'=>false, 'active_keys'=>[], 'reason'=>'invalid_submission'];
        $submission = $wpdb->get_row($wpdb->prepare(
            'SELECT id,form_id,meta_json FROM ' . FormsModule::table_submissions() . ' WHERE id=%d LIMIT 1',
            $submissionId
        ), ARRAY_A);
        if (!$submission) return ['eligible'=>false, 'active_keys'=>[], 'reason'=>'submission_missing'];

        $meta = json_decode((string)($submission['meta_json'] ?? ''), true);
        $meta = is_array($meta) ? $meta : [];
        $flow = is_array($meta['conditional_flow'] ?? null) ? $meta['conditional_flow'] : null;
        $snapshotResolved = is_array($flow) && array_key_exists('order_suggestion_keys', $flow);
        $orderKeys = $snapshotResolved ? (array)($flow['order_suggestion_keys'] ?? []) : [];
        $snapshotActive = $snapshotResolved ? (array)($flow['active_order_suggestion_keys'] ?? []) : [];
        $snapshotHidden = $snapshotResolved ? (array)($flow['hidden_question_keys'] ?? []) : [];

        $canonical = self::canonicalOrderSuggestionKeysFromPersistedAnswers($submission, $meta, $orderKeys);
        $state = self::resolveOrderSuggestionMailEligibility(
            $orderKeys,
            $snapshotActive,
            $snapshotHidden,
            $canonical,
            $snapshotResolved
        );

        // 9BQ: persisted positive order lines are the final authority for mail
        // eligibility unless the submission snapshot explicitly hid the order
        // question. This prevents a stale conditional re-evaluation from
        // silently suppressing a real order entered by the merchandiser.
        $evidence = self::persistedOrderSuggestionEvidence($submissionId, $submission, $meta);
        if (empty($state['eligible']) && !empty($evidence['has_visible_positive_order'])) {
            $state = [
                'eligible' => true,
                'active_keys' => (array)($evidence['visible_positive_keys'] ?? []),
                'reason' => 'persisted_positive_order_authority',
                'baseline_reason' => sanitize_key((string)($state['reason'] ?? 'inactive')),
            ];
        }
        $state['persisted_evidence'] = $evidence;
        return $state;
    }

    public static function persistedOrderSuggestionEvidence(int $submissionId, array $submission = [], array $meta = []): array {
        global $wpdb;
        $submissionId = absint($submissionId);
        if ($submissionId <= 0) {
            return ['has_positive_order'=>false,'has_visible_positive_order'=>false,'positive_keys'=>[],'visible_positive_keys'=>[],'hidden_positive_keys'=>[],'positive_rows'=>0];
        }
        if (!$submission) {
            $submission = $wpdb->get_row($wpdb->prepare(
                'SELECT id,form_id,meta_json FROM ' . FormsModule::table_submissions() . ' WHERE id=%d LIMIT 1',
                $submissionId
            ), ARRAY_A) ?: [];
        }
        if (!$submission) {
            return ['has_positive_order'=>false,'has_visible_positive_order'=>false,'positive_keys'=>[],'visible_positive_keys'=>[],'hidden_positive_keys'=>[],'positive_rows'=>0];
        }
        if (!$meta) {
            $decoded = json_decode((string)($submission['meta_json'] ?? ''), true);
            $meta = is_array($decoded) ? $decoded : [];
        }
        $flow = is_array($meta['conditional_flow'] ?? null) ? $meta['conditional_flow'] : [];
        $snapshotResolved = array_key_exists('order_suggestion_keys', $flow);
        $snapshotOrderKeys = array_values(array_unique(array_filter(array_map('sanitize_key', (array)($flow['order_suggestion_keys'] ?? [])))));
        $hiddenKeys = array_values(array_unique(array_filter(array_map('sanitize_key', (array)($flow['hidden_question_keys'] ?? [])))));
        if ($snapshotResolved && !$snapshotOrderKeys) {
            return ['has_positive_order'=>false,'has_visible_positive_order'=>false,'positive_keys'=>[],'visible_positive_keys'=>[],'hidden_positive_keys'=>[],'positive_rows'=>0];
        }

        $answerRows = $wpdb->get_results($wpdb->prepare(
            'SELECT question_key,value_text,value_number,value_json FROM ' . FormsModule::table_answers() . ' WHERE submission_id=%d ORDER BY id ASC',
            $submissionId
        ), ARRAY_A) ?: [];
        $positiveKeys = [];
        $visiblePositiveKeys = [];
        $hiddenPositiveKeys = [];
        $positiveRows = 0;
        foreach ($answerRows as $answer) {
            $key = sanitize_key((string)($answer['question_key'] ?? ''));
            if ($key === '') continue;
            if ($snapshotResolved && $snapshotOrderKeys && !in_array($key, $snapshotOrderKeys, true)) continue;
            $rows = self::order_suggestion_rows_from_answer((array)$answer);
            if ($rows === null) continue;
            $hasPositive = false;
            foreach ($rows as $row) {
                $qty = $row['order_qty'] ?? '';
                $units = $row['units_total'] ?? '';
                $qtyPositive = is_numeric($qty) && (float)$qty > 0;
                $unitsPositive = is_numeric($units) && (float)$units > 0;
                if ($qtyPositive || $unitsPositive) {
                    $positiveRows++;
                    $hasPositive = true;
                }
            }
            if (!$hasPositive) continue;
            $positiveKeys[] = $key;
            if (in_array($key, $hiddenKeys, true)) $hiddenPositiveKeys[] = $key;
            else $visiblePositiveKeys[] = $key;
        }

        $positiveKeys = array_values(array_unique($positiveKeys));
        $visiblePositiveKeys = array_values(array_unique($visiblePositiveKeys));
        $hiddenPositiveKeys = array_values(array_unique($hiddenPositiveKeys));
        return [
            'has_positive_order' => !empty($positiveKeys),
            'has_visible_positive_order' => !empty($visiblePositiveKeys),
            'positive_keys' => $positiveKeys,
            'visible_positive_keys' => $visiblePositiveKeys,
            'hidden_positive_keys' => $hiddenPositiveKeys,
            'positive_rows' => $positiveRows,
        ];
    }

    private static function canonicalOrderSuggestionKeysFromPersistedAnswers(array $submission, array $meta, array $snapshotOrderKeys = []): ?array {
        global $wpdb;
        $formId = absint($submission['form_id'] ?? 0);
        if ($formId <= 0 || !class_exists('\\RoutesPro\\Forms\\ConditionalFlow')) return null;
        $form = FormsModule::get_form($formId);
        if (!$form) return null;

        // Re-evaluate only while the form definition is still the one used for
        // this submission. Historical resends remain governed by their snapshot
        // after a BO form edit, avoiding false negatives caused by schema drift.
        $savedDefinition = sanitize_text_field((string)($meta['form_definition']['server_version'] ?? ''));
        $currentDefinition = FormsModule::definition_version($form);
        if ($savedDefinition !== '' && $currentDefinition !== '' && !hash_equals($savedDefinition, $currentDefinition)) return null;

        $schema = FormsModule::decode_schema((string)($form['schema_json'] ?? ''));
        $schema = \RoutesPro\Forms\ConditionalFlow::normalise_schema($schema);
        $orderKeys = [];
        foreach ((array)($schema['questions'] ?? []) as $question) {
            if (!is_array($question)) continue;
            if (sanitize_key((string)($question['type'] ?? '')) !== 'order_suggestion') continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($key !== '') $orderKeys[] = $key;
        }
        // Dynamic schemas may inject the order question outside the stored base
        // schema. In that case the submission snapshot remains the authority.
        if (!$orderKeys) return null;
        if ($snapshotOrderKeys && !array_intersect(array_map('sanitize_key', $snapshotOrderKeys), $orderKeys)) return null;

        $answerRows = $wpdb->get_results($wpdb->prepare(
            'SELECT question_key,value_text,value_number,value_json FROM ' . FormsModule::table_answers() . ' WHERE submission_id=%d ORDER BY id ASC',
            absint($submission['id'] ?? 0)
        ), ARRAY_A) ?: [];
        $values = [];
        foreach ($answerRows as $answer) {
            $key = sanitize_key((string)($answer['question_key'] ?? ''));
            if ($key === '') continue;
            $json = (string)($answer['value_json'] ?? '');
            if ($json !== '') {
                $decoded = json_decode($json, true);
                $values[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $json;
            } elseif ($answer['value_number'] !== null && $answer['value_number'] !== '') {
                $values[$key] = (float)$answer['value_number'];
            } else {
                $values[$key] = (string)($answer['value_text'] ?? '');
            }
        }
        $payload = \RoutesPro\Forms\ConditionalFlow::payload($schema, $values);
        $visibility = is_array($payload['initial_visibility'] ?? null) ? $payload['initial_visibility'] : [];
        $active = [];
        foreach ($orderKeys as $key) {
            if (!array_key_exists($key, $visibility) || !empty($visibility[$key])) $active[] = $key;
        }
        return array_values(array_unique($active));
    }

    public static function activeOrderSuggestionKeys(int $submissionId): ?array {
        $state = self::orderSuggestionMailEligibility($submissionId);
        if (empty($state['eligible'])) return [];
        return is_array($state['active_keys'] ?? null) ? (array)$state['active_keys'] : null;
    }

    public static function getOrderSuggestionSubmission(int $submissionId): array {
        $submissionId = absint($submissionId);
        if ($submissionId <= 0) return [];
        $mailEligibility = self::orderSuggestionMailEligibility($submissionId);
        if (empty($mailEligibility['eligible'])) return [];
        $activeOrderKeys = is_array($mailEligibility['active_keys'] ?? null) ? (array)$mailEligibility['active_keys'] : null;
        $dataset = self::get_submission_dataset([
            'submission_id' => $submissionId,
            'limit' => 1,
            'scope_user_id' => 0,
            'include_wide' => false,
        ]);
        $row = (array)($dataset['rows'][0] ?? []);
        if (!$row) return [];
        $presentation = is_array($row['presentation'] ?? null) ? $row['presentation'] : [];
        $summary = is_array($presentation['summary'] ?? null) ? $presentation['summary'] : [];
        if ((int)($summary['orders'] ?? 0) <= 0) return [];
        $rows = [];
        foreach ((array)($presentation['order_questions'] ?? []) as $question) {
            $questionKey = sanitize_key((string)($question['key'] ?? ''));
            if (is_array($activeOrderKeys) && $questionKey !== '' && !in_array($questionKey, $activeOrderKeys, true)) continue;
            foreach ((array)($question['rows'] ?? []) as $order) {
                $rows[] = [
                    'question' => (string)($question['label'] ?? 'Sugestão de encomenda'),
                    'category' => (string)($order['category'] ?? ''),
                    'ref' => (string)($order['ref'] ?? ''),
                    'name' => (string)($order['name'] ?? ''),
                    'item_type' => (string)($order['item_type'] ?? ''),
                    'capacity_cl' => $order['capacity_cl'] ?? '',
                    'units_per_case' => $order['units_per_case'] ?? '',
                    'order_qty' => $order['order_qty'] ?? '',
                    'units_total' => $order['units_total'] ?? '',
                    'notes' => (string)($order['notes'] ?? ''),
                ];
            }
        }
        if (!$rows) return [];
        return [
            'submission_id' => $submissionId,
            'submitted_at' => (string)($row['submitted_at'] ?? ''),
            'report_day' => (string)($row['report_day'] ?? substr((string)($row['submitted_at'] ?? ''), 0, 10)),
            'client_id' => absint($row['client_id'] ?? 0),
            'client_name' => (string)($row['client_name'] ?? ''),
            'project_id' => absint($row['project_id'] ?? 0),
            'project_name' => (string)($row['project_name'] ?? ''),
            'route_id' => absint($row['route_id'] ?? 0),
            'route_stop_id' => absint($row['route_stop_id'] ?? 0),
            'location_id' => absint($row['location_id'] ?? 0),
            'location_name' => (string)($row['location_name'] ?? ''),
            'operative_user_id' => absint($row['user_id'] ?? $row['joined_owner_user_id'] ?? $row['owner_user_id'] ?? 0),
            'operative_name' => (string)($row['user_name'] ?? $row['owner_name'] ?? ''),
            'form_id' => absint($row['form_id'] ?? 0),
            'form_name' => (string)($row['form_title'] ?? ''),
            'rows' => $rows,
            'mail_eligibility' => $mailEligibility,
            'summary' => [
                'orders' => (int)($summary['orders'] ?? count($rows)),
                'order_boxes' => $summary['order_boxes'] ?? 0,
                'order_units' => $summary['order_units'] ?? 0,
            ],
        ];
    }

    public static function orderSuggestionExportRows(array $filters = []): array {
        $dataset = self::get_submission_dataset([
            'client_id' => absint($filters['client_id'] ?? 0),
            'project_id' => absint($filters['project_id'] ?? 0),
            'report_user_id' => absint($filters['user_id'] ?? $filters['owner_user_id'] ?? 0),
            'report_role' => (string)($filters['role'] ?? ''),
            'route_id' => absint($filters['route_id'] ?? 0),
            'location_id' => absint($filters['location_id'] ?? 0),
            'date_from' => (string)($filters['from'] ?? $filters['date_from'] ?? ''),
            'date_to' => (string)($filters['to'] ?? $filters['date_to'] ?? ''),
            'limit' => 0,
            'scope_user_id' => get_current_user_id(),
            'operational_only' => true,
            'include_wide' => false,
        ]);
        $rows = [];
        $categoryFilter = trim((string)($filters['category'] ?? ''));
        $productFilter = trim((string)($filters['product'] ?? ''));
        foreach ((array)($dataset['rows'] ?? []) as $submission) {
            if (!\RoutesPro\Services\SubmissionStatusPolicy::isOperational($submission['status'] ?? null)) continue;
            $presentation = is_array($submission['presentation'] ?? null) ? $submission['presentation'] : [];
            foreach ((array)($presentation['order_questions'] ?? []) as $question) {
                foreach ((array)($question['rows'] ?? []) as $order) {
                    $category = trim((string)($order['category'] ?? ''));
                    $ref = trim((string)($order['ref'] ?? ''));
                    $name = trim((string)($order['name'] ?? ''));
                    if ($categoryFilter !== '' && strcasecmp($categoryFilter, $category) !== 0) continue;
                    if ($productFilter !== '') {
                        $haystack = trim($ref . ' ' . $name);
                        $productLabel = trim(($ref !== '' ? $ref . ' · ' : '') . ($name ?: 'Produto'));
                        $filterLabel = trim(($category !== '' ? $category . ' | ' : '') . $productLabel);
                        if (!in_array($productFilter, [$productLabel, $filterLabel], true) && stripos($haystack, $productFilter) === false && strcasecmp($productFilter, $ref) !== 0 && strcasecmp($productFilter, $name) !== 0) continue;
                    }
                    $rows[] = [
                        (int)($submission['id'] ?? 0),
                        OperationalReportDayPolicy::resolve($submission['report_day'] ?? '', $submission['route_date'] ?? '', $submission['submitted_at'] ?? ''),
                        (string)($submission['client_name'] ?? ''),
                        (string)($submission['project_name'] ?? ''),
                        (string)($submission['user_name'] ?? $submission['owner_name'] ?? ''),
                        (string)($submission['location_name'] ?? ''),
                        (string)($question['label'] ?? 'Sugestão de encomenda'),
                        (string)($order['category'] ?? ''),
                        (string)($order['ref'] ?? ''),
                        (string)($order['name'] ?? ''),
                        (string)($order['item_type'] ?? ''),
                        (string)($order['capacity_cl'] ?? ''),
                        (string)($order['units_per_case'] ?? ''),
                        (string)($order['order_qty'] ?? ''),
                        (string)($order['units_total'] ?? ''),
                        (string)($order['notes'] ?? ''),
                    ];
                    if (!empty($filters['include_lineage'])) {
                        $last = count($rows) - 1;
                        $rows[$last]['location_id'] = absint($submission['resolved_location_id'] ?? $submission['location_id'] ?? 0);
                        $rows[$last]['route_id'] = absint($submission['resolved_route_id'] ?? $submission['route_id'] ?? 0);
                        $rows[$last]['submitted_at'] = (string)($submission['submitted_at'] ?? '');
                    }
                }
            }
        }
        return $rows;
    }

    public static function createOrderSuggestionReportFile(int $submissionId, string $format): array {
        $payload = self::getOrderSuggestionSubmission($submissionId);
        if (!$payload) throw new \RuntimeException('A submissão não contém uma sugestão de encomenda.');
        $format = sanitize_key($format);
        if ($format === 'excel' || $format === 'xls') $format = 'xlsx';
        if (!in_array($format, ['xlsx','pdf'], true)) throw new \RuntimeException('Formato de sugestão de encomenda inválido.');
        $dateSlug = preg_replace('/[^0-9]/', '', (string)($payload['report_day'] ?? '')) ?: gmdate('Ymd');
        $base = 'fieldflow-sugestao-encomenda-' . $submissionId . '-' . $dateSlug;
        $summary = (array)($payload['summary'] ?? []);
        $detailRows = [];
        foreach ((array)$payload['rows'] as $order) {
            $detailRows[] = [
                (string)($order['question'] ?? ''),(string)($order['category'] ?? ''),(string)($order['ref'] ?? ''),(string)($order['name'] ?? ''),
                (string)($order['item_type'] ?? ''),(string)($order['capacity_cl'] ?? ''),(string)($order['units_per_case'] ?? ''),
                (string)($order['order_qty'] ?? ''),(string)($order['units_total'] ?? ''),(string)($order['notes'] ?? ''),
            ];
        }
        if ($format === 'xlsx') {
            $sheets = [[
                'name'=>'Resumo','title'=>'Sugestão de encomenda','subtitle'=>(string)$payload['client_name'].' | '.(string)$payload['project_name'],
                'headers'=>['Indicador','Valor'],
                'rows'=>[
                    ['Submissão', '#'.$submissionId],['Data', (string)$payload['submitted_at']],['Loja', (string)$payload['location_name']],['Operativo', (string)$payload['operative_name']],
                    ['Referências sugeridas', (int)($summary['orders'] ?? count($detailRows))],['Total caixas', $summary['order_boxes'] ?? 0],['Total unidades', $summary['order_units'] ?? 0],
                ],
                'types'=>['text','auto'],'widths'=>[28,64],
            ],[
                'name'=>'Sugestão Encomenda','title'=>'Detalhe da sugestão de encomenda','subtitle'=>(string)$payload['location_name'].' | '.(string)$payload['submitted_at'],
                'headers'=>['Pergunta','Categoria','Referência','Produto','Tipo','Capacidade (cl)','Unidades / caixa','Caixas','Unidades totais','Observações'],
                'rows'=>$detailRows,'types'=>['text','text','text','text','text','auto','auto','auto','auto','text'],'widths'=>[34,22,18,38,18,16,18,14,16,46],
            ]];
            $filename = $base . '.xlsx';
            $path = self::create_dashboard_xlsx_file($filename, $sheets, ['client_name'=>$payload['client_name'],'project_name'=>$payload['project_name'],'period'=>$payload['report_day']]);
            return ['path'=>$path,'filename'=>$filename,'mime'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','format'=>'xlsx'];
        }
        $blocks = [
            ['type'=>'section','lines'=>['RESUMO']],
            ['type'=>'metric','lines'=>[
                'Cliente: '.(string)$payload['client_name'].' | Projeto: '.(string)$payload['project_name'],
                'Loja: '.(string)$payload['location_name'].' | Operativo: '.(string)$payload['operative_name'],
                'Data: '.(string)$payload['submitted_at'].' | Submissão #'.$submissionId,
                'Referências sugeridas: '.(int)($summary['orders'] ?? count($detailRows)).' | Caixas: '.(string)($summary['order_boxes'] ?? 0).' | Unidades: '.(string)($summary['order_units'] ?? 0),
            ]],
            ['type'=>'section','lines'=>['DETALHE DA SUGESTÃO']],
        ];
        foreach ((array)$payload['rows'] as $order) {
            $line = trim(implode(' | ', array_filter([
                (string)($order['category'] ?? ''),(string)($order['ref'] ?? ''),(string)($order['name'] ?? ''),
                ((string)($order['order_qty'] ?? '') !== '' ? 'Caixas: '.(string)$order['order_qty'] : ''),
                ((string)($order['units_total'] ?? '') !== '' ? 'Unidades: '.(string)$order['units_total'] : ''),
                ((string)($order['notes'] ?? '') !== '' ? 'Obs.: '.(string)$order['notes'] : ''),
            ], static fn($v) => $v !== '')));
            if ($line !== '') $blocks[] = ['type'=>'metric','lines'=>[$line]];
        }
        $pdf = self::build_simple_pdf([], ['title'=>'Sugestão de encomenda','subtitle'=>(string)$payload['location_name'],'summary'=>['FieldFlow','Submissão #'.$submissionId],'blocks'=>$blocks]);
        $tmp = wp_tempnam($base . '.pdf');
        if (!$tmp) throw new \RuntimeException('Não foi possível preparar o PDF da sugestão.');
        $target = trailingslashit(dirname($tmp)) . wp_unique_filename(dirname($tmp), $base . '.pdf');
        @unlink($target); @rename($tmp, $target);
        if (file_put_contents($target, $pdf) === false) throw new \RuntimeException('Não foi possível guardar o PDF da sugestão.');
        return ['path'=>$target,'filename'=>basename($target),'mime'=>'application/pdf','format'=>'pdf'];
    }

    private static function load_answers_by_submission(array $submissionRows): array {
        global $wpdb;
        $ids = [];
        foreach ($submissionRows as $row) {
            $id = absint($row['id'] ?? 0);
            if ($id > 0) $ids[$id] = $id;
        }
        if (!$ids) return [];

        $grouped = [];
        $table = FormsModule::table_answers();
        foreach (array_chunk(array_values($ids), 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
            $sql = "SELECT * FROM {$table} WHERE submission_id IN ({$placeholders}) ORDER BY submission_id ASC, id ASC";
            $answers = $wpdb->get_results($wpdb->prepare($sql, ...$chunk), ARRAY_A) ?: [];
            foreach ($answers as $answer) {
                $submissionId = absint($answer['submission_id'] ?? 0);
                if ($submissionId > 0) $grouped[$submissionId][] = $answer;
            }
        }
        return $grouped;
    }


    /**
     * Build the briefing portion of one report from the same store-aware engine
     * used by the field form. This deliberately does not infer a briefing from
     * client/project alone: the exact location, validity date and form context
     * are required, and an Operative filter is intersected with the existing
     * campaign store assignment map.
     */
    private static function briefing_questions_for_submission(array $submission, array $answers, array $filters): array {
        $locationId = absint($submission['location_id'] ?? 0);
        $projectId = absint($submission['project_id'] ?? 0);
        $clientId = absint($submission['client_id'] ?? 0);
        $formId = absint($submission['form_id'] ?? 0);
        if ($locationId <= 0 || $projectId <= 0) return [];

        $ownerId = absint($filters['owner_user_id'] ?? 0);
        if ($ownerId > 0 && !self::briefing_location_assigned_to_owner($projectId, $locationId, $ownerId)) return [];

        $contextDate = sanitize_text_field((string)($submission['report_day'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $contextDate)) {
            $contextDate = sanitize_text_field((string)($submission['route_date'] ?? ''));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $contextDate)) {
            $submittedAt = trim((string)($submission['submitted_at'] ?? ''));
            $contextDate = preg_match('/^(\d{4}-\d{2}-\d{2})/', $submittedAt, $m) ? $m[1] : current_time('Y-m-d');
        }

        $questions = ContextQuestionService::briefing_for_context([
            'client_id' => $clientId,
            'project_id' => $projectId,
            'location_id' => $locationId,
            'form_id' => $formId,
            'date' => $contextDate,
            'route_date' => $contextDate,
        ]);
        if (!$questions) return [];

        $answersByKey = [];
        foreach ($answers as $answer) {
            if (!is_array($answer)) continue;
            $key = sanitize_key((string)($answer['question_key'] ?? ''));
            if ($key !== '' && !isset($answersByKey[$key])) $answersByKey[$key] = $answer;
        }

        $out = [];
        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $key = sanitize_key((string)($question['question_key'] ?? ''));
            if ($key === '') continue;
            $answer = $answersByKey[$key] ?? null;
            $answerText = is_array($answer) ? self::answer_to_string($answer) : '';
            $answered = is_array($answer) && ($answerText !== '' || $answerText === '0');
            $out[] = [
                'key' => $key,
                'label' => sanitize_text_field((string)($question['question_label'] ?? $key)),
                'type' => sanitize_key((string)($question['question_type'] ?? 'text')) ?: 'text',
                'briefing_id' => absint($question['briefing_id'] ?? 0),
                'briefing_title' => sanitize_text_field((string)($question['briefing_title'] ?? 'Briefing')),
                'briefing_section_id' => absint($question['briefing_section_id'] ?? 0),
                'location_id' => $locationId,
                'answer' => $answerText,
                'answered' => $answered,
                'state' => $answered ? 'answered' : 'pending',
            ];
        }
        return $out;
    }

    private static function briefing_location_assigned_to_owner(int $projectId, int $locationId, int $ownerId): bool {
        global $wpdb;
        static $cache = [];
        $projectId = absint($projectId);
        $locationId = absint($locationId);
        $ownerId = absint($ownerId);
        if (!$projectId || !$locationId || !$ownerId) return false;
        $cacheKey = $projectId . ':' . $locationId . ':' . $ownerId;
        if (array_key_exists($cacheKey, $cache)) return $cache[$cacheKey];
        $table = $wpdb->prefix . 'routespro_campaign_locations';
        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE project_id=%d AND location_id=%d AND assigned_to=%d AND is_active=1 LIMIT 1",
            $projectId,
            $locationId,
            $ownerId
        ));
        return $cache[$cacheKey] = ($exists === 1);
    }

    private static function prepare_export_runtime(): void {
        if (function_exists('wp_raise_memory_limit')) wp_raise_memory_limit('admin');
        if (function_exists('ignore_user_abort')) @ignore_user_abort(true);
        // Long Analytics exports must not keep the PHP session lock while the
        // browser or WebView is waiting for the generated file.
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) @session_write_close();

        // Keep the download request as a clean binary channel. Notices, HTML
        // and output compression can prepend bytes or truncate a valid XLSX.
        if (function_exists('ini_set')) {
            @ini_set('display_errors', '0');
            @ini_set('html_errors', '0');
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '0');
        }
        if (ob_get_level() === 0) @ob_start();

        if (function_exists('set_time_limit')) {
            $requested = function_exists('apply_filters') ? (int) apply_filters('fieldflow_export_time_limit', 600) : 600;
            $requested = max(180, min(1800, $requested));
            $configured = (int) ini_get('max_execution_time');
            if ($configured !== 0 && $configured < $requested) @set_time_limit($requested);
        }
    }

    public static function repair_missing_context(int $limit = 20000): int {
        global $wpdb;
        $limit = max(1, min(100000, $limit));
        $submissions = FormsModule::table_submissions();
        $bindings = \RoutesPro\Forms\BindingResolver::table();
        $rows = $wpdb->get_results(
            "SELECT s.id,
                COALESCE(NULLIF(s.route_stop_id,0),NULLIF(fb.stop_id,0)) AS route_stop_id,
                COALESCE(NULLIF(s.route_id,0),NULLIF(rs.route_id,0),NULLIF(fb.route_id,0)) AS route_id,
                COALESCE(NULLIF(s.location_id,0),NULLIF(rs.location_id,0),NULLIF(fb.location_id,0)) AS location_id,
                COALESCE(NULLIF(s.project_id,0),NULLIF(r.project_id,0),NULLIF(fb.project_id,0),NULLIF(loc.project_id,0)) AS project_id,
                COALESCE(NULLIF(s.client_id,0),NULLIF(r.client_id,0),NULLIF(fb.client_id,0),NULLIF(p.client_id,0),NULLIF(loc.client_id,0)) AS client_id
             FROM {$submissions} s
             LEFT JOIN {$bindings} fb ON fb.id=s.binding_id
             LEFT JOIN {$wpdb->prefix}routespro_route_stops rs ON rs.id=COALESCE(NULLIF(s.route_stop_id,0),NULLIF(fb.stop_id,0))
             LEFT JOIN {$wpdb->prefix}routespro_routes r ON r.id=COALESCE(NULLIF(s.route_id,0),NULLIF(rs.route_id,0),NULLIF(fb.route_id,0))
             LEFT JOIN {$wpdb->prefix}routespro_locations loc ON loc.id=COALESCE(NULLIF(s.location_id,0),NULLIF(rs.location_id,0),NULLIF(fb.location_id,0))
             LEFT JOIN {$wpdb->prefix}routespro_projects p ON p.id=COALESCE(NULLIF(s.project_id,0),NULLIF(r.project_id,0),NULLIF(fb.project_id,0),NULLIF(loc.project_id,0))
             WHERE COALESCE(s.client_id,0)=0 OR COALESCE(s.project_id,0)=0 OR COALESCE(s.route_id,0)=0 OR COALESCE(s.location_id,0)=0
             ORDER BY s.id ASC LIMIT {$limit}",
            ARRAY_A
        ) ?: [];
        $updated = 0;
        foreach ($rows as $row) {
            $data = [];
            foreach (['client_id','project_id','route_id','route_stop_id','location_id'] as $field) {
                $value = absint($row[$field] ?? 0);
                if ($value) $data[$field] = $value;
            }
            if (!$data) continue;
            $result = $wpdb->update($submissions, $data, ['id' => (int)$row['id']]);
            if ($result !== false && $result > 0) $updated++;
        }
        return $updated;
    }

    private static function append_context_question_columns(array $columns, array $seen, array $filters, array $rows): array {
        global $wpdb;
        $table = ContextQuestionService::table();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return $columns;

        $clientIds = [];
        $projectIds = [];
        $locationIds = [];
        $formIds = [];
        foreach ($rows as $row) {
            foreach ([['client_id', &$clientIds], ['project_id', &$projectIds], ['location_id', &$locationIds], ['form_id', &$formIds]] as $pair) {
                $value = absint($row[$pair[0]] ?? 0);
                if ($value > 0) $pair[1][$value] = $value;
            }
            $joinedLocation = absint($row['location_id'] ?? $row['stop_row_id'] ?? 0);
            if ($joinedLocation > 0) $locationIds[$joinedLocation] = $joinedLocation;
        }
        foreach (['client_id' => &$clientIds, 'project_id' => &$projectIds, 'location_id' => &$locationIds] as $field => &$bucket) {
            $value = absint($filters[$field] ?? 0);
            if ($value > 0) $bucket[$value] = $value;
        }
        unset($bucket);

        $where = ['is_active = 1'];
        $args = [];
        foreach (['client_id' => $clientIds, 'project_id' => $projectIds, 'location_id' => $locationIds, 'form_id' => $formIds] as $field => $ids) {
            $ids = array_values(array_filter(array_map('absint', (array)$ids)));
            if ($ids) {
                $where[] = '(' . $field . '=0 OR ' . $field . ' IN (' . implode(',', array_fill(0, count($ids), '%d')) . '))';
                $args = array_merge($args, $ids);
            } else {
                if (in_array($field, ['client_id', 'project_id', 'location_id'], true) && !empty($filters[$field])) continue;
            }
        }

        // Generic context columns remain broad by dataset scope, but briefing
        // columns are never inferred globally. Briefing columns are appended only
        // from the exact per-submission store mapping resolved above.
        $where[] = "(COALESCE(source_type,'')<>'briefing' AND COALESCE(briefing_id,0)=0)";
        $sql = 'SELECT question_key, question_label, context_type, source_type, briefing_id FROM ' . $table . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY priority ASC, id ASC LIMIT 500';
        $questions = $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
        foreach ($questions as $q) {
            $key = sanitize_key($q['question_key'] ?? '');
            if (!$key || isset($seen[$key])) continue;
            $seen[$key] = true;
            $label = (string)($q['question_label'] ?? $key);
            $columns[] = ['key' => $key, 'label' => $label];
        }
        foreach ($rows as $row) {
            foreach ((array)($row['presentation']['briefing_questions'] ?? []) as $briefingQuestion) {
                $key = sanitize_key((string)($briefingQuestion['key'] ?? ''));
                if ($key === '' || isset($seen[$key])) continue;
                $seen[$key] = true;
                $label = sanitize_text_field((string)($briefingQuestion['label'] ?? $key));
                $columns[] = ['key' => $key, 'label' => $label . ' · Briefing'];
            }
        }
        return $columns;
    }

    private static function render_export_actions(array $filters) {
        $base = admin_url('admin-post.php?action=routespro_export_submissions');
        $query = [];
        foreach (['client_id','project_id','owner_user_id','route_id','location_id','date_from','date_to'] as $k) {
            if (!empty($filters[$k])) $query[$k] = $filters[$k];
        }
        $csv = wp_nonce_url(add_query_arg($query + ['format' => 'csv'], $base), 'routespro_export_submissions');
        $csvDetail = wp_nonce_url(add_query_arg($query + ['format' => 'csv_detail'], $base), 'routespro_export_submissions');
        $xlsx = wp_nonce_url(add_query_arg($query + ['format' => 'xlsx'], $base), 'routespro_export_submissions');
        $xlsxMobile = wp_nonce_url(add_query_arg($query + ['format' => 'xlsx_mobile'], $base), 'routespro_export_submissions');
        $pdf = wp_nonce_url(add_query_arg($query + ['format' => 'pdf'], $base), 'routespro_export_submissions');
        echo '<div style="display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap;margin-top:14px;padding:14px 16px;background:#fff;border:1px solid #e2e8f0;border-radius:16px">';
        echo '<div><strong>Exportações organizadas</strong><div style="font-size:12px;color:#64748b;margin-top:4px">Sem uma coluna por referência. O Excel separa submissões, respostas, produtos, encomendas e evidências em folhas próprias.</div></div>';
        echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';
        echo '<a class="button button-primary" href="' . esc_url($csv) . '">CSV resumo</a>';
        echo '<a class="button" href="' . esc_url($csvDetail) . '">CSV dados</a>';
        echo '<a class="button" href="' . esc_url($xlsx) . '">Excel completo (.xlsx)</a>';
        echo '<a class="button" href="' . esc_url($xlsxMobile) . '">Excel mobile (.xlsx)</a>';
        echo '<a class="button" href="' . esc_url($pdf) . '">PDF executivo</a>';
        echo '</div></div>';
    }



    public static function handle_dashboard_export() {
        self::prepare_export_runtime();
        if (!is_user_logged_in()) wp_die('Sem permissões.');
        if (!current_user_can('routespro_manage') && !\RoutesPro\Support\Permissions::can_access_front()) wp_die('Sem permissões.');
        $nonce_ok = isset($_REQUEST['_wpnonce']) && wp_verify_nonce((string)$_REQUEST['_wpnonce'], 'routespro_export_dashboard');
        if (!$nonce_ok) wp_die('Pedido inválido.');

        $format = sanitize_key($_GET['format'] ?? 'csv');
        if ($format === 'excel' || $format === 'xls') $format = 'xlsx';
        if (in_array($format, ['excel_mobile','mobile_excel','xlsx_mobile'], true)) $format = 'xlsx_mobile';
        $self_mode = !empty($_GET['self_mode']);
        $filters = [
            'from' => sanitize_text_field($_GET['from'] ?? date('Y-m-d', strtotime('-6 days'))),
            'to' => sanitize_text_field($_GET['to'] ?? current_time('Y-m-d')),
            'client_id' => absint($_GET['client_id'] ?? 0),
            'project_id' => absint($_GET['project_id'] ?? 0),
            'user_id' => $self_mode ? get_current_user_id() : absint($_GET['user_id'] ?? 0),
            'location_id' => absint($_GET['location_id'] ?? 0),
            'category' => sanitize_text_field($_GET['category'] ?? ''),
            'product' => sanitize_text_field($_GET['product'] ?? ''),
            'role' => sanitize_key($_GET['role'] ?? ''),
            'audience' => sanitize_key($_GET['analytics_audience'] ?? ($self_mode ? 'merchandiser' : 'manager')),
            'self_mode' => $self_mode,
        ];
        if (!in_array($filters['audience'], ['client','manager','merchandiser'], true)) $filters['audience'] = $self_mode ? 'merchandiser' : 'manager';
        self::assert_saas_export($format, (int)$filters['client_id']);
        $filters['export_full'] = true;
        $filters['prefer_snapshot'] = false;
        try {
            if (in_array($format, ['xlsx','xlsx_mobile','pdf'], true)) {
                $file = self::createDashboardReportFile($filters, $format, 'download');
                self::streamDashboardReportFile($file);
            }
            $prepared = self::prepareDashboardReport($filters);
            [$csv_headers, $csv_rows] = self::dashboard_export_csv($prepared['data'], $prepared['filters'], $prepared['context']);
            self::output_csv($prepared['filename'] . '.csv', $csv_headers, $csv_rows);
        } catch (\Throwable $e) {
            wp_die(esc_html($e->getMessage()));
        }
    }

    /**
     * Prepare an authenticated Analytics export as a temporary file for the
     * mobile download-ticket flow. The request is still evaluated under the
     * current WordPress user before any ticket can be issued.
     *
     * @return array{path:string,filename:string,mime:string,format:string}
     */
    public static function createDashboardExportTicketFile(array $request): array {
        self::prepare_export_runtime();
        if (!is_user_logged_in()) throw new \RuntimeException('Sem sessão ativa.');
        if (!current_user_can('routespro_manage') && !\RoutesPro\Support\Permissions::can_access_front()) {
            throw new \RuntimeException('Sem permissões para exportar Analytics.');
        }

        $format = sanitize_key((string)($request['format'] ?? 'csv'));
        if ($format === 'excel' || $format === 'xls') $format = 'xlsx';
        if (in_array($format, ['excel_mobile','mobile_excel','xlsx_mobile'], true)) $format = 'xlsx_mobile';
        if (!in_array($format, ['csv','xlsx','xlsx_mobile','pdf'], true)) {
            throw new \RuntimeException('Formato de exportação inválido.');
        }

        $self_mode = !empty($request['self_mode']);
        $filters = [
            'from' => sanitize_text_field((string)($request['from'] ?? date('Y-m-d', strtotime('-6 days')))),
            'to' => sanitize_text_field((string)($request['to'] ?? current_time('Y-m-d'))),
            'client_id' => absint($request['client_id'] ?? 0),
            'project_id' => absint($request['project_id'] ?? 0),
            'user_id' => $self_mode ? get_current_user_id() : absint($request['user_id'] ?? 0),
            'location_id' => absint($request['location_id'] ?? 0),
            'category' => sanitize_text_field((string)($request['category'] ?? '')),
            'product' => sanitize_text_field((string)($request['product'] ?? '')),
            'role' => sanitize_key((string)($request['role'] ?? '')),
            'audience' => sanitize_key((string)($request['analytics_audience'] ?? ($self_mode ? 'merchandiser' : 'manager'))),
            'self_mode' => $self_mode,
        ];
        if (!in_array($filters['audience'], ['client','manager','merchandiser'], true)) {
            $filters['audience'] = $self_mode ? 'merchandiser' : 'manager';
        }
        $filters['export_full'] = true;
        $filters['prefer_snapshot'] = false;

        // Prepare first. StatsController enforces the same client/project/user
        // scope as the visible Analytics dashboard, preventing a ticket from
        // being created for data the current user cannot access.
        $prepared = self::prepareDashboardReport($filters);
        self::assert_saas_export($format, (int)$filters['client_id']);

        if (in_array($format, ['xlsx','xlsx_mobile','pdf'], true)) {
            return self::createDashboardReportFileFromPrepared($prepared, $format, 'mobile-ticket');
        }

        [$headers, $rows] = self::dashboard_export_csv($prepared['data'], $prepared['filters'], $prepared['context']);
        $filename = sanitize_file_name((string)$prepared['filename'] . '-mobile-ticket.csv');
        if ($filename === '') $filename = 'fieldflow-analytics.csv';
        $tmp = wp_tempnam($filename);
        if (!$tmp) throw new \RuntimeException('Não foi possível preparar o CSV.');
        $target = trailingslashit(dirname($tmp)) . wp_unique_filename(dirname($tmp), $filename);
        @unlink($target);
        if (!@rename($tmp, $target)) {
            @unlink($tmp);
            throw new \RuntimeException('Não foi possível preparar o CSV.');
        }
        $fh = @fopen($target, 'wb');
        if (!$fh) {
            @unlink($target);
            throw new \RuntimeException('Não foi possível criar o CSV.');
        }
        fwrite($fh, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($fh, array_map([self::class, 'normalize_export_text'], $headers));
        foreach ($rows as $row) {
            fputcsv($fh, array_map([self::class, 'normalize_export_text'], $row));
        }
        fclose($fh);
        if (!is_file($target) || (int)@filesize($target) < 1) {
            @unlink($target);
            throw new \RuntimeException('O CSV ficou vazio.');
        }
        return ['path'=>$target,'filename'=>$filename,'mime'=>'text/csv; charset=utf-8','format'=>'csv'];
    }

    public static function storeDashboardSnapshot(array $filters, array $data): void {
        if (!$data || !function_exists('set_transient')) return;
        $filters = self::normalizeDashboardReportFilters($filters);
        $key = self::dashboardSnapshotKey($filters);
        set_transient($key, ['stored_at' => time(), 'data' => $data], 20 * MINUTE_IN_SECONDS);
    }

    private static function dashboardSnapshotKey(array $filters): string {
        unset($filters['prefer_snapshot'], $filters['self_mode'], $filters['export_full']);
        return 'ff_dash_v167_' . md5((string)get_current_user_id() . '|' . wp_json_encode($filters));
    }

    private static function getDashboardSnapshot(array $filters): ?array {
        if (!function_exists('get_transient')) return null;
        $payload = get_transient(self::dashboardSnapshotKey($filters));
        if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) return null;
        return $payload['data'];
    }

    /* fieldflow-analytics-header-date-export-authority-v1 */
    private static function normalizeDashboardReportDate($value, string $fallback): string {
        $value = sanitize_text_field((string)$value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $fallback;
        $parts = array_map('intval', explode('-', $value));
        return count($parts) === 3 && checkdate($parts[1], $parts[2], $parts[0]) ? $value : $fallback;
    }

    private static function normalizeDashboardReportFilters(array $filters): array {
        $filters = array_merge([
            'from' => wp_date('Y-m-d', current_time('timestamp', true) - 6 * DAY_IN_SECONDS), 'to' => current_time('Y-m-d'),
            'client_id' => 0, 'project_id' => 0, 'user_id' => 0, 'location_id' => 0, 'route_id' => 0,
            'category' => '', 'product' => '', 'role' => '', 'audience' => 'manager', 'self_mode' => false,
            'prefer_snapshot' => true, 'export_full' => false,
        ], $filters);
        foreach (['client_id','project_id','user_id','location_id','route_id'] as $key) $filters[$key] = absint($filters[$key] ?? 0);
        $fallbackTo = current_time('Y-m-d');
        $fallbackFrom = date('Y-m-d', strtotime('-6 days'));
        $filters['from'] = self::normalizeDashboardReportDate($filters['from'] ?? '', $fallbackFrom);
        $filters['to'] = self::normalizeDashboardReportDate($filters['to'] ?? '', $fallbackTo);
        if ($filters['from'] > $filters['to']) [$filters['from'], $filters['to']] = [$filters['to'], $filters['from']];
        foreach (['category','product'] as $key) $filters[$key] = sanitize_text_field((string)($filters[$key] ?? ''));
        $filters['role'] = sanitize_key((string)($filters['role'] ?? ''));
        $filters['audience'] = sanitize_key((string)($filters['audience'] ?? 'manager'));
        if (!in_array($filters['audience'], ['client','manager','merchandiser'], true)) $filters['audience'] = 'manager';
        $filters['self_mode'] = !empty($filters['self_mode']);
        $filters['prefer_snapshot'] = !array_key_exists('prefer_snapshot', $filters) || !empty($filters['prefer_snapshot']);
        $filters['export_full'] = !empty($filters['export_full']);
        if ($filters['export_full']) $filters['prefer_snapshot'] = false;
        return $filters;
    }

    public static function prepareDashboardReport(array $filters): array {
        static $cache = [];
        $filters['export_full'] = true;
        $filters['prefer_snapshot'] = false;
        $filters = self::normalizeDashboardReportFilters($filters);
        $cacheKey = md5(wp_json_encode([$filters, get_current_user_id()]));
        if (isset($cache[$cacheKey])) return $cache[$cacheKey];

        $dataSource = 'Consulta direta ao Analytics';
        $data = !empty($filters['prefer_snapshot']) && empty($filters['export_full']) ? self::getDashboardSnapshot($filters) : null;
        if (is_array($data)) {
            $dataSource = 'Dados já validados na aba Analytics';
        } else {
            $req = new \WP_REST_Request('GET', '/routespro/v1/stats');
            foreach ([
                'from' => $filters['from'], 'to' => $filters['to'], 'client_id' => $filters['client_id'],
                'project_id' => $filters['project_id'], 'user_id' => $filters['user_id'], 'location_id' => $filters['location_id'], 'route_id' => $filters['route_id'],
                'category' => $filters['category'], 'product' => $filters['product'], 'role' => $filters['role'],
                'analytics_audience' => $filters['audience'], 'export_full' => $filters['export_full'] ? 1 : 0,
                'report_export' => 1,
            ] as $key => $value) {
                if ($value !== '' && $value !== 0) $req->set_param($key, $value);
            }
            $response = (new \RoutesPro\Rest\StatsController())->stats($req);
            if (is_wp_error($response)) throw new \RuntimeException((string)$response->get_error_message());
            if ($response instanceof \WP_REST_Response) $data = $response->get_data();
            elseif (is_array($response)) $data = $response;
            else $data = [];
            if (isset($data['data']) && is_array($data['data']) && !isset($data['total_routes'])) $data = $data['data'];
            if (!is_array($data)) $data = [];
            if (empty($filters['export_full'])) self::storeDashboardSnapshot($filters, $data);
        }
        $data = array_merge([
            'total_routes'=>0,'completed_routes'=>0,'completion_rate'=>0,'total_stops'=>0,'done_stops'=>0,
            'done_rate'=>0,'avg_stops_per_route'=>0,'avg_stop_minutes'=>0,'by_day'=>[],
            'previous_summary'=>[],'rankings'=>[],'form_analytics'=>['metrics'=>[]],
        ], $data);
        if (!is_array($data['by_day'])) $data['by_day'] = [];
        if (!is_array($data['form_analytics'])) $data['form_analytics'] = ['metrics'=>[]];
        if (!isset($data['form_analytics']['metrics']) || !is_array($data['form_analytics']['metrics'])) $data['form_analytics']['metrics'] = [];

        $context = self::dashboard_export_context($filters);
        if (empty($data['canonical_operational_report']) || !is_array($data['canonical_operational_report'])) {
            try { $data['canonical_operational_report'] = CanonicalOperationalReportService::build($data, $filters, $context); }
            catch (\Throwable $e) { $data['canonical_operational_report'] = []; }
        }
        $context['data_source'] = $dataSource;
        $context['route_rows'] = count((array)($data['by_day'] ?? []));
        $context['metric_rows'] = count((array)($data['form_analytics']['metrics'] ?? []));
        // 2.2.431: one intelligence envelope feeds email, Excel, PDF and CSV.
        // Operational detail remains untouched underneath the executive layer.
        $data['report_intelligence'] = ReportIntelligenceService::build($data, $filters, $context);
        $slug = sanitize_title(($context['client_name'] !== 'Global' ? $context['client_name'] : 'fieldflow') . '-' . ($context['project_name'] !== 'Global' ? $context['project_name'] : 'analytics'));
        $filename = 'fieldflow-analytics-' . ($slug ?: 'relatorio') . '-' . preg_replace('/[^0-9]/', '', $filters['from']) . '-a-' . preg_replace('/[^0-9]/', '', $filters['to']);
        return $cache[$cacheKey] = ['data'=>$data,'filters'=>$filters,'context'=>$context,'filename'=>$filename];
    }

    public static function createDashboardReportFile(array $filters, string $format, string $label = ''): array {
        $normalized_format = sanitize_key($format);
        if ($normalized_format === 'excel' || $normalized_format === 'xls') $normalized_format = 'xlsx';
        if (in_array($normalized_format, ['excel_mobile','mobile_excel','xlsx_mobile'], true)) $normalized_format = 'xlsx_mobile';
        if (in_array($normalized_format, ['xlsx','csv','pdf','xlsx_mobile'], true)) {
            $filters['export_full'] = true;
            $filters['prefer_snapshot'] = false;
        }
        return self::createDashboardReportFileFromPrepared(self::prepareDashboardReport($filters), $normalized_format, $label);
    }

    public static function createDashboardReportFileFromPrepared(array $prepared, string $format, string $label = ''): array {
        $format = sanitize_key($format);
        if ($format === 'excel' || $format === 'xls') $format = 'xlsx';
        if (in_array($format, ['excel_mobile','mobile_excel','xlsx_mobile'], true)) $format = 'xlsx_mobile';
        if (!in_array($format, ['csv','xlsx','xlsx_mobile','pdf'], true)) throw new \RuntimeException('Formato de relatório inválido.');
        if (empty($prepared['data']) || empty($prepared['filters']) || empty($prepared['context']) || empty($prepared['filename'])) {
            throw new \RuntimeException('Os dados do relatório não foram preparados corretamente.');
        }
        $suffix = $label && $label !== 'download' ? '-' . sanitize_title($label) : '';
        if ($format === 'csv') {
            [$headers, $rows] = !empty($prepared['data']['canonical_operational_report'])
                ? self::canonical_dashboard_export_csv($prepared['data']['canonical_operational_report'])
                : self::dashboard_export_csv($prepared['data'], $prepared['filters'], $prepared['context']);
            $downloadName = $prepared['filename'] . $suffix . '.csv';
            $path = self::create_dashboard_csv_file($downloadName, $headers, $rows);
            return ['path'=>$path,'filename'=>$downloadName,'mime'=>'text/csv; charset=UTF-8','format'=>'csv'];
        }
        if (in_array($format, ['xlsx','xlsx_mobile'], true)) {
            $sheets = !empty($prepared['data']['canonical_operational_report'])
                ? self::canonical_dashboard_export_sheets($prepared['data']['canonical_operational_report'], $prepared['context'])
                : self::dashboard_export_sheets($prepared['data'], $prepared['filters'], $prepared['context']);
            if ($format === 'xlsx_mobile') $sheets = self::dashboard_mobile_export_sheets($sheets);
            $mobileSuffix = $format === 'xlsx_mobile' ? '-mobile' : '';
            $downloadName = $prepared['filename'] . $suffix . $mobileSuffix . '.xlsx';
            $path = self::create_dashboard_xlsx_file($downloadName, $sheets, $prepared['context'], $format === 'xlsx_mobile');
            return ['path'=>$path,'filename'=>$downloadName,'mime'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','format'=>$format];
        }
        $pdf = !empty($prepared['data']['canonical_operational_report'])
            ? self::build_canonical_dashboard_pdf_binary($prepared['data']['canonical_operational_report'], $prepared['context'])
            : self::build_dashboard_pdf_binary($prepared['data'], $prepared['filters'], $prepared['context']);
        $path = wp_tempnam($prepared['filename'] . $suffix . '.pdf');
        if (!$path) throw new \RuntimeException('Não foi possível preparar o PDF.');
        $target = trailingslashit(dirname($path)) . wp_unique_filename(dirname($path), sanitize_file_name($prepared['filename'] . $suffix . '.pdf')); @unlink($target); @rename($path, $target);
        if (file_put_contents($target, $pdf) === false) throw new \RuntimeException('Não foi possível guardar o PDF.');
        return ['path'=>$target,'filename'=>basename($target),'mime'=>'application/pdf','format'=>'pdf'];
    }

    private static function create_dashboard_csv_file(string $filename, array $headers, array $rows): string {
        $filename = sanitize_file_name($filename);
        if ($filename === '') $filename = 'fieldflow-analytics.csv';
        $tmp = wp_tempnam($filename);
        if (!$tmp) throw new \RuntimeException('Não foi possível preparar o CSV.');
        $target = trailingslashit(dirname($tmp)) . wp_unique_filename(dirname($tmp), $filename);
        @unlink($target);
        if (!@rename($tmp, $target)) {
            @unlink($tmp);
            throw new \RuntimeException('Não foi possível preparar o CSV.');
        }
        $fh = @fopen($target, 'wb');
        if (!$fh) {
            @unlink($target);
            throw new \RuntimeException('Não foi possível criar o CSV.');
        }
        fwrite($fh, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($fh, array_map([self::class, 'normalize_export_text'], $headers));
        foreach ($rows as $row) fputcsv($fh, array_map([self::class, 'normalize_export_text'], array_values((array)$row)));
        fclose($fh);
        return $target;
    }

    private static function streamDashboardReportFile(array $file): void {
        $path = (string)($file['path'] ?? '');
        if (!$path || !is_file($path)) throw new \RuntimeException('O ficheiro do relatório não foi criado.');

        clearstatcache(true, $path);
        $size = (int) @filesize($path);
        if ($size < 1) throw new \RuntimeException('O ficheiro do relatório ficou vazio.');
        $mime = (string)($file['mime'] ?? 'application/octet-stream');
        if (strpos($mime, 'spreadsheetml') !== false || strtolower((string)pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx') {
            self::assert_xlsx_package_integrity($path);
        }

        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) @session_write_close();
        if (function_exists('ini_set')) {
            @ini_set('display_errors', '0');
            @ini_set('html_errors', '0');
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '0');
        }
        while (ob_get_level() > 0) @ob_end_clean();

        if (headers_sent($source, $line)) {
            @unlink($path);
            throw new \RuntimeException('A exportação foi interrompida por uma saída inesperada do servidor em ' . basename((string)$source) . ':' . (int)$line . '.');
        }
        if (function_exists('header_remove')) {
            @header_remove('Content-Encoding');
            @header_remove('Content-Length');
            @header_remove('Content-Type');
            @header_remove('Content-Disposition');
        }

        $downloadName = sanitize_file_name((string)($file['filename'] ?? basename($path)));
        if ($downloadName === '') $downloadName = basename($path);
        nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
        header('Content-Length: ' . $size);
        header('X-FieldFlow-File-Size: ' . $size);
        header('X-FieldFlow-Download-Integrity: ooxml-strict-v3');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0, no-transform');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Accept-Ranges: none');

        $handle = @fopen($path, 'rb');
        if (!$handle) {
            @unlink($path);
            throw new \RuntimeException('Não foi possível abrir o ficheiro final para download.');
        }
        $sent = 0;
        while (!feof($handle)) {
            $chunk = fread($handle, 1048576);
            if ($chunk === false) break;
            if ($chunk === '') continue;
            echo $chunk;
            $sent += strlen($chunk);
        }
        fclose($handle);
        @unlink($path);
        if ($sent !== $size) error_log('FieldFlow export stream mismatch: expected=' . $size . ' sent=' . $sent);
        exit;
    }

    private static function assert_xlsx_package_integrity(string $path): void {
        clearstatcache(true, $path);
        $size = (int) @filesize($path);
        if ($size < 1000) throw new \RuntimeException('O ficheiro Excel ficou incompleto.');
        $handle = @fopen($path, 'rb');
        if (!$handle) throw new \RuntimeException('Não foi possível validar o ficheiro Excel.');
        $signature = fread($handle, 4);
        $tailLength = min(66000, $size);
        fseek($handle, max(0, $size - $tailLength));
        $tail = fread($handle, $tailLength);
        fclose($handle);
        if ($signature !== "PK\x03\x04" || $tail === false || strpos($tail, "PK\x05\x06") === false || strpos($tail, "PK\x01\x02") === false) {
            throw new \RuntimeException('O pacote Excel não ficou completo antes do download.');
        }

        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            $flags = defined('ZipArchive::CHECKCONS') ? \ZipArchive::CHECKCONS : 0;
            if ($zip->open($path, $flags) !== true) throw new \RuntimeException('O pacote Excel não passou a validação ZIP.');
            $required = ['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml'];
            foreach ($required as $entry) {
                if ($zip->locateName($entry) === false) {
                    $zip->close();
                    throw new \RuntimeException('A estrutura do Excel ficou incompleta.');
                }
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!is_array($stat) || empty($stat['name'])) continue;
                $name = (string)$stat['name'];
                if (substr($name, -4) !== '.xml' && substr($name, -5) !== '.rels') continue;
                $xml = $zip->getFromIndex($i);
                if (!is_string($xml) || strpos(ltrim($xml), '<?xml') !== 0) {
                    $zip->close();
                    throw new \RuntimeException('Foi detetado XML inválido dentro do Excel.');
                }
                if (class_exists('DOMDocument')) {
                    $previous = libxml_use_internal_errors(true);
                    $document = new \DOMDocument();
                    $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                    if (!$loaded) {
                        $zip->close();
                        throw new \RuntimeException('Foi detetado XML malformado dentro do Excel.');
                    }
                }
                if (strpos($name, 'xl/worksheets/sheet') === 0) {
                    $autoFilterAt = strpos($xml, '<autoFilter');
                    $mergeCellsAt = strpos($xml, '<mergeCells');
                    if ($autoFilterAt !== false && $mergeCellsAt !== false && $autoFilterAt > $mergeCellsAt) {
                        $zip->close();
                        throw new \RuntimeException('A folha Excel não respeita a ordem OOXML exigida pelo Microsoft Excel.');
                    }
                    if (preg_match('/<v>\s*(?:inf|-inf|nan)\s*<\/v>/i', $xml)) {
                        $zip->close();
                        throw new \RuntimeException('A folha Excel contém um valor numérico incompatível.');
                    }
                }
                if ($name === 'docProps/app.xml') {
                    $companyAt = strpos($xml, '<Company>');
                    $applicationAt = strpos($xml, '<Application>');
                    if ($companyAt !== false && $applicationAt !== false && $companyAt > $applicationAt) {
                        $zip->close();
                        throw new \RuntimeException('As propriedades do Excel não respeitam a ordem OOXML exigida pelo Microsoft Excel.');
                    }
                }
            }
            $zip->close();
        }
    }

    private static function dashboard_export_context(array $filters): array {
        global $wpdb;
        $client_id = (int)($filters['client_id'] ?? 0);
        $project_id = (int)($filters['project_id'] ?? 0);
        $user_id = (int)($filters['user_id'] ?? 0);
        $location_id = (int)($filters['location_id'] ?? 0);
        $client_name = 'Global';
        $project_name = 'Global';
        $user_name = 'Todos';
        $location_name = 'Todas';
        if ($client_id) $client_name = (string)($wpdb->get_var($wpdb->prepare('SELECT name FROM ' . $wpdb->prefix . 'routespro_clients WHERE id=%d', $client_id)) ?: ('#' . $client_id));
        if ($project_id) $project_name = (string)($wpdb->get_var($wpdb->prepare('SELECT name FROM ' . $wpdb->prefix . 'routespro_projects WHERE id=%d', $project_id)) ?: ('#' . $project_id));
        if ($user_id) {
            $user = get_userdata($user_id);
            $user_name = $user ? (string)$user->display_name : ('#' . $user_id);
        }
        if ($location_id) $location_name = (string)($wpdb->get_var($wpdb->prepare('SELECT name FROM ' . $wpdb->prefix . 'routespro_locations WHERE id=%d', $location_id)) ?: ('#' . $location_id));
        $audience = sanitize_key((string)($filters['audience'] ?? 'manager'));
        $audience_label = $audience === 'client' ? 'Cliente' : ($audience === 'merchandiser' ? 'Merchandiser' : 'Gestão');
        return [
            'client_name' => $client_name,
            'project_name' => $project_name,
            'user_name' => $user_name,
            'location_name' => $location_name,
            'audience' => $audience,
            'audience_label' => $audience_label,
            'period' => (string)($filters['from'] ?? '') . ' a ' . (string)($filters['to'] ?? ''),
            'generated_at' => wp_date('d/m/Y H:i'),
        ];
    }

    private static function dashboard_intelligence_sheets(array $data, array $context): array {
        $ri = is_array($data['report_intelligence'] ?? null) ? $data['report_intelligence'] : [];
        if (!$ri) return [];
        $adaptive = is_array($ri['adaptive_intelligence'] ?? null) ? $ri['adaptive_intelligence'] : [];
        $predictive = is_array($ri['predictive_intelligence'] ?? null) ? $ri['predictive_intelligence'] : [];
        $contextual = is_array($ri['contextual_predictive_intelligence'] ?? null) ? $ri['contextual_predictive_intelligence'] : [];
        $scoreLabels = [
            'operational_health'=>'Operational Health',
            'execution'=>'Execution Score',
            'trend'=>'Trend Score',
            'risk'=>'Risk Score',
            'opportunity'=>'Opportunity Score',
            'stock_risk'=>'Stock Risk',
            'confidence'=>'Confidence Score',
        ];
        $intelligenceRows = [];
        foreach ((array)($ri['scores'] ?? []) as $key => $score) {
            if (!is_array($score)) continue;
            $value = $score['score'] ?? null;
            $intelligenceRows[] = [
                'Score',
                $scoreLabels[sanitize_key((string)$key)] ?? ucwords(str_replace('_', ' ', (string)$key)),
                $value === null ? 'n.d.' : number_format((float)$value, 1, ',', '.') . '/100',
                (string)($score['label'] ?? ''),
                (string)($score['explanation'] ?? ''),
            ];
        }
        foreach ((array)($ri['summary_metrics'] ?? []) as $metric) {
            if (!is_array($metric)) continue;
            $value = $metric['value'] ?? null;
            if (is_array($value) || is_object($value)) $value = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $intelligenceRows[] = [
                'Widget inteligente',
                (string)($metric['metric_label'] ?? 'Métrica'),
                $value === null || $value === '' ? 'Sem dados' : (string)$value,
                (string)($metric['topic'] ?? ''),
                trim((string)($metric['reason'] ?? '') . ((string)($metric['note'] ?? '') !== '' ? ' | ' . (string)$metric['note'] : '')),
            ];
        }
        foreach (array_slice(array_values(array_filter((array)($adaptive['next_best_actions'] ?? []), 'is_array')), 0, 8) as $nba) {
            $intelligenceRows[] = [
                'Next Best Action',
                (string)($nba['title'] ?? 'Recomendação'),
                number_format((float)($nba['rank_score'] ?? 0),1,',','.') . '/100',
                'Confiança ' . number_format((float)($nba['confidence_score'] ?? 0),1,',','.') . '/100',
                trim((string)($nba['action'] ?? '') . ' | ' . (string)($nba['explanation'] ?? '')),
            ];
        }
        if (!$intelligenceRows) $intelligenceRows[] = ['Intelligence','Sem leitura disponível','','','Não existem dados suficientes para construir o resumo inteligente neste período.'];

        $alertRows = [];
        foreach ((array)($ri['store_alerts'] ?? []) as $alert) {
            if (!is_array($alert)) continue;
            $rate = $alert['done_rate'] ?? null;
            $alertRows[] = [
                strtoupper((string)($alert['severity'] ?? 'medium')),
                (float)($alert['priority_score'] ?? 0),
                (string)($alert['location_name'] ?? 'PDV'),
                (string)($alert['city'] ?? ''),
                $rate === null ? '' : number_format((float)$rate, 1, ',', '.') . '%',
                (int)($alert['pending_stops'] ?? 0),
                implode(', ', (array)($alert['signals'] ?? [])),
                trim((string)($alert['title'] ?? '') . ((string)($alert['detail'] ?? '') !== '' ? ' | ' . (string)$alert['detail'] : '')),
            ];
        }
        if (!$alertRows) $alertRows[] = ['INFO',0,'Sem alertas por loja','','',0,'','Não foram detetadas lojas prioritárias com os dados disponíveis.'];

        // Adaptive patterns have their own dedicated sheet below. Do not mix
        // them into the generic 30-column CSV row contract here. The 2.2.441
        // implementation accidentally referenced $headers/$rows from another
        // exporter, which raises a PHP 8 TypeError as soon as adaptive evidence
        // exists and blocks XLSX generation used by automated email reports.
        $storeRows = [];
        foreach ((array)($ri['store_intelligence']['rankings']['priority'] ?? []) as $store) {
            if (!is_array($store)) continue;
            $storeRows[] = [
                number_format((float)($store['adaptive_rank_score'] ?? $store['priority_score'] ?? 0),1,',','.'), (string)($store['location_name'] ?? 'PDV'), (string)($store['city'] ?? ''),
                number_format((float)($store['operational_health'] ?? 0),1,',','.'), number_format((float)($store['execution_score'] ?? 0),1,',','.'),
                number_format((float)($store['risk_score'] ?? 0),1,',','.'), number_format((float)($store['opportunity_score'] ?? 0),1,',','.'),
                ($store['stock_risk'] ?? null) === null ? 'n.d.' : number_format((float)$store['stock_risk'],1,',','.'),
                ($store['share_gap_pp'] ?? null) === null ? 'n.d.' : number_format((float)$store['share_gap_pp'],1,',','.'),
                ($store['trend_delta_pp'] ?? null) === null ? 'n.d.' : number_format((float)$store['trend_delta_pp'],1,',','.') . ' pp',
                number_format((float)($store['adaptive_confidence_score'] ?? $store['confidence_score'] ?? 0),1,',','.'),
                number_format((float)($store['adaptive']['effectiveness_score'] ?? $store['learning']['efficacy_rate'] ?? 0),1,',','.') . '%',
                ($store['forecast_risk_score'] ?? null) === null ? 'n.d.' : number_format((float)$store['forecast_risk_score'],1,',','.'),
                ($store['forecast_priority_score'] ?? null) === null ? 'n.d.' : number_format((float)$store['forecast_priority_score'],1,',','.'),
                (string)($store['forecast_direction'] ?? 'n.d.'),
                ($store['forecast_confidence_score'] ?? null) === null ? 'n.d.' : number_format((float)$store['forecast_confidence_score'],1,',','.'),
                (string)($store['next_best_action']['issue_label'] ?? ''),
                (string)($store['next_best_action']['product'] ?? ''),
                (string)($store['next_best_action']['action'] ?? ''),
                trim((string)($store['reason'] ?? '') . ((string)($store['forecast_reason'] ?? '') !== '' ? ' | Futuro: ' . (string)$store['forecast_reason'] : '')),
            ];
        }
        if (!$storeRows) $storeRows[] = [0,'Sem perfis de loja','','','','','','','','','','','','','','','','','','Não existem dados suficientes para Store Intelligence neste âmbito.'];

        $recommendationRows = [];
        foreach ((array)($ri['recommendations'] ?? []) as $recommendation) {
            if (!is_array($recommendation)) continue;
            $recommendationRows[] = [
                strtoupper((string)($recommendation['severity'] ?? 'medium')),
                (string)($recommendation['status'] ?? 'active'),
                (int)($recommendation['location_id'] ?? 0),
                (string)($recommendation['title'] ?? 'Recomendação FieldFlow'),
                (string)($recommendation['suggested_action'] ?? ''),
                number_format((float)($recommendation['adaptive_rank_score'] ?? 0), 1, ',', '.') . '/100',
                number_format((float)($recommendation['adaptive_confidence_score'] ?? $recommendation['confidence_score'] ?? 0), 1, ',', '.') . '/100',
                (int)($recommendation['adaptive']['evidence_count'] ?? 0),
                (string)($recommendation['baseline_value'] ?? ''),
                (string)($recommendation['latest_value'] ?? ''),
                (string)($recommendation['outcome_status'] ?? 'pending'),
                trim((string)($recommendation['rationale'] ?? '') . ((string)($recommendation['outcome_note'] ?? '') !== '' ? ' | Outcome: ' . (string)$recommendation['outcome_note'] : '')),
            ];
        }
        if (!$recommendationRows) $recommendationRows[] = ['INFO','',0,'Sem recomendações ativas','','','',0,'','','','Ainda não existem recomendações suficientes neste âmbito.'];

        $predictiveRows = [];
        $horizon = is_array($predictive['horizon'] ?? null) ? $predictive['horizon'] : [];
        foreach ((array)($predictive['forecasts'] ?? []) as $forecastKey => $forecast) {
            if (!is_array($forecast)) continue;
            $unit = (string)($forecast['unit'] ?? '');
            $current = $forecast['current'] ?? null;
            $future = $forecast['forecast'] ?? null;
            $delta = $forecast['delta'] ?? null;
            $predictiveRows[] = [
                'Projeção',
                (string)($forecast['label'] ?? ucwords(str_replace('_', ' ', (string)$forecastKey))),
                $current === null ? 'n.d.' : number_format((float)$current, 1, ',', '.') . $unit,
                $future === null ? 'n.d.' : number_format((float)$future, 1, ',', '.') . $unit,
                $delta === null ? 'n.d.' : (($delta > 0 ? '+' : '') . number_format((float)$delta, 1, ',', '.') . ($forecastKey === 'execution' ? ' pp' : '')),
                (string)($forecast['direction'] ?? 'stable'),
                number_format((float)($predictive['summary']['confidence'] ?? 0), 1, ',', '.') . '/100',
                (string)($horizon['label'] ?? 'Próxima janela equivalente'),
                (string)($forecast['explanation'] ?? ''),
            ];
        }
        foreach ((array)($predictive['leading_signals'] ?? []) as $signal) {
            if (!is_array($signal)) continue;
            $predictiveRows[] = [
                'Sinal',
                (string)($signal['label'] ?? 'Sinal preditivo'),
                (string)($signal['value'] ?? ''),
                '',
                '',
                (string)($signal['direction'] ?? 'stable'),
                number_format((float)($predictive['summary']['confidence'] ?? 0), 1, ',', '.') . '/100',
                (string)($horizon['label'] ?? 'Próxima janela equivalente'),
                (string)($signal['detail'] ?? ''),
            ];
        }
        if (!$predictiveRows) $predictiveRows[] = ['INFO','Sem projeção disponível','','','','','','','Não existem dados suficientes para uma projeção determinística neste âmbito.'];

        $contextualRows = [];
        foreach ((array)($contextual['next_best_actions'] ?? []) as $action) {
            if (!is_array($action)) continue;
            $evidenceParts = [];
            foreach (array_slice((array)($action['evidence'] ?? []), 0, 4) as $evidence) {
                if (!is_array($evidence)) continue;
                $kind = (string)($evidence['kind'] ?? 'observed') === 'projected' ? 'Projetado' : 'Observado';
                $evidenceParts[] = $kind . ': ' . (string)($evidence['label'] ?? 'Sinal') . ' ' . (string)($evidence['value'] ?? '');
            }
            $contextualRows[] = [
                number_format((float)($action['rank_score'] ?? 0),1,',','.') . '/100',
                strtoupper((string)($action['urgency'] ?? 'medium')),
                (string)($action['location_name'] ?? 'PDV'),
                (string)($action['product'] ?? ''),
                (string)($action['issue_label'] ?? $action['issue_type'] ?? 'Ação recomendada'),
                (string)($action['action'] ?? ''),
                number_format((float)($action['confidence_score'] ?? 0),1,',','.') . '/100',
                number_format((float)($action['forecast_risk_score'] ?? 0),1,',','.') . '/100',
                (string)($action['forecast_direction'] ?? 'stable'),
                implode(' | ', $evidenceParts),
                (string)($action['reason'] ?? ''),
            ];
        }
        if (!$contextualRows) $contextualRows[] = ['0/100','INFO','Sem ações contextuais','','','','','','','','Não existem sinais de loja/produto/problema suficientes neste âmbito.'];

        $adaptiveRows = [];
        foreach ((array)($adaptive['patterns'] ?? []) as $pattern) {
            if (!is_array($pattern)) continue;
            $adaptiveRows[] = [
                'Padrão', (string)($pattern['label'] ?? 'Padrão'), (int)($pattern['evidence_count'] ?? 0),
                number_format((float)($pattern['effectiveness_score'] ?? 50),1,',','.') . '%',
                number_format((float)($pattern['human_relevance_score'] ?? 50),1,',','.') . '%',
                number_format((float)($pattern['confidence_delta'] ?? 0),1,',','.'),
                number_format((float)($pattern['rank_delta'] ?? 0),1,',','.'),
                (string)($pattern['maturity'] ?? 'initial'), (string)($pattern['explanation'] ?? ''),
            ];
        }
        foreach ((array)($adaptive['next_best_actions'] ?? []) as $nba) {
            if (!is_array($nba)) continue;
            $adaptiveRows[] = [
                'Next Best Action', (string)($nba['title'] ?? 'Recomendação'), (int)($nba['evidence_count'] ?? 0), '', '',
                number_format((float)($nba['confidence_score'] ?? 0),1,',','.'),
                number_format((float)($nba['rank_score'] ?? 0),1,',','.'),
                (string)($nba['maturity'] ?? 'initial'), trim((string)($nba['action'] ?? '') . ' | ' . (string)($nba['explanation'] ?? '')),
            ];
        }
        if (!$adaptiveRows) $adaptiveRows[] = ['INFO','Sem evidência adaptativa',0,'','','','','initial','O dataset ainda não tem feedback/outcomes suficientes neste âmbito.'];

        return [[
            'name'=>'Intelligence',
            'title'=>'FieldFlow Intelligence',
            'subtitle'=>(string)($context['client_name'] ?? '') . ' | ' . (string)($context['project_name'] ?? '') . ' | ' . (string)($context['period'] ?? ''),
            'headers'=>['Tipo','Indicador / Widget','Valor','Estado / Tema','Justificação'],
            'rows'=>$intelligenceRows,
            'types'=>['text','text','auto','text','text'],
            'widths'=>[20,34,18,22,70],
        ],[
            'name'=>'Adaptive Intelligence',
            'title'=>'Adaptive Intelligence Foundation',
            'subtitle'=>'Padrões históricos, guardrails e Next Best Actions, sem alterar scores operacionais',
            'headers'=>['Tipo','Padrão / Ação','Evidência','Eficácia','Relevância humana','Δ confiança / confiança','Δ ranking / rank','Maturidade','Explicação'],
            'rows'=>$adaptiveRows,
            'types'=>['text','text','integer','text','text','text','text','text','text'],
            'widths'=>[18,44,12,14,18,18,18,16,78],
        ],[
            'name'=>'Predictive Intelligence',
            'title'=>'Predictive Intelligence Foundation',
            'subtitle'=>'Projeção determinística da próxima janela, não probabilística e sem alterar scores operacionais',
            'headers'=>['Tipo','Indicador / Sinal','Atual','Projetado','Delta','Direção','Confiança','Horizonte','Explicação'],
            'rows'=>$predictiveRows,
            'types'=>['text','text','text','text','text','text','text','text','text'],
            'widths'=>[16,34,16,16,14,16,16,28,78],
        ],[
            'name'=>'Contextual Actions',
            'title'=>'Contextual Predictive Intelligence + Next Best Action',
            'subtitle'=>'Prioridades por loja, produto e tipo de problema, separando sinais observados de projeções e mantendo revisão humana',
            'headers'=>['Prioridade','Urgência','Loja','Produto','Tipo de problema','Next Best Action','Confiança','Risco futuro','Direção prevista','Evidência','Justificação'],
            'rows'=>$contextualRows,
            'types'=>['text','text','text','text','text','text','text','text','text','text','text'],
            'widths'=>[16,14,34,28,30,70,16,16,18,76,76],
        ],[
            'name'=>'Alertas Lojas',
            'title'=>'Alertas e prioridades por loja',
            'subtitle'=>'Lojas que merecem atenção no período selecionado',
            'headers'=>['Severidade','Prioridade','Loja / Local','Cidade','Execução','Pendentes','Sinais','Detalhe'],
            'rows'=>$alertRows,
            'types'=>['text','number','text','text','text','integer','text','text'],
            'widths'=>[14,14,34,22,14,12,28,70],
        ],[
            'name'=>'Store Intelligence',
            'title'=>'Store Opportunity & Risk Engine',
            'subtitle'=>'Perfil por loja, scores determinísticos e confiança auditável',
            'headers'=>['Prioridade','Loja','Cidade','Health','Execução','Risco','Oportunidade','Stock Risk','Share Gap','Tendência','Confiança','Eficácia histórica','Risco futuro','Prioridade futura','Direção prevista','Confiança preditiva','Problema prioritário','Produto foco','Next Best Action','Motivo'],
            'rows'=>$storeRows,
            'types'=>['text','text','text','text','text','text','text','text','text','text','text','text','text','text','text','text','text','text','text','text'],
            'widths'=>[14,34,20,14,14,14,16,14,14,16,14,18,16,18,18,18,30,28,70,76],
        ],[
            'name'=>'Recomendações',
            'title'=>'FieldFlow Intelligence, recomendações e aprendizagem',
            'subtitle'=>'Recomendações determinísticas, feedback humano e outcomes observados',
            'headers'=>['Severidade','Estado','Local ID','Recomendação','Ação sugerida','Rank adaptativo','Confiança adaptativa','Evidência','Baseline','Atual','Outcome','Justificação / outcome'],
            'rows'=>$recommendationRows,
            'types'=>['text','text','integer','text','text','text','text','integer','text','text','text','text'],
            'widths'=>[14,16,12,44,58,16,18,12,16,16,20,70],
        ]];
    }

    private static function canonical_dashboard_export_sheets(array $report, array $context): array {
        // Historical 9AE markers retained for successor certification: Resumo Executivo, Rotas & Visitas, Espacos Extra.
        $k = is_array($report['kpis'] ?? null) ? $report['kpis'] : [];
        $analysis = is_array($report['commercial_analysis'] ?? null)
            ? $report['commercial_analysis']
            : \RoutesPro\Reporting\DailyCommercialReportService::build($report);
        $cap = is_array($analysis['capabilities'] ?? null) ? $analysis['capabilities'] : [];
        $audience = (string)($context['report_audience'] ?? 'client') === 'merchandiser' ? 'merchandiser' : 'client';
        $narrative = is_array($analysis[$audience] ?? null) ? $analysis[$audience] : [];
        $summary = [
            ['Periodo', (string)($context['period'] ?? ''), 'Ambito temporal'],
            ['Cliente', (string)($context['client_name'] ?? 'Global'), 'Ambito'],
            ['Projeto / Campanha', (string)($context['project_name'] ?? 'Global'), 'Ambito'],
            ['Operativo', (string)($context['user_name'] ?? 'Todos'), 'Ambito'],
        ];
        if (!empty($narrative['headline'])) $summary[] = ['Leitura do periodo', (string)$narrative['headline'], $audience === 'client' ? 'Visao comercial dinamica' : 'Fecho operacional pessoal'];
        foreach ((array)($narrative['paragraphs'] ?? []) as $i => $paragraph) {
            $paragraph = trim((string)$paragraph);
            if ($paragraph !== '') $summary[] = ['Analise ' . ((int)$i + 1), $paragraph, 'Gerada apenas a partir dos dados canonicos'];
        }
        if (!empty($cap['visits'])) {
            $summary[] = ['Execucao', number_format((float)($k['execution_rate'] ?? 0), 1, ',', '.') . '%', (int)($k['visits_completed'] ?? 0) . ' realizadas / ' . (int)($k['visits_planned'] ?? 0) . ' planeadas | ' . (int)($k['visits_justified'] ?? 0) . ' justificadas | fecho ' . number_format((float)($k['operational_closure_rate'] ?? 0), 1, ',', '.') . '%'];
            $summary[] = ['Rotas concluidas', (int)($k['routes_completed'] ?? 0) . ' / ' . (int)($k['routes_total'] ?? 0), number_format((float)($k['routes_completion_rate'] ?? 0), 1, ',', '.') . '%'];
        }
        if (!empty($cap['fronts'])) $summary[] = ['Ganho liquido de frentes', self::signed_number($k['fronts_gain'] ?? 0), self::signed_percent($k['fronts_growth_percent'] ?? null) . ' | ' . (int)($k['fronts_locations_gain'] ?? 0) . ' lojas com ganho'];
        if (!empty($cap['extra_spaces'])) $summary[] = ['Espacos extra / conquistas', (int)($k['extra_spaces'] ?? 0), (int)($k['extra_space_locations'] ?? 0) . ' lojas'];
        if (!empty($cap['orders'])) $summary[] = ['Sugestoes de encomenda', (int)($k['order_suggestions'] ?? 0) . ' submissoes', (int)($k['order_lines'] ?? 0) . ' linhas | ' . self::plain_number($k['order_boxes'] ?? 0) . ' caixas | ' . self::plain_number($k['order_units'] ?? 0) . ' unidades'];
        if (!empty($cap['briefing'])) $summary[] = ['Briefing', number_format((float)($k['briefing_coverage_percent'] ?? 0), 1, ',', '.') . '% cobertura', (int)($k['briefing_answered_assignments'] ?? 0) . ' / ' . (int)($k['briefing_target_assignments'] ?? 0) . ' respostas alvo | ' . (int)($k['briefing_questions'] ?? 0) . ' pergunta(s) aplicável(is) | ' . (int)($k['briefing_target_stores'] ?? 0) . ' loja(s) alvo'];
        if (!empty($cap['mobility'])) {
            $mobilitySummary = is_array($report['mobility']['summary'] ?? null) ? $report['mobility']['summary'] : [];
            $partialLabel = !empty($mobilitySummary['partial']) ? 'PARCIAL · ' : '';
            $recoveredLabel = (int)($mobilitySummary['recovered_routes'] ?? 0) > 0 ? 'ESTIMATIVA RECUPERADA · ' : '';
            $closedRoutes = (int)($mobilitySummary['closed_routes'] ?? 0);
            $incompleteRoutes = (int)($mobilitySummary['incomplete_routes'] ?? 0);
            $distanceValue = (float)($mobilitySummary['distance_km'] ?? 0);
            $hasDistance = $distanceValue > 0 || ($closedRoutes > 0 && $incompleteRoutes === 0);
            $hasCosts = (int)($mobilitySummary['costed_routes'] ?? 0) > 0;
            $summary[] = ['Km executados', $hasDistance ? $partialLabel . $recoveredLabel . number_format($distanceValue, 1, ',', '.') . ' km' : 'POR APURAR', $closedRoutes . ' rota(s) operacionalmente fechada(s)'];
            $summary[] = ['Combustivel / energia estimado', $hasCosts ? $partialLabel . $recoveredLabel . number_format((float)($mobilitySummary['energy_cost_eur'] ?? 0), 2, ',', '.') . ' EUR' : 'POR CALCULAR', $recoveredLabel !== '' ? 'Estimativa recuperada pelo MobilityCostService a partir dos km conhecidos' : 'Valor do MobilityCostService'];
            $summary[] = ['Portagens estimadas', $hasCosts ? $partialLabel . $recoveredLabel . number_format((float)($mobilitySummary['toll_cost_eur'] ?? 0), 2, ',', '.') . ' EUR' : 'POR CALCULAR', $recoveredLabel !== '' ? 'Valor conhecido/recuperado da rota' : 'Valor canonico da rota'];
            $summary[] = ['Total combustivel/energia + portagens', $hasCosts ? $partialLabel . $recoveredLabel . number_format((float)($mobilitySummary['fuel_tolls_cost_eur'] ?? ((float)($mobilitySummary['energy_cost_eur'] ?? 0) + (float)($mobilitySummary['toll_cost_eur'] ?? 0))), 2, ',', '.') . ' EUR' : 'POR CALCULAR', 'Soma direta dos dois custos apresentados no email'];
            $summary[] = ['Custo total de deslocacao', $hasCosts ? $partialLabel . $recoveredLabel . number_format((float)($mobilitySummary['total_mobility_cost_eur'] ?? 0), 2, ',', '.') . ' EUR' : 'POR CALCULAR', 'Inclui a componente operacional por km quando configurada'];
        }
        if (!empty($cap['evidence'])) $summary[] = ['Evidencias', (int)($k['evidence_files'] ?? 0) . ' ficheiros', (int)($k['evidence_locations'] ?? 0) . ' lojas'];
        $summary[] = ['Gerado em', (string)($context['generated_at'] ?? current_time('mysql')), CanonicalOperationalReportService::CONTRACT];

        $routeRows = [];
        foreach ((array)($report['routing']['routes'] ?? []) as $route) {
            $routeRows[] = [
                (string)($route['date'] ?? ''),(int)($route['route_id'] ?? 0),(string)($route['user_name'] ?? ''),(string)($route['status'] ?? ''),
                (int)($route['planned_stops'] ?? 0),(int)($route['completed_stops'] ?? 0),(int)($route['justified_stops'] ?? 0),(int)($route['remaining_stops'] ?? 0),
                ((float)($route['execution_rate'] ?? 0))/100,((float)($route['operational_closure_rate'] ?? 0))/100,(string)($route['start_address'] ?? ''),(string)($route['end_address'] ?? ''),(string)($route['path'] ?? ''),
                $route['access_distance_km'] ?? '',$route['inter_stop_distance_km'] ?? '',$route['return_distance_km'] ?? '',$route['distance_km'] ?? '',$route['energy_cost_eur'] ?? '',$route['toll_cost_eur'] ?? '',$route['total_mobility_cost_eur'] ?? '',(string)($route['mobility_profile_label'] ?? '')
            ];
        }

        $visitRows = [];
        foreach ((array)($analysis['visits'] ?? []) as $stop) {
            if (!is_array($stop)) continue;
            $visitRows[] = [
                (string)($stop['date'] ?? ''),(int)($stop['route_id'] ?? 0),(int)($stop['sequence'] ?? 0),(string)($stop['user_name'] ?? ''),(string)($stop['location_name'] ?? ''),(string)($stop['location_address'] ?? ''),
                !empty($stop['executed']) ? 'Sim' : 'Nao',(string)($stop['result_label'] ?? ''),(string)($stop['status'] ?? ''),!empty($stop['justified']) ? 'Sim' : 'Nao',(string)($stop['justification'] ?? ''),
                self::short_datetime($stop['planned_arrival'] ?? ''),self::short_datetime($stop['arrived_at'] ?? ''),self::short_datetime($stop['departed_at'] ?? ''),$stop['duration_minutes'] ?? '',(string)($stop['note'] ?? '')
            ];
        }

        $gainExceptionRows = [];
        foreach ((array)($analysis['gains'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $gainExceptionRows[] = ['Ganho','positivo',(string)($row['location'] ?? ''),self::signed_number($row['delta'] ?? 0),self::signed_percent($row['growth_percent'] ?? null),self::commercial_item_detail($row),'Crescimento de frentes'];
        }
        foreach ((array)($analysis['losses'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $gainExceptionRows[] = ['Perda','atencao',(string)($row['location'] ?? ''),self::signed_number($row['delta'] ?? 0),self::signed_percent($row['growth_percent'] ?? null),self::commercial_item_detail($row),'Ponto de atencao comercial'];
        }
        foreach ((array)($analysis['exceptions'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $gainExceptionRows[] = ['Excecao',(string)($row['severity'] ?? 'attention'),(string)($row['location'] ?? ''),'','','',(string)($row['detail'] ?? '')];
        }

        $alertRows = [];
        if ($audience === 'merchandiser') {
            foreach ((array)($analysis['alerts'] ?? []) as $row) {
                if (!is_array($row)) continue;
                $alertRows[] = [(string)($row['severity'] ?? 'attention'),(string)($row['type'] ?? ''),(string)($row['location'] ?? ''),(string)($row['title'] ?? ''),(string)($row['detail'] ?? '')];
            }
        }

        $frontRows = [];
        foreach ((array)($report['fronts']['rows'] ?? []) as $row) $frontRows[] = [
            (string)($row['date'] ?? ''),(string)($row['route'] ?? ''),(string)($row['location'] ?? ''),(string)($row['category'] ?? ''),(string)($row['product'] ?? ''),(string)($row['question'] ?? ''),
            $row['before'] ?? '',$row['after'] ?? '',$row['difference'] ?? '',$row['growth_percent'] ?? '',(string)($row['status'] ?? '')
        ];
        $spaceRows = [];
        foreach ((array)($report['extra_spaces']['rows'] ?? []) as $row) $spaceRows[] = [(string)($row['date'] ?? ''),(string)($row['route'] ?? ''),(string)($row['location'] ?? ''),(string)($row['type'] ?? ''),(string)($row['product'] ?? ''),(string)($row['category'] ?? ''),(string)($row['value'] ?? ''),(string)($row['notes'] ?? '')];
        $orderRows = [];
        foreach ((array)($report['orders']['rows'] ?? []) as $row) $orderRows[] = [(string)($row['date'] ?? ''),(string)($row['user'] ?? ''),(string)($row['location'] ?? ''),(string)($row['product'] ?? ''),(string)($row['reference'] ?? ''),(string)($row['item_type'] ?? ''),(string)($row['capacity'] ?? ''),$row['boxes'] ?? 0,$row['units_per_case'] ?? 0,$row['units'] ?? 0,(string)($row['notes'] ?? ''),(int)($row['submission_id'] ?? 0)];
        $briefRows = [];
        foreach ((array)($report['briefing']['rows'] ?? []) as $row) $briefRows[] = [(string)($row['dashboard'] ?? ''),(string)($row['question'] ?? ''),(string)($row['type'] ?? ''),(int)($row['target_assignments'] ?? 0),(int)($row['answered_assignments'] ?? $row['record_count'] ?? 0),(int)($row['pending_assignments'] ?? 0),(int)($row['target_stores'] ?? 0),(int)($row['answered_stores'] ?? 0),((float)($row['coverage_percent'] ?? 0))/100,(int)($row['evidence_files'] ?? 0),(string)($row['note'] ?? '')];
        $evidenceRows = [];
        foreach ((array)($report['evidence']['rows'] ?? []) as $row) $evidenceRows[] = [(string)($row['date'] ?? ''),(int)($row['route_id'] ?? 0),(string)($row['location'] ?? ''),(string)($row['question'] ?? ''),(string)($row['name'] ?? ''),(string)($row['url'] ?? ''),(string)($row['mime'] ?? ''),(int)($row['attachment_id'] ?? 0)];

        $mobilityRows = [];
        foreach ((array)($report['mobility']['by_day'] ?? []) as $day) {
            if (!is_array($day)) continue;
            $dayHasCosts = (int)($day['costed_routes'] ?? 0) > 0;
            $dayState = !empty($day['partial']) ? 'Parcial' : 'Completo';
            if ((int)($day['recovered_routes'] ?? 0) > 0) $dayState .= ' · estimativa recuperada';
            if (!$dayHasCosts) $dayState .= ' · custos por calcular';
            $mobilityRows[] = [
                (string)($day['date'] ?? ''),
                (float)($day['access_distance_km'] ?? 0),
                (float)($day['inter_stop_distance_km'] ?? 0),
                (float)($day['return_distance_km'] ?? 0),
                (float)($day['distance_km'] ?? 0),
                $dayHasCosts ? (float)($day['energy_cost_eur'] ?? 0) : '',
                $dayHasCosts ? (float)($day['toll_cost_eur'] ?? 0) : '',
                $dayHasCosts ? (float)($day['fuel_tolls_cost_eur'] ?? ((float)($day['energy_cost_eur'] ?? 0) + (float)($day['toll_cost_eur'] ?? 0))) : '',
                $dayHasCosts ? (float)($day['total_mobility_cost_eur'] ?? 0) : '',
                $dayHasCosts ? (float)($day['estimated_litres'] ?? 0) : '',
                $dayHasCosts ? (float)($day['estimated_kwh'] ?? 0) : '',
                (int)($day['closed_routes'] ?? 0),
                (int)($day['costed_routes'] ?? 0),
                $dayState,
            ];
        }
        if ($mobilityRows) {
            $mt = is_array($report['mobility']['summary'] ?? null) ? $report['mobility']['summary'] : [];
            $totalHasCosts = (int)($mt['costed_routes'] ?? 0) > 0;
            $totalState = !empty($mt['partial']) ? 'Parcial' : 'Completo';
            if ((int)($mt['recovered_routes'] ?? 0) > 0) $totalState .= ' · estimativa recuperada';
            if (!$totalHasCosts) $totalState .= ' · custos por calcular';
            $mobilityRows[] = [
                'TOTAL GERAL',
                (float)($mt['access_distance_km'] ?? 0),
                (float)($mt['inter_stop_distance_km'] ?? 0),
                (float)($mt['return_distance_km'] ?? 0),
                (float)($mt['distance_km'] ?? 0),
                $totalHasCosts ? (float)($mt['energy_cost_eur'] ?? 0) : '',
                $totalHasCosts ? (float)($mt['toll_cost_eur'] ?? 0) : '',
                $totalHasCosts ? (float)($mt['fuel_tolls_cost_eur'] ?? ((float)($mt['energy_cost_eur'] ?? 0) + (float)($mt['toll_cost_eur'] ?? 0))) : '',
                $totalHasCosts ? (float)($mt['total_mobility_cost_eur'] ?? 0) : '',
                $totalHasCosts ? (float)($mt['estimated_litres'] ?? 0) : '',
                $totalHasCosts ? (float)($mt['estimated_kwh'] ?? 0) : '',
                (int)($mt['closed_routes'] ?? 0),
                (int)($mt['costed_routes'] ?? 0),
                $totalState,
            ];
        }

        $sheets = [
            ['name'=>'Resumo Comercial','title'=>$audience === 'client' ? 'Resumo comercial e operacional' : 'Resumo operacional pessoal','subtitle'=>'Analise dinamica baseada no mesmo snapshot usado no email e Analytics','headers'=>['KPI / Analise','Valor','Leitura'],'rows'=>$summary,'types'=>['text','text','text'],'widths'=>[34,52,68]],
        ];
        if ($routeRows) $sheets[] = ['name'=>'Rotas','title'=>'Routing e concretizacao','subtitle'=>'Resultado operacional por rota, incluindo percurso integral e mobilidade canonica','headers'=>['Data','Rota','Operativo','Estado','Planeadas','Realizadas','Justificadas','Pendentes','Execucao','Fecho operacional','Partida','Regresso','Percurso','Km acesso','Km entre PDVs','Km regresso','Km executados','Comb./energia EUR','Portagens EUR','Custo total EUR','Perfil mobilidade'],'rows'=>$routeRows,'types'=>['date','integer','text','text','integer','integer','integer','integer','percent','percent','text','text','text','decimal','decimal','decimal','decimal','currency','currency','currency','text'],'widths'=>[14,10,24,18,12,12,12,12,12,15,36,36,80,14,16,14,14,18,16,16,28]];
        if ($mobilityRows) $sheets[] = ['name'=>'Mobilidade Diaria','title'=>'Mobilidade e custos por dia','subtitle'=>'Percurso integral por dia: acesso + entre PDVs + regresso; a ultima linha apresenta o total geral do periodo','headers'=>['Data','Km acesso','Km entre PDVs','Km regresso','Km executados','Comb./energia EUR','Portagens EUR','Total comb.+portagens EUR','Custo deslocacao EUR','Litros estimados','kWh estimados','Rotas fechadas','Rotas com custo','Estado'],'rows'=>$mobilityRows,'types'=>['text','decimal','decimal','decimal','decimal','currency','currency','currency','currency','decimal','decimal','integer','integer','text'],'widths'=>[16,14,16,14,16,20,16,22,20,16,16,16,18,18]];
        if ($visitRows) $sheets[] = ['name'=>'Visitas','title'=>'Visitas loja a loja','subtitle'=>'Estado de execucao e justificacao explicitados por loja','headers'=>['Data','Rota','Seq.','Operativo','Loja','Morada','Executada','Resultado','Estado','Justificada','Justificacao','Planeado','Chegada','Saida','Duracao min','Notas'],'rows'=>$visitRows,'types'=>['date','integer','integer','text','text','text','text','text','text','text','text','text','text','text','decimal','text'],'widths'=>[14,10,8,24,30,42,12,26,14,12,52,18,18,18,14,52]];
        if ($gainExceptionRows) $sheets[] = ['name'=>'Ganhos e Excecoes','title'=>'Ganhos, perdas e excecoes','subtitle'=>'Leitura comercial por loja com os pontos que merecem acompanhamento','headers'=>['Tipo','Prioridade','Loja','Saldo','Crescimento','Detalhe','Leitura'],'rows'=>$gainExceptionRows,'types'=>['text','text','text','text','text','text','text'],'widths'=>[16,14,32,14,16,60,60]];
        if ($alertRows) $sheets[] = ['name'=>'Alertas','title'=>'Alertas e acoes do merchandiser','subtitle'=>'Pontos a rever ou regularizar a partir dos dados do dia','headers'=>['Prioridade','Tipo','Loja','Alerta','Acao / detalhe'],'rows'=>$alertRows,'types'=>['text','text','text','text','text'],'widths'=>[14,18,30,36,70]];
        if ($frontRows) $sheets[] = ['name'=>'Frentes','title'=>'Ganhos de frentes','subtitle'=>'Antes, depois e ganho por loja/produto','headers'=>['Data','Rota','Loja','Categoria','Produto','Pergunta','Antes','Depois','Ganho','Crescimento %','Estado'],'rows'=>$frontRows,'types'=>['date','text','text','text','text','text','decimal','decimal','decimal','decimal','text'],'widths'=>[14,12,30,24,28,40,12,12,12,14,14]];
        if ($spaceRows) $sheets[] = ['name'=>'Espacos Extra','title'=>'Espacos extra e conquistas','subtitle'=>'Implementacoes comerciais positivas detetadas no periodo','headers'=>['Data','Rota','Loja','Tipo','Produto','Categoria','Valor','Notas'],'rows'=>$spaceRows,'types'=>['date','text','text','text','text','text','text','text'],'widths'=>[14,12,30,34,28,24,18,60]];
        if ($orderRows) $sheets[] = ['name'=>'Encomendas','title'=>'Sugestoes de encomenda','subtitle'=>'Linhas sugeridas por loja e produto','headers'=>['Data','Operativo','Loja','Produto','Referencia','Tipo','Capacidade','Caixas','Unid./caixa','Unidades','Notas','Submissao'],'rows'=>$orderRows,'types'=>['date','text','text','text','text','text','text','decimal','decimal','decimal','text','integer'],'widths'=>[14,24,30,32,20,18,16,12,14,14,60,12]];
        if ($briefRows) $sheets[] = ['name'=>'Briefing','title'=>'Perguntas de briefing','subtitle'=>'Apenas perguntas mapeadas às lojas presentes no âmbito/rotas do relatório','headers'=>['Dashboard','Pergunta','Tipo','Respostas alvo','Respondidas','Pendentes','Lojas alvo','Lojas respondidas','Cobertura','Evidencias','Nota'],'rows'=>$briefRows,'types'=>['text','text','text','integer','integer','integer','integer','integer','percent','integer','text'],'widths'=>[26,46,18,14,14,12,12,16,12,12,64]];
        if ($evidenceRows) $sheets[] = ['name'=>'Evidencias','title'=>'Evidencias e imagens','subtitle'=>'Indice dos ficheiros associados aos reportes','headers'=>['Data','Rota','Loja','Pergunta','Ficheiro','URL','Tipo','Media ID'],'rows'=>$evidenceRows,'types'=>['date','integer','text','text','text','text','text','integer'],'widths'=>[14,10,30,44,34,70,20,12]];
        return $sheets;
    }

    private static function commercial_item_detail(array $entry): string {
        $parts = [];
        foreach ((array)($entry['items'] ?? []) as $item) {
            if (!is_array($item)) continue;
            $label = trim((string)($item['label'] ?? ''));
            if ($label === '') continue;
            $parts[] = $label . ' ' . self::signed_number($item['delta'] ?? 0);
        }
        return implode(' | ', array_slice($parts, 0, 5));
    }

    private static function canonical_dashboard_export_csv(array $report): array {
        $headers = ['Seccao','Data','Rota','Sequencia','Operativo','Loja','Item / Pergunta','Antes','Depois','Delta','Valor / Estado','Notas / Evidencia'];
        $rows = [];
        foreach ((array)($report['routing']['routes'] ?? []) as $row) $rows[] = ['Routing',(string)($row['date'] ?? ''),(string)($row['route_id'] ?? ''),'',(string)($row['user_name'] ?? ''),'','Rota completa','','','',(string)($row['execution_rate'] ?? 0) . '% execucao | ' . (string)($row['operational_closure_rate'] ?? 0) . '% fecho',(string)($row['path'] ?? '')];
        foreach ((array)($report['routing']['stops'] ?? []) as $row) $rows[] = ['Routing',(string)($row['date'] ?? ''),(string)($row['route_id'] ?? ''),(string)($row['sequence'] ?? ''),(string)($row['user_name'] ?? ''),(string)($row['location_name'] ?? ''),'Visita','','','',(string)($row['status'] ?? ''),trim((string)($row['justification'] ?? '') . ' ' . (string)($row['note'] ?? ''))];
        foreach ((array)($report['fronts']['rows'] ?? []) as $row) $rows[] = ['Frentes',(string)($row['date'] ?? ''),(string)($row['route'] ?? ''),'','',(string)($row['location'] ?? ''),(string)($row['product'] ?? $row['question'] ?? ''),(string)($row['before'] ?? ''),(string)($row['after'] ?? ''),(string)($row['difference'] ?? ''),(string)($row['growth_percent'] ?? ''),(string)($row['status'] ?? '')];
        foreach ((array)($report['extra_spaces']['rows'] ?? []) as $row) $rows[] = ['Espacos Extra',(string)($row['date'] ?? ''),(string)($row['route'] ?? ''),'','',(string)($row['location'] ?? ''),(string)($row['type'] ?? ''),'','','',(string)($row['value'] ?? ''),(string)($row['notes'] ?? '')];
        foreach ((array)($report['orders']['rows'] ?? []) as $row) $rows[] = ['Encomendas',(string)($row['date'] ?? ''),'','',(string)($row['user'] ?? ''),(string)($row['location'] ?? ''),(string)($row['product'] ?? ''),'','','',(string)($row['boxes'] ?? 0) . ' caixas / ' . (string)($row['units'] ?? 0) . ' un.',(string)($row['notes'] ?? '')];
        foreach ((array)($report['briefing']['rows'] ?? []) as $row) $rows[] = ['Briefing','','','','','',(string)($row['question'] ?? ''),'','','',(string)($row['value'] ?? ''),(string)($row['note'] ?? '')];
        foreach ((array)($report['evidence']['rows'] ?? []) as $row) $rows[] = ['Evidencias',(string)($row['date'] ?? ''),(string)($row['route_id'] ?? ''),'','',(string)($row['location'] ?? ''),(string)($row['question'] ?? ''),'','','',(string)($row['name'] ?? ''),(string)($row['url'] ?? '')];
        return [$headers,$rows];
    }

    private static function build_canonical_dashboard_pdf_binary(array $report, array $context): string {
        $k = is_array($report['kpis'] ?? null) ? $report['kpis'] : [];
        $analysis = is_array($report['commercial_analysis'] ?? null)
            ? $report['commercial_analysis']
            : \RoutesPro\Reporting\DailyCommercialReportService::build($report);
        $cap = is_array($analysis['capabilities'] ?? null) ? $analysis['capabilities'] : [];
        $audience = (string)($context['report_audience'] ?? 'client') === 'merchandiser' ? 'merchandiser' : 'client';
        $narrative = is_array($analysis[$audience] ?? null) ? $analysis[$audience] : [];
        $blocks = [];
        if (!empty($narrative['headline'])) $blocks[] = ['type'=>'section','lines'=>[strtoupper((string)($narrative['eyebrow'] ?? 'RESUMO')) . ' | ' . (string)$narrative['headline']]];
        if (!empty($narrative['paragraphs'])) $blocks[] = ['type'=>'text','lines'=>array_values(array_filter(array_map('strval', (array)$narrative['paragraphs'])))];

        if (!empty($cap['visits'])) {
            $blocks[] = ['type'=>'section','lines'=>['EXECUCAO E ROUTING']];
            $blocks[] = ['type'=>'text','lines'=>[
                'Execucao: ' . number_format((float)($k['execution_rate'] ?? 0), 1, ',', '.') . '% | ' . (int)($k['visits_completed'] ?? 0) . ' realizadas / ' . (int)($k['visits_planned'] ?? 0) . ' planeadas | ' . (int)($k['visits_justified'] ?? 0) . ' justificadas | fecho ' . number_format((float)($k['operational_closure_rate'] ?? 0), 1, ',', '.') . '%',
                'Rotas: ' . (int)($k['routes_completed'] ?? 0) . ' / ' . (int)($k['routes_total'] ?? 0) . ' concluidas',
            ]];
        }
        if (!empty($cap['mobility'])) {
            $mobilitySummary = is_array($report['mobility']['summary'] ?? null) ? $report['mobility']['summary'] : [];
            $mobilityLines = [];
            foreach ((array)($report['mobility']['by_day'] ?? []) as $day) {
                if (!is_array($day)) continue;
                $dayHasCosts = (int)($day['costed_routes'] ?? 0) > 0;
                $dayDistance = (float)($day['distance_km'] ?? 0);
                $dayHasDistance = $dayDistance > 0 || ((int)($day['closed_routes'] ?? 0) > 0 && (int)($day['incomplete_routes'] ?? 0) === 0);
                $mobilityLines[] = (string)($day['date'] ?? '') . ' | ' . ($dayHasDistance ? number_format($dayDistance, 1, ',', '.') . ' km' : 'km por apurar') . ' | combustivel/energia ' . ($dayHasCosts ? number_format((float)($day['energy_cost_eur'] ?? 0), 2, ',', '.') . ' EUR' : 'por calcular') . ' | portagens ' . ($dayHasCosts ? number_format((float)($day['toll_cost_eur'] ?? 0), 2, ',', '.') . ' EUR' : 'por calcular') . ' | comb.+portagens ' . ($dayHasCosts ? number_format((float)($day['fuel_tolls_cost_eur'] ?? ((float)($day['energy_cost_eur'] ?? 0) + (float)($day['toll_cost_eur'] ?? 0))), 2, ',', '.') . ' EUR' : 'por calcular') . ' | deslocacao total ' . ($dayHasCosts ? number_format((float)($day['total_mobility_cost_eur'] ?? 0), 2, ',', '.') . ' EUR' : 'por calcular') . (!empty($day['partial']) ? ' | PARCIAL' : '') . ((int)($day['recovered_routes'] ?? 0) > 0 ? ' | ESTIMATIVA RECUPERADA' : '');
            }
            $totalHasCosts = (int)($mobilitySummary['costed_routes'] ?? 0) > 0;
            $totalDistance = (float)($mobilitySummary['distance_km'] ?? 0);
            $totalHasDistance = $totalDistance > 0 || ((int)($mobilitySummary['closed_routes'] ?? 0) > 0 && (int)($mobilitySummary['incomplete_routes'] ?? 0) === 0);
            $mobilityLines[] = 'TOTAL GERAL' . (!empty($mobilitySummary['partial']) ? ' PARCIAL' : '') . ((int)($mobilitySummary['recovered_routes'] ?? 0) > 0 ? ' ESTIMATIVA RECUPERADA' : '') . ' | ' . ($totalHasDistance ? number_format($totalDistance, 1, ',', '.') . ' km' : 'km por apurar') . ' | combustivel/energia ' . ($totalHasCosts ? number_format((float)($mobilitySummary['energy_cost_eur'] ?? 0), 2, ',', '.') . ' EUR' : 'por calcular') . ' | portagens ' . ($totalHasCosts ? number_format((float)($mobilitySummary['toll_cost_eur'] ?? 0), 2, ',', '.') . ' EUR' : 'por calcular') . ' | comb.+portagens ' . ($totalHasCosts ? number_format((float)($mobilitySummary['fuel_tolls_cost_eur'] ?? ((float)($mobilitySummary['energy_cost_eur'] ?? 0) + (float)($mobilitySummary['toll_cost_eur'] ?? 0))), 2, ',', '.') . ' EUR' : 'por calcular') . ' | deslocacao total ' . ($totalHasCosts ? number_format((float)($mobilitySummary['total_mobility_cost_eur'] ?? 0), 2, ',', '.') . ' EUR' : 'por calcular');
            $blocks[] = ['type'=>'section','lines'=>['MOBILIDADE E CUSTOS']];
            $blocks[] = ['type'=>'text','lines'=>$mobilityLines];
        }
        if (!empty($cap['fronts'])) {
            $blocks[] = ['type'=>'section','lines'=>['IMPACTO COMERCIAL, FRENTES']];
            $blocks[] = ['type'=>'text','lines'=>['Ganho liquido: ' . self::signed_number($k['fronts_gain'] ?? 0) . ' | Crescimento: ' . self::signed_percent($k['fronts_growth_percent'] ?? null) . ' | ' . (int)($k['fronts_locations_gain'] ?? 0) . ' lojas com ganho']];
        }
        if (!empty($cap['extra_spaces'])) {
            $blocks[] = ['type'=>'section','lines'=>['CONQUISTAS E IMPLEMENTACOES']];
            $blocks[] = ['type'=>'text','lines'=>[(int)($k['extra_spaces'] ?? 0) . ' registos em ' . (int)($k['extra_space_locations'] ?? 0) . ' lojas']];
        }
        if (!empty($cap['orders'])) {
            $blocks[] = ['type'=>'section','lines'=>['OPORTUNIDADES DE ENCOMENDA']];
            $blocks[] = ['type'=>'text','lines'=>[(int)($k['order_suggestions'] ?? 0) . ' submissoes | ' . (int)($k['order_lines'] ?? 0) . ' linhas | ' . self::plain_number($k['order_boxes'] ?? 0) . ' caixas | ' . self::plain_number($k['order_units'] ?? 0) . ' unidades']];
        }
        if ($audience === 'client') {
            $exceptions = array_values(array_filter((array)($analysis['exceptions'] ?? []), 'is_array'));
            if ($exceptions) {
                $blocks[] = ['type'=>'section','lines'=>['EXCECOES E PONTOS DE ATENCAO']];
                foreach (array_slice($exceptions, 0, 12) as $row) $blocks[] = ['type'=>'text','lines'=>[(string)($row['location'] ?? 'Loja') . ': ' . (string)($row['detail'] ?? '')]];
            }
        } else {
            $alerts = array_values(array_filter((array)($analysis['alerts'] ?? []), 'is_array'));
            if ($alerts) {
                $blocks[] = ['type'=>'section','lines'=>['ALERTAS E ACOES']];
                foreach (array_slice($alerts, 0, 12) as $row) $blocks[] = ['type'=>'text','lines'=>[(string)($row['location'] ?? '') . ((string)($row['location'] ?? '') !== '' ? ': ' : '') . (string)($row['detail'] ?? $row['title'] ?? '')]];
            }
        }
        $visits = array_values(array_filter((array)($analysis['visits'] ?? []), 'is_array'));
        if ($visits) {
            $blocks[] = ['type'=>'section','lines'=>['VISITAS LOJA A LOJA']];
            foreach (array_slice($visits, 0, 40) as $visit) {
                $line = (string)($visit['location_name'] ?? 'Loja') . ' | ' . (string)($visit['result_label'] ?? $visit['status'] ?? '');
                if (trim((string)($visit['justification'] ?? '')) !== '') $line .= ' | Justificacao: ' . (string)$visit['justification'];
                $blocks[] = ['type'=>'text','lines'=>[$line]];
            }
        }
        return self::build_simple_pdf([], [
            'title'=>$audience === 'client' ? 'FieldFlow | Relatorio Comercial Diario' : 'FieldFlow | Fecho Operacional Pessoal',
            'subtitle'=>trim((string)($context['client_name'] ?? '') . ' | ' . (string)($context['project_name'] ?? '') . ' | ' . (string)($context['period'] ?? '')),
            'summary'=>[
                'Execucao'=>!empty($cap['visits']) ? number_format((float)($k['execution_rate'] ?? 0),1,',','.') . '%' : 'n.a.',
                'Frentes'=>!empty($cap['fronts']) ? self::signed_number($k['fronts_gain'] ?? 0) : 'n.a.',
                'Conquistas'=>!empty($cap['extra_spaces']) ? (string)(int)($k['extra_spaces'] ?? 0) : 'n.a.',
                'Alertas'=>(string)count((array)($analysis['alerts'] ?? [])),
            ],
            'blocks'=>$blocks,
        ]);
    }

    private static function signed_number($value): string {
        $n = (float)$value;
        return ($n > 0 ? '+' : '') . number_format($n, 1, ',', '.');
    }

    private static function signed_percent($value): string {
        if ($value === null || $value === '') return 'n.d.';
        $n = (float)$value;
        return ($n > 0 ? '+' : '') . number_format($n, 1, ',', '.') . '%';
    }

    private static function plain_number($value): string {
        $n = (float)$value;
        return number_format($n, abs($n - round($n)) < 0.0001 ? 0 : 1, ',', '.');
    }

    private static function short_datetime($value): string {
        $value = trim((string)$value);
        if ($value === '') return '-';
        $ts = strtotime($value);
        return $ts ? wp_date('d/m H:i', $ts) : $value;
    }

    private static function dashboard_export_sheets(array $data, array $filters, array $context): array {
        $prev = is_array($data['previous_summary'] ?? null) ? $data['previous_summary'] : [];
        $rank = is_array($data['rankings'] ?? null) ? $data['rankings'] : [];
        $done_rate = (float)($data['done_rate'] ?? 0);
        $previous_rate = (float)($prev['done_rate'] ?? 0);
        $delta_rate = round($done_rate - $previous_rate, 1);
        $summaryRows = [
            ['Período', $context['period'], 'Filtro temporal do relatório'],
            ['Cliente', $context['client_name'], 'Âmbito selecionado'],
            ['Projeto / Campanha', $context['project_name'], 'Âmbito selecionado'],
            ['Operativo', $context['user_name'], 'Todos quando não filtrado'],
            ['Loja / Local', $context['location_name'], 'Todas quando não filtrado'],
            ['Categoria', (string)($filters['category'] ?? '') ?: 'Todas', 'Filtro analytics'],
            ['Produto', (string)($filters['product'] ?? '') ?: 'Todos', 'Filtro analytics'],
            ['Fonte dos dados', (string)($context['data_source'] ?? 'Analytics FieldFlow'), 'A exportação reutiliza os dados visíveis quando disponíveis'],
            ['Linhas disponíveis', (int)($context['route_rows'] ?? 0) . ' rotas · ' . (int)($context['metric_rows'] ?? 0) . ' métricas', 'Contagem antes da construção do Excel'],
            ['Rotas', (int)($data['total_routes'] ?? 0), 'Total no período'],
            ['Rotas concluídas', (int)($data['completed_routes'] ?? 0), 'Estado concluído'],
            ['Taxa de rotas concluídas', number_format((float)($data['completion_rate'] ?? 0), 1, ',', '.') . '%', 'Percentagem'],
            ['Paragens planeadas', (int)($data['total_stops'] ?? 0), 'Total no período'],
            ['Paragens concluídas', (int)($data['done_stops'] ?? 0), 'Visitas concluídas'],
            ['Execução', number_format($done_rate, 1, ',', '.') . '%', 'Paragens concluídas / planeadas'],
            ['Variação de execução', ($delta_rate > 0 ? '+' : '') . number_format($delta_rate, 1, ',', '.') . ' p.p.', 'Comparação com o período anterior'],
            ['Média de paragens por rota', (float)($data['avg_stops_per_route'] ?? 0), 'Produtividade'],
            ['Tempo médio de visita', (float)($data['avg_stop_minutes'] ?? 0), 'Minutos com check-in e checkout'],
            ['Rotas com mobilidade configurada', (int)(($data['mobility_summary']['configured_routes'] ?? 0)), 'Rotas com perfil de combustível/energia resolvido'],
            ['Rotas com custo calculado', (int)(($data['mobility_summary']['costed_routes'] ?? 0)), 'Só entram nos custos rotas com partida, percurso e chegada completos'],
            ['Rotas com distância incompleta', (int)(($data['mobility_summary']['incomplete_distance_routes'] ?? 0)), 'Excluídas dos custos até terem partida e chegada válidas'],
            ['Combustível / energia estimado', ((int)(($data['mobility_summary']['incomplete_distance_routes'] ?? 0)) > 0 ? 'PARCIAL · ' : '') . number_format((float)(($data['mobility_summary']['energy_cost_eur'] ?? 0)), 2, ',', '.') . ' EUR', 'Somatório apenas das rotas com distância integral canónica'],
            ['Custo operacional calculado', ((int)(($data['mobility_summary']['incomplete_distance_routes'] ?? 0)) > 0 ? 'PARCIAL · ' : '') . number_format((float)(($data['mobility_summary']['operational_cost_eur'] ?? 0)), 2, ',', '.') . ' EUR', 'Componente €/km apenas em rotas com distância integral'],
            ['Portagens calculadas', ((int)(($data['mobility_summary']['incomplete_distance_routes'] ?? 0)) > 0 ? 'PARCIAL · ' : '') . number_format((float)(($data['mobility_summary']['toll_cost_eur'] ?? 0)), 2, ',', '.') . ' EUR', 'Portagens apenas das rotas com percurso integral'],
            ['Custo de deslocação calculado', ((int)(($data['mobility_summary']['incomplete_distance_routes'] ?? 0)) > 0 ? 'PARCIAL · ' : '') . number_format((float)(($data['mobility_summary']['total_mobility_cost_eur'] ?? 0)), 2, ',', '.') . ' EUR', 'Energia + portagens + custo operacional apenas nas rotas completas'],
            ['Litros calculados', ((int)(($data['mobility_summary']['incomplete_distance_routes'] ?? 0)) > 0 ? 'PARCIAL · ' : '') . (string)(float)(($data['mobility_summary']['estimated_litres'] ?? 0)), 'Gasolina, gasóleo, GPL ou componente térmica, só em rotas completas'],
            ['kWh calculados', ((int)(($data['mobility_summary']['incomplete_distance_routes'] ?? 0)) > 0 ? 'PARCIAL · ' : '') . (string)(float)(($data['mobility_summary']['estimated_kwh'] ?? 0)), 'Elétrico ou PHEV, só em rotas completas'],
            ['Top campanha', (string)($rank['top_project'] ?? 'Sem dados'), (string)($rank['top_project_value'] ?? 0) . ' paragens concluídas'],
            ['Top operativo', (string)($rank['top_owner'] ?? 'Sem dados'), (string)($rank['top_owner_value'] ?? 0) . ' paragens concluídas'],
            ['Gerado em', $context['generated_at'], 'FieldFlow Pro'],
        ];

        $appendChunkedSheets = static function(array &$target, array $sheet, int $maxRows = 60000): array {
            $rows = array_values((array)($sheet['rows'] ?? []));
            $chunks = $rows ? array_chunk($rows, max(1, $maxRows)) : [[]];
            $names = [];
            $total = count($chunks);
            foreach ($chunks as $index => $chunkRows) {
                $copy = $sheet;
                $baseName = (string)($sheet['name'] ?? 'Dados');
                if ($total > 1) {
                    if (preg_match('/^(Q\d{3})(.*)$/u', $baseName, $matches)) {
                        $baseName = $matches[1] . '.' . ($index + 1) . $matches[2];
                    } else {
                        $baseName .= ' ' . ($index + 1);
                    }
                    $copy['title'] = (string)($sheet['title'] ?? $baseName) . ' · parte ' . ($index + 1) . '/' . $total;
                }
                $copy['name'] = self::excel_sheet_name($baseName);
                $copy['rows'] = $chunkRows;
                $target[] = $copy;
                $names[] = $copy['name'];
            }
            return $names;
        };

        $routeRows = [];
        foreach ((array)($data['by_day'] ?? []) as $row) {
            $mobility = is_array($row['mobility_cost'] ?? null) ? (array)$row['mobility_cost'] : [];
            $costsAvailable = !empty($mobility['costs_available']);
            $routeRows[] = [
                (string)($row['date'] ?? ''),
                (string)($row['client_name'] ?? ''),
                (string)($row['project_name'] ?? ''),
                (string)($row['user_name'] ?? ''),
                (int)($row['route_id'] ?? 0),
                (string)($row['route_status'] ?? ''),
                (int)($row['stops'] ?? 0),
                (int)($row['stops_done'] ?? 0),
                ((float)($row['done_rate'] ?? 0)) / 100,
                !empty($mobility['enabled']) ? (string)($mobility['profile_label'] ?? '') : '',
                !empty($mobility['enabled']) ? (string)($mobility['energy_type'] ?? '') : '',
                $costsAvailable ? (float)($mobility['estimated_litres'] ?? 0) : '',
                $costsAvailable ? (float)($mobility['estimated_kwh'] ?? 0) : '',
                $costsAvailable ? (float)($mobility['energy_cost_eur'] ?? 0) : '',
                $costsAvailable ? (float)($mobility['operational_cost_eur'] ?? 0) : '',
                $costsAvailable ? (float)($mobility['toll_cost_eur'] ?? 0) : '',
                $costsAvailable ? (float)($mobility['total_mobility_cost_eur'] ?? 0) : '',
            ];
        }

        $metricRows = [];
        $seriesRows = [];
        $detailRows = [];
        $evidenceRows = [];
        $questionSheets = [];
        $questionIndexRows = [];
        $questionNumber = 0;
        $exportFull = !empty($filters['export_full']);
        $detailLimit = $exportFull ? PHP_INT_MAX : max(1000, (int)apply_filters('fieldflow_dashboard_export_detail_limit', 6000));
        $perMetricLimit = $exportFull ? PHP_INT_MAX : max(250, (int)apply_filters('fieldflow_dashboard_export_per_metric_limit', 1200));
        $detailTruncated = false;
        $fa = is_array($data['form_analytics'] ?? null) ? $data['form_analytics'] : [];

        foreach ((array)($fa['metrics'] ?? []) as $metric) {
            $questionNumber++;
            $rawValue = $metric['value'] ?? null;
            $metricValue = is_numeric($rawValue) ? (float)$rawValue : (string)($rawValue ?? 'Sem dados');
            $dashboardTitle = (string)($metric['dashboard_title'] ?? 'Analytics operacional');
            $metricLabel = (string)($metric['metric_label'] ?? $metric['metric_key'] ?? 'Métrica');
            $questionLabel = trim((string)($metric['question_label'] ?? '')) ?: $metricLabel;
            $questionKey = (string)($metric['question_key'] ?? $metric['metric_key'] ?? '');
            $questionType = sanitize_key((string)($metric['question_type'] ?? '')) ?: 'dado';
            $metricAggregation = trim((string)($metric['aggregation_label'] ?? $metric['aggregation'] ?? ''));
            $metricDimension = trim((string)($metric['dimension_label'] ?? $metric['dimension'] ?? ''));
            $metricSecondaryDimension = trim((string)($metric['secondary_dimension_label'] ?? $metric['secondary_dimension'] ?? ''));
            if ($metricSecondaryDimension !== '') $metricDimension .= ($metricDimension !== '' ? ' + ' : '') . $metricSecondaryDimension;
            $frontsSummary = is_array($metric['fronts_summary'] ?? null) ? $metric['fronts_summary'] : [];
            if ($frontsSummary) {
                $summaryRows[] = [
                    'Frentes · ' . $questionLabel,
                    (string)($frontsSummary['growth_label'] ?? '0,0%'),
                    (string)($frontsSummary['before_total'] ?? 0) . ' antes · ' . (string)($frontsSummary['after_total'] ?? 0) . ' depois · ' . (string)($frontsSummary['up'] ?? 0) . ' cresceram · ' . (string)($frontsSummary['down'] ?? 0) . ' reduziram',
                ];
            }
            $metricRows[] = [
                $dashboardTitle,
                $questionLabel,
                $questionKey,
                $questionType,
                $metricLabel,
                (string)($metric['chart_type'] ?? ''),
                $metricAggregation,
                $metricDimension,
                (string)($metric['store_group_name'] ?? 'Todas as lojas'),
                $metricValue,
                (int)($metric['record_count'] ?? 0),
                (string)($metric['note'] ?? ''),
            ];

            foreach ((array)($metric['series'] ?? []) as $series) {
                $seriesRows[] = [
                    $dashboardTitle,
                    $questionLabel,
                    $questionKey,
                    $metricDimension,
                    (string)($series['label'] ?? ''),
                    is_numeric($series['value'] ?? null) ? (float)$series['value'] : (string)($series['value'] ?? ''),
                ];
            }

            $metricSourceRows = (array)($metric['rows'] ?? []);
            if ($exportFull) {
                $metricExportRows = $metricSourceRows;
            } else {
                $remaining = max(0, $detailLimit - count($detailRows));
                $metricExportRows = array_slice($metricSourceRows, 0, min($perMetricLimit, $remaining));
                if (count($metricExportRows) < count($metricSourceRows)) $detailTruncated = true;
            }
            foreach ($metricExportRows as $row) {
                $detailRows[] = [
                    $dashboardTitle,
                    $questionLabel,
                    $questionKey,
                    $questionType,
                    (string)($row['location'] ?? ''),
                    (int)($row['location_id'] ?? 0),
                    (string)($row['category'] ?? ''),
                    (string)($row['product'] ?? ''),
                    (string)($row['metric'] ?? $row['comparison'] ?? ''),
                    (string)($row['pr_label'] ?? $row['reference_a'] ?? ''),
                    (string)($row['pr_fronts'] ?? $row['fronts_a'] ?? ''),
                    (string)($row['pr_share'] ?? $row['share_a'] ?? ''),
                    (string)($row['competitor_label'] ?? $row['reference_b'] ?? ''),
                    (string)($row['competitor_fronts'] ?? $row['fronts_b'] ?? ''),
                    (string)($row['competitor_share'] ?? $row['share_b'] ?? ''),
                    (string)($row['total_fronts'] ?? ''),
                    (string)($row['target'] ?? $row['target_share'] ?? ''),
                    (string)($row['gap'] ?? $row['deviation'] ?? ''),
                    (string)($row['value'] ?? ''),
                    (string)($row['before'] ?? ''),
                    (string)($row['after'] ?? ''),
                    (string)($row['difference'] ?? ''),
                    (string)($row['growth'] ?? ''),
                    (string)($row['date'] ?? ''),
                    (string)($row['route'] ?? ''),
                    (string)($row['status'] ?? ''),
                ];
            }

            $questionRows = [[
                'Resumo', $dashboardTitle, $questionLabel, 0, $questionKey, $questionType, '', '', '', '', '', '', '', '', '', '', '', '', $metricValue, '', '', '', '', '', '', (string)($metric['note'] ?? ''), '', '', '', 0,
            ]];
            foreach ((array)($metric['series'] ?? []) as $series) {
                $questionRows[] = [
                    'Série', $dashboardTitle, (string)($series['label'] ?? ''), 0, $questionKey, $questionType, '', '', '', '', '', '', '', '', '', '', '', '',
                    is_numeric($series['value'] ?? null) ? (float)$series['value'] : (string)($series['value'] ?? ''),
                    '', '', '', '', '', '', 'Dimensão: ' . $metricDimension, '', '', '', 0,
                ];
            }
            foreach ($metricExportRows as $row) {
                $questionRows[] = [
                    'Resposta', $dashboardTitle, (string)($row['location'] ?? ''), (int)($row['location_id'] ?? 0), $questionKey, $questionType,
                    (string)($row['category'] ?? ''), (string)($row['product'] ?? ''), (string)($row['metric'] ?? $row['comparison'] ?? ''),
                    (string)($row['pr_label'] ?? $row['reference_a'] ?? ''), (string)($row['pr_fronts'] ?? $row['fronts_a'] ?? ''), (string)($row['pr_share'] ?? $row['share_a'] ?? ''),
                    (string)($row['competitor_label'] ?? $row['reference_b'] ?? ''), (string)($row['competitor_fronts'] ?? $row['fronts_b'] ?? ''), (string)($row['competitor_share'] ?? $row['share_b'] ?? ''),
                    (string)($row['total_fronts'] ?? ''), (string)($row['target'] ?? $row['target_share'] ?? ''), (string)($row['gap'] ?? $row['deviation'] ?? ''),
                    (string)($row['value'] ?? ''), (string)($row['before'] ?? ''), (string)($row['after'] ?? ''), (string)($row['difference'] ?? ''),
                    (string)($row['growth'] ?? ''), (string)($row['date'] ?? ''), (string)($row['route'] ?? ''), (string)($row['status'] ?? ''), '', '', '', 0,
                ];
            }

            $evidenceCount = 0;
            foreach ((array)($metric['evidence_groups'] ?? []) as $evidenceGroup) {
                foreach ((array)($evidenceGroup['files'] ?? []) as $file) {
                    $evidenceCount++;
                    $evidenceDate = (string)($file['date'] ?? '');
                    if ($evidenceDate === '' && !empty($file['submitted_at'])) $evidenceDate = substr((string)$file['submitted_at'], 0, 10);
                    $evidenceRow = [
                        $dashboardTitle,
                        $questionLabel,
                        $questionKey,
                        (string)($evidenceGroup['location'] ?? ''),
                        (int)($evidenceGroup['location_id'] ?? 0),
                        $evidenceDate,
                        (string)($file['name'] ?? 'Evidência'),
                        (string)($file['url'] ?? ''),
                        (string)($file['type'] ?? ''),
                        (int)($file['attachment_id'] ?? 0),
                    ];
                    $evidenceRows[] = $evidenceRow;
                    $questionRows[] = [
                        'Evidência', $dashboardTitle, (string)($evidenceGroup['location'] ?? ''), (int)($evidenceGroup['location_id'] ?? 0), $questionKey, $questionType,
                        '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', $evidenceDate, (string)($file['route_id'] ?? ''), 'Ficheiro associado à resposta',
                        (string)($file['name'] ?? 'Evidência'), (string)($file['url'] ?? ''), (string)($file['type'] ?? ''), (int)($file['attachment_id'] ?? 0),
                    ];
                }
            }

            $questionBaseName = 'Q' . str_pad((string)$questionNumber, 3, '0', STR_PAD_LEFT) . ' ' . $questionLabel;
            $questionSheetNames = $appendChunkedSheets($questionSheets, [
                'name' => $questionBaseName,
                'title' => $questionLabel,
                'subtitle' => $dashboardTitle . ' · ' . $questionType . ' · ' . (string)($metric['chart_type'] ?? '') . ' · ' . $metricAggregation . ($metricDimension !== '' ? ' · ' . $metricDimension : ''),
                'headers' => ['Tipo de linha', 'Dashboard', 'Dimensão / Loja', 'ID Loja', 'Chave da pergunta', 'Tipo de pergunta', 'Categoria', 'Produto', 'Comparação', 'Referência A', 'Frentes A', 'Quota A (%)', 'Referência B', 'Frentes B', 'Quota B (%)', 'Total frentes', 'Objetivo (%)', 'Desvio (pp)', 'Valor', 'Antes', 'Depois', 'Diferença', 'Evolução', 'Data', 'Rota', 'Estado / Nota', 'Ficheiro', 'URL', 'Tipo ficheiro', 'ID Media'],
                'rows' => $questionRows,
                'types' => ['text','text','text','integer','text','text','text','text','text','text','auto','auto','text','auto','auto','auto','auto','auto','auto','auto','auto','auto','text','date','integer','text','text','text','text','integer'],
                'widths' => [14,26,32,11,24,18,22,38,32,28,12,13,28,12,13,12,13,13,16,12,12,12,15,14,10,46,32,64,18,11],
            ]);
            $questionIndexRows[] = [
                $questionNumber,
                implode(', ', $questionSheetNames),
                $dashboardTitle,
                $questionLabel,
                $questionKey,
                $questionType,
                (string)($metric['chart_type'] ?? ''),
                $metricAggregation,
                $metricDimension,
                (string)($metric['store_group_name'] ?? 'Todas as lojas'),
                $metricValue,
                (int)($metric['record_count'] ?? 0),
                $evidenceCount,
                (string)($metric['note'] ?? ''),
            ];
        }

        if (!$routeRows) $routeRows[] = ['', $context['client_name'], $context['project_name'], $context['user_name'], '', 'Sem rotas no período', 0, 0, 0, '', '', 0, 0, 0, 0, 0, 0];
        if (!$metricRows) $metricRows[] = ['Analytics operacional', 'Sem métricas publicadas para o âmbito', '', '', '', '', '', '', 'Todas as lojas', 'Sem dados', 0, ''];
        if (!$seriesRows) $seriesRows[] = ['Analytics operacional', 'Sem séries disponíveis', '', '', '', ''];
        if (!$detailRows) $detailRows[] = ['Analytics operacional', 'Sem detalhe disponível', '', '', '', 0, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        if (!$evidenceRows) $evidenceRows[] = ['Analytics operacional', 'Sem evidências no período', '', '', 0, '', '', '', '', 0];
        if (!$questionIndexRows) $questionIndexRows[] = [0, '', 'Analytics operacional', 'Sem perguntas publicadas', '', '', '', '', '', 'Todas as lojas', 'Sem dados', 0, 0, ''];

        $summaryRows[] = ['Folhas por pergunta', $questionNumber, 'Cada pergunta ou widget Analytics dispõe da sua própria folha, com resumo, séries, respostas e evidências.'];
        if ($exportFull) $summaryRows[] = ['Integridade do Excel', count($detailRows) . ' linhas de detalhe · ' . count($evidenceRows) . ' evidências', 'Exportação integral, sem cortes por métrica. Folhas extensas são divididas em partes para manter compatibilidade com Excel.'];
        elseif ($detailTruncated) $summaryRows[] = ['Limite do detalhe Excel', count($detailRows) . ' linhas', 'O ficheiro foi otimizado para abertura rápida. Usa filtros mais curtos para detalhe adicional.'];

        $orderSuggestionRows = self::orderSuggestionExportRows($filters);
        if ($orderSuggestionRows) {
            $summaryRows[] = ['Sugestões de encomenda', count($orderSuggestionRows) . ' linhas', 'Detalhe integral incluído na folha Sugestões Encomenda.'];
        }

        $sheets = self::dashboard_intelligence_sheets($data, $context);
        if ($orderSuggestionRows) {
            $appendChunkedSheets($sheets, [
                'name' => 'Sugestões Encomenda',
                'title' => 'Sugestões de encomenda',
                'subtitle' => $context['client_name'] . ' | ' . $context['project_name'] . ' | ' . $context['period'],
                'headers' => ['Submissão ID','Data','Cliente','Projeto','Operativo','Loja / Local','Pergunta','Categoria','Referência','Produto','Tipo','Capacidade (cl)','Unidades / caixa','Caixas','Unidades totais','Observações'],
                'rows' => $orderSuggestionRows,
                'types' => ['integer','date','text','text','text','text','text','text','text','text','text','auto','auto','auto','auto','text'],
                'widths' => [12,18,24,28,24,32,36,22,18,38,18,16,18,14,16,46],
            ]);
        }
        $sheets[] = [
            'name' => 'Resumo',
            'title' => 'Relatório semanal de Analytics',
            'subtitle' => $context['client_name'] . ' | ' . $context['project_name'] . ' | ' . $context['period'],
            'headers' => ['Indicador', 'Valor', 'Contexto'],
            'rows' => $summaryRows,
            'types' => ['text', 'auto', 'text'],
            'widths' => [30, 32, 64],
        ];
        $sheets[] = [
            'name' => 'Índice',
            'title' => 'Índice de perguntas e dados',
            'subtitle' => 'Mapa das folhas individuais incluídas neste livro',
            'headers' => ['N.º', 'Folha(s)', 'Dashboard', 'Pergunta', 'Chave', 'Tipo', 'Visual', 'Agregação', 'Dimensão', 'Grupo de lojas', 'Valor', 'Registos', 'Evidências', 'Nota'],
            'rows' => $questionIndexRows,
            'types' => ['integer','text','text','text','text','text','text','text','text','text','auto','integer','integer','text'],
            'widths' => [8,26,28,38,24,18,14,16,18,26,16,12,12,52],
        ];

        $appendChunkedSheets($sheets, [
            'name' => 'Rotas',
            'title' => 'Rotas e execução',
            'subtitle' => 'Detalhe operacional do período selecionado',
            'headers' => ['Data', 'Cliente', 'Projeto', 'Operativo', 'Rota', 'Estado', 'Paragens', 'Concluídas', 'Execução', 'Perfil mobilidade', 'Tipo energia', 'Litros estimados', 'kWh estimados', 'Energia EUR', 'Custo operacional EUR', 'Portagens EUR', 'Custo deslocação EUR'],
            'rows' => $routeRows,
            'types' => ['date', 'text', 'text', 'text', 'integer', 'text', 'integer', 'integer', 'percent', 'text', 'text', 'number', 'number', 'number', 'number', 'number', 'number'],
            'widths' => [14, 24, 28, 24, 10, 16, 12, 12, 13, 34, 18, 16, 16, 16, 18, 16, 20],
        ]);

        $sheets[] = [
            'name' => 'Métricas',
            'title' => 'Métricas configuradas',
            'subtitle' => 'Resumo dos dashboards analíticos publicados',
            'headers' => ['Dashboard', 'Pergunta', 'Chave', 'Tipo pergunta', 'Métrica', 'Visual', 'Agregação', 'Dimensão', 'Grupo de lojas', 'Valor', 'Registos', 'Nota'],
            'rows' => $metricRows,
            'types' => ['text','text','text','text','text','text','text','text','text','auto','integer','text'],
            'widths' => [28,38,24,18,30,13,18,18,26,16,12,52],
        ];
        $sheets[] = [
            'name' => 'Séries',
            'title' => 'Séries dos gráficos',
            'subtitle' => 'Valores agregados utilizados nos gráficos Analytics',
            'headers' => ['Dashboard', 'Pergunta', 'Chave', 'Dimensão', 'Rótulo', 'Valor'],
            'rows' => $seriesRows,
            'types' => ['text','text','text','text','text','auto'],
            'widths' => [28,38,24,20,34,16],
        ];

        $appendChunkedSheets($sheets, [
            'name' => 'Detalhe Analytics',
            'title' => 'Detalhe analítico',
            'subtitle' => 'Dados de suporte para validação, filtros e tratamento adicional',
            'headers' => ['Dashboard', 'Pergunta', 'Chave', 'Tipo pergunta', 'Loja / Local', 'ID Loja', 'Categoria', 'Produto', 'Comparação', 'Referência A', 'Frentes A', 'Quota A (%)', 'Referência B', 'Frentes B', 'Quota B (%)', 'Total frentes', 'Objetivo (%)', 'Desvio (pp)', 'Valor', 'Antes', 'Depois', 'Diferença', 'Evolução', 'Data', 'Rota', 'Estado'],
            'rows' => $detailRows,
            'types' => ['text','text','text','text','text','integer','text','text','text','text','auto','auto','text','auto','auto','auto','auto','auto','auto','auto','auto','auto','text','date','integer','text'],
            'widths' => [28,38,24,18,30,11,22,38,32,28,12,13,28,12,13,12,13,13,14,12,12,12,16,14,10,34],
        ]);
        $appendChunkedSheets($sheets, [
            'name' => 'Evidências',
            'title' => 'Evidências por loja',
            'subtitle' => 'Índice integral dos ficheiros associados aos widgets de imagem',
            'headers' => ['Dashboard', 'Pergunta', 'Chave', 'Loja / Local', 'ID Loja', 'Data', 'Ficheiro', 'URL', 'Tipo', 'ID Media'],
            'rows' => $evidenceRows,
            'types' => ['text','text','text','text','integer','date','text','text','text','integer'],
            'widths' => [28,38,24,30,12,14,34,64,20,12],
        ]);
        foreach ($questionSheets as $questionSheet) $sheets[] = $questionSheet;
        return $sheets;
    }

    private static function dashboard_mobile_export_sheets(array $sheets): array {
        // Mobile Excel is deliberately a lighter operational workbook. The full
        // workbook remains available separately for desktop analysis.
        $preferred = ['Intelligence', 'Adaptive Intelligence', 'Predictive Intelligence', 'Contextual Actions', 'Alertas Lojas', 'Store Intelligence', 'Recomendações', 'Resumo', 'Rotas', 'Métricas', 'Sugestões Encomenda', 'Detalhe Analytics', 'Evidências'];
        $rowLimits = [
            'Intelligence' => 80,
            'Adaptive Intelligence' => 100,
            'Predictive Intelligence' => 100,
            'Contextual Actions' => 120,
            'Alertas Lojas' => 120,
            'Store Intelligence' => 120,
            'Resumo' => 120,
            'Rotas' => 350,
            'Métricas' => 350,
            'Sugestões Encomenda' => 500,
            'Detalhe Analytics' => 500,
            'Evidências' => 120,
        ];
        $selected = [];
        foreach ($preferred as $prefix) {
            foreach ($sheets as $sheet) {
                $name = (string)($sheet['name'] ?? '');
                if ($name !== $prefix && strpos($name, $prefix . ' ') !== 0) continue;
                $copy = $sheet;
                $rows = array_values((array)($copy['rows'] ?? []));
                $limit = (int)($rowLimits[$prefix] ?? 500);
                $wasLimited = count($rows) > $limit;
                if ($wasLimited) $rows = array_slice($rows, 0, $limit);
                $rows = array_map(static function($row){
                    return array_map(static function($value){
                        if (!is_string($value)) return $value;
                        $value = self::normalize_export_text($value);
                        if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > 320) return mb_substr($value, 0, 317, 'UTF-8') . '...';
                        if (strlen($value) > 600) return substr($value, 0, 597) . '...';
                        return $value;
                    }, array_values((array)$row));
                }, $rows);
                if ($wasLimited) {
                    $headers = array_values((array)($copy['headers'] ?? []));
                    $notice = array_fill(0, max(1, count($headers)), '');
                    $notice[0] = 'Nota';
                    if (isset($notice[1])) $notice[1] = 'Versão mobile limitada às primeiras ' . number_format_i18n($limit) . ' linhas. Usa Excel completo para todos os registos.';
                    $rows[] = $notice;
                }
                $copy['rows'] = $rows;
                $copy['widths'] = array_map(static function($width){ return max(10, min(30, (float)$width)); }, (array)($copy['widths'] ?? []));
                $copy['subtitle'] = trim((string)($copy['subtitle'] ?? '') . ' · versão leve para Excel mobile');
                $selected[] = $copy;
            }
        }
        return $selected ?: array_slice($sheets, 0, 1);
    }

    private static function dashboard_export_csv(array $data, array $filters, array $context): array {
        $headers = ['Secção','Dashboard','Pergunta / Métrica','Chave da pergunta','Tipo de pergunta','Data','Cliente','Projeto','Operativo','Rota','Loja / Local','Categoria','Produto','Comparação','Referência A','Frentes A','Quota A (%)','Referência B','Frentes B','Quota B (%)','Total frentes','Objetivo (%)','Desvio (pp)','Valor','Antes','Depois','Diferença','Evolução','Estado','Notas','URL'];
        $rows = [];
        $ri = is_array($data['report_intelligence'] ?? null) ? $data['report_intelligence'] : [];
        $scoreLabels = ['operational_health'=>'Operational Health','execution'=>'Execution Score','trend'=>'Trend Score','risk'=>'Risk Score','opportunity'=>'Opportunity Score','stock_risk'=>'Stock Risk','confidence'=>'Confidence Score'];
        foreach ((array)($ri['scores'] ?? []) as $scoreKey => $score) {
            if (!is_array($score)) continue;
            $row = array_fill(0, count($headers), '');
            $row[0] = 'Intelligence'; $row[2] = $scoreLabels[sanitize_key((string)$scoreKey)] ?? (string)$scoreKey; $row[4] = 'score';
            $row[6] = $context['client_name']; $row[7] = $context['project_name']; $row[8] = $context['user_name']; $row[10] = $context['location_name'];
            $row[23] = ($score['score'] ?? null) === null ? 'n.d.' : (string)$score['score'] . '/100';
            $row[28] = (string)($score['label'] ?? ''); $row[29] = (string)($score['explanation'] ?? '');
            $rows[] = $row;
        }
        $predictive = is_array($ri['predictive_intelligence'] ?? null) ? $ri['predictive_intelligence'] : [];
        foreach ((array)($predictive['forecasts'] ?? []) as $forecastKey => $forecast) {
            if (!is_array($forecast)) continue;
            $row = array_fill(0, count($headers), '');
            $row[0] = 'Predictive Intelligence';
            $row[1] = 'Predictive Intelligence Foundation';
            $row[2] = (string)($forecast['label'] ?? $forecastKey);
            $row[3] = (string)$forecastKey;
            $row[4] = 'predictive_forecast';
            $row[6] = $context['client_name']; $row[7] = $context['project_name']; $row[8] = $context['user_name']; $row[10] = $context['location_name'];
            $row[23] = (string)($forecast['forecast'] ?? '');
            $row[24] = (string)($forecast['current'] ?? '');
            $row[26] = (string)($forecast['delta'] ?? '');
            $row[27] = (string)($forecast['direction'] ?? 'stable');
            $row[28] = 'Confiança ' . number_format((float)($predictive['summary']['confidence'] ?? 0), 1, ',', '.') . '/100 | Horizonte ' . (string)($predictive['horizon']['label'] ?? 'Próxima janela equivalente');
            $row[29] = (string)($forecast['explanation'] ?? '') . ' | Projeção determinística, não probabilidade.';
            $rows[] = $row;
        }
        foreach ((array)($predictive['leading_signals'] ?? []) as $signal) {
            if (!is_array($signal)) continue;
            $row = array_fill(0, count($headers), '');
            $row[0] = 'Predictive Signal';
            $row[1] = 'Predictive Intelligence Foundation';
            $row[2] = (string)($signal['label'] ?? 'Sinal preditivo');
            $row[3] = (string)($signal['key'] ?? 'predictive_signal');
            $row[4] = 'predictive_signal';
            $row[6] = $context['client_name']; $row[7] = $context['project_name']; $row[8] = $context['user_name']; $row[10] = $context['location_name'];
            $row[23] = (string)($signal['value'] ?? '');
            $row[27] = (string)($signal['direction'] ?? 'stable');
            $row[28] = 'Horizonte ' . (string)($predictive['horizon']['label'] ?? 'Próxima janela equivalente');
            $row[29] = (string)($signal['detail'] ?? '');
            $rows[] = $row;
        }
        $contextual = is_array($ri['contextual_predictive_intelligence'] ?? null) ? $ri['contextual_predictive_intelligence'] : [];
        foreach ((array)($contextual['next_best_actions'] ?? []) as $action) {
            if (!is_array($action)) continue;
            $evidenceParts = [];
            foreach (array_slice((array)($action['evidence'] ?? []), 0, 4) as $evidence) {
                if (!is_array($evidence)) continue;
                $evidenceParts[] = ((string)($evidence['kind'] ?? 'observed') === 'projected' ? 'Projetado' : 'Observado') . ': ' . (string)($evidence['label'] ?? 'Sinal') . ' ' . (string)($evidence['value'] ?? '');
            }
            $row = array_fill(0, count($headers), '');
            $row[0] = 'Contextual Action';
            $row[1] = 'Contextual Predictive Intelligence';
            $row[2] = (string)($action['issue_label'] ?? 'Next Best Action');
            $row[3] = (string)($action['issue_type'] ?? 'contextual_action');
            $row[4] = 'contextual_next_best_action';
            $row[6] = $context['client_name']; $row[7] = $context['project_name']; $row[8] = $context['user_name'];
            $row[10] = (string)($action['location_name'] ?? 'PDV');
            $row[12] = (string)($action['product'] ?? '');
            $row[23] = (string)($action['rank_score'] ?? 0);
            $row[27] = strtoupper((string)($action['urgency'] ?? 'medium'));
            $row[28] = 'Confiança ' . number_format((float)($action['confidence_score'] ?? 0), 1, ',', '.') . '/100 | Risco futuro ' . number_format((float)($action['forecast_risk_score'] ?? 0), 1, ',', '.') . '/100 | ' . (string)($action['forecast_direction'] ?? 'stable');
            $row[29] = trim((string)($action['action'] ?? '') . ' | ' . implode(' | ', $evidenceParts) . ' | ' . (string)($action['reason'] ?? ''));
            $rows[] = $row;
        }
        foreach ((array)($ri['summary_metrics'] ?? []) as $metric) {
            if (!is_array($metric)) continue;
            $row = array_fill(0, count($headers), '');
            $row[0] = 'Widget inteligente'; $row[1] = (string)($metric['dashboard_title'] ?? 'Analytics operacional'); $row[2] = (string)($metric['metric_label'] ?? 'Métrica'); $row[3] = (string)($metric['metric_key'] ?? ''); $row[4] = 'widget_recomendado';
            $row[6] = $context['client_name']; $row[7] = $context['project_name']; $row[8] = $context['user_name']; $row[10] = $context['location_name'];
            $value = $metric['value'] ?? null; $row[23] = is_scalar($value) || $value === null ? (string)($value ?? 'Sem dados') : wp_json_encode($value);
            $row[28] = (string)($metric['topic'] ?? ''); $row[29] = trim((string)($metric['reason'] ?? '') . ((string)($metric['note'] ?? '') !== '' ? ' | ' . (string)$metric['note'] : ''));
            $rows[] = $row;
        }
        foreach ((array)($ri['store_intelligence']['rankings']['priority'] ?? []) as $store) {
            if (!is_array($store)) continue;
            $row=array_fill(0,count($headers),''); $row[0]='Store Intelligence'; $row[1]='Store Opportunity & Risk Engine'; $row[2]=(string)($store['location_name']??'PDV'); $row[4]='store_profile';
            $row[6]=$context['client_name'];$row[7]=$context['project_name'];$row[8]=$context['user_name'];$row[10]=(string)($store['location_name']??'PDV');
            $row[23]=(string)($store['adaptive_rank_score']??$store['priority_score']??0); $row[28]='Health '.($store['operational_health']??0).' | Risk '.($store['risk_score']??0).' | Opportunity '.($store['opportunity_score']??0).' | Confidence '.($store['adaptive_confidence_score']??$store['confidence_score']??0).' | Base priority '.($store['priority_score']??0).' | Future risk '.($store['forecast_risk_score']??'n.d.').' | Future priority '.($store['forecast_priority_score']??'n.d.').' | Forecast '.($store['forecast_direction']??'n.d.');
            $row[29]=trim((string)($store['reason']??'').' | '.(string)($store['suggested_action']??'').' | '.(string)($store['forecast_reason']??'') . (!empty($store['next_best_action']) ? ' | Next Best Action: ' . (string)($store['next_best_action']['issue_label'] ?? '') . ((string)($store['next_best_action']['product'] ?? '') !== '' ? ' · ' . (string)$store['next_best_action']['product'] : '') . ' · ' . (string)($store['next_best_action']['action'] ?? '') : '')); $rows[]=$row;
        }
        foreach ((array)($ri['store_alerts'] ?? []) as $alert) {
            if (!is_array($alert)) continue;
            $row = array_fill(0, count($headers), '');
            $row[0] = 'Alerta Loja'; $row[2] = (string)($alert['title'] ?? 'Alerta operacional'); $row[4] = 'store_alert';
            $row[6] = $context['client_name']; $row[7] = $context['project_name']; $row[8] = $context['user_name']; $row[10] = (string)($alert['location_name'] ?? 'PDV');
            $row[23] = (string)($alert['priority_score'] ?? 0); $row[28] = strtoupper((string)($alert['severity'] ?? 'medium'));
            $row[29] = trim((string)($alert['detail'] ?? '') . (!empty($alert['signals']) ? ' | Sinais: ' . implode(', ', (array)$alert['signals']) : ''));
            $rows[] = $row;
        }
        foreach ((array)($ri['recommendations'] ?? []) as $recommendation) {
            if (!is_array($recommendation)) continue;
            $row = array_fill(0, count($headers), '');
            $row[0] = 'Recomendação'; $row[1] = 'FieldFlow Intelligence Learning'; $row[2] = (string)($recommendation['title'] ?? 'Recomendação FieldFlow'); $row[3] = (string)($recommendation['metric_key'] ?? ''); $row[4] = 'intelligence_recommendation';
            $row[6] = $context['client_name']; $row[7] = $context['project_name']; $row[8] = $context['user_name']; $row[10] = (int)($recommendation['location_id'] ?? 0) > 0 ? 'Local #' . (int)$recommendation['location_id'] : $context['location_name'];
            $row[23] = (string)($recommendation['latest_value'] ?? '');
            $row[24] = (string)($recommendation['baseline_value'] ?? '');
            $row[26] = ($recommendation['outcome_score'] ?? null) === null ? '' : (string)$recommendation['outcome_score'];
            $row[28] = strtoupper((string)($recommendation['severity'] ?? 'medium')) . ' | ' . (string)($recommendation['status'] ?? 'active') . ' | confidence ' . number_format((float)($recommendation['adaptive_confidence_score'] ?? $recommendation['confidence_score'] ?? 0), 1, ',', '.') . '/100 | adaptive rank ' . number_format((float)($recommendation['adaptive_rank_score'] ?? 0), 1, ',', '.') . '/100';
            $row[29] = trim((string)($recommendation['suggested_action'] ?? '') . ' | ' . (string)($recommendation['rationale'] ?? '') . ((string)($recommendation['outcome_status'] ?? '') !== '' ? ' | Outcome: ' . (string)$recommendation['outcome_status'] : ''));
            $rows[] = $row;
        }
        $blankComparison = ['', '', '', '', '', '', '', '', '', ''];
        $summary = [
            'Rotas' => $data['total_routes'] ?? 0,
            'Rotas concluídas' => $data['completed_routes'] ?? 0,
            'Taxa rotas concluídas' => (string)($data['completion_rate'] ?? 0) . '%',
            'Paragens' => $data['total_stops'] ?? 0,
            'Paragens concluídas' => $data['done_stops'] ?? 0,
            'Execução' => (string)($data['done_rate'] ?? 0) . '%',
            'Média paragens por rota' => $data['avg_stops_per_route'] ?? 0,
            'Tempo médio de visita' => ($data['avg_stop_minutes'] ?? 0) . ' min',
        ];
        foreach ($summary as $metric => $value) {
            $rows[] = array_merge(['Resumo','',$metric,'','indicador','',$context['client_name'],$context['project_name'],$context['user_name'],'',$context['location_name'],(string)($filters['category'] ?? ''),(string)($filters['product'] ?? '')], $blankComparison, [$value,'','','','','','Período: ' . $context['period'],'']);
        }
        $fa = is_array($data['form_analytics'] ?? null) ? $data['form_analytics'] : [];
        foreach ((array)($fa['metrics'] ?? []) as $metric) {
            $dashboard = (string)($metric['dashboard_title'] ?? 'Analytics operacional');
            $question = trim((string)($metric['question_label'] ?? '')) ?: (string)($metric['metric_label'] ?? $metric['metric_key'] ?? '');
            $label = (string)($metric['metric_label'] ?? $metric['metric_key'] ?? '');
            $key = (string)($metric['question_key'] ?? $metric['metric_key'] ?? '');
            $type = sanitize_key((string)($metric['question_type'] ?? '')) ?: 'dado';
            $rows[] = array_merge(['Métrica',$dashboard,$question,$key,$type,'',$context['client_name'],$context['project_name'],$context['user_name'],'','','',''], $blankComparison, [$metric['value'] ?? 'Sem dados','','','','','', 'Métrica: ' . $label . ' | Registos: ' . (string)($metric['record_count'] ?? 0) . ' | ' . (string)($metric['note'] ?? ''),'']);
            foreach (array_slice((array)($metric['rows'] ?? []), 0, 10000) as $row) {
                $detailValue = array_key_exists('image_count', (array)$row) ? (string)($row['image_count'] ?? 0) : (string)($row['value'] ?? '');
                $detailNote = array_key_exists('image_count', (array)$row) ? 'Quantidade de imagens' : '';
                $rows[] = [
                    'Detalhe analytics',$dashboard,$question,$key,$type,(string)($row['date'] ?? ''),$context['client_name'],$context['project_name'],$context['user_name'],
                    (string)($row['route'] ?? ''),(string)($row['location'] ?? ''),(string)($row['category'] ?? ''),(string)($row['product'] ?? ''),
                    (string)($row['metric'] ?? $row['comparison'] ?? ''),(string)($row['pr_label'] ?? $row['reference_a'] ?? ''),(string)($row['pr_fronts'] ?? $row['fronts_a'] ?? ''),
                    (string)($row['pr_share'] ?? $row['share_a'] ?? ''),(string)($row['competitor_label'] ?? $row['reference_b'] ?? ''),(string)($row['competitor_fronts'] ?? $row['fronts_b'] ?? ''),
                    (string)($row['competitor_share'] ?? $row['share_b'] ?? ''),(string)($row['total_fronts'] ?? ''),(string)($row['target'] ?? $row['target_share'] ?? ''),
                    (string)($row['gap'] ?? $row['deviation'] ?? ''),$detailValue,(string)($row['before'] ?? ''),(string)($row['after'] ?? ''),(string)($row['difference'] ?? ''),
                    (string)($row['growth'] ?? ''),(string)($row['status'] ?? ''),$detailNote,''
                ];
            }
            foreach ((array)($metric['evidence_groups'] ?? []) as $evidenceGroup) {
                foreach ((array)($evidenceGroup['files'] ?? []) as $file) {
                    $fileDate = (string)($file['date'] ?? '');
                    if ($fileDate === '' && !empty($file['submitted_at'])) $fileDate = substr((string)$file['submitted_at'], 0, 10);
                    $rows[] = array_merge(['Evidência',$dashboard,$question,$key,$type,$fileDate,$context['client_name'],$context['project_name'],$context['user_name'],(string)($file['route_id'] ?? ''),(string)($evidenceGroup['location'] ?? ''),'',''], $blankComparison, [(string)($file['name'] ?? 'Evidência'),'','','','','','Ficheiro associado à resposta',(string)($file['url'] ?? '')]);
                }
            }
        }
        foreach ((array)($data['by_day'] ?? []) as $row) {
            $rows[] = array_merge(['Rota','','Execução da rota','','rota',(string)($row['date'] ?? ''),(string)($row['client_name'] ?? $context['client_name']),(string)($row['project_name'] ?? $context['project_name']),(string)($row['user_name'] ?? ''),(string)($row['route_id'] ?? ''),'','',''], $blankComparison, [(string)($row['done_rate'] ?? 0) . '%',(string)($row['stops'] ?? 0),(string)($row['stops_done'] ?? 0),'','',(string)($row['route_status'] ?? ''),'Paragens planeadas / concluídas nos campos Antes e Depois','']);
            $mobility = is_array($row['mobility_cost'] ?? null) ? (array)$row['mobility_cost'] : [];
            if ($mobility) {
                $mobilityNotes = [];
                if (!empty($mobility['profile_label'])) $mobilityNotes[] = 'Perfil: ' . (string)$mobility['profile_label'];
                if (!empty($mobility['profile_source'])) $mobilityNotes[] = 'Fonte: ' . (string)$mobility['profile_source'];
                $costsAvailable = !empty($mobility['costs_available']);
                if (!empty($mobility['enabled']) && $costsAvailable) {
                    $mobilityNotes[] = 'Energia: ' . number_format((float)($mobility['energy_cost_eur'] ?? 0), 2, ',', '.') . ' EUR';
                    $mobilityNotes[] = 'Litros: ' . number_format((float)($mobility['estimated_litres'] ?? 0), 3, ',', '.');
                    $mobilityNotes[] = 'kWh: ' . number_format((float)($mobility['estimated_kwh'] ?? 0), 3, ',', '.');
                    $mobilityNotes[] = 'Operacional: ' . number_format((float)($mobility['operational_cost_eur'] ?? 0), 2, ',', '.') . ' EUR';
                    $mobilityNotes[] = 'Portagens: ' . number_format((float)($mobility['toll_cost_eur'] ?? 0), 2, ',', '.') . ' EUR';
                    $mobilityNotes[] = 'Total: ' . number_format((float)($mobility['total_mobility_cost_eur'] ?? 0), 2, ',', '.') . ' EUR';
                } elseif (!empty($mobility['enabled'])) {
                    $mobilityNotes[] = 'Custos: POR CALCULAR, distância incompleta entre partida e chegada';
                }
                $mobilityRow = array_fill(0, count($headers), '');
                $mobilityRow[0] = 'Mobilidade';
                $mobilityRow[2] = 'Custo de deslocação';
                $mobilityRow[3] = 'mobility_cost';
                $mobilityRow[4] = 'mobility_cost';
                $mobilityRow[5] = (string)($row['date'] ?? '');
                $mobilityRow[6] = (string)($row['client_name'] ?? $context['client_name']);
                $mobilityRow[7] = (string)($row['project_name'] ?? $context['project_name']);
                $mobilityRow[8] = (string)($row['user_name'] ?? '');
                $mobilityRow[9] = (string)($row['route_id'] ?? '');
                $mobilityRow[23] = $costsAvailable ? (string)($mobility['total_mobility_cost_eur'] ?? 0) : '';
                $mobilityRow[28] = !empty($mobility['enabled']) ? ($costsAvailable ? 'CALCULADO' : 'POR CALCULAR, DISTÂNCIA INCOMPLETA') : 'SEM PERFIL DE ENERGIA';
                $mobilityRow[29] = implode(' | ', $mobilityNotes);
                $rows[] = $mobilityRow;
            }
        }
        foreach (self::orderSuggestionExportRows($filters) as $orderRow) {
            if (!is_array($orderRow)) continue;
            $notes = [];
            if ((string)($orderRow[8] ?? '') !== '') $notes[] = 'Ref: ' . (string)$orderRow[8];
            if ((string)($orderRow[10] ?? '') !== '') $notes[] = 'Tipo: ' . (string)$orderRow[10];
            if ((string)($orderRow[11] ?? '') !== '') $notes[] = 'Capacidade: ' . (string)$orderRow[11];
            if ((string)($orderRow[12] ?? '') !== '') $notes[] = 'Unidades/caixa: ' . (string)$orderRow[12];
            $notes[] = 'Unidades totais: ' . (string)($orderRow[14] ?? 0);
            if ((string)($orderRow[15] ?? '') !== '') $notes[] = 'Observações: ' . (string)$orderRow[15];
            $notes[] = 'Submissão #' . (string)($orderRow[0] ?? '');
            $row = array_fill(0, count($headers), '');
            $row[0] = 'Sugestão encomenda';
            $row[1] = 'Formulários';
            $row[2] = (string)($orderRow[6] ?? 'Sugestão de encomenda');
            $row[3] = 'order_suggestion';
            $row[4] = 'order_suggestion';
            $row[5] = (string)($orderRow[1] ?? '');
            $row[6] = (string)($orderRow[2] ?? $context['client_name']);
            $row[7] = (string)($orderRow[3] ?? $context['project_name']);
            $row[8] = (string)($orderRow[4] ?? $context['user_name']);
            $row[10] = (string)($orderRow[5] ?? '');
            $row[11] = (string)($orderRow[7] ?? '');
            $row[12] = (string)($orderRow[9] ?? '');
            $row[23] = (string)($orderRow[13] ?? 0);
            $row[24] = (string)($orderRow[12] ?? '');
            $row[25] = (string)($orderRow[14] ?? 0);
            $row[28] = ((float)($orderRow[13] ?? 0) > 0 || (float)($orderRow[14] ?? 0) > 0) ? 'SUGERIDA' : '';
            $row[29] = implode(' | ', $notes);
            $rows[] = $row;
        }
        return [$headers, $rows];
    }

    private static function create_dashboard_excel_xml_file(string $filename, array $sheets, array $context): string {
        $tmpBase = wp_tempnam($filename);
        if (!$tmpBase) throw new \RuntimeException('Não foi possível preparar o ficheiro Excel.');
        $target = trailingslashit(dirname($tmpBase)) . wp_unique_filename(dirname($tmpBase), sanitize_file_name($filename));
        @unlink($target);
        @rename($tmpBase, $target);

        $handle = @fopen($target, 'wb');
        if (!$handle) throw new \RuntimeException('Não foi possível criar o ficheiro Excel.');
        $write = static function (string $chunk) use ($handle): void {
            if (fwrite($handle, $chunk) === false) throw new \RuntimeException('Falha ao escrever o ficheiro Excel.');
        };

        try {
            $write("\xEF\xBB\xBF");
            $write('<?xml version="1.0" encoding="UTF-8"?>' . "\n");
            $write('<?mso-application progid="Excel.Sheet"?>' . "\n");
            $write('<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">');
            $write('<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office"><Author>The Wild Theory</Author><Company>FieldFlow</Company><Title>Relatório Premium de Analytics</Title><Created>' . gmdate('Y-m-d\TH:i:s\Z') . '</Created><Description>' . self::xml_escape((string)($context['client_name'] ?? '') . ' | ' . (string)($context['project_name'] ?? '') . ' | ' . (string)($context['period'] ?? '')) . '</Description></DocumentProperties>');
            $write('<Styles>');
            $write('<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Top" ss:WrapText="1"/><Font ss:FontName="Aptos" ss:Size="10"/></Style>');
            $write('<Style ss:ID="Title"><Alignment ss:Vertical="Center"/><Font ss:FontName="Aptos Display" ss:Size="18" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0F172A" ss:Pattern="Solid"/></Style>');
            $write('<Style ss:ID="Subtitle"><Alignment ss:Vertical="Center"/><Font ss:FontName="Aptos" ss:Size="10" ss:Color="#475569"/><Interior ss:Color="#F8FAFC" ss:Pattern="Solid"/></Style>');
            $write('<Style ss:ID="Header"><Alignment ss:Vertical="Center" ss:WrapText="1"/><Font ss:FontName="Aptos" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1D4ED8" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders></Style>');
            $write('<Style ss:ID="Data"><Alignment ss:Vertical="Top" ss:WrapText="1"/><Font ss:FontName="Aptos" ss:Size="10"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>');
            $write('<Style ss:ID="Integer"><Alignment ss:Vertical="Top"/><NumberFormat ss:Format="0"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>');
            $write('<Style ss:ID="Number"><Alignment ss:Vertical="Top"/><NumberFormat ss:Format="0.00"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>');
            $write('<Style ss:ID="Percent"><Alignment ss:Vertical="Top"/><NumberFormat ss:Format="0.0%"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>');
            $write('</Styles>');

            $usedNames = [];
            $dataCells = 0;
            foreach (array_values($sheets) as $sheetIndex => $sheet) {
                $headers = array_values((array)($sheet['headers'] ?? []));
                $rows = array_values((array)($sheet['rows'] ?? []));
                $types = array_values((array)($sheet['types'] ?? []));
                $widths = array_values((array)($sheet['widths'] ?? []));
                $columnCount = max(1, count($headers));
                $name = self::excel_unique_sheet_name((string)($sheet['name'] ?? ('Folha ' . ($sheetIndex + 1))), $usedNames);

                $write('<Worksheet ss:Name="' . self::xml_escape($name) . '"><Table ss:ExpandedColumnCount="' . $columnCount . '" ss:ExpandedRowCount="' . (count($rows) + 4) . '" x:FullColumns="1" x:FullRows="1">');
                for ($i = 0; $i < $columnCount; $i++) {
                    $width = isset($widths[$i]) ? max(55, min(360, (float)$widths[$i] * 7)) : 125;
                    $write('<Column ss:AutoFitWidth="0" ss:Width="' . number_format($width, 1, '.', '') . '"/>');
                }
                $write('<Row ss:StyleID="Title" ss:Height="30"><Cell ss:MergeAcross="' . max(0, $columnCount - 1) . '"><Data ss:Type="String">' . self::xml_escape((string)($sheet['title'] ?? $name)) . '</Data></Cell></Row>');
                $write('<Row ss:StyleID="Subtitle" ss:Height="22"><Cell ss:MergeAcross="' . max(0, $columnCount - 1) . '"><Data ss:Type="String">' . self::xml_escape((string)($sheet['subtitle'] ?? '')) . '</Data></Cell></Row>');
                $write('<Row ss:StyleID="Subtitle" ss:Height="22"><Cell ss:MergeAcross="' . max(0, $columnCount - 1) . '"><Data ss:Type="String">Gerado por FieldFlow Pro em ' . self::xml_escape(wp_date('d/m/Y H:i')) . '</Data></Cell></Row>');
                $write('<Row ss:StyleID="Header" ss:Height="30">');
                foreach ($headers as $header) $write('<Cell><Data ss:Type="String">' . self::xml_escape((string)$header) . '</Data></Cell>');
                $write('</Row>');

                foreach ($rows as $row) {
                    $values = array_values((array)$row);
                    $write('<Row>');
                    for ($i = 0; $i < $columnCount; $i++) {
                        $value = $values[$i] ?? '';
                        $type = $types[$i] ?? 'auto';
                        $style = 'Data'; $excelType = 'String'; $out = self::normalize_export_text((string)$value);
                        if ($value !== '' && ($type === 'integer' || ($type === 'auto' && is_int($value)))) { $style = 'Integer'; $excelType = 'Number'; $out = (string)(int)$value; }
                        elseif ($value !== '' && $type === 'percent' && is_numeric($value)) { $style = 'Percent'; $excelType = 'Number'; $out = number_format((float)$value, 6, '.', ''); }
                        elseif ($value !== '' && ($type === 'number' || ($type === 'auto' && is_numeric($value)))) { $style = 'Number'; $excelType = 'Number'; $out = number_format((float)$value, 6, '.', ''); }
                        if ($out !== '') $dataCells++;
                        $write('<Cell ss:StyleID="' . $style . '"><Data ss:Type="' . $excelType . '">' . self::xml_escape($out) . '</Data></Cell>');
                    }
                    $write('</Row>');
                }
                $write('</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>4</SplitHorizontal><TopRowBottomPane>4</TopRowBottomPane><ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios></WorksheetOptions><AutoFilter x:Range="R4C1:R' . max(4, count($rows) + 4) . 'C' . $columnCount . '" xmlns="urn:schemas-microsoft-com:office:excel"/></Worksheet>');
            }
            $write('</Workbook>');
            fclose($handle);
            $handle = null;
            if ($dataCells < 1 || !is_file($target) || filesize($target) < 1000) throw new \RuntimeException('O Excel não recebeu dados válidos da Analytics.');
            $sample = file_get_contents($target, false, null, 0, min(8192, filesize($target)));
            if ($sample === false || strpos($sample, '<Workbook') === false || strpos($sample, '<Worksheet') === false) throw new \RuntimeException('A validação do ficheiro Excel falhou.');
            return $target;
        } catch (\Throwable $e) {
            if (is_resource($handle)) fclose($handle);
            @unlink($target);
            throw $e;
        }
    }

    private static function create_dashboard_xlsx_file(string $filename, array $sheets, array $context, bool $mobileSafe = false): string {
        $tmpBase = wp_tempnam($filename);
        if (!$tmpBase) throw new \RuntimeException('Não foi possível preparar o ficheiro Excel.');
        $archive = $tmpBase . '.zip';
        $tmp = trailingslashit(dirname($tmpBase)) . wp_unique_filename(dirname($tmpBase), sanitize_file_name($filename));
        @unlink($archive);
        @unlink($tmp);
        @unlink($tmpBase);

        $zip = null;
        $phar = null;
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('Não foi possível criar o ficheiro Excel.');
            $add = static function (string $path, string $content) use ($zip): void {
                if (!$zip->addFromString($path, $content)) throw new \RuntimeException('Falha ao escrever o pacote Excel.');
                // ZipArchive stores addFromString entries without compression on
                // some hosting builds. Excel mobile then receives workbooks with
                // tens of megabytes of raw XML and can remain loading forever.
                if (method_exists($zip, 'setCompressionName')) {
                    $zip->setCompressionName($path, \ZipArchive::CM_DEFLATE);
                }
            };
        } elseif (class_exists('PharData')) {
            try {
                $phar = new \PharData($archive, 0, null, \Phar::ZIP);
            } catch (\Throwable $e) {
                throw new \RuntimeException('Não foi possível criar o ficheiro Excel.');
            }
            $add = static function (string $path, string $content) use ($phar): void { $phar->addFromString($path, $content); };
        } else {
            @unlink($archive);
            throw new \RuntimeException('O servidor não suporta a criação de ficheiros XLSX.');
        }

        $sheetRels = [];
        $usedNames = [];
        $contentOverrides = '';
        $workbookRels = '';
        $sheetCount = count($sheets);
        foreach (array_values($sheets) as $index => $sheet) {
            $sheetId = $index + 1;
            $xml = self::xlsx_sheet_xml($sheet, $mobileSafe);
            $add('xl/worksheets/sheet' . $sheetId . '.xml', $xml);
            unset($xml);
            $sheetName = self::excel_unique_sheet_name((string)($sheet['name'] ?? ('Folha ' . $sheetId)), $usedNames);
            $sheetRels[] = '<sheet name="' . self::xlsx_escape($sheetName) . '" sheetId="' . $sheetId . '" r:id="rId' . $sheetId . '"/>';
            $contentOverrides .= '<Override PartName="/xl/worksheets/sheet' . $sheetId . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $workbookRels .= '<Relationship Id="rId' . $sheetId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetId . '.xml"/>';
        }
        $stylesRid = $sheetCount + 1;
        $workbookRels .= '<Relationship Id="rId' . $stylesRid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $add('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>' . $contentOverrides . '</Types>');
        $add('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>');
        $add('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="15000"/></bookViews><sheets>' . implode('', $sheetRels) . '</sheets><calcPr calcId="191029"/></workbook>');
        $add('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $workbookRels . '</Relationships>');
        $add('xl/styles.xml', self::xlsx_styles_xml());
        $add('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Relatório semanal de Analytics</dc:title><dc:creator>The Wild Theory</dc:creator><cp:lastModifiedBy>FieldFlow Pro</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created><dc:description>' . self::xlsx_escape($context['client_name'] . ' | ' . $context['project_name'] . ' | ' . $context['period']) . '</dc:description></cp:coreProperties>');
        // CT_Properties is sequence-sensitive in Microsoft Office. Company
        // must precede Application, otherwise Excel desktop may reject an
        // otherwise well-formed XLSX as damaged.
        $add('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Company>The Wild Theory</Company><Application>FieldFlow Pro</Application><AppVersion>2.2</AppVersion></Properties>');
        if ($zip instanceof \ZipArchive) $zip->close();
        if ($phar instanceof \PharData) {
            try {
                if (!\Phar::canCompress(\Phar::GZ)) throw new \RuntimeException('A extensão zlib não está disponível para comprimir o Excel.');
                $phar->compressFiles(\Phar::GZ);
            } catch (\Throwable $e) {
                unset($phar);
                @unlink($archive);
                throw new \RuntimeException('Não foi possível comprimir o ficheiro Excel.');
            }
            unset($phar);
        }
        if (!is_file($archive) || filesize($archive) < 1000) throw new \RuntimeException('O ficheiro Excel ficou incompleto.');
        if (!@rename($archive, $tmp)) { @copy($archive, $tmp); @unlink($archive); }
        if (!is_file($tmp) || filesize($tmp) < 1000) throw new \RuntimeException('Não foi possível finalizar o ficheiro Excel.');
        if (class_exists('ZipArchive')) {
            $check = new \ZipArchive();
            if ($check->open($tmp) !== true || $check->locateName('xl/workbook.xml') === false || $check->locateName('xl/worksheets/sheet1.xml') === false) {
                if ($check instanceof \ZipArchive) $check->close();
                @unlink($tmp);
                throw new \RuntimeException('A validação do ficheiro Excel falhou.');
            }
            $rawBytes = 0;
            $compressedBytes = 0;
            for ($i = 0; $i < $check->numFiles; $i++) {
                $stat = $check->statIndex($i);
                if (!is_array($stat)) continue;
                $rawBytes += (int)($stat['size'] ?? 0);
                $compressedBytes += (int)($stat['comp_size'] ?? 0);
            }
            $check->close();
            if ($rawBytes > 1048576 && $compressedBytes >= (int)($rawBytes * 0.92)) {
                @unlink($tmp);
                throw new \RuntimeException('O pacote Excel não foi comprimido corretamente.');
            }
        } elseif (class_exists('PharData')) {
            try {
                $check = new \PharData($tmp);
                if (!isset($check['xl/workbook.xml']) || !isset($check['xl/worksheets/sheet1.xml'])) {
                    throw new \RuntimeException('Estrutura OOXML incompleta.');
                }
                $rawBytes = 0;
                $compressedBytes = 0;
                foreach (new \RecursiveIteratorIterator($check) as $entry) {
                    if (!$entry instanceof \PharFileInfo) continue;
                    $rawBytes += (int)$entry->getSize();
                    $compressedBytes += (int)$entry->getCompressedSize();
                }
                unset($check);
                if ($rawBytes > 1048576 && $compressedBytes >= (int)($rawBytes * 0.92)) {
                    throw new \RuntimeException('Pacote OOXML sem compressão efetiva.');
                }
            } catch (\Throwable $e) {
                @unlink($tmp);
                throw new \RuntimeException('A validação comprimida do ficheiro Excel falhou.');
            }
        }
        self::assert_xlsx_package_integrity($tmp);
        return $tmp;
    }

    private static function output_dashboard_xlsx(string $filename, array $sheets, array $context): void {
        self::streamDashboardReportFile(['path'=>self::create_dashboard_xlsx_file($filename, $sheets, $context), 'filename'=>$filename, 'mime'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }


    private static function xlsx_sheet_xml(array $sheet, bool $mobileSafe = false): string {
        $headers = array_values((array)($sheet['headers'] ?? []));
        $rows = array_values((array)($sheet['rows'] ?? []));
        $types = array_values((array)($sheet['types'] ?? []));
        $widths = array_values((array)($sheet['widths'] ?? []));
        $count = max(1, count($headers));
        $lastCol = self::xlsx_column_name($count);
        $rowNo = 1;
        $dimensionLastRow = max(4, count($rows) + 4);
        if ($mobileSafe) {
            // Excel Android is considerably stricter than desktop readers. The
            // mobile workbook deliberately avoids panes, merged title rows,
            // filters and print settings, leaving a compact canonical sheet.
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:' . $lastCol . $dimensionLastRow . '"/><sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="18"/>';
        } else {
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:' . $lastCol . $dimensionLastRow . '"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/>';
        }
        $xml .= '<cols>';
        for ($i = 0; $i < $count; $i++) {
            $width = isset($widths[$i]) ? (float)$widths[$i] : 18;
            $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . max(8, min(60, $width)) . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        $xml .= self::xlsx_row($rowNo++, [(string)($sheet['title'] ?? $sheet['name'] ?? 'Analytics')], ['title'], $count);
        $xml .= self::xlsx_row($rowNo++, [(string)($sheet['subtitle'] ?? '')], ['subtitle'], $count);
        $xml .= self::xlsx_row($rowNo++, ['Gerado por FieldFlow Pro em ' . wp_date('d/m/Y H:i')], ['subtitle'], $count);
        $xml .= self::xlsx_row($rowNo++, $headers, array_fill(0, $count, 'header'), $count);
        foreach ($rows as $row) {
            $xml .= self::xlsx_row($rowNo++, array_values((array)$row), $types, $count);
        }
        if ($mobileSafe) {
            $xml .= '</sheetData></worksheet>';
        } else {
            $xml .= '</sheetData>';
            // SpreadsheetML worksheet children have a strict schema order.
            // autoFilter must be written before mergeCells. Excel desktop is
            // less tolerant than generic ZIP/XML readers and reports the whole
            // workbook as damaged when these elements are reversed.
            $xml .= '<autoFilter ref="A4:' . $lastCol . max(4, $rowNo - 1) . '"/>';
            if ($count > 1) $xml .= '<mergeCells count="3"><mergeCell ref="A1:' . $lastCol . '1"/><mergeCell ref="A2:' . $lastCol . '2"/><mergeCell ref="A3:' . $lastCol . '3"/></mergeCells>';
            $xml .= '<pageMargins left="0.35" right="0.35" top="0.55" bottom="0.55" header="0.2" footer="0.2"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
        }
        return $xml;
    }

    private static function xlsx_row(int $rowNo, array $values, array $types, int $columnCount): string {
        $height = $rowNo === 1 ? 30 : ($rowNo <= 3 ? 21 : 20);
        $xml = '<row r="' . $rowNo . '" ht="' . $height . '" customHeight="1">';
        for ($i = 0; $i < $columnCount; $i++) {
            $ref = self::xlsx_column_name($i + 1) . $rowNo;
            $value = $values[$i] ?? '';
            $type = $types[$i] ?? 'auto';
            if ($rowNo <= 3 && $i > 0) continue;
            if ($rowNo <= 3 && $i === 0) {
                $mergeStyle = $rowNo === 1 ? 1 : 2;
                $xml .= '<c r="' . $ref . '" t="inlineStr" s="' . $mergeStyle . '"><is><t xml:space="preserve">' . self::xlsx_escape((string)$value) . '</t></is></c>';
                continue;
            }
            $finiteNumber = is_numeric($value) && is_finite((float)$value);
            if ($type === 'header') {
                $xml .= '<c r="' . $ref . '" t="inlineStr" s="3"><is><t xml:space="preserve">' . self::xlsx_escape((string)$value) . '</t></is></c>';
            } elseif ($type === 'integer' && $finiteNumber) {
                $xml .= '<c r="' . $ref . '" s="5"><v>' . (int)$value . '</v></c>';
            } elseif ($type === 'percent' && $finiteNumber) {
                $xml .= '<c r="' . $ref . '" s="6"><v>' . number_format((float)$value, 6, '.', '') . '</v></c>';
            } elseif (($type === 'number' || ($type === 'auto' && $finiteNumber)) && $value !== '' && $finiteNumber) {
                $xml .= '<c r="' . $ref . '" s="4"><v>' . number_format((float)$value, 6, '.', '') . '</v></c>';
            } else {
                $style = $type === 'date' ? 7 : 4;
                $xml .= '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . self::xlsx_escape((string)$value) . '</t></is></c>';
            }
        }
        return $xml . '</row>';
    }

    private static function xlsx_column_name(int $index): string {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }
        return $name;
    }

    private static function xlsx_escape(string $value): string {
        $value = self::xlsx_safe_text($value);
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function xlsx_safe_text(string $value): string {
        $value = self::normalize_export_text($value);
        if (function_exists('wp_check_invalid_utf8')) {
            $value = (string) wp_check_invalid_utf8($value, true);
        } elseif (!preg_match('//u', $value)) {
            if (function_exists('mb_convert_encoding')) {
                $value = (string) @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            } elseif (function_exists('iconv')) {
                $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
                $value = $converted === false ? '' : (string)$converted;
            } else {
                $value = '';
            }
        }
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?: '';
        $value = preg_replace('/[\x{FFFE}\x{FFFF}]/u', '', $value) ?: '';

        // Excel limits a cell text value to 32,767 characters.
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value, 'UTF-8') > 32767) $value = mb_substr($value, 0, 32764, 'UTF-8') . '...';
        } elseif (strlen($value) > 32767) {
            $value = substr($value, 0, 32764) . '...';
        }
        return $value;
    }

    private static function xlsx_styles_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="2"><numFmt numFmtId="165" formatCode="0.0%"/><numFmt numFmtId="166" formatCode="dd/mm/yyyy"/></numFmts><fonts count="4"><font><sz val="10"/><name val="Aptos"/><family val="2"/></font><font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Aptos Display"/></font><font><sz val="10"/><color rgb="FF64748B"/><name val="Aptos"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font></fonts><fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1D4ED8"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right><top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="8"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment wrapText="1" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment wrapText="1" vertical="top"/></xf><xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment vertical="top"/></xf><xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment vertical="top"/></xf><xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment vertical="top"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function build_dashboard_pdf_binary(array $data, array $filters, array $context = []): string {
        if (!$context) $context = self::dashboard_export_context($filters);
        $prev = is_array($data['previous_summary'] ?? null) ? $data['previous_summary'] : [];
        $rank = is_array($data['rankings'] ?? null) ? $data['rankings'] : [];
        $done = (float)($data['done_rate'] ?? 0);
        $delta = round($done - (float)($prev['done_rate'] ?? 0), 1);
        $health = $done >= 90 ? 'Execução sólida' : ($done >= 75 ? 'Execução sob atenção' : 'Execução prioritária');
        $deltaText = ($delta > 0 ? '+' : '') . $delta . ' p.p. face ao período anterior';

        $blocks = [];
        $blocks[] = ['type' => 'section', 'lines' => ['LEITURA EXECUTIVA']];
        $blocks[] = ['type' => 'metric', 'lines' => [
            $health . ' | ' . number_format($done, 1, ',', '.') . '% de execução',
            $deltaText . ' | ' . (int)($data['done_stops'] ?? 0) . ' de ' . (int)($data['total_stops'] ?? 0) . ' paragens concluídas.',
            'Top campanha: ' . (string)($rank['top_project'] ?? 'Sem dados') . ' | Top operativo: ' . (string)($rank['top_owner'] ?? 'Sem dados'),
            'Tempo médio de visita: ' . ((float)($data['avg_stop_minutes'] ?? 0) > 0 ? number_format((float)$data['avg_stop_minutes'], 1, ',', '.') . ' min' : 'n.d.'),
        ]];
        $ri = is_array($data['report_intelligence'] ?? null) ? $data['report_intelligence'] : [];
        if ($ri) {
            $scoreLabels = ['operational_health'=>'Operational Health','execution'=>'Execution Score','trend'=>'Trend Score','risk'=>'Risk Score','opportunity'=>'Opportunity Score','stock_risk'=>'Stock Risk','confidence'=>'Confidence Score'];
            $scoreLines = [];
            foreach ((array)($ri['scores'] ?? []) as $scoreKey => $score) {
                if (!is_array($score)) continue;
                $value = $score['score'] ?? null;
                $scoreLines[] = ($scoreLabels[sanitize_key((string)$scoreKey)] ?? (string)$scoreKey) . ': ' . ($value === null ? 'n.d.' : number_format((float)$value, 1, ',', '.') . '/100') . ' | ' . (string)($score['label'] ?? '');
            }
            if ($scoreLines) {
                $blocks[] = ['type'=>'section','lines'=>['FIELD INTELLIGENCE']];
                $blocks[] = ['type'=>'metric','lines'=>$scoreLines];
            }
            $smartLines = [];
            foreach (array_slice((array)($ri['summary_metrics'] ?? []), 0, 8) as $metric) {
                if (!is_array($metric)) continue;
                $value = $metric['value'] ?? null;
                if (is_array($value) || is_object($value)) $value = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $smartLines[] = (string)($metric['metric_label'] ?? 'Métrica') . ': ' . (($value === null || $value === '') ? 'Sem dados' : (string)$value) . ((string)($metric['note'] ?? '') !== '' ? ' | ' . (string)$metric['note'] : '');
            }
            if ($smartLines) {
                $blocks[] = ['type'=>'section','lines'=>['WIDGETS PRIORITÁRIOS']];
                $blocks[] = ['type'=>'metric','lines'=>$smartLines];
            }
            $storeLines=[];
            foreach (array_slice((array)($ri['store_intelligence']['rankings']['priority'] ?? []),0,10) as $store) {
                if(!is_array($store))continue;
                $stock=($store['stock_risk']??null)===null?'stock n.d.':'stock '.number_format((float)$store['stock_risk'],1,',','.').'/100';
                $trend=($store['trend_delta_pp']??null)===null?'tendência n.d.':'tendência '.((float)$store['trend_delta_pp']>0?'+':'').number_format((float)$store['trend_delta_pp'],1,',','.').' pp';
                $adaptivePriority = (float)($store['adaptive_rank_score'] ?? $store['priority_score'] ?? 0);
                $basePriority = (float)($store['base_priority_score'] ?? $store['priority_score'] ?? 0);
                $priorityText = 'prioridade assistida ' . number_format($adaptivePriority,1,',','.') . '/100';
                if (abs($adaptivePriority - $basePriority) >= 0.05) $priorityText .= ' (base ' . number_format($basePriority,1,',','.') . ')';
                $futureRisk = ($store['forecast_risk_score'] ?? null) === null ? 'risco futuro n.d.' : 'risco futuro ' . number_format((float)$store['forecast_risk_score'],1,',','.') . '/100';
                $futurePriority = ($store['forecast_priority_score'] ?? null) === null ? 'prioridade futura n.d.' : 'prioridade futura ' . number_format((float)$store['forecast_priority_score'],1,',','.') . '/100';
                $storeLines[]=(string)($store['location_name']??'PDV').' | '.$priorityText.' | health '.number_format((float)($store['operational_health']??0),1,',','.').' | risco '.number_format((float)($store['risk_score']??0),1,',','.').' | oportunidade '.number_format((float)($store['opportunity_score']??0),1,',','.').' | '.$futureRisk.' | '.$futurePriority.' | '.$stock.' | '.$trend;
            }
            if($storeLines){$blocks[]=['type'=>'section','lines'=>['STORE INTELLIGENCE']];$blocks[]=['type'=>'metric','lines'=>$storeLines];}
            $adaptive = is_array($ri['adaptive_intelligence'] ?? null) ? $ri['adaptive_intelligence'] : [];
            if ($adaptive && !empty($adaptive['available'])) {
                $adaptiveLines = [];
                $summary = is_array($adaptive['summary'] ?? null) ? $adaptive['summary'] : [];
                $adaptiveLines[] = 'Padrões: ' . (int)($summary['patterns'] ?? 0)
                    . ' | eficácia média ' . number_format((float)($summary['effectiveness_avg'] ?? 50),1,',','.') . '/100'
                    . ' | relevância humana ' . number_format((float)($summary['human_relevance_avg'] ?? 50),1,',','.') . '/100';
                foreach (array_slice((array)($adaptive['next_best_actions'] ?? []), 0, 6) as $action) {
                    if (!is_array($action)) continue;
                    $adaptiveLines[] = 'Next Best Action | ' . (string)($action['title'] ?? 'Recomendação FieldFlow')
                        . ' | ' . (string)($action['action'] ?? '')
                        . ' | prioridade assistida ' . number_format((float)($action['adaptive_rank_score'] ?? 0),1,',','.') . '/100'
                        . ' | confiança ' . number_format((float)($action['adaptive_confidence_score'] ?? 0),1,',','.') . '/100';
                }
                if ($adaptiveLines) {
                    $blocks[] = ['type'=>'section','lines'=>['ADAPTIVE INTELLIGENCE']];
                    $blocks[] = ['type'=>'metric','lines'=>$adaptiveLines];
                }
            }
            $predictive = is_array($ri['predictive_intelligence'] ?? null) ? $ri['predictive_intelligence'] : [];
            if ($predictive && !empty($predictive['available'])) {
                $predictiveLines = [];
                $ps = is_array($predictive['summary'] ?? null) ? $predictive['summary'] : [];
                $horizon = is_array($predictive['horizon'] ?? null) ? $predictive['horizon'] : [];
                $predictiveLines[] = 'Horizonte: ' . (string)($horizon['label'] ?? 'Próxima janela equivalente')
                    . ' | estado ' . (string)($predictive['state'] ?? 'stable')
                    . ' | confiança ' . number_format((float)($ps['confidence'] ?? 0),1,',','.') . '/100';
                $predictiveLines[] = 'Execução projetada ' . number_format((float)($ps['projected_execution'] ?? 0),1,',','.') . '%'
                    . ' | risco projetado ' . number_format((float)($ps['forecast_risk'] ?? 0),1,',','.') . '/100'
                    . ' | oportunidade projetada ' . number_format((float)($ps['forecast_opportunity'] ?? 0),1,',','.') . '/100';
                foreach (array_slice((array)($predictive['leading_signals'] ?? []), 0, 6) as $signal) {
                    if (!is_array($signal)) continue;
                    $predictiveLines[] = (string)($signal['label'] ?? 'Sinal') . ': ' . (string)($signal['value'] ?? '') . ' | ' . (string)($signal['detail'] ?? '');
                }
                $predictiveLines[] = 'Nota: projeção determinística de sinais observados. Não representa probabilidade nem prova causalidade.';
                $blocks[] = ['type'=>'section','lines'=>['PREDICTIVE INTELLIGENCE']];
                $blocks[] = ['type'=>'metric','lines'=>$predictiveLines];
            }
            $contextual = is_array($ri['contextual_predictive_intelligence'] ?? null) ? $ri['contextual_predictive_intelligence'] : [];
            if ($contextual && !empty($contextual['available'])) {
                $contextualLines = [];
                foreach (array_slice((array)($contextual['next_best_actions'] ?? []), 0, 8) as $action) {
                    if (!is_array($action)) continue;
                    $label = (string)($action['location_name'] ?? 'PDV');
                    if ((string)($action['product'] ?? '') !== '') $label .= ' | ' . (string)$action['product'];
                    $contextualLines[] = $label
                        . ' | ' . (string)($action['issue_label'] ?? 'Ação recomendada')
                        . ' | prioridade ' . number_format((float)($action['rank_score'] ?? 0),1,',','.') . '/100'
                        . ' | confiança ' . number_format((float)($action['confidence_score'] ?? 0),1,',','.') . '/100'
                        . ' | risco futuro ' . number_format((float)($action['forecast_risk_score'] ?? 0),1,',','.') . '/100'
                        . ' | ' . (string)($action['action'] ?? '');
                }
                if ($contextualLines) {
                    $contextualLines[] = 'Nota: Next Best Actions são recomendações assistidas. Sinais observados e projetados permanecem separados e nenhuma ação é executada automaticamente.';
                    $blocks[] = ['type'=>'section','lines'=>['CONTEXTUAL NEXT BEST ACTIONS']];
                    $blocks[] = ['type'=>'metric','lines'=>$contextualLines];
                }
            }
            $alertLines = [];
            foreach (array_slice((array)($ri['store_alerts'] ?? []), 0, 10) as $alert) {
                if (!is_array($alert)) continue;
                $rate = $alert['done_rate'] ?? null;
                $alertLines[] = strtoupper((string)($alert['severity'] ?? 'medium')) . ' | ' . (string)($alert['location_name'] ?? 'PDV') . ($rate === null ? '' : ' | execução ' . number_format((float)$rate, 1, ',', '.') . '%') . ' | ' . (string)($alert['detail'] ?? '');
            }
            if ($alertLines) {
                $blocks[] = ['type'=>'section','lines'=>['ALERTAS POR LOJA']];
                $blocks[] = ['type'=>'metric','lines'=>$alertLines];
            }
            $recommendationLines = [];
            foreach (array_slice((array)($ri['recommendations'] ?? []), 0, 8) as $recommendation) {
                if (!is_array($recommendation)) continue;
                $recommendationLines[] = strtoupper((string)($recommendation['severity'] ?? 'medium')) . ' | ' . (string)($recommendation['title'] ?? 'Recomendação FieldFlow') . ' | ' . (string)($recommendation['suggested_action'] ?? '') . ' | confiança ' . number_format((float)($recommendation['adaptive_confidence_score'] ?? $recommendation['confidence_score'] ?? 0), 1, ',', '.') . '/100';
            }
            if ($recommendationLines) {
                $blocks[] = ['type'=>'section','lines'=>['AÇÕES RECOMENDADAS']];
                $blocks[] = ['type'=>'metric','lines'=>$recommendationLines];
            }
        }
        $blocks[] = ['type' => 'section', 'lines' => ['ÂMBITO DO RELATÓRIO']];
        $blocks[] = ['type' => 'summary', 'lines' => [
            'Cliente: ' . $context['client_name'],
            'Campanha / Projeto: ' . $context['project_name'],
            'Operativo: ' . $context['user_name'],
            'Loja / Local: ' . $context['location_name'],
            'Período: ' . $context['period'],
            'Categoria: ' . (((string)($filters['category'] ?? '')) !== '' ? (string)$filters['category'] : 'Todas'),
            'Produto: ' . (((string)($filters['product'] ?? '')) !== '' ? (string)$filters['product'] : 'Todos'),
        ]];

        $fa = is_array($data['form_analytics'] ?? null) ? $data['form_analytics'] : [];
        $metrics = (array)($fa['metrics'] ?? []);
        if ($metrics) {
            $blocks[] = ['type' => 'section', 'lines' => ['MÉTRICAS ANALÍTICAS']];
            foreach (array_slice($metrics, 0, 30) as $metric) {
                $value = ($metric['value'] ?? null) === null ? 'Sem dados' : (string)$metric['value'];
                $lines = [
                    (string)($metric['dashboard_title'] ?? 'Analytics operacional') . ' | ' . (string)($metric['metric_label'] ?? ''),
                    'Valor: ' . $value . ' | Tipo: ' . (string)($metric['question_type'] ?? 'dado') . ' | Registos: ' . (string)($metric['record_count'] ?? 0) . ' | ' . (string)($metric['aggregation'] ?? ''),
                    'Grupo de lojas: ' . ((string)($metric['store_group_name'] ?? '') ?: 'Todas as lojas do âmbito'),
                ];
                if (!empty($metric['note'])) $lines[] = 'Nota: ' . (string)$metric['note'];
                $frontsSummary = is_array($metric['fronts_summary'] ?? null) ? $metric['fronts_summary'] : [];
                if ($frontsSummary) {
                    $lines[] = 'Frentes: ' . (string)($frontsSummary['before_total'] ?? 0) . ' antes > ' . (string)($frontsSummary['after_total'] ?? 0) . ' depois | Evolução total: ' . (string)($frontsSummary['growth_label'] ?? '0,0%');
                    $lines[] = 'Referências: ' . (string)($frontsSummary['up'] ?? 0) . ' com crescimento | ' . (string)($frontsSummary['same'] ?? 0) . ' estáveis | ' . (string)($frontsSummary['down'] ?? 0) . ' com redução | ' . (string)($frontsSummary['new'] ?? 0) . ' novas';
                }
                $topRows = array_slice((array)($metric['rows'] ?? []), 0, 5);
                foreach ($topRows as $mrow) {
                    $label = trim(((string)($mrow['location'] ?? '') ?: 'Sem loja') . ' | ' . ((string)($mrow['product'] ?? '') ?: (string)($mrow['category'] ?? '')));
                    if (array_key_exists('pr_fronts', (array)$mrow) || array_key_exists('competitor_fronts', (array)$mrow) || array_key_exists('pr_share', (array)$mrow)) {
                        $detail = (string)($mrow['pr_label'] ?? $mrow['reference_a'] ?? 'Referência A') . ': ' . (string)($mrow['pr_fronts'] ?? $mrow['fronts_a'] ?? '') . ' frentes, ' . (string)($mrow['pr_share'] ?? $mrow['share_a'] ?? '') . '%';
                        $detail .= ' | ' . (string)($mrow['competitor_label'] ?? $mrow['reference_b'] ?? 'Referência B') . ': ' . (string)($mrow['competitor_fronts'] ?? $mrow['fronts_b'] ?? '') . ' frentes, ' . (string)($mrow['competitor_share'] ?? $mrow['share_b'] ?? '') . '%';
                        $detail .= ' | Objetivo: ' . (string)($mrow['target'] ?? $mrow['target_share'] ?? '') . '% | Desvio: ' . (string)($mrow['gap'] ?? $mrow['deviation'] ?? '') . ' pp | Estado: ' . (string)($mrow['status'] ?? '');
                    } else {
                        $detail = 'Valor: ' . (string)($mrow['value'] ?? '');
                        if ((string)($mrow['before'] ?? '') !== '' || (string)($mrow['after'] ?? '') !== '') $detail .= ' | ' . (string)($mrow['before'] ?? '') . ' > ' . (string)($mrow['after'] ?? '');
                        if ((string)($mrow['difference'] ?? '') !== '') $detail .= ' | Diferença: ' . (string)$mrow['difference'];
                        if ((string)($mrow['growth'] ?? '') !== '') $detail .= ' | Evolução: ' . (string)$mrow['growth'];
                    }
                    $lines[] = '- ' . $label . ': ' . $detail;
                }
                if (count((array)($metric['rows'] ?? [])) > count($topRows)) $lines[] = '- Detalhe completo disponível no Excel premium.';
                $blocks[] = ['type' => 'metric', 'lines' => $lines];
            }
        }

        $orderRows = self::orderSuggestionExportRows($filters);
        if ($orderRows) {
            $totalBoxes = 0.0;
            $totalUnits = 0.0;
            $submissionIds = [];
            foreach ($orderRows as $orderRow) {
                $totalBoxes += (float)($orderRow[13] ?? 0);
                $totalUnits += (float)($orderRow[14] ?? 0);
                if ((int)($orderRow[0] ?? 0) > 0) $submissionIds[(int)$orderRow[0]] = true;
            }
            $blocks[] = ['type' => 'section', 'lines' => ['SUGESTÕES DE ENCOMENDA']];
            $blocks[] = ['type' => 'summary', 'lines' => [
                'Sugestões: ' . count($submissionIds) . ' | Linhas de produto: ' . count($orderRows),
                'Total de caixas sugeridas: ' . number_format($totalBoxes, 2, ',', '.'),
                'Total de unidades sugeridas: ' . number_format($totalUnits, 0, ',', '.'),
            ]];
            $orderLines = [];
            foreach (array_slice($orderRows, 0, 80) as $orderRow) {
                $product = trim((string)($orderRow[9] ?? ''));
                $ref = trim((string)($orderRow[8] ?? ''));
                $label = trim(($ref !== '' ? $ref . ' | ' : '') . ($product !== '' ? $product : 'Produto'));
                $orderLines[] = (string)($orderRow[1] ?? '') . ' | ' . (string)($orderRow[5] ?? 'Loja') . ' | ' . $label
                    . ' | ' . number_format((float)($orderRow[13] ?? 0), 2, ',', '.') . ' caixas'
                    . ' | ' . number_format((float)($orderRow[14] ?? 0), 0, ',', '.') . ' unidades'
                    . ((string)($orderRow[15] ?? '') !== '' ? ' | ' . (string)$orderRow[15] : '');
            }
            if (count($orderRows) > count($orderLines)) $orderLines[] = 'O PDF apresenta as primeiras 80 linhas. Consulta o Excel premium para o detalhe completo.';
            $blocks[] = ['type' => 'metric', 'lines' => $orderLines];
        }

        $routes = (array)($data['by_day'] ?? []);
        if ($routes) {
            $blocks[] = ['type' => 'section', 'lines' => ['ROTAS DO PERÍODO']];
            foreach (array_slice($routes, 0, 30) as $row) {
                $blocks[] = ['type' => 'route', 'lines' => [
                    (string)($row['date'] ?? '') . ' | Rota #' . (string)($row['route_id'] ?? '') . ' | ' . (string)($row['project_name'] ?? ''),
                    (string)($row['user_name'] ?? '') . ' | Estado: ' . (string)($row['route_status'] ?? ''),
                    'Paragens: ' . (string)($row['stops'] ?? 0) . ' | Concluídas: ' . (string)($row['stops_done'] ?? 0) . ' | Execução: ' . (string)($row['done_rate'] ?? 0) . '%',
                ]];
            }
            if (count($routes) > 30) $blocks[] = ['type' => 'summary', 'lines' => ['O PDF apresenta as primeiras 30 rotas. Consulta o Excel premium para o detalhe completo.']];
        }

        $logo_paths = [];
        $tmp_logo_files = [];
        foreach (Branding::get_header_logos(['client_id' => (int)($filters['client_id'] ?? 0), 'project_id' => (int)($filters['project_id'] ?? 0)]) as $logo) {
            $prepared = Branding::maybe_prepare_pdf_logo_file((string)($logo['url'] ?? ''));
            if ($prepared) {
                $logo_paths[] = $prepared;
                if (strpos($prepared, sys_get_temp_dir()) === 0) $tmp_logo_files[] = $prepared;
            }
        }
        if (!$logo_paths) $logo_paths[] = ROUTESPRO_PATH . 'assets/logo-twt.jpg';
        $pdfSummary = [
            'Execução' => number_format($done, 1, ',', '.') . '%',
            'Rotas' => (string)($data['total_routes'] ?? 0),
            'Paragens' => (string)($data['total_stops'] ?? 0),
            'Concluídas' => (string)($data['done_stops'] ?? 0),
        ];
        if (isset($ri['scores']['risk']['score']) && $ri['scores']['risk']['score'] !== null) $pdfSummary['Risk'] = number_format((float)$ri['scores']['risk']['score'], 1, ',', '.') . '/100';
        if (isset($ri['scores']['opportunity']['score']) && $ri['scores']['opportunity']['score'] !== null) $pdfSummary['Opportunity'] = number_format((float)$ri['scores']['opportunity']['score'], 1, ',', '.') . '/100';
        $pdf = self::build_simple_pdf([], [
            'title' => 'Relatório semanal de Analytics',
            'subtitle' => $context['audience_label'] . ' | ' . $context['client_name'] . ' | ' . $context['project_name'] . ' | ' . $context['period'],
            'logo_paths' => $logo_paths,
            'summary' => $pdfSummary,
            'blocks' => $blocks,
        ]);
        foreach ($tmp_logo_files as $tmp_file) if (is_string($tmp_file) && file_exists($tmp_file)) @unlink($tmp_file);
        return $pdf;
    }

    private static function output_dashboard_pdf(string $filename, array $data, array $filters, array $context = []): void {
        $pdf = self::build_dashboard_pdf_binary($data, $filters, $context);
        $tmp = wp_tempnam($filename);
        if (!$tmp) throw new \RuntimeException('Não foi possível preparar o PDF.');
        $path = $tmp . '.pdf'; @unlink($path); @rename($tmp, $path); file_put_contents($path, $pdf);
        self::streamDashboardReportFile(['path'=>$path,'filename'=>$filename,'mime'=>'application/pdf']);
    }

    public static function handle_export() {
        self::prepare_export_runtime();
        if (!(current_user_can('routespro_manage') || \RoutesPro\Support\Permissions::can_access_front())) wp_die('Sem permissões.');
        $nonce_ok = false;
        if (!empty($_REQUEST['_wpnonce'])) {
            $nonce_ok = wp_verify_nonce((string) $_REQUEST['_wpnonce'], 'routespro_export_submissions');
        }
        if (!$nonce_ok && is_admin()) check_admin_referer('routespro_export_submissions');

        $filters = [
            'client_id' => absint($_GET['client_id'] ?? 0),
            'project_id' => absint($_GET['project_id'] ?? 0),
            'owner_user_id' => absint($_GET['owner_user_id'] ?? 0),
            'route_id' => absint($_GET['route_id'] ?? 0),
            'location_id' => absint($_GET['location_id'] ?? 0),
            'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_GET['date_to'] ?? ''),
            'limit' => 10000,
            'include_wide' => false,
            'scope_user_id' => current_user_can('routespro_manage') ? 0 : get_current_user_id(),
        ];
        $format = sanitize_key($_GET['format'] ?? 'csv');
        if ($format === 'excel') $format = 'xlsx';
        if (in_array($format, ['excel_mobile','mobile_excel'], true)) $format = 'xlsx_mobile';
        self::assert_saas_export($format, (int)$filters['client_id']);
        $reportMode = sanitize_key((string)($_GET['report_mode'] ?? ''));
        $dataset = $reportMode === 'visits' ? self::get_visit_report_dataset($filters) : self::get_submission_dataset($filters);
        $tables = \RoutesPro\Forms\SubmissionPresentation::exportTables((array)($dataset['rows'] ?? []));
        $filename = ($reportMode === 'visits' ? 'fieldflow-visitas-fechadas-' : 'fieldflow-submissoes-') . gmdate('Ymd-His');

        if (in_array($format, ['xlsx','xlsx_mobile'], true)) {
            $sheets = $format === 'xlsx_mobile'
                ? [$tables['summary'], $tables['detail']]
                : [$tables['summary'], $tables['answers'], $tables['briefing'], $tables['comparisons'], $tables['products'], $tables['orders'], $tables['files'], $tables['detail']];
            foreach ($sheets as &$sheet) {
                $sheet['title'] = (string)($sheet['name'] ?? 'Dados FieldFlow');
                $sheet['subtitle'] = $format === 'xlsx_mobile' ? 'Versão otimizada para Excel mobile' : 'Exportação completa FieldFlow';
            }
            unset($sheet);
            $downloadName = $filename . ($format === 'xlsx_mobile' ? '-mobile' : '-completo') . '.xlsx';
            self::streamDashboardReportFile([
                'path' => self::create_dashboard_xlsx_file($downloadName, $sheets, ['client_name'=>'FieldFlow','project_name'=>'Submissões','period'=>gmdate('Y-m-d')], $format === 'xlsx_mobile'),
                'filename' => $downloadName,
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'format' => $format,
            ]);
        }
        if ($format === 'xls') {
            self::output_excel_workbook_xml($filename . '.xls', [
                $tables['summary'],
                $tables['answers'],
                $tables['briefing'],
                $tables['comparisons'],
                $tables['products'],
                $tables['orders'],
                $tables['files'],
            ]);
        }
        if ($format === 'pdf') {
            self::output_pdf_report($filename . '.pdf', $filters, $dataset);
        }
        if ($format === 'csv_detail') {
            self::output_csv($filename . '-dados.csv', $tables['detail']['headers'], $tables['detail']['rows']);
        }
        self::output_csv($filename . '-resumo.csv', $tables['summary']['headers'], $tables['summary']['rows']);
    }


    private static function assert_saas_export(string $format, int $client_id): void {
        if (!class_exists('\RoutesPro\SaaS\EntitlementService')) return;
        $format = sanitize_key($format);
        $capability = in_array($format, ['xlsx','xlsx_mobile','xls','excel','excel_mobile','mobile_excel'], true) ? 'exports.excel' : ($format === 'pdf' ? 'exports.pdf' : 'exports.csv');
        $allowed = $client_id > 0
            ? \RoutesPro\SaaS\EntitlementService::clientHas($client_id, $capability)
            : \RoutesPro\SaaS\EntitlementService::userHasAny($capability);
        if (!$allowed) wp_die('Este formato de exportação não está incluído no plano do cliente.', 'FieldFlow', ['response'=>403,'back_link'=>true]);
        if ($client_id > 0 && class_exists('\RoutesPro\SaaS\UsageService')) {
            $quota = \RoutesPro\SaaS\UsageService::canConsume($client_id, 'exports_monthly', 1);
            if (empty($quota['allowed'])) wp_die('O limite mensal de exportações foi atingido.', 'FieldFlow', ['response'=>429,'back_link'=>true]);
            \RoutesPro\SaaS\UsageService::increment($client_id, 'exports_monthly', 1);
        }
    }

    private static function output_csv(string $filename, array $headers, array $rows) {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, array_map([self::class, 'normalize_export_text'], $headers));
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'normalize_export_text'], $row));
        }
        fclose($out);
        exit;
    }

    private static function output_excel_xml(string $filename, array $headers, array $rows) {
        nocache_headers();
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Submissoes"><Table>';
        echo '<Row>';
        foreach ($headers as $cell) echo '<Cell><Data ss:Type="String">' . self::xml_escape($cell) . '</Data></Cell>';
        echo '</Row>';
        foreach ($rows as $row) {
            echo '<Row>';
            foreach ($row as $cell) echo '<Cell><Data ss:Type="String">' . self::xml_escape((string) $cell) . '</Data></Cell>';
            echo '</Row>';
        }
        echo '</Table></Worksheet></Workbook>';
        exit;
    }


    private static function output_excel_workbook_xml(string $filename, array $sheets): void {
        nocache_headers();
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        echo '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office"><Author>The Wild Theory</Author><Company>FieldFlow</Company><Title>Submissões FieldFlow</Title><Created>' . gmdate('Y-m-d\TH:i:s\Z') . '</Created></DocumentProperties>';
        echo '<Styles>';
        echo '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Top" ss:WrapText="1"/><Font ss:FontName="Aptos" ss:Size="10"/><Borders/><Interior/><NumberFormat/><Protection/></Style>';
        echo '<Style ss:ID="Header"><Alignment ss:Vertical="Center" ss:WrapText="1"/><Font ss:FontName="Aptos" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#172033" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders></Style>';
        echo '<Style ss:ID="Data"><Alignment ss:Vertical="Top" ss:WrapText="1"/><Font ss:FontName="Aptos" ss:Size="10"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>';
        echo '</Styles>';

        $usedNames = [];
        foreach ($sheets as $sheet) {
            if (!is_array($sheet)) continue;
            $headers = array_values((array)($sheet['headers'] ?? []));
            $rows = array_values((array)($sheet['rows'] ?? []));
            $baseName = self::excel_sheet_name((string)($sheet['name'] ?? 'Dados'));
            $chunks = $rows ? array_chunk($rows, 60000) : [[]];
            foreach ($chunks as $chunkIndex => $chunkRows) {
                $name = $baseName;
                if (count($chunks) > 1) $name = self::excel_sheet_name($baseName . ' ' . ($chunkIndex + 1));
                $name = self::excel_unique_sheet_name($name, $usedNames);
                $widths = self::excel_column_widths($headers, $chunkRows);
                echo '<Worksheet ss:Name="' . self::xml_escape($name) . '"><Table>';
                foreach ($widths as $width) echo '<Column ss:AutoFitWidth="0" ss:Width="' . number_format($width, 1, '.', '') . '"/>';
                echo '<Row ss:StyleID="Header" ss:Height="30">';
                foreach ($headers as $cell) echo '<Cell><Data ss:Type="String">' . self::xml_escape((string)$cell) . '</Data></Cell>';
                echo '</Row>';
                foreach ($chunkRows as $row) {
                    echo '<Row ss:StyleID="Data">';
                    foreach ($headers as $index => $unused) {
                        $cell = $row[$index] ?? '';
                        echo '<Cell><Data ss:Type="String">' . self::xml_escape((string)$cell) . '</Data></Cell>';
                    }
                    echo '</Row>';
                }
                echo '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>1</SplitHorizontal><TopRowBottomPane>1</TopRowBottomPane><Selected/><ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios></WorksheetOptions></Worksheet>';
            }
        }
        echo '</Workbook>';
        exit;
    }

    private static function excel_unique_sheet_name(string $name, array &$usedNames): string {
        $base = self::excel_sheet_name($name);
        $candidate = $base;
        $suffixNo = 2;
        while (isset($usedNames[strtolower($candidate)])) {
            $suffix = ' ' . $suffixNo++;
            $suffixLength = function_exists('mb_strlen') ? mb_strlen($suffix) : strlen($suffix);
            $limit = max(1, 31 - $suffixLength);
            $trimmed = function_exists('mb_substr') ? mb_substr($base, 0, $limit) : substr($base, 0, $limit);
            $candidate = rtrim($trimmed) . $suffix;
        }
        $usedNames[strtolower($candidate)] = true;
        return $candidate;
    }

    private static function excel_sheet_name(string $name): string {
        $name = self::xlsx_safe_text($name);
        $name = preg_replace('~[\\/?*\[\]:]+~u', ' ', trim($name)) ?: 'Dados';
        $name = preg_replace('/\s+/u', ' ', $name) ?: 'Dados';
        $name = trim($name, " '");
        if ($name === '' || strcasecmp($name, 'History') === 0) $name = 'Dados';
        if (function_exists('mb_substr')) return mb_substr($name, 0, 31, 'UTF-8');
        return substr($name, 0, 31);
    }

    private static function excel_column_widths(array $headers, array $rows): array {
        $widths = [];
        foreach ($headers as $index => $header) {
            $max = function_exists('mb_strlen') ? mb_strlen((string)$header) : strlen((string)$header);
            foreach (array_slice($rows, 0, 80) as $row) {
                $value = preg_replace('/\s+/u', ' ', (string)($row[$index] ?? '')) ?: '';
                $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
                $max = max($max, min(48, $length));
            }
            $widths[] = min(230, max(72, 16 + ($max * 6.2)));
        }
        return $widths;
    }


    private static function output_pdf_report(string $filename, array $filters, array $dataset) {
        $filterLines = self::build_filter_lines($filters);
        $projectId = (int)($filters['project_id'] ?? 0);
        $clientId = (int)($filters['client_id'] ?? 0);
        if (!$clientId && !empty($dataset['rows'][0]['client_id'])) $clientId = (int)$dataset['rows'][0]['client_id'];
        if (!$projectId && !empty($dataset['rows'][0]['project_id'])) $projectId = (int)$dataset['rows'][0]['project_id'];

        $totals = ['products'=>0,'zeros'=>0,'orders'=>0,'comparisons'=>0,'comparison_risks'=>0,'files'=>0,'briefing_questions'=>0,'briefing_answered'=>0];
        $locations = [];
        foreach ((array)($dataset['rows'] ?? []) as $row) {
            $summary = (array)($row['presentation']['summary'] ?? []);
            $totals['products'] += (int)($summary['product_rows'] ?? 0);
            $totals['zeros'] += (int)($summary['zero_products'] ?? 0);
            $totals['orders'] += (int)($summary['orders'] ?? 0);
            $totals['comparisons'] += (int)($summary['comparisons'] ?? 0);
            $totals['comparison_risks'] += (int)($summary['comparisons_below_target'] ?? 0);
            $totals['files'] += (int)($summary['files'] ?? 0);
            $totals['briefing_questions'] += (int)($summary['briefing_questions'] ?? 0);
            $totals['briefing_answered'] += (int)($summary['briefing_answered'] ?? 0);
            if (!empty($row['location_name'])) $locations[(string)$row['location_name']] = true;
        }

        $blocks = [];
        $blocks[] = ['type'=>'summary','lines'=>[
            'Resumo executivo',
            'Gerado em: ' . wp_date('d/m/Y H:i'),
            'Submissões: ' . count((array)($dataset['rows'] ?? [])) . ' | Lojas: ' . count($locations),
            'Referências: ' . $totals['products'] . ' | Ruturas: ' . $totals['zeros'] . ' | Encomendas: ' . $totals['orders'] . ' | Comparativos: ' . $totals['comparisons'] . ' | Briefing: ' . $totals['briefing_answered'] . '/' . $totals['briefing_questions'] . ' | Evidências: ' . $totals['files'],
            'O Excel organizado contém o detalhe integral em folhas separadas.',
        ]];
        $filterBlock = ['Filtros aplicados:'];
        foreach ($filterLines as $line) $filterBlock[] = '- ' . $line;
        $blocks[] = ['type'=>'filters','lines'=>$filterBlock];

        foreach ((array)($dataset['rows'] ?? []) as $row) {
            $presentation = (array)($row['presentation'] ?? []);
            $summary = (array)($presentation['summary'] ?? []);
            $lines = [];
            $lines[] = 'Submissão #' . (int)$row['id'] . ' | ' . self::format_submission_date((string)($row['submitted_at'] ?? ''));
            $lines[] = 'Loja: ' . ($row['location_name'] ?: 'Sem loja') . ' | Rota #' . (int)($row['route_id'] ?? 0) . ' | Paragem #' . (int)($row['route_stop_id'] ?? 0);
            $lines[] = 'Formulário: ' . ($row['form_title'] ?: ('#' . (int)$row['form_id']));
            $lines[] = 'Cliente: ' . ($row['client_name'] ?: 'Sem cliente') . ' | Campanha: ' . ($row['project_name'] ?: 'Sem campanha');
            $lines[] = 'Operativo: ' . ($row['owner_name'] ?: 'Sem operativo') . ' | Submetido por: ' . ($row['user_name'] ?: ('User #' . (int)$row['user_id']));
            $lines[] = 'Estado: ' . self::submission_status_label((string)($row['status'] ?? '')) . ' | Visita: ' . self::stop_status_label((string)($row['stop_status'] ?? ''));
            $lines[] = 'Conteúdo: ' . (int)($summary['answers'] ?? 0) . ' respostas | ' . (int)($summary['briefing_questions'] ?? 0) . ' briefing, ' . (int)($summary['briefing_answered'] ?? 0) . ' respondidas | ' . (int)($summary['product_rows'] ?? 0) . ' referências | ' . (int)($summary['zero_products'] ?? 0) . ' ruturas | ' . (int)($summary['orders'] ?? 0) . ' encomendas | ' . (int)($summary['comparisons'] ?? 0) . ' comparativos | ' . (int)($summary['files'] ?? 0) . ' evidências';

            $answers = (array)($presentation['answers'] ?? []);
            if ($answers) {
                $lines[] = 'Respostas do formulário:';
                foreach ($answers as $answer) {
                    $value = trim((string)($answer['value'] ?? ''));
                    if ($value === '') continue;
                    $lines[] = '- ' . (string)($answer['label'] ?? 'Resposta') . ': ' . $value;
                }
            }

            $briefingQuestions = (array)($presentation['briefing_questions'] ?? []);
            if ($briefingQuestions) {
                $lines[] = 'Briefing da loja:';
                foreach ($briefingQuestions as $question) {
                    $value = !empty($question['answered']) ? (string)($question['answer'] ?? '0') : 'Sem resposta';
                    $lines[] = '- ' . (string)($question['label'] ?? 'Pergunta de briefing') . ': ' . $value . ' | ' . (string)($question['briefing_title'] ?? 'Briefing');
                }
            }

            foreach ((array)($presentation['comparison_questions'] ?? []) as $question) {
                $comparisonRows = (array)($question['rows'] ?? []);
                if (!$comparisonRows) continue;
                $lines[] = (string)($question['label'] ?? 'Comparativo') . ' [' . (string)($question['type_label'] ?? 'Comparativo automático') . ']:';
                foreach ($comparisonRows as $comparison) {
                    $lines[] = '- ' . (string)($comparison['metric'] ?? 'Comparação') . ': ' . (string)($comparison['reference_a'] ?? 'A') . ' ' . (string)($comparison['fronts_a'] ?? '') . ' frentes, ' . (string)($comparison['share_a'] ?? '') . '% | ' . (string)($comparison['reference_b'] ?? 'B') . ' ' . (string)($comparison['fronts_b'] ?? '') . ' frentes, ' . (string)($comparison['share_b'] ?? '') . '% | Objetivo ' . (string)($comparison['target'] ?? '') . '% | Desvio ' . (string)($comparison['gap'] ?? '') . ' pp | ' . (string)($comparison['status_label'] ?? '');
                }
            }

            foreach ((array)($presentation['product_questions'] ?? []) as $question) {
                $productRows = (array)($question['rows'] ?? []);
                if (!$productRows) continue;
                $zero = 0; $positive = 0; $changed = 0;
                foreach ($productRows as $product) {
                    if (($product['status'] ?? '') === 'zero') $zero++;
                    elseif (($product['status'] ?? '') === 'positive') $positive++;
                    if (!empty($product['changed'])) $changed++;
                }
                $lines[] = (string)($question['label'] ?? 'Produtos') . ': ' . count($productRows) . ' referências | ' . $zero . ' ruturas | ' . $positive . ' com presença' . ($changed ? ' | ' . $changed . ' alterações' : '');
                foreach ((array)($question['categories'] ?? []) as $category) {
                    $exceptions = [];
                    foreach ((array)($category['rows'] ?? []) as $product) {
                        if (($product['status'] ?? '') !== 'zero' && empty($product['changed'])) continue;
                        $value = ((string)($product['before'] ?? '') !== '' || (string)($product['after'] ?? '') !== '')
                            ? (string)($product['before'] ?? '') . ' > ' . (string)($product['after'] ?? '')
                            : (string)($product['value'] ?? $product['qty'] ?? '');
                        $productLabel = trim(((string)($product['ref'] ?? '') !== '' ? (string)$product['ref'] . ' - ' : '') . ((string)($product['name'] ?? '') ?: 'Produto'));
                        $exceptions[] = $productLabel . ': ' . ($value !== '' ? $value : '0') . (($product['status'] ?? '') === 'zero' ? ' [RUTURA]' : ' [ALTERADO]');
                    }
                    $lines[] = '  ' . (string)($category['name'] ?? 'Sem categoria') . ': ' . (int)($category['count'] ?? 0) . ' refs | ' . (int)($category['zero'] ?? 0) . ' a zero';
                    $shownExceptions = array_slice($exceptions, 0, 20);
                    foreach ($shownExceptions as $exception) $lines[] = '  - ' . $exception;
                    if (count($exceptions) > count($shownExceptions)) $lines[] = '  - +' . (count($exceptions) - count($shownExceptions)) . ' outras exceções, consulta o Excel organizado para o detalhe completo.';
                }
            }

            foreach ((array)($presentation['order_questions'] ?? []) as $question) {
                $orderRows = (array)($question['rows'] ?? []);
                if (!$orderRows) continue;
                $lines[] = (string)($question['label'] ?? 'Sugestão de encomenda') . ':';
                foreach ($orderRows as $order) {
                    $productLabel = trim(((string)($order['category'] ?? '') !== '' ? (string)$order['category'] . ' | ' : '') . ((string)($order['ref'] ?? '') !== '' ? (string)$order['ref'] . ' - ' : '') . ((string)($order['name'] ?? '') ?: 'Produto'));
                    $detail = (string)($order['order_qty'] ?? '0') . ' caixas';
                    if ((string)($order['units_total'] ?? '') !== '') $detail .= ' | ' . (string)$order['units_total'] . ' unidades';
                    if (trim((string)($order['notes'] ?? '')) !== '') $detail .= ' | ' . trim((string)$order['notes']);
                    $lines[] = '- ' . $productLabel . ': ' . $detail;
                }
            }

            $files = (array)($presentation['files'] ?? []);
            if ($files) {
                $lines[] = 'Evidências:';
                foreach ($files as $file) $lines[] = '- ' . (string)($file['label'] ?? 'Ficheiro') . ': ' . (string)($file['url'] ?? '');
            }
            $blocks[] = ['type'=>'submission','lines'=>$lines];
        }

        $logoPaths = [];
        $tmpLogoFiles = [];
        foreach (Branding::get_header_logos(['client_id'=>$clientId,'project_id'=>$projectId]) as $logo) {
            $prepared = Branding::maybe_prepare_pdf_logo_file((string)($logo['url'] ?? ''));
            if ($prepared) {
                $logoPaths[] = $prepared;
                if (strpos($prepared, sys_get_temp_dir()) === 0) $tmpLogoFiles[] = $prepared;
            }
        }
        if (!$logoPaths) $logoPaths[] = ROUTESPRO_PATH . 'assets/logo-twt.jpg';

        $pdf = self::build_simple_pdf([], [
            'title'=>'Relatório executivo de submissões',
            'subtitle'=>'FieldFlow Pro | Respostas, ruturas, encomendas e evidências',
            'logo_paths'=>$logoPaths,
            'summary'=>[
                'Submissões'=>(string)count((array)($dataset['rows'] ?? [])),
                'Lojas'=>(string)count($locations),
                'Ruturas'=>(string)$totals['zeros'],
                'Encomendas'=>(string)$totals['orders'],
                'Comparativos'=>(string)$totals['comparisons'],
            ],
            'blocks'=>$blocks,
        ]);
        foreach ($tmpLogoFiles as $tmpFile) if (is_string($tmpFile) && file_exists($tmpFile)) @unlink($tmpFile);
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }


    private static function format_duration_minutes($seconds): string {
        if ($seconds === null || $seconds === '' || !is_numeric($seconds)) return '--';
        $minutes = round(((float)$seconds) / 60, 1);
        if (abs($minutes - round($minutes)) < 0.01) return (string)((int)round($minutes));
        return number_format($minutes, 1, '.', '');
    }

    private static function format_real_gps(array $row): string {
        $lat = isset($row['real_lat']) && $row['real_lat'] !== '' ? (string)$row['real_lat'] : '';
        $lng = isset($row['real_lng']) && $row['real_lng'] !== '' ? (string)$row['real_lng'] : '';
        if ($lat === '' && $lng === '') return '--';
        return trim($lat . ', ' . $lng, ', ');
    }

    private static function build_filter_lines(array $filters): array {
        global $wpdb;
        $lines = [];
        if (!empty($filters['client_id'])) {
            $name = $wpdb->get_var($wpdb->prepare('SELECT name FROM ' . $wpdb->prefix . 'routespro_clients WHERE id=%d', (int) $filters['client_id']));
            $lines[] = 'Cliente: ' . ($name ?: ('#' . (int) $filters['client_id']));
        }
        if (!empty($filters['project_id'])) {
            $name = $wpdb->get_var($wpdb->prepare('SELECT name FROM ' . $wpdb->prefix . 'routespro_projects WHERE id=%d', (int) $filters['project_id']));
            $lines[] = 'Campanha / Projeto: ' . ($name ?: ('#' . (int) $filters['project_id']));
        }
        if (!empty($filters['owner_user_id'])) {
            $user = get_userdata((int) $filters['owner_user_id']);
            $lines[] = 'Owner: ' . ($user ? $user->display_name : ('#' . (int) $filters['owner_user_id']));
        }
        if (!empty($filters['route_id'])) {
            $route = $wpdb->get_row($wpdb->prepare('SELECT id, date, status FROM ' . $wpdb->prefix . 'routespro_routes WHERE id=%d', (int) $filters['route_id']), ARRAY_A);
            $lines[] = 'Rota: ' . ($route ? ('#' . (int) $route['id'] . ' | ' . ($route['date'] ?: 'Sem data') . ' | ' . ($route['status'] ?: '')) : ('#' . (int) $filters['route_id']));
        }
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $lines[] = 'Periodo: ' . (!empty($filters['date_from']) ? $filters['date_from'] : '...') . ' ate ' . (!empty($filters['date_to']) ? $filters['date_to'] : '...');
        }
        if (empty($lines)) $lines[] = 'Sem filtros específicos, relatório global dentro do âmbito visível.';
        return $lines;
    }

    private static function build_simple_pdf(array $lines, array $options = []): string {
        $title = (string) ($options['title'] ?? 'Relatório');
        $subtitle = (string) ($options['subtitle'] ?? 'FieldFlow Pro');
        $summary = is_array($options['summary'] ?? null) ? $options['summary'] : [];
        $logo_paths = [];
        if (!empty($options['logo_paths']) && is_array($options['logo_paths'])) $logo_paths = array_values(array_filter(array_map('strval', $options['logo_paths'])));
        elseif (!empty($options['logo_path'])) $logo_paths = [(string)$options['logo_path']];

        $max_chars = 102;
        $blocks = is_array($options['blocks'] ?? null) ? $options['blocks'] : [];
        if (!$blocks) {
            foreach ($lines as $line) {
                $blocks[] = ['type' => 'text', 'lines' => [$line]];
            }
        }

        $page_w = 841.89;
        $page_h = 595.28;
        $line_h = 12.0;
        $first_page_start = 372;
        $other_page_start = 438;
        $bottom_margin = 40;
        $pages = [];
        $current = [];
        $y = $first_page_start;

        foreach ($blocks as $block) {
            $block_lines = [];
            foreach ((array)($block['lines'] ?? []) as $line) {
                $line = self::normalize_export_text($line, ['for_pdf' => false]);
                if ($line === '') {
                    $block_lines[] = '';
                    continue;
                }
                foreach (preg_split('/\n/', wordwrap($line, $max_chars, "\n", true)) as $chunk) {
                    $block_lines[] = $chunk;
                }
            }
            if (!$block_lines) continue;

            $block_height = count($block_lines) * $line_h + 8;
            if (($y - $block_height) < $bottom_margin && !empty($current)) {
                $pages[] = $current;
                $current = [];
                $y = $other_page_start;
            }

            foreach ($block_lines as $line) {
                if ($y < $bottom_margin) {
                    $pages[] = $current;
                    $current = [];
                    $y = $other_page_start;
                }
                $current[] = [$line, $y, (string)($block['type'] ?? 'text')];
                $y -= $line_h;
            }
            $y -= 8;
            if ($y < $bottom_margin && !empty($current)) {
                $pages[] = $current;
                $current = [];
                $y = $other_page_start;
            }
        }
        if ($current || empty($pages)) $pages[] = $current;

        $objects = [];
        $add = function ($content) use (&$objects) {
            $objects[] = $content;
            return count($objects);
        };

        $font_regular = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $font_bold = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');

        $image_objects = [];
        foreach ($logo_paths as $logo_path) {
            if (!$logo_path || !is_readable($logo_path)) continue;
            $img_data = @file_get_contents($logo_path);
            $img_info = @getimagesize($logo_path);
            if ($img_data === false || empty($img_info[0]) || empty($img_info[1])) continue;
            $filter = '/DCTDecode';
            if (($img_info['mime'] ?? '') === 'image/png') $filter = '/FlateDecode';
            $image_objects[] = [
                'id' => $add("<< /Type /XObject /Subtype /Image /Width {$img_info[0]} /Height {$img_info[1]} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filter} /Length " . strlen($img_data) . " >>
stream
" . $img_data . "
endstream"),
                'w' => (int)$img_info[0],
                'h' => (int)$img_info[1],
            ];
        }

        $content_ids = [];
        $page_ids = [];
        foreach ($pages as $page_index => $page_lines) {
            $stream = '';
            $stream .= "0.949 0.957 0.976 rg 0 0 {$page_w} {$page_h} re f
";
            $stream .= "0.973 0.976 0.988 rg 24 454 794 116 re f
";
            $stream .= "0.85 0.88 0.93 RG 24 454 794 116 re S
";
            $stream .= "0.92 0.38 0.16 rg 24 454 6 116 re f
";

            $logo_x = 40;
            foreach ($image_objects as $idx => $img) {
                $draw_h = 30;
                $ratio = $img['w'] / max(1, $img['h']);
                $draw_w = min(110, max(58, $ratio * $draw_h));
                $box_w = $draw_w + 14;
                $stream .= sprintf("1 1 1 rg %.2f 478 %.2f 44 re f
", $logo_x, $box_w);
                $stream .= sprintf("0.85 0.88 0.93 RG %.2f 478 %.2f 44 re S
", $logo_x, $box_w);
                $stream .= 'q ' . number_format($draw_w, 2, '.', '') . ' 0 0 ' . number_format($draw_h, 2, '.', '') . ' ' . number_format($logo_x + 7, 2, '.', '') . ' 485 cm /Im' . ($idx + 1) . ' Do Q' . "
";
                $logo_x += $box_w + 12;
            }

            $stream .= "0.16 0.20 0.29 rg
";
            $stream .= 'BT /F2 21 Tf 40 554 Td (' . self::pdf_escape($title) . ') Tj ET' . "
";
            if ($subtitle !== '') {
                $stream .= "0.36 0.42 0.52 rg
";
                $stream .= 'BT /F1 10 Tf 40 537 Td (' . self::pdf_escape($subtitle) . ') Tj ET' . "
";
            }
            $stream .= "0.92 0.38 0.16 rg
";
            $stream .= 'BT /F2 11 Tf 690 548 Td (' . self::pdf_escape('FieldFlow Pro') . ') Tj ET' . "
";
            $stream .= "0.36 0.42 0.52 rg
";
            $stream .= 'BT /F1 9 Tf 690 534 Td (' . self::pdf_escape('Exportação PDF') . ') Tj ET' . "
";

            if ($page_index === 0 && $summary) {
                $card_x = [40, 236, 432, 628];
                $i = 0;
                foreach ($summary as $label => $value) {
                    if ($i >= 4) break;
                    $x = $card_x[$i];
                    $stream .= sprintf("1 1 1 rg %.2f 405 170 46 re f
", $x);
                    $stream .= sprintf("0.85 0.88 0.93 RG %.2f 405 170 46 re S
", $x);
                    $stream .= "0.36 0.42 0.52 rg
";
                    $stream .= 'BT /F1 8 Tf ' . ($x + 10) . ' 435 Td (' . self::pdf_escape((string)$label) . ') Tj ET' . "
";
                    $stream .= "0.16 0.20 0.29 rg
";
                    $stream .= 'BT /F2 10 Tf ' . ($x + 10) . ' 418 Td (' . self::pdf_escape((string)$value) . ') Tj ET' . "
";
                    $i++;
                }
            }

            foreach ($page_lines as $line_data) {
                [$line, $yy, $block_type] = array_pad($line_data, 3, 'text');
                $font = '/F1 9 Tf';
                $text_rgb = '0.23 0.27 0.34 rg';
                if ($block_type === 'section') {
                    $font = '/F2 10 Tf';
                    $text_rgb = '0.12 0.29 0.58 rg';
                    $stream .= '0.91 0.95 1 rg 34 ' . number_format($yy - 4, 2, '.', '') . ' 774 15 re f' . "
";
                } elseif ($block_type === 'metric' && strpos($line, '- ') !== 0) {
                    $font = '/F2 9 Tf';
                    $text_rgb = '0.16 0.20 0.29 rg';
                } elseif (strpos($line, 'Submissão #') === 0 || $line === 'Filtros aplicados:' || $line === 'Respostas:' || $line === 'Resumo executivo') {
                    $font = '/F2 10 Tf';
                    $text_rgb = '0.16 0.20 0.29 rg';
                } elseif ($block_type === 'route' && strpos($line, 'Rota #') !== false) {
                    $font = '/F2 9 Tf';
                    $text_rgb = '0.16 0.20 0.29 rg';
                } elseif (preg_match('/^(Cliente|Campanha \/ Projeto|Rota|Paragem|Owner|Owner ID|Submetido por|Status|Check\-in|Check\-out|Duração visita|Estado paragem|Nota operacional|Motivo falha|GPS real|Formulário):/u', $line)) {
                    $text_rgb = '0.30 0.35 0.43 rg';
                } elseif (strpos($line, '- ') === 0) {
                    $text_rgb = '0.36 0.42 0.52 rg';
                }
                $stream .= $text_rgb . "
";
                $stream .= 'BT ' . $font . ' 40 ' . number_format($yy, 2, '.', '') . ' Td (' . self::pdf_escape($line) . ') Tj ET' . "
";
            }

            $stream .= "0.85 0.88 0.93 RG 24 22 794 0.7 re f
";
            $stream .= "0.36 0.42 0.52 rg
";
            $stream .= 'BT /F1 8 Tf 40 12 Td (' . self::pdf_escape('FieldFlow Pro | Relatório operacional') . ') Tj ET' . "
";
            $stream .= 'BT /F1 8 Tf 740 12 Td (' . self::pdf_escape('Página ' . ($page_index + 1)) . ') Tj ET' . "
";

            $content_ids[] = $add("<< /Length " . strlen($stream) . " >>
stream
" . $stream . "endstream");
            $page_ids[] = 0;
        }

        $pages_root_hint = count($objects) + count($pages) + 1;
        foreach ($pages as $i => $page_lines) {
            $resources = '<< /Font << /F1 ' . $font_regular . ' 0 R /F2 ' . $font_bold . ' 0 R >>';
            if ($image_objects) {
                $resources .= ' /XObject << ';
                foreach ($image_objects as $idx => $img) $resources .= '/Im' . ($idx + 1) . ' ' . $img['id'] . ' 0 R ';
                $resources .= '>>';
            }
            $resources .= ' >>';
            $page_ids[$i] = $add('<< /Type /Page /Parent ' . $pages_root_hint . ' 0 R /MediaBox [0 0 ' . $page_w . ' ' . $page_h . '] /Resources ' . $resources . ' /Contents ' . $content_ids[$i] . ' 0 R >>');
        }
        $kids = implode(' ', array_map(static function ($id) { return $id . ' 0 R'; }, $page_ids));
        $pages_id = $add('<< /Type /Pages /Kids [ ' . $kids . ' ] /Count ' . count($page_ids) . ' >>');
        $catalog_id = $add('<< /Type /Catalog /Pages ' . $pages_id . ' 0 R >>');

        $pdf = "%PDF-1.4
%âãÏÓ
";
        $offsets = [0];
        foreach ($objects as $idx => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($idx + 1) . " 0 obj
" . $obj . "
endobj
";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "
" . '0 ' . (count($objects) + 1) . "
";
        $pdf .= "0000000000 65535 f 
";
        for ($i = 1; $i <= count($objects); $i++) $pdf .= sprintf("%010d 00000 n 
", $offsets[$i]);
        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root ' . $catalog_id . ' 0 R >>' . "
";
        $pdf .= 'startxref' . "
" . $xref . "
%%EOF";
        return $pdf;
    }

    private static function normalize_export_text($value, array $options = []): string {
        $text = is_scalar($value) || (is_object($value) && method_exists($value, '__toString')) ? (string) $value : '';
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x{00A0}\x{2007}\x{202F}]/u', ' ', $text) ?: $text;
        $text = preg_replace('/[\r\n\t]+/u', ' ', $text) ?: $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?: $text;
        $text = trim($text);

        $map = [
            '–' => '-',
            '—' => '-',
            '−' => '-',
            '•' => '-',
            '·' => '-',
            '“' => '"',
            '”' => '"',
            '„' => '"',
            '’' => "'",
            '‘' => "'",
            '´' => "'",
            '…' => '...',
        ];
        $text = strtr($text, $map);

        if (!empty($options['for_pdf'])) {
            if (function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
                if ($converted !== false) return $converted;
            } elseif (function_exists('iconv')) {
                $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
                if ($converted !== false) return $converted;
            }
        }

        return $text;
    }

    private static function xml_escape($value): string {
        return htmlspecialchars(self::normalize_export_text($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function pdf_escape(string $text): string {
        $text = self::normalize_export_text($text, ['for_pdf' => true]);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    public static function render_edit() {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        global $wpdb;
        $id = absint($_GET['id'] ?? 0);
        $submission = $id ? $wpdb->get_row($wpdb->prepare('SELECT s.*, c.name AS client_name, p.name AS project_name, r.owner_user_id, r.date AS route_date, r.status AS route_status, rs.status AS stop_status, loc.name AS location_name, owner.display_name AS owner_name FROM ' . FormsModule::table_submissions() . ' s LEFT JOIN ' . $wpdb->prefix . 'routespro_clients c ON c.id=s.client_id LEFT JOIN ' . $wpdb->prefix . 'routespro_projects p ON p.id=s.project_id LEFT JOIN ' . $wpdb->prefix . 'routespro_routes r ON r.id=s.route_id LEFT JOIN ' . $wpdb->users . ' owner ON owner.ID=r.owner_user_id LEFT JOIN ' . $wpdb->prefix . 'routespro_route_stops rs ON rs.id=s.route_stop_id LEFT JOIN ' . $wpdb->prefix . 'routespro_locations loc ON loc.id=rs.location_id WHERE s.id=%d', $id), ARRAY_A) : null;
        if (!$submission) wp_die('Submissão não encontrada.');
        $form = FormsModule::get_form((int)$submission['form_id']);
        $schema = FormsModule::decode_schema($form['schema_json'] ?? '');
        $answers = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . FormsModule::table_answers() . ' WHERE submission_id=%d ORDER BY id ASC', $id), ARRAY_A) ?: [];
        $answers_by_key = [];
        foreach ($answers as $a) $answers_by_key[$a['question_key']] = $a;
        $dynamic_question_meta = [];
        if ($answers_by_key) {
            $contextTable = ContextQuestionService::table();
            $keys = array_values(array_filter(array_map('sanitize_key', array_keys($answers_by_key))));
            if ($keys && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $contextTable)) === $contextTable) {
                $ph = implode(',', array_fill(0, count($keys), '%s'));
                $metaRows = $wpdb->get_results($wpdb->prepare("SELECT question_key,question_label,question_type,question_options_json,source_type,briefing_id FROM {$contextTable} WHERE question_key IN ({$ph})", ...$keys), ARRAY_A) ?: [];
                foreach ($metaRows as $metaRow) $dynamic_question_meta[sanitize_key((string)$metaRow['question_key'])] = $metaRow;
            }
        }
        echo '<div class="wrap">';
        Branding::render_header('Editar submissão', 'Podes corrigir respostas, ajustar o estado e rever também o contexto operacional da rota.');
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="routespro_save_submission">';
        echo '<input type="hidden" name="id" value="' . (int)$id . '">';
        wp_nonce_field('routespro_save_submission_' . $id, 'routespro_save_submission_nonce');
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">ID</th><td>#' . (int)$submission['id'] . '</td></tr>';
        echo '<tr><th scope="row">Formulário</th><td>' . esc_html($form['title'] ?? ('#' . (int)$submission['form_id'])) . '</td></tr>';
        echo '<tr><th scope="row">Cliente</th><td>' . esc_html($submission['client_name'] ?: 'Sem cliente') . '</td></tr>';
        echo '<tr><th scope="row">Campanha / Projeto</th><td>' . esc_html($submission['project_name'] ?: 'Sem campanha') . '</td></tr>';
        echo '<tr><th scope="row">Rota</th><td>' . wp_kses_post(self::render_route_cell($submission)) . '</td></tr>';
        echo '<tr><th scope="row">Paragem</th><td>' . wp_kses_post(self::render_stop_cell($submission)) . '</td></tr>';
        echo '<tr><th scope="row">Owner</th><td>' . esc_html($submission['owner_name'] ?: 'Sem owner') . '</td></tr>';
        echo '<tr><th scope="row"><label for="routespro_submission_status">Status</label></th><td><select id="routespro_submission_status" name="status">';
        foreach (['submitted'=>'Submetida','reviewed'=>'Revista','approved'=>'Aprovada','rejected'=>'Rejeitada','draft'=>'Rascunho','void'=>'Anulada'] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($submission['status'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><p class="description">Estados operacionais: Submetida, Revista e Aprovada. Rascunho, Rejeitada e Anulada não contam para execução.</p></td></tr>';
        echo '<tr><th scope="row"><label for="routespro_correction_reason">Motivo da correção</label></th><td><textarea class="large-text" rows="3" id="routespro_correction_reason" name="correction_reason" required placeholder="Explica o que estás a corrigir e porquê"></textarea><p class="description">Obrigatório. Fica registado no histórico com utilizador, data e campos alterados.</p></td></tr>';
        echo '</tbody></table>';
        self::render_correction_history($submission);
        echo '<h2 style="margin-top:24px">Respostas</h2>';
        if (!empty($schema['questions'])) {
            echo '<table class="form-table" role="presentation"><tbody>';
            $rendered_keys = [];
            foreach ($schema['questions'] as $question) {
                if (!is_array($question)) continue;
                $key = sanitize_key($question['key'] ?? '');
                if (!$key) continue;
                $type = sanitize_key($question['type'] ?? 'text');
                if (in_array($type, ['briefing_block','briefing_intro'], true)) continue;
                $rendered_keys[$key] = true;
                $answer = $answers_by_key[$key] ?? null;
                $label = sanitize_text_field($question['label'] ?? $key);
                echo '<tr><th scope="row"><label for="routespro_answer_' . esc_attr($key) . '">' . esc_html($label) . '</label><div style="font-weight:400;color:#64748b;margin-top:4px">' . esc_html($key) . '</div></th><td>';
                self::render_answer_input($key, $type, $answer, $question);
                echo '</td></tr>';
            }
            foreach ($answers_by_key as $key => $answer) {
                $key = sanitize_key((string)$key);
                if (!$key || isset($rendered_keys[$key])) continue;
                $meta = $dynamic_question_meta[$key] ?? [];
                $type = sanitize_key((string)($meta['question_type'] ?? 'text')) ?: 'text';
                $label = sanitize_text_field((string)($meta['question_label'] ?? $answer['question_label'] ?? $key));
                $options = json_decode((string)($meta['question_options_json'] ?? ''), true);
                $question = ['options'=>is_array($options)?$options:[]];
                $source = (sanitize_key((string)($meta['source_type'] ?? '')) === 'briefing' || !empty($meta['briefing_id'])) ? 'Briefing' : 'Contextual';
                echo '<tr><th scope="row"><label for="routespro_answer_' . esc_attr($key) . '">' . esc_html($label) . '</label><div style="font-weight:400;color:#64748b;margin-top:4px">' . esc_html($source . ' · ' . $key) . '</div></th><td>';
                self::render_answer_input($key, $type, $answer, $question);
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>O formulário já não tem schema disponível. Vais ver apenas as respostas atuais.</p>';
            echo '<table class="form-table" role="presentation"><tbody>';
            foreach ($answers as $answer) {
                $key = sanitize_key($answer['question_key'] ?? '');
                $label = sanitize_text_field($answer['question_label'] ?: $key);
                echo '<tr><th scope="row"><label for="routespro_answer_' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
                self::render_answer_input($key, 'text', $answer, []);
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }
        submit_button('Guardar correção');
        echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=routespro-form-submissions')) . '">Voltar</a>';
        echo '</form>';
        if (sanitize_key((string)($submission['status'] ?? '')) !== 'void') {
            echo '<hr style="margin:28px 0">';
            echo '<section id="ff-submission-void" style="max-width:900px;padding:18px;border:1px solid #fecaca;background:#fff7f7;border-radius:12px">';
            echo '<h2 style="margin-top:0">Anular submissão</h2><p>A anulação retira o reporte da execução operacional, mas preserva respostas, fotografias, versões e auditoria.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="routespro_delete_submission"><input type="hidden" name="id" value="' . (int)$id . '">';
            wp_nonce_field('routespro_delete_submission_' . $id, 'routespro_delete_submission_nonce');
            echo '<p><label for="routespro_void_reason"><strong>Motivo da anulação</strong></label><br><textarea class="large-text" rows="3" id="routespro_void_reason" name="correction_reason" required></textarea></p>';
            submit_button('Anular submissão', 'delete', 'submit', false, ['onclick'=>"return confirm('Anular esta submissão mantendo todo o histórico?')"]);
            echo '</form></section>';
        }
        echo '</div>';
    }

    public static function handle_save() {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        $id = absint($_POST['id'] ?? 0);
        check_admin_referer('routespro_save_submission_' . $id, 'routespro_save_submission_nonce');
        global $wpdb;
        $submission = $id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . FormsModule::table_submissions() . ' WHERE id=%d', $id), ARRAY_A) : null;
        if (!$submission) wp_die('Submissão não encontrada.');

        $status = sanitize_key((string)($_POST['status'] ?? 'submitted'));
        $reason = sanitize_textarea_field(wp_unslash((string)($_POST['correction_reason'] ?? '')));
        if (trim($reason) === '') wp_die('Indica o motivo da correção antes de guardar.');

        $answers = self::build_admin_correction_answers($submission);
        if (is_wp_error($answers)) wp_die(esc_html($answers->get_error_message()));

        $result = SubmissionCorrectionService::correct($id, $status, $answers, $reason, get_current_user_id());
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()));
        $args = ['page'=>'routespro-form-submissions', 'corrected'=>1];
        if (!empty($result['warning'])) $args['warning'] = sanitize_text_field((string)$result['warning']);
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function handle_delete() {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        $id = absint($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('routespro_delete_submission_' . $id, 'routespro_delete_submission_nonce');
        } else {
            check_admin_referer('routespro_delete_submission_' . $id);
        }
        $reason = sanitize_textarea_field(wp_unslash((string)($_POST['correction_reason'] ?? '')));
        if (trim($reason) === '') {
            wp_die('A anulação exige uma justificação. Abre a submissão em Editar e usa a secção “Anular submissão”.');
        }
        $result = SubmissionCorrectionService::void($id, $reason, get_current_user_id());
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()));
        $args = ['page'=>'routespro-form-submissions', 'voided'=>1];
        if (!empty($result['warning'])) $args['warning'] = sanitize_text_field((string)$result['warning']);
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /** @return array|\WP_Error */
    private static function build_admin_correction_answers(array $submission) {
        global $wpdb;
        $form = FormsModule::get_form((int)($submission['form_id'] ?? 0));
        $schema = FormsModule::decode_schema((string)($form['schema_json'] ?? ''));
        if (class_exists(EvidenceFlow::class)) $schema = EvidenceFlow::expand_schema($schema);
        $questions = !empty($schema['questions']) && is_array($schema['questions']) ? $schema['questions'] : [];
        $out = [];
        $known = [];

        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            if ($key === '') continue;
            $type = sanitize_key((string)($question['type'] ?? 'text')) ?: 'text';
            if (in_array($type, ['briefing_block','briefing_intro'], true)) continue;
            $known[$key] = true;
            $question['type'] = $type;
            $raw = self::extract_posted_answer($key, $type);
            $semantic = class_exists(AnswerSemantics::class) ? AnswerSemantics::normalize($question, $raw, true) : ['valid'=>true,'value'=>$raw];
            if (empty($semantic['valid'])) {
                return new \WP_Error('fieldflow_admin_answer_invalid', sanitize_text_field((string)($semantic['message'] ?? 'Resposta inválida.')) . ' [' . $key . ']');
            }
            $out[$key] = [
                'label'=>sanitize_text_field((string)($question['label'] ?? $key)),
                'type'=>$type,
                'value'=>$semantic['value'] ?? $raw,
            ];
        }

        $posted = is_array($_POST['answers'] ?? null) ? (array)$_POST['answers'] : [];
        $extraKeys = [];
        foreach (array_keys($posted) as $key) {
            $key = sanitize_key((string)$key);
            if ($key !== '' && empty($known[$key])) $extraKeys[$key] = true;
        }
        $metaByKey = [];
        if ($extraKeys) {
            $table = ContextQuestionService::table();
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
                $keys = array_keys($extraKeys);
                $ph = implode(',', array_fill(0, count($keys), '%s'));
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT question_key,question_label,question_type,question_options_json FROM {$table} WHERE question_key IN ({$ph}) ORDER BY id DESC",
                    ...$keys
                ), ARRAY_A) ?: [];
                foreach ($rows as $row) {
                    $key = sanitize_key((string)($row['question_key'] ?? ''));
                    if ($key !== '' && !isset($metaByKey[$key])) $metaByKey[$key] = $row;
                }
            }
        }
        foreach (array_keys($extraKeys) as $key) {
            $meta = $metaByKey[$key] ?? [];
            $type = sanitize_key((string)($meta['question_type'] ?? 'text')) ?: 'text';
            $options = json_decode((string)($meta['question_options_json'] ?? ''), true);
            $question = [
                'key'=>$key,
                'label'=>sanitize_text_field((string)($meta['question_label'] ?? $key)),
                'type'=>$type,
                'options'=>is_array($options) ? $options : [],
            ];
            $raw = self::extract_posted_answer($key, $type);
            $semantic = class_exists(AnswerSemantics::class) ? AnswerSemantics::normalize($question, $raw, true) : ['valid'=>true,'value'=>$raw];
            if (empty($semantic['valid'])) {
                return new \WP_Error('fieldflow_admin_answer_invalid', sanitize_text_field((string)($semantic['message'] ?? 'Resposta inválida.')) . ' [' . $key . ']');
            }
            $out[$key] = [
                'label'=>$question['label'],
                'type'=>$type,
                'value'=>$semantic['value'] ?? $raw,
            ];
        }
        return $out;
    }

    private static function render_correction_history(array $submission): void {
        $meta = json_decode((string)($submission['meta_json'] ?? ''), true);
        $history = is_array($meta) && is_array($meta['admin_correction_history'] ?? null) ? array_values($meta['admin_correction_history']) : [];
        echo '<section style="max-width:1000px;margin:18px 0;padding:14px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px">';
        echo '<h2 style="margin:0 0 10px">Histórico de correções</h2>';
        if (!$history) {
            echo '<p style="margin:0;color:#64748b">Ainda não existem correções administrativas nesta submissão.</p></section>';
            return;
        }
        $history = array_reverse(array_slice($history, -12));
        echo '<table class="widefat striped"><thead><tr><th>Data</th><th>Ação</th><th>Utilizador</th><th>Estado</th><th>Campos</th><th>Motivo</th></tr></thead><tbody>';
        foreach ($history as $entry) {
            if (!is_array($entry)) continue;
            $changed = array_values(array_filter(array_map('sanitize_key', (array)($entry['changed_keys'] ?? []))));
            $before = self::submission_status_label(sanitize_key((string)($entry['status_before'] ?? '')));
            $after = self::submission_status_label(sanitize_key((string)($entry['status_after'] ?? '')));
            $actor = absint($entry['corrected_by'] ?? 0);
            $user = $actor > 0 ? get_userdata($actor) : null;
            echo '<tr><td>' . esc_html(self::format_submission_date((string)($entry['corrected_at'] ?? ''))) . '</td>';
            echo '<td>' . esc_html(sanitize_key((string)($entry['action'] ?? 'correct')) === 'void' ? 'Anulação' : 'Correção') . '</td>';
            echo '<td>' . esc_html($user ? $user->display_name : ($actor > 0 ? ('User #' . $actor) : 'Sistema')) . '</td>';
            echo '<td>' . esc_html($before . ' → ' . $after) . '</td>';
            echo '<td>' . esc_html($changed ? implode(', ', $changed) : 'Só estado') . '</td>';
            echo '<td>' . esc_html((string)($entry['reason'] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table></section>';
    }

    private static function enrich_answers_with_question_types(array $answers, int $formId): array {
        global $wpdb;
        static $schemaMaps = [];
        static $contextTypes = [];
        static $contextTableReady = null;

        if (!isset($schemaMaps[$formId])) {
            $map = [];
            if ($formId > 0) {
                $form = FormsModule::get_form($formId);
                $schema = FormsModule::decode_schema((string)($form['schema_json'] ?? ''));
                if (class_exists(EvidenceFlow::class)) $schema = EvidenceFlow::expand_schema($schema);
                foreach ((array)($schema['questions'] ?? []) as $question) {
                    if (!is_array($question)) continue;
                    $key = sanitize_key((string)($question['key'] ?? ''));
                    if ($key === '') continue;
                    $map[$key] = sanitize_key((string)($question['evidence_flow_role'] ?? '')) === 'answer'
                        ? 'evidence_check'
                        : (sanitize_key((string)($question['type'] ?? 'text')) ?: 'text');
                }
            }
            $schemaMaps[$formId] = $map;
        }

        $missing = [];
        foreach ($answers as $answer) {
            $key = sanitize_key((string)($answer['question_key'] ?? ''));
            if ($key !== '' && empty($schemaMaps[$formId][$key]) && !array_key_exists($key, $contextTypes)) $missing[$key] = true;
        }
        if ($missing) {
            $table = ContextQuestionService::table();
            if ($contextTableReady === null) $contextTableReady = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
            if ($contextTableReady) {
                $keys = array_keys($missing);
                $placeholders = implode(',', array_fill(0, count($keys), '%s'));
                $rows = $wpdb->get_results($wpdb->prepare("SELECT question_key,question_type FROM {$table} WHERE question_key IN ({$placeholders}) ORDER BY id DESC", ...$keys), ARRAY_A) ?: [];
                foreach ($rows as $row) {
                    $key = sanitize_key((string)($row['question_key'] ?? ''));
                    if ($key !== '' && !isset($contextTypes[$key])) $contextTypes[$key] = sanitize_key((string)($row['question_type'] ?? 'text')) ?: 'text';
                }
            }
            foreach (array_keys($missing) as $key) if (!array_key_exists($key, $contextTypes)) $contextTypes[$key] = '';
        }

        foreach ($answers as &$answer) {
            $key = sanitize_key((string)($answer['question_key'] ?? ''));
            $type = $schemaMaps[$formId][$key] ?? ($contextTypes[$key] ?? '');
            if ($type === '') $type = self::infer_answer_question_type($answer);
            $answer['question_type'] = sanitize_key((string)$type) ?: 'text';
        }
        unset($answer);
        return $answers;
    }

    private static function infer_answer_question_type(array $answer): string {
        $key = sanitize_key((string)($answer['question_key'] ?? ''));
        if (class_exists(EvidenceFlow::class)) {
            if (EvidenceFlow::is_photo_key($key)) return 'image_upload';
            if (EvidenceFlow::is_reason_key($key)) return 'textarea';
        }
        $json = trim((string)($answer['value_json'] ?? ''));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $first = reset($decoded);
                if (is_array($first)) {
                    if (array_key_exists('pr_fronts', $first) || array_key_exists('competitor_fronts', $first) || array_key_exists('pr_share', $first)) return 'share_space_matrix';
                    if (array_key_exists('order_qty', $first) || array_key_exists('boxes', $first) || array_key_exists('units_total', $first)) return 'order_suggestion';
                    if (array_key_exists('qty', $first) || array_key_exists('before', $first) || array_key_exists('after', $first) || array_key_exists('ref', $first)) return 'product_matrix';
                }
                return 'json';
            }
        }
        if (isset($answer['value_number']) && $answer['value_number'] !== null && $answer['value_number'] !== '') return 'number';
        $text = trim((string)($answer['value_text'] ?? ''));
        if ($text !== '' && preg_match('#^https?://#i', $text)) return 'file_upload';
        return 'text';
    }


    private static function render_submission_kpi(string $label, $value): void {
        echo '<div class="ff-submission-kpi"><span>' . esc_html($label) . '</span><strong>' . esc_html((string)$value) . '</strong></div>';
    }

    private static function render_submission_badges(array $summary): string {
        $badges = [];
        $answers = (int)($summary['answers'] ?? 0);
        $briefingQuestions = (int)($summary['briefing_questions'] ?? 0);
        $briefingAnswered = (int)($summary['briefing_answered'] ?? 0);
        $products = (int)($summary['product_rows'] ?? 0);
        $zeros = (int)($summary['zero_products'] ?? 0);
        $orders = (int)($summary['orders'] ?? 0);
        $files = (int)($summary['files'] ?? 0);
        $comparisons = (int)($summary['comparisons'] ?? 0);
        $comparisonRisks = (int)($summary['comparisons_below_target'] ?? 0);
        if ($answers) $badges[] = '<span class="ff-badge">' . $answers . ' respostas</span>';
        if ($briefingQuestions) $badges[] = '<span class="ff-badge good">' . $briefingQuestions . ' briefing · ' . $briefingAnswered . ' respondidas</span>';
        if ($products) $badges[] = '<span class="ff-badge good">' . $products . ' referências</span>';
        if ($zeros) $badges[] = '<span class="ff-badge alert">' . $zeros . ' ruturas</span>';
        if ($orders) $badges[] = '<span class="ff-badge alert">' . $orders . ' encomendas</span>';
        if ($comparisons) $badges[] = '<span class="ff-badge ' . ($comparisonRisks ? 'alert' : 'good') . '">' . $comparisons . ' comparativos' . ($comparisonRisks ? ' · ' . $comparisonRisks . ' abaixo' : '') . '</span>';
        if ($files) $badges[] = '<span class="ff-badge file">' . $files . ' evidências</span>';
        if (!$badges) $badges[] = '<span class="ff-badge">Sem conteúdo</span>';
        return '<div class="ff-badges">' . implode('', $badges) . '</div>';
    }

    private static function render_submission_detail(array $presentation): string {
        ob_start();
        $hasContent = false;
        $briefingQuestions = (array)($presentation['briefing_questions'] ?? []);
        if ($briefingQuestions) {
            $hasContent = true;
            echo '<section class="ff-report-section"><h4>Briefing da loja</h4><div class="ff-meta">Apenas perguntas mapeadas para esta loja no âmbito operacional aplicável.</div><div class="ff-answer-grid">';
            foreach ($briefingQuestions as $question) {
                $value = !empty($question['answered']) ? (string)($question['answer'] ?? '0') : 'Sem resposta';
                echo '<div class="ff-answer-card"><strong>' . esc_html((string)($question['label'] ?? 'Pergunta de briefing')) . '</strong><div>' . nl2br(esc_html($value)) . '</div><div class="ff-meta">' . esc_html((string)($question['briefing_title'] ?? 'Briefing')) . '</div></div>';
            }
            echo '</div></section>';
        }
        $answers = (array)($presentation['answers'] ?? []);
        if ($answers) {
            $hasContent = true;
            echo '<section class="ff-report-section"><h4>Respostas do formulário</h4><div class="ff-answer-grid">';
            foreach ($answers as $answer) {
                echo '<div class="ff-answer-card"><strong>' . esc_html((string)($answer['label'] ?? 'Resposta')) . '</strong><div>' . nl2br(esc_html((string)($answer['value'] ?? ''))) . '</div></div>';
            }
            echo '</div></section>';
        }

        foreach ((array)($presentation['comparison_questions'] ?? []) as $question) {
            $rows = (array)($question['rows'] ?? []);
            if (!$rows) continue;
            $hasContent = true;
            echo '<section class="ff-report-section"><h4>' . esc_html((string)($question['label'] ?? 'Comparativo')) . ' <span class="ff-meta">' . esc_html((string)($question['type_label'] ?? 'Comparativo automático')) . '</span></h4>';
            echo '<table class="ff-mini-table"><thead><tr><th>Comparação</th><th>Referência A</th><th>Frentes A</th><th>Quota A</th><th>Referência B</th><th>Frentes B</th><th>Quota B</th><th>Objetivo</th><th>Desvio</th><th>Estado</th></tr></thead><tbody>';
            foreach ($rows as $comparison) {
                echo '<tr><td>' . esc_html((string)($comparison['metric'] ?? '')) . '</td><td>' . esc_html((string)($comparison['reference_a'] ?? '')) . '</td><td>' . esc_html((string)($comparison['fronts_a'] ?? '')) . '</td><td>' . esc_html((string)($comparison['share_a'] ?? '')) . '%</td><td>' . esc_html((string)($comparison['reference_b'] ?? '')) . '</td><td>' . esc_html((string)($comparison['fronts_b'] ?? '')) . '</td><td>' . esc_html((string)($comparison['share_b'] ?? '')) . '%</td><td>' . esc_html((string)($comparison['target'] ?? '')) . '%</td><td>' . esc_html((string)($comparison['gap'] ?? '')) . ' pp</td><td>' . esc_html((string)($comparison['status_label'] ?? '')) . '</td></tr>';
            }
            echo '</tbody></table></section>';
        }

        foreach ((array)($presentation['product_questions'] ?? []) as $question) {
            $categories = (array)($question['categories'] ?? []);
            if (!$categories) continue;
            $hasContent = true;
            $rows = (array)($question['rows'] ?? []);
            $zero = 0; $positive = 0; $changed = 0;
            foreach ($rows as $product) {
                if (($product['status'] ?? '') === 'zero') $zero++;
                elseif (($product['status'] ?? '') === 'positive') $positive++;
                if (!empty($product['changed'])) $changed++;
            }
            echo '<section class="ff-report-section"><h4>' . esc_html((string)($question['label'] ?? 'Produtos')) . ' <span class="ff-meta">' . count($rows) . ' referências · ' . $zero . ' ruturas · ' . $positive . ' com presença' . ($changed ? ' · ' . $changed . ' alterações' : '') . '</span></h4>';
            foreach ($categories as $category) {
                echo '<details class="ff-category-detail"><summary>' . esc_html((string)($category['name'] ?? 'Sem categoria')) . ' · ' . (int)($category['count'] ?? 0) . ' referências · ' . (int)($category['zero'] ?? 0) . ' a zero</summary>';
                echo '<table class="ff-mini-table"><thead><tr><th>Referência</th><th>Produto</th><th>Valor</th><th>Estado</th></tr></thead><tbody>';
                foreach ((array)($category['rows'] ?? []) as $product) {
                    $before = (string)($product['before'] ?? '');
                    $after = (string)($product['after'] ?? '');
                    $value = ($before !== '' || $after !== '') ? trim($before . ' → ' . $after, ' →') : (string)($product['value'] ?? $product['qty'] ?? '');
                    $isZero = ($product['status'] ?? '') === 'zero';
                    $status = $isZero ? 'Rutura / zero' : (($product['status'] ?? '') === 'positive' ? 'Com presença' : 'Sem classificação');
                    echo '<tr><td>' . esc_html((string)($product['ref'] ?? '')) . '</td><td>' . esc_html((string)($product['name'] ?? 'Produto')) . '</td><td class="' . ($isZero ? 'ff-zero' : '') . '">' . esc_html($value !== '' ? $value : 'Sem valor') . '</td><td>' . esc_html($status) . (!empty($product['changed']) ? ' · Alterado' : '') . '</td></tr>';
                }
                echo '</tbody></table></details>';
            }
            echo '</section>';
        }

        foreach ((array)($presentation['order_questions'] ?? []) as $question) {
            $categories = (array)($question['categories'] ?? []);
            if (!$categories) continue;
            $hasContent = true;
            echo '<section class="ff-report-section"><h4>' . esc_html((string)($question['label'] ?? 'Sugestão de encomenda')) . ' <span class="ff-meta">Apenas referências com quantidade ou observação</span></h4>';
            foreach ($categories as $category) {
                echo '<details class="ff-category-detail"><summary>' . esc_html((string)($category['name'] ?? 'Sem categoria')) . ' · ' . (int)($category['count'] ?? 0) . ' referências · ' . esc_html((string)($category['boxes'] ?? 0)) . ' caixas</summary>';
                echo '<table class="ff-mini-table"><thead><tr><th>Referência</th><th>Produto</th><th>Caixas</th><th>Unidades</th><th>Observações</th></tr></thead><tbody>';
                foreach ((array)($category['rows'] ?? []) as $order) {
                    echo '<tr><td>' . esc_html((string)($order['ref'] ?? '')) . '</td><td>' . esc_html((string)($order['name'] ?? 'Produto')) . '</td><td>' . esc_html((string)($order['order_qty'] ?? '')) . '</td><td>' . esc_html((string)($order['units_total'] ?? '')) . '</td><td>' . esc_html((string)($order['notes'] ?? '')) . '</td></tr>';
                }
                echo '</tbody></table></details>';
            }
            echo '</section>';
        }

        $files = (array)($presentation['files'] ?? []);
        if ($files) {
            $hasContent = true;
            echo '<section class="ff-report-section"><h4>Evidências e ficheiros</h4><div class="ff-badges">';
            foreach ($files as $file) echo '<a class="button button-small" href="' . esc_url((string)($file['url'] ?? '')) . '" target="_blank" rel="noopener">' . esc_html((string)($file['label'] ?? 'Abrir ficheiro')) . '</a>';
            echo '</div></section>';
        }

        if (!$hasContent) echo '<p class="ff-meta">Esta submissão não tem respostas preenchidas.</p>';
        return (string)ob_get_clean();
    }

    private static function render_submission_pagination(array $filters, int $currentPage, int $totalPages, int $perPage, int $total): void {
        echo '<div class="ff-pagination"><div class="ff-meta">' . (int)$total . ' submissões · Página ' . (int)$currentPage . ' de ' . (int)$totalPages . '</div><div class="ff-pagination-links">';
        $baseArgs = ['page'=>'routespro-form-submissions','per_page'=>$perPage];
        foreach (['client_id','project_id','owner_user_id','route_id','location_id','date_from','date_to'] as $key) if (!empty($filters[$key])) $baseArgs[$key] = $filters[$key];
        if ($currentPage > 1) echo '<a class="button" href="' . esc_url(add_query_arg($baseArgs + ['submission_page'=>$currentPage - 1], admin_url('admin.php'))) . '">Anterior</a>';
        $start = max(1, $currentPage - 2); $end = min($totalPages, $currentPage + 2);
        for ($page = $start; $page <= $end; $page++) {
            $class = $page === $currentPage ? 'button button-primary' : 'button';
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url(add_query_arg($baseArgs + ['submission_page'=>$page], admin_url('admin.php'))) . '">' . (int)$page . '</a>';
        }
        if ($currentPage < $totalPages) echo '<a class="button" href="' . esc_url(add_query_arg($baseArgs + ['submission_page'=>$currentPage + 1], admin_url('admin.php'))) . '">Seguinte</a>';
        echo '</div></div>';
    }

    private static function format_submission_date(string $date): string {
        if ($date === '') return 'Sem data';
        $timestamp = strtotime($date);
        return $timestamp ? wp_date('d/m/Y H:i', $timestamp) : $date;
    }

    private static function submission_status_label(string $status): string {
        $labels = ['submitted'=>'Submetida','reviewed'=>'Revista','approved'=>'Aprovada','rejected'=>'Rejeitada','draft'=>'Rascunho'];
        return $labels[$status] ?? ($status !== '' ? ucfirst($status) : 'Sem estado');
    }

    private static function stop_status_label(string $status): string {
        $labels = ['done'=>'Concluída','completed'=>'Concluída','pending'=>'Pendente','failed'=>'Falhou','in_progress'=>'Em curso'];
        return $labels[$status] ?? ($status !== '' ? ucfirst($status) : 'Sem estado');
    }

    private static function render_filters(array $filters, array $clients, array $projects, array $owners) {
        $url = admin_url('admin.php');
        echo '<form method="get" action="' . esc_url($url) . '" style="margin:16px 0 18px;padding:14px 16px;background:#fff;border:1px solid #e2e8f0;border-radius:12px">';
        echo '<input type="hidden" name="page" value="routespro-form-submissions">';
        echo '<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">';
        echo '<p style="margin:0"><label for="routespro_filter_client"><strong>Cliente</strong></label><br><select id="routespro_filter_client" name="client_id"><option value="0">Todos</option>';
        foreach ($clients as $client) echo '<option value="' . (int)$client['id'] . '"' . selected($filters['client_id'], (int)$client['id'], false) . '>' . esc_html($client['name']) . '</option>';
        echo '</select></p>';
        echo '<p style="margin:0"><label for="routespro_filter_project"><strong>Campanha / Projeto</strong></label><br><select id="routespro_filter_project" name="project_id"><option value="0">Todas</option>';
        foreach ($projects as $project) echo '<option value="' . (int)$project['id'] . '"' . selected($filters['project_id'], (int)$project['id'], false) . '>' . esc_html($project['name']) . '</option>';
        echo '</select></p>';
        echo '<p style="margin:0"><label for="routespro_filter_owner"><strong>Owner</strong></label><br><select id="routespro_filter_owner" name="owner_user_id"><option value="0">Todos</option>';
        foreach ($owners as $owner) echo '<option value="' . (int)$owner['id'] . '"' . selected($filters['owner_user_id'], (int)$owner['id'], false) . '>' . esc_html($owner['name']) . '</option>';
        echo '</select></p>';
        echo '<p style="margin:0"><label for="routespro_filter_date_from"><strong>De</strong></label><br><input type="date" id="routespro_filter_date_from" name="date_from" value="' . esc_attr($filters['date_from'] ?? '') . '"></p>';
        echo '<p style="margin:0"><label for="routespro_filter_date_to"><strong>Até</strong></label><br><input type="date" id="routespro_filter_date_to" name="date_to" value="' . esc_attr($filters['date_to'] ?? '') . '"></p>';
        echo '<p style="margin:0"><label for="routespro_filter_per_page"><strong>Por página</strong></label><br><select id="routespro_filter_per_page" name="per_page">';
        foreach ([10,25,50,100] as $size) echo '<option value="' . $size . '"' . selected((int)($filters['per_page'] ?? 25), $size, false) . '>' . $size . '</option>';
        echo '</select></p>';
        echo '<p style="margin:0"><button class="button button-primary">Filtrar</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=routespro-form-submissions')) . '">Limpar</a></p>';
        echo '</div>';
        echo '</form>';
    }

    private static function render_route_cell(array $row): string {
        $route_id = (int)($row['route_id'] ?? $row['joined_route_id'] ?? 0);
        if (!$route_id) return 'Sem rota';
        $parts = ['#' . $route_id];
        if (!empty($row['route_date'])) $parts[] = esc_html($row['route_date']);
        if (!empty($row['route_status'])) $parts[] = esc_html($row['route_status']);
        return implode('<br>', $parts);
    }

    private static function render_stop_cell(array $row): string {
        $stop_id = (int)($row['route_stop_id'] ?? $row['stop_row_id'] ?? 0);
        if (!$stop_id) return 'Sem paragem';
        $parts = ['#' . $stop_id];
        if (!empty($row['location_name'])) $parts[] = esc_html($row['location_name']);
        if (!empty($row['stop_status'])) $parts[] = esc_html($row['stop_status']);
        return implode('<br>', $parts);
    }

    private static function render_answer_input(string $key, string $type, ?array $answer, array $question) {
        $field_name = 'answers[' . $key . ']';
        $id = 'routespro_answer_' . $key;
        $value = self::answer_raw_value($answer, $type);
        $options = [];
        if ($type === 'visual_choice' && !empty($question['visual_options']) && is_array($question['visual_options'])) {
            foreach ($question['visual_options'] as $option) {
                if (is_scalar($option)) $option_value = sanitize_text_field((string)$option);
                elseif (is_array($option)) $option_value = sanitize_text_field((string)($option['value'] ?? $option['label'] ?? ''));
                else $option_value = '';
                if ($option_value !== '') $options[] = $option_value;
            }
            $options = array_values(array_unique($options));
        } elseif (!empty($question['options']) && is_array($question['options'])) {
            $options = array_values(array_filter(array_map('sanitize_text_field', $question['options'])));
        }
        if (in_array($type, ['textarea'], true)) {
            echo '<textarea class="large-text" rows="5" id="' . esc_attr($id) . '" name="' . esc_attr($field_name) . '">' . esc_textarea(is_array($value) ? wp_json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value) . '</textarea>';
            return;
        }
        if (in_array($type, ['select','radio','visual_choice'], true) && $options) {
            $multiple = self::question_allows_multiple_selection($question, $type);
            if ($multiple) {
                $selected_values = is_array($value) ? array_map('strval', $value) : (($value !== '') ? [(string)$value] : []);
                echo '<div style="display:grid;gap:8px;max-width:680px">';
                foreach ($options as $index => $option) {
                    $choice_id = $id . '_' . $index;
                    echo '<label for="' . esc_attr($choice_id) . '" style="display:flex;align-items:center;gap:9px;padding:9px 11px;border:1px solid #dcdcde;border-radius:8px;background:#fff"><input type="checkbox" id="' . esc_attr($choice_id) . '" name="' . esc_attr($field_name) . '[]" value="' . esc_attr($option) . '"' . checked(in_array((string)$option, $selected_values, true), true, false) . '> ' . esc_html($option) . '</label>';
                }
                echo '</div>';
            } else {
                echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($field_name) . '"><option value="">Selecione</option>';
                foreach ($options as $option) echo '<option value="' . esc_attr($option) . '"' . selected((string)$value, (string)$option, false) . '>' . esc_html($option) . '</option>';
                echo '</select>';
            }
            return;
        }
        if ($type === 'checkbox') {
            echo '<label><input type="hidden" name="' . esc_attr($field_name) . '" value="0"><input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($field_name) . '" value="1"' . checked(!empty($value), true, false) . '> Marcado</label>';
            return;
        }
        if (in_array($type, ['number','currency','percent'], true)) {
            echo '<input type="number" step="0.01" class="regular-text" id="' . esc_attr($id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr((string)$value) . '">';
            return;
        }
        if (in_array($type, ['date','time'], true)) {
            echo '<input type="' . esc_attr($type) . '" class="regular-text" id="' . esc_attr($id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr((string)$value) . '">';
            return;
        }

        if ($type === 'categorized_image_upload') {
            $rows = self::normalise_categorized_evidence_answer($value);
            echo '<input type="hidden" name="' . esc_attr($field_name) . '" value="' . esc_attr(wp_json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '">';
            if (!$rows) {
                echo '<p>Sem imagens por categoria.</p>';
                return;
            }
            echo '<div style="display:grid;gap:12px;max-width:860px">';
            foreach ($rows as $row) {
                $label = sanitize_text_field((string)($row['category_label'] ?? $row['category_key'] ?? 'Categoria'));
                $images = is_array($row['images'] ?? null) ? $row['images'] : [];
                echo '<section style="border:1px solid #dbe3ee;border-radius:12px;padding:12px;background:#fff"><strong>' . esc_html($label) . '</strong><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:9px">';
                foreach ($images as $image) {
                    $url = esc_url((string)($image['url'] ?? ''));
                    if ($url === '') continue;
                    echo '<a href="' . $url . '" target="_blank" rel="noopener" style="display:block;width:96px;height:96px;border:1px solid #dbe3ee;border-radius:10px;overflow:hidden;background:#f8fafc"><img src="' . $url . '" alt="" style="width:100%;height:100%;object-fit:cover;display:block"></a>';
                }
                echo '</div></section>';
            }
            echo '</div><p class="description">As imagens desta pergunta são geridas no reporte original para preservar a associação entre categoria e evidência.</p>';
            return;
        }
        if (in_array($type, ['image_upload','file_upload'], true)) {
            if (!empty($value)) echo '<p><a href="' . esc_url((string)$value) . '" target="_blank" rel="noopener">Ver ficheiro atual</a></p>';
            echo '<input type="url" class="large-text" id="' . esc_attr($id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr((string)$value) . '" placeholder="https://">';
            return;
        }
        echo '<input type="text" class="regular-text" id="' . esc_attr($id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr(is_array($value) ? wp_json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value) . '">';
    }

    private static function question_allows_multiple_selection(array $question, string $type): bool {
        if (!in_array($type, ['select', 'radio', 'visual_choice'], true)) return false;
        return sanitize_key((string)($question['selection_mode'] ?? '')) === 'multiple' || !empty($question['multiple']);
    }

    private static function extract_posted_answer(string $key, string $type) {
        $raw = $_POST['answers'][$key] ?? null;
        if ($type === 'checkbox') return !empty($raw) ? 1 : 0;
        if (is_array($raw)) return array_map('sanitize_text_field', wp_unslash($raw));
        $raw = is_string($raw) ? wp_unslash($raw) : $raw;
        switch ($type) {
            case 'number':
            case 'currency':
            case 'percent':
                return ($raw === '' || $raw === null) ? '' : (float) str_replace(',', '.', (string) $raw);
            case 'textarea':
                return sanitize_textarea_field((string) $raw);
            case 'categorized_image_upload':
                $decoded = json_decode((string)$raw, true);
                return self::normalise_categorized_evidence_answer(is_array($decoded) ? $decoded : []);
            case 'product_matrix':
            case 'order_suggestion':
            case 'share_space_matrix':
                if (is_array($raw)) return $raw;
                $decoded = json_decode((string)$raw, true);
                return is_array($decoded) ? $decoded : [];
            case 'image_upload':
            case 'file_upload':
                return esc_url_raw((string) $raw);
            default:
                return sanitize_text_field((string) $raw);
        }
    }

    private static function upsert_answer(int $submission_id, string $key, string $label, string $type, $value) {
        global $wpdb;
        $existing = $wpdb->get_row($wpdb->prepare('SELECT id FROM ' . FormsModule::table_answers() . ' WHERE submission_id=%d AND question_key=%s LIMIT 1', $submission_id, $key), ARRAY_A);
        $stored = self::prepare_answer_storage($value, $type);
        $data = [
            'question_label' => $label,
            'value_text' => $stored['text'],
            'value_number' => $stored['number'],
            'value_json' => $stored['json'],
        ];
        $formats = ['%s','%s','%f','%s'];
        if ($existing) {
            $wpdb->update(FormsModule::table_answers(), $data, ['id' => (int)$existing['id']], $formats, ['%d']);
        } else {
            $wpdb->insert(FormsModule::table_answers(), [
                'submission_id' => $submission_id,
                'question_key' => $key,
                'question_label' => $label,
                'value_text' => $stored['text'],
                'value_number' => $stored['number'],
                'value_json' => $stored['json'],
                'created_at' => current_time('mysql'),
            ], ['%d','%s','%s','%s','%f','%s','%s']);
        }
    }

    private static function normalise_categorized_evidence_answer($value): array {
        if (!is_array($value)) return [];
        $is_list = empty($value) || array_keys($value) === range(0, count($value) - 1);
        $rows = $is_list ? $value : [];
        if (!$rows) foreach ($value as $key => $images) $rows[] = ['category_key'=>$key,'category_label'=>$key,'images'=>$images];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $key = sanitize_key((string)($row['category_key'] ?? $row['key'] ?? ''));
            $label = sanitize_text_field((string)($row['category_label'] ?? $row['label'] ?? $key));
            if ($key === '' && $label !== '') $key = sanitize_key(remove_accents($label));
            if ($key === '') continue;
            $images = $row['images'] ?? $row['files'] ?? [];
            if (!is_array($images)) $images = $images ? [$images] : [];
            $clean = [];
            foreach ($images as $image) {
                if (is_string($image)) $image = ['url'=>$image];
                if (!is_array($image)) continue;
                $url = esc_url_raw((string)($image['url'] ?? $image['file_url'] ?? $image['src'] ?? ''));
                if ($url === '') continue;
                $path = (string)wp_parse_url($url, PHP_URL_PATH);
                $clean[] = [
                    'url'=>$url,
                    'name'=>sanitize_file_name((string)($image['name'] ?? ($path !== '' ? basename($path) : 'evidencia'))),
                    'type'=>sanitize_text_field((string)($image['type'] ?? $image['mime_type'] ?? '')),
                    'category_key'=>$key,
                    'category_label'=>$label ?: $key,
                ];
            }
            if ($clean) $out[] = ['category_key'=>$key,'category_label'=>$label ?: $key,'images'=>$clean];
        }
        return $out;
    }

    private static function prepare_answer_storage($value, string $type): array {
        $text = null; $number = null; $json = null;
        if (is_array($value)) {
            $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE);
            $text = $json;
        } elseif (in_array($type, ['number','currency','percent'], true) && $value !== '') {
            $number = (float) $value;
            $text = (string) $value;
        } elseif ($type === 'checkbox') {
            $number = $value ? 1 : 0;
            $text = $value ? '1' : '0';
        } else {
            $text = is_scalar($value) ? (string) $value : wp_json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return ['text' => $text, 'number' => $number, 'json' => $json];
    }

    private static function answer_raw_value(?array $answer, string $type) {
        if (!$answer) return '';
        if (!empty($answer['value_json'])) {
            $decoded = json_decode($answer['value_json'], true);
            if (json_last_error() === JSON_ERROR_NONE) return $decoded;
        }
        if (in_array($type, ['number','currency','percent'], true) && $answer['value_number'] !== null) return $answer['value_number'];
        if ($type === 'checkbox') return !empty($answer['value_number']) || $answer['value_text'] === '1';
        return $answer['value_text'] ?? '';
    }

    private static function order_suggestion_rows_from_answer(array $answer): ?array {
        if (empty($answer['value_json'])) return null;
        $decoded = json_decode((string)$answer['value_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) return null;
        $is_order = false;
        $rows = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) continue;
            if (array_key_exists('order_qty', $row) || array_key_exists('boxes', $row) || array_key_exists('notes', $row) || array_key_exists('observations', $row)) $is_order = true;
            $category = sanitize_text_field((string)($row['category'] ?? $row['categoria'] ?? ''));
            $ref = sanitize_text_field((string)($row['ref'] ?? $row['reference'] ?? $row['code'] ?? ''));
            $name = sanitize_text_field((string)($row['name'] ?? $row['product'] ?? ''));
            $item_type = sanitize_text_field((string)($row['item_type'] ?? $row['type'] ?? ''));
            $capacity = $row['capacity_cl'] ?? '';
            $units_per_case = $row['units_per_case'] ?? '';
            $order_qty = $row['order_qty'] ?? $row['boxes'] ?? '';
            $units_total = $row['units_total'] ?? (($order_qty !== '' && $units_per_case !== '' && is_numeric($order_qty) && is_numeric($units_per_case)) ? (float)$order_qty * (float)$units_per_case : '');
            $notes = sanitize_textarea_field((string)($row['notes'] ?? $row['observations'] ?? ''));
            if ($category === '' && $ref === '' && $name === '' && $order_qty === '' && $notes === '') continue;
            $rows[] = [
                'category'=>$category,'ref'=>$ref,'name'=>$name,'item_type'=>$item_type,
                'capacity_cl'=>$capacity,'units_per_case'=>$units_per_case,'order_qty'=>$order_qty,
                'units_total'=>$units_total,'notes'=>$notes,
            ];
        }
        return $is_order ? $rows : null;
    }

    private static function product_matrix_rows_from_answer(array $answer): ?array {
        if (empty($answer['value_json'])) return null;
        $decoded = json_decode((string)$answer['value_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) return null;
        $is_matrix = false;
        $rows = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) continue;
            if (array_key_exists('qty', $row) || array_key_exists('quantity', $row) || array_key_exists('ref', $row) || array_key_exists('reference', $row)) $is_matrix = true;
            $category = sanitize_text_field((string)($row['category'] ?? $row['categoria'] ?? ''));
            $ref = sanitize_text_field((string)($row['ref'] ?? $row['reference'] ?? ''));
            $name = sanitize_text_field((string)($row['name'] ?? $row['product'] ?? ''));
            $qty = $row['qty'] ?? ($row['after'] ?? ($row['quantity'] ?? ''));
            $before = array_key_exists('before', $row) ? $row['before'] : null;
            $after = array_key_exists('after', $row) ? $row['after'] : null;
            if ($category === '' && $ref === '' && $name === '' && $qty === '' && $before === null && $after === null) continue;
            $out_row = ['category'=>$category,'ref'=>$ref,'name'=>$name,'qty'=>$qty];
            if ($before !== null || $after !== null) { $out_row['before'] = $before; $out_row['after'] = $after !== null ? $after : $qty; }
            $rows[] = $out_row;
        }
        return $is_matrix ? $rows : null;
    }

    private static function answer_to_string(array $answer): string {
        if (!empty($answer['value_json'])) {
            $decoded_evidence = json_decode((string)$answer['value_json'], true);
            $categorized_evidence = self::normalise_categorized_evidence_answer(is_array($decoded_evidence) ? $decoded_evidence : []);
            if ($categorized_evidence) {
                $parts = [];
                foreach ($categorized_evidence as $row) {
                    $count = count((array)($row['images'] ?? []));
                    $parts[] = (string)($row['category_label'] ?? $row['category_key'] ?? 'Categoria') . ': ' . $count . ' imagem' . ($count === 1 ? '' : 's');
                }
                return implode('; ', $parts);
            }
            $orders = self::order_suggestion_rows_from_answer($answer);
            if ($orders !== null) {
                $parts = [];
                foreach ($orders as $row) {
                    $category = trim((string)($row['category'] ?? ''));
                    $label = trim(($category ? $category . ' | ' : '') . ((string)($row['ref'] ?? '') ? (string)$row['ref'] . ' · ' : '') . ((string)($row['name'] ?? '') ?: 'Produto'));
                    $qty = (string)($row['order_qty'] ?? '');
                    $units = (string)($row['units_total'] ?? '');
                    $notes = trim((string)($row['notes'] ?? ''));
                    $detail = $qty !== '' ? $qty . ' cx' : 'sem caixas';
                    if ($units !== '') $detail .= ' / ' . $units . ' un';
                    if ($notes !== '') $detail .= ' / ' . $notes;
                    $parts[] = $label . ': ' . $detail;
                }
                return implode('; ', $parts);
            }
            $matrix = self::product_matrix_rows_from_answer($answer);
            if ($matrix !== null) {
                $parts = [];
                foreach ($matrix as $row) {
                    $category = trim((string)($row['category'] ?? $row['categoria'] ?? ''));
                    $label = trim(($category ? $category . ' | ' : '') . ((string)($row['ref'] ?? '') ? (string)$row['ref'] . ' · ' : '') . ((string)($row['name'] ?? '') ?: 'Produto'));
                    $qty = (string)($row['qty'] ?? '');
                    $before = array_key_exists('before', $row) ? (string)$row['before'] : '';
                    $after = array_key_exists('after', $row) ? (string)$row['after'] : $qty;
                    if ($label !== '' && ($before !== '' || $after !== '')) $parts[] = $label . ': ' . ($before !== '' ? $before . ' > ' : '') . $after;
                }
                return implode('; ', $parts);
            }
            $decoded = json_decode($answer['value_json'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decoded)) return implode(', ', array_map('strval', $decoded));
                return (string) $decoded;
            }
            return (string) $answer['value_json'];
        }
        if ($answer['value_number'] !== null && $answer['value_text'] === null) return (string) $answer['value_number'];
        return (string) ($answer['value_text'] ?? '');
    }
}
