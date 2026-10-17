<?php
namespace RoutesPro\Support;

if (!defined('ABSPATH')) exit;

/**
 * Validates FieldFlow private release ZIPs before they are allowed near Azure.
 *
 * Canonical package contract:
 *   fieldflow/
 *     fieldflow.php
 *     production-manifest.json
 *     ...
 *
 * ZipArchive is preferred but not required. PharData and WordPress PclZip are
 * supported so the Release Manager works on conservative WordPress hosts too.
 */
final class ReleasePackageValidator {
    public const MAX_BYTES = 64 * 1024 * 1024;
    public const MAX_EXPANDED_BYTES = 256 * 1024 * 1024;
    public const MAX_ENTRIES = 12000;
    public const ROOT = 'fieldflow/';
    public const MAIN_FILE = 'fieldflow/fieldflow.php';
    public const MANIFEST_FILE = 'fieldflow/production-manifest.json';

    public static function validate(string $path): array {
        $result = [
            'ok'=>false,
            'path'=>$path,
            'version'=>'',
            'stable_tag'=>'',
            'sha256'=>'',
            'bytes'=>0,
            'entry_count'=>0,
            'file_count'=>0,
            'manifest_file_count'=>0,
            'manifest_version'=>'',
            'manifest_expected_aggregate'=>'',
            'manifest_actual_aggregate'=>'',
            'archive_engine'=>'',
            'offline_runtime_file'=>'',
            'offline_runtime_identity_ok'=>false,
            'offline_runtime_reference_ok'=>false,
            'canonical_offline_runtime_identity_ok'=>false,
            'frontend_build_file'=>'',
            'frontend_build_identity_ok'=>false,
            'frontend_build_reference_ok'=>false,
            'frontend_package_identity_ok'=>false,
            'react_backoffice_file'=>'',
            'react_backoffice_identity_ok'=>false,
            'react_backoffice_reference_ok'=>false,
            'release_continuity_ok'=>false,
            'react_backoffice_foundation_ok'=>false,
            'react_backoffice_shared_ok'=>false,
            'react_analytics_foundation_ok'=>false,
            'react_devices_control_ok'=>false,
            'react_front_file'=>'',
            'react_front_identity_ok'=>false,
            'react_front_reference_ok'=>false,
            'react_front_form_wizard_ok'=>false,
            'react_front_operational_wave_ok'=>false,
            'react_simple_field_controls_ok'=>false,
            'react_choice_special_wave_ok'=>false,
            'modernization_stable_closure_ok'=>false,
            'front_fullwidth_navigation_ok'=>false,
            'react_operational_modules_ok'=>false,
            'operational_action_hardening_ok'=>false,
            'briefing_progress_tabs_hardening_ok'=>false,
            'react_form_completion_ok'=>false,
            'certification_runtime_identity_ok'=>false,
            'release_gate_historical_markers_ok'=>false,
            'front_transition_stability_ok'=>false,
            'mobility_cost_intelligence_ok'=>false,
            'mobility_integrity_canonical_metrics_ok'=>false,
            'route_endpoint_distance_integrity_ok'=>false,
            'route_endpoint_provenance_reconciliation_ok'=>false,
            'route_financial_continuity_ok'=>false,
            'operational_endpoint_source_of_truth_ok'=>false,
            'endpoint_reconciliation_address_reuse_ok'=>false,
            'semantic_edge_endpoint_reconciliation_ok'=>false,
            'owner_profile_route_migration_ok'=>false,
            'endpoint_repair_signature_compatibility_ok'=>false,
            'route_distance_finalization_certification_continuity_ok'=>false,
            'release_gate_runtime_source_authority_ok'=>false,
            'client_portal_shell_stability_ok'=>false,
            'client_portal_continuity_ok'=>false,
            'react_client_wave1_analytics_stability_ok'=>false,
            'react_client_wave2_cross_portal_isolation_ok'=>false,
            'app_resume_dual_portal_fullwidth_ok'=>false,
            'dual_portal_fullwidth_parity_resume_v2_ok'=>false,
            'ui_composition_resume_diagnostics_ok'=>false,
            'shared_portal_paint_stability_ok'=>false,
            'portal_entry_sync_visual_continuity_ok'=>false,
            'mail_dispatch_certification_continuity_ok'=>false,
            'mail_reliability_outbox_ok'=>false,
            'certification_asset_successor_continuity_ok'=>false,
            'runtime_database_performance_ok'=>false,
            'security_storage_hardening_ok'=>false,
            'ai_governance_modernization_ok'=>false,
            'ai_governance_certification_continuity_ok'=>false,
            'operational_modernization_wave_ok'=>false,
            'frontend_runtime_certification_continuity_ok'=>false,
            'operational_modernization_wave2_ok'=>false,
            'field_form_terrain_continuity_ok'=>false,
            'field_session_form_continuity_ok'=>false,
            'certification_gate_continuity_9t_ok'=>false,
            'order_source_cardex_consistency_9u_ok'=>false,
            'canonical_answer_semantics_9v_ok'=>false,
            'visit_closure_daily_mail_9w_ok'=>false,
            'operational_intelligence_hardening_ok'=>false,
            'certification_continuity_visit_closure_9x_ok'=>false,
            'submission_status_operational_day_9y_ok'=>false,
            'submission_corrections_audit_9z_ok'=>false,
            'field_resume_navigation_9aa_ok'=>false,
            'report_selection_media_9ab_ok'=>false,
            'resume_certification_media_9ac_ok'=>false,
            'form_viewport_shopping_delivery_9ad_ok'=>false,
            'canonical_reporting_delivery_9ae_ok'=>false,
            'certification_viewport_mail_9af_ok'=>false,
            'mail_flow_viewport_9ag_ok'=>false,
            'single_anchor_viewport_9ah_ok'=>false,
            'mail_intelligence_daily_reporting_9ai_ok'=>false,
            'briefing_store_scope_reporting_9aj_ok'=>false,
            'daily_mobility_cost_reporting_9ak_ok'=>false,
            'known_mobility_reporting_recovery_9al_ok'=>false,
            'full_route_mobility_reporting_9am_ok'=>false,
            'routing_endpoint_geographic_integrity_9an_ok'=>false,
            'route_mobility_dashboard_widgets_9ao_ok'=>false,
            'report_status_canonical_sync_9ap_ok'=>false,
            'condition_graph_integrity_9aq_ok'=>false,
            'wordpress_media_isolation_9ar_ok'=>false,
            'my_route_periodicity_9as_ok'=>false,
            'my_route_periodicity_action_9at_ok'=>false,
            'release_manager_publication_receipt_9au_ok'=>false,
            'client_cost_date_scope_route_totals_9av_ok'=>false,
            'route_financial_audience_analytics_9aw_ok'=>false,
            'client_portal_runtime_scope_9ax_ok'=>false,
            'client_portal_filter_authority_9ay_ok'=>false,
            'route_calendar_mobile_portal_features_9az_ok'=>false,
            'client_route_hero_date_authority_9ba_ok'=>false,
            'planner_assigned_store_scope_9bb_ok'=>false,
            'planner_canonical_assignment_9bc_ok'=>false,
            'form_visit_memory_fronts_9bd_ok'=>false,
            'planner_free_warning_9be_ok'=>false,
            'visit_exception_justified_nonvisit_9bf_ok'=>false,
            'open_day_planning_visit_completion_9bg_ok'=>false,
            'latest_visit_history_isolation_9bh_ok'=>false,
            'visit_memory_refresh_9bi_ok'=>false,
            'store_identity_authority_9bj_ok'=>false,
            'static_routing_cost_authority_9bk_ok'=>false,
            'toll_cost_intelligence_9bl_ok'=>false,
            'places_geocoding_cost_authority_9bm_ok'=>false,
            'google_cost_control_center_9bn_ok'=>false,
            'daily_operational_priority_9bo_ok'=>false,
            'daily_execution_exception_9bp_ok'=>false,
            'order_mail_visit_reopen_9bq_ok'=>false,
            'certification_foundation_continuity_9br_ok'=>false,
            'executive_analytics_route_financial_9bs_ok'=>false,
            'mobile_analytics_readability_evidence_9bt_ok'=>false,
            'client_reports_runtime_9bu_ok'=>false,
            'portal_export_runtime_cert_parity_9bv_ok'=>false,
            'client_report_scope_briefing_assignment_9bw_ok'=>false,
            'visit_centric_reporting_briefing_9bx_ok'=>false,
            'cardex_data_consistency_9dw_ok'=>false,
            'briefing_steps_continuity_9dx_ok'=>false,
            'typed_offline_core_ok'=>false,
            'typed_draft_media_core_ok'=>false,
            'typed_form_conditional_core_ok'=>false,
            'typed_submission_validation_core_ok'=>false,
            'typed_specialized_validation_core_ok'=>false,
            'submission_application_service_ok'=>false,
            'submission_side_effects_ok'=>false,
            'order_suggestion_mail_eligibility_ok'=>false,
            'fronts_safe_multimodal_9cs_ok'=>false,
            'form_deletion_confirmation_9ct_ok'=>false,
            'fronts_capture_review_9cu_ok'=>false,
            'report_context_recovery_9cv_ok'=>false,
            'fronts_visual_evidence_9cw_ok'=>false,
            'fronts_sku_identity_9cx_ok'=>false,
            'fronts_blind_inventory_9cy_ok'=>false,
            'fronts_ai_cost_metering_9cz_ok'=>false,
            'fronts_budget_reconciliation_9d0_ok'=>false,
            'briefing_category_continuity_9d2_ok'=>false,
            'catalog_category_picker_9d3_ok'=>false,
            'visual_experience_consolidation_9d5_ok'=>false,
            'operative_dock_parity_9d6_ok'=>false,
            'operative_dock_overlay_9d7_ok'=>false,
            'canonical_root'=>false,
            'main_file_found'=>false,
            'manifest_found'=>false,
            'manifest_key_files_integrity_9dh_ok'=>false,
            'manifest_ok'=>false,
            'unexpected_roots'=>[],
            'unsafe_entries'=>[],
            'unexpected_symlinks'=>[],
            'manifest_mismatches'=>[],
            'warnings'=>[],
            'error'=>'',
        ];

        if (!is_file($path) || !is_readable($path)) {
            $result['error'] = 'O ZIP não está disponível para leitura.';
            return $result;
        }
        $bytes = (int)@filesize($path);
        $result['bytes'] = $bytes;
        if ($bytes < 1 || $bytes > self::MAX_BYTES) {
            $result['error'] = 'O ZIP excede o limite de segurança de 64 MB ou está vazio.';
            return $result;
        }
        $sha = strtolower((string)@hash_file('sha256', $path));
        if (!preg_match('/^[a-f0-9]{64}$/', $sha)) {
            $result['error'] = 'Não foi possível calcular o SHA-256 do pacote.';
            return $result;
        }
        $result['sha256'] = $sha;

        try {
            $archive = self::readArchive($path);
        } catch (\Throwable $e) {
            $result['error'] = sanitize_text_field($e->getMessage());
            return $result;
        }
        $result['archive_engine'] = (string)($archive['engine'] ?? '');
        $entries = (array)($archive['entries'] ?? []);
        $result['entry_count'] = count($entries);
        if ($result['entry_count'] < 2 || $result['entry_count'] > self::MAX_ENTRIES) {
            $result['error'] = 'Número de entradas do ZIP fora do limite de segurança.';
            return $result;
        }

        $files = [];
        $roots = [];
        $main = '';
        $manifestRaw = '';
        $expandedBytes = 0;

        foreach ($entries as $entry) {
            if (!is_array($entry)) continue;
            $name = str_replace('\\', '/', (string)($entry['name'] ?? ''));
            if ($name === '') continue;
            $trimmed = ltrim($name, '/');
            $parts = explode('/', $trimmed);
            $root = (string)($parts[0] ?? '');
            if ($root !== '') $roots[$root] = true;

            if ($name !== $trimmed || strpos($trimmed, '../') !== false || strpos($trimmed, '/..') !== false || strpos($trimmed, "\0") !== false) {
                $result['unsafe_entries'][] = $name;
                continue;
            }
            if (!empty($entry['directory']) || substr($trimmed, -1) === '/') continue;
            if (!empty($entry['symlink'])) {
                $result['unexpected_symlinks'][] = $name;
                continue;
            }
            if (!self::isAllowedReleasePath($trimmed)) {
                $result['unsafe_entries'][] = $name;
                continue;
            }
            if (strpos($trimmed, self::ROOT) !== 0) continue;

            $contents = $entry['contents'] ?? null;
            if (!is_string($contents)) {
                $result['manifest_mismatches'][] = $trimmed;
                continue;
            }
            $expandedBytes += strlen($contents);
            if ($expandedBytes > self::MAX_EXPANDED_BYTES) {
                $result['error'] = 'O conteúdo descomprimido do ZIP excede o limite de segurança.';
                return $result;
            }

            if ($trimmed === self::MAIN_FILE) $main = $contents;
            if ($trimmed === self::MANIFEST_FILE) {
                $manifestRaw = $contents;
                continue;
            }

            $relative = substr($trimmed, strlen(self::ROOT));
            if ($relative === '') continue;
            $files[$relative] = [
                'sha256'=>hash('sha256', $contents),
                'bytes'=>strlen($contents),
            ];
        }

        $result['unexpected_roots'] = array_values(array_diff(array_keys($roots), ['fieldflow']));
        $result['canonical_root'] = count($roots) === 1 && isset($roots['fieldflow']) && !$result['unsafe_entries'];
        $result['main_file_found'] = $main !== '';
        $result['manifest_found'] = $manifestRaw !== '';
        $result['file_count'] = count($files);

        if (!$result['canonical_root']) {
            $result['error'] = 'Estrutura inválida: o ZIP deve conter uma única pasta raiz chamada fieldflow/.';
            return $result;
        }
        if (!$result['main_file_found']) {
            $result['error'] = 'Estrutura inválida: fieldflow/fieldflow.php não foi encontrado.';
            return $result;
        }
        if (!$result['manifest_found']) {
            $result['error'] = 'production-manifest.json não foi encontrado dentro de fieldflow/.';
            return $result;
        }
        if ($result['unexpected_symlinks']) {
            $result['error'] = 'O ZIP contém links simbólicos, o que não é permitido numa release.';
            return $result;
        }
        if ($result['unsafe_entries']) {
            $result['error'] = 'O ZIP contém caminhos ou ficheiros não permitidos.';
            return $result;
        }

        if (!preg_match('/^[\s\S]*?Version:\s*([0-9]+\.[0-9]+\.[0-9]+(?:\.[0-9]+)?(?:[-+][A-Za-z0-9.-]+)?)/im', $main, $m)) {
            $result['error'] = 'Não foi possível identificar a versão em fieldflow.php.';
            return $result;
        }
        $result['version'] = sanitize_text_field((string)$m[1]);
        // 9D0B: verify the publisher's remote version contract at validation time.
        if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/', $result['version'])) {
            $result['error'] = 'Versao nao suportada pelo Release Publisher Azure: usar tres segmentos numericos.';
            return $result;
        }
        if (preg_match("/define\\(\\s*['\"]FIELDFLOW_VERSION['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/", $main, $vm)) {
            $constantVersion = sanitize_text_field((string)$vm[1]);
            if ($constantVersion !== '' && !hash_equals($result['version'], $constantVersion)) {
                $result['error'] = 'A versão do header e FIELDFLOW_VERSION não coincidem.';
                return $result;
            }
        }

        $readme = isset($files['readme.txt']) ? self::entryContents($entries, 'fieldflow/readme.txt') : '';
        if ($readme !== '' && preg_match('/^Stable tag:\s*(\S+)/mi', $readme, $sm)) {
            $result['stable_tag'] = sanitize_text_field((string)$sm[1]);
            if ($result['stable_tag'] !== '' && !hash_equals($result['version'], $result['stable_tag'])) {
                $result['error'] = 'Stable tag e versão do plugin não coincidem.';
                return $result;
            }
        }

        // Phase 8C: release continuity has one authority. Historical component
        // gates must not pin SOURCE_RELEASE independently from the stable chain.
        $phase6Raw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $sourceRelease = '';
        $stableRelease = '';
        if ($phase6Raw !== '') {
            if (preg_match("/SOURCE_RELEASE\\s*=\\s*'([^']+)'/", $phase6Raw, $sourceMatch)) $sourceRelease = sanitize_text_field((string)$sourceMatch[1]);
            if (preg_match("/STABLE_RELEASE\\s*=\\s*'([^']+)'/", $phase6Raw, $stableMatch)) $stableRelease = sanitize_text_field((string)$stableMatch[1]);
        }
        $expectedSource = self::previousPatchRelease($result['version']);
        $result['release_continuity_ok'] = $expectedSource !== ''
            && hash_equals($result['version'], $stableRelease)
            && hash_equals($expectedSource, $sourceRelease)
            && version_compare($sourceRelease, $stableRelease, '<');
        if (!$result['release_continuity_ok']) {
            $result['error'] = 'A continuidade N→N+1 da Phase 6 não corresponde à versão do pacote.';
            return $result;
        }

        // Release Manager is the canonical publication gate. Validate the
        // versioned offline runtime identity here so a stale WebView runtime
        // can never be published under a newer plugin version again.
        $runtimeRelative = 'assets/fieldflow-offline-runtime-' . $result['version'] . '.js';
        $runtimeArchive = 'fieldflow/' . $runtimeRelative;
        $runtimeRaw = self::entryContents($entries, $runtimeArchive);
        $responsiveRaw = self::entryContents($entries, 'fieldflow/src/Front/ResponsiveAssets.php');
        $result['offline_runtime_file'] = $runtimeRelative;
        $result['offline_runtime_identity_ok'] = $runtimeRaw !== ''
            && strpos($runtimeRaw, "const RUNTIME_BUILD='" . $result['version'] . "'") !== false
            && strpos($runtimeRaw, '__runtimeBuild:RUNTIME_BUILD') !== false;
        $result['offline_runtime_reference_ok'] = $responsiveRaw !== ''
            && strpos($responsiveRaw, basename($runtimeRelative)) !== false;
        if (!$result['offline_runtime_identity_ok']) {
            $result['error'] = 'O runtime offline versionado não declara a mesma identidade da release.';
            return $result;
        }
        if (!$result['offline_runtime_reference_ok']) {
            $result['error'] = 'ResponsiveAssets não referencia o runtime offline versionado da release.';
            return $result;
        }

        // The structured frontend build is compiled from TypeScript at release time.
        // Bind the compiled browser asset to the exact plugin version so Release
        // Manager cannot publish a stale bundle under a newer PHP package.
        $frontendRelative = 'assets/fieldflow-frontend-core-' . $result['version'] . '.js';
        $frontendArchive = 'fieldflow/' . $frontendRelative;
        $frontendRaw = self::entryContents($entries, $frontendArchive);
        $frontendPackageRaw = self::entryContents($entries, 'fieldflow/frontend/package.json');
        $result['frontend_build_file'] = $frontendRelative;
        $result['frontend_package_identity_ok'] = $frontendPackageRaw !== ''
            && strpos($frontendPackageRaw, '"name": "fieldflow-frontend"') !== false
            && strpos($frontendPackageRaw, '"version": "' . $result['version'] . '"') !== false;
        if (!$result['frontend_package_identity_ok']) {
            $result['error'] = 'frontend/package.json não declara a mesma identidade da release.';
            return $result;
        }
        $result['frontend_build_identity_ok'] = $frontendRaw !== ''
            && $result['frontend_package_identity_ok']
            && strpos($frontendRaw, 'FieldFlow ' . $result['version'] . ' | TypeScript frontend foundation') !== false
            && strpos($frontendRaw, "FIELD_FLOW_FRONTEND_BUILD_VERSION = '" . $result['version'] . "'") !== false
            && strpos($frontendRaw, 'fieldflow-frontend-build-foundation-v1') !== false;
        $result['frontend_build_reference_ok'] = $responsiveRaw !== ''
            && strpos($responsiveRaw, basename($frontendRelative)) !== false
            && strpos($responsiveRaw, "FRONTEND_CORE_SCRIPT_HANDLE = 'fieldflow-frontend-core'") !== false;
        if (!$result['frontend_build_identity_ok']) {
            $result['error'] = 'O bundle frontend TypeScript não declara a mesma identidade da release.';
            return $result;
        }
        if (!$result['frontend_build_reference_ok']) {
            $result['error'] = 'ResponsiveAssets não referencia o bundle frontend TypeScript versionado da release.';
            return $result;
        }

        // Phase 8A: React is loaded only as a progressive admin island using
        // WordPress' bundled wp-element runtime. The PHP fallback must remain
        // present so a React failure never makes the control center unusable.
        $reactRelative = 'assets/fieldflow-react-backoffice-' . $result['version'] . '.js';
        $reactRaw = self::entryContents($entries, 'fieldflow/' . $reactRelative);
        $reactCssRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-react-backoffice.css');
        $reactFoundationRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReactBackofficeFoundation.php');
        $mobileControlRaw = self::entryContents($entries, 'fieldflow/src/Admin/MobileControlCenter.php');
        $reactVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-react-backoffice-foundation.php');
        $reactDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8A-REACT-BACKOFFICE-FOUNDATION.md');
        // frontend/package.json was loaded with the TypeScript bundle identity checks above.
        $loaderForReactRaw = self::entryContents($entries, 'fieldflow/src/Support/Loader.php');
        $result['react_backoffice_file'] = $reactRelative;
        $result['react_backoffice_identity_ok'] = $reactRaw !== ''
            && strpos($reactRaw, 'FieldFlow ' . $result['version'] . ' | React Backoffice Foundation') !== false
            && strpos($reactRaw, "FIELD_FLOW_FRONTEND_BUILD_VERSION = '" . $result['version'] . "'") !== false
            && strpos($reactRaw, 'fieldflow-react-backoffice-foundation-v1') !== false
            && strpos($reactRaw, 'mobile-release-status') !== false
            && strpos($reactRaw, 'wordpress-wp-element') !== false;
        $result['react_backoffice_reference_ok'] = $responsiveRaw !== ''
            && strpos($responsiveRaw, basename($reactRelative)) !== false
            && strpos($responsiveRaw, "[self::FRONTEND_CORE_SCRIPT_HANDLE, 'wp-element']") !== false
            && strpos($responsiveRaw, 'ReactBackofficeFoundation::pageEnabled()') !== false;
        $result['react_backoffice_foundation_ok'] = $result['react_backoffice_identity_ok']
            && $result['react_backoffice_reference_ok']
            && $reactCssRaw !== ''
            && strpos($reactCssRaw, 'ff-react-mobile-release-status') !== false
            && $reactFoundationRaw !== ''
            && strpos($reactFoundationRaw, "CONTRACT = 'fieldflow-react-backoffice-foundation-v1'") !== false
            && strpos($reactFoundationRaw, "PILOT_PAGE = 'routespro-mobile'") !== false
            && strpos($reactFoundationRaw, 'FIELDFLOW_REACT_BACKOFFICE') !== false
            && strpos($mobileControlRaw, 'data-fieldflow-react-island="mobile-release-status"') !== false
            && strpos($mobileControlRaw, 'publication_status_island') !== false
            && strpos($loaderForReactRaw, 'src/Admin/ReactBackofficeFoundation.php') !== false
            && $reactVerifierRaw !== ''
            && strpos($reactVerifierRaw, 'REACT BACKOFFICE FOUNDATION CONTRACT OK') !== false
            && $reactDocRaw !== ''
            && strpos($reactDocRaw, 'PHP fallback') !== false
            && strpos($frontendPackageRaw, '"react"') === false
            && strpos($frontendPackageRaw, '"react-dom"') === false;
        if (!$result['react_backoffice_foundation_ok']) {
            $result['error'] = 'A React Backoffice Foundation não está integralmente compilada, ligada ao wp-element e protegida por fallback PHP.';
            return $result;
        }

        // Phase 8B: Devices becomes the first operational React control surface.
        // The same DeviceManager must power both the React island and the full
        // server-rendered PHP fallback, and mutating commands stay audited.
        $devicesAdminRaw = self::entryContents($entries, 'fieldflow/src/Admin/Devices.php');
        $deviceManagerRaw = self::entryContents($entries, 'fieldflow/src/Mobile/DeviceManager.php');
        $reactDevicesVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-react-control-center-devices.php');
        $reactDevicesDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8B-REACT-CONTROL-CENTER-DEVICES.md');
        $reactTestsRaw = self::entryContents($entries, 'fieldflow/frontend/tests/react-backoffice.test.mjs');
        $reactSharedSourceRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-backoffice/shared.ts');
        $result['react_devices_control_ok'] = $result['react_backoffice_foundation_ok']
            && strpos($reactRaw, 'device-control-table') !== false
            && strpos($reactRaw, 'fieldflow_react_devices_list') !== false
            && strpos($reactRaw, 'fieldflow_react_device_action') !== false
            && $reactSharedSourceRaw !== ''
            && strpos($reactSharedSourceRaw, "credentials: 'same-origin'") !== false
            && strpos($reactSharedSourceRaw, 'fieldFlowReactPost') !== false
            && strpos($reactCssRaw, 'ff-react-device-control') !== false
            && strpos($reactCssRaw, 'ff-react-device-pagination') !== false
            && strpos($reactFoundationRaw, "DEVICES_PAGE = 'routespro-devices'") !== false
            && strpos($reactFoundationRaw, "DEVICES_ISLAND = 'device-control-table'") !== false
            && $devicesAdminRaw !== ''
            && strpos($devicesAdminRaw, 'data-fieldflow-react-island="device-control-table"') !== false
            && strpos($devicesAdminRaw, 'DeviceManager::adminListPayload($listing)') !== false
            && strpos($devicesAdminRaw, '<table class="widefat striped">') !== false
            && $deviceManagerRaw !== ''
            && strpos($deviceManagerRaw, 'wp_ajax_fieldflow_react_devices_list') !== false
            && strpos($deviceManagerRaw, 'wp_ajax_fieldflow_react_device_action') !== false
            && strpos($deviceManagerRaw, 'performAdminAction') !== false
            && strpos($deviceManagerRaw, "check_ajax_referer('fieldflow_react_devices','nonce')") !== false
            && strpos($deviceManagerRaw, "AuditLog::record('device.'.\$action") !== false
            && strpos($deviceManagerRaw, "EventBus::emit('device.'.\$action") !== false
            && $reactTestsRaw !== ''
            && strpos($reactTestsRaw, 'React device control island is compiled with async list/action contracts') !== false
            && $reactDevicesVerifierRaw !== ''
            && strpos($reactDevicesVerifierRaw, 'REACT CONTROL CENTER DEVICES CONTRACT OK') !== false
            && $reactDevicesDocRaw !== ''
            && strpos($reactDevicesDocRaw, 'fieldflow-react-control-center-devices-v1') !== false
            && strpos($reactDevicesDocRaw, 'PHP fallback') !== false;
        if (!$result['react_devices_control_ok']) {
            $result['error'] = 'O React Control Center Devices não está integralmente ligado à release.';
            return $result;
        }

        // Phase 8C: shared React BO infrastructure and Analytics progressive island.
        $assignmentsRaw = self::entryContents($entries, 'fieldflow/src/Admin/Assignments.php');
        $analyticsAdminRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-analytics2-admin.js');
        $reactAnalyticsVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-react-analytics-foundation.php');
        $reactContinuityVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-release-continuity-authority.php');
        $reactAnalyticsDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8C-RELEASE-CONTINUITY-REACT-ANALYTICS.md');
        $result['react_backoffice_shared_ok'] = $result['react_backoffice_foundation_ok']
            && strpos($reactRaw, 'fieldflow-react-backoffice-shared-v1') !== false
            && $reactSharedSourceRaw !== ''
            && strpos($reactSharedSourceRaw, 'fieldFlowReactPost') !== false
            && strpos($reactSharedSourceRaw, 'fieldFlowReactTodayRange') !== false
            && strpos($reactFoundationRaw, "SHARED_CONTRACT = 'fieldflow-react-backoffice-shared-v1'") !== false;
        if (!$result['react_backoffice_shared_ok']) {
            $result['error'] = 'A infraestrutura React BO partilhada não está integralmente compilada e ligada à release.';
            return $result;
        }
        $result['react_analytics_foundation_ok'] = $result['react_backoffice_shared_ok']
            && strpos($reactRaw, 'fieldflow-react-analytics-foundation-v1') !== false
            && strpos($reactRaw, 'analytics-context-kpis') !== false
            && strpos($reactRaw, 'FieldFlowAnalytics2') !== false
            && strpos($reactRaw, 'fieldflow:analytics2-data') !== false
            && strpos($reactRaw, 'fieldflow:analytics2-state') !== false
            && strpos($reactCssRaw, 'ff-react-analytics-control') !== false
            && strpos($reactFoundationRaw, "ANALYTICS_PAGE = 'routespro-assignments-hub'") !== false
            && strpos($reactFoundationRaw, "ANALYTICS_ISLAND = 'analytics-context-kpis'") !== false
            && $assignmentsRaw !== ''
            && strpos($assignmentsRaw, 'data-fieldflow-react-island="analytics-context-kpis"') !== false
            && strpos($assignmentsRaw, 'ff-a2-legacy-control-fallback') !== false
            && $analyticsAdminRaw !== ''
            && strpos($analyticsAdminRaw, 'window.FieldFlowAnalytics2') !== false
            && strpos($analyticsAdminRaw, 'fieldflow:analytics2-data') !== false
            && strpos($analyticsAdminRaw, 'history.replaceState') !== false
            && $reactAnalyticsVerifierRaw !== ''
            && strpos($reactAnalyticsVerifierRaw, 'REACT ANALYTICS FOUNDATION CONTRACT OK') !== false
            && $reactContinuityVerifierRaw !== ''
            && strpos($reactContinuityVerifierRaw, 'RELEASE CONTINUITY AUTHORITY CONTRACT OK') !== false
            && $reactAnalyticsDocRaw !== ''
            && strpos($reactAnalyticsDocRaw, 'fieldflow-react-analytics-foundation-v1') !== false
            && strpos($reactAnalyticsDocRaw, 'single continuity authority') !== false;
        if (!$result['react_analytics_foundation_ok']) {
            $result['error'] = 'A React Analytics Foundation não está integralmente ligada ao Analytics 2.0 com fallback PHP.';
            return $result;
        }


        $formRendererRaw = self::entryContents($entries, 'fieldflow/src/Forms/FormRenderer.php');

        // Phase 8D: operational front React starts with a presentation-only
        // form wizard progress island. Submission, validation, media and
        // offline ownership remain with the certified runtimes.
        $reactFrontRelative = 'assets/fieldflow-react-front-' . $result['version'] . '.js';
        $reactFrontRaw = self::entryContents($entries, 'fieldflow/' . $reactFrontRelative);
        $reactFrontCssRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-react-front.css');
        $reactFrontFoundationRaw = self::entryContents($entries, 'fieldflow/src/Front/ReactFrontFoundation.php');
        $reactFrontVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-react-front-form-wizard.php');
        $reactFrontDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8D-REACT-FRONT-FORM-WIZARD.md');
        $reactFrontTestsRaw = self::entryContents($entries, 'fieldflow/frontend/tests/react-front.test.mjs');
        $result['react_front_file'] = $reactFrontRelative;
        $result['react_front_identity_ok'] = $reactFrontRaw !== ''
            && strpos($reactFrontRaw, 'FieldFlow ' . $result['version'] . ' | React Front Foundation') !== false
            && strpos($reactFrontRaw, 'fieldflow-react-front-foundation-v1') !== false
            && strpos($reactFrontRaw, 'fieldflow-react-form-wizard-progress-v1') !== false
            && strpos($reactFrontRaw, 'form-wizard-progress') !== false
            && strpos($reactFrontRaw, 'wordpress-wp-element') !== false;
        $result['react_front_reference_ok'] = $responsiveRaw !== ''
            && strpos($responsiveRaw, basename($reactFrontRelative)) !== false
            && strpos($responsiveRaw, "[self::FRONTEND_CORE_SCRIPT_HANDLE, 'wp-element']") !== false
            && strpos($responsiveRaw, 'ReactFrontFoundation::FORM_WIZARD_ISLAND') !== false;
        $result['react_front_form_wizard_ok'] = $result['react_front_identity_ok']
            && $result['react_front_reference_ok']
            && $reactFrontCssRaw !== ''
            && strpos($reactFrontCssRaw, 'ff-react-front-form-progress') !== false
            && $reactFrontFoundationRaw !== ''
            && strpos($reactFrontFoundationRaw, "CONTRACT = 'fieldflow-react-front-foundation-v1'") !== false
            && strpos($reactFrontFoundationRaw, "FORM_WIZARD_ISLAND = 'form-wizard-progress'") !== false
            && strpos($reactFrontFoundationRaw, 'FIELDFLOW_REACT_FRONT') !== false
            && strpos($formRendererRaw, 'data-fieldflow-react-front-island="form-wizard-progress"') !== false
            && strpos($formRendererRaw, 'data-fieldflow-react-front-fallback="form-wizard-progress"') !== false
            && strpos($formRendererRaw, 'routespro-form-progress-fill') !== false
            && $reactFrontTestsRaw !== ''
            && strpos($reactFrontTestsRaw, 'React form wizard progress mounts progressively without owning submission') !== false
            && $reactFrontVerifierRaw !== ''
            && strpos($reactFrontVerifierRaw, 'REACT FRONT FORM WIZARD CONTRACT OK') !== false
            && $reactFrontDocRaw !== ''
            && strpos($reactFrontDocRaw, 'PHP fallback') !== false
            && strpos($reactFrontDocRaw, 'No IndexedDB schema change') !== false
            && strpos($frontendPackageRaw, '"react"') === false
            && strpos($frontendPackageRaw, '"react-dom"') === false;
        if (!$result['react_front_form_wizard_ok']) {
            $result['error'] = 'A React Front Foundation do formulário não está integralmente ligada com fallback PHP e ownership operacional seguro.';
            return $result;
        }

        // Phase 8E: expand the progressive React front into the operational
        // shell, Minha Rota toolbar and simple field assist states. Existing
        // route/form runtimes remain canonical and all fallbacks stay present.
        $reactFrontWaveVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-react-front-operational-wave.php');
        $reactFrontWaveDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8E-REACT-FRONT-OPERATIONAL-WAVE.md');
        $reactFrontTypesRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/types.ts');
        $reactFrontDispatchRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/form-wizard.ts');
        $reactFrontShellRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/operational-shell.ts');
        $reactFrontRouteRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/route-toolbar.ts');
        $reactFrontFieldsRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/simple-fields.ts');
        $reactFrontBootstrapRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/bootstrap.ts');
        $appShortcodeRaw = self::entryContents($entries, 'fieldflow/src/Front/AppShortcodeRenderer.php');
        $shortcodesRaw = self::entryContents($entries, 'fieldflow/src/Shortcodes.php');
        $result['react_front_operational_wave_ok'] = $result['react_front_form_wizard_ok']
            && strpos($reactFrontRaw, 'fieldflow-react-operational-shell-v1') !== false
            && strpos($reactFrontRaw, 'fieldflow-react-my-route-toolbar-v1') !== false
            && strpos($reactFrontRaw, 'fieldflow-react-simple-field-assist-v1') !== false
            && strpos($reactFrontCssRaw, 'ff-react-front-operational-shell') !== false
            && strpos($reactFrontCssRaw, 'ff-react-front-route-toolbar') !== false
            && strpos($reactFrontCssRaw, 'ff-react-front-field-assist') !== false
            && strpos($reactFrontFoundationRaw, "OPERATIONAL_SHELL_CONTRACT = 'fieldflow-react-operational-shell-v1'") !== false
            && strpos($reactFrontFoundationRaw, "MY_ROUTE_CONTRACT = 'fieldflow-react-my-route-toolbar-v1'") !== false
            && strpos($reactFrontFoundationRaw, "SIMPLE_FIELD_CONTRACT = 'fieldflow-react-simple-field-assist-v1'") !== false
            && strpos($reactFrontFoundationRaw, 'simpleFieldTypeEnabled') !== false
            && $reactFrontTypesRaw !== ''
            && strpos($reactFrontTypesRaw, 'FieldFlowOperationalShellProps') !== false
            && strpos($reactFrontTypesRaw, 'FieldFlowMyRouteToolbarProps') !== false
            && strpos($reactFrontTypesRaw, 'FieldFlowSimpleFieldProps') !== false
            && $reactFrontDispatchRaw !== ''
            && strpos($reactFrontDispatchRaw, 'FieldFlowOperationalShellIsland') !== false
            && strpos($reactFrontDispatchRaw, 'FieldFlowMyRouteToolbarIsland') !== false
            && strpos($reactFrontDispatchRaw, 'FieldFlowSimpleFieldAssistIsland') !== false
            && $reactFrontShellRaw !== ''
            && strpos($reactFrontShellRaw, 'navigator.onLine') !== false
            && $reactFrontRouteRaw !== ''
            && strpos($reactFrontRouteRaw, '#rp-date') !== false
            && strpos($reactFrontRouteRaw, '#rp-route-select') !== false
            && strpos($reactFrontRouteRaw, "dispatchEvent(new Event('change'") !== false
            && $reactFrontFieldsRaw !== ''
            && strpos($reactFrontFieldsRaw, 'fieldFlowSimpleFieldInput') !== false
            && $reactFrontBootstrapRaw !== ''
            && strpos($reactFrontBootstrapRaw, 'fieldflow:lazy-panel-loaded') !== false
            && strpos($reactFrontBootstrapRaw, 'fieldflow:form-rendered') !== false
            && strpos($appShortcodeRaw, 'data-fieldflow-react-front-island="operational-shell"') !== false
            && strpos($appShortcodeRaw, 'data-fieldflow-react-front-fallback="operational-shell"') !== false
            && strpos($shortcodesRaw, 'data-fieldflow-react-front-island="my-route-toolbar"') !== false
            && strpos($shortcodesRaw, 'data-fieldflow-react-front-fallback="my-route-toolbar"') !== false
            && strpos($formRendererRaw, 'data-fieldflow-react-front-island="simple-field-assist"') !== false
            && $reactFrontTestsRaw !== ''
            && strpos($reactFrontTestsRaw, 'React operational front wave compiles shell, route toolbar and simple-field assist contracts') !== false
            && $reactFrontWaveVerifierRaw !== ''
            && strpos($reactFrontWaveVerifierRaw, 'REACT FRONT OPERATIONAL WAVE CONTRACT OK') !== false
            && $reactFrontWaveDocRaw !== ''
            && strpos($reactFrontWaveDocRaw, 'fieldflow-react-front-operational-wave-v1') !== false
            && strpos($reactFrontWaveDocRaw, 'PHP fallback') !== false
            && strpos($reactFrontWaveDocRaw, 'No IndexedDB schema change') !== false;
        if (!$result['react_front_operational_wave_ok']) {
            $result['error'] = 'A React Front Operational Wave não está integralmente ligada com fallback PHP e ownership seguro.';
            return $result;
        }


        // Phase 8F: simple form fields now render their visible controls in
        // React while the original native input remains the canonical value,
        // validation, offline and submission source of truth.
        $reactSimpleControlsVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-react-form-simple-controls.php');
        $reactSimpleControlsDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8F-REACT-FORM-SIMPLE-CONTROLS.md');
        $result['react_simple_field_controls_ok'] = $result['react_front_operational_wave_ok']
            && strpos($reactFrontRaw, 'fieldflow-react-simple-field-control-v1') !== false
            && strpos($reactFrontFoundationRaw, "SIMPLE_FIELD_CONTROL_CONTRACT = 'fieldflow-react-simple-field-control-v1'") !== false
            && strpos($responsiveRaw, 'ReactFrontFoundation::SIMPLE_FIELD_CONTROL_CONTRACT') !== false
            && strpos($formRendererRaw, "'controlContract' => \\RoutesPro\\Front\\ReactFrontFoundation::SIMPLE_FIELD_CONTROL_CONTRACT") !== false
            && strpos($formRendererRaw, "'unit' => \$unit") !== false
            && strpos($reactFrontTypesRaw, 'FieldFlowSimpleFieldOption') !== false
            && strpos($reactFrontTypesRaw, 'controlContract?: string') !== false
            && strpos($reactFrontFieldsRaw, 'fieldFlowSimpleFieldWrite') !== false
            && strpos($reactFrontFieldsRaw, 'data-fieldflow-react-simple-native') !== false
            && strpos($reactFrontFieldsRaw, 'data-fieldflow-react-simple-control-input') !== false
            && strpos($reactFrontFieldsRaw, "dispatchEvent(new Event('input'") !== false
            && strpos($reactFrontFieldsRaw, "dispatchEvent(new Event('change'") !== false
            && strpos($reactFrontFieldsRaw, 'redirectInvalid') !== false
            && strpos($reactFrontCssRaw, 'ff-react-front-simple-control') !== false
            && strpos($reactFrontCssRaw, 'data-fieldflow-react-simple-native') !== false
            && strpos($reactFrontTestsRaw, 'React simple field control owns visible UI while canonical input owns value and submit semantics') !== false
            && $reactSimpleControlsVerifierRaw !== ''
            && strpos($reactSimpleControlsVerifierRaw, 'REACT FORM SIMPLE CONTROLS CONTRACT OK') !== false
            && $reactSimpleControlsDocRaw !== ''
            && strpos($reactSimpleControlsDocRaw, 'fieldflow-react-simple-field-control-v1') !== false
            && strpos($reactSimpleControlsDocRaw, 'canonical native input') !== false
            && strpos($reactSimpleControlsDocRaw, 'No IndexedDB schema change') !== false;
        if (!$result['react_simple_field_controls_ok']) {
            $result['error'] = 'Os controlos simples React do formulário não estão integralmente ligados ao input canónico com fallback e ownership seguro.';
            return $result;
        }

        // Phase 8G: React controls own choice presentation while native
        // inputs keep canonical conditional/validation/offline semantics. Advanced
        // form blocks receive React status/actions without duplicating business logic.
        $reactChoiceSpecialVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-react-form-choice-special-wave.php');
        $reactChoiceSpecialDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8G-REACT-FORM-CHOICE-SPECIAL-WAVE.md');
        $reactChoiceSourceRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/choice-fields.ts');
        $reactSpecialSourceRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/special-fields.ts');
        $result['react_choice_special_wave_ok'] = $result['react_simple_field_controls_ok']
            && strpos($reactFrontRaw, 'fieldflow-react-choice-field-control-v1') !== false
            && strpos($reactFrontRaw, 'fieldflow-react-special-field-assist-v1') !== false
            && strpos($reactFrontFoundationRaw, "CHOICE_FIELD_CONTRACT = 'fieldflow-react-choice-field-control-v1'") !== false
            && strpos($reactFrontFoundationRaw, "SPECIAL_FIELD_CONTRACT = 'fieldflow-react-special-field-assist-v1'") !== false
            && strpos($responsiveRaw, 'ReactFrontFoundation::CHOICE_FIELD_CONTRACT') !== false
            && strpos($responsiveRaw, 'ReactFrontFoundation::SPECIAL_FIELD_CONTRACT') !== false
            && strpos($formRendererRaw, 'data-fieldflow-react-front-island="choice-field-control"') !== false
            && strpos($formRendererRaw, 'data-fieldflow-react-front-island="special-field-assist"') !== false
            && strpos($formRendererRaw, 'ProductCardex::question_rows') !== false
            && strpos($formRendererRaw, 'ProductCardex::order_question_rows') !== false
            && $reactChoiceSourceRaw !== ''
            && strpos($reactChoiceSourceRaw, 'fieldFlowChoiceWrite') !== false
            && strpos($reactChoiceSourceRaw, "dispatchEvent(new Event('change'") !== false
            && strpos($reactChoiceSourceRaw, 'data-fieldflow-react-choice-native') !== false
            && $reactSpecialSourceRaw !== ''
            && strpos($reactSpecialSourceRaw, 'data-share-space-card') !== false
            && strpos($reactSpecialSourceRaw, 'data-fronts-input') !== false
            && strpos($reactSpecialSourceRaw, 'data-order-qty') !== false
            && strpos($reactSpecialSourceRaw, 'data-category-open-all') !== false
            && strpos($reactFrontCssRaw, 'ff-react-choice-control') !== false
            && strpos($reactFrontCssRaw, 'ff-react-special-field') !== false
            && strpos($reactFrontTestsRaw, 'React choice control writes the canonical radio group so conditional listeners keep ownership') !== false
            && $reactChoiceSpecialVerifierRaw !== ''
            && strpos($reactChoiceSpecialVerifierRaw, 'REACT FORM CHOICE SPECIAL WAVE CONTRACT OK') !== false
            && $reactChoiceSpecialDocRaw !== ''
            && strpos($reactChoiceSpecialDocRaw, 'ProductCardex remains canonical') !== false
            && strpos($reactChoiceSpecialDocRaw, 'No IndexedDB schema change') !== false;
        if (!$result['react_choice_special_wave_ok']) {
            $result['error'] = 'A React Choice & Special Form Wave não está integralmente ligada ao runtime canónico com fallback e ownership seguro.';
            return $result;
        }

        // Phase 8H: final modernization closure. React owns the visible media
        // capture surface only by proxying the certified native controls;
        // IndexedDB, recovery, media transport and submission remain canonical.
        $closureVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-modernization-stable-closure.php');
        $closureDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8H-MODERNIZATION-STABLE-CLOSURE.md');
        $result['modernization_stable_closure_ok'] = $result['react_choice_special_wave_ok']
            && strpos($reactFrontFoundationRaw, "MODERNIZATION_CLOSURE_CONTRACT = 'fieldflow-modernization-stable-closure-v1'") !== false
            && strpos($reactFrontRaw, 'fieldflow-modernization-stable-closure-v1') !== false
            && strpos($reactFrontRaw, 'fieldflow-react-media-control-v1') !== false
            && strpos($reactFrontFoundationRaw, "MEDIA_CONTROL_CONTRACT = 'fieldflow-react-media-control-v1'") !== false
            && strpos($formRendererRaw, 'ReactFrontFoundation::MEDIA_CONTROL_CONTRACT') !== false
            && strpos($reactSpecialSourceRaw, 'fieldFlowMediaClick') !== false
            && strpos($reactSpecialSourceRaw, 'data-fieldflow-react-media-native') !== false
            && strpos($reactSpecialSourceRaw, 'fieldflow:offline-form-synced') !== false
            && strpos($reactSpecialSourceRaw, 'fieldflow:sync-completed') !== false
            && strpos($reactFrontCssRaw, 'ff-react-media-control') !== false
            && strpos($reactFrontCssRaw, 'data-fieldflow-react-media-native') !== false
            && strpos($reactFrontTestsRaw, 'React media surface proxies camera action to certified native upload trigger') !== false
            && $closureVerifierRaw !== ''
            && strpos($closureVerifierRaw, 'MODERNIZATION STABLE CLOSURE CONTRACT OK') !== false
            && $closureDocRaw !== ''
            && strpos($closureDocRaw, 'fieldflow-modernization-stable-closure-v1') !== false
            && strpos($closureDocRaw, 'Native media transport remains canonical') !== false
            && strpos($closureDocRaw, 'No IndexedDB schema change') !== false
            && strpos($frontendPackageRaw, '"react"') === false
            && strpos($frontendPackageRaw, '"react-dom"') === false;
        if (!$result['modernization_stable_closure_ok']) {
            $result['error'] = 'O Modernization Stable Closure não está integralmente ligado com ownership seguro de media e continuidade dos contratos React.';
            return $result;
        }

        // Phase 8I: presentation-only consolidation. The package must expose the
        // complete authorised operational navigation and shared full-width mobile
        // surfaces without changing the canonical offline/submission ownership.
        $fullwidthCssRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-operational-experience.css');
        $briefingsRendererRaw = self::entryContents($entries, 'fieldflow/src/Front/BriefingsShortcodeRenderer.php');
        $implementationsRendererRaw = self::entryContents($entries, 'fieldflow/src/Front/ImplementationsShortcodeRenderer.php');
        $stocksRendererRaw = self::entryContents($entries, 'fieldflow/src/Front/StocksShortcodeRenderer.php');
        $shoppingRendererRaw = self::entryContents($entries, 'fieldflow/src/Front/ShoppingShortcodeRenderer.php');
        $plannerRendererRaw = self::entryContents($entries, 'fieldflow/src/Front/SelfRoutePlannerShortcodeRenderer.php');
        $expensesRendererRaw = self::entryContents($entries, 'fieldflow/src/Front/ExpensesShortcodeRenderer.php');
        $messagesRendererRaw = self::entryContents($entries, 'fieldflow/src/Front/TeamInboxShortcodeRenderer.php');
        $fullwidthVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-front-fullwidth-navigation.php');
        $fullwidthDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8I-FRONT-FULL-WIDTH-NAVIGATION-CONSOLIDATION.md');
        $result['front_fullwidth_navigation_ok'] = $result['modernization_stable_closure_ok']
            && strpos($reactFrontFoundationRaw, "FULLWIDTH_NAVIGATION_CONTRACT = 'fieldflow-front-fullwidth-navigation-v1'") !== false
            && strpos($appShortcodeRaw, "['key'=>'implementations','label'=>'Implementações']") !== false
            && strpos($appShortcodeRaw, "['key'=>'stocks','label'=>'Stocks']") !== false
            && strpos($appShortcodeRaw, "['key'=>'shopping','label'=>'Shopping']") !== false
            && strpos($appShortcodeRaw, "['key'=>'expenses','label'=>'Despesas']") !== false
            && strpos($appShortcodeRaw, "['key'=>'messages','label'=>'Mensagens']") !== false
            && strpos($reactFrontShellRaw, '.slice(0, 5)') === false
            && strpos($reactFrontShellRaw, 'button[data-panel=') !== false
            && strpos($reactFrontShellRaw, "'aria-current'") !== false
            && strpos($fullwidthCssRaw, 'fieldflow-front-fullwidth-navigation-v1') !== false
            && strpos($fullwidthCssRaw, '.ff-operational-surface') !== false
            && strpos($fullwidthCssRaw, 'data-panel="briefings"') !== false
            && strpos($fullwidthCssRaw, 'data-panel="messages"') !== false
            && strpos($briefingsRendererRaw, 'ff-operational-surface') !== false
            && strpos($implementationsRendererRaw, 'ff-operational-surface') !== false
            && strpos($stocksRendererRaw, 'ff-operational-surface') !== false
            && strpos($shoppingRendererRaw, 'ff-operational-surface') !== false
            && strpos($plannerRendererRaw, 'ff-operational-surface') !== false
            && strpos($expensesRendererRaw, 'ff-operational-surface') !== false
            && strpos($messagesRendererRaw, 'ff-operational-surface') !== false
            && $fullwidthVerifierRaw !== ''
            && strpos($fullwidthVerifierRaw, 'FRONT FULL WIDTH NAVIGATION CONTRACT OK') !== false
            && $fullwidthDocRaw !== ''
            && strpos($fullwidthDocRaw, 'No IndexedDB schema change.') !== false
            && strpos($fullwidthDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false;
        if (!$result['front_fullwidth_navigation_ok']) {
            $result['error'] = 'O Front Full Width & Navigation Consolidation não está integralmente ligado ao shell React e às superfícies mobile sem alterar o core certificado.';
            return $result;
        }

        // Phase 8J: shared React operational modules. React presents and
        // coordinates controls, but every functional change is proxied through
        // the existing native controls and their certified runtimes.
        $operationalModulesSourceRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/module-controls.ts');
        $operationalModulesVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-react-operational-modules.php');
        $operationalModulesDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8J-REACT-OPERATIONAL-MODULES.md');
        $submissionPipelineRaw = self::entryContents($entries, 'fieldflow/src/Forms/SubmissionPipeline.php');
        $result['react_operational_modules_ok'] = $result['front_fullwidth_navigation_ok']
            && strpos($reactFrontFoundationRaw, "OPERATIONAL_MODULES_CONTRACT = 'fieldflow-react-operational-modules-v1'") !== false
            && strpos($reactFrontFoundationRaw, "OPERATIONAL_MODULES_ISLAND = 'operational-module-controls'") !== false
            && strpos($reactFrontFoundationRaw, 'operationalModuleMount') !== false
            && strpos($responsiveRaw, 'ReactFrontFoundation::OPERATIONAL_MODULES_CONTRACT') !== false
            && strpos($responsiveRaw, 'ReactFrontFoundation::OPERATIONAL_MODULES_ISLAND') !== false
            && $operationalModulesSourceRaw !== ''
            && strpos($operationalModulesSourceRaw, 'fieldflow-react-operational-modules-v1') !== false
            && strpos($operationalModulesSourceRaw, 'fieldFlowOperationalModuleWrite') !== false
            && strpos($operationalModulesSourceRaw, 'fieldFlowOperationalModuleClickAction') !== false
            && strpos($operationalModulesSourceRaw, 'data-fieldflow-react-operational-native') !== false
            && strpos($operationalModulesSourceRaw, 'dispatchEvent(new Event(eventName, { bubbles: true }))') !== false
            && strpos($operationalModulesSourceRaw, 'ffp-react-primary-filters') !== false
            && strpos($operationalModulesSourceRaw, '.ffs-form-view .ffs-store-card') !== false
            && strpos($briefingsRendererRaw, 'operationalModuleMount') !== false && strpos($briefingsRendererRaw, "'briefings'") !== false
            && strpos($implementationsRendererRaw, 'operationalModuleMount') !== false && strpos($implementationsRendererRaw, "'implementations'") !== false
            && strpos($stocksRendererRaw, 'operationalModuleMount') !== false && strpos($stocksRendererRaw, "'stocks'") !== false
            && strpos($shoppingRendererRaw, 'operationalModuleMount') !== false && strpos($shoppingRendererRaw, "'shopping'") !== false
            && strpos($plannerRendererRaw, 'operationalModuleMount') !== false && strpos($plannerRendererRaw, "'planner'") !== false && strpos($plannerRendererRaw, 'ffp-react-primary-filters') !== false
            && strpos($expensesRendererRaw, 'operationalModuleMount') !== false && strpos($expensesRendererRaw, "'expenses'") !== false
            && strpos($messagesRendererRaw, 'operationalModuleMount') !== false && strpos($messagesRendererRaw, "'messages'") !== false
            && strpos($reactFrontCssRaw, 'fieldflow-react-operational-modules-v1') !== false
            && strpos($reactFrontCssRaw, 'data-fieldflow-react-operational-native') !== false
            && strpos($reactFrontRaw, 'fieldflow-react-operational-modules-v1') !== false
            && strpos($reactFrontRaw, 'operational-module-controls') !== false
            && strpos($reactFrontRaw, 'data-fieldflow-react-operational-native') !== false
            && strpos($reactFrontTestsRaw, 'React operational modules compile a shared native-proxy contract for all priority surfaces') !== false
            && strpos($reactFrontTestsRaw, 'React operational module mounts preserve the seven canonical PHP module owners') !== false
            && $operationalModulesVerifierRaw !== ''
            && strpos($operationalModulesVerifierRaw, 'REACT OPERATIONAL MODULES CONTRACT OK') !== false
            && $operationalModulesDocRaw !== ''
            && strpos($operationalModulesDocRaw, 'No IndexedDB schema change.') !== false
            && strpos($operationalModulesDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false
            && strpos($frontendPackageRaw, '"react"') === false
            && strpos($frontendPackageRaw, '"react-dom"') === false;
        if (!$result['react_operational_modules_ok']) {
            $result['error'] = 'Os React Operational Modules não estão integralmente ligados aos controlos nativos certificados com fallback seguro.';
            return $result;
        }

        // Phase 8K: harden the shared React action proxy without moving
        // business ownership away from the certified native module runtimes.
        $operationalActionsDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8K-OPERATIONAL-ACTION-HARDENING.md');
        $operationalActionsVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-operational-action-hardening.php');
        $result['operational_action_hardening_ok'] = $result['react_operational_modules_ok']
            && strpos($operationalModulesSourceRaw, 'fieldflow-react-operational-actions-hardening-v1') !== false
            && strpos($operationalModulesSourceRaw, 'fieldFlowOperationalModuleReconcileImplementationVisibility') !== false
            && strpos($operationalModulesSourceRaw, "'data-action-contract': FIELD_FLOW_REACT_OPERATIONAL_ACTIONS_CONTRACT") !== false
            && strpos($operationalModulesSourceRaw, 'queueMicrotask(() => fieldFlowOperationalModuleReconcileImplementationVisibility(moduleRoot))') !== false
            && strpos($reactFrontCssRaw, 'fieldflow-react-operational-actions-hardening-v1') !== false
            && strpos($reactFrontCssRaw, '.ff-react-operational-module-implementations .ff-react-module-chips') !== false
            && strpos($reactFrontCssRaw, '.ff-operational-surface-implementations .ffi-front-filters') !== false
            && strpos($reactFrontRaw, 'fieldflow-react-operational-actions-hardening-v1') !== false
            && strpos($reactFrontRaw, 'fieldFlowOperationalModuleReconcileImplementationVisibility') !== false
            && strpos($reactFrontTestsRaw, 'Operational action hardening keeps Implementações chips stable and reconciles combined visibility') !== false
            && strpos($reactFrontTestsRaw, 'Operational React inputs do not double-dispatch the same visible edit') !== false
            && $operationalActionsVerifierRaw !== ''
            && strpos($operationalActionsVerifierRaw, 'OPERATIONAL ACTION HARDENING CONTRACT OK') !== false
            && $operationalActionsDocRaw !== ''
            && strpos($operationalActionsDocRaw, 'No IndexedDB schema change.') !== false
            && strpos($operationalActionsDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['operational_action_hardening_ok']) {
            $result['error'] = 'O Operational Action Hardening não está integralmente ligado aos proxies React e à UX de Implementações sem alterar o core certificado.';
            return $result;
        }

        // Phase 8L: harden the internal Briefing navigation/progress tabs
        // against the global full-width min-width contract without changing data mechanics.
        $briefingTabsDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8L-BRIEFING-PROGRESS-TABS-HARDENING.md');
        $briefingTabsVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-briefing-progress-tabs-hardening.php');
        $result['briefing_progress_tabs_hardening_ok'] = $result['operational_action_hardening_ok']
            && strpos($briefingsRendererRaw, 'fieldflow-briefing-tabstrip-hardening-v1') !== false
            && strpos($briefingsRendererRaw, '.ffb-tabs{display:flex!important') !== false
            && strpos($briefingsRendererRaw, 'flex-wrap:nowrap!important') !== false
            && strpos($briefingsRendererRaw, 'overflow-x:auto!important') !== false
            && strpos($briefingsRendererRaw, 'touch-action:pan-x pan-y') !== false
            && strpos($briefingsRendererRaw, 'flex:0 0 auto!important') !== false
            && strpos($briefingsRendererRaw, 'min-width:max-content!important') !== false
            && strpos($briefingsRendererRaw, 'scroll-snap-align:start') !== false
            && strpos($briefingsRendererRaw, 'function activateTab(card,key)') !== false
            && strpos($briefingsRendererRaw, "btn.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'})") !== false
            && $briefingTabsVerifierRaw !== ''
            && strpos($briefingTabsVerifierRaw, 'BRIEFING PROGRESS TABS HARDENING CONTRACT OK') !== false
            && $briefingTabsDocRaw !== ''
            && strpos($briefingTabsDocRaw, 'No Briefing data, import or mapping change.') !== false
            && strpos($briefingTabsDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['briefing_progress_tabs_hardening_ok']) {
            $result['error'] = 'O Briefing Progress Tabs Hardening não está integralmente ligado à navegação interna sem alterar o core certificado.';
            return $result;
        }

        // Phase 8M: complete the React UX around the advanced form fields while
        // retaining the native/typed/offline runtimes as canonical data owners.
        $specialFieldsRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/special-fields.ts');
        $reactFrontBuildToolRaw = self::entryContents($entries, 'fieldflow/frontend/tools/build.mjs');
        $formCompletionVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-react-form-completion.php');
        $formCompletionDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8M-REACT-FORM-COMPLETION.md');
        $result['react_form_completion_ok'] = $result['briefing_progress_tabs_hardening_ok']
            && strpos($reactFrontFoundationRaw, "FORM_COMPLETION_CONTRACT = 'fieldflow-react-form-completion-v1'") !== false
            && strpos($reactFrontFoundationRaw, "'form_completion_contract' => self::FORM_COMPLETION_CONTRACT") !== false
            && strpos($reactFrontFoundationRaw, "'submission_owner' => 'legacy_typed_runtime'") !== false
            && strpos($reactFrontFoundationRaw, "'offline_owner' => 'fieldflow_offline_runtime'") !== false
            && strpos($formRendererRaw, 'ReactFrontFoundation::FORM_COMPLETION_CONTRACT') !== false
            && strpos($formRendererRaw, 'data-fronts-input') !== false
            && strpos($formRendererRaw, 'data-order-qty') !== false
            && strpos($formRendererRaw, 'data-share-space-card') !== false
            && strpos($formRendererRaw, 'data-rp-evidence-category') !== false
            && strpos($formRendererRaw, 'ProductCardex::question_rows') !== false
            && strpos($formRendererRaw, 'ProductCardex::order_question_rows') !== false
            && strpos($reactFrontDispatchRaw, "FIELD_FLOW_REACT_FORM_COMPLETION_CONTRACT = 'fieldflow-react-form-completion-v1'") !== false
            && strpos($reactFrontTypesRaw, 'formCompletionContract?: string') !== false
            && strpos($reactFrontTypesRaw, 'FieldFlowSpecialCategorySnapshot') !== false
            && $specialFieldsRaw !== ''
            && strpos($specialFieldsRaw, 'fieldFlowSpecialActive') !== false
            && strpos($specialFieldsRaw, 'fieldFlowSpecialMediaCount') !== false
            && strpos($specialFieldsRaw, 'fieldFlowSpecialGoToIncomplete') !== false
            && strpos($specialFieldsRaw, 'fieldFlowSpecialOpenCategory') !== false
            && strpos($specialFieldsRaw, 'Inativo pela condição anterior') !== false
            && strpos($specialFieldsRaw, 'Zero automático') !== false
            && strpos($specialFieldsRaw, 'data-fieldflow-react-form-completion') !== false
            && strpos($specialFieldsRaw, 'Captura, recuperação e envio continuam no runtime FieldFlow certificado.') !== false
            && $reactFrontBuildToolRaw !== ''
            && strpos($reactFrontBuildToolRaw, 'reactFormCompletionContract') !== false
            && strpos($reactFrontBuildToolRaw, 'fieldflow-react-form-completion-v1') !== false
            && strpos($reactFrontTestsRaw, 'React form completion compiles category navigation, pending guidance and conditional suggestion awareness') !== false
            && strpos($reactFrontTestsRaw, 'React form completion stays wired as UX over canonical special-field owners') !== false
            && strpos($reactFrontCssRaw, 'Phase 8M, 2.2.498: React Form Completion.') !== false
            && strpos($reactFrontCssRaw, 'fieldflow-react-form-completion-v1') !== false
            && strpos($reactFrontCssRaw, '.ff-react-special-category-nav') !== false
            && strpos($reactFrontCssRaw, '.ff-react-special-summary') !== false
            && strpos($reactFrontCssRaw, '[data-fieldflow-react-form-completion=') !== false
            && strpos($reactFrontRaw, 'fieldflow-react-form-completion-v1') !== false
            && strpos($reactFrontRaw, 'fieldFlowSpecialGoToIncomplete') !== false
            && strpos($reactFrontRaw, 'Inativo pela condição anterior') !== false
            && strpos($reactFrontRaw, 'Zero automático') !== false
            && strpos($reactFrontRaw, 'wordpress-wp-element') !== false
            && $formCompletionVerifierRaw !== ''
            && strpos($formCompletionVerifierRaw, 'REACT FORM COMPLETION CONTRACT OK') !== false
            && strpos($formCompletionVerifierRaw, 'business_logic_owner=canonical_native_runtime') !== false
            && $formCompletionDocRaw !== ''
            && strpos($formCompletionDocRaw, 'fieldflow-react-form-completion-v1') !== false
            && strpos($formCompletionDocRaw, 'ProductCardex remains canonical.') !== false
            && strpos($formCompletionDocRaw, 'No IndexedDB schema change.') !== false
            && strpos($formCompletionDocRaw, 'No media transport ownership change.') !== false
            && strpos($formCompletionDocRaw, '`SOURCE_RELEASE` is `2.2.497` and `STABLE_RELEASE` is `2.2.498`') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false
            && strpos($frontendPackageRaw, '"react"') === false
            && strpos($frontendPackageRaw, '"react-dom"') === false;
        if (!$result['react_form_completion_ok']) {
            $result['error'] = 'O React Form Completion não está integralmente ligado aos componentes avançados mantendo ownership canónico do core certificado.';
            return $result;
        }

        // Phase 8N successor: runtime identity follows the candidate release dynamically.
        // Static 2.2.527 bridge markers may remain solely for the installed 2.2.526 validator.
        $runtimeIdentityDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8N-CERTIFICATION-RUNTIME-IDENTITY-HOTFIX.md');
        $runtimeIdentityVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-certification-runtime-identity-hotfix.php');
        $releaseGateRaw = self::entryContents($entries, 'fieldflow/src/Support/ReleaseGate.php');
        $previousRuntimeMarker = $expectedSource !== '' ? "const RUNTIME_BUILD='" . $expectedSource . "'" : '';
        $previousRuntimeFile = $expectedSource !== '' ? 'fieldflow-offline-runtime-' . $expectedSource . '.js' : '';
        $currentRuntimeMarker = "const RUNTIME_BUILD='" . $result['version'] . "'";
        $currentRuntimeFile = 'fieldflow-offline-runtime-' . $result['version'] . '.js';
        $currentFrontendFile = 'fieldflow-frontend-core-' . $result['version'] . '.js';
        $currentReactFrontFile = 'fieldflow-react-front-' . $result['version'] . '.js';
        $currentReactBackofficeFile = 'fieldflow-react-backoffice-' . $result['version'] . '.js';
        $result['certification_runtime_identity_ok'] = $runtimeRaw !== ''
            && strpos($runtimeRaw, $currentRuntimeMarker) !== false
            && strpos($runtimeRaw, '__runtimeBuild:RUNTIME_BUILD') !== false
            && $responsiveRaw !== ''
            && strpos($responsiveRaw, $currentRuntimeFile) !== false
            && strpos($responsiveRaw, $currentFrontendFile) !== false
            && strpos($responsiveRaw, $currentReactFrontFile) !== false
            && strpos($responsiveRaw, $currentReactBackofficeFile) !== false
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, 'checkCertificationRuntimeIdentityContract') !== false
            && strpos($releaseGateRaw, 'fieldflow-certification-runtime-identity-v1') !== false
            && strpos($releaseGateRaw, "RUNTIME_IDENTITY_SUCCESSOR_CONTRACT = 'fieldflow-runtime-identity-successor-v1'") !== false
            && strpos($releaseGateRaw, 'currentOfflineRuntimeRelative') !== false
            && strpos($releaseGateRaw, 'currentOfflineRuntimeMarker') !== false
            && ($previousRuntimeFile === '' || strpos($responsiveRaw, $previousRuntimeFile) === false)
            && $runtimeIdentityVerifierRaw !== ''
            && strpos($runtimeIdentityVerifierRaw, 'CERTIFICATION RUNTIME IDENTITY HOTFIX CONTRACT OK') !== false
            && $runtimeIdentityDocRaw !== ''
            && strpos($runtimeIdentityDocRaw, 'fieldflow-certification-runtime-identity-v1') !== false
            && strpos($runtimeIdentityDocRaw, '`SOURCE_RELEASE` is `2.2.498` and `STABLE_RELEASE` is `2.2.499`') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['certification_runtime_identity_ok']) {
            $result['error'] = 'A identidade do runtime, dos assets versionados ou das expectativas do Release Gate não está alinhada com a release.';
            return $result;
        }

        // Phase 8O: historical phase markers must remain stable and must not
        // be coupled to the current global plugin release number.
        $historicalGateDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8O-RELEASE-GATE-HISTORICAL-MARKER-HARDENING.md');
        $historicalGateVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-release-gate-historical-marker-hardening.php');
        $result['release_gate_historical_markers_ok'] = $result['certification_runtime_identity_ok']
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, 'fieldflow-release-gate-historical-marker-v1') !== false
            && strpos($releaseGateRaw, "'Phase 8J'") !== false
            && strpos($releaseGateRaw, "'Phase 8K'") !== false
            && strpos($releaseGateRaw, "'Phase 8J, ' . \$release") === false
            && strpos($releaseGateRaw, "'Phase 8K, ' . \$release") === false
            && $reactFrontCssRaw !== ''
            && strpos($reactFrontCssRaw, 'Phase 8J') !== false
            && strpos($reactFrontCssRaw, 'fieldflow-react-operational-modules-v1') !== false
            && strpos($reactFrontCssRaw, 'Phase 8K') !== false
            && strpos($reactFrontCssRaw, 'fieldflow-react-operational-actions-hardening-v1') !== false
            && $historicalGateVerifierRaw !== ''
            && strpos($historicalGateVerifierRaw, 'RELEASE GATE HISTORICAL MARKER HARDENING CONTRACT OK') !== false
            && $historicalGateDocRaw !== ''
            && strpos($historicalGateDocRaw, 'fieldflow-release-gate-historical-marker-v1') !== false
            && strpos($historicalGateDocRaw, '`SOURCE_RELEASE` is `2.2.499` and `STABLE_RELEASE` is `2.2.500`') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['release_gate_historical_markers_ok']) {
            $result['error'] = 'Os Release Gates React históricos continuam acoplados à versão global ou perderam os contratos funcionais estáveis.';
            return $result;
        }

        // Phase 8P: front transition stability removes first-paint React fallback
        // flashes and fast lazy-loader visual flashes without changing data ownership.
        $transitionDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8P-FRONT-TRANSITION-STABILITY.md');
        $transitionVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-front-transition-stability.php');
        $uiRuntimeRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-ui-runtime.js');
        $designSystemRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-design-system.css');
        $panelLoaderRaw = self::entryContents($entries, 'fieldflow/src/Front/PanelLoader.php');
        $operationalShellSourceRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/operational-shell.ts');
        $routeToolbarSourceRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/route-toolbar.ts');
        $formWizardSourceRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/form-wizard.ts');
        $result['front_transition_stability_ok'] = $result['release_gate_historical_markers_ok']
            && $reactFrontFoundationRaw !== ''
            && strpos($reactFrontFoundationRaw, "FRONT_TRANSITION_STABILITY_CONTRACT = 'fieldflow-front-transition-stability-v1'") !== false
            && $reactFrontRaw !== ''
            && strpos($reactFrontRaw, 'fieldflow-front-transition-stability-v1') !== false
            && $appShortcodeRaw !== ''
            && strpos($appShortcodeRaw, 'data-fieldflow-react-front-fallback-pending="1"') !== false
            && strpos($appShortcodeRaw, 'let initialPanelApplied=false') !== false
            && strpos($appShortcodeRaw, 'openPanel(initialPanel,{remember:false,scroll:false})') !== false
            && strpos($appShortcodeRaw, 'setTimeout(applyInitial') === false
            && $shortcodesRaw !== ''
            && strpos($shortcodesRaw, 'data-fieldflow-react-front-fallback-pending="1"') !== false
            && $formRendererRaw !== ''
            && strpos($formRendererRaw, 'data-fieldflow-react-front-fallback-pending="1"') !== false
            && $panelLoaderRaw !== ''
            && strpos($panelLoaderRaw, 'class="ff-skeleton" aria-hidden="true" hidden') !== false
            && strpos($panelLoaderRaw, 'class="ff-lazy-label" hidden') !== false
            && $designSystemRaw !== ''
            && strpos($designSystemRaw, '[data-fieldflow-react-front-fallback-pending="1"]{display:none!important}') !== false
            && $uiRuntimeRaw !== ''
            && strpos($uiRuntimeRaw, 'reactFallbackTimers') !== false
            && strpos($uiRuntimeRaw, 'const visualTimer=window.setTimeout') !== false
            && strpos($uiRuntimeRaw, '},180)') !== false
            && substr_count($uiRuntimeRaw, "placeholder.dispatchEvent(new CustomEvent('fieldflow:lazy-panel-loaded'") === 1
            && strpos($uiRuntimeRaw, "document.dispatchEvent(new CustomEvent('fieldflow:lazy-panel-loaded'") === false
            && strpos($operationalShellSourceRaw, "removeAttribute('data-fieldflow-react-front-fallback-pending')") !== false
            && strpos($routeToolbarSourceRaw, "removeAttribute('data-fieldflow-react-front-fallback-pending')") !== false
            && strpos($formWizardSourceRaw, "removeAttribute('data-fieldflow-react-front-fallback-pending')") !== false
            && $reactFrontTestsRaw !== ''
            && strpos($reactFrontTestsRaw, 'Front transition stability hides React fallbacks before mount and initializes the app panel once') !== false
            && strpos($reactFrontTestsRaw, 'Front transition stability delays lazy visuals and emits one canonical loaded event') !== false
            && $transitionVerifierRaw !== ''
            && strpos($transitionVerifierRaw, 'FRONT TRANSITION STABILITY CONTRACT OK') !== false
            && $transitionDocRaw !== ''
            && strpos($transitionDocRaw, 'fieldflow-front-transition-stability-v1') !== false
            && strpos($transitionDocRaw, '`SOURCE_RELEASE` is `2.2.500` and `STABLE_RELEASE` is `2.2.501`') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['front_transition_stability_ok']) {
            $result['error'] = 'A estabilidade de transição do front não está integralmente aplicada ou reintroduziu flashes/eventos duplicados.';
            return $result;
        }

        // Phase 8Q: Mobility Cost Intelligence derives financial mobility metrics
        // from the existing canonical route distance/toll metrics. It stores
        // campaign/operator configuration and per-route snapshots in existing
        // meta_json structures, without changing P1-P4 routing ownership.
        $mobilityServiceRaw = self::entryContents($entries, 'fieldflow/src/Services/MobilityCostService.php');
        $assignmentsRaw = self::entryContents($entries, 'fieldflow/src/Admin/Assignments.php');
        $campaignLocationsRaw = self::entryContents($entries, 'fieldflow/src/Admin/CampaignLocations.php');
        $routesAdminRaw = self::entryContents($entries, 'fieldflow/src/Admin/Routes.php');
        $routeAccessRaw = self::entryContents($entries, 'fieldflow/src/Repositories/RouteAccessRepository.php');
        $routesControllerRaw = self::entryContents($entries, 'fieldflow/src/Rest/RoutesController.php');
        $clientPortalRaw = self::entryContents($entries, 'fieldflow/src/Front/ClientPortalShortcodeRenderer.php');
        $statsControllerRaw = self::entryContents($entries, 'fieldflow/src/Rest/StatsController.php');
        $formSubmissionsRaw = self::entryContents($entries, 'fieldflow/src/Admin/FormSubmissions.php');
        $mobilityVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-mobility-cost-intelligence.php');
        $mobilityDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8Q-MOBILITY-COST-INTELLIGENCE.md');
        $routeMetricsRaw = self::entryContents($entries, 'fieldflow/src/Services/RouteMetricsService.php');
        $result['mobility_cost_intelligence_ok'] = $result['front_transition_stability_ok']
            && $mobilityServiceRaw !== ''
            && strpos($mobilityServiceRaw, "public const CONTRACT = 'fieldflow-mobility-cost-intelligence-v1'") !== false
            && strpos($mobilityServiceRaw, "'mobility_cost_default'") !== false
            && strpos($mobilityServiceRaw, "'mobility_cost_profiles'") !== false
            && strpos($mobilityServiceRaw, "'mobility_cost_snapshot'") !== false
            && strpos($mobilityServiceRaw, 'profileIssues') !== false
            && strpos($mobilityServiceRaw, 'backfillRoutes') !== false
            && strpos($mobilityServiceRaw, 'total_mobility_cost_eur') !== false
            && $assignmentsRaw !== ''
            && strpos($assignmentsRaw, "'mobility' => 'Mobilidade'") !== false
            && strpos($assignmentsRaw, 'backfill_mobility_routes') !== false
            && strpos($assignmentsRaw, 'save_route_mobility_override') !== false
            && $campaignLocationsRaw !== ''
            && strpos($campaignLocationsRaw, 'Mobilidade e Energia') !== false
            && strpos($campaignLocationsRaw, 'routespro-live-energy-cost') !== false
            && strpos($campaignLocationsRaw, 'routespro-manual-live-energy') !== false
            && strpos($campaignLocationsRaw, 'MobilityCostService::ensureRouteSnapshot') !== false
            && $routesAdminRaw !== ''
            && strpos($routesAdminRaw, 'mobility_cost_snapshot') !== false
            && strpos($routesAdminRaw, 'Custo deslocação total EUR') !== false
            && $routeAccessRaw !== '' && strpos($routeAccessRaw, "['mobility_cost']") !== false
            && $routesControllerRaw !== '' && strpos($routesControllerRaw, "'mobility_cost'") !== false
            && $clientPortalRaw !== ''
            && strpos($clientPortalRaw, 'routeMobility') !== false
            && strpos($clientPortalRaw, 'Combustível / energia') !== false
            && $statsControllerRaw !== '' && strpos($statsControllerRaw, "'mobility_summary'") !== false
            && $shortcodesRaw !== '' && strpos($shortcodesRaw, 'mobility_summary') !== false
            && strpos($shortcodesRaw, 'Combustível / energia') !== false
            && strpos($shortcodesRaw, 'Custo deslocação') !== false
            && $formSubmissionsRaw !== ''
            && strpos($formSubmissionsRaw, 'Combustível / energia estimado') !== false
            && strpos($formSubmissionsRaw, 'Custo deslocação EUR') !== false
            && $mobilityVerifierRaw !== ''
            && strpos($mobilityVerifierRaw, 'MOBILITY COST INTELLIGENCE CONTRACT OK') !== false
            && $mobilityDocRaw !== ''
            && strpos($mobilityDocRaw, 'fieldflow-mobility-cost-intelligence-v1') !== false
            && strpos($mobilityDocRaw, '`SOURCE_RELEASE` is `2.2.501` and `STABLE_RELEASE` is `2.2.502`') !== false
            && strpos($mobilityDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['mobility_cost_intelligence_ok']) {
            $result['error'] = 'O Mobility Cost Intelligence não está integralmente ligado a Campanhas, Centro de Atribuição, rotas, front, Analytics e exportações sem alterar o routing certificado.';
            return $result;
        }

        // Phase 8R: a single compatibility resolver owns stored distance/toll
        // interpretation for mobility and route consumers. Persistence must be
        // verified by readback and historical snapshots must remain protected.
        $routeStatusRaw = self::entryContents($entries, 'fieldflow/src/Support/RouteStatus.php');
        $mobilityIntegrityVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-mobility-integrity-canonical-metrics.php');
        $mobilityIntegrityDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8R-MOBILITY-INTEGRITY-CANONICAL-METRICS.md');
        $result['mobility_integrity_canonical_metrics_ok'] = $result['mobility_cost_intelligence_ok']
            && $routeMetricsRaw !== ''
            && strpos($routeMetricsRaw, 'canonicalFromRouteArray') !== false
            && strpos($routeMetricsRaw, 'fieldflow-canonical-route-metrics-resolver-v2') !== false
            && strpos($routeMetricsRaw, 'distanceFromStoredRouteLegs') !== false
            && $mobilityServiceRaw !== ''
            && strpos($mobilityServiceRaw, 'RouteMetricsService::canonicalFromRouteArray') !== false
            && strpos($mobilityServiceRaw, 'profilesEquivalent') !== false
            && strpos($mobilityServiceRaw, 'refreshSnapshotForIdentityChange') !== false
            && strpos($mobilityServiceRaw, "status NOT IN ('completed','done','canceled','cancelled')") !== false
            && strpos($mobilityServiceRaw, "unset(\$meta['mobility_cost_snapshot'])") !== false
            && $routeStatusRaw !== ''
            && strpos($routeStatusRaw, 'isSnapshotMutable') !== false
            && strpos($routeStatusRaw, "if (\$status === 'done')") !== false
            && strpos($routeStatusRaw, "if (\$status === 'cancelled')") !== false
            && $assignmentsRaw !== ''
            && strpos($assignmentsRaw, 'mobility_persist_failed') !== false
            && strpos($assignmentsRaw, 'mobility_issue') !== false
            && $routesAdminRaw !== ''
            && strpos($routesAdminRaw, 'RouteStatus::isSnapshotMutable') !== false
            && $routesControllerRaw !== ''
            && strpos($routesControllerRaw, 'RouteStatus::normalize') !== false
            && strpos($routesControllerRaw, 'refreshSnapshotForIdentityChange') !== false
            && $routeAccessRaw !== ''
            && strpos($routeAccessRaw, 'RouteMetricsService::canonicalFromRouteArray') !== false
            && $clientPortalRaw !== ''
            && strpos($clientPortalRaw, 'km*0.58*0.075') === false
            && $mobilityIntegrityVerifierRaw !== ''
            && strpos($mobilityIntegrityVerifierRaw, 'MOBILITY INTEGRITY & CANONICAL METRICS CONTRACT OK') !== false
            && $mobilityIntegrityDocRaw !== ''
            && strpos($mobilityIntegrityDocRaw, 'fieldflow-mobility-integrity-canonical-metrics-v1') !== false
            && strpos($mobilityIntegrityDocRaw, '`SOURCE_RELEASE` is `2.2.502` and `STABLE_RELEASE` is `2.2.503`') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['mobility_integrity_canonical_metrics_ok']) {
            $result['error'] = 'A integridade canónica de km/portagens, persistência e snapshots de mobilidade não está integralmente aplicada.';
            return $result;
        }

        // Phase 8S: complete-route integrity. A financial distance is authoritative
        // only when start, every planned leg and end are represented. Partial
        // kilometres remain diagnostic and must not silently feed mobility cost.
        $routingMatrixRaw = self::entryContents($entries, 'fieldflow/src/Services/Planning/RoutingMatrixService.php');
        $routeOptimizationRaw = self::entryContents($entries, 'fieldflow/src/Services/RouteOptimizationService.php');
        $selfPlannerRaw = self::entryContents($entries, 'fieldflow/src/Front/SelfRoutePlannerShortcodeRenderer.php');
        $routeIntegrityVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-route-endpoint-distance-integrity.php');
        $routeIntegrityDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8S-ROUTE-ENDPOINT-DISTANCE-INTEGRITY.md');
        $routeCoordinateTrustDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8T-ROUTE-ENDPOINT-COORDINATE-TRUST-HOTFIX.md');
        $result['route_endpoint_distance_integrity_ok'] = $result['mobility_integrity_canonical_metrics_ok']
            && $routeMetricsRaw !== ''
            && strpos($routeMetricsRaw, 'fieldflow-route-endpoint-distance-integrity-v1') !== false
            && strpos($routeMetricsRaw, 'fieldflow_route_metrics_v5') !== false
            && strpos($routeMetricsRaw, 'endpointIntegrity') !== false
            && strpos($routeMetricsRaw, 'repairPlannedRouteIntegrity') !== false
            && strpos($routeMetricsRaw, 'route_endpoints_incomplete') !== false
            && $routingMatrixRaw !== ''
            && strpos($routingMatrixRaw, 'missing_start') !== false
            && strpos($routingMatrixRaw, 'missing_end') !== false
            && strpos($routingMatrixRaw, 'endpoints_complete') !== false
            && $mobilityServiceRaw !== ''
            && strpos($mobilityServiceRaw, 'estimateForMetrics') !== false
            && strpos($mobilityServiceRaw, 'costs_available') !== false
            && strpos($mobilityServiceRaw, 'distance_incomplete') !== false
            && $routeOptimizationRaw !== ''
            && strpos($routeOptimizationRaw, 'fieldflow_route_endpoints_required') !== false
            && strpos($routeOptimizationRaw, 'access_distance_km') !== false
            && strpos($routeOptimizationRaw, 'return_distance_km') !== false
            && $routesAdminRaw !== ''
            && strpos($routesAdminRaw, 'Para publicar a rota, define o ponto de partida e o ponto de chegada') !== false
            && strpos($routesAdminRaw, ' conhecidos') !== false
            && strpos($routesAdminRaw, 'fieldflow-route-endpoint-coordinate-trust-v1') !== false
            && strpos($routesAdminRaw, 'resolve_route_endpoint') !== false
            && strpos($routesAdminRaw, 'endpoint_coordinates_refreshed') !== false
            && $campaignLocationsRaw !== ''
            && strpos($campaignLocationsRaw, 'skipped_endpoints') !== false
            && strpos($campaignLocationsRaw, 'plan_distance_integrity_metrics') !== false
            && $selfPlannerRaw !== ''
            && strpos($selfPlannerRaw, 'return_to_start') !== false
            && $assignmentsRaw !== ''
            && strpos($assignmentsRaw, 'repair_route_distance_integrity') !== false
            && strpos($assignmentsRaw, 'Reparar partida, chegada e km de rotas por planear') !== false
            && $statsControllerRaw !== ''
            && strpos($statsControllerRaw, 'costed_routes') !== false
            && strpos($statsControllerRaw, 'incomplete_distance_routes') !== false
            && $clientPortalRaw !== ''
            && strpos($clientPortalRaw, 'routeDistanceComplete') !== false
            && strpos($clientPortalRaw, ' conhecidos') !== false
            && $shortcodesRaw !== ''
            && strpos($shortcodesRaw, 'mobilityAggregate') !== false
            && $formSubmissionsRaw !== ''
            && strpos($formSubmissionsRaw, 'Rotas com distância incompleta') !== false
            && strpos($formSubmissionsRaw, 'POR CALCULAR, DISTÂNCIA INCOMPLETA') !== false
            && $routeIntegrityVerifierRaw !== ''
            && strpos($routeIntegrityVerifierRaw, 'ROUTE ENDPOINT & DISTANCE INTEGRITY CONTRACT OK') !== false
            && $routeIntegrityDocRaw !== ''
            && strpos($routeIntegrityDocRaw, 'fieldflow-route-endpoint-distance-integrity-v1') !== false
            && strpos($routeIntegrityDocRaw, '`SOURCE_RELEASE` is `2.2.503` and `STABLE_RELEASE` is `2.2.504`') !== false
            && $routeCoordinateTrustDocRaw !== ''
            && strpos($routeCoordinateTrustDocRaw, 'fieldflow-route-endpoint-coordinate-trust-v1') !== false
            && strpos($routeCoordinateTrustDocRaw, '`SOURCE_RELEASE` is `2.2.504` and `STABLE_RELEASE` is `2.2.505`') !== false
            && strpos($routeCoordinateTrustDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($routeCoordinateTrustDocRaw, 'No database schema change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['route_endpoint_distance_integrity_ok']) {
            $result['error'] = 'A integridade da distância completa, partida, percurso e chegada, não está integralmente aplicada.';
            return $result;
        }

        // Phase 8U: endpoint provenance and legacy reconciliation. Endpoints
        // that silently collapse to the first/last PDV cannot certify as a
        // complete financial route unless explicitly confirmed.
        $endpointResolverRaw = self::entryContents($entries, 'fieldflow/src/Services/RouteEndpointResolverService.php');
        $endpointReconciliationVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-route-endpoint-provenance-reconciliation.php');
        $endpointReconciliationDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8U-ROUTE-ENDPOINT-PROVENANCE-LEGACY-RECONCILIATION.md');
        $result['route_endpoint_provenance_reconciliation_ok'] = $result['route_endpoint_distance_integrity_ok']
            && $endpointResolverRaw !== ''
            && strpos($endpointResolverRaw, 'fieldflow-route-endpoint-provenance-reconciliation-v1') !== false
            && strpos($endpointResolverRaw, 'pointsCoincide') !== false
            && strpos($endpointResolverRaw, 'allow_matches_stop') !== false
            && strpos($endpointResolverRaw, 'endpoint_address_fingerprint') !== false
            && $routeMetricsRaw !== ''
            && strpos($routeMetricsRaw, 'suspicious_endpoint_count') !== false
            && strpos($routeMetricsRaw, 'route_endpoints_untrusted') !== false
            && strpos($routeMetricsRaw, 'route_default_repair') !== false
            && strpos($routeMetricsRaw, 'endpoint_reconciled_at') !== false
            && $routeOptimizationRaw !== ''
            && strpos($routeOptimizationRaw, 'fieldflow_route_endpoints_untrusted') !== false
            && strpos($routeOptimizationRaw, 'RouteEndpointResolverService::integrity') !== false
            && strpos($routeOptimizationRaw, 'endpoint_reconciliation_contract') !== false
            && $routesAdminRaw !== ''
            && strpos($routesAdminRaw, 'start_at_first_stop') !== false
            && strpos($routesAdminRaw, 'end_at_last_stop') !== false
            && strpos($routesAdminRaw, 'suspicious_endpoint_count') !== false
            && $campaignLocationsRaw !== ''
            && strpos($campaignLocationsRaw, 'RouteEndpointResolverService') !== false
            && strpos($campaignLocationsRaw, 'resolve_route_endpoint') !== false
            && strpos($campaignLocationsRaw, 'suspicious_endpoint_count') !== false
            && $assignmentsRaw !== ''
            && strpos($assignmentsRaw, 'Reconciliar endpoints e km') !== false
            && strpos($assignmentsRaw, 'distance_suspicious') !== false
            && strpos($assignmentsRaw, 'distance_reconciled') !== false
            && $endpointReconciliationVerifierRaw !== ''
            && strpos($endpointReconciliationVerifierRaw, 'ROUTE ENDPOINT PROVENANCE & LEGACY RECONCILIATION CONTRACT OK') !== false
            && $endpointReconciliationDocRaw !== ''
            && strpos($endpointReconciliationDocRaw, 'fieldflow-route-endpoint-provenance-reconciliation-v1') !== false
            && strpos($endpointReconciliationDocRaw, '`SOURCE_RELEASE` is `2.2.505` and `STABLE_RELEASE` is `2.2.506`') !== false
            && strpos($endpointReconciliationDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($endpointReconciliationDocRaw, 'No database schema change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['route_endpoint_provenance_reconciliation_ok']) {
            $result['error'] = 'A proveniência e reconciliação de partida/chegada, incluindo endpoints legacy coincidentes, não está integralmente aplicada.';
            return $result;
        }

        // Phase 8V: endpoint trust remains auditable but is no longer a
        // financial blocker when both endpoints and every route leg are routable.
        $routeFinancialVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-route-financial-continuity-hotfix.php');
        $routeFinancialDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8V-ROUTE-FINANCIAL-CONTINUITY-HOTFIX.md');
        $result['route_financial_continuity_ok'] = $result['route_endpoint_provenance_reconciliation_ok']
            && $endpointResolverRaw !== ''
            && strpos($endpointResolverRaw, 'endpoints_routable') !== false
            && strpos($endpointResolverRaw, 'endpoints_trusted') !== false
            && strpos($endpointResolverRaw, 'COINCIDENCE_KM = 0.03') !== false
            && $routeMetricsRaw !== ''
            && strpos($routeMetricsRaw, 'fieldflow-route-financial-continuity-v1') !== false
            && strpos($routeMetricsRaw, 'endpoints_routable') !== false
            && strpos($routeMetricsRaw, 'known_toll_recovered') !== false
            && strpos($routeMetricsRaw, 'route_endpoints_untrusted') !== false
            && $mobilityServiceRaw !== ''
            && strpos($mobilityServiceRaw, 'endpoints_routable') !== false
            && strpos($mobilityServiceRaw, 'costs_available') !== false
            && $routeOptimizationRaw !== ''
            && strpos($routeOptimizationRaw, 'endpoints_routable') !== false
            && strpos($routeOptimizationRaw, 'endpoints_trusted') !== false
            && strpos($routeOptimizationRaw, 'fieldflow_route_endpoints_untrusted') !== false
            && $routesAdminRaw !== ''
            && strpos($routesAdminRaw, 'endpointsRoutable') !== false
            && strpos($routesAdminRaw, 'Calculável') !== false
            && $clientPortalRaw !== ''
            && strpos($clientPortalRaw, 'Distância calculada, validar proximidade dos extremos') !== false
            && $routeFinancialVerifierRaw !== ''
            && strpos($routeFinancialVerifierRaw, 'ROUTE FINANCIAL CONTINUITY HOTFIX CONTRACT OK') !== false
            && $routeFinancialDocRaw !== ''
            && strpos($routeFinancialDocRaw, 'fieldflow-route-financial-continuity-v1') !== false
            && strpos($routeFinancialDocRaw, '`SOURCE_RELEASE` is `2.2.506` and `STABLE_RELEASE` is `2.2.507`') !== false
            && strpos($routeFinancialDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($routeFinancialDocRaw, 'No database schema change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['route_financial_continuity_ok']) {
            $result['error'] = 'A continuidade financeira de rotas roteáveis com avisos de confiança nos endpoints não está integralmente aplicada.';
            return $result;
        }

        // Phase 8W: operational endpoint source of truth. Start/end master
        // data is explicit per campaign/operator/route and current route producers
        // may not manufacture endpoints from the edge PDVs.
        $endpointProfileRaw = self::entryContents($entries, 'fieldflow/src/Services/RouteEndpointProfileService.php');
        $mobileControllerRaw = self::entryContents($entries, 'fieldflow/src/Mobile/Controller.php');
        $googleMapsUrlRaw = self::entryContents($entries, 'fieldflow/src/Services/GoogleMapsUrlService.php');
        $endpointSourceVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-operational-endpoint-source-of-truth.php');
        $endpointSourceDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8W-OPERATIONAL-ENDPOINT-SOURCE-OF-TRUTH.md');
        $result['operational_endpoint_source_of_truth_ok'] = $result['route_financial_continuity_ok']
            && $endpointProfileRaw !== ''
            && strpos($endpointProfileRaw, 'fieldflow-operational-endpoint-source-of-truth-v1') !== false
            && strpos($endpointProfileRaw, 'route_endpoint_default') !== false
            && strpos($endpointProfileRaw, 'route_endpoint_profiles') !== false
            && strpos($endpointProfileRaw, 'recoverHistoricalEndpoints') !== false
            && strpos($endpointProfileRaw, 'Legacy route defaults are never consumed implicitly') !== false
            && strpos($endpointProfileRaw, '$legacy = self::legacyDefault') === false
            && $assignmentsRaw !== ''
            && strpos($assignmentsRaw, 'save_route_endpoint_default') !== false
            && strpos($assignmentsRaw, 'save_route_endpoint_profile') !== false
            && strpos($assignmentsRaw, 'Base da rota') !== false
            && $mobileControllerRaw !== ''
            && strpos($mobileControllerRaw, 'RouteEndpointProfileService::resolveForRoute') !== false
            && strpos($mobileControllerRaw, 'fieldflow_mobile_route_endpoints_required') !== false
            && strpos($mobileControllerRaw, '$start = $to_point($edge_lookup') === false
            && strpos($mobileControllerRaw, '$end = $to_point($edge_lookup') === false
            && $routesAdminRaw !== ''
            && strpos($routesAdminRaw, 'routeEndpointConfigs') !== false
            && strpos($routesAdminRaw, 'endpointDefaultsForSelection') !== false
            && strpos($routesAdminRaw, 'Base da rota no Centro de Atribuições') !== false
            && strpos($routesAdminRaw, 'valid[0].lat') === false
            && strpos($routesAdminRaw, 'valid[valid.length-1].lat') === false
            && $campaignLocationsRaw !== ''
            && strpos($campaignLocationsRaw, 'RouteEndpointProfileService::effectiveProfile') !== false
            && strpos($campaignLocationsRaw, 'RouteEndpointProfileService::resolveForRoute') !== false
            && strpos($campaignLocationsRaw, "get_option('routespro_route_defaults'") === false
            && $routesControllerRaw !== ''
            && strpos($routesControllerRaw, 'RouteEndpointProfileService') !== false
            && strpos($routesControllerRaw, 'route_edge_stop_points') !== false
            && strpos($routesControllerRaw, 'route_endpoint_profile_contract') !== false
            && $routeMetricsRaw !== ''
            && strpos($routeMetricsRaw, 'recoverHistoricalEndpoints') !== false
            && strpos($routeMetricsRaw, 'route_endpoint_profile_contract') !== false
            && $selfPlannerRaw !== ''
            && strpos($selfPlannerRaw, 'data-endpoint-profiles') !== false
            && strpos($selfPlannerRaw, 'endpointProfiles') !== false
            && strpos($selfPlannerRaw, "endpointProvenance='current_location'") !== false
            && strpos($selfPlannerRaw, "if(!start||!end)return'';") !== false
            && strpos($selfPlannerRaw, 'const pts=[start,...rows.map') === false
            && $shortcodesRaw !== ''
            && strpos($shortcodesRaw, 'data-endpoint-profiles') !== false
            && strpos($shortcodesRaw, 'applyEndpointProfile') !== false
            && strpos($shortcodesRaw, 'buildGMapsUrlFromRoute') !== false
            && strpos($shortcodesRaw, 'origin=startText||located[0].location') === false
            && strpos($shortcodesRaw, 'buildGMapsUrlFromStops') === false
            && $googleMapsUrlRaw !== ''
            && strpos($googleMapsUrlRaw, 'Operational Endpoint Source of Truth') !== false
            && strpos($googleMapsUrlRaw, 'Missing real endpoints') !== false
            && strpos($googleMapsUrlRaw, 'if ($startValue !== \'\') $points[] = $startValue;') === false
            && $endpointSourceVerifierRaw !== ''
            && strpos($endpointSourceVerifierRaw, 'OPERATIONAL ENDPOINT SOURCE OF TRUTH CONTRACT OK') !== false
            && $endpointSourceDocRaw !== ''
            && strpos($endpointSourceDocRaw, 'fieldflow-operational-endpoint-source-of-truth-v1') !== false
            && strpos($endpointSourceDocRaw, '`SOURCE_RELEASE` is `2.2.507` and `STABLE_RELEASE` is `2.2.508`') !== false
            && strpos($endpointSourceDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($endpointSourceDocRaw, 'No database schema change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['operational_endpoint_source_of_truth_ok']) {
            $result['error'] = 'A fonte operacional canónica de partida/chegada não está integralmente aplicada ou ainda existe um fallback implícito para os PDVs extremos.';
            return $result;
        }

        // Phase 8X: reconciliation fatal fix + explicit-address reuse. The
        // Intelligence Center is a fallback UX, while route/campaign/day inputs
        // remain authoritative whenever explicitly provided.
        $endpointReuseVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-endpoint-reconciliation-address-reuse.php');
        $endpointReuseDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8X-ENDPOINT-RECONCILIATION-ADDRESS-REUSE.md');
        $result['endpoint_reconciliation_address_reuse_ok'] = $result['operational_endpoint_source_of_truth_ok']
            && $routeMetricsRaw !== ''
            && strpos($routeMetricsRaw, 'repairPlannedRouteIntegrity(int $projectId, int $ownerUserId = 0') !== false
            && strpos($routeMetricsRaw, 'string $dateFrom =') !== false
            && strpos($routeMetricsRaw, 'string $dateTo =') !== false
            && strpos($routeMetricsRaw, 'int $actorUserId = 0') !== false
            && $assignmentsRaw !== ''
            && strpos($assignmentsRaw, 'data-ff-endpoint-address') !== false
            && strpos($assignmentsRaw, 'FieldFlowGoogleMaps.load') !== false
            && strpos($assignmentsRaw, 'Fallback opcional') !== false
            && strpos($assignmentsRaw, 'Campanhas PDV, a própria rota ou o Planear') !== false
            && $campaignLocationsRaw !== ''
            && strpos($campaignLocationsRaw, 'resolvedStartPoint') !== false
            && strpos($campaignLocationsRaw, 'resolvedEndPoint') !== false
            && $endpointReuseVerifierRaw !== ''
            && strpos($endpointReuseVerifierRaw, 'ENDPOINT RECONCILIATION ADDRESS REUSE CONTRACT OK') !== false
            && $endpointReuseDocRaw !== ''
            && strpos($endpointReuseDocRaw, 'fieldflow-endpoint-reconciliation-address-reuse-v1') !== false
            && strpos($endpointReuseDocRaw, '`SOURCE_RELEASE` is `2.2.508` and `STABLE_RELEASE` is `2.2.509`') !== false
            && strpos($endpointReuseDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($endpointReuseDocRaw, 'No database schema change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['endpoint_reconciliation_address_reuse_ok']) {
            $result['error'] = 'A reconciliação de endpoints, o autocomplete ou a prioridade das moradas explícitas não está integralmente ligada.';
            return $result;
        }

        // Phase 8Y: semantic edge endpoint reconciliation. The same
        // address as the edge PDV remains the same semantic place even when
        // geocoder/stored coordinates drift beyond the 30 metre radius.
        $semanticEdgeVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-semantic-edge-endpoint-reconciliation.php');
        $semanticEdgeDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8Y-SEMANTIC-EDGE-ENDPOINT-RECONCILIATION.md');
        $result['semantic_edge_endpoint_reconciliation_ok'] = $result['endpoint_reconciliation_address_reuse_ok']
            && $endpointResolverRaw !== ''
            && strpos($endpointResolverRaw, 'fieldflow-semantic-edge-endpoint-reconciliation-v1') !== false
            && strpos($endpointResolverRaw, 'Address identity must win over') !== false
            && strpos($endpointResolverRaw, '$aAddress === $bAddress') !== false
            && strpos($endpointResolverRaw, 'COINCIDENCE_KM = 0.03') !== false
            && $endpointProfileRaw !== ''
            && strpos($endpointProfileRaw, 'RouteEndpointResolverService::pointsCoincide') !== false
            && $routeMetricsRaw !== ''
            && strpos($routeMetricsRaw, 'RouteEndpointProfileService::resolveForRoute') !== false
            && $semanticEdgeVerifierRaw !== ''
            && strpos($semanticEdgeVerifierRaw, 'SEMANTIC EDGE ENDPOINT RECONCILIATION CONTRACT OK') !== false
            && strpos($semanticEdgeVerifierRaw, 'same_normalized_address_wins_over_coordinate_drift') !== false
            && strpos($semanticEdgeVerifierRaw, 'different_address_does_not_widen_geometric_threshold') !== false
            && $semanticEdgeDocRaw !== ''
            && strpos($semanticEdgeDocRaw, 'fieldflow-semantic-edge-endpoint-reconciliation-v1') !== false
            && strpos($semanticEdgeDocRaw, '`SOURCE_RELEASE` is `2.2.509` and `STABLE_RELEASE` is `2.2.510`') !== false
            && strpos($semanticEdgeDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($semanticEdgeDocRaw, 'No database schema change.') !== false
            && strpos($semanticEdgeDocRaw, 'No widening of the 30 metre coordinate coincidence threshold.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['semantic_edge_endpoint_reconciliation_ok']) {
            $result['error'] = 'A reconciliação semântica dos endpoints legacy nos PDVs extremos não está integralmente ligada.';
            return $result;
        }

        // Phase 8Z: explicit owner-profile migration for existing draft/planned routes.
        $ownerMigrationVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-owner-profile-route-migration.php');
        $ownerMigrationDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8Z-OWNER-PROFILE-ROUTE-MIGRATION.md');
        $assignmentsRaw = self::entryContents($entries, 'fieldflow/src/Admin/Assignments.php');
        $result['owner_profile_route_migration_ok'] = $result['semantic_edge_endpoint_reconciliation_ok']
            && $routeMetricsRaw !== ''
            && strpos($routeMetricsRaw, 'repairPlannedRouteIntegrityWithOwnerProfile') !== false
            && strpos($routeMetricsRaw, 'repairPlannedRouteIntegrityInternal') !== false
            && strpos($routeMetricsRaw, 'bool $forceOwnerProfile = false') !== false
            && strpos($routeMetricsRaw, '$forceProfileForRoute') !== false
            && strpos($routeMetricsRaw, 'operator_profile_forced') !== false
            && strpos($routeMetricsRaw, 'route_endpoint_forced_migration_at') !== false
            && strpos($routeMetricsRaw, 'manual_order_locked') !== false
            && $assignmentsRaw !== ''
            && strpos($assignmentsRaw, 'force_owner_profile') !== false
            && strpos($assignmentsRaw, 'Aplicar base do operativo e reconciliar km') !== false
            && strpos($assignmentsRaw, 'distance_forced_profile') !== false
            && $ownerMigrationVerifierRaw !== ''
            && strpos($ownerMigrationVerifierRaw, 'OWNER PROFILE ROUTE MIGRATION CONTRACT OK') !== false
            && $ownerMigrationDocRaw !== ''
            && strpos($ownerMigrationDocRaw, 'fieldflow-owner-profile-route-migration-v1') !== false
            && strpos($ownerMigrationDocRaw, '`SOURCE_RELEASE` is `2.2.510` and `STABLE_RELEASE` is `2.2.511`') !== false
            && strpos($ownerMigrationDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($ownerMigrationDocRaw, 'No database schema change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['owner_profile_route_migration_ok']) {
            $result['error'] = 'A migração em massa da Base habitual por operativo não está integralmente ligada.';
            return $result;
        }

        // Phase 8ZA: keep the certified five-parameter repair signature intact.
        $repairSignatureVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-endpoint-repair-signature-compatibility.php');
        $repairSignatureDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8ZA-ENDPOINT-REPAIR-SIGNATURE-COMPATIBILITY.md');
        $result['endpoint_repair_signature_compatibility_ok'] = $result['owner_profile_route_migration_ok']
            && $routeMetricsRaw !== ''
            && strpos($routeMetricsRaw, "repairPlannedRouteIntegrity(int \$projectId, int \$ownerUserId = 0, string \$dateFrom = '', string \$dateTo = '', int \$actorUserId = 0): array") !== false
            && strpos($routeMetricsRaw, 'repairPlannedRouteIntegrityWithOwnerProfile') !== false
            && strpos($routeMetricsRaw, 'repairPlannedRouteIntegrityInternal') !== false
            && $assignmentsRaw !== ''
            && strpos($assignmentsRaw, 'repairPlannedRouteIntegrityWithOwnerProfile') !== false
            && $repairSignatureVerifierRaw !== ''
            && strpos($repairSignatureVerifierRaw, 'ENDPOINT REPAIR SIGNATURE COMPATIBILITY CONTRACT OK') !== false
            && $repairSignatureDocRaw !== ''
            && strpos($repairSignatureDocRaw, 'fieldflow-endpoint-repair-signature-compatibility-v1') !== false
            && strpos($repairSignatureDocRaw, '`SOURCE_RELEASE` is `2.2.511` and `STABLE_RELEASE` is `2.2.512`') !== false
            && strpos($repairSignatureDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($repairSignatureDocRaw, 'No database schema change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['endpoint_repair_signature_compatibility_ok']) {
            $result['error'] = 'A assinatura certificada do reconciliador ou o entrypoint dedicado da migração não está integralmente preservado.';
            return $result;
        }

        // Phase 8ZB: finalize reconciled route distance from the ordered Google
        // sequence when available and preserve certification history as an explicit
        // post-update revalidation state rather than a first-time certification.
        $distanceFinalizationVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-route-distance-finalization-certification-continuity.php');
        $distanceFinalizationDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8ZB-ROUTE-DISTANCE-FINALIZATION-CERTIFICATION-CONTINUITY.md');
        $licenseTenantsRaw = self::entryContents($entries, 'fieldflow/src/Admin/LicenseTenants.php');
        $licenseFoundationRaw = self::entryContents($entries, 'fieldflow/src/Cloud/LicenseTenantFoundation.php');
        $result['route_distance_finalization_certification_continuity_ok'] = $result['endpoint_repair_signature_compatibility_ok']
            && $routeMetricsRaw !== ''
            && strpos($routeMetricsRaw, "DISTANCE_FINALIZATION_CONTRACT = 'fieldflow-route-distance-finalization-v1'") !== false
            && strpos($routeMetricsRaw, 'self::maybeRefreshGoogleRoute($routeId, [], true)') !== false
            && strpos($routeMetricsRaw, "'routing_finalized_by'=>'google_routes_sequence'") !== false
            && strpos($routeMetricsRaw, "'routing_finalized_by' => 'routing_matrix_preview'") !== false
            && strpos($routeMetricsRaw, "'google_exact'=>0") !== false
            && strpos($routeMetricsRaw, "'matrix_fallback'=>0") !== false
            && strpos($routeMetricsRaw, 'distance_component_delta_km') !== false
            && strpos($routeMetricsRaw, 'GoogleRoutes::calculateRoute($points)') !== false
            && strpos($routeMetricsRaw, 'ORDER BY rs.seq ASC, rs.id ASC') !== false
            && $assignmentsRaw !== ''
            && strpos($assignmentsRaw, 'distance_google_exact') !== false
            && strpos($assignmentsRaw, 'distance_matrix_fallback') !== false
            && strpos($assignmentsRaw, 'Google exato') !== false
            && $licenseTenantsRaw !== ''
            && strpos($licenseTenantsRaw, 'certificationVisualState') !== false
            && strpos($licenseTenantsRaw, 'CERTIFICADA, REVALIDAR') !== false
            && strpos($licenseTenantsRaw, 'Certificação histórica preservada.') !== false
            && $licenseFoundationRaw !== ''
            && strpos($licenseFoundationRaw, 'certification_state_present') !== false
            && strpos($licenseFoundationRaw, 'certification_requires_revalidation') !== false
            && $phase6Raw !== ''
            && strpos($phase6Raw, 'certification_state_present') !== false
            && strpos($phase6Raw, 'certification_requires_revalidation') !== false
            && $distanceFinalizationVerifierRaw !== ''
            && strpos($distanceFinalizationVerifierRaw, 'ROUTE DISTANCE FINALIZATION CERTIFICATION CONTINUITY CONTRACT OK') !== false
            && $distanceFinalizationDocRaw !== ''
            && strpos($distanceFinalizationDocRaw, 'fieldflow-route-distance-finalization-certification-continuity-v1') !== false
            && strpos($distanceFinalizationDocRaw, '`SOURCE_RELEASE` is `2.2.512` and `STABLE_RELEASE` is `2.2.513`') !== false
            && strpos($distanceFinalizationDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($distanceFinalizationDocRaw, 'No database schema change.') !== false
            && strpos($distanceFinalizationDocRaw, 'No certification gate is bypassed or auto-certified.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['route_distance_finalization_certification_continuity_ok']) {
            $result['error'] = 'A finalização exata dos km reconciliados ou a continuidade da certificação pós-update não está integralmente ligada.';
            return $result;
        }

        // Phase 8ZC: canonical installed source remains authoritative across
        // a private updater request while a stale in-memory class from the same
        // canonical file is downgraded to a non-blocking warning. Foreign origins fail.
        $runtimeAuthorityVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-release-gate-runtime-source-authority.php');
        $runtimeAuthorityDocRaw = self::entryContents($entries, 'fieldflow/PHASE-8ZC-RELEASE-GATE-RUNTIME-SOURCE-AUTHORITY.md');
        $result['release_gate_runtime_source_authority_ok'] = $result['route_distance_finalization_certification_continuity_ok']
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, 'fieldflow-release-gate-runtime-source-authority-v1') !== false
            && strpos($releaseGateRaw, 'inspectCanonicalRuntimeMethod') !== false
            && strpos($releaseGateRaw, 'inspectCanonicalRuntimeConstant') !== false
            && strpos($releaseGateRaw, 'class_exists($class, false)') !== false
            && strpos($releaseGateRaw, 'runtime_origin_mismatch') !== false
            && strpos($releaseGateRaw, 'stale_runtime_non_blocking') !== false
            && strpos($releaseGateRaw, 'opcache_invalidate') !== false
            && strpos($routeMetricsRaw, "repairPlannedRouteIntegrity(int \$projectId, int \$ownerUserId = 0, string \$dateFrom = '', string \$dateTo = '', int \$actorUserId = 0): array") !== false
            && $runtimeAuthorityVerifierRaw !== ''
            && strpos($runtimeAuthorityVerifierRaw, 'RELEASE GATE RUNTIME SOURCE AUTHORITY CONTRACT OK') !== false
            && strpos($runtimeAuthorityVerifierRaw, 'canonical_source_authority') !== false
            && strpos($runtimeAuthorityVerifierRaw, 'wrong_runtime_origin_blocks') !== false
            && strpos($runtimeAuthorityVerifierRaw, 'stale_canonical_runtime_non_blocking') !== false
            && $runtimeAuthorityDocRaw !== ''
            && strpos($runtimeAuthorityDocRaw, 'fieldflow-release-gate-runtime-source-authority-v1') !== false
            && strpos($runtimeAuthorityDocRaw, '`SOURCE_RELEASE` is `2.2.513` and `STABLE_RELEASE` is `2.2.514`') !== false
            && strpos($runtimeAuthorityDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($runtimeAuthorityDocRaw, 'No database schema change.') !== false
            && strpos($runtimeAuthorityDocRaw, 'No certification gate is bypassed or auto-certified.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['release_gate_runtime_source_authority_ok']) {
            $result['error'] = 'A autoridade entre source canónico e runtime PHP não está integralmente protegida contra bytecode stale ou origens duplicadas.';
            return $result;
        }


        // Phase 9A: the Client Portal shell must be shaped before first paint,
        // lazy-panel loading must have a single navigation owner and unloaded
        // panels must inherit the current client/project scope.
        $clientPortalVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-client-portal-shell-stability.php');
        $clientPortalDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9A-CLIENT-PORTAL-SHELL-STABILITY.md');
        $clientPortalRaw = self::entryContents($entries, 'fieldflow/src/Front/ClientPortalShortcodeRenderer.php');
        $uiRuntimeRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-ui-runtime.js');
        $premiumShellRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-premium-shell-v2.js');
        $premiumShellCssRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-premium-shell-v2.css');
        $operationalExperienceRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-operational-experience.js');
        $releaseManagerRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['client_portal_shell_stability_ok'] = $result['release_gate_runtime_source_authority_ok']
            && $clientPortalRaw !== ''
            && strpos($clientPortalRaw, 'fieldflow-client-portal-shell-stability-v1') !== false
            && strpos($clientPortalRaw, 'data-ff2-layout="server"') !== false
            && strpos($clientPortalRaw, 'data-ff2-rail="server"') !== false
            && strpos($clientPortalRaw, 'function switchPanel(key,options={})') !== false
            && strpos($clientPortalRaw, "source:'client-shell'") !== false
            && strpos($clientPortalRaw, "source:'client-shell-module'") !== false
            && strpos($clientPortalRaw, 'panel.hidden=!active') !== false
            && strpos($clientPortalRaw, "setTimeout(()=>target.scrollIntoView({behavior:'smooth'") === false
            && $uiRuntimeRaw !== ''
            && strpos($uiRuntimeRaw, "CLIENT_SHELL_CONTRACT='fieldflow-client-portal-shell-stability-v1'") !== false
            && strpos($uiRuntimeRaw, 'const clientTab=') !== false
            && strpos($uiRuntimeRaw, "source!=='client-shell-module'") !== false
            && strpos($uiRuntimeRaw, "source!=='lazy-loader'") !== false
            && strpos($uiRuntimeRaw, 'syncClientLazyScope') !== false
            && $premiumShellRaw !== ''
            && strpos($premiumShellRaw, "SERVER_SHELL_CONTRACT = 'fieldflow-client-portal-shell-stability-v1'") !== false
            && strpos($premiumShellRaw, 'data-ff2-layout="server"') !== false
            && strpos($premiumShellRaw, 'if (!serverLayout)') !== false
            && $premiumShellCssRaw !== ''
            && strpos($premiumShellCssRaw, 'Phase 9A, Client Portal Shell Stability') !== false
            && $operationalExperienceRaw !== ''
            && strpos($operationalExperienceRaw, "CLIENT_PORTAL_SHELL_STABILITY_CONTRACT='fieldflow-client-portal-shell-stability-v1'") !== false
            && strpos($operationalExperienceRaw, "root.querySelector('.rp-shell > .ff-ope-command-deck')") !== false
            && strpos($operationalExperienceRaw, "type!=='client'") !== false
            && $clientPortalVerifierRaw !== ''
            && strpos($clientPortalVerifierRaw, 'CLIENT PORTAL SHELL STABILITY CONTRACT OK') !== false
            && $clientPortalDocRaw !== ''
            && strpos($clientPortalDocRaw, 'fieldflow-client-portal-shell-stability-v1') !== false
            && strpos($clientPortalDocRaw, '`SOURCE_RELEASE` is `2.2.514` and `STABLE_RELEASE` is `2.2.515`') !== false
            && strpos($clientPortalDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($clientPortalDocRaw, 'No database schema change.') !== false
            && $releaseManagerRaw !== ''
            && strpos($releaseManagerRaw, 'Client Portal Stability') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['client_portal_shell_stability_ok']) {
            $result['error'] = 'O Client Portal Shell Stability não está integralmente ligado.';
            return $result;
        }

        // Phase 9B: client tab continuity, mobile full-width navigation and
        // client-team sender eligibility must be wired as one release contract.
        $clientContinuityVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-client-portal-continuity-fullwidth-messaging.php');
        $clientContinuityDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9B-CLIENT-PORTAL-CONTINUITY-FULLWIDTH-MESSAGING.md');
        $emailsRaw = self::entryContents($entries, 'fieldflow/src/Admin/Emails.php');
        $shortcodesRaw = self::entryContents($entries, 'fieldflow/src/Shortcodes.php');
        $result['client_portal_continuity_ok'] = $result['client_portal_shell_stability_ok']
            && $clientPortalRaw !== ''
            && strpos($clientPortalRaw, 'fieldflow-client-portal-continuity-v1') !== false
            && strpos($clientPortalRaw, 'ff-client-continuity') !== false
            && strpos($clientPortalRaw, 'function keepClientTabVisible(button)') !== false
            && strpos($clientPortalRaw, "bar.scrollTo({left:desired,behavior:'auto'})") !== false
            && strpos($clientPortalRaw, 'window.FieldFlowUI?.compactLayout?.(root)') === false
            && $uiRuntimeRaw !== ''
            && strpos($uiRuntimeRaw, 'Phase 9B: the Client Portal owns its own panel continuity') !== false
            && strpos($uiRuntimeRaw, "if(shell.classList.contains('rp-client-premium')) return;") !== false
            && strpos($uiRuntimeRaw, 'if(!clientTab) window.requestAnimationFrame(()=>compactPortalLayout(shell));') !== false
            && $premiumShellCssRaw !== ''
            && strpos($premiumShellCssRaw, 'Phase 9B, Client Portal Continuity + Full Width') !== false
            && strpos($premiumShellCssRaw, 'fieldflow-client-portal-continuity-v1') !== false
            && strpos($premiumShellCssRaw, 'scrollbar-width:none!important') !== false
            && $emailsRaw !== ''
            && strpos($emailsRaw, 'Permissions::assert_scope_or_error($client_id, $project_id, $logged_user_id)') !== false
            && $shortcodesRaw !== ''
            && strpos($shortcodesRaw, 'senderRes.ok') !== false
            && strpos($shortcodesRaw, 'failureMessage') !== false
            && $clientContinuityVerifierRaw !== ''
            && strpos($clientContinuityVerifierRaw, 'CLIENT PORTAL CONTINUITY CONTRACT OK') !== false
            && $clientContinuityDocRaw !== ''
            && strpos($clientContinuityDocRaw, 'fieldflow-client-portal-continuity-v1') !== false
            && strpos($clientContinuityDocRaw, '`SOURCE_RELEASE` is `2.2.515` and `STABLE_RELEASE` is `2.2.516`') !== false
            && strpos($clientContinuityDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($clientContinuityDocRaw, 'No database schema change.') !== false
            && $releaseManagerRaw !== ''
            && strpos($releaseManagerRaw, 'Client Portal Continuity') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['client_portal_continuity_ok']) {
            $result['error'] = 'O Client Portal Continuity não está integralmente ligado.';
            return $result;
        }

        // Phase 9C: close residual Analytics repaint behaviour and start
        // controlled React visual ownership in Stocks, Shopping and Messages.
        $clientWaveVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-react-client-wave1-analytics-stability.php');
        $clientWaveDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9C-REACT-CLIENT-WAVE1-ANALYTICS-STABILITY.md');
        $reactFoundationRaw = self::entryContents($entries, 'fieldflow/src/Front/ReactFrontFoundation.php');
        $responsiveRaw = self::entryContents($entries, 'fieldflow/src/Front/ResponsiveAssets.php');
        $reactModuleControlsRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/module-controls.ts');
        $frontendBuildRaw = self::entryContents($entries, 'fieldflow/frontend/tools/build.mjs');
        $reactFrontCssRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-react-front.css');
        $result['react_client_wave1_analytics_stability_ok'] = $result['client_portal_continuity_ok']
            && $clientPortalRaw !== ''
            && strpos($clientPortalRaw, 'fieldflow-client-panel-lifecycle-v1') !== false
            && strpos($clientPortalRaw, 'fieldflow-react-client-wave1-v1') !== false
            && strpos($clientPortalRaw, 'function setPanelLifecycle(key,status)') !== false
            && strpos($clientPortalRaw, 'clientAIScopeKey()') !== false
            && strpos($clientPortalRaw, 'await Promise.all([') !== false
            && strpos($clientPortalRaw, 'keepClientTabVisible(activeButton);') !== false
            && $uiRuntimeRaw !== ''
            && strpos($uiRuntimeRaw, 'Height-only changes must not emit a') !== false
            && strpos($uiRuntimeRaw, 'const clientPanelSize=new WeakMap();') !== false
            && strpos($uiRuntimeRaw, 'if(Math.abs(width-previous.width)>3) shouldDispatch=true;') !== false
            && $premiumShellCssRaw !== ''
            && strpos($premiumShellCssRaw, 'fieldflow-client-panel-lifecycle-v1') !== false
            && strpos($premiumShellCssRaw, 'fieldflow-react-client-wave1-v1') !== false
            && $reactFrontCssRaw !== ''
            && strpos($reactFrontCssRaw, 'Phase 9C, React Client Wave 1') !== false
            && strpos($reactFrontCssRaw, 'data-client-wave1="fieldflow-react-client-wave1-v1"') !== false
            && $reactFoundationRaw !== ''
            && strpos($reactFoundationRaw, "CLIENT_WAVE1_CONTRACT = 'fieldflow-react-client-wave1-v1'") !== false
            && $responsiveRaw !== ''
            && strpos($responsiveRaw, 'ReactFrontFoundation::CLIENT_WAVE1_CONTRACT') !== false
            && $reactModuleControlsRaw !== ''
            && strpos($reactModuleControlsRaw, "FIELD_FLOW_REACT_CLIENT_WAVE1_CONTRACT = 'fieldflow-react-client-wave1-v1'") !== false
            && strpos($reactModuleControlsRaw, 'fieldflow:client-panel-lifecycle') !== false
            && strpos($reactModuleControlsRaw, "'data-client-wave1'") !== false
            && $frontendBuildRaw !== ''
            && strpos($frontendBuildRaw, 'reactClientWave1Contract') !== false
            && strpos($frontendBuildRaw, 'clientPanelLifecycleContract') !== false
            && $reactFrontRaw !== ''
            && strpos($reactFrontRaw, 'fieldflow-react-client-wave1-v1') !== false
            && strpos($reactFrontRaw, 'fieldflow-client-panel-lifecycle-v1') !== false
            && $clientWaveVerifierRaw !== ''
            && strpos($clientWaveVerifierRaw, 'REACT CLIENT WAVE 1 ANALYTICS STABILITY CONTRACT OK') !== false
            && strpos($clientWaveVerifierRaw, 'analytics_resize=width_only') !== false
            && strpos($clientWaveVerifierRaw, 'analytics_ai_render=single_commit') !== false
            && $clientWaveDocRaw !== ''
            && strpos($clientWaveDocRaw, 'fieldflow-react-client-wave1-analytics-stability-v1') !== false
            && strpos($clientWaveDocRaw, '`SOURCE_RELEASE` is `2.2.516` and `STABLE_RELEASE` is `2.2.517`') !== false
            && strpos($clientWaveDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($clientWaveDocRaw, 'No database schema change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false
            && $releaseManagerRaw !== ''
            && strpos($releaseManagerRaw, 'React Client Wave 1') !== false;
        if (!$result['react_client_wave1_analytics_stability_ok']) {
            $result['error'] = 'O React Client Wave 1 ou a estabilidade do Analytics não estão integralmente ligados.';
            return $result;
        }

        // Phase 9C2: React Client Wave 2 and explicit Client/Operative
        // render isolation. This closes the remaining cross-portal event and
        // Android WebView compositor ambiguity without moving business logic.
        $clientWave2VerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-react-client-wave2-cross-portal-isolation.php');
        $clientWave2DocRaw = self::entryContents($entries, 'fieldflow/PHASE-9C2-REACT-CLIENT-WAVE2-CROSS-PORTAL-ISOLATION.md');
        $appPortalRaw = self::entryContents($entries, 'fieldflow/src/Front/AppShortcodeRenderer.php');
        $premiumAnalyticsRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-premium-analytics.js');
        $result['react_client_wave2_cross_portal_isolation_ok'] = $result['react_client_wave1_analytics_stability_ok']
            && $clientPortalRaw !== ''
            && strpos($clientPortalRaw, 'data-fieldflow-portal="client"') !== false
            && strpos($clientPortalRaw, 'fieldflow-cross-portal-render-isolation-v1') !== false
            && strpos($clientPortalRaw, 'fieldflow-react-client-wave2-v1') !== false
            && strpos($clientPortalRaw, "portal:'client'") !== false
            && $appPortalRaw !== ''
            && strpos($appPortalRaw, 'data-fieldflow-portal="operative"') !== false
            && strpos($appPortalRaw, 'fieldflow-cross-portal-render-isolation-v1') !== false
            && strpos($appPortalRaw, "portal:'operative'") !== false
            && $uiRuntimeRaw !== ''
            && strpos($uiRuntimeRaw, 'function portalShellFor(node,portal)') !== false
            && strpos($uiRuntimeRaw, 'fieldflow:client-panel-layout') !== false
            && strpos($uiRuntimeRaw, "if(portal!=='client') return;") !== false
            && $premiumAnalyticsRaw !== ''
            && strpos($premiumAnalyticsRaw, 'function eventScope(event)') !== false
            && strpos($premiumAnalyticsRaw, "portal==='client'") !== false
            && strpos($premiumAnalyticsRaw, "portal==='operative'") !== false
            && $operationalExperienceRaw !== ''
            && strpos($operationalExperienceRaw, 'function eventRoot(event)') !== false
            && strpos($operationalExperienceRaw, "portal==='client'") !== false
            && strpos($operationalExperienceRaw, "portal==='operative'") !== false
            && strpos($operationalExperienceRaw, "event.target?.closest?.(ROOTS)||document.querySelector(ROOTS)") === false
            && $premiumShellCssRaw !== ''
            && strpos($premiumShellCssRaw, 'Phase 9C Wave 2 + Cross-Portal Render Isolation') !== false
            && strpos($premiumShellCssRaw, 'fieldflow-react-client-wave2-v1') !== false
            && strpos($premiumShellCssRaw, '-webkit-mask-image:none!important') !== false
            && strpos($premiumShellCssRaw, 'backdrop-filter:none!important') !== false
            && $reactFrontCssRaw !== ''
            && strpos($reactFrontCssRaw, 'Phase 9C, React Client Wave 2 + Cross-Portal Render Isolation') !== false
            && strpos($reactFrontCssRaw, 'data-client-wave2="fieldflow-react-client-wave2-v1"') !== false
            && $reactFoundationRaw !== ''
            && strpos($reactFoundationRaw, "CLIENT_WAVE2_CONTRACT = 'fieldflow-react-client-wave2-v1'") !== false
            && strpos($reactFoundationRaw, "CROSS_PORTAL_ISOLATION_CONTRACT = 'fieldflow-cross-portal-render-isolation-v1'") !== false
            && $responsiveRaw !== ''
            && strpos($responsiveRaw, 'ReactFrontFoundation::CLIENT_WAVE2_CONTRACT') !== false
            && strpos($responsiveRaw, 'ReactFrontFoundation::CROSS_PORTAL_ISOLATION_CONTRACT') !== false
            && $reactModuleControlsRaw !== ''
            && strpos($reactModuleControlsRaw, "FIELD_FLOW_REACT_CLIENT_WAVE2_CONTRACT = 'fieldflow-react-client-wave2-v1'") !== false
            && strpos($reactModuleControlsRaw, "['briefings', 'implementations', 'planner', 'expenses'].includes(props.module)") !== false
            && strpos($reactModuleControlsRaw, "portalScope === 'client'") !== false
            && strpos($reactModuleControlsRaw, "clientPortal.addEventListener('fieldflow:client-panel-lifecycle'") !== false
            && strpos($reactModuleControlsRaw, "'data-client-wave2'") !== false
            && $frontendBuildRaw !== ''
            && strpos($frontendBuildRaw, 'reactClientWave2Contract') !== false
            && strpos($frontendBuildRaw, 'crossPortalIsolationContract') !== false
            && $reactFrontRaw !== ''
            && strpos($reactFrontRaw, 'fieldflow-react-client-wave2-v1') !== false
            && strpos($reactFrontRaw, 'fieldflow-cross-portal-render-isolation-v1') !== false
            && strpos($reactFrontRaw, 'data-client-wave2') !== false
            && $clientWave2VerifierRaw !== ''
            && strpos($clientWave2VerifierRaw, 'REACT CLIENT WAVE 2 CROSS-PORTAL ISOLATION CONTRACT OK') !== false
            && strpos($clientWave2VerifierRaw, 'react_client_wave2=briefings,implementations,planner,expenses') !== false
            && strpos($clientWave2VerifierRaw, 'portal_isolation=client,operative') !== false
            && $clientWave2DocRaw !== ''
            && strpos($clientWave2DocRaw, 'fieldflow-react-client-wave2-cross-portal-isolation-v1') !== false
            && strpos($clientWave2DocRaw, '`SOURCE_RELEASE` is `2.2.517` and `STABLE_RELEASE` is `2.2.518`') !== false
            && strpos($clientWave2DocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($clientWave2DocRaw, 'No database schema change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false
            && $releaseManagerRaw !== ''
            && strpos($releaseManagerRaw, 'React Client Wave 2') !== false;
        if (!$result['react_client_wave2_cross_portal_isolation_ok']) {
            $result['error'] = 'O React Client Wave 2 ou o isolamento entre portais não estão integralmente ligados.';
            return $result;
        }


        // Phase 9D: Client and Operative share the same true-full-width shell
        // geometry and the native WebView covers the first resumed frames with
        // a neutral paint guard before exposing the live portal again.
        $resumeFullwidthVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-app-resume-dual-portal-fullwidth.php');
        $resumeFullwidthDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9D-APP-RESUME-DUAL-PORTAL-TRUE-FULLWIDTH.md');
        $webViewBridgeRaw = self::entryContents($entries, 'fieldflow/src/Mobile/WebViewBridge.php');
        $briefingsFrontRaw = self::entryContents($entries, 'fieldflow/src/Front/BriefingsShortcodeRenderer.php');
        $performanceFrontRaw = self::entryContents($entries, 'fieldflow/src/Front/PerformanceShortcodeRenderer.php');
        $teamInboxFrontRaw = self::entryContents($entries, 'fieldflow/src/Front/TeamInboxShortcodeRenderer.php');
        $result['app_resume_dual_portal_fullwidth_ok'] = $result['react_client_wave2_cross_portal_isolation_ok']
            && $clientPortalRaw !== ''
            && strpos($clientPortalRaw, 'data-fieldflow-layout-contract="fieldflow-dual-portal-true-fullwidth-v1"') !== false
            && strpos($clientPortalRaw, 'data-fieldflow-resume-contract="fieldflow-app-resume-paint-guard-v1"') !== false
            && $appPortalRaw !== ''
            && strpos($appPortalRaw, 'data-fieldflow-layout-contract="fieldflow-dual-portal-true-fullwidth-v1"') !== false
            && strpos($appPortalRaw, 'data-fieldflow-resume-contract="fieldflow-app-resume-paint-guard-v1"') !== false
            && strpos($appPortalRaw, 'margin-left:calc(50% - 50vw + 6px)') === false
            && $reactFoundationRaw !== ''
            && strpos($reactFoundationRaw, "DUAL_PORTAL_LAYOUT_CONTRACT = 'fieldflow-dual-portal-true-fullwidth-v1'") !== false
            && strpos($reactFoundationRaw, "APP_RESUME_PAINT_GUARD_CONTRACT = 'fieldflow-app-resume-paint-guard-v1'") !== false
            && $responsiveRaw !== ''
            && strpos($responsiveRaw, 'ReactFrontFoundation::DUAL_PORTAL_LAYOUT_CONTRACT') !== false
            && strpos($responsiveRaw, 'ReactFrontFoundation::APP_RESUME_PAINT_GUARD_CONTRACT') !== false
            && $uiRuntimeRaw !== ''
            && strpos($uiRuntimeRaw, "DUAL_PORTAL_LAYOUT_CONTRACT='fieldflow-dual-portal-true-fullwidth-v1'") !== false
            && strpos($uiRuntimeRaw, 'fieldflow:operative-panel-layout') !== false
            && strpos($uiRuntimeRaw, 'const portalPanelSize=new WeakMap()') !== false
            && $premiumShellCssRaw !== ''
            && strpos($premiumShellCssRaw, 'Phase 9D, App Resume Stability + Dual Portal True Full Width') !== false
            && strpos($premiumShellCssRaw, 'fieldflow-dual-portal-true-fullwidth-v1') !== false
            && strpos($premiumShellCssRaw, 'fieldflow-app-resume-paint-guard-v1') !== false
            && $webViewBridgeRaw !== ''
            && strpos($webViewBridgeRaw, 'fieldflow-resume-paint-guard') !== false
            && strpos($webViewBridgeRaw, "RESUME_PAINT_GUARD_CONTRACT='fieldflow-app-resume-paint-guard-v1'") !== false
            && strpos($webViewBridgeRaw, "document.addEventListener('visibilitychange'") !== false
            && strpos($webViewBridgeRaw, "stabilizeResumePaint('fieldflow:app-resume')") !== false
            && $briefingsFrontRaw !== '' && strpos($briefingsFrontRaw, '.ff-briefings{width:100%;max-width:none;margin:0;') !== false
            && $performanceFrontRaw !== '' && strpos($performanceFrontRaw, '.ffp-front{width:100%;max-width:none;margin:0;') !== false
            && $teamInboxFrontRaw !== '' && strpos($teamInboxFrontRaw, '.rp-team-inbox{width:100%;max-width:none;margin:0;') !== false
            && $resumeFullwidthVerifierRaw !== ''
            && strpos($resumeFullwidthVerifierRaw, 'APP RESUME DUAL PORTAL FULLWIDTH CONTRACT OK') !== false
            && strpos($resumeFullwidthVerifierRaw, 'layout=client,operative:true_full_width') !== false
            && $resumeFullwidthDocRaw !== ''
            && strpos($resumeFullwidthDocRaw, 'fieldflow-app-resume-dual-portal-true-fullwidth-v1') !== false
            && strpos($resumeFullwidthDocRaw, '`SOURCE_RELEASE` is `2.2.518` and `STABLE_RELEASE` is `2.2.519`') !== false
            && strpos($resumeFullwidthDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($resumeFullwidthDocRaw, 'No database schema change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false
            && $releaseManagerRaw !== ''
            && strpos($releaseManagerRaw, 'Dual Portal Full Width') !== false;
        if (!$result['app_resume_dual_portal_fullwidth_ok']) {
            $result['error'] = 'O App Resume Paint Guard ou o True Full Width dos portais Cliente/Operativo não estão integralmente ligados.';
            return $result;
        }

        // Phase 9D2: field-verified full-width parity on both portals plus
        // a stronger resume guard that hides the live portal while backgrounded,
        // exposes it under the neutral guard, then releases the final frame.
        $fullwidthParityDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9D2-DUAL-PORTAL-FULLWIDTH-PARITY-RESUME-HARDENING.md');
        $fullwidthParityVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-dual-portal-fullwidth-parity-resume-v2.php');
        $result['dual_portal_fullwidth_parity_resume_v2_ok'] = $result['app_resume_dual_portal_fullwidth_ok']
            && $clientPortalRaw !== ''
            && strpos($clientPortalRaw, 'data-fieldflow-fullwidth-parity="fieldflow-dual-portal-fullwidth-parity-v2"') !== false
            && strpos($clientPortalRaw, 'data-fieldflow-resume-hardening="fieldflow-app-resume-paint-guard-v2"') !== false
            && $appPortalRaw !== ''
            && strpos($appPortalRaw, 'data-fieldflow-fullwidth-parity="fieldflow-dual-portal-fullwidth-parity-v2"') !== false
            && strpos($appPortalRaw, 'data-fieldflow-resume-hardening="fieldflow-app-resume-paint-guard-v2"') !== false
            && $reactFoundationRaw !== ''
            && strpos($reactFoundationRaw, "DUAL_PORTAL_FULLWIDTH_PARITY_CONTRACT = 'fieldflow-dual-portal-fullwidth-parity-v2'") !== false
            && strpos($reactFoundationRaw, "APP_RESUME_PAINT_GUARD_V2_CONTRACT = 'fieldflow-app-resume-paint-guard-v2'") !== false
            && $responsiveRaw !== ''
            && strpos($responsiveRaw, 'ReactFrontFoundation::DUAL_PORTAL_FULLWIDTH_PARITY_CONTRACT') !== false
            && strpos($responsiveRaw, 'ReactFrontFoundation::APP_RESUME_PAINT_GUARD_V2_CONTRACT') !== false
            && $premiumShellCssRaw !== ''
            && strpos($premiumShellCssRaw, 'Phase 9D2, Dual Portal Full-Width Parity + Resume Hardening v2.') !== false
            && strpos($premiumShellCssRaw, '#rpcp-commercial-panel') !== false
            && strpos($premiumShellCssRaw, '#rpcp-briefings-panel') !== false
            && strpos($premiumShellCssRaw, '#rpcp-implementations-panel') !== false
            && strpos($premiumShellCssRaw, '#rpcp-stocks-panel') !== false
            && strpos($premiumShellCssRaw, '#rpcp-team-panel') !== false
            && strpos($premiumShellCssRaw, '#rpcp-analytics-panel') !== false
            && strpos($premiumShellCssRaw, '#rp-app-briefings') !== false
            && strpos($premiumShellCssRaw, '#rp-app-academy') !== false
            && strpos($premiumShellCssRaw, '#rp-app-expenses') !== false
            && strpos($premiumShellCssRaw, '#rp-app-messages') !== false
            && strpos($premiumShellCssRaw, '#rp-app-analytics') !== false
            && $webViewBridgeRaw !== ''
            && strpos($webViewBridgeRaw, "RESUME_PAINT_GUARD_V2_CONTRACT='fieldflow-app-resume-paint-guard-v2'") !== false
            && strpos($webViewBridgeRaw, 'ff-app-resume-portal-hidden') !== false
            && strpos($webViewBridgeRaw, 'portal-ready-under-guard') !== false
            && strpos($webViewBridgeRaw, 'content-visibility:hidden!important') !== false
            && $fullwidthParityVerifierRaw !== ''
            && strpos($fullwidthParityVerifierRaw, 'DUAL PORTAL FULLWIDTH PARITY RESUME V2 CONTRACT OK') !== false
            && $fullwidthParityDocRaw !== ''
            && strpos($fullwidthParityDocRaw, 'fieldflow-dual-portal-fullwidth-parity-resume-v2') !== false
            && strpos($fullwidthParityDocRaw, '`SOURCE_RELEASE` is `2.2.519` and `STABLE_RELEASE` is `2.2.520`') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false
            && $releaseManagerRaw !== ''
            && strpos($releaseManagerRaw, 'Dual Portal Parity v2') !== false;
        if (!$result['dual_portal_fullwidth_parity_resume_v2_ok']) {
            $result['error'] = 'A paridade Full Width Cliente/Operativo ou o Resume Guard v2 não estão integralmente ligados.';
            return $result;
        }

        // Phase 9E: remove duplicate presentation chrome from the field-verified
        // Client/Operative panels without changing their DOM ownership, and add
        // feature-flagged native resume media coverage plus bounded diagnostics.
        $uiCompositionDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9E-UI-COMPOSITION-RESUME-DIAGNOSTICS.md');
        $uiCompositionVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-ui-composition-resume-diagnostics.php');
        $result['ui_composition_resume_diagnostics_ok'] = $result['dual_portal_fullwidth_parity_resume_v2_ok']
            && $clientPortalRaw !== ''
            && strpos($clientPortalRaw, 'ff-ui-composition-v1') !== false
            && strpos($clientPortalRaw, 'fieldflow-ui-composition-ownership-v1') !== false
            && strpos($clientPortalRaw, 'data-fieldflow-resume-diagnostics="fieldflow-resume-diagnostics-v1"') !== false
            && $appPortalRaw !== ''
            && strpos($appPortalRaw, 'ff-ui-composition-v1') !== false
            && strpos($appPortalRaw, 'fieldflow-ui-composition-ownership-v1') !== false
            && strpos($appPortalRaw, 'data-fieldflow-resume-diagnostics="fieldflow-resume-diagnostics-v1"') !== false
            && $reactFoundationRaw !== ''
            && strpos($reactFoundationRaw, "UI_COMPOSITION_CONTRACT = 'fieldflow-ui-composition-ownership-v1'") !== false
            && strpos($reactFoundationRaw, "RESUME_DIAGNOSTICS_CONTRACT = 'fieldflow-resume-diagnostics-v1'") !== false
            && $responsiveRaw !== ''
            && strpos($responsiveRaw, 'ReactFrontFoundation::UI_COMPOSITION_CONTRACT') !== false
            && strpos($responsiveRaw, 'ReactFrontFoundation::RESUME_DIAGNOSTICS_CONTRACT') !== false
            && $premiumShellCssRaw !== ''
            && strpos($premiumShellCssRaw, 'Phase 9E, UI Composition Ownership + Resume Diagnostics foundation.') !== false
            && strpos($premiumShellCssRaw, 'fieldflow-ui-composition-ownership-v1') !== false
            && strpos($premiumShellCssRaw, 'border:0!important') !== false
            && strpos($premiumShellCssRaw, 'box-shadow:none!important') !== false
            && $webViewBridgeRaw !== ''
            && strpos($webViewBridgeRaw, "fieldflow_mobile_resume_media_guard_enabled") !== false
            && strpos($webViewBridgeRaw, "RESUME_DIAGNOSTICS_CONTRACT='fieldflow-resume-diagnostics-v1'") !== false
            && strpos($webViewBridgeRaw, 'ff-app-resume-media-hidden') !== false
            && strpos($webViewBridgeRaw, 'iframe,img,object,embed') !== false
            && strpos($webViewBridgeRaw, 'recordResumeDiagnostic') !== false
            && strpos($webViewBridgeRaw, 'media-ready-under-guard') !== false
            && $uiCompositionVerifierRaw !== ''
            && strpos($uiCompositionVerifierRaw, 'UI COMPOSITION RESUME DIAGNOSTICS CONTRACT OK') !== false
            && $uiCompositionDocRaw !== ''
            && strpos($uiCompositionDocRaw, 'fieldflow-ui-composition-resume-diagnostics-v1') !== false
            && strpos($uiCompositionDocRaw, '`SOURCE_RELEASE` is `2.2.520` and `STABLE_RELEASE` is `2.2.521`') !== false
            && strpos($uiCompositionDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($uiCompositionDocRaw, 'No database schema change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false
            && $releaseManagerRaw !== ''
            && strpos($releaseManagerRaw, 'UI Composition &amp; Resume Diagnostics') !== false;
        if (!$result['ui_composition_resume_diagnostics_ok']) {
            $result['error'] = 'The UI Composition or Resume Diagnostics contract is not fully wired into the release.';
            return $result;
        }

        // Phase 9F: structural portal state is present in the server HTML before
        // first paint, lazy modules prepare while the previous panel remains visible,
        // and entry opacity/translate animations are disabled for portal panels.
        $paintStabilityDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9F-SHARED-PORTAL-PAINT-STABILITY.md');
        $paintStabilityVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-shared-portal-paint-stability.php');
        $operationalExperienceRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-operational-experience.js');
        $uiRuntimeRawPaint = self::entryContents($entries, 'fieldflow/assets/fieldflow-ui-runtime.js');
        $result['shared_portal_paint_stability_ok'] = $result['ui_composition_resume_diagnostics_ok']
            && strpos($clientPortalRaw, 'ff-paint-stable-v1') !== false
            && strpos($clientPortalRaw, 'ff-scroll-extent-safe ff-responsive-runtime ff-ope') !== false
            && strpos($clientPortalRaw, 'data-fieldflow-paint-stability=') !== false
            && strpos($clientPortalRaw, 'fieldflow-shared-portal-paint-stability-v1') !== false
            && strpos($clientPortalRaw, 'data-ff-server-command-deck="1"') !== false
            && strpos($appPortalRaw, 'ff-paint-stable-v1') !== false
            && strpos($appPortalRaw, 'ff-scroll-extent-safe ff-responsive-runtime ff-ope') !== false
            && strpos($appPortalRaw, 'data-fieldflow-paint-stability=') !== false
            && strpos($appPortalRaw, 'fieldflow-shared-portal-paint-stability-v1') !== false
            && strpos($appPortalRaw, 'data-ff-server-command-deck="1"') !== false
            && strpos($appPortalRaw, 'applyInitial();') !== false
            && $reactFoundationRaw !== ''
            && strpos($reactFoundationRaw, "PORTAL_PAINT_STABILITY_CONTRACT = 'fieldflow-shared-portal-paint-stability-v1'") !== false
            && $premiumShellCssRaw !== ''
            && strpos($premiumShellCssRaw, 'Phase 9F, Shared Portal Paint Stability.') !== false
            && strpos($premiumShellCssRaw, '.ff-paint-stable-v1') !== false
            && strpos($premiumShellCssRaw, '.ff-lazy-panel[data-ff-paint-ready="1"]') !== false
            && strpos($premiumShellCssRaw, 'animation:none!important') !== false
            && $uiRuntimeRawPaint !== ''
            && strpos($uiRuntimeRawPaint, 'return Promise.all(tasks)') !== false
            && strpos($uiRuntimeRawPaint, "placeholder.dataset.ffPaintReady='1'") !== false
            && $operationalExperienceRaw !== ''
            && strpos($operationalExperienceRaw, "const BUILD='2.2.522'") !== false
            && strpos($operationalExperienceRaw, 'bindCommandDeck(root)') !== false
            && $paintStabilityVerifierRaw !== ''
            && strpos($paintStabilityVerifierRaw, 'SHARED PORTAL PAINT STABILITY CONTRACT OK') !== false
            && $paintStabilityDocRaw !== ''
            && strpos($paintStabilityDocRaw, 'fieldflow-shared-portal-paint-stability-v1') !== false
            && strpos($paintStabilityDocRaw, '`SOURCE_RELEASE` is `2.2.521` and `STABLE_RELEASE` is `2.2.522`') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false
            && $releaseManagerRaw !== ''
            && strpos($releaseManagerRaw, 'Shared Portal Paint Stability') !== false;
        if (!$result['shared_portal_paint_stability_ok']) {
            $result['error'] = 'The Shared Portal Paint Stability contract is not fully wired into the release.';
            return $result;
        }

        // Phase 9G: preserve the certified synchronous first-paint successor while
        // suppressing only the transient zero-item syncing badge on portal entry.
        $entrySyncDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9G-PORTAL-ENTRY-SYNC-VISUAL-CONTINUITY.md');
        $entrySyncVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-portal-entry-sync-visual-continuity.php');
        $canonicalOfflineRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-offline-runtime.js');
        $result['canonical_offline_runtime_identity_ok'] = $canonicalOfflineRaw !== ''
            && strpos($canonicalOfflineRaw, "const RUNTIME_BUILD='" . $result['version'] . "'") !== false
            && $runtimeRaw !== ''
            && hash_equals(hash('sha256', $runtimeRaw), hash('sha256', $canonicalOfflineRaw));
        $result['portal_entry_sync_visual_continuity_ok'] = $result['shared_portal_paint_stability_ok']
            && $result['canonical_offline_runtime_identity_ok']
            && $runtimeRaw !== ''
            && strpos($runtimeRaw, "const RUNTIME_BUILD='" . $result['version'] . "'") !== false
            && strpos($runtimeRaw, "el.hidden=(state==='online'&&!count)||(state==='syncing'&&!count)") !== false
            && strpos($runtimeRaw, "lastSyncResult='syncing';setBanner('syncing',0)") !== false
            && $canonicalOfflineRaw !== ''
            && strpos($canonicalOfflineRaw, "el.hidden=(state==='online'&&!count)||(state==='syncing'&&!count)") !== false
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, 'legacyInitialActivation') !== false
            && strpos($releaseGateRaw, 'successorInitialActivation') !== false
            && strpos($releaseGateRaw, 'checkPortalEntrySyncVisualContract') !== false
            && $entrySyncVerifierRaw !== ''
            && strpos($entrySyncVerifierRaw, 'PORTAL ENTRY SYNC VISUAL CONTINUITY CONTRACT OK') !== false
            && $entrySyncDocRaw !== ''
            && strpos($entrySyncDocRaw, 'fieldflow-portal-entry-sync-visual-v1') !== false
            && strpos($entrySyncDocRaw, '`SOURCE_RELEASE` is `2.2.522` and `STABLE_RELEASE` is `2.2.523`') !== false
            && strpos($entrySyncDocRaw, 'No IndexedDB schema change.') !== false
            && strpos($entrySyncDocRaw, 'No P1-P4 routing or optimisation-rule change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['portal_entry_sync_visual_continuity_ok']) {
            $result['error'] = 'The Portal Entry Sync Visual Continuity contract is not fully wired into the release.';
            return $result;
        }

        // Phase 9H: all FieldFlow email transport is routed through one
        // canonical wp_mail seam and Client Portal Shell certification validates
        // a durable contract marker instead of the operational runtime build number.
        $mailDispatchRaw = self::entryContents($entries, 'fieldflow/src/Services/MailDispatchService.php');
        $mailDispatchDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9H-MAIL-DISPATCH-CERTIFICATION-CONTINUITY.md');
        $mailDispatchVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-mail-dispatch-certification-continuity.php');
        $loaderRaw = self::entryContents($entries, 'fieldflow/src/Support/Loader.php');
        $mailCallerFiles = [
            'fieldflow/src/Observability/AlertService.php','fieldflow/src/Performance/Module.php','fieldflow/src/Services/Notify.php',
            'fieldflow/src/Billing/ProductionHardening.php','fieldflow/src/Billing/BillingService.php','fieldflow/src/Shopping/ShoppingService.php',
            'fieldflow/src/Admin/ReportAutomations.php','fieldflow/src/Admin/Emails.php','fieldflow/src/Admin/ajax.php'
        ];
        $mailCallersOk = true;
        foreach ($mailCallerFiles as $mailCallerFile) {
            $mailCallerRaw = self::entryContents($entries, $mailCallerFile);
            if ($mailCallerRaw === '' || strpos($mailCallerRaw, 'MailDispatchService::') === false || preg_match('/\\bwp_mail\\s*\\(/', $mailCallerRaw)) {
                $mailCallersOk = false;
                break;
            }
        }
        $result['mail_dispatch_certification_continuity_ok'] = $result['portal_entry_sync_visual_continuity_ok']
            && $mailDispatchRaw !== ''
            && strpos($mailDispatchRaw, 'fieldflow-mail-dispatch-foundation-v1') !== false
            && strpos($mailDispatchRaw, "public const BUILD = '2.2.524'") !== false
            && strpos($mailDispatchRaw, 'public static function dispatch(') !== false
            && strpos($mailDispatchRaw, 'wp_mail($to, $subject, $message, $headers, $attachments)') !== false
            && strpos($mailDispatchRaw, 'fieldflow_mail_dispatch_after') !== false
            && $loaderRaw !== ''
            && strpos($loaderRaw, 'src/Services/MailDispatchService.php') !== false
            && $mailCallersOk
            && $operationalExperienceRaw !== ''
            && strpos($operationalExperienceRaw, "const BUILD='2.2.524'") !== false
            && strpos($operationalExperienceRaw, "CLIENT_PORTAL_SHELL_STABILITY_CONTRACT='fieldflow-client-portal-shell-stability-v1'") !== false
            && strpos($operationalExperienceRaw, "const BUILD='2.2.523'") !== false
            && strpos($operationalExperienceRaw, "const BUILD='2.2.522'") !== false
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, 'checkMailDispatchFoundationContract') !== false
            && strpos($releaseGateRaw, "CLIENT_PORTAL_SHELL_STABILITY_CONTRACT='fieldflow-client-portal-shell-stability-v1'") !== false
            && $mailDispatchVerifierRaw !== ''
            && strpos($mailDispatchVerifierRaw, 'MAIL DISPATCH + CERTIFICATION CONTINUITY CONTRACT OK') !== false
            && $mailDispatchDocRaw !== ''
            && strpos($mailDispatchDocRaw, 'fieldflow-mail-dispatch-foundation-v1') !== false
            && strpos($mailDispatchDocRaw, '`SOURCE_RELEASE` is `2.2.523` and `STABLE_RELEASE` is `2.2.524`') !== false
            && strpos($mailDispatchDocRaw, 'No database schema change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['mail_dispatch_certification_continuity_ok']) {
            $result['error'] = 'The Mail Dispatch Foundation or durable Client Portal certification continuity contract is not fully wired into the release.';
            return $result;
        }

        // Phase 9I. The 2.2.524 transport seam remains the historical
        // compatibility contract while 2.2.525 adds persistent reliability.
        $mailOutboxRepoRaw = self::entryContents($entries, 'fieldflow/src/Services/MailOutboxRepository.php');
        $migrationsOutboxRaw = self::entryContents($entries, 'fieldflow/src/Support/Migrations.php');
        $schemaOutboxRaw = self::entryContents($entries, 'fieldflow/src/Support/SchemaManifest.php');
        $runtimeRegistryRaw = self::entryContents($entries, 'fieldflow/src/Architecture/RuntimeRegistry.php');
        $emailsOutboxRaw = self::entryContents($entries, 'fieldflow/src/Admin/Emails.php');
        $reportsOutboxRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReportAutomations.php');
        $billingOutboxRaw = self::entryContents($entries, 'fieldflow/src/Billing/ProductionHardening.php');
        $performanceOutboxRaw = self::entryContents($entries, 'fieldflow/src/Performance/Module.php');
        $mailOutboxDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9I-MAIL-RELIABILITY-OUTBOX-FOUNDATION.md');
        $mailOutboxVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-mail-reliability-outbox.php');
        $result['mail_reliability_outbox_ok'] = $result['mail_dispatch_certification_continuity_ok']
            && version_compare((string)($result['version'] ?? ''), '2.2.525', '>=')
            && strpos($mailDispatchRaw, "OUTBOX_CONTRACT = 'fieldflow-mail-reliability-outbox-v1'") !== false
            && strpos($mailDispatchRaw, "OUTBOX_BUILD = '2.2.525'") !== false
            && strpos($mailDispatchRaw, 'processOutbox') !== false
            && strpos($mailDispatchRaw, 'recoverStaleLocks') !== false
            && strpos($mailDispatchRaw, 'idempotency_key') !== false
            && $mailOutboxRepoRaw !== ''
            && strpos($mailOutboxRepoRaw, "TABLE_SUFFIX = 'mail_outbox'") !== false
            && strpos($mailOutboxRepoRaw, 'function findByIdempotencyKey(') !== false
            && strpos($mailOutboxRepoRaw, 'function claim(') !== false
            && $migrationsOutboxRaw !== ''
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.525'") !== false
            && strpos($migrationsOutboxRaw, 'migrate22525') !== false
            && $schemaOutboxRaw !== ''
            && strpos($schemaOutboxRaw, "'mail_outbox'") !== false
            && $runtimeRegistryRaw !== ''
            && strpos($runtimeRegistryRaw, 'MailDispatchService::registerHooks') !== false
            && $emailsOutboxRaw !== ''
            && strpos($emailsOutboxRaw, "tab==='outbox'") !== false
            && strpos($emailsOutboxRaw, 'renderOutbox') !== false
            && $reportsOutboxRaw !== ''
            && strpos($reportsOutboxRaw, "'idempotency_key']='order_suggestion|") !== false
            && strpos($reportsOutboxRaw, "'idempotency_key'] = 'scheduled_report|") !== false
            && strpos($reportsOutboxRaw, "!empty(\$mailDispatch['accepted'])") !== false
            && $billingOutboxRaw !== ''
            && strpos($billingOutboxRaw, "'idempotency_key'=>'billing_transactional|'") !== false
            && $performanceOutboxRaw !== ''
            && strpos($performanceOutboxRaw, "'idempotency_key'=>'performance_automation|'") !== false
            && strpos($releaseGateRaw, 'checkMailReliabilityOutboxContract') !== false
            && $mailOutboxVerifierRaw !== ''
            && strpos($mailOutboxVerifierRaw, 'MAIL RELIABILITY OUTBOX CONTRACT OK') !== false
            && $mailOutboxDocRaw !== ''
            && strpos($mailOutboxDocRaw, 'fieldflow-mail-reliability-outbox-v1') !== false
            && strpos($mailOutboxDocRaw, '`SOURCE_RELEASE` is `2.2.524` and `STABLE_RELEASE` is `2.2.525`') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['mail_reliability_outbox_ok']) {
            $result['error'] = 'The Mail Reliability / Outbox Foundation contract is not fully wired into the release.';
            return $result;
        }

        // Phase 9J: release gates must follow the current versioned frontend assets,
        // and the 2.2.525 Outbox foundation must remain valid for successor releases.
        $assetSuccessorDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9J-CERTIFICATION-ASSET-SUCCESSOR-CONTINUITY.md');
        $assetSuccessorVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-certification-asset-successor-continuity.php');
        $result['certification_asset_successor_continuity_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.526', '>=')
            && $result['mail_reliability_outbox_ok']
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, "ASSET_SUCCESSOR_CONTRACT = 'fieldflow-certification-asset-successor-continuity-v1'") !== false
            && strpos($releaseGateRaw, "'assets/fieldflow-frontend-core-' . \$release . '.js'") !== false
            && strpos($releaseGateRaw, "'assets/fieldflow-react-front-' . \$release . '.js'") !== false
            && $responsiveRaw !== ''
            && strpos($responsiveRaw, basename($frontendRelative)) !== false
            && strpos($responsiveRaw, basename($reactFrontRelative)) !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.525'") !== false
            && $assetSuccessorVerifierRaw !== ''
            && strpos($assetSuccessorVerifierRaw, 'CERTIFICATION ASSET SUCCESSOR CONTINUITY CONTRACT OK') !== false
            && $assetSuccessorDocRaw !== ''
            && strpos($assetSuccessorDocRaw, 'fieldflow-certification-asset-successor-continuity-v1') !== false
            && strpos($assetSuccessorDocRaw, '`SOURCE_RELEASE` is `2.2.525` and `STABLE_RELEASE` is `2.2.526`') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['certification_asset_successor_continuity_ok']) {
            $result['error'] = 'Certification Asset Successor Continuity is not fully wired into the release.';
            return $result;
        }

        // Phase 9K: Runtime & Database Performance must remain successor-safe.
        // The 2.2.527 implementation removes confirmed Academy query fanout,
        // scopes observer work and adds read-only database footprint telemetry.
        $runtimePerformanceDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9K-RUNTIME-DATABASE-PERFORMANCE.md');
        $runtimePerformanceVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-runtime-database-performance.php');
        $performanceModuleRaw = self::entryContents($entries, 'fieldflow/src/Performance/Module.php');
        $loadReadinessRaw = self::entryContents($entries, 'fieldflow/src/Performance/LoadReadiness.php');
        $securityObservabilityRaw = self::entryContents($entries, 'fieldflow/src/Admin/SecurityObservability.php');
        $healthDiagnosticsRaw = self::entryContents($entries, 'fieldflow/src/Support/SystemHealthDiagnostics.php');
        $reactBootstrapRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/bootstrap.ts');
        $uiRuntimeRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-ui-runtime.js');
        $releaseManagerPerformanceRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['runtime_database_performance_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.527', '>=')
            && $result['certification_asset_successor_continuity_ok']
            && $performanceModuleRaw !== ''
            && strpos($performanceModuleRaw, "PERFORMANCE_BATCH_CONTRACT = 'fieldflow-academy-batch-read-v1'") !== false
            && strpos($performanceModuleRaw, 'COUNT(DISTINCT lesson_id) lessons_done') !== false
            && strpos($performanceModuleRaw, 'SELECT course_id,user_id,COUNT(DISTINCT lesson_id) done') !== false
            && strpos($performanceModuleRaw, '$projectUserIdsCache') !== false
            && strpos($performanceModuleRaw, '$clientUserIdsCache') !== false
            && $loadReadinessRaw !== ''
            && strpos($loadReadinessRaw, "VERSION = '1.2.0'") !== false
            && strpos($loadReadinessRaw, 'databaseFootprint') !== false
            && strpos($loadReadinessRaw, 'SHOW TABLE STATUS LIKE %s') !== false
            && strpos($loadReadinessRaw, "'largest_tables'") !== false
            && $securityObservabilityRaw !== ''
            && strpos($securityObservabilityRaw, 'Footprint FieldFlow') !== false
            && $healthDiagnosticsRaw !== ''
            && strpos($healthDiagnosticsRaw, "'database_profile'") !== false
            && $reactBootstrapRaw !== ''
            && strpos($reactBootstrapRaw, 'containsUnmountedIsland') !== false
            && $uiRuntimeRaw !== ''
            && strpos($uiRuntimeRaw, 'pendingEnhanceScopes') !== false
            && strpos($uiRuntimeRaw, 'scopes.forEach((scope)=>enhance(scope))') !== false
            && $migrationsOutboxRaw !== ''
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.525'") !== false
            && $runtimePerformanceVerifierRaw !== ''
            && strpos($runtimePerformanceVerifierRaw, 'RUNTIME DATABASE PERFORMANCE CONTRACT OK') !== false
            && $runtimePerformanceDocRaw !== ''
            && strpos($runtimePerformanceDocRaw, 'fieldflow-runtime-database-performance-v1') !== false
            && strpos($runtimePerformanceDocRaw, '`SOURCE_RELEASE` is `2.2.526` and `STABLE_RELEASE` is `2.2.527`') !== false
            && strpos($runtimePerformanceDocRaw, 'No database schema change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, "RUNTIME_DATABASE_PERFORMANCE_CONTRACT = 'fieldflow-runtime-database-performance-v1'") !== false
            && $releaseManagerPerformanceRaw !== ''
            && strpos($releaseManagerPerformanceRaw, 'Runtime &amp; Database Performance') !== false;
        if (!$result['runtime_database_performance_ok']) {
            $result['error'] = 'Runtime & Database Performance is not fully wired into the release.';
            return $result;
        }

        // Phase 9L: explicit tenant scope, private temporary storage governance and
        // same-origin WebView fallback messaging. The contract is successor-safe.
        $securityStorageDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9L-SECURITY-STORAGE-HARDENING.md');
        $securityStorageVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-security-storage-hardening.php');
        $restScopeRaw = self::entryContents($entries, 'fieldflow/src/Security/RestScopeGuard.php');
        $storageGovernanceRaw = self::entryContents($entries, 'fieldflow/src/Security/StorageGovernance.php');
        $mobileControllerSecurityRaw = self::entryContents($entries, 'fieldflow/src/Mobile/Controller.php');
        $webViewSecurityRaw = self::entryContents($entries, 'fieldflow/src/Mobile/WebViewBridge.php');
        $systemHealthSecurityRaw = self::entryContents($entries, 'fieldflow/src/Support/SystemHealth.php');
        $loaderSecurityRaw = self::entryContents($entries, 'fieldflow/src/Support/Loader.php');
        $runtimeRegistrySecurityRaw = self::entryContents($entries, 'fieldflow/src/Architecture/RuntimeRegistry.php');
        $releaseManagerSecurityRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['security_storage_hardening_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.528', '>=')
            && $result['runtime_database_performance_ok']
            && $restScopeRaw !== ''
            && strpos($restScopeRaw, "CONTRACT = 'fieldflow-rest-scope-isolation-v1'") !== false
            && strpos($restScopeRaw, "add_filter('rest_pre_dispatch'") !== false
            && strpos($restScopeRaw, 'fieldflow_scope_context_conflict') !== false
            && strpos($restScopeRaw, 'fieldflow_scope_client_denied') !== false
            && strpos($restScopeRaw, 'fieldflow_scope_project_denied') !== false
            && strpos($restScopeRaw, 'public static function decision') !== false
            && $storageGovernanceRaw !== ''
            && strpos($storageGovernanceRaw, "CONTRACT = 'fieldflow-private-storage-governance-v1'") !== false
            && strpos($storageGovernanceRaw, "CRON_HOOK = 'fieldflow_private_storage_governance_daily'") !== false
            && strpos($storageGovernanceRaw, 'cleanupExpiredTemporaryStorage') !== false
            && strpos($storageGovernanceRaw, 'expired_resumable_only') !== false
            && strpos($storageGovernanceRaw, 'fieldflow-private-resumable') !== false
            && strpos($mobileControllerSecurityRaw, "'permission_callback' => [\$this, 'submission_access']") !== false
            && strpos($mobileControllerSecurityRaw, "'permission_callback' => [\$this, 'briefing_access']") !== false
            && strpos($webViewSecurityRaw, "PARENT_ORIGIN_CONTRACT = 'fieldflow-webview-parent-origin-v1'") !== false
            && strpos($webViewSecurityRaw, 'function nativeParentOrigin()') !== false
            && strpos($webViewSecurityRaw, "postMessage(message,'*')") === false
            && strpos($webViewSecurityRaw, 'postMessage(message,"*")') === false
            && strpos($systemHealthSecurityRaw, "'rest_scope_guard' => \$rest_scope_guard") !== false
            && strpos($systemHealthSecurityRaw, "'storage_governance' => \$storage_governance") !== false
            && strpos($loaderSecurityRaw, 'src/Security/RestScopeGuard.php') !== false
            && strpos($loaderSecurityRaw, 'src/Security/StorageGovernance.php') !== false
            && strpos($runtimeRegistrySecurityRaw, 'RestScopeGuard::registerHooks()') !== false
            && strpos($runtimeRegistrySecurityRaw, 'StorageGovernance::registerHooks()') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.525'") !== false
            && $securityStorageVerifierRaw !== ''
            && strpos($securityStorageVerifierRaw, 'SECURITY STORAGE HARDENING CONTRACT OK') !== false
            && $securityStorageDocRaw !== ''
            && strpos($securityStorageDocRaw, 'fieldflow-security-storage-hardening-v1') !== false
            && strpos($securityStorageDocRaw, '`SOURCE_RELEASE` is `2.2.527` and `STABLE_RELEASE` is `2.2.528`') !== false
            && strpos($securityStorageDocRaw, 'No database schema change.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, "SECURITY_STORAGE_HARDENING_CONTRACT = 'fieldflow-security-storage-hardening-v1'") !== false
            && $releaseManagerSecurityRaw !== ''
            && strpos($releaseManagerSecurityRaw, 'Security &amp; Storage Hardening') !== false;
        if (!$result['security_storage_hardening_ok']) {
            $result['error'] = 'Security & Storage Hardening is not fully wired into the release.';
            return $result;
        }

        // Phase 9M: AI FinOps attribution, scoped budgets, presentation-only
        // operational React v2 and completion of same-origin WebView fallbacks.
        // This contract is deliberately successor-safe.
        $aiGovernanceModernizationDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9M-AI-GOVERNANCE-MODERNIZATION-CONSOLIDATION.md');
        $aiGovernanceModernizationVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-ai-governance-modernization.php');
        $aiGovernanceRaw = self::entryContents($entries, 'fieldflow/src/AI/Governance.php');
        $aiUsageRepositoryRaw = self::entryContents($entries, 'fieldflow/src/Repositories/AIUsageRepository.php');
        $aiServiceRaw = self::entryContents($entries, 'fieldflow/src/Services/AI.php');
        $aiControlCenterRaw = self::entryContents($entries, 'fieldflow/src/Admin/AIControlCenter.php');
        $configAiRaw = self::entryContents($entries, 'fieldflow/src/Support/Config.php');
        $schemaManifestAiRaw = self::entryContents($entries, 'fieldflow/src/Support/SchemaManifest.php');
        $reactFrontFoundationAiRaw = self::entryContents($entries, 'fieldflow/src/Front/ReactFrontFoundation.php');
        $reactFrontTypesAiRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/types.ts');
        $reactModuleControlsAiRaw = self::entryContents($entries, 'fieldflow/frontend/src/react-front/module-controls.ts');
        $expensesRendererAiRaw = self::entryContents($entries, 'fieldflow/src/Front/ExpensesShortcodeRenderer.php');
        $uiRuntimeAiRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-ui-runtime.js');
        $systemHealthAiRaw = self::entryContents($entries, 'fieldflow/src/Support/SystemHealth.php');
        $releaseManagerAiRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['ai_governance_modernization_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.529', '>=')
            && $result['security_storage_hardening_ok']
            && $aiGovernanceRaw !== ''
            && strpos($aiGovernanceRaw, "VERSION = '2.0'") !== false
            && strpos($aiGovernanceRaw, "CONTRACT = 'fieldflow-ai-governance-finops-v2'") !== false
            && strpos($aiGovernanceRaw, 'ALERT_THRESHOLDS = [70, 85, 100]') !== false
            && strpos($aiGovernanceRaw, 'budgetSnapshot') !== false
            && strpos($aiGovernanceRaw, 'reconcileBudgetAlerts') !== false
            && strpos($aiGovernanceRaw, 'fieldflow_ai_budget_threshold') !== false
            && $aiUsageRepositoryRaw !== ''
            && strpos($aiUsageRepositoryRaw, 'monthBreakdown') !== false
            && strpos($aiUsageRepositoryRaw, 'monthCost(array $scope = [])') !== false
            && strpos($aiUsageRepositoryRaw, 'hasAttributionColumns') !== false
            && strpos($aiUsageRepositoryRaw, 'feature_key') !== false
            && strpos($aiUsageRepositoryRaw, 'request_kind') !== false
            && $aiServiceRaw !== ''
            && strpos($aiServiceRaw, 'resolveAttribution') !== false
            && strpos($aiServiceRaw, 'fieldflow_attribution') !== false
            && strpos($aiServiceRaw, 'Governance::reconcileBudgetAlerts') !== false
            && $aiControlCenterRaw !== ''
            && strpos($aiControlCenterRaw, 'AI Governance 2.0') !== false
            && strpos($aiControlCenterRaw, 'FinOps mensal') !== false
            && strpos($aiControlCenterRaw, 'renderFinopsBreakdown') !== false
            && strpos($configAiRaw, "'ai_client_monthly_budget_eur' => 0") !== false
            && strpos($configAiRaw, "'ai_project_monthly_budget_eur' => 0") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($migrationsOutboxRaw, "'2.2.529' => [self::class, 'migrate22529']") !== false
            && strpos($migrationsOutboxRaw, 'migrate22529') !== false
            && $schemaManifestAiRaw !== ''
            && strpos($schemaManifestAiRaw, "VERSION = '1.11.0'") !== false
            && strpos($schemaManifestAiRaw, "'client_created_idx'") !== false
            && strpos($schemaManifestAiRaw, "'project_created_idx'") !== false
            && strpos($schemaManifestAiRaw, "'feature_created_idx'") !== false
            && strpos($schemaManifestAiRaw, "'request_kind_idx'") !== false
            && $reactFrontFoundationAiRaw !== ''
            && strpos($reactFrontFoundationAiRaw, "OPERATIONAL_MODULES_V2_CONTRACT = 'fieldflow-react-operational-modules-v2'") !== false
            && strpos($reactFrontTypesAiRaw, 'operationalModulesV2Contract?: string') !== false
            && strpos($reactModuleControlsAiRaw, "FIELD_FLOW_REACT_OPERATIONAL_MODULES_V2_CONTRACT = 'fieldflow-react-operational-modules-v2'") !== false
            && strpos($reactModuleControlsAiRaw, 'fieldflow:operational-module-refresh') !== false
            && strpos($reactModuleControlsAiRaw, 'react-controls-v2') !== false
            && strpos($reactFrontRaw, 'fieldflow-react-operational-modules-v2') !== false
            && strpos($reactFrontRaw, 'fieldflow:operational-module-refresh') !== false
            && strpos($formRendererRaw, 'ffParentOrigin') !== false
            && strpos($formRendererRaw, 'window.location.origin') !== false
            && strpos($expensesRendererAiRaw, 'ffParentOrigin') !== false
            && strpos($expensesRendererAiRaw, 'window.location.origin') !== false
            && strpos($uiRuntimeAiRaw, 'parentOrigin') !== false
            && strpos($uiRuntimeAiRaw, 'window.location.origin') !== false
            && !preg_match('/postMessage\([^\n;]*,[\'"]\*[\'"]\)/', $formRendererRaw)
            && !preg_match('/postMessage\([^\n;]*,[\'"]\*[\'"]\)/', $expensesRendererAiRaw)
            && !preg_match('/postMessage\([^\n;]*,[\'"]\*[\'"]\)/', $uiRuntimeAiRaw)
            && strpos($systemHealthAiRaw, 'budget_usage_percent') !== false
            && $aiGovernanceModernizationVerifierRaw !== ''
            && strpos($aiGovernanceModernizationVerifierRaw, 'AI GOVERNANCE MODERNIZATION CONTRACT OK') !== false
            && strpos($aiGovernanceModernizationVerifierRaw, 'successor=>=2.2.529') !== false
            && $aiGovernanceModernizationDocRaw !== ''
            && strpos($aiGovernanceModernizationDocRaw, 'fieldflow-ai-governance-modernization-wave-v1') !== false
            && strpos($aiGovernanceModernizationDocRaw, '`SOURCE_RELEASE` is `2.2.528` and `STABLE_RELEASE` is `2.2.529`') !== false
            && strpos($aiGovernanceModernizationDocRaw, '`LATEST_SCHEMA_VERSION` is `2.2.529`.') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, "AI_GOVERNANCE_MODERNIZATION_CONTRACT = 'fieldflow-ai-governance-modernization-wave-v1'") !== false
            && $releaseManagerAiRaw !== ''
            && strpos($releaseManagerAiRaw, 'AI Governance &amp; Modernization') !== false;
        if (!$result['ai_governance_modernization_ok']) {
            $result['error'] = 'AI Governance & Modernization Consolidation is not fully wired into the release.';
            return $result;
        }


        // 2.2.530 fixes a runtime Release Gate false negative: index ownership belongs
        // to Migrations/SchemaManifest, not AIUsageRepository source markers.
        $aiCertificationContinuityDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9N-AI-GOVERNANCE-CERTIFICATION-CONTINUITY.md');
        $aiCertificationContinuityVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-ai-governance-certification-continuity.php');
        $result['ai_governance_certification_continuity_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.530', '>=')
            && $result['ai_governance_modernization_ok']
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, "AI_GOVERNANCE_CERTIFICATION_CONTINUITY_CONTRACT = 'fieldflow-ai-governance-certification-continuity-v1'") !== false
            && strpos($releaseGateRaw, 'checkAiGovernanceCertificationContinuityContract') !== false
            && $aiCertificationContinuityDocRaw !== ''
            && strpos($aiCertificationContinuityDocRaw, 'fieldflow-ai-governance-certification-continuity-v1') !== false
            && strpos($aiCertificationContinuityDocRaw, '`SOURCE_RELEASE` is `2.2.529` and `STABLE_RELEASE` is `2.2.530`') !== false
            && strpos($aiCertificationContinuityDocRaw, '`LATEST_SCHEMA_VERSION` remains `2.2.529`.') !== false
            && $aiCertificationContinuityVerifierRaw !== ''
            && strpos($aiCertificationContinuityVerifierRaw, 'AI GOVERNANCE CERTIFICATION CONTINUITY CONTRACT OK') !== false
            && strpos($aiCertificationContinuityVerifierRaw, 'successor=>=2.2.530') !== false
            && $result['release_continuity_ok']
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['ai_governance_certification_continuity_ok']) {
            $result['error'] = 'AI Governance Certification Continuity is not fully wired into the release.';
            return $result;
        }

        // 2.2.531 Operational Modernization Wave: React owns the visible control-shell
        // lifecycle of Briefings, Stocks, Shopping and Messages while certified PHP/domain
        // owners remain canonical for business content, submissions and side effects.
        $operationalModernizationDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9O-OPERATIONAL-MODERNIZATION-WAVE.md');
        $operationalModernizationVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-operational-modernization-wave.php');
        $premiumShellRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-premium-shell-v2.css');
        $stockServiceRaw = self::entryContents($entries, 'fieldflow/src/Stocks/StockService.php');
        $shoppingServiceRaw = self::entryContents($entries, 'fieldflow/src/Shopping/ShoppingService.php');
        $result['operational_modernization_wave_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.531', '>=')
            && $result['ai_governance_certification_continuity_ok']
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, "OPERATIONAL_MODERNIZATION_CONTRACT = 'fieldflow-operational-modernization-wave-v1'") !== false
            && strpos($releaseGateRaw, 'checkOperationalModernizationWaveContract') !== false
            && $reactFrontFoundationRaw !== ''
            && strpos($reactFrontFoundationRaw, "OPERATIONAL_MODERNIZATION_CONTRACT = 'fieldflow-operational-modernization-wave-v1'") !== false
            && $operationalModulesSourceRaw !== ''
            && strpos($operationalModulesSourceRaw, 'fieldflow-operational-modernization-wave-v1') !== false
            && strpos($operationalModulesSourceRaw, 'react-event-shell-9o') !== false
            && strpos($operationalModulesSourceRaw, 'if (!modernized)') !== false
            && strpos($operationalModulesSourceRaw, 'fieldflow:operational-module-refresh') !== false
            && $reactFrontRaw !== ''
            && strpos($reactFrontRaw, 'fieldflow-operational-modernization-wave-v1') !== false
            && strpos($reactFrontRaw, 'react-event-shell-9o') !== false
            && substr_count($briefingsRendererRaw, "operationalModuleMount(\$id, 'briefings'") >= 2
            && substr_count($stocksRendererRaw, "operationalModuleMount(\$id, 'stocks'") >= 2
            && strpos($briefingsRendererRaw, 'fieldflow_briefings_v2_') !== false
            && strpos($shoppingRendererRaw, 'fieldflow:shopping:draft:v2:') !== false
            && strpos($shoppingRendererRaw, 'legacyLocalKey') !== false
            && strpos($shoppingRendererRaw, "root.addEventListener('routespro:panel-open'") === false
            && strpos($stocksRendererRaw, "root.addEventListener('routespro:panel-open'") === false
            && strpos($messagesRendererRaw, "module:'messages'") !== false
            && strpos($messagesRendererRaw, "['http:','https:','mailto:','tel:']") !== false
            && strpos($stockServiceRaw, 'GROUP BY batch_id') !== false
            && strpos($stockServiceRaw, 'viewedByBatch') !== false
            && strpos($shoppingServiceRaw, '$projectUserCache') !== false
            && strpos($premiumShellRaw, 'fieldflow-operational-modernization-wave-v1') !== false
            && strpos($premiumShellRaw, '#rp-app-stocks') !== false
            && strpos($premiumShellRaw, '#rp-app-shopping') !== false
            && $operationalModernizationDocRaw !== ''
            && strpos($operationalModernizationDocRaw, '`SOURCE_RELEASE` is `2.2.530` and `STABLE_RELEASE` is `2.2.531`') !== false
            && strpos($operationalModernizationDocRaw, '`LATEST_SCHEMA_VERSION` remains `2.2.529`.') !== false
            && $operationalModernizationVerifierRaw !== ''
            && strpos($operationalModernizationVerifierRaw, 'OPERATIONAL MODERNIZATION WAVE CONTRACT OK') !== false
            && strpos($operationalModernizationVerifierRaw, 'successor=>=2.2.531') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['operational_modernization_wave_ok']) {
            $result['error'] = 'Operational Modernization Wave is not fully wired into the release.';
            return $result;
        }

        // 2.2.532 Frontend & Runtime Certification Continuity: the frontend package
        // identity and canonical offline runtime must track the current release so
        // historical gates cannot block a valid successor after publication.
        $frontendRuntimeContinuityDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9P-FRONTEND-RUNTIME-CERTIFICATION-CONTINUITY.md');
        $frontendRuntimeContinuityVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-frontend-runtime-certification-continuity.php');
        $result['frontend_runtime_certification_continuity_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.532', '>=')
            && $result['operational_modernization_wave_ok']
            && $result['frontend_package_identity_ok']
            && $result['canonical_offline_runtime_identity_ok']
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, "FRONTEND_RUNTIME_CERTIFICATION_CONTINUITY_CONTRACT = 'fieldflow-frontend-runtime-certification-continuity-v1'") !== false
            && strpos($releaseGateRaw, 'checkFrontendRuntimeCertificationContinuityContract') !== false
            && strpos($releaseGateRaw, '"version": "\' . $release . \'"') !== false
            && $frontendRuntimeContinuityDocRaw !== ''
            && strpos($frontendRuntimeContinuityDocRaw, 'fieldflow-frontend-runtime-certification-continuity-v1') !== false
            && strpos($frontendRuntimeContinuityDocRaw, '`SOURCE_RELEASE` is `2.2.531` and `STABLE_RELEASE` is `2.2.532`') !== false
            && strpos($frontendRuntimeContinuityDocRaw, '`LATEST_SCHEMA_VERSION` remains `2.2.529`.') !== false
            && $frontendRuntimeContinuityVerifierRaw !== ''
            && strpos($frontendRuntimeContinuityVerifierRaw, 'FRONTEND RUNTIME CERTIFICATION CONTINUITY CONTRACT OK') !== false
            && strpos($frontendRuntimeContinuityVerifierRaw, 'successor=>=2.2.532') !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['frontend_runtime_certification_continuity_ok']) {
            $result['error'] = 'Frontend & Runtime Certification Continuity is not fully wired into the release.';
            return $result;
        }

        // 2.2.533 Operational Modernization Wave 2: Academy and Expenses join
        // event-driven React ownership while Analytics removes its global observer.
        // Academy front reads are batched without changing completion/write semantics.
        $operationalModernizationWave2DocRaw = self::entryContents($entries, 'fieldflow/PHASE-9Q-OPERATIONAL-MODERNIZATION-WAVE2.md');
        $operationalModernizationWave2VerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-operational-modernization-wave2.php');
        $performanceRendererRaw = self::entryContents($entries, 'fieldflow/src/Front/PerformanceShortcodeRenderer.php');
        $performanceModuleRaw = self::entryContents($entries, 'fieldflow/src/Performance/Module.php');
        $expensesRendererWave2Raw = self::entryContents($entries, 'fieldflow/src/Front/ExpensesShortcodeRenderer.php');
        $premiumAnalyticsWave2Raw = self::entryContents($entries, 'fieldflow/assets/fieldflow-premium-analytics.js');
        $shortcodesWave2Raw = self::entryContents($entries, 'fieldflow/src/Shortcodes.php');
        $appRendererWave2Raw = self::entryContents($entries, 'fieldflow/src/Front/AppShortcodeRenderer.php');
        $result['operational_modernization_wave2_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.533', '>=')
            && $result['frontend_runtime_certification_continuity_ok']
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, "OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT = 'fieldflow-operational-modernization-wave2-v1'") !== false
            && strpos($releaseGateRaw, 'checkOperationalModernizationWave2Contract') !== false
            && $reactFrontFoundationRaw !== ''
            && strpos($reactFrontFoundationRaw, "OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT = 'fieldflow-operational-modernization-wave2-v1'") !== false
            && strpos($reactFrontFoundationRaw, 'operationalModernizationWave2Contract') !== false
            && $operationalModulesSourceRaw !== ''
            && strpos($operationalModulesSourceRaw, 'fieldflow-operational-modernization-wave2-v1') !== false
            && strpos($operationalModulesSourceRaw, 'react-event-shell-9q') !== false
            && strpos($operationalModulesSourceRaw, "['academy', 'expenses'].includes(props.module)") !== false
            && strpos($operationalModulesSourceRaw, 'if (!modernized)') !== false
            && $reactFrontRaw !== ''
            && strpos($reactFrontRaw, 'fieldflow-operational-modernization-wave2-v1') !== false
            && strpos($reactFrontRaw, 'react-event-shell-9q') !== false
            && $performanceRendererRaw !== ''
            && strpos($performanceRendererRaw, "operationalModuleMount(\$id, 'academy'") !== false
            && strpos($performanceRendererRaw, 'academy_front_state') !== false
            && strpos($performanceRendererRaw, '$wpdb->get_var') === false
            && $performanceModuleRaw !== ''
            && strpos($performanceModuleRaw, "ACADEMY_FRONT_BATCH_CONTRACT = 'fieldflow-academy-front-batch-v2'") !== false
            && strpos($performanceModuleRaw, 'academy_front_state') !== false
            && strpos($performanceModuleRaw, 'lessons_by_course') !== false
            && strpos($performanceModuleRaw, 'mission_status_by_id') !== false
            && $expensesRendererWave2Raw !== ''
            && strpos($expensesRendererWave2Raw, 'notifyOperationalRefresh') !== false
            && strpos($expensesRendererWave2Raw, "module:'expenses'") !== false
            && strpos($expensesRendererWave2Raw, "root.addEventListener('routespro:panel-open'") !== false
            && $premiumAnalyticsWave2Raw !== ''
            && strpos($premiumAnalyticsWave2Raw, 'new MutationObserver') === false
            && strpos($premiumAnalyticsWave2Raw, 'fieldflow:analytics-rendered') !== false
            && $shortcodesWave2Raw !== ''
            && strpos($shortcodesWave2Raw, 'fieldflow:analytics-rendered') !== false
            && strpos($shortcodesWave2Raw, 'fieldflow-operational-modernization-wave2-v1') !== false
            && $appRendererWave2Raw !== ''
            && strpos($appRendererWave2Raw, '.ffp-academy') !== false
            && $premiumShellRaw !== ''
            && strpos($premiumShellRaw, 'fieldflow-operational-modernization-wave2-v1') !== false
            && strpos($premiumShellRaw, '#rp-app-academy') !== false
            && strpos($premiumShellRaw, '#rp-app-expenses') !== false
            && strpos($premiumShellRaw, '#rp-app-analytics') !== false
            && $operationalModernizationWave2DocRaw !== ''
            && strpos($operationalModernizationWave2DocRaw, 'fieldflow-operational-modernization-wave2-v1') !== false
            && strpos($operationalModernizationWave2DocRaw, '`SOURCE_RELEASE` is `2.2.532` and `STABLE_RELEASE` is `2.2.533`') !== false
            && strpos($operationalModernizationWave2DocRaw, '`LATEST_SCHEMA_VERSION` remains `2.2.529`.') !== false
            && $operationalModernizationWave2VerifierRaw !== ''
            && strpos($operationalModernizationWave2VerifierRaw, 'OPERATIONAL MODERNIZATION WAVE 2 CONTRACT OK') !== false
            && strpos($operationalModernizationWave2VerifierRaw, 'successor=>=2.2.533') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['operational_modernization_wave2_ok']) {
            $result['error'] = 'Operational Modernization Wave 2 is not fully wired into the release.';
            return $result;
        }


        // 2.2.534 Field Form Terrain Continuity: restricted field hotfix for
        // Cardex/wizard continuity and durable draft recovery across native resume.
        $terrainDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9R-FIELD-FORM-TERRAIN-CONTINUITY.md');
        $terrainVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-field-form-terrain-continuity.php');
        $terrainFormRaw = self::entryContents($entries, 'fieldflow/src/Forms/FormRenderer.php');
        $terrainDraftRaw = self::entryContents($entries, 'fieldflow/src/Forms/DraftService.php');
        $saveBeforeReflow = false;
        if ($terrainFormRaw !== '') {
            $savePos = strpos($terrainFormRaw, 'scheduleDraftSave();');
            $flowPos = $savePos === false ? false : strpos($terrainFormRaw, 'refreshConditionalFlow();', $savePos);
            $saveBeforeReflow = $savePos !== false && $flowPos !== false && $flowPos > $savePos;
        }
        $result['field_form_terrain_continuity_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.534', '>=')
            && $result['operational_modernization_wave2_ok']
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, "FIELD_FORM_TERRAIN_CONTINUITY_CONTRACT = 'fieldflow-field-form-terrain-continuity-v1'") !== false
            && strpos($releaseGateRaw, 'checkFieldFormTerrainContinuityContract') !== false
            && $terrainFormRaw !== ''
            && strpos($terrainFormRaw, "RP_TERRAIN_CONTINUITY_CONTRACT='fieldflow-field-form-terrain-continuity-v1'") !== false
            && strpos($terrainFormRaw, 'var RP_DRAFT_SERVER_DELAY=900;') !== false
            && strpos($terrainFormRaw, 'function draftRecoveryKey()') !== false
            && strpos($terrainFormRaw, 'function draftServerBeacon(snapshot)') !== false
            && strpos($terrainFormRaw, 'opts.lifecycle&&draftServerBeacon(snapshot)') !== false
            && strpos($terrainFormRaw, "if(String(e.key||'')!=='Enter') return;") !== false
            && strpos($terrainFormRaw, 'e.preventDefault();e.stopPropagation();') !== false
            && strpos($terrainFormRaw, 'if(currentlyShown===show) return;') !== false
            && strpos($terrainFormRaw, 'if(visibleIndexes.indexOf(index)===-1)') !== false
            && strpos($terrainFormRaw, '_rpPrimaryKey') !== false
            && $saveBeforeReflow
            && $terrainDraftRaw !== ''
            && strpos($terrainDraftRaw, "TERRAIN_CONTINUITY_CONTRACT = 'fieldflow-field-form-terrain-continuity-v1'") !== false
            && strpos($terrainDraftRaw, 'find_recovery_scope_row') !== false
            && strpos($terrainDraftRaw, 'count($rows) !== 1') !== false
            && strpos($terrainDraftRaw, "'scope_rebased' => \$scope_rebased") !== false
            && $terrainDocRaw !== ''
            && strpos($terrainDocRaw, 'fieldflow-field-form-terrain-continuity-v1') !== false
            && strpos($terrainDocRaw, '`SOURCE_RELEASE` is `2.2.533` and `STABLE_RELEASE` is `2.2.534`') !== false
            && strpos($terrainDocRaw, '`LATEST_SCHEMA_VERSION` remains `2.2.529`.') !== false
            && $terrainVerifierRaw !== ''
            && strpos($terrainVerifierRaw, 'FIELD FORM TERRAIN CONTINUITY CONTRACT OK') !== false
            && strpos($terrainVerifierRaw, 'successor=>=2.2.534') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['field_form_terrain_continuity_ok']) {
            $result['error'] = 'Field Form Terrain Continuity is not fully wired into the release.';
            return $result;
        }

        // 2.2.535 Field Session & Form Continuity: emergency terrain hotfix
        // for rotating mobile refresh, WebView resume and order-suggestion shell.
        $fieldSessionDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9S-FIELD-SESSION-FORM-CONTINUITY.md');
        $fieldSessionVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-field-session-form-continuity.php');
        $fieldSessionAuthRaw = self::entryContents($entries, 'fieldflow/src/Mobile/Auth.php');
        $fieldSessionBridgeRaw = self::entryContents($entries, 'fieldflow/src/Mobile/WebViewBridge.php');
        $fieldSessionPolicyRaw = self::entryContents($entries, 'fieldflow/src/Security/SessionPolicy.php');
        $fieldSessionFormRaw = self::entryContents($entries, 'fieldflow/src/Forms/FormRenderer.php');
        $result['field_session_form_continuity_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.535', '>=')
            && $result['field_form_terrain_continuity_ok']
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, "FIELD_SESSION_FORM_CONTINUITY_CONTRACT = 'fieldflow-field-session-form-continuity-v1'") !== false
            && strpos($releaseGateRaw, 'checkFieldSessionFormContinuityContract') !== false
            && $fieldSessionAuthRaw !== ''
            && strpos($fieldSessionAuthRaw, "FIELD_SESSION_FORM_CONTINUITY_CONTRACT = 'fieldflow-field-session-form-continuity-v1'") !== false
            && strpos($fieldSessionAuthRaw, 'REFRESH_REPLAY_TTL = 30') !== false
            && strpos($fieldSessionAuthRaw, 'refresh_replay_get') !== false
            && strpos($fieldSessionAuthRaw, 'refresh_replay_store') !== false
            && strpos($fieldSessionAuthRaw, "'aes-256-gcm'") !== false
            && $fieldSessionBridgeRaw !== ''
            && strpos($fieldSessionBridgeRaw, "NATIVE_SHELL_VERSION = '1.10'") !== false
            && strpos($fieldSessionBridgeRaw, 'function queueNativeResume(reason)') !== false
            && strpos($fieldSessionBridgeRaw, "queueNativeResume('visibility-visible')") !== false
            && strpos($fieldSessionBridgeRaw, "queueNativeResume('pageshow')") !== false
            && (($fieldSessionBridgeBareFocus = strpos($fieldSessionBridgeRaw, "queueNativeResume('focus');") !== false) || ($fieldSessionBridgeGuardedFocus = strpos($fieldSessionBridgeRaw, "queueNativeResume('focus-after-hidden')") !== false && strpos($fieldSessionBridgeRaw, 'consumeNativeResumeArm()') !== false))
            && $fieldSessionPolicyRaw !== ''
            && strpos($fieldSessionPolicyRaw, '!self::isPrivilegedUser($userId)') !== false
            && $fieldSessionFormRaw !== ''
            && strpos($fieldSessionFormRaw, "RP_FIELD_SESSION_FORM_CONTINUITY_CONTRACT='fieldflow-field-session-form-continuity-v1'") !== false
            && strpos($fieldSessionFormRaw, 'function rpQueueResumeDraftForms(reason)') !== false
            && (($fieldSessionFormBareFocus = strpos($fieldSessionFormRaw, "rpQueueResumeDraftForms('focus')") !== false) || ($fieldSessionFormGuardedFocus = strpos($fieldSessionFormRaw, "rpQueueResumeDraftForms('focus-after-hidden')") !== false && strpos($fieldSessionFormRaw, 'rpConsumeResumeDraftArm()') !== false))
            && strpos($fieldSessionFormRaw, 'function draftPreferredInitialStep()') !== false
            && strpos($fieldSessionFormRaw, 'showStep(draftPreferredInitialStep(),false);') !== false
            && strpos($fieldSessionFormRaw, "if (\$type === 'product_matrix'") !== false
            && strpos($fieldSessionFormRaw, 'data-paired-fronts-key') !== false
            && strpos($fieldSessionFormRaw, 'pairedKey===frontKey') !== false
            && strpos($fieldSessionFormRaw, "in_array(\$type, ['product_matrix', 'order_suggestion'], true)") === false
            && $fieldSessionDocRaw !== ''
            && strpos($fieldSessionDocRaw, 'fieldflow-field-session-form-continuity-v1') !== false
            && strpos($fieldSessionDocRaw, '`SOURCE_RELEASE` is `2.2.534` and `STABLE_RELEASE` is `2.2.535`') !== false
            && strpos($fieldSessionDocRaw, '`LATEST_SCHEMA_VERSION` remains `2.2.529`.') !== false
            && $fieldSessionVerifierRaw !== ''
            && strpos($fieldSessionVerifierRaw, 'FIELD SESSION FORM CONTINUITY CONTRACT OK') !== false
            && strpos($fieldSessionVerifierRaw, 'successor=>=2.2.535') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['field_session_form_continuity_ok']) {
            $result['error'] = 'Field Session & Form Continuity is not fully wired into the release.';
            return $result;
        }


        // 2.2.536 Certification Gate Continuity 9T: makes historical native-shell
        // checks successor-safe and aligns the 9S Release Manager marker with HTML escaping.
        $cert9tDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9T-CERTIFICATION-GATE-CONTINUITY-HOTFIX.md');
        $cert9tVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-certification-gate-continuity-9t.php');
        $cert9tManagerRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['certification_gate_continuity_9t_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.536', '>=')
            && $result['field_session_form_continuity_ok']
            && $releaseGateRaw !== ''
            && strpos($releaseGateRaw, "CERTIFICATION_GATE_CONTINUITY_9T_CONTRACT = 'fieldflow-certification-gate-continuity-9t-v1'") !== false
            && strpos($releaseGateRaw, "version_compare(\$nativeShellVersion, '1.9', '<')") !== false
            && strpos($releaseGateRaw, "'native_shell_min'=>'1.9'") !== false
            && strpos($releaseGateRaw, "'Field Session &amp; Form Continuity'") !== false
            && $cert9tManagerRaw !== ''
            && strpos($cert9tManagerRaw, 'Certification Gate Continuity 9T') !== false
            && strpos($cert9tManagerRaw, "packageResult['certification_gate_continuity_9t_ok']") !== false
            && $cert9tDocRaw !== ''
            && strpos($cert9tDocRaw, 'fieldflow-certification-gate-continuity-9t-v1') !== false
            && strpos($cert9tDocRaw, '`SOURCE_RELEASE` is `2.2.535` and `STABLE_RELEASE` is `2.2.536`') !== false
            && strpos($cert9tDocRaw, '`LATEST_SCHEMA_VERSION` remains `2.2.529`.') !== false
            && strpos($cert9tVerifierRaw, 'CERTIFICATION GATE CONTINUITY 9T CONTRACT OK') !== false
            && strpos($cert9tVerifierRaw, 'successor=>=2.2.536') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['certification_gate_continuity_9t_ok']) {
            $result['error'] = 'Certification Gate Continuity 9T is not fully wired into the release.';
            return $result;
        }

        // 2.2.537 Order Source & Cardex Consistency 9U: explicit store Cardex
        // mappings tolerate missing route context, order rows are paired to the
        // exact Fronts question, and a manual merchandiser quantity is sovereign.
        $orderCardexRaw = self::entryContents($entries, 'fieldflow/src/Forms/ProductCardex.php');
        $orderFormsRaw = self::entryContents($entries, 'fieldflow/src/Forms/Forms.php');
        $orderRendererRaw = self::entryContents($entries, 'fieldflow/src/Forms/FormRenderer.php');
        $orderMobilePayloadRaw = self::entryContents($entries, 'fieldflow/src/Mobile/FormPayloadService.php');
        $orderMobileSubmitRaw = self::entryContents($entries, 'fieldflow/src/Mobile/SubmissionService.php');
        $orderMailRaw = self::entryContents($entries, 'fieldflow/src/Admin/FormSubmissions.php');
        $order9uDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9U-ORDER-SOURCE-CARDEX-CONSISTENCY.md');
        $order9uVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-order-source-cardex-consistency-9u.php');
        $order9uRuntimeTestRaw = self::entryContents($entries, 'fieldflow/tools/test-order-source-runtime-9u.php');
        $result['order_source_cardex_consistency_9u_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.537', '>=')
            && $result['certification_gate_continuity_9t_ok']
            && $orderCardexRaw !== ''
            && strpos($orderCardexRaw, "ORDER_SOURCE_CARDEX_CONSISTENCY_CONTRACT = 'fieldflow-order-source-cardex-consistency-v1'") !== false
            && strpos($orderCardexRaw, 'AND (c.client_id=0 OR %d=0 OR c.client_id=%d)') !== false
            && strpos($orderCardexRaw, 'AND (c.project_id=0 OR %d=0 OR c.project_id=%d)') !== false
            && strpos($orderCardexRaw, "['pd', 'pingo doce', 'p doce', 'pingodoce']") !== false
            && strpos($orderCardexRaw, "['cbd', 'continente bom dia']") !== false
            && $orderFormsRaw !== ''
            && strpos($orderFormsRaw, "if (\$type === 'product_matrix'") !== false
            && strpos($orderFormsRaw, 'posted_front_selection($cardex_id, $paired_fronts_key)') !== false
            && strpos($orderFormsRaw, "\$quantity_source !== 'manual'") !== false
            && strpos($orderFormsRaw, "'quantity_source' => \$quantity_source") !== false
            && $orderRendererRaw !== ''
            && strpos($orderRendererRaw, 'data-order-quantity-source') !== false
            && strpos($orderRendererRaw, ".val('auto')") !== false
            && strpos($orderRendererRaw, ".val('manual')") !== false
            && $orderMobilePayloadRaw !== ''
            && strpos($orderMobilePayloadRaw, "\$available = \$type === 'order_suggestion' ? true : \$cardexAvailable") !== false
            && strpos($orderMobilePayloadRaw, "['paired_fronts_key']") !== false
            && $orderMobileSubmitRaw !== ''
            && strpos($orderMobileSubmitRaw, 'sanitize_order_suggestion_value') !== false
            && strpos($orderMailRaw, 'getOrderSuggestionSubmission') !== false
            && strpos($orderMailRaw, "'order_qty' => \$order['order_qty'] ?? ''") !== false
            && $order9uDocRaw !== ''
            && strpos($order9uDocRaw, 'fieldflow-order-source-cardex-consistency-v1') !== false
            && strpos($order9uDocRaw, '`SOURCE_RELEASE` is `2.2.536` and `STABLE_RELEASE` is `2.2.537`') !== false
            && strpos($order9uVerifierRaw, 'ORDER SOURCE CARDEX CONSISTENCY 9U CONTRACT OK') !== false
            && strpos($order9uVerifierRaw, 'successor=>=2.2.537') !== false
            && strpos($order9uRuntimeTestRaw, 'ORDER SOURCE RUNTIME BEHAVIOUR OK') !== false
            && strpos($order9uRuntimeTestRaw, 'manual_quantity=4') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['order_source_cardex_consistency_9u_ok']) {
            $result['error'] = 'Order Source & Cardex Consistency 9U is not fully wired into the release.';
            return $result;
        }

        // 2.2.538 Canonical Answer Semantics 9V: Web, Mobile and the
        // TypeScript preflight share one answer-normalization contract.
        $answerSemanticsRaw = self::entryContents($entries, 'fieldflow/src/Forms/AnswerSemantics.php');
        $answerFormsRaw = self::entryContents($entries, 'fieldflow/src/Forms/Forms.php');
        $answerMobileRaw = self::entryContents($entries, 'fieldflow/src/Mobile/SubmissionService.php');
        $answerTsRaw = self::entryContents($entries, 'fieldflow/frontend/src/forms/answer-semantics-core.ts');
        $answerSubmissionTsRaw = self::entryContents($entries, 'fieldflow/frontend/src/forms/submission-core.ts');
        $answerRendererRaw = self::entryContents($entries, 'fieldflow/src/Forms/FormRenderer.php');
        $answer9vDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9V-CANONICAL-ANSWER-SEMANTICS.md');
        $answer9vVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-canonical-answer-semantics-9v.php');
        $answer9vRuntimeTestRaw = self::entryContents($entries, 'fieldflow/tools/test-canonical-answer-semantics-9v.php');
        $result['canonical_answer_semantics_9v_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.538', '>=')
            && $result['order_source_cardex_consistency_9u_ok']
            && $answerSemanticsRaw !== ''
            && strpos($answerSemanticsRaw, "CONTRACT = 'fieldflow-canonical-answer-semantics-v1'") !== false
            && strpos($answerSemanticsRaw, 'number_below_min') !== false
            && strpos($answerSemanticsRaw, 'number_above_max') !== false
            && strpos($answerSemanticsRaw, 'choice_not_allowed') !== false
            && strpos($answerSemanticsRaw, 'structured_value_invalid') !== false
            && $answerFormsRaw !== ''
            && strpos($answerFormsRaw, 'AnswerSemantics::normalize($question, $value, true)') !== false
            && strpos($answerFormsRaw, "'invalid_answer_semantics'") !== false
            && $answerMobileRaw !== ''
            && strpos($answerMobileRaw, 'use RoutesPro\\Forms\\AnswerSemantics;') !== false
            && strpos($answerMobileRaw, 'AnswerSemantics::normalize($question, $value, true)') !== false
            && strpos($answerMobileRaw, "'fieldflow_mobile_invalid_answer'") !== false
            && $answerTsRaw !== ''
            && strpos($answerTsRaw, "FIELD_FLOW_ANSWER_SEMANTICS_CONTRACT = 'fieldflow-canonical-answer-semantics-v1'") !== false
            && $answerSubmissionTsRaw !== ''
            && strpos($answerSubmissionTsRaw, 'fieldFlowAnswerSemanticNormalize') !== false
            && strpos($answerSubmissionTsRaw, 'invalidSemanticKeys') !== false
            && $answerRendererRaw !== ''
            && strpos($answerRendererRaw, 'prepared.invalidSemanticKeys') !== false
            && $frontendRaw !== ''
            && strpos($frontendRaw, 'fieldflow-canonical-answer-semantics-v1') !== false
            && $answer9vDocRaw !== ''
            && strpos($answer9vDocRaw, '`SOURCE_RELEASE` is `2.2.537` and `STABLE_RELEASE` is `2.2.538`') !== false
            && $answer9vVerifierRaw !== ''
            && strpos($answer9vVerifierRaw, 'CANONICAL ANSWER SEMANTICS 9V CONTRACT OK') !== false
            && strpos($answer9vVerifierRaw, 'successor=>=2.2.538') !== false
            && $answer9vRuntimeTestRaw !== ''
            && strpos($answer9vRuntimeTestRaw, 'CANONICAL ANSWER SEMANTICS RUNTIME OK') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['canonical_answer_semantics_9v_ok']) {
            $result['error'] = 'Canonical Answer Semantics 9V is not fully wired into the release.';
            return $result;
        }

        // 2.2.539 Visit Closure & Daily Completion Mail 9W: only final
        // submissions close visits; justified non-visits are terminal; every
        // route-completion path queues the same reliable Daily Close contract.
        $visitPolicy9wRaw = self::entryContents($entries, 'fieldflow/src/Services/VisitCompletionPolicy.php');
        $submissionStatus9wRaw = self::entryContents($entries, 'fieldflow/src/Services/SubmissionStatusPolicy.php');
        $routeProgress9wRaw = self::entryContents($entries, 'fieldflow/src/Services/RouteProgressService.php');
        $formCompletion9wRaw = self::entryContents($entries, 'fieldflow/src/Services/FormVisitCompletionService.php');
        $automations9wRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReportAutomations.php');
        $notify9wRaw = self::entryContents($entries, 'fieldflow/src/Services/Notify.php');
        $events9wRaw = self::entryContents($entries, 'fieldflow/src/Rest/EventsController.php');
        $routes9wRaw = self::entryContents($entries, 'fieldflow/src/Rest/RoutesController.php');
        $manual9wRaw = self::entryContents($entries, 'fieldflow/src/Services/VisitManualCompletionService.php');
        $loader9wRaw = self::entryContents($entries, 'fieldflow/src/Support/Loader.php');
        $visit9wDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9W-VISIT-CLOSURE-DAILY-COMPLETION-MAIL.md');
        $visit9wVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-visit-closure-daily-mail-9w.php');
        $visit9wRuntimeRaw = self::entryContents($entries, 'fieldflow/tools/test-visit-closure-daily-mail-9w.php');
        $visit9wManagerRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['visit_closure_daily_mail_9w_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.539', '>=')
            && $result['canonical_answer_semantics_9v_ok']
            && $visitPolicy9wRaw !== ''
            && strpos($visitPolicy9wRaw, "CONTRACT = 'fieldflow-visit-closure-daily-mail-v1'") !== false
            && strpos($visitPolicy9wRaw, 'SubmissionStatusPolicy::isOperational') !== false
            && strpos($submissionStatus9wRaw, "['submitted','reviewed','approved']") !== false
            && strpos($visitPolicy9wRaw, 'stopIsJustifiedNonVisit') !== false
            && $routeProgress9wRaw !== ''
            && strpos($routeProgress9wRaw, "SubmissionStatusPolicy::operationalSql('fs')") !== false
            && strpos($routeProgress9wRaw, 'VisitCompletionPolicy::remainingSql()') !== false
            && strpos($routeProgress9wRaw, "'operationally_complete'") !== false
            && $formCompletion9wRaw !== ''
            && strpos($formCompletion9wRaw, 'VisitCompletionPolicy::submissionClosesVisit') !== false
            && strpos($formCompletion9wRaw, 'ReportAutomations::queueDailyCloseForRoute') !== false
            && $automations9wRaw !== ''
            && strpos($automations9wRaw, "DAILY_CLOSE_RETRY_HOOK = 'fieldflow_daily_close_dispatch_retry'") !== false
            && strpos($automations9wRaw, "'recipient_mode'=>'client_and_extra'") !== false
            && strpos($automations9wRaw, "['claim'=>'extra','mode'=>'extra']") !== false
            && strpos($automations9wRaw, 'scheduleDailyCloseRetry') !== false
            && strpos($automations9wRaw, 'legacy_route_notification') !== false
            && strpos($notify9wRaw, "'route_notification|completed|' . \$route_id") !== false
            && substr_count($events9wRaw, 'ReportAutomations::queueDailyCloseForRoute') >= 2
            && strpos($routes9wRaw, 'ReportAutomations::queueDailyCloseForRoute') !== false
            && strpos($manual9wRaw, 'ReportAutomations::queueDailyCloseForRoute') !== false
            && strpos($loader9wRaw, 'src/Services/VisitCompletionPolicy.php') !== false
            && $visit9wDocRaw !== ''
            && strpos($visit9wDocRaw, '`SOURCE_RELEASE` is `2.2.538` and `STABLE_RELEASE` is `2.2.539`') !== false
            && $visit9wVerifierRaw !== ''
            && strpos($visit9wVerifierRaw, 'VISIT CLOSURE DAILY COMPLETION MAIL 9W CONTRACT OK') !== false
            && strpos($visit9wVerifierRaw, 'successor=>=2.2.539') !== false
            && $visit9wRuntimeRaw !== ''
            && strpos($visit9wRuntimeRaw, 'VISIT CLOSURE DAILY COMPLETION MAIL 9W RUNTIME OK') !== false
            && $visit9wManagerRaw !== ''
            && strpos($visit9wManagerRaw, 'Visit Closure &amp; Daily Completion Mail 9W') !== false
            && strpos($visit9wManagerRaw, "packageResult['visit_closure_daily_mail_9w_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['visit_closure_daily_mail_9w_ok']) {
            $result['error'] = 'Visit Closure & Daily Completion Mail 9W is not fully wired into the release.';
            return $result;
        }

        // 2.2.540 certification continuity: mirror the installed
        // Operational Intelligence & Field Reliability gate at package time.
        $temporalOpsRaw = self::entryContents($entries, 'fieldflow/src/Analytics/TemporalAnalyticsService.php');
        $statsOpsRaw = self::entryContents($entries, 'fieldflow/src/Rest/StatsController.php');
        $aiOpsRaw = self::entryContents($entries, 'fieldflow/src/Analytics/AnalyticsAIService.php');
        $askOpsRaw = self::entryContents($entries, 'fieldflow/src/Analytics/FieldFlowAskService.php');
        $cardexOpsRaw = self::entryContents($entries, 'fieldflow/src/Forms/ProductCardex.php');
        $responsiveOpsRaw = self::entryContents($entries, 'fieldflow/src/Front/ResponsiveAssets.php');
        $shortcodesOpsRaw = self::entryContents($entries, 'fieldflow/src/Shortcodes.php');
        $portalOpsRaw = self::entryContents($entries, 'fieldflow/src/Front/ClientPortalShortcodeRenderer.php');
        $adminAskOpsRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-analytics2-admin.js');
        $opsVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-operational-intelligence-hardening.php');
        $mailVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-mail-reliability-outbox.php');
        $expectedOfflineRuntime = 'fieldflow-offline-runtime-' . (string)($result['version'] ?? '') . '.js';
        $result['operational_intelligence_hardening_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.530', '>=')
            && $temporalOpsRaw !== ''
            && strpos($temporalOpsRaw, 'closed_days_only') !== false
            && strpos($temporalOpsRaw, 'today_is_in_progress') !== false
            && strpos($temporalOpsRaw, 'future_never_reduces_operational_scores') !== false
            && strpos($temporalOpsRaw, 'justified_non_visits_are_terminal_not_executed') !== false
            && $statsOpsRaw !== ''
            && strpos($statsOpsRaw, 'stops_justified_failed') !== false
            && strpos($statsOpsRaw, 'stops_terminal') !== false
            && strpos($statsOpsRaw, 'TemporalAnalyticsService::enrich') !== false
            && strpos($statsOpsRaw, 'TemporalAnalyticsService::forEvaluation') !== false
            && strpos($statsOpsRaw, 'FieldFlowAskService::isHelpQuestion') !== false
            && $aiOpsRaw !== ''
            && strpos($aiOpsRaw, 'TemporalAnalyticsService::forEvaluation') !== false
            && strpos($aiOpsRaw, 'temporal_context') !== false
            && strpos($aiOpsRaw, 'future_planned') !== false
            && $askOpsRaw !== ''
            && strpos($askOpsRaw, 'isHelpQuestion') !== false
            && strpos($askOpsRaw, 'helpAnswer') !== false
            && strpos($askOpsRaw, 'Rotas futuras planeadas') !== false
            && strpos($cardexOpsRaw, '_fieldflow_runtime_cardex_bridge') !== false
            && strpos($cardexOpsRaw, 'Explicit manual product lists are never changed') !== false
            && $automations9wRaw !== ''
            && strpos($automations9wRaw, 'queueDailyCloseForRoute') !== false
            && strpos($automations9wRaw, 'scheduleDailyCloseRetry') !== false
            && strpos($automations9wRaw, 'dispatchDailyCloseForRoute') !== false
            && strpos($automations9wRaw, 'claimDispatch') !== false
            && $events9wRaw !== ''
            && strpos($events9wRaw, "\$wpdb->query('COMMIT');") !== false
            && strpos($events9wRaw, "route_status'] ?? '')) === 'completed'") !== false
            && strpos($events9wRaw, 'operationally_complete') !== false
            && strpos($events9wRaw, 'ReportAutomations::queueDailyCloseForRoute') !== false
            && $responsiveOpsRaw !== ''
            && strpos($responsiveOpsRaw, $expectedOfflineRuntime) !== false
            && strpos($shortcodesOpsRaw, "function askUser(question){if(!els.askThread)return;if(els.askThread.querySelector('.ffad-ask-empty'))els.askThread.innerHTML='';}") !== false
            && strpos($portalOpsRaw, "function portalAskUser(question){if(!els.askThread)return;if(els.askThread.querySelector('.rp-ask-empty'))els.askThread.innerHTML='';}") !== false
            && strpos($adminAskOpsRaw, "function renderAskUser(question){\n        if(!askThread)return;\n        if(askThread.querySelector('.ff-a2-ask-empty'))askThread.innerHTML='';\n    }") !== false
            && $opsVerifierRaw !== ''
            && strpos($opsVerifierRaw, 'OPERATIONAL INTELLIGENCE HARDENING CONTRACT OK') !== false
            && strpos($opsVerifierRaw, 'successor=>=2.2.530') !== false
            && $mailVerifierRaw !== ''
            && strpos($mailVerifierRaw, 'MAIL RELIABILITY OUTBOX CONTRACT OK') !== false
            && strpos($mailVerifierRaw, 'successor=>=2.2.530') !== false;
        if (!$result['operational_intelligence_hardening_ok']) {
            $result['error'] = 'Operational Intelligence & Field Reliability is not package-safe for this successor.';
            return $result;
        }

        $continuity9xDocRaw = self::entryContents($entries, 'fieldflow/PHASE-9X-CERTIFICATION-CONTINUITY-VISIT-CLOSURE.md');
        $continuity9xVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-certification-continuity-visit-closure-9x.php');
        $continuity9xGateRaw = self::entryContents($entries, 'fieldflow/src/Support/ReleaseGate.php');
        $result['certification_continuity_visit_closure_9x_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.540', '>=')
            && $result['visit_closure_daily_mail_9w_ok']
            && $result['operational_intelligence_hardening_ok']
            && $result['mail_reliability_outbox_ok']
            && $continuity9xDocRaw !== ''
            && strpos($continuity9xDocRaw, 'fieldflow-certification-continuity-visit-closure-v1') !== false
            && strpos($continuity9xDocRaw, '`SOURCE_RELEASE` is `2.2.539` and `STABLE_RELEASE` is `2.2.540`') !== false
            && $continuity9xVerifierRaw !== ''
            && strpos($continuity9xVerifierRaw, 'CERTIFICATION CONTINUITY VISIT CLOSURE 9X CONTRACT OK') !== false
            && strpos($continuity9xVerifierRaw, 'successor=>=2.2.540') !== false
            && $continuity9xGateRaw !== ''
            && strpos($continuity9xGateRaw, 'fieldflow-certification-continuity-visit-closure-v1') !== false
            && $visit9wManagerRaw !== ''
            && strpos($visit9wManagerRaw, 'Certification Continuity Visit Closure 9X') !== false
            && strpos($visit9wManagerRaw, "packageResult['certification_continuity_visit_closure_9x_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['certification_continuity_visit_closure_9x_ok']) {
            $result['error'] = 'Certification Continuity Visit Closure 9X is not fully wired into the release.';
            return $result;
        }


        $status9yRaw = self::entryContents($entries, 'fieldflow/src/Services/SubmissionStatusPolicy.php');
        $day9yRaw = self::entryContents($entries, 'fieldflow/src/Services/OperationalReportDayPolicy.php');
        $visitPolicy9yRaw = self::entryContents($entries, 'fieldflow/src/Services/VisitCompletionPolicy.php');
        $routeProgress9yRaw = self::entryContents($entries, 'fieldflow/src/Services/RouteProgressService.php');
        $contextQuestions9yRaw = self::entryContents($entries, 'fieldflow/src/Forms/ContextQuestions.php');
        $canonicalState9yRaw = self::entryContents($entries, 'fieldflow/src/Core/CanonicalStateService.php');
        $manualCompletion9yRaw = self::entryContents($entries, 'fieldflow/src/Services/VisitManualCompletionService.php');
        $reportAutomations9yRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReportAutomations.php');
        $formSubmissions9yRaw = self::entryContents($entries, 'fieldflow/src/Admin/FormSubmissions.php');
        $analyticsAi9yRaw = self::entryContents($entries, 'fieldflow/src/Analytics/AnalyticsAIService.php');
        $stats9yRaw = self::entryContents($entries, 'fieldflow/src/Rest/StatsController.php');
        $publicQuery9yRaw = self::entryContents($entries, 'fieldflow/src/Integrations/PublicQueryService.php');
        $storeProfile9yRaw = self::entryContents($entries, 'fieldflow/src/Stores/StoreProfileService.php');
        $loader9yRaw = self::entryContents($entries, 'fieldflow/src/Support/Loader.php');
        $doc9yRaw = self::entryContents($entries, 'fieldflow/PHASE-9Y-SUBMISSION-STATUS-OPERATIONAL-DAY.md');
        $verifier9yRaw = self::entryContents($entries, 'fieldflow/tools/verify-submission-status-operational-day-9y.php');
        $manager9yRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['submission_status_operational_day_9y_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.541', '>=')
            && $status9yRaw !== '' && strpos($status9yRaw, 'fieldflow-submission-status-policy-v1') !== false && strpos($status9yRaw, "['submitted','reviewed','approved']") !== false
            && $day9yRaw !== '' && strpos($day9yRaw, 'fieldflow-operational-report-day-policy-v1') !== false && strpos($day9yRaw, 'sqlExpression') !== false && strpos($day9yRaw, 'versionSqlExpression') !== false
            && strpos($visitPolicy9yRaw, 'SubmissionStatusPolicy::isOperational') !== false
            && substr_count($routeProgress9yRaw, "SubmissionStatusPolicy::operationalSql('fs')") >= 2
            && strpos($contextQuestions9yRaw, "SubmissionStatusPolicy::operationalSql('s')") !== false
            && strpos($canonicalState9yRaw, 'SubmissionStatusPolicy::operationalSql()') !== false
            && strpos($manualCompletion9yRaw, 'SubmissionStatusPolicy::operationalSql()') !== false
            && strpos($reportAutomations9yRaw, 'SubmissionStatusPolicy::operationalSql()') !== false
            && strpos($formSubmissions9yRaw, "OperationalReportDayPolicy::sqlExpression('s', 'r')") !== false
            && strpos($analyticsAi9yRaw, "OperationalReportDayPolicy::versionSqlExpression('s', 'v')") !== false
            && strpos($stats9yRaw, "SubmissionStatusPolicy::operationalSql('s')") !== false && strpos($stats9yRaw, 'OperationalReportDayPolicy::versionSqlExpression') !== false
            && strpos($publicQuery9yRaw, "OperationalReportDayPolicy::sqlExpression('s')") !== false
            && strpos($storeProfile9yRaw, "SubmissionStatusPolicy::operationalSql('fs2')") !== false && strpos($storeProfile9yRaw, "OperationalReportDayPolicy::sqlExpression('fs2')") !== false
            && strpos($loader9yRaw, 'src/Services/SubmissionStatusPolicy.php') !== false && strpos($loader9yRaw, 'src/Services/OperationalReportDayPolicy.php') !== false
            && strpos($doc9yRaw, 'fieldflow-submission-status-operational-day-v1') !== false && strpos($doc9yRaw, '`SOURCE_RELEASE` is `2.2.540` and `STABLE_RELEASE` is `2.2.541`') !== false
            && strpos($verifier9yRaw, 'SUBMISSION STATUS OPERATIONAL DAY 9Y CONTRACT OK') !== false && strpos($verifier9yRaw, 'successor=>=2.2.541') !== false
            && strpos($manager9yRaw, 'Submission Status &amp; Operational Day 9Y') !== false && strpos($manager9yRaw, "packageResult['submission_status_operational_day_9y_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['submission_status_operational_day_9y_ok']) {
            $result['error'] = 'Submission Status & Operational Day 9Y is not fully wired into the release.';
            return $result;
        }

        $correction9zRaw = self::entryContents($entries, 'fieldflow/src/Services/SubmissionCorrectionService.php');
        $admin9zRaw = self::entryContents($entries, 'fieldflow/src/Admin/FormSubmissions.php');
        $records9zRaw = self::entryContents($entries, 'fieldflow/src/Forms/RecordService.php');
        $loader9zRaw = self::entryContents($entries, 'fieldflow/src/Support/Loader.php');
        $doc9zRaw = self::entryContents($entries, 'fieldflow/PHASE-9Z-SUBMISSION-CORRECTIONS-AUDIT-TRAIL.md');
        $verifier9zRaw = self::entryContents($entries, 'fieldflow/tools/verify-submission-corrections-audit-9z.php');
        $runtime9zRaw = self::entryContents($entries, 'fieldflow/tools/test-submission-corrections-audit-9z.php');
        $manager9zRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['submission_corrections_audit_9z_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.542', '>=')
            && $correction9zRaw !== '' && strpos($correction9zRaw, 'fieldflow-submission-corrections-audit-v1') !== false
            && strpos($correction9zRaw, 'admin_correction_history') !== false && strpos($correction9zRaw, 'last_admin_correction') !== false
            && strpos($correction9zRaw, 'reconcileVisitInsideTransaction') !== false
            && strpos($correction9zRaw, 'RouteProgressService::sync($routeId, true, false)') !== false
            && strpos($correction9zRaw, "'mail_side_effects_emitted'=>false") !== false
            && strpos($correction9zRaw, 'queueDailyCloseForRoute') === false && strpos($correction9zRaw, "EventBus::emit('route.completed'") === false
            && $admin9zRaw !== '' && strpos($admin9zRaw, 'SubmissionCorrectionService::correct') !== false && strpos($admin9zRaw, 'SubmissionCorrectionService::void') !== false
            && strpos($admin9zRaw, 'correction_reason') !== false && strpos($admin9zRaw, 'render_correction_history') !== false
            && strpos($admin9zRaw, 'delete_submission_history($id)') === false && strpos($admin9zRaw, '$wpdb->delete(FormsModule::table_submissions()') === false
            && $records9zRaw !== '' && strpos($records9zRaw, 'reconcile_operational_records') !== false && strpos($records9zRaw, 'reconcile_operational_record_state') !== false
            && strpos($records9zRaw, "'status'=>'inactive'") !== false && strpos($records9zRaw, 'current_version_id') !== false
            && strpos($loader9zRaw, 'src/Services/SubmissionCorrectionService.php') !== false
            && strpos($doc9zRaw, 'fieldflow-submission-corrections-audit-v1') !== false && strpos($doc9zRaw, '`SOURCE_RELEASE` is `2.2.541` and `STABLE_RELEASE` is `2.2.542`') !== false
            && strpos($verifier9zRaw, 'SUBMISSION CORRECTIONS AUDIT 9Z CONTRACT OK') !== false && strpos($verifier9zRaw, 'successor=>=2.2.542') !== false
            && strpos($runtime9zRaw, 'SUBMISSION CORRECTIONS AUDIT 9Z RUNTIME OK') !== false
            && strpos($manager9zRaw, 'Submission Corrections &amp; Audit Trail 9Z') !== false && strpos($manager9zRaw, "packageResult['submission_corrections_audit_9z_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['submission_corrections_audit_9z_ok']) {
            $result['error'] = 'Submission Corrections & Audit Trail 9Z is not fully wired into the release.';
            return $result;
        }

        $resumeApp9aaRaw = self::entryContents($entries, 'fieldflow/src/Front/AppShortcodeRenderer.php');
        $resumeForm9aaRaw = self::entryContents($entries, 'fieldflow/src/Forms/FormRenderer.php');
        $resumeBridge9aaRaw = self::entryContents($entries, 'fieldflow/src/Mobile/WebViewBridge.php');
        $resumeDoc9aaRaw = self::entryContents($entries, 'fieldflow/PHASE-9AA-FIELD-RESUME-NAVIGATION-CONTINUITY.md');
        $resumeVerifier9aaRaw = self::entryContents($entries, 'fieldflow/tools/verify-field-resume-navigation-9aa.php');
        $resumeRuntime9aaRaw = self::entryContents($entries, 'fieldflow/tools/test-field-resume-navigation-9aa.php');
        $resumeManager9aaRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['field_resume_navigation_9aa_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.543', '>=')
            && $resumeApp9aaRaw !== '' && strpos($resumeApp9aaRaw, 'fieldflow-field-resume-navigation-continuity-v1') !== false
            && strpos($resumeApp9aaRaw, 'fieldflow:resume:operational:v1:') !== false
            && strpos($resumeApp9aaRaw, 'readResumeCheckpoint') !== false && strpos($resumeApp9aaRaw, 'writeResumeCheckpoint') !== false
            && strpos($resumeApp9aaRaw, 'panelFromResume') !== false && strpos($resumeApp9aaRaw, 'restoreResumeContext') !== false
            && strpos($resumeApp9aaRaw, 'fieldflow:native-resume-sync') !== false && strpos($resumeApp9aaRaw, 'fieldflow:form-external-handoff') !== false
            && strpos($resumeApp9aaRaw, "sessionStorage.setItem('fieldflow_active_panel',key)") !== false
            && strpos($resumeApp9aaRaw, 'localStorage.setItem(resumeStorageKey') !== false
            && $resumeForm9aaRaw !== '' && strpos($resumeForm9aaRaw, 'fieldflow-field-resume-navigation-continuity-v1') !== false
            && strpos($resumeForm9aaRaw, 'rpCheckpointExternalMediaHandoff') !== false
            && strpos($resumeForm9aaRaw, 'routesproFlushDraft({force:true,silent:true,immediateServer:true,keepalive:true,lifecycle:true})') !== false
            && strpos($resumeForm9aaRaw, 'fieldflow:form-external-handoff') !== false
            && strpos($resumeForm9aaRaw, 'showStep(parseInt(draft.step||1,10)||1,false)') !== false
            && $resumeBridge9aaRaw !== '' && strpos($resumeBridge9aaRaw, "FIELD_RESUME_NAVIGATION_CONTRACT = 'fieldflow-field-resume-navigation-continuity-v1'") !== false
            && strpos($resumeBridge9aaRaw, 'resumeNavigationContract') !== false
            && strpos($resumeBridge9aaRaw, 'fieldflow:resume:operational:v1:') !== false
            && strpos($resumeBridge9aaRaw, 'sessionStorage.removeItem("fieldflow_active_panel")') !== false
            && strpos($resumeDoc9aaRaw, 'fieldflow-field-resume-navigation-continuity-v1') !== false
            && strpos($resumeDoc9aaRaw, '`SOURCE_RELEASE` is `2.2.542` and `STABLE_RELEASE` is `2.2.543`') !== false
            && strpos($resumeVerifier9aaRaw, 'FIELD RESUME NAVIGATION 9AA CONTRACT OK') !== false && strpos($resumeVerifier9aaRaw, 'successor=>=2.2.543') !== false
            && strpos($resumeRuntime9aaRaw, 'FIELD RESUME NAVIGATION 9AA RUNTIME OK') !== false
            && strpos($resumeManager9aaRaw, 'Field Resume Navigation Continuity 9AA') !== false && strpos($resumeManager9aaRaw, "packageResult['field_resume_navigation_9aa_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['field_resume_navigation_9aa_ok']) {
            $result['error'] = 'Field Resume Navigation Continuity 9AA is not fully wired into the release.';
            return $result;
        }

        $reportApp9abRaw = self::entryContents($entries, 'fieldflow/src/Front/AppShortcodeRenderer.php');
        $reportShortcodes9abRaw = self::entryContents($entries, 'fieldflow/src/Shortcodes.php');
        $reportForm9abRaw = self::entryContents($entries, 'fieldflow/src/Forms/FormRenderer.php');
        $reportBridge9abRaw = self::entryContents($entries, 'fieldflow/src/Mobile/WebViewBridge.php');
        $reportDoc9abRaw = self::entryContents($entries, 'fieldflow/PHASE-9AB-REPORT-SELECTION-MEDIA-CONTINUITY.md');
        $reportVerifier9abRaw = self::entryContents($entries, 'fieldflow/tools/verify-report-selection-media-continuity-9ab.php');
        $reportRuntime9abRaw = self::entryContents($entries, 'fieldflow/tools/test-report-selection-media-continuity-9ab.php');
        $reportManager9abRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['report_selection_media_9ab_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.544', '>=')
            && $reportApp9abRaw !== '' && strpos($reportApp9abRaw, 'fieldflow-report-selection-media-continuity-v1') !== false
            && strpos($reportApp9abRaw, 'manualReportSelectionLockUntil') !== false && strpos($reportApp9abRaw, 'cancelPendingReportRestore') !== false
            && strpos($reportApp9abRaw, 'fieldflow:report-selection-intent') !== false && strpos($reportApp9abRaw, 'fieldflow:report-selection-changed') !== false
            && strpos($reportApp9abRaw, 'liveReportVisit') !== false && strpos($reportApp9abRaw, 'live-report-authority') !== false
            && strpos($reportApp9abRaw, 'fieldflow:form-media-checkpointed') !== false
            && $reportShortcodes9abRaw !== '' && strpos($reportShortcodes9abRaw, 'manual-store-dropdown') !== false
            && strpos($reportShortcodes9abRaw, 'fieldflow:report-selection-intent') !== false && strpos($reportShortcodes9abRaw, 'fieldflow:report-selection-changed') !== false
            && $reportForm9abRaw !== '' && strpos($reportForm9abRaw, 'fieldflow-report-selection-media-continuity-v1') !== false
            && strpos($reportForm9abRaw, 'fieldflow:form-media-checkpointed') !== false && strpos($reportForm9abRaw, "form.dataset.rpMediaSettling='1'") !== false
            && strpos($reportForm9abRaw, 'Persist the optimised transport copy exactly once.') !== false
            && strpos($reportForm9abRaw, 'rpResumeDraftArmedAt') !== false && strpos($reportForm9abRaw, 'rpConsumeResumeDraftArm') !== false
            && strpos($reportForm9abRaw, "rpQueueResumeDraftForms('focus-after-hidden')") !== false && strpos($reportForm9abRaw, "rpQueueResumeDraftForms('focus')") === false
            && $reportBridge9abRaw !== '' && strpos($reportBridge9abRaw, 'fieldflowResumeArmedAt') !== false && strpos($reportBridge9abRaw, 'consumeNativeResumeArm') !== false
            && strpos($reportBridge9abRaw, "queueNativeResume('focus-after-hidden')") !== false && strpos($reportBridge9abRaw, "queueNativeResume('focus');") === false
            && strpos($reportDoc9abRaw, 'fieldflow-report-selection-media-continuity-v1') !== false
            && strpos($reportDoc9abRaw, '`SOURCE_RELEASE` is `2.2.543` and `STABLE_RELEASE` is `2.2.544`') !== false
            && strpos($reportVerifier9abRaw, 'REPORT SELECTION MEDIA CONTINUITY 9AB CONTRACT OK') !== false && strpos($reportVerifier9abRaw, 'successor=>=2.2.544') !== false
            && strpos($reportRuntime9abRaw, 'REPORT SELECTION MEDIA CONTINUITY 9AB RUNTIME OK') !== false
            && strpos($reportManager9abRaw, 'Report Selection &amp; Media Continuity 9AB') !== false && strpos($reportManager9abRaw, "packageResult['report_selection_media_9ab_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['report_selection_media_9ab_ok']) {
            $result['error'] = 'Report Selection & Media Continuity 9AB is not fully wired into the release.';
            return $result;
        }

        // 2.2.545 Resume Certification & Media Integrity 9AC: aligns the
        // historical 9S capability check with guarded resume and closes the
        // camera-original, operational-day and explicit-selection edge cases.
        $resumeCertDoc9acRaw = self::entryContents($entries, 'fieldflow/PHASE-9AC-RESUME-CERTIFICATION-MEDIA-INTEGRITY.md');
        $resumeCertVerifier9acRaw = self::entryContents($entries, 'fieldflow/tools/verify-resume-certification-media-9ac.php');
        $resumeCertRuntime9acRaw = self::entryContents($entries, 'fieldflow/tools/test-resume-certification-media-9ac.php');
        $resumeCertManager9acRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $offlineCanonical9acRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-offline-runtime.js');
        $offlineVersioned9acRaw = self::entryContents($entries, 'fieldflow/assets/fieldflow-offline-runtime-' . (string)($result['version'] ?? '') . '.js');
        $result['resume_certification_media_9ac_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.545', '>=')
            && $result['field_session_form_continuity_ok'] && $result['report_selection_media_9ab_ok']
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "RESUME_CERTIFICATION_MEDIA_9AC_CONTRACT = 'fieldflow-resume-certification-media-integrity-v1'") !== false
            && strpos($releaseGateRaw, 'bridge:focus_resume_capability_missing') !== false && strpos($releaseGateRaw, 'form:focus_resume_capability_missing') !== false
            && strpos($releaseGateRaw, 'date:String(\$form.find') !== false
            && isset($fieldSessionBridgeGuardedFocus) && $fieldSessionBridgeGuardedFocus === true && (!isset($fieldSessionBridgeBareFocus) || $fieldSessionBridgeBareFocus === false)
            && isset($fieldSessionFormGuardedFocus) && $fieldSessionFormGuardedFocus === true && (!isset($fieldSessionFormBareFocus) || $fieldSessionFormBareFocus === false)
            && strpos($reportForm9abRaw, "RP_RESUME_CERT_MEDIA_CONTRACT='fieldflow-resume-certification-media-integrity-v1'") !== false
            && strpos($reportForm9abRaw, "RP_MEDIA_ORIGINAL_PREFIX='__fieldflow_original__:'") !== false
            && strpos($reportForm9abRaw, 'rpDraftStoreOriginalInputFiles') !== false && strpos($reportForm9abRaw, 'rpDraftRecordsForInput') !== false
            && strpos($reportForm9abRaw, "date:String(\$form.find('[name=\"routespro_report_day\"],[name=\"routespro_context_date\"]')") !== false
            && strpos($reportForm9abRaw, 'Persist the optimised transport copy exactly once.') !== false && strpos($reportForm9abRaw, 'optimiseFileInput(input,false)') !== false
            && strpos($reportApp9abRaw, 'manual-report-selection-cleared') !== false && strpos($reportApp9abRaw, "localStorage.removeItem('fieldflow:last-report-visit')") !== false
            && strpos($reportShortcodes9abRaw, 'clearStoredReportContext') !== false && strpos($reportShortcodes9abRaw, 'else clearStoredReportContext()') !== false
            && $offlineCanonical9acRaw !== '' && $offlineVersioned9acRaw !== '' && hash_equals(hash('sha256', $offlineCanonical9acRaw), hash('sha256', $offlineVersioned9acRaw))
            && strpos($offlineCanonical9acRaw, "DURABLE_ORIGINAL_MEDIA_PREFIX='__fieldflow_original__:'") !== false && strpos($offlineCanonical9acRaw, 'durableGroups') !== false
            && strpos($resumeCertDoc9acRaw, 'fieldflow-resume-certification-media-integrity-v1') !== false && strpos($resumeCertDoc9acRaw, '`SOURCE_RELEASE` is `2.2.544` and `STABLE_RELEASE` is `2.2.545`') !== false
            && strpos($resumeCertVerifier9acRaw, 'RESUME CERTIFICATION MEDIA 9AC CONTRACT OK') !== false && strpos($resumeCertVerifier9acRaw, 'successor=>=2.2.545') !== false
            && strpos($resumeCertRuntime9acRaw, 'RESUME CERTIFICATION MEDIA 9AC RUNTIME OK') !== false
            && strpos($resumeCertManager9acRaw, 'Resume Certification &amp; Media Integrity 9AC') !== false && strpos($resumeCertManager9acRaw, "packageResult['resume_certification_media_9ac_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['resume_certification_media_9ac_ok']) {
            $result['error'] = 'Resume Certification & Media Integrity 9AC is not fully wired into the release.';
            return $result;
        }

        // 2.2.546 Form Viewport & Shopping Delivery Integrity 9AD:
        // preserve the operator's exact visual working point and require the
        // Shopping message plus XLSX/PDF to share one canonical submission snapshot.
        $viewportDoc9adRaw = self::entryContents($entries, 'fieldflow/PHASE-9AD-FORM-VIEWPORT-SHOPPING-DELIVERY-INTEGRITY.md');
        $viewportVerifier9adRaw = self::entryContents($entries, 'fieldflow/tools/verify-form-viewport-shopping-delivery-9ad.php');
        $viewportRuntime9adRaw = self::entryContents($entries, 'fieldflow/tools/test-form-viewport-shopping-delivery-9ad.php');
        $viewportManager9adRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $viewportShortcodes9adRaw = self::entryContents($entries, 'fieldflow/src/Shortcodes.php');
        $shoppingService9adRaw = self::entryContents($entries, 'fieldflow/src/Shopping/ShoppingService.php');
        $shoppingExport9adRaw = self::entryContents($entries, 'fieldflow/src/Shopping/ShoppingExportService.php');
        $result['form_viewport_shopping_delivery_9ad_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.546', '>=')
            && $result['resume_certification_media_9ac_ok'] && $result['report_selection_media_9ab_ok']
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "FORM_VIEWPORT_SHOPPING_9AD_CONTRACT = 'fieldflow-form-viewport-shopping-delivery-integrity-v1'") !== false
            && strpos($releaseGateRaw, 'checkFormViewportShoppingDelivery9ADContract') !== false
            && $reportForm9abRaw !== '' && strpos($reportForm9abRaw, "RP_FORM_VIEWPORT_SHOPPING_CONTRACT='fieldflow-form-viewport-shopping-delivery-integrity-v1'") !== false
            && strpos($reportForm9abRaw, 'rpCaptureFormViewport') !== false && strpos($reportForm9abRaw, 'rpRestoreFormViewport') !== false
            && strpos($reportForm9abRaw, 'rpCaptureAllFormViewports') !== false && strpos($reportForm9abRaw, 'rpRestoreElementViewport') !== false
            && $viewportShortcodes9adRaw !== '' && strpos($viewportShortcodes9adRaw, "reportViewportContract='fieldflow-form-viewport-shopping-delivery-integrity-v1'") !== false
            && strpos($viewportShortcodes9adRaw, 'reserveReportSlotHeight') !== false && strpos($viewportShortcodes9adRaw, 'restoreReportViewport') !== false
            && strpos($viewportShortcodes9adRaw, "manual-store-dropdown") !== false
            && $shoppingService9adRaw !== '' && strpos($shoppingService9adRaw, 'fieldflow-shopping-canonical-attachment-v1') !== false
            && strpos($shoppingService9adRaw, 'canonical_submission_snapshot') !== false && strpos($shoppingService9adRaw, 'attachment_generation_failed') !== false
            && strpos($shoppingService9adRaw, '$requestedFormats = []') !== false && strpos($shoppingService9adRaw, '$missingAttachmentFormats') !== false
            && strpos($shoppingService9adRaw, 'if ($requestedFormats && ($missingAttachmentFormats || $attachmentErrors))') !== false
            && $shoppingExport9adRaw !== '' && strpos($shoppingExport9adRaw, 'array $snapshot = []') !== false
            && strpos($shoppingExport9adRaw, 'normaliseSubmissionDetail') !== false && strpos($shoppingExport9adRaw, 'canonical_submission_snapshot') !== false
            && strpos($shoppingExport9adRaw, "['Campo','Valor']") !== false && strpos($shoppingExport9adRaw, "'Diferença €'") !== false && strpos($shoppingExport9adRaw, "'Variação %'") !== false
            && strpos($shoppingExport9adRaw, 'priceAnalysis') !== false && strpos($shoppingExport9adRaw, 'RESUMO DA SUBMISSÃO') !== false && strpos($shoppingExport9adRaw, 'PREÇOS VERIFICADOS') !== false
            && $viewportDoc9adRaw !== '' && strpos($viewportDoc9adRaw, 'fieldflow-form-viewport-shopping-delivery-integrity-v1') !== false
            && strpos($viewportDoc9adRaw, '`SOURCE_RELEASE` is `2.2.545` and `STABLE_RELEASE` is `2.2.546`') !== false
            && strpos($viewportVerifier9adRaw, 'FORM VIEWPORT SHOPPING DELIVERY 9AD CONTRACT OK') !== false && strpos($viewportVerifier9adRaw, 'successor=>=2.2.546') !== false
            && strpos($viewportRuntime9adRaw, 'FORM VIEWPORT SHOPPING DELIVERY 9AD RUNTIME OK') !== false
            && strpos($viewportManager9adRaw, 'Form Viewport &amp; Shopping Delivery Integrity 9AD') !== false && strpos($viewportManager9adRaw, "packageResult['form_viewport_shopping_delivery_9ad_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['form_viewport_shopping_delivery_9ad_ok']) {
            $result['error'] = 'Form Viewport & Shopping Delivery Integrity 9AD is not fully wired into the release.';
            return $result;
        }

        // 2.2.547 Canonical Operational Reporting, Delivery Reliability & Viewport Continuity 9AE.
        $canonicalService9aeRaw = self::entryContents($entries, 'fieldflow/src/Reporting/CanonicalOperationalReportService.php');
        $canonicalStats9aeRaw = self::entryContents($entries, 'fieldflow/src/Rest/StatsController.php');
        $canonicalExports9aeRaw = self::entryContents($entries, 'fieldflow/src/Admin/FormSubmissions.php');
        $canonicalMail9aeRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReportAutomations.php');
        $canonicalQueue9aeRaw = self::entryContents($entries, 'fieldflow/src/Operations/Queue/QueueManager.php');
        $canonicalQueueSettings9aeRaw = self::entryContents($entries, 'fieldflow/src/Operations/Queue/QueueSettings.php');
        $canonicalShortcodes9aeRaw = self::entryContents($entries, 'fieldflow/src/Shortcodes.php');
        $canonicalForm9aeRaw = self::entryContents($entries, 'fieldflow/src/Forms/FormRenderer.php');
        $canonicalDoc9aeRaw = self::entryContents($entries, 'fieldflow/PHASE-9AE-CANONICAL-REPORTING-DELIVERY-VIEWPORT-CONTINUITY.md');
        $canonicalVerifier9aeRaw = self::entryContents($entries, 'fieldflow/tools/verify-canonical-reporting-delivery-9ae.php');
        $canonicalRuntime9aeRaw = self::entryContents($entries, 'fieldflow/tools/test-canonical-reporting-delivery-9ae.php');
        $canonicalManager9aeRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['canonical_reporting_delivery_9ae_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.547', '>=')
            && $result['form_viewport_shopping_delivery_9ad_ok'] && $result['mail_reliability_outbox_ok'] && $result['visit_closure_daily_mail_9w_ok']
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "CANONICAL_REPORTING_DELIVERY_9AE_CONTRACT = 'fieldflow-canonical-reporting-delivery-viewport-continuity-v1'") !== false
            && strpos($releaseGateRaw, 'checkCanonicalReportingDelivery9AEContract') !== false
            && $canonicalService9aeRaw !== '' && strpos($canonicalService9aeRaw, "CONTRACT = 'fieldflow-canonical-operational-commercial-report-v1'") !== false
            && strpos($canonicalService9aeRaw, "'operational_closure_rate'") !== false && strpos($canonicalService9aeRaw, "'extra_spaces' => \$spaces") !== false
            && $canonicalStats9aeRaw !== '' && strpos($canonicalStats9aeRaw, "'canonical_operational_report'") !== false && strpos($canonicalStats9aeRaw, 'CanonicalOperationalReportService::build') !== false
            && $canonicalExports9aeRaw !== '' && strpos($canonicalExports9aeRaw, 'canonical_dashboard_export_sheets') !== false && strpos($canonicalExports9aeRaw, 'canonical_dashboard_export_csv') !== false && strpos($canonicalExports9aeRaw, 'build_canonical_dashboard_pdf_binary') !== false
            && strpos($canonicalExports9aeRaw, 'Resumo Executivo') !== false && strpos($canonicalExports9aeRaw, 'Rotas & Visitas') !== false && strpos($canonicalExports9aeRaw, 'Espacos Extra') !== false
            && $canonicalMail9aeRaw !== '' && strpos($canonicalMail9aeRaw, 'reconcileMissedDailyClosures') !== false && strpos($canonicalMail9aeRaw, "'force_durable'=>true") !== false
            && strpos($canonicalMail9aeRaw, "if (!\$formats) \$formats = ['xlsx','pdf'];") !== false && strpos($canonicalMail9aeRaw, '$missingFormats') !== false && strpos($canonicalMail9aeRaw, 'Faltam anexos obrigatorios') !== false
            && $canonicalQueue9aeRaw !== '' && strpos($canonicalQueue9aeRaw, '$forceDurable') !== false
            && $canonicalQueueSettings9aeRaw !== '' && strpos($canonicalQueueSettings9aeRaw, 'fieldflow_report_automation_run') !== false && strpos($canonicalQueueSettings9aeRaw, 'fieldflow_daily_close_dispatch_retry') !== false
            && $canonicalShortcodes9aeRaw !== '' && strpos($canonicalShortcodes9aeRaw, "reportViewportContract='fieldflow-form-viewport-continuity-v2'") !== false && strpos($canonicalShortcodes9aeRaw, 'reportScrollContainer') !== false
            && strpos($canonicalShortcodes9aeRaw, "if(!visit){els.slot.innerHTML=''") !== false
            && $canonicalForm9aeRaw !== '' && strpos($canonicalForm9aeRaw, "rpViewportContract='fieldflow-form-viewport-continuity-v2'") !== false && strpos($canonicalForm9aeRaw, 'rpScrollContainerFor') !== false
            && $canonicalDoc9aeRaw !== '' && strpos($canonicalDoc9aeRaw, 'fieldflow-canonical-reporting-delivery-viewport-continuity-v1') !== false && strpos($canonicalDoc9aeRaw, '`SOURCE_RELEASE` is `2.2.546` and `STABLE_RELEASE` is `2.2.547`') !== false
            && $canonicalVerifier9aeRaw !== '' && strpos($canonicalVerifier9aeRaw, 'CANONICAL REPORTING DELIVERY 9AE CONTRACT OK') !== false
            && $canonicalRuntime9aeRaw !== '' && strpos($canonicalRuntime9aeRaw, 'CANONICAL REPORTING DELIVERY 9AE RUNTIME OK') !== false
            && $canonicalManager9aeRaw !== '' && strpos($canonicalManager9aeRaw, 'Canonical Reporting &amp; Delivery Reliability 9AE') !== false && strpos($canonicalManager9aeRaw, "packageResult['canonical_reporting_delivery_9ae_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['canonical_reporting_delivery_9ae_ok']) {
            $result['error'] = 'Canonical Reporting & Delivery Reliability 9AE is not fully wired into the release.';
            return $result;
        }



        // 2.2.548 Certification, Viewport Lock & Mail Template Recovery 9AF.
        $certViewportShortcodes9afRaw = self::entryContents($entries, 'fieldflow/src/Shortcodes.php');
        $certViewportForm9afRaw = self::entryContents($entries, 'fieldflow/src/Forms/FormRenderer.php');
        $certViewportApp9afRaw = self::entryContents($entries, 'fieldflow/src/Front/AppShortcodeRenderer.php');
        $certViewportMail9afRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReportAutomations.php');
        $certViewportDoc9afRaw = self::entryContents($entries, 'fieldflow/PHASE-9AF-CERTIFICATION-VIEWPORT-MAIL-RECOVERY.md');
        $certViewportVerifier9afRaw = self::entryContents($entries, 'fieldflow/tools/verify-certification-viewport-mail-9af.php');
        $certViewportRuntime9afRaw = self::entryContents($entries, 'fieldflow/tools/test-certification-viewport-mail-9af.php');
        $certViewportManager9afRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $bareFocus9af = $certViewportForm9afRaw !== '' && preg_match("/rpQueueResumeDraftForms\(\s*['\"]focus['\"]\s*\)/", $certViewportForm9afRaw);
        $result['certification_viewport_mail_9af_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.548', '>=')
            && $result['canonical_reporting_delivery_9ae_ok'] && $result['mobility_cost_intelligence_ok']
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "CERTIFICATION_VIEWPORT_MAIL_9AF_CONTRACT = 'fieldflow-certification-viewport-lock-mail-template-recovery-v1'") !== false
            && strpos($releaseGateRaw, 'checkCertificationViewportMail9AFContract') !== false
            && $certViewportShortcodes9afRaw !== '' && strpos($certViewportShortcodes9afRaw, 'fieldflow-form-viewport-lock-v1') !== false
            && strpos($certViewportShortcodes9afRaw, 'beginReportViewportLock') !== false && strpos($certViewportShortcodes9afRaw, 'finishReportViewportLock') !== false
            && strpos($certViewportShortcodes9afRaw, 'Combustível / energia') !== false && strpos($certViewportShortcodes9afRaw, 'Custo deslocação') !== false
            && strpos($certViewportShortcodes9afRaw, 'blur({preventScroll:true})') !== false
            && $certViewportForm9afRaw !== '' && strpos($certViewportForm9afRaw, 'fieldflow-form-viewport-lock-v1') !== false
            && strpos($certViewportForm9afRaw, 'rpBeginViewportLock') !== false && strpos($certViewportForm9afRaw, 'rpReleaseFormViewportLock') !== false
            && strpos($certViewportForm9afRaw, "rpQueueResumeDraftForms('focus-after-hidden')") !== false && !$bareFocus9af
            && $certViewportApp9afRaw !== '' && strpos($certViewportApp9afRaw, '.rp-premium-shell .ff-react-front-operational-fallback span') !== false
            && strpos($certViewportApp9afRaw, 'Operação pronta') !== false && strpos($certViewportApp9afRaw, 'Navegação clássica disponível') !== false
            && $certViewportMail9afRaw !== '' && strpos($certViewportMail9afRaw, 'projectMailTemplateState') !== false && strpos($certViewportMail9afRaw, 'ensureDailyCloseAutomation') !== false && strpos($certViewportMail9afRaw, 'recoverMissingProjectTemplateAutomations') !== false
            && strpos($certViewportMail9afRaw, 'Template do projeto · recuperável') !== false && strpos($certViewportMail9afRaw, 'Template canónico · recuperável') !== false
            && strpos($certViewportMail9afRaw, 'Deliberately paused rows are never reactivated here.') !== false
            && $certViewportDoc9afRaw !== '' && strpos($certViewportDoc9afRaw, 'fieldflow-certification-viewport-lock-mail-template-recovery-v1') !== false
            && strpos($certViewportDoc9afRaw, '`SOURCE_RELEASE` is `2.2.547` and `STABLE_RELEASE` is `2.2.548`') !== false
            && $certViewportVerifier9afRaw !== '' && strpos($certViewportVerifier9afRaw, 'CERTIFICATION VIEWPORT MAIL 9AF CONTRACT OK') !== false
            && $certViewportRuntime9afRaw !== '' && strpos($certViewportRuntime9afRaw, 'CERTIFICATION VIEWPORT MAIL 9AF RUNTIME OK') !== false
            && $certViewportManager9afRaw !== '' && strpos($certViewportManager9afRaw, 'Certification, Viewport Lock &amp; Mail Recovery 9AF') !== false
            && strpos($certViewportManager9afRaw, "packageResult['certification_viewport_mail_9af_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['certification_viewport_mail_9af_ok']) {
            $result['error'] = 'Certification, Viewport Lock & Mail Template Recovery 9AF is not fully wired into the release.';
            return $result;
        }


        // 2.2.549+ Mail Flow Finalization & True Viewport Freeze 9AG.
        $mailFlowShortcodes9agRaw = self::entryContents($entries, 'fieldflow/src/Shortcodes.php');
        $mailFlowForm9agRaw = self::entryContents($entries, 'fieldflow/src/Forms/FormRenderer.php');
        $mailFlowReport9agRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReportAutomations.php');
        $mailFlowShopping9agRaw = self::entryContents($entries, 'fieldflow/src/Shopping/ShoppingService.php');
        $mailFlowDispatch9agRaw = self::entryContents($entries, 'fieldflow/src/Services/MailDispatchService.php');
        $mailFlowDoc9agRaw = self::entryContents($entries, 'fieldflow/PHASE-9AG-MAIL-FLOW-FINALIZATION-TRUE-VIEWPORT-FREEZE.md');
        $mailFlowVerifier9agRaw = self::entryContents($entries, 'fieldflow/tools/verify-mail-flow-viewport-9ag.php');
        $mailFlowRuntime9agRaw = self::entryContents($entries, 'fieldflow/tools/test-mail-flow-viewport-9ag.php');
        $mailFlowManager9agRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $mailFlowBareBodyLock9ag = $mailFlowForm9agRaw !== '' && (strpos($mailFlowForm9agRaw, "document.body.style.position==='fixed'") !== false || strpos($mailFlowForm9agRaw, "document.body.style.position='fixed'") !== false);
        $mailFlowPassive551 = version_compare((string)($result['version'] ?? ''), '2.2.551', '>=');
        $mailFlowPaintMask550 = !$mailFlowPassive551 && version_compare((string)($result['version'] ?? ''), '2.2.550', '>=');
        $mailFlowViewportMarkersOk9ag = $mailFlowPassive551
            ? ($mailFlowShortcodes9agRaw !== ''
                && strpos($mailFlowShortcodes9agRaw, 'fieldflow-passive-viewport-continuity-v3') !== false
                && strpos($mailFlowShortcodes9agRaw, 'reportPassiveViewportContract') !== false
                && strpos($mailFlowShortcodes9agRaw, 'Passive continuity deliberately performs no scrollTop/window.scrollTo write.') !== false
                && strpos($mailFlowShortcodes9agRaw, 'fieldflow:form-external-handoff') !== false
                && strpos($mailFlowShortcodes9agRaw, 'fieldflow:form-viewport-unlocked') !== false
                && strpos($mailFlowShortcodes9agRaw, "root.style.position='fixed';") === false
                && $mailFlowForm9agRaw !== ''
                && strpos($mailFlowForm9agRaw, 'fieldflow-passive-viewport-continuity-v3') !== false
                && strpos($mailFlowForm9agRaw, 'Do not write scrollTop. The WebView/browser owns scrolling in 2.2.551.') !== false
                && strpos($mailFlowForm9agRaw, 'position:fixed!important;left:-10000px!important;top:0!important') !== false)
            : ($mailFlowPaintMask550
                ? ($mailFlowShortcodes9agRaw !== ''
                    && strpos($mailFlowShortcodes9agRaw, 'fieldflow-viewport-paint-mask-v1') !== false
                    && strpos($mailFlowShortcodes9agRaw, 'data-fieldflow-viewport-snapshot') !== false
                    && strpos($mailFlowShortcodes9agRaw, 'stripReportSnapshotRuntime') !== false
                    && strpos($mailFlowShortcodes9agRaw, 'guardReportViewport') !== false
                    && strpos($mailFlowShortcodes9agRaw, 'reportSelectionViewportState') !== false
                    && strpos($mailFlowShortcodes9agRaw, 'fieldflow:form-visual-freeze') !== false
                    && strpos($mailFlowShortcodes9agRaw, "root.style.position='fixed';") === false
                    && $mailFlowForm9agRaw !== ''
                    && strpos($mailFlowForm9agRaw, 'fieldflow-viewport-paint-mask-v1') !== false
                    && strpos($mailFlowForm9agRaw, 'rpRequestFormVisualFreeze') !== false
                    && strpos($mailFlowForm9agRaw, 'rpRequestFormVisualUnfreeze') !== false
                    && strpos($mailFlowForm9agRaw, 'conditional-choice-change') !== false
                    && strpos($mailFlowForm9agRaw, 'cardex-add') !== false
                    && strpos($mailFlowForm9agRaw, 'cardex-remove') !== false)
                : ($mailFlowShortcodes9agRaw !== ''
                    && strpos($mailFlowShortcodes9agRaw, 'fieldflow-visual-viewport-freeze-v2') !== false
                    && strpos($mailFlowShortcodes9agRaw, 'data-fieldflow-viewport-placeholder') !== false
                    && strpos($mailFlowShortcodes9agRaw, "root.style.position='fixed'") !== false
                    && $mailFlowForm9agRaw !== ''
                    && strpos($mailFlowForm9agRaw, 'fieldflow-visual-viewport-freeze-v2') !== false));
        $result['mail_flow_viewport_9ag_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.549', '>=')
            && $result['certification_viewport_mail_9af_ok'] && $result['canonical_reporting_delivery_9ae_ok']
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "MAIL_FLOW_VIEWPORT_9AG_CONTRACT = 'fieldflow-mail-flow-finalization-true-viewport-freeze-v1'") !== false
            && strpos($releaseGateRaw, 'checkMailFlowViewport9AGContract') !== false
            && $mailFlowViewportMarkersOk9ag
            && !$mailFlowBareBodyLock9ag
            && strpos($mailFlowShortcodes9agRaw, 'fieldflow:form-external-handoff') !== false && strpos($mailFlowShortcodes9agRaw, 'fieldflow:form-viewport-unlocked') !== false
            && strpos($mailFlowForm9agRaw, "rpQueueResumeDraftForms('focus-after-hidden')") !== false
            && $mailFlowReport9agRaw !== '' && strpos($mailFlowReport9agRaw, 'if ($automationType === \'daily_close\' && isset($ranges[\'daily\'])) $attachmentPeriodKeys = [\'daily\'];') !== false
            && strpos($mailFlowReport9agRaw, "'audience' => 'manager'") !== false && strpos($mailFlowReport9agRaw, 'Canonical report data uses full operational metric visibility') !== false
            && strpos($mailFlowReport9agRaw, 'Excel and PDF are part of the order-suggestion delivery contract') !== false && strpos($mailFlowReport9agRaw, 'orderSuggestionMailEligibility') !== false
            && $mailFlowShopping9agRaw !== '' && strpos($mailFlowShopping9agRaw, 'Shopping delivery contract: every verification email carries both canonical attachments.') !== false
            && strpos($mailFlowShopping9agRaw, '$requestedFormats = [\'xlsx\',\'pdf\'];') !== false
            && $mailFlowDispatch9agRaw !== '' && strpos($mailFlowDispatch9agRaw, '$seen = [];') !== false && strpos($mailFlowDispatch9agRaw, 'realpath($path) ?: $path') !== false
            && $mailFlowDoc9agRaw !== '' && strpos($mailFlowDoc9agRaw, 'fieldflow-mail-flow-finalization-true-viewport-freeze-v1') !== false
            && strpos($mailFlowDoc9agRaw, '`SOURCE_RELEASE` is `2.2.548` and `STABLE_RELEASE` is `2.2.549`') !== false
            && $mailFlowVerifier9agRaw !== '' && strpos($mailFlowVerifier9agRaw, 'MAIL FLOW VIEWPORT 9AG CONTRACT OK') !== false
            && $mailFlowRuntime9agRaw !== '' && strpos($mailFlowRuntime9agRaw, 'MAIL FLOW VIEWPORT 9AG RUNTIME OK') !== false
            && $mailFlowManager9agRaw !== '' && strpos($mailFlowManager9agRaw, 'Mail Flow Finalization &amp; True Viewport Freeze 9AG') !== false
            && strpos($mailFlowManager9agRaw, "packageResult['mail_flow_viewport_9ag_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['mail_flow_viewport_9ag_ok']) {
            $result['error'] = 'Mail Flow Finalization & True Viewport Freeze 9AG is not fully wired into the release.';
            return $result;
        }


        // 2.2.552+ Single Anchor Viewport Continuity 9AH.
        $singleAnchorForm9ahRaw = self::entryContents($entries, 'fieldflow/src/Forms/FormRenderer.php');
        $singleAnchorShortcodes9ahRaw = self::entryContents($entries, 'fieldflow/src/Shortcodes.php');
        $singleAnchorBridge9ahRaw = self::entryContents($entries, 'fieldflow/src/Mobile/WebViewBridge.php');
        $singleAnchorDoc9ahRaw = self::entryContents($entries, 'fieldflow/PHASE-9AH-SINGLE-ANCHOR-VIEWPORT-CONTINUITY.md');
        $singleAnchorVerifier9ahRaw = self::entryContents($entries, 'fieldflow/tools/verify-single-anchor-viewport-9ah.php');
        $singleAnchorRuntime9ahRaw = self::entryContents($entries, 'fieldflow/tools/test-single-anchor-viewport-9ah.php');
        $singleAnchorManager9ahRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $singleAnchorHotReplay = preg_match("/routesproResumeDraft=function\\(opts\\)\\{[\\s\\S]{0,900}?restoreDraft\\(\\);/", $singleAnchorForm9ahRaw) === 1;
        $singleAnchorBridgeCollapsesGeometry = preg_match('/ff-app-resume-portal-hidden[^\\n\\r]*content-visibility\\s*:\\s*hidden/i', $singleAnchorBridge9ahRaw) === 1;
        $result['single_anchor_viewport_9ah_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.552', '>=')
            && $result['mail_flow_viewport_9ag_ok']
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "SINGLE_ANCHOR_VIEWPORT_9AH_CONTRACT = 'fieldflow-single-anchor-viewport-continuity-v4'") !== false
            && strpos($releaseGateRaw, 'checkSingleAnchorViewport9AHContract') !== false
            && $singleAnchorForm9ahRaw !== '' && strpos($singleAnchorForm9ahRaw, 'fieldflow-single-anchor-viewport-continuity-v4') !== false
            && strpos($singleAnchorForm9ahRaw, 'fieldflow-hot-resume-dom-authority-v1') !== false
            && strpos($singleAnchorForm9ahRaw, "rpRestoreFormViewport(form,'media-return')") !== false
            && strpos($singleAnchorForm9ahRaw, "rpRestoreFormViewport(form,'draft-cold-restore')") !== false
            && strpos($singleAnchorForm9ahRaw, "rpRestoreFormViewport(form,'hot-resume')") !== false
            && strpos($singleAnchorForm9ahRaw, 'apply();window.requestAnimationFrame(apply);') === false
            && strpos($singleAnchorForm9ahRaw, "finally(function(){rpRestoreFormViewport(form,'media-selected')") === false
            && !$singleAnchorHotReplay
            && $singleAnchorShortcodes9ahRaw !== '' && strpos($singleAnchorShortcodes9ahRaw, 'reportSingleAnchorViewportContract') !== false
            && strpos($singleAnchorShortcodes9ahRaw, 'reportSelectionAnchor=captureReportViewport()') !== false
            && strpos($singleAnchorShortcodes9ahRaw, 'options.viewportCheckpoint||captureReportViewport()') !== false
            && $singleAnchorBridge9ahRaw !== '' && strpos($singleAnchorBridge9ahRaw, 'fieldflow-resume-geometry-preserving-v3') !== false
            && !$singleAnchorBridgeCollapsesGeometry
            && $singleAnchorDoc9ahRaw !== '' && strpos($singleAnchorDoc9ahRaw, 'fieldflow-single-anchor-viewport-continuity-v4') !== false
            && strpos($singleAnchorDoc9ahRaw, '`SOURCE_RELEASE` is `2.2.551` and `STABLE_RELEASE` is `2.2.552`') !== false
            && $singleAnchorVerifier9ahRaw !== '' && strpos($singleAnchorVerifier9ahRaw, 'SINGLE ANCHOR VIEWPORT 9AH CONTRACT OK') !== false
            && $singleAnchorRuntime9ahRaw !== '' && strpos($singleAnchorRuntime9ahRaw, 'SINGLE ANCHOR VIEWPORT 9AH RUNTIME OK') !== false
            && $singleAnchorManager9ahRaw !== '' && strpos($singleAnchorManager9ahRaw, 'Single Anchor Viewport Continuity 9AH') !== false
            && strpos($singleAnchorManager9ahRaw, "packageResult['single_anchor_viewport_9ah_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['single_anchor_viewport_9ah_ok']) {
            $result['error'] = 'Single Anchor Viewport Continuity 9AH is not fully wired into the release.';
            return $result;
        }

        // 2.2.553+ Mail Intelligence & Daily Commercial Reporting 9AI.
        $mailIntelligenceService9aiRaw = self::entryContents($entries, 'fieldflow/src/Reporting/DailyCommercialReportService.php');
        $mailIntelligenceCanonical9aiRaw = self::entryContents($entries, 'fieldflow/src/Reporting/CanonicalOperationalReportService.php');
        $mailIntelligenceAutomation9aiRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReportAutomations.php');
        $mailIntelligenceExports9aiRaw = self::entryContents($entries, 'fieldflow/src/Admin/FormSubmissions.php');
        $mailIntelligenceShopping9aiRaw = self::entryContents($entries, 'fieldflow/src/Shopping/ShoppingService.php');
        $mailIntelligenceNotify9aiRaw = self::entryContents($entries, 'fieldflow/src/Services/Notify.php');
        $mailIntelligenceRepo9aiRaw = self::entryContents($entries, 'fieldflow/src/Services/MailOutboxRepository.php');
        $mailIntelligenceDispatch9aiRaw = self::entryContents($entries, 'fieldflow/src/Services/MailDispatchService.php');
        $mailIntelligenceDoc9aiRaw = self::entryContents($entries, 'fieldflow/PHASE-9AI-MAIL-INTELLIGENCE-DAILY-COMMERCIAL-REPORTING.md');
        $mailIntelligenceVerifier9aiRaw = self::entryContents($entries, 'fieldflow/tools/verify-mail-intelligence-daily-reporting-9ai.php');
        $mailIntelligenceRuntime9aiRaw = self::entryContents($entries, 'fieldflow/tools/test-mail-intelligence-daily-reporting-9ai.php');
        $mailIntelligenceManager9aiRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['mail_intelligence_daily_reporting_9ai_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.553', '>=')
            && $result['single_anchor_viewport_9ah_ok']
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "MAIL_INTELLIGENCE_DAILY_REPORTING_9AI_CONTRACT = 'fieldflow-mail-intelligence-daily-commercial-reporting-v1'") !== false
            && strpos($releaseGateRaw, 'checkMailIntelligenceDailyReporting9AIContract') !== false
            && $mailIntelligenceService9aiRaw !== '' && strpos($mailIntelligenceService9aiRaw, 'fieldflow-mail-intelligence-daily-commercial-reporting-v1') !== false
            && strpos($mailIntelligenceService9aiRaw, "'client'=>self::clientNarrative") !== false
            && strpos($mailIntelligenceService9aiRaw, "'merchandiser'=>self::merchandiserNarrative") !== false
            && strpos($mailIntelligenceService9aiRaw, "'capabilities'=>") !== false
            && strpos($mailIntelligenceService9aiRaw, "'alerts'=>") !== false
            && $mailIntelligenceCanonical9aiRaw !== '' && strpos($mailIntelligenceCanonical9aiRaw, "'commercial_analysis'") !== false
            && strpos($mailIntelligenceCanonical9aiRaw, 'DailyCommercialReportService::build') !== false
            && $mailIntelligenceAutomation9aiRaw !== '' && strpos($mailIntelligenceAutomation9aiRaw, 'Visão comercial do dia') !== false
            && strpos($mailIntelligenceAutomation9aiRaw, 'Alertas e ações') !== false
            && strpos($mailIntelligenceAutomation9aiRaw, "'report_audience'") !== false
            && $mailIntelligenceExports9aiRaw !== '' && strpos($mailIntelligenceExports9aiRaw, 'Resumo Comercial') !== false
            && strpos($mailIntelligenceExports9aiRaw, 'Ganhos e Excecoes') !== false
            && strpos($mailIntelligenceExports9aiRaw, "'Visitas'") !== false
            && strpos($mailIntelligenceExports9aiRaw, "'Executada'") !== false
            && strpos($mailIntelligenceShopping9aiRaw, "shopping_verification|' . \$submissionId") !== false
            && strpos($mailIntelligenceShopping9aiRaw, "'queued_recipients'") !== false
            && $mailIntelligenceNotify9aiRaw !== '' && strpos($mailIntelligenceNotify9aiRaw, 'One transport envelope per recipient') !== false
            && strpos($mailIntelligenceNotify9aiRaw, "'queued_recipients'") !== false
            && $mailIntelligenceRepo9aiRaw !== '' && strpos($mailIntelligenceRepo9aiRaw, 'purgeTerminalRows') !== false
            && $mailIntelligenceDispatch9aiRaw !== '' && strpos($mailIntelligenceDispatch9aiRaw, 'OUTBOX_ROW_RETENTION_SECONDS') !== false
            && $mailIntelligenceDoc9aiRaw !== '' && strpos($mailIntelligenceDoc9aiRaw, 'fieldflow-mail-intelligence-daily-commercial-reporting-v1') !== false
            && strpos($mailIntelligenceDoc9aiRaw, '`SOURCE_RELEASE` is `2.2.552` and `STABLE_RELEASE` is `2.2.553`') !== false
            && $mailIntelligenceVerifier9aiRaw !== '' && strpos($mailIntelligenceVerifier9aiRaw, 'MAIL INTELLIGENCE DAILY REPORTING 9AI CONTRACT OK') !== false
            && $mailIntelligenceRuntime9aiRaw !== '' && strpos($mailIntelligenceRuntime9aiRaw, 'MAIL INTELLIGENCE DAILY REPORTING 9AI RUNTIME OK') !== false
            && $mailIntelligenceManager9aiRaw !== '' && strpos($mailIntelligenceManager9aiRaw, 'Mail Intelligence &amp; Daily Commercial Reporting 9AI') !== false
            && strpos($mailIntelligenceManager9aiRaw, "packageResult['mail_intelligence_daily_reporting_9ai_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['mail_intelligence_daily_reporting_9ai_ok']) {
            $result['error'] = 'Mail Intelligence & Daily Commercial Reporting 9AI is not fully wired into the release.';
            return $result;
        }

        // 2.2.554+ Briefing Store Scope Reporting Continuity 9AJ.
        $briefingScopeStats9ajRaw = self::entryContents($entries, 'fieldflow/src/Rest/StatsController.php');
        $briefingScopeCanonical9ajRaw = self::entryContents($entries, 'fieldflow/src/Reporting/CanonicalOperationalReportService.php');
        $briefingScopeCommercial9ajRaw = self::entryContents($entries, 'fieldflow/src/Reporting/DailyCommercialReportService.php');
        $briefingScopeAutomation9ajRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReportAutomations.php');
        $briefingScopeExports9ajRaw = self::entryContents($entries, 'fieldflow/src/Admin/FormSubmissions.php');
        $briefingScopeDoc9ajRaw = self::entryContents($entries, 'fieldflow/PHASE-9AJ-BRIEFING-STORE-SCOPE-REPORTING-CONTINUITY.md');
        $briefingScopeVerifier9ajRaw = self::entryContents($entries, 'fieldflow/tools/verify-briefing-store-scope-reporting-9aj.php');
        $briefingScopeRuntime9ajRaw = self::entryContents($entries, 'fieldflow/tools/test-briefing-store-scope-reporting-9aj.php');
        $briefingScopeManager9ajRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['briefing_store_scope_reporting_9aj_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.554', '>=')
            && $result['mail_intelligence_daily_reporting_9ai_ok']
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "BRIEFING_STORE_SCOPE_REPORTING_9AJ_CONTRACT = 'fieldflow-briefing-store-scope-reporting-v1'") !== false
            && strpos($releaseGateRaw, 'checkBriefingStoreScopeReporting9AJContract') !== false
            && $briefingScopeStats9ajRaw !== '' && strpos($briefingScopeStats9ajRaw, "'target_assignment_keys'=>") !== false
            && strpos($briefingScopeStats9ajRaw, "'answered_assignment_keys'=>") !== false
            && $briefingScopeCanonical9ajRaw !== '' && strpos($briefingScopeCanonical9ajRaw, 'question_store_mapping_x_report_routes') !== false
            && strpos($briefingScopeCanonical9ajRaw, 'briefingAssignmentLocationId') !== false
            && strpos($briefingScopeCanonical9ajRaw, "'briefing_target_assignments'") !== false
            && strpos($briefingScopeCanonical9ajRaw, "'briefing_pending_assignments'") !== false
            && $briefingScopeCommercial9ajRaw !== '' && strpos($briefingScopeCommercial9ajRaw, 'mapeadas às lojas presentes nesta rota/período') !== false
            && $briefingScopeAutomation9ajRaw !== '' && strpos($briefingScopeAutomation9ajRaw, 'respostas alvo') !== false
            && strpos($briefingScopeAutomation9ajRaw, 'loja(s) mapeada(s) na rota') !== false
            && $briefingScopeExports9ajRaw !== '' && strpos($briefingScopeExports9ajRaw, 'Respostas alvo') !== false
            && strpos($briefingScopeExports9ajRaw, 'Lojas alvo') !== false
            && $briefingScopeDoc9ajRaw !== '' && strpos($briefingScopeDoc9ajRaw, 'fieldflow-briefing-store-scope-reporting-v1') !== false
            && strpos($briefingScopeDoc9ajRaw, '`SOURCE_RELEASE` is `2.2.553` and `STABLE_RELEASE` is `2.2.554`') !== false
            && $briefingScopeVerifier9ajRaw !== '' && strpos($briefingScopeVerifier9ajRaw, 'BRIEFING STORE SCOPE REPORTING 9AJ CONTRACT OK') !== false
            && $briefingScopeRuntime9ajRaw !== '' && strpos($briefingScopeRuntime9ajRaw, 'BRIEFING STORE SCOPE REPORTING 9AJ RUNTIME OK') !== false
            && $briefingScopeManager9ajRaw !== '' && strpos($briefingScopeManager9ajRaw, 'Briefing Store Scope Reporting Continuity 9AJ') !== false
            && strpos($briefingScopeManager9ajRaw, "packageResult['briefing_store_scope_reporting_9aj_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['briefing_store_scope_reporting_9aj_ok']) {
            $result['error'] = 'Briefing Store Scope Reporting Continuity 9AJ is not fully wired into the release.';
            return $result;
        }

        // 2.2.555+ Daily Mobility Cost Reporting Continuity 9AK.
        $mobilityReportingCanonical9akRaw = self::entryContents($entries, 'fieldflow/src/Reporting/CanonicalOperationalReportService.php');
        $mobilityReportingCommercial9akRaw = self::entryContents($entries, 'fieldflow/src/Reporting/DailyCommercialReportService.php');
        $mobilityReportingAutomation9akRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReportAutomations.php');
        $mobilityReportingExports9akRaw = self::entryContents($entries, 'fieldflow/src/Admin/FormSubmissions.php');
        $mobilityReportingDoc9akRaw = self::entryContents($entries, 'fieldflow/PHASE-9AK-DAILY-MOBILITY-COST-REPORTING-CONTINUITY.md');
        $mobilityReportingVerifier9akRaw = self::entryContents($entries, 'fieldflow/tools/verify-daily-mobility-cost-reporting-9ak.php');
        $mobilityReportingRuntime9akRaw = self::entryContents($entries, 'fieldflow/tools/test-daily-mobility-cost-reporting-9ak.php');
        $mobilityReportingManager9akRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['daily_mobility_cost_reporting_9ak_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.555', '>=')
            && $result['briefing_store_scope_reporting_9aj_ok']
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "DAILY_MOBILITY_COST_REPORTING_9AK_CONTRACT = 'fieldflow-daily-mobility-cost-reporting-v1'") !== false
            && strpos($releaseGateRaw, 'checkDailyMobilityCostReporting9AKContract') !== false
            && $mobilityReportingCanonical9akRaw !== '' && strpos($mobilityReportingCanonical9akRaw, "'mobility' => \$mobility") !== false
            && strpos($mobilityReportingCanonical9akRaw, "'open_routes_excluded'") !== false
            && strpos($mobilityReportingCanonical9akRaw, "'mobility_energy_cost_eur'") !== false
            && strpos($mobilityReportingCanonical9akRaw, "'mobility_toll_cost_eur'") !== false
            && $mobilityReportingCommercial9akRaw !== '' && strpos($mobilityReportingCommercial9akRaw, "'mobility'=>") !== false
            && strpos($mobilityReportingCommercial9akRaw, "'energy_cost_eur'") !== false
            && strpos($mobilityReportingCommercial9akRaw, "'toll_cost_eur'") !== false
            && $mobilityReportingAutomation9akRaw !== '' && strpos($mobilityReportingAutomation9akRaw, 'Mobilidade e custos por dia') !== false
            && strpos($mobilityReportingAutomation9akRaw, 'Km executados') !== false
            && strpos($mobilityReportingAutomation9akRaw, 'TOTAL GERAL') !== false
            && $mobilityReportingExports9akRaw !== '' && strpos($mobilityReportingExports9akRaw, "'Mobilidade Diaria'") !== false
            && strpos($mobilityReportingExports9akRaw, 'Comb./energia EUR') !== false
            && strpos($mobilityReportingExports9akRaw, 'Portagens EUR') !== false
            && $mobilityReportingDoc9akRaw !== '' && strpos($mobilityReportingDoc9akRaw, 'fieldflow-daily-mobility-cost-reporting-v1') !== false
            && strpos($mobilityReportingDoc9akRaw, '`SOURCE_RELEASE` is `2.2.554` and `STABLE_RELEASE` is `2.2.555`') !== false
            && $mobilityReportingVerifier9akRaw !== '' && strpos($mobilityReportingVerifier9akRaw, 'DAILY MOBILITY COST REPORTING 9AK CONTRACT OK') !== false
            && $mobilityReportingRuntime9akRaw !== '' && strpos($mobilityReportingRuntime9akRaw, 'DAILY MOBILITY COST REPORTING 9AK RUNTIME OK') !== false
            && $mobilityReportingManager9akRaw !== '' && strpos($mobilityReportingManager9akRaw, 'Daily Mobility Cost Reporting Continuity 9AK') !== false
            && strpos($mobilityReportingManager9akRaw, "packageResult['daily_mobility_cost_reporting_9ak_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['daily_mobility_cost_reporting_9ak_ok']) {
            $result['error'] = 'Daily Mobility Cost Reporting Continuity 9AK is not fully wired into the release.';
            return $result;
        }

        // 2.2.556+ Known Mobility Reporting Recovery 9AL.
        $knownMobilityService9alRaw = self::entryContents($entries, 'fieldflow/src/Services/MobilityCostService.php');
        $knownMobilityCanonical9alRaw = self::entryContents($entries, 'fieldflow/src/Reporting/CanonicalOperationalReportService.php');
        $knownMobilityCommercial9alRaw = self::entryContents($entries, 'fieldflow/src/Reporting/DailyCommercialReportService.php');
        $knownMobilityMail9alRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReportAutomations.php');
        $knownMobilityExports9alRaw = self::entryContents($entries, 'fieldflow/src/Admin/FormSubmissions.php');
        $knownMobilityDoc9alRaw = self::entryContents($entries, 'fieldflow/PHASE-9AL-KNOWN-MOBILITY-REPORTING-RECOVERY.md');
        $knownMobilityVerifier9alRaw = self::entryContents($entries, 'fieldflow/tools/verify-known-mobility-reporting-recovery-9al.php');
        $knownMobilityRuntime9alRaw = self::entryContents($entries, 'fieldflow/tools/test-known-mobility-reporting-recovery-9al.php');
        $knownMobilityManager9alRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['known_mobility_reporting_recovery_9al_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.556', '>=')
            && $result['daily_mobility_cost_reporting_9ak_ok']
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "KNOWN_MOBILITY_REPORTING_RECOVERY_9AL_CONTRACT = 'fieldflow-known-mobility-reporting-recovery-v1'") !== false
            && strpos($releaseGateRaw, 'checkKnownMobilityReportingRecovery9ALContract') !== false
            && $knownMobilityService9alRaw !== '' && strpos($knownMobilityService9alRaw, 'reportingProjection') !== false
            && strpos($knownMobilityService9alRaw, "'known_toll_cost_eur'") !== false
            && strpos($knownMobilityService9alRaw, "'known_distance_estimate'") !== false
            && $knownMobilityCanonical9alRaw !== '' && strpos($knownMobilityCanonical9alRaw, "'mobility_reporting_recovered'") !== false
            && strpos($knownMobilityCanonical9alRaw, "'recovered_routes'") !== false
            && $knownMobilityCommercial9alRaw !== '' && strpos($knownMobilityCommercial9alRaw, 'estimativa recuperada a partir dos km conhecidos') !== false
            && strpos($knownMobilityCommercial9alRaw, 'custos de combustível/energia e portagens ainda por calcular') !== false
            && $knownMobilityMail9alRaw !== '' && strpos($knownMobilityMail9alRaw, 'estimativa recuperada') !== false
            && strpos($knownMobilityMail9alRaw, 'Por calcular') !== false && strpos($knownMobilityMail9alRaw, 'Por apurar') !== false
            && $knownMobilityExports9alRaw !== '' && strpos($knownMobilityExports9alRaw, 'ESTIMATIVA RECUPERADA') !== false
            && strpos($knownMobilityExports9alRaw, 'POR CALCULAR') !== false && strpos($knownMobilityExports9alRaw, 'custos por calcular') !== false
            && $knownMobilityDoc9alRaw !== '' && strpos($knownMobilityDoc9alRaw, 'fieldflow-known-mobility-reporting-recovery-v1') !== false
            && strpos($knownMobilityDoc9alRaw, '`SOURCE_RELEASE` is `2.2.555` and `STABLE_RELEASE` is `2.2.556`') !== false
            && $knownMobilityVerifier9alRaw !== '' && strpos($knownMobilityVerifier9alRaw, 'KNOWN MOBILITY REPORTING RECOVERY 9AL CONTRACT OK') !== false
            && $knownMobilityRuntime9alRaw !== '' && strpos($knownMobilityRuntime9alRaw, 'KNOWN MOBILITY REPORTING RECOVERY 9AL RUNTIME OK') !== false
            && $knownMobilityManager9alRaw !== '' && strpos($knownMobilityManager9alRaw, 'Known Mobility Reporting Recovery 9AL') !== false
            && strpos($knownMobilityManager9alRaw, "packageResult['known_mobility_reporting_recovery_9al_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['known_mobility_reporting_recovery_9al_ok']) {
            $result['error'] = 'Known Mobility Reporting Recovery 9AL is not fully wired into the release.';
            return $result;
        }

        // 2.2.557+ Full Route Mobility Reporting Reconciliation 9AM.
        $fullRouteMobilityService9amRaw = self::entryContents($entries, 'fieldflow/src/Services/MobilityCostService.php');
        $fullRouteMetrics9amRaw = self::entryContents($entries, 'fieldflow/src/Services/RouteMetricsService.php');
        $fullRouteCanonical9amRaw = self::entryContents($entries, 'fieldflow/src/Reporting/CanonicalOperationalReportService.php');
        $fullRouteMail9amRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReportAutomations.php');
        $fullRouteExports9amRaw = self::entryContents($entries, 'fieldflow/src/Admin/FormSubmissions.php');
        $fullRouteDoc9amRaw = self::entryContents($entries, 'fieldflow/PHASE-9AM-FULL-ROUTE-MOBILITY-REPORTING-RECONCILIATION.md');
        $fullRouteVerifier9amRaw = self::entryContents($entries, 'fieldflow/tools/verify-full-route-mobility-reporting-reconciliation-9am.php');
        $fullRouteRuntime9amRaw = self::entryContents($entries, 'fieldflow/tools/test-full-route-mobility-reporting-reconciliation-9am.php');
        $fullRouteManager9amRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['full_route_mobility_reporting_9am_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.557', '>=')
            && $result['known_mobility_reporting_recovery_9al_ok']
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "FULL_ROUTE_MOBILITY_REPORTING_9AM_CONTRACT = 'fieldflow-full-route-mobility-reporting-reconciliation-v1'") !== false
            && strpos($releaseGateRaw, 'checkFullRouteMobilityReporting9AMContract') !== false
            && $fullRouteMobilityService9amRaw !== '' && strpos($fullRouteMobilityService9amRaw, "'reporting_full_route_reconciled'") !== false
            && strpos($fullRouteMobilityService9amRaw, "'reporting_distance_basis'") !== false
            && strpos($fullRouteMobilityService9amRaw, "'reporting_access_distance_km'") !== false
            && strpos($fullRouteMobilityService9amRaw, "'reporting_return_distance_km'") !== false
            && $fullRouteMetrics9amRaw !== '' && strpos($fullRouteMetrics9amRaw, 'reportingStoredRouteLegBreakdown') !== false
            && $fullRouteCanonical9amRaw !== '' && strpos($fullRouteCanonical9amRaw, "'access_distance_km'") !== false
            && strpos($fullRouteCanonical9amRaw, "'inter_stop_distance_km'") !== false
            && strpos($fullRouteCanonical9amRaw, "'return_distance_km'") !== false
            && $fullRouteMail9amRaw !== '' && strpos($fullRouteMail9amRaw, 'Acesso ') !== false && strpos($fullRouteMail9amRaw, 'PDVs ') !== false && strpos($fullRouteMail9amRaw, 'Regresso ') !== false
            && $fullRouteExports9amRaw !== '' && strpos($fullRouteExports9amRaw, "'Km acesso','Km entre PDVs','Km regresso'") !== false
            && strpos($fullRouteExports9amRaw, 'Percurso integral por dia') !== false
            && $fullRouteDoc9amRaw !== '' && strpos($fullRouteDoc9amRaw, 'fieldflow-full-route-mobility-reporting-reconciliation-v1') !== false
            && strpos($fullRouteDoc9amRaw, '`SOURCE_RELEASE` is `2.2.556` and `STABLE_RELEASE` is `2.2.557`') !== false
            && $fullRouteVerifier9amRaw !== '' && strpos($fullRouteVerifier9amRaw, 'FULL ROUTE MOBILITY REPORTING RECONCILIATION 9AM CONTRACT OK') !== false
            && $fullRouteRuntime9amRaw !== '' && strpos($fullRouteRuntime9amRaw, 'FULL ROUTE MOBILITY REPORTING RECONCILIATION 9AM RUNTIME OK') !== false
            && $fullRouteManager9amRaw !== '' && strpos($fullRouteManager9amRaw, 'Full Route Mobility Reporting Reconciliation 9AM') !== false
            && strpos($fullRouteManager9amRaw, "packageResult['full_route_mobility_reporting_9am_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['full_route_mobility_reporting_9am_ok']) {
            $result['error'] = 'Full Route Mobility Reporting Reconciliation 9AM is not fully wired into the release.';
            return $result;
        }

        // 2.2.558+ Routing Endpoint Geographic Integrity 9AN.
        $geo9anRaw = self::entryContents($entries, 'fieldflow/src/Services/RoutingGeographyService.php');
        $maps9anRaw = self::entryContents($entries, 'fieldflow/src/Services/Maps.php');
        $resolver9anRaw = self::entryContents($entries, 'fieldflow/src/Services/RouteEndpointResolverService.php');
        $metrics9anRaw = self::entryContents($entries, 'fieldflow/src/Services/RouteMetricsService.php');
        $campaigns9anRaw = self::entryContents($entries, 'fieldflow/src/Admin/CampaignLocations.php');
        $routes9anRaw = self::entryContents($entries, 'fieldflow/src/Admin/Routes.php');
        $assignments9anRaw = self::entryContents($entries, 'fieldflow/src/Admin/Assignments.php');
        $mail9anRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReportAutomations.php');
        $doc9anRaw = self::entryContents($entries, 'fieldflow/PHASE-9AN-ROUTING-ENDPOINT-GEOGRAPHIC-INTEGRITY.md');
        $verify9anRaw = self::entryContents($entries, 'fieldflow/tools/verify-routing-endpoint-geographic-integrity-9an.php');
        $runtime9anRaw = self::entryContents($entries, 'fieldflow/tools/test-routing-endpoint-geographic-integrity-9an.php');
        $manager9anRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['routing_endpoint_geographic_integrity_9an_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.558', '>=')
            && $result['full_route_mobility_reporting_9am_ok']
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "ROUTING_ENDPOINT_GEOGRAPHIC_INTEGRITY_9AN_CONTRACT = 'fieldflow-routing-endpoint-geographic-integrity-v1'") !== false
            && strpos($releaseGateRaw, 'checkRoutingEndpointGeographicIntegrity9ANContract') !== false
            && $geo9anRaw !== '' && strpos($geo9anRaw, "CONTRACT = 'fieldflow-routing-endpoint-geographic-integrity-v1'") !== false
            && strpos($geo9anRaw, 'projectCountryCode') !== false && strpos($geo9anRaw, 'coordinatesLikelyInCountry') !== false
            && $maps9anRaw !== '' && strpos($maps9anRaw, "'components'=>'country:' . \$countryCode") !== false && strpos($maps9anRaw, "'countrySet'=>\$countryCode") !== false
            && strpos($maps9anRaw, 'google_geocode_v2') !== false && strpos($maps9anRaw, 'azure_geocode_v2') !== false
            && $resolver9anRaw !== '' && strpos($resolver9anRaw, 'country_safe_coordinate_fallback') !== false && strpos($resolver9anRaw, "'country_code' => \$countryCode") !== false
            && $metrics9anRaw !== '' && strpos($metrics9anRaw, 'ensureGeographicIntegrityForRoute') !== false && strpos($metrics9anRaw, 'reconcileProjectGeographicIntegrity') !== false && strpos($metrics9anRaw, 'google_routes_geography_v3') !== false
            && $campaigns9anRaw !== '' && strpos($campaigns9anRaw, 'routing_country_code') !== false && strpos($campaigns9anRaw, 'componentRestrictions') !== false
            && $routes9anRaw !== '' && strpos($routes9anRaw, 'routingCountryForSelection') !== false
            && $assignments9anRaw !== '' && strpos($assignments9anRaw, 'reconcile_routing_geography') !== false
            && $mail9anRaw !== '' && strpos($mail9anRaw, 'ensureGeographicIntegrityForRoute') !== false && strpos($mail9anRaw, '$subjectAnchorDate') !== false
            && $doc9anRaw !== '' && strpos($doc9anRaw, 'fieldflow-routing-endpoint-geographic-integrity-v1') !== false && strpos($doc9anRaw, '`SOURCE_RELEASE` is `2.2.557` and `STABLE_RELEASE` is `2.2.558`') !== false
            && $verify9anRaw !== '' && strpos($verify9anRaw, 'ROUTING ENDPOINT GEOGRAPHIC INTEGRITY 9AN CONTRACT OK') !== false
            && $runtime9anRaw !== '' && strpos($runtime9anRaw, 'ROUTING ENDPOINT GEOGRAPHIC INTEGRITY 9AN RUNTIME OK') !== false
            && $manager9anRaw !== '' && strpos($manager9anRaw, 'Routing Endpoint Geographic Integrity 9AN') !== false && strpos($manager9anRaw, "packageResult['routing_endpoint_geographic_integrity_9an_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['routing_endpoint_geographic_integrity_9an_ok']) {
            $result['error'] = 'Routing Endpoint Geographic Integrity 9AN is not fully wired into the release.';
            return $result;
        }

        // 2.2.559+ Route & Mobility Dashboard Widgets 9AO.
        $routeWidgetService9aoRaw = self::entryContents($entries, 'fieldflow/src/Analytics/RouteMobilityWidgetService.php');
        $routeWidgetCanonical9aoRaw = self::entryContents($entries, 'fieldflow/src/Reporting/CanonicalOperationalReportService.php');
        $routeWidgetStats9aoRaw = self::entryContents($entries, 'fieldflow/src/Rest/StatsController.php');
        $routeWidgetAdmin9aoRaw = self::entryContents($entries, 'fieldflow/src/Admin/Assignments.php');
        $routeWidgetFront9aoRaw = self::entryContents($entries, 'fieldflow/src/Front/ClientPortalShortcodeRenderer.php');
        $routeWidgetOrchestrator9aoRaw = self::entryContents($entries, 'fieldflow/src/Analytics/WidgetOrchestrator.php');
        $routeWidgetDoc9aoRaw = self::entryContents($entries, 'fieldflow/PHASE-9AO-ROUTE-MOBILITY-DASHBOARD-WIDGETS.md');
        $routeWidgetVerify9aoRaw = self::entryContents($entries, 'fieldflow/tools/verify-route-mobility-dashboard-widgets-9ao.php');
        $routeWidgetRuntime9aoRaw = self::entryContents($entries, 'fieldflow/tools/test-route-mobility-dashboard-widgets-9ao.php');
        $result['route_mobility_dashboard_widgets_9ao_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.559', '>=')
            && $routeWidgetService9aoRaw !== '' && strpos($routeWidgetService9aoRaw, "CONTRACT = 'fieldflow-route-mobility-dashboard-widgets-v1'") !== false
            && strpos($routeWidgetService9aoRaw, 'CanonicalOperationalReportService::routeMobilitySnapshot') !== false
            && strpos($routeWidgetService9aoRaw, "'route_execution'") !== false && strpos($routeWidgetService9aoRaw, "'route_daily_mobility'") !== false
            && $routeWidgetCanonical9aoRaw !== '' && strpos($routeWidgetCanonical9aoRaw, 'public static function routeMobilitySnapshot') !== false
            && $routeWidgetStats9aoRaw !== '' && strpos($routeWidgetStats9aoRaw, 'RouteMobilityWidgetService::buildMetric') !== false
            && $routeWidgetAdmin9aoRaw !== '' && strpos($routeWidgetAdmin9aoRaw, 'Origem do widget') !== false && strpos($routeWidgetAdmin9aoRaw, 'Rotas e mobilidade') !== false
            && $routeWidgetFront9aoRaw !== '' && strpos($routeWidgetFront9aoRaw, 'routeMobilityTableHtml') !== false && strpos($routeWidgetFront9aoRaw, 'Km acesso') !== false && strpos($routeWidgetFront9aoRaw, 'Km entre PDVs') !== false && strpos($routeWidgetFront9aoRaw, 'Km regresso') !== false
            && $routeWidgetOrchestrator9aoRaw !== '' && strpos($routeWidgetOrchestrator9aoRaw, "return 'mobility'") !== false
            && $routeWidgetDoc9aoRaw !== '' && strpos($routeWidgetDoc9aoRaw, '`SOURCE_RELEASE` is `2.2.558` and `STABLE_RELEASE` is `2.2.559`') !== false
            && $routeWidgetVerify9aoRaw !== '' && strpos($routeWidgetVerify9aoRaw, 'ROUTE MOBILITY DASHBOARD WIDGETS 9AO CONTRACT OK') !== false
            && $routeWidgetRuntime9aoRaw !== '' && strpos($routeWidgetRuntime9aoRaw, 'ROUTE MOBILITY DASHBOARD WIDGETS 9AO RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "ROUTE_MOBILITY_DASHBOARD_WIDGETS_9AO_CONTRACT = 'fieldflow-route-mobility-dashboard-widgets-v1'") !== false
            && strpos($releaseGateRaw, 'checkRouteMobilityDashboardWidgets9AOContract') !== false
            && $manager9anRaw !== '' && strpos($manager9anRaw, 'Route & Mobility Dashboard Widgets 9AO') !== false && strpos($manager9anRaw, "packageResult['route_mobility_dashboard_widgets_9ao_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['route_mobility_dashboard_widgets_9ao_ok']) {
            $result['error'] = 'Route & Mobility Dashboard Widgets 9AO is not fully wired into the release.';
            return $result;
        }

        // 2.2.560+ Report Status Canonical Sync 9AP.
        $reportStatusShortcodes9apRaw = self::entryContents($entries, 'fieldflow/src/Shortcodes.php');
        $reportStatusRoutes9apRaw = self::entryContents($entries, 'fieldflow/src/Rest/RoutesController.php');
        $reportStatusDoc9apRaw = self::entryContents($entries, 'fieldflow/PHASE-9AP-REPORT-STATUS-CANONICAL-SYNC.md');
        $reportStatusVerify9apRaw = self::entryContents($entries, 'fieldflow/tools/verify-report-status-canonical-sync-9ap.php');
        $reportStatusRuntime9apRaw = self::entryContents($entries, 'fieldflow/tools/test-report-status-canonical-sync-9ap.php');
        $result['report_status_canonical_sync_9ap_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.560', '>=')
            && $reportStatusShortcodes9apRaw !== '' && strpos($reportStatusShortcodes9apRaw, "reportStatusSyncContract='fieldflow-report-status-canonical-sync-v1'") !== false
            && strpos($reportStatusShortcodes9apRaw, 'recentVisitStates') !== false
            && strpos($reportStatusShortcodes9apRaw, 'fieldflowLive:true') !== false
            && strpos($reportStatusShortcodes9apRaw, "canonicalVisitStatus(stop.canonical_status||stop.status||'pending')") !== false
            && strpos($reportStatusShortcodes9apRaw, "document.addEventListener('fieldflow:visit-completed'") !== false
            && strpos($reportStatusShortcodes9apRaw, 'cached&&remembered') !== false
            && $reportStatusRoutes9apRaw !== '' && strpos($reportStatusRoutes9apRaw, 'CanonicalStateService::visit') !== false
            && strpos($reportStatusRoutes9apRaw, "'canonical_status'") !== false
            && strpos($reportStatusRoutes9apRaw, "'legacy_status'") !== false
            && strpos($reportStatusRoutes9apRaw, "'status_source'") !== false
            && strpos($reportStatusRoutes9apRaw, 'canonical_visit_state') !== false
            && $reportStatusDoc9apRaw !== '' && strpos($reportStatusDoc9apRaw, '`SOURCE_RELEASE` is `2.2.559` and `STABLE_RELEASE` is `2.2.560`') !== false
            && $reportStatusVerify9apRaw !== '' && strpos($reportStatusVerify9apRaw, 'REPORT STATUS CANONICAL SYNC 9AP CONTRACT OK') !== false
            && $reportStatusRuntime9apRaw !== '' && strpos($reportStatusRuntime9apRaw, 'REPORT STATUS CANONICAL SYNC 9AP RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "REPORT_STATUS_CANONICAL_SYNC_9AP_CONTRACT = 'fieldflow-report-status-canonical-sync-v1'") !== false
            && strpos($releaseGateRaw, 'checkReportStatusCanonicalSync9APContract') !== false
            && $manager9anRaw !== '' && strpos($manager9anRaw, 'Report Status Canonical Sync 9AP') !== false && strpos($manager9anRaw, "packageResult['report_status_canonical_sync_9ap_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['report_status_canonical_sync_9ap_ok']) {
            $result['error'] = 'Report Status Canonical Sync 9AP is not fully wired into the release.';
            return $result;
        }

        // 2.2.561+ Condition Graph Integrity 9AQ.
        $conditionGraph9aqRaw = self::entryContents($entries, 'fieldflow/src/Forms/ConditionalGraphIntegrity.php');
        $conditionAdmin9aqRaw = self::entryContents($entries, 'fieldflow/src/Admin/Forms.php');
        $conditionBuilder9aqRaw = self::entryContents($entries, 'fieldflow/assets/routespro-form-builder.js');
        $conditionCss9aqRaw = self::entryContents($entries, 'fieldflow/assets/routespro-form-builder.css');
        $conditionDoc9aqRaw = self::entryContents($entries, 'fieldflow/PHASE-9AQ-CONDITION-GRAPH-INTEGRITY.md');
        $conditionVerify9aqRaw = self::entryContents($entries, 'fieldflow/tools/verify-condition-graph-integrity-9aq.php');
        $conditionRuntime9aqRaw = self::entryContents($entries, 'fieldflow/tools/test-condition-graph-integrity-9aq.php');
        $conditionManager9aqRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['condition_graph_integrity_9aq_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.561', '>=')
            && $conditionGraph9aqRaw !== '' && strpos($conditionGraph9aqRaw, "CONTRACT = 'fieldflow-condition-graph-integrity-v1'") !== false
            && strpos($conditionGraph9aqRaw, 'cross_domain_gate') !== false
            && strpos($conditionGraph9aqRaw, 'controlled_by') !== false
            && strpos($conditionGraph9aqRaw, 'non_blocking_graph_audit') !== false
            && $conditionAdmin9aqRaw !== '' && strpos($conditionAdmin9aqRaw, 'ConditionalGraphIntegrity::analyse') !== false
            && strpos($conditionAdmin9aqRaw, 'Auditoria condicional') !== false
            && $conditionBuilder9aqRaw !== '' && strpos($conditionBuilder9aqRaw, "fieldflow-condition-graph-integrity-v1") !== false
            && strpos($conditionBuilder9aqRaw, 'Controlada por') !== false
            && strpos($conditionBuilder9aqRaw, 'Grafo condicional coerente') !== false
            && $conditionCss9aqRaw !== '' && strpos($conditionCss9aqRaw, '.twt-fb-integrity') !== false
            && strpos($conditionCss9aqRaw, '.twt-fb-controlled-by') !== false
            && $conditionDoc9aqRaw !== '' && strpos($conditionDoc9aqRaw, '`SOURCE_RELEASE` is `2.2.560` and `STABLE_RELEASE` is `2.2.561`') !== false
            && $conditionVerify9aqRaw !== '' && strpos($conditionVerify9aqRaw, 'CONDITION GRAPH INTEGRITY 9AQ CONTRACT OK') !== false
            && $conditionRuntime9aqRaw !== '' && strpos($conditionRuntime9aqRaw, 'CONDITION GRAPH INTEGRITY 9AQ RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "CONDITION_GRAPH_INTEGRITY_9AQ_CONTRACT = 'fieldflow-condition-graph-integrity-v1'") !== false
            && strpos($releaseGateRaw, 'checkConditionGraphIntegrity9AQContract') !== false
            && $conditionManager9aqRaw !== '' && strpos($conditionManager9aqRaw, 'Condition Graph Integrity 9AQ') !== false
            && strpos($conditionManager9aqRaw, "packageResult['condition_graph_integrity_9aq_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['condition_graph_integrity_9aq_ok']) {
            $result['error'] = 'Condition Graph Integrity 9AQ is not fully wired into the release.';
            return $result;
        }

        // 2.2.562+ WordPress Media Isolation 9AR.
        $mediaPlugin9arRaw = self::entryContents($entries, 'fieldflow/src/Support/Plugin.php');
        $mediaBootstrap9arRaw = self::entryContents($entries, 'fieldflow/fieldflow.php');
        $mediaGuard9arRaw = self::entryContents($entries, 'fieldflow/src/Security/UploadGuard.php');
        $mediaDoc9arRaw = self::entryContents($entries, 'fieldflow/PHASE-9AR-WORDPRESS-MEDIA-ISOLATION.md');
        $mediaVerify9arRaw = self::entryContents($entries, 'fieldflow/tools/verify-wordpress-media-isolation-9ar.php');
        $mediaRuntime9arRaw = self::entryContents($entries, 'fieldflow/tools/test-wordpress-media-isolation-9ar.php');
        $mediaManager9arRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $result['wordpress_media_isolation_9ar_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.562', '>=')
            && $mediaPlugin9arRaw !== '' && strpos($mediaPlugin9arRaw, "WORDPRESS_MEDIA_ISOLATION_CONTRACT = 'fieldflow-wordpress-media-isolation-v1'") !== false
            && strpos($mediaPlugin9arRaw, 'async-upload.php') !== false
            && strpos($mediaPlugin9arRaw, 'upload-attachment') !== false
            && strpos($mediaPlugin9arRaw, 'image-editor') !== false
            && strpos($mediaPlugin9arRaw, '/wp/v2/media') !== false
            && $mediaBootstrap9arRaw !== '' && strpos($mediaBootstrap9arRaw, 'isWordPressMediaProcessingRequest') !== false
            && strpos($mediaBootstrap9arRaw, 'WordPress Media Library isolation') !== false
            && $mediaGuard9arRaw !== '' && strpos($mediaGuard9arRaw, "if (!self::isFieldFlowRequest()) return;") !== false
            && strpos($mediaGuard9arRaw, 'isWordPressMediaProcessingRequest') !== false
            && strpos($mediaGuard9arRaw, "['routespro_form_id','form_id'") === false
            && $mediaDoc9arRaw !== '' && strpos($mediaDoc9arRaw, '`SOURCE_RELEASE` is `2.2.561` and `STABLE_RELEASE` is `2.2.562`') !== false
            && strpos($mediaDoc9arRaw, 'fieldflow-wordpress-media-isolation-v1') !== false
            && $mediaVerify9arRaw !== '' && strpos($mediaVerify9arRaw, 'WORDPRESS MEDIA ISOLATION 9AR CONTRACT OK') !== false
            && $mediaRuntime9arRaw !== '' && strpos($mediaRuntime9arRaw, 'WORDPRESS MEDIA ISOLATION 9AR RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "WORDPRESS_MEDIA_ISOLATION_9AR_CONTRACT = 'fieldflow-wordpress-media-isolation-v1'") !== false
            && strpos($releaseGateRaw, 'checkWordPressMediaIsolation9ARContract') !== false
            && $mediaManager9arRaw !== '' && strpos($mediaManager9arRaw, 'WordPress Media Isolation 9AR') !== false
            && strpos($mediaManager9arRaw, "packageResult['wordpress_media_isolation_9ar_ok']") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['wordpress_media_isolation_9ar_ok']) {
            $result['error'] = 'WordPress Media Isolation 9AR is not fully wired into the release.';
            return $result;
        }

        // 2.2.563+ My Route Periodicity Control 9AS.
        $periodicityService9asRaw = self::entryContents($entries, 'fieldflow/src/Services/PeriodicityProgressService.php');
        $periodicityRoutes9asRaw = self::entryContents($entries, 'fieldflow/src/Rest/RoutesController.php');
        $periodicityFront9asRaw = self::entryContents($entries, 'fieldflow/src/Shortcodes.php');
        $periodicityDoc9asRaw = self::entryContents($entries, 'fieldflow/PHASE-9AS-MY-ROUTE-PERIODICITY-CONTROL.md');
        $periodicityVerify9asRaw = self::entryContents($entries, 'fieldflow/tools/verify-my-route-periodicity-9as.php');
        $periodicityRuntime9asRaw = self::entryContents($entries, 'fieldflow/tools/test-my-route-periodicity-9as.php');
        $periodicityManager9asRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $phase6Stable9asRaw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $result['my_route_periodicity_9as_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.563', '>=')
            && $periodicityService9asRaw !== '' && strpos($periodicityService9asRaw, "CONTRACT = 'fieldflow-my-route-periodicity-control-v1'") !== false
            && strpos($periodicityService9asRaw, "SubmissionStatusPolicy::operationalSql('fs')") !== false
            && strpos($periodicityService9asRaw, "'planned'") !== false
            && strpos($periodicityService9asRaw, "'no_visit'") !== false
            && strpos($periodicityService9asRaw, 'Coberta no plano') !== false
            && $periodicityRoutes9asRaw !== '' && strpos($periodicityRoutes9asRaw, "'/my-periodicity'") !== false
            && strpos($periodicityRoutes9asRaw, 'PeriodicityProgressService::forUser') !== false
            && $periodicityFront9asRaw !== '' && strpos($periodicityFront9asRaw, 'rp-periodicity') !== false
            && strpos($periodicityFront9asRaw, 'Cobertura do mês') !== false
            && strpos($periodicityFront9asRaw, 'loadPeriodicity') !== false
            && strpos($periodicityFront9asRaw, 'forcePeriodicity') !== false
            && $periodicityDoc9asRaw !== '' && strpos($periodicityDoc9asRaw, '`SOURCE_RELEASE` is `2.2.562` and `STABLE_RELEASE` is `2.2.563`') !== false
            && strpos($periodicityDoc9asRaw, 'fieldflow-my-route-periodicity-control-v1') !== false
            && $periodicityVerify9asRaw !== '' && strpos($periodicityVerify9asRaw, 'MY ROUTE PERIODICITY 9AS CONTRACT OK') !== false
            && $periodicityRuntime9asRaw !== '' && strpos($periodicityRuntime9asRaw, 'MY ROUTE PERIODICITY 9AS RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "MY_ROUTE_PERIODICITY_9AS_CONTRACT = 'fieldflow-my-route-periodicity-control-v1'") !== false
            && strpos($releaseGateRaw, 'checkMyRoutePeriodicity9ASContract') !== false
            && $periodicityManager9asRaw !== '' && strpos($periodicityManager9asRaw, 'My Route Periodicity Control 9AS') !== false
            && strpos($periodicityManager9asRaw, "packageResult['my_route_periodicity_9as_ok']") !== false
            && $phase6Stable9asRaw !== '' && strpos($phase6Stable9asRaw, "SOURCE_RELEASE = '2.2.562'") !== false
            && strpos($phase6Stable9asRaw, "STABLE_RELEASE = '2.2.563'") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['my_route_periodicity_9as_ok']) {
            $result['error'] = 'My Route Periodicity Control 9AS is not fully wired into the release.';
            return $result;
        }

        // 2.2.564+ My Route Periodicity Action UX 9AT.
        $periodicityActionService9atRaw = self::entryContents($entries, 'fieldflow/src/Services/PeriodicityProgressService.php');
        $periodicityActionFront9atRaw = self::entryContents($entries, 'fieldflow/src/Shortcodes.php');
        $periodicityActionApp9atRaw = self::entryContents($entries, 'fieldflow/src/Front/AppShortcodeRenderer.php');
        $periodicityActionPlanner9atRaw = self::entryContents($entries, 'fieldflow/src/Front/SelfRoutePlannerShortcodeRenderer.php');
        $periodicityActionDoc9atRaw = self::entryContents($entries, 'fieldflow/PHASE-9AT-MY-ROUTE-PERIODICITY-ACTION-UX.md');
        $periodicityActionVerify9atRaw = self::entryContents($entries, 'fieldflow/tools/verify-my-route-periodicity-action-9at.php');
        $periodicityActionRuntime9atRaw = self::entryContents($entries, 'fieldflow/tools/test-my-route-periodicity-action-9at.php');
        $periodicityActionManager9atRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $phase6Stable9atRaw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $result['my_route_periodicity_action_9at_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.564', '>=')
            && $periodicityActionService9atRaw !== '' && strpos($periodicityActionService9atRaw, "ACTION_UX_CONTRACT = 'fieldflow-my-route-periodicity-action-ux-v1'") !== false
            && strpos($periodicityActionService9atRaw, 'businessDaysRemaining') !== false
            && strpos($periodicityActionService9atRaw, 'riskProfile') !== false
            && strpos($periodicityActionService9atRaw, "'risk_level'") !== false
            && strpos($periodicityActionService9atRaw, "'business_days_remaining'") !== false
            && $periodicityActionFront9atRaw !== '' && strpos($periodicityActionFront9atRaw, 'Só falta cumprir') !== false
            && strpos($periodicityActionFront9atRaw, 'rp-periodicity-risk') !== false
            && strpos($periodicityActionFront9atRaw, 'fieldflow:planner-intent:v1') !== false
            && strpos($periodicityActionFront9atRaw, 'fieldflow:open-planner') !== false
            && strpos($periodicityActionFront9atRaw, 'fieldflow:self-routes-created') !== false
            && $periodicityActionApp9atRaw !== '' && strpos($periodicityActionApp9atRaw, 'fieldflow-my-route-periodicity-action-ux-v1') !== false
            && strpos($periodicityActionApp9atRaw, 'fieldflow:planner-focus-store') !== false
            && $periodicityActionPlanner9atRaw !== '' && strpos($periodicityActionPlanner9atRaw, 'applyPlannerIntent') !== false
            && strpos($periodicityActionPlanner9atRaw, 'plannerIntentStorageKey') !== false
            && strpos($periodicityActionPlanner9atRaw, 'intent-focus') !== false
            && $periodicityActionDoc9atRaw !== '' && strpos($periodicityActionDoc9atRaw, '`SOURCE_RELEASE` is `2.2.563` and `STABLE_RELEASE` is `2.2.564`') !== false
            && strpos($periodicityActionDoc9atRaw, 'fieldflow-my-route-periodicity-action-ux-v1') !== false
            && $periodicityActionVerify9atRaw !== '' && strpos($periodicityActionVerify9atRaw, 'MY ROUTE PERIODICITY ACTION 9AT CONTRACT OK') !== false
            && $periodicityActionRuntime9atRaw !== '' && strpos($periodicityActionRuntime9atRaw, 'MY ROUTE PERIODICITY ACTION 9AT RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "MY_ROUTE_PERIODICITY_ACTION_9AT_CONTRACT = 'fieldflow-my-route-periodicity-action-ux-v1'") !== false
            && strpos($releaseGateRaw, 'checkMyRoutePeriodicityAction9ATContract') !== false
            && $periodicityActionManager9atRaw !== '' && strpos($periodicityActionManager9atRaw, 'My Route Periodicity Action UX 9AT') !== false
            && strpos($periodicityActionManager9atRaw, "packageResult['my_route_periodicity_action_9at_ok']") !== false
            && $phase6Stable9atRaw !== '' && strpos($phase6Stable9atRaw, "SOURCE_RELEASE = '2.2.563'") !== false
            && strpos($phase6Stable9atRaw, "STABLE_RELEASE = '2.2.564'") !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['my_route_periodicity_action_9at_ok']) {
            $result['error'] = 'My Route Periodicity Action UX 9AT is not fully wired into the release.';
            return $result;
        }

        // 2.2.565+ Release Manager Publication Receipt Continuity 9AU.
        $publicationReceipt9auRaw = self::entryContents($entries, 'fieldflow/src/Cloud/ReleasePublicationReceipt.php');
        $publicationManager9auRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $publicationPhase69auRaw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $publicationDoc9auRaw = self::entryContents($entries, 'fieldflow/PHASE-9AU-RELEASE-MANAGER-PUBLICATION-RECEIPT-CONTINUITY.md');
        $publicationVerify9auRaw = self::entryContents($entries, 'fieldflow/tools/verify-release-publication-receipt-continuity-9au.php');
        $publicationRuntime9auRaw = self::entryContents($entries, 'fieldflow/tools/test-release-publication-receipt-continuity-9au.php');
        $result['release_manager_publication_receipt_9au_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.565', '>=')
            && $publicationReceipt9auRaw !== '' && strpos($publicationReceipt9auRaw, "CONTRACT = 'fieldflow-release-manager-publication-receipt-v1'") !== false
            && strpos($publicationReceipt9auRaw, "OPTION = 'fieldflow_release_manager_last_state'") !== false
            && strpos($publicationReceipt9auRaw, 'auditCandidates') !== false
            && strpos($publicationReceipt9auRaw, "'action_name'=>'release.published'") !== false
            && strpos($publicationReceipt9auRaw, 'healLocalOption') !== false
            && $publicationManager9auRaw !== '' && strpos($publicationManager9auRaw, 'ReleasePublicationReceipt::persist($state)') !== false
            && strpos($publicationManager9auRaw, "ReleasePublicationReceipt::current(defined('FIELDFLOW_VERSION')") !== false
            && strpos($publicationManager9auRaw, 'Release Manager Publication Receipt Continuity 9AU') !== false
            && strpos($publicationManager9auRaw, "packageResult['release_manager_publication_receipt_9au_ok']") !== false
            && $publicationPhase69auRaw !== '' && strpos($publicationPhase69auRaw, 'ReleasePublicationReceipt::current(self::STABLE_RELEASE)') !== false
            && strpos($publicationPhase69auRaw, "'wordpress_evidence'") !== false
            && strpos($publicationPhase69auRaw, "'catalog_sha_match'") !== false
            && strpos($publicationPhase69auRaw, "SOURCE_RELEASE = '2.2.564'") !== false
            && strpos($publicationPhase69auRaw, "STABLE_RELEASE = '2.2.565'") !== false
            && $publicationDoc9auRaw !== '' && strpos($publicationDoc9auRaw, 'fieldflow-release-manager-publication-receipt-v1') !== false
            && strpos($publicationDoc9auRaw, '`SOURCE_RELEASE` is `2.2.564` and `STABLE_RELEASE` is `2.2.565`') !== false
            && strpos($publicationDoc9auRaw, 'No bypass') !== false
            && $publicationVerify9auRaw !== '' && strpos($publicationVerify9auRaw, 'RELEASE MANAGER PUBLICATION RECEIPT 9AU CONTRACT OK') !== false
            && $publicationRuntime9auRaw !== '' && strpos($publicationRuntime9auRaw, 'RELEASE MANAGER PUBLICATION RECEIPT 9AU RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "RELEASE_MANAGER_PUBLICATION_RECEIPT_9AU_CONTRACT = 'fieldflow-release-manager-publication-receipt-v1'") !== false
            && strpos($releaseGateRaw, 'checkReleaseManagerPublicationReceipt9AUContract') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['release_manager_publication_receipt_9au_ok']) {
            $result['error'] = 'Release Manager Publication Receipt Continuity 9AU is not fully wired into the release.';
            return $result;
        }

        // 2.2.566+ Client Cost Date Scope & Route Totals 9AV.
        $clientCostFront9avRaw = self::entryContents($entries, 'fieldflow/src/Front/ClientPortalShortcodeRenderer.php');
        $clientCostStats9avRaw = self::entryContents($entries, 'fieldflow/src/Rest/StatsController.php');
        $clientCostPhase69avRaw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $clientCostDoc9avRaw = self::entryContents($entries, 'fieldflow/PHASE-9AV-CLIENT-COST-DATE-SCOPE-ROUTE-TOTALS.md');
        $clientCostVerify9avRaw = self::entryContents($entries, 'fieldflow/tools/verify-client-cost-date-scope-route-totals-9av.php');
        $clientCostRuntime9avRaw = self::entryContents($entries, 'fieldflow/tools/test-client-cost-date-scope-route-totals-9av.php');
        $result['client_cost_date_scope_route_totals_9av_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.566', '>=')
            && $clientCostFront9avRaw !== '' && strpos($clientCostFront9avRaw, 'selectedFilterRange') !== false
            && strpos($clientCostFront9avRaw, 'routeInRange') !== false
            && strpos($clientCostFront9avRaw, 'routeAggregate') !== false
            && strpos($clientCostFront9avRaw, 'aggregateMoney') !== false
            && strpos($clientCostFront9avRaw, 'rp-route-list-totals') !== false
            && strpos($clientCostFront9avRaw, "p.set('date_from',els.from.value)") !== false
            && strpos($clientCostFront9avRaw, "p.set('date_to',els.to.value)") !== false
            && $clientCostStats9avRaw !== '' && strpos($clientCostStats9avRaw, "get_param('date_from')") !== false
            && strpos($clientCostStats9avRaw, "get_param('date_to')") !== false
            && $clientCostPhase69avRaw !== '' && strpos($clientCostPhase69avRaw, "SOURCE_RELEASE = '2.2.565'") !== false
            && strpos($clientCostPhase69avRaw, "STABLE_RELEASE = '2.2.566'") !== false
            && $clientCostDoc9avRaw !== '' && strpos($clientCostDoc9avRaw, 'fieldflow-client-cost-date-scope-route-totals-v1') !== false
            && strpos($clientCostDoc9avRaw, '`SOURCE_RELEASE` is `2.2.565` and `STABLE_RELEASE` is `2.2.566`') !== false
            && $clientCostVerify9avRaw !== '' && strpos($clientCostVerify9avRaw, 'CLIENT COST DATE SCOPE ROUTE TOTALS 9AV CONTRACT OK') !== false
            && $clientCostRuntime9avRaw !== '' && strpos($clientCostRuntime9avRaw, 'CLIENT COST DATE SCOPE ROUTE TOTALS 9AV RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "CLIENT_COST_DATE_SCOPE_ROUTE_TOTALS_9AV_CONTRACT = 'fieldflow-client-cost-date-scope-route-totals-v1'") !== false
            && strpos($releaseGateRaw, 'checkClientCostDateScopeRouteTotals9AVContract') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['client_cost_date_scope_route_totals_9av_ok']) {
            $result['error'] = 'Client Cost Date Scope & Route Totals 9AV is not fully wired into the release.';
            return $result;
        }

        // 2.2.567+ Route Financial Breakdown & Audience Analytics 9AW.
        $routeFinanceFront9awRaw = self::entryContents($entries, 'fieldflow/src/Front/ClientPortalShortcodeRenderer.php');
        $routeFinanceRoutes9awRaw = self::entryContents($entries, 'fieldflow/src/Rest/RoutesController.php');
        $routeFinanceRepo9awRaw = self::entryContents($entries, 'fieldflow/src/Repositories/RouteAccessRepository.php');
        $routeFinanceStats9awRaw = self::entryContents($entries, 'fieldflow/src/Rest/StatsController.php');
        $routeFinanceAsk9awRaw = self::entryContents($entries, 'fieldflow/src/Analytics/FieldFlowAskService.php');
        $routeFinanceAnalytics9awRaw = self::entryContents($entries, 'fieldflow/src/Shortcodes.php');
        $routeFinanceManager9awRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $routeFinancePhase69awRaw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $routeFinanceDoc9awRaw = self::entryContents($entries, 'fieldflow/PHASE-9AW-ROUTE-FINANCIAL-AUDIENCE-ANALYTICS.md');
        $routeFinanceVerify9awRaw = self::entryContents($entries, 'fieldflow/tools/verify-route-financial-audience-analytics-9aw.php');
        $routeFinanceRuntime9awRaw = self::entryContents($entries, 'fieldflow/tools/test-route-financial-audience-analytics-9aw.php');
        $result['route_financial_audience_analytics_9aw_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.567', '>=')
            && $routeFinanceFront9awRaw !== '' && strpos($routeFinanceFront9awRaw, 'routeOperationalCost') !== false
            && strpos($routeFinanceFront9awRaw, 'routeMobilityReportingAvailable') !== false
            && strpos($routeFinanceFront9awRaw, 'reporting_costs_available') !== false
            && strpos($routeFinanceFront9awRaw, 'Custo operacional') !== false
            && strpos($routeFinanceFront9awRaw, 'Custo deslocação total') !== false
            && strpos($routeFinanceFront9awRaw, 'rp-consolidated-summary') !== false
            && strpos($routeFinanceFront9awRaw, 'renderTeamPerformance') !== false
            && strpos($routeFinanceFront9awRaw, 'Performance da equipa') !== false
            && strpos($routeFinanceRoutes9awRaw, 'MobilityCostService::reportingProjection') !== false
            && strpos($routeFinanceRepo9awRaw, 'MobilityCostService::reportingProjection') !== false
            && $routeFinanceStats9awRaw !== '' && strpos($routeFinanceStats9awRaw, 'team_performance') !== false
            && strpos($routeFinanceStats9awRaw, 'fieldflow-audience-performance-v1') !== false
            && strpos($routeFinanceStats9awRaw, 'performance_index') !== false
            && strpos($routeFinanceStats9awRaw, "'question_metric_scope'") !== false
            && $routeFinanceAsk9awRaw !== '' && strpos($routeFinanceAsk9awRaw, "return 'performance'") !== false
            && strpos($routeFinanceAsk9awRaw, 'team_leader_name') !== false
            && strpos($routeFinanceAsk9awRaw, "audience === 'merchandiser' ? 'self' : 'scope_member'") !== false
            && $routeFinanceAnalytics9awRaw !== '' && strpos($routeFinanceAnalytics9awRaw, 'renderAudiencePerformance') !== false
            && strpos($routeFinanceAnalytics9awRaw, 'A minha performance') !== false
            && strpos($routeFinanceAnalytics9awRaw, 'Performance da equipa') !== false
            && $routeFinanceManager9awRaw !== '' && strpos($routeFinanceManager9awRaw, "packageResult['route_financial_audience_analytics_9aw_ok']") !== false
            && $routeFinancePhase69awRaw !== '' && strpos($routeFinancePhase69awRaw, "SOURCE_RELEASE = '2.2.566'") !== false
            && strpos($routeFinancePhase69awRaw, "STABLE_RELEASE = '2.2.567'") !== false
            && $routeFinanceDoc9awRaw !== '' && strpos($routeFinanceDoc9awRaw, 'fieldflow-route-financial-audience-analytics-v1') !== false
            && strpos($routeFinanceDoc9awRaw, '`SOURCE_RELEASE` is `2.2.566` and `STABLE_RELEASE` is `2.2.567`') !== false
            && $routeFinanceVerify9awRaw !== '' && strpos($routeFinanceVerify9awRaw, 'ROUTE FINANCIAL AUDIENCE ANALYTICS 9AW CONTRACT OK') !== false
            && $routeFinanceRuntime9awRaw !== '' && strpos($routeFinanceRuntime9awRaw, 'ROUTE FINANCIAL AUDIENCE ANALYTICS 9AW RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "ROUTE_FINANCIAL_AUDIENCE_ANALYTICS_9AW_CONTRACT = 'fieldflow-route-financial-audience-analytics-v1'") !== false
            && strpos($releaseGateRaw, 'checkRouteFinancialAudienceAnalytics9AWContract') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['route_financial_audience_analytics_9aw_ok']) {
            $result['error'] = 'Route Financial Breakdown & Audience Analytics 9AW is not fully wired into the release.';
            return $result;
        }


        // 2.2.568+ Client Portal Runtime Scope Continuity 9AX.
        $portalScopeFront9axRaw = self::entryContents($entries, 'fieldflow/src/Front/ClientPortalShortcodeRenderer.php');
        $portalScopePhase69axRaw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $portalScopeDoc9axRaw = self::entryContents($entries, 'fieldflow/PHASE-9AX-CLIENT-PORTAL-RUNTIME-SCOPE-CONTINUITY.md');
        $portalScopeVerify9axRaw = self::entryContents($entries, 'fieldflow/tools/verify-client-portal-runtime-scope-9ax.php');
        $portalScopeRuntime9axRaw = self::entryContents($entries, 'fieldflow/tools/test-client-portal-runtime-scope-9ax.php');
        $result['client_portal_runtime_scope_9ax_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.568', '>=')
            && $portalScopeFront9axRaw !== ''
            && strpos($portalScopeFront9axRaw, "function renderKpis(stats){ const prev=stats?.previous_summary||{}; const rank=stats?.rankings||{}; const perf=(stats&&stats.team_performance&&typeof stats.team_performance==='object')?stats.team_performance:{}; const leader=(perf.leader&&typeof perf.leader==='object')?perf.leader:null;") !== false
            && strpos($portalScopeFront9axRaw, 'renderKpis(state.stats);') !== false
            && strpos($portalScopeFront9axRaw, 'renderRoutesTable(state.stats.by_day||[]);') !== false
            && strpos($portalScopeFront9axRaw, 'renderAnalytics(state.stats);') !== false
            && $portalScopePhase69axRaw !== '' && strpos($portalScopePhase69axRaw, "SOURCE_RELEASE = '2.2.567'") !== false
            && strpos($portalScopePhase69axRaw, "STABLE_RELEASE = '2.2.568'") !== false
            && $portalScopeDoc9axRaw !== '' && strpos($portalScopeDoc9axRaw, 'fieldflow-client-portal-runtime-scope-continuity-v1') !== false
            && strpos($portalScopeDoc9axRaw, '`SOURCE_RELEASE` is `2.2.567` and `STABLE_RELEASE` is `2.2.568`') !== false
            && $portalScopeVerify9axRaw !== '' && strpos($portalScopeVerify9axRaw, 'CLIENT PORTAL RUNTIME SCOPE 9AX CONTRACT OK') !== false
            && $portalScopeRuntime9axRaw !== '' && strpos($portalScopeRuntime9axRaw, 'CLIENT PORTAL RUNTIME SCOPE 9AX RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "CLIENT_PORTAL_RUNTIME_SCOPE_9AX_CONTRACT = 'fieldflow-client-portal-runtime-scope-continuity-v1'") !== false
            && strpos($releaseGateRaw, 'checkClientPortalRuntimeScope9AXContract') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['client_portal_runtime_scope_9ax_ok']) {
            $result['error'] = 'Client Portal Runtime Scope Continuity 9AX is not fully wired into the release.';
            return $result;
        }


        // 2.2.569+ Client Portal Filter Authority Rebase 9AY.
        $filterAuthorityFront9ayRaw = self::entryContents($entries, 'fieldflow/src/Front/ClientPortalShortcodeRenderer.php');
        $filterAuthorityPhase69ayRaw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $filterAuthorityManager9ayRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $filterAuthorityDoc9ayRaw = self::entryContents($entries, 'fieldflow/PHASE-9AY-CLIENT-PORTAL-FILTER-AUTHORITY-REBASE.md');
        $filterAuthorityVerify9ayRaw = self::entryContents($entries, 'fieldflow/tools/verify-client-portal-filter-authority-9ay.php');
        $filterAuthorityRuntime9ayRaw = self::entryContents($entries, 'fieldflow/tools/test-client-portal-filter-authority-9ay.php');
        $result['client_portal_filter_authority_9ay_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.569', '>=')
            && $filterAuthorityFront9ayRaw !== ''
            && strpos($filterAuthorityFront9ayRaw, 'selectedFilterRange') !== false
            && strpos($filterAuthorityFront9ayRaw, 'routesInSelectedRange') !== false
            && strpos($filterAuthorityFront9ayRaw, 'O filtro global De/Até permanece ativo.') !== false
            && strpos($filterAuthorityFront9ayRaw, 'if(els.from) els.from.disabled=false;') !== false
            && strpos($filterAuthorityFront9ayRaw, 'if(els.to) els.to.disabled=false;') !== false
            && strpos($filterAuthorityFront9ayRaw, 'els.from.disabled=!!date') === false
            && strpos($filterAuthorityFront9ayRaw, 'els.to.disabled=!!date') === false
            && strpos($filterAuthorityFront9ayRaw, 'if(state.routeDateLocked) return;') === false
            && strpos($filterAuthorityFront9ayRaw, 'if(date) els.from.value=date;') === false
            && strpos($filterAuthorityFront9ayRaw, 'if(date) els.to.value=date;') === false
            && strpos($filterAuthorityFront9ayRaw, "p.set('from',els.from.value)") !== false
            && strpos($filterAuthorityFront9ayRaw, "p.set('to',els.to.value)") !== false
            && strpos($filterAuthorityFront9ayRaw, "p.set('date_from',els.from.value)") !== false
            && strpos($filterAuthorityFront9ayRaw, "p.set('date_to',els.to.value)") !== false
            && strpos($filterAuthorityFront9ayRaw, 'renderTeamPerformance') !== false
            && strpos($filterAuthorityFront9ayRaw, 'rp-consolidated-summary') !== false
            && strpos($filterAuthorityFront9ayRaw, 'Custo deslocação total') !== false
            && $filterAuthorityPhase69ayRaw !== '' && strpos($filterAuthorityPhase69ayRaw, "SOURCE_RELEASE = '2.2.568'") !== false
            && strpos($filterAuthorityPhase69ayRaw, "STABLE_RELEASE = '2.2.569'") !== false
            && $filterAuthorityManager9ayRaw !== '' && strpos($filterAuthorityManager9ayRaw, "packageResult['client_portal_filter_authority_9ay_ok']") !== false
            && $filterAuthorityDoc9ayRaw !== '' && strpos($filterAuthorityDoc9ayRaw, 'fieldflow-client-portal-filter-authority-rebase-v1') !== false
            && strpos($filterAuthorityDoc9ayRaw, '`SOURCE_RELEASE` is `2.2.568` and `STABLE_RELEASE` is `2.2.569`') !== false
            && $filterAuthorityVerify9ayRaw !== '' && strpos($filterAuthorityVerify9ayRaw, 'CLIENT PORTAL FILTER AUTHORITY 9AY CONTRACT OK') !== false
            && $filterAuthorityRuntime9ayRaw !== '' && strpos($filterAuthorityRuntime9ayRaw, 'CLIENT PORTAL FILTER AUTHORITY 9AY RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "CLIENT_PORTAL_FILTER_AUTHORITY_9AY_CONTRACT = 'fieldflow-client-portal-filter-authority-rebase-v1'") !== false
            && strpos($releaseGateRaw, 'checkClientPortalFilterAuthority9AYContract') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['client_portal_filter_authority_9ay_ok']) {
            $result['error'] = 'Client Portal Filter Authority Rebase 9AY is not fully wired into the release.';
            return $result;
        }


        // 2.2.570+ Route Calendar Daily Total & Mobile Portal Features 9AZ.
        $calendarFront9azRaw = self::entryContents($entries, 'fieldflow/src/Front/ClientPortalShortcodeRenderer.php');
        $mobileSettings9azRaw = self::entryContents($entries, 'fieldflow/src/Mobile/Settings.php');
        $mobileAdmin9azRaw = self::entryContents($entries, 'fieldflow/src/Admin/MobileControlCenter.php');
        $mobileController9azRaw = self::entryContents($entries, 'fieldflow/src/Mobile/Controller.php');
        $mobileBridge9azRaw = self::entryContents($entries, 'fieldflow/src/Mobile/WebViewBridge.php');
        $phase69azRaw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $manager9azRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $doc9azRaw = self::entryContents($entries, 'fieldflow/PHASE-9AZ-ROUTE-CALENDAR-DAILY-TOTAL-MOBILE-PORTAL-FEATURES.md');
        $verify9azRaw = self::entryContents($entries, 'fieldflow/tools/verify-route-calendar-mobile-portal-features-9az.php');
        $runtime9azRaw = self::entryContents($entries, 'fieldflow/tools/test-route-calendar-mobile-portal-features-9az.php');
        $calendarStart9az = strpos($calendarFront9azRaw, 'function renderRouteCalendar()');
        $calendarEnd9az = $calendarStart9az === false ? false : strpos($calendarFront9azRaw, 'async function openRouteFromAgenda', $calendarStart9az);
        $calendarBlock9az = ($calendarStart9az !== false && $calendarEnd9az !== false) ? substr($calendarFront9azRaw, $calendarStart9az, $calendarEnd9az - $calendarStart9az) : '';
        $result['route_calendar_mobile_portal_features_9az_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.570', '>=')
            && $calendarBlock9az !== ''
            && strpos($calendarBlock9az, 'const totals=routeAggregate(items);') !== false
            && strpos($calendarBlock9az, 'const totalLabel=aggregateMoney(totals.mobility,totals.unresolved);') !== false
            && strpos($calendarBlock9az, 'const toll=items.reduce') === false
            && strpos($calendarBlock9az, 'const tollLabel=') === false
            && strpos($calendarFront9azRaw, 'routeMobilityTotal(r)') !== false
            && $mobileSettings9azRaw !== '' && strpos($mobileSettings9azRaw, "'feature_visibility' => [") !== false
            && strpos($mobileSettings9azRaw, 'feature_for_target') !== false
            && strpos($mobileSettings9azRaw, 'features_for_target') !== false
            && strpos($mobileAdmin9azRaw, 'Funcionalidades móveis por portal') !== false
            && strpos($mobileAdmin9azRaw, 'Portal Operativo') !== false
            && strpos($mobileAdmin9azRaw, 'Portal Cliente') !== false
            && strpos($mobileAdmin9azRaw, 'fieldflow_mobile[feature_visibility]') !== false
            && strpos($mobileController9azRaw, "'portal_features' => \$portal_features") !== false
            && strpos($mobileController9azRaw, 'Settings::feature_for_target') !== false
            && strpos($mobileBridge9azRaw, 'Settings::feature_for_target($feature, $target)') !== false
            && $phase69azRaw !== '' && strpos($phase69azRaw, "SOURCE_RELEASE = '2.2.569'") !== false
            && strpos($phase69azRaw, "STABLE_RELEASE = '2.2.570'") !== false
            && $manager9azRaw !== '' && strpos($manager9azRaw, "packageResult['route_calendar_mobile_portal_features_9az_ok']") !== false
            && $doc9azRaw !== '' && strpos($doc9azRaw, 'fieldflow-route-calendar-daily-total-mobile-portal-features-v1') !== false
            && strpos($doc9azRaw, '`SOURCE_RELEASE` is `2.2.569` and `STABLE_RELEASE` is `2.2.570`') !== false
            && $verify9azRaw !== '' && strpos($verify9azRaw, 'ROUTE CALENDAR MOBILE PORTAL FEATURES 9AZ CONTRACT OK') !== false
            && $runtime9azRaw !== '' && strpos($runtime9azRaw, 'ROUTE CALENDAR MOBILE PORTAL FEATURES 9AZ RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "ROUTE_CALENDAR_MOBILE_PORTAL_FEATURES_9AZ_CONTRACT = 'fieldflow-route-calendar-daily-total-mobile-portal-features-v1'") !== false
            && strpos($releaseGateRaw, 'checkRouteCalendarMobilePortalFeatures9AZContract') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['route_calendar_mobile_portal_features_9az_ok']) {
            $result['error'] = 'Route Calendar Daily Total & Mobile Portal Features 9AZ is not fully wired into the release.';
            return $result;
        }


        // 2.2.571+ Client Route Hero Date Authority 9BA.
        $front9baRaw = self::entryContents($entries, 'fieldflow/src/Front/ClientPortalShortcodeRenderer.php');
        $phase69baRaw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $manager9baRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $doc9baRaw = self::entryContents($entries, 'fieldflow/PHASE-9BA-CLIENT-ROUTE-HERO-DATE-AUTHORITY.md');
        $verify9baRaw = self::entryContents($entries, 'fieldflow/tools/verify-client-route-hero-date-authority-9ba.php');
        $runtime9baRaw = self::entryContents($entries, 'fieldflow/tools/test-client-route-hero-date-authority-9ba.php');
        $calendarStart9ba = strpos($front9baRaw, 'function renderRouteCalendar()');
        $calendarEnd9ba = $calendarStart9ba === false ? false : strpos($front9baRaw, 'async function openRouteFromAgenda', $calendarStart9ba);
        $calendarBlock9ba = ($calendarStart9ba !== false && $calendarEnd9ba !== false) ? substr($front9baRaw, $calendarStart9ba, $calendarEnd9ba - $calendarStart9ba) : '';
        $listStart9ba = strpos($front9baRaw, 'function renderRouteList()');
        $listEnd9ba = $listStart9ba === false ? false : strpos($front9baRaw, 'function reportBadges', $listStart9ba);
        $listBlock9ba = ($listStart9ba !== false && $listEnd9ba !== false) ? substr($front9baRaw, $listStart9ba, $listEnd9ba - $listStart9ba) : '';
        $noSecondaryMonth9ba = true;
        foreach (['rpcp-route-month','rpcp-summary-month','data-cal-prev','data-cal-next','ownerRoutesMonth','routeCalendarMonth','Agenda anual'] as $forbidden9ba) {
            if (strpos($front9baRaw, $forbidden9ba) !== false) { $noSecondaryMonth9ba = false; break; }
        }
        $result['client_route_hero_date_authority_9ba_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.571', '>=')
            && $front9baRaw !== ''
            && strpos($front9baRaw, 'function agendaRange(){ return selectedFilterRange(); }') !== false
            && strpos($front9baRaw, "p.set('date_from',range.from)") !== false
            && strpos($front9baRaw, "p.set('date_to',range.to)") !== false
            && $calendarBlock9ba !== ''
            && strpos($calendarBlock9ba, 'const range=selectedFilterRange();') !== false
            && strpos($calendarBlock9ba, 'const routes=routesInSelectedRange(state.routes);') !== false
            && strpos($calendarBlock9ba, 'const totals=routeAggregate(items);') !== false
            && strpos($calendarBlock9ba, 'aggregateMoney(totals.mobility,totals.unresolved)') !== false
            && strpos($calendarBlock9ba, 'Calendário validado exclusivamente por <strong>De → Até</strong>') !== false
            && $listBlock9ba !== ''
            && strpos($listBlock9ba, 'const routes=routesInSelectedRange(state.routes);') !== false
            && $noSecondaryMonth9ba
            && strpos($front9baRaw, 'els.from.disabled=!!date') === false
            && strpos($front9baRaw, 'els.to.disabled=!!date') === false
            && strpos($front9baRaw, 'if(date) els.from.value=date;') === false
            && strpos($front9baRaw, 'if(date) els.to.value=date;') === false
            && $phase69baRaw !== '' && strpos($phase69baRaw, "SOURCE_RELEASE = '2.2.570'") !== false
            && strpos($phase69baRaw, "STABLE_RELEASE = '2.2.571'") !== false
            && $manager9baRaw !== '' && strpos($manager9baRaw, "packageResult['client_route_hero_date_authority_9ba_ok']") !== false
            && $doc9baRaw !== '' && strpos($doc9baRaw, 'fieldflow-client-route-hero-date-authority-v1') !== false
            && strpos($doc9baRaw, '`SOURCE_RELEASE` is `2.2.570` and `STABLE_RELEASE` is `2.2.571`') !== false
            && $verify9baRaw !== '' && strpos($verify9baRaw, 'CLIENT ROUTE HERO DATE AUTHORITY 9BA CONTRACT OK') !== false
            && $runtime9baRaw !== '' && strpos($runtime9baRaw, 'CLIENT ROUTE HERO DATE AUTHORITY 9BA RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "CLIENT_ROUTE_HERO_DATE_AUTHORITY_9BA_CONTRACT = 'fieldflow-client-route-hero-date-authority-v1'") !== false
            && strpos($releaseGateRaw, 'checkClientRouteHeroDateAuthority9BAContract') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['client_route_hero_date_authority_9ba_ok']) {
            $result['error'] = 'Client Route Hero Date Authority 9BA is not fully wired into the release.';
            return $result;
        }


        // 2.2.572+ Planner Assigned Store Scope Continuity 9BB.
        $permissions9bbRaw = self::entryContents($entries, 'fieldflow/src/Support/Permissions.php');
        $scopeIndex9bbRaw = self::entryContents($entries, 'fieldflow/src/Support/ScopeIndex.php');
        $routes9bbRaw = self::entryContents($entries, 'fieldflow/src/Rest/RoutesController.php');
        $planner9bbRaw = self::entryContents($entries, 'fieldflow/src/Front/SelfRoutePlannerShortcodeRenderer.php');
        $phase69bbRaw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $manager9bbRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $doc9bbRaw = self::entryContents($entries, 'fieldflow/PHASE-9BB-PLANNER-ASSIGNED-STORE-SCOPE-CONTINUITY.md');
        $verify9bbRaw = self::entryContents($entries, 'fieldflow/tools/verify-planner-assigned-store-scope-continuity-9bb.php');
        $runtime9bbRaw = self::entryContents($entries, 'fieldflow/tools/test-planner-assigned-store-scope-continuity-9bb.php');
        $result['planner_assigned_store_scope_9bb_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.572', '>=')
            && $permissions9bbRaw !== ''
            && strpos($permissions9bbRaw, 'assignedStoreScopeForUser') !== false
            && strpos($permissions9bbRaw, 'FROM {$px}campaign_locations cl') !== false
            && strpos($permissions9bbRaw, "cl.assigned_to=%d AND cl.is_active=1 AND cl.status='active'") !== false
            && strpos($permissions9bbRaw, 'Atribuições reais de lojas também são uma associação direta à campanha.') !== false
            && $scopeIndex9bbRaw !== ''
            && strpos($scopeIndex9bbRaw, "'campaign_location'") !== false
            && strpos($scopeIndex9bbRaw, 'FROM {$px}campaign_locations cl') !== false
            && strpos($scopeIndex9bbRaw, "source_type IN ('project_meta','project_assignment','campaign_location','route_owner','route_assignment')") !== false
            && $routes9bbRaw !== ''
            && strpos($routes9bbRaw, 'project_has_assigned_pdvs') !== false
            && strpos($routes9bbRaw, "assigned_to=%d AND is_active=1 AND status='active'") !== false
            && $planner9bbRaw !== ''
            && strpos($planner9bbRaw, 'Carregar lojas atribuídas') !== false
            && strpos($planner9bbRaw, 'campaign-pdvs?client_id=') !== false
            && strpos($planner9bbRaw, '&active=1&status=active') !== false
            && $phase69bbRaw !== '' && strpos($phase69bbRaw, "SOURCE_RELEASE = '2.2.571'") !== false
            && strpos($phase69bbRaw, "STABLE_RELEASE = '2.2.572'") !== false
            && $manager9bbRaw !== '' && strpos($manager9bbRaw, "packageResult['planner_assigned_store_scope_9bb_ok']") !== false
            && $doc9bbRaw !== '' && strpos($doc9bbRaw, 'fieldflow-planner-assigned-store-scope-continuity-v1') !== false
            && strpos($doc9bbRaw, '`SOURCE_RELEASE` is `2.2.571` and `STABLE_RELEASE` is `2.2.572`') !== false
            && $verify9bbRaw !== '' && strpos($verify9bbRaw, 'PLANNER ASSIGNED STORE SCOPE 9BB CONTRACT OK') !== false
            && $runtime9bbRaw !== '' && strpos($runtime9bbRaw, 'PLANNER ASSIGNED STORE SCOPE 9BB RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "PLANNER_ASSIGNED_STORE_SCOPE_9BB_CONTRACT = 'fieldflow-planner-assigned-store-scope-continuity-v1'") !== false
            && strpos($releaseGateRaw, 'checkPlannerAssignedStoreScope9BBContract') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['planner_assigned_store_scope_9bb_ok']) {
            $result['error'] = 'Planner Assigned Store Scope Continuity 9BB is not fully wired into the release.';
            return $result;
        }


        // 2.2.573+ Planner Canonical Assignment Catalog 9BC.
        $permissions9bcRaw = self::entryContents($entries, 'fieldflow/src/Support/Permissions.php');
        $routes9bcRaw = self::entryContents($entries, 'fieldflow/src/Rest/RoutesController.php');
        $mobile9bcRaw = self::entryContents($entries, 'fieldflow/src/Mobile/Controller.php');
        $planner9bcRaw = self::entryContents($entries, 'fieldflow/src/Front/SelfRoutePlannerShortcodeRenderer.php');
        $phase69bcRaw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $manager9bcRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $doc9bcRaw = self::entryContents($entries, 'fieldflow/PHASE-9BC-PLANNER-CANONICAL-ASSIGNMENT-CATALOG.md');
        $verify9bcRaw = self::entryContents($entries, 'fieldflow/tools/verify-planner-canonical-assignment-9bc.php');
        $runtime9bcRaw = self::entryContents($entries, 'fieldflow/tools/test-planner-canonical-assignment-9bc.php');
        $result['planner_canonical_assignment_9bc_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.573', '>=')
            && $permissions9bcRaw !== '' && strpos($permissions9bcRaw, '9BC planner catalog authority: assigned_to is canonical') !== false
            && strpos($permissions9bcRaw, 'WHERE cl.assigned_to=%d') !== false
            && $routes9bcRaw !== '' && strpos($routes9bcRaw, "get_param('planner_assigned')") !== false
            && strpos($routes9bcRaw, '9BC planner_assigned bypasses lifecycle filters') !== false
            && strpos($routes9bcRaw, 'WHERE project_id=%d AND assigned_to=%d LIMIT 1') !== false
            && $mobile9bcRaw !== '' && strpos($mobile9bcRaw, 'all_location_ids_lookup') !== false
            && strpos($mobile9bcRaw, '9BC cross-day repeat contract') !== false
            && strpos($mobile9bcRaw, "return new WP_Error('fieldflow_mobile_store_duplicate'") === false
            && $planner9bcRaw !== '' && strpos($planner9bcRaw, 'fieldflow-planner-canonical-assignment-catalog-v1') !== false
            && strpos($planner9bcRaw, 'planner_assigned=1') !== false
            && strpos($planner9bcRaw, 'dayHasStore') !== false
            && strpos($planner9bcRaw, 'dayPlanningLocked') !== false
            && strpos($planner9bcRaw, 'dirtyDays=new Set()') !== false
            && strpos($planner9bcRaw, 'Já no período · pode repetir') !== false
            && strpos($planner9bcRaw, 'periodDates.filter(date=>dirtyDays.has(date))') !== false
            && $phase69bcRaw !== '' && strpos($phase69bcRaw, "SOURCE_RELEASE = '2.2.572'") !== false
            && strpos($phase69bcRaw, "STABLE_RELEASE = '2.2.573'") !== false
            && $manager9bcRaw !== '' && strpos($manager9bcRaw, "packageResult['planner_canonical_assignment_9bc_ok']") !== false
            && $doc9bcRaw !== '' && strpos($doc9bcRaw, 'fieldflow-planner-canonical-assignment-catalog-v1') !== false
            && strpos($doc9bcRaw, '`SOURCE_RELEASE` is `2.2.572` and `STABLE_RELEASE` is `2.2.573`') !== false
            && $verify9bcRaw !== '' && strpos($verify9bcRaw, 'PLANNER CANONICAL ASSIGNMENT 9BC CONTRACT OK') !== false
            && $runtime9bcRaw !== '' && strpos($runtime9bcRaw, 'PLANNER CANONICAL ASSIGNMENT 9BC RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "PLANNER_CANONICAL_ASSIGNMENT_9BC_CONTRACT = 'fieldflow-planner-canonical-assignment-catalog-v1'") !== false
            && strpos($releaseGateRaw, 'checkPlannerCanonicalAssignment9BCContract') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['planner_canonical_assignment_9bc_ok']) {
            $result['error'] = 'Planner Canonical Assignment Catalog 9BC is not fully wired into the release.';
            return $result;
        }

        // 2.2.574+ Form Visit Memory & Fronts Continuity 9BD.
        $daily9bdRaw = self::entryContents($entries, 'fieldflow/src/Forms/DailySubmissionService.php');
        $forms9bdRaw = self::entryContents($entries, 'fieldflow/src/Forms/Forms.php');
        $payload9bdRaw = self::entryContents($entries, 'fieldflow/src/Mobile/FormPayloadService.php');
        $cardex9bdRaw = self::entryContents($entries, 'fieldflow/src/Forms/ProductCardex.php');
        $mobileSubmit9bdRaw = self::entryContents($entries, 'fieldflow/src/Mobile/SubmissionService.php');
        $phase69bdRaw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $manager9bdRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $doc9bdRaw = self::entryContents($entries, 'fieldflow/PHASE-9BD-FORM-VISIT-MEMORY-FRONTS-CONTINUITY.md');
        $verify9bdRaw = self::entryContents($entries, 'fieldflow/tools/verify-form-visit-memory-fronts-9bd.php');
        $runtime9bdRaw = self::entryContents($entries, 'fieldflow/tools/test-form-visit-memory-fronts-9bd.php');
        $result['form_visit_memory_fronts_9bd_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.574', '>=')
            && $daily9bdRaw !== '' && strpos($daily9bdRaw, 'find_previous_operational') !== false
            && strpos($daily9bdRaw, 'SubmissionStatusPolicy::operationalSql()') !== false
            && strpos($daily9bdRaw, 'visit_memory_question_eligible') !== false
            && strpos($daily9bdRaw, 'project_id > 0') !== false
            && $forms9bdRaw !== '' && strpos($forms9bdRaw, 'DailySubmissionService::find_previous_operational') !== false
            && strpos($forms9bdRaw, 'ProductCardex::normalise_fronts_submission_rows') !== false
            && strpos($forms9bdRaw, "'history_mode' => 'inline'") !== false
            && strpos($forms9bdRaw, 'form_record_history_inline') !== false
            && $payload9bdRaw !== '' && strpos($payload9bdRaw, 'DailySubmissionService::find_previous_operational') !== false
            && strpos($payload9bdRaw, 'visit_memory_question_eligible') !== false
            && $cardex9bdRaw !== '' && strpos($cardex9bdRaw, 'normalise_fronts_submission_rows') !== false
            && strpos($cardex9bdRaw, "'before'") !== false && strpos($cardex9bdRaw, "'after'") !== false && strpos($cardex9bdRaw, "'qty'") !== false
            && $mobileSubmit9bdRaw !== '' && strpos($mobileSubmit9bdRaw, 'ProductCardex::normalise_fronts_submission_rows') !== false
            && $phase69bdRaw !== '' && strpos($phase69bdRaw, "SOURCE_RELEASE = '2.2.573'") !== false
            && strpos($phase69bdRaw, "STABLE_RELEASE = '2.2.574'") !== false
            && $manager9bdRaw !== '' && strpos($manager9bdRaw, "packageResult['form_visit_memory_fronts_9bd_ok']") !== false
            && $doc9bdRaw !== '' && strpos($doc9bdRaw, 'fieldflow-form-visit-memory-fronts-continuity-v1') !== false
            && strpos($doc9bdRaw, '`SOURCE_RELEASE` is `2.2.573` and `STABLE_RELEASE` is `2.2.574`') !== false
            && $verify9bdRaw !== '' && strpos($verify9bdRaw, 'FORM VISIT MEMORY FRONTS 9BD CONTRACT OK') !== false
            && $runtime9bdRaw !== '' && strpos($runtime9bdRaw, 'FORM VISIT MEMORY FRONTS 9BD RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "FORM_VISIT_MEMORY_FRONTS_9BD_CONTRACT = 'fieldflow-form-visit-memory-fronts-continuity-v1'") !== false
            && strpos($releaseGateRaw, 'checkFormVisitMemoryFronts9BDContract') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['form_visit_memory_fronts_9bd_ok']) {
            $result['error'] = 'Form Visit Memory & Fronts Continuity 9BD is not fully wired into the release.';
            return $result;
        }


        // 2.2.575+ Planner Free Warning Continuity 9BE.
        $optimizer9beRaw = self::entryContents($entries, 'fieldflow/src/Services/RouteOptimizationService.php');
        $planner9beRaw = self::entryContents($entries, 'fieldflow/src/Front/SelfRoutePlannerShortcodeRenderer.php');
        $mobile9beRaw = self::entryContents($entries, 'fieldflow/src/Mobile/Controller.php');
        $phase69beRaw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $manager9beRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $doc9beRaw = self::entryContents($entries, 'fieldflow/PHASE-9BE-PLANNER-FREE-WARNING-CONTINUITY.md');
        $verify9beRaw = self::entryContents($entries, 'fieldflow/tools/verify-planner-free-warning-9be.php');
        $runtime9beRaw = self::entryContents($entries, 'fieldflow/tools/test-planner-free-warning-9be.php');
        $result['planner_free_warning_9be_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.575', '>=')
            && $optimizer9beRaw !== '' && strpos($optimizer9beRaw, "PLANNER_WARNING_CONTRACT = 'fieldflow-planner-free-warning-continuity-v1'") !== false
            && strpos($optimizer9beRaw, "'planner_mode'") !== false
            && strpos($optimizer9beRaw, "\$lifecycleSql = \$plannerMode ? '' :") !== false
            && strpos($optimizer9beRaw, 'plannerWarnings') !== false
            && strpos($optimizer9beRaw, "'planning_warnings'") !== false
            && strpos($optimizer9beRaw, 'Sobreposição:') !== false
            && strpos($optimizer9beRaw, 'Periodicidade:') !== false
            && $planner9beRaw !== '' && strpos($planner9beRaw, 'fieldflow-planner-free-warning-continuity-v1') !== false
            && strpos($planner9beRaw, 'planner_mode:1') !== false
            && strpos($planner9beRaw, "planning_source:'self_planner'") !== false
            && strpos($planner9beRaw, 'planningWarningText') !== false
            && $mobile9beRaw !== '' && strpos($mobile9beRaw, "'planner_mode' => 1") !== false
            && strpos($mobile9beRaw, "'planning_source' => 'self_planner'") !== false
            && strpos($mobile9beRaw, "'planning_warnings'") !== false
            && strpos($mobile9beRaw, "'warnings' => array_values(\$planning_warnings)") !== false
            && $phase69beRaw !== '' && strpos($phase69beRaw, "SOURCE_RELEASE = '2.2.574'") !== false
            && strpos($phase69beRaw, "STABLE_RELEASE = '2.2.575'") !== false
            && $manager9beRaw !== '' && strpos($manager9beRaw, "packageResult['planner_free_warning_9be_ok']") !== false
            && $doc9beRaw !== '' && strpos($doc9beRaw, 'fieldflow-planner-free-warning-continuity-v1') !== false
            && strpos($doc9beRaw, '`SOURCE_RELEASE` is `2.2.574` and `STABLE_RELEASE` is `2.2.575`') !== false
            && $verify9beRaw !== '' && strpos($verify9beRaw, 'PLANNER FREE WARNING 9BE CONTRACT OK') !== false
            && $runtime9beRaw !== '' && strpos($runtime9beRaw, 'PLANNER FREE WARNING 9BE RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "PLANNER_FREE_WARNING_9BE_CONTRACT = 'fieldflow-planner-free-warning-continuity-v1'") !== false
            && strpos($releaseGateRaw, 'checkPlannerFreeWarning9BEContract') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['planner_free_warning_9be_ok']) {
            $result['error'] = 'Planner Free Warning Continuity 9BE is not fully wired into the release.';
            return $result;
        }

        // 2.2.576+ Visit Exception & Justified Non Visit Continuity 9BF.
        $visitException9bfRaw = self::entryContents($entries, 'fieldflow/src/Services/VisitExceptionService.php');
        $periodicity9bfRaw = self::entryContents($entries, 'fieldflow/src/Services/PeriodicityProgressService.php');
        $routes9bfRaw = self::entryContents($entries, 'fieldflow/src/Rest/RoutesController.php');
        $shortcodes9bfRaw = self::entryContents($entries, 'fieldflow/src/Shortcodes.php');
        $phase69bfRaw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $manager9bfRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $doc9bfRaw = self::entryContents($entries, 'fieldflow/PHASE-9BF-VISIT-EXCEPTION-JUSTIFIED-NONVISIT.md');
        $verify9bfRaw = self::entryContents($entries, 'fieldflow/tools/verify-visit-exception-justified-nonvisit-9bf.php');
        $runtime9bfRaw = self::entryContents($entries, 'fieldflow/tools/test-visit-exception-justified-nonvisit-9bf.php');
        $result['visit_exception_justified_nonvisit_9bf_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.576', '>=')
            && $visitException9bfRaw !== '' && strpos($visitException9bfRaw, "CONTRACT = 'fieldflow-visit-exception-justified-nonvisit-v1'") !== false
            && strpos($visitException9bfRaw, "OPTION = 'fieldflow_visit_exceptions_v1'") !== false
            && strpos($visitException9bfRaw, "AuditLog::record('visit_exception.upsert'") !== false
            && strpos($visitException9bfRaw, "AuditLog::record('visit_exception.clear'") !== false
            && $periodicity9bfRaw !== '' && strpos($periodicity9bfRaw, "JUSTIFIED_EXCEPTION_CONTRACT = 'fieldflow-visit-exception-justified-nonvisit-v1'") !== false
            && strpos($periodicity9bfRaw, 'rs.fail_reason') !== false
            && strpos($periodicity9bfRaw, "'remaining_after_justification'") !== false
            && strpos($periodicity9bfRaw, "'justified_stores'") !== false
            && strpos($periodicity9bfRaw, "'visits_justified'") !== false
            && $routes9bfRaw !== '' && strpos($routes9bfRaw, "'/visit-exceptions'") !== false
            && strpos($routes9bfRaw, 'save_visit_exception') !== false
            && strpos($routes9bfRaw, 'clear_visit_exception') !== false
            && $shortcodes9bfRaw !== '' && strpos($shortcodes9bfRaw, 'Justificar não visita') !== false
            && strpos($shortcodes9bfRaw, 'data-periodicity-justify') !== false
            && strpos($shortcodes9bfRaw, 'rp-periodicity-justified') !== false
            && $phase69bfRaw !== '' && strpos($phase69bfRaw, "SOURCE_RELEASE = '2.2.575'") !== false
            && strpos($phase69bfRaw, "STABLE_RELEASE = '2.2.576'") !== false
            && $manager9bfRaw !== '' && strpos($manager9bfRaw, "packageResult['visit_exception_justified_nonvisit_9bf_ok']") !== false
            && $doc9bfRaw !== '' && strpos($doc9bfRaw, 'fieldflow-visit-exception-justified-nonvisit-v1') !== false
            && strpos($doc9bfRaw, '`SOURCE_RELEASE` is `2.2.575` and `STABLE_RELEASE` is `2.2.576`') !== false
            && $verify9bfRaw !== '' && strpos($verify9bfRaw, 'VISIT EXCEPTION JUSTIFIED NONVISIT 9BF CONTRACT OK') !== false
            && $runtime9bfRaw !== '' && strpos($runtime9bfRaw, 'VISIT EXCEPTION JUSTIFIED NONVISIT 9BF RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "VISIT_EXCEPTION_JUSTIFIED_NONVISIT_9BF_CONTRACT = 'fieldflow-visit-exception-justified-nonvisit-v1'") !== false
            && strpos($releaseGateRaw, 'checkVisitExceptionJustifiedNonVisit9BFContract') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['visit_exception_justified_nonvisit_9bf_ok']) {
            $result['error'] = 'Visit Exception & Justified Non Visit Continuity 9BF is not fully wired into the release.';
            return $result;
        }


        // 2.2.578+ Open Day Planning & Visit Completion Sync 9BG.
        $planner9bgRaw = self::entryContents($entries, 'fieldflow/src/Front/SelfRoutePlannerShortcodeRenderer.php');
        $planSync9bgRaw = self::entryContents($entries, 'fieldflow/src/Services/RoutePlanSyncService.php');
        $completion9bgRaw = self::entryContents($entries, 'fieldflow/src/Services/FormVisitCompletionService.php');
        $mobile9bgRaw = self::entryContents($entries, 'fieldflow/src/Mobile/Controller.php');
        $phase69bgRaw = self::entryContents($entries, 'fieldflow/src/Cloud/Phase6StableRelease.php');
        $manager9bgRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReleaseManager.php');
        $doc9bgRaw = self::entryContents($entries, 'fieldflow/PHASE-9BG-OPEN-DAY-PLANNING-VISIT-COMPLETION-SYNC.md');
        $verify9bgRaw = self::entryContents($entries, 'fieldflow/tools/verify-open-day-planning-visit-completion-9bg.php');
        $runtime9bgRaw = self::entryContents($entries, 'fieldflow/tools/test-open-day-planning-visit-completion-9bg.php');
        $result['open_day_planning_visit_completion_9bg_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.578', '>=')
            && $planner9bgRaw !== '' && strpos($planner9bgRaw, 'fieldflow-open-day-planning-visit-completion-sync-v1') !== false
            && strpos($planner9bgRaw, 'const operationalToday=') !== false
            && strpos($planner9bgRaw, 'function dayPlanningLocked(date)') !== false
            && strpos($planner9bgRaw, 'function canonicalStopStatus(store)') !== false
            && strpos($planner9bgRaw, 'function stopIsProtected(store)') !== false
            && strpos($planner9bgRaw, 'route_stop_canonical_status') !== false
            && strpos($planner9bgRaw, "document.addEventListener('fieldflow:visit-completed'") !== false
            && strpos($planner9bgRaw, 'A rota deste dia já foi iniciada ou fechada. Escolhe outro dia para planear novas visitas.') === false
            && $planSync9bgRaw !== '' && strpos($planSync9bgRaw, 'route_plan_operational_stop_protected') !== false
            && strpos($planSync9bgRaw, 'has_operational_state') !== false
            && $completion9bgRaw !== '' && strpos($completion9bgRaw, 'RouteProgressService::sync') !== false
            && $mobile9bgRaw !== '' && strpos($mobile9bgRaw, 'RoutePlanSyncService::sync') !== false
            && strpos($mobile9bgRaw, 'RouteProgressService::sync') !== false
            && $phase69bgRaw !== '' && strpos($phase69bgRaw, "SOURCE_RELEASE = '2.2.577'") !== false
            && strpos($phase69bgRaw, "STABLE_RELEASE = '2.2.578'") !== false
            && $manager9bgRaw !== '' && strpos($manager9bgRaw, "packageResult['open_day_planning_visit_completion_9bg_ok']") !== false
            && $doc9bgRaw !== '' && strpos($doc9bgRaw, 'fieldflow-open-day-planning-visit-completion-sync-v1') !== false
            && strpos($doc9bgRaw, '`SOURCE_RELEASE` is `2.2.577` and `STABLE_RELEASE` is `2.2.578`') !== false
            && $verify9bgRaw !== '' && strpos($verify9bgRaw, 'OPEN DAY PLANNING VISIT COMPLETION 9BG CONTRACT OK') !== false
            && $runtime9bgRaw !== '' && strpos($runtime9bgRaw, 'OPEN DAY PLANNING VISIT COMPLETION 9BG RUNTIME OK') !== false
            && $releaseGateRaw !== '' && strpos($releaseGateRaw, "OPEN_DAY_PLANNING_VISIT_COMPLETION_9BG_CONTRACT = 'fieldflow-open-day-planning-visit-completion-sync-v1'") !== false
            && strpos($releaseGateRaw, 'checkOpenDayPlanningVisitCompletion9BGContract') !== false
            && strpos($migrationsOutboxRaw, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false
            && strpos($submissionPipelineRaw, "public const BUILD = '2.2.485'") !== false;
        if (!$result['open_day_planning_visit_completion_9bg_ok']) {
            $result['error'] = 'Open Day Planning & Visit Completion Sync 9BG is not fully wired into the release.';
            return $result;
        }

                $result['typed_offline_core_ok'] = $frontendRaw !== ''
            && strpos($frontendRaw, 'fieldflow-typed-offline-core-v1') !== false
            && strpos($frontendRaw, 'fieldFlowConnectivityApi') !== false
            && strpos($frontendRaw, 'fieldFlowSyncLockApi') !== false
            && strpos($frontendRaw, 'fieldFlowOutboxApi') !== false
            && $runtimeRaw !== ''
            && strpos($runtimeRaw, 'typedOfflineCore()') !== false
            && strpos($runtimeRaw, 'core.connectivity.isUsable') !== false
            && strpos($runtimeRaw, 'core.syncLock') !== false
            && strpos($runtimeRaw, 'core.outbox') !== false;
        if (!$result['typed_offline_core_ok']) {
            $result['error'] = 'O Typed Offline Core não está integralmente compilado e ligado ao runtime offline da release.';
            return $result;
        }

        // Typed Draft & Media Core must be compiled and actually consumed by
        // FormRenderer. The IndexedDB database name/version remain compatible
        // with existing field devices, and the legacy implementation remains a fallback.
        $formRendererRaw = self::entryContents($entries, 'fieldflow/src/Forms/FormRenderer.php');
        $draftVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-typed-draft-media-core.php');
        $result['typed_draft_media_core_ok'] = $frontendRaw !== ''
            && strpos($frontendRaw, 'fieldflow-typed-draft-media-core-v1') !== false
            && strpos($frontendRaw, 'fieldflow-form-drafts') !== false
            && strpos($frontendRaw, 'fieldFlowTypedDraftCoreApi') !== false
            && strpos($frontendRaw, 'cleanupExpiredSnapshots') !== false
            && $formRendererRaw !== ''
            && strpos($formRendererRaw, 'fieldflow-typed-draft-media-core-v1') !== false
            && strpos($formRendererRaw, 'rpTypedDraftPersistence') !== false
            && strpos($formRendererRaw, 'persistence.storeSnapshot') !== false
            && strpos($formRendererRaw, 'persistence.storeFiles') !== false
            && strpos($formRendererRaw, 'core.state.contextValid') !== false
            && strpos($formRendererRaw, 'core.state.newestLocal') !== false
            && $draftVerifierRaw !== ''
            && strpos($draftVerifierRaw, 'fieldflow-typed-draft-media-core-v1') !== false;
        if (!$result['typed_draft_media_core_ok']) {
            $result['error'] = 'O Typed Draft & Media Core não está integralmente compilado e ligado ao FormRenderer da release.';
            return $result;
        }

        // Typed Form State + Conditional Engine must be present in the compiled
        // frontend bundle and consumed by both browser runtimes. PHP keeps the
        // same canonical contract and a standalone fixture verifier certifies
        // parity before Release Manager can publish the package.
        $formsRuntimeRaw = self::entryContents($entries, 'fieldflow/assets/forms-runtime-v3.js');
        $conditionalFlowRaw = self::entryContents($entries, 'fieldflow/src/Forms/ConditionalFlow.php');
        $typedFormVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-typed-form-conditional-core.php');
        $typedFormFixturesRaw = self::entryContents($entries, 'fieldflow/frontend/tests/conditional-flow-fixtures.json');
        $result['typed_form_conditional_core_ok'] = $frontendRaw !== ''
            && strpos($frontendRaw, 'fieldflow-typed-form-state-conditional-core-v1') !== false
            && strpos($frontendRaw, 'fieldFlowTypedFormCoreApi') !== false
            && strpos($frontendRaw, 'fieldFlowConditionalEvaluate') !== false
            && $formRendererRaw !== ''
            && strpos($formRendererRaw, 'rpTypedFormCore') !== false
            && strpos($formRendererRaw, 'typedCore.conditions.evaluate') !== false
            && $formsRuntimeRaw !== ''
            && strpos($formsRuntimeRaw, 'typedFormCore()') !== false
            && strpos($formsRuntimeRaw, 'core.conditions.evaluate') !== false
            && $conditionalFlowRaw !== ''
            && strpos($conditionalFlowRaw, "public const VERSION = 3") !== false
            && strpos($conditionalFlowRaw, "public const CONTRACT = 'fieldflow-typed-form-state-conditional-core-v1'") !== false
            && $typedFormVerifierRaw !== ''
            && strpos($typedFormVerifierRaw, 'TYPED FORM STATE CONDITIONAL CORE CONTRACT OK') !== false
            && strpos($typedFormVerifierRaw, 'php_typescript_parity=ok') !== false
            && $typedFormFixturesRaw !== ''
            && strpos($typedFormFixturesRaw, 'no keeps order suggestion hidden') !== false;
        if (!$result['typed_form_conditional_core_ok']) {
            $result['error'] = 'O Typed Form State + Conditional Engine não está integralmente compilado e ligado à release.';
            return $result;
        }

        // Typed Submission & Validation Core aligns generic active-question,
        // required and persistence semantics across browser, web PHP and mobile PHP.
        $submissionValidationRaw = self::entryContents($entries, 'fieldflow/src/Forms/SubmissionValidation.php');
        $formsRaw = self::entryContents($entries, 'fieldflow/src/Forms/Forms.php');
        $mobileSubmissionRaw = self::entryContents($entries, 'fieldflow/src/Mobile/SubmissionService.php');
        $typedSubmissionVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-typed-submission-validation-core.php');
        $typedSubmissionFixturesRaw = self::entryContents($entries, 'fieldflow/frontend/tests/submission-validation-fixtures.json');
        $result['typed_submission_validation_core_ok'] = $frontendRaw !== ''
            && strpos($frontendRaw, 'fieldflow-typed-submission-validation-core-v1') !== false
            && strpos($frontendRaw, 'fieldFlowTypedSubmissionCoreApi') !== false
            && strpos($frontendRaw, 'fieldFlowSubmissionPrepare') !== false
            && $formRendererRaw !== ''
            && strpos($formRendererRaw, 'rpTypedSubmissionCore') !== false
            && strpos($formRendererRaw, 'core.validation.prepare') !== false
            && $submissionValidationRaw !== ''
            && strpos($submissionValidationRaw, "public const CONTRACT = 'fieldflow-typed-submission-validation-core-v1'") !== false
            && strpos($submissionValidationRaw, 'required_missing') !== false
            && strpos($submissionValidationRaw, 'should_persist') !== false
            && $formsRaw !== ''
            && strpos($formsRaw, 'SubmissionValidation::required_missing') !== false
            && strpos($formsRaw, 'SubmissionValidation::should_persist') !== false
            && $mobileSubmissionRaw !== ''
            && strpos($mobileSubmissionRaw, 'SubmissionValidation::required_missing') !== false
            && strpos($mobileSubmissionRaw, 'SubmissionValidation::should_persist') !== false
            && $typedSubmissionVerifierRaw !== ''
            && strpos($typedSubmissionVerifierRaw, 'TYPED SUBMISSION VALIDATION CORE CONTRACT OK') !== false
            && strpos($typedSubmissionVerifierRaw, 'php_typescript_parity=ok') !== false
            && $typedSubmissionFixturesRaw !== ''
            && strpos($typedSubmissionFixturesRaw, 'hidden order suggestion never enters active payload') !== false;
        if (!$result['typed_submission_validation_core_ok']) {
            $result['error'] = 'O Typed Submission & Validation Core não está integralmente compilado e ligado à release.';
            return $result;
        }

        // Typed Specialized Validation Core aligns structured fields whose
        // validity cannot be reduced to generic empty/non-empty semantics.
        $specializedValidationRaw = self::entryContents($entries, 'fieldflow/src/Forms/SpecializedValidation.php');
        $typedSpecializedVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-typed-specialized-validation-core.php');
        $typedSpecializedFixturesRaw = self::entryContents($entries, 'fieldflow/frontend/tests/specialized-validation-fixtures.json');
        $result['typed_specialized_validation_core_ok'] = $frontendRaw !== ''
            && strpos($frontendRaw, 'fieldflow-typed-specialized-validation-core-v1') !== false
            && strpos($frontendRaw, 'fieldFlowTypedSpecializedValidationCoreApi') !== false
            && strpos($frontendRaw, 'fieldFlowSpecializedShareSpace') !== false
            && $formRendererRaw !== ''
            && strpos($formRendererRaw, 'rpTypedSpecializedValidationCore') !== false
            && strpos($formRendererRaw, 'firstTypedSpecializedInvalid') !== false
            && strpos($formRendererRaw, 'core.validation.shareSpace') !== false
            && strpos($formRendererRaw, 'core.validation.categorized') !== false
            && $specializedValidationRaw !== ''
            && strpos($specializedValidationRaw, "public const CONTRACT = 'fieldflow-typed-specialized-validation-core-v1'") !== false
            && strpos($specializedValidationRaw, 'validate_schema') !== false
            && strpos($specializedValidationRaw, 'share_space_state') !== false
            && $formsRaw !== ''
            && strpos($formsRaw, 'SpecializedValidation::validate_schema') !== false
            && $mobileSubmissionRaw !== ''
            && strpos($mobileSubmissionRaw, 'SpecializedValidation::validate_schema') !== false
            && $typedSpecializedVerifierRaw !== ''
            && strpos($typedSpecializedVerifierRaw, 'TYPED SPECIALIZED VALIDATION CORE CONTRACT OK') !== false
            && strpos($typedSpecializedVerifierRaw, 'share_space_partial_mobile_guard=ok') !== false
            && $typedSpecializedFixturesRaw !== ''
            && strpos($typedSpecializedFixturesRaw, 'required matrix rejects partial rows') !== false;
        if (!$result['typed_specialized_validation_core_ok']) {
            $result['error'] = 'O Typed Specialized Validation Core não está integralmente compilado e ligado à release.';
            return $result;
        }

        // Submission Application Service centralizes the durable application
        // path for render/web/mobile without absorbing secondary side effects.
        $applicationServiceRaw = self::entryContents($entries, 'fieldflow/src/Forms/SubmissionApplicationService.php');
        $applicationVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-submission-application-service.php');
        $loaderRaw = self::entryContents($entries, 'fieldflow/src/Support/Loader.php');
        $applicationDocRaw = self::entryContents($entries, 'fieldflow/PHASE-7ZY-SUBMISSION-APPLICATION-SERVICE.md');
        $result['submission_application_service_ok'] = $applicationServiceRaw !== ''
            && strpos($applicationServiceRaw, "public const CONTRACT = 'fieldflow-submission-application-service-v1'") !== false
            && strpos($applicationServiceRaw, 'public static function buildSchema') !== false
            && strpos($applicationServiceRaw, 'public static function persist') !== false
            && strpos($applicationServiceRaw, 'public static function buildPipelineJob') !== false
            && strpos($applicationServiceRaw, 'commit_after_persist') !== false
            && $formsRaw !== ''
            && strpos($formsRaw, 'SubmissionApplicationService::buildSchema($form, $context_for_questions)') !== false
            && strpos($formsRaw, 'SubmissionApplicationService::buildSchema($form, $submit_context)') !== false
            && strpos($formsRaw, 'SubmissionApplicationService::persist([') !== false
            && strpos($formsRaw, 'SubmissionApplicationService::buildPipelineJob') !== false
            && $mobileSubmissionRaw !== ''
            && strpos($mobileSubmissionRaw, 'use RoutesPro\\Forms\\SubmissionApplicationService') !== false
            && strpos($mobileSubmissionRaw, 'SubmissionApplicationService::persist([') !== false
            && strpos($mobileSubmissionRaw, "'channel' => 'mobile'") !== false
            && strpos($mobileSubmissionRaw, 'commit_after_persist') !== false
            && strpos($mobileSubmissionRaw, 'SubmissionApplicationService::buildPipelineJob') !== false
            && $loaderRaw !== ''
            && strpos($loaderRaw, 'src/Forms/SubmissionApplicationService.php') !== false
            && $applicationVerifierRaw !== ''
            && strpos($applicationVerifierRaw, 'SUBMISSION APPLICATION SERVICE CONTRACT OK') !== false
            && strpos($applicationVerifierRaw, 'web_mobile_durable_persistence=shared') !== false
            && strpos($applicationVerifierRaw, 'mobile_legacy_transaction_semantics=preserved') !== false
            && $applicationDocRaw !== ''
            && strpos($applicationDocRaw, 'fieldflow-submission-application-service-v1') !== false;
        if (!$result['submission_application_service_ok']) {
            $result['error'] = 'O Submission Application Service não está integralmente ligado aos caminhos web e mobile da release.';
            return $result;
        }

        // Submission side effects must be isolated after durable COMMIT so one
        // integration failure cannot replay completed lanes or invalidate field data.
        $sideEffectsRaw = self::entryContents($entries, 'fieldflow/src/Forms/SubmissionSideEffectDispatcher.php');
        $sideEffectsVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-submission-side-effects.php');
        $sideEffectsDocRaw = self::entryContents($entries, 'fieldflow/PHASE-7ZZ-SUBMISSION-EVENTS-SIDE-EFFECTS.md');
        $eventBusRaw = self::entryContents($entries, 'fieldflow/src/Core/EventBus.php');
        $automationsRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReportAutomations.php');
        $performanceRaw = self::entryContents($entries, 'fieldflow/src/Performance/Module.php');
        $queueSettingsRaw = self::entryContents($entries, 'fieldflow/src/Operations/Queue/QueueSettings.php');
        $processMonitorRaw = self::entryContents($entries, 'fieldflow/src/Operations/ProcessMonitor.php');
        $pipelineRaw = self::entryContents($entries, 'fieldflow/src/Forms/SubmissionPipeline.php');
        $result['submission_side_effects_ok'] = $sideEffectsRaw !== ''
            && strpos($sideEffectsRaw, "public const CONTRACT = 'fieldflow-submission-side-effects-v1'") !== false
            && strpos($sideEffectsRaw, "public const RETRY_HOOK = 'fieldflow_submission_side_effect_retry'") !== false
            && strpos($sideEffectsRaw, "'order_suggestion_mail'") !== false
            && strpos($sideEffectsRaw, "'submission_core_event'") !== false
            && strpos($sideEffectsRaw, "'submission_public_hook'") !== false
            && strpos($sideEffectsRaw, 'per_revision_per_lane') !== false
            && $formsRaw !== ''
            && strpos($formsRaw, 'SubmissionSideEffectDispatcher::registerHooks()') !== false
            && strpos($formsRaw, 'SubmissionSideEffectDispatcher::dispatchFromSubmission(') !== false
            && $mobileSubmissionRaw !== ''
            && strpos($mobileSubmissionRaw, 'use RoutesPro\\Forms\\SubmissionSideEffectDispatcher') !== false
            && strpos($mobileSubmissionRaw, 'SubmissionSideEffectDispatcher::dispatchFromSubmission(') !== false
            && strpos($mobileSubmissionRaw, 'observe_media_non_blocking') !== false
            && $eventBusRaw !== ''
            && strpos($eventBusRaw, "add_action('fieldflow_form_submitted'") === false
            && $automationsRaw !== ''
            && strpos($automationsRaw, "add_action('fieldflow_form_submitted'") === false
            && $performanceRaw !== ''
            && strpos($performanceRaw, "add_action('fieldflow_form_submitted'") === false
            && $queueSettingsRaw !== ''
            && strpos($queueSettingsRaw, 'fieldflow_submission_side_effect_retry') !== false
            && $processMonitorRaw !== ''
            && strpos($processMonitorRaw, 'submission_side_effect_retry') !== false
            && $pipelineRaw !== ''
            && strpos($pipelineRaw, "PIPELINE_VERSION = '1.1.0'") !== false
            && strpos($pipelineRaw, 'public const BUILD =') !== false
            && $loaderRaw !== ''
            && strpos($loaderRaw, 'src/Forms/SubmissionSideEffectDispatcher.php') !== false
            && $sideEffectsVerifierRaw !== ''
            && strpos($sideEffectsVerifierRaw, 'SUBMISSION SIDE EFFECTS CONTRACT OK') !== false
            && strpos($sideEffectsVerifierRaw, 'retry_scope=per_lane') !== false
            && $sideEffectsDocRaw !== ''
            && strpos($sideEffectsDocRaw, 'fieldflow-submission-side-effects-v1') !== false;
        if (!$result['submission_side_effects_ok']) {
            $result['error'] = 'O dispatcher isolado de efeitos pós-submissão não está integralmente ligado à release.';
            return $result;
        }

        // 2.2.579+ latest eligible visit, with media/order isolation in all form paths.
        $history9bhRequirements = [
            'src/Forms/DailySubmissionService.php'=>['include_same_day','exclude_submission_id','visit_memory_prefill','new_visit_prefill'],
            'src/Forms/Forms.php'=>['DailySubmissionService::visit_memory_prefill','DailySubmissionService::new_visit_prefill','$history_source = $previous_submission;'],
            'src/Mobile/FormPayloadService.php'=>['DailySubmissionService::visit_memory_prefill','DailySubmissionService::new_visit_prefill','$historySource = $previousSubmission;'],
            'src/Shortcodes.php'=>['embeddedFormSequence','requestId!==embeddedFormSequence'],
            'src/Cloud/Phase6StableRelease.php'=>["SOURCE_RELEASE = '2.2.578'","STABLE_RELEASE = '2.2.579'"],
            'src/Support/ReleaseGate.php'=>['LATEST_VISIT_HISTORY_ISOLATION_9BH_CONTRACT', 'checkLatestVisitHistoryIsolation9BHContract'],
            'src/Admin/ReleaseManager.php'=>["packageResult['latest_visit_history_isolation_9bh_ok']"],
            'PHASE-9BH-LATEST-VISIT-HISTORY-ISOLATION.md'=>['fieldflow-latest-visit-history-isolation-v1'],
            'tools/verify-latest-visit-history-isolation-9bh.php'=>['LATEST VISIT HISTORY ISOLATION 9BH CONTRACT OK'],
            'tools/test-latest-visit-history-isolation-9bh.php'=>['LATEST VISIT HISTORY ISOLATION 9BH RUNTIME OK'],
            'tools/test-quick-report-selection-9bh.mjs'=>['QUICK REPORT SELECTION 9BH RUNTIME OK'],
        ];
        $result['latest_visit_history_isolation_9bh_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.579', '>=');
        foreach ($history9bhRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/' . $relative);
            foreach ($markers as $marker) if (strpos($raw, $marker) === false) $result['latest_visit_history_isolation_9bh_ok'] = false;
        }
        if (!$result['latest_visit_history_isolation_9bh_ok']) {
            $result['error'] = 'Latest Visit History & Media/Order Isolation 9BH is incomplete.';
            return $result;
        }

        $memory9biRequirements = [
            'src/Forms/DailySubmissionService.php'=>['Resolve ownership through submission_id instead.', 'WHERE submission_id=%d ORDER BY id DESC LIMIT 1'],
            'src/Forms/Forms.php'=>['data-rp-previous-visit-revision', '$previous_modal_revision', 'fieldflow-visit-memory-refresh-v1'],
            'src/Forms/FormRenderer.php'=>['fieldflow-visit-memory-refresh-v1','rpClearPreviousVisitModal','memoryRevision','routesproPreviousVisitCleanup'],
            'src/Shortcodes.php'=>['routesproClearPreviousVisitModal','embeddedFormVisitKey'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.579'","public const STABLE_RELEASE = '2.2.580'"],
            'tools/test-visit-memory-refresh-9bi.mjs'=>['VISIT MEMORY REFRESH 9BI RUNTIME OK'],
            'tools/test-visit-memory-version-ownership-9bi.php'=>['VISIT MEMORY VERSION OWNERSHIP 9BI RUNTIME OK'],
            'PHASE-9BI-VISIT-MEMORY-REFRESH.md'=>['fieldflow-visit-memory-refresh-v1'],
        ];
        $result['visit_memory_refresh_9bi_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.580', '>=');
        foreach ($memory9biRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['visit_memory_refresh_9bi_ok'] = false;
        }
        if (!$result['visit_memory_refresh_9bi_ok']) {
            $result['error'] = 'Visit Memory Refresh & Version Ownership 9BI is incomplete.';
            return $result;
        }

        $identity9bjRequirements = [
            'src/Forms/DailySubmissionService.php'=>['canonical_location_sql','_canonical_location_id','Historical FieldFlow builds could persist location_id=route_stop_id'],
            'src/Forms/BindingResolver.php'=>['explicit route stop is the canonical store identity','route_stop_id'],
            'src/Forms/Forms.php'=>['$daily_existing && !$daily_context_conflict','another route/stop/store'],
            'src/Mobile/FormPayloadService.php'=>['$existing && !$context_conflict','never expose another route/stop/store report'],
            'src/Forms/DraftService.php'=>['payload_identity_matches_scope','payload_identity_from_scope',"'retained' => 'identity'"],
            'src/Forms/FormRenderer.php'=>['function draftIdentity()','identity:draftIdentity()','allowRouteRebase'],
            'frontend/src/drafts/draft-core.ts'=>['identity?: FieldFlowDraftIdentity','allowRouteRebase?: boolean','const saved = snapshot.identity'],
            'src/Stores/StoreProfileService.php'=>['DailySubmissionService::canonical_location_sql'],
            'src/Support/ReleaseGate.php'=>['STORE_IDENTITY_AUTHORITY_9BJ_CONTRACT','checkStoreIdentityAuthority9BJContract'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.580'","public const STABLE_RELEASE = '2.2.581'"],
            'tools/verify-store-identity-authority-9bj.php'=>['STORE IDENTITY AUTHORITY 9BJ CONTRACT OK','successor=>=2.2.581'],
            'tools/test-store-identity-authority-9bj.php'=>['STORE IDENTITY AUTHORITY 9BJ RUNTIME OK','canonical_store_detects_numeric_collision'],
            'PHASE-9BJ-STORE-IDENTITY-AUTHORITY.md'=>['fieldflow-store-identity-authority-v1'],
        ];
        $result['store_identity_authority_9bj_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.581', '>=');
        foreach ($identity9bjRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['store_identity_authority_9bj_ok'] = false;
        }
        if (!$result['store_identity_authority_9bj_ok']) {
            $result['error'] = 'Store Identity Authority & Historical Media Isolation 9BJ is incomplete.';
            return $result;
        }

        $routing9bkRequirements = [
            'src/Support/Config.php'=>["'google_routes_preference' => 'TRAFFIC_UNAWARE'", "'google_route_matrix_enabled' => 0", "'google_routes_waypoint_optimization_enabled' => 0", "'google_routes_tolls_enabled' => 0"],
            'src/Support/Plugin.php'=>['applyStaticRoutingCostAuthority', 'fieldflow_static_routing_cost_authority_migrated', 'fieldflow-static-routing-cost-authority-v1'],
            'src/Services/GoogleRoutes.php'=>['STATIC_ROUTING_COST_CONTRACT', 'routeSignature', 'matrix_disabled_by_cost_policy', 'google_routes_tolls_enabled', 'google_routes_waypoint_optimization_enabled'],
            'src/Services/Planning/RoutingMatrixService.php'=>["VERSION = 'fieldflow_routing_matrix_v2'", 'google_route_matrix_enabled', 'Estimador rodoviário interno'],
            'src/Services/RouteMetricsService.php'=>['google_routes_static_snapshot_v4', 'route_signature_unchanged', 'existing_google_snapshot_adopted', 'routing_cost_contract'],
            'src/Services/RouteOptimizationService.php'=>['route_order_or_composition_changed', 'routing_finalization'],
            'src/Admin/Routes.php'=>["['mode' => 'internal']", 'routing_signature', 'google_routes_static_snapshot_v4', 'build_route_schedule_details($dayDate, $dayPoints, $start, $end, false)', 'build_route_schedule_details($date, $points, $start, $end, false)'],
            'src/Admin/CampaignLocations.php'=>["['mode'=>'internal','cache_days'=>0]", 'routing_signature', 'google_routes_static_snapshot_v4', "['routing_matrix_mode'=>'internal']"],
            'src/Admin/Settings.php'=>['Custos Google avançados', 'google_route_matrix_enabled', 'google_routes_tolls_enabled'],
            'src/Rest/IntegrationsController.php'=>['matrix_disabled_by_cost_policy', 'google_route_matrix_enabled'],
            'src/Support/ReleaseGate.php'=>['STATIC_ROUTING_COST_AUTHORITY_9BK_CONTRACT', 'checkStaticRoutingCostAuthority9BKContract'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.581'", "public const STABLE_RELEASE = '2.2.582'"],
            'tools/verify-static-routing-cost-authority-9bk.php'=>['STATIC ROUTING COST AUTHORITY 9BK CONTRACT OK', 'successor=>=2.2.582'],
            'tools/test-static-routing-cost-authority-9bk.php'=>['STATIC ROUTING COST AUTHORITY 9BK RUNTIME OK', 'matrix_disabled_without_http'],
            'PHASE-9BK-STATIC-ROUTING-COST-AUTHORITY.md'=>['fieldflow-static-routing-cost-authority-v1'],
        ];
        $result['static_routing_cost_authority_9bk_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.582', '>=');
        foreach ($routing9bkRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['static_routing_cost_authority_9bk_ok'] = false;
        }
        if (!$result['static_routing_cost_authority_9bk_ok']) {
            $result['error'] = 'Static Routing & Cost Authority 9BK is incomplete.';
            return $result;
        }

        $toll9blRequirements = [
            'src/Support/TollEstimator.php'=>['INTELLIGENCE_CONTRACT','PT-2026-IMT-IP-ASCENDI-v1','estimateFromGoogleRoute',"'A22'=>['rate'=>0.000", "'A41'=>['rate'=>0.085"],
            'src/Support/Config.php'=>["'toll_intelligence_enabled' => 1","'toll_vehicle_class' => 1",'api_rate_google_routes_compute_routes_essentials_per_1000_eur'],
            'src/Support/Plugin.php'=>['applyTollCostIntelligence','fieldflow_toll_cost_intelligence_migrated','fieldflow-toll-cost-intelligence-v1'],
            'src/Services/GoogleRoutes.php'=>['TOLL_COST_INTELLIGENCE_CONTRACT','routes.legs.steps.navigationInstruction.instructions','compute_routes_essentials','compute_routes_enterprise','toll_confidence','toll_tariff_version'],
            'src/Observability/ApiUsageLedger.php'=>['strpos($operationKey, \'compute_routes_\')','api_rate_google_routes_route_per_1000_eur'],
            'src/Admin/Settings.php'=>['Portagens estimadas FieldFlow','toll_intelligence_enabled','toll_vehicle_class'],
            'src/Admin/SecurityObservability.php'=>['Compute Routes Essentials por 1.000','Compute Routes Enterprise por 1.000'],
            'src/Services/RouteMetricsService.php'=>['toll_confidence','toll_tariff_version','toll_cost_intelligence_contract'],
            'src/Admin/Routes.php'=>['Confiança:','toll_confidence','toll_cost_intelligence_contract'],
            'src/Support/ReleaseGate.php'=>['TOLL_COST_INTELLIGENCE_9BL_CONTRACT','checkTollCostIntelligence9BLContract'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.582'","public const STABLE_RELEASE = '2.2.583'"],
            'tools/verify-toll-cost-intelligence-9bl.php'=>['TOLL COST INTELLIGENCE 9BL CONTRACT OK','successor=>=2.2.583'],
            'tools/test-toll-cost-intelligence-9bl.php'=>['TOLL COST INTELLIGENCE 9BL RUNTIME OK','a22_free_zero','essentials_without_google_tolls'],
            'PHASE-9BL-TOLL-COST-INTELLIGENCE.md'=>['fieldflow-toll-cost-intelligence-v1'],
        ];
        $result['toll_cost_intelligence_9bl_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.583', '>=');
        foreach ($toll9blRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['toll_cost_intelligence_9bl_ok'] = false;
        }
        if (!$result['toll_cost_intelligence_9bl_ok']) {
            $result['error'] = 'Toll Cost Intelligence 9BL is incomplete.';
            return $result;
        }

        $places9bmRequirements = [
            'src/Services/PlacesCostAuthority.php'=>["CONTRACT = 'fieldflow-places-geocoding-cost-authority-v1'",'canReuseEndpoint','findReusableLocation','GOOGLE_COORDINATE_CACHE_MAX_DAYS = 30','googleCoordinateWindowValid','recordAvoided'],
            'src/Services/RouteEndpointResolverService.php'=>['PlacesCostAuthority::canReuseEndpoint','endpoint_signature_reuse','force_external_geocode'],
            'src/Services/Maps.php'=>['google_geocode_v3','30 * DAY_IN_SECONDS'],
            'src/Rest/IntegrationsController.php'=>['known_location_reuse','PlacesCostAuthority::findReusableLocation','routespro_geocode_v3_'],
            'src/Support/Config.php'=>["'places_cost_authority_enabled' => 1","'google_places_contact_enrichment_enabled' => 0"],
            'src/Support/Plugin.php'=>['applyPlacesGeocodingCostAuthority','fieldflow_places_geocoding_cost_authority_migrated','fieldflow-places-geocoding-cost-authority-v1'],
            'src/Admin/Settings.php'=>['Places &amp; Geocoding low cost','places_cost_authority_enabled','google_places_contact_enrichment_enabled'],
            'src/Admin/Routes.php'=>['googlePlacesContactEnrichment','if (!googlePlacesContactEnrichment) { cb(null); return; }'],
            'src/Shortcodes.php'=>['googlePlacesContactEnrichment',"addressFields.push('website','formatted_phone_number')"],
            'src/Support/ReleaseGate.php'=>['PLACES_GEOCODING_COST_AUTHORITY_9BM_CONTRACT','checkPlacesGeocodingCostAuthority9BMContract'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.583'","public const STABLE_RELEASE = '2.2.584'"],
            'tools/verify-places-geocoding-cost-authority-9bm.php'=>['PLACES GEOCODING COST AUTHORITY 9BM CONTRACT OK','successor=>=2.2.584'],
            'tools/test-places-geocoding-cost-authority-9bm.php'=>['PLACES GEOCODING COST AUTHORITY 9BM RUNTIME OK','unchanged_endpoint_reuses_coordinates','google_coordinate_window_capped_30_days'],
            'PHASE-9BM-PLACES-GEOCODING-COST-AUTHORITY.md'=>['fieldflow-places-geocoding-cost-authority-v1'],
        ];
        $result['places_geocoding_cost_authority_9bm_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.584', '>=');
        foreach ($places9bmRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['places_geocoding_cost_authority_9bm_ok'] = false;
        }
        if (!$result['places_geocoding_cost_authority_9bm_ok']) {
            $result['error'] = 'Places & Geocoding Cost Authority 9BM is incomplete.';
            return $result;
        }

        $cost9bnRequirements = [
            'src/Services/GoogleCostControlCenter.php'=>["CONTRACT = 'fieldflow-google-cost-control-center-v1'",'authorizeOperation','configurationDrift','recordBlocked','POLÍTICA SAUDÁVEL'],
            'src/Services/GoogleRoutes.php'=>['GoogleCostControlCenter::authorizeOperation','google_cost_control_blocked'],
            'src/Observability/ApiUsageLedger.php'=>["status === 'blocked'","SUM(status='blocked') blocked",'avoided_events'],
            'src/Support/Config.php'=>["'google_cost_control_enabled' => 1","'google_cost_block_matrix' => 1","'google_cost_block_pro' => 1","'google_cost_block_enterprise' => 1"],
            'src/Support/Plugin.php'=>['applyGoogleCostControlCenter','fieldflow_google_cost_control_center_migrated','fieldflow-google-cost-control-center-v1'],
            'src/Admin/SecurityObservability.php'=>['Google Cost Control Center','Bloquear Google Route Matrix','Bloquear Google Routes Pro','Bloquear Google Routes Enterprise'],
            'src/Admin/Settings.php'=>['Google Cost Control Center:','bloqueios financeiros têm prioridade'],
            'src/Support/ReleaseGate.php'=>['GOOGLE_COST_CONTROL_CENTER_9BN_CONTRACT','checkGoogleCostControlCenter9BNContract'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.584'","public const STABLE_RELEASE = '2.2.585'"],
            'tools/verify-google-cost-control-center-9bn.php'=>['GOOGLE COST CONTROL CENTER 9BN CONTRACT OK','successor=>=2.2.585'],
            'tools/test-google-cost-control-center-9bn.php'=>['GOOGLE COST CONTROL CENTER 9BN RUNTIME OK','pro_blocked_before_http','matrix_blocked_before_http'],
            'PHASE-9BN-GOOGLE-COST-CONTROL-CENTER.md'=>['fieldflow-google-cost-control-center-v1'],
        ];
        $result['google_cost_control_center_9bn_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.585', '>=');
        foreach ($cost9bnRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['google_cost_control_center_9bn_ok'] = false;
        }
        if (!$result['google_cost_control_center_9bn_ok']) {
            $result['error'] = 'Google Cost Control Center 9BN is incomplete.';
            return $result;
        }


        $daily9boRequirements = [
            'src/Services/DailyOperationalPriorityService.php'=>["CONTRACT = 'fieldflow-daily-operational-priority-v1'",'rankSignals','external_api_calls','route_mutations'],
            'src/Rest/RoutesController.php'=>["'/my-priorities'",'DailyOperationalPriorityService::forUser'],
            'src/Shortcodes.php'=>['rp-daily-focus','fieldflow-daily-operational-priority-v1','loadDailyFocus','daily-operational-priority'],
            'src/Admin/Assignments.php'=>["'operations' => 'Operação diária'",'DailyOperationalPriorityService::forUser','0 chamadas externas'],
            'src/Support/ReleaseGate.php'=>['DAILY_OPERATIONAL_PRIORITY_9BO_CONTRACT','checkDailyOperationalPriority9BOContract'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.585'","public const STABLE_RELEASE = '2.2.586'"],
            'tools/verify-daily-operational-priority-9bo.php'=>['DAILY OPERATIONAL PRIORITY 9BO CONTRACT OK','successor=>=2.2.586'],
            'tools/test-daily-operational-priority-9bo.php'=>['DAILY OPERATIONAL PRIORITY 9BO RUNTIME OK','active_first','read_only'],
            'PHASE-9BO-DAILY-OPERATIONAL-PRIORITY.md'=>['fieldflow-daily-operational-priority-v1'],
        ];
        $result['daily_operational_priority_9bo_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.586', '>=');
        foreach ($daily9boRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['daily_operational_priority_9bo_ok'] = false;
        }
        if (!$result['daily_operational_priority_9bo_ok']) {
            $result['error'] = 'Daily Operational Priority 9BO is incomplete.';
            return $result;
        }

        $daily9bpRequirements = [
            'src/Services/DailyExecutionExceptionService.php'=>["CONTRACT = 'fieldflow-daily-execution-exception-control-v1'",'classifyRecord','external_api_calls','submission_mutations'],
            'src/Rest/RoutesController.php'=>["'/my-execution'",'DailyExecutionExceptionService::forUser'],
            'src/Shortcodes.php'=>['data-execution-contract="fieldflow-daily-execution-exception-control-v1"','rp-focus-toggle','focusOpenStorageKey','loadDailyExecution','alertas de execução'],
            'src/Admin/Assignments.php'=>['DailyExecutionExceptionService::forUser','Controlo de execução e exceções','Alertas execução'],
            'src/Support/ReleaseGate.php'=>['DAILY_EXECUTION_EXCEPTION_9BP_CONTRACT','checkDailyExecutionException9BPContract'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.586'","public const STABLE_RELEASE = '2.2.587'"],
            'tools/verify-daily-execution-exception-control-9bp.php'=>['DAILY EXECUTION EXCEPTION CONTROL 9BP CONTRACT OK','successor=>=2.2.587'],
            'tools/test-daily-execution-exception-control-9bp.php'=>['DAILY EXECUTION EXCEPTION CONTROL 9BP RUNTIME OK','canonical_state','mobile_alerts'],
            'PHASE-9BP-DAILY-EXECUTION-EXCEPTION-CONTROL.md'=>['fieldflow-daily-execution-exception-control-v1','No database schema'],
        ];
        $result['daily_execution_exception_9bp_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.587', '>=');
        foreach ($daily9bpRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['daily_execution_exception_9bp_ok'] = false;
        }
        if (!$result['daily_execution_exception_9bp_ok']) {
            $result['error'] = 'Daily Execution & Exception Control 9BP is incomplete.';
            return $result;
        }

        $orderMailReopen9bqRequirements = [
            'src/Admin/FormSubmissions.php'=>['persistedOrderSuggestionEvidence','persisted_positive_order_authority','hidden_positive_keys'],
            'src/Admin/ReportAutomations.php'=>['recordOrderSuggestionMailState',"'failed_retry'","'queued_or_sent'",'accepted_by_outbox'],
            'src/Shortcodes.php'=>['Queres mesmo concluir esta visita?','editingExisting'],
            'src/Forms/FormRenderer.php'=>['Queres mesmo concluir esta visita?','_rpVisitCloseConfirmed'],
            'src/Services/VisitResetService.php'=>['reopenToPending','visit_admin_reopen','RouteProgressService::sync($routeId, false, false)'],
            'src/Services/RouteProgressService.php'=>["visit_admin_reopen']['active",'passive reconciliation'],
            'src/Services/FormVisitCompletionService.php'=>['resolved_submission_id','visit_admin_reopen'],
            'src/Rest/RoutesController.php'=>["'/stops/(?P<id>\\d+)/reopen'",'reopen_stop_pending'],
            'src/Admin/Routes.php'=>['Reabrir loja','data-reopen-stop',"api+'stops/'+stopId+'/reopen"],
            'src/Support/ReleaseGate.php'=>['ORDER_MAIL_VISIT_REOPEN_9BQ_CONTRACT','checkOrderMailVisitReopen9BQContract'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.587'","public const STABLE_RELEASE = '2.2.588'","public const STABLE_RELEASE = '2.2.588'"],
            'tools/verify-order-mail-visit-reopen-safety-9bq.php'=>['ORDER MAIL VISIT REOPEN SAFETY 9BQ CONTRACT OK','successor=>=2.2.588'],
            'tools/test-order-mail-visit-reopen-safety-9bq.php'=>['ORDER MAIL VISIT REOPEN SAFETY 9BQ RUNTIME OK','positive_order_authority','reopen_guard','close_confirmation'],
            'PHASE-9BQ-ORDER-MAIL-VISIT-REOPEN-SAFETY.md'=>['fieldflow-order-mail-visit-reopen-safety-v1','No database schema'],
        ];
        $result['order_mail_visit_reopen_9bq_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.588', '>=');
        foreach ($orderMailReopen9bqRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['order_mail_visit_reopen_9bq_ok'] = false;
        }
        if (!$result['order_mail_visit_reopen_9bq_ok']) {
            $result['error'] = 'Order Mail & Visit Reopen Safety 9BQ is incomplete.';
            return $result;
        }

        $certFoundation9brRequirements = [
            'src/Cloud/LicenseTenantFoundation.php'=>['release_integrity_ready', '$foundationCertified = !empty($state[\'certified\']) && $match && $remoteConnectivityReady;', 'certification_requires_revalidation', '(!$match || !$remoteConnectivityReady)'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.588'", "public const STABLE_RELEASE = '2.2.589'", "'stage'=>'6B.1-revalidate'", 'LicenseTenantFoundation::certifyFoundation($actorId)', 'failedChecks'],
            'src/Admin/LicenseTenants.php'=>['failedCheckKeys','Ainda existem pré-condições da 6B.1 por concluir:'],
            'src/Support/ReleaseGate.php'=>['CERTIFICATION_FOUNDATION_CONTINUITY_9BR_CONTRACT','checkCertificationFoundationContinuity9BRContract'],
            'tools/verify-certification-foundation-continuity-9br.php'=>['CERTIFICATION FOUNDATION CONTINUITY 9BR CONTRACT OK','successor=>=2.2.589'],
            'tools/test-certification-foundation-continuity-9br.php'=>['CERTIFICATION FOUNDATION CONTINUITY 9BR RUNTIME OK','foundation_certificate_decoupled','chain_includes_6b1','diagnostic_visibility'],
            'PHASE-9BR-CERTIFICATION-FOUNDATION-CONTINUITY.md'=>['fieldflow-certification-foundation-continuity-v1','No database schema'],
        ];
        $result['certification_foundation_continuity_9br_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.589', '>=');
        foreach ($certFoundation9brRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['certification_foundation_continuity_9br_ok'] = false;
        }
        if (!$result['certification_foundation_continuity_9br_ok']) {
            $result['error'] = 'Certification Foundation Continuity 9BR is incomplete.';
            return $result;
        }

        $executiveAnalytics9bsRequirements = [
            'src/Analytics/MonthlyExecutiveAnalyticsService.php'=>['fieldflow-monthly-executive-analytics-v1','primary_kpis','risk_locations','commercial_signals','mobility_cost_per_visit_eur'],
            'src/Rest/StatsController.php'=>['MonthlyExecutiveAnalyticsService','monthly_executive'],
            'src/Admin/Routes.php'=>['rp-routes-financial-summary','reportingProjection','data-mobility-cost','Custo deslocação'],
            'src/Front/ClientPortalShortcodeRenderer.php'=>['rpcp-monthly-executive','Este mês em 30 segundos','renderMonthlyExecutive','Como funciona esta análise?'],
            'src/Analytics/AnalyticsAIService.php'=>['fieldflow-explainable-analytics-v1','explainability','does_not_do'],
            'src/Analytics/FieldFlowAskService.php'=>['No Analytics Cliente','Como é calculada a confiança?','O FieldFlow altera alguma coisa sozinho?'],
            'src/Shortcodes.php'=>['ffadExplainabilityHtml','Como funciona esta análise?'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.589'", "public const STABLE_RELEASE = '2.2.590'"],
            'tools/verify-executive-analytics-route-financial-visibility-9bs.php'=>['EXECUTIVE ANALYTICS ROUTE FINANCIAL VISIBILITY 9BS CONTRACT OK','successor=>=2.2.590'],
            'tools/test-executive-analytics-route-financial-visibility-9bs.php'=>['EXECUTIVE ANALYTICS ROUTE FINANCIAL VISIBILITY 9BS RUNTIME OK','client_explainability','merchandiser_explainability'],
            'PHASE-9BS-EXECUTIVE-ANALYTICS-ROUTE-FINANCIAL-VISIBILITY.md'=>['fieldflow-executive-analytics-route-financial-visibility-v1','No database schema'],
        ];
        $result['executive_analytics_route_financial_9bs_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.590', '>=');
        foreach ($executiveAnalytics9bsRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['executive_analytics_route_financial_9bs_ok'] = false;
        }
        if (!$result['executive_analytics_route_financial_9bs_ok']) {
            $result['error'] = 'Executive Analytics & Route Financial Visibility 9BS is incomplete.';
            return $result;
        }

        $mobileAnalytics9btRequirements = [
            'src/Front/ClientPortalShortcodeRenderer.php'=>['fieldflow-mobile-analytics-readability-evidence-density-v1','width:100vw!important','data-rp-evidence-store-detail','data-rp-evidence-date-detail','height:50px!important',"content:'Abrir'!important"],
            'src/Shortcodes.php'=>['fieldflow-mobile-analytics-readability-evidence-density-v1','width:100vw!important','ffad-evidence-section','data-ffad-detail','height:50px!important','ffad-performance-focus'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.590'", "public const STABLE_RELEASE = '2.2.591'"],
            'tools/verify-mobile-analytics-readability-evidence-density-9bt.php'=>['MOBILE ANALYTICS READABILITY EVIDENCE DENSITY 9BT CONTRACT OK','successor=>=2.2.591'],
            'tools/test-mobile-analytics-readability-evidence-density-9bt.php'=>['MOBILE ANALYTICS READABILITY EVIDENCE DENSITY 9BT RUNTIME OK','client_evidence_collapsible','operative_evidence_collapsible','ask_height_compact'],
            'PHASE-9BT-MOBILE-ANALYTICS-READABILITY-EVIDENCE-DENSITY.md'=>['fieldflow-mobile-analytics-readability-evidence-density-v1','No database schema'],
        ];
        $result['mobile_analytics_readability_evidence_9bt_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.591', '>=');
        foreach ($mobileAnalytics9btRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['mobile_analytics_readability_evidence_9bt_ok'] = false;
        }
        if (!$result['mobile_analytics_readability_evidence_9bt_ok']) {
            $result['error'] = 'Mobile Analytics Readability & Evidence Density 9BT is incomplete.';
            return $result;
        }

        $clientReports9buRequirements = [
            'src/Front/ClientPortalShortcodeRenderer.php'=>['fieldflow-client-reports-runtime-continuity-v1','function paramsForReports()','async function loadReports()','form-submissions?','owner_user_id','date_from','date_to'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.591'", "public const STABLE_RELEASE = '2.2.592'"],
            'tools/verify-client-reports-runtime-continuity-9bu.php'=>['CLIENT REPORTS RUNTIME CONTINUITY 9BU CONTRACT OK','successor=>=2.2.592'],
            'tools/test-client-reports-runtime-continuity-9bu.php'=>['CLIENT REPORTS RUNTIME CONTINUITY 9BU RUNTIME OK','loader_defined','filter_bridge'],
            'PHASE-9BU-CLIENT-REPORTS-RUNTIME-CONTINUITY.md'=>['fieldflow-client-reports-runtime-continuity-v1','No database schema'],
        ];
        $result['client_reports_runtime_9bu_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.592', '>=');
        foreach ($clientReports9buRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['client_reports_runtime_9bu_ok'] = false;
        }
        if (!$result['client_reports_runtime_9bu_ok']) {
            $result['error'] = 'Client Reports Runtime Continuity 9BU is incomplete.';
            return $result;
        }

        $portalExport9bvRequirements = [
            'src/Front/ClientPortalShortcodeRenderer.php'=>['fieldflow-portal-export-runtime-certification-parity-v1','function exportUrl(format)','function dashboardExportUrl(format)','function routeExportUrl(format)','function safeRefreshExportLinks()','routespro_export_submissions','routespro_export_dashboard','routespro_export_routes','exportNonce','dashboardExportNonce','routeExportNonce'],
            'src/Shortcodes.php'=>['function exportUrl(format)',"p.set('action','routespro_export_dashboard')",'self_mode'],
            'src/Front/AppShortcodeRenderer.php'=>['fieldflow_route_today mode="app"'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.592'", "public const STABLE_RELEASE = '2.2.593'"],
            'src/Support/ReleaseGate.php'=>['PORTAL_EXPORT_RUNTIME_CERT_PARITY_9BV_CONTRACT','checkPortalExportRuntimeCertParity9BVContract'],
            'tools/verify-portal-export-runtime-certification-parity-9bv.php'=>['PORTAL EXPORT RUNTIME CERTIFICATION PARITY 9BV CONTRACT OK','successor=>=2.2.593'],
            'tools/test-portal-export-runtime-certification-parity-9bv.php'=>['PORTAL EXPORT RUNTIME CERTIFICATION PARITY 9BV RUNTIME OK','client_export_dependencies_closed','reports_failure_domain_isolated','operative_export_path_preserved','gate_validator_parity'],
            'PHASE-9BV-PORTAL-EXPORT-RUNTIME-CERTIFICATION-PARITY.md'=>['fieldflow-portal-export-runtime-certification-parity-v1','No database schema'],
        ];
        $result['portal_export_runtime_cert_parity_9bv_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.593', '>=');
        foreach ($portalExport9bvRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['portal_export_runtime_cert_parity_9bv_ok'] = false;
        }
        if (!$result['portal_export_runtime_cert_parity_9bv_ok']) {
            $result['error'] = 'Portal Export Runtime Integrity & Certification Parity 9BV is incomplete.';
            return $result;
        }

        $clientReportScope9bwRequirements = [
            'src/Front/ClientPortalShortcodeRenderer.php'=>['fieldflow-client-report-scope-briefing-assignment-v1','function paramsForReports()','date_from','date_to','owner_user_id','A route opened in Rotas must never leak into Relatórios'],
            'src/Admin/FormSubmissions.php'=>['fieldflow-client-report-scope-briefing-assignment-v1','briefing_questions_for_submission','ContextQuestionService::briefing_for_context','briefing_location_assigned_to_owner','assigned_to=%d AND is_active=1','briefing_questions'],
            'src/Forms/SubmissionPresentation.php'=>['fieldflow-client-report-scope-briefing-assignment-v1',"'briefing' => ['name'=>'Briefing'",'Sem resposta'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.593'", "public const STABLE_RELEASE = '2.2.594'"],
            'src/Support/ReleaseGate.php'=>['CLIENT_REPORT_SCOPE_BRIEFING_ASSIGNMENT_9BW_CONTRACT','checkClientReportScopeBriefingAssignment9BWContract'],
            'tools/verify-client-report-scope-briefing-assignment-9bw.php'=>['CLIENT REPORT HEADER SCOPE & BRIEFING ASSIGNMENT 9BW CONTRACT OK','successor=>=2.2.594'],
            'tools/test-client-report-scope-briefing-assignment-9bw.php'=>['CLIENT REPORT HEADER SCOPE & BRIEFING ASSIGNMENT 9BW RUNTIME OK','header_date_scope','selected_route_leak=0','store_briefing_scope','operative_store_assignment','briefing_export_parity','gate_validator_parity'],
            'PHASE-9BW-CLIENT-REPORT-HEADER-SCOPE-BRIEFING-ASSIGNMENT.md'=>['fieldflow-client-report-scope-briefing-assignment-v1','No database schema'],
        ];
        $result['client_report_scope_briefing_assignment_9bw_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.594', '>=');
        foreach ($clientReportScope9bwRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['client_report_scope_briefing_assignment_9bw_ok'] = false;
        }
        if (!$result['client_report_scope_briefing_assignment_9bw_ok']) {
            $result['error'] = 'Client Report Header Scope & Briefing Assignment Integrity 9BW is incomplete.';
            return $result;
        }

        $visitCentric9bxRequirements = [
            'src/Front/ClientPortalShortcodeRenderer.php'=>['fieldflow-visit-centric-reporting-briefing-continuity-v1',"p.set('report_mode','visits')",'visitas fechadas'],
            'src/Rest/EventsController.php'=>["\$reportMode === 'visits'",'get_visit_report_dataset'],
            'src/Admin/FormSubmissions.php'=>['fieldflow-visit-centric-reporting-briefing-continuity-v1','get_visit_report_dataset',"VisitCompletionPolicy::terminalSql('rs')","INNER JOIN ' . \$px . 'route_stops rs","LEFT JOIN ' . \$submissions . ' s",'assignments ao','campaign_locations clo','s.user_id=%d','briefing_questions_for_submission','BindingResolver::resolve','justified_non_visit'],
            'src/Forms/SubmissionPresentation.php'=>['Fecho operacional','Justificação','Fecho da visita','Relatório ID'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.594'", "public const STABLE_RELEASE = '2.2.595'"],
            'src/Support/ReleaseGate.php'=>['VISIT_CENTRIC_REPORTING_BRIEFING_9BX_CONTRACT','checkVisitCentricReportingBriefing9BXContract'],
            'tools/verify-visit-centric-reporting-briefing-continuity-9bx.php'=>['VISIT-CENTRIC REPORTING & BRIEFING CONTINUITY 9BX CONTRACT OK','successor=>=2.2.595'],
            'tools/test-visit-centric-reporting-briefing-continuity-9bx.php'=>['VISIT-CENTRIC REPORTING & BRIEFING CONTINUITY 9BX RUNTIME OK','visit_stop_base=1','terminal_cardinality=3/5','operative_visit_scope=1','briefing_visit_scope=1','portal_rest_export_parity=1','gate_validator_parity=1'],
            'PHASE-9BX-VISIT-CENTRIC-REPORTING-BRIEFING-CONTINUITY.md'=>['fieldflow-visit-centric-reporting-briefing-continuity-v1','No database schema changes'],
        ];
        $result['visit_centric_reporting_briefing_9bx_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.595', '>=');
        foreach ($visitCentric9bxRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['visit_centric_reporting_briefing_9bx_ok'] = false;
        }
        if (!$result['visit_centric_reporting_briefing_9bx_ok']) {
            $result['error'] = 'Visit-Centric Reporting & Briefing Continuity 9BX is incomplete.';
            return $result;
        }

        $multilingual9byRequirements = [
            'src/Localization/LanguageService.php'=>['fieldflow-multilingual-client-locale-authority-v1',"public const META_KEY = 'i18n'",'supportedLocales','clientConfig','resolveLocale','persistPreference','ResponsiveAssets::shouldLoadForRequest()','fieldflow-i18n.js','fieldflow-i18n.css','WebViewBridge',"'pt-PT'","'en-GB'","'es-ES'","'fr-FR'"],
            'src/Localization/LanguageController.php'=>["register_rest_route('fieldflow/v1', '/language'",'persistPreference','fieldflow_i18n_scope_denied','fieldflow_i18n_locale_disabled'],
            'src/Admin/Clients.php'=>['fieldflow_default_locale','fieldflow_enabled_locales','fieldflow_allow_locale_switch','LanguageService::sanitiseClientI18n'],
            'src/Architecture/RuntimeRegistry.php'=>['LanguageService::registerHooks'],
            'src/Architecture/RestRouteRegistry.php'=>['LanguageController'],
            'assets/fieldflow-i18n.js'=>['fieldflow-multilingual-client-locale-authority-v1','MutationObserver','window.FieldFlowI18n','fieldflow:locale-changed','data-fieldflow-i18n-ignore','fieldflow:i18n:'],
            'assets/fieldflow-i18n.css'=>['.ff-language-switcher','.ff-language-trigger','.ff-language-option'],
            'assets/fieldflow-offline-runtime.js'=>['window.FieldFlowI18n',"const RUNTIME_BUILD='2.2.596'"],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.595'","public const STABLE_RELEASE = '2.2.596'","// public const SOURCE_RELEASE = '2.2.594'","// public const STABLE_RELEASE = '2.2.595'"],
            'src/Support/ReleaseGate.php'=>['MULTILINGUAL_CLIENT_LOCALE_AUTHORITY_9BY_CONTRACT','checkMultilingualClientLocaleAuthority9BYContract'],
            'tools/verify-multilingual-client-locale-authority-9by.php'=>['MULTILINGUAL CLIENT LOCALE AUTHORITY 9BY CONTRACT OK','successor=>=2.2.596'],
            'tools/test-multilingual-client-locale-authority-9by.php'=>['MULTILINGUAL CLIENT LOCALE AUTHORITY 9BY RUNTIME OK','client_meta_authority=1','user_preference_scope=1','front_selector=1','dynamic_runtime_translation=1','native_webview_no_flutterflow=1','data_translation_guard=1','gate_validator_parity=1'],
            'PHASE-9BY-MULTILINGUAL-CLIENT-LOCALE-AUTHORITY.md'=>['fieldflow-multilingual-client-locale-authority-v1','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $result['multilingual_client_locale_authority_9by_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.596', '>=');
        foreach ($multilingual9byRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['multilingual_client_locale_authority_9by_ok'] = false;
        }
        if (!$result['multilingual_client_locale_authority_9by_ok']) {
            $result['error'] = 'Multilingual Client Locale Authority 9BY is incomplete.';
            return $result;
        }

        $premiumLocaleVisitMemory9bzRequirements = [
            'src/Localization/LanguageService.php'=>['PREMIUM_UI_CONTRACT','fieldflow-react-locale-switcher-v1','COVERAGE_CONTRACT','fieldflow-i18n-operational-coverage-v2'],
            'src/Front/ReactFrontFoundation.php'=>['LOCALE_SWITCHER_CONTRACT','fieldflow-react-locale-switcher-v1'],
            'frontend/src/react-front/locale-switcher.ts'=>['fieldflow-react-locale-switcher-v1','ff-language-globe3d','runtime.setLocale','typescript-react-wp-element'],
            'frontend/tsconfig.react-front.json'=>['src/react-front/locale-switcher.ts'],
            'assets/fieldflow-i18n.js'=>['fieldflow-i18n-operational-coverage-v2','fieldflow:i18n-ready','data-fieldflow-i18n-user-content','contenteditable'],
            'assets/fieldflow-i18n.css'=>['.ff-language-globe3d','.ff-language-menu-premium','prefers-reduced-motion'],
            'src/Forms/DailySubmissionService.php'=>['visit_memory_autofill_question_eligible','new_visit_autofill_prefill'],
            'src/Forms/Forms.php'=>['DailySubmissionService::new_visit_autofill_prefill','Histórico da última visita carregado.','Fotografias, ficheiros e Sugestão de encomenda nunca são reutilizados'],
            'src/Mobile/FormPayloadService.php'=>['DailySubmissionService::new_visit_autofill_prefill',"'mode' => 'prefill'"],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.596'","public const STABLE_RELEASE = '2.2.597'","// public const SOURCE_RELEASE = '2.2.595'","// public const STABLE_RELEASE = '2.2.596'"],
            'src/Support/ReleaseGate.php'=>['PREMIUM_LOCALE_VISIT_MEMORY_9BZ_CONTRACT','checkPremiumLocaleVisitMemory9BZContract'],
            'tools/verify-premium-locale-visit-memory-9bz.php'=>['PREMIUM LOCALE & VISIT MEMORY CONTINUITY 9BZ CONTRACT OK','successor=>=2.2.597'],
            'tools/test-premium-locale-visit-memory-9bz.php'=>['PREMIUM LOCALE & VISIT MEMORY CONTINUITY 9BZ RUNTIME OK','normal_answers_prefilled=1','fronts_prefilled=1','photos_excluded=1','order_excluded=1','gate_validator_parity=1'],
            'PHASE-9BZ-PREMIUM-LOCALE-VISIT-MEMORY-CONTINUITY.md'=>['fieldflow-premium-locale-visit-memory-continuity-v1','No FlutterFlow source modification is required','No database schema changes'],
        ];
        $result['premium_locale_visit_memory_9bz_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.597', '>=');
        foreach ($premiumLocaleVisitMemory9bzRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['premium_locale_visit_memory_9bz_ok'] = false;
        }
        $react9bz = self::entryContents($entries, 'fieldflow/assets/fieldflow-react-front-2.2.597.js');
        foreach (['fieldflow-react-locale-switcher-v1','typescript-react-wp-element','2.2.597'] as $marker) {
            if (strpos($react9bz,$marker) === false) $result['premium_locale_visit_memory_9bz_ok'] = false;
        }
        $offline9bz = self::entryContents($entries, 'fieldflow/assets/fieldflow-offline-runtime-2.2.597.js');
        if (strpos($offline9bz,"const RUNTIME_BUILD='2.2.597'") === false) $result['premium_locale_visit_memory_9bz_ok'] = false;
        if (!$result['premium_locale_visit_memory_9bz_ok']) {
            $result['error'] = 'Premium Locale & Visit Memory Continuity 9BZ is incomplete.';
            return $result;
        }

        // 2.2.598+ Certification Marker Continuity 9CA.
        $certificationMarker9caRequirements = [
            'src/Forms/Forms.php'=>['Frentes reutiliza o valor final anterior','DailySubmissionService::new_visit_autofill_prefill','9BD historical certification marker only'],
            'src/Forms/DailySubmissionService.php'=>['visit_memory_autofill_question_eligible','new_visit_autofill_prefill'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.597'","public const STABLE_RELEASE = '2.2.598'","// public const SOURCE_RELEASE = '2.2.596'","// public const STABLE_RELEASE = '2.2.597'"],
            'src/Support/ReleaseGate.php'=>['CERTIFICATION_MARKER_CONTINUITY_9CA_CONTRACT','checkCertificationMarkerContinuity9CAContract'],
            'src/Admin/ReleaseManager.php'=>["packageResult['certification_marker_continuity_9ca_ok']"],
            'tools/verify-certification-marker-continuity-9ca.php'=>['CERTIFICATION MARKER CONTINUITY 9CA CONTRACT OK','successor=>=2.2.598'],
            'tools/test-certification-marker-continuity-9ca.php'=>['CERTIFICATION MARKER CONTINUITY 9CA RUNTIME OK','9bd_marker=1','9bz_runtime_preserved=1','successor_continuity=1'],
            'PHASE-9CA-CERTIFICATION-MARKER-CONTINUITY.md'=>['fieldflow-certification-marker-continuity-9ca-v1','`SOURCE_RELEASE` is `2.2.597` and `STABLE_RELEASE` is `2.2.598`','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $result['certification_marker_continuity_9ca_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.598', '>=');
        foreach ($certificationMarker9caRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['certification_marker_continuity_9ca_ok'] = false;
        }
        if (!$result['certification_marker_continuity_9ca_ok']) {
            $result['error'] = 'Certification Marker Continuity 9CA is incomplete.';
            return $result;
        }

        // 2.2.599+ Locale Brand & Associated Client Scope Continuity 9CB.
        $localeBrandScope9cbRequirements = [
            'src/Localization/LanguageService.php'=>['SCOPE_CONTINUITY_CONTRACT','fieldflow-locale-brand-scope-continuity-v1','scopeClientIds'],
            'src/Localization/LanguageController.php'=>["register_rest_route('fieldflow/v1', '/language'",'Permissions::is_allowed_client'],
            'assets/fieldflow-i18n.js'=>['fieldflow-locale-brand-scope-continuity-v1','setClientContext',"document.addEventListener('routespro:scope-change'",'fieldflow:i18n-context-changed','applyLegacyBrand'],
            'assets/fieldflow-i18n.css'=>['.ff-language-trigger:hover','var(--ff-lang-primary)','var(--ff-lang-accent)','.ff-language-code-badge'],
            'frontend/src/react-front/locale-switcher.ts'=>['fieldflow-locale-brand-scope-continuity-v1','fieldFlowLocaleBrandStyle',"document.addEventListener('routespro:scope-change'","window.addEventListener('fieldflow:i18n-context-changed'",'MutationObserver'],
            'frontend/src/react-front/bootstrap.ts'=>["window.addEventListener('fieldflow:i18n-context-changed'",'fieldFlowReactLocaleSwitcherMount'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.598'","public const STABLE_RELEASE = '2.2.599'","// public const SOURCE_RELEASE = '2.2.597'","// public const STABLE_RELEASE = '2.2.598'"],
            'src/Support/ReleaseGate.php'=>['LOCALE_BRAND_ASSOCIATED_CLIENT_SCOPE_9CB_CONTRACT','checkLocaleBrandAssociatedClientScope9CBContract'],
            'src/Admin/ReleaseManager.php'=>["packageResult['locale_brand_associated_client_scope_9cb_ok']"],
            'tools/verify-locale-brand-associated-client-scope-9cb.php'=>['LOCALE BRAND & ASSOCIATED CLIENT SCOPE CONTINUITY 9CB CONTRACT OK','successor=>=2.2.599'],
            'tools/test-locale-brand-associated-client-scope-9cb.php'=>['LOCALE BRAND & ASSOCIATED CLIENT SCOPE CONTINUITY 9CB RUNTIME OK','brand_hover=1','dynamic_client_scope=1','react_rebind=1','historical_certification=1'],
            'PHASE-9CB-LOCALE-BRAND-ASSOCIATED-CLIENT-SCOPE-CONTINUITY.md'=>['fieldflow-locale-brand-scope-continuity-v1','`SOURCE_RELEASE` is `2.2.598` and `STABLE_RELEASE` is `2.2.599`','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $result['locale_brand_associated_client_scope_9cb_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.599', '>=');
        foreach ($localeBrandScope9cbRequirements as $relative => $markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker) === false) $result['locale_brand_associated_client_scope_9cb_ok'] = false;
        }
        $react9cb = self::entryContents($entries, 'fieldflow/assets/fieldflow-react-front-2.2.599.js');
        foreach (['FieldFlow 2.2.599','fieldflow-locale-brand-scope-continuity-v1','fieldFlowLocaleBrandStyle','routespro:scope-change'] as $marker) {
            if (strpos($react9cb,$marker) === false) $result['locale_brand_associated_client_scope_9cb_ok'] = false;
        }
        if (!$result['locale_brand_associated_client_scope_9cb_ok']) {
            $result['error'] = 'Locale Brand & Associated Client Scope Continuity 9CB is incomplete.';
            return $result;
        }


        // 2.2.600+ App Shell & Browser-like Translation Continuity 9CC.
        $appShellTranslation9ccRequirements = [
            'src/Localization/LanguageService.php'=>['AUTO_TRANSLATION_CONTRACT','APP_SHELL_CONTINUITY_CONTRACT','fieldflow-browserlike-auto-translation-v1','fieldflow-app-shell-locale-runtime-v1',"'zh-CN'","translationEndpoint'=>rest_url('fieldflow/v1/language/translate')"],
            'src/Localization/LanguageController.php'=>["register_rest_route('fieldflow/v1', '/language/translate'",'TranslationService::translateBatch','Permissions::is_manager()','canUseClient'],
            'src/Localization/TranslationService.php'=>['fieldflow-browserlike-auto-translation-v1','translateBatch',"AIFactory::make('translation')",'store names, postal addresses and product names','Simplified Chinese'],
            'assets/fieldflow-i18n.js'=>['fieldflow-browserlike-auto-translation-v1','fieldflow-app-shell-locale-runtime-v1','window.Translator','T.create({sourceLanguage:','serverTranslateBatch','preserveElement',"locale==='zh-CN'",'fieldflow:portal-ready','ensureSwitcher','data-fieldflow-locale-host'],
            'frontend/src/react-front/locale-switcher.ts'=>['fieldflow-app-shell-locale-runtime-v1','fieldflow-browserlike-auto-translation-v1','element.createElement(fieldFlowReactLocaleSwitcherComponent, null)','Invalid hook call','runtime.ensureSwitcher','runtime.prepareTranslator'],
            'frontend/src/react-front/bootstrap.ts'=>["document.addEventListener('fieldflow:portal-ready'","window.addEventListener('pageshow'",'FieldFlowI18n?.reconcile'],
            'src/Front/AppShortcodeRenderer.php'=>['data-fieldflow-locale-host="1"','data-fieldflow-locale-shell="operative"'],
            'src/Front/ClientPortalShortcodeRenderer.php'=>['data-fieldflow-locale-host="1"','data-fieldflow-locale-shell="client"'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.599'","public const STABLE_RELEASE = '2.2.600'","// public const SOURCE_RELEASE = '2.2.598'","// public const STABLE_RELEASE = '2.2.599'"],
            'src/Support/ReleaseGate.php'=>['APP_SHELL_BROWSERLIKE_TRANSLATION_9CC_CONTRACT','checkAppShellBrowserlikeTranslation9CCContract'],
            'src/Admin/ReleaseManager.php'=>["packageResult['app_shell_browserlike_translation_9cc_ok']"],
            'tools/verify-app-shell-browserlike-translation-9cc.php'=>['APP SHELL & BROWSER-LIKE TRANSLATION 9CC CONTRACT OK','successor=>=2.2.600'],
            'tools/test-app-shell-browserlike-translation-9cc.php'=>['APP SHELL & BROWSER-LIKE TRANSLATION 9CC RUNTIME OK','react_hooks=1','dual_portal_webview=1','browserlike_translation=1','protected_names=1','chinese_full_translation=1','historical_memory=1'],
            'PHASE-9CC-APP-SHELL-BROWSERLIKE-TRANSLATION-CONTINUITY.md'=>['fieldflow-app-shell-browserlike-translation-v1','`SOURCE_RELEASE` is `2.2.599` and `STABLE_RELEASE` is `2.2.600`','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $result['app_shell_browserlike_translation_9cc_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.600', '>=');
        foreach ($appShellTranslation9ccRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['app_shell_browserlike_translation_9cc_ok'] = false;
        }
        $react9cc = self::entryContents($entries, 'fieldflow/assets/fieldflow-react-front-2.2.600.js');
        foreach (['FieldFlow 2.2.600','fieldflow-app-shell-locale-runtime-v1','fieldflow-browserlike-auto-translation-v1','fieldFlowReactLocaleSwitcherComponent','Invalid hook call'] as $required) {
            if (strpos($react9cc,$required) === false) $result['app_shell_browserlike_translation_9cc_ok'] = false;
        }
        if (!$result['app_shell_browserlike_translation_9cc_ok']) {
            $result['error'] = 'App Shell & Browser-like Translation Continuity 9CC is incomplete.';
            return $result;
        }


        // 2.2.601+ Adaptive Locale Dock Continuity 9CD.
        $adaptiveLocaleDock9cdRequirements = [
            'frontend/src/react-front/locale-switcher.ts'=>['fieldflow-adaptive-locale-dock-v1',"window.addEventListener('scroll'","document.addEventListener('scroll'","switcherClasses.push('is-docked')","switcherClasses.push('is-scrolling')",'setDocked(false)','setOpen(true)'],
            'assets/fieldflow-i18n.css'=>['Adaptive Locale Dock','.ff-language-switcher.is-docked:not(.is-open):not(.is-scrolling)','.ff-language-switcher.is-scrolling:not(.is-open)','safe-area-inset-right','prefers-reduced-motion:reduce'],
            'assets/fieldflow-i18n.js'=>['fieldflow-adaptive-locale-dock-v1','wireLegacyAdaptiveDock',"wrap.classList.add('is-docked','is-scrolling')",'adaptiveDockContract'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.600'","public const STABLE_RELEASE = '2.2.601'","// public const SOURCE_RELEASE = '2.2.599'","// public const STABLE_RELEASE = '2.2.600'"],
            'src/Support/ReleaseGate.php'=>['ADAPTIVE_LOCALE_DOCK_9CD_CONTRACT','checkAdaptiveLocaleDock9CDContract'],
            'src/Admin/ReleaseManager.php'=>["packageResult['adaptive_locale_dock_9cd_ok']"],
            'tools/verify-adaptive-locale-dock-9cd.php'=>['ADAPTIVE LOCALE DOCK 9CD CONTRACT OK','successor=>=2.2.601'],
            'tools/test-adaptive-locale-dock-9cd.php'=>['ADAPTIVE LOCALE DOCK 9CD RUNTIME OK','initial_visible=1','scroll_hidden=1','resting_tab=1','tap_reveal=1','safe_area=1','historical_continuity=1'],
            'PHASE-9CD-ADAPTIVE-LOCALE-DOCK-CONTINUITY.md'=>['fieldflow-adaptive-locale-dock-v1','`SOURCE_RELEASE` is `2.2.600` and `STABLE_RELEASE` is `2.2.601`','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $result['adaptive_locale_dock_9cd_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.601', '>=');
        foreach ($adaptiveLocaleDock9cdRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['adaptive_locale_dock_9cd_ok'] = false;
        }
        $react9cd = self::entryContents($entries, 'fieldflow/assets/fieldflow-react-front-2.2.601.js');
        foreach (['FieldFlow 2.2.601','fieldflow-adaptive-locale-dock-v1',"switcherClasses.push('is-docked')","switcherClasses.push('is-scrolling')"] as $required) {
            if (strpos($react9cd,$required) === false) $result['adaptive_locale_dock_9cd_ok'] = false;
        }
        if (!$result['adaptive_locale_dock_9cd_ok']) {
            $result['error'] = 'Adaptive Locale Dock Continuity 9CD is incomplete.';
            return $result;
        }

        // 2.2.602+ Analytics Header Date Export Authority 9CE.
        $analyticsHeaderDate9ceRequirements = [
            'fieldflow.php'=>['Version: 2.2.602',"define('FIELDFLOW_VERSION', '2.2.602')"],
            'src/Front/ClientPortalShortcodeRenderer.php'=>['fieldflow-analytics-header-date-export-authority-v1','function analyticsHeaderDateScope()','function syncAnalyticsExportDateAuthority()',"addEventListener('input',syncAnalyticsExportDateAuthority)","addEventListener('pointerdown',syncAnalyticsExportDateAuthority","p.set('from',scope.from)","p.set('to',scope.to)"],
            'src/Shortcodes.php'=>['fieldflow-analytics-header-date-export-authority-v1','function analyticsHeaderDateScope()','function syncAnalyticsExportDateAuthority()',"addEventListener('input',syncAnalyticsExportDateAuthority)","addEventListener('pointerdown',syncAnalyticsExportDateAuthority","p.set('from',scope.from)","p.set('to',scope.to)"],
            'src/Mobile/WebViewBridge.php'=>['fieldflow-webview-dashboard-export-date-authority-v1','function applyLiveAnalyticsDateScope(anchor,url)',"action!=='routespro_export_dashboard'","url.searchParams.set('from',fromValue)","url.searchParams.set('to',toValue)"],
            'src/Admin/FormSubmissions.php'=>['fieldflow-analytics-header-date-export-authority-v1','normalizeDashboardReportDate','checkdate($parts[1], $parts[2], $parts[0])','if ($filters[\'from\'] > $filters[\'to\'])'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.601'","public const STABLE_RELEASE = '2.2.602'","// public const SOURCE_RELEASE = '2.2.600'","// public const STABLE_RELEASE = '2.2.601'"],
            'src/Support/ReleaseGate.php'=>['ANALYTICS_HEADER_DATE_EXPORT_AUTHORITY_9CE_CONTRACT','checkAnalyticsHeaderDateExportAuthority9CEContract'],
            'src/Admin/ReleaseManager.php'=>["packageResult['analytics_header_date_export_authority_9ce_ok']",'DATAS EXPORT OK'],
            'tools/verify-analytics-header-date-export-authority-9ce.php'=>['ANALYTICS HEADER DATE EXPORT AUTHORITY 9CE CONTRACT OK','successor=>=2.2.602'],
            'tools/test-analytics-header-date-export-authority-9ce.php'=>['ANALYTICS HEADER DATE EXPORT AUTHORITY 9CE RUNTIME OK','client_live_dates=1','operative_live_dates=1','webview_capture=1','server_normalization=1','presets_preserved=1','historical_continuity=1'],
            'PHASE-9CE-ANALYTICS-HEADER-DATE-EXPORT-AUTHORITY.md'=>['fieldflow-analytics-header-date-export-authority-v1','`SOURCE_RELEASE` is `2.2.601` and `STABLE_RELEASE` is `2.2.602`','Header dates override stale export URLs','Preset buttons remain supported','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $result['analytics_header_date_export_authority_9ce_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.602', '>=');
        foreach ($analyticsHeaderDate9ceRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['analytics_header_date_export_authority_9ce_ok'] = false;
        }
        if (!$result['analytics_header_date_export_authority_9ce_ok']) {
            $result['error'] = 'Analytics Header Date Export Authority 9CE is incomplete.';
            return $result;
        }

        // 2.2.603+ Expense Statement PDF & Receipt Authority 9CF.
        $expenseStatement9cfRequirements = [
            'fieldflow.php'=>['Version: 2.2.603',"define('FIELDFLOW_VERSION', '2.2.603')"],
            'src/Expenses/ExpenseStatementService.php'=>['fieldflow-expense-statement-pdf-receipt-authority-v1','normalizeFilters(array $input)','statementData(int $userId','ExpensesController::buildWhere($filters, $userId, true)','Branding::get_header_logos','Declaração de recebimento','Assinatura do colaborador','Data: ____ / ____ / ______'],
            'src/Admin/Expenses.php'=>['admin_post_fieldflow_expense_statement_export','exportStatement()','Folha de despesas PDF'],
            'src/Front/ExpensesShortcodeRenderer.php'=>['fieldflow_expense_statement_export','ffe-statement-pdf','syncStatementUrl()',"['month','.ffe-filter-month']","['date_from','.ffe-filter-from']","['date_to','.ffe-filter-to']","['category','.ffe-filter-category']","['status','.ffe-filter-status']","['search','.ffe-filter-search']"],
            'src/Mobile/WebViewBridge.php'=>['fieldflow-webview-expense-statement-scope-authority-v1','function applyLiveExpenseStatementScope(anchor,url)',"action!=='fieldflow_expense_statement_export'",'url=applyLiveExpenseStatementScope(anchor,url);'],
            'src/Support/Loader.php'=>['src/Expenses/ExpenseStatementService.php'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.602'","public const STABLE_RELEASE = '2.2.603'","// public const SOURCE_RELEASE = '2.2.601'","// public const STABLE_RELEASE = '2.2.602'"],
            'src/Support/ReleaseGate.php'=>['EXPENSE_STATEMENT_PDF_RECEIPT_AUTHORITY_9CF_CONTRACT','checkExpenseStatementPdfReceiptAuthority9CFContract'],
            'src/Admin/ReleaseManager.php'=>["packageResult['expense_statement_pdf_receipt_authority_9cf_ok']",'FOLHA DESPESAS OK'],
            'tools/verify-expense-statement-pdf-receipt-authority-9cf.php'=>['EXPENSE STATEMENT PDF & RECEIPT AUTHORITY 9CF CONTRACT OK','successor=>=2.2.603'],
            'tools/test-expense-statement-pdf-receipt-authority-9cf.php'=>['EXPENSE STATEMENT PDF & RECEIPT AUTHORITY 9CF RUNTIME OK','personal_pdf=1','admin_operational_pdf=1','date_filter_authority=1','daily_totals=1','dual_branding=1','signature_receipt=1','webview_live_scope=1','historical_continuity=1'],
            'PHASE-9CF-EXPENSE-STATEMENT-PDF-RECEIPT-AUTHORITY.md'=>['fieldflow-expense-statement-pdf-receipt-authority-v1','`SOURCE_RELEASE` is `2.2.602` and `STABLE_RELEASE` is `2.2.603`','Existing expenses remain the single source of truth','Explicit De/Até has precedence over the month shortcut','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $result['expense_statement_pdf_receipt_authority_9cf_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.603', '>=');
        foreach ($expenseStatement9cfRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['expense_statement_pdf_receipt_authority_9cf_ok'] = false;
        }
        if (!$result['expense_statement_pdf_receipt_authority_9cf_ok']) {
            $result['error'] = 'Expense Statement PDF & Receipt Authority 9CF is incomplete.';
            return $result;
        }

        // 2.2.604+ Expense Statement Mobile Ticket Delivery 9CG.
        $expenseStatementMobile9cgRequirements = [
            'fieldflow.php'=>['Version: 2.2.604',"define('FIELDFLOW_VERSION', '2.2.604')"],
            'src/Admin/Expenses.php'=>['fieldflow-expense-statement-mobile-ticket-delivery-v1','prepareStatementTicket()','handleStatementTicketDownload()',"wp_tempnam('fieldflow-expense-statement.pdf')",'fieldflow_expense_statement_download',"in_array(\$method, ['GET','HEAD'], true)",'X-FieldFlow-Expense-Statement-Ticket','Keep the ticket alive for Android/Chrome HEAD probes and GET retries.'],
            'src/Architecture/RuntimeRegistry.php'=>['wp_ajax_fieldflow_prepare_expense_statement_ticket','handleStatementTicketDownload','fieldflow_expense_statement_ticket_cleanup'],
            'src/Mobile/WebViewBridge.php'=>['fieldflow-expense-statement-mobile-ticket-delivery-v1',"expenseStatement:action==='fieldflow_expense_statement_export'",'function requestExpenseStatement(meta)',"body.set('action','fieldflow_prepare_expense_statement_ticket')","source:'expense_statement_pdf_ticket'","transport:'external_system_browser'",'if(meta&&meta.expenseStatement){requestExpenseStatement(meta);return;}'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.603'","public const STABLE_RELEASE = '2.2.604'","// public const SOURCE_RELEASE = '2.2.602'","// public const STABLE_RELEASE = '2.2.603'"],
            'src/Support/ReleaseGate.php'=>['EXPENSE_STATEMENT_MOBILE_TICKET_DELIVERY_9CG_CONTRACT','checkExpenseStatementMobileTicketDelivery9CGContract'],
            'src/Admin/ReleaseManager.php'=>["packageResult['expense_statement_mobile_ticket_delivery_9cg_ok']",'PDF APP OK'],
            'tools/verify-expense-statement-mobile-ticket-delivery-9cg.php'=>['EXPENSE STATEMENT MOBILE TICKET DELIVERY 9CG CONTRACT OK','successor=>=2.2.604'],
            'tools/test-expense-statement-mobile-ticket-delivery-9cg.php'=>['EXPENSE STATEMENT MOBILE TICKET DELIVERY 9CG RUNTIME OK','cookie_free_ticket=1','head_safe=1','generic_flutter_bypass=1','system_browser=1','filter_scope=1','historical_continuity=1'],
            'PHASE-9CG-EXPENSE-STATEMENT-MOBILE-TICKET-DELIVERY.md'=>['fieldflow-expense-statement-mobile-ticket-delivery-v1','`SOURCE_RELEASE` is `2.2.603` and `STABLE_RELEASE` is `2.2.604`','authenticated WebView session','cookie-free capability URL','HEAD probes','generic Flutter downloader','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $result['expense_statement_mobile_ticket_delivery_9cg_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.604', '>=');
        foreach ($expenseStatementMobile9cgRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['expense_statement_mobile_ticket_delivery_9cg_ok'] = false;
        }
        if (!$result['expense_statement_mobile_ticket_delivery_9cg_ok']) {
            $result['error'] = 'Expense Statement Mobile Ticket Delivery 9CG is incomplete.';
            return $result;
        }

        // 2.2.605+ Expense Settlement, Payment & Signature Authority 9CH.
        $expenseSettlement9chRequirements = [
            'fieldflow.php'=>['Version: 2.2.605',"define('FIELDFLOW_VERSION', '2.2.605')","9CH predecessor marker: define('FIELDFLOW_VERSION', '2.2.604')"],
            'src/Expenses/ExpenseService.php'=>["'paid' => 'Paga / Reembolsada'",'public static function setStatus(','payment_history',"'paid_at' => \$now","'paid_by' => \$actorUserId"],
            'src/Expenses/ExpenseSettlementService.php'=>['fieldflow-expense-settlement-signature-authority-v1',"POST_TYPE = 'ff_exp_settlement'","'public' => false",'normalizeSettlementFilters(array $input)',"\$filters['status'] = 'paid'",'submitUpload(','submitDirectSignature(','snapshotHash(array $data)','fieldflow-private-expense-settlements','serveEvidence()'],
            'src/Expenses/ExpensesController.php'=>['/expenses/statement-context','/expenses/statement-evidence','statementContext(','submitStatementEvidence(','declaration_confirmed'],
            'src/Expenses/ExpenseStatementService.php'=>['signature_image_path','signature_signed_at','/Sig1','Assinado no FieldFlow em','signedFilename(array $data)'],
            'src/Admin/Expenses.php'=>['fieldflow_expense_bulk_paid','bulkMarkPaid()','Marcar filtradas como Paga / Reembolsada','Folhas assinadas e comprovativos de recebimento','folhas-assinadas/','Pago em','Pago por'],
            'src/Front/ExpensesShortcodeRenderer.php'=>['Fecho e comprovativo de recebimento','Enviar folha já assinada','Assinar diretamente no FieldFlow','ffe-signature','statement-context','statement-evidence',"fd.set('mode','upload')","fd.set('mode','signature')"],
            'src/Support/Loader.php'=>['src/Expenses/ExpenseSettlementService.php'],
            'src/Architecture/RuntimeRegistry.php'=>['ExpenseSettlementService::registerHooks()'],
            'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.604'","public const STABLE_RELEASE = '2.2.605'","// public const SOURCE_RELEASE = '2.2.603'","// public const STABLE_RELEASE = '2.2.604'"],
            'src/Support/ReleaseGate.php'=>['EXPENSE_SETTLEMENT_SIGNATURE_9CH_CONTRACT','checkExpenseSettlementSignature9CHContract'],
            'src/Admin/ReleaseManager.php'=>["packageResult['expense_settlement_signature_9ch_ok']",'PAGAMENTO + ASSINATURA OK'],
            'tools/verify-expense-settlement-signature-authority-9ch.php'=>['EXPENSE SETTLEMENT PAYMENT & SIGNATURE AUTHORITY 9CH CONTRACT OK','successor=>=2.2.605'],
            'tools/test-expense-settlement-signature-authority-9ch.php'=>['EXPENSE SETTLEMENT PAYMENT & SIGNATURE AUTHORITY 9CH RUNTIME OK','paid_state=1','bulk_payment=1','dual_evidence=1','direct_signature_pdf=1','private_archive=1','bo_archive=1','zip_archive=1','no_schema=1','historical_continuity=1'],
            'PHASE-9CH-EXPENSE-SETTLEMENT-SIGNATURE-AUTHORITY.md'=>['fieldflow-expense-settlement-signature-authority-v1','`SOURCE_RELEASE` is `2.2.604` and `STABLE_RELEASE` is `2.2.605`','Paga / Reembolsada','upload an already signed statement','draw a handwritten signature directly','No schema migration is required','No FlutterFlow source modification is required'],
        ];
        $result['expense_settlement_signature_9ch_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.605', '>=');
        foreach ($expenseSettlement9chRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['expense_settlement_signature_9ch_ok'] = false;
        }
        if (!$result['expense_settlement_signature_9ch_ok']) {
            $result['error'] = 'Expense Settlement, Payment & Signature Authority 9CH is incomplete.';
            return $result;
        }

        // 2.2.606+ Visit Closure Selector Return & Route First Paint 9CI.
        $visitClosureSelector9ciRequirements = [
            'fieldflow.php'=>['Version: 2.2.606','define(\'FIELDFLOW_VERSION\', \'2.2.606\')','9CI predecessor marker: define(\'FIELDFLOW_VERSION\', \'2.2.605\')','fieldflow-legacy-pdv-autocomplete-retirement-v1','apply_filters(\'fieldflow_legacy_pdv_autocomplete_enabled\', false)'],
            'src/Support/ReleaseGate.php'=>['VISIT_CLOSURE_SELECTOR_RETURN_9CI_CONTRACT','checkVisitClosureSelectorReturn9CIContract'],
            'src/Shortcodes.php'=>['fieldflow-visit-closure-selector-return-v1','id="rpf-closure"','async function returnToStoreSelector(closedContext,state)','async function refreshVisitsAfterClosure(date)','if(preferred.selection===\'none\')','data-rpf-next-stop','Próxima loja','const scrollToStoreSelector=()=>','await returnToStoreSelector(completedContext,editingExisting?\'updated\':\'done\');','await returnToStoreSelector(closureContextFromSelection(),\'offline\');','await returnToStoreSelector(failureClosedContext,\'failed_offline\');','await returnToStoreSelector(failureClosedContext,\'failed\');','await returnToStoreSelector(fallbackClosedContext,\'done\');','Queres mesmo concluir esta visita?','fieldflow-route-first-paint-v1','Promise.resolve().then(()=>loadRoutesForDay(inpDate.value))','source:\'visit-closure-return\'','.rp-report-focus.is-closure-return #rpf-submit'],
            'src/Cloud/Phase6StableRelease.php'=>['public const SOURCE_RELEASE = \'2.2.605\'','public const STABLE_RELEASE = \'2.2.606\'','// public const SOURCE_RELEASE = \'2.2.604\'','// public const STABLE_RELEASE = \'2.2.605\''],
            'src/Support/ReleasePackageValidator.php'=>['visit_closure_selector_return_9ci_ok','Visit Closure Selector Return & Route First Paint 9CI'],
            'src/Admin/ReleaseManager.php'=>['packageResult[\'visit_closure_selector_return_9ci_ok\']','FECHO → SELETOR OK'],
            'tools/verify-visit-closure-selector-return-9ci.php'=>['VISIT CLOSURE SELECTOR RETURN 9CI CONTRACT OK','successor=>=2.2.606'],
            'tools/test-visit-closure-selector-return-9ci.php'=>['VISIT CLOSURE SELECTOR RETURN 9CI RUNTIME OK','closed_store_not_reselected=1','selector_scroll_once=1','next_store_card=1','offline_return=1','failure_return=1','stored_context_cleared=1','resume_checkpoint_cleared=1','idle_actions_hidden=1','route_first_paint=1','legacy_autocomplete_retired=1','historical_continuity=1'],
            'PHASE-9CI-VISIT-CLOSURE-SELECTOR-RETURN.md'=>['fieldflow-visit-closure-selector-return-v1','`SOURCE_RELEASE` is `2.2.605` and `STABLE_RELEASE` is `2.2.606`','returns to the store selector','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $visitClosureSelector9ciForbidden = [
            'src/Shortcodes.php'=>['await loadVisits(completedContext.date,{stop_id:completedContext.stop_id,force:true})','loadVisits(currentDate,{stop_id:parseInt(els.stop.value||0,10),force:true})','loadGoogle(()=>{ loadRoutesForDay(inpDate.value); });'],
        ];
        $result['visit_closure_selector_return_9ci_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.606', '>=');
        foreach ($visitClosureSelector9ciRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['visit_closure_selector_return_9ci_ok'] = false;
        }
        foreach ($visitClosureSelector9ciForbidden as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw,$blocked) !== false) $result['visit_closure_selector_return_9ci_ok'] = false;
        }
        if (!$result['visit_closure_selector_return_9ci_ok']) {
            $result['error'] = 'Visit Closure Selector Return & Route First Paint 9CI is incomplete.';
            return $result;
        }

        // 2.2.607+ Security & Hygiene Authority 9CJ.
        $securityHygiene9cjRequirements = [
            'fieldflow.php'=>['Version: 2.2.607','define(\'FIELDFLOW_VERSION\', \'2.2.607\')','9CJ predecessor marker: define(\'FIELDFLOW_VERSION\', \'2.2.606\')'],
            'src/Support/ReleaseGate.php'=>['SECURITY_HYGIENE_AUTHORITY_9CJ_CONTRACT','checkSecurityHygieneAuthority9CJContract'],
            'src/Forms/Forms.php'=>['fieldflow-context-form-scope-authority-v1','private static function context_form_scope_error(array $context): string','$scope_error = self::context_form_scope_error($context);','\'fieldflow_context_form_forbidden\''],
            'src/Services/AI.php'=>['fieldflow-ai-model-currency-v1','public const DEFAULT_MODEL = \'gemini-2.5-flash-lite\';','public static function resolveModel(string $model): string','\'x-goog-api-key\'=>$this->key'],
            'src/Support/Config.php'=>['\'google_ai_model\' => \'gemini-2.5-flash-lite\''],
            'src/Admin/AIControlCenter.php'=>['GoogleAIProvider::resolveModel(','placeholder="gemini-2.5-flash-lite"'],
            'tools/.htaccess'=>['fieldflow-build-tooling-web-isolation-v1','Require all denied','Deny from all'],
            'frontend/.htaccess'=>['fieldflow-build-tooling-web-isolation-v1','Require all denied','Deny from all'],
            'src/Shortcodes.php'=>['sanitize_text_field($atts[\'date\'] ?? current_time(\'Y-m-d\'))'],
            'src/Cloud/Phase6StableRelease.php'=>['public const SOURCE_RELEASE = \'2.2.606\'','public const STABLE_RELEASE = \'2.2.607\'','// public const SOURCE_RELEASE = \'2.2.605\'','// public const STABLE_RELEASE = \'2.2.606\''],
            'src/Support/ReleasePackageValidator.php'=>['security_hygiene_authority_9cj_ok','Security & Hygiene Authority 9CJ'],
            'src/Admin/ReleaseManager.php'=>['packageResult[\'security_hygiene_authority_9cj_ok\']','SCOPE + TOOLS + IA OK'],
            'tools/verify-security-hygiene-authority-9cj.php'=>['SECURITY & HYGIENE AUTHORITY 9CJ CONTRACT OK','successor=>=2.2.607'],
            'tools/test-security-hygiene-authority-9cj.php'=>['SECURITY & HYGIENE AUTHORITY 9CJ RUNTIME OK','context_form_scope=1','ai_model_currency=1','gemini_key_header=1','tooling_isolated=1','site_timezone_dates=1','no_schema=1','historical_continuity=1'],
            'PHASE-9CJ-SECURITY-HYGIENE-AUTHORITY.md'=>['fieldflow-security-hygiene-authority-v1','`SOURCE_RELEASE` is `2.2.606` and `STABLE_RELEASE` is `2.2.607`','same authority as submission','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $securityHygiene9cjForbidden = [
            'src/Services/AI.php'=>[':generateContent?key=','\'gemini-1.5-flash\')'],
            'src/Support/Config.php'=>['\'google_ai_model\' => \'gemini-1.5-flash\''],
            'src/Shortcodes.php'=>['esc_attr(date(\'Y-m-d\'))','sanitize_text_field($atts[\'date\'] ?? date(\'Y-m-d\'))'],
        ];
        $result['security_hygiene_authority_9cj_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.607', '>=');
        foreach ($securityHygiene9cjRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['security_hygiene_authority_9cj_ok'] = false;
        }
        foreach ($securityHygiene9cjForbidden as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw,$blocked) !== false) $result['security_hygiene_authority_9cj_ok'] = false;
        }
        if (!$result['security_hygiene_authority_9cj_ok']) {
            $result['error'] = 'Security & Hygiene Authority 9CJ is incomplete.';
            return $result;
        }

        // 2.2.608+ Planner & Commercial Google Cost Authority 9CK.
        $plannerGoogleCost9ckRequirements = [
            'fieldflow.php'=>['Version: 2.2.608','define(\'FIELDFLOW_VERSION\', \'2.2.608\')','9CK predecessor marker: define(\'FIELDFLOW_VERSION\', \'2.2.607\')'],
            'src/Support/ReleaseGate.php'=>['PLANNER_GOOGLE_COST_AUTHORITY_9CK_CONTRACT','checkPlannerGoogleCostAuthority9CKContract'],
            'src/Shortcodes.php'=>['fieldflow-planner-local-preview-cost-authority-v1','function localRoutePreview(){','function internalRoadEdge(a,b){','routeCalcTimer=setTimeout(()=>localRoutePreview(),Math.max(0,delay));','optimizeWaypoints:googleWaypointOptimizationEnabled&&!manualOrderLocked','Calcular percurso real','const extra=googlePlacesContactEnrichment?await findPlaceDetails(r.place_id):{};','new google.maps.places.PlacesService(document.createElement(\'div\'))','Config::get(\'google_routes_waypoint_optimization_enabled\', 0)','wp_json_encode($endpoint_profiles ?? [])'],
            'src/Cloud/Phase6StableRelease.php'=>['public const SOURCE_RELEASE = \'2.2.607\'','public const STABLE_RELEASE = \'2.2.608\'','// public const SOURCE_RELEASE = \'2.2.606\'','// public const STABLE_RELEASE = \'2.2.607\''],
            'src/Support/ReleasePackageValidator.php'=>['planner_google_cost_authority_9ck_ok','Planner & Commercial Google Cost Authority 9CK'],
            'src/Admin/ReleaseManager.php'=>['packageResult[\'planner_google_cost_authority_9ck_ok\']','GOOGLE SÓ A PEDIDO OK'],
            'tools/verify-planner-google-cost-authority-9ck.php'=>['PLANNER GOOGLE COST AUTHORITY 9CK CONTRACT OK','successor=>=2.2.608'],
            'tools/test-planner-google-cost-authority-9ck.php'=>['PLANNER GOOGLE COST AUTHORITY 9CK RUNTIME OK','local_preview=1','estimator_parity=1','manual_order_preserved=1','google_on_request=1','contact_details_gated=1','no_hidden_map=1','nearby_gated=1','no_schema=1','historical_continuity=1'],
            'PHASE-9CK-PLANNER-GOOGLE-COST-AUTHORITY.md'=>['fieldflow-planner-google-cost-authority-v1','`SOURCE_RELEASE` is `2.2.607` and `STABLE_RELEASE` is `2.2.608`','zero-cost local preview','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $plannerGoogleCost9ckForbidden = [
            'src/Shortcodes.php'=>['routeCalcTimer=setTimeout(()=>recalcRoute(),Math.max(0,delay));','optimizeWaypoints:!manualOrderLocked&&!queue.some(item=>item.position_locked)','const extra=await findPlaceDetails(r.place_id);','const dummy=document.createElement(\'div\'); googleMap=new google.maps.Map(dummy'],
        ];
        $result['planner_google_cost_authority_9ck_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.608', '>=');
        foreach ($plannerGoogleCost9ckRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['planner_google_cost_authority_9ck_ok'] = false;
        }
        foreach ($plannerGoogleCost9ckForbidden as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw,$blocked) !== false) $result['planner_google_cost_authority_9ck_ok'] = false;
        }
        if (!$result['planner_google_cost_authority_9ck_ok']) {
            $result['error'] = 'Planner & Commercial Google Cost Authority 9CK is incomplete.';
            return $result;
        }

        // 2.2.609+ Visual Product Catalog 9CL.
        $visualProductCatalog9clRequirements = [
            'fieldflow.php'=>['Version: 2.2.609','define(\'FIELDFLOW_VERSION\', \'2.2.609\')','9CL predecessor marker: define(\'FIELDFLOW_VERSION\', \'2.2.608\')'],
            'src/Support/ReleaseGate.php'=>['VISUAL_PRODUCT_CATALOG_9CL_CONTRACT','checkVisualProductCatalog9CLContract'],
            'src/Catalog/ProductCatalogService.php'=>['fieldflow-visual-product-catalog-v1','fieldflow-briefing-product-label-mapping-v1','public const POST_TYPE = \'ff_catalog_product\';','\'public\' => false,','\'show_in_rest\' => false,','public static function syncFromCardex(int $onlyClientId = 0): array {','public static function mapLabel(int $clientId, string $label, array $productIds): int {','public static function merge(int $sourceId, int $targetId): void {','public static function resolveProductId(int $clientId, string $reference, string $name, $capacity = null): int {','[\'cardex_import\', \'cardex\', \'cardex_briefing\', \'cardex_sync\']'],
            'src/Admin/ProductCatalog.php'=>['fieldflow-visual-product-catalog-admin-v1','check_admin_referer($action, \'fieldflow_catalog_nonce\');','add_action(\'admin_post_fieldflow_catalog_map_label\', [self::class, \'handle_map_label\']);','wp_enqueue_media()'],
            'src/Admin/ProductCardex.php'=>['if (ProductCatalog::current_tab() !== \'cardex\') { ProductCatalog::render(); return; }','ProductCatalog::render_tabs(\'cardex\');'],
            'src/Architecture/RuntimeRegistry.php'=>['\\RoutesPro\\Catalog\\ProductCatalogService::registerHooks();','add_action(\'admin_init\', [\'RoutesPro\\\\Admin\\\\ProductCatalog\', \'register_hooks\']);'],
            'src/Support/Loader.php'=>['\'src/Catalog/ProductCatalogService.php\'','\'src/Admin/ProductCatalog.php\''],
            'src/Cloud/Phase6StableRelease.php'=>['public const SOURCE_RELEASE = \'2.2.608\'','public const STABLE_RELEASE = \'2.2.609\'','// public const SOURCE_RELEASE = \'2.2.607\'','// public const STABLE_RELEASE = \'2.2.608\''],
            'src/Support/ReleasePackageValidator.php'=>['visual_product_catalog_9cl_ok','Visual Product Catalog 9CL'],
            'src/Admin/ReleaseManager.php'=>['packageResult[\'visual_product_catalog_9cl_ok\']','CATÁLOGO VISUAL OK'],
            'tools/verify-visual-product-catalog-9cl.php'=>['VISUAL PRODUCT CATALOG 9CL CONTRACT OK','successor=>=2.2.609'],
            'tools/test-visual-product-catalog-9cl.php'=>['VISUAL PRODUCT CATALOG 9CL RUNTIME OK','dedupe=1','manual_preserved=1','resolution=1','competitor_mapping=1','merge=1','private_storage=1','guarded_admin=1','no_schema=1','historical_continuity=1'],
            'PHASE-9CL-VISUAL-PRODUCT-CATALOG.md'=>['fieldflow-visual-product-catalog-v1','`SOURCE_RELEASE` is `2.2.608` and `STABLE_RELEASE` is `2.2.609`','fieldflow-briefing-product-label-mapping-v1','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $visualProductCatalog9clForbidden = [
            'src/Catalog/ProductCatalogService.php'=>['CREATE TABLE','ALTER TABLE'],
            'src/Admin/ProductCatalog.php'=>['CREATE TABLE','ALTER TABLE'],
        ];
        $result['visual_product_catalog_9cl_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.609', '>=');
        foreach ($visualProductCatalog9clRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['visual_product_catalog_9cl_ok'] = false;
        }
        foreach ($visualProductCatalog9clForbidden as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw,$blocked) !== false) $result['visual_product_catalog_9cl_ok'] = false;
        }
        if (!$result['visual_product_catalog_9cl_ok']) {
            $result['error'] = 'Visual Product Catalog 9CL is incomplete.';
            return $result;
        }

        // 2.2.610+ Fronts Mobile Layout & Catalog Visuals 9CM.
        $frontsMobileCatalogVisual9cmRequirements = [
            'fieldflow.php'=>['Version: 2.2.610','define(\'FIELDFLOW_VERSION\', \'2.2.610\')','9CM predecessor marker: define(\'FIELDFLOW_VERSION\', \'2.2.609\')'],
            'src/Support/ReleaseGate.php'=>['FRONTS_MOBILE_CATALOG_VISUAL_9CM_CONTRACT','checkFrontsMobileCatalogVisual9CMContract'],
            'src/Forms/FormRenderer.php'=>['fieldflow-fronts-mobile-catalog-visual-v1','private static function fronts_stepper(string $input, bool $enabled, string $name = \'\'): string {','private static function fronts_row_summary(','self::$fronts_visuals = self::fronts_catalog_visuals($q, $context, $rows, (int)$resolved_cardex_id);','self::share_space_side_visual($share_catalog_client, $prLabel)','$(document).on(\'click\',\'[data-fronts-step]\',function(e){','$(document).on(\'click\',\'[data-product-zoom]\',function(e){','.rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary{','data-fronts-input','data-share-space-pr','data-share-space-competitor'],
            'src/Catalog/ProductCatalogService.php'=>['public static function visualsForRows(int $clientId, array $rows): array {','public static function visualsForLabel(int $clientId, string $label, int $limit = 3): array {','public static function clientForContext(array $context, int $cardexId = 0): int {'],
            'assets/routespro-form-builder.js'=>['catalog_images_disabled: !!q.catalog_images_disabled,','input[data-fb="catalog_images_disabled"]'],
            'src/Admin/Forms.php'=>['data-fb="catalog_images_disabled"'],
            'src/Cloud/Phase6StableRelease.php'=>['public const SOURCE_RELEASE = \'2.2.609\'','public const STABLE_RELEASE = \'2.2.610\'','// public const SOURCE_RELEASE = \'2.2.608\'','// public const STABLE_RELEASE = \'2.2.609\''],
            'src/Support/ReleasePackageValidator.php'=>['fronts_mobile_catalog_visual_9cm_ok','Fronts Mobile Layout & Catalog Visuals 9CM'],
            'src/Admin/ReleaseManager.php'=>['packageResult[\'fronts_mobile_catalog_visual_9cm_ok\']','FRENTES COMPACTAS OK'],
            'tools/verify-fronts-mobile-catalog-visual-9cm.php'=>['FRONTS MOBILE CATALOG VISUAL 9CM CONTRACT OK','successor=>=2.2.610'],
            'tools/test-fronts-mobile-catalog-visual-9cm.php'=>['FRONTS MOBILE CATALOG VISUAL 9CM RUNTIME OK','resolution=1','same_inputs=1','compact_summary=1','history_before=1','opt_out=1','share_space_visual=1','runtime_handlers=1','no_schema=1','historical_continuity=1'],
            'PHASE-9CM-FRONTS-MOBILE-CATALOG-VISUAL.md'=>['fieldflow-fronts-mobile-catalog-visual-v1','`SOURCE_RELEASE` is `2.2.609` and `STABLE_RELEASE` is `2.2.610`','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $frontsMobileCatalogVisual9cmForbidden = [
        ];
        $result['fronts_mobile_catalog_visual_9cm_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.610', '>=');
        foreach ($frontsMobileCatalogVisual9cmRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['fronts_mobile_catalog_visual_9cm_ok'] = false;
        }
        foreach ($frontsMobileCatalogVisual9cmForbidden as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw,$blocked) !== false) $result['fronts_mobile_catalog_visual_9cm_ok'] = false;
        }
        if (!$result['fronts_mobile_catalog_visual_9cm_ok']) {
            $result['error'] = 'Fronts Mobile Layout & Catalog Visuals 9CM is incomplete.';
            return $result;
        }

        // 2.2.611+ Catalog Cross-Client Reuse 9CN.
        $catalogCrossClientReuse9cnRequirements = [
            'fieldflow.php'=>['Version: 2.2.611','define(\'FIELDFLOW_VERSION\', \'2.2.611\')','9CN predecessor marker: define(\'FIELDFLOW_VERSION\', \'2.2.610\')'],
            'src/Support/ReleaseGate.php'=>['CATALOG_CROSS_CLIENT_REUSE_9CN_CONTRACT','checkCatalogCrossClientReuse9CNContract'],
            'src/Catalog/ProductCatalogService.php'=>['fieldflow-catalog-cross-client-reuse-v1','public static function copyFromClient(int $targetClientId, array $sourceProductIds, string $kind = \'keep\'): array {','public static function refreshImagesFromSource(int $productId): int {','$filters[\'search\'] ?? $filters[\'s\'] ?? \'\''],
            'src/Admin/ProductCatalog.php'=>['add_action(\'admin_post_fieldflow_catalog_reuse\', [self::class, \'handle_reuse\']);','self::guard(\'fieldflow_catalog_reuse\');','Reutilizar de outro cliente','Atualizar imagens da origem'],
            'src/Cloud/Phase6StableRelease.php'=>['public const SOURCE_RELEASE = \'2.2.610\'','public const STABLE_RELEASE = \'2.2.611\'','// public const SOURCE_RELEASE = \'2.2.609\'','// public const STABLE_RELEASE = \'2.2.610\''],
            'src/Support/ReleasePackageValidator.php'=>['catalog_cross_client_reuse_9cn_ok','Catalog Cross-Client Reuse 9CN'],
            'src/Admin/ReleaseManager.php'=>['packageResult[\'catalog_cross_client_reuse_9cn_ok\']','CATÁLOGO REUTILIZÁVEL OK'],
            'tools/verify-catalog-reuse-9cn.php'=>['CATALOG CROSS CLIENT REUSE 9CN CONTRACT OK','successor=>=2.2.611'],
            'tools/test-catalog-reuse-9cn.php'=>['CATALOG CROSS CLIENT REUSE 9CN RUNTIME OK','shared_images=1','no_duplicates=1','independent_copies=1','refresh_from_source=1','labels_per_client=1','guarded_admin=1','no_schema=1','historical_continuity=1'],
            'PHASE-9CN-CATALOG-CROSS-CLIENT-REUSE.md'=>['fieldflow-catalog-cross-client-reuse-v1','`SOURCE_RELEASE` is `2.2.610` and `STABLE_RELEASE` is `2.2.611`','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $catalogCrossClientReuse9cnForbidden = [
            'src/Catalog/ProductCatalogService.php'=>['wp_insert_attachment','CREATE TABLE'],
        ];
        $result['catalog_cross_client_reuse_9cn_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.611', '>=');
        foreach ($catalogCrossClientReuse9cnRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['catalog_cross_client_reuse_9cn_ok'] = false;
        }
        foreach ($catalogCrossClientReuse9cnForbidden as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw,$blocked) !== false) $result['catalog_cross_client_reuse_9cn_ok'] = false;
        }
        if (!$result['catalog_cross_client_reuse_9cn_ok']) {
            $result['error'] = 'Catalog Cross-Client Reuse 9CN is incomplete.';
            return $result;
        }

        // 2.2.612+ Fronts Camera Reading 9CO.
        $frontsCameraReading9coRequirements = [
            'fieldflow.php'=>['Version: 2.2.612','define(\'FIELDFLOW_VERSION\', \'2.2.612\')','9CO predecessor marker: define(\'FIELDFLOW_VERSION\', \'2.2.611\')'],
            'src/Support/ReleaseGate.php'=>['FRONTS_CAMERA_READING_9CO_CONTRACT','checkFrontsCameraReading9COContract'],
            'src/Vision/FrontsVisionService.php'=>['fieldflow-fronts-vision-reading-v1','public const MODULE = \'fronts_vision\';','check_ajax_referer(self::NONCE, \'nonce\', false)','self::rateLimit($userId);','$scopeError = self::scopeError($request, $userId);','\\RoutesPro\\Services\\AIManager::complete($module, $prompt, $params)','public static function confirm(int $readingId, array $confirmed, int $userId): array {'],
            'src/Services/AI.php'=>['trait ProviderVisionPayload {','\'type\' => \'image_url\'','\'type\' => \'input_image\'','\'inline_data\' => [\'mime_type\'','fieldflow-gemini-model-availability-v1','public const DEFAULT_MODEL = \'gemini-3.5-flash-lite\';','private const UNAVAILABLE_FALLBACK = ['],
            'src/Forms/FormRenderer.php'=>['private static function fronts_vision_attrs(array $q, array $context): string {','function rpVisionFill(){','data-fronts-camera-card','fd.append(\'action\',\'fieldflow_fronts_vision_read\')','data-fronts-input','data-share-space-pr'],
            'src/Admin/FrontsVisionAdmin.php'=>['fieldflow-fronts-vision-admin-v1','check_admin_referer(\'fieldflow_vision_settings\', \'fieldflow_vision_nonce\');','check_admin_referer(\'fieldflow_vision_test\', \'fieldflow_vision_nonce\');'],
            'src/Admin/AIControlCenter.php'=>['\'fronts_vision\'=>[\'Leitura de frentes\'','azure_openai_vision_deployment'],
            'src/Support/Config.php'=>['\'ai_module_fronts_vision_provider\' => \'inherit\'','\'google_ai_model\' => \'gemini-3.5-flash-lite\''],
            'assets/routespro-form-builder.js'=>['camera_reading: !!q.camera_reading,','input[data-fb="camera_reading"]'],
            'src/Cloud/Phase6StableRelease.php'=>['public const SOURCE_RELEASE = \'2.2.611\'','public const STABLE_RELEASE = \'2.2.612\'','// public const SOURCE_RELEASE = \'2.2.610\'','// public const STABLE_RELEASE = \'2.2.611\''],
            'src/Support/ReleasePackageValidator.php'=>['fronts_camera_reading_9co_ok','Fronts Camera Reading 9CO'],
            'src/Admin/ReleaseManager.php'=>['packageResult[\'fronts_camera_reading_9co_ok\']','LEITURA POR CÂMARA OK'],
            'tools/verify-fronts-camera-reading-9co.php'=>['FRONTS CAMERA READING 9CO CONTRACT OK','successor=>=2.2.612'],
            'tools/test-fronts-camera-reading-9co.php'=>['FRONTS CAMERA READING 9CO RUNTIME OK','multimodal_providers=1','off_by_default=1','reading_flow=1','confirmation=1','share_space=1','limits=1','guarded=1','no_schema=1','historical_continuity=1'],
            'PHASE-9CO-FRONTS-CAMERA-READING.md'=>['fieldflow-fronts-vision-reading-v1','`SOURCE_RELEASE` is `2.2.611` and `STABLE_RELEASE` is `2.2.612`','fieldflow-gemini-model-availability-v1','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $frontsCameraReading9coForbidden = [
            'src/Vision/FrontsVisionService.php'=>['wp_ajax_nopriv','CREATE TABLE'],
        ];
        $result['fronts_camera_reading_9co_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.612', '>=');
        foreach ($frontsCameraReading9coRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['fronts_camera_reading_9co_ok'] = false;
        }
        foreach ($frontsCameraReading9coForbidden as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw,$blocked) !== false) $result['fronts_camera_reading_9co_ok'] = false;
        }
        if (!$result['fronts_camera_reading_9co_ok']) {
            $result['error'] = 'Fronts Camera Reading 9CO is incomplete.';
            return $result;
        }

        // 2.2.613+ Catalog General Identity 9CP.
        $catalogGeneralIdentity9cpRequirements = [
            'fieldflow.php'=>['Version: 2.2.613','define(\'FIELDFLOW_VERSION\', \'2.2.613\')','9CP predecessor marker: define(\'FIELDFLOW_VERSION\', \'2.2.612\')'],
            'src/Support/ReleaseGate.php'=>['CATALOG_GENERAL_IDENTITY_9CP_CONTRACT','checkCatalogGeneralIdentity9CPContract'],
            'src/Catalog/ProductCatalogService.php'=>['fieldflow-catalog-general-identity-v1','public const IDENTITY_VERSION = 2;','public static function identityKeys(string $reference, string $name, $capacity = null, string $ean = \'\', string $scope = \'\'): array {','public static function resolveProductIdScoped(int $clientId, string $reference, string $name, $capacity = null, string $scope = \'\', string $ean = \'\'): int {','public static function duplicateSuggestions(int $clientId, int $limit = 40): array {','if (!$union($productNode[$id], $owner[$key], true)) $result[\'conflicts\']++;','public static function maybeScheduleIdentityRebuild(): void {','public static function resolveProductId(int $clientId, string $reference, string $name, $capacity = null): int {'],
            'src/Admin/ProductCatalog.php'=>['add_action(\'admin_post_fieldflow_catalog_not_duplicate\', [self::class, \'handle_not_duplicate\']);','self::guard(\'fieldflow_catalog_not_duplicate\');','\'lock_name\' => true,','Possíveis duplicados','private static function insignia_chips(array $row): string {'],
            'src/Vision/FrontsVisionService.php'=>['fieldflow-fronts-field-reference-v1','add_action(\'wp_ajax_\' . self::AJAX_REFERENCE, [self::class, \'ajaxReference\']);','self::rateLimit($userId, \'ff_vision_ref_rate_\', self::REFERENCE_RATE_MAX,','public static function addFieldReference(array $request, array $item, array $photo, int $userId): array {','if ($product[\'image_count\'] > 0) throw new \\RuntimeException(\'Este produto já tem imagem de referência no catálogo.\');','Catalog::resolveProductIdScoped($clientId, $reference, $name, $capacity === \'\' ? null : $capacity, $scope)'],
            'src/Admin/FrontsVisionAdmin.php'=>['[field_references]','\'field_references\' => !empty($row[\'field_references\']),'],
            'src/Forms/FormRenderer.php'=>['fd.append(\'action\',\'fieldflow_fronts_vision_reference\')','data-vision-ref-camera','data-vision-references="1"','function rpVisionSendReference(st,key,blob){','data-fronts-input','data-share-space-pr'],
            'src/Cloud/Phase6StableRelease.php'=>['public const SOURCE_RELEASE = \'2.2.612\'','public const STABLE_RELEASE = \'2.2.613\'','// public const SOURCE_RELEASE = \'2.2.611\'','// public const STABLE_RELEASE = \'2.2.612\''],
            'src/Support/ReleasePackageValidator.php'=>['catalog_general_identity_9cp_ok','Catalog General Identity 9CP'],
            'src/Admin/ReleaseManager.php'=>['packageResult[\'catalog_general_identity_9cp_ok\']','CATÁLOGO GERAL OK'],
            'tools/verify-catalog-general-identity-9cp.php'=>['CATALOG GENERAL IDENTITY 9CP CONTRACT OK','successor=>=2.2.613'],
            'tools/test-catalog-general-identity-9cp.php'=>['CATALOG GENERAL IDENTITY 9CP RUNTIME OK','identity=1','reorganised=1','idempotent=1','suggestions=1','conflict_guard=1','manual_preserved=1','field_reference=1','guarded=1','no_schema=1','historical_continuity=1'],
            'PHASE-9CP-CATALOG-GENERAL-IDENTITY.md'=>['fieldflow-catalog-general-identity-v1','`SOURCE_RELEASE` is `2.2.612` and `STABLE_RELEASE` is `2.2.613`','fieldflow-fronts-field-reference-v1','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $catalogGeneralIdentity9cpForbidden = [
            'src/Catalog/ProductCatalogService.php'=>['wp_insert_attachment','CREATE TABLE'],
            'src/Vision/FrontsVisionService.php'=>['wp_ajax_nopriv','CREATE TABLE'],
        ];
        $result['catalog_general_identity_9cp_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.613', '>=');
        foreach ($catalogGeneralIdentity9cpRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['catalog_general_identity_9cp_ok'] = false;
        }
        foreach ($catalogGeneralIdentity9cpForbidden as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw,$blocked) !== false) $result['catalog_general_identity_9cp_ok'] = false;
        }
        if (!$result['catalog_general_identity_9cp_ok']) {
            $result['error'] = 'Catalog General Identity 9CP is incomplete.';
            return $result;
        }

        // 2.2.614+ Front Theme Camera Live 9CQ.
        $frontThemeCameraLive9cqRequirements = [
            'fieldflow.php'=>['Version: 2.2.614','define(\'FIELDFLOW_VERSION\', \'2.2.614\')','9CQ predecessor marker: define(\'FIELDFLOW_VERSION\', \'2.2.613\')'],
            'src/Support/ReleaseGate.php'=>['FRONT_THEME_CAMERA_LIVE_9CQ_CONTRACT','checkFrontThemeCameraLive9CQContract'],
            'src/Front/ThemeIsolation.php'=>['fieldflow-theme-isolation-v1','add_action(\'wp_enqueue_scripts\', [self::class, \'enqueue\'], 9999);','if (self::dependsOn($styles, $dependency, $handle, [])) continue;','.rp-form-ui button.routespro-form-btn:is(:hover,:focus,:active)'],
            'src/Vision/FrontsVisionService.php'=>['fieldflow-fronts-vision-question-switch-v1','fieldflow-fronts-vision-live-count-v1','public static function questionBlockReason(array $question, array $context, int $clientId = 0): string {','$photo = $capture === \'live\' ? self::livePhoto($_FILES[\'photo\'] ?? null) : self::storePhoto($_FILES[\'photo\'] ?? null);','is_uploaded_file($path)','self::rateLimit($userId);'],
            'src/Catalog/ProductCatalogService.php'=>['fieldflow-catalog-insignia-name-variants-v1','public const IDENTITY_VERSION = 3;','// 9CP marker: public const IDENTITY_VERSION = 2;','public static function variantScore(array $a, array $b): float {','$result[\'variants\'] = self::joinInsigniaVariants($list, $find, $union);'],
            'src/Admin/ProductCatalog.php'=>['add_action(\'admin_post_fieldflow_catalog_export\', [self::class, \'handle_export\']);','check_admin_referer(\'fieldflow_catalog_export_\' . $clientId);','$rebuilt = Catalog::syncFromCardex($clientId);'],
            'src/Admin/FrontsVisionAdmin.php'=>['\'questions\' => \'Nas perguntas com a opção ligada\'','\'mode\' => sanitize_key((string)($row[\'mode\'] ?? \'questions\')),'],
            'src/Forms/FormRenderer.php'=>['function rpVisionLiveStart(){','data-vision-live-now','fd.append(\'capture\',capture||\'camera\')','function rpFrontsDecorateDynamicRow($row,$box){','private static function fronts_vision_admin_hint(array $q, array $context, string $attrs): string {','data-fronts-input','data-share-space-pr'],
            'src/Support/Loader.php'=>['\'src/Front/ThemeIsolation.php\''],
            'src/Architecture/RuntimeRegistry.php'=>['\\RoutesPro\\Front\\ThemeIsolation::registerHooks();'],
            'src/Cloud/Phase6StableRelease.php'=>['public const SOURCE_RELEASE = \'2.2.613\'','public const STABLE_RELEASE = \'2.2.614\'','// public const SOURCE_RELEASE = \'2.2.612\'','// public const STABLE_RELEASE = \'2.2.613\''],
            'src/Support/ReleasePackageValidator.php'=>['front_theme_camera_live_9cq_ok','Front Theme Camera Live 9CQ'],
            'src/Admin/ReleaseManager.php'=>['packageResult[\'front_theme_camera_live_9cq_ok\']','FRONT E CÂMARA AO VIVO OK'],
            'tools/verify-front-theme-camera-live-9cq.php'=>['FRONT THEME CAMERA LIVE 9CQ CONTRACT OK','successor=>=2.2.614'],
            'tools/test-front-theme-camera-live-9cq.php'=>['FRONT THEME CAMERA LIVE 9CQ RUNTIME OK','insignia_variants=1','question_switch=1','client_modes=1','live_without_photo=1','theme_isolation=1','front_fixes=1','guarded=1','no_schema=1','historical_continuity=1'],
            'PHASE-9CQ-FRONT-THEME-CAMERA-LIVE.md'=>['fieldflow-theme-isolation-v1','`SOURCE_RELEASE` is `2.2.613` and `STABLE_RELEASE` is `2.2.614`','fieldflow-fronts-vision-live-count-v1','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $frontThemeCameraLive9cqForbidden = [
            'src/Vision/FrontsVisionService.php'=>['wp_ajax_nopriv','CREATE TABLE'],
            'src/Front/ThemeIsolation.php'=>['#c36'],
            'src/Catalog/ProductCatalogService.php'=>['wp_insert_attachment','CREATE TABLE'],
        ];
        $result['front_theme_camera_live_9cq_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.614', '>=');
        foreach ($frontThemeCameraLive9cqRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['front_theme_camera_live_9cq_ok'] = false;
        }
        foreach ($frontThemeCameraLive9cqForbidden as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw,$blocked) !== false) $result['front_theme_camera_live_9cq_ok'] = false;
        }
        if (!$result['front_theme_camera_live_9cq_ok']) {
            $result['error'] = 'Front Theme Camera Live 9CQ is incomplete.';
            return $result;
        }

        // 2.2.615+ Media Capture Resilience 9CR.
        $mediaCaptureResilience9crRequirements = [
            'fieldflow.php'=>['Version: 2.2.615','define(\'FIELDFLOW_VERSION\', \'2.2.615\')','9CR predecessor marker: define(\'FIELDFLOW_VERSION\', \'2.2.614\')'],
            'src/Support/ReleaseGate.php'=>['MEDIA_CAPTURE_RESILIENCE_9CR_CONTRACT','checkMediaCaptureResilience9CRContract'],
            'src/Forms/FormRenderer.php'=>['fieldflow-media-capture-resilience-v1','function rpMediaOpener(){var saved=rpMediaGet(\'opener\');if(saved===\'click\'||saved===\'picker\')return saved;return rpMediaIsNativeApp()?\'click\':\'picker\';}','function rpMediaCheckRetap(zone,source,origin){','rpMediaReport(\'media_picker_no_signal\'','rpMediaSet(\'camera\',\'native\');','rpMediaGet(\'camera\')===\'native\'','document.addEventListener(\'pointerup\',rpMediaReviveTrigger,true);','rpMediaReport(\'media_input_disabled\'','rpMediaNoteChange(this,$zone);','offline.submitForm.__rpMediaWatch','rpCheckpointExternalMediaHandoff($(this),source);','data-rp-upload-trigger="camera"','data-rp-upload-trigger="gallery"','if(input.dataset.rpOptimisedPass!==\'1\') rpMediaNoteChange(input,$(input).closest(\'[data-rp-upload-zone]\'));'],
            'src/Forms/Forms.php'=>['data-rp-media-diag-endpoint="<?php echo esc_url(rest_url(\'fieldflow-mobile/v1/diagnostics/client-event\')); ?>"','data-rp-media-diag-nonce="<?php echo esc_attr(wp_create_nonce(\'wp_rest\')); ?>"'],
            'src/Mobile/StabilityController.php'=>['\'/diagnostics/client-event\'','\'media_error\'','\'permission_callback\' => [$this, \'authenticated\']','$context[\'detail\'] = substr(sanitize_textarea_field($body[\'detail\']), 0, 600);'],
            'src/Cloud/Phase6StableRelease.php'=>['public const SOURCE_RELEASE = \'2.2.614\'','public const STABLE_RELEASE = \'2.2.615\'','// public const SOURCE_RELEASE = \'2.2.613\'','// public const STABLE_RELEASE = \'2.2.614\''],
            'src/Support/ReleasePackageValidator.php'=>['media_capture_resilience_9cr_ok','Media Capture Resilience 9CR'],
            'src/Admin/ReleaseManager.php'=>['packageResult[\'media_capture_resilience_9cr_ok\']','FOTOGRAFIAS RESILIENTES OK'],
            'tools/verify-media-capture-resilience-9cr.php'=>['MEDIA CAPTURE RESILIENCE 9CR CONTRACT OK','successor=>=2.2.615'],
            'tools/test-media-capture-resilience-9cr.php'=>['MEDIA CAPTURE RESILIENCE 9CR RUNTIME OK','openers=1','retap=1','camera_fallback=1','disabled_guard=1','diagnostics=1','submit_watch=1','historical_continuity=1'],
            'PHASE-9CR-MEDIA-CAPTURE-RESILIENCE.md'=>['fieldflow-media-capture-resilience-v1','`SOURCE_RELEASE` is `2.2.614` and `STABLE_RELEASE` is `2.2.615`','No database schema changes','No FlutterFlow source modification is required'],
        ];
        $mediaCaptureResilience9crForbidden = [
            'src/Forms/FormRenderer.php'=>['try{if(typeof input.showPicker===\'function\'){input.showPicker();return;}}catch(_){ }'],
        ];
        $result['media_capture_resilience_9cr_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.615', '>=');
        foreach ($mediaCaptureResilience9crRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['media_capture_resilience_9cr_ok'] = false;
        }
        foreach ($mediaCaptureResilience9crForbidden as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $blocked) if ($raw !== '' && strpos($raw,$blocked) !== false) $result['media_capture_resilience_9cr_ok'] = false;
        }
        if (!$result['media_capture_resilience_9cr_ok']) {
            $result['error'] = 'Media Capture Resilience 9CR is incomplete.';
            return $result;
        }

        // 2.2.616+ Safe Multimodal Fronts 9CS. No new schema.
        $frontsSafeMultimodal9csRequirements = [
            'src/Support/ReleaseGate.php'=>['FRONTS_SAFE_MULTIMODAL_9CS_CONTRACT', 'checkFrontsSafeMultimodal9CSContract'],
            'src/Vision/VisionUsageGuard.php'=>['fieldflow-vision-usage-reservation-v1', 'SELECT GET_LOCK(%s, 3)', 'VisionUsageGuard'],
            'src/Vision/FrontsVisionService.php'=>['fieldflow-fronts-safe-multimodal-9cs-v1','VisionUsageGuard::reserve($clientId, $settings)', "['key' => '_ff_project_id'", 'assertQuestionScope(', "'fronts' => ", 'self::livePhoto('],
            'src/Forms/FormRenderer.php'=>['data-vision-manual="select"', 'data-vision-manual="text"', 'data-vision-manual="voice"', 'function rpVisionManualTarget(st,row){', 'function rpVisionParseLines(text,rows){'],
            'src/Admin/FrontsVisionAdmin.php'=>['monthly_budget_cents','reservation_cents','VisionUsageGuard::summary('],
            'src/Support/Loader.php'=>['src/Vision/VisionUsageGuard.php'],
            'PHASE-9CS-MULTIMODAL-SAFE-COUNTING.md'=>['fieldflow-fronts-safe-multimodal-9cs-v1','2.2.615 -> 2.2.616'],
            'tools/test-fronts-multimodal-9cs.mjs'=>['FRONTS 9CS TEXT AND MULTIMODAL OK'],
        ];
        $result['fronts_safe_multimodal_9cs_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.616', '>=');
        foreach ($frontsSafeMultimodal9csRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['fronts_safe_multimodal_9cs_ok'] = false;
        }
        if (!$result['fronts_safe_multimodal_9cs_ok']) {
            $result['error'] = 'Safe Multimodal Fronts 9CS is incomplete.';
            return $result;
        }

        // 2.2.617+ 9CT. Destructive controls in the field form require explicit confirmation.
        $formDeletion9ctRequirements = [
            'src/Support/ReleaseGate.php'=>['FORM_DELETION_CONFIRMATION_9CT_CONTRACT','checkFormDeletionConfirmation9CTContract'],
            'src/Forms/FormRenderer.php'=>['function rpConfirmFormDeletion(options){',"'data-rp-delete-confirm'",'current.lastModified!==file.lastModified',"if(pending){toggleExisting();return;}",'.routespro-delete-confirm-overlay{',"$(document).on('click','[data-rp-upload-preview-remove]'", "$(document).on('click','[data-rp-upload-remove]'", "$(document).on('click','[data-rp-remove-existing-media]'", "$(document).on('click','.routespro-product-remove'", "\$form.on('click','[data-rp-discard-draft]'"],
            'PHASE-9CT-FORM-DELETION-CONFIRMATION.md'=>['fieldflow-form-deletion-confirmation-9ct-v1','2.2.616 -> 2.2.617'],
            'tools/test-form-delete-confirm-9ct.mjs'=>['FORM DELETION 9CT OK'],
        ];
        $result['form_deletion_confirmation_9ct_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.617', '>=');
        foreach ($formDeletion9ctRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required) === false) $result['form_deletion_confirmation_9ct_ok'] = false;
        }
        if (!$result['form_deletion_confirmation_9ct_ok']) {
            $result['error'] = 'Form Deletion Confirmation 9CT is incomplete.';
            return $result;
        }

        // 2.2.618+ 9CU. A user-approved capture is required before paid AI.
        $frontsCaptureReview9cuRequirements = [
            'src/Support/ReleaseGate.php'=>['FRONTS_CAPTURE_REVIEW_9CU_CONTRACT', 'checkFrontsCaptureReview9CUContract'],
            'src/Vision/FrontsVisionService.php'=>['fieldflow-fronts-capture-review-9cu-v1', 'captureReplayKey(', 'SELECT GET_LOCK(%s, 0)', "'review_reason'", 'VisionUsageGuard::reserve($clientId, $settings)'],
            'src/Forms/FormRenderer.php'=>['fieldflow-fronts-capture-review-9cu-v1', 'function rpVisionReviewCapture(', 'function rpVisionApproveAnalysis(', 'function rpVisionAssessCapture(', 'data-vision-analyze', 'data-vision-select-product', "rpVisionReviewCapture(st,blob,'live')"],
            'tools/test-fronts-capture-review-9cu.php'=>['FRONTS CAPTURE REVIEW 9CU PHP OK'],
            'tools/test-fronts-capture-review-9cu.mjs'=>['FRONTS CAPTURE REVIEW 9CU JS OK'],
            'PHASE-9CU-FRONTS-CAPTURE-REVIEW.md'=>['fieldflow-fronts-capture-review-9cu-v1','2.2.617 -> 2.2.618'],
        ];
        $result['fronts_capture_review_9cu_ok'] = version_compare((string)($result['version'] ?? ''), '2.2.618', '>=');
        foreach ($frontsCaptureReview9cuRequirements as $relative=>$markers) {
            $raw = self::entryContents($entries, 'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw, $required) === false) $result['fronts_capture_review_9cu_ok'] = false;
        }
        if (!$result['fronts_capture_review_9cu_ok']) {
            $result['error'] = 'Fronts Capture Review 9CU is incomplete.';
            return $result;
        }

        // 2.2.619+ 9CV. Fail safely if WordPress or database cannot serve the store form.
        $contextRecovery9cvRequirements = [
            'src/Support/ReleaseGate.php'=>['REPORT_CONTEXT_RECOVERY_9CV_CONTRACT','checkReportContextRecovery9CVContract'],
            'src/Shortcodes.php'=>['fieldflow-report-context-recovery-9cv-v1','isReportDatabaseError=raw=>','id="rpf-retry"',"showReportRetry('form')","showReportRetry('visits')",'matchesCachedContext=cached=>',
                'isOwnCachedForm=cached=>',"if(!sameLiveForm)els.slot.innerHTML=''",'reportRetryLastAt<4000'],
            'src/Forms/Forms.php'=>['fieldflow-report-context-recovery-9cv-v1','fieldflow_report_context_unavailable','], 503);'],
            'tools/test-report-context-recovery-9cv.mjs'=>['REPORT CONTEXT RECOVERY 9CV JS OK'],
            'PHASE-9CV-REPORT-CONTEXT-RECOVERY.md'=>['fieldflow-report-context-recovery-9cv-v1','2.2.618 -> 2.2.619'],
        ];
        $result['report_context_recovery_9cv_ok']=version_compare((string)($result['version']??''),'2.2.619','>=');
        foreach ($contextRecovery9cvRequirements as $relative=>$markers) {
            $raw=self::entryContents($entries,'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required)===false) $result['report_context_recovery_9cv_ok']=false;
        }
        if (!$result['report_context_recovery_9cv_ok']) {
            $result['error']='Report Context Recovery 9CV is incomplete.';
            return $result;
        }

        // 2.2.620+ 9CW. A positive SKU requires physical and textual evidence.
        $visualEvidence9cwRequirements = [
            'src/Support/ReleaseGate.php'=>['FRONTS_VISUAL_EVIDENCE_9CW_CONTRACT','checkFrontsVisualEvidence9CWContract'],
            'src/Vision/FrontsVisionService.php'=>['fieldflow-fronts-visual-evidence-9cw-v1','variantDiscriminators(', 'evidenceBoxesOverlap(', 'evidenceVariantMatches(', "'capacity_unverified'", "'brand_unverified'", "'duplicate_position'", "'model_fronts'", '$prompt = self::blindInventoryPrompt();', 'self::VISUAL_EVIDENCE_CONTRACT,', "'max_attempts' => 1", 'VisionUsageGuard::reserve($clientId, $settings)'],
            'src/Forms/FormRenderer.php'=>['function rpVisionReviewMessage(it)','variant_unverified','capacity_unverified','duplicate_position','rpVisionReviewMessage(it)',"st.values[it.key]=it.needs_review"],
            'tools/test-fronts-visual-evidence-9cw.php'=>['FRONTS VISUAL EVIDENCE 9CW PHP OK','black_barrel_phantom_is_held'],
            'tools/test-fronts-visual-evidence-9cw.mjs'=>['FRONTS VISUAL EVIDENCE 9CW JS OK'],
            'PHASE-9CW-FRONTS-VISUAL-EVIDENCE.md'=>['fieldflow-fronts-visual-evidence-9cw-v1','2.2.619 -> 2.2.620'],
        ];
        $result['fronts_visual_evidence_9cw_ok']=version_compare((string)($result['version']??''),'2.2.620','>=');
        foreach ($visualEvidence9cwRequirements as $relative=>$markers) {
            $raw=self::entryContents($entries,'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required)===false) $result['fronts_visual_evidence_9cw_ok']=false;
        }
        if (!$result['fronts_visual_evidence_9cw_ok']) {
            $result['error']='Fronts Visual Evidence 9CW is incomplete.';
            return $result;
        }

        // 2.2.621+ 9CX. Recover the actual Jameson identity from prefixed Cardex labels.
        $skuIdentity9cxRequirements = [
            'src/Support/ReleaseGate.php'=>['FRONTS_SKU_IDENTITY_9CX_CONTRACT','checkFrontsSkuIdentity9CXContract'],
            'src/Vision/FrontsVisionService.php'=>['fieldflow-fronts-sku-identity-9cx-v1','evidenceCapacityMatches(','IRLANDES',"'capacity_unverified'",'foreach (array_keys($collisions) as $i)',"'max_attempts' => 1"],
            'src/Forms/FormRenderer.php'=>['routespro-vision-photo-inner','routespro-vision-box.is-review','data-vision-accept',"['capacity_unverified','low_confidence']",'st.values[it.key]=it.needs_review'],
            'tools/test-fronts-sku-identity-9cx.php'=>['FRONTS SKU IDENTITY 9CX PHP OK','real_jameson_original_detected'],
            'tools/test-fronts-sku-identity-9cx.mjs'=>['FRONTS SKU IDENTITY 9CX JS OK'],
            'PHASE-9CX-FRONTS-SKU-IDENTITY-RECOVERY.md'=>['fieldflow-fronts-sku-identity-9cx-v1','2.2.620 -> 2.2.621'],
        ];
        $result['fronts_sku_identity_9cx_ok']=version_compare((string)($result['version']??''),'2.2.621','>=');
        foreach ($skuIdentity9cxRequirements as $relative=>$markers) {
            $raw=self::entryContents($entries,'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required)===false) $result['fronts_sku_identity_9cx_ok']=false;
        }
        if (!$result['fronts_sku_identity_9cx_ok']) {
            $result['error']='Fronts SKU Identity Recovery 9CX is incomplete.';
            return $result;
        }

        // 2.2.622+ 9CY: shelf observations independent of SKU shortlist.
        $blind9cyRequirements = [
            'src/Support/ReleaseGate.php'=>['FRONTS_BLIND_INVENTORY_9CY_CONTRACT','checkFrontsBlindInventory9CYContract'],
            'src/Vision/FrontsVisionService.php'=>['fieldflow-fronts-blind-inventory-9cy-v1','blindInventoryPrompt(',
                'parseBlindInventory(','containsObservedTokens(','$prompt = self::blindInventoryPrompt();',
                '$result = self::parseBlindInventory($raw, $items);',"'max_attempts' => 1"],
            'src/Forms/FormRenderer.php'=>['variant_manual_verification','res.vision_mode'],
            'tools/test-fronts-blind-inventory-9cy.php'=>['FRONTS BLIND INVENTORY 9CY PHP OK','carina_never_assigned_black_barrel'],
            'tools/test-fronts-blind-inventory-9cy.mjs'=>['FRONTS BLIND INVENTORY 9CY JS OK'],
            'PHASE-9CY-FRONTS-BLIND-INVENTORY.md'=>['fieldflow-fronts-blind-inventory-9cy-v1','2.2.621 -> 2.2.622'],
        ];
        $result['fronts_blind_inventory_9cy_ok']=version_compare((string)($result['version']??''),'2.2.622','>=');
        foreach ($blind9cyRequirements as $relative=>$markers) {
            $raw=self::entryContents($entries,'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required)===false) $result['fronts_blind_inventory_9cy_ok']=false;
        }
        if (!$result['fronts_blind_inventory_9cy_ok']) {
            $result['error']='Fronts Blind Inventory 9CY is incomplete.';
            return $result;
        }

        // 2.2.623+ 9CZ: provider usage and per-model EUR pricing, no paid provider call added.
        $cost9czRequirements = [
            'src/Support/ReleaseGate.php'=>['FRONTS_AI_COST_METERING_9CZ_CONTRACT', 'checkFrontsAiCostMetering9CZContract'],
            'src/Vision/VisionCostMeter.php'=>['fieldflow-fronts-ai-usage-finops-9cz-v1', 'public static function quote(',
                'public static function saveRate(', 'public static function monthByClient(', "'tariff_missing'", "'unmetered'"],
            'src/Services/AI.php'=>['visionMeteredUsage(', 'lastVisionReceipt()', "'vision_cost_status'", "'vision_finops'", 'getLastUsage()'],
            'src/Vision/FrontsVisionService.php'=>['VisionUsageGuard::reserve($clientId, $settings)',
                "'_ff_ai_usage_log_id'", "'_ff_ai_estimated_cost_eur'"],
            'src/Admin/FrontsVisionAdmin.php'=>['handle_tariffs()', 'VisionCostMeter::monthByClient()',
                'FinOps de IA', 'Reserva por tentativa'],
            'tools/test-fronts-cost-metering-9cz.php'=>['FRONTS AI COST METERING 9CZ PHP OK','same_safety_gate_one_attempt'],
            'tools/test-fronts-ai-runtime-metering-9cz.php'=>['FRONTS AI RUNTIME METERING 9CZ PHP OK','no_extra_paid_call','image_tokens_from_azure'],
            'PHASE-9CZ-FRONTS-AI-TRANSPARENT-COSTS.md'=>['fieldflow-fronts-ai-usage-finops-9cz-v1','2.2.622 -> 2.2.623'],
        ];
        $result['fronts_ai_cost_metering_9cz_ok']=version_compare((string)($result['version']??''),'2.2.623','>=');
        foreach ($cost9czRequirements as $relative=>$markers) {
            $raw=self::entryContents($entries,'fieldflow/'.$relative);
            foreach ($markers as $required) if (strpos($raw,$required)===false) $result['fronts_ai_cost_metering_9cz_ok']=false;
        }
        if (!$result['fronts_ai_cost_metering_9cz_ok']) {
            $result['error']='Fronts AI Cost Metering 9CZ is incomplete.';
            return $result;
        }

        // 2.2.624 9D0: pre-call reservation, conservative settlement and explicit legacy audit.
        $reconcile9d0 = [
            'src/Support/ReleaseGate.php'=>['FRONTS_BUDGET_RECONCILIATION_9D0_CONTRACT','checkFrontsBudgetReconciliation9D0Contract'],
            'src/Vision/VisionUsageGuard.php'=>['fieldflow-fronts-budget-reconciliation-9d0-v1',
                'public static function settle(', 'public static function reconcileLegacy(', 'pending_reservations',
                'SELECT GET_LOCK(%s, 3)', 'ESTIMATE_SAFETY_FACTOR'],
            'src/Vision/FrontsVisionService.php'=>['VisionUsageGuard::reserve($clientId, $settings)', 'VisionUsageGuard::settle('],
            'src/Admin/FrontsVisionAdmin.php'=>['handle_reconcile_legacy()', 'render_legacy_reconciliation(', 'fieldflow_vision_reconcile_legacy'],
            'src/Forms/FormRenderer.php'=>['data-vision-block-reason','rpVisionEsc(cameraReason)'],
            'tools/test-fronts-budget-reconciliation-9d0.php'=>['FRONTS BUDGET RECONCILIATION 9D0 PHP OK','idempotent_settlement','historic_review_only_once'],
            'tools/test-fronts-budget-availability-9d0.mjs'=>['FRONTS BUDGET AVAILABILITY 9D0 JS OK','rpVisionEsc(cameraReason)'],
            'PHASE-9D0-FRONTS-BUDGET-RECONCILIATION.md'=>['fieldflow-fronts-budget-reconciliation-9d0-v1','2.2.623 -> 2.2.624'],
        ];
        $result['fronts_budget_reconciliation_9d0_ok']=version_compare((string)($result['version']??''),'2.2.624','>=');
        foreach ($reconcile9d0 as $relative=>$markers) {
            $raw=self::entryContents($entries,'fieldflow/'.$relative);
            foreach ($markers as $marker) if (strpos($raw,$marker)===false) $result['fronts_budget_reconciliation_9d0_ok']=false;
        }
        if (!$result['fronts_budget_reconciliation_9d0_ok']) {
            $result['error']='Fronts Budget Reconciliation 9D0 is incomplete.';
            return $result;
        }

        // Order-suggestion email is a side effect and must fail closed when a
        // conditional branch is inactive. Release Manager validates both the
        // persisted-answer veto and the no-retry automatic dispatch guard.
        $submissionsRaw = self::entryContents($entries, 'fieldflow/src/Admin/FormSubmissions.php');
        $automationsRaw = self::entryContents($entries, 'fieldflow/src/Admin/ReportAutomations.php');
        $eligibilityVerifierRaw = self::entryContents($entries, 'fieldflow/tools/verify-order-suggestion-mail-eligibility.php');
        $result['order_suggestion_mail_eligibility_ok'] = $submissionsRaw !== ''
            && strpos($submissionsRaw, 'resolveOrderSuggestionMailEligibility') !== false
            && strpos($submissionsRaw, 'canonicalOrderSuggestionKeysFromPersistedAnswers') !== false
            && strpos($submissionsRaw, 'conditional_disagreement_inactive') !== false
            && $automationsRaw !== ''
            && strpos($automationsRaw, 'FormSubmissions::orderSuggestionMailEligibility($submissionId)') !== false
            && strpos($automationsRaw, 'Sem envio: a Sugestão de encomenda estava inativa') !== false
            && $eligibilityVerifierRaw !== ''
            && strpos($eligibilityVerifierRaw, 'fieldflow-order-suggestion-mail-eligibility-v2') !== false
            && strpos($eligibilityVerifierRaw, 'persisted_no_vetoes_stale_active_snapshot') !== false;
        if (!$result['order_suggestion_mail_eligibility_ok']) {
            $result['error'] = 'O guard de elegibilidade do email de Sugestão de encomenda não está integralmente certificado na release.';
            return $result;
        }

        $manifest = json_decode($manifestRaw, true);
        if (!is_array($manifest)) {
            $result['error'] = 'production-manifest.json não contém JSON válido.';
            return $result;
        }
        $result['manifest_version'] = sanitize_text_field((string)($manifest['version'] ?? ''));
        $result['manifest_file_count'] = max(0, (int)($manifest['file_count'] ?? 0));
        $result['manifest_expected_aggregate'] = strtolower(trim((string)($manifest['aggregate_sha256'] ?? '')));
        if ($result['manifest_version'] === '' || !hash_equals($result['version'], $result['manifest_version'])) {
            $result['error'] = 'A versão do production manifest não coincide com a release.';
            return $result;
        }

        ksort($files, SORT_STRING);
        $aggregate = '';
        foreach ($files as $relative => $meta) {
            $aggregate .= $relative . "\t" . strtolower((string)$meta['sha256']) . "\t" . (int)$meta['bytes'] . "\n";
        }
        $result['manifest_actual_aggregate'] = hash('sha256', $aggregate);

        $manifestFiles = [];
        foreach ((array)($manifest['files'] ?? []) as $manifestKey => $entry) {
            if (!is_array($entry)) continue;
            // Certified manifests use path => metadata. A short-lived 2.2.394/
            // 2.2.395 build pipeline emitted a list of {path,sha256,bytes}.
            $relative = is_string($manifestKey)
                ? self::normaliseRelative($manifestKey)
                : self::normaliseRelative((string)($entry['path'] ?? ''));
            if ($relative === '' || $relative === 'production-manifest.json') continue;
            $manifestFiles[$relative] = [
                'sha256'=>strtolower((string)($entry['sha256'] ?? '')),
                'bytes'=>(int)($entry['bytes'] ?? -1),
            ];
        }

        foreach ($manifestFiles as $relative => $expected) {
            $actual = $files[$relative] ?? null;
            if (!$actual || !hash_equals((string)$expected['sha256'], strtolower((string)$actual['sha256'])) || (int)$expected['bytes'] !== (int)$actual['bytes']) {
                $result['manifest_mismatches'][] = $relative;
            }
        }
        foreach ($files as $relative => $_meta) {
            if (!isset($manifestFiles[$relative])) $result['manifest_mismatches'][] = $relative;
        }
        $result['manifest_mismatches'] = array_values(array_unique($result['manifest_mismatches']));

        $countOk = $result['manifest_file_count'] === $result['file_count'];
        $hashOk = preg_match('/^[a-f0-9]{64}$/', $result['manifest_expected_aggregate'])
            && hash_equals($result['manifest_expected_aggregate'], $result['manifest_actual_aggregate']);
        $result['manifest_ok'] = $countOk && $hashOk && !$result['manifest_mismatches'];
        if (!$result['manifest_ok']) {
            $result['error'] = 'O conteúdo do ZIP não corresponde integralmente ao production manifest.';
            return $result;
        }
        // 9DH: immutable ZIP evidence must also prove every mandatory key_file.
        // 2.2.641 passed the prior aggregate check with four stale 2.2.640 names;
        // reject this regression before Azure publication, not after install.
        if (version_compare((string)$result['version'], '2.2.642', '>=')) {
            $requiredKeyFiles = $manifest['key_files'] ?? null;
            if (!is_array($requiredKeyFiles) || !$requiredKeyFiles) {
                $result['error'] = '9DH: key_files em falta ou inválidos no manifesto.';
                return $result;
            }
            $visitedKeyFiles = [];
            foreach ($requiredKeyFiles as $key => $value) {
                $relative = is_int($key) && is_string($value)
                    ? self::normaliseRelative($value)
                    : self::normaliseRelative((string)$key);
                if ($relative === '' || $relative === 'production-manifest.json'
                    || isset($visitedKeyFiles[$relative])
                    || !isset($manifestFiles[$relative]) || !isset($files[$relative])) {
                    $result['error'] = '9DH: key_files inválidos ou obsoletos: ' . $relative;
                    return $result;
                }
                $visitedKeyFiles[$relative] = true;
            }
            foreach (['fieldflow-offline-runtime','fieldflow-frontend-core','fieldflow-react-front','fieldflow-react-backoffice'] as $stem) {
                $required = 'assets/' . $stem . '-' . $result['version'] . '.js';
                if (!isset($visitedKeyFiles[$required])) {
                    $result['error'] = '9DH: bundle obrigatório ausente em key_files: ' . $required;
                    return $result;
                }
            }
            $result['manifest_key_files_integrity_9dh_ok'] = true;
        }

        // 9DA: authenticated against the ZIP above, used only for the read-only file diff.
        $result['manifest_file_metadata'] = $manifestFiles;

        // 9D0A runtime 9CS persistence contract retained as historical protection.
        // The 2.2.624.1 attempt was rejected by Azure publisher (four segments).
        // 2.2.625 is the publisher-compatible recovery; no extra AI calls.
        if (version_compare((string)($result['version'] ?? ''),'2.2.624.1','>=')) {
            $compat = [
                'src/Vision/VisionUsageGuard.php' => [
                    'The historic statement was: if (!update_option($key, $new, false))',
                    'if (!update_option($key, $row, false))',
                    'self::persist($key, $row);',
                    'SELECT GET_LOCK(%s, 3)',
                ],
                'src/Support/ReleaseGate.php' => [
                    "'if (!update_option(\$key, \$row, false))'",
                    "'self::persist(\$key, \$row);'",
                    'checkFrontsSafeMultimodal9CSContract',
                ],
                'tools/test-fronts-certification-recovery-9d0a.php' => [
                    'FRONTS CERTIFICATION RECOVERY 9D0A PHP OK',
                    'checkFrontsSafeMultimodal9CSContract',
                ],
                'PHASE-9D0A-FRONTS-9CS-CERTIFICATION-HOTFIX.md' => [
                    'Certified predecessor:',
                    '2.2.624.1',
                ],
            ];
            $result['fronts_certification_recovery_9d0a_ok']=true;
            foreach($compat as $relative=>$markers) {
                $raw=self::entryContents($entries,'fieldflow/'.$relative);
                foreach($markers as $marker) if (strpos($raw,$marker)===false) $result['fronts_certification_recovery_9d0a_ok']=false;
            }
            if (!$result['fronts_certification_recovery_9d0a_ok']) {
                $result['error']='9D0A 9CS Certification Recovery is incomplete.';
                $result['ok']=false;
                return $result;
            }
        }
        if (version_compare((string)($result['version'] ?? ''), '2.2.625', '>=')) {
            $contract = [
                'src/Cloud/ReleasePublisherClient.php' => [
                    '9D0B: Azure publisher supports exactly three numeric segments',
                    'Versao rejeitada antes do upload:',
                ],
                'azure-release-publisher-6b5/release-publisher-function/src/index.js' => [
                    'function validateVersion(value)',
                    'Invalid version or channel.',
                ],
                'tools/test-publisher-version-9d0b.mjs' => [
                    'PUBLISHER VERSION CONTRACT 9D0B PASS',
                    '2.2.624.1',
                    '2.2.625',
                ],
                'tools/test-certification-publisher-recovery-9d0b.php' => [
                    'RELEASE RECOVERY 9D0B PHP PASS',
                    'checkFrontsSafeMultimodal9CSContract',
                ],
                'PHASE-9D0B-PRIVATE-PUBLISHER-RECOVERY.md' => [
                    '2.2.625',
                    '2.2.624.1',
                    '2.2.623',
                ],
            ];
            $result['private_publisher_version_9d0b_ok'] = true;
            foreach ($contract as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) {
                    if (strpos($raw, $marker) === false) $result['private_publisher_version_9d0b_ok'] = false;
                }
            }
            if (!$result['private_publisher_version_9d0b_ok']) {
                $result['error'] = '9D0B Private Publisher version contract is incomplete.';
                return $result;
            }
        }
        // 2.2.626 9D1: visual deduplication, explicit human confirmation,
        // previous confirmed closed visit in the same tenant/store/form/question only.
        if (version_compare((string)($result['version'] ?? ''), '2.2.626', '>=')) {
            $requirements9d1 = [
                'src/Support/ReleaseGate.php' => ['FRONTS_VISION_INTELLIGENCE_9D1_CONTRACT', 'checkFrontsVisionIntelligence9D1Contract'],
                'src/Vision/FrontsVisionService.php' => ['VISION_INTELLIGENCE_II_CONTRACT', 'evidenceBoxesStrongDuplicate(',
                    'compareConfirmedWithPrevious(', 'previousClosedVisit(', "'_ff_submission_id', 'value' => '0', 'compare' => '>'",
                    "'basis' => 'confirmed_closed_visit'", "'max_attempts' => 1"],
                'src/Forms/FormRenderer.php' => ['function rpVisionRefreshComparison()', 'data-vision-delta', 'data-vision-fill'],
                'src/Admin/FrontsVisionAdmin.php' => ['Visita fechada anterior'],
                'tools/test-fronts-vision-intelligence-9d1.php' => ['FRONTS VISION INTELLIGENCE 9D1 PHP OK', 'no_added_ai_calls'],
                'tools/test-fronts-gate-9d1.php' => ['FRONTS RELEASE GATE 9D1 PHP OK'],
                'tools/test-fronts-history-scope-9d1.php' => ['FRONTS HISTORY SCOPE 9D1 PHP OK', 'tenant_store_question_isolation'],
                'tools/test-fronts-vision-intelligence-9d1.mjs' => ['FRONTS VISION INTELLIGENCE 9D1 JS OK', 'no_extra_ai_calls'],
                'PHASE-9D1-FRONTS-VISION-INTELLIGENCE-II.md' => ['fieldflow-fronts-vision-intelligence-9d1-v1', '2.2.625 -> 2.2.626'],
            ];
            $result['fronts_vision_intelligence_9d1_ok'] = true;
            foreach ($requirements9d1 as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) {
                    if (strpos($raw, $marker) === false) $result['fronts_vision_intelligence_9d1_ok'] = false;
                }
            }
            if (!$result['fronts_vision_intelligence_9d1_ok']) {
                $result['error'] = '9D1 Vision Intelligence II contract is incomplete.';
                return $result;
            }
        }
        // 2.2.627 9D2: deterministic category associations, safe question carryover,
        // and a premium review panel for every execution question (not only Share Space).
        if (version_compare((string)($result['version'] ?? ''), '2.2.627', '>=')) {
            $requirements9d2 = [
                'src/Support/ReleaseGate.php' => ['BRIEFING_CATEGORY_CONTINUITY_9D2_CONTRACT', 'checkBriefingCategoryContinuity9D2Contract'],
                'src/Briefings/BriefingCategoryMappingService.php' => ['fieldflow-briefing-category-continuity-9d2-v1',
                    'inheritVerifiedQuestions(', 'sameProductEvidence(', 'saveManual(', 'synchronizeExisting(',
                    'decisionKey(', 'assign('],
                'src/Admin/Briefings.php' => ['CategoryMapping::inheritVerifiedQuestions(', 'product_category_identity', 'product_category_override'],
                'src/Admin/ProductCatalog.php' => ['render_question_categories(', 'handle_question_category()',
                    'handle_sync_categories()', 'ff-cat-qmap-row', 'ff-catalog-tabs'],
                'src/Forms/ContextQuestions.php' => ["'product_category' =>", "'product_category_source' =>"],
                'src/Front/BriefingsShortcodeRenderer.php' => ['ffb-exec-category', 'Categoria por confirmar'],
                'src/Forms/FormRenderer.php' => ['$help = \'Categoria: \' . $category'],
                'tools/test-briefing-category-9d2.php' => ['BRIEFING CATEGORY CONTINUITY 9D2 PHP PASS', 'changed SKU blocks inheritance'],
                'tools/test-briefing-gate-9d2.php' => ['BRIEFING RELEASE GATE 9D2 PHP PASS', 'checkBriefingCategoryContinuity9D2Contract'],
                'PHASE-9D2-BRIEFING-CATEGORY-CONTINUITY.md' => ['fieldflow-briefing-category-continuity-9d2-v1',
                    '2.2.626 -> 2.2.627', 'sem chamadas adicionais à IA'],
            ];
            $result['briefing_category_continuity_9d2_ok'] = true;
            foreach ($requirements9d2 as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) if (strpos($raw, $marker) === false) $result['briefing_category_continuity_9d2_ok'] = false;
            }
            if (!$result['briefing_category_continuity_9d2_ok']) {
                $result['error'] = '9D2 Briefing Category Continuity contract is incomplete.';
                return $result;
            }
        }
        // 2.2.628 9D3: catalog category dropdown, create new, persistent per-client taxonomy.
        if (version_compare((string)($result['version'] ?? ''), '2.2.628', '>=')) {
            $requirements9d3 = [
                'src/Support/ReleaseGate.php' => ['CATALOG_CATEGORY_PICKER_9D3_CONTRACT', 'checkCatalogCategoryPicker9D3Contract'],
                'src/Briefings/BriefingCategoryMappingService.php' => ['fieldflow-catalog-category-picker-9d3-v1',
                    'catalogCategories(', 'catalogCategorySelection(', 'rememberCatalogCategory(', 'clientCategoryOption9D3(',
                    'self::categories($clientId)', 'update_option($key, array_values($values), false)'],
                'src/Admin/ProductCatalog.php' => ['data-ff-cat-category-editor', 'name="category_choice"',
                    'name="category_new"', 'Adicionar nova categoria', 'CategoryMapping::catalogCategorySelection(',
                    'CategoryMapping::rememberCatalogCategory(', 'CategoryMapping::categories($clientId)'],
                'tools/test-catalog-category-picker-9d3.php' => ['CATALOG CATEGORY PICKER 9D3 PHP PASS', 'tenant isolation'],
                'tools/test-catalog-category-gate-9d3.php' => ['CATALOG CATEGORY RELEASE GATE 9D3 PHP PASS', 'checkCatalogCategoryPicker9D3Contract'],
                'PHASE-9D3-CATALOG-CATEGORY-PICKER.md' => ['fieldflow-catalog-category-picker-9d3-v1', '2.2.627 -> 2.2.628'],
            ];
            $result['catalog_category_picker_9d3_ok'] = true;
            foreach ($requirements9d3 as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) if (strpos($raw, $marker) === false) $result['catalog_category_picker_9d3_ok'] = false;
            }
            if (!$result['catalog_category_picker_9d3_ok']) {
                $result['error'] = '9D3 Catalog Category Picker contract is incomplete.';
                return $result;
            }
        }
        // 9D4: the successor must preserve authenticated cache boundaries and
        // paginated visit reports, including browser and WebView continuity.
        if (version_compare((string)($result['version'] ?? ''), '2.2.629', '>=')) {
            $requirements9d4 = [
                'src/Support/ReleaseGate.php' => ['COMMERCIAL_PRODUCTION_HARDENING_9D4_CONTRACT',
                    'checkCommercialProductionHardening9D4Contract'],
                'src/Rest/CommercialController.php' => ['Permissions::get_scope($uid)',
                    'COUNT(DISTINCT l.id)', 'LIMIT %d OFFSET %d', "'include_unassigned' =>"],
                'src/Rest/EventsController.php' => ["'offset' => min(10000000", "'with_total' =>"],
                'src/Front/ClientPortalShortcodeRenderer.php' => ["scoped.set('with_total','1')",
                    'requestSequence!==reportLoadSequence'],
                'src/Front/AppShortcodeRenderer.php' => ["lastReportVisitStorageKey='fieldflow:last-report-visit:v2:'"],
                'src/Mobile/WebViewBridge.php' => ['key.indexOf("fieldflow:last-report-visit:v2:")===0'],
                'tools/test-commercial-production-isolation-9d4.php' => ['COMMERCIAL PRODUCTION ISOLATION 9D4 PHP PASS'],
                'tools/test-commercial-report-pagination-9d4.php' => ['COMMERCIAL REPORT PAGINATION 9D4 PHP PASS'],
                'tools/test-commercial-production-gate-9d4.php' => ['COMMERCIAL PRODUCTION RELEASE GATE 9D4 PHP PASS'],
                'PHASE-9D4-COMMERCIAL-PRODUCTION-HARDENING.md' => [
                    'fieldflow-commercial-production-hardening-9d4-v1', '2.2.628 -> 2.2.629'],
            ];
            $result['commercial_production_hardening_9d4_ok'] = true;
            foreach ($requirements9d4 as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) if (strpos($raw, $marker) === false) {
                    $result['commercial_production_hardening_9d4_ok'] = false;
                }
            }
            if (!$result['commercial_production_hardening_9d4_ok']) {
                $result['error'] = '9D4 Commercial Production Hardening contract is incomplete.';
                return $result;
            }
        }
        // 9D5: packaging must retain the 2.2.629 contracts and include
        // the scoped visual assets and their own dedicated release gate.
        if (version_compare((string)($result['version'] ?? ''), '2.2.630', '>=')) {
            $requirements9d5 = [
                'src/Support/ReleaseGate.php' => [
                    'VISUAL_EXPERIENCE_CONSOLIDATION_9D5_CONTRACT',
                    'checkVisualExperienceConsolidation9D5Contract'],
                'src/Front/ResponsiveAssets.php' => [
                    'FIELDFLOW_VISUAL_CONSOLIDATION_9D5',
                    'fieldflow-visual-consolidation-9d5.css',
                    'fieldflow-visual-consolidation-9d5.js'],
                'src/Front/AppShortcodeRenderer.php' => ['ffv-visual-9d5'],
                'src/Front/ClientPortalShortcodeRenderer.php' => ['ffv-visual-9d5'],
                'assets/fieldflow-visual-consolidation-9d5.css' => [
                    '.ffv-dock-hidden', '.ffv-filters-open', '--ffv-dock-height'],
                'assets/fieldflow-visual-consolidation-9d5.js' => [
                    'fieldflow-visual-experience-consolidation-9d5-v1', 'aria-expanded', 'setupDock(root)'],
                'src/Support/LicenseManager.php' => ['get(?string $key = null, $default = null)'],
                'tools/test-visual-experience-9d5.php' => ['VISUAL EXPERIENCE CONSOLIDATION 9D5 PHP PASS'],
                'tools/test-visual-experience-gate-9d5.php' => ['VISUAL EXPERIENCE RELEASE GATE 9D5 PHP PASS'],
                'PHASE-9D5-VISUAL-EXPERIENCE-CONSOLIDATION.md' => [
                    'fieldflow-visual-experience-consolidation-9d5-v1', '2.2.629 -> 2.2.630'],
            ];
            $result['visual_experience_consolidation_9d5_ok'] = true;
            foreach ($requirements9d5 as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) if (strpos($raw, $marker) === false) {
                    $result['visual_experience_consolidation_9d5_ok'] = false;
                }
            }
            if (!$result['visual_experience_consolidation_9d5_ok']) {
                $result['error'] = '9D5 Visual Experience Consolidation contract is incomplete.';
                return $result;
            }
        }
        // 9D6: 2.2.631 retains all 9D5 assets and applies operative-only
        // nested-scroll enhancement with independent rollback and no API writes.
        if (version_compare((string)($result['version'] ?? ''), '2.2.631', '>=')) {
            $requirements9d6 = [
                'assets/fieldflow-operative-dock-parity-9d6.js' => [
                    'fieldflow-operative-dock-parity-9d6-v1', 'ffvDockAuthority',
                    "document.addEventListener('scroll',onDocumentScroll,true)"],
                'assets/fieldflow-operative-dock-parity-9d6.css' => [
                    '#routespro-app.ffv-visual-9d5.ffv-operative-parity-9d6', '--ffv-dock-height'],
                'assets/fieldflow-visual-consolidation-9d5.js' => ["root.dataset.ffvDockAuthority==='9d6'"],
                'src/Front/ResponsiveAssets.php' => [
                    'FIELDFLOW_OPERATIVE_PARITY_9D6', 'fieldflow-operative-dock-parity-9d6.css',
                    'fieldflow-operative-dock-parity-9d6.js', 'fieldflow-offline-runtime-' . $result['version'] . '.js'],
                'src/Front/ClientPortalShortcodeRenderer.php' => ['function statusLabel(status)'],
                'src/Cloud/Phase6StableRelease.php' => [
                    "public const SOURCE_RELEASE = '2.2.630';", "public const STABLE_RELEASE = '2.2.631';"],
                'tools/test-operative-dock-parity-9d6.php' => ['OPERATIVE DOCK PARITY 9D6 PHP PASS'],
                'tools/test-operative-dock-parity-gate-9d6.php' => ['OPERATIVE DOCK RELEASE GATE 9D6 PHP PASS'],
                'PHASE-9D6-OPERATIVE-DOCK-PARITY.md' => [
                    'fieldflow-operative-dock-parity-9d6-v1', '2.2.630 -> 2.2.631'],
            ];
            $result['operative_dock_parity_9d6_ok'] = true;
            foreach ($requirements9d6 as $relative=>$markers) {
                $raw=self::entryContents($entries,'fieldflow/'.$relative);
                foreach ($markers as $marker) if (strpos($raw,$marker)===false) {
                    $result['operative_dock_parity_9d6_ok']=false;
                }
            }
            if (!$result['operative_dock_parity_9d6_ok']) {
                $result['error']='9D6 Operative Dock Parity contract is incomplete.';
                return $result;
            }
        }
        // 9D7: the certified 2.2.631 contracts must be retained while
        // the operative WebView dock gains gesture parity and an actual reserve.
        if (version_compare((string)($result['version'] ?? ''), '2.2.632', '>=')) {
            $requirements9d7 = [
                'assets/fieldflow-operative-dock-overlay-9d7.js' => [
                    'fieldflow-operative-dock-overlay-9d7-v1', 'touchstart', 'touchmove',
                    'ffvDockAuthority', 'canScrollVertically'],
                'assets/fieldflow-operative-dock-overlay-9d7.css' => [
                    '#routespro-app.ffv-visual-9d5.ffv-operative-overlay-9d7',
                    'ffv-dock-hidden', '--ffv-dock-reserve', 'scroll-padding-bottom'],
                'assets/fieldflow-operative-dock-parity-9d6.js' => [
                    "root.dataset.ffvDockAuthority==='9d7'"],
                'assets/fieldflow-visual-consolidation-9d5.js' => [
                    "root.dataset.ffvDockAuthority==='9d6'", "root.dataset.ffvDockAuthority==='9d7'"],
                'src/Front/ResponsiveAssets.php' => [
                    'FIELDFLOW_OPERATIVE_OVERLAY_9D7',
                    'fieldflow-operative-dock-overlay-9d7.css',
                    'fieldflow-operative-dock-overlay-9d7.js',
                    'fieldflow-offline-runtime-' . $result['version'] . '.js'],
                'src/Cloud/Phase6StableRelease.php' => [
                    "public const SOURCE_RELEASE = '2.2.631';", "public const STABLE_RELEASE = '2.2.632';"],
                'tools/test-operative-dock-overlay-9d7.php' => ['OPERATIVE DOCK OVERLAY 9D7 PHP PASS'],
                'tools/test-operative-dock-overlay-gate-9d7.php' => ['OPERATIVE DOCK OVERLAY GATE 9D7 PHP PASS'],
                'PHASE-9D7-OPERATIVE-DOCK-OVERLAY-GESTURE-RECOVERY.md' => [
                    'fieldflow-operative-dock-overlay-9d7-v1', '2.2.631 -> 2.2.632'],
            ];
            $result['operative_dock_overlay_9d7_ok']=true;
            foreach($requirements9d7 as $relative=>$markers){
                $raw=self::entryContents($entries,'fieldflow/'.$relative);
                foreach($markers as $marker)if(strpos($raw,$marker)===false){
                    $result['operative_dock_overlay_9d7_ok']=false;
                }
            }
            if(!$result['operative_dock_overlay_9d7_ok']){
                $result['error']='9D7 Operative Dock Overlay contract is incomplete.';
                return $result;
            }
        }
        // 9D8: verify the shortcode-owned operative dock that also works when
        // Elementor or PWA page discovery does not enqueue presentation scripts.
        if (version_compare((string)($result['version'] ?? ''), '2.2.633', '>=')) {
            $requirements9d8 = [
                'assets/fieldflow-operative-dock-authority-9d8.js' => [
                    'fieldflow-operative-inline-dock-authority-9d8-v1', 'touchstart',
                    'touchmove', "root.dataset.ffvDockAuthority='9d8'", 'ffv-dock-collapsed-9d8'],
                'assets/fieldflow-operative-dock-authority-9d8.css' => [
                    '#routespro-app.ffv-dock-authority-9d8', 'visibility:hidden!important',
                    '--ffv-dock-reserve', 'scroll-padding-bottom'],
                'src/Front/AppShortcodeRenderer.php' => [
                    'FIELDFLOW_OPERATIVE_DOCK_AUTHORITY_9D8',
                    'fieldflow-operative-dock-authority-9d8.css',
                    'fieldflow-operative-dock-authority-9d8.js',
                    'data-fieldflow-contract="fieldflow-operative-inline-dock-authority-9d8-v1"'],
                'src/Support/ReleaseGate.php' => [
                    'OPERATIVE_INLINE_DOCK_AUTHORITY_9D8_CONTRACT',
                    'checkOperativeInlineDockAuthority9D8Contract'],
                'assets/fieldflow-visual-consolidation-9d5.js' => ["root.dataset.ffvDockAuthority==='9d8'"],
                'assets/fieldflow-operative-dock-parity-9d6.js' => ["root.dataset.ffvDockAuthority==='9d8'"],
                'assets/fieldflow-operative-dock-overlay-9d7.js' => ["root.dataset.ffvDockAuthority==='9d8'"],
                'src/Cloud/Phase6StableRelease.php' => [
                    "public const SOURCE_RELEASE = '2.2.632';", "public const STABLE_RELEASE = '2.2.633';"],
                'tools/test-operative-dock-authority-9d8.php' => ['OPERATIVE DOCK AUTHORITY 9D8 PHP PASS'],
                'tools/test-operative-dock-authority-gate-9d8.php' => ['OPERATIVE DOCK AUTHORITY GATE 9D8 PHP PASS'],
                'PHASE-9D8-OPERATIVE-INLINE-DOCK-AUTHORITY.md' => [
                    'fieldflow-operative-inline-dock-authority-9d8-v1', '2.2.632 -> 2.2.633'],
            ];
            $result['operative_inline_dock_authority_9d8_ok']=true;
            foreach ($requirements9d8 as $relative=>$markers) {
                $raw=self::entryContents($entries,'fieldflow/'.$relative);
                foreach ($markers as $marker) if (strpos($raw,$marker)===false) {
                    $result['operative_inline_dock_authority_9d8_ok']=false;
                }
            }
            if (!$result['operative_inline_dock_authority_9d8_ok']) {
                $result['error']='9D8 Operative Inline Dock Authority contract is incomplete.';
                return $result;
            }
        }
        // 9D9: fail closed if the operative fails to reuse the same Client 9D5 controller.
        if (version_compare((string)($result['version'] ?? ''), '2.2.634', '>=')) {
            $requirements9d9 = [
                'assets/fieldflow-visual-consolidation-9d5.js' => [
                    'function setupDock(root)', "dock.classList.add('ffv-dock-hidden')",
                    "dock.classList.remove('ffv-dock-hidden')"],
                'src/Front/AppShortcodeRenderer.php' => [
                    'FIELDFLOW_OPERATIVE_CLIENT_DOCK_9D9',
                    'data-fieldflow-dock-controller=', 'client-9d5-9d9',
                    'fieldflow-operative-client-dock-parity-9d9-v1',
                    "file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-visual-consolidation-9d5.js')",
                    '$dock9d8_enabled = !$shared_dock_enabled',
                    '.rp-tabs.ffv-dock-hidden'],
                'src/Front/ResponsiveAssets.php' => [
                    'FIELDFLOW_OPERATIVE_CLIENT_DOCK_9D9',
                    '$legacyOperativeDock', 'if ($legacyOperativeDock'],
                'src/Support/ReleaseGate.php' => [
                    'OPERATIVE_CLIENT_DOCK_PARITY_9D9_CONTRACT',
                    'checkOperativeClientDockParity9D9Contract'],
                // Active Phase6 identity is already validated above via release_continuity_ok.
                // Historical 9D9 functionality is verified through its actual runtime markers.
                'tools/test-operative-client-dock-parity-9d9.php' => [
                    'OPERATIVE CLIENT DOCK PARITY 9D9 PASS'],
                'tools/test-operative-client-dock-parity-gate-9d9.php' => [
                    'OPERATIVE CLIENT DOCK PARITY GATE 9D9 PASS'],
                'PHASE-9D9-OPERATIVE-CLIENT-DOCK-PARITY.md' => [
                    'ffv-dock-hidden', '2.2.634'],
            ];
            $result['operative_client_dock_parity_9d9_ok']=true;
            $missing9d9=[];
            foreach ($requirements9d9 as $relative=>$markers) {
                $raw=self::entryContents($entries,'fieldflow/'.$relative);
                foreach ($markers as $marker) if(strpos($raw,$marker)===false) {
                    $result['operative_client_dock_parity_9d9_ok']=false;
                    $missing9d9[]=$relative . ': ' . $marker;
                }
            }
            if (!$result['operative_client_dock_parity_9d9_ok']) {
                $result['error']='9D9 Operative Client Dock Parity contract is incomplete: '.implode('; ', array_slice($missing9d9, 0, 6));
                return $result;
            }
        }
        // 9DA: new assistance is additive and may not bypass any previous gate.
        if (version_compare((string)($result['version'] ?? ''), '2.2.635', '>=')) {
            $requirements9da = [
                'src/Support/ReleasePreflight.php' => [
                    'fieldflow-release-manager-assisted-preflight-9da-v1',
                    'function evaluate(', "'can_publish'", "'diagnostics'", "'diff_available'",
                ],
                'src/Admin/ReleaseManager.php' => [
                    'ReleasePreflight::evaluate(', 'ffrm-assisted-9da', 'ffrm-assisted-diagnostics',
                    "'publish_package'", 'confirm_publish',
                ],
                'src/Support/ReleaseGate.php' => [
                    'checkReleaseManagerAssistedPreflight9DAContract',
                    'checkReleaseContinuityAuthorityContract',
                ],
                'tools/test-release-manager-assisted-9da.php' => [
                    'RELEASE MANAGER ASSISTED PREFLIGHT 9DA PASS',
                ],
                'PHASE-9DA-RELEASE-MANAGER-ASSISTED-PREFLIGHT.md' => [
                    'fieldflow-release-manager-assisted-preflight-9da-v1',
                    '2.2.634 -> 2.2.635',
                ],
            ];
            foreach ($requirements9da as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) {
                    if (strpos($raw, $marker) === false) {
                        $result['error'] = '9DA Release Manager Assisted Preflight incomplete: ' . $relative . ' / ' . $marker;
                        return $result;
                    }
                }
            }
            $result['release_manager_assisted_preflight_9da_ok'] = true;
        }
        // 9DB: keep every prior gate; require assistance and recovery without version pinning.
        if (version_compare((string)($result['version'] ?? ''), '2.2.636', '>=')) {
            $requirements9db = [
                'src/Support/ReleaseStagingWorkflow.php' => [
                    'fieldflow-release-manager-assisted-workflow-9db-v1',
                    'function stage(', 'function publish(', 'function reconcile(',
                    'function resumeCatalog(', 'function confirmedInspect(',
                    'function verifiedPath(', 'function privateDirectory(',
                    'is_uploaded_file(', 'hash_file(', 'flock(', 'confirm',
                ],
                'src/Admin/ReleaseManager.php' => [
                    'ReleaseStagingWorkflow::stage(', 'ReleaseStagingWorkflow::publish(',
                    'ReleaseStagingWorkflow::reconcile(', 'ReleaseStagingWorkflow::resumeCatalog(',
                    'confirm_publish', 'confirm_discard', 'publish_package',
                ],
                'src/Support/ReleaseGate.php' => ['checkReleaseManagerAssistedWorkflow9DBContract'],
                'tools/test-release-manager-assisted-workflow-9db.php' => ['RELEASE MANAGER ASSISTED WORKFLOW 9DB PASS'],
                'PHASE-9DB-RELEASE-MANAGER-ASSISTED-WORKFLOW.md' => ['fieldflow-release-manager-assisted-workflow-9db-v1'],
            ];
            foreach ($requirements9db as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) {
                    if (strpos($raw, $marker) === false) {
                        $result['error'] = '9DB assisted workflow incomplete: ' . $relative . ' / ' . $marker;
                        return $result;
                    }
                }
            }
            $result['release_manager_assisted_workflow_9db_ok'] = true;
        }
        // 9DC: preserve 9D9 history while requiring a working operative-only owner.
        if (version_compare((string)($result['version'] ?? ''), '2.2.637', '>=')) {
            $requirements9dc = [
                'src/Front/AppShortcodeRenderer.php' => [
                    'FIELDFLOW_OPERATIVE_SCROLL_AUTHORITY_9DC',
                    'fieldflow-operative-dock-scroll-9dc.css',
                    'fieldflow-operative-dock-scroll-9dc.js',
                    'data-fieldflow-contract="fieldflow-operative-dock-scroll-authority-9dc-v1"',
                ],
                'assets/fieldflow-operative-dock-scroll-9dc.js' => [
                    "const CONTRACT='fieldflow-operative-dock-scroll-authority-9dc-v1'",
                    "root.dataset.ffvDockAuthority='9d8'",
                    'document.querySelectorAll(ROOT).forEach(enhance)',
                    "document.addEventListener('scroll',onDocumentScroll,true)",
                    "document.addEventListener('touchmove',onTouchMove",
                    'ffv-operative-dock-collapsed-9dc',
                    'syncDock()',
                ],
                'assets/fieldflow-operative-dock-scroll-9dc.css' => [
                    '#routespro-app.ffv-operative-dock-9dc',
                    'ffv-operative-dock-collapsed-9dc',
                    'scroll-padding-bottom',
                ],
                'tools/test-operative-dock-scroll-9dc.php' => ['OPERATIVE DOCK SCROLL 9DC PASS'],
                'PHASE-9DC-OPERATIVE-DOCK-SCROLL-AUTHORITY.md' => ['fieldflow-operative-dock-scroll-authority-9dc-v1'],
            ];
            foreach ($requirements9dc as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) {
                    if (strpos($raw, $marker) === false) {
                        $result['error'] = '9DC operative dock incomplete: ' . $relative . ' / ' . $marker;
                        return $result;
                    }
                }
            }
            $result['operative_dock_scroll_authority_9dc_ok'] = true;
        }
        // 9DD: opt-in runtime diagnostic with zero automatic behaviour change.
        if (version_compare((string)($result['version'] ?? ''), '2.2.638', '>=')) {
            $requires9dd = [
                'src/Front/AppShortcodeRenderer.php' => ['ff_dock_diag', 'data-ffdd-status',
                    'fieldflow-operative-dock-audit-9dd.js', 'fieldflow-operative-dock-audit-9dd.css'],
                'assets/fieldflow-operative-dock-audit-9dd.js' => [
                    "const CONTRACT='fieldflow-operative-dock-real-runtime-audit-9dd-v1'",
                    "document.addEventListener('scroll'", "document.addEventListener('touchmove'",
                    "el.style.setProperty('transform'", 'window.FieldFlowDockAudit9DD={snapshot,contract:CONTRACT}',
                ],
                'assets/fieldflow-operative-dock-audit-9dd.css' => ['#ff-dock-audit-9dd', 'z-index:2147483646'],
                'tools/test-operative-dock-runtime-audit-9dd.php' => ['OPERATIVE DOCK REAL RUNTIME AUDIT 9DD PASS'],
                'PHASE-9DD-OPERATIVE-DOCK-REAL-RUNTIME-AUDIT.md' => ['fieldflow-operative-dock-real-runtime-audit-9dd-v1'],
            ];
            foreach ($requires9dd as $relative=>$markers) {
                $raw = self::entryContents($entries,'fieldflow/'.$relative);
                foreach ($markers as $marker) {
                    if (strpos($raw,$marker) === false) {
                        $result['error']='9DD operative runtime audit incomplete: '.$relative.' / '.$marker;
                        return $result;
                    }
                }
            }
            $result['operative_dock_real_runtime_audit_9dd_ok']=true;
        }
        // 9DE: the real runtime must include a bootstrapped Operative owner,
        // not only historic assets that static certification can see.
        if (version_compare((string)($result['version'] ?? ''), '2.2.639', '>=')) {
            $requires9de = [
                'src/Front/AppShortcodeRenderer.php' => [
                    'FIELDFLOW_OPERATIVE_DOCK_BOOTSTRAP_9DE',
                    'data-fieldflow-dock-delivery-9de=',
                    'fieldflow-operative-dock-bootstrap-9de.js',
                    'fieldflow-operative-dock-bootstrap-9de.css',
                    'data-fieldflow-contract="fieldflow-operative-dock-bootstrap-recovery-9de-v1"',
                    '$dock9dc_enabled && !$dock9de_enabled',
                ],
                'src/Front/ResponsiveAssets.php' => ['FIELDFLOW_OPERATIVE_DOCK_BOOTSTRAP_9DE', '$legacyDockAllowed9de'],
                'assets/fieldflow-operative-dock-bootstrap-9de.js' => [
                    "const CONTRACT = 'fieldflow-operative-dock-bootstrap-recovery-9de-v1'",
                    "win.FieldFlowOperativeDock9DE = {",
                    "listen(doc, 'touchmove', onTouchMove",
                    "listen(doc, 'scroll', onScroll, true)",
                    'watcher.observe(doc.documentElement',
                    "root.dataset.ffvDockControllerActive = 'operative-9de'",
                ],
                'assets/fieldflow-operative-dock-bootstrap-9de.css' => [
                    '#routespro-app[data-fieldflow-portal="operative"].ffv-operative-dock-9de',
                    'ffv-operative-dock-collapsed-9de',
                    'scroll-padding-bottom',
                ],
                'tools/test-operative-dock-bootstrap-9de.php' => ['OPERATIVE DOCK BOOTSTRAP 9DE PASS'],
                'tools/test-operative-dock-bootstrap-9de.mjs' => ['OPERATIVE DOCK BOOTSTRAP 9DE JS PASS'],
                'PHASE-9DE-OPERATIVE-DOCK-BOOTSTRAP-RECOVERY.md' => [
                    'fieldflow-operative-dock-bootstrap-recovery-9de-v1',
                ],
            ];
            foreach ($requires9de as $relative=>$markers) {
                $raw = self::entryContents($entries, 'fieldflow/'.$relative);
                foreach ($markers as $marker) {
                    if (strpos($raw,$marker) === false) {
                        $result['error'] = '9DE operative dock bootstrap incomplete: '.$relative.' / '.$marker;
                        return $result;
                    }
                }
            }
            $result['operative_dock_bootstrap_recovery_9de_ok'] = true;
        }
        // 9DF: successor-safe contract; preserve 9DE and historical release gates.
        if (version_compare((string)($result['version'] ?? ''), '2.2.640', '>=')) {
            $required9df = [
                'assets/fieldflow-operative-dock-bootstrap-9de.js' => [
                    "const MOTION_CONTRACT = 'fieldflow-operative-dock-calm-motion-9df-v1'",
                    'const HOLD_VISIBLE_MS = 900',
                    'const TOUCH_SCROLL_GRACE_MS = 480',
                    'if (Date.now() - lastTouchMoveAt < TOUCH_SCROLL_GRACE_MS)',
                    "bar.style.setProperty('transform', 'translate3d(0,14px,0)'",
                ],
                'assets/fieldflow-operative-dock-bootstrap-9de.css' => [
                    'fieldflow-operative-dock-calm-motion-9df-v1',
                    'opacity .36s ease',
                    'visibility 0s linear .36s',
                ],
                'src/Performance/Phase4Baseline.php' => [
                    'fieldflow-phase4-observability-cost-baseline-9df-v1',
                    'ApiUsageLedger::summary(30)',
                    'GoogleCostControlCenter::status(30)',
                    'Monitor::summary()',
                ],
                'src/Admin/SecurityObservability.php' => [
                    'Phase4Baseline::collect($gate)',
                    'Fase 4, diagnóstico de performance e custos',
                ],
                'tools/test-operative-dock-calm-motion-9df.mjs' => ['OPERATIVE DOCK CALM MOTION 9DF JS PASS'],
                'tools/test-phase4-observability-9df.php' => ['PHASE 4 OBSERVABILITY 9DF PASS'],
                'PHASE-9DF-CALM-OPERATIVE-DOCK-PHASE4-BASELINE.md' => [
                    'fieldflow-operative-dock-calm-motion-9df-v1',
                    'fieldflow-phase4-observability-cost-baseline-9df-v1',
                ],
            ];
            foreach ($required9df as $relative=>$markers) {
                $raw = self::entryContents($entries,'fieldflow/'.$relative);
                foreach ($markers as $marker) {
                    if (strpos($raw,$marker)===false) {
                        $result['error']='9DF Phase4/dock contract missing: '.$relative.' / '.$marker;
                        return $result;
                    }
                }
            }
            $result['phase4_calm_operative_dock_9df_ok']=true;
        }
        // 9DG: read-only cost and P95 drivers; keep historic 9DF/9DE contracts.
        if (version_compare((string)($result['version'] ?? ''), '2.2.641', '>=')) {
            $required9dg = [
                'src/Performance/Phase4Drivers.php' => [
                    'fieldflow-phase4-cost-performance-drivers-9dg-v1',
                    'final class Phase4Drivers',
                    'ApiUsageLedger::byOperation(30, 80)',
                    'ApiUsageLedger::daily(14)',
                    'reference_rate_eur_per_1000',
                    'cost_unknown',
                    'public static function analyze(',
                ],
                'src/Admin/SecurityObservability.php' => [
                    'Phase4Drivers::collect($gate, $phase4)',
                    'Fase 4B, prioridades de custos e performance',
                    'data-fieldflow-phase4-drivers',
                    'Tarifas de referência em falta',
                ],
                'tools/test-phase4-cost-performance-drivers-9dg.php' => [
                    'PHASE 4 COST PERFORMANCE DRIVERS 9DG PASS',
                ],
                'PHASE-9DG-PHASE4-COST-PERFORMANCE-DRIVERS.md' => [
                    'fieldflow-phase4-cost-performance-drivers-9dg-v1',
                ],
            ];
            foreach ($required9dg as $relative=>$markers) {
                $raw = self::entryContents($entries, 'fieldflow/'.$relative);
                foreach ($markers as $marker) {
                    if (strpos($raw, $marker)===false) {
                        $result['error']='9DG Phase4 drivers contract missing: '.$relative.' / '.$marker;
                        return $result;
                    }
                }
            }
            $result['phase4_cost_performance_drivers_9dg_ok'] = true;
        }
        // 9DH successor-safe: real manifest key validation occurs above,
        // and code, UI, test and documented packaging pipeline must be present.
        if (version_compare((string)($result['version'] ?? ''), '2.2.642', '>=')) {
            $required9dh = [
                'src/Performance/Phase4AdminHotspots.php' => [
                    'fieldflow-phase4-admin-hotspots-manifest-safety-9dh-v1',
                    'final class Phase4AdminHotspots', 'MAX_ROWS = 2500',
                    'public static function analyze(', 'public static function collect(',
                ],
                'src/Admin/SecurityObservability.php' => [
                    'Phase4AdminHotspots::collect(', 'data-fieldflow-phase4-admin-hotspots',
                    'overviewSnapshotsRequired($tab)',
                    'Fase 4C, gargalos por página do Backoffice',
                ],
                'tools/build-production-manifest-9dh.py' => [
                    'canonical_keys(', 'key_files', 'inspect(', 'ZIP 9DH OK',
                ],
                'tools/test-phase4-admin-hotspots-manifest-9dh.php' => [
                    'PHASE 4 ADMIN HOTSPOTS MANIFEST 9DH PASS',
                ],
                'PHASE-9DH-ADMIN-HOTSPOTS-MANIFEST-SAFETY.md' => [
                    'fieldflow-phase4-admin-hotspots-manifest-safety-9dh-v1',
                ],
            ];
            foreach ($required9dh as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) {
                    if (strpos($raw, $marker) === false) {
                        $result['error'] = '9DH Phase4/manifest contract missing: ' . $relative . ' / ' . $marker;
                        return $result;
                    }
                }
            }
            $result['phase4_admin_hotspots_manifest_safety_9dh_ok'] = true;
        }
        // 9DI: fail closed on missing / regressed Phase 4D admin-health optimization.
        if (version_compare((string)($result['version'] ?? ''), '2.2.643', '>=')) {
            $required9di = [
                'src/Performance/Phase4AdminHealth.php' => [
                    'fieldflow-phase4-admin-health-snapshot-reuse-9di-v1',
                    'final class Phase4AdminHealth', 'integrationCounts()',
                    'SELECT id FROM ', 'SELECT status FROM ', 'LIMIT 500',
                ],
                'src/Admin/SystemHealth.php' => [
                    "is_array(\$report['offline'] ?? null)",
                    "is_array(\$report['backoffice_ui'] ?? null)",
                    'Phase4AdminHealth::integrationCounts()', '$plansReady',
                ],
                'src/Support/SystemHealth.php' => ["'offline' => \$offline, // 9DI"],
                'tools/test-phase4-admin-health-9di.php' => [
                    'PHASE 4 ADMIN HEALTH 9DI PASS',
                ],
                'PHASE-9DI-ADMIN-HEALTH-SNAPSHOT-REUSE.md' => [
                    'fieldflow-phase4-admin-health-snapshot-reuse-9di-v1',
                ],
            ];
            foreach ($required9di as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) {
                    if (strpos($raw, $marker) === false) {
                        $result['error'] = '9DI Phase4 admin-health contract missing: ' . $relative . ' / ' . $marker;
                        return $result;
                    }
                }
            }
            $result['phase4_admin_health_snapshot_reuse_9di_ok'] = true;
        }
        // 9DJ: fail-closed certificate freshness separation and scoped mobile performance layout.
        if (version_compare((string)($result['version'] ?? ''), '2.2.644', '>=')) {
            $required9dj = [
                'src/Cloud/Phase6StableRelease.php' => [
                    'bool $forceFreshCertification = true',
                    'if ($forceFreshCertification)',
                    'ProductionManifest::invalidateCache()',
                    'ProductionManifest::verify(true)',
                    'ProductionManifest::verify(); // 9DJ read-only admin snapshot',
                ],
                'src/Admin/LicenseTenants.php' => [
                    'Phase6StableRelease::localProbe(false,',
                    'REQUEST_METHOD', "!== 'GET'",
                ],
                'src/Admin/SecurityObservability.php' => [
                    'ff-ops-performance-9dj',
                    'ff-ops-responsive-table-9dj',
                    'data-label="P95 queries"',
                    'n/d (sem tarifas)',
                    'Gate de performance',
                ],
                'assets/fieldflow-admin-components.css' => [
                    '.ff-ops-performance-9dj',
                    '.ff-ops-process-grid-9dj',
                    '.ff-ops-responsive-table-9dj',
                    'content:attr(data-label)',
                ],
                'tools/test-phase4e-admin-mobile-9dj.php' => ['PHASE 4E 9DJ PASS'],
                'PHASE-9DJ-ADMIN-PERFORMANCE-MOBILE-READABILITY.md' => [
                    'fieldflow-phase4e-admin-cert-readonly-mobile-layout-9dj-v1',
                ],
            ];
            foreach ($required9dj as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) {
                    if (strpos($raw, $marker) === false) {
                        $result['error'] = '9DJ Phase4E admin mobile/read-only contract missing: ' . $relative . ' / ' . $marker;
                        return $result;
                    }
                }
            }
            $result['phase4e_admin_readonly_mobile_9dj_ok'] = true;
        }
        // 9DK: N+1 source contract for briefing interpretation and route authority.
        if (version_compare((string)($result['version'] ?? ''), '2.2.645', '>=')) {
            $required9dk = [
                'src/Briefings/PernodWeeklyImporter.php' => ['$seenNarrative', 'count($uniqueVisible) === 1', 'DateTimeImmutable'],
                'src/Stocks/StockImporter.php' => ["['pack', 'packs', 'embalagem']", "unset(\$candidate['mapping']['conversion_factor']"],
                'src/Briefings/BriefingService.php' => ['routeLocationIdsForBriefing(', 'storeVisibility9DK(', "'briefing_only'"],
                'src/Front/BriefingsShortcodeRenderer.php' => ['Na tua rota', 'Só briefing'],
                'src/Forms/ProductCardex.php' => ["'review_types'", "'review_count'", 'confirmar com a marca'],
                'src/Stores/StoreProfileCard.php' => ['Perfis do Cardex a confirmar com a marca'],
                'tools/test-briefing-stock-scope-9dk.php' => ['BRIEFING STOCK SCOPE 9DK PASS'],
                'PHASE-9DK-BRIEFING-STOCK-SCOPE-INTEGRITY.md' => ['fieldflow-weekly-import-store-scope-integrity-9dk-v1'],
            ];
            foreach ($required9dk as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) {
                    if (strpos($raw, $marker) === false) {
                        $result['error'] = '9DK briefing/stock/route scope contract missing: ' . $relative . ' / ' . $marker;
                        return $result;
                    }
                }
            }
            $result['briefing_stock_route_scope_9dk_ok'] = true;
        }

        // 9DL: N+1 source contract for route-only briefing, safe store identity and mobile filters.
        if (version_compare((string)($result['version'] ?? ''), '2.2.646', '>=')) {
            $required9dl = [
                'src/Briefings/BriefingService.php' => ["if (\$scope !== 'assigned')", 'excluded_store_items', 'if (!$relevant) continue;'],
                'src/Stocks/StockService.php' => ["'SONAE','SONAE MC'", 'locationMatchesInsignia(', 'cross_insignia_rows', 'repairBatchScope('],
                'src/Front/BriefingsShortcodeRenderer.php' => ['ffb-mobile-records', 'ffb-responsive-records', 'ffb-scope-banner'],
                'src/Front/StocksShortcodeRenderer.php' => ['rebuildStoreOptions(', 'selectedInsignia', 'data-filter-feedback'],
                'tools/test-briefing-stock-mobile-9dl.php' => ['BRIEFING STOCK MOBILE 9DL PASS'],
            'frontend/tests/stocks-insignia-filter-9dl.test.mjs' => ['9DL: switching Sonae / Pingo Doce', 'brand.onchange();'],
                'PHASE-9DL-BRIEFING-STOCK-MOBILE-SCOPE.md' => ['fieldflow-briefing-stock-mobile-scope-integrity-9dl-v1'],
            ];
            foreach ($required9dl as $relative=>$markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) {
                    if (strpos($raw,$marker) === false) {
                        $result['error'] = '9DL briefing/stock/mobile route identity contract missing: ' . $relative . ' / ' . $marker;
                        return $result;
                    }
                }
            }
            $result['briefing_stocks_mobile_scope_9dl_ok'] = true;
        }
        // 9DM: N+1 closed-world store identity and non-destructive mapping review.
        if (version_compare((string)($result['version'] ?? ''), '2.2.647', '>=')) {
            $required9dm = [
                'src/Stocks/StockService.php' => [
                    "'arcol' => ['ARCOL']", "'mcunha' => ['M CUNHA'",
                    'sourceMatchesInsignia(', 'explicitLocationFamily(',
                    'invalidBatchStoreAssociations(', 'count($found) !== 1',
                ],
                'src/Stocks/StockStoreMatcher.php' => [
                    'StockService::sourceMatchesLocation(', "'arcol' => ['ARCOL']",
                    "'mcunha' => ['M CUNHA'", 'StockService::sourceMatchesInsignia(',
                ],
                'src/Admin/Stocks.php' => ['review_count', 'Nenhum stock nem mapeamento foi apagado'],
                'tools/test-stock-insignia-authority-9dm.php' => ['STOCK INSIGNIA AUTHORITY 9DM PASS'],
            'tools/test-release-gate-runtime-9dm.php' => ['RELEASE GATE RUNTIME 9DM PASS'],
                'PHASE-9DM-STOCK-INSIGNIA-IDENTITY-AUTHORITY.md' => [
                    'fieldflow-stock-insignia-identity-authority-9dm-v1',
                ],
            ];
            foreach ($required9dm as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) {
                    if (strpos($raw, $marker) === false) {
                        $result['error'] = '9DM stock insignia authority contract missing: ' . $relative . ' / ' . $marker;
                        return $result;
                    }
                }
            }
            $result['stock_insignia_authority_9dm_ok'] = true;
        }
        // 9DN: universal closed-world identity is required for every store consumer.
        if (version_compare((string)($result['version'] ?? ''), '2.2.648', '>=')) {
            $required9dn = [
                'src/Stores/StoreIdentityAuthority.php' => [
                    'fieldflow-universal-store-identity-authority-9dn-v1',
                    'sourceMatchesLocation(', 'canUpdateLocation(', 'targetSubtype(',
                ],
                'src/Briefings/BriefingService.php' => ['StoreIdentityAuthority::sourceMatchesLocation(', 'identity_review_required'],
                'src/Admin/Briefings.php' => ['identity_review_required', 'Por rever'],
                'src/Mapping/MappingEngine.php' => ['StoreIdentityAuthority::sourceMatchesLocation('],
                'src/Mapping/MappingService.php' => ['storeTargetCompatible(', 'StoreIdentityAuthority::sourceMatchesLocation('],
                'src/Shopping/ShoppingService.php' => ['StoreIdentityAuthority::locationMatches(', 'locationMatchesInsigniaStrict('],
                'src/Implementations/ImplementationService.php' => ['StoreIdentityAuthority::sourceMatchesLocation('],
                'src/Forms/ProductCardex.php' => ['StoreIdentityAuthority::locationMatches(', 'LIMIT 20', 'cardex_name'],
                'src/Import/CSVImporter.php' => ['StoreIdentityAuthority::canUpdateLocation('],
                'tools/test-universal-store-identity-9dn.php' => ['UNIVERSAL STORE IDENTITY 9DN PASS'],
            'tools/test-cardex-store-identity-9dn.php' => ['CARDEX STORE IDENTITY 9DN PASS'],
                'tools/test-release-gate-runtime-9dn.php' => ['RELEASE GATE RUNTIME 9DN PASS'],
                'PHASE-9DN-UNIVERSAL-STORE-IDENTITY-AUTHORITY.md' => ['fieldflow-universal-store-identity-authority-9dn-v1'],
            ];
            foreach ($required9dn as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) {
                    if (strpos($raw, $marker) === false) {
                        $result['error'] = '9DN universal store identity contract missing: ' . $relative . ' / ' . $marker;
                        return $result;
                    }
                }
            }
            $result['universal_store_identity_authority_9dn_ok'] = true;
        }
        // 9DO: fail closed on partial Daily Close, stale sending and unavailable reliable Outbox.
        if (version_compare((string)($result['version'] ?? ''), '2.2.649', '>=')) {
            $required9do = [
                'src/Admin/ReportAutomations.php' => ['recipientDispatchIdentity(', 'daily_close_partial', 'recordDailyCloseDiagnostic(', 'legacy_idempotency_key', 'reliable_required'],
                'src/Services/MailDispatchService.php' => ['reliableBlocked(', "'uncertain'", 'legacy_idempotency_key', "'status'=>'sending'"],
                'src/Services/MailOutboxRepository.php' => ["SET status='uncertain'", "'uncertain'=>0"],
                'src/Admin/Emails.php' => ['fieldflow_daily_close_diagnostics_9do', 'Confirmar e reabrir'],
                'tools/test-daily-close-recipient-reliability-9do.php' => ['DAILY CLOSE RECIPIENT RELIABILITY 9DO PASS'],
                'tools/test-release-gate-runtime-9do.php' => ['RELEASE GATE RUNTIME 9DO PASS'],
                'PHASE-9DO-DAILY-CLOSE-RECIPIENT-RELIABILITY.md' => ['fieldflow-daily-close-recipient-reliability-9do-v1'],
            ];
            foreach ($required9do as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) if (strpos($raw, $marker) === false) {
                    $result['error'] = '9DO daily close reliability contract missing: ' . $relative . ' / ' . $marker;
                    return $result;
                }
            }
            $result['daily_close_recipient_reliability_9do_ok'] = true;
        }
        // 9DP: publish cloned assortments only after store scope and SKU integrity are approved.
        if (version_compare((string)($result['version'] ?? ''), '2.2.650', '>=')) {
            $required9dp = [
                'src/Admin/CardexCloneService.php' => ['fieldflow-safe-cardex-clone-publication-9dp-v1','function duplicate(', 'function publish(', 'locationInScope(', "'status'=>'draft'"],
                'src/Admin/ProductCardex.php' => ['routespro_duplicate_product_cardex', 'routespro_publish_product_cardex', 'CardexCloneService::supportedInsignia('],
                'src/Forms/ProductCardex.php' => ['clone_scope', 'clone_source_id'],
                'src/Catalog/ProductCatalogService.php' => ["(\$cardex['status'] ?? 'active') !== 'active'"],
                'src/Briefings/CardexSyncService.php' => ["if (!empty(\$meta['clone_source_id'])) continue;"],
                'tools/test-safe-cardex-clone-publication-9dp.php' => ['SAFE CARDEX CLONE PUBLICATION 9DP PASS'],
                'tools/test-cardex-csv-identity-9dp.php' => ['CARDEX CSV INSIGNIA SKU 9DP PASS'],
                'tools/test-release-gate-runtime-9dp.php' => ['RELEASE GATE RUNTIME 9DP PASS'],
                'PHASE-9DP-SAFE-CARDEX-CLONE-PUBLICATION.md' => ['fieldflow-safe-cardex-clone-publication-9dp-v1'],
            ];
            foreach ($required9dp as $relative=>$markers) {
                $raw = self::entryContents($entries,'fieldflow/'.$relative);
                foreach ($markers as $marker) if (strpos($raw,$marker)===false) {
                    $result['error']='9DP cardex clone publication contract missing: '.$relative.' / '.$marker;
                    return $result;
                }
            }
            $result['safe_cardex_clone_publication_9dp_ok']=true;
        }
        // 9DQ: support new commercial retailers without bypassing brand and store authority.
        if (version_compare((string)($result['version'] ?? ''), '2.2.651', '>=')) {
            $required9dq = [
                'src/Admin/CardexCloneService.php' => [
                    'fieldflow-flexible-cardex-insignia-registry-9dq-v1','function canonicalInsignia(',
                    'function rememberInsignia(', 'function locationMatchesInsignia(', 'locationInScope('],
                'src/Admin/ProductCardex.php' => ['ff-cardex-brand-options','name="destination_insignia"',
                    'CardexCloneService::canonicalInsignia(', 'CardexCloneService::locationMatchesInsignia('],
                'src/Forms/ProductCardex.php' => ['CardexCloneService::locationMatchesInsignia(', 'clone_scope'],
                'tools/test-flexible-cardex-insignia-registry-9dq.php' => ['FLEXIBLE CARDEX INSIGNIA REGISTRY 9DQ PASS'],
                'tools/test-release-gate-runtime-9dq.php' => ['RELEASE GATE RUNTIME 9DQ PASS'],
                'PHASE-9DQ-FLEXIBLE-CARDEX-INSIGNIA-REGISTRY.md' => ['fieldflow-flexible-cardex-insignia-registry-9dq-v1'],
            ];
            foreach ($required9dq as $relative=>$markers) {
                $raw=self::entryContents($entries,'fieldflow/'.$relative);
                foreach ($markers as $marker) if (strpos($raw,$marker)===false) {
                    $result['error']='9DQ flexible cardex insignia registry contract missing: '.$relative.' / '.$marker;
                    return $result;
                }
            }
            $result['flexible_cardex_insignia_registry_9dq_ok']=true;
        }
        // 9DR: preserve historical Form Cardex bindings without weakening retailer isolation.
        if (version_compare((string)($result['version'] ?? ''), '2.2.652', '>=')) {
            $required9dr = [
                'src/Forms/ProductCardex.php' => [
                    'cardex_retailer_matches_location(', 'StoreIdentityAuthority::locationMatches(',
                    'StoreIdentityAuthority::targetSubtype(', 'CardexCloneService::locationMatchesInsignia(',
                    'question_is_available_for_context(', 'synchronise_order_questions_with_fronts(',
                ],
                'src/Briefings/CardexSyncService.php' => [
                    "(\$meta['retailer_key'] ?? '') !== \$retailer", "(\$meta['managed_by'] ?? '') !== self::MANAGED_BY",
                    'StoreIdentityAuthority::subtype(', 'clone_source_id',
                ],
                'tools/test-form-cardex-identity-continuity-9dr.php' => ['FORM CARDEX IDENTITY CONTINUITY 9DR PASS'],
                'tools/test-weekly-cardex-scope-9dr.php' => ['WEEKLY CARDEX SCOPE 9DR PASS'],
                'tools/test-release-gate-runtime-9dr.php' => ['RELEASE GATE RUNTIME 9DR PASS'],
                'PHASE-9DR-CARDEX-FORM-BINDING-CONTINUITY.md' => ['fieldflow-cardex-form-binding-continuity-9dr-v1'],
            ];
            foreach ($required9dr as $relative=>$markers) {
                $raw=self::entryContents($entries,'fieldflow/'.$relative);
                foreach ($markers as $marker) if (strpos($raw,$marker)===false) {
                    $result['error']='9DR form cardex identity contract missing: '.$relative.' / '.$marker;
                    return $result;
                }
            }
            $result['cardex_form_binding_continuity_9dr_ok']=true;
        }
        // 9DS: a configured Frentes question must never be silently removed
        // when a cardex is unavailable, including on a submitted visit.
        if (version_compare((string)($result['version'] ?? ''), '2.2.653', '>=')) {
            $required9ds = [
                'src/Forms/ProductCardex.php' => ['cardex_retailer_evidence(', 'question_binding_issue(', 'cardex_retailer_matches_location('],
                'src/Forms/FormRenderer.php' => ['render_unavailable_cardex_question(', 'data-rp-cardex-unavailable="1"', 'Esta pergunta está temporariamente indisponível'],
                'src/Forms/Forms.php' => ['$daily_existing && array_key_exists($key, $existing_prefill)', 'As respostas existentes desta visita foram preservadas'],
                'src/Mobile/SubmissionService.php' => ['$is_update && array_key_exists($key, $prior_visit_answers)', 'DailySubmissionService::submission_prefill('],
                'src/Mobile/FormPayloadService.php' => ["['binding_error']", "['read_only']", "['cardex_available']"],
                'tools/test-form-cardex-visible-binding-9ds.php' => ['FORM CARDEX VISIBLE BINDING 9DS PASS'],
                'tools/test-release-gate-runtime-9ds.php' => ['RELEASE GATE RUNTIME 9DS PASS'],
                'PHASE-9DS-CARDEX-FORM-VISIBLE-BINDING.md' => ['fieldflow-cardex-visible-binding-9ds-v1'],
            ];
            foreach ($required9ds as $relative=>$markers) {
                $raw=self::entryContents($entries,'fieldflow/'.$relative);
                foreach ($markers as $marker) if (strpos($raw,$marker)===false) {
                    $result['error']='9DS visible form Cardex contract missing: '.$relative.' / '.$marker;
                    return $result;
                }
            }
            $result['cardex_visible_form_binding_9ds_ok']=true;
        }
        // 9DT: a real linked Sonae shop needs a route-context diagnostic, not a silent bypass.
        if (version_compare((string)($result['version'] ?? ''),'2.2.654','>=')) {
            $required9dt = [
                'src/Forms/ProductCardex.php'=>['inspect_location_binding(',"'project_mismatch'", "'retailer_mismatch'"],
                'src/Admin/ProductCardex.php'=>['commercial_scope_choices(', 'render_binding_diagnostics(', 'diagnose_location_id', 'scope_only', 'confirm_scope_change'],
                'tools/test-cardex-scope-truth-9dt.php'=>['CARDEX SCOPE TRUTH 9DT PASS'],
                'tools/test-release-gate-runtime-9dt.php'=>['RELEASE GATE RUNTIME 9DT PASS'],
                'PHASE-9DT-CARDEX-CLIENT-PROJECT-SCOPE-TRUTH.md'=>['fieldflow-cardex-client-project-scope-truth-9dt-v1'],
            ];
            foreach ($required9dt as $relative=>$markers) {
                $raw=self::entryContents($entries,'fieldflow/'.$relative);
                foreach ($markers as $m) if (strpos($raw,$m)===false) {
                    $result['error']='9DT cardex scope truth contract missing: '.$relative.' / '.$m;
                    return $result;
                }
            }
            $result['cardex_scope_truth_9dt_ok']=true;
        }
        // 9DU: cross-store Cardex diagnostics and tightly scoped historical Sonae group evidence.
        if (version_compare((string)($result['version'] ?? ''),'2.2.655','>=')) {
            $required9du = [
                'src/Forms/ProductCardex.php'=>['legacy_sonae_umbrella(','legacy_sonae_umbrella($cardex)','legacy_sonae_umbrella'],
                'src/Admin/ProductCardex.php'=>['render_global_binding_audit(', 'audit_bindings', 'audit_client', 'audit_cardex', 'audit_store'],
                'tools/test-cardex-global-scope-9du.php'=>['CARDEX GLOBAL LINKED SCOPE 9DU PASS'],
                'tools/test-release-gate-runtime-9du.php'=>['RELEASE GATE RUNTIME 9DU PASS'],
                'PHASE-9DU-CARDEX-GLOBAL-LINKED-SCOPE.md'=>['fieldflow-cardex-global-linked-scope-authority-9du-v1'],
            ];
            foreach ($required9du as $relative=>$markers) {
                $raw=self::entryContents($entries,'fieldflow/'.$relative);
                foreach ($markers as $marker) if (strpos($raw,$marker)===false) {
                    $result['error']='9DU cardex global scope authority missing: '.$relative.' / '.$marker;
                    return $result;
                }
            }
            $result['cardex_global_linked_scope_9du_ok']=true;
        }
        // 9DV: a positive order may only arise from a confirmed zero or an explicit manual edit.
        if (version_compare((string)($result['version'] ?? ''),'2.2.656','>=')) {
            $required9dv = [
                'src/Forms/OrderSuggestionIntegrity.php'=>['fieldflow-order-suggestion-integrity-9dv-v1','public static function normalise(', 'public static function confirmedZero(', 'fronts_confirmed'],
                'src/Forms/ProductCardex.php'=>['normalise_order_submission_rows(', 'if ($uses_cardex && !$same_cardex) continue;'],
                'src/Forms/Forms.php'=>['ProductCardex::normalise_order_submission_rows('],
                'src/Mobile/SubmissionService.php'=>['ProductCardex::normalise_order_submission_rows('],
                'src/Forms/FormRenderer.php'=>['function pairedFrontQuestion(', 'data-fronts-confirmed', 'function syncOrderSuggestionForRow('],
                'tools/test-order-suggestion-integrity-9dv.php'=>['ORDER SUGGESTION INTEGRITY 9DV PASS'],
                'tools/test-release-gate-runtime-9dv.php'=>['RELEASE GATE RUNTIME 9DV PASS'],
                'PHASE-9DV-ORDER-SUGGESTION-INTEGRITY.md'=>['fieldflow-order-suggestion-integrity-9dv-v1'],
            ];
            foreach ($required9dv as $relative=>$markers) {
                $raw=self::entryContents($entries,'fieldflow/'.$relative);
                foreach ($markers as $marker) if (strpos($raw,$marker)===false) {
                    $result['error']='9DV order suggestion integrity missing: '.$relative.' / '.$marker;
                    return $result;
                }
            }
            $result['order_suggestion_integrity_9dv_ok']=true;
        }
        // 9DW: read-only store, stock and briefing diagnostics and auto-mode UX.
        if (version_compare((string)($result['version'] ?? ''),'2.2.657','>=')) {
            $required9dw = [
                'src/Admin/CardexConsistency.php' => [
                    'fieldflow-cardex-data-consistency-center-9dw-v1',
                    'public static function inspectStore(', 'private static function stockSnapshot(',
                    'private static function briefingQuestions(', 'duplicate_sku', 'missing_cardex',
                ],
                'src/Admin/ProductCardex.php' => ['CardexConsistency::render();', 'consistency=1'],
                'src/Admin/Forms.php' => ['twt-fb-cardex-fixed-picker', 'twt-fb-auto-cardex-note'],
                'assets/routespro-form-builder.js' => [".toggle(source === 'cardex_fixed')", ".toggle(source === 'cardex_auto')"],
                'tools/test-cardex-data-consistency-9dw.php' => ['CARDEX DATA CONSISTENCY 9DW PASS'],
                'tools/test-release-gate-runtime-9dw.php' => ['RELEASE GATE RUNTIME 9DW PASS'],
                'PHASE-9DW-CARDEX-DATA-CONSISTENCY.md' => ['fieldflow-cardex-data-consistency-center-9dw-v1'],
            ];
            foreach ($required9dw as $relative=>$markers) {
                $raw=self::entryContents($entries,'fieldflow/'.$relative);
                foreach ($markers as $marker) if (strpos($raw,$marker)===false) {
                    $result['error']='9DW Cardex data consistency contract missing: '.$relative.' / '.$marker;
                    return $result;
                }
            }
            $result['cardex_data_consistency_9dw_ok']=true;
        }
        // 9DX: briefing-generated question categories; answers and keys remain unchanged.
        if (version_compare((string)($result['version'] ?? ''), '2.2.658', '>=')) {
            $required9dx = [
                'src/Briefings/BriefingSteps.php' => [
                    'fieldflow-briefing-steps-continuity-9dx-v1',
                    'public static function save(', 'fieldflow_briefing_steps_9dx_',
                ],
                'src/Admin/Briefings.php' => [
                    'BriefingSteps::all(', 'BriefingSteps::save(', 'briefing_step_key',
                    'save_steps', 'question_step_keys',
                ],
                'src/Forms/BriefingFormBridge.php' => [
                    'stepGroupsForBriefing(', 'expandLayoutSteps(', 'briefing_step_key',
                    'replacementStepMap',
                ],
                'src/Forms/ContextQuestions.php' => ['briefing_step_key'],
                'tools/test-briefing-steps-9dx.php' => ['BRIEFING STEPS CONTINUITY 9DX PASS'],
                'tools/test-release-gate-runtime-9dx.php' => ['RELEASE GATE RUNTIME 9DX PASS'],
                'PHASE-9DX-BRIEFING-STEPS-CONTINUITY.md' => ['fieldflow-briefing-steps-continuity-9dx-v1'],
            ];
            foreach ($required9dx as $relative => $markers) {
                $raw = self::entryContents($entries, 'fieldflow/' . $relative);
                foreach ($markers as $marker) if (strpos($raw, $marker) === false) {
                    $result['error'] = '9DX briefing steps continuity missing: ' . $relative . ' / ' . $marker;
                    return $result;
                }
            }
            $result['briefing_steps_continuity_9dx_ok'] = true;
        }
        $result['ok'] = true;
        return $result;
    }

    private static function readArchive(string $path): array {
        if (class_exists('ZipArchive')) return self::readWithZipArchive($path);
        if (class_exists('PharData')) return self::readWithPharData($path);

        $pcl = defined('ABSPATH') ? ABSPATH . 'wp-admin/includes/class-pclzip.php' : '';
        if ($pcl && is_file($pcl)) require_once $pcl;
        if (class_exists('PclZip')) return self::readWithPclZip($path);
        throw new \RuntimeException('O servidor não tem suporte ZIP suficiente para validar releases privadas.');
    }

    private static function readWithZipArchive(string $path): array {
        $zip = new \ZipArchive();
        $flags = defined('ZipArchive::CHECKCONS') ? \ZipArchive::CHECKCONS : 0;
        if ($zip->open($path, $flags) !== true) throw new \RuntimeException('O ficheiro não é um ZIP válido.');
        $entries = [];
        try {
            $num = (int)$zip->numFiles;
            if ($num > self::MAX_ENTRIES) throw new \RuntimeException('Número de entradas do ZIP fora do limite de segurança.');
            for ($i = 0; $i < $num; $i++) {
                $stat = $zip->statIndex($i);
                if (!is_array($stat)) continue;
                $name = str_replace('\\', '/', (string)($stat['name'] ?? ''));
                $directory = substr($name, -1) === '/';
                $entries[] = [
                    'name'=>$name,
                    'directory'=>$directory,
                    'symlink'=>!$directory && self::isZipSymlink($zip, $i),
                    'contents'=>$directory ? '' : $zip->getFromIndex($i),
                ];
            }
        } finally {
            $zip->close();
        }
        return ['engine'=>'ZipArchive','entries'=>$entries];
    }

    private static function readWithPharData(string $path): array {
        try {
            $phar = new \PharData($path);
            $iterator = new \RecursiveIteratorIterator($phar, \RecursiveIteratorIterator::LEAVES_ONLY);
            $prefix = 'phar://' . str_replace('\\', '/', $path) . '/';
            $entries = [];
            foreach ($iterator as $file) {
                /** @var \PharFileInfo $file */
                $full = str_replace('\\', '/', (string)$file->getPathName());
                $name = strpos($full, $prefix) === 0 ? substr($full, strlen($prefix)) : '';
                if ($name === '') continue;
                $entries[] = [
                    'name'=>$name,
                    'directory'=>false,
                    'symlink'=>$file->isLink(),
                    'contents'=>$file->getContent(),
                ];
                if (count($entries) > self::MAX_ENTRIES) throw new \RuntimeException('Número de entradas do ZIP fora do limite de segurança.');
            }
            return ['engine'=>'PharData','entries'=>$entries];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Não foi possível abrir o ZIP com PharData: ' . $e->getMessage());
        }
    }

    private static function readWithPclZip(string $path): array {
        $archive = new \PclZip($path);
        $list = $archive->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
        if (!is_array($list)) throw new \RuntimeException('Não foi possível abrir o ZIP com PclZip.');
        $entries = [];
        foreach ($list as $entry) {
            if (!is_array($entry)) continue;
            $name = str_replace('\\', '/', (string)($entry['filename'] ?? ''));
            $directory = !empty($entry['folder']) || substr($name, -1) === '/';
            $entries[] = [
                'name'=>$name,
                'directory'=>$directory,
                'symlink'=>false,
                'contents'=>$directory ? '' : (string)($entry['content'] ?? ''),
            ];
            if (count($entries) > self::MAX_ENTRIES) throw new \RuntimeException('Número de entradas do ZIP fora do limite de segurança.');
        }
        return ['engine'=>'PclZip','entries'=>$entries];
    }

    private static function previousPatchRelease(string $version): string {
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)(?:\.(\d+))?$/', $version, $m)) return '';
        $patch = (int)$m[3];
        if ($patch <= 0) return '';
        // Four-segment builds are safety hotfixes of an uncertified candidate.
        // Their certified predecessor remains the previous patch release.
        return (int)$m[1] . '.' . (int)$m[2] . '.' . ($patch - 1);
    }

    private static function entryContents(array $entries, string $name): string {
        foreach ($entries as $entry) {
            if (str_replace('\\', '/', (string)($entry['name'] ?? '')) === $name && is_string($entry['contents'] ?? null)) {
                return (string)$entry['contents'];
            }
        }
        return '';
    }

    private static function isAllowedReleasePath(string $name): bool {
        $lower = strtolower($name);
        foreach ([
            '/.git/', '/.svn/', '/.hg/', '/node_modules/', '/vendor/bin/',
            '/.env', '.pem', '.pfx', '.p12', '.key', 'id_rsa', 'id_ed25519',
            'wp-config.php', '.htpasswd',
        ] as $needle) {
            if (strpos('/' . $lower, $needle) !== false) return false;
        }
        return true;
    }

    private static function isZipSymlink(\ZipArchive $zip, int $index): bool {
        $opsys = 0;
        $attr = 0;
        if (!method_exists($zip, 'getExternalAttributesIndex')) return false;
        if (!$zip->getExternalAttributesIndex($index, $opsys, $attr)) return false;
        // ZIP operating-system code 3 is Unix. The symbolic-link file type is 0xA000.
        if ((int)$opsys !== 3) return false;
        $mode = ($attr >> 16) & 0xF000;
        return $mode === 0xA000;
    }

    private static function normaliseRelative(string $relative): string {
        $relative = str_replace('\\', '/', ltrim($relative, '/\\'));
        if ($relative === '' || strpos($relative, '../') !== false || strpos($relative, '/..') !== false) return '';
        return $relative;
    }
}
