<?php
namespace RoutesPro\Admin;

class Emails {
    const OPT_KEY = 'routespro_emails';

    private static function defaults() {
        return [
            'on_completed'       => 1,
            'on_updated'         => 0,
            'on_exception'       => 1,
            'to_client'          => 1,
            'to_collaborator'    => 1,
            'extra_emails'       => '',
            'exception_to_client'       => 1,
            'exception_to_collaborator' => 0,
            'exception_extra_emails'    => '',
            'exception_include_route_link' => 0,
            'subject_completed'  => '[FieldFlow] Rota #{route_id} concluída ({date})',
            'body_completed'     => "Olá,\n\nA rota #{route_id} do cliente {client_name} foi CONCLUÍDA em {date}.\nResponsável: {user_name}.\nProjeto: {project_name}\nTotal de paragens: {stops}.\n\n{stops_list}\n\nVer rota: {route_url}\n\nCumprimentos,\nRoutesPro",
            'subject_updated'    => '[FieldFlow] Rota #{route_id} atualizada ({date})',
            'body_updated'       => "Olá,\n\nA rota #{route_id} do cliente {client_name} foi ATUALIZADA em {date}.\nResponsável: {user_name}.\nProjeto: {project_name}\nAlterações: {changes}.\n\n{stops_list}\n\nVer rota: {route_url}\n\nCumprimentos,\nRoutesPro",
            'subject_exception'  => '[FieldFlow] Ocorrência na rota #{route_id}, {client_name}, {date}',
            'body_exception'     => "Olá,\n\nInformamos que foi registada uma ocorrência operacional na rota #{route_id}, prevista para {date}.\n\nCliente: {client_name}\nProjeto: {project_name}\nMerchandiser: {user_name}\nMotivo: {exception_reason}\nExecução: {completed_stops} de {stops} lojas, {completion_percent}%\n\nNota operacional:\n{exception_note}\n\nLojas ainda por executar: {pending_stops}.\n\nA equipa FieldFlow está a acompanhar a situação e atualizará o planeamento quando aplicável.\n\nCumprimentos,\nFieldFlow",
            'project_templates'  => [],
            'from_name'          => 'FieldFlow',
            'from_email'         => '',
            'reply_to'           => '',
            'brand_primary'      => '#111827',
            'button_label'       => 'Abrir rota',
            'footer_text'        => 'Notificação automática gerada pelo FieldFlow.',
            'send_as_html'       => 1,
        ];
    }

    public static function get() {
        $saved = get_option(self::OPT_KEY, []);
        $opts  = array_merge(self::defaults(), is_array($saved) ? $saved : []);
        if (empty($opts['project_templates']) || !is_array($opts['project_templates'])) {
            $opts['project_templates'] = [];
        }
        if ((string)($opts['from_name'] ?? '') === 'RoutesPro') $opts['from_name'] = 'FieldFlow';
        if ((string)($opts['footer_text'] ?? '') === 'Notificação automática gerada pelo RoutesPro.') $opts['footer_text'] = 'Notificação automática gerada pelo FieldFlow.';
        return $opts;
    }

    public static function template_tokens(): array {
        return [
            '{route_id}'     => 'ID da rota',
            '{date}'         => 'Data da rota',
            '{client_name}'  => 'Nome do cliente',
            '{project_name}' => 'Nome do projeto ou campanha',
            '{user_name}'    => 'Responsável principal',
            '{user_email}'   => 'Email do responsável principal',
            '{client_email}' => 'Email do cliente',
            '{stops}'        => 'Número total de paragens',
            '{changes}'      => 'Resumo da alteração',
            '{route_status}' => 'Estado atual da rota',
            '{route_url}'    => 'Link público da rota no Google Maps',
            '{stops_list}'   => 'Lista formatada de paragens',
            '{exception_reason}' => 'Motivo da ocorrência de rota',
            '{exception_note}' => 'Nota operacional da ocorrência',
            '{completed_stops}' => 'Número de lojas executadas',
            '{pending_stops}' => 'Número de lojas por executar',
            '{completion_percent}' => 'Percentagem de execução da rota',
            '{exception_reported_at}' => 'Data e hora do registo da ocorrência',
        ];
    }

    public static function get_project_template(int $project_id, array $opts = []): array {
        $opts = $opts ?: self::get();
        $tpl = [
            'subject_completed' => (string)($opts['subject_completed'] ?? ''),
            'body_completed'    => (string)($opts['body_completed'] ?? ''),
            'subject_updated'   => (string)($opts['subject_updated'] ?? ''),
            'body_updated'      => (string)($opts['body_updated'] ?? ''),
            'subject_exception' => (string)($opts['subject_exception'] ?? ''),
            'body_exception'    => (string)($opts['body_exception'] ?? ''),
        ];
        if ($project_id && !empty($opts['project_templates'][$project_id]) && is_array($opts['project_templates'][$project_id])) {
            $ov = $opts['project_templates'][$project_id];
            foreach ($tpl as $key => $value) {
                if (!empty($ov[$key])) $tpl[$key] = (string) $ov[$key];
            }
        }
        return $tpl;
    }

    public static function apply_placeholders(string $content, array $context): string {
        $replace = [];
        foreach ($context as $key => $value) {
            $replace[$key] = is_scalar($value) ? (string) $value : '';
        }
        return strtr($content, $replace);
    }

    public static function format_body_html(string $body): string {
        $body = trim((string) $body);
        if ($body === '') return '';
        if (preg_match('/<\/?(?:p|div|table|ul|ol|li|br|strong|em|a|h[1-6])\b/i', $body)) {
            return wp_kses_post($body);
        }
        return wpautop(esc_html($body));
    }

    /**
     * CSS comum aos emails FieldFlow. O contrato e deliberadamente conservador:
     * width=100% inline primeiro, max-width apenas como melhoria progressiva e
     * tabelas de dados sem largura intrinseca capaz de sair do viewport mobile.
     */
    /**
     * Normaliza HTML editavel sem alterar o template guardado. O objetivo e
     * impedir que width fixo ou nowrap herdado de um template antigo force a
     * mensagem para fora do viewport quando o cliente ignora CSS responsivo.
     */
    public static function harden_email_content_html(string $html): string {
        if ($html === '') return '';
        $html = (string)preg_replace_callback('/<table\b([^>]*)>/i', static function(array $match): string {
            $attrs = (string)($match[1] ?? '');
            $attrs = (string)preg_replace('/\swidth\s*=\s*(["\']).*?\1/i', '', $attrs);
            if (preg_match('/\sstyle\s*=\s*(["\'])(.*?)\1/is', $attrs, $styleMatch)) {
                $style = rtrim((string)$styleMatch[2], " ;\t\r\n") . ';width:100%;max-width:100%;box-sizing:border-box;';
                $replacement = ' style=' . $styleMatch[1] . $style . $styleMatch[1];
                $attrs = (string)preg_replace('/\sstyle\s*=\s*(["\'])(.*?)\1/is', $replacement, $attrs, 1);
            } else {
                $attrs .= ' style="width:100%;max-width:100%;box-sizing:border-box;"';
            }
            return '<table width="100%"' . $attrs . '>';
        }, $html);
        $html = (string)preg_replace_callback('/<(td|th)\b([^>]*)>/i', static function(array $match): string {
            $tag = strtolower((string)$match[1]);
            $attrs = (string)($match[2] ?? '');
            $safe = 'white-space:normal;max-width:100%;overflow-wrap:anywhere;word-break:break-word;box-sizing:border-box;';
            if (preg_match('/\sstyle\s*=\s*(["\'])(.*?)\1/is', $attrs, $styleMatch)) {
                $style = rtrim((string)$styleMatch[2], " ;\t\r\n") . ';' . $safe;
                $replacement = ' style=' . $styleMatch[1] . $style . $styleMatch[1];
                $attrs = (string)preg_replace('/\sstyle\s*=\s*(["\'])(.*?)\1/is', $replacement, $attrs, 1);
            } else {
                $attrs .= ' style="' . $safe . '"';
            }
            return '<' . $tag . $attrs . '>';
        }, $html);
        $html = (string)preg_replace_callback('/<img\b([^>]*)>/i', static function(array $match): string {
            $attrs = (string)($match[1] ?? '');
            $safe = 'max-width:100%;height:auto;box-sizing:border-box;';
            if (preg_match('/\sstyle\s*=\s*(["\'])(.*?)\1/is', $attrs, $styleMatch)) {
                $style = rtrim((string)$styleMatch[2], " ;\t\r\n") . ';' . $safe;
                $replacement = ' style=' . $styleMatch[1] . $style . $styleMatch[1];
                $attrs = (string)preg_replace('/\sstyle\s*=\s*(["\'])(.*?)\1/is', $replacement, $attrs, 1);
            } else {
                $attrs .= ' style="' . $safe . '"';
            }
            return '<img' . $attrs . '>';
        }, $html);
        return $html;
    }

    public static function responsive_email_css(): string {
        return 'html,body{margin:0!important;padding:0!important;width:100%!important;}'
            . '.ff-email-viewport{width:100%!important;max-width:100%!important;}'
            . '.ff-email-shell,.ff-news-wrap{width:100%!important;box-sizing:border-box!important;}'
            . '.ff-email-shell{max-width:680px!important;}.ff-news-wrap{max-width:640px!important;}'
            . '.ff-email-content{box-sizing:border-box!important;max-width:100%!important;overflow-wrap:anywhere!important;word-break:break-word!important;}'
            . '.ff-email-content table{max-width:100%!important;box-sizing:border-box!important;}'
            . '.ff-email-content td,.ff-email-content th{max-width:100%!important;white-space:normal!important;overflow-wrap:anywhere!important;word-break:break-word!important;box-sizing:border-box!important;}'
            . '.ff-email-content img{max-width:100%!important;height:auto!important;}'
            . '.ff-data-table{width:100%!important;max-width:100%!important;table-layout:fixed!important;border-collapse:collapse!important;box-sizing:border-box!important;}'
            . '.ff-data-table th,.ff-data-table td{box-sizing:border-box!important;max-width:100%!important;white-space:normal!important;overflow-wrap:anywhere!important;word-break:break-word!important;}'
            . '@media only screen and (max-width:660px){'
            . '.ff-email-outer-pad{padding:0!important;}'
            . '.ff-email-shell,.ff-news-wrap{width:100%!important;max-width:100%!important;border-radius:0!important;}'
            . '.ff-email-pad,.ff-news-pad{padding-left:14px!important;padding-right:14px!important;}'
            . '.ff-email-content,.ff-news-content{padding:18px 14px!important;}'
            . '.ff-route-score-cell,.ff-score-cell,.ff-scope-cell{display:block!important;width:100%!important;max-width:100%!important;padding:4px 0!important;box-sizing:border-box!important;}'
            . '.ff-metric-label,.ff-metric-value{display:block!important;width:100%!important;max-width:100%!important;text-align:left!important;box-sizing:border-box!important;}'
            . '.ff-metric-value{padding-top:0!important;}'
            . '.ff-news-stat{display:block!important;width:100%!important;padding:0 0 8px 0!important;box-sizing:border-box!important;}'
            . '.ff-order-summary-cell{padding:3px!important;}'
            . '.ff-price-head-type{display:none!important;}.ff-price-type{display:block!important;}'
            . '}';
    }

    public static function render_message_html(string $subject, string $body, array $opts = []): string {
        $primary = sanitize_hex_color($opts['brand_primary'] ?? '') ?: '#111827';
        $footer  = trim((string)($opts['footer_text'] ?? ''));
        $button  = trim((string)($opts['button_label'] ?? 'Abrir rota'));
        $routeUrl = esc_url((string)($opts['route_url'] ?? ''));
        $logoUrl = defined('ROUTESPRO_URL') ? ROUTESPRO_URL . 'assets/logo-twt.png' : '';
        $bodyHtml = self::harden_email_content_html(self::format_body_html($body));
        $buttonHtml = '';
        if ($routeUrl !== '') {
            $buttonHtml = '<p style="margin:24px 0 8px"><a href="'.$routeUrl.'" style="display:inline-block;background:'.$primary.';color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:600">'.esc_html($button).'</a></p>';
        }
        $css = self::responsive_email_css();
        return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="x-apple-disable-message-reformatting"><meta name="format-detection" content="telephone=no,date=no,address=no,email=no">'
            . '<!--[if mso]><style type="text/css">body,table,td,p,a,span{font-family:Arial,Helvetica,sans-serif!important}table{border-collapse:collapse!important}</style><![endif]-->'
            . '<style>'.$css.'</style></head><body class="ff-email-body" style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif;color:#111827;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%">'
            . '<table class="ff-email-viewport" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f3f4f6" style="width:100%;max-width:100%;border-collapse:collapse;background:#f3f4f6"><tr><td class="ff-email-outer-pad" align="center" style="padding:24px 12px">'
            . '<!--[if mso]><table role="presentation" width="680" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->'
            . '<table class="ff-email-shell" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:100%;max-width:680px;background:#ffffff;border-collapse:collapse;border:1px solid #e5e7eb">'
            . '<tr><td class="ff-email-pad" bgcolor="'.$primary.'" style="padding:20px 24px;background:'.$primary.';color:#ffffff;overflow-wrap:anywhere;word-break:break-word">'
            . ($logoUrl ? '<img src="'.esc_url($logoUrl).'" alt="FieldFlow" height="34" style="display:block;height:34px;max-width:220px;width:auto;margin:0 0 14px;border:0">' : '')
            . '<div style="font-size:20px;font-weight:700;line-height:1.3;overflow-wrap:anywhere;word-break:break-word">'.esc_html($subject).'</div></td></tr>'
            . '<tr><td class="ff-email-content" style="padding:18px 14px;box-sizing:border-box;max-width:100%;overflow-wrap:anywhere;word-break:break-word">'.$bodyHtml.$buttonHtml.'</td></tr>'
            . '<tr><td class="ff-email-pad" style="padding:16px 24px;background:#f9fafb;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px;overflow-wrap:anywhere;word-break:break-word">'.esc_html($footer).'</td></tr>'
            . '</table><!--[if mso]></td></tr></table><![endif]--></td></tr></table></body></html>';
    }

    /**
     * Table-first newsletter shell intended for Outlook desktop as well as
     * modern web/mobile mail clients. The width attribute remains fluid so a
     * client that ignores media queries still cannot create a 640px viewport.
     */
    public static function render_newsletter_html(string $subject, string $body, array $opts = []): string {
        $primary = sanitize_hex_color($opts['brand_primary'] ?? '') ?: '#172033';
        $footer = trim((string)($opts['footer_text'] ?? ''));
        $eyebrow = trim((string)($opts['eyebrow'] ?? 'FieldFlow'));
        $preheader = trim((string)($opts['preheader'] ?? $subject));
        $logoUrl = defined('ROUTESPRO_URL') ? ROUTESPRO_URL . 'assets/logo-twt.png' : '';
        $bodyHtml = self::harden_email_content_html(self::format_body_html($body));
        $stats = array_values(array_filter((array)($opts['stats'] ?? []), static function($row): bool {
            return is_array($row) && trim((string)($row['label'] ?? '')) !== '';
        }));
        $stats = array_slice($stats, 0, 3);
        $statsHtml = '';
        if ($stats) {
            $width = (int)floor(100 / count($stats));
            $statsHtml = '<tr><td class="ff-news-pad" style="padding:0 24px 22px 24px;background:#ffffff"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:100%;table-layout:fixed;border-collapse:collapse"><tr>';
            foreach ($stats as $index => $stat) {
                $pad = $index === 0 ? '0 6px 0 0' : ($index === count($stats) - 1 ? '0 0 0 6px' : '0 6px');
                $statsHtml .= '<td class="ff-news-stat" width="' . $width . '%" valign="top" style="width:' . $width . '%;padding:' . $pad . ';box-sizing:border-box;overflow-wrap:anywhere;word-break:break-word">'
                    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:100%;table-layout:fixed;border-collapse:collapse;background:#f8fafc;border:1px solid #e2e8f0"><tr><td style="padding:12px 13px;overflow-wrap:anywhere;word-break:break-word">'
                    . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:10px;line-height:14px;letter-spacing:.08em;text-transform:uppercase;color:#64748b;font-weight:700;overflow-wrap:anywhere;word-break:break-word">' . esc_html((string)$stat['label']) . '</div>'
                    . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:20px;line-height:26px;color:#0f172a;font-weight:700;margin-top:2px;overflow-wrap:anywhere;word-break:break-word">' . esc_html((string)($stat['value'] ?? '')) . '</div>'
                    . '</td></tr></table></td>';
            }
            $statsHtml .= '</tr></table></td></tr>';
        }
        $safeSubject = esc_html($subject);
        $safeEyebrow = esc_html($eyebrow);
        $safePreheader = esc_html(wp_strip_all_tags($preheader));
        $safeFooter = esc_html($footer);
        $css = self::responsive_email_css();
        return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="x-apple-disable-message-reformatting"><meta name="format-detection" content="telephone=no,date=no,address=no,email=no">'
            . '<!--[if mso]><style type="text/css">body,table,td,p,a,span{font-family:Arial,Helvetica,sans-serif!important}table{border-collapse:collapse!important}</style><![endif]-->'
            . '<style>'.$css.'</style></head>'
            . '<body class="ff-email-body" style="margin:0;padding:0;background:#eef2f7;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%">'
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all">' . $safePreheader . '</div>'
            . '<table class="ff-email-viewport" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#eef2f7" style="width:100%;max-width:100%;background:#eef2f7;border-collapse:collapse"><tr><td class="ff-email-outer-pad" align="center" style="padding:28px 12px">'
            . '<!--[if mso]><table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->'
            . '<table class="ff-news-wrap" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:100%;max-width:640px;background:#ffffff;border-collapse:collapse;border:1px solid #dbe3ec;box-sizing:border-box">'
            . '<tr><td class="ff-news-pad" style="padding:18px 24px;background:#ffffff;border-bottom:1px solid #e2e8f0"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:100%;table-layout:fixed;border-collapse:collapse"><tr><td valign="middle" style="overflow-wrap:anywhere;word-break:break-word">'
            . ($logoUrl ? '<img src="' . esc_url($logoUrl) . '" alt="The Wild Theory" width="118" style="display:block;width:118px;max-width:100%;height:auto;border:0;outline:none;text-decoration:none">' : '<strong style="font-family:Arial,Helvetica,sans-serif;color:#0f172a">FieldFlow</strong>')
            . '</td><td align="right" valign="middle" style="font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:16px;letter-spacing:.10em;text-transform:uppercase;color:#64748b;font-weight:700;overflow-wrap:anywhere;word-break:break-word">Shopping &amp; Preços</td></tr></table></td></tr>'
            . '<tr><td class="ff-news-pad" bgcolor="' . esc_attr($primary) . '" style="padding:24px;background:' . esc_attr($primary) . ';color:#ffffff;overflow-wrap:anywhere;word-break:break-word">'
            . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:16px;letter-spacing:.12em;text-transform:uppercase;color:#ffffff;font-weight:700;opacity:.82;overflow-wrap:anywhere;word-break:break-word">' . $safeEyebrow . '</div>'
            . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:31px;color:#ffffff;font-weight:700;margin-top:7px;overflow-wrap:anywhere;word-break:break-word">' . $safeSubject . '</div></td></tr>'
            . $statsHtml
            . '<tr><td class="ff-email-content ff-news-content" style="padding:24px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:21px;color:#334155;max-width:100%;overflow-wrap:anywhere;word-break:break-word">' . $bodyHtml . '</td></tr>'
            . '<tr><td class="ff-news-pad" style="padding:16px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:17px;color:#64748b;overflow-wrap:anywhere;word-break:break-word">' . $safeFooter . '</td></tr>'
            . '</table><!--[if mso]></td></tr></table><![endif]--></td></tr></table></body></html>';
    }

    private static function count_overrides(array $opts): int {
        $n = 0;
        foreach ((array)($opts['project_templates'] ?? []) as $row) {
            if (is_array($row) && array_filter($row)) $n++;
        }
        return $n;
    }

    private static function build_demo_context(int $client_id = 0, int $project_id = 0): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $client_name = $client_id ? (string) $wpdb->get_var($wpdb->prepare("SELECT name FROM {$px}clients WHERE id=%d", $client_id)) : 'Cliente Demo';
        $client_email = $client_id ? (string) $wpdb->get_var($wpdb->prepare("SELECT email FROM {$px}clients WHERE id=%d", $client_id)) : 'cliente@example.com';
        $project_name = $project_id ? (string) $wpdb->get_var($wpdb->prepare("SELECT name FROM {$px}projects WHERE id=%d", $project_id)) : 'Campanha Demo';
        $user = wp_get_current_user();
        return [
            '{route_id}'     => '123',
            '{date}'         => date_i18n('Y-m-d'),
            '{client_name}'  => $client_name ?: 'Cliente Demo',
            '{project_name}' => $project_name ?: 'Campanha Demo',
            '{user_name}'    => $user && $user->exists() ? (string) $user->display_name : 'Utilizador Demo',
            '{user_email}'   => $user && $user->exists() ? (string) $user->user_email : 'user@example.com',
            '{client_email}' => $client_email ?: 'cliente@example.com',
            '{stops}'        => '5',
            '{changes}'      => 'status: draft para planned, ordem otimizada, owner revisto',
            '{route_status}' => 'planned',
            '{route_url}'    => 'https://www.google.com/maps/dir/?api=1&travelmode=driving&dir_action=navigate&origin=Lisboa&destination=Lisboa',
            '{stops_list}'   => '<ul style="margin:0;padding-left:18px"><li>Pingo Doce Alvalade, Check-in 09:00</li><li>Continente Roma, Check-in 10:10</li><li>Auchan Campo Grande, Check-in 11:35</li><li>Intermarché Lumiar, Check-in 14:15</li><li>Minipreço Telheiras, Check-in 16:00</li></ul>',
            '{exception_reason}' => 'Rota não concluída',
            '{exception_note}' => 'O merchandiser teve uma indisponibilidade operacional e não conseguiu executar as duas últimas lojas. Será proposta uma nova data.',
            '{completed_stops}' => '3',
            '{pending_stops}' => '2',
            '{completion_percent}' => '60',
            '{exception_reported_at}' => date_i18n('d/m/Y H:i'),
        ];
    }

    public static function render(){
        if (!current_user_can('routespro_manage')) return;
        $tab = sanitize_key((string)($_GET['mail_center_tab'] ?? 'overview'));
        if (!in_array($tab, ['overview','automations','manual','test','outbox','advanced'], true)) $tab = 'overview';
        $overview = class_exists('\\RoutesPro\\Admin\\ReportAutomations') ? ReportAutomations::overviewData() : [];
        $base = admin_url('admin.php?page=routespro-emails');
        ?>
        <div class="wrap routespro-emails-wrap ff-mail-center-v2">
          <style>
            .ff-mail-center-v2 .ff-mc-hero{background:linear-gradient(135deg,#0f172a,#334155);color:#fff;border-radius:22px;padding:24px;margin:8px 0 16px}.ff-mail-center-v2 .ff-mc-hero h1{color:#fff;margin:0 0 6px}.ff-mail-center-v2 .ff-mc-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px}.ff-mail-center-v2 .ff-mc-tabs a{text-decoration:none;padding:10px 14px;border:1px solid #dbe3ee;border-radius:999px;background:#fff;color:#334155;font-weight:700}.ff-mail-center-v2 .ff-mc-tabs a.active{background:#0f172a;color:#fff;border-color:#0f172a}.ff-mail-center-v2 .ff-mc-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.ff-mail-center-v2 .ff-mc-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px}.ff-mail-center-v2 .ff-mc-kpi strong{display:block;font-size:28px}.ff-mail-center-v2 .ff-mc-flow{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:18px}.ff-mail-center-v2 .ff-mc-flow .rp-card{margin:0}.ff-mail-center-v2 .rp-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:18px}.ff-mail-center-v2 .rp-label{font-weight:700;display:block;margin:0 0 6px}.ff-mail-center-v2 input,.ff-mail-center-v2 select,.ff-mail-center-v2 textarea{width:100%;max-width:none}@media(max-width:1100px){.ff-mail-center-v2 .ff-mc-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.ff-mail-center-v2 .ff-mc-flow{grid-template-columns:1fr}}@media(max-width:782px){.ff-mail-center-v2 .ff-mc-kpis{grid-template-columns:1fr}}
          </style>
          <div class="ff-mc-hero"><div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;opacity:.72">Comunicação operacional</div><h1>Mail Center 2.0</h1><p style="margin:0;color:rgba(255,255,255,.82);max-width:920px">Fecho diário, Daily Intelligence, Weekly Intelligence, anexos e testes passam a viver no mesmo cockpit.</p></div>
          <nav class="ff-mc-tabs"><a class="<?php echo $tab==='overview'?'active':''; ?>" href="<?php echo esc_url(add_query_arg('mail_center_tab','overview',$base)); ?>">Visão geral</a><a class="<?php echo $tab==='automations'?'active':''; ?>" href="<?php echo esc_url(add_query_arg('mail_center_tab','automations',$base)); ?>">Automações</a><a class="<?php echo $tab==='manual'?'active':''; ?>" href="<?php echo esc_url(add_query_arg('mail_center_tab','manual',$base)); ?>">Envio manual</a><a class="<?php echo $tab==='test'?'active':''; ?>" href="<?php echo esc_url(add_query_arg('mail_center_tab','test',$base)); ?>">Test Center</a><a class="<?php echo $tab==='outbox'?'active':''; ?>" href="<?php echo esc_url(add_query_arg('mail_center_tab','outbox',$base)); ?>">Outbox</a><a href="<?php echo esc_url(admin_url('admin.php?page=routespro-email-log')); ?>">Histórico</a><a class="<?php echo $tab==='advanced'?'active':''; ?>" href="<?php echo esc_url(add_query_arg('mail_center_tab','advanced',$base)); ?>">Templates e entrega</a></nav>
          <?php if($tab==='overview'): ?>
            <div class="ff-mc-kpis"><div class="ff-mc-kpi"><strong><?php echo (int)($overview['active']??0); ?></strong><span>Automações ativas</span></div><div class="ff-mc-kpi"><strong><?php echo (int)($overview['daily_close']??0); ?></strong><span>Fechos diários</span></div><div class="ff-mc-kpi"><strong><?php echo (int)($overview['weekly']??0); ?></strong><span>Weekly Intelligence</span></div><div class="ff-mc-kpi"><strong><?php echo (int)($overview['failed']??0); ?></strong><span>Com último erro</span></div></div>
            <div class="ff-mc-flow"><div class="rp-card"><h2 style="margin-top:0">1. Fecho diário</h2><p>Quando o merchandiser termina a rota do dia, recebe o seu resumo pessoal. O cliente pode receber imediatamente ou num único consolidado.</p><a class="button button-primary" href="<?php echo esc_url(add_query_arg('mail_center_tab','automations',$base)); ?>">Configurar fecho diário</a></div><div class="rp-card"><h2 style="margin-top:0">2. Weekly Intelligence</h2><p>Mantém o envio semanal com acumulado, alertas, Adaptive, Predictive e anexos Excel, PDF ou CSV.</p><a class="button" href="<?php echo esc_url(add_query_arg('mail_center_tab','automations',$base)); ?>">Gerir automações</a></div><div class="rp-card"><h2 style="margin-top:0">3. Recuperar envios históricos</h2><p>Reenvia manualmente fechos de rota e sugestões já gravadas, para cliente, merchandiser, ambos ou apenas para um email de teste.</p><a class="button" href="<?php echo esc_url(add_query_arg('mail_center_tab','manual',$base)); ?>">Abrir envio manual</a></div><div class="rp-card"><h2 style="margin-top:0">4. Testar antes de ativar</h2><p>Simula cliente ou merchandiser numa data concreta, usando o mesmo motor do envio real e sem contactar destinatários reais.</p><a class="button" href="<?php echo esc_url(add_query_arg('mail_center_tab','test',$base)); ?>">Abrir Test Center</a></div></div>
            <div class="rp-card" style="margin-top:18px"><h2 style="margin-top:0">Estado operacional</h2><div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px"><div><small>Último envio</small><div style="font-weight:800;margin-top:4px"><?php echo !empty($overview['last_run'])?esc_html(wp_date('d/m/Y H:i',strtotime((string)$overview['last_run']))):'Ainda sem envios'; ?></div></div><div><small>Próximo envio agendado</small><div style="font-weight:800;margin-top:4px"><?php echo !empty($overview['next_run'])?esc_html(wp_date('d/m/Y H:i',strtotime((string)$overview['next_run']))):'Sem envio agendado'; ?></div></div></div></div>
          <?php elseif($tab==='automations'): ReportAutomations::renderPanel(); elseif($tab==='manual'): ReportAutomations::renderManualCenter(); elseif($tab==='test'): ReportAutomations::renderTestCenter(); elseif($tab==='outbox'): self::renderOutbox(); elseif($tab==='advanced'): self::renderLegacySettings(); endif; ?>
        </div>
        <?php
    }

    public static function renderOutbox(){
        if (!current_user_can('routespro_manage')) return;
        $service = '\\RoutesPro\\Services\\MailDispatchService';
        $repository = '\\RoutesPro\\Services\\MailOutboxRepository';
        if (!class_exists($service) || !class_exists($repository)) {
            echo '<div class="notice notice-error inline"><p>A camada Mail Outbox não está disponível nesta instalação.</p></div>';
            return;
        }
        if (!empty($_POST['fieldflow_mail_outbox_nonce']) && wp_verify_nonce((string)$_POST['fieldflow_mail_outbox_nonce'], 'fieldflow_mail_outbox_action')) {
            $action = sanitize_key((string)($_POST['outbox_action'] ?? ''));
            $id = absint($_POST['outbox_id'] ?? 0);
            $message = '';
            if ($action === 'enable') { $service::setOutboxEnabled(true); $message = 'Mail Outbox ativada.'; }
            elseif ($action === 'disable') { $service::setOutboxEnabled(false); $message = 'Mail Outbox desativada. Os envios voltam ao transporte direto.'; }
            elseif ($action === 'requeue' && $id > 0) {
                $verified = !empty($_POST['verified_not_delivered']);
                $message = $service::requeue($id, $verified) ? 'Mensagem recolocada na fila com confirmacao de revisao.' : 'Reenvio bloqueado: confirma o estado do transporte e os anexos.';
            }
            elseif ($action === 'cancel' && $id > 0) { $message = $service::cancel($id) ? 'Mensagem cancelada.' : 'Não foi possível cancelar a mensagem.'; }
            if ($message !== '') echo '<div class="notice notice-info inline"><p>'.esc_html($message).'</p></div>';
        }
        $status = $service::status();
        $summary = (array)($status['summary'] ?? []);
        $statusFilter = sanitize_key((string)($_GET['outbox_status'] ?? ''));
        if (!in_array($statusFilter, ['', 'pending','sending','retry','sent','failed','uncertain','cancelled'], true)) $statusFilter = '';
        $sourceFilter = sanitize_key((string)($_GET['outbox_source'] ?? ''));
        $rows = !empty($status['available']) ? $repository::list(['status'=>$statusFilter,'source'=>$sourceFilter], 100) : [];
        $base = admin_url('admin.php?page=routespro-emails&mail_center_tab=outbox');
        $pending = (int)($summary['pending'] ?? 0) + (int)($summary['retry'] ?? 0);
        $dailyCloseHistory = get_option('fieldflow_daily_close_diagnostics_9do', []);
        if (!is_array($dailyCloseHistory)) $dailyCloseHistory = [];
        $dailyCloseHistory = array_reverse(array_slice($dailyCloseHistory, -15));
        global $wpdb;
        $deliveryFailures = $wpdb->get_results("SELECT route_id,recipient_email,meta_json,created_at,mail_result FROM {$wpdb->prefix}routespro_email_logs WHERE route_id>0 AND email_type IN ('scheduled_report','scheduled_report_manual') AND mail_result='failed' ORDER BY id DESC LIMIT 20", ARRAY_A) ?: [];
        ?>
        <div class="rp-card" style="margin-bottom:18px">
          <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap">
            <div><h2 style="margin:0 0 6px">Mail Reliability Outbox</h2><p style="margin:0;max-width:900px;color:#64748b">Fila persistente, idempotência e retry controlado. O estado <strong>Enviado</strong> significa que o transporte WordPress aceitou a mensagem, não confirma entrega na caixa do destinatário.</p></div>
            <form method="post"><?php wp_nonce_field('fieldflow_mail_outbox_action','fieldflow_mail_outbox_nonce'); ?><input type="hidden" name="outbox_action" value="<?php echo !empty($status['enabled'])?'disable':'enable'; ?>"><button class="button <?php echo !empty($status['enabled'])?'':'button-primary'; ?>"><?php echo !empty($status['enabled'])?'Desativar outbox':'Ativar outbox'; ?></button></form>
          </div>
          <div class="ff-mc-kpis" style="margin-top:16px"><div class="ff-mc-kpi"><strong><?php echo $pending; ?></strong><span>Pendentes + retry</span></div><div class="ff-mc-kpi"><strong><?php echo (int)($summary['sending']??0); ?></strong><span>Em processamento</span></div><div class="ff-mc-kpi"><strong><?php echo (int)($summary['failed']??0); ?></strong><span>Falhados</span></div><div class="ff-mc-kpi"><strong><?php echo (int)($summary['uncertain']??0); ?></strong><span>A confirmar no SMTP</span></div><div class="ff-mc-kpi"><strong><?php echo (int)($summary['sent']??0); ?></strong><span>Aceites pelo transporte</span></div></div>
          <?php if((int)($summary['uncertain']??0)>0): ?><div class="notice notice-warning inline" style="margin:16px 0 0"><p><strong><?php echo (int)$summary['uncertain']; ?> mensagem(ns) com resultado incerto.</strong> A 9DO bloqueia repeticoes automaticas. Verifica os registos do fornecedor SMTP antes de autorizar novo envio.</p></div><?php endif; ?>
          <?php if(empty($status['available'])): ?><div class="notice notice-warning inline" style="margin:16px 0 0"><p>A tabela de outbox ainda não existe. A migração de schema 2.2.525 deve ser executada antes de ativar esta camada.</p></div><?php endif; ?>
        </div>
        <div class="rp-card">
          <form method="get" style="display:grid;grid-template-columns:minmax(180px,240px) minmax(220px,1fr) auto;gap:10px;align-items:end;margin-bottom:14px"><input type="hidden" name="page" value="routespro-emails"><input type="hidden" name="mail_center_tab" value="outbox"><div><label class="rp-label">Estado</label><select name="outbox_status"><option value="">Todos</option><?php foreach(['pending'=>'Pendente','sending'=>'A enviar','retry'=>'Retry','sent'=>'Aceite no transporte','failed'=>'Falhado','uncertain'=>'A confirmar','cancelled'=>'Cancelado'] as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($statusFilter,$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div><div><label class="rp-label">Origem</label><input type="text" name="outbox_source" value="<?php echo esc_attr($sourceFilter); ?>" placeholder="ex.: order_suggestion"></div><div><button class="button button-primary">Filtrar</button> <a class="button" href="<?php echo esc_url($base); ?>">Limpar</a></div></form>
          <div style="overflow:auto"><table class="widefat striped"><thead><tr><th>Data</th><th>Origem</th><th>Destinatário</th><th>Assunto</th><th>Estado</th><th>Tentativas</th><th>Próxima</th><th>Último erro</th><th>Ações</th></tr></thead><tbody>
          <?php if(!$rows): ?><tr><td colspan="9">Sem mensagens para estes filtros.</td></tr><?php endif; ?>
          <?php foreach($rows as $row): $to=json_decode((string)($row['to_json']??'[]'),true); if(!is_array($to))$to=[]; $st=sanitize_key((string)($row['status']??'')); ?><tr><td><?php echo esc_html(!empty($row['created_at'])?wp_date('d/m/Y H:i',strtotime((string)$row['created_at'].' UTC')):''); ?></td><td><code><?php echo esc_html((string)$row['source']); ?></code><br><small>#<?php echo (int)$row['id']; ?> · <?php echo esc_html((string)$row['message_id']); ?></small><br><small>Rota #<?php echo (int)($row['route_id']??0); ?> · <?php echo esc_html((string)($row['email_type']??'')); ?></small></td><td><?php echo esc_html(implode(', ',array_map('strval',$to))); ?></td><td><?php echo esc_html((string)$row['subject']); ?></td><td><strong><?php echo esc_html(strtoupper($st)); ?></strong></td><td><?php echo (int)$row['attempt_count']; ?> / <?php echo (int)$row['max_attempts']; ?></td><td><?php echo esc_html(!empty($row['next_attempt_at'])?wp_date('d/m/Y H:i',strtotime((string)$row['next_attempt_at'].' UTC')):''); ?></td><td><?php echo esc_html((string)($row['last_error']??'')); ?></td><td><div style="display:flex;gap:6px;flex-wrap:wrap"><?php if(in_array($st,['failed','cancelled','uncertain'],true)): ?><form method="post" <?php if($st==='uncertain'): ?>onsubmit="return confirm('A entrega pode ja ter ocorrido. Confirmaste no servidor SMTP que NAO foi entregue?')"<?php endif; ?>><?php if($st==='uncertain'): ?><input type="hidden" name="verified_not_delivered" value="1"><?php endif; ?><?php wp_nonce_field('fieldflow_mail_outbox_action','fieldflow_mail_outbox_nonce'); ?><input type="hidden" name="outbox_action" value="requeue"><input type="hidden" name="outbox_id" value="<?php echo (int)$row['id']; ?>"><button class="button button-small"><?php echo $st==='uncertain'?'Confirmar e reabrir':'Reenviar'; ?></button></form><?php endif; ?><?php if(in_array($st,['pending','retry','failed'],true)): ?><form method="post"><?php wp_nonce_field('fieldflow_mail_outbox_action','fieldflow_mail_outbox_nonce'); ?><input type="hidden" name="outbox_action" value="cancel"><input type="hidden" name="outbox_id" value="<?php echo (int)$row['id']; ?>"><button class="button button-small" onclick="return confirm('Cancelar esta mensagem?')">Cancelar</button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </div>
        <div class="rp-card" style="margin-top:16px">
          <h2>Diagnostico de fechos de rota, 9DO</h2>
          <p class="description">Falhas antes da Outbox, destinatarios, tentativas e bloqueios de geografia. Os estados enviados significam apenas aceites no transporte. Nao ha reenvios automaticos de resultados incertos.</p>
          <div style="overflow:auto"><table class="widefat striped"><thead><tr><th>Data (UTC)</th><th>Rota</th><th>Tentativa</th><th>Pedidos aceites</th><th>Falhas</th><th>Resultado</th></tr></thead><tbody>
          <?php if(!$dailyCloseHistory): ?><tr><td colspan="6">Sem tentativas registadas nesta versao.</td></tr><?php endif; ?>
          <?php foreach($dailyCloseHistory as $diag): ?><tr><td><?php echo esc_html((string)($diag['at']??'')); ?></td><td>#<?php echo (int)($diag['route_id']??0); ?></td><td><?php echo (int)($diag['attempt']??0); ?></td><td><?php echo (int)($diag['accepted']??0); ?></td><td><?php echo (int)($diag['failed']??0); ?></td><td><?php echo esc_html((string)($diag['reason']??'')); ?><?php if(!empty($diag['retry'])): ?>, retry previsto<?php endif; ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
          <h3>Ultimas falhas por destinatario, incluindo geracao de anexos</h3>
          <div style="overflow:auto"><table class="widefat striped"><thead><tr><th>Data (UTC)</th><th>Rota</th><th>Email</th><th>Ultimo erro registado</th></tr></thead><tbody>
          <?php if(!$deliveryFailures): ?><tr><td colspan="4">Sem falhas de fecho registadas no historico.</td></tr><?php endif; ?>
          <?php foreach($deliveryFailures as $issue): $meta=json_decode((string)($issue['meta_json']??'{}'),true);if(!is_array($meta))$meta=[]; ?><tr><td><?php echo esc_html((string)($issue['created_at']??'')); ?></td><td>#<?php echo (int)($issue['route_id']??0); ?></td><td><?php echo esc_html((string)($issue['recipient_email']??'')); ?></td><td><?php echo esc_html((string)($meta['error']??'Falha de envio ou de preparacao de anexo.')); ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </div>
        <?php
    }

    public static function renderLegacySettings(){
        if (!current_user_can('routespro_manage')) return;

        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';

        $o = self::get();
        $clients     = $wpdb->get_results("SELECT id,name,email FROM {$px}clients ORDER BY name ASC", ARRAY_A);
        $sel_client  = absint($_POST['preview_client_id'] ?? ($_GET['preview_client_id'] ?? 0));
        $projects    = $sel_client ? $wpdb->get_results($wpdb->prepare("SELECT id,name FROM {$px}projects WHERE client_id=%d ORDER BY name ASC", $sel_client), ARRAY_A) : [];
        $sel_project = absint($_POST['preview_project_id'] ?? ($_GET['preview_project_id'] ?? 0));
        $preview_type = sanitize_key($_POST['preview_type'] ?? ($_GET['preview_type'] ?? 'updated'));
        if (!in_array($preview_type, ['updated','completed','exception'], true)) $preview_type = 'updated';

        if (!empty($_POST['routespro_emails_nonce']) && wp_verify_nonce($_POST['routespro_emails_nonce'],'routespro_emails')) {
            if (isset($_POST['save_global'])) {
                $o['on_completed']    = isset($_POST['on_completed']) ? 1 : 0;
                $o['on_updated']      = isset($_POST['on_updated']) ? 1 : 0;
                $o['on_exception']    = isset($_POST['on_exception']) ? 1 : 0;
                $o['to_client']       = isset($_POST['to_client']) ? 1 : 0;
                $o['to_collaborator'] = isset($_POST['to_collaborator']) ? 1 : 0;
                $o['extra_emails']    = sanitize_text_field($_POST['extra_emails'] ?? '');
                $o['exception_to_client'] = isset($_POST['exception_to_client']) ? 1 : 0;
                $o['exception_to_collaborator'] = isset($_POST['exception_to_collaborator']) ? 1 : 0;
                $o['exception_extra_emails'] = sanitize_text_field($_POST['exception_extra_emails'] ?? '');
                $o['exception_include_route_link'] = isset($_POST['exception_include_route_link']) ? 1 : 0;
                $o['subject_completed'] = sanitize_text_field($_POST['subject_completed'] ?? '');
                $o['body_completed']    = wp_kses_post(str_replace(["\r\n","\r"], "\n", $_POST['body_completed'] ?? ''));
                $o['subject_updated']   = sanitize_text_field($_POST['subject_updated'] ?? '');
                $o['body_updated']      = wp_kses_post(str_replace(["\r\n","\r"], "\n", $_POST['body_updated'] ?? ''));
                $o['subject_exception'] = sanitize_text_field($_POST['subject_exception'] ?? '');
                $o['body_exception'] = wp_kses_post(str_replace(["\r\n","\r"], "\n", $_POST['body_exception'] ?? ''));
                $o['from_name']         = sanitize_text_field($_POST['from_name'] ?? '');
                $o['from_email']        = sanitize_email($_POST['from_email'] ?? '');
                $o['reply_to']          = sanitize_email($_POST['reply_to'] ?? '');
                $o['brand_primary']     = sanitize_hex_color($_POST['brand_primary'] ?? '') ?: '#111827';
                $o['button_label']      = sanitize_text_field($_POST['button_label'] ?? 'Abrir rota');
                $o['footer_text']       = sanitize_text_field($_POST['footer_text'] ?? '');
                $o['send_as_html']      = isset($_POST['send_as_html']) ? 1 : 0;

                update_option(self::OPT_KEY, $o);
                echo '<div class="updated notice"><p>Configurações globais guardadas.</p></div>';
            }

            if (isset($_POST['save_project_tpl']) && $sel_project) {
                $pt = $o['project_templates'];
                $pt[$sel_project] = [
                    'subject_completed' => sanitize_text_field($_POST['project_subject_completed'] ?? ''),
                    'body_completed'    => wp_kses_post(str_replace(["\r\n","\r"], "\n", $_POST['project_body_completed'] ?? '')),
                    'subject_updated'   => sanitize_text_field($_POST['project_subject_updated'] ?? ''),
                    'body_updated'      => wp_kses_post(str_replace(["\r\n","\r"], "\n", $_POST['project_body_updated'] ?? '')),
                    'subject_exception' => sanitize_text_field($_POST['project_subject_exception'] ?? ''),
                    'body_exception'    => wp_kses_post(str_replace(["\r\n","\r"], "\n", $_POST['project_body_exception'] ?? '')),
                ];
                $o['project_templates'] = $pt;
                update_option(self::OPT_KEY, $o);
                echo '<div class="updated notice"><p>Template do projeto guardado.</p></div>';
            }

            if (isset($_POST['delete_project_tpl']) && $sel_project) {
                if (isset($o['project_templates'][$sel_project])) {
                    unset($o['project_templates'][$sel_project]);
                    update_option(self::OPT_KEY, $o);
                    echo '<div class="updated notice"><p>Override do projeto removido. A usar template global.</p></div>';
                }
            }

            if (isset($_POST['send_test'])) {
                $ctx = self::build_demo_context($sel_client, $sel_project);
                $tpl = self::get_project_template($sel_project, $o);
                $subjectKey = $preview_type === 'completed' ? 'subject_completed' : ($preview_type === 'exception' ? 'subject_exception' : 'subject_updated');
                $bodyKey = $preview_type === 'completed' ? 'body_completed' : ($preview_type === 'exception' ? 'body_exception' : 'body_updated');
                $subject = self::apply_placeholders((string)($tpl[$subjectKey] ?? ''), $ctx);
                $body    = self::apply_placeholders((string)($tpl[$bodyKey] ?? ''), $ctx);
                $headers = ['Content-Type: text/html; charset=UTF-8'];
                if (!empty($o['from_name']) || !empty($o['from_email'])) {
                    $fromEmail = sanitize_email($o['from_email'] ?: get_bloginfo('admin_email'));
                    $fromName  = wp_specialchars_decode($o['from_name'] ?: get_bloginfo('name'), ENT_QUOTES);
                    if ($fromEmail) $headers[] = 'From: '.$fromName.' <'.$fromEmail.'>';
                }
                if (!empty($o['reply_to'])) {
                    $headers[] = 'Reply-To: '.sanitize_email($o['reply_to']);
                }
                $user = wp_get_current_user();
                if ($user && !empty($user->user_email)) {
                    $html = !empty($o['send_as_html']) ? self::render_message_html($subject, $body, [
                        'brand_primary' => $o['brand_primary'] ?? '',
                        'footer_text'   => $o['footer_text'] ?? '',
                        'button_label'  => $o['button_label'] ?? 'Abrir rota',
                        'route_url'     => ($preview_type === 'exception' && empty($o['exception_include_route_link'])) ? '' : ($ctx['{route_url}'] ?? ''),
                    ]) : nl2br(esc_html(wp_strip_all_tags($body)));
                    $mailDispatch = \RoutesPro\Services\MailDispatchService::dispatch($user->user_email, $subject, $html, $headers, [], ['source'=>'mail_center_test','preview_type'=>$preview_type,'client_id'=>$sel_client,'project_id'=>$sel_project]);
                    $sent = !empty($mailDispatch['sent']);
                    $mailError = (string)($mailDispatch['error'] ?? '');
                    if ($sent) {
                        echo '<div class="updated notice"><p>Email de teste enviado para '.esc_html($user->user_email).'.</p></div>';
                    } else {
                        $detail = $mailError !== '' ? $mailError : 'wp_mail devolveu false.';
                        echo '<div class="error notice"><p>O WordPress não conseguiu enviar o email de teste. <strong>Detalhe:</strong> '.esc_html($detail).'</p></div>';
                    }
                } else {
                    echo '<div class="error notice"><p>Sem email de utilizador para envio de teste.</p></div>';
                }
            }

            $o = self::get();
        }

        $project_override = ($sel_project && !empty($o['project_templates'][$sel_project])) ? $o['project_templates'][$sel_project] : [
            'subject_completed' => '',
            'body_completed'    => '',
            'subject_updated'   => '',
            'body_updated'      => '',
            'subject_exception' => '',
            'body_exception'    => '',
        ];
        $routes30   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$px}routes WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $activeProj = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$px}projects WHERE status='active'");
        $overrides  = self::count_overrides($o);
        $recipients = count(array_filter(array_map('trim', explode(',', (string)($o['extra_emails'] ?? ''))))) + (!empty($o['to_client']) ? 1 : 0) + (!empty($o['to_collaborator']) ? 1 : 0);

        $ctx = self::build_demo_context($sel_client, $sel_project);
        $tpl = self::get_project_template($sel_project, $o);
        $previewSubjectKey = $preview_type === 'completed' ? 'subject_completed' : ($preview_type === 'exception' ? 'subject_exception' : 'subject_updated');
        $previewBodyKey = $preview_type === 'completed' ? 'body_completed' : ($preview_type === 'exception' ? 'body_exception' : 'body_updated');
        $previewSubject = self::apply_placeholders((string)($tpl[$previewSubjectKey] ?? ''), $ctx);
        $previewBody    = self::apply_placeholders((string)($tpl[$previewBodyKey] ?? ''), $ctx);
        $previewHtml    = self::render_message_html($previewSubject, $previewBody, [
            'brand_primary' => $o['brand_primary'] ?? '',
            'footer_text'   => $o['footer_text'] ?? '',
            'button_label'  => $o['button_label'] ?? 'Abrir rota',
            'route_url'     => ($preview_type === 'exception' && empty($o['exception_include_route_link'])) ? '' : ($ctx['{route_url}'] ?? ''),
        ]);
        ?>
        <div class="wrap routespro-emails-wrap">
          <h1 style="margin-bottom:16px">E-mails</h1>
          <style>
            .routespro-emails-wrap .rp-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px;align-items:start}
            .routespro-emails-wrap .rp-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:18px;box-shadow:0 10px 24px rgba(15,23,42,.04)}
            .routespro-emails-wrap .rp-hero{background:linear-gradient(135deg,#111827 0%,#374151 100%);color:#fff;padding:22px;border-radius:22px;margin:8px 0 18px}
            .routespro-emails-wrap .rp-hero h2{margin:0 0 8px;font-size:24px}
            .routespro-emails-wrap .rp-hero p{margin:0;color:rgba(255,255,255,.85);max-width:900px}
            .routespro-emails-wrap .rp-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0 18px}
            .routespro-emails-wrap .rp-stat{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:14px 16px}
            .routespro-emails-wrap .rp-stat b{display:block;font-size:24px;line-height:1.1;margin-bottom:6px}
            .routespro-emails-wrap .rp-muted{color:#6b7280}
            .routespro-emails-wrap .rp-field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
            .routespro-emails-wrap input[type=text], .routespro-emails-wrap input[type=email], .routespro-emails-wrap select, .routespro-emails-wrap textarea{width:100%;max-width:none;border:1px solid #d1d5db;border-radius:12px;padding:10px 12px}
            .routespro-emails-wrap textarea{min-height:150px}
            .routespro-emails-wrap .rp-inline{display:flex;gap:16px;flex-wrap:wrap;align-items:center}
            .routespro-emails-wrap .rp-label{font-weight:600;margin:0 0 8px;display:block}
            .routespro-emails-wrap .rp-token-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 14px}
            .routespro-emails-wrap code{background:#f3f4f6;border-radius:8px;padding:2px 6px}
            .routespro-emails-wrap .rp-preview-shell{border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;background:#f3f4f6}
            .routespro-emails-wrap .rp-preview-head{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:14px 16px;background:#fff;border-bottom:1px solid #e5e7eb}
            .routespro-emails-wrap .rp-preview-body{padding:12px}
            .routespro-emails-wrap .rp-preview-body iframe{width:100%;height:720px;border:0;background:#fff;border-radius:14px}
            .routespro-emails-wrap .rp-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:14px}
            @media (max-width: 1280px){.routespro-emails-wrap .rp-grid{grid-template-columns:1fr}.routespro-emails-wrap .rp-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
            @media (max-width: 782px){.routespro-emails-wrap .rp-field-grid,.routespro-emails-wrap .rp-token-list,.routespro-emails-wrap .rp-stats{grid-template-columns:1fr}}
          </style>

          <div class="rp-hero">
            <h2>Centro de e-mails operacional</h2>
            <p>Fecha aqui a integração do BO com clientes, projetos, rotas e responsáveis. O foco desta área é simples, template global sólido, override por projeto, preview vivo e envio de teste com branding alinhado ao resto do plugin.</p>
          </div>

          <div class="rp-stats">
            <div class="rp-stat"><b><?php echo (int) $routes30; ?></b><span class="rp-muted">Rotas nos últimos 30 dias</span></div>
            <div class="rp-stat"><b><?php echo (int) $activeProj; ?></b><span class="rp-muted">Projetos ativos</span></div>
            <div class="rp-stat"><b><?php echo (int) $overrides; ?></b><span class="rp-muted">Overrides por projeto</span></div>
            <div class="rp-stat"><b><?php echo (int) $recipients; ?></b><span class="rp-muted">Canais de destino ativos</span></div>
          </div>

          <form method="post">
            <?php wp_nonce_field('routespro_emails','routespro_emails_nonce'); ?>
            <div class="rp-grid">
              <div>
                <div class="rp-card" style="margin-bottom:18px">
                  <h2 style="margin-top:0">Automação e destinatários</h2>
                  <div class="rp-inline" style="margin-bottom:14px">
                    <label><input type="checkbox" name="on_completed" <?php checked($o['on_completed'],1); ?>> Enviar quando a rota fica concluída</label>
                    <label><input type="checkbox" name="on_updated" <?php checked($o['on_updated'],1); ?>> Enviar quando a rota é atualizada <small style="color:#64748b">(legado, sem evento automático ativo)</small></label>
                    <label><input type="checkbox" name="on_exception" <?php checked($o['on_exception'],1); ?>> Enviar quando é registado incumprimento ou ocorrência</label>
                  </div>
                  <div class="rp-inline" style="margin-bottom:14px">
                    <label><input type="checkbox" name="to_client" <?php checked($o['to_client'],1); ?>> Cliente</label>
                    <label><input type="checkbox" name="to_collaborator" <?php checked($o['to_collaborator'],1); ?>> Colaborador responsável</label>
                    <label><input type="checkbox" name="send_as_html" <?php checked($o['send_as_html'],1); ?>> Enviar em HTML premium</label>
                  </div>
                  <label class="rp-label">Emails extra, separados por vírgula</label>
                  <input type="text" name="extra_emails" value="<?php echo esc_attr($o['extra_emails']); ?>" placeholder="gerente@empresa.pt, supervisao@empresa.pt">
                  <div style="margin-top:16px;padding:14px;border:1px solid #fde68a;background:#fffbeb;border-radius:12px">
                    <strong style="display:block;margin-bottom:8px">Destinatários das ocorrências de rota</strong>
                    <div class="rp-inline" style="margin-bottom:10px">
                      <label><input type="checkbox" name="exception_to_client" <?php checked($o['exception_to_client'],1); ?>> Cliente</label>
                      <label><input type="checkbox" name="exception_to_collaborator" <?php checked($o['exception_to_collaborator'],1); ?>> Merchandiser responsável</label>
                      <label><input type="checkbox" name="exception_include_route_link" <?php checked($o['exception_include_route_link'],1); ?>> Incluir botão de acesso à rota</label>
                    </div>
                    <label class="rp-label">Emails extra para ocorrências</label>
                    <input type="text" name="exception_extra_emails" value="<?php echo esc_attr($o['exception_extra_emails']); ?>" placeholder="direcao@cliente.pt, operacoes@empresa.pt">
                  </div>
                </div>

                <div class="rp-card" style="margin-bottom:18px">
                  <h2 style="margin-top:0">Branding e cabeçalhos</h2>
                  <div class="rp-field-grid">
                    <div>
                      <label class="rp-label">Nome do remetente</label>
                      <input type="text" name="from_name" value="<?php echo esc_attr($o['from_name']); ?>" placeholder="FieldFlow">
                    </div>
                    <div>
                      <label class="rp-label">Email do remetente</label>
                      <input type="email" name="from_email" value="<?php echo esc_attr($o['from_email']); ?>" placeholder="no-reply@empresa.pt">
                    </div>
                    <div>
                      <label class="rp-label">Reply-To</label>
                      <input type="email" name="reply_to" value="<?php echo esc_attr($o['reply_to']); ?>" placeholder="operacoes@empresa.pt">
                    </div>
                    <div>
                      <label class="rp-label">Cor principal</label>
                      <input type="text" name="brand_primary" value="<?php echo esc_attr($o['brand_primary']); ?>" placeholder="#111827">
                    </div>
                    <div>
                      <label class="rp-label">Texto do botão</label>
                      <input type="text" name="button_label" value="<?php echo esc_attr($o['button_label']); ?>" placeholder="Abrir rota">
                    </div>
                    <div>
                      <label class="rp-label">Texto de rodapé</label>
                      <input type="text" name="footer_text" value="<?php echo esc_attr($o['footer_text']); ?>" placeholder="Notificação automática gerada pelo FieldFlow.">
                    </div>
                  </div>
                </div>

                <div class="rp-card" style="margin-bottom:18px">
                  <h2 style="margin-top:0">Templates globais</h2>
                  <p class="rp-muted" style="margin-top:0">Estes textos servem como base para toda a operação. O projeto só substitui o que precisares mesmo de personalizar.</p>
                  <div class="rp-field-grid">
                    <div>
                      <label class="rp-label">Assunto, rota concluída</label>
                      <input type="text" name="subject_completed" value="<?php echo esc_attr($o['subject_completed']); ?>">
                    </div>
                    <div>
                      <label class="rp-label">Assunto, rota atualizada</label>
                      <input type="text" name="subject_updated" value="<?php echo esc_attr($o['subject_updated']); ?>">
                    </div>
                    <div style="grid-column:1/-1">
                      <label class="rp-label">Corpo, rota concluída</label>
                      <textarea name="body_completed" class="code"><?php echo esc_textarea($o['body_completed']); ?></textarea>
                    </div>
                    <div style="grid-column:1/-1">
                      <label class="rp-label">Corpo, rota atualizada</label>
                      <textarea name="body_updated" class="code"><?php echo esc_textarea($o['body_updated']); ?></textarea>
                    </div>
                    <div style="grid-column:1/-1;border-top:1px solid #e5e7eb;padding-top:14px">
                      <label class="rp-label">Assunto, ocorrência ou incumprimento</label>
                      <input type="text" name="subject_exception" value="<?php echo esc_attr($o['subject_exception']); ?>">
                    </div>
                    <div style="grid-column:1/-1">
                      <label class="rp-label">Corpo, ocorrência ou incumprimento</label>
                      <textarea name="body_exception" class="code"><?php echo esc_textarea($o['body_exception']); ?></textarea>
                    </div>
                  </div>
                  <div class="rp-actions">
                    <button class="button button-primary" name="save_global" value="1">Guardar configuração global</button>
                  </div>
                </div>

                <div class="rp-card">
                  <h2 style="margin-top:0">Override por projeto</h2>
                  <p class="rp-muted" style="margin-top:0">Útil para campanhas com comunicação própria, sem partir a coerência global do BO.</p>
                  <div class="rp-field-grid" style="margin-bottom:14px">
                    <div>
                      <label class="rp-label">Cliente</label>
                      <select name="preview_client_id" onchange="this.form.submit()">
                        <option value="">Selecionar</option>
                        <?php foreach($clients as $c): ?>
                          <option value="<?php echo intval($c['id']); ?>" <?php selected($sel_client, $c['id']); ?>><?php echo esc_html($c['name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div>
                      <label class="rp-label">Projeto ou campanha</label>
                      <select name="preview_project_id" onchange="this.form.submit()">
                        <option value="">Selecionar</option>
                        <?php foreach($projects as $p): ?>
                          <option value="<?php echo intval($p['id']); ?>" <?php selected($sel_project, $p['id']); ?>><?php echo esc_html($p['name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div>
                      <label class="rp-label">Assunto, rota concluída</label>
                      <input type="text" name="project_subject_completed" value="<?php echo esc_attr($project_override['subject_completed']); ?>">
                    </div>
                    <div>
                      <label class="rp-label">Assunto, rota atualizada</label>
                      <input type="text" name="project_subject_updated" value="<?php echo esc_attr($project_override['subject_updated']); ?>">
                    </div>
                    <div style="grid-column:1/-1">
                      <label class="rp-label">Corpo, rota concluída</label>
                      <textarea name="project_body_completed" class="code"><?php echo esc_textarea($project_override['body_completed']); ?></textarea>
                    </div>
                    <div style="grid-column:1/-1">
                      <label class="rp-label">Corpo, rota atualizada</label>
                      <textarea name="project_body_updated" class="code"><?php echo esc_textarea($project_override['body_updated']); ?></textarea>
                    </div>
                    <div style="grid-column:1/-1;border-top:1px solid #e5e7eb;padding-top:14px">
                      <label class="rp-label">Assunto, ocorrência ou incumprimento</label>
                      <input type="text" name="project_subject_exception" value="<?php echo esc_attr($project_override['subject_exception']); ?>">
                    </div>
                    <div style="grid-column:1/-1">
                      <label class="rp-label">Corpo, ocorrência ou incumprimento</label>
                      <textarea name="project_body_exception" class="code"><?php echo esc_textarea($project_override['body_exception']); ?></textarea>
                    </div>
                  </div>
                  <div class="rp-actions">
                    <button class="button button-primary" name="save_project_tpl" value="1" <?php disabled(!$sel_project); ?>>Guardar override do projeto</button>
                    <button class="button button-link-delete" name="delete_project_tpl" value="1" <?php disabled(!$sel_project || empty($o['project_templates'][$sel_project])); ?> onclick="return confirm('Remover override deste projeto?')">Apagar override</button>
                  </div>
                </div>
              </div>

              <div>
                <div class="rp-card" style="margin-bottom:18px">
                  <h2 style="margin-top:0">Placeholders e integração</h2>
                  <div class="rp-token-list">
                    <?php foreach (self::template_tokens() as $token => $label): ?>
                      <div><code><?php echo esc_html($token); ?></code><div class="rp-muted"><?php echo esc_html($label); ?></div></div>
                    <?php endforeach; ?>
                  </div>
                </div>

                <div class="rp-card" style="margin-bottom:18px">
                  <div class="rp-preview-head" style="padding:0 0 14px;border:0;background:none">
                    <div>
                      <h2 style="margin:0">Preview vivo</h2>
                      <div class="rp-muted">Puxa o template global ou o override do projeto selecionado.</div>
                    </div>
                  </div>
                  <div class="rp-field-grid" style="margin-bottom:14px">
                    <div>
                      <label class="rp-label">Tipo de pré visualização</label>
                      <select name="preview_type" onchange="this.form.submit()">
                        <option value="updated" <?php selected($preview_type, 'updated'); ?>>Rota atualizada</option>
                        <option value="completed" <?php selected($preview_type, 'completed'); ?>>Rota concluída</option>
                        <option value="exception" <?php selected($preview_type, 'exception'); ?>>Ocorrência ou incumprimento</option>
                      </select>
                    </div>
                    <div>
                      <label class="rp-label">Assunto final</label>
                      <input type="text" value="<?php echo esc_attr($previewSubject); ?>" readonly>
                    </div>
                  </div>
                  <div class="rp-preview-shell">
                    <div class="rp-preview-head">
                      <strong><?php echo esc_html($previewSubject); ?></strong>
                      <span class="rp-muted">Branding BO</span>
                    </div>
                    <div class="rp-preview-body">
                      <iframe sandbox="allow-same-origin" srcdoc="<?php echo esc_attr($previewHtml); ?>"></iframe>
                    </div>
                  </div>
                  <div class="rp-actions">
                    <button class="button" name="send_test" value="1">Enviar teste para o meu email</button>
                  </div>
                </div>

                <div class="rp-card">
                  <h2 style="margin-top:0">Como isto fica ligado ao resto do plugin</h2>
                  <ul style="margin:0;padding-left:18px;line-height:1.8">
                    <li>Clientes, projetos e responsáveis alimentam automaticamente os placeholders.</li>
                    <li>Os overrides por projeto deixam alinhar campanhas PDV com comunicação própria.</li>
                    <li>O link da rota aponta para o BO, útil para operação, coordenação e validação rápida.</li>
                    <li>O corpo pode continuar simples em texto ou passar para HTML premium sem perder compatibilidade.</li>
                  </ul>
                </div>
              </div>
            </div>
          </form>
        </div>
        <?php
    }

    
public static function get_team_recipients(int $client_id = 0, int $project_id = 0): array {
        $user_ids = [];

        if ($project_id > 0) {
            $user_ids = array_values(array_filter(array_map('absint', \RoutesPro\Support\Permissions::get_associated_user_ids($client_id, $project_id))));
        } elseif ($client_id > 0) {
            $user_ids = array_values(array_filter(array_map('absint', \RoutesPro\Support\Permissions::get_associated_user_ids($client_id, 0))));
        } else {
            $scope = \RoutesPro\Support\Permissions::get_scope();
            $project_ids = array_values(array_filter(array_map('absint', (array) ($scope['project_ids'] ?? []))));
            foreach ($project_ids as $pid) {
                foreach ((array) \RoutesPro\Support\Permissions::get_associated_user_ids(0, (int) $pid) as $uid) {
                    $uid = absint($uid);
                    if ($uid) $user_ids[$uid] = $uid;
                }
            }
            if (!$user_ids) {
                $client_ids = array_values(array_filter(array_map('absint', (array) ($scope['client_ids'] ?? []))));
                foreach ($client_ids as $cid) {
                    foreach ((array) \RoutesPro\Support\Permissions::get_associated_user_ids((int) $cid, 0) as $uid) {
                        $uid = absint($uid);
                        if ($uid) $user_ids[$uid] = $uid;
                    }
                }
            }
            $user_ids = array_values($user_ids);
        }

        $user_ids = array_values(array_unique(array_filter(array_map('absint', $user_ids))));
        if (!$user_ids) return [];

        $users = get_users([
            'include' => $user_ids,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID','display_name','user_email','user_login'],
        ]);
        if (!is_array($users)) return [];

        $out = [];
        foreach ($users as $u) {
            $email = (string) ($u->user_email ?? '');
            if ($email === '') continue;
            $display = $u->display_name ?: $u->user_login ?: ('#' . $u->ID);
            $out[] = [
                'ID' => (int) $u->ID,
                'display_name' => (string) $display,
                'user_email' => $email,
                'user_login' => (string) ($u->user_login ?? ''),
                'label' => trim($display . ' • ' . $email),
            ];
        }
        return array_values($out);
    }

    public static function get_available_senders_for_user(int $client_id = 0, int $project_id = 0, ?int $logged_user_id = null): array {
        $logged_user_id = $logged_user_id ?: get_current_user_id();
        if (!$logged_user_id) return [];

        $user = get_user_by('id', $logged_user_id);
        if (!$user || !($user instanceof \WP_User) || !$user->exists()) return [];

        if (current_user_can('routespro_manage')) {
            $display = (string) ($user->display_name ?: $user->user_login ?: ('#' . $logged_user_id));
            $email = (string) ($user->user_email ?? '');
            if ($email === '') return [];
            return [[
                'ID' => (int) $logged_user_id,
                'display_name' => $display,
                'user_email' => $email,
                'user_login' => (string) ($user->user_login ?? ''),
                'label' => trim($display . ' • ' . $email),
            ]];
        }

        // Phase 9B: sender eligibility follows the authenticated portal scope,
        // not the operational project user list. A project may define its own
        // merchandiser roster, which intentionally overrides associated_user_ids
        // for operational recipient resolution. That must never evict a client
        // user who legitimately inherits access from the client account.
        $scope_check = \RoutesPro\Support\Permissions::assert_scope_or_error($client_id, $project_id, $logged_user_id);
        if (is_wp_error($scope_check)) {
            return [];
        }

        $display = (string) ($user->display_name ?: $user->user_login ?: ('#' . $logged_user_id));
        $email = (string) ($user->user_email ?? '');
        if ($email === '') return [];

        return [[
            'ID' => (int) $logged_user_id,
            'display_name' => $display,
            'user_email' => $email,
            'user_login' => (string) ($user->user_login ?? ''),
            'label' => trim($display . ' • ' . $email),
        ]];
    }

    public static function get_message_app_url(int $log_id = 0): string {
        $target = home_url('/');
        $args = [];
        if ($log_id > 0) $args['routespro_message'] = $log_id;
        $url = add_query_arg($args, $target);
        return $url . '#rp-app-messages';
    }

    public static function log_email(array $args = []): int {
        global $wpdb;
        $table = $wpdb->prefix . 'routespro_email_logs';
        $data = [
            'email_type' => sanitize_key($args['email_type'] ?? 'system'),
            'context_key' => sanitize_key($args['context_key'] ?? ''),
            'client_id' => absint($args['client_id'] ?? 0) ?: null,
            'project_id' => absint($args['project_id'] ?? 0) ?: null,
            'route_id' => absint($args['route_id'] ?? 0) ?: null,
            'sender_user_id' => absint($args['sender_user_id'] ?? 0) ?: null,
            'recipient_user_id' => absint($args['recipient_user_id'] ?? 0) ?: null,
            'recipient_email' => sanitize_email($args['recipient_email'] ?? ''),
            'recipient_name' => sanitize_text_field($args['recipient_name'] ?? ''),
            'message_kind' => sanitize_text_field($args['message_kind'] ?? ''),
            'subject' => sanitize_text_field($args['subject'] ?? ''),
            'body' => wp_kses_post($args['body'] ?? ''),
            'meta_json' => wp_json_encode(is_array($args['meta'] ?? null) ? ($args['meta'] ?? []) : []),
            'mail_result' => sanitize_key($args['mail_result'] ?? 'sent'),
        ];
        $formats = ['%s','%s','%d','%d','%d','%d','%d','%s','%s','%s','%s','%s','%s','%s'];
        $wpdb->insert($table, $data, $formats);
        return (int) $wpdb->insert_id;
    }

    private static function get_log_recipients(int $client_id = 0, int $project_id = 0): array {
        global $wpdb;
        $table = $wpdb->prefix . 'routespro_email_logs';
        $where = ['1=1'];
        $args = [];
        if ($client_id) { $where[] = 'client_id=%d'; $args[] = $client_id; }
        if ($project_id) { $where[] = 'project_id=%d'; $args[] = $project_id; }
        $sql = "SELECT recipient_user_id, recipient_email, recipient_name, MAX(created_at) AS last_seen
                FROM {$table}
                WHERE " . implode(' AND ', $where) . "
                GROUP BY recipient_user_id, recipient_email, recipient_name
                ORDER BY last_seen DESC";
        $rows = $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
        $out = [];
        foreach ($rows as $row) {
            $email = sanitize_email((string) ($row['recipient_email'] ?? ''));
            $uid = absint($row['recipient_user_id'] ?? 0);
            $name = trim((string) ($row['recipient_name'] ?? ''));
            if (!$uid && $email === '') continue;
            $key = $uid ? ('u:' . $uid) : ('e:' . strtolower($email));
            $label = $name ?: $email ?: ('#' . $uid);
            if ($email && stripos($label, $email) === false) $label .= ' · ' . $email;
            $out[$key] = ['key' => $key, 'label' => $label];
        }
        return array_values($out);
    }



    
public static function get_user_message_logs(int $user_id, array $filters = []): array {
    global $wpdb;
    $table = $wpdb->prefix . 'routespro_email_logs';
    $px = $wpdb->prefix . 'routespro_';

    $user_id = absint($user_id);
    if (!$user_id) return [];

    $scope = \RoutesPro\Support\Permissions::get_scope($user_id);
    $user = get_userdata($user_id);
    $user_email = strtolower((string) ($user->user_email ?? ''));

    $project_ids = array_values(array_filter(array_map('absint', (array) ($scope['project_ids'] ?? []))));
    $client_ids  = array_values(array_filter(array_map('absint', (array) ($scope['client_ids'] ?? []))));
    $route_ids   = array_values(array_filter(array_map('absint', (array) ($scope['route_ids'] ?? []))));

    $metaProjects = $wpdb->get_results("SELECT id, client_id, meta_json FROM {$px}projects", ARRAY_A) ?: [];
    foreach ($metaProjects as $projectRow) {
        $meta = json_decode((string)($projectRow['meta_json'] ?? ''), true);
        if (!is_array($meta)) $meta = [];
        $values = $meta['associated_user_ids'] ?? ($meta['user_ids'] ?? ($meta['assigned_users'] ?? []));
        if (is_string($values)) $values = preg_split('/\s*,\s*/', trim($values));
        if (!is_array($values)) $values = [];
        foreach ($values as $value) {
            if (is_array($value)) $value = $value['user_id'] ?? $value['id'] ?? 0;
            $value = absint($value);
            if ($value === $user_id) {
                $pid = (int)($projectRow['id'] ?? 0);
                $cid = (int)($projectRow['client_id'] ?? 0);
                if ($pid) $project_ids[] = $pid;
                if ($cid) $client_ids[] = $cid;
            }
        }
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT pa.project_id, p.client_id
         FROM {$px}project_assignments pa
         INNER JOIN {$px}projects p ON p.id = pa.project_id
         WHERE pa.user_id = %d AND pa.is_active = 1",
        $user_id
    ), ARRAY_A) ?: [];
    foreach ($rows as $row) {
        $pid = (int)($row['project_id'] ?? 0);
        $cid = (int)($row['client_id'] ?? 0);
        if ($pid) $project_ids[] = $pid;
        if ($cid) $client_ids[] = $cid;
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT r.id AS route_id, r.project_id, r.client_id
         FROM {$px}routes r
         LEFT JOIN {$px}assignments a ON a.route_id = r.id AND a.is_active = 1
         WHERE r.owner_user_id = %d OR a.user_id = %d",
        $user_id,
        $user_id
    ), ARRAY_A) ?: [];
    foreach ($rows as $row) {
        $rid = (int)($row['route_id'] ?? 0);
        $pid = (int)($row['project_id'] ?? 0);
        $cid = (int)($row['client_id'] ?? 0);
        if ($rid) $route_ids[] = $rid;
        if ($pid) $project_ids[] = $pid;
        if ($cid) $client_ids[] = $cid;
    }

    $project_ids = array_values(array_unique(array_filter(array_map('absint', $project_ids))));
    $client_ids  = array_values(array_unique(array_filter(array_map('absint', $client_ids))));
    $route_ids   = array_values(array_unique(array_filter(array_map('absint', $route_ids))));

    $where = ["el.email_type IN ('client_team_message','client_team_message_reply')"];
    $args = [];

    if (!empty($filters['project_id'])) {
        $where[] = 'el.project_id = %d';
        $args[] = absint($filters['project_id']);
    }
    if (!empty($filters['client_id'])) {
        $where[] = 'el.client_id = %d';
        $args[] = absint($filters['client_id']);
    }
    if (!empty($filters['message_id'])) {
        $where[] = 'el.id = %d';
        $args[] = absint($filters['message_id']);
    }
    if (!empty($filters['recipient_user_id'])) {
        $where[] = 'el.recipient_user_id = %d';
        $args[] = absint($filters['recipient_user_id']);
    }
    if (!empty($filters['sender_user_id'])) {
        $where[] = 'el.sender_user_id = %d';
        $args[] = absint($filters['sender_user_id']);
    }

    $participantUserId = absint($filters['participant_user_id'] ?? 0);

    $sql = "SELECT
                el.*,
                c.name AS client_name,
                p.name AS project_name,
                su.display_name AS sender_name,
                su.user_email AS sender_email_joined,
                ru.display_name AS recipient_user_name,
                ru.user_email AS recipient_user_email
            FROM {$table} el
            LEFT JOIN {$wpdb->prefix}routespro_clients c ON c.id = el.client_id
            LEFT JOIN {$wpdb->prefix}routespro_projects p ON p.id = el.project_id
            LEFT JOIN {$wpdb->users} su ON su.ID = el.sender_user_id
            LEFT JOIN {$wpdb->users} ru ON ru.ID = el.recipient_user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY el.created_at DESC, el.id DESC
            LIMIT 800";

    $rows = $args
        ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [])
        : ($wpdb->get_results($sql, ARRAY_A) ?: []);

    $parentStatusMap = [];
    $parentIds = [];
    foreach ($rows as $candidateRow) {
        $candidateMeta = json_decode((string)($candidateRow['meta_json'] ?? ''), true);
        if (!is_array($candidateMeta)) $candidateMeta = [];
        $parentId = absint($candidateMeta['parent_log_id'] ?? 0);
        if ($parentId > 0) $parentIds[$parentId] = $parentId;
    }
    if ($parentIds) {
        $placeholders = implode(',', array_fill(0, count($parentIds), '%d'));
        $parentRows = $wpdb->get_results($wpdb->prepare("SELECT id, meta_json FROM {$table} WHERE id IN ($placeholders)", ...array_values($parentIds)), ARRAY_A) ?: [];
        foreach ($parentRows as $parentRow) {
            $parentMeta = json_decode((string)($parentRow['meta_json'] ?? ''), true);
            if (!is_array($parentMeta)) $parentMeta = [];
            $parentStatusMap[(int)($parentRow['id'] ?? 0)] = sanitize_text_field((string)($parentMeta['workflow_status'] ?? 'novo')) ?: 'novo';
        }
    }

    $statusFilter = sanitize_text_field((string)($filters['status'] ?? ''));
    $filterProjectId = absint($filters['project_id'] ?? 0);
    $filterClientId = absint($filters['client_id'] ?? 0);
    $onlyDirect = !empty($filters['only_direct']);
    $out = [];

    foreach ($rows as $row) {
        $meta = json_decode((string)($row['meta_json'] ?? ''), true);
        if (!is_array($meta)) $meta = [];

        $workflowStatus = sanitize_text_field((string)($meta['workflow_status'] ?? 'novo'));
        if ($workflowStatus === '') $workflowStatus = 'novo';

        $rowProjectId = (int)($row['project_id'] ?? 0);
        $rowClientId  = (int)($row['client_id'] ?? 0);
        $rowRouteId   = (int)($row['route_id'] ?? 0);

        $submittedByUserId    = absint($meta['submitted_by_user_id'] ?? 0);
        $selectedSenderUserId = absint($meta['selected_sender_user_id'] ?? 0);

        $submittedByEmail     = strtolower((string)($meta['submitted_by_email'] ?? ''));
        $selectedSenderEmail  = strtolower((string)($meta['selected_sender_email'] ?? ''));
        $recipientEmail       = strtolower((string)($row['recipient_email'] ?? ''));
        $senderEmailJoined    = strtolower((string)($row['sender_email_joined'] ?? ''));
        $recipientUserEmail   = strtolower((string)($row['recipient_user_email'] ?? ''));

        $isDirectMessage =
            ((int)($row['recipient_user_id'] ?? 0) === $user_id)
            || ((int)($row['sender_user_id'] ?? 0) === $user_id)
            || ($submittedByUserId === $user_id)
            || ($selectedSenderUserId === $user_id)
            || ($user_email !== '' && (
                $recipientEmail === $user_email
                || $senderEmailJoined === $user_email
                || $recipientUserEmail === $user_email
                || $submittedByEmail === $user_email
                || $selectedSenderEmail === $user_email
            ));

        $isScopeVisible =
            ($rowProjectId && in_array($rowProjectId, $project_ids, true))
            || ($rowClientId && in_array($rowClientId, $client_ids, true))
            || ($rowRouteId && in_array($rowRouteId, $route_ids, true));

        $matchesExplicitFilter =
            ($filterProjectId > 0 && $rowProjectId === $filterProjectId)
            || ($filterClientId > 0 && !$filterProjectId && $rowClientId === $filterClientId);

        if ($onlyDirect) {
            if (!$isDirectMessage) {
                continue;
            }
        } elseif (!$isDirectMessage && !$isScopeVisible && !$matchesExplicitFilter) {
            continue;
        }

        $parentLogId = absint($meta['parent_log_id'] ?? 0);
        $effectiveWorkflowStatus = $parentLogId && !empty($parentStatusMap[$parentLogId])
            ? (string) $parentStatusMap[$parentLogId]
            : $workflowStatus;

        if ($participantUserId > 0) {
            $matchesParticipant = ((int)($row['recipient_user_id'] ?? 0) === $participantUserId)
                || ((int)($row['sender_user_id'] ?? 0) === $participantUserId)
                || ($submittedByUserId === $participantUserId)
                || ($selectedSenderUserId === $participantUserId);
            if (!$matchesParticipant) {
                continue;
            }
        }

        if ($statusFilter !== '' && $effectiveWorkflowStatus !== $statusFilter) {
            continue;
        }

        if (empty($row['sender_name']) && !empty($meta['selected_sender_user_id'])) {
            $senderUser = get_userdata((int)$meta['selected_sender_user_id']);
            if ($senderUser) $row['sender_name'] = (string)($senderUser->display_name ?: $senderUser->user_login);
        }
        if (empty($row['sender_name']) && !empty($meta['selected_sender_email'])) {
            $row['sender_name'] = (string)$meta['selected_sender_email'];
        }

        $row['meta'] = $meta;
        $row['workflow_status'] = $workflowStatus;
        $row['effective_workflow_status'] = $effectiveWorkflowStatus;
        $row['app_url'] = self::get_message_app_url((int)($row['id'] ?? 0));
        $out[] = $row;
    }

    return array_values($out);
}


    private static function handle_log_actions(string $table): void {
        global $wpdb;
        if (!current_user_can('routespro_manage')) return;

        if (isset($_GET['rp_action']) && $_GET['rp_action'] === 'export') {
            check_admin_referer('routespro_email_log_export');
            self::export_log_csv($table);
        }

        if (!empty($_POST['routespro_email_log_action']) && check_admin_referer('routespro_email_log_save', 'routespro_email_log_nonce')) {
            $action = sanitize_key($_POST['routespro_email_log_action']);
            $log_id = absint($_POST['log_id'] ?? 0);
            if ($log_id) {
                if ($action === 'save') {
                    $wpdb->update($table, [
                        'recipient_name' => sanitize_text_field($_POST['recipient_name'] ?? ''),
                        'recipient_email' => sanitize_email($_POST['recipient_email'] ?? ''),
                        'message_kind' => sanitize_text_field($_POST['message_kind'] ?? ''),
                        'subject' => sanitize_text_field($_POST['subject'] ?? ''),
                        'mail_result' => sanitize_key($_POST['mail_result'] ?? 'sent'),
                    ], ['id' => $log_id], ['%s','%s','%s','%s','%s'], ['%d']);
                    add_settings_error('routespro_email_log', 'saved', 'Email atualizado com sucesso.', 'updated');
                } elseif ($action === 'delete') {
                    $wpdb->delete($table, ['id' => $log_id], ['%d']);
                    add_settings_error('routespro_email_log', 'deleted', 'Email apagado com sucesso.', 'updated');
                }
            }
        } elseif (isset($_GET['rp_action'], $_GET['log_id']) && $_GET['rp_action'] === 'delete') {
            check_admin_referer('routespro_email_log_delete_' . absint($_GET['log_id']));
            $wpdb->delete($table, ['id' => absint($_GET['log_id'])], ['%d']);
            add_settings_error('routespro_email_log', 'deleted', 'Email apagado com sucesso.', 'updated');
        }
    }

    private static function export_log_csv(string $table): void {
        global $wpdb;
        if (class_exists('\RoutesPro\SaaS\EntitlementService')) \RoutesPro\SaaS\EntitlementService::assertExportOrDie('csv', 0, false);
        [$where_sql, $args] = self::build_log_filters($wpdb);
        $sql = "SELECT el.*, c.name AS client_name, p.name AS project_name, su.display_name AS sender_name, ru.display_name AS recipient_user_name
                FROM {$table} el
                LEFT JOIN {$wpdb->prefix}routespro_clients c ON c.id = el.client_id
                LEFT JOIN {$wpdb->prefix}routespro_projects p ON p.id = el.project_id
                LEFT JOIN {$wpdb->users} su ON su.ID = el.sender_user_id
                LEFT JOIN {$wpdb->users} ru ON ru.ID = el.recipient_user_id
                WHERE {$where_sql}
                ORDER BY el.created_at DESC, el.id DESC";
        $rows = $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=routespro-email-log.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id','data','tipo','cliente','campanha','destinatario','destinatario_email','categoria','assunto','remetente','estado']);
        foreach ($rows as $row) {
            fputcsv($out, [
                (int) $row['id'],
                (string) $row['created_at'],
                (string) $row['email_type'],
                (string) ($row['client_name'] ?: ''),
                (string) ($row['project_name'] ?: ''),
                (string) ($row['recipient_user_name'] ?: $row['recipient_name'] ?: $row['recipient_email']),
                (string) ($row['recipient_email'] ?: ''),
                (string) ($row['message_kind'] ?: ''),
                (string) ($row['subject'] ?: ''),
                (string) ($row['sender_name'] ?: ''),
                (string) ($row['mail_result'] ?: ''),
            ]);
        }
        fclose($out);
        exit;
    }

    private static function build_log_filters($wpdb): array {
        $client_id = absint($_GET['client_id'] ?? 0);
        $project_id = absint($_GET['project_id'] ?? 0);
        $recipient_key = sanitize_text_field($_GET['recipient_key'] ?? '');
        if ($recipient_key === '' && !empty($_GET['recipient_user_id'])) $recipient_key = 'u:' . absint($_GET['recipient_user_id']);
        $email_type = sanitize_key($_GET['email_type'] ?? '');
        $message_kind = sanitize_text_field($_GET['message_kind'] ?? '');
        $q = sanitize_text_field($_GET['q'] ?? '');
        $date_from = sanitize_text_field($_GET['date_from'] ?? '');
        $date_to = sanitize_text_field($_GET['date_to'] ?? '');

        $where = ['1=1'];
        $args = [];
        if ($client_id) { $where[] = 'el.client_id=%d'; $args[] = $client_id; }
        if ($project_id) { $where[] = 'el.project_id=%d'; $args[] = $project_id; }
        if ($recipient_key !== '') {
            if (strpos($recipient_key, 'u:') === 0) {
                $where[] = 'el.recipient_user_id=%d'; $args[] = absint(substr($recipient_key, 2));
            } elseif (strpos($recipient_key, 'e:') === 0) {
                $where[] = 'LOWER(el.recipient_email)=%s'; $args[] = strtolower(substr($recipient_key, 2));
            }
        }
        if ($email_type !== '') { $where[] = 'el.email_type=%s'; $args[] = $email_type; }
        if ($message_kind !== '') { $where[] = 'el.message_kind=%s'; $args[] = $message_kind; }
        if ($q !== '') {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where[] = '(el.subject LIKE %s OR el.recipient_email LIKE %s OR el.recipient_name LIKE %s OR ru.display_name LIKE %s OR el.body LIKE %s)';
            array_push($args, $like, $like, $like, $like, $like);
        }
        if ($date_from !== '') { $where[] = 'DATE(el.created_at) >= %s'; $args[] = $date_from; }
        if ($date_to !== '') { $where[] = 'DATE(el.created_at) <= %s'; $args[] = $date_to; }
        return [implode(' AND ', $where), $args];
    }

    public static function render_log() {
        if (!current_user_can('routespro_manage')) return;
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $table = $wpdb->prefix . 'routespro_email_logs';
        self::handle_log_actions($table);

        $client_id = absint($_GET['client_id'] ?? 0);
        $project_id = absint($_GET['project_id'] ?? 0);
        $recipient_key = sanitize_text_field($_GET['recipient_key'] ?? '');
        if ($recipient_key === '' && !empty($_GET['recipient_user_id'])) $recipient_key = 'u:' . absint($_GET['recipient_user_id']);
        $email_type = sanitize_key($_GET['email_type'] ?? '');
        $message_kind = sanitize_text_field($_GET['message_kind'] ?? '');
        $q = sanitize_text_field($_GET['q'] ?? '');
        $date_from = sanitize_text_field($_GET['date_from'] ?? '');
        $date_to = sanitize_text_field($_GET['date_to'] ?? '');
        $page_num = max(1, absint($_GET['paged'] ?? 1));
        $per_page = absint($_GET['per_page'] ?? 25);
        if (!in_array($per_page, [25,50,100,250], true)) $per_page = 25;
        $edit_id = absint($_GET['edit_id'] ?? 0);

        $clients = $wpdb->get_results("SELECT id,name FROM {$px}clients ORDER BY name ASC", ARRAY_A) ?: [];
        $projects = $client_id ? ($wpdb->get_results($wpdb->prepare("SELECT id,name FROM {$px}projects WHERE client_id=%d ORDER BY name ASC", $client_id), ARRAY_A) ?: []) : ($wpdb->get_results("SELECT id,name FROM {$px}projects ORDER BY name ASC", ARRAY_A) ?: []);
        $recipients = self::get_log_recipients($client_id, $project_id);
        [$where_sql, $args] = self::build_log_filters($wpdb);

        $count_sql = "SELECT COUNT(*) FROM {$table} el LEFT JOIN {$wpdb->users} ru ON ru.ID = el.recipient_user_id WHERE {$where_sql}";
        $total = (int) ($args ? $wpdb->get_var($wpdb->prepare($count_sql, ...$args)) : $wpdb->get_var($count_sql));
        $total_pages = max(1, (int) ceil($total / max(1, $per_page)));
        if ($page_num > $total_pages) $page_num = $total_pages;
        $offset = ($page_num - 1) * $per_page;

        $sql = "SELECT el.*, c.name AS client_name, p.name AS project_name, su.display_name AS sender_name, ru.display_name AS recipient_user_name
                FROM {$table} el
                LEFT JOIN {$px}clients c ON c.id = el.client_id
                LEFT JOIN {$px}projects p ON p.id = el.project_id
                LEFT JOIN {$wpdb->users} su ON su.ID = el.sender_user_id
                LEFT JOIN {$wpdb->users} ru ON ru.ID = el.recipient_user_id
                WHERE {$where_sql}
                ORDER BY el.created_at DESC, el.id DESC
                LIMIT %d OFFSET %d";
        $rows_args = array_values($args);
        $rows_args[] = $per_page;
        $rows_args[] = $offset;
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$rows_args), ARRAY_A) ?: [];
        $edit_row = $edit_id ? ($wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $edit_id), ARRAY_A) ?: null) : null;

        $base_url = admin_url('admin.php?page=routespro-email-log');
        echo '<div class="wrap">';
        \RoutesPro\Admin\Branding::render_header('E-mails enviados', 'Tabela operacional para seguir tudo o que saiu do sistema, incluindo mensagens do portal para a equipa.');
        settings_errors('routespro_email_log');
        echo '<div class="routespro-card" style="margin-top:18px">';
        echo '<form method="get" style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;align-items:end">';
        echo '<input type="hidden" name="page" value="routespro-email-log">';
        echo '<label>Cliente<br><select name="client_id"><option value="0">Todos</option>';
        foreach($clients as $c) echo '<option value="'.intval($c['id']).'" '.selected($client_id,(int)$c['id'],false).'>'.esc_html($c['name']).'</option>';
        echo '</select></label>';
        echo '<label>Campanha<br><select name="project_id"><option value="0">Todas</option>';
        foreach($projects as $p) echo '<option value="'.intval($p['id']).'" '.selected($project_id,(int)$p['id'],false).'>'.esc_html($p['name']).'</option>';
        echo '</select></label>';
        echo '<label>Destinatário<br><select name="recipient_key"><option value="">Todos</option>';
        foreach($recipients as $u) echo '<option value="'.esc_attr($u['key']).'" '.selected($recipient_key,$u['key'],false).'>'.esc_html($u['label']).'</option>';
        echo '</select></label>';
        echo '<label>Tipo<br><select name="email_type"><option value="">Todos</option>';
        foreach(['route_notification'=>'Notificação rota','client_team_message'=>'Portal cliente','scheduled_report'=>'Relatório automático','scheduled_report_test'=>'Relatório automático, teste','order_suggestion'=>'Sugestão de encomenda','order_suggestion_test'=>'Sugestão de encomenda, teste','shopping_verification'=>'Shopping','shopping_verification_resend'=>'Shopping, reenvio'] as $k=>$label) echo '<option value="'.esc_attr($k).'" '.selected($email_type,$k,false).'>'.esc_html($label).'</option>';
        echo '</select></label>';
        echo '<label>Categoria<br><select name="message_kind"><option value="">Todas</option>';
        foreach(['corretiva'=>'Corretiva','preventiva'=>'Preventiva','informacao'=>'Informação','geral'=>'Geral','price_verification'=>'Shopping, verificação','price_verification_resend'=>'Shopping, reenvio'] as $k=>$label) echo '<option value="'.esc_attr($k).'" '.selected($message_kind,$k,false).'>'.esc_html($label).'</option>';
        echo '</select></label>';
        echo '<label>Pesquisa<br><input type="text" name="q" value="'.esc_attr($q).'" placeholder="Assunto, destinatário, texto"></label>';
        echo '<label>De<br><input type="date" name="date_from" value="'.esc_attr($date_from).'"></label>';
        echo '<label>Até<br><input type="date" name="date_to" value="'.esc_attr($date_to).'"></label>';
        echo '<label>Por página<br><select name="per_page">';
        foreach([25,50,100,250] as $n) echo '<option value="'.$n.'" '.selected($per_page,$n,false).'>'.$n.'</option>';
        echo '</select></label>';
        echo '<div style="display:flex;gap:8px;flex-wrap:wrap"><button class="button button-primary">Filtrar</button>';
        $export_url = wp_nonce_url(add_query_arg([
            'page' => 'routespro-email-log',
            'client_id' => $client_id,
            'project_id' => $project_id,
            'recipient_key' => $recipient_key,
            'email_type' => $email_type,
            'message_kind' => $message_kind,
            'q' => $q,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'per_page' => $per_page,
            'rp_action' => 'export',
        ], $base_url), 'routespro_email_log_export');
        echo '<a class="button" href="'.esc_url($export_url).'">Exportar CSV</a></div>';
        echo '</form>';
        echo '<p style="margin:14px 0 10px;color:#64748b">A mostrar '.($total ? ($offset+1) : 0).' a '.min($total, $offset + $per_page).' de '.$total.' emails registados.</p>';

        if ($edit_row) {
            echo '<div style="margin:0 0 18px;padding:16px;border:1px solid #dbeafe;background:#f8fbff;border-radius:16px">';
            echo '<h3 style="margin-top:0">Editar email #'.intval($edit_row['id']).'</h3>';
            echo '<form method="post" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">';
            wp_nonce_field('routespro_email_log_save', 'routespro_email_log_nonce');
            echo '<input type="hidden" name="routespro_email_log_action" value="save">';
            echo '<input type="hidden" name="log_id" value="'.intval($edit_row['id']).'">';
            echo '<label>Destinatário<br><input type="text" name="recipient_name" value="'.esc_attr((string)$edit_row['recipient_name']).'"></label>';
            echo '<label>Email<br><input type="email" name="recipient_email" value="'.esc_attr((string)$edit_row['recipient_email']).'"></label>';
            echo '<label>Categoria<br><select name="message_kind">';
            foreach([''=>'','corretiva'=>'Corretiva','preventiva'=>'Preventiva','informacao'=>'Informação','geral'=>'Geral'] as $k=>$label) echo '<option value="'.esc_attr($k).'" '.selected((string)$edit_row['message_kind'],$k,false).'>'.esc_html($label ?: 'Sem categoria').'</option>';
            echo '</select></label>';
            echo '<label>Estado<br><select name="mail_result">';
            foreach(['sent'=>'Enviado','queued'=>'Em fila','failed'=>'Falhado','deleted'=>'Apagado','edited'=>'Editado'] as $k=>$label) echo '<option value="'.esc_attr($k).'" '.selected((string)$edit_row['mail_result'],$k,false).'>'.esc_html($label).'</option>';
            echo '</select></label>';
            echo '<label style="grid-column:1/-1">Assunto<br><input type="text" name="subject" value="'.esc_attr((string)$edit_row['subject']).'"></label>';
            echo '<div style="grid-column:1/-1;display:flex;gap:10px"><button class="button button-primary">Guardar alterações</button><a class="button" href="'.esc_url(remove_query_arg('edit_id')).'">Cancelar</a></div>';
            echo '</form></div>';
        }

        echo '<div style="overflow:auto"><table class="widefat striped"><thead><tr><th>Data</th><th>Tipo</th><th>Cliente</th><th>Campanha</th><th>Destinatário</th><th>Categoria</th><th>Assunto</th><th>Remetente</th><th>Estado</th><th>Ações</th></tr></thead><tbody>';
        if (!$rows) {
            echo '<tr><td colspan="10">Sem emails para os filtros atuais.</td></tr>';
        } else {
            foreach($rows as $row){
                $recipient = $row['recipient_user_name'] ?: $row['recipient_name'] ?: $row['recipient_email'];
                $sender = $row['sender_name'] ?: ('#' . (int)($row['sender_user_id'] ?? 0));
                $edit_url = add_query_arg([
                    'page' => 'routespro-email-log', 'edit_id' => (int)$row['id'], 'client_id'=>$client_id, 'project_id'=>$project_id, 'recipient_key'=>$recipient_key, 'email_type'=>$email_type, 'message_kind'=>$message_kind, 'q'=>$q, 'date_from'=>$date_from, 'date_to'=>$date_to, 'per_page'=>$per_page, 'paged'=>$page_num,
                ], $base_url);
                $delete_url = wp_nonce_url(add_query_arg([
                    'page' => 'routespro-email-log', 'rp_action' => 'delete', 'log_id' => (int)$row['id'], 'client_id'=>$client_id, 'project_id'=>$project_id, 'recipient_key'=>$recipient_key, 'email_type'=>$email_type, 'message_kind'=>$message_kind, 'q'=>$q, 'date_from'=>$date_from, 'date_to'=>$date_to, 'per_page'=>$per_page, 'paged'=>$page_num,
                ], $base_url), 'routespro_email_log_delete_' . (int)$row['id']);
                echo '<tr>';
                echo '<td>'.esc_html(mysql2date('Y-m-d H:i', (string)$row['created_at'], false)).'</td>';
                echo '<td>'.esc_html((string)$row['email_type']).'</td>';
                echo '<td>'.esc_html((string)($row['client_name'] ?: '-')).'</td>';
                echo '<td>'.esc_html((string)($row['project_name'] ?: '-')).'</td>';
                echo '<td>'.esc_html((string)$recipient).'<br><span style="color:#64748b">'.esc_html((string)($row['recipient_email'] ?: '')).'</span></td>';
                echo '<td>'.esc_html((string)($row['message_kind'] ?: '-')).'</td>';
                echo '<td><strong>'.esc_html((string)$row['subject']).'</strong><div style="margin-top:6px;color:#64748b;max-width:480px">'.esc_html(wp_trim_words(wp_strip_all_tags((string)$row['body']), 20, '…')).'</div></td>';
                echo '<td>'.esc_html((string)$sender).'</td>';
                echo '<td>'.esc_html((string)($row['mail_result'] ?: 'sent')).'</td>';
                echo '<td><a href="'.esc_url($edit_url).'">Editar</a> · <a href="'.esc_url($delete_url).'" onclick="return confirm(\'Apagar este registo de email?\')">Apagar</a></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';
        if ($total_pages > 1) {
            echo '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:16px">';
            for($i=max(1,$page_num-3); $i<=min($total_pages,$page_num+3); $i++){
                $url = add_query_arg([
                    'page' => 'routespro-email-log',
                    'client_id' => $client_id,
                    'project_id' => $project_id,
                    'recipient_key' => $recipient_key,
                    'email_type' => $email_type,
                    'message_kind' => $message_kind,
                    'q' => $q,
                    'date_from' => $date_from,
                    'date_to' => $date_to,
                    'per_page' => $per_page,
                    'paged' => $i,
                ], $base_url);
                echo '<a class="button '.($i===$page_num?'button-primary':'').'" href="'.esc_url($url).'">'.$i.'</a>';
            }
            echo '</div>';
        }
        echo '</div></div>';
    }

}
