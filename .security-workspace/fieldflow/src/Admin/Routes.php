<?php
namespace RoutesPro\Admin;

use RoutesPro\Support\GeoPT;
use RoutesPro\Support\Permissions;
use RoutesPro\Support\RouteStatus;
use RoutesPro\Services\LocationDeduplicator;
use RoutesPro\Services\MapsFactory;
use RoutesPro\Services\Planning\RouteCalculator;
use RoutesPro\Services\Planning\RoutingMatrixService;
use RoutesPro\Services\RouteExceptionService;
use RoutesPro\Services\RouteWorkspaceService;
use RoutesPro\Services\MobilityCostService;
use RoutesPro\Services\RouteEndpointResolverService;
use RoutesPro\Services\RouteEndpointProfileService;
use RoutesPro\Services\RoutingGeographyService;

class Routes {
    public static function register_hooks() {
        add_action('admin_post_routespro_export_routes', [self::class, 'handle_export']);
        add_action('wp_ajax_routespro_route_metrics_preview', [self::class, 'ajax_route_metrics_preview']);
    }

    public static function render() {
        if (!current_user_can('routespro_manage')) return;
        global $wpdb; $px = $wpdb->prefix.'routespro_';

        $clients = $wpdb->get_results("SELECT id,name FROM {$px}clients ORDER BY name ASC", ARRAY_A);
        $sel_client_id = isset($_REQUEST['client_id']) ? absint($_REQUEST['client_id']) : 0;
        $projects = $sel_client_id
            ? $wpdb->get_results($wpdb->prepare("SELECT id,name FROM {$px}projects WHERE client_id=%d ORDER BY name ASC", $sel_client_id), ARRAY_A)
            : $wpdb->get_results("SELECT id,name FROM {$px}projects ORDER BY name ASC LIMIT 200", ARRAY_A);


        if (!empty($_POST['routespro_route_restore_nonce']) && wp_verify_nonce($_POST['routespro_route_restore_nonce'], 'routespro_route_restore')) {
            $restore_route_id = absint($_POST['route_id'] ?? 0);
            $restore_revision_id = sanitize_text_field((string)($_POST['revision_id'] ?? ''));
            $restore_result = RouteWorkspaceService::restore($restore_route_id, $restore_revision_id, get_current_user_id());
            if (is_wp_error($restore_result)) {
                echo '<div class="notice notice-error"><p>' . esc_html($restore_result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="updated notice"><p>Versão restaurada com sucesso. A rota voltou a rascunho ou planeada, sem tocar nos reportes.</p></div>';
                $_GET['edit'] = $restore_route_id;
            }
        }

        if (!empty($_POST['routespro_bulk_delete_nonce']) && wp_verify_nonce($_POST['routespro_bulk_delete_nonce'], 'routespro_routes_bulk_delete')) {
            $delete_ids = isset($_POST['route_ids']) ? array_map('absint', (array) $_POST['route_ids']) : [];
            $delete_ids = array_values(array_filter($delete_ids));
            if ($delete_ids) {
                $placeholders = implode(',', array_fill(0, count($delete_ids), '%d'));
                $affected_user_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT user_id FROM (
                        SELECT owner_user_id AS user_id FROM {$px}routes WHERE id IN ({$placeholders}) AND owner_user_id>0
                        UNION
                        SELECT a.user_id FROM {$px}assignments a WHERE a.route_id IN ({$placeholders}) AND a.user_id>0
                    ) users",
                    ...array_merge($delete_ids, $delete_ids)
                )) ?: [];
                $wpdb->query($wpdb->prepare("DELETE FROM {$px}routes WHERE id IN ({$placeholders})", ...$delete_ids));
                foreach ($affected_user_ids as $uid) Permissions::clear_scope_cache((int)$uid);
                echo '<div class="updated notice"><p>' . esc_html(count($delete_ids) . ' rota(s) removida(s).') . '</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>Seleciona pelo menos uma rota para apagar.</p></div>';
            }
        }

        if (!empty($_POST['routespro_routes_nonce']) && wp_verify_nonce($_POST['routespro_routes_nonce'],'routespro_routes')) {
            $id = absint($_POST['id'] ?? 0);
            $workspace_action = sanitize_key((string)($_POST['route_workspace_action'] ?? 'save'));
            $route_is_new = $id <= 0;
            $route_before = $id > 0 ? ($wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $id), ARRAY_A) ?: null) : null;
            $data = [
                'client_id'     => absint($_POST['client_id'] ?? 0),
                'project_id'    => ($_POST['project_id'] !== '' ? absint($_POST['project_id']) : null),
                'date'          => sanitize_text_field($_POST['date'] ?? current_time('Y-m-d')),
                'status'        => $workspace_action === 'publish' ? 'planned' : ($workspace_action === 'save_draft' ? 'draft' : sanitize_text_field($_POST['status'] ?? 'draft')),
                'owner_user_id' => ($_POST['owner_user_id'] !== '' ? absint($_POST['owner_user_id']) : null),
            ];
            $posted_workspace_state = $route_before ? RouteWorkspaceService::editableState($id, $route_before) : ['editable'=>true, 'live_adjustable'=>false, 'can_plan'=>true];
            $is_live_adjustment = !empty($posted_workspace_state['live_adjustable']);
            if ($is_live_adjustment) {
                // A identidade operacional da rota fica imutável durante a execução.
                // O ajuste em direto altera apenas o segmento de lojas ainda pendente.
                $data['client_id'] = absint($route_before['client_id'] ?? 0);
                $data['project_id'] = !empty($route_before['project_id']) ? absint($route_before['project_id']) : null;
                $data['date'] = sanitize_text_field((string)($route_before['date'] ?? $data['date']));
                $data['owner_user_id'] = !empty($route_before['owner_user_id']) ? absint($route_before['owner_user_id']) : null;
                $data['status'] = sanitize_key((string)($route_before['status'] ?? 'in_progress'));
                if ($data['status'] === 'completed') $data['status'] = 'in_progress';
            }
            if ($route_is_new && !empty($data['client_id']) && class_exists('\RoutesPro\SaaS\EntitlementService')) {
                if (!\RoutesPro\SaaS\EntitlementService::clientHas((int)$data['client_id'], 'routes.create')) {
                    wp_die('A criação de rotas não está incluída no plano deste cliente.', 'FieldFlow', ['response'=>403,'back_link'=>true]);
                }
                if (class_exists('\RoutesPro\SaaS\UsageService')) {
                    $quota = \RoutesPro\SaaS\UsageService::canConsume((int)$data['client_id'], 'routes_monthly', 1);
                    if (empty($quota['allowed'])) wp_die('O limite mensal de rotas foi atingido.', 'FieldFlow', ['response'=>429,'back_link'=>true]);
                }
            }

            $existing_meta = [];
            if ($id) {
                $existing_meta_raw = $wpdb->get_var($wpdb->prepare("SELECT meta_json FROM {$px}routes WHERE id=%d LIMIT 1", $id));
                $existing_meta = json_decode((string)$existing_meta_raw, true);
                if (!is_array($existing_meta)) $existing_meta = [];
            }
            $existing_exception = is_array($existing_meta['route_exception'] ?? null) ? $existing_meta['route_exception'] : [];
            $route_exception = RouteExceptionService::normalize([
                'status' => $_POST['route_exception_status'] ?? 'none',
                'note' => $_POST['route_exception_note'] ?? '',
                'notify_client' => isset($_POST['route_exception_notify_client']) ? 1 : 0,
            ], $existing_exception, get_current_user_id());
            $force_exception_notice = !empty($_POST['route_exception_resend']);

            $commercial_meta = [
                'district' => sanitize_text_field($_POST['commercial_district'] ?? ''),
                'county' => sanitize_text_field($_POST['commercial_county'] ?? ''),
                'city' => sanitize_text_field($_POST['commercial_city'] ?? ''),
                'category_id' => absint($_POST['commercial_category_id'] ?? 0),
                'subcategory_id' => absint($_POST['commercial_subcategory_id'] ?? 0),
                'routing_country_code' => class_exists(RoutingGeographyService::class)
                    ? RoutingGeographyService::normalizeCountryCode((string)($_POST['routing_country_code'] ?? RoutingGeographyService::projectCountryCode((int)($data['project_id'] ?? 0))))
                    : strtoupper((string)($_POST['routing_country_code'] ?? 'PT')),
                'start_point' => [
                    'address' => sanitize_text_field($_POST['start_address'] ?? ''),
                    'lat' => (is_numeric($_POST['start_lat'] ?? null) ? (float) $_POST['start_lat'] : null),
                    'lng' => (is_numeric($_POST['start_lng'] ?? null) ? (float) $_POST['start_lng'] : null),
                    'provenance' => sanitize_key((string)($_POST['start_provenance'] ?? 'manual')) ?: 'manual',
                    'allow_matches_stop' => !empty($_POST['start_at_first_stop']) ? 1 : 0,
                    'country_code' => class_exists(RoutingGeographyService::class) ? RoutingGeographyService::normalizeCountryCode((string)($_POST['routing_country_code'] ?? 'PT')) : strtoupper((string)($_POST['routing_country_code'] ?? 'PT')),
                ],
                'end_point' => [
                    'address' => sanitize_text_field($_POST['end_address'] ?? ''),
                    'lat' => (is_numeric($_POST['end_lat'] ?? null) ? (float) $_POST['end_lat'] : null),
                    'lng' => (is_numeric($_POST['end_lng'] ?? null) ? (float) $_POST['end_lng'] : null),
                    'provenance' => sanitize_key((string)($_POST['end_provenance'] ?? 'manual')) ?: 'manual',
                    'allow_matches_stop' => !empty($_POST['end_at_last_stop']) ? 1 : 0,
                    'country_code' => class_exists(RoutingGeographyService::class) ? RoutingGeographyService::normalizeCountryCode((string)($_POST['routing_country_code'] ?? 'PT')) : strtoupper((string)($_POST['routing_country_code'] ?? 'PT')),
                ],
            ];
            if ($existing_meta) {
                $commercial_meta = array_replace_recursive($existing_meta, $commercial_meta);
            }
            $commercial_meta['route_exception'] = $route_exception;
            if (sanitize_key((string)($data['status'] ?? 'draft')) === 'planned' && class_exists('\RoutesPro\Services\RouteMetricsService')) {
                $integrity = \RoutesPro\Services\RouteMetricsService::endpointIntegrity((array)$commercial_meta['start_point'], (array)$commercial_meta['end_point']);
                if (empty($integrity['endpoints_routable']) && class_exists(RouteEndpointProfileService::class)) {
                    $resolvedEndpoints = RouteEndpointProfileService::resolveForRoute(
                        (int)($data['client_id'] ?? 0),
                        (int)($data['project_id'] ?? 0),
                        (int)($data['owner_user_id'] ?? 0),
                        $commercial_meta,
                        (array)$commercial_meta['start_point'],
                        (array)$commercial_meta['end_point'],
                        [],
                        [],
                        ['return_to_start'=>!empty($commercial_meta['return_to_start'])]
                    );
                    if (empty($integrity['has_start']) && !empty($resolvedEndpoints['start_point'])) $commercial_meta['start_point'] = (array)$resolvedEndpoints['start_point'];
                    if (empty($integrity['has_end']) && !empty($resolvedEndpoints['end_point'])) $commercial_meta['end_point'] = (array)$resolvedEndpoints['end_point'];
                    if (!empty($resolvedEndpoints['return_to_start'])) $commercial_meta['return_to_start'] = 1;
                    $commercial_meta['route_endpoint_profile_contract'] = RouteEndpointProfileService::CONTRACT;
                    $integrity = \RoutesPro\Services\RouteMetricsService::endpointIntegrity((array)$commercial_meta['start_point'], (array)$commercial_meta['end_point']);
                }
                if (empty($integrity['endpoints_routable'])) {
                    echo '<div class="notice notice-error"><p>Para publicar a rota, define o ponto de partida e o ponto de chegada. O FieldFlow já não usa a primeira ou a última loja como extremo implícito.</p></div>';
                    return;
                }
            }
            $data['meta_json'] = wp_json_encode($commercial_meta);
            if (!empty($_POST['save_route_defaults'])) {
                $defs = get_option('routespro_route_defaults', []);
                if (!is_array($defs)) $defs = [];
                $key = ($data['client_id'] ?: 0) . '|' . ($data['project_id'] ?: 0) . '|' . ($data['owner_user_id'] ?: 0);
                $defaultStart = class_exists(RouteEndpointResolverService::class)
                    ? RouteEndpointResolverService::resolve((array)$commercial_meta['start_point'], 'start', 'route_default', ['allow_matches_stop'=>!empty($commercial_meta['start_point']['allow_matches_stop'])])
                    : (array)$commercial_meta['start_point'];
                $defaultEnd = class_exists(RouteEndpointResolverService::class)
                    ? RouteEndpointResolverService::resolve((array)$commercial_meta['end_point'], 'end', 'route_default', ['allow_matches_stop'=>!empty($commercial_meta['end_point']['allow_matches_stop'])])
                    : (array)$commercial_meta['end_point'];
                $defs[$key] = [
                    'start_point' => class_exists(RouteEndpointResolverService::class) ? RouteEndpointResolverService::publicPoint($defaultStart) : $defaultStart,
                    'end_point' => class_exists(RouteEndpointResolverService::class) ? RouteEndpointResolverService::publicPoint($defaultEnd) : $defaultEnd,
                    'updated_at' => current_time('mysql'),
                    'endpoint_contract' => class_exists(RouteEndpointResolverService::class) ? RouteEndpointResolverService::CONTRACT : '',
                ];
                update_option('routespro_route_defaults', $defs, false);
                if (class_exists(RouteEndpointProfileService::class) && !empty($data['project_id'])) {
                    $endpointProfile = [
                        'enabled' => 1,
                        'start_point' => $defs[$key]['start_point'],
                        'end_point' => $defs[$key]['end_point'],
                        'return_to_start' => !empty($commercial_meta['return_to_start']) ? 1 : 0,
                    ];
                    if (!empty($data['owner_user_id'])) RouteEndpointProfileService::saveProjectUserProfile((int)$data['project_id'], (int)$data['owner_user_id'], $endpointProfile, get_current_user_id());
                    else RouteEndpointProfileService::saveProjectDefault((int)$data['project_id'], $endpointProfile, get_current_user_id());
                }
            }

            $workspace_snapshot = null;
            if ($id > 0) {
                $workspace_snapshot = RouteWorkspaceService::captureCurrent($id);
                if (is_wp_error($workspace_snapshot)) $workspace_snapshot = null;
            }

            if ($wpdb->query('START TRANSACTION') === false) {
                echo '<div class="notice notice-error"><p>Não foi possível iniciar a gravação segura da rota.</p></div>';
                return;
            }
            if ($id) {
                $route_write = $wpdb->update($px.'routes', $data, ['id'=>$id]);
            } else {
                $route_write = $wpdb->insert($px.'routes', $data);
                $id = (int) $wpdb->insert_id;
            }
            if ($route_write === false || $id <= 0) {
                $wpdb->query('ROLLBACK');
                echo '<div class="notice notice-error"><p>' . esc_html('Não foi possível guardar a rota: ' . ($wpdb->last_error ?: 'falha de base de dados')) . '</p></div>';
                return;
            }
            if ($id) {
                \RoutesPro\Support\AssignmentMatrix::sync_route_owner_assignment((int)$id, (int)($data['owner_user_id'] ?? 0), 'owner');
            }

            $raw_points = wp_unslash($_POST['route_points_json'] ?? '[]');
            $points = json_decode($raw_points, true);
            if (!is_array($points)) $points = [];
            $manual_order_locked = !empty($_POST['manual_order_locked']);
            if ($is_live_adjustment) $manual_order_locked = true;
            $position_locked_location_ids = [];
            foreach ($points as &$workspace_point) {
                if (!is_array($workspace_point)) continue;
                if ($manual_order_locked || !empty($workspace_point['position_locked']) || !empty($workspace_point['workspace_preserve_order'])) {
                    $workspace_point['workspace_preserve_order'] = 1;
                }
                if (!empty($workspace_point['position_locked'])) {
                    $locked_location_id = absint($workspace_point['location_id'] ?? $workspace_point['id'] ?? 0);
                    if ($locked_location_id > 0) $position_locked_location_ids[] = $locked_location_id;
                }
            }
            unset($workspace_point);
            $commercial_meta['manual_order_locked'] = $manual_order_locked ? 1 : 0;
            $commercial_meta['position_locked_location_ids'] = array_values(array_unique($position_locked_location_ids));
            $commercial_meta['route_workspace_action'] = $workspace_action;
            if ($is_live_adjustment) {
                $commercial_meta['last_live_route_adjustment_at'] = current_time('mysql');
                $commercial_meta['last_live_route_adjustment_by'] = get_current_user_id();
            }
            if (is_array($workspace_snapshot)) {
                $revision_label = $workspace_action === 'publish' ? 'Antes de publicar' : ($workspace_action === 'save_draft' ? 'Antes de guardar rascunho' : 'Antes de guardar alterações');
                $commercial_meta = RouteWorkspaceService::appendRevision($commercial_meta, $workspace_snapshot, 'before_' . $workspace_action, get_current_user_id(), $revision_label);
            } else {
                $commercial_meta['route_workspace_version'] = RouteWorkspaceService::VERSION;
                $commercial_meta['route_workspace_last_saved_at'] = current_time('mysql');
                $commercial_meta['route_workspace_last_saved_by'] = get_current_user_id();
            }

            $schedule = self::build_route_schedule_details((string)($data['date'] ?? ''), (array)$points, (array)$commercial_meta['start_point'], (array)$commercial_meta['end_point']);
            if (is_array($schedule['resolved_start_point'] ?? null)) $commercial_meta['start_point'] = self::endpoint_meta_point((array)$schedule['resolved_start_point']);
            if (is_array($schedule['resolved_end_point'] ?? null)) $commercial_meta['end_point'] = self::endpoint_meta_point((array)$schedule['resolved_end_point']);
            if (sanitize_key((string)($data['status'] ?? 'draft')) === 'planned' && (string)($schedule['distance_status'] ?? '') !== 'complete') {
                $wpdb->query('ROLLBACK');
                $warnings = array_values(array_filter(array_map('sanitize_key', (array)($schedule['endpoint_warning_codes'] ?? []))));
                $detail = $warnings ? ' Motivo: ' . implode(', ', $warnings) . '.' : '';
                echo '<div class="notice notice-error"><p>Não foi possível publicar a rota porque a partida ou a chegada ainda não é financeiramente confiável.' . esc_html($detail) . ' Confirma os extremos reais ou assinala explicitamente quando a rota começa no primeiro PDV ou termina no último PDV.</p></div>';
                return;
            }
            $commercial_meta['route_metrics'] = class_exists('\RoutesPro\Services\RouteMetricsService')
                ? \RoutesPro\Services\RouteMetricsService::canonicalFromSchedule($schedule)
                : $schedule;
            $commercial_meta['plan_summary'] = $commercial_meta['route_metrics'];
            $refresh_mobility_snapshot = empty($commercial_meta['mobility_cost_snapshot']['profile']);
            if (!$route_is_new && is_array($route_before)) {
                $before_status = (string)($route_before['status'] ?? 'draft');
                $after_status = (string)($data['status'] ?? $before_status);
                $identity_changed = absint($route_before['project_id'] ?? 0) !== absint($data['project_id'] ?? 0)
                    || absint($route_before['owner_user_id'] ?? 0) !== absint($data['owner_user_id'] ?? 0)
                    || (string)($route_before['date'] ?? '') !== (string)($data['date'] ?? '');
                if ($identity_changed && RouteStatus::isSnapshotMutable($before_status) && RouteStatus::isSnapshotMutable($after_status)) {
                    $refresh_mobility_snapshot = true;
                }
            }
            if ($refresh_mobility_snapshot) {
                $mobility_profile = MobilityCostService::resolveProfile((int)($data['project_id'] ?? 0), (int)($data['owner_user_id'] ?? 0), $commercial_meta, (string)($data['date'] ?? ''));
                if (!empty($mobility_profile['enabled'])) {
                    $commercial_meta['mobility_cost_snapshot'] = MobilityCostService::snapshotPayload($mobility_profile, (string)($mobility_profile['_source'] ?? ''), get_current_user_id());
                    $commercial_meta['mobility_cost_contract'] = MobilityCostService::CONTRACT;
                } elseif ($identity_changed ?? false) {
                    // A planned route changed identity and no longer resolves an active profile.
                    // Do not retain the previous operative's financial snapshot.
                    unset($commercial_meta['mobility_cost_snapshot']);
                }
            }
            $data['meta_json'] = wp_json_encode($commercial_meta);
            $wpdb->update($px.'routes', ['meta_json' => $data['meta_json']], ['id' => $id]);

            $planned_stops = [];
            $scheduleStops = array_values((array)($schedule['stops'] ?? []));
            $points = !empty($schedule['ordered_points']) && is_array($schedule['ordered_points']) ? array_values((array)$schedule['ordered_points']) : $points;
            $seq = 0;
            foreach ($points as $point) {
                if (!is_array($point)) continue;
                $location_id = absint($point['location_id'] ?? 0);
                $name = sanitize_text_field($point['name'] ?? '');
                $address = sanitize_textarea_field($point['address'] ?? '');
                $place_id = sanitize_text_field($point['place_id'] ?? '');
                $phone = sanitize_text_field($point['phone'] ?? '');
                $email = sanitize_email($point['email'] ?? '');
                $contact_person = sanitize_text_field($point['contact_person'] ?? '');
                $district = sanitize_text_field($point['district'] ?? '');
                $county = sanitize_text_field($point['county'] ?? '');
                $city = sanitize_text_field($point['city'] ?? '');
                $postal_code = sanitize_text_field($point['postal_code'] ?? '');
                $category_id = absint($point['category_id'] ?? 0) ?: ($commercial_meta['category_id'] ?: null);
                $subcategory_id = absint($point['subcategory_id'] ?? 0) ?: ($commercial_meta['subcategory_id'] ?: null);
                $source = sanitize_text_field($point['source'] ?? 'route_capture');
                $visit_time_min = max(0, (int) ($point['visit_time_min'] ?? 0));
                $visit_time_mode = sanitize_text_field((string) ($point['visit_time_mode'] ?? ''));
                $lat = is_numeric($point['lat'] ?? null) ? (float) $point['lat'] : null;
                $lng = is_numeric($point['lng'] ?? null) ? (float) $point['lng'] : null;

                $locationPayload = [
                    'client_id' => $data['client_id'],
                    'project_id' => $data['project_id'],
                    'name' => $name !== '' ? $name : $address,
                    'address' => $address,
                    'district' => $district,
                    'county' => $county,
                    'city' => $city,
                    'postal_code' => $postal_code,
                    'category_id' => $category_id,
                    'subcategory_id' => $subcategory_id,
                    'contact_person' => $contact_person,
                    'phone' => $phone,
                    'email' => $email,
                    'place_id' => $place_id,
                    'lat' => $lat,
                    'lng' => $lng,
                    'source' => $source ?: 'route_capture',
                    'location_type' => 'pdv',
                    'is_active' => 1,
                ];

                if ($location_id) {
                    LocationDeduplicator::upsert($locationPayload, 0, true);
                } elseif ($name !== '' || $address !== '') {
                    $res = LocationDeduplicator::upsert($locationPayload, 0, true);
                    $location_id = (int)($res['id'] ?? 0);
                }

                if ($location_id) {
                    if (!empty($point['position_locked'])) $position_locked_location_ids[] = $location_id;
                    $stopSchedule = $scheduleStops[$seq] ?? [];
                    $scheduledMeta = [
                        'visit_time_min' => $visit_time_min,
                        'visit_time_mode' => $visit_time_mode,
                        'distance_from_prev_km' => is_numeric($stopSchedule['distance_from_prev_km'] ?? ($point['distance_from_prev_km'] ?? null)) ? round((float)($stopSchedule['distance_from_prev_km'] ?? $point['distance_from_prev_km']), 2) : null,
                        'travel_min_from_prev' => is_numeric($stopSchedule['travel_min_from_prev'] ?? ($point['travel_min_from_prev'] ?? null)) ? round((float)($stopSchedule['travel_min_from_prev'] ?? $point['travel_min_from_prev']), 1) : null,
                        'toll_cost_eur_from_prev' => is_numeric($stopSchedule['toll_cost_eur_from_prev'] ?? ($point['toll_cost_eur_from_prev'] ?? null)) ? round((float)($stopSchedule['toll_cost_eur_from_prev'] ?? $point['toll_cost_eur_from_prev']), 2) : null,
                        'leg_status' => (string)($stopSchedule['leg_status'] ?? 'unavailable'),
                        'distance_source' => (string)($stopSchedule['distance_source'] ?? ''),
                        'toll_provider' => (string)($stopSchedule['toll_provider'] ?? ''),
                        'toll_is_real_api' => !empty($stopSchedule['toll_is_real_api']) ? 1 : 0,
                        'routing_provider' => (string)($stopSchedule['routing_provider'] ?? ''),
                        'arrival_point' => (array)($stopSchedule['arrival_point'] ?? []),
                        'departure_point' => [
                            'lat' => $lat,
                            'lng' => $lng,
                            'address' => $address,
                        ],
                        'snapshot' => [
                            'location_id' => $location_id,
                            'name' => $name !== '' ? $name : $address,
                            'address' => $address,
                            'district' => $district,
                            'county' => $county,
                            'city' => $city,
                            'postal_code' => $postal_code,
                            'lat' => $lat,
                            'lng' => $lng,
                            'place_id' => $place_id,
                        ],
                    ];
                    $planned_stops[] = [
                        'route_stop_id' => absint($point['route_stop_id'] ?? 0),
                        'location_id' => $location_id,
                        'seq' => $seq,
                        'planned_arrival' => !empty($stopSchedule['planned_arrival']) ? $stopSchedule['planned_arrival'] : null,
                        'planned_departure' => !empty($stopSchedule['planned_departure']) ? $stopSchedule['planned_departure'] : null,
                        'duration_s' => (int)($stopSchedule['duration_s'] ?? max(0, $visit_time_min * 60)),
                        'meta_json' => $scheduledMeta,
                    ];
                    $seq++;
                }
            }

            $commercial_meta['position_locked_location_ids'] = array_values(array_unique(array_filter(array_map('absint', $position_locked_location_ids))));
            $commercial_meta['store_count'] = count($planned_stops);
            $data['meta_json'] = wp_json_encode($commercial_meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $wpdb->update($px.'routes', ['meta_json' => $data['meta_json']], ['id' => $id]);

            $plan_sync = \RoutesPro\Services\RoutePlanSyncService::sync($id, $planned_stops);
            $save_error = null;
            $route_progress = null;
            if (is_wp_error($plan_sync)) {
                $save_error = $plan_sync->get_error_message();
                $wpdb->query('ROLLBACK');
            } else {
                $route_progress = \RoutesPro\Services\RouteProgressService::sync($id, true);
                if (is_wp_error($route_progress)) {
                    $save_error = $route_progress->get_error_message();
                    $wpdb->query('ROLLBACK');
                } else {
                    $wpdb->query('COMMIT');
                }
            }

            if ($save_error !== null) {
                echo '<div class="notice notice-error"><p>' . esc_html('Não foi possível guardar a rota porque as paragens não puderam ser reconciliadas: ' . $save_error) . '</p></div>';
            } else {
                $exception_notice = null;
                if (!empty($route_exception['active']) && !empty($route_exception['notify_client']) && trim((string)($route_exception['note'] ?? '')) === '') {
                    $exception_notice = [
                        'sent' => false,
                        'skipped' => 'é necessária uma nota para enviar o aviso ao cliente',
                        'recipients' => [],
                    ];
                } elseif (RouteExceptionService::should_notify((int)$id, $route_exception, $force_exception_notice)) {
                    $exception_notice = \RoutesPro\Services\Notify::route_event((int)$id, 'exception', (string)($route_exception['note'] ?? ''));
                    $route_exception = RouteExceptionService::mark_notification($route_exception, (int)$id, is_array($exception_notice) ? $exception_notice : []);
                    $commercial_meta['route_exception'] = $route_exception;
                    $wpdb->update($px.'routes', ['meta_json' => wp_json_encode($commercial_meta)], ['id' => $id]);
                }
                $route_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $id), ARRAY_A) ?: [];
                if (class_exists('\\RoutesPro\\Core\\EventBus')) {
                    $event_name = $route_is_new ? 'route.created' : 'route.updated';
                    $context = [
                        'entity_type' => 'route',
                        'entity_id' => $id,
                        'route_id' => $id,
                        'client_id' => (int)($route_after['client_id'] ?? $data['client_id'] ?? 0),
                        'project_id' => (int)($route_after['project_id'] ?? $data['project_id'] ?? 0),
                        'actor_user_id' => get_current_user_id(),
                        'source' => 'backoffice',
                    ];
                    \RoutesPro\Core\AuditLog::record($event_name, 'route', $id, $route_before, $route_after, $context, [
                        'plan_sync' => $plan_sync,
                        'route_progress' => $route_progress,
                    ]);
                    \RoutesPro\Core\EventBus::emit($event_name, \RoutesPro\Core\DataContract::envelope('route', [
                        'route' => $route_after,
                        'plan_sync' => $plan_sync,
                        'route_progress' => $route_progress,
                    ]), $context);
                }
                $summary = sprintf(
                    'Rota guardada. %d paragem(ns) preservada(s), %d nova(s), %d removida(s). Estado: %s.',
                    (int)($plan_sync['preserved'] ?? 0),
                    (int)($plan_sync['created'] ?? 0),
                    (int)($plan_sync['removed'] ?? 0),
                    (string)($route_progress['route_status'] ?? $data['status'])
                );
                if (is_array($exception_notice)) {
                    $summary .= !empty($exception_notice['sent'])
                        ? ' Aviso de ocorrência enviado para ' . count((array)($exception_notice['recipients'] ?? [])) . ' destinatário(s).'
                        : ' A ocorrência foi guardada, mas o email não foi enviado: ' . (string)($exception_notice['skipped'] ?? 'falha de envio') . '.';
                }
                echo '<div class="updated notice"><p>' . esc_html($summary) . '</p></div>';
            }
        }

        if (!empty($_GET['delete'])) {
            $del = absint($_GET['delete']); check_admin_referer('routespro_routes_del_'.$del);
            $affected_user_ids = class_exists('\RoutesPro\Support\ScopeIndex') ? \RoutesPro\Support\ScopeIndex::routeUserIds($del) : [];
            $route_before_delete = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $del), ARRAY_A) ?: [];
            $wpdb->delete($px.'routes', ['id'=>$del]);
            foreach ($affected_user_ids as $uid) Permissions::clear_scope_cache((int)$uid);
            if ($route_before_delete && class_exists('\\RoutesPro\\Core\\EventBus')) {
                $context = [
                    'entity_type' => 'route',
                    'entity_id' => $del,
                    'route_id' => $del,
                    'client_id' => (int)($route_before_delete['client_id'] ?? 0),
                    'project_id' => (int)($route_before_delete['project_id'] ?? 0),
                    'actor_user_id' => get_current_user_id(),
                    'source' => 'backoffice',
                ];
                \RoutesPro\Core\AuditLog::record('route.deleted', 'route', $del, $route_before_delete, null, $context);
                \RoutesPro\Core\EventBus::emit('route.deleted', \RoutesPro\Core\DataContract::envelope('route', $route_before_delete), $context);
            }
            echo '<div class="updated notice"><p>Rota removida.</p></div>';
        }

        $edit = null;
        $route_meta = [];
        $route_points = [];
        if (!empty($_GET['edit'])) {
            $id = absint($_GET['edit']);
            $edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d", $id), ARRAY_A);
            if ($edit) {
                $sel_client_id = (int)$edit['client_id'];
                $projects = $wpdb->get_results($wpdb->prepare("SELECT id,name FROM {$px}projects WHERE client_id=%d ORDER BY name ASC", $sel_client_id), ARRAY_A);
                if (!empty($edit['meta_json'])) {
                    $decoded = json_decode((string)$edit['meta_json'], true);
                    if (is_array($decoded)) $route_meta = $decoded;
                }
                $route_points = $wpdb->get_results($wpdb->prepare(
                    "SELECT rs.id AS route_stop_id, rs.seq, rs.status, rs.arrived_at, rs.departed_at, rs.planned_arrival, rs.planned_departure, rs.duration_s, rs.meta_json AS stop_meta_json, l.id AS location_id, l.name, l.address, l.phone, l.email, l.contact_person, l.district, l.county, l.city, l.postal_code, l.category_id, l.subcategory_id, l.place_id, l.lat, l.lng, l.source
                     FROM {$px}route_stops rs
                     INNER JOIN {$px}locations l ON l.id=rs.location_id
                     WHERE rs.route_id=%d ORDER BY rs.seq ASC, rs.id ASC", $id), ARRAY_A);
                $snapshot_map = [];
                if ($route_points) {
                    $route_stop_ids = array_values(array_filter(array_map(function($row){ return (int)($row['route_stop_id'] ?? 0); }, $route_points)));
                    if ($route_stop_ids) {
                        $placeholders = implode(',', array_fill(0, count($route_stop_ids), '%d'));
                        $snapshot_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$px}route_location_snapshot WHERE route_id=%d AND route_stop_id IN ({$placeholders}) ORDER BY id DESC", array_merge([$id], $route_stop_ids)), ARRAY_A) ?: [];
                        foreach ($snapshot_rows as $snapshot_row) {
                            $sid = (int)($snapshot_row['route_stop_id'] ?? 0);
                            if ($sid > 0 && empty($snapshot_map[$sid])) $snapshot_map[$sid] = $snapshot_row;
                        }
                    }
                    $position_locked_set = array_fill_keys(array_values(array_filter(array_map('absint', (array)($route_meta['position_locked_location_ids'] ?? [])))), true);
                    foreach ($route_points as &$route_point) {
                        $stop_meta = !empty($route_point['stop_meta_json']) ? json_decode((string) $route_point['stop_meta_json'], true) : [];
                        $route_point['position_locked'] = !empty($position_locked_set[(int)($route_point['location_id'] ?? 0)]) ? 1 : 0;
                        $route_point['workspace_preserve_order'] = !empty($route_meta['manual_order_locked']) || !empty($route_point['position_locked']) ? 1 : 0;
                        $snapshot = $snapshot_map[(int)($route_point['route_stop_id'] ?? 0)] ?? [];
                        if (is_array($stop_meta)) {
                            $route_point['visit_time_min'] = max(0, (int) ($stop_meta['visit_time_min'] ?? 0));
                            $route_point['visit_time_mode'] = sanitize_text_field((string) ($stop_meta['visit_time_mode'] ?? ''));
                            $route_point['distance_from_prev_km'] = round((float)($stop_meta['distance_from_prev_km'] ?? 0), 2);
                            $route_point['travel_min_from_prev'] = (int)round((float)($stop_meta['travel_min_from_prev'] ?? 0));
                            $metaSnapshot = is_array($stop_meta['snapshot'] ?? null) ? $stop_meta['snapshot'] : [];
                            if ((!is_numeric($route_point['lat'] ?? null) || !is_numeric($route_point['lng'] ?? null)) && is_numeric($metaSnapshot['lat'] ?? null) && is_numeric($metaSnapshot['lng'] ?? null)) {
                                $route_point['lat'] = (float)$metaSnapshot['lat'];
                                $route_point['lng'] = (float)$metaSnapshot['lng'];
                            }
                            foreach (['name','address','district','county','city','postal_code','place_id'] as $field) {
                                if (empty($route_point[$field]) && !empty($metaSnapshot[$field])) $route_point[$field] = $metaSnapshot[$field];
                            }
                        }
                        if ((!is_numeric($route_point['lat'] ?? null) || !is_numeric($route_point['lng'] ?? null)) && is_numeric($snapshot['lat'] ?? null) && is_numeric($snapshot['lng'] ?? null)) {
                            $route_point['lat'] = (float)$snapshot['lat'];
                            $route_point['lng'] = (float)$snapshot['lng'];
                        }
                        foreach (['name','address','district','county','city','postal_code','place_id','phone','email','contact_person'] as $field) {
                            if (empty($route_point[$field]) && !empty($snapshot[$field])) $route_point[$field] = $snapshot[$field];
                        }
                        unset($route_point['stop_meta_json']);
                    }
                    unset($route_point);
                }
            }
        }

        $default_client_id = (int)($edit['client_id'] ?? $sel_client_id ?: 0);
        $default_project_id = (int)($edit['project_id'] ?? ($_REQUEST['project_id'] ?? 0));
        $default_owner_id = (int)($edit['owner_user_id'] ?? ($_REQUEST['owner_user_id'] ?? 0));
        $route_defaults = [];
        if (class_exists(RouteEndpointProfileService::class) && $default_project_id > 0) {
            $endpointProfile = RouteEndpointProfileService::effectiveProfile($default_project_id, $default_owner_id, $default_client_id);
            if (!empty($endpointProfile['enabled'])) {
                $route_defaults = [
                    'start_point' => (array)$endpointProfile['start_point'],
                    'end_point' => (array)$endpointProfile['end_point'],
                    'return_to_start' => !empty($endpointProfile['return_to_start']) ? 1 : 0,
                    'route_endpoint_profile_source' => (string)($endpointProfile['_source'] ?? 'profile'),
                    'route_endpoint_profile_contract' => RouteEndpointProfileService::CONTRACT,
                ];
            }
        }
        $route_meta = array_replace_recursive($route_defaults, $route_meta);
        $routeCountryCode = class_exists(RoutingGeographyService::class)
            ? RoutingGeographyService::normalizeCountryCode((string)($route_meta['routing_country_code'] ?? RoutingGeographyService::projectCountryCode($default_project_id)))
            : strtoupper((string)($route_meta['routing_country_code'] ?? 'PT'));
        $route_meta['routing_country_code'] = $routeCountryCode;
        $startPoint = is_array($route_meta['start_point'] ?? null) ? $route_meta['start_point'] : ['address'=>'','lat'=>'','lng'=>''];
        $endPoint = is_array($route_meta['end_point'] ?? null) ? $route_meta['end_point'] : ['address'=>'','lat'=>'','lng'=>''];
        $savedRouteMetrics = is_array($route_meta['route_metrics'] ?? null) ? (array)$route_meta['route_metrics'] : (is_array($route_meta['plan_summary'] ?? null) ? (array)$route_meta['plan_summary'] : []);
        $routeWorkspaceHistory = RouteWorkspaceService::historyFromMeta($route_meta);
        $routeWorkspaceState = $edit ? RouteWorkspaceService::editableState((int)$edit['id'], $edit) : ['editable' => true, 'live_adjustable'=>false, 'can_plan'=>true, 'can_restore'=>true, 'mode'=>'full', 'reason' => '', 'status' => 'new'];
        $selected_client_id = (int)($edit['client_id'] ?? $sel_client_id ?: 0);
        $selected_project_id = (int)($edit['project_id'] ?? 0);
        $users = Permissions::get_assignable_users($selected_client_id, $selected_project_id, ['ID','display_name','user_email','user_login']);
        $selected_owner_id = (int)($edit['owner_user_id'] ?? 0);
        $savedMobility = MobilityCostService::calculateForRouteArray(array_merge(is_array($edit) ? $edit : [], [
            'project_id' => $selected_project_id,
            'owner_user_id' => $selected_owner_id,
            'date' => (string)($edit['date'] ?? current_time('Y-m-d')),
            'meta_decoded' => $route_meta,
            'metrics' => $savedRouteMetrics,
        ]));
        if ($selected_owner_id && !array_filter($users, fn($u) => (int)$u->ID === $selected_owner_id)) {
            $owner_user = get_user_by('id', $selected_owner_id);
            if ($owner_user) $users[] = $owner_user;
        }
        $rows = $wpdb->get_results("SELECT r.*, c.name as client_name, p.name as project_name, owner.display_name AS owner_name, (SELECT COUNT(*) FROM {$px}route_stops rs_count WHERE rs_count.route_id=r.id) AS stops_count
            FROM {$px}routes r
            LEFT JOIN {$px}clients c ON c.id=r.client_id
            LEFT JOIN {$px}projects p ON p.id=r.project_id
            LEFT JOIN {$wpdb->users} owner ON owner.ID=r.owner_user_id
            ORDER BY r.date DESC, r.id DESC
            LIMIT 500", ARRAY_A);
        // 9BS: project the same mobility authority used by canonical reporting into
        // the BO route list. Force id=0 here so list rendering never triggers a
        // per-route stored-leg query or an external routing request for up to 500 rows.
        foreach ($rows as &$routeListRow) {
            $routeListMeta = json_decode((string)($routeListRow['meta_json'] ?? ''), true);
            if (!is_array($routeListMeta)) $routeListMeta = [];
            $routeListCalc = MobilityCostService::calculateForRouteArray(array_merge($routeListRow, [
                'id' => 0,
                'route_id' => 0,
                'meta_decoded' => $routeListMeta,
            ]));
            $routeListProjection = MobilityCostService::reportingProjection($routeListCalc);
            $routeListMetrics = is_array($routeListMeta['route_metrics'] ?? null)
                ? (array)$routeListMeta['route_metrics']
                : (is_array($routeListMeta['plan_summary'] ?? null) ? (array)$routeListMeta['plan_summary'] : []);
            $routeListDistance = is_numeric($routeListProjection['reporting_distance_km'] ?? null)
                ? round((float)$routeListProjection['reporting_distance_km'], 2)
                : null;
            $routeListTravel = is_numeric($routeListMetrics['travel_min'] ?? null)
                ? round((float)$routeListMetrics['travel_min'], 1)
                : (is_numeric($routeListMetrics['known_travel_min'] ?? null) ? round((float)$routeListMetrics['known_travel_min'], 1) : null);
            $routeListCostsAvailable = !empty($routeListProjection['reporting_costs_available']);
            $pickProjected = static function(array $projection, string $key) {
                $reportingKey = 'reporting_' . $key;
                if (is_numeric($projection[$reportingKey] ?? null)) return round((float)$projection[$reportingKey], 2);
                if (is_numeric($projection[$key] ?? null)) return round((float)$projection[$key], 2);
                return null;
            };
            $routeListRow['_ff_financial'] = [
                'distance_km' => $routeListDistance,
                'travel_min' => $routeListTravel,
                'costs_available' => $routeListCostsAvailable ? 1 : 0,
                'energy_cost_eur' => $routeListCostsAvailable ? $pickProjected($routeListProjection, 'energy_cost_eur') : null,
                'toll_cost_eur' => $routeListCostsAvailable ? $pickProjected($routeListProjection, 'toll_cost_eur') : null,
                'total_mobility_cost_eur' => $routeListCostsAvailable ? $pickProjected($routeListProjection, 'total_mobility_cost_eur') : null,
                'quality' => sanitize_key((string)($routeListProjection['reporting_quality'] ?? 'unavailable')),
                'source' => sanitize_text_field((string)($routeListProjection['distance_value_source'] ?? $routeListProjection['known_distance_value_source'] ?? '')),
                'recovered' => !empty($routeListProjection['reporting_recovered']) ? 1 : 0,
            ];
        }
        unset($routeListRow);

        $assignedRoutes = [];
        foreach ($rows as $r) {
            $rid = (int)($r['id'] ?? 0);
            $exportBase = admin_url('admin-post.php?action=routespro_export_routes&route_id=' . $rid);
            $assignedRoutes[] = [
                'id' => $rid,
                'client_id' => (int)($r['client_id'] ?? 0),
                'project_id' => (int)($r['project_id'] ?? 0),
                'owner_user_id' => (int)($r['owner_user_id'] ?? 0),
                'date' => (string)($r['date'] ?? ''),
                'status' => (string)($r['status'] ?? ''),
                'client_name' => (string)($r['client_name'] ?? ''),
                'project_name' => (string)($r['project_name'] ?? ''),
                'owner_name' => (string)($r['owner_name'] ?? ''),
                'stops_count' => (int)($r['stops_count'] ?? 0),
                'edit_url' => admin_url('admin.php?page=routespro-routes&edit=' . $rid . '&client_id=' . (int)($r['client_id'] ?? 0)),
                'csv_url' => wp_nonce_url(add_query_arg(['format' => 'csv'], $exportBase), 'routespro_export_routes'),
                'xls_url' => wp_nonce_url(add_query_arg(['format' => 'xls'], $exportBase), 'routespro_export_routes'),
                'pdf_url' => wp_nonce_url(add_query_arg(['format' => 'pdf'], $exportBase), 'routespro_export_routes'),
            ];
        }

        $categories = $wpdb->get_results("SELECT id,name,parent_id FROM {$px}categories WHERE is_active=1 ORDER BY sort_order ASC, name ASC", ARRAY_A);
        $categorySeen = [];
        $categories = array_values(array_filter($categories, function($c) use (&$categorySeen){
            $key = strtolower(trim((string)($c['name'] ?? ''))) . '|' . (int)($c['parent_id'] ?? 0);
            if ($key === '|0') return false;
            if (isset($categorySeen[$key])) return false;
            $categorySeen[$key] = true;
            return true;
        }));
        $commercialRoots = array_values(array_filter($categories, fn($c) => empty($c['parent_id'])));
        $commercialChildren = array_values(array_filter($categories, fn($c) => !empty($c['parent_id'])));
        $commercialChildrenByParent = [];
        foreach ($commercialChildren as $child) {
            $parentId = (int) ($child['parent_id'] ?? 0);
            if (!$parentId) continue;
            $childName = trim((string) ($child['name'] ?? ''));
            if ($childName === '') continue;
            $childKey = sanitize_title($childName);
            if (!isset($commercialChildrenByParent[$parentId])) $commercialChildrenByParent[$parentId] = [];
            if (!isset($commercialChildrenByParent[$parentId][$childKey])) {
                $commercialChildrenByParent[$parentId][$childKey] = [
                    'id' => (int) ($child['id'] ?? 0),
                    'parent_id' => $parentId,
                    'name' => $childName,
                ];
            }
        }
        foreach ($commercialChildrenByParent as $parentId => $items) {
            uasort($items, fn($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
            $commercialChildrenByParent[$parentId] = array_values($items);
        }
        $rpDistricts = GeoPT::districts();
        $rpCounties = GeoPT::all_counties();
        $rpCities = GeoPT::all_cities();
        $rpCountiesByDistrict = GeoPT::counties_by_district();
        $rpCitiesByDistrict = GeoPT::cities_by_district();
        $settings = Settings::get();
        $gmKey = trim((string)($settings['google_maps_key'] ?? ''));
        $mapsProvider = trim((string)($settings['maps_provider'] ?? 'leaflet'));
        $routeEndpointConfigs = [];
        if (class_exists(RouteEndpointProfileService::class)) {
            foreach ((array)$projects as $projectRow) {
                $pid = absint($projectRow['id'] ?? 0);
                if ($pid <= 0) continue;
                $cfg = RouteEndpointProfileService::getProjectConfig($pid);
                $routeEndpointConfigs[(string)$pid] = [
                    'default' => (array)($cfg['default'] ?? []),
                    'profiles' => (array)($cfg['profiles'] ?? []),
                    'contract' => RouteEndpointProfileService::CONTRACT,
                    'routing_country_code' => class_exists(RoutingGeographyService::class) ? RoutingGeographyService::projectCountryCode($pid) : 'PT',
                ];
            }
        }
        $campaignAddressOptions = [];
        $projectAddresses = $wpdb->get_results("SELECT cl.project_id, l.name, l.address, l.lat, l.lng FROM {$px}campaign_locations cl INNER JOIN {$px}locations l ON l.id=cl.location_id WHERE cl.is_active=1 AND l.is_active=1 ORDER BY cl.project_id ASC, l.name ASC", ARRAY_A) ?: [];
        foreach ($projectAddresses as $addrRow) {
            $pid = (int) ($addrRow['project_id'] ?? 0);
            if (!$pid) continue;
            if (!isset($campaignAddressOptions[$pid])) $campaignAddressOptions[$pid] = [];
            $label = trim((string)($addrRow['name'] ?? ''));
            $address = trim((string)($addrRow['address'] ?? ''));
            $textAddr = trim($label . ($address ? ' · ' . $address : ''));
            if ($textAddr === '') continue;
            $campaignAddressOptions[$pid][] = ['label'=>$textAddr,'name'=>$label,'address'=>$address,'lat'=>$addrRow['lat'],'lng'=>$addrRow['lng']];
        }

        echo '<div class="wrap routespro-routes-premium">';
        Branding::render_header('Rotas', 'Descobre PDVs, adiciona-os à rota e calcula automaticamente a distância e o tempo, tudo no mesmo ecrã.');
        ?>
        <style>
          .routespro-routes-premium .rp-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(340px,.75fr);gap:20px;align-items:start;margin-top:16px}
          .routespro-routes-premium .rp-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 10px 30px rgba(15,23,42,.06);padding:22px}
          .routespro-routes-premium .rp-card h2,.routespro-routes-premium .rp-card h3{margin-top:0}
          .routespro-routes-premium .rp-note{color:#64748b;margin-top:0}
          .routespro-routes-premium .rp-chip{display:inline-flex;align-items:center;padding:10px 14px;border-radius:999px;background:#f8fafc;border:1px solid #dbeafe;font-weight:600;margin:0 8px 8px 0}
          .routespro-routes-premium .rp-columns{display:grid;grid-template-columns:1fr 1fr;gap:16px}
          .routespro-routes-premium .rp-mini-card{border:1px solid #e5e7eb;border-radius:14px;padding:14px;background:#fff}
          .routespro-routes-premium .rp-result{border:1px solid #e5e7eb;border-radius:14px;padding:12px;background:#fff;margin-bottom:10px}
          .routespro-routes-premium .rp-result h4{margin:0 0 6px;font-size:14px}
          .routespro-routes-premium .rp-muted{color:#64748b}
          .routespro-routes-premium .rp-queue-item{display:grid;grid-template-columns:28px 44px minmax(0,1fr) auto;gap:12px;align-items:center;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:#fff;margin-bottom:10px;transition:.16s ease}
          .routespro-routes-premium .rp-queue-item[draggable="true"]{cursor:grab}
          .routespro-routes-premium .rp-queue-item.rp-dragging{opacity:.55;transform:scale(.99)}
          .routespro-routes-premium .rp-queue-item.rp-selected{border-color:#2563eb;box-shadow:0 0 0 2px rgba(37,99,235,.12)}
          .routespro-routes-premium .rp-queue-item.rp-position-locked{border-color:#f59e0b;background:#fffbeb}
          .routespro-routes-premium .rp-queue-item.rp-operational-protected{background:#f8fafc;border-color:#cbd5e1}
          .routespro-routes-premium .rp-drag-handle{font-size:20px;color:#64748b;text-align:center;user-select:none}
          .routespro-routes-premium .rp-workspace-compare{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px}
          .routespro-routes-premium .rp-workspace-compare>div{border:1px solid #e2e8f0;border-radius:14px;padding:12px;background:#f8fafc}
          .routespro-routes-premium .rp-history-item{border:1px solid #e2e8f0;border-radius:14px;padding:12px;margin-bottom:10px;background:#fff}
          .routespro-routes-premium .rp-queue-no{width:36px;height:36px;border-radius:999px;background:#111827;color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center}
          .routespro-routes-premium .rp-badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;font-size:12px;font-weight:600;margin-right:6px}
          .routespro-routes-premium .rp-actions{display:flex;gap:8px;flex-wrap:wrap}
          .routespro-routes-premium .rp-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
          .routespro-routes-premium .rp-toolbar input,.routespro-routes-premium .rp-toolbar select,.routespro-routes-premium .rp-toolbar textarea{min-width:180px}
          .routespro-routes-premium .form-table th{width:220px;padding-top:16px}
          .routespro-routes-premium .form-table td{padding-top:10px;padding-bottom:10px}
          .routespro-routes-premium #rp-discovery-map,#rp-route-map{height:340px;border:1px solid #e5e7eb;border-radius:14px;background:#f8fafc}
          .routespro-routes-premium .rp-summary-grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(155px,1fr));gap:12px}
          .routespro-routes-premium .rp-summary-box{padding:14px;border:1px solid #e5e7eb;border-radius:14px;background:#f8fafc}
          .routespro-routes-premium .rp-summary-box strong{display:block;font-size:22px;color:#0f172a}
          .routespro-routes-premium .rp-inline-form{display:grid;grid-template-columns:1fr 1fr;gap:12px}
          .routespro-routes-premium .rp-inline-form .full{grid-column:1 / -1}
          .routespro-routes-premium .rp-aside{position:sticky;top:24px}
          .routespro-routes-premium .rp-assigned-panel{border:1px solid #bfdbfe;background:#eff6ff;border-radius:14px;padding:12px;max-width:760px}
          .routespro-routes-premium .rp-assigned-panel.empty{border-color:#e5e7eb;background:#f8fafc;color:#64748b}
          .routespro-routes-premium .rp-assigned-hit{background:#fff;border:1px solid #dbeafe;border-radius:12px;padding:12px;margin-top:8px}
          .routespro-routes-premium .rp-assigned-hit strong{display:block;color:#0f172a;margin-bottom:4px}
          .routespro-routes-premium .rp-route-financial-summary{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:10px;margin:0 0 14px}.routespro-routes-premium .rp-route-financial-summary>div{padding:11px 12px;border:1px solid #e2e8f0;border-radius:13px;background:#f8fafc}.routespro-routes-premium .rp-route-financial-summary span{display:block;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}.routespro-routes-premium .rp-route-financial-summary strong{display:block;margin-top:4px;font-size:16px;color:#0f172a}.routespro-routes-premium .rp-route-financial-cell strong,.routespro-routes-premium .rp-route-financial-cell small{display:block;white-space:nowrap}.routespro-routes-premium .rp-route-financial-cell small{color:#64748b;margin-top:3px}.routespro-routes-premium .rp-route-financial-cell .rp-financial-estimate{color:#92400e}.routespro-routes-premium .rp-financial-unavailable{color:#94a3b8;font-size:12px}.routespro-routes-premium #rp-existing-routes-table{min-width:1180px}.routespro-routes-premium #rp-bulk-delete-form{overflow-x:auto}
          @media (max-width:1180px){.routespro-routes-premium .rp-grid,.routespro-routes-premium .rp-columns,.routespro-routes-premium .rp-inline-form,.routespro-routes-premium .rp-summary-grid{grid-template-columns:1fr}.routespro-routes-premium .rp-aside{position:static}.routespro-routes-premium .rp-route-financial-summary{grid-template-columns:repeat(3,minmax(120px,1fr))}}
          @media (max-width:680px){.routespro-routes-premium .rp-route-financial-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}
        </style>
        <div>
          <span class="rp-chip">1. Filtrar mercado</span>
          <span class="rp-chip">2. Escolher ou criar PDVs</span>
          <span class="rp-chip">3. Adicionar à rota</span>
          <span class="rp-chip">4. Ver distância e tempo</span>
        </div>
        <form method="post" id="rp-route-form">
          <?php wp_nonce_field('routespro_routes','routespro_routes_nonce'); ?>
          <input type="hidden" name="id" value="<?php echo esc_attr($edit['id'] ?? 0); ?>">
          <input type="hidden" name="route_points_json" id="rp-route-points-json" value="<?php echo esc_attr(wp_json_encode($route_points)); ?>">
          <input type="hidden" name="route_workspace_action" id="rp-route-workspace-action" value="save">
          <input type="hidden" name="manual_order_locked" id="rp-manual-order-locked" value="<?php echo !empty($route_meta['manual_order_locked']) ? '1' : '0'; ?>">
          <datalist id="rp-address-options"><?php foreach (($campaignAddressOptions[(int)($edit['project_id'] ?? 0)] ?? []) as $opt): ?><option value="<?php echo esc_attr($opt['label'] ?: $opt['address']); ?>" label="<?php echo esc_attr($opt['address']); ?>"></option><?php endforeach; ?></datalist>
          <div class="rp-grid">
            <div>
              <div class="rp-card">
                <h2><?php echo $edit ? 'Editar rota' : 'Nova rota'; ?></h2>
                <p class="rp-note">Escolhe a zona e a categoria, encontra PDVs existentes ou novos, adiciona-os à rota e deixa o planeamento alimentar-se automaticamente.</p>
                <table class="form-table" role="presentation">
                  <tr><th>Cliente</th><td><select name="client_id" id="rp-client" required><option value="">--</option><?php foreach($clients as $c): ?><option value="<?php echo intval($c['id']); ?>" <?php selected(($edit['client_id'] ?? $sel_client_id), $c['id']); ?>><?php echo esc_html($c['name']); ?></option><?php endforeach; ?></select><p class="description">O owner operacional passa a sincronizar automaticamente a visibilidade da rota no front e nas atribuições da própria rota.</p></td></tr>
                  <tr><th>Projeto</th><td><select name="project_id" id="rp-project"><option value="">--</option><?php foreach($projects as $p): ?><option value="<?php echo intval($p['id']); ?>" <?php selected(($edit['project_id'] ?? 0), $p['id']); ?>><?php echo esc_html($p['name']); ?></option><?php endforeach; ?></select><input type="hidden" name="routing_country_code" id="rp-routing-country-code" value="<?php echo esc_attr($routeCountryCode); ?>"><p class="description">País de routing: <strong id="rp-routing-country-label"><?php echo esc_html($routeCountryCode); ?></strong>. O geocode de partida, chegada e PDVs é restringido a este país.</p></td></tr>
                  <tr><th>Data</th><td><input type="date" name="date" id="rp-date" value="<?php echo esc_attr($edit['date'] ?? current_time('Y-m-d')); ?>"></td></tr>
                  <tr><th>Status</th><td><select name="status" id="rp-route-status"><?php foreach(['draft','planned','in_progress','completed','canceled'] as $s): ?><option value="<?php echo esc_attr($s); ?>" <?php selected(($edit['status'] ?? 'draft'), $s); ?>><?php echo esc_html($s); ?></option><?php endforeach; ?></select></td></tr>
                  <tr><th>Owner operacional</th><td><select name="owner_user_id" id="rp-owner"><option value="">--</option><?php foreach($users as $u): ?><option value="<?php echo intval($u->ID); ?>" <?php selected(($edit['owner_user_id'] ?? 0), $u->ID); ?>><?php echo esc_html($u->display_name.' ['.$u->user_login.'] ('.$u->user_email.')'); ?></option><?php endforeach; ?></select></td></tr>
                  <tr><th>Rota atribuída</th><td><div id="rp-assigned-route-panel" class="rp-assigned-panel empty">Escolhe cliente, campanha, data e owner para veres se já existe uma rota atribuída.</div><p class="description">Quando existe rota na data selecionada, aparece aqui com acesso direto à edição e exportação para cliente.</p></td></tr>
                  <tr><th>Ocorrência da rota</th><td>
                    <?php $routeException = is_array($route_meta['route_exception'] ?? null) ? $route_meta['route_exception'] : []; ?>
                    <div style="padding:14px;border:1px solid #fde68a;background:#fffbeb;border-radius:14px;max-width:760px">
                      <label style="display:block;font-weight:700;margin-bottom:6px">Motivo</label>
                      <select name="route_exception_status" id="rp-route-exception-status" style="width:100%;max-width:520px">
                        <?php foreach(RouteExceptionService::statuses() as $exceptionKey=>$exceptionLabel): ?>
                          <option value="<?php echo esc_attr($exceptionKey); ?>" <?php selected((string)($routeException['status'] ?? 'none'), $exceptionKey); ?>><?php echo esc_html($exceptionLabel); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <label style="display:block;font-weight:700;margin:12px 0 6px">Nota para o cliente</label>
                      <textarea name="route_exception_note" id="rp-route-exception-note" rows="5" style="width:100%" placeholder="Explica de forma objetiva o motivo, as lojas afetadas e a ação prevista."><?php echo esc_textarea((string)($routeException['note'] ?? '')); ?></textarea>
                      <label style="display:block;margin-top:10px"><input type="checkbox" name="route_exception_notify_client" value="1" <?php checked(!empty($routeException['notify_client'])); ?>> Enviar automaticamente o email configurado no menu E-mails quando esta ocorrência for guardada ou alterada</label>
                      <?php if (!empty($routeException['active']) && !empty($routeException['last_notified_at'])): ?>
                        <div style="margin-top:10px;color:#6b7280">Último envio: <?php echo esc_html(wp_date('d/m/Y H:i', strtotime((string)$routeException['last_notified_at']))); ?>, <?php echo esc_html((string)($routeException['last_notification_result'] ?? '')); ?>, <?php echo intval($routeException['notification_count'] ?? 0); ?> envio(s) concluído(s).</div>
                        <label style="display:block;margin-top:8px"><input type="checkbox" name="route_exception_resend" value="1"> Reenviar mesmo que a nota não tenha mudado</label>
                      <?php endif; ?>
                    </div>
                    <p class="description">Sem ocorrência não é enviado qualquer email. O sistema evita duplicados e volta a enviar quando o motivo ou a nota mudam.</p>
                  </td></tr>
                  <tr><th>Ponto de partida</th><td><input type="text" name="start_address" id="rp-start-address" list="rp-address-options" value="<?php echo esc_attr($startPoint['address'] ?? ''); ?>" placeholder="Morada inicial" style="min-width:360px"><input type="hidden" name="start_lat" id="rp-start-lat" value="<?php echo esc_attr($startPoint['lat'] ?? ''); ?>"><input type="hidden" name="start_lng" id="rp-start-lng" value="<?php echo esc_attr($startPoint['lng'] ?? ''); ?>"><input type="hidden" name="start_provenance" value="<?php echo esc_attr($startPoint['provenance'] ?? 'manual'); ?>"><p class="description">Google autocomplete ou moradas já usadas na campanha. A morada visível é a autoridade do endpoint.</p><label style="display:block;margin-top:8px"><input type="checkbox" name="start_at_first_stop" id="rp-start-at-first-stop" value="1" <?php checked(!empty($startPoint['allow_matches_stop'])); ?>> A rota começa mesmo no primeiro PDV, permitir 0 km de acesso</label></td></tr>
                  <tr><th>Ponto de chegada</th><td><input type="text" name="end_address" id="rp-end-address" list="rp-address-options" value="<?php echo esc_attr($endPoint['address'] ?? ''); ?>" placeholder="Morada final" style="min-width:360px"><input type="hidden" name="end_lat" id="rp-end-lat" value="<?php echo esc_attr($endPoint['lat'] ?? ''); ?>"><input type="hidden" name="end_lng" id="rp-end-lng" value="<?php echo esc_attr($endPoint['lng'] ?? ''); ?>"><input type="hidden" name="end_provenance" value="<?php echo esc_attr($endPoint['provenance'] ?? 'manual'); ?>"><p class="description">Obrigatório para publicar. Em rascunho pode ficar vazio, mas os custos de mobilidade ficam por calcular.</p><label style="display:block;margin-top:8px"><input type="checkbox" name="end_at_last_stop" id="rp-end-at-last-stop" value="1" <?php checked(!empty($endPoint['allow_matches_stop'])); ?>> A rota termina mesmo no último PDV, permitir 0 km de regresso</label><label style="display:block;margin-top:8px"><input type="checkbox" name="save_route_defaults" value="1"> Fixar estes pontos por cliente/projeto/owner para novas rotas</label></td></tr>
                </table>
              </div>

              <div class="rp-card">
                <h3>Descobrir PDVs</h3>
                <div class="rp-toolbar">
                  <select name="commercial_district" id="rp-commercial-district"><option value="">Distrito</option><?php foreach($rpDistricts as $d): ?><option value="<?php echo esc_attr($d); ?>" <?php selected(($route_meta['district'] ?? ''), $d); ?>><?php echo esc_html($d); ?></option><?php endforeach; ?></select>
                  <select name="commercial_county" id="rp-commercial-county"><option value="">Concelho</option><?php foreach($rpCounties as $d): ?><option value="<?php echo esc_attr($d); ?>" <?php selected(($route_meta['county'] ?? ''), $d); ?>><?php echo esc_html($d); ?></option><?php endforeach; ?></select>
                  <select name="commercial_city" id="rp-commercial-city"><option value="">Cidade</option><?php foreach($rpCities as $d): ?><option value="<?php echo esc_attr($d); ?>" <?php selected(($route_meta['city'] ?? ''), $d); ?>><?php echo esc_html($d); ?></option><?php endforeach; ?></select>
                  <select name="commercial_category_id" id="rp-commercial-category"><option value="">Categoria</option><?php foreach($commercialRoots as $cat): ?><option value="<?php echo intval($cat['id']); ?>" <?php selected((int)($route_meta['category_id'] ?? 0), (int)$cat['id']); ?>><?php echo esc_html($cat['name']); ?></option><?php endforeach; ?></select>
                  <select name="commercial_subcategory_id" id="rp-commercial-subcategory"><option value="">Subcategoria</option></select>
                  <input type="search" id="rp-discovery-q" placeholder="Nome, morada ou referência">
                  <button type="button" class="button button-primary" id="rp-run-discovery">Procurar PDVs</button>
                </div>
                <p class="rp-note">Pesquisa interna e Google no mesmo fluxo. Em baixo podes adicionar diretamente à rota.</p><div id="rp-dedupe-notice" class="notice inline" style="display:none;margin:10px 0"></div>
                <div class="rp-columns">
                  <div class="rp-mini-card">
                    <h4 style="margin:0 0 10px">PDVs já existentes</h4>
                    <select id="rp-existing-select" style="width:100%;min-height:44px"><option value="">Escolhe um PDV existente</option></select>
                    <div id="rp-existing-results" style="margin-top:10px;max-height:260px;overflow:auto"></div>
                  </div>
                  <div class="rp-mini-card">
                    <h4 style="margin:0 0 10px">Novos PDVs encontrados</h4>
                    <div id="rp-google-results" style="max-height:310px;overflow:auto"></div>
                  </div>
                </div>
                <div style="margin-top:14px" id="rp-discovery-map-wrap">
                  <div id="rp-discovery-map"></div>
                </div>
              </div>

              <div class="rp-card">
                <h3>Novo PDV inline</h3>
                <p class="rp-note">Quando não existir um PDV adequado, cria-o aqui e adiciona-o de imediato à rota.</p>
                <div class="rp-inline-form">
                  <input type="text" id="rp-new-name" placeholder="Nome estabelecimento">
                  <input type="text" id="rp-new-phone" placeholder="Telefone">
                  <input type="text" id="rp-new-contact" placeholder="Contacto">
                  <input type="email" id="rp-new-email" placeholder="Email">
                  <input type="text" id="rp-new-address" class="full" placeholder="Morada">
                  <input type="text" id="rp-new-district" placeholder="Distrito">
                  <input type="text" id="rp-new-county" placeholder="Concelho">
                  <input type="text" id="rp-new-city" placeholder="Cidade">
                  <input type="text" id="rp-new-postal-code" placeholder="Cod. postal">
                  <select id="rp-new-category"><option value="">Categoria</option><?php foreach($commercialRoots as $cat): ?><option value="<?php echo intval($cat['id']); ?>"><?php echo esc_html($cat['name']); ?></option><?php endforeach; ?></select>
                  <select id="rp-new-subcategory"><option value="">Subcategoria</option></select>
                  <input type="text" id="rp-new-lat" placeholder="Lat">
                  <input type="text" id="rp-new-lng" placeholder="Lng">
                  <input type="hidden" id="rp-new-place-id">
                </div>
                <p class="rp-note" id="rp-new-fill-note">Ao escolheres um novo PDV encontrado, os campos abaixo são preenchidos automaticamente para poderes classificar e adicionar à rota.</p>
                <div class="rp-actions" style="margin-top:12px">
                  <button type="button" class="button" id="rp-add-manual">Adicionar novo PDV à rota</button>
                </div>
              </div>
            </div>

            <div class="rp-aside">
              <div class="rp-card">
                <div class="rp-section-title"><div><h3 style="margin:0">Workspace da rota</h3><p class="rp-note" style="margin:6px 0 0"><?php echo !empty($routeWorkspaceState['live_adjustable']) ? 'A rota está em execução. Podes acrescentar, remover e reordenar apenas lojas ainda pendentes. As visitas executadas e em curso ficam protegidas.' : 'Arrasta para reordenar, bloqueia lojas críticas, compara alternativas e só publica quando o plano estiver validado.'; ?></p></div><span class="rp-badge" id="rp-workspace-mode"><?php echo !empty($routeWorkspaceState['live_adjustable']) ? 'Ajuste de rota ativa' : (!empty($routeWorkspaceState['editable']) ? 'Rascunho editável' : 'Planeamento bloqueado'); ?></span></div>
                <div class="rp-toolbar" style="margin:14px 0 10px">
                  <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" id="rp-select-all-points"> Selecionar todas</label>
                  <button type="button" class="button" id="rp-remove-selected" disabled>Remover selecionadas</button>
                  <button type="button" class="button" id="rp-optimize-workspace">Comparar e otimizar</button>
                  <button type="button" class="button" id="rp-apply-optimized" disabled>Aplicar ordem otimizada</button>
                  <button type="button" class="button" id="rp-clear-queue">Limpar rota</button>
                </div>
                <div id="rp-route-queue"></div>
                <div id="rp-workspace-comparison" class="rp-workspace-compare" style="display:none">
                  <div><strong>Ordem atual</strong><div id="rp-compare-current" class="rp-muted" style="margin-top:6px">Por calcular</div></div>
                  <div><strong>Ordem otimizada</strong><div id="rp-compare-optimized" class="rp-muted" style="margin-top:6px">Por calcular</div></div>
                </div>
              </div>

              <div class="rp-card">
                <h3>Planeador Google</h3>
                <p class="rp-note">O planeamento é alimentado automaticamente pelos PDVs adicionados acima. Aqui só vês o resultado, não precisas de preencher tudo outra vez.</p>
                <div class="rp-summary-grid">
                  <div class="rp-summary-box"><span>Total de PDVs</span><strong id="rp-summary-count">0</strong></div>
                  <div class="rp-summary-box"><span>Distância total</span><strong id="rp-summary-km"><?php $savedEndpointsRoutable = isset($savedRouteMetrics['endpoints_routable']) ? !empty($savedRouteMetrics['endpoints_routable']) : (max(0,(int)($savedRouteMetrics['missing_endpoint_count'] ?? 0)) === 0); $savedDistanceComplete = (string)($savedRouteMetrics['distance_status'] ?? '') === 'complete' && $savedEndpointsRoutable; $savedDistance = $savedDistanceComplete ? ($savedRouteMetrics['distance_km'] ?? null) : ($savedRouteMetrics['known_distance_km'] ?? null); echo is_numeric($savedDistance) ? esc_html(number_format((float)$savedDistance, 1, ',', '.') . ' km' . ($savedDistanceComplete ? '' : ' conhecidos')) : '--'; ?></strong></div>
                  <div class="rp-summary-box"><span>Partida usada</span><strong id="rp-summary-start-point" style="font-size:12px"><?php echo esc_html((string)($startPoint['address'] ?? '') ?: 'Por definir'); ?></strong></div>
                  <div class="rp-summary-box"><span>Chegada usada</span><strong id="rp-summary-end-point" style="font-size:12px"><?php echo esc_html((string)($endPoint['address'] ?? '') ?: 'Por definir'); ?></strong></div>
                  <div class="rp-summary-box"><span>Portagens estimadas</span><strong id="rp-summary-toll"><?php $savedToll = $savedDistanceComplete ? ($savedRouteMetrics['toll_cost_eur'] ?? $savedRouteMetrics['toll_estimated_eur'] ?? null) : null; echo is_numeric($savedToll) ? esc_html(\RoutesPro\Support\TollEstimator::formatEuro((float)$savedToll)) : 'Por calcular'; ?></strong><?php if (is_numeric($savedToll) && !empty($savedRouteMetrics['toll_confidence'])): ?><small style="display:block;margin-top:4px;color:#64748b">Confiança: <?php echo esc_html((string)$savedRouteMetrics['toll_confidence']); ?><?php if (!empty($savedRouteMetrics['toll_vehicle_class'])) echo ' · Classe '.esc_html((string)$savedRouteMetrics['toll_vehicle_class']); ?></small><?php endif; ?></div>
                  <div class="rp-summary-box"><span>Combustível / energia</span><strong id="rp-summary-energy"><?php echo !empty($savedMobility['enabled']) ? (!empty($savedMobility['costs_available']) ? esc_html(\RoutesPro\Support\TollEstimator::formatEuro((float)($savedMobility['energy_cost_eur'] ?? 0))) : 'Por calcular') : '--'; ?></strong></div>
                  <div class="rp-summary-box"><span>Custo deslocação</span><strong id="rp-summary-mobility-total"><?php echo !empty($savedMobility['enabled']) ? (!empty($savedMobility['costs_available']) ? esc_html(\RoutesPro\Support\TollEstimator::formatEuro((float)($savedMobility['total_mobility_cost_eur'] ?? 0))) : 'Por calcular') : (is_numeric($savedToll) ? esc_html(\RoutesPro\Support\TollEstimator::formatEuro((float)$savedToll)) : 'Por calcular'); ?></strong></div>
                  <div class="rp-summary-box"><span>Perfil mobilidade</span><strong id="rp-summary-mobility-profile" style="font-size:12px"><?php echo !empty($savedMobility['enabled']) ? esc_html((string)($savedMobility['profile_label'] ?? '')) : 'Não configurado'; ?></strong></div>
                  <div class="rp-summary-box"><span>Tempo viagem</span><strong id="rp-summary-travel-time"><?php $savedTravel = $savedRouteMetrics['travel_min'] ?? $savedRouteMetrics['known_travel_min'] ?? null; echo is_numeric($savedTravel) ? esc_html((int)round((float)$savedTravel) . ' min') : '--'; ?></strong></div>
                  <div class="rp-summary-box"><span>Tempo visita</span><strong id="rp-summary-visit-time"><?php echo esc_html(isset($savedRouteMetrics['visit_min']) && is_numeric($savedRouteMetrics['visit_min']) ? ((int)$savedRouteMetrics['visit_min'] . ' min') : '0 min'); ?></strong></div>
                  <div class="rp-summary-box"><span>Tempo total</span><strong id="rp-summary-time"><?php echo esc_html(isset($savedRouteMetrics['total_min']) && is_numeric($savedRouteMetrics['total_min']) ? ((int)$savedRouteMetrics['total_min'] . ' min') : '--'); ?></strong></div>
                  <div class="rp-summary-box"><span>Km acesso</span><strong id="rp-summary-access"><?php echo isset($savedRouteMetrics['access_distance_km']) && is_numeric($savedRouteMetrics['access_distance_km']) ? esc_html(number_format((float)$savedRouteMetrics['access_distance_km'], 1, ',', '.') . ' km') : '--'; ?></strong></div>
                  <div class="rp-summary-box"><span>Km entre PDVs</span><strong id="rp-summary-inter"><?php echo isset($savedRouteMetrics['inter_stop_distance_km']) && is_numeric($savedRouteMetrics['inter_stop_distance_km']) ? esc_html(number_format((float)$savedRouteMetrics['inter_stop_distance_km'], 1, ',', '.') . ' km') : '--'; ?></strong></div>
                  <div class="rp-summary-box"><span>Km regresso</span><strong id="rp-summary-return"><?php echo isset($savedRouteMetrics['return_distance_km']) && is_numeric($savedRouteMetrics['return_distance_km']) ? esc_html(number_format((float)$savedRouteMetrics['return_distance_km'], 1, ',', '.') . ' km') : '--'; ?></strong></div>
                  <div class="rp-summary-box"><span>Integridade</span><strong id="rp-summary-integrity"><?php echo esc_html((string)($savedRouteMetrics['distance_status'] ?? '') === 'complete' ? 'Completa' : 'Por validar'); ?></strong></div>
                  <div class="rp-summary-box"><span>Fonte</span><strong id="rp-summary-source" style="font-size:12px"><?php echo esc_html(str_replace('_', ' ', (string)($savedRouteMetrics['matrix_source_label'] ?? $savedRouteMetrics['matrix_provider'] ?? $savedRouteMetrics['distance_source'] ?? 'Estimador interno'))); ?></strong></div>
                  <div class="rp-summary-box"><span>Poupança otimização</span><strong id="rp-summary-saving"><?php echo isset($savedRouteMetrics['optimization_saved_distance_km']) && is_numeric($savedRouteMetrics['optimization_saved_distance_km']) ? esc_html(number_format((float)$savedRouteMetrics['optimization_saved_distance_km'], 1, ',', '.') . ' km') : '0 km'; ?></strong></div>
                  <div class="rp-summary-box"><span>Direção dominante</span><strong id="rp-summary-direction"><?php echo esc_html((string)($savedRouteMetrics['territory_dominant_direction_label'] ?? 'Por calcular')); ?></strong></div>
                  <div class="rp-summary-box"><span>Coerência territorial</span><strong id="rp-summary-coherence"><?php echo esc_html((string)($savedRouteMetrics['territory_coherence_label'] ?? 'Por calcular')); ?></strong></div>
                </div>
                <p class="rp-note" style="margin-top:10px">Tempo perdido na visita é opcional. Podes definir manualmente ou escolher buckets de 15 minutos até 6 horas.</p>
                <div style="margin-top:14px" id="rp-route-map"></div>
                <div id="rp-route-legs" style="margin-top:12px;max-height:260px;overflow:auto"></div>
              </div>

              <div class="rp-card">
                <h3><?php echo !empty($routeWorkspaceState['live_adjustable']) ? 'Guardar ajuste operacional' : 'Validar e publicar'; ?></h3>
                <p class="rp-note"><?php echo !empty($routeWorkspaceState['live_adjustable']) ? 'A gravação mantém o estado operacional da rota e preserva todas as visitas já executadas ou em curso.' : 'Guardar rascunho mantém a rota fora da operação. Publicar muda o estado para planeada e disponibiliza-a ao owner.'; ?></p>
                <div class="rp-actions">
                  <button type="submit" class="button button-large" id="rp-save-draft" data-workspace-action="save_draft" <?php disabled(!empty($routeWorkspaceState['live_adjustable'])); ?>>Guardar rascunho</button>
                  <button type="submit" class="button button-primary button-large" id="rp-publish-route" data-workspace-action="publish" <?php disabled(!empty($routeWorkspaceState['live_adjustable'])); ?>>Publicar rota</button>
                  <button type="submit" class="button button-large" id="rp-save-route" data-workspace-action="save"><?php echo !empty($routeWorkspaceState['live_adjustable']) ? 'Guardar ajuste na rota ativa' : 'Guardar com estado selecionado'; ?></button>
                  <?php if ($edit): ?>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=routespro-routes&client_id='.(int)$sel_client_id)); ?>">Nova rota</a>
                    <?php
                      $singleExportBase = admin_url('admin-post.php?action=routespro_export_routes&route_id=' . (int) $edit['id']);
                      $singleCsv = wp_nonce_url(add_query_arg(['format' => 'csv'], $singleExportBase), 'routespro_export_routes');
                      $singleXls = wp_nonce_url(add_query_arg(['format' => 'xls'], $singleExportBase), 'routespro_export_routes');
                      $singlePdf = wp_nonce_url(add_query_arg(['format' => 'pdf'], $singleExportBase), 'routespro_export_routes');
                    ?>
                    <a class="button" href="<?php echo esc_url($singleCsv); ?>">CSV da rota</a>
                    <a class="button" href="<?php echo esc_url($singleXls); ?>">Excel da rota</a>
                    <a class="button" href="<?php echo esc_url($singlePdf); ?>">PDF da rota</a>
                  <?php endif; ?>
                </div>
              </div>


            </div>
          </div>
        </form>

        <?php if ($edit): ?>
        <div class="rp-card" style="margin-top:20px">
          <h3>Histórico do planeamento</h3>
          <p class="rp-note">São guardadas até <?php echo intval(RouteWorkspaceService::HISTORY_LIMIT); ?> versões. O restauro só é permitido antes de existir atividade operacional.</p>
          <?php if (!$routeWorkspaceHistory): ?>
            <div class="rp-muted">Ainda não existem versões anteriores.</div>
          <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px">
            <?php foreach ($routeWorkspaceHistory as $revision): ?>
              <div class="rp-history-item">
                <strong><?php echo esc_html((string)($revision['label'] ?? 'Versão anterior')); ?></strong>
                <div class="rp-muted" style="margin:5px 0"><?php echo esc_html(wp_date('d/m/Y H:i', strtotime((string)($revision['created_at'] ?? 'now')))); ?>, <?php echo intval($revision['stop_count'] ?? 0); ?> lojas, estado <?php echo esc_html((string)($revision['route_status'] ?? 'draft')); ?></div>
                <form method="post" onsubmit="return confirm('Restaurar esta versão do planeamento?');">
                  <?php wp_nonce_field('routespro_route_restore', 'routespro_route_restore_nonce'); ?>
                  <input type="hidden" name="route_id" value="<?php echo intval($edit['id']); ?>">
                  <input type="hidden" name="revision_id" value="<?php echo esc_attr((string)($revision['id'] ?? '')); ?>">
                  <button type="submit" class="button button-small" <?php disabled(empty($routeWorkspaceState['can_restore'])); ?>>Restaurar versão</button>
                </form>
              </div>
            <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="rp-card" style="margin-top:20px">
          <?php
            $routesExportBase = admin_url('admin-post.php?action=routespro_export_routes');
            $routesExportCsv = wp_nonce_url(add_query_arg(['format' => 'csv'], $routesExportBase), 'routespro_export_routes');
            $routesExportXls = wp_nonce_url(add_query_arg(['format' => 'xls'], $routesExportBase), 'routespro_export_routes');
            $routesExportPdf = wp_nonce_url(add_query_arg(['format' => 'pdf'], $routesExportBase), 'routespro_export_routes');
          ?>
          <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px"><div><h3 style="margin:0">Rotas existentes</h3><p class="rp-note" style="margin:6px 0 0">A lista respeita os filtros ativos acima para ajudares a navegar rapidamente por cliente, campanha, owner e data. Para exportar vários dias, usa o intervalo de datas abaixo.</p></div><div class="rp-actions"><a class="button button-primary rp-routes-export" id="rp-export-routes-csv" data-format="csv" href="<?php echo esc_url($routesExportCsv); ?>">Exportar CSV</a><a class="button rp-routes-export" id="rp-export-routes-xls" data-format="xls" href="<?php echo esc_url($routesExportXls); ?>">Exportar Excel</a><a class="button rp-routes-export" id="rp-export-routes-pdf" data-format="pdf" href="<?php echo esc_url($routesExportPdf); ?>">Exportar PDF</a></div></div>
          <div class="rp-toolbar" style="margin-bottom:12px"><label>Data de <input type="date" id="rp-routes-date-from"></label><label>Data até <input type="date" id="rp-routes-date-to"></label><button type="button" class="button" id="rp-clear-routes-date-range">Limpar intervalo</button><span class="rp-note">O intervalo sobrepõe-se à data da ficha da rota apenas na lista e na exportação global.</span></div>
          <div id="rp-routes-financial-summary" class="rp-route-financial-summary" aria-live="polite"><div><span>Rotas filtradas</span><strong>0</strong></div><div><span>Distância</span><strong>0 km</strong></div><div><span>Com custo</span><strong>0</strong></div><div><span>Energia</span><strong>0,00 €</strong></div><div><span>Portagens</span><strong>0,00 €</strong></div><div><span>Custo deslocação</span><strong>0,00 €</strong></div></div>
          <form method="post" id="rp-bulk-delete-form" style="margin:0">
            <?php wp_nonce_field('routespro_routes_bulk_delete', 'routespro_bulk_delete_nonce'); ?>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">
              <div id="rp-bulk-selection-meta" style="color:#64748b">0 selecionadas</div>
              <button type="submit" class="button button-secondary" id="rp-bulk-delete-btn" disabled>Apagar selecionadas</button>
            </div>
            <table class="widefat striped" id="rp-existing-routes-table">
              <thead><tr><th style="width:42px"><input type="checkbox" id="rp-select-all-routes" aria-label="Selecionar todas"></th><th>ID</th><th>Data</th><th>Cliente</th><th>Projeto</th><th>PDVs</th><th>Status</th><th>Percurso</th><th>Custos</th><th>Ocorrência</th><th>Owner</th><th>Ações</th></tr></thead>
              <tbody id="rp-existing-routes-body">
              <?php foreach($rows as $r): ?>
                <?php $rowFinancial = is_array($r['_ff_financial'] ?? null) ? $r['_ff_financial'] : []; ?>
                <tr data-route-id="<?php echo intval($r['id']); ?>" data-client-id="<?php echo intval($r['client_id'] ?? 0); ?>" data-project-id="<?php echo intval($r['project_id'] ?? 0); ?>" data-owner-id="<?php echo intval($r['owner_user_id'] ?? 0); ?>" data-date="<?php echo esc_attr($r['date']); ?>" data-distance-km="<?php echo esc_attr(is_numeric($rowFinancial['distance_km'] ?? null) ? (string)$rowFinancial['distance_km'] : ''); ?>" data-costed="<?php echo !empty($rowFinancial['costs_available']) ? '1' : '0'; ?>" data-energy-cost="<?php echo esc_attr(is_numeric($rowFinancial['energy_cost_eur'] ?? null) ? (string)$rowFinancial['energy_cost_eur'] : ''); ?>" data-toll-cost="<?php echo esc_attr(is_numeric($rowFinancial['toll_cost_eur'] ?? null) ? (string)$rowFinancial['toll_cost_eur'] : ''); ?>" data-mobility-cost="<?php echo esc_attr(is_numeric($rowFinancial['total_mobility_cost_eur'] ?? null) ? (string)$rowFinancial['total_mobility_cost_eur'] : ''); ?>">
                  <td><input type="checkbox" class="rp-route-checkbox" name="route_ids[]" value="<?php echo intval($r['id']); ?>" aria-label="Selecionar rota <?php echo intval($r['id']); ?>"></td>
                  <td>#<?php echo intval($r['id']); ?></td>
                  <td><?php echo esc_html($r['date']); ?></td>
                  <td><?php echo esc_html($r['client_name'] ?: '-'); ?></td>
                  <td><?php echo esc_html($r['project_name'] ?: '-'); ?></td>
                  <td><?php echo intval($r['stops_count'] ?? 0); ?></td>
                  <td><?php echo esc_html($r['status']); ?></td>
                  <td class="rp-route-financial-cell"><?php if (is_numeric($rowFinancial['distance_km'] ?? null)): ?><strong><?php echo esc_html(number_format_i18n((float)$rowFinancial['distance_km'], 1)); ?> km</strong><?php if (is_numeric($rowFinancial['travel_min'] ?? null)): ?><small><?php echo esc_html(number_format_i18n((float)$rowFinancial['travel_min'], 0)); ?> min estimados</small><?php endif; ?><?php else: ?><span class="rp-financial-unavailable">Sem distância</span><?php endif; ?></td>
                  <td class="rp-route-financial-cell"><?php if (!empty($rowFinancial['costs_available']) && is_numeric($rowFinancial['total_mobility_cost_eur'] ?? null)): ?><strong><?php echo esc_html(number_format_i18n((float)$rowFinancial['total_mobility_cost_eur'], 2)); ?> €</strong><small>Energia <?php echo esc_html(number_format_i18n((float)($rowFinancial['energy_cost_eur'] ?? 0), 2)); ?> € · Portagens <?php echo esc_html(number_format_i18n((float)($rowFinancial['toll_cost_eur'] ?? 0), 2)); ?> €</small><?php if (!empty($rowFinancial['recovered'])): ?><small class="rp-financial-estimate">Estimativa recuperada</small><?php endif; ?><?php else: ?><span class="rp-financial-unavailable">Sem perfil/cálculo</span><?php endif; ?></td>
                  <?php $rowMeta=json_decode((string)($r['meta_json'] ?? ''),true); $rowException=is_array($rowMeta['route_exception'] ?? null)?$rowMeta['route_exception']:[]; ?>
                  <td><?php if(!empty($rowException['active'])): ?><span style="display:inline-block;padding:3px 8px;border-radius:999px;background:#fef3c7;color:#92400e;font-weight:600"><?php echo esc_html((string)($rowException['reason_label'] ?? 'Ocorrência')); ?></span><?php else: ?>-<?php endif; ?></td>
                  <td><?php echo esc_html($r['owner_name'] ?: (get_the_author_meta('display_name', (int)($r['owner_user_id'] ?? 0)) ?: '-')); ?></td>
                  <td>
                    <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=routespro-routes&edit='.(int)$r['id'].'&client_id='.(int)$r['client_id'])); ?>">Editar</a>
                    <?php $rowExportBase = admin_url('admin-post.php?action=routespro_export_routes&route_id=' . (int) $r['id']); ?>
                    <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['format' => 'csv'], $rowExportBase), 'routespro_export_routes')); ?>">CSV</a>
                    <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['format' => 'xls'], $rowExportBase), 'routespro_export_routes')); ?>">Excel</a>
                    <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['format' => 'pdf'], $rowExportBase), 'routespro_export_routes')); ?>">PDF</a>
                    <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=routespro-routes&delete='.(int)$r['id']), 'routespro_routes_del_'.(int)$r['id'])); ?>" onclick="return confirm('Remover esta rota?')">Apagar</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </form>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px"><div id="rp-existing-routes-meta" style="color:#64748b">0 registos</div><div id="rp-existing-routes-pagination" style="display:flex;gap:8px;align-items:center"></div></div>

        <script>
        document.addEventListener('DOMContentLoaded', function(){
          const api = <?php echo wp_json_encode(rest_url('routespro/v1/')); ?>;
          const nonce = <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>;
          const wpToday = <?php echo wp_json_encode(current_time('Y-m-d')); ?>;
          const wpNowLocal = <?php echo wp_json_encode(current_time('Y-m-d\TH:i')); ?>;
          const routeEndpointConfigs = <?php echo wp_json_encode($routeEndpointConfigs); ?>;
          const assignedRoutes = <?php echo wp_json_encode($assignedRoutes); ?>;
          const campaignAddressOptions = <?php echo wp_json_encode($campaignAddressOptions); ?>;
          const ownerAjaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
          const routeMetricsPreviewNonce = <?php echo wp_json_encode(wp_create_nonce('routespro_route_metrics_preview')); ?>;
          const savedRouteMetrics = <?php echo wp_json_encode($savedRouteMetrics); ?>;
          const mapsProvider = <?php echo wp_json_encode($mapsProvider); ?>;
          const mapsKey = <?php echo wp_json_encode($gmKey); ?>;
          const googlePlacesContactEnrichment = <?php echo !empty(\RoutesPro\Support\Config::get('google_places_contact_enrichment_enabled', 0)) ? 'true' : 'false'; ?>;
          const geoCounties = <?php echo wp_json_encode($rpCountiesByDistrict); ?>;
          const geoCities = <?php echo wp_json_encode($rpCitiesByDistrict); ?>;
          const subcatEl = document.getElementById('rp-commercial-subcategory');
          const catEl = document.getElementById('rp-commercial-category');
          const subcatsByParent = <?php echo wp_json_encode($commercialChildrenByParent); ?>;
          const initialSubcategoryId = <?php echo (int)($route_meta['subcategory_id'] ?? 0); ?>;
          const districtEl = document.getElementById('rp-commercial-district');
          const countyEl = document.getElementById('rp-commercial-county');
          const cityEl = document.getElementById('rp-commercial-city');
          const routePointsInput = document.getElementById('rp-route-points-json');
          let routePoints = [];
          try { routePoints = JSON.parse(routePointsInput.value || '[]') || []; } catch(e) { routePoints = []; }
          routePoints = routePoints.map(function(p){ return normalizePoint(p); });
          const currentRouteId = <?php echo (int)($edit['id'] ?? 0); ?>;
          const workspaceFullEditable = <?php echo !empty($routeWorkspaceState['editable']) ? 'true' : 'false'; ?>;
          const workspaceLiveAdjustable = <?php echo !empty($routeWorkspaceState['live_adjustable']) ? 'true' : 'false'; ?>;
          const workspaceEditable = workspaceFullEditable || workspaceLiveAdjustable;
          let pendingOptimizedOrder = null;
          let lastWorkspaceMetrics = <?php echo wp_json_encode($savedRouteMetrics); ?> || {};
          let discoveryMap = null, routeMap = null, routeDirections = null, discoveryMarkers = [], routeMarkers = [];
          let routeCountryAutocompletes = [];
          function routingCountryForSelection(){
            const projectId = document.getElementById('rp-project')?.value || '0';
            const cfg = routeEndpointConfigs[String(projectId)] || {};
            const hidden = document.getElementById('rp-routing-country-code');
            return String(cfg.routing_country_code || hidden?.value || 'PT').trim().toUpperCase() || 'PT';
          }
          function routingCountryLabel(){ return routingCountryForSelection()==='ES' ? 'Espanha' : (routingCountryForSelection()==='PT' ? 'Portugal' : routingCountryForSelection()); }
          function syncRoutingCountry(){
            const code = routingCountryForSelection();
            const hidden = document.getElementById('rp-routing-country-code');
            const label = document.getElementById('rp-routing-country-label');
            if(hidden) hidden.value = code;
            if(label) label.textContent = code;
            routeCountryAutocompletes.forEach(function(ac){ try{ ac.setComponentRestrictions({country:code.toLowerCase()}); }catch(_){ } });
            return code;
          }

          function updateBulkDeleteState(){
            const checkboxes = Array.from(document.querySelectorAll('.rp-route-checkbox'));
            const checked = checkboxes.filter(function(cb){ return cb.checked; });
            const bulkMeta = document.getElementById('rp-bulk-selection-meta');
            const bulkBtn = document.getElementById('rp-bulk-delete-btn');
            const selectAll = document.getElementById('rp-select-all-routes');
            if (bulkMeta) bulkMeta.textContent = checked.length + ' selecionadas';
            if (bulkBtn) bulkBtn.disabled = checked.length === 0;
            if (selectAll) {
              const visible = checkboxes.filter(function(cb){ return cb.closest('tr') && cb.closest('tr').style.display !== 'none'; });
              const visibleChecked = visible.filter(function(cb){ return cb.checked; });
              selectAll.checked = visible.length > 0 && visibleChecked.length === visible.length;
              selectAll.indeterminate = visibleChecked.length > 0 && visibleChecked.length < visible.length;
            }
          }

          function routeMoney(value){ return (Number(value||0)).toLocaleString('pt-PT',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' €'; }
          function renderRouteFinancialSummary(filtered){
            const summary = document.getElementById('rp-routes-financial-summary');
            if (!summary) return;
            const totals=(filtered||[]).reduce(function(acc,row){
              const distance=parseFloat(row.dataset.distanceKm||'');
              const energy=parseFloat(row.dataset.energyCost||'');
              const toll=parseFloat(row.dataset.tollCost||'');
              const mobility=parseFloat(row.dataset.mobilityCost||'');
              acc.routes++;
              if(Number.isFinite(distance)) acc.distance+=distance;
              if(String(row.dataset.costed||'')==='1') acc.costed++;
              if(Number.isFinite(energy)) acc.energy+=energy;
              if(Number.isFinite(toll)) acc.toll+=toll;
              if(Number.isFinite(mobility)) acc.mobility+=mobility;
              return acc;
            },{routes:0,distance:0,costed:0,energy:0,toll:0,mobility:0});
            const values=[String(totals.routes),totals.distance.toLocaleString('pt-PT',{maximumFractionDigits:1})+' km',String(totals.costed),routeMoney(totals.energy),routeMoney(totals.toll),routeMoney(totals.mobility)];
            Array.from(summary.querySelectorAll('strong')).forEach(function(el,index){ if(values[index]!==undefined) el.textContent=values[index]; });
          }

          function renderExistingRoutesTable(page){
            const table = document.getElementById('rp-existing-routes-table');
            const body = document.getElementById('rp-existing-routes-body');
            const meta = document.getElementById('rp-existing-routes-meta');
            const pager = document.getElementById('rp-existing-routes-pagination');
            if (!table || !body || !pager) return;
            const rows = Array.from(body.querySelectorAll('tr'));
            const clientId = document.getElementById('rp-client')?.value || '';
            const projectId = document.getElementById('rp-project')?.value || '';
            const ownerId = document.getElementById('rp-owner')?.value || '';
            const dateVal = document.getElementById('rp-date')?.value || '';
            const dateFrom = document.getElementById('rp-routes-date-from')?.value || '';
            const dateTo = document.getElementById('rp-routes-date-to')?.value || '';
            const useRange = !!(dateFrom || dateTo);
            const filtered = rows.filter(function(row){
              const rowDate = String(row.dataset.date || '');
              const okClient = !clientId || String(row.dataset.clientId || '') === String(clientId);
              const okProject = !projectId || String(row.dataset.projectId || '') === String(projectId);
              const okOwner = !ownerId || String(row.dataset.ownerId || '') === String(ownerId);
              const okDate = useRange ? ((!dateFrom || rowDate >= dateFrom) && (!dateTo || rowDate <= dateTo)) : (!dateVal || rowDate === String(dateVal));
              return okClient && okProject && okOwner && okDate;
            });
            renderRouteFinancialSummary(filtered);
            const perPage = 10;
            const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
            const safePage = Math.min(Math.max(parseInt(page || 1, 10) || 1, 1), totalPages);
            rows.forEach(function(row){ row.style.display = 'none'; });
            filtered.slice((safePage - 1) * perPage, safePage * perPage).forEach(function(row){ row.style.display = ''; });
            meta.textContent = filtered.length + ' registos';
            if (totalPages <= 1) { pager.innerHTML = ''; updateBulkDeleteState(); updateRoutesExportLinks(); renderAssignedRoutePanel(); return; }
            pager.innerHTML = '<button type="button" class="button" data-page="'+(safePage-1)+'" '+(safePage<=1?'disabled':'')+'>Anterior</button><span>Página '+safePage+' de '+totalPages+'</span><button type="button" class="button" data-page="'+(safePage+1)+'" '+(safePage>=totalPages?'disabled':'')+'>Seguinte</button>';
            pager.querySelectorAll('[data-page]').forEach(function(btn){ btn.addEventListener('click', function(){ renderExistingRoutesTable(parseInt(btn.dataset.page || '1', 10) || 1); }); });
            updateBulkDeleteState();
            updateRoutesExportLinks();
            renderAssignedRoutePanel();
          }

          function renderAssignedRoutePanel(){
            const panel = document.getElementById('rp-assigned-route-panel');
            if (!panel) return;
            const clientId = document.getElementById('rp-client')?.value || '';
            const projectId = document.getElementById('rp-project')?.value || '';
            const ownerId = document.getElementById('rp-owner')?.value || '';
            const dateVal = document.getElementById('rp-date')?.value || '';
            if (!clientId || !dateVal) {
              panel.className = 'rp-assigned-panel empty';
              panel.textContent = 'Escolhe pelo menos cliente e data para verificar rotas atribuídas.';
              return;
            }
            const matches = (assignedRoutes || []).filter(function(route){
              const okClient = String(route.client_id || '') === String(clientId);
              const okProject = !projectId || String(route.project_id || '') === String(projectId);
              const okOwner = !ownerId || String(route.owner_user_id || '') === String(ownerId);
              const okDate = String(route.date || '') === String(dateVal);
              return okClient && okProject && okOwner && okDate;
            });
            if (!matches.length) {
              panel.className = 'rp-assigned-panel empty';
              panel.innerHTML = 'Não existe rota atribuída para a seleção atual.';
              return;
            }
            panel.className = 'rp-assigned-panel';
            panel.innerHTML = '<strong>Já existe rota atribuída para esta seleção:</strong>' + matches.map(function(route){
              const title = 'Rota #' + route.id + ' | ' + (route.project_name || 'Sem campanha') + ' | ' + (route.owner_name || 'Sem owner');
              const meta = [route.date || '', route.status || '', (parseInt(route.stops_count || 0, 10) || 0) + ' PDVs'].filter(Boolean).join(' | ');
              return '<div class="rp-assigned-hit"><strong>'+escapeHtml(title)+'</strong><div class="rp-muted">'+escapeHtml(meta)+'</div><div class="rp-actions" style="margin-top:8px"><a class="button button-small" href="'+escapeHtml(route.edit_url || '#')+'">Editar</a><a class="button button-small" href="'+escapeHtml(route.csv_url || '#')+'">CSV</a><a class="button button-small" href="'+escapeHtml(route.xls_url || '#')+'">Excel</a><a class="button button-small" href="'+escapeHtml(route.pdf_url || '#')+'">PDF</a></div></div>';
            }).join('');
          }

          function updateRoutesExportLinks(){
            const clientId = document.getElementById('rp-client')?.value || '';
            const projectId = document.getElementById('rp-project')?.value || '';
            const ownerId = document.getElementById('rp-owner')?.value || '';
            const dateVal = document.getElementById('rp-date')?.value || '';
            const dateFrom = document.getElementById('rp-routes-date-from')?.value || '';
            const dateTo = document.getElementById('rp-routes-date-to')?.value || '';
            const useRange = !!(dateFrom || dateTo);
            document.querySelectorAll('.rp-routes-export').forEach(function(link){
              try {
                const url = new URL(link.href, window.location.origin);
                ['client_id','project_id','owner_user_id','date','date_from','date_to','route_id'].forEach(function(k){ url.searchParams.delete(k); });
                if (clientId) url.searchParams.set('client_id', clientId);
                if (projectId) url.searchParams.set('project_id', projectId);
                if (ownerId) url.searchParams.set('owner_user_id', ownerId);
                if (useRange) {
                  if (dateFrom) url.searchParams.set('date_from', dateFrom);
                  if (dateTo) url.searchParams.set('date_to', dateTo);
                } else if (dateVal) {
                  url.searchParams.set('date', dateVal);
                }
                link.href = url.toString();
              } catch(e) {}
            });
          }

          const bulkDeleteForm = document.getElementById('rp-bulk-delete-form');
          const selectAllRoutes = document.getElementById('rp-select-all-routes');
          document.querySelectorAll('.rp-route-checkbox').forEach(function(cb){
            cb.addEventListener('change', updateBulkDeleteState);
          });
          if (selectAllRoutes) {
            selectAllRoutes.addEventListener('change', function(){
              document.querySelectorAll('#rp-existing-routes-body tr').forEach(function(row){
                if (row.style.display === 'none') return;
                const cb = row.querySelector('.rp-route-checkbox');
                if (cb) cb.checked = !!selectAllRoutes.checked;
              });
              updateBulkDeleteState();
            });
          }
          if (bulkDeleteForm) {
            bulkDeleteForm.addEventListener('submit', function(e){
              const total = document.querySelectorAll('.rp-route-checkbox:checked').length;
              if (!total) {
                e.preventDefault();
                window.alert('Seleciona pelo menos uma rota.');
                return;
              }
              if (!window.confirm('Apagar ' + total + ' rota(s) selecionada(s)?')) {
                e.preventDefault();
              }
            });
          }

          let lastExistingItems = [], lastGoogleItems = [];
          let existingMapItems = [];

          function normalizePoint(p){
            return {
              route_stop_id: parseInt(p.route_stop_id || 0, 10) || 0,
              location_id: parseInt(p.location_id || p.id || 0, 10) || 0,
              status: p.status || 'pending',
              arrived_at: p.arrived_at || '',
              departed_at: p.departed_at || '',
              duration_s: parseInt(p.duration_s || 0, 10) || 0,
              fail_reason: p.fail_reason || '',
              name: p.name || p.location_name || '',
              address: p.address || '',
              phone: p.phone || '',
              email: p.email || '',
              contact_person: p.contact_person || '',
              district: p.district || '',
              county: p.county || '',
              city: p.city || '',
              postal_code: p.postal_code || '',
              visit_time_min: parseInt(p.visit_time_min || 0, 10) || 0,
              visit_time_mode: p.visit_time_mode || '',
              category_id: parseInt(p.category_id || 0, 10) || 0,
              subcategory_id: parseInt(p.subcategory_id || 0, 10) || 0,
              place_id: p.place_id || '',
              lat: toNum(p.lat),
              lng: toNum(p.lng),
              source: p.source || (p.location_id ? 'existing' : 'route_capture'),
              position_locked: !!p.position_locked,
              workspace_preserve_order: !!p.workspace_preserve_order,
              _selected: !!p._selected
            };
          }
          function toNum(v){ const n = parseFloat(v); return Number.isFinite(n) ? n : null; }
          function escapeHtml(s){ return String(s || '').replace(/[&<>\"]/g, function(ch){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[ch]; }); }
          function updateHidden(){ routePointsInput.value = JSON.stringify(routePoints); }
          function formatMinutes(totalMinutes){
            const mins = Math.max(0, parseInt(totalMinutes || 0, 10) || 0);
            const h = Math.floor(mins / 60);
            const m = mins % 60;
            if (h && m) return h + 'h ' + m + 'm';
            if (h) return h + 'h';
            return m + 'm';
          }
          function visitOptionsHtml(selected){
            const current = Math.max(0, parseInt(selected || 0, 10) || 0);
            const out = ['<option value="">Sem tempo de visita</option>'];
            for (let mins = 15; mins <= 360; mins += 15) {
              out.push('<option value="'+mins+'"'+(mins===current?' selected':'')+'>'+formatMinutes(mins)+'</option>');
            }
            out.push('<option value="manual"'+((current>0 && current % 15 !== 0)?' selected':'')+'>Manual</option>');
            return out.join('');
          }
          function filterSubcats(){
            const pid = String(catEl.value || '');
            const current = String(subcatEl.value || '');
            const items = pid && subcatsByParent[pid] ? subcatsByParent[pid] : [];
            const seen = new Set();
            const options = ['<option value="">Subcategoria</option>'];
            items.forEach(function(item){
              const name = String(item && item.name || '').trim();
              const key = name.toLocaleLowerCase('pt-PT');
              if (!name || seen.has(key)) return;
              seen.add(key);
              const id = parseInt(item.id || 0, 10) || 0;
              options.push('<option value="'+id+'"'+((String(id) === current || (!current && String(id) === String(initialSubcategoryId))) ? ' selected' : '')+'>'+escapeHtml(name)+'</option>');
            });
            subcatEl.innerHTML = options.join('');
            if (current && !items.some(function(item){ return String(item.id) === current; })) {
              subcatEl.value = '';
            }
          }
          function uniqueList(items){ return Array.from(new Set((items || []).filter(Boolean))).sort((a,b)=>String(a).localeCompare(String(b), 'pt')); }
          function repopulate(selectEl, items, selected, placeholder){
            const opts = ['<option value="">'+placeholder+'</option>'].concat((items || []).map(function(item){
              const safe = String(item).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
              return '<option value="'+safe+'"'+(item===selected?' selected':'')+'>'+safe+'</option>';
            }));
            selectEl.innerHTML = opts.join('');
          }
          function dedupeLocationItems(items){
            const seen = new Map();
            (items || []).forEach(function(raw){
              const item = normalizePoint(raw || {});
              const key = item.location_id
                ? ('id:' + item.location_id)
                : (item.place_id
                    ? ('place:' + item.place_id)
                    : ('nameaddr:' + String(item.name || '').trim().toLowerCase() + '|' + String(item.address || '').trim().toLowerCase()));
              if (!seen.has(key)) {
                seen.set(key, Object.assign({}, raw, item));
                return;
              }
              const prev = seen.get(key) || {};
              const score = function(v){
                let s = 0;
                if (v.phone) s += 2;
                if (v.email) s += 2;
                if (v.contact_person) s += 1;
                if (Number.isFinite(parseFloat(v.lat)) && Number.isFinite(parseFloat(v.lng))) s += 2;
                if (v.address) s += 1;
                return s;
              };
              if (score(item) >= score(prev)) seen.set(key, Object.assign({}, prev, raw, item));
            });
            return Array.from(seen.values());
          }
          function syncGeo(){
            const district = districtEl.value || '';
            const selectedCounty = countyEl.value || '';
            const selectedCity = cityEl.value || '';
            const allCounties = uniqueList(Object.values(geoCounties).flat());
            const allCities = uniqueList(Object.values(geoCities).flat());
            repopulate(countyEl, district ? (geoCounties[district] || []) : allCounties, selectedCounty, 'Concelho');
            repopulate(cityEl, district ? (geoCities[district] || []) : allCities, selectedCity, 'Cidade');
          }
          filterSubcats();
          catEl.addEventListener('change', filterSubcats);
          districtEl.addEventListener('change', syncGeo);
          syncGeo();

          async function reloadProjectsForClient(){
            const clientId = document.getElementById('rp-client').value || '';
            const projectEl = document.getElementById('rp-project');
            if(!clientId){ projectEl.innerHTML = '<option value="">--</option>'; return; }
            const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            const res = await fetch(ajaxUrl + '?action=routespro_projects_for_client&client_id=' + encodeURIComponent(clientId), {credentials:'same-origin'});
            if(!res.ok) return;
            const items = await res.json();
            const current = projectEl.value || '';
            projectEl.innerHTML = ['<option value="">--</option>'].concat((items||[]).map(function(p){ return '<option value="'+String(p.id)+'">'+String(p.name)+'</option>'; })).join('');
            if(current) projectEl.value = current;
          }
          document.getElementById('rp-client').addEventListener('change', reloadProjectsForClient);

          function currentSearchParams(){
            const p = new URLSearchParams();
            const clientId = document.getElementById('rp-client')?.value || '';
            const projectId = document.getElementById('rp-project')?.value || '';
            const ownerId = document.getElementById('rp-owner')?.value || '';
            if (clientId) p.set('client_id', clientId);
            if (projectId) p.set('project_id', projectId);
            if (ownerId) p.set('owner_user_id', ownerId);
            if (districtEl.value) p.set('district', districtEl.value);
            if (countyEl.value) p.set('county', countyEl.value);
            if (cityEl.value) p.set('city', cityEl.value);
            if (catEl.value) p.set('category_id', catEl.value);
            if (subcatEl.value) p.set('subcategory_id', subcatEl.value);
            const q = document.getElementById('rp-discovery-q').value || '';
            if (q) p.set('q', q);
            return p;
          }

          function samePoint(a,b){
            if (a.location_id && b.location_id && String(a.location_id) === String(b.location_id)) return true;
            if (a.place_id && b.place_id && a.place_id === b.place_id) return true;
            return !!a.name && !!a.address && a.name === b.name && a.address === b.address;
          }
          function normalizePhone(v){ return String(v || '').replace(/\D+/g, ''); }
          function showDedupeNotice(message, type){
            const el = document.getElementById('rp-dedupe-notice');
            if (!el) return;
            el.style.display = 'block';
            el.className = 'notice inline ' + ((type === 'warning') ? 'notice-warning' : 'notice-info');
            el.innerHTML = '<p style="margin:8px 0">' + escapeHtml(message) + '</p>';
          }
          function clearDedupeNotice(){ const el = document.getElementById('rp-dedupe-notice'); if (el) { el.style.display = 'none'; el.innerHTML=''; } }
          function findDuplicateCandidate(point){
            const p = normalizePoint(point);
            const pool = [].concat(lastExistingItems || [], existingMapItems || []);
            for (const item of pool) {
              const candidate = normalizePoint(item);
              if (samePoint(candidate, p)) return {item:candidate, reason:'nome e morada'};
              if (candidate.place_id && p.place_id && candidate.place_id === p.place_id) return {item:candidate, reason:'place_id'};
              const cp = normalizePhone(candidate.phone), pp = normalizePhone(p.phone);
              if (cp && pp && cp === pp) return {item:candidate, reason:'telefone'};
              if (candidate.email && p.email && String(candidate.email).toLowerCase() === String(p.email).toLowerCase()) return {item:candidate, reason:'email'};
            }
            return null;
          }
          function addPointToRoute(point){
            if(!workspaceEditable){ window.alert('Esta rota já não permite alterações de planeamento.'); return; }
            const n = normalizePoint(point);
            if (!n.name && !n.address) return;
            if (catEl.value && !n.category_id) n.category_id = parseInt(catEl.value, 10) || 0;
            if (subcatEl.value && !n.subcategory_id) n.subcategory_id = parseInt(subcatEl.value, 10) || 0;
            if (!n.district) n.district = districtEl.value || '';
            if (!n.county) n.county = countyEl.value || '';
            if (!n.city) n.city = cityEl.value || '';
            const dup = findDuplicateCandidate(n);
            if (dup && !n.location_id) {
              n.location_id = parseInt(dup.item.location_id || dup.item.id || 0, 10) || 0;
              n.source = 'existing';
              n.place_id = n.place_id || dup.item.place_id || '';
              n.phone = n.phone || dup.item.phone || '';
              n.email = n.email || dup.item.email || '';
              n.contact_person = n.contact_person || dup.item.contact_person || '';
              showDedupeNotice('Este PDV já existe na base. Foi associado ao registo existente por ' + dup.reason + '. Se alterares os dados e guardares a rota, o último save substitui o registo existente.', 'warning');
            } else {
              clearDedupeNotice();
            }
            if (routePoints.some(function(existing){ return samePoint(existing, n); })) return;
            routePoints.push(n);
            if(workspaceLiveAdjustable) markWorkspaceOrder(true);
            else { updateHidden(); renderQueue(); renderPlanner(); }
          renderExistingRoutesTable(1);
          }

          function populateInlineFromPoint(point){
            const n = normalizePoint(point || {});
            document.getElementById('rp-new-name').value = n.name || '';
            document.getElementById('rp-new-phone').value = n.phone || '';
            document.getElementById('rp-new-contact').value = n.contact_person || '';
            document.getElementById('rp-new-email').value = n.email || '';
            document.getElementById('rp-new-address').value = n.address || '';
            document.getElementById('rp-new-district').value = n.district || districtEl.value || '';
            document.getElementById('rp-new-county').value = n.county || countyEl.value || '';
            document.getElementById('rp-new-city').value = n.city || cityEl.value || '';
            const postalEl = document.getElementById('rp-new-postal-code');
            if (postalEl) postalEl.value = n.postal_code || '';
            document.getElementById('rp-new-lat').value = n.lat ?? '';
            document.getElementById('rp-new-lng').value = n.lng ?? '';
            const placeIdEl = document.getElementById('rp-new-place-id');
            if (placeIdEl) placeIdEl.value = n.place_id || '';
            if (newCatEl) {
              newCatEl.value = String(n.category_id || catEl.value || '');
              refreshInlineSubcategories();
            }
            if (newSubcatEl) {
              newSubcatEl.value = String(n.subcategory_id || subcatEl.value || '');
            }
            const note = document.getElementById('rp-new-fill-note');
            if (note) note.textContent = 'PDV preparado a partir da descoberta. Revê categoria, subcategoria e dados antes de adicionares à rota.';
          }

          function cardHtml(item, source){
            const meta = [item.category_name || '', item.city || '', item.phone || ''].filter(Boolean).join(' • ');
            const buttonText = source === 'google' ? 'Usar no formulário' : 'Adicionar à rota';
            return '<div class="rp-result"><h4>'+escapeHtml(item.name || 'PDV')+'</h4><div class="rp-muted">'+escapeHtml(item.address || '')+'</div>'+
              (meta ? '<div style="font-size:12px;margin:6px 0;color:#475569">'+escapeHtml(meta)+'</div>' : '')+
              '<div class="rp-actions"><button type="button" class="button button-small rp-add-point" data-source="'+escapeHtml(source)+'">'+buttonText+'</button></div></div>';
          }

          async function runExistingLookup(){
            const params = currentSearchParams();
            params.set('per_page','100');
            params.set('only_active','1');
            const wrap = document.getElementById('rp-existing-results');
            const select = document.getElementById('rp-existing-select');
            const res = await fetch(api + 'commercial-search?' + params.toString(), {credentials:'same-origin', headers:{'X-WP-Nonce': nonce}});
            if (!res.ok) { wrap.innerHTML = '<em>Falha a carregar PDVs existentes.</em>'; select.innerHTML = '<option value="">Escolhe um PDV existente</option>'; lastExistingItems = []; existingMapItems = []; return []; }
            const data = await res.json();
            const items = dedupeLocationItems((data.items || []).map(function(item){
              item.text = [item.name || '', item.city || '', item.address || ''].filter(Boolean).join(' | ');
              return item;
            }));
            lastExistingItems = items;
            existingMapItems = items.filter(function(item){ return Number.isFinite(parseFloat(item.lat)) && Number.isFinite(parseFloat(item.lng)); });
            select.innerHTML = '<option value="">Escolhe um PDV existente</option>' + items.map(function(item, idx){ return '<option value="'+idx+'">'+escapeHtml(item.text || item.name || 'PDV')+'</option>'; }).join('');
            select.onchange = function(){
              const idx = parseInt(this.value, 10);
              if (Number.isFinite(idx) && items[idx]) {
                populateInlineFromPoint(items[idx]);
                addPointToRoute(items[idx]);
              }
            };
            wrap.innerHTML = items.length ? items.map(function(item){ return cardHtml(item, 'existing'); }).join('') : '<em>Sem PDVs existentes para este filtro.</em>';
            wrap.querySelectorAll('.rp-add-point').forEach(function(btn, idx){ btn.addEventListener('click', function(){ populateInlineFromPoint(items[idx]); addPointToRoute(items[idx]); }); });
            return items;
          }

          let geocoder = null, placesService = null;
          function setupGooglePlaces(){
            if (!(window.google && google.maps && google.maps.places)) return false;
            if (!geocoder) geocoder = new google.maps.Geocoder();
            if (!placesService) placesService = new google.maps.places.PlacesService(document.createElement('div'));
            return true;
          }
          function currentZoneQuery(){
            return [cityEl && cityEl.value, countyEl && countyEl.value, districtEl && districtEl.value, routingCountryLabel()].filter(Boolean).join(', ');
          }
          function currentKeyword(){
            const cat = catEl && catEl.selectedOptions && catEl.selectedOptions[0] ? catEl.selectedOptions[0].textContent.trim() : '';
            const sub = subcatEl && subcatEl.selectedOptions && subcatEl.selectedOptions[0] ? subcatEl.selectedOptions[0].textContent.trim() : '';
            const q = document.getElementById('rp-discovery-q').value || '';
            return [sub || cat, q].filter(Boolean).join(' ').trim() || 'estabelecimentos';
          }
          function googleTypeFromText(text){
            const t = String(text || '').toLowerCase();
            if (t.includes('hotel')) return 'lodging';
            if (t.includes('rest')) return 'restaurant';
            if (t.includes('cafe') || t.includes('café')) return 'cafe';
            if (t.includes('bar')) return 'bar';
            if (t.includes('super')) return 'supermarket';
            if (t.includes('loja') || t.includes('retalho') || t.includes('store')) return 'store';
            if (t.includes('farm')) return 'pharmacy';
            return '';
          }
          function geocodeAddress(address){
            return new Promise(function(resolve, reject){
              if (!setupGooglePlaces()) return reject(new Error('Google Maps Places não está disponível.'));
              const code=routingCountryForSelection();
              geocoder.geocode({address: address,region:code.toLowerCase(),componentRestrictions:{country:code}}, function(results, status){
                if (status !== 'OK' || !results || !results[0]) return reject(new Error('Não foi possível localizar a zona selecionada.'));
                resolve(results[0]);
              });
            });
          }
          function pagedSearch(methodName, request){
            return new Promise(function(resolve, reject){
              if (!setupGooglePlaces()) return reject(new Error('Google Maps Places não está disponível.'));
              const out = [];
              const handler = function(results, status, pagination){
                if (status !== google.maps.places.PlacesServiceStatus.OK && status !== google.maps.places.PlacesServiceStatus.ZERO_RESULTS) return reject(new Error('Google Places devolveu: ' + status));
                out.push(...(results || []));
                if (pagination && pagination.hasNextPage && out.length < 60) {
                  window.setTimeout(function(){ pagination.nextPage(); }, 2200);
                  return;
                }
                resolve(out);
              };
              if (methodName === 'textSearch') placesService.textSearch(request, handler);
              else placesService.nearbySearch(request, handler);
            });
          }
          function getPlaceDetails(placeId){
            return new Promise(function(resolve){
              if (!placeId || !setupGooglePlaces()) return resolve({});
              placesService.getDetails({placeId: placeId, fields:['formatted_phone_number','international_phone_number','website','name','formatted_address','address_components','geometry','place_id']}, function(result, status){
                if (status !== google.maps.places.PlacesServiceStatus.OK || !result) return resolve({});
                resolve(result);
              });
            });
          }
          function parseAddressComponents(components){
            const out = {district:'', county:'', city:''};
            (components || []).forEach(function(c){
              const types = c.types || [];
              if (!out.city && (types.includes('locality') || types.includes('postal_town'))) out.city = c.long_name || '';
              if (!out.county && types.includes('administrative_area_level_2')) out.county = c.long_name || '';
              if (!out.district && types.includes('administrative_area_level_1')) out.district = c.long_name || '';
            });
            return out;
          }
          async function runGoogleDiscovery(){
            const wrap = document.getElementById('rp-google-results');
            if (!setupGooglePlaces()) {
              wrap.innerHTML = '<em>Ativa Google Maps com Places nas Settings para descobrir novos PDVs.</em>';
              lastGoogleItems = [];
              drawDiscoveryMarkers(existingMapItems, []);
              return [];
            }
            const zone = currentZoneQuery();
            if (!zone) {
              wrap.innerHTML = '<em>Escolhe primeiro Distrito, Concelho ou Cidade.</em>';
              lastGoogleItems = [];
              drawDiscoveryMarkers(existingMapItems, []);
              return [];
            }
            try {
              const geo = await geocodeAddress(zone);
              const center = geo.geometry.location;
              const label = currentKeyword();
              const googleType = googleTypeFromText(label);
              const radius = cityEl && cityEl.value ? 15000 : (countyEl && countyEl.value ? 35000 : 80000);
              const textRequest = {query: label + ' em ' + zone, location: center, radius: radius};
              const nearbyRequest = {location: center, radius: radius, keyword: label};
              if (googleType) nearbyRequest.type = googleType;
              const textResults = await pagedSearch('textSearch', textRequest).catch(function(){ return []; });
              const nearbyResults = await pagedSearch('nearbySearch', nearbyRequest).catch(function(){ return []; });
              const merged = new Map();
              [].concat(textResults || [], nearbyResults || []).forEach(function(item){
                const key = item.place_id || ((item.name || '') + '|' + (item.formatted_address || item.vicinity || ''));
                if (!merged.has(key)) merged.set(key, item);
              });
              const items = [];
              for (const item of Array.from(merged.values()).slice(0, 60)) {
                const details = await getPlaceDetails(item.place_id);
                const parsed = parseAddressComponents((details && details.address_components) || item.address_components || []);
                const geometry = (details && details.geometry) || item.geometry || null;
                const loc = geometry && geometry.location ? geometry.location : null;
                items.push({
                  name: (details && details.name) || item.name || '',
                  address: (details && details.formatted_address) || item.formatted_address || item.vicinity || '',
                  district: parsed.district || (districtEl.value || ''),
                  county: parsed.county || (countyEl.value || ''),
                  city: parsed.city || (cityEl.value || ''),
                  lat: loc ? (typeof loc.lat === 'function' ? loc.lat() : loc.lat) : null,
                  lng: loc ? (typeof loc.lng === 'function' ? loc.lng() : loc.lng) : null,
                  place_id: item.place_id || (details && details.place_id) || '',
                  phone: (details && (details.international_phone_number || details.formatted_phone_number)) || item.formatted_phone_number || '',
                  email: '',
                  contact_person: '',
                  source: 'google'
                });
              }
              const deduped = dedupeLocationItems(Array.from(new Map(items.map(function(item){ return [item.place_id || ((item.name || '') + '|' + (item.address || '')), item]; })).values()));
              lastGoogleItems = deduped;
              wrap.innerHTML = deduped.length ? deduped.map(function(item){ return cardHtml(item, 'google'); }).join('') : '<em>Sem novos PDVs encontrados no Google para este filtro.</em>';
              wrap.querySelectorAll('.rp-add-point').forEach(function(btn, idx){ btn.addEventListener('click', function(){ addPointToRoute(deduped[idx]); }); });
              drawDiscoveryMarkers(existingMapItems, deduped);
              return deduped;
            } catch(err) {
              wrap.innerHTML = '<em>' + escapeHtml(err && err.message ? err.message : 'A descoberta Google falhou.') + '</em>';
              lastGoogleItems = [];
              drawDiscoveryMarkers(existingMapItems, []);
              return [];
            }
          }

          async function runDiscovery(){
            const btn = document.getElementById('rp-run-discovery');
            btn.disabled = true;
            btn.textContent = 'A procurar...';
            try {
              const existing = await runExistingLookup();
              drawDiscoveryMarkers(existingMapItems, []);
              await runGoogleDiscovery();
            }
            finally {
              btn.disabled = false;
              btn.textContent = 'Procurar PDVs';
            }
          }
          document.getElementById('rp-run-discovery').addEventListener('click', runDiscovery);
          document.getElementById('rp-discovery-q').addEventListener('keydown', function(ev){ if (ev.key === 'Enter') { ev.preventDefault(); runDiscovery(); } });

          const newCatEl = document.getElementById('rp-new-category');
          const newSubcatEl = document.getElementById('rp-new-subcategory');
          function refreshInlineSubcategories(){
            if (!newSubcatEl) return;
            const pid = parseInt((newCatEl && newCatEl.value) || '0', 10) || 0;
            const options = ['<option value="">Subcategoria</option>'];
            (subcatsByParent[String(pid)] || subcatsByParent[pid] || []).forEach(function(item){ options.push('<option value="'+String(item.id)+'">'+escapeHtml(item.name || '')+'</option>'); });
            newSubcatEl.innerHTML = options.join('');
          }
          if (newCatEl) newCatEl.addEventListener('change', refreshInlineSubcategories);
          refreshInlineSubcategories();
          document.getElementById('rp-add-manual').addEventListener('click', function(){
            addPointToRoute({
              name: document.getElementById('rp-new-name').value || '',
              address: document.getElementById('rp-new-address').value || '',
              phone: document.getElementById('rp-new-phone').value || '',
              contact_person: document.getElementById('rp-new-contact').value || '',
              email: document.getElementById('rp-new-email').value || '',
              district: document.getElementById('rp-new-district').value || '',
              county: document.getElementById('rp-new-county').value || '',
              city: document.getElementById('rp-new-city').value || '',
              postal_code: document.getElementById('rp-new-postal-code')?.value || '',
              category_id: parseInt((newCatEl && newCatEl.value) || '0', 10) || 0,
              subcategory_id: parseInt((newSubcatEl && newSubcatEl.value) || '0', 10) || 0,
              lat: document.getElementById('rp-new-lat').value || '',
              lng: document.getElementById('rp-new-lng').value || '',
              place_id: document.getElementById('rp-new-place-id').value || '',
              source: 'route_capture'
            });
          });
          document.getElementById('rp-clear-queue').addEventListener('click', function(){ if(!workspaceFullEditable){ window.alert('Numa rota ativa só podes remover lojas pendentes individualmente ou em seleção.'); return; } if(routePoints.length&&!window.confirm('Limpar todas as lojas da rota?'))return; routePoints = []; markWorkspaceOrder(true); renderQueue(); renderPlanner(); });

          function isOperationalPoint(point){
            const status=String(point?.status||'pending').toLowerCase();
            return !!String(point?.arrived_at||'') || !!String(point?.departed_at||'') || !['pending','planned','draft'].includes(status);
          }
          function isPointMutable(point){ return workspaceFullEditable || (workspaceLiveAdjustable && !isOperationalPoint(point)); }
          function lastProtectedIndex(){ let last=-1; routePoints.forEach(function(point,index){ if(isOperationalPoint(point)) last=index; }); return last; }
          function setWorkspaceManualLock(locked){
            const hidden=document.getElementById('rp-manual-order-locked');
            if(hidden) hidden.value=locked?'1':'0';
            const mode=document.getElementById('rp-workspace-mode');
            if(mode) mode.textContent=workspaceLiveAdjustable?'Ajuste de rota ativa':(locked?'Ordem manual bloqueada':'Rascunho editável');
          }
          function markWorkspaceOrder(lockGlobal){
            routePoints.forEach(function(point){ point.workspace_preserve_order=true; });
            if(lockGlobal!==false) setWorkspaceManualLock(true);
            pendingOptimizedOrder=null;
            const apply=document.getElementById('rp-apply-optimized'); if(apply) apply.disabled=true;
            updateHidden();
          }
          function updatePointSelectionState(){
            const mutable=routePoints.filter(isPointMutable);
            const selected=mutable.filter(function(point){return !!point._selected;}).length;
            const remove=document.getElementById('rp-remove-selected');
            if(remove) remove.disabled=!workspaceEditable||selected===0;
            const all=document.getElementById('rp-select-all-points');
            if(all){ all.checked=mutable.length>0&&selected===mutable.length; all.indeterminate=selected>0&&selected<mutable.length; all.disabled=!workspaceEditable||mutable.length===0; }
          }
          function moveWorkspacePoint(from,to){
            if(!workspaceEditable||from===to||from<0||to<0||from>=routePoints.length||to>=routePoints.length) return;
            if(!isPointMutable(routePoints[from])) return window.alert('As lojas executadas ou em curso estão protegidas.');
            if(workspaceLiveAdjustable && to<=lastProtectedIndex()) return window.alert('As lojas pendentes só podem ser reorganizadas depois da última visita executada ou em curso.');
            const moved=routePoints.splice(from,1)[0]; routePoints.splice(to,0,moved);
            markWorkspaceOrder(true); renderQueue(); renderPlanner();
          }
          function renderQueue(){
            const wrap = document.getElementById('rp-route-queue');
            const visitSummaryEl = document.getElementById('rp-summary-visit-time');
            const totalVisitMin = routePoints.reduce(function(sum, point){ return sum + (parseInt(point.visit_time_min || 0, 10) || 0); }, 0);
            if (visitSummaryEl) visitSummaryEl.textContent = formatMinutes(totalVisitMin);
            if (!routePoints.length) {
              wrap.innerHTML = '<em>Ainda não adicionaste PDVs à rota.</em>';
              document.getElementById('rp-summary-count').textContent = '0';
              updatePointSelectionState();
              return;
            }
            wrap.innerHTML = routePoints.map(function(item, idx){
              const role = idx === 0 ? 'Início' : (idx === routePoints.length - 1 ? 'Fim' : 'Paragem');
              const currentVisit = parseInt(item.visit_time_min || 0, 10) || 0;
              const showManual = currentVisit > 0 && currentVisit % 15 !== 0;
              const operationalStatus = String(item.status || 'pending').toLowerCase();
              const isCompleted = operationalStatus === 'done' || operationalStatus === 'completed';
              const statusLabel = isCompleted ? 'Efetuada' : (operationalStatus === 'in_progress' || operationalStatus === 'active' || operationalStatus === 'started' ? 'Em curso' : (operationalStatus === 'failed' ? 'Falhada' : 'Por visitar'));
              const mutable=isPointMutable(item);
              const routeDateValue = String(document.getElementById('rp-date')?.value || '');
              const canResetPending = currentRouteId > 0 && !isCompleted && ['in_progress','active','started','arrived'].includes(operationalStatus) && !String(item.departed_at || '');
              const canReopen = currentRouteId > 0 && ['done','completed','failed'].includes(operationalStatus);
              const canManualComplete = currentRouteId > 0 && !isCompleted && !['cancelled','canceled'].includes(operationalStatus) && routeDateValue !== '' && routeDateValue <= wpToday;
              const classes=['rp-queue-item']; if(item._selected) classes.push('rp-selected'); if(item.position_locked) classes.push('rp-position-locked'); if(!mutable) classes.push('rp-operational-protected');
              return '<div class="'+classes.join(' ')+'" data-route-index="'+idx+'" draggable="'+(mutable?'true':'false')+'">'+
                '<div><input type="checkbox" data-select-point="'+idx+'" '+(item._selected?'checked':'')+' '+(!mutable?'disabled':'')+' aria-label="Selecionar loja"></div>'+
                '<div><div class="rp-drag-handle" title="Arrastar para reordenar">⋮⋮</div><div class="rp-queue-no">'+(idx+1)+'</div></div>'+
                '<div><span class="rp-badge">'+escapeHtml(role)+'</span><span class="rp-badge">'+escapeHtml(item.source || 'pdv')+'</span><span class="rp-badge">'+escapeHtml(statusLabel)+'</span>'+(item.position_locked?'<span class="rp-badge" style="background:#fff7ed;border-color:#fdba74;color:#9a3412">Posição fixa</span>':'')+'<div style="font-weight:700;color:#0f172a">'+escapeHtml(item.name || item.address || 'PDV')+'</div><div class="rp-muted">'+escapeHtml(item.address || '')+'</div><div style="font-size:12px;color:#475569;margin-top:4px">'+escapeHtml([item.phone || '', item.email || '', item.contact_person || ''].filter(Boolean).join(' • '))+'</div>'+
                '<div style="margin-top:10px;display:grid;grid-template-columns:minmax(0,1fr) 120px;gap:8px;align-items:center">'+
                  '<select data-visit-select="'+idx+'" '+(!mutable?'disabled':'')+'>'+visitOptionsHtml(currentVisit)+'</select>'+
                  '<input type="number" min="1" max="360" step="1" data-visit-manual="'+idx+'" value="'+(showManual ? currentVisit : '')+'" placeholder="Minutos" style="display:'+(showManual ? 'block' : 'none')+'" '+(!mutable?'disabled':'')+'>'+
                '</div><div class="rp-muted" style="margin-top:6px">Tempo visita: '+escapeHtml(currentVisit ? formatMinutes(currentVisit) : 'não definido')+'</div></div>'+
                '<div class="rp-actions">'+(canReopen?'<button type="button" class="button button-small" data-reopen-stop="'+idx+'" style="border-color:#7c3aed;color:#6d28d9">Reabrir loja</button>':'')+(canResetPending?'<button type="button" class="button button-small" data-reset-pending="'+idx+'" style="border-color:#f59e0b;color:#92400e">Repor como pendente</button>':'')+(canManualComplete?'<button type="button" class="button button-small" data-complete-manual="'+idx+'" style="border-color:#16a34a;color:#166534">Concluir manualmente</button>':'')+'<button type="button" class="button button-small" data-lock="'+idx+'" '+(!mutable?'disabled':'')+'>'+(item.position_locked?'Desbloquear':'Fixar posição')+'</button><button type="button" class="button button-small" data-up="'+idx+'" '+(!mutable||idx===0||workspaceLiveAdjustable&&idx-1<=lastProtectedIndex()?'disabled':'')+'>↑</button><button type="button" class="button button-small" data-down="'+idx+'" '+(!mutable||idx===routePoints.length-1?'disabled':'')+'>↓</button><button type="button" class="button button-small" data-remove="'+idx+'" '+(!mutable?'disabled':'')+'>Remover</button></div>'+
              '</div>';
            }).join('');
            document.getElementById('rp-summary-count').textContent = String(routePoints.length);
            wrap.querySelectorAll('[data-select-point]').forEach(function(input){ input.addEventListener('change',function(){ const idx=parseInt(input.dataset.selectPoint,10); if(routePoints[idx]) routePoints[idx]._selected=!!input.checked; renderQueue(); }); });
            wrap.querySelectorAll('[data-visit-select]').forEach(function(select){
              select.addEventListener('change', function(){
                const idx = parseInt(select.dataset.visitSelect, 10); if (!Number.isFinite(idx) || !routePoints[idx]) return;
                const manualInput = wrap.querySelector('[data-visit-manual="'+idx+'"]');
                if (select.value === 'manual') { routePoints[idx].visit_time_mode = 'manual'; if (manualInput) manualInput.style.display = 'block'; routePoints[idx].visit_time_min = parseInt((manualInput && manualInput.value) || routePoints[idx].visit_time_min || 0, 10) || 0; }
                else { routePoints[idx].visit_time_mode = select.value ? 'bucket' : ''; routePoints[idx].visit_time_min = parseInt(select.value || 0, 10) || 0; if (manualInput) { manualInput.style.display = 'none'; manualInput.value = ''; } }
                markWorkspaceOrder(false); renderQueue(); renderPlanner();
              });
            });
            wrap.querySelectorAll('[data-visit-manual]').forEach(function(input){
              const applyManual = function(){ const idx=parseInt(input.dataset.visitManual,10); if(!Number.isFinite(idx)||!routePoints[idx]) return; const val=Math.max(0,Math.min(360,parseInt(input.value||0,10)||0)); routePoints[idx].visit_time_mode=val?'manual':''; routePoints[idx].visit_time_min=val; markWorkspaceOrder(false); renderQueue(); renderPlanner(); };
              input.addEventListener('change',applyManual); input.addEventListener('blur',applyManual);
            });
            wrap.querySelectorAll('[data-up]').forEach(function(btn){ btn.addEventListener('click',function(){ const idx=parseInt(btn.dataset.up,10); moveWorkspacePoint(idx,idx-1); }); });
            wrap.querySelectorAll('[data-down]').forEach(function(btn){ btn.addEventListener('click',function(){ const idx=parseInt(btn.dataset.down,10); moveWorkspacePoint(idx,idx+1); }); });
            wrap.querySelectorAll('[data-lock]').forEach(function(btn){ btn.addEventListener('click',function(){ const idx=parseInt(btn.dataset.lock,10); if(!routePoints[idx]) return; routePoints[idx].position_locked=!routePoints[idx].position_locked; routePoints[idx].workspace_preserve_order=true; pendingOptimizedOrder=null; updateHidden(); renderQueue(); renderPlanner(); }); });
            wrap.querySelectorAll('[data-reopen-stop]').forEach(function(btn){ btn.addEventListener('click', async function(){
              const idx=parseInt(btn.dataset.reopenStop,10), item=routePoints[idx]||{};
              const stopId=parseInt(item.route_stop_id||0,10)||0;
              if(!stopId) return window.alert('Não foi possível identificar esta visita. Atualiza a página e tenta novamente.');
              const reason=window.prompt('Indica o motivo da reabertura desta loja:', 'Correção de fecho efetuado por engano');
              if(reason===null) return;
              if(!String(reason||'').trim()) return window.alert('O motivo da reabertura é obrigatório para manter a auditoria.');
              if(!window.confirm('Reabrir esta loja? O reporte, respostas e fotografias existentes serão preservados como histórico. A loja volta a pendente e só uma nova confirmação/submissão a poderá fechar novamente.')) return;
              const previousText=btn.textContent; btn.disabled=true; btn.textContent='A reabrir...';
              try{
                const response=await fetch(api+'stops/'+stopId+'/reopen',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({reason:String(reason||'')})});
                const data=await response.json().catch(function(){return {};});
                if(!response.ok) throw new Error(data.message||'Não foi possível reabrir a loja.');
                item.status='pending'; item.arrived_at=''; item.departed_at=''; item.duration_s=0; item.fail_reason=''; item.real_lat=null; item.real_lng=null; item._selected=false;
                updateHidden(); renderQueue(); renderPlanner();
                window.alert('Loja reaberta. O histórico foi preservado e a rota voltou ao estado operacional adequado. Já podes editar, remover ou reordenar esta loja e guardar o ajuste.');
              }catch(error){
                btn.disabled=false; btn.textContent=previousText;
                window.alert(error&&error.message?error.message:'Não foi possível reabrir a loja.');
              }
            }); });
            wrap.querySelectorAll('[data-reset-pending]').forEach(function(btn){ btn.addEventListener('click', async function(){
              const idx=parseInt(btn.dataset.resetPending,10), item=routePoints[idx]||{};
              const stopId=parseInt(item.route_stop_id||0,10)||0;
              if(!stopId) return window.alert('Não foi possível identificar esta visita. Atualiza a página e tenta novamente.');
              const reason=window.prompt('Indica o motivo da reposição para pendente:', 'Correção operacional no backoffice');
              if(reason===null) return;
              if(!window.confirm('Repor esta visita como pendente? O check-in e os tempos operacionais serão limpos. Formulários e fotografias submetidos nunca são apagados.')) return;
              const previousText=btn.textContent; btn.disabled=true; btn.textContent='A repor...';
              try{
                const response=await fetch(api+'stops/'+stopId+'/reset-pending',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({reason:String(reason||'Correção operacional no backoffice')})});
                const data=await response.json().catch(function(){return {};});
                if(!response.ok) throw new Error(data.message||'Não foi possível repor a visita como pendente.');
                item.status='pending'; item.arrived_at=''; item.departed_at=''; item.duration_s=0; item.fail_reason=''; item.real_lat=null; item.real_lng=null; item._selected=false;
                updateHidden(); renderQueue(); renderPlanner();
                window.alert('Visita reposta como pendente. Já podes removê-la, substituí-la ou reordená-la e guardar o ajuste da rota.');
              }catch(error){
                btn.disabled=false; btn.textContent=previousText;
                window.alert(error&&error.message?error.message:'Não foi possível repor a visita como pendente.');
              }
            }); });
            wrap.querySelectorAll('[data-complete-manual]').forEach(function(btn){ btn.addEventListener('click', async function(){
              const idx=parseInt(btn.dataset.completeManual,10), item=routePoints[idx]||{};
              const stopId=parseInt(item.route_stop_id||0,10)||0;
              if(!stopId) return window.alert('Não foi possível identificar esta visita. Atualiza a página e tenta novamente.');
              const routeDate=String(document.getElementById('rp-date')?.value||'');
              const defaultCompletedAt=routeDate===wpToday?wpNowLocal:(routeDate?routeDate+'T18:00':wpNowLocal);
              const completedAt=window.prompt('Data e hora em que a visita ficou concluída. Formato: AAAA-MM-DD HH:MM', defaultCompletedAt.replace('T',' '));
              if(completedAt===null) return;
              const reason=window.prompt('Indica o motivo desta correção administrativa:', 'Visita realizada no terreno e não concluída na aplicação');
              if(reason===null) return;
              if(!window.confirm('Concluir manualmente esta visita? Se existir um rascunho sincronizado do merchandiser, o FieldFlow vai primeiro recuperar as respostas, fotografias e ficheiros e convertê-los num reporte real. Só depois fecha a visita.')) return;
              const previousText=btn.textContent; btn.disabled=true; btn.textContent='A concluir...';
              try{
                const requestBody={completed_at:String(completedAt||'').replace(' ','T'),reason:String(reason||''),allow_without_draft:false};
                let response=await fetch(api+'stops/'+stopId+'/complete-manual',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify(requestBody)});
                let data=await response.json().catch(function(){return {};});
                if(!response.ok&&data.code==='fieldflow_visit_manual_no_recoverable_data'){
                  const proceed=window.confirm((data.message||'Não existe informação sincronizada no servidor para recuperar.')+'\n\nFechar mesmo assim, sem criar qualquer reporte?');
                  if(!proceed){btn.disabled=false;btn.textContent=previousText;return;}
                  requestBody.allow_without_draft=true;
                  response=await fetch(api+'stops/'+stopId+'/complete-manual',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify(requestBody)});
                  data=await response.json().catch(function(){return {};});
                }
                if(!response.ok) throw new Error(data.message||'Não foi possível concluir manualmente a visita.');
                const stop=data.stop||{};
                item.status='done';
                item.arrived_at=String(stop.arrived_at||data.completed_at||item.arrived_at||'');
                item.departed_at=String(stop.departed_at||data.completed_at||'');
                item.duration_s=parseInt(stop.duration_s||0,10)||0;
                item.fail_reason='';
                item._selected=false;
                updateHidden(); renderQueue(); renderPlanner();
                const submissionCount=parseInt(data.submission_count||0,10)||0;
                const recovery=data.draft_recovery||{};
                if(recovery.applied){
                  window.alert('Visita concluída. O rascunho do merchandiser foi recuperado como reporte real, incluindo '+(parseInt(recovery.files||0,10)||0)+' ficheiro(s) sincronizado(s).');
                }else if(recovery.found&&recovery.status==='skipped_existing_submission'){
                  window.alert('Visita concluída. Já existia um reporte final, por isso o rascunho residual não substituiu os dados submetidos.');
                }else{
                  window.alert(submissionCount>0?'Visita concluída manualmente e progresso da rota recalculado. O reporte existente foi preservado.':'Visita concluída manualmente e progresso da rota recalculado. Não existia um rascunho sincronizado no servidor para converter em reporte.');
                }
              }catch(error){
                btn.disabled=false; btn.textContent=previousText;
                window.alert(error&&error.message?error.message:'Não foi possível concluir manualmente a visita.');
              }
            }); });
            wrap.querySelectorAll('[data-remove]').forEach(function(btn){ btn.addEventListener('click', function(){
              const idx=parseInt(btn.dataset.remove,10), item=routePoints[idx]||{};
              if(!isPointMutable(item)) return window.alert('Esta loja já foi executada ou está em curso e não pode ser removida.');
              routePoints.splice(idx,1); markWorkspaceOrder(true); renderQueue(); renderPlanner();
            }); });
            let dragIndex=null;
            wrap.querySelectorAll('[data-route-index]').forEach(function(row){
              row.addEventListener('dragstart',function(ev){ const idx=parseInt(row.dataset.routeIndex,10); if(!workspaceEditable||!isPointMutable(routePoints[idx])){ev.preventDefault();return;} dragIndex=idx; row.classList.add('rp-dragging'); if(ev.dataTransfer) ev.dataTransfer.effectAllowed='move'; });
              row.addEventListener('dragend',function(){ row.classList.remove('rp-dragging'); dragIndex=null; });
              row.addEventListener('dragover',function(ev){ if(workspaceEditable) ev.preventDefault(); });
              row.addEventListener('drop',function(ev){ ev.preventDefault(); const target=parseInt(row.dataset.routeIndex,10); if(Number.isFinite(dragIndex)&&Number.isFinite(target)) moveWorkspacePoint(dragIndex,target); });
            });
            updatePointSelectionState();
          }

          function workspacePointKey(point){
            const id=parseInt(point.location_id||point.id||0,10)||0; if(id) return 'id:'+id;
            if(point.place_id) return 'place:'+String(point.place_id);
            return 'text:'+String(point.name||'').toLowerCase()+'|'+String(point.address||'').toLowerCase();
          }
          function mergeLockedWorkspaceOrder(candidate){
            const lockedKeys=new Set(routePoints.filter(function(point){return !!point.position_locked;}).map(workspacePointKey));
            const candidateUnlocked=candidate.filter(function(point){return !lockedKeys.has(workspacePointKey(point));});
            let cursor=0;
            return routePoints.map(function(original){ if(original.position_locked) return original; return candidateUnlocked[cursor++]||original; });
          }
          function workspaceMetricText(metrics){
            metrics=metrics||{};
            const km=Number(metrics.distance_km??metrics.known_distance_km??0);
            const travel=Number(metrics.travel_min??0);
            const total=Number(metrics.total_min??travel);
            return (Number.isFinite(km)?km.toFixed(1)+' km':'--')+' · '+formatMinutes(Number.isFinite(total)?total:0);
          }
          function showWorkspaceComparison(currentMetrics,optimizedMetrics,savingKm,savingMinutes){
            const box=document.getElementById('rp-workspace-comparison');
            const current=document.getElementById('rp-compare-current');
            const optimized=document.getElementById('rp-compare-optimized');
            if(box) box.style.display='grid';
            if(current) current.textContent=workspaceMetricText(currentMetrics);
            if(optimized) optimized.textContent=workspaceMetricText(optimizedMetrics)+(Number(savingKm)>0.05?' · poupa '+Number(savingKm).toFixed(1)+' km':'')+(Number(savingMinutes)>0.5?' e '+Math.round(Number(savingMinutes))+' min':'');
          }
          async function fallbackWorkspaceOptimization(){
            const body=new URLSearchParams();
            const value=function(id){return document.getElementById(id)?.value||'';};
            const cleanPoints=routePoints.map(function(point){ const copy=Object.assign({},point); delete copy.workspace_preserve_order; delete copy.position_locked; delete copy._selected; return copy; });
            body.set('action','routespro_route_metrics_preview'); body.set('nonce',routeMetricsPreviewNonce); body.set('date',value('rp-date'));
            body.set('points',JSON.stringify(cleanPoints));
            body.set('start',JSON.stringify({address:value('rp-start-address'),lat:value('rp-start-lat'),lng:value('rp-start-lng'),provenance:'manual',allow_matches_stop:document.getElementById('rp-start-at-first-stop')?.checked?1:0}));
            body.set('end',JSON.stringify({address:value('rp-end-address'),lat:value('rp-end-lat'),lng:value('rp-end-lng'),provenance:'manual',allow_matches_stop:document.getElementById('rp-end-at-last-stop')?.checked?1:0}));
            const response=await fetch(ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()});
            const payload=await response.json();
            if(!response.ok||!payload.success) throw new Error(payload?.data?.message||'Não foi possível comparar a rota.');
            const ordered=Array.isArray(payload.data?.ordered_points)?payload.data.ordered_points.map(normalizePoint):routePoints.slice();
            return {ordered:mergeLockedWorkspaceOrder(ordered),baseline:lastWorkspaceMetrics||{},optimized:payload.data?.metrics||{},saving_km:0,saving_minutes:0};
          }
          async function compareAndOptimizeWorkspace(){
            if(!workspaceEditable) return window.alert('A rota já não permite alterações de planeamento.');
            if(workspaceLiveAdjustable) return window.alert('A otimização automática fica reservada ao segmento ainda não iniciado. Nesta build, ajusta manualmente as lojas pendentes.');
            if(routePoints.length<2) return window.alert('Adiciona pelo menos duas lojas para comparar a ordem.');
            const btn=document.getElementById('rp-optimize-workspace'); if(btn){btn.disabled=true;btn.textContent='A comparar...';}
            try{
              const ids=routePoints.map(function(point){return parseInt(point.location_id||point.id||0,10)||0;});
              let result;
              if(routePoints.length<=8&&ids.every(Boolean)){
                const start={address:document.getElementById('rp-start-address')?.value||'',lat:document.getElementById('rp-start-lat')?.value||'',lng:document.getElementById('rp-start-lng')?.value||'',provenance:'manual',allow_matches_stop:document.getElementById('rp-start-at-first-stop')?.checked?1:0};
                const end={address:document.getElementById('rp-end-address')?.value||'',lat:document.getElementById('rp-end-lat')?.value||'',lng:document.getElementById('rp-end-lng')?.value||'',provenance:'manual',allow_matches_stop:document.getElementById('rp-end-at-last-stop')?.checked?1:0};
                const locked=routePoints.filter(function(point){return point.position_locked;}).map(function(point){return parseInt(point.location_id||point.id||0,10)||0;}).filter(Boolean);
                const payload={route_id:currentRouteId,client_id:parseInt(document.getElementById('rp-client')?.value||0,10)||0,project_id:parseInt(document.getElementById('rp-project')?.value||0,10)||0,location_ids:ids,locked_location_ids:locked,start_point:start,end_point:end,mode:'internal',manual_order_locked:0};
                const response=await fetch(api+'route-optimization/preview',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify(payload)});
                const data=await response.json(); if(!response.ok) throw new Error(data.message||'Falha ao otimizar.');
                const lookup=new Map(routePoints.map(function(point){return [parseInt(point.location_id||point.id||0,10)||0,point];}));
                const ordered=(data.ordered_location_ids||[]).map(function(id){return lookup.get(parseInt(id,10));}).filter(Boolean);
                result={ordered:ordered.length===routePoints.length?ordered:routePoints.slice(),baseline:data.baseline||{},optimized:data.optimized||{},saving_km:data.saving_km||0,saving_minutes:data.saving_minutes||0};
              }else{
                result=await fallbackWorkspaceOptimization();
              }
              pendingOptimizedOrder=result.ordered.map(function(point){return Object.assign({},point,{_selected:false,workspace_preserve_order:true});});
              showWorkspaceComparison(result.baseline,result.optimized,result.saving_km,result.saving_minutes);
              const apply=document.getElementById('rp-apply-optimized'); if(apply) apply.disabled=false;
            }catch(error){ window.alert(error.message||'Não foi possível comparar a rota.'); }
            finally{ if(btn){btn.disabled=false;btn.textContent='Comparar e otimizar';} }
          }
          const selectAllPoints=document.getElementById('rp-select-all-points');
          if(selectAllPoints) selectAllPoints.addEventListener('change',function(){ routePoints.forEach(function(point){point._selected=isPointMutable(point)?!!selectAllPoints.checked:false;}); renderQueue(); });
          const removeSelected=document.getElementById('rp-remove-selected');
          if(removeSelected) removeSelected.addEventListener('click',function(){ const selected=routePoints.filter(function(point){return !!point._selected&&isPointMutable(point);}); if(!selected.length)return; if(!window.confirm('Remover '+selected.length+' loja(s) pendente(s) do plano ativo? O histórico será preservado ao guardar.'))return; routePoints=routePoints.filter(function(point){return !(point._selected&&isPointMutable(point));}); markWorkspaceOrder(true); renderQueue(); renderPlanner(); });
          const optimizeWorkspace=document.getElementById('rp-optimize-workspace'); if(optimizeWorkspace) optimizeWorkspace.addEventListener('click',compareAndOptimizeWorkspace);
          const applyOptimized=document.getElementById('rp-apply-optimized'); if(applyOptimized) applyOptimized.addEventListener('click',function(){ if(!pendingOptimizedOrder)return; routePoints=pendingOptimizedOrder.map(normalizePoint); routePoints.forEach(function(point){point.workspace_preserve_order=true;point._selected=false;}); pendingOptimizedOrder=null; setWorkspaceManualLock(false); applyOptimized.disabled=true; updateHidden(); renderQueue(); renderPlanner(); });

          function updateAddressDatalist(){
            const projectId = document.getElementById('rp-project')?.value || '0';
            const options = campaignAddressOptions[projectId] || [];
            const list = document.getElementById('rp-address-options');
            if (!list) return;
            list.innerHTML = options.map(function(opt){
              const value = opt.label || opt.address || '';
              const label = opt.address || value;
              return '<option value="' + escapeHtml(value) + '" label="' + escapeHtml(label) + '"></option>';
            }).join('');
          }


          async function updateOwnerOptions(){
            const ownerSel = document.getElementById('rp-owner');
            if (!ownerSel) return;
            const current = ownerSel.value || '';
            const clientId = document.getElementById('rp-client')?.value || '0';
            const projectId = document.getElementById('rp-project')?.value || '0';
            const url = new URL(ownerAjaxUrl, window.location.origin);
            url.searchParams.set('action', 'routespro_users');
            if (clientId && clientId !== '0') url.searchParams.set('client_id', clientId);
            if (projectId && projectId !== '0') url.searchParams.set('project_id', projectId);
            try {
              const res = await fetch(url.toString(), {credentials:'same-origin'});
              const rows = await res.json();
              const options = Array.isArray(rows) ? rows : [];
              ownerSel.innerHTML = '<option value="">--</option>' + options.map(function(u){
                const id = parseInt(u.ID || 0, 10);
                const label = u.label || u.displayName || u.username || ('User ' + id);
                return '<option value="' + id + '">' + escapeHtml(label) + '</option>';
              }).join('');
              if (current) ownerSel.value = current;
              if (current && ownerSel.value !== current) {
                const opt = document.createElement('option');
                opt.value = current;
                opt.textContent = 'Owner atual #' + current;
                ownerSel.appendChild(opt);
                ownerSel.value = current;
              }
            } catch(err) {}
          }

          function endpointDefaultsForSelection(){
            const projectId = document.getElementById('rp-project')?.value || '0';
            const ownerId = document.getElementById('rp-owner')?.value || '0';
            const cfg = routeEndpointConfigs[String(projectId)] || null;
            if (!cfg) return null;
            const operator = cfg.profiles && (cfg.profiles[String(ownerId)] || cfg.profiles[Number(ownerId)]);
            if (operator && Number(operator.enabled || 0) === 1) return operator;
            if (cfg.default && Number(cfg.default.enabled || 0) === 1) return cfg.default;
            return null;
          }

          function applyRouteDefaults(){
            const routeId = document.querySelector('input[name="id"]')?.value || '0';
            if (routeId !== '0') return;
            const defaults = endpointDefaultsForSelection();
            if (!defaults) return;
            const start = defaults.start_point || {};
            const end = Number(defaults.return_to_start || 0) === 1 ? start : (defaults.end_point || {});
            if (start) {
              if (!document.getElementById('rp-start-address').value) document.getElementById('rp-start-address').value = start.address || '';
              if (!document.getElementById('rp-start-lat').value) document.getElementById('rp-start-lat').value = start.lat ?? '';
              if (!document.getElementById('rp-start-lng').value) document.getElementById('rp-start-lng').value = start.lng ?? '';
            }
            if (end) {
              if (!document.getElementById('rp-end-address').value) document.getElementById('rp-end-address').value = end.address || '';
              if (!document.getElementById('rp-end-lat').value) document.getElementById('rp-end-lat').value = end.lat ?? '';
              if (!document.getElementById('rp-end-lng').value) document.getElementById('rp-end-lng').value = end.lng ?? '';
            }
          }

          let routePlacesService = null;
          function getRoutePlaceDetails(placeId, cb){
            if (!googlePlacesContactEnrichment) { cb(null); return; }
            if (!(window.google && google.maps && google.maps.places) || !placeId) { cb(null); return; }
            if (!routePlacesService) routePlacesService = new google.maps.places.PlacesService(document.createElement('div'));
            routePlacesService.getDetails({placeId: placeId, fields:['name','formatted_address','geometry','address_components','place_id','formatted_phone_number','international_phone_number']}, function(place, status){
              cb(status === google.maps.places.PlacesServiceStatus.OK ? place : null);
            });
          }

          function loadGoogle(cb){
            if (!mapsKey) { cb(false); return; }
            if (window.FieldFlowGoogleMaps && typeof window.FieldFlowGoogleMaps.load === 'function') {
              window.FieldFlowGoogleMaps.load({key:mapsKey,libraries:['places'],language:'pt-PT',region:'PT'})
                .then(function(ok){ cb(!!ok); })
                .catch(function(){ cb(false); });
              return;
            }
            if (window.google && google.maps && google.maps.places && google.maps.places.Autocomplete) { cb(true); return; }
            const existing = document.querySelector('script[data-routespro-google="1"],script[src*="maps.googleapis.com/maps/api/js"]');
            if (existing) {
              let tries = 0;
              const wait = function(){
                tries++;
                if (window.google && google.maps && google.maps.places && google.maps.places.Autocomplete) { cb(true); return; }
                if (window.google && google.maps && typeof google.maps.importLibrary === 'function') {
                  google.maps.importLibrary('places').then(function(){ cb(!!(google.maps.places && google.maps.places.Autocomplete)); }).catch(function(){ cb(false); });
                  return;
                }
                if (tries >= 100) { cb(false); return; }
                setTimeout(wait, 120);
              };
              wait();
              return;
            }
            const callbackName = '__fieldflowRoutesGoogleReady_' + Math.random().toString(36).slice(2);
            window[callbackName] = function(){
              try { delete window[callbackName]; } catch (_) { window[callbackName] = undefined; }
              cb(!!(window.google && google.maps && google.maps.places && google.maps.places.Autocomplete));
            };
            const script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(mapsKey) + '&libraries=places&loading=async&callback=' + callbackName;
            script.async = true; script.defer = true; script.dataset.routesproGoogle = '1';
            script.onerror = function(){ try { delete window[callbackName]; } catch (_) { window[callbackName] = undefined; } cb(false); };
            document.head.appendChild(script);
          }

          function drawDiscoveryMarkers(existingItems, googleItems){
            if (!(window.google && google.maps)) return;
            if (!discoveryMap) discoveryMap = new google.maps.Map(document.getElementById('rp-discovery-map'), {center:{lat:39.5,lng:-8.0}, zoom:6});
            discoveryMarkers.forEach(function(m){ m.setMap(null); });
            discoveryMarkers = [];
            const bounds = new google.maps.LatLngBounds();
            const combined = [];
            (existingItems || []).forEach(function(item){ combined.push({item:item, source:'existing'}); });
            (googleItems || []).forEach(function(item){ combined.push({item:item, source:'google'}); });
            combined.forEach(function(entry){
              const item = entry.item || {};
              if (!Number.isFinite(parseFloat(item.lat)) || !Number.isFinite(parseFloat(item.lng))) return;
              const icon = entry.source === 'google' ? 'http://maps.google.com/mapfiles/ms/icons/red-dot.png' : 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png';
              const marker = new google.maps.Marker({
                map:discoveryMap,
                position:{lat:parseFloat(item.lat), lng:parseFloat(item.lng)},
                title:item.name || item.address || 'PDV',
                icon: icon
              });
              marker.addListener('click', function(){
                if (entry.source === 'google') {
                  populateInlineFromPoint(item);
                  document.getElementById('rp-new-name').focus();
                } else {
                  populateInlineFromPoint(item);
                  addPointToRoute(item);
                }
              });
              discoveryMarkers.push(marker);
              bounds.extend(marker.getPosition());
            });
            if (discoveryMarkers.length) discoveryMap.fitBounds(bounds);
          }

          function estimateTollEurFromKm(km){
            km = parseFloat(km || 0) || 0;
            if (km <= 0) return 0;
            const raw = km * 0.58 * 0.075;
            return Math.round(raw / 0.05) * 0.05;
          }
          function formatTollEur(value){
            value = parseFloat(value || 0) || 0;
            return value.toLocaleString('pt-PT', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €';
          }

          function sameRouteBase(origin,destination){
            if (!origin || !destination) return false;
            const dy=(Number(origin.lat)-Number(destination.lat))*111.2;
            const baseLat=Number(origin.lat)||39;
            const dx=(Number(origin.lng)-Number(destination.lng))*111.2*Math.max(.2,Math.cos(baseLat*Math.PI/180));
            return Math.sqrt(dx*dx+dy*dy)<=2;
          }
          function territorialDirection(point,base){
            const dy=(Number(point.lat)-Number(base.lat))*111.2;
            const dx=(Number(point.lng)-Number(base.lng))*111.2*Math.max(.2,Math.cos((Number(base.lat)||39)*Math.PI/180));
            if(Math.sqrt(dx*dx+dy*dy)<=2) return 'base';
            if(Math.abs(dy)>=Math.abs(dx)*.55) return dy>=0?'norte':'sul';
            return dx>=0?'este':'oeste';
          }
          function territorialLocality(point){
            return String(point.city||point.locality||point.county||point.district||point.address||'').trim().toLowerCase();
          }
          function groupTerritorialWaypoints(points,base){
            const directionOrder=[]; const groups={};
            points.forEach(function(point){
              const direction=territorialDirection(point,base); const locality=territorialLocality(point)||('sem-localidade-'+directionOrder.length);
              if(!groups[direction]){ groups[direction]={}; directionOrder.push(direction); }
              if(!groups[direction][locality]) groups[direction][locality]=[];
              groups[direction][locality].push(point);
            });
            directionOrder.sort(function(a,b){
              const firstA=Object.values(groups[a])[0]?.[0]||base; const firstB=Object.values(groups[b])[0]?.[0]||base;
              const da=Math.hypot(Number(firstA.lat)-Number(base.lat),Number(firstA.lng)-Number(base.lng));
              const db=Math.hypot(Number(firstB.lat)-Number(base.lat),Number(firstB.lng)-Number(base.lng));
              return da-db;
            });
            const ordered=[];
            directionOrder.forEach(function(direction){ Object.keys(groups[direction]).forEach(function(locality){ groups[direction][locality].forEach(function(point){ ordered.push(point); }); }); });
            return ordered;
          }

          let routeMetricsPreviewSerial = 0;
          function applyCanonicalMetrics(metrics){
            if(!metrics || typeof metrics!=='object') return false;
            lastWorkspaceMetrics = Object.assign({}, metrics);
            const numberOrNull=(value)=>value===null||value===undefined||value===''||Number.isNaN(Number(value))?null:Number(value);
            const km=(value)=>{ const n=numberOrNull(value); return n===null?'--':n.toLocaleString('pt-PT',{minimumFractionDigits:1,maximumFractionDigits:1})+' km'; };
            const endpointsRoutable=(metrics.endpoints_routable==null?(metrics.missing_endpoint_count==null||Number(metrics.missing_endpoint_count)===0):Number(metrics.endpoints_routable)!==0);
            const distanceComplete=String(metrics.distance_status||'').toLowerCase()==='complete' && endpointsRoutable;
            const totalDistance=distanceComplete?numberOrNull(metrics.distance_km):numberOrNull(metrics.known_distance_km);
            const travel=distanceComplete?numberOrNull(metrics.travel_min):null;
            const visit=numberOrNull(metrics.visit_min) ?? routePoints.reduce(function(sum, point){ return sum + (parseInt(point.visit_time_min || 0, 10) || 0); }, 0);
            const total=numberOrNull(metrics.total_min) ?? ((travel??0)+visit);
            const kmEl=document.getElementById('rp-summary-km');
            const timeEl=document.getElementById('rp-summary-time');
            const travelEl=document.getElementById('rp-summary-travel-time');
            const visitEl=document.getElementById('rp-summary-visit-time');
            const tollEl=document.getElementById('rp-summary-toll');
            const energyEl=document.getElementById('rp-summary-energy');
            const mobilityTotalEl=document.getElementById('rp-summary-mobility-total');
            const mobilityProfileEl=document.getElementById('rp-summary-mobility-profile');
            const accessEl=document.getElementById('rp-summary-access');
            const interEl=document.getElementById('rp-summary-inter');
            const returnEl=document.getElementById('rp-summary-return');
            const integrityEl=document.getElementById('rp-summary-integrity');
            const startPointEl=document.getElementById('rp-summary-start-point');
            const endPointEl=document.getElementById('rp-summary-end-point');
            const sourceEl=document.getElementById('rp-summary-source');
            const savingEl=document.getElementById('rp-summary-saving');
            const directionEl=document.getElementById('rp-summary-direction');
            const coherenceEl=document.getElementById('rp-summary-coherence');
            if(kmEl) kmEl.textContent=totalDistance!==null?km(totalDistance)+(distanceComplete?'':' conhecidos'):'--';
            if(timeEl) timeEl.textContent=total>0?formatMinutes(total):'--';
            if(travelEl) travelEl.textContent=travel!==null?formatMinutes(travel):'--';
            if(visitEl) visitEl.textContent=formatMinutes(visit);
            if(tollEl) tollEl.textContent=distanceComplete?formatTollEur(numberOrNull(metrics.toll_cost_eur)??0):'Por calcular';
            const mobility=(metrics.mobility_cost&&typeof metrics.mobility_cost==='object')?metrics.mobility_cost:null;
            const mobilityAvailable=mobility&&Number(mobility.enabled||0)===1&&Number(mobility.costs_available??1)===1&&distanceComplete;
            if(energyEl) energyEl.textContent=mobility&&Number(mobility.enabled||0)===1?(mobilityAvailable?formatTollEur(numberOrNull(mobility.energy_cost_eur)??0):'Por calcular'):'--';
            if(mobilityTotalEl) mobilityTotalEl.textContent=mobility&&Number(mobility.enabled||0)===1?(mobilityAvailable?formatTollEur(numberOrNull(mobility.total_mobility_cost_eur)??0):'Por calcular'):(distanceComplete?formatTollEur(numberOrNull(metrics.toll_cost_eur)??0):'Por calcular');
            if(mobilityProfileEl) mobilityProfileEl.textContent=mobility&&Number(mobility.enabled||0)===1?(mobilityAvailable?String(mobility.profile_label||'Perfil configurado'):'Por calcular, distância incompleta'):'Não configurado';
            if(accessEl) accessEl.textContent=km(metrics.access_distance_km);
            if(interEl) interEl.textContent=km(metrics.inter_stop_distance_km);
            if(returnEl) returnEl.textContent=km(metrics.return_distance_km);
            if(startPointEl) startPointEl.textContent=String(metrics.start_point_address||document.getElementById('rp-start-address')?.value||'Por definir');
            if(endPointEl) endPointEl.textContent=String(metrics.end_point_address||document.getElementById('rp-end-address')?.value||'Por definir');
            if(integrityEl){ const endpoint=String(metrics.endpoint_status||''); const warnings=Array.isArray(metrics.endpoint_warning_codes)?metrics.endpoint_warning_codes:[]; const startSuspicious=warnings.includes('start_matches_first_stop_unconfirmed')||endpoint==='suspicious_start'||endpoint==='suspicious_both'; const endSuspicious=warnings.includes('end_matches_last_stop_unconfirmed')||endpoint==='suspicious_end'||endpoint==='suspicious_both'; const confirmedStart=warnings.includes('start_matches_first_stop_confirmed'); const confirmedEnd=warnings.includes('end_matches_last_stop_confirmed'); if(distanceComplete && (startSuspicious||endSuspicious)){ integrityEl.textContent=startSuspicious&&endSuspicious?'Calculável · validar partida e chegada próximas dos PDVs extremos':(startSuspicious?'Calculável · validar partida próxima do 1.º PDV':'Calculável · validar chegada próxima do último PDV'); } else if(distanceComplete){ integrityEl.textContent='Completa'+((confirmedStart||confirmedEnd)?' · extremo coincidente confirmado':''); } else { integrityEl.textContent=endpoint==='missing_start'?'Incompleta, falta partida':(endpoint==='missing_end'?'Incompleta, falta chegada':(endpoint==='missing_both'?'Incompleta, faltam extremos':'Incompleta')); } }
            if(sourceEl) sourceEl.textContent=String(metrics.source_label||'Estimador FieldFlow');
            if(savingEl) savingEl.textContent=km(numberOrNull(metrics.optimization_saved_distance_km)??0);
            if(directionEl) directionEl.textContent=String(metrics.territory_dominant_direction_label||'Por calcular');
            if(coherenceEl) coherenceEl.textContent=String(metrics.territory_coherence_label||'Por calcular');
            return totalDistance!==null;
          }
          async function requestCanonicalRouteMetrics(){
            const serial=++routeMetricsPreviewSerial;
            if(!routePoints.length) return null;
            const value=(id)=>document.getElementById(id)?.value||'';
            const start={address:value('rp-start-address'),lat:value('rp-start-lat'),lng:value('rp-start-lng'),provenance:'manual',allow_matches_stop:document.getElementById('rp-start-at-first-stop')?.checked?1:0};
            const end={address:value('rp-end-address'),lat:value('rp-end-lat'),lng:value('rp-end-lng'),provenance:'manual',allow_matches_stop:document.getElementById('rp-end-at-last-stop')?.checked?1:0};
            const body=new URLSearchParams();
            body.set('action','routespro_route_metrics_preview');
            body.set('nonce',routeMetricsPreviewNonce);
            body.set('date',value('rp-date'));
            body.set('project_id',value('rp-project'));
            body.set('owner_user_id',value('rp-owner'));
            body.set('points',JSON.stringify(routePoints));
            body.set('start',JSON.stringify(start));
            body.set('end',JSON.stringify(end));
            try{
              const response=await fetch(ownerAjaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()});
              const json=await response.json();
              if(serial!==routeMetricsPreviewSerial) return null;
              if(!response.ok || !json || !json.success || !json.data) throw new Error(json?.data?.message||'Falha no cálculo');
              const metrics=json.data.metrics||{};
              applyCanonicalMetrics(metrics);
              const legs=Array.isArray(json.data.legs)?json.data.legs:[];
              const legsEl=document.getElementById('rp-route-legs');
              if(legsEl && legs.length && !(window.google&&google.maps)){
                legsEl.innerHTML=legs.map(function(leg,idx){
                  const detail=[leg.distance_km!=null?Number(leg.distance_km).toLocaleString('pt-PT',{maximumFractionDigits:1})+' km':'distância por validar',leg.travel_min!=null?Math.round(Number(leg.travel_min))+' min':'tempo por validar'].join(' · ');
                  return '<div class="rp-result"><h4>'+escapeHtml((idx+1)+'. '+(leg.name||'PDV'))+'</h4><div class="rp-muted">'+escapeHtml(leg.address||'')+'</div><div style="font-size:12px;color:#475569;margin-top:6px">'+escapeHtml(detail)+'</div></div>';
                }).join('');
              }
              return metrics;
            }catch(error){
              if(serial===routeMetricsPreviewSerial){
                const sourceEl=document.getElementById('rp-summary-source');
                if(sourceEl) sourceEl.textContent='Estimador temporariamente indisponível';
              }
              return null;
            }
          }

          async function renderPlanner(){
            const kmEl = document.getElementById('rp-summary-km');
            const timeEl = document.getElementById('rp-summary-time');
            const travelTimeEl = document.getElementById('rp-summary-travel-time');
            const visitTimeEl = document.getElementById('rp-summary-visit-time');
            const tollEl = document.getElementById('rp-summary-toll');
            const energyEl = document.getElementById('rp-summary-energy');
            const mobilityTotalEl = document.getElementById('rp-summary-mobility-total');
            const mobilityProfileEl = document.getElementById('rp-summary-mobility-profile');
            const accessEl = document.getElementById('rp-summary-access');
            const interEl = document.getElementById('rp-summary-inter');
            const returnEl = document.getElementById('rp-summary-return');
            const integrityEl = document.getElementById('rp-summary-integrity');
            const startPointEl = document.getElementById('rp-summary-start-point');
            const endPointEl = document.getElementById('rp-summary-end-point');
            const sourceEl = document.getElementById('rp-summary-source');
            const savingEl = document.getElementById('rp-summary-saving');
            const directionEl = document.getElementById('rp-summary-direction');
            const coherenceEl = document.getElementById('rp-summary-coherence');
            const legsEl = document.getElementById('rp-route-legs');
            if(startPointEl) startPointEl.textContent=String(document.getElementById('rp-start-address')?.value||'Por definir'); if(endPointEl) endPointEl.textContent=String(document.getElementById('rp-end-address')?.value||'Por definir');
            kmEl.textContent = routePoints.length ? 'A calcular...' : '--'; timeEl.textContent = routePoints.length ? 'A calcular...' : '--'; if (tollEl) tollEl.textContent = routePoints.length ? 'A calcular...' : '--'; if (energyEl) energyEl.textContent = routePoints.length ? 'A calcular...' : '--'; if (mobilityTotalEl) mobilityTotalEl.textContent = routePoints.length ? 'A calcular...' : '--'; if (mobilityProfileEl) mobilityProfileEl.textContent = routePoints.length ? 'A calcular...' : 'Não configurado'; if (travelTimeEl) travelTimeEl.textContent = routePoints.length ? 'A calcular...' : '--'; if (visitTimeEl) visitTimeEl.textContent = formatMinutes(routePoints.reduce(function(sum, point){ return sum + (parseInt(point.visit_time_min || 0, 10) || 0); }, 0)); if (accessEl) accessEl.textContent=routePoints.length?'A calcular...':'--'; if (interEl) interEl.textContent=routePoints.length?'A calcular...':'--'; if (returnEl) returnEl.textContent=routePoints.length?'A calcular...':'--'; if (integrityEl) integrityEl.textContent=routePoints.length?'A validar':'Por validar'; if (sourceEl) sourceEl.textContent=routePoints.length?'Motor FieldFlow':'Estimador FieldFlow'; if (savingEl) savingEl.textContent='Ao guardar'; if (directionEl) directionEl.textContent='Por calcular'; if (coherenceEl) coherenceEl.textContent='Por calcular';
            let canonicalApplied=false;
            requestCanonicalRouteMetrics().then(function(metrics){ canonicalApplied=!!metrics; });
            if (!(window.google && google.maps) || routePoints.length < 2) {
              if (routePoints.length < 2) legsEl.innerHTML = '<em>Adiciona pelo menos 2 PDVs para calcular o percurso.</em>';
              return;
            }
            const valid = routePoints.filter(function(p){ return Number.isFinite(toNum(p.lat)) && Number.isFinite(toNum(p.lng)); });
            if (valid.length < 2) { legsEl.innerHTML = '<em>Faltam coordenadas em alguns PDVs. Usa resultados existentes, Google ou preenche lat/lng no novo PDV.</em>'; return; }
            if (!routeMap) routeMap = new google.maps.Map(document.getElementById('rp-route-map'), {center:{lat:39.5,lng:-8.0}, zoom:6});
            if (!routeDirections) routeDirections = new google.maps.DirectionsRenderer({map: routeMap, suppressMarkers: false});
            const service = new google.maps.DirectionsService();
            const startLat = toNum(document.getElementById('rp-start-lat')?.value);
            const startLng = toNum(document.getElementById('rp-start-lng')?.value);
            const endLat = toNum(document.getElementById('rp-end-lat')?.value);
            const endLng = toNum(document.getElementById('rp-end-lng')?.value);
            const hasFixedStart = Number.isFinite(startLat) && Number.isFinite(startLng);
            const hasFixedEnd = Number.isFinite(endLat) && Number.isFinite(endLng);
            if (!hasFixedStart || !hasFixedEnd) {
              legsEl.innerHTML = '<em>Define partida e chegada reais ou configura a Base da rota no Centro de Atribuições. O FieldFlow não usa o primeiro ou último PDV como extremo implícito.</em>';
              if (kmEl) kmEl.textContent = '--';
              if (accessEl) accessEl.textContent = '--';
              if (interEl) interEl.textContent = '--';
              if (returnEl) returnEl.textContent = '--';
              if (integrityEl) integrityEl.textContent = !hasFixedStart && !hasFixedEnd ? 'Incompleta · faltam partida e chegada' : (!hasFixedStart ? 'Incompleta · falta partida' : 'Incompleta · falta chegada');
              if (sourceEl) sourceEl.textContent = 'Base da rota por definir';
              return;
            }
            const origin = {lat:startLat, lng:startLng};
            const destination = {lat:endLat, lng:endLng};
            const sameBase = sameRouteBase(origin,destination);
            const inputWaypointsRaw = valid;
            const inputWaypoints = sameBase ? groupTerritorialWaypoints(inputWaypointsRaw, origin) : inputWaypointsRaw;
            const waypoints = inputWaypoints.map(function(p){ return {location:{lat:toNum(p.lat), lng:toNum(p.lng)}, stopover:true}; });
            service.route({origin:origin, destination:destination, waypoints:waypoints, optimizeWaypoints:!sameBase, avoidTolls:false, travelMode:google.maps.TravelMode.DRIVING}, function(result, status){
              if (status !== 'OK') { legsEl.innerHTML = '<em>Não foi possível calcular a rota neste momento.</em>'; return; }
              routeDirections.setDirections(result);
              let meters = 0, seconds = 0;
              const legs = result.routes[0].legs || [];
              const waypointOrder = Array.isArray(result.routes[0].waypoint_order) ? result.routes[0].waypoint_order : [];
              const orderedWaypoints = waypointOrder.length===inputWaypoints.length ? waypointOrder.map(function(sourceIndex){ return inputWaypoints[sourceIndex]; }).filter(Boolean) : inputWaypoints;
              const originPoint = {name:'Partida',address:document.getElementById('rp-start-address')?.value||''};
              const destinationPoint = {name:'Chegada',address:document.getElementById('rp-end-address')?.value||''};
              const orderedPath = [originPoint].concat(orderedWaypoints).concat([destinationPoint]);
              legsEl.innerHTML = legs.map(function(leg, idx){
                meters += leg.distance.value || 0; seconds += leg.duration.value || 0;
                const fromPoint = orderedPath[idx] || {};
                const toPoint = orderedPath[idx+1] || {};
                const fromLabel = fromPoint.name || fromPoint.address || ('Ponto '+(idx+1));
                const toLabel = toPoint.name || toPoint.address || ('Ponto '+(idx+2));
                return '<div class="rp-result"><h4>'+escapeHtml(fromLabel+' → '+toLabel)+'</h4><div class="rp-muted">'+escapeHtml(leg.start_address || '')+'</div><div style="font-size:12px;color:#475569;margin-top:6px">'+escapeHtml((leg.distance?.text || '--') + ' • ' + (leg.duration?.text || '--'))+'</div></div>';
              }).join('');
              const totalKm = meters/1000;
              const accessKm = legs.length ? ((legs[0].distance?.value||0)/1000) : 0;
              const returnKm = legs.length ? ((legs[legs.length-1].distance?.value||0)/1000) : 0;
              const interKm = Math.max(0, totalKm-accessKm-returnKm);
              if(!canonicalApplied){
                kmEl.textContent = totalKm.toFixed(1) + ' km';
                if (accessEl) accessEl.textContent=accessKm.toFixed(1)+' km';
                if (interEl) interEl.textContent=interKm.toFixed(1)+' km';
                if (returnEl) returnEl.textContent=returnKm.toFixed(1)+' km';
                if (integrityEl) integrityEl.textContent='Completa';
                if (sourceEl) sourceEl.textContent='Google Maps preview';
                if (savingEl) savingEl.textContent=(result.routes[0].waypoint_order||[]).length?'Ordem otimizada':'0 km';
              }
              const routeVisitPoints=orderedWaypoints;
              const directionRuns=[]; let lastDirection=''; const localRuns=[]; let lastLocal=''; const directionCounts={};
              routeVisitPoints.forEach(function(point){ const d=territorialDirection(point,origin); const l=territorialLocality(point); if(d!=='base'){ directionCounts[d]=(directionCounts[d]||0)+1; if(d!==lastDirection){directionRuns.push(d);lastDirection=d;} } if(l&&l!==lastLocal){localRuns.push(l);lastLocal=l;} });
              const directionReentries=Math.max(0,directionRuns.length-(new Set(directionRuns)).size);
              const localityReentries=Math.max(0,localRuns.length-(new Set(localRuns)).size);
              const dominant=Object.keys(directionCounts).sort(function(a,b){return directionCounts[b]-directionCounts[a];})[0]||'base';
              const labels={norte:'Norte',sul:'Sul',este:'Este',oeste:'Oeste',base:'Zona da base'};
              const opposite=(directionCounts.norte&&directionCounts.sul)||(directionCounts.este&&directionCounts.oeste);
              const coherent=directionReentries===0&&localityReentries===0&&!opposite;
              const mins = Math.round(seconds/60);
              const visitMins = routePoints.reduce(function(sum, point){ return sum + (parseInt(point.visit_time_min || 0, 10) || 0); }, 0);
              if(!canonicalApplied){
                if(directionEl) directionEl.textContent=labels[dominant]||dominant;
                if(coherenceEl) coherenceEl.textContent=coherent?'Coerente':(directionReentries===0&&localityReentries===0?'Aceitável':'Fragmentada');
                if (tollEl) tollEl.textContent = formatTollEur(estimateTollEurFromKm(totalKm));
                if (travelTimeEl) travelTimeEl.textContent = formatMinutes(mins);
                if (visitTimeEl) visitTimeEl.textContent = formatMinutes(visitMins);
                timeEl.textContent = formatMinutes(mins + visitMins);
              }
            });
          }

          ['rp-client','rp-project','rp-owner','rp-date'].forEach(function(id){ const el = document.getElementById(id); if (el) el.addEventListener('change', function(){ if (id !== 'rp-owner' && id !== 'rp-date') updateOwnerOptions(); updateAddressDatalist(); applyRouteDefaults(); renderPlanner(); renderExistingRoutesTable(1); updateRoutesExportLinks(); }); });
          ['rp-routes-date-from','rp-routes-date-to'].forEach(function(id){ const el = document.getElementById(id); if (el) el.addEventListener('change', function(){ renderExistingRoutesTable(1); updateRoutesExportLinks(); }); });
          const clearRouteDateRange = document.getElementById('rp-clear-routes-date-range');
          if (clearRouteDateRange) clearRouteDateRange.addEventListener('click', function(){ const from = document.getElementById('rp-routes-date-from'); const to = document.getElementById('rp-routes-date-to'); if (from) from.value = ''; if (to) to.value = ''; renderExistingRoutesTable(1); updateRoutesExportLinks(); });
          updateOwnerOptions();

          // fieldflow-route-endpoint-coordinate-trust-v1: visible address edits invalidate hidden coordinates even when Google Places is unavailable.
          const routeEndpointFields=[['rp-start-address','rp-start-lat','rp-start-lng'],['rp-end-address','rp-end-lat','rp-end-lng']];
          routeEndpointFields.forEach(function(cfg){
            const input=document.getElementById(cfg[0]);
            if(!input) return;
            const latInput=document.getElementById(cfg[1]);
            const lngInput=document.getElementById(cfg[2]);
            input.addEventListener('input',function(){ if(latInput) latInput.value=''; if(lngInput) lngInput.value=''; });
            input.addEventListener('change',function(){ renderPlanner(); });
          });
          ['rp-start-at-first-stop','rp-end-at-last-stop'].forEach(function(id){ const el=document.getElementById(id); if(el) el.addEventListener('change',function(){ renderPlanner(); }); });

          loadGoogle(function(loaded){
            if (!loaded) {
              document.getElementById('rp-discovery-map-wrap').innerHTML = '<div class="rp-muted">Ativa Google Maps em Settings para ver o mapa de descoberta e o cálculo automático de distâncias.</div>';
              document.getElementById('rp-route-map').innerHTML = '<div class="rp-muted" style="padding:14px">Ativa Google Maps em Settings para calcular kms e tempo automaticamente.</div>';
            } else {
              const addressInput = document.getElementById('rp-new-address');
              if (addressInput) {
                const ac = new google.maps.places.Autocomplete(addressInput, {componentRestrictions:{country:routingCountryForSelection().toLowerCase()},fields:['formatted_address','geometry','name','address_components','place_id']});
                routeCountryAutocompletes.push(ac);
                ac.addListener('place_changed', function(){
                  const plc = ac.getPlace();
                  if (!plc) return;
                  const parsed = parseAddressComponents(plc.address_components || []);
                  document.getElementById('rp-new-name').value = document.getElementById('rp-new-name').value || plc.name || '';
                  document.getElementById('rp-new-address').value = plc.formatted_address || addressInput.value || '';
                  document.getElementById('rp-new-district').value = parsed.district || document.getElementById('rp-new-district').value || '';
                  document.getElementById('rp-new-county').value = parsed.county || document.getElementById('rp-new-county').value || '';
                  document.getElementById('rp-new-city').value = parsed.city || document.getElementById('rp-new-city').value || '';
                  if (plc.geometry) {
                    document.getElementById('rp-new-lat').value = String(plc.geometry.location.lat());
                    document.getElementById('rp-new-lng').value = String(plc.geometry.location.lng());
                  }
                  getRoutePlaceDetails(plc.place_id || '', function(details){
                    if (!details) return;
                    if (!document.getElementById('rp-new-phone').value) document.getElementById('rp-new-phone').value = details.international_phone_number || details.formatted_phone_number || '';
                    const components = details.address_components || plc.address_components || [];
                    const parsedDetails = parseAddressComponents(components);
                    if (!document.getElementById('rp-new-district').value) document.getElementById('rp-new-district').value = parsedDetails.district || '';
                    if (!document.getElementById('rp-new-county').value) document.getElementById('rp-new-county').value = parsedDetails.county || '';
                    if (!document.getElementById('rp-new-city').value) document.getElementById('rp-new-city').value = parsedDetails.city || '';
                  });
                });
              }
              routeEndpointFields.forEach(function(cfg){
                const input = document.getElementById(cfg[0]);
                if (!input) return;
                const latInput=document.getElementById(cfg[1]);
                const lngInput=document.getElementById(cfg[2]);
                const ac = new google.maps.places.Autocomplete(input, {componentRestrictions:{country:routingCountryForSelection().toLowerCase()},fields:['formatted_address','geometry','name','place_id','address_components']});
                routeCountryAutocompletes.push(ac);
                ac.addListener('place_changed', function(){
                  const plc = ac.getPlace();
                  if (!plc) return;
                  input.value = plc.formatted_address || input.value || '';
                  if (plc.geometry) {
                    if(latInput) latInput.value = String(plc.geometry.location.lat());
                    if(lngInput) lngInput.value = String(plc.geometry.location.lng());
                  }
                  renderPlanner();
                });
              });
            }
            updateAddressDatalist();
            syncRoutingCountry();
            applyRouteDefaults();
            renderQueue();
            renderPlanner();
            runDiscovery();
          });
          document.getElementById('rp-project')?.addEventListener('change', function(){ syncRoutingCountry(); });

          document.querySelectorAll('[data-workspace-action]').forEach(function(button){
            button.addEventListener('click',function(){ const action=document.getElementById('rp-route-workspace-action'); if(action) action.value=button.dataset.workspaceAction||'save'; });
          });
          if(!workspaceEditable){
            ['rp-remove-selected','rp-optimize-workspace','rp-apply-optimized','rp-clear-queue','rp-save-draft','rp-publish-route','rp-save-route','rp-add-manual'].forEach(function(id){ const el=document.getElementById(id); if(el) el.disabled=true; });
            const mode=document.getElementById('rp-workspace-mode'); if(mode) mode.textContent='Planeamento bloqueado';
          } else if(workspaceLiveAdjustable){
            ['rp-optimize-workspace','rp-apply-optimized','rp-clear-queue','rp-save-draft','rp-publish-route'].forEach(function(id){ const el=document.getElementById(id); if(el) el.disabled=true; });
            const mode=document.getElementById('rp-workspace-mode'); if(mode) mode.textContent='Ajuste de rota ativa';
          }
          document.getElementById('rp-route-form').addEventListener('submit', function(ev){
            const action=document.getElementById('rp-route-workspace-action')?.value||'save';
            if (!routePoints.length) {
              ev.preventDefault();
              alert('Adiciona pelo menos um PDV à rota antes de guardar.');
              return;
            }
            if(action==='publish'){
              const owner=document.getElementById('rp-owner')?.value||'';
              const start=String(document.getElementById('rp-start-address')?.value||'').trim();
              const end=String(document.getElementById('rp-end-address')?.value||'').trim();
              if(!owner){ ev.preventDefault(); alert('Seleciona o owner operacional antes de publicar.'); return; }
              if(!start||!end){ ev.preventDefault(); alert('Define o ponto de partida e o ponto de chegada antes de publicar.'); return; }
              const total=Number(lastWorkspaceMetrics?.total_min??0);
              if(Number.isFinite(total)&&total>480&&!window.confirm('A rota ultrapassa 8 horas estimadas. Publicar mesmo assim?')){ ev.preventDefault(); return; }
            }
            routePoints.forEach(function(point){ delete point._selected; });
            updateHidden();
          });
        });
        </script>
        <?php
        echo '</div>';
    }
    private static function resolve_route_point(array $point, int $client_id = 0, int $project_id = 0): array {
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $location_id = (int)($point['location_id'] ?? $point['id'] ?? 0);
        $resolved = [
            'route_stop_id' => absint($point['route_stop_id'] ?? 0),
            'location_id' => $location_id,
            'status' => sanitize_key((string)($point['status'] ?? 'pending')),
            'arrived_at' => sanitize_text_field((string)($point['arrived_at'] ?? '')),
            'departed_at' => sanitize_text_field((string)($point['departed_at'] ?? '')),
            'name' => sanitize_text_field((string)($point['name'] ?? '')),
            'address' => sanitize_text_field((string)($point['address'] ?? '')),
            'phone' => sanitize_text_field((string)($point['phone'] ?? '')),
            'email' => sanitize_email((string)($point['email'] ?? '')),
            'contact_person' => sanitize_text_field((string)($point['contact_person'] ?? '')),
            'district' => sanitize_text_field((string)($point['district'] ?? '')),
            'county' => sanitize_text_field((string)($point['county'] ?? '')),
            'city' => sanitize_text_field((string)($point['city'] ?? '')),
            'postal_code' => sanitize_text_field((string)($point['postal_code'] ?? '')),
            'country' => sanitize_text_field((string)($point['country'] ?? '')),
            'category_id' => (int)($point['category_id'] ?? 0),
            'subcategory_id' => (int)($point['subcategory_id'] ?? 0),
            'place_id' => sanitize_text_field((string)($point['place_id'] ?? '')),
            'lat' => is_numeric($point['lat'] ?? null) ? (float)$point['lat'] : null,
            'lng' => is_numeric($point['lng'] ?? null) ? (float)$point['lng'] : null,
            'source' => sanitize_text_field((string)($point['source'] ?? 'route_capture')),
            'visit_time_min' => max(0, (int)($point['visit_time_min'] ?? 0)),
            'visit_time_mode' => sanitize_text_field((string)($point['visit_time_mode'] ?? '')),
            'position_locked' => !empty($point['position_locked']) ? 1 : 0,
            'workspace_preserve_order' => !empty($point['workspace_preserve_order']) ? 1 : 0,
        ];
        if ($location_id > 0) {
            $loc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}locations WHERE id=%d LIMIT 1", $location_id), ARRAY_A);
            if (is_array($loc)) {
                foreach (['name','address','phone','email','contact_person','district','county','city','postal_code','country','place_id','source'] as $field) {
                    if ($resolved[$field] === '' && !empty($loc[$field])) $resolved[$field] = sanitize_text_field((string)$loc[$field]);
                }
                foreach (['category_id','subcategory_id'] as $field) {
                    if (empty($resolved[$field]) && !empty($loc[$field])) $resolved[$field] = (int)$loc[$field];
                }
                if ($resolved['lat'] === null && is_numeric($loc['lat'] ?? null)) $resolved['lat'] = (float)$loc['lat'];
                if ($resolved['lng'] === null && is_numeric($loc['lng'] ?? null)) $resolved['lng'] = (float)$loc['lng'];
            }
        }
        if (($resolved['lat'] === null || $resolved['lng'] === null) && $resolved['address'] !== '') {
            $countryCode = class_exists(RoutingGeographyService::class)
                ? RoutingGeographyService::normalizeCountryCode((string)($resolved['country'] ?: RoutingGeographyService::projectCountryCode($project_id)))
                : 'PT';
            $geo = self::geocode_address($resolved['address'], $countryCode);
            if ($geo) {
                $resolved['lat'] = $geo['lat'];
                $resolved['lng'] = $geo['lng'];
                if ($location_id > 0) {
                    $wpdb->update($px.'locations', ['lat' => $geo['lat'], 'lng' => $geo['lng']], ['id' => $location_id]);
                } elseif ($client_id || $project_id) {
                    $payload = [
                        'client_id' => $client_id, 'project_id' => $project_id, 'name' => $resolved['name'] ?: $resolved['address'], 'address' => $resolved['address'],
                        'district' => $resolved['district'], 'county' => $resolved['county'], 'city' => $resolved['city'], 'postal_code' => $resolved['postal_code'], 'country' => RoutingGeographyService::countryName($countryCode), 'category_id' => $resolved['category_id'] ?: null,
                        'subcategory_id' => $resolved['subcategory_id'] ?: null, 'contact_person' => $resolved['contact_person'], 'phone' => $resolved['phone'], 'email' => $resolved['email'],
                        'place_id' => $resolved['place_id'], 'lat' => $geo['lat'], 'lng' => $geo['lng'], 'source' => $resolved['source'] ?: 'route_capture', 'location_type' => 'pdv', 'is_active' => 1,
                    ];
                    $res = LocationDeduplicator::upsert($payload, 0, true);
                    $resolved['location_id'] = (int)($res['id'] ?? 0);
                }
            }
        }
        return $resolved;
    }

    /**
     * Resolve a route endpoint with the visible address as the authority.
     *
     * Route start/end fields keep hidden coordinates for fast previews. If the
     * operator edits the visible address after autocomplete/defaults, those
     * coordinates may become stale. Endpoints are therefore re-geocoded by
     * address when possible before routing so a new address can never reuse the
     * previous point's coordinates silently.
     */
    private static function resolve_route_endpoint(array $point, string $role = 'start', string $provenance = 'manual'): array {
        if (class_exists(RouteEndpointResolverService::class)) {
            return RouteEndpointResolverService::resolve($point, $role, $provenance, [
                'allow_matches_stop' => !empty($point['allow_matches_stop']) || !empty($point['_allow_matches_stop']),
                'force_address_authority' => true,
                'discard_stale_on_failure' => true,
            ]);
        }
        $rawAddress = trim(sanitize_text_field((string)($point['address'] ?? '')));
        $resolved = self::resolve_route_point($point);
        $resolved['provenance'] = sanitize_key((string)($point['provenance'] ?? $provenance)) ?: 'manual';
        $resolved['allow_matches_stop'] = !empty($point['allow_matches_stop']) ? 1 : 0;
        if ($rawAddress === '') return $resolved;

        $countryCode = class_exists(RoutingGeographyService::class) ? RoutingGeographyService::normalizeCountryCode((string)($point['country_code'] ?? 'PT')) : 'PT';
        $geo = self::geocode_address($rawAddress, $countryCode);
        if (is_array($geo) && isset($geo['lat'], $geo['lng']) && is_numeric($geo['lat']) && is_numeric($geo['lng'])) {
            $beforeLat = is_numeric($resolved['lat'] ?? null) ? (float)$resolved['lat'] : null;
            $beforeLng = is_numeric($resolved['lng'] ?? null) ? (float)$resolved['lng'] : null;
            $resolved['address'] = $rawAddress;
            $resolved['lat'] = (float)$geo['lat'];
            $resolved['lng'] = (float)$geo['lng'];
            $resolved['_endpoint_geocode_verified'] = 1;
            $resolved['_endpoint_coordinates_refreshed'] = ($beforeLat === null || $beforeLng === null || abs($beforeLat - (float)$geo['lat']) > 0.00001 || abs($beforeLng - (float)$geo['lng']) > 0.00001) ? 1 : 0;
        } else {
            $resolved['_endpoint_geocode_verified'] = 0;
        }
        return $resolved;
    }

    private static function endpoint_meta_point(array $point): array {
        if (class_exists(RouteEndpointResolverService::class)) return RouteEndpointResolverService::publicPoint($point);
        return [
            'address' => sanitize_text_field((string)($point['address'] ?? '')),
            'lat' => is_numeric($point['lat'] ?? null) ? (float)$point['lat'] : null,
            'lng' => is_numeric($point['lng'] ?? null) ? (float)$point['lng'] : null,
            'provenance' => sanitize_key((string)($point['provenance'] ?? 'legacy')) ?: 'legacy',
            'allow_matches_stop' => !empty($point['allow_matches_stop']) ? 1 : 0,
        ];
    }

    private static function geocode_address(string $address, string $countryCode = 'PT'): ?array {
        static $cache = [];
        $address = trim($address);
        if ($address === '') return null;
        $countryCode = class_exists(RoutingGeographyService::class) ? RoutingGeographyService::normalizeCountryCode($countryCode) : strtoupper($countryCode ?: 'PT');
        $cacheKey = $countryCode . '|' . $address;
        if (array_key_exists($cacheKey, $cache)) return $cache[$cacheKey];
        $provider = class_exists('\RoutesPro\Services\MapsFactory') ? MapsFactory::make() : null;
        if (!$provider || !method_exists($provider, 'geocode')) return $cache[$cacheKey] = null;
        $result = $provider->geocode($address, class_exists(RoutingGeographyService::class) ? RoutingGeographyService::geocodeOptions($countryCode) : ['country_code'=>$countryCode]);
        if (is_array($result) && isset($result['lat'], $result['lng']) && is_numeric($result['lat']) && is_numeric($result['lng'])) {
            return $cache[$cacheKey] = ['lat' => (float)$result['lat'], 'lng' => (float)$result['lng']];
        }
        return $cache[$cacheKey] = null;
    }

    private static function estimate_leg_details(array $fromPoint, array $toPoint): array {
        static $cache = [];
        $fromLat = is_numeric($fromPoint['lat'] ?? null) ? (float)$fromPoint['lat'] : null;
        $fromLng = is_numeric($fromPoint['lng'] ?? null) ? (float)$fromPoint['lng'] : null;
        $toLat = is_numeric($toPoint['lat'] ?? null) ? (float)$toPoint['lat'] : null;
        $toLng = is_numeric($toPoint['lng'] ?? null) ? (float)$toPoint['lng'] : null;
        if ($fromLat === null || $fromLng === null || $toLat === null || $toLng === null) return ['ok' => false, 'distance_km' => null, 'travel_min' => null, 'source' => 'missing_coordinates', 'status' => 'unavailable'];
        $key = implode('|', [round($fromLat, 6), round($fromLng, 6), round($toLat, 6), round($toLng, 6)]);
        if (isset($cache[$key])) return $cache[$key];
        if (class_exists('\RoutesPro\Services\Planning\RoutingMatrixService')) {
            $matrix = RoutingMatrixService::build([
                ['_matrix_key'=>'__from__','lat'=>$fromLat,'lng'=>$fromLng],
                ['_matrix_key'=>'__to__','lat'=>$toLat,'lng'=>$toLng],
            ], ['mode'=>'internal','cache_days'=>0]);
            $edge = RoutingMatrixService::edge($matrix, ['_matrix_key'=>'__from__','lat'=>$fromLat,'lng'=>$fromLng], ['_matrix_key'=>'__to__','lat'=>$toLat,'lng'=>$toLng]);
            if (is_numeric($edge['distance_km'] ?? null) && is_numeric($edge['travel_min'] ?? null)) {
                return $cache[$key] = ['ok'=>true,'distance_km'=>(float)$edge['distance_km'],'travel_min'=>(float)$edge['travel_min'],'source'=>'internal_road_estimator','status'=>'complete'];
            }
        }
        $distanceKm = self::haversine_km($fromLat, $fromLng, $toLat, $toLng);
        return $cache[$key] = ['ok' => true, 'distance_km' => $distanceKm, 'travel_min' => $distanceKm * 1.6, 'source' => 'haversine_estimate', 'status' => 'estimated'];
    }

    private static function build_route_schedule_details(string $date, array $points, array $startPoint = [], array $endPoint = [], bool $allowGoogle = true): array {
        $start = self::resolve_route_endpoint($startPoint, 'start', (string)($startPoint['provenance'] ?? 'manual'));
        $end = self::resolve_route_endpoint($endPoint, 'end', (string)($endPoint['provenance'] ?? 'manual'));
        $rawPoints = array_values(array_map(static function($point){ return (array)$point; }, (array)$points));
        $resolvedStops = [];
        foreach ($rawPoints as $point) $resolvedStops[] = self::resolve_route_point((array)$point);
        $firstResolvedStop = $resolvedStops[0] ?? [];
        $lastResolvedStop = $resolvedStops ? $resolvedStops[count($resolvedStops) - 1] : [];
        $endpointIntegrity = class_exists(RouteEndpointResolverService::class)
            ? RouteEndpointResolverService::integrity($start, $end, $firstResolvedStop, $lastResolvedStop, [
                'allow_start_matches_stop' => !empty($start['allow_matches_stop']) || !empty($start['_allow_matches_stop']),
                'allow_end_matches_stop' => !empty($end['allow_matches_stop']) || !empty($end['_allow_matches_stop']),
            ])
            : RouteMetricsService::endpointIntegrity($start, $end);
        $hasStart = !empty($endpointIntegrity['has_start']);
        $hasEnd = !empty($endpointIntegrity['has_end']);
        $matrixStart = $start;
        $matrixEnd = $end;
        $matrixStart['_matrix_key'] = '__start__';
        $matrixEnd['_matrix_key'] = '__end__';
        $matrixPoints = [];
        if ($hasStart) $matrixPoints[] = $matrixStart;
        foreach ($resolvedStops as $matrixStop) $matrixPoints[] = $matrixStop;
        if ($hasEnd) $matrixPoints[] = $matrixEnd;
        $matrix = RoutingMatrixService::build($matrixPoints, ['mode' => 'internal']);
        $calculator = new RouteCalculator($matrix);
        $baselineMetrics = $calculator->pathMetrics($resolvedStops, $start, $end, $matrix);
        $preserveWorkspaceOrder = false;
        foreach ($rawPoints as $rawPoint) {
            if (!empty($rawPoint['workspace_preserve_order']) || !empty($rawPoint['position_locked'])) { $preserveWorkspaceOrder = true; break; }
        }
        if (!$hasStart || !$hasEnd) $preserveWorkspaceOrder = true;
        if (!$preserveWorkspaceOrder) {
            $resolvedStops = array_values($calculator->reorderStops($resolvedStops, $start, $end, $matrix));
        }
        $sameBase = RouteCalculator::sameBase((array)$start, (array)$end);
        $routePoints = $hasStart && $hasEnd ? array_merge([$start], $resolvedStops, [$end]) : [];
        // For same-base routes Google calculates the road legs but cannot
        // reintroduce north/south zigzags into the territorial order. Manual
        // workspace ordering and position locks are always authoritative.
        $routing = $allowGoogle && class_exists('\\RoutesPro\Services\GoogleRoutes') ? \RoutesPro\Services\GoogleRoutes::calculateRoute($routePoints, ['optimize_waypoint_order' => !$preserveWorkspaceOrder && count($resolvedStops) > 1 && !$sameBase]) : ['ok' => false];
        $googleWaypointOrder = array_values(array_map('intval', (array)($routing['optimized_intermediate_waypoint_index'] ?? [])));
        if (!$preserveWorkspaceOrder && !empty($routing['ok']) && count($googleWaypointOrder) === count($resolvedStops)) {
            $googleOrderedStops = [];
            foreach ($googleWaypointOrder as $sourceIndex) if (isset($resolvedStops[$sourceIndex])) $googleOrderedStops[] = $resolvedStops[$sourceIndex];
            if (count($googleOrderedStops) === count($resolvedStops)) $resolvedStops = array_values($googleOrderedStops);
        }
        $optimizedMetrics = $calculator->pathMetrics($resolvedStops, $start, $end, $matrix);
        $territoryMetrics = RouteCalculator::territoryMetrics($resolvedStops, $start, $end);
        $expectedLegs = count($resolvedStops) + 1;
        $routingLegs = !empty($routing['ok']) && is_array($routing['legs'] ?? null) ? array_values((array)$routing['legs']) : [];
        $routingOk = !empty($routing['ok']) && count($routingLegs) >= $expectedLegs;
        $routeStartTs = strtotime(trim($date) . ' 09:00:00');
        if (!$routeStartTs) $routeStartTs = current_time('timestamp');
        $cursorTs = $routeStartTs;
        $stops = [];
        $travelMin = 0.0;
        $visitMin = 0;
        $knownDistanceKm = 0.0;
        $tollTotal = 0.0;
        $unresolvedLegs = 0;
        $sources = [];
        $accessDistance = null;
        $accessTravel = null;
        $interStopDistance = 0.0;
        $interStopTravel = 0.0;
        $prev = $start;
        foreach ($resolvedStops as $idx => $point) {
            $leg = ($idx === 0 && !$hasStart) ? [] : ($routingOk ? ($routingLegs[$idx] ?? []) : self::estimate_leg_details($prev, $point));
            $legDistance = isset($leg['distance_km']) && is_numeric($leg['distance_km']) ? (float)$leg['distance_km'] : null;
            $legTravel = isset($leg['travel_min']) && is_numeric($leg['travel_min']) ? (float)$leg['travel_min'] : null;
            $legSource = $routingOk ? 'google_routes' : (string)($leg['source'] ?? 'internal_estimator');
            $sources[$legSource] = true;
            if ($legDistance === null || $legTravel === null) $unresolvedLegs++;
            if ($idx === 0) {
                $accessDistance = $legDistance;
                $accessTravel = $legTravel;
            } else {
                if ($legDistance !== null) $interStopDistance += $legDistance;
                if ($legTravel !== null) $interStopTravel += $legTravel;
            }
            $legToll = isset($leg['toll_cost_eur']) && is_numeric($leg['toll_cost_eur']) ? (float)$leg['toll_cost_eur'] : ($legDistance !== null ? (float)\RoutesPro\Support\TollEstimator::costFromKm($legDistance, 'segment') : null);
            if ($legDistance !== null) $knownDistanceKm += $legDistance;
            if ($legTravel !== null) {
                $travelMin += $legTravel;
                $cursorTs += (int)round($legTravel * 60);
            }
            if ($legToll !== null) $tollTotal += $legToll;
            $arrival = date('Y-m-d H:i:s', $cursorTs);
            $visit = max(0, (int)($point['visit_time_min'] ?? 0));
            $visitMin += $visit;
            $cursorTs += $visit * 60;
            $departure = date('Y-m-d H:i:s', $cursorTs);
            $stops[] = [
                'item' => $point,
                'planned_arrival' => $arrival,
                'planned_departure' => $departure,
                'duration_s' => $visit * 60,
                'distance_from_prev_km' => $legDistance,
                'travel_min_from_prev' => $legTravel,
                'toll_cost_eur_from_prev' => $legToll !== null ? round($legToll, 2) : null,
                'leg_status' => ($legDistance !== null && $legTravel !== null) ? 'complete' : 'unavailable',
                'distance_source' => $legSource,
                'toll_provider' => $routingOk && !empty($leg['toll_has_price']) ? 'google_routes' : 'internal_estimator',
                'toll_is_real_api' => !empty($leg['toll_has_price']) ? 1 : 0,
                'toll_confidence' => (string)($leg['toll_confidence'] ?? (!empty($leg['toll_has_price']) ? 'real' : 'low')),
                'toll_min_eur' => isset($leg['toll_min_eur']) && is_numeric($leg['toll_min_eur']) ? round((float)$leg['toll_min_eur'], 2) : ($legToll !== null ? round($legToll, 2) : null),
                'toll_max_eur' => isset($leg['toll_max_eur']) && is_numeric($leg['toll_max_eur']) ? round((float)$leg['toll_max_eur'], 2) : ($legToll !== null ? round($legToll, 2) : null),
                'routing_provider' => $routingOk ? 'google_routes' : 'internal',
                'arrival_point' => ['lat' => is_numeric($prev['lat'] ?? null) ? (float)$prev['lat'] : null, 'lng' => is_numeric($prev['lng'] ?? null) ? (float)$prev['lng'] : null, 'address' => (string)($prev['address'] ?? '')],
            ];
            $prev = $point;
        }
        $lastLeg = !$hasEnd ? [] : ($routingOk ? ($routingLegs[count($resolvedStops)] ?? []) : self::estimate_leg_details($prev, $end));
        $lastDistance = isset($lastLeg['distance_km']) && is_numeric($lastLeg['distance_km']) ? (float)$lastLeg['distance_km'] : null;
        $lastTravel = isset($lastLeg['travel_min']) && is_numeric($lastLeg['travel_min']) ? (float)$lastLeg['travel_min'] : null;
        $lastSource = $routingOk ? 'google_routes' : (string)($lastLeg['source'] ?? 'internal_estimator');
        $sources[$lastSource] = true;
        if ($lastDistance === null || $lastTravel === null) $unresolvedLegs++;
        $lastToll = isset($lastLeg['toll_cost_eur']) && is_numeric($lastLeg['toll_cost_eur']) ? (float)$lastLeg['toll_cost_eur'] : ($lastDistance !== null ? (float)\RoutesPro\Support\TollEstimator::costFromKm($lastDistance, 'segment') : null);
        if ($lastDistance !== null) $knownDistanceKm += $lastDistance;
        if ($lastTravel !== null) {
            $travelMin += $lastTravel;
            $cursorTs += (int)round($lastTravel * 60);
        }
        if ($lastToll !== null) $tollTotal += $lastToll;
        if ($routingOk) {
            $knownDistanceKm = (float)($routing['distance_km'] ?? $knownDistanceKm);
            $travelMin = (float)($routing['travel_min'] ?? $travelMin);
            $tollTotal = (float)($routing['toll_cost_eur'] ?? $tollTotal);
            $unresolvedLegs = 0;
        }
        if (!$routingOk && $tollTotal <= 0 && $knownDistanceKm > 0) $tollTotal = (float)\RoutesPro\Support\TollEstimator::costFromKm($knownDistanceKm, 'route');
        $distanceStatus = ($unresolvedLegs > 0 || empty($endpointIntegrity['endpoints_routable'])) ? 'incomplete' : 'complete';
        $distanceSource = $routingOk ? 'google_routes' : (count($sources) === 1 ? (string)array_key_first($sources) : 'mixed_internal');
        $endpointWarnings = array_values(array_unique((array)($endpointIntegrity['endpoint_warning_codes'] ?? [])));
        // Historical warning markers remain for Release Gate continuity.
        if ($hasStart && $accessDistance !== null && $accessDistance < 0.05 && count($resolvedStops) > 0 && !in_array('start_matches_first_stop', $endpointWarnings, true)) $endpointWarnings[] = 'start_matches_first_stop';
        if ($hasEnd && $lastDistance !== null && $lastDistance < 0.05 && count($resolvedStops) > 0 && !in_array('end_matches_last_stop', $endpointWarnings, true)) $endpointWarnings[] = 'end_matches_last_stop';
        return [
            'resolved_start_point' => $start,
            'resolved_end_point' => $end,
            'ordered_points' => $resolvedStops,
            'route_start' => date('Y-m-d H:i:s', $routeStartTs),
            'route_end' => date('Y-m-d H:i:s', $cursorTs),
            'travel_min' => $distanceStatus === 'complete' ? $travelMin : null,
            'known_travel_min' => $travelMin,
            'access_travel_min' => $accessTravel,
            'inter_stop_travel_min' => $interStopTravel,
            'return_travel_min' => $lastTravel,
            'visit_min' => $visitMin,
            'work_min' => (int)round($travelMin + $visitMin),
            'total_min' => (int)round($travelMin + $visitMin),
            'distance_km' => $distanceStatus === 'complete' ? $knownDistanceKm : null,
            'known_distance_km' => $knownDistanceKm,
            'access_distance_km' => $accessDistance,
            'inter_stop_distance_km' => $interStopDistance,
            'return_distance_km' => $lastDistance,
            'stops' => $stops,
            'end_leg_distance_km' => $lastDistance,
            'end_leg_travel_min' => $lastTravel,
            'end_leg_toll_cost_eur' => $lastToll !== null ? round($lastToll, 2) : null,
            'toll_cost_eur' => $distanceStatus === 'complete' ? round($tollTotal, 2) : null,
            'toll_estimated_eur' => $distanceStatus === 'complete' ? round($tollTotal, 2) : null,
            'known_toll_cost_eur' => round($tollTotal, 2),
            'toll_model' => $routingOk ? (string)($routing['toll_model'] ?? 'Google Routes API') : (string)(\RoutesPro\Support\TollEstimator::estimateFromKm($knownDistanceKm, 'route')['model'] ?? ''),
            'toll_provider' => $routingOk && !empty($routing['toll_is_real_api']) ? 'google_routes' : 'internal_estimator',
            'toll_is_real_api' => $routingOk && !empty($routing['toll_is_real_api']) ? 1 : 0,
            'toll_confidence' => $routingOk ? (string)($routing['toll_confidence'] ?? (!empty($routing['toll_is_real_api']) ? 'real' : 'low')) : 'low',
            'toll_min_eur' => $routingOk && is_numeric($routing['toll_min_eur'] ?? null) ? round((float)$routing['toll_min_eur'],2) : round($tollTotal,2),
            'toll_max_eur' => $routingOk && is_numeric($routing['toll_max_eur'] ?? null) ? round((float)$routing['toll_max_eur'],2) : round($tollTotal,2),
            'toll_tariff_version' => $routingOk ? (string)($routing['toll_tariff_version'] ?? '') : \RoutesPro\Support\TollEstimator::TARIFF_VERSION,
            'toll_vehicle_class' => $routingOk ? max(1,min(4,(int)($routing['toll_vehicle_class'] ?? 1))) : max(1,min(4,(int)\RoutesPro\Support\Config::get('toll_vehicle_class',1))),
            'toll_cost_intelligence_contract' => $routingOk ? (string)($routing['toll_cost_intelligence_contract'] ?? '') : \RoutesPro\Support\TollEstimator::INTELLIGENCE_CONTRACT,
            'routing_provider' => $routingOk ? 'google_routes' : 'internal',
            'routing_signature' => $routingOk ? (string)($routing['route_signature'] ?? '') : '',
            'routing_cost_contract' => $routingOk ? (string)($routing['routing_cost_contract'] ?? '') : '',
            'routing_refresh_version' => $routingOk ? 'google_routes_static_snapshot_v4' : '',
            'routing_calculated_at' => $routingOk ? (string)($routing['calculated_at'] ?? current_time('mysql')) : current_time('mysql'),
            'matrix_provider' => (string)($matrix['provider'] ?? 'internal_road_estimator'),
            'matrix_source_label' => (string)($matrix['source_label'] ?? 'Estimador rodoviário interno'),
            'matrix_version' => (string)($matrix['matrix_version'] ?? RoutingMatrixService::VERSION),
            'matrix_coverage_pct' => is_numeric($matrix['coverage_pct'] ?? null) ? round((float)$matrix['coverage_pct'], 1) : 0.0,
            'optimization_method' => $routingOk && !empty($routing['waypoint_order_optimized']) ? 'base_radial_corridor_google_waypoint' : 'base_radial_corridor_regret_2opt',
            'waypoint_order_optimized' => $routingOk && !empty($routing['waypoint_order_optimized']) ? 1 : 0,
            'optimization_saved_distance_km' => round(max(0.0, (float)($baselineMetrics['known_distance_km'] ?? 0) - (float)($optimizedMetrics['known_distance_km'] ?? 0)), 2),
            'optimization_saved_travel_min' => round(max(0.0, (float)($baselineMetrics['known_travel_min'] ?? 0) - (float)($optimizedMetrics['known_travel_min'] ?? 0)), 1),
            'territorial_coherence_mode' => 'strong',
            'territory_same_base' => !empty($territoryMetrics['same_base']) ? 1 : 0,
            'territory_dominant_direction' => (string)($territoryMetrics['dominant_direction'] ?? 'base'),
            'territory_dominant_direction_label' => (string)($territoryMetrics['dominant_direction_label'] ?? 'Zona da base'),
            'territory_dominant_share_pct' => (float)($territoryMetrics['dominant_direction_share_pct'] ?? 100),
            'territory_direction_count' => (int)($territoryMetrics['direction_count'] ?? 0),
            'territory_direction_switches' => (int)($territoryMetrics['direction_switches'] ?? 0),
            'territory_direction_reentries' => (int)($territoryMetrics['direction_reentries'] ?? 0),
            'territory_opposite_mix' => !empty($territoryMetrics['opposite_direction_mix']) ? 1 : 0,
            'territory_opposite_direction_switches' => (int)($territoryMetrics['opposite_direction_switches'] ?? 0),
            'territory_locality_count' => (int)($territoryMetrics['locality_count'] ?? 0),
            'territory_locality_reentries' => (int)($territoryMetrics['locality_reentries'] ?? 0),
            'territory_bearing_span_deg' => (float)($territoryMetrics['bearing_span_deg'] ?? 0),
            'territory_far_stop_count' => (int)($territoryMetrics['far_stop_count'] ?? 0),
            'territory_base_crossing_risk' => !empty($territoryMetrics['base_crossing_risk']) ? 1 : 0,
            'territory_route_cone_label' => (string)($territoryMetrics['route_cone_label'] ?? 'Corredor focado'),
            'territory_coherence_score' => (float)($territoryMetrics['coherence_score'] ?? 100),
            'territory_coherence_status' => (string)($territoryMetrics['coherence_status'] ?? 'coherent'),
            'territory_coherence_label' => (string)($territoryMetrics['coherence_label'] ?? 'Coerente'),
            'distance_source' => $distanceSource,
            'distance_status' => $distanceStatus,
            'endpoint_status' => (string)($endpointIntegrity['endpoint_status'] ?? ($hasStart && $hasEnd ? 'complete' : ($hasStart ? 'missing_end' : ($hasEnd ? 'missing_start' : 'missing_both')))),
            'endpoints_complete' => !empty($endpointIntegrity['endpoints_complete']) ? 1 : 0,
            'endpoints_routable' => !empty($endpointIntegrity['endpoints_routable']) ? 1 : 0,
            'endpoints_trusted' => !empty($endpointIntegrity['endpoints_complete']) ? 1 : 0,
            'missing_endpoint_count' => (int)($endpointIntegrity['missing_endpoint_count'] ?? (($hasStart ? 0 : 1) + ($hasEnd ? 0 : 1))),
            'suspicious_endpoint_count' => (int)($endpointIntegrity['suspicious_endpoint_count'] ?? 0),
            'start_matches_first_stop' => !empty($endpointIntegrity['start_matches_first_stop']) ? 1 : 0,
            'end_matches_last_stop' => !empty($endpointIntegrity['end_matches_last_stop']) ? 1 : 0,
            'start_endpoint_provenance' => (string)($endpointIntegrity['start_provenance'] ?? ($start['provenance'] ?? 'legacy')),
            'end_endpoint_provenance' => (string)($endpointIntegrity['end_provenance'] ?? ($end['provenance'] ?? 'legacy')),
            'endpoint_reconciliation_contract' => class_exists(RouteEndpointResolverService::class) ? RouteEndpointResolverService::CONTRACT : '',
            'endpoint_warning_codes' => $endpointWarnings,
            'endpoint_geocode_verified' => (!empty($start['_endpoint_geocode_verified']) && !empty($end['_endpoint_geocode_verified'])) ? 1 : 0,
            'endpoint_coordinates_refreshed' => (!empty($start['_endpoint_coordinates_refreshed']) || !empty($end['_endpoint_coordinates_refreshed'])) ? 1 : 0,
            'routing_incomplete' => $distanceStatus === 'complete' ? 0 : 1,
            'unresolved_leg_count' => $unresolvedLegs,
            'segment_count' => $expectedLegs,
        ];
    }



    public static function ajax_route_metrics_preview(): void {
        if (!current_user_can('routespro_manage')) {
            wp_send_json_error(['message' => 'Sem permissões.'], 403);
        }
        check_ajax_referer('routespro_route_metrics_preview', 'nonce');

        $decode = static function($value, array $fallback = []): array {
            if (is_array($value)) return $value;
            $decoded = json_decode(wp_unslash((string)$value), true);
            return is_array($decoded) ? $decoded : $fallback;
        };
        $date = sanitize_text_field((string)($_POST['date'] ?? current_time('Y-m-d')));
        $start = $decode($_POST['start'] ?? '[]');
        $end = $decode($_POST['end'] ?? '[]');
        $days = $decode($_POST['days'] ?? '[]');
        $points = $decode($_POST['points'] ?? '[]');
        $projectId = absint($_POST['project_id'] ?? 0);
        $ownerUserId = absint($_POST['owner_user_id'] ?? 0);

        if ($days) {
            $days = array_slice(array_values($days), 0, 31);
            $dayResults = [];
            $totalDistance = 0.0;
            $totalKnownDistance = 0.0;
            $totalToll = 0.0;
            $totalTravel = 0.0;
            $totalVisit = 0;
            $totalEnergyCost = 0.0;
            $totalOperationalCost = 0.0;
            $totalMobilityCost = 0.0;
            $totalLitres = 0.0;
            $totalKwh = 0.0;
            $allComplete = true;
            $providers = [];
            foreach ($days as $index => $day) {
                if (!is_array($day)) continue;
                $dayPoints = array_slice(array_values((array)($day['points'] ?? $day['items'] ?? [])), 0, 250);
                $dayDate = sanitize_text_field((string)($day['date'] ?? $date));
                if (!$dayPoints) {
                    $emptyPayload = self::preview_metrics_payload([], []);
                    $emptyProfile = MobilityCostService::resolveProfile($projectId, $ownerUserId, [], $dayDate);
                    $emptyPayload['mobility_cost'] = MobilityCostService::estimateForMetrics($emptyPayload, $emptyProfile);
                    $dayResults[] = [
                        'index' => (int)$index,
                        'date' => $dayDate,
                        'metrics' => $emptyPayload,
                        'legs' => [],
                    ];
                    continue;
                }
                $cacheKey = 'ff_route_preview_' . md5(wp_json_encode([$dayDate, $dayPoints, $start, $end]));
                $cached = get_transient($cacheKey);
                if (is_array($cached)) {
                    $schedule = (array)($cached['schedule'] ?? []);
                    $metrics = (array)($cached['metrics'] ?? []);
                } else {
                    $schedule = self::build_route_schedule_details($dayDate, $dayPoints, $start, $end, false);
                    $metrics = class_exists('\\RoutesPro\\Services\\RouteMetricsService')
                        ? \RoutesPro\Services\RouteMetricsService::canonicalFromSchedule($schedule)
                        : $schedule;
                    set_transient($cacheKey, ['schedule' => $schedule, 'metrics' => $metrics], 5 * MINUTE_IN_SECONDS);
                }
                $payload = self::preview_metrics_payload($schedule, $metrics);
                $dayProfile = MobilityCostService::resolveProfile($projectId, $ownerUserId, [], $dayDate);
                $dayToll = is_numeric($payload['toll_cost_eur'] ?? null) ? (float)$payload['toll_cost_eur'] : 0.0;
                $dayMobility = MobilityCostService::estimateForMetrics($payload, $dayProfile);
                $dayMobility['profile_source'] = sanitize_key((string)($dayProfile['_source'] ?? ''));
                $payload['mobility_cost'] = $dayMobility;
                $totalEnergyCost += (float)($dayMobility['energy_cost_eur'] ?? 0);
                $totalOperationalCost += (float)($dayMobility['operational_cost_eur'] ?? 0);
                $totalMobilityCost += (float)($dayMobility['total_mobility_cost_eur'] ?? $dayToll);
                $totalLitres += (float)($dayMobility['estimated_litres'] ?? 0);
                $totalKwh += (float)($dayMobility['estimated_kwh'] ?? 0);
                $totalDistance += is_numeric($payload['distance_km'] ?? null) ? (float)$payload['distance_km'] : 0.0;
                $totalKnownDistance += is_numeric($payload['known_distance_km'] ?? null) ? (float)$payload['known_distance_km'] : 0.0;
                $totalToll += is_numeric($payload['toll_cost_eur'] ?? null) ? (float)$payload['toll_cost_eur'] : 0.0;
                $totalTravel += is_numeric($payload['travel_min'] ?? null) ? (float)$payload['travel_min'] : 0.0;
                $totalVisit += (int)($payload['visit_min'] ?? 0);
                if (($payload['distance_status'] ?? '') !== 'complete') $allComplete = false;
                if (!empty($payload['source_label'])) $providers[(string)$payload['source_label']] = true;
                $dayResults[] = [
                    'index' => (int)$index,
                    'date' => $dayDate,
                    'metrics' => $payload,
                    'legs' => self::preview_leg_payload($schedule),
                ];
            }
            $incompleteDays = count(array_filter($dayResults, static function(array $day): bool {
                return (string)($day['metrics']['distance_status'] ?? '') !== 'complete';
            }));
            wp_send_json_success([
                'mode' => 'plan',
                'days' => $dayResults,
                'metrics' => [
                    'distance_km' => $allComplete ? round($totalDistance, 2) : null,
                    'known_distance_km' => round($totalKnownDistance, 2),
                    'toll_cost_eur' => $allComplete ? round($totalToll, 2) : null,
                    'travel_min' => $allComplete ? round($totalTravel, 1) : null,
                    'visit_min' => $totalVisit,
                    'total_min' => $allComplete ? (int)round($totalTravel + $totalVisit) : null,
                    'distance_status' => $allComplete ? 'complete' : 'incomplete',
                    'routing_incomplete' => $allComplete ? 0 : 1,
                    'incomplete_day_count' => $incompleteDays,
                    'source_label' => implode(' + ', array_keys($providers)) ?: 'Estimador FieldFlow',
                    'mobility_cost' => [
                        'contract' => MobilityCostService::CONTRACT,
                        'enabled' => ($totalEnergyCost > 0 || $totalOperationalCost > 0) ? 1 : 0,
                        'costs_available' => $allComplete ? 1 : 0,
                        'calculation_status' => $allComplete ? 'complete' : 'distance_incomplete',
                        'estimated_litres' => $allComplete ? round($totalLitres, 3) : 0.0,
                        'estimated_kwh' => $allComplete ? round($totalKwh, 3) : 0.0,
                        'energy_cost_eur' => $allComplete ? round($totalEnergyCost, 2) : 0.0,
                        'operational_cost_eur' => $allComplete ? round($totalOperationalCost, 2) : 0.0,
                        'toll_cost_eur' => $allComplete ? round($totalToll, 2) : 0.0,
                        'total_mobility_cost_eur' => $allComplete ? round($totalMobilityCost, 2) : 0.0,
                    ],
                ],
            ]);
        }

        $points = array_slice(array_values($points), 0, 250);
        if (!$points) {
            wp_send_json_success([
                'mode' => 'route',
                'metrics' => self::preview_metrics_payload([], []),
                'legs' => [],
            ]);
        }
        $cacheKey = 'ff_route_preview_' . md5(wp_json_encode([$date, $points, $start, $end]));
        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            $schedule = (array)($cached['schedule'] ?? []);
            $metrics = (array)($cached['metrics'] ?? []);
        } else {
            $schedule = self::build_route_schedule_details($date, $points, $start, $end, false);
            $metrics = class_exists('\\RoutesPro\\Services\\RouteMetricsService')
                ? \RoutesPro\Services\RouteMetricsService::canonicalFromSchedule($schedule)
                : $schedule;
            set_transient($cacheKey, ['schedule' => $schedule, 'metrics' => $metrics], 5 * MINUTE_IN_SECONDS);
        }
        $routePayload = self::preview_metrics_payload($schedule, $metrics);
        $routeProfile = MobilityCostService::resolveProfile($projectId, $ownerUserId, [], $date);
        $routeMobility = MobilityCostService::estimateForMetrics($routePayload, $routeProfile);
        $routeMobility['profile_source'] = sanitize_key((string)($routeProfile['_source'] ?? ''));
        $routePayload['mobility_cost'] = $routeMobility;
        wp_send_json_success([
            'mode' => 'route',
            'metrics' => $routePayload,
            'legs' => self::preview_leg_payload($schedule),
            'ordered_points' => array_values((array)($schedule['ordered_points'] ?? [])),
        ]);
    }

    private static function preview_metrics_payload(array $schedule, array $metrics): array {
        $distance = is_numeric($metrics['distance_km'] ?? null) ? (float)$metrics['distance_km'] : null;
        $known = is_numeric($metrics['known_distance_km'] ?? null) ? (float)$metrics['known_distance_km'] : ($distance ?? 0.0);
        $toll = is_numeric($metrics['toll_cost_eur'] ?? null) ? (float)$metrics['toll_cost_eur'] : null;
        $source = trim((string)($metrics['matrix_source_label'] ?? $metrics['matrix_provider'] ?? $metrics['distance_source'] ?? ''));
        if ($source === '') $source = 'Estimador FieldFlow';
        return [
            'distance_km' => $distance !== null ? round($distance, 2) : null,
            'known_distance_km' => round($known, 2),
            'access_distance_km' => is_numeric($metrics['access_distance_km'] ?? null) ? round((float)$metrics['access_distance_km'], 2) : null,
            'inter_stop_distance_km' => is_numeric($metrics['inter_stop_distance_km'] ?? null) ? round((float)$metrics['inter_stop_distance_km'], 2) : null,
            'return_distance_km' => is_numeric($metrics['return_distance_km'] ?? null) ? round((float)$metrics['return_distance_km'], 2) : null,
            'travel_min' => is_numeric($metrics['travel_min'] ?? null) ? round((float)$metrics['travel_min'], 1) : (is_numeric($metrics['known_travel_min'] ?? null) ? round((float)$metrics['known_travel_min'], 1) : null),
            'visit_min' => max(0, (int)($metrics['visit_min'] ?? $schedule['visit_min'] ?? 0)),
            'total_min' => max(0, (int)($metrics['total_min'] ?? $schedule['total_min'] ?? 0)),
            'toll_cost_eur' => $toll !== null ? round((float)$toll, 2) : null,
            'known_toll_cost_eur' => is_numeric($metrics['known_toll_cost_eur'] ?? null) ? round((float)$metrics['known_toll_cost_eur'], 2) : null,
            'toll_model' => (string)($metrics['toll_model'] ?? ''),
            'toll_provider' => (string)($metrics['toll_provider'] ?? ''),
            'distance_status' => (string)($metrics['distance_status'] ?? ($distance !== null ? 'complete' : ($known > 0 ? 'incomplete' : 'unavailable'))),
            'endpoint_status' => (string)($metrics['endpoint_status'] ?? 'incomplete'),
            'endpoints_complete' => !empty($metrics['endpoints_complete']) ? 1 : 0,
            'endpoints_routable' => !empty($metrics['endpoints_routable']) ? 1 : 0,
            'endpoints_trusted' => !empty($metrics['endpoints_complete']) ? 1 : 0,
            'missing_endpoint_count' => max(0, (int)($metrics['missing_endpoint_count'] ?? 0)),
            'endpoint_warning_codes' => array_values(array_filter(array_map('sanitize_key', (array)($schedule['endpoint_warning_codes'] ?? $metrics['endpoint_warning_codes'] ?? [])))),
            'endpoint_geocode_verified' => !empty($schedule['endpoint_geocode_verified']) ? 1 : 0,
            'endpoint_coordinates_refreshed' => !empty($schedule['endpoint_coordinates_refreshed']) ? 1 : 0,
            'start_point_address' => sanitize_text_field((string)($schedule['resolved_start_point']['address'] ?? '')),
            'end_point_address' => sanitize_text_field((string)($schedule['resolved_end_point']['address'] ?? '')),
            'unresolved_leg_count' => max(0, (int)($metrics['unresolved_leg_count'] ?? 0)),
            'source_label' => str_replace('_', ' ', $source),
            'optimization_saved_distance_km' => is_numeric($metrics['optimization_saved_distance_km'] ?? null) ? round((float)$metrics['optimization_saved_distance_km'], 2) : 0.0,
            'territory_dominant_direction_label' => (string)($metrics['territory_dominant_direction_label'] ?? 'Por calcular'),
            'territory_coherence_label' => (string)($metrics['territory_coherence_label'] ?? 'Por calcular'),
        ];
    }

    private static function preview_leg_payload(array $schedule): array {
        $legs = [];
        foreach ((array)($schedule['stops'] ?? []) as $index => $stop) {
            $item = is_array($stop['item'] ?? null) ? (array)$stop['item'] : [];
            $legs[] = [
                'index' => (int)$index,
                'name' => sanitize_text_field((string)($item['name'] ?? ('PDV ' . ($index + 1)))),
                'address' => sanitize_text_field((string)($item['address'] ?? '')),
                'distance_km' => is_numeric($stop['distance_from_prev_km'] ?? null) ? round((float)$stop['distance_from_prev_km'], 2) : null,
                'travel_min' => is_numeric($stop['travel_min_from_prev'] ?? null) ? round((float)$stop['travel_min_from_prev'], 1) : null,
                'toll_cost_eur' => is_numeric($stop['toll_cost_eur_from_prev'] ?? null) ? round((float)$stop['toll_cost_eur_from_prev'], 2) : null,
                'status' => sanitize_key((string)($stop['leg_status'] ?? '')),
            ];
        }
        return $legs;
    }


    public static function handle_export() {
        $userId = get_current_user_id();
        $isManager = current_user_can('routespro_manage');
        if (!$userId) wp_die('Sem permissões.', 'FieldFlow', ['response' => 403]);

        $requestNonce = sanitize_text_field(wp_unslash((string)($_REQUEST['_wpnonce'] ?? '')));
        if ($requestNonce === '') wp_die('Pedido inválido.', 'FieldFlow', ['response' => 403]);

        // A mesma action é usada no BO e no Portal Cliente. Um administrador pode
        // abrir o Portal Cliente, por isso o tipo de pedido é determinado pelo nonce
        // recebido e não apenas pela capability routespro_manage.
        $adminNonceValid = $isManager && wp_verify_nonce($requestNonce, 'routespro_export_routes');
        $clientNonceValid = wp_verify_nonce($requestNonce, 'routespro_export_client_routes');
        if (!$adminNonceValid && !$clientNonceValid) wp_die('Pedido inválido.', 'FieldFlow', ['response' => 403]);

        try {
            $context = self::prepare_route_export_context((array)$_GET, $userId, $clientNonceValid, true);
        } catch (\Throwable $e) {
            wp_die(esc_html($e->getMessage() ?: 'Não foi possível preparar a exportação.'), 'FieldFlow', ['response' => 403]);
        }

        $filters = $context['filters'];
        $format = $context['format'];
        $dataset = $context['dataset'];
        $filenameBase = $context['filename_base'];

        if ($format === 'pdf') self::output_routes_pdf($filenameBase . '.pdf', $filters, $dataset);

        $table = self::routes_dataset_to_table($dataset, true);
        if ($format === 'xls') self::output_routes_excel($filenameBase . '.xls', $table['headers'], $table['rows']);
        self::output_routes_csv($filenameBase . '.csv', $table['headers'], $table['rows']);
    }

    /**
     * Creates a physical export file while the WordPress session is still present.
     * Desktop/mobile can then download it through a short-lived opaque ticket without
     * asking the native downloader to carry WordPress authentication cookies.
     */
    public static function create_client_portal_export_ticket_file(array $request): array {
        $userId = get_current_user_id();
        if (!$userId) throw new \RuntimeException('Sem sessão ativa.');

        $nonce = sanitize_text_field(wp_unslash((string)($request['_wpnonce'] ?? '')));
        if ($nonce === '' || !wp_verify_nonce($nonce, 'routespro_export_client_routes')) {
            throw new \RuntimeException('Pedido de exportação inválido.');
        }
        if (!current_user_can('routespro_manage') && !Permissions::can_access_front($userId)) {
            throw new \RuntimeException('Sem permissões para exportar rotas.');
        }

        $context = self::prepare_route_export_context($request, $userId, true, true);
        $filters = $context['filters'];
        $format = $context['format'];
        $dataset = $context['dataset'];
        $filenameBase = $context['filename_base'];
        $table = self::routes_dataset_to_table($dataset, true);

        $extension = $format === 'pdf' ? 'pdf' : ($format === 'xls' ? 'xls' : 'csv');
        $filename = sanitize_file_name($filenameBase . '.' . $extension);
        $path = wp_tempnam($filename);
        if (!$path) throw new \RuntimeException('Não foi possível criar o ficheiro temporário de exportação.');

        try {
            if ($format === 'pdf') {
                $content = self::build_routes_pdf_content($filters, $dataset);
                if (@file_put_contents($path, $content, LOCK_EX) === false) throw new \RuntimeException('Não foi possível gravar o PDF de rotas.');
                $mime = 'application/pdf';
            } elseif ($format === 'xls') {
                $content = self::build_routes_excel_content($table['headers'], $table['rows']);
                if (@file_put_contents($path, $content, LOCK_EX) === false) throw new \RuntimeException('Não foi possível gravar o Excel de rotas.');
                $mime = 'application/vnd.ms-excel';
            } else {
                self::write_routes_csv_file($path, $table['headers'], $table['rows']);
                $mime = 'text/csv';
            }

            clearstatcache(true, $path);
            if (!is_file($path) || (int)@filesize($path) < 1) throw new \RuntimeException('O ficheiro de exportação ficou vazio.');
            return ['path' => $path, 'filename' => $filename, 'mime' => $mime, 'format' => $format];
        } catch (\Throwable $e) {
            if (is_file($path)) @unlink($path);
            throw $e;
        }
    }

    private static function prepare_route_export_context(array $request, int $userId, bool $clientPortalRequest, bool $consumeQuota): array {
        global $wpdb;
        $isManager = current_user_can('routespro_manage');
        if (!$isManager && !$clientPortalRequest) throw new \RuntimeException('Sem permissões.');
        if ($clientPortalRequest && !$isManager && !Permissions::can_access_front($userId)) throw new \RuntimeException('Sem permissões.');

        $filters = [
            'route_id' => absint($request['route_id'] ?? 0),
            'client_id' => absint($request['client_id'] ?? 0),
            'project_id' => absint($request['project_id'] ?? 0),
            'owner_user_id' => absint($request['owner_user_id'] ?? 0),
            'date' => sanitize_text_field(wp_unslash((string)($request['date'] ?? ''))),
            'date_from' => sanitize_text_field(wp_unslash((string)($request['date_from'] ?? ''))),
            'date_to' => sanitize_text_field(wp_unslash((string)($request['date_to'] ?? ''))),
            'scope_user_id' => 0,
            'limit' => 5000,
        ];
        $format = sanitize_key(wp_unslash((string)($request['format'] ?? 'csv')));
        if ($format === 'excel') $format = 'xls';
        if (!in_array($format, ['csv', 'xls', 'pdf'], true)) $format = 'csv';

        if (!$isManager) {
            if (!empty($filters['route_id']) && !Permissions::can_access_route((int)$filters['route_id'], $userId)) {
                throw new \RuntimeException('Sem acesso à rota selecionada.');
            }
            $scopeCheck = Permissions::assert_scope_or_error((int)$filters['client_id'], (int)$filters['project_id'], $userId);
            if (is_wp_error($scopeCheck)) throw new \RuntimeException($scopeCheck->get_error_message());
            $filters['scope_user_id'] = $userId;
        }

        if (class_exists('\\RoutesPro\\SaaS\\EntitlementService')) {
            $saasClientId = (int)$filters['client_id'];
            if (!$saasClientId && !empty($filters['project_id'])) {
                $saasClientId = (int)$wpdb->get_var($wpdb->prepare("SELECT client_id FROM {$wpdb->prefix}routespro_projects WHERE id=%d", (int)$filters['project_id']));
            }
            if (!$saasClientId && !empty($filters['route_id'])) {
                $saasClientId = (int)$wpdb->get_var($wpdb->prepare("SELECT client_id FROM {$wpdb->prefix}routespro_routes WHERE id=%d", (int)$filters['route_id']));
            }
            $routesAllowed = $saasClientId > 0
                ? \RoutesPro\SaaS\EntitlementService::clientHas($saasClientId, 'routes.view')
                : \RoutesPro\SaaS\EntitlementService::userHasAny('routes.view', $userId);
            if (!$routesAllowed) throw new \RuntimeException('A consulta de rotas não está incluída no plano deste cliente.');

            $exportCapability = \RoutesPro\SaaS\EntitlementService::exportCapability($format);
            $exportAllowed = $saasClientId > 0
                ? \RoutesPro\SaaS\EntitlementService::clientHas($saasClientId, $exportCapability)
                : \RoutesPro\SaaS\EntitlementService::userHasAny($exportCapability, $userId);
            if (!$exportAllowed) throw new \RuntimeException('Este formato de exportação não está incluído no plano deste cliente.');
            if ($consumeQuota && $saasClientId > 0 && class_exists('\\RoutesPro\\SaaS\\UsageService')) {
                $quota = \RoutesPro\SaaS\UsageService::canConsume($saasClientId, 'exports_monthly', 1);
                if (empty($quota['allowed'])) throw new \RuntimeException('O limite mensal de exportações foi atingido.');
                \RoutesPro\SaaS\UsageService::increment($saasClientId, 'exports_monthly', 1);
            }
        }

        $dataset = self::get_routes_export_dataset($filters);
        $filenameBase = $filters['route_id'] ? ('fieldflow-rota-' . (int)$filters['route_id']) : 'fieldflow-rotas';
        if (!$filters['route_id'] && !empty($filters['owner_user_id'])) $filenameBase .= '-operativo-' . (int)$filters['owner_user_id'];
        $filenameBase .= '-' . gmdate('Ymd-His');

        return ['filters' => $filters, 'format' => $format, 'dataset' => $dataset, 'filename_base' => $filenameBase];
    }

    private static function get_routes_export_dataset(array $filters): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $filters = wp_parse_args($filters, [
            'route_id' => 0,
            'client_id' => 0,
            'project_id' => 0,
            'owner_user_id' => 0,
            'date' => '',
            'date_from' => '',
            'date_to' => '',
            'scope_user_id' => 0,
            'limit' => 5000,
        ]);
        $where = ['1=1'];
        $args = [];
        if (!empty($filters['route_id'])) { $where[] = 'r.id=%d'; $args[] = (int)$filters['route_id']; }
        if (!empty($filters['client_id'])) { $where[] = 'r.client_id=%d'; $args[] = (int)$filters['client_id']; }
        if (!empty($filters['project_id'])) { $where[] = 'r.project_id=%d'; $args[] = (int)$filters['project_id']; }
        if (!empty($filters['owner_user_id'])) { $where[] = 'r.owner_user_id=%d'; $args[] = (int)$filters['owner_user_id']; }
        if (!empty($filters['scope_user_id'])) {
            [$scopeSql, $scopeArgs] = Permissions::scope_sql('r', null, null, (int)$filters['scope_user_id']);
            $where[] = $scopeSql;
            $args = array_merge($args, $scopeArgs);
        }
        if (!empty($filters['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['date'])) {
            $where[] = 'r.date=%s';
            $args[] = (string)$filters['date'];
        } else {
            if (!empty($filters['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['date_from'])) { $where[] = 'r.date >= %s'; $args[] = (string)$filters['date_from']; }
            if (!empty($filters['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['date_to'])) { $where[] = 'r.date <= %s'; $args[] = (string)$filters['date_to']; }
        }
        $limit = max(1, min(10000, (int)($filters['limit'] ?? 5000)));
        $sql = "SELECT r.*, c.name AS client_name, p.name AS project_name, owner.display_name AS owner_name, owner.user_email AS owner_email, (SELECT COUNT(*) FROM {$px}route_stops rs_count WHERE rs_count.route_id=r.id) AS stops_count
            FROM {$px}routes r
            LEFT JOIN {$px}clients c ON c.id=r.client_id
            LEFT JOIN {$px}projects p ON p.id=r.project_id
            LEFT JOIN {$wpdb->users} owner ON owner.ID=r.owner_user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.date DESC, r.id DESC
            LIMIT {$limit}";
        $routes = $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
        $routeIds = array_values(array_filter(array_map(static function($route){ return (int)($route['id'] ?? 0); }, $routes)));
        $stopsByRoute = self::get_routes_export_stops_by_route($routeIds);
        foreach ($routes as &$route) {
            $routeId = (int)($route['id'] ?? 0);
            $routeStops = $stopsByRoute[$routeId] ?? [];
            $route['meta_decoded'] = self::decode_json_array($route['meta_json'] ?? '');
            $route['metrics'] = self::calculate_route_export_metrics_from_route($route, $routeStops);
            $routeMobilityMeta = $route['meta_decoded'];
            $routeMobilityMeta['route_metrics'] = array_merge(is_array($routeMobilityMeta['route_metrics'] ?? null) ? (array)$routeMobilityMeta['route_metrics'] : [], (array)$route['metrics']);
            $routeForMobility = $route;
            $routeForMobility['meta_json'] = wp_json_encode($routeMobilityMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $route['mobility_cost'] = MobilityCostService::calculateForRouteArray($routeForMobility);
            // Recarrega paragens após eventual refresh Google, para exportar segmentos com portagens reais.
            $route['stops'] = class_exists('\RoutesPro\Services\GoogleRoutes') && \RoutesPro\Services\GoogleRoutes::enabled() ? self::get_route_export_stops($routeId) : $routeStops;
        }
        unset($route);
        return ['routes' => $routes, 'filters' => $filters];
    }

    private static function get_route_export_stops(int $route_id): array {
        if (!$route_id) return [];
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT rs.*,
                COALESCE(NULLIF(snap.name,''), l.name) AS location_name,
                COALESCE(NULLIF(snap.address,''), l.address) AS address,
                COALESCE(NULLIF(snap.district,''), l.district) AS district,
                COALESCE(NULLIF(snap.county,''), l.county) AS county,
                COALESCE(NULLIF(snap.city,''), l.city) AS city,
                COALESCE(NULLIF(snap.parish,''), l.parish) AS parish,
                COALESCE(NULLIF(snap.postal_code,''), l.postal_code) AS postal_code,
                COALESCE(NULLIF(snap.country,''), l.country) AS country,
                COALESCE(NULLIF(snap.contact_person,''), l.contact_person) AS contact_person,
                COALESCE(NULLIF(snap.phone,''), l.phone) AS phone,
                COALESCE(NULLIF(snap.email,''), l.email) AS email,
                COALESCE(NULLIF(snap.website,''), l.website) AS website,
                COALESCE(snap.lat, l.lat) AS lat,
                COALESCE(snap.lng, l.lng) AS lng,
                l.external_ref,
                COALESCE(NULLIF(snap.place_id,''), l.place_id) AS place_id,
                COALESCE(NULLIF(snap.source,''), l.source) AS source,
                COALESCE(NULLIF(snap.category_name,''), c.name) AS category_name,
                COALESCE(NULLIF(snap.subcategory_name,''), sc.name) AS subcategory_name
            FROM {$px}route_stops rs
            LEFT JOIN {$px}locations l ON l.id=rs.location_id
            LEFT JOIN {$px}route_location_snapshot snap ON snap.id=(SELECT MAX(s2.id) FROM {$px}route_location_snapshot s2 WHERE s2.route_id=rs.route_id AND s2.route_stop_id=rs.id)
            LEFT JOIN {$px}categories c ON c.id=l.category_id
            LEFT JOIN {$px}categories sc ON sc.id=l.subcategory_id
            WHERE rs.route_id=%d
            ORDER BY rs.seq ASC, rs.id ASC", $route_id), ARRAY_A) ?: [];
        foreach ($rows as &$row) {
            $row['meta_decoded'] = self::decode_json_array($row['meta_json'] ?? '');
        }
        unset($row);
        return $rows;
    }

    private static function get_routes_export_stops_by_route(array $route_ids): array {
        $route_ids = array_values(array_unique(array_filter(array_map('intval', $route_ids))));
        if (!$route_ids) return [];
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $placeholders = implode(',', array_fill(0, count($route_ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT rs.*,
                COALESCE(NULLIF(snap.name,''), l.name) AS location_name,
                COALESCE(NULLIF(snap.address,''), l.address) AS address,
                COALESCE(NULLIF(snap.district,''), l.district) AS district,
                COALESCE(NULLIF(snap.county,''), l.county) AS county,
                COALESCE(NULLIF(snap.city,''), l.city) AS city,
                COALESCE(NULLIF(snap.parish,''), l.parish) AS parish,
                COALESCE(NULLIF(snap.postal_code,''), l.postal_code) AS postal_code,
                COALESCE(NULLIF(snap.country,''), l.country) AS country,
                COALESCE(NULLIF(snap.contact_person,''), l.contact_person) AS contact_person,
                COALESCE(NULLIF(snap.phone,''), l.phone) AS phone,
                COALESCE(NULLIF(snap.email,''), l.email) AS email,
                COALESCE(NULLIF(snap.website,''), l.website) AS website,
                COALESCE(snap.lat, l.lat) AS lat,
                COALESCE(snap.lng, l.lng) AS lng,
                l.external_ref,
                COALESCE(NULLIF(snap.place_id,''), l.place_id) AS place_id,
                COALESCE(NULLIF(snap.source,''), l.source) AS source,
                COALESCE(NULLIF(snap.category_name,''), c.name) AS category_name,
                COALESCE(NULLIF(snap.subcategory_name,''), sc.name) AS subcategory_name
            FROM {$px}route_stops rs
            LEFT JOIN {$px}locations l ON l.id=rs.location_id
            LEFT JOIN {$px}route_location_snapshot snap ON snap.id=(SELECT MAX(s2.id) FROM {$px}route_location_snapshot s2 WHERE s2.route_id=rs.route_id AND s2.route_stop_id=rs.id)
            LEFT JOIN {$px}categories c ON c.id=l.category_id
            LEFT JOIN {$px}categories sc ON sc.id=l.subcategory_id
            WHERE rs.route_id IN ({$placeholders})
            ORDER BY rs.route_id ASC, rs.seq ASC, rs.id ASC", ...$route_ids), ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $routeId = (int)($row['route_id'] ?? 0);
            if (!$routeId) continue;
            $row['meta_decoded'] = self::decode_json_array($row['meta_json'] ?? '');
            if (!isset($out[$routeId])) $out[$routeId] = [];
            $out[$routeId][] = $row;
        }
        return $out;
    }

    private static function calculate_route_export_metrics(int $route_id): array {
        if (!$route_id) return ['distance_km' => null, 'known_distance_km' => null, 'travel_min' => null, 'visit_min' => 0, 'total_min' => null, 'end_leg_distance_km' => null, 'end_leg_travel_min' => null, 'toll_cost_eur' => null, 'toll_estimated_eur' => null, 'toll_model' => '', 'distance_status' => 'unavailable', 'routing_incomplete' => 1, 'unresolved_leg_count' => 1];
        global $wpdb;
        $route = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'routespro_routes WHERE id=%d', $route_id), ARRAY_A) ?: [];
        $route['meta_decoded'] = self::decode_json_array($route['meta_json'] ?? '');
        return self::calculate_route_export_metrics_from_route($route, self::get_route_export_stops($route_id));
    }

    private static function calculate_route_export_metrics_from_route(array $route, array $stops): array {
        if (!empty($route['id']) && class_exists('\RoutesPro\Services\RouteMetricsService')) {
            $refresh = \RoutesPro\Services\RouteMetricsService::maybeRefreshGoogleRoute((int)$route['id'], $route, false);
            if (!empty($refresh['ok']) && !empty($refresh['meta'])) {
                $route['meta_decoded'] = (array)$refresh['meta'];
                $route['meta_json'] = wp_json_encode($route['meta_decoded'], JSON_UNESCAPED_UNICODE);
            }
        }
        $metrics = self::calculate_route_export_metrics_from_stops($stops);
        $meta = is_array($route['meta_decoded'] ?? null) ? $route['meta_decoded'] : self::decode_json_array($route['meta_json'] ?? '');
        $stored = [];
        if (isset($meta['route_metrics']) && is_array($meta['route_metrics'])) $stored = $meta['route_metrics'];
        elseif (isset($meta['plan_summary']) && is_array($meta['plan_summary'])) $stored = $meta['plan_summary'];
        foreach (['distance_km','known_distance_km','access_distance_km','inter_stop_distance_km','return_distance_km','travel_min','access_travel_min','inter_stop_travel_min','return_travel_min','visit_min','total_min','work_min','end_leg_distance_km','end_leg_travel_min','end_leg_toll_cost_eur','toll_cost_eur','toll_estimated_eur','unresolved_leg_count','segment_count'] as $key) {
            if (isset($stored[$key]) && is_numeric($stored[$key])) $metrics[$key] = (float)$stored[$key];
        }
        if (isset($stored['toll_model'])) $metrics['toll_model'] = (string)$stored['toll_model'];
        if (isset($stored['toll_provider'])) $metrics['toll_provider'] = (string)$stored['toll_provider'];
        if (isset($stored['routing_provider'])) $metrics['routing_provider'] = (string)$stored['routing_provider'];
        if (isset($stored['distance_source'])) $metrics['distance_source'] = (string)$stored['distance_source'];
        if (isset($stored['distance_status'])) $metrics['distance_status'] = (string)$stored['distance_status'];
        if (isset($stored['routing_incomplete'])) $metrics['routing_incomplete'] = !empty($stored['routing_incomplete']) ? 1 : 0;
        if (isset($stored['toll_is_real_api'])) $metrics['toll_is_real_api'] = !empty($stored['toll_is_real_api']) ? 1 : 0;
        if (class_exists('\RoutesPro\Services\RouteMetricsService')) {
            $routeForCanonical = $route;
            $routeForCanonical['meta_json'] = wp_json_encode($meta, JSON_UNESCAPED_UNICODE);
            $canonical = \RoutesPro\Services\RouteMetricsService::canonicalFromRouteArray($routeForCanonical);
            foreach (['distance_km','known_distance_km','access_distance_km','inter_stop_distance_km','return_distance_km','travel_min','access_travel_min','inter_stop_travel_min','return_travel_min','total_min','work_min','toll_cost_eur','known_toll_cost_eur','toll_estimated_eur','distance_status','endpoint_status','endpoints_complete','endpoints_routable','endpoints_trusted','missing_endpoint_count','routing_incomplete','unresolved_leg_count','segment_count','distance_source','matrix_provider','matrix_source_label','matrix_version'] as $key) {
                if (array_key_exists($key, $canonical)) $metrics[$key] = $canonical[$key];
            }
        }
        $metrics['distance_km'] = isset($metrics['distance_km']) && is_numeric($metrics['distance_km']) ? round((float)$metrics['distance_km'], 2) : null;
        $metrics['known_distance_km'] = isset($metrics['known_distance_km']) && is_numeric($metrics['known_distance_km']) ? round((float)$metrics['known_distance_km'], 2) : $metrics['distance_km'];
        foreach (['access_distance_km','inter_stop_distance_km','return_distance_km','end_leg_distance_km'] as $distanceKey) {
            $metrics[$distanceKey] = isset($metrics[$distanceKey]) && is_numeric($metrics[$distanceKey]) ? round((float)$metrics[$distanceKey], 2) : null;
        }
        $metrics['travel_min'] = isset($metrics['travel_min']) && is_numeric($metrics['travel_min']) ? round((float)$metrics['travel_min'], 1) : null;
        foreach (['access_travel_min','inter_stop_travel_min','return_travel_min','end_leg_travel_min'] as $travelKey) {
            $metrics[$travelKey] = isset($metrics[$travelKey]) && is_numeric($metrics[$travelKey]) ? round((float)$metrics[$travelKey], 1) : null;
        }
        $metrics['visit_min'] = round((float)($metrics['visit_min'] ?? 0), 1);
        if (isset($metrics['total_min']) && is_numeric($metrics['total_min'])) {
            $metrics['total_min'] = round((float)$metrics['total_min'], 1);
        } elseif (isset($metrics['travel_min']) && is_numeric($metrics['travel_min'])) {
            $metrics['total_min'] = round((float)$metrics['travel_min'] + (float)$metrics['visit_min'], 1);
        } else {
            $metrics['total_min'] = null;
        }
        $hasAuthoritativeDistance = isset($metrics['distance_km']) && is_numeric($metrics['distance_km']);
        $tollEstimate = $hasAuthoritativeDistance ? \RoutesPro\Support\TollEstimator::estimateFromKm((float)$metrics['distance_km'], 'route') : [];
        if ($hasAuthoritativeDistance && (!isset($metrics['toll_cost_eur']) || !is_numeric($metrics['toll_cost_eur']) || ((float)$metrics['toll_cost_eur'] <= 0 && (float)$metrics['distance_km'] > 0))) $metrics['toll_cost_eur'] = (float)($tollEstimate['cost_eur'] ?? 0);
        if ($hasAuthoritativeDistance && (!isset($metrics['toll_estimated_eur']) || !is_numeric($metrics['toll_estimated_eur']) || ((float)$metrics['toll_estimated_eur'] <= 0 && (float)$metrics['distance_km'] > 0))) $metrics['toll_estimated_eur'] = (float)($metrics['toll_cost_eur'] ?? 0);
        if ($hasAuthoritativeDistance && empty($metrics['toll_model'])) $metrics['toll_model'] = (string)($tollEstimate['model'] ?? '');
        $metrics['toll_cost_eur'] = isset($metrics['toll_cost_eur']) && is_numeric($metrics['toll_cost_eur']) ? round((float)$metrics['toll_cost_eur'], 2) : null;
        $metrics['toll_estimated_eur'] = isset($metrics['toll_estimated_eur']) && is_numeric($metrics['toll_estimated_eur']) ? round((float)$metrics['toll_estimated_eur'], 2) : $metrics['toll_cost_eur'];
        return $metrics;
    }

    private static function calculate_route_export_metrics_from_stops(array $stops): array {
        $knownDistance = 0.0;
        $knownTravel = 0.0;
        $visit = 0;
        $knownDistanceLegs = 0;
        $knownTravelLegs = 0;
        $missingDistanceLegs = 0;
        $accessDistance = null;
        $accessTravel = null;
        $interStopDistance = 0.0;
        $interStopTravel = 0.0;

        foreach ($stops as $index => $stop) {
            $meta = is_array($stop['meta_decoded'] ?? null) ? $stop['meta_decoded'] : [];
            if (array_key_exists('distance_from_prev_km', $meta) && is_numeric($meta['distance_from_prev_km'])) {
                $legDistance = (float)$meta['distance_from_prev_km'];
                $knownDistance += $legDistance;
                $knownDistanceLegs++;
                if ($index === 0) $accessDistance = $legDistance;
                else $interStopDistance += $legDistance;
            } else {
                $missingDistanceLegs++;
            }
            if (array_key_exists('travel_min_from_prev', $meta) && is_numeric($meta['travel_min_from_prev'])) {
                $legTravel = (float)$meta['travel_min_from_prev'];
                $knownTravel += $legTravel;
                $knownTravelLegs++;
                if ($index === 0) $accessTravel = $legTravel;
                else $interStopTravel += $legTravel;
            }
            if (isset($meta['visit_time_min']) && is_numeric($meta['visit_time_min'])) $visit += max(0, (int)$meta['visit_time_min']);
        }

        // The return leg is unavailable in legacy stop-only records, so the total must remain non-authoritative.
        $unresolvedLegs = max(1, $missingDistanceLegs + 1);
        return [
            'distance_km' => null,
            'known_distance_km' => $knownDistanceLegs > 0 ? round($knownDistance, 2) : null,
            'travel_min' => null,
            'known_travel_min' => $knownTravelLegs > 0 ? round($knownTravel, 1) : null,
            'visit_min' => $visit,
            'total_min' => null,
            'access_distance_km' => $accessDistance !== null ? round($accessDistance, 2) : null,
            'inter_stop_distance_km' => count($stops) > 1 && $knownDistanceLegs > 1 ? round($interStopDistance, 2) : null,
            'return_distance_km' => null,
            'access_travel_min' => $accessTravel !== null ? round($accessTravel, 1) : null,
            'inter_stop_travel_min' => count($stops) > 1 && $knownTravelLegs > 1 ? round($interStopTravel, 1) : null,
            'return_travel_min' => null,
            'end_leg_distance_km' => null,
            'end_leg_travel_min' => null,
            'distance_status' => 'incomplete',
            'distance_source' => 'stored_segments',
            'routing_incomplete' => 1,
            'unresolved_leg_count' => $unresolvedLegs,
            'segment_count' => count($stops) + 1,
            'toll_cost_eur' => null,
            'toll_estimated_eur' => null,
            'toll_model' => '',
        ];
    }

    private static function routes_dataset_to_table(array $dataset, bool $detail = false): array {
        if ($detail) {
            $headers = array_merge(self::route_export_base_headers(true), [
                'Tipo segmento',
                'Ordem segmento',
                'Origem tipo',
                'Origem nome',
                'Origem morada',
                'Origem distrito',
                'Origem concelho',
                'Origem cidade',
                'Origem cod. postal',
                'Destino tipo',
                'Destino ordem PDV',
                'Destino ID PDV',
                'Destino nome',
                'Destino morada',
                'Destino distrito',
                'Destino concelho',
                'Destino cidade',
                'Destino cod. postal',
                'Destino categoria',
                'Destino subcategoria',
                'Destino contacto',
                'Destino telefone',
                'Destino email',
                'Destino website',
                'Destino lat',
                'Destino lng',
                'Distancia segmento km',
                'Portagem segmento estimada EUR',
                'Tempo segmento min',
                'Tempo visita destino min',
                'Estado paragem',
                'Check-in',
                'Check-out',
                'Duracao visita s',
                'Nota',
                'Motivo falha',
                'Origem dados',
            ]);
            $rows = [];
            foreach ((array)($dataset['routes'] ?? []) as $route) {
                foreach (self::route_export_segment_rows($route) as $row) {
                    $rows[] = $row;
                }
            }
            return ['headers' => $headers, 'rows' => $rows];
        }

        $headers = self::route_export_base_headers(false);
        $rows = [];
        foreach ((array)($dataset['routes'] ?? []) as $route) {
            $rows[] = self::route_export_base_row($route, false, false);
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function route_export_base_headers(bool $detail): array {
        $headers = ['ID rota','Chave rota','Data','Cliente','Campanha / Projeto','Status','Ocorrência ativa','Motivo ocorrência','Nota ocorrência','Último aviso ocorrência','Estado último aviso','Owner ID','Owner','Email owner'];
        if ($detail) {
            return array_merge($headers, ['Total PDVs na rota, apenas 1a linha','Segmentos na rota, apenas 1a linha','Rota contabilizavel','Km acesso, apenas 1a linha','Km entre PDVs, apenas 1a linha','Km regresso, apenas 1a linha','Total rota km, apenas 1a linha','Estado distancia, apenas 1a linha','Segmentos por calcular, apenas 1a linha','Fonte distancia, apenas 1a linha','Fornecedor matriz, apenas 1a linha','Versao matriz, apenas 1a linha','Metodo otimizacao, apenas 1a linha','Km poupados, apenas 1a linha','Min poupados, apenas 1a linha','Mesma base, apenas 1a linha','Direcao dominante, apenas 1a linha','Peso direcao dominante pct, apenas 1a linha','Numero direcoes, apenas 1a linha','Mudancas direcao, apenas 1a linha','Regressos direcao, apenas 1a linha','Mistura direcoes opostas, apenas 1a linha','Numero localidades, apenas 1a linha','Regressos localidade, apenas 1a linha','Arco territorial graus, apenas 1a linha','Corredor territorial, apenas 1a linha','Risco cruzamento base, apenas 1a linha','Score coerencia territorial, apenas 1a linha','Estado coerencia territorial, apenas 1a linha','Custo portagens rota EUR, apenas 1a linha','Modelo portagens, apenas 1a linha','Tempo viagem rota min, apenas 1a linha','Tempo visita rota min, apenas 1a linha','Tempo total rota min, apenas 1a linha','Ponto partida','Ponto chegada','Criada em','Atualizada em','Perfil mobilidade','Fonte perfil mobilidade','Tipo energia','Viatura','Tipo combustível','Modo híbrido','Preço combustível EUR/L','Consumo L/100km','Litros estimados','Custo combustível EUR','Preço eletricidade EUR/kWh','Consumo kWh/100km','kWh estimados','Custo eletricidade EUR','Custo energia EUR','Custo operacional EUR/km','Custo operacional EUR','Custo deslocação total EUR']);
        }
        return array_merge($headers, ['Total PDVs na rota','Km acesso','Km entre PDVs','Km regresso','Distancia total rota km','Estado distancia','Segmentos por calcular','Fonte distancia','Fornecedor matriz','Versao matriz','Metodo otimizacao','Km poupados','Min poupados','Mesma base','Direcao dominante','Peso direcao dominante pct','Numero direcoes','Mudancas direcao','Regressos direcao','Mistura direcoes opostas','Numero localidades','Regressos localidade','Arco territorial graus','Corredor territorial','Risco cruzamento base','Score coerencia territorial','Estado coerencia territorial','Custo portagens estimado EUR','Modelo portagens','Km regresso estimado','Tempo viagem min','Tempo visita min','Tempo total min','Ponto partida','Ponto chegada','Criada em','Atualizada em','Perfil mobilidade','Fonte perfil mobilidade','Tipo energia','Viatura','Tipo combustível','Modo híbrido','Preço combustível EUR/L','Consumo L/100km','Litros estimados','Custo combustível EUR','Preço eletricidade EUR/kWh','Consumo kWh/100km','kWh estimados','Custo eletricidade EUR','Custo energia EUR','Custo operacional EUR/km','Custo operacional EUR','Custo deslocação total EUR']);
    }

    private static function route_export_base_row(array $route, bool $detail = false, bool $route_countable = true): array {
        $meta = is_array($route['meta_decoded'] ?? null) ? $route['meta_decoded'] : self::decode_json_array($route['meta_json'] ?? '');
        $start = is_array($meta['start_point'] ?? null) ? $meta['start_point'] : [];
        $end = is_array($meta['end_point'] ?? null) ? $meta['end_point'] : [];
        $metrics = is_array($route['metrics'] ?? null) ? $route['metrics'] : ['distance_km'=>'','travel_min'=>'','visit_min'=>'','total_min'=>''];
        $exception = is_array($meta['route_exception'] ?? null) ? $meta['route_exception'] : [];
        $routeId = (int)($route['id'] ?? 0);
        $mobility = is_array($route['mobility_cost'] ?? null) ? (array)$route['mobility_cost'] : MobilityCostService::calculateForRouteArray($route);
        $mobilityCostsAvailable = !empty($mobility['costs_available']);
        $mobilityCells = [
            (string)($mobility['profile_label'] ?? ''),
            (string)($mobility['profile_source'] ?? ''),
            (string)($mobility['energy_type'] ?? ''),
            (string)($mobility['vehicle_label'] ?? ''),
            (string)($mobility['fuel_type'] ?? ''),
            (string)($mobility['hybrid_mode'] ?? ''),
            (string)($mobility['fuel_price_eur_per_l'] ?? ''),
            (string)($mobility['consumption_l_per_100km'] ?? ''),
            $mobilityCostsAvailable ? (string)($mobility['estimated_litres'] ?? '') : '',
            $mobilityCostsAvailable ? (string)($mobility['fuel_cost_eur'] ?? '') : '',
            (string)($mobility['electricity_price_eur_per_kwh'] ?? ''),
            (string)($mobility['consumption_kwh_per_100km'] ?? ''),
            $mobilityCostsAvailable ? (string)($mobility['estimated_kwh'] ?? '') : '',
            $mobilityCostsAvailable ? (string)($mobility['electricity_cost_eur'] ?? '') : '',
            $mobilityCostsAvailable ? (string)($mobility['energy_cost_eur'] ?? '') : '',
            (string)($mobility['operational_cost_eur_per_km'] ?? ''),
            $mobilityCostsAvailable ? (string)($mobility['operational_cost_eur'] ?? '') : '',
            $mobilityCostsAvailable ? (string)($mobility['total_mobility_cost_eur'] ?? '') : '',
        ];
        $base = [
            (string)($route['id'] ?? ''),
            trim((string)($route['date'] ?? '') . '|' . (string)($route['owner_user_id'] ?? '') . '|' . (string)$routeId, '|'),
            (string)($route['date'] ?? ''),
            (string)($route['client_name'] ?? ''),
            (string)($route['project_name'] ?? ''),
            (string)($route['status'] ?? ''),
            !empty($exception['active']) ? '1' : '0',
            (string)($exception['reason_label'] ?? ''),
            (string)($exception['note'] ?? ''),
            (string)($exception['last_notified_at'] ?? ''),
            (string)($exception['last_notification_result'] ?? ''),
            (string)($route['owner_user_id'] ?? ''),
            (string)($route['owner_name'] ?? ''),
            (string)($route['owner_email'] ?? ''),
            $detail ? ($route_countable ? (string)($route['stops_count'] ?? '') : '') : (string)($route['stops_count'] ?? ''),
        ];
        if ($detail) {
            $segments = max(1, count((array)($route['stops'] ?? [])) + 1);
            return array_merge($base, [
                $route_countable ? (string)$segments : '',
                $route_countable ? '1' : '0',
                $route_countable ? (string)($metrics['access_distance_km'] ?? '') : '',
                $route_countable ? (string)($metrics['inter_stop_distance_km'] ?? '') : '',
                $route_countable ? (string)($metrics['return_distance_km'] ?? $metrics['end_leg_distance_km'] ?? '') : '',
                $route_countable ? (string)($metrics['distance_km'] ?? '') : '',
                $route_countable ? (string)($metrics['distance_status'] ?? '') : '',
                $route_countable ? (string)($metrics['unresolved_leg_count'] ?? '') : '',
                $route_countable ? (string)($metrics['distance_source'] ?? '') : '',
                $route_countable ? (string)($metrics['matrix_provider'] ?? '') : '',
                $route_countable ? (string)($metrics['matrix_version'] ?? '') : '',
                $route_countable ? (string)($metrics['optimization_method'] ?? '') : '',
                $route_countable ? (string)($metrics['optimization_saved_distance_km'] ?? '') : '',
                $route_countable ? (string)($metrics['optimization_saved_travel_min'] ?? '') : '',
                $route_countable ? (!empty($metrics['territory_same_base']) ? '1' : '0') : '',
                $route_countable ? (string)($metrics['territory_dominant_direction_label'] ?? $metrics['territory_dominant_direction'] ?? '') : '',
                $route_countable ? (string)($metrics['territory_dominant_share_pct'] ?? '') : '',
                $route_countable ? (string)($metrics['territory_direction_count'] ?? '') : '',
                $route_countable ? (string)($metrics['territory_direction_switches'] ?? '') : '',
                $route_countable ? (string)($metrics['territory_direction_reentries'] ?? '') : '',
                $route_countable ? (!empty($metrics['territory_opposite_mix']) ? '1' : '0') : '',
                $route_countable ? (string)($metrics['territory_locality_count'] ?? '') : '',
                $route_countable ? (string)($metrics['territory_locality_reentries'] ?? '') : '',
                $route_countable ? (string)($metrics['territory_bearing_span_deg'] ?? '') : '',
                $route_countable ? (string)($metrics['territory_route_cone_label'] ?? '') : '',
                $route_countable ? (!empty($metrics['territory_base_crossing_risk']) ? '1' : '0') : '',
                $route_countable ? (string)($metrics['territory_coherence_score'] ?? '') : '',
                $route_countable ? (string)($metrics['territory_coherence_status'] ?? '') : '',
                $route_countable ? (string)($metrics['toll_cost_eur'] ?? '') : '',
                $route_countable ? (string)($metrics['toll_model'] ?? '') : '',
                $route_countable ? (string)($metrics['travel_min'] ?? '') : '',
                $route_countable ? (string)($metrics['visit_min'] ?? '') : '',
                $route_countable ? (string)($metrics['total_min'] ?? '') : '',
                (string)($start['address'] ?? ''),
                (string)($end['address'] ?? ''),
                (string)($route['created_at'] ?? ''),
                (string)($route['updated_at'] ?? ''),
            ], $route_countable ? $mobilityCells : array_fill(0, count($mobilityCells), ''));
        }
        return array_merge($base, [
            (string)($metrics['access_distance_km'] ?? ''),
            (string)($metrics['inter_stop_distance_km'] ?? ''),
            (string)($metrics['return_distance_km'] ?? $metrics['end_leg_distance_km'] ?? ''),
            (string)($metrics['distance_km'] ?? ''),
            (string)($metrics['distance_status'] ?? ''),
            (string)($metrics['unresolved_leg_count'] ?? ''),
            (string)($metrics['distance_source'] ?? ''),
            (string)($metrics['matrix_provider'] ?? ''),
            (string)($metrics['matrix_version'] ?? ''),
            (string)($metrics['optimization_method'] ?? ''),
            (string)($metrics['optimization_saved_distance_km'] ?? ''),
            (string)($metrics['optimization_saved_travel_min'] ?? ''),
            !empty($metrics['territory_same_base']) ? '1' : '0',
            (string)($metrics['territory_dominant_direction_label'] ?? $metrics['territory_dominant_direction'] ?? ''),
            (string)($metrics['territory_dominant_share_pct'] ?? ''),
            (string)($metrics['territory_direction_count'] ?? ''),
            (string)($metrics['territory_direction_switches'] ?? ''),
            (string)($metrics['territory_direction_reentries'] ?? ''),
            !empty($metrics['territory_opposite_mix']) ? '1' : '0',
            (string)($metrics['territory_locality_count'] ?? ''),
            (string)($metrics['territory_locality_reentries'] ?? ''),
            (string)($metrics['territory_bearing_span_deg'] ?? ''),
            (string)($metrics['territory_route_cone_label'] ?? ''),
            !empty($metrics['territory_base_crossing_risk']) ? '1' : '0',
            (string)($metrics['territory_coherence_score'] ?? ''),
            (string)($metrics['territory_coherence_status'] ?? ''),
            (string)($metrics['toll_cost_eur'] ?? ''),
            (string)($metrics['toll_model'] ?? ''),
            (string)($metrics['end_leg_distance_km'] ?? ''),
            (string)($metrics['travel_min'] ?? ''),
            (string)($metrics['visit_min'] ?? ''),
            (string)($metrics['total_min'] ?? ''),
            (string)($start['address'] ?? ''),
            (string)($end['address'] ?? ''),
            (string)($route['created_at'] ?? ''),
            (string)($route['updated_at'] ?? ''),
        ], $mobilityCells);
    }

    private static function route_export_segment_rows(array $route): array {
        $rows = [];
        $stops = array_values((array)($route['stops'] ?? []));
        $meta = is_array($route['meta_decoded'] ?? null) ? $route['meta_decoded'] : self::decode_json_array($route['meta_json'] ?? '');
        $metrics = is_array($route['metrics'] ?? null) ? $route['metrics'] : [];
        $startPoint = self::route_export_named_point('Partida', is_array($meta['start_point'] ?? null) ? $meta['start_point'] : []);
        $endPoint = self::route_export_named_point('Chegada', is_array($meta['end_point'] ?? null) ? $meta['end_point'] : []);
        $previous = $startPoint;
        $segmentNo = 1;

        foreach ($stops as $stop) {
            $stopPoint = self::route_export_point_from_stop($stop);
            $stopMeta = is_array($stop['meta_decoded'] ?? null) ? $stop['meta_decoded'] : [];
            $leg = self::route_export_leg_details($previous, $stopPoint, $stopMeta, 'from_prev');
            $rows[] = array_merge(
                self::route_export_base_row($route, true, $segmentNo === 1),
                self::route_export_segment_row_cells('Visita PDV', $segmentNo, $previous, $stopPoint, $leg, $stop, $stopMeta)
            );
            $previous = $stopPoint;
            $segmentNo++;
        }

        $endLeg = [
            'distance_km' => isset($metrics['end_leg_distance_km']) && is_numeric($metrics['end_leg_distance_km']) ? (float)$metrics['end_leg_distance_km'] : null,
            'travel_min' => isset($metrics['end_leg_travel_min']) && is_numeric($metrics['end_leg_travel_min']) ? (float)$metrics['end_leg_travel_min'] : null,
            'end_leg_toll_cost_eur' => isset($metrics['end_leg_toll_cost_eur']) && is_numeric($metrics['end_leg_toll_cost_eur']) ? (float)$metrics['end_leg_toll_cost_eur'] : null,
        ];
        $endLeg = self::route_export_leg_details($previous, $endPoint, $endLeg, 'explicit');
        $rows[] = array_merge(
            self::route_export_base_row($route, true, $segmentNo === 1),
            self::route_export_segment_row_cells('Regresso / chegada', $segmentNo, $previous, $endPoint, $endLeg, [], [])
        );

        return $rows;
    }

    private static function route_export_named_point(string $type, array $point): array {
        return [
            'type' => $type,
            'order' => '',
            'location_id' => '',
            'name' => (string)($point['name'] ?? $type),
            'address' => (string)($point['address'] ?? ''),
            'district' => (string)($point['district'] ?? ''),
            'county' => (string)($point['county'] ?? ''),
            'city' => (string)($point['city'] ?? ''),
            'postal_code' => (string)($point['postal_code'] ?? ''),
            'category_name' => '',
            'subcategory_name' => '',
            'contact_person' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
            'lat' => is_numeric($point['lat'] ?? null) ? (float)$point['lat'] : '',
            'lng' => is_numeric($point['lng'] ?? null) ? (float)$point['lng'] : '',
        ];
    }

    private static function route_export_point_from_stop(array $stop): array {
        return [
            'type' => 'PDV',
            'order' => (string)((int)($stop['seq'] ?? 0) + 1),
            'location_id' => (string)($stop['location_id'] ?? ''),
            'name' => (string)($stop['location_name'] ?? ''),
            'address' => (string)($stop['address'] ?? ''),
            'district' => (string)($stop['district'] ?? ''),
            'county' => (string)($stop['county'] ?? ''),
            'city' => (string)($stop['city'] ?? ''),
            'postal_code' => (string)($stop['postal_code'] ?? ''),
            'category_name' => (string)($stop['category_name'] ?? ''),
            'subcategory_name' => (string)($stop['subcategory_name'] ?? ''),
            'contact_person' => (string)($stop['contact_person'] ?? ''),
            'phone' => (string)($stop['phone'] ?? ''),
            'email' => (string)($stop['email'] ?? ''),
            'website' => (string)($stop['website'] ?? ''),
            'lat' => is_numeric($stop['lat'] ?? null) ? (float)$stop['lat'] : '',
            'lng' => is_numeric($stop['lng'] ?? null) ? (float)$stop['lng'] : '',
        ];
    }

    private static function route_export_leg_details(array $from, array $to, array $meta, string $mode): array {
        $distance = null;
        $travel = null;
        if ($mode === 'explicit') {
            if (isset($meta['distance_km']) && is_numeric($meta['distance_km'])) $distance = (float)$meta['distance_km'];
            if (isset($meta['travel_min']) && is_numeric($meta['travel_min'])) $travel = (float)$meta['travel_min'];
        } else {
            if (isset($meta['distance_from_prev_km']) && is_numeric($meta['distance_from_prev_km'])) $distance = (float)$meta['distance_from_prev_km'];
            if (isset($meta['travel_min_from_prev']) && is_numeric($meta['travel_min_from_prev'])) $travel = (float)$meta['travel_min_from_prev'];
        }
        if ($distance === null || $travel === null) {
            $estimated = self::estimate_leg_details($from, $to);
            if ($distance === null && isset($estimated['distance_km']) && is_numeric($estimated['distance_km'])) $distance = (float)$estimated['distance_km'];
            if ($travel === null && isset($estimated['travel_min']) && is_numeric($estimated['travel_min'])) $travel = (float)$estimated['travel_min'];
        }
        $distance = $distance !== null ? round((float)$distance, 2) : null;
        $tollCost = null;
        foreach (['toll_cost_eur_from_prev','toll_cost_eur','end_leg_toll_cost_eur'] as $tollKey) {
            if (isset($meta[$tollKey]) && is_numeric($meta[$tollKey])) { $tollCost = (float)$meta[$tollKey]; break; }
        }
        if ($tollCost === null) {
            $toll = \RoutesPro\Support\TollEstimator::estimateFromKm($distance !== null ? $distance : 0.0, 'segment');
            $tollCost = (float)($toll['cost_eur'] ?? 0);
        }
        return ['distance_km' => $distance, 'toll_cost_eur' => $distance !== null ? round((float)$tollCost, 2) : null, 'travel_min' => $travel !== null ? round((float)$travel, 1) : null];
    }

    private static function route_export_segment_row_cells(string $type, int $segmentNo, array $origin, array $destination, array $leg, array $stop = [], array $meta = []): array {
        return [
            $type,
            (string)$segmentNo,
            (string)($origin['type'] ?? ''),
            (string)($origin['name'] ?? ''),
            (string)($origin['address'] ?? ''),
            (string)($origin['district'] ?? ''),
            (string)($origin['county'] ?? ''),
            (string)($origin['city'] ?? ''),
            (string)($origin['postal_code'] ?? ''),
            (string)($destination['type'] ?? ''),
            (string)($destination['order'] ?? ''),
            (string)($destination['location_id'] ?? ''),
            (string)($destination['name'] ?? ''),
            (string)($destination['address'] ?? ''),
            (string)($destination['district'] ?? ''),
            (string)($destination['county'] ?? ''),
            (string)($destination['city'] ?? ''),
            (string)($destination['postal_code'] ?? ''),
            (string)($destination['category_name'] ?? ''),
            (string)($destination['subcategory_name'] ?? ''),
            (string)($destination['contact_person'] ?? ''),
            (string)($destination['phone'] ?? ''),
            (string)($destination['email'] ?? ''),
            (string)($destination['website'] ?? ''),
            (string)($destination['lat'] ?? ''),
            (string)($destination['lng'] ?? ''),
            (string)($leg['distance_km'] ?? ''),
            (string)($leg['toll_cost_eur'] ?? ''),
            (string)($leg['travel_min'] ?? ''),
            (string)($meta['visit_time_min'] ?? ''),
            (string)($stop['status'] ?? ''),
            (string)($stop['arrived_at'] ?? ''),
            (string)($stop['departed_at'] ?? ''),
            (string)($stop['duration_s'] ?? ''),
            (string)($stop['note'] ?? ''),
            (string)($stop['fail_reason'] ?? ''),
            (string)($stop['source'] ?? ''),
        ];
    }

    private static function write_routes_csv_file(string $path, array $headers, array $rows): void {
        $out = @fopen($path, 'wb');
        if (!$out) throw new \RuntimeException('Não foi possível abrir o CSV de rotas para escrita.');
        fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, array_map([self::class, 'normalize_export_value'], $headers));
        foreach ($rows as $row) fputcsv($out, array_map([self::class, 'normalize_export_value'], $row));
        fclose($out);
    }

    private static function build_routes_excel_content(array $headers, array $rows): string {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Rotas"><Table>';
        $xml .= '<Row>';
        foreach ($headers as $cell) $xml .= '<Cell><Data ss:Type="String">' . self::xml_escape_export($cell) . '</Data></Cell>';
        $xml .= '</Row>';
        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($row as $cell) $xml .= '<Cell><Data ss:Type="String">' . self::xml_escape_export((string)$cell) . '</Data></Cell>';
            $xml .= '</Row>';
        }
        $xml .= '</Table></Worksheet></Workbook>';
        return $xml;
    }

    private static function output_routes_csv(string $filename, array $headers, array $rows): void {
        while (ob_get_level() > 0) @ob_end_clean();
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('X-Content-Type-Options: nosniff');
        $out = fopen('php://output', 'wb');
        fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, array_map([self::class, 'normalize_export_value'], $headers));
        foreach ($rows as $row) fputcsv($out, array_map([self::class, 'normalize_export_value'], $row));
        fclose($out);
        exit;
    }

    private static function output_routes_excel(string $filename, array $headers, array $rows): void {
        $content = self::build_routes_excel_content($headers, $rows);
        while (ob_get_level() > 0) @ob_end_clean();
        nocache_headers();
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . strlen($content));
        header('X-Content-Type-Options: nosniff');
        echo $content;
        exit;
    }

    private static function build_routes_pdf_content(array $filters, array $dataset): string {

        $lines = [];
        $lines[] = 'FieldFlow, relatório de rotas';
        $lines[] = 'Exportado em: ' . wp_date('d/m/Y H:i');
        $lines[] = 'Total de rotas: ' . count((array)($dataset['routes'] ?? []));
        foreach (self::route_export_filter_lines($filters) as $filterLine) $lines[] = $filterLine;
        $lines[] = '';
        foreach ((array)($dataset['routes'] ?? []) as $route) {
            $metrics = is_array($route['metrics'] ?? null) ? $route['metrics'] : [];
            $lines[] = 'Rota #' . (int)($route['id'] ?? 0) . ' | ' . ($route['date'] ?? '') . ' | ' . ($route['client_name'] ?? 'Sem cliente') . ' | ' . ($route['project_name'] ?? 'Sem campanha');
            $lines[] = 'Status: ' . ($route['status'] ?? '') . ' | Owner: ' . ($route['owner_name'] ?? 'Sem owner') . ' | PDVs: ' . (int)($route['stops_count'] ?? 0);
            $distanceLabel = is_numeric($metrics['distance_km'] ?? null) ? ((string)$metrics['distance_km'] . ' km') : 'por calcular';
            $tollLabel = is_numeric($metrics['toll_cost_eur'] ?? null) ? \RoutesPro\Support\TollEstimator::formatEuro((float)$metrics['toll_cost_eur']) : 'por calcular';
            $travelLabel = is_numeric($metrics['travel_min'] ?? null) ? ((string)$metrics['travel_min'] . ' min') : 'por calcular';
            $totalLabel = is_numeric($metrics['total_min'] ?? null) ? ((string)$metrics['total_min'] . ' min') : 'por calcular';
            $lines[] = 'Distancia: ' . $distanceLabel . ' | Estado: ' . (string)($metrics['distance_status'] ?? 'incomplete') . ' | Segmentos por calcular: ' . (int)($metrics['unresolved_leg_count'] ?? 0) . ' | Portagens estimadas: ' . $tollLabel . ' | Viagem: ' . $travelLabel . ' | Visita: ' . ($metrics['visit_min'] ?? 0) . ' min | Total: ' . $totalLabel;
            $mobility = is_array($route['mobility_cost'] ?? null) ? (array)$route['mobility_cost'] : MobilityCostService::calculateForRouteArray($route);
            if (!empty($mobility['enabled'])) {
                if (!empty($mobility['costs_available'])) {
                    $lines[] = 'Mobilidade: ' . (string)($mobility['profile_label'] ?? '') . ' | Energia: ' . \RoutesPro\Support\TollEstimator::formatEuro((float)($mobility['energy_cost_eur'] ?? 0)) . ' | Custo operacional: ' . \RoutesPro\Support\TollEstimator::formatEuro((float)($mobility['operational_cost_eur'] ?? 0)) . ' | Deslocacao total: ' . \RoutesPro\Support\TollEstimator::formatEuro((float)($mobility['total_mobility_cost_eur'] ?? 0)) . ' | Litros: ' . (string)($mobility['estimated_litres'] ?? 0) . ' | kWh: ' . (string)($mobility['estimated_kwh'] ?? 0);
                } else {
                    $lines[] = 'Mobilidade: ' . (string)($mobility['profile_label'] ?? '') . ' | Custos: POR CALCULAR, distância incompleta entre partida e chegada';
                }
            }
            $lines[] = 'Km acesso: ' . ($metrics['access_distance_km'] ?? 'por calcular') . ' | Km entre PDVs: ' . ($metrics['inter_stop_distance_km'] ?? 'por calcular') . ' | Km regresso: ' . ($metrics['return_distance_km'] ?? $metrics['end_leg_distance_km'] ?? 'por calcular') . ' | Fonte: ' . ($metrics['distance_source'] ?? '');
            $lines[] = 'Matriz: ' . ($metrics['matrix_provider'] ?? '') . ' | Versao: ' . ($metrics['matrix_version'] ?? '') . ' | Metodo: ' . ($metrics['optimization_method'] ?? '') . ' | Poupanca: ' . ($metrics['optimization_saved_distance_km'] ?? 0) . ' km / ' . ($metrics['optimization_saved_travel_min'] ?? 0) . ' min';
            $lines[] = 'Territorio: ' . ($metrics['territory_dominant_direction_label'] ?? $metrics['territory_dominant_direction'] ?? 'Por calcular') . ' | Coerencia: ' . ($metrics['territory_coherence_label'] ?? $metrics['territory_coherence_status'] ?? 'Por calcular') . ' (' . ($metrics['territory_coherence_score'] ?? 0) . '/100) | Localidades: ' . ($metrics['territory_locality_count'] ?? 0) . ' | Regressos localidade: ' . ($metrics['territory_locality_reentries'] ?? 0) . ' | Arco: ' . ($metrics['territory_bearing_span_deg'] ?? 0) . ' graus | Corredor: ' . ($metrics['territory_route_cone_label'] ?? 'Por calcular') . ' | Mistura oposta: ' . (!empty($metrics['territory_opposite_mix']) ? 'Sim' : 'Nao');
            $meta = is_array($route['meta_decoded'] ?? null) ? $route['meta_decoded'] : [];
            $start = is_array($meta['start_point'] ?? null) ? trim((string)($meta['start_point']['address'] ?? '')) : '';
            $end = is_array($meta['end_point'] ?? null) ? trim((string)($meta['end_point']['address'] ?? '')) : '';
            if (!empty($metrics['end_leg_distance_km'])) $lines[] = 'Regresso estimado: ' . $metrics['end_leg_distance_km'] . ' km | ' . ($metrics['end_leg_travel_min'] ?? 'por calcular') . ' min';
            if ($start || $end) $lines[] = 'Partida: ' . ($start ?: '--') . ' | Chegada: ' . ($end ?: '--');
            $lines[] = 'Segmentos da rota:';
            $previousName = $start ?: 'Partida';
            $segmentNo = 1;
            if (!empty($route['stops'])) {
                foreach ($route['stops'] as $stop) {
                    $metaStop = is_array($stop['meta_decoded'] ?? null) ? $stop['meta_decoded'] : [];
                    $geo = array_filter([(string)($stop['address'] ?? ''), (string)($stop['postal_code'] ?? ''), (string)($stop['city'] ?? ''), (string)($stop['county'] ?? ''), (string)($stop['district'] ?? '')]);
                    $class = array_filter([(string)($stop['category_name'] ?? ''), (string)($stop['subcategory_name'] ?? '')]);
                    $distance = isset($metaStop['distance_from_prev_km']) ? $metaStop['distance_from_prev_km'] : '';
                    $travel = isset($metaStop['travel_min_from_prev']) ? $metaStop['travel_min_from_prev'] : '';
                    $segToll = isset($metaStop['toll_cost_eur_from_prev']) && is_numeric($metaStop['toll_cost_eur_from_prev']) ? (float)$metaStop['toll_cost_eur_from_prev'] : (is_numeric($distance) ? \RoutesPro\Support\TollEstimator::costFromKm((float)$distance, 'segment') : null);
                    $segmentDistanceLabel = is_numeric($distance) ? ((string)$distance . ' km') : 'por calcular';
                    $segmentTravelLabel = is_numeric($travel) ? ((string)$travel . ' min') : 'por calcular';
                    $segmentTollLabel = is_numeric($segToll) ? (\RoutesPro\Support\TollEstimator::formatEuro((float)$segToll) . ' portagens') : 'portagens por calcular';
                    $lines[] = '  ' . $segmentNo . '. ' . $previousName . ' -> ' . ($stop['location_name'] ?? 'PDV') . ' | ' . $segmentDistanceLabel . ' | ' . $segmentTollLabel . ' | ' . $segmentTravelLabel;
                    $lines[] = '     ' . implode(', ', $geo) . ' | ' . implode(' / ', $class) . ' | visita ' . ($metaStop['visit_time_min'] ?? 0) . ' min';
                    $previousName = (string)($stop['location_name'] ?? 'PDV');
                    $segmentNo++;
                }
            }
            $endDistance = $metrics['end_leg_distance_km'] ?? null;
            $endTravel = $metrics['end_leg_travel_min'] ?? null;
            $endToll = isset($metrics['end_leg_toll_cost_eur']) && is_numeric($metrics['end_leg_toll_cost_eur']) ? (float)$metrics['end_leg_toll_cost_eur'] : (is_numeric($endDistance) ? \RoutesPro\Support\TollEstimator::costFromKm((float)$endDistance, 'segment') : null);
            $endDistanceLabel = is_numeric($endDistance) ? ((string)$endDistance . ' km') : 'por calcular';
            $endTravelLabel = is_numeric($endTravel) ? ((string)$endTravel . ' min') : 'por calcular';
            $endTollLabel = is_numeric($endToll) ? (\RoutesPro\Support\TollEstimator::formatEuro((float)$endToll) . ' portagens') : 'portagens por calcular';
            $lines[] = '  ' . $segmentNo . '. ' . $previousName . ' -> ' . ($end ?: 'Chegada') . ' | ' . $endDistanceLabel . ' | ' . $endTollLabel . ' | ' . $endTravelLabel;
            $lines[] = '';
        }
        return self::build_text_pdf($lines, 'Relatório de Rotas FieldFlow');
    }

    private static function output_routes_pdf(string $filename, array $filters, array $dataset): void {
        $pdf = self::build_routes_pdf_content($filters, $dataset);
        while (ob_get_level() > 0) @ob_end_clean();
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . strlen($pdf));
        header('X-Content-Type-Options: nosniff');
        echo $pdf;
        exit;
    }

    private static function route_export_filter_lines(array $filters): array {
        global $wpdb;
        $lines = ['Filtros aplicados:'];
        if (!empty($filters['route_id'])) $lines[] = 'Rota: #' . (int)$filters['route_id'];
        if (!empty($filters['client_id'])) {
            $name = $wpdb->get_var($wpdb->prepare('SELECT name FROM ' . $wpdb->prefix . 'routespro_clients WHERE id=%d', (int)$filters['client_id']));
            $lines[] = 'Cliente: ' . ($name ?: ('#' . (int)$filters['client_id']));
        }
        if (!empty($filters['project_id'])) {
            $name = $wpdb->get_var($wpdb->prepare('SELECT name FROM ' . $wpdb->prefix . 'routespro_projects WHERE id=%d', (int)$filters['project_id']));
            $lines[] = 'Campanha / Projeto: ' . ($name ?: ('#' . (int)$filters['project_id']));
        }
        if (!empty($filters['owner_user_id'])) {
            $user = get_userdata((int)$filters['owner_user_id']);
            $lines[] = 'Owner: ' . ($user ? $user->display_name : ('#' . (int)$filters['owner_user_id']));
        }
        if (!empty($filters['date'])) $lines[] = 'Data: ' . (string)$filters['date'];
        if (empty($filters['date'])) {
            if (!empty($filters['date_from'])) $lines[] = 'Data de: ' . (string)$filters['date_from'];
            if (!empty($filters['date_to'])) $lines[] = 'Data até: ' . (string)$filters['date_to'];
        }
        if (count($lines) === 1) $lines[] = 'Sem filtros específicos.';
        return $lines;
    }

    private static function build_text_pdf(array $lines, string $title = 'Relatório'): string {
        $pageW = 841.89;
        $pageH = 595.28;
        $marginX = 40;
        $startY = 545;
        $lineH = 13;
        $maxChars = 128;
        $pages = [[]];
        $y = $startY;
        foreach ($lines as $rawLine) {
            $rawLine = self::normalize_export_value($rawLine);
            $chunks = $rawLine === '' ? [''] : preg_split('/\n/', wordwrap($rawLine, $maxChars, "\n", true));
            foreach ($chunks as $line) {
                if ($y < 38) {
                    $pages[] = [];
                    $y = $startY;
                }
                $pages[count($pages) - 1][] = ['text' => (string)$line, 'x' => $marginX, 'y' => $y];
                $y -= $lineH;
            }
        }

        $objects = [];
        $add = function(string $content) use (&$objects): int { $objects[] = $content; return count($objects); };
        $fontRegular = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $fontBold = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        $pageIds = [];
        $contentIds = [];
        $pagesRootHint = 9999;
        foreach ($pages as $i => $pageLines) {
            $stream = "BT\n/F2 15 Tf\n" . $marginX . ' ' . 570 . ' Td (' . self::pdf_escape_export($title) . ") Tj\nET\n";
            $stream .= "BT\n/F1 9 Tf\n";
            foreach ($pageLines as $line) {
                $stream .= '1 0 0 1 ' . (float)$line['x'] . ' ' . (float)$line['y'] . ' Tm (' . self::pdf_escape_export($line['text']) . ") Tj\n";
            }
            $stream .= "ET\n";
            $contentIds[$i] = $add('<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream");
        }
        foreach ($pages as $i => $_) {
            $resources = '<< /Font << /F1 ' . $fontRegular . ' 0 R /F2 ' . $fontBold . ' 0 R >> >>';
            $pageIds[$i] = $add('<< /Type /Page /Parent ' . $pagesRootHint . ' 0 R /MediaBox [0 0 ' . $pageW . ' ' . $pageH . '] /Resources ' . $resources . ' /Contents ' . $contentIds[$i] . ' 0 R >>');
        }
        $kids = implode(' ', array_map(static function($id){ return $id . ' 0 R'; }, $pageIds));
        $pagesRoot = $add('<< /Type /Pages /Kids [ ' . $kids . ' ] /Count ' . count($pageIds) . ' >>');
        foreach ($objects as &$object) $object = str_replace($pagesRootHint . ' 0 R', $pagesRoot . ' 0 R', $object);
        unset($object);
        $catalog = $add('<< /Type /Catalog /Pages ' . $pagesRoot . ' 0 R >>');
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $idx => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($idx + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root ' . $catalog . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }

    private static function decode_json_array($json): array {
        if (!is_string($json) || trim($json) === '') return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function normalize_export_value($value, array $options = []): string {
        $text = is_scalar($value) || (is_object($value) && method_exists($value, '__toString')) ? (string)$value : '';
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x{00A0}\x{2007}\x{202F}]/u', ' ', $text) ?: $text;
        $text = preg_replace('/[\r\n\t]+/u', ' ', $text) ?: $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?: $text;
        $text = trim($text);
        $map = ['–'=>'-','—'=>'-','−'=>'-','•'=>'-','·'=>'-','“'=>'"','”'=>'"','„'=>'"','’'=>"'",'‘'=>"'",'´'=>"'",'…'=>'...'];
        $text = strtr($text, $map);
        if (!empty($options['for_pdf'])) {
            if (function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
                if ($converted !== false) return $converted;
            }
            if (function_exists('iconv')) {
                $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
                if ($converted !== false) return $converted;
            }
        }
        return $text;
    }

    private static function xml_escape_export($value): string {
        return htmlspecialchars(self::normalize_export_value($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function pdf_escape_export(string $text): string {
        $text = self::normalize_export_value($text, ['for_pdf' => true]);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private static function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earth * $c;
    }

}
