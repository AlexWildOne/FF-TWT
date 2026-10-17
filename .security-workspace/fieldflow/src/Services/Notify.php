<?php
namespace RoutesPro\Services;

class Notify {
    private static function build_route_context(array $route, array $client, ?array $project, string $assignee_name, string $assignee_email, int $stops, string $changes, string $stops_list_html, array $exception = [], array $progress = []): array {
        $routeId = (int)($route['id'] ?? 0);
        return [
            '{route_id}'     => (string) $routeId,
            '{date}'         => (string) ($route['date'] ?? ''),
            '{client_name}'  => (string) ($client['name'] ?? ''),
            '{project_name}' => (string) ($project['name'] ?? ''),
            '{user_name}'    => (string) $assignee_name,
            '{user_email}'   => (string) $assignee_email,
            '{client_email}' => (string) ($client['email'] ?? ''),
            '{stops}'        => (string) $stops,
            '{changes}'      => (string) $changes,
            '{route_status}' => (string) ($route['status'] ?? ''),
            '{route_url}'    => GoogleMapsUrlService::forRouteId($routeId),
            '{stops_list}'   => $stops_list_html,
            '{exception_reason}' => (string)($exception['reason_label'] ?? $exception['status'] ?? ''),
            '{exception_note}' => (string)($exception['note'] ?? ''),
            '{completed_stops}' => (string)($progress['completed'] ?? 0),
            '{pending_stops}' => (string)($progress['pending'] ?? 0),
            '{completion_percent}' => (string)($progress['percent'] ?? 0),
            '{exception_reported_at}' => (string)($exception['updated_at'] ?? current_time('mysql')),
        ];
    }

    private static function collect_stops_list(int $route_id): string {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT rs.seq, rs.status, rs.planned_arrival, l.name, l.city
             FROM {$px}route_stops rs
             LEFT JOIN {$px}locations l ON l.id = rs.location_id
             WHERE rs.route_id=%d
             ORDER BY rs.seq ASC, rs.id ASC
             LIMIT 25",
            $route_id
        ), ARRAY_A) ?: [];
        if (!$rows) return '';
        $html = '<ul style="margin:0;padding-left:18px">';
        foreach ($rows as $row) {
            $parts = [];
            $parts[] = esc_html((string)($row['name'] ?: 'Paragem'));
            if (!empty($row['city'])) $parts[] = esc_html((string)$row['city']);
            if (!empty($row['planned_arrival'])) {
                $parts[] = esc_html(mysql2date('H:i', (string)$row['planned_arrival'], false));
            }
            if (!empty($row['status'])) $parts[] = esc_html((string)$row['status']);
            $html .= '<li>'.implode(' · ', array_filter($parts)).'</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    private static function route_progress(int $route_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'routespro_route_stops';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS total, SUM(CASE WHEN status IN ('done','completed','executed') THEN 1 ELSE 0 END) AS completed FROM {$table} WHERE route_id=%d",
            $route_id
        ), ARRAY_A) ?: [];
        $total = max(0, (int)($row['total'] ?? 0));
        $completed = max(0, min($total, (int)($row['completed'] ?? 0)));
        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => max(0, $total - $completed),
            'percent' => $total > 0 ? (int)round(($completed / $total) * 100) : 0,
        ];
    }

    private static function route_intelligence_digest(array $route): string {
        $routeId = absint($route['id'] ?? 0);
        $date = sanitize_text_field((string)($route['date'] ?? ''));
        if (!$routeId || !$date) return '';
        try {
            $req = new \WP_REST_Request('GET', '/routespro/v1/stats');
            foreach ([
                'from'=>$date,
                'to'=>$date,
                'client_id'=>absint($route['client_id'] ?? 0),
                'project_id'=>absint($route['project_id'] ?? 0),
                'route_id'=>$routeId,
                'analytics_audience'=>'client',
                'report_export'=>1,
            ] as $key => $value) if ($value !== '' && $value !== 0) $req->set_param($key, $value);
            $response = (new \RoutesPro\Rest\StatsController())->stats($req);
            if (is_wp_error($response)) return '';
            $data = $response instanceof \WP_REST_Response ? $response->get_data() : (is_array($response) ? $response : []);
            if (isset($data['data']) && is_array($data['data']) && !isset($data['total_routes'])) $data = $data['data'];
            if (!is_array($data)) return '';
            $filters = [
                'from'=>$date,'to'=>$date,
                'client_id'=>absint($route['client_id'] ?? 0),
                'project_id'=>absint($route['project_id'] ?? 0),
                'route_id'=>$routeId,'user_id'=>0,'location_id'=>0,
                'audience'=>'client','self_mode'=>false,
            ];
            $context = ['period'=>$date];
            $ri = \RoutesPro\Analytics\ReportIntelligenceService::build($data, $filters, $context);
            $scores = (array)($ri['scores'] ?? []);
            $scoreParts = [];
            foreach (['execution'=>'Execução','risk'=>'Risco','opportunity'=>'Oportunidade'] as $key => $label) {
                $score = $scores[$key] ?? null;
                if (!is_array($score)) continue;
                $value = $score['score'] ?? null;
                $scoreParts[] = '<td class="ff-route-score-cell" width="50%" valign="top" style="width:50%;max-width:50%;padding:6px;box-sizing:border-box"><div style="max-width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;box-sizing:border-box;overflow:hidden;word-break:break-word;overflow-wrap:anywhere"><div style="font-size:11px;color:#64748b;font-weight:700">' . esc_html($label) . '</div><div style="font-size:19px;font-weight:800;color:#0f172a">' . esc_html($value === null ? 'n.d.' : number_format((float)$value, 1, ',', '.') . '/100') . '</div></div></td>';
            }
            $alerts = array_slice((array)($ri['store_alerts'] ?? []), 0, 5);
            $html = '<div style="margin:20px 0;max-width:100%;padding:15px;border:1px solid #bfdbfe;border-radius:14px;background:#f8fbff;box-sizing:border-box;overflow:hidden;word-break:break-word;overflow-wrap:anywhere"><div style="font-size:12px;color:#1d4ed8;font-weight:800;text-transform:uppercase">Resumo Analytics da rota</div>';
            if ($scoreParts) {
                $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;table-layout:fixed;margin:8px 0;border-collapse:collapse">';
                foreach (array_chunk($scoreParts, 2) as $scoreRow) $html .= '<tr>' . implode('', $scoreRow) . '</tr>';
                $html .= '</table>';
            }
            if ($alerts) {
                $html .= '<div style="font-weight:800;margin-top:8px">Alertas por loja</div><ul style="margin:7px 0 0;padding-left:18px">';
                foreach ($alerts as $alert) {
                    if (!is_array($alert)) continue;
                    $html .= '<li style="margin:5px 0"><strong>' . esc_html((string)($alert['location_name'] ?? 'PDV')) . '</strong>, ' . esc_html((string)($alert['detail'] ?? '')) . '</li>';
                }
                $html .= '</ul>';
            } else {
                $html .= '<div style="margin-top:8px;color:#166534">Sem alertas prioritários por loja nesta rota.</div>';
            }
            $recommendations = array_slice((array)($ri['recommendations'] ?? []), 0, 3);
            if ($recommendations) {
                $html .= '<div style="font-weight:800;margin-top:10px">Ações recomendadas</div><ul style="margin:7px 0 0;padding-left:18px">';
                foreach ($recommendations as $recommendation) {
                    if (!is_array($recommendation)) continue;
                    $html .= '<li style="margin:5px 0"><strong>' . esc_html((string)($recommendation['title'] ?? 'Recomendação FieldFlow')) . '</strong>, ' . esc_html((string)($recommendation['suggested_action'] ?? 'Rever no Intelligence Center.')) . '<br><small style="color:#64748b">conf. ' . esc_html(number_format((float)($recommendation['adaptive_confidence_score'] ?? $recommendation['confidence_score'] ?? 0),1,',','.')) . '/100 · rank ' . esc_html(number_format((float)($recommendation['adaptive_rank_score'] ?? 0),1,',','.')) . '/100</small></li>';
                }
                $html .= '</ul><div style="margin-top:6px;font-size:11px;color:#64748b">Recomendações explicáveis, sujeitas a decisão humana.</div>';
            }
            return $html . '</div>';
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function route_event($route_id, $type='updated', $changes=''){
        $opts = \RoutesPro\Admin\Emails::get();
        if (($type==='completed' && empty($opts['on_completed'])) || ($type==='updated' && empty($opts['on_updated'])) || ($type==='exception' && empty($opts['on_exception']))) {
            return ['sent'=>false,'skipped'=>'disabled','recipients'=>[]];
        }

        global $wpdb;
        $px = $wpdb->prefix.'routespro_';
        $route = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d", $route_id), ARRAY_A);
        if (!$route) return ['sent'=>false,'skipped'=>'route_not_found','recipients'=>[]];

        // Mail Center 2.0 owns the completed-day communication whenever a
        // Daily Close automation is active for this exact client/project/date.
        // This prevents the legacy route-completed email and the Intelligence
        // daily-close email from being delivered twice.
        if ($type === 'completed'
            && class_exists('\RoutesPro\Admin\ReportAutomations')
            && \RoutesPro\Admin\ReportAutomations::hasActiveDailyClose(
                (int)($route['client_id'] ?? 0),
                (int)($route['project_id'] ?? 0),
                (string)($route['date'] ?? '')
            )) {
            return ['sent'=>false,'skipped'=>'mail_center_daily_close','recipients'=>[]];
        }

        $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}clients WHERE id=%d", $route['client_id']), ARRAY_A) ?: [];
        $project = !empty($route['project_id'])
            ? ($wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}projects WHERE id=%d", $route['project_id']), ARRAY_A) ?: [])
            : [];

        $assignee_email = '';
        $assignee_name = '';
        $uid = intval($route['owner_user_id'] ?? 0);
        if ($uid) {
            $u = get_userdata($uid);
            if ($u) {
                $assignee_email = (string) $u->user_email;
                $assignee_name = (string) $u->display_name;
            }
        }
        if (!$assignee_email) {
            $uid2 = (int)$wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$px}assignments WHERE route_id=%d ORDER BY id ASC LIMIT 1", $route_id));
            if ($uid2) {
                $u2 = get_userdata($uid2);
                if ($u2) {
                    $assignee_email = (string) $u2->user_email;
                    $assignee_name = (string) $u2->display_name;
                }
            }
        }

        $stops = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$px}route_stops WHERE route_id=%d", $route_id));
        $stopsListHtml = self::collect_stops_list((int)$route_id);
        $routeMeta = json_decode((string)($route['meta_json'] ?? ''), true);
        if (!is_array($routeMeta)) $routeMeta = [];
        $exception = is_array($routeMeta['route_exception'] ?? null) ? $routeMeta['route_exception'] : [];
        $progress = self::route_progress((int)$route_id);
        $context = self::build_route_context($route, $client, $project, $assignee_name, $assignee_email, $stops, (string)$changes, $stopsListHtml, $exception, $progress);

        $tpl = \RoutesPro\Admin\Emails::get_project_template((int)($route['project_id'] ?? 0), $opts);
        $subjectTpl = $type==='completed' ? ($tpl['subject_completed'] ?? '') : ($type==='exception' ? ($tpl['subject_exception'] ?? '') : ($tpl['subject_updated'] ?? ''));
        $bodyTpl    = $type==='completed' ? ($tpl['body_completed'] ?? '')    : ($type==='exception' ? ($tpl['body_exception'] ?? '') : ($tpl['body_updated'] ?? ''));
        $subject = \RoutesPro\Admin\Emails::apply_placeholders((string)$subjectTpl, $context);
        $body    = \RoutesPro\Admin\Emails::apply_placeholders((string)$bodyTpl, $context);
        if (!empty($opts['send_as_html']) && in_array($type, ['completed','exception'], true)) {
            $body .= self::route_intelligence_digest($route);
        }

        $to = [];
        $sendToClient = $type === 'exception' ? !empty($opts['exception_to_client']) : !empty($opts['to_client']);
        $sendToCollaborator = $type === 'exception' ? !empty($opts['exception_to_collaborator']) : !empty($opts['to_collaborator']);
        $extraEmails = $type === 'exception' ? (string)($opts['exception_extra_emails'] ?? '') : (string)($opts['extra_emails'] ?? '');
        if ($sendToClient && !empty($client['email'])) $to[] = (string)$client['email'];
        if ($sendToCollaborator && !empty($assignee_email)) $to[] = (string)$assignee_email;
        if ($extraEmails !== '') {
            foreach (explode(',', $extraEmails) as $e) {
                $e = sanitize_email(trim($e));
                if ($e) $to[] = $e;
            }
        }
        $to = array_values(array_unique(array_filter($to)));
        if (!$to) return ['sent'=>false,'skipped'=>'no_recipients','recipients'=>[],'subject'=>$subject];

        $headers = [];
        if (!empty($opts['send_as_html'])) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }
        $fromEmail = sanitize_email((string)($opts['from_email'] ?? ''));
        $fromName  = wp_specialchars_decode((string)($opts['from_name'] ?? ''), ENT_QUOTES);
        if ($fromEmail) {
            $headers[] = 'From: '.($fromName ?: get_bloginfo('name')).' <'.$fromEmail.'>';
        }
        $replyTo = sanitize_email((string)($opts['reply_to'] ?? ''));
        if ($replyTo) {
            $headers[] = 'Reply-To: '.$replyTo;
        }

        $message = !empty($opts['send_as_html'])
            ? \RoutesPro\Admin\Emails::render_message_html($subject, $body, [
                'brand_primary' => $opts['brand_primary'] ?? '',
                'footer_text'   => $opts['footer_text'] ?? '',
                'button_label'  => $opts['button_label'] ?? 'Abrir rota',
                'route_url'     => ($type === 'exception' && empty($opts['exception_include_route_link'])) ? '' : ($context['{route_url}'] ?? ''),
            ])
            : wp_strip_all_tags($body);

        // One transport envelope per recipient preserves privacy, individual retry and recipient-level idempotency.
        $acceptedRecipients = [];
        $failedRecipients = [];
        $deliveredRecipients = [];
        $queuedRecipients = [];
        foreach ($to as $recipientEmail) {
            $dispatchContext = [
                'source'=>'route_notification',
                'email_type'=>'route_' . sanitize_key((string)$type),
                'route_id'=>$route_id,
                'entity_type'=>'route',
                'entity_id'=>$route_id,
                'client_id'=>(int)($route['client_id']??0),
                'project_id'=>(int)($route['project_id']??0),
                'message_kind'=>$type,
            ];
            if ($type === 'completed') {
                $dispatchContext['idempotency_key'] = 'route_notification|completed|' . $route_id . '|' . (string)($route['date'] ?? '') . '|' . strtolower((string)$recipientEmail);
            }
            $mailDispatch = \RoutesPro\Services\MailDispatchService::dispatch((string)$recipientEmail, $subject, $message, $headers, [], $dispatchContext);
            $accepted = !empty($mailDispatch['accepted']);
            $delivered = !empty($mailDispatch['sent']);
            $mailResult = $delivered ? 'sent' : ($accepted ? 'queued' : 'failed');
            if ($accepted) {
                $acceptedRecipients[] = (string)$recipientEmail;
                if ($delivered) $deliveredRecipients[] = (string)$recipientEmail;
                else $queuedRecipients[] = (string)$recipientEmail;
            } else {
                $failedRecipients[] = ['email'=>(string)$recipientEmail,'error'=>(string)($mailDispatch['error'] ?? '')];
            }
            \RoutesPro\Admin\Emails::log_email([
                'email_type' => 'route_notification',
                'context_key' => $type,
                'client_id' => (int) ($route['client_id'] ?? 0),
                'project_id' => (int) ($route['project_id'] ?? 0),
                'route_id' => (int) $route_id,
                'sender_user_id' => get_current_user_id(),
                'recipient_user_id' => ($recipientEmail === $assignee_email && !empty($uid)) ? (int) $uid : 0,
                'recipient_email' => (string) $recipientEmail,
                'recipient_name' => ($recipientEmail === $assignee_email ? (string) $assignee_name : ''),
                'message_kind' => $type,
                'subject' => $subject,
                'body' => $body,
                'meta' => [
                    'route_status' => (string) ($route['status'] ?? ''),
                    'stops' => $stops,
                    'to_client' => ((string)$recipientEmail === (string)($client['email'] ?? '')),
                    'exception' => $type === 'exception' ? $exception : [],
                    'progress' => $progress,
                    'mail_dispatch_id' => (string)($mailDispatch['dispatch_id'] ?? ''),
                    'outbox_id' => (int)($mailDispatch['outbox_id'] ?? 0),
                    'mail_error' => (string)($mailDispatch['error'] ?? ''),
                ],
                'mail_result' => $mailResult,
            ]);
        }
        $sent = !empty($acceptedRecipients);
        return [
            'sent' => (bool)$sent,
            'recipients' => $to,
            'accepted_recipients' => $acceptedRecipients,
            'delivered_recipients' => $deliveredRecipients,
            'queued_recipients' => $queuedRecipients,
            'failed_recipients' => $failedRecipients,
            'subject' => $subject,
            'type' => $type,
        ];
    }
}
