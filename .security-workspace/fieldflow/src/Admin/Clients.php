<?php
namespace RoutesPro\Admin;

class Clients {
    private static function decode_meta($json): array {
        if (is_array($json)) return $json;
        if (!is_string($json) || trim($json) === '') return [];
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private static function extract_user_ids(array $meta): array {
        $values = $meta['associated_user_ids'] ?? ($meta['user_ids'] ?? []);
        if (is_string($values)) $values = preg_split('/\s*,\s*/', trim($values));
        $out = [];
        if (is_array($values)) {
            foreach ($values as $value) {
                if (is_array($value)) $value = $value['user_id'] ?? $value['id'] ?? 0;
                $id = absint($value);
                if ($id) $out[$id] = $id;
            }
        }
        ksort($out);
        return array_values($out);
    }

    public static function render() {
        if (!current_user_can('routespro_manage')) return;

        global $wpdb;
        $px    = $wpdb->prefix . 'routespro_';
        $table = $px . 'clients';
        if (class_exists('\RoutesPro\SaaS\PlanRepository')) \RoutesPro\SaaS\PlanRepository::seed();
        $saas_plans = class_exists('\RoutesPro\SaaS\PlanRepository') ? \RoutesPro\SaaS\PlanRepository::plans(true) : [];

        if (!empty($_POST['routespro_clients_nonce']) && wp_verify_nonce($_POST['routespro_clients_nonce'],'routespro_clients')) {
            $id   = absint($_POST['id'] ?? 0);
            $name = sanitize_text_field($_POST['name'] ?? '');
            $tax  = sanitize_text_field($_POST['taxid'] ?? '');
            $mail = sanitize_email($_POST['email'] ?? '');
            $phone= sanitize_text_field($_POST['phone'] ?? '');
            $saas_plan_id = absint($_POST['saas_plan_id'] ?? 0);
            $saas_status = sanitize_key((string)($_POST['saas_status'] ?? 'active'));

            if (!$name) {
                echo '<div class="error notice"><p>O nome é obrigatório.</p></div>';
            } else {
                $existing_meta = [];
                if ($id) {
                    $existing_meta_raw = $wpdb->get_var($wpdb->prepare("SELECT meta_json FROM {$table} WHERE id=%d", $id));
                    $existing_meta = self::decode_meta((string)$existing_meta_raw);
                }
                if (class_exists('\\RoutesPro\\Localization\\LanguageService')) {
                    $existing_meta['i18n'] = \RoutesPro\Localization\LanguageService::sanitiseClientI18n([
                        'default_locale' => (string)($_POST['fieldflow_default_locale'] ?? 'pt-PT'),
                        'enabled_locales' => (array)($_POST['fieldflow_enabled_locales'] ?? []),
                        'allow_user_switch' => !empty($_POST['fieldflow_allow_locale_switch']),
                        'overrides' => is_array($existing_meta['i18n']['overrides'] ?? null) ? $existing_meta['i18n']['overrides'] : [],
                    ]);
                }
                $data = [
                    'name'  => $name,
                    'taxid' => $tax,
                    'email' => $mail ?: '',
                    'phone' => $phone ?: '',
                    'meta_json' => wp_json_encode($existing_meta, JSON_UNESCAPED_UNICODE),
                ];
                $formats = ['%s','%s','%s','%s','%s'];

                if ($id) {
                    $ok = $wpdb->update($table, $data, ['id'=>$id], $formats, ['%d']);
                } else {
                    $ok = $wpdb->insert($table, $data, $formats);
                    if ($ok) { $id = (int)$wpdb->insert_id; }
                }

                if ($ok === false) {
                    echo '<div class="error notice"><p>Falha ao guardar: ' . esc_html($wpdb->last_error ?: 'DB erro') . '</p></div>';
                } else {
                    if ($id && $saas_plan_id && class_exists('\RoutesPro\SaaS\PlanRepository')) {
                        \RoutesPro\SaaS\PlanRepository::assign($id, $saas_plan_id, [
                            'status' => $saas_status,
                            'starts_at' => (string)($_POST['saas_starts_at'] ?? ''),
                            'renews_at' => (string)($_POST['saas_renews_at'] ?? ''),
                            'status_reason' => (string)($_POST['saas_status_reason'] ?? ''),
                            'notes' => (string)($_POST['saas_notes'] ?? ''),
                        ], get_current_user_id(), true);
                        if (class_exists('\RoutesPro\SaaS\EntitlementService')) \RoutesPro\SaaS\EntitlementService::clearCache();
                    }
                    echo '<div class="updated notice"><p>Guardado.</p></div>';
                }
            }
        }

        if (!empty($_GET['delete'])) {
            $del = absint($_GET['delete']);
            if ($del) {
                check_admin_referer('routespro_clients_del_'.$del);
                $ok = $wpdb->delete($table, ['id'=>$del], ['%d']);
                if ($ok === false) {
                    echo '<div class="error notice"><p>Falha ao remover: ' . esc_html($wpdb->last_error ?: 'DB erro') . '</p></div>';
                } else {
                    if (class_exists('\RoutesPro\SaaS\PlanRepository')) {
                        $wpdb->delete(\RoutesPro\SaaS\PlanRepository::subscriptionsTable(), ['client_id'=>$del], ['%d']);
                        $wpdb->delete(\RoutesPro\SaaS\PlanRepository::usageTable(), ['client_id'=>$del], ['%d']);
                        if (class_exists('\RoutesPro\SaaS\EntitlementService')) \RoutesPro\SaaS\EntitlementService::clearCache();
                    }
                    \RoutesPro\Support\Permissions::clear_scope_cache();
                    echo '<div class="updated notice"><p>Removido.</p></div>';
                }
            }
        }

        $edit = null;
        if (!empty($_GET['edit'])) {
            $id = absint($_GET['edit']);
            if ($id) {
                $edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);
            }
        }
        $edit_subscription = ($edit && class_exists('\RoutesPro\SaaS\PlanRepository')) ? \RoutesPro\SaaS\PlanRepository::subscription((int)$edit['id']) : null;
        $default_plan = class_exists('\RoutesPro\SaaS\PlanRepository') ? \RoutesPro\SaaS\PlanRepository::planBySlug('start') : null;
        $edit_i18n = class_exists('\RoutesPro\Localization\LanguageService')
            ? \RoutesPro\Localization\LanguageService::clientConfig((int)($edit['id'] ?? 0))
            : ['default_locale'=>'pt-PT','enabled_locales'=>['pt-PT'],'allow_user_switch'=>true];
        $supported_locales = class_exists('\RoutesPro\Localization\LanguageService')
            ? \RoutesPro\Localization\LanguageService::supportedLocales()
            : ['pt-PT'=>['label'=>'Português','flag'=>'🇵🇹']];

        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY name ASC, id DESC LIMIT 500", ARRAY_A);

        echo '<div class="wrap"><h1>Clientes</h1>';
        ?>
        <h2><?php echo $edit ? 'Editar Cliente' : 'Novo Cliente'; ?></h2>
        <form method="post">
            <?php wp_nonce_field('routespro_clients','routespro_clients_nonce'); ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($edit['id'] ?? 0); ?>"/>
            <table class="form-table">
              <tr>
                <th><label for="rp-name">Nome</label></th>
                <td><input id="rp-name" name="name" class="regular-text" required value="<?php echo esc_attr($edit['name'] ?? ''); ?>"/></td>
              </tr>
              <tr>
                <th><label for="rp-taxid">NIF</label></th>
                <td><input id="rp-taxid" name="taxid" class="regular-text" value="<?php echo esc_attr($edit['taxid'] ?? ''); ?>"/></td>
              </tr>
              <tr>
                <th><label for="rp-email">Email</label></th>
                <td><input id="rp-email" type="email" name="email" class="regular-text" value="<?php echo esc_attr($edit['email'] ?? ''); ?>"/></td>
              </tr>
              <tr>
                <th><label for="rp-phone">Telefone</label></th>
                <td><input id="rp-phone" name="phone" class="regular-text" value="<?php echo esc_attr($edit['phone'] ?? ''); ?>"/></td>
              </tr>
              <tr>
                <th><label for="rp-default-locale">Idioma principal</label></th>
                <td>
                  <select id="rp-default-locale" name="fieldflow_default_locale">
                    <?php foreach ($supported_locales as $locale_code=>$locale_meta): ?>
                      <option value="<?php echo esc_attr($locale_code); ?>" <?php selected((string)($edit_i18n['default_locale'] ?? 'pt-PT'), $locale_code); ?>><?php echo esc_html((string)($locale_meta['flag'] ?? '🌐') . ' ' . (string)($locale_meta['label'] ?? $locale_code)); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <p class="description">Idioma usado por defeito neste cliente no Portal, operação e app.</p>
                </td>
              </tr>
              <tr>
                <th>Idiomas disponíveis</th>
                <td>
                  <?php foreach ($supported_locales as $locale_code=>$locale_meta): ?>
                    <label style="display:inline-block;margin:0 16px 8px 0"><input type="checkbox" name="fieldflow_enabled_locales[]" value="<?php echo esc_attr($locale_code); ?>" <?php checked(in_array($locale_code, (array)($edit_i18n['enabled_locales'] ?? []), true)); ?>> <?php echo esc_html((string)($locale_meta['flag'] ?? '🌐') . ' ' . (string)($locale_meta['label'] ?? $locale_code)); ?></label>
                  <?php endforeach; ?>
                  <br><label><input type="checkbox" name="fieldflow_allow_locale_switch" value="1" <?php checked(!empty($edit_i18n['allow_user_switch'])); ?>> Permitir que o utilizador altere o idioma no front e na app</label>
                  <p class="description">A preferência fica associada ao utilizador e ao cliente. O front e a app usam tradução automática; fora de chinês preservam nomes de lojas, moradas e nomes de produtos. Em chinês a tradução pode ser total. Não requer alterações no FlutterFlow.</p>
                </td>
              </tr>
              <tr>
                <th><label for="rp-saas-plan">Plano FieldFlow</label></th>
                <td>
                  <select id="rp-saas-plan" name="saas_plan_id">
                    <?php foreach ($saas_plans as $plan): ?>
                      <option value="<?php echo intval($plan['id']); ?>" <?php selected((int)($edit_subscription['plan_id'] ?? ($default_plan['id'] ?? 0)), (int)$plan['id']); ?>><?php echo esc_html($plan['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select name="saas_status">
                    <?php foreach (['trial'=>'Teste','active'=>'Ativo','past_due'=>'Pagamento pendente','suspended'=>'Suspenso','expired'=>'Expirado','cancelled'=>'Cancelado'] as $status_key=>$status_label): ?>
                      <option value="<?php echo esc_attr($status_key); ?>" <?php selected((string)($edit_subscription['status'] ?? 'active'), $status_key); ?>><?php echo esc_html($status_label); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <p class="description">O plano controla os módulos no BO, portais, app, API, IA e exportações.</p>
                </td>
              </tr>
              <tr>
                <th>Datas comerciais</th>
                <td>
                  <label>Início <input type="datetime-local" name="saas_starts_at" value="<?php echo esc_attr(!empty($edit_subscription['starts_at']) ? date('Y-m-d\TH:i', strtotime((string)$edit_subscription['starts_at'])) : ''); ?>"></label>
                  <label style="margin-left:12px">Renovação <input type="datetime-local" name="saas_renews_at" value="<?php echo esc_attr(!empty($edit_subscription['renews_at']) ? date('Y-m-d\TH:i', strtotime((string)$edit_subscription['renews_at'])) : ''); ?>"></label>
                </td>
              </tr>
              <tr>
                <th>Motivo do estado</th>
                <td><input name="saas_status_reason" class="large-text" value="<?php echo esc_attr((string)($edit_subscription['status_reason'] ?? '')); ?>" placeholder="Ex.: renovação pendente ou suspensão solicitada"></td>
              </tr>
              <tr>
                <th>Notas de subscrição</th>
                <td><textarea name="saas_notes" rows="3" class="large-text"><?php echo esc_textarea((string)($edit_subscription['notes'] ?? '')); ?></textarea></td>
              </tr>
              <tr>
                <th>Acessos e utilizadores</th>
                <td>
                  <p class="description"><strong>Fonte única:</strong> as associações deste cliente são geridas exclusivamente no Intelligence Center, evitando configurações duplicadas.</p>
                  <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=routespro-assignments-hub&tab=clients&client_id=' . (int)($edit['id'] ?? 0))); ?>">Abrir Intelligence Center</a>
                </td>
              </tr>
            </table>
            <p><button class="button button-primary">Guardar</button></p>
        </form>

        <h2 style="margin-top:2em">Lista</h2>
        <table class="widefat striped">
          <thead>
            <tr>
              <th style="width:70px">ID</th>
              <th>Nome</th>
              <th>NIF</th>
              <th>Email</th>
              <th>Telefone</th>
              <th>Idioma</th>
              <th>Plano</th>
              <th>Estado</th>
              <th>Acessos</th>
              <th style="width:160px"></th>
            </tr>
          </thead>
          <tbody>
          <?php if ($rows): foreach($rows as $r): ?>
            <?php $user_ids = class_exists('\RoutesPro\Support\AssignmentResolver') ? \RoutesPro\Support\AssignmentResolver::get_client_user_ids((int)$r['id']) : []; ?>
            <tr>
              <td><?php echo intval($r['id']); ?></td>
              <td><?php echo esc_html($r['name']); ?></td>
              <td><?php echo esc_html($r['taxid']); ?></td>
              <td><?php echo esc_html($r['email']); ?></td>
              <td><?php echo esc_html($r['phone']); ?></td>
              <?php $row_i18n = class_exists('\RoutesPro\Localization\LanguageService') ? \RoutesPro\Localization\LanguageService::clientConfig((int)$r['id']) : ['default_locale'=>'pt-PT','enabled_locales'=>['pt-PT']]; ?>
              <td><?php echo esc_html((string)($row_i18n['default_locale'] ?? 'pt-PT') . ' · ' . count((array)($row_i18n['enabled_locales'] ?? [])) . ' idioma(s)'); ?></td>
              <?php $row_subscription = class_exists('\RoutesPro\SaaS\PlanRepository') ? \RoutesPro\SaaS\PlanRepository::subscription((int)$r['id']) : null; ?>
              <td><?php echo esc_html((string)($row_subscription['plan_name'] ?? 'Compatibilidade total')); ?></td>
              <td><?php echo esc_html(class_exists('\RoutesPro\SaaS\EntitlementService') ? \RoutesPro\SaaS\EntitlementService::clientStatus((int)$r['id']) : (string)($row_subscription['status'] ?? 'legacy')); ?></td>
              <td><a href="<?php echo esc_url(admin_url('admin.php?page=routespro-assignments-hub&tab=clients&client_id=' . (int)$r['id'])); ?>"><?php echo esc_html(count($user_ids) . ' no Intelligence Center'); ?></a></td>
              <td>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=routespro-clients&edit='.$r['id']), 'routespro_clients_edit_'.$r['id'] ) ); ?>">Editar</a> |
                <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=routespro-clients&delete='.$r['id']), 'routespro_clients_del_'.$r['id'] ) ); ?>" onclick="return confirm('Remover este cliente?')">Remover</a>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="10"><em>Sem clientes.</em></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
        <?php
        echo '</div>';
    }
}
