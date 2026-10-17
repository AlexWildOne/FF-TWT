<?php
namespace RoutesPro\Admin;

use RoutesPro\Support\AssignmentMatrix;
use RoutesPro\Support\AssignmentResolver;
use RoutesPro\Support\Config;
use RoutesPro\Forms\BindingResolver;
use RoutesPro\Forms\Forms as FormsModule;
use RoutesPro\Forms\ContextQuestions as ContextQuestionService;
use RoutesPro\Analytics\QuestionAnalyticsService;
use RoutesPro\Analytics\AnalyticsAIService;
use RoutesPro\Analytics\OperationalIntelligenceService;
use RoutesPro\Analytics\WidgetOrchestrator;
use RoutesPro\Analytics\RouteMobilityWidgetService;
use RoutesPro\Analytics\RecommendationLearningService;
use RoutesPro\Analytics\AdaptiveIntelligenceService;
use RoutesPro\Services\MobilityCostService;
use RoutesPro\Services\RouteMetricsService;
use RoutesPro\Services\RouteEndpointProfileService;
use RoutesPro\Services\RoutingGeographyService;

class Assignments {
    // Historical Release Gate marker: Reparar partida, chegada e km de rotas por planear

    private static function get_roles(){
        $roles = get_option('routespro_roles');
        if (!is_array($roles) || !$roles) {
            $roles = ['driver','merchandiser','sales','supervisor','implementador','operacional','owner'];
            update_option('routespro_roles', $roles);
        }
        $roles = array_values(array_unique(array_filter(array_map(function($r){ return trim(sanitize_text_field($r)); }, $roles))));
        foreach (['operacional','owner'] as $required_role) {
            if (!in_array($required_role, $roles, true)) $roles[] = $required_role;
        }
        return array_values(array_unique($roles));
    }

    private static function save_roles($roles){
        $clean = array_values(array_unique(array_filter(array_map(function($r){ return trim(sanitize_text_field($r)); }, (array)$roles))));
        update_option('routespro_roles', $clean);
        return $clean;
    }

    private static function admin_url(array $args = []): string {
        return add_query_arg($args, admin_url('admin.php?page=routespro-assignments-hub'));
    }

    private static function selected_multi(array $selected, int $candidate): string {
        return in_array($candidate, $selected, true) ? ' selected' : '';
    }

    private static function redirect_with_state(string $tab, array $args = []): void {
        $base = [
            'page' => 'routespro-assignments-hub',
            'tab' => $tab,
        ];
        foreach (['client_id','project_id','route_id'] as $key) {
            $value = absint($_POST[$key] ?? $_GET[$key] ?? 0);
            if ($value > 0) $base[$key] = $value;
        }
        $url = add_query_arg(array_merge($base, $args), admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    private static function render_status_notice(): void {
        $status = sanitize_key($_GET['ff_status'] ?? '');
        if (!$status) return;
        $map = [
            'client_saved' => ['success', 'Atribuição de cliente guardada com sucesso.'],
            'project_saved' => ['success', 'Atribuição de projeto guardada com sucesso.'],
            'route_saved' => ['success', 'Atribuição de rota guardada com sucesso.'],
            'mobility_default_saved' => ['success', 'Perfil de mobilidade default da campanha guardado com sucesso.'],
            'mobility_profile_saved' => ['success', 'Perfil de mobilidade do operativo guardado com sucesso.'],
            'mobility_backfill_done' => ['success', 'Custos de mobilidade aplicados às rotas selecionadas. As rotas existentes mantiveram a ordem, PDVs e métricas canónicas.'],
            'routing_geography_reconciled' => ['success', 'Geografia de routing revalidada no país da campanha. Endpoints, PDVs e métricas das rotas selecionadas foram reconciliados antes de novos cálculos e emails.'],
            'route_distance_repair_done' => ['success', 'Integridade de distância reparada nas rotas elegíveis. Foram preservados PDVs, ordem e histórico.'],
            'mobility_route_override_saved' => ['success', 'Override de mobilidade da rota guardado e snapshot atualizado.'],
            'mobility_invalid' => ['error', 'Escolhe uma campanha válida e confirma os dados do perfil de mobilidade.'],
            'mobility_persist_failed' => ['error', 'O perfil foi recebido, mas o FieldFlow não conseguiu confirmar a persistência na base de dados. Nenhuma estimativa deve ser considerada atualizada até voltares a guardar.'],
            'endpoint_default_saved' => ['success', 'Base de rota default da campanha guardada e confirmada.'],
            'endpoint_profile_saved' => ['success', 'Base de rota do operativo guardada e confirmada.'],
            'endpoint_profile_invalid' => ['error', 'Confirma a partida e a chegada da base de rota.'],
            'endpoint_profile_persist_failed' => ['error', 'A base de rota foi recebida, mas o FieldFlow não conseguiu confirmar a persistência.'],
            'endpoint_legacy_migrated' => ['success', 'Defaults anteriores de partida e chegada migrados para a fonte canónica.'],
            'binding_saved' => ['success', 'Ligação de formulário guardada com sucesso.'],
            'binding_deleted' => ['success', 'Ligação de formulário removida com sucesso.'],
            'roles_saved' => ['success', 'Função adicionada com sucesso.'],
            'save_error' => ['error', 'Ocorreu um problema ao gravar. Verifica os dados e tenta novamente.'],
            'binding_invalid' => ['error', 'Para guardar a ligação do formulário tens de escolher um formulário e pelo menos um âmbito.'],
            'analytics_binding_saved' => ['success', 'Ligação analítica guardada com sucesso.'],
            'analytics_binding_deleted' => ['success', 'Ligação analítica removida com sucesso.'],
            'analytics_binding_invalid' => ['error', 'Para guardar a ligação analítica tens de escolher formulário, pergunta, métrica e label.'],
            'analytics_dashboard_saved' => ['success', 'Dashboard analítico guardado com sucesso.'],
            'analytics_dashboard_content_saved' => ['success', 'Conteúdo, visualização e ordem do dashboard guardados com sucesso.'],
            'analytics_ai_dashboard_created' => ['success', 'Dashboard Analytics IA criado e publicado com sucesso.'],
            'analytics_dashboard_deleted' => ['success', 'Dashboard analítico removido. Os widgets associados foram preservados no dashboard automático.'],
            'analytics_dashboard_invalid' => ['error', 'Para criar um dashboard analítico tens de indicar pelo menos o nome.'],
            'analytics_group_saved' => ['success', 'Grupo de lojas guardado com sucesso.'],
            'analytics_group_deleted' => ['success', 'Grupo de lojas removido com sucesso.'],
            'analytics_group_invalid' => ['error', 'Para criar um grupo de lojas tens de indicar nome e pelo menos uma loja.'],
            'analytics_evergreen_reconciled' => ['success', 'Dashboard otimizado. Perguntas pontuais, famílias operacionais e evidências foram consolidadas; duplicados antigos ficaram apenas como legado, sem apagar histórico.'],
            'learning_feedback_saved' => ['success', 'Feedback Intelligence guardado. O sinal fica registado no dataset de aprendizagem.'],
            'feedback_error' => ['error', 'Não foi possível guardar o feedback da recomendação.'],
        ];
        if (empty($map[$status])) return;
        [$kind, $message] = $map[$status];
        if ($status === 'mobility_invalid' && !empty($_GET['mobility_issue'])) {
            $issue = sanitize_text_field(wp_unslash((string)$_GET['mobility_issue']));
            if ($issue !== '') $message .= ' ' . $issue;
        }
        if ($status === 'endpoint_profile_invalid' && !empty($_GET['endpoint_issue'])) {
            $issue = sanitize_text_field(wp_unslash((string)$_GET['endpoint_issue']));
            if ($issue !== '') $message .= ' ' . $issue;
        }
        echo '<div class="notice notice-' . esc_attr($kind) . ' is-dismissible"><p><strong>' . esc_html($message) . '</strong></p></div>';
    }

    private static function render_user_options(array $users, array $selected = []): string {
        $html = '';
        foreach ($users as $user) {
            $label = ($user->display_name ?: $user->user_login) . ' [' . $user->user_login . ']';
            if (!empty($user->user_email)) $label .= ' • ' . $user->user_email;
            $html .= '<option value="' . (int)$user->ID . '"' . self::selected_multi($selected, (int)$user->ID) . '>' . esc_html($label) . '</option>';
        }
        return $html;
    }

    private static function resolve_selected_role(array $roles, string $current_role, string $fallback): array {
        $selected_role = trim(sanitize_text_field($current_role));
        if ($selected_role === '') $selected_role = $fallback;
        if (!in_array($selected_role, $roles, true)) $roles[] = $selected_role;
        return [array_values(array_unique($roles)), $selected_role];
    }


    private static function find_client_name(array $clients, int $client_id): string {
        foreach ($clients as $client) {
            if ((int)($client['id'] ?? 0) == $client_id) return (string)($client['name'] ?? '');
        }
        return '';
    }

    private static function find_project_name(array $projects, int $project_id): string {
        foreach ($projects as $project) {
            if ((int)($project['id'] ?? 0) == $project_id) return (string)($project['name'] ?? '');
        }
        return '';
    }



    private static function mobility_profile_from_post(string $prefix = 'mobility_'): array {
        return MobilityCostService::normalizeProfile([
            'enabled' => !empty($_POST[$prefix . 'enabled']) ? 1 : 0,
            'energy_type' => sanitize_key((string)($_POST[$prefix . 'energy_type'] ?? 'diesel')),
            'vehicle_label' => sanitize_text_field((string)($_POST[$prefix . 'vehicle_label'] ?? '')),
            'fuel_type' => sanitize_key((string)($_POST[$prefix . 'fuel_type'] ?? 'diesel')),
            'hybrid_mode' => sanitize_key((string)($_POST[$prefix . 'hybrid_mode'] ?? 'standard')),
            'fuel_price_eur_per_l' => wp_unslash((string)($_POST[$prefix . 'fuel_price_eur_per_l'] ?? '0')),
            'consumption_l_per_100km' => wp_unslash((string)($_POST[$prefix . 'consumption_l_per_100km'] ?? '0')),
            'electricity_price_eur_per_kwh' => wp_unslash((string)($_POST[$prefix . 'electricity_price_eur_per_kwh'] ?? '0')),
            'consumption_kwh_per_100km' => wp_unslash((string)($_POST[$prefix . 'consumption_kwh_per_100km'] ?? '0')),
            'electric_share_pct' => wp_unslash((string)($_POST[$prefix . 'electric_share_pct'] ?? '50')),
            'operational_cost_eur_per_km' => wp_unslash((string)($_POST[$prefix . 'operational_cost_eur_per_km'] ?? '0')),
            'valid_from' => sanitize_text_field((string)($_POST[$prefix . 'valid_from'] ?? '')),
        ]);
    }

    private static function endpoint_profile_from_post(string $prefix = 'endpoint_'): array {
        $countryCode = class_exists(RoutingGeographyService::class) ? RoutingGeographyService::normalizeCountryCode((string)($_POST[$prefix . 'country_code'] ?? 'PT')) : strtoupper((string)($_POST[$prefix . 'country_code'] ?? 'PT'));
        $returnToStart = !empty($_POST[$prefix . 'return_to_start']);
        $start = [
            'address' => sanitize_text_field((string)($_POST[$prefix . 'start_address'] ?? '')),
            'lat' => is_numeric($_POST[$prefix . 'start_lat'] ?? null) ? (float)$_POST[$prefix . 'start_lat'] : null,
            'lng' => is_numeric($_POST[$prefix . 'start_lng'] ?? null) ? (float)$_POST[$prefix . 'start_lng'] : null,
            'provenance' => 'endpoint_profile',
            'country_code' => $countryCode,
        ];
        $end = [
            'address' => sanitize_text_field((string)($_POST[$prefix . 'end_address'] ?? '')),
            'lat' => is_numeric($_POST[$prefix . 'end_lat'] ?? null) ? (float)$_POST[$prefix . 'end_lat'] : null,
            'lng' => is_numeric($_POST[$prefix . 'end_lng'] ?? null) ? (float)$_POST[$prefix . 'end_lng'] : null,
            'provenance' => 'endpoint_profile',
            'country_code' => $countryCode,
        ];
        if ($returnToStart) $end = $start;
        return RouteEndpointProfileService::normalizeProfile([
            'enabled' => !empty($_POST[$prefix . 'enabled']) ? 1 : 0,
            'start_point' => $start,
            'end_point' => $end,
            'return_to_start' => $returnToStart ? 1 : 0,
            'country_code' => $countryCode,
        ], 'endpoint_profile', $countryCode);
    }

    private static function render_endpoint_profile_fields(array $profile, string $prefix = 'endpoint_'): void {
        $countryCode = class_exists(RoutingGeographyService::class) ? RoutingGeographyService::normalizeCountryCode((string)($profile['country_code'] ?? 'PT')) : strtoupper((string)($profile['country_code'] ?? 'PT'));
        $profile = RouteEndpointProfileService::normalizeProfile($profile, (string)($profile['source'] ?? 'endpoint_profile'), $countryCode);
        $start = (array)($profile['start_point'] ?? []);
        $end = (array)($profile['end_point'] ?? []);
        echo '<div class="ff-form-grid ff-endpoint-profile" data-ff-endpoint-profile data-country="' . esc_attr(strtolower($countryCode)) . '"><input type="hidden" name="' . esc_attr($prefix . 'country_code') . '" value="' . esc_attr($countryCode) . '">';
        echo '<p style="grid-column:1/-1"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="' . esc_attr($prefix . 'enabled') . '" value="1"' . checked(!empty($profile['enabled']), true, false) . '> <strong>Usar esta base habitual</strong></label><span class="ff-note">Fallback opcional. Uma partida/chegada preenchida diretamente em Campanhas PDV, numa rota ou no Planear tem prioridade e é reutilizada. Nunca é substituída silenciosamente pelo primeiro ou último PDV.</span></p>';
        echo '<p><label><strong>Partida habitual</strong><br><input type="text" name="' . esc_attr($prefix . 'start_address') . '" value="' . esc_attr((string)($start['address'] ?? '')) . '" placeholder="Pesquisar morada no Google" autocomplete="off" data-ff-endpoint-address="start" data-lat-name="' . esc_attr($prefix . 'start_lat') . '" data-lng-name="' . esc_attr($prefix . 'start_lng') . '" style="width:100%"></label><input type="hidden" name="' . esc_attr($prefix . 'start_lat') . '" value="' . esc_attr((string)($start['lat'] ?? '')) . '"><input type="hidden" name="' . esc_attr($prefix . 'start_lng') . '" value="' . esc_attr((string)($start['lng'] ?? '')) . '"></p>';
        echo '<p><label><strong>Chegada habitual</strong><br><input type="text" name="' . esc_attr($prefix . 'end_address') . '" value="' . esc_attr((string)($end['address'] ?? '')) . '" placeholder="Pesquisar morada no Google" autocomplete="off" data-ff-endpoint-address="end" data-lat-name="' . esc_attr($prefix . 'end_lat') . '" data-lng-name="' . esc_attr($prefix . 'end_lng') . '" style="width:100%"></label><input type="hidden" name="' . esc_attr($prefix . 'end_lat') . '" value="' . esc_attr((string)($end['lat'] ?? '')) . '"><input type="hidden" name="' . esc_attr($prefix . 'end_lng') . '" value="' . esc_attr((string)($end['lng'] ?? '')) . '"></p>';
        echo '<p style="grid-column:1/-1"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="' . esc_attr($prefix . 'return_to_start') . '" value="1"' . checked(!empty($profile['return_to_start']), true, false) . '> Normalmente regressa ao ponto de partida</label></p>';
        echo '</div>';
    }

    private static function render_endpoint_profile_autocomplete_script(): void {
        $settings = Config::all();
        $gmKey = trim((string)($settings['google_maps_key'] ?? ''));
        echo '<script>(function(){const key=' . wp_json_encode($gmKey) . '; const inputs=Array.from(document.querySelectorAll("[data-ff-endpoint-address]")); if(!inputs.length) return; function country(input){return String(input.closest("[data-ff-endpoint-profile]")?.dataset.country||"pt").toLowerCase();} function refs(input){const form=input.closest("form")||document; return {lat:form.querySelector("input[name=\""+CSS.escape(input.dataset.latName||"")+"\"]"),lng:form.querySelector("input[name=\""+CSS.escape(input.dataset.lngName||"")+"\"]")};} function clearCoords(input){const r=refs(input); if(r.lat)r.lat.value=""; if(r.lng)r.lng.value="";} function apply(input,place){if(!place)return; const r=refs(input); input.value=String(place.formatted_address||input.value||""); if(place.geometry&&r.lat&&r.lng){r.lat.value=String(place.geometry.location.lat()); r.lng.value=String(place.geometry.location.lng());} input.dispatchEvent(new Event("change",{bubbles:true}));} function bind(){if(!(window.google&&google.maps&&google.maps.places&&google.maps.places.Autocomplete))return false; inputs.forEach(function(input){if(input.dataset.ffEndpointAcBound==="1")return; input.dataset.ffEndpointAcBound="1"; const c=country(input); const ac=new google.maps.places.Autocomplete(input,{componentRestrictions:{country:c},fields:["formatted_address","geometry","name","place_id","address_components"]}); ac.addListener("place_changed",function(){apply(input,ac.getPlace());}); input.addEventListener("input",function(){clearCoords(input);}); input.addEventListener("blur",function(){const r=refs(input); if(!input.value.trim()||!r.lat||!r.lng||r.lat.value||r.lng.value||!(window.google&&google.maps&&google.maps.Geocoder))return; const geocoder=new google.maps.Geocoder(); geocoder.geocode({address:input.value.trim(),region:c,componentRestrictions:{country:c.toUpperCase()}},function(results,status){if(status==="OK"&&results&&results[0])apply(input,results[0]);});});}); return true;} if(bind())return; if(window.FieldFlowGoogleMaps&&typeof window.FieldFlowGoogleMaps.load==="function"){const c=country(inputs[0]);window.FieldFlowGoogleMaps.load({key:key,libraries:["places"],language:c==="es"?"es-ES":"pt-PT",region:c.toUpperCase()}).then(function(ok){if(ok)bind();});}})();</script>';
    }

    private static function render_mobility_profile_fields(array $profile, string $prefix = 'mobility_'): void {
        $profile = MobilityCostService::normalizeProfile($profile);
        echo '<div class="ff-form-grid ff-mobility-profile" data-mobility-profile>';
        echo '<p><label><strong>Ativar cálculo</strong><br><label style="display:flex;align-items:center;gap:8px;margin-top:8px"><input type="checkbox" name="' . esc_attr($prefix . 'enabled') . '" value="1" ' . checked(!empty($profile['enabled']), true, false) . '> Usar este perfil nas estimativas</label></label></p>';
        echo '<p><label><strong>Tipo de energia</strong><br><select name="' . esc_attr($prefix . 'energy_type') . '" data-mobility-energy style="width:100%">';
        foreach (MobilityCostService::energyTypes() as $value => $label) echo '<option value="' . esc_attr($value) . '"' . selected((string)$profile['energy_type'], (string)$value, false) . '>' . esc_html($label) . '</option>';
        echo '</select></label></p>';
        echo '<p><label><strong>Viatura / identificação</strong><br><input type="text" name="' . esc_attr($prefix . 'vehicle_label') . '" value="' . esc_attr((string)$profile['vehicle_label']) . '" placeholder="Ex.: Opel Astra 34-AA-00" style="width:100%"></label></p>';
        echo '<p data-mobility-fuel-base><label><strong>Combustível base</strong><br><select name="' . esc_attr($prefix . 'fuel_type') . '" style="width:100%">';
        foreach (MobilityCostService::fuelTypes() as $value => $label) echo '<option value="' . esc_attr($value) . '"' . selected((string)$profile['fuel_type'], (string)$value, false) . '>' . esc_html($label) . '</option>';
        echo '</select></label></p>';
        echo '<p data-mobility-hybrid><label><strong>Tipo de híbrido</strong><br><select name="' . esc_attr($prefix . 'hybrid_mode') . '" style="width:100%"><option value="standard"' . selected((string)$profile['hybrid_mode'], 'standard', false) . '>Híbrido não plug-in</option><option value="plug_in"' . selected((string)$profile['hybrid_mode'], 'plug_in', false) . '>Híbrido plug-in</option></select></label></p>';
        echo '<p data-mobility-fuel><label><strong>Preço combustível €/L</strong><br><input type="number" min="0" max="10" step="0.001" name="' . esc_attr($prefix . 'fuel_price_eur_per_l') . '" value="' . esc_attr((string)$profile['fuel_price_eur_per_l']) . '" style="width:100%"></label></p>';
        echo '<p data-mobility-fuel><label><strong>Consumo L/100 km</strong><br><input type="number" min="0" max="80" step="0.1" name="' . esc_attr($prefix . 'consumption_l_per_100km') . '" value="' . esc_attr((string)$profile['consumption_l_per_100km']) . '" style="width:100%"></label></p>';
        echo '<p data-mobility-electric><label><strong>Preço eletricidade €/kWh</strong><br><input type="number" min="0" max="5" step="0.001" name="' . esc_attr($prefix . 'electricity_price_eur_per_kwh') . '" value="' . esc_attr((string)$profile['electricity_price_eur_per_kwh']) . '" style="width:100%"></label></p>';
        echo '<p data-mobility-electric><label><strong>Consumo kWh/100 km</strong><br><input type="number" min="0" max="100" step="0.1" name="' . esc_attr($prefix . 'consumption_kwh_per_100km') . '" value="' . esc_attr((string)$profile['consumption_kwh_per_100km']) . '" style="width:100%"></label></p>';
        echo '<p data-mobility-plugin><label><strong>Percentagem elétrica estimada</strong><br><input type="number" min="0" max="100" step="1" name="' . esc_attr($prefix . 'electric_share_pct') . '" value="' . esc_attr((string)$profile['electric_share_pct']) . '" style="width:100%"> <span class="ff-note">Aplicável ao híbrido plug-in.</span></label></p>';
        echo '<p><label><strong>Custo operacional €/km</strong><br><input type="number" min="0" max="10" step="0.001" name="' . esc_attr($prefix . 'operational_cost_eur_per_km') . '" value="' . esc_attr((string)$profile['operational_cost_eur_per_km']) . '" style="width:100%"></label><br><span class="ff-note">Opcional. Mantém desgaste/renting separado do combustível ou energia.</span></p>';
        echo '<p><label><strong>Válido desde</strong><br><input type="date" name="' . esc_attr($prefix . 'valid_from') . '" value="' . esc_attr((string)$profile['valid_from']) . '" style="width:100%"></label><br><span class="ff-note">O perfil só é elegível para rotas nesta data ou posteriores. O snapshot preserva os valores usados em cada rota.</span></p>';
        echo '</div>';
        echo '<div class="ff-note" style="margin-top:10px;padding:12px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc"><strong>Como preencher:</strong> Gasolina, Gasóleo ou GPL: preço €/L + consumo L/100 km. Híbrido não plug-in: combustível base + preço €/L + L/100 km. Híbrido plug-in: os mesmos dados do combustível + preço €/kWh + kWh/100 km + percentagem elétrica estimada. 100% elétrico: preço €/kWh + kWh/100 km. O custo operacional €/km é opcional e fica sempre separado da energia.</div>';
    }

    private static function render_mobility_script(): void {
        echo <<<'HTML'
<script>
(function(){
  function sync(root){
    if(!root) return;
    const type=root.querySelector('[data-mobility-energy]')?.value||'diesel';
    const hybrid=root.querySelector('select[name$="hybrid_mode"]')?.value||'standard';
    root.querySelectorAll('[data-mobility-fuel]').forEach(el=>el.hidden=(type==='electric'));
    root.querySelectorAll('[data-mobility-fuel-base]').forEach(el=>el.hidden=(type!=='hybrid'));
    root.querySelectorAll('[data-mobility-hybrid]').forEach(el=>el.hidden=(type!=='hybrid'));
    root.querySelectorAll('[data-mobility-electric]').forEach(el=>el.hidden=!(type==='electric'||(type==='hybrid'&&hybrid==='plug_in')));
    root.querySelectorAll('[data-mobility-plugin]').forEach(el=>el.hidden=!(type==='hybrid'&&hybrid==='plug_in'));
  }
  document.querySelectorAll('[data-mobility-profile]').forEach(root=>{
    root.querySelectorAll('select').forEach(el=>el.addEventListener('change',()=>sync(root)));
    sync(root);
  });
})();
</script>
HTML;
    }


    private static function analytics_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_form_analytics_bindings';
    }

    private static function analytics_dashboard_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_analytics_dashboards';
    }

    private static function analytics_store_group_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_analytics_store_groups';
    }

    private static function analytics_store_group_items_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_analytics_store_group_items';
    }

    private static function extract_schema_fields(string $schema_json): array {
        $schema_json = trim($schema_json);
        if ($schema_json === '') return [];
        $decoded = json_decode($schema_json, true);
        if (!is_array($decoded)) return [];
        $out = []; $seen = [];
        $walk = static function($node) use (&$walk, &$out, &$seen): void {
            if (!is_array($node)) return;
            $key = sanitize_key((string)($node['key'] ?? $node['name'] ?? $node['slug'] ?? ''));
            $type = sanitize_key((string)($node['type'] ?? $node['question_type'] ?? ''));
            if ($key !== '' && ($type !== '' || isset($node['label']) || isset($node['title'])) && empty($seen[$key])) {
                $seen[$key] = true;
                $out[] = [
                    'question_key' => $key,
                    'question_label' => (string)($node['label'] ?? $node['title'] ?? $key),
                    'question_type' => $type !== '' ? $type : 'text',
                    'source' => 'schema',
                ];
            }
            foreach ($node as $childKey => $child) {
                if (in_array((string)$childKey, ['options','products','meta','theme'], true)) continue;
                if (is_array($child)) $walk($child);
            }
        };
        $walk($decoded);
        return $out;
    }

    private static function analytics_visibility_label(string $visibility): string {
        return [
            'client_portal' => 'Portal do cliente',
            'fieldflow_app' => 'App do merchandiser',
            'both' => 'Cliente e merchandiser',
            'all' => 'Todos os canais',
        ][$visibility] ?? 'Portal do cliente';
    }

    private static function analytics_options(string $type): array {
        $map = [
            'chart' => [
                'line' => 'Linha',
                'bar' => 'Barras',
                'pie' => 'Pizza',
                'image_gallery' => 'Imagens / evidências por loja',
                'table' => 'Tabela',
                'kpi' => 'KPI',
            ],
            'aggregation' => [
                'latest' => 'Último valor',
                'sum' => 'Soma',
                'avg' => 'Média',
                'count' => 'Contagem',
                'evidence' => 'Todas as evidências',
            ],
            'dimension' => [
                'submitted_at' => 'Data da visita',
                'location_id' => 'Local',
                'route_id' => 'Rota',
                'project_id' => 'Campanha',
                'client_id' => 'Cliente',
            ],
            'evidence_grouping' => [
                'location_date' => 'Local, data e imagens',
                'date_location' => 'Data, local e imagens',
                'location' => 'Apenas por local',
            ],
            'scope' => [
                'client_project_location' => 'Cliente + campanha + local',
                'client_project' => 'Cliente + campanha',
                'client_only' => 'Só cliente',
                'global' => 'Global',
            ],
        ];
        return $map[$type] ?? [];
    }

    private static function render_simple_options(array $options, string $selected = ''): string {
        $html = '';
        foreach ($options as $value => $label) {
            $html .= '<option value="' . esc_attr((string)$value) . '"' . selected($selected, (string)$value, false) . '>' . esc_html((string)$label) . '</option>';
        }
        return $html;
    }

    private static function analytics_question_options(array $questions): string {
        if (!$questions) {
            return '<option value="">Sem perguntas disponíveis ainda</option>';
        }
        $html = '<option value="">Seleciona uma pergunta</option>';
        $currentFormId = 0;
        foreach ($questions as $row) {
            $formId = (int)($row['form_id'] ?? 0);
            if ($formId !== $currentFormId) {
                if ($currentFormId !== 0) {
                    $html .= '</optgroup>';
                }
                $currentFormId = $formId;
                $html .= '<optgroup label="Formulário #' . $formId . '">';
            }
            $key = (string)($row['question_key'] ?? '');
            $label = (string)($row['question_label'] ?? $key);
            $html .= '<option value="' . esc_attr($key) . '">' . esc_html($label . ' [' . $key . ']') . '</option>';
        }
        if ($currentFormId !== 0) {
            $html .= '</optgroup>';
        }
        return $html;
    }

    private static function render_tab_nav(string $tab): void {
        $tabs = [
            'overview' => 'Command Center',
            'operations' => 'Operação diária',
            'clients' => 'Clientes',
            'projects' => 'Projetos',
            'routes' => 'Rotas',
            'mobility' => 'Mobilidade',
            'forms' => 'Formulários',
            'analytics' => 'Analytics & IA',
            'learning' => 'Aprendizagem',
        ];
        echo '<nav class="nav-tab-wrapper" style="margin-bottom:18px">';
        foreach ($tabs as $slug => $label) {
            $class = $slug === $tab ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url(self::admin_url(['tab' => $slug])) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
    }

    private static function intelligence_overview_state(int $client_id, int $project_id, int $route_id): array {
        global $wpdb;
        $dashTable = self::analytics_dashboard_table();
        $bindingTable = self::analytics_table();
        $formBindingTable = BindingResolver::table();

        $dashWhere = ['is_active=1'];
        $dashArgs = [];
        foreach (['client_id'=>$client_id,'project_id'=>$project_id,'route_id'=>$route_id] as $field=>$value) {
            if ($value > 0) {
                $dashWhere[] = "({$field} IS NULL OR {$field}=0 OR {$field}=%d)";
                $dashArgs[] = $value;
            }
        }
        $dashSql = "SELECT id,ai_enabled FROM {$dashTable} WHERE " . implode(' AND ', $dashWhere) . ' ORDER BY sort_order ASC,id ASC';
        $dashRows = $dashArgs ? ($wpdb->get_results($wpdb->prepare($dashSql, ...$dashArgs), ARRAY_A) ?: []) : ($wpdb->get_results($dashSql, ARRAY_A) ?: []);
        $dashboardIds = array_values(array_filter(array_map('intval', array_column($dashRows, 'id'))));
        $aiDashboards = count(array_filter($dashRows, static fn($row)=>!empty($row['ai_enabled'])));

        $widgetRows = $wpdb->get_results("SELECT id,settings_json FROM {$bindingTable} WHERE is_active=1 ORDER BY id ASC", ARRAY_A) ?: [];
        $widgets = 0;
        foreach ($widgetRows as $row) {
            $settings = json_decode((string)($row['settings_json'] ?? '{}'), true);
            if (!is_array($settings)) $settings = [];
            $matches = true;
            foreach (['client_id'=>$client_id,'project_id'=>$project_id,'route_id'=>$route_id] as $field=>$value) {
                $expected = absint($settings[$field] ?? 0);
                if ($value > 0 && $expected > 0 && $expected !== $value) { $matches = false; break; }
            }
            $dashboardId = absint($settings['dashboard_id'] ?? 0);
            if ($matches && $dashboardId > 0 && !in_array($dashboardId, $dashboardIds, true)) $matches = false;
            if ($matches) $widgets++;
        }

        $formWhere = ['is_active=1'];
        $formArgs = [];
        foreach (['client_id'=>$client_id,'project_id'=>$project_id,'route_id'=>$route_id] as $field=>$value) {
            if ($value > 0) {
                $formWhere[] = "({$field} IS NULL OR {$field}=0 OR {$field}=%d)";
                $formArgs[] = $value;
            }
        }
        $formSql = "SELECT COUNT(*) FROM {$formBindingTable} WHERE " . implode(' AND ', $formWhere);
        $formBindings = (int)($formArgs ? $wpdb->get_var($wpdb->prepare($formSql, ...$formArgs)) : $wpdb->get_var($formSql));

        $clientUsers = $client_id > 0 ? count(AssignmentResolver::get_client_user_ids($client_id)) : 0;
        $projectUsers = 0;
        $projectOwners = 0;
        $projectPeople = 0;
        if ($project_id > 0) {
            $project = AssignmentResolver::get_project_context($project_id);
            $projectUserIds = array_values(array_filter(array_map('absint', (array)($project['associated_user_ids'] ?? []))));
            $projectOwnerIds = array_values(array_filter(array_map(static fn($row)=>!empty($row['is_active']) ? absint($row['user_id'] ?? 0) : 0, (array)($project['owners'] ?? []))));
            $projectUsers = count($projectUserIds);
            $projectOwners = count($projectOwnerIds);
            $projectPeople = count(array_unique(array_merge($projectUserIds, $projectOwnerIds)));
        }
        $routeOwner = 0;
        $routeTeam = 0;
        $routePeople = 0;
        if ($route_id > 0) {
            $route = AssignmentResolver::get_route_context($route_id);
            $routeOwner = absint($route['owner_user_id'] ?? 0);
            $routeUserIds = array_values(array_filter(array_map(static fn($row)=>!empty($row['is_active']) ? absint($row['user_id'] ?? 0) : 0, (array)($route['assignments'] ?? []))));
            $routeTeam = count($routeUserIds);
            $routePeople = count(array_unique(array_merge($routeUserIds, $routeOwner > 0 ? [$routeOwner] : [])));
        }

        $alertFilters = array_filter(['client_id'=>$client_id,'project_id'=>$project_id], static fn($v)=>$v>0);
        $alertRows = OperationalIntelligenceService::recentAlerts(200, $alertFilters);
        $alerts = array_slice($alertRows, 0, 6);
        $openAlerts = count(array_filter($alertRows, static fn($row)=>(string)($row['status'] ?? '') === 'open'));
        $priorityAlerts = count(array_filter($alertRows, static fn($row)=>(string)($row['status'] ?? '') === 'open' && in_array((string)($row['severity'] ?? ''), ['critical','high'], true)));

        $learningFilters = array_filter(['client_id'=>$client_id,'project_id'=>$project_id,'route_id'=>$route_id], static fn($v)=>$v>0);
        $learningSummary = class_exists('\RoutesPro\Analytics\RecommendationLearningService')
            ? RecommendationLearningService::summary($learningFilters)
            : ['total'=>0,'open'=>0,'accepted'=>0,'deferred'=>0,'resolved'=>0,'not_relevant'=>0,'feedback_coverage'=>0.0,'outcome_coverage'=>0.0];

        $checks = [];
        if ($client_id > 0) $checks['Cliente com utilizadores'] = $clientUsers > 0;
        if ($project_id > 0) {
            $checks['Projeto com equipa'] = $projectUsers > 0;
            $checks['Projeto com responsável'] = $projectOwners > 0;
        }
        if ($route_id > 0) {
            $checks['Rota com owner'] = $routeOwner > 0;
            $checks['Rota com equipa'] = $routeTeam > 0;
        }
        $checks['Formulários ligados'] = $formBindings > 0;
        $checks['Dashboard ativo'] = count($dashRows) > 0;
        $checks['Widgets ativos'] = $widgets > 0;
        $ready = count(array_filter($checks));
        $readiness = $checks ? (int)round(($ready / count($checks)) * 100) : 0;

        return [
            'dashboards'=>count($dashRows),
            'ai_dashboards'=>$aiDashboards,
            'widgets'=>$widgets,
            'form_bindings'=>$formBindings,
            'client_users'=>$clientUsers,
            'project_users'=>$projectUsers,
            'project_owners'=>$projectOwners,
            'project_people'=>$projectPeople,
            'route_owner'=>$routeOwner,
            'route_team'=>$routeTeam,
            'route_people'=>$routePeople,
            'alerts'=>$alerts,
            'open_alerts'=>$openAlerts,
            'priority_alerts'=>$priorityAlerts,
            'learning_summary'=>$learningSummary,
            'checks'=>$checks,
            'readiness'=>$readiness,
            'ai_available'=>AnalyticsAIService::available(),
            'ai_provider'=>AnalyticsAIService::providerLabel(),
        ];
    }

    private static function handle_post_actions(): void {
        if (!current_user_can('routespro_manage')) return;
        global $wpdb;

        if (!empty($_POST['routespro_role_nonce']) && wp_verify_nonce($_POST['routespro_role_nonce'], 'routespro_role_manage')) {
            $new = sanitize_text_field($_POST['new_role'] ?? '');
            if ($new !== '') {
                self::save_roles(array_merge(self::get_roles(), [$new]));
                self::redirect_with_state(sanitize_key($_GET['tab'] ?? 'overview'), ['ff_status' => 'roles_saved']);
            }
        }

        $action = sanitize_key($_POST['routespro_assignment_hub_action'] ?? '');
        if (!$action) return;
        check_admin_referer('routespro_assignment_hub_' . $action);
        $tab = sanitize_key($_GET['tab'] ?? 'overview');

        switch ($action) {
            case 'save_client_scope':
                $client_id = absint($_POST['client_id'] ?? 0);
                $user_ids = array_map('absint', (array)($_POST['associated_user_ids'] ?? []));
                AssignmentResolver::save_client_user_ids($client_id, $user_ids);
                self::redirect_with_state('clients', ['client_id' => $client_id, 'ff_status' => $wpdb->last_error ? 'save_error' : 'client_saved']);
                break;
            case 'save_project_scope':
                $project_id = absint($_POST['project_id'] ?? 0);
                $user_ids = array_map('absint', (array)($_POST['associated_user_ids'] ?? []));
                $owner_ids = array_map('absint', (array)($_POST['owner_user_ids'] ?? []));
                $owner_role = sanitize_text_field($_POST['owner_role'] ?? 'owner');
                AssignmentResolver::save_project_assignments($project_id, $user_ids, $owner_ids, $owner_role ?: 'owner');
                self::redirect_with_state('projects', ['project_id' => $project_id, 'ff_status' => $wpdb->last_error ? 'save_error' : 'project_saved']);
                break;
            case 'save_route_scope':
                $route_id = absint($_POST['route_id'] ?? 0);
                $owner_user_id = absint($_POST['owner_user_id'] ?? 0);
                $team_user_ids = array_map('absint', (array)($_POST['team_user_ids'] ?? []));
                $team_role = sanitize_text_field($_POST['team_role'] ?? 'operacional');
                AssignmentResolver::save_route_assignments($route_id, $owner_user_id, $team_user_ids, $team_role ?: 'operacional');
                self::redirect_with_state('routes', ['route_id' => $route_id, 'ff_status' => $wpdb->last_error ? 'save_error' : 'route_saved']);
                break;
            case 'save_mobility_default':
                $project_id = absint($_POST['project_id'] ?? 0);
                $profile = self::mobility_profile_from_post('mobility_');
                $issues = MobilityCostService::profileIssues($profile);
                if ($project_id <= 0 || $issues) {
                    self::redirect_with_state('mobility', ['project_id'=>$project_id, 'owner_user_id'=>absint($_POST['owner_user_id'] ?? 0), 'ff_status'=>'mobility_invalid', 'mobility_issue'=>implode(' ', $issues)]);
                }
                $ok = MobilityCostService::saveProjectDefault($project_id, $profile, get_current_user_id());
                self::redirect_with_state('mobility', ['project_id'=>$project_id, 'owner_user_id'=>absint($_POST['owner_user_id'] ?? 0), 'ff_status'=>$ok ? 'mobility_default_saved' : 'mobility_persist_failed']);
                break;
            case 'save_mobility_profile':
                $project_id = absint($_POST['project_id'] ?? 0);
                $owner_user_id = absint($_POST['owner_user_id'] ?? 0);
                $profile = self::mobility_profile_from_post('mobility_');
                $issues = MobilityCostService::profileIssues($profile);
                if ($project_id <= 0 || $owner_user_id <= 0 || $issues) {
                    self::redirect_with_state('mobility', ['project_id'=>$project_id, 'owner_user_id'=>$owner_user_id, 'ff_status'=>'mobility_invalid', 'mobility_issue'=>implode(' ', $issues)]);
                }
                $ok = MobilityCostService::saveProjectUserProfile($project_id, $owner_user_id, $profile, get_current_user_id());
                self::redirect_with_state('mobility', ['project_id'=>$project_id, 'owner_user_id'=>$owner_user_id, 'ff_status'=>$ok ? 'mobility_profile_saved' : 'mobility_persist_failed']);
                break;
            case 'save_route_endpoint_default':
                $project_id = absint($_POST['project_id'] ?? 0);
                $profile = self::endpoint_profile_from_post('endpoint_');
                $issues = RouteEndpointProfileService::profileIssues($profile);
                if ($project_id <= 0 || $issues) {
                    self::redirect_with_state('mobility', ['project_id'=>$project_id, 'owner_user_id'=>absint($_POST['owner_user_id'] ?? 0), 'ff_status'=>'endpoint_profile_invalid', 'endpoint_issue'=>implode(' ', $issues)]);
                }
                $ok = RouteEndpointProfileService::saveProjectDefault($project_id, $profile, get_current_user_id());
                self::redirect_with_state('mobility', ['project_id'=>$project_id, 'owner_user_id'=>absint($_POST['owner_user_id'] ?? 0), 'ff_status'=>$ok ? 'endpoint_default_saved' : 'endpoint_profile_persist_failed']);
                break;
            case 'save_route_endpoint_profile':
                $project_id = absint($_POST['project_id'] ?? 0);
                $owner_user_id = absint($_POST['owner_user_id'] ?? 0);
                $profile = self::endpoint_profile_from_post('endpoint_');
                $issues = RouteEndpointProfileService::profileIssues($profile);
                if ($project_id <= 0 || $owner_user_id <= 0 || $issues) {
                    self::redirect_with_state('mobility', ['project_id'=>$project_id, 'owner_user_id'=>$owner_user_id, 'ff_status'=>'endpoint_profile_invalid', 'endpoint_issue'=>implode(' ', $issues)]);
                }
                $ok = RouteEndpointProfileService::saveProjectUserProfile($project_id, $owner_user_id, $profile, get_current_user_id());
                self::redirect_with_state('mobility', ['project_id'=>$project_id, 'owner_user_id'=>$owner_user_id, 'ff_status'=>$ok ? 'endpoint_profile_saved' : 'endpoint_profile_persist_failed']);
                break;
            case 'migrate_route_endpoint_defaults':
                $project_id = absint($_POST['project_id'] ?? 0);
                $client_id = absint($_POST['client_id'] ?? 0);
                if ($project_id <= 0) self::redirect_with_state('mobility', ['ff_status'=>'endpoint_profile_invalid']);
                $migration = RouteEndpointProfileService::migrateLegacyDefaults($project_id, $client_id, get_current_user_id());
                self::redirect_with_state('mobility', [
                    'project_id'=>$project_id,
                    'owner_user_id'=>absint($_POST['owner_user_id'] ?? 0),
                    'endpoint_migrated_default'=>(int)($migration['migrated_default'] ?? 0),
                    'endpoint_migrated_profiles'=>(int)($migration['migrated_profiles'] ?? 0),
                    'endpoint_migration_skipped'=>(int)($migration['skipped'] ?? 0),
                    'endpoint_migration_errors'=>(int)($migration['errors'] ?? 0),
                    'ff_status'=>'endpoint_legacy_migrated',
                ]);
                break;
            case 'backfill_mobility_routes':
                $project_id = absint($_POST['project_id'] ?? 0);
                $owner_user_id = absint($_POST['owner_user_id'] ?? 0);
                $date_from = sanitize_text_field((string)($_POST['date_from'] ?? ''));
                $date_to = sanitize_text_field((string)($_POST['date_to'] ?? ''));
                if ($project_id <= 0) self::redirect_with_state('mobility', ['ff_status'=>'mobility_invalid']);
                $result = MobilityCostService::backfillRoutes($project_id, $owner_user_id, $date_from, $date_to, !empty($_POST['include_completed']), !empty($_POST['force_existing_snapshots']), get_current_user_id());
                self::redirect_with_state('mobility', [
                    'project_id'=>$project_id,
                    'owner_user_id'=>$owner_user_id,
                    'mobility_processed'=>(int)($result['processed'] ?? 0),
                    'mobility_updated'=>(int)($result['updated'] ?? 0),
                    'mobility_skipped'=>(int)($result['skipped'] ?? 0),
                    'mobility_errors'=>(int)($result['errors'] ?? 0),
                    'ff_status'=>'mobility_backfill_done',
                ]);
                break;
            case 'reconcile_routing_geography':
                $project_id = absint($_POST['project_id'] ?? 0);
                $owner_user_id = absint($_POST['owner_user_id'] ?? 0);
                $date_from = sanitize_text_field((string)($_POST['date_from'] ?? ''));
                $date_to = sanitize_text_field((string)($_POST['date_to'] ?? ''));
                if ($project_id <= 0) self::redirect_with_state('mobility', ['ff_status'=>'mobility_invalid']);
                $result = RouteMetricsService::reconcileProjectGeographicIntegrity($project_id, $owner_user_id, $date_from, $date_to, !empty($_POST['include_completed']));
                self::redirect_with_state('mobility', [
                    'project_id'=>$project_id,
                    'owner_user_id'=>$owner_user_id,
                    'geo_processed'=>(int)($result['processed'] ?? 0),
                    'geo_reconciled'=>(int)($result['reconciled'] ?? 0),
                    'geo_skipped'=>(int)($result['skipped'] ?? 0),
                    'geo_failed'=>(int)($result['failed'] ?? 0),
                    'geo_pdvs_updated'=>(int)($result['pdvs_updated'] ?? 0),
                    'ff_status'=>'routing_geography_reconciled',
                ]);
                break;
            case 'repair_route_distance_integrity':
                $project_id = absint($_POST['project_id'] ?? 0);
                $owner_user_id = absint($_POST['owner_user_id'] ?? 0);
                $date_from = sanitize_text_field((string)($_POST['date_from'] ?? ''));
                $date_to = sanitize_text_field((string)($_POST['date_to'] ?? ''));
                if ($project_id <= 0) self::redirect_with_state('mobility', ['ff_status'=>'mobility_invalid']);
                $force_owner_profile = !empty($_POST['force_owner_profile']) && $owner_user_id > 0;
                $result = $force_owner_profile
                    ? RouteMetricsService::repairPlannedRouteIntegrityWithOwnerProfile($project_id, $owner_user_id, $date_from, $date_to, get_current_user_id())
                    : RouteMetricsService::repairPlannedRouteIntegrity($project_id, $owner_user_id, $date_from, $date_to, get_current_user_id());
                self::redirect_with_state('mobility', [
                    'project_id'=>$project_id,
                    'owner_user_id'=>$owner_user_id,
                    'distance_processed'=>(int)($result['processed'] ?? 0),
                    'distance_repaired'=>(int)($result['repaired'] ?? 0),
                    'distance_reconciled'=>(int)($result['reconciled'] ?? 0),
                    'distance_recalculated'=>(int)($result['recalculated'] ?? 0),
                    'distance_forced_profile'=>(int)($result['forced_profile'] ?? 0),
                    'distance_google_exact'=>(int)($result['google_exact'] ?? 0),
                    'distance_matrix_fallback'=>(int)($result['matrix_fallback'] ?? 0),
                    'distance_google_exact_failed'=>(int)($result['google_exact_failed'] ?? 0),
                    'distance_suspicious'=>(int)($result['suspicious'] ?? 0),
                    'distance_suspicious_unresolved'=>(int)($result['suspicious_unresolved'] ?? 0),
                    'distance_unchanged'=>(int)($result['unchanged'] ?? 0),
                    'distance_missing_defaults'=>(int)($result['missing_defaults'] ?? 0),
                    'distance_started'=>(int)($result['started'] ?? 0),
                    'distance_errors'=>(int)($result['errors'] ?? 0),
                    'ff_status'=>'route_distance_repair_done',
                ]);
                break;
            case 'save_route_mobility_override':
                $route_id = absint($_POST['route_id'] ?? 0);
                $profile = self::mobility_profile_from_post('mobility_');
                $issues = MobilityCostService::profileIssues($profile);
                if ($route_id <= 0 || $issues) {
                    self::redirect_with_state('mobility', ['route_id'=>$route_id, 'project_id'=>absint($_POST['project_id'] ?? 0), 'owner_user_id'=>absint($_POST['owner_user_id'] ?? 0), 'ff_status'=>'mobility_invalid', 'mobility_issue'=>implode(' ', $issues)]);
                }
                $ok = MobilityCostService::saveRouteOverride($route_id, $profile, true, get_current_user_id());
                self::redirect_with_state('mobility', ['route_id'=>$route_id, 'project_id'=>absint($_POST['project_id'] ?? 0), 'owner_user_id'=>absint($_POST['owner_user_id'] ?? 0), 'ff_status'=>$ok ? 'mobility_route_override_saved' : 'mobility_persist_failed']);
                break;
            case 'save_form_binding':
                $data = [
                    'form_id' => absint($_POST['form_id'] ?? 0),
                    'client_id' => absint($_POST['client_id'] ?? 0),
                    'project_id' => absint($_POST['project_id'] ?? 0),
                    'route_id' => absint($_POST['route_id'] ?? 0),
                    'stop_id' => absint($_POST['stop_id'] ?? 0),
                    'location_id' => absint($_POST['location_id'] ?? 0),
                    'mode' => sanitize_key($_POST['mode'] ?? 'route_and_form'),
                    'priority' => max(0, min(999, (int)($_POST['priority'] ?? 10))),
                    'is_active' => !empty($_POST['is_active']) ? 1 : 0,
                    'created_at' => current_time('mysql'),
                ];
                if ($data['form_id'] && ($data['client_id'] || $data['project_id'] || $data['route_id'] || $data['stop_id'] || $data['location_id'])) {
                    $wpdb->insert(BindingResolver::table(), $data, ['%d','%d','%d','%d','%d','%d','%s','%d','%d','%s']);
                    self::redirect_with_state('forms', [
                        'client_id' => $data['client_id'],
                        'project_id' => $data['project_id'],
                        'route_id' => $data['route_id'],
                        'ff_status' => $wpdb->last_error ? 'save_error' : 'binding_saved'
                    ]);
                } else {
                    self::redirect_with_state('forms', ['ff_status' => 'binding_invalid']);
                }
                break;
            case 'delete_form_binding':
                $id = absint($_POST['binding_id'] ?? 0);
                if ($id) {
                    $wpdb->delete(BindingResolver::table(), ['id' => $id], ['%d']);
                }
                self::redirect_with_state('forms', ['ff_status' => $wpdb->last_error ? 'save_error' : 'binding_deleted']);
                break;
            case 'save_analytics_dashboard':
                $dashboard_id = absint($_POST['analytics_dashboard_id'] ?? 0);
                $title = sanitize_text_field($_POST['dashboard_title'] ?? '');
                $client_id = absint($_POST['client_id'] ?? 0);
                $project_id = absint($_POST['project_id'] ?? 0);
                $route_id = absint($_POST['route_id'] ?? 0);
                $dashboard_visibility = sanitize_key($_POST['dashboard_visibility'] ?? 'client_portal');
                if (!in_array($dashboard_visibility, ['client_portal','fieldflow_app','both'], true)) $dashboard_visibility = 'client_portal';
                $dashboard_layout = sanitize_key($_POST['dashboard_layout_type'] ?? 'mixed');
                if (!in_array($dashboard_layout, ['mixed','executive','table'], true)) $dashboard_layout = 'mixed';
                if ($title !== '') {
                    $current_ai_settings = [];
                    if ($dashboard_id > 0) {
                        $current_ai_json = (string)$wpdb->get_var($wpdb->prepare('SELECT ai_settings_json FROM ' . self::analytics_dashboard_table() . ' WHERE id=%d', $dashboard_id));
                        $decoded_ai_settings = json_decode($current_ai_json, true);
                        if (is_array($decoded_ai_settings)) $current_ai_settings = $decoded_ai_settings;
                    }
                    $widget_mode = sanitize_key((string)($_POST['dashboard_widget_mode'] ?? 'automatic'));
                    if (!in_array($widget_mode, ['automatic','assisted','manual'], true)) $widget_mode = 'automatic';
                    $current_ai_settings['widget_orchestration'] = [
                        'mode'=>$widget_mode,
                        'max_client'=>max(3, min(12, absint($_POST['dashboard_widget_max_client'] ?? 7))),
                        'max_merchandiser'=>max(3, min(12, absint($_POST['dashboard_widget_max_merchandiser'] ?? 6))),
                        'max_email'=>max(3, min(8, absint($_POST['dashboard_widget_max_email'] ?? 5))),
                        'alert_boost'=>!empty($_POST['dashboard_widget_alert_boost']) ? 1 : 0,
                        'version'=>WidgetOrchestrator::VERSION,
                    ];
                    $dashboard_data = [
                        'client_id' => $client_id ?: null,
                        'project_id' => $project_id ?: null,
                        'route_id' => $route_id ?: null,
                        'title' => $title,
                        'description' => wp_kses_post($_POST['dashboard_description'] ?? ''),
                        'visibility' => $dashboard_visibility,
                        'layout_type' => $dashboard_layout,
                        'ai_settings_json' => wp_json_encode($current_ai_settings),
                        'sort_order' => (int)($_POST['dashboard_sort_order'] ?? 10),
                        'is_active' => !empty($_POST['dashboard_is_active']) ? 1 : 0,
                    ];
                    $dashboard_formats = ['%d','%d','%d','%s','%s','%s','%s','%s','%d','%d'];
                    if ($dashboard_id > 0) {
                        $exists = (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::analytics_dashboard_table() . ' WHERE id=%d', $dashboard_id));
                        if (!$exists) self::redirect_with_state('analytics', ['ff_status'=>'analytics_dashboard_invalid']);
                        $wpdb->update(self::analytics_dashboard_table(), $dashboard_data, ['id'=>$dashboard_id], $dashboard_formats, ['%d']);
                    } else {
                        $wpdb->insert(self::analytics_dashboard_table(), $dashboard_data, $dashboard_formats);
                        $dashboard_id = (int)$wpdb->insert_id;
                    }
                    self::redirect_with_state('analytics', ['client_id'=>$client_id,'project_id'=>$project_id,'route_id'=>$route_id,'edit_analytics_dashboard'=>$dashboard_id,'ff_status'=>$wpdb->last_error ? 'save_error' : 'analytics_dashboard_saved']);
                }
                self::redirect_with_state('analytics', ['ff_status'=>'analytics_dashboard_invalid']);
                break;
            case 'save_analytics_dashboard_content':
                $dashboard_id = absint($_POST['analytics_dashboard_id'] ?? 0);
                $binding_ids = array_values(array_unique(array_filter(array_map('absint', (array)($_POST['dashboard_widget_ids'] ?? [])))));
                $chart_types = (array)($_POST['dashboard_widget_chart_type'] ?? []);
                $sort_orders = (array)($_POST['dashboard_widget_sort_order'] ?? []);
                $active_ids = array_map('absint', (array)($_POST['dashboard_widget_active'] ?? []));
                $included_ids = array_map('absint', (array)($_POST['dashboard_widget_in_dashboard'] ?? []));
                $kpi_ids = array_map('absint', (array)($_POST['dashboard_widget_kpi'] ?? []));
                $table_ids = array_map('absint', (array)($_POST['dashboard_widget_table'] ?? []));
                $pinned_ids = array_map('absint', (array)($_POST['dashboard_widget_pinned'] ?? []));
                $content_error = false;
                if ($dashboard_id > 0 && $binding_ids) {
                    $binding_table = self::analytics_table();
                    $allowed_charts = array_keys(self::analytics_options('chart'));
                    $wpdb->query('START TRANSACTION');
                    foreach ($binding_ids as $binding_id) {
                        $binding = $wpdb->get_row($wpdb->prepare("SELECT id,chart_type,settings_json FROM {$binding_table} WHERE id=%d", $binding_id), ARRAY_A);
                        if (!$binding) continue;
                        $settings = json_decode((string)($binding['settings_json'] ?? ''), true); if (!is_array($settings)) $settings = [];
                        if ((int)($settings['dashboard_id'] ?? 0) !== $dashboard_id) continue;
                        $chart_type = sanitize_key((string)($chart_types[$binding_id] ?? $binding['chart_type'] ?? 'line'));
                        if (!in_array($chart_type, $allowed_charts, true)) $chart_type = 'line';
                        $question_type = sanitize_key((string)($settings['question_type'] ?? ''));
                        if (in_array($question_type, ['image_upload','file_upload','categorized_image_upload'], true)) $chart_type = 'image_gallery';
                        elseif (in_array($question_type, ['product_matrix','order_suggestion'], true) && $chart_type === 'image_gallery') $chart_type = 'table';
                        if (!in_array($binding_id, $included_ids, true)) $settings['dashboard_id'] = 0;
                        $settings['sort_order'] = (int)($sort_orders[$binding_id] ?? 10);
                        $settings['show_kpi'] = in_array($binding_id, $kpi_ids, true) ? 1 : 0;
                        $settings['show_table'] = in_array($binding_id, $table_ids, true) || in_array($question_type, ['product_matrix','order_suggestion'], true) ? 1 : 0;
                        $settings['orchestration_pinned'] = in_array($binding_id, $pinned_ids, true) ? 1 : 0;
                        $updated = $wpdb->update($binding_table, [
                            'chart_type'=>$chart_type,
                            'settings_json'=>wp_json_encode($settings),
                            'is_active'=>in_array($binding_id, $active_ids, true) ? 1 : 0,
                        ], ['id'=>$binding_id], ['%s','%s','%d'], ['%d']);
                        if ($updated === false) { $content_error = true; break; }
                    }
                    $wpdb->query($content_error ? 'ROLLBACK' : 'COMMIT');
                }
                self::redirect_with_state('analytics', ['edit_analytics_dashboard'=>$dashboard_id,'ff_status'=>$content_error ? 'save_error' : 'analytics_dashboard_content_saved']);
                break;
            case 'move_analytics_dashboard':
                $dashboard_id = absint($_POST['analytics_dashboard_id'] ?? 0);
                $direction = sanitize_key((string)($_POST['move_direction'] ?? ''));
                if ($dashboard_id && in_array($direction, ['up','down'], true)) {
                    $dashboard_table = self::analytics_dashboard_table();
                    $siblings = $wpdb->get_results("SELECT id,sort_order FROM {$dashboard_table} ORDER BY sort_order ASC,id ASC", ARRAY_A) ?: [];
                    $index = null;
                    foreach ($siblings as $i => $sibling) if ((int)$sibling['id'] === $dashboard_id) { $index = $i; break; }
                    if ($index !== null) {
                        $target = $direction === 'up' ? $index - 1 : $index + 1;
                        if (isset($siblings[$target])) { $tmp=$siblings[$index]; $siblings[$index]=$siblings[$target]; $siblings[$target]=$tmp; }
                        foreach ($siblings as $i => $sibling) $wpdb->update($dashboard_table, ['sort_order'=>($i+1)*10], ['id'=>(int)$sibling['id']], ['%d'], ['%d']);
                    }
                }
                self::redirect_with_state('analytics', ['edit_analytics_dashboard'=>$dashboard_id,'ff_status'=>$wpdb->last_error ? 'save_error' : 'analytics_dashboard_saved']);
                break;
            case 'delete_analytics_dashboard':
                $id = absint($_POST['analytics_dashboard_id'] ?? 0);
                if ($id) {
                    $binding_table = self::analytics_table();
                    $bindings = $wpdb->get_results("SELECT id,settings_json FROM {$binding_table} ORDER BY id ASC", ARRAY_A) ?: [];
                    $delete_error = false;
                    $wpdb->query('START TRANSACTION');
                    foreach ($bindings as $binding) {
                        $settings = json_decode((string)($binding['settings_json'] ?? ''), true); if (!is_array($settings)) $settings = [];
                        if ((int)($settings['dashboard_id'] ?? 0) !== $id) continue;
                        $settings['dashboard_id'] = 0;
                        if ($wpdb->update($binding_table, ['settings_json'=>wp_json_encode($settings)], ['id'=>(int)$binding['id']], ['%s'], ['%d']) === false) { $delete_error = true; break; }
                    }
                    if (!$delete_error && $wpdb->delete(self::analytics_dashboard_table(), ['id'=>$id], ['%d']) === false) $delete_error = true;
                    $wpdb->query($delete_error ? 'ROLLBACK' : 'COMMIT');
                    if ($delete_error) self::redirect_with_state('analytics', ['ff_status'=>'save_error']);
                }
                self::redirect_with_state('analytics', ['ff_status'=>$wpdb->last_error ? 'save_error' : 'analytics_dashboard_deleted']);
                break;
            case 'save_analytics_store_group':
                $name = sanitize_text_field($_POST['store_group_name'] ?? '');
                $client_id = absint($_POST['client_id'] ?? 0);
                $project_id = absint($_POST['project_id'] ?? 0);
                $location_ids = array_values(array_unique(array_filter(array_map('absint', (array)($_POST['store_group_location_ids'] ?? [])))));
                if ($name !== '' && $location_ids) {
                    $wpdb->insert(self::analytics_store_group_table(), [
                        'client_id' => $client_id ?: null,
                        'project_id' => $project_id ?: null,
                        'name' => $name,
                        'group_type' => 'manual',
                        'rule_json' => wp_json_encode(['mode'=>'manual','location_ids'=>$location_ids]),
                        'is_active' => !empty($_POST['store_group_is_active']) ? 1 : 0,
                    ], ['%d','%d','%s','%s','%s','%d']);
                    $group_id = (int)$wpdb->insert_id;
                    foreach ($location_ids as $location_id) {
                        $wpdb->replace(self::analytics_store_group_items_table(), ['group_id'=>$group_id,'location_id'=>$location_id], ['%d','%d']);
                    }
                    self::redirect_with_state('analytics', ['client_id'=>$client_id,'project_id'=>$project_id,'ff_status'=>$wpdb->last_error ? 'save_error' : 'analytics_group_saved']);
                }
                self::redirect_with_state('analytics', ['ff_status'=>'analytics_group_invalid']);
                break;
            case 'delete_analytics_store_group':
                $id = absint($_POST['analytics_store_group_id'] ?? 0);
                if ($id) {
                    $wpdb->delete(self::analytics_store_group_items_table(), ['group_id'=>$id], ['%d']);
                    $wpdb->delete(self::analytics_store_group_table(), ['id'=>$id], ['%d']);
                }
                self::redirect_with_state('analytics', ['ff_status'=>$wpdb->last_error ? 'save_error' : 'analytics_group_deleted']);
                break;
            case 'reconcile_evergreen_briefing_analytics':
                if (class_exists('\RoutesPro\Admin\ContextQuestions')) \RoutesPro\Admin\ContextQuestions::ensure_table();
                $scopeClientId = absint($_POST['client_id'] ?? 0);
                $scopeProjectId = absint($_POST['project_id'] ?? 0);
                $result = QuestionAnalyticsService::reconcileSmartBriefingAnalytics(true, $scopeClientId, $scopeProjectId);
                self::redirect_with_state('analytics', [
                    'client_id' => $scopeClientId,
                    'project_id' => $scopeProjectId,
                    'ff_status' => !empty($result['processed']) ? 'analytics_evergreen_reconciled' : 'save_error',
                    'evergreen_processed' => (int)($result['processed'] ?? 0),
                    'evergreen_synced' => (int)($result['synced'] ?? 0),
                    'evergreen_deactivated' => (int)($result['deactivated'] ?? 0),
                    'evergreen_rollup' => (int)($result['rollup'] ?? 0),
                    'evergreen_permanent' => (int)($result['permanent'] ?? 0),
                    'evergreen_operational_families' => (int)($result['operational_families'] ?? 0),
                    'evergreen_operational_evidence' => (int)($result['operational_evidence_rollups'] ?? 0),
                    'evergreen_covered_legacy' => (int)($result['covered_legacy_deactivated'] ?? 0),
                    'evergreen_ownership_repaired' => (int)($result['ownership_repaired'] ?? 0),
                ]);
                break;
            case 'save_analytics_binding':
                $metric_source = sanitize_key($_POST['metric_source'] ?? 'question');
                $system_metric_key = sanitize_key($_POST['system_metric_key'] ?? '');
                $system_catalog = RouteMobilityWidgetService::catalog();
                $is_system_metric = $metric_source === RouteMobilityWidgetService::SOURCE_TYPE && isset($system_catalog[$system_metric_key]);
                $dashboard_id_post = absint($_POST['dashboard_id'] ?? 0);
                $form_id = absint($_POST['form_id'] ?? 0);
                $question_key = sanitize_key($_POST['question_key'] ?? '');
                $metric_key = sanitize_key($_POST['metric_key'] ?? '');
                $metric_label = sanitize_text_field($_POST['metric_label'] ?? '');
                $chart_type = sanitize_key($_POST['chart_type'] ?? 'line');
                $question_type = sanitize_key($_POST['question_type'] ?? 'text') ?: 'text';
                if ($is_system_metric) {
                    $definition = (array)$system_catalog[$system_metric_key];
                    $form_id = 0;
                    $question_key = RouteMobilityWidgetService::questionKey($system_metric_key, $dashboard_id_post);
                    if ($metric_key === '') $metric_key = $system_metric_key;
                    if ($metric_label === '') $metric_label = sanitize_text_field((string)($definition['label'] ?? $system_metric_key));
                    $question_type = 'system_route_mobility';
                    if ($chart_type === '') $chart_type = sanitize_key((string)($definition['chart_type'] ?? 'kpi'));
                }
                $aggregation = sanitize_key($_POST['aggregation'] ?? 'latest');
                $dimension = sanitize_key($_POST['dimension'] ?? 'submitted_at');
                $secondary_dimension = sanitize_key($_POST['secondary_dimension'] ?? '');
                $evidence_grouping = sanitize_key($_POST['evidence_grouping'] ?? 'location_date');
                if (!in_array($evidence_grouping, ['location_date','date_location','location'], true)) $evidence_grouping = 'location_date';
                if (in_array($question_type, ['image_upload', 'file_upload', 'categorized_image_upload'], true)) {
                    $chart_type = 'image_gallery';
                    $aggregation = 'evidence';
                    $dimension = 'location_id';
                    if ($secondary_dimension === '' || $secondary_dimension === 'location_id') $secondary_dimension = 'submitted_at';
                } elseif ($question_type === 'product_matrix') {
                    if ($chart_type === 'image_gallery') $chart_type = 'table';
                    $dimension = 'location_id';
                } elseif ($question_type === 'order_suggestion') {
                    if ($chart_type === 'image_gallery') $chart_type = 'table';
                    $dimension = 'location_id';
                }
                $scope_mode = sanitize_key($_POST['scope_mode'] ?? 'client_project_location');
                $is_active = !empty($_POST['is_active']) ? 1 : 0;
                $existing_id = absint($_POST['analytics_binding_id'] ?? 0);
                $existing_settings = [];
                if ($existing_id) {
                    $existing_settings_json = $wpdb->get_var($wpdb->prepare('SELECT settings_json FROM ' . self::analytics_table() . ' WHERE id=%d', $existing_id));
                    $existing_settings = json_decode((string)$existing_settings_json, true);
                    if (!is_array($existing_settings)) $existing_settings = [];
                }
                $settings_data = array_merge($existing_settings, [
                    'client_id' => absint($_POST['client_id'] ?? 0),
                    'project_id' => absint($_POST['project_id'] ?? 0),
                    'route_id' => absint($_POST['route_id'] ?? 0),
                    'dashboard_id' => $dashboard_id_post,
                    'store_group_id' => $is_system_metric ? 0 : absint($_POST['store_group_id'] ?? 0),
                    'metric_source' => $is_system_metric ? RouteMobilityWidgetService::SOURCE_TYPE : 'question',
                    'system_metric_key' => $is_system_metric ? $system_metric_key : '',
                    'secondary_dimension' => $secondary_dimension,
                    'evidence_grouping' => $evidence_grouping,
                    'evidence_show_thumbnails' => !empty($_POST['evidence_show_thumbnails']) ? 1 : 0,
                    'question_type' => $question_type,
                    'show_kpi' => !empty($_POST['show_kpi']) ? 1 : 0,
                    'show_table' => (in_array($question_type, ['product_matrix','order_suggestion'], true) || !empty($_POST['show_table'])) ? 1 : 0,
                    'show_empty' => !empty($_POST['show_empty']) ? 1 : 0,
                    'sort_order' => (int)($_POST['sort_order'] ?? 10),
                ]);
                if (!empty($settings_data['evergreen_briefing'])) $settings_data['canonical_metric_key'] = $metric_key;
                $settings_json = wp_json_encode($settings_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (($is_system_metric || $form_id > 0) && $question_key !== '' && $metric_key !== '' && $metric_label !== '') {
                    $table = self::analytics_table();
                    $data = [
                        'form_id' => $form_id,
                        'question_key' => $question_key,
                        'metric_key' => $metric_key,
                        'metric_label' => $metric_label,
                        'chart_type' => $chart_type,
                        'aggregation' => $aggregation,
                        'dimension' => $dimension,
                        'scope_mode' => $scope_mode,
                        'settings_json' => $settings_json,
                        'is_active' => $is_active,
                    ];
                    $formats = ['%d','%s','%s','%s','%s','%s','%s','%s','%s','%d'];
                    if ($existing_id) {
                        $wpdb->update($table, $data, ['id' => $existing_id], $formats, ['%d']);
                    } else {
                        $wpdb->insert($table, $data, $formats);
                    }
                    if (!empty($settings_data['evergreen_briefing'])) {
                        $managed_keys = array_values(array_unique(array_filter(array_map('sanitize_key', array_merge(
                            (array)($settings_data['managed_question_keys'] ?? []),
                            [(string)($settings_data['managed_question_key'] ?? ''), $question_key]
                        )))));
                        $context_table = ContextQuestionService::table();
                        if ($managed_keys && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $context_table)) === $context_table) {
                            $has_metric_key = (bool)$wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $context_table . ' LIKE %s', 'analytics_metric_key'));
                            if ($has_metric_key) {
                                $placeholders = implode(',', array_fill(0, count($managed_keys), '%s'));
                                $update_args = array_merge([$metric_key, absint($settings_data['dashboard_id'] ?? 0)], $managed_keys);
                                $wpdb->query($wpdb->prepare(
                                    'UPDATE ' . $context_table . ' SET analytics_metric_key=%s,analytics_dashboard_id=%d WHERE question_key IN (' . $placeholders . ')',
                                    ...$update_args
                                ));
                            }
                        }
                    }
                    self::redirect_with_state('analytics', [
                        'client_id' => absint($_POST['client_id'] ?? 0),
                        'project_id' => absint($_POST['project_id'] ?? 0),
                        'route_id' => absint($_POST['route_id'] ?? 0),
                        'edit_analytics_dashboard' => absint($_POST['return_dashboard_id'] ?? 0),
                        'ff_status' => $wpdb->last_error ? 'save_error' : 'analytics_binding_saved'
                    ]);
                } else {
                    self::redirect_with_state('analytics', ['ff_status' => 'analytics_binding_invalid']);
                }
                break;
            case 'save_analytics_binding_order':
                $ordered = array_values(array_filter(array_map('absint', explode(',', (string)($_POST['binding_order'] ?? '')))));
                foreach ($ordered as $index => $binding_id) {
                    $row = $wpdb->get_var($wpdb->prepare('SELECT settings_json FROM ' . self::analytics_table() . ' WHERE id=%d', $binding_id));
                    $settings = json_decode((string)$row, true); if (!is_array($settings)) $settings = [];
                    $settings['sort_order'] = ($index + 1) * 10;
                    $wpdb->update(self::analytics_table(), ['settings_json'=>wp_json_encode($settings)], ['id'=>$binding_id], ['%s'], ['%d']);
                }
                self::redirect_with_state('analytics', ['ff_status'=>$wpdb->last_error ? 'save_error' : 'analytics_binding_saved']);
                break;
            case 'move_analytics_binding':
                $binding_id = absint($_POST['analytics_binding_id'] ?? 0);
                $direction = sanitize_key((string)($_POST['move_direction'] ?? ''));
                if ($binding_id && in_array($direction, ['up','down'], true)) {
                    $table = self::analytics_table();
                    $all = $wpdb->get_results("SELECT id,settings_json FROM {$table} ORDER BY id ASC", ARRAY_A) ?: [];
                    $dashboard_id = null;
                    foreach ($all as $row) {
                        if ((int)$row['id'] !== $binding_id) continue;
                        $settings = json_decode((string)($row['settings_json'] ?? ''), true); if (!is_array($settings)) $settings = [];
                        $dashboard_id = (int)($settings['dashboard_id'] ?? 0); break;
                    }
                    if ($dashboard_id !== null) {
                        $siblings = [];
                        foreach ($all as $row) {
                            $settings = json_decode((string)($row['settings_json'] ?? ''), true); if (!is_array($settings)) $settings = [];
                            if ((int)($settings['dashboard_id'] ?? 0) !== $dashboard_id) continue;
                            $siblings[] = ['id'=>(int)$row['id'],'settings'=>$settings,'sort_order'=>(int)($settings['sort_order'] ?? 10)];
                        }
                        usort($siblings, static function(array $a,array $b): int { return $a['sort_order'] === $b['sort_order'] ? ($a['id'] <=> $b['id']) : ($a['sort_order'] <=> $b['sort_order']); });
                        foreach ($siblings as $i => &$sibling) $sibling['settings']['sort_order'] = ($i + 1) * 10;
                        unset($sibling);
                        $index = null;
                        foreach ($siblings as $i => $sibling) if ($sibling['id'] === $binding_id) { $index = $i; break; }
                        if ($index !== null) {
                            $target = $direction === 'up' ? $index - 1 : $index + 1;
                            if (isset($siblings[$target])) { $tmp=$siblings[$index]; $siblings[$index]=$siblings[$target]; $siblings[$target]=$tmp; }
                            foreach ($siblings as $i => $sibling) {
                                $settings = $sibling['settings']; $settings['sort_order'] = ($i + 1) * 10;
                                $wpdb->update($table, ['settings_json'=>wp_json_encode($settings)], ['id'=>$sibling['id']], ['%s'], ['%d']);
                            }
                        }
                    }
                }
                self::redirect_with_state('analytics', ['ff_status'=>$wpdb->last_error ? 'save_error' : 'analytics_binding_saved']);
                break;
            case 'delete_analytics_binding':
                $id = absint($_POST['analytics_binding_id'] ?? 0);
                if ($id) {
                    $wpdb->delete(self::analytics_table(), ['id' => $id], ['%d']);
                }
                self::redirect_with_state('analytics', ['ff_status' => $wpdb->last_error ? 'save_error' : 'analytics_binding_deleted']);
                break;
        }
    }


    private static function render_analytics2_workspace(array $clients, array $projects, array $routes, array $users, array $locations, int $selected_client_id, int $selected_project_id, int $selected_route_id): void {
        $valid_date = static function($value): string {
            $value = trim(sanitize_text_field((string)$value));
            if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) return '';
            [$year, $month, $day] = array_map('intval', explode('-', $value));
            return checkdate($month, $day, $year) ? $value : '';
        };

        $today = current_time('Y-m-d');
        $to = $valid_date($_GET['to'] ?? '') ?: $today;
        $from = $valid_date($_GET['from'] ?? '') ?: date('Y-m-d', strtotime($to . ' -29 days'));
        if ($from > $to) [$from, $to] = [$to, $from];
        $selected_user_id = absint($_GET['user_id'] ?? 0);
        $selected_location_id = absint($_GET['location_id'] ?? 0);
        $default_to = $today;
        $default_from = date('Y-m-d', strtotime($default_to . ' -29 days'));
        $react_analytics_enabled = class_exists('\\RoutesPro\\Admin\\ReactBackofficeFoundation')
            && \RoutesPro\Admin\ReactBackofficeFoundation::islandEnabled('analytics-context-kpis', 'routespro-assignments-hub');
        $analytics_react_props = [];
        if ($react_analytics_enabled) {
            $client_options = [];
            foreach ($clients as $client) {
                $id = (int)($client['id'] ?? 0);
                if ($id > 0) $client_options[] = ['value'=>(string)$id,'label'=>(string)($client['name'] ?? ('Cliente #' . $id))];
            }
            $project_options = [];
            foreach ($projects as $project) {
                $id = (int)($project['id'] ?? 0);
                if ($id > 0) $project_options[] = ['value'=>(string)$id,'label'=>(string)($project['name'] ?? ('Projeto #' . $id)),'clientId'=>(string)((int)($project['client_id'] ?? 0))];
            }
            $route_options = [];
            foreach ($routes as $route) {
                $id = (int)($route['id'] ?? 0);
                if ($id > 0) $route_options[] = ['value'=>(string)$id,'label'=>'#' . $id . ' · ' . ((string)($route['date'] ?? '') ?: 'sem data'),'clientId'=>(string)((int)($route['client_id'] ?? 0)),'projectId'=>(string)((int)($route['project_id'] ?? 0))];
            }
            $user_options = [];
            foreach ($users as $user) {
                $id = (int)($user->ID ?? 0);
                if ($id < 1) continue;
                $label = (string)($user->display_name ?: $user->user_login);
                if (!empty($user->user_login) && $label !== (string)$user->user_login) $label .= ' [' . (string)$user->user_login . ']';
                $user_options[] = ['value'=>(string)$id,'label'=>$label];
            }
            $location_options = [];
            foreach ($locations as $location) {
                $id = (int)($location['id'] ?? 0);
                if ($id > 0) $location_options[] = ['value'=>(string)$id,'label'=>(string)($location['name'] ?? ('PDV #' . $id)),'clientId'=>(string)((int)($location['client_id'] ?? 0)),'projectId'=>(string)((int)($location['project_id'] ?? 0))];
            }
            $analytics_react_props = [
                'contract'=>\RoutesPro\Admin\ReactBackofficeFoundation::CONTRACT,
                'sharedContract'=>\RoutesPro\Admin\ReactBackofficeFoundation::SHARED_CONTRACT,
                'island'=>\RoutesPro\Admin\ReactBackofficeFoundation::ANALYTICS_ISLAND,
                'title'=>'Analytics em tempo real',
                'description'=>'Altera o contexto sem recarregar a página; KPIs e restantes painéis usam o mesmo motor Analytics 2.0.',
                'fallbackId'=>'ff-a2-legacy-control-fallback',
                'values'=>[
                    'from'=>$from,'to'=>$to,
                    'client_id'=>(string)$selected_client_id,'project_id'=>(string)$selected_project_id,'route_id'=>(string)$selected_route_id,
                    'user_id'=>(string)$selected_user_id,'location_id'=>(string)$selected_location_id,
                ],
                'defaultValues'=>[
                    'from'=>$default_from,'to'=>$default_to,
                    'client_id'=>'0','project_id'=>'0','route_id'=>'0','user_id'=>'0','location_id'=>'0',
                ],
                'clients'=>$client_options,'projects'=>$project_options,'routes'=>$route_options,'users'=>$user_options,'locations'=>$location_options,
            ];
        }

        echo '<section class="ff-a2" id="ff-analytics2-workspace" data-ff-analytics2>';
        echo '<div class="ff-a2-hero">';
        echo '<div><h3>Analytics 2.0 Command View</h3><p>Uma leitura operacional única, do contexto atribuído à decisão. Cliente, campanha, rota, pessoa, loja e período alimentam a mesma camada de métricas, comparação temporal, prioridades e inteligência.</p>';
        echo '<div class="ff-a2-hero-badges"><span class="ff-a2-hero-badge">Filtros universais</span><span class="ff-a2-hero-badge">Comparação temporal</span><span class="ff-a2-hero-badge">Decision Scores</span><span class="ff-a2-hero-badge">Store Risk Engine</span><span class="ff-a2-hero-badge">Predictive Intelligence</span><span class="ff-a2-hero-badge">Perguntar ao FieldFlow</span><span class="ff-a2-hero-badge">Drill-down</span><span class="ff-a2-hero-badge">Revisão humana</span></div></div>';
        echo '<div class="ff-a2-state-card is-loading" data-a2-state><span>Estado da leitura</span><strong data-a2-state-title>A preparar contexto</strong><small data-a2-state-detail>A cruzar as fontes disponíveis sem inferir causas não demonstradas.</small></div>';
        echo '</div>';

        if ($react_analytics_enabled) {
            echo '<div class="ff-react-analytics-shell" data-fieldflow-react-island="analytics-context-kpis" data-fieldflow-react-props="' . esc_attr((string)wp_json_encode($analytics_react_props)) . '"></div>';
        }
        echo '<div id="ff-a2-legacy-control-fallback" class="ff-a2-react-fallback">';
        echo '<form method="get" class="ff-a2-filter" data-ff-a2-filter>';
        echo '<input type="hidden" name="page" value="routespro-assignments-hub"><input type="hidden" name="tab" value="analytics">';
        echo '<div class="ff-a2-filter-head"><div><h4>Contexto universal</h4><p>O mesmo filtro governa execução, comparação, prioridades, stocks e leitura de IA.</p></div><div class="ff-a2-quick"><button type="button" data-a2-range="7">7 dias</button><button type="button" data-a2-range="30">30 dias</button><button type="button" data-a2-range="90">90 dias</button></div></div>';
        echo '<div class="ff-a2-filter-grid">';
        echo '<label>De<input type="date" name="from" value="' . esc_attr($from) . '"></label>';
        echo '<label>Até<input type="date" name="to" value="' . esc_attr($to) . '"></label>';
        echo '<label>Cliente<select name="client_id"><option value="0">Todos os clientes</option>';
        foreach ($clients as $client) {
            $id = (int)($client['id'] ?? 0);
            echo '<option value="' . $id . '"' . selected($selected_client_id, $id, false) . '>' . esc_html((string)($client['name'] ?? ('Cliente #' . $id))) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Campanha / Projeto<select name="project_id"><option value="0">Todas as campanhas</option>';
        foreach ($projects as $project) {
            $id = (int)($project['id'] ?? 0);
            echo '<option value="' . $id . '" data-client-id="' . (int)($project['client_id'] ?? 0) . '"' . selected($selected_project_id, $id, false) . '>' . esc_html((string)($project['name'] ?? ('Projeto #' . $id))) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Rota<select name="route_id"><option value="0">Todas as rotas</option>';
        foreach ($routes as $route) {
            $id = (int)($route['id'] ?? 0);
            $label = '#' . $id . ' · ' . ((string)($route['date'] ?? '') ?: 'sem data');
            echo '<option value="' . $id . '" data-client-id="' . (int)($route['client_id'] ?? 0) . '" data-project-id="' . (int)($route['project_id'] ?? 0) . '"' . selected($selected_route_id, $id, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Operativo / Owner<select name="user_id"><option value="0">Toda a equipa</option>';
        foreach ($users as $user) {
            $id = (int)($user->ID ?? 0);
            $label = (string)($user->display_name ?: $user->user_login);
            if (!empty($user->user_login) && $label !== (string)$user->user_login) $label .= ' [' . (string)$user->user_login . ']';
            echo '<option value="' . $id . '"' . selected($selected_user_id, $id, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Loja / PDV<select name="location_id"><option value="0">Todas as lojas</option>';
        foreach ($locations as $location) {
            $id = (int)($location['id'] ?? 0);
            echo '<option value="' . $id . '" data-client-id="' . (int)($location['client_id'] ?? 0) . '" data-project-id="' . (int)($location['project_id'] ?? 0) . '"' . selected($selected_location_id, $id, false) . '>' . esc_html((string)($location['name'] ?? ('PDV #' . $id))) . '</option>';
        }
        echo '</select></label>';
        echo '</div>';
        echo '<div class="ff-a2-filter-actions"><button type="submit" class="button button-primary">Aplicar contexto</button><a class="button" href="' . esc_url(add_query_arg(['page'=>'routespro-assignments-hub','tab'=>'analytics'], admin_url('admin.php'))) . '#ff-analytics2-workspace">Limpar filtros</a><span class="ff-a2-filter-note">O drill-down mantém o mesmo motor analítico e apenas estreita o âmbito.</span></div>';
        echo '</form>';

        $score_cards = [
            'health'=>['Operational Health','Saúde integrada do contexto'],
            'execution'=>['Execution Score','Execução e pontualidade'],
            'risk'=>['Risk Score','Alertas determinísticos'],
            'opportunity'=>['Opportunity Score','Margem operacional observável'],
            'stock'=>['Stock Risk','Risco do snapshot atual'],
            'confidence'=>['Confidence Score','Cobertura e confiança das fontes'],
        ];
        echo '<div class="ff-a2-score-grid">';
        foreach ($score_cards as $key => $card) {
            echo '<article class="ff-a2-score is-empty" data-a2-score="' . esc_attr($key) . '"><span>' . esc_html($card[0]) . '</span><strong data-a2-score-value>n.d.</strong><b data-a2-score-label>A calcular</b><small data-a2-score-detail>' . esc_html($card[1]) . '</small></article>';
        }
        echo '</div>';
        echo '</div>'; // #ff-a2-legacy-control-fallback

        echo '<div class="ff-a2-columns">';
        echo '<div class="ff-a2-panel"><div class="ff-a2-panel-head"><div><h4>Comparação temporal</h4><p>Execução do período atual contra uma janela anterior de duração equivalente.</p></div><em data-a2-period-meta>A calcular visitas...</em></div><div class="ff-a2-trend" data-a2-trend><div class="ff-a2-empty">A calcular tendência...</div></div></div>';
        echo '<div class="ff-a2-panel"><div class="ff-a2-panel-head"><div><h4>FieldFlow Intelligence</h4><p>Leitura factual, com IA apenas quando disponível e sempre apoiada nas métricas calculadas.</p></div><em>Human review</em></div><div data-a2-ai><div class="ff-a2-empty">A preparar leitura operacional...</div></div></div>';
        echo '</div>';
        echo '<div class="ff-a2-panel ff-a2-ask-panel"><div class="ff-a2-panel-head"><div><h4>Perguntar ao FieldFlow</h4><p>Faz perguntas em linguagem natural sobre o contexto selecionado. As respostas usam apenas evidência FieldFlow, distinguem observado de projetado e nunca executam alterações.</p></div><em>Grounded · Read-only</em></div><div class="ff-a2-ask-shell" data-a2-ask><div class="ff-a2-ask-suggestions"><button type="button" data-a2-ask-question="Quais são as lojas que devo priorizar a seguir?">Lojas a priorizar</button><button type="button" data-a2-ask-question="O que está previsto para hoje e para os próximos dias com rota?">Rotas próximas</button><button type="button" data-a2-ask-question="Que riscos exigem acompanhamento?">Riscos</button><button type="button" data-a2-ask-question="Que oportunidades estão mais fortes?">Oportunidades</button><button type="button" data-a2-ask-question="O que devo fazer amanhã?">O que fazer amanhã?</button><button type="button" data-a2-ask-question="Como funciona o FieldFlow?">Como funciona?</button></div><div class="ff-a2-ask-thread" data-a2-ask-thread><div class="ff-a2-ask-empty"><strong>Pergunta pelo que interessa.</strong><span>A conversa usa os filtros desta página e não fica gravada como dado operacional.</span></div></div><form class="ff-a2-ask-form" data-a2-ask-form><textarea rows="2" maxlength="700" data-a2-ask-input placeholder="Ex.: Que lojas estão a deteriorar-se e qual é a próxima ação recomendada?"></textarea><div class="ff-a2-ask-actions"><span data-a2-ask-meta>Contexto atual · revisão humana obrigatória</span><button type="button" class="button" data-a2-ask-clear>Limpar</button><button type="submit" class="button button-primary" data-a2-ask-submit>Perguntar</button></div></form></div></div>';
        echo '<div class="ff-a2-panel ff-a2-adaptive-panel"><div class="ff-a2-panel-head"><div><h4>Adaptive Intelligence</h4><p>Prioriza recomendações com base em feedback e outcomes históricos, sem alterar os scores operacionais.</p></div><em>Assistido · Human review</em></div><div data-a2-adaptive><div class="ff-a2-empty">A avaliar padrões históricos...</div></div></div>';
        echo '<div class="ff-a2-panel ff-a2-predictive-panel"><div class="ff-a2-panel-head"><div><h4>Predictive Intelligence</h4><p>Projeta a próxima janela com base em execução, tendência, risco, stock, alertas e confiança. É uma projeção determinística, não uma probabilidade nem um facto futuro.</p></div><em>Forward view · Human review</em></div><div data-a2-predictive><div class="ff-a2-empty">A calcular projeção...</div></div></div>';

        echo '<div class="ff-a2-panel ff-a2-store-panel"><div class="ff-a2-panel-head"><div><h4>Store Intelligence por loja</h4><p>Health, execução, risco, oportunidade, stock, tendência, confiança e risco futuro calculados por PDV. A previsão nunca altera Health, Risk, Opportunity ou a prioridade base.</p></div><em>Top 12</em></div><div class="ff-a2-store-summary" data-a2-store-summary><span class="ff-a2-store-chip">A calcular perfis...</span></div><div class="ff-a2-table-wrap"><table class="ff-a2-table ff-a2-store-table"><thead><tr><th>Loja</th><th>Health</th><th>Execução</th><th>Risco</th><th>Oportunidade</th><th>Stock</th><th>Tendência</th><th>Prioridade</th><th>Ação</th></tr></thead><tbody data-a2-store-body><tr><td colspan="9"><div class="ff-a2-empty">A calcular Store Intelligence...</div></td></tr></tbody></table></div></div>';
        echo '<div class="ff-a2-panel ff-a2-contextual-panel"><div class="ff-a2-panel-head"><div><h4>Ações prioritárias para o próximo ciclo</h4><p>Next Best Action por loja, produto e tipo de problema, construída apenas com sinais observados e projeções explicáveis. Não executa alterações automaticamente.</p></div><em>Contextual · Human review</em></div><div data-a2-contextual><div class="ff-a2-empty">A cruzar loja, produto e tipo de problema...</div></div></div>';
        echo '<div class="ff-a2-columns">';
        echo '<div class="ff-a2-panel"><div class="ff-a2-panel-head"><div><h4>Lojas em deterioração</h4><p>PDVs com queda temporal, Share Gap ou perda de frentes material.</p></div><em>Watchlist</em></div><div class="ff-a2-store-list" data-a2-deteriorating><div class="ff-a2-empty">A analisar tendência...</div></div></div>';
        echo '<div class="ff-a2-panel"><div class="ff-a2-panel-head"><div><h4>Lojas recuperadas</h4><p>PDVs com evolução positiva observada no período ou outcomes favoráveis.</p></div><em>Recovery</em></div><div class="ff-a2-store-list" data-a2-recovered><div class="ff-a2-empty">A analisar recuperações...</div></div></div>';
        echo '</div>';
        echo '<div class="ff-a2-panel"><div class="ff-a2-panel-head"><div><h4>Rotas a acompanhar</h4><p>As rotas com menor execução aparecem primeiro. O drill-down aplica a rota ao filtro universal.</p></div><em>Top 8</em></div><div class="ff-a2-route-list" data-a2-routes><div class="ff-a2-empty">A carregar rotas...</div></div></div>';

        echo '<div class="ff-analytics-help"><strong>Fonte de verdade Analytics 2.0:</strong> os Decision Scores e Store Opportunity & Risk Engine são determinísticos e não inferem causas. A Adaptive Intelligence usa feedback e outcomes apenas para confiança e ordenação assistida. A Predictive Intelligence projeta a próxima janela com sinais observáveis, mas não representa probabilidade nem facto futuro. A Contextual Predictive Intelligence transforma sinais de loja, produto e problema em Next Best Actions explicáveis, sempre sujeitas a revisão humana. Perguntar ao FieldFlow usa apenas esta evidência já delimitada, com conversa read-only e sem inferir causalidade. Risk, Opportunity, Health, rotas, stocks e dados operacionais nunca são alterados automaticamente.</div>';
        echo '</section>';
    }

    private static function render_learning_workspace(array $clients, array $projects, array $routes, array $users, array $locations, int $selected_client_id, int $selected_project_id, int $selected_route_id): void {
        $selectedUser = absint($_GET['user_id'] ?? 0);
        $selectedLocation = absint($_GET['location_id'] ?? 0);
        $selectedStatus = sanitize_key((string)($_GET['learning_status'] ?? ''));
        $filters = array_filter([
            'client_id'=>$selected_client_id,
            'project_id'=>$selected_project_id,
            'route_id'=>$selected_route_id,
            'user_id'=>$selectedUser,
            'location_id'=>$selectedLocation,
        ], static fn($v)=>$v>0);
        $summary = RecommendationLearningService::summary($filters);
        $statuses = in_array($selectedStatus, ['active','accepted','deferred','resolved','not_relevant'], true) ? [$selectedStatus] : [];
        $recommendations = RecommendationLearningService::recent(120, $filters, $statuses);
        $adaptive = class_exists('\RoutesPro\Analytics\AdaptiveIntelligenceService')
            ? AdaptiveIntelligenceService::analyse($filters, $recommendations, 16)
            : [];
        if (!empty($adaptive['available']) && is_array($adaptive['recommendations'] ?? null)) {
            $recommendations = array_values($adaptive['recommendations']);
        }
        $locationMap = [];
        foreach ($locations as $location) $locationMap[(int)($location['id'] ?? 0)] = (string)($location['name'] ?? ('PDV #' . (int)($location['id'] ?? 0)));
        $userMap = [];
        foreach ($users as $user) $userMap[(int)$user->ID] = (string)($user->display_name ?: $user->user_login);
        $statusLabels = ['active'=>'Ativa','accepted'=>'Faz sentido','deferred'=>'Adiada','resolved'=>'Resolvida','not_relevant'=>'Não relevante'];
        $outcomeLabels = ['pending'=>'A aguardar','not_observed'=>'Sem comparação','stable'=>'Estável','improving'=>'A melhorar','worsening'=>'A piorar','human_resolved'=>'Resolvida por revisão'];

        echo '<div class="ff-learning">';
        echo '<div class="ff-learning-hero"><div><span>ADAPTIVE INTELLIGENCE FOUNDATION</span><h2>Aprendizagem operacional assistida</h2><p>O FieldFlow usa feedback e outcomes para ordenar recomendações e ajustar confiança dentro de limites auditáveis. Risk, Opportunity, Health e dados operacionais continuam determinísticos.</p></div><div class="ff-learning-policy">Adaptação assistida<br><strong>ATIVA</strong><br><small>Auto-mutações OFF</small></div></div>';

        echo '<form method="get" class="ff-learning-filters">';
        echo '<input type="hidden" name="page" value="routespro-assignments-hub"><input type="hidden" name="tab" value="learning">';
        echo '<label><strong>Cliente</strong><select name="client_id"><option value="0">Todos</option>';
        foreach ($clients as $client) echo '<option value="' . (int)$client['id'] . '"' . selected($selected_client_id, (int)$client['id'], false) . '>' . esc_html((string)$client['name']) . '</option>';
        echo '</select></label>';
        echo '<label><strong>Projeto</strong><select name="project_id"><option value="0">Todos</option>';
        foreach ($projects as $project) echo '<option value="' . (int)$project['id'] . '"' . selected($selected_project_id, (int)$project['id'], false) . '>' . esc_html((string)$project['name']) . '</option>';
        echo '</select></label>';
        echo '<label><strong>Rota</strong><select name="route_id"><option value="0">Todas</option>';
        foreach ($routes as $route) echo '<option value="' . (int)$route['id'] . '"' . selected($selected_route_id, (int)$route['id'], false) . '>#' . (int)$route['id'] . ' · ' . esc_html((string)($route['date'] ?? '')) . '</option>';
        echo '</select></label>';
        echo '<label><strong>Operativo</strong><select name="user_id"><option value="0">Todos</option>';
        foreach ($users as $user) echo '<option value="' . (int)$user->ID . '"' . selected($selectedUser, (int)$user->ID, false) . '>' . esc_html((string)($user->display_name ?: $user->user_login)) . '</option>';
        echo '</select></label>';
        echo '<label><strong>Loja</strong><select name="location_id"><option value="0">Todas</option>';
        foreach ($locations as $location) echo '<option value="' . (int)$location['id'] . '"' . selected($selectedLocation, (int)$location['id'], false) . '>' . esc_html((string)$location['name']) . '</option>';
        echo '</select></label>';
        echo '<label><strong>Estado</strong><select name="learning_status"><option value="">Todos</option>';
        foreach ($statusLabels as $key=>$label) echo '<option value="' . esc_attr($key) . '"' . selected($selectedStatus, $key, false) . '>' . esc_html($label) . '</option>';
        echo '</select></label><button class="button button-primary">Aplicar filtros</button></form>';

        echo '<div class="ff-learning-kpis">';
        $kpis = [
            ['Recomendações', (int)($summary['total'] ?? 0), 'Dataset acumulado'],
            ['Em aberto', (int)($summary['open'] ?? 0), 'Ativas, aceites ou adiadas'],
            ['Faz sentido', (int)($summary['accepted'] ?? 0), 'Confirmadas por revisão humana'],
            ['Resolvidas', (int)($summary['resolved'] ?? 0), 'Fechadas por revisão'],
            ['Feedback', number_format_i18n((float)($summary['feedback_coverage'] ?? 0), 1) . '%', 'Cobertura do dataset'],
            ['Outcome', number_format_i18n((float)($summary['outcome_coverage'] ?? 0), 1) . '%', 'Já observado posteriormente'],
        ];
        foreach ($kpis as $kpi) echo '<div class="ff-learning-kpi"><span>' . esc_html((string)$kpi[0]) . '</span><strong>' . esc_html((string)$kpi[1]) . '</strong><small>' . esc_html((string)$kpi[2]) . '</small></div>';
        echo '</div>';

        echo '<div class="ff-learning-note"><strong>Regra de governação.</strong> “Faz sentido”, “Não relevante”, “Adiar” e “Resolvido” são sinais humanos. Outcomes são observações, não prova de causalidade. A 2.2.441 pode adaptar apenas a ordem das recomendações, o ranking de lojas e a confiança apresentada, com limites explícitos e sem alterar scores operacionais.</div>';

        $adaptiveSummary = is_array($adaptive['summary'] ?? null) ? $adaptive['summary'] : [];
        echo '<div class="ff-adaptive-panel"><div class="ff-adaptive-head"><div><span>ADAPTIVE INTELLIGENCE v1</span><h3>Padrões aprendidos com revisão humana</h3><p>Aprendizagem isolada por cliente e projeto. Nunca mistura evidência entre clientes.</p></div><div class="ff-adaptive-badge">Guardrails ±8 confiança · ±10 ranking</div></div>';
        echo '<div class="ff-adaptive-kpis">';
        $adaptiveKpis = [
            ['Padrões', (int)($adaptiveSummary['patterns'] ?? 0), 'Tipos com evidência'],
            ['Estabelecidos', (int)($adaptiveSummary['established_patterns'] ?? 0), '≥12 sinais úteis'],
            ['Eficácia', number_format_i18n((float)($adaptiveSummary['effectiveness_avg'] ?? 50),1) . '%', 'Outcome suavizado'],
            ['Relevância humana', number_format_i18n((float)($adaptiveSummary['human_relevance_avg'] ?? 50),1) . '%', 'Aceite/resolvido vs não relevante'],
            ['Reordenadas', (int)($adaptiveSummary['recommendations_ranked'] ?? 0), 'Sem alterar severidade'],
            ['Next Best Actions', (int)($adaptiveSummary['next_best_actions'] ?? 0), 'Prioridades assistidas'],
        ];
        foreach ($adaptiveKpis as $kpi) echo '<div class="ff-adaptive-kpi"><span>' . esc_html((string)$kpi[0]) . '</span><strong>' . esc_html((string)$kpi[1]) . '</strong><small>' . esc_html((string)$kpi[2]) . '</small></div>';
        echo '</div>';
        $nextBest = array_values(array_filter((array)($adaptive['next_best_actions'] ?? []), 'is_array'));
        if ($nextBest) {
            echo '<div class="ff-adaptive-next"><h4>Next Best Actions</h4><div class="ff-adaptive-next-grid">';
            foreach (array_slice($nextBest,0,6) as $item) {
                echo '<article><span>' . esc_html(strtoupper((string)($item['severity'] ?? 'medium'))) . ' · ' . esc_html(number_format_i18n((float)($item['rank_score'] ?? 0),1)) . '/100</span><strong>' . esc_html((string)($item['title'] ?? 'Recomendação')) . '</strong><p>' . esc_html((string)($item['action'] ?? '')) . '</p><small>Confiança ' . esc_html(number_format_i18n((float)($item['confidence_score'] ?? 0),1)) . '/100 · ' . (int)($item['evidence_count'] ?? 0) . ' sinais · ' . esc_html((string)($item['maturity'] ?? 'initial')) . '</small></article>';
            }
            echo '</div></div>';
        }
        $patterns = array_values(array_filter((array)($adaptive['patterns'] ?? []), 'is_array'));
        if ($patterns) {
            echo '<details class="ff-adaptive-patterns"><summary>Ver padrões e ajustes aprendidos</summary><div class="ff-table-scroll"><table class="widefat striped"><thead><tr><th>Padrão</th><th>Evidência</th><th>Eficácia</th><th>Relevância</th><th>Δ confiança</th><th>Δ ranking</th><th>Maturidade</th></tr></thead><tbody>';
            foreach ($patterns as $pattern) {
                $cd=(float)($pattern['confidence_delta'] ?? 0); $rd=(float)($pattern['rank_delta'] ?? 0);
                echo '<tr><td><strong>' . esc_html((string)($pattern['label'] ?? 'Padrão')) . '</strong><div class="ff-note">' . esc_html((string)($pattern['explanation'] ?? '')) . '</div></td><td>' . (int)($pattern['evidence_count'] ?? 0) . '</td><td>' . esc_html(number_format_i18n((float)($pattern['effectiveness_score'] ?? 50),1)) . '%</td><td>' . esc_html(number_format_i18n((float)($pattern['human_relevance_score'] ?? 50),1)) . '%</td><td>' . esc_html(($cd>0?'+':'') . number_format_i18n($cd,1)) . '</td><td>' . esc_html(($rd>0?'+':'') . number_format_i18n($rd,1)) . '</td><td>' . esc_html((string)($pattern['maturity'] ?? 'initial')) . '</td></tr>';
            }
            echo '</tbody></table></div></details>';
        }
        echo '</div>';

        echo '<div class="ff-table-scroll"><table class="widefat striped ff-learning-table"><thead><tr><th>Prioridade</th><th>Recomendação</th><th>Contexto</th><th>Confiança</th><th>Feedback / Outcome</th><th>Ação humana</th></tr></thead><tbody>';
        if (!$recommendations) {
            echo '<tr><td colspan="6"><div class="ff-learning-empty"><strong>Ainda não existem recomendações neste âmbito.</strong><br>Abre Analytics 2.0 com dados do período para o motor detetar sinais e construir o primeiro dataset.</div></td></tr>';
        } else {
            foreach ($recommendations as $rec) {
                $severity = sanitize_key((string)($rec['severity'] ?? 'medium'));
                $status = sanitize_key((string)($rec['status'] ?? 'active'));
                $locationId = absint($rec['location_id'] ?? 0);
                $userId = absint($rec['user_id'] ?? 0);
                $locationName = $locationId > 0 ? ($locationMap[$locationId] ?? ('PDV #' . $locationId)) : 'Âmbito global';
                $contextParts = [];
                if ((int)($rec['client_id'] ?? 0)) $contextParts[] = 'Cliente #' . (int)$rec['client_id'];
                if ((int)($rec['project_id'] ?? 0)) $contextParts[] = 'Projeto #' . (int)$rec['project_id'];
                if ((int)($rec['route_id'] ?? 0)) $contextParts[] = 'Rota #' . (int)$rec['route_id'];
                if ($userId > 0) $contextParts[] = $userMap[$userId] ?? ('User #' . $userId);
                $outcome = sanitize_key((string)($rec['outcome_status'] ?? 'pending'));
                echo '<tr>';
                echo '<td><span class="ff-learning-severity ' . esc_attr($severity) . '">' . esc_html($severity) . '</span><div class="ff-note">Rank ' . esc_html(number_format_i18n((float)($rec['adaptive_rank_score'] ?? 0),1)) . '/100</div><div class="ff-note">' . (int)($rec['occurrence_count'] ?? 0) . ' ocorrência(s)</div></td>';
                echo '<td><strong>' . esc_html((string)($rec['title'] ?? 'Recomendação')) . '</strong><div class="ff-note" style="margin-top:5px">' . esc_html((string)($rec['detail'] ?? '')) . '</div><div class="ff-learning-action-copy"><b>Ação sugerida:</b> ' . esc_html((string)($rec['suggested_action'] ?? '')) . '</div><details><summary>Porque apareceu?</summary><p>' . esc_html((string)($rec['rationale'] ?? '')) . '</p></details></td>';
                echo '<td><strong>' . esc_html($locationName) . '</strong><div class="ff-note">' . esc_html(implode(' · ', $contextParts)) . '</div><div class="ff-note">Último sinal: ' . esc_html((string)($rec['last_seen_at'] ?? '')) . '</div></td>';
                echo '<td><strong>' . esc_html(number_format_i18n((float)($rec['adaptive_confidence_score'] ?? $rec['confidence_score'] ?? 0), 1) . '/100') . '</strong><div class="ff-note">Base ' . esc_html(number_format_i18n((float)($rec['base_confidence_score'] ?? $rec['confidence_score'] ?? 0),1)) . ' · Δ ' . esc_html(((float)($rec['adaptive']['confidence_delta'] ?? 0)>0?'+':'') . number_format_i18n((float)($rec['adaptive']['confidence_delta'] ?? 0),1)) . '</div><div class="ff-note">' . esc_html((string)($rec['metric_key'] ?? '')) . '</div><div class="ff-note">Base ' . esc_html((string)($rec['baseline_value'] ?? 'n.d.')) . ' → atual ' . esc_html((string)($rec['latest_value'] ?? 'n.d.')) . '</div></td>';
                echo '<td><span class="ff-learning-status ' . esc_attr($status) . '">' . esc_html($statusLabels[$status] ?? $status) . '</span><div class="ff-note" style="margin-top:6px">Outcome: ' . esc_html($outcomeLabels[$outcome] ?? $outcome) . '</div>';
                if (!empty($rec['feedback_note'])) echo '<div class="ff-learning-feedback-note">' . esc_html((string)$rec['feedback_note']) . '</div>';
                echo '</td>';
                echo '<td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ff-learning-feedback-form">';
                wp_nonce_field('fieldflow_intelligence_feedback');
                echo '<input type="hidden" name="action" value="fieldflow_intelligence_feedback"><input type="hidden" name="recommendation_id" value="' . (int)$rec['id'] . '">';
                foreach (['client_id'=>$selected_client_id,'project_id'=>$selected_project_id,'route_id'=>$selected_route_id,'user_id'=>$selectedUser,'location_id'=>$selectedLocation] as $key=>$value) echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . (int)$value . '">';
                echo '<input type="text" name="feedback_note" maxlength="500" placeholder="Nota opcional"><div class="ff-learning-feedback-buttons"><button class="button button-small button-primary" name="decision" value="accepted">Faz sentido</button><button class="button button-small" name="decision" value="deferred">Adiar</button><button class="button button-small" name="decision" value="resolved">Resolvido</button><button class="button button-small" name="decision" value="not_relevant">Não relevante</button></div></form></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';
        echo '</div>';
    }

    public static function render_hub() {
        if (!current_user_can('routespro_manage')) return;
        self::handle_post_actions();
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $tab = sanitize_key($_GET['tab'] ?? 'overview');
        $roles = self::get_roles();
        $users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
        $clients = $wpdb->get_results("SELECT id, name, meta_json FROM {$px}clients ORDER BY name ASC", ARRAY_A) ?: [];
        $projects = $wpdb->get_results("SELECT id, client_id, name, meta_json FROM {$px}projects ORDER BY name ASC", ARRAY_A) ?: [];
        $routes = $wpdb->get_results("SELECT id, client_id, project_id, owner_user_id, date, status FROM {$px}routes ORDER BY date DESC, id DESC LIMIT 300", ARRAY_A) ?: [];
        $forms = $wpdb->get_results('SELECT id, title, status, schema_json FROM ' . FormsModule::table() . ' ORDER BY id DESC LIMIT 200', ARRAY_A) ?: [];
        $stops = $wpdb->get_results("SELECT rs.id, rs.route_id, rs.seq, COALESCE(l.name,'PDV') AS location_name FROM {$px}route_stops rs LEFT JOIN {$px}locations l ON l.id=rs.location_id ORDER BY rs.id DESC LIMIT 300", ARRAY_A) ?: [];
        $locations = $wpdb->get_results("SELECT id, client_id, project_id, name FROM {$px}locations ORDER BY name ASC LIMIT 1000", ARRAY_A) ?: [];

        $selected_client_id = absint($_GET['client_id'] ?? ($_POST['client_id'] ?? 0));
        $selected_project_id = absint($_GET['project_id'] ?? ($_POST['project_id'] ?? 0));
        $selected_route_id = absint($_GET['route_id'] ?? ($_POST['route_id'] ?? 0));
        $selected_owner_user_id = absint($_GET['owner_user_id'] ?? ($_POST['owner_user_id'] ?? 0));
        $selected_operation_date = sanitize_text_field((string)($_GET['date'] ?? ($_POST['date'] ?? current_time('Y-m-d'))));
        if (!$selected_project_id && $selected_route_id) {
            foreach ($routes as $route) if ((int)$route['id'] === $selected_route_id) $selected_project_id = (int)($route['project_id'] ?? 0);
        }
        if (!$selected_client_id && $selected_project_id) {
            foreach ($projects as $project) if ((int)$project['id'] === $selected_project_id) $selected_client_id = (int)($project['client_id'] ?? 0);
        }
        if (!$selected_client_id && $selected_route_id) {
            foreach ($routes as $route) if ((int)$route['id'] === $selected_route_id) $selected_client_id = (int)($route['client_id'] ?? 0);
        }
        if (!$selected_owner_user_id && $selected_route_id) {
            foreach ($routes as $route) if ((int)$route['id'] === $selected_route_id) $selected_owner_user_id = (int)($route['owner_user_id'] ?? 0);
        }

        echo '<div class="wrap">';
        Branding::render_header('FieldFlow Intelligence Center');
        self::render_status_notice();
        echo '<style>
            .ff-hub-grid{display:grid;grid-template-columns:280px minmax(0,1fr);gap:18px;align-items:start}
            .ff-hub-sidebar,.ff-hub-main{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:18px}
            .ff-kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px}
            .ff-kpi{border:1px solid #e5e7eb;border-radius:14px;padding:16px;background:linear-gradient(180deg,#fff,#f8fafc)}
            .ff-kpi strong{display:block;font-size:24px;line-height:1.1;margin-top:6px}
            .ff-chip{display:inline-block;padding:6px 10px;border-radius:999px;background:#eef2ff;color:#3730a3;font-weight:600;font-size:12px;margin-right:8px;margin-bottom:8px}
            .ff-section-title{margin:0 0 12px 0;font-size:18px}
            .ff-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
            .ff-note{color:#64748b;font-size:13px}
            .ff-sticky{position:sticky;top:46px}
            .ff-list{margin:0;padding-left:18px}
            .ff-list li{margin:0 0 6px 0}
            .ff-table td code{white-space:normal;word-break:break-word}
            .ff-analytics-admin{display:flex;flex-direction:column;gap:18px}.ff-analytics-hero{position:relative;overflow:hidden;padding:22px;border-radius:20px;background:linear-gradient(135deg,#0f172a,#1e293b 58%,#2563eb);color:#fff}.ff-analytics-hero h2{margin:0 0 7px;color:#fff;font-size:25px}.ff-analytics-hero p{margin:0;color:rgba(255,255,255,.78);max-width:780px}.ff-analytics-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}.ff-analytics-summary .ff-analytics-kpi{padding:15px;border:1px solid #e2e8f0;border-radius:16px;background:#fff;box-shadow:0 8px 22px rgba(15,23,42,.05)}.ff-analytics-kpi span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:800}.ff-analytics-kpi strong{display:block;margin-top:6px;font-size:26px;color:#0f172a}.ff-analytics-builder-grid{display:grid;grid-template-columns:minmax(300px,.85fr) minmax(360px,1.15fr);gap:16px;align-items:start}.ff-analytics-step{border-radius:18px!important;padding:18px!important;box-shadow:0 10px 28px rgba(15,23,42,.05)}.ff-analytics-step h3{display:flex;align-items:center;gap:9px}.ff-analytics-step h3:before{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:9px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:900}.ff-analytics-step.step-dashboard h3:before{content:"1"}.ff-analytics-step.step-group h3:before{content:"2"}.ff-analytics-step.step-widget h3:before{content:"3"}.ff-analytics-admin label strong{display:inline-block;margin-bottom:5px;color:#334155}.ff-analytics-admin input[type=text],.ff-analytics-admin input[type=number],.ff-analytics-admin input[type=search],.ff-analytics-admin select,.ff-analytics-admin textarea{width:100%;max-width:none;min-height:40px;border-radius:10px}.ff-analytics-admin .submit{margin-bottom:0}.ff-analytics-admin .button-primary{border-radius:10px;min-height:38px;padding-inline:16px}.ff-analytics-admin>h3{margin:4px 0 -8px;padding-top:4px;font-size:18px}.ff-analytics-admin .ff-table-scroll{width:100%;overflow:auto;border-radius:14px;border:1px solid #e2e8f0;background:#fff}.ff-analytics-admin table.widefat{width:100%;min-width:920px;border:0;box-shadow:none}.ff-analytics-admin table.widefat th{background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.05em}.ff-analytics-admin table.widefat td{vertical-align:top}.ff-analytics-admin .ff-note{line-height:1.4}.ff-analytics-help{padding:12px 14px;border:1px solid #bfdbfe;border-radius:14px;background:#eff6ff;color:#1e3a8a}.ff-analytics-admin .ff-form-grid[data-ff-context="analytics-form"]{grid-template-columns:repeat(3,minmax(220px,1fr))}
            .ff-command-hero{position:relative;overflow:hidden;padding:24px;border-radius:22px;background:linear-gradient(135deg,#0f172a 0%,#172554 58%,#0f766e 100%);color:#fff;margin-bottom:18px}.ff-command-hero:after{content:"INTELLIGENCE";position:absolute;right:18px;bottom:-10px;font-size:46px;font-weight:950;letter-spacing:.08em;color:rgba(255,255,255,.055);pointer-events:none}.ff-command-hero h2{margin:0 0 7px;color:#fff;font-size:26px}.ff-command-hero p{margin:0;max-width:850px;color:#dbeafe}.ff-command-badges{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.ff-command-badge{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:999px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);font-size:12px;font-weight:800}.ff-command-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}.ff-command-kpi{padding:16px;border:1px solid #e2e8f0;border-radius:16px;background:#fff;box-shadow:0 10px 25px rgba(15,23,42,.045)}.ff-command-kpi span{display:block;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.055em;font-weight:850}.ff-command-kpi strong{display:block;margin-top:6px;font-size:26px;color:#0f172a}.ff-command-kpi small{display:block;margin-top:4px;color:#64748b}.ff-command-columns{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(320px,.9fr);gap:16px}.ff-command-panel{border:1px solid #e2e8f0;border-radius:18px;padding:17px;background:#fff}.ff-command-panel h3{margin:0 0 12px}.ff-readiness{display:grid;grid-template-columns:92px minmax(0,1fr);gap:16px;align-items:center}.ff-readiness-score{display:flex;align-items:center;justify-content:center;width:86px;height:86px;border-radius:999px;background:#eff6ff;border:8px solid #dbeafe;color:#1d4ed8;font-size:22px;font-weight:900}.ff-readiness-list{display:grid;gap:7px}.ff-readiness-item{display:flex;justify-content:space-between;gap:10px;padding:7px 0;border-bottom:1px solid #eef2f7}.ff-readiness-item:last-child{border-bottom:0}.ff-status-dot{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:800}.ff-status-dot:before{content:"";width:8px;height:8px;border-radius:999px;background:#f59e0b}.ff-status-dot.ok:before{background:#22c55e}.ff-alert-list{display:grid;gap:9px}.ff-alert-row{display:grid;grid-template-columns:auto minmax(0,1fr);gap:10px;padding:10px;border-radius:13px;background:#f8fafc;border:1px solid #e2e8f0}.ff-alert-severity{display:inline-flex;align-items:center;justify-content:center;min-width:62px;height:25px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:10px;font-weight:900;text-transform:uppercase}.ff-alert-severity.high,.ff-alert-severity.critical{background:#fee2e2;color:#991b1b}.ff-command-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.ff-command-actions .button{border-radius:10px}.ff-source-of-truth{padding:12px 14px;border-radius:14px;background:#ecfeff;border:1px solid #a5f3fc;color:#155e75;line-height:1.45}.ff-source-of-truth strong{color:#164e63}
            .ff-ai-builder{position:relative;overflow:hidden;border:1px solid #c7d2fe;border-radius:22px;background:linear-gradient(145deg,#eef2ff 0%,#fff 48%,#ecfeff 100%);padding:20px;box-shadow:0 16px 42px rgba(30,64,175,.08)}.ff-ai-builder:after{content:"IA";position:absolute;right:18px;top:10px;font-size:72px;font-weight:900;color:rgba(37,99,235,.06);pointer-events:none}.ff-ai-builder-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;position:relative;z-index:1}.ff-ai-builder-head h3{margin:0 0 6px;font-size:21px}.ff-ai-provider{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:999px;background:#fff;border:1px solid #bfdbfe;color:#1e3a8a;font-size:12px;font-weight:800;white-space:nowrap}.ff-ai-provider i{width:8px;height:8px;border-radius:999px;background:#22c55e}.ff-ai-provider.off i{background:#f59e0b}.ff-ai-builder-grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr);gap:16px;margin-top:16px;position:relative;z-index:1}.ff-ai-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.ff-ai-form-grid .wide{grid-column:1/-1}.ff-ai-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:14px}.ff-ai-actions .button{min-height:42px;border-radius:11px;padding:6px 16px}.ff-ai-preview{min-height:310px;border:1px solid #dbeafe;border-radius:18px;background:#fff;padding:16px}.ff-ai-preview-empty{display:flex;min-height:276px;align-items:center;justify-content:center;text-align:center;color:#64748b;padding:24px}.ff-ai-preview h4{margin:0 0 5px;font-size:19px}.ff-ai-preview p{color:#64748b}.ff-ai-preview-meta{display:flex;gap:7px;flex-wrap:wrap;margin:10px 0}.ff-ai-preview-meta span,.ff-ai-badge{display:inline-flex;padding:5px 8px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:11px;font-weight:800}.ff-ai-widget-list{display:grid;gap:8px;margin-top:12px}.ff-ai-widget{display:grid;grid-template-columns:34px minmax(0,1fr) auto;gap:9px;align-items:center;padding:9px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc}.ff-ai-widget-no{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:9px;background:#dbeafe;color:#1d4ed8;font-weight:900}.ff-ai-widget strong{display:block}.ff-ai-widget small{display:block;color:#64748b;margin-top:2px}.ff-ai-error{padding:10px 12px;border:1px solid #fecaca;border-radius:12px;background:#fef2f2;color:#991b1b}.ff-ai-loading{opacity:.65;pointer-events:none}.ff-ai-spinner{display:inline-block;width:15px;height:15px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:ff-ai-spin .7s linear infinite}@keyframes ff-ai-spin{to{transform:rotate(360deg)}}.ff-dashboard-audience{display:inline-flex;padding:4px 7px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:11px;font-weight:800;margin-top:5px}.ff-widget-type{display:inline-flex;margin-top:6px;padding:4px 7px;border-radius:999px;background:#ecfeff;color:#155e75;font-size:11px;font-weight:850}.ff-widget-type.is-image{background:#eff6ff;color:#1d4ed8}.ff-widget-type.is-fronts{background:#ecfdf5;color:#047857}.ff-widget-type.is-order{background:#fff7ed;color:#c2410c}.ff-analytics-warning{padding:12px 14px;border:1px solid #fed7aa;border-radius:14px;background:#fff7ed;color:#9a3412}
            .ff-learning{display:flex;flex-direction:column;gap:16px}.ff-learning-hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;padding:22px;border-radius:20px;background:linear-gradient(135deg,#111827,#1e3a8a 62%,#0f766e);color:#fff}.ff-learning-hero span{display:block;font-size:11px;font-weight:900;letter-spacing:.09em;color:#bfdbfe}.ff-learning-hero h2{margin:5px 0 6px;color:#fff;font-size:24px}.ff-learning-hero p{margin:0;max-width:800px;color:#dbeafe}.ff-learning-policy{min-width:150px;padding:12px 14px;border:1px solid rgba(255,255,255,.18);border-radius:14px;background:rgba(255,255,255,.1);font-size:11px;text-transform:uppercase;letter-spacing:.05em}.ff-learning-policy strong{display:block;margin-top:4px;font-size:16px;color:#a7f3d0}.ff-learning-filters{display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:10px;align-items:end;padding:14px;border:1px solid #e2e8f0;border-radius:16px;background:#f8fafc}.ff-learning-filters label strong{display:block;margin-bottom:5px;color:#475569;font-size:12px}.ff-learning-filters select{width:100%;max-width:none}.ff-learning-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}.ff-learning-kpi{padding:14px;border:1px solid #e2e8f0;border-radius:15px;background:#fff}.ff-learning-kpi span{display:block;color:#64748b;font-size:10px;font-weight:900;text-transform:uppercase}.ff-learning-kpi strong{display:block;margin-top:4px;font-size:23px;color:#0f172a}.ff-learning-kpi small{display:block;margin-top:3px;color:#64748b}.ff-learning-note{padding:13px 15px;border:1px solid #bae6fd;border-radius:14px;background:#f0f9ff;color:#0c4a6e;line-height:1.45}.ff-learning .ff-table-scroll{overflow:auto;border:1px solid #e2e8f0;border-radius:16px}.ff-learning-table{min-width:1120px;border:0!important}.ff-learning-table th{background:#f8fafc!important;color:#64748b!important;font-size:10px!important;text-transform:uppercase;letter-spacing:.05em}.ff-learning-table td{vertical-align:top!important}.ff-learning-severity,.ff-learning-status{display:inline-flex;align-items:center;padding:5px 8px;border-radius:999px;font-size:10px;font-weight:900;text-transform:uppercase}.ff-learning-severity{background:#fef3c7;color:#92400e}.ff-learning-severity.high,.ff-learning-severity.critical{background:#fee2e2;color:#991b1b}.ff-learning-status{background:#f1f5f9;color:#475569}.ff-learning-status.accepted{background:#dcfce7;color:#166534}.ff-learning-status.deferred{background:#fef3c7;color:#92400e}.ff-learning-status.resolved{background:#dbeafe;color:#1e40af}.ff-learning-status.not_relevant{background:#f3f4f6;color:#6b7280}.ff-learning-action-copy{margin-top:8px;padding:8px 10px;border-left:3px solid #60a5fa;background:#eff6ff;color:#1e3a8a;font-size:12px;line-height:1.4}.ff-learning-feedback-note{margin-top:7px;padding:7px 9px;border-radius:9px;background:#f8fafc;color:#475569;font-size:12px}.ff-learning-feedback-form{min-width:220px}.ff-learning-feedback-form input[type=text]{width:100%;margin-bottom:7px}.ff-learning-feedback-buttons{display:flex;gap:5px;flex-wrap:wrap}.ff-learning-empty{padding:28px;text-align:center;color:#64748b}
            .ff-adaptive-panel{padding:16px;border:1px solid #c7d2fe;border-radius:18px;background:linear-gradient(145deg,#f8fafc,#eef2ff 55%,#ecfeff);box-shadow:0 12px 30px rgba(30,64,175,.06)}.ff-adaptive-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.ff-adaptive-head span{font-size:10px;font-weight:900;letter-spacing:.08em;color:#1d4ed8}.ff-adaptive-head h3{margin:4px 0 5px}.ff-adaptive-head p{margin:0;color:#64748b}.ff-adaptive-badge{padding:7px 10px;border-radius:999px;background:#fff;border:1px solid #bfdbfe;color:#1e3a8a;font-size:11px;font-weight:850;white-space:nowrap}.ff-adaptive-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:9px;margin-top:14px}.ff-adaptive-kpi{padding:12px;border-radius:13px;background:#fff;border:1px solid #dbeafe}.ff-adaptive-kpi span{display:block;font-size:9px;text-transform:uppercase;font-weight:900;color:#64748b}.ff-adaptive-kpi strong{display:block;margin-top:4px;font-size:20px;color:#0f172a}.ff-adaptive-kpi small{display:block;margin-top:3px;color:#64748b}.ff-adaptive-next{margin-top:14px}.ff-adaptive-next h4{margin:0 0 8px}.ff-adaptive-next-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.ff-adaptive-next article{padding:12px;border:1px solid #e2e8f0;border-radius:13px;background:#fff}.ff-adaptive-next article>span{font-size:9px;font-weight:900;color:#1d4ed8}.ff-adaptive-next article strong{display:block;margin-top:4px}.ff-adaptive-next article p{margin:5px 0;color:#475569;font-size:12px}.ff-adaptive-next article small{color:#64748b}.ff-adaptive-patterns{margin-top:13px}.ff-adaptive-patterns>summary{cursor:pointer;font-weight:850;color:#1e40af;margin-bottom:8px}.ff-adaptive-patterns table{min-width:860px}
            @media (max-width: 1180px){.ff-learning-kpis,.ff-adaptive-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.ff-adaptive-next-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ff-command-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ff-command-columns{grid-template-columns:1fr}.ff-ai-builder-grid,.ff-analytics-builder-grid{grid-template-columns:1fr}.ff-analytics-admin .ff-form-grid[data-ff-context="analytics-form"]{grid-template-columns:repeat(2,minmax(220px,1fr))}}@media (max-width: 980px){.ff-hub-grid{grid-template-columns:1fr}.ff-sticky{position:static}}@media (max-width: 680px){.ff-learning-hero,.ff-adaptive-head{flex-direction:column}.ff-learning-policy,.ff-adaptive-badge{min-width:0}.ff-learning-filters,.ff-learning-kpis,.ff-adaptive-kpis,.ff-adaptive-next-grid{grid-template-columns:1fr}.ff-command-grid{grid-template-columns:1fr}.ff-readiness{grid-template-columns:1fr}.ff-ai-builder{padding:15px}.ff-ai-builder-head{flex-direction:column}.ff-ai-form-grid,.ff-analytics-summary,.ff-analytics-admin .ff-form-grid[data-ff-context="analytics-form"]{grid-template-columns:1fr}.ff-ai-form-grid .wide{grid-column:auto}.ff-ai-actions .button{width:100%}.ff-ai-widget{grid-template-columns:30px minmax(0,1fr)}.ff-ai-widget .ff-ai-badge{grid-column:2}}
        </style>';
        self::render_tab_nav($tab);
        echo '<div class="ff-hub-grid">';
        echo '<aside class="ff-hub-sidebar ff-sticky">';
        echo '<h2 class="ff-section-title">Contexto de trabalho</h2>';
        echo '<form method="get" data-ff-context="sidebar">';
        echo '<input type="hidden" name="page" value="routespro-assignments-hub">';
        echo '<input type="hidden" name="tab" value="' . esc_attr($tab) . '">';
        echo '<p><label><strong>Cliente</strong><br><select name="client_id" data-ff-role="client" style="width:100%"><option value="0">Todos</option>';
        foreach ($clients as $client) echo '<option value="' . (int)$client['id'] . '"' . selected($selected_client_id, (int)$client['id'], false) . '>' . esc_html($client['name']) . '</option>';
        echo '</select></label></p>';
        echo '<p><label><strong>Projeto</strong><br><select name="project_id" data-ff-role="project" style="width:100%"><option value="0">Todos</option>';
        foreach ($projects as $project) {
            $hidden = $selected_client_id && (int)$project['client_id'] !== $selected_client_id ? ' style="display:none"' : '';
            echo '<option value="' . (int)$project['id'] . '" data-client-id="' . (int)$project['client_id'] . '"' . $hidden . selected($selected_project_id, (int)$project['id'], false) . '>' . esc_html($project['name'] . ' #' . (int)$project['id']) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label><strong>Rota</strong><br><select name="route_id" data-ff-role="route" style="width:100%"><option value="0">Todas</option>';
        foreach ($routes as $route) {
            $hidden = '';
            if ($selected_client_id && (int)$route['client_id'] !== $selected_client_id) $hidden = ' style="display:none"';
            if ($selected_project_id && (int)$route['project_id'] !== $selected_project_id) $hidden = ' style="display:none"';
            $label = '#' . (int)$route['id'] . ' · ' . ($route['date'] ?: 'sem data') . ' · ' . ($route['status'] ?: '');
            echo '<option value="' . (int)$route['id'] . '" data-client-id="' . (int)$route['client_id'] . '" data-project-id="' . (int)$route['project_id'] . '"' . $hidden . selected($selected_route_id, (int)$route['id'], false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><button class="button button-primary">Aplicar contexto</button></p>';
        echo '</form>';
        echo '<hr style="margin:18px 0">';
        echo '<div class="ff-source-of-truth"><strong>Fonte única FieldFlow.</strong> Cliente, projeto, rota, formulários, Analytics e IA usam o mesmo contexto. Evita configurar o mesmo âmbito em ecrãs diferentes.</div>';
        echo '</aside>';
        echo '<section class="ff-hub-main">';

        if ($tab === 'overview') {
            $counts = AssignmentResolver::get_overview_counts();
            $intel = self::intelligence_overview_state($selected_client_id, $selected_project_id, $selected_route_id);
            $contextLabel = $selected_route_id ? ('Rota #' . $selected_route_id) : ($selected_project_id ? (self::find_project_name($projects, $selected_project_id) ?: ('Projeto #' . $selected_project_id)) : ($selected_client_id ? (self::find_client_name($clients, $selected_client_id) ?: ('Cliente #' . $selected_client_id)) : 'Visão global'));
            echo '<div class="ff-command-hero"><h2>Command Center</h2><p>Atribuições, Analytics e IA passam a ser uma única cadeia operacional. Primeiro definimos quem e o quê pertence ao contexto. Depois medimos, detetamos desvios e transformamos dados em ação.</p><div class="ff-command-badges"><span class="ff-command-badge">Contexto: ' . esc_html($contextLabel) . '</span><span class="ff-command-badge">IA: ' . esc_html($intel['ai_available'] ? $intel['ai_provider'] : 'motor inteligente FieldFlow') . '</span><span class="ff-command-badge">Readiness: ' . (int)$intel['readiness'] . '%</span></div></div>';
            $peopleCount = $selected_route_id ? (int)$intel['route_people'] : ($selected_project_id ? (int)$intel['project_people'] : ($selected_client_id ? (int)$intel['client_users'] : (int)$counts['project_assignments']));
            $learningSummary = is_array($intel['learning_summary'] ?? null) ? $intel['learning_summary'] : [];
            echo '<div class="ff-command-grid">';
            echo '<div class="ff-command-kpi"><span>Equipa / owners</span><strong>' . $peopleCount . '</strong><small>Pessoas únicas no contexto</small></div>';
            echo '<div class="ff-command-kpi"><span>Formulários ligados</span><strong>' . (int)$intel['form_bindings'] . '</strong><small>Bindings válidos no contexto</small></div>';
            echo '<div class="ff-command-kpi"><span>Dashboards</span><strong>' . (int)$intel['dashboards'] . '</strong><small>' . (int)$intel['ai_dashboards'] . ' criados com IA</small></div>';
            echo '<div class="ff-command-kpi"><span>Widgets ativos</span><strong>' . (int)$intel['widgets'] . '</strong><small>Métricas publicadas</small></div>';
            echo '<div class="ff-command-kpi"><span>Alertas abertos</span><strong>' . (int)$intel['open_alerts'] . '</strong><small>' . (int)$intel['priority_alerts'] . ' de prioridade alta</small></div>';
            echo '<div class="ff-command-kpi"><span>Rotas</span><strong>' . (int)$counts['routes'] . '</strong><small>' . (int)$counts['route_assignments'] . ' atribuições ativas</small></div>';
            echo '<div class="ff-command-kpi"><span>Projetos</span><strong>' . (int)$counts['projects'] . '</strong><small>' . (int)$counts['clients'] . ' clientes</small></div>';
            echo '<div class="ff-command-kpi"><span>Readiness</span><strong>' . (int)$intel['readiness'] . '%</strong><small>Configuração do contexto</small></div>';
            echo '<div class="ff-command-kpi"><span>Recomendações</span><strong>' . (int)($learningSummary['open'] ?? 0) . '</strong><small>' . (int)($learningSummary['accepted'] ?? 0) . ' confirmadas · feedback ' . esc_html(number_format_i18n((float)($learningSummary['feedback_coverage'] ?? 0), 1)) . '%</small></div>';
            echo '</div>';

            echo '<div class="ff-command-columns">';
            echo '<div class="ff-command-panel"><h3>Saúde do contexto</h3><div class="ff-readiness"><div class="ff-readiness-score">' . (int)$intel['readiness'] . '%</div><div class="ff-readiness-list">';
            foreach ((array)$intel['checks'] as $label=>$ok) echo '<div class="ff-readiness-item"><span>' . esc_html((string)$label) . '</span><span class="ff-status-dot ' . ($ok ? 'ok' : '') . '">' . ($ok ? 'OK' : 'Configurar') . '</span></div>';
            echo '</div></div><div class="ff-command-actions"><a class="button" href="' . esc_url(self::admin_url(['tab'=>'projects','client_id'=>$selected_client_id,'project_id'=>$selected_project_id])) . '">Equipa e owners</a><a class="button" href="' . esc_url(self::admin_url(['tab'=>'forms','client_id'=>$selected_client_id,'project_id'=>$selected_project_id,'route_id'=>$selected_route_id])) . '">Formulários</a><a class="button button-primary" href="' . esc_url(self::admin_url(['tab'=>'analytics','client_id'=>$selected_client_id,'project_id'=>$selected_project_id,'route_id'=>$selected_route_id])) . '">Analytics & IA</a><a class="button" href="' . esc_url(self::admin_url(['tab'=>'learning','client_id'=>$selected_client_id,'project_id'=>$selected_project_id,'route_id'=>$selected_route_id])) . '">Aprendizagem</a></div></div>';

            echo '<div class="ff-command-panel"><h3>Intelligence Feed</h3>';
            if (!$intel['alerts']) {
                echo '<p class="ff-note">Ainda não existem alertas persistidos para este contexto. Ao abrir Analytics, o motor operacional analisa execução, comparação temporal, stocks, Share Space e frentes, sem inventar causas.</p>';
            } else {
                echo '<div class="ff-alert-list">';
                foreach ((array)$intel['alerts'] as $alert) {
                    $severity = sanitize_key((string)($alert['severity'] ?? 'medium'));
                    echo '<div class="ff-alert-row"><span class="ff-alert-severity ' . esc_attr($severity) . '">' . esc_html($severity) . '</span><div><strong>' . esc_html((string)($alert['title'] ?? 'Alerta')) . '</strong><div class="ff-note">' . esc_html((string)($alert['detail'] ?? '')) . '</div></div></div>';
                }
                echo '</div>';
            }
            echo '<div class="ff-command-actions"><a class="button button-primary" href="' . esc_url(self::admin_url(['tab'=>'analytics','client_id'=>$selected_client_id,'project_id'=>$selected_project_id,'route_id'=>$selected_route_id])) . '">Abrir Analytics & IA</a><a class="button" href="' . esc_url(self::admin_url(['tab'=>'learning','client_id'=>$selected_client_id,'project_id'=>$selected_project_id,'route_id'=>$selected_route_id])) . '">Ver recomendações</a><a class="button" href="' . esc_url(admin_url('admin.php?page=routespro-ai')) . '">Configurar motor IA</a></div></div>';
            echo '</div>';

            echo '<div class="routespro-card" style="margin-top:18px"><h3 style="margin-top:0">Modelo único FieldFlow</h3><span class="ff-chip">1. Contexto</span><span class="ff-chip">2. Equipa</span><span class="ff-chip">3. Formulários</span><span class="ff-chip">4. Dados</span><span class="ff-chip">5. Analytics</span><span class="ff-chip">6. Intelligence</span><span class="ff-chip">7. Aprendizagem</span><p class="ff-note">O centro é a fonte de verdade para Cliente → Projeto → Rota → Loja → Formulário → Métrica → Insight → Recomendação → Feedback → Outcome. Nesta release o dataset aprende com observações e revisão humana, mas nenhum score ou dado operacional é alterado automaticamente.</p></div>';
        } elseif ($tab === 'operations') {
            echo '<div class="ff-command-hero"><h2>Operação diária</h2><p>Prioridade e controlo de execução determinísticos para coordenação. Junta rota do dia, estado canónico das visitas, reportes e risco de periodicidade sem alterar dados e sem chamar Google ou IA externa.</p><div class="ff-command-badges"><span class="ff-command-badge">Prioridade: fieldflow-daily-operational-priority-v1</span><span class="ff-command-badge">Execução: fieldflow-daily-execution-exception-control-v1</span><span class="ff-command-badge">Read-only</span><span class="ff-command-badge">0 chamadas externas</span></div></div>';
            echo '<form method="get" class="routespro-card" style="margin:14px 0 18px">';
            echo '<input type="hidden" name="page" value="routespro-assignments-hub"><input type="hidden" name="tab" value="operations"><input type="hidden" name="client_id" value="' . (int)$selected_client_id . '"><input type="hidden" name="project_id" value="' . (int)$selected_project_id . '">';
            echo '<div class="ff-form-grid"><p><label><strong>Data operacional</strong><br><input type="date" name="date" value="' . esc_attr($selected_operation_date) . '" style="width:100%"></label></p>';
            echo '<p><label><strong>Operativo</strong><br><select name="owner_user_id" style="width:100%"><option value="0">Selecionar merchandiser</option>' . self::render_user_options($users, $selected_owner_user_id ? [$selected_owner_user_id] : []) . '</select></label></p>';
            echo '<p style="align-self:end"><button class="button button-primary">Analisar prioridades</button></p></div></form>';

            if ($selected_owner_user_id > 0) {
                $dailyPriority = \RoutesPro\Services\DailyOperationalPriorityService::forUser($selected_owner_user_id, $selected_operation_date, $selected_project_id, $selected_client_id);
                $dailyExecution = \RoutesPro\Services\DailyExecutionExceptionService::forUser($selected_owner_user_id, $selected_operation_date, $selected_project_id, $selected_client_id);
                if (is_wp_error($dailyPriority) || is_wp_error($dailyExecution)) {
                    $dailyError = is_wp_error($dailyPriority) ? $dailyPriority : $dailyExecution;
                    echo '<div class="notice notice-error inline"><p>' . esc_html($dailyError->get_error_message()) . '</p></div>';
                } else {
                    $summary = (array)($dailyPriority['summary'] ?? []);
                    $health = (array)($dailyPriority['health'] ?? []);
                    $items = (array)($dailyPriority['priorities'] ?? []);
                    $executionSummary = (array)($dailyExecution['summary'] ?? []);
                    $executionExceptions = (array)($dailyExecution['exceptions'] ?? []);
                    $healthLevel = sanitize_key((string)($health['level'] ?? 'clear'));
                    $healthLabel = (string)($health['label'] ?? 'Operação controlada');
                    echo '<div class="routespro-card" style="margin-bottom:16px"><div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap"><div><h3 style="margin:0 0 5px">Foco de ' . esc_html($selected_operation_date) . '</h3><p class="ff-note" style="margin:0">O ranking usa apenas factos operacionais já guardados no FieldFlow. Nenhuma ação é executada automaticamente.</p></div><span class="ff-chip">' . esc_html($healthLabel) . '</span></div></div>';
                    echo '<div class="ff-command-grid">';
                    echo '<div class="ff-command-kpi"><span>Rotas hoje</span><strong>' . (int)($summary['routes_today'] ?? 0) . '</strong><small>Publicadas no âmbito</small></div>';
                    echo '<div class="ff-command-kpi"><span>Concluídas</span><strong>' . (int)($summary['stops_completed'] ?? 0) . '</strong><small>Visitas executadas hoje</small></div>';
                    echo '<div class="ff-command-kpi"><span>Pendentes</span><strong>' . (int)($summary['stops_pending'] ?? 0) . '</strong><small>Paragens por concluir</small></div>';
                    echo '<div class="ff-command-kpi"><span>Críticas no mês</span><strong>' . (int)($summary['critical_unresolved'] ?? 0) . '</strong><small>Sem cobertura suficiente</small></div>';
                    echo '<div class="ff-command-kpi"><span>Atenção</span><strong>' . (int)($summary['attention_unresolved'] ?? 0) . '</strong><small>Periodicidade a acompanhar</small></div>';
                    echo '<div class="ff-command-kpi"><span>Prioridades</span><strong>' . (int)($summary['focus_items'] ?? 0) . '</strong><small>Ações sugeridas</small></div>';
                    echo '<div class="ff-command-kpi"><span>Alertas execução</span><strong>' . (int)($executionSummary['exceptions'] ?? 0) . '</strong><small>Incoerências ou fechos pendentes</small></div>';
                    echo '</div>';
                    echo '<div class="routespro-card" style="margin-top:16px"><h3 style="margin-top:0">Fila de prioridade</h3>';
                    if (!$items) {
                        echo '<p class="ff-note">Sem prioridades críticas ou pendentes para este operativo nesta data.</p>';
                    } else {
                        echo '<div class="ff-table-scroll"><table class="widefat striped ff-table"><thead><tr><th>Prioridade</th><th>Loja</th><th>Razão</th><th>Ação</th></tr></thead><tbody>';
                        foreach ($items as $item) {
                            $severity = sanitize_key((string)($item['severity'] ?? 'attention'));
                            $action = sanitize_key((string)($item['action'] ?? ''));
                            echo '<tr><td><strong>' . esc_html((string)($item['title'] ?? 'Prioridade')) . '</strong><br><span class="ff-chip">' . esc_html($severity) . '</span></td>';
                            echo '<td><strong>' . esc_html((string)($item['store_name'] ?? 'Loja')) . '</strong><br><span class="ff-note">' . esc_html(implode(' · ', array_filter([(string)($item['project_name'] ?? ''),(string)($item['client_name'] ?? '')]))) . '</span></td>';
                            echo '<td>' . esc_html((string)($item['reason'] ?? '')) . '</td><td>';
                            if ($action === 'open_route' && !empty($item['route_id'])) echo '<a class="button button-small" href="' . esc_url(admin_url('admin.php?page=routespro-routes&route_id=' . (int)$item['route_id'])) . '">Abrir rota</a>';
                            elseif ($action === 'open_planner') echo '<a class="button button-small" href="' . esc_url(admin_url('admin.php?page=routespro-routes')) . '">Planear</a>';
                            elseif ($action === 'review_justification') echo '<span class="ff-note">Rever justificação na Cobertura do mês</span>';
                            echo '</td></tr>';
                        }
                        echo '</tbody></table></div>';
                    }
                    echo '</div>';

                    echo '<div class="routespro-card" style="margin-top:16px"><h3 style="margin-top:0">Controlo de execução e exceções</h3><p class="ff-note">Autoridade única de leitura para alinhar Minha Rota e Reportar. O painel não corrige dados automaticamente, mostra a incoerência e a ação humana necessária.</p>';
                    if (!$executionExceptions) {
                        echo '<p><span class="ff-chip">Sem alertas de execução</span></p>';
                    } else {
                        echo '<div class="ff-table-scroll"><table class="widefat striped ff-table"><thead><tr><th>Severidade</th><th>Loja</th><th>Exceção</th><th>Ação</th></tr></thead><tbody>';
                        foreach ($executionExceptions as $exception) {
                            $severity = sanitize_key((string)($exception['severity'] ?? 'attention'));
                            echo '<tr><td><span class="ff-chip">' . esc_html($severity) . '</span></td>';
                            echo '<td><strong>' . esc_html((string)($exception['store_name'] ?? 'Loja')) . '</strong><br><span class="ff-note">' . esc_html(implode(' · ', array_filter([(string)($exception['project_name'] ?? ''),(string)($exception['client_name'] ?? '')]))) . '</span></td>';
                            echo '<td><strong>' . esc_html((string)($exception['title'] ?? 'Exceção operacional')) . '</strong><br><span class="ff-note">' . esc_html((string)($exception['reason'] ?? '')) . '</span></td><td>';
                            if (!empty($exception['route_id'])) echo '<a class="button button-small" href="' . esc_url(admin_url('admin.php?page=routespro-routes&route_id=' . (int)$exception['route_id'])) . '">Abrir rota</a>';
                            else echo '<span class="ff-note">Rever estado operacional</span>';
                            echo '</td></tr>';
                        }
                        echo '</tbody></table></div>';
                    }
                    echo '</div>';
                }
            } else {
                echo '<div class="routespro-card"><h3 style="margin-top:0">Seleciona um operativo</h3><p class="ff-note">A coordenação pode analisar um merchandiser de cada vez, mantendo a leitura rápida e evitando fan-out pesado sobre toda a equipa.</p></div>';
            }
        } elseif ($tab === 'clients') {
            $selected = AssignmentResolver::get_client_user_ids($selected_client_id);
            echo '<h2 class="ff-section-title">Atribuição de cliente</h2>';
            echo '<p class="ff-note"><strong>Aba Clientes.</strong> Aqui defines a base de acesso ao cliente. O que guardares aqui deve servir de universo para projetos, rotas e formulários.</p>';
            echo '<div class="routespro-card" style="margin:12px 0 18px 0"><strong>Estado guardado agora:</strong> ' . ($selected_client_id ? esc_html(count($selected) . ' utilizadores associados ao cliente selecionado.') : 'Seleciona um cliente para veres o estado guardado.') . '</div>';
            echo '<form method="post">';
            wp_nonce_field('routespro_assignment_hub_save_client_scope');
            echo '<input type="hidden" name="routespro_assignment_hub_action" value="save_client_scope">';
            echo '<input type="hidden" name="tab" value="clients">';
            echo '<div class="ff-form-grid">';
            echo '<p><label><strong>Cliente</strong><br><select name="client_id" style="width:100%" required><option value="">Seleciona</option>';
            foreach ($clients as $client) echo '<option value="' . (int)$client['id'] . '"' . selected($selected_client_id, (int)$client['id'], false) . '>' . esc_html($client['name']) . '</option>';
            echo '</select></label></p>';
            echo '<p style="grid-column:1/-1"><label><strong>Utilizadores associados</strong><br><select name="associated_user_ids[]" multiple size="12" style="width:min(100%,760px)">' . self::render_user_options($users, $selected) . '</select></label><br><span class="ff-note">Estes utilizadores passam a ver o cliente e servem de base para o resto das heranças.</span></p>';
            echo '</div>';
            submit_button('Guardar atribuição de cliente');
            echo '</form>';
        } elseif ($tab === 'projects') {
            $project = AssignmentResolver::get_project_context($selected_project_id);
            $associated = array_map('intval', (array)($project['associated_user_ids'] ?? []));
            $owners = array_map(function($row){ return (int)($row['user_id'] ?? 0); }, array_filter((array)($project['owners'] ?? []), fn($row) => !empty($row['is_active'])));
            $project_owner_role = 'owner';
            foreach ((array)($project['owners'] ?? []) as $project_owner_row) {
                if (!empty($project_owner_row['is_active']) && !empty($project_owner_row['role'])) { $project_owner_role = (string)$project_owner_row['role']; break; }
            }
            [$project_role_options, $project_owner_role] = self::resolve_selected_role($roles, $project_owner_role, 'owner');
            echo '<h2 class="ff-section-title">Atribuição de campanha / projeto</h2>';
            echo '<p class="ff-note"><strong>Aba Campanhas / Projetos.</strong> Primeiro filtras o cliente, depois a campanha. O save é sempre feito para a campanha selecionada, nunca para todas.</p>';
            echo '<div class="routespro-card" style="margin:12px 0 18px 0"><strong>Estado guardado agora:</strong> ' . ($selected_project_id ? esc_html((self::find_client_name($clients, (int)($project['client_id'] ?? $selected_client_id)) ?: 'Sem cliente') . ' · ' . (self::find_project_name($projects, $selected_project_id) ?: ('Campanha #' . $selected_project_id)) . ' · ' . count($associated) . ' utilizadores com acesso, ' . count($owners) . ' responsáveis ativos.') : 'Seleciona uma campanha para veres o estado guardado.') . '</div>';
            echo '<form method="post">';
            wp_nonce_field('routespro_assignment_hub_save_project_scope');
            echo '<input type="hidden" name="routespro_assignment_hub_action" value="save_project_scope">';
            echo '<input type="hidden" name="tab" value="projects">';
            echo '<div class="ff-form-grid" data-ff-context="project-form">';
            echo '<p><label><strong>Cliente / Marca</strong><br><select name="client_id" data-ff-role="client" style="width:100%"><option value="0">Todos</option>';
            foreach ($clients as $client) echo '<option value="' . (int)$client['id'] . '"' . selected($selected_client_id, (int)$client['id'], false) . '>' . esc_html($client['name']) . '</option>';
            echo '</select></label><br><span class="ff-note">Filtra as campanhas deste cliente.</span></p>';
            echo '<p><label><strong>Campanha / Projeto</strong><br><select name="project_id" data-ff-role="project" style="width:100%" required><option value="">Seleciona</option>';
            foreach ($projects as $item) { $clientName = self::find_client_name($clients, (int)$item['client_id']); echo '<option value="' . (int)$item['id'] . '" data-client-id="' . (int)$item['client_id'] . '"' . selected($selected_project_id, (int)$item['id'], false) . '>' . esc_html(($clientName ? $clientName . ' · ' : '') . $item['name'] . ' #' . (int)$item['id']) . '</option>'; }
            echo '</select></label><br><span class="ff-note">O save fica preso à campanha selecionada.</span></p>';
            echo '<p><label><strong>Função dos responsáveis</strong><br><select name="owner_role" style="width:100%">';
            foreach ($project_role_options as $role) echo '<option value="' . esc_attr($role) . '"' . selected($role, $project_owner_role, false) . '>' . esc_html($role) . '</option>';
            echo '</select></label></p>';
            echo '<p style="grid-column:1/-1"><label><strong>Utilizadores com acesso ao projeto</strong><br><select name="associated_user_ids[]" multiple size="10" style="width:min(100%,760px)">' . self::render_user_options($users, $associated) . '</select></label></p>';
            echo '<p style="grid-column:1/-1"><label><strong>Responsáveis ativos do projeto</strong><br><select name="owner_user_ids[]" multiple size="8" style="width:min(100%,760px)">' . self::render_user_options($users, $owners) . '</select></label><br><span class="ff-note">Estes users alimentam BO, front e sincronização operacional da campanha.</span></p>';
            echo '</div>';
            submit_button('Guardar atribuição de projeto');
            echo '</form>';
        } elseif ($tab === 'routes') {
            $route = AssignmentResolver::get_route_context($selected_route_id);
            $owner_id = (int)($route['owner_user_id'] ?? 0);
            $team_ids = array_map(function($row){ return (int)($row['user_id'] ?? 0); }, array_filter((array)($route['assignments'] ?? []), fn($row) => !empty($row['is_active']) && (($row['role'] ?? '') !== 'owner')));
            $route_meta = json_decode((string)($route['meta_json'] ?? ''), true);
            if (!is_array($route_meta)) $route_meta = [];
            $route_team_role = (string)($route_meta['default_team_role'] ?? 'operacional');
            foreach ((array)($route['assignments'] ?? []) as $route_assignment_row) {
                if (!empty($route_assignment_row['is_active']) && (($route_assignment_row['role'] ?? '') !== 'owner') && !empty($route_assignment_row['role'])) { $route_team_role = (string)$route_assignment_row['role']; break; }
            }
            [$route_role_options, $route_team_role] = self::resolve_selected_role($roles, $route_team_role, 'operacional');
            $assignable = AssignmentMatrix::get_assignable_users((int)($route['client_id'] ?? $selected_client_id), (int)($route['project_id'] ?? $selected_project_id));
            if (!$assignable) $assignable = $users;
            echo '<h2 class="ff-section-title">Atribuição de rota</h2>';
            echo '<p class="ff-note"><strong>Aba Rotas.</strong> Primeiro escolhes cliente, depois campanha, e só depois a rota. Todos os filtros são dinâmicos e o save fica preso à rota selecionada.</p>';
            echo '<div class="routespro-card" style="margin:12px 0 18px 0"><strong>Estado guardado agora:</strong> ' . ($selected_route_id ? esc_html('owner: ' . ($owner_id ? '#' . $owner_id : 'sem owner') . ', equipa adicional: ' . count($team_ids) . ' utilizadores.') : 'Seleciona cliente, campanha e rota para veres o estado guardado.') . '</div>';
            echo '<form method="post">';
            wp_nonce_field('routespro_assignment_hub_save_route_scope');
            echo '<input type="hidden" name="routespro_assignment_hub_action" value="save_route_scope">';
            echo '<input type="hidden" name="tab" value="routes">';
            echo '<div class="ff-form-grid" data-ff-context="route-form">';
            echo '<p><label><strong>Cliente / Marca</strong><br><select name="client_id" data-ff-role="client" style="width:100%"><option value="0">Todos</option>';
            foreach ($clients as $client) echo '<option value="' . (int)$client['id'] . '"' . selected((int)($route['client_id'] ?? $selected_client_id), (int)$client['id'], false) . '>' . esc_html($client['name']) . '</option>';
            echo '</select></label></p>';
            echo '<p><label><strong>Campanha / Projeto</strong><br><select name="project_id" data-ff-role="project" style="width:100%"><option value="0">Todos</option>';
            foreach ($projects as $item) { $clientName = self::find_client_name($clients, (int)$item['client_id']); echo '<option value="' . (int)$item['id'] . '" data-client-id="' . (int)$item['client_id'] . '"' . selected((int)($route['project_id'] ?? $selected_project_id), (int)$item['id'], false) . '>' . esc_html(($clientName ? $clientName . ' · ' : '') . $item['name'] . ' #' . (int)$item['id']) . '</option>'; }
            echo '</select></label></p>';
            echo '<p style="grid-column:1/-1"><label><strong>Rota</strong><br><select name="route_id" data-ff-role="route" style="width:100%" required><option value="">Seleciona</option>';
            foreach ($routes as $item) {
                $projectName = self::find_project_name($projects, (int)($item['project_id'] ?? 0));
                $clientId = (int)($item['client_id'] ?? 0);
                $projectId = (int)($item['project_id'] ?? 0);
                $label = '#' . (int)$item['id'] . ' · ' . ($projectName ?: 'Sem campanha') . ' · ' . ($item['date'] ?: 'sem data') . ' · ' . ($item['status'] ?: '');
                echo '<option value="' . (int)$item['id'] . '" data-client-id="' . $clientId . '" data-project-id="' . $projectId . '"' . selected($selected_route_id, (int)$item['id'], false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></label><br><span class="ff-note">A lista de rotas reage ao cliente e à campanha.</span></p>';
            echo '<p><label><strong>Owner operacional</strong><br><select name="owner_user_id" style="width:100%"><option value="0">Sem owner</option>' . self::render_user_options($assignable, $owner_id ? [$owner_id] : []) . '</select></label></p>';
            echo '<p><label><strong>Função da equipa</strong><br><select name="team_role" style="width:100%">';
            foreach ($route_role_options as $role) echo '<option value="' . esc_attr($role) . '"' . selected($role, $route_team_role, false) . '>' . esc_html($role) . '</option>';
            echo '</select></label></p>';
            echo '<p style="grid-column:1/-1"><label><strong>Equipa adicional da rota</strong><br><select name="team_user_ids[]" multiple size="10" style="width:min(100%,760px)">' . self::render_user_options($assignable, $team_ids) . '</select></label><br><span class="ff-note">O owner é sempre sincronizado, mesmo que não o seleciones novamente aqui.</span></p>';
            echo '</div>';
            submit_button('Guardar atribuição de rota');
            echo '</form>';
        } elseif ($tab === 'mobility') {
            $mobilityConfig = $selected_project_id > 0 ? MobilityCostService::getProjectMobilityConfig($selected_project_id) : ['default'=>MobilityCostService::emptyProfile(),'profiles'=>[]];
            $defaultProfile = (array)($mobilityConfig['default'] ?? MobilityCostService::emptyProfile());
            $operatorProfile = $selected_owner_user_id > 0 && !empty($mobilityConfig['profiles'][$selected_owner_user_id]) ? (array)$mobilityConfig['profiles'][$selected_owner_user_id] : MobilityCostService::emptyProfile();
            $endpointConfig = $selected_project_id > 0 ? RouteEndpointProfileService::getProjectConfig($selected_project_id) : ['default'=>RouteEndpointProfileService::emptyProfile(),'profiles'=>[]];
            $endpointDefaultProfile = (array)($endpointConfig['default'] ?? RouteEndpointProfileService::emptyProfile());
            $endpointOperatorProfile = $selected_owner_user_id > 0 && !empty($endpointConfig['profiles'][$selected_owner_user_id]) ? (array)$endpointConfig['profiles'][$selected_owner_user_id] : RouteEndpointProfileService::emptyProfile();
            $assignable = $selected_project_id > 0 ? AssignmentMatrix::get_assignable_users($selected_client_id, $selected_project_id) : [];
            if (!$assignable) $assignable = $users;
            $selectedRoute = $selected_route_id > 0 ? AssignmentResolver::get_route_context($selected_route_id) : [];
            $selectedRouteMeta = !empty($selectedRoute['meta_json']) ? json_decode((string)$selectedRoute['meta_json'], true) : [];
            if (!is_array($selectedRouteMeta)) $selectedRouteMeta = [];
            $routeProfile = $selectedRoute ? MobilityCostService::profileFromRouteMeta($selectedRouteMeta, (int)($selectedRoute['project_id'] ?? 0), (int)($selectedRoute['owner_user_id'] ?? 0)) : MobilityCostService::emptyProfile();
            $routeCosts = $selectedRoute ? MobilityCostService::calculateForRouteArray($selectedRoute) : [];

            echo '<h2 class="ff-section-title">Mobilidade e custos de rota</h2>';
            echo '<p class="ff-note"><strong>Aba Mobilidade.</strong> A campanha define o perfil default; cada operativo pode ter um perfil próprio; cada rota guarda um snapshot para preservar o histórico. O cálculo usa os km e portagens canónicos existentes e não altera a otimização P1-P4.</p>';

            echo '<form method="get" class="routespro-card" style="margin:12px 0 18px">';
            echo '<input type="hidden" name="page" value="routespro-assignments-hub"><input type="hidden" name="tab" value="mobility">';
            echo '<div class="ff-form-grid">';
            echo '<p><label><strong>Cliente / Marca</strong><br><select name="client_id" data-ff-role="client" style="width:100%"><option value="0">Todos</option>';
            foreach ($clients as $client) echo '<option value="' . (int)$client['id'] . '"' . selected($selected_client_id, (int)$client['id'], false) . '>' . esc_html($client['name']) . '</option>';
            echo '</select></label></p>';
            echo '<p><label><strong>Campanha / Projeto</strong><br><select name="project_id" data-ff-role="project" style="width:100%"><option value="0">Seleciona</option>';
            foreach ($projects as $item) { $clientName=self::find_client_name($clients,(int)$item['client_id']); echo '<option value="' . (int)$item['id'] . '" data-client-id="' . (int)$item['client_id'] . '"' . selected($selected_project_id,(int)$item['id'],false) . '>' . esc_html(($clientName?$clientName.' · ':'').$item['name'].' #'.(int)$item['id']) . '</option>'; }
            echo '</select></label></p>';
            echo '<p><label><strong>Operativo</strong><br><select name="owner_user_id" style="width:100%"><option value="0">Default da campanha</option>' . self::render_user_options($assignable, $selected_owner_user_id ? [$selected_owner_user_id] : []) . '</select></label></p>';
            echo '<p style="align-self:end"><button class="button button-primary">Carregar configuração</button></p>';
            echo '</div></form>';

            if ($selected_project_id > 0) {
                echo '<div class="routespro-card" style="margin-bottom:18px"><h3 style="margin-top:0">1. Default da campanha</h3><p class="ff-note">Usado pela geração de rotas e como fallback quando o operativo não tem perfil próprio.</p><form method="post">';
                wp_nonce_field('routespro_assignment_hub_save_mobility_default');
                echo '<input type="hidden" name="routespro_assignment_hub_action" value="save_mobility_default"><input type="hidden" name="tab" value="mobility"><input type="hidden" name="client_id" value="' . (int)$selected_client_id . '"><input type="hidden" name="project_id" value="' . (int)$selected_project_id . '"><input type="hidden" name="owner_user_id" value="' . (int)$selected_owner_user_id . '">';
                self::render_mobility_profile_fields($defaultProfile);
                submit_button('Guardar default de mobilidade da campanha');
                echo '</form></div>';

                if ($selected_owner_user_id > 0) {
                    $operator = get_userdata($selected_owner_user_id);
                    echo '<div class="routespro-card" style="margin-bottom:18px"><h3 style="margin-top:0">2. Perfil do operativo' . ($operator ? ' · ' . esc_html($operator->display_name) : '') . '</h3><p class="ff-note">Sobrepõe o default da campanha nas rotas deste operativo.</p><form method="post">';
                    wp_nonce_field('routespro_assignment_hub_save_mobility_profile');
                    echo '<input type="hidden" name="routespro_assignment_hub_action" value="save_mobility_profile"><input type="hidden" name="tab" value="mobility"><input type="hidden" name="client_id" value="' . (int)$selected_client_id . '"><input type="hidden" name="project_id" value="' . (int)$selected_project_id . '"><input type="hidden" name="owner_user_id" value="' . (int)$selected_owner_user_id . '">';
                    self::render_mobility_profile_fields($operatorProfile);
                    submit_button('Guardar perfil do operativo');
                    echo '</form></div>';
                }

                echo '<div class="routespro-card" style="margin-bottom:18px"><h3 style="margin-top:0">Base da rota · default da campanha</h3><p class="ff-note">Fallback habitual para quando Campanhas PDV, a própria rota ou o Planear não definem uma partida/chegada explícita. Se esses ecrãs tiverem moradas preenchidas, são essas que prevalecem.</p><form method="post">';
                wp_nonce_field('routespro_assignment_hub_save_route_endpoint_default');
                echo '<input type="hidden" name="routespro_assignment_hub_action" value="save_route_endpoint_default"><input type="hidden" name="tab" value="mobility"><input type="hidden" name="client_id" value="' . (int)$selected_client_id . '"><input type="hidden" name="project_id" value="' . (int)$selected_project_id . '"><input type="hidden" name="owner_user_id" value="' . (int)$selected_owner_user_id . '">';
                self::render_endpoint_profile_fields($endpointDefaultProfile);
                submit_button('Guardar base de rota da campanha');
                echo '</form></div>';

                if ($selected_owner_user_id > 0) {
                    $operator = get_userdata($selected_owner_user_id);
                    echo '<div class="routespro-card" style="margin-bottom:18px"><h3 style="margin-top:0">Base da rota do operativo' . ($operator ? ' · ' . esc_html($operator->display_name) : '') . '</h3><p class="ff-note">Fallback habitual deste operativo. Só é usado quando Campanhas PDV, a rota ou o Planear não fornecerem endpoints explícitos. Se estiver desativado, pode ser usado o fallback da campanha.</p><form method="post">';
                    wp_nonce_field('routespro_assignment_hub_save_route_endpoint_profile');
                    echo '<input type="hidden" name="routespro_assignment_hub_action" value="save_route_endpoint_profile"><input type="hidden" name="tab" value="mobility"><input type="hidden" name="client_id" value="' . (int)$selected_client_id . '"><input type="hidden" name="project_id" value="' . (int)$selected_project_id . '"><input type="hidden" name="owner_user_id" value="' . (int)$selected_owner_user_id . '">';
                    self::render_endpoint_profile_fields($endpointOperatorProfile);
                    submit_button('Guardar base de rota do operativo');
                    echo '</form></div>';
                }

                self::render_endpoint_profile_autocomplete_script();

                echo '<div class="routespro-card" style="margin-bottom:18px"><h3 style="margin-top:0">Migração dos defaults anteriores</h3><p class="ff-note">Copia os antigos <code>routespro_route_defaults</code> para a fonte canónica do projeto, sem apagar o legado. Só preenche perfis ainda vazios.</p>';
                if (isset($_GET['endpoint_migrated_default'])) echo '<div class="notice notice-info inline"><p>Default campanha: <strong>' . (int)$_GET['endpoint_migrated_default'] . '</strong> · perfis operativo: <strong>' . (int)($_GET['endpoint_migrated_profiles'] ?? 0) . '</strong> · ignorados: <strong>' . (int)($_GET['endpoint_migration_skipped'] ?? 0) . '</strong> · erros: <strong>' . (int)($_GET['endpoint_migration_errors'] ?? 0) . '</strong>.</p></div>';
                echo '<form method="post">';
                wp_nonce_field('routespro_assignment_hub_migrate_route_endpoint_defaults');
                echo '<input type="hidden" name="routespro_assignment_hub_action" value="migrate_route_endpoint_defaults"><input type="hidden" name="tab" value="mobility"><input type="hidden" name="client_id" value="' . (int)$selected_client_id . '"><input type="hidden" name="project_id" value="' . (int)$selected_project_id . '"><input type="hidden" name="owner_user_id" value="' . (int)$selected_owner_user_id . '">';
                submit_button('Migrar defaults anteriores', 'secondary');
                echo '</form></div>';

                $monthStart = gmdate('Y-m-01');
                $monthEnd = gmdate('Y-m-t');
                echo '<div class="routespro-card" style="margin-bottom:18px"><h3 style="margin-top:0">3. Aplicar a rotas já existentes</h3><p class="ff-note">Grava um snapshot nas rotas do intervalo escolhido. Não altera PDVs, ordem, quilómetros, portagens ou estado da rota.</p>';
                if (isset($_GET['mobility_processed'])) echo '<div class="notice notice-info inline"><p>Processadas: <strong>' . (int)$_GET['mobility_processed'] . '</strong> · atualizadas: <strong>' . (int)($_GET['mobility_updated'] ?? 0) . '</strong> · ignoradas: <strong>' . (int)($_GET['mobility_skipped'] ?? 0) . '</strong> · erros: <strong>' . (int)($_GET['mobility_errors'] ?? 0) . '</strong>.</p></div>';
                echo '<form method="post">';
                wp_nonce_field('routespro_assignment_hub_backfill_mobility_routes');
                echo '<input type="hidden" name="routespro_assignment_hub_action" value="backfill_mobility_routes"><input type="hidden" name="tab" value="mobility"><input type="hidden" name="client_id" value="' . (int)$selected_client_id . '"><input type="hidden" name="project_id" value="' . (int)$selected_project_id . '"><input type="hidden" name="owner_user_id" value="' . (int)$selected_owner_user_id . '">';
                echo '<div class="ff-form-grid"><p><label><strong>De</strong><br><input type="date" name="date_from" value="' . esc_attr($monthStart) . '" style="width:100%"></label></p><p><label><strong>Até</strong><br><input type="date" name="date_to" value="' . esc_attr($monthEnd) . '" style="width:100%"></label></p><p><label style="display:flex;gap:8px;align-items:center;margin-top:28px"><input type="checkbox" name="include_completed" value="1"> Incluir rotas concluídas</label></p><p><label style="display:flex;gap:8px;align-items:center;margin-top:28px"><input type="checkbox" name="force_existing_snapshots" value="1"> Substituir snapshots já existentes</label></p></div>';
                submit_button('Aplicar perfil às rotas existentes', 'secondary');
                echo '</form></div>';

                echo '<div class="routespro-card" style="margin-bottom:18px"><h3 style="margin-top:0">4. Revalidar geografia da campanha</h3><p class="ff-note">Revalida partida, chegada e coordenadas dos PDVs no país configurado da campanha, invalida ambiguidades geográficas e recalcula a rota completa com a mesma geometria usada por Analytics e emails. É a ação indicada para rotas antigas criadas antes do contrato geográfico 9AN, incluindo casos como Montijo Portugal versus Montijo Espanha.</p>';
                if (isset($_GET['geo_processed'])) echo '<div class="notice notice-info inline"><p>Processadas: <strong>' . (int)$_GET['geo_processed'] . '</strong> · reconciliadas: <strong>' . (int)($_GET['geo_reconciled'] ?? 0) . '</strong> · PDVs atualizados: <strong>' . (int)($_GET['geo_pdvs_updated'] ?? 0) . '</strong> · ignoradas: <strong>' . (int)($_GET['geo_skipped'] ?? 0) . '</strong> · falhas: <strong>' . (int)($_GET['geo_failed'] ?? 0) . '</strong>.</p></div>';
                echo '<form method="post">';
                wp_nonce_field('routespro_assignment_hub_reconcile_routing_geography');
                echo '<input type="hidden" name="routespro_assignment_hub_action" value="reconcile_routing_geography"><input type="hidden" name="tab" value="mobility"><input type="hidden" name="client_id" value="' . (int)$selected_client_id . '"><input type="hidden" name="project_id" value="' . (int)$selected_project_id . '"><input type="hidden" name="owner_user_id" value="' . (int)$selected_owner_user_id . '">';
                echo '<div class="ff-form-grid"><p><label><strong>De</strong><br><input type="date" name="date_from" value="' . esc_attr($monthStart) . '" style="width:100%"></label></p><p><label><strong>Até</strong><br><input type="date" name="date_to" value="' . esc_attr($monthEnd) . '" style="width:100%"></label></p><p><label style="display:flex;gap:8px;align-items:center;margin-top:28px"><input type="checkbox" name="include_completed" value="1" checked> Incluir rotas concluídas</label></p></div>';
                submit_button('Revalidar país, endpoints, PDVs e km', 'secondary');
                echo '</form></div>';

                echo '<div class="routespro-card" style="margin-bottom:18px"><h3 style="margin-top:0">5. Reconciliar partida, chegada e km de rotas por planear</h3><p class="ff-note">Corrige extremos em falta e também extremos legacy suspeitos que coincidem silenciosamente com o primeiro ou último PDV, usando apenas defaults reais já guardados para campanha e operativo. Nunca inventa um extremo, preserva a ordem dos PDVs e não toca em rotas iniciadas, concluídas ou canceladas. Depois recalcula as métricas canónicas e, quando Google Routes está disponível, finaliza a distância pela sequência real completa, partida, PDVs pela ordem gravada e chegada. A matriz híbrida fica apenas como fallback.</p>';
                if (isset($_GET['distance_processed'])) echo '<div class="notice notice-info inline"><p>Processadas: <strong>' . (int)$_GET['distance_processed'] . '</strong> · reparadas: <strong>' . (int)($_GET['distance_repaired'] ?? 0) . '</strong> · endpoints suspeitos encontrados: <strong>' . (int)($_GET['distance_suspicious'] ?? 0) . '</strong> · reconciliados: <strong>' . (int)($_GET['distance_reconciled'] ?? 0) . '</strong> · base do operativo aplicada: <strong>' . (int)($_GET['distance_forced_profile'] ?? 0) . '</strong> · km recalculados: <strong>' . (int)($_GET['distance_recalculated'] ?? 0) . '</strong> · Google exato: <strong>' . (int)($_GET['distance_google_exact'] ?? 0) . '</strong> · fallback matriz: <strong>' . (int)($_GET['distance_matrix_fallback'] ?? 0) . '</strong> · falhas Google exato: <strong>' . (int)($_GET['distance_google_exact_failed'] ?? 0) . '</strong> · suspeitos por resolver: <strong>' . (int)($_GET['distance_suspicious_unresolved'] ?? 0) . '</strong> · sem alteração: <strong>' . (int)($_GET['distance_unchanged'] ?? 0) . '</strong> · sem defaults: <strong>' . (int)($_GET['distance_missing_defaults'] ?? 0) . '</strong> · protegidas por já terem iniciado: <strong>' . (int)($_GET['distance_started'] ?? 0) . '</strong> · erros: <strong>' . (int)($_GET['distance_errors'] ?? 0) . '</strong>.</p></div>';
                echo '<form method="post">';
                wp_nonce_field('routespro_assignment_hub_repair_route_distance_integrity');
                echo '<input type="hidden" name="routespro_assignment_hub_action" value="repair_route_distance_integrity"><input type="hidden" name="tab" value="mobility"><input type="hidden" name="client_id" value="' . (int)$selected_client_id . '"><input type="hidden" name="project_id" value="' . (int)$selected_project_id . '"><input type="hidden" name="owner_user_id" value="' . (int)$selected_owner_user_id . '">';
                echo '<div class="ff-form-grid"><p><label><strong>De</strong><br><input type="date" name="date_from" value="' . esc_attr($monthStart) . '" style="width:100%"></label></p><p><label><strong>Até</strong><br><input type="date" name="date_to" value="' . esc_attr($monthEnd) . '" style="width:100%"></label></p></div>';
                if ($selected_owner_user_id > 0) {
                    echo '<p style="margin:8px 0 14px"><label style="display:flex;gap:8px;align-items:flex-start"><input type="checkbox" name="force_owner_profile" value="1" checked> <span><strong>Forçar a Base habitual deste operativo nas rotas existentes</strong><br><span class="description">Usa a partida e chegada guardadas acima como fonte de verdade para todas as rotas draft/planned deste operativo no intervalo. Corrige em massa rotas antigas que ficaram com o primeiro e último PDV como extremos. Rotas já iniciadas, concluídas ou canceladas continuam protegidas.</span></span></label></p>';
                } else {
                    echo '<p class="ff-note"><strong>Para migração forçada:</strong> seleciona primeiro um operativo. A reconciliação geral continua disponível, mas não substitui endpoints válidos em massa sem um owner explícito.</p>';
                }
                submit_button($selected_owner_user_id > 0 ? 'Aplicar base do operativo e reconciliar km' : 'Reconciliar endpoints e km', 'secondary');
                echo '</form></div>';

                echo '<div class="routespro-card" style="margin-bottom:18px"><h3 style="margin-top:0">6. Override excecional por rota</h3><p class="ff-note">Usa apenas quando uma rota específica foi feita com outra viatura ou outro preço.</p><form method="get" style="margin-bottom:14px"><input type="hidden" name="page" value="routespro-assignments-hub"><input type="hidden" name="tab" value="mobility"><input type="hidden" name="client_id" value="' . (int)$selected_client_id . '"><input type="hidden" name="project_id" value="' . (int)$selected_project_id . '"><input type="hidden" name="owner_user_id" value="' . (int)$selected_owner_user_id . '"><label><strong>Rota</strong><br><select name="route_id" style="width:min(100%,760px)"><option value="0">Seleciona uma rota</option>';
                foreach ($routes as $item) { if ((int)($item['project_id'] ?? 0)!==$selected_project_id) continue; if ($selected_owner_user_id>0 && (int)($item['owner_user_id'] ?? 0)!==$selected_owner_user_id) continue; echo '<option value="' . (int)$item['id'] . '"' . selected($selected_route_id,(int)$item['id'],false) . '>#' . (int)$item['id'] . ' · ' . esc_html((string)($item['date'] ?? '')) . ' · ' . esc_html((string)($item['status'] ?? '')) . '</option>'; }
                echo '</select></label> <button class="button">Carregar rota</button></form>';
                if ($selectedRoute) {
                    echo '<div class="ff-kpi-grid" style="margin-bottom:14px">';
                    $costsAvailable = !empty($routeCosts['costs_available']);
                    $costCards = [
                        ['Perfil', (string)($routeCosts['profile_label'] ?? MobilityCostService::profileLabel($routeProfile))],
                        ['Energia', $costsAvailable ? number_format_i18n((float)($routeCosts['energy_cost_eur'] ?? 0),2) . ' €' : 'Por calcular'],
                        ['Portagens', $costsAvailable ? number_format_i18n((float)($routeCosts['toll_cost_eur'] ?? 0),2) . ' €' : 'Por calcular'],
                        ['Custo total', $costsAvailable ? number_format_i18n((float)($routeCosts['total_mobility_cost_eur'] ?? 0),2) . ' €' : 'Por calcular'],
                    ];
                    foreach ($costCards as $card) echo '<div class="ff-kpi"><span>' . esc_html($card[0]) . '</span><strong style="font-size:18px">' . esc_html($card[1]) . '</strong></div>';
                    echo '</div><form method="post">';
                    wp_nonce_field('routespro_assignment_hub_save_route_mobility_override');
                    echo '<input type="hidden" name="routespro_assignment_hub_action" value="save_route_mobility_override"><input type="hidden" name="tab" value="mobility"><input type="hidden" name="client_id" value="' . (int)$selected_client_id . '"><input type="hidden" name="project_id" value="' . (int)$selected_project_id . '"><input type="hidden" name="owner_user_id" value="' . (int)$selected_owner_user_id . '"><input type="hidden" name="route_id" value="' . (int)$selected_route_id . '">';
                    self::render_mobility_profile_fields($routeProfile);
                    submit_button('Guardar override e snapshot da rota', 'secondary');
                    echo '</form>';
                }
                echo '</div>';
                self::render_mobility_script();
            } else {
                echo '<div class="routespro-card"><strong>Seleciona uma campanha.</strong><p class="ff-note">Depois podes configurar o default, escolher o operativo e aplicar o perfil às rotas já criadas.</p></div>';
            }
        } elseif ($tab === 'forms') {
            $bindings = AssignmentResolver::get_form_bindings(['client_id' => $selected_client_id, 'project_id' => $selected_project_id, 'route_id' => $selected_route_id]);
            echo '<h2 class="ff-section-title">Formulários por contexto</h2>';
            echo '<p class="ff-note"><strong>Aba Formulários.</strong> Primeiro filtras cliente e campanha, depois a rota. O save fica ligado exatamente ao contexto que selecionares.</p>';
            echo '<div class="routespro-card" style="margin:12px 0 18px 0"><strong>Estado guardado agora:</strong> ' . esc_html(count($bindings) . ' ligações encontradas para o contexto atual.') . '</div>';
            echo '<form method="post">';
            wp_nonce_field('routespro_assignment_hub_save_form_binding');
            echo '<input type="hidden" name="routespro_assignment_hub_action" value="save_form_binding">';
            echo '<input type="hidden" name="tab" value="forms">';
            echo '<div class="ff-form-grid" data-ff-context="binding-form">';
            echo '<p><label><strong>Formulário</strong><br><select name="form_id" style="width:100%" required><option value="">Seleciona</option>';
            foreach ($forms as $form) echo '<option value="' . (int)$form['id'] . '">' . esc_html(($form['title'] ?: 'Sem título') . ' #' . (int)$form['id']) . '</option>';
            echo '</select></label></p>';
            echo '<p><label><strong>Modo</strong><br><select name="mode" style="width:100%"><option value="route_and_form">Rota e formulário</option><option value="form_only">Só formulário</option><option value="route_only">Só rota</option></select></label></p>';
            echo '<p><label><strong>Prioridade</strong><br><input type="number" name="priority" value="10" min="0" max="999" style="width:120px"></label></p>';
            echo '<p><label><strong>Cliente / Marca</strong><br><select name="client_id" data-ff-role="client" style="width:100%"><option value="0">Nenhum</option>';
            foreach ($clients as $client) echo '<option value="' . (int)$client['id'] . '"' . selected($selected_client_id, (int)$client['id'], false) . '>' . esc_html($client['name']) . '</option>';
            echo '</select></label></p>';
            echo '<p><label><strong>Campanha / Projeto</strong><br><select name="project_id" data-ff-role="project" style="width:100%"><option value="0">Nenhum</option>';
            foreach ($projects as $project) echo '<option value="' . (int)$project['id'] . '" data-client-id="' . (int)$project['client_id'] . '"' . selected($selected_project_id, (int)$project['id'], false) . '>' . esc_html($project['name'] . ' #' . (int)$project['id']) . '</option>';
            echo '</select></label></p>';
            echo '<p><label><strong>Rota</strong><br><select name="route_id" data-ff-role="route" style="width:100%"><option value="0">Nenhuma</option>';
            foreach ($routes as $route) {
                $label = '#' . (int)$route['id'] . ' · ' . ($route['date'] ?: 'sem data');
                echo '<option value="' . (int)$route['id'] . '" data-client-id="' . (int)($route['client_id'] ?? 0) . '" data-project-id="' . (int)($route['project_id'] ?? 0) . '"' . selected($selected_route_id, (int)$route['id'], false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></label></p>';
            echo '<p><label><strong>Paragem</strong><br><select name="stop_id" style="width:100%"><option value="0">Nenhuma</option>';
            foreach ($stops as $stop) {
                $label = '#' . (int)$stop['id'] . ' · Rota #' . (int)$stop['route_id'] . ' · ' . ($stop['location_name'] ?: 'PDV');
                echo '<option value="' . (int)$stop['id'] . '">' . esc_html($label) . '</option>';
            }
            echo '</select></label></p>';
            echo '<p><label><strong>Local</strong><br><select name="location_id" style="width:100%"><option value="0">Nenhum</option>';
            foreach ($locations as $location) echo '<option value="' . (int)$location['id'] . '">' . esc_html($location['name'] . ' #' . (int)$location['id']) . '</option>';
            echo '</select></label></p>';
            echo '<p><label><input type="checkbox" name="is_active" value="1" checked> Ligação ativa</label></p>';
            echo '</div>';
            submit_button('Guardar ligação de formulário');
            echo '</form>';

            echo '<h3 style="margin-top:28px">Ligações existentes</h3>';
            echo '<table class="widefat striped ff-table"><thead><tr><th>ID</th><th>Formulário</th><th>Âmbito</th><th>Modo</th><th>Prioridade</th><th>Ação</th></tr></thead><tbody>';
            if (!$bindings) {
                echo '<tr><td colspan="6">Sem ligações para este contexto.</td></tr>';
            } else {
                foreach ($bindings as $row) {
                    $scope = [];
                    if (!empty($row['client_id'])) $scope[] = 'Cliente ' . ($row['client_name'] ?: '#' . (int)$row['client_id']);
                    if (!empty($row['project_id'])) $scope[] = 'Projeto ' . ($row['project_name'] ?: '#' . (int)$row['project_id']);
                    if (!empty($row['route_id'])) $scope[] = 'Rota #' . (int)$row['route_id'] . ' ' . ($row['route_date'] ?: '');
                    if (!empty($row['location_id'])) $scope[] = 'Local ' . ($row['location_name'] ?: '#' . (int)$row['location_id']);
                    if (!empty($row['stop_id'])) $scope[] = 'Paragem #' . (int)$row['stop_id'];
                    echo '<tr><td>' . (int)$row['id'] . '</td><td>' . esc_html($row['form_title'] ?: ('#' . (int)$row['form_id'])) . '</td><td>' . esc_html(implode(' | ', $scope)) . '</td><td>' . esc_html($row['mode']) . '</td><td>' . (int)$row['priority'] . '</td><td><form method="post" style="margin:0">';
                    wp_nonce_field('routespro_assignment_hub_delete_form_binding');
                    echo '<input type="hidden" name="routespro_assignment_hub_action" value="delete_form_binding"><input type="hidden" name="binding_id" value="' . (int)$row['id'] . '"><button class="button button-small" onclick="return confirm(\'Remover ligação?\')">Apagar</button></form></td></tr>';
                }
            }
            echo '</tbody></table>';
        } elseif ($tab === 'learning') {
            self::render_learning_workspace($clients, $projects, $routes, $users, $locations, $selected_client_id, $selected_project_id, $selected_route_id);
        } elseif ($tab === 'analytics') {
            $analytics_questions = [];
            $analytics_question_map = [];
            foreach ($forms as $form) {
                $form_id = (int)($form['id'] ?? 0);
                $schema_questions = self::extract_schema_fields((string)($form['schema_json'] ?? ''));
                $analytics_question_map[$form_id] = $schema_questions;
                foreach ($schema_questions as $question) {
                    $analytics_questions[] = [
                        'form_id' => $form_id,
                        'question_key' => $question['question_key'],
                        'question_label' => $question['question_label'],
                        'question_type' => $question['question_type'],
                    ];
                }
            }
            $context_table = ContextQuestionService::table();
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $context_table)) === $context_table) {
                $ctx_where = ['is_active=1'];
                $ctx_args = [];
                if ($selected_client_id) { $ctx_where[] = '(client_id=0 OR client_id=%d)'; $ctx_args[] = $selected_client_id; }
                if ($selected_project_id) { $ctx_where[] = '(project_id=0 OR project_id=%d)'; $ctx_args[] = $selected_project_id; }
                $ctx_sql = 'SELECT id, form_id, question_key, question_label, question_type, context_type, source_type, briefing_id, client_id, project_id, location_id, analytics_enabled, analytics_dashboard_id, analytics_metric_label, analytics_chart_type, analytics_aggregation, analytics_dimension, analytics_secondary_dimension, analytics_sort_order, analytics_show_kpi, analytics_show_table, analytics_show_empty FROM ' . $context_table . ' WHERE ' . implode(' AND ', $ctx_where) . ' ORDER BY priority ASC, id ASC LIMIT 1000';
                $ctx_questions = $ctx_args ? ($wpdb->get_results($wpdb->prepare($ctx_sql, ...$ctx_args), ARRAY_A) ?: []) : ($wpdb->get_results($ctx_sql, ARRAY_A) ?: []);
                foreach ($ctx_questions as $cq) {
                    $target_form_ids = [];
                    $ctx_form_id = (int)($cq['form_id'] ?? 0);
                    if ($ctx_form_id > 0) {
                        $target_form_ids[] = $ctx_form_id;
                    } else {
                        foreach ($forms as $form_for_ctx) $target_form_ids[] = (int)($form_for_ctx['id'] ?? 0);
                    }
                    foreach (array_unique(array_filter($target_form_ids)) as $target_form_id) {
                        $key = sanitize_key((string)($cq['question_key'] ?? ''));
                        if ($key === '') continue;
                        $exists = false;
                        foreach (($analytics_question_map[$target_form_id] ?? []) as $existing_question) {
                            if (($existing_question['question_key'] ?? '') === $key) { $exists = true; break; }
                        }
                        if ($exists) continue;
                        $label = trim((string)($cq['question_label'] ?? $key));
                        $isBriefingQuestion = sanitize_key((string)($cq['source_type'] ?? '')) === 'briefing' || !empty($cq['briefing_id']);
                        $analytics_question_map[$target_form_id][] = [
                            'question_key' => $key,
                            'question_label' => $label,
                            'question_type' => sanitize_key((string)($cq['question_type'] ?? 'text')),
                            'source' => $isBriefingQuestion ? 'briefing_question' : 'context_question',
                            'analytics_enabled' => !empty($cq['analytics_enabled']) ? 1 : 0,
                            'preferred_dashboard_id' => (int)($cq['analytics_dashboard_id'] ?? 0),
                            'preferred_metric_label' => (string)($cq['analytics_metric_label'] ?? $label),
                            'preferred_chart' => sanitize_key((string)($cq['analytics_chart_type'] ?? '')),
                            'preferred_aggregation' => sanitize_key((string)($cq['analytics_aggregation'] ?? '')),
                            'preferred_dimension' => sanitize_key((string)($cq['analytics_dimension'] ?? '')),
                            'preferred_secondary_dimension' => sanitize_key((string)($cq['analytics_secondary_dimension'] ?? '')),
                            'preferred_sort_order' => (int)($cq['analytics_sort_order'] ?? 10),
                            'preferred_show_kpi' => !empty($cq['analytics_show_kpi']) ? 1 : 0,
                            'preferred_show_table' => !empty($cq['analytics_show_table']) ? 1 : 0,
                            'preferred_show_empty' => !empty($cq['analytics_show_empty']) ? 1 : 0,
                            'briefing_id' => (int)($cq['briefing_id'] ?? 0),
                        ];
                        $analytics_questions[] = [
                            'form_id' => $target_form_id,
                            'question_key' => $key,
                            'question_label' => $label,
                            'question_type' => sanitize_key((string)($cq['question_type'] ?? 'text')),
                        ];
                    }
                }
            }
            // Inclui perguntas realmente gravadas, mesmo quando o schema foi alterado.
            $records_table = $px . 'form_records';
            $versions_table = $px . 'form_record_versions';
            $values_table = $px . 'form_record_values';
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $values_table)) === $values_table) {
                $stored_questions = $wpdb->get_results("SELECT r.form_id,rv.question_key,MAX(rv.question_label) AS question_label,MAX(rv.value_type) AS question_type,MAX(CASE WHEN rv.value_json LIKE '%\"order_qty\"%' OR rv.value_json LIKE '%\"boxes\"%' OR rv.value_json LIKE '%\"units_total\"%' THEN 1 ELSE 0 END) AS looks_order,MAX(CASE WHEN rv.value_json LIKE '%\"before\"%' OR rv.value_json LIKE '%\"after\"%' THEN 1 ELSE 0 END) AS looks_matrix,MAX(CASE WHEN rv.value_text LIKE 'http%' OR rv.value_json LIKE '%http%' THEN 1 ELSE 0 END) AS looks_upload FROM {$values_table} rv INNER JOIN {$versions_table} v ON v.id=rv.version_id INNER JOIN {$records_table} r ON r.id=v.record_id GROUP BY r.form_id,rv.question_key ORDER BY r.form_id ASC,question_label ASC LIMIT 5000", ARRAY_A) ?: [];
                foreach ($stored_questions as $stored_question) {
                    $form_id = (int)($stored_question['form_id'] ?? 0);
                    $key = sanitize_key((string)($stored_question['question_key'] ?? ''));
                    if ($form_id <= 0 || $key === '') continue;
                    $exists = false;
                    foreach (($analytics_question_map[$form_id] ?? []) as $candidate) if (($candidate['question_key'] ?? '') === $key) { $exists = true; break; }
                    if ($exists) continue;
                    $type = sanitize_key((string)($stored_question['question_type'] ?? 'text')) ?: 'text';
                    if (!empty($stored_question['looks_order'])) $type = 'order_suggestion';
                    elseif (!empty($stored_question['looks_matrix'])) $type = 'product_matrix';
                    elseif (!empty($stored_question['looks_upload']) && in_array($type, ['text', 'json', ''], true)) $type = 'image_upload';
                    $label = trim((string)($stored_question['question_label'] ?? $key));
                    $analytics_question_map[$form_id][] = ['question_key'=>$key,'question_label'=>$label . ' · Histórico','question_type'=>$type,'source'=>'stored'];
                    $analytics_questions[] = ['form_id'=>$form_id,'question_key'=>$key,'question_label'=>$label,'question_type'=>$type];
                }
            }
            $dash_table = self::analytics_dashboard_table();
            $group_table = self::analytics_store_group_table();
            $item_table = self::analytics_store_group_items_table();
            $all_analytics_dashboards = $wpdb->get_results("SELECT * FROM {$dash_table} ORDER BY sort_order ASC, id ASC", ARRAY_A) ?: [];
            $all_analytics_groups = $wpdb->get_results("SELECT g.*, COUNT(i.location_id) AS locations_count FROM {$group_table} g LEFT JOIN {$item_table} i ON i.group_id=g.id GROUP BY g.id ORDER BY g.name ASC", ARRAY_A) ?: [];

            // 2.2.338: the Analytics census and lists now honour the selected
            // business context. Previously the left-hand Client/Project/Route
            // selector filtered available questions, but the widget counter and
            // list still showed every active binding in the database. That made
            // a correctly consolidated dashboard look as if widgets had returned.
            $analytics_dashboards = array_values(array_filter($all_analytics_dashboards, static function(array $dash) use ($selected_client_id, $selected_project_id, $selected_route_id): bool {
                $clientId = absint($dash['client_id'] ?? 0);
                $projectId = absint($dash['project_id'] ?? 0);
                $routeId = absint($dash['route_id'] ?? 0);
                if ($selected_client_id && $clientId && $clientId !== $selected_client_id) return false;
                if ($selected_project_id && $projectId && $projectId !== $selected_project_id) return false;
                if ($selected_route_id && $routeId && $routeId !== $selected_route_id) return false;
                return true;
            }));
            $analytics_groups = array_values(array_filter($all_analytics_groups, static function(array $group) use ($selected_client_id, $selected_project_id): bool {
                $clientId = absint($group['client_id'] ?? 0);
                $projectId = absint($group['project_id'] ?? 0);
                if ($selected_client_id && $clientId && $clientId !== $selected_client_id) return false;
                if ($selected_project_id && $projectId && $projectId !== $selected_project_id) return false;
                return true;
            }));
            $analytics_locations = $wpdb->get_results("SELECT id, client_id, project_id, name, city, district FROM {$px}locations WHERE is_active=1 ORDER BY name ASC LIMIT 2000", ARRAY_A) ?: [];
            $show_inactive_analytics = !empty($_GET['show_inactive_analytics']);
            $requested_edit_binding_id = absint($_GET['edit_analytics_binding'] ?? 0);
            $binding_where = $show_inactive_analytics ? '1=1' : ('(b.is_active=1' . ($requested_edit_binding_id ? ' OR b.id=' . $requested_edit_binding_id : '') . ')');
            $bindings_sql = 'SELECT b.*, f.title AS form_title FROM ' . self::analytics_table() . ' b LEFT JOIN ' . FormsModule::table() . ' f ON f.id=b.form_id WHERE ' . $binding_where;
            $all_analytics_bindings = $wpdb->get_results($bindings_sql, ARRAY_A) ?: [];
            $dashboard_scope_lookup = [];
            foreach ($all_analytics_dashboards as $dash) $dashboard_scope_lookup[(int)($dash['id'] ?? 0)] = $dash;
            $analytics_bindings = [];
            foreach ($all_analytics_bindings as $binding_row) {
                $settings = json_decode((string)($binding_row['settings_json'] ?? ''), true); if (!is_array($settings)) $settings = [];
                $dashboardId = absint($settings['dashboard_id'] ?? 0);
                $dashScope = $dashboard_scope_lookup[$dashboardId] ?? [];
                $clientId = absint($settings['client_id'] ?? 0) ?: absint($dashScope['client_id'] ?? 0);
                $projectId = absint($settings['project_id'] ?? 0) ?: absint($dashScope['project_id'] ?? 0);
                $routeId = absint($settings['route_id'] ?? 0) ?: absint($dashScope['route_id'] ?? 0);
                $isRequestedEdit = $requested_edit_binding_id && (int)($binding_row['id'] ?? 0) === $requested_edit_binding_id;
                if (!$isRequestedEdit) {
                    if ($selected_client_id && $clientId && $clientId !== $selected_client_id) continue;
                    if ($selected_project_id && $projectId && $projectId !== $selected_project_id) continue;
                    if ($selected_route_id && $routeId && $routeId !== $selected_route_id) continue;
                }
                $analytics_bindings[] = $binding_row;
            }
            $analytics_active_count = count(array_filter($analytics_bindings, static fn($row): bool => !empty($row['is_active'])));
            $analytics_dashboard_order = [];
            foreach ($analytics_dashboards as $dashboard_row) {
                $analytics_dashboard_order[(int)($dashboard_row['id'] ?? 0)] = (int)($dashboard_row['sort_order'] ?? 10);
            }
            usort($analytics_bindings, static function(array $a, array $b) use ($analytics_dashboard_order): int {
                $as = json_decode((string)($a['settings_json'] ?? ''), true); if (!is_array($as)) $as = [];
                $bs = json_decode((string)($b['settings_json'] ?? ''), true); if (!is_array($bs)) $bs = [];
                $ad = (int)($as['dashboard_id'] ?? 0); $bd = (int)($bs['dashboard_id'] ?? 0);
                $ado = (int)($analytics_dashboard_order[$ad] ?? 999);
                $bdo = (int)($analytics_dashboard_order[$bd] ?? 999);
                if ($ado !== $bdo) return $ado <=> $bdo;
                if ($ad !== $bd) return $ad <=> $bd;
                $ao = (int)($as['sort_order'] ?? 10); $bo = (int)($bs['sort_order'] ?? 10);
                return $ao === $bo ? ((int)$a['id'] <=> (int)$b['id']) : ($ao <=> $bo);
            });

            $analytics_question_lookup = [];
            foreach ($analytics_question_map as $form_id => $question_rows) {
                foreach ((array)$question_rows as $question_row) {
                    $question_key = sanitize_key((string)($question_row['question_key'] ?? ''));
                    if ($question_key === '') continue;
                    $analytics_question_lookup[(int)$form_id . ':' . $question_key] = $question_row;
                }
            }
            $dashboard_ids = array_fill_keys(array_map(static fn($row) => (int)($row['id'] ?? 0), $analytics_dashboards), true);
            $group_ids = array_fill_keys(array_map(static fn($row) => (int)($row['id'] ?? 0), $analytics_groups), true);
            $analytics_evidence_widgets = 0;
            $analytics_fronts_widgets = 0;
            $analytics_integrity_issues = 0;
            foreach ($analytics_bindings as $binding_row) {
                if (empty($binding_row['is_active'])) continue;
                $binding_settings = json_decode((string)($binding_row['settings_json'] ?? ''), true); if (!is_array($binding_settings)) $binding_settings = [];
                $binding_lookup_key = (int)($binding_row['form_id'] ?? 0) . ':' . sanitize_key((string)($binding_row['question_key'] ?? ''));
                $binding_question = $analytics_question_lookup[$binding_lookup_key] ?? [];
                $question_type = sanitize_key((string)($binding_settings['question_type'] ?? $binding_question['question_type'] ?? ''));
                if ((string)($binding_row['chart_type'] ?? '') === 'image_gallery' || in_array($question_type, ['image_upload','file_upload','categorized_image_upload'], true)) $analytics_evidence_widgets++;
                if ($question_type === 'product_matrix') $analytics_fronts_widgets++;
                $dashboard_id = (int)($binding_settings['dashboard_id'] ?? 0);
                $group_id = (int)($binding_settings['store_group_id'] ?? 0);
                if (($dashboard_id > 0 && empty($dashboard_ids[$dashboard_id])) || ($group_id > 0 && empty($group_ids[$group_id]))) $analytics_integrity_issues++;
            }
            $analytics_edit_dashboard_id = absint($_GET['edit_analytics_dashboard'] ?? 0);
            $analytics_edit_dashboard = [];
            foreach ($analytics_dashboards as $dashboard_row) {
                if ((int)($dashboard_row['id'] ?? 0) === $analytics_edit_dashboard_id) { $analytics_edit_dashboard = $dashboard_row; break; }
            }
            $analytics_new_dashboard_id = absint($_GET['new_dashboard_id'] ?? 0);
            if ($analytics_new_dashboard_id > 0 && empty($dashboard_ids[$analytics_new_dashboard_id])) $analytics_new_dashboard_id = 0;
            $analytics_edit_dashboard_widgets = [];
            if ($analytics_edit_dashboard) {
                foreach ($analytics_bindings as $binding_row) {
                    $binding_settings = json_decode((string)($binding_row['settings_json'] ?? ''), true); if (!is_array($binding_settings)) $binding_settings = [];
                    if ((int)($binding_settings['dashboard_id'] ?? 0) !== $analytics_edit_dashboard_id) continue;
                    $binding_row['_dashboard_settings'] = $binding_settings;
                    $analytics_edit_dashboard_widgets[] = $binding_row;
                }
            }
            $analytics_edit_id = absint($_GET['edit_analytics_binding'] ?? 0);
            $analytics_edit_binding = [];
            foreach ($analytics_bindings as $binding_row) if ((int)($binding_row['id'] ?? 0) === $analytics_edit_id) { $analytics_edit_binding = $binding_row; break; }
            $analytics_edit_settings = $analytics_edit_binding ? json_decode((string)($analytics_edit_binding['settings_json'] ?? ''), true) : [];
            if (!is_array($analytics_edit_settings)) $analytics_edit_settings = [];
            $analytics_edit_config = $analytics_edit_binding ? [
                'id'=>(int)$analytics_edit_binding['id'], 'form_id'=>(int)$analytics_edit_binding['form_id'],
                'question_key'=>(string)$analytics_edit_binding['question_key'], 'metric_key'=>(string)$analytics_edit_binding['metric_key'],
                'metric_label'=>(string)$analytics_edit_binding['metric_label'], 'chart_type'=>(string)$analytics_edit_binding['chart_type'],
                'aggregation'=>(string)$analytics_edit_binding['aggregation'], 'dimension'=>(string)$analytics_edit_binding['dimension'],
                'scope_mode'=>(string)$analytics_edit_binding['scope_mode'], 'is_active'=>(int)$analytics_edit_binding['is_active'],
            ] + $analytics_edit_settings : [];

            echo '<div class="ff-analytics-admin">';
            echo '<div class="ff-analytics-hero"><h2>Analytics 2.0 & Intelligence</h2><p>O Intelligence Center passa a ter uma única camada de contexto para atribuições, leitura operacional, comparação temporal e decisão. Usa primeiro o Command View para analisar o negócio e mantém abaixo a configuração dos dashboards, grupos e widgets.</p></div>';
            echo '<div class="ff-analytics-summary"><div class="ff-analytics-kpi"><span>Dashboards</span><strong>' . (int)count($analytics_dashboards) . '</strong></div><div class="ff-analytics-kpi"><span>Grupos de lojas</span><strong>' . (int)count($analytics_groups) . '</strong></div><div class="ff-analytics-kpi"><span>Widgets ativos no contexto</span><strong>' . (int)$analytics_active_count . '</strong></div><div class="ff-analytics-kpi"><span>Evidências</span><strong>' . (int)$analytics_evidence_widgets . '</strong></div><div class="ff-analytics-kpi"><span>Frentes</span><strong>' . (int)$analytics_fronts_widgets . '</strong></div></div>';
            echo '<div class="ff-analytics-help"><strong>Fluxo recomendado:</strong> cria um dashboard por objetivo de negócio, agrupa as lojas quando precisares de comparar universos específicos e adiciona apenas as métricas que ajudam a decidir. Nos briefings recorrentes, usa métricas permanentes para manter um único dashboard e um único widget por conceito, com histórico acumulado no BO, portal do cliente e FieldFlow App.</div>';
            if (isset($_GET['evergreen_processed'])) echo '<div class="ff-analytics-help"><strong>Consolidação:</strong> ' . (int)$_GET['evergreen_processed'] . ' pergunta(s) analisada(s), ' . (int)($_GET['evergreen_synced'] ?? 0) . ' widget(s) sincronizado(s), ' . (int)($_GET['evergreen_deactivated'] ?? 0) . ' legado(s) desativado(s), ' . (int)($_GET['evergreen_ownership_repaired'] ?? 0) . ' decisão(ões) antiga(s) de propriedade reparada(s).</div>';
            if ($analytics_integrity_issues > 0) echo '<div class="ff-analytics-warning"><strong>Atenção:</strong> existem ' . (int)$analytics_integrity_issues . ' widgets ligados a dashboards ou grupos de lojas que já não existem. Revê-os antes de publicar o quadro.</div>';

            self::render_analytics2_workspace($clients, $projects, $routes, $users, $locations, $selected_client_id, $selected_project_id, $selected_route_id);

            $ai_nonce = wp_create_nonce('routespro_ai_dashboard_builder');
            $ai_available = \RoutesPro\Analytics\AnalyticsAIService::available();
            $ai_provider = \RoutesPro\Analytics\AnalyticsAIService::providerLabel();
            echo '<section class="ff-ai-builder" id="ff-ai-dashboard-builder" data-nonce="' . esc_attr($ai_nonce) . '">';
            echo '<div class="ff-ai-builder-head"><div><h3>AI Dashboard Builder</h3><p style="margin:0;color:#475569;max-width:760px">Seleciona o objetivo e os formulários. O FieldFlow calcula as métricas e a IA propõe apenas a melhor arquitetura, gráficos e prioridades, sem inventar dados.</p></div><span class="ff-ai-provider' . ($ai_available ? '' : ' off') . '"><i></i>' . esc_html($ai_available ? $ai_provider : 'Motor inteligente FieldFlow') . '</span></div>';
            echo '<div class="ff-ai-builder-grid"><form id="ff-ai-builder-form"><div class="ff-ai-form-grid">';
            echo '<label><strong>Cliente / Marca</strong><select name="client_id" data-ai-role="client"><option value="0">Todos</option>';
            foreach ($clients as $client) echo '<option value="' . (int)$client['id'] . '"' . selected($selected_client_id, (int)$client['id'], false) . '>' . esc_html($client['name']) . '</option>';
            echo '</select></label>';
            echo '<label><strong>Campanha / Projeto</strong><select name="project_id" data-ai-role="project"><option value="0">Todas</option>';
            foreach ($projects as $project) echo '<option value="' . (int)$project['id'] . '" data-client-id="' . (int)$project['client_id'] . '"' . selected($selected_project_id, (int)$project['id'], false) . '>' . esc_html($project['name'] . ' #' . (int)$project['id']) . '</option>';
            echo '</select></label>';
            echo '<label class="wide"><strong>Formulários a analisar</strong><select name="form_ids[]" multiple size="7">';
            foreach ($forms as $form) echo '<option value="' . (int)$form['id'] . '">' . esc_html(($form['title'] ?: 'Sem título') . ' #' . (int)$form['id']) . '</option>';
            echo '</select><span class="ff-note">Podes selecionar vários com Ctrl ou Cmd. Sem seleção, o motor analisa todos os formulários disponíveis.</span></label>';
            echo '<label><strong>Objetivo</strong><select name="objective"><option value="mixed">Visão integrada</option><option value="execution">Execução operacional</option><option value="availability">Disponibilidade e ruturas</option><option value="fronts">Frentes e presença</option><option value="orders">Encomendas e reposição</option><option value="quality">Qualidade dos reportes</option></select></label>';
            echo '<label><strong>Público</strong><select name="audience"><option value="both">Cliente e merchandiser</option><option value="client_portal">Portal do cliente</option><option value="fieldflow_app">App do merchandiser</option></select></label>';
            echo '<label><strong>Período de referência</strong><select name="period_days"><option value="7">7 dias</option><option value="30" selected>30 dias</option><option value="90">90 dias</option><option value="180">180 dias</option><option value="365">365 dias</option></select></label>';
            echo '<label><strong>Layout</strong><select name="layout_type"><option value="mixed">Misto e responsivo</option><option value="executive">Executivo</option><option value="table">Detalhado</option></select></label>';
            echo '<label><strong>Máximo de widgets</strong><input type="number" name="max_widgets" value="7" min="3" max="10"></label>';
            echo '<label><strong>Grupo de lojas</strong><select name="store_group_id"><option value="0">Todas as lojas do âmbito</option>';
            foreach ($analytics_groups as $grp) echo '<option value="' . (int)$grp['id'] . '">' . esc_html($grp['name'] . ' (' . (int)$grp['locations_count'] . ' lojas)') . '</option>';
            echo '</select></label>';
            echo '<label class="wide"><strong>Título ou foco pretendido</strong><input type="text" name="title_hint" placeholder="Ex.: Disponibilidade e frentes por insígnia"></label>';
            echo '<label class="wide"><strong>Contexto de negócio</strong><textarea name="business_context" rows="3" placeholder="Ex.: Priorizar ruturas recorrentes e lojas com quebra de frentes."></textarea></label>';
            echo '</div><div class="ff-ai-actions"><button type="submit" class="button button-primary" data-ai-action="preview">Gerar proposta</button><button type="button" class="button" data-ai-action="create" hidden>Criar e publicar dashboard</button><span class="ff-note" data-ai-status></span></div></form>';
            echo '<div class="ff-ai-preview" data-ai-preview><div class="ff-ai-preview-empty"><div><strong>A proposta aparece aqui.</strong><br>A IA escolhe widgets apenas a partir das perguntas e métricas existentes.</div></div></div></div></section>';

            $dashboard_form = $analytics_edit_dashboard ?: [
                'id'=>0,
                'client_id'=>$selected_client_id,
                'project_id'=>$selected_project_id,
                'route_id'=>$selected_route_id,
                'title'=>'',
                'description'=>'',
                'layout_type'=>'mixed',
                'visibility'=>'client_portal',
                'sort_order'=>10,
                'is_active'=>1,
            ];
            $widget_orchestration_settings = WidgetOrchestrator::settingsForDashboard($dashboard_form);
            echo '<div class="ff-analytics-builder-grid' . ($analytics_edit_dashboard ? ' is-dashboard-editing' : '') . '">';
            echo '<div class="routespro-card ff-analytics-step step-dashboard" id="ff-analytics-dashboard-editor"><h3 style="margin-top:0">' . ($analytics_edit_dashboard ? 'Editar dashboard' : 'Criar dashboard') . '</h3><form method="post">';
            wp_nonce_field('routespro_assignment_hub_save_analytics_dashboard');
            echo '<input type="hidden" name="routespro_assignment_hub_action" value="save_analytics_dashboard"><input type="hidden" name="tab" value="analytics"><input type="hidden" name="analytics_dashboard_id" value="' . (int)($dashboard_form['id'] ?? 0) . '">';
            echo '<p><label><strong>Cliente / Marca</strong><br><select name="client_id" data-ff-role="client" style="width:100%"><option value="0">Todos</option>';
            foreach ($clients as $client) echo '<option value="' . (int)$client['id'] . '"' . selected((int)($dashboard_form['client_id'] ?? 0), (int)$client['id'], false) . '>' . esc_html($client['name']) . '</option>';
            echo '</select></label></p>';
            echo '<p><label><strong>Campanha / Projeto</strong><br><select name="project_id" data-ff-role="project" style="width:100%"><option value="0">Todos</option>';
            foreach ($projects as $project) echo '<option value="' . (int)$project['id'] . '" data-client-id="' . (int)$project['client_id'] . '"' . selected((int)($dashboard_form['project_id'] ?? 0), (int)$project['id'], false) . '>' . esc_html($project['name'] . ' #' . (int)$project['id']) . '</option>';
            echo '</select></label></p>';
            echo '<p><label><strong>Rota</strong><br><select name="route_id" data-ff-role="route" style="width:100%"><option value="0">Todas</option>';
            foreach ($routes as $route) echo '<option value="' . (int)$route['id'] . '" data-client-id="' . (int)($route['client_id'] ?? 0) . '" data-project-id="' . (int)($route['project_id'] ?? 0) . '"' . selected((int)($dashboard_form['route_id'] ?? 0), (int)$route['id'], false) . '>#' . (int)$route['id'] . ' · ' . esc_html($route['date'] ?: 'sem data') . '</option>';
            echo '</select></label></p>';
            echo '<p><label><strong>Nome do dashboard</strong><br><input type="text" name="dashboard_title" value="' . esc_attr((string)($dashboard_form['title'] ?? '')) . '" placeholder="Frentes e execução por loja" style="width:100%" required></label></p>';
            echo '<p><label><strong>Descrição</strong><br><textarea name="dashboard_description" rows="2" style="width:100%" placeholder="Quadro para acompanhamento executivo do cliente.">' . esc_textarea((string)($dashboard_form['description'] ?? '')) . '</textarea></label></p>';
            echo '<p><label><strong>Layout</strong><br><select name="dashboard_layout_type" style="width:100%"><option value="mixed"' . selected((string)($dashboard_form['layout_type'] ?? 'mixed'), 'mixed', false) . '>Misto: KPIs + gráficos + tabelas</option><option value="executive"' . selected((string)($dashboard_form['layout_type'] ?? 'mixed'), 'executive', false) . '>Resumo executivo</option><option value="table"' . selected((string)($dashboard_form['layout_type'] ?? 'mixed'), 'table', false) . '>Detalhado: uma coluna</option></select></label></p>';
            echo '<p><label><strong>Visível em</strong><br><select name="dashboard_visibility" style="width:100%"><option value="client_portal"' . selected((string)($dashboard_form['visibility'] ?? 'client_portal'), 'client_portal', false) . '>Portal do cliente</option><option value="fieldflow_app"' . selected((string)($dashboard_form['visibility'] ?? 'client_portal'), 'fieldflow_app', false) . '>App do merchandiser</option><option value="both"' . selected((string)($dashboard_form['visibility'] ?? 'client_portal'), 'both', false) . '>Cliente e merchandiser</option></select></label></p>';
            echo '<div style="margin:14px 0;padding:14px;border:1px solid #bfdbfe;border-radius:14px;background:#f8fbff"><strong>Widget Intelligence</strong><p class="ff-note" style="margin:5px 0 12px">O FieldFlow cruza briefing, perguntas, dados disponíveis e público para publicar apenas os widgets mais relevantes. Os ficheiros exportados continuam a preservar o detalhe integral.</p>';
            echo '<p><label><strong>Modo</strong><br><select name="dashboard_widget_mode" style="width:100%"><option value="automatic"' . selected((string)$widget_orchestration_settings['mode'], 'automatic', false) . '>Automático, publica apenas os mais relevantes</option><option value="assisted"' . selected((string)$widget_orchestration_settings['mode'], 'assisted', false) . '>Assistido, recomenda mas mantém todos visíveis</option><option value="manual"' . selected((string)$widget_orchestration_settings['mode'], 'manual', false) . '>Manual, respeita a seleção atual</option></select></label></p>';
            echo '<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px"><label><strong>Máx. cliente</strong><br><input type="number" name="dashboard_widget_max_client" min="3" max="12" value="' . (int)$widget_orchestration_settings['max_client'] . '" style="width:100%"></label><label><strong>Máx. merchandiser</strong><br><input type="number" name="dashboard_widget_max_merchandiser" min="3" max="12" value="' . (int)$widget_orchestration_settings['max_merchandiser'] . '" style="width:100%"></label><label><strong>Máx. no email</strong><br><input type="number" name="dashboard_widget_max_email" min="3" max="8" value="' . (int)$widget_orchestration_settings['max_email'] . '" style="width:100%"></label></div>';
            echo '<p style="margin-bottom:0"><label><input type="checkbox" name="dashboard_widget_alert_boost" value="1"' . checked(!empty($widget_orchestration_settings['alert_boost']), true, false) . '> Dar prioridade automática a widgets associados a alertas ativos</label></p></div>';
            echo '<p><label><strong>Ordem</strong><br><input type="number" name="dashboard_sort_order" value="' . (int)($dashboard_form['sort_order'] ?? 10) . '" style="width:100%"><span class="ff-note">Também podes usar Subir e Descer na lista de dashboards.</span></label></p>';
            echo '<p><label><input type="checkbox" name="dashboard_is_active" value="1"' . checked((int)($dashboard_form['is_active'] ?? 1), 1, false) . '> Dashboard ativo e visível no âmbito escolhido</label></p>';
            submit_button($analytics_edit_dashboard ? 'Atualizar dashboard' : 'Guardar dashboard');
            if ($analytics_edit_dashboard) echo '<p><a class="button" href="' . esc_url(add_query_arg(['page'=>'routespro-assignments-hub','tab'=>'analytics'], admin_url('admin.php'))) . '#ff-analytics-dashboard-editor">Cancelar edição</a></p>';
            echo '</form>';
            if ($analytics_edit_dashboard) {
                echo '<div class="ff-dashboard-content-editor"><div class="ff-dashboard-content-head"><div><h4>Conteúdo e visualização</h4><p>Escolhe o gráfico, a presença no topo, a tabela e a ordem de cada widget. Ao desmarcar “No dashboard”, o widget é preservado no quadro automático.</p></div><a class="button button-primary" href="' . esc_url(add_query_arg(['page'=>'routespro-assignments-hub','tab'=>'analytics','new_dashboard_id'=>$analytics_edit_dashboard_id,'client_id'=>(int)($dashboard_form['client_id'] ?? 0),'project_id'=>(int)($dashboard_form['project_id'] ?? 0),'route_id'=>(int)($dashboard_form['route_id'] ?? 0)], admin_url('admin.php'))) . '#ff-analytics-widget-editor">Adicionar widget</a></div>';
                if (!$analytics_edit_dashboard_widgets) {
                    echo '<div class="ff-dashboard-content-empty">Este dashboard ainda não tem widgets. Usa “Adicionar widget” para escolher o conteúdo.</div>';
                } else {
                    echo '<form method="post">';
                    wp_nonce_field('routespro_assignment_hub_save_analytics_dashboard_content');
                    echo '<input type="hidden" name="routespro_assignment_hub_action" value="save_analytics_dashboard_content"><input type="hidden" name="analytics_dashboard_id" value="' . (int)$analytics_edit_dashboard_id . '"><div class="ff-table-scroll"><table class="widefat striped ff-dashboard-content-table"><thead><tr><th>Widget</th><th>Visualização</th><th>Opções</th><th>Ordem</th><th></th></tr></thead><tbody>';
                    foreach ($analytics_edit_dashboard_widgets as $widget_row) {
                        $widget_id = (int)$widget_row['id'];
                        $widget_settings = (array)($widget_row['_dashboard_settings'] ?? []);
                        $question_lookup_key = (int)($widget_row['form_id'] ?? 0) . ':' . sanitize_key((string)($widget_row['question_key'] ?? ''));
                        $widget_question = $analytics_question_lookup[$question_lookup_key] ?? [];
                        $widget_question_type = sanitize_key((string)($widget_settings['question_type'] ?? $widget_question['question_type'] ?? ''));
                        $locked_chart = in_array($widget_question_type, ['image_upload','file_upload','categorized_image_upload'], true);
                        echo '<tr><td><input type="hidden" name="dashboard_widget_ids[]" value="' . $widget_id . '"><strong>' . esc_html((string)$widget_row['metric_label']) . '</strong><br><span class="ff-note">' . esc_html((string)($widget_question['question_label'] ?? $widget_row['question_key'])) . '</span></td>';
                        echo '<td><select name="dashboard_widget_chart_type[' . $widget_id . ']"' . ($locked_chart ? ' disabled' : '') . '>' . self::render_simple_options(self::analytics_options('chart'), (string)$widget_row['chart_type']) . '</select>' . ($locked_chart ? '<input type="hidden" name="dashboard_widget_chart_type[' . $widget_id . ']" value="image_gallery"><span class="ff-note">Galeria obrigatória para evidências.</span>' : '') . '</td>';
                        echo '<td><label><input type="checkbox" name="dashboard_widget_in_dashboard[]" value="' . $widget_id . '" checked> No dashboard</label><br><label><input type="checkbox" name="dashboard_widget_active[]" value="' . $widget_id . '"' . checked((int)$widget_row['is_active'], 1, false) . '> Ativo</label><br><label><input type="checkbox" name="dashboard_widget_pinned[]" value="' . $widget_id . '"' . checked((int)($widget_settings['orchestration_pinned'] ?? 0), 1, false) . '> Fixar sempre</label><br><label><input type="checkbox" name="dashboard_widget_kpi[]" value="' . $widget_id . '"' . checked((int)($widget_settings['show_kpi'] ?? 0), 1, false) . '> KPI</label><br><label><input type="checkbox" name="dashboard_widget_table[]" value="' . $widget_id . '"' . checked((int)($widget_settings['show_table'] ?? 0), 1, false) . '> Tabela</label></td>';
                        echo '<td><input type="number" name="dashboard_widget_sort_order[' . $widget_id . ']" value="' . (int)($widget_settings['sort_order'] ?? 10) . '" min="0" step="10" style="width:78px"></td>';
                        echo '<td><a class="button button-small" href="' . esc_url(add_query_arg(['page'=>'routespro-assignments-hub','tab'=>'analytics','edit_analytics_binding'=>$widget_id,'edit_analytics_dashboard'=>$analytics_edit_dashboard_id], admin_url('admin.php'))) . '#ff-analytics-widget-editor">Detalhes</a></td></tr>';
                    }
                    echo '</tbody></table></div>';
                    submit_button('Guardar conteúdo e visualização', 'secondary');
                    echo '</form>';
                }
                echo '</div>';
            }
            echo '</div>';

            echo '<div class="routespro-card ff-analytics-step step-group"><h3 style="margin-top:0">Criar grupo de lojas</h3><form method="post">';
            wp_nonce_field('routespro_assignment_hub_save_analytics_store_group');
            echo '<input type="hidden" name="routespro_assignment_hub_action" value="save_analytics_store_group"><input type="hidden" name="tab" value="analytics">';
            echo '<p><label><strong>Cliente</strong><br><select name="client_id" data-ff-role="client" style="width:100%"><option value="0">Todos</option>';
            foreach ($clients as $client) echo '<option value="' . (int)$client['id'] . '"' . selected($selected_client_id, (int)$client['id'], false) . '>' . esc_html($client['name']) . '</option>';
            echo '</select></label></p>';
            echo '<p><label><strong>Campanha</strong><br><select name="project_id" data-ff-role="project" style="width:100%"><option value="0">Todas</option>';
            foreach ($projects as $project) echo '<option value="' . (int)$project['id'] . '" data-client-id="' . (int)$project['client_id'] . '"' . selected($selected_project_id, (int)$project['id'], false) . '>' . esc_html($project['name'] . ' #' . (int)$project['id']) . '</option>';
            echo '</select></label></p>';
            echo '<p><label><strong>Nome do grupo</strong><br><input type="text" name="store_group_name" placeholder="Grande Lisboa, Norte, Auchan, Top 20" style="width:100%" required></label></p>';
            echo '<p><label><strong>Lojas / Locais</strong><br><input type="search" class="regular-text" data-ff-store-group-search placeholder="Pesquisar por nome, cidade, distrito ou ID" style="width:100%;max-width:none;margin-bottom:8px"></label>';
            echo '<span style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px"><button type="button" class="button" data-ff-store-select="visible">Selecionar visíveis</button><button type="button" class="button" data-ff-store-select="clear">Limpar seleção</button><button type="button" class="button" data-ff-store-select="client">Selecionar cliente atual</button><button type="button" class="button" data-ff-store-select="project">Selecionar campanha atual</button><span class="ff-note" data-ff-store-count>0 selecionadas</span></span>';
            echo '<select name="store_group_location_ids[]" multiple size="14" data-ff-store-group-select style="width:100%;min-height:320px">';
            foreach ($analytics_locations as $loc) {
                $label = '#' . (int)$loc['id'] . ' · ' . (string)$loc['name'];
                if (!empty($loc['city'])) $label .= ' · ' . (string)$loc['city'];
                if (!empty($loc['district'])) $label .= ' · ' . (string)$loc['district'];
                echo '<option value="' . (int)$loc['id'] . '" data-search="' . esc_attr(strtolower(remove_accents($label))) . '" data-client-id="' . (int)($loc['client_id'] ?? 0) . '" data-project-id="' . (int)($loc['project_id'] ?? 0) . '">' . esc_html($label) . '</option>';
            }
            echo '</select><span class="ff-note">Pesquisa, filtra por cliente/campanha e usa seleção em massa. Assim não tens de picar 276 lojas uma a uma, que isso é castigo bíblico disfarçado de backoffice.</span></p>';
            echo '<p><label><input type="checkbox" name="store_group_is_active" value="1" checked> Grupo ativo</label></p>';
            submit_button('Guardar grupo');
            echo '</form></div>';
            echo '</div>';

            echo '<div class="routespro-card ff-analytics-step step-widget" id="ff-analytics-widget-editor"><h3 style="margin-top:0">' . ($analytics_edit_binding ? 'Editar widget ou métrica' : 'Criar widget ou métrica') . '</h3><form method="post">';
            wp_nonce_field('routespro_assignment_hub_save_analytics_binding');
            $widget_default_dashboard_id = (int)($analytics_edit_settings['dashboard_id'] ?? $analytics_new_dashboard_id);
            $widget_return_dashboard_id = $analytics_edit_dashboard_id ?: $widget_default_dashboard_id;
            echo '<input type="hidden" name="routespro_assignment_hub_action" value="save_analytics_binding"><input type="hidden" name="tab" value="analytics"><input type="hidden" name="analytics_binding_id" value="' . (int)($analytics_edit_binding['id'] ?? 0) . '"><input type="hidden" name="return_dashboard_id" value="' . (int)$widget_return_dashboard_id . '">';
            echo '<div class="ff-form-grid" data-ff-context="analytics-form">';
            echo '<p><label><strong>Dashboard</strong><br><select name="dashboard_id" style="width:100%"><option value="0">Dashboard legacy / automático</option>';
            foreach ($analytics_dashboards as $dash) echo '<option value="' . (int)$dash['id'] . '"' . selected($widget_default_dashboard_id, (int)$dash['id'], false) . '>' . esc_html($dash['title'] . ' #' . (int)$dash['id']) . '</option>';
            echo '</select></label></p>';
            echo '<p><label><strong>Grupo de lojas</strong><br><select name="store_group_id" style="width:100%"><option value="0">Todas as lojas do âmbito</option>';
            foreach ($analytics_groups as $grp) echo '<option value="' . (int)$grp['id'] . '">' . esc_html($grp['name'] . ' (' . (int)$grp['locations_count'] . ' lojas)') . '</option>';
            echo '</select></label></p>';
            echo '<p><label><strong>Cliente / Marca</strong><br><select name="client_id" data-ff-role="client" style="width:100%"><option value="0">Todos</option>';
            foreach ($clients as $client) echo '<option value="' . (int)$client['id'] . '"' . selected($selected_client_id, (int)$client['id'], false) . '>' . esc_html($client['name']) . '</option>';
            echo '</select></label></p>';
            echo '<p><label><strong>Campanha / Projeto</strong><br><select name="project_id" data-ff-role="project" style="width:100%"><option value="0">Todos</option>';
            foreach ($projects as $project) echo '<option value="' . (int)$project['id'] . '" data-client-id="' . (int)$project['client_id'] . '"' . selected($selected_project_id, (int)$project['id'], false) . '>' . esc_html($project['name'] . ' #' . (int)$project['id']) . '</option>';
            echo '</select></label></p>';
            echo '<p><label><strong>Rota</strong><br><select name="route_id" data-ff-role="route" style="width:100%"><option value="0">Todas</option>';
            foreach ($routes as $route) echo '<option value="' . (int)$route['id'] . '" data-client-id="' . (int)($route['client_id'] ?? 0) . '" data-project-id="' . (int)($route['project_id'] ?? 0) . '">#' . (int)$route['id'] . ' · ' . esc_html($route['date'] ?: 'sem data') . '</option>';
            echo '</select></label></p>';
            $route_mobility_widget_catalog = RouteMobilityWidgetService::catalog();
            echo '<p><label><strong>Origem do widget</strong><br><select name="metric_source" data-ff-analytics-source style="width:100%"><option value="question">Pergunta / formulário</option><option value="' . esc_attr(RouteMobilityWidgetService::SOURCE_TYPE) . '">Rotas e mobilidade</option></select><span class="ff-note">Os widgets de rota usam a mesma fonte canónica do Daily Close, Excel e PDF.</span></label></p>';
            echo '<p data-ff-system-metric-row style="display:none"><label><strong>Indicador de rota / mobilidade</strong><br><select name="system_metric_key" data-ff-system-metric style="width:100%"><option value="">Seleciona</option>';
            foreach ($route_mobility_widget_catalog as $system_key => $definition) {
                echo '<option value="' . esc_attr((string)$system_key) . '" data-label="' . esc_attr((string)($definition['label'] ?? $system_key)) . '" data-description="' . esc_attr((string)($definition['description'] ?? '')) . '" data-chart="' . esc_attr((string)($definition['chart_type'] ?? 'kpi')) . '" data-aggregation="' . esc_attr((string)($definition['aggregation'] ?? 'sum')) . '" data-dimension="' . esc_attr((string)($definition['dimension'] ?? 'submitted_at')) . '" data-show-kpi="' . (int)($definition['show_kpi'] ?? 0) . '" data-show-table="' . (int)($definition['show_table'] ?? 0) . '" data-show-empty="' . (int)($definition['show_empty'] ?? 1) . '">' . esc_html((string)($definition['label'] ?? $system_key)) . '</option>';
            }
            echo '</select><span class="ff-note" data-ff-system-metric-hint>Escolhe o indicador operacional a publicar no dashboard.</span></label></p>';
            echo '<p data-ff-question-source-row><label><strong>Formulário</strong><br><select name="form_id" data-ff-analytics-form style="width:100%" required><option value="">Seleciona</option>';
            foreach ($forms as $form) echo '<option value="' . (int)$form['id'] . '">' . esc_html(($form['title'] ?: 'Sem título') . ' #' . (int)$form['id']) . '</option>';
            echo '</select></label></p>';
            echo '<p data-ff-question-source-row><label><strong>Pergunta</strong><br><select name="question_key" data-ff-analytics-question style="width:100%" required><option value="">Seleciona um formulário primeiro</option></select><input type="hidden" name="question_type" value="text" data-ff-analytics-question-type></label></p>';
            echo '<p><label><strong>Chave da métrica</strong><br><input type="text" name="metric_key" placeholder="frentes_atuais" style="width:100%" required></label></p>';
            echo '<p><label><strong>Label</strong><br><input type="text" name="metric_label" placeholder="Frentes atuais" style="width:100%" required></label></p>';
            echo '<p><label><strong>Tipo de widget</strong><br><select name="chart_type" data-ff-analytics-chart style="width:100%">' . self::render_simple_options(self::analytics_options('chart'), 'line') . '</select><span class="ff-note" data-ff-analytics-type-hint>Escolhe a pergunta para receber uma recomendação.</span></label></p>';
            echo '<p><label><strong>Agregação</strong><br><select name="aggregation" style="width:100%">' . self::render_simple_options(self::analytics_options('aggregation'), 'latest') . '</select></label></p>';
            echo '<p><label><strong>Dimensão principal</strong><br><select name="dimension" style="width:100%">' . self::render_simple_options(self::analytics_options('dimension'), 'location_id') . '</select></label></p>';
            echo '<p><label><strong>Dimensão secundária</strong><br><select name="secondary_dimension" style="width:100%"><option value="">Nenhuma</option>' . self::render_simple_options(self::analytics_options('dimension'), '') . '</select></label></p>';
            echo '<div data-ff-evidence-options style="display:none;grid-column:1/-1;padding:14px;border:1px solid #bfdbfe;border-radius:14px;background:#f8fbff"><p style="margin-top:0"><strong>Composição das evidências</strong><br><span class="ff-note">O widget combina local, data da visita e imagens, sem mostrar links técnicos ou JSON.</span></p><div style="display:grid;grid-template-columns:minmax(220px,1fr) minmax(220px,1fr);gap:12px"><label><strong>Agrupamento visual</strong><br><select name="evidence_grouping" style="width:100%">' . self::render_simple_options(self::analytics_options('evidence_grouping'), 'location_date') . '</select></label><label style="display:flex;align-items:center;gap:8px;padding-top:26px"><input type="checkbox" name="evidence_show_thumbnails" value="1" checked> Mostrar miniaturas das imagens</label></div></div>';
            echo '<p><label><strong>Modo de scope</strong><br><select name="scope_mode" style="width:100%">' . self::render_simple_options(self::analytics_options('scope'), 'client_project_location') . '</select></label></p>';
            echo '<p><label><strong>Ordem</strong><br><input type="number" name="sort_order" value="10" style="width:100%"></label></p>';
            echo '<p><label><input type="checkbox" name="show_kpi" value="1" checked> Mostrar KPI no topo</label><br><label><input type="checkbox" name="show_table" value="1" checked> Mostrar tabela detalhada</label><br><label><input type="checkbox" name="show_empty" value="1" checked> Mostrar mesmo sem dados</label><br><label><input type="checkbox" name="is_active" value="1" checked> Widget ativo</label></p>';
            echo '</div>';
            submit_button($analytics_edit_binding ? 'Atualizar widget analítico' : 'Guardar widget analítico');
            if ($analytics_edit_binding) echo '<p><a class="button" href="' . esc_url(add_query_arg(['page'=>'routespro-assignments-hub','tab'=>'analytics'], admin_url('admin.php'))) . '#ff-analytics-widget-editor">Cancelar edição</a></p>';
            echo '</form></div>';

            echo '<script>(function(){const box=document.getElementById("ff-ai-dashboard-builder");if(!box)return;const form=box.querySelector("#ff-ai-builder-form"),preview=box.querySelector("[data-ai-preview]"),createBtn=box.querySelector("[data-ai-action=create]"),previewBtn=box.querySelector("[data-ai-action=preview]"),status=box.querySelector("[data-ai-status]"),nonce=box.dataset.nonce||"";let proposal=null;const esc=s=>{const d=document.createElement("div");d.textContent=String(s??"");return d.innerHTML;};function setBusy(on,msg){box.classList.toggle("ff-ai-loading",on);if(previewBtn)previewBtn.innerHTML=on?"<span class=\\"ff-ai-spinner\\"></span> A analisar...":"Gerar proposta";if(status)status.textContent=msg||"";}function render(p,meta){proposal=p;const widgets=Array.isArray(p.widgets)?p.widgets:[];preview.innerHTML=`<h4>${esc(p.title||"Dashboard Analytics IA")}</h4><p>${esc(p.description||"")}</p><div class="ff-ai-preview-meta"><span>${esc(p.layout_type||"mixed")}</span><span>${widgets.length} widgets</span><span>${esc(meta?.provider||"FieldFlow")}</span></div>${p.narrative?`<p><strong>Estratégia:</strong> ${esc(p.narrative)}</p>`:""}<div class="ff-ai-widget-list">${widgets.map((w,i)=>`<div class="ff-ai-widget"><span class="ff-ai-widget-no">${i+1}</span><div><strong>${esc(w.metric_label||w.question_key)}</strong><small>${esc((w.chart_type||"")+" · "+(w.aggregation||"")+" · "+(w.reason||""))}</small></div><span class="ff-ai-badge">${esc(w.dimension||"")}</span></div>`).join("")||"<div class=\\"ff-ai-error\\">Não foram encontrados widgets válidos.</div>"}</div><p class="ff-note">${esc(p.generation_note||"")}</p>`;createBtn.hidden=!widgets.length;}async function request(action){const data=new FormData(form);data.append("action",action);data.append("nonce",nonce);if(action==="routespro_ai_dashboard_create")data.append("proposal",JSON.stringify(proposal||{}));const res=await fetch(ajaxurl,{method:"POST",credentials:"same-origin",body:data});const json=await res.json();if(!res.ok||!json.success)throw new Error(json?.data?.message||"Não foi possível concluir o pedido.");return json.data;}form.addEventListener("submit",async ev=>{ev.preventDefault();setBusy(true,"A ler formulários e cobertura real dos dados...");createBtn.hidden=true;try{const data=await request("routespro_ai_dashboard_preview");render(data.proposal,data);setBusy(false,"Proposta pronta para revisão.");}catch(err){preview.innerHTML=`<div class="ff-ai-error">${esc(err.message)}</div>`;setBusy(false,"");}});createBtn.addEventListener("click",async()=>{if(!proposal)return;setBusy(true,"A criar dashboard e widgets...");try{const data=await request("routespro_ai_dashboard_create");status.textContent=data.message||"Dashboard criado.";window.location.href=data.redirect_url||window.location.href;}catch(err){setBusy(false,err.message);}});const client=form.querySelector("[data-ai-role=client]"),project=form.querySelector("[data-ai-role=project]");function filterProjects(){if(!client||!project)return;const id=String(client.value||"0");Array.from(project.options).forEach(o=>{if(!o.value)return;o.hidden=id!=="0"&&String(o.dataset.clientId||"0")!==id;});if(project.selectedOptions[0]?.hidden)project.value="0";}client?.addEventListener("change",filterProjects);filterProjects();})();</script>';

            $analytics_question_map_json = wp_json_encode($analytics_question_map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $analytics_builder_script = <<<'JS'
<script>(function(){
    var formSel=document.querySelector("[data-ff-analytics-form]");
    var questionSel=document.querySelector("[data-ff-analytics-question]");
    var questionTypeInput=document.querySelector("[data-ff-analytics-question-type]");
    var chartSel=document.querySelector("[data-ff-analytics-chart]");
    var typeHint=document.querySelector("[data-ff-analytics-type-hint]");
    var sourceSel=document.querySelector("[data-ff-analytics-source]");
    var systemMetricSel=document.querySelector("[data-ff-system-metric]");
    var systemMetricRow=document.querySelector("[data-ff-system-metric-row]");
    var systemMetricHint=document.querySelector("[data-ff-system-metric-hint]");
    var questionSourceRows=Array.from(document.querySelectorAll("[data-ff-question-source-row]"));
    var widgetRoot=formSel?formSel.closest("form"):document;
    var questionMap=__FF_QUESTION_MAP__;
    var editConfig=__FF_EDIT_CONFIG__;
    function escHtml(value){var d=document.createElement("div");d.textContent=String(value==null?"":value);return d.innerHTML;}
    function renderQuestions(){
        if(!formSel||!questionSel)return;
        var formId=String(formSel.value||"");
        var items=questionMap[formId]||[];
        if(!formId){questionSel.innerHTML="<option value=\"\">Seleciona um formulário primeiro</option>";return;}
        if(!items.length){questionSel.innerHTML="<option value=\"\">Sem perguntas disponíveis neste formulário</option>";return;}
        var html="<option value=\"\">Seleciona uma pergunta</option>";
        items.forEach(function(item){
            var key=String(item.question_key||"");if(!key)return;
            var label=String(item.question_label||key),type=String(item.question_type||"text"),source=String(item.source||"");
            var suffix=source==="context_question"?" · contextual":(source==="briefing_question"?" · briefing":(source==="stored"?" · histórico":""));
            html+="<option value=\""+escHtml(key)+"\" data-question-type=\""+escHtml(type)+"\" data-question-label=\""+escHtml(label)+"\" data-dashboard-id=\""+escHtml(item.preferred_dashboard_id||0)+"\" data-chart=\""+escHtml(item.preferred_chart||"")+"\" data-aggregation=\""+escHtml(item.preferred_aggregation||"")+"\" data-dimension=\""+escHtml(item.preferred_dimension||"")+"\" data-secondary-dimension=\""+escHtml(item.preferred_secondary_dimension||"")+"\" data-sort-order=\""+escHtml(item.preferred_sort_order||10)+"\" data-show-kpi=\""+escHtml(item.preferred_show_kpi||0)+"\" data-show-table=\""+escHtml(item.preferred_show_table||0)+"\" data-show-empty=\""+escHtml(item.preferred_show_empty||0)+"\">"+escHtml(label)+suffix+" · "+escHtml(type)+" ["+escHtml(key)+"]</option>";
        });
        questionSel.innerHTML=html;
    }
    function isSystemSource(){return sourceSel&&String(sourceSel.value||"")==="route_mobility";}
    function applySystemMetric(force){
        if(!isSystemSource()||!systemMetricSel||!chartSel)return;
        var option=systemMetricSel.options[systemMetricSel.selectedIndex];
        var key=option?String(option.value||""):"";
        var metricKey=widgetRoot.querySelector("[name=metric_key]"),metricLabel=widgetRoot.querySelector("[name=metric_label]"),aggregation=widgetRoot.querySelector("[name=aggregation]"),dimension=widgetRoot.querySelector("[name=dimension]"),secondaryDimension=widgetRoot.querySelector("[name=secondary_dimension]"),showKpi=widgetRoot.querySelector("[name=show_kpi]"),showTable=widgetRoot.querySelector("[name=show_table]"),showEmpty=widgetRoot.querySelector("[name=show_empty]"),evidenceOptions=widgetRoot.querySelector("[data-ff-evidence-options]");
        if(questionTypeInput)questionTypeInput.value="system_route_mobility";
        if(evidenceOptions)evidenceOptions.style.display="none";
        if(!key){if(systemMetricHint)systemMetricHint.textContent="Escolhe o indicador operacional a publicar no dashboard.";if(typeHint)typeHint.textContent="Widgets de rota usam os dados canónicos de routing e mobilidade.";return;}
        if(metricKey&&(force||!metricKey.value))metricKey.value=key;
        if(metricLabel&&(force||!metricLabel.value))metricLabel.value=String(option.dataset.label||option.textContent||key);
        if(force||!chartSel.value)chartSel.value=String(option.dataset.chart||"kpi");
        if(aggregation&&(force||!aggregation.value))aggregation.value=String(option.dataset.aggregation||"sum");
        if(dimension&&(force||!dimension.value))dimension.value=String(option.dataset.dimension||"submitted_at");
        if(secondaryDimension&&force)secondaryDimension.value="";
        if(showKpi&&option.dataset.showKpi!==undefined&&(force||!editConfig?.id))showKpi.checked=option.dataset.showKpi==="1";
        if(showTable&&option.dataset.showTable!==undefined&&(force||!editConfig?.id))showTable.checked=option.dataset.showTable==="1";
        if(showEmpty&&option.dataset.showEmpty!==undefined&&(force||!editConfig?.id))showEmpty.checked=option.dataset.showEmpty==="1";
        if(systemMetricHint)systemMetricHint.textContent=String(option.dataset.description||"Indicador canónico de rotas e mobilidade.");
        if(typeHint)typeHint.textContent="O dashboard lê a mesma projeção canónica usada no Daily Close, Excel e PDF.";
    }
    function toggleMetricSource(force){
        var system=isSystemSource();
        questionSourceRows.forEach(function(row){row.style.display=system?"none":"";});
        if(systemMetricRow)systemMetricRow.style.display=system?"":"none";
        if(formSel){formSel.required=!system;formSel.disabled=system;}
        if(questionSel){questionSel.required=!system;questionSel.disabled=system;}
        if(systemMetricSel){systemMetricSel.required=system;systemMetricSel.disabled=!system;}
        if(system)applySystemMetric(!!force);else{if(questionTypeInput)questionTypeInput.value="text";renderQuestions();adaptWidgetType();}
    }
    function adaptWidgetType(){
        if(isSystemSource()){applySystemMetric(false);return;}
        if(!questionSel||!chartSel)return;
        var option=questionSel.options[questionSel.selectedIndex];
        var type=option?String(option.dataset.questionType||"text"):"text";
        if(questionTypeInput)questionTypeInput.value=type||"text";
        var aggregation=widgetRoot.querySelector("[name=aggregation]");
        var dimension=widgetRoot.querySelector("[name=dimension]");
        var secondaryDimension=widgetRoot.querySelector("[name=secondary_dimension]");
        var evidenceOptions=widgetRoot.querySelector("[data-ff-evidence-options]");
        var showTable=widgetRoot.querySelector("[name=show_table]");
        var metricKey=widgetRoot.querySelector("[name=metric_key]"),metricLabel=widgetRoot.querySelector("[name=metric_label]"),dashboard=widgetRoot.querySelector("[name=dashboard_id]"),sortOrder=widgetRoot.querySelector("[name=sort_order]"),showKpi=widgetRoot.querySelector("[name=show_kpi]"),showEmpty=widgetRoot.querySelector("[name=show_empty]");
        if(option&&option.value){
            if(metricKey&&!metricKey.value)metricKey.value=String(option.value||"").replace(/[^a-z0-9_]+/gi,"_").toLowerCase()+"_analytics";
            if(metricLabel&&!metricLabel.value)metricLabel.value=String(option.dataset.questionLabel||option.textContent||option.value).replace(/ · (briefing|contextual|histórico).*$/i,"");
            if(option.dataset.dashboardId&&dashboard)dashboard.value=option.dataset.dashboardId;
            if(option.dataset.chart)chartSel.value=option.dataset.chart;
            if(option.dataset.aggregation&&aggregation)aggregation.value=option.dataset.aggregation;
            if(option.dataset.dimension&&dimension)dimension.value=option.dataset.dimension;
            if(option.dataset.secondaryDimension&&secondaryDimension)secondaryDimension.value=option.dataset.secondaryDimension;
            if(option.dataset.sortOrder&&sortOrder)sortOrder.value=option.dataset.sortOrder;
            if(showKpi&&option.dataset.showKpi)showKpi.checked=option.dataset.showKpi==="1";
            if(showTable&&option.dataset.showTable)showTable.checked=option.dataset.showTable==="1";
            if(showEmpty&&option.dataset.showEmpty)showEmpty.checked=option.dataset.showEmpty==="1";
        }
        if(type==="image_upload"||type==="file_upload"||type==="categorized_image_upload"){
            chartSel.value="image_gallery";
            if(aggregation)aggregation.value="evidence";
            if(dimension)dimension.value="location_id";
            if(secondaryDimension)secondaryDimension.value="submitted_at";
            if(evidenceOptions)evidenceOptions.style.display="block";
            if(showTable)showTable.checked=false;
            if(typeHint)typeHint.textContent="Widget composto por local, data e imagens, com ZIP por loja e por data. A tabela detalhada é opcional para manter o front limpo.";
        }else if(type==="product_matrix"){
            if(evidenceOptions)evidenceOptions.style.display="none";
            if(chartSel.value==="image_gallery")chartSel.value="table";
            if(dimension)dimension.value="location_id";
            if(showTable)showTable.checked=true;
            if(typeHint)typeHint.textContent="Frentes: mostra a pergunta, resumo e tabela Antes, Depois, Diferença e Evolução.";
        }else if(type==="order_suggestion"){
            if(evidenceOptions)evidenceOptions.style.display="none";
            if(chartSel.value==="image_gallery")chartSel.value="table";
            if(dimension)dimension.value="location_id";
            if(showTable)showTable.checked=true;
            if(typeHint)typeHint.textContent="Sugestão de encomenda: mostra caixas, unidades totais, produto e observações, sem misturar com a evolução de frentes.";
        }else{
            if(evidenceOptions)evidenceOptions.style.display="none";
            if(typeHint)typeHint.textContent="Gráfico, tabela ou KPI para a pergunta selecionada.";
        }
    }
    if(formSel){formSel.addEventListener("change",function(){renderQuestions();adaptWidgetType();});renderQuestions();}
    if(questionSel)questionSel.addEventListener("change",adaptWidgetType);
    if(sourceSel)sourceSel.addEventListener("change",function(){toggleMetricSource(true);});
    if(systemMetricSel)systemMetricSel.addEventListener("change",function(){applySystemMetric(true);});
    if(editConfig&&editConfig.id){
        var set=function(name,value){var el=widgetRoot.querySelector("[name="+name+"]");if(el&&value!==undefined&&value!==null)el.value=String(value);};
        set("dashboard_id",editConfig.dashboard_id||0);set("store_group_id",editConfig.store_group_id||0);set("client_id",editConfig.client_id||0);set("project_id",editConfig.project_id||0);set("route_id",editConfig.route_id||0);
        set("metric_source",editConfig.metric_source||"question");
        toggleMetricSource(false);
        if(isSystemSource()){set("system_metric_key",editConfig.system_metric_key||"");applySystemMetric(false);}else{set("form_id",editConfig.form_id||0);renderQuestions();set("question_key",editConfig.question_key||"");adaptWidgetType();}
        set("metric_key",editConfig.metric_key||"");set("metric_label",editConfig.metric_label||"");set("chart_type",editConfig.chart_type||"bar");set("aggregation",editConfig.aggregation||"latest");set("dimension",editConfig.dimension||"location_id");set("secondary_dimension",editConfig.secondary_dimension||"");set("scope_mode",editConfig.scope_mode||"client_project_location");set("sort_order",editConfig.sort_order||10);set("evidence_grouping",editConfig.evidence_grouping||"location_date");
        [["show_kpi",editConfig.show_kpi],["show_table",editConfig.show_table],["show_empty",editConfig.show_empty],["is_active",editConfig.is_active],["evidence_show_thumbnails",editConfig.evidence_show_thumbnails]].forEach(function(pair){var el=widgetRoot.querySelector("[name="+pair[0]+"][type=checkbox]");if(el)el.checked=String(pair[1])==="1";});
        document.getElementById("ff-analytics-widget-editor")?.scrollIntoView({behavior:"smooth",block:"start"});
    }else{toggleMetricSource(false);adaptWidgetType();}
    var storeSelect=document.querySelector("[data-ff-store-group-select]");
    if(storeSelect){
        var card=storeSelect.closest(".routespro-card")||document;
        var search=card.querySelector("[data-ff-store-group-search]");
        var count=card.querySelector("[data-ff-store-count]");
        function selectedCount(){if(count)count.textContent=Array.from(storeSelect.options).filter(function(o){return o.selected;}).length+" selecionadas";}
        function filterStores(){
            var query=(search&&search.value?search.value:"").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g,"");
            var client=card.querySelector("[data-ff-role=client]"),project=card.querySelector("[data-ff-role=project]");
            var clientId=client?String(client.value||"0"):"0",projectId=project?String(project.value||"0"):"0";
            Array.from(storeSelect.options).forEach(function(option){
                var visible=(!query||String(option.dataset.search||"").indexOf(query)!==-1)&&(clientId==="0"||String(option.dataset.clientId||"0")===clientId)&&(projectId==="0"||String(option.dataset.projectId||"0")===projectId);
                option.hidden=!visible;
            });
        }
        if(search)search.addEventListener("input",filterStores);
        card.querySelectorAll("[data-ff-role=client],[data-ff-role=project]").forEach(function(el){el.addEventListener("change",filterStores);});
        document.querySelectorAll("[data-ff-store-select]").forEach(function(button){button.addEventListener("click",function(){var mode=button.dataset.ffStoreSelect;Array.from(storeSelect.options).forEach(function(option){if(mode==="clear")option.selected=false;else if(!option.hidden)option.selected=true;});selectedCount();});});
        storeSelect.addEventListener("change",selectedCount);filterStores();selectedCount();
    }
})();</script>
JS;
            $analytics_builder_script = str_replace('__FF_QUESTION_MAP__', $analytics_question_map_json ?: '{}', $analytics_builder_script);
            echo str_replace('__FF_EDIT_CONFIG__', wp_json_encode($analytics_edit_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', $analytics_builder_script);

            $analytics_dashboard_widget_counts = [];
            foreach ($analytics_bindings as $binding_row) {
                if (empty($binding_row['is_active'])) continue;
                $binding_settings = json_decode((string)($binding_row['settings_json'] ?? ''), true); if (!is_array($binding_settings)) $binding_settings = [];
                $binding_dashboard_id = (int)($binding_settings['dashboard_id'] ?? 0);
                $analytics_dashboard_widget_counts[$binding_dashboard_id] = (int)($analytics_dashboard_widget_counts[$binding_dashboard_id] ?? 0) + 1;
            }
            echo '<h3 style="margin-top:28px">Dashboards existentes</h3><p class="ff-note">A ordem desta lista é a ordem publicada no portal do cliente, no FieldFlow App e nos consumos Analytics usados pelo FlutterFlow.</p><div class="ff-table-scroll"><table class="widefat striped ff-table ff-dashboard-list-table"><thead><tr><th>ID</th><th>Dashboard</th><th>Âmbito</th><th>Layout e público</th><th>Estado</th><th>Ação</th></tr></thead><tbody>';
            if (!$analytics_dashboards) echo '<tr><td colspan="6">Sem dashboards configurados.</td></tr>';
            foreach ($analytics_dashboards as $dash) {
                $dashboard_widget_count = (int)($analytics_dashboard_widget_counts[(int)$dash['id']] ?? 0);
                $dash_widget_settings = WidgetOrchestrator::settingsForDashboard($dash);
                $dash_widget_mode_label = ['automatic'=>'Automático','assisted'=>'Assistido','manual'=>'Manual'][$dash_widget_settings['mode']] ?? 'Automático';
                echo '<tr><td>' . (int)$dash['id'] . '</td><td><strong>' . esc_html($dash['title']) . '</strong>' . (!empty($dash['ai_enabled']) ? ' <span class="ff-ai-badge">IA</span>' : '') . '<br><span class="ff-note">' . esc_html(wp_strip_all_tags((string)$dash['description'])) . '</span><br><span class="ff-dashboard-widget-count">' . $dashboard_widget_count . ' widget' . ($dashboard_widget_count === 1 ? '' : 's') . ' · Widget Intelligence: ' . esc_html($dash_widget_mode_label) . '</span></td><td>Cliente #' . (int)$dash['client_id'] . ' | Projeto #' . (int)$dash['project_id'] . ' | Rota #' . (int)$dash['route_id'] . '</td><td>' . esc_html($dash['layout_type']) . ' · ordem ' . (int)$dash['sort_order'] . '<br><span class="ff-dashboard-audience">' . esc_html(self::analytics_visibility_label((string)($dash['visibility'] ?? 'client_portal'))) . '</span><br><span class="ff-note">Máx. ' . (int)$dash_widget_settings['max_client'] . ' cliente · ' . (int)$dash_widget_settings['max_merchandiser'] . ' merch.</span></td><td>' . ((int)$dash['is_active'] ? 'Ativo' : 'Inativo') . '</td><td><div class="ff-dashboard-actions"><a class="button button-small button-primary" href="' . esc_url(add_query_arg(['page'=>'routespro-assignments-hub','tab'=>'analytics','edit_analytics_dashboard'=>(int)$dash['id']], admin_url('admin.php'))) . '#ff-analytics-dashboard-editor">Editar e gerir</a><form method="post" style="margin:0">';
                wp_nonce_field('routespro_assignment_hub_move_analytics_dashboard');
                echo '<input type="hidden" name="routespro_assignment_hub_action" value="move_analytics_dashboard"><input type="hidden" name="analytics_dashboard_id" value="' . (int)$dash['id'] . '"><button type="submit" name="move_direction" value="up" class="button button-small">Subir</button><button type="submit" name="move_direction" value="down" class="button button-small">Descer</button></form><form method="post" style="margin:0">';
                wp_nonce_field('routespro_assignment_hub_delete_analytics_dashboard');
                echo '<input type="hidden" name="routespro_assignment_hub_action" value="delete_analytics_dashboard"><input type="hidden" name="analytics_dashboard_id" value="' . (int)$dash['id'] . '"><button class="button button-small" onclick="return confirm(\'Remover este dashboard? Os widgets serão preservados no dashboard automático.\')">Apagar</button></form></div></td></tr>';
            }
            echo '</tbody></table></div>';

            echo '<h3 style="margin-top:28px">Grupos de lojas</h3><div class="ff-table-scroll"><table class="widefat striped ff-table ff-dashboard-group-table"><thead><tr><th>ID</th><th>Grupo</th><th>Âmbito</th><th>Lojas</th><th>Ação</th></tr></thead><tbody>';
            if (!$analytics_groups) echo '<tr><td colspan="5">Sem grupos de lojas.</td></tr>';
            foreach ($analytics_groups as $grp) {
                echo '<tr><td>' . (int)$grp['id'] . '</td><td><strong>' . esc_html($grp['name']) . '</strong><br><span class="ff-note">' . esc_html($grp['group_type']) . '</span></td><td>Cliente #' . (int)$grp['client_id'] . ' | Projeto #' . (int)$grp['project_id'] . '</td><td>' . (int)$grp['locations_count'] . '</td><td><form method="post" style="margin:0">';
                wp_nonce_field('routespro_assignment_hub_delete_analytics_store_group');
                echo '<input type="hidden" name="routespro_assignment_hub_action" value="delete_analytics_store_group"><input type="hidden" name="analytics_store_group_id" value="' . (int)$grp['id'] . '"><button class="button button-small" onclick="return confirm(\'Remover grupo de lojas?\')">Apagar</button></form></td></tr>';
            }
            echo '</tbody></table></div>';

            echo '<div style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap;margin-top:28px"><h3 style="margin:0">Widgets existentes</h3><div style="display:flex;gap:8px;flex-wrap:wrap">';
            echo '<form method="post" style="margin:0">';
            wp_nonce_field('routespro_assignment_hub_reconcile_evergreen_briefing_analytics');
            echo '<input type="hidden" name="routespro_assignment_hub_action" value="reconcile_evergreen_briefing_analytics"><input type="hidden" name="client_id" value="' . (int)$selected_client_id . '"><input type="hidden" name="project_id" value="' . (int)$selected_project_id . '"><button class="button button-primary" onclick="return confirm(&quot;Otimizar o dashboard de briefing? As perguntas pontuais, os duplicados antigos e as evidências operacionais serão consolidados. Nenhum histórico será apagado.&quot;)">Otimizar dashboard</button></form>';
            $toggle_args = ['page'=>'routespro-assignments-hub','tab'=>'analytics'];
            if ($selected_client_id) $toggle_args['client_id'] = $selected_client_id;
            if ($selected_project_id) $toggle_args['project_id'] = $selected_project_id;
            if (!$show_inactive_analytics) $toggle_args['show_inactive_analytics'] = 1;
            echo '<a class="button" href="' . esc_url(add_query_arg($toggle_args, admin_url('admin.php'))) . '">' . ($show_inactive_analytics ? 'Ocultar inativos / legados' : 'Mostrar inativos / legados') . '</a></div></div>';
            echo '<p class="ff-note">Por defeito aparecem apenas widgets ativos. Perguntas pontuais entram nos widgets dinâmicos Briefing operacional e Evidências do briefing. Métricas estratégicas são agrupadas por significado de negócio, nunca apenas pelo tipo técnico da pergunta, e as fotografias operacionais permanentes entram em Evidências operacionais. O histórico antigo fica disponível como inativo / legado.</p><div class="ff-table-scroll"><table class="widefat striped ff-table ff-dashboard-widget-list-table"><thead><tr><th>ID / Ordem</th><th>Dashboard</th><th>Formulário</th><th>Pergunta</th><th>Métrica</th><th>Visualização</th><th>Scope</th><th>Ação</th></tr></thead><tbody>';
            if (!$analytics_bindings) {
                echo '<tr><td colspan="8">Sem widgets analíticos.</td></tr>';
            } else {
                $dashNames=[]; foreach($analytics_dashboards as $d){ $dashNames[(int)$d['id']]=$d['title']; }
                $grpNames=[]; foreach($analytics_groups as $g){ $grpNames[(int)$g['id']]=$g['name']; }
                foreach ($analytics_bindings as $row) {
                    $scopeSettings = json_decode((string)($row['settings_json'] ?? '{}'), true); if(!is_array($scopeSettings)) $scopeSettings=[];
                    $isSystemMetric = RouteMobilityWidgetService::isSystemSettings($scopeSettings);
                    $systemMetricCatalog = $isSystemMetric ? RouteMobilityWidgetService::catalog() : [];
                    $systemMetricKey = $isSystemMetric ? sanitize_key((string)($scopeSettings['system_metric_key'] ?? '')) : '';
                    $systemMetricDefinition = $isSystemMetric ? (array)($systemMetricCatalog[$systemMetricKey] ?? []) : [];
                    $isSmartRollup = QuestionAnalyticsService::isSmartRollupBinding($row, $scopeSettings);
                    $smartRollupKind = sanitize_key((string)($scopeSettings['briefing_rollup_kind'] ?? ''));
                    $isSmartEvidenceRollup = $isSmartRollup && in_array($smartRollupKind, ['evidence','operational_evidence'], true);
                    $isOperationalEvidenceRollup = $isSmartRollup && $smartRollupKind === 'operational_evidence';
                    $scopeLabel = [];
                    if (!empty($scopeSettings['client_id'])) $scopeLabel[] = 'Cliente #' . (int)$scopeSettings['client_id'];
                    if (!empty($scopeSettings['project_id'])) $scopeLabel[] = 'Projeto #' . (int)$scopeSettings['project_id'];
                    if (!empty($scopeSettings['route_id'])) $scopeLabel[] = 'Rota #' . (int)$scopeSettings['route_id'];
                    if (!$scopeLabel) $scopeLabel[] = 'Global';
                    $dashId=(int)($scopeSettings['dashboard_id'] ?? 0); $grpId=(int)($scopeSettings['store_group_id'] ?? 0);
                    $questionLookupKey = (int)($row['form_id'] ?? 0) . ':' . sanitize_key((string)($row['question_key'] ?? ''));
                    $questionInfo = $analytics_question_lookup[$questionLookupKey] ?? [];
                    $questionLabel = trim((string)($questionInfo['question_label'] ?? $row['question_key'] ?? 'Pergunta'));
                    if ($isSystemMetric) $questionLabel = (string)($systemMetricDefinition['label'] ?? $row['metric_label'] ?? 'Rotas e mobilidade');
                    if ($isSmartRollup) $questionLabel = (string)($row['metric_label'] ?? ($isOperationalEvidenceRollup ? 'Evidências operacionais' : ($isSmartEvidenceRollup ? 'Evidências do briefing' : 'Briefing operacional')));
                    $questionType = sanitize_key((string)($scopeSettings['question_type'] ?? $questionInfo['question_type'] ?? 'text')) ?: 'text';
                    $questionTypeLabel = $isSystemMetric
                        ? 'Rotas e mobilidade · fonte canónica'
                        : ($isSmartRollup
                        ? ($isOperationalEvidenceRollup ? 'Widget dinâmico de evidências operacionais' : ($isSmartEvidenceRollup ? 'Widget dinâmico de evidências' : 'Widget dinâmico de perguntas'))
                        : ($questionType === 'product_matrix'
                            ? 'Frentes / matriz de produtos'
                            : ($questionType === 'order_suggestion'
                                ? 'Sugestão de encomenda'
                                : (in_array($questionType, ['image_upload','file_upload','categorized_image_upload'], true) ? 'Imagem / evidência' : str_replace('_', ' ', $questionType)))));
                    $questionTypeClass = $isSystemMetric ? ' is-route' : ($isSmartRollup
                        ? ($isSmartEvidenceRollup ? ' is-image' : '')
                        : ($questionType === 'product_matrix'
                            ? ' is-fronts'
                            : ($questionType === 'order_suggestion' ? ' is-order' : (in_array($questionType, ['image_upload','file_upload','categorized_image_upload'], true) ? ' is-image' : ''))));
                    echo '<tr data-ff-widget-row="' . (int)$row['id'] . '"><td>' . (int)$row['id'] . '<br><span class="ff-note">ordem ' . (int)($scopeSettings['sort_order'] ?? 10) . '</span></td>';
                    echo '<td>' . esc_html($dashId && isset($dashNames[$dashId]) ? $dashNames[$dashId] : 'Legacy / automático') . '</td>';
                    echo '<td>' . esc_html($isSystemMetric ? 'Sistema / Rotas' : ($row['form_title'] ?: ('#' . (int)$row['form_id']))) . '</td>';
                    echo '<td><strong>' . esc_html($questionLabel) . '</strong>' . ($isSmartRollup ? '<br><span class="ff-note">Gerido automaticamente pelo FieldFlow</span>' : ($isSystemMetric ? '<br><span class="ff-note">Métrica operacional do FieldFlow</span>' : '<br><code>' . esc_html($row['question_key']) . '</code>')) . '<br><span class="ff-widget-type' . esc_attr($questionTypeClass) . '">' . esc_html($questionTypeLabel) . '</span></td>';
                    $managedQuestionCount = count(array_values(array_unique(array_filter(array_map('sanitize_key', (array)($scopeSettings['managed_question_keys'] ?? []))))));
                    $isEvergreenBriefing = !empty($scopeSettings['evergreen_briefing']);
                    $metricBadge = '';
                    if ($isSmartRollup) {
                        $rollupSourceCount = count(array_values(array_unique(array_filter(array_map('sanitize_key', (array)($scopeSettings['managed_question_keys'] ?? []))))));
                        $metricBadge = '<br><span class="ff-widget-type">' . ($isOperationalEvidenceRollup ? 'Widget dinâmico · ' . max(1, $rollupSourceCount) . ' tipo(s) reunido(s)' : 'Widget dinâmico · sem widgets individuais') . '</span>';
                    }
                    elseif ($isEvergreenBriefing) $metricBadge = '<br><span class="ff-widget-type">Métrica permanente · ' . max(1, $managedQuestionCount) . ' fonte(s)</span>';
                    echo '<td><strong>' . esc_html($row['metric_label']) . '</strong><br><span class="ff-note"><code>' . esc_html($row['metric_key']) . '</code></span>' . $metricBadge . (empty($row['is_active']) ? '<br><span class="ff-note">Inativo / legado</span>' : '') . '</td>';
                    $dimensionLabels = self::analytics_options('dimension');
                    $aggregationLabels = self::analytics_options('aggregation');
                    $isEvidenceWidget = !$isSmartRollup && (in_array($questionType, ['image_upload','file_upload','categorized_image_upload'], true) || (string)$row['chart_type'] === 'image_gallery');
                    $effectiveChart = $isSmartRollup ? ($isOperationalEvidenceRollup ? 'Evidências operacionais' : ($isSmartEvidenceRollup ? 'Evidências do briefing' : 'Briefing operacional')) : ($isEvidenceWidget ? 'image_gallery' : (string)$row['chart_type']);
                    $effectiveAggregation = $isSmartRollup ? (string)$row['aggregation'] : ($isEvidenceWidget ? 'evidence' : (string)$row['aggregation']);
                    $effectivePrimary = ($isEvidenceWidget || $isSmartRollup) ? 'location_id' : (string)$row['dimension'];
                    $primaryLabel = $dimensionLabels[$effectivePrimary] ?? $effectivePrimary;
                    $secondaryKey = $isEvidenceWidget ? 'submitted_at' : sanitize_key((string)($scopeSettings['secondary_dimension'] ?? ''));
                    $secondaryLabel = $secondaryKey !== '' ? ($dimensionLabels[$secondaryKey] ?? $secondaryKey) : '';
                    $aggregationLabel = $aggregationLabels[$effectiveAggregation] ?? $effectiveAggregation;
                    $evidenceGrouping = $isEvidenceWidget ? sanitize_key((string)($scopeSettings['evidence_grouping'] ?? 'location_date')) : sanitize_key((string)($scopeSettings['evidence_grouping'] ?? ''));
                    $groupingLabels = self::analytics_options('evidence_grouping');
                    echo '<td>' . esc_html($effectiveChart) . ' · ' . esc_html($aggregationLabel) . '<br><span class="ff-note">Dimensão: ' . esc_html($primaryLabel) . ($secondaryLabel !== '' ? ' + ' . esc_html($secondaryLabel) : '') . ($evidenceGrouping !== '' ? ' · ' . esc_html($groupingLabels[$evidenceGrouping] ?? $evidenceGrouping) : '') . ($isEvidenceWidget ? ' · composição automática' : '') . ($grpId ? ' · Grupo: ' . esc_html($grpNames[$grpId] ?? ('#'.$grpId)) : '') . '</span></td>';
                    echo '<td>' . esc_html(implode(' | ', $scopeLabel)) . '<br><span class="ff-note">' . esc_html($row['scope_mode']) . '</span></td>';
                    echo '<td><div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">';
                    if ($isSmartRollup) echo '<span class="ff-note">Gerido automaticamente</span>';
                    else echo '<a class="button button-small" href="' . esc_url(add_query_arg(['page'=>'routespro-assignments-hub','tab'=>'analytics','edit_analytics_binding'=>(int)$row['id']], admin_url('admin.php'))) . '#ff-analytics-widget-editor">Editar</a>';
                    echo '<form method="post" style="margin:0">';
                    wp_nonce_field('routespro_assignment_hub_move_analytics_binding');
                    echo '<input type="hidden" name="routespro_assignment_hub_action" value="move_analytics_binding"><input type="hidden" name="analytics_binding_id" value="' . (int)$row['id'] . '"><button type="submit" name="move_direction" value="up" class="button button-small">Subir</button><button type="submit" name="move_direction" value="down" class="button button-small">Descer</button></form>';
                    if (!$isSmartRollup) {
                        echo '<form method="post" style="margin:0">';
                        wp_nonce_field('routespro_assignment_hub_delete_analytics_binding');
                        echo '<input type="hidden" name="routespro_assignment_hub_action" value="delete_analytics_binding"><input type="hidden" name="analytics_binding_id" value="' . (int)$row['id'] . '"><button class="button button-small" onclick="return confirm(\'Remover widget analítico?\')">Apagar</button></form>';
                    }
                    echo '</div></td></tr>';
                }
            }
            echo '</tbody></table></div>';
            echo '</div>';
        }

        echo '</section></div>';
        echo '<script>(function(){
            function syncSelectVisibility(root){
                const client = root.querySelector("[data-ff-role=client]");
                const project = root.querySelector("[data-ff-role=project]");
                const route = root.querySelector("[data-ff-role=route]");
                const clientVal = client ? (client.value || "0") : "0";
                const projectVal = project ? (project.value || "0") : "0";
                if (project) {
                    Array.from(project.options).forEach(function(opt){
                        if (!opt.dataset.clientId) return;
                        const visible = clientVal === "0" || opt.dataset.clientId === clientVal;
                        opt.hidden = !visible;
                        if (!visible && opt.selected) project.value = "0";
                    });
                }
                if (route) {
                    Array.from(route.options).forEach(function(opt){
                        if (!opt.dataset.clientId && !opt.dataset.projectId) return;
                        const matchClient = clientVal === "0" || opt.dataset.clientId === clientVal;
                        const matchProject = projectVal === "0" || opt.dataset.projectId === projectVal;
                        const visible = matchClient && matchProject;
                        opt.hidden = !visible;
                        if (!visible && opt.selected) route.value = "0";
                    });
                }
            }
            function bindContext(root){
                if (!root) return;
                const client = root.querySelector("[data-ff-role=client]");
                const project = root.querySelector("[data-ff-role=project]");
                const route = root.querySelector("[data-ff-role=route]");
                syncSelectVisibility(root);
                if (client) client.addEventListener("change", function(){
                    if (project && project.selectedOptions[0] && project.selectedOptions[0].dataset.clientId && project.selectedOptions[0].dataset.clientId !== this.value) project.value = "0";
                    if (route) route.value = "0";
                    syncSelectVisibility(root);
                });
                if (project) project.addEventListener("change", function(){
                    const opt = this.selectedOptions[0];
                    if (client && opt && opt.dataset.clientId && client.value !== opt.dataset.clientId) client.value = opt.dataset.clientId;
                    if (route) route.value = "0";
                    syncSelectVisibility(root);
                });
                if (route) route.addEventListener("change", function(){
                    const opt = this.selectedOptions[0];
                    if (!opt) return;
                    if (client && opt.dataset.clientId && client.value !== opt.dataset.clientId) client.value = opt.dataset.clientId;
                    if (project && opt.dataset.projectId && project.value !== opt.dataset.projectId) project.value = opt.dataset.projectId;
                    syncSelectVisibility(root);
                });
            }
            document.querySelectorAll("[data-ff-context]").forEach(bindContext);
            const sidebar = document.querySelector(".ff-hub-sidebar form");
            if (sidebar) bindContext(sidebar);
        })();</script>';
        echo '</div>';
    }

    public static function render() {
        echo '<div class="wrap">';
        echo '<div class="notice notice-info"><p>Esta página legacy foi substituída pelo <strong>Intelligence Center</strong>.</p><p><a class="button button-primary" href="' . esc_url(self::admin_url()) . '">Abrir Intelligence Center</a></p></div>';
        echo '</div>';
    }
}
