<?php
namespace RoutesPro\Admin;

use RoutesPro\Cloud\DestinationRegistry;
use RoutesPro\Cloud\DestinationRouter;
use RoutesPro\Cloud\DestinationTypes;
use RoutesPro\Cloud\MultiDestinationFoundation;
use RoutesPro\Cloud\ClientAzureDelivery;
use RoutesPro\Cloud\ConnectorDelivery;
use RoutesPro\Cloud\ControlledExternalDelivery;
use RoutesPro\Cloud\SafeLivePilot;
use RoutesPro\Cloud\ProductionDeliveryGate;
use RoutesPro\Cloud\CredentialLifecycle;
use RoutesPro\Cloud\GovernanceRecovery;
use RoutesPro\Cloud\Phase5StableRelease;
use RoutesPro\Cloud\ClientAzureRelayConnection;
use RoutesPro\Cloud\ConnectorFoundation;
use RoutesPro\Cloud\EntraCrossTenantFoundation;
use RoutesPro\Cloud\EntraTenantOnboarding;
use RoutesPro\Cloud\ResourceAuthorizationFoundation;
use RoutesPro\Cloud\TenantIdentity;
use RoutesPro\Support\AdminPage;

if (!defined('ABSPATH')) exit;

final class DataDestinations {
    public const SLUG = 'routespro-data-destinations';

    public static function registerHooks(): void {
        add_action('admin_init', [self::class, 'handleActions']);
        EntraTenantOnboarding::registerHooks();
    }

    public static function handleActions(): void {
        if (!is_admin() || !current_user_can('routespro_manage')) return;
        if (sanitize_key((string)($_GET['page'] ?? '')) !== self::SLUG) return;
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') return;
        check_admin_referer('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce');
        $action = sanitize_key((string)($_POST['ff_destination_action'] ?? ''));
        $result = true;
        $message = '';

        if ($action === 'create_destination') {
            $result = DestinationRegistry::createDestination([
                'name'=>$_POST['name'] ?? '',
                'type_key'=>$_POST['type_key'] ?? '',
                'client_id'=>$_POST['client_id'] ?? 0,
                'project_id'=>$_POST['project_id'] ?? 0,
                'mode'=>$_POST['mode'] ?? 'mirror',
                'endpoint_ref'=>$_POST['endpoint_ref'] ?? '',
                'secret_ref'=>$_POST['secret_ref'] ?? '',
                'is_active'=>!empty($_POST['is_active']) ? 1 : 0,
                'auth_strategy'=>$_POST['auth_strategy'] ?? '',
                'retry_policy'=>[
                    'max_attempts'=>$_POST['max_attempts'] ?? 5,
                    'base_delay_seconds'=>$_POST['base_delay_seconds'] ?? 60,
                ],
                'entra_target_tenant_id'=>$_POST['entra_target_tenant_id'] ?? '',
                'entra_resource_scope'=>$_POST['entra_resource_scope'] ?? '',
            ]);
            $message = is_wp_error($result) ? $result->get_error_message() : 'Destino criado no registry Multi-Destination.';
        } elseif ($action === 'toggle_destination') {
            $result = DestinationRegistry::setDestinationActive(absint($_POST['destination_id'] ?? 0), !empty($_POST['enable']));
            $message = is_wp_error($result) ? $result->get_error_message() : 'Estado do destino atualizado.';
        } elseif ($action === 'delete_destination') {
            $result = DestinationRegistry::deleteDestination(absint($_POST['destination_id'] ?? 0));
            $message = is_wp_error($result) ? $result->get_error_message() : 'Destino removido do registry.';
        } elseif ($action === 'create_rule') {
            $result = DestinationRegistry::createRule([
                'destination_id'=>$_POST['destination_id'] ?? 0,
                'client_id'=>$_POST['rule_client_id'] ?? 0,
                'project_id'=>$_POST['rule_project_id'] ?? 0,
                'event_pattern'=>$_POST['event_pattern'] ?? '*',
                'action_name'=>$_POST['action_name'] ?? 'include',
                'priority'=>$_POST['priority'] ?? 100,
                'is_active'=>!empty($_POST['rule_is_active']) ? 1 : 0,
            ]);
            $message = is_wp_error($result) ? $result->get_error_message() : 'Regra de routing criada.';
        } elseif ($action === 'toggle_rule') {
            $result = DestinationRegistry::setRuleActive(absint($_POST['rule_id'] ?? 0), !empty($_POST['enable']));
            $message = is_wp_error($result) ? $result->get_error_message() : 'Estado da regra atualizado.';
        } elseif ($action === 'delete_rule') {
            $result = DestinationRegistry::deleteRule(absint($_POST['rule_id'] ?? 0));
            $message = is_wp_error($result) ? $result->get_error_message() : 'Regra removida.';
        } elseif ($action === 'probe') {
            $probe = MultiDestinationFoundation::localProbe();
            set_transient('fieldflow_5a_probe_' . get_current_user_id(), $probe, 5 * MINUTE_IN_SECONDS);
            $message = !empty($probe['ok']) ? 'Diagnóstico local 5A concluído com sucesso.' : 'O diagnóstico local 5A encontrou pontos a rever.';
            $result = !empty($probe['ok']);
        } elseif ($action === 'route_preview') {
            $event = sanitize_text_field((string)($_POST['preview_event'] ?? 'submission.created'));
            $plan = DestinationRouter::routePlan($event, [
                'client_id'=>absint($_POST['preview_client_id'] ?? 0),
                'project_id'=>absint($_POST['preview_project_id'] ?? 0),
            ]);
            set_transient('fieldflow_5a_route_preview_' . get_current_user_id(), $plan, 5 * MINUTE_IN_SECONDS);
            $message = 'Preview de routing calculado localmente. Nenhum dado foi enviado.';
            $result = true;
        } elseif ($action === 'save_5b_settings') {
            $result = ClientAzureRelayConnection::save([
                'mode'=>$_POST['relay_mode'] ?? 'shadow',
                'relay_endpoint'=>$_POST['relay_endpoint'] ?? '',
                'batch_size'=>$_POST['relay_batch_size'] ?? 10,
                'timeout'=>$_POST['relay_timeout'] ?? 20,
            ]);
            $message = is_wp_error($result) ? $result->get_error_message() : 'Configuração da Fase 5B atualizada.';
        } elseif ($action === 'probe_5b_local') {
            $probe = ClientAzureDelivery::localProbe();
            set_transient('fieldflow_5b_local_probe_' . get_current_user_id(), $probe, 5 * MINUTE_IN_SECONDS);
            $message = !empty($probe['ok']) ? 'Diagnóstico local 5B concluído com sucesso.' : 'O diagnóstico local 5B encontrou pontos a rever.';
            $result = !empty($probe['ok']);
        } elseif ($action === 'dispatch_5b') {
            $summary = ClientAzureDelivery::dispatchNow(10);
            set_transient('fieldflow_5b_dispatch_' . get_current_user_id(), $summary, 5 * MINUTE_IN_SECONDS);
            $result = !empty($summary['ok']);
            if (ClientAzureDelivery::mode() !== 'live') {
                $message = 'O dispatcher 5B não envia enquanto o modo não estiver em Live.';
            } else {
                $message = 'Dispatcher 5B executado. Relayed: ' . (int)($summary['relayed'] ?? 0) . ', retry: ' . (int)($summary['retried'] ?? 0) . ', dead: ' . (int)($summary['dead'] ?? 0) . '.';
            }
        } elseif ($action === 'retry_5b') {
            $count = ClientAzureDelivery::retryDead(absint($_POST['job_id'] ?? 0));
            $result = true;
            $message = $count > 0 ? 'Entrega(s) 5B recolocada(s) na fila.' : 'Não existiam entregas dead para recuperar.';
        } elseif ($action === 'probe_destination_5b') {
            $destination = DestinationRegistry::getDestination(absint($_POST['destination_id'] ?? 0));
            if (!$destination || (string)($destination['type_key'] ?? '') !== 'client_azure') {
                $result = new \WP_Error('fieldflow_5b_destination_invalid', 'Destino Azure do Cliente não encontrado.');
                $message = $result->get_error_message();
            } else {
                $response = ClientAzureRelayConnection::probeDestination($destination);
                if (is_wp_error($response)) {
                    $result = $response;
                    $message = $response->get_error_message();
                    set_transient('fieldflow_5b_destination_probe_' . get_current_user_id(), ['ok'=>false,'error'=>$message], 5 * MINUTE_IN_SECONDS);
                } else {
                    $code = (int)wp_remote_retrieve_response_code($response);
                    $body = json_decode((string)wp_remote_retrieve_body($response), true);
                    if (!is_array($body)) $body = [];
                    $ok = $code >= 200 && $code < 300 && !empty($body['ok']);
                    set_transient('fieldflow_5b_destination_probe_' . get_current_user_id(), [
                        'ok'=>$ok,'http_code'=>$code,'destination_uuid'=>(string)$destination['destination_uuid'],
                        'remote_status'=>sanitize_text_field((string)($body['status'] ?? '')),
                        'delivery_key'=>sanitize_text_field((string)($body['delivery_key'] ?? '')),
                        'error'=>sanitize_text_field((string)($body['error'] ?? '')),
                    ], 5 * MINUTE_IN_SECONDS);
                    $result = $ok;
                    $message = $ok ? 'Teste 5B end-to-end concluído com sucesso.' : 'O Relay respondeu, mas o destino não confirmou o probe 5B.';
                }
            }
        } elseif ($action === 'probe_5c_local') {
            $probe = ConnectorFoundation::localProbe();
            set_transient('fieldflow_5c_local_probe_' . get_current_user_id(), $probe, 5 * MINUTE_IN_SECONDS);
            $message = !empty($probe['ok']) ? 'Diagnóstico local 5C.1 concluído com sucesso.' : 'O diagnóstico local 5C.1 encontrou pontos a rever.';
            $result = !empty($probe['ok']);
        } elseif ($action === 'probe_destination_5c') {
            $destination = DestinationRegistry::getDestination(absint($_POST['destination_id'] ?? 0));
            if (!$destination || !ConnectorFoundation::is5cType((string)($destination['type_key'] ?? ''))) {
                $result = new \WP_Error('fieldflow_5c_destination_invalid', 'Destino Connector Foundation 5C.1 não encontrado.');
                $message = $result->get_error_message();
            } else {
                $probe = ConnectorFoundation::validateRegisteredDestination($destination);
                set_transient('fieldflow_5c_destination_probe_' . get_current_user_id(), $probe, 5 * MINUTE_IN_SECONDS);
                $result = !empty($probe['ok']);
                $message = !empty($probe['ok']) ? 'Contrato 5C.1 validado localmente. Nenhum dado foi enviado.' : ((string)($probe['error'] ?? '') ?: 'O contrato 5C.1 precisa de revisão.');
            }
        } elseif ($action === 'save_5c2_settings') {
            $result = EntraCrossTenantFoundation::save([
                'broker_endpoint'=>$_POST['entra_broker_endpoint'] ?? '',
                'broker_client_id'=>$_POST['entra_broker_client_id'] ?? '',
                'home_tenant_id'=>$_POST['entra_home_tenant_id'] ?? '',
                'timeout'=>$_POST['entra_timeout'] ?? 20,
            ]);
            $message = is_wp_error($result) ? $result->get_error_message() : 'Configuração segura da Fase 5C.2 atualizada. Nenhum segredo foi guardado.';
        } elseif ($action === 'probe_5c2_local') {
            $probe = EntraCrossTenantFoundation::localProbe();
            set_transient('fieldflow_5c2_local_probe_' . get_current_user_id(), $probe, 5 * MINUTE_IN_SECONDS);
            $result = !empty($probe['ok']);
            $message = $result ? 'Diagnóstico local 5C.2 concluído com sucesso.' : 'O diagnóstico local 5C.2 encontrou pontos a rever.';
        } elseif ($action === 'probe_5c2_cloud') {
            $response = EntraCrossTenantFoundation::probeHomeTenant();
            if (is_wp_error($response)) {
                $result = $response;
                $message = $response->get_error_message();
                set_transient('fieldflow_5c2_cloud_probe_' . get_current_user_id(), ['ok'=>false,'error'=>$message], 5 * MINUTE_IN_SECONDS);
            } else {
                $code = (int)wp_remote_retrieve_response_code($response);
                $body = json_decode((string)wp_remote_retrieve_body($response), true);
                if (!is_array($body)) $body = [];
                $ok = $code >= 200 && $code < 300 && !empty($body['ok']) && !empty($body['federated']);
                $safe = [
                    'ok'=>$ok,
                    'http_code'=>$code,
                    'federated'=>!empty($body['federated']),
                    'target_tenant_id'=>sanitize_text_field((string)($body['target_tenant_id'] ?? '')),
                    'scope'=>sanitize_text_field((string)($body['scope'] ?? '')),
                    'expires_in_seconds'=>absint($body['expires_in_seconds'] ?? 0),
                    'error'=>sanitize_text_field((string)($body['error'] ?? '')),
                    'entra_code'=>sanitize_text_field((string)($body['entra_code'] ?? '')),
                ];
                set_transient('fieldflow_5c2_cloud_probe_' . get_current_user_id(), $safe, 5 * MINUTE_IN_SECONDS);
                $result = $ok;
                $message = $ok ? 'Federação Entra 5C.2 validada no tenant TWT sem client secret.' : 'O Entra Broker respondeu, mas a troca federada não ficou validada.';
            }
        } elseif ($action === 'probe_destination_5c2') {
            $destination = DestinationRegistry::getDestination(absint($_POST['destination_id'] ?? 0));
            if (!$destination) {
                $result = new \WP_Error('fieldflow_5c2_destination_missing', 'Destino 5C.2 não encontrado.');
                $message = $result->get_error_message();
            } else {
                $response = EntraCrossTenantFoundation::probeDestination($destination);
                if (is_wp_error($response)) {
                    $result = $response;
                    $message = $response->get_error_message();
                    set_transient('fieldflow_5c2_destination_probe_' . get_current_user_id(), ['ok'=>false,'error'=>$message], 5 * MINUTE_IN_SECONDS);
                } else {
                    $code = (int)wp_remote_retrieve_response_code($response);
                    $body = json_decode((string)wp_remote_retrieve_body($response), true);
                    if (!is_array($body)) $body = [];
                    $ok = $code >= 200 && $code < 300 && !empty($body['ok']) && !empty($body['federated']);
                    set_transient('fieldflow_5c2_destination_probe_' . get_current_user_id(), [
                        'ok'=>$ok,'http_code'=>$code,'federated'=>!empty($body['federated']),
                        'target_tenant_id'=>sanitize_text_field((string)($body['target_tenant_id'] ?? '')),
                        'scope'=>sanitize_text_field((string)($body['scope'] ?? '')),
                        'error'=>sanitize_text_field((string)($body['error'] ?? '')),
                        'entra_code'=>sanitize_text_field((string)($body['entra_code'] ?? '')),
                    ], 5 * MINUTE_IN_SECONDS);
                    $result = $ok;
                    $message = $ok ? 'Token probe Entra cross-tenant concluído sem expor o token.' : 'O destino ainda não está provisionado/autorizado no tenant Entra indicado.';
                }
            }
        } elseif ($action === 'save_5c2_destination_identity') {
            $destinationId = absint($_POST['destination_id'] ?? 0);
            $result = DestinationRegistry::updateEntraIdentity(
                $destinationId,
                (string)($_POST['entra_target_tenant_id'] ?? ''),
                (string)($_POST['entra_resource_scope'] ?? '')
            );
            if (!is_wp_error($result) && $result !== false) {
                delete_transient('fieldflow_5c2_onboarding_link_' . get_current_user_id());
            }
            $message = is_wp_error($result)
                ? $result->get_error_message()
                : 'Tenant Entra e resource scope guardados. O onboarding ficou em DRAFT e já pode gerar a ligação Microsoft.';
        } elseif ($action === 'start_5c2_onboarding') {
            $destination = DestinationRegistry::getDestination(absint($_POST['destination_id'] ?? 0));
            if (!$destination) {
                $result = new \WP_Error('fieldflow_5c2_onboarding_destination_missing', 'Destino Entra não encontrado.');
                $message = $result->get_error_message();
            } else {
                $onboarding = EntraTenantOnboarding::start($destination);
                if (is_wp_error($onboarding)) {
                    $result = $onboarding;
                    $message = $onboarding->get_error_message();
                } else {
                    set_transient('fieldflow_5c2_onboarding_link_' . get_current_user_id(), $onboarding, EntraTenantOnboarding::CONSENT_TTL_SECONDS);
                    $result = true;
                    $message = 'Link seguro Microsoft criado. Pode ser aberto ou enviado ao administrador Entra do cliente. Não exige subscrição Azure nem Cloud Shell no tenant do cliente.';
                }
            }
        } elseif ($action === 'verify_5c2_onboarding') {
            $destination = DestinationRegistry::getDestination(absint($_POST['destination_id'] ?? 0));
            if (!$destination) {
                $result = new \WP_Error('fieldflow_5c2_onboarding_destination_missing', 'Destino Entra não encontrado.');
                $message = $result->get_error_message();
            } else {
                $verified = EntraTenantOnboarding::manualVerify($destination);
                $result = $verified;
                $message = is_wp_error($verified)
                    ? $verified->get_error_message()
                    : 'Tenant externo validado por token probe. A identidade está READY, mas o delivery 5C continua bloqueado em Shadow.';
            }
        } elseif ($action === 'reset_5c2_onboarding') {
            $destination = DestinationRegistry::getDestination(absint($_POST['destination_id'] ?? 0));
            if (!$destination) {
                $result = new \WP_Error('fieldflow_5c2_onboarding_destination_missing', 'Destino Entra não encontrado.');
                $message = $result->get_error_message();
            } else {
                $result = EntraTenantOnboarding::reset($destination);
                $message = is_wp_error($result) ? $result->get_error_message() : 'Estado de onboarding reiniciado para DRAFT. Nenhuma autorização no tenant do cliente foi removida.';
            }
        } elseif ($action === 'create_5c3_home_powerbi_destination') {
            $existingId = 0;
            foreach (DestinationRegistry::listDestinations() as $candidate) {
                if ((string)($candidate['type_key'] ?? '') !== 'client_powerbi') continue;
                $candidateProfile = ConnectorFoundation::profileFromDestination($candidate);
                if ((string)($candidateProfile['auth_strategy'] ?? '') !== 'entra_home_tenant') continue;
                $existingId = absint($candidate['id'] ?? 0);
                break;
            }
            if ($existingId > 0) {
                $result = true;
                $message = 'O destino Power BI TWT já existe. Configura agora o Workspace ID na Fase 5C.3.';
            } else {
                $result = DestinationRegistry::createDestination([
                    'name'=>'Power BI TWT, FieldFlow',
                    'type_key'=>'client_powerbi',
                    'client_id'=>0,
                    'project_id'=>0,
                    'mode'=>'reporting',
                    'endpoint_ref'=>'powerbi://twt-home',
                    'secret_ref'=>'',
                    'is_active'=>0,
                    'auth_strategy'=>'entra_home_tenant',
                    'retry_policy'=>['max_attempts'=>5,'base_delay_seconds'=>60],
                    'entra_target_tenant_id'=>'',
                    'entra_resource_scope'=>'https://analysis.windows.net/powerbi/api/.default',
                ]);
                $message = is_wp_error($result)
                    ? $result->get_error_message()
                    : 'Destino Power BI TWT criado em Shadow e inativo no routing. Configura agora apenas o Workspace ID na Fase 5C.3.';
            }
        } elseif ($action === 'probe_5c3_local') {
            $probe = ResourceAuthorizationFoundation::localProbe();
            set_transient('fieldflow_5c3_local_probe_' . get_current_user_id(), $probe, 5 * MINUTE_IN_SECONDS);
            $result = !empty($probe['ok']);
            $message = $result ? 'Diagnóstico local 5C.3 concluído com sucesso.' : 'O diagnóstico local 5C.3 encontrou pontos a rever.';
        } elseif ($action === 'probe_5c3_broker') {
            $response = ResourceAuthorizationFoundation::probeBrokerHealth();
            if (is_wp_error($response)) {
                $result = $response;
                $message = $response->get_error_message();
                set_transient('fieldflow_5c3_broker_probe_' . get_current_user_id(), ['ok'=>false,'error'=>$message], 5 * MINUTE_IN_SECONDS);
            } else {
                $code = (int)wp_remote_retrieve_response_code($response);
                $body = json_decode((string)wp_remote_retrieve_body($response), true);
                if (!is_array($body)) $body = [];
                $ok = $code >= 200 && $code < 300 && !empty($body['ok'])
                    && version_compare((string)($body['version'] ?? '0.0.0'), '1.3.0', '>=')
                    && !empty($body['resource_authorization_control_plane'])
                    && !empty($body['resource_probe_read_only'])
                    && empty($body['delivery_enabled']);
                $safe = [
                    'ok'=>$ok,
                    'http_code'=>$code,
                    'version'=>sanitize_text_field((string)($body['version'] ?? '')),
                    'hmac_runtime_ready'=>!empty($body['hmac_runtime_ready']),
                    'hmac_runtime_source'=>sanitize_key((string)($body['hmac_runtime_source'] ?? '')),
                    'resource_authorization_control_plane'=>!empty($body['resource_authorization_control_plane']),
                    'resource_probe_read_only'=>!empty($body['resource_probe_read_only']),
                    'delivery_enabled'=>!empty($body['delivery_enabled']),
                    'error'=>sanitize_text_field((string)($body['error'] ?? '')),
                ];
                set_transient('fieldflow_5c3_broker_probe_' . get_current_user_id(), $safe, 5 * MINUTE_IN_SECONDS);
                $result = $ok;
                $message = $ok ? 'Entra Broker 5C.3 validado: resource probes read-only ativos e delivery bloqueado.' : 'O Broker respondeu, mas ainda não expõe integralmente a Foundation 5C.3.';
            }
        } elseif ($action === 'save_5c3_resource') {
            $destinationId = absint($_POST['destination_id'] ?? 0);
            $result = ResourceAuthorizationFoundation::configureDestination($destinationId, [
                'sql_server'=>$_POST['resource_sql_server'] ?? '',
                'sql_database'=>$_POST['resource_sql_database'] ?? '',
                'powerbi_workspace_id'=>$_POST['resource_powerbi_workspace_id'] ?? '',
            ]);
            $message = is_wp_error($result)
                ? $result->get_error_message()
                : 'Recurso 5C.3 configurado. O delivery continua bloqueado; apenas o probe read-only pode ser executado.';
        } elseif ($action === 'probe_5c3_resource') {
            $destination = DestinationRegistry::getDestination(absint($_POST['destination_id'] ?? 0));
            if (!$destination) {
                $result = new \WP_Error('fieldflow_5c3_destination_missing', 'Destino 5C.3 não encontrado.');
                $message = $result->get_error_message();
            } else {
                $verified = ResourceAuthorizationFoundation::probeDestination($destination);
                if (is_wp_error($verified)) {
                    $result = $verified;
                    $message = $verified->get_error_message();
                    set_transient('fieldflow_5c3_resource_probe_' . get_current_user_id(), ['ok'=>false,'error'=>$message], 5 * MINUTE_IN_SECONDS);
                } else {
                    $result = true;
                    $message = 'Autorização do recurso 5C.3 validada em modo read-only. O delivery continua bloqueado.';
                    set_transient('fieldflow_5c3_resource_probe_' . get_current_user_id(), $verified, 5 * MINUTE_IN_SECONDS);
                }
            }
        } elseif ($action === 'reset_5c3_resource') {
            $result = ResourceAuthorizationFoundation::resetDestination(absint($_POST['destination_id'] ?? 0));
            $message = is_wp_error($result) ? $result->get_error_message() : 'Estado 5C.3 reiniciado. Nenhuma permissão foi removida no recurso do cliente.';
        } elseif ($action === 'save_5c5_settings') {
            $settings55 = ConnectorDelivery::save([
                'mode'=>$_POST['connector_5c5_mode'] ?? 'shadow',
                'kill_switch'=>!empty($_POST['connector_5c5_kill_switch']) ? 1 : 0,
                'batch_size'=>$_POST['connector_5c5_batch_size'] ?? 10,
                'timeout'=>$_POST['connector_5c5_timeout'] ?? 20,
                'max_attempts'=>$_POST['connector_5c5_max_attempts'] ?? 5,
                'base_delay_seconds'=>$_POST['connector_5c5_base_delay'] ?? 60,
                'circuit_failure_threshold'=>$_POST['connector_5c5_circuit_failures'] ?? 5,
                'circuit_open_seconds'=>$_POST['connector_5c5_circuit_open'] ?? 900,
                'retention_days'=>$_POST['connector_5c5_retention_days'] ?? 30,
            ]);
            $result = true;
            $message = 'Configuração 5C.5 guardada em ' . strtoupper((string)$settings55['mode']) . '. O dispatcher operacional continua bloqueado; apenas a certificação sintética 5C.6 pode usar rede.';
        } elseif ($action === 'probe_5c5_local') {
            $probe = ConnectorDelivery::localProbe();
            set_transient('fieldflow_5c5_local_probe_' . get_current_user_id(), $probe, 5 * MINUTE_IN_SECONDS);
            $result = !empty($probe['ok']);
            $message = $result ? 'Diagnóstico local 5C.5 concluído com sucesso. Intake, idempotência e fila estão prontos, sem tráfego externo.' : 'O diagnóstico local 5C.5 encontrou pontos a rever.';
        } elseif ($action === 'certify_5c5_shadow') {
            $cert = ConnectorDelivery::certificationProbe(absint($_POST['connector_5c5_destination_id'] ?? 0), 'shadow');
            set_transient('fieldflow_5c5_certification_' . get_current_user_id(), $cert, 10 * MINUTE_IN_SECONDS);
            $result = !empty($cert['ok']);
            $message = $result
                ? 'Certificação Shadow 5C.5 concluída: routing, fila, redaction e idempotência validados sem emitir tráfego externo.'
                : 'A certificação Shadow 5C.5 encontrou um ponto a rever: ' . sanitize_text_field((string)($cert['message'] ?? $cert['reason'] ?? 'certification_failed'));
        } elseif ($action === 'certify_5c5_queue') {
            $cert = ConnectorDelivery::certificationProbe(absint($_POST['connector_5c5_destination_id'] ?? 0), 'queue');
            set_transient('fieldflow_5c5_certification_' . get_current_user_id(), $cert, 10 * MINUTE_IN_SECONDS);
            $result = !empty($cert['ok']);
            $message = $result
                ? 'Certificação Queue 5C.5 concluída: pending, payload sintético e idempotência validados. A rede continua hard-locked.'
                : 'A certificação Queue 5C.5 encontrou um ponto a rever: ' . sanitize_text_field((string)($cert['message'] ?? $cert['reason'] ?? 'certification_failed'));
        } elseif ($action === 'dispatch_5c5') {
            $summary = ConnectorDelivery::dispatchNow(10);
            set_transient('fieldflow_5c5_dispatch_' . get_current_user_id(), $summary, 5 * MINUTE_IN_SECONDS);
            $result = true;
            $message = !empty($summary['blocked'])
                ? 'Gate 5C.5 confirmado: o dispatcher operacional permanece bloqueado. A 5C.6 usa um sender separado apenas para certificação sintética.'
                : 'Dispatcher 5C.5 executado.';
        } elseif ($action === 'retry_5c5') {
            $count = ConnectorDelivery::retryDead(absint($_POST['job_id'] ?? 0));
            $result = true;
            $message = $count > 0 ? 'Job(s) 5C.5 recolocados em pending. O dispatcher operacional continua bloqueado; apenas a certificação sintética 5C.6 pode usar rede.' : 'Não existiam jobs dead 5C.5 para recuperar.';
        } elseif ($action === 'probe_5c6_local') {
            $probe56 = ControlledExternalDelivery::localProbe();
            set_transient('fieldflow_5c6_local_probe_' . get_current_user_id(), $probe56, 10 * MINUTE_IN_SECONDS);
            $result = !empty($probe56['ok']);
            $message = $result ? 'Diagnóstico local 5C.6 OK. O sender está isolado para certificação e o delivery operacional continua bloqueado.' : 'O diagnóstico local 5C.6 encontrou um ponto a rever.';
        } elseif ($action === 'probe_5c6_broker') {
            $probe56 = ControlledExternalDelivery::probeBroker();
            if (is_wp_error($probe56)) {
                $result = $probe56;
                $message = $probe56->get_error_message();
                set_transient('fieldflow_5c6_broker_probe_' . get_current_user_id(), ['ok'=>false,'error'=>$message], 10 * MINUTE_IN_SECONDS);
            } else {
                set_transient('fieldflow_5c6_broker_probe_' . get_current_user_id(), $probe56, 10 * MINUTE_IN_SECONDS);
                $result = !empty($probe56['ok']);
                $message = $result ? 'Broker 1.5.0+ validado para Controlled Delivery 5C.6, certification-only, receiver fixo e receipts assinados.' : 'O Broker respondeu, mas ainda não expõe integralmente a 5C.6.';
            }
        } elseif ($action === 'certify_5c6_delivery') {
            $cert56 = ControlledExternalDelivery::certifyDelivery(absint($_POST['connector_5c6_destination_id'] ?? 0));
            set_transient('fieldflow_5c6_certification_' . get_current_user_id(), $cert56, 15 * MINUTE_IN_SECONDS);
            $result = !empty($cert56['ok']);
            $message = $result ? 'Controlled Delivery 5C.6 certificado: pedido real ao Broker, receiver fixo, idempotência e receipt HMAC validados.' : 'A certificação Controlled Delivery 5C.6 encontrou um ponto a rever: ' . sanitize_text_field((string)($cert56['message'] ?? $cert56['reason'] ?? 'certification_failed'));
        } elseif ($action === 'certify_5c6_resilience') {
            $cert56 = ControlledExternalDelivery::certifyRetryAndDeadLetter(absint($_POST['connector_5c6_destination_id'] ?? 0));
            set_transient('fieldflow_5c6_resilience_' . get_current_user_id(), $cert56, 15 * MINUTE_IN_SECONDS);
            $result = !empty($cert56['ok']);
            $message = $result ? 'Resiliência 5C.6 certificada: retry/backoff e dead-letter validados contra o receiver controlado.' : 'A certificação de resiliência 5C.6 encontrou um ponto a rever: ' . sanitize_text_field((string)($cert56['message'] ?? $cert56['reason'] ?? 'certification_failed'));
        } elseif ($action === 'certify_5c6_circuit') {
            $cert56 = ControlledExternalDelivery::certifyCircuitBreaker(absint($_POST['connector_5c6_destination_id'] ?? 0));
            set_transient('fieldflow_5c6_circuit_' . get_current_user_id(), $cert56, 15 * MINUTE_IN_SECONDS);
            $result = !empty($cert56['ok']);
            $message = $result ? 'Circuit breaker 5C.6 certificado: abriu no threshold configurado e bloqueou a tentativa seguinte sem nova chamada de rede.' : 'A certificação do circuit breaker 5C.6 encontrou um ponto a rever: ' . sanitize_text_field((string)($cert56['message'] ?? $cert56['reason'] ?? 'certification_failed'));
        } elseif ($action === 'probe_5c7_local') {
            $probe57 = SafeLivePilot::localProbe();
            set_transient('fieldflow_5c7_local_probe_' . get_current_user_id(), $probe57, 15 * MINUTE_IN_SECONDS);
            $result = !empty($probe57['ok']);
            $message = $result ? 'Diagnóstico local 5C.7 OK. O piloto está isolado, o dispatcher operacional continua bloqueado e só a projeção segura pode sair.' : 'O diagnóstico local 5C.7 encontrou um ponto a rever.';
        } elseif ($action === 'probe_5c7_broker') {
            $probe57 = SafeLivePilot::probeBroker();
            if (is_wp_error($probe57)) {
                $result = $probe57;
                $message = $probe57->get_error_message();
                set_transient('fieldflow_5c7_broker_probe_' . get_current_user_id(), ['ok'=>false,'error'=>$message], 15 * MINUTE_IN_SECONDS);
            } else {
                set_transient('fieldflow_5c7_broker_probe_' . get_current_user_id(), $probe57, 15 * MINUTE_IN_SECONDS);
                $result = !empty($probe57['ok']);
                $message = $result ? 'Broker 1.6.0 validado para Safe Live Pilot 5C.7, target dinâmico guardado, tokenizado, one-shot e safe projection only.' : 'O Broker respondeu, mas ainda não expõe integralmente a 5C.7.';
            }
        } elseif ($action === 'create_5c7_twt_pilot_destination') {
            $created57 = SafeLivePilot::ensureTwtPilotDestination();
            $result = $created57;
            $message = is_wp_error($created57) ? $created57->get_error_message() : 'Receiver TWT Safe Live Pilot preparado. Não tem regra de routing operacional e serve apenas para certificar a 5C.7.';
        } elseif ($action === 'prepare_5c7_target') {
            $pilot57 = SafeLivePilot::prepareTarget(absint($_POST['connector_5c7_destination_id'] ?? 0));
            set_transient('fieldflow_5c7_prepare_' . get_current_user_id(), $pilot57, 20 * MINUTE_IN_SECONDS);
            $result = !empty($pilot57['ok']);
            $message = $result ? 'Alvo 5C.7 preparado e vinculado a destination UUID + endpoint SHA-256 com token de curta duração.' : 'Não foi possível preparar o alvo 5C.7: ' . sanitize_text_field((string)($pilot57['message'] ?? $pilot57['reason'] ?? 'pilot_prepare_failed'));
        } elseif ($action === 'certify_5c7_synthetic') {
            $pilot57 = SafeLivePilot::certifySynthetic(absint($_POST['connector_5c7_destination_id'] ?? 0));
            set_transient('fieldflow_5c7_synthetic_' . get_current_user_id(), $pilot57, 20 * MINUTE_IN_SECONDS);
            $result = !empty($pilot57['ok']);
            $message = $result ? 'Target 5C.7 certificado com payload sintético, endpoint fingerprint e receipt HMAC.' : 'A certificação sintética 5C.7 encontrou um ponto a rever: ' . sanitize_text_field((string)($pilot57['message'] ?? $pilot57['reason'] ?? 'pilot_synthetic_failed'));
        } elseif ($action === 'arm_5c7_one_shot') {
            $pilot57 = SafeLivePilot::armOneShot(absint($_POST['connector_5c7_destination_id'] ?? 0), (string)($_POST['connector_5c7_event_pattern'] ?? 'submission.created'));
            set_transient('fieldflow_5c7_arm_' . get_current_user_id(), $pilot57, 20 * MINUTE_IN_SECONDS);
            $result = !empty($pilot57['ok']);
            $message = $result ? 'One-shot 5C.7 armado por 15 minutos. Apenas o próximo evento compatível envia metadados seguros, uma única vez.' : 'Não foi possível armar o one-shot 5C.7: ' . sanitize_text_field((string)($pilot57['message'] ?? $pilot57['reason'] ?? 'pilot_arm_failed'));
        } elseif ($action === 'certify_5c7_one_shot') {
            $pilot57 = SafeLivePilot::certifyOneShot(absint($_POST['connector_5c7_destination_id'] ?? 0));
            set_transient('fieldflow_5c7_one_shot_' . get_current_user_id(), $pilot57, 20 * MINUTE_IN_SECONDS);
            $result = !empty($pilot57['ok']);
            $message = $result ? 'Safe Live Pilot 5C.7 one-shot certificado: listener operacional, projeção segura, consumo único e receipt HMAC validados.' : 'O one-shot 5C.7 encontrou um ponto a rever: ' . sanitize_text_field((string)($pilot57['message'] ?? $pilot57['reason'] ?? 'pilot_one_shot_failed'));
        } elseif ($action === 'cancel_5c7_pilot') {
            $pilot57 = SafeLivePilot::cancelPilot(absint($_POST['connector_5c7_destination_id'] ?? 0));
            set_transient('fieldflow_5c7_cancel_' . get_current_user_id(), $pilot57, 10 * MINUTE_IN_SECONDS);
            $result = !empty($pilot57['ok']);
            $message = $result ? 'Piloto 5C.7 cancelado. Nenhum one-shot fica armado.' : 'Não foi possível cancelar o piloto 5C.7.';
        } elseif ($action === 'probe_5c8_local') {
            $probe58 = ProductionDeliveryGate::localProbe();
            set_transient('fieldflow_5c8_local_probe_' . get_current_user_id(), $probe58, 20 * MINUTE_IN_SECONDS);
            $result = !empty($probe58['ok']);
            $message = $result ? 'Diagnóstico local 5C.8 OK. A gate de produção está isolada, safe projection only e ativação geral continua bloqueada.' : 'O diagnóstico local 5C.8 encontrou um ponto a rever.';
        } elseif ($action === 'probe_5c8_broker') {
            $probe58 = ProductionDeliveryGate::probeBroker();
            if (is_wp_error($probe58)) {
                $result = $probe58;
                $message = $probe58->get_error_message();
                set_transient('fieldflow_5c8_broker_probe_' . get_current_user_id(), ['ok'=>false,'error'=>$message], 20 * MINUTE_IN_SECONDS);
            } else {
                set_transient('fieldflow_5c8_broker_probe_' . get_current_user_id(), $probe58, 20 * MINUTE_IN_SECONDS);
                $result = !empty($probe58['ok']);
                $message = $result ? 'Broker 1.7.0 validado para Production Delivery Gate 5C.8, target guardado, policy-bound e full payload bloqueado.' : 'O Broker respondeu, mas ainda não expõe integralmente a 5C.8.';
            }
        } elseif ($action === 'create_5c8_twt_destination') {
            $created58 = ProductionDeliveryGate::ensureTwtProductionDestination();
            $result = $created58;
            $message = is_wp_error($created58) ? $created58->get_error_message() : 'Receiver TWT Production Gate preparado. Não tem routing operacional e serve apenas para certificar a 5C.8.';
        } elseif ($action === 'prepare_5c8_gate') {
            $gate58 = ProductionDeliveryGate::prepareGate(
                absint($_POST['connector_5c8_destination_id'] ?? 0),
                (string)($_POST['connector_5c8_event_pattern'] ?? 'submission.created'),
                absint($_POST['connector_5c8_max_per_hour'] ?? 10)
            );
            set_transient('fieldflow_5c8_prepare_' . get_current_user_id(), $gate58, 30 * MINUTE_IN_SECONDS);
            $result = !empty($gate58['ok']);
            $message = $result ? 'Gate 5C.8 preparada com endpoint fingerprint, policy hash e token de curta duração.' : 'Não foi possível preparar a gate 5C.8: ' . sanitize_text_field((string)($gate58['message'] ?? $gate58['reason'] ?? 'production_gate_prepare_failed'));
        } elseif ($action === 'certify_5c8_target') {
            $gate58 = ProductionDeliveryGate::certifyTarget(absint($_POST['connector_5c8_destination_id'] ?? 0));
            set_transient('fieldflow_5c8_target_' . get_current_user_id(), $gate58, 30 * MINUTE_IN_SECONDS);
            $result = !empty($gate58['ok']);
            $message = $result ? 'Target 5C.8 certificado com safe projection, policy binding e receipt HMAC.' : 'A certificação do target 5C.8 encontrou um ponto a rever: ' . sanitize_text_field((string)($gate58['message'] ?? $gate58['reason'] ?? 'production_target_failed'));
        } elseif ($action === 'certify_5c8_queue') {
            $gate58 = ProductionDeliveryGate::certifyQueuedDispatcher(absint($_POST['connector_5c8_destination_id'] ?? 0));
            set_transient('fieldflow_5c8_queue_' . get_current_user_id(), $gate58, 30 * MINUTE_IN_SECONDS);
            $result = !empty($gate58['ok']);
            $message = $result ? 'Production Delivery Gate 5C.8 certificada: queue, idempotência, Broker, target e receipt HMAC validados. Na Stable 2.2.376 a ativação operacional continua desligada por defeito.' : 'A certificação da queue 5C.8 encontrou um ponto a rever: ' . sanitize_text_field((string)($gate58['message'] ?? $gate58['reason'] ?? 'production_queue_failed'));
        } elseif ($action === 'reset_5c8_gate') {
            $gate58 = ProductionDeliveryGate::resetGate(absint($_POST['connector_5c8_destination_id'] ?? 0));
            set_transient('fieldflow_5c8_reset_' . get_current_user_id(), $gate58, 10 * MINUTE_IN_SECONDS);
            $result = !empty($gate58['ok']);
            $message = $result ? 'Estado local da gate 5C.8 reiniciado. Nenhuma ativação de produção foi aberta.' : 'Não foi possível reiniciar a gate 5C.8.';
        } elseif ($action === 'probe_5d_local') {
            $probe5d = CredentialLifecycle::localProbe();
            set_transient('fieldflow_5d_local_probe_' . get_current_user_id(), $probe5d, 30 * MINUTE_IN_SECONDS);
            $result = !empty($probe5d['ok']);
            $message = $result ? 'Diagnóstico local 5D OK. Key Vault-only, guards de rotação/expiração/revogação ativos e produção geral continua bloqueada.' : 'O diagnóstico local 5D encontrou um ponto a rever.';
        } elseif ($action === 'probe_5d_broker') {
            $probe5d = CredentialLifecycle::probeBroker();
            if (is_wp_error($probe5d)) {
                $result = $probe5d;
                $message = $probe5d->get_error_message();
                set_transient('fieldflow_5d_broker_probe_' . get_current_user_id(), ['ok'=>false,'error'=>$message], 30 * MINUTE_IN_SECONDS);
            } else {
                set_transient('fieldflow_5d_broker_probe_' . get_current_user_id(), $probe5d, 30 * MINUTE_IN_SECONDS);
                $result = !empty($probe5d['ok']);
                $message = $result ? 'Broker 1.8.0 validado para Credential Lifecycle 5D. Key Vault, metadata probe, rotação, expiração e receipts assinados estão READY.' : 'O Broker respondeu, mas a Foundation 5D ainda não está integralmente READY.';
            }
        } elseif ($action === 'create_5d_twt_destination') {
            $created5d = CredentialLifecycle::ensureTwtCredentialDestination();
            $result = $created5d;
            $message = is_wp_error($created5d) ? $created5d->get_error_message() : 'Destino TWT Credential Lifecycle preparado com referência Key Vault, sem regra de routing operacional.';
        } elseif ($action === 'save_5d_credential') {
            $destinationId5d = absint($_POST['connector_5d_destination_id'] ?? 0);
            $destination5d = DestinationRegistry::getDestination($destinationId5d);
            $currentRef5d = trim((string)($destination5d['secret_ref'] ?? ''));
            $secretRef5d = trim((string)($_POST['connector_5d_secret_ref'] ?? ''));
            if ($secretRef5d === '') $secretRef5d = $currentRef5d;
            $result = CredentialLifecycle::configureDestination(
                $destinationId5d,
                $secretRef5d,
                (string)($_POST['connector_5d_auth_mode'] ?? 'api_key_header'),
                (string)($_POST['connector_5d_header_name'] ?? 'X-API-Key'),
                absint($_POST['connector_5d_warning_days'] ?? 30)
            );
            $message = is_wp_error($result) ? $result->get_error_message() : 'Perfil 5D guardado. O WordPress reteve apenas a referência Key Vault e metadados não sensíveis.';
        } elseif ($action === 'probe_5d_credential') {
            $cred5d = CredentialLifecycle::probeCredential(absint($_POST['connector_5d_destination_id'] ?? 0));
            set_transient('fieldflow_5d_credential_probe_' . get_current_user_id(), $cred5d, 30 * MINUTE_IN_SECONDS);
            $result = !empty($cred5d['ok']);
            $message = $result ? 'Credential probe 5D OK. O Broker resolveu o segredo no Key Vault e devolveu apenas versão/validade/estado.' : 'O credential probe 5D encontrou um ponto a rever: ' . sanitize_text_field((string)($cred5d['message'] ?? $cred5d['reason'] ?? 'credential_probe_failed'));
        } elseif ($action === 'certify_5d_credential') {
            $cred5d = CredentialLifecycle::certifyCredential(absint($_POST['connector_5d_destination_id'] ?? 0));
            set_transient('fieldflow_5d_credential_certification_' . get_current_user_id(), $cred5d, 30 * MINUTE_IN_SECONDS);
            $result = !empty($cred5d['ok']);
            $message = $result ? 'Credential Lifecycle 5D certificado: resolução Key Vault, injeção no sink fixo, receipt HMAC, versão e guards de rotação/expiração/revogação validados.' : 'A certificação 5D encontrou um ponto a rever: ' . sanitize_text_field((string)($cred5d['message'] ?? $cred5d['reason'] ?? 'credential_certification_failed'));
        } elseif ($action === 'revoke_5d_credential') {
            $result = CredentialLifecycle::revoke(absint($_POST['connector_5d_destination_id'] ?? 0));
            $message = is_wp_error($result) ? $result->get_error_message() : 'Credencial revogada localmente. A readiness fica imediatamente bloqueada sem apagar o segredo no Key Vault.';
        } elseif ($action === 'reset_5d_credential') {
            $result = CredentialLifecycle::reset(absint($_POST['connector_5d_destination_id'] ?? 0));
            $message = is_wp_error($result) ? $result->get_error_message() : 'Estado 5D reiniciado. A referência Key Vault do destino não foi apagada.';
        } elseif ($action === 'probe_5e_local') {
            $probe5e = GovernanceRecovery::localProbe();
            set_transient('fieldflow_5e_local_probe_' . get_current_user_id(), $probe5e, 30 * MINUTE_IN_SECONDS);
            $result = !empty($probe5e['ok']);
            $message = $result ? 'Diagnóstico local 5E OK. Ledger, kill controls, replay e recovery estão disponíveis; produção continua desligada.' : 'O diagnóstico local 5E encontrou um ponto a rever.';
        } elseif ($action === 'probe_5e_broker') {
            $probe5e = GovernanceRecovery::probeBroker();
            if (is_wp_error($probe5e)) {
                $result = $probe5e;
                $message = $probe5e->get_error_message();
                set_transient('fieldflow_5e_broker_probe_' . get_current_user_id(), ['ok'=>false,'error'=>$message], 30 * MINUTE_IN_SECONDS);
            } else {
                set_transient('fieldflow_5e_broker_probe_' . get_current_user_id(), $probe5e, 30 * MINUTE_IN_SECONDS);
                $result = !empty($probe5e['ok']);
                $message = $result ? 'Broker 1.8.0 preservado e validado para 5E. Não é necessário novo deployment Azure nesta fase.' : 'O Broker respondeu, mas a pré-condição 5D ainda não está integralmente READY.';
            }
        } elseif ($action === 'save_5e_controls') {
            $control5e = GovernanceRecovery::saveSettings([
                'blocked_client_ids'=>$_POST['governance_5e_blocked_client_ids'] ?? [],
                'blocked_destination_uuids'=>$_POST['governance_5e_blocked_destination_uuids'] ?? [],
                'blocked_event_patterns'=>$_POST['governance_5e_blocked_event_patterns'] ?? '',
                'stale_lock_seconds'=>$_POST['governance_5e_stale_lock_seconds'] ?? GovernanceRecovery::DEFAULT_STALE_LOCK_SECONDS,
            ]);
            set_transient('fieldflow_5e_controls_' . get_current_user_id(), $control5e, 20 * MINUTE_IN_SECONDS);
            $result = !empty($control5e['ok']);
            $message = $result ? 'Controlos 5E guardados. O kill switch global permanece ON e bloqueado nesta release.' : 'Não foi possível guardar os controlos 5E.';
        } elseif ($action === 'certify_5e_ledger') {
            $cert5e = GovernanceRecovery::certifyLedgerAndKillControls();
            set_transient('fieldflow_5e_ledger_cert_' . get_current_user_id(), $cert5e, 30 * MINUTE_IN_SECONDS);
            $result = !empty($cert5e['ok']);
            $message = $result ? 'Ledger 5E e kill controls certificados. Cadeia hash append-only validada.' : 'A certificação do ledger/kill controls 5E encontrou um ponto a rever.';
        } elseif ($action === 'certify_5e_replay') {
            $cert5e = GovernanceRecovery::certifyDeadLetterReplay();
            set_transient('fieldflow_5e_replay_cert_' . get_current_user_id(), $cert5e, 30 * MINUTE_IN_SECONDS);
            $result = !empty($cert5e['ok']);
            $message = $result ? 'Dead Letter + Replay 5E certificados com autorização, justificação, ticket de curta duração e idempotência.' : 'A certificação Dead Letter + Replay 5E encontrou um ponto a rever.';
        } elseif ($action === 'certify_5e_recovery') {
            $cert5e = GovernanceRecovery::certifyRecovery();
            set_transient('fieldflow_5e_recovery_cert_' . get_current_user_id(), $cert5e, 30 * MINUTE_IN_SECONDS);
            $result = !empty($cert5e['ok']);
            $message = $result ? 'Recovery 5E certificado: snapshot determinístico, stale-lock recovery, idempotência e reset de circuit breaker validados.' : 'A certificação de recovery 5E encontrou um ponto a rever.';
        } elseif ($action === 'snapshot_5e') {
            $snapshot5e = GovernanceRecovery::createRecoverySnapshot();
            set_transient('fieldflow_5e_snapshot_' . get_current_user_id(), $snapshot5e, 30 * MINUTE_IN_SECONDS);
            $result = !empty($snapshot5e['ok']);
            $message = $result ? 'Snapshot 5E criado sem valores secretos. SHA-256: ' . substr((string)($snapshot5e['sha256'] ?? ''), 0, 16) . '…' : 'Não foi possível criar o snapshot 5E.';
        } elseif ($action === 'recover_5e_stale') {
            $recovery5e = GovernanceRecovery::recoverStaleLocks(false);
            set_transient('fieldflow_5e_manual_recovery_' . get_current_user_id(), $recovery5e, 20 * MINUTE_IN_SECONDS);
            $result = !empty($recovery5e['ok']);
            $message = $result ? 'Recovery 5E executado. Locks recuperados: ' . (int)($recovery5e['recovered'] ?? 0) . ', movidos para dead: ' . (int)($recovery5e['dead'] ?? 0) . '.' : 'Não foi possível executar o recovery de stale locks.';
        } elseif ($action === 'replay_5e_dead_job') {
            $jobId5e = absint($_POST['governance_5e_job_id'] ?? 0);
            $reason5e = sanitize_text_field((string)($_POST['governance_5e_replay_reason'] ?? ''));
            $auth5e = GovernanceRecovery::authoriseReplay($jobId5e, $reason5e);
            $replay5e = !empty($auth5e['ok']) ? GovernanceRecovery::executeReplay((string)$auth5e['ticket_uuid']) : $auth5e;
            set_transient('fieldflow_5e_manual_replay_' . get_current_user_id(), ['authorisation'=>$auth5e,'execution'=>$replay5e], 20 * MINUTE_IN_SECONDS);
            $result = !empty($replay5e['ok']);
            $message = $result ? 'Replay 5E autorizado e executado. O job regressou a pending; a rede operacional continua bloqueada nesta release.' : 'Replay 5E não executado: ' . sanitize_text_field((string)($replay5e['message'] ?? $replay5e['reason'] ?? 'replay_failed'));
        } elseif ($action === 'certify_5e_final') {
            $cert5e = GovernanceRecovery::certifyFinalGate();
            set_transient('fieldflow_5e_final_cert_' . get_current_user_id(), $cert5e, 30 * MINUTE_IN_SECONDS);
            $result = !empty($cert5e['ok']);
            $message = $result ? 'Fase 5E certificada: PRODUCTION ELIGIBLE. A Stable 2.2.376 mantém a produção explicitamente DESLIGADA por defeito.' : 'A gate final 5E ainda tem checks por fechar.';
        } elseif ($action === 'reset_5e_certification') {
            $reset5e = GovernanceRecovery::resetCertification();
            set_transient('fieldflow_5e_reset_' . get_current_user_id(), $reset5e, 10 * MINUTE_IN_SECONDS);
            $result = !empty($reset5e['ok']);
            $message = 'Certificação 5E reiniciada. O governance ledger não foi apagado e a produção continua desligada.';
        } elseif ($action === 'probe_phase5_stable') {
            $stableProbe = Phase5StableRelease::localProbe();
            set_transient('fieldflow_phase5_stable_probe_' . get_current_user_id(), $stableProbe, 30 * MINUTE_IN_SECONDS);
            $result = !empty($stableProbe['ok']);
            $message = $result ? 'Diagnóstico de fecho da Fase 5 concluído. A baseline está pronta para certificação stable.' : 'O diagnóstico de fecho da Fase 5 encontrou um ponto a rever.';
        } elseif ($action === 'certify_phase5_stable') {
            $stableCert = Phase5StableRelease::certifyStable(get_current_user_id());
            set_transient('fieldflow_phase5_stable_cert_' . get_current_user_id(), $stableCert, 60 * MINUTE_IN_SECONDS);
            $result = !empty($stableCert['ok']);
            $message = $result ? 'Fase 5 Stable certificada na 2.2.376. Production Eligible: SIM. Production Enabled: NÃO.' : 'A gate Stable da Fase 5 ainda tem checks por fechar.';
        } elseif ($action === 'reset_phase5_stable') {
            $stableReset = Phase5StableRelease::reset();
            set_transient('fieldflow_phase5_stable_reset_' . get_current_user_id(), $stableReset, 10 * MINUTE_IN_SECONDS);
            $result = !empty($stableReset['ok']);
            $message = 'Gate Stable da Fase 5 reiniciada. O snapshot e o governance ledger foram preservados.';
        } elseif ($action === 'download_phase5_baseline') {
            $payload = Phase5StableRelease::exportPayload();
            GovernanceRecovery::recordAdminAction($action, true, $_POST);
            nocache_headers();
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="fieldflow-phase5-stable-baseline-2.2.376.json"');
            echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if (class_exists('\RoutesPro\Cloud\GovernanceRecovery')) {
            GovernanceRecovery::recordAdminAction($action, $result, $_POST);
        }

        $status = is_wp_error($result) || $result === false ? 'error' : 'success';
        $url = add_query_arg([
            'page'=>self::SLUG,
            'ff_notice'=>$status,
            'ff_message'=>rawurlencode($message),
        ], admin_url('admin.php'));
        if (in_array($action, ['save_5c2_destination_identity','start_5c2_onboarding','verify_5c2_onboarding','reset_5c2_onboarding'], true)) {
            $url .= '#ff-entra-onboarding';
        } elseif (in_array($action, ['create_5c3_home_powerbi_destination','probe_5c3_local','probe_5c3_broker','save_5c3_resource','probe_5c3_resource','reset_5c3_resource'], true)) {
            $url .= '#ff-resource-auth';
        } elseif (in_array($action, ['probe_phase5_stable','certify_phase5_stable','reset_phase5_stable'], true)) {
            $url .= '#ff-phase5-stable';
        } elseif (in_array($action, ['probe_5e_local','probe_5e_broker','save_5e_controls','certify_5e_ledger','certify_5e_replay','certify_5e_recovery','snapshot_5e','recover_5e_stale','replay_5e_dead_job','certify_5e_final','reset_5e_certification'], true)) {
            $url .= '#ff-governance-recovery';
        } elseif (in_array($action, ['probe_5d_local','probe_5d_broker','create_5d_twt_destination','save_5d_credential','probe_5d_credential','certify_5d_credential','revoke_5d_credential','reset_5d_credential'], true)) {
            $url .= '#ff-credential-lifecycle';
        } elseif (in_array($action, ['probe_5c8_local','probe_5c8_broker','create_5c8_twt_destination','prepare_5c8_gate','certify_5c8_target','certify_5c8_queue','reset_5c8_gate'], true)) {
            $url .= '#ff-production-gate';
        } elseif (in_array($action, ['probe_5c7_local','probe_5c7_broker','create_5c7_twt_pilot_destination','prepare_5c7_target','certify_5c7_synthetic','arm_5c7_one_shot','certify_5c7_one_shot','cancel_5c7_pilot'], true)) {
            $url .= '#ff-safe-live-pilot';
        } elseif (in_array($action, ['probe_5c6_local','probe_5c6_broker','certify_5c6_delivery','certify_5c6_resilience','certify_5c6_circuit'], true)) {
            $url .= '#ff-controlled-delivery';
        } elseif (in_array($action, ['save_5c5_settings','probe_5c5_local','certify_5c5_shadow','certify_5c5_queue','dispatch_5c5','retry_5c5'], true)) {
            $url .= '#ff-connector-delivery';
        }
        wp_safe_redirect($url);
        exit;
    }

    public static function render(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        DestinationRegistry::ensureFoundation();
        DestinationRegistry::ensurePrimaryTwtDestination();
        $health = MultiDestinationFoundation::health();
        $destinations = DestinationRegistry::listDestinations();
        $rules = DestinationRegistry::listRules();
        $types = DestinationTypes::definitions();
        [$clients, $projects] = self::scopeOptions();
        $probe = get_transient('fieldflow_5a_probe_' . get_current_user_id());
        $preview = get_transient('fieldflow_5a_route_preview_' . get_current_user_id());
        $deliveryHealth = ClientAzureDelivery::health();
        $relayStatus = ClientAzureRelayConnection::status();
        $deliveryProbe = get_transient('fieldflow_5b_local_probe_' . get_current_user_id());
        $dispatchSummary = get_transient('fieldflow_5b_dispatch_' . get_current_user_id());
        $destinationProbe = get_transient('fieldflow_5b_destination_probe_' . get_current_user_id());
        $recentDeliveries = ClientAzureDelivery::recent(20);
        $intakeRecent = ClientAzureDelivery::intakeRecent(15);
        $connectorHealth = ConnectorFoundation::health();
        $connectorProbe = get_transient('fieldflow_5c_local_probe_' . get_current_user_id());
        $connectorDestinationProbe = get_transient('fieldflow_5c_destination_probe_' . get_current_user_id());
        $entraHealth = EntraCrossTenantFoundation::status();
        $entraSettings = EntraCrossTenantFoundation::settings();
        $entraProbe = get_transient('fieldflow_5c2_local_probe_' . get_current_user_id());
        $entraCloudProbe = get_transient('fieldflow_5c2_cloud_probe_' . get_current_user_id());
        $entraDestinationProbe = get_transient('fieldflow_5c2_destination_probe_' . get_current_user_id());
        $entraOnboarding = EntraTenantOnboarding::summary($destinations);
        $entraOnboardingLink = get_transient('fieldflow_5c2_onboarding_link_' . get_current_user_id());
        $editEntraIdentityId = absint($_GET['ff_edit_5c2_identity'] ?? 0);
        $resourceAuth = ResourceAuthorizationFoundation::summary($destinations);
        $resourceAuthProbe = get_transient('fieldflow_5c3_local_probe_' . get_current_user_id());
        $resourceBrokerProbe = get_transient('fieldflow_5c3_broker_probe_' . get_current_user_id());
        $resourceProbe = get_transient('fieldflow_5c3_resource_probe_' . get_current_user_id());
        $editResourceAuthId = absint($_GET['ff_edit_5c3_resource'] ?? 0);
        $connectorDeliveryHealth = ConnectorDelivery::health();
        $connectorDeliverySettings = ConnectorDelivery::settings();
        $connectorDeliveryProbe = get_transient('fieldflow_5c5_local_probe_' . get_current_user_id());
        $connectorDeliveryDispatch = get_transient('fieldflow_5c5_dispatch_' . get_current_user_id());
        $connectorDeliveryCertification = get_transient('fieldflow_5c5_certification_' . get_current_user_id());
        $connectorDeliveryEligibleDestinations = array_values(array_filter($destinations, static function($destination){
            return !empty($destination['is_active']) && ConnectorDelivery::isSupportedType((string)($destination['type_key'] ?? ''));
        }));
        $connectorDeliveryRecent = ConnectorDelivery::recent(20);
        $controlledDeliveryHealth = ControlledExternalDelivery::health();
        $controlledDeliveryProbe = get_transient('fieldflow_5c6_local_probe_' . get_current_user_id());
        $controlledBrokerProbe = get_transient('fieldflow_5c6_broker_probe_' . get_current_user_id());
        $controlledDeliveryCertification = get_transient('fieldflow_5c6_certification_' . get_current_user_id());
        $controlledResilienceCertification = get_transient('fieldflow_5c6_resilience_' . get_current_user_id());
        $controlledCircuitCertification = get_transient('fieldflow_5c6_circuit_' . get_current_user_id());
        $safePilotHealth = SafeLivePilot::health();
        $safePilotProbe = get_transient('fieldflow_5c7_local_probe_' . get_current_user_id());
        $safePilotBrokerProbe = get_transient('fieldflow_5c7_broker_probe_' . get_current_user_id());
        $safePilotPrepare = get_transient('fieldflow_5c7_prepare_' . get_current_user_id());
        $safePilotSynthetic = get_transient('fieldflow_5c7_synthetic_' . get_current_user_id());
        $safePilotArm = get_transient('fieldflow_5c7_arm_' . get_current_user_id());
        $safePilotOneShot = get_transient('fieldflow_5c7_one_shot_' . get_current_user_id());
        $safePilotSummary = SafeLivePilot::summary();
        $safePilotDestinations = SafeLivePilot::eligibleDestinations();
        $productionGateHealth = ProductionDeliveryGate::health();
        $productionGateProbe = get_transient('fieldflow_5c8_local_probe_' . get_current_user_id());
        $productionGateBrokerProbe = get_transient('fieldflow_5c8_broker_probe_' . get_current_user_id());
        $productionGatePrepare = get_transient('fieldflow_5c8_prepare_' . get_current_user_id());
        $productionGateTarget = get_transient('fieldflow_5c8_target_' . get_current_user_id());
        $productionGateQueue = get_transient('fieldflow_5c8_queue_' . get_current_user_id());
        $productionGateSummary = ProductionDeliveryGate::summary();
        $productionGateDestinations = ProductionDeliveryGate::eligibleDestinations();
        $credentialLifecycleHealth = CredentialLifecycle::health();
        $credentialLifecycleProbe = get_transient('fieldflow_5d_local_probe_' . get_current_user_id());
        $credentialLifecycleBrokerProbe = get_transient('fieldflow_5d_broker_probe_' . get_current_user_id());
        $credentialLifecycleCredentialProbe = get_transient('fieldflow_5d_credential_probe_' . get_current_user_id());
        $credentialLifecycleCertification = get_transient('fieldflow_5d_credential_certification_' . get_current_user_id());
        $credentialLifecycleSummary = CredentialLifecycle::summary();
        $credentialLifecycleDestinations = CredentialLifecycle::eligibleDestinations(true);
        $credentialLifecycleDefaultId = CredentialLifecycle::defaultDestinationId();
        $governanceHealth = GovernanceRecovery::health();
        $governanceSettings = GovernanceRecovery::settings();
        $governanceSummary = GovernanceRecovery::summary();
        $governanceProbe = get_transient('fieldflow_5e_local_probe_' . get_current_user_id());
        $governanceBrokerProbe = get_transient('fieldflow_5e_broker_probe_' . get_current_user_id());
        $governanceLedgerCert = get_transient('fieldflow_5e_ledger_cert_' . get_current_user_id());
        $governanceReplayCert = get_transient('fieldflow_5e_replay_cert_' . get_current_user_id());
        $governanceRecoveryCert = get_transient('fieldflow_5e_recovery_cert_' . get_current_user_id());
        $governanceFinalCert = get_transient('fieldflow_5e_final_cert_' . get_current_user_id());
        $governanceManualReplay = get_transient('fieldflow_5e_manual_replay_' . get_current_user_id());
        $governanceManualRecovery = get_transient('fieldflow_5e_manual_recovery_' . get_current_user_id());
        $governanceSnapshot = GovernanceRecovery::recoverySnapshotState();
        $governanceDeadJobs = GovernanceRecovery::deadJobs(12);
        $governanceLedgerRows = GovernanceRecovery::recentLedger(20);
        $phase5StableHealth = Phase5StableRelease::health();
        $phase5StableMatrix = Phase5StableRelease::matrix();
        $phase5StableProbe = get_transient('fieldflow_phase5_stable_probe_' . get_current_user_id());
        $phase5StableCert = get_transient('fieldflow_phase5_stable_cert_' . get_current_user_id());
        $phase5StableSnapshot = Phase5StableRelease::snapshot();
        $notice = sanitize_key((string)($_GET['ff_notice'] ?? ''));
        $message = isset($_GET['ff_message']) ? rawurldecode((string)$_GET['ff_message']) : '';

        AdminPage::open('Destinos de Dados', 'Fase 5 Stable, routing multi-destination, identidade Entra, delivery externo, Credential Lifecycle, Governance & Recovery e baseline de release.');
        if ($message !== '') {
            echo '<div class="notice ' . ($notice === 'error' ? 'notice-error' : 'notice-success') . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
        ?>
        <style>
        .ff5-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-top:18px}.ff5-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px;box-shadow:0 1px 2px rgba(0,0,0,.03)}.ff5-card h2{margin-top:0}.ff5-badges{display:flex;flex-wrap:wrap;gap:8px}.ff5-badge{display:inline-flex;padding:5px 9px;border-radius:999px;background:#f0f0f1;font-size:12px}.ff5-ok{background:#edfaef}.ff5-warn{background:#fff8e5}.ff5-table-wrap{overflow:auto}.ff5-table{min-width:1050px}.ff5-actions{display:flex;gap:6px;flex-wrap:wrap}.ff5-mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;word-break:break-all}.ff5-note{padding:12px 14px;background:#f6f7f7;border-left:4px solid #2271b1;border-radius:4px}.ff5-danger{border-left-color:#d63638}.ff5-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.ff5-form-grid .full{grid-column:1/-1}.ff5-form-grid label{font-weight:600;display:block;margin-bottom:5px}.ff5-form-grid input,.ff5-form-grid select,.ff5-form-grid textarea{width:100%}@media(max-width:900px){.ff5-grid,.ff5-form-grid{grid-template-columns:1fr}.ff5-form-grid .full{grid-column:auto}}
        </style>
        <div class="ff5-grid">
          <div class="ff5-card">
            <h2>Estado da Foundation</h2>
            <div class="ff5-badges">
              <span class="ff5-badge <?php echo !empty($health['registry']['ready']) ? 'ff5-ok' : 'ff5-warn'; ?>">Registry: <?php echo !empty($health['registry']['ready']) ? 'READY' : 'ATTENTION'; ?></span>
              <span class="ff5-badge">Modo: <?php echo esc_html((string)($health['mode'] ?? 'shadow')); ?></span>
              <span class="ff5-badge">Destinos: <?php echo (int)($health['registry']['destinations'] ?? 0); ?></span>
              <span class="ff5-badge">Regras: <?php echo (int)($health['registry']['rules'] ?? 0); ?></span>
              <span class="ff5-badge <?php echo !empty($health['registry']['primary_twt']) ? 'ff5-ok' : 'ff5-warn'; ?>">Azure TWT principal: <?php echo !empty($health['registry']['primary_twt']) ? 'SIM' : 'NÃO'; ?></span>
            </div>
            <p><strong>Tenant:</strong> <code><?php echo esc_html(TenantIdentity::tenantId()); ?></code></p>
            <p class="ff5-note">O Azure TWT continua a usar <code>AzureSyncFoundation</code> e HMAC v1 sem alterações. A 5B mantém a fila separada para <code>client_azure</code>. A 5C.1 define os contratos, a 5C.2 trata identidade Microsoft Entra sem client secrets no WordPress, a 5C.3 valida acesso read-only e a 5C.5 abre apenas a <strong>intake/queue local</strong> para API Externa e Webhook. Na 2.2.376 o dispatcher operacional completo continua bloqueado por defeito; a 5C.6 mantém a certificação sintética, a 5C.7 prova um one-shot real seguro e a 5C.8 certifica a gate de produção com policy binding, queue e receipt HMAC. A 5D acrescenta lifecycle de credenciais Key Vault-only, rotação, expiração e revogação. A 5E fecha governance, recovery e elegibilidade. A 2.2.376 acrescenta o fecho Stable, baseline certificada e rollback conhecido, mantendo a ativação operacional desligada por defeito.</p>
            <form method="post" style="margin-top:12px">
              <?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?>
              <input type="hidden" name="ff_destination_action" value="probe">
              <button class="button button-primary">Executar diagnóstico local 5A</button>
            </form>
          </div>
          <div class="ff5-card">
            <h2>Política de segredos</h2>
            <p><strong>Não introduzir passwords, client secrets, tokens ou keys neste ecrã.</strong></p>
            <p>O WordPress guarda apenas referências, por exemplo:</p>
            <div class="ff5-mono">https://kv-exemplo.vault.azure.net/secrets/fieldflow-client-destination</div>
            <div class="ff5-mono" style="margin-top:6px">kv://kv-exemplo/fieldflow-client-destination</div>
            <p class="ff5-note ff5-danger">O WordPress nunca resolve o segredo do cliente. Guarda apenas referências seguras. Na 5C.2/5C.3 o WordPress continua sem guardar client secrets, certificados ou access tokens. Os token/resource probes são executados apenas no Entra Broker TWT com workload identity federation. A Fase 5D gere rotação, expiração e revogação de referências Key Vault sem colocar valores secretos no WordPress. A Fase 5E acrescenta governance ledger, replay controlado, recovery e a decisão final PRODUCTION ELIGIBLE.</p>
          </div>
        </div>

        <div class="ff5-card" style="margin-top:18px">
          <h2>Fase 5B, Azure do Cliente</h2>
          <div class="ff5-badges">
            <span class="ff5-badge <?php echo !empty($deliveryHealth['table_ready']) ? 'ff5-ok' : 'ff5-warn'; ?>">Fila 5B: <?php echo !empty($deliveryHealth['table_ready']) ? 'READY' : 'ATTENTION'; ?></span>
            <span class="ff5-badge">Modo: <?php echo esc_html((string)$deliveryHealth['mode']); ?></span>
            <span class="ff5-badge <?php echo !empty($relayStatus['configured']) ? 'ff5-ok' : 'ff5-warn'; ?>">Relay TWT: <?php echo !empty($relayStatus['configured']) ? 'CONFIGURADO' : 'POR CONFIGURAR'; ?></span>
            <span class="ff5-badge">Pendentes: <?php echo (int)($deliveryHealth['stats']['pending'] ?? 0); ?></span>
            <span class="ff5-badge">Relayed 24h: <?php echo (int)($deliveryHealth['stats']['relayed_24h'] ?? 0); ?></span>
            <span class="ff5-badge <?php echo (int)($deliveryHealth['stats']['dead'] ?? 0) === 0 ? 'ff5-ok' : 'ff5-warn'; ?>">Dead: <?php echo (int)($deliveryHealth['stats']['dead'] ?? 0); ?></span>
          </div>
          <p class="ff5-note"><strong>Shadow</strong> apenas calcula routing. <strong>Queue</strong> cria a fila local sem enviar. <strong>Live</strong> envia a fila para o Relay Azure TWT, nunca diretamente para o Azure do cliente.</p>
          <form method="post" class="ff5-form-grid" style="margin-top:14px">
            <?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?>
            <input type="hidden" name="ff_destination_action" value="save_5b_settings">
            <div><label>Modo 5B</label><select name="relay_mode"><option value="shadow" <?php selected($deliveryHealth['mode'], 'shadow'); ?>>Shadow, sem fila</option><option value="queue" <?php selected($deliveryHealth['mode'], 'queue'); ?>>Queue, fila sem envio</option><option value="live" <?php selected($deliveryHealth['mode'], 'live'); ?>>Live, Relay TWT ativo</option></select></div>
            <div><label>Lote por dispatch</label><input type="number" min="1" max="50" name="relay_batch_size" value="<?php echo (int)$relayStatus['batch_size']; ?>"></div>
            <div class="full"><label>Endpoint do Relay Azure TWT, não é endpoint do cliente</label><input type="url" name="relay_endpoint" value="<?php echo esc_attr((string)$relayStatus['relay_endpoint']); ?>" placeholder="https://func-fieldflow-clientrelay-....azurewebsites.net/api/v1/client-azure-relay"></div>
            <div><label>Timeout</label><input type="number" min="5" max="45" name="relay_timeout" value="<?php echo (int)$relayStatus['timeout']; ?>"> segundos</div>
            <div class="full"><button class="button button-primary">Guardar configuração 5B</button></div>
          </form>
          <div class="ff5-actions" style="margin-top:12px">
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5b_local"><button class="button">Diagnóstico local 5B</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="dispatch_5b"><button class="button">Processar fila 5B agora</button></form>
            <?php if ((int)($deliveryHealth['stats']['dead'] ?? 0) > 0): ?><form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="retry_5b"><button class="button">Recuperar dead 5B</button></form><?php endif; ?>
          </div>
          <?php if (is_array($deliveryProbe)): ?><p><strong>Diagnóstico 5B:</strong> <?php echo !empty($deliveryProbe['ok']) ? 'OK' : 'REVER'; ?></p><div class="ff5-badges"><?php foreach ((array)($deliveryProbe['checks'] ?? []) as $key=>$ok): ?><span class="ff5-badge <?php echo $ok ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key . ': ' . ($ok ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endif; ?>
          <?php if (is_array($dispatchSummary)): ?><p><strong>Último dispatch:</strong> claimed <?php echo (int)($dispatchSummary['claimed'] ?? 0); ?>, relayed <?php echo (int)($dispatchSummary['relayed'] ?? 0); ?>, retry <?php echo (int)($dispatchSummary['retried'] ?? 0); ?>, dead <?php echo (int)($dispatchSummary['dead'] ?? 0); ?>.</p><?php endif; ?>
          <?php if (is_array($destinationProbe)): ?><p class="ff5-note <?php echo !empty($destinationProbe['ok']) ? '' : 'ff5-danger'; ?>"><strong>Último teste end-to-end 5B:</strong> <?php echo !empty($destinationProbe['ok']) ? 'OK' : 'FALHA'; ?><?php if (!empty($destinationProbe['http_code'])): ?>, HTTP <?php echo (int)$destinationProbe['http_code']; ?><?php endif; ?><?php if (!empty($destinationProbe['remote_status'])): ?>, estado <?php echo esc_html((string)$destinationProbe['remote_status']); ?><?php endif; ?><?php if (!empty($destinationProbe['error'])): ?>, <?php echo esc_html((string)$destinationProbe['error']); ?><?php endif; ?></p><?php endif; ?>
        </div>

        <div class="ff5-card" style="margin-top:18px">
          <h2>Fase 5C.1, Connector Foundation</h2>
          <div class="ff5-badges">
            <span class="ff5-badge <?php echo (string)($connectorHealth['status'] ?? '') === 'ready' ? 'ff5-ok' : 'ff5-warn'; ?>">Foundation: <?php echo strtoupper(esc_html((string)($connectorHealth['status'] ?? 'attention'))); ?></span>
            <span class="ff5-badge ff5-ok">Modo: SHADOW BLOQUEADO</span>
            <span class="ff5-badge">Contratos: <?php echo (int)($connectorHealth['contracts_ready'] ?? 0); ?>/<?php echo (int)($connectorHealth['contracts_total'] ?? 0); ?></span>
            <span class="ff5-badge">Destinos 5C ativos: <?php echo (int)($connectorHealth['active_destinations'] ?? 0); ?></span>
            <span class="ff5-badge ff5-ok">Rede externa: 0</span>
            <span class="ff5-badge ff5-ok">Secrets resolvidos no WP: 0</span>
          </div>
          <p class="ff5-note">A Foundation continua a separar contratos, identidade e delivery. Os destinos <code>external_api</code> e <code>webhook</code> já podem ser observados ou persistidos na fila dedicada 5C.5, mas <code>delivery_ready=false</code> para rede. Azure SQL e Power BI/Fabric mantêm apenas os probes read-only certificados.</p>
          <div class="ff5-table-wrap"><table class="widefat striped ff5-table"><thead><tr><th>Tipo</th><th>Contrato</th><th>Transporte</th><th>Auth por defeito</th><th>Entrega futura</th></tr></thead><tbody>
          <?php foreach ((array)($connectorHealth['contracts'] ?? []) as $typeKey=>$contract): ?>
            <tr><td><code><?php echo esc_html((string)$typeKey); ?></code></td><td><code><?php echo esc_html((string)($contract['contract'] ?? '')); ?></code></td><td><?php echo esc_html((string)($contract['transport'] ?? '')); ?></td><td><code><?php echo esc_html((string)($contract['default_auth'] ?? '')); ?></code></td><td><?php echo esc_html((string)($contract['future_phase'] ?? '')); ?></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
          <div class="ff5-actions" style="margin-top:12px">
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5c_local"><button class="button button-primary">Diagnóstico local 5C.1</button></form>
          </div>
          <?php if (is_array($connectorProbe)): ?><p><strong>Diagnóstico 5C.1:</strong> <?php echo !empty($connectorProbe['ok']) ? 'OK' : 'REVER'; ?></p><div class="ff5-badges"><?php foreach ((array)($connectorProbe['checks'] ?? []) as $key=>$ok): ?><span class="ff5-badge <?php echo $ok ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key . ': ' . ($ok ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endif; ?>
          <?php if (is_array($connectorDestinationProbe)): ?><p class="ff5-note <?php echo !empty($connectorDestinationProbe['ok']) ? '' : 'ff5-danger'; ?>"><strong>Última validação de destino 5C.1:</strong> <?php echo !empty($connectorDestinationProbe['ok']) ? 'OK' : 'REVER'; ?><?php if (!empty($connectorDestinationProbe['profile']['contract'])): ?>, contrato <code><?php echo esc_html((string)$connectorDestinationProbe['profile']['contract']); ?></code><?php endif; ?><?php if (!empty($connectorDestinationProbe['error'])): ?>, <?php echo esc_html((string)$connectorDestinationProbe['error']); ?><?php endif; ?></p><?php endif; ?>
        </div>

        <div class="ff5-card" style="margin-top:18px">
          <h2>Fase 5C.2, Microsoft Entra cross-tenant</h2>
          <div class="ff5-badges">
            <span class="ff5-badge ff5-ok">Identity Foundation: READY</span>
            <span class="ff5-badge ff5-ok">Modo: SHADOW</span>
            <span class="ff5-badge <?php echo !empty($entraHealth['configured']) ? 'ff5-ok' : 'ff5-warn'; ?>">Entra Broker: <?php echo !empty($entraHealth['configured']) ? 'CONFIGURADO' : 'POR CONFIGURAR'; ?></span>
            <span class="ff5-badge ff5-ok">Credencial: Workload Identity Federation</span>
            <span class="ff5-badge ff5-ok">Client secrets no WP: 0</span>
            <span class="ff5-badge">Destinos Entra: <?php echo (int)($entraHealth['entra_destinations_ready'] ?? 0); ?>/<?php echo (int)($entraHealth['entra_destinations'] ?? 0); ?> preparados</span>
          </div>
          <p class="ff5-note">A 5C.2 separa autenticação de delivery. O WordPress guarda apenas IDs, tenant e resource scope não sensíveis. A autenticação real é feita no Azure por uma aplicação Entra multi-tenant da TWT, usando uma <strong>User Assigned Managed Identity</strong> como credencial federada. Não existem client secrets nem certificados no WordPress.</p>
          <form method="post" class="ff5-form-grid" style="margin-top:14px">
            <?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?>
            <input type="hidden" name="ff_destination_action" value="save_5c2_settings">
            <div class="full"><label>Endpoint do Entra Broker TWT</label><input type="url" name="entra_broker_endpoint" value="<?php echo esc_attr((string)$entraSettings['broker_endpoint']); ?>" placeholder="https://func-fieldflow-entrabroker-prod-eb978f11.azurewebsites.net/api/v1/token-probe"><p class="description">Endpoint TWT dedicado ao token probe. Nunca é o endpoint do cliente.</p></div>
            <div><label>Application (client) ID do Broker</label><input type="text" name="entra_broker_client_id" value="<?php echo esc_attr((string)$entraSettings['broker_client_id']); ?>" placeholder="00000000-0000-0000-0000-000000000000"><p class="description">Identificador público, não é segredo.</p></div>
            <div><label>Directory (tenant) ID TWT</label><input type="text" name="entra_home_tenant_id" value="<?php echo esc_attr((string)$entraSettings['home_tenant_id']); ?>" placeholder="00000000-0000-0000-0000-000000000000"></div>
            <div><label>Timeout do probe</label><input type="number" min="5" max="45" name="entra_timeout" value="<?php echo (int)$entraSettings['timeout']; ?>"> segundos</div>
            <div class="full"><button class="button button-primary">Guardar configuração 5C.2</button></div>
          </form>
          <div class="ff5-actions" style="margin-top:12px">
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5c2_local"><button class="button">Diagnóstico local 5C.2</button></form>
            <?php if (!empty($entraHealth['configured'])): ?><form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5c2_cloud"><button class="button button-primary">Testar federação no tenant TWT</button></form><?php endif; ?>
          </div>
          <?php if (is_array($entraProbe)): ?><p><strong>Diagnóstico local 5C.2:</strong> <?php echo !empty($entraProbe['ok']) ? 'OK' : 'REVER'; ?></p><div class="ff5-badges"><?php foreach ((array)($entraProbe['checks'] ?? []) as $key=>$ok): ?><span class="ff5-badge <?php echo $ok ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key . ': ' . ($ok ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endif; ?>
          <?php if (is_array($entraCloudProbe)): ?><p class="ff5-note <?php echo !empty($entraCloudProbe['ok']) ? '' : 'ff5-danger'; ?>"><strong>Último token probe TWT:</strong> <?php echo !empty($entraCloudProbe['ok']) ? 'FEDERAÇÃO OK' : 'FALHA'; ?><?php if (!empty($entraCloudProbe['http_code'])): ?>, HTTP <?php echo (int)$entraCloudProbe['http_code']; ?><?php endif; ?><?php if (!empty($entraCloudProbe['scope'])): ?>, scope <code><?php echo esc_html((string)$entraCloudProbe['scope']); ?></code><?php endif; ?><?php if (!empty($entraCloudProbe['entra_code'])): ?>, Entra <code><?php echo esc_html((string)$entraCloudProbe['entra_code']); ?></code><?php endif; ?><?php if (!empty($entraCloudProbe['error'])): ?>, <?php echo esc_html((string)$entraCloudProbe['error']); ?><?php endif; ?></p><?php endif; ?>
          <?php if (is_array($entraDestinationProbe)): ?><p class="ff5-note <?php echo !empty($entraDestinationProbe['ok']) ? '' : 'ff5-danger'; ?>"><strong>Último token probe de destino:</strong> <?php echo !empty($entraDestinationProbe['ok']) ? 'FEDERAÇÃO OK' : 'NÃO VALIDADO'; ?><?php if (!empty($entraDestinationProbe['target_tenant_id'])): ?>, tenant <code><?php echo esc_html((string)$entraDestinationProbe['target_tenant_id']); ?></code><?php endif; ?><?php if (!empty($entraDestinationProbe['scope'])): ?>, scope <code><?php echo esc_html((string)$entraDestinationProbe['scope']); ?></code><?php endif; ?><?php if (!empty($entraDestinationProbe['entra_code'])): ?>, Entra <code><?php echo esc_html((string)$entraDestinationProbe['entra_code']); ?></code><?php endif; ?><?php if (!empty($entraDestinationProbe['error'])): ?>, <?php echo esc_html((string)$entraDestinationProbe['error']); ?><?php endif; ?></p><?php endif; ?>
        </div>

        <div id="ff-entra-onboarding" class="ff5-card" style="margin-top:18px">
          <h2>Fase 5C.2, onboarding de tenants externos</h2>
          <div class="ff5-badges">
            <span class="ff5-badge <?php echo !empty($entraOnboarding['control_plane_ready']) ? 'ff5-ok' : 'ff5-warn'; ?>">Control plane: <?php echo !empty($entraOnboarding['control_plane_ready']) ? 'READY' : 'ATTENTION'; ?></span>
            <span class="ff5-badge ff5-ok">Delivery 5C: BLOQUEADO</span>
            <span class="ff5-badge">Destinos Entra: <?php echo (int)($entraOnboarding['configured'] ?? 0); ?>/<?php echo (int)($entraOnboarding['total'] ?? 0); ?> configurados</span>
            <span class="ff5-badge <?php echo (int)($entraOnboarding['ready'] ?? 0) > 0 ? 'ff5-ok' : ''; ?>">READY identidade: <?php echo (int)($entraOnboarding['ready'] ?? 0); ?></span>
            <span class="ff5-badge">Pendentes: <?php echo (int)($entraOnboarding['pending'] ?? 0); ?></span>
            <span class="ff5-badge <?php echo (int)($entraOnboarding['failed'] ?? 0) === 0 ? 'ff5-ok' : 'ff5-warn'; ?>">A rever: <?php echo (int)($entraOnboarding['failed'] ?? 0); ?></span>
          </div>
          <p class="ff5-note">Este fluxo cria a Enterprise Application no tenant Microsoft do cliente sem client secret, sem Cloud Shell e sem exigir subscrição Azure ao cliente. O administrador abre um link Microsoft no browser; o callback regressa ao Broker TWT, que valida o estado assinado e executa um <strong>token probe real</strong>. Só depois o destino fica <strong>READY, IDENTIDADE</strong>. Isto não abre delivery, SQL, Power BI ou APIs do cliente.</p>

          <?php if (is_array($entraOnboardingLink) && !empty($entraOnboardingLink['consent_url']) && (int)($entraOnboardingLink['expires_at'] ?? 0) > time()): ?>
            <div class="ff5-note" style="margin-top:14px">
              <strong>Link Microsoft pronto</strong><br>
              <span>Destino <code><?php echo esc_html((string)($entraOnboardingLink['destination_uuid'] ?? '')); ?></code>, válido até <?php echo esc_html(gmdate('Y-m-d H:i:s', (int)$entraOnboardingLink['expires_at'])); ?> UTC.</span>
              <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px">
                <input id="ff-entra-consent-url" type="text" readonly value="<?php echo esc_attr((string)$entraOnboardingLink['consent_url']); ?>" style="flex:1;min-width:280px" onclick="this.select();">
                <button type="button" class="button" onclick="var el=document.getElementById('ff-entra-consent-url');el.select();navigator.clipboard&&navigator.clipboard.writeText(el.value);">Copiar link</button>
                <a class="button button-primary" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url((string)$entraOnboardingLink['consent_url']); ?>">Abrir Microsoft Entra</a>
              </div>
              <p class="description">Podes enviar este link ao administrador Entra do cliente. Ele só precisa de uma conta com permissões de administração de aplicações no tenant; não precisa de acesso ao WordPress, Cloud Shell ou subscrição Azure.</p>
            </div>
          <?php endif; ?>

          <div class="ff5-table-wrap" style="margin-top:14px"><table class="widefat striped ff5-table"><thead><tr><th>Destino</th><th>Scope FieldFlow</th><th>Tenant Entra</th><th>Resource scope</th><th>Estado</th><th>Callback</th><th>Ações</th></tr></thead><tbody>
          <?php $hasEntraOnboardingRows = false; foreach ($destinations as $destination):
              $onboardingProfile = ConnectorFoundation::is5cType((string)$destination['type_key']) ? ConnectorFoundation::profileFromDestination($destination) : [];
              if ((string)($onboardingProfile['auth_strategy'] ?? '') !== 'entra_cross_tenant') continue;
              $hasEntraOnboardingRows = true;
              $identity = EntraCrossTenantFoundation::identityProfileFromDestination($destination);
              $identityValidation = EntraCrossTenantFoundation::validateDestinationIdentity($destination, true);
              $onboardingStatus = EntraTenantOnboarding::statusForDestination($destination);
              $onboardingState = (string)($onboardingStatus['status'] ?? 'draft');
              $stateClass = !empty($onboardingStatus['ready']) ? 'ff5-ok' : (in_array($onboardingState, ['token_probe_failed','consent_denied','tenant_mismatch'], true) ? 'ff5-warn' : '');
          ?>
            <tr>
              <td><strong><?php echo esc_html((string)$destination['name']); ?></strong><br><code><?php echo esc_html((string)$destination['destination_uuid']); ?></code></td>
              <td><?php echo esc_html(self::scopeLabel($destination, $clients, $projects)); ?></td>
              <td><code><?php echo esc_html((string)($identity['target_tenant_id'] ?: 'por configurar')); ?></code></td>
              <td><code><?php echo esc_html((string)($identity['resource_scope'] ?: 'por configurar')); ?></code></td>
              <td><span class="ff5-badge <?php echo esc_attr($stateClass); ?>"><?php echo esc_html((string)$onboardingStatus['status_label']); ?></span><?php if (!empty($onboardingStatus['entra_code'])): ?><br><code><?php echo esc_html((string)$onboardingStatus['entra_code']); ?></code><?php endif; ?><?php if (!empty($onboardingStatus['error'])): ?><br><small><?php echo esc_html((string)$onboardingStatus['error']); ?></small><?php endif; ?></td>
              <td><?php echo esc_html((string)($onboardingStatus['callback_delivery'] ?? 'none')); ?><?php if (!empty($onboardingStatus['token_verified_at'])): ?><br><small><?php echo esc_html(gmdate('Y-m-d H:i:s', (int)$onboardingStatus['token_verified_at'])); ?> UTC</small><?php endif; ?></td>
              <td><div class="ff5-actions">
                <?php if (!empty($identityValidation['ok'])): ?>
                  <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="start_5c2_onboarding"><input type="hidden" name="destination_id" value="<?php echo (int)$destination['id']; ?>"><button class="button button-small"><?php echo $onboardingState === 'draft' ? 'Ligar Microsoft Entra' : 'Gerar novo link'; ?></button></form>
                  <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="verify_5c2_onboarding"><input type="hidden" name="destination_id" value="<?php echo (int)$destination['id']; ?>"><button class="button button-small button-primary">Verificar tenant</button></form>
                  <?php if ($onboardingState !== 'draft'): ?><form method="post" onsubmit="return confirm('Reiniciar apenas o estado local de onboarding? A Enterprise Application existente no tenant do cliente não será removida.');"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="reset_5c2_onboarding"><input type="hidden" name="destination_id" value="<?php echo (int)$destination['id']; ?>"><button class="button button-small">Reiniciar estado</button></form><?php endif; ?>
                <?php endif; ?>
                <?php $identityEditUrl = add_query_arg(['page'=>self::SLUG,'ff_edit_5c2_identity'=>(int)$destination['id']], admin_url('admin.php')) . '#ff-entra-onboarding'; ?>
                <a class="button button-small <?php echo empty($identityValidation['ok']) ? 'button-primary' : ''; ?>" href="<?php echo esc_url($identityEditUrl); ?>"><?php echo empty($identityValidation['ok']) ? 'Configurar tenant/scope' : 'Editar tenant/scope'; ?></a>
              </div></td>
            </tr>
            <?php if ($editEntraIdentityId === (int)$destination['id']):
                $identityTenantValue = (string)($identity['target_tenant_id'] ?? '');
                $identityScopeValue = (string)($identity['resource_scope'] ?? '');
                $identityCancelUrl = add_query_arg(['page'=>self::SLUG], admin_url('admin.php')) . '#ff-entra-onboarding';
            ?>
            <tr class="ff5-entra-identity-editor">
              <td colspan="7">
                <div class="ff5-note" style="margin:4px 0">
                  <strong>Configurar identidade Entra deste destino</strong>
                  <p style="margin:6px 0 12px">Indica o <strong>Directory (tenant) ID do cliente</strong> e o resource scope. Estes dados são identificadores públicos. Alterar qualquer um deles reinicia apenas o estado local de onboarding para DRAFT.</p>
                  <form method="post" class="ff5-form-grid">
                    <?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?>
                    <input type="hidden" name="ff_destination_action" value="save_5c2_destination_identity">
                    <input type="hidden" name="destination_id" value="<?php echo (int)$destination['id']; ?>">
                    <div>
                      <label>Tenant Entra do cliente</label>
                      <input type="text" name="entra_target_tenant_id" required value="<?php echo esc_attr($identityTenantValue); ?>" placeholder="00000000-0000-0000-0000-000000000000">
                      <p class="description">Usa o ID do inquilino do tenant externo, nunca o Application client ID.</p>
                    </div>
                    <div>
                      <label>Resource scope</label>
                      <input type="text" name="entra_resource_scope" list="ff-entra-resource-scopes-<?php echo (int)$destination['id']; ?>" required value="<?php echo esc_attr($identityScopeValue); ?>" placeholder="https://management.azure.com/.default">
                      <datalist id="ff-entra-resource-scopes-<?php echo (int)$destination['id']; ?>">
                        <option value="https://management.azure.com/.default">Azure Resource Manager</option>
                        <option value="https://database.windows.net/.default">Azure SQL</option>
                        <option value="https://analysis.windows.net/powerbi/api/.default">Power BI REST</option>
                        <option value="https://api.fabric.microsoft.com/.default">Microsoft Fabric REST</option>
                      </datalist>
                      <p class="description">Para o teste de identidade usa <code>https://management.azure.com/.default</code>.</p>
                    </div>
                    <div class="full ff5-actions">
                      <button class="button button-primary">Guardar tenant/scope</button>
                      <a class="button" href="<?php echo esc_url($identityCancelUrl); ?>">Cancelar</a>
                    </div>
                  </form>
                </div>
              </td>
            </tr>
            <?php endif; ?>
          <?php endforeach; if (!$hasEntraOnboardingRows): ?><tr><td colspan="7">Ainda não existem destinos 5C com autenticação <code>entra_cross_tenant</code>. Cria um destino abaixo e indica o tenant ID e resource scope do cliente.</td></tr><?php endif; ?>
          </tbody></table></div>
          <p class="ff5-note ff5-danger"><strong>READY, IDENTIDADE significa apenas identidade federada validada.</strong> A autorização do recurso concreto é tratada logo abaixo na Fase 5C.3 com probes read-only. O delivery continua a zero nesta release.</p>
        </div>

        <div id="ff-resource-auth" class="ff5-card" style="margin-top:18px">
          <h2>Fase 5C.3, autorização de recursos</h2>
          <div class="ff5-badges">
            <span class="ff5-badge <?php echo !empty($resourceAuth['control_plane_ready']) ? 'ff5-ok' : 'ff5-warn'; ?>">Control plane: <?php echo !empty($resourceAuth['control_plane_ready']) ? 'READY' : 'ATTENTION'; ?></span>
            <span class="ff5-badge ff5-ok">Modo: SHADOW</span>
            <span class="ff5-badge ff5-ok">Probe: READ-ONLY</span>
            <span class="ff5-badge ff5-ok">Delivery 5C: BLOQUEADO</span>
            <span class="ff5-badge">Identidades READY: <?php echo (int)($resourceAuth['eligible'] ?? 0); ?></span>
            <span class="ff5-badge">Home TWT: <?php echo (int)($resourceAuth['home_eligible'] ?? 0); ?></span>
            <span class="ff5-badge">Externas: <?php echo (int)($resourceAuth['external_eligible'] ?? 0); ?></span>
            <span class="ff5-badge <?php echo (int)($resourceAuth['scope_ready'] ?? 0) === (int)($resourceAuth['eligible'] ?? 0) ? 'ff5-ok' : 'ff5-warn'; ?>">Scope compatível: <?php echo (int)($resourceAuth['scope_ready'] ?? 0); ?>/<?php echo (int)($resourceAuth['eligible'] ?? 0); ?></span>
            <span class="ff5-badge">Configurados: <?php echo (int)($resourceAuth['configured'] ?? 0); ?></span>
            <span class="ff5-badge <?php echo (int)($resourceAuth['authorized'] ?? 0) > 0 ? 'ff5-ok' : ''; ?>">READY recurso: <?php echo (int)($resourceAuth['authorized'] ?? 0); ?></span>
            <span class="ff5-badge <?php echo (int)($resourceAuth['failed'] ?? 0) === 0 ? 'ff5-ok' : 'ff5-warn'; ?>">A rever: <?php echo (int)($resourceAuth['failed'] ?? 0); ?></span>
          </div>
          <p class="ff5-note">A 5C.3 prova o <strong>acesso ao recurso concreto</strong>, sem enviar dados. Pode usar uma identidade externa já READY na 5C.2 ou a identidade home do tenant TWT através do mesmo Broker. Azure SQL executa apenas <code>SELECT 1</code> e Power BI/Fabric faz apenas leitura do workspace, usando Power BI REST ou Fabric REST conforme o scope selecionado. APIs externas continuam em Shadow e não recebem tokens nesta fase. Tokens nunca saem do Entra Broker.</p>
          <div class="ff5-actions" style="margin-top:12px">
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5c3_local"><button class="button button-primary">Diagnóstico local 5C.3</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5c3_broker"><button class="button">Verificar Broker 5C.3</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="create_5c3_home_powerbi_destination"><button class="button">Preparar Power BI TWT</button></form>
          </div>
          <?php if (is_array($resourceAuthProbe)): ?><p><strong>Diagnóstico local 5C.3:</strong> <?php echo !empty($resourceAuthProbe['ok']) ? 'OK' : 'REVER'; ?></p><div class="ff5-badges"><?php foreach ((array)($resourceAuthProbe['checks'] ?? []) as $key=>$ok): ?><span class="ff5-badge <?php echo $ok ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key . ': ' . ($ok ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endif; ?>
          <?php if (is_array($resourceBrokerProbe)): ?><p class="ff5-note <?php echo !empty($resourceBrokerProbe['ok']) ? '' : 'ff5-danger'; ?>"><strong>Broker 5C.3:</strong> <?php echo !empty($resourceBrokerProbe['ok']) ? 'READY' : 'REVER'; ?><?php if (!empty($resourceBrokerProbe['version'])): ?>, versão <code><?php echo esc_html((string)$resourceBrokerProbe['version']); ?></code><?php endif; ?>, HMAC <?php echo !empty($resourceBrokerProbe['hmac_runtime_ready']) ? 'READY' : 'NÃO VALIDADO'; ?>, resource probe <?php echo !empty($resourceBrokerProbe['resource_probe_read_only']) ? 'READ-ONLY' : 'INDISPONÍVEL'; ?>, delivery <?php echo !empty($resourceBrokerProbe['delivery_enabled']) ? 'ATIVO' : 'BLOQUEADO'; ?><?php if (!empty($resourceBrokerProbe['error'])): ?>, <?php echo esc_html((string)$resourceBrokerProbe['error']); ?><?php endif; ?></p><?php endif; ?>
          <?php if (is_array($resourceProbe)): ?><p class="ff5-note <?php echo !empty($resourceProbe['ok']) ? '' : 'ff5-danger'; ?>"><strong>Último probe de recurso:</strong> <?php echo !empty($resourceProbe['ok']) ? 'AUTORIZADO' : 'NÃO VALIDADO'; ?><?php if (!empty($resourceProbe['kind'])): ?>, <?php echo esc_html((string)$resourceProbe['kind']); ?><?php endif; ?><?php if (!empty($resourceProbe['http_status'])): ?>, HTTP <?php echo (int)$resourceProbe['http_status']; ?><?php endif; ?><?php if (!empty($resourceProbe['probe_code'])): ?>, <code><?php echo esc_html((string)$resourceProbe['probe_code']); ?></code><?php endif; ?><?php if (!empty($resourceProbe['error'])): ?>, <?php echo esc_html((string)$resourceProbe['error']); ?><?php endif; ?></p><?php endif; ?>

          <div class="ff5-table-wrap" style="margin-top:14px"><table class="widefat striped ff5-table"><thead><tr><th>Destino</th><th>Tipo</th><th>Identidade</th><th>Recurso</th><th>Estado 5C.3</th><th>Último probe</th><th>Ações</th></tr></thead><tbody>
          <?php $hasResourceRows = false; foreach ($destinations as $destination):
              if (!ResourceAuthorizationFoundation::isSupportedDestination($destination)) continue;
              if (!ResourceAuthorizationFoundation::usesSupportedIdentity($destination)) continue;
              $hasResourceRows = true;
              $identity53 = ResourceAuthorizationFoundation::identityContext($destination);
              $identityReady53 = !empty($identity53['ready']);
              $resource53 = ResourceAuthorizationFoundation::profileFromDestination($destination);
              $resourceValidation53 = ResourceAuthorizationFoundation::validateConfiguration($destination, false);
              $resourceScopeCheck53 = ResourceAuthorizationFoundation::validateIdentityScopeForKind($destination);
              $resourceScopeReady53 = !is_wp_error($resourceScopeCheck53);
              $resourceState53 = (string)($resource53['status'] ?? 'draft');
              $resourceStateClass53 = !empty($resource53['authorized']) ? 'ff5-ok' : ($resourceState53 === 'probe_failed' ? 'ff5-warn' : '');
              $resourceLabel53 = 'por configurar';
              if ($resource53['kind'] === 'azure_sql' && $resource53['sql_server'] !== '' && $resource53['sql_database'] !== '') $resourceLabel53 = $resource53['sql_server'] . ' / ' . $resource53['sql_database'];
              elseif (in_array($resource53['kind'], ['powerbi_workspace','fabric_workspace'], true) && $resource53['powerbi_workspace_id'] !== '') $resourceLabel53 = 'workspace ' . $resource53['powerbi_workspace_id'];
          ?>
            <tr>
              <td><strong><?php echo esc_html((string)$destination['name']); ?></strong><br><code><?php echo esc_html((string)$destination['destination_uuid']); ?></code></td>
              <td><?php $kindDef53 = ResourceAuthorizationFoundation::supportedKinds()[(string)$resource53['kind']] ?? []; echo esc_html((string)($kindDef53['label'] ?? ($types[$destination['type_key']]['label'] ?? $destination['type_key']))); ?><br><small><?php echo esc_html((string)$resource53['kind']); ?></small></td>
              <td><span class="ff5-badge <?php echo $identityReady53 ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html((string)($identity53['status_label'] ?? 'DRAFT')); ?></span><br><code><?php echo esc_html((string)($identity53['target_tenant_id'] ?? '')); ?></code><br><small><?php echo esc_html((string)($identity53['resource_scope'] ?? '')); ?></small></td>
              <td><span class="ff5-mono"><?php echo esc_html($resourceLabel53); ?></span><?php if ($identityReady53 && !$resourceScopeReady53): ?><br><span class="ff5-badge ff5-warn">Scope de identidade incompatível</span><?php endif; ?></td>
              <td><span class="ff5-badge <?php echo esc_attr($resourceStateClass53); ?>"><?php echo esc_html((string)$resource53['status_label']); ?></span><?php if (!empty($resource53['error'])): ?><br><small><?php echo esc_html((string)$resource53['error']); ?></small><?php endif; ?></td>
              <td><?php if (!empty($resource53['last_probe_at'])): ?><?php echo esc_html(gmdate('Y-m-d H:i:s', (int)$resource53['last_probe_at'])); ?> UTC<?php if (!empty($resource53['last_http_status'])): ?><br>Broker HTTP <?php echo (int)$resource53['last_http_status']; ?><?php endif; ?><?php if (!empty($resource53['last_remote_http_status'])): ?><br>Recurso HTTP <?php echo (int)$resource53['last_remote_http_status']; ?><?php endif; ?><?php if (!empty($resource53['last_probe_code'])): ?><br><code><?php echo esc_html((string)$resource53['last_probe_code']); ?></code><?php endif; ?><?php else: ?>none<?php endif; ?></td>
              <td><div class="ff5-actions">
                <?php if ($identityReady53):
                    $resourceEditUrl = add_query_arg(['page'=>self::SLUG,'ff_edit_5c3_resource'=>(int)$destination['id']], admin_url('admin.php')) . '#ff-resource-auth';
                    $identityEditUrl53 = add_query_arg(['page'=>self::SLUG,'ff_edit_5c2_identity'=>(int)$destination['id']], admin_url('admin.php')) . '#ff-entra-onboarding'; ?>
                  <?php if ($resourceScopeReady53): ?>
                    <a class="button button-small <?php echo empty($resourceValidation53['configured']) ? 'button-primary' : ''; ?>" href="<?php echo esc_url($resourceEditUrl); ?>"><?php echo empty($resourceValidation53['configured']) ? 'Configurar recurso' : 'Editar recurso'; ?></a>
                    <?php if (!empty($resourceValidation53['configured'])): ?><form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5c3_resource"><input type="hidden" name="destination_id" value="<?php echo (int)$destination['id']; ?>"><button class="button button-small button-primary">Verificar acesso</button></form><?php endif; ?>
                  <?php else: ?>
                    <?php if ((string)($identity53['mode'] ?? '') === 'external_tenant'): ?><a class="button button-small button-primary" href="<?php echo esc_url($identityEditUrl53); ?>">Corrigir scope 5C.2</a><?php else: ?><span class="ff5-badge ff5-warn">Scope home TWT inválido</span><?php endif; ?>
                  <?php endif; ?>
                  <?php if ($resourceState53 !== 'draft'): ?><form method="post" onsubmit="return confirm('Reiniciar apenas o estado local 5C.3? Nenhuma permissão no recurso será removida.');"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="reset_5c3_resource"><input type="hidden" name="destination_id" value="<?php echo (int)$destination['id']; ?>"><button class="button button-small">Reiniciar estado</button></form><?php endif; ?>
                <?php else: ?><span class="ff5-badge ff5-warn">Aguarda identidade READY</span><?php endif; ?>
              </div></td>
            </tr>
            <?php if ($editResourceAuthId === (int)$destination['id'] && $identityReady53 && $resourceScopeReady53):
                $resourceCancelUrl53 = add_query_arg(['page'=>self::SLUG], admin_url('admin.php')) . '#ff-resource-auth';
            ?>
            <tr class="ff5-resource-auth-editor"><td colspan="7">
              <div class="ff5-note" style="margin:4px 0">
                <strong>Configurar autorização read-only do recurso</strong>
                <p style="margin:6px 0 12px">O FieldFlow guarda apenas identificadores públicos. Não introduzas passwords, tokens, connection strings, client secrets ou keys.</p>
                <form method="post" class="ff5-form-grid">
                  <?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?>
                  <input type="hidden" name="ff_destination_action" value="save_5c3_resource">
                  <input type="hidden" name="destination_id" value="<?php echo (int)$destination['id']; ?>">
                  <?php if ($resource53['kind'] === 'azure_sql'): ?>
                    <div><label>Servidor Azure SQL</label><input type="text" name="resource_sql_server" required value="<?php echo esc_attr((string)$resource53['sql_server']); ?>" placeholder="cliente.database.windows.net"><p class="description">Apenas o hostname Azure SQL, sem utilizador, password ou connection string.</p></div>
                    <div><label>Base de dados</label><input type="text" name="resource_sql_database" required value="<?php echo esc_attr((string)$resource53['sql_database']); ?>" placeholder="FieldFlowClient"><p class="description">O probe executa apenas <code>SELECT 1</code>.</p></div>
                  <?php elseif (in_array($resource53['kind'], ['powerbi_workspace','fabric_workspace'], true)): ?>
                    <div class="full"><label>Workspace ID Power BI / Fabric</label><input type="text" name="resource_powerbi_workspace_id" required value="<?php echo esc_attr((string)$resource53['powerbi_workspace_id']); ?>" placeholder="00000000-0000-0000-0000-000000000000"><p class="description">O service principal do FieldFlow deve estar autorizado no workspace. O probe faz apenas leitura dos metadados do workspace.</p></div>
                  <?php endif; ?>
                  <div class="full ff5-actions"><button class="button button-primary">Guardar recurso 5C.3</button><a class="button" href="<?php echo esc_url($resourceCancelUrl53); ?>">Cancelar</a></div>
                </form>
              </div>
            </td></tr>
            <?php endif; ?>
          <?php endforeach; if (!$hasResourceRows): ?><tr><td colspan="7">Ainda não existem destinos Azure SQL ou Power BI/Fabric com identidade suportada. Usa <strong>Preparar Power BI TWT</strong> para criar um destino home em Shadow, ou cria um destino cross-tenant.</td></tr><?php endif; ?>
          </tbody></table></div>
          <p class="ff5-note ff5-danger"><strong>READY, RECURSO não significa delivery.</strong> A 5C.3 apenas prova acesso read-only a Azure SQL ou Power BI/Fabric. Escrita, ingestão no cliente, publicação/refresh Power BI e APIs externas continuam bloqueadas e pertencem às fases de delivery/least-privilege seguintes.</p>
        </div>


        <div id="ff-connector-delivery" class="ff5-card" style="margin-top:18px">
          <h2>Fase 5C.5, Delivery Foundation de APIs externas e Webhooks</h2>
          <div class="ff5-badges">
            <span class="ff5-badge <?php echo !empty($connectorDeliveryHealth['table_ready']) ? 'ff5-ok' : 'ff5-warn'; ?>">Fila dedicada: <?php echo !empty($connectorDeliveryHealth['table_ready']) ? 'READY' : 'ATTENTION'; ?></span>
            <span class="ff5-badge">Modo: <?php echo strtoupper(esc_html((string)($connectorDeliveryHealth['mode'] ?? 'shadow'))); ?></span>
            <span class="ff5-badge <?php echo !empty($connectorDeliveryHealth['kill_switch']) ? 'ff5-ok' : 'ff5-warn'; ?>">Kill switch: <?php echo !empty($connectorDeliveryHealth['kill_switch']) ? 'ON' : 'OFF'; ?></span>
            <span class="ff5-badge">Destinos elegíveis: <?php echo (int)($connectorDeliveryHealth['eligible_destinations'] ?? 0); ?></span>
            <span class="ff5-badge">Shadow: <?php echo (int)($connectorDeliveryHealth['stats']['shadow'] ?? 0); ?></span>
            <span class="ff5-badge">Pending: <?php echo (int)($connectorDeliveryHealth['stats']['pending'] ?? 0); ?></span>
            <span class="ff5-badge <?php echo (int)($connectorDeliveryHealth['stats']['dead'] ?? 0) === 0 ? 'ff5-ok' : 'ff5-warn'; ?>">Dead: <?php echo (int)($connectorDeliveryHealth['stats']['dead'] ?? 0); ?></span>
            <span class="ff5-badge ff5-ok">Rede externa: BLOQUEADA</span>
          </div>
          <p class="ff5-note"><strong>2.2.376 preserva 5A a 5E e acrescenta o fecho Stable da Fase 5.</strong> Shadow regista o routing com payload funcional redigido e sem criar jobs pendentes. Queue persiste jobs com <code>event_uuid</code> e <code>delivery_key</code> determinística, mas o dispatcher termina sempre em <code>network_delivery_locked_2_2_370</code>. O modo Live geral continua a não existir nesta release.</p>
          <p class="ff5-note ff5-danger"><strong>Segredos:</strong> o WordPress nunca resolve <code>secret_ref</code>. API keys, tokens e credenciais futuras serão resolvidos no Azure Broker/Key Vault. O Azure TWT principal e o Relay 5B não são alterados por esta fila.</p>
          <form method="post" class="ff5-form-grid" style="margin-top:14px">
            <?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?>
            <input type="hidden" name="ff_destination_action" value="save_5c5_settings">
            <div><label>Modo 5C.5</label><select name="connector_5c5_mode"><option value="shadow" <?php selected((string)$connectorDeliverySettings['mode'], 'shadow'); ?>>Shadow, observar sem pending</option><option value="queue" <?php selected((string)$connectorDeliverySettings['mode'], 'queue'); ?>>Queue, persistir sem transmitir</option></select></div>
            <div><label>Kill switch global</label><label style="font-weight:400;margin-top:10px"><input type="checkbox" name="connector_5c5_kill_switch" value="1" <?php checked(!empty($connectorDeliverySettings['kill_switch'])); ?>> ON, recomendado</label></div>
            <div><label>Lote futuro</label><input type="number" min="1" max="50" name="connector_5c5_batch_size" value="<?php echo (int)$connectorDeliverySettings['batch_size']; ?>"></div>
            <div><label>Timeout futuro, segundos</label><input type="number" min="5" max="45" name="connector_5c5_timeout" value="<?php echo (int)$connectorDeliverySettings['timeout']; ?>"></div>
            <div><label>Máx. tentativas</label><input type="number" min="1" max="12" name="connector_5c5_max_attempts" value="<?php echo (int)$connectorDeliverySettings['max_attempts']; ?>"></div>
            <div><label>Backoff base, segundos</label><input type="number" min="30" max="3600" name="connector_5c5_base_delay" value="<?php echo (int)$connectorDeliverySettings['base_delay_seconds']; ?>"></div>
            <div><label>Circuit breaker, falhas</label><input type="number" min="2" max="20" name="connector_5c5_circuit_failures" value="<?php echo (int)$connectorDeliverySettings['circuit_failure_threshold']; ?>"></div>
            <div><label>Circuit open, segundos</label><input type="number" min="60" max="86400" name="connector_5c5_circuit_open" value="<?php echo (int)$connectorDeliverySettings['circuit_open_seconds']; ?>"></div>
            <div><label>Retenção da fila, dias</label><input type="number" min="7" max="180" name="connector_5c5_retention_days" value="<?php echo (int)$connectorDeliverySettings['retention_days']; ?>"></div>
            <div class="full"><button class="button button-primary">Guardar configuração 5C.5</button></div>
          </form>
          <div class="ff5-actions" style="margin-top:12px">
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5c5_local"><button class="button button-primary">Diagnóstico local 5C.5</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="dispatch_5c5"><button class="button">Testar gate do dispatcher</button></form>
            <?php if ((int)($connectorDeliveryHealth['stats']['dead'] ?? 0) > 0): ?><form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="retry_5c5"><button class="button">Recuperar dead 5C.5</button></form><?php endif; ?>
          </div>
          <div class="ff5-card" style="margin-top:14px;background:#fbfbfc">
            <h3 style="margin-top:0">Certificação sintética 5C.5, sem tocar em dados operacionais</h3>
            <p class="ff5-note">Escolhe um destino API Externa/Webhook já ativo. O teste usa o routing real e a fila 5C.5, mas não dispara o EventBus global, não cria submissões/rotas e não transmite nada para a Internet. Executa o mesmo <code>delivery_key</code> duas vezes para provar idempotência.</p>
            <?php if (!$connectorDeliveryEligibleDestinations): ?>
              <p class="ff5-note ff5-danger">Ainda não existe um destino API Externa/Webhook ativo elegível. Ativa um destino e garante pelo menos uma regra INCLUDE ativa.</p>
            <?php else: ?>
              <form method="post">
                <?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?>
                <div class="ff5-form-grid">
                  <div class="full"><label>Destino para certificação</label><select name="connector_5c5_destination_id">
                    <?php foreach ($connectorDeliveryEligibleDestinations as $destination55): ?><option value="<?php echo (int)$destination55['id']; ?>"><?php echo esc_html((string)$destination55['name'] . ' · ' . (string)($destination55['type_label'] ?: $destination55['type_key'])); ?></option><?php endforeach; ?>
                  </select></div>
                </div>
                <div class="ff5-actions" style="margin-top:10px">
                  <button class="button button-primary" name="ff_destination_action" value="certify_5c5_shadow">Certificar Shadow 5C.5</button>
                  <button class="button" name="ff_destination_action" value="certify_5c5_queue">Certificar Queue 5C.5</button>
                </div>
              </form>
              <p class="description" style="margin-top:8px">Shadow exige Modo SHADOW + Kill switch ON. Queue exige Modo QUEUE + Kill switch OFF. Mesmo em Queue, o dispatcher operacional permanece hard-locked; só o sender sintético 5C.6 pode emitir rede durante certificação.</p>
            <?php endif; ?>
          </div>
          <?php if (is_array($connectorDeliveryProbe)): ?><p><strong>Diagnóstico 5C.5:</strong> <?php echo !empty($connectorDeliveryProbe['ok']) ? 'OK' : 'REVER'; ?></p><div class="ff5-badges"><?php foreach ((array)($connectorDeliveryProbe['checks'] ?? []) as $key=>$ok): ?><span class="ff5-badge <?php echo $ok ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key . ': ' . ($ok ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endif; ?>
          <?php if (is_array($connectorDeliveryCertification)): ?><p><strong>Última certificação sintética:</strong> <?php echo !empty($connectorDeliveryCertification['ok']) ? 'OK' : 'REVER'; ?>, modo <code><?php echo esc_html((string)($connectorDeliveryCertification['mode'] ?? '')); ?></code>, destino <strong><?php echo esc_html((string)($connectorDeliveryCertification['destination_name'] ?? '')); ?></strong>, job #<?php echo (int)($connectorDeliveryCertification['job_id'] ?? 0); ?>, estado <code><?php echo esc_html((string)($connectorDeliveryCertification['status'] ?? '')); ?></code>.</p><div class="ff5-badges"><?php foreach ((array)($connectorDeliveryCertification['checks'] ?? []) as $key=>$ok): ?><span class="ff5-badge <?php echo $ok ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key . ': ' . ($ok ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endif; ?>
          <?php if (is_array($connectorDeliveryDispatch)): ?><p class="ff5-note"><strong>Último teste do dispatcher:</strong> <?php echo !empty($connectorDeliveryDispatch['blocked']) ? 'BLOQUEADO COMO PREVISTO' : 'EXECUTADO'; ?>, motivo <code><?php echo esc_html((string)($connectorDeliveryDispatch['reason'] ?? '')); ?></code>.</p><?php endif; ?>
          <div class="ff5-table-wrap" style="margin-top:14px"><table class="widefat striped ff5-table"><thead><tr><th>ID</th><th>Conector</th><th>Evento</th><th>Destino UUID</th><th>Modo</th><th>Estado</th><th>Tentativas</th><th>Payload hash</th><th>Criado UTC</th></tr></thead><tbody>
          <?php if (!$connectorDeliveryRecent): ?><tr><td colspan="9">Ainda não existem jobs 5C.5. Ativa um destino API Externa/Webhook e deixa o modo em Shadow para começar a observar sem transmitir.</td></tr><?php endif; ?>
          <?php foreach ($connectorDeliveryRecent as $job55): ?><tr><td><?php echo (int)$job55['id']; ?></td><td><code><?php echo esc_html((string)$job55['connector_type']); ?></code></td><td><code><?php echo esc_html((string)$job55['event_name']); ?></code></td><td><code><?php echo esc_html((string)$job55['destination_uuid']); ?></code></td><td><?php echo esc_html((string)$job55['execution_mode']); ?></td><td><?php echo esc_html((string)$job55['status']); ?></td><td><?php echo (int)$job55['attempts']; ?>/<?php echo (int)$job55['max_attempts']; ?></td><td><code><?php echo esc_html(substr((string)$job55['payload_sha256'], 0, 16)); ?>...</code></td><td><?php echo esc_html((string)$job55['created_at']); ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </div>

        <div class="ff5-card" id="ff-controlled-delivery" style="margin-top:18px">
          <h2>Fase 5C.6, Controlled External Delivery</h2>
          <div class="ff5-badges">
            <span class="ff5-badge <?php echo !empty($controlledDeliveryHealth['table_ready']) ? 'ff5-ok' : 'ff5-warn'; ?>">Fila 5C.5: <?php echo !empty($controlledDeliveryHealth['table_ready']) ? 'READY' : 'ATTENTION'; ?></span>
            <span class="ff5-badge <?php echo !empty($controlledDeliveryHealth['broker_configured']) ? 'ff5-ok' : 'ff5-warn'; ?>">Broker: <?php echo !empty($controlledDeliveryHealth['broker_configured']) ? 'CONFIGURADO' : 'POR VALIDAR'; ?></span>
            <span class="ff5-badge ff5-ok">Certification-only</span>
            <span class="ff5-badge ff5-ok">Target arbitrário: BLOQUEADO</span>
            <span class="ff5-badge ff5-ok">Receipts: HMAC obrigatório</span>
            <span class="ff5-badge ff5-ok">Delivery operacional: BLOQUEADO</span>
          </div>
          <p class="ff5-note"><strong>A 5C.6 envia rede real apenas com payloads sintéticos.</strong> O WordPress envia o job ao Entra Broker 1.6.0, mantendo o contrato 5C.6 compatível. O Broker ignora qualquer endpoint do destino e encaminha apenas para o seu receiver fixo de certificação, que não persiste dados. Nenhum payload operacional, endpoint arbitrário, client secret, access token ou API key pode sair por este caminho.</p>
          <p class="ff5-note ff5-danger"><strong>Pré-condição para os testes de rede:</strong> Modo 5C.5 em <code>QUEUE</code> e Kill switch global <code>OFF</code>. Terminada a certificação, volta a <code>SHADOW</code> + Kill switch <code>ON</code>.</p>
          <div class="ff5-actions" style="margin-top:12px">
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5c6_local"><button class="button button-primary">Diagnóstico local 5C.6</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5c6_broker"><button class="button">Verificar Broker 5C.6</button></form>
          </div>
          <?php if ($connectorDeliveryEligibleDestinations): ?>
          <form method="post" style="margin-top:14px">
            <?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?>
            <div class="ff5-form-grid">
              <div class="full"><label>Destino lógico para a certificação</label><select name="connector_5c6_destination_id">
                <?php foreach ($connectorDeliveryEligibleDestinations as $destination56): ?><option value="<?php echo (int)$destination56['id']; ?>"><?php echo esc_html((string)$destination56['name'] . ' · ' . (string)($destination56['type_label'] ?: $destination56['type_key'])); ?></option><?php endforeach; ?>
              </select><p class="description">O endpoint real deste destino não é utilizado na 5C.6. O Broker força o receiver sintético TWT.</p></div>
            </div>
            <div class="ff5-actions" style="margin-top:10px">
              <button class="button button-primary" name="ff_destination_action" value="certify_5c6_delivery">Certificar Delivery 5C.6</button>
              <button class="button" name="ff_destination_action" value="certify_5c6_resilience">Certificar Retry + Dead Letter</button>
              <button class="button" name="ff_destination_action" value="certify_5c6_circuit">Certificar Circuit Breaker</button>
            </div>
          </form>
          <?php else: ?>
            <p class="ff5-note ff5-danger">É necessário pelo menos um destino API Externa ou Webhook ativo com uma regra INCLUDE ativa.</p>
          <?php endif; ?>
          <?php if (is_array($controlledDeliveryProbe)): ?><p><strong>Diagnóstico local 5C.6:</strong> <?php echo !empty($controlledDeliveryProbe['ok']) ? 'OK' : 'REVER'; ?></p><div class="ff5-badges"><?php foreach ((array)($controlledDeliveryProbe['checks'] ?? []) as $key=>$ok): ?><span class="ff5-badge <?php echo $ok ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key . ': ' . ($ok ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endif; ?>
          <?php if (is_array($controlledBrokerProbe)): ?><p class="ff5-note <?php echo !empty($controlledBrokerProbe['ok']) ? '' : 'ff5-danger'; ?>"><strong>Broker 5C.6:</strong> <?php echo !empty($controlledBrokerProbe['ok']) ? 'READY' : 'REVER'; ?>, versão <code><?php echo esc_html((string)($controlledBrokerProbe['version'] ?? '')); ?></code>, certification-only <?php echo !empty($controlledBrokerProbe['certification_only']) ? 'SIM' : 'NÃO'; ?>, receiver fixo <?php echo !empty($controlledBrokerProbe['receiver_fixed']) ? 'SIM' : 'NÃO'; ?>, receipts assinados <?php echo !empty($controlledBrokerProbe['signed_receipts']) ? 'SIM' : 'NÃO'; ?>.</p><?php endif; ?>
          <?php foreach ([['label'=>'Delivery', 'data'=>$controlledDeliveryCertification], ['label'=>'Retry + Dead Letter', 'data'=>$controlledResilienceCertification], ['label'=>'Circuit Breaker', 'data'=>$controlledCircuitCertification]] as $certBlock56): $cert56 = $certBlock56['data']; if (!is_array($cert56)) continue; ?><p><strong><?php echo esc_html($certBlock56['label']); ?>:</strong> <?php echo !empty($cert56['ok']) ? 'OK' : 'REVER'; ?><?php if (!empty($cert56['destination_name'])): ?>, destino <strong><?php echo esc_html((string)$cert56['destination_name']); ?></strong><?php endif; ?>.</p><div class="ff5-badges"><?php foreach ((array)($cert56['checks'] ?? []) as $key=>$ok): ?><span class="ff5-badge <?php echo $ok ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key . ': ' . ($ok ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endforeach; ?>
        </div>

        <div class="ff5-card" id="ff-safe-live-pilot" style="margin-top:18px">
          <h2>Fase 5C.7, Safe Live Pilot</h2>
          <div class="ff5-badges">
            <span class="ff5-badge <?php echo (string)($connectorDeliverySettings['mode'] ?? '') === 'shadow' ? 'ff5-ok' : 'ff5-warn'; ?>">5C.5: <?php echo strtoupper(esc_html((string)($connectorDeliverySettings['mode'] ?? 'shadow'))); ?></span>
            <span class="ff5-badge <?php echo !empty($connectorDeliverySettings['kill_switch']) ? 'ff5-ok' : 'ff5-warn'; ?>">Kill switch: <?php echo !empty($connectorDeliverySettings['kill_switch']) ? 'ON' : 'OFF'; ?></span>
            <span class="ff5-badge ff5-ok">Full payload: BLOQUEADO</span>
            <span class="ff5-badge ff5-ok">Safe projection only</span>
            <span class="ff5-badge">Targets preparados: <?php echo (int)($safePilotHealth['prepared_targets'] ?? 0); ?></span>
            <span class="ff5-badge">Targets certificados: <?php echo (int)($safePilotHealth['synthetic_certified_targets'] ?? 0); ?></span>
            <span class="ff5-badge <?php echo (int)($safePilotHealth['armed_one_shots'] ?? 0) === 0 ? 'ff5-ok' : 'ff5-warn'; ?>">One-shots armados: <?php echo (int)($safePilotHealth['armed_one_shots'] ?? 0); ?></span>
          </div>
          <p class="ff5-note"><strong>A 5C.7 prova um endpoint HTTPS configurado sem abrir o dispatcher operacional.</strong> O Broker 1.6.0 valida DNS/rede pública, bloqueia IP literals, redes privadas, portas não 443 e redirects, e devolve um token de curta duração ligado ao <code>destination UUID + endpoint SHA-256</code>. O alvo recebe apenas IDs e metadados técnicos. Respostas de formulários, fotos, uploads, tokens, API keys e client secrets nunca entram neste contrato.</p>
          <p class="ff5-note ff5-danger"><strong>Estado seguro para 5C.7:</strong> mantém <code>SHADOW</code> + Kill switch <code>ON</code>. O one-shot consome no máximo um evento e é marcado como usado <strong>antes</strong> da chamada de rede, por isso nunca há retry automático de um evento operacional neste gate.</p>
          <div class="ff5-actions" style="margin-top:12px">
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5c7_local"><button class="button button-primary">Diagnóstico local 5C.7</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5c7_broker"><button class="button">Verificar Broker 5C.7</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="create_5c7_twt_pilot_destination"><button class="button">Preparar receiver TWT 5C.7</button></form>
          </div>
          <?php if ($safePilotDestinations): ?>
          <form method="post" style="margin-top:14px">
            <?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?>
            <div class="ff5-form-grid">
              <div class="full"><label>Destino HTTPS para o piloto</label><select name="connector_5c7_destination_id">
                <?php foreach ($safePilotDestinations as $destination57): ?><option value="<?php echo (int)$destination57['id']; ?>"><?php echo esc_html((string)$destination57['name'] . ' · ' . (string)($destination57['type_label'] ?: $destination57['type_key']) . ' · ' . (string)(wp_parse_url((string)$destination57['endpoint_ref'], PHP_URL_HOST) ?: '')); ?></option><?php endforeach; ?>
              </select><p class="description">Para certificação rápida usa <strong>TWT Safe Live Pilot Receiver</strong>. Para um cliente real podes usar depois um endpoint HTTPS do próprio cliente.</p></div>
              <div class="full"><label>Evento para one-shot real, opcional</label><input type="text" name="connector_5c7_event_pattern" value="submission.created" placeholder="submission.created"><p class="description">Nunca uses <code>*</code>. O armamento expira em 15 minutos e só pode consumir um evento compatível.</p></div>
            </div>
            <div class="ff5-actions" style="margin-top:10px">
              <button class="button" name="ff_destination_action" value="prepare_5c7_target">1. Preparar alvo</button>
              <button class="button button-primary" name="ff_destination_action" value="certify_5c7_synthetic">2. Certificar alvo sintético</button>
              <button class="button" name="ff_destination_action" value="certify_5c7_one_shot">3. Certificar one-shot seguro</button>
              <button class="button" name="ff_destination_action" value="arm_5c7_one_shot" onclick="return confirm('Armar o próximo evento compatível por 15 minutos? Apenas IDs/metadados seguros saem; o payload operacional original é bloqueado.');">Armar próximo evento real</button>
              <button class="button" name="ff_destination_action" value="cancel_5c7_pilot">Cancelar one-shot</button>
            </div>
          </form>
          <?php else: ?>
            <p class="ff5-note ff5-danger">Ainda não existe um destino API Externa/Webhook ativo com endpoint HTTPS. Clica em <strong>Preparar receiver TWT 5C.7</strong> para criar o receiver de certificação sem regras de routing operacional.</p>
          <?php endif; ?>
          <?php if (is_array($safePilotProbe)): ?><p><strong>Diagnóstico local 5C.7:</strong> <?php echo !empty($safePilotProbe['ok']) ? 'OK' : 'REVER'; ?></p><div class="ff5-badges"><?php foreach ((array)($safePilotProbe['checks'] ?? []) as $key=>$ok): ?><span class="ff5-badge <?php echo $ok ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key . ': ' . ($ok ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endif; ?>
          <?php if (is_array($safePilotBrokerProbe)): ?><p class="ff5-note <?php echo !empty($safePilotBrokerProbe['ok']) ? '' : 'ff5-danger'; ?>"><strong>Broker 5C.7:</strong> <?php echo !empty($safePilotBrokerProbe['ok']) ? 'READY' : 'REVER'; ?>, versão <code><?php echo esc_html((string)($safePilotBrokerProbe['version'] ?? '')); ?></code>, target guardado <?php echo !empty($safePilotBrokerProbe['dynamic_target_guarded']) ? 'SIM' : 'NÃO'; ?>, tokenizado <?php echo !empty($safePilotBrokerProbe['tokenized']) ? 'SIM' : 'NÃO'; ?>, one-shot <?php echo !empty($safePilotBrokerProbe['one_shot']) ? 'SIM' : 'NÃO'; ?>, full payload bloqueado <?php echo !empty($safePilotBrokerProbe['full_payload_blocked']) ? 'SIM' : 'NÃO'; ?>.</p><?php endif; ?>
          <?php foreach ([['label'=>'Preparar alvo','data'=>$safePilotPrepare],['label'=>'Target sintético','data'=>$safePilotSynthetic],['label'=>'Armamento','data'=>$safePilotArm],['label'=>'One-shot seguro','data'=>$safePilotOneShot]] as $certBlock57): $cert57=$certBlock57['data']; if (!is_array($cert57)) continue; ?><p><strong><?php echo esc_html($certBlock57['label']); ?>:</strong> <?php echo !empty($cert57['ok']) ? 'OK' : 'REVER'; ?><?php if (!empty($cert57['destination_name'])): ?>, destino <strong><?php echo esc_html((string)$cert57['destination_name']); ?></strong><?php endif; ?><?php if (!empty($cert57['remote_http_status'])): ?>, HTTP <?php echo (int)$cert57['remote_http_status']; ?><?php endif; ?>.</p><div class="ff5-badges"><?php foreach ((array)($cert57['checks'] ?? []) as $key=>$ok): ?><span class="ff5-badge <?php echo $ok ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key . ': ' . ($ok ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endforeach; ?>
          <div class="ff5-table-wrap" style="margin-top:14px"><table class="widefat striped ff5-table"><thead><tr><th>Destino</th><th>Host</th><th>Preparado</th><th>Sintético</th><th>One-shot</th><th>Evento armado</th><th>Último resultado</th></tr></thead><tbody>
          <?php if (!$safePilotSummary): ?><tr><td colspan="7">Sem targets HTTPS elegíveis para 5C.7.</td></tr><?php endif; ?>
          <?php foreach ($safePilotSummary as $pilotRow57): ?><tr><td><strong><?php echo esc_html((string)$pilotRow57['name']); ?></strong><br><code><?php echo esc_html((string)$pilotRow57['destination_uuid']); ?></code></td><td><?php echo esc_html((string)$pilotRow57['endpoint_host']); ?></td><td><?php echo !empty($pilotRow57['prepared']) ? 'READY' : 'Não'; ?></td><td><?php echo !empty($pilotRow57['synthetic_certified']) ? 'CERTIFICADO' : 'Não'; ?></td><td><?php echo !empty($pilotRow57['armed']) ? 'ARMADO' : (!empty($pilotRow57['consumed']) ? 'CONSUMIDO' : 'Seguro'); ?></td><td><code><?php echo esc_html((string)$pilotRow57['armed_event_pattern']); ?></code></td><td><?php echo !empty($pilotRow57['last_ok']) ? 'OK' : ((string)$pilotRow57['last_error'] !== '' ? esc_html((string)$pilotRow57['last_error']) : 'Sem teste'); ?><?php if (!empty($pilotRow57['last_http_code'])): ?>, HTTP <?php echo (int)$pilotRow57['last_http_code']; ?><?php endif; ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </div>

        <div class="ff5-card" id="ff-production-gate" style="margin-top:18px">
          <h2>Fase 5C.8, Production Delivery Gate</h2>
          <div class="ff5-badges">
            <span class="ff5-badge <?php echo (string)($connectorDeliverySettings['mode'] ?? '') === 'shadow' ? 'ff5-ok' : 'ff5-warn'; ?>">5C.5: <?php echo strtoupper(esc_html((string)($connectorDeliverySettings['mode'] ?? 'shadow'))); ?></span>
            <span class="ff5-badge <?php echo !empty($connectorDeliverySettings['kill_switch']) ? 'ff5-ok' : 'ff5-warn'; ?>">Kill switch: <?php echo !empty($connectorDeliverySettings['kill_switch']) ? 'ON' : 'OFF'; ?></span>
            <span class="ff5-badge ff5-ok">5C.7 obrigatório</span>
            <span class="ff5-badge ff5-ok">Policy-bound</span>
            <span class="ff5-badge ff5-ok">Safe projection only</span>
            <span class="ff5-badge ff5-ok">Full payload: BLOQUEADO</span>
            <span class="ff5-badge ff5-ok">Produção geral: DESLIGADA por defeito</span>
            <span class="ff5-badge">Production ready: <?php echo (int)($productionGateHealth['production_ready_gates'] ?? 0); ?></span>
          </div>
          <p class="ff5-note"><strong>A 5C.8 fecha a cadeia técnica de delivery externo antes das gates finais de credenciais e governance.</strong> Cada target fica ligado ao seu endpoint SHA-256, a uma policy explícita de evento e limite horário, a um token de curta duração e a receipts HMAC. O teste final usa a queue real 5C.5, mas apenas com <code>execution_mode=production_gate_test</code> e uma projeção de metadados segura.</p>
          <p class="ff5-note ff5-danger"><strong>Importante:</strong> esta release não expõe um botão de ativação geral. Respostas de formulários, fotos, uploads, texto livre, tokens, API keys e client secrets continuam fora do contrato. Destinos com autenticação real ficam reservados à 5D.</p>
          <div class="ff5-actions" style="margin-top:12px">
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5c8_local"><button class="button button-primary">Diagnóstico local 5C.8</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5c8_broker"><button class="button">Verificar Broker 5C.8</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="create_5c8_twt_destination"><button class="button">Preparar receiver TWT 5C.8</button></form>
          </div>
          <?php if ($productionGateDestinations): ?>
          <form method="post" style="margin-top:14px">
            <?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?>
            <div class="ff5-form-grid">
              <div class="full"><label>Destino elegível, com 5C.7 certificada</label><select name="connector_5c8_destination_id">
                <?php foreach ($productionGateDestinations as $destination58): ?><option value="<?php echo (int)$destination58['id']; ?>"><?php echo esc_html((string)$destination58['name'] . ' · ' . (string)($destination58['type_label'] ?: $destination58['type_key']) . ' · ' . (string)(wp_parse_url((string)$destination58['endpoint_ref'], PHP_URL_HOST) ?: '')); ?></option><?php endforeach; ?>
              </select><p class="description">Para a certificação mais rápida usa <strong>TWT Production Gate Receiver</strong>. O botão acima cria-o e certifica automaticamente a pré-condição 5C.7 sem dados operacionais.</p></div>
              <div><label>Evento permitido</label><input type="text" name="connector_5c8_event_pattern" value="submission.created" placeholder="submission.created"><p class="description">Tem de ser explícito. Wildcards não são aceites nesta gate.</p></div>
              <div><label>Limite futuro por hora</label><input type="number" name="connector_5c8_max_per_hour" min="1" max="60" value="10"><p class="description">Fica incluído e assinado na policy 5C.8.</p></div>
            </div>
            <div class="ff5-actions" style="margin-top:10px">
              <button class="button" name="ff_destination_action" value="prepare_5c8_gate">1. Preparar gate</button>
              <button class="button" name="ff_destination_action" value="certify_5c8_target">2. Certificar target</button>
              <button class="button button-primary" name="ff_destination_action" value="certify_5c8_queue">3. Certificar queue de produção</button>
              <button class="button" name="ff_destination_action" value="reset_5c8_gate" onclick="return confirm('Reiniciar apenas o estado local 5C.8 deste destino?');">Reiniciar gate</button>
            </div>
          </form>
          <?php else: ?>
            <p class="ff5-note ff5-danger">Ainda não existe um destino elegível com 5C.7 completa. Clica em <strong>Preparar receiver TWT 5C.8</strong> para criar um target de certificação e cumprir automaticamente a pré-condição 5C.7.</p>
          <?php endif; ?>
          <?php if (is_array($productionGateProbe)): ?><p><strong>Diagnóstico local 5C.8:</strong> <?php echo !empty($productionGateProbe['ok']) ? 'OK' : 'REVER'; ?></p><div class="ff5-badges"><?php foreach ((array)($productionGateProbe['checks'] ?? []) as $key=>$ok): ?><span class="ff5-badge <?php echo $ok ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key . ': ' . ($ok ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endif; ?>
          <?php if (is_array($productionGateBrokerProbe)): ?><p class="ff5-note <?php echo !empty($productionGateBrokerProbe['ok']) ? '' : 'ff5-danger'; ?>"><strong>Broker 5C.8:</strong> <?php echo !empty($productionGateBrokerProbe['ok']) ? 'READY' : 'REVER'; ?>, versão <code><?php echo esc_html((string)($productionGateBrokerProbe['version'] ?? '')); ?></code>, target guardado <?php echo !empty($productionGateBrokerProbe['dynamic_target_guarded']) ? 'SIM' : 'NÃO'; ?>, policy-bound <?php echo !empty($productionGateBrokerProbe['policy_bound']) ? 'SIM' : 'NÃO'; ?>, full payload bloqueado <?php echo !empty($productionGateBrokerProbe['full_payload_blocked']) ? 'SIM' : 'NÃO'; ?>, ativação geral <?php echo !empty($productionGateBrokerProbe['general_activation_enabled']) ? 'ABERTA' : 'BLOQUEADA'; ?>.</p><?php endif; ?>
          <?php foreach ([['label'=>'Preparar gate','data'=>$productionGatePrepare],['label'=>'Target 5C.8','data'=>$productionGateTarget],['label'=>'Queue de produção','data'=>$productionGateQueue]] as $certBlock58): $cert58=$certBlock58['data']; if (!is_array($cert58)) continue; ?><p><strong><?php echo esc_html($certBlock58['label']); ?>:</strong> <?php echo !empty($cert58['ok']) ? 'OK' : 'REVER'; ?><?php if (!empty($cert58['destination_name'])): ?>, destino <strong><?php echo esc_html((string)$cert58['destination_name']); ?></strong><?php endif; ?><?php if (!empty($cert58['remote_http_status'])): ?>, HTTP <?php echo (int)$cert58['remote_http_status']; ?><?php endif; ?>.</p><div class="ff5-badges"><?php foreach ((array)($cert58['checks'] ?? []) as $key=>$ok): ?><span class="ff5-badge <?php echo $ok ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key . ': ' . ($ok ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endforeach; ?>
          <div class="ff5-table-wrap" style="margin-top:14px"><table class="widefat striped ff5-table"><thead><tr><th>Destino</th><th>Host</th><th>5C.7</th><th>Gate</th><th>Target</th><th>Queue</th><th>Production Ready</th><th>Policy</th><th>Ativação geral</th><th>Último resultado</th></tr></thead><tbody>
          <?php if (!$productionGateSummary): ?><tr><td colspan="10">Sem destinos elegíveis para 5C.8.</td></tr><?php endif; ?>
          <?php foreach ($productionGateSummary as $gateRow58): ?><tr><td><strong><?php echo esc_html((string)$gateRow58['name']); ?></strong><br><code><?php echo esc_html((string)$gateRow58['destination_uuid']); ?></code></td><td><?php echo esc_html((string)$gateRow58['endpoint_host']); ?></td><td><?php echo !empty($gateRow58['pilot_certified']) ? 'CERTIFICADA' : 'Não'; ?></td><td><?php echo !empty($gateRow58['prepared']) ? 'READY' : 'Não'; ?></td><td><?php echo !empty($gateRow58['target_certified']) ? 'CERTIFICADO' : 'Não'; ?></td><td><?php echo !empty($gateRow58['queue_certified']) ? 'CERTIFICADA' : 'Não'; ?></td><td><?php echo !empty($gateRow58['production_ready']) ? 'READY' : 'Não'; ?></td><td><code><?php echo esc_html((string)$gateRow58['event_pattern']); ?></code><br><small><?php echo (int)$gateRow58['max_per_hour']; ?>/h</small></td><td><span class="ff5-badge ff5-ok">DESLIGADA por defeito</span></td><td><?php echo !empty($gateRow58['last_ok']) ? 'OK' : ((string)$gateRow58['last_error'] !== '' ? esc_html((string)$gateRow58['last_error']) : 'Sem teste'); ?><?php if (!empty($gateRow58['last_http_code'])): ?>, HTTP <?php echo (int)$gateRow58['last_http_code']; ?><?php endif; ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </div>

        <div class="ff5-card" id="ff-credential-lifecycle" style="margin-top:18px">
          <h2>Fase 5D, Credential Lifecycle &amp; Secret Governance</h2>
          <div class="ff5-badges">
            <span class="ff5-badge ff5-ok">Key Vault-only</span>
            <span class="ff5-badge ff5-ok">Segredos no WordPress: NÃO</span>
            <span class="ff5-badge ff5-ok">Rotação: DETETADA</span>
            <span class="ff5-badge ff5-ok">Expiração: BLOQUEIA</span>
            <span class="ff5-badge ff5-ok">Revogação: IMEDIATA</span>
            <span class="ff5-badge ff5-ok">Receipts: HMAC</span>
            <span class="ff5-badge">Credenciais READY: <?php echo (int)($credentialLifecycleHealth['ready_credentials'] ?? 0); ?></span>
            <span class="ff5-badge ff5-warn">Produção geral: DESLIGADA por defeito</span>
          </div>
          <p class="ff5-note"><strong>A 5D retira os valores das credenciais do WordPress.</strong> O BO guarda apenas referências <code>kv://vault/secret</code> e metadados de lifecycle. O Entra Broker 1.8.0 resolve o segredo com Managed Identity, devolve apenas versão/validade/estado e certifica a injeção num receiver TWT fixo. Se a versão rodar, expirar, ficar disabled ou for revogada localmente, a readiness cai automaticamente.</p>
          <p class="ff5-note ff5-danger"><strong>Continua sem ativação automática.</strong> A 5C.8 permanece Production Ready e a 5E fecha governance, recovery e elegibilidade. A Stable 2.2.376 congela a baseline certificada, mantendo full payload e delivery operacional desligados por defeito.</p>
          <div class="ff5-actions">
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5d_local"><button class="button button-primary">Diagnóstico local 5D</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5d_broker"><button class="button">Verificar Broker 5D</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="create_5d_twt_destination"><button class="button">Preparar credencial TWT 5D</button></form>
          </div>

          <?php if ($credentialLifecycleDestinations): ?>
          <form method="post" class="ff5-form-grid" style="margin-top:16px">
            <?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?>
            <div class="full"><label>Destino para lifecycle 5D</label><select name="connector_5d_destination_id">
              <?php foreach ($credentialLifecycleDestinations as $dest5d): ?><option value="<?php echo (int)$dest5d['id']; ?>" <?php selected((int)$dest5d['id'], $credentialLifecycleDefaultId); ?>><?php echo esc_html((string)$dest5d['name'] . ' · ' . (string)$dest5d['type_key']); ?></option><?php endforeach; ?>
            </select></div>
            <div class="full"><label>Secret reference, nunca o segredo</label><input type="text" name="connector_5d_secret_ref" placeholder="kv://kv-ff-prod-.../cliente-api-key"><p class="description">Se deixares vazio, mantém a referência já guardada no destino. O receiver TWT usa automaticamente <code>kv://<?php echo esc_html(CredentialLifecycle::TEST_VAULT . '/' . CredentialLifecycle::TEST_SECRET); ?></code>.</p></div>
            <div><label>Modo de autenticação</label><select name="connector_5d_auth_mode"><option value="api_key_header">API key em header</option><option value="bearer">Bearer token</option></select></div>
            <div><label>Header, para API key</label><input type="text" name="connector_5d_header_name" value="X-FieldFlow-Credential"><p class="description">Bearer usa sempre <code>Authorization</code>. Headers reservados são rejeitados.</p></div>
            <div><label>Alerta antes de expirar, dias</label><input type="number" name="connector_5d_warning_days" min="1" max="90" value="30"></div>
            <div class="full ff5-actions">
              <button class="button" name="ff_destination_action" value="save_5d_credential">Guardar perfil 5D</button>
              <button class="button" name="ff_destination_action" value="probe_5d_credential">1. Verificar credencial</button>
              <button class="button button-primary" name="ff_destination_action" value="certify_5d_credential">2. Certificar lifecycle 5D</button>
              <button class="button" name="ff_destination_action" value="revoke_5d_credential" onclick="return confirm('Revogar localmente esta credencial? A readiness fica bloqueada imediatamente, sem apagar o segredo no Key Vault.');">Revogar localmente</button>
              <button class="button" name="ff_destination_action" value="reset_5d_credential" onclick="return confirm('Reiniciar apenas o estado 5D deste destino?');">Reiniciar 5D</button>
            </div>
          </form>
          <?php else: ?>
            <p class="ff5-note ff5-danger">Ainda não existe um destino para credenciais Key Vault. Clica em <strong>Preparar credencial TWT 5D</strong>.</p>
          <?php endif; ?>

          <?php if (is_array($credentialLifecycleProbe)): ?><p><strong>Diagnóstico local 5D:</strong> <?php echo !empty($credentialLifecycleProbe['ok']) ? 'OK' : 'REVER'; ?></p><div class="ff5-badges"><?php foreach ((array)($credentialLifecycleProbe['checks'] ?? []) as $key=>$ok): ?><span class="ff5-badge <?php echo $ok ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key . ': ' . ($ok ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endif; ?>
          <?php if (is_array($credentialLifecycleBrokerProbe)): ?><p class="ff5-note <?php echo !empty($credentialLifecycleBrokerProbe['ok']) ? '' : 'ff5-danger'; ?>"><strong>Broker 5D:</strong> <?php echo !empty($credentialLifecycleBrokerProbe['ok']) ? 'READY' : 'REVER'; ?>, versão <code><?php echo esc_html((string)($credentialLifecycleBrokerProbe['version'] ?? '')); ?></code>, Key Vault-only <?php echo !empty($credentialLifecycleBrokerProbe['key_vault_only']) ? 'SIM' : 'NÃO'; ?>, test secret <?php echo !empty($credentialLifecycleBrokerProbe['test_secret_ready']) ? 'READY' : 'NÃO'; ?>, valores devolvidos <?php echo !empty($credentialLifecycleBrokerProbe['secret_values_never_returned']) ? 'NÃO' : 'A REVER'; ?>.</p><?php endif; ?>
          <?php foreach ([['label'=>'Credential probe','data'=>$credentialLifecycleCredentialProbe],['label'=>'Certificação 5D','data'=>$credentialLifecycleCertification]] as $block5d): $cert5d=$block5d['data']; if (!is_array($cert5d)) continue; ?><p><strong><?php echo esc_html($block5d['label']); ?>:</strong> <?php echo !empty($cert5d['ok']) ? 'OK' : 'REVER'; ?><?php if (!empty($cert5d['destination_name'])): ?>, destino <strong><?php echo esc_html((string)$cert5d['destination_name']); ?></strong><?php endif; ?><?php if (!empty($cert5d['remote_http_status'])): ?>, HTTP <?php echo (int)$cert5d['remote_http_status']; ?><?php endif; ?><?php if (!empty($cert5d['credential_status'])): ?>, estado <?php echo esc_html((string)$cert5d['credential_status']); ?><?php endif; ?>.</p><div class="ff5-badges"><?php foreach ((array)($cert5d['checks'] ?? []) as $key=>$ok): ?><span class="ff5-badge <?php echo $ok ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key . ': ' . ($ok ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endforeach; ?>

          <div class="ff5-table-wrap" style="margin-top:14px"><table class="widefat striped ff5-table"><thead><tr><th>Destino</th><th>Secret ref</th><th>Auth</th><th>Estado</th><th>Versão</th><th>Validade</th><th>Rotação</th><th>Revogado</th><th>5D Ready</th></tr></thead><tbody>
            <?php if (!$credentialLifecycleSummary): ?><tr><td colspan="9">Sem perfis de credencial 5D.</td></tr><?php endif; ?>
            <?php foreach ($credentialLifecycleSummary as $row5d): ?><tr><td><strong><?php echo esc_html((string)$row5d['name']); ?></strong><br><code><?php echo esc_html((string)$row5d['destination_uuid']); ?></code></td><td class="ff5-mono"><?php echo esc_html((string)$row5d['secret_ref']); ?></td><td><?php echo esc_html((string)$row5d['auth_mode']); ?><br><small><?php echo esc_html((string)$row5d['header_name']); ?></small></td><td><?php echo esc_html((string)$row5d['credential_status']); ?></td><td class="ff5-mono"><?php echo esc_html((string)$row5d['secret_version']); ?><?php if (!empty($row5d['certified'])): ?><br><small>certificada</small><?php endif; ?></td><td><?php echo $row5d['expires_at'] !== '' ? esc_html((string)$row5d['expires_at']) : 'Sem expiração'; ?><?php if ($row5d['days_remaining'] !== null): ?><br><small><?php echo (int)$row5d['days_remaining']; ?> dias</small><?php endif; ?></td><td><?php echo !empty($row5d['rotation_detected']) ? '<span class="ff5-badge ff5-warn">DETETADA</span>' : 'OK'; ?></td><td><?php echo !empty($row5d['revoked']) ? '<span class="ff5-badge ff5-warn">SIM</span>' : 'Não'; ?></td><td><?php echo !empty($row5d['ready']) ? '<span class="ff5-badge ff5-ok">READY</span>' : '<span class="ff5-badge ff5-warn">BLOQUEADA</span>'; ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </div>

        <div class="ff5-card" id="ff-governance-recovery" style="margin-top:18px">
          <h2>Fase 5E, Governance, Recovery &amp; Final Eligibility Gate</h2>
          <div class="ff5-badges">
            <span class="ff5-badge <?php echo !empty($governanceHealth['ledger_chain_valid']) ? 'ff5-ok' : 'ff5-warn'; ?>">Ledger: <?php echo !empty($governanceHealth['ledger_chain_valid']) ? 'VALIDO' : 'REVER'; ?></span>
            <span class="ff5-badge <?php echo !empty($governanceHealth['global_kill_switch']) ? 'ff5-ok' : 'ff5-warn'; ?>">Kill global: <?php echo !empty($governanceHealth['global_kill_switch']) ? 'ON' : 'OFF'; ?></span>
            <span class="ff5-badge">5C.8 READY: <?php echo (int)($governanceHealth['production_5c8_ready_gates'] ?? 0); ?></span>
            <span class="ff5-badge">5D READY: <?php echo (int)($governanceHealth['credential_5d_ready'] ?? 0); ?></span>
            <span class="ff5-badge <?php echo !empty($governanceSummary['ledger_certified']) ? 'ff5-ok' : 'ff5-warn'; ?>">Ledger controls: <?php echo !empty($governanceSummary['ledger_certified']) ? 'CERTIFICADO' : 'PENDENTE'; ?></span>
            <span class="ff5-badge <?php echo !empty($governanceSummary['replay_certified']) ? 'ff5-ok' : 'ff5-warn'; ?>">Replay: <?php echo !empty($governanceSummary['replay_certified']) ? 'CERTIFICADO' : 'PENDENTE'; ?></span>
            <span class="ff5-badge <?php echo !empty($governanceSummary['recovery_certified']) ? 'ff5-ok' : 'ff5-warn'; ?>">Recovery: <?php echo !empty($governanceSummary['recovery_certified']) ? 'CERTIFICADO' : 'PENDENTE'; ?></span>
            <span class="ff5-badge <?php echo !empty($governanceSummary['production_eligible']) ? 'ff5-ok' : 'ff5-warn'; ?>">Production: <?php echo !empty($governanceSummary['production_eligible']) ? 'ELIGIBLE' : 'BLOQUEADA'; ?></span>
            <span class="ff5-badge ff5-warn">Production enabled: NÃO</span>
          </div>
          <p class="ff5-note"><strong>A 5E fecha governance e recovery sem abrir produção.</strong> O ledger é encadeado por SHA-256, os kill controls são globais e por scope, dead letters só podem ser reabertos com justificação e ticket de curta duração, stale locks têm recovery auditado, e a gate final fica vinculada a um fingerprint do control plane. Se destinos, routing, policies ou versões de credencial mudarem, o estado <code>PRODUCTION ELIGIBLE</code> deixa de ser válido.</p>
          <p class="ff5-note ff5-danger"><strong>Estado seguro desta release:</strong> 5C.5 em SHADOW, kill switch 5C.5 ON, kill switch governance ON e delivery operacional de rede continua hard-locked. A 2.2.376 certifica a baseline Stable em <code>PRODUCTION ELIGIBLE</code>, mantendo <code>PRODUCTION ENABLED</code> desligado por defeito.</p>

          <div class="ff5-actions">
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5e_local"><button class="button button-primary">Diagnóstico local 5E</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_5e_broker"><button class="button">Verificar Broker 5E</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="snapshot_5e"><button class="button">Criar snapshot recovery</button></form>
          </div>

          <form method="post" class="ff5-form-grid" style="margin-top:16px">
            <?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?>
            <div><label>Kill switch global</label><input type="text" value="ON, bloqueado nesta release" readonly><p class="description">A Stable 2.2.376 preserva este kill switch ON. A ativação futura será explícita, auditada e feita por cliente/destino.</p></div>
            <div><label>Lock stale após, segundos</label><input type="number" name="governance_5e_stale_lock_seconds" min="120" max="86400" value="<?php echo (int)($governanceSettings['stale_lock_seconds'] ?? GovernanceRecovery::DEFAULT_STALE_LOCK_SECONDS); ?>"></div>
            <div><label>Clientes bloqueados</label><select name="governance_5e_blocked_client_ids[]" multiple size="6"><option value="">Nenhum</option><?php foreach ($clients as $cid5e=>$clabel5e): ?><option value="<?php echo (int)$cid5e; ?>" <?php echo in_array((int)$cid5e, (array)($governanceSettings['blocked_client_ids'] ?? []), true) ? 'selected' : ''; ?>><?php echo esc_html((string)$clabel5e); ?></option><?php endforeach; ?></select></div>
            <div><label>Destinos bloqueados</label><select name="governance_5e_blocked_destination_uuids[]" multiple size="6"><option value="">Nenhum</option><?php foreach ($destinations as $dest5e): ?><option value="<?php echo esc_attr((string)$dest5e['destination_uuid']); ?>" <?php echo in_array((string)$dest5e['destination_uuid'], (array)($governanceSettings['blocked_destination_uuids'] ?? []), true) ? 'selected' : ''; ?>><?php echo esc_html((string)$dest5e['name'] . ' · ' . (string)$dest5e['destination_uuid']); ?></option><?php endforeach; ?></select></div>
            <div class="full"><label>Eventos bloqueados, um por linha</label><textarea name="governance_5e_blocked_event_patterns" rows="4" placeholder="submission.created&#10;route.completed"><?php echo esc_textarea(implode("\n", (array)($governanceSettings['blocked_event_patterns'] ?? []))); ?></textarea><p class="description">Sem wildcards nesta gate. A comparação é explícita por nome normalizado.</p></div>
            <div class="full ff5-actions"><button class="button" name="ff_destination_action" value="save_5e_controls">Guardar controlos 5E</button></div>
          </form>

          <div class="ff5-actions" style="margin-top:16px">
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="certify_5e_ledger"><button class="button">1. Certificar ledger + kill controls</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="certify_5e_replay"><button class="button">2. Certificar Dead Letter + Replay</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="certify_5e_recovery"><button class="button">3. Certificar Recovery</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="certify_5e_final"><button class="button button-primary">4. Certificar gate final 5E</button></form>
            <form method="post" onsubmit="return confirm('Reiniciar a certificação 5E? O ledger não será apagado.');"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="reset_5e_certification"><button class="button">Reiniciar 5E</button></form>
          </div>

          <?php if (is_array($governanceProbe)): ?><p><strong>Diagnóstico local 5E:</strong> <?php echo !empty($governanceProbe['ok']) ? 'OK' : 'REVER'; ?></p><div class="ff5-badges"><?php foreach ((array)($governanceProbe['checks'] ?? []) as $key5e=>$ok5e): ?><span class="ff5-badge <?php echo $ok5e ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key5e . ': ' . ($ok5e ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endif; ?>
          <?php if (is_array($governanceBrokerProbe)): ?><p class="ff5-note <?php echo !empty($governanceBrokerProbe['ok']) ? '' : 'ff5-danger'; ?>"><strong>Broker 5E:</strong> <?php echo !empty($governanceBrokerProbe['ok']) ? 'READY' : 'REVER'; ?>, versão <code><?php echo esc_html((string)($governanceBrokerProbe['version'] ?? '')); ?></code>, reutiliza o Broker 1.8.0 certificado na 5D, novo deployment Azure <?php echo !empty($governanceBrokerProbe['governance_recovery_requires_new_broker']) ? 'SIM' : 'NÃO'; ?>.</p><?php endif; ?>
          <?php foreach ([['label'=>'1. Ledger + kill controls','data'=>$governanceLedgerCert],['label'=>'2. Dead Letter + Replay','data'=>$governanceReplayCert],['label'=>'3. Recovery','data'=>$governanceRecoveryCert],['label'=>'4. Gate final','data'=>$governanceFinalCert]] as $block5e): $cert5e=$block5e['data']; if (!is_array($cert5e)) continue; ?><p><strong><?php echo esc_html($block5e['label']); ?>:</strong> <?php echo !empty($cert5e['ok']) ? 'OK' : 'REVER'; ?><?php if (!empty($cert5e['message'])): ?>, <?php echo esc_html((string)$cert5e['message']); ?><?php endif; ?></p><div class="ff5-badges"><?php foreach ((array)($cert5e['checks'] ?? []) as $key5e=>$ok5e): ?><span class="ff5-badge <?php echo $ok5e ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key5e . ': ' . ($ok5e ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div><?php endforeach; ?>

          <div class="ff5-grid" style="margin-top:16px">
            <div class="ff5-card" style="box-shadow:none">
              <h3>Recovery operacional</h3>
              <p>Dead letters: <strong><?php echo (int)($governanceHealth['dead_letter_count'] ?? 0); ?></strong>, stale running: <strong><?php echo (int)($governanceHealth['stale_running_count'] ?? 0); ?></strong>.</p>
              <?php if (!empty($governanceSnapshot['sha256'])): ?><p class="ff5-mono">Snapshot: <?php echo esc_html((string)$governanceSnapshot['sha256']); ?><br><small><?php echo esc_html((string)($governanceSnapshot['created_at'] ?? '')); ?></small></p><?php endif; ?>
              <form method="post" onsubmit="return confirm('Recuperar agora locks stale reais da fila 5C.5? A rede continuará bloqueada.');"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="recover_5e_stale"><button class="button">Recuperar stale locks</button></form>
              <?php if (is_array($governanceManualRecovery)): ?><p class="ff5-note">Último recovery manual: <?php echo (int)($governanceManualRecovery['recovered'] ?? 0); ?> recuperados, <?php echo (int)($governanceManualRecovery['dead'] ?? 0); ?> dead.</p><?php endif; ?>
            </div>
            <div class="ff5-card" style="box-shadow:none">
              <h3>Final gate</h3>
              <p><strong><?php echo !empty($governanceSummary['production_eligible']) ? 'PRODUCTION ELIGIBLE' : 'AINDA BLOQUEADA'; ?></strong></p>
              <p>Fingerprint atual: <code><?php echo esc_html(substr((string)($governanceHealth['eligibility_fingerprint_sha256'] ?? ''), 0, 16)); ?>…</code></p>
              <p>Fingerprint certificado: <?php echo !empty($governanceHealth['eligibility_fingerprint_match']) ? '<span class="ff5-badge ff5-ok">MATCH</span>' : '<span class="ff5-badge ff5-warn">PENDENTE / ALTERADO</span>'; ?></p>
              <p class="ff5-note ff5-danger">Production Enabled permanece <strong>NÃO</strong>. A ativação real será uma release gate separada, depois de a 5E estar certificada.</p>
            </div>
          </div>

          <h3 style="margin-top:20px">Dead Letter Queue</h3>
          <div class="ff5-table-wrap"><table class="widefat striped ff5-table"><thead><tr><th>Job</th><th>Evento</th><th>Destino</th><th>Tentativas</th><th>Erro</th><th>Criado UTC</th><th>Replay controlado</th></tr></thead><tbody>
            <?php if (!$governanceDeadJobs): ?><tr><td colspan="7">Sem dead letters na fila 5C.5.</td></tr><?php endif; ?>
            <?php foreach ($governanceDeadJobs as $dead5e): ?><tr><td>#<?php echo (int)$dead5e['id']; ?><br><code><?php echo esc_html(substr((string)$dead5e['delivery_key'], 0, 18)); ?>…</code></td><td><?php echo esc_html((string)$dead5e['event_name']); ?></td><td><code><?php echo esc_html((string)$dead5e['destination_uuid']); ?></code></td><td><?php echo (int)$dead5e['attempts']; ?>/<?php echo (int)$dead5e['max_attempts']; ?></td><td><?php echo esc_html((string)($dead5e['last_error_code'] ?? '')); ?><br><small><?php echo esc_html(substr((string)($dead5e['last_error_message'] ?? ''), 0, 160)); ?></small></td><td><?php echo esc_html((string)$dead5e['created_at']); ?></td><td><form method="post" style="min-width:260px"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="replay_5e_dead_job"><input type="hidden" name="governance_5e_job_id" value="<?php echo (int)$dead5e['id']; ?>"><input type="text" name="governance_5e_replay_reason" required minlength="10" placeholder="Justificação obrigatória"><button class="button" style="margin-top:6px" onclick="return confirm('Autorizar e executar este replay? O dispatcher de rede continua bloqueado nesta release.');">Autorizar replay</button></form></td></tr><?php endforeach; ?>
          </tbody></table></div>
          <?php if (is_array($governanceManualReplay)): $exec5e=$governanceManualReplay['execution'] ?? []; ?><p class="ff5-note <?php echo !empty($exec5e['ok']) ? '' : 'ff5-danger'; ?>">Último replay manual: <?php echo !empty($exec5e['ok']) ? 'OK' : 'REVER'; ?><?php if (!empty($exec5e['job_id'])): ?>, job #<?php echo (int)$exec5e['job_id']; ?> em <code><?php echo esc_html((string)($exec5e['status'] ?? '')); ?></code><?php endif; ?>.</p><?php endif; ?>

          <h3 style="margin-top:20px">Governance Ledger, últimos eventos</h3>
          <div class="ff5-table-wrap"><table class="widefat striped ff5-table"><thead><tr><th>ID</th><th>Evento</th><th>Entidade</th><th>Actor</th><th>Scope</th><th>Correlation</th><th>Hash</th><th>UTC</th></tr></thead><tbody>
            <?php if (!$governanceLedgerRows): ?><tr><td colspan="8">O ledger ainda não tem entradas.</td></tr><?php endif; ?>
            <?php foreach ($governanceLedgerRows as $log5e): ?><tr><td>#<?php echo (int)$log5e['id']; ?></td><td><code><?php echo esc_html((string)$log5e['event_name']); ?></code></td><td><?php echo esc_html((string)$log5e['entity_type']); ?><?php if (!empty($log5e['entity_ref'])): ?><br><small><?php echo esc_html((string)$log5e['entity_ref']); ?></small><?php endif; ?></td><td><?php echo (int)($log5e['actor_user_id'] ?? 0); ?><br><small><?php echo esc_html((string)$log5e['source']); ?></small></td><td>C<?php echo (int)($log5e['client_id'] ?? 0); ?> / P<?php echo (int)($log5e['project_id'] ?? 0); ?></td><td><code><?php echo esc_html(substr((string)$log5e['correlation_id'], 0, 14)); ?>…</code></td><td><code><?php echo esc_html(substr((string)$log5e['entry_hash'], 0, 16)); ?>…</code></td><td><?php echo esc_html((string)$log5e['created_at']); ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </div>


        <div class="ff5-card" id="ff-phase5-stable" style="margin-top:18px">
          <h2>Fecho Stable da Fase 5</h2>
          <div class="ff5-badges">
            <span class="ff5-badge <?php echo !empty($phase5StableHealth['matrix_ready']) ? 'ff5-ok' : 'ff5-warn'; ?>">Matriz 5A a 5E: <?php echo !empty($phase5StableHealth['matrix_ready']) ? 'READY' : 'REVER'; ?></span>
            <span class="ff5-badge <?php echo !empty($phase5StableHealth['stable_certified']) ? 'ff5-ok' : 'ff5-warn'; ?>">Stable 2.2.376: <?php echo !empty($phase5StableHealth['stable_certified']) ? 'CERTIFICADA' : 'POR CERTIFICAR'; ?></span>
            <span class="ff5-badge <?php echo !empty($phase5StableHealth['baseline_match']) ? 'ff5-ok' : 'ff5-warn'; ?>">Baseline: <?php echo !empty($phase5StableHealth['baseline_match']) ? 'MATCH' : 'PENDENTE'; ?></span>
            <span class="ff5-badge <?php echo !empty($phase5StableHealth['production_eligible']) ? 'ff5-ok' : 'ff5-warn'; ?>">Production Eligible: <?php echo !empty($phase5StableHealth['production_eligible']) ? 'SIM' : 'NÃO'; ?></span>
            <span class="ff5-badge ff5-ok">Production Enabled: NÃO</span>
          </div>
          <p class="ff5-note"><strong>Esta gate fecha formalmente a Fase 5.</strong> Congela uma baseline SHA-256 de 5A a 5E, valida o manifesto da release, regista a certificação no governance ledger e cria um snapshot de rollback. A ativação operacional continua desligada por defeito e passa a ser uma decisão explícita por cliente/destino no onboarding seguinte.</p>
          <p class="ff5-note ff5-danger"><strong>Segurança da Stable:</strong> SHADOW preservado, kill switches ON, full payload bloqueado e nenhuma instalação desta release ativa produção automaticamente.</p>

          <div class="ff5-actions" style="margin:14px 0">
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_phase5_stable"><button class="button button-primary">1. Diagnóstico de fecho</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="certify_phase5_stable"><button class="button button-primary">2. Certificar Stable 2.2.376</button></form>
            <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="download_phase5_baseline"><button class="button">Descarregar baseline + rollback JSON</button></form>
            <form method="post" onsubmit="return confirm('Reiniciar apenas a certificação Stable? O governance ledger e o snapshot de rollback serão preservados.');"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="reset_phase5_stable"><button class="button">Reiniciar gate Stable</button></form>
          </div>

          <?php if (is_array($phase5StableProbe)): ?>
            <p><strong>Diagnóstico Stable:</strong> <?php echo !empty($phase5StableProbe['ok']) ? 'OK' : 'REVER'; ?></p>
            <div class="ff5-badges"><?php foreach ((array)($phase5StableProbe['checks'] ?? []) as $stableKey=>$stableOk): ?><span class="ff5-badge <?php echo $stableOk ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($stableKey . ': ' . ($stableOk ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div>
          <?php endif; ?>
          <?php if (is_array($phase5StableCert)): ?>
            <p><strong>Certificação Stable:</strong> <?php echo !empty($phase5StableCert['ok']) ? 'OK' : 'REVER'; ?><?php if (!empty($phase5StableCert['message'])): ?>, <?php echo esc_html((string)$phase5StableCert['message']); ?><?php endif; ?></p>
            <div class="ff5-badges"><?php foreach ((array)($phase5StableCert['checks'] ?? []) as $stableKey=>$stableOk): ?><span class="ff5-badge <?php echo $stableOk ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($stableKey . ': ' . ($stableOk ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?></div>
          <?php endif; ?>

          <h3 style="margin-top:20px">Matriz final de certificação</h3>
          <div class="ff5-table-wrap"><table class="widefat striped ff5-table" style="min-width:760px"><thead><tr><th>Gate</th><th>Componente</th><th>Estado</th><th>Evidência</th></tr></thead><tbody>
          <?php foreach ($phase5StableMatrix as $stablePhase=>$stableRow): ?><tr><td><strong><?php echo esc_html((string)$stablePhase); ?></strong></td><td><?php echo esc_html((string)($stableRow['label'] ?? '')); ?></td><td><?php echo !empty($stableRow['ready']) ? '<span class="ff5-badge ff5-ok">READY</span>' : '<span class="ff5-badge ff5-warn">REVER</span>'; ?></td><td><?php echo esc_html((string)($stableRow['detail'] ?? '')); ?></td></tr><?php endforeach; ?>
          </tbody></table></div>

          <div class="ff5-grid" style="margin-top:16px">
            <div class="ff5-card" style="box-shadow:none">
              <h3>Baseline certificada</h3>
              <p>Fingerprint atual: <code><?php echo esc_html(substr((string)($phase5StableHealth['baseline_fingerprint_sha256'] ?? ''), 0, 20)); ?>...</code></p>
              <p>Fingerprint certificado: <?php echo !empty($phase5StableHealth['baseline_match']) ? '<span class="ff5-badge ff5-ok">MATCH</span>' : '<span class="ff5-badge ff5-warn">PENDENTE</span>'; ?></p>
              <?php if (!empty($phase5StableSnapshot['snapshot_sha256'])): ?><p>Snapshot SHA-256: <code><?php echo esc_html(substr((string)$phase5StableSnapshot['snapshot_sha256'], 0, 24)); ?>...</code></p><?php endif; ?>
              <?php if (!empty($phase5StableHealth['certified_at'])): ?><p>Certificada em UTC: <strong><?php echo esc_html((string)$phase5StableHealth['certified_at']); ?></strong></p><?php endif; ?>
            </div>
            <div class="ff5-card" style="box-shadow:none">
              <h3>Rollback conhecido</h3>
              <p>Release de rollback: <strong>2.2.375</strong></p>
              <p><code><?php echo esc_html((string)($phase5StableHealth['rollback_package'] ?? 'fieldflow-2.2.375-phase-5e-governance-recovery.zip')); ?></code></p>
              <p class="ff5-note">Antes de rollback: backup da base de dados e uploads, SHADOW, kill switches ON e Production Enabled em NÃO. O JSON exportado inclui a sequência completa.</p>
            </div>
          </div>

          <?php if (!empty($phase5StableHealth['stable_certified'])): ?>
            <p class="ff5-note" style="margin-top:16px"><strong>FASE 5 FECHADA EM STABLE.</strong> A plataforma está tecnicamente certificada e pronta para a Fase 6, onboarding simplificado por cliente. Production Eligible: SIM. Production Enabled: NÃO.</p>
          <?php endif; ?>
        </div>

        <?php if (is_array($probe)): ?>
        <div class="ff5-card" style="margin-top:18px"><h2>Último diagnóstico local</h2>
          <div class="ff5-badges">
          <?php foreach ((array)($probe['checks'] ?? []) as $key=>$ok): ?><span class="ff5-badge <?php echo $ok ? 'ff5-ok' : 'ff5-warn'; ?>"><?php echo esc_html($key . ': ' . ($ok ? 'OK' : 'FALHA')); ?></span><?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="ff5-card" style="margin-top:18px">
          <h2>Destination Registry</h2>
          <div class="ff5-table-wrap"><table class="widefat striped ff5-table"><thead><tr><th>Destino</th><th>Tipo</th><th>Scope</th><th>Modo</th><th>Estado</th><th>Endpoint/ref</th><th>Secret ref</th><th>Fase</th><th>Ações</th></tr></thead><tbody>
          <?php if (!$destinations): ?><tr><td colspan="9">Sem destinos registados.</td></tr><?php endif; ?>
          <?php foreach ($destinations as $destination): $primary = (string)$destination['type_key'] === 'twt_azure'; $connectorProfile = ConnectorFoundation::is5cType((string)$destination['type_key']) ? ConnectorFoundation::profileFromDestination($destination) : []; $entraDestination = (string)($connectorProfile['auth_strategy'] ?? '') === 'entra_cross_tenant'; ?>
            <tr>
              <td><strong><?php echo esc_html((string)$destination['name']); ?></strong><br><code><?php echo esc_html((string)$destination['destination_uuid']); ?></code></td>
              <td><?php echo esc_html((string)($destination['type_label'] ?: $destination['type_key'])); ?></td>
              <td><?php echo esc_html(self::scopeLabel($destination, $clients, $projects)); ?></td>
              <td><?php echo esc_html((string)$destination['mode']); ?></td>
              <td><?php echo !empty($destination['is_active']) ? 'Ativo' : 'Inativo'; ?>, <?php echo esc_html((string)$destination['status']); ?></td>
              <td class="ff5-mono"><?php echo esc_html((string)($destination['endpoint_ref'] ?? '')); ?></td>
              <td class="ff5-mono"><?php echo esc_html((string)($destination['secret_ref'] ?? '')); ?></td>
              <td><?php echo esc_html((string)($destination['delivery_phase'] ?? '')); ?></td>
              <td><div class="ff5-actions">
              <?php if ($primary): ?><span class="ff5-badge ff5-ok">Bloqueado como principal</span><?php else: ?>
                <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="toggle_destination"><input type="hidden" name="destination_id" value="<?php echo (int)$destination['id']; ?>"><input type="hidden" name="enable" value="<?php echo !empty($destination['is_active']) ? '0' : '1'; ?>"><button class="button button-small"><?php echo !empty($destination['is_active']) ? 'Desativar' : 'Ativar'; ?></button></form>
                <?php if ((string)$destination['type_key'] === 'client_azure'): ?><form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_destination_5b"><input type="hidden" name="destination_id" value="<?php echo (int)$destination['id']; ?>"><button class="button button-small">Testar 5B</button></form><?php endif; ?>
                <?php if (ConnectorFoundation::is5cType((string)$destination['type_key'])): ?><form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_destination_5c"><input type="hidden" name="destination_id" value="<?php echo (int)$destination['id']; ?>"><button class="button button-small">Validar 5C.1</button></form><?php endif; ?>
                <?php if ($entraDestination): ?><form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="probe_destination_5c2"><input type="hidden" name="destination_id" value="<?php echo (int)$destination['id']; ?>"><button class="button button-small">Testar Entra 5C.2</button></form><?php endif; ?>
                <form method="post" onsubmit="return confirm('Remover este destino e as respetivas regras?');"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="delete_destination"><input type="hidden" name="destination_id" value="<?php echo (int)$destination['id']; ?>"><button class="button button-small">Remover</button></form>
              <?php endif; ?>
              </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody></table></div>
        </div>

        <div class="ff5-grid">
          <div class="ff5-card">
            <h2>Novo destino</h2>
            <form method="post" class="ff5-form-grid">
              <?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?>
              <input type="hidden" name="ff_destination_action" value="create_destination">
              <div class="full"><label>Nome</label><input type="text" name="name" required placeholder="Azure Pernod, Reporting Cliente, API ERP..."></div>
              <div><label>Tipo</label><select name="type_key" required><?php foreach ($types as $typeKey=>$definition): if ($typeKey === 'twt_azure') continue; ?><option value="<?php echo esc_attr($typeKey); ?>"><?php echo esc_html($definition['label'] . ' , Fase ' . $definition['delivery_phase']); ?></option><?php endforeach; ?></select></div>
              <div><label>Modo</label><select name="mode"><option value="mirror">Mirror</option><option value="reporting">Reporting</option><option value="optional">Optional</option></select></div>
              <div><label>Cliente</label><?php self::clientSelect('client_id', $clients); ?></div>
              <div><label>Projeto / campanha</label><?php self::projectSelect('project_id', $projects); ?></div>
              <div class="full"><label>Endpoint ou referência não sensível</label><input type="text" name="endpoint_ref" placeholder="https://... ou azure://..."></div>
              <div class="full"><label>Secret reference, nunca o segredo</label><input type="text" name="secret_ref" placeholder="https://...vault.azure.net/secrets/... ou kv://vault/secret"><p class="description">Para API Externa/Webhook na 5C.5 esta referência continua a ser apenas uma referência segura. Nunca coloques passwords, tokens ou API keys.</p></div>
              <div><label>Autenticação prevista, 5C</label><select name="auth_strategy"><option value="">Automática pelo tipo</option><option value="entra_cross_tenant">Microsoft Entra cross-tenant</option><option value="entra_home_tenant">Microsoft Entra, tenant TWT</option><option value="managed_identity">Managed Identity</option><option value="key_vault_reference">Key Vault reference</option><option value="none">Sem autenticação</option></select></div>
              <div><label>Tenant Entra do cliente, 5C.2</label><input type="text" name="entra_target_tenant_id" placeholder="00000000-0000-0000-0000-000000000000"><p class="description">Só é usado quando a autenticação é Entra cross-tenant. É um identificador público.</p></div>
              <div class="full"><label>Resource scope Entra, 5C.2</label><input type="text" name="entra_resource_scope" placeholder="https://database.windows.net/.default"><p class="description">Exemplos: Azure SQL <code>https://database.windows.net/.default</code>, Power BI <code>https://analysis.windows.net/powerbi/api/.default</code> ou uma API própria <code>api://.../.default</code>. Nunca coloques tokens.</p></div>
              <div><label>Máx. tentativas</label><input type="number" min="1" max="12" name="max_attempts" value="5"></div>
              <div><label>Backoff base, segundos</label><input type="number" min="30" max="3600" name="base_delay_seconds" value="60"></div>
              <div class="full"><label><input type="checkbox" name="is_active" value="1"> Ativo no routing Multi-Destination</label><p class="description">Azure do Cliente usa o Relay 5B quando Live. API Externa/Webhook permanecem protegidos pelo 5C.5. A 2.2.376 preserva 5C.6/5C.7/5C.8/5D/5E e fecha a baseline Stable da Fase 5. A ativação efetiva passa a ser uma decisão explícita do onboarding por cliente, nunca automática.</p></div>
              <div class="full"><button class="button button-primary">Criar destino</button></div>
            </form>
          </div>
          <div class="ff5-card">
            <h2>Preview de routing</h2>
            <form method="post" class="ff5-form-grid">
              <?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?>
              <input type="hidden" name="ff_destination_action" value="route_preview">
              <div class="full"><label>Evento</label><input type="text" name="preview_event" value="submission.created"></div>
              <div><label>Cliente</label><?php self::clientSelect('preview_client_id', $clients); ?></div>
              <div><label>Projeto / campanha</label><?php self::projectSelect('preview_project_id', $projects); ?></div>
              <div class="full"><button class="button">Calcular routing local</button></div>
            </form>
            <?php if (is_array($preview)): ?><hr><p><strong>Event UUID de preview:</strong> <code><?php echo esc_html((string)($preview['event_uuid'] ?? '')); ?></code></p>
            <?php foreach ((array)($preview['destinations'] ?? []) as $item): ?><p class="ff5-note"><strong><?php echo esc_html((string)$item['name']); ?></strong><br>Tipo: <?php echo esc_html((string)$item['type_key']); ?><br>Delivery key: <code><?php echo esc_html((string)$item['delivery_key']); ?></code><br>Connector: <code><?php echo esc_html((string)$item['connector']); ?></code><?php if (!empty($item['connector_contract'])): ?><br>Contrato: <code><?php echo esc_html((string)$item['connector_contract']); ?></code><br>Execução: <?php echo esc_html((string)($item['execution_mode'] ?? 'shadow')); ?>, delivery ready: <?php echo !empty($item['delivery_ready']) ? 'SIM' : 'NÃO'; ?><?php if (!empty($item['entra_credential_model'])): ?><br>Entra: <code><?php echo esc_html((string)$item['entra_credential_model']); ?></code>, tenant <code><?php echo esc_html((string)($item['entra_target_tenant_id'] ?? 'por configurar')); ?></code>, scope <code><?php echo esc_html((string)($item['entra_resource_scope'] ?? 'por configurar')); ?></code><?php endif; ?><?php endif; ?></p><?php endforeach; ?>
            <?php if (empty($preview['destinations'])): ?><p>Nenhum destino corresponde a este scope/evento.</p><?php endif; endif; ?>
          </div>
        </div>

        <div class="ff5-card" style="margin-top:18px">
          <h2>Fila local Client Azure, Fase 5B</h2>
          <p>Esta fila é independente do Azure TWT. <strong>Completed</strong> significa aceite pelo Relay TWT, não altera a fila principal.</p>
          <div class="ff5-table-wrap"><table class="widefat striped ff5-table"><thead><tr><th>ID</th><th>Evento</th><th>Destino UUID</th><th>Estado</th><th>Tentativas</th><th>HTTP</th><th>Erro</th><th>Criado</th></tr></thead><tbody>
          <?php if (!$recentDeliveries): ?><tr><td colspan="8">Sem entregas 5B registadas.</td></tr><?php endif; ?>
          <?php foreach ($recentDeliveries as $job): ?><tr><td><?php echo (int)$job['id']; ?></td><td><code><?php echo esc_html((string)$job['event_name']); ?></code></td><td><code><?php echo esc_html((string)$job['destination_uuid']); ?></code></td><td><?php echo esc_html((string)$job['status']); ?></td><td><?php echo (int)$job['attempts']; ?>/<?php echo (int)$job['max_attempts']; ?></td><td><?php echo (int)($job['last_http_code'] ?? 0) ?: ''; ?></td><td><?php echo esc_html((string)($job['last_error_message'] ?? '')); ?></td><td><?php echo esc_html((string)$job['created_at']); ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </div>

        <div class="ff5-card" style="margin-top:18px">
          <h2>Intake de eventos 5B, diagnóstico</h2>
          <p>Mostra apenas metadados técnicos do evento, nunca respostas de formulários. Serve para perceber se o evento chegou à 5B, com que scope foi resolvido e por que motivo foi ou não enfileirado.</p>
          <div class="ff5-table-wrap"><table class="widefat striped ff5-table"><thead><tr><th>Quando UTC</th><th>Evento</th><th>Scope</th><th>Submission</th><th>Estado</th><th>Motivo</th><th>Routed</th><th>Enqueued</th></tr></thead><tbody>
          <?php if (!$intakeRecent): ?><tr><td colspan="8">Ainda não existem eventos observados pela intake 5B.</td></tr><?php endif; ?>
          <?php foreach ($intakeRecent as $row): ?><tr><td><?php echo esc_html((string)($row['received_at_utc'] ?? '')); ?></td><td><code><?php echo esc_html((string)($row['event_name'] ?? '')); ?></code></td><td>Cliente <?php echo (int)($row['client_id'] ?? 0); ?> / Projeto <?php echo (int)($row['project_id'] ?? 0); ?></td><td><?php echo (int)($row['submission_id'] ?? 0); ?></td><td><?php echo esc_html((string)($row['status'] ?? '')); ?></td><td><code><?php echo esc_html((string)($row['reason'] ?? '')); ?></code></td><td><?php echo (int)($row['routed'] ?? 0); ?></td><td><?php echo (int)($row['enqueued'] ?? 0); ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </div>

        <div class="ff5-card" style="margin-top:18px">
          <h2>Routing Rules</h2>
          <div class="ff5-table-wrap"><table class="widefat striped ff5-table"><thead><tr><th>Destino</th><th>Evento</th><th>Scope</th><th>Ação</th><th>Prioridade</th><th>Estado</th><th>Ações</th></tr></thead><tbody>
          <?php if (!$rules): ?><tr><td colspan="7">Sem regras.</td></tr><?php endif; ?>
          <?php foreach ($rules as $rule): $primaryRule = (string)($rule['type_key'] ?? '') === 'twt_azure'; ?>
          <tr><td><?php echo esc_html((string)$rule['destination_name']); ?></td><td><code><?php echo esc_html((string)$rule['event_pattern']); ?></code></td><td><?php echo esc_html(self::scopeLabel($rule, $clients, $projects)); ?></td><td><?php echo esc_html((string)$rule['action_name']); ?></td><td><?php echo (int)$rule['priority']; ?></td><td><?php echo !empty($rule['is_active']) ? 'Ativa' : 'Inativa'; ?></td><td><div class="ff5-actions">
          <?php if ($primaryRule): ?><span class="ff5-badge ff5-ok">Obrigatória</span><?php else: ?>
          <form method="post"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="toggle_rule"><input type="hidden" name="rule_id" value="<?php echo (int)$rule['id']; ?>"><input type="hidden" name="enable" value="<?php echo !empty($rule['is_active']) ? '0' : '1'; ?>"><button class="button button-small"><?php echo !empty($rule['is_active']) ? 'Desativar' : 'Ativar'; ?></button></form>
          <form method="post" onsubmit="return confirm('Remover esta regra?');"><?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?><input type="hidden" name="ff_destination_action" value="delete_rule"><input type="hidden" name="rule_id" value="<?php echo (int)$rule['id']; ?>"><button class="button button-small">Remover</button></form>
          <?php endif; ?></div></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
        </div>

        <div class="ff5-card" style="margin-top:18px">
          <h2>Nova regra de routing</h2>
          <form method="post" class="ff5-form-grid">
            <?php wp_nonce_field('fieldflow_data_destinations_action', 'fieldflow_data_destinations_nonce'); ?>
            <input type="hidden" name="ff_destination_action" value="create_rule">
            <div><label>Destino externo</label><select name="destination_id" required><option value="">Selecionar</option><?php foreach ($destinations as $destination): if ((string)$destination['type_key'] === 'twt_azure') continue; ?><option value="<?php echo (int)$destination['id']; ?>"><?php echo esc_html((string)$destination['name']); ?></option><?php endforeach; ?></select></div>
            <div><label>Evento</label><input type="text" name="event_pattern" value="*" placeholder="submission.*"></div>
            <div><label>Cliente</label><?php self::clientSelect('rule_client_id', $clients); ?></div>
            <div><label>Projeto / campanha</label><?php self::projectSelect('rule_project_id', $projects); ?></div>
            <div><label>Ação</label><select name="action_name"><option value="include">Include</option><option value="exclude">Exclude</option></select></div>
            <div><label>Prioridade</label><input type="number" min="0" max="9999" name="priority" value="100"></div>
            <div class="full"><label><input type="checkbox" name="rule_is_active" value="1" checked> Regra ativa</label></div>
            <div class="full"><button class="button button-primary">Criar regra</button></div>
          </form>
        </div>
        <?php
        AdminPage::close();
    }

    private static function scopeOptions(): array {
        global $wpdb;
        $clientsTable = $wpdb->prefix . 'routespro_clients';
        $projectsTable = $wpdb->prefix . 'routespro_projects';
        $clients = [];
        $projects = [];
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $clientsTable)) === $clientsTable) {
            $rows = $wpdb->get_results('SELECT id,name FROM ' . $clientsTable . ' ORDER BY name ASC', ARRAY_A) ?: [];
            foreach ($rows as $row) $clients[(int)$row['id']] = (string)$row['name'];
        }
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $projectsTable)) === $projectsTable) {
            $rows = $wpdb->get_results('SELECT id,client_id,name FROM ' . $projectsTable . ' ORDER BY name ASC', ARRAY_A) ?: [];
            foreach ($rows as $row) $projects[(int)$row['id']] = ['name'=>(string)$row['name'],'client_id'=>(int)$row['client_id']];
        }
        return [$clients, $projects];
    }

    private static function clientSelect(string $name, array $clients): void {
        echo '<select name="' . esc_attr($name) . '"><option value="0">Todos / sem scope de cliente</option>';
        foreach ($clients as $id=>$label) echo '<option value="' . (int)$id . '">' . esc_html($label) . '</option>';
        echo '</select>';
    }

    private static function projectSelect(string $name, array $projects): void {
        echo '<select name="' . esc_attr($name) . '"><option value="0">Todos / sem scope de projeto</option>';
        foreach ($projects as $id=>$project) echo '<option value="' . (int)$id . '">' . esc_html((string)$project['name']) . '</option>';
        echo '</select>';
    }

    private static function scopeLabel(array $row, array $clients, array $projects): string {
        $clientId = absint($row['client_id'] ?? 0);
        $projectId = absint($row['project_id'] ?? 0);
        if ($projectId > 0) return ($clients[$clientId] ?? ('Cliente #' . $clientId)) . ' / ' . ($projects[$projectId]['name'] ?? ('Projeto #' . $projectId));
        if ($clientId > 0) return (string)($clients[$clientId] ?? ('Cliente #' . $clientId));
        return 'Tenant completo';
    }
}
